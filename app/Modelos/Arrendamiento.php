<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio que representa un contrato de arrendamiento patrimonial de mediana o larga estancia.
 * 
 * Reglas vinculantes (D-076):
 * - Separación ontológica: RESERVA != ESTADÍA != ARRENDAMIENTO.
 * - Temporalidad cerrada: fecha_fin NOT NULL y fecha_fin > fecha_inicio.
 * - Semántica de disponibilidad: Intervalo semiabierto [fecha_inicio, fecha_fin). fecha_fin queda libre.
 * - Ciclo de vida: BORRADOR -> VIGENTE -> FINALIZADO | RESCINDIDO; o BORRADOR -> CANCELADO.
 */
class Arrendamiento
{
    private ?int $id;
    private string $codigo;
    private int $unidadId;
    private ?int $arrendamientoAnteriorId;
    private string $fechaInicio;
    private string $fechaFin;
    private int $diaVencimiento;
    private string $rentaMensual;
    private string $depositoGarantia;
    private string $montoPrimerPeriodo;
    private bool $esPrimerMesProrrateado;
    private string $monedaCodigo;
    private string $estado;
    private ?string $motivoRescision;
    private ?string $rescididoEn;
    private ?int $rescididoPorActorId;
    private ?string $notasAdicionales;
    private int $creadoPorActorId;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    // Metadatos auxiliares de consulta/proyección
    private ?string $unidadNumero = null;
    private ?string $unidadNombre = null;
    private ?string $propiedadNombre = null;
    private ?string $titularNombreCompleto = null;
    private ?string $titularNumeroDocumento = null;
    private ?int $titularPersonaId = null;
    private ?string $folioCodigo = null;
    private ?int $folioId = null;

    public function __construct(
        ?int $id,
        string $codigo,
        int $unidadId,
        ?int $arrendamientoAnteriorId,
        string $fechaInicio,
        string $fechaFin,
        int $diaVencimiento,
        string $rentaMensual,
        string $depositoGarantia,
        string $montoPrimerPeriodo,
        bool $esPrimerMesProrrateado,
        string $monedaCodigo,
        string $estado,
        ?string $motivoRescision,
        ?string $rescididoEn,
        ?int $rescididoPorActorId,
        ?string $notasAdicionales,
        int $creadoPorActorId,
        ?string $creadoEn = null,
        ?string $actualizadoEn = null
    ) {
        $this->id = $id;
        $this->codigo = $codigo;
        $this->unidadId = $unidadId;
        $this->arrendamientoAnteriorId = $arrendamientoAnteriorId;
        $this->fechaInicio = $fechaInicio;
        $this->fechaFin = $fechaFin;
        $this->diaVencimiento = $diaVencimiento;
        $this->rentaMensual = $rentaMensual;
        $this->depositoGarantia = $depositoGarantia;
        $this->montoPrimerPeriodo = $montoPrimerPeriodo;
        $this->esPrimerMesProrrateado = $esPrimerMesProrrateado;
        $this->monedaCodigo = $monedaCodigo;
        $this->estado = $estado;
        $this->motivoRescision = $motivoRescision;
        $this->rescididoEn = $rescididoEn;
        $this->rescididoPorActorId = $rescididoPorActorId;
        $this->notasAdicionales = $notasAdicionales;
        $this->creadoPorActorId = $creadoPorActorId;
        $this->creadoEn = $creadoEn;
        $this->actualizadoEn = $actualizadoEn;
    }

    public function obtenerId(): ?int { return $this->id; }
    public function obtenerCodigo(): string { return $this->codigo; }
    public function obtenerUnidadId(): int { return $this->unidadId; }
    public function obtenerArrendamientoAnteriorId(): ?int { return $this->arrendamientoAnteriorId; }
    public function obtenerFechaInicio(): string { return $this->fechaInicio; }
    public function obtenerFechaFin(): string { return $this->fechaFin; }
    public function obtenerDiaVencimiento(): int { return $this->diaVencimiento; }
    public function obtenerRentaMensual(): string { return $this->rentaMensual; }
    public function obtenerDepositoGarantia(): string { return $this->depositoGarantia; }
    public function obtenerMontoPrimerPeriodo(): string { return $this->montoPrimerPeriodo; }
    public function esPrimerMesProrrateado(): bool { return $this->esPrimerMesProrrateado; }
    public function obtenerMonedaCodigo(): string { return $this->monedaCodigo; }
    public function obtenerEstado(): string { return $this->estado; }
    public function obtenerMotivoRescision(): ?string { return $this->motivoRescision; }
    public function obtenerRescididoEn(): ?string { return $this->rescididoEn; }
    public function obtenerRescididoPorActorId(): ?int { return $this->rescididoPorActorId; }
    public function obtenerNotasAdicionales(): ?string { return $this->notasAdicionales; }
    public function obtenerCreadoPorActorId(): int { return $this->creadoPorActorId; }
    public function obtenerCreadoEn(): ?string { return $this->creadoEn; }
    public function obtenerActualizadoEn(): ?string { return $this->actualizadoEn; }

    // Helpers de estado
    public function esBorrador(): bool { return $this->estado === 'BORRADOR'; }
    public function esVigente(): bool { return $this->estado === 'VIGENTE'; }
    public function esFinalizado(): bool { return $this->estado === 'FINALIZADO'; }
    public function esRescindido(): bool { return $this->estado === 'RESCINDIDO'; }
    public function esCancelado(): bool { return $this->estado === 'CANCELADO'; }

    // Metadatos auxiliares
    public function obtenerUnidadNumero(): ?string { return $this->unidadNumero; }
    public function fijarUnidadNumero(?string $val): void { $this->unidadNumero = $val; }
    public function obtenerUnidadNombre(): ?string { return $this->unidadNombre; }
    public function fijarUnidadNombre(?string $val): void { $this->unidadNombre = $val; }
    public function obtenerPropiedadNombre(): ?string { return $this->propiedadNombre; }
    public function fijarPropiedadNombre(?string $val): void { $this->propiedadNombre = $val; }
    public function obtenerTitularNombreCompleto(): ?string { return $this->titularNombreCompleto; }
    public function fijarTitularNombreCompleto(?string $val): void { $this->titularNombreCompleto = $val; }
    public function obtenerTitularNumeroDocumento(): ?string { return $this->titularNumeroDocumento; }
    public function fijarTitularNumeroDocumento(?string $val): void { $this->titularNumeroDocumento = $val; }
    public function obtenerTitularPersonaId(): ?int { return $this->titularPersonaId; }
    public function fijarTitularPersonaId(?int $val): void { $this->titularPersonaId = $val; }
    public function obtenerFolioCodigo(): ?string { return $this->folioCodigo; }
    public function fijarFolioCodigo(?string $val): void { $this->folioCodigo = $val; }
    public function obtenerFolioId(): ?int { return $this->folioId; }
    public function fijarFolioId(?int $val): void { $this->folioId = $val; }

    /**
     * @return array<string, mixed>
     */
    public function haciaArreglo(): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'unidad_id' => $this->unidadId,
            'arrendamiento_anterior_id' => $this->arrendamientoAnteriorId,
            'fecha_inicio' => $this->fechaInicio,
            'fecha_fin' => $this->fechaFin,
            'dia_vencimiento' => $this->diaVencimiento,
            'renta_mensual' => $this->rentaMensual,
            'deposito_garantia' => $this->depositoGarantia,
            'monto_primer_periodo' => $this->montoPrimerPeriodo,
            'es_primer_mes_prorrateado' => $this->esPrimerMesProrrateado ? 1 : 0,
            'moneda_codigo' => $this->monedaCodigo,
            'estado' => $this->estado,
            'motivo_rescision' => $this->motivoRescision,
            'rescidido_en' => $this->rescididoEn,
            'rescidido_por_actor_id' => $this->rescididoPorActorId,
            'notas_adicionales' => $this->notasAdicionales,
            'creado_por_actor_id' => $this->creadoPorActorId,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
            // Proyecciones
            'unidad_numero' => $this->unidadNumero,
            'unidad_nombre' => $this->unidadNombre,
            'propiedad_nombre' => $this->propiedadNombre,
            'titular_nombre_completo' => $this->titularNombreCompleto,
            'titular_numero_documento' => $this->titularNumeroDocumento,
            'titular_persona_id' => $this->titularPersonaId,
            'folio_codigo' => $this->folioCodigo,
            'folio_id' => $this->folioId,
        ];
    }

    /**
     * @param array<string, mixed> $datos
     */
    public static function desdeArreglo(array $datos): self
    {
        $arr = new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (string) ($datos['codigo'] ?? ''),
            (int) ($datos['unidad_id'] ?? 0),
            isset($datos['arrendamiento_anterior_id']) && $datos['arrendamiento_anterior_id'] !== null ? (int) $datos['arrendamiento_anterior_id'] : null,
            (string) ($datos['fecha_inicio'] ?? ''),
            (string) ($datos['fecha_fin'] ?? ''),
            (int) ($datos['dia_vencimiento'] ?? 1),
            number_format((float) ($datos['renta_mensual'] ?? 0), 2, '.', ''),
            number_format((float) ($datos['deposito_garantia'] ?? 0), 2, '.', ''),
            number_format((float) ($datos['monto_primer_periodo'] ?? 0), 2, '.', ''),
            !empty($datos['es_primer_mes_prorrateado']),
            (string) ($datos['moneda_codigo'] ?? 'PEN'),
            (string) ($datos['estado'] ?? 'BORRADOR'),
            isset($datos['motivo_rescision']) && $datos['motivo_rescision'] !== null ? (string) $datos['motivo_rescision'] : null,
            isset($datos['rescidido_en']) && $datos['rescidido_en'] !== null ? (string) $datos['rescidido_en'] : null,
            isset($datos['rescidido_por_actor_id']) && $datos['rescidido_por_actor_id'] !== null ? (int) $datos['rescidido_por_actor_id'] : null,
            isset($datos['notas_adicionales']) && $datos['notas_adicionales'] !== null ? (string) $datos['notas_adicionales'] : null,
            (int) ($datos['creado_por_actor_id'] ?? 0),
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            isset($datos['actualizado_en']) ? (string) $datos['actualizado_en'] : null
        );

        if (isset($datos['unidad_numero'])) {
            $arr->fijarUnidadNumero((string) $datos['unidad_numero']);
        }
        if (isset($datos['unidad_nombre'])) {
            $arr->fijarUnidadNombre((string) $datos['unidad_nombre']);
        }
        if (isset($datos['propiedad_nombre'])) {
            $arr->fijarPropiedadNombre((string) $datos['propiedad_nombre']);
        }
        if (isset($datos['titular_nombre_completo'])) {
            $arr->fijarTitularNombreCompleto((string) $datos['titular_nombre_completo']);
        }
        if (isset($datos['titular_numero_documento'])) {
            $arr->fijarTitularNumeroDocumento((string) $datos['titular_numero_documento']);
        }
        if (isset($datos['titular_persona_id'])) {
            $arr->fijarTitularPersonaId((int) $datos['titular_persona_id']);
        }
        if (isset($datos['folio_codigo'])) {
            $arr->fijarFolioCodigo((string) $datos['folio_codigo']);
        }
        if (isset($datos['folio_id'])) {
            $arr->fijarFolioId((int) $datos['folio_id']);
        }

        return $arr;
    }
}
