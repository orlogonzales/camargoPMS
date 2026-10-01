<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Modelo de Dominio que representa un Evento Webhook de Pasarela de Pago Externa.
 *
 * Mantiene la trazabilidad y la idempotencia multidimensional (D-107):
 * Garantiza que cada notificación del proveedor sea procesada exactamente una vez
 * o registrada como DUPLICADO_OMITIDO sin duplicar transacciones ni abonos contables.
 */
class PagoWebhookEvento
{
    public const ESTADO_PROCESADO = 'PROCESADO';
    public const ESTADO_DUPLICADO_OMITIDO = 'DUPLICADO_OMITIDO';
    public const ESTADO_ERROR_VERIFICACION = 'ERROR_VERIFICACION';
    public const ESTADO_ERROR_PROCESAMIENTO = 'ERROR_PROCESAMIENTO';

    public function __construct(
        private ?int $id,
        private ?int $transaccionPasarelaId,
        private string $proveedor,
        private string $proveedorEventoId,
        private string $tipoEvento,
        private string $cuerpoHash,
        private string $payloadRaw,
        private ?array $cabeceras,
        private string $estadoProcesamiento,
        private int $codigoHttpRespuesta,
        private ?string $errorDetalle,
        private ?string $recibidoEn = null,
        private ?string $procesadoEn = null
    ) {
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerTransaccionPasarelaId(): ?int
    {
        return $this->transaccionPasarelaId;
    }

    public function obtenerProveedor(): string
    {
        return $this->proveedor;
    }

    public function obtenerProveedorEventoId(): string
    {
        return $this->proveedorEventoId;
    }

    public function obtenerTipoEvento(): string
    {
        return $this->tipoEvento;
    }

    public function obtenerCuerpoHash(): string
    {
        return $this->cuerpoHash;
    }

    public function obtenerPayloadRaw(): string
    {
        return $this->payloadRaw;
    }

    public function obtenerPayloadDecodificado(): array
    {
        $dec = json_decode($this->payloadRaw, true);
        return is_array($dec) ? $dec : [];
    }

    public function obtenerCabeceras(): ?array
    {
        return $this->cabeceras;
    }

    public function obtenerEstadoProcesamiento(): string
    {
        return $this->estadoProcesamiento;
    }

    public function obtenerCodigoHttpRespuesta(): int
    {
        return $this->codigoHttpRespuesta;
    }

    public function obtenerErrorDetalle(): ?string
    {
        return $this->errorDetalle;
    }

    public function obtenerRecibidoEn(): ?string
    {
        return $this->recibidoEn;
    }

    public function obtenerProcesadoEn(): ?string
    {
        return $this->procesadoEn;
    }

    public function estaProcesado(): bool
    {
        return $this->estadoProcesamiento === self::ESTADO_PROCESADO;
    }

    public function aArray(): array
    {
        return [
            'id' => $this->id,
            'transaccion_pasarela_id' => $this->transaccionPasarelaId,
            'proveedor' => $this->proveedor,
            'proveedor_evento_id' => $this->proveedorEventoId,
            'tipo_evento' => $this->tipoEvento,
            'cuerpo_hash' => $this->cuerpoHash,
            'payload_raw' => $this->payloadRaw,
            'cabeceras' => $this->cabeceras,
            'estado_procesamiento' => $this->estadoProcesamiento,
            'codigo_http_respuesta' => $this->codigoHttpRespuesta,
            'error_detalle' => $this->errorDetalle,
            'recibido_en' => $this->recibidoEn,
            'procesado_en' => $this->procesadoEn,
        ];
    }

    public static function desdeArray(array $datos): self
    {
        $cabeceras = $datos['cabeceras'] ?? null;
        if (is_string($cabeceras)) {
            $decodificadoH = json_decode($cabeceras, true);
            $cabeceras = is_array($decodificadoH) ? $decodificadoH : null;
        }

        return new self(
            id: isset($datos['id']) ? (int) $datos['id'] : null,
            transaccionPasarelaId: isset($datos['transaccion_pasarela_id']) && $datos['transaccion_pasarela_id'] !== null ? (int) $datos['transaccion_pasarela_id'] : null,
            proveedor: (string) ($datos['proveedor'] ?? ''),
            proveedorEventoId: (string) ($datos['proveedor_evento_id'] ?? ''),
            tipoEvento: (string) ($datos['tipo_evento'] ?? ''),
            cuerpoHash: (string) ($datos['cuerpo_hash'] ?? ''),
            payloadRaw: (string) ($datos['payload_raw'] ?? ''),
            cabeceras: $cabeceras,
            estadoProcesamiento: (string) ($datos['estado_procesamiento'] ?? self::ESTADO_PROCESADO),
            codigoHttpRespuesta: (int) ($datos['codigo_http_respuesta'] ?? 200),
            errorDetalle: isset($datos['error_detalle']) ? (string) $datos['error_detalle'] : null,
            recibidoEn: isset($datos['recibido_en']) ? (string) $datos['recibido_en'] : null,
            procesadoEn: isset($datos['procesado_en']) ? (string) $datos['procesado_en'] : null
        );
    }
}
