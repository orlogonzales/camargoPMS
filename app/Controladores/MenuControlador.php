<?php

declare(strict_types=1);

namespace CamargoPMS\Controladores;

use CamargoPMS\Excepciones\NivelMenuInvalidoExcepcion;
use CamargoPMS\Excepciones\OpcionMenuDuplicadaExcepcion;
use CamargoPMS\Excepciones\OpcionMenuNoEncontradaExcepcion;
use CamargoPMS\Excepciones\OpcionMenuProtegidaExcepcion;
use CamargoPMS\Excepciones\PermisoNoEncontradoExcepcion;
use CamargoPMS\Excepciones\RutaInvalidaExcepcion;
use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Nucleo\Vista;
use CamargoPMS\Servicios\CsrfServicio;
use CamargoPMS\Servicios\MenuServicio;
use CamargoPMS\Servicios\SesionServicio;
use InvalidArgumentException;
use Throwable;

/**
 * Controlador de peticiones web y endpoints JSON para la Gestión de Menú.
 *
 * Coordina la presentación de la vista administrativa y despacha las operaciones CRUD,
 * activación/desactivación y ordenamiento transaccional.
 */
class MenuControlador
{
    private MenuServicio $menuServicio;
    private CsrfServicio $csrfServicio;
    private Vista $vista;

    public function __construct(
        ?MenuServicio $menuServicio = null,
        ?CsrfServicio $csrfServicio = null,
        ?Vista $vista = null
    ) {
        $this->menuServicio = $menuServicio ?? new MenuServicio();
        $this->csrfServicio = $csrfServicio ?? new CsrfServicio();
        $this->vista = $vista ?? new Vista();
    }

    /**
     * Muestra la pantalla principal de Gestión de Menú (GET /configuracion/menu).
     *
     * @return Respuesta
     */
    public function index(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $datosGestion = $this->menuServicio->obtenerDatosGestion();

        $datos = [
            'titulo' => 'Camargo PMS — Gestión de Menú',
            'categoriaActiva' => 'configuracion',
            'migasPan' => [
                ['etiqueta' => 'Panel', 'url' => url_ruta('/'), 'activo' => false],
                ['etiqueta' => 'Configuración', 'url' => '#', 'activo' => false],
                ['etiqueta' => 'Gestión de menú', 'url' => url_ruta('/configuracion/menu'), 'activo' => true],
            ],
            'datosGestion' => $datosGestion,
        ];

        $html = $this->vista->renderizar('configuracion/menu/index', $datos, 'principal');

        return new Respuesta($html, 200);
    }

    /**
     * Devuelve la estructura completa del menú y catálogo de permisos en formato JSON (GET /configuracion/menu/datos).
     *
     * @return Respuesta
     */
    public function datosJson(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        try {
            $datos = $this->menuServicio->obtenerDatosGestion();
            return Respuesta::json([
                'exito' => true,
                'datos' => $datos
            ], 200);
        } catch (Throwable $e) {
            return Respuesta::json([
                'exito' => false,
                'error' => 'Error al recuperar los datos del menú: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Procesa la creación de una nueva opción de menú (POST /configuracion/menu).
     *
     * @return Respuesta
     */
    public function crear(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $payload = $this->obtenerPayload();
        if (!$this->validarCsrf($payload)) {
            return Respuesta::json(['exito' => false, 'error' => 'Token CSRF inválido o ausente.'], 403);
        }

        try {
            $opcion = $this->menuServicio->crearOpcion($payload);
            return Respuesta::json([
                'exito' => true,
                'mensaje' => "Opción '{$opcion->obtenerNombre()}' creada exitosamente.",
                'opcion' => $opcion->haciaArreglo()
            ], 201);
        } catch (OpcionMenuDuplicadaExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 409);
        } catch (NivelMenuInvalidoExcepcion | RutaInvalidaExcepcion | PermisoNoEncontradoExcepcion | InvalidArgumentException $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => 'Error interno al crear opción: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Procesa la actualización de una opción existente (PUT|POST /configuracion/menu/{id}).
     *
     * @param string|int $id
     * @return Respuesta
     */
    public function actualizar(string|int $id): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $opcionId = (int) $id;
        $payload = $this->obtenerPayload();

        if (!$this->validarCsrf($payload)) {
            return Respuesta::json(['exito' => false, 'error' => 'Token CSRF inválido o ausente.'], 403);
        }

        try {
            $opcion = $this->menuServicio->actualizarOpcion($opcionId, $payload);
            return Respuesta::json([
                'exito' => true,
                'mensaje' => "Opción '{$opcion->obtenerNombre()}' actualizada con éxito.",
                'opcion' => $opcion->haciaArreglo()
            ], 200);
        } catch (OpcionMenuNoEncontradaExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 404);
        } catch (OpcionMenuDuplicadaExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 409);
        } catch (OpcionMenuProtegidaExcepcion | NivelMenuInvalidoExcepcion | RutaInvalidaExcepcion | PermisoNoEncontradoExcepcion | InvalidArgumentException $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => 'Error interno al actualizar opción: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Alterna o asigna el estado de una opción de menú (PATCH|POST /configuracion/menu/{id}/estado).
     *
     * @param string|int $id
     * @return Respuesta
     */
    public function cambiarEstado(string|int $id): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $opcionId = (int) $id;
        $payload = $this->obtenerPayload();

        if (!$this->validarCsrf($payload)) {
            return Respuesta::json(['exito' => false, 'error' => 'Token CSRF inválido o ausente.'], 403);
        }

        $nuevoEstado = isset($payload['estado']) ? (string) $payload['estado'] : null;

        try {
            $estadoResultado = $this->menuServicio->cambiarEstado($opcionId, $nuevoEstado);
            return Respuesta::json([
                'exito' => true,
                'mensaje' => "Estado actualizado a '{$estadoResultado}'.",
                'estado' => $estadoResultado
            ], 200);
        } catch (OpcionMenuNoEncontradaExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 404);
        } catch (OpcionMenuProtegidaExcepcion | InvalidArgumentException $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => 'Error interno al cambiar estado: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Reordena transaccionalmente una lista de opciones homogéneas (POST /configuracion/menu/orden).
     *
     * @return Respuesta
     */
    public function actualizarOrden(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $payload = $this->obtenerPayload();

        if (!$this->validarCsrf($payload)) {
            return Respuesta::json(['exito' => false, 'error' => 'Token CSRF inválido o ausente.'], 403);
        }

        $elementos = $payload['elementos'] ?? [];
        $padreId = array_key_exists('padre_id', $payload)
            ? ($payload['padre_id'] !== null && $payload['padre_id'] !== '' ? (int) $payload['padre_id'] : null)
            : null;

        if (!is_array($elementos)) {
            return Respuesta::json(['exito' => false, 'error' => 'La lista de elementos a reordenar es inválida.'], 422);
        }

        try {
            $this->menuServicio->reordenarOpciones($elementos, $padreId);
            return Respuesta::json([
                'exito' => true,
                'mensaje' => 'El orden de las opciones fue actualizado con éxito.'
            ], 200);
        } catch (OpcionMenuNoEncontradaExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 404);
        } catch (InvalidArgumentException $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => 'Error interno al actualizar orden: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Elimina una opción de menú que no sea de sistema ni tenga hijos (DELETE|POST /configuracion/menu/{id}/eliminar).
     *
     * @param string|int $id
     * @return Respuesta
     */
    public function eliminar(string|int $id): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $opcionId = (int) $id;
        $payload = $this->obtenerPayload();

        if (!$this->validarCsrf($payload)) {
            return Respuesta::json(['exito' => false, 'error' => 'Token CSRF inválido o ausente.'], 403);
        }

        try {
            $this->menuServicio->eliminarOpcion($opcionId);
            return Respuesta::json([
                'exito' => true,
                'mensaje' => 'Opción de menú eliminada con éxito.'
            ], 200);
        } catch (OpcionMenuNoEncontradaExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 404);
        } catch (OpcionMenuProtegidaExcepcion | InvalidArgumentException $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => 'Error interno al eliminar opción: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Extrae el payload de la petición unificando JSON y formulario estándar.
     *
     * @return array<string, mixed>
     */
    private function obtenerPayload(): array
    {
        $contenidoCrudo = file_get_contents('php://input');
        if (!empty($contenidoCrudo)) {
            $json = json_decode($contenidoCrudo, true);
            if (is_array($json)) {
                return array_merge($_POST, $json);
            }
        }

        return $_POST;
    }

    /**
     * Valida el token CSRF provisto en el payload o en las cabeceras HTTP.
     *
     * @param array<string, mixed> $payload
     * @return bool
     */
    private function validarCsrf(array $payload): bool
    {
        $token = $payload['_csrf_token']
            ?? $payload['_token']
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? $_SERVER['HTTP_X_XSRF_TOKEN']
            ?? null;

        return $this->csrfServicio->validarToken(is_string($token) ? $token : null);
    }
}
