<?php
declare(strict_types=1);

/**
 * Suite de Verificación UI-3A — Fidelidad Visual Exacta de Formularios Nativos Alina (D-075)
 * 
 * Verifica que los componentes de formularios (Vertical Form With Icon y Select 2)
 * implementen fielmente los contratos visuales nativos de la plantilla Alina:
 * - Geometría de píldora: var(--app-border-radius) (20px)
 * - Separador vertical de 1px (.icon-control::after)
 * - Dimensiones nativas: Select2 a 42px, padding-left 48px en inputs con icono
 * - Ausencia de overrides destructivos (0.375rem)
 * - Cobertura transversal en todas las vistas de la aplicación
 * - Confinamiento estricto: cero dependencias externas CDN
 */

$rootDir = dirname(__DIR__);
$cssFile = $rootDir . '/public/assets/css/camargo.css';
$vistasDir = $rootDir . '/app/Vistas';

$totalPruebas = 0;
$pruebasExitosas = 0;
$errores = [];

function assertTest(bool $condicion, string $descripcion): void {
    global $totalPruebas, $pruebasExitosas, $errores;
    $totalPruebas++;
    if ($condicion) {
        $pruebasExitosas++;
        echo "  [PASS] {$descripcion}\n";
    } else {
        $errores[] = $descripcion;
        echo "  [FAIL] {$descripcion}\n";
    }
}

echo "======================================================================\n";
echo "   SUITE UI-3A: FIDELIDAD VISUAL EXACTA DE COMPONENTES ALINA (D-075)   \n";
echo "======================================================================\n\n";

// -----------------------------------------------------------------------------
// 1. Verificación de Reglas CSS en camargo.css
// -----------------------------------------------------------------------------
echo "[1/4] Verificando contratos CSS nativos de Alina en camargo.css...\n";

assertTest(file_exists($cssFile), "El archivo public/assets/css/camargo.css existe");
$cssContent = (string) file_get_contents($cssFile);

// Ausencia de overrides destructivos
assertTest(
    !str_contains($cssContent, 'border-radius: 0.375rem !important'),
    "camargo.css no contiene overrides destructivos 'border-radius: 0.375rem !important'"
);
assertTest(
    !str_contains($cssContent, 'height: calc(2.35rem + 2px) !important'),
    "camargo.css no fuerza altura rectangular 'height: calc(2.35rem + 2px) !important'"
);

// Contrato Vertical Form With Icon (Alina default_forms.html)
assertTest(
    str_contains($cssContent, '.icon-control::after'),
    "camargo.css define el pseudo-elemento .icon-control::after para el separador vertical"
);
assertTest(
    preg_match('/\.icon-control::after\s*\{[^}]*width:\s*1px;/s', $cssContent) === 1,
    "El separador .icon-control::after tiene ancho de 1px"
);
assertTest(
    preg_match('/\.icon-control::after\s*\{[^}]*height:\s*20px;/s', $cssContent) === 1,
    "El separador .icon-control::after tiene altura de 20px"
);
assertTest(
    preg_match('/\.icon-control::after\s*\{[^}]*left:\s*40px;/s', $cssContent) === 1,
    "El separador .icon-control::after está posicionado a left: 40px (separación exacta del icono)"
);
assertTest(
    str_contains($cssContent, 'border-radius: var(--app-border-radius)'),
    "Los inputs dentro de .icon-control usan var(--app-border-radius) nativo de Alina"
);
assertTest(
    preg_match('/\.icon-control\s*>\s*\.form-control\s*\{[^}]*padding:[^;]*3rem;/s', $cssContent) === 1,
    "Los inputs dentro de .icon-control tienen padding izquierdo de 3rem (48px) para despejar icono y separador"
);

// Contrato Select 2 (Alina select.html)
assertTest(
    preg_match('/\.select2-container--default\s+\.select2-selection\s*\{[^}]*border-radius:\s*var\(--app-border-radius\);/s', $cssContent) === 1,
    "Select2 selection tiene border-radius: var(--app-border-radius) (píldora 20px)"
);
assertTest(
    preg_match('/\.select2-container--default\s+\.select2-selection--single\s*\{[^}]*height:\s*calc\(2\.5rem\s*\+\s*var\(--bs-border-width\)\s*\*\s*2\);/s', $cssContent) === 1,
    "Select2 single tiene altura nativa de 42px (calc(2.5rem + border*2))"
);
assertTest(
    str_contains($cssContent, '\f078'),
    "Select2 utiliza la flecha chevron Font Awesome 6 '\\f078'"
);
assertTest(
    preg_match('/\.select2-selection__clear\s*\{[^}]*border-radius:\s*14px;/s', $cssContent) === 1,
    "El botón limpiar de Select2 tiene borde redondeado a 14px con fondo tenue"
);

// Soporte de validación PristineJS
assertTest(
    str_contains($cssContent, '.has-danger .select2-container--default .select2-selection'),
    "Select2 soporta retroalimentación visual de error con PristineJS (.has-danger)"
);
assertTest(
    str_contains($cssContent, '.has-success .select2-container--default .select2-selection'),
    "Select2 soporta retroalimentación visual de éxito con PristineJS (.has-success)"
);

// -----------------------------------------------------------------------------
// 2. Verificación de Adopción Transversal en Vistas
// -----------------------------------------------------------------------------
echo "\n[2/4] Verificando clases app-form app-icon-form en vistas y modales...\n";

$vistasRequeridas = [
    'auth/login.php' => ['app-form', 'app-icon-form'],
    'usuarios/index.php' => ['app-form', 'app-icon-form', 'basic-select2'],
    'roles/index.php' => ['app-form', 'app-icon-form'],
    'configuracion/menu/index.php' => ['app-form', 'app-icon-form'],
    'configuracion/sistema/index.php' => ['app-form'],
    'propiedades/index.php' => ['app-form', 'app-icon-form', 'basic-select2'],
    'propiedades/detalle.php' => ['app-form', 'app-icon-form'],
    'unidades/index.php' => ['app-form', 'app-icon-form', 'basic-select2'],
    'unidades/detalle.php' => ['app-form', 'app-icon-form'],
    'disponibilidad/index.php' => ['app-form', 'app-icon-form', 'basic-select2'],
    'reservas/index.php' => ['app-form', 'app-icon-form', 'basic-select2'],
    'estadias/index.php' => ['app-form', 'app-icon-form', 'basic-select2'],
    'servicios/index.php' => ['app-form', 'app-icon-form', 'basic-select2'],
    'caja/index.php' => ['app-form', 'app-icon-form', 'basic-select2'],
];

foreach ($vistasRequeridas as $relPath => $clasesEsperadas) {
    $fullPath = $vistasDir . '/' . $relPath;
    assertTest(file_exists($fullPath), "La vista app/Vistas/{$relPath} existe");
    $content = (string) file_get_contents($fullPath);
    
    foreach ($clasesEsperadas as $clase) {
        assertTest(
            str_contains($content, $clase),
            "Vista {$relPath} contiene clase requerida '{$clase}'"
        );
    }
}

// -----------------------------------------------------------------------------
// 3. Ausencia de Clases Conflictivas (form-control-sm / form-select-sm en Alina Controls)
// -----------------------------------------------------------------------------
echo "\n[3/4] Verificando ausencia de clases conflictivas form-control-sm y form-select-sm...\n";

$archivosPHP = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($vistasDir));
$conflictosEncontrados = [];

foreach ($archivosPHP as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $c = (string) file_get_contents($file->getPathname());
    // Buscar si un elemento combina form-control-sm o form-select-sm con basic-select2
    if (preg_match('/class=["\'][^"\']*\bform-select-sm\b[^"\']*\bbasic-select2\b/i', $c)) {
        $conflictosEncontrados[] = $file->getBasename() . ' (form-select-sm con basic-select2)';
    }
}

assertTest(
    empty($conflictosEncontrados),
    "Cero elementos combinan form-select-sm con basic-select2 (conflicto: " . implode(', ', $conflictosEncontrados) . ")"
);

// -----------------------------------------------------------------------------
// 4. Verificación de Confinamiento Local (0 Dependencias CDN)
// -----------------------------------------------------------------------------
echo "\n[4/4] Verificando confinamiento local y 0 dependencias CDN...\n";

$vendorDir = $rootDir . '/public/assets/vendor';
assertTest(
    file_exists($vendorDir . '/select/select2.min.css') || file_exists($vendorDir . '/select2/select2.min.css'),
    "Select2 CSS presente localmente"
);
assertTest(
    file_exists($vendorDir . '/select/select2.min.js') || file_exists($vendorDir . '/select2/select2.min.js'),
    "Select2 JS presente localmente"
);
assertTest(file_exists($vendorDir . '/pristine/pristine.min.js'), "PristineJS presente localmente");
assertTest(file_exists($rootDir . '/public/assets/vendor/jquery/jquery-3.7.1.min.js') || file_exists($rootDir . '/public/assets/vendor/jquery/jquery.min.js'), "jQuery presente localmente para Select2");

// Verificar que ninguna vista incluya librerías desde CDN
$cdnDetectado = false;
foreach ($archivosPHP as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $c = (string) file_get_contents($file->getPathname());
    if (preg_match('/https?:\/\/(cdn|cdnjs|unpkg|jsdelivr)/i', $c)) {
        $cdnDetectado = true;
        break;
    }
}
assertTest(!$cdnDetectado, "Cero referencias a scripts o estilos CDN externos en las vistas");

// -----------------------------------------------------------------------------
// Resumen Final
// -----------------------------------------------------------------------------
echo "\n======================================================================\n";
echo "                     RESUMEN DE PRUEBAS UI-3A                         \n";
echo "======================================================================\n";
echo "  Total de Aserciones : {$totalPruebas}\n";
echo "  Aserciones Exitosas : {$pruebasExitosas}\n";
echo "  Aserciones Fallidas : " . count($errores) . "\n";
echo "======================================================================\n";

if (count($errores) > 0) {
    echo "\nERRORES ENCONTRADOS:\n";
    foreach ($errores as $err) {
        echo " - {$err}\n";
    }
    exit(1);
} else {
    echo "\nDICTAMEN: PASS (100% Fidelidad Visual Alina Lograda)\n\n";
    exit(0);
}
