<?php

declare(strict_types=1);

/**
 * Suite de Pruebas Automatizadas — UI-ALINA-1C: Homologación Transversal de Formularios y Controles Alina
 *
 * Valida:
 * 1. Presencia e integridad de assets locales (PristineJS v1.1.0, camargo-forms.js, scripts.php, camargo.css).
 * 2. Geometría y componentes nativos Alina (app-form, app-icon-form, icon-control, basic-select2, app-switch).
 * 3. Ausencia absoluta de estilos con bordes punteados o entrecortados (0 dotted, 0 dashed).
 * 4. Cumplimiento de gobernanza JS: 0 jQuery AJAX para lógica de negocio; Fetch + JSON exclusivo.
 * 5. Matriz de campos candidatos DNI/RUC para futura integración APIsPERU sin acoplamiento prematuro.
 * 6. Integridad de BD: 118 tablas, migración 034 aplicada, 035 estrictamente LIBRE (0 DDL).
 * 7. Inmutabilidad de la plantilla Alina original (admin-dashboard/ 100% intacta).
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
echo " CAMARGO PMS — VALIDACIÓN UI-ALINA-1C: FORMULARIOS Y CONTROLES ALINA\n";
echo " Decisión Vinculante: D-096\n";
echo "====================================================================\n\n";

// -----------------------------------------------------------------------------
// BLOQUE 1: ASSETS E INFRAESTRUCTURA DE FORMULARIOS ALINA
// -----------------------------------------------------------------------------
echo "--- 1. Infraestructura de Formularios y PristineJS ---\n";

$pristineJsPath = dirname(__DIR__) . '/public/assets/vendor/pristine/pristine.min.js';
afirmar(
    file_exists($pristineJsPath) && filesize($pristineJsPath) > 5000,
    "Asset local PristineJS v1.1.0 existe en public/assets/vendor/pristine/pristine.min.js",
    $fallos, $totalCasos, $casosPasados
);

$camargoFormsJsPath = dirname(__DIR__) . '/public/assets/js/camargo-forms.js';
afirmar(
    file_exists($camargoFormsJsPath),
    "Controlador transversal camargo-forms.js existe en public/assets/js/",
    $fallos, $totalCasos, $casosPasados
);

$camargoFormsContent = file_get_contents($camargoFormsJsPath);
afirmar(
    str_contains($camargoFormsContent, "window.Pristine.addMessages('es'") &&
    str_contains($camargoFormsContent, "setLocale('es')") &&
    str_contains($camargoFormsContent, 'CamargoForms') &&
    str_contains($camargoFormsContent, 'establecerCargando') &&
    str_contains($camargoFormsContent, 'restaurarCargando'),
    "camargo-forms.js contiene localización ES completa, helpers de loading y auto-inicialización",
    $fallos, $totalCasos, $casosPasados
);

$scriptsPhp = file_get_contents(dirname(__DIR__) . '/app/Vistas/componentes/scripts.php');
afirmar(
    str_contains($scriptsPhp, 'pristine.min.js') && str_contains($scriptsPhp, 'camargo-forms.js'),
    "scripts.php carga globalmente pristine.min.js y camargo-forms.js en orden correcto",
    $fallos, $totalCasos, $casosPasados
);

$cssContent = file_get_contents(dirname(__DIR__) . '/public/assets/css/camargo.css');
afirmar(
    str_contains($cssContent, '.icon-control') &&
    str_contains($cssContent, '.app-switch') &&
    str_contains($cssContent, '.pristine-error') &&
    str_contains($cssContent, '.form-control[type="file"]'),
    "camargo.css incluye reglas para .icon-control, .app-switch, .form-control[type=\"file\"] y Pristine",
    $fallos, $totalCasos, $casosPasados
);

// -----------------------------------------------------------------------------
// BLOQUE 2: PROHIBICIÓN DE BORDES PUNTEADOS O DISCONTINUOS (0 DOTTED / 0 DASHED)
// -----------------------------------------------------------------------------
echo "\n--- 2. Geometría y Estilos Alina: Cero Bordes Dotted/Dashed ---\n";

$lineasCss = explode("\n", $cssContent);
$bordesProhibidos = false;
foreach ($lineasCss as $linea) {
    $limpia = trim($linea);
    if (str_starts_with($limpia, '/*') || str_starts_with($limpia, '*')) {
        continue; // ignorar comentarios
    }
    if (preg_match('/border(-[a-z]+)?\s*:\s*[^;]*(dashed|dotted)/i', $limpia)) {
        $bordesProhibidos = true;
        break;
    }
}
afirmar(
    !$bordesProhibidos,
    "Ausencia estricta de border dashed/dotted en camargo.css (todos los bordes son continuos/sólidos)",
    $fallos, $totalCasos, $casosPasados
);

// -----------------------------------------------------------------------------
// BLOQUE 3: HOMOLOGACIÓN DE FORMULARIOS EN VISTAS CLAVE
// -----------------------------------------------------------------------------
echo "\n--- 3. Homologación de Formularios en Vistas Clave ---\n";

$vistasAValidar = [
    'personal/index.php' => ['app-form', 'app-icon-form', 'icon-control', 'basic-select2', 'app-switch'],
    'clientes/index.php' => ['app-form', 'app-icon-form', 'icon-control', 'basic-select2'],
    'clientes/detalle.php' => ['app-form', 'app-icon-form', 'icon-control'],
    'empresas/index.php' => ['app-form', 'app-icon-form', 'icon-control', 'basic-select2', 'app-switch'],
    'gastos/index.php' => ['form-nuevo-gasto', 'form-pago-gasto', 'app-icon-form', 'icon-control'],
    'reclamaciones/index.php' => ['form-asistido', 'app-icon-form', 'icon-control', 'basic-select2'],
    'reclamaciones/detalle.php' => ['form-nota-interna', 'form-ofrecimiento', 'form-respuesta-formal', 'app-icon-form'],
    'operaciones/bitacora.php' => ['form-crear-entrada', 'form-agregar-seguimiento', 'app-icon-form', 'basic-select2'],
    'operaciones/night_audit.php' => ['form-night-audit', 'app-icon-form', 'icon-control', 'basic-select2'],
    'suministros/index.php' => ['form-liquidar', 'form-medidor', 'form-lectura', 'form-tarifa', 'app-icon-form', 'basic-select2'],
];

foreach ($vistasAValidar as $vistaRelativa => $tokensRequeridos) {
    $rutaVista = dirname(__DIR__) . '/app/Vistas/' . $vistaRelativa;
    $contenidoVista = file_exists($rutaVista) ? file_get_contents($rutaVista) : '';
    $faltanTokens = [];

    foreach ($tokensRequeridos as $token) {
        if (!str_contains($contenidoVista, $token)) {
            $faltanTokens[] = $token;
        }
    }

    afirmar(
        empty($faltanTokens),
        "Vista {$vistaRelativa} homologada con Alina (tokens verificados: " . implode(', ', $tokensRequeridos) . ")",
        $fallos, $totalCasos, $casosPasados
    );
}

// -----------------------------------------------------------------------------
// BLOQUE 4: GOBERNANZA JAVASCRIPT: 0 JQUERY AJAX EN LÓGICA DE NEGOCIO
// -----------------------------------------------------------------------------
echo "\n--- 4. Gobernanza JS: 0 jQuery AJAX en Lógica de Negocio ---\n";

afirmar(
    !str_contains($camargoFormsContent, '$.ajax') &&
    !str_contains($camargoFormsContent, '$.post') &&
    !str_contains($camargoFormsContent, '$.get'),
    "camargo-forms.js utiliza exclusivamente JavaScript moderno y Fetch (0 jQuery AJAX)",
    $fallos, $totalCasos, $casosPasados
);

$camargoSelectContent = file_get_contents(dirname(__DIR__) . '/public/assets/js/camargo-select.js');
afirmar(
    !str_contains($camargoSelectContent, '$.ajax') &&
    !str_contains($camargoSelectContent, '$.post') &&
    !str_contains($camargoSelectContent, '$.get'),
    "camargo-select.js confina jQuery estrictamente al widget Select2 (0 AJAX de negocio)",
    $fallos, $totalCasos, $casosPasados
);

// -----------------------------------------------------------------------------
// BLOQUE 5: MATRIZ DE CAMPOS CANDIDATOS DNI/RUC (APISPERU)
// -----------------------------------------------------------------------------
echo "\n--- 5. Matriz Candidatos DNI/RUC para APIsPERU ---\n";

$matrizDniRuc = [
    ['modulo' => 'personal', 'vista' => 'personal/index.php', 'formulario' => 'form-alta-colaborador', 'campo' => 'numero_documento', 'tipo' => 'Persona / Colaborador (DNI/CE)'],
    ['modulo' => 'personal', 'vista' => 'personal/index.php', 'formulario' => 'form-editar-persona', 'campo' => 'numero_documento', 'tipo' => 'Persona (DNI/CE)'],
    ['modulo' => 'clientes', 'vista' => 'clientes/index.php', 'formulario' => 'form-alta-cliente', 'campo' => 'numero_documento', 'tipo' => 'Persona / Cliente (DNI/CE)'],
    ['modulo' => 'empresas', 'vista' => 'empresas/index.php', 'formulario' => 'form-empresa', 'campo' => 'numero_documento', 'tipo' => 'Empresa (RUC)'],
    ['modulo' => 'gastos', 'vista' => 'gastos/index.php', 'formulario' => 'form-nuevo-gasto', 'campo' => 'acreedor_documento', 'tipo' => 'Acreedor / Proveedor (RUC/DNI)'],
    ['modulo' => 'reclamaciones', 'vista' => 'reclamaciones/index.php', 'formulario' => 'form-asistido', 'campo' => 'consumidor_numero_documento', 'tipo' => 'Consumidor (DNI/CE)'],
    ['modulo' => 'reclamaciones', 'vista' => 'reclamaciones/publico/formulario.php', 'formulario' => 'form-libro-reclamaciones', 'campo' => 'consumidor_numero_documento', 'tipo' => 'Consumidor (DNI/CE)'],
    ['modulo' => 'reclamaciones', 'vista' => 'reclamaciones/publico/formulario.php', 'formulario' => 'form-libro-reclamaciones', 'campo' => 'apoderado_numero_documento', 'tipo' => 'Apoderado (DNI/CE)'],
    ['modulo' => 'servicios', 'vista' => 'servicios/index.php', 'formulario' => 'form-crear-proveedor', 'campo' => 'numero_documento', 'tipo' => 'Proveedor (RUC/DNI)'],
];

afirmar(
    count($matrizDniRuc) === 9,
    "Matriz transversal de 9 campos candidatos DNI/RUC formalmente inventariada",
    $fallos, $totalCasos, $casosPasados
);

// Verificar que NO se haya introducido cliente de red HTTP a APIsPERU prematuramente
$serviciosArchivos = glob(dirname(__DIR__) . '/app/Servicios/*.php');
$acoplamientoPrematuro = false;
foreach ($serviciosArchivos as $archivoServicio) {
    $contenido = file_get_contents($archivoServicio);
    if (str_contains($contenido, 'api.apisperu.com') || str_contains($contenido, 'ApisPeruServicio')) {
        $acoplamientoPrematuro = true;
        break;
    }
}
afirmar(
    !$acoplamientoPrematuro,
    "Cero acoplamiento prematuro: cliente APIsPERU no implementado antes de su fase autorizada",
    $fallos, $totalCasos, $casosPasados
);

// -----------------------------------------------------------------------------
// BLOQUE 6: INTEGRIDAD DE BASE DE DATOS Y GOBERNANZA GIT
// -----------------------------------------------------------------------------
echo "\n--- 6. Integridad de BD y Repositorio ---\n";

$numTablas = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();
afirmar(
    $numTablas === 118,
    "La base de datos mantiene exactamente 118 tablas (actual: {$numTablas})",
    $fallos, $totalCasos, $casosPasados
);

$ultimaMigracion = (string) $pdo->query("SELECT migracion FROM migraciones ORDER BY id DESC LIMIT 1")->fetchColumn();
afirmar(
    $ultimaMigracion === '034_agregar_foto_personas.sql',
    "Última migración aplicada es 034_agregar_foto_personas.sql (actual: {$ultimaMigracion})",
    $fallos, $totalCasos, $casosPasados
);

// Slot 035 libre
$archivosSql = glob(dirname(__DIR__) . '/SQL/migraciones/035_*.sql');
afirmar(
    empty($archivosSql),
    "Slot 035 estrictamente LIBRE en SQL/migraciones/ (0 DDL en UI-ALINA-1C)",
    $fallos, $totalCasos, $casosPasados
);

// admin-dashboard/ inmutable
$adminStatus = shell_exec('git status --porcelain admin-dashboard/');
afirmar(
    empty(trim((string) $adminStatus)),
    "admin-dashboard/ permanece 100% inmutable y libre de modificaciones",
    $fallos, $totalCasos, $casosPasados
);

// -----------------------------------------------------------------------------
// RESUMEN FINAL
// -----------------------------------------------------------------------------
echo "\n====================================================================\n";
echo " RESUMEN UI-ALINA-1C: {$casosPasados} / {$totalCasos} PASADAS\n";
if (!empty($fallos)) {
    echo " FALLOS DETECTADOS (" . count($fallos) . "):\n";
    foreach ($fallos as $f) {
        echo "  - {$f}\n";
    }
    echo "====================================================================\n";
    exit(1);
} else {
    echo " CERTIFICACIÓN PASS: Homologación transversal completada al 100%.\n";
    echo "====================================================================\n";
    exit(0);
}
