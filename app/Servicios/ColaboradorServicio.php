<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

use CamargoPMS\Excepciones\ColaboradorDuplicadoExcepcion;
use CamargoPMS\Excepciones\EntidadNoEncontradaExcepcion;
use CamargoPMS\Excepciones\EpisodioLaboralActivoExcepcion;
use CamargoPMS\Excepciones\SolapamientoLaboralExcepcion;
use CamargoPMS\Excepciones\ValidacionExcepcion;
use CamargoPMS\Modelos\AsignacionCargo;
use CamargoPMS\Modelos\Cargo;
use CamargoPMS\Modelos\Colaborador;
use CamargoPMS\Modelos\EpisodioLaboral;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Repositorios\AsignacionCargoRepositorio;
use CamargoPMS\Repositorios\CargoRepositorio;
use CamargoPMS\Repositorios\ColaboradorRepositorio;
use CamargoPMS\Repositorios\EpisodioLaboralRepositorio;
use CamargoPMS\Repositorios\PersonaRepositorio;
use DateTimeImmutable;
use PDO;
use Throwable;

/**
 * Servicio de dominio para la gestión del personal, colaboradores y episodios laborales.
 *
 * Principio Vinculante:
 * PERSONA ≠ COLABORADOR ≠ EPISODIO LABORAL ≠ CARGO ≠ USUARIO ≠ ROL DEL SISTEMA
 *
 * Garantiza integridad transaccional, invariantes temporales, unicidad lógica de colaborador
 * por persona, e inmutabilidad de la historia laboral en ceses y reingresos.
 */
class ColaboradorServicio
{
    private const MOTIVOS_CESE_VALIDOS = [
        'RENUNCIA',
        'DESPIDO',
        'MUTUO_ACUERDO',
        'FIN_CONTRATO',
        'JUBILACION',
        'OTRO',
    ];

    private PDO $pdo;
    private ColaboradorRepositorio $colaboradorRepo;
    private PersonaRepositorio $personaRepo;
    private EpisodioLaboralRepositorio $episodioRepo;
    private AsignacionCargoRepositorio $asignacionRepo;
    private CargoRepositorio $cargoRepo;

    public function __construct(
        ?PDO $pdo = null,
        ?ColaboradorRepositorio $colaboradorRepo = null,
        ?PersonaRepositorio $personaRepo = null,
        ?EpisodioLaboralRepositorio $episodioRepo = null,
        ?AsignacionCargoRepositorio $asignacionRepo = null,
        ?CargoRepositorio $cargoRepo = null
    ) {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->colaboradorRepo = $colaboradorRepo ?? new ColaboradorRepositorio($this->pdo);
        $this->personaRepo = $personaRepo ?? new PersonaRepositorio($this->pdo);
        $this->episodioRepo = $episodioRepo ?? new EpisodioLaboralRepositorio($this->pdo);
        $this->asignacionRepo = $asignacionRepo ?? new AsignacionCargoRepositorio($this->pdo);
        $this->cargoRepo = $cargoRepo ?? new CargoRepositorio($this->pdo);
    }

    /**
     * Registra una persona natural como colaborador inicial de la organización,
     * abriendo atómicamente su primer episodio laboral y asignación de cargo.
     *
     * @param array<string, mixed> $datos
     * @return Colaborador
     * @throws EntidadNoEncontradaExcepcion
     * @throws ValidacionExcepcion
     * @throws ColaboradorDuplicadoExcepcion
     * @throws Throwable
     */
    public function crearColaborador(array $datos): Colaborador
    {
        $personaId = isset($datos['persona_id']) ? (int) $datos['persona_id'] : 0;
        if ($personaId <= 0) {
            throw new ValidacionExcepcion('Se requiere un identificador de persona válido.', ['persona_id' => 'Requerido']);
        }

        $cargoId = isset($datos['cargo_id']) ? (int) $datos['cargo_id'] : 0;
        if ($cargoId <= 0) {
            throw new ValidacionExcepcion('Se requiere un identificador de cargo válido.', ['cargo_id' => 'Requerido']);
        }

        $fechaInicio = isset($datos['fecha_inicio']) ? trim((string) $datos['fecha_inicio']) : date('Y-m-d');
        $this->validarFecha($fechaInicio, 'fecha_inicio');

        $observaciones = isset($datos['observaciones']) && trim((string) $datos['observaciones']) !== ''
            ? trim((string) $datos['observaciones'])
            : null;

        $this->pdo->beginTransaction();
        try {
            // Bloqueo pesimista de la persona para evitar registros concurrentes
            $stmt = $this->pdo->prepare('SELECT id, estado FROM personas WHERE id = :id FOR UPDATE');
            $stmt->bindValue(':id', $personaId, PDO::PARAM_INT);
            $stmt->execute();
            $filaPersona = $stmt->fetch();

            if (!$filaPersona) {
                throw new EntidadNoEncontradaExcepcion('Persona', $personaId);
            }

            if ($filaPersona['estado'] !== 'ACTIVO') {
                throw new ValidacionExcepcion(
                    'No se puede registrar como colaborador a una persona inactiva.',
                    ['persona_id' => 'La persona natural se encuentra inactiva.']
                );
            }

            // Unicidad lógica 1:1 Persona -> Colaborador
            $colaboradorExistente = $this->colaboradorRepo->buscarPorPersonaId($personaId, false);
            if ($colaboradorExistente !== null) {
                throw new ColaboradorDuplicadoExcepcion(
                    $personaId,
                    "La persona con ID {$personaId} ya está registrada como colaborador con código '{$colaboradorExistente->obtenerCodigo()}'. Para un nuevo período de trabajo utilice reingresarColaborador()."
                );
            }

            // Validar cargo existente y activo
            $cargo = $this->cargoRepo->buscarPorId($cargoId);
            if (!$cargo || !$cargo->esActivo()) {
                throw new ValidacionExcepcion(
                    'El cargo seleccionado no existe o no se encuentra activo.',
                    ['cargo_id' => 'Cargo no disponible.']
                );
            }

            // Determinar código único de colaborador
            $codigo = isset($datos['codigo']) && trim((string) $datos['codigo']) !== ''
                ? strtoupper(trim((string) $datos['codigo']))
                : $this->colaboradorRepo->generarSiguienteCodigo();

            // 1. Insertar Colaborador
            $nuevoColaborador = new Colaborador(
                null,
                $personaId,
                $codigo,
                'ACTIVO'
            );
            $colaboradorPersistido = $this->colaboradorRepo->insertar($nuevoColaborador);
            $colaboradorId = (int) $colaboradorPersistido->obtenerId();

            // 2. Insertar Episodio Laboral inicial
            $nuevoEpisodio = new EpisodioLaboral(
                null,
                $colaboradorId,
                $fechaInicio,
                null,
                null,
                $observaciones,
                'ACTIVO'
            );
            $episodioPersistido = $this->episodioRepo->insertar($nuevoEpisodio);
            $episodioId = (int) $episodioPersistido->obtenerId();

            // 3. Insertar Asignación inicial de Cargo
            $nuevaAsignacion = new AsignacionCargo(
                null,
                $episodioId,
                $cargoId,
                $fechaInicio,
                null,
                $observaciones
            );
            $this->asignacionRepo->insertar($nuevaAsignacion);

            $this->pdo->commit();

            return $this->colaboradorRepo->buscarPorId($colaboradorId, true) ?? $colaboradorPersistido;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Registra el reingreso laboral de un colaborador existente previamente cesado.
     * Crea un NUEVO episodio laboral sin duplicar la fila de colaborador ni la persona.
     *
     * @param int $colaboradorId
     * @param array<string, mixed> $datos
     * @return Colaborador
     * @throws EntidadNoEncontradaExcepcion
     * @throws ValidacionExcepcion
     * @throws EpisodioLaboralActivoExcepcion
     * @throws SolapamientoLaboralExcepcion
     * @throws Throwable
     */
    public function reingresarColaborador(int $colaboradorId, array $datos): Colaborador
    {
        if ($colaboradorId <= 0) {
            throw new ValidacionExcepcion('Se requiere un identificador de colaborador válido.');
        }

        $cargoId = isset($datos['cargo_id']) ? (int) $datos['cargo_id'] : 0;
        if ($cargoId <= 0) {
            throw new ValidacionExcepcion('Se requiere un identificador de cargo válido.', ['cargo_id' => 'Requerido']);
        }

        $fechaInicio = isset($datos['fecha_inicio']) ? trim((string) $datos['fecha_inicio']) : '';
        if ($fechaInicio === '') {
            throw new ValidacionExcepcion('Se requiere la fecha de reingreso.', ['fecha_inicio' => 'Requerido']);
        }
        $this->validarFecha($fechaInicio, 'fecha_inicio');

        $observaciones = isset($datos['observaciones']) && trim((string) $datos['observaciones']) !== ''
            ? trim((string) $datos['observaciones'])
            : null;

        $this->pdo->beginTransaction();
        try {
            // Bloqueo pesimista del colaborador
            $stmt = $this->pdo->prepare('SELECT id, persona_id, estado FROM colaboradores WHERE id = :id FOR UPDATE');
            $stmt->bindValue(':id', $colaboradorId, PDO::PARAM_INT);
            $stmt->execute();
            $filaColab = $stmt->fetch();

            if (!$filaColab) {
                throw new EntidadNoEncontradaExcepcion('Colaborador', $colaboradorId);
            }

            $personaId = (int) $filaColab['persona_id'];

            // Verificar que la persona natural siga activa
            $persona = $this->personaRepo->buscarPorId($personaId, false);
            if (!$persona || !$persona->esActivo()) {
                throw new ValidacionExcepcion('No se puede reingresar a un colaborador cuya persona natural está inactiva.');
            }

            // Verificar que el cargo exista y esté activo
            $cargo = $this->cargoRepo->buscarPorId($cargoId);
            if (!$cargo || !$cargo->esActivo()) {
                throw new ValidacionExcepcion('El cargo indicado no existe o no se encuentra activo.', ['cargo_id' => 'Cargo inactivo']);
            }

            // Verificar si ya cuenta con un episodio laboral abierto
            $episodioAbierto = $this->episodioRepo->buscarEpisodioActivo($colaboradorId, false);
            if ($episodioAbierto !== null) {
                throw new EpisodioLaboralActivoExcepcion(
                    $colaboradorId,
                    "El colaborador con ID {$colaboradorId} ya cuenta con un episodio laboral activo abierto (ID {$episodioAbierto->obtenerId()}). Debe cesar el vínculo previo antes de tramitar un reingreso."
                );
            }

            // Validar que la nueva fecha de inicio no se solape con ningún episodio previo
            $episodiosPrevios = $this->episodioRepo->listarPorColaboradorId($colaboradorId, false);
            foreach ($episodiosPrevios as $ep) {
                $inicioPrevio = $ep->obtenerFechaInicio();
                $finPrevio = $ep->obtenerFechaFin();

                if ($finPrevio !== null && $fechaInicio <= $finPrevio) {
                    throw new SolapamientoLaboralExcepcion(
                        "La fecha de reingreso ({$fechaInicio}) entra en conflicto con el episodio previo ID {$ep->obtenerId()} finalizado el {$finPrevio}."
                    );
                }

                if ($fechaInicio <= $inicioPrevio) {
                    throw new SolapamientoLaboralExcepcion(
                        "La fecha de reingreso ({$fechaInicio}) debe ser cronológicamente posterior a los episodios previos."
                    );
                }
            }

            // Reactivar el colaborador si estaba INACTIVO
            if ($filaColab['estado'] !== 'ACTIVO') {
                $this->colaboradorRepo->actualizarEstado($colaboradorId, 'ACTIVO');
            }

            // Crear nuevo episodio laboral
            $nuevoEpisodio = new EpisodioLaboral(
                null,
                $colaboradorId,
                $fechaInicio,
                null,
                null,
                $observaciones,
                'ACTIVO'
            );
            $episodioPersistido = $this->episodioRepo->insertar($nuevoEpisodio);
            $episodioId = (int) $episodioPersistido->obtenerId();

            // Crear asignación inicial para el nuevo episodio
            $nuevaAsignacion = new AsignacionCargo(
                null,
                $episodioId,
                $cargoId,
                $fechaInicio,
                null,
                $observaciones
            );
            $this->asignacionRepo->insertar($nuevaAsignacion);

            $this->pdo->commit();

            return $this->colaboradorRepo->buscarPorId($colaboradorId, true) ?? throw new EntidadNoEncontradaExcepcion('Colaborador', $colaboradorId);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Realiza una transición de cargo dentro del episodio laboral activo.
     * Cierra la asignación vigente el día anterior y abre la nueva sin solapamiento.
     *
     * @param int $colaboradorId
     * @param int $nuevoCargoId
     * @param string $fechaCambio Formato 'Y-m-d'
     * @param string|null $observaciones
     * @return Colaborador
     * @throws EntidadNoEncontradaExcepcion
     * @throws ValidacionExcepcion
     * @throws Throwable
     */
    public function cambiarCargo(int $colaboradorId, int $nuevoCargoId, string $fechaCambio, ?string $observaciones = null): Colaborador
    {
        if ($colaboradorId <= 0) {
            throw new ValidacionExcepcion('Se requiere un identificador de colaborador válido.');
        }

        if ($nuevoCargoId <= 0) {
            throw new ValidacionExcepcion('Se requiere un identificador de nuevo cargo válido.');
        }

        $fechaCambio = trim($fechaCambio);
        $this->validarFecha($fechaCambio, 'fecha_cambio');

        $this->pdo->beginTransaction();
        try {
            // Bloqueo pesimista del colaborador
            $stmt = $this->pdo->prepare('SELECT id, estado FROM colaboradores WHERE id = :id FOR UPDATE');
            $stmt->bindValue(':id', $colaboradorId, PDO::PARAM_INT);
            $stmt->execute();
            $filaColab = $stmt->fetch();

            if (!$filaColab) {
                throw new EntidadNoEncontradaExcepcion('Colaborador', $colaboradorId);
            }

            if ($filaColab['estado'] !== 'ACTIVO') {
                throw new ValidacionExcepcion('No se puede cambiar el cargo de un colaborador inactivo.');
            }

            // Obtener episodio activo abierto
            $episodioActivo = $this->episodioRepo->buscarEpisodioActivo($colaboradorId, true);
            if ($episodioActivo === null) {
                throw new ValidacionExcepcion('El colaborador no cuenta con un vínculo laboral activo abierto.');
            }

            $episodioId = (int) $episodioActivo->obtenerId();

            // Obtener asignación de cargo vigente
            $asignacionVigente = $this->asignacionRepo->buscarAsignacionActiva($episodioId, true);
            if ($asignacionVigente === null) {
                throw new ValidacionExcepcion('El episodio laboral activo no tiene una asignación de cargo vigente.');
            }

            // Validar que el nuevo cargo exista y esté activo
            $nuevoCargo = $this->cargoRepo->buscarPorId($nuevoCargoId);
            if (!$nuevoCargo || !$nuevoCargo->esActivo()) {
                throw new ValidacionExcepcion('El nuevo cargo seleccionado no existe o no se encuentra activo.');
            }

            // Validar que no sea el mismo cargo
            if ($asignacionVigente->obtenerCargoId() === $nuevoCargoId) {
                throw new ValidacionExcepcion(
                    "El colaborador ya ocupa actualmente el cargo '{$nuevoCargo->obtenerNombre()}'.",
                    ['nuevo_cargo_id' => 'Mismo cargo vigente']
                );
            }

            // Validar coherencia de fechas
            $fechaInicioAsignacion = $asignacionVigente->obtenerFechaInicio();
            if ($fechaCambio <= $fechaInicioAsignacion) {
                throw new ValidacionExcepcion(
                    "La fecha de cambio de cargo ({$fechaCambio}) debe ser posterior a la fecha de inicio del cargo actual ({$fechaInicioAsignacion}).",
                    ['fecha_cambio' => 'Fecha anterior o igual al inicio del cargo previo']
                );
            }

            // Calcular fecha_fin de la asignación previa: día anterior al cambio
            $tsCambio = strtotime($fechaCambio);
            $fechaFinPrevia = date('Y-m-d', strtotime('-1 day', $tsCambio));

            // 1. Cerrar asignación anterior
            $obsCierre = "Transición al cargo '{$nuevoCargo->obtenerNombre()}'.";
            $this->asignacionRepo->cerrarAsignacion((int) $asignacionVigente->obtenerId(), $fechaFinPrevia, $obsCierre);

            // Validar solapamiento e integridad temporal con el historial del episodio
            $this->validarSolapamientoAsignacionCargo($episodioId, $fechaCambio, null);

            // 2. Abrir nueva asignación de cargo
            $nuevaAsignacion = new AsignacionCargo(
                null,
                $episodioId,
                $nuevoCargoId,
                $fechaCambio,
                null,
                $observaciones
            );
            $this->asignacionRepo->insertar($nuevaAsignacion);

            $this->pdo->commit();

            return $this->colaboradorRepo->buscarPorId($colaboradorId, true) ?? throw new EntidadNoEncontradaExcepcion('Colaborador', $colaboradorId);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Asigna un cargo a un episodio laboral específico, validando existencia,
     * estado del cargo y ausencia total de solapamiento temporal.
     *
     * @param int $episodioLaboralId
     * @param int $cargoId
     * @param string $fechaInicio Formato 'Y-m-d'
     * @param string|null $fechaFin Formato 'Y-m-d' o null si es indefinida
     * @param string|null $observaciones
     * @return AsignacionCargo
     * @throws EntidadNoEncontradaExcepcion
     * @throws ValidacionExcepcion
     * @throws SolapamientoLaboralExcepcion
     * @throws Throwable
     */
    public function asignarCargoAEpisodio(
        int $episodioLaboralId,
        int $cargoId,
        string $fechaInicio,
        ?string $fechaFin = null,
        ?string $observaciones = null
    ): AsignacionCargo {
        if ($episodioLaboralId <= 0) {
            throw new ValidacionExcepcion('Se requiere un identificador de episodio laboral válido.');
        }

        if ($cargoId <= 0) {
            throw new ValidacionExcepcion('Se requiere un identificador de cargo válido.');
        }

        $cargo = $this->cargoRepo->buscarPorId($cargoId);
        if (!$cargo || !$cargo->esActivo()) {
            throw new ValidacionExcepcion(
                'El cargo seleccionado no existe o no se encuentra activo.',
                ['cargo_id' => 'Cargo inexistente o inactivo']
            );
        }

        $iniciaTransaccionInterna = !$this->pdo->inTransaction();
        if ($iniciaTransaccionInterna) {
            $this->pdo->beginTransaction();
        }

        try {
            // Bloqueo pesimista del episodio laboral
            $stmtEp = $this->pdo->prepare('SELECT id, colaborador_id, estado, fecha_inicio, fecha_fin FROM episodios_laborales WHERE id = :id FOR UPDATE');
            $stmtEp->bindValue(':id', $episodioLaboralId, PDO::PARAM_INT);
            $stmtEp->execute();
            $filaEp = $stmtEp->fetch();

            if (!$filaEp) {
                throw new EntidadNoEncontradaExcepcion('EpisodioLaboral', $episodioLaboralId);
            }

            // Validar solapamiento temporal y límites del episodio
            $this->validarSolapamientoAsignacionCargo($episodioLaboralId, $fechaInicio, $fechaFin);

            $nuevaAsignacion = new AsignacionCargo(
                null,
                $episodioLaboralId,
                $cargoId,
                $fechaInicio,
                $fechaFin,
                $observaciones !== null && trim($observaciones) !== '' ? trim($observaciones) : null
            );

            $asignacionPersistida = $this->asignacionRepo->insertar($nuevaAsignacion);

            if ($iniciaTransaccionInterna) {
                $this->pdo->commit();
            }

            return $asignacionPersistida;
        } catch (Throwable $e) {
            if ($iniciaTransaccionInterna && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Valida que un rango de fechas para una asignación de cargo no se solape con otras asignaciones
     * del mismo episodio ni viole los límites temporales del episodio laboral.
     *
     * @param int $episodioLaboralId
     * @param string $fechaInicio Formato 'Y-m-d'
     * @param string|null $fechaFin Formato 'Y-m-d' o null si la asignación es abierta
     * @param int|null $excluirAsignacionId ID de asignación a excluir de la validación
     * @return void
     * @throws EntidadNoEncontradaExcepcion
     * @throws ValidacionExcepcion
     * @throws SolapamientoLaboralExcepcion
     */
    public function validarSolapamientoAsignacionCargo(
        int $episodioLaboralId,
        string $fechaInicio,
        ?string $fechaFin = null,
        ?int $excluirAsignacionId = null
    ): void {
        if ($episodioLaboralId <= 0) {
            throw new ValidacionExcepcion('Se requiere un identificador de episodio laboral válido.');
        }

        $fechaInicio = trim($fechaInicio);
        $this->validarFecha($fechaInicio, 'fecha_inicio');

        if ($fechaFin !== null) {
            $fechaFin = trim($fechaFin);
            $this->validarFecha($fechaFin, 'fecha_fin');

            if ($fechaFin < $fechaInicio) {
                throw new ValidacionExcepcion(
                    "La fecha de fin ({$fechaFin}) no puede ser anterior a la fecha de inicio ({$fechaInicio}).",
                    ['fecha_fin' => 'Fecha de fin anterior a fecha de inicio']
                );
            }
        }

        // Obtener episodio laboral para verificar sus límites temporales
        $episodio = $this->episodioRepo->buscarPorId($episodioLaboralId, false);
        if ($episodio === null) {
            throw new EntidadNoEncontradaExcepcion('EpisodioLaboral', $episodioLaboralId);
        }

        $inicioEpisodio = $episodio->obtenerFechaInicio();
        $finEpisodio = $episodio->obtenerFechaFin();

        if ($fechaInicio < $inicioEpisodio) {
            throw new SolapamientoLaboralExcepcion(
                $fechaInicio,
                $fechaFin,
                "La asignación de cargo no puede iniciar ({$fechaInicio}) antes del inicio del episodio laboral ({$inicioEpisodio})."
            );
        }

        if ($finEpisodio !== null) {
            if ($fechaInicio > $finEpisodio) {
                throw new SolapamientoLaboralExcepcion(
                    $fechaInicio,
                    $fechaFin,
                    "La asignación de cargo no puede iniciar ({$fechaInicio}) después de la finalización del episodio laboral ({$finEpisodio})."
                );
            }

            if ($fechaFin === null) {
                throw new SolapamientoLaboralExcepcion(
                    $fechaInicio,
                    null,
                    "No se puede registrar una asignación abierta o indefinida en un episodio laboral ya concluido ({$finEpisodio})."
                );
            }

            if ($fechaFin > $finEpisodio) {
                throw new SolapamientoLaboralExcepcion(
                    $fechaInicio,
                    $fechaFin,
                    "La asignación de cargo no puede finalizar ({$fechaFin}) después del cese del episodio laboral ({$finEpisodio})."
                );
            }
        }

        // Consultar asignaciones existentes del episodio para verificar no solapamiento
        $sql = "SELECT id, cargo_id, fecha_inicio, fecha_fin"
            . " FROM episodios_laborales_cargos"
            . " WHERE episodio_laboral_id = :episodio_id";
        if ($this->pdo->inTransaction()) {
            $sql .= " FOR UPDATE";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':episodio_id', $episodioLaboralId, PDO::PARAM_INT);
        $stmt->execute();
        $asignaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($asignaciones as $asig) {
            $asigId = (int) $asig['id'];
            if ($excluirAsignacionId !== null && $asigId === $excluirAsignacionId) {
                continue;
            }

            $asigInicio = (string) $asig['fecha_inicio'];
            $asigFin = $asig['fecha_fin'] !== null ? (string) $asig['fecha_fin'] : null;

            $haySolapamiento = false;

            if ($fechaFin === null && $asigFin === null) {
                // Ambas abiertas
                $haySolapamiento = true;
            } elseif ($fechaFin === null && $asigFin !== null) {
                // Nueva abierta, existente cerrada
                $haySolapamiento = ($asigFin >= $fechaInicio);
            } elseif ($fechaFin !== null && $asigFin === null) {
                // Nueva cerrada, existente abierta
                $haySolapamiento = ($fechaFin >= $asigInicio);
            } else {
                // Ambas cerradas
                /** @var string $asigFin */
                $haySolapamiento = ($fechaInicio <= $asigFin && $asigInicio <= $fechaFin);
            }

            if ($haySolapamiento) {
                $rangoExistente = $asigFin !== null ? "{$asigInicio} al {$asigFin}" : "desde {$asigInicio} (abierta)";
                $rangoNuevo = $fechaFin !== null ? "{$fechaInicio} al {$fechaFin}" : "desde {$fechaInicio} (abierta)";
                throw new SolapamientoLaboralExcepcion(
                    $fechaInicio,
                    $fechaFin,
                    "La asignación ({$rangoNuevo}) entra en solapamiento temporal con la asignación ID {$asigId} ({$rangoExistente}) del mismo episodio laboral."
                );
            }
        }
    }

    /**
     * Registra el cese del vínculo laboral activo de un colaborador.
     * Cierra la asignación vigente, cierra el episodio con motivo justificado
     * y marca al colaborador como INACTIVO conservando íntegro su historial.
     *
     * @param int $colaboradorId
     * @param string $fechaCese Formato 'Y-m-d'
     * @param string $motivoCese ENUM: RENUNCIA, DESPIDO, MUTUO_ACUERDO, FIN_CONTRATO, JUBILACION, OTRO
     * @param string|null $observaciones
     * @return Colaborador
     * @throws EntidadNoEncontradaExcepcion
     * @throws ValidacionExcepcion
     * @throws Throwable
     */
    public function cesarColaborador(int $colaboradorId, string $fechaCese, string $motivoCese, ?string $observaciones = null): Colaborador
    {
        if ($colaboradorId <= 0) {
            throw new ValidacionExcepcion('Se requiere un identificador de colaborador válido.');
        }

        $fechaCese = trim($fechaCese);
        $this->validarFecha($fechaCese, 'fecha_cese');

        $motivoCese = strtoupper(trim($motivoCese));
        if (!in_array($motivoCese, self::MOTIVOS_CESE_VALIDOS, true)) {
            throw new ValidacionExcepcion(
                "El motivo de cese '{$motivoCese}' no es válido. Opciones permitidas: " . implode(', ', self::MOTIVOS_CESE_VALIDOS),
                ['motivo_cese' => 'Motivo no permitido']
            );
        }

        $this->pdo->beginTransaction();
        try {
            // Bloqueo pesimista del colaborador
            $stmt = $this->pdo->prepare('SELECT id, estado FROM colaboradores WHERE id = :id FOR UPDATE');
            $stmt->bindValue(':id', $colaboradorId, PDO::PARAM_INT);
            $stmt->execute();
            $filaColab = $stmt->fetch();

            if (!$filaColab) {
                throw new EntidadNoEncontradaExcepcion('Colaborador', $colaboradorId);
            }

            // Buscar episodio laboral activo abierto
            $episodioActivo = $this->episodioRepo->buscarEpisodioActivo($colaboradorId, true);
            if ($episodioActivo === null) {
                throw new ValidacionExcepcion('El colaborador no cuenta con un episodio laboral activo abierto para cesar.');
            }

            $episodioId = (int) $episodioActivo->obtenerId();

            // Validar que la fecha de cese no sea anterior al inicio del episodio
            if ($fechaCese < $episodioActivo->obtenerFechaInicio()) {
                throw new ValidacionExcepcion(
                    "La fecha de cese ({$fechaCese}) no puede ser anterior al inicio del vínculo laboral ({$episodioActivo->obtenerFechaInicio()}).",
                    ['fecha_cese' => 'Fecha previa al inicio del episodio']
                );
            }

            // Buscar y cerrar asignación de cargo vigente
            $asignacionVigente = $this->asignacionRepo->buscarAsignacionActiva($episodioId, false);
            if ($asignacionVigente !== null) {
                if ($fechaCese < $asignacionVigente->obtenerFechaInicio()) {
                    throw new ValidacionExcepcion(
                        "La fecha de cese ({$fechaCese}) no puede ser anterior a la asignación de cargo actual ({$asignacionVigente->obtenerFechaInicio()})."
                    );
                }
                $this->asignacionRepo->cerrarAsignacion(
                    (int) $asignacionVigente->obtenerId(),
                    $fechaCese,
                    "Cese de vínculo laboral por {$motivoCese}."
                );
            }

            // Cerrar el episodio laboral
            $this->episodioRepo->cerrarEpisodio($episodioId, $fechaCese, $motivoCese, $observaciones);

            // Actualizar estado del colaborador a INACTIVO
            $this->colaboradorRepo->actualizarEstado($colaboradorId, 'INACTIVO');

            $this->pdo->commit();

            return $this->colaboradorRepo->buscarPorId($colaboradorId, true) ?? throw new EntidadNoEncontradaExcepcion('Colaborador', $colaboradorId);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Obtiene una radiografía consolidada de la situación actual y antecedentes laborales del colaborador.
     *
     * @param int $colaboradorId
     * @return array<string, mixed>
     * @throws EntidadNoEncontradaExcepcion
     */
    public function obtenerSituacionActual(int $colaboradorId): array
    {
        $colaborador = $this->colaboradorRepo->buscarPorId($colaboradorId, true);
        if (!$colaborador) {
            throw new EntidadNoEncontradaExcepcion('Colaborador', $colaboradorId);
        }

        $episodioAbierto = $colaborador->obtenerEpisodioAbierto();
        $cargoActual = $colaborador->obtenerCargoActual();
        $episodios = $colaborador->obtenerEpisodios();

        return [
            'colaborador' => $colaborador->aArreglo(),
            'persona' => $colaborador->obtenerPersona() ? $colaborador->obtenerPersona()->aArreglo() : null,
            'tiene_vinculo_activo' => $colaborador->tieneEpisodioAbierto(),
            'episodio_activo' => $episodioAbierto ? $episodioAbierto->aArreglo() : null,
            'cargo_actual' => $cargoActual ? $cargoActual->aArreglo() : null,
            'total_episodios' => count($episodios),
            'historial_cargos_episodio_activo' => $episodioAbierto
                ? array_map(static fn(AsignacionCargo $asig): array => $asig->aArreglo(), $episodioAbierto->obtenerAsignacionesCargos())
                : [],
        ];
    }

    /**
     * Busca un colaborador por su ID con todas sus relaciones cargadas.
     *
     * @param int $id
     * @return Colaborador|null
     */
    public function buscarPorId(int $id): ?Colaborador
    {
        return $this->colaboradorRepo->buscarPorId($id, true);
    }

    /**
     * Busca un colaborador por su código interno.
     *
     * @param string $codigo
     * @return Colaborador|null
     */
    public function buscarPorCodigo(string $codigo): ?Colaborador
    {
        return $this->colaboradorRepo->buscarPorCodigo($codigo, true);
    }

    /**
     * Busca un colaborador por el ID de la persona asociada.
     *
     * @param int $personaId
     * @return Colaborador|null
     */
    public function buscarPorPersonaId(int $personaId): ?Colaborador
    {
        return $this->colaboradorRepo->buscarPorPersonaId($personaId, true);
    }

    /**
     * Valida estrictamente un formato de fecha 'Y-m-d'.
     *
     * @param string $fecha
     * @param string $campo
     * @throws ValidacionExcepcion
     */
    private function validarFecha(string $fecha, string $campo): void
    {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $fecha);
        if (!$dt || $dt->format('Y-m-d') !== $fecha) {
            throw new ValidacionExcepcion(
                "El campo '{$campo}' contiene una fecha inválida ('{$fecha}'). Formato requerido: AAAA-MM-DD.",
                [$campo => 'Formato de fecha inválido']
            );
        }
    }
}
