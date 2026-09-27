<?php

declare(strict_types=1);

/**
 * Funciones auxiliares globales para vistas, componentes y plantillas de Camargo PMS.
 */

use CamargoPMS\Nucleo\Ayudante;

if (!function_exists('url_asset')) {
    /**
     * Genera la URL normalizada para un recurso público de assets.
     *
     * @param string $recurso Ruta relativa dentro de la carpeta public/assets.
     * @return string URL absoluta para su inclusión en HTML.
     */
    function url_asset(string $recurso): string
    {
        return Ayudante::asset($recurso);
    }
}

if (!function_exists('url_ruta')) {
    /**
     * Genera la URL normalizada para una ruta interna del sistema.
     *
     * @param string $ruta Ruta interna relativa (ej. "/" o "/panel").
     * @return string URL absoluta para enlaces href y formularios.
     */
    function url_ruta(string $ruta = ''): string
    {
        return Ayudante::ruta($ruta);
    }
}

if (!function_exists('e')) {
    /**
     * Escapa valores para salida segura en HTML previniendo XSS.
     *
     * @param mixed $valor Valor a escapar.
     * @return string Cadena saneada en UTF-8.
     */
    function e(mixed $valor): string
    {
        return Ayudante::escapar($valor);
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Obtiene el token CSRF activo de la sesión.
     *
     * @return string
     */
    function csrf_token(): string
    {
        return (new \CamargoPMS\Servicios\CsrfServicio())->obtenerToken();
    }
}

if (!function_exists('csrf_campo')) {
    /**
     * Genera el input hidden para formularios POST con el token CSRF.
     *
     * @return string
     */
    function csrf_campo(): string
    {
        return (new \CamargoPMS\Servicios\CsrfServicio())->generarCampoHtml();
    }
}

if (!function_exists('usuario_autenticado')) {
    /**
     * Obtiene el usuario autenticado en la sesión actual, o null si es anónimo.
     *
     * @return \CamargoPMS\Modelos\Usuario|null
     */
    function usuario_autenticado(): ?\CamargoPMS\Modelos\Usuario
    {
        return (new \CamargoPMS\Servicios\AutenticacionServicio())->obtenerUsuarioAutenticado();
    }
}

if (!function_exists('insignia_badge')) {
    /**
     * Genera un Badge estándar de Alina (Variants of badge).
     */
    function insignia_badge(string $texto, string $variante = 'secondary', ?string $icono = null, string $clasesExtra = ''): string
    {
        return \CamargoPMS\Nucleo\Insignia::badge($texto, $variante, $icono, $clasesExtra);
    }
}

if (!function_exists('insignia_chip')) {
    /**
     * Genera un Chip estándar de Alina (Variants of chip).
     */
    function insignia_chip(string $texto, string $variante = 'primary', ?string $icono = null, string $clasesExtra = ''): string
    {
        return \CamargoPMS\Nucleo\Insignia::chip($texto, $variante, $icono, $clasesExtra);
    }
}

if (!function_exists('insignia_estado')) {
    /**
     * Resuelve y renderiza automáticamente la insignia estándar para un estado.
     */
    function insignia_estado(string $estado, bool $comoChip = false, string $clasesExtra = ''): string
    {
        return \CamargoPMS\Nucleo\Insignia::estado($estado, $comoChip, $clasesExtra);
    }
}

