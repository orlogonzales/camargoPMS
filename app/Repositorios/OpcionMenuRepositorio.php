<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\OpcionMenu;
use CamargoPMS\Modelos\Permiso;
use CamargoPMS\Nucleo\BaseDatos;
use PDO;

/**
 * Repositorio de persistencia para opciones de menú (tabla `opciones_menu`).
 *
 * Administra el almacenamiento, consultas estructuradas de 2 niveles,
 * reordenamiento y estados de las opciones de navegación.
 */
class OpcionMenuRepositorio
{
    private PDO $pdo;
    private ?PermisoRepositorio $permisoRepo;

    public function __construct(?PDO $pdo = null, ?PermisoRepositorio $permisoRepo = null)
    {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->permisoRepo = $permisoRepo ?? new PermisoRepositorio($this->pdo);
    }

    /**
     * Obtiene la conexión PDO subyacente para transacciones.
     */
    public function obtenerPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Busca una opción de menú por su ID primario.
     *
     * @param int $id
     * @param bool $cargarPermiso
     * @return OpcionMenu|null
     */
    public function buscarPorId(int $id, bool $cargarPermiso = false): ?OpcionMenu
    {
        $sql = 'SELECT * FROM opciones_menu WHERE id = :id LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fila) {
            return null;
        }

        $opcion = OpcionMenu::hidratar($fila);
        if ($cargarPermiso && $opcion->obtenerPermisoId() !== null) {
            $permiso = $this->permisoRepo->buscarPorId($opcion->obtenerPermisoId());
            $opcion->asignarPermiso($permiso);
        }

        return $opcion;
    }

    /**
     * Busca una opción de menú por su clave única normalizada.
     *
     * @param string $clave
     * @param bool $cargarPermiso
     * @return OpcionMenu|null
     */
    public function buscarPorClave(string $clave, bool $cargarPermiso = false): ?OpcionMenu
    {
        $sql = 'SELECT * FROM opciones_menu WHERE clave = :clave LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':clave', trim(strtolower($clave)), PDO::PARAM_STR);
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fila) {
            return null;
        }

        $opcion = OpcionMenu::hidratar($fila);
        if ($cargarPermiso && $opcion->obtenerPermisoId() !== null) {
            $permiso = $this->permisoRepo->buscarPorId($opcion->obtenerPermisoId());
            $opcion->asignarPermiso($permiso);
        }

        return $opcion;
    }

    /**
     * Comprueba si existe una clave técnica, opcionalmente excluyendo un ID específico.
     *
     * @param string $clave
     * @param int|null $excluirId
     * @return bool
     */
    public function existeClave(string $clave, ?int $excluirId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM opciones_menu WHERE clave = :clave';
        if ($excluirId !== null) {
            $sql .= ' AND id <> :excluir_id';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':clave', trim(strtolower($clave)), PDO::PARAM_STR);
        if ($excluirId !== null) {
            $stmt->bindValue(':excluir_id', $excluirId, PDO::PARAM_INT);
        }
        $stmt->execute();

        return ((int) $stmt->fetchColumn()) > 0;
    }

    /**
     * Obtiene todas las categorías principales (Nivel 1: padre_id IS NULL) ordenadas por `orden` ASC.
     *
     * @param bool $soloActivos
     * @param bool $cargarPermisos
     * @return array<int, OpcionMenu>
     */
    public function obtenerPrincipales(bool $soloActivos = false, bool $cargarPermisos = false): array
    {
        $sql = 'SELECT * FROM opciones_menu WHERE padre_id IS NULL';
        if ($soloActivos) {
            $sql .= " AND estado = 'ACTIVO'";
        }
        $sql .= ' ORDER BY orden ASC, nombre ASC';

        $stmt = $this->pdo->query($sql);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resultado = [];
        foreach ($filas as $fila) {
            $opcion = OpcionMenu::hidratar($fila);
            if ($cargarPermisos && $opcion->obtenerPermisoId() !== null) {
                $permiso = $this->permisoRepo->buscarPorId($opcion->obtenerPermisoId());
                $opcion->asignarPermiso($permiso);
            }
            $resultado[] = $opcion;
        }

        return $resultado;
    }

    /**
     * Obtiene las opciones secundarias hijas de una categoría principal específica (Nivel 2).
     *
     * @param int $padreId
     * @param bool $soloActivos
     * @param bool $cargarPermisos
     * @return array<int, OpcionMenu>
     */
    public function obtenerHijosDe(int $padreId, bool $soloActivos = false, bool $cargarPermisos = false): array
    {
        $sql = 'SELECT * FROM opciones_menu WHERE padre_id = :padre_id';
        if ($soloActivos) {
            $sql .= " AND estado = 'ACTIVO'";
        }
        $sql .= ' ORDER BY orden ASC, nombre ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':padre_id', $padreId, PDO::PARAM_INT);
        $stmt->execute();

        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $resultado = [];

        foreach ($filas as $fila) {
            $opcion = OpcionMenu::hidratar($fila);
            if ($cargarPermisos && $opcion->obtenerPermisoId() !== null) {
                $permiso = $this->permisoRepo->buscarPorId($opcion->obtenerPermisoId());
                $opcion->asignarPermiso($permiso);
            }
            $resultado[] = $opcion;
        }

        return $resultado;
    }

    /**
     * Obtiene el árbol completo de 2 niveles (principales con sus opciones hijas asignadas).
     *
     * @param bool $soloActivos
     * @param bool $cargarPermisos
     * @return array<int, OpcionMenu>
     */
    public function obtenerArbolCompleto(bool $soloActivos = false, bool $cargarPermisos = true): array
    {
        $principales = $this->obtenerPrincipales($soloActivos, $cargarPermisos);

        // Pre-cargar todos los permisos en memoria si se requiere para evitar queries N+1
        $mapaPermisos = [];
        if ($cargarPermisos) {
            $todosPermisos = $this->permisoRepo->listarTodos();
            foreach ($todosPermisos as $p) {
                $mapaPermisos[$p->obtenerId()] = $p;
            }
        }

        // Obtener todas las secundarias
        $sqlSecundarias = 'SELECT * FROM opciones_menu WHERE padre_id IS NOT NULL';
        if ($soloActivos) {
            $sqlSecundarias .= " AND estado = 'ACTIVO'";
        }
        $sqlSecundarias .= ' ORDER BY orden ASC, nombre ASC';

        $stmtSec = $this->pdo->query($sqlSecundarias);
        $filasSec = $stmtSec->fetchAll(PDO::FETCH_ASSOC);

        $hijosPorPadre = [];
        foreach ($filasSec as $fila) {
            $hijo = OpcionMenu::hidratar($fila);
            if ($cargarPermisos && $hijo->obtenerPermisoId() !== null && isset($mapaPermisos[$hijo->obtenerPermisoId()])) {
                $hijo->asignarPermiso($mapaPermisos[$hijo->obtenerPermisoId()]);
            }
            $hijosPorPadre[$hijo->obtenerPadreId()][] = $hijo;
        }

        foreach ($principales as $principal) {
            if ($cargarPermisos && $principal->obtenerPermisoId() !== null && isset($mapaPermisos[$principal->obtenerPermisoId()])) {
                $principal->asignarPermiso($mapaPermisos[$principal->obtenerPermisoId()]);
            }
            $pId = (int) $principal->obtenerId();
            if (isset($hijosPorPadre[$pId])) {
                $principal->asignarHijos($hijosPorPadre[$pId]);
            }
        }

        return $principales;
    }

    /**
     * Calcula el siguiente número de orden para un nuevo elemento entre sus hermanos.
     *
     * @param int|null $padreId
     * @return int
     */
    public function obtenerSiguienteOrden(?int $padreId = null): int
    {
        if ($padreId === null) {
            $sql = 'SELECT COALESCE(MAX(orden), 0) + 1 FROM opciones_menu WHERE padre_id IS NULL';
            return (int) $this->pdo->query($sql)->fetchColumn();
        }

        $sql = 'SELECT COALESCE(MAX(orden), 0) + 1 FROM opciones_menu WHERE padre_id = :padre_id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':padre_id', $padreId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * Cuenta la cantidad de opciones hijas que tiene un principal.
     *
     * @param int $padreId
     * @param bool $soloActivos
     * @return int
     */
    public function contarHijos(int $padreId, bool $soloActivos = false): int
    {
        $sql = 'SELECT COUNT(*) FROM opciones_menu WHERE padre_id = :padre_id';
        if ($soloActivos) {
            $sql .= " AND estado = 'ACTIVO'";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':padre_id', $padreId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * Inserta una nueva opción de menú en la base de datos.
     *
     * @param OpcionMenu $opcion
     * @return OpcionMenu Instancia con el ID autogenerado asignado.
     */
    public function insertar(OpcionMenu $opcion): OpcionMenu
    {
        $sql = 'INSERT INTO opciones_menu (padre_id, clave, nombre, icono, ruta, orden, estado, permiso_id, es_sistema, creado_en, actualizado_en)
                VALUES (:padre_id, :clave, :nombre, :icono, :ruta, :orden, :estado, :permiso_id, :es_sistema, NOW(), NOW())';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':padre_id', $opcion->obtenerPadreId(), $opcion->obtenerPadreId() !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':clave', $opcion->obtenerClave(), PDO::PARAM_STR);
        $stmt->bindValue(':nombre', $opcion->obtenerNombre(), PDO::PARAM_STR);
        $stmt->bindValue(':icono', $opcion->obtenerIcono(), $opcion->obtenerIcono() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':ruta', $opcion->obtenerRuta(), $opcion->obtenerRuta() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':orden', $opcion->obtenerOrden(), PDO::PARAM_INT);
        $stmt->bindValue(':estado', $opcion->obtenerEstado(), PDO::PARAM_STR);
        $stmt->bindValue(':permiso_id', $opcion->obtenerPermisoId(), $opcion->obtenerPermisoId() !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':es_sistema', $opcion->esSistema() ? 1 : 0, PDO::PARAM_INT);

        $stmt->execute();
        $nuevoId = (int) $this->pdo->lastInsertId();

        return $this->buscarPorId($nuevoId, true) ?? $opcion;
    }

    /**
     * Actualiza los datos editables de una opción de menú existente.
     *
     * @param OpcionMenu $opcion
     * @return bool
     */
    public function actualizar(OpcionMenu $opcion): bool
    {
        $sql = 'UPDATE opciones_menu 
                SET padre_id = :padre_id,
                    clave = :clave,
                    nombre = :nombre,
                    icono = :icono,
                    ruta = :ruta,
                    orden = :orden,
                    estado = :estado,
                    permiso_id = :permiso_id,
                    es_sistema = :es_sistema,
                    actualizado_en = NOW()
                WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $opcion->obtenerId(), PDO::PARAM_INT);
        $stmt->bindValue(':padre_id', $opcion->obtenerPadreId(), $opcion->obtenerPadreId() !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':clave', $opcion->obtenerClave(), PDO::PARAM_STR);
        $stmt->bindValue(':nombre', $opcion->obtenerNombre(), PDO::PARAM_STR);
        $stmt->bindValue(':icono', $opcion->obtenerIcono(), $opcion->obtenerIcono() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':ruta', $opcion->obtenerRuta(), $opcion->obtenerRuta() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':orden', $opcion->obtenerOrden(), PDO::PARAM_INT);
        $stmt->bindValue(':estado', $opcion->obtenerEstado(), PDO::PARAM_STR);
        $stmt->bindValue(':permiso_id', $opcion->obtenerPermisoId(), $opcion->obtenerPermisoId() !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':es_sistema', $opcion->esSistema() ? 1 : 0, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Cambia únicamente el estado ('ACTIVO' / 'INACTIVO') de una opción.
     *
     * @param int $id
     * @param string $estado
     * @return bool
     */
    public function cambiarEstado(int $id, string $estado): bool
    {
        $estadoNormalizado = strtoupper(trim($estado)) === 'INACTIVO' ? 'INACTIVO' : 'ACTIVO';
        $sql = 'UPDATE opciones_menu SET estado = :estado, actualizado_en = NOW() WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':estado', $estadoNormalizado, PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Actualiza la posición de orden de una opción de menú.
     *
     * @param int $id
     * @param int $nuevoOrden
     * @return bool
     */
    public function actualizarOrden(int $id, int $nuevoOrden): bool
    {
        $sql = 'UPDATE opciones_menu SET orden = :orden, actualizado_en = NOW() WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':orden', $nuevoOrden, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Elimina físicamente una opción de menú por su ID.
     *
     * @param int $id
     * @return bool
     */
    public function eliminar(int $id): bool
    {
        $sql = 'DELETE FROM opciones_menu WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }
}
