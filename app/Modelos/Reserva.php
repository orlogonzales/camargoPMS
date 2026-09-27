<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Modelo de dominio que representa una Reserva Comercial Directa en Camargo PMS (RESERVAS-1).
 *
 * Principios vinculantes:
 * - RESERVA != DISPONIBILIDAD != INVENTARIO != ESTANCIA != PAGO != CONTRATO != PERSONA.
 * - Intervalo semiabierto [fecha_entrada, fecha_salida) (D-066). La noche de salida no se pernocta.
 * - Ciclo de vida: PENDIENTE -> CONFIRMADA | CANCELADA | EXPIRADA.
 * - Inmutabilidad histórica: Cero DELETE físico; estados terminales preservan el registro.
 * - Snapshot económico D-069: PEN, DECIMAL(15,2), redondeo ROUND_HALF_UP.
 * - Multiunidad: 1 Reserva : N Unidades a través de ReservaUnidad.
 */
class Reserva
{
    public const ESTADO_PENDIENTE = 'PENDIENTE';
    public const ESTADO_CONFIRMADA = 'CONFIRMADA';
    public const ESTADO_CANCELADA = 'CANCELADA';
    public const ESTADO_EXPIRADA = 'EXPIRADA';

    public const CANAL_PMS = 'PMS';
    public const CANAL_WEB = 'WEB';
    public const CANAL_APP = 'APP';
    public const CANAL_OTA = 'OTA';

    private ?int $id;
    private string $codigo;
    private int $personaTitularId;
    private string $fechaEntrada;
    private string $fechaSalida;
    private int $noches;
    private string $estado;
    private ?string $expiraEn;
    private string $canal;
    private string $origen;
    private string $monedaCodigo;
    private string $subtotal;
    private string $impuestoTotal;
    private string $total;
    private ?string $observaciones;
    private ?string $motivoCancelacion;
    private ?string $canceladaEn;
    private ?int $canceladaPorActorId;
    private ?string $confirmadaEn;
    private ?int $confirmadaPorActorId;
    private ?int $creadoPorActorId;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    /**
     * @var array<ReservaUnidad> Detalle de unidades asignadas
     */
    private array $unidades = [];

    // Metadatos auxiliares de relaciones
    private ?string $titularNombreCompleto = null;
    private ?string $titularDocumentoNumero = null;
    private ?string $titularDocumentoTipo = null;
    private ?string $titularTelefono = null;
    private ?string $titularEmail = null;
    private ?string $creadorNombre = null;
    private ?string $confirmadorNombre = null;
    private ?string $canceladorNombre = null;

    public function __construct(
        ?int $id,
        string $codigo,
        int $personaTitularId,
        string $fechaEntrada,
        string $fechaSalida,
        int $noches,
        string $estado = self::ESTADO_PENDIENTE,
        ?string $expiraEn = null,
        string $canal = self::CANAL_PMS,
        string $origen = 'DIRECTO',
        string $monedaCodigo = 'PEN',
        string $subtotal = '0.00',
        string $impuestoTotal = '0.00',
        string $total = '0.00',
        ?string $observaciones = null,
        ?string $motivoCancelacion = null,
        ?string $canceladaEn = null,
        ?int $canceladaPorActorId = null,
        ?string $confirmadaEn = null,
        ?int $confirmadaPorActorId = null,
        ?int $creadoPorActorId = null,
        ?string $creadoEn = null,
        ?string $actualizadoEn = null
    ) {
        $this->id = $id;
        $this->codigo = trim(strtoupper($codigo));
        $this->personaTitularId = $personaTitularId;
        $this->fechaEntrada = trim($fechaEntrada);
        $this->fechaSalida = trim($fechaSalida);
        $this->noches = $noches;
        $this->estado = in_array(strtoupper(trim($estado)), [
            self::ESTADO_PENDIENTE,
            self::ESTADO_CONFIRMADA,
            self::ESTADO_CANCELADA,
            self::ESTADO_EXPIRADA,
        ], true) ? strtoupper(trim($estado)) : self::ESTADO_PENDIENTE;
        $this->expiraEn = $expiraEn !== null && trim($expiraEn) !== '' ? trim($expiraEn) : null;
        $this->canal = in_array(strtoupper(trim($canal)), [
            self::CANAL_PMS,
            self::CANAL_WEB,
            self::CANAL_APP,
            self::CANAL_OTA,
        ], true) ? strtoupper(trim($canal)) : self::CANAL_PMS;
        $this->origen = trim($origen) !== '' ? trim($origen) : 'DIRECTO';
        $this->monedaCodigo = trim(strtoupper($monedaCodigo));
        $this->subtotal = number_format((float) $subtotal, 2, '.', '');
        $this->impuestoTotal = number_format((float) $impuestoTotal, 2, '.', '');
        $this->total = number_format((float) $total, 2, '.', '');
        $this->observaciones = $observaciones !== null && trim($observaciones) !== '' ? trim($observaciones) : null;
        $this->motivoCancelacion = $motivoCancelacion !== null && trim($motivoCancelacion) !== '' ? trim($motivoCancelacion) : null;
        $this->canceladaEn = $canceladaEn;
        $this->canceladaPorActorId = $canceladaPorActorId;
        $this->confirmadaEn = $confirmadaEn;
        $this->confirmadaPorActorId = $confirmadaPorActorId;
        $this->creadoPorActorId = $creadoPorActorId;
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

    public function obtenerPersonaTitularId(): int
    {
        return $this->personaTitularId;
    }

    public function obtenerFechaEntrada(): string
    {
        return $this->fechaEntrada;
    }

    public function obtenerFechaSalida(): string
    {
        return $this->fechaSalida;
    }

    public function obtenerNoches(): int
    {
        return $this->noches;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function obtenerExpiraEn(): ?string
    {
        return $this->expiraEn;
    }

    public function obtenerCanal(): string
    {
        return $this->canal;
    }

    public function obtenerOrigen(): string
    {
        return $this->origen;
    }

    public function obtenerMonedaCodigo(): string
    {
        return $this->monedaCodigo;
    }

    public function obtenerSubtotal(): string
    {
        return $this->subtotal;
    }

    public function obtenerImpuestoTotal(): string
    {
        return $this->impuestoTotal;
    }

    public function obtenerTotal(): string
    {
        return $this->total;
    }

    public function obtenerObservaciones(): ?string
    {
        return $this->observaciones;
    }

    public function obtenerMotivoCancelacion(): ?string
    {
        return $this->motivoCancelacion;
    }

    public function obtenerCanceladaEn(): ?string
    {
        return $this->canceladaEn;
    }

    public function obtenerCanceladaPorActorId(): ?int
    {
        return $this->canceladaPorActorId;
    }

    public function obtenerConfirmadaEn(): ?string
    {
        return $this->confirmadaEn;
    }

    public function obtenerConfirmadaPorActorId(): ?int
    {
        return $this->confirmadaPorActorId;
    }

    public function obtenerCreadoPorActorId(): ?int
    {
        return $this->creadoPorActorId;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function obtenerActualizadoEn(): ?string
    {
        return $this->actualizadoEn;
    }

    /**
     * @return array<ReservaUnidad>
     */
    public function obtenerUnidades(): array
    {
        return $this->unidades;
    }

    /**
     * @param array<ReservaUnidad> $unidades
     */
    public function asignarUnidades(array $unidades): void
    {
        $this->unidades = $unidades;
    }

    public function agregarUnidad(ReservaUnidad $unidad): void
    {
        $this->unidades[] = $unidad;
    }

    public function esPendiente(): bool
    {
        return $this->estado === self::ESTADO_PENDIENTE;
    }

    public function esConfirmada(): bool
    {
        return $this->estado === self::ESTADO_CONFIRMADA;
    }

    public function esCancelada(): bool
    {
        return $this->estado === self::ESTADO_CANCELADA;
    }

    public function esExpirada(): bool
    {
        return $this->estado === self::ESTADO_EXPIRADA;
    }

    /**
     * Indica si la reserva retiene inventario actualmente (PENDIENTE con hold o CONFIRMADA).
     */
    public function retieneInventario(): bool
    {
        return $this->estado === self::ESTADO_PENDIENTE || $this->estado === self::ESTADO_CONFIRMADA;
    }

    /**
     * Verifica si una reserva PENDIENTE ya superó su instante técnico de hold.
     */
    public function haExpirado(?string $ahora = null): bool
    {
        if (!$this->esPendiente() || $this->expiraEn === null) {
            return false;
        }

        $referencia = $ahora ?? date('Y-m-d H:i:s');
        return $this->expiraEn <= $referencia;
    }

    public function obtenerTitularNombreCompleto(): ?string
    {
        return $this->titularNombreCompleto;
    }

    public function obtenerTitularDocumentoNumero(): ?string
    {
        return $this->titularDocumentoNumero;
    }

    public function obtenerTitularDocumentoTipo(): ?string
    {
        return $this->titularDocumentoTipo;
    }

    public function obtenerTitularTelefono(): ?string
    {
        return $this->titularTelefono;
    }

    public function obtenerTitularEmail(): ?string
    {
        return $this->titularEmail;
    }

    public function obtenerCreadorNombre(): ?string
    {
        return $this->creadorNombre;
    }

    public function obtenerConfirmadorNombre(): ?string
    {
        return $this->confirmadorNombre;
    }

    public function obtenerCanceladorNombre(): ?string
    {
        return $this->canceladorNombre;
    }

    public function asignarMetadatosTitular(
        ?string $nombreCompleto,
        ?string $documentoNumero = null,
        ?string $documentoTipo = null,
        ?string $telefono = null,
        ?string $email = null
    ): void {
        $this->titularNombreCompleto = $nombreCompleto;
        $this->titularDocumentoNumero = $documentoNumero;
        $this->titularDocumentoTipo = $documentoTipo;
        $this->titularTelefono = $telefono;
        $this->titularEmail = $email;
    }

    public function asignarNombresActores(
        ?string $creador = null,
        ?string $confirmador = null,
        ?string $cancelador = null
    ): void {
        $this->creadorNombre = $creador;
        $this->confirmadorNombre = $confirmador;
        $this->canceladorNombre = $cancelador;
    }

    /**
     * @param array<string, mixed> $datos
     */
    public static function desdeArreglo(array $datos): self
    {
        $reserva = new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (string) ($datos['codigo'] ?? ''),
            (int) ($datos['persona_titular_id'] ?? 0),
            (string) ($datos['fecha_entrada'] ?? ''),
            (string) ($datos['fecha_salida'] ?? ''),
            (int) ($datos['noches'] ?? 0),
            (string) ($datos['estado'] ?? self::ESTADO_PENDIENTE),
            isset($datos['expira_en']) ? (string) $datos['expira_en'] : null,
            (string) ($datos['canal'] ?? self::CANAL_PMS),
            (string) ($datos['origen'] ?? 'DIRECTO'),
            (string) ($datos['moneda_codigo'] ?? 'PEN'),
            (string) ($datos['subtotal'] ?? '0.00'),
            (string) ($datos['impuesto_total'] ?? '0.00'),
            (string) ($datos['total'] ?? '0.00'),
            isset($datos['observaciones']) ? (string) $datos['observaciones'] : null,
            isset($datos['motivo_cancelacion']) ? (string) $datos['motivo_cancelacion'] : null,
            isset($datos['cancelada_en']) ? (string) $datos['cancelada_en'] : null,
            isset($datos['cancelada_por_actor_id']) ? (int) $datos['cancelada_por_actor_id'] : null,
            isset($datos['confirmada_en']) ? (string) $datos['confirmada_en'] : null,
            isset($datos['confirmada_por_actor_id']) ? (int) $datos['confirmada_por_actor_id'] : null,
            isset($datos['creado_por_actor_id']) ? (int) $datos['creado_por_actor_id'] : null,
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            isset($datos['actualizado_en']) ? (string) $datos['actualizado_en'] : null
        );

        if (isset($datos['titular_nombre_completo']) || isset($datos['titular_nombres'])) {
            $nombre = $datos['titular_nombre_completo']
                ?? trim(($datos['titular_nombres'] ?? '') . ' ' . ($datos['titular_apellido_paterno'] ?? '') . ' ' . ($datos['titular_apellido_materno'] ?? ''));

            $reserva->asignarMetadatosTitular(
                $nombre !== '' ? $nombre : null,
                isset($datos['titular_documento_numero']) ? (string) $datos['titular_documento_numero'] : null,
                isset($datos['titular_documento_tipo']) ? (string) $datos['titular_documento_tipo'] : null,
                isset($datos['titular_telefono']) ? (string) $datos['titular_telefono'] : null,
                isset($datos['titular_email']) ? (string) $datos['titular_email'] : null
            );
        }

        if (isset($datos['creador_nombre']) || isset($datos['confirmador_nombre']) || isset($datos['cancelador_nombre'])) {
            $reserva->asignarNombresActores(
                isset($datos['creador_nombre']) ? (string) $datos['creador_nombre'] : null,
                isset($datos['confirmador_nombre']) ? (string) $datos['confirmador_nombre'] : null,
                isset($datos['cancelador_nombre']) ? (string) $datos['cancelador_nombre'] : null
            );
        }

        return $reserva;
    }

    /**
     * @return array<string, mixed>
     */
    public function haciaArreglo(): array
    {
        $unidadesArreglo = [];
        foreach ($this->unidades as $u) {
            $unidadesArreglo[] = $u->haciaArreglo();
        }

        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'persona_titular_id' => $this->personaTitularId,
            'titular_nombre_completo' => $this->titularNombreCompleto,
            'titular_documento_numero' => $this->titularDocumentoNumero,
            'titular_documento_tipo' => $this->titularDocumentoTipo,
            'titular_telefono' => $this->titularTelefono,
            'titular_email' => $this->titularEmail,
            'fecha_entrada' => $this->fechaEntrada,
            'fecha_salida' => $this->fechaSalida,
            'noches' => $this->noches,
            'estado' => $this->estado,
            'expira_en' => $this->expiraEn,
            'ha_expirado' => $this->haExpirado(),
            'retiene_inventario' => $this->retieneInventario(),
            'canal' => $this->canal,
            'origen' => $this->origen,
            'moneda_codigo' => $this->monedaCodigo,
            'subtotal' => $this->subtotal,
            'impuesto_total' => $this->impuestoTotal,
            'total' => $this->total,
            'observaciones' => $this->observaciones,
            'motivo_cancelacion' => $this->motivoCancelacion,
            'cancelada_en' => $this->canceladaEn,
            'cancelada_por_actor_id' => $this->canceladaPorActorId,
            'cancelador_nombre' => $this->canceladorNombre,
            'confirmada_en' => $this->confirmadaEn,
            'confirmada_por_actor_id' => $this->confirmadaPorActorId,
            'confirmador_nombre' => $this->confirmadorNombre,
            'creado_por_actor_id' => $this->creadoPorActorId,
            'creador_nombre' => $this->creadorNombre,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
            'unidades' => $unidadesArreglo,
            'cantidad_unidades' => count($this->unidades),
        ];
    }

    /**
     * Alias de haciaArreglo().
     *
     * @return array<string, mixed>
     */
    public function aArreglo(): array
    {
        return $this->haciaArreglo();
    }
}
