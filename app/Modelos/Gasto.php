<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio que representa el Hecho Económico Soberano de Gasto.
 * GASTOS-1 / D-086.
 *
 * Principio: GASTO = Obligación / Consumo reconocido.
 * Cero campos de saldo o amortización mutable soberana.
 */
class Gasto
{
    public const ESTADO_BORRADOR = 'BORRADOR';
    public const ESTADO_REGISTRADO = 'REGISTRADO';
    public const ESTADO_APROBADO = 'APROBADO';
    public const ESTADO_ANULADO = 'ANULADO';

    public const AMBITO_CORPORATIVO = 'CORPORATIVO';
    public const AMBITO_PROPIEDAD = 'PROPIEDAD';
    public const AMBITO_UNIDAD = 'UNIDAD';

    public const COMPROBANTE_FACTURA = 'FACTURA';
    public const COMPROBANTE_BOLETA = 'BOLETA';
    public const COMPROBANTE_RECIBO_HONORARIOS = 'RECIBO_HONORARIOS';
    public const COMPROBANTE_RECIBO_SERVICIO_PUBLICO = 'RECIBO_SERVICIO_PUBLICO';
    public const COMPROBANTE_DECLARACION_JURADA = 'DECLARACION_JURADA_CAJA_CHICA';
    public const COMPROBANTE_TICKET = 'TICKET_MAQUINA';
    public const COMPROBANTE_OTRO = 'OTRO_NO_TRIBUTARIO';

    public function __construct(
        private ?int $id,
        private string $codigo,
        private int $categoriaId,
        private string $ambito = self::AMBITO_PROPIEDAD,
        private ?int $propiedadId = null,
        private ?int $unidadId = null,
        private ?int $proveedorId = null,
        private string $acreedorNombre = '',
        private ?string $acreedorDocumento = null,
        private string $descripcionConcepto = '',
        private string $tipoComprobante = self::COMPROBANTE_FACTURA,
        private ?string $comprobanteSerie = null,
        private ?string $comprobanteNumero = null,
        private string $fechaEmision = '',
        private string $fechaVencimiento = '',
        private string $monedaCodigo = 'PEN',
        private string $subtotal = '0.00',
        private string $impuestos = '0.00',
        private string $total = '0.00',
        private string $estado = self::ESTADO_REGISTRADO,
        private ?string $motivoAnulacion = null,
        private ?string $anuladoEn = null,
        private ?int $anuladoPorActorId = null,
        private int $creadoPorActorId = 1,
        private ?string $creadoEn = null,
        private ?string $actualizadoEn = null
    ) {
    }

    public function obtenerId(): ?int { return $this->id; }
    public function obtenerCodigo(): string { return $this->codigo; }
    public function obtenerCategoriaId(): int { return $this->categoriaId; }
    public function obtenerAmbito(): string { return $this->ambito; }
    public function obtenerPropiedadId(): ?int { return $this->propiedadId; }
    public function obtenerUnidadId(): ?int { return $this->unidadId; }
    public function obtenerProveedorId(): ?int { return $this->proveedorId; }
    public function obtenerAcreedorNombre(): string { return $this->acreedorNombre; }
    public function obtenerAcreedorDocumento(): ?string { return $this->acreedorDocumento; }
    public function obtenerDescripcionConcepto(): string { return $this->descripcionConcepto; }
    public function obtenerTipoComprobante(): string { return $this->tipoComprobante; }
    public function obtenerComprobanteSerie(): ?string { return $this->comprobanteSerie; }
    public function obtenerComprobanteNumero(): ?string { return $this->comprobanteNumero; }
    public function obtenerFechaEmision(): string { return $this->fechaEmision; }
    public function obtenerFechaVencimiento(): string { return $this->fechaVencimiento; }
    public function obtenerMonedaCodigo(): string { return $this->monedaCodigo; }
    public function obtenerSubtotal(): string { return $this->subtotal; }
    public function obtenerImpuestos(): string { return $this->impuestos; }
    public function obtenerTotal(): string { return $this->total; }
    public function obtenerEstado(): string { return $this->estado; }
    public function obtenerMotivoAnulacion(): ?string { return $this->motivoAnulacion; }
    public function obtenerAnuladoEn(): ?string { return $this->anuladoEn; }
    public function obtenerAnuladoPorActorId(): ?int { return $this->anuladoPorActorId; }
    public function obtenerCreadoPorActorId(): int { return $this->creadoPorActorId; }
    public function obtenerCreadoEn(): ?string { return $this->creadoEn; }
    public function obtenerActualizadoEn(): ?string { return $this->actualizadoEn; }

    public function estaAprobado(): bool
    {
        return $this->estado === self::ESTADO_APROBADO;
    }

    public function estaAnulado(): bool
    {
        return $this->estado === self::ESTADO_ANULADO;
    }

    public function puedeAprobar(): bool
    {
        return in_array($this->estado, [self::ESTADO_BORRADOR, self::ESTADO_REGISTRADO], true);
    }

    public function puedeAnular(): bool
    {
        return $this->estado !== self::ESTADO_ANULADO;
    }

    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'categoria_id' => $this->categoriaId,
            'ambito' => $this->ambito,
            'propiedad_id' => $this->propiedadId,
            'unidad_id' => $this->unidadId,
            'proveedor_id' => $this->proveedorId,
            'acreedor_nombre' => $this->acreedorNombre,
            'acreedor_documento' => $this->acreedorDocumento,
            'descripcion_concepto' => $this->descripcionConcepto,
            'tipo_comprobante' => $this->tipoComprobante,
            'comprobante_serie' => $this->comprobanteSerie,
            'comprobante_numero' => $this->comprobanteNumero,
            'fecha_emision' => $this->fechaEmision,
            'fecha_vencimiento' => $this->fechaVencimiento,
            'moneda_codigo' => $this->monedaCodigo,
            'subtotal' => $this->subtotal,
            'impuestos' => $this->impuestos,
            'total' => $this->total,
            'estado' => $this->estado,
            'motivo_anulacion' => $this->motivoAnulacion,
            'anulado_en' => $this->anuladoEn,
            'anulado_por_actor_id' => $this->anuladoPorActorId,
            'creado_por_actor_id' => $this->creadoPorActorId,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }

    public static function desdeArreglo(array $datos): self
    {
        return new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (string) ($datos['codigo'] ?? ''),
            (int) ($datos['categoria_id'] ?? 0),
            (string) ($datos['ambito'] ?? self::AMBITO_PROPIEDAD),
            isset($datos['propiedad_id']) && $datos['propiedad_id'] !== '' ? (int) $datos['propiedad_id'] : null,
            isset($datos['unidad_id']) && $datos['unidad_id'] !== '' ? (int) $datos['unidad_id'] : null,
            isset($datos['proveedor_id']) && $datos['proveedor_id'] !== '' ? (int) $datos['proveedor_id'] : null,
            (string) ($datos['acreedor_nombre'] ?? ''),
            isset($datos['acreedor_documento']) && $datos['acreedor_documento'] !== '' ? (string) $datos['acreedor_documento'] : null,
            (string) ($datos['descripcion_concepto'] ?? ''),
            (string) ($datos['tipo_comprobante'] ?? self::COMPROBANTE_FACTURA),
            isset($datos['comprobante_serie']) && $datos['comprobante_serie'] !== '' ? (string) $datos['comprobante_serie'] : null,
            isset($datos['comprobante_numero']) && $datos['comprobante_numero'] !== '' ? (string) $datos['comprobante_numero'] : null,
            (string) ($datos['fecha_emision'] ?? date('Y-m-d')),
            (string) ($datos['fecha_vencimiento'] ?? date('Y-m-d')),
            (string) ($datos['moneda_codigo'] ?? 'PEN'),
            (string) ($datos['subtotal'] ?? '0.00'),
            (string) ($datos['impuestos'] ?? '0.00'),
            (string) ($datos['total'] ?? '0.00'),
            (string) ($datos['estado'] ?? self::ESTADO_REGISTRADO),
            isset($datos['motivo_anulacion']) ? (string) $datos['motivo_anulacion'] : null,
            isset($datos['anulado_en']) ? (string) $datos['anulado_en'] : null,
            isset($datos['anulado_por_actor_id']) ? (int) $datos['anulado_por_actor_id'] : null,
            isset($datos['creado_por_actor_id']) ? (int) $datos['creado_por_actor_id'] : 1,
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            isset($datos['actualizado_en']) ? (string) $datos['actualizado_en'] : null
        );
    }
}
