<?php
/**
 * Test Suite: Validación UI-ALINA-1E — Homologación Transversal de Tablas Alina
 * Decisión Vinculante: D-098
 * 
 * Verifica que todas las tablas candidatas en app/Vistas implementen el estándar:
 * Bordered Tables With Striped + Hoverable Table (.table.table-bordered.table-striped.table-hover.align-middle),
 * que los contenedores responsive estén presentes, que las exclusiones legítimas se respeten,
 * y que la base de datos e inmutabilidad de Alina se mantengan íntegras.
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
echo " CAMARGO PMS — VALIDACIÓN UI-ALINA-1E: TABLAS ALINA (D-098)\n";
echo "====================================================================\n\n";

// --- 1. Reglas CSS de Tablas en camargo.css ---
echo "--- 1. Reglas CSS de Tablas en camargo.css ---\n";
$cssPath = __DIR__ . '/../public/assets/css/camargo.css';
assertCheck(file_exists($cssPath), "El archivo public/assets/css/camargo.css existe");
$cssContent = file_get_contents($cssPath);

assertCheck(strpos($cssContent, 'TABLAS ALINA') !== false, "camargo.css incluye sección dedicada a Tablas Alina (UI-ALINA-1E)");
assertCheck(strpos($cssContent, '.table.table-bordered') !== false, "camargo.css define estilización corporativa para .table.table-bordered");
assertCheck(strpos($cssContent, '.table.table-striped') !== false, "camargo.css define alternancia de filas .table.table-striped");
assertCheck(strpos($cssContent, '.table.table-hover') !== false, "camargo.css define retroalimentación interactiva .table.table-hover");
assertCheck(strpos($cssContent, '.table.align-middle') !== false, "camargo.css garantiza alineación vertical middle");
assertCheck(!preg_match('/border(?:-[a-z]+)?\s*:\s*[^;]*(?:dotted|dashed)/i', $cssContent), "Ausencia absoluta de border dotted o border dashed en camargo.css (0 dotted / 0 dashed)");

// --- 2. Inventario y Homologación de Tablas Candidatas ---
echo "\n--- 2. Verificación de Tablas Homologadas en Vistas ---\n";

$dir = new RecursiveDirectoryIterator(__DIR__ . '/../app/Vistas', RecursiveDirectoryIterator::SKIP_DOTS);
$iter = new RecursiveIteratorIterator($dir);

$candidatas = [];
$excluidas = [];

foreach ($iter as $file) {
    if ($file->getExtension() !== 'php') continue;
    $filePath = str_replace('\\', '/', $file->getPathname());
    $lines = file($file->getPathname());
    
    foreach ($lines as $idx => $line) {
        if (preg_match('/<table(\s+[^>]*)?>/i', $line, $matches)) {
            $tag = trim($matches[0]);
            $isPdf = (strpos($filePath, 'plantillas/') !== false);
            $isTapeChart = (strpos($filePath, 'tape-chart') !== false);
            $isRack = (strpos($line, 'tabla-rack') !== false);
            $isBorderless = (strpos($line, 'table-borderless') !== false);
            
            if ($isPdf || $isTapeChart || $isRack || $isBorderless) {
                $excluidas[] = [
                    'file' => $filePath,
                    'line' => $idx + 1,
                    'tag' => $tag,
                    'isPdf' => $isPdf,
                    'isTapeChart' => $isTapeChart,
                    'isRack' => $isRack,
                    'isBorderless' => $isBorderless
                ];
            } else {
                $candidatas[] = [
                    'file' => $filePath,
                    'line' => $idx + 1,
                    'tag' => $tag,
                    'raw_line' => $line
                ];
            }
        }
    }
}

assertCheck(count($candidatas) >= 80, "Se identifican al menos 80 tablas candidatas a homologación (encontradas: " . count($candidatas) . ")");

$todasCumplenBordered = true;
$todasCumplenStriped = true;
$todasCumplenHover = true;
$todasCumplenAlignMiddle = true;

foreach ($candidatas as $c) {
    if (strpos($c['tag'], 'table-bordered') === false) {
        $todasCumplenBordered = false;
        echo "  [FAIL DETAIL] Falta table-bordered en {$c['file']} L{$c['line']}\n";
    }
    if (strpos($c['tag'], 'table-striped') === false) {
        $todasCumplenStriped = false;
        echo "  [FAIL DETAIL] Falta table-striped en {$c['file']} L{$c['line']}\n";
    }
    if (strpos($c['tag'], 'table-hover') === false) {
        $todasCumplenHover = false;
        echo "  [FAIL DETAIL] Falta table-hover en {$c['file']} L{$c['line']}\n";
    }
    if (strpos($c['tag'], 'align-middle') === false) {
        $todasCumplenAlignMiddle = false;
        echo "  [FAIL DETAIL] Falta align-middle en {$c['file']} L{$c['line']}\n";
    }
}

$totalCand = count($candidatas);
assertCheck($todasCumplenBordered, "100% de las tablas candidatas incluyen .table-bordered ($totalCand/$totalCand)");
assertCheck($todasCumplenStriped, "100% de las tablas candidatas incluyen .table-striped ($totalCand/$totalCand)");
assertCheck($todasCumplenHover, "100% de las tablas candidatas incluyen .table-hover ($totalCand/$totalCand)");
assertCheck($todasCumplenAlignMiddle, "100% de las tablas candidatas incluyen .align-middle ($totalCand/$totalCand)");

// --- 3. Contenedores Responsive ---
echo "\n--- 3. Contenedores Responsive (.table-responsive) ---\n";
$todasTienenResponsive = true;
foreach ($candidatas as $c) {
    $lines = file($c['file']);
    $lineIdx = $c['line'] - 1;
    $hasResp = false;
    for ($k = max(0, $lineIdx - 5); $k < $lineIdx; $k++) {
        if (strpos($lines[$k], 'table-responsive') !== false) {
            $hasResp = true;
            break;
        }
    }
    if (!$hasResp) {
        $todasTienenResponsive = false;
        echo "  [FAIL DETAIL] Falta .table-responsive inmediato en {$c['file']} L{$c['line']}\n";
    }
}
assertCheck($todasTienenResponsive, "100% de las tablas candidatas están encapsuladas en contenedores .table-responsive");

// --- 4. Exclusiones Legítimas Justificadas ---
echo "\n--- 4. Exclusiones Legítimas Justificadas ---\n";
assertCheck(count($excluidas) === 16, "Se identifican exactamente 16 tablas con exclusión legítima (encontradas: " . count($excluidas) . ")");

$pdfCount = 0;
$tapeChartCount = 0;
$rackCount = 0;
$borderlessCount = 0;

foreach ($excluidas as $e) {
    if ($e['isPdf']) $pdfCount++;
    if ($e['isTapeChart']) $tapeChartCount++;
    if ($e['isRack']) $rackCount++;
    if ($e['isBorderless']) $borderlessCount++;
}

assertCheck($pdfCount === 6, "Plantilla PDF/Impresión (hoja_reclamacion.php) preserva sus 6 tablas intactas sin clases web");
assertCheck($tapeChartCount === 1, "Tape Chart preserva su grilla interactiva especializada (.tape-chart-table)");
assertCheck($rackCount === 1, "Disponibilidad preserva su matriz de rack interactiva (#tabla-rack)");
assertCheck($borderlessCount === 8, "Fichas de metadatos clave-valor preservan sus 8 tablas como .table-borderless");

// --- 5. Integridad de Base de Datos y Repositorio ---
echo "\n--- 5. Integridad de BD y Repositorio ---\n";
try {
    $stmtTablas = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()");
    $totalTablas = (int) $stmtTablas->fetchColumn();
    assertCheck($totalTablas >= 118, "Base de datos cuenta con al menos 118 tablas (actual: $totalTablas)");

    $mig034Presente = (bool) $pdo->query("SELECT 1 FROM migraciones WHERE migracion = '034_agregar_foto_personas.sql'")->fetchColumn();
    assertCheck($mig034Presente, "Migración 034_agregar_foto_personas.sql presente en BD");

    $migraciones039 = glob(dirname(__DIR__) . '/SQL/migraciones/039_*.sql');
    assertCheck(empty($migraciones039), "Slot de migración 039 estrictamente LIBRE en SQL/migraciones/ (0 DDL no autorizado)");
} catch (Exception $e) {
    assertCheck(false, "Error al verificar BD: " . $e->getMessage());
}

$statusAlina = (string) (shell_exec('git status --porcelain admin-dashboard/') ?? '');
assertCheck(empty(trim($statusAlina)), "admin-dashboard/ permanece 100% inmutable y libre de modificaciones");

echo "\n====================================================================\n";
echo " RESUMEN: $passedAssertions / $totalAssertions pruebas superadas\n";
echo "====================================================================\n\n";

if ($passedAssertions === $totalAssertions) {
    exit(0);
} else {
    exit(1);
}
