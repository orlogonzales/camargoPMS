<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\ConfiguracionParametro;
use CamargoPMS\Nucleo\BaseDatos;
use PDO;

/**
 * Repositorio de persistencia para parámetros de configuración funcional (tabla `configuraciones`).
 */
class ConfiguracionRepositorio
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? BaseDatos::conexion();
    }

    /**
     * Obtiene la conexión PDO subyacente para soporte de transacciones atómicas.
     */
    public function obtenerPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Busca un parámetro de configuración por su clave única normalizada.
     *
     * @param string $clave
     * @return ConfiguracionParametro|null
     */
    public function buscarPorClave(string $clave): ?ConfiguracionParametro
    {
        $sql = 'SELECT * FROM configuraciones WHERE clave = :clave LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':clave', trim(strtolower($clave)), PDO::PARAM_STR);
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fila) {
            return null;
        }

        return ConfiguracionParametro::hidratar($fila);
    }

    /**
     * Busca un parámetro de configuración por su ID primario.
     *
     * @param int $id
     * @return ConfiguracionParametro|null
     */
    public function buscarPorId(int $id): ?ConfiguracionParametro
    {
        $sql = 'SELECT * FROM configuraciones WHERE id = :id LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fila) {
            return null;
        }

        return ConfiguracionParametro::hidratar($fila);
    }

    /**
     * Comprueba si una clave de configuración ya existe.
     *
     * @param string $clave
     * @return bool
     */
    public function existeClave(string $clave): bool
    {
        $sql = 'SELECT COUNT(*) FROM configuraciones WHERE clave = :clave';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':clave', trim(strtolower($clave)), PDO::PARAM_STR);
        $stmt->execute();

        return ((int) $stmt->fetchColumn()) > 0;
    }

    /**
     * Retorna todos los parámetros de configuración, opcionalmente filtrados por estado.
     *
     * @param string|null $estado 'ACTIVO', 'INACTIVO' o null para todos
     * @return array<int, ConfiguracionParametro>
     */
    public function listarTodos(?string $estado = null): array
    {
        $sql = 'SELECT * FROM configuraciones';
        if ($estado !== null) {
            $sql .= ' WHERE estado = :estado';
        }
        $sql .= ' ORDER BY grupo ASC, orden ASC, clave ASC';

        $stmt = $this->pdo->prepare($sql);
        if ($estado !== null) {
            $stmt->bindValue(':estado', strtoupper(trim($estado)), PDO::PARAM_STR);
        }
        $stmt->execute();

        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $resultados = [];
        foreach ($filas as $fila) {
            $resultados[] = ConfiguracionParametro::hidratar($fila);
        }

        return $resultados;
    }

    /**
     * Retorna todos los parámetros pertenecientes a un grupo funcional específico.
     *
     * @param string $grupo
     * @param string|null $estado
     * @return array<int, ConfiguracionParametro>
     */
    public function listarPorGrupo(string $grupo, ?string $estado = null): array
    {
        $sql = 'SELECT * FROM configuraciones WHERE grupo = :grupo';
        if ($estado !== null) {
            $sql .= ' AND estado = :estado';
        }
        $sql .= ' ORDER BY orden ASC, clave ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':grupo', strtoupper(trim($grupo)), PDO::PARAM_STR);
        if ($estado !== null) {
            $stmt->bindValue(':estado', strtoupper(trim($estado)), PDO::PARAM_STR);
        }
        $stmt->execute();

        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $resultados = [];
        foreach ($filas as $fila) {
            $resultados[] = ConfiguracionParametro::hidratar($fila);
        }

        return $resultados;
    }

    /**
     * Obtiene el listado de grupos funcionales distintos ordenados alfabéticamente.
     *
     * @return array<int, string>
     */
    public function listarGrupos(): array
    {
        $sql = 'SELECT DISTINCT grupo FROM configuraciones ORDER BY grupo ASC';
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Actualiza el valor de un parámetro de configuración.
     *
     * @param string $clave
     * @param string|null $nuevoValor
     * @return bool
     */
    public function actualizarValor(string $clave, ?string $nuevoValor): bool
    {
        $sql = 'UPDATE configuraciones SET valor = :valor WHERE clave = :clave';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':valor', $nuevoValor, $nuevoValor === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':clave', trim(strtolower($clave)), PDO::PARAM_STR);

        return $stmt->execute();
    }

    /**
     * Restaura el valor de un parámetro al valor predeterminado de fábrica (`valor = valor_predeterminado`).
     *
     * @param string $clave
     * @return bool
     */
    public function restaurarPredeterminado(string $clave): bool
    {
        $sql = 'UPDATE configuraciones SET valor = valor_predeterminado WHERE clave = :clave';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':clave', trim(strtolower($clave)), PDO::PARAM_STR);

        return $stmt->execute();
    }

    /**
     * Inserta un nuevo parámetro en la persistencia.
     *
     * @param ConfiguracionParametro $parametro
     * @return int ID insertado
     */
    public function insertar(ConfiguracionParametro $parametro): int
    {
        $sql = 'INSERT INTO configuraciones (
                    clave, grupo, nombre, descripcion, tipo, valor, valor_predeterminado,
                    editable, es_sensible, orden, estado
                ) VALUES (
                    :clave, :grupo, :nombre, :descripcion, :tipo, :valor, :valor_predeterminado,
                    :editable, :es_sensible, :orden, :estado
                )';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':clave', $parametro->obtenerClave(), PDO::PARAM_STR);
        $stmt->bindValue(':grupo', $parametro->obtenerGrupo(), PDO::PARAM_STR);
        $stmt->bindValue(':nombre', $parametro->obtenerNombre(), PDO::PARAM_STR);
        $stmt->bindValue(':descripcion', $parametro->obtenerDescripcion(), $parametro->obtenerDescripcion() === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':tipo', $parametro->obtenerTipo()->value, PDO::PARAM_STR);
        $stmt->bindValue(':valor', $parametro->obtenerValor(), $parametro->obtenerValor() === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':valor_predeterminado', $parametro->obtenerValorPredeterminado(), $parametro->obtenerValorPredeterminado() === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':editable', $parametro->esEditable() ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':es_sensible', $parametro->esSensible() ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':orden', $parametro->obtenerOrden(), PDO::PARAM_INT);
        $stmt->bindValue(':estado', $parametro->obtenerEstado(), PDO::PARAM_STR);

        $stmt->execute();
        return (int) $this->pdo->lastInsertId();
    }
}
