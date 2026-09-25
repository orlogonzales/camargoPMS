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
