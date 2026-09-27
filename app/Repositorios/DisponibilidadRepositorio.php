<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\BloqueoUnidad;
use CamargoPMS\Modelos\InventarioDiario;
use PDO;

/**
 * Repositorio para la persistencia y consulta del inventario diario y bloqueos (DISPONIBILIDAD-1).
 *
 * Principios vinculantes:
 * - D-067: Modelo Híbrido Sparse con UNIQUE(unidad_id, fecha).
 * - Orden determinista ORDER BY unidad_id ASC, fecha ASC para mitigación de deadlocks.
 * - Disponibilidad definida como ausencia de fila en inventario_diario_unidades.
 */
class DisponibilidadRepositorio
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Retorna la conexión PDO subyacente para control transaccional explícito.
     */
    public function obtenerPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Obtiene las noches ocupadas/bloqueadas de una unidad en el intervalo semiabierto [fechaInicio, fechaFin).
     *
     * @param int $unidadId
     * @param string $fechaInicio Inclusive (DATE Y-m-d)
     * @param string $fechaFin Exclusive (DATE Y-m-d)
     * @return array<int, array<string, mixed>>
     */
    public function obtenerNochesOcupadas(int $unidadId, string $fechaInicio, string $fechaFin): array
    {
        $sql = 'SELECT id, unidad_id, fecha, tipo_bloqueo, origen_tipo, origen_id, creado_en
                FROM inventario_diario_unidades
                WHERE unidad_id = :unidad_id
                  AND fecha >= :fecha_inicio
                  AND fecha < :fecha_fin
                ORDER BY fecha ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':unidad_id', $unidadId, PDO::PARAM_INT);
        $stmt->bindValue(':fecha_inicio', $fechaInicio, PDO::PARAM_STR);
        $stmt->bindValue(':fecha_fin', $fechaFin, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene las noches ocupadas para una lista de unidades en el intervalo semiabierto [fechaInicio, fechaFin).
     * Retorna un mapa: [unidad_id => [fecha => array{tipo_bloqueo, origen_tipo, origen_id}]]
     *
     * @param array<int> $unidadesIds
     * @param string $fechaInicio Inclusive
     * @param string $fechaFin Exclusive
     * @return array<int, array<string, array<string, mixed>>>
     */
    public function obtenerNochesOcupadasPorUnidades(array $unidadesIds, string $fechaInicio, string $fechaFin): array
    {
        if (empty($unidadesIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($unidadesIds), '?'));
        $sql = "SELECT unidad_id, fecha, tipo_bloqueo, origen_tipo, origen_id
                FROM inventario_diario_unidades
                WHERE unidad_id IN ($placeholders)
                  AND fecha >= ?
                  AND fecha < ?
                ORDER BY unidad_id ASC, fecha ASC";

        $stmt = $this->pdo->prepare($sql);
        $pos = 1;
        foreach ($unidadesIds as $uId) {
            $stmt->bindValue($pos++, (int) $uId, PDO::PARAM_INT);
        }
        $stmt->bindValue($pos++, $fechaInicio, PDO::PARAM_STR);
        $stmt->bindValue($pos, $fechaFin, PDO::PARAM_STR);
        $stmt->execute();

        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $mapa = [];
        foreach ($unidadesIds as $uId) {
            $mapa[(int) $uId] = [];
        }

        foreach ($filas as $fila) {
            $uId = (int) $fila['unidad_id'];
            $fecha = (string) $fila['fecha'];
            $mapa[$uId][$fecha] = $fila;
        }

        return $mapa;
    }

    /**
     * Inserta atómicamente una noche en el inventario diario.
     * Sujeto a la restricción UNIQUE(unidad_id, fecha) en InnoDB.
     *
     * @param int $unidadId
     * @param string $fecha
     * @param string $tipoBloqueo
     * @param string $origenTipo
     * @param int $origenId
     * @throws \PDOException Si existe colisión 1062, timeout 1205 o deadlock 1213
     */
    public function insertarInventarioNoche(
        int $unidadId,
        string $fecha,
        string $tipoBloqueo,
        string $origenTipo,
        int $origenId
    ): void {
        $sql = 'INSERT INTO inventario_diario_unidades (
                    unidad_id, fecha, tipo_bloqueo, origen_tipo, origen_id, creado_en
                ) VALUES (
                    :unidad_id, :fecha, :tipo_bloqueo, :origen_tipo, :origen_id, NOW()
                )';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':unidad_id', $unidadId, PDO::PARAM_INT);
        $stmt->bindValue(':fecha', $fecha, PDO::PARAM_STR);
        $stmt->bindValue(':tipo_bloqueo', $tipoBloqueo, PDO::PARAM_STR);
        $stmt->bindValue(':origen_tipo', $origenTipo, PDO::PARAM_STR);
        $stmt->bindValue(':origen_id', $origenId, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Elimina atómicamente las noches del inventario diario asociadas a un origen.
     * Utilizado para liberación de bloqueos o cancelaciones (D-067).
     *
     * @param string $origenTipo
     * @param int $origenId
     * @return int Cantidad de noches eliminadas
     */
    public function eliminarInventarioPorOrigen(string $origenTipo, int $origenId): int
    {
        $sql = 'DELETE FROM inventario_diario_unidades
                WHERE origen_tipo = :origen_tipo AND origen_id = :origen_id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':origen_tipo', $origenTipo, PDO::PARAM_STR);
        $stmt->bindValue(':origen_id', $origenId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

    /**
     * Inserta un nuevo registro maestro de bloqueo de unidad.
     *
     * @param BloqueoUnidad $bloqueo
     * @return BloqueoUnidad Con ID primario asignado
     */
    public function crearBloqueo(BloqueoUnidad $bloqueo): BloqueoUnidad
    {
        $sql = 'INSERT INTO bloqueos_unidad (
                    unidad_id, fecha_inicio, fecha_fin, noches,
                    motivo, tipo, estado, creado_por_actor_id, creado_en
                ) VALUES (
                    :unidad_id, :fecha_inicio, :fecha_fin, :noches,
                    :motivo, :tipo, :estado, :creado_por_actor_id, NOW()
                )';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':unidad_id', $bloqueo->obtenerUnidadId(), PDO::PARAM_INT);
        $stmt->bindValue(':fecha_inicio', $bloqueo->obtenerFechaInicio(), PDO::PARAM_STR);
        $stmt->bindValue(':fecha_fin', $bloqueo->obtenerFechaFin(), PDO::PARAM_STR);
        $stmt->bindValue(':noches', $bloqueo->obtenerNoches(), PDO::PARAM_INT);
        $stmt->bindValue(':motivo', $bloqueo->obtenerMotivo(), PDO::PARAM_STR);
        $stmt->bindValue(':tipo', $bloqueo->obtenerTipo(), PDO::PARAM_STR);
        $stmt->bindValue(':estado', $bloqueo->obtenerEstado(), PDO::PARAM_STR);
        $stmt->bindValue(':creado_por_actor_id', $bloqueo->obtenerCreadoPorActorId(), $bloqueo->obtenerCreadoPorActorId() === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->execute();

        $nuevoId = (int) $this->pdo->lastInsertId();

        return $this->buscarBloqueoPorId($nuevoId) ?? $bloqueo;
    }

    /**
     * Busca un bloqueo de unidad por su ID primario, con metadatos de unidad y actores.
     *
     * @param int $id
     * @return BloqueoUnidad|null
     */
    public function buscarBloqueoPorId(int $id): ?BloqueoUnidad
    {
        $sql = 'SELECT b.*,
                       u.codigo AS unidad_codigo,
                       u.nombre AS unidad_nombre,
                       u.propiedad_id,
                       p.nombre AS propiedad_nombre,
                       ac.nombre AS creador_nombre,
                       al.nombre AS liberador_nombre
                FROM bloqueos_unidad b
                INNER JOIN unidades u ON b.unidad_id = u.id
                INNER JOIN propiedades p ON u.propiedad_id = p.id
                LEFT JOIN actores ac ON b.creado_por_actor_id = ac.id
                LEFT JOIN actores al ON b.liberado_por_actor_id = al.id
                WHERE b.id = :id
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? BloqueoUnidad::hidratar($fila) : null;
    }

    /**
     * Actualiza el estado de un bloqueo (ej. ACTIVO -> LIBERADO) y registra el actor liberador.
     *
     * @param int $id
     * @param string $estado
     * @param int|null $liberadoPorActorId
     * @return bool
     */
    public function actualizarEstadoBloqueo(int $id, string $estado, ?int $liberadoPorActorId = null): bool
    {
        $sql = 'UPDATE bloqueos_unidad SET
                    estado = :estado,
                    liberado_por_actor_id = :liberado_por_actor_id,
                    liberado_en = NOW()
                WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':estado', $estado, PDO::PARAM_STR);
        $stmt->bindValue(':liberado_por_actor_id', $liberadoPorActorId, $liberadoPorActorId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Lista bloqueos de unidades con filtros y paginación.
     *
     * @param array<string, mixed> $filtros
     * @param int $limite
     * @param int $offset
     * @return array<int, BloqueoUnidad>
     */
    public function listarBloqueos(array $filtros = [], int $limite = 20, int $offset = 0): array
    {
        $condiciones = ['1=1'];
        $params = [];

        if (!empty($filtros['propiedad_id'])) {
            $condiciones[] = 'u.propiedad_id = :propiedad_id';
            $params[':propiedad_id'] = (int) $filtros['propiedad_id'];
        }

        if (!empty($filtros['unidad_id'])) {
            $condiciones[] = 'b.unidad_id = :unidad_id';
            $params[':unidad_id'] = (int) $filtros['unidad_id'];
        }

        if (!empty($filtros['estado'])) {
            $condiciones[] = 'b.estado = :estado';
            $params[':estado'] = strtoupper(trim((string) $filtros['estado']));
        }

        if (!empty($filtros['tipo'])) {
            $condiciones[] = 'b.tipo = :tipo';
            $params[':tipo'] = strtoupper(trim((string) $filtros['tipo']));
        }

        if (!empty($filtros['fecha_desde'])) {
            $condiciones[] = 'b.fecha_fin >= :fecha_desde';
            $params[':fecha_desde'] = trim((string) $filtros['fecha_desde']);
        }

        if (!empty($filtros['fecha_hasta'])) {
            $condiciones[] = 'b.fecha_inicio <= :fecha_hasta';
            $params[':fecha_hasta'] = trim((string) $filtros['fecha_hasta']);
        }

        $where = implode(' AND ', $condiciones);
        $sql = "SELECT b.*,
                       u.codigo AS unidad_codigo,
                       u.nombre AS unidad_nombre,
                       u.propiedad_id,
                       p.nombre AS propiedad_nombre,
                       ac.nombre AS creador_nombre,
                       al.nombre AS liberador_nombre
                FROM bloqueos_unidad b
                INNER JOIN unidades u ON b.unidad_id = u.id
                INNER JOIN propiedades p ON u.propiedad_id = p.id
                LEFT JOIN actores ac ON b.creado_por_actor_id = ac.id
                LEFT JOIN actores al ON b.liberado_por_actor_id = al.id
                WHERE {$where}
                ORDER BY b.fecha_inicio DESC, b.id DESC
                LIMIT :limite OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limite', max(1, $limite), PDO::PARAM_INT);
        $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();

        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $resultado = [];
        foreach ($filas as $fila) {
            $resultado[] = BloqueoUnidad::hidratar($fila);
        }

        return $resultado;
    }

    /**
     * Cuenta el total de bloqueos que coinciden con los filtros.
     *
     * @param array<string, mixed> $filtros
     * @return int
     */
    public function contarBloqueos(array $filtros = []): int
    {
        $condiciones = ['1=1'];
        $params = [];

        if (!empty($filtros['propiedad_id'])) {
            $condiciones[] = 'u.propiedad_id = :propiedad_id';
            $params[':propiedad_id'] = (int) $filtros['propiedad_id'];
        }

        if (!empty($filtros['unidad_id'])) {
            $condiciones[] = 'b.unidad_id = :unidad_id';
            $params[':unidad_id'] = (int) $filtros['unidad_id'];
        }

        if (!empty($filtros['estado'])) {
            $condiciones[] = 'b.estado = :estado';
            $params[':estado'] = strtoupper(trim((string) $filtros['estado']));
        }

        if (!empty($filtros['tipo'])) {
            $condiciones[] = 'b.tipo = :tipo';
            $params[':tipo'] = strtoupper(trim((string) $filtros['tipo']));
        }

        if (!empty($filtros['fecha_desde'])) {
            $condiciones[] = 'b.fecha_fin >= :fecha_desde';
            $params[':fecha_desde'] = trim((string) $filtros['fecha_desde']);
        }

        if (!empty($filtros['fecha_hasta'])) {
            $condiciones[] = 'b.fecha_inicio <= :fecha_hasta';
            $params[':fecha_hasta'] = trim((string) $filtros['fecha_hasta']);
        }

        $where = implode(' AND ', $condiciones);
        $sql = "SELECT COUNT(*)
                FROM bloqueos_unidad b
                INNER JOIN unidades u ON b.unidad_id = u.id
                WHERE {$where}";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * Obtiene todas las filas de inventario diario para una propiedad en un rango de fechas.
     * Usado para generar matrices y calendarios de ocupación.
     *
     * @param int $propiedadId
     * @param string $fechaInicio Inclusive
     * @param string $fechaFin Exclusive
     * @return array<int, array<string, mixed>>
     */
    public function obtenerInventarioPorPropiedadYRango(int $propiedadId, string $fechaInicio, string $fechaFin): array
    {
        $sql = 'SELECT inv.id, inv.unidad_id, inv.fecha, inv.tipo_bloqueo, inv.origen_tipo, inv.origen_id,
                       u.codigo AS unidad_codigo, u.nombre AS unidad_nombre
                FROM inventario_diario_unidades inv
                INNER JOIN unidades u ON inv.unidad_id = u.id
                WHERE u.propiedad_id = :propiedad_id
                  AND inv.fecha >= :fecha_inicio
                  AND inv.fecha < :fecha_fin
                ORDER BY inv.unidad_id ASC, inv.fecha ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':propiedad_id', $propiedadId, PDO::PARAM_INT);
        $stmt->bindValue(':fecha_inicio', $fechaInicio, PDO::PARAM_STR);
        $stmt->bindValue(':fecha_fin', $fechaFin, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
