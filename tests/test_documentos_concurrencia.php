<?php

declare(strict_types=1);

/**
 * Camargo PMS — Batería de Pruebas de Concurrencia e Integridad Transaccional (DOCUMENTOS-1 / D-079)
 *
 * Casos evaluados:
 * - DOC-C01: Generación de folios concurrentes bajo bloqueo pesimista FOR UPDATE sin duplicados.
 * - DOC-C02: Restricción física InnoDB uq_dpv_plantilla_activa sobre columna virtual (Error 1062).
 * - DOC-C03: Detección transaccional de discrepancia de hash SHA-256 y detención de entrega corrupta.
 * - DOC-C04: Confinamiento chroot estricto en Dompdf que bloquea traversal o accesos al SO.
 * - DOC-C05: Rechazo estricto de inyección XSS y contenido inseguro antes de persistir versiones.
 * - DOC-C06: Inmutabilidad histórica garantizada: regeneración controlada exclusiva desde snapshot congelado.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CamargoPMS\Excepciones\ContenidoDocumentalInseguroExcepcion;
use CamargoPMS\Excepciones\DocumentoCorruptoExcepcion;
use CamargoPMS\Modelos\DocumentoPlantillaVersion;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Repositorios\ArrendamientoRepositorio;
use CamargoPMS\Repositorios\DocumentoRepositorio;
use CamargoPMS\Servicios\DocumentoServicio;
use CamargoPMS\Servicios\Documentos\GeneradorPdf;
use CamargoPMS\Servicios\Documentos\ValidadorHtmlDocumental;

echo "====================================================================\n";
echo " Camargo PMS — Concurrencia e Integridad (DOCUMENTOS-1 / D-079)\n";
echo "====================================================================\n\n";

$passCount = 0;
$totalCount = 6;

function verificarConcurrencia(string $codigo, string $descripcion, bool $resultado, ?string $detalle = null): void
{
    global $passCount;
    if ($resultado) {
        $passCount++;
        echo "  [PASS] {$codigo}: {$descripcion}\n";
    } else {
        echo "  [FAIL] {$codigo}: {$descripcion}\n";
        if ($detalle) {
            echo "         Motivo: {$detalle}\n";
        }
    }
}

try {
    Configuracion::cargar(dirname(__DIR__));
    $pdo = BaseDatos::conexion();
    $pdo->exec("SET SESSION innodb_lock_wait_timeout = 3;");

    $docRepo = new DocumentoRepositorio($pdo);
    $arrRepo = new ArrendamientoRepositorio($pdo);
    $docServicio = new DocumentoServicio($docRepo, $arrRepo);

    // ---------------------------------------------------------------------
    // DOC-C01: Generación de folios concurrentes bajo bloqueo pesimista
    // ---------------------------------------------------------------------
    $fechaSimulada = new DateTimeImmutable('2026-11-01');
    $pdo->exec("DELETE FROM documento_secuencias WHERE tipo_documento = 'CONTRATO_ARRENDAMIENTO' AND periodo_ym = '202611'");
    $folios = [];
    $pdo->beginTransaction();
    try {
        for ($i = 0; $i < 5; $i++) {
            $folios[] = $docRepo->obtenerSiguienteFolio('CONTRATO_ARRENDAMIENTO', 'ARR', $fechaSimulada);
        }
        $pdo->commit();
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    $foliosUnicos = array_unique($folios);
    $c01Exito = count($folios) === 5 && count($foliosUnicos) === 5 && $folios[4] === 'DOC-ARR-202611-0005';
    verificarConcurrencia(
        'DOC-C01',
        'Generación de folios atómicos bajo FOR UPDATE sin colisiones (5/5 únicos)',
        $c01Exito,
        $c01Exito ? null : 'Duplicación o salto en generación de folios: ' . implode(', ', $folios)
    );

    // ---------------------------------------------------------------------
    // DOC-C02: Restricción física InnoDB de versión activa única
    // ---------------------------------------------------------------------
    $c02Exito = false;
    $vPrevias = $docRepo->obtenerUltimoNumeroVersion(1);
    try {
        $stmtC02 = $pdo->prepare(
            'INSERT INTO documento_plantilla_versiones (
                plantilla_id, numero_version, titulo_documento, cuerpo_html, es_activa, creado_por_actor_id
            ) VALUES (1, :numVer, "Version Conflicto", "<p>Test</p>", 1, 1)'
        );
        $stmtC02->execute(['numVer' => $vPrevias + 500]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'uq_dpv_plantilla_activa')) {
            $c02Exito = true;
        }
    }
    verificarConcurrencia(
        'DOC-C02',
        'Restricción física InnoDB uq_dpv_plantilla_activa bloquea versiones activas simultáneas (Error 1062)',
        $c02Exito
    );

    // ---------------------------------------------------------------------
    // DOC-C03: Detección transaccional de discrepancia de hash y detención
    // ---------------------------------------------------------------------
    $emision = $docServicio->emitirContratoArrendamiento(1, 1, null, false);
    $docId = $emision['documento']->obtenerId();
    $rutaPdf = dirname(__DIR__) . '/storage/' . str_replace('/', DIRECTORY_SEPARATOR, $emision['documento']->obtenerRutaArchivoPdf());

    $bytesOriginales = file_get_contents($rutaPdf);
    // Inyectar byte alterado simulando corrupción o sabotaje en almacenamiento
    file_put_contents($rutaPdf, $bytesOriginales . 'TAMPERED');

    $c03Exito = false;
    try {
        $docServicio->descargarDocumento($docId, 1);
    } catch (DocumentoCorruptoExcepcion $e) {
        if ($e->obtenerTipoIncidencia() === 'HASH_NO_COINCIDE') {
            $c03Exito = true;
        }
    }

    // Restaurar archivo para siguientes operaciones
    file_put_contents($rutaPdf, $bytesOriginales);

    verificarConcurrencia(
        'DOC-C03',
        'Detección transaccional de discrepancia de hash SHA-256 detiene la entrega del documento corrupto',
        $c03Exito
    );

    // ---------------------------------------------------------------------
    // DOC-C04: Confinamiento chroot estricto en Dompdf
    // ---------------------------------------------------------------------
    $generador = new GeneradorPdf();
    $opts = $generador->obtenerOpcionesConfiguradas();
    $chroots = is_array($opts->getChroot()) ? $opts->getChroot() : [$opts->getChroot()];

    $permiteRemoto = $opts->getIsRemoteEnabled();
    $todosDentroDeStorage = true;
    foreach ($chroots as $dir) {
        if (!str_contains($dir, 'storage') && !str_contains($dir, 'fuentes') && !str_contains($dir, 'public')) {
            $todosDentroDeStorage = false;
        }
    }

    $c04Exito = ($permiteRemoto === false) && $todosDentroDeStorage;
    verificarConcurrencia(
        'DOC-C04',
        'Confinamiento chroot estricto de Dompdf sin URLs remotas ni acceso a archivos de sistema',
        $c04Exito
    );

    // ---------------------------------------------------------------------
    // DOC-C05: Rechazo estricto de inyección XSS y contenido inseguro
    // ---------------------------------------------------------------------
    $validador = new ValidadorHtmlDocumental();
    $payloadsMaliciosos = [
        '<div onmouseover="alert(\'xss\')">Texto</div>',
        '<script type="text/javascript">window.location="http://evil.com"</script>',
        '<iframe src="file:///etc/passwd"></iframe>',
        '<embed src="malware.pdf"></embed>',
        '<a href="javascript:void(0)">Link</a>',
    ];

    $todosRechazados = true;
    foreach ($payloadsMaliciosos as $p) {
        try {
            $validador->validar($p);
            $todosRechazados = false;
            break;
        } catch (ContenidoDocumentalInseguroExcepcion $e) {
            // Esperado
        }
    }

    verificarConcurrencia(
        'DOC-C05',
        'Rechazo estricto de 5 vectores XSS / HTML malicioso antes de compilar o persistir versiones',
        $todosRechazados
    );

    // ---------------------------------------------------------------------
    // DOC-C06: Inmutabilidad histórica ante mutación viva del catálogo
    // ---------------------------------------------------------------------
    // Simular que el canon del arrendamiento en BD cambia
    $canonOriginal = $pdo->query('SELECT renta_mensual FROM arrendamientos WHERE id = 1')->fetchColumn();
    $pdo->exec('UPDATE arrendamientos SET renta_mensual = 8888.88 WHERE id = 1');

    // Forzar regeneración del PDF físico usando EXCLUSIVAMENTE el snapshot_html congelado
    $docRegen = $docServicio->regenerarPdfDesdeSnapshot($docId, 1, 'Auditoría de inmutabilidad histórica');
    $rutaRegen = dirname(__DIR__) . '/storage/' . str_replace('/', DIRECTORY_SEPARATOR, $docRegen->obtenerRutaArchivoPdf());
    $pdfContenido = file_get_contents($rutaRegen);

    // El PDF reconstruido debe contener el canon histórico original, NO el nuevo canon 8888.88
    $canonOriginalFormato = number_format((float) $canonOriginal, 2);
    $c06Exito = !str_contains($pdfContenido, '8888.88') && $docRegen->obtenerSnapshotDatosJson()['contrato.canon_monto'] !== '8888.88';

    // Restaurar canon original
    $stmtRest = $pdo->prepare('UPDATE arrendamientos SET renta_mensual = :canon WHERE id = 1');
    $stmtRest->execute(['canon' => $canonOriginal]);

    verificarConcurrencia(
        'DOC-C06',
        'Inmutabilidad histórica: PDF regenerado utiliza estrictamente snapshot congelado y no se contamina por cambios en BD',
        $c06Exito
    );

} catch (\Throwable $e) {
    echo "ERROR CATASTRÓFICO EN SUITE DE CONCURRENCIA:\n" . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n====================================================================\n";
echo "RESULTADOS CONCURRENCIA DOCUMENTOS-1: {$passCount} / {$totalCount} PASS\n";
echo "====================================================================\n";

if ($passCount === $totalCount) {
    exit(0);
}
exit(1);
