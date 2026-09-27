<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios\Documentos;

use CamargoPMS\Excepciones\ContenidoDocumentalInseguroExcepcion;

/**
 * Validador estricto de seguridad para contenido HTML y CSS documental (D-079 #3).
 *
 * Rechaza activamente scripts, iframes, objetos, URLs remotas o esquemas no autorizados
 * para garantizar que el renderizador Dompdf opere en un entorno cerrado y seguro.
 */
class ValidadorHtmlDocumental
{
    /**
     * Patrones de etiquetas o construcciones HTML estrictamente prohibidas.
     */
    private const PATRONES_PROHIBIDOS = [
        '/<script\b[^>]*>/i'        => '<script>',
        '/<\/script>/i'             => '</script>',
        '/<iframe\b[^>]*>/i'        => '<iframe>',
        '/<object\b[^>]*>/i'        => '<object>',
        '/<embed\b[^>]*>/i'         => '<embed>',
        '/<applet\b[^>]*>/i'        => '<applet>',
        '/<meta\b[^>]*>/i'          => '<meta>',
        '/<link\b[^>]*>/i'          => '<link>',
        '/<form\b[^>]*>/i'          => '<form>',
        '/<\?php/i'                 => '<?php',
        '/<\?=/i'                   => '<?=',
        '/\bon\w+\s*=/i'            => 'event handler (ej: onload, onclick, onerror)',
        '/javascript\s*:/i'         => 'javascript: URI',
        '/vbscript\s*:/i'           => 'vbscript: URI',
        '/data\s*:\s*text\/html/i'  => 'data:text/html URI',
        '/https?:\/\//i'            => 'URL remota http:// o https://',
        '/file:\/\//i'              => 'esquema arbitrario file://',
    ];

    /**
     * Valida el contenido HTML de una plantilla o versión.
     *
     * @throws ContenidoDocumentalInseguroExcepcion si se detecta código o etiquetas prohibidas.
     */
    public function validar(string $html): void
    {
        foreach (self::PATRONES_PROHIBIDOS as $patron => $descripcion) {
            if (preg_match($patron, $html)) {
                throw new ContenidoDocumentalInseguroExcepcion($descripcion);
            }
        }
    }

    /**
     * Valida reglas CSS para evitar inyección de scripts o URLs remotas.
     */
    public function validarCss(?string $css): void
    {
        if ($css === null || trim($css) === '') {
            return;
        }

        if (preg_match('/https?:\/\//i', $css)) {
            throw new ContenidoDocumentalInseguroExcepcion('URL remota http:// o https:// en CSS');
        }

        if (preg_match('/javascript\s*:/i', $css)) {
            throw new ContenidoDocumentalInseguroExcepcion('javascript: URI en CSS');
        }

        if (preg_match('/expression\s*\(/i', $css)) {
            throw new ContenidoDocumentalInseguroExcepcion('CSS expression()');
        }
    }
}
