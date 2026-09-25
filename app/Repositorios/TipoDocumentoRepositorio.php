<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\TipoDocumento;
use CamargoPMS\Nucleo\BaseDatos;
use PDO;

/**
 * Repositorio de persistencia para el catálogo de tipos de documento de identidad.
 */
class TipoDocumentoRepositorio
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? BaseDatos::conexion();
    }

    /**
     * Busca un tipo de documento por su ID.
     *
     * @param int $id
     * @return TipoDocumento|null
     */
    public function buscarPorId(int $id): ?TipoDocumento
    {
        $sql = "SELECT * FROM tipos_documento WHERE id = :id LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch();
        return $fila ? TipoDocumento::desdeArreglo($fila) : null;
    }

    /**
     * Busca un tipo de documento por su código estándar (ej. 'DNI', 'PASAPORTE', 'CE').
     *
     * @param string $codigo
     * @return TipoDocumento|null
     */
    public function buscarPorCodigo(string $codigo): ?TipoDocumento
    {
        $sql = "SELECT * FROM tipos_documento WHERE codigo = :codigo LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':codigo', strtoupper(trim($codigo)), PDO::PARAM_STR);
        $stmt->execute();

        $fila = $stmt->fetch();
        return $fila ? TipoDocumento::desdeArreglo($fila) : null;
    }

    /**
     * Lista todos los tipos de documento activos para personas naturales.
     *
     * @return array<int, TipoDocumento>
     */
    public function listarActivos(): array
    {
        $sql = "SELECT * FROM tipos_documento WHERE activo = 1 ORDER BY id ASC";
        $stmt = $this->pdo->query($sql);

        $resultado = [];
        while ($fila = $stmt->fetch()) {
            $resultado[] = TipoDocumento::desdeArreglo($fila);
        }

        return $resultado;
    }
}
