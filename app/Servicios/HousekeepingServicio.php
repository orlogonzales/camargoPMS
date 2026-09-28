<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

use CamargoPMS\Excepciones\ConflictoHousekeepingExcepcion;
use CamargoPMS\Excepciones\HousekeepingNoEncontradoExcepcion;
use CamargoPMS\Excepciones\UnidadNoListaExcepcion;
use CamargoPMS\Excepciones\ValidacionHousekeepingExcepcion;
use CamargoPMS\Modelos\HousekeepingChecklistItem;
use CamargoPMS\Modelos\HousekeepingDerivacionOperativa;
use CamargoPMS\Modelos\HousekeepingEstadoLimpieza;
use CamargoPMS\Modelos\HousekeepingLoteLavanderia;
use CamargoPMS\Modelos\HousekeepingLoteLinea;
use CamargoPMS\Modelos\HousekeepingTarea;
use CamargoPMS\Repositorios\HousekeepingRepositorio;
use PDO;
use Throwable;

/**
 * Servicio de Dominio para Housekeeping, Pisos, Inspección y Control de Lencería.
 * HOUSEKEEPING-1 / D-083.
 */
class HousekeepingServicio
{
    private PDO $pdo;

    public function __construct(
        private HousekeepingRepositorio $housekeepingRepo,
        private AuditoriaServicio $auditoriaServicio,
        private ?InventarioServicio $inventarioServicio = null,
        private ?MantenimientoServicio $mantenimientoServicio = null,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? $this->housekeepingRepo->obtenerPdo();
    }

    public function obtenerRepositorio(): HousekeepingRepositorio
    {
        return $this->housekeepingRepo;
    }

    /**
     * Evalúa si una habitación cumple estrictamente las condiciones derivadas para check-in (D-083).
     * Condición 'VR' (Vacant Ready):
     * 1. Unidad comercialmente ACTIVO.
     * 2. Desocupada (sin estadía activa).
     * 3. Higiene: LIMPIA_INSPECCIONADA estrictamente (cero bypass por guardar maletas o limpieza pendiente).
     * 4. Sin bloqueos u órdenes de mantenimiento que afecten disponibilidad.
     */
    public function puedeCheckIn(int $unidadId): bool
    {
        if ($unidadId <= 0) {
            return false;
        }

        // 1. Estado comercial y existencia de unidad
        $stmtU = $this->pdo->prepare('SELECT id, estado FROM unidades WHERE id = :uid LIMIT 1');
        $stmtU->execute(['uid' => $unidadId]);
        $u = $stmtU->fetch(PDO::FETCH_ASSOC);
        if (!$u || $u['estado'] !== 'ACTIVO') {
            return false;
        }

        // 2. Ocupación: verificar estadías activas
        $stmtEst = $this->pdo->prepare(
            'SELECT COUNT(*) FROM estadias WHERE unidad_id = :uid AND estado = "EN_CURSO"'
        );
        $stmtEst->execute(['uid' => $unidadId]);
        if ((int) $stmtEst->fetchColumn() > 0) {
            return false;
        }

        // 3. Mantenimiento: verificar bloqueos activos
        $stmtMto = $this->pdo->prepare(
            'SELECT COUNT(*) FROM mantenimiento_ordenes
             WHERE unidad_id = :uid AND estado IN ("PROGRAMADA", "EN_PROCESO") AND requiere_bloqueo = 1'
        );
        $stmtMto->execute(['uid' => $unidadId]);
        if ((int) $stmtMto->fetchColumn() > 0) {
            return false;
        }

        // 4. Limpieza física: debe ser LIMPIA_INSPECCIONADA
        $limpieza = $this->housekeepingRepo->obtenerLimpiezaUnidad($unidadId);
        if (!$limpieza) {
            return false;
        }

        return $limpieza['estado_limpieza'] === HousekeepingEstadoLimpieza::ESTADO_LIMPIA_INSPECCIONADA;
    }

    /**
     * Valida que una unidad esté apta para check-in o lanza UnidadNoListaExcepcion.
     */
    public function validarAptaParaCheckin(int $unidadId): void
    {
        if ($unidadId <= 0) {
            throw new ValidacionHousekeepingExcepcion('El identificador de unidad es obligatorio.');
        }

        // Obtener estado detallado para mensaje explicativo
        $stmtU = $this->pdo->prepare('SELECT codigo, estado FROM unidades WHERE id = :uid LIMIT 1');
        $stmtU->execute(['uid' => $unidadId]);
        $u = $stmtU->fetch(PDO::FETCH_ASSOC);
        $num = $u['codigo'] ?? (string) $unidadId;

        $limpieza = $this->housekeepingRepo->obtenerLimpiezaUnidad($unidadId);
        $estadoLimpieza = $limpieza['estado_limpieza'] ?? 'SIN_REGISTRO';

        $stmtEst = $this->pdo->prepare('SELECT codigo FROM estadias WHERE unidad_id = :uid AND estado = "EN_CURSO" LIMIT 1');
        $stmtEst->execute(['uid' => $unidadId]);
        $estadiaActiva = $stmtEst->fetchColumn();

        $stmtMto = $this->pdo->prepare('SELECT codigo FROM mantenimiento_ordenes WHERE unidad_id = :uid AND estado IN ("PROGRAMADA", "EN_PROCESO") AND requiere_bloqueo = 1 LIMIT 1');
        $stmtMto->execute(['uid' => $unidadId]);
        $mtoActivo = $stmtMto->fetchColumn();

        if ($u && $u['estado'] !== 'ACTIVO') {
            throw new UnidadNoListaExcepcion("La habitación {$num} no está disponible: estado comercial '{$u['estado']}'.");
        }

        if ($mtoActivo) {
            throw new UnidadNoListaExcepcion("La habitación {$num} no está disponible para check-in: presenta bloqueo por orden de mantenimiento {$mtoActivo} (OOO - Out of Order).");
        }

        if ($estadiaActiva) {
            throw new UnidadNoListaExcepcion("La habitación {$num} no está disponible: se encuentra actualmente ocupada por la estadía {$estadiaActiva}.");
        }

        if ($estadoLimpieza !== HousekeepingEstadoLimpieza::ESTADO_LIMPIA_INSPECCIONADA) {
            throw new UnidadNoListaExcepcion(
                "La habitación {$num} no está lista para check-in. Su condición de higiene actual es '{$estadoLimpieza}'. Se requiere que la habitación esté en estado 'LIMPIA_INSPECCIONADA' (VR - Vacant Ready)."
            );
        }
    }

    /**
     * Marca una unidad como SUCIA tras un check-out y crea atómicamente la orden operativa de SALIDA (D-083).
     * Idempotencia garantizada: máximo 1 tarea activa de salida por estadía.
     */
    public function marcarSuciaPorCheckout(
        int $unidadId,
        int $estadiaId,
        int $actorId,
        ?string $observaciones = null
    ): HousekeepingTarea {
        $debeCerrarTx = !$this->pdo->inTransaction();
        if ($debeCerrarTx) {
            $this->pdo->beginTransaction();
        }

        try {
            // 1. Obtener propiedad de la unidad
            $stmtProp = $this->pdo->prepare('SELECT propiedad_id FROM unidades WHERE id = :uid LIMIT 1');
            $stmtProp->execute(['uid' => $unidadId]);
            $propiedadId = (int) $stmtProp->fetchColumn();
            if ($propiedadId <= 0) {
                throw new ValidacionHousekeepingExcepcion("La unidad ID {$unidadId} no tiene una propiedad asignada.");
            }

            // 2. Verificar si ya existe una tarea activa de salida para esta estadía (Idempotencia)
            $tareaExistente = $this->housekeepingRepo->obtenerTareaSalidaActivaPorEstadia($estadiaId);
            if ($tareaExistente) {
                // Actualizar estado de limpieza a SUCIA asegurando vínculo
                $this->housekeepingRepo->asegurarRegistroLimpieza($unidadId, HousekeepingEstadoLimpieza::ESTADO_SUCIA);
                $this->housekeepingRepo->actualizarEstadoLimpieza(
                    $unidadId,
                    HousekeepingEstadoLimpieza::ESTADO_SUCIA,
                    (int) $tareaExistente['id'],
                    $actorId,
                    $observaciones
                );

                if ($debeCerrarTx) {
                    $this->pdo->commit();
                }

                $chk = $this->housekeepingRepo->obtenerChecklistTarea((int) $tareaExistente['id']);
                $tareaExistente['checklist'] = $chk;
                return HousekeepingTarea::desdeArreglo($tareaExistente);
            }

            // 3. Generar código único HK-YYYYMMDD-XXXX
            $codigo = $this->housekeepingRepo->generarCodigoTarea();

            // 4. Crear tarea de SALIDA
            $tareaId = $this->housekeepingRepo->crearTarea([
                'codigo' => $codigo,
                'propiedad_id' => $propiedadId,
                'unidad_id' => $unidadId,
                'estadia_id' => $estadiaId,
                'tipo_tarea' => HousekeepingTarea::TIPO_SALIDA,
                'prioridad' => HousekeepingTarea::PRIORIDAD_ALTA,
                'estado' => HousekeepingTarea::ESTADO_PENDIENTE,
                'fecha_programada' => date('Y-m-d'),
                'notas_operario' => $observaciones ? "Check-out: {$observaciones}" : 'Check-out de huésped completado',
                'creado_por_actor_id' => $actorId,
            ]);

            // 5. Cargar e inmutabilizar el checklist estándar de inspección
            $plantilla = $this->housekeepingRepo->obtenerPlantillaActiva(HousekeepingTarea::TIPO_SALIDA);
            if ($plantilla) {
                $items = $this->housekeepingRepo->obtenerItemsPlantilla((int) $plantilla['id']);
                $this->housekeepingRepo->guardarSnapshotChecklist($tareaId, $items);
            }

            // 6. Actualizar registro 1:1 de limpieza de la unidad
            $this->housekeepingRepo->asegurarRegistroLimpieza($unidadId, HousekeepingEstadoLimpieza::ESTADO_SUCIA);
            $this->housekeepingRepo->actualizarEstadoLimpieza(
                $unidadId,
                HousekeepingEstadoLimpieza::ESTADO_SUCIA,
                $tareaId,
                $actorId,
                $observaciones
            );

            // 7. Historial append-only
            $this->housekeepingRepo->registrarHistorialTarea(
                $tareaId,
                null,
                HousekeepingTarea::ESTADO_PENDIENTE,
                $actorId,
                "Tarea de salida generada automáticamente por check-out de estadía ID {$estadiaId}"
            );

            // 8. Auditoría transversal D-061
            $this->auditoriaServicio->registrar(
                accion: 'HOUSEKEEPING_TAREA_CREADA',
                modulo: 'housekeeping',
                entidad: 'housekeeping_tareas',
                entidadId: (string) $tareaId,
                descripcion: "Generada tarea de limpieza por salida '{$codigo}' para unidad ID {$unidadId}.",
                valoresAnteriores: null,
                valoresNuevos: [
                    'codigo' => $codigo,
                    'tipo_tarea' => HousekeepingTarea::TIPO_SALIDA,
                    'estadia_id' => $estadiaId,
                    'estado' => HousekeepingTarea::ESTADO_PENDIENTE,
                ],
                contexto: null,
                actor: $actorId,
                pdoTransaccional: $this->pdo
            );

            if ($debeCerrarTx) {
                $this->pdo->commit();
            }

            $tareaCreada = $this->housekeepingRepo->obtenerTareaPorId($tareaId);
            $tareaCreada['checklist'] = $this->housekeepingRepo->obtenerChecklistTarea($tareaId);
            return HousekeepingTarea::desdeArreglo($tareaCreada);
        } catch (Throwable $e) {
            if ($debeCerrarTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Crea manualmente una tarea operativa de housekeeping (D-083).
     */
    public function crearTareaManual(array $datos, int $actorId): HousekeepingTarea
    {
        $unidadId = (int) ($datos['unidad_id'] ?? 0);
        $tipoTarea = (string) ($datos['tipo_tarea'] ?? HousekeepingTarea::TIPO_SALIDA);
        $prioridad = (string) ($datos['prioridad'] ?? HousekeepingTarea::PRIORIDAD_MEDIA);
        $fechaProgramada = (string) ($datos['fecha_programada'] ?? date('Y-m-d'));
        $camareraId = !empty($datos['camarera_colaborador_id']) ? (int) $datos['camarera_colaborador_id'] : null;
        $supervisorId = !empty($datos['supervisor_colaborador_id']) ? (int) $datos['supervisor_colaborador_id'] : null;
        $notas = $datos['notas_operario'] ?? null;

        if ($unidadId <= 0) {
            throw new ValidacionHousekeepingExcepcion('La unidad física es obligatoria.');
        }

        $stmtProp = $this->pdo->prepare('SELECT propiedad_id FROM unidades WHERE id = :uid LIMIT 1');
        $stmtProp->execute(['uid' => $unidadId]);
        $propiedadId = (int) $stmtProp->fetchColumn();
        if ($propiedadId <= 0) {
            throw new ValidacionHousekeepingExcepcion("La unidad ID {$unidadId} no existe o no tiene propiedad asignada.");
        }

        $debeCerrarTx = !$this->pdo->inTransaction();
        if ($debeCerrarTx) {
            $this->pdo->beginTransaction();
        }

        try {
            $codigo = $this->housekeepingRepo->generarCodigoTarea();
            $estadoInicial = $camareraId ? HousekeepingTarea::ESTADO_ASIGNADA : HousekeepingTarea::ESTADO_PENDIENTE;

            $tareaId = $this->housekeepingRepo->crearTarea([
                'codigo' => $codigo,
                'propiedad_id' => $propiedadId,
                'unidad_id' => $unidadId,
                'estadia_id' => !empty($datos['estadia_id']) ? (int) $datos['estadia_id'] : null,
                'tipo_tarea' => $tipoTarea,
                'prioridad' => $prioridad,
                'estado' => $estadoInicial,
                'camarera_colaborador_id' => $camareraId,
                'supervisor_colaborador_id' => $supervisorId,
                'fecha_programada' => $fechaProgramada,
                'notas_operario' => $notas,
                'creado_por_actor_id' => $actorId,
            ]);

            // Cargar checklist
            $plantilla = $this->housekeepingRepo->obtenerPlantillaActiva($tipoTarea);
            if ($plantilla) {
                $items = $this->housekeepingRepo->obtenerItemsPlantilla((int) $plantilla['id']);
                $this->housekeepingRepo->guardarSnapshotChecklist($tareaId, $items);
            }

            // Actualizar estado 1:1 de unidad
            $this->housekeepingRepo->asegurarRegistroLimpieza($unidadId);
            $nuevoEstadoUnidad = ($tipoTarea === HousekeepingTarea::TIPO_RETOQUE)
                ? HousekeepingEstadoLimpieza::ESTADO_RETOQUE_REQUERIDO
                : HousekeepingEstadoLimpieza::ESTADO_SUCIA;

            $this->housekeepingRepo->actualizarEstadoLimpieza(
                $unidadId,
                $nuevoEstadoUnidad,
                $tareaId,
                $actorId,
                $notas
            );

            // Historial append-only
            $this->housekeepingRepo->registrarHistorialTarea(
                $tareaId,
                null,
                $estadoInicial,
                $actorId,
                "Tarea manual creada con prioridad {$prioridad}"
            );

            // Auditoría transversal D-061
            $this->auditoriaServicio->registrar(
                accion: 'HOUSEKEEPING_TAREA_CREADA',
                modulo: 'housekeeping',
                entidad: 'housekeeping_tareas',
                entidadId: (string) $tareaId,
                descripcion: "Tarea manual '{$codigo}' creada para unidad ID {$unidadId}.",
                valoresAnteriores: null,
                valoresNuevos: [
                    'codigo' => $codigo,
                    'tipo_tarea' => $tipoTarea,
                    'estado' => $estadoInicial,
                ],
                contexto: null,
                actor: $actorId,
                pdoTransaccional: $this->pdo
            );

            if ($debeCerrarTx) {
                $this->pdo->commit();
            }

            $tarea = $this->housekeepingRepo->obtenerTareaPorId($tareaId);
            $tarea['checklist'] = $this->housekeepingRepo->obtenerChecklistTarea($tareaId);
            return HousekeepingTarea::desdeArreglo($tarea);
        } catch (Throwable $e) {
            if ($debeCerrarTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Asigna camarera y/o supervisor a una tarea de housekeeping.
     */
    public function asignarTarea(
        int $tareaId,
        int $camareraColaboradorId,
        ?int $supervisorColaboradorId,
        int $actorId
    ): HousekeepingTarea {
        $tarea = $this->housekeepingRepo->obtenerTareaPorId($tareaId);
        if (!$tarea) {
            throw new HousekeepingNoEncontradoExcepcion("La tarea de housekeeping ID {$tareaId} no existe.");
        }

        if (in_array($tarea['estado'], [HousekeepingTarea::ESTADO_COMPLETADA, HousekeepingTarea::ESTADO_CANCELADA], true)) {
            throw new ConflictoHousekeepingExcepcion("No se puede asignar una tarea en estado '{$tarea['estado']}'.");
        }

        $debeCerrarTx = !$this->pdo->inTransaction();
        if ($debeCerrarTx) {
            $this->pdo->beginTransaction();
        }

        try {
            $nuevoEstado = ($tarea['estado'] === HousekeepingTarea::ESTADO_PENDIENTE)
                ? HousekeepingTarea::ESTADO_ASIGNADA
                : $tarea['estado'];

            $this->housekeepingRepo->actualizarEstadoTarea($tareaId, $nuevoEstado, [
                'camarera_colaborador_id' => $camareraColaboradorId,
                'supervisor_colaborador_id' => $supervisorColaboradorId,
            ]);

            $this->housekeepingRepo->registrarHistorialTarea(
                $tareaId,
                $tarea['estado'],
                $nuevoEstado,
                $actorId,
                "Asignada camarera ID {$camareraColaboradorId}"
            );

            if ($debeCerrarTx) {
                $this->pdo->commit();
            }

            $tareaActualizada = $this->housekeepingRepo->obtenerTareaPorId($tareaId);
            $tareaActualizada['checklist'] = $this->housekeepingRepo->obtenerChecklistTarea($tareaId);
            return HousekeepingTarea::desdeArreglo($tareaActualizada);
        } catch (Throwable $e) {
            if ($debeCerrarTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Inicia los trabajos de limpieza física en la unidad (pasa a EN_LIMPIEZA).
     */
    public function iniciarLimpieza(int $tareaId, int $actorId): HousekeepingTarea
    {
        $tarea = $this->housekeepingRepo->obtenerTareaPorId($tareaId);
        if (!$tarea) {
            throw new HousekeepingNoEncontradoExcepcion("La tarea ID {$tareaId} no existe.");
        }

        if (!in_array($tarea['estado'], [HousekeepingTarea::ESTADO_PENDIENTE, HousekeepingTarea::ESTADO_ASIGNADA, HousekeepingTarea::ESTADO_RECHAZADA], true)) {
            throw new ConflictoHousekeepingExcepcion("No se puede iniciar limpieza en una tarea en estado '{$tarea['estado']}'.");
        }

        $debeCerrarTx = !$this->pdo->inTransaction();
        if ($debeCerrarTx) {
            $this->pdo->beginTransaction();
        }

        try {
            $horaInicio = date('Y-m-d H:i:s');
            $this->housekeepingRepo->actualizarEstadoTarea($tareaId, HousekeepingTarea::ESTADO_EN_PROCESO, [
                'iniciado_en' => $horaInicio,
            ]);

            // Actualizar 1:1 de unidad a EN_LIMPIEZA
            $this->housekeepingRepo->actualizarEstadoLimpieza(
                (int) $tarea['unidad_id'],
                HousekeepingEstadoLimpieza::ESTADO_EN_LIMPIEZA,
                $tareaId
            );

            $this->housekeepingRepo->registrarHistorialTarea(
                $tareaId,
                $tarea['estado'],
                HousekeepingTarea::ESTADO_EN_PROCESO,
                $actorId,
                'Inicio de labores de limpieza física'
            );

            if ($debeCerrarTx) {
                $this->pdo->commit();
            }

            $tareaActualizada = $this->housekeepingRepo->obtenerTareaPorId($tareaId);
            $tareaActualizada['checklist'] = $this->housekeepingRepo->obtenerChecklistTarea($tareaId);
            return HousekeepingTarea::desdeArreglo($tareaActualizada);
        } catch (Throwable $e) {
            if ($debeCerrarTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Finaliza la limpieza por parte de la operaria y la envía a inspección de supervisión (D-083).
     * Procesa consumos de amenities registrando salida atómica en Kardex sin saldo negativo.
     */
    public function finalizarLimpieza(int $tareaId, array $datos, int $actorId): HousekeepingTarea
    {
        $tarea = $this->housekeepingRepo->obtenerTareaPorId($tareaId);
        if (!$tarea) {
            throw new HousekeepingNoEncontradoExcepcion("La tarea ID {$tareaId} no existe.");
        }

        if (!in_array($tarea['estado'], [HousekeepingTarea::ESTADO_EN_PROCESO, HousekeepingTarea::ESTADO_ASIGNADA], true)) {
            throw new ConflictoHousekeepingExcepcion("La tarea debe estar en proceso para finalizar limpieza (estado actual: '{$tarea['estado']}').");
        }

        $debeCerrarTx = !$this->pdo->inTransaction();
        if ($debeCerrarTx) {
            $this->pdo->beginTransaction();
        }

        try {
            $horaFin = date('Y-m-d H:i:s');
            $notas = $datos['notas_operario'] ?? $tarea['notas_operario'];
            $condicion = $datos['condicion_operacional'] ?? HousekeepingTarea::CONDICION_NINGUNA;

            // Procesar consumos de amenities si fueron reportados
            $consumos = $datos['consumos'] ?? [];
            if (!empty($consumos) && is_array($consumos)) {
                foreach ($consumos as $c) {
                    $articuloId = (int) ($c['articulo_id'] ?? 0);
                    $almacenId = (int) ($c['almacen_origen_id'] ?? 0);
                    $cantidad = (string) ($c['cantidad'] ?? '0');

                    if ($articuloId > 0 && $almacenId > 0 && (float) $cantidad > 0) {
                        // Utilizar InventarioServicio para debitar Kardex si está disponible
                        $movimientoId = 0;
                        if ($this->inventarioServicio !== null) {
                            $mov = $this->inventarioServicio->registrarSalidaConsumo(
                                $articuloId,
                                $almacenId,
                                $cantidad,
                                $actorId,
                                "Consumo de amenities en limpieza tarea {$tarea['codigo']}"
                            );
                            $movimientoId = (int) $mov->obtenerId();
                        } else {
                            // Inserción directa segura en inventario_movimientos
                            $stmtMov = $this->pdo->prepare(
                                'INSERT INTO inventario_movimientos (
                                    codigo, articulo_id, ubicacion_id, tipo_movimiento, cantidad,
                                    costo_unitario_historico, costo_total_historico, motivo, creado_por_actor_id, creado_en
                                ) VALUES (
                                    :codigo, :articulo_id, :almacen_id, "SALIDA_CONSUMO", :cantidad,
                                    0.0000, 0.00, :motivo, :actor_id, NOW()
                                )'
                            );
                            $codigoMov = 'MOV-HK-' . date('YmdHis') . '-' . rand(100, 999);
                            $stmtMov->execute([
                                'codigo' => $codigoMov,
                                'articulo_id' => $articuloId,
                                'almacen_id' => $almacenId,
                                'cantidad' => $cantidad,
                                'actor_id' => $actorId,
                                'motivo' => "Consumo amenities tarea {$tarea['codigo']}",
                            ]);
                            $movimientoId = (int) $this->pdo->lastInsertId();

                            // Actualizar existencia
                            $stmtEx = $this->pdo->prepare(
                                'UPDATE inventario_existencias
                                 SET cantidad_actual = cantidad_actual - :cant_resta, actualizado_en = NOW()
                                 WHERE articulo_id = :articulo_id AND ubicacion_id = :almacen_id AND cantidad_actual >= :cant_minima'
                            );
                            $stmtEx->execute([
                                'cant_resta' => $cantidad,
                                'cant_minima' => $cantidad,
                                'articulo_id' => $articuloId,
                                'almacen_id' => $almacenId,
                            ]);
                            if ($stmtEx->rowCount() === 0) {
                                throw new ConflictoHousekeepingExcepcion(
                                    "Stock insuficiente para consumir amenitie ID {$articuloId} en almacén ID {$almacenId}."
                                );
                            }
                        }

                        $this->housekeepingRepo->registrarConsumo($tareaId, $articuloId, $almacenId, $cantidad, $movimientoId);
                    }
                }
            }

            // Actualizar tarea a POR_INSPECCIONAR
            $this->housekeepingRepo->actualizarEstadoTarea($tareaId, HousekeepingTarea::ESTADO_POR_INSPECCIONAR, [
                'terminado_en' => $horaFin,
                'notas_operario' => $notas,
                'condicion_operacional' => $condicion,
            ]);

            // Actualizar 1:1 de unidad a LIMPIA_POR_INSPECCIONAR
            $this->housekeepingRepo->actualizarEstadoLimpieza(
                (int) $tarea['unidad_id'],
                HousekeepingEstadoLimpieza::ESTADO_LIMPIA_POR_INSPECCIONAR,
                $tareaId,
                ultimaLimpiezaEn: $horaFin
            );

            $this->housekeepingRepo->registrarHistorialTarea(
                $tareaId,
                $tarea['estado'],
                HousekeepingTarea::ESTADO_POR_INSPECCIONAR,
                $actorId,
                'Limpieza física finalizada y enviada a inspección'
            );

            if ($debeCerrarTx) {
                $this->pdo->commit();
            }

            $tareaActualizada = $this->housekeepingRepo->obtenerTareaPorId($tareaId);
            $tareaActualizada['checklist'] = $this->housekeepingRepo->obtenerChecklistTarea($tareaId);
            $tareaActualizada['consumos'] = $this->housekeepingRepo->obtenerConsumosTarea($tareaId);
            return HousekeepingTarea::desdeArreglo($tareaActualizada);
        } catch (Throwable $e) {
            if ($debeCerrarTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Inspección y calificación formal de la habitación por supervisión (D-083).
     * - Si se aprueba y todos los ítems críticos están conformes: COMPLETADA y unidad LIMPIA_INSPECCIONADA (VR).
     * - Si se rechaza: RECHAZADA y unidad RETOQUE_REQUERIDO con motivo explícito.
     */
    public function inspeccionarTarea(
        int $tareaId,
        bool $aprobada,
        array $checklistEvaluado = [],
        ?string $notasSupervisor = null,
        int $actorId = 1,
        ?int $supervisorColaboradorId = null
    ): HousekeepingTarea {
        $tarea = $this->housekeepingRepo->obtenerTareaPorId($tareaId);
        if (!$tarea) {
            throw new HousekeepingNoEncontradoExcepcion("La tarea ID {$tareaId} no existe.");
        }

        if ($tarea['estado'] !== HousekeepingTarea::ESTADO_POR_INSPECCIONAR && $tarea['estado'] !== HousekeepingTarea::ESTADO_EN_PROCESO) {
            throw new ConflictoHousekeepingExcepcion("Solo se pueden inspeccionar tareas en estado 'POR_INSPECCIONAR' o 'EN_PROCESO'.");
        }

        $debeCerrarTx = !$this->pdo->inTransaction();
        if ($debeCerrarTx) {
            $this->pdo->beginTransaction();
        }

        try {
            $horaInspeccion = date('Y-m-d H:i:s');
            $hayCriticosFallidos = false;

            // Actualizar evaluaciones individuales del checklist snapshot
            foreach ($checklistEvaluado as $item) {
                $chkId = (int) ($item['id'] ?? 0);
                $resultado = (string) ($item['resultado'] ?? HousekeepingChecklistItem::RESULTADO_CONFORME);
                $obs = $item['observacion'] ?? null;

                if ($chkId > 0) {
                    $this->housekeepingRepo->actualizarResultadoChecklistItem($chkId, $resultado, $obs, $actorId);
                }
            }

            // Verificar si algún ítem crítico quedó NO_CONFORME
            $itemsActuales = $this->housekeepingRepo->obtenerChecklistTarea($tareaId);
            foreach ($itemsActuales as $ia) {
                if (!empty($ia['es_critico_snapshot']) && $ia['resultado'] === HousekeepingChecklistItem::RESULTADO_NO_CONFORME) {
                    $hayCriticosFallidos = true;
                    break;
                }
            }

            // Si hay ítems críticos fallidos, no se puede forzar la aprobación
            if ($aprobada && $hayCriticosFallidos) {
                throw new ValidacionHousekeepingExcepcion(
                    'No es posible aprobar la inspección: existen puntos de control críticos calificados como NO_CONFORME.'
                );
            }

            $camposTarea = [
                'inspeccionado_en' => $horaInspeccion,
                'notas_supervisor' => $notasSupervisor,
            ];
            if ($supervisorColaboradorId !== null && $supervisorColaboradorId > 0) {
                $camposTarea['supervisor_colaborador_id'] = $supervisorColaboradorId;
            }

            if ($aprobada) {
                // Tarea COMPLETADA
                $this->housekeepingRepo->actualizarEstadoTarea($tareaId, HousekeepingTarea::ESTADO_COMPLETADA, $camposTarea);

                // Unidad pasa a LIMPIA_INSPECCIONADA (VR habilitada)
                $this->housekeepingRepo->actualizarEstadoLimpieza(
                    (int) $tarea['unidad_id'],
                    HousekeepingEstadoLimpieza::ESTADO_LIMPIA_INSPECCIONADA,
                    tareaActivaId: 0, // Libera la tarea activa
                    actorId: $actorId,
                    observaciones: $notasSupervisor,
                    ultimaInspeccionEn: $horaInspeccion
                );

                $this->housekeepingRepo->registrarHistorialTarea(
                    $tareaId,
                    $tarea['estado'],
                    HousekeepingTarea::ESTADO_COMPLETADA,
                    $actorId,
                    'Inspección aprobada satisfactoriamente. Habitación lista (VR).'
                );
            } else {
                // Tarea RECHAZADA (reproceso)
                $camposTarea['notas_supervisor'] = $notasSupervisor ?? 'Rechazada en inspección';
                $this->housekeepingRepo->actualizarEstadoTarea($tareaId, HousekeepingTarea::ESTADO_RECHAZADA, $camposTarea);

                // Unidad pasa a RETOQUE_REQUERIDO
                $this->housekeepingRepo->actualizarEstadoLimpieza(
                    (int) $tarea['unidad_id'],
                    HousekeepingEstadoLimpieza::ESTADO_RETOQUE_REQUERIDO,
                    tareaActivaId: $tareaId, // Mantiene la tarea para reproceso
                    actorId: $actorId,
                    observaciones: $notasSupervisor ?? 'Rechazada en inspección. Requiere retoque.',
                    ultimaInspeccionEn: $horaInspeccion
                );

                $this->housekeepingRepo->registrarHistorialTarea(
                    $tareaId,
                    $tarea['estado'],
                    HousekeepingTarea::ESTADO_RECHAZADA,
                    $actorId,
                    "Inspección rechazada: " . ($notasSupervisor ?? 'Observaciones pendientes')
                );
            }

            // Auditoría transversal D-061
            $this->auditoriaServicio->registrar(
                accion: $aprobada ? 'HOUSEKEEPING_INSPECCION_APROBADA' : 'HOUSEKEEPING_INSPECCION_RECHAZADA',
                modulo: 'housekeeping',
                entidad: 'housekeeping_tareas',
                entidadId: (string) $tareaId,
                descripcion: $aprobada
                    ? "Inspección de tarea '{$tarea['codigo']}' APROBADA para unidad ID {$tarea['unidad_id']}."
                    : "Inspección de tarea '{$tarea['codigo']}' RECHAZADA para unidad ID {$tarea['unidad_id']}.",
                valoresAnteriores: ['estado' => $tarea['estado']],
                valoresNuevos: [
                    'estado' => $aprobada ? HousekeepingTarea::ESTADO_COMPLETADA : HousekeepingTarea::ESTADO_RECHAZADA,
                    'notas_supervisor' => $notasSupervisor,
                ],
                contexto: null,
                actor: $actorId,
                pdoTransaccional: $this->pdo
            );

            if ($debeCerrarTx) {
                $this->pdo->commit();
            }

            $tareaActualizada = $this->housekeepingRepo->obtenerTareaPorId($tareaId);
            $tareaActualizada['checklist'] = $this->housekeepingRepo->obtenerChecklistTarea($tareaId);
            return HousekeepingTarea::desdeArreglo($tareaActualizada);
        } catch (Throwable $e) {
            if ($debeCerrarTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Reporta formalmente una incidencia / desperfecto físico a Mantenimiento (D-083).
     */
    public function reportarDesperfectoMantenimiento(
        int $tareaId,
        array $datosIncidencia,
        int $actorId
    ): array {
        $tarea = $this->housekeepingRepo->obtenerTareaPorId($tareaId);
        if (!$tarea) {
            throw new HousekeepingNoEncontradoExcepcion("La tarea ID {$tareaId} no existe.");
        }

        $titulo = trim((string) ($datosIncidencia['titulo'] ?? 'Desperfecto detectado en Housekeeping'));
        $descripcion = trim((string) ($datosIncidencia['descripcion'] ?? ''));
        $prioridad = (string) ($datosIncidencia['prioridad'] ?? 'MEDIA');
        $afectaDisponibilidad = !empty($datosIncidencia['afecta_disponibilidad']);

        if ($descripcion === '') {
            throw new ValidacionHousekeepingExcepcion('La descripción del desperfecto físico es obligatoria.');
        }

        $debeCerrarTx = !$this->pdo->inTransaction();
        if ($debeCerrarTx) {
            $this->pdo->beginTransaction();
        }

        try {
            $incidenciaId = 0;
            if ($this->mantenimientoServicio !== null) {
                $inc = $this->mantenimientoServicio->reportarIncidencia([
                    'propiedad_id' => (int) $tarea['propiedad_id'],
                    'unidad_id' => (int) $tarea['unidad_id'],
                    'titulo' => $titulo,
                    'descripcion' => "Reportado desde Housekeeping (Tarea {$tarea['codigo']}): {$descripcion}",
                    'prioridad' => $prioridad,
                    'origen_reporte' => 'HOUSEKEEPING',
                ], $actorId);
                $incidenciaId = (int) $inc->obtenerId();
            } else {
                $codigoInc = 'INC-' . date('Ymd') . '-' . str_pad((string) rand(1, 9999), 4, '0', STR_PAD_LEFT);
                $severidad = in_array(strtoupper($prioridad), ['BAJA', 'MEDIA', 'ALTA', 'CRITICA'], true)
                    ? strtoupper($prioridad)
                    : 'MEDIA';

                $personaId = 1;
                $stmtPersona = $this->pdo->query('SELECT id FROM personas LIMIT 1');
                if ($rowP = $stmtPersona->fetch(PDO::FETCH_ASSOC)) {
                    $personaId = (int) $rowP['id'];
                }

                $stmtInc = $this->pdo->prepare(
                    'INSERT INTO mantenimiento_incidencias (
                        codigo, propiedad_id, unidad_id, reportado_por_persona_id, categoria,
                        severidad, titulo, descripcion, estado, creado_por_actor_id, creado_en
                    ) VALUES (
                        :codigo, :propiedad_id, :unidad_id, :persona_id, "OTRO",
                        :severidad, :titulo, :descripcion, "REPORTADA", :actor_id, NOW()
                    )'
                );
                $stmtInc->execute([
                    'codigo' => $codigoInc,
                    'propiedad_id' => (int) $tarea['propiedad_id'],
                    'unidad_id' => (int) $tarea['unidad_id'],
                    'persona_id' => $personaId,
                    'severidad' => $severidad,
                    'titulo' => $titulo,
                    'descripcion' => "Reportado desde Housekeeping (Tarea {$tarea['codigo']}): {$descripcion}",
                    'actor_id' => $actorId,
                ]);
                $incidenciaId = (int) $this->pdo->lastInsertId();
            }

            // Anotar en la tarea
            $notaAdicional = "[Incidencia Mantenimiento #{$incidenciaId}]: {$titulo}";
            $notasActuales = $tarea['notas_operario'] ? $tarea['notas_operario'] . "\n" . $notaAdicional : $notaAdicional;
            $this->housekeepingRepo->actualizarEstadoTarea($tareaId, $tarea['estado'], [
                'notas_operario' => $notasActuales,
            ]);

            // Auditoría transversal D-061
            $this->auditoriaServicio->registrar(
                accion: 'HOUSEKEEPING_DESPERFECTO_REPORTADO',
                modulo: 'housekeeping',
                entidad: 'housekeeping_tareas',
                entidadId: (string) $tareaId,
                descripcion: "Reportado desperfecto a mantenimiento (Incidencia #{$incidenciaId}) para unidad ID {$tarea['unidad_id']}.",
                valoresAnteriores: null,
                valoresNuevos: [
                    'incidencia_id' => $incidenciaId,
                    'titulo' => $titulo,
                ],
                contexto: null,
                actor: $actorId,
                pdoTransaccional: $this->pdo
            );

            if ($debeCerrarTx) {
                $this->pdo->commit();
            }

            return [
                'incidencia_id' => $incidenciaId,
                'tarea_id' => $tareaId,
                'unidad_id' => (int) $tarea['unidad_id'],
                'mensaje' => 'Incidencia de mantenimiento levantada exitosamente.',
            ];
        } catch (Throwable $e) {
            if ($debeCerrarTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Despacha un lote de lencería textil hacia lavandería (CUSTODIA_EXTERNA) con Kardex de dos patas (D-083).
     */
    public function despacharLoteLavanderia(array $datos, int $actorId): HousekeepingLoteLavanderia
    {
        $propiedadId = (int) ($datos['propiedad_id'] ?? 0);
        $almacenOrigenId = (int) ($datos['almacen_origen_id'] ?? 0);
        $ubicacionLavanderiaId = (int) ($datos['ubicacion_lavanderia_id'] ?? 0);
        $fechaDespacho = (string) ($datos['fecha_despacho'] ?? date('Y-m-d'));
        $lineas = $datos['lineas'] ?? [];

        if ($propiedadId <= 0 || $almacenOrigenId <= 0 || $ubicacionLavanderiaId <= 0) {
            throw new ValidacionHousekeepingExcepcion('Propiedad, almacén de origen y ubicación de lavandería son obligatorios.');
        }

        if (empty($lineas) || !is_array($lineas)) {
            throw new ValidacionHousekeepingExcepcion('Debe incluir al menos una prenda textil en el despacho de lavandería.');
        }

        $debeCerrarTx = !$this->pdo->inTransaction();
        if ($debeCerrarTx) {
            $this->pdo->beginTransaction();
        }

        try {
            // Traslado en Kardex de cada prenda desde origen a lavandería (dos patas TRASLADO_SALIDA y TRASLADO_ENTRADA)
            foreach ($lineas as $l) {
                $articuloId = (int) ($l['articulo_id'] ?? 0);
                $cant = (string) ($l['cantidad_enviada'] ?? $l['cantidad'] ?? '0');

                if ($articuloId <= 0 || (float) $cant <= 0) {
                    throw new ValidacionHousekeepingExcepcion('La cantidad enviada debe ser estrictamente positiva.');
                }

                if ($this->inventarioServicio !== null) {
                    $this->inventarioServicio->registrarTraslado(
                        $articuloId,
                        $almacenOrigenId,
                        $ubicacionLavanderiaId,
                        $cant,
                        $actorId,
                        "Envío de lencería a lavandería"
                    );
                } else {
                    // Traslado directo seguro
                    $stmtDebito = $this->pdo->prepare(
                        'UPDATE inventario_existencias
                         SET cantidad_actual = cantidad_actual - :cant_resta, actualizado_en = NOW()
                         WHERE articulo_id = :aid AND ubicacion_id = :uid AND cantidad_actual >= :cant_minima'
                    );
                    $stmtDebito->execute([
                        'cant_resta' => $cant,
                        'cant_minima' => $cant,
                        'aid' => $articuloId,
                        'uid' => $almacenOrigenId
                    ]);
                    if ($stmtDebito->rowCount() === 0) {
                        throw new ConflictoHousekeepingExcepcion("Stock insuficiente de lencería ID {$articuloId} en almacén de origen.");
                    }

                    // Acreditar en lavandería
                    $stmtCred = $this->pdo->prepare(
                        'INSERT INTO inventario_existencias (articulo_id, ubicacion_id, cantidad_actual, actualizado_en)
                         VALUES (:aid, :uid, :cant, NOW())
                         ON DUPLICATE KEY UPDATE cantidad_actual = cantidad_actual + VALUES(cantidad_actual), actualizado_en = NOW()'
                    );
                    $stmtCred->execute(['cant' => $cant, 'aid' => $articuloId, 'uid' => $ubicacionLavanderiaId]);
                }
            }

            // Generar folio de lavandería LAV-YYYYMMDD-XXXX
            $codigoLote = $this->housekeepingRepo->generarCodigoLote();

            $loteId = $this->housekeepingRepo->crearLoteLavanderia([
                'codigo' => $codigoLote,
                'propiedad_id' => $propiedadId,
                'almacen_origen_id' => $almacenOrigenId,
                'ubicacion_lavanderia_id' => $ubicacionLavanderiaId,
                'fecha_despacho' => $fechaDespacho,
                'fecha_retorno_estimada' => $datos['fecha_retorno_estimada'] ?? null,
                'notas_despacho' => $datos['notas_despacho'] ?? null,
                'creado_por_actor_id' => $actorId,
            ], $lineas);

            // Auditoría transversal D-061
            $this->auditoriaServicio->registrar(
                accion: 'HOUSEKEEPING_LAVANDERIA_DESPACHO',
                modulo: 'housekeeping',
                entidad: 'housekeeping_lotes_lavanderia',
                entidadId: (string) $loteId,
                descripcion: "Despachado lote de lavandería '{$codigoLote}' con " . count($lineas) . " tipos de prendas.",
                valoresAnteriores: null,
                valoresNuevos: [
                    'codigo' => $codigoLote,
                    'lineas_total' => count($lineas),
                ],
                contexto: null,
                actor: $actorId,
                pdoTransaccional: $this->pdo
            );

            if ($debeCerrarTx) {
                $this->pdo->commit();
            }

            $lote = $this->housekeepingRepo->obtenerLotePorId($loteId);
            $lote['lineas'] = $this->housekeepingRepo->obtenerLineasLote($loteId);
            return HousekeepingLoteLavanderia::desdeArreglo($lote);
        } catch (Throwable $e) {
            if ($debeCerrarTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Registra el retorno de lencería limpia y mermas con preservación estricta de diferencias (D-083).
     */
    public function recibirLoteLavanderia(
        int $loteId,
        array $recepciones,
        ?string $notasRetorno,
        int $actorId
    ): HousekeepingLoteLavanderia {
        $lote = $this->housekeepingRepo->obtenerLotePorId($loteId);
        if (!$lote) {
            throw new HousekeepingNoEncontradoExcepcion("El lote de lavandería ID {$loteId} no existe.");
        }

        if ($lote['estado'] === HousekeepingLoteLavanderia::ESTADO_ANULADO) {
            throw new ConflictoHousekeepingExcepcion('No se puede recibir un lote de lavandería anulado.');
        }

        $debeCerrarTx = !$this->pdo->inTransaction();
        if ($debeCerrarTx) {
            $this->pdo->beginTransaction();
        }

        try {
            $lineasExistentes = $this->housekeepingRepo->obtenerLineasLote($loteId);
            $mapaLineas = [];
            foreach ($lineasExistentes as $le) {
                $mapaLineas[$le['id']] = $le;
            }

            $hayDiscrepancia = false;
            $retornoTotal = true;

            foreach ($recepciones as $rec) {
                $lineaId = (int) ($rec['linea_id'] ?? 0);
                if (!isset($mapaLineas[$lineaId])) {
                    continue;
                }

                $linea = $mapaLineas[$lineaId];
                $cantRecibida = (string) ($rec['cantidad_recibida'] ?? '0');
                $cantMerma = (string) ($rec['cantidad_baja_merma'] ?? $rec['cantidad_merma'] ?? '0');
                $obs = $rec['observaciones'] ?? null;

                $enviada = (float) $linea['cantidad_enviada'];
                $recibida = (float) $cantRecibida;
                $merma = (float) $cantMerma;

                if ($recibida + $merma > $enviada) {
                    throw new ValidacionHousekeepingExcepcion(
                        "La cantidad retornada + merma (" . ($recibida + $merma) . ") excede la cantidad enviada ({$enviada}) en la prenda ID {$linea['articulo_id']}."
                    );
                }

                // Trasladar lencería limpia recibida de vuelta al almacén de origen
                if ($recibida > 0) {
                    if ($this->inventarioServicio !== null) {
                        $this->inventarioServicio->registrarTraslado(
                            (int) $linea['articulo_id'],
                            (int) $lote['ubicacion_lavanderia_id'],
                            (int) $lote['almacen_origen_id'],
                            $cantRecibida,
                            $actorId,
                            "Retorno de lencería limpia desde lavandería lote {$lote['codigo']}"
                        );
                    } else {
                        // Kardex directo seguro
                        $stmtDebito = $this->pdo->prepare(
                            'UPDATE inventario_existencias
                             SET cantidad_actual = cantidad_actual - :cant_resta, actualizado_en = NOW()
                             WHERE articulo_id = :aid AND ubicacion_id = :uid AND cantidad_actual >= :cant_minima'
                        );
                        $stmtDebito->execute([
                            'cant_resta' => $cantRecibida,
                            'cant_minima' => $cantRecibida,
                            'aid' => $linea['articulo_id'],
                            'uid' => $lote['ubicacion_lavanderia_id']
                        ]);

                        $stmtCred = $this->pdo->prepare(
                            'INSERT INTO inventario_existencias (articulo_id, ubicacion_id, cantidad_actual, actualizado_en)
                             VALUES (:aid, :uid, :cant, NOW())
                             ON DUPLICATE KEY UPDATE cantidad_actual = cantidad_actual + VALUES(cantidad_actual), actualizado_en = NOW()'
                        );
                        $stmtCred->execute(['cant' => $cantRecibida, 'aid' => $linea['articulo_id'], 'uid' => $lote['almacen_origen_id']]);
                    }
                }

                // Dar de baja merma si existió daño irrecuperable
                if ($merma > 0) {
                    $stmtBaja = $this->pdo->prepare(
                        'UPDATE inventario_existencias
                         SET cantidad_actual = cantidad_actual - :cant_resta, actualizado_en = NOW()
                         WHERE articulo_id = :aid AND ubicacion_id = :uid AND cantidad_actual >= :cant_minima'
                    );
                    $stmtBaja->execute([
                        'cant_resta' => $cantMerma,
                        'cant_minima' => $cantMerma,
                        'aid' => $linea['articulo_id'],
                        'uid' => $lote['ubicacion_lavanderia_id']
                    ]);
                }

                // Actualizar línea de lote
                $this->housekeepingRepo->actualizarRetornoLinea($lineaId, $cantRecibida, $cantMerma, $obs);

                $diferencia = $enviada - ($recibida + $merma);
                if ($diferencia > 0) {
                    $hayDiscrepancia = true;
                    $retornoTotal = false;
                }
            }

            $nuevoEstado = $hayDiscrepancia
                ? HousekeepingLoteLavanderia::ESTADO_CON_DISCREPANCIA
                : HousekeepingLoteLavanderia::ESTADO_RETORNADO_TOTAL;

            $fechaRetornoReal = date('Y-m-d');
            $this->housekeepingRepo->actualizarEstadoLote($loteId, $nuevoEstado, $notasRetorno, $fechaRetornoReal);

            // Auditoría transversal D-061
            $this->auditoriaServicio->registrar(
                accion: 'HOUSEKEEPING_LAVANDERIA_RETORNO',
                modulo: 'housekeeping',
                entidad: 'housekeeping_lotes_lavanderia',
                entidadId: (string) $loteId,
                descripcion: "Registrado retorno de lote '{$lote['codigo']}'. Estado resultante: {$nuevoEstado}.",
                valoresAnteriores: ['estado' => $lote['estado']],
                valoresNuevos: [
                    'estado' => $nuevoEstado,
                    'fecha_retorno_real' => $fechaRetornoReal,
                ],
                contexto: null,
                actor: $actorId,
                pdoTransaccional: $this->pdo
            );

            if ($debeCerrarTx) {
                $this->pdo->commit();
            }

            $loteActualizado = $this->housekeepingRepo->obtenerLotePorId($loteId);
            $loteActualizado['lineas'] = $this->housekeepingRepo->obtenerLineasLote($loteId);
            return HousekeepingLoteLavanderia::desdeArreglo($loteActualizado);
        } catch (Throwable $e) {
            if ($debeCerrarTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Proyecta el Rack Operacional en vivo derivando la condición hotelera de cada habitación (D-083).
     * @return HousekeepingDerivacionOperativa[]
     */
    public function obtenerRackOperacional(?int $propiedadId = null, ?int $piso = null): array
    {
        $filas = $this->housekeepingRepo->obtenerRackOperacional($propiedadId, $piso);
        $resultado = [];

        foreach ($filas as $f) {
            $tieneEstadiaActiva = !empty($f['estadia_activa_id']);
            $tieneBloqueoMantenimiento = !empty($f['tiene_bloqueo_mantenimiento']);

            $tareaActiva = !empty($f['tarea_activa_id']) ? [
                'id' => $f['tarea_activa_id'],
                'codigo' => $f['tarea_codigo'],
                'estado' => $f['tarea_estado'],
                'camarera_nombre' => $f['camarera_nombre'],
            ] : null;

            $resultado[] = HousekeepingDerivacionOperativa::derivar(
                unidad: [
                    'id' => $f['unidad_id'],
                    'numero' => $f['unidad_numero'],
                    'propiedad_id' => $f['propiedad_id'],
                    'propiedad_nombre' => $f['propiedad_nombre'],
                    'piso' => $f['piso'],
                    'tipo_unidad_nombre' => $f['tipo_unidad_nombre'],
                    'estado' => $f['estado_unidad_comercial'],
                ],
                limpieza: [
                    'estado_limpieza' => $f['estado_limpieza'],
                ],
                tieneEstadiaActiva: $tieneEstadiaActiva,
                estadiaId: $tieneEstadiaActiva ? (int) $f['estadia_activa_id'] : null,
                tieneBloqueoMantenimiento: $tieneBloqueoMantenimiento,
                motivoBloqueo: $f['motivo_bloqueo'] ?? null,
                tareaActiva: $tareaActiva
            );
        }

        return $resultado;
    }
}
