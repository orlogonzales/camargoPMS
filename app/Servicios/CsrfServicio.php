<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

use CamargoPMS\Excepciones\CsrfInvalidoExcepcion;

/**
 * Servicio transversal para protección contra ataques CSRF (Cross-Site Request Forgery).
 *
 * Emplea tokens aleatorios de alta entropía generados con CSPRNG y comparación en tiempo constante.
 */
class CsrfServicio
{
    private const CLAVE_SESION = '_csrf_token';

    /**
     * Obtiene el token CSRF activo de la sesión actual, o genera uno nuevo si no existe.
     *
     * @return string
     */
    public function obtenerToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            // No iniciar sesión si no es necesario, pero si está inactiva y se solicita token,
            // asegurarse de que haya contexto
        }

        if (empty($_SESSION[self::CLAVE_SESION]) || !is_string($_SESSION[self::CLAVE_SESION])) {
            $_SESSION[self::CLAVE_SESION] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::CLAVE_SESION];
    }

    /**
     * Regenera forzosamente un nuevo token CSRF en la sesión.
     *
     * @return string
     */
    public function regenerarToken(): string
    {
        $_SESSION[self::CLAVE_SESION] = bin2hex(random_bytes(32));
        return $_SESSION[self::CLAVE_SESION];
    }

    /**
     * Valida si el token provisto coincide de manera segura con el token de sesión.
     *
     * @param string|null $token
     * @return bool
     */
    public function validarToken(?string $token): bool
    {
        if ($token === null || trim($token) === '') {
            return false;
        }

        $tokenSesion = $_SESSION[self::CLAVE_SESION] ?? '';
        if (!is_string($tokenSesion) || $tokenSesion === '') {
            return false;
        }

        return hash_equals($tokenSesion, trim($token));
    }

    /**
     * Valida el token o lanza una excepción CsrfInvalidoExcepcion si es incorrecto.
     *
     * @param string|null $token
     * @return void
     * @throws CsrfInvalidoExcepcion
     */
    public function verificarToken(?string $token): void
    {
        if (!$this->validarToken($token)) {
            throw new CsrfInvalidoExcepcion('Token de seguridad CSRF inválido o expirado.');
        }
    }

    /**
     * Genera un campo HTML oculto listo para incrustar en formularios POST.
     *
     * @return string
     */
    public function generarCampoHtml(): string
    {
        $token = htmlspecialchars($this->obtenerToken(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="_csrf_token" value="' . $token . '">';
    }
}
