<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios\Documentos;

use CamargoPMS\Modelos\DocumentoPlantilla;
use CamargoPMS\Modelos\DocumentoPlantillaVersion;

/**
 * Compilador de documentos y generador de snapshots inmutables (D-079 #4, #6, #18).
 *
 * Combina la plantilla versionada con los datos resueltos, inyecta los estilos
 * print-oriented de Dompdf y produce los snapshots deterministas antes de la renderización.
 */
class CompiladorDocumental
{
    private ValidadorHtmlDocumental $validadorHtml;
    private RegistroVariablesDocumentales $registroVariables;

    public function __construct(
        ?ValidadorHtmlDocumental $validadorHtml = null,
        ?RegistroVariablesDocumentales $registroVariables = null
    ) {
        $this->validadorHtml = $validadorHtml ?? new ValidadorHtmlDocumental();
        $this->registroVariables = $registroVariables ?? new RegistroVariablesDocumentales();
    }

    /**
     * Compila la versión de la plantilla con los datos del contrato y genera el snapshot inmutable.
     *
     * @param DocumentoPlantilla $plantilla
     * @param DocumentoPlantillaVersion $version
     * @param array<string, mixed> $datosCrudos
     * @param string|null $marcaAgua Texto de marca de agua opcional (ej: "BORRADOR - SIN VALIDEZ LEGAL")
     * @param string|null $rutaMembreteAbsoluta Ruta absoluta al membrete para inyectar en CSS @page
     * @return array{
     *     snapshot_html: string,
     *     snapshot_datos_json: array<string, string>,
     *     hash_snapshot_sha256: string
     * }
     */
    public function compilar(
        DocumentoPlantilla $plantilla,
        DocumentoPlantillaVersion $version,
        array $datosCrudos,
        ?string $marcaAgua = null,
        ?string $rutaMembreteAbsoluta = null
    ): array {
        // 1. Validar seguridad del HTML y CSS de la plantilla
        $this->validadorHtml->validar($version->obtenerCuerpoHtml());
        $this->validadorHtml->validarCss($version->obtenerEstilosCss());

        // 2. Validar que no existan shortcodes no registrados en la plantilla
        $this->registroVariables->validarShortcodesEnHtml(
            $version->obtenerCuerpoHtml(),
            $plantilla->obtenerOrigenTipoPermitido(),
            $plantilla->obtenerCodigo()
        );

        // 3. Resolver y formatear variables requeridas
        $datosResueltos = $this->registroVariables->resolverVariables(
            $plantilla->obtenerOrigenTipoPermitido(),
            $datosCrudos
        );

        // Ordenar datos determinísticamente por clave para repetibilidad de snapshot
        ksort($datosResueltos);

        // 4. Reemplazar shortcodes {{variable}}
        $cuerpoCompilado = $version->obtenerCuerpoHtml();
        foreach ($datosResueltos as $var => $val) {
            $cuerpoCompilado = str_replace("{{{$var}}}", $val, $cuerpoCompilado);
        }

        // 5. Construir maquetación CSS A4 completa para Dompdf
        $cssBase = $this->construirCssPagina($plantilla, $version, $rutaMembreteAbsoluta);
        $marcaAguaHtml = $this->construirMarcaAguaHtml($marcaAgua);

        $htmlCompleto = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{$version->obtenerTituloDocumento()}</title>
    <style>
{$cssBase}
    </style>
</head>
<body>
{$marcaAguaHtml}
{$cuerpoCompilado}
</body>
</html>
HTML;

        $hashSnapshot = hash('sha256', $htmlCompleto);

        return [
            'snapshot_html' => $htmlCompleto,
            'snapshot_datos_json' => $datosResueltos,
            'hash_snapshot_sha256' => $hashSnapshot,
        ];
    }

    /**
     * Construye las reglas CSS de maquetación A4 específicas para Dompdf (D-079 #17, #18).
     */
    private function construirCssPagina(
        DocumentoPlantilla $plantilla,
        DocumentoPlantillaVersion $version,
        ?string $rutaMembreteAbsoluta = null
    ): string {
        $mSup = $plantilla->obtenerMargenSuperiorMm();
        $mInf = $plantilla->obtenerMargenInferiorMm();
        $mIzq = $plantilla->obtenerMargenIzquierdoMm();
        $mDer = $plantilla->obtenerMargenDerechoMm();
        $size = strtolower($plantilla->obtenerTamanoPapel()) . ' ' . strtolower($plantilla->obtenerOrientacion());

        $bgCss = '';
        if ($plantilla->requiereMembrete() && $rutaMembreteAbsoluta && file_exists($rutaMembreteAbsoluta)) {
            // En Dompdf, las imágenes de fondo en @page deben ser rutas locales absolutas
            $rutaNormalizada = str_replace('\\', '/', $rutaMembreteAbsoluta);
            $bgCss = "background-image: url('{$rutaNormalizada}'); background-repeat: no-repeat; background-position: top left; background-size: cover;";
        }

        $cssPropio = $version->obtenerEstilosCss() ?? '';

        return <<<CSS
@page {
    size: {$size};
    margin-top: {$mSup}mm;
    margin-bottom: {$mInf}mm;
    margin-left: {$mIzq}mm;
    margin-right: {$mDer}mm;
    {$bgCss}
}

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 0;
    font-family: "DejaVu Sans", "Helvetica Neue", Arial, sans-serif;
    font-size: 9.5pt;
    line-height: 1.45;
    color: #1a1a1a;
}

table {
    border-collapse: collapse;
}

.marca-agua {
    position: fixed;
    top: 35%;
    left: 10%;
    width: 80%;
    text-align: center;
    font-size: 38pt;
    font-weight: bold;
    color: rgba(220, 53, 69, 0.18);
    transform: rotate(-35deg);
    z-index: -1000;
    text-transform: uppercase;
    letter-spacing: 4px;
}

{$cssPropio}
CSS;
    }

    private function construirMarcaAguaHtml(?string $marcaAgua): string
    {
        if ($marcaAgua === null || trim($marcaAgua) === '') {
            return '';
        }
        $seguro = htmlspecialchars(trim($marcaAgua), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return "<div class=\"marca-agua\">{$seguro}</div>";
    }
}
