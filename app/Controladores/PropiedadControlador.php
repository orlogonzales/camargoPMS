<?php

declare(strict_types=1);

namespace CamargoPMS\Controladores;

use CamargoPMS\Excepciones\PropiedadDuplicadaExcepcion;
use CamargoPMS\Excepciones\PropiedadNoEncontradaExcepcion;
use CamargoPMS\Excepciones\ValidacionExcepcion;
use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Nucleo\Vista;
use CamargoPMS\Repositorios\PaisRepositorio;
use CamargoPMS\Servicios\AutorizacionServicio;
use CamargoPMS\Servicios\CsrfServicio;
use CamargoPMS\Servicios\PropiedadServicio;
use CamargoPMS\Servicios\SesionServicio;
use Throwable;

/**
 * Controlador de gestión del catálogo maestro de Propiedades e Inmuebles Físicos.
 *
 * Principios vinculantes:
 * - PROPIEDAD ≠ UNIDAD: Inmueble físico contenedor, no unidad arrendable.
 * - PROPIEDAD ≠ REGISTRO DESECHABLE: No DELETE físico; preservación mediante ACTIVO / INACTIVO.
 * - ACTOR ≠ USUARIO (D-061): Trazabilidad append-only identificando actor humano ejecutor.
 */
class PropiedadControlador
{
    private PropiedadServicio $propiedadServicio;
    private PaisRepositorio $paisRepositorio;
    private SesionServicio $sesionServicio;
    private CsrfServicio $csrfServicio;
    private AutorizacionServicio $autorizacionServicio;
    private Vista $vista;

    public function __construct(
        ?PropiedadServicio $propiedadServicio = null,
        ?PaisRepositorio $paisRepositorio = null,
        ?SesionServicio $sesionServicio = null,
        ?CsrfServicio $csrfServicio = null,
        ?AutorizacionServicio $autorizacionServicio = null,
        ?Vista $vista = null
    ) {
        $this->propiedadServicio = $propiedadServicio ?? new PropiedadServicio();
        $this->paisRepositorio = $paisRepositorio ?? new PaisRepositorio();
        $this->sesionServicio = $sesionServicio ?? new SesionServicio();
        $this->csrfServicio = $csrfServicio ?? new CsrfServicio();
        $this->autorizacionServicio = $autorizacionServicio ?? new AutorizacionServicio();
        $this->vista = $vista ?? new Vista();
    }

    /**
     * Renderiza la vista principal del maestro de propiedades (GET /propiedades).
     *
     * @return Respuesta
     */
    public function index(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $usuarioActualId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : 0;

        $capacidades = [
            'puede_crear' => $this->autorizacionServicio->puede($usuarioActualId, 'propiedades.crear'),
            'puede_editar' => $this->autorizacionServicio->puede($usuarioActualId, 'propiedades.editar'),
            'puede_cambiar_estado' => $this->autorizacionServicio->puede($usuarioActualId, 'propiedades.cambiar_estado'),
        ];

        $paises = $this->paisRepositorio->listarActivos();
        $paisesFormateados = array_map(static function ($p) {
            return [
                'id' => (int) $p->obtenerId(),
                'codigo_iso2' => $p->obtenerCodigoIso2(),
                'codigo_iso3' => $p->obtenerCodigoIso3(),
                'nombre' => $p->obtenerNombre(),
            ];
        }, $paises);

        $datos = [
            'titulo' => 'Camargo PMS — Maestro de Propiedades',
            'categoriaActiva' => 'propiedades',
            'migasPan' => [
                ['etiqueta' => 'Panel', 'url' => url_ruta('/'), 'activo' => false],
                ['etiqueta' => 'Propiedades', 'url' => url_ruta('/propiedades'), 'activo' => true],
            ],
            'capacidades' => $capacidades,
            'paises' => $paisesFormateados,
            'csrf_token' => $this->csrfServicio->obtenerToken(),
        ];

        $html = $this->vista->renderizar('propiedades/index', $datos, 'principal');

        return new Respuesta($html, 200);
    }

    /**
     * Retorna el listado paginado y filtrado de propiedades en formato JSON (GET /propiedades/datos).
     *
     * @return Respuesta
     */
    public function datosJson(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $busqueda = isset($_GET['busqueda']) ? (string) $_GET['busqueda'] : null;
        $estado = isset($_GET['estado']) ? (string) $_GET['estado'] : null;
        $paisId = isset($_GET['pais_id']) && is_numeric($_GET['pais_id']) ? (int) $_GET['pais_id'] : null;
        $pagina = isset($_GET['pagina']) && is_numeric($_GET['pagina']) ? max(1, (int) $_GET['pagina']) : 1;
        $limite = isset($_GET['limite']) && is_numeric($_GET['limite']) ? max(1, (int) $_GET['limite']) : null;

        try {
            $resultado = $this->propiedadServicio->listar($busqueda, $estado, $paisId, $pagina, $limite);

            return Respuesta::json([
                'ok' => true,
                'exito' => true,
                'datos' => $resultado['propiedades'],
                'paginacion' => [
                    'total_registros' => $resultado['total'],
                    'pagina_actual' => $resultado['pagina'],
                    'limite' => $resultado['limite'],
                    'total_paginas' => $resultado['paginas'],
                ],
            ], 200);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Error al recuperar listado de propiedades: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retorna la información completa de una propiedad en formato JSON (GET /propiedades/{id}).
     *
     * @param string|int $id
     * @return Respuesta
     */
    public function detalle(string|int $id): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $propiedadId = (int) $id;
        if ($propiedadId <= 0) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Identificador de propiedad inválido.',
            ], 400);
        }

        try {
            $propiedad = $this->propiedadServicio->obtenerPorId($propiedadId);

            return Respuesta::json([
                'ok' => true,
                'exito' => true,
                'datos' => $propiedad->aArreglo(),
            ], 200);
        } catch (PropiedadNoEncontradaExcepcion $e) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => $e->getMessage()], 404);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Error al recuperar detalle de la propiedad: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Renderiza la ficha visual y perfil completo de una propiedad (GET /propiedades/{id}/perfil).
     *
     * @param string|int $id
     * @return Respuesta
     */
    public function perfil(string|int $id): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $propiedadId = (int) $id;
        if ($propiedadId <= 0) {
            $html404 = $this->vista->renderizar('errores/404', ['titulo' => 'Propiedad no encontrada'], 'principal');
            return new Respuesta($html404, 404);
        }

        try {
            $propiedad = $this->propiedadServicio->obtenerPorId($propiedadId);
        } catch (PropiedadNoEncontradaExcepcion $e) {
            $html404 = $this->vista->renderizar('errores/404', ['titulo' => 'Propiedad no encontrada'], 'principal');
            return new Respuesta($html404, 404);
        }

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $usuarioActualId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : 0;

        $capacidades = [
            'puede_editar' => $this->autorizacionServicio->puede($usuarioActualId, 'propiedades.editar'),
            'puede_cambiar_estado' => $this->autorizacionServicio->puede($usuarioActualId, 'propiedades.cambiar_estado'),
        ];

        $paises = $this->paisRepositorio->listarActivos();
        $paisesFormateados = array_map(static function ($p) {
            return [
                'id' => (int) $p->obtenerId(),
                'codigo_iso2' => $p->obtenerCodigoIso2(),
                'codigo_iso3' => $p->obtenerCodigoIso3(),
                'nombre' => $p->obtenerNombre(),
            ];
        }, $paises);

        $datos = [
            'titulo' => 'Camargo PMS — Ficha de Propiedad: ' . $propiedad->obtenerNombre(),
            'categoriaActiva' => 'propiedades',
            'migasPan' => [
                ['etiqueta' => 'Panel', 'url' => url_ruta('/'), 'activo' => false],
                ['etiqueta' => 'Propiedades', 'url' => url_ruta('/propiedades'), 'activo' => false],
                ['etiqueta' => $propiedad->obtenerNombre(), 'url' => url_ruta("/propiedades/{$propiedadId}/perfil"), 'activo' => true],
            ],
            'propiedad' => $propiedad,
            'paises' => $paisesFormateados,
            'capacidades' => $capacidades,
            'csrf_token' => $this->csrfServicio->obtenerToken(),
        ];

        $html = $this->vista->renderizar('propiedades/detalle', $datos, 'principal');

        return new Respuesta($html, 200);
    }

    /**
     * Registra una nueva propiedad física en el catálogo maestro (POST /propiedades).
     *
     * @return Respuesta
     */
    public function crear(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $payload = $this->obtenerPayload();
        if (!$this->validarCsrf($payload)) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => 'Token CSRF inválido o ausente.'], 403);
        }

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $ejecutorId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : null;

        try {
            $propiedad = $this->propiedadServicio->crear($payload, $ejecutorId);

            return Respuesta::json([
                'ok' => true,
                'exito' => true,
                'mensaje' => "Propiedad '{$propiedad->obtenerNombre()}' registrada exitosamente.",
                'datos' => $propiedad->aArreglo(),
            ], 201);
        } catch (PropiedadDuplicadaExcepcion $e) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => $e->getMessage()], 409);
        } catch (ValidacionExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => $e->getMessage(),
                'errores' => $e->obtenerErrores(),
            ], 422);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Error interno al registrar la propiedad: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualiza los datos de una propiedad física (PUT/POST /propiedades/{id}).
     *
     * @param string|int $id
     * @return Respuesta
     */
    public function actualizar(string|int $id): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $propiedadId = (int) $id;
        if ($propiedadId <= 0) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => 'Identificador de propiedad inválido.'], 400);
        }

        $payload = $this->obtenerPayload();
        if (!$this->validarCsrf($payload)) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => 'Token CSRF inválido o ausente.'], 403);
        }

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $ejecutorId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : null;

        try {
            $propiedad = $this->propiedadServicio->actualizar($propiedadId, $payload, $ejecutorId);

            return Respuesta::json([
                'ok' => true,
                'exito' => true,
                'mensaje' => "Propiedad '{$propiedad->obtenerNombre()}' actualizada exitosamente.",
                'datos' => $propiedad->aArreglo(),
            ], 200);
        } catch (PropiedadNoEncontradaExcepcion $e) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => $e->getMessage()], 404);
        } catch (PropiedadDuplicadaExcepcion $e) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => $e->getMessage()], 409);
        } catch (ValidacionExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => $e->getMessage(),
                'errores' => $e->obtenerErrores(),
            ], 422);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Error interno al actualizar la propiedad: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cambia el estado operativo de una propiedad (ACTIVO / INACTIVO) (PATCH/POST /propiedades/{id}/estado).
     *
     * @param string|int $id
     * @return Respuesta
     */
    public function cambiarEstado(string|int $id): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $propiedadId = (int) $id;
        if ($propiedadId <= 0) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => 'Identificador de propiedad inválido.'], 400);
        }

        $payload = $this->obtenerPayload();
        if (!$this->validarCsrf($payload)) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => 'Token CSRF inválido o ausente.'], 403);
        }

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $ejecutorId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : null;

        $nuevoEstado = (string) ($payload['estado'] ?? $payload['nuevo_estado'] ?? '');
        $motivo = isset($payload['motivo']) && trim((string) $payload['motivo']) !== '' ? (string) $payload['motivo'] : null;

        try {
            $propiedad = $this->propiedadServicio->cambiarEstado($propiedadId, $nuevoEstado, $motivo, $ejecutorId);

            return Respuesta::json([
                'ok' => true,
                'exito' => true,
                'mensaje' => "Estado de la propiedad '{$propiedad->obtenerNombre()}' actualizado a {$propiedad->obtenerEstado()}.",
                'datos' => $propiedad->aArreglo(),
            ], 200);
        } catch (PropiedadNoEncontradaExcepcion $e) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => $e->getMessage()], 404);
        } catch (ValidacionExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => $e->getMessage(),
                'errores' => $e->obtenerErrores(),
            ], 422);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Error interno al cambiar el estado de la propiedad: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtiene el payload de la petición parseando JSON o $_POST según corresponda.
     *
     * @return array<string, mixed>
     */
    private function obtenerPayload(): array
    {
        $tipoContenido = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($tipoContenido, 'application/json')) {
            $cuerpo = file_get_contents('php://input');
            if ($cuerpo !== false && trim($cuerpo) !== '') {
                $decodificado = json_decode($cuerpo, true);
                if (is_array($decodificado)) {
                    return $decodificado;
                }
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
