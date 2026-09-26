<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\Propiedad;
use CamargoPMS\Nucleo\BaseDatos;
use PDO;

/**
 * Repositorio de persistencia para el maestro de propiedades (tabla `propiedades`).
 *
 * Responsabilidad estricta:
 * - Consultas y persistencia mediante sentencias preparadas PDO.
 * - Sin reglas de negocio complejas en esta capa.
 * - Sin operaciones de eliminación física (`DELETE` = 0).
 */
class PropiedadRepositorio
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? BaseDatos::conexion();
    }

    /**
     * Retorna la conexión PDO subyacente.
     */
    public function obtenerPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Busca una propiedad por su identificador primario.
     *
     * @param int $id
     * @return Propiedad|null
     */
    public function buscarPorId(int $id): ?Propiedad
    {
        $sql = 'SELECT pr.*, pa.nombre AS pais_nombre
                FROM propiedades pr
                LEFT JOIN paises pa ON pr.pais_id = pa.id
                WHERE pr.id = :id
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fila) {
            return null;
        }

        return Propiedad::hidratar($fila);
    }

    /**
     * Busca una propiedad por su código técnico único.
     *
     * @param string $codigo
     * @return Propiedad|null
     */
    public function buscarPorCodigo(string $codigo): ?Propiedad
    {
        $sql = 'SELECT pr.*, pa.nombre AS pais_nombre
                FROM propiedades pr
                LEFT JOIN paises pa ON pr.pais_id = pa.id
                WHERE pr.codigo = :codigo
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':codigo', trim(strtoupper($codigo)), PDO::PARAM_STR);
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fila) {
            return null;
        }

        return Propiedad::hidratar($fila);
    }

    /**
     * Verifica si un código de propiedad ya se encuentra registrado.
     *
     * @param string $codigo
     * @param int|null $excluirId
     * @return bool
     */
    public function existeCodigo(string $codigo, ?int $excluirId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM propiedades WHERE codigo = :codigo';
        if ($excluirId !== null && $excluirId > 0) {
            $sql .= ' AND id <> :excluir_id';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':codigo', trim(strtoupper($codigo)), PDO::PARAM_STR);
        if ($excluirId !== null && $excluirId > 0) {
            $stmt->bindValue(':excluir_id', $excluirId, PDO::PARAM_INT);
        }
        $stmt->execute();

        return ((int) $stmt->fetchColumn()) > 0;
    }

    /**
     * Inserta una nueva propiedad en la base de datos.
     *
     * @param Propiedad $propiedad
     * @return Propiedad Entidad con ID asignado
     */
    public function insertar(Propiedad $propiedad): Propiedad
    {
        $sql = 'INSERT INTO propiedades (
                    codigo, nombre, descripcion, pais_id,
                    departamento, provincia, distrito, direccion, referencia,
                    latitud, longitud, estado, observaciones, creado_en, actualizado_en
                ) VALUES (
                    :codigo, :nombre, :descripcion, :pais_id,
                    :departamento, :provincia, :distrito, :direccion, :referencia,
                    :latitud, :longitud, :estado, :observaciones, NOW(), NOW()
                )';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':codigo', $propiedad->obtenerCodigo(), PDO::PARAM_STR);
        $stmt->bindValue(':nombre', $propiedad->obtenerNombre(), PDO::PARAM_STR);
        $stmt->bindValue(':descripcion', $propiedad->obtenerDescripcion(), $propiedad->obtenerDescripcion() === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':pais_id', $propiedad->obtenerPaisId(), PDO::PARAM_INT);
        $stmt->bindValue(':departamento', $propiedad->obtenerDepartamento(), $propiedad->obtenerDepartamento() === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':provincia', $propiedad->obtenerProvincia(), $propiedad->obtenerProvincia() === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':distrito', $propiedad->obtenerDistrito(), $propiedad->obtenerDistrito() === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':direccion', $propiedad->obtenerDireccion(), $propiedad->obtenerDireccion() === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':referencia', $propiedad->obtenerReferencia(), $propiedad->obtenerReferencia() === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':latitud', $propiedad->obtenerLatitud(), $propiedad->obtenerLatitud() === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':longitud', $propiedad->obtenerLongitud(), $propiedad->obtenerLongitud() === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':estado', $propiedad->obtenerEstado(), PDO::PARAM_STR);
        $stmt->bindValue(':observaciones', $propiedad->obtenerObservaciones(), $propiedad->obtenerObservaciones() === null ? PDO::PARAM_NULL : PDO::PARAM_STR);

        $stmt->execute();
        $nuevoId = (int) $this->pdo->lastInsertId();

        return $this->buscarPorId($nuevoId) ?? $propiedad;
    }

    /**
     * Actualiza la información de una propiedad existente.
     *
     * @param Propiedad $propiedad
     * @return bool
     */
    public function actualizar(Propiedad $propiedad): bool
    {
        $sql = 'UPDATE propiedades SET
                    codigo = :codigo,
                    nombre = :nombre,
                    descripcion = :descripcion,
                    pais_id = :pais_id,
                    departamento = :departamento,
                    provincia = :provincia,
                    distrito = :distrito,
                    direccion = :direccion,
                    referencia = :referencia,
                    latitud = :latitud,
                    longitud = :longitud,
                    estado = :estado,
                    observaciones = :observaciones,
                    actualizado_en = NOW()
                WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $propiedad->obtenerId(), PDO::PARAM_INT);
        $stmt->bindValue(':codigo', $propiedad->obtenerCodigo(), PDO::PARAM_STR);
        $stmt->bindValue(':nombre', $propiedad->obtenerNombre(), PDO::PARAM_STR);
        $stmt->bindValue(':descripcion', $propiedad->obtenerDescripcion(), $propiedad->obtenerDescripcion() === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':pais_id', $propiedad->obtenerPaisId(), PDO::PARAM_INT);
        $stmt->bindValue(':departamento', $propiedad->obtenerDepartamento(), $propiedad->obtenerDepartamento() === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':provincia', $propiedad->obtenerProvincia(), $propiedad->obtenerProvincia() === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':distrito', $propiedad->obtenerDistrito(), $propiedad->obtenerDistrito() === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':direccion', $propiedad->obtenerDireccion(), $propiedad->obtenerDireccion() === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':referencia', $propiedad->obtenerReferencia(), $propiedad->obtenerReferencia() === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':latitud', $propiedad->obtenerLatitud(), $propiedad->obtenerLatitud() === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':longitud', $propiedad->obtenerLongitud(), $propiedad->obtenerLongitud() === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':estado', $propiedad->obtenerEstado(), PDO::PARAM_STR);
        $stmt->bindValue(':observaciones', $propiedad->obtenerObservaciones(), $propiedad->obtenerObservaciones() === null ? PDO::PARAM_NULL : PDO::PARAM_STR);

        return $stmt->execute();
    }

    /**
     * Alterna o asigna el estado operativo de una propiedad (ACTIVO / INACTIVO).
     *
     * @param int $id
     * @param string $nuevoEstado
     * @return bool
     */
    public function cambiarEstado(int $id, string $nuevoEstado): bool
    {
        $sql = 'UPDATE propiedades SET estado = :estado, actualizado_en = NOW() WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':estado', strtoupper(trim($nuevoEstado)), PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Lista propiedades aplicando filtros opcionales de búsqueda y estado.
     *
     * @param string|null $busqueda
     * @param string|null $estado
     * @param int|null $paisId
     * @param int $limite
     * @param int $offset
     * @return array<int, Propiedad>
     */
    public function listar(
        ?string $busqueda = null,
        ?string $estado = null,
        ?int $paisId = null,
        int $limite = 50,
        int $offset = 0
    ): array {
        $sql = 'SELECT pr.*, pa.nombre AS pais_nombre
                FROM propiedades pr
                LEFT JOIN paises pa ON pr.pais_id = pa.id
                WHERE 1 = 1';
        $params = [];

        if ($busqueda !== null && trim($busqueda) !== '') {
            $sql .= ' AND (pr.nombre LIKE :b_nom OR pr.codigo LIKE :b_cod OR pr.direccion LIKE :b_dir OR pr.departamento LIKE :b_dep OR pr.provincia LIKE :b_pro OR pr.distrito LIKE :b_dis)';
            $patron = '%' . trim($busqueda) . '%';
            $params[':b_nom'] = $patron;
            $params[':b_cod'] = $patron;
            $params[':b_dir'] = $patron;
            $params[':b_dep'] = $patron;
            $params[':b_pro'] = $patron;
            $params[':b_dis'] = $patron;
        }

        if ($estado !== null && trim($estado) !== '') {
            $sql .= ' AND pr.estado = :estado';
            $params[':estado'] = strtoupper(trim($estado));
        }

        if ($paisId !== null && $paisId > 0) {
            $sql .= ' AND pr.pais_id = :pais_id';
            $params[':pais_id'] = $paisId;
        }

        $sql .= ' ORDER BY pr.nombre ASC LIMIT :limite OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $clave => $valor) {
            $stmt->bindValue($clave, $valor, is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limite', max(1, $limite), PDO::PARAM_INT);
        $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();

        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $resultado = [];
        foreach ($filas as $fila) {
            $resultado[] = Propiedad::hidratar($fila);
        }

        return $resultado;
    }

    /**
     * Retorna el número total de propiedades que satisfacen los filtros dados.
     *
     * @param string|null $busqueda
     * @param string|null $estado
     * @param int|null $paisId
     * @return int
     */
    public function contar(?string $busqueda = null, ?string $estado = null, ?int $paisId = null): int
    {
        $sql = 'SELECT COUNT(*) FROM propiedades pr WHERE 1 = 1';
        $params = [];

        if ($busqueda !== null && trim($busqueda) !== '') {
            $sql .= ' AND (pr.nombre LIKE :b_nom OR pr.codigo LIKE :b_cod OR pr.direccion LIKE :b_dir OR pr.departamento LIKE :b_dep OR pr.provincia LIKE :b_pro OR pr.distrito LIKE :b_dis)';
            $patron = '%' . trim($busqueda) . '%';
            $params[':b_nom'] = $patron;
            $params[':b_cod'] = $patron;
            $params[':b_dir'] = $patron;
            $params[':b_dep'] = $patron;
            $params[':b_pro'] = $patron;
            $params[':b_dis'] = $patron;
        }

        if ($estado !== null && trim($estado) !== '') {
            $sql .= ' AND pr.estado = :estado';
            $params[':estado'] = strtoupper(trim($estado));
        }

        if ($paisId !== null && $paisId > 0) {
            $sql .= ' AND pr.pais_id = :pais_id';
            $params[':pais_id'] = $paisId;
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $clave => $valor) {
            $stmt->bindValue($clave, $valor, is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }
}
