<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

use CamargoPMS\Excepciones\CapacidadExcedidaExcepcion;
use CamargoPMS\Excepciones\ConflictoEstadiaExcepcion;
use CamargoPMS\Excepciones\EstadiaNoEncontradaExcepcion;
use CamargoPMS\Excepciones\EstadoEstadiaInvalidoExcepcion;
use CamargoPMS\Excepciones\EstadoReservaInvalidoExcepcion;
use CamargoPMS\Excepciones\HuespedInvalidoExcepcion;
use CamargoPMS\Excepciones\IntervaloInvalidoExcepcion;
use CamargoPMS\Excepciones\ReservaNoEncontradaExcepcion;
use CamargoPMS\Excepciones\UnidadNoEncontradaExcepcion;
use CamargoPMS\Excepciones\ValidacionExcepcion;
use CamargoPMS\Modelos\Estadia;
use CamargoPMS\Modelos\EstadiaHuesped;
use CamargoPMS\Modelos\Reserva;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Repositorios\EstadiaRepositorio;
use CamargoPMS\Repositorios\HousekeepingRepositorio;
use CamargoPMS\Repositorios\PersonaRepositorio;
use CamargoPMS\Repositorios\ReservaRepositorio;
use CamargoPMS\Repositorios\UnidadRepositorio;
use PDO;
use PDOException;
use Throwable;

/**
 * Servicio transaccional central para la gestión operativa de estadías y huéspedes (ESTADÍAS-1).
 *
 * Principios vinculantes:
 * - RESERVA != ESTADÍA != ARRENDAMIENTO.
 * - Multiunidad: 1 Reserva : N Estadías físicas independientes (una por reserva_unidad).
 * - UNIQUE(reserva_unidad_id): una reserva_unidad genera como máximo una estadía histórica.
 * - Walk-in diferido: Solo check-in de reserva_unidad existente de reserva CONFIRMADA.
 * - Capacidad física estricta: 1 <= count(huéspedes) <= unidad.capacidad_personas.
 * - Exactamente 1 huésped responsable por estadía.
 * - Inmutabilidad histórica: Cero DELETE sobre estadías y huéspedes; ON DELETE RESTRICT.
 * - D-066: fechas hoteleras DATE vs instantes UTC (gmdate).
 * - D-061: trazabilidad de actores en eventos de dominio.
 */
class EstadiaServicio
{
    private PDO $pdo;
    private EstadiaRepositorio $estadiaRepo;
    private ReservaRepositorio $reservaRepo;
    private UnidadRepositorio $unidadRepo;
    private PersonaRepositorio $personaRepo;
    private AuditoriaServicio $auditoriaServicio;
    private HousekeepingServicio $housekeepingServicio;

    public function __construct(
        ?PDO $pdo = null,
        ?EstadiaRepositorio $estadiaRepo = null,
        ?ReservaRepositorio $reservaRepo = null,
        ?UnidadRepositorio $unidadRepo = null,
        ?PersonaRepositorio $personaRepo = null,
        ?AuditoriaServicio $auditoriaServicio = null,
        ?HousekeepingServicio $housekeepingServicio = null
    ) {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->estadiaRepo = $estadiaRepo ?? new EstadiaRepositorio($this->pdo);
        $this->reservaRepo = $reservaRepo ?? new ReservaRepositorio($this->pdo);
        $this->unidadRepo = $unidadRepo ?? new UnidadRepositorio($this->pdo);
        $this->personaRepo = $personaRepo ?? new PersonaRepositorio($this->pdo);
        $this->auditoriaServicio = $auditoriaServicio ?? new AuditoriaServicio($this->pdo);
        $this->housekeepingServicio = $housekeepingServicio ?? new HousekeepingServicio(
            new HousekeepingRepositorio($this->pdo),
            $this->auditoriaServicio,
            null,
            null,
            $this->pdo
        );
    }

    public function obtenerPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Realiza el check-in físico de una unidad de reserva confirmada.
     *
     * @param array<string, mixed> $datos
     */
    public function realizarCheckin(array $datos, int $actorId): Estadia
    {
        // 1. Validar parámetros requeridos
        $reservaId = (int) ($datos['reserva_id'] ?? 0);
        $reservaUnidadId = (int) ($datos['reserva_unidad_id'] ?? 0);

        if ($reservaId <= 0) {
            throw new ValidacionExcepcion('El identificador de reserva es obligatorio.');
        }

        if ($reservaUnidadId <= 0) {
            throw new ValidacionExcepcion('El identificador de la unidad de reserva es obligatorio.');
        }

        // 2. Localizar y validar estado de la reserva
        $reserva = $this->reservaRepo->buscarPorId($reservaId, true);
        if (!$reserva) {
            throw new ReservaNoEncontradaExcepcion($reservaId);
        }

        if ($reserva->obtenerEstado() !== Reserva::ESTADO_CONFIRMADA) {
            throw new EstadoReservaInvalidoExcepcion(
                $reserva->obtenerEstado(),
                'check-in',
                "Solo es posible realizar check-in sobre una reserva en estado 'CONFIRMADA'. El estado actual es '{$reserva->obtenerEstado()}'."
            );
        }

        // 3. Validar pertenencia de la reserva_unidad a la reserva
        $unidadAsignada = null;
        foreach ($reserva->obtenerUnidades() as $ru) {
            if ($ru->obtenerId() === $reservaUnidadId) {
                $unidadAsignada = $ru;
                break;
            }
        }

        if (!$unidadAsignada) {
            throw new ValidacionExcepcion("La unidad de reserva ID {$reservaUnidadId} no pertenece a la reserva ID {$reservaId}.");
        }

        $unidadId = $unidadAsignada->obtenerUnidadId();
        if (isset($datos['unidad_id']) && (int) $datos['unidad_id'] !== $unidadId) {
            throw new ValidacionExcepcion("La unidad enviada (ID {$datos['unidad_id']}) no coincide con la unidad de la reserva (ID {$unidadId}).");
        }

        // 4. Verificar que no exista check-in previo (UNIQUE reserva_unidad_id)
        $existente = $this->estadiaRepo->buscarPorReservaUnidadId($reservaUnidadId);
        if ($existente !== null) {
            throw new ConflictoEstadiaExcepcion(
                $reservaUnidadId,
                "La unidad de reserva ID {$reservaUnidadId} ya cuenta con una estadía registrada ({$existente->obtenerCodigo()})."
            );
        }

        // 5. Validar capacidad máxima de la unidad
        $unidad = $this->unidadRepo->buscarPorId($unidadId);
        if (!$unidad) {
            throw new UnidadNoEncontradaExcepcion($unidadId);
        }
        $capacidadMaxima = $unidad->obtenerCapacidadPersonas();

        // 5b. Validar condición operacional para check-in (VR - Vacant Ready / D-083)
        $this->housekeepingServicio->validarAptaParaCheckin($unidadId);

        // 6. Validar lista de huéspedes
        $huespedesRaw = $datos['huespedes'] ?? [];
        if (!is_array($huespedesRaw)) {
            throw new HuespedInvalidoExcepcion('La lista de huéspedes debe ser un arreglo.');
        }

        $huespedesValidados = $this->validarHuespedes($huespedesRaw, $capacidadMaxima, $unidadId);

        // 7. Fechas hoteleras locales (D-066)
        $fechaEntrada = !empty($datos['fecha_entrada']) ? trim((string) $datos['fecha_entrada']) : $reserva->obtenerFechaEntrada();
        $fechaSalidaPrevista = !empty($datos['fecha_salida_prevista']) ? trim((string) $datos['fecha_salida_prevista']) : $reserva->obtenerFechaSalida();

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaEntrada)) {
            throw new IntervaloInvalidoExcepcion($fechaEntrada, $fechaSalidaPrevista, "La fecha de entrada '{$fechaEntrada}' no tiene un formato válido (YYYY-MM-DD).");
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaSalidaPrevista)) {
            throw new IntervaloInvalidoExcepcion($fechaEntrada, $fechaSalidaPrevista, "La fecha de salida prevista '{$fechaSalidaPrevista}' no tiene un formato válido (YYYY-MM-DD).");
        }
        if ($fechaSalidaPrevista <= $fechaEntrada) {
            throw new IntervaloInvalidoExcepcion(
                $fechaEntrada,
                $fechaSalidaPrevista,
                "La fecha de salida prevista ({$fechaSalidaPrevista}) debe ser posterior a la fecha de entrada ({$fechaEntrada})."
            );
        }

        // 8. Datos complementarios
        $identificadorLlave = isset($datos['identificador_llave']) && trim((string) $datos['identificador_llave']) !== ''
            ? trim((string) $datos['identificador_llave'])
            : null;

        $observacionesCheckin = isset($datos['observaciones_checkin']) && trim((string) $datos['observaciones_checkin']) !== ''
            ? trim((string) $datos['observaciones_checkin'])
            : null;

        // Instante técnico UTC real (D-066)
        $checkinEn = gmdate('Y-m-d H:i:s');
        $actorIdFinal = $this->resolverActorId($actorId);

        // 9. Transacción ACID estricta
        $this->pdo->beginTransaction();
        try {
            $codigo = $this->estadiaRepo->generarSiguienteCodigo();

            $nuevaEstadia = new Estadia(
                null,
                $codigo,
                $reservaId,
                $reservaUnidadId,
                $unidadId,
                $fechaEntrada,
                $fechaSalidaPrevista,
                Estadia::ESTADO_EN_CURSO,
                $checkinEn,
                $actorIdFinal,
                null,
                null,
                $identificadorLlave,
                $observacionesCheckin
            );

            $estadiaId = $this->estadiaRepo->guardar($nuevaEstadia);

            // Guardar huéspedes vinculados a la estadía
            $modelosHuespedes = [];
            foreach ($huespedesValidados as $h) {
                $modelosHuespedes[] = new EstadiaHuesped(
                    null,
                    $estadiaId,
                    $h['persona_id'],
                    $h['es_responsable']
                );
            }
            $this->estadiaRepo->guardarHuespedes($estadiaId, $modelosHuespedes);

            // Auditoría transversal D-061
            $this->auditoriaServicio->registrar(
                accion: 'ESTADIA_CHECKIN',
                modulo: 'estadias',
                entidad: 'estadias',
                entidadId: (string) $estadiaId,
                descripcion: "Check-in realizado para estadía '{$codigo}' en unidad {$unidad->obtenerCodigo()} ({$unidad->obtenerNombre()}). Reserva: {$reserva->obtenerCodigo()}. Huéspedes: " . count($modelosHuespedes),
                valoresAnteriores: null,
                valoresNuevos: [
                    'codigo' => $codigo,
                    'reserva_id' => $reservaId,
                    'reserva_unidad_id' => $reservaUnidadId,
                    'unidad_id' => $unidadId,
                    'fecha_entrada' => $fechaEntrada,
                    'fecha_salida_prevista' => $fechaSalidaPrevista,
                    'checkin_en' => $checkinEn,
                    'checkin_por_actor_id' => $actorIdFinal,
                    'identificador_llave' => $identificadorLlave,
                    'cantidad_huespedes' => count($modelosHuespedes),
                ],
                contexto: null,
                actor: $actorIdFinal,
                usuarioId: null,
                correlacionId: null,
                pdoTransaccional: $this->pdo
            );

            $this->pdo->commit();

            return $this->estadiaRepo->buscarPorId($estadiaId, true);
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            // Detección de colisión por unicidad de reserva_unidad_id (código 1062)
            if ($e->errorInfo[1] === 1062 && str_contains($e->getMessage(), 'uq_estadias_reserva_unidad')) {
                throw new ConflictoEstadiaExcepcion(
                    $reservaUnidadId,
                    "La unidad de reserva ID {$reservaUnidadId} ya cuenta con una estadía registrada.",
                    409,
                    $e
                );
            }

            throw $e;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Realiza el check-out operativo de una estadía en curso.
     * Check-out anticipado o tardío: registra el instante real sin recalcular noches comerciales (D-066).
     */
    public function realizarCheckout(int $estadiaId, ?string $observaciones, int $actorId): Estadia
    {
        $actorIdFinal = $this->resolverActorId($actorId);

        $this->pdo->beginTransaction();
        try {
            $estadia = $this->estadiaRepo->buscarPorIdParaActualizar($estadiaId);
            if (!$estadia) {
                throw new EstadiaNoEncontradaExcepcion($estadiaId);
            }

            if (!$estadia->estaEnCurso()) {
                throw new EstadoEstadiaInvalidoExcepcion(
                    $estadia->obtenerEstado(),
                    'check-out',
                    "No es posible realizar check-out: la estadía se encuentra en estado '{$estadia->obtenerEstado()}'. Solo estadías en curso pueden finalizarse."
                );
            }

            $checkoutEn = gmdate('Y-m-d H:i:s');
            $obsCheckout = $observaciones !== null && trim($observaciones) !== '' ? trim($observaciones) : null;

            $this->estadiaRepo->actualizarEstado(
                $estadiaId,
                Estadia::ESTADO_FINALIZADA,
                checkoutEn: $checkoutEn,
                checkoutPorActorId: $actorIdFinal,
                observacionesCheckout: $obsCheckout
            );

            // D-083: Al completar check-out, marcar la unidad como SUCIA y generar tarea de salida atómicamente
            $this->housekeepingServicio->marcarSuciaPorCheckout(
                $estadia->obtenerUnidadId(),
                $estadiaId,
                $actorIdFinal,
                $obsCheckout
            );

            // Auditoría transversal D-061
            $this->auditoriaServicio->registrar(
                accion: 'ESTADIA_CHECKOUT',
                modulo: 'estadias',
                entidad: 'estadias',
                entidadId: (string) $estadiaId,
                descripcion: "Check-out operativo completado para estadía '{$estadia->obtenerCodigo()}'. Unidad {$estadia->obtenerUnidadNumero()}.",
                valoresAnteriores: [
                    'estado' => $estadia->obtenerEstado(),
                ],
                valoresNuevos: [
                    'estado' => Estadia::ESTADO_FINALIZADA,
                    'checkout_en' => $checkoutEn,
                    'checkout_por_actor_id' => $actorIdFinal,
                    'observaciones_checkout' => $obsCheckout,
                ],
                contexto: null,
                actor: $actorIdFinal,
                usuarioId: null,
                correlacionId: null,
                pdoTransaccional: $this->pdo
            );

            $this->pdo->commit();

            return $this->estadiaRepo->buscarPorId($estadiaId, true);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Anula operativamente una estadía en curso con justificación obligatoria.
     * Preservación histórica: ANULACIÓN != DELETE; la fila permanece en base de datos.
     */
    public function anularEstadia(int $estadiaId, string $motivo, int $actorId): Estadia
    {
        $motivoTrim = trim($motivo);
        if ($motivoTrim === '') {
            throw new ValidacionExcepcion('El motivo de anulación es obligatorio.');
        }

        if (mb_strlen($motivoTrim) > 255) {
            throw new ValidacionExcepcion('El motivo de anulación no debe superar los 255 caracteres.');
        }

        $actorIdFinal = $this->resolverActorId($actorId);

        $this->pdo->beginTransaction();
        try {
            $estadia = $this->estadiaRepo->buscarPorIdParaActualizar($estadiaId);
            if (!$estadia) {
                throw new EstadiaNoEncontradaExcepcion($estadiaId);
            }

            if (!$estadia->estaEnCurso()) {
                throw new EstadoEstadiaInvalidoExcepcion(
                    $estadia->obtenerEstado(),
                    'anular',
                    "No es posible anular la estadía: se encuentra en estado '{$estadia->obtenerEstado()}'. Solo estadías en curso pueden anularse."
                );
            }

            $anuladaEn = gmdate('Y-m-d H:i:s');

            $this->estadiaRepo->actualizarEstado(
                $estadiaId,
                Estadia::ESTADO_ANULADA,
                anuladaEn: $anuladaEn,
                anuladaPorActorId: $actorIdFinal,
                motivoAnulacion: $motivoTrim
            );

            // Auditoría transversal D-061
            $this->auditoriaServicio->registrar(
                accion: 'ESTADIA_ANULADA',
                modulo: 'estadias',
                entidad: 'estadias',
                entidadId: (string) $estadiaId,
                descripcion: "Estadía '{$estadia->obtenerCodigo()}' anulada operativamente. Motivo: {$motivoTrim}.",
                valoresAnteriores: [
                    'estado' => $estadia->obtenerEstado(),
                ],
                valoresNuevos: [
                    'estado' => Estadia::ESTADO_ANULADA,
                    'anulada_en' => $anuladaEn,
                    'anulada_por_actor_id' => $actorIdFinal,
                    'motivo_anulacion' => $motivoTrim,
                ],
                contexto: null,
                actor: $actorIdFinal,
                usuarioId: null,
                correlacionId: null,
                pdoTransaccional: $this->pdo
            );

            $this->pdo->commit();

            return $this->estadiaRepo->buscarPorId($estadiaId, true);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Actualiza la lista de huéspedes y asignación de responsable para una estadía en curso.
     *
     * @param array<array<string, mixed>> $huespedesRaw
     * @return array<EstadiaHuesped>
     */
    public function actualizarHuespedes(int $estadiaId, array $huespedesRaw, int $actorId): array
    {
        $actorIdFinal = $this->resolverActorId($actorId);

        $this->pdo->beginTransaction();
        try {
            $estadia = $this->estadiaRepo->buscarPorIdParaActualizar($estadiaId);
            if (!$estadia) {
                throw new EstadiaNoEncontradaExcepcion($estadiaId);
            }

            if (!$estadia->estaEnCurso()) {
                throw new EstadoEstadiaInvalidoExcepcion(
                    $estadia->obtenerEstado(),
                    'actualizar huéspedes',
                    "Solo es posible modificar huéspedes en estadías en estado 'EN_CURSO'."
                );
            }

            $unidad = $this->unidadRepo->buscarPorId($estadia->obtenerUnidadId());
            $capacidadMaxima = $unidad ? $unidad->obtenerCapacidadPersonas() : 10;

            $huespedesValidados = $this->validarHuespedes($huespedesRaw, $capacidadMaxima, $estadia->obtenerUnidadId());

            $modelosHuespedes = [];
            foreach ($huespedesValidados as $h) {
                $modelosHuespedes[] = new EstadiaHuesped(
                    null,
                    $estadiaId,
                    $h['persona_id'],
                    $h['es_responsable']
                );
            }

            $this->estadiaRepo->reemplazarHuespedes($estadiaId, $modelosHuespedes);

            // Auditoría transversal D-061
            $this->auditoriaServicio->registrar(
                accion: 'ESTADIA_HUESPEDES_ACTUALIZADOS',
                modulo: 'estadias',
                entidad: 'estadias',
                entidadId: (string) $estadiaId,
                descripcion: "Huéspedes actualizados para estadía '{$estadia->obtenerCodigo()}'. Total: " . count($modelosHuespedes),
                valoresAnteriores: [
                    'cantidad_huespedes' => $estadia->obtenerCantidadHuespedes(),
                ],
                valoresNuevos: [
                    'cantidad_huespedes' => count($modelosHuespedes),
                ],
                contexto: null,
                actor: $actorIdFinal,
                usuarioId: null,
                correlacionId: null,
                pdoTransaccional: $this->pdo
            );

            $this->pdo->commit();

            return $this->estadiaRepo->obtenerHuespedesPorEstadiaId($estadiaId);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Valida de forma estricta las reglas de negocio de huéspedes para una estadía.
     *
     * @param array<array<string, mixed>> $huespedesRaw
     * @return array<array{persona_id: int, es_responsable: bool}>
     */
    private function validarHuespedes(array $huespedesRaw, int $capacidadMaxima, int $unidadId): array
    {
        $totalHuespedes = count($huespedesRaw);

        if ($totalHuespedes < 1) {
            throw new HuespedInvalidoExcepcion('Debe registrar al menos un huésped para la estadía.');
        }

        if ($totalHuespedes > $capacidadMaxima) {
            throw new CapacidadExcedidaExcepcion($totalHuespedes, $capacidadMaxima, $unidadId);
        }

        $personasIds = [];
        $responsablesCount = 0;
        $huespedesValidados = [];

        foreach ($huespedesRaw as $idx => $h) {
            $pId = (int) ($h['persona_id'] ?? 0);
            if ($pId <= 0) {
                throw new HuespedInvalidoExcepcion("El huésped en la posición " . ($idx + 1) . " no tiene un identificador de persona válido.");
            }

            if (in_array($pId, $personasIds, true)) {
                throw new HuespedInvalidoExcepcion("El huésped con ID {$pId} se encuentra duplicado en la lista.");
            }

            $personasIds[] = $pId;

            // Verificar existencia en catálogo central de personas
            $persona = $this->personaRepo->buscarPorId($pId, false);
            if (!$persona) {
                throw new HuespedInvalidoExcepcion("No se encontró el registro central de la persona con ID {$pId}.");
            }

            $esResponsable = !empty($h['es_responsable']);
            if ($esResponsable) {
                $responsablesCount++;
            }

            $huespedesValidados[] = [
                'persona_id' => $pId,
                'es_responsable' => $esResponsable,
            ];
        }

        if ($responsablesCount === 0) {
            throw new HuespedInvalidoExcepcion('Debe designar exactamente un huésped responsable de la estadía.');
        }

        if ($responsablesCount > 1) {
            throw new HuespedInvalidoExcepcion('Solo puede haber un huésped responsable por estadía.');
        }

        return $huespedesValidados;
    }

    /**
     * Obtiene los datos detallados de una estadía por su ID.
     *
     * @return array<string, mixed>
     */
    public function obtenerEstadia(int $id): array
    {
        $estadia = $this->estadiaRepo->buscarPorId($id, true);
        if (!$estadia) {
            throw new EstadiaNoEncontradaExcepcion($id);
        }

        return $estadia->haciaArreglo();
    }

    /**
     * Busca una estadía por su código único de estadía.
     */
    public function buscarEstadiaPorCodigo(string $codigo): ?Estadia
    {
        return $this->estadiaRepo->buscarPorCodigo($codigo, true);
    }

    /**
     * Lista estadías paginadas con soporte para filtros.
     *
     * @param array<string, mixed> $filtros
     * @return array{items: array<array<string, mixed>>, total: int, pagina: int, por_pagina: int, total_paginas: int}
     */
    public function listarEstadias(array $filtros = [], int $pagina = 1, int $porPagina = 20): array
    {
        $pagina = max(1, $pagina);
        $porPagina = max(1, min(100, $porPagina));
        $offset = ($pagina - 1) * $porPagina;

        $total = $this->estadiaRepo->contar($filtros);
        $estadias = $this->estadiaRepo->listar($filtros, $porPagina, $offset);

        $items = [];
        foreach ($estadias as $e) {
            $items[] = $e->haciaArreglo();
        }

        return [
            'items' => $items,
            'total' => $total,
            'pagina' => $pagina,
            'por_pagina' => $porPagina,
            'total_paginas' => (int) ceil($total / $porPagina),
        ];
    }

    /**
     * Obtiene las unidades de una reserva confirmada que son elegibles para check-in (aún no tienen estadía).
     *
     * @return array<array<string, mixed>>
     */
    public function obtenerUnidadesElegiblesParaCheckin(int $reservaId): array
    {
        $reserva = $this->reservaRepo->buscarPorId($reservaId, false);
        if (!$reserva) {
            throw new ReservaNoEncontradaExcepcion($reservaId);
        }

        if ($reserva->obtenerEstado() !== Reserva::ESTADO_CONFIRMADA) {
            return [];
        }

        return $this->estadiaRepo->obtenerUnidadesElegiblesParaCheckin($reservaId);
    }

    /**
     * Obtiene el resumen operativo derivado de una reserva evaluando el estado de sus unidades físicas.
     * Principio: no muta el estado comercial de reservas, sino que computa la ocupación física derivada.
     *
     * @return array<string, mixed>
     */
    public function obtenerResumenOperativoReserva(int $reservaId): array
    {
        $reserva = $this->reservaRepo->buscarPorId($reservaId, true);
        if (!$reserva) {
            throw new ReservaNoEncontradaExcepcion($reservaId);
        }

        $unidades = $reserva->obtenerUnidades();
        $totalUnidades = count($unidades);
        $estadias = $this->estadiaRepo->obtenerEstadiasPorReservaId($reservaId);

        $enCurso = 0;
        $finalizadas = 0;
        $anuladas = 0;
        $unidadesConEstadia = [];

        foreach ($estadias as $e) {
            $unidadesConEstadia[$e->obtenerReservaUnidadId()] = true;
            if ($e->estaEnCurso()) {
                $enCurso++;
            } elseif ($e->estaFinalizada()) {
                $finalizadas++;
            } elseif ($e->estaAnulada()) {
                $anuladas++;
            }
        }

        $pendientesCheckin = max(0, $totalUnidades - count($unidadesConEstadia));

        // Derivar estado operativo de la reserva
        $estadoOperativo = 'SIN_CHECKIN';
        if ($enCurso > 0 && ($pendientesCheckin > 0 || $finalizadas > 0)) {
            $estadoOperativo = 'PARCIAL_EN_CURSO';
        } elseif ($enCurso > 0 && $pendientesCheckin === 0) {
            $estadoOperativo = 'COMPLETA_EN_CURSO';
        } elseif ($finalizadas > 0 && $pendientesCheckin === 0 && $enCurso === 0) {
            $estadoOperativo = 'FINALIZADA';
        } elseif ($finalizadas > 0 && $pendientesCheckin > 0) {
            $estadoOperativo = 'PARCIAL_FINALIZADA';
        }

        return [
            'reserva_id' => $reservaId,
            'reserva_codigo' => $reserva->obtenerCodigo(),
            'reserva_estado_comercial' => $reserva->obtenerEstado(),
            'total_unidades' => $totalUnidades,
            'unidades_en_curso' => $enCurso,
            'unidades_finalizadas' => $finalizadas,
            'unidades_anuladas' => $anuladas,
            'unidades_pendientes_checkin' => $pendientesCheckin,
            'estado_operativo' => $estadoOperativo,
            'estado_operativo_derivado' => $estadoOperativo,
            'estadias' => array_map(fn(Estadia $e) => $e->haciaArreglo(), $estadias),
        ];
    }

    /**
     * Resuelve canónicamente el ID de actor ejecutor (D-061: ACTOR != USUARIO).
     * Si se provee un actor_id existente, lo utiliza; si se provee un usuario_id,
     * resuelve o asegura su actor humano asociado; de lo contrario utiliza el actor actual de sesión.
     */
    public function resolverActorId(?int $actorOUsuarioId = null): int
    {
        if ($actorOUsuarioId !== null && $actorOUsuarioId > 0) {
            $stmt = $this->pdo->prepare('SELECT id FROM actores WHERE id = :id LIMIT 1');
            $stmt->bindValue(':id', $actorOUsuarioId, PDO::PARAM_INT);
            $stmt->execute();
            if ($stmt->fetchColumn()) {
                return $actorOUsuarioId;
            }

            try {
                $actor = $this->auditoriaServicio->obtenerOAsegurarActorUsuario($actorOUsuarioId, $this->pdo);
                if ($actor && $actor->obtenerId() !== null) {
                    return (int) $actor->obtenerId();
                }
            } catch (Throwable) {
                // Fallback defensivo
            }
        }

        $actorActual = $this->auditoriaServicio->obtenerActorActual($this->pdo);
        return (int) ($actorActual->obtenerId() ?? 1);
    }
}

