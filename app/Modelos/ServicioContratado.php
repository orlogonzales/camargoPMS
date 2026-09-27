<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Modelo de dominio para un servicio contratado o consumo imputado a una reserva (y opcionalmente estadía).
 *
 * Principios vinculantes:
 * - Snapshots inmutables: precio, costo, modalidad, categoría y descripción congelados al contratar (D-010/D-069).
 * - reservaId obligatorio: la reserva comercial es la fuente de verdad del folio de cobro.
 * - Operación interna: esOperacionInterna = true y proveedorId = NULL.
 * - Ciclo de vida: SOLICITADO -> CONFIRMADO -> EJECUTADO / CANCELADO. Prohibida la cancelación de EJECUTADO.
 */
class ServicioContratado
{
    private ?int $id;
    private string $codigo;
    private int $reservaId;
    private ?int $estadiaId;
    private int $servicioId;
    private ?int $proveedorId;

    // Snapshots históricos inmutables
    private string $descripcionServicioSnapshot;
    private string $categoriaCodigoSnapshot;
    private string $modalidadCobroCodigoSnapshot;
    private bool $esOperacionInterna;
    private string $cantidad;
    private string $precioUnitario;
    private string $costoUnitario;
    private string $subtotal;
    private string $tasaImpuesto;
    private string $impuestoTotal;
    private string $total;
    private string $costoTotal;
    private string $monedaCodigo;

    // Ciclo de vida
    private string $estado; // 'SOLICITADO' | 'CONFIRMADO' | 'EJECUTADO' | 'CANCELADO'
    private string $fechaServicio;
    private ?string $horaServicio;
    private ?string $ejecutadoEn;
    private ?string $canceladaEn;
    private ?string $motivoCancelacion;
    private ?string $observaciones;

    // Trazabilidad D-061
    private int $solicitadoPorActorId;
    private ?int $ejecutadoPorActorId;
    private ?int $canceladaPorActorId;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    // Metadatos auxiliares de consulta
    private ?string $reservaCodigo = null;
    private ?string $titularNombreCompleto = null;
    private ?string $estadiaCodigo = null;
    private ?string $unidadNombre = null;
    private ?string $servicioNombre = null;
    private ?string $proveedorRazonSocial = null;
    private ?string $proveedorCodigo = null;
    private ?ServicioTraslado $traslado = null;

    public function __construct(
        ?int $id,
        string $codigo,
        int $reservaId,
        ?int $estadiaId,
        int $servicioId,
        ?int $proveedorId,
        string $descripcionServicioSnapshot,
        string $categoriaCodigoSnapshot,
        string $modalidadCobroCodigoSnapshot,
        bool $esOperacionInterna,
        string $cantidad,
        string $precioUnitario,
        string $costoUnitario = '0.00',
        string $subtotal = '0.00',
        string $tasaImpuesto = '0.0000',
        string $impuestoTotal = '0.00',
        string $total = '0.00',
        string $costoTotal = '0.00',
        string $monedaCodigo = 'PEN',
        string $estado = 'SOLICITADO',
        string $fechaServicio = '',
        ?string $horaServicio = null,
        ?string $ejecutadoEn = null,
        ?string $canceladaEn = null,
        ?string $motivoCancelacion = null,
        ?string $observaciones = null,
        int $solicitadoPorActorId = 1,
        ?int $ejecutadoPorActorId = null,
        ?int $canceladaPorActorId = null,
        ?string $creadoEn = null,
        ?string $actualizadoEn = null
    ) {
        $this->id = $id;
        $this->codigo = trim(strtoupper($codigo));
        $this->reservaId = $reservaId;
        $this->estadiaId = $estadiaId;
        $this->servicioId = $servicioId;
        $this->proveedorId = $esOperacionInterna ? null : $proveedorId;
        $this->descripcionServicioSnapshot = trim($descripcionServicioSnapshot);
        $this->categoriaCodigoSnapshot = trim(strtoupper($categoriaCodigoSnapshot));
        $this->modalidadCobroCodigoSnapshot = trim(strtoupper($modalidadCobroCodigoSnapshot));
        $this->esOperacionInterna = $esOperacionInterna;
        $this->cantidad = number_format((float) $cantidad, 2, '.', '');
        $this->precioUnitario = number_format((float) $precioUnitario, 2, '.', '');
        $this->costoUnitario = number_format((float) $costoUnitario, 2, '.', '');
        $this->subtotal = number_format((float) $subtotal, 2, '.', '');
        $this->tasaImpuesto = number_format((float) $tasaImpuesto, 4, '.', '');
        $this->impuestoTotal = number_format((float) $impuestoTotal, 2, '.', '');
        $this->total = number_format((float) $total, 2, '.', '');
        $this->costoTotal = number_format((float) $costoTotal, 2, '.', '');
        $this->monedaCodigo = trim(strtoupper($monedaCodigo));
        $this->estado = trim(strtoupper($estado));
        $this->fechaServicio = trim($fechaServicio);
        $this->horaServicio = $horaServicio !== null ? trim($horaServicio) : null;
        $this->ejecutadoEn = $ejecutadoEn;
        $this->canceladaEn = $canceladaEn;
        $this->motivoCancelacion = $motivoCancelacion !== null ? trim($motivoCancelacion) : null;
        $this->observaciones = $observaciones !== null ? trim($observaciones) : null;
        $this->solicitadoPorActorId = $solicitadoPorActorId;
        $this->ejecutadoPorActorId = $ejecutadoPorActorId;
        $this->canceladaPorActorId = $canceladaPorActorId;
        $this->creadoEn = $creadoEn;
        $this->actualizadoEn = $actualizadoEn;
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerCodigo(): string
    {
        return $this->codigo;
    }

    public function obtenerReservaId(): int
    {
        return $this->reservaId;
    }

    public function obtenerEstadiaId(): ?int
    {
        return $this->estadiaId;
    }

    public function obtenerServicioId(): int
    {
        return $this->servicioId;
    }

    public function obtenerProveedorId(): ?int
    {
        return $this->proveedorId;
    }

    public function obtenerDescripcionServicioSnapshot(): string
    {
        return $this->descripcionServicioSnapshot;
    }

    public function obtenerConceptoServicio(): string
    {
        return $this->descripcionServicioSnapshot;
    }

    public function obtenerCategoriaCodigoSnapshot(): string
    {
        return $this->categoriaCodigoSnapshot;
    }

    public function obtenerModalidadCobroCodigoSnapshot(): string
    {
        return $this->modalidadCobroCodigoSnapshot;
    }

    public function esOperacionInterna(): bool
    {
        return $this->esOperacionInterna;
    }

    public function obtenerCantidad(): string
    {
        return $this->cantidad;
    }

    public function obtenerPrecioUnitario(): string
    {
        return $this->precioUnitario;
    }

    public function obtenerCostoUnitario(): string
    {
        return $this->costoUnitario;
    }

    public function obtenerSubtotal(): string
    {
        return $this->subtotal;
    }

    public function obtenerTasaImpuesto(): string
    {
        return $this->tasaImpuesto;
    }

    public function obtenerImpuestoTotal(): string
    {
        return $this->impuestoTotal;
    }

    public function obtenerTotal(): string
    {
        return $this->total;
    }

    public function obtenerCostoTotal(): string
    {
        return $this->costoTotal;
    }

    public function obtenerMonedaCodigo(): string
    {
        return $this->monedaCodigo;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function esSolicitado(): bool
    {
        return $this->estado === 'SOLICITADO';
    }

    public function esConfirmado(): bool
    {
        return $this->estado === 'CONFIRMADO';
    }

    public function esEjecutado(): bool
    {
        return $this->estado === 'EJECUTADO';
    }

    public function esCancelado(): bool
    {
        return $this->estado === 'CANCELADO';
    }

    public function obtenerFechaServicio(): string
    {
        return $this->fechaServicio;
    }

    public function obtenerHoraServicio(): ?string
    {
        return $this->horaServicio;
    }

    public function obtenerEjecutadoEn(): ?string
    {
        return $this->ejecutadoEn;
    }

    public function obtenerCanceladaEn(): ?string
    {
        return $this->canceladaEn;
    }

    public function obtenerMotivoCancelacion(): ?string
    {
        return $this->motivoCancelacion;
    }

    public function obtenerObservaciones(): ?string
    {
        return $this->observaciones;
    }

    public function obtenerSolicitadoPorActorId(): int
    {
        return $this->solicitadoPorActorId;
    }

    public function obtenerEjecutadoPorActorId(): ?int
    {
        return $this->ejecutadoPorActorId;
    }

    public function obtenerCanceladaPorActorId(): ?int
    {
        return $this->canceladaPorActorId;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function obtenerActualizadoEn(): ?string
    {
        return $this->actualizadoEn;
    }

    public function asignarMetadatos(
        ?string $reservaCodigo,
        ?string $titularNombreCompleto,
        ?string $estadiaCodigo,
        ?string $unidadNombre,
        ?string $servicioNombre,
        ?string $proveedorRazonSocial,
        ?string $proveedorCodigo
    ): void {
        $this->reservaCodigo = $reservaCodigo;
        $this->titularNombreCompleto = $titularNombreCompleto;
        $this->estadiaCodigo = $estadiaCodigo;
        $this->unidadNombre = $unidadNombre;
        $this->servicioNombre = $servicioNombre;
        $this->proveedorRazonSocial = $proveedorRazonSocial;
        $this->proveedorCodigo = $proveedorCodigo;
    }

    public function obtenerReservaCodigo(): ?string
    {
        return $this->reservaCodigo;
    }

    public function obtenerTitularNombreCompleto(): ?string
    {
        return $this->titularNombreCompleto;
    }

    public function obtenerEstadiaCodigo(): ?string
    {
        return $this->estadiaCodigo;
    }

    public function obtenerUnidadNombre(): ?string
    {
        return $this->unidadNombre;
    }

    public function obtenerServicioNombre(): ?string
    {
        return $this->servicioNombre;
    }

    public function obtenerProveedorRazonSocial(): ?string
    {
        return $this->proveedorRazonSocial;
    }

    public function obtenerProveedorCodigo(): ?string
    {
        return $this->proveedorCodigo;
    }

    public function asignarTraslado(?ServicioTraslado $traslado): void
    {
        $this->traslado = $traslado;
    }

    public function obtenerTraslado(): ?ServicioTraslado
    {
        return $this->traslado;
    }

    /**
     * @param array<string, mixed> $datos
     */
    public static function desdeArreglo(array $datos): self
    {
        $esInterno = !empty($datos['es_operacion_interna']) || empty($datos['proveedor_id']);

        $servicioContratado = new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (string) ($datos['codigo'] ?? ''),
            (int) ($datos['reserva_id'] ?? 0),
            isset($datos['estadia_id']) && $datos['estadia_id'] !== '' ? (int) $datos['estadia_id'] : null,
            (int) ($datos['servicio_id'] ?? 0),
            !$esInterno && isset($datos['proveedor_id']) && $datos['proveedor_id'] !== '' ? (int) $datos['proveedor_id'] : null,
            (string) ($datos['descripcion_servicio_snapshot'] ?? ''),
            (string) ($datos['categoria_codigo_snapshot'] ?? ''),
            (string) ($datos['modalidad_cobro_codigo_snapshot'] ?? ''),
            $esInterno,
            (string) ($datos['cantidad'] ?? '1.00'),
            (string) ($datos['precio_unitario'] ?? '0.00'),
            (string) ($datos['costo_unitario'] ?? '0.00'),
            (string) ($datos['subtotal'] ?? '0.00'),
            (string) ($datos['tasa_impuesto'] ?? '0.0000'),
            (string) ($datos['impuesto_total'] ?? '0.00'),
            (string) ($datos['total'] ?? '0.00'),
            (string) ($datos['costo_total'] ?? '0.00'),
            (string) ($datos['moneda_codigo'] ?? 'PEN'),
            (string) ($datos['estado'] ?? 'SOLICITADO'),
            (string) ($datos['fecha_servicio'] ?? ''),
            isset($datos['hora_servicio']) ? (string) $datos['hora_servicio'] : null,
            isset($datos['ejecutado_en']) ? (string) $datos['ejecutado_en'] : null,
            isset($datos['cancelada_en']) ? (string) $datos['cancelada_en'] : null,
            isset($datos['motivo_cancelacion']) ? (string) $datos['motivo_cancelacion'] : null,
            isset($datos['observaciones']) ? (string) $datos['observaciones'] : null,
            (int) ($datos['solicitado_por_actor_id'] ?? 1),
            isset($datos['ejecutado_por_actor_id']) && $datos['ejecutado_por_actor_id'] !== '' ? (int) $datos['ejecutado_por_actor_id'] : null,
            isset($datos['cancelada_por_actor_id']) && $datos['cancelada_por_actor_id'] !== '' ? (int) $datos['cancelada_por_actor_id'] : null,
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            isset($datos['actualizado_en']) ? (string) $datos['actualizado_en'] : null
        );

        $servicioContratado->asignarMetadatos(
            isset($datos['reserva_codigo']) ? (string) $datos['reserva_codigo'] : null,
            isset($datos['titular_nombre_completo']) ? (string) $datos['titular_nombre_completo'] : null,
            isset($datos['estadia_codigo']) ? (string) $datos['estadia_codigo'] : null,
            isset($datos['unidad_nombre']) ? (string) $datos['unidad_nombre'] : null,
            isset($datos['servicio_nombre']) ? (string) $datos['servicio_nombre'] : null,
            isset($datos['proveedor_razon_social']) ? (string) $datos['proveedor_razon_social'] : null,
            isset($datos['proveedor_codigo']) ? (string) $datos['proveedor_codigo'] : null
        );

        if (isset($datos['traslado']) && is_array($datos['traslado'])) {
            $servicioContratado->asignarTraslado(ServicioTraslado::desdeArreglo($datos['traslado']));
        }

        return $servicioContratado;
    }

    /**
     * @return array<string, mixed>
     */
    public function haciaArreglo(): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'reserva_id' => $this->reservaId,
            'reserva_codigo' => $this->reservaCodigo,
            'titular_nombre_completo' => $this->titularNombreCompleto,
            'estadia_id' => $this->estadiaId,
            'estadia_codigo' => $this->estadiaCodigo,
            'unidad_nombre' => $this->unidadNombre,
            'servicio_id' => $this->servicioId,
            'servicio_nombre' => $this->servicioNombre,
            'proveedor_id' => $this->proveedorId,
            'proveedor_codigo' => $this->proveedorCodigo,
            'proveedor_razon_social' => $this->proveedorRazonSocial,
            'descripcion_servicio_snapshot' => $this->descripcionServicioSnapshot,
            'categoria_codigo_snapshot' => $this->categoriaCodigoSnapshot,
            'modalidad_cobro_codigo_snapshot' => $this->modalidadCobroCodigoSnapshot,
            'es_operacion_interna' => $this->esOperacionInterna,
            'cantidad' => $this->cantidad,
            'precio_unitario' => $this->precioUnitario,
            'costo_unitario' => $this->costoUnitario,
            'subtotal' => $this->subtotal,
            'tasa_impuesto' => $this->tasaImpuesto,
            'impuesto_total' => $this->impuestoTotal,
            'total' => $this->total,
            'costo_total' => $this->costoTotal,
            'moneda_codigo' => $this->monedaCodigo,
            'estado' => $this->estado,
            'fecha_servicio' => $this->fechaServicio,
            'hora_servicio' => $this->horaServicio,
            'ejecutado_en' => $this->ejecutadoEn,
            'cancelada_en' => $this->canceladaEn,
            'motivo_cancelacion' => $this->motivoCancelacion,
            'observaciones' => $this->observaciones,
            'solicitado_por_actor_id' => $this->solicitadoPorActorId,
            'ejecutado_por_actor_id' => $this->ejecutadoPorActorId,
            'cancelada_por_actor_id' => $this->canceladaPorActorId,
            'traslado' => $this->traslado?->haciaArreglo(),
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function aArreglo(): array
    {
        return $this->haciaArreglo();
    }
}
