<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\HousekeepingEstadoLimpieza;
use CamargoPMS\Modelos\HousekeepingTarea;
use CamargoPMS\Modelos\HousekeepingChecklistItem;
use CamargoPMS\Modelos\HousekeepingLoteLavanderia;
use CamargoPMS\Modelos\HousekeepingLoteLinea;
use DateTimeImmutable;
use PDO;

/**
 * Repositorio de persistencia relacional para Housekeeping, Pisos y Control de Lencería.
 * HOUSEKEEPING-1 / D-083.
 */
class HousekeepingRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    public function obtenerPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Genera un folio único y correlativo para tareas (HK-YYYYMMDD-XXXX) mediante documento_secuencias con bloqueo FOR UPDATE.
     */
    public function generarCodigoTarea(?DateTimeImmutable $fecha = null): string
    {
        $fecha = $fecha ?? new DateTimeImmutable();
        $periodoYm = $fecha->format('Ym');
        $tipo = 'HOUSEKEEPING_TAREA';

        $debeCerrarTx = !$this->pdo->inTransaction();
        if ($debeCerrarTx) {
            $this->pdo->beginTransaction();
        }

        try {
            $stmt = $this->pdo->prepare(
                'SELECT ultimo_correlativo FROM documento_secuencias WHERE tipo_documento = :tipo AND periodo_ym = :ym FOR UPDATE'
            );
            $stmt->execute(['tipo' => $tipo, 'ym' => $periodoYm]);
            $correlativoActual = $stmt->fetchColumn();

            if ($correlativoActual === false) {
                $nuevoCorrelativo = 1;
                $stmtIns = $this->pdo->prepare(
                    'INSERT INTO documento_secuencias (tipo_documento, periodo_ym, ultimo_correlativo) VALUES (:tipo, :ym, :correlativo)'
                );
                $stmtIns->execute([
                    'tipo' => $tipo,
                    'ym' => $periodoYm,
                    'correlativo' => $nuevoCorrelativo,
                ]);
            } else {
                $nuevoCorrelativo = (int) $correlativoActual + 1;
                $stmtUpd = $this->pdo->prepare(
                    'UPDATE documento_secuencias SET ultimo_correlativo = :correlativo WHERE tipo_documento = :tipo AND periodo_ym = :ym'
                );
                $stmtUpd->execute([
                    'correlativo' => $nuevoCorrelativo,
                    'tipo' => $tipo,
                    'ym' => $periodoYm,
                ]);
            }

            if ($debeCerrarTx) {
                $this->pdo->commit();
            }

            return sprintf('HK-%s-%04d', $fecha->format('Ymd'), $nuevoCorrelativo);
        } catch (\Throwable $e) {
            if ($debeCerrarTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Genera un folio único y correlativo para lotes de lavandería (LAV-YYYYMMDD-XXXX).
     */
    public function generarCodigoLote(?DateTimeImmutable $fecha = null): string
    {
        $fecha = $fecha ?? new DateTimeImmutable();
        $periodoYm = $fecha->format('Ym');
        $tipo = 'HOUSEKEEPING_LAVANDERIA';

        $debeCerrarTx = !$this->pdo->inTransaction();
        if ($debeCerrarTx) {
            $this->pdo->beginTransaction();
        }

        try {
            $stmt = $this->pdo->prepare(
                'SELECT ultimo_correlativo FROM documento_secuencias WHERE tipo_documento = :tipo AND periodo_ym = :ym FOR UPDATE'
            );
            $stmt->execute(['tipo' => $tipo, 'ym' => $periodoYm]);
            $correlativoActual = $stmt->fetchColumn();

            if ($correlativoActual === false) {
                $nuevoCorrelativo = 1;
                $stmtIns = $this->pdo->prepare(
                    'INSERT INTO documento_secuencias (tipo_documento, periodo_ym, ultimo_correlativo) VALUES (:tipo, :ym, :correlativo)'
                );
                $stmtIns->execute([
                    'tipo' => $tipo,
                    'ym' => $periodoYm,
                    'correlativo' => $nuevoCorrelativo,
                ]);
            } else {
                $nuevoCorrelativo = (int) $correlativoActual + 1;
                $stmtUpd = $this->pdo->prepare(
                    'UPDATE documento_secuencias SET ultimo_correlativo = :correlativo WHERE tipo_documento = :tipo AND periodo_ym = :ym'
                );
                $stmtUpd->execute([
                    'correlativo' => $nuevoCorrelativo,
                    'tipo' => $tipo,
                    'ym' => $periodoYm,
                ]);
            }

            if ($debeCerrarTx) {
                $this->pdo->commit();
            }

            return sprintf('LAV-%s-%04d', $fecha->format('Ymd'), $nuevoCorrelativo);
        } catch (\Throwable $e) {
            if ($debeCerrarTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Obtiene el registro de estado de limpieza 1:1 de una unidad.
     */
    public function obtenerLimpiezaUnidad(int $unidadId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM housekeeping_unidades_limpieza WHERE unidad_id = :uid LIMIT 1');
        $stmt->execute(['uid' => $unidadId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Asegura la existencia del registro de limpieza para una unidad.
     */
    public function asegurarRegistroLimpieza(int $unidadId, string $estadoInicial = HousekeepingEstadoLimpieza::ESTADO_LIMPIA_INSPECCIONADA): array
    {
        $registro = $this->obtenerLimpiezaUnidad($unidadId);
        if ($registro) {
            return $registro;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO housekeeping_unidades_limpieza (unidad_id, estado_limpieza, ultima_inspeccion_en)
             VALUES (:uid, :estado, NOW())
             ON DUPLICATE KEY UPDATE actualizado_en = NOW()'
        );
        $stmt->execute([
            'uid' => $unidadId,
            'estado' => $estadoInicial,
        ]);

        return $this->obtenerLimpiezaUnidad($unidadId) ?? [];
    }

    /**
     * Actualiza el estado de limpieza de una unidad (1:1).
     */
    public function actualizarEstadoLimpieza(
        int $unidadId,
        string $nuevoEstado,
        ?int $tareaActivaId = null,
        ?int $actorId = null,
        ?string $observaciones = null,
        ?string $ultimaLimpiezaEn = null,
        ?string $ultimaInspeccionEn = null
    ): bool {
        $sets = ['estado_limpieza = :nuevo_estado'];
        $params = [
            'uid' => $unidadId,
            'nuevo_estado' => $nuevoEstado,
        ];

        if ($tareaActivaId !== null) {
            $sets[] = 'tarea_activa_id = :tarea_activa_id';
            $params['tarea_activa_id'] = $tareaActivaId > 0 ? $tareaActivaId : null;
        }

        if ($actorId !== null) {
            $sets[] = 'inspeccionado_por_actor_id = :actor_id';
            $params['actor_id'] = $actorId;
        }

        if ($observaciones !== null) {
            $sets[] = 'observaciones = :observaciones';
            $params['observaciones'] = $observaciones;
        }

        if ($ultimaLimpiezaEn !== null) {
            $sets[] = 'ultima_limpieza_en = :ultima_limpieza_en';
            $params['ultima_limpieza_en'] = $ultimaLimpiezaEn;
        }

        if ($ultimaInspeccionEn !== null) {
            $sets[] = 'ultima_inspeccion_en = :ultima_inspeccion_en';
            $params['ultima_inspeccion_en'] = $ultimaInspeccionEn;
        }

        $sql = 'UPDATE housekeeping_unidades_limpieza SET ' . implode(', ', $sets) . ', actualizado_en = NOW() WHERE unidad_id = :uid';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Inserta una nueva tarea operativa de trabajo de housekeeping.
     */
    public function crearTarea(array $datos): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO housekeeping_tareas (
                codigo, propiedad_id, unidad_id, estadia_id, tipo_tarea, prioridad, estado,
                camarera_colaborador_id, supervisor_colaborador_id, fecha_programada,
                condicion_operacional, notas_operario, notas_supervisor, creado_por_actor_id
            ) VALUES (
                :codigo, :propiedad_id, :unidad_id, :estadia_id, :tipo_tarea, :prioridad, :estado,
                :camarera_colaborador_id, :supervisor_colaborador_id, :fecha_programada,
                :condicion_operacional, :notas_operario, :notas_supervisor, :creado_por_actor_id
            )'
        );

        $stmt->execute([
            'codigo' => $datos['codigo'],
            'propiedad_id' => $datos['propiedad_id'],
            'unidad_id' => $datos['unidad_id'],
            'estadia_id' => $datos['estadia_id'] ?? null,
            'tipo_tarea' => $datos['tipo_tarea'] ?? HousekeepingTarea::TIPO_SALIDA,
            'prioridad' => $datos['prioridad'] ?? HousekeepingTarea::PRIORIDAD_MEDIA,
            'estado' => $datos['estado'] ?? HousekeepingTarea::ESTADO_PENDIENTE,
            'camarera_colaborador_id' => $datos['camarera_colaborador_id'] ?? null,
            'supervisor_colaborador_id' => $datos['supervisor_colaborador_id'] ?? null,
            'fecha_programada' => $datos['fecha_programada'] ?? date('Y-m-d'),
            'condicion_operacional' => $datos['condicion_operacional'] ?? HousekeepingTarea::CONDICION_NINGUNA,
            'notas_operario' => $datos['notas_operario'] ?? null,
            'notas_supervisor' => $datos['notas_supervisor'] ?? null,
            'creado_por_actor_id' => $datos['creado_por_actor_id'] ?? 1,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Obtiene una tarea por ID.
     */
    public function obtenerTareaPorId(int $tareaId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT t.*,
                    u.codigo AS unidad_numero,
                    p.nombre AS propiedad_nombre,
                    CONCAT(p_cam.nombres, " ", p_cam.apellido_paterno) AS camarera_nombre,
                    CONCAT(p_sup.nombres, " ", p_sup.apellido_paterno) AS supervisor_nombre
             FROM housekeeping_tareas t
             INNER JOIN unidades u ON u.id = t.unidad_id
             INNER JOIN propiedades p ON p.id = t.propiedad_id
             LEFT JOIN colaboradores c_cam ON c_cam.id = t.camarera_colaborador_id
             LEFT JOIN personas p_cam ON p_cam.id = c_cam.persona_id
             LEFT JOIN colaboradores c_sup ON c_sup.id = t.supervisor_colaborador_id
             LEFT JOIN personas p_sup ON p_sup.id = c_sup.persona_id
             WHERE t.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $tareaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Obtiene una tarea por código.
     */
    public function obtenerTareaPorCodigo(string $codigo): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM housekeeping_tareas WHERE codigo = :codigo LIMIT 1');
        $stmt->execute(['codigo' => $codigo]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Obtiene la tarea activa de salida vinculada a una estadía (para garantizar idempotencia estricta).
     */
    public function obtenerTareaSalidaActivaPorEstadia(int $estadiaId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM housekeeping_tareas
             WHERE tipo_tarea = "SALIDA" AND estadia_id = :estadia_id AND estado != "CANCELADA"
             LIMIT 1'
        );
        $stmt->execute(['estadia_id' => $estadiaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Actualiza el estado y atributos de una tarea.
     */
    public function actualizarEstadoTarea(
        int $tareaId,
        string $nuevoEstado,
        array $camposAdicionales = []
    ): bool {
        $sets = ['estado = :nuevo_estado'];
        $params = [
            'id' => $tareaId,
            'nuevo_estado' => $nuevoEstado,
        ];

        foreach ($camposAdicionales as $campo => $valor) {
            $sets[] = "`{$campo}` = :{$campo}";
            $params[$campo] = $valor;
        }

        $sql = 'UPDATE housekeeping_tareas SET ' . implode(', ', $sets) . ', actualizado_en = NOW() WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Registra un evento append-only en el historial inmutable de tareas (D-061 / D-083).
     */
    public function registrarHistorialTarea(
        int $tareaId,
        ?string $estadoAnterior,
        string $estadoNuevo,
        int $actorId,
        ?string $motivo = null
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO housekeeping_tarea_historial (tarea_id, estado_anterior, estado_nuevo, actor_id, motivo, creado_en)
             VALUES (:tarea_id, :estado_anterior, :estado_nuevo, :actor_id, :motivo, NOW())'
        );
        $stmt->execute([
            'tarea_id' => $tareaId,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $estadoNuevo,
            'actor_id' => $actorId,
            'motivo' => $motivo,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Lista tareas de housekeeping con filtros opcionales.
     */
    public function listarTareas(array $filtros = []): array
    {
        $sql = 'SELECT t.*,
                       u.codigo AS unidad_numero,
                       p.nombre AS propiedad_nombre,
                       CONCAT(p_cam.nombres, " ", p_cam.apellido_paterno) AS camarera_nombre,
                       CONCAT(p_sup.nombres, " ", p_sup.apellido_paterno) AS supervisor_nombre
                FROM housekeeping_tareas t
                INNER JOIN unidades u ON u.id = t.unidad_id
                INNER JOIN propiedades p ON p.id = t.propiedad_id
                LEFT JOIN colaboradores c_cam ON c_cam.id = t.camarera_colaborador_id
                LEFT JOIN personas p_cam ON p_cam.id = c_cam.persona_id
                LEFT JOIN colaboradores c_sup ON c_sup.id = t.supervisor_colaborador_id
                LEFT JOIN personas p_sup ON p_sup.id = c_sup.persona_id
                WHERE 1=1';

        $params = [];

        if (!empty($filtros['propiedad_id'])) {
            $sql .= ' AND t.propiedad_id = :propiedad_id';
            $params['propiedad_id'] = (int) $filtros['propiedad_id'];
        }

        if (!empty($filtros['unidad_id'])) {
            $sql .= ' AND t.unidad_id = :unidad_id';
            $params['unidad_id'] = (int) $filtros['unidad_id'];
        }

        if (!empty($filtros['estado'])) {
            $sql .= ' AND t.estado = :estado';
            $params['estado'] = $filtros['estado'];
        }

        if (!empty($filtros['tipo_tarea'])) {
            $sql .= ' AND t.tipo_tarea = :tipo_tarea';
            $params['tipo_tarea'] = $filtros['tipo_tarea'];
        }

        if (!empty($filtros['camarera_colaborador_id'])) {
            $sql .= ' AND t.camarera_colaborador_id = :camarera_id';
            $params['camarera_id'] = (int) $filtros['camarera_colaborador_id'];
        }

        if (!empty($filtros['fecha_programada'])) {
            $sql .= ' AND t.fecha_programada = :fecha_programada';
            $params['fecha_programada'] = $filtros['fecha_programada'];
        }

        $sql .= ' ORDER BY t.fecha_programada DESC, CASE t.prioridad WHEN "URGENTE" THEN 1 WHEN "ALTA" THEN 2 WHEN "MEDIA" THEN 3 ELSE 4 END, t.id DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene la plantilla activa de checklist según tipo de tarea.
     */
    public function obtenerPlantillaActiva(string $tipoTarea = 'TODAS'): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM housekeeping_plantillas_checklist
             WHERE es_activa = 1 AND (tipo_tarea = :tipo_tarea OR tipo_tarea = "TODAS")
             ORDER BY CASE WHEN tipo_tarea = :tipo_tarea_order THEN 1 ELSE 2 END, version DESC
             LIMIT 1'
        );
        $stmt->execute([
            'tipo_tarea' => $tipoTarea,
            'tipo_tarea_order' => $tipoTarea,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Obtiene los ítems ordenados de una plantilla de checklist.
     */
    public function obtenerItemsPlantilla(int $plantillaId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM housekeeping_plantilla_items WHERE plantilla_id = :pid ORDER BY orden ASC, id ASC'
        );
        $stmt->execute(['pid' => $plantillaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Guarda el snapshot congelado de los ítems de checklist para una tarea específica.
     */
    public function guardarSnapshotChecklist(int $tareaId, array $items): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO housekeeping_tarea_checklist (
                tarea_id, codigo_item_snapshot, categoria_snapshot, descripcion_snapshot,
                es_critico_snapshot, resultado, observacion
            ) VALUES (
                :tarea_id, :codigo, :categoria, :descripcion, :es_critico, :resultado, :observacion
            )'
        );

        foreach ($items as $item) {
            $stmt->execute([
                'tarea_id' => $tareaId,
                'codigo' => $item['codigo_item'] ?? $item['codigo_item_snapshot'],
                'categoria' => $item['categoria'] ?? $item['categoria_snapshot'] ?? 'GENERAL',
                'descripcion' => $item['descripcion'] ?? $item['descripcion_snapshot'],
                'es_critico' => !empty($item['es_critico']) || !empty($item['es_critico_snapshot']) ? 1 : 0,
                'resultado' => $item['resultado'] ?? HousekeepingChecklistItem::RESULTADO_CONFORME,
                'observacion' => $item['observacion'] ?? null,
            ]);
        }
    }

    /**
     * Obtiene el checklist congelado de una tarea.
     */
    public function obtenerChecklistTarea(int $tareaId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM housekeeping_tarea_checklist WHERE tarea_id = :tid ORDER BY id ASC'
        );
        $stmt->execute(['tid' => $tareaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Actualiza la evaluación de un ítem de checklist individual.
     */
    public function actualizarResultadoChecklistItem(
        int $checklistId,
        string $resultado,
        ?string $observacion = null,
        ?int $actorId = null
    ): bool {
        $stmt = $this->pdo->prepare(
            'UPDATE housekeeping_tarea_checklist
             SET resultado = :resultado,
                 observacion = :observacion,
                 verificado_en = NOW(),
                 verificado_por_actor_id = :actor_id
             WHERE id = :id'
        );
        return $stmt->execute([
            'id' => $checklistId,
            'resultado' => $resultado,
            'observacion' => $observacion,
            'actor_id' => $actorId,
        ]);
    }

    /**
     * Registra el consumo de un amenitie en una tarea con FK directa al movimiento de Kardex.
     */
    public function registrarConsumo(
        int $tareaId,
        int $articuloId,
        int $almacenId,
        string $cantidad,
        int $movimientoId
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO housekeeping_tarea_consumos (
                tarea_id, articulo_id, almacen_origen_id, cantidad, movimiento_id, creado_en
            ) VALUES (
                :tarea_id, :articulo_id, :almacen_id, :cantidad, :movimiento_id, NOW()
            )'
        );
        $stmt->execute([
            'tarea_id' => $tareaId,
            'articulo_id' => $articuloId,
            'almacen_id' => $almacenId,
            'cantidad' => $cantidad,
            'movimiento_id' => $movimientoId,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Obtiene los consumos de amenities asociados a una tarea.
     */
    public function obtenerConsumosTarea(int $tareaId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.*, a.codigo_sku AS articulo_codigo, a.nombre AS articulo_nombre, u.nombre AS almacen_nombre
             FROM housekeeping_tarea_consumos c
             INNER JOIN inventario_articulos a ON a.id = c.articulo_id
             INNER JOIN inventario_ubicaciones u ON u.id = c.almacen_origen_id
             WHERE c.tarea_id = :tid
             ORDER BY c.id ASC'
        );
        $stmt->execute(['tid' => $tareaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Registra un nuevo lote de lavandería con sus líneas de prendas textiles.
     */
    public function crearLoteLavanderia(array $datosLote, array $lineas): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO housekeeping_lotes_lavanderia (
                codigo, propiedad_id, almacen_origen_id, ubicacion_lavanderia_id, fecha_despacho,
                fecha_retorno_estimada, estado, notas_despacho, creado_por_actor_id, creado_en
            ) VALUES (
                :codigo, :propiedad_id, :almacen_origen_id, :ubicacion_lavanderia_id, :fecha_despacho,
                :fecha_retorno_estimada, :estado, :notas_despacho, :creado_por_actor_id, NOW()
            )'
        );
        $stmt->execute([
            'codigo' => $datosLote['codigo'],
            'propiedad_id' => $datosLote['propiedad_id'],
            'almacen_origen_id' => $datosLote['almacen_origen_id'],
            'ubicacion_lavanderia_id' => $datosLote['ubicacion_lavanderia_id'],
            'fecha_despacho' => $datosLote['fecha_despacho'] ?? date('Y-m-d'),
            'fecha_retorno_estimada' => $datosLote['fecha_retorno_estimada'] ?? null,
            'estado' => $datosLote['estado'] ?? HousekeepingLoteLavanderia::ESTADO_DESPACHADO,
            'notas_despacho' => $datosLote['notas_despacho'] ?? null,
            'creado_por_actor_id' => $datosLote['creado_por_actor_id'] ?? 1,
        ]);

        $loteId = (int) $this->pdo->lastInsertId();

        $stmtLin = $this->pdo->prepare(
            'INSERT INTO housekeeping_lote_lineas (
                lote_id, articulo_id, cantidad_enviada, cantidad_recibida, cantidad_baja_merma, observaciones
            ) VALUES (
                :lote_id, :articulo_id, :cantidad_enviada, 0.0000, 0.0000, :observaciones
            )'
        );

        foreach ($lineas as $l) {
            $stmtLin->execute([
                'lote_id' => $loteId,
                'articulo_id' => $l['articulo_id'],
                'cantidad_enviada' => $l['cantidad_enviada'],
                'observaciones' => $l['observaciones'] ?? null,
            ]);
        }

        return $loteId;
    }

    /**
     * Obtiene un lote de lavandería por ID.
     */
    public function obtenerLotePorId(int $loteId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT l.*,
                    p.nombre AS propiedad_nombre,
                    u_orig.nombre AS almacen_origen_nombre,
                    u_lav.nombre AS lavanderia_nombre
             FROM housekeeping_lotes_lavanderia l
             INNER JOIN propiedades p ON p.id = l.propiedad_id
             INNER JOIN inventario_ubicaciones u_orig ON u_orig.id = l.almacen_origen_id
             INNER JOIN inventario_ubicaciones u_lav ON u_lav.id = l.ubicacion_lavanderia_id
             WHERE l.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $loteId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Obtiene las líneas textiles de un lote de lavandería con cálculo virtual de discrepancias.
     */
    public function obtenerLineasLote(int $loteId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT lin.*, a.codigo_sku AS articulo_codigo, a.nombre AS articulo_nombre
             FROM housekeeping_lote_lineas lin
             INNER JOIN inventario_articulos a ON a.id = lin.articulo_id
             WHERE lin.lote_id = :lid
             ORDER BY lin.id ASC'
        );
        $stmt->execute(['lid' => $loteId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Actualiza el retorno de una línea textil de lavandería.
     */
    public function actualizarRetornoLinea(
        int $lineaId,
        string $cantidadRecibida,
        string $cantidadBajaMerma,
        ?string $observaciones = null
    ): bool {
        $stmt = $this->pdo->prepare(
            'UPDATE housekeeping_lote_lineas
             SET cantidad_recibida = :recibida,
                 cantidad_baja_merma = :baja,
                 observaciones = :obs
             WHERE id = :id'
        );
        return $stmt->execute([
            'id' => $lineaId,
            'recibida' => $cantidadRecibida,
            'baja' => $cantidadBajaMerma,
            'obs' => $observaciones,
        ]);
    }

    /**
     * Actualiza el estado y notas de retorno de un lote de lavandería.
     */
    public function actualizarEstadoLote(
        int $loteId,
        string $nuevoEstado,
        ?string $notasRetorno = null,
        ?string $fechaRetornoReal = null
    ): bool {
        $stmt = $this->pdo->prepare(
            'UPDATE housekeeping_lotes_lavanderia
             SET estado = :estado,
                 notas_retorno = :notas_retorno,
                 fecha_retorno_real = :fecha_retorno,
                 actualizado_en = NOW()
             WHERE id = :id'
        );
        return $stmt->execute([
            'id' => $loteId,
            'estado' => $nuevoEstado,
            'notas_retorno' => $notasRetorno,
            'fecha_retorno' => $fechaRetornoReal,
        ]);
    }

    /**
     * Lista lotes de lavandería con filtros.
     */
    public function listarLotesLavanderia(array $filtros = []): array
    {
        $sql = 'SELECT l.*,
                       p.nombre AS propiedad_nombre,
                       u_orig.nombre AS almacen_origen_nombre,
                       u_lav.nombre AS lavanderia_nombre
                FROM housekeeping_lotes_lavanderia l
                INNER JOIN propiedades p ON p.id = l.propiedad_id
                INNER JOIN inventario_ubicaciones u_orig ON u_orig.id = l.almacen_origen_id
                INNER JOIN inventario_ubicaciones u_lav ON u_lav.id = l.ubicacion_lavanderia_id
                WHERE 1=1';

        $params = [];
        if (!empty($filtros['propiedad_id'])) {
            $sql .= ' AND l.propiedad_id = :propiedad_id';
            $params['propiedad_id'] = (int) $filtros['propiedad_id'];
        }

        if (!empty($filtros['estado'])) {
            $sql .= ' AND l.estado = :estado';
            $params['estado'] = $filtros['estado'];
        }

        $sql .= ' ORDER BY l.fecha_despacho DESC, l.id DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene el listado completo de unidades con sus estados de ocupación, mantenimiento y limpieza
     * para proyectar el Rack de Pisos dinámico sin guardar VR/VD/OD en base de datos (D-083).
     */
    public function obtenerRackOperacional(?int $propiedadId = null, ?int $piso = null): array
    {
        $sql = 'SELECT u.id AS unidad_id,
                       u.codigo AS unidad_numero,
                       u.propiedad_id,
                       p.nombre AS propiedad_nombre,
                       u.piso_nivel AS piso,
                       tu.nombre AS tipo_unidad_nombre,
                       u.estado AS estado_unidad_comercial,
                       -- Limpieza 1:1
                       COALESCE(hul.estado_limpieza, "SUCIA") AS estado_limpieza,
                       hul.tarea_activa_id,
                       hul.ultima_limpieza_en,
                       hul.ultima_inspeccion_en,
                       -- Estadía en curso
                       e.id AS estadia_activa_id,
                       e.codigo AS estadia_codigo,
                       -- Tarea activa
                       t.codigo AS tarea_codigo,
                       t.estado AS tarea_estado,
                       t.tipo_tarea AS tarea_tipo,
                       CONCAT(p_col.nombres, " ", p_col.apellido_paterno) AS camarera_nombre,
                       -- Bloqueo de mantenimiento hoy
                       CASE WHEN bloq.unidad_id IS NOT NULL THEN 1 ELSE 0 END AS tiene_bloqueo_mantenimiento,
                       bloq.motivo AS motivo_bloqueo
                FROM unidades u
                INNER JOIN propiedades p ON p.id = u.propiedad_id
                INNER JOIN tipos_unidad tu ON tu.id = u.tipo_unidad_id
                LEFT JOIN housekeeping_unidades_limpieza hul ON hul.unidad_id = u.id
                LEFT JOIN estadias e ON e.unidad_id = u.id AND e.estado = "EN_CURSO"
                LEFT JOIN housekeeping_tareas t ON t.id = hul.tarea_activa_id
                LEFT JOIN colaboradores col ON col.id = t.camarera_colaborador_id
                LEFT JOIN personas p_col ON p_col.id = col.persona_id
                LEFT JOIN (
                    SELECT unidad_id, "MANTENIMIENTO" AS motivo
                    FROM mantenimiento_ordenes
                    WHERE estado IN ("PROGRAMADA", "EN_PROCESO") AND requiere_bloqueo = 1
                    GROUP BY unidad_id
                ) bloq ON bloq.unidad_id = u.id
                WHERE 1=1';

        $params = [];
        if ($propiedadId !== null && $propiedadId > 0) {
            $sql .= ' AND u.propiedad_id = :propiedad_id';
            $params['propiedad_id'] = $propiedadId;
        }

        if ($piso !== null) {
            $sql .= ' AND u.piso_nivel = :piso';
            $params['piso'] = (string) $piso;
        }

        $sql .= ' ORDER BY u.propiedad_id ASC, u.piso_nivel ASC, u.codigo ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
