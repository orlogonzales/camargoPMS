<?php

declare(strict_types=1);

namespace CamargoPMS\Nucleo;

/**
 * Clase de utilidades para URLs, rutas y escape de Camargo PMS.
 */
final class Ayudante
{
    /**
     * URL base de la aplicación (ej. "" si está en raíz/vhost, o "/app.camargo-pms" si es subcarpeta).
     */
    private static string $urlBase = '';

    /**
     * Define la URL base detectada por el Front Controller.
     *
     * @param string $urlBase URL base prefijada.
     * @return void
     */
    public static function definirUrlBase(string $urlBase): void
    {
        self::$urlBase = rtrim($urlBase, '/');
    }

    /**
     * Obtiene la URL base configurada.
     *
     * @return string
     */
    public static function obtenerUrlBase(): string
    {
        return self::$urlBase;
    }

    /**
     * Genera la URL pública absoluta para un recurso o asset.
     * Evita el uso de rutas relativas frágiles (ej. ../assets/...).
     *
     * @param string $recurso Ruta relativa dentro de la carpeta assets (ej. "css/style.css").
     * @return string URL absoluta normalizada para el navegador.
     */
    public static function asset(string $recurso): string
    {
        $recursoLimpio = ltrim($recurso, '/');
        $base = self::$urlBase;
        return ($base === '' ? '' : $base) . '/assets/' . $recursoLimpio;
    }

    /**
     * Genera la URL para una ruta interna del PMS.
     *
     * @param string $ruta Ruta interna relativa (ej. "/" o "/panel").
     * @return string URL absoluta normalizada para enlaces de la aplicación.
     */
    public static function ruta(string $ruta = ''): string
    {
        $rutaLimpia = '/' . ltrim($ruta, '/');
        $base = self::$urlBase;
        return ($base === '' ? '' : $base) . ($rutaLimpia === '/' ? '/' : $rutaLimpia);
    }

    /**
     * Escapa caracteres especiales en cadenas para prevenir XSS en vistas HTML.
     *
     * @param mixed $valor Valor a escapar.
     * @return string Cadena saneada en codificación UTF-8.
     */
    public static function escapar(mixed $valor): string
    {
        return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
