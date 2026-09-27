<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Modelo de dominio que representa la ocupación física real de una unidad hotelera (ESTADÍAS-1).
 *
 * Principios vinculantes:
 * - RESERVA != ESTADÍA != ARRENDAMIENTO.
 * - 1 Reserva : N Estadías físicas independientes (una por reserva_unidad).
 * - UNIQUE(reserva_unidad_id): una reserva_unidad genera como máximo una estadía histórica.
 * - Ciclo de vida operativo: EN_CURSO -> FINALIZADA | ANULADA.
 * - D-066: fechas hoteleras DATE (fecha_entrada, fecha_salida_prevista) vs instantes UTC (checkin_en, checkout_en, anulada_en).
 * - Capacidad física estricta: 1 <= count(huéspedes) <= unidad.capacidad_personas.
 * - Exactamente 1 huésped responsable por estadía.
 * - Inmutabilidad histórica: Cero DELETE físico; integridad referencial ON DELETE RESTRICT.
 */
class Estadia
{
    public const ESTADO_EN_CURSO = 'EN_CURSO';
    public const ESTADO_FINALIZADA = 'FINALIZADA';
    public const ESTADO_ANULADA = 'ANULADA';

    private ?int $id;
    private string $codigo;
    private int $reservaId;
    private int $reservaUnidadId;
    private int $unidadId;
    private string $fechaEntrada;
    private string $fechaSalidaPrevista;
    private string $estado;
    private string $checkinEn;
    private int $checkinPorActorId;
    private ?string $checkoutEn;
    private ?int $checkoutPorActorId;
    private ?string $identificadorLlave;
    private ?string $observacionesCheckin;
    private ?string $observacionesCheckout;
    private ?string $motivoAnulacion;
    private ?string $anuladaEn;
    private ?int $anuladaPorActorId;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    /**
     * @var array<EstadiaHuesped> Lista de huéspedes de la estadía
     */
    private array $huespedes = [];

    // Metadatos auxiliares de relaciones
    private ?string $reservaCodigo = null;
    private ?string $unidadNumero = null;
    private ?string $unidadNombre = null;
    private ?int $propiedadId = null;
    private ?string $propiedadNombre = null;
    private ?int $capacidadPersonas = null;
    private ?string $titularNombreCompleto = null;
    private ?string $checkinPorActorNombre = null;
    private ?string $checkoutPorActorNombre = null;
    private ?string $anuladaPorActorNombre = null;

    public function __construct(
        ?int $id,
        string $codigo,
        int $reservaId,
        int $reservaUnidadId,
        int $unidadId,
        string $fechaEntrada,
        string $fechaSalidaPrevista,
        string $estado,
        string $checkinEn,
        int $checkinPorActorId,
        ?string $checkoutEn = null,
        ?int $checkoutPorActorId = null,
        ?string $identificadorLlave = null,
        ?string $observacionesCheckin = null,
        ?string $observacionesCheckout = null,
        ?string $motivoAnulacion = null,
        ?string $anuladaEn = null,
        ?int $anuladaPorActorId = null,
        ?string $creadoEn = null,
        ?string $actualizadoEn = null
    ) {
        $this->id = $id;
        $this->codigo = trim($codigo);
        $this->reservaId = $reservaId;
        $this->reservaUnidadId = $reservaUnidadId;
        $this->unidadId = $unidadId;
        $this->fechaEntrada = trim($fechaEntrada);
        $this->fechaSalidaPrevista = trim($fechaSalidaPrevista);
        $this->estado = trim(strtoupper($estado));
        $this->checkinEn = trim($checkinEn);
        $this->checkinPorActorId = $checkinPorActorId;
        $this->checkoutEn = $checkoutEn !== null ? trim($checkoutEn) : null;
        $this->checkoutPorActorId = $checkoutPorActorId;
        $this->identificadorLlave = $identificadorLlave !== null && trim($identificadorLlave) !== '' ? trim($identificadorLlave) : null;
        $this->observacionesCheckin = $observacionesCheckin !== null && trim($observacionesCheckin) !== '' ? trim($observacionesCheckin) : null;
        $this->observacionesCheckout = $observacionesCheckout !== null && trim($observacionesCheckout) !== '' ? trim($observacionesCheckout) : null;
        $this->motivoAnulacion = $motivoAnulacion !== null && trim($motivoAnulacion) !== '' ? trim($motivoAnulacion) : null;
        $this->anuladaEn = $anuladaEn !== null ? trim($anuladaEn) : null;
        $this->anuladaPorActorId = $anuladaPorActorId;
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

    public function obtenerReservaUnidadId(): int
    {
        return $this->reservaUnidadId;
    }

    public function obtenerUnidadId(): int
    {
        return $this->unidadId;
    }

    public function obtenerFechaEntrada(): string
    {
        return $this->fechaEntrada;
    }

    public function obtenerFechaSalidaPrevista(): string
    {
        return $this->fechaSalidaPrevista;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function estaEnCurso(): bool
    {
        return $this->estado === self::ESTADO_EN_CURSO;
    }

    public function estaFinalizada(): bool
    {
        return $this->estado === self::ESTADO_FINALIZADA;
    }

    public function estaAnulada(): bool
    {
        return $this->estado === self::ESTADO_ANULADA;
    }

    public function obtenerCheckinEn(): string
    {
        return $this->checkinEn;
    }

    public function obtenerCheckinPorActorId(): int
    {
        return $this->checkinPorActorId;
    }

    public function obtenerCheckoutEn(): ?string
    {
        return $this->checkoutEn;
    }

    public function obtenerCheckoutPorActorId(): ?int
    {
        return $this->checkoutPorActorId;
    }

    public function obtenerIdentificadorLlave(): ?string
    {
        return $this->identificadorLlave;
    }

    public function obtenerObservacionesCheckin(): ?string
    {
        return $this->observacionesCheckin;
    }

    public function obtenerObservacionesCheckout(): ?string
    {
        return $this->observacionesCheckout;
    }

    public function obtenerMotivoAnulacion(): ?string
    {
        return $this->motivoAnulacion;
    }

    public function obtenerAnuladaEn(): ?string
    {
        return $this->anuladaEn;
    }

    public function obtenerAnuladaPorActorId(): ?int
    {
        return $this->anuladaPorActorId;
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
     * @return array<EstadiaHuesped>
     */
    public function obtenerHuespedes(): array
    {
        return $this->huespedes;
    }

    public function obtenerCantidadHuespedes(): int
    {
        return count($this->huespedes);
    }

    public function obtenerHuespedResponsable(): ?EstadiaHuesped
    {
        foreach ($this->huespedes as $huesped) {
            if ($huesped->esResponsable()) {
                return $huesped;
            }
        }

        return null;
    }

    /**
     * @param array<EstadiaHuesped> $huespedes
     */
    public function asignarHuespedes(array $huespedes): void
    {
        $this->huespedes = $huespedes;
    }

    public function agregarHuesped(EstadiaHuesped $huesped): void
    {
        $this->huespedes[] = $huesped;
    }

    // Metadatos auxiliares de presentación
    public function obtenerReservaCodigo(): ?string
    {
        return $this->reservaCodigo;
    }

    public function obtenerUnidadNumero(): ?string
    {
        return $this->unidadNumero;
    }

    public function obtenerUnidadNombre(): ?string
    {
        return $this->unidadNombre;
    }

    public function obtenerPropiedadId(): ?int
    {
        return $this->propiedadId;
    }

    public function obtenerPropiedadNombre(): ?string
    {
        return $this->propiedadNombre;
    }

    public function obtenerCapacidadPersonas(): ?int
    {
        return $this->capacidadPersonas;
    }

    public function obtenerTitularNombreCompleto(): ?string
    {
        return $this->titularNombreCompleto;
    }

    public function obtenerCheckinPorActorNombre(): ?string
    {
        return $this->checkinPorActorNombre;
    }

    public function obtenerCheckoutPorActorNombre(): ?string
    {
        return $this->checkoutPorActorNombre;
    }

    public function obtenerAnuladaPorActorNombre(): ?string
    {
        return $this->anuladaPorActorNombre;
    }

    public function asignarMetadatosRelaciones(
        ?string $reservaCodigo,
        ?string $unidadNumero = null,
        ?string $unidadNombre = null,
        ?int $propiedadId = null,
        ?string $propiedadNombre = null,
        ?int $capacidadPersonas = null,
        ?string $titularNombreCompleto = null,
        ?string $checkinPorActorNombre = null,
        ?string $checkoutPorActorNombre = null,
        ?string $anuladaPorActorNombre = null
    ): void {
        $this->reservaCodigo = $reservaCodigo;
        $this->unidadNumero = $unidadNumero;
        $this->unidadNombre = $unidadNombre;
        $this->propiedadId = $propiedadId;
        $this->propiedadNombre = $propiedadNombre;
        $this->capacidadPersonas = $capacidadPersonas;
        $this->titularNombreCompleto = $titularNombreCompleto;
        $this->checkinPorActorNombre = $checkinPorActorNombre;
        $this->checkoutPorActorNombre = $checkoutPorActorNombre;
        $this->anuladaPorActorNombre = $anuladaPorActorNombre;
    }

    /**
     * @param array<string, mixed> $datos
     */
    public static function desdeArreglo(array $datos): self
    {
        $estadia = new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (string) ($datos['codigo'] ?? ''),
            (int) ($datos['reserva_id'] ?? 0),
            (int) ($datos['reserva_unidad_id'] ?? 0),
            (int) ($datos['unidad_id'] ?? 0),
            (string) ($datos['fecha_entrada'] ?? ''),
            (string) ($datos['fecha_salida_prevista'] ?? ''),
            (string) ($datos['estado'] ?? self::ESTADO_EN_CURSO),
            (string) ($datos['checkin_en'] ?? ''),
            (int) ($datos['checkin_por_actor_id'] ?? 0),
            isset($datos['checkout_en']) && $datos['checkout_en'] !== null ? (string) $datos['checkout_en'] : null,
            isset($datos['checkout_por_actor_id']) && $datos['checkout_por_actor_id'] !== null ? (int) $datos['checkout_por_actor_id'] : null,
            isset($datos['identificador_llave']) ? (string) $datos['identificador_llave'] : null,
            isset($datos['observaciones_checkin']) ? (string) $datos['observaciones_checkin'] : null,
            isset($datos['observaciones_checkout']) ? (string) $datos['observaciones_checkout'] : null,
            isset($datos['motivo_anulacion']) ? (string) $datos['motivo_anulacion'] : null,
            isset($datos['anulada_en']) && $datos['anulada_en'] !== null ? (string) $datos['anulada_en'] : null,
            isset($datos['anulada_por_actor_id']) && $datos['anulada_por_actor_id'] !== null ? (int) $datos['anulada_por_actor_id'] : null,
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            isset($datos['actualizado_en']) ? (string) $datos['actualizado_en'] : null
        );

        $estadia->asignarMetadatosRelaciones(
            isset($datos['reserva_codigo']) ? (string) $datos['reserva_codigo'] : null,
            isset($datos['unidad_numero']) ? (string) $datos['unidad_numero'] : null,
            isset($datos['unidad_nombre']) ? (string) $datos['unidad_nombre'] : null,
            isset($datos['propiedad_id']) ? (int) $datos['propiedad_id'] : null,
            isset($datos['propiedad_nombre']) ? (string) $datos['propiedad_nombre'] : null,
            isset($datos['capacidad_personas']) ? (int) $datos['capacidad_personas'] : null,
            isset($datos['titular_nombre_completo']) ? (string) $datos['titular_nombre_completo'] : null,
            isset($datos['checkin_por_actor_nombre']) ? (string) $datos['checkin_por_actor_nombre'] : null,
            isset($datos['checkout_por_actor_nombre']) ? (string) $datos['checkout_por_actor_nombre'] : null,
            isset($datos['anulada_por_actor_nombre']) ? (string) $datos['anulada_por_actor_nombre'] : null
        );

        if (isset($datos['huespedes']) && is_array($datos['huespedes'])) {
            $huespedes = [];
            foreach ($datos['huespedes'] as $h) {
                if ($h instanceof EstadiaHuesped) {
                    $huespedes[] = $h;
                } elseif (is_array($h)) {
                    $huespedes[] = EstadiaHuesped::desdeArreglo($h);
                }
            }
            $estadia->asignarHuespedes($huespedes);
        }

        return $estadia;
    }

    /**
     * @return array<string, mixed>
     */
    public function haciaArreglo(): array
    {
        $huespedesArreglo = [];
        foreach ($this->huespedes as $h) {
            $huespedesArreglo[] = $h->haciaArreglo();
        }

        $responsable = $this->obtenerHuespedResponsable();

        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'reserva_id' => $this->reservaId,
            'reserva_codigo' => $this->reservaCodigo,
            'reserva_unidad_id' => $this->reservaUnidadId,
            'unidad_id' => $this->unidadId,
            'unidad_numero' => $this->unidadNumero,
            'unidad_nombre' => $this->unidadNombre,
            'propiedad_id' => $this->propiedadId,
            'propiedad_nombre' => $this->propiedadNombre,
            'capacidad_personas' => $this->capacidadPersonas,
            'titular_nombre_completo' => $this->titularNombreCompleto,
            'fecha_entrada' => $this->fechaEntrada,
            'fecha_salida_prevista' => $this->fechaSalidaPrevista,
            'estado' => $this->estado,
            'esta_en_curso' => $this->estaEnCurso(),
            'esta_finalizada' => $this->estaFinalizada(),
            'esta_anulada' => $this->estaAnulada(),
            'checkin_en' => $this->checkinEn,
            'checkin_por_actor_id' => $this->checkinPorActorId,
            'checkin_por_actor_nombre' => $this->checkinPorActorNombre,
            'checkout_en' => $this->checkoutEn,
            'checkout_por_actor_id' => $this->checkoutPorActorId,
            'checkout_por_actor_nombre' => $this->checkoutPorActorNombre,
            'identificador_llave' => $this->identificadorLlave,
            'observaciones_checkin' => $this->observacionesCheckin,
            'observaciones_checkout' => $this->observacionesCheckout,
            'motivo_anulacion' => $this->motivoAnulacion,
            'anulada_en' => $this->anuladaEn,
            'anulada_por_actor_id' => $this->anuladaPorActorId,
            'anulada_por_actor_nombre' => $this->anuladaPorActorNombre,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
            'cantidad_huespedes' => count($this->huespedes),
            'responsable_nombre' => $responsable?->obtenerNombreCompleto(),
            'huespedes' => $huespedesArreglo,
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
