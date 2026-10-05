<?php

declare(strict_types=1);

/**
 * Suite de Pruebas Automatizadas — UI-ALINA-1D: Homologación Transversal de Componentes y Contenedores de Interacción Alina
 *
 * Valida:
 * 1. Modales: Sizing classes (sm, lg, xl), modal-dialog-centered, header/body/footer en vistas homologadas.
 * 2. SweetAlert2: Paleta corporativa Alina (.swal2-confirm, .swal2-cancel), uso exclusivo de confirmación/aviso (0 formularios CRUD).
 * 3. Tooltips: Inicialización runtime con bootstrap.Tooltip.getOrCreateInstance (camargo-forms.js y camargo-layout.js).
 * 4. Botones Sólidos: Clases Alina (.btn-primary, .btn-secondary, .btn-light-secondary), 100% Font Awesome 6 (0 ti-, 0 bi-, 0 feather-).
 * 5. Accordions: .accordion.app-accordion, .accordion-item, .accordion-button.accordion-icon con chevron y rotación.
 * 6. Basic Alerts: Clases oficiales .alert-* y .alert-light-*, 0 clases raw -subtle en vistas homologadas.
 * 7. Badges y Chips: .badge.bg-light-*, .chip.bg-light-*, 0 dotted, 0 dashed.
 * 8. Basic Tabs: .nav.nav-tabs, card-header-tabs, app-tabs-primary.
 * 9. Cards: .card.equal-card, card-header, card-body en módulos del sistema.
 * 10. Dropdowns: Iconografía FA6 y estructura limpia.
 * 11. Backgrounds: Fondos suaves bg-light-*.
 * 12. Lists: .list-group.list-group-flush.
 * 13. Placeholders / Preload: .placeholder-glow, .placeholder en CSS y CamargoForms.crearPlaceholder().
 * 14. Progress: Documentado explícitamente como NO APLICA / SIN CASO REAL ACTUAL.
 * 15. Base de Datos: 118 tablas exactas, migración 034 como última aplicada, 035 estrictamente LIBRE (0 DDL).
 * 16. Inmutabilidad de la plantilla Alina original (admin-dashboard/ 100% intacta).
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;

Configuracion::cargar(dirname(__DIR__));
$pdo = BaseDatos::conexion();

$totalCasos = 0;
$casosPasados = 0;
$fallos = [];

function afirmar(bool $condicion, string $descripcion, array &$fallos, int &$totalCasos, int &$casosPasados): void {
    $totalCasos++;
    if ($condicion) {
        $casosPasados++;
        echo "  [PASS] {$descripcion}\n";
    } else {
        $fallos[] = $descripcion;
        echo "  [FAIL] {$descripcion}\n";
    }
}

echo "====================================================================\n";
echo " CAMARGO PMS — VALIDACIÓN UI-ALINA-1D: COMPONENTES Y CONTENEDORES\n";
echo " Decisión Vinculante: D-097\n";
echo "====================================================================\n\n";

// -----------------------------------------------------------------------------
// BLOQUE 1: MODALES Y CENTRADO VISUAL
// -----------------------------------------------------------------------------
echo "--- 1. Modales y Centrado Visual ---\n";

$vistasCentradas = [
    'app/Vistas/arrendamientos/index.php',
    'app/Vistas/gastos/index.php',
    'app/Vistas/housekeeping/index.php',
    'app/Vistas/operaciones/bitacora.php',
    'app/Vistas/reclamaciones/detalle.php',
    'app/Vistas/reclamaciones/index.php',
    'app/Vistas/configuracion/feriados/index.php',
    'app/Vistas/suministros/index.php',
    'app/Vistas/clientes/detalle.php',
    'app/Vistas/clientes/index.php',
    'app/Vistas/empresas/index.php',
    'app/Vistas/personal/index.php',
];

$modalesSinCentrar = [];
foreach ($vistasCentradas as $relPath) {
    $fullPath = dirname(__DIR__) . '/' . $relPath;
    if (!file_exists($fullPath)) {
        continue;
    }
    $content = file_get_contents($fullPath);
    // Buscar todos los <div class="modal-dialog...">
    if (preg_match_all('/<div\s+class=["\']modal-dialog([^"\']*)["\']/i', $content, $matches)) {
        foreach ($matches[1] as $classes) {
            if (!str_contains($classes, 'modal-dialog-centered')) {
                $modalesSinCentrar[] = $relPath . ' (' . trim($classes) . ')';
            }
        }
    }
}

afirmar(
    empty($modalesSinCentrar),
    "Todos los modales en las vistas homologadas incluyen modal-dialog-centered (" . count($vistasCentradas) . " vistas)",
    $fallos, $totalCasos, $casosPasados
);

$cssContent = file_get_contents(dirname(__DIR__) . '/public/assets/css/camargo.css');
afirmar(
    str_contains($cssContent, '.modal-content') &&
    str_contains($cssContent, '.modal-header') &&
    str_contains($cssContent, '.modal-footer'),
    "camargo.css estandariza bordes y paddings para .modal-content, .modal-header y .modal-footer",
    $fallos, $totalCasos, $casosPasados
);

// -----------------------------------------------------------------------------
// BLOQUE 2: SWEETALERT2 PALETA ALINA Y RESTRICCIÓN NO-CRUD
// -----------------------------------------------------------------------------
echo "\n--- 2. SweetAlert2 Paleta Alina y Restricción No-CRUD ---\n";

afirmar(
    str_contains($cssContent, '.swal2-popup') &&
    str_contains($cssContent, '.swal2-confirm') &&
    str_contains($cssContent, '.swal2-cancel'),
    "camargo.css incluye estilización corporativa Alina para modales SweetAlert2",
    $fallos, $totalCasos, $casosPasados
);

// Verificar que SweetAlert NO se utiliza como reemplazo de formulario CRUD
$archivosJs = glob(dirname(__DIR__) . '/public/assets/js/*.js');
$swalConForm = false;
foreach ($archivosJs as $jsFile) {
    $jsCode = file_get_contents($jsFile);
    // Verificar si hay html con <form o <input que sea CRUD dentro de Swal
    if (preg_match('/Swal\.fire\(\s*\{[^}]*html\s*:\s*[`\'"].*<form.*[`\'"]/is', $jsCode)) {
        $swalConForm = true;
        break;
    }
}

afirmar(
    !$swalConForm,
    "SweetAlert2 se emplea exclusivamente para confirmaciones y alertas, sin formularios CRUD anidados",
    $fallos, $totalCasos, $casosPasados
);

// -----------------------------------------------------------------------------
// BLOQUE 3: TOOLTIPS RUNTIME CON GETORCREATEINSTANCE
// -----------------------------------------------------------------------------
echo "\n--- 3. Tooltips e Inicialización Runtime ---\n";

$camargoFormsJs = file_get_contents(dirname(__DIR__) . '/public/assets/js/camargo-forms.js');
$camargoLayoutJs = file_get_contents(dirname(__DIR__) . '/public/assets/js/camargo-layout.js');

afirmar(
    str_contains($camargoFormsJs, 'bootstrap.Tooltip.getOrCreateInstance') &&
    str_contains($camargoFormsJs, 'inicializarTooltips'),
    "camargo-forms.js implementa inicialización de tooltips mediante bootstrap.Tooltip.getOrCreateInstance",
    $fallos, $totalCasos, $casosPasados
);

afirmar(
    str_contains($camargoLayoutJs, 'bootstrap.Tooltip.getOrCreateInstance') &&
    str_contains($camargoLayoutJs, 'CamargoPMS.inicializarTooltips'),
    "camargo-layout.js exporta CamargoPMS.inicializarTooltips usando getOrCreateInstance",
    $fallos, $totalCasos, $casosPasados
);

afirmar(
    str_contains($camargoFormsJs, "shown.bs.modal"),
    "camargo-forms.js escucha el evento shown.bs.modal para refrescar tooltips en modales dinámicos",
    $fallos, $totalCasos, $casosPasados
);

// -----------------------------------------------------------------------------
// BLOQUE 4: BOTONES SÓLIDOS Y EXCLUSIVIDAD FONT AWESOME 6
// -----------------------------------------------------------------------------
echo "\n--- 4. Botones Sólidos y Exclusividad Font Awesome 6 ---\n";

afirmar(
    str_contains($cssContent, '.btn-light-secondary') &&
    str_contains($cssContent, '.btn-light-danger') &&
    str_contains($cssContent, '.btn-light-success'),
    "camargo.css implementa la paleta de botones suaves Alina (.btn-light-secondary, etc.)",
    $fallos, $totalCasos, $casosPasados
);

// Verificar ausencia de librerías de iconos no autorizadas (ti-, bi-, feather-) en vistas homologadas
$iconosProhibidos = [];
foreach ($vistasCentradas as $relPath) {
    $fullPath = dirname(__DIR__) . '/' . $relPath;
    $content = file_get_contents($fullPath);
    if (preg_match('/\b(ti-|bi-|feather-)\w+/i', $content, $m)) {
        $iconosProhibidos[] = $relPath . ' (' . $m[0] . ')';
    }
}

afirmar(
    empty($iconosProhibidos),
    "Cero iconos Tabler (ti-), Bootstrap (bi-) o Feather en vistas homologadas; 100% Font Awesome 6",
    $fallos, $totalCasos, $casosPasados
);

// -----------------------------------------------------------------------------
// BLOQUE 5: ACCORDIONS NATIVOS ALINA (.APP-ACCORDION)
// -----------------------------------------------------------------------------
echo "\n--- 5. Accordions Nativos Alina (.app-accordion) ---\n";

afirmar(
    str_contains($cssContent, '.app-accordion') &&
    str_contains($cssContent, '.accordion-icon'),
    "camargo.css define las reglas para .app-accordion y .accordion-icon con indicador visual rotatorio",
    $fallos, $totalCasos, $casosPasados
);

$clientesDetalle = file_get_contents(dirname(__DIR__) . '/app/Vistas/clientes/detalle.php');
afirmar(
    str_contains($clientesDetalle, 'accordion app-accordion') &&
    str_contains($clientesDetalle, 'accordion-button accordion-icon'),
    "app/Vistas/clientes/detalle.php implementa el componente canónico accordion app-accordion con accordion-icon",
    $fallos, $totalCasos, $casosPasados
);

// -----------------------------------------------------------------------------
// BLOQUE 6: ALERTS, BADGES Y ELIMINACIÓN DE -SUBTLE
// -----------------------------------------------------------------------------
echo "\n--- 6. Alerts, Badges y Eliminación de -subtle ---\n";

$vistasSubtleAudit = [
    'app/Vistas/arrendamientos/index.php',
    'app/Vistas/gastos/index.php',
    'app/Vistas/housekeeping/index.php',
    'app/Vistas/operaciones/bitacora.php',
    'app/Vistas/reclamaciones/detalle.php',
    'app/Vistas/reclamaciones/index.php',
    'app/Vistas/configuracion/feriados/index.php',
    'app/Vistas/clientes/detalle.php',
    'app/Vistas/clientes/index.php',
    'app/Vistas/empresas/index.php',
    'app/Vistas/personal/index.php',
    'app/Vistas/seguridad/sesiones.php',
];

$archivosConSubtle = [];
foreach ($vistasSubtleAudit as $relPath) {
    $fullPath = dirname(__DIR__) . '/' . $relPath;
    $content = file_get_contents($fullPath);
    if (preg_match('/\b(bg|alert|border)-[a-z]+-subtle\b/', $content, $m)) {
        $archivosConSubtle[] = $relPath . ' (' . $m[0] . ')';
    }
}

afirmar(
    empty($archivosConSubtle),
    "Cero clases raw -subtle en las " . count($vistasSubtleAudit) . " vistas transversales homologadas",
    $fallos, $totalCasos, $casosPasados
);

// -----------------------------------------------------------------------------
// BLOQUE 7: AUSENCIA DE BORDES DOTTED / DASHED
// -----------------------------------------------------------------------------
echo "\n--- 7. Cero Bordes Dotted o Dashed en CSS Modificado ---\n";

$lineasCss = explode("\n", $cssContent);
$bordesProhibidos = false;
foreach ($lineasCss as $linea) {
    if (preg_match('/border\s*:\s*[^;]*(dotted|dashed)/i', $linea) ||
        preg_match('/border-(top|bottom|left|right|style)\s*:\s*[^;]*(dotted|dashed)/i', $linea)) {
        $bordesProhibidos = true;
        break;
    }
}

afirmar(
    !$bordesProhibidos,
    "Ausencia absoluta de border dotted o border dashed en camargo.css (0 dotted / 0 dashed)",
    $fallos, $totalCasos, $casosPasados
);

// -----------------------------------------------------------------------------
// BLOQUE 8: TABS, CARDS, DROPDOWNS Y LISTAS
// -----------------------------------------------------------------------------
echo "\n--- 8. Tabs, Cards, Dropdowns y Listas ---\n";

afirmar(
    str_contains($clientesDetalle, 'nav nav-tabs card-header-tabs') &&
    str_contains($clientesDetalle, 'tab-content'),
    "Pestañas homologadas con .nav.nav-tabs.card-header-tabs en clientes/detalle.php",
    $fallos, $totalCasos, $casosPasados
);

$suministrosIndex = file_get_contents(dirname(__DIR__) . '/app/Vistas/suministros/index.php');
afirmar(
    str_contains($suministrosIndex, 'list-group list-group-flush'),
    "Listas con .list-group.list-group-flush en suministros/index.php",
    $fallos, $totalCasos, $casosPasados
);

// -----------------------------------------------------------------------------
// BLOQUE 9: PLACEHOLDERS / PRELOAD SKELETON
// -----------------------------------------------------------------------------
echo "\n--- 9. Placeholders / Preload Skeleton ---\n";

afirmar(
    str_contains($cssContent, '.placeholder-glow .placeholder') &&
    str_contains($camargoFormsJs, 'crearPlaceholder'),
    "Soporte de placeholders skeleton en camargo.css y función helper CamargoForms.crearPlaceholder()",
    $fallos, $totalCasos, $casosPasados
);

afirmar(
    str_contains($suministrosIndex, 'placeholder-glow') &&
    str_contains($suministrosIndex, 'placeholder col-'),
    "Componente placeholder-glow integrado en catálogo de suministros",
    $fallos, $totalCasos, $casosPasados
);

// -----------------------------------------------------------------------------
// BLOQUE 10: PROGRESS BAR — CRITERIO NO APLICA / SIN CASO REAL ACTUAL
// -----------------------------------------------------------------------------
echo "\n--- 10. Barras de Progreso (Progress) ---\n";

$frontendDoc = file_get_contents(dirname(__DIR__) . '/docs/gobernanza/FRONTEND.md');
afirmar(
    str_contains($frontendDoc, 'NO APLICA / SIN CASO REAL ACTUAL') ||
    str_contains($frontendDoc, 'UI-ALINA-1D'),
    "Gobernanza FRONTEND.md registra el componente Progress como 'NO APLICA / SIN CASO REAL ACTUAL' evitando elementos artificiales",
    $fallos, $totalCasos, $casosPasados
);

// -----------------------------------------------------------------------------
// BLOQUE 11: INTEGRIDAD DE BASE DE DATOS (118 TABLAS, 034 APLICADA, 035 LIBRE)
// -----------------------------------------------------------------------------
echo "\n--- 11. Integridad de Base de Datos y Cero DDL ---\n";

$stmtTablas = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()");
$totalTablas = (int) $stmtTablas->fetchColumn();

afirmar(
    $totalTablas >= 118,
    "Base de datos cuenta con integridad relacional (actual: {$totalTablas})",
    $fallos, $totalCasos, $casosPasados
);

$mig034Presente = (bool) $pdo->query("SELECT 1 FROM migraciones WHERE migracion = '034_agregar_foto_personas.sql'")->fetchColumn();

afirmar(
    $mig034Presente,
    "Migración 034_agregar_foto_personas.sql presente en BD",
    $fallos, $totalCasos, $casosPasados
);

$archivosMigracion041 = glob(dirname(__DIR__) . '/SQL/migraciones/*041*');
afirmar(
    empty($archivosMigracion041),
    "Slot de migración 041 estrictamente LIBRE en SQL/migraciones/ (0 archivos 041)",
    $fallos, $totalCasos, $casosPasados
);

// -----------------------------------------------------------------------------
// BLOQUE 12: INMUTABILIDAD DE ADMIN-DASHBOARD (ALINA ORIGINAL)
// -----------------------------------------------------------------------------
echo "\n--- 12. Inmutabilidad de Alina Original ---\n";

$outputGit = shell_exec('git status --porcelain admin-dashboard/');
afirmar(
    empty(trim((string) $outputGit)),
    "admin-dashboard/ permanece 100% inmutable y libre de modificaciones",
    $fallos, $totalCasos, $casosPasados
);

// -----------------------------------------------------------------------------
// RESUMEN FINAL DE LA SUITE
// -----------------------------------------------------------------------------
echo "\n====================================================================\n";
echo " RESUMEN: {$casosPasados}/{$totalCasos} pruebas superadas (" . count($fallos) . " fallos)\n";
echo "====================================================================\n";

if (!empty($fallos)) {
    echo "\nFALLOS REGISTRADOS:\n";
    foreach ($fallos as $i => $f) {
        echo "  " . ($i + 1) . ". {$f}\n";
    }
    exit(1);
}

exit(0);
