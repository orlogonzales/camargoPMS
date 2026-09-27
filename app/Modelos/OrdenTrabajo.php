<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio que representa una orden de trabajo de mantenimiento técnico o preventivo.
 * 
 * Reglas vinculantes (D-077):
 * - INCIDENCIA != ORDEN DE TRABAJO != BLOQUEO OPERATIVO.
 * - Es la unidad de gestión técnica, asignación (interno/externo) y costeo (estimado, materiales, mano de obra, total).
 * - requiere_bloqueo = 1: Materializa noches en inventario_diario_unidades en estado PROGRAMADA para el intervalo semiabierto [inicio, fin).
 * - requiere_bloqueo = 0: fecha_bloqueo_inicio y fecha_bloqueo_fin son estrictamente NULL.
 * - Ciclo de vida: BORRADOR -> PROGRAMADA -> EN_PROCESO -> COMPLETADA; o CANCELADA en cualquier fase previa con motivo justificado.
 */
class OrdenTrabajo
{
    private ?int $id;
    private string $codigo;
    private string $tipo;
    private string $prioridad;
    private int $propiedadId;
    private ?int $unidadId;
    private string $titulo;
    private string $descripcion;
    private string $tipoAsignacion;
    private ?int $colaboradorAsignadoId;
    private ?int $proveedorId;
    private ?string $numeroComprobanteProveedor;
    private bool $requiereBloqueo;
    private string $fechaProgramadaInicio;
    private string $fechaProgramadaFin;
    private ?string $fechaBloqueoInicio;
    private ?string $fechaBloqueoFin;
    private ?string $fechaEjecucionInicio;
    private ?string $fechaEjecucionFin;
    private string $costoEstimado;
    private string $costoManoObra;
    private string $costoMateriales;
    private string $costoTotal;
    private string $monedaCodigo;
    private string $estado;
    private int $creadoPorActorId;
    private ?int $completadoPorActorId;
    private ?int $canceladoPorActorId;
    private ?string $motivoCancelacion;
    private ?string $notasCierre;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    // Proyecciones y datos auxiliares
    private ?string $propiedadNombre = null;
    private ?string $unidadNumero = null;
    private ?string $unidadNombre = null;
    private ?string $colaboradorNombreCompleto = null;
    private ?string $proveedorRazonSocial = null;
    /** @var array<int, array> */
    private array $incidenciasAsociadas = [];

    public function __construct(
        ?int $id,
        string $codigo,
        string $tipo,
        string $prioridad,
        int $propiedadId,
        ?int $unidadId,
        string $titulo,
        string $descripcion,
        string $tipoAsignacion,
        ?int $colaboradorAsignadoId,
        ?int $proveedorId,
        ?string $numeroComprobanteProveedor,
        bool $requiereBloqueo,
        string $fechaProgramadaInicio,
        string $fechaProgramadaFin,
        ?string $fechaBloqueoInicio,
        ?string $fechaBloqueoFin,
        ?string $fechaEjecucionInicio,
        ?string $fechaEjecucionFin,
        string $costoEstimado,
        string $costoManoObra,
        string $costoMateriales,
        string $costoTotal,
        string $monedaCodigo,
        string $estado,
        int $creadoPorActorId,
        ?int $completadoPorActorId = null,
        ?int $canceladoPorActorId = null,
        ?string $motivoCancelacion = null,
        ?string $notasCierre = null,
        ?string $creadoEn = null,
        ?string $actualizadoEn = null
    ) {
        $this->id = $id;
        $this->codigo = $codigo;
        $this->tipo = $tipo;
        $this->prioridad = $prioridad;
        $this->propiedadId = $propiedadId;
        $this->unidadId = $unidadId;
        $this->titulo = $titulo;
        $this->descripcion = $descripcion;
        $this->tipoAsignacion = $tipoAsignacion;
        $this->colaboradorAsignadoId = $colaboradorAsignadoId;
        $this->proveedorId = $proveedorId;
        $this->numeroComprobanteProveedor = $numeroComprobanteProveedor;
        $this->requiereBloqueo = $requiereBloqueo;
        $this->fechaProgramadaInicio = $fechaProgramadaInicio;
        $this->fechaProgramadaFin = $fechaProgramadaFin;
        $this->fechaBloqueoInicio = $fechaBloqueoInicio;
        $this->fechaBloqueoFin = $fechaBloqueoFin;
        $this->fechaEjecucionInicio = $fechaEjecucionInicio;
        $this->fechaEjecucionFin = $fechaEjecucionFin;
        $this->costoEstimado = $costoEstimado;
        $this->costoManoObra = $costoManoObra;
        $this->costoMateriales = $costoMateriales;
        $this->costoTotal = $costoTotal;
        $this->monedaCodigo = $monedaCodigo;
        $this->estado = $estado;
        $this->creadoPorActorId = $creadoPorActorId;
        $this->completadoPorActorId = $completadoPorActorId;
        $this->canceladoPorActorId = $canceladoPorActorId;
        $this->motivoCancelacion = $motivoCancelacion;
        $this->notasCierre = $notasCierre;
        $this->creadoEn = $creadoEn;
        $this->actualizadoEn = $actualizadoEn;
    }

    public function obtenerId(): ?int { return $this->id; }
    public function obtenerCodigo(): string { return $this->codigo; }
    public function obtenerTipo(): string { return $this->tipo; }
    public function obtenerPrioridad(): string { return $this->prioridad; }
    public function obtenerPropiedadId(): int { return $this->propiedadId; }
    public function obtenerUnidadId(): ?int { return $this->unidadId; }
    public function obtenerTitulo(): string { return $this->titulo; }
    public function obtenerDescripcion(): string { return $this->descripcion; }
    public function obtenerTipoAsignacion(): string { return $this->tipoAsignacion; }
    public function obtenerColaboradorAsignadoId(): ?int { return $this->colaboradorAsignadoId; }
    public function obtenerProveedorId(): ?int { return $this->proveedorId; }
    public function obtenerNumeroComprobanteProveedor(): ?string { return $this->numeroComprobanteProveedor; }
    public function requiereBloqueo(): bool { return $this->requiereBloqueo; }
    public function obtenerFechaProgramadaInicio(): string { return $this->fechaProgramadaInicio; }
    public function obtenerFechaProgramadaFin(): string { return $this->fechaProgramadaFin; }
    public function obtenerFechaBloqueoInicio(): ?string { return $this->fechaBloqueoInicio; }
    public function obtenerFechaBloqueoFin(): ?string { return $this->fechaBloqueoFin; }
    public function obtenerFechaEjecucionInicio(): ?string { return $this->fechaEjecucionInicio; }
    public function obtenerFechaEjecucionFin(): ?string { return $this->fechaEjecucionFin; }
    public function obtenerCostoEstimado(): string { return $this->costoEstimado; }
    public function obtenerCostoManoObra(): string { return $this->costoManoObra; }
    public function obtenerCostoMateriales(): string { return $this->costoMateriales; }
    public function obtenerCostoTotal(): string { return $this->costoTotal; }
    public function obtenerMonedaCodigo(): string { return $this->monedaCodigo; }
    public function obtenerEstado(): string { return $this->estado; }
    public function obtenerCreadoPorActorId(): int { return $this->creadoPorActorId; }
    public function obtenerCompletadoPorActorId(): ?int { return $this->completadoPorActorId; }
    public function obtenerCanceladoPorActorId(): ?int { return $this->canceladoPorActorId; }
    public function obtenerMotivoCancelacion(): ?string { return $this->motivoCancelacion; }
    public function obtenerNotasCierre(): ?string { return $this->notasCierre; }
    public function obtenerCreadoEn(): ?string { return $this->creadoEn; }
    public function obtenerActualizadoEn(): ?string { return $this->actualizadoEn; }

    public function obtenerPropiedadNombre(): ?string { return $this->propiedadNombre; }
    public function fijarPropiedadNombre(?string $nombre): void { $this->propiedadNombre = $nombre; }

    public function obtenerUnidadNumero(): ?string { return $this->unidadNumero; }
    public function fijarUnidadNumero(?string $numero): void { $this->unidadNumero = $numero; }

    public function obtenerUnidadNombre(): ?string { return $this->unidadNombre; }
    public function fijarUnidadNombre(?string $nombre): void { $this->unidadNombre = $nombre; }

    public function obtenerColaboradorNombreCompleto(): ?string { return $this->colaboradorNombreCompleto; }
    public function fijarColaboradorNombreCompleto(?string $nombre): void { $this->colaboradorNombreCompleto = $nombre; }

    public function obtenerProveedorRazonSocial(): ?string { return $this->proveedorRazonSocial; }
    public function fijarProveedorRazonSocial(?string $razon): void { $this->proveedorRazonSocial = $razon; }

    public function obtenerIncidenciasAsociadas(): array { return $this->incidenciasAsociadas; }
    public function fijarIncidenciasAsociadas(array $incidencias): void { $this->incidenciasAsociadas = $incidencias; }

    public function estaEnBorrador(): bool { return $this->estado === 'BORRADOR'; }
    public function estaProgramada(): bool { return $this->estado === 'PROGRAMADA'; }
    public function estaEnProceso(): bool { return $this->estado === 'EN_PROCESO'; }
    public function estaCompletada(): bool { return $this->estado === 'COMPLETADA'; }
    public function estaCancelada(): bool { return $this->estado === 'CANCELADA'; }

    public function tieneBloqueoActivo(): bool
    {
        return $this->requiereBloqueo && in_array($this->estado, ['PROGRAMADA', 'EN_PROCESO'], true);
    }

    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'tipo' => $this->tipo,
            'prioridad' => $this->prioridad,
            'propiedad_id' => $this->propiedadId,
            'unidad_id' => $this->unidadId,
            'titulo' => $this->titulo,
            'descripcion' => $this->descripcion,
            'tipo_asignacion' => $this->tipoAsignacion,
            'colaborador_asignado_id' => $this->colaboradorAsignadoId,
            'proveedor_id' => $this->proveedorId,
            'numero_comprobante_proveedor' => $this->numeroComprobanteProveedor,
            'requiere_bloqueo' => $this->requiereBloqueo,
            'fecha_programada_inicio' => $this->fechaProgramadaInicio,
            'fecha_programada_fin' => $this->fechaProgramadaFin,
            'fecha_bloqueo_inicio' => $this->fechaBloqueoInicio,
            'fecha_bloqueo_fin' => $this->fechaBloqueoFin,
            'fecha_ejecucion_inicio' => $this->fechaEjecucionInicio,
            'fecha_ejecucion_fin' => $this->fechaEjecucionFin,
            'costo_estimado' => $this->costoEstimado,
            'costo_mano_obra' => $this->costoManoObra,
            'costo_materiales' => $this->costoMateriales,
            'costo_total' => $this->costoTotal,
            'moneda_codigo' => $this->monedaCodigo,
            'estado' => $this->estado,
            'creado_por_actor_id' => $this->creadoPorActorId,
            'completado_por_actor_id' => $this->completadoPorActorId,
            'cancelado_por_actor_id' => $this->canceladoPorActorId,
            'motivo_cancelacion' => $this->motivoCancelacion,
            'notas_cierre' => $this->notasCierre,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
            'propiedad_nombre' => $this->propiedadNombre,
            'unidad_numero' => $this->unidadNumero,
            'unidad_nombre' => $this->unidadNombre,
            'colaborador_nombre_completo' => $this->colaboradorNombreCompleto,
            'proveedor_razon_social' => $this->proveedorRazonSocial,
            'incidencias_asociadas' => $this->incidenciasAsociadas,
        ];
    }

    public static function desdeArreglo(array $datos): self
    {
        $ot = new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (string) ($datos['codigo'] ?? ''),
            (string) ($datos['tipo'] ?? 'CORRECTIVO'),
            (string) ($datos['prioridad'] ?? 'MEDIA'),
            (int) ($datos['propiedad_id'] ?? 0),
            isset($datos['unidad_id']) && $datos['unidad_id'] !== null ? (int) $datos['unidad_id'] : null,
            (string) ($datos['titulo'] ?? ''),
            (string) ($datos['descripcion'] ?? ''),
            (string) ($datos['tipo_asignacion'] ?? 'INTERNO'),
            isset($datos['colaborador_asignado_id']) && $datos['colaborador_asignado_id'] !== null ? (int) $datos['colaborador_asignado_id'] : null,
            isset($datos['proveedor_id']) && $datos['proveedor_id'] !== null ? (int) $datos['proveedor_id'] : null,
            isset($datos['numero_comprobante_proveedor']) ? (string) $datos['numero_comprobante_proveedor'] : null,
            !empty($datos['requiere_bloqueo']),
            (string) ($datos['fecha_programada_inicio'] ?? ''),
            (string) ($datos['fecha_programada_fin'] ?? ''),
            isset($datos['fecha_bloqueo_inicio']) && $datos['fecha_bloqueo_inicio'] !== null ? (string) $datos['fecha_bloqueo_inicio'] : null,
            isset($datos['fecha_bloqueo_fin']) && $datos['fecha_bloqueo_fin'] !== null ? (string) $datos['fecha_bloqueo_fin'] : null,
            isset($datos['fecha_ejecucion_inicio']) ? (string) $datos['fecha_ejecucion_inicio'] : null,
            isset($datos['fecha_ejecucion_fin']) ? (string) $datos['fecha_ejecucion_fin'] : null,
            number_format((float) ($datos['costo_estimado'] ?? 0), 2, '.', ''),
            number_format((float) ($datos['costo_mano_obra'] ?? 0), 2, '.', ''),
            number_format((float) ($datos['costo_materiales'] ?? 0), 2, '.', ''),
            number_format((float) ($datos['costo_total'] ?? 0), 2, '.', ''),
            (string) ($datos['moneda_codigo'] ?? 'PEN'),
            (string) ($datos['estado'] ?? 'BORRADOR'),
            (int) ($datos['creado_por_actor_id'] ?? 0),
            isset($datos['completado_por_actor_id']) && $datos['completado_por_actor_id'] !== null ? (int) $datos['completado_por_actor_id'] : null,
            isset($datos['cancelado_por_actor_id']) && $datos['cancelado_por_actor_id'] !== null ? (int) $datos['cancelado_por_actor_id'] : null,
            isset($datos['motivo_cancelacion']) ? (string) $datos['motivo_cancelacion'] : null,
            isset($datos['notas_cierre']) ? (string) $datos['notas_cierre'] : null,
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            isset($datos['actualizado_en']) ? (string) $datos['actualizado_en'] : null
        );

        if (isset($datos['propiedad_nombre'])) {
            $ot->fijarPropiedadNombre((string) $datos['propiedad_nombre']);
        }
        if (isset($datos['unidad_numero'])) {
            $ot->fijarUnidadNumero((string) $datos['unidad_numero']);
        }
        if (isset($datos['unidad_nombre'])) {
            $ot->fijarUnidadNombre((string) $datos['unidad_nombre']);
        }
        if (isset($datos['colaborador_nombre_completo'])) {
            $ot->fijarColaboradorNombreCompleto((string) $datos['colaborador_nombre_completo']);
        }
        if (isset($datos['proveedor_razon_social'])) {
            $ot->fijarProveedorRazonSocial((string) $datos['proveedor_razon_social']);
        }
        if (isset($datos['incidencias_asociadas']) && is_array($datos['incidencias_asociadas'])) {
            $ot->fijarIncidenciasAsociadas($datos['incidencias_asociadas']);
        }

        return $ot;
    }
}
