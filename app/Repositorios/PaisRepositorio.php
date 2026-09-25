<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\Pais;
use CamargoPMS\Nucleo\BaseDatos;
use PDO;

/**
 * Repositorio de persistencia para el catálogo de países y nacionalidades.
 */
class PaisRepositorio
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? BaseDatos::conexion();
    }

    /**
     * Busca un país por su identificador numérico primario.
     *
     * @param int $id
     * @return Pais|null
     */
    public function buscarPorId(int $id): ?Pais
    {
        $sql = "SELECT * FROM paises WHERE id = :id LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch();
        return $fila ? Pais::desdeArreglo($fila) : null;
    }

    /**
     * Busca un país por su código ISO2 (ej. 'PE').
     *
     * @param string $codigoIso2
     * @return Pais|null
     */
    public function buscarPorIso2(string $codigoIso2): ?Pais
    {
        $sql = "SELECT * FROM paises WHERE codigo_iso2 = :codigo LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':codigo', strtoupper(trim($codigoIso2)), PDO::PARAM_STR);
        $stmt->execute();

        $fila = $stmt->fetch();
        return $fila ? Pais::desdeArreglo($fila) : null;
    }

    /**
     * Busca un país por su código ISO3 (ej. 'PER').
     *
     * @param string $codigoIso3
     * @return Pais|null
     */
    public function buscarPorIso3(string $codigoIso3): ?Pais
    {
        $sql = "SELECT * FROM paises WHERE codigo_iso3 = :codigo LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':codigo', strtoupper(trim($codigoIso3)), PDO::PARAM_STR);
        $stmt->execute();

        $fila = $stmt->fetch();
        return $fila ? Pais::desdeArreglo($fila) : null;
    }

    /**
     * Lista todos los países activos.
     *
     * @return array<int, Pais>
     */
    public function listarActivos(): array
    {
        $sql = "SELECT * FROM paises WHERE activo = 1 ORDER BY nombre ASC";
        $stmt = $this->pdo->query($sql);

        $resultado = [];
        while ($fila = $stmt->fetch()) {
            $resultado[] = Pais::desdeArreglo($fila);
        }

        return $resultado;
    }

    /**
     * Inserta un nuevo país en el catálogo.
     *
     * @param Pais $pais
     * @return int ID insertado.
     */
    public function insertar(Pais $pais): int
    {
        $sql = "INSERT INTO paises (codigo_iso2, codigo_iso3, nombre, nacionalidad, activo)
                VALUES (:iso2, :iso3, :nombre, :nacionalidad, :activo)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':iso2', $pais->obtenerCodigoIso2(), PDO::PARAM_STR);
        $stmt->bindValue(':iso3', $pais->obtenerCodigoIso3(), PDO::PARAM_STR);
        $stmt->bindValue(':nombre', $pais->obtenerNombre(), PDO::PARAM_STR);
        $stmt->bindValue(':nacionalidad', $pais->obtenerNacionalidad(), PDO::PARAM_STR);
        $stmt->bindValue(':activo', $pais->esActivo() ? 1 : 0, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $this->pdo->lastInsertId();
    }
}
