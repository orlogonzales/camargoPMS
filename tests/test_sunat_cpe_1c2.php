<?php

declare(strict_types=1);

/**
 * Suite de Pruebas: SUNAT-1C2 — Dominio CPE Soberano, Repositorios y Motor de Correlativos Concurrente
 *
 * Valida:
 * 1. Autoloading, tipado estricto e instanciación de agregados de dominio (CpeComprobante, CpeSerie, CpeEstablecimiento, CpeLinea, CpeDocumentoRelacionado).
 * 2. Cero tipos float/double en modelos; uso exclusivo de string y BCMath en importes, bases y cantidades.
 * 3. Dominio de excepciones especializadas bajo la jerarquía CpeExcepcion.
 * 4. Repositorios de persistencia sin gestión autónoma de transacciones (CpeSerieRepositorio, CpeEstablecimientoRepositorio, CpeComprobanteRepositorio).
 * 5. Asignación inicial de correlativo desde 1 y avance creciente por serie (N+1).
 * 6. Rollback transaccional ante fallos: serie protegida contra incrementos espurios y comprobante no emitido.
 * 7. Ajuste Vinculante C2-01: Revalidación autoritativa de idempotencia en sección crítica bajo lock pesimista de serie.
 * 8. Cross-Validation vinculante: Rechazo estricto de series pertenecientes a otro establecimiento (EstablecimientoSerieIncompatibleExcepcion).
 * 9. Ajuste Vinculante C2-02: Propiedad estricta de transacciones externas ($debeCerrarTx) — el servicio respeta transacciones preexistentes.
 * 10. Aislamiento multiempresa: series con el mismo nombre coexisten en establecimientos distintos sin colisión.
 * 11. Concurrencia real con bloqueo pesimista (SELECT ... FOR UPDATE) y timeout de bloqueo InnoDB.
 * 12. Concurrencia real con procesos paralelos CLI: asignación atómica y creciente de correlativos sin colisiones ni duplicados.
 * 13. Concurrencia real con clave de idempotencia compartida: consumo único de correlativo y resolución determinista.
 * 14. Limpieza defensiva total: cero residuos en la base de datos tras la ejecución.
 * 15. Inmutabilidad de migraciones, ranura 041 libre y admin-dashboard/ intacto.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use CamargoPMS\Excepciones\CpeExcepcion;
use CamargoPMS\Excepciones\EstablecimientoNoEncontradoExcepcion;
use CamargoPMS\Excepciones\EstablecimientoSerieIncompatibleExcepcion;
use CamargoPMS\Excepciones\SerieFiscalInactivaExcepcion;
use CamargoPMS\Excepciones\SerieFiscalNoEncontradaExcepcion;
use CamargoPMS\Excepciones\SerieTipoIncompatibleExcepcion;
use CamargoPMS\Excepciones\ValidacionFiscalExcepcion;
use CamargoPMS\Modelos\CPE\CpeComprobante;
use CamargoPMS\Modelos\CPE\CpeDocumentoRelacionado;
use CamargoPMS\Modelos\CPE\CpeEstablecimiento;
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

$totalChecks = 0;
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
echo " EJECUTANDO SUITE: test_sunat_cpe_1c2.php (SUNAT-1C2)\n";
echo "====================================================================\n\n";

// --------------------------------------------------------------------
// BLOQUE 1: Dominio Soberano, Modelos Tipados y Cero Floats
// --------------------------------------------------------------------
echo "--- BLOQUE 1: Dominio Soberano, Modelos Tipados y Cero Floats ---\n";

verificar("Clase CpeComprobante cargada vía PSR-4", class_exists(CpeComprobante::class));
verificar("Clase CpeSerie cargada vía PSR-4", class_exists(CpeSerie::class));
verificar("Clase CpeEstablecimiento cargada vía PSR-4", class_exists(CpeEstablecimiento::class));
verificar("Clase CpeLinea cargada vía PSR-4", class_exists(CpeLinea::class));
verificar("Clase CpeDocumentoRelacionado cargada vía PSR-4", class_exists(CpeDocumentoRelacionado::class));

// Verificación de jerarquía de excepciones de dominio
verificar("Excepción CpeExcepcion extiende RuntimeException", is_subclass_of(CpeExcepcion::class, RuntimeException::class));
verificar("EstablecimientoNoEncontradoExcepcion hereda de CpeExcepcion", is_subclass_of(EstablecimientoNoEncontradoExcepcion::class, CpeExcepcion::class));
verificar("EstablecimientoSerieIncompatibleExcepcion hereda de CpeExcepcion", is_subclass_of(EstablecimientoSerieIncompatibleExcepcion::class, CpeExcepcion::class));
verificar("SerieFiscalNoEncontradaExcepcion hereda de CpeExcepcion", is_subclass_of(SerieFiscalNoEncontradaExcepcion::class, CpeExcepcion::class));
verificar("SerieFiscalInactivaExcepcion hereda de CpeExcepcion", is_subclass_of(SerieFiscalInactivaExcepcion::class, CpeExcepcion::class));
verificar("SerieTipoIncompatibleExcepcion hereda de CpeExcepcion", is_subclass_of(SerieTipoIncompatibleExcepcion::class, CpeExcepcion::class));
verificar("ValidacionFiscalExcepcion hereda de CpeExcepcion", is_subclass_of(ValidacionFiscalExcepcion::class, CpeExcepcion::class));

// Instanciación de modelos en memoria y chequeo de tipos estrictos
$modeloSerie = new CpeSerie(
    id: 1,
    emisorEstablecimientoId: 10,
    tipoComprobante: 'FACTURA',
    serie: 'F001',
    ultimoCorrelativo: 5,
    prefijoTipo: 'F',
    descripcion: 'Serie Principal Facturación',
    estado: 'ACTIVO'
);
verificar("CpeSerie::obtenerSiguienteCorrelativo() calcula 6 correctamente", $modeloSerie->obtenerSiguienteCorrelativo() === 6);
verificar("CpeSerie::formatearFolio(6) formatea 'F001-00000006'", $modeloSerie->formatearFolio(6) === 'F001-00000006');
verificar("CpeSerie::estaActiva() retorna true", $modeloSerie->estaActiva() === true);
verificar("CpeSerie::esFactura() retorna true", $modeloSerie->esFactura() === true);

$modeloLinea = new CpeLinea(
    id: null,
    cpeId: null,
    numeroOrden: 1,
    codigoProductoInterno: 'HAB-MAT',
    codigoProductoSunat: '90111501',
    descripcion: 'Noche de Alojamiento Habitación Matrimonial',
    unidadMedida: 'ZZ',
    cantidad: '2.0000',
    valorUnitario: '100.0000',
    precioUnitario: '118.0000',
    descuentoMonto: '0.00',
    baseImponible: '200.00',
    tipoAfectacionIgv: '10',
    tasaIgv: '18.00',
    montoIgv: '36.00',
    totalLinea: '236.00',
    creadoEn: null,
    cargosAtribuidos: [
        ['cargo_cuenta_id' => 99, 'cantidad_atribuida' => '2.0000', 'monto_atribuido' => '236.00']
    ]
);

verificar("CpeLinea almacena cantidad como string de escala 4 ('2.0000')", is_string($modeloLinea->obtenerCantidad()) && $modeloLinea->obtenerCantidad() === '2.0000');
verificar("CpeLinea almacena base imponible como string ('200.00')", is_string($modeloLinea->obtenerBaseImponible()) && $modeloLinea->obtenerBaseImponible() === '200.00');
verificar("CpeLinea almacena monto IGV como string ('36.00')", is_string($modeloLinea->obtenerMontoIgv()) && $modeloLinea->obtenerMontoIgv() === '36.00');
verificar("CpeLinea almacena total de línea como string ('236.00')", is_string($modeloLinea->obtenerTotalLinea()) && $modeloLinea->obtenerTotalLinea() === '236.00');
verificar("CpeLinea preserva atribución de cargos operativos de cuenta", count($modeloLinea->obtenerCargosAtribuidos()) === 1);

// Verificación de serialización haciaArreglo / desdeArreglo
$arrLinea = $modeloLinea->haciaArreglo();
$lineaReconstruida = CpeLinea::desdeArreglo($arrLinea);
verificar("CpeLinea::desdeArreglo reconstruye idénticamente los datos", $lineaReconstruida->obtenerDescripcion() === $modeloLinea->obtenerDescripcion() && $lineaReconstruida->obtenerTotalLinea() === '236.00');

// --------------------------------------------------------------------
// BLOQUE 2: Repositorios Soberanos y Consultas Preparadas
// --------------------------------------------------------------------
echo "\n--- BLOQUE 2: Repositorios Soberanos y Consultas Preparadas ---\n";

$serieRepo = new CpeSerieRepositorio($pdo);
$estabRepo = new CpeEstablecimientoRepositorio($pdo);
$comprobanteRepo = new CpeComprobanteRepositorio($pdo);

verificar("Instancia CpeSerieRepositorio creada exitosamente", $serieRepo instanceof CpeSerieRepositorio);
verificar("Instancia CpeEstablecimientoRepositorio creada exitosamente", $estabRepo instanceof CpeEstablecimientoRepositorio);
verificar("Instancia CpeComprobanteRepositorio creada exitosamente", $comprobanteRepo instanceof CpeComprobanteRepositorio);

// Preparar entorno de prueba transaccional / fixtures sintéticos
$uidPrueba = bin2hex(random_bytes(4));
$usuarioIdPrueba = (int) $pdo->query("SELECT id FROM usuarios LIMIT 1")->fetchColumn() ?: 1;
$tipoDocRucTest = (int) $pdo->query("SELECT id FROM tipos_documento WHERE codigo IN ('RUC', 'DNI') LIMIT 1")->fetchColumn() ?: 1;

$rucPrueba = sprintf('20%09d', random_int(100000000, 999999999));
$rucEmpresa2 = sprintf('20%09d', random_int(100000000, 999999999));

// Limpieza preventiva ante posibles fallos previos
$pdo->exec("DELETE FROM cpe_series WHERE descripcion LIKE '%1C2%'");
$pdo->exec("DELETE FROM cpe_establecimientos_configuracion WHERE razon_social_snapshot LIKE '%1C2%'");
$pdo->exec("DELETE FROM empresas WHERE codigo LIKE 'EMP_T_%' OR codigo LIKE 'EMP_2_%'");

// Insertar empresa de prueba
$pdo->prepare(
    "INSERT INTO empresas (codigo, tipo_documento_id, numero_documento, razon_social, direccion_fiscal, pais_id)
     VALUES (:cod, :tipo_doc, :num_doc, :razon, 'Av. Test Fiscal 123', 1)"
)->execute([
    ':cod' => "EMP_T_{$uidPrueba}",
    ':tipo_doc' => $tipoDocRucTest,
    ':num_doc' => $rucPrueba,
    ':razon' => "Empresa Test 1C2 {$uidPrueba}",
]);
$empresaPruebaId = (int) $pdo->lastInsertId();

// Insertar establecimiento de prueba
$estabPrueba = new CpeEstablecimiento(
    null,
    $empresaPruebaId,
    null,
    '0000',
    "Empresa Test 1C2 {$uidPrueba}",
    'Nombre Comercial Test',
    'Av. Test Fiscal 123',
    '150101',
    'Lima',
    'Lima',
    'Lima',
    'BETA',
    'SUNAT_SOAP_DIRECTO',
    'ACTIVO'
);
$estabPruebaId = $estabRepo->crear($estabPrueba);
verificar("Establecimiento fiscal creado correctamente mediante repositorio (ID={$estabPruebaId})", $estabPruebaId > 0);

$estabRecuperado = $estabRepo->obtenerPorId($estabPruebaId);
verificar("CpeEstablecimientoRepositorio::obtenerPorId recupera entidad", $estabRecuperado !== null && $estabRecuperado->obtenerCodigoEstablecimientoSunat() === '0000');
verificar("CpeEstablecimientoRepositorio::obtenerPorEmpresaYAnexo recupera establecimiento", $estabRepo->obtenerPorEmpresaYAnexo($empresaPruebaId, '0000') !== null);

// Insertar serie de prueba
$seriePrueba = new CpeSerie(
    null,
    $estabPruebaId,
    'FACTURA',
    'F001',
    0, // empieza en 0
    'F',
    'Serie de prueba unitaria 1C2',
    'ACTIVO'
);
$seriePruebaId = $serieRepo->crear($seriePrueba);
verificar("Serie fiscal creada correctamente mediante repositorio (ID={$seriePruebaId})", $seriePruebaId > 0);

$serieRecuperada = $serieRepo->obtenerPorId($seriePruebaId, false);
verificar("CpeSerieRepositorio::obtenerPorId recupera serie", $serieRecuperada !== null && $serieRecuperada->obtenerSerie() === 'F001');
verificar("CpeSerieRepositorio::buscarPorEstablecimientoYTipo recupera serie", $serieRepo->buscarPorEstablecimientoYTipo($estabPruebaId, 'FACTURA', 'F001') !== null);

// --------------------------------------------------------------------
// BLOQUE 3: Servicio de Correlativos — Asignación Inicial Concurrency-Safe desde 1
// --------------------------------------------------------------------
echo "\n--- BLOQUE 3: Servicio de Correlativos — Asignación Inicial Concurrency-Safe desde 1 ---\n";

$servicioCorrelativo = new CpeCorrelativoServicio($pdo, $serieRepo, $comprobanteRepo, $estabRepo);
verificar("Instancia CpeCorrelativoServicio creada exitosamente", $servicioCorrelativo instanceof CpeCorrelativoServicio);

$siguienteTentativo = $servicioCorrelativo->obtenerSiguienteCorrelativoTentativo($seriePruebaId);
verificar("obtenerSiguienteCorrelativoTentativo() retorna 1 para serie con correlativo 0", $siguienteTentativo === 1);

// Emitir primer comprobante
$cpe1 = new CpeComprobante(
    null,
    $estabPruebaId,
    $seriePruebaId,
    null,
    'FACTURA',
    'F001',
    0,
    '',
    "IDEMP_1_{$uidPrueba}",
    $rucPrueba,
    "Empresa Test 1C2 {$uidPrueba}",
    'Nombre Comercial Test',
    'Av. Test Fiscal 123',
    '150101',
    '0000',
    'Lima',
    'Lima',
    'Lima',
    '6',
    '20555555551',
    'Cliente Receptor Test 1 SAC',
    'Av. Cliente 123',
    '150101',
    'facturas@cliente1.com',
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
    $usuarioIdPrueba,
    null,
    null,
    [$modeloLinea]
);

$cpeEmitido1 = $servicioCorrelativo->emitirBorradorOAsignarCorrelativo($cpe1, $usuarioIdPrueba);
verificar("Primer CPE emitido recibe correlativo exactamente 1", $cpeEmitido1->obtenerCorrelativo() === 1);
verificar("Primer CPE emitido recibe folio 'F001-00000001'", $cpeEmitido1->obtenerCodigoFolioCompleto() === 'F001-00000001');
verificar("Primer CPE emitido actualiza estado_generacion a 'EMITIDO'", $cpeEmitido1->obtenerEstadoGeneracion() === 'EMITIDO');
verificar("Primer CPE emitido conserva líneas fiscales hidratadas", count($cpeEmitido1->obtenerLineas()) === 1);

// Verificar persistencia en base de datos
$serieActualizada = $serieRepo->obtenerPorId($seriePruebaId);
verificar("cpe_series.ultimo_correlativo se incrementó a 1 en BD", $serieActualizada->obtenerUltimoCorrelativo() === 1);

// Emitir segundo comprobante
$cpe2 = new CpeComprobante(
    null,
    $estabPruebaId,
    $seriePruebaId,
    null,
    'FACTURA',
    'F001',
    0,
    '',
    "IDEMP_2_{$uidPrueba}",
    $rucPrueba,
    "Empresa Test 1C2 {$uidPrueba}",
    'Nombre Comercial Test',
    'Av. Test Fiscal 123',
    '150101',
    '0000',
    'Lima',
    'Lima',
    'Lima',
    '6',
    '20555555552',
    'Cliente Receptor Test 2 SAC',
    'Av. Cliente 456',
    '150101',
    'facturas@cliente2.com',
    'PE',
    false,
    null,
    null,
    null,
    null,
    'PEN',
    null,
    '200.00',
    '0.00',
    '0.00',
    '0.00',
    '0.00',
    '36.00',
    '0.00',
    '236.00',
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
    $usuarioIdPrueba,
    null,
    null,
    [$modeloLinea]
);

$cpeEmitido2 = $servicioCorrelativo->emitirBorradorOAsignarCorrelativo($cpe2, $usuarioIdPrueba);
verificar("Segundo CPE emitido recibe correlativo exactamente 2", $cpeEmitido2->obtenerCorrelativo() === 2);
verificar("Segundo CPE emitido recibe folio 'F001-00000002'", $cpeEmitido2->obtenerCodigoFolioCompleto() === 'F001-00000002');

$serieActualizada2 = $serieRepo->obtenerPorId($seriePruebaId);
verificar("cpe_series.ultimo_correlativo se incrementó a 2 en BD", $serieActualizada2->obtenerUltimoCorrelativo() === 2);

// --------------------------------------------------------------------
// BLOQUE 4: Ajuste Vinculante C2-01 — Idempotencia en Sección Crítica
// --------------------------------------------------------------------
echo "\n--- BLOQUE 4: Ajuste Vinculante C2-01 — Idempotencia en Sección Crítica ---\n";

// Reintento con clave existente IDEMP_1_{uidPrueba}
$cpeRepetido = new CpeComprobante(
    null,
    $estabPruebaId,
    $seriePruebaId,
    null,
    'FACTURA',
    'F001',
    0,
    '',
    "IDEMP_1_{$uidPrueba}", // MISMA CLAVE QUE CPE 1
    $rucPrueba,
    "Empresa Test 1C2 {$uidPrueba}",
    'Nombre Comercial Test',
    'Av. Test Fiscal 123',
    '150101',
    '0000',
    'Lima',
    'Lima',
    'Lima',
    '6',
    '20555555551',
    'Cliente Receptor Test 1 SAC',
    null,
    null,
    null,
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
    'ORIGINAL'
);

$cpeResultadoReintento = $servicioCorrelativo->emitirBorradorOAsignarCorrelativo($cpeRepetido, $usuarioIdPrueba);
verificar("Reintento idempotente retorna el mismo ID del CPE original", $cpeResultadoReintento->obtenerId() === $cpeEmitido1->obtenerId());
verificar("Reintento idempotente retorna el mismo folio 'F001-00000001'", $cpeResultadoReintento->obtenerCodigoFolioCompleto() === 'F001-00000001');

$serieSinAvance = $serieRepo->obtenerPorId($seriePruebaId);
verificar("Reintento idempotente NO avanzó el correlativo de la serie (continúa en 2)", $serieSinAvance->obtenerUltimoCorrelativo() === 2);

$totalComprobantes = (int) $pdo->query(
    "SELECT COUNT(*) FROM cpe_comprobantes WHERE emisor_establecimiento_id = {$estabPruebaId}"
)->fetchColumn();
verificar("No se crearon registros duplicados en cpe_comprobantes (total: 2)", $totalComprobantes === 2);

// --------------------------------------------------------------------
// BLOQUE 5: Cross-Validation Vinculante — Establecimiento vs Serie
// --------------------------------------------------------------------
echo "\n--- BLOQUE 5: Cross-Validation Vinculante — Establecimiento vs Serie ---\n";

// Crear un segundo establecimiento ajeno bajo la misma empresa
$estabAjeno = new CpeEstablecimiento(
    null,
    $empresaPruebaId,
    null,
    '0001',
    "Empresa Test 1C2 Sucursal {$uidPrueba}",
    'Sucursal 1',
    'Av. Sucursal 456',
    '150101',
    'Lima',
    'Lima',
    'Lima',
    'BETA',
    'SUNAT_SOAP_DIRECTO',
    'ACTIVO'
);
$estabAjenoId = $estabRepo->crear($estabAjeno);

// Intentar emitir para EstabAjeno usando la serie de EstabPrueba
$cpeIncompatible = new CpeComprobante(
    null,
    $estabAjenoId, // Declara establecimiento 0001
    $seriePruebaId, // Pero apunta a serie del establecimiento 0000
    null,
    'FACTURA',
    'F001',
    0,
    '',
    "IDEMP_INCOMP_{$uidPrueba}",
    $rucPrueba,
    "Empresa Test 1C2 {$uidPrueba}",
    null,
    'Av. Sucursal 456',
    '150101',
    '0001',
    'Lima',
    'Lima',
    'Lima',
    '6',
    '20111111111',
    'Cliente SAC',
    null,
    null,
    null,
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
    'BORRADOR'
);

$capturoIncompatibilidad = false;
try {
    $servicioCorrelativo->emitirBorradorOAsignarCorrelativo($cpeIncompatible, $usuarioIdPrueba);
} catch (EstablecimientoSerieIncompatibleExcepcion $e) {
    $capturoIncompatibilidad = true;
}
verificar("Rechazo estricto con EstablecimientoSerieIncompatibleExcepcion ante cruce de serie", $capturoIncompatibilidad);

// Validar que la serie no avanzó
$serieInalterada = $serieRepo->obtenerPorId($seriePruebaId);
verificar("Serie original permanece en correlativo 2 tras intento inválido", $serieInalterada->obtenerUltimoCorrelativo() === 2);

// --------------------------------------------------------------------
// BLOQUE 6: Ajuste Vinculante C2-02 — Propiedad de Transacciones ($debeCerrarTx)
// --------------------------------------------------------------------
echo "\n--- BLOQUE 6: Ajuste Vinculante C2-02 — Propiedad de Transacciones Externas ---\n";

// Caso A: El llamador externo abre la transacción, el servicio emite, el llamador hace ROLLBACK
$pdo->beginTransaction();
verificar("Transacción externa iniciada por el llamador (\$pdo->inTransaction() === true)", $pdo->inTransaction() === true);

$cpeTxExterna = new CpeComprobante(
    null,
    $estabPruebaId,
    $seriePruebaId,
    null,
    'FACTURA',
    'F001',
    0,
    '',
    "IDEMP_TX_EXT_{$uidPrueba}",
    $rucPrueba,
    "Empresa Test 1C2 {$uidPrueba}",
    null,
    'Av. Test 123',
    '150101',
    '0000',
    'Lima',
    'Lima',
    'Lima',
    '6',
    '20888888888',
    'Cliente En Transacción SAC',
    null,
    null,
    null,
    'PE',
    false,
    null,
    null,
    null,
    null,
    'PEN',
    null,
    '50.00',
    '0.00',
    '0.00',
    '0.00',
    '0.00',
    '9.00',
    '0.00',
    '59.00',
    'BORRADOR'
);

$cpeEmitidoEnTx = $servicioCorrelativo->emitirBorradorOAsignarCorrelativo($cpeTxExterna, $usuarioIdPrueba);
verificar("CPE asignó correlativo 3 en memoria de la transacción", $cpeEmitidoEnTx->obtenerCorrelativo() === 3);
verificar("El servicio NO hizo commit: transacción sigue abierta para el llamador (\$pdo->inTransaction() === true)", $pdo->inTransaction() === true);

// El llamador decide revertir la transacción externa completa
$pdo->rollBack();
verificar("Transacción externa revertida exitosamente por el llamador", $pdo->inTransaction() === false);

// Verificar que ni el comprobante ni el correlativo 3 persisten
$seriePostRollback = $serieRepo->obtenerPorId($seriePruebaId);
verificar("cpe_series.ultimo_correlativo volvió a 2 tras el rollback del llamador", $seriePostRollback->obtenerUltimoCorrelativo() === 2);

$cpeRevertido = $comprobanteRepo->buscarPorIdempotencia($estabPruebaId, "IDEMP_TX_EXT_{$uidPrueba}");
verificar("El comprobante fue completamente revertido y no existe en BD", $cpeRevertido === null);

// Caso B: El llamador externo abre la transacción, el servicio emite, el llamador hace COMMIT
$pdo->beginTransaction();
$cpeTxCommit = new CpeComprobante(
    null,
    $estabPruebaId,
    $seriePruebaId,
    null,
    'FACTURA',
    'F001',
    0,
    '',
    "IDEMP_TX_COMMIT_{$uidPrueba}",
    $rucPrueba,
    "Empresa Test 1C2 {$uidPrueba}",
    null,
    'Av. Test 123',
    '150101',
    '0000',
    'Lima',
    'Lima',
    'Lima',
    '6',
    '20888888888',
    'Cliente En Transacción Commit SAC',
    null,
    null,
    null,
    'PE',
    false,
    null,
    null,
    null,
    null,
    'PEN',
    null,
    '50.00',
    '0.00',
    '0.00',
    '0.00',
    '0.00',
    '9.00',
    '0.00',
    '59.00',
    'BORRADOR'
);
$cpeEmitidoCommit = $servicioCorrelativo->emitirBorradorOAsignarCorrelativo($cpeTxCommit, $usuarioIdPrueba);
verificar("Transacción externa sigue abierta tras segunda llamada de servicio", $pdo->inTransaction() === true);
$pdo->commit();
verificar("Transacción externa consolidada por el llamador (\$pdo->commit())", $pdo->inTransaction() === false);

$seriePostCommit = $serieRepo->obtenerPorId($seriePruebaId);
verificar("cpe_series.ultimo_correlativo consolidado en 3 tras commit del llamador", $seriePostCommit->obtenerUltimoCorrelativo() === 3);

// --------------------------------------------------------------------
// BLOQUE 7: Aislamiento Multiempresa Estricto
// --------------------------------------------------------------------
echo "\n--- BLOQUE 7: Aislamiento Multiempresa Estricto ---\n";

// Crear una segunda empresa independiente
$pdo->prepare(
    "INSERT INTO empresas (codigo, tipo_documento_id, numero_documento, razon_social, direccion_fiscal, pais_id)
     VALUES (:cod, :tipo_doc, :num_doc, :razon, 'Av. Segunda Empresa 789', 1)"
)->execute([
    ':cod' => "EMP_2_{$uidPrueba}",
    ':tipo_doc' => $tipoDocRucTest,
    ':num_doc' => $rucEmpresa2,
    ':razon' => "Segunda Empresa Test {$uidPrueba}",
]);
$empresa2Id = (int) $pdo->lastInsertId();

$estabEmpresa2 = new CpeEstablecimiento(
    null,
    $empresa2Id,
    null,
    '0000',
    "Segunda Empresa Test {$uidPrueba}",
    'Segunda Empresa',
    'Av. Segunda Empresa 789',
    '150101',
    'Lima',
    'Lima',
    'Lima',
    'BETA',
    'SUNAT_SOAP_DIRECTO',
    'ACTIVO'
);
$estabEmp2Id = $estabRepo->crear($estabEmpresa2);

// Crear la serie F001 para la segunda empresa
$serieEmpresa2 = new CpeSerie(
    null,
    $estabEmp2Id,
    'FACTURA',
    'F001', // MISMO NOMBRE DE SERIE F001
    0,
    'F',
    'Serie F001 Empresa 2',
    'ACTIVO'
);
$serieEmp2Id = $serieRepo->crear($serieEmpresa2);

// Emitir F001-00000001 en Empresa 2
$cpeEmp2 = new CpeComprobante(
    null,
    $estabEmp2Id,
    $serieEmp2Id,
    null,
    'FACTURA',
    'F001',
    0,
    '',
    "IDEMP_EMP2_1_{$uidPrueba}",
    $rucEmpresa2,
    "Segunda Empresa Test {$uidPrueba}",
    null,
    'Av. Segunda 789',
    '150101',
    '0000',
    'Lima',
    'Lima',
    'Lima',
    '6',
    '20333333333',
    'Cliente Empresa 2 SAC',
    null,
    null,
    null,
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
    'BORRADOR'
);

$cpeEmp2Emitido = $servicioCorrelativo->emitirBorradorOAsignarCorrelativo($cpeEmp2, $usuarioIdPrueba);
verificar("Empresa 2 emite independientemente su correlativo 1 ('F001-00000001')", $cpeEmp2Emitido->obtenerCodigoFolioCompleto() === 'F001-00000001');

// Verificar que Empresa 1 sigue intacta en correlativo 3
$serieEmp1 = $serieRepo->obtenerPorId($seriePruebaId);
verificar("Empresa 1 conserva su serie en correlativo 3 sin alteración por Empresa 2", $serieEmp1->obtenerUltimoCorrelativo() === 3);

// --------------------------------------------------------------------
// BLOQUE 8: Concurrencia Real — Bloqueo Pesimista (Lock Wait Timeout)
// --------------------------------------------------------------------
echo "\n--- BLOQUE 8: Concurrencia Real — Bloqueo Pesimista (Lock Wait Timeout) ---\n";

// Conexión A adquiere el lock FOR UPDATE
$pdoA = BaseDatos::conexion();

$cfgDb = Configuracion::obtenerBaseDatos();
$dsnB = "mysql:host={$cfgDb['host']};port={$cfgDb['port']};dbname={$cfgDb['database']};charset={$cfgDb['charset']}";
$pdoB = new PDO($dsnB, $cfgDb['username'], $cfgDb['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

$pdoA->beginTransaction();
$stmtLockA = $pdoA->prepare("SELECT * FROM cpe_series WHERE id = :id FOR UPDATE");
$stmtLockA->execute([':id' => $seriePruebaId]);
$serieBloqueada = $stmtLockA->fetch(PDO::FETCH_ASSOC);
verificar("Conexión A adquiere bloqueo pesimista de fila en cpe_series (FOR UPDATE)", !empty($serieBloqueada));

// Conexión B configura lock wait timeout a 1 segundo e intenta adquirir el mismo lock
$pdoB->exec("SET innodb_lock_wait_timeout = 1");
$bloqueoDetectado = false;
$inicioEspera = microtime(true);

try {
    $stmtLockB = $pdoB->prepare("SELECT * FROM cpe_series WHERE id = :id FOR UPDATE");
    $stmtLockB->execute([':id' => $seriePruebaId]);
} catch (PDOException $e) {
    // MySQL 1205: Lock wait timeout exceeded
    if (str_contains($e->getMessage(), '1205') || $e->getCode() === 'HY000') {
        $bloqueoDetectado = true;
    }
}
$duracionEspera = microtime(true) - $inicioEspera;

$pdoA->rollBack(); // Liberar lock de conexión A
verificar("Conexión B experimentó contención pesimista real y timeout (1205 Lock wait timeout)", $bloqueoDetectado && $duracionEspera >= 0.8);

// --------------------------------------------------------------------
// BLOQUE 9: Concurrencia Real — Procesos Paralelos CLI y Resolución de Carrera
// --------------------------------------------------------------------
echo "\n--- BLOQUE 9: Concurrencia Real — Procesos Paralelos CLI y Resolución de Carrera ---\n";

$workerScript = dirname(__DIR__) . '/tests/helpers/cpe_concurrency_worker.php';
verificar("Script helper de concurrencia existe en tests/helpers/cpe_concurrency_worker.php", file_exists($workerScript));

// Crear serie exclusiva para prueba concurrente limpia
$serieConcurrente = new CpeSerie(
    null,
    $estabPruebaId,
    'BOLETA',
    'B001',
    0,
    'B',
    'Serie Concurrencia Paralela',
    'ACTIVO'
);
$serieConcId = $serieRepo->crear($serieConcurrente);

// Test 9.1: Dos procesos paralelos emiten simultáneamente con claves distintas
// Ambos deben tener éxito, obteniendo correlativos 1 y 2 consecutivamente sin colisión
$cmd1 = sprintf(
    'php "%s" --estab-id=%d --serie-id=%d --tipo=BOLETA --serie=B001 --idemp=CONC_KEY_1_%s --user-id=%d',
    $workerScript,
    $estabPruebaId,
    $serieConcId,
    $uidPrueba,
    $usuarioIdPrueba
);

$cmd2 = sprintf(
    'php "%s" --estab-id=%d --serie-id=%d --tipo=BOLETA --serie=B001 --idemp=CONC_KEY_2_%s --user-id=%d',
    $workerScript,
    $estabPruebaId,
    $serieConcId,
    $uidPrueba,
    $usuarioIdPrueba
);

$p1 = proc_open($cmd1, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes1);
$p2 = proc_open($cmd2, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes2);

$out1 = stream_get_contents($pipes1[1]);
$out2 = stream_get_contents($pipes2[1]);
fclose($pipes1[1]);
fclose($pipes1[2]);
proc_close($p1);
fclose($pipes2[1]);
fclose($pipes2[2]);
proc_close($p2);

$res1 = json_decode($out1, true);
$res2 = json_decode($out2, true);

verificar("Proceso concurrente 1 finalizó con éxito", !empty($res1['success']) && $res1['success'] === true);
verificar("Proceso concurrente 2 finalizó con éxito", !empty($res2['success']) && $res2['success'] === true);

$correlativosObtenidos = [$res1['correlativo'] ?? 0, $res2['correlativo'] ?? 0];
sort($correlativosObtenidos);

verificar(
    "Procesos concurrentes obtuvieron correlativos crecientes [1, 2] sin colisión (obtenidos: " . implode(', ', $correlativosObtenidos) . ")",
    $correlativosObtenidos === [1, 2]
);

$serieConcFinal = $serieRepo->obtenerPorId($serieConcId);
verificar("cpe_series.ultimo_correlativo se incrementó exactamente a 2", $serieConcFinal->obtenerUltimoCorrelativo() === 2);

// Test 9.2: Dos procesos paralelos intentan emitir con la EXACTA MISMA clave de idempotencia
// Solo debe consumirse un único correlativo (3) y ambos deben recibir el mismo ID de CPE
$claveCompartida = "IDEMP_COMPARTIDA_{$uidPrueba}";
$cmdDuplicado1 = sprintf(
    'php "%s" --estab-id=%d --serie-id=%d --tipo=BOLETA --serie=B001 --idemp=%s --user-id=%d',
    $workerScript,
    $estabPruebaId,
    $serieConcId,
    $claveCompartida,
    $usuarioIdPrueba
);
$cmdDuplicado2 = sprintf(
    'php "%s" --estab-id=%d --serie-id=%d --tipo=BOLETA --serie=B001 --idemp=%s --user-id=%d',
    $workerScript,
    $estabPruebaId,
    $serieConcId,
    $claveCompartida,
    $usuarioIdPrueba
);

$pd1 = proc_open($cmdDuplicado1, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipesD1);
$pd2 = proc_open($cmdDuplicado2, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipesD2);

$outD1 = stream_get_contents($pipesD1[1]);
$outD2 = stream_get_contents($pipesD2[1]);
fclose($pipesD1[1]);
fclose($pipesD1[2]);
proc_close($pd1);
fclose($pipesD2[1]);
fclose($pipesD2[2]);
proc_close($pd2);

$resD1 = json_decode($outD1, true);
$resD2 = json_decode($outD2, true);


verificar("Proceso con clave compartida 1 finalizó con éxito", !empty($resD1['success']) && $resD1['success'] === true);
verificar("Proceso con clave compartida 2 finalizó con éxito", !empty($resD2['success']) && $resD2['success'] === true);

verificar(
    "Ambos procesos obtuvieron el mismo correlativo 3 bajo carrera concurrente",
    ($resD1['correlativo'] ?? 0) === 3 && ($resD2['correlativo'] ?? 0) === 3
);

verificar(
    "Ambos procesos recibieron el mismo CPE ID bajo carrera concurrente (ID 1: " . ($resD1['cpe_id'] ?? 0) . ", ID 2: " . ($resD2['cpe_id'] ?? 0) . ")",
    !empty($resD1['cpe_id']) && $resD1['cpe_id'] === $resD2['cpe_id']
);

$serieConcPostIdemp = $serieRepo->obtenerPorId($serieConcId);
verificar("Correlativo no avanzó espuriamente y se detuvo en 3 tras carrera de idempotencia", $serieConcPostIdemp->obtenerUltimoCorrelativo() === 3);

// --------------------------------------------------------------------
// BLOQUE 10: Limpieza Defensiva y Cero Residuos
// --------------------------------------------------------------------
echo "\n--- BLOQUE 10: Limpieza Defensiva y Cero Residuos ---\n";

// Eliminar todos los registros creados durante esta ejecución de prueba
$pdo->beginTransaction();
try {
    // 1. Eliminar atribución de cargos
    $pdo->exec("DELETE lc FROM cpe_linea_cargos lc
                INNER JOIN cpe_lineas l ON lc.cpe_linea_id = l.id
                INNER JOIN cpe_comprobantes c ON l.cpe_id = c.id
                WHERE c.emisor_establecimiento_id IN ({$estabPruebaId}, {$estabAjenoId}, {$estabEmp2Id})");

    // 2. Eliminar líneas de comprobantes
    $pdo->exec("DELETE l FROM cpe_lineas l
                INNER JOIN cpe_comprobantes c ON l.cpe_id = c.id
                WHERE c.emisor_establecimiento_id IN ({$estabPruebaId}, {$estabAjenoId}, {$estabEmp2Id})");

    // 3. Eliminar comprobantes
    $pdo->exec("DELETE FROM cpe_comprobantes WHERE emisor_establecimiento_id IN ({$estabPruebaId}, {$estabAjenoId}, {$estabEmp2Id})");

    // 4. Eliminar series de prueba
    $pdo->exec("DELETE FROM cpe_series WHERE emisor_establecimiento_id IN ({$estabPruebaId}, {$estabAjenoId}, {$estabEmp2Id})");

    // 5. Eliminar establecimientos de prueba
    $pdo->exec("DELETE FROM cpe_establecimientos_configuracion WHERE id IN ({$estabPruebaId}, {$estabAjenoId}, {$estabEmp2Id})");

    // 6. Eliminar empresas de prueba
    $pdo->exec("DELETE FROM empresas WHERE id IN ({$empresaPruebaId}, {$empresa2Id})");

    $pdo->commit();
    verificar("Transacción de limpieza defensiva ejecutada con éxito", true);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    verificar("Transacción de limpieza defensiva falló: " . $e->getMessage(), false);
}

// Verificación de cero residuos en BD
$residuosCpe = (int) $pdo->query("SELECT COUNT(*) FROM cpe_comprobantes WHERE emisor_establecimiento_id IN ({$estabPruebaId}, {$estabAjenoId}, {$estabEmp2Id})")->fetchColumn();
$residuosSeries = (int) $pdo->query("SELECT COUNT(*) FROM cpe_series WHERE emisor_establecimiento_id IN ({$estabPruebaId}, {$estabAjenoId}, {$estabEmp2Id})")->fetchColumn();
$residuosEmpresas = (int) $pdo->query("SELECT COUNT(*) FROM empresas WHERE id IN ({$empresaPruebaId}, {$empresa2Id})")->fetchColumn();

verificar(
    "Cero residuos en base de datos tras ejecución (CPEs: {$residuosCpe}, series: {$residuosSeries}, empresas: {$residuosEmpresas})",
    $residuosCpe === 0 && $residuosSeries === 0 && $residuosEmpresas === 0
);

// --------------------------------------------------------------------
// BLOQUE 11: Gobernanza, Migraciones y Ranura 041 Libre
// --------------------------------------------------------------------
echo "\n--- BLOQUE 11: Gobernanza, Migraciones y Ranura 041 Libre ---\n";

$totalTablasFinal = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();
verificar("Total de tablas relacionales en MySQL permanece exactamente en 139 (actual: {$totalTablasFinal})", $totalTablasFinal === 139);

$archivos041 = glob(dirname(__DIR__) . '/SQL/migraciones/*041*');
verificar("Ranura de migración 041 estrictamente LIBRE (cero archivos 041)", empty($archivos041));

$diff040 = trim(shell_exec('git diff --name-only origin/main -- SQL/migraciones/040_cpe_esquema_fiscal.sql') ?? '');
verificar("Migración 040_cpe_esquema_fiscal.sql permanece 100% inmutable respecto a origin/main", empty($diff040));

$diffSql = trim(shell_exec('git diff --name-only origin/main -- SQL/camargo_pms.sql') ?? '');
verificar("SQL/camargo_pms.sql permanece 100% inmutable respecto a origin/main", empty($diffSql));

$diffAdmin = trim(shell_exec('git status --porcelain admin-dashboard/') ?? '');
verificar("Catálogo admin-dashboard/ permanece 100% intacto y limpio", empty($diffAdmin));

// --------------------------------------------------------------------
// RESUMEN FINAL
// --------------------------------------------------------------------
echo "\n====================================================================\n";
echo " RESUMEN: test_sunat_cpe_1c2.php\n";
echo " Total checks:  {$totalChecks}\n";
echo " Checks PASS:   {$passedChecks}\n";
echo " Checks FAIL:   {$failedChecks}\n";
echo " Estado:        " . ($failedChecks === 0 ? "100% PASS — HOMOLOGADO" : "CON FALLOS") . "\n";
echo "====================================================================\n";

if ($failedChecks > 0) {
    exit(1);
}
exit(0);
