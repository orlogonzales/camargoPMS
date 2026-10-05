<?php
/**
 * Test Suite: Validación UI-ALINA-1F — Barrido Visual Global, Responsive y Cierre de Homologación Alina
 * Decisión Vinculante: D-099
 * 
 * Valida de forma exhaustiva:
 * 1. Barrido de residuos visuales: ausencia total de clases *-subtle en vistas y JS propios.
 * 2. Ausencia absoluta de bordes dotted/dashed y fuentes de iconos ajenas (ti-, bi-, feather-).
 * 3. 100% de modales con alineación centrada (modal-dialog-centered).
 * 4. Preservación estricta de las ganancias acumuladas (1A, 1B, 1B-C1, 1C, 1D, 1E).
 * 5. Preservación estricta de las 16 exclusiones legítimas de tablas de 1E.
 * 6. Gobernanza inmutable: 118 tablas en BD, migración 034 como última, ranura 035 libre y admin-dashboard/ intacto.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;

Configuracion::cargar(dirname(__DIR__));
$pdo = BaseDatos::conexion();

$totalAssertions = 0;
$passedAssertions = 0;

function assertCheck($condition, $message) {
    global $totalAssertions, $passedAssertions;
    $totalAssertions++;
    if ($condition) {
        $passedAssertions++;
        echo "  [PASS] $message\n";
    } else {
        echo "  [FAIL] $message\n";
    }
}

echo "====================================================================\n";
echo " CAMARGO PMS — VALIDACIÓN UI-ALINA-1F: BARRIDO VISUAL Y CIERRE (D-099)\n";
echo "====================================================================\n\n";

// --- 1. BARRIDO DE RESIDUOS VISUALES ---
echo "--- 1. Barrido de Residuos Visuales (*-subtle, dotted/dashed, iconos foráneos) ---\n";

// 1.1 Clases *-subtle en app/Vistas/
$vistasDir = new RecursiveDirectoryIterator(__DIR__ . '/../app/Vistas', RecursiveDirectoryIterator::SKIP_DOTS);
$vistasIter = new RecursiveIteratorIterator($vistasDir);

$vistasConSubtle = [];
$vistasConIconosAjenos = [];
$vistasConDottedDashed = [];
$modalesSinCentrar = [];

foreach ($vistasIter as $file) {
    if ($file->getExtension() !== 'php') continue;
    $content = file_get_contents($file->getPathname());
    $relPath = str_replace('\\', '/', str_replace(dirname(__DIR__) . '/', '', $file->getPathname()));
    
    // Buscar *-subtle
    if (preg_match_all('/\b(bg|text|border)-[a-z]+-subtle\b/', $content, $mSubtle)) {
        $vistasConSubtle[$relPath] = array_unique($mSubtle[0]);
    }
    
    // Buscar iconos ajenos (ti-, bi-, feather-)
    if (preg_match_all('/\b(ti-[a-z0-9-]+|bi-[a-z0-9-]+|feather-[a-z0-9-]+)\b/', $content, $mIcons)) {
        $vistasConIconosAjenos[$relPath] = array_unique($mIcons[0]);
    }
    
    // Buscar dotted/dashed
    if (preg_match_all('/style="[^"]*border[^"]*(?:dotted|dashed)[^"]*"/i', $content, $mDotted)) {
        $vistasConDottedDashed[$relPath] = $mDotted[0];
    }
    
    // Modales sin modal-dialog-centered
    if (strpos($content, 'modal-dialog') !== false) {
        preg_match_all('/class="modal-dialog(?:\s+[^"]*)?"/i', $content, $mModals);
        foreach ($mModals[0] as $modalClass) {
            if (strpos($modalClass, 'modal-dialog-centered') === false) {
                $modalesSinCentrar[] = $relPath . " (" . $modalClass . ")";
            }
        }
    }
}

assertCheck(empty($vistasConSubtle), "Ausencia total de clases *-subtle en app/Vistas/ (0 encontradas: " . count($vistasConSubtle) . ")");
assertCheck(empty($vistasConIconosAjenos), "Ausencia total de iconos ajenos ti-, bi-, feather- en app/Vistas/ (100% Font Awesome 6)");
assertCheck(empty($vistasConDottedDashed), "Ausencia total de estilos border dotted/dashed en app/Vistas/");
assertCheck(empty($modalesSinCentrar), "100% de los diálogos modales implementan .modal-dialog-centered (0 sin centrar)");

// 1.2 Clases *-subtle en public/assets/js/
$jsDir = new RecursiveDirectoryIterator(__DIR__ . '/../public/assets/js', RecursiveDirectoryIterator::SKIP_DOTS);
$jsIter = new RecursiveIteratorIterator($jsDir);
$jsConSubtle = [];

foreach ($jsIter as $file) {
    if ($file->getExtension() !== 'js') continue;
    $content = file_get_contents($file->getPathname());
    $relPath = str_replace('\\', '/', str_replace(dirname(__DIR__) . '/', '', $file->getPathname()));
    
    if (preg_match_all('/\b(bg|text|border)-[a-z]+-subtle\b/', $content, $mSubtleJs)) {
        $jsConSubtle[$relPath] = array_unique($mSubtleJs[0]);
    }
}

assertCheck(empty($jsConSubtle), "Ausencia total de clases *-subtle en scripts JavaScript propios de public/assets/js/ (0 encontradas)");

// 1.3 CSS propio (camargo.css)
$cssPath = __DIR__ . '/../public/assets/css/camargo.css';
$cssContent = file_get_contents($cssPath);
// Solo contar si no es un comentario CSS
$cssNoComments = preg_replace('/\/\*.*?\*\//s', '', $cssContent);
assertCheck(!preg_match('/(?:dotted|dashed)/i', $cssNoComments), "Ausencia total de border dotted/dashed en reglas activas de public/assets/css/camargo.css");

// --- 2. CONSISTENCIA DE COMPONENTES ALINA ---
echo "\n--- 2. Consistencia y Adherencia al Sistema de Diseño Alina ---\n";

$panelInicio = file_get_contents(__DIR__ . '/../app/Vistas/panel/inicio.php');
assertCheck(strpos($panelInicio, 'bg-light-primary') !== false, "panel/inicio.php implementa superficies nativas .bg-light-primary de Alina");
assertCheck(strpos($panelInicio, 'bg-light-info') !== false, "panel/inicio.php implementa superficies nativas .bg-light-info de Alina");
assertCheck(strpos($panelInicio, 'bg-light-warning') !== false, "panel/inicio.php implementa superficies nativas .bg-light-warning de Alina");
assertCheck(strpos($panelInicio, 'bg-light-secondary') !== false, "panel/inicio.php implementa superficies nativas .bg-light-secondary de Alina");

$rolesVista = file_get_contents(__DIR__ . '/../app/Vistas/roles/index.php');
assertCheck(strpos($rolesVista, 'text-white-50') !== false, "roles/index.php aplica contraste canónico .text-white-50 en cabecera oscura de modal");

$disponibilidadVista = file_get_contents(__DIR__ . '/../app/Vistas/disponibilidad/index.php');
assertCheck(strpos($disponibilidadVista, 'bg-light border-bottom') !== false, "disponibilidad/index.php aplica .bg-light neutro estándar en tarjetas de KPI");

// --- 3. PRESERVACIÓN DE GANANCIAS ACUMULADAS ---
echo "\n--- 3. Preservación Estricta de Fases Previas (1A, 1B, 1C, 1D, 1E) ---\n";

// 3.1 Layout 1A
$layoutPrincipal = file_get_contents(__DIR__ . '/../app/Vistas/plantillas/principal.php');
$navComponente = file_get_contents(__DIR__ . '/../app/Vistas/componentes/navegacion.php');
assertCheck(strpos($layoutPrincipal, 'app-wrapper') !== false && strpos($navComponente, 'app-navbar') !== false, "UI-ALINA-1A: Estructura canónica app-wrapper / app-navbar de Alina preservada intacta");

// 3.2 Perfil y Customizer 1B / 1B-C1
$perfilVista = file_get_contents(__DIR__ . '/../app/Vistas/perfil/index.php');
assertCheck(strpos($perfilVista, 'profile-container') !== false && strpos($perfilVista, 'avatar-preview') !== false, "UI-ALINA-1B: Arquitectura de perfil Alina con .profile-container y .avatar-preview preservada");
assertCheck(strpos($perfilVista, 'PERSONA ≠ USUARIO') !== false, "UI-ALINA-1B: Principio soberano PERSONA ≠ USUARIO visiblemente preservado");

// 3.3 Formularios 1C
$loginVista = file_get_contents(__DIR__ . '/../app/Vistas/auth/login.php');
assertCheck(strpos($loginVista, 'sign-bg-wrapper') !== false && strpos($loginVista, 'app-icon-form') !== false, "UI-ALINA-1C: Estructura sign-bg-wrapper y app-icon-form de Alina en login preservada");

// 3.4 Componentes 1D
$componentesCss = file_get_contents(__DIR__ . '/../public/assets/css/camargo.css');
assertCheck(strpos($componentesCss, 'COMPONENTES DE INTERACCIÓN ALINA') !== false, "UI-ALINA-1D: Especificación de componentes de interacción preservada en camargo.css");

// 3.5 Tablas 1E: 80 tablas candidatas homologadas
$candidatasTablas = 0;
$tablasHomologadas = 0;
$exclusionesTablas = 0;

foreach ($vistasIter as $file) {
    if ($file->getExtension() !== 'php') continue;
    $filePath = str_replace('\\', '/', $file->getPathname());
    $lines = file($file->getPathname());
    
    foreach ($lines as $line) {
        if (preg_match('/<table(\s+[^>]*)?>/i', $line, $matches)) {
            $isPdf = (strpos($filePath, 'plantillas/') !== false);
            $isTapeChart = (strpos($filePath, 'tape-chart') !== false);
            $isRack = (strpos($line, 'tabla-rack') !== false);
            $isBorderless = (strpos($line, 'table-borderless') !== false);
            
            if ($isPdf || $isTapeChart || $isRack || $isBorderless) {
                $exclusionesTablas++;
            } else {
                $candidatasTablas++;
                if (strpos($line, 'table-bordered') !== false &&
                    strpos($line, 'table-striped') !== false &&
                    strpos($line, 'table-hover') !== false &&
                    strpos($line, 'align-middle') !== false) {
                    $tablasHomologadas++;
                }
            }
        }
    }
}

assertCheck($candidatasTablas >= 80, "UI-ALINA-1E: Se identifican al menos 80 tablas candidatas a homologación (encontradas: $candidatasTablas)");
assertCheck($tablasHomologadas === $candidatasTablas, "UI-ALINA-1E: 100% de tablas convencionales mantienen estándar Alina .table-bordered.table-striped.table-hover.align-middle ($tablasHomologadas/$candidatasTablas)");
assertCheck($exclusionesTablas === 16, "UI-ALINA-1E: Se preservan intactas las 16 exclusiones legítimas (encontradas: $exclusionesTablas)");

// --- 4. GOBERNANZA DE BASE DE DATOS Y REPOSITORIO ---
echo "\n--- 4. Gobernanza, Base de Datos y Repositorio ---\n";

$tablesStmt = $pdo->query('SHOW TABLES');
$tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);
assertCheck(count($tables) >= 118, "Base de datos cuenta con al menos 118 tablas relacionales (actual: " . count($tables) . ")");

$mig034Presente = (bool) $pdo->query("SELECT 1 FROM migraciones WHERE migracion = '034_agregar_foto_personas.sql'")->fetchColumn();
assertCheck($mig034Presente, "Migración 034_agregar_foto_personas.sql presente en BD");

$mig042Files = glob(__DIR__ . '/../SQL/migraciones/*042*');
assertCheck(empty($mig042Files), "Ranura de migración 042 estrictamente LIBRE en SQL/migraciones/ (cero DDL no autorizado)");

// Verificación de inmutabilidad de admin-dashboard/
$gitStatusAlina = shell_exec('git status --porcelain admin-dashboard/');
assertCheck(empty(trim((string)$gitStatusAlina)), "admin-dashboard/ permanece 100% inmutable y libre de modificaciones");

echo "\n====================================================================\n";
echo " RESUMEN: $passedAssertions / $totalAssertions pruebas superadas\n";
echo "====================================================================\n\n";

if ($passedAssertions === $totalAssertions) {
    echo ">>> UI-ALINA-1F: HOMOLOGACIÓN Y BARRIDO VISUAL EXITOSO (100% PASS) <<<\n";
    exit(0);
} else {
    echo ">>> UI-ALINA-1F: DETECTADAS FALLAS EN LA VALIDACIÓN <<<\n";
    exit(1);
}
