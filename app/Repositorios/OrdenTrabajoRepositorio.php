<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\OrdenTrabajo;
use PDO;

/**
 * Repositorio para la persistencia y consulta de órdenes de trabajo de mantenimiento.
 */
class OrdenTrabajoRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    public function crear(OrdenTrabajo $orden): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO mantenimiento_ordenes (
                codigo, tipo, prioridad, propiedad_id, unidad_id, titulo, descripcion,
                tipo_asignacion, colaborador_asignado_id, proveedor_id, numero_comprobante_proveedor,
                requiere_bloqueo, fecha_programada_inicio, fecha_programada_fin,
                fecha_bloqueo_inicio, fecha_bloqueo_fin, fecha_ejecucion_inicio, fecha_ejecucion_fin,
                costo_estimado, costo_mano_obra, costo_materiales, costo_total, moneda_codigo,
                estado, creado_por_actor_id, motivo_cancelacion, notas_cierre, creado_en
            ) VALUES (
                :codigo, :tipo, :prioridad, :propiedad_id, :unidad_id, :titulo, :descripcion,
                :tipo_asignacion, :colaborador_asignado_id, :proveedor_id, :numero_comprobante_proveedor,
                :requiere_bloqueo, :fecha_programada_inicio, :fecha_programada_fin,
                :fecha_bloqueo_inicio, :fecha_bloqueo_fin, :fecha_ejecucion_inicio, :fecha_ejecucion_fin,
                :costo_estimado, :costo_mano_obra, :costo_materiales, :costo_total, :moneda_codigo,
                :estado, :creado_por_actor_id, :motivo_cancelacion, :notas_cierre, NOW()
            )'
        );

        $stmt->execute([
            'codigo' => $orden->obtenerCodigo(),
            'tipo' => $orden->obtenerTipo(),
            'prioridad' => $orden->obtenerPrioridad(),
            'propiedad_id' => $orden->obtenerPropiedadId(),
            'unidad_id' => $orden->obtenerUnidadId(),
            'titulo' => $orden->obtenerTitulo(),
            'descripcion' => $orden->obtenerDescripcion(),
            'tipo_asignacion' => $orden->obtenerTipoAsignacion(),
            'colaborador_asignado_id' => $orden->obtenerColaboradorAsignadoId(),
            'proveedor_id' => $orden->obtenerProveedorId(),
            'numero_comprobante_proveedor' => $orden->obtenerNumeroComprobanteProveedor(),
            'requiere_bloqueo' => $orden->requiereBloqueo() ? 1 : 0,
            'fecha_programada_inicio' => $orden->obtenerFechaProgramadaInicio(),
            'fecha_programada_fin' => $orden->obtenerFechaProgramadaFin(),
            'fecha_bloqueo_inicio' => $orden->obtenerFechaBloqueoInicio(),
            'fecha_bloqueo_fin' => $orden->obtenerFechaBloqueoFin(),
            'fecha_ejecucion_inicio' => $orden->obtenerFechaEjecucionInicio(),
            'fecha_ejecucion_fin' => $orden->obtenerFechaEjecucionFin(),
            'costo_estimado' => $orden->obtenerCostoEstimado(),
            'costo_mano_obra' => $orden->obtenerCostoManoObra(),
            'costo_materiales' => $orden->obtenerCostoMateriales(),
            'costo_total' => $orden->obtenerCostoTotal(),
            'moneda_codigo' => $orden->obtenerMonedaCodigo(),
            'estado' => $orden->obtenerEstado(),
            'creado_por_actor_id' => $orden->obtenerCreadoPorActorId(),
            'motivo_cancelacion' => $orden->obtenerMotivoCancelacion(),
            'notas_cierre' => $orden->obtenerNotasCierre(),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function obtenerPorId(int $id, bool $bloquear = false): ?OrdenTrabajo
    {
        $sql = 'SELECT 
                    o.*,
                    p.nombre AS propiedad_nombre,
                    u.codigo AS unidad_numero,
                    u.nombre AS unidad_nombre,
                    TRIM(CONCAT(per.nombres, " ", per.apellido_paterno, " ", COALESCE(per.apellido_materno, ""))) AS colaborador_nombre_completo,
                    prov.razon_social AS proveedor_razon_social
                FROM mantenimiento_ordenes o
                INNER JOIN propiedades p ON p.id = o.propiedad_id
                LEFT JOIN unidades u ON u.id = o.unidad_id
                LEFT JOIN colaboradores col ON col.id = o.colaborador_asignado_id
                LEFT JOIN personas per ON per.id = col.persona_id
                LEFT JOIN proveedores prov ON prov.id = o.proveedor_id
                WHERE o.id = :id
                LIMIT 1' . ($bloquear ? ' FOR UPDATE' : '');

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            return null;
        }

        $orden = OrdenTrabajo::desdeArreglo($fila);
        $orden->fijarIncidenciasAsociadas($this->obtenerIncidenciasAsociadas((int) $fila['id']));

        return $orden;
    }

    public function obtenerPorCodigo(string $codigo, bool $bloquear = false): ?OrdenTrabajo
    {
        $sql = 'SELECT 
                    o.*,
                    p.nombre AS propiedad_nombre,
                    u.codigo AS unidad_numero,
                    u.nombre AS unidad_nombre,
                    TRIM(CONCAT(per.nombres, " ", per.apellido_paterno, " ", COALESCE(per.apellido_materno, ""))) AS colaborador_nombre_completo,
                    prov.razon_social AS proveedor_razon_social
                FROM mantenimiento_ordenes o
                INNER JOIN propiedades p ON p.id = o.propiedad_id
                LEFT JOIN unidades u ON u.id = o.unidad_id
                LEFT JOIN colaboradores col ON col.id = o.colaborador_asignado_id
                LEFT JOIN personas per ON per.id = col.persona_id
                LEFT JOIN proveedores prov ON prov.id = o.proveedor_id
                WHERE o.codigo = :codigo
                LIMIT 1' . ($bloquear ? ' FOR UPDATE' : '');

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['codigo' => $codigo]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            return null;
        }

        $orden = OrdenTrabajo::desdeArreglo($fila);
        $orden->fijarIncidenciasAsociadas($this->obtenerIncidenciasAsociadas((int) $fila['id']));

        return $orden;
    }

    public function actualizar(OrdenTrabajo $orden): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE mantenimiento_ordenes SET
                tipo = :tipo,
                prioridad = :prioridad,
                propiedad_id = :propiedad_id,
                unidad_id = :unidad_id,
                titulo = :titulo,
                descripcion = :descripcion,
                tipo_asignacion = :tipo_asignacion,
                colaborador_asignado_id = :colaborador_asignado_id,
                proveedor_id = :proveedor_id,
                numero_comprobante_proveedor = :numero_comprobante_proveedor,
                requiere_bloqueo = :requiere_bloqueo,
                fecha_programada_inicio = :fecha_programada_inicio,
                fecha_programada_fin = :fecha_programada_fin,
                fecha_bloqueo_inicio = :fecha_bloqueo_inicio,
                fecha_bloqueo_fin = :fecha_bloqueo_fin,
                fecha_ejecucion_inicio = :fecha_ejecucion_inicio,
                fecha_ejecucion_fin = :fecha_ejecucion_fin,
                costo_estimado = :costo_estimado,
                costo_mano_obra = :costo_mano_obra,
                costo_materiales = :costo_materiales,
                costo_total = :costo_total,
                estado = :estado,
                completado_por_actor_id = :completado_por_actor_id,
                cancelado_por_actor_id = :cancelado_por_actor_id,
                motivo_cancelacion = :motivo_cancelacion,
                notas_cierre = :notas_cierre
            WHERE id = :id'
        );

        return $stmt->execute([
            'id' => $orden->obtenerId(),
            'tipo' => $orden->obtenerTipo(),
            'prioridad' => $orden->obtenerPrioridad(),
            'propiedad_id' => $orden->obtenerPropiedadId(),
            'unidad_id' => $orden->obtenerUnidadId(),
            'titulo' => $orden->obtenerTitulo(),
            'descripcion' => $orden->obtenerDescripcion(),
            'tipo_asignacion' => $orden->obtenerTipoAsignacion(),
            'colaborador_asignado_id' => $orden->obtenerColaboradorAsignadoId(),
            'proveedor_id' => $orden->obtenerProveedorId(),
            'numero_comprobante_proveedor' => $orden->obtenerNumeroComprobanteProveedor(),
            'requiere_bloqueo' => $orden->requiereBloqueo() ? 1 : 0,
            'fecha_programada_inicio' => $orden->obtenerFechaProgramadaInicio(),
            'fecha_programada_fin' => $orden->obtenerFechaProgramadaFin(),
            'fecha_bloqueo_inicio' => $orden->obtenerFechaBloqueoInicio(),
            'fecha_bloqueo_fin' => $orden->obtenerFechaBloqueoFin(),
            'fecha_ejecucion_inicio' => $orden->obtenerFechaEjecucionInicio(),
            'fecha_ejecucion_fin' => $orden->obtenerFechaEjecucionFin(),
            'costo_estimado' => $orden->obtenerCostoEstimado(),
            'costo_mano_obra' => $orden->obtenerCostoManoObra(),
            'costo_materiales' => $orden->obtenerCostoMateriales(),
            'costo_total' => $orden->obtenerCostoTotal(),
            'estado' => $orden->obtenerEstado(),
            'completado_por_actor_id' => $orden->obtenerCompletadoPorActorId(),
            'cancelado_por_actor_id' => $orden->obtenerCanceladoPorActorId(),
            'motivo_cancelacion' => $orden->obtenerMotivoCancelacion(),
            'notas_cierre' => $orden->obtenerNotasCierre(),
        ]);
    }

    public function actualizarCostos(int $id, string $costoMateriales, string $costoManoObra, string $costoTotal): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE mantenimiento_ordenes SET
                costo_materiales = :materiales,
                costo_mano_obra = :mano_obra,
                costo_total = :total
            WHERE id = :id'
        );

        return $stmt->execute([
            'id' => $id,
            'materiales' => $costoMateriales,
            'mano_obra' => $costoManoObra,
            'total' => $costoTotal,
        ]);
    }

    public function asociarIncidencia(int $ordenId, int $incidenciaId): bool
    {
        $stmt = $this->pdo->prepare(
            'INSERT IGNORE INTO mantenimiento_orden_incidencias (orden_id, incidencia_id, creado_en)
             VALUES (:orden_id, :incidencia_id, NOW())'
        );

        return $stmt->execute([
            'orden_id' => $ordenId,
            'incidencia_id' => $incidenciaId,
        ]);
    }

    public function desasociarIncidencia(int $ordenId, int $incidenciaId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM mantenimiento_orden_incidencias
             WHERE orden_id = :orden_id AND incidencia_id = :incidencia_id'
        );

        return $stmt->execute([
            'orden_id' => $ordenId,
            'incidencia_id' => $incidenciaId,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function obtenerIncidenciasAsociadas(int $ordenId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT i.id, i.codigo, i.titulo, i.categoria, i.severidad, i.estado
             FROM mantenimiento_incidencias i
             INNER JOIN mantenimiento_orden_incidencias moi ON moi.incidencia_id = i.id
             WHERE moi.orden_id = :orden_id
             ORDER BY i.id ASC'
        );

        $stmt->execute(['orden_id' => $ordenId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<string, mixed> $filtros
     * @return array<int, OrdenTrabajo>
     */
    public function listar(array $filtros = []): array
    {
        $sql = 'SELECT 
                    o.*,
                    p.nombre AS propiedad_nombre,
                    u.codigo AS unidad_numero,
                    u.nombre AS unidad_nombre,
                    TRIM(CONCAT(per.nombres, " ", per.apellido_paterno, " ", COALESCE(per.apellido_materno, ""))) AS colaborador_nombre_completo,
                    prov.razon_social AS proveedor_razon_social
                FROM mantenimiento_ordenes o
                INNER JOIN propiedades p ON p.id = o.propiedad_id
                LEFT JOIN unidades u ON u.id = o.unidad_id
                LEFT JOIN colaboradores col ON col.id = o.colaborador_asignado_id
                LEFT JOIN personas per ON per.id = col.persona_id
                LEFT JOIN proveedores prov ON prov.id = o.proveedor_id
                WHERE 1=1';

        $params = [];

        if (!empty($filtros['propiedad_id'])) {
            $sql .= ' AND o.propiedad_id = :propiedad_id';
            $params['propiedad_id'] = (int) $filtros['propiedad_id'];
        }

        if (!empty($filtros['unidad_id'])) {
            $sql .= ' AND o.unidad_id = :unidad_id';
            $params['unidad_id'] = (int) $filtros['unidad_id'];
        }

        if (!empty($filtros['estado'])) {
            $sql .= ' AND o.estado = :estado';
            $params['estado'] = (string) $filtros['estado'];
        }

        if (!empty($filtros['tipo'])) {
            $sql .= ' AND o.tipo = :tipo';
            $params['tipo'] = (string) $filtros['tipo'];
        }

        if (!empty($filtros['prioridad'])) {
            $sql .= ' AND o.prioridad = :prioridad';
            $params['prioridad'] = (string) $filtros['prioridad'];
        }

        if (!empty($filtros['tipo_asignacion'])) {
            $sql .= ' AND o.tipo_asignacion = :tipo_asignacion';
            $params['tipo_asignacion'] = (string) $filtros['tipo_asignacion'];
        }

        if (isset($filtros['requiere_bloqueo']) && $filtros['requiere_bloqueo'] !== '') {
            $sql .= ' AND o.requiere_bloqueo = :requiere_bloqueo';
            $params['requiere_bloqueo'] = !empty($filtros['requiere_bloqueo']) ? 1 : 0;
        }

        if (!empty($filtros['busqueda'])) {
            $sql .= ' AND (o.codigo LIKE :busqueda OR o.titulo LIKE :busqueda OR o.descripcion LIKE :busqueda)';
            $params['busqueda'] = '%' . trim((string) $filtros['busqueda']) . '%';
        }

        $sql .= ' ORDER BY o.id DESC';

        if (isset($filtros['limite'])) {
            $limite = (int) $filtros['limite'];
            $offset = (int) ($filtros['offset'] ?? 0);
            $sql .= " LIMIT {$offset}, {$limite}";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resultado = [];
        foreach ($filas as $fila) {
            $orden = OrdenTrabajo::desdeArreglo($fila);
            $orden->fijarIncidenciasAsociadas($this->obtenerIncidenciasAsociadas((int) $fila['id']));
            $resultado[] = $orden;
        }

        return $resultado;
    }

    /**
     * @param array<string, mixed> $filtros
     */
    public function contar(array $filtros = []): int
    {
        $sql = 'SELECT COUNT(*) FROM mantenimiento_ordenes o WHERE 1=1';
        $params = [];

        if (!empty($filtros['propiedad_id'])) {
            $sql .= ' AND o.propiedad_id = :propiedad_id';
            $params['propiedad_id'] = (int) $filtros['propiedad_id'];
        }

        if (!empty($filtros['unidad_id'])) {
            $sql .= ' AND o.unidad_id = :unidad_id';
            $params['unidad_id'] = (int) $filtros['unidad_id'];
        }

        if (!empty($filtros['estado'])) {
            $sql .= ' AND o.estado = :estado';
            $params['estado'] = (string) $filtros['estado'];
        }

        if (!empty($filtros['tipo'])) {
            $sql .= ' AND o.tipo = :tipo';
            $params['tipo'] = (string) $filtros['tipo'];
        }

        if (!empty($filtros['prioridad'])) {
            $sql .= ' AND o.prioridad = :prioridad';
            $params['prioridad'] = (string) $filtros['prioridad'];
        }

        if (!empty($filtros['busqueda'])) {
            $sql .= ' AND (o.codigo LIKE :busqueda OR o.titulo LIKE :busqueda OR o.descripcion LIKE :busqueda)';
            $params['busqueda'] = '%' . trim((string) $filtros['busqueda']) . '%';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function contarUnidadesBloqueadasActivas(): int
    {
        $sql = 'SELECT COUNT(DISTINCT unidad_id) 
                FROM mantenimiento_ordenes 
                WHERE requiere_bloqueo = 1 
                  AND estado IN ("PROGRAMADA", "EN_PROCESO") 
                  AND unidad_id IS NOT NULL';
        return (int) $this->pdo->query($sql)->fetchColumn();
    }

    public function generarSiguienteCodigo(): string
    {
        $prefijo = 'OT-' . date('Ymd') . '-';
        $stmt = $this->pdo->prepare(
            'SELECT codigo FROM mantenimiento_ordenes 
             WHERE codigo LIKE :prefijo 
             ORDER BY id DESC 
             LIMIT 1'
        );
        $stmt->execute(['prefijo' => $prefijo . '%']);
        $ultimo = $stmt->fetchColumn();

        if ($ultimo && preg_match('/-(\d{4})$/', (string) $ultimo, $coincidencias)) {
            $correlativo = ((int) $coincidencias[1]) + 1;
        } else {
            $correlativo = 1;
        }

        $stmtExiste = $this->pdo->prepare('SELECT 1 FROM mantenimiento_ordenes WHERE codigo = :cod LIMIT 1');
        do {
            $candidato = $prefijo . str_pad((string) $correlativo, 4, '0', STR_PAD_LEFT);
            $stmtExiste->execute(['cod' => $candidato]);
            if ($stmtExiste->fetchColumn()) {
                $correlativo++;
            } else {
                return $candidato;
            }
        } while (true);
    }
}
