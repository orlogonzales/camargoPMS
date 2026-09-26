<?php

declare(strict_types=1);

namespace CamargoPMS\Intermediarios;

use CamargoPMS\Controladores\PanelControlador;
use CamargoPMS\Nucleo\Ayudante;
use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Servicios\AutorizacionServicio;
use CamargoPMS\Servicios\SesionServicio;

/**
 * Intermediario (Middleware) que protege rutas exigiendo un permiso de autorización RBAC específico.
 *
 * "¿Tienes permiso para acceder a este recurso?"
 * 
 * Flujo:
 * 1. Si no hay sesión válida, redirige a /login con parámetro de retorno.
 * 2. Si hay sesión pero el usuario carece del permiso, retorna HTTP 403 Forbidden seguro.
 * 3. Si cuenta con el permiso requerido (o es Superadministrador activo), permite el paso.
 */
class AutorizacionIntermediario
{
    private string $permisoRequerido;
    private SesionServicio $sesionServicio;
    private AutorizacionServicio $autorizacionServicio;
    private PanelControlador $panelControlador;

    public function __construct(
        string $permisoRequerido,
        ?SesionServicio $sesionServicio = null,
        ?AutorizacionServicio $autorizacionServicio = null,
        ?PanelControlador $panelControlador = null
    ) {
        $this->permisoRequerido = trim($permisoRequerido);
        $this->sesionServicio = $sesionServicio ?? new SesionServicio();
        $this->autorizacionServicio = $autorizacionServicio ?? new AutorizacionServicio();
        $this->panelControlador = $panelControlador ?? new PanelControlador();
    }

    /**
     * Evalúa la autorización de la petición HTTP.
     *
     * @param string $rutaSolicitada
     * @return Respuesta|null Retorna Respuesta HTTP (redirección o 403) si se bloquea, o null para continuar.
     */
    public function manejar(string $rutaSolicitada = '/'): ?Respuesta
    {
        $usuario = $this->sesionServicio->validarSesionActual();

        // Si no está autenticado, redirigir a inicio de sesión
        if ($usuario === null) {
            $rutaRetorno = AutenticacionIntermediario::sanitizarRutaRetorno($rutaSolicitada);
            $urlLogin = Ayudante::ruta('/login');

            if ($rutaRetorno !== '/' && $rutaRetorno !== '') {
                $urlLogin .= '?return=' . urlencode($rutaRetorno);
            }

            return Respuesta::redirigir($urlLogin);
        }

        // Si está autenticado, validar permiso de autorización RBAC
        if (!$this->autorizacionServicio->puede($usuario->obtenerId(), $this->permisoRequerido)) {
            return $this->panelControlador->error(403);
        }

        return null;
    }

    /**
     * Obtiene la clave del permiso que este intermediario exige.
     */
    public function obtenerPermisoRequerido(): string
    {
        return $this->permisoRequerido;
    }
}
