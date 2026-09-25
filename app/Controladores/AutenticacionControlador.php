<?php

declare(strict_types=1);

namespace CamargoPMS\Controladores;

use CamargoPMS\Excepciones\CredencialesInvalidasExcepcion;
use CamargoPMS\Excepciones\CsrfInvalidoExcepcion;
use CamargoPMS\Excepciones\DemasiadosIntentosExcepcion;
use CamargoPMS\Intermediarios\AutenticacionIntermediario;
use CamargoPMS\Nucleo\Ayudante;
use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Nucleo\Vista;
use CamargoPMS\Servicios\AutenticacionServicio;
use CamargoPMS\Servicios\CsrfServicio;
use CamargoPMS\Servicios\SesionServicio;
use Throwable;

/**
 * Controlador de peticiones web para el flujo de autenticación, login y logout.
 *
 * Sigue el patrón PRG (Post-Redirect-Get) para evitar reenvío accidental de credenciales.
 */
class AutenticacionControlador
{
    private AutenticacionServicio $authServicio;
    private CsrfServicio $csrfServicio;
    private AutenticacionIntermediario $authIntermediario;

    public function __construct(
        ?AutenticacionServicio $authServicio = null,
        ?CsrfServicio $csrfServicio = null,
        ?AutenticacionIntermediario $authIntermediario = null
    ) {
        $this->authServicio = $authServicio ?? new AutenticacionServicio();
        $this->csrfServicio = $csrfServicio ?? new CsrfServicio();
        $this->authIntermediario = $authIntermediario ?? new AutenticacionIntermediario();
    }

    /**
     * Muestra la pantalla de inicio de sesión (GET /login).
     *
     * @return Respuesta
     */
    public function mostrarLogin(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        // Si ya cuenta con una sesión válida activa, redirigir al panel principal
        if ($this->authServicio->obtenerUsuarioAutenticado() !== null) {
            return Respuesta::redirigir(Ayudante::ruta('/'));
        }

        $error = $_SESSION['_flash_error'] ?? null;
        unset($_SESSION['_flash_error']);

        $nombreUsuarioPrevio = $_SESSION['_flash_username'] ?? null;
        unset($_SESSION['_flash_username']);

        $returnRaw = $_GET['return'] ?? '/';
        $return = $this->authIntermediario->sanitizarRutaRetorno((string) $returnRaw);

        $vista = new Vista();
        $html = $vista->renderizar('auth/login', [
            'titulo' => 'Iniciar Sesión — Camargo PMS',
            'error' => $error,
            'nombreUsuarioPrevio' => $nombreUsuarioPrevio,
            'return' => $return !== '/' ? $return : null,
        ], null);

        return new Respuesta($html, 200);
    }

    /**
     * Procesa el formulario de credenciales de inicio de sesión (POST /login).
     *
     * @return Respuesta
     */
    public function procesarLogin(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $tokenCsrf = $_POST['_csrf_token'] ?? null;
        $returnRaw = $_POST['return'] ?? '/';
        $rutaRetorno = $this->authIntermediario->sanitizarRutaRetorno((string) $returnRaw);

        // Validación estricta de CSRF
        if (!$this->csrfServicio->validarToken(is_string($tokenCsrf) ? $tokenCsrf : null)) {
            $_SESSION['_flash_error'] = 'Token de seguridad inválido o expirado. Por favor, intente nuevamente.';
            return Respuesta::redirigir($this->construirUrlLoginConRetorno($rutaRetorno));
        }

        $nombreUsuario = isset($_POST['nombre_usuario']) ? (string) $_POST['nombre_usuario'] : '';
        $contrasena = isset($_POST['contrasena']) ? (string) $_POST['contrasena'] : '';

        try {
            $this->authServicio->autenticar($nombreUsuario, $contrasena);

            // PRG exitoso: Redirigir a la ruta interna solicitada o al panel
            return Respuesta::redirigir(Ayudante::ruta($rutaRetorno));
        } catch (CredencialesInvalidasExcepcion $e) {
            $_SESSION['_flash_error'] = $e->getMessage();
            $_SESSION['_flash_username'] = $nombreUsuario;
            return Respuesta::redirigir($this->construirUrlLoginConRetorno($rutaRetorno));
        } catch (DemasiadosIntentosExcepcion $e) {
            $_SESSION['_flash_error'] = $e->getMessage();
            $_SESSION['_flash_username'] = $nombreUsuario;
            return Respuesta::redirigir($this->construirUrlLoginConRetorno($rutaRetorno));
        } catch (Throwable $e) {
            $_SESSION['_flash_error'] = 'Ocurrió un error inesperado al procesar la solicitud.';
            $_SESSION['_flash_username'] = $nombreUsuario;
            return Respuesta::redirigir($this->construirUrlLoginConRetorno($rutaRetorno));
        }
    }

    /**
     * Cierra la sesión activa actual y redirige a la pantalla de login (POST /logout).
     *
     * @return Respuesta
     */
    public function cerrarSesion(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $tokenCsrf = $_POST['_csrf_token'] ?? null;
        if (!$this->csrfServicio->validarToken(is_string($tokenCsrf) ? $tokenCsrf : null)) {
            throw new CsrfInvalidoExcepcion('Token de seguridad CSRF inválido para cierre de sesión.');
        }

        $this->authServicio->cerrarSesion();

        return Respuesta::redirigir(Ayudante::ruta('/login'));
    }

    /**
     * Construye la URL de redirección a login preservando el parámetro return si es aplicable.
     *
     * @param string $rutaRetorno
     * @return string
     */
    private function construirUrlLoginConRetorno(string $rutaRetorno): string
    {
        $url = Ayudante::ruta('/login');
        if ($rutaRetorno !== '/' && $rutaRetorno !== '') {
            $url .= '?return=' . urlencode($rutaRetorno);
        }
        return $url;
    }
}
