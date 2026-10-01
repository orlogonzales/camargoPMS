<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios\Pagos\Adaptadores;

use CamargoPMS\Servicios\Pagos\DTOs\ConsultaOrdenResultado;
use CamargoPMS\Servicios\Pagos\DTOs\ConsultaTransaccionResultado;
use CamargoPMS\Servicios\Pagos\DTOs\IntencionPagoResultado;
use CamargoPMS\Servicios\Pagos\DTOs\IntencionPagoSolicitud;
use CamargoPMS\Servicios\Pagos\DTOs\ReembolsoResultado;
use CamargoPMS\Servicios\Pagos\DTOs\ReembolsoSolicitud;
use CamargoPMS\Servicios\Pagos\DTOs\WebhookNotificacionResultado;
use CamargoPMS\Servicios\Pagos\ProveedorPagoInterfaz;
use InvalidArgumentException;
use RuntimeException;

/**
 * Adaptador / Driver para la pasarela de pagos Culqi (Perú).
 *
 * Implementa ProveedorPagoInterfaz cumpliendo con:
 * - Conversión segura de importes decimales (PEN) a céntimos enteros (BCMath).
 * - Verificación zero-trust de webhooks y consulta server-to-server.
 * - Cliente HTTP desacoplado e inyectable para pruebas sin red.
 */
class CulqiProveedor implements ProveedorPagoInterfaz
{
    private string $llaveSecreta;
    private string $llavePublica;
    private ?string $webhookSecret;
    private string $baseUrl;
    /** @var callable|null */
    private $clienteHttp;

    public function __construct(
        string $llaveSecreta,
        string $llavePublica = '',
        ?string $webhookSecret = null,
        ?callable $clienteHttp = null,
        string $baseUrl = 'https://api.culqi.com/v2'
    ) {
        $this->llaveSecreta = trim($llaveSecreta);
        $this->llavePublica = trim($llavePublica);
        $this->webhookSecret = $webhookSecret !== null ? trim($webhookSecret) : null;
        $this->clienteHttp = $clienteHttp;
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function obtenerNombre(): string
    {
        return 'CULQI';
    }

    public function crearIntencionPago(IntencionPagoSolicitud $solicitud): IntencionPagoResultado
    {
        // Culqi requiere importes en céntimos (enteros)
        $montoCentimos = (int) bcmul($solicitud->monto, '100', 0);
        if ($montoCentimos <= 0) {
            return IntencionPagoResultado::fallo('CULQI', 'El monto a cobrar debe ser mayor a 0.00');
        }

        // Expiración Unix timestamp (mínimo 15 minutos, estándar 30 min o hold de reserva)
        $timestampExpiracion = time() + (30 * 60);
        if (!empty($solicitud->expiraEn)) {
            $ts = strtotime($solicitud->expiraEn);
            if ($ts !== false && $ts > time()) {
                $timestampExpiracion = $ts;
            }
        }

        // Partir nombre y apellido para client_details
        $partesNombre = explode(' ', trim($solicitud->clienteNombre), 2);
        $nombre = $partesNombre[0] ?? 'Huésped';
        $apellido = $partesNombre[1] ?? 'Camargo';

        $payload = [
            'amount' => $montoCentimos,
            'currency_code' => strtoupper($solicitud->moneda),
            'description' => mb_substr($solicitud->descripcion, 0, 80),
            'order_number' => mb_substr($solicitud->reservaCodigo, 0, 60),
            'client_details' => [
                'first_name' => $nombre,
                'last_name' => $apellido,
                'email' => $solicitud->clienteEmail,
                'phone_number' => !empty($solicitud->clienteTelefono) ? $solicitud->clienteTelefono : '999999999',
            ],
            'expiration_date' => $timestampExpiracion,
            'metadata' => array_merge($solicitud->metadatos, [
                'reserva_id' => $solicitud->reservaId,
                'reserva_codigo' => $solicitud->reservaCodigo,
            ]),
        ];

        try {
            $respuesta = $this->realizarPeticion('POST', '/orders', $payload);

            if (!isset($respuesta['id'])) {
                $mensaje = $respuesta['user_message'] ?? $respuesta['merchant_message'] ?? 'Respuesta inválida de Culqi Orders';
                return IntencionPagoResultado::fallo('CULQI', $mensaje);
            }

            $ordenId = (string) $respuesta['id'];
            $expiraEnIso = date('Y-m-d H:i:s', (int) ($respuesta['expiration_date'] ?? $timestampExpiracion));

            return IntencionPagoResultado::exito(
                proveedor: 'CULQI',
                proveedorOrdenId: $ordenId,
                tokenTransaccion: $ordenId,
                monto: $solicitud->monto,
                moneda: strtoupper($solicitud->moneda),
                fechaExpiracion: $expiraEnIso,
                metadatos: [
                    'qr' => $respuesta['qr'] ?? null,
                    'order_number' => $respuesta['order_number'] ?? $solicitud->reservaCodigo,
                ]
            );
        } catch (\Throwable $e) {
            return IntencionPagoResultado::fallo('CULQI', 'Error al comunicar con Culqi: ' . $e->getMessage());
        }
    }

    public function verificarYParsearWebhook(array $headers, string $cuerpoBruto): WebhookNotificacionResultado
    {
        if (trim($cuerpoBruto) === '') {
            return WebhookNotificacionResultado::invalido('CULQI', 'Cuerpo de webhook vacío');
        }

        $datos = json_decode($cuerpoBruto, true);
        if (!is_array($datos)) {
            return WebhookNotificacionResultado::invalido('CULQI', 'Payload JSON inválido');
        }

        // Culqi Webhook Event Structure:
        // { "id": "evt_test_...", "type": "order.status.changed", "data": "{...}" or {...} }
        $eventoId = (string) ($datos['id'] ?? '');
        $tipoEvento = (string) ($datos['type'] ?? '');

        if ($eventoId === '' || $tipoEvento === '') {
            return WebhookNotificacionResultado::invalido('CULQI', 'Evento sin id o type obligatorio');
        }

        // El nodo 'data' en Culqi a veces viene serializado como string JSON o como array
        $data = $datos['data'] ?? [];
        if (is_string($data)) {
            $dataDecoded = json_decode($data, true);
            $data = is_array($dataDecoded) ? $dataDecoded : [];
        }

        // Normalización de estados Culqi:
        // Eventos de orden: order.status.changed
        // Eventos de cargo: charge.creation.succeeded, charge.creation.failed
        $proveedorOrdenId = null;
        $proveedorTransaccionId = null;
        $monto = null;
        $montoNeto = null;
        $comision = '0.00';
        $comisionImpuesto = '0.00';
        $moneda = 'PEN';
        $pagadoEn = null;
        $estadoNormalizado = WebhookNotificacionResultado::ESTADO_IGNORADO;
        $motivoError = null;

        if ($tipoEvento === 'order.status.changed') {
            $proveedorOrdenId = (string) ($data['id'] ?? '');
            $estadoOrden = strtolower((string) ($data['state'] ?? ''));

            if (isset($data['amount'])) {
                $monto = bcdiv((string) $data['amount'], '100', 2);
            }
            if (isset($data['currency_code'])) {
                $moneda = strtoupper((string) $data['currency_code']);
            }

            if ($estadoOrden === 'paid') {
                $estadoNormalizado = WebhookNotificacionResultado::ESTADO_APROBADO;
                $pagadoEn = date('Y-m-d H:i:s', (int) ($data['updated_at'] ?? time()));
            } elseif ($estadoOrden === 'expired') {
                $estadoNormalizado = WebhookNotificacionResultado::ESTADO_EXPIRADO;
            } elseif ($estadoOrden === 'pending') {
                $estadoNormalizado = WebhookNotificacionResultado::ESTADO_PENDIENTE;
            } else {
                $estadoNormalizado = WebhookNotificacionResultado::ESTADO_IGNORADO;
            }
        } elseif ($tipoEvento === 'charge.creation.succeeded') {
            $proveedorTransaccionId = (string) ($data['id'] ?? '');
            $proveedorOrdenId = isset($data['order_id']) && !empty($data['order_id']) ? (string) $data['order_id'] : null;
            $estadoNormalizado = WebhookNotificacionResultado::ESTADO_APROBADO;

            if (isset($data['amount'])) {
                $monto = bcdiv((string) $data['amount'], '100', 2);
            }
            if (isset($data['currency_code'])) {
                $moneda = strtoupper((string) $data['currency_code']);
            }

            // Comisiones si vienen informadas
            if (isset($data['fee_details']['amount'])) {
                $comision = bcdiv((string) $data['fee_details']['amount'], '100', 2);
            }
            if (isset($data['net_amount'])) {
                $montoNeto = bcdiv((string) $data['net_amount'], '100', 2);
            }

            $pagadoEn = date('Y-m-d H:i:s', (int) ($data['creation_date'] ?? time()));
        } elseif ($tipoEvento === 'charge.creation.failed') {
            $proveedorTransaccionId = (string) ($data['id'] ?? '');
            $proveedorOrdenId = isset($data['order_id']) && !empty($data['order_id']) ? (string) $data['order_id'] : null;
            $estadoNormalizado = WebhookNotificacionResultado::ESTADO_FALLIDO;
            $motivoError = (string) ($data['user_message'] ?? $data['outcome']['user_message'] ?? 'Cargo rechazado en Culqi');

            if (isset($data['amount'])) {
                $monto = bcdiv((string) $data['amount'], '100', 2);
            }
        }

        return new WebhookNotificacionResultado(
            valido: true,
            proveedor: 'CULQI',
            proveedorEventoId: $eventoId,
            tipoEvento: $tipoEvento,
            proveedorOrdenId: $proveedorOrdenId,
            proveedorTransaccionId: $proveedorTransaccionId,
            estadoNormalizado: $estadoNormalizado,
            monto: $monto,
            montoNeto: $montoNeto,
            comisionProveedor: $comision,
            comisionImpuesto: $comisionImpuesto,
            moneda: $moneda,
            pagadoEn: $pagadoEn,
            metadatos: is_array($data) ? $data : [],
            motivoError: $motivoError
        );
    }

    public function consultarOrden(string $proveedorOrdenId): ConsultaOrdenResultado
    {
        try {
            $resp = $this->realizarPeticion('GET', '/orders/' . urlencode($proveedorOrdenId));

            if (!isset($resp['id'])) {
                return ConsultaOrdenResultado::noEncontrada('CULQI', $proveedorOrdenId, $resp['user_message'] ?? 'Orden no encontrada');
            }

            $state = strtolower((string) ($resp['state'] ?? ''));
            $estadoNormalizado = match ($state) {
                'paid' => ConsultaOrdenResultado::ESTADO_PAGADA,
                'expired' => ConsultaOrdenResultado::ESTADO_EXPIRADA,
                'pending' => ConsultaOrdenResultado::ESTADO_PENDIENTE,
                default => ConsultaOrdenResultado::ESTADO_FALLIDA,
            };

            $monto = isset($resp['amount']) ? bcdiv((string) $resp['amount'], '100', 2) : '0.00';
            $moneda = strtoupper((string) ($resp['currency_code'] ?? 'PEN'));
            $pagadoEn = ($estadoNormalizado === ConsultaOrdenResultado::ESTADO_PAGADA && isset($resp['updated_at']))
                ? date('Y-m-d H:i:s', (int) $resp['updated_at'])
                : null;

            return new ConsultaOrdenResultado(
                encontrado: true,
                proveedor: 'CULQI',
                proveedorOrdenId: (string) $resp['id'],
                estadoOrden: $estadoNormalizado,
                monto: $monto,
                moneda: $moneda,
                transaccionId: null,
                pagadoEn: $pagadoEn,
                metadatos: $resp
            );
        } catch (\Throwable $e) {
            return ConsultaOrdenResultado::noEncontrada('CULQI', $proveedorOrdenId, $e->getMessage());
        }
    }

    public function consultarTransaccion(string $proveedorTransaccionId): ConsultaTransaccionResultado
    {
        try {
            $resp = $this->realizarPeticion('GET', '/charges/' . urlencode($proveedorTransaccionId));

            if (!isset($resp['id'])) {
                return ConsultaTransaccionResultado::noEncontrada('CULQI', $proveedorTransaccionId, $resp['user_message'] ?? 'Cargo no encontrado');
            }

            $capturado = (bool) ($resp['capture'] ?? false);
            $outcome = (string) ($resp['outcome']['type'] ?? '');
            $estadoNormalizado = ($capturado || $outcome === 'venta_exitosa')
                ? ConsultaTransaccionResultado::ESTADO_APROBADA
                : ConsultaTransaccionResultado::ESTADO_FALLIDA;

            $monto = isset($resp['amount']) ? bcdiv((string) $resp['amount'], '100', 2) : '0.00';
            $moneda = strtoupper((string) ($resp['currency_code'] ?? 'PEN'));
            $comision = isset($resp['fee_details']['amount']) ? bcdiv((string) $resp['fee_details']['amount'], '100', 2) : '0.00';
            $neto = isset($resp['net_amount']) ? bcdiv((string) $resp['net_amount'], '100', 2) : null;
            $pagadoEn = isset($resp['creation_date']) ? date('Y-m-d H:i:s', (int) $resp['creation_date']) : null;

            return new ConsultaTransaccionResultado(
                encontrado: true,
                proveedor: 'CULQI',
                proveedorTransaccionId: (string) $resp['id'],
                estadoTransaccion: $estadoNormalizado,
                monto: $monto,
                montoNeto: $neto,
                comision: $comision,
                moneda: $moneda,
                pagadoEn: $pagadoEn,
                metadatos: $resp
            );
        } catch (\Throwable $e) {
            return ConsultaTransaccionResultado::noEncontrada('CULQI', $proveedorTransaccionId, $e->getMessage());
        }
    }

    public function procesarReembolso(ReembolsoSolicitud $solicitud): ReembolsoResultado
    {
        $montoCentimos = (int) bcmul($solicitud->monto, '100', 0);
        if ($montoCentimos <= 0) {
            return ReembolsoResultado::fallo('CULQI', 'Monto de reembolso inválido');
        }

        $payload = [
            'charge_id' => $solicitud->proveedorTransaccionId,
            'amount' => $montoCentimos,
            'reason' => $solicitud->motivo,
        ];

        try {
            $resp = $this->realizarPeticion('POST', '/refunds', $payload);

            if (!isset($resp['id'])) {
                return ReembolsoResultado::fallo('CULQI', $resp['user_message'] ?? 'Error al procesar reembolso en Culqi');
            }

            return ReembolsoResultado::exito(
                proveedor: 'CULQI',
                reembolsoId: (string) $resp['id'],
                monto: $solicitud->monto,
                moneda: $solicitud->moneda,
                metadatos: $resp
            );
        } catch (\Throwable $e) {
            return ReembolsoResultado::fallo('CULQI', $e->getMessage());
        }
    }

    /**
     * Realiza una petición HTTP contra Culqi o delega en el cliente inyectado (mock).
     */
    private function realizarPeticion(string $metodo, string $ruta, ?array $cuerpo = null): array
    {
        if ($this->clienteHttp !== null) {
            return ($this->clienteHttp)($metodo, $this->baseUrl . $ruta, $cuerpo, [
                'Authorization' => 'Bearer ' . $this->llaveSecreta,
                'Content-Type' => 'application/json',
            ]);
        }

        $url = $this->baseUrl . $ruta;
        $headers = [
            'Authorization: Bearer ' . $this->llaveSecreta,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $opciones = [
            'http' => [
                'method' => $metodo,
                'header' => implode("\r\n", $headers),
                'timeout' => 15,
                'ignore_errors' => true,
            ],
        ];

        if ($cuerpo !== null && in_array($metodo, ['POST', 'PUT', 'PATCH'], true)) {
            $opciones['http']['content'] = json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
        }

        $contexto = stream_context_create($opciones);
        $respuesta = @file_get_contents($url, false, $contexto);

        if ($respuesta === false) {
            throw new RuntimeException("No se pudo conectar con el endpoint de Culqi: $url");
        }

        $decodificado = json_decode($respuesta, true);
        if (!is_array($decodificado)) {
            throw new RuntimeException("Respuesta de Culqi no es JSON válido: $respuesta");
        }

        return $decodificado;
    }
}
