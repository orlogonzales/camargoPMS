<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

use CamargoPMS\Excepciones\ConfiguracionFaltanteExcepcion;
use CamargoPMS\Excepciones\ConflictoDisponibilidadExcepcion;
use CamargoPMS\Excepciones\EstadoReservaInvalidoExcepcion;
use CamargoPMS\Excepciones\IntervaloInvalidoExcepcion;
use CamargoPMS\Excepciones\ReservaNoEncontradaExcepcion;
use CamargoPMS\Excepciones\UnidadNoEncontradaExcepcion;
use CamargoPMS\Excepciones\ValidacionExcepcion;
use CamargoPMS\Modelos\ActorAuditoria;
use CamargoPMS\Modelos\Reserva;
use CamargoPMS\Modelos\ReservaUnidad;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Repositorios\DisponibilidadRepositorio;
use CamargoPMS\Repositorios\PersonaRepositorio;
use CamargoPMS\Repositorios\PropiedadRepositorio;
use CamargoPMS\Repositorios\ReservaRepositorio;
use CamargoPMS\Repositorios\UnidadRepositorio;
use DateTimeImmutable;
use PDO;
use PDOException;
use Throwable;

/**
 * Servicio transaccional central para la gestión de reservas comerciales directas (RESERVAS-1).
 *
 * Principios vinculantes:
 * - RESERVA != DISPONIBILIDAD != INVENTARIO != ESTANCIA != PAGO != CONTRATO != PERSONA.
 * - Intervalo semiabierto [fecha_entrada, fecha_salida) (D-066).
 * - Orden determinista ORDER BY unidad_id ASC, fecha ASC (D-067).
 * - Concurrencia ACID con detección de colisión 1062, 1205, 1213 -> ConflictoDisponibilidadExcepcion (HTTP 409).
 * - Expiración de hold y transición de estados estricta (PENDIENTE -> CONFIRMADA | CANCELADA | EXPIRADA).
 * - Snapshot económico D-069: PEN, DECIMAL(15,2), redondeo ROUND_HALF_UP.
 * - Preservación histórica: Cero DELETE sobre reservas.
 */
class ReservaServicio
{
    private PDO $pdo;
    private ReservaRepositorio $reservaRepo;
    private DisponibilidadRepositorio $disponibilidadRepo;
    private UnidadRepositorio $unidadRepo;
    private PropiedadRepositorio $propiedadRepo;
    private PersonaRepositorio $personaRepo;
    private ConfiguracionServicio $configServicio;
    private AuditoriaServicio $auditoriaServicio;
    private DisponibilidadServicio $disponibilidadServicio;

    public function __construct(
        ?PDO $pdo = null,
        ?ReservaRepositorio $reservaRepo = null,
        ?DisponibilidadRepositorio $disponibilidadRepo = null,
        ?UnidadRepositorio $unidadRepo = null,
        ?PropiedadRepositorio $propiedadRepo = null,
        ?PersonaRepositorio $personaRepo = null,
        ?ConfiguracionServicio $configServicio = null,
        ?AuditoriaServicio $auditoriaServicio = null,
        ?DisponibilidadServicio $disponibilidadServicio = null
    ) {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->reservaRepo = $reservaRepo ?? new ReservaRepositorio($this->pdo);
        $this->disponibilidadRepo = $disponibilidadRepo ?? new DisponibilidadRepositorio($this->pdo);
        $this->unidadRepo = $unidadRepo ?? new UnidadRepositorio($this->pdo);
        $this->propiedadRepo = $propiedadRepo ?? new PropiedadRepositorio($this->pdo);
        $this->personaRepo = $personaRepo ?? new PersonaRepositorio($this->pdo);
        $this->configServicio = $configServicio ?? new ConfiguracionServicio($this->pdo);
        $this->auditoriaServicio = $auditoriaServicio ?? new AuditoriaServicio($this->pdo);
        $this->disponibilidadServicio = $disponibilidadServicio ?? new DisponibilidadServicio(
            $this->pdo,
            $this->disponibilidadRepo,
            $this->unidadRepo,
            $this->propiedadRepo,
            $this->configServicio,
            $this->auditoriaServicio
        );
    }

    /**
     * Crea una nueva reserva comercial directa (1 Reserva : N Unidades).
     *
     * @param array<string, mixed> $datos
     * @param int|null $ejecutadoPorUsuarioId
     * @return Reserva
     * @throws ValidacionExcepcion
     * @throws IntervaloInvalidoExcepcion
     * @throws ConflictoDisponibilidadExcepcion
     * @throws UnidadNoEncontradaExcepcion
     */
    public function crearReserva(array $datos, ?int $ejecutadoPorUsuarioId = null): Reserva
    {
        // 1. Validar titular
        $titularId = isset($datos['persona_titular_id']) ? (int) $datos['persona_titular_id'] : 0;
        if ($titularId <= 0) {
            throw new ValidacionExcepcion('Debe especificar una persona titular válida para la reserva.');
        }

        $personaTitular = $this->personaRepo->buscarPorId($titularId, false);
        if ($personaTitular === null) {
            throw new ValidacionExcepcion("La persona titular con ID {$titularId} no existe.");
        }

        // 2. Validar intervalo temporal hotelero (D-066)
        $fechaEntrada = (string) ($datos['fecha_entrada'] ?? '');
        $fechaSalida = (string) ($datos['fecha_salida'] ?? '');
        $intervalo = $this->disponibilidadServicio->validarIntervaloHotelero($fechaEntrada, $fechaSalida);
        $noches = $intervalo['noches'];
        $diasEstancia = $intervalo['dias'];

        // 3. Validar unidades asignadas (Multiunidad)
        $unidadesInput = $datos['unidades'] ?? [];
        if (!is_array($unidadesInput) || empty($unidadesInput)) {
            throw new ValidacionExcepcion('La reserva debe contener al menos una unidad física asignada.');
        }

        $unidadesProcesadas = [];
        $unidadesIds = [];
        foreach ($unidadesInput as $indice => $item) {
            $uId = isset($item['unidad_id']) ? (int) $item['unidad_id'] : 0;
            if ($uId <= 0) {
                throw new ValidacionExcepcion("La unidad en el índice {$indice} no tiene un ID válido.");
            }

            if (in_array($uId, $unidadesIds, true)) {
                throw new ValidacionExcepcion("La unidad con ID {$uId} está duplicada en la misma reserva.");
            }

            $unidad = $this->unidadRepo->buscarPorId($uId);
            if ($unidad === null) {
                throw new UnidadNoEncontradaExcepcion("La unidad solicitada con ID {$uId} no existe.");
            }

            if (!$unidad->estaActiva()) {
                throw new ValidacionExcepcion("La unidad '{$unidad->obtenerCodigo()}' no se encuentra activa para operar.");
            }

            // Normalizar precio unitario con BCMath y cadenas
            $precioNocheStr = trim((string) ($item['precio_unitario_noche'] ?? '0.00'));
            if (!is_numeric($precioNocheStr) || bccomp($precioNocheStr, '0.00', 4) < 0) {
                throw new ValidacionExcepcion("El precio unitario por noche para la unidad '{$unidad->obtenerCodigo()}' no puede ser negativo o inválido.");
            }
            // Snapshot económico por unidad (D-069):
            // Precisión intermedia >= 4 decimales para valores unitarios / tasas (D-069).
            $precioNocheIntermedio = $this->redondearBc($precioNocheStr, 4);
            $precioNoche = $this->redondearBc($precioNocheStr, 2);

            // En RESERVAS-1, al no existir aún fuente tributaria ni motor fiscal formal, se aplica
            // impuesto 0.00 (sin impuesto aplicado por el PMS, sin asumir clasificaciones fiscales
            // como gravada, exonerada o inafecta). Por tanto, total de la unidad = subtotal.
            $subtotalUnidad = bcmul($precioNocheIntermedio, (string) $noches, 4);
            $subtotalUnidadRedondeado = $this->redondearBc($subtotalUnidad, 2);
            $impuestoUnidad = '0.00';
            $totalUnidad = bcadd($subtotalUnidadRedondeado, $impuestoUnidad, 2);

            $unidadesIds[] = $uId;
            $unidadesProcesadas[] = [
                'unidad' => $unidad,
                'unidad_id' => $uId,
                'precio_unitario_noche' => $precioNoche,
                'noches' => $noches,
                'subtotal' => $subtotalUnidadRedondeado,
                'impuesto' => $impuestoUnidad,
                'total' => $totalUnidad,
                'moneda_codigo' => 'PEN',
            ];
        }

        // 4. Totales de reserva acumulados (D-069)
        $subtotalAcumulado = '0.00';
        $impuestoAcumulado = '0.00';
        $totalAcumulado = '0.00';
        foreach ($unidadesProcesadas as $u) {
            $subtotalAcumulado = bcadd($subtotalAcumulado, $u['subtotal'], 2);
            $impuestoAcumulado = bcadd($impuestoAcumulado, $u['impuesto'], 2);
            $totalAcumulado = bcadd($totalAcumulado, $u['total'], 2);
        }

        // 5. Estado y vigencia de hold
        $estado = strtoupper(trim((string) ($datos['estado'] ?? Reserva::ESTADO_PENDIENTE)));
        if (!in_array($estado, [Reserva::ESTADO_PENDIENTE, Reserva::ESTADO_CONFIRMADA], true)) {
            $estado = Reserva::ESTADO_PENDIENTE;
        }

        $expiraEn = null;
        if ($estado === Reserva::ESTADO_PENDIENTE) {
            $paramHold = isset($datos['duracion_hold_minutos']) && is_numeric((string) $datos['duracion_hold_minutos']) && (int) $datos['duracion_hold_minutos'] > 0
                ? (int) $datos['duracion_hold_minutos']
                : $this->configServicio->obtener('reservas.duracion_hold_minutos', null);

            if ($paramHold === null || trim((string) $paramHold) === '' || !is_numeric((string) $paramHold) || (int) $paramHold <= 0) {
                throw new ConfiguracionFaltanteExcepcion(
                    'reservas.duracion_hold_minutos',
                    "No se puede crear una reserva en estado PENDIENTE: el parámetro operacional 'reservas.duracion_hold_minutos' no ha sido configurado por el negocio."
                );
            }
            $duracionHoldMin = (int) $paramHold;
            $expiraEn = date('Y-m-d H:i:s', time() + ($duracionHoldMin * 60));
        }

        // 6. Canal y origen
        $canal = strtoupper(trim((string) ($datos['canal'] ?? Reserva::CANAL_PMS)));
        if (!in_array($canal, [Reserva::CANAL_PMS, Reserva::CANAL_WEB, Reserva::CANAL_APP, Reserva::CANAL_OTA], true)) {
            $canal = Reserva::CANAL_PMS;
        }
        $origen = trim((string) ($datos['origen'] ?? 'DIRECTO'));
        if ($origen === '') {
            $origen = 'DIRECTO';
        }
        $observaciones = isset($datos['observaciones']) && trim((string) $datos['observaciones']) !== ''
            ? trim((string) $datos['observaciones'])
            : null;

        // 7. Pre-verificación fail-fast de disponibilidad para todas las unidades
        $ocupadas = $this->disponibilidadRepo->obtenerNochesOcupadasPorUnidades($unidadesIds, $fechaEntrada, $fechaSalida);
        foreach ($ocupadas as $uId => $diasOcupados) {
            if (!empty($diasOcupados)) {
                $primeraFecha = array_key_first($diasOcupados);
                throw new ConflictoDisponibilidadExcepcion(
                    $uId,
                    (string) $primeraFecha,
                    "La unidad con ID {$uId} ya no está disponible para la fecha {$primeraFecha}."
                );
            }
        }

        // 8. Resolver actor ejecutor (D-061)
        $actor = $this->resolverActorEjecutor($ejecutadoPorUsuarioId);
        $actorId = $actor?->obtenerId();

        // 9. Transacción ACID estricta
        $this->pdo->beginTransaction();
        try {
            $codigo = $this->reservaRepo->generarSiguienteCodigo();

            $nuevaReserva = new Reserva(
                null,
                $codigo,
                $titularId,
                $fechaEntrada,
                $fechaSalida,
                $noches,
                $estado,
                $expiraEn,
                $canal,
                $origen,
                'PEN',
                $subtotalAcumulado,
                $impuestoAcumulado,
                $totalAcumulado,
                $observaciones,
                null,
                null,
                null,
                $estado === Reserva::ESTADO_CONFIRMADA ? date('Y-m-d H:i:s') : null,
                $estado === Reserva::ESTADO_CONFIRMADA ? $actorId : null,
                $actorId,
                null,
                null
            );

            $reservaId = $this->reservaRepo->crear($nuevaReserva);

            // A. Guardar detalle de unidades
            $reservaUnidadesModelos = [];
            foreach ($unidadesProcesadas as $uProc) {
                $ru = new ReservaUnidad(
                    null,
                    $reservaId,
                    $uProc['unidad_id'],
                    $uProc['precio_unitario_noche'],
                    $uProc['noches'],
                    $uProc['subtotal'],
                    $uProc['impuesto'],
                    $uProc['total'],
                    'PEN'
                );
                $this->reservaRepo->guardarUnidad($ru);
                $reservaUnidadesModelos[] = $ru;
            }

            // B. Bloqueo determinista de inventario (D-067)
            // Se ordenan las tuplas deterministamente por (unidad_id ASC, fecha ASC)
            $bloqueosAInsertar = [];
            foreach ($unidadesIds as $uId) {
                foreach ($diasEstancia as $fechaNoche) {
                    $bloqueosAInsertar[] = [
                        'unidad_id' => $uId,
                        'fecha' => $fechaNoche,
                    ];
                }
            }

            usort($bloqueosAInsertar, function (array $a, array $b): int {
                if ($a['unidad_id'] !== $b['unidad_id']) {
                    return $a['unidad_id'] <=> $b['unidad_id'];
                }
                return strcmp($a['fecha'], $b['fecha']);
            });

            foreach ($bloqueosAInsertar as $bloqueo) {
                $this->disponibilidadRepo->insertarInventarioNoche(
                    $bloqueo['unidad_id'],
                    $bloqueo['fecha'],
                    'RESERVA',
                    'RESERVA',
                    $reservaId
                );
            }

            // C. Registrar auditoría transversal
            $this->auditoriaServicio->registrar(
                accion: 'REGISTRAR',
                modulo: 'reservas',
                entidad: 'reservas',
                entidadId: (string) $reservaId,
                descripcion: "Reserva directa '{$codigo}' creada en estado {$estado} para titular ID {$titularId} con " . count($unidadesIds) . " unidad(es) del {$fechaEntrada} al {$fechaSalida} ({$noches} noches). Total: S/ {$totalAcumulado}",
                valoresAnteriores: null,
                valoresNuevos: [
                    'codigo' => $codigo,
                    'persona_titular_id' => $titularId,
                    'fecha_entrada' => $fechaEntrada,
                    'fecha_salida' => $fechaSalida,
                    'noches' => $noches,
                    'estado' => $estado,
                    'expira_en' => $expiraEn,
                    'total' => $totalAcumulado,
                    'unidades_ids' => $unidadesIds,
                ],
                contexto: null,
                actor: $actor,
                usuarioId: $ejecutadoPorUsuarioId,
                correlacionId: null,
                pdoTransaccional: $this->pdo
            );

            $this->pdo->commit();

            return $this->reservaRepo->buscarPorId($reservaId, true);
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $errorCode = (string) $e->getCode();
            $errorInfo = $e->errorInfo[1] ?? 0;

            if ($errorCode === '23000' || $errorInfo === 1062) {
                throw new ConflictoDisponibilidadExcepcion(
                    0,
                    null,
                    'Conflicto de disponibilidad: una o más unidades seleccionadas ya fueron reservadas o bloqueadas simultáneamente.',
                    409,
                    $e
                );
            }

            if ($errorInfo === 1205 || $errorInfo === 1213) {
                throw new ConflictoDisponibilidadExcepcion(
                    0,
                    null,
                    'Conflicto temporal de concurrencia en la base de datos (timeout/deadlock). Por favor reintente la reserva.',
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
     * Consulta una reserva por su ID numérico o código comercial.
     *
     * @param int|string $identificador
     * @return Reserva
     * @throws ReservaNoEncontradaExcepcion
     */
    public function consultarReserva(int|string $identificador): Reserva
    {
        $reserva = null;
        if (is_int($identificador) || (is_string($identificador) && ctype_digit($identificador))) {
            $reserva = $this->reservaRepo->buscarPorId((int) $identificador, true);
        }

        if ($reserva === null && is_string($identificador)) {
            $reserva = $this->reservaRepo->buscarPorCodigo($identificador, true);
        }

        if ($reserva === null) {
            throw new ReservaNoEncontradaExcepcion($identificador);
        }

        return $reserva;
    }

    /**
     * Lista reservas filtradas y paginadas.
     *
     * @param array<string, mixed> $filtros
     * @return array{total: int, items: array<Reserva>, limite: int, offset: int}
     */
    public function listarReservas(array $filtros = [], int $limite = 20, int $offset = 0): array
    {
        $total = $this->reservaRepo->contar($filtros);
        $items = $this->reservaRepo->listar($filtros, $limite, $offset);

        return [
            'total' => $total,
            'items' => $items,
            'limite' => $limite,
            'offset' => $offset,
        ];
    }

    /**
     * Confirma una reserva que se encuentra en estado PENDIENTE.
     *
     * @param int $id
     * @param int|null $ejecutadoPorUsuarioId
     * @return Reserva
     * @throws ReservaNoEncontradaExcepcion
     * @throws EstadoReservaInvalidoExcepcion
     */
    public function confirmarReserva(int $id, ?int $ejecutadoPorUsuarioId = null): Reserva
    {
        $reserva = $this->reservaRepo->buscarPorId($id, false);
        if ($reserva === null) {
            throw new ReservaNoEncontradaExcepcion($id);
        }

        // Si ya está confirmada, idempotencia
        if ($reserva->esConfirmada()) {
            return $this->reservaRepo->buscarPorId($id, true);
        }

        if ($reserva->esCancelada() || $reserva->esExpirada()) {
            throw new EstadoReservaInvalidoExcepcion(
                $reserva->obtenerEstado(),
                'confirmar',
                "No es posible confirmar la reserva '{$reserva->obtenerCodigo()}' porque ya se encuentra en estado terminal {$reserva->obtenerEstado()}."
            );
        }

        // Verificar si la reserva pendiente ya expiró por tiempo de hold
        if ($reserva->haExpirado()) {
            // Ejecutar expiración atómica para liberar inventario
            $this->expirarReservasPendientes();
            throw new EstadoReservaInvalidoExcepcion(
                Reserva::ESTADO_EXPIRADA,
                'confirmar',
                "No es posible confirmar la reserva '{$reserva->obtenerCodigo()}' porque su tiempo de retención (hold) ha vencido ({$reserva->obtenerExpiraEn()})."
            );
        }

        $actor = $this->resolverActorEjecutor($ejecutadoPorUsuarioId);
        $actorId = $actor?->obtenerId();

        $this->pdo->beginTransaction();
        try {
            $this->reservaRepo->cambiarEstado($id, Reserva::ESTADO_CONFIRMADA, [
                'actor_id' => $actorId,
            ]);

            $this->auditoriaServicio->registrar(
                accion: 'CONFIRMAR',
                modulo: 'reservas',
                entidad: 'reservas',
                entidadId: (string) $id,
                descripcion: "Reserva '{$reserva->obtenerCodigo()}' confirmada exitosamente.",
                valoresAnteriores: ['estado' => Reserva::ESTADO_PENDIENTE],
                valoresNuevos: ['estado' => Reserva::ESTADO_CONFIRMADA],
                contexto: null,
                actor: $actor,
                usuarioId: $ejecutadoPorUsuarioId,
                correlacionId: null,
                pdoTransaccional: $this->pdo
            );

            $this->pdo->commit();

            return $this->reservaRepo->buscarPorId($id, true);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Cancela una reserva PENDIENTE o CONFIRMADA y libera inmediatamente el inventario diario asociado (D-067).
     *
     * @param int $id
     * @param string $motivo
     * @param int|null $ejecutadoPorUsuarioId
     * @return Reserva
     * @throws ReservaNoEncontradaExcepcion
     * @throws EstadoReservaInvalidoExcepcion
     * @throws ValidacionExcepcion
     */
    public function cancelarReserva(int $id, string $motivo, ?int $ejecutadoPorUsuarioId = null): Reserva
    {
        $reserva = $this->reservaRepo->buscarPorId($id, false);
        if ($reserva === null) {
            throw new ReservaNoEncontradaExcepcion($id);
        }

        if ($reserva->esCancelada() || $reserva->esExpirada()) {
            throw new EstadoReservaInvalidoExcepcion(
                $reserva->obtenerEstado(),
                'cancelar',
                "La reserva '{$reserva->obtenerCodigo()}' ya se encuentra en estado terminal {$reserva->obtenerEstado()}."
            );
        }

        $motivo = trim($motivo);
        if ($motivo === '') {
            throw new ValidacionExcepcion('Debe indicar un motivo de cancelación para registrar el evento.');
        }

        if (mb_strlen($motivo) > 255) {
            throw new ValidacionExcepcion('El motivo de cancelación no puede exceder los 255 caracteres.');
        }

        $actor = $this->resolverActorEjecutor($ejecutadoPorUsuarioId);
        $actorId = $actor?->obtenerId();

        $this->pdo->beginTransaction();
        try {
            // A. Cambiar estado maestro
            $this->reservaRepo->cambiarEstado($id, Reserva::ESTADO_CANCELADA, [
                'actor_id' => $actorId,
                'motivo' => $motivo,
            ]);

            // B. Liberación atómica de inventario diario asociado
            $nochesLiberadas = $this->disponibilidadRepo->eliminarInventarioPorOrigen('RESERVA', $id);

            // C. Auditoría transversal inmutable
            $this->auditoriaServicio->registrar(
                accion: 'CANCELAR',
                modulo: 'reservas',
                entidad: 'reservas',
                entidadId: (string) $id,
                descripcion: "Reserva '{$reserva->obtenerCodigo()}' cancelada. Motivo: {$motivo}. Noches liberadas en inventario: {$nochesLiberadas}.",
                valoresAnteriores: ['estado' => $reserva->obtenerEstado()],
                valoresNuevos: ['estado' => Reserva::ESTADO_CANCELADA, 'motivo_cancelacion' => $motivo],
                contexto: ['noches_liberadas' => $nochesLiberadas],
                actor: $actor,
                usuarioId: $ejecutadoPorUsuarioId,
                correlacionId: null,
                pdoTransaccional: $this->pdo
            );

            $this->pdo->commit();

            return $this->reservaRepo->buscarPorId($id, true);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Expira atómicamente todas las reservas PENDIENTES cuyo hold haya vencido (Servicio Idempotente).
     *
     * @param int|null $actorSistemaId
     * @return array{total_expiradas: int, codigos: array<string>}
     */
    public function expirarReservasPendientes(?int $actorSistemaId = null): array
    {
        $ahora = date('Y-m-d H:i:s');
        $pendientes = $this->reservaRepo->obtenerPendientesExpiradas($ahora);

        if (empty($pendientes)) {
            return [
                'total_expiradas' => 0,
                'codigos' => [],
            ];
        }

        $actorSistema = $this->auditoriaServicio->obtenerActorSistema($this->pdo);
        $codigosExpirados = [];

        foreach ($pendientes as $reserva) {
            $reservaId = $reserva->obtenerId();
            if ($reservaId === null) {
                continue;
            }

            $this->pdo->beginTransaction();
            try {
                // A. Actualizar estado a EXPIRADA
                $this->reservaRepo->cambiarEstado($reservaId, Reserva::ESTADO_EXPIRADA, [
                    'motivo' => "Hold de reserva pendiente expirado (vencía {$reserva->obtenerExpiraEn()})",
                ]);

                // B. Liberar noches de inventario retenidas
                $nochesLiberadas = $this->disponibilidadRepo->eliminarInventarioPorOrigen('RESERVA', $reservaId);

                // C. Registrar auditoría transversal
                $this->auditoriaServicio->registrar(
                    accion: 'EXPIRAR',
                    modulo: 'reservas',
                    entidad: 'reservas',
                    entidadId: (string) $reservaId,
                    descripcion: "Reserva '{$reserva->obtenerCodigo()}' expirada automáticamente por vencimiento de hold. Noches liberadas: {$nochesLiberadas}.",
                    valoresAnteriores: ['estado' => Reserva::ESTADO_PENDIENTE, 'expira_en' => $reserva->obtenerExpiraEn()],
                    valoresNuevos: ['estado' => Reserva::ESTADO_EXPIRADA],
                    contexto: ['noches_liberadas' => $nochesLiberadas, 'instante_expiracion' => $ahora],
                    actor: $actorSistema,
                    usuarioId: null,
                    correlacionId: null,
                    pdoTransaccional: $this->pdo
                );

                $this->pdo->commit();
                $codigosExpirados[] = $reserva->obtenerCodigo();
            } catch (Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                // Si falla una, continuar con las siguientes para no bloquear todo el lote
                continue;
            }
        }

        return [
            'total_expiradas' => count($codigosExpirados),
            'codigos' => $codigosExpirados,
        ];
    }

    /**
     * Resuelve el actor humano ejecutor para trazabilidad (D-061: ACTOR != USUARIO).
     */
    private function resolverActorEjecutor(?int $usuarioId): ?ActorAuditoria
    {
        if ($usuarioId === null || $usuarioId <= 0) {
            return null;
        }

        return $this->auditoriaServicio->obtenerOAsegurarActorUsuario($usuarioId, $this->pdo);
    }

    /**
     * Redondeo canónico ROUND_HALF_UP usando BCMath y strings (D-069).
     * Evita conversiones o imprecisiones de coma flotante binaria IEEE 754.
     */
    private function redondearBc(string $importe, int $decimales = 2): string
    {
        $importe = trim($importe);
        if ($importe === '' || !is_numeric($importe)) {
            return '0.' . str_repeat('0', $decimales);
        }

        if (!str_contains($importe, '.')) {
            return $importe . '.' . str_repeat('0', $decimales);
        }

        $esNegativo = str_starts_with($importe, '-');
        $factor = '0.' . str_repeat('0', $decimales) . '5';

        $ajustado = $esNegativo
            ? bcsub($importe, $factor, $decimales + 1)
            : bcadd($importe, $factor, $decimales + 1);

        $partes = explode('.', $ajustado);
        $enteros = $partes[0];
        $fraccion = isset($partes[1]) ? substr($partes[1], 0, $decimales) : str_repeat('0', $decimales);

        if (strlen($fraccion) < $decimales) {
            $fraccion = str_pad($fraccion, $decimales, '0');
        }

        return $enteros . '.' . $fraccion;
    }
}
