<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\Incidencia;
use PDO;

/**
 * Repositorio para la persistencia y consulta de incidencias técnicas.
 */
class IncidenciaRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    public function crear(Incidencia $incidencia): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO mantenimiento_incidencias (
                codigo, propiedad_id, unidad_id, reportado_por_persona_id, categoria, severidad,
                titulo, descripcion, ubicacion_detallada, estado, motivo_cierre,
                creado_por_actor_id, cerrado_por_actor_id, creado_en
            ) VALUES (
                :codigo, :propiedad_id, :unidad_id, :reportado_por_persona_id, :categoria, :severidad,
                :titulo, :descripcion, :ubicacion_detallada, :estado, :motivo_cierre,
                :creado_por_actor_id, :cerrado_por_actor_id, NOW()
            )'
        );

        $stmt->execute([
            'codigo' => $incidencia->obtenerCodigo(),
            'propiedad_id' => $incidencia->obtenerPropiedadId(),
            'unidad_id' => $incidencia->obtenerUnidadId(),
            'reportado_por_persona_id' => $incidencia->obtenerReportadoPorPersonaId(),
            'categoria' => $incidencia->obtenerCategoria(),
            'severidad' => $incidencia->obtenerSeveridad(),
            'titulo' => $incidencia->obtenerTitulo(),
            'descripcion' => $incidencia->obtenerDescripcion(),
            'ubicacion_detallada' => $incidencia->obtenerUbicacionDetallada(),
            'estado' => $incidencia->obtenerEstado(),
            'motivo_cierre' => $incidencia->obtenerMotivoCierre(),
            'creado_por_actor_id' => $incidencia->obtenerCreadoPorActorId(),
            'cerrado_por_actor_id' => $incidencia->obtenerCerradoPorActorId(),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function obtenerPorId(int $id, bool $bloquear = false): ?Incidencia
    {
        $sql = 'SELECT 
                    i.*,
                    p.nombre AS propiedad_nombre,
                    u.codigo AS unidad_numero,
                    u.nombre AS unidad_nombre,
                    TRIM(CONCAT(per.nombres, " ", per.apellido_paterno, " ", COALESCE(per.apellido_materno, ""))) AS reportado_por_nombre,
                    COALESCE((SELECT pd.numero_documento FROM personas_documentos pd WHERE pd.persona_id = per.id LIMIT 1), "") AS reportado_por_documento,
                    (SELECT COUNT(*) FROM mantenimiento_orden_incidencias moi WHERE moi.incidencia_id = i.id) AS ordenes_asociadas_count
                FROM mantenimiento_incidencias i
                INNER JOIN propiedades p ON p.id = i.propiedad_id
                LEFT JOIN unidades u ON u.id = i.unidad_id
                INNER JOIN personas per ON per.id = i.reportado_por_persona_id
                WHERE i.id = :id
                LIMIT 1' . ($bloquear ? ' FOR UPDATE' : '');

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? Incidencia::desdeArreglo($fila) : null;
    }

    public function obtenerPorCodigo(string $codigo, bool $bloquear = false): ?Incidencia
    {
        $sql = 'SELECT 
                    i.*,
                    p.nombre AS propiedad_nombre,
                    u.codigo AS unidad_numero,
                    u.nombre AS unidad_nombre,
                    TRIM(CONCAT(per.nombres, " ", per.apellido_paterno, " ", COALESCE(per.apellido_materno, ""))) AS reportado_por_nombre,
                    COALESCE((SELECT pd.numero_documento FROM personas_documentos pd WHERE pd.persona_id = per.id LIMIT 1), "") AS reportado_por_documento,
                    (SELECT COUNT(*) FROM mantenimiento_orden_incidencias moi WHERE moi.incidencia_id = i.id) AS ordenes_asociadas_count
                FROM mantenimiento_incidencias i
                INNER JOIN propiedades p ON p.id = i.propiedad_id
                LEFT JOIN unidades u ON u.id = i.unidad_id
                INNER JOIN personas per ON per.id = i.reportado_por_persona_id
                WHERE i.codigo = :codigo
                LIMIT 1' . ($bloquear ? ' FOR UPDATE' : '');

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['codigo' => $codigo]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? Incidencia::desdeArreglo($fila) : null;
    }

    public function actualizarEstado(int $id, string $nuevoEstado, ?string $motivoCierre = null, ?int $cerradoPorActorId = null): bool
    {
        $esCierre = in_array($nuevoEstado, ['RESUELTA_DIRECTA', 'DESESTIMADA'], true);
        $sql = 'UPDATE mantenimiento_incidencias SET 
                    estado = :estado,
                    motivo_cierre = COALESCE(:motivo_cierre, motivo_cierre),
                    cerrado_por_actor_id = :cerrado_por_actor_id,
                    resuelto_en = ' . ($esCierre ? 'NOW()' : 'resuelto_en') . '
                WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'id' => $id,
            'estado' => $nuevoEstado,
            'motivo_cierre' => $motivoCierre,
            'cerrado_por_actor_id' => $cerradoPorActorId,
        ]);
    }

    public function actualizar(Incidencia $incidencia): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE mantenimiento_incidencias SET
                propiedad_id = :propiedad_id,
                unidad_id = :unidad_id,
                categoria = :categoria,
                severidad = :severidad,
                titulo = :titulo,
                descripcion = :descripcion,
                ubicacion_detallada = :ubicacion_detallada,
                estado = :estado,
                motivo_cierre = :motivo_cierre,
                cerrado_por_actor_id = :cerrado_por_actor_id
            WHERE id = :id'
        );

        return $stmt->execute([
            'id' => $incidencia->obtenerId(),
            'propiedad_id' => $incidencia->obtenerPropiedadId(),
            'unidad_id' => $incidencia->obtenerUnidadId(),
            'categoria' => $incidencia->obtenerCategoria(),
            'severidad' => $incidencia->obtenerSeveridad(),
            'titulo' => $incidencia->obtenerTitulo(),
            'descripcion' => $incidencia->obtenerDescripcion(),
            'ubicacion_detallada' => $incidencia->obtenerUbicacionDetallada(),
            'estado' => $incidencia->obtenerEstado(),
            'motivo_cierre' => $incidencia->obtenerMotivoCierre(),
            'cerrado_por_actor_id' => $incidencia->obtenerCerradoPorActorId(),
        ]);
    }

    /**
     * @param array<string, mixed> $filtros
     * @return array<int, Incidencia>
     */
    public function listar(array $filtros = []): array
    {
        $sql = 'SELECT 
                    i.*,
                    p.nombre AS propiedad_nombre,
                    u.codigo AS unidad_numero,
                    u.nombre AS unidad_nombre,
                    TRIM(CONCAT(per.nombres, " ", per.apellido_paterno, " ", COALESCE(per.apellido_materno, ""))) AS reportado_por_nombre,
                    COALESCE((SELECT pd.numero_documento FROM personas_documentos pd WHERE pd.persona_id = per.id LIMIT 1), "") AS reportado_por_documento,
                    (SELECT COUNT(*) FROM mantenimiento_orden_incidencias moi WHERE moi.incidencia_id = i.id) AS ordenes_asociadas_count
                FROM mantenimiento_incidencias i
                INNER JOIN propiedades p ON p.id = i.propiedad_id
                LEFT JOIN unidades u ON u.id = i.unidad_id
                INNER JOIN personas per ON per.id = i.reportado_por_persona_id
                WHERE 1=1';

        $params = [];

        if (!empty($filtros['propiedad_id'])) {
            $sql .= ' AND i.propiedad_id = :propiedad_id';
            $params['propiedad_id'] = (int) $filtros['propiedad_id'];
        }

        if (!empty($filtros['unidad_id'])) {
            $sql .= ' AND i.unidad_id = :unidad_id';
            $params['unidad_id'] = (int) $filtros['unidad_id'];
        }

        if (!empty($filtros['estado'])) {
            $sql .= ' AND i.estado = :estado';
            $params['estado'] = (string) $filtros['estado'];
        }

        if (!empty($filtros['severidad'])) {
            $sql .= ' AND i.severidad = :severidad';
            $params['severidad'] = (string) $filtros['severidad'];
        }

        if (!empty($filtros['categoria'])) {
            $sql .= ' AND i.categoria = :categoria';
            $params['categoria'] = (string) $filtros['categoria'];
        }

        if (!empty($filtros['busqueda'])) {
            $sql .= ' AND (i.codigo LIKE :busqueda OR i.titulo LIKE :busqueda OR i.descripcion LIKE :busqueda)';
            $params['busqueda'] = '%' . trim((string) $filtros['busqueda']) . '%';
        }

        $sql .= ' ORDER BY i.id DESC';

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
            $resultado[] = Incidencia::desdeArreglo($fila);
        }

        return $resultado;
    }

    /**
     * @param array<string, mixed> $filtros
     */
    public function contar(array $filtros = []): int
    {
        $sql = 'SELECT COUNT(*) FROM mantenimiento_incidencias i WHERE 1=1';
        $params = [];

        if (!empty($filtros['propiedad_id'])) {
            $sql .= ' AND i.propiedad_id = :propiedad_id';
            $params['propiedad_id'] = (int) $filtros['propiedad_id'];
        }

        if (!empty($filtros['unidad_id'])) {
            $sql .= ' AND i.unidad_id = :unidad_id';
            $params['unidad_id'] = (int) $filtros['unidad_id'];
        }

        if (!empty($filtros['estado'])) {
            $sql .= ' AND i.estado = :estado';
            $params['estado'] = (string) $filtros['estado'];
        }

        if (!empty($filtros['severidad'])) {
            $sql .= ' AND i.severidad = :severidad';
            $params['severidad'] = (string) $filtros['severidad'];
        }

        if (!empty($filtros['categoria'])) {
            $sql .= ' AND i.categoria = :categoria';
            $params['categoria'] = (string) $filtros['categoria'];
        }

        if (!empty($filtros['busqueda'])) {
            $sql .= ' AND (i.codigo LIKE :busqueda OR i.titulo LIKE :busqueda OR i.descripcion LIKE :busqueda)';
            $params['busqueda'] = '%' . trim((string) $filtros['busqueda']) . '%';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function generarSiguienteCodigo(): string
    {
        $prefijo = 'INC-' . date('Ymd') . '-';
        $stmt = $this->pdo->prepare(
            'SELECT codigo FROM mantenimiento_incidencias 
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

        $stmtExiste = $this->pdo->prepare('SELECT 1 FROM mantenimiento_incidencias WHERE codigo = :cod LIMIT 1');
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
