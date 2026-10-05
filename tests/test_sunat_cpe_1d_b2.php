<?php

declare(strict_types=1);

/**
 * Suite de Pruebas: SUNAT-1D-B2 — Hardening Fiscal de Esquema, Modelos y Persistencia
 *
 * Valida:
 * 1. Conteo exacto de 141 tablas relacionales en MySQL 8.4 LTS.
 * 2. Migración 041_cpe_credito_hospedaje_hardening.sql registrada formalmente.
 * 3. Migración 040_cpe_esquema_fiscal.sql inmutable respecto a origin/main.
 * 4. Ranura de migración 042 estrictamente LIBRE (cero archivos 042).
 * 5. Estructura física completa de 041: cpe_cuotas, cpe_hospedajes, columnas en cpe_comprobantes y cpe_lineas.
 * 6. Composite Foreign Key de protección Cross-CPE: cpe_lineas(cpe_id, cpe_hospedaje_id) -> cpe_hospedajes(cpe_id, id).
 * 7. Modelos de dominio soberanos: CpeCuota, CpeHospedajeFiscal, CpeLinea y CpeComprobante (cero floats).
 * 8. Emisión y persistencia a CONTADO (sin cuotas, sin monto pendiente positivo).
 * 9. Rechazo estricto de CONTADO con cuotas o con monto pendiente.
 * 10. Emisión y persistencia a CRÉDITO (1 cuota, multicuotas con suma exacta BCMath).
 * 11. Rechazo de CRÉDITO sin cuotas o con descuadre en suma de cuotas.
 * 12. Emisión y persistencia Régimen DL 919 Hospedaje: Receptor con RUC != Huésped extranjero con Pasaporte.
 * 13. Validación de fecha_consumo de línea dentro del rango de estancia [checkin, checkout].
 * 14. Rechazo de línea de hospedaje con fecha_consumo fuera de rango o sin vínculo a hospedaje.
 * 15. Restricciones CHECK en motor: permanencia <= 60 días y checkout >= checkin.
 * 16. Restricciones UNIQUE en motor: uq_cpe_cuota_numero y uq_cpe_hospedaje_orden.
 * 17. Protección Cross-CPE física (InnoDB 1452) y lógica en CpeCorrelativoServicio.
 * 18. Limpieza defensiva y comprobación de cero residuos en base de datos.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use CamargoPMS\Excepciones\CpeExcepcion;
use CamargoPMS\Excepciones\ValidacionFiscalExcepcion;
use CamargoPMS\Modelos\CPE\CpeComprobante;
use CamargoPMS\Modelos\CPE\CpeCuota;
use CamargoPMS\Modelos\CPE\CpeEstablecimiento;
use CamargoPMS\Modelos\CPE\CpeHospedajeFiscal;
use CamargoPMS\Modelos\CPE\CpeLinea;
use CamargoPMS\Modelos\CPE\CpeSerie;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Repositorios\CPE\CpeComprobanteRepositorio;
use CamargoPMS\Repositorios\CPE\CpeEstablecimientoRepositorio;
use CamargoPMS\Repositorios\CPE\CpeSerieRepositorio;
use CamargoPMS\Servicios\CPE\CpeCorrelativoServicio;

Configuracion::cargar(dirname(__DIR__));
$pdo = BaseDatos::conexion();

$totalChecks  = 0;
$passedChecks = 0;
$failedChecks = 0;

function verificar(string $descripcion, bool $condicion): void
{
    global $totalChecks, $passedChecks, $failedChecks;
    $totalChecks++;
    if ($condicion) {
        $passedChecks++;
        echo "  [PASS] {$descripcion}\n";
    } else {
        $failedChecks++;
        echo "  [FAIL] {$descripcion}\n";
    }
}

echo "====================================================================\n";
echo " EJECUTANDO SUITE: test_sunat_cpe_1d_b2.php (SUNAT-1D-B2)\n";
echo "====================================================================\n\n";

// --------------------------------------------------------------------
// BLOQUE 1: Gobernanza, Motor Relacional y Esquema 041
// --------------------------------------------------------------------
echo "--- BLOQUE 1: Gobernanza, Motor Relacional y Esquema 041 ---\n";

$totalTablas = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'")->fetchColumn();
verificar("Total de tablas relacionales en MySQL es exactamente 141 (actual: {$totalTablas})", $totalTablas === 141);

$migracion040Registrada = (int) $pdo->query("SELECT COUNT(*) FROM migraciones WHERE migracion = '040_cpe_esquema_fiscal.sql'")->fetchColumn();
verificar("Migración 040_cpe_esquema_fiscal.sql registrada formalmente en tabla técnica 'migraciones'", $migracion040Registrada === 1);

$migracion041Registrada = (int) $pdo->query("SELECT COUNT(*) FROM migraciones WHERE migracion = '041_cpe_credito_hospedaje_hardening.sql'")->fetchColumn();
verificar("Migración 041_cpe_credito_hospedaje_hardening.sql registrada formalmente en tabla técnica 'migraciones'", $migracion041Registrada === 1);

$diff040 = trim(shell_exec('git diff --name-only origin/main -- SQL/migraciones/040_cpe_esquema_fiscal.sql') ?? '');
verificar("Migración 040_cpe_esquema_fiscal.sql permanece 100% inmutable respecto a origin/main", empty($diff040));

$archivos042 = glob(dirname(__DIR__) . '/SQL/migraciones/*042*');
verificar("Ranura de migración 042 estrictamente LIBRE (cero archivos 042)", empty($archivos042));

$archivoSqlCamargo = dirname(__DIR__) . '/SQL/camargo_pms.sql';
$sqlCamargoContenido = file_get_contents($archivoSqlCamargo);
verificar("SQL/camargo_pms.sql contiene definición sincronizada de cpe_cuotas", str_contains($sqlCamargoContenido, 'cpe_cuotas'));
verificar("SQL/camargo_pms.sql contiene definición sincronizada de cpe_hospedajes", str_contains($sqlCamargoContenido, 'cpe_hospedajes'));
verificar("SQL/camargo_pms.sql contiene campo forma_pago", str_contains($sqlCamargoContenido, 'forma_pago'));
verificar("SQL/camargo_pms.sql contiene composite FK fk_cpe_lineas_hospedaje_cross", str_contains($sqlCamargoContenido, 'fk_cpe_lineas_hospedaje_cross'));

$diffAdmin = trim(shell_exec('git status --porcelain admin-dashboard/') ?? '');
verificar("Catálogo admin-dashboard/ permanece 100% intacto y limpio", empty($diffAdmin));

// --------------------------------------------------------------------
// BLOQUE 2: Integridad Estructural y Restricciones Físicas de Migración 041
// --------------------------------------------------------------------
echo "\n--- BLOQUE 2: Integridad Estructural y Restricciones Físicas de Migración 041 ---\n";

$tablaCuotasExiste = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'cpe_cuotas'")->fetchColumn();
verificar("Tabla cpe_cuotas existe físicamente en InnoDB", $tablaCuotasExiste === 1);

$tablaHospedajesExiste = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'cpe_hospedajes'")->fetchColumn();
verificar("Tabla cpe_hospedajes existe físicamente en InnoDB", $tablaHospedajesExiste === 1);

// Verificar columnas en cpe_comprobantes
$colsComprobantes = $pdo->query("SHOW COLUMNS FROM cpe_comprobantes")->fetchAll(PDO::FETCH_COLUMN);
verificar("Columna cpe_comprobantes.forma_pago existe en BD", in_array('forma_pago', $colsComprobantes, true));
verificar("Columna cpe_comprobantes.monto_neto_pendiente existe en BD", in_array('monto_neto_pendiente', $colsComprobantes, true));

// Verificar columnas en cpe_lineas
$colsLineas = $pdo->query("SHOW COLUMNS FROM cpe_lineas")->fetchAll(PDO::FETCH_COLUMN);
verificar("Columna cpe_lineas.cpe_hospedaje_id existe en BD", in_array('cpe_hospedaje_id', $colsLineas, true));
verificar("Columna cpe_lineas.fecha_consumo existe en BD", in_array('fecha_consumo', $colsLineas, true));

// Verificar Foreign Keys
$fksStmt = $pdo->prepare(
    "SELECT CONSTRAINT_NAME, TABLE_NAME, REFERENCED_TABLE_NAME, DELETE_RULE
     FROM information_schema.REFERENTIAL_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME IN ('fk_cpe_cuotas_cpe', 'fk_cpe_hospedajes_cpe', 'fk_cpe_lineas_hospedaje_cross')"
);
$fksStmt->execute();
$fks = $fksStmt->fetchAll();
$fkNames = array_column($fks, 'DELETE_RULE', 'CONSTRAINT_NAME');

verificar("FK fk_cpe_cuotas_cpe configurada con ON DELETE RESTRICT", ($fkNames['fk_cpe_cuotas_cpe'] ?? '') === 'RESTRICT');
verificar("FK fk_cpe_hospedajes_cpe configurada con ON DELETE RESTRICT", ($fkNames['fk_cpe_hospedajes_cpe'] ?? '') === 'RESTRICT');
verificar("Composite FK fk_cpe_lineas_hospedaje_cross configurada con ON DELETE RESTRICT", ($fkNames['fk_cpe_lineas_hospedaje_cross'] ?? '') === 'RESTRICT');

// Verificar UNIQUE Keys
$uniquesStmt = $pdo->prepare(
    "SELECT INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX
     FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND INDEX_NAME IN ('uq_cpe_cuota_numero', 'uq_cpe_hospedaje_orden', 'uq_cpe_hospedaje_cpe_id')
     ORDER BY INDEX_NAME, SEQ_IN_INDEX"
);
$uniquesStmt->execute();
$uniqueCols = [];
foreach ($uniquesStmt->fetchAll() as $row) {
    $uniqueCols[$row['INDEX_NAME']][] = $row['COLUMN_NAME'];
}
verificar("UNIQUE uq_cpe_cuota_numero está compuesta por (cpe_id, numero_cuota)", ($uniqueCols['uq_cpe_cuota_numero'] ?? []) === ['cpe_id', 'numero_cuota']);
verificar("UNIQUE uq_cpe_hospedaje_orden está compuesta por (cpe_id, numero_orden)", ($uniqueCols['uq_cpe_hospedaje_orden'] ?? []) === ['cpe_id', 'numero_orden']);
verificar("UNIQUE uq_cpe_hospedaje_cpe_id está compuesta por (cpe_id, id)", ($uniqueCols['uq_cpe_hospedaje_cpe_id'] ?? []) === ['cpe_id', 'id']);

// Ausencia de FLOAT o DOUBLE en nuevas tablas
$floats = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('cpe_cuotas', 'cpe_hospedajes')
     AND DATA_TYPE IN ('float', 'double')"
)->fetchColumn();
verificar("Ausencia absoluta de FLOAT/DOUBLE en cpe_cuotas y cpe_hospedajes (detectados: {$floats})", $floats === 0);

// --------------------------------------------------------------------
// BLOQUE 3: Modelos de Dominio Soberanos (Cero Floats)
// --------------------------------------------------------------------
echo "\n--- BLOQUE 3: Modelos de Dominio Soberanos (Cero Floats) ---\n";

$cuota1 = new CpeCuota(
    id: null,
    cpeId: null,
    numeroCuota: 1,
    monto: '150.50',
    fechaVencimiento: '2026-11-15'
);
verificar("CpeCuota instancia correctamente", $cuota1 instanceof CpeCuota);
verificar("CpeCuota::obtenerCodigoCuota() retorna 'Cuota001'", $cuota1->obtenerCodigoCuota() === 'Cuota001');
verificar("CpeCuota monto almacenado como string exacto ('150.50')", is_string($cuota1->obtenerMonto()) && $cuota1->obtenerMonto() === '150.50');
$arrCuota = $cuota1->haciaArreglo();
$cuotaReconstruida = CpeCuota::desdeArreglo($arrCuota);
verificar("CpeCuota::desdeArreglo reconstruye idénticamente", $cuotaReconstruida->obtenerNumeroCuota() === 1 && $cuotaReconstruida->obtenerMonto() === '150.50');

// Validaciones de dominio en CpeCuota
$cuotaNumInvalido = false;
try {
    new CpeCuota(id: null, cpeId: null, numeroCuota: 0, monto: '50.00', fechaVencimiento: '2026-11-01');
} catch (Throwable $e) {
    $cuotaNumInvalido = true;
}
verificar("CpeCuota rechaza numeroCuota <= 0", $cuotaNumInvalido);

$cuotaMontoInvalido = false;
try {
    new CpeCuota(id: null, cpeId: null, numeroCuota: 1, monto: '-10.00', fechaVencimiento: '2026-11-01');
} catch (Throwable $e) {
    $cuotaMontoInvalido = true;
}
verificar("CpeCuota rechaza monto <= 0.00", $cuotaMontoInvalido);

$cuotaExcedeLimite = false;
try {
    new CpeCuota(id: null, cpeId: null, numeroCuota: 1000, monto: '50.00', fechaVencimiento: '2026-11-01');
} catch (ValidacionFiscalExcepcion $e) {
    $cuotaExcedeLimite = true;
}
verificar("CpeCuota rechaza numeroCuota > 999 (ej. 1000)", $cuotaExcedeLimite);

// Modelo CpeHospedajeFiscal
$hospedaje1 = new CpeHospedajeFiscal(
    id: null,
    cpeId: null,
    numeroOrden: 1,
    huespedNombreCompleto: 'John Doe International Guest',
    huespedTipoDocumento: 'PAS',
    huespedNumeroDocumento: 'A12345678',
    huespedPaisEmisionPasaporte: 'US',
    huespedPaisResidencia: 'US',
    fechaCheckin: '2026-10-01',
    fechaCheckout: '2026-10-15',
    diasPermanencia: 14,
    fechaIngresoPais: '2026-09-28',
    fechaConsumo: null,
    paqueteTuristicoDocumento: null,
    tamVirtualNumero: 'TAM-99887766'
);
verificar("CpeHospedajeFiscal instancia correctamente", $hospedaje1 instanceof CpeHospedajeFiscal);
verificar("CpeHospedajeFiscal Catálogo 55 código 4000 presente ('PAS' normalizado a '7')", in_array($hospedaje1->obtenerHuespedTipoDocumento(), ['7', 'PAS'], true));
verificar("CpeHospedajeFiscal código ISO de país emisión 'US' validado", $hospedaje1->obtenerHuespedPaisEmisionPasaporte() === 'US');
verificar("CpeHospedajeFiscal permanencia de 14 días registrada", $hospedaje1->obtenerDiasPermanencia() === 14);

$arrHosp = $hospedaje1->haciaArreglo();
$hospReconstruido = CpeHospedajeFiscal::desdeArreglo($arrHosp);
verificar("CpeHospedajeFiscal::desdeArreglo reconstruye idénticamente", $hospReconstruido->obtenerHuespedNumeroDocumento() === 'A12345678');

$hospPaisesDistintos = new CpeHospedajeFiscal(
    id: null, cpeId: null, numeroOrden: 2,
    huespedNombreCompleto: 'Jean-Pierre Rossi', huespedTipoDocumento: 'PAS',
    huespedNumeroDocumento: 'F12345678', huespedPaisEmisionPasaporte: 'FR', huespedPaisResidencia: 'IT',
    fechaCheckin: '2026-10-01', fechaCheckout: '2026-10-05', diasPermanencia: 4,
    fechaIngresoPais: '2026-09-29'
);
verificar("CpeHospedajeFiscal admite paises distintos (emisión='FR', residencia='IT')", $hospPaisesDistintos->obtenerHuespedPaisEmisionPasaporte() === 'FR' && $hospPaisesDistintos->obtenerHuespedPaisResidencia() === 'IT');

$hospPermanenciaIndependiente = new CpeHospedajeFiscal(
    id: null, cpeId: null, numeroOrden: 3,
    huespedNombreCompleto: 'Carlos Explorer', huespedTipoDocumento: 'PAS',
    huespedNumeroDocumento: 'E98765432', huespedPaisEmisionPasaporte: 'ES', huespedPaisResidencia: 'ES',
    fechaCheckin: '2026-10-01', fechaCheckout: '2026-10-04', diasPermanencia: 25,
    fechaIngresoPais: '2026-09-10'
);
verificar("CpeHospedajeFiscal preserva permanencia en Perú (25 días) independiente de estancia (3 noches)", $hospPermanenciaIndependiente->obtenerDiasPermanencia() === 25);

// Validaciones de dominio en CpeHospedajeFiscal
$hospPermanenciaInvalida = false;
try {
    new CpeHospedajeFiscal(
        id: null, cpeId: null, numeroOrden: 1,
        huespedNombreCompleto: 'Long Stay Guest', huespedTipoDocumento: 'PAS',
        huespedNumeroDocumento: 'B998877', huespedPaisEmisionPasaporte: 'US', huespedPaisResidencia: 'US',
        fechaCheckin: '2026-01-01', fechaCheckout: '2026-03-15', diasPermanencia: 73,
        fechaIngresoPais: '2026-01-01'
    );
} catch (Throwable $e) {
    $hospPermanenciaInvalida = true;
}
verificar("CpeHospedajeFiscal rechaza diasPermanencia > 60", $hospPermanenciaInvalida);

$hospFechasInvertidas = false;
try {
    new CpeHospedajeFiscal(
        id: null, cpeId: null, numeroOrden: 1,
        huespedNombreCompleto: 'Time Traveler Guest', huespedTipoDocumento: 'PAS',
        huespedNumeroDocumento: 'C112233', huespedPaisEmisionPasaporte: 'US', huespedPaisResidencia: 'US',
        fechaCheckin: '2026-10-15', fechaCheckout: '2026-10-01', diasPermanencia: 5,
        fechaIngresoPais: '2026-09-30'
    );
} catch (Throwable $e) {
    $hospFechasInvertidas = true;
}
verificar("CpeHospedajeFiscal rechaza fechaCheckout < fechaCheckin", $hospFechasInvertidas);

// Modelo CpeLinea con campos nuevos
$lineaHospedaje = new CpeLinea(
    id: null,
    cpeId: null,
    numeroOrden: 1,
    codigoProductoInterno: 'HAB-DELUXE',
    codigoProductoSunat: '90111501',
    descripcion: 'Alojamiento Habitación Deluxe Suite DL 919',
    unidadMedida: 'ZZ',
    cantidad: '5.0000',
    valorUnitario: '100.0000',
    precioUnitario: '100.0000',
    descuentoMonto: '0.00',
    baseImponible: '500.00',
    tipoAfectacionIgv: '40',
    tasaIgv: '0.00',
    montoIgv: '0.00',
    totalLinea: '500.00',
    cpeHospedajeId: null,
    fechaConsumo: '2026-10-05',
    creadoEn: null,
    cargosAtribuidos: []
);
verificar("CpeLinea::esBeneficioHospedaje() detecta tipo 40", $lineaHospedaje->esBeneficioHospedaje() === true);
verificar("CpeLinea::obtenerFechaConsumo() retorna '2026-10-05'", $lineaHospedaje->obtenerFechaConsumo() === '2026-10-05');

// --------------------------------------------------------------------
// PREPARACIÓN DE FIXTURES DE PRUEBA
// --------------------------------------------------------------------
$serieRepo = new CpeSerieRepositorio($pdo);
$estabRepo = new CpeEstablecimientoRepositorio($pdo);
$comprobanteRepo = new CpeComprobanteRepositorio($pdo);
$correlativoServicio = new CpeCorrelativoServicio($pdo, $serieRepo, $comprobanteRepo, $estabRepo);

$uidPrueba = bin2hex(random_bytes(4));
$usuarioIdPrueba = (int) $pdo->query("SELECT id FROM usuarios LIMIT 1")->fetchColumn() ?: 1;
$tipoDocRucTest = (int) $pdo->query("SELECT id FROM tipos_documento WHERE codigo IN ('RUC', 'DNI') LIMIT 1")->fetchColumn() ?: 1;
$rucPrueba = sprintf('20%09d', random_int(100000000, 999999999));

// Limpieza preventiva respetando jerarquía de integridad referencial
$pdo->exec("DELETE lc FROM cpe_linea_cargos lc INNER JOIN cpe_lineas l ON lc.cpe_linea_id = l.id INNER JOIN cpe_comprobantes c ON l.cpe_id = c.id INNER JOIN cpe_series s ON c.serie_id = s.id WHERE s.descripcion LIKE '%1D-B2%'");
$pdo->exec("DELETE l FROM cpe_lineas l INNER JOIN cpe_comprobantes c ON l.cpe_id = c.id INNER JOIN cpe_series s ON c.serie_id = s.id WHERE s.descripcion LIKE '%1D-B2%'");
$pdo->exec("DELETE q FROM cpe_cuotas q INNER JOIN cpe_comprobantes c ON q.cpe_id = c.id INNER JOIN cpe_series s ON c.serie_id = s.id WHERE s.descripcion LIKE '%1D-B2%'");
$pdo->exec("DELETE h FROM cpe_hospedajes h INNER JOIN cpe_comprobantes c ON h.cpe_id = c.id INNER JOIN cpe_series s ON c.serie_id = s.id WHERE s.descripcion LIKE '%1D-B2%'");
$pdo->exec("DELETE c FROM cpe_comprobantes c INNER JOIN cpe_series s ON c.serie_id = s.id WHERE s.descripcion LIKE '%1D-B2%'");
$pdo->exec("DELETE FROM cpe_series WHERE descripcion LIKE '%1D-B2%'");
$pdo->exec("DELETE FROM cpe_establecimientos_configuracion WHERE razon_social_snapshot LIKE '%1D-B2%'");
$pdo->exec("DELETE FROM empresas WHERE codigo LIKE 'EMP_1DB2_%'");

// Insertar empresa
$pdo->prepare(
    "INSERT INTO empresas (codigo, tipo_documento_id, numero_documento, razon_social, direccion_fiscal, pais_id)
     VALUES (:cod, :tipo_doc, :num_doc, :razon, 'Av. Sol 500, Cusco', 1)"
)->execute([
    ':cod' => "EMP_1DB2_{$uidPrueba}",
    ':tipo_doc' => $tipoDocRucTest,
    ':num_doc' => $rucPrueba,
    ':razon' => "Empresa Hotelera Cusco 1D-B2 {$uidPrueba}",
]);
$empresaPruebaId = (int) $pdo->lastInsertId();

// Insertar establecimiento
$estabPrueba = new CpeEstablecimiento(
    null,
    $empresaPruebaId,
    null,
    '0000',
    "Empresa Hotelera Cusco 1D-B2 {$uidPrueba}",
    'Cusco Grand Palace',
    'Av. Sol 500, Cusco',
    '080101',
    'Cusco',
    'Cusco',
    'Cusco',
    'BETA',
    'SUNAT_SOAP_DIRECTO',
    'ACTIVO'
);
$estabId = $estabRepo->crear($estabPrueba);

// Insertar series F001 (Factura) y B001 (Boleta)
$serieF001 = new CpeSerie(
    null,
    $estabId,
    'FACTURA',
    'F001',
    0,
    'F',
    "Serie F001 1D-B2 {$uidPrueba}",
    'ACTIVO'
);
$serieIdF001 = $serieRepo->crear($serieF001);

$serieB001 = new CpeSerie(
    null,
    $estabId,
    'BOLETA',
    'B001',
    0,
    'B',
    "Serie B001 1D-B2 {$uidPrueba}",
    'ACTIVO'
);
$serieIdB001 = $serieRepo->crear($serieB001);

// Helper para armar comprobante base
function fabricarComprobanteBase(int $empresaId, int $estabId, int $serieId, string $tipoDoc, string $serie, string $uid): CpeComprobante
{
    $linea = new CpeLinea(
        null,
        null,
        1,
        'SRV-CONF',
        '90111501',
        'Alquiler Salón de Conferencias',
        'ZZ',
        '1.0000',
        '100.0000',
        '118.0000',
        '0.00',
        '100.00',
        '10',
        '18.00',
        '18.00',
        '118.00',
        null,
        null,
        null,
        []
    );

    return new CpeComprobante(
        null,
        $estabId,
        $serieId,
        null,
        $tipoDoc,
        $serie,
        0,
        '',
        "IDEM_1DB2_{$uid}",
        '20123456789',
        'HOTEL TEST S.A.C.',
        'Hotel Test',
        'Av. Test 123',
        '080101',
        '0000',
        'Cusco',
        'Cusco',
        'Cusco',
        '6',
        '20999888777',
        'AGENCIA DE VIAJES CORPORATIVA S.A.C.',
        'Av. Corporativa 456, Lima',
        '150101',
        'facturacion@agenciatest.com',
        'PE',
        false,
        null,
        null,
        null,
        null,
        'PEN',
        null,
        '100.00',
        '0.00',
        '0.00',
        '0.00',
        '0.00',
        '18.00',
        '0.00',
        '118.00',
        'BORRADOR',
        'NO_INICIADO',
        'PENDIENTE_ENVIO',
        'ORIGINAL',
        null,
        null,
        null,
        null,
        null,
        date('Y-m-d H:i:s'),
        null,
        1,
        null,
        null,
        [$linea],
        [],
        'CONTADO',
        null,
        [],
        []
    );
}

// --------------------------------------------------------------------
// BLOQUE 4: Emisión CONTADO — Reglas e Invariantes
// --------------------------------------------------------------------
echo "\n--- BLOQUE 4: Emisión CONTADO — Reglas e Invariantes ---\n";

$cpeContado = fabricarComprobanteBase($empresaPruebaId, $estabId, $serieIdF001, 'FACTURA', 'F001', 'CONTADO_OK_' . $uidPrueba);
$cpeContadoEmitido = $correlativoServicio->asignarCorrelativoYEmitir($cpeContado);

verificar("Comprobante CONTADO emitido con ID válido", $cpeContadoEmitido->obtenerId() !== null);
verificar("Comprobante CONTADO asignó correlativo 1", $cpeContadoEmitido->obtenerCorrelativo() === 1);
verificar("Comprobante CONTADO asigna forma_pago 'CONTADO' explícitamente por dominio", $cpeContado->obtenerFormaPago() === 'CONTADO');
verificar("Comprobante CONTADO tiene forma_pago 'CONTADO'", $cpeContadoEmitido->obtenerFormaPago() === 'CONTADO');
verificar("Comprobante CONTADO tiene monto_neto_pendiente NULL", $cpeContadoEmitido->obtenerMontoNetoPendiente() === null);
verificar("Comprobante CONTADO tiene lista de cuotas vacía", empty($cpeContadoEmitido->obtenerCuotas()));

// Rehidratación desde BD
$cpeContadoRecuperado = $comprobanteRepo->obtenerPorId($cpeContadoEmitido->obtenerId());
verificar("Rehidratación: forma_pago 'CONTADO' recuperado de BD", $cpeContadoRecuperado->obtenerFormaPago() === 'CONTADO');
verificar("Rehidratación: monto_neto_pendiente NULL en BD", $cpeContadoRecuperado->obtenerMontoNetoPendiente() === null);
verificar("Rehidratación: cuotas vacías en BD", count($cpeContadoRecuperado->obtenerCuotas()) === 0);

// Invariante: Rechazar CONTADO si trae cuotas
$cpeContadoConCuotas = fabricarComprobanteBase($empresaPruebaId, $estabId, $serieIdF001, 'FACTURA', 'F001', 'CONTADO_FAIL_CUOTAS_' . $uidPrueba);
$cpeContadoConCuotas->establecerCuotas([
    new CpeCuota(id: null, cpeId: null, numeroCuota: 1, monto: '118.00', fechaVencimiento: '2026-11-01')
]);
$falloContadoCuotas = false;
try {
    $correlativoServicio->asignarCorrelativoYEmitir($cpeContadoConCuotas);
} catch (ValidacionFiscalExcepcion $e) {
    $falloContadoCuotas = true;
}
verificar("Rechazo estricto: CONTADO con cuotas lanza ValidacionFiscalExcepcion", $falloContadoCuotas);

// Invariante: Rechazar CONTADO si tiene monto neto pendiente positivo
$cpeContadoConMonto = fabricarComprobanteBase($empresaPruebaId, $estabId, $serieIdF001, 'FACTURA', 'F001', 'CONTADO_FAIL_MONTO_' . $uidPrueba);
$cpeContadoConMonto->establecerMontoNetoPendiente('50.00');
$falloContadoMonto = false;
try {
    $correlativoServicio->asignarCorrelativoYEmitir($cpeContadoConMonto);
} catch (ValidacionFiscalExcepcion $e) {
    $falloContadoMonto = true;
}
verificar("Rechazo estricto: CONTADO con monto pendiente positivo lanza ValidacionFiscalExcepcion", $falloContadoMonto);

// --------------------------------------------------------------------
// BLOQUE 5: Emisión CRÉDITO — Cuotas, Validaciones BCMath y Multicuota
// --------------------------------------------------------------------
echo "\n--- BLOQUE 5: Emisión CRÉDITO — Cuotas, Validaciones BCMath y Multicuota ---\n";

// Invariante: Rechazar CRÉDITO sin cuotas
$cpeCreditoSinCuotas = fabricarComprobanteBase($empresaPruebaId, $estabId, $serieIdF001, 'FACTURA', 'F001', 'CRED_FAIL_NOCUOTAS_' . $uidPrueba);
$cpeCreditoSinCuotas->establecerFormaPago('CREDITO');
$cpeCreditoSinCuotas->establecerMontoNetoPendiente('118.00');
$cpeCreditoSinCuotas->establecerCuotas([]);
$falloCreditoSinCuotas = false;
try {
    $correlativoServicio->asignarCorrelativoYEmitir($cpeCreditoSinCuotas);
} catch (ValidacionFiscalExcepcion $e) {
    $falloCreditoSinCuotas = true;
}
verificar("Rechazo estricto: CRÉDITO sin cuotas lanza ValidacionFiscalExcepcion", $falloCreditoSinCuotas);

// Invariante: Rechazar CRÉDITO con monto neto pendiente nulo o cero
$cpeCreditoSinMonto = fabricarComprobanteBase($empresaPruebaId, $estabId, $serieIdF001, 'FACTURA', 'F001', 'CRED_FAIL_NOMONTO_' . $uidPrueba);
$cpeCreditoSinMonto->establecerFormaPago('CREDITO');
$cpeCreditoSinMonto->establecerMontoNetoPendiente(null);
$cpeCreditoSinMonto->establecerCuotas([
    new CpeCuota(id: null, cpeId: null, numeroCuota: 1, monto: '118.00', fechaVencimiento: '2026-11-01')
]);
$falloCreditoSinMonto = false;
try {
    $correlativoServicio->asignarCorrelativoYEmitir($cpeCreditoSinMonto);
} catch (ValidacionFiscalExcepcion $e) {
    $falloCreditoSinMonto = true;
}
verificar("Rechazo estricto: CRÉDITO con monto pendiente NULL lanza ValidacionFiscalExcepcion", $falloCreditoSinMonto);

// Invariante: Rechazar CRÉDITO con monto neto pendiente = '0.00'
$cpeCreditoCero = fabricarComprobanteBase($empresaPruebaId, $estabId, $serieIdF001, 'FACTURA', 'F001', 'CRED_FAIL_CERO_' . $uidPrueba);
$cpeCreditoCero->establecerFormaPago('CREDITO');
$cpeCreditoCero->establecerMontoNetoPendiente('0.00');
$cpeCreditoCero->establecerCuotas([
    new CpeCuota(id: null, cpeId: null, numeroCuota: 1, monto: '118.00', fechaVencimiento: '2026-11-01')
]);
$falloCreditoCero = false;
try {
    $correlativoServicio->asignarCorrelativoYEmitir($cpeCreditoCero);
} catch (ValidacionFiscalExcepcion $e) {
    $falloCreditoCero = true;
}
verificar("Rechazo estricto: CRÉDITO con monto pendiente '0.00' lanza ValidacionFiscalExcepcion", $falloCreditoCero);

// Invariante: Rechazar CRÉDITO con monto neto pendiente negativo '-10.00'
$cpeCreditoNeg = fabricarComprobanteBase($empresaPruebaId, $estabId, $serieIdF001, 'FACTURA', 'F001', 'CRED_FAIL_NEG_' . $uidPrueba);
$cpeCreditoNeg->establecerFormaPago('CREDITO');
$cpeCreditoNeg->establecerMontoNetoPendiente('-10.00');
$cpeCreditoNeg->establecerCuotas([
    new CpeCuota(id: null, cpeId: null, numeroCuota: 1, monto: '118.00', fechaVencimiento: '2026-11-01')
]);
$falloCreditoNeg = false;
try {
    $correlativoServicio->asignarCorrelativoYEmitir($cpeCreditoNeg);
} catch (ValidacionFiscalExcepcion $e) {
    $falloCreditoNeg = true;
}
verificar("Rechazo estricto: CRÉDITO con monto pendiente negativo lanza ValidacionFiscalExcepcion", $falloCreditoNeg);

// Invariante: Rechazar CRÉDITO cuando suma de cuotas descuadra por defecto (117.99 vs 118.00)
$cpeCreditoDescuadre = fabricarComprobanteBase($empresaPruebaId, $estabId, $serieIdF001, 'FACTURA', 'F001', 'CRED_FAIL_DIFF_' . $uidPrueba);
$cpeCreditoDescuadre->establecerFormaPago('CREDITO');
$cpeCreditoDescuadre->establecerMontoNetoPendiente('118.00');
$cpeCreditoDescuadre->establecerCuotas([
    new CpeCuota(id: null, cpeId: null, numeroCuota: 1, monto: '117.99', fechaVencimiento: '2026-11-01')
]);
$falloCreditoDescuadre = false;
try {
    $correlativoServicio->asignarCorrelativoYEmitir($cpeCreditoDescuadre);
} catch (ValidacionFiscalExcepcion $e) {
    $falloCreditoDescuadre = true;
}
verificar("Rechazo estricto: suma de cuotas menor a monto pendiente (descuadre 0.01) lanza ValidacionFiscalExcepcion", $falloCreditoDescuadre);

// Invariante: Rechazar CRÉDITO cuando suma de cuotas excede monto pendiente (118.01 vs 118.00)
$cpeCreditoExceso = fabricarComprobanteBase($empresaPruebaId, $estabId, $serieIdF001, 'FACTURA', 'F001', 'CRED_FAIL_EXCESS_' . $uidPrueba);
$cpeCreditoExceso->establecerFormaPago('CREDITO');
$cpeCreditoExceso->establecerMontoNetoPendiente('118.00');
$cpeCreditoExceso->establecerCuotas([
    new CpeCuota(id: null, cpeId: null, numeroCuota: 1, monto: '118.01', fechaVencimiento: '2026-11-01')
]);
$falloCreditoExceso = false;
try {
    $correlativoServicio->asignarCorrelativoYEmitir($cpeCreditoExceso);
} catch (ValidacionFiscalExcepcion $e) {
    $falloCreditoExceso = true;
}
verificar("Rechazo estricto: suma de cuotas mayor a monto pendiente (118.01 vs 118.00) lanza ValidacionFiscalExcepcion", $falloCreditoExceso);

// Invariante: Rechazar CRÉDITO con número de cuota duplicado en colección
$cpeCreditoDup = fabricarComprobanteBase($empresaPruebaId, $estabId, $serieIdF001, 'FACTURA', 'F001', 'CRED_FAIL_DUPCUOTA_' . $uidPrueba);
$cpeCreditoDup->establecerFormaPago('CREDITO');
$cpeCreditoDup->establecerMontoNetoPendiente('118.00');
$cpeCreditoDup->establecerCuotas([
    new CpeCuota(id: null, cpeId: null, numeroCuota: 1, monto: '59.00', fechaVencimiento: '2026-11-01'),
    new CpeCuota(id: null, cpeId: null, numeroCuota: 1, monto: '59.00', fechaVencimiento: '2026-12-01'),
]);
$falloCreditoDup = false;
try {
    $correlativoServicio->asignarCorrelativoYEmitir($cpeCreditoDup);
} catch (ValidacionFiscalExcepcion $e) {
    $falloCreditoDup = true;
}
verificar("Rechazo estricto: número de cuota duplicado en colección lanza ValidacionFiscalExcepcion", $falloCreditoDup);

// Emisión exitosa de CRÉDITO con 1 cuota
$cpeCreditoOk = fabricarComprobanteBase($empresaPruebaId, $estabId, $serieIdF001, 'FACTURA', 'F001', 'CRED_OK_1CUOTA_' . $uidPrueba);
$cpeCreditoOk->establecerFormaPago('CREDITO');
$cpeCreditoOk->establecerMontoNetoPendiente('118.00');
$cpeCreditoOk->establecerFechaVencimiento('2026-11-15');
$cpeCreditoOk->establecerCuotas([
    new CpeCuota(id: null, cpeId: null, numeroCuota: 1, monto: '118.00', fechaVencimiento: '2026-11-15')
]);
$cpeCreditoEmitido = $correlativoServicio->asignarCorrelativoYEmitir($cpeCreditoOk);

verificar("Comprobante CRÉDITO emitido exitosamente (ID={$cpeCreditoEmitido->obtenerId()})", $cpeCreditoEmitido->obtenerId() !== null);
verificar("Comprobante CRÉDITO asignó correlativo 2", $cpeCreditoEmitido->obtenerCorrelativo() === 2);
verificar("Comprobante CRÉDITO monto_neto_pendiente es '118.00'", $cpeCreditoEmitido->obtenerMontoNetoPendiente() === '118.00');

// Rehidratación CRÉDITO 1 cuota
$cpeCreditoRecuperado = $comprobanteRepo->obtenerPorId($cpeCreditoEmitido->obtenerId());
$cuotasRecuperadas = $cpeCreditoRecuperado->obtenerCuotas();
verificar("Rehidratación: exactamente 1 cuota recuperada de cpe_cuotas", count($cuotasRecuperadas) === 1);
verificar("Rehidratación: cuota 1 tiene código 'Cuota001'", $cuotasRecuperadas[0]->obtenerCodigoCuota() === 'Cuota001');
verificar("Rehidratación: cuota 1 tiene monto '118.00'", $cuotasRecuperadas[0]->obtenerMonto() === '118.00');
verificar("Rehidratación: cuota 1 tiene vencimiento '2026-11-15'", $cuotasRecuperadas[0]->obtenerFechaVencimiento() === '2026-11-15');

// Emisión exitosa de CRÉDITO multicuota (3 cuotas: 30.00 + 48.00 + 40.00 = 118.00)
$cpeMultiCuota = fabricarComprobanteBase($empresaPruebaId, $estabId, $serieIdF001, 'FACTURA', 'F001', 'CRED_OK_MULTICUOTA_' . $uidPrueba);
$cpeMultiCuota->establecerFormaPago('CREDITO');
$cpeMultiCuota->establecerMontoNetoPendiente('118.00');
$cpeMultiCuota->establecerFechaVencimiento('2026-12-30');
$cpeMultiCuota->establecerCuotas([
    new CpeCuota(id: null, cpeId: null, numeroCuota: 1, monto: '30.00', fechaVencimiento: '2026-10-30'),
    new CpeCuota(id: null, cpeId: null, numeroCuota: 2, monto: '48.00', fechaVencimiento: '2026-11-30'),
    new CpeCuota(id: null, cpeId: null, numeroCuota: 3, monto: '40.00', fechaVencimiento: '2026-12-30'),
]);
$cpeMultiCuotaEmitido = $correlativoServicio->asignarCorrelativoYEmitir($cpeMultiCuota);

verificar("Comprobante CRÉDITO multicuota emitido con correlativo 3", $cpeMultiCuotaEmitido->obtenerCorrelativo() === 3);

$cpeMultiRecuperado = $comprobanteRepo->obtenerPorId($cpeMultiCuotaEmitido->obtenerId());
$cuotasMulti = $cpeMultiRecuperado->obtenerCuotas();
verificar("Rehidratación: exactamente 3 cuotas recuperadas", count($cuotasMulti) === 3);
verificar("Rehidratación: orden correlativo de cuotas preservado (1, 2, 3)", $cuotasMulti[0]->obtenerNumeroCuota() === 1 && $cuotasMulti[1]->obtenerNumeroCuota() === 2 && $cuotasMulti[2]->obtenerNumeroCuota() === 3);
verificar("Rehidratación: montos multicuota exactos ('30.00', '48.00', '40.00')", $cuotasMulti[0]->obtenerMonto() === '30.00' && $cuotasMulti[1]->obtenerMonto() === '48.00' && $cuotasMulti[2]->obtenerMonto() === '40.00');

// Helper para armar comprobante DL 919 con huésped fiscal
function fabricarComprobanteHospedaje(
    int $estabId,
    int $serieId,
    string $uid,
    string $rucPrueba,
    string $uidPrueba,
    string $huespedNombre = 'Alice Smith',
    string $huespedDoc = 'P887766554',
    string $pais = 'US',
    string $checkin = '2026-10-01',
    string $checkout = '2026-10-07',
    int $dias = 6,
    string $fechaConsumo = '2026-10-02'
): CpeComprobante {
    $linea = new CpeLinea(
        null,
        null,
        1,
        'HAB-SUITE-PREM',
        '90111501',
        "Alojamiento Suite Imperial DL 919 (Huésped: {$huespedNombre})",
        'ZZ',
        '6.0000',
        '200.0000',
        '200.0000',
        '0.00',
        '1200.00',
        '40',
        '0.00',
        '0.00',
        '1200.00',
        null,
        $fechaConsumo,
        null,
        []
    );

    $hospedaje = new CpeHospedajeFiscal(
        null,
        null,
        1,
        $huespedNombre,
        'PAS',
        $huespedDoc,
        $pais,
        $pais,
        $checkin,
        $checkin,
        $checkout,
        $dias,
        'TAM-2026-887766'
    );

    return new CpeComprobante(
        null,
        $estabId,
        $serieId,
        null,
        'FACTURA',
        'F001',
        0,
        '',
        "DL919_{$uid}",
        $rucPrueba,
        "Empresa Hotelera Cusco 1D-B2 {$uidPrueba}",
        'Cusco Grand Palace',
        'Av. Sol 500',
        '080101',
        '0000',
        'Cusco',
        'Cusco',
        'Cusco',
        '6',
        '20444555666',
        'GLOBAL TOUR OPERATOR S.A.C.',
        'Av. Las Begonias 441, San Isidro',
        '150131',
        'reservas@globaltour.com',
        'PE',
        true,
        'TAM-2026-887766',
        $checkin,
        $dias,
        'EXPORTACIÓN DE SERVICIOS - DECRETO LEGISLATIVO Nº 919',
        'USD',
        null,
        '0.00',
        '0.00',
        '0.00',
        '1200.00',
        '0.00',
        '0.00',
        '0.00',
        '1200.00',
        'BORRADOR',
        'NO_INICIADO',
        'PENDIENTE_ENVIO',
        'ORIGINAL',
        null,
        null,
        null,
        null,
        null,
        date('Y-m-d H:i:s'),
        null,
        1,
        null,
        null,
        [$linea],
        [],
        'CONTADO',
        null,
        [],
        [$hospedaje]
    );
}

// --------------------------------------------------------------------
// BLOQUE 6: Régimen de Hospedaje a No Domiciliados (DL 919)
// --------------------------------------------------------------------
echo "\n--- BLOQUE 6: Régimen de Hospedaje a No Domiciliados (DL 919) ---\n";

$cpeHospedajeOk = fabricarComprobanteHospedaje($estabId, $serieIdF001, "OK_{$uidPrueba}", $rucPrueba, $uidPrueba);
$cpeHospEmitido = $correlativoServicio->asignarCorrelativoYEmitir($cpeHospedajeOk);

verificar("Comprobante DL 919 emitido exitosamente (ID={$cpeHospEmitido->obtenerId()})", $cpeHospEmitido->obtenerId() !== null);
verificar("Comprobante DL 919 asignó correlativo 4", $cpeHospEmitido->obtenerCorrelativo() === 4);
verificar("Separación arquitectónica: Receptor RUC '20444555666' != Huésped Pasaporte 'P887766554'", $cpeHospEmitido->obtenerReceptorNumeroDocumento() !== $cpeHospEmitido->obtenerHospedajes()[0]->obtenerHuespedNumeroDocumento());

// Rehidratación completa del agregado fiscal DL 919
$cpeHospRecuperado = $comprobanteRepo->obtenerPorId($cpeHospEmitido->obtenerId());
$hospedajesRecuperados = $cpeHospRecuperado->obtenerHospedajes();
verificar("Rehidratación: exactamente 1 hospedaje recuperado", count($hospedajesRecuperados) === 1);
$hosp1Rec = $hospedajesRecuperados[0];
verificar("Rehidratación: nombre de huésped 'Alice Smith'", $hosp1Rec->obtenerHuespedNombreCompleto() === 'Alice Smith');
verificar("Rehidratación: tipo doc Catálogo 06 ('7' / Pasaporte) y país pasaporte 'US'", in_array($hosp1Rec->obtenerHuespedTipoDocumento(), ['7', 'PAS'], true) && $hosp1Rec->obtenerHuespedPaisEmisionPasaporte() === 'US');
verificar("Rehidratación: fecha checkin '2026-10-01' y checkout '2026-10-07'", $hosp1Rec->obtenerFechaCheckin() === '2026-10-01' && $hosp1Rec->obtenerFechaCheckout() === '2026-10-07');
verificar("Rehidratación: días de permanencia 6", $hosp1Rec->obtenerDiasPermanencia() === 6);

// Verificar hidratación en cpe_lineas y enlace a cpe_hospedaje_id
$lineasRecuperadas = $cpeHospRecuperado->obtenerLineas();
verificar("Rehidratación: línea fiscal tiene cpe_hospedaje_id asignado", $lineasRecuperadas[0]->obtenerCpeHospedajeId() === $hosp1Rec->obtenerId());
verificar("Rehidratación: línea fiscal tiene fecha_consumo '2026-10-02'", $lineasRecuperadas[0]->obtenerFechaConsumo() === '2026-10-02');

// Emisión exitosa de CPE DL 919 con múltiples huéspedes (Hospedaje 1 + Hospedaje 2)
$lineaMultiHosp1 = new CpeLinea(
    null, null, 1, 'HAB-01', '90111501',
    'Habitación Huésped 1 DL 919', 'ZZ', '2.0000', '100.0000', '100.0000',
    '0.00', '200.00', '40', '0.00', '0.00', '200.00',
    1, '2026-10-02', null, []
);
$lineaMultiHosp2 = new CpeLinea(
    null, null, 2, 'HAB-02', '90111501',
    'Habitación Huésped 2 DL 919', 'ZZ', '2.0000', '100.0000', '100.0000',
    '0.00', '200.00', '40', '0.00', '0.00', '200.00',
    2, '2026-10-03', null, []
);
$hospMulti1 = new CpeHospedajeFiscal(
    null, null, 1, 'Guest One Primary', 'PAS', 'P1000001', 'US', 'US',
    '2026-10-01', '2026-10-01', '2026-10-05', 4, 'TAM-001'
);
$hospMulti2 = new CpeHospedajeFiscal(
    null, null, 2, 'Guest Two Secondary', 'PAS', 'P2000002', 'CA', 'CA',
    '2026-10-01', '2026-10-01', '2026-10-05', 4, 'TAM-002'
);
$cpeMultiHosp = new CpeComprobante(
    null, $estabId, $serieIdF001, null, 'FACTURA', 'F001', 0, '', "DL919_MULTI_{$uidPrueba}",
    $rucPrueba, "Empresa Hotelera Cusco 1D-B2 {$uidPrueba}", 'Cusco Grand Palace', 'Av. Sol 500', '080101', '0000',
    'Cusco', 'Cusco', 'Cusco', '6', '20444555666', 'GLOBAL TOUR OPERATOR S.A.C.', 'Av. Las Begonias 441', '150131',
    'reservas@globaltour.com', 'PE', true, 'TAM-MULTI', '2026-10-01', 4,
    'EXPORTACIÓN DE SERVICIOS - DECRETO LEGISLATIVO Nº 919', 'USD', null,
    '0.00', '0.00', '0.00', '400.00', '0.00', '0.00', '0.00', '400.00',
    'BORRADOR', 'NO_INICIADO', 'PENDIENTE_ENVIO', 'ORIGINAL',
    null, null, null, null, null, date('Y-m-d H:i:s'), null, 1, null, null,
    [$lineaMultiHosp1, $lineaMultiHosp2], [], 'CONTADO', null, [], [$hospMulti1, $hospMulti2]
);
$cpeMultiHospEmitido = $correlativoServicio->asignarCorrelativoYEmitir($cpeMultiHosp);
verificar("Multi-huésped en 1 CPE emitido exitosamente (ID={$cpeMultiHospEmitido->obtenerId()})", $cpeMultiHospEmitido->obtenerId() !== null);

$cpeMultiHospRec = $comprobanteRepo->obtenerPorId($cpeMultiHospEmitido->obtenerId());
$hospsMultiRec = $cpeMultiHospRec->obtenerHospedajes();
$lineasMultiRec = $cpeMultiHospRec->obtenerLineas();
verificar("Rehidratación multi-huésped: exactamente 2 hospedajes fiscales en 1 CPE sin colisión de unicidad", count($hospsMultiRec) === 2);
verificar("Rehidratación multi-huésped: líneas asociadas a sus respectivos hospedajes sin colisión",
    $lineasMultiRec[0]->obtenerCpeHospedajeId() === $hospsMultiRec[0]->obtenerId() &&
    $lineasMultiRec[1]->obtenerCpeHospedajeId() === $hospsMultiRec[1]->obtenerId()
);

// --------------------------------------------------------------------
// BLOQUE 7: Restricciones de Dominio y Motor para DL 919 (Casos Negativos)
// --------------------------------------------------------------------
echo "\n--- BLOQUE 7: Restricciones de Dominio y Motor para DL 919 (Casos Negativos) ---\n";

// Invariante: Consumo anterior a check-in
$cpeConsumoPrevio = fabricarComprobanteHospedaje($estabId, $serieIdF001, "FAIL_PREV_{$uidPrueba}", $rucPrueba, $uidPrueba, fechaConsumo: '2026-09-30');

$falloConsumoPrevio = false;
try {
    $correlativoServicio->asignarCorrelativoYEmitir($cpeConsumoPrevio);
} catch (ValidacionFiscalExcepcion $e) {
    $falloConsumoPrevio = true;
}
verificar("Rechazo estricto: fecha_consumo anterior a check-in lanza ValidacionFiscalExcepcion", $falloConsumoPrevio);

// Invariante: Consumo posterior a check-out
$cpeConsumoPost = fabricarComprobanteHospedaje($estabId, $serieIdF001, "FAIL_POST_{$uidPrueba}", $rucPrueba, $uidPrueba, fechaConsumo: '2026-10-08');

$falloConsumoPost = false;
try {
    $correlativoServicio->asignarCorrelativoYEmitir($cpeConsumoPost);
} catch (ValidacionFiscalExcepcion $e) {
    $falloConsumoPost = true;
}
verificar("Rechazo estricto: fecha_consumo posterior a check-out lanza ValidacionFiscalExcepcion", $falloConsumoPost);

// Invariante en MySQL: Rechazo CHECK permanencia > 60 días
$falloCheckDiasMotor = false;
try {
    $stmtBadDias = $pdo->prepare(
        "INSERT INTO cpe_hospedajes (cpe_id, numero_orden, nombres_apellidos, tipo_documento, numero_documento, pais_emision_pasaporte, pais_residencia, fecha_ingreso_pais, fecha_checkin, fecha_checkout, dias_permanencia)
         VALUES (:cpe_id, 99, 'Overstay Guest', '7', 'X112233', 'US', 'US', '2026-01-01', '2026-01-01', '2026-03-10', 65)"
    );
    $stmtBadDias->execute([':cpe_id' => $cpeHospEmitido->obtenerId()]);
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'chk_cpe_hosp_dias_max')) {
        $falloCheckDiasMotor = true;
    }
}
verificar("Motor InnoDB rechaza CHECK chk_cpe_hosp_dias_max (permanencia 65 > 60 días)", $falloCheckDiasMotor);

// Invariante en MySQL: Rechazo CHECK checkout < checkin
$falloCheckFechasMotor = false;
try {
    $stmtBadFechas = $pdo->prepare(
        "INSERT INTO cpe_hospedajes (cpe_id, numero_orden, nombres_apellidos, tipo_documento, numero_documento, pais_emision_pasaporte, pais_residencia, fecha_ingreso_pais, fecha_checkin, fecha_checkout, dias_permanencia)
         VALUES (:cpe_id, 98, 'Inverted Guest', '7', 'X112234', 'US', 'US', '2026-09-30', '2026-10-15', '2026-10-01', 5)"
    );
    $stmtBadFechas->execute([':cpe_id' => $cpeHospEmitido->obtenerId()]);
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'chk_cpe_hosp_fechas')) {
        $falloCheckFechasMotor = true;
    }
}
verificar("Motor InnoDB rechaza CHECK chk_cpe_hosp_fechas (checkout < checkin)", $falloCheckFechasMotor);

// Invariante en MySQL: Rechazo UNIQUE numero_orden duplicado en cpe_hospedajes
$falloUniqueOrdenMotor = false;
try {
    $stmtBadOrden = $pdo->prepare(
        "INSERT INTO cpe_hospedajes (cpe_id, numero_orden, nombres_apellidos, tipo_documento, numero_documento, pais_emision_pasaporte, pais_residencia, fecha_ingreso_pais, fecha_checkin, fecha_checkout, dias_permanencia)
         VALUES (:cpe_id, 1, 'Duplicate Order Guest', '7', 'X999999', 'US', 'US', '2026-10-01', '2026-10-01', '2026-10-05', 4)"
    );
    $stmtBadOrden->execute([':cpe_id' => $cpeHospEmitido->obtenerId()]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        $falloUniqueOrdenMotor = true;
    }
}
verificar("Motor InnoDB rechaza UNIQUE uq_cpe_hospedaje_orden (numero_orden 1 duplicado en mismo CPE)", $falloUniqueOrdenMotor);

// Invariante en MySQL: Rechazo UNIQUE numero_cuota duplicado en cpe_cuotas
$falloUniqueCuotaMotor = false;
try {
    $stmtBadCuota = $pdo->prepare(
        "INSERT INTO cpe_cuotas (cpe_id, numero_cuota, monto, fecha_vencimiento)
         VALUES (:cpe_id, 1, 50.00, '2026-11-01')"
    );
    $stmtBadCuota->execute([':cpe_id' => $cpeCreditoEmitido->obtenerId()]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        $falloUniqueCuotaMotor = true;
    }
}
verificar("Motor InnoDB rechaza UNIQUE uq_cpe_cuota_numero (numero_cuota 1 duplicado en mismo CPE)", $falloUniqueCuotaMotor);

// --------------------------------------------------------------------
// BLOQUE 8: Protección Física y Lógica Cross-CPE
// --------------------------------------------------------------------
echo "\n--- BLOQUE 8: Protección Física y Lógica Cross-CPE ---\n";

// Crear un segundo comprobante DL 919 con su propio huésped (Hospedaje B)
$cpeHospedajeB = fabricarComprobanteHospedaje(
    $estabId, $serieIdF001, "CPE_B_{$uidPrueba}", $rucPrueba, $uidPrueba,
    'Bob Marley Foreign Guest', 'JM112233', 'JM', '2026-10-01', '2026-10-05', 4, '2026-10-02'
);
$cpeBEmitido = $correlativoServicio->asignarCorrelativoYEmitir($cpeHospedajeB);
$hospedajeBId = $cpeBEmitido->obtenerHospedajes()[0]->obtenerId();
$hospedajeAId = $hosp1Rec->obtenerId();
$cpeAId = $cpeHospEmitido->obtenerId();

verificar("CPE B emitido exitosamente con Hospedaje ID={$hospedajeBId}", $hospedajeBId !== null && $hospedajeBId !== $hospedajeAId);

// Intento 1: Detección en servicio — Asociar línea de nuevo CPE con Hospedaje de CPE B
$cpeCruzado = fabricarComprobanteHospedaje($estabId, $serieIdF001, "CROSS_SVC_FAIL_{$uidPrueba}", $rucPrueba, $uidPrueba);
$cpeCruzado->obtenerLineas()[0]->establecerCpeHospedajeId($hospedajeBId); // Hospedaje ajeno (pertenece a CPE B)

$falloCrossServicio = false;
try {
    $correlativoServicio->asignarCorrelativoYEmitir($cpeCruzado);
} catch (ValidacionFiscalExcepcion $e) {
    $falloCrossServicio = true;
}
verificar("Servicio CpeCorrelativoServicio rechaza cruce Cross-CPE con ValidacionFiscalExcepcion", $falloCrossServicio);

// Intento 2: Inserción directa en MySQL bypassando el servicio — Verificación de Composite Foreign Key
$falloCrossFkMotor = false;
try {
    // Intentar insertar una línea perteneciente a CPE A que apunta a hospedajeBId (pertenece a CPE B)
    $stmtBadCross = $pdo->prepare(
        "INSERT INTO cpe_lineas (
            cpe_id, cpe_hospedaje_id, numero_orden, codigo_producto_interno, codigo_producto_sunat,
            descripcion, unidad_medida, cantidad, valor_unitario, precio_unitario,
            descuento_monto, base_imponible, tipo_afectacion_igv, tasa_igv, monto_igv, total_linea,
            fecha_consumo
         ) VALUES (
            :cpe_id, :cpe_hospedaje_id, 99, 'HAB-X', '90111501',
            'Cross Injection Line', 'ZZ', 1.0000, 100.0000, 100.0000,
            0.00, 100.00, '40', 0.00, 0.00, 100.00,
            '2026-10-02'
         )"
    );
    $stmtBadCross->execute([
        ':cpe_id' => $cpeAId,
        ':cpe_hospedaje_id' => $hospedajeBId, // Pertenece a cpeBEmitido, no a cpeAId
    ]);
} catch (PDOException $e) {
    // Error 1452: Cannot add or update a child row: a foreign key constraint fails
    if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'fk_cpe_lineas_hospedaje_cross')) {
        $falloCrossFkMotor = true;
    }
}
verificar("Motor InnoDB rechaza cruce Cross-CPE con FK fk_cpe_lineas_hospedaje_cross (SQLSTATE 23000 / Error 1452)", $falloCrossFkMotor);

// --------------------------------------------------------------------
// BLOQUE 9: Auditoría de Seguridad de Secretos y Cero Floats
// --------------------------------------------------------------------
echo "\n--- BLOQUE 9: Auditoría de Seguridad de Secretos y Cero Floats ---\n";

$columnasSecretos = $pdo->query(
    "SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME IN ('cpe_cuotas', 'cpe_hospedajes')
     AND (COLUMN_NAME LIKE '%password%' OR COLUMN_NAME LIKE '%secret%' OR COLUMN_NAME LIKE '%private%' OR COLUMN_NAME LIKE '%token%')"
)->fetchAll();
verificar("Cero columnas de contraseñas, secretos o tokens en cpe_cuotas y cpe_hospedajes", empty($columnasSecretos));

// --------------------------------------------------------------------
// BLOQUE 10: Limpieza Defensiva y Cero Residuos en Base de Datos
// --------------------------------------------------------------------
echo "\n--- BLOQUE 10: Limpieza Defensiva y Cero Residuos en Base de Datos ---\n";

$pdo->beginTransaction();
try {
    // 1. Eliminar atribución de cargos
    $pdo->exec("DELETE lc FROM cpe_linea_cargos lc
                INNER JOIN cpe_lineas l ON lc.cpe_linea_id = l.id
                INNER JOIN cpe_comprobantes c ON l.cpe_id = c.id
                WHERE c.emisor_establecimiento_id = {$estabId}");

    // 2. Eliminar líneas
    $pdo->exec("DELETE l FROM cpe_lineas l
                INNER JOIN cpe_comprobantes c ON l.cpe_id = c.id
                WHERE c.emisor_establecimiento_id = {$estabId}");

    // 3. Eliminar cuotas
    $pdo->exec("DELETE q FROM cpe_cuotas q
                INNER JOIN cpe_comprobantes c ON q.cpe_id = c.id
                WHERE c.emisor_establecimiento_id = {$estabId}");

    // 4. Eliminar hospedajes
    $pdo->exec("DELETE h FROM cpe_hospedajes h
                INNER JOIN cpe_comprobantes c ON h.cpe_id = c.id
                WHERE c.emisor_establecimiento_id = {$estabId}");

    // 5. Eliminar comprobantes
    $pdo->exec("DELETE FROM cpe_comprobantes WHERE emisor_establecimiento_id = {$estabId}");

    // 6. Eliminar series
    $pdo->exec("DELETE FROM cpe_series WHERE emisor_establecimiento_id = {$estabId}");

    // 7. Eliminar establecimientos
    $pdo->exec("DELETE FROM cpe_establecimientos_configuracion WHERE id = {$estabId}");

    // 8. Eliminar empresas
    $pdo->exec("DELETE FROM empresas WHERE id = {$empresaPruebaId}");

    $pdo->commit();
    verificar("Transacción de limpieza defensiva ejecutada con éxito", true);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    verificar("Transacción de limpieza defensiva falló: " . $e->getMessage(), false);
}

// Comprobar cero residuos
$residuosCpe = (int) $pdo->query("SELECT COUNT(*) FROM cpe_comprobantes WHERE emisor_establecimiento_id = {$estabId}")->fetchColumn();
$residuosCuotas = (int) $pdo->query("SELECT COUNT(*) FROM cpe_cuotas q INNER JOIN cpe_comprobantes c ON q.cpe_id = c.id WHERE c.emisor_establecimiento_id = {$estabId}")->fetchColumn();
$residuosHosp = (int) $pdo->query("SELECT COUNT(*) FROM cpe_hospedajes h INNER JOIN cpe_comprobantes c ON h.cpe_id = c.id WHERE c.emisor_establecimiento_id = {$estabId}")->fetchColumn();
$residuosSeries = (int) $pdo->query("SELECT COUNT(*) FROM cpe_series WHERE emisor_establecimiento_id = {$estabId}")->fetchColumn();
$residuosEmpresas = (int) $pdo->query("SELECT COUNT(*) FROM empresas WHERE id = {$empresaPruebaId}")->fetchColumn();

verificar(
    "Cero residuos en base de datos tras ejecución (CPEs: {$residuosCpe}, Cuotas: {$residuosCuotas}, Hospedajes: {$residuosHosp}, Series: {$residuosSeries}, Empresas: {$residuosEmpresas})",
    $residuosCpe === 0 && $residuosCuotas === 0 && $residuosHosp === 0 && $residuosSeries === 0 && $residuosEmpresas === 0
);

// --------------------------------------------------------------------
// RESUMEN FINAL
// --------------------------------------------------------------------
echo "\n====================================================================\n";
echo " RESUMEN: test_sunat_cpe_1d_b2.php\n";
echo " Total checks:  {$totalChecks}\n";
echo " Checks PASS:   {$passedChecks}\n";
echo " Checks FAIL:   {$failedChecks}\n";
echo " Estado:        " . ($failedChecks === 0 ? "100% PASS — HOMOLOGADO" : "CON FALLOS") . "\n";
echo "====================================================================\n";

if ($failedChecks > 0) {
    exit(1);
}
