<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos\CPE;

/**
 * Entidad interna del agregado que representa un ítem o línea fiscal de bien o servicio documentado.
 */
class CpeLinea
{
    /**
     * @param array<int, array{cargo_cuenta_id: int, monto_atribuido: string, cantidad_atribuida: string}> $cargosAtribuidos
     */
    public function __construct(
        private ?int $id,
        private ?int $cpeId,
        private int $numeroOrden,
        private ?string $codigoProductoInterno,
        private ?string $codigoProductoSunat,
        private string $descripcion,
        private string $unidadMedida = 'ZZ',
        private string $cantidad = '1.0000',
        private string $valorUnitario = '0.0000',
        private string $precioUnitario = '0.0000',
        private string $descuentoMonto = '0.00',
        private string $baseImponible = '0.00',
        private string $tipoAfectacionIgv = '10',
        private string $tasaIgv = '18.00',
        private string $montoIgv = '0.00',
        private string $totalLinea = '0.00',
        private ?int $cpeHospedajeId = null,
        private ?string $fechaConsumo = null,
        private ?string $creadoEn = null,
        private array $cargosAtribuidos = []
    ) {
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function fijarId(int $id): void
    {
        $this->id = $id;
    }

    public function obtenerCpeId(): ?int
    {
        return $this->cpeId;
    }

    public function fijarCpeId(int $cpeId): void
    {
        $this->cpeId = $cpeId;
    }

    public function obtenerNumeroOrden(): int
    {
        return $this->numeroOrden;
    }

    public function obtenerCodigoProductoInterno(): ?string
    {
        return $this->codigoProductoInterno;
    }

    public function obtenerCodigoProductoSunat(): ?string
    {
        return $this->codigoProductoSunat;
    }

    public function obtenerDescripcion(): string
    {
        return $this->descripcion;
    }

    public function obtenerUnidadMedida(): string
    {
        return $this->unidadMedida;
    }

    public function obtenerCantidad(): string
    {
        return $this->cantidad;
    }

    public function obtenerValorUnitario(): string
    {
        return $this->valorUnitario;
    }

    public function obtenerPrecioUnitario(): string
    {
        return $this->precioUnitario;
    }

    public function obtenerDescuentoMonto(): string
    {
        return $this->descuentoMonto;
    }

    public function obtenerBaseImponible(): string
    {
        return $this->baseImponible;
    }

    public function obtenerTipoAfectacionIgv(): string
    {
        return $this->tipoAfectacionIgv;
    }

    public function obtenerTasaIgv(): string
    {
        return $this->tasaIgv;
    }

    public function obtenerMontoIgv(): string
    {
        return $this->montoIgv;
    }

    public function obtenerTotalLinea(): string
    {
        return $this->totalLinea;
    }

    public function obtenerCpeHospedajeId(): ?int
    {
        return $this->cpeHospedajeId;
    }

    public function fijarCpeHospedajeId(?int $cpeHospedajeId): void
    {
        $this->cpeHospedajeId = $cpeHospedajeId;
    }

    public function establecerCpeHospedajeId(?int $cpeHospedajeId): void
    {
        $this->fijarCpeHospedajeId($cpeHospedajeId);
    }

    public function obtenerFechaConsumo(): ?string
    {
        return $this->fechaConsumo;
    }

    public function fijarFechaConsumo(?string $fechaConsumo): void
    {
        $this->fechaConsumo = $fechaConsumo;
    }

    public function establecerFechaConsumo(?string $fechaConsumo): void
    {
        $this->fijarFechaConsumo($fechaConsumo);
    }

    public function esBeneficioHospedaje(): bool
    {
        return $this->tipoAfectacionIgv === '40';
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    /**
     * @return array<int, array{cargo_cuenta_id: int, monto_atribuido: string, cantidad_atribuida: string}>
     */
    public function obtenerCargosAtribuidos(): array
    {
        return $this->cargosAtribuidos;
    }

    /**
     * @param array<int, array{cargo_cuenta_id: int, monto_atribuido: string, cantidad_atribuida: string}> $cargosAtribuidos
     */
    public function fijarCargosAtribuidos(array $cargosAtribuidos): void
    {
        $this->cargosAtribuidos = $cargosAtribuidos;
    }

    /**
     * @return array<string, mixed>
     */
    public function haciaArreglo(): array
    {
        return [
            'id' => $this->id,
            'cpe_id' => $this->cpeId,
            'cpe_hospedaje_id' => $this->cpeHospedajeId,
            'numero_orden' => $this->numeroOrden,
            'codigo_producto_interno' => $this->codigoProductoInterno,
            'codigo_producto_sunat' => $this->codigoProductoSunat,
            'descripcion' => $this->descripcion,
            'unidad_medida' => $this->unidadMedida,
            'cantidad' => $this->cantidad,
            'valor_unitario' => $this->valorUnitario,
            'precio_unitario' => $this->precioUnitario,
            'descuento_monto' => $this->descuentoMonto,
            'base_imponible' => $this->baseImponible,
            'tipo_afectacion_igv' => $this->tipoAfectacionIgv,
            'tasa_igv' => $this->tasaIgv,
            'monto_igv' => $this->montoIgv,
            'total_linea' => $this->totalLinea,
            'fecha_consumo' => $this->fechaConsumo,
            'creado_en' => $this->creadoEn,
            'cargos_atribuidos' => $this->cargosAtribuidos,
        ];
    }

    /**
     * @param array<string, mixed> $datos
     */
    public static function desdeArreglo(array $datos): self
    {
        return new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            isset($datos['cpe_id']) && $datos['cpe_id'] !== null ? (int) $datos['cpe_id'] : null,
            (int) ($datos['numero_orden'] ?? 1),
            isset($datos['codigo_producto_interno']) && $datos['codigo_producto_interno'] !== null ? (string) $datos['codigo_producto_interno'] : null,
            isset($datos['codigo_producto_sunat']) && $datos['codigo_producto_sunat'] !== null ? (string) $datos['codigo_producto_sunat'] : null,
            (string) ($datos['descripcion'] ?? ''),
            (string) ($datos['unidad_medida'] ?? 'ZZ'),
            (string) ($datos['cantidad'] ?? '1.0000'),
            (string) ($datos['valor_unitario'] ?? '0.0000'),
            (string) ($datos['precio_unitario'] ?? '0.0000'),
            (string) ($datos['descuento_monto'] ?? '0.00'),
            (string) ($datos['base_imponible'] ?? '0.00'),
            (string) ($datos['tipo_afectacion_igv'] ?? '10'),
            (string) ($datos['tasa_igv'] ?? '18.00'),
            (string) ($datos['monto_igv'] ?? '0.00'),
            (string) ($datos['total_linea'] ?? '0.00'),
            isset($datos['cpe_hospedaje_id']) && $datos['cpe_hospedaje_id'] !== null ? (int) $datos['cpe_hospedaje_id'] : null,
            isset($datos['fecha_consumo']) && $datos['fecha_consumo'] !== null ? (string) $datos['fecha_consumo'] : null,
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            (array) ($datos['cargos_atribuidos'] ?? [])
        );
    }
}
