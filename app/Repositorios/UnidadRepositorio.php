<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\Unidad;
use CamargoPMS\Nucleo\BaseDatos;
use PDO;

/**
 * Repositorio para la persistencia, consulta y gestión del ciclo de vida de unidades.
 *
 * Principio vinculante: UNIDAD ≠ REGISTRO DESECHABLE.
 * No expone métodos de eliminación física ('DELETE FROM unidades').
 */
class UnidadRepositorio
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? BaseDatos::conexion();
    }

    /**
     * Busca una unidad por su ID primario, incluyendo metadatos de su propiedad y tipología.
     */
    public function buscarPorId(int $id): ?Unidad
    {
        $sql = 'SELECT u.*, 
                       p.nombre AS propiedad_nombre,
                       p.codigo AS propiedad_codigo,
                       p.estado AS propiedad_estado,
                       tu.codigo AS tipo_unidad_codigo,
                       tu.nombre AS tipo_unidad_nombre
                FROM unidades u
                INNER JOIN propiedades p ON u.propiedad_id = p.id
                INNER JOIN tipos_unidad tu ON u.tipo_unidad_id = tu.id
                WHERE u.id = :id
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? Unidad::desdeArreglo($fila) : null;
    }

    /**
     * Alias de buscarPorId para compatibilidad transversal con servicios del PMS.
     */
    public function obtenerPorId(int $id): ?Unidad
    {
        return $this->buscarPorId($id);
    }

    /**
     * Busca una unidad por propiedad y código técnico.
     */
    public function buscarPorPropiedadYCodigo(int $propiedadId, string $codigo): ?Unidad
    {
        $sql = 'SELECT u.*, 
                       p.nombre AS propiedad_nombre,
                       p.codigo AS propiedad_codigo,
                       p.estado AS propiedad_estado,
                       tu.codigo AS tipo_unidad_codigo,
                       tu.nombre AS tipo_unidad_nombre
                FROM unidades u
                INNER JOIN propiedades p ON u.propiedad_id = p.id
                INNER JOIN tipos_unidad tu ON u.tipo_unidad_id = tu.id
                WHERE u.propiedad_id = :propiedad_id AND u.codigo = :codigo
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':propiedad_id', $propiedadId, PDO::PARAM_INT);
        $stmt->bindValue(':codigo', strtoupper(trim($codigo)), PDO::PARAM_STR);
        $stmt->execute();
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? Unidad::desdeArreglo($fila) : null;
    }

    /**
     * Verifica si ya existe un código dentro de una propiedad (excluyendo opcionalmente un ID).
     */
    public function existeCodigoEnPropiedad(int $propiedadId, string $codigo, ?int $excluirId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM unidades WHERE propiedad_id = :propiedad_id AND codigo = :codigo';
        if ($excluirId !== null) {
            $sql .= ' AND id <> :excluir_id';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':propiedad_id', $propiedadId, PDO::PARAM_INT);
        $stmt->bindValue(':codigo', strtoupper(trim($codigo)), PDO::PARAM_STR);
        if ($excluirId !== null) {
            $stmt->bindValue(':excluir_id', $excluirId, PDO::PARAM_INT);
        }
        $stmt->execute();

        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Inserta un nuevo registro de unidad física en la base de datos.
     */
    public function insertar(Unidad $unidad): int
    {
        $sql = 'INSERT INTO unidades (
                    propiedad_id, tipo_unidad_id, codigo, nombre, descripcion,
                    piso_nivel, capacidad_personas, dormitorios, banos, area_m2,
                    estado, observaciones, creado_en, actualizado_en
                ) VALUES (
                    :propiedad_id, :tipo_unidad_id, :codigo, :nombre, :descripcion,
                    :piso_nivel, :capacidad_personas, :dormitorios, :banos, :area_m2,
                    :estado, :observaciones, NOW(), NOW()
                )';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':propiedad_id', $unidad->obtenerPropiedadId(), PDO::PARAM_INT);
        $stmt->bindValue(':tipo_unidad_id', $unidad->obtenerTipoUnidadId(), PDO::PARAM_INT);
        $stmt->bindValue(':codigo', strtoupper(trim($unidad->obtenerCodigo())), PDO::PARAM_STR);
        $stmt->bindValue(':nombre', trim($unidad->obtenerNombre()), PDO::PARAM_STR);
        $stmt->bindValue(':descripcion', $unidad->obtenerDescripcion(), $unidad->obtenerDescripcion() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':piso_nivel', $unidad->obtenerPisoNivel(), $unidad->obtenerPisoNivel() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':capacidad_personas', $unidad->obtenerCapacidadPersonas(), PDO::PARAM_INT);
        $stmt->bindValue(':dormitorios', $unidad->obtenerDormitorios(), PDO::PARAM_INT);
        $stmt->bindValue(':banos', $unidad->obtenerBanos(), PDO::PARAM_STR);
        $stmt->bindValue(':area_m2', $unidad->obtenerAreaM2(), $unidad->obtenerAreaM2() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':estado', $unidad->obtenerEstado(), PDO::PARAM_STR);
        $stmt->bindValue(':observaciones', $unidad->obtenerObservaciones(), $unidad->obtenerObservaciones() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);

        $stmt->execute();
        $id = (int)$this->pdo->lastInsertId();
        $unidad->fijarId($id);

        return $id;
    }

    /**
     * Actualiza la información técnica de una unidad existente.
     */
    public function actualizar(Unidad $unidad): bool
    {
        if ($unidad->obtenerId() === null) {
            return false;
        }

        $sql = 'UPDATE unidades SET
                    tipo_unidad_id = :tipo_unidad_id,
                    codigo = :codigo,
                    nombre = :nombre,
                    descripcion = :descripcion,
                    piso_nivel = :piso_nivel,
                    capacidad_personas = :capacidad_personas,
                    dormitorios = :dormitorios,
                    banos = :banos,
                    area_m2 = :area_m2,
                    estado = :estado,
                    observaciones = :observaciones,
                    actualizado_en = NOW()
                WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':tipo_unidad_id', $unidad->obtenerTipoUnidadId(), PDO::PARAM_INT);
        $stmt->bindValue(':codigo', strtoupper(trim($unidad->obtenerCodigo())), PDO::PARAM_STR);
        $stmt->bindValue(':nombre', trim($unidad->obtenerNombre()), PDO::PARAM_STR);
        $stmt->bindValue(':descripcion', $unidad->obtenerDescripcion(), $unidad->obtenerDescripcion() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':piso_nivel', $unidad->obtenerPisoNivel(), $unidad->obtenerPisoNivel() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':capacidad_personas', $unidad->obtenerCapacidadPersonas(), PDO::PARAM_INT);
        $stmt->bindValue(':dormitorios', $unidad->obtenerDormitorios(), PDO::PARAM_INT);
        $stmt->bindValue(':banos', $unidad->obtenerBanos(), PDO::PARAM_STR);
        $stmt->bindValue(':area_m2', $unidad->obtenerAreaM2(), $unidad->obtenerAreaM2() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':estado', $unidad->obtenerEstado(), PDO::PARAM_STR);
        $stmt->bindValue(':observaciones', $unidad->obtenerObservaciones(), $unidad->obtenerObservaciones() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':id', $unidad->obtenerId(), PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Cambia el estado operacional de una unidad (ACTIVO ↔ INACTIVO).
     */
    public function cambiarEstado(int $id, string $nuevoEstado): bool
    {
        $estadoNormalizado = strtoupper(trim($nuevoEstado));
        if (!in_array($estadoNormalizado, ['ACTIVO', 'INACTIVO'], true)) {
            return false;
        }

        $stmt = $this->pdo->prepare('UPDATE unidades SET estado = :estado, actualizado_en = NOW() WHERE id = :id');
        $stmt->bindValue(':estado', $estadoNormalizado, PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Lista unidades con filtros multicriterio y paginación.
     *
     * @param array<string, mixed> $filtros
     * @param int $pagina
     * @param int $limite
     * @return array<Unidad>
     */
    public function listar(array $filtros = [], int $pagina = 1, int $limite = 20): array
    {
        $condiciones = [];
        $parametros = [];

        if (!empty($filtros['propiedad_id'])) {
            $condiciones[] = 'u.propiedad_id = :propiedad_id';
            $parametros[':propiedad_id'] = [(int)$filtros['propiedad_id'], PDO::PARAM_INT];
        }

        if (!empty($filtros['tipo_unidad_id'])) {
            $condiciones[] = 'u.tipo_unidad_id = :tipo_unidad_id';
            $parametros[':tipo_unidad_id'] = [(int)$filtros['tipo_unidad_id'], PDO::PARAM_INT];
        }

        if (!empty($filtros['estado']) && in_array(strtoupper((string)$filtros['estado']), ['ACTIVO', 'INACTIVO'], true)) {
            $condiciones[] = 'u.estado = :estado';
            $parametros[':estado'] = [strtoupper((string)$filtros['estado']), PDO::PARAM_STR];
        }

        if (!empty($filtros['busqueda'])) {
            $termino = '%' . trim((string)$filtros['busqueda']) . '%';
            $condiciones[] = '(u.nombre LIKE :b_nom OR u.codigo LIKE :b_cod OR p.nombre LIKE :b_prop)';
            $parametros[':b_nom'] = [$termino, PDO::PARAM_STR];
            $parametros[':b_cod'] = [$termino, PDO::PARAM_STR];
            $parametros[':b_prop'] = [$termino, PDO::PARAM_STR];
        }

        $where = !empty($condiciones) ? 'WHERE ' . implode(' AND ', $condiciones) : '';
        $offset = max(0, ($pagina - 1) * $limite);

        $sql = "SELECT u.*, 
                       p.nombre AS propiedad_nombre,
                       p.codigo AS propiedad_codigo,
                       p.estado AS propiedad_estado,
                       tu.codigo AS tipo_unidad_codigo,
                       tu.nombre AS tipo_unidad_nombre
                FROM unidades u
                INNER JOIN propiedades p ON u.propiedad_id = p.id
                INNER JOIN tipos_unidad tu ON u.tipo_unidad_id = tu.id
                {$where}
                ORDER BY p.nombre ASC, u.codigo ASC
                LIMIT :limite OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($parametros as $placeholder => [$valor, $tipoPdo]) {
            $stmt->bindValue($placeholder, $valor, $tipoPdo);
        }
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn(array $f): Unidad => Unidad::desdeArreglo($f), $filas);
    }

    /**
     * Cuenta el total de unidades que coinciden con los criterios de filtro.
     *
     * @param array<string, mixed> $filtros
     */
    public function contar(array $filtros = []): int
    {
        $condiciones = [];
        $parametros = [];

        if (!empty($filtros['propiedad_id'])) {
            $condiciones[] = 'u.propiedad_id = :propiedad_id';
            $parametros[':propiedad_id'] = [(int)$filtros['propiedad_id'], PDO::PARAM_INT];
        }

        if (!empty($filtros['tipo_unidad_id'])) {
            $condiciones[] = 'u.tipo_unidad_id = :tipo_unidad_id';
            $parametros[':tipo_unidad_id'] = [(int)$filtros['tipo_unidad_id'], PDO::PARAM_INT];
        }

        if (!empty($filtros['estado']) && in_array(strtoupper((string)$filtros['estado']), ['ACTIVO', 'INACTIVO'], true)) {
            $condiciones[] = 'u.estado = :estado';
            $parametros[':estado'] = [strtoupper((string)$filtros['estado']), PDO::PARAM_STR];
        }

        if (!empty($filtros['busqueda'])) {
            $termino = '%' . trim((string)$filtros['busqueda']) . '%';
            $condiciones[] = '(u.nombre LIKE :b_nom OR u.codigo LIKE :b_cod OR p.nombre LIKE :b_prop)';
            $parametros[':b_nom'] = [$termino, PDO::PARAM_STR];
            $parametros[':b_cod'] = [$termino, PDO::PARAM_STR];
            $parametros[':b_prop'] = [$termino, PDO::PARAM_STR];
        }

        $where = !empty($condiciones) ? 'WHERE ' . implode(' AND ', $condiciones) : '';

        $sql = "SELECT COUNT(*) FROM unidades u
                INNER JOIN propiedades p ON u.propiedad_id = p.id
                INNER JOIN tipos_unidad tu ON u.tipo_unidad_id = tu.id
                {$where}";

        $stmt = $this->pdo->prepare($sql);
        foreach ($parametros as $placeholder => [$valor, $tipoPdo]) {
            $stmt->bindValue($placeholder, $valor, $tipoPdo);
        }
        $stmt->execute();

        return (int)$stmt->fetchColumn();
    }

    /**
     * Lista todas las unidades pertenecientes a una propiedad específica.
     *
     * @return array<Unidad>
     */
    public function listarPorPropiedad(int $propiedadId, ?string $estado = null): array
    {
        $sql = 'SELECT u.*, 
                       p.nombre AS propiedad_nombre,
                       p.codigo AS propiedad_codigo,
                       p.estado AS propiedad_estado,
                       tu.codigo AS tipo_unidad_codigo,
                       tu.nombre AS tipo_unidad_nombre
                FROM unidades u
                INNER JOIN propiedades p ON u.propiedad_id = p.id
                INNER JOIN tipos_unidad tu ON u.tipo_unidad_id = tu.id
                WHERE u.propiedad_id = :propiedad_id';

        if ($estado !== null && in_array(strtoupper(trim($estado)), ['ACTIVO', 'INACTIVO'], true)) {
            $sql .= ' AND u.estado = :estado';
        }

        $sql .= ' ORDER BY u.codigo ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':propiedad_id', $propiedadId, PDO::PARAM_INT);
        if ($estado !== null && in_array(strtoupper(trim($estado)), ['ACTIVO', 'INACTIVO'], true)) {
            $stmt->bindValue(':estado', strtoupper(trim($estado)), PDO::PARAM_STR);
        }
        $stmt->execute();

        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn(array $f): Unidad => Unidad::desdeArreglo($f), $filas);
    }

    /**
     * Cuenta el total de unidades de una propiedad.
     */
    public function contarPorPropiedad(int $propiedadId, ?string $estado = null): int
    {
        $sql = 'SELECT COUNT(*) FROM unidades WHERE propiedad_id = :propiedad_id';
        if ($estado !== null && in_array(strtoupper(trim($estado)), ['ACTIVO', 'INACTIVO'], true)) {
            $sql .= ' AND estado = :estado';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':propiedad_id', $propiedadId, PDO::PARAM_INT);
        if ($estado !== null && in_array(strtoupper(trim($estado)), ['ACTIVO', 'INACTIVO'], true)) {
            $stmt->bindValue(':estado', strtoupper(trim($estado)), PDO::PARAM_STR);
        }
        $stmt->execute();

        return (int)$stmt->fetchColumn();
    }
}
