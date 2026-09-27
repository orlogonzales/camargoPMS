<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios\Documentos;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Adaptador oficial del motor de generación PDF (Dompdf 3.x) con configuración de seguridad estricta (D-079 #1, #2, #7).
 */
class GeneradorPdf
{
    private string $storagePath;
    private string $publicPath;

    public function __construct(?string $basePath = null)
    {
        $base = $basePath ?? dirname(__DIR__, 3);
        $this->storagePath = rtrim($base, '/\\') . DIRECTORY_SEPARATOR . 'storage';
        $this->publicPath = rtrim($base, '/\\') . DIRECTORY_SEPARATOR . 'public';
    }

    /**
     * Renderiza un HTML pre-compilado y devuelve el binario PDF con su hash criptográfico.
     *
     * @param string $html
     * @param bool $incluirPaginacionCanvas Si debe inyectarse "Página X de Y" al pie
     * @return Options
     */
    public function obtenerOpcionesConfiguradas(): Options
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('isJavascriptEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('dpi', 150);

        // Chroot restringido estrictamente a directorios autorizados
        $chroot = [
            $this->storagePath,
            $this->storagePath . DIRECTORY_SEPARATOR . 'documentos',
            $this->storagePath . DIRECTORY_SEPARATOR . 'membretes',
            $this->publicPath . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'fuentes',
        ];
        $options->set('chroot', $chroot);

        return $options;
    }

    public function obtenerInstanciaDompdf(): Dompdf
    {
        return new Dompdf($this->obtenerOpcionesConfiguradas());
    }

    /**
     * Renderiza un HTML pre-compilado y devuelve el binario PDF con su hash criptográfico.
     *
     * @param string $html
     * @param bool $incluirPaginacionCanvas Si debe inyectarse "Página X de Y" al pie
     * @return array{
     *     binario_pdf: string,
     *     hash_pdf_sha256: string,
     *     tamano_bytes: int,
     *     numero_paginas: int
     * }
     */
    public function renderizar(string $html, bool $incluirPaginacionCanvas = true): array
    {
        $options = $this->obtenerOpcionesConfiguradas();

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        $canvas = $dompdf->getCanvas();
        $numPaginas = $canvas->get_page_count();

        if ($incluirPaginacionCanvas && $numPaginas > 0) {
            // Inyectar "Página {PAGE_NUM} de {PAGE_COUNT}" en la esquina inferior derecha
            $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');
            $size = 8.5;
            $color = [0.45, 0.45, 0.45];
            $canvas->page_text(
                $canvas->get_width() - 110,
                $canvas->get_height() - 24,
                "Página {PAGE_NUM} de {PAGE_COUNT}",
                $font,
                $size,
                $color
            );
        }

        $binarioPdf = $dompdf->output();
        $hashPdfSha256 = hash('sha256', $binarioPdf);
        $tamanoBytes = strlen($binarioPdf);

        return [
            'binario_pdf' => $binarioPdf,
            'hash_pdf_sha256' => $hashPdfSha256,
            'tamano_bytes' => $tamanoBytes,
            'numero_paginas' => $numPaginas,
        ];
    }
}
