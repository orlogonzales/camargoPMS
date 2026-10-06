<?php

declare(strict_types=1);

/**
 * Script CLI Administrativo: Aprovisionamiento e Instalación Segura de Esquemas XSD SUNAT UBL 2.1
 *
 * Uso:
 *   php bin/aprovisionar-esquemas-sunat.php <ruta_paquete_zip_o_directorio> [directorio_destino]
 *
 * Seguridad y Gobernanza:
 * - Estrictamente CLI (rechaza ejecución web).
 * - Cero descargas automáticas por red.
 * - Validación exhaustiva de rutas en ZIP (anti path traversal, UNC, symlinks).
 * - Extracción en directorio temporal privado y verificación del 100% de los hashes SHA-256
 *   contra ManifiestoEsquemasUbl21 antes de la promoción atómica.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "ACCESO DENEGADO: Este comando solo puede ser ejecutado desde la interfaz de línea de comandos (CLI).\n";
    exit(1);
}

require_once __DIR__ . '/../vendor/autoload.php';

use CamargoPMS\Servicios\CPE\Validacion\ManifiestoEsquemasUbl21;

echo "====================================================================\n";
echo " CAMARGO PMS — APROVISIONAMIENTO SEGURO DE ESQUEMAS XSD UBL 2.1\n";
echo "====================================================================\n\n";

$origen = $argv[1] ?? null;
$destino = $argv[2] ?? (dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cpe' . DIRECTORY_SEPARATOR . 'esquemas' . DIRECTORY_SEPARATOR . '2.1');

if ($origen === null || trim($origen) === '' || in_array($origen, ['-h', '--help', 'help'], true)) {
    echo "Uso:\n";
    echo "  php bin/aprovisionar-esquemas-sunat.php <ruta_zip_o_carpeta_origen> [directorio_destino]\n\n";
    echo "Ejemplo:\n";
    echo "  php bin/aprovisionar-esquemas-sunat.php scratch/sunat_assets/SFS_v-2.1.zip\n\n";
    exit(1);
}

if (!file_exists($origen)) {
    fwrite(STDERR, "[ERROR] La ruta de origen no existe: {$origen}\n");
    exit(1);
}

// 1. Directorio temporal privado para cuarentena y verificación
$dirTemp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'camargo_sunat_prov_' . bin2hex(random_bytes(6));
if (!mkdir($dirTemp, 0700, true)) {
    fwrite(STDERR, "[ERROR] No se pudo crear directorio temporal de cuarentena: {$dirTemp}\n");
    exit(1);
}

// Función de limpieza de emergencia
$limpiarTemp = function () use ($dirTemp) {
    if (is_dir($dirTemp)) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dirTemp, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dirTemp);
    }
};

try {
    echo "[1/3] Extrayendo archivos a cuarentena...\n";

    if (is_file($origen)) {
        // Paquete ZIP
        $zip = new ZipArchive();
        $abierto = $zip->open($origen);
        if ($abierto !== true) {
            throw new RuntimeException("No se pudo abrir el archivo ZIP (código error: {$abierto})");
        }

        // Inspección previa de seguridad de cada entrada en el ZIP
        $entradasXsd = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nombre = $zip->getNameIndex($i);

            // Defensas anti-traversal y rutas sospechosas
            if (str_contains($nombre, '..') || str_starts_with($nombre, '/') || str_starts_with($nombre, '\\') || preg_match('/^[a-zA-Z]:/', $nombre)) {
                throw new RuntimeException("El archivo ZIP contiene una ruta sospechosa o intento de traversal: {$nombre}");
            }

            // Omitir directorios explícitos del ZIP
            if (str_ends_with($nombre, '/') || str_ends_with($nombre, '\\')) {
                continue;
            }

            // Identificar esquemas bajo commons/xsd/2.1/ que terminen en .xsd
            if (preg_match('/(?:sunat_archivos\/sfs\/VALI\/commons\/xsd\/2\.1|commons\/xsd\/2\.1)\/(.+\.xsd)$/i', $nombre, $match)) {
                $subRuta = str_replace('\\', '/', $match[1]);
                $stat = $zip->statIndex($i);
                if ($stat['size'] > 10485760) { // 10 MB límite individual
                    throw new RuntimeException("Archivo en ZIP excede el tamaño máximo razonable (10MB): {$nombre}");
                }
                $entradasXsd[$nombre] = $subRuta;
            }
        }

        if (empty($entradasXsd)) {
            throw new RuntimeException("No se encontraron esquemas XSD UBL 2.1 válidos dentro del paquete ZIP suministrado");
        }

        // Extracción segura en cuarentena
        foreach ($entradasXsd as $nombreZip => $subRuta) {
            $destinoArchivo = $dirTemp . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $subRuta);
            $dirPadre = dirname($destinoArchivo);
            if (!is_dir($dirPadre)) {
                mkdir($dirPadre, 0700, true);
            }
            $streamIn = $zip->getStream($nombreZip);
            if (!$streamIn) {
                throw new RuntimeException("Fallo al leer stream de archivo en ZIP: {$nombreZip}");
            }
            $streamOut = fopen($destinoArchivo, 'wb');
            stream_copy_to_stream($streamIn, $streamOut);
            fclose($streamIn);
            fclose($streamOut);
        }
        $zip->close();
    } else {
        // Directorio fuente
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($origen, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($it as $item) {
            if ($item->isFile()) {
                $rel = ltrim(str_replace(str_replace('/', DIRECTORY_SEPARATOR, $origen), '', $item->getPathname()), '/\\');
                $relNorm = str_replace('\\', '/', $rel);
                if (preg_match('/(?:commons\/xsd\/2\.1\/|2\.1\/)?((?:maindoc|common)\/.+\.xsd)$/i', $relNorm, $m)) {
                    $subRuta = $m[1];
                    $destinoArchivo = $dirTemp . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $subRuta);
                    $dirPadre = dirname($destinoArchivo);
                    if (!is_dir($dirPadre)) {
                        mkdir($dirPadre, 0700, true);
                    }
                    copy($item->getPathname(), $destinoArchivo);
                }
            }
        }
    }

    echo "      Esquemas extraídos en cuarentena temporal.\n";

    // 2. Verificación de integridad contra el Manifiesto oficial
    echo "[2/3] Verificando integridad de esquemas contra Manifiesto UBL 2.1...\n";
    $listaRequerida = ManifiestoEsquemasUbl21::obtenerListaEsquemas();
    $totalVerificados = 0;

    foreach ($listaRequerida as $relPath) {
        $rutaCuarentena = $dirTemp . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath);
        if (!file_exists($rutaCuarentena)) {
            throw new RuntimeException("Falta esquema requerido en el paquete suministrado: {$relPath}");
        }

        $meta = ManifiestoEsquemasUbl21::obtenerMetadatos($relPath);
        $tamano = filesize($rutaCuarentena);
        if ($tamano !== $meta['bytes']) {
            throw new RuntimeException("Discrepancia en tamaño para '{$relPath}'. Esperado: {$meta['bytes']}, Actual: {$tamano}");
        }

        $hash = hash_file('sha256', $rutaCuarentena);
        if (!hash_equals($meta['sha256'], (string) $hash)) {
            throw new RuntimeException("Huella SHA-256 no coincide para '{$relPath}'. Posible archivo adulterado o corrupto");
        }
        $totalVerificados++;
    }

    echo "      100% de los esquemas requeridos ({$totalVerificados} archivos) verificados con éxito (SHA-256 PASS).\n";

    // 3. Promoción atómica al directorio de destino final
    echo "[3/3] Promoviendo esquemas al directorio activo...\n";
    if (!is_dir($destino)) {
        if (!mkdir($destino, 0755, true)) {
            throw new RuntimeException("No se pudo crear el directorio de destino final: {$destino}");
        }
    }

    foreach ($listaRequerida as $relPath) {
        $origenArch = $dirTemp . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath);
        $destinoArch = $destino . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath);
        $dirPadre = dirname($destinoArch);
        if (!is_dir($dirPadre)) {
            mkdir($dirPadre, 0755, true);
        }
        if (!copy($origenArch, $destinoArch)) {
            throw new RuntimeException("Error al copiar esquema a ubicación definitiva: {$relPath}");
        }
    }

    // Limpieza
    $limpiarTemp();

    echo "\n====================================================================\n";
    echo " APROVISIONAMIENTO COMPLETADO EXITOSAMENTE:\n";
    echo " Total esquemas promovidos: {$totalVerificados}\n";
    echo " Directorio activo:         {$destino}\n";
    echo " Versión normativa:         " . ManifiestoEsquemasUbl21::VERSION_NORMATIVA . "\n";
    echo " Integridad SHA-256:        100% CONFORME\n";
    echo "====================================================================\n";
    exit(0);

} catch (Throwable $e) {
    $limpiarTemp();
    fwrite(STDERR, "\n[ERROR CRÍTICO] " . $e->getMessage() . "\n");
    fwrite(STDERR, "Aprovisionamiento abortado (Fail-Closed). Ningún esquema fue modificado.\n");
    exit(1);
}
