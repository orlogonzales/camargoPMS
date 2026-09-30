<?php

declare(strict_types=1);

namespace CamargoPMS\Controladores;

use CamargoPMS\Excepciones\EntidadNoEncontradaExcepcion;
use CamargoPMS\Excepciones\ValidacionExcepcion;
use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Nucleo\Vista;
use CamargoPMS\Repositorios\PersonaRepositorio;
use CamargoPMS\Repositorios\UsuarioRepositorio;
use CamargoPMS\Servicios\CsrfServicio;
use CamargoPMS\Servicios\FotoPersonaServicio;
use CamargoPMS\Servicios\SesionServicio;
use Throwable;

/**
 * Controlador de Perfil de Usuario para Camargo PMS (UI-ALINA-1B / UI-ALINA-1B-C1).
 *
 * Basado en la interfaz y componentes de Alina profile.html.
 * Aplica estrictamente el principio arquitectónico:
 * PERSONA ≠ USUARIO
 *
 * Gestiona la persistencia segura de la fotografía de Persona delegando
 * en FotoPersonaServicio, manteniendo el controlador delgado y desacoplado.
 */
class PerfilControlador
{
    private Vista $vista;
    private SesionServicio $sesionServicio;
    private UsuarioRepositorio $usuarioRepo;
    private PersonaRepositorio $personaRepo;
    private FotoPersonaServicio $fotoPersonaServicio;
    private CsrfServicio $csrfServicio;

    public function __construct(
        ?Vista $vista = null,
        ?SesionServicio $sesionServicio = null,
        ?UsuarioRepositorio $usuarioRepo = null,
        ?PersonaRepositorio $personaRepo = null,
        ?FotoPersonaServicio $fotoPersonaServicio = null,
        ?CsrfServicio $csrfServicio = null
    ) {
        $this->vista = $vista ?? new Vista();
        $this->sesionServicio = $sesionServicio ?? new SesionServicio();
        $this->usuarioRepo = $usuarioRepo ?? new UsuarioRepositorio();
        $this->personaRepo = $personaRepo ?? new PersonaRepositorio();
        $this->fotoPersonaServicio = $fotoPersonaServicio ?? new FotoPersonaServicio(null, $this->personaRepo);
        $this->csrfServicio = $csrfServicio ?? new CsrfServicio();
    }

    /**
     * Muestra la pantalla de perfil del usuario autenticado actual.
     *
     * @return Respuesta
     */
    public function index(): Respuesta
    {
        $usuarioActual = $this->sesionServicio->validarSesionActual();
        if ($usuarioActual === null) {
            return Respuesta::redireccionar(url_ruta('/login'));
        }

        $usuarioId = (int) $usuarioActual->obtenerId();
        $personaId = (int) $usuarioActual->obtenerPersonaId();

        // 1. Cargar datos técnicos y roles del Usuario (cuenta de acceso)
        $detalleUsuario = $this->usuarioRepo->buscarDetallePorId($usuarioId);

        // 2. Cargar datos de la Persona humana vinculada (sujeto natural)
        $persona = $this->personaRepo->buscarPorId($personaId, true);

        // 3. Resolver URL de presentación de fotografía
        $fotoRuta = $persona?->obtenerFotoRuta();
        $urlFoto = $this->fotoPersonaServicio->resolverUrlFoto($fotoRuta);

        // 4. Preparar datos para la vista respetando PERSONA ≠ USUARIO
        $datos = [
            'titulo' => 'Camargo PMS — Mi Perfil',
            'categoriaActiva' => 'inicio',
            'migasPan' => [
                ['etiqueta' => 'Inicio', 'url' => url_ruta('/')],
                ['etiqueta' => 'Mi Perfil', 'activo' => true],
            ],
            'usuario' => $usuarioActual,
            'detalleUsuario' => $detalleUsuario,
            'persona' => $persona,
            'fotoRuta' => $fotoRuta,
            'urlFoto' => $urlFoto,
            'csrf_token' => $this->csrfServicio->obtenerToken(),
        ];

        $html = $this->vista->renderizar('perfil/index', $datos, 'principal');

        return new Respuesta($html, 200);
    }

    /**
     * Endpoint autenticado para actualizar la fotografía de la Persona asociada al usuario en sesión.
     *
     * Requerimientos estrictos:
     * - Sesión válida.
     * - Validación de token CSRF.
     * - La Persona objetivo deriva obligatoriamente del usuario autenticado (no acepta persona_id externo).
     *
     * @return Respuesta
     */
    public function actualizarFoto(): Respuesta
    {
        // 1. Autenticación de sesión
        $usuarioActual = $this->sesionServicio->validarSesionActual();
        if ($usuarioActual === null) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Sesión no válida o expirada. Debe iniciar sesión.',
            ], 401);
        }

        // 2. Validación de CSRF
        $token = $_POST['_csrf_token']
            ?? $_POST['csrf_token']
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? $_SERVER['HTTP_X_XSRF_TOKEN']
            ?? null;

        if (!$this->csrfServicio->validarToken($token)) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Token CSRF inválido o ausente.',
            ], 403);
        }

        // 3. Resolución segura de la Persona vinculada (derivada de la sesión, no del payload)
        $personaId = (int) $usuarioActual->obtenerPersonaId();
        if ($personaId <= 0) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'El usuario autenticado no cuenta con una Persona natural vinculada.',
            ], 422);
        }

        // 4. Extracción del archivo subido
        $archivo = $_FILES['foto']
            ?? $_FILES['avatar']
            ?? $_FILES['imageUpload']
            ?? [];

        if (empty($archivo)) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'No se recibió ningún archivo de imagen para procesar.',
            ], 422);
        }

        // 5. Procesamiento atómico mediante el servicio
        try {
            $fotoRuta = $this->fotoPersonaServicio->procesarSubidaFoto($personaId, $archivo);
            $urlFoto = $this->fotoPersonaServicio->resolverUrlFoto($fotoRuta);

            return Respuesta::json([
                'ok' => true,
                'exito' => true,
                'mensaje' => 'Fotografía de perfil actualizada con éxito.',
                'foto_ruta' => $fotoRuta,
                'url_foto' => $urlFoto,
            ], 200);
        } catch (ValidacionExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => $e->getMessage(),
                'errores' => $e->obtenerErrores(),
            ], 422);
        } catch (EntidadNoEncontradaExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => $e->getMessage(),
            ], 404);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Ocurrió un error inesperado al procesar la fotografía: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Endpoint autenticado para eliminar la fotografía de perfil y restaurar el avatar neutro.
     *
     * @return Respuesta
     */
    public function eliminarFoto(): Respuesta
    {
        $usuarioActual = $this->sesionServicio->validarSesionActual();
        if ($usuarioActual === null) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Sesión no válida o expirada.',
            ], 401);
        }

        $token = $_POST['_csrf_token']
            ?? $_POST['csrf_token']
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? $_SERVER['HTTP_X_XSRF_TOKEN']
            ?? null;

        if (!$this->csrfServicio->validarToken($token)) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Token CSRF inválido o ausente.',
            ], 403);
        }

        $personaId = (int) $usuarioActual->obtenerPersonaId();
        if ($personaId <= 0) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'El usuario autenticado no cuenta con una Persona natural vinculada.',
            ], 422);
        }

        try {
            $this->fotoPersonaServicio->eliminarFoto($personaId);
            $urlFallback = $this->fotoPersonaServicio->resolverUrlFoto(null);

            return Respuesta::json([
                'ok' => true,
                'exito' => true,
                'mensaje' => 'Fotografía eliminada correctamente. Se restauró el avatar por defecto.',
                'url_foto' => $urlFallback,
            ], 200);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Error al eliminar la fotografía: ' . $e->getMessage(),
            ], 500);
        }
    }
}
