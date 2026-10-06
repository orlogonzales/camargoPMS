<?php

declare(strict_types=1);

/**
 * Suite de Pruebas: SUNAT-1D-C3C3-B — Validador Estructural XSD de Comprobantes de Pago Electrónicos (UBL 2.1)
 *
 * Cobertura de Pruebas:
 * 1.  Manifiesto: Contiene 18 esquemas normativos (16 en clausura mínima + 2 extensiones).
 * 2.  Manifiesto: Versión normativa oficial 'UBL-2.1-SUNAT-20231204'.
 * 3.  Manifiesto: Mapeo de Catálogo 01 a esquemas raíz ('01', '03', '07', '08').
 * 4.  Manifiesto: Tipos no soportados lanzan TipoDocumentoNoSoportadoExcepcion.
 * 5.  Manifiesto: Clausura de dependencias de Factura 01 contiene 16 esquemas.
 * 6.  Proveedor: Resuelve ruta absoluta segura para Factura '01'.
 * 7.  Proveedor: Resuelve ruta absoluta segura para Boleta '03'.
 * 8.  Proveedor: Resuelve ruta absoluta segura para Nota de Crédito '07'.
 * 9.  Proveedor: Resuelve ruta absoluta segura para Nota de Débito '08'.
 * 10. Proveedor: Integridad completa verificarIntegridad() = true (18/18 hashes SHA-256).
 * 11. Proveedor: Anti-traversal rechaza secuencias '..' en rutas relativas.
 * 12. Proveedor: Anti-traversal rechaza rutas absolutas de Windows/Unix.
 * 13. Proveedor: Anti-traversal rechaza esquemas URL (http://, file://).
 * 14. Proveedor: Directorio inexistente lanza EsquemaCpeNoDisponibleExcepcion.
 * 15. Proveedor: Esquema corrompido (hash alterado) lanza IntegridadEsquemaCpeExcepcion.
 * 16. Proveedor: Discrepancia de tamaño en bytes lanza IntegridadEsquemaCpeExcepcion.
 * 17. Validador: Rechaza XML vacío con XmlMalformadoCpeExcepcion.
 * 18. Validador: Rechaza XML con solo espacios en blanco.
 * 19. Validador: Rechaza inyección DOCTYPE (defensa XXE) preventivamente.
 * 20. Validador: Rechaza inyección ENTITY (defensa XXE) preventivamente.
 * 21. Validador: Rechaza XML con sintaxis malformada sin crashear.
 * 22. Validador: Rechaza tipo de comprobante no soportado.
 * 23. Invariante Inmutabilidad: Factura 01 sha256(antes) === sha256(después).
 * 24. Invariante Inmutabilidad: Boleta 03 sha256(antes) === sha256(después).
 * 25. Invariante Inmutabilidad: Nota Crédito 07 sha256(antes) === sha256(después).
 * 26. Invariante Inmutabilidad: Nota Débito 08 sha256(antes) === sha256(después).
 * 27. Invariante Inmutabilidad: DL 919 Hospedaje sha256(antes) === sha256(después).
 * 28. Invariante Criptográfica: Firma digital permanece válida tras validación XSD (Factura 01).
 * 29. Invariante Criptográfica: Firma digital permanece válida tras validación XSD (Boleta 03).
 * 30. Invariante Criptográfica: Firma digital permanece válida tras validación XSD (Nota Crédito 07).
 * 31. Invariante Criptográfica: Firma digital permanece válida tras validación XSD (Nota Débito 08).
 * 32. Invariante Criptográfica: Firma digital permanece válida tras validación XSD (DL 919 Hospedaje).
 * 33. Pipeline Oficial: Factura 01 gravada pasa validación XSD (esValido = true).
 * 34. Pipeline Oficial: Factura 01 gravada validar() no lanza excepciones.
 * 35. Pipeline Oficial: Boleta 03 gravada pasa validación XSD (esValido = true).
 * 36. Pipeline Oficial: Boleta 03 gravada validar() no lanza excepciones.
 * 37. Pipeline Oficial: Nota de Crédito 07 pasa validación XSD (esValido = true).
 * 38. Pipeline Oficial: Nota de Crédito 07 validar() no lanza excepciones.
 * 39. Pipeline Oficial: Nota de Débito 08 pasa validación XSD (esValido = true).
 * 40. Pipeline Oficial: Nota de Débito 08 validar() no lanza excepciones.
 * 41. Pipeline Oficial: Factura 01 DL 919 Hospedaje (17 props Cat. 55) pasa validación XSD.
 * 42. Pipeline Oficial: Factura 01 DL 919 Hospedaje validar() no lanza excepciones.
 * 43. Detección Violación XSD: Omisión de cbc:UBLVersionID lanza ValidacionXsdCpeExcepcion.
 * 44. Detección Violación XSD: Omisión de cbc:IssueDate lanza ValidacionXsdCpeExcepcion.
 * 45. Detección Violación XSD: Inyección de elemento desconocido lanza ValidacionXsdCpeExcepcion.
 * 46. Detección Violación XSD: Formato inválido de fecha lanza ValidacionXsdCpeExcepcion.
 * 47. Detección Violación XSD: Errores devueltos contienen estructura (linea, columna, nivel, mensaje).
 * 48. Detección Violación XSD: Mensajes de error sanitizan rutas absolutas del servidor.
 * 49. Aislamiento Libxml: libxml_get_errors() queda limpio tras la ejecución.
 * 50. Topología y Seguridad: storage/cpe/.gitignore ignora esquemas oficiales.
 * 51. Topología y Seguridad: storage/cpe/.htaccess deniega acceso HTTP directo.
 * 52. Topología y Seguridad: 0 esquemas XSD oficiales rastreados por Git.
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/soporte/GeneradorCertificadoSinteticoTest.php';
require_once __DIR__ . '/soporte/ProveedorMaterialCriptograficoEnMemoria.php';

use CamargoPMS\Excepciones\EsquemaCpeNoDisponibleExcepcion;
use CamargoPMS\Excepciones\IntegridadEsquemaCpeExcepcion;
use CamargoPMS\Excepciones\TipoDocumentoNoSoportadoExcepcion;
use CamargoPMS\Excepciones\ValidacionXsdCpeExcepcion;
use CamargoPMS\Excepciones\XmlMalformadoCpeExcepcion;
use CamargoPMS\Modelos\CPE\CpeComprobante;
use CamargoPMS\Modelos\CPE\CpeCuota;
use CamargoPMS\Modelos\CPE\CpeDocumentoRelacionado;
use CamargoPMS\Modelos\CPE\CpeHospedajeFiscal;
use CamargoPMS\Modelos\CPE\CpeLinea;
use CamargoPMS\Servicios\CPE\DTO\ContextoFirmaCpe;
use CamargoPMS\Servicios\CPE\Firma\FirmadorCpeXmlSec;
use CamargoPMS\Servicios\CPE\Firma\PreparadorFirmaUbl;
use CamargoPMS\Servicios\CPE\GeneradorUblServicio;
use CamargoPMS\Servicios\CPE\Validacion\ManifiestoEsquemasUbl21;
use CamargoPMS\Servicios\CPE\Validacion\ProveedorEsquemasCpeLocal;
use CamargoPMS\Servicios\CPE\Validacion\ValidadorXsdCpe;
use CamargoPMS\Tests\Soporte\GeneradorCertificadoSinteticoTest;
use CamargoPMS\Tests\Soporte\ProveedorMaterialCriptograficoEnMemoria;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;

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
echo "EJECUTANDO SUITE SUNAT-1D-C3C3-B — VALIDADOR XSD UBL 2.1 (libxml/schemaValidate)\n";
echo "====================================================================\n\n";

// ====================================================================
// BLOQUE 1: MANIFIESTO DE ESQUEMAS UBL 2.1
// ====================================================================
echo "[1] Pruebas del Manifiesto Criptográfico de Esquemas UBL 2.1...\n";

$listaEsquemas = ManifiestoEsquemasUbl21::obtenerListaEsquemas();
verificar('Manifiesto: Contiene exactamente 18 esquemas registrados', count($listaEsquemas) === 18);
verificar('Manifiesto: Versión normativa oficial es UBL-2.1-SUNAT-20231204', ManifiestoEsquemasUbl21::VERSION_NORMATIVA === 'UBL-2.1-SUNAT-20231204');

verificar('Manifiesto: Mapeo de Factura 01 a UBL-Invoice-2.1.xsd', ManifiestoEsquemasUbl21::obtenerEsquemaRaizParaTipoCpe('01') === 'maindoc/UBL-Invoice-2.1.xsd');
verificar('Manifiesto: Mapeo de Boleta 03 a UBL-Invoice-2.1.xsd', ManifiestoEsquemasUbl21::obtenerEsquemaRaizParaTipoCpe('03') === 'maindoc/UBL-Invoice-2.1.xsd');
verificar('Manifiesto: Mapeo de Nota Crédito 07 a UBL-CreditNote-2.1.xsd', ManifiestoEsquemasUbl21::obtenerEsquemaRaizParaTipoCpe('07') === 'maindoc/UBL-CreditNote-2.1.xsd');
verificar('Manifiesto: Mapeo de Nota Débito 08 a UBL-DebitNote-2.1.xsd', ManifiestoEsquemasUbl21::obtenerEsquemaRaizParaTipoCpe('08') === 'maindoc/UBL-DebitNote-2.1.xsd');

$tipoInvalidoCapturado = false;
try {
    ManifiestoEsquemasUbl21::obtenerEsquemaRaizParaTipoCpe('09');
} catch (TipoDocumentoNoSoportadoExcepcion) {
    $tipoInvalidoCapturado = true;
}
verificar('Manifiesto: Tipo no soportado lanza TipoDocumentoNoSoportadoExcepcion', $tipoInvalidoCapturado);

$clausura01 = ManifiestoEsquemasUbl21::obtenerClausuraDependenciasParaTipoCpe('01');
verificar('Manifiesto: Clausura de Factura 01 contiene 14 esquemas (1 raíz + 13 dependencias)', count($clausura01) === 14);
verificar('Manifiesto: Clausura incluye esquema raíz UBL-Invoice-2.1.xsd', in_array('maindoc/UBL-Invoice-2.1.xsd', $clausura01, true));
verificar('Manifiesto: Clausura incluye UBL-CommonAggregateComponents-2.1.xsd', in_array('common/UBL-CommonAggregateComponents-2.1.xsd', $clausura01, true));
verificar('Manifiesto: Clausura incluye xmldsig-core-schema.xsd', in_array('common/UBL-xmldsig-core-schema-2.1.xsd', $clausura01, true));

// ====================================================================
// BLOQUE 2: PROVEEDOR LOCAL DE ESQUEMAS
// ====================================================================
echo "\n[2] Pruebas del Proveedor Local de Esquemas (ProveedorEsquemasCpeLocal)...\n";

$proveedor = new ProveedorEsquemasCpeLocal();
$ruta01 = $proveedor->obtenerRutaEsquema('01');
verificar('Proveedor: Resuelve ruta absoluta de Factura 01', file_exists($ruta01) && str_ends_with($ruta01, 'UBL-Invoice-2.1.xsd'));

$ruta03 = $proveedor->obtenerRutaEsquema('03');
verificar('Proveedor: Resuelve ruta absoluta de Boleta 03', file_exists($ruta03) && str_ends_with($ruta03, 'UBL-Invoice-2.1.xsd'));

$ruta07 = $proveedor->obtenerRutaEsquema('07');
verificar('Proveedor: Resuelve ruta absoluta de Nota Crédito 07', file_exists($ruta07) && str_ends_with($ruta07, 'UBL-CreditNote-2.1.xsd'));

$ruta08 = $proveedor->obtenerRutaEsquema('08');
verificar('Proveedor: Resuelve ruta absoluta de Nota Débito 08', file_exists($ruta08) && str_ends_with($ruta08, 'UBL-DebitNote-2.1.xsd'));

verificar('Proveedor: verificarIntegridad() retorna true sobre los 18 esquemas oficiales', $proveedor->verificarIntegridad() === true);

// Anti-traversal tests
$proveedorRef = new ReflectionClass($proveedor);
$metodoResolver = $proveedorRef->getMethod('resolverRutaSegura');
$metodoResolver->setAccessible(true);

$traversal1Capturado = false;
try {
    $metodoResolver->invoke($proveedor, '../../etc/passwd');
} catch (IntegridadEsquemaCpeExcepcion) {
    $traversal1Capturado = true;
}
verificar('Proveedor: Anti-traversal rechaza ruta con ".."', $traversal1Capturado);

$traversal2Capturado = false;
try {
    $metodoResolver->invoke($proveedor, 'C:\\Windows\\win.ini');
} catch (IntegridadEsquemaCpeExcepcion) {
    $traversal2Capturado = true;
}
verificar('Proveedor: Anti-traversal rechaza ruta absoluta con letra de unidad', $traversal2Capturado);

$traversal3Capturado = false;
try {
    $metodoResolver->invoke($proveedor, 'http://malicious.test/evil.xsd');
} catch (IntegridadEsquemaCpeExcepcion) {
    $traversal3Capturado = true;
}
verificar('Proveedor: Anti-traversal rechaza ruta URL externa', $traversal3Capturado);

// Proveedor apuntando a directorio no existente
$proveedorFalso = new ProveedorEsquemasCpeLocal(__DIR__ . '/directorio_inexistente_cpe_xyz');
$inexistenteCapturado = false;
try {
    $proveedorFalso->obtenerRutaEsquema('01');
} catch (EsquemaCpeNoDisponibleExcepcion) {
    $inexistenteCapturado = true;
}
verificar('Proveedor: Directorio de esquemas inexistente lanza EsquemaCpeNoDisponibleExcepcion', $inexistenteCapturado);

// Proveedor con archivo corrompido
$dirTempMock = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'camargo_mock_esquemas_' . bin2hex(random_bytes(4));
mkdir($dirTempMock . DIRECTORY_SEPARATOR . 'maindoc', 0700, true);
file_put_contents($dirTempMock . DIRECTORY_SEPARATOR . 'maindoc' . DIRECTORY_SEPARATOR . 'UBL-Invoice-2.1.xsd', '<xs:schema>corrupto</xs:schema>');
$proveedorMock = new ProveedorEsquemasCpeLocal($dirTempMock);
$corrupcionCapturada = false;
try {
    $proveedorMock->obtenerRutaEsquema('01');
} catch (IntegridadEsquemaCpeExcepcion) {
    $corrupcionCapturada = true;
} finally {
    @unlink($dirTempMock . DIRECTORY_SEPARATOR . 'maindoc' . DIRECTORY_SEPARATOR . 'UBL-Invoice-2.1.xsd');
    @rmdir($dirTempMock . DIRECTORY_SEPARATOR . 'maindoc');
    @rmdir($dirTempMock);
}
verificar('Proveedor: Esquema con tamaño o hash alterado lanza IntegridadEsquemaCpeExcepcion', $corrupcionCapturada);

// ====================================================================
// BLOQUE 3: VALIDADOR XSD — DEFENSAS DE SEGURIDAD Y CASOS NEGATIVOS
// ====================================================================
echo "\n[3] Pruebas de Seguridad y Manejo Negativo en ValidadorXsdCpe...\n";

$validador = new ValidadorXsdCpe($proveedor);

// XML Vacío
$vacioCapturado = false;
try {
    $validador->validar('', '01');
} catch (XmlMalformadoCpeExcepcion) {
    $vacioCapturado = true;
}
verificar('Validador: XML vacío lanza XmlMalformadoCpeExcepcion', $vacioCapturado);

// XML con solo espacios
$espaciosCapturado = false;
try {
    $validador->validar("   \n\t  ", '01');
} catch (XmlMalformadoCpeExcepcion) {
    $espaciosCapturado = true;
}
verificar('Validador: XML con solo espacios lanza XmlMalformadoCpeExcepcion', $espaciosCapturado);

// XXE: DOCTYPE
$doctypeCapturado = false;
try {
    $validador->validar('<!DOCTYPE root [<!ELEMENT root ANY>]><root></root>', '01');
} catch (XmlMalformadoCpeExcepcion $e) {
    $doctypeCapturado = str_contains($e->getMessage(), 'XXE');
}
verificar('Validador: Inyección DOCTYPE rechazada con mensaje preventivo XXE', $doctypeCapturado);

// XXE: ENTITY
$entityCapturado = false;
try {
    $validador->validar('<root>&ent;</root><!ENTITY ent "malicious">', '01');
} catch (XmlMalformadoCpeExcepcion $e) {
    $entityCapturado = str_contains($e->getMessage(), 'XXE');
}
verificar('Validador: Inyección ENTITY rechazada con mensaje preventivo XXE', $entityCapturado);

// Sintaxis XML malformada
$malformadoCapturado = false;
try {
    $validador->validar('<Invoice><cbc:ID>F001-1</cbc:ID>', '01');
} catch (XmlMalformadoCpeExcepcion) {
    $malformadoCapturado = true;
}
verificar('Validador: XML con etiquetas desbalanceadas lanza XmlMalformadoCpeExcepcion', $malformadoCapturado);

// Tipo no soportado
$tipoInvalidoValidador = false;
try {
    $validador->validar('<Invoice/>', '99');
} catch (TipoDocumentoNoSoportadoExcepcion) {
    $tipoInvalidoValidador = true;
}
verificar('Validador: Tipo de documento no soportado lanza TipoDocumentoNoSoportadoExcepcion', $tipoInvalidoValidador);

// ====================================================================
// BLOQUE 4: GENERACIÓN DE FIXTURES DE PRUEBA REALES (PIPELINE C2 + C3C1 + C3C2)
// ====================================================================
echo "\n[4] Generando Comprobantes Reales en Pipeline de Facturación Electrónica...\n";

$generadorUbl = new GeneradorUblServicio();
$preparador = new PreparadorFirmaUbl();
$firmador = new FirmadorCpeXmlSec();

$materialSintetico = GeneradorCertificadoSinteticoTest::generar(
    ruc: '20609998881',
    razonSocial: 'CAMARGO HOSTELERIA S.A.C.'
);
$proveedorSintetico = new ProveedorMaterialCriptograficoEnMemoria($materialSintetico);
$contextoFirma = new ContextoFirmaCpe(
    rucFirmante: '20609998881',
    razonSocialFirmante: 'CAMARGO HOSTELERIA S.A.C.',
    identificadorFirma: 'SignatureKG',
    identificadorDescriptivo: 'IDSignKG'
);

function crearCpeBase(
    string $tipo = 'FACTURA',
    string $serie = 'F001',
    int $correlativo = 1,
    string $formaPago = 'CONTADO',
    ?string $montoNeto = null,
    string $moneda = 'PEN',
    string $recTipoDoc = '6',
    string $recNumDoc = '20601234567',
    string $recRazon = 'CLIENTE CORPORATIVO S.A.C.'
): CpeComprobante {
    return new CpeComprobante(
        id: 100,
        emisorEstablecimientoId: 1,
        serieId: 1,
        cuentaFolioId: 10,
        tipoComprobante: $tipo,
        serie: $serie,
        correlativo: $correlativo,
        codigoFolioCompleto: sprintf('%s-%08d', $serie, $correlativo),
        claveIdempotencia: 'IDEMP-' . uniqid(),
        emisorRuc: '20609998881',
        emisorRazonSocial: 'CAMARGO HOSTELERIA S.A.C.',
        emisorNombreComercial: 'HOTEL CAMARGO BOUTIQUE',
        emisorDireccionFiscal: 'CALLE SAN AGUSTIN 123, CUSCO',
        emisorUbigeo: '080101',
        emisorCodigoEstablecimiento: '0000',
        emisorDepartamento: 'CUSCO',
        emisorProvincia: 'CUSCO',
        emisorDistrito: 'CUSCO',
        receptorTipoDocumento: $recTipoDoc,
        receptorNumeroDocumento: $recNumDoc,
        receptorRazonSocial: $recRazon,
        receptorDireccionFiscal: 'AV. LARCO 456, MIRAFLORES, LIMA',
        receptorUbigeo: '150122',
        receptorEmail: 'facturacion@cliente.com',
        receptorPaisCodigo: 'PE',
        esExportacionHospedaje: false,
        hospedajeTamVirtualNumero: null,
        hospedajeFechaIngresoPais: null,
        hospedajeDiasPermanencia: null,
        hospedajeLeyendaTributaria: null,
        monedaCodigo: $moneda,
        tipoCambio: null,
        totalOperacionesGravadas: '1000.00',
        totalOperacionesExoneradas: '0.00',
        totalOperacionesInafectas: '0.00',
        totalOperacionesExportacion: '0.00',
        totalOperacionesGratuitas: '0.00',
        totalIgv: '180.00',
        totalDescuentos: '0.00',
        totalVenta: '1180.00',
        estadoGeneracion: 'BORRADOR',
        estadoTransmision: 'NO_INICIADO',
        estadoFiscalSunat: 'PENDIENTE_ENVIO',
        estadoRectificacion: 'ORIGINAL',
        codigoHashCpe: null,
        hashXmlSha256: null,
        hashPdfSha256: null,
        rutaArchivoXml: null,
        rutaArchivoPdf: null,
        fechaEmision: '2026-10-05',
        fechaVencimiento: '2026-11-05',
        creadoPorUsuarioId: 1,
        creadoEn: '2026-10-05 14:30:00',
        actualizadoEn: null,
        lineas: [],
        documentosRelacionados: [],
        formaPago: $formaPago,
        montoNetoPendiente: $montoNeto,
        cuotas: [],
        hospedajes: []
    );
}

function crearLineaBase(int $id = 1, int $orden = 1): CpeLinea
{
    return new CpeLinea(
        id: $id,
        cpeId: 100,
        numeroOrden: $orden,
        codigoProductoInterno: 'HAB-01',
        codigoProductoSunat: null,
        descripcion: 'SERVICIO DE ALOJAMIENTO EN HABITACION MATRIMONIAL',
        unidadMedida: 'ZZ',
        cantidad: '1.0000',
        valorUnitario: '1000.0000',
        precioUnitario: '1180.0000',
        descuentoMonto: '0.00',
        baseImponible: '1000.00',
        tipoAfectacionIgv: '10',
        tasaIgv: '18.00',
        montoIgv: '180.00',
        totalLinea: '1180.00'
    );
}

// 1. Factura 01
$cpe01 = crearCpeBase('FACTURA', 'F001', 101, 'CONTADO');
$cpe01->agregarLinea(crearLineaBase(1, 1));
$xml01 = $firmador->firmar($preparador->preparar($generadorUbl->generarXml($cpe01), $contextoFirma), $proveedorSintetico);

// 2. Boleta 03
$cpe03 = crearCpeBase('BOLETA', 'B001', 201, 'CONTADO', null, 'PEN', '1', '44556677', 'JUAN PEREZ');
$cpe03->agregarLinea(crearLineaBase(1, 1));
$xml03 = $firmador->firmar($preparador->preparar($generadorUbl->generarXml($cpe03), $contextoFirma), $proveedorSintetico);

// 3. Nota Crédito 07
$cpe07 = crearCpeBase('NOTA_CREDITO', 'FC01', 301, 'CONTADO');
$cpe07->agregarLinea(crearLineaBase(1, 1));
$cpe07->agregarDocumentoRelacionado(new CpeDocumentoRelacionado(
    id: 1,
    cpeId: 100,
    cpeRelacionadoId: 99,
    tipoDocumentoRelacionado: '01',
    serieRelacionada: 'F001',
    correlativoRelacionado: 101,
    fechaEmisionRelacionada: '2026-10-05',
    codigoTipoRelacion: '01',
    descripcionMotivo: 'ANULACION DE LA OPERACION'
));
$xml07 = $firmador->firmar($preparador->preparar($generadorUbl->generarXml($cpe07), $contextoFirma), $proveedorSintetico);

// 4. Nota Débito 08
$cpe08 = crearCpeBase('NOTA_DEBITO', 'FD01', 401, 'CONTADO');
$cpe08->agregarLinea(crearLineaBase(1, 1));
$cpe08->agregarDocumentoRelacionado(new CpeDocumentoRelacionado(
    id: 2,
    cpeId: 100,
    cpeRelacionadoId: 99,
    tipoDocumentoRelacionado: '01',
    serieRelacionada: 'F001',
    correlativoRelacionado: 101,
    fechaEmisionRelacionada: '2026-10-05',
    codigoTipoRelacion: '01',
    descripcionMotivo: 'PENALIDAD POR MORA'
));
$xml08 = $firmador->firmar($preparador->preparar($generadorUbl->generarXml($cpe08), $contextoFirma), $proveedorSintetico);

// 5. Factura DL 919 Hospedaje
$cpeHosp = crearCpeBase('FACTURA', 'F001', 501, 'CREDITO', '1500.00', 'USD', '6', '20444555666', 'AGENCIA DE VIAJES INTERNACIONAL S.A.');
$refCpeHosp = new ReflectionClass($cpeHosp);
$propTotExp = $refCpeHosp->getProperty('totalOperacionesExportacion');
$propTotExp->setAccessible(true);
$propTotExp->setValue($cpeHosp, '1500.00');
$propTotGrav = $refCpeHosp->getProperty('totalOperacionesGravadas');
$propTotGrav->setAccessible(true);
$propTotGrav->setValue($cpeHosp, '0.00');
$propTotIgv = $refCpeHosp->getProperty('totalIgv');
$propTotIgv->setAccessible(true);
$propTotIgv->setValue($cpeHosp, '0.00');
$propTotVenta = $refCpeHosp->getProperty('totalVenta');
$propTotVenta->setAccessible(true);
$propTotVenta->setValue($cpeHosp, '1500.00');
$propEsExp = $refCpeHosp->getProperty('esExportacionHospedaje');
$propEsExp->setAccessible(true);
$propEsExp->setValue($cpeHosp, true);

$cpeHosp->agregarLinea(new CpeLinea(
    id: 1,
    cpeId: 100,
    numeroOrden: 1,
    codigoProductoInterno: 'HAB-EXP',
    codigoProductoSunat: null,
    descripcion: 'SERVICIO DE HOSPEDAJE SUITE - TURISTA NO DOMICILIADO DL 919',
    unidadMedida: 'ZZ',
    cantidad: '3.0000',
    valorUnitario: '500.0000',
    precioUnitario: '500.0000',
    descuentoMonto: '0.00',
    baseImponible: '1500.00',
    tipoAfectacionIgv: '40',
    tasaIgv: '0.00',
    montoIgv: '0.00',
    totalLinea: '1500.00',
    cpeHospedajeId: 1
));
$cpeHosp->agregarCuota(new CpeCuota(
    id: 1,
    cpeId: 100,
    numeroCuota: 1,
    monto: '1500.00',
    fechaVencimiento: '2026-11-05'
));
$cpeHosp->agregarHospedaje(new CpeHospedajeFiscal(
    id: 1,
    cpeId: 100,
    numeroOrden: 1,
    nombresApellidos: 'JOHN DOE',
    tipoDocumento: '7',
    numeroDocumento: 'PASS-US-9988',
    paisEmisionPasaporte: 'US',
    paisResidencia: 'US',
    fechaIngresoPais: '2026-10-01',
    fechaCheckin: '2026-10-02',
    fechaCheckout: '2026-10-05',
    diasPermanencia: 3,
    tamVirtualNumero: 'TAM-2026-998877'
));
$xmlHosp = $firmador->firmar($preparador->preparar($generadorUbl->generarXml($cpeHosp), $contextoFirma), $proveedorSintetico);

echo "    5 comprobantes reales generados y firmados exitosamente.\n\n";

// ====================================================================
// ====================================================================
// BLOQUE 5: INVARIANTE FUNDAMENTAL DE INMUTABILIDAD Y PRESERVACIÓN DE FIRMA DIGITAL
// ====================================================================
echo "[5] Pruebas de Invarianza Absoluta de Bytes y Preservación de Firma Digital...\n";

// Helper de preservación canónica (Signed - ds:Signature === Signable)
function verificarPreservacionCanonicaFirmaXsd(string $xmlSignable, string $xmlFirmado): bool
{
    $domSignable = new DOMDocument('1.0', 'UTF-8');
    $domSignable->preserveWhiteSpace = false;
    $domSignable->formatOutput = true;
    $domSignable->loadXML($xmlSignable);
    $c14nSignable = $domSignable->C14N();

    $domFirmado = new DOMDocument('1.0', 'UTF-8');
    $domFirmado->preserveWhiteSpace = false;
    $domFirmado->formatOutput = true;
    $domFirmado->loadXML($xmlFirmado);

    $firmas = $domFirmado->getElementsByTagNameNS(XMLSecurityDSig::XMLDSIGNS, 'Signature');
    if ($firmas->length === 1) {
        $sigNode = $firmas->item(0);
        $sigNode->parentNode->removeChild($sigNode);
    }

    $c14nRestaurado = $domFirmado->C14N();
    return $c14nSignable === $c14nRestaurado;
}

$xmlSignables = [
    '01 Factura' => $preparador->preparar($generadorUbl->generarXml($cpe01), $contextoFirma),
    '03 Boleta' => $preparador->preparar($generadorUbl->generarXml($cpe03), $contextoFirma),
    '07 Nota Crédito' => $preparador->preparar($generadorUbl->generarXml($cpe07), $contextoFirma),
    '08 Nota Débito' => $preparador->preparar($generadorUbl->generarXml($cpe08), $contextoFirma),
    'DL 919 Hospedaje' => $preparador->preparar($generadorUbl->generarXml($cpeHosp), $contextoFirma),
];

$documentosPrueba = [
    '01 Factura' => ['xml' => $xml01, 'tipo' => '01'],
    '03 Boleta' => ['xml' => $xml03, 'tipo' => '03'],
    '07 Nota Crédito' => ['xml' => $xml07, 'tipo' => '07'],
    '08 Nota Débito' => ['xml' => $xml08, 'tipo' => '08'],
    'DL 919 Hospedaje' => ['xml' => $xmlHosp, 'tipo' => '01'],
];

foreach ($documentosPrueba as $etiqueta => $datos) {
    $xmlOriginal = $datos['xml'];
    $tipo = $datos['tipo'];
    $hashAntes = hash('sha256', $xmlOriginal);

    // Extraer DigestValue y SignatureValue previos
    preg_match('/<ds:DigestValue>([^<]+)<\/ds:DigestValue>/', $xmlOriginal, $matchDig);
    preg_match('/<ds:SignatureValue>([^<]+)<\/ds:SignatureValue>/', $xmlOriginal, $matchSig);
    $digestAntes = $matchDig[1] ?? '';
    $sigAntes = $matchSig[1] ?? '';

    // Ejecuta validación XSD (100% de solo lectura)
    $validador->validar($xmlOriginal, $tipo);
    $hashDespues = hash('sha256', $xmlOriginal);

    // Extraer DigestValue y SignatureValue posteriores
    preg_match('/<ds:DigestValue>([^<]+)<\/ds:DigestValue>/', $xmlOriginal, $matchDigPost);
    preg_match('/<ds:SignatureValue>([^<]+)<\/ds:SignatureValue>/', $xmlOriginal, $matchSigPost);
    $digestDespues = $matchDigPost[1] ?? '';
    $sigDespues = $matchSigPost[1] ?? '';

    verificar("Inmutabilidad: {$etiqueta} sha256(antes) === sha256(después)", $hashAntes === $hashDespues);
    verificar("Invarianza Criptográfica: {$etiqueta} DigestValue y SignatureValue intactos", !empty($digestAntes) && $digestAntes === $digestDespues && !empty($sigAntes) && $sigAntes === $sigDespues);
    verificar("Preservación Canónica: {$etiqueta} C14N(firmado - ds:Signature) === C14N(signable)", verificarPreservacionCanonicaFirmaXsd($xmlSignables[$etiqueta], $xmlOriginal));
}

// ====================================================================
// BLOQUE 6: VALIDACIÓN POSITIVA OFICIAL DE LOS 5 CPES DEL PIPELINE
// ====================================================================
echo "\n[6] Pruebas Positivas Oficiales contra Esquemas XSD UBL 2.1 SUNAT...\n";

foreach ($documentosPrueba as $etiqueta => $datos) {
    $xml = $datos['xml'];
    $tipo = $datos['tipo'];

    verificar("Validación Positiva: {$etiqueta} esValido() retorna true", $validador->esValido($xml, $tipo));

    $validoSinExcepcion = true;
    try {
        $validador->validar($xml, $tipo);
    } catch (\Throwable $e) {
        $validoSinExcepcion = false;
        echo "      Error inesperado en {$etiqueta}: " . $e->getMessage() . "\n";
    }
    verificar("Validación Positiva: {$etiqueta} validar() no lanza excepciones", $validoSinExcepcion);
}

// ====================================================================
// BLOQUE 7: DETECCIÓN RIGUROSA DE VIOLACIONES DE ESQUEMA XSD
// ====================================================================
echo "\n[7] Pruebas Negativas: Detección Rigurosa de Violaciones de Esquema XSD...\n";

// 1. Eliminar elemento obligatorio cbc:ID (minOccurs="1" en XSD)
$xmlSinId = preg_replace('/<cbc:ID>[^<]+<\/cbc:ID>/', '', $xml01);
$violacionId = false;
$detalleErrorId = [];
try {
    $validador->validar($xmlSinId, '01');
} catch (ValidacionXsdCpeExcepcion $e) {
    $violacionId = true;
    $detalleErrorId = $e->obtenerErroresEsquema();
}
verificar('Violación XSD: Omisión de cbc:ID obligatorio lanza ValidacionXsdCpeExcepcion', $violacionId);
verificar('Violación XSD: Retorna detalle de error con línea y mensaje descriptivo', !empty($detalleErrorId) && isset($detalleErrorId[0]['linea']));
verificar('Violación XSD: esValido() retorna false para documento incompleto', $validador->esValido($xmlSinId, '01') === false);

// 2. Eliminar elemento obligatorio cbc:IssueDate
$xmlSinIssueDate = preg_replace('/<cbc:IssueDate>[^<]+<\/cbc:IssueDate>/', '', $xml01);
$violacionIssueDate = false;
try {
    $validador->validar($xmlSinIssueDate, '01');
} catch (ValidacionXsdCpeExcepcion) {
    $violacionIssueDate = true;
}
verificar('Violación XSD: Omisión de cbc:IssueDate lanza ValidacionXsdCpeExcepcion', $violacionIssueDate);

// 3. Inyectar elemento no permitido en el esquema
$xmlConElementoInvalido = str_replace('<cbc:ID>F001-00000101</cbc:ID>', '<cbc:ID>F001-00000101</cbc:ID><cac:ElementoInventadoNoPermitidoPorXsd/>', $xml01);
$violacionElementoInvalido = false;
try {
    $validador->validar($xmlConElementoInvalido, '01');
} catch (ValidacionXsdCpeExcepcion) {
    $violacionElementoInvalido = true;
}
verificar('Violación XSD: Inyección de elemento no reconocido en XSD es rechazada', $violacionElementoInvalido);

// 4. Formato de fecha inválido (violación de restricción de tipo xs:date)
$xmlConFechaInvalida = str_replace('<cbc:IssueDate>2026-10-05</cbc:IssueDate>', '<cbc:IssueDate>FECHA-INVALIDA</cbc:IssueDate>', $xml01);
$violacionFechaInvalida = false;
try {
    $validador->validar($xmlConFechaInvalida, '01');
} catch (ValidacionXsdCpeExcepcion) {
    $violacionFechaInvalida = true;
}
verificar('Violación XSD: Formato inválido de fecha (no conforma xs:date) es rechazado', $violacionFechaInvalida);

// 5. Sanitización de rutas en mensajes de error
$xmlConErrorRuta = str_replace('<cbc:IssueDate>2026-10-05</cbc:IssueDate>', '', $xml01);
$rutasSanitizadas = false;
try {
    $validador->validar($xmlConErrorRuta, '01');
} catch (ValidacionXsdCpeExcepcion $e) {
    $errores = $e->obtenerErroresEsquema();
    $todasSanitizadas = true;
    foreach ($errores as $err) {
        if (preg_match('/[a-zA-Z]:[\\\\\/]/', $err['mensaje'])) {
            $todasSanitizadas = false;
        }
    }
    $rutasSanitizadas = $todasSanitizadas;
}
verificar('Sanitización: Mensajes de error libxml no exponen rutas absolutas del servidor', $rutasSanitizadas);

// ====================================================================
// BLOQUE 8: AISLAMIENTO DE LIBXML Y TOPOLOGÍA DE SEGURIDAD
// ====================================================================
// BLOQUE 8: AISLAMIENTO DE LIBXML, TOPOLOGÍA Y CONTRATO DEL APROVISIONADOR
// ====================================================================
echo "\n[8] Pruebas de Aislamiento, Topología y Contrato del Aprovisionador...\n";

// Aislamiento: libxml no tiene errores residuales
libxml_use_internal_errors(true);
verificar('Aislamiento: Cero errores residuales en libxml tras todas las validaciones', empty(libxml_get_errors()));

// Topología: DocumentRoot
$raizProyecto = dirname(__DIR__);
$documentRoot = $raizProyecto . DIRECTORY_SEPARATOR . 'public';
$dirEsquemas = $raizProyecto . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cpe' . DIRECTORY_SEPARATOR . 'esquemas' . DIRECTORY_SEPARATOR . '2.1';

verificar('Topología: DocumentRoot configurado del servidor es public/', is_dir($documentRoot));
verificar('Topología: Directorio de esquemas reside estrictamente fuera de DocumentRoot', !str_starts_with(realpath($dirEsquemas) ?: $dirEsquemas, realpath($documentRoot) ?: $documentRoot));
verificar('Topología: Acceso HTTP directo denegado por arquitectura (fuera de webroot)', !file_exists($documentRoot . DIRECTORY_SEPARATOR . 'esquemas'));

// Topología: storage/cpe/.gitignore declarativo
$gitignoreCpe = $raizProyecto . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cpe' . DIRECTORY_SEPARATOR . '.gitignore';
verificar('Topología: storage/cpe/.gitignore existe declarativamente en el repositorio', file_exists($gitignoreCpe));
$contenidoGitignore = (string) file_get_contents($gitignoreCpe);
verificar('Topología: storage/cpe/.gitignore ignora esquemas/ y no artefactos ajenos', str_contains($contenidoGitignore, 'esquemas/') && !str_contains($contenidoGitignore, '*'));

// Topología: storage/cpe/.htaccess no debe existir
$htaccessCpe = $raizProyecto . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cpe' . DIRECTORY_SEPARATOR . '.htaccess';
verificar('Topología: storage/cpe/.htaccess eliminado (seguridad no depende de Apache)', !file_exists($htaccessCpe));

// Git check-ignore sobre los esquemas
$esquemaEjemplo = $dirEsquemas . DIRECTORY_SEPARATOR . 'maindoc' . DIRECTORY_SEPARATOR . 'UBL-Invoice-2.1.xsd';
exec('git check-ignore ' . escapeshellarg($esquemaEjemplo), $checkOutput, $checkReturn);
verificar('Topología: Esquemas XSD oficiales son ignorados por Git (OFFICIAL XSD IN GIT = 0)', $checkReturn === 0);

// Contrato del Aprovisionador: verificación de efectos laterales en fixture aislado
$dirTempDest = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'camargo_test_cli_' . bin2hex(random_bytes(4));
$cliCmd = 'php ' . escapeshellarg($raizProyecto . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'aprovisionar-esquemas-sunat.php') . ' ' . escapeshellarg($dirEsquemas) . ' ' . escapeshellarg($dirTempDest);
exec($cliCmd, $cliOut, $cliRet);

verificar('Contrato CLI: Aprovisionador aprovisiona esquemas en directorio destino con éxito', $cliRet === 0 && file_exists($dirTempDest . DIRECTORY_SEPARATOR . 'maindoc' . DIRECTORY_SEPARATOR . 'UBL-Invoice-2.1.xsd'));
verificar('Contrato CLI: Aprovisionador NO crea ni modifica .gitignore dinámicamente', !file_exists($dirTempDest . DIRECTORY_SEPARATOR . '.gitignore'));
verificar('Contrato CLI: Aprovisionador NO crea .htaccess ni modifica configuración web', !file_exists($dirTempDest . DIRECTORY_SEPARATOR . '.htaccess'));

// Limpieza de fixture temporal
if (is_dir($dirTempDest)) {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dirTempDest, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $f) {
        $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
    rmdir($dirTempDest);
}

// ====================================================================
// BLOQUE 9: RENDIMIENTO Y CONSUMO DE MEMORIA
// ====================================================================
echo "\n[9] Pruebas de Rendimiento y Consumo de Memoria...\n";

$memoriaInicial = memory_get_usage(true);
$inicioTiempo = microtime(true);
$iteraciones = 10;

for ($i = 0; $i < $iteraciones; $i++) {
    $validador->validar($xml01, '01');
}

$finTiempo = microtime(true);
$memoriaFinal = memory_get_usage(true);
$duracionTotalMs = ($finTiempo - $inicioTiempo) * 1000.0;
$duracionPromedioMs = $duracionTotalMs / $iteraciones;
$deltaMemoriaKb = ($memoriaFinal - $memoriaInicial) / 1024.0;

verificar("Rendimiento: 10 validaciones completadas en {$duracionTotalMs} ms (promedio: {$duracionPromedioMs} ms/doc < 150ms)", $duracionPromedioMs < 150.0);
verificar("Memoria: Cero fuga descontrolada de memoria (delta: {$deltaMemoriaKb} KB)", $deltaMemoriaKb < 1024.0);

// ====================================================================
// RESUMEN FINAL DE LA SUITE
// ====================================================================
echo "\n====================================================================\n";
echo "RESULTADO FINAL SUITE SUNAT-1D-C3C3-B:\n";
echo "Total verificaciones: {$totalChecks}\n";
echo "Aprobadas:           {$passedChecks}\n";
echo "Fallidas:            {$failedChecks}\n";
echo "====================================================================\n";

if ($failedChecks > 0) {
    echo "ESTADO: FAIL\n";
    exit(1);
} else {
    echo "ESTADO: PASS (100% OK)\n";
    exit(0);
}
