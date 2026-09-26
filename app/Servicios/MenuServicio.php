<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

use CamargoPMS\Excepciones\NivelMenuInvalidoExcepcion;
use CamargoPMS\Excepciones\OpcionMenuDuplicadaExcepcion;
use CamargoPMS\Excepciones\OpcionMenuNoEncontradaExcepcion;
use CamargoPMS\Excepciones\OpcionMenuProtegidaExcepcion;
use CamargoPMS\Excepciones\PermisoNoEncontradoExcepcion;
use CamargoPMS\Excepciones\RutaInvalidaExcepcion;
use CamargoPMS\Modelos\OpcionMenu;
use CamargoPMS\Modelos\Usuario;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Repositorios\OpcionMenuRepositorio;
use CamargoPMS\Repositorios\PermisoRepositorio;
use InvalidArgumentException;
use PDO;
use Throwable;

/**
 * Servicio de dominio para la navegación dinámica autorizada y la gestión del menú.
 *
 * Principio Vinculante: MENÚ ≠ AUTORIZACIÓN.
 * Este servicio determina la estructura de navegación visible para un usuario;
 * el control de acceso a recursos/acciones es responsabilidad estricta de AutorizacionServicio.
 */
class MenuServicio
{
    private PDO $pdo;
    private OpcionMenuRepositorio $opcionRepo;
    private PermisoRepositorio $permisoRepo;
    private AutorizacionServicio $autorizacionServicio;

    /**
     * Claves técnicas protegidas que no pueden eliminarse ni deshabilitarse si comprometen la administración.
     */
    private const CLAVES_CRITICAS_SISTEMA = ['inicio', 'configuracion', 'config_menu'];

    public function __construct(
        ?PDO $pdo = null,
        ?OpcionMenuRepositorio $opcionRepo = null,
        ?PermisoRepositorio $permisoRepo = null,
        ?AutorizacionServicio $autorizacionServicio = null
    ) {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->opcionRepo = $opcionRepo ?? new OpcionMenuRepositorio($this->pdo);
        $this->permisoRepo = $permisoRepo ?? new PermisoRepositorio($this->pdo);
        $this->autorizacionServicio = $autorizacionServicio ?? new AutorizacionServicio($this->pdo);
    }

    /**
     * Construye la estructura de navegación autorizada para un usuario conforme al contrato visual Alina.
     *
     * Reglas de visibilidad:
     * 1. Una opción inactiva nunca se muestra.
     * 2. Si la opción tiene permiso asignado, el usuario debe poseerlo (o ser Superadministrador).
     * 3. Una categoría principal solo es visible si está autorizada Y tiene al menos un hijo visible.
     *
     * @param Usuario|null $usuario Usuario autenticado o null si es anónimo.
     * @param string $rutaActual Ruta relativa solicitada (ej. '/' o '/usuarios').
     * @return array<string, array<string, mixed>> Estructura indexada por clave de categoría principal.
     */
    public function obtenerMenuParaUsuario(?Usuario $usuario, string $rutaActual = '/'): array
    {
        if ($usuario === null) {
            return [];
        }

        $usuarioId = (int) $usuario->obtenerId();
        $esSuperadmin = $this->autorizacionServicio->esSuperadministrador($usuarioId);

        // Árbol completo activo con permisos cargados
        $principales = $this->opcionRepo->obtenerArbolCompleto(true, true);
        $rutaNormalizada = $this->normalizarRutaParaComparacion($rutaActual);

        $menuAlina = [];
        $categoriaCoincidente = null;

        foreach ($principales as $principal) {
            // Evaluar permiso propio de la categoría principal si lo tuviera
            if ($principal->obtenerPermisoId() !== null) {
                $permisoCat = $principal->obtenerPermiso();
                if ($permisoCat !== null) {
                    if (!$permisoCat->esActivo()) {
                        continue;
                    }
                    $tienePermisoCat = $esSuperadmin || $this->autorizacionServicio->puede($usuarioId, $permisoCat->obtenerCodigo());
                    if (!$tienePermisoCat) {
                        continue;
                    }
                }
            }

            $hijosVisibles = [];
            $algunHijoActivo = false;

            foreach ($principal->obtenerHijos() as $hijo) {
                if (!$hijo->esActivo()) {
                    continue;
                }

                // Evaluar permiso RBAC de la opción secundaria
                if ($hijo->obtenerPermisoId() !== null) {
                    $permisoHijo = $hijo->obtenerPermiso();
                    if ($permisoHijo !== null) {
                        if (!$permisoHijo->esActivo()) {
                            continue;
                        }
                        $tienePermisoHijo = $esSuperadmin || $this->autorizacionServicio->puede($usuarioId, $permisoHijo->obtenerCodigo());
                        if (!$tienePermisoHijo) {
                            continue;
                        }
                    }
                }

                $rutaHijo = $hijo->obtenerRuta();
                $rutaHijoNorm = $rutaHijo !== null ? $this->normalizarRutaParaComparacion($rutaHijo) : '';
                $esOpcionActiva = ($rutaHijoNorm !== '' && $rutaHijoNorm === $rutaNormalizada);

                if ($esOpcionActiva) {
                    $algunHijoActivo = true;
                    $categoriaCoincidente = $principal->obtenerClave();
                }

                $hijosVisibles[] = [
                    'id' => $hijo->obtenerId(),
                    'clave' => $hijo->obtenerClave(),
                    'tipo' => 'simple',
                    'titulo' => $hijo->obtenerNombre(),
                    'url' => $rutaHijo !== null ? url_ruta($rutaHijo) : '#',
                    'ruta' => $rutaHijo,
                    'icono' => $hijo->obtenerIcono() ?? 'ti ti-point',
                    'activo' => $esOpcionActiva,
                ];
            }

            // Regla de Visibilidad: Un principal DEBE tener al menos una opción secundaria visible
            if (empty($hijosVisibles)) {
                continue;
            }

            $clavePrincipal = $principal->obtenerClave();
            $menuAlina[$clavePrincipal] = [
                'id' => $principal->obtenerId(),
                'clave' => $clavePrincipal,
                'etiqueta' => $principal->obtenerNombre(),
                'icono' => $principal->obtenerIcono() ?? 'ti ti-folder',
                'activo' => $algunHijoActivo,
                'grupos' => $hijosVisibles,
            ];
        }

        // Si ninguna opción secundaria coincidió con la ruta actual, activar la primera categoría por defecto
        if ($categoriaCoincidente === null && !empty($menuAlina)) {
            $primeraClave = array_key_first($menuAlina);
            $menuAlina[$primeraClave]['activo'] = true;
        }

        return $menuAlina;
    }

    /**
     * Obtiene todos los datos estructurados del menú para la interfaz de administración.
     *
     * @return array{principales: array<int, array<string, mixed>>, permisos: array<int, array{id: int, codigo: string, nombre: string, modulo: string}>}
     */
    public function obtenerDatosGestion(): array
    {
        $arbol = $this->opcionRepo->obtenerArbolCompleto(false, true);
        $principalesArray = array_map(static fn(OpcionMenu $opcion) => $opcion->haciaArreglo(), $arbol);

        $permisos = $this->permisoRepo->listarTodos();
        $permisosArray = array_map(static fn($p) => [
            'id' => $p->obtenerId(),
            'codigo' => $p->obtenerCodigo(),
            'nombre' => $p->obtenerNombre(),
            'modulo' => $p->obtenerModulo(),
        ], $permisos);

        return [
            'principales' => $principalesArray,
            'permisos' => $permisosArray,
        ];
    }

    /**
     * Crea una nueva opción de menú validando jerarquía de 2 niveles, unicidad y seguridad de rutas.
     *
     * @param array{padre_id?: int|null, clave: string, nombre: string, icono?: string|null, ruta?: string|null, orden?: int|null, estado?: string, permiso_id?: int|null} $datos
     * @return OpcionMenu
     */
    public function crearOpcion(array $datos): OpcionMenu
    {
        $clave = trim(strtolower((string) ($datos['clave'] ?? '')));
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        $padreId = isset($datos['padre_id']) && $datos['padre_id'] !== '' && (int) $datos['padre_id'] > 0 ? (int) $datos['padre_id'] : null;
        $icono = isset($datos['icono']) && trim((string) $datos['icono']) !== '' ? trim((string) $datos['icono']) : null;
        $ruta = isset($datos['ruta']) && trim((string) $datos['ruta']) !== '' ? trim((string) $datos['ruta']) : null;
        $estado = strtoupper(trim((string) ($datos['estado'] ?? 'ACTIVO'))) === 'INACTIVO' ? 'INACTIVO' : 'ACTIVO';
        $permisoId = isset($datos['permiso_id']) && $datos['permiso_id'] !== '' && (int) $datos['permiso_id'] > 0 ? (int) $datos['permiso_id'] : null;

        $this->validarClave($clave);
        $this->validarNombre($nombre);
        $this->validarRuta($ruta, $padreId !== null);
        $this->validarJerarquia($padreId, null);

        if ($this->opcionRepo->existeClave($clave)) {
            throw new OpcionMenuDuplicadaExcepcion($clave);
        }

        if ($permisoId !== null) {
            $this->validarPermisoExiste($permisoId);
        }

        $orden = isset($datos['orden']) && (int) $datos['orden'] > 0
            ? (int) $datos['orden']
            : $this->opcionRepo->obtenerSiguienteOrden($padreId);

        $nuevaOpcion = new OpcionMenu(
            null,
            $padreId,
            $clave,
            $nombre,
            $icono,
            $ruta,
            $orden,
            $estado,
            $permisoId,
            false // Nuevas opciones nunca son de sistema
        );

        return $this->opcionRepo->insertar($nuevaOpcion);
    }

    /**
     * Actualiza una opción existente respetando las restricciones de elementos estructurales protegidos.
     *
     * @param int $id
     * @param array{padre_id?: int|null, clave?: string, nombre?: string, icono?: string|null, ruta?: string|null, orden?: int|null, estado?: string, permiso_id?: int|null} $datos
     * @return OpcionMenu
     */
    public function actualizarOpcion(int $id, array $datos): OpcionMenu
    {
        $opcion = $this->opcionRepo->buscarPorId($id);
        if ($opcion === null) {
            throw new OpcionMenuNoEncontradaExcepcion("La opción de menú ID {$id} no existe.");
        }

        $nombre = isset($datos['nombre']) ? trim((string) $datos['nombre']) : $opcion->obtenerNombre();
        $this->validarNombre($nombre);

        $clave = isset($datos['clave']) ? trim(strtolower((string) $datos['clave'])) : $opcion->obtenerClave();
        $padreId = array_key_exists('padre_id', $datos)
            ? ($datos['padre_id'] !== null && $datos['padre_id'] !== '' && (int) $datos['padre_id'] > 0 ? (int) $datos['padre_id'] : null)
            : $opcion->obtenerPadreId();

        // Protección de elementos estructurales del sistema
        if ($opcion->esSistema()) {
            if ($clave !== $opcion->obtenerClave()) {
                throw new OpcionMenuProtegidaExcepcion("No se puede modificar la clave técnica de la opción estructural protegida '{$opcion->obtenerNombre()}'.");
            }
            if ($padreId !== $opcion->obtenerPadreId()) {
                throw new OpcionMenuProtegidaExcepcion("No se puede mover de nivel un elemento estructural protegido del sistema.");
            }
        } else {
            $this->validarClave($clave);
            if ($this->opcionRepo->existeClave($clave, $id)) {
                throw new OpcionMenuDuplicadaExcepcion($clave);
            }
        }

        $this->validarJerarquia($padreId, $id);

        $ruta = array_key_exists('ruta', $datos)
            ? (trim((string) $datos['ruta']) !== '' ? trim((string) $datos['ruta']) : null)
            : $opcion->obtenerRuta();
        $this->validarRuta($ruta, $padreId !== null);

        $icono = array_key_exists('icono', $datos)
            ? (trim((string) $datos['icono']) !== '' ? trim((string) $datos['icono']) : null)
            : $opcion->obtenerIcono();

        $estado = array_key_exists('estado', $datos)
            ? (strtoupper(trim((string) $datos['estado'])) === 'INACTIVO' ? 'INACTIVO' : 'ACTIVO')
            : $opcion->obtenerEstado();

        // Verificar que no se desactive una opción crítica del sistema
        if ($estado === 'INACTIVO' && $opcion->esSistema() && in_array($opcion->obtenerClave(), self::CLAVES_CRITICAS_SISTEMA, true)) {
            throw new OpcionMenuProtegidaExcepcion("No se puede desactivar la opción estructural '{$opcion->obtenerNombre()}' porque dejaría al sistema sin acceso administrativo.");
        }

        $permisoId = array_key_exists('permiso_id', $datos)
            ? ($datos['permiso_id'] !== null && $datos['permiso_id'] !== '' && (int) $datos['permiso_id'] > 0 ? (int) $datos['permiso_id'] : null)
            : $opcion->obtenerPermisoId();

        if ($permisoId !== null) {
            $this->validarPermisoExiste($permisoId);
        }

        $orden = isset($datos['orden']) && (int) $datos['orden'] > 0
            ? (int) $datos['orden']
            : $opcion->obtenerOrden();

        $opcionActualizada = new OpcionMenu(
            $id,
            $padreId,
            $clave,
            $nombre,
            $icono,
            $ruta,
            $orden,
            $estado,
            $permisoId,
            $opcion->esSistema(),
            $opcion->obtenerCreadoEn()
        );

        $this->opcionRepo->actualizar($opcionActualizada);

        return $this->opcionRepo->buscarPorId($id, true) ?? $opcionActualizada;
    }

    /**
     * Alterna o asigna el estado de una opción, protegiendo las rutas administrativas críticas.
     *
     * @param int $id
     * @param string|null $nuevoEstado Opcional: 'ACTIVO' o 'INACTIVO'. Si es null, invierte el actual.
     * @return string Estado resultante ('ACTIVO' o 'INACTIVO').
     */
    public function cambiarEstado(int $id, ?string $nuevoEstado = null): string
    {
        $opcion = $this->opcionRepo->buscarPorId($id);
        if ($opcion === null) {
            throw new OpcionMenuNoEncontradaExcepcion("La opción de menú ID {$id} no existe.");
        }

        $estadoFinal = $nuevoEstado !== null
            ? (strtoupper(trim($nuevoEstado)) === 'INACTIVO' ? 'INACTIVO' : 'ACTIVO')
            : ($opcion->esActivo() ? 'INACTIVO' : 'ACTIVO');

        if ($estadoFinal === 'INACTIVO' && $opcion->esSistema() && in_array($opcion->obtenerClave(), self::CLAVES_CRITICAS_SISTEMA, true)) {
            throw new OpcionMenuProtegidaExcepcion("No se puede desactivar la opción estructural '{$opcion->obtenerNombre()}' porque dejaría al sistema sin acceso administrativo.");
        }

        $this->opcionRepo->cambiarEstado($id, $estadoFinal);

        return $estadoFinal;
    }

    /**
     * Reordena transaccionalmente un conjunto homogéneo de opciones que comparten el mismo padre.
     *
     * @param array<int, array{id: int, orden: int}> $elementos
     * @param int|null $padreId Nivel a reordenar (null para categorías principales; int para secundarias).
     * @return bool
     * @throws InvalidArgumentException
     * @throws OpcionMenuNoEncontradaExcepcion
     */
    public function reordenarOpciones(array $elementos, ?int $padreId = null): bool
    {
        if (empty($elementos)) {
            throw new InvalidArgumentException('El conjunto de elementos a reordenar no puede estar vacío.');
        }

        $idsProcesados = [];
        $opcionesValidadas = [];

        foreach ($elementos as $elem) {
            if (!isset($elem['id']) || !isset($elem['orden'])) {
                throw new InvalidArgumentException('Cada elemento debe contener los campos "id" y "orden".');
            }

            $id = (int) $elem['id'];
            $orden = (int) $elem['orden'];

            if ($id <= 0 || $orden <= 0) {
                throw new InvalidArgumentException('Los valores de "id" y "orden" deben ser enteros positivos.');
            }

            if (isset($idsProcesados[$id])) {
                throw new InvalidArgumentException("Se detectó un identificador duplicado en la solicitud de ordenamiento (ID: {$id}).");
            }
            $idsProcesados[$id] = true;

            $opcion = $this->opcionRepo->buscarPorId($id);
            if ($opcion === null) {
                throw new OpcionMenuNoEncontradaExcepcion("La opción de menú con ID {$id} no existe.");
            }

            // Verificar homogeneidad: todos los elementos deben pertenecer exactamente al mismo padre/nivel
            if ($opcion->obtenerPadreId() !== $padreId) {
                throw new InvalidArgumentException('No se permite mezclar elementos de diferentes categorías o niveles en una sola operación de reordenamiento.');
            }

            $opcionesValidadas[] = ['id' => $id, 'orden' => $orden];
        }

        // Ejecución transaccional atómica con timeout y rollback defensivo
        $this->pdo->beginTransaction();
        try {
            foreach ($opcionesValidadas as $item) {
                $this->opcionRepo->actualizarOrden($item['id'], $item['orden']);
            }
            $this->pdo->commit();
            return true;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    /**
     * Elimina una opción de menú verificando que no sea estructural y que no tenga hijos huérfanos.
     *
     * @param int $id
     * @return bool
     */
    public function eliminarOpcion(int $id): bool
    {
        $opcion = $this->opcionRepo->buscarPorId($id);
        if ($opcion === null) {
            throw new OpcionMenuNoEncontradaExcepcion("La opción de menú ID {$id} no existe.");
        }

        if ($opcion->esSistema()) {
            throw new OpcionMenuProtegidaExcepcion("No se puede eliminar la opción estructural del sistema '{$opcion->obtenerNombre()}'.");
        }

        if ($this->opcionRepo->contarHijos($id) > 0) {
            throw new OpcionMenuProtegidaExcepcion("No se puede eliminar la categoría '{$opcion->obtenerNombre()}' porque aún posee opciones secundarias asignadas. Reubíquelas o elimínelas primero.");
        }

        return $this->opcionRepo->eliminar($id);
    }

    /**
     * Valida el formato de la clave técnica.
     */
    private function validarClave(string $clave): void
    {
        if ($clave === '') {
            throw new InvalidArgumentException('La clave técnica del menú no puede estar vacía.');
        }

        if (strlen($clave) > 50) {
            throw new InvalidArgumentException('La clave técnica del menú no puede superar los 50 caracteres.');
        }

        if (!preg_match('/^[a-z0-9_\-]+$/', $clave)) {
            throw new InvalidArgumentException("La clave técnica '{$clave}' contiene caracteres no permitidos. Solo se admiten letras minúsculas, números, guiones y barras bajas.");
        }
    }

    /**
     * Valida el nombre visible de la opción.
     */
    private function validarNombre(string $nombre): void
    {
        if ($nombre === '') {
            throw new InvalidArgumentException('El nombre de la opción de menú no puede estar vacío.');
        }

        if (mb_strlen($nombre) > 100) {
            throw new InvalidArgumentException('El nombre de la opción de menú no puede superar los 100 caracteres.');
        }
    }

    /**
     * Valida la seguridad y formato de una ruta interna.
     *
     * @param string|null $ruta
     * @param bool $esSecundaria
     */
    private function validarRuta(?string $ruta, bool $esSecundaria): void
    {
        if ($ruta === null || $ruta === '') {
            return;
        }

        $rutaLimpia = strtolower(trim($ruta));

        // Rechazar estrictamente esquemas peligrosos para prevenir XSS o redirecciones arbitrarias
        if (str_starts_with($rutaLimpia, 'javascript:')
            || str_starts_with($rutaLimpia, 'data:')
            || str_starts_with($rutaLimpia, 'vbscript:')
            || str_starts_with($rutaLimpia, 'http://')
            || str_starts_with($rutaLimpia, 'https://')
            || str_contains($rutaLimpia, '<')
            || str_contains($rutaLimpia, '>')
            || str_contains($rutaLimpia, '"')
        ) {
            throw new RutaInvalidaExcepcion("La ruta '{$ruta}' es insegura o contiene un esquema no permitido.");
        }

        // Las rutas del menú administrativo deben ser locales internas empezando por '/' o anclas '#'
        if (!str_starts_with($ruta, '/') && !str_starts_with($ruta, '#')) {
            throw new RutaInvalidaExcepcion("La ruta '{$ruta}' debe ser una ruta relativa local que inicie con '/' o '#'.");
        }
    }

    /**
     * Valida la jerarquía estricta de dos niveles impuesta por la arquitectura Alina.
     *
     * @param int|null $padreId
     * @param int|null $opcionId
     */
    private function validarJerarquia(?int $padreId, ?int $opcionId): void
    {
        if ($padreId === null) {
            // Es Nivel 1 (Categoría Principal).
            return;
        }

        // 1. Prohibir autorreferencia
        if ($opcionId !== null && $padreId === $opcionId) {
            throw new NivelMenuInvalidoExcepcion('Una opción de menú no puede asignarse a sí misma como categoría padre.');
        }

        // 2. Verificar que el padre exista
        $padre = $this->opcionRepo->buscarPorId($padreId);
        if ($padre === null) {
            throw new OpcionMenuNoEncontradaExcepcion("La categoría padre con ID {$padreId} no existe.");
        }

        // 3. Jerarquía de 2 niveles: El padre DEBE ser de Nivel 1 (padre_id = null)
        if ($padre->obtenerPadreId() !== null) {
            throw new NivelMenuInvalidoExcepcion('No se permite anidar una opción bajo otra opción secundaria. Camargo PMS maneja un máximo estricto de dos niveles.');
        }

        // 4. Si la opción que se está editando ya tiene hijos, no puede convertirse en secundaria
        if ($opcionId !== null && $this->opcionRepo->contarHijos($opcionId) > 0) {
            throw new NivelMenuInvalidoExcepcion('Una categoría que ya contiene opciones secundarias no puede convertirse en secundaria.');
        }
    }

    /**
     * Valida que un permiso RBAC exista en el catálogo del sistema.
     */
    private function validarPermisoExiste(int $permisoId): void
    {
        $permiso = $this->permisoRepo->buscarPorId($permisoId);
        if ($permiso === null) {
            throw new PermisoNoEncontradoExcepcion("El permiso con ID {$permisoId} no existe en el catálogo RBAC.");
        }
    }

    /**
     * Normaliza una ruta para realizar comparaciones precisas de estado activo.
     */
    private function normalizarRutaParaComparacion(string $ruta): string
    {
        $path = parse_url($ruta, PHP_URL_PATH) ?? '/';
        $path = trim($path);

        if ($path === '' || $path === '/') {
            return '/';
        }

        return '/' . trim($path, '/');
    }
}
