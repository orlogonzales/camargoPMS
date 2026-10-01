<?php

declare(strict_types=1);

namespace CamargoPMS\Controladores;

use CamargoPMS\Modelos\Reserva;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\ContextoHttpApi;
use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Nucleo\RespuestaApi;
use CamargoPMS\Repositorios\ReservaRepositorio;
use CamargoPMS\Servicios\Pagos\Adaptadores\CulqiProveedor;
use CamargoPMS\Servicios\Pagos\FabricaProveedoresPago;
use CamargoPMS\Servicios\PagoServicio;
use PDO;
use Throwable;

/**
 * Controlador API RESTful para el dominio soberano de pagos y pasarelas (PAGOS-1C).
 *
 * Responsabilidades:
 * - POST /api/v1/pagos/intenciones: Creación de intención de cobro sobre reservas en hold.
 * - POST /api/v1/webhooks/pagos/culqi: Ingesta, validación y conciliación de webhooks externos.
 * - OPTIONS /api/v1/pagos/intenciones y /webhooks/pagos/culqi: Preflights CORS.
 *
 * Invariantes arquitectónicos vinculantes:
 * - TRADUCTOR HTTP: No duplica lógica de negocio de PagoServicio ni CuentaFolioServicio.
 * - SOBERANÍA MONETARIA: El cliente JAMÁS decide el monto a cobrar; el monto proviene de la reserva.
 * - TRUST BOUNDARY DIFERENCIADO: El webhook es server-to-server; no utiliza Bearer ni Idempotency-Key de clientes.
 * - SEGURIDAD ZERO-TRUST: Cero exposición de llaves privadas, webhook secrets ni datos de tarjetas.
 */
class ApiPagoControlador
{
    private PDO $pdo;
    private PagoServicio $pagoServicio;
    private ReservaRepositorio $reservaRepo;

    /** @var string|null Soporte para inyección de payload crudo en tests unitarios y CLI */
    public static ?string $cuerpoPrueba = null;

    /** @var array<string, string>|null Soporte para inyección de cabeceras en tests */
    public static ?array $cabecerasPrueba = null;

    public function __construct(
        ?PDO $pdo = null,
        ?PagoServicio $pagoServicio = null,
        ?ReservaRepositorio $reservaRepo = null
    ) {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->pagoServicio = $pagoServicio ?? new PagoServicio($this->pdo);
        $this->reservaRepo = $reservaRepo ?? new ReservaRepositorio($this->pdo);
    }

    /**
     * Responde a preflights OPTIONS con HTTP 204 No Content.
     */
    public function preflight(): Respuesta
    {
        return RespuestaApi::sinContenido();
    }

    /**
     * Creación de intención de pago sobre una reserva comercial en hold (PAGOS-1C).
     * POST /api/v1/pagos/intenciones
     *
     * Pipeline obligatorio:
     * - ApiCorrelacionIntermediario
     * - ApiCorsIntermediario
     * - ApiAutenticacionIntermediario (Bearer cpms_live_...)
     * - ApiRateLimitIntermediario
     * - ApiScopeIntermediario (reservas.hold)
     * - ApiIdempotenciaIntermediario (Idempotency-Key)
     */
    public function crearIntencion(): Respuesta
    {
        $cuerpo = self::$cuerpoPrueba ?? (string) file_get_contents('php://input');
        $datos = json_decode($cuerpo !== '' ? $cuerpo : '{}', true);

        if (!is_array($datos)) {
            return RespuestaApi::error('El cuerpo de la petición debe ser un objeto JSON válido.', 'JSON_INVALIDO', 400);
        }

        $reservaCodigo = trim((string) ($datos['reserva_codigo'] ?? ''));
        if ($reservaCodigo === '') {
            return RespuestaApi::error(
                "El campo 'reserva_codigo' es obligatorio.",
                'PARAMETROS_REQUERIDOS',
                422,
                ['campo' => 'reserva_codigo']
            );
        }

        $proveedor = strtoupper(trim((string) ($datos['proveedor'] ?? 'CULQI')));
        if (!FabricaProveedoresPago::proveedorSoportado($proveedor)) {
            return RespuestaApi::error(
                "El proveedor de pago '{$proveedor}' no se encuentra soportado.",
                'PROVEEDOR_NO_SOPORTADO',
                422,
                ['proveedor' => $proveedor]
            );
        }

        // 1. Localizar la reserva por su código alfanumérico soberano
        $reserva = $this->reservaRepo->buscarPorCodigo($reservaCodigo);
        if ($reserva === null) {
            return RespuestaApi::error(
                "No se encontró ninguna reserva asociada al código '{$reservaCodigo}'.",
                'RESERVA_NO_ENCONTRADA',
                404
            );
        }

        // 2. Validaciones soberanas de estado y vigencia de hold
        if ($reserva->esConfirmada()) {
            return RespuestaApi::error(
                "La reserva '{$reservaCodigo}' ya se encuentra confirmada y no admite nuevos pagos.",
                'RESERVA_YA_CONFIRMADA',
                422
            );
        }

        if ($reserva->esCancelada()) {
            return RespuestaApi::error(
                "La reserva '{$reservaCodigo}' se encuentra cancelada y no admite pagos.",
                'RESERVA_NO_PAGABLE',
                422
            );
        }

        if ($reserva->haExpirado() || $reserva->esExpirada()) {
            return RespuestaApi::error(
                "El tiempo de retención temporal (hold) de la reserva '{$reservaCodigo}' ha expirado.",
                'HOLD_EXPIRADO',
                422
            );
        }

        if (!$reserva->esPendiente()) {
            return RespuestaApi::error(
                "El estado actual de la reserva ('{$reserva->obtenerEstado()}') no admite intenciones de pago.",
                'RESERVA_ESTADO_INVALIDO',
                422
            );
        }

        // 3. Validación de moneda soberana (D-069)
        if ($reserva->obtenerMonedaCodigo() !== 'PEN') {
            return RespuestaApi::error(
                "La reserva se encuentra cotizada en moneda '{$reserva->obtenerMonedaCodigo()}', actualmente solo se admite 'PEN'.",
                'MONEDA_NO_SOPORTADA',
                422
            );
        }

        // 4. Identificar actor técnico autenticado desde el contexto HTTP (D-061)
        $contexto = ContextoHttpApi::obtenerAutenticacion();
        $actorTecnico = $contexto?->obtenerActor();
        $actorId = $actorTecnico?->obtenerId();

        // 5. Delegar orquestación soberana en PagoServicio
        // INVARIANTE: El cliente JAMÁS fija el monto; el monto proviene de $reserva->obtenerTotal().
        try {
            $resultado = $this->pagoServicio->crearIntencionPagoParaReserva(
                reservaId: (int) $reserva->obtenerId(),
                proveedor: $proveedor,
                actorId: $actorId
            );
        } catch (Throwable $e) {
            return RespuestaApi::error(
                'Error al generar la intención de pago ante la pasarela: ' . $e->getMessage(),
                'ERROR_GENERACION_PAGO',
                500
            );
        }

        if (!$resultado['exito']) {
            return RespuestaApi::error(
                $resultado['mensaje'] ?? 'No se pudo crear la intención de pago.',
                $resultado['codigo'] ?? 'ERROR_INTENCION_PAGO',
                422
            );
        }

        // 6. Obtener llave pública segura para frontend checkout (cero llaves privadas)
        $driver = FabricaProveedoresPago::obtenerProveedor($proveedor);
        $llavePublica = ($driver instanceof CulqiProveedor) ? $driver->obtenerLlavePublica() : null;

        $datosRespuesta = [
            'transaccion_codigo' => $resultado['codigo_transaccion'] ?? ('TX-PAG-' . str_pad((string) $resultado['transaccion_id'], 6, '0', STR_PAD_LEFT)),
            'proveedor' => $proveedor,
            'proveedor_orden_id' => $resultado['proveedor_orden_id'],
            'token_transaccion' => $resultado['token_transaccion'] ?? null,
            'monto' => $resultado['monto'],
            'moneda' => $resultado['moneda'],
            'reserva_codigo' => $reserva->obtenerCodigo(),
            'fecha_expiracion' => $resultado['fecha_expiracion'] ?? $reserva->obtenerExpiraEn(),
            'llave_publica' => $llavePublica,
            'metadatos' => $resultado['metadatos'] ?? [],
        ];

        return RespuestaApi::exito($datosRespuesta, 201, [], 'INTENCION_PAGO_CREADA');
    }

    /**
     * Ingesta y conciliación de webhooks asíncronos de la pasarela Culqi (PAGOS-1C).
     * POST /api/v1/webhooks/pagos/culqi
     *
     * Trust boundary diferenciado:
     * - NO requiere Bearer token de cliente API.
     * - NO requiere Idempotency-Key en cabeceras.
     * - Utiliza ApiCorrelacionIntermediario para trazabilidad transversal.
     * - Delegación zero-trust en PagoServicio::procesarWebhook() con bloqueos ACID.
     */
    public function webhookCulqi(): Respuesta
    {
        $cuerpo = self::$cuerpoPrueba ?? (string) file_get_contents('php://input');

        if (trim($cuerpo) === '') {
            return RespuestaApi::error(
                'El cuerpo de la notificación webhook no puede estar vacío.',
                'PAYLOAD_VACIO',
                400
            );
        }

        $jsonTest = json_decode($cuerpo, true);
        if (!is_array($jsonTest)) {
            return RespuestaApi::error(
                'El payload del webhook no es un JSON válido.',
                'PAYLOAD_INVALIDO',
                400
            );
        }

        // Recolectar cabeceras entrantes de forma normalizada (minúsculas)
        $cabeceras = self::$cabecerasPrueba ?? (function_exists('getallheaders') ? getallheaders() : []);
        $cabecerasNormalizadas = [];
        foreach ($cabeceras as $k => $v) {
            $cabecerasNormalizadas[strtolower((string) $k)] = (string) $v;
        }

        if (empty($cabecerasNormalizadas)) {
            foreach ($_SERVER as $k => $v) {
                if (str_starts_with($k, 'HTTP_')) {
                    $nombre = strtolower(str_replace('_', '-', substr($k, 5)));
                    $cabecerasNormalizadas[$nombre] = (string) $v;
                }
            }
        }

        try {
            $resultado = $this->pagoServicio->procesarWebhook('CULQI', $cabecerasNormalizadas, $cuerpo);
        } catch (Throwable $e) {
            return RespuestaApi::error(
                'Error interno al procesar el evento webhook: ' . $e->getMessage(),
                'ERROR_INTERNO_WEBHOOK',
                500
            );
        }

        if (!$resultado['exito']) {
            $codigoHttp = match ($resultado['codigo'] ?? '') {
                'FIRMA_INVALIDA', 'AUTENTICIDAD_FALLIDA' => 401,
                'PAYLOAD_INVALIDO', 'EVENTO_DESCONOCIDO' => 400,
                default => 400,
            };

            return RespuestaApi::error(
                $resultado['mensaje'] ?? 'Error en webhook',
                $resultado['codigo'] ?? 'ERROR_WEBHOOK',
                $codigoHttp
            );
        }

        return RespuestaApi::exito(
            $resultado,
            200,
            [],
            $resultado['codigo'] ?? 'WEBHOOK_PROCESADO'
        );
    }
}
