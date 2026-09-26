<?php

declare(strict_types=1);

namespace CamargoPMS\Controladores;

use CamargoPMS\Excepciones\UnidadDuplicadaExcepcion;
use CamargoPMS\Excepciones\UnidadNoEncontradaExcepcion;
use CamargoPMS\Excepciones\ValidacionExcepcion;
use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Nucleo\Vista;
use CamargoPMS\Repositorios\PropiedadRepositorio;
use CamargoPMS\Repositorios\TipoUnidadRepositorio;
use CamargoPMS\Servicios\AuditoriaServicio;
use CamargoPMS\Servicios\AutorizacionServicio;
use CamargoPMS\Servicios\CsrfServicio;
use CamargoPMS\Servicios\SesionServicio;
use CamargoPMS\Servicios\UnidadServicio;
use Throwable;

/**
 * Controlador para la gestión y administración del maestro central de unidades físicas.
 *
 * Principios vinculantes:
 * - PROPIEDAD ≠ UNIDAD: La propiedad es el inmueble/edificación raíz; la unidad es la división física.
 * - UNIDAD ≠ REGISTRO DESECHABLE: Cero eliminación física.
 * - ACTOR ≠ USUARIO (D-061): Trazabilidad append-only identificando actor humano ejecutor.
 */
class UnidadControlador
{
    private UnidadServicio $unidadServicio;
    private PropiedadRepositorio $propiedadRepositorio;
    private TipoUnidadRepositorio $tipoUnidadRepositorio;
    private AuditoriaServicio $auditoriaServicio;
    private SesionServicio $sesionServicio;
    private CsrfServicio $csrfServicio;
    private AutorizacionServicio $autorizacionServicio;
    private Vista $vista;

    public function __construct(
        ?UnidadServicio $unidadServicio = null,
        ?PropiedadRepositorio $propiedadRepositorio = null,
        ?TipoUnidadRepositorio $tipoUnidadRepositorio = null,
        ?AuditoriaServicio $auditoriaServicio = null,
        ?SesionServicio $sesionServicio = null,
        ?CsrfServicio $csrfServicio = null,
        ?AutorizacionServicio $autorizacionServicio = null,
        ?Vista $vista = null
    ) {
        $this->unidadServicio = $unidadServicio ?? new UnidadServicio();
        $this->propiedadRepositorio = $propiedadRepositorio ?? new PropiedadRepositorio();
        $this->tipoUnidadRepositorio = $tipoUnidadRepositorio ?? new TipoUnidadRepositorio(\CamargoPMS\Nucleo\BaseDatos::conexion());
        $this->auditoriaServicio = $auditoriaServicio ?? new AuditoriaServicio();
        $this->sesionServicio = $sesionServicio ?? new SesionServicio();
        $this->csrfServicio = $csrfServicio ?? new CsrfServicio();
        $this->autorizacionServicio = $autorizacionServicio ?? new AutorizacionServicio();
        $this->vista = $vista ?? new Vista();
    }

    /**
     * Renderiza la vista principal del catálogo de unidades (GET /unidades).
     */
    public function index(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $usuarioActualId = $usuarioActual !== null ? (int)$usuarioActual->obtenerId() : 0;

        $capacidades = [
            'puede_crear' => $this->autorizacionServicio->puede($usuarioActualId, 'unidades.crear'),
            'puede_editar' => $this->autorizacionServicio->puede($usuarioActualId, 'unidades.editar'),
            'puede_cambiar_estado' => $this->autorizacionServicio->puede($usuarioActualId, 'unidades.cambiar_estado'),
        ];

        // Obtener propiedades activas para filtro y selector de modal
        $propiedades = $this->propiedadRepositorio->listar(null, 'ACTIVO', null, 100, 0);
        $propiedadesFormateadas = array_map(static function ($p) {
            return [
                'id' => (int)$p->obtenerId(),
                'codigo' => $p->obtenerCodigo(),
                'nombre' => $p->obtenerNombre(),
            ];
        }, $propiedades);

        // Obtener tipos de unidad para selector
        $tiposUnidad = $this->tipoUnidadRepositorio->listarActivos();
        $tiposFormateados = array_map(static function ($t) {
            return [
                'id' => (int)$t->obtenerId(),
                'codigo' => $t->obtenerCodigo(),
                'nombre' => $t->obtenerNombre(),
            ];
        }, $tiposUnidad);

        // Propiedad prefiltrada opcional si viene por query string
        $propiedadIdFiltro = isset($_GET['propiedad_id']) && is_numeric($_GET['propiedad_id'])
            ? (int)$_GET['propiedad_id']
            : null;

        $datos = [
            'titulo' => 'Camargo PMS — Catálogo de Unidades',
            'categoriaActiva' => 'propiedades',
            'subcategoriaActiva' => 'unidades_catalogo',
            'migasPan' => [
                ['etiqueta' => 'Panel', 'url' => url_ruta('/'), 'activo' => false],
                ['etiqueta' => 'Propiedades', 'url' => url_ruta('/propiedades'), 'activo' => false],
                ['etiqueta' => 'Unidades', 'url' => url_ruta('/unidades'), 'activo' => true],
            ],
            'capacidades' => $capacidades,
            'propiedades' => $propiedadesFormateadas,
            'tiposUnidad' => $tiposFormateados,
            'propiedadIdFiltro' => $propiedadIdFiltro,
            'csrf_token' => $this->csrfServicio->obtenerToken(),
        ];

        $html = $this->vista->renderizar('unidades/index', $datos, 'principal');

        return new Respuesta($html, 200);
    }

    /**
     * Retorna el listado paginado y filtrado de unidades en formato JSON (GET /unidades/datos).
     */
    public function datosJson(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $filtros = [
            'busqueda' => isset($_GET['busqueda']) ? trim((string)$_GET['busqueda']) : null,
            'propiedad_id' => isset($_GET['propiedad_id']) && is_numeric($_GET['propiedad_id']) ? (int)$_GET['propiedad_id'] : null,
            'tipo_unidad_id' => isset($_GET['tipo_unidad_id']) && is_numeric($_GET['tipo_unidad_id']) ? (int)$_GET['tipo_unidad_id'] : null,
            'estado' => isset($_GET['estado']) && in_array(strtoupper(trim((string)$_GET['estado'])), ['ACTIVO', 'INACTIVO'], true)
                ? strtoupper(trim((string)$_GET['estado']))
                : null,
        ];

        $pagina = isset($_GET['pagina']) && is_numeric($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
        $limite = isset($_GET['limite']) && is_numeric($_GET['limite']) ? max(1, min(100, (int)$_GET['limite'])) : 15;

        try {
            $resultado = $this->unidadServicio->listar($filtros, $pagina, $limite);

            $unidadesFormateadas = array_map(static function ($u) {
                return $u->aArreglo();
            }, $resultado['unidades']);

            return Respuesta::json([
                'ok' => true,
                'datos' => $unidadesFormateadas,
                'paginacion' => [
                    'total' => $resultado['total'],
                    'pagina' => $resultado['pagina'],
                    'limite' => $resultado['limite'],
                    'paginas' => $resultado['total_paginas'],
                ],
            ], 200);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => 'Error al consultar el catálogo de unidades: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retorna el detalle de una unidad en formato JSON (GET /unidades/{id}).
     */
    public function detalle(string|int|array $id): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $idInt = is_array($id) ? (int)($id['id'] ?? 0) : (int)$id;
        if ($idInt <= 0) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Identificador de unidad inválido.'], 400);
        }

        try {
            $unidad = $this->unidadServicio->obtenerPorId($idInt);

            return Respuesta::json([
                'ok' => true,
                'datos' => $unidad->aArreglo(),
                'unidad' => $unidad->aArreglo(),
            ], 200);
        } catch (UnidadNoEncontradaExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al obtener la unidad: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Renderiza la vista de ficha técnica y perfil de la unidad (GET /unidades/{id}/perfil).
     */
    public function perfil(string|int|array $id): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $idInt = is_array($id) ? (int)($id['id'] ?? 0) : (int)$id;
        if ($idInt <= 0) {
            return new Respuesta($this->vista->renderizar('errores/error', [
                'titulo' => '400 — Solicitud inválida',
                'codigo' => 400,
                'mensaje' => 'El identificador de unidad es inválido.',
            ], 'error'), 400);
        }

        try {
            $unidad = $this->unidadServicio->obtenerPorId($idInt);
            $propiedad = $this->propiedadRepositorio->buscarPorId($unidad->obtenerPropiedadId());

            $usuarioActual = $this->sesionServicio->validarSesionActual();
            $usuarioActualId = $usuarioActual !== null ? (int)$usuarioActual->obtenerId() : 0;

            $capacidades = [
                'puede_editar' => $this->autorizacionServicio->puede($usuarioActualId, 'unidades.editar'),
                'puede_cambiar_estado' => $this->autorizacionServicio->puede($usuarioActualId, 'unidades.cambiar_estado'),
            ];

            // Obtener tipos de unidad para modal de edición
            $tiposUnidad = $this->tipoUnidadRepositorio->listarActivos();
            $tiposFormateados = array_map(static function ($t) {
                return [
                    'id' => (int)$t->obtenerId(),
                    'codigo' => $t->obtenerCodigo(),
                    'nombre' => $t->obtenerNombre(),
                ];
            }, $tiposUnidad);

            // Obtener registros de auditoría de esta unidad
            $registrosAuditoria = $this->auditoriaServicio->listarPorEntidad('unidades', (string)$idInt);
            $historialAuditoria = array_map(static fn($r) => $r->aArreglo(), array_slice($registrosAuditoria, 0, 10));

            $datos = [
                'titulo' => "Camargo PMS — Unidad {$unidad->obtenerCodigo()}",
                'categoriaActiva' => 'propiedades',
                'subcategoriaActiva' => 'unidades_catalogo',
                'migasPan' => [
                    ['etiqueta' => 'Panel', 'url' => url_ruta('/'), 'activo' => false],
                    ['etiqueta' => 'Propiedades', 'url' => url_ruta('/propiedades'), 'activo' => false],
                    ['etiqueta' => $propiedad !== null ? $propiedad->obtenerNombre() : 'Propiedad', 'url' => url_ruta("/propiedades/{$unidad->obtenerPropiedadId()}/perfil"), 'activo' => false],
                    ['etiqueta' => 'Unidades', 'url' => url_ruta('/unidades'), 'activo' => false],
                    ['etiqueta' => $unidad->obtenerCodigo(), 'url' => url_ruta("/unidades/{$idInt}/perfil"), 'activo' => true],
                ],
                'unidad' => $unidad,
                'propiedad' => $propiedad,
                'tiposUnidad' => $tiposFormateados,
                'capacidades' => $capacidades,
                'historialAuditoria' => $historialAuditoria,
                'csrf_token' => $this->csrfServicio->obtenerToken(),
            ];

            $html = $this->vista->renderizar('unidades/detalle', $datos, 'principal');

            return new Respuesta($html, 200);
        } catch (UnidadNoEncontradaExcepcion $e) {
            return new Respuesta($this->vista->renderizar('errores/error', [
                'titulo' => '404 — Unidad no encontrada',
                'codigo' => 404,
                'mensaje' => $e->getMessage(),
            ], 'error'), 404);
        } catch (Throwable $e) {
            return new Respuesta($this->vista->renderizar('errores/error', [
                'titulo' => '500 — Error interno',
                'codigo' => 500,
                'mensaje' => 'Ocurrió un error al cargar la ficha técnica de la unidad.',
            ], 'error'), 500);
        }
    }

    /**
     * Procesa la creación de una nueva unidad física (POST /unidades).
     */
    public function crear(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $datos = $this->obtenerCuerpoPeticion();

        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Token CSRF inválido o ausente.'], 403);
        }

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $usuarioActualId = $usuarioActual !== null ? (int)$usuarioActual->obtenerId() : null;

        try {
            $unidad = $this->unidadServicio->crear($datos, $usuarioActualId);

            return Respuesta::json([
                'ok' => true,
                'mensaje' => "La unidad '{$unidad->obtenerCodigo()}' ({$unidad->obtenerNombre()}) fue registrada exitosamente.",
                'datos' => $unidad->aArreglo(),
                'unidad' => $unidad->aArreglo(),
            ], 201);
        } catch (ValidacionExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => 'Existen inconsistencias en los datos proporcionados.',
                'errores' => $e->obtenerErrores(),
            ], 422);
        } catch (UnidadDuplicadaExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => $e->getMessage(),
                'errores' => ['codigo' => [$e->getMessage()]],
            ], 409);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => 'Error inesperado al crear la unidad: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Procesa la actualización de una unidad (PUT/POST /unidades/{id}).
     */
    public function actualizar(string|int|array $id): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $datos = $this->obtenerCuerpoPeticion();

        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Token CSRF inválido o ausente.'], 403);
        }

        $idInt = is_array($id) ? (int)($id['id'] ?? 0) : (int)$id;
        if ($idInt <= 0) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Identificador de unidad inválido.'], 400);
        }

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $usuarioActualId = $usuarioActual !== null ? (int)$usuarioActual->obtenerId() : null;

        try {
            $unidad = $this->unidadServicio->actualizar($idInt, $datos, $usuarioActualId);

            return Respuesta::json([
                'ok' => true,
                'mensaje' => "La unidad '{$unidad->obtenerCodigo()}' fue actualizada exitosamente.",
                'datos' => $unidad->aArreglo(),
                'unidad' => $unidad->aArreglo(),
            ], 200);
        } catch (UnidadNoEncontradaExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (ValidacionExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => 'Existen inconsistencias en los datos proporcionados.',
                'errores' => $e->obtenerErrores(),
            ], 422);
        } catch (UnidadDuplicadaExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => $e->getMessage(),
                'errores' => ['codigo' => [$e->getMessage()]],
            ], 409);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => 'Error inesperado al actualizar la unidad: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Procesa la alternancia de estado operativo (PATCH/POST /unidades/{id}/estado).
     */
    public function cambiarEstado(string|int|array $id): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $datos = $this->obtenerCuerpoPeticion();

        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Token CSRF inválido o ausente.'], 403);
        }

        $idInt = is_array($id) ? (int)($id['id'] ?? 0) : (int)$id;
        if ($idInt <= 0) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Identificador de unidad inválido.'], 400);
        }

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $usuarioActualId = $usuarioActual !== null ? (int)$usuarioActual->obtenerId() : null;

        $nuevoEstado = isset($datos['estado']) ? (string)$datos['estado'] : '';
        $motivo = isset($datos['motivo']) && trim((string)$datos['motivo']) !== '' ? trim((string)$datos['motivo']) : null;

        try {
            $unidad = $this->unidadServicio->cambiarEstado($idInt, $nuevoEstado, $motivo, $usuarioActualId);

            return Respuesta::json([
                'ok' => true,
                'mensaje' => "El estado de la unidad '{$unidad->obtenerCodigo()}' cambió a {$unidad->obtenerEstado()}.",
                'datos' => $unidad->aArreglo(),
                'unidad' => $unidad->aArreglo(),
            ], 200);
        } catch (UnidadNoEncontradaExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (ValidacionExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => 'Inconsistencia en el cambio de estado.',
                'errores' => $e->obtenerErrores(),
            ], 422);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => 'Error inesperado al cambiar estado: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Extrae los datos del cuerpo de la petición ya sea form-data o JSON crudo.
     *
     * @return array<string, mixed>
     */
    private function obtenerCuerpoPeticion(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            $contenido = file_get_contents('php://input');
            $decodificado = json_decode($contenido, true);
            if (is_array($decodificado)) {
                return $decodificado;
            }
        }

        return $_POST;
    }

    /**
     * Valida el token CSRF presente en el payload o cabeceras HTTP.
     *
     * @param array<string, mixed> $payload
     * @return bool
     */
    private function validarCsrf(array $payload): bool
    {
        $token = $payload['_csrf_token']
            ?? $payload['csrf_token']
            ?? $payload['_token']
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? $_SERVER['HTTP_X_XSRF_TOKEN']
            ?? null;

        if ($token === null || !is_string($token) || trim($token) === '') {
            return false;
        }

        return $this->csrfServicio->validarToken($token);
    }
}
