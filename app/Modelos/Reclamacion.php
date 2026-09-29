<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

use InvalidArgumentException;

/**
 * Modelo de dominio para el Expediente del Libro de Reclamaciones (RECLAMACIONES-1).
 *
 * Principios regulatorios y arquitectónicos:
 * 1. RECLAMO ≠ QUEJA (Tipificación legal excluyente según D.S. 011-2011-PCM)
 * 2. RECLAMANTE → PERSONA con Snapshot Legal T0 inmutable (Ley 29571)
 * 3. EXPEDIENTE INMUTABLE Y CERO DELETE FÍSICO
 * 4. PLAZO LEGAL: 15 días hábiles improrrogables (Ley 31435) con suspensión de hasta 5 días hábiles (D.S. 101-2022-PCM)
 */
class Reclamacion
{
    public const TIPO_RECLAMO = 'RECLAMO';
    public const TIPO_QUEJA = 'QUEJA';

    public const TIPOS_VALIDOS = [
        self::TIPO_RECLAMO,
        self::TIPO_QUEJA,
    ];

    public const BIEN_PRODUCTO = 'PRODUCTO';
    public const BIEN_SERVICIO = 'SERVICIO';

    public const BIENES_VALIDOS = [
        self::BIEN_PRODUCTO,
        self::BIEN_SERVICIO,
    ];

    public const CANAL_VIRTUAL = 'VIRTUAL';
    public const CANAL_PRESENCIAL = 'PRESENCIAL';

    public const CANALES_VALIDOS = [
        self::CANAL_VIRTUAL,
        self::CANAL_PRESENCIAL,
    ];

    public const ESTADO_REGISTRADO = 'REGISTRADO';
    public const ESTADO_EN_PROCESO = 'EN_PROCESO';
    public const ESTADO_SUSPENDIDO_OFRECIMIENTO = 'SUSPENDIDO_OFRECIMIENTO';
    public const ESTADO_ATENDIDO = 'ATENDIDO';
    public const ESTADO_CONCLUIDO_POR_ACUERDO = 'CONCLUIDO_POR_ACUERDO';
    public const ESTADO_ANULADO = 'ANULADO';

    public const ESTADOS_VALIDOS = [
        self::ESTADO_REGISTRADO,
        self::ESTADO_EN_PROCESO,
        self::ESTADO_SUSPENDIDO_OFRECIMIENTO,
        self::ESTADO_ATENDIDO,
        self::ESTADO_CONCLUIDO_POR_ACUERDO,
        self::ESTADO_ANULADO,
    ];

    private ?int $id;
    private string $codigoHoja;
    private string $codigoInterno;
    private int $empresaId;
    private int $propiedadId;
    private int $consumidorPersonaId;
    private bool $esMenorEdad;
    private ?int $apoderadoPersonaId;
    private string $tipo;
    private string $tipoBien;
    private string $montoReclamado;
    private string $moneda;
    private string $descripcionBien;
    private string $detalleReclamacion;
    private string $pedidoConsumidor;
    private string $canalEntrada;
    private string $estado;
    private string $fechaInterposicion;
    private string $fechaLimiteLegal;
    private int $diasHabilesConsumidos;
    private ?string $fechaSuspension;
    private ?string $fechaLimiteOfrecimiento;
    private ?int $documentoEmitidoId;
    private ?string $motivoAnulacion;
    private ?int $anuladoPorActorId;
    private ?string $anuladoEn;
    private array $snapshotConsumidor;
    private array $snapshotProveedor;
    private ?int $creadoPorActorId;
    private ?int $actualizadoPorActorId;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    // Relaciones enriquecidas
    private ?Persona $consumidor = null;
    private ?Persona $apoderado = null;
    private ?array $empresa = null;
    private ?array $propiedad = null;
    /** @var array<int, ReclamacionActuacion> */
    private array $actuaciones = [];

    public function __construct(
        ?int $id,
        string $codigoHoja,
        string $codigoInterno,
        int $empresaId,
        int $propiedadId,
        int $consumidorPersonaId,
        bool $esMenorEdad,
        ?int $apoderadoPersonaId,
        string $tipo,
        string $tipoBien,
        string $montoReclamado,
        string $moneda,
        string $descripcionBien,
        string $detalleReclamacion,
        string $pedidoConsumidor,
        string $canalEntrada = self::CANAL_VIRTUAL,
        string $estado = self::ESTADO_REGISTRADO,
        ?string $fechaInterposicion = null,
        ?string $fechaLimiteLegal = null,
        int $diasHabilesConsumidos = 0,
        ?string $fechaSuspension = null,
        ?string $fechaLimiteOfrecimiento = null,
        ?int $documentoEmitidoId = null,
        ?string $motivoAnulacion = null,
        ?int $anuladoPorActorId = null,
        ?string $anuladoEn = null,
        array $snapshotConsumidor = [],
        array $snapshotProveedor = [],
        ?int $creadoPorActorId = null,
        ?int $actualizadoPorActorId = null,
        ?string $creadoEn = null,
        ?string $actualizadoEn = null
    ) {
        $codigoHojaLimpio = trim($codigoHoja);
        if ($codigoHojaLimpio === '') {
            throw new InvalidArgumentException("El número de hoja no puede estar vacío.");
        }

        $codigoInternoLimpio = trim($codigoInterno);
        if ($codigoInternoLimpio === '') {
            throw new InvalidArgumentException("El código interno de reclamación no puede estar vacío.");
        }

        if ($empresaId <= 0) {
            throw new InvalidArgumentException("El ID de empresa debe ser un entero positivo.");
        }

        if ($propiedadId <= 0) {
            throw new InvalidArgumentException("El ID de propiedad debe ser un entero positivo.");
        }

        if ($consumidorPersonaId <= 0) {
            throw new InvalidArgumentException("El ID de la Persona del consumidor debe ser un entero positivo.");
        }

        if ($esMenorEdad && ($apoderadoPersonaId === null || $apoderadoPersonaId <= 0)) {
            throw new InvalidArgumentException("Para consumidores menores de edad, se requiere la identificación del apoderado.");
        }

        if (!in_array($tipo, self::TIPOS_VALIDOS, true)) {
            throw new InvalidArgumentException("Tipo de reclamación inválido: {$tipo} (esperado RECLAMO o QUEJA).");
        }

        if (!in_array($tipoBien, self::BIENES_VALIDOS, true)) {
            throw new InvalidArgumentException("Tipo de bien inválido: {$tipoBien} (esperado PRODUCTO o SERVICIO).");
        }

        if (!in_array($canalEntrada, self::CANALES_VALIDOS, true)) {
            throw new InvalidArgumentException("Canal de entrada inválido: {$canalEntrada}.");
        }

        if (!in_array($estado, self::ESTADOS_VALIDOS, true)) {
            throw new InvalidArgumentException("Estado de reclamación inválido: {$estado}.");
        }

        $descLimpia = trim($descripcionBien);
        if ($descLimpia === '') {
            throw new InvalidArgumentException("La descripción del bien o servicio reclamado es obligatoria.");
        }

        $detLimpio = trim($detalleReclamacion);
        if ($detLimpio === '') {
            throw new InvalidArgumentException("El detalle de los hechos expuestos por el consumidor es obligatorio.");
        }

        $pedLimpio = trim($pedidoConsumidor);
        if ($pedLimpio === '') {
            throw new InvalidArgumentException("El pedido concreto del consumidor es obligatorio.");
        }

        // Formato numérico seguro para monto
        $montoLimpio = trim($montoReclamado);
        if (!is_numeric($montoLimpio) || bccomp($montoLimpio, '0.00', 2) < 0) {
            throw new InvalidArgumentException("El monto reclamado debe ser un valor decimal no negativo.");
        }

        $this->id = $id;
        $this->codigoHoja = $codigoHojaLimpio;
        $this->codigoInterno = $codigoInternoLimpio;
        $this->empresaId = $empresaId;
        $this->propiedadId = $propiedadId;
        $this->consumidorPersonaId = $consumidorPersonaId;
        $this->esMenorEdad = $esMenorEdad;
        $this->apoderadoPersonaId = $apoderadoPersonaId;
        $this->tipo = $tipo;
        $this->tipoBien = $tipoBien;
        $this->montoReclamado = number_format((float) $montoLimpio, 2, '.', '');
        $this->moneda = strtoupper(trim($moneda)) ?: 'PEN';
        $this->descripcionBien = $descLimpia;
        $this->detalleReclamacion = $detLimpio;
        $this->pedidoConsumidor = $pedLimpio;
        $this->canalEntrada = $canalEntrada;
        $this->estado = $estado;
        $this->fechaInterposicion = $fechaInterposicion ?? date('Y-m-d H:i:s');
        $this->fechaLimiteLegal = $fechaLimiteLegal ?? date('Y-m-d');
        $this->diasHabilesConsumidos = max(0, $diasHabilesConsumidos);
        $this->fechaSuspension = $fechaSuspension;
        $this->fechaLimiteOfrecimiento = $fechaLimiteOfrecimiento;
        $this->documentoEmitidoId = $documentoEmitidoId;
        $this->motivoAnulacion = $motivoAnulacion !== null ? trim($motivoAnulacion) : null;
        $this->anuladoPorActorId = $anuladoPorActorId;
        $this->anuladoEn = $anuladoEn;
        $this->snapshotConsumidor = $snapshotConsumidor;
        $this->snapshotProveedor = $snapshotProveedor;
        $this->creadoPorActorId = $creadoPorActorId;
        $this->actualizadoPorActorId = $actualizadoPorActorId;
        $this->creadoEn = $creadoEn;
        $this->actualizadoEn = $actualizadoEn;
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerCodigoHoja(): string
    {
        return $this->codigoHoja;
    }

    public function obtenerCodigoInterno(): string
    {
        return $this->codigoInterno;
    }

    public function obtenerEmpresaId(): int
    {
        return $this->empresaId;
    }

    public function obtenerPropiedadId(): int
    {
        return $this->propiedadId;
    }

    public function obtenerConsumidorPersonaId(): int
    {
        return $this->consumidorPersonaId;
    }

    public function esMenorEdad(): bool
    {
        return $this->esMenorEdad;
    }

    public function obtenerApoderadoPersonaId(): ?int
    {
        return $this->apoderadoPersonaId;
    }

    public function obtenerTipo(): string
    {
        return $this->tipo;
    }

    public function esReclamo(): bool
    {
        return $this->tipo === self::TIPO_RECLAMO;
    }

    public function esQueja(): bool
    {
        return $this->tipo === self::TIPO_QUEJA;
    }

    public function obtenerTipoBien(): string
    {
        return $this->tipoBien;
    }

    public function obtenerMontoReclamado(): string
    {
        return $this->montoReclamado;
    }

    public function obtenerMoneda(): string
    {
        return $this->moneda;
    }

    public function obtenerDescripcionBien(): string
    {
        return $this->descripcionBien;
    }

    public function obtenerDetalleReclamacion(): string
    {
        return $this->detalleReclamacion;
    }

    public function obtenerPedidoConsumidor(): string
    {
        return $this->pedidoConsumidor;
    }

    public function obtenerCanalEntrada(): string
    {
        return $this->canalEntrada;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function obtenerFechaInterposicion(): string
    {
        return $this->fechaInterposicion;
    }

    public function obtenerFechaLimiteLegal(): string
    {
        return $this->fechaLimiteLegal;
    }

    public function obtenerDiasHabilesConsumidos(): int
    {
        return $this->diasHabilesConsumidos;
    }

    public function obtenerFechaSuspension(): ?string
    {
        return $this->fechaSuspension;
    }

    public function obtenerFechaLimiteOfrecimiento(): ?string
    {
        return $this->fechaLimiteOfrecimiento;
    }

    public function obtenerDocumentoEmitidoId(): ?int
    {
        return $this->documentoEmitidoId;
    }

    public function obtenerMotivoAnulacion(): ?string
    {
        return $this->motivoAnulacion;
    }

    public function obtenerAnuladoPorActorId(): ?int
    {
        return $this->anuladoPorActorId;
    }

    public function obtenerAnuladoEn(): ?string
    {
        return $this->anuladoEn;
    }

    public function obtenerSnapshotConsumidor(): array
    {
        return $this->snapshotConsumidor;
    }

    public function obtenerSnapshotProveedor(): array
    {
        return $this->snapshotProveedor;
    }

    public function obtenerCreadoPorActorId(): ?int
    {
        return $this->creadoPorActorId;
    }

    public function obtenerActualizadoPorActorId(): ?int
    {
        return $this->actualizadoPorActorId;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function obtenerActualizadoEn(): ?string
    {
        return $this->actualizadoEn;
    }

    public function asignarDocumentoEmitidoId(?int $documentoEmitidoId): void
    {
        $this->documentoEmitidoId = $documentoEmitidoId;
    }

    public function suspenderPorOfrecimiento(string $fechaSuspension, string $fechaLimiteOfrecimiento, int $diasConsumidos): void
    {
        if ($this->estado === self::ESTADO_ANULADO || $this->estado === self::ESTADO_CONCLUIDO_POR_ACUERDO || $this->estado === self::ESTADO_ATENDIDO) {
            throw new InvalidArgumentException("No se puede suspender una reclamación que ya concluyó o fue anulada.");
        }
        $this->estado = self::ESTADO_SUSPENDIDO_OFRECIMIENTO;
        $this->fechaSuspension = $fechaSuspension;
        $this->fechaLimiteOfrecimiento = $fechaLimiteOfrecimiento;
        $this->diasHabilesConsumidos = $diasConsumidos;
    }

    public function reanudar(string $nuevaFechaLimite, int $diasConsumidos): void
    {
        if ($this->estado !== self::ESTADO_SUSPENDIDO_OFRECIMIENTO) {
            throw new InvalidArgumentException("Solo se puede reanudar una reclamación en estado suspendido por ofrecimiento.");
        }
        $this->estado = self::ESTADO_EN_PROCESO;
        $this->fechaSuspension = null;
        $this->fechaLimiteOfrecimiento = null;
        $this->fechaLimiteLegal = $nuevaFechaLimite;
        $this->diasHabilesConsumidos = $diasConsumidos;
    }

    public function concluirPorAcuerdo(): void
    {
        $this->estado = self::ESTADO_CONCLUIDO_POR_ACUERDO;
        $this->fechaSuspension = null;
        $this->fechaLimiteOfrecimiento = null;
    }

    public function atender(): void
    {
        if ($this->estado === self::ESTADO_ANULADO) {
            throw new InvalidArgumentException("No se puede atender una reclamación anulada.");
        }
        $this->estado = self::ESTADO_ATENDIDO;
        $this->fechaSuspension = null;
        $this->fechaLimiteOfrecimiento = null;
    }

    public function anular(string $motivo, int $actorId): void
    {
        $motivoLimpio = trim($motivo);
        if (mb_strlen($motivoLimpio) < 10) {
            throw new InvalidArgumentException("La anulación supervisada exige un motivo fundamentado de al menos 10 caracteres.");
        }
        $this->estado = self::ESTADO_ANULADO;
        $this->motivoAnulacion = $motivoLimpio;
        $this->anuladoPorActorId = $actorId;
        $this->anuladoEn = date('Y-m-d H:i:s');
    }

    public function asignarConsumidor(?Persona $persona): void
    {
        $this->consumidor = $persona;
    }

    public function obtenerConsumidor(): ?Persona
    {
        return $this->consumidor;
    }

    public function asignarApoderado(?Persona $persona): void
    {
        $this->apoderado = $persona;
    }

    public function obtenerApoderado(): ?Persona
    {
        return $this->apoderado;
    }

    public function asignarEmpresa(?array $empresa): void
    {
        $this->empresa = $empresa;
    }

    public function obtenerEmpresa(): ?array
    {
        return $this->empresa;
    }

    public function asignarPropiedad(?array $propiedad): void
    {
        $this->propiedad = $propiedad;
    }

    public function obtenerPropiedad(): ?array
    {
        return $this->propiedad;
    }

    /**
     * @param array<int, ReclamacionActuacion> $actuaciones
     */
    public function asignarActuaciones(array $actuaciones): void
    {
        $this->actuaciones = $actuaciones;
    }

    /**
     * @return array<int, ReclamacionActuacion>
     */
    public function obtenerActuaciones(): array
    {
        return $this->actuaciones;
    }

    /**
     * Serializa la entidad a un arreglo asociativo completo.
     *
     * @return array<string, mixed>
     */
    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'codigo_hoja' => $this->codigoHoja,
            'codigo_interno' => $this->codigoInterno,
            'empresa_id' => $this->empresaId,
            'propiedad_id' => $this->propiedadId,
            'consumidor_persona_id' => $this->consumidorPersonaId,
            'es_menor_edad' => $this->esMenorEdad,
            'apoderado_persona_id' => $this->apoderadoPersonaId,
            'tipo' => $this->tipo,
            'tipo_bien' => $this->tipoBien,
            'monto_reclamado' => $this->montoReclamado,
            'moneda' => $this->moneda,
            'descripcion_bien' => $this->descripcionBien,
            'detalle_reclamacion' => $this->detalleReclamacion,
            'pedido_consumidor' => $this->pedidoConsumidor,
            'canal_entrada' => $this->canalEntrada,
            'estado' => $this->estado,
            'fecha_interposicion' => $this->fechaInterposicion,
            'fecha_limite_legal' => $this->fechaLimiteLegal,
            'dias_habiles_consumidos' => $this->diasHabilesConsumidos,
            'fecha_suspension' => $this->fechaSuspension,
            'fecha_limite_ofrecimiento' => $this->fechaLimiteOfrecimiento,
            'documento_emitido_id' => $this->documentoEmitidoId,
            'motivo_anulacion' => $this->motivoAnulacion,
            'anulado_por_actor_id' => $this->anuladoPorActorId,
            'anulado_en' => $this->anuladoEn,
            'snapshot_consumidor' => $this->snapshotConsumidor,
            'snapshot_proveedor' => $this->snapshotProveedor,
            'creado_por_actor_id' => $this->creadoPorActorId,
            'actualizado_por_actor_id' => $this->actualizadoPorActorId,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
            'consumidor' => $this->consumidor?->aArreglo(),
            'apoderado' => $this->apoderado?->aArreglo(),
            'empresa' => $this->empresa,
            'propiedad' => $this->propiedad,
            'actuaciones' => array_map(static fn(ReclamacionActuacion $a) => $a->aArreglo(), $this->actuaciones),
        ];
    }

    /**
     * Reconstruye la entidad desde datos de base de datos.
     *
     * @param array<string, mixed> $datos
     * @return self
     */
    public static function desdeArreglo(array $datos): self
    {
        $snapConsumidor = [];
        if (!empty($datos['snapshot_consumidor_json'])) {
            $snapConsumidor = is_string($datos['snapshot_consumidor_json'])
                ? (json_decode($datos['snapshot_consumidor_json'], true) ?: [])
                : (array) $datos['snapshot_consumidor_json'];
        }

        $snapProveedor = [];
        if (!empty($datos['snapshot_proveedor_json'])) {
            $snapProveedor = is_string($datos['snapshot_proveedor_json'])
                ? (json_decode($datos['snapshot_proveedor_json'], true) ?: [])
                : (array) $datos['snapshot_proveedor_json'];
        }

        return new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (string) ($datos['codigo_hoja'] ?? ''),
            (string) ($datos['codigo_interno'] ?? ''),
            (int) ($datos['empresa_id'] ?? 0),
            (int) ($datos['propiedad_id'] ?? 0),
            (int) ($datos['consumidor_persona_id'] ?? 0),
            !empty($datos['es_menor_edad']),
            isset($datos['apoderado_persona_id']) && $datos['apoderado_persona_id'] !== null ? (int) $datos['apoderado_persona_id'] : null,
            (string) ($datos['tipo'] ?? self::TIPO_RECLAMO),
            (string) ($datos['tipo_bien'] ?? self::BIEN_SERVICIO),
            (string) ($datos['monto_reclamado'] ?? '0.00'),
            (string) ($datos['moneda'] ?? 'PEN'),
            (string) ($datos['descripcion_bien'] ?? ''),
            (string) ($datos['detalle_reclamacion'] ?? ''),
            (string) ($datos['pedido_consumidor'] ?? ''),
            (string) ($datos['canal_entrada'] ?? self::CANAL_VIRTUAL),
            (string) ($datos['estado'] ?? self::ESTADO_REGISTRADO),
            isset($datos['fecha_interposicion']) ? (string) $datos['fecha_interposicion'] : null,
            isset($datos['fecha_limite_legal']) ? (string) $datos['fecha_limite_legal'] : null,
            (int) ($datos['dias_habiles_consumidos'] ?? 0),
            isset($datos['fecha_suspension']) ? (string) $datos['fecha_suspension'] : null,
            isset($datos['fecha_limite_ofrecimiento']) ? (string) $datos['fecha_limite_ofrecimiento'] : null,
            isset($datos['documento_emitido_id']) && $datos['documento_emitido_id'] !== null ? (int) $datos['documento_emitido_id'] : null,
            isset($datos['motivo_anulacion']) ? (string) $datos['motivo_anulacion'] : null,
            isset($datos['anulado_por_actor_id']) && $datos['anulado_por_actor_id'] !== null ? (int) $datos['anulado_por_actor_id'] : null,
            isset($datos['anulado_en']) ? (string) $datos['anulado_en'] : null,
            $snapConsumidor,
            $snapProveedor,
            isset($datos['creado_por_actor_id']) && $datos['creado_por_actor_id'] !== null ? (int) $datos['creado_por_actor_id'] : null,
            isset($datos['actualizado_por_actor_id']) && $datos['actualizado_por_actor_id'] !== null ? (int) $datos['actualizado_por_actor_id'] : null,
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            isset($datos['actualizado_en']) ? (string) $datos['actualizado_en'] : null
        );
    }
}
