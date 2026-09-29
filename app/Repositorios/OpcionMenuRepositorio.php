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
    public function buscarPorId(int $id, bool $cargarPermiso = false, bool $cargarPadre = true): ?OpcionMenu
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

        if ($cargarPadre && $opcion->obtenerPadreId() !== null) {
            $padre = $this->buscarPorId($opcion->obtenerPadreId(), false, true);
            if ($padre !== null) {
                $opcion->asignarPadre($padre);
            }
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
    public function buscarPorClave(string $clave, bool $cargarPermiso = false, bool $cargarPadre = true): ?OpcionMenu
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

        if ($cargarPadre && $opcion->obtenerPadreId() !== null) {
            $padre = $this->buscarPorId($opcion->obtenerPadreId(), false, true);
            if ($padre !== null) {
                $opcion->asignarPadre($padre);
            }
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
     * Obtiene el árbol completo de hasta 3 niveles (Dominio, Módulo, Función/Submódulo).
     *
     * @param bool $soloActivos
     * @param bool $cargarPermisos
     * @return array<int, OpcionMenu>
     */
    public function obtenerArbolCompleto(bool $soloActivos = false, bool $cargarPermisos = true): array
    {
        // Pre-cargar todos los permisos en memoria si se requiere para evitar queries N+1
        $mapaPermisos = [];
        if ($cargarPermisos) {
            $todosPermisos = $this->permisoRepo->listarTodos();
            foreach ($todosPermisos as $p) {
                $mapaPermisos[$p->obtenerId()] = $p;
            }
        }

        // Consultar todas las opciones ordenadas
        $sql = 'SELECT * FROM opciones_menu';
        if ($soloActivos) {
            $sql .= " WHERE estado = 'ACTIVO'";
        }
        $sql .= ' ORDER BY orden ASC, nombre ASC';

        $stmt = $this->pdo->query($sql);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        /** @var array<int, OpcionMenu> $todasPorId */
        $todasPorId = [];
        /** @var array<int|string, array<int, OpcionMenu>> $hijosPorPadre */
        $hijosPorPadre = [];

        foreach ($filas as $fila) {
            $opcion = OpcionMenu::hidratar($fila);
            if ($cargarPermisos && $opcion->obtenerPermisoId() !== null && isset($mapaPermisos[$opcion->obtenerPermisoId()])) {
                $opcion->asignarPermiso($mapaPermisos[$opcion->obtenerPermisoId()]);
            }
            $opId = (int) $opcion->obtenerId();
            $todasPorId[$opId] = $opcion;

            $padreKey = $opcion->obtenerPadreId() ?? 'raiz';
            $hijosPorPadre[$padreKey][] = $opcion;
        }

        // Asignar referencias padre-hijo bidireccionales y poblar árbol
        foreach ($todasPorId as $opcion) {
            $padreId = $opcion->obtenerPadreId();
            if ($padreId !== null && isset($todasPorId[$padreId])) {
                $opcion->asignarPadre($todasPorId[$padreId]);
            }
            $opId = (int) $opcion->obtenerId();
            if (isset($hijosPorPadre[$opId])) {
                $opcion->asignarHijos($hijosPorPadre[$opId]);
            }
        }

        // Las raíces (Nivel 1 — Dominios) son las que tienen padre_id === null
        return $hijosPorPadre['raiz'] ?? [];
    }

    /**
     * Calcula el nivel jerárquico real de una opción en la base de datos (1, 2 o 3).
     *
     * @param int $id
     * @return int 0 si no existe, 1 para raíz, 2 para módulo, 3 para función/submódulo.
     */
    public function calcularNivel(int $id): int
    {
        $nivel = 1;
        $actualId = $id;
        $limite = 10;

        while ($limite-- > 0) {
            $sql = 'SELECT padre_id FROM opciones_menu WHERE id = :id LIMIT 1';
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':id', $actualId, PDO::PARAM_INT);
            $stmt->execute();

            $fila = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$fila) {
                return $actualId === $id ? 0 : $nivel;
            }

            if ($fila['padre_id'] === null) {
                return $nivel;
            }

            $nivel++;
            $actualId = (int) $fila['padre_id'];
        }

        return $nivel;
    }

    /**
     * Calcula la altura del subárbol de descendientes de una opción (0 = hoja, 1 = tiene hijos, 2 = tiene nietos).
     *
     * @param int $id
     * @return int
     */
    public function calcularProfundidadSubarbol(int $id): int
    {
        // Verificar si tiene hijos directos
        $sqlHijos = 'SELECT id FROM opciones_menu WHERE padre_id = :id';
        $stmtHijos = $this->pdo->prepare($sqlHijos);
        $stmtHijos->bindValue(':id', $id, PDO::PARAM_INT);
        $stmtHijos->execute();
        $hijosIds = $stmtHijos->fetchAll(PDO::FETCH_COLUMN);

        if (empty($hijosIds)) {
            return 0; // Es una hoja
        }

        // Verificar si alguno de sus hijos tiene a su vez hijos (nietos)
        $inParams = implode(',', array_fill(0, count($hijosIds), '?'));
        $sqlNietos = "SELECT COUNT(*) FROM opciones_menu WHERE padre_id IN ({$inParams})";
        $stmtNietos = $this->pdo->prepare($sqlNietos);
        $stmtNietos->execute($hijosIds);
        $totalNietos = (int) $stmtNietos->fetchColumn();

        return $totalNietos > 0 ? 2 : 1;
    }

    /**
     * Detecta si asignar nuevoPadreId como padre de opcionId crearía una referencia circular.
     *
     * @param int $opcionId
     * @param int $nuevoPadreId
     * @return bool True si se detecta ciclo, false si es seguro.
     */
    public function detectarCiclo(int $opcionId, int $nuevoPadreId): bool
    {
        if ($opcionId === $nuevoPadreId) {
            return true;
        }

        $actualId = $nuevoPadreId;
        $limite = 15;

        while ($limite-- > 0) {
            $sql = 'SELECT padre_id FROM opciones_menu WHERE id = :id LIMIT 1';
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':id', $actualId, PDO::PARAM_INT);
            $stmt->execute();

            $fila = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$fila || $fila['padre_id'] === null) {
                return false;
            }

            $padreDelActual = (int) $fila['padre_id'];
            if ($padreDelActual === $opcionId) {
                return true; // Se detectó un ciclo (A -> ... -> B -> A)
            }

            $actualId = $padreDelActual;
        }

        return false;
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
