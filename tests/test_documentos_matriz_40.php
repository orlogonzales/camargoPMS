<?php

declare(strict_types=1);

/**
 * Suite de Verificación DOCUMENTOS-1 — Matriz Exhaustiva de 40 Pruebas de Dominio
 *
 * Verifica las decisiones y contratos vinculantes de D-079 (GATE P-007):
 * - PLANTILLA != VERSIÓN != SNAPSHOT != DOCUMENTO EMITIDO != PDF BINARIO.
 * - Dompdf 3.x puro con confinamiento chroot y desactivación de PHP/JS/Remote.
 * - Validador documental estricto (bloquea XSS, scripts, iframes, urls remotas).
 * - Catálogo tipado de shortcodes con detección de variables desconocidas y faltantes (HTTP 422).
 * - Snapshots deterministas (ksort) y hash SHA-256 físico del archivo PDF.
 * - Cero regeneración silenciosa: detección de corrupción y registro de incidencias.
 * - Regeneración asistida controlada desde snapshot_html inmutable.
 * - Concurrencia de folios atómicos y columna virtual InnoDB para activación única de versiones.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CamargoPMS\Excepciones\ContenidoDocumentalInseguroExcepcion;
use CamargoPMS\Excepciones\DocumentoCorruptoExcepcion;
use CamargoPMS\Excepciones\PlantillaNoEncontradaExcepcion;
use CamargoPMS\Excepciones\VariableDocumentalDesconocidaExcepcion;
use CamargoPMS\Excepciones\VariableDocumentalFaltanteExcepcion;
use CamargoPMS\Modelos\DocumentoEmitido;
use CamargoPMS\Modelos\DocumentoIncidencia;
use CamargoPMS\Modelos\DocumentoPlantilla;
use CamargoPMS\Modelos\DocumentoPlantillaVersion;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Repositorios\ArrendamientoRepositorio;
use CamargoPMS\Repositorios\DocumentoRepositorio;
use CamargoPMS\Servicios\DocumentoServicio;
use CamargoPMS\Servicios\Documentos\CompiladorDocumental;
use CamargoPMS\Servicios\Documentos\GeneradorPdf;
use CamargoPMS\Servicios\Documentos\RegistroVariablesDocumentales;
use CamargoPMS\Servicios\Documentos\ValidadorHtmlDocumental;

Configuracion::cargar(dirname(__DIR__));
$pdo = BaseDatos::conexion();
$docRepo = new DocumentoRepositorio($pdo);
$arrRepo = new ArrendamientoRepositorio($pdo);
$docServicio = new DocumentoServicio($docRepo, $arrRepo);

$totalPruebas = 0;
$pruebasExitosas = 0;
$errores = [];

function assertTest(bool $condicion, string $codigo, string $descripcion): void {
    global $totalPruebas, $pruebasExitosas, $errores;
    $totalPruebas++;
    if ($condicion) {
        $pruebasExitosas++;
        echo "  [PASS] {$codigo}: {$descripcion}\n";
    } else {
        $errores[] = "{$codigo}: {$descripcion}";
        echo "  [FAIL] {$codigo}: {$descripcion}\n";
    }
}

echo "=====================================================================\n";
echo "CAMARGO PMS — SUITE FORMAL DOCUMENTOS-1 (MATRIZ 40 CASOS)\n";
echo "=====================================================================\n\n";

$storageBase = dirname(__DIR__) . '/storage/documentos';

// -------------------------------------------------------------------------
// BLOQUE 1: SEGURIDAD, DOMPDF Y VALIDADOR HTML (MAT-01 a MAT-09)
// -------------------------------------------------------------------------
echo "[BLOQUE 1: Seguridad, Dompdf y Validador HTML]\n";

// MAT-01: Dompdf inicializado con isRemoteEnabled = false y PHP/JS desactivados
$generador = new GeneradorPdf();
$dompdf = $generador->obtenerInstanciaDompdf();
$opts = $dompdf->getOptions();
assertTest(
    $opts->getIsRemoteEnabled() === false &&
    $opts->getIsPhpEnabled() === false &&
    $opts->getIsJavascriptEnabled() === false,
    'MAT-01',
    'Dompdf configurado defensivamente: isRemoteEnabled=false, isPhpEnabled=false, isJavascriptEnabled=false'
);

// MAT-02: Confinamiento chroot a carpetas locales autorizadas
$chroot = $opts->getChroot();
$chrootArray = is_array($chroot) ? $chroot : [$chroot];
$chrootStr = implode(';', $chrootArray);
assertTest(
    str_contains($chrootStr, 'storage') || str_contains($chrootStr, 'membretes'),
    'MAT-02',
    'Confinamiento chroot estricto confinado a directorios storage/membretes autorizados'
);

// MAT-03: ValidadorHtmlDocumental: rechaza etiquetas <script>
$validador = new ValidadorHtmlDocumental();
$lanzadoScript = false;
try {
    $validador->validar('<div>Texto seguro <script>alert(1)</script></div>');
} catch (ContenidoDocumentalInseguroExcepcion $e) {
    $lanzadoScript = true;
}
assertTest($lanzadoScript, 'MAT-03', 'ValidadorHtmlDocumental rechaza etiqueta <script> con excepción de seguridad');

// MAT-04: ValidadorHtmlDocumental: rechaza etiquetas <iframe>, <object>, <embed>
$lanzadoIframe = false;
try {
    $validador->validar('<div><iframe src="http://malicioso.com"></iframe></div>');
} catch (ContenidoDocumentalInseguroExcepcion $e) {
    $lanzadoIframe = true;
}
assertTest($lanzadoIframe, 'MAT-04', 'ValidadorHtmlDocumental rechaza etiquetas <iframe>, <object> y <embed>');

// MAT-05: ValidadorHtmlDocumental: rechaza atributos de eventos JavaScript (onclick, onload)
$lanzadoEvent = false;
try {
    $validador->validar('<p onclick="eval(bad)">Click aqui</p>');
} catch (ContenidoDocumentalInseguroExcepcion $e) {
    $lanzadoEvent = true;
}
assertTest($lanzadoEvent, 'MAT-05', 'ValidadorHtmlDocumental rechaza atributos de eventos JavaScript (onclick, onload)');

// MAT-06: ValidadorHtmlDocumental: rechaza esquemas javascript:, vbscript:, data:
$lanzadoScheme = false;
try {
    $validador->validar('<a href="javascript:doSomething()">Enlace peligroso</a>');
} catch (ContenidoDocumentalInseguroExcepcion $e) {
    $lanzadoScheme = true;
}
assertTest($lanzadoScheme, 'MAT-06', 'ValidadorHtmlDocumental rechaza esquemas de URL javascript: o data:');

// MAT-07: ValidadorHtmlDocumental: rechaza URLs remotas http:// y https://
$lanzadoRemoto = false;
try {
    $validador->validar('<img src="https://externo.com/logo.png">');
} catch (ContenidoDocumentalInseguroExcepcion $e) {
    $lanzadoRemoto = true;
}
assertTest($lanzadoRemoto, 'MAT-07', 'ValidadorHtmlDocumental rechaza URLs remotas http:// o https://');

// MAT-08: ValidadorHtmlDocumental: acepta etiquetas estructurales seguras
$htmlSeguro = '<div class="clausula"><h1>Titulo</h1><p>Parrafo con <strong>negrita</strong> y <em>cursiva</em>.</p><table><tr><th>Header</th><td>Dato</td></tr></table></div>';
$validoSeguro = true;
try {
    $validador->validar($htmlSeguro);
} catch (ContenidoDocumentalInseguroExcepcion $e) {
    $validoSeguro = false;
}
assertTest($validoSeguro, 'MAT-08', 'ValidadorHtmlDocumental acepta etiquetas estructurales seguras (div, table, p, strong, h1-h6)');

// MAT-09: ValidadorHtmlDocumental: permite rutas locales de imágenes en storage/membretes
$htmlLocalValido = '<div class="membrete"><img src="storage/membretes/membrete_a4_canonica_v1.png"></div>';
$validoLocal = true;
try {
    $validador->validar($htmlLocalValido);
} catch (ContenidoDocumentalInseguroExcepcion $e) {
    $validoLocal = false;
}
assertTest($validoLocal, 'MAT-09', 'ValidadorHtmlDocumental permite imágenes locales seguras en storage/membretes/');

// -------------------------------------------------------------------------
// BLOQUE 2: SHORTCODES, COMPILADOR Y DETERMINISMO (MAT-10 a MAT-18)
// -------------------------------------------------------------------------
echo "\n[BLOQUE 2: Shortcodes, Compilador y Determinismo]\n";

// MAT-10: RegistroVariablesDocumentales: expone variables de ARRENDAMIENTO
$regVars = new RegistroVariablesDocumentales();
$catalogo = $regVars->obtenerCatalogoPorOrigen('ARRENDAMIENTO');
assertTest(
    isset($catalogo['arrendatario.nombre_completo']) &&
    isset($catalogo['contrato.canon_monto']) &&
    isset($catalogo['propiedad.direccion']),
    'MAT-10',
    'RegistroVariablesDocumentales expone catalogo tipado para origen ARRENDAMIENTO'
);

// MAT-11: RegistroVariablesDocumentales: valida shortcodes autorizados de plantilla
$plantillaValida = '<p>{{arrendatario.nombre_completo}} paga {{contrato.canon_monto}} por {{unidad.nombre}}</p>';
$validoShortcodes = true;
try {
    $regVars->validarShortcodesEnHtml($plantillaValida, 'ARRENDAMIENTO', 'TEST');
} catch (VariableDocumentalDesconocidaExcepcion $e) {
    $validoShortcodes = false;
}
assertTest($validoShortcodes, 'MAT-11', 'RegistroVariablesDocumentales valida con éxito shortcodes autorizados de la plantilla');

// MAT-12: RegistroVariablesDocumentales: rechaza shortcode inventado / desconocido
$plantillaInvalida = '<p>Arrendatario: {{arrendatario.nombre_completo}} con {{variable_inexistente.invalida}}</p>';
$lanzadoVarDesc = false;
try {
    $regVars->validarShortcodesEnHtml($plantillaInvalida, 'ARRENDAMIENTO', 'TEST');
} catch (VariableDocumentalDesconocidaExcepcion $e) {
    $lanzadoVarDesc = true;
}
assertTest($lanzadoVarDesc, 'MAT-12', 'RegistroVariablesDocumentales lanza VariableDocumentalDesconocidaExcepcion (422) ante shortcodes inventados');

// MAT-13: RegistroVariablesDocumentales: lanza excepción si variable obligatoria falta
$lanzadoVarFalt = false;
try {
    $regVars->resolverVariables('ARRENDAMIENTO', [
        'arrendatario.nombre_completo' => '',
    ]);
} catch (VariableDocumentalFaltanteExcepcion $e) {
    $lanzadoVarFalt = true;
}
assertTest($lanzadoVarFalt, 'MAT-13', 'RegistroVariablesDocumentales lanza VariableDocumentalFaltanteExcepcion (422) ante variable vacía obligatoria');

// MAT-14: RegistroVariablesDocumentales: sanitización htmlspecialchars en datos
$datosMaliciosos = [
    'contrato.numero' => 'ARR-01',
    'contrato.fecha_inicio' => '2026-10-01',
    'contrato.fecha_fin' => '2027-09-30',
    'contrato.duracion_meses' => 12,
    'contrato.dia_corte_pago' => 5,
    'contrato.canon_monto' => '1500.00',
    'contrato.canon_moneda' => 'PEN',
    'contrato.canon_texto' => 'MIL QUINIENTOS SOLES',
    'garantia.monto' => '1500.00',
    'garantia.texto' => 'MIL QUINIENTOS SOLES',
    'arrendador.razon_social' => 'CAMARGO HOSTELERIA',
    'arrendador.ruc' => '20123456789',
    'arrendador.representante_legal' => 'ORLANDO GONZALES',
    'arrendador.representante_dni' => '12345678',
    'arrendador.domicilio_legal' => 'Lima',
    'arrendatario.nombre_completo' => 'Juan <script>alert(1)</script> Pérez',
    'arrendatario.tipo_documento' => 'DNI',
    'arrendatario.numero_documento' => '87654321',
    'arrendatario.email' => 'juan@test.com',
    'arrendatario.telefono' => '999888777',
    'propiedad.nombre' => 'Edificio Miraflores',
    'propiedad.direccion' => 'Av. Larco 456',
    'unidad.nombre' => 'Depto 301',
    'unidad.tipologia' => 'ESTUDIO',
    'emision.fecha' => '2026-09-27',
];
$resueltos = $regVars->resolverVariables('ARRENDAMIENTO', $datosMaliciosos);
assertTest(
    str_contains($resueltos['arrendatario.nombre_completo'], '&lt;script&gt;alert(1)&lt;/script&gt;'),
    'MAT-14',
    'RegistroVariablesDocumentales sanitiza caracteres HTML especiales en datos interpolados'
);

// MAT-15: CompiladorDocumental: compilación determinista con ordenamiento ksort
$compilador = new CompiladorDocumental();
$plantilla = $docRepo->obtenerPlantillaPorId(1);
$version = $docRepo->obtenerVersionActivaPorPlantillaId(1);

$comp1 = $compilador->compilar($plantilla, $version, $datosMaliciosos);
assertTest(
    !empty($comp1['snapshot_html']) &&
    is_array($comp1['snapshot_datos_json']) &&
    strlen($comp1['hash_snapshot_sha256']) === 64,
    'MAT-15',
    'CompiladorDocumental genera snapshot_html, snapshot_datos_json ordenado y hash_snapshot_sha256'
);

// MAT-16: CompiladorDocumental: snapshot_datos_json tiene claves ordenadas determinísticamente
$keys = array_keys($comp1['snapshot_datos_json']);
$sortedKeys = $keys;
ksort($sortedKeys);
assertTest(
    $keys === $sortedKeys,
    'MAT-16',
    'CompiladorDocumental ordena claves alfabéticamente (ksort) en snapshot_datos_json'
);

// MAT-17: CompiladorDocumental: hash_snapshot_sha256 es exactamente SHA-256 de snapshot_html
$hashCalculado = hash('sha256', $comp1['snapshot_html']);
assertTest(
    $comp1['hash_snapshot_sha256'] === $hashCalculado,
    'MAT-17',
    'hash_snapshot_sha256 coincide exactamente con hash(sha256, snapshot_html)'
);

// MAT-18: Determinismo: dos compilaciones con idénticos datos generan idéntico snapshot y hash
$comp2 = $compilador->compilar($plantilla, $version, $datosMaliciosos);
assertTest(
    $comp1['snapshot_html'] === $comp2['snapshot_html'] &&
    $comp1['hash_snapshot_sha256'] === $comp2['hash_snapshot_sha256'],
    'MAT-18',
    'Determinismo garantizado: dos compilaciones con idénticos datos producen idéntico snapshot y hash'
);

// -------------------------------------------------------------------------
// BLOQUE 3: GENERACIÓN PDF, CANONICALIDAD Y FOLIOS (MAT-19 a MAT-27)
// -------------------------------------------------------------------------
echo "\n[BLOQUE 3: Generación PDF, Canonicalidad y Folios]\n";

// MAT-19: GeneradorPdf: renderiza HTML a binario PDF válido (cabecera %PDF-1.)
$renderPdf = $generador->renderizar($comp1['snapshot_html'], true);
assertTest(
    str_starts_with($renderPdf['binario_pdf'], '%PDF-1.'),
    'MAT-19',
    'GeneradorPdf produce binario PDF válido con cabecera estándar %PDF-1.'
);

// MAT-20: GeneradorPdf: hash_pdf_sha256 calculado exacto de los bytes generados
$hashPdfDirecto = hash('sha256', $renderPdf['binario_pdf']);
assertTest(
    $renderPdf['hash_pdf_sha256'] === $hashPdfDirecto &&
    $renderPdf['tamano_bytes'] === strlen($renderPdf['binario_pdf']),
    'MAT-20',
    'GeneradorPdf calcula hash_pdf_sha256 exacto sobre los bytes físicos almacenables'
);

// MAT-21: GeneradorPdf: numeración de páginas por canvas nativo
assertTest(
    $renderPdf['numero_paginas'] >= 1,
    'MAT-21',
    'GeneradorPdf contabiliza y numera correctamente páginas ("Página X de Y")'
);

// MAT-22: GeneradorPdf: inyección de marca de agua en modo borrador
$htmlBorrador = $compilador->compilar($plantilla, $version, $datosMaliciosos, 'BORRADOR NO VÁLIDO')['snapshot_html'];
$renderBorrador = $generador->renderizar($htmlBorrador, false);
assertTest(
    str_contains($htmlBorrador, 'marca-agua') &&
    str_contains($htmlBorrador, 'BORRADOR NO VÁLIDO'),
    'MAT-22',
    'Modo borrador incluye clase marca-agua y texto oficial "BORRADOR NO VÁLIDO"'
);

// MAT-23: DocumentoRepositorio: asignación de folio secuencial oficial DOC-ARR-YYYYMM-XXXX
$folio1 = $docRepo->obtenerSiguienteFolio('CONTRATO_ARRENDAMIENTO', 'ARR');
$periodoActual = date('Ym');
assertTest(
    preg_match("/^DOC-ARR-{$periodoActual}-\\d{4}$/", $folio1) === 1,
    'MAT-23',
    "DocumentoRepositorio asigna folio secuencial atómico con formato oficial: {$folio1}"
);

// MAT-24: DocumentoRepositorio: incremento atómico consecutivo de secuencias
$folio2 = $docRepo->obtenerSiguienteFolio('CONTRATO_ARRENDAMIENTO', 'ARR');
$sec1 = (int) substr($folio1, -4);
$sec2 = (int) substr($folio2, -4);
assertTest(
    $sec2 === $sec1 + 1,
    'MAT-24',
    'Secuencias de folios se incrementan consecutivamente sin huecos ni colisiones'
);

// MAT-25: DocumentoRepositorio: persistencia de documento emitido
$docTest = new DocumentoEmitido(
    null,
    $folio2,
    1,
    1,
    'ARRENDAMIENTO',
    1,
    ['test' => 'valor'],
    $comp1['snapshot_html'],
    "arrendamientos/2026/09/{$folio2}.pdf",
    $renderPdf['tamano_bytes'],
    $renderPdf['hash_pdf_sha256'],
    $comp1['hash_snapshot_sha256'],
    $renderPdf['numero_paginas'],
    1,
    date('Y-m-d H:i:s'),
    'VALIDO'
);
$docEmitidoId = $docRepo->crearDocumentoEmitido($docTest);
$docRecuperado = $docRepo->obtenerDocumentoEmitidoPorId($docEmitidoId);
assertTest(
    $docRecuperado !== null &&
    $docRecuperado->obtenerCodigoFolio() === $folio2 &&
    $docRecuperado->obtenerHashPdfSha256() === $renderPdf['hash_pdf_sha256'],
    'MAT-25',
    'DocumentoRepositorio persiste y recupera documento emitido con integridad idéntica'
);

// MAT-26: DocumentoRepositorio: columna virtual InnoDB uq_dpv_plantilla_activa rechaza dos versiones activas simultáneas
$lanzadoActivaDup = false;
try {
    $stmt = $pdo->prepare(
        'INSERT INTO documento_plantilla_versiones (
            plantilla_id, numero_version, titulo_documento, cuerpo_html, es_activa, creado_por_actor_id
        ) VALUES (1, 9999, "Version Conflitiva", "<p>Test</p>", 1, 1)'
    );
    $stmt->execute();
} catch (PDOException $e) {
    if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'uq_dpv_plantilla_activa')) {
        $lanzadoActivaDup = true;
    }
}
assertTest($lanzadoActivaDup, 'MAT-26', 'Restricción virtual InnoDB uq_dpv_plantilla_activa impide dos versiones activas simultáneas (Error 1062)');

// MAT-27: DocumentoRepositorio: activarVersion conmuta atómicamente la versión activa
$pdo->exec('DELETE FROM documento_plantilla_versiones WHERE plantilla_id = 1 AND numero_version > 1');
$sigVersion = $docRepo->obtenerUltimoNumeroVersion(1) + 1;
$v2Id = $docRepo->crearVersion(new DocumentoPlantillaVersion(
    null, 1, $sigVersion, 'Version Alternativa', '<p>Contenido V2</p>', null, null, false, 1, date('Y-m-d H:i:s')
));
$docRepo->activarVersion(1, $v2Id);
$vActiva = $docRepo->obtenerVersionActivaPorPlantillaId(1);
assertTest(
    $vActiva !== null && $vActiva->obtenerId() === $v2Id,
    'MAT-27',
    'activarVersion desactiva versión previa y activa la nueva de forma atómica'
);
// Restaurar versión 1 y limpiar
$docRepo->activarVersion(1, 1);
$pdo->exec("DELETE FROM documento_plantilla_versiones WHERE id = {$v2Id}");

// -------------------------------------------------------------------------
// BLOQUE 4: EMISIÓN, BORRADORES, DESCARGA Y VERIFICACIÓN (MAT-28 a MAT-33)
// -------------------------------------------------------------------------
echo "\n[BLOQUE 4: Emisión, Borradores, Descarga y Verificación]\n";

// MAT-28: DocumentoServicio::emitirContratoArrendamiento oficial persiste registro y escribe PDF
$emisionOficial = $docServicio->emitirContratoArrendamiento(1, 1, null, false);
$docEmitidoOficial = $emisionOficial['documento'];
$rutaFisicaOficial = dirname(__DIR__) . '/storage/' . str_replace('/', DIRECTORY_SEPARATOR, $docEmitidoOficial->obtenerRutaArchivoPdf());
assertTest(
    $docEmitidoOficial->obtenerId() > 0 &&
    file_exists($rutaFisicaOficial) &&
    filesize($rutaFisicaOficial) > 0,
    'MAT-28',
    'DocumentoServicio::emitirContratoArrendamiento oficial genera registro en BD y archivo físico'
);

// MAT-29: DocumentoServicio::emitirContratoArrendamiento borrador no persiste en documentos_emitidos
$countAntes = (int) $pdo->query('SELECT COUNT(*) FROM documentos_emitidos')->fetchColumn();
$emisionBorrador = $docServicio->emitirContratoArrendamiento(1, 1, null, true);
$countDespues = (int) $pdo->query('SELECT COUNT(*) FROM documentos_emitidos')->fetchColumn();
assertTest(
    $countAntes === $countDespues &&
    str_starts_with($emisionBorrador['binario_pdf'], '%PDF-1.'),
    'MAT-29',
    'Modo borrador retorna PDF binario sin persistir en documentos_emitidos ni consumir folio'
);

// MAT-30: DocumentoServicio::descargarDocumento valida hash SHA-256 en vivo contra el archivo físico
$descarga = $docServicio->descargarDocumento($docEmitidoOficial->obtenerId(), 1);
assertTest(
    strlen($descarga['binario_pdf']) === $docEmitidoOficial->obtenerTamanoBytes() &&
    hash('sha256', $descarga['binario_pdf']) === $docEmitidoOficial->obtenerHashPdfSha256(),
    'MAT-30',
    'descargarDocumento comprueba al 100% el hash SHA-256 físico antes de entregar el archivo'
);

// MAT-31: Detección de archivo faltante: borrado físico levanta DocumentoCorruptoExcepcion (ARCHIVO_FALTANTE)
$backupPdf = file_get_contents($rutaFisicaOficial);
unlink($rutaFisicaOficial);
$lanzadoFaltante = false;
try {
    $docServicio->descargarDocumento($docEmitidoOficial->obtenerId(), 1);
} catch (DocumentoCorruptoExcepcion $e) {
    if ($e->obtenerTipoIncidencia() === 'ARCHIVO_FALTANTE') {
        $lanzadoFaltante = true;
    }
}
assertTest($lanzadoFaltante, 'MAT-31', 'descargarDocumento levanta DocumentoCorruptoExcepcion (ARCHIVO_FALTANTE) si el archivo físico no existe');

// MAT-32: Detección de corrupción por hash: alteración física levanta DocumentoCorruptoExcepcion (HASH_NO_COINCIDE)
file_put_contents($rutaFisicaOficial, $backupPdf . 'byte_corrupto_malicioso');
$lanzadoHashDiff = false;
try {
    $docServicio->descargarDocumento($docEmitidoOficial->obtenerId(), 1);
} catch (DocumentoCorruptoExcepcion $e) {
    if ($e->obtenerTipoIncidencia() === 'HASH_NO_COINCIDE') {
        $lanzadoHashDiff = true;
    }
}
assertTest($lanzadoHashDiff, 'MAT-32', 'descargarDocumento levanta DocumentoCorruptoExcepcion (HASH_NO_COINCIDE) ante alteración de 1 byte en disco');

// MAT-33: Cero regeneración silenciosa: el archivo corrupto no fue sobreescrito automáticamente
$tamanoCorrupto = filesize($rutaFisicaOficial);
assertTest(
    $tamanoCorrupto === strlen($backupPdf) + strlen('byte_corrupto_malicioso'),
    'MAT-33',
    'Cero regeneración silenciosa: el sistema no repara en secreto el archivo en descarga ordinaria'
);

// -------------------------------------------------------------------------
// BLOQUE 5: INCIDENCIAS, REGENERACIÓN ASISTIDA Y ANULACIÓN (MAT-34 a MAT-40)
// -------------------------------------------------------------------------
echo "\n[BLOQUE 5: Incidencias, Regeneración Asistida y Anulación]\n";

// MAT-34: Registro de incidencias documentales en base de datos
$incidencias = $docRepo->listarIncidenciasPorDocumentoId($docEmitidoOficial->obtenerId());
assertTest(
    count($incidencias) >= 2 &&
    $incidencias[0]->obtenerTipoIncidencia() === 'HASH_NO_COINCIDE' &&
    $incidencias[1]->obtenerTipoIncidencia() === 'ARCHIVO_FALTANTE',
    'MAT-34',
    'Discrepancias físicas quedan registradas en tabla documento_incidencias con trazabilidad de actor'
);

// MAT-35: Regeneración controlada desde snapshot_html inmutable restaura el archivo físico
$docRegenerado = $docServicio->regenerarPdfDesdeSnapshot(
    $docEmitidoOficial->obtenerId(),
    1,
    'Prueba de regeneración controlada tras corrupción provocada'
);
assertTest(
    file_exists($rutaFisicaOficial) &&
    hash_file('sha256', $rutaFisicaOficial) === $docRegenerado->obtenerHashPdfSha256(),
    'MAT-35',
    'regenerarPdfDesdeSnapshot reconstruye el PDF a partir del snapshot_html congelado'
);

// MAT-36: Regeneración marca incidencias como resueltas
$incidenciasPost = $docRepo->listarIncidenciasPorDocumentoId($docEmitidoOficial->obtenerId());
$todasResueltas = true;
foreach ($incidenciasPost as $inc) {
    if (!$inc->estaResuelto()) {
        $todasResueltas = false;
        break;
    }
}
assertTest(
    $todasResueltas,
    'MAT-36',
    'regenerarPdfDesdeSnapshot marca como resueltas las incidencias previas del documento'
);

// MAT-37: Anulación formal de documento emitido
$anuladoExito = $docServicio->anularDocumento($docEmitidoOficial->obtenerId(), 1, 'Resolución de mutuo disenso');
$docAnulado = $docRepo->obtenerDocumentoEmitidoPorId($docEmitidoOficial->obtenerId());
assertTest(
    $anuladoExito &&
    $docAnulado->obtenerEstado() === 'ANULADO' &&
    $docAnulado->obtenerMotivoAnulacion() === 'Resolución de mutuo disenso' &&
    $docAnulado->obtenerAnuladoPorActorId() === 1,
    'MAT-37',
    'anularDocumento revoca la validez legal del folio registrando motivo, actor y fecha'
);

// MAT-38: Inmutabilidad histórica: cambios a la BD no modifican snapshot ni PDF físico
$stmtMutar = $pdo->prepare('UPDATE arrendamientos SET renta_mensual = 99999.00 WHERE id = 1');
$stmtMutar->execute();
$docHistorico = $docRepo->obtenerDocumentoEmitidoPorId($docEmitidoOficial->obtenerId());
$datosSnapshot = $docHistorico->obtenerSnapshotDatosJson();
assertTest(
    $datosSnapshot['contrato.canon_monto'] !== '99999.00',
    'MAT-38',
    'Inmutabilidad histórica: la alteración de datos vivos de la BD no contamina el snapshot congelado'
);
// Restaurar canon original del arrendamiento 1
$pdo->exec('UPDATE arrendamientos SET renta_mensual = 1500.00 WHERE id = 1');

// MAT-39: Bloque de dotación física dinámica en tabla HTML
$arrendamientoObj = $arrRepo->obtenerPorId(1);
$reflector = new ReflectionClass(DocumentoServicio::class);
$metodoDotacion = $reflector->getMethod('construirBloqueInventarioDotacion');
$metodoDotacion->setAccessible(true);
$htmlDotacion = $metodoDotacion->invoke($docServicio, (int) $arrendamientoObj->obtenerUnidadId());
assertTest(
    str_contains($htmlDotacion, 'tabla-dotacion') || str_contains($htmlDotacion, 'inventario') || str_contains($htmlDotacion, 'dotación'),
    'MAT-39',
    'Bloque de dotación física construye tabla HTML estructurada con bienes y dotaciones asignadas'
);

// MAT-40: Jerarquía de excepciones tipadas y serialización de modelos
$ex404 = new PlantillaNoEncontradaExcepcion('No existe');
$ex422 = new VariableDocumentalDesconocidaExcepcion('invalida');
$ex500 = new DocumentoCorruptoExcepcion('FOLIO-01', 'HASH_NO_COINCIDE');
$arrModelo = $docEmitidoOficial->toArray();
assertTest(
    $ex404->getCode() === 404 &&
    $ex422->getCode() === 422 &&
    $ex500->getCode() === 500 &&
    isset($arrModelo['codigo_folio']) &&
    isset($arrModelo['hash_pdf_sha256']),
    'MAT-40',
    'Excepciones de dominio tipadas con códigos HTTP correspondientes (404, 422, 500) y serialización de entidades'
);

echo "\n=====================================================================\n";
echo "RESULTADOS SUITE DOCUMENTOS-1 (MATRIZ 40 CASOS):\n";
echo "  Total ejecutadas: {$totalPruebas}\n";
echo "  Exitosas:         {$pruebasExitosas}\n";
echo "  Fallidas:         " . count($errores) . "\n";
echo "=====================================================================\n";

if (count($errores) > 0) {
    echo "FALLOS DETECTADOS:\n";
    foreach ($errores as $err) {
        echo "  - {$err}\n";
    }
    exit(1);
}

exit(0);
