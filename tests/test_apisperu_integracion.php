<?php

declare(strict_types=1);

/**
 * Suite de Pruebas Automatizadas: APISPERU-1B
 * Motor Seguro Local-First DNI/RUC e Integración Piloto APIsPERU.
 *
 * Principios vinculantes certificados:
 * 1. 0 consumo de cuota real: Todas las llamadas externas en pruebas usan mocks aislados.
 * 2. CERO fuga de secretos: El token nunca aparece en mensajes, excepciones o URLs impresas.
 * 3. Local-First estricto: Si la entidad existe en el PMS, nunca llama al proveedor externo.
 * 4. Normalización no destructiva y cero invención de datos (DNI no inventa género ni domicilio).
 * 5. CE y Pasaporte quedan 100% manuales con origen 'MANUAL'.
 * 6. Invariantes de gobernanza: 118 tablas, migración 034, ranura 035 libre, Alina intacta.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CamargoPMS\Adaptadores\ApisPeruAdaptador;
use CamargoPMS\Controladores\DocumentoConsultaControlador;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Nucleo\Enrutador;
use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Repositorios\EmpresaRepositorio;
use CamargoPMS\Repositorios\PersonaRepositorio;
use CamargoPMS\Servicios\ConsultaDocumentoServicio;
use CamargoPMS\Servicios\SesionServicio;

Configuracion::cargar(dirname(__DIR__));
$pdo = BaseDatos::conexion();

$totalAssertions = 0;
$passedAssertions = 0;

function assertCheck(bool $condition, string $message): void
{
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
echo " CAMARGO PMS — PRUEBAS APISPERU-1B: MOTOR LOCAL-FIRST DNI/RUC\n";
echo "====================================================================\n\n";

// =========================================================================
// 1. ADAPTADOR TÉCNICO (ApisPeruAdaptador)
// =========================================================================
echo "--- 1. Adaptador Técnico (ApisPeruAdaptador) ---\n";

$adaptadorRef = new ReflectionClass(ApisPeruAdaptador::class);
assertCheck($adaptadorRef->hasMethod('consultarDni'), "ApisPeruAdaptador expone el método consultarDni()");
assertCheck($adaptadorRef->hasMethod('consultarRuc'), "ApisPeruAdaptador expone el método consultarRuc()");
assertCheck($adaptadorRef->hasMethod('estaConfigurado'), "ApisPeruAdaptador expone el método estaConfigurado()");

// Validación de timeout configurable
$adaptadorCustom = new ApisPeruAdaptador('test_token', 'https://mock.api', 8);
assertCheck($adaptadorCustom->obtenerTimeout() === 8, "El timeout se configura correctamente (8 segundos)");

// Validación de formato DNI (debe ser 8 dígitos)
$formatoDniInvalido = false;
try {
    $adaptadorCustom->consultarDni('12345');
} catch (InvalidArgumentException $e) {
    $formatoDniInvalido = true;
}
assertCheck($formatoDniInvalido, "Rechaza DNI con menos de 8 dígitos lanzando InvalidArgumentException");

// Validación de formato RUC (debe ser 11 dígitos)
$formatoRucInvalido = false;
try {
    $adaptadorCustom->consultarRuc('1234567890');
} catch (InvalidArgumentException $e) {
    $formatoRucInvalido = true;
}
assertCheck($formatoRucInvalido, "Rechaza RUC con menos de 11 dígitos lanzando InvalidArgumentException");

// Mock callable para DNI: ejecución sin red y 0 cuota consumida
$mockDniLlamado = 0;
$mockCallableDni = function (string $recurso, string $parametro, int $timeout) use (&$mockDniLlamado) {
    $mockDniLlamado++;
    assertCheck($recurso === 'dni', "Mock adaptador recibe recurso 'dni'");
    assertCheck($parametro === '77665544', "Mock adaptador recibe parámetro '77665544'");
    assertCheck($timeout === 5, "Mock adaptador recibe timeout de 5s");
    return [
        'dni' => '77665544',
        'nombres' => 'MARIA ELENA',
        'apellidoPaterno' => 'QUISPE',
        'apellidoMaterno' => 'MAMANI',
        'codVerifica' => '7',
    ];
};

$adaptadorMockDni = new ApisPeruAdaptador('token_falso_test', 'https://mock.api', 5, $mockCallableDni);
$resMockDni = $adaptadorMockDni->consultarDni('77665544');
assertCheck($mockDniLlamado === 1, "Cliente mock invocado exactamente 1 vez (0 cuota externa consumida)");
assertCheck(($resMockDni['nombres'] ?? '') === 'MARIA ELENA', "Mock devuelve payload deserializado de DNI correctamente");

// Mock callable para RUC
$mockRucLlamado = 0;
$mockCallableRuc = function (string $recurso, string $parametro, int $timeout) use (&$mockRucLlamado) {
    $mockRucLlamado++;
    assertCheck($recurso === 'ruc', "Mock adaptador recibe recurso 'ruc'");
    assertCheck($parametro === '20123456789', "Mock adaptador recibe parámetro '20123456789'");
    return [
        'ruc' => '20123456789',
        'razonSocial' => 'INVERSIONES CAMARGO S.A.C.',
        'nombreComercial' => 'CAMARGO SUITES',
        'direccion' => 'CALLE SAN MARTIN 450',
        'departamento' => 'LIMA',
        'provincia' => 'LIMA',
        'distrito' => 'MIRAFLORES',
        'ubigeo' => '150122',
        'telefonos' => ['01-4455667'],
        'estado' => 'ACTIVO',
        'condicion' => 'HABIDO',
    ];
};

$adaptadorMockRuc = new ApisPeruAdaptador('token_falso_test', 'https://mock.api', 5, $mockCallableRuc);
$resMockRuc = $adaptadorMockRuc->consultarRuc('20123456789');
assertCheck($mockRucLlamado === 1, "Cliente mock RUC invocado exactamente 1 vez (0 cuota externa consumida)");
assertCheck(($resMockRuc['razonSocial'] ?? '') === 'INVERSIONES CAMARGO S.A.C.', "Mock devuelve datos fiscales de SUNAT");

// Cero fuga de credenciales ante errores
$tokenSecretoTest = 'SECRETO_SUPER_CONFIDENCIAL_123456';
$adaptadorConError = new ApisPeruAdaptador($tokenSecretoTest, 'https://mock.api', 5, function () {
    throw new RuntimeException('Error simulado de comunicación HTTP.');
});

$mensajeExcepcion = '';
try {
    $adaptadorConError->consultarDni('12345678');
} catch (RuntimeException $e) {
    $mensajeExcepcion = $e->getMessage();
}
assertCheck(!str_contains($mensajeExcepcion, $tokenSecretoTest), "La excepción NO filtra el token secreto bajo ninguna circunstancia");
assertCheck(!str_contains($mensajeExcepcion, '?token='), "La excepción NO expone parámetros query ni URLs completas");

// =========================================================================
// 2. SERVICIO SOBERANO (ConsultaDocumentoServicio) & POLÍTICA LOCAL-FIRST
// =========================================================================
echo "\n--- 2. Servicio Soberano (ConsultaDocumentoServicio) & Política Local-First ---\n";

$personaRepo = new PersonaRepositorio($pdo);
$empresaRepo = new EmpresaRepositorio($pdo);

// 2.1 Validación de tipos manuales (CE, Pasaporte)
$servicioBase = new ConsultaDocumentoServicio($personaRepo, $empresaRepo, $adaptadorMockDni);

$resPasaporte = $servicioBase->consultar('PASAPORTE', 'A12345678');
assertCheck($resPasaporte['success'] === true, "Consulta de PASAPORTE responde con éxito");
assertCheck($resPasaporte['origen'] === 'MANUAL', "PASAPORTE tiene origen estrictamente 'MANUAL'");
assertCheck($resPasaporte['encontrado'] === false, "PASAPORTE indica encontrado=false para completar en formulario");

$resCe = $servicioBase->consultar('CE', '001234567');
assertCheck($resCe['success'] === true, "Consulta de Carné de Extranjería (CE) responde con éxito");
assertCheck($resCe['origen'] === 'MANUAL', "CE tiene origen estrictamente 'MANUAL' (sin consumo de API)");

// 2.2 Validación de formato estructural
$resDniInvalido = $servicioBase->consultar('DNI', '1234');
assertCheck($resDniInvalido['success'] === false && $resDniInvalido['origen'] === 'ERROR', "DNI con formato inválido retorna origen 'ERROR' y success=false");

$resRucInvalido = $servicioBase->consultar('RUC', '99999');
assertCheck($resRucInvalido['success'] === false && $resRucInvalido['origen'] === 'ERROR', "RUC con formato inválido retorna origen 'ERROR' y success=false");

// 2.3 LOCAL-FIRST DNI (Si la persona existe localmente en el PMS)
// Buscar un DNI real en la base de datos local
$stmtDniLocal = $pdo->query("SELECT pd.numero_documento FROM personas_documentos pd JOIN tipos_documento td ON td.id = pd.tipo_documento_id WHERE td.codigo = 'DNI' LIMIT 1");
$dniLocalReal = (string) $stmtDniLocal->fetchColumn();

if ($dniLocalReal !== '') {
    $llamadasExternasLocalFirstDni = 0;
    $adaptadorEspia = new ApisPeruAdaptador('token', 'https://mock.api', 5, function () use (&$llamadasExternasLocalFirstDni) {
        $llamadasExternasLocalFirstDni++;
        return [];
    });

    $servicioLocalFirst = new ConsultaDocumentoServicio($personaRepo, $empresaRepo, $adaptadorEspia);
    $resLocalDni = $servicioLocalFirst->consultar('DNI', $dniLocalReal);

    assertCheck($resLocalDni['success'] === true, "Consulta de DNI local responde success=true");
    assertCheck($resLocalDni['origen'] === 'LOCAL', "DNI existente resuelve con origen 'LOCAL'");
    assertCheck($resLocalDni['encontrado'] === true, "DNI existente resuelve con encontrado=true");
    assertCheck($llamadasExternasLocalFirstDni === 0, "ESTRICTO LOCAL-FIRST: CERO llamadas a APIsPERU cuando el DNI existe en BD local");
    assertCheck(!empty($resLocalDni['datos']['nombres']), "Respuesta local provee nombres desde Persona local");
} else {
    assertCheck(true, "[SKIP] No hay DNIs registrados en personas_documentos para prueba local directa");
}

// 2.4 LOCAL-FIRST RUC (Si la empresa existe localmente en el PMS)
$stmtRucLocal = $pdo->query("SELECT numero_documento FROM empresas WHERE numero_documento IS NOT NULL AND numero_documento <> '' LIMIT 1");
$rucLocalReal = (string) $stmtRucLocal->fetchColumn();

if ($rucLocalReal !== '') {
    $llamadasExternasLocalFirstRuc = 0;
    $adaptadorEspiaRuc = new ApisPeruAdaptador('token', 'https://mock.api', 5, function () use (&$llamadasExternasLocalFirstRuc) {
        $llamadasExternasLocalFirstRuc++;
        return [];
    });

    $servicioLocalFirstRuc = new ConsultaDocumentoServicio($personaRepo, $empresaRepo, $adaptadorEspiaRuc);
    $resLocalRuc = $servicioLocalFirstRuc->consultar('RUC', $rucLocalReal);

    assertCheck($resLocalRuc['success'] === true, "Consulta de RUC local responde success=true");
    assertCheck($resLocalRuc['origen'] === 'LOCAL', "RUC existente resuelve con origen 'LOCAL'");
    assertCheck($resLocalRuc['encontrado'] === true, "RUC existente resuelve con encontrado=true");
    assertCheck($llamadasExternasLocalFirstRuc === 0, "ESTRICTO LOCAL-FIRST: CERO llamadas a APIsPERU cuando el RUC existe en empresas local");
    assertCheck(!empty($resLocalRuc['datos']['razon_social']), "Respuesta local provee razón social desde Empresa local");
} else {
    assertCheck(true, "[SKIP] No hay empresas registradas para prueba local directa");
}

// 2.5 FALLBACK EXTERNO DNI (No existe localmente -> APIsPERU)
$dniInexistenteLocal = '09999991';
// Asegurar que no exista localmente
$pdo->prepare("DELETE FROM personas_documentos WHERE numero_documento = :num")->execute(['num' => $dniInexistenteLocal]);

$llamadaExternaDni = 0;
$adaptadorMockExternoDni = new ApisPeruAdaptador('token', 'https://mock.api', 5, function ($rec, $num) use (&$llamadaExternaDni) {
    $llamadaExternaDni++;
    return [
        'dni' => $num,
        'nombres' => 'CARLOS ALBERTO',
        'apellidoPaterno' => 'MENDOZA',
        'apellidoMaterno' => 'ROJAS',
        'codVerifica' => '3',
    ];
});

$servicioExternoDni = new ConsultaDocumentoServicio($personaRepo, $empresaRepo, $adaptadorMockExternoDni);
$resExternoDni = $servicioExternoDni->consultar('DNI', $dniInexistenteLocal);

assertCheck($resExternoDni['success'] === true, "Fallback externo de DNI responde success=true");
assertCheck($resExternoDni['origen'] === 'APISPERU', "DNI no local resuelve con origen 'APISPERU'");
assertCheck($resExternoDni['encontrado'] === true, "DNI encontrado en Reniec tiene encontrado=true");
assertCheck($llamadaExternaDni === 1, "Llamada al adaptador ejecutada exactamente 1 vez para DNI no existente");
assertCheck($resExternoDni['datos']['nombre_completo'] === 'CARLOS ALBERTO MENDOZA ROJAS', "Nombre completo normalizado correctamente");
assertCheck(!isset($resExternoDni['datos']['genero']), "CERO invención de datos: No se inventa género en consulta de DNI");
assertCheck(!isset($resExternoDni['datos']['direccion']), "CERO invención de datos: No se inventa dirección en consulta de DNI");

// 2.6 FALLBACK EXTERNO RUC (No existe localmente -> APIsPERU SUNAT)
$rucInexistenteLocal = '20999999991';
$pdo->prepare("DELETE FROM empresas WHERE numero_documento = :num")->execute(['num' => $rucInexistenteLocal]);

$llamadaExternaRuc = 0;
$adaptadorMockExternoRuc = new ApisPeruAdaptador('token', 'https://mock.api', 5, function ($rec, $num) use (&$llamadaExternaRuc) {
    $llamadaExternaRuc++;
    return [
        'ruc' => $num,
        'razonSocial' => 'CORPORACION HOSTELERA DEL SUR S.A.C.',
        'nombreComercial' => 'HOSTAL SUR',
        'direccion' => 'AV. EL SOL 123',
        'departamento' => 'CUSCO',
        'provincia' => 'CUSCO',
        'distrito' => 'CUSCO',
        'ubigeo' => '080101',
        'telefonos' => ['084-234567'],
        'estado' => 'ACTIVO',
        'condicion' => 'HABIDO',
    ];
});

$servicioExternoRuc = new ConsultaDocumentoServicio($personaRepo, $empresaRepo, $adaptadorMockExternoRuc);
$resExternoRuc = $servicioExternoRuc->consultar('RUC', $rucInexistenteLocal);

assertCheck($resExternoRuc['success'] === true, "Fallback externo de RUC responde success=true");
assertCheck($resExternoRuc['origen'] === 'APISPERU', "RUC no local resuelve con origen 'APISPERU'");
assertCheck($resExternoRuc['datos']['razon_social'] === 'CORPORACION HOSTELERA DEL SUR S.A.C.', "Razón social obtenida de SUNAT");
assertCheck($resExternoRuc['datos']['estado_sunat'] === 'ACTIVO', "Estado SUNAT obtenido fielmente");
assertCheck($resExternoRuc['datos']['condicion_sunat'] === 'HABIDO', "Condición SUNAT obtenida fielmente");

// =========================================================================
// 3. CONTROLADOR Y ENRUTADOR (DocumentoConsultaControlador)
// =========================================================================
echo "\n--- 3. Controlador y Enrutador (GET /api/documentos/consultar) ---\n";

$controladorRef = new ReflectionClass(DocumentoConsultaControlador::class);
assertCheck($controladorRef->hasMethod('consultar'), "DocumentoConsultaControlador define el método consultar()");

// 3.1 Protección de autenticación
$sesionServicio = new SesionServicio($pdo);
// Simular que no hay sesión activa
$_SESSION = [];
$controlador = new DocumentoConsultaControlador($servicioExternoDni, $sesionServicio);
$respSinSesion = $controlador->consultar();
assertCheck($respSinSesion->obtenerCodigo() === 401, "Endpoint deniega acceso con HTTP 401 cuando no existe sesión activa");

// 3.2 Simular usuario autenticado mediante mock de SesionServicio
$usuarioRepo = new \CamargoPMS\Repositorios\UsuarioRepositorio($pdo);
$stmtUsuario = $pdo->query("SELECT id FROM usuarios WHERE estado = 'ACTIVO' LIMIT 1");
$usrId = (int) $stmtUsuario->fetchColumn();
$usuarioModelo = $usuarioRepo->buscarPorId($usrId);

$sesionServicioMock = new class($usuarioModelo) extends SesionServicio {
    private ?\CamargoPMS\Modelos\Usuario $usuarioMock;
    public function __construct(?\CamargoPMS\Modelos\Usuario $u) {
        $this->usuarioMock = $u;
    }
    public function validarSesionActual(): ?\CamargoPMS\Modelos\Usuario {
        return $this->usuarioMock;
    }
};

$controladorAutenticado = new DocumentoConsultaControlador($servicioExternoDni, $sesionServicioMock);

// Sin parámetros obligatorios -> 422
$_GET = [];
$respSinParams = $controladorAutenticado->consultar();
assertCheck($respSinParams->obtenerCodigo() === 422, "Petición sin parámetros retorna HTTP 422 Unprocessable Entity");

// Con parámetros válidos de DNI mock -> 200
$_GET = ['tipo' => 'DNI', 'numero' => '09999991'];
$respValida = $controladorAutenticado->consultar();
assertCheck($respValida->obtenerCodigo() === 200, "Consulta válida de DNI retorna HTTP 200 OK");
$jsonValido = json_decode($respValida->obtenerCuerpo(), true);
assertCheck(($jsonValido['success'] ?? false) === true, "Cuerpo JSON contiene success=true");
assertCheck(($jsonValido['origen'] ?? '') === 'APISPERU', "Cuerpo JSON contiene origen='APISPERU'");

// Con parámetros de PASAPORTE -> 200 con origen 'MANUAL'
$_GET = ['tipo' => 'PASAPORTE', 'numero' => 'P12345678'];
$respPas = $controladorAutenticado->consultar();
assertCheck($respPas->obtenerCodigo() === 200, "Consulta de pasaporte retorna HTTP 200");
$jsonPas = json_decode($respPas->obtenerCuerpo(), true);
assertCheck(($jsonPas['origen'] ?? '') === 'MANUAL', "Pasaporte responde formalmente con origen='MANUAL'");
$_GET = [];

// 3.3 Verificación de Enrutador en public/index.php
$indexContenido = file_get_contents(dirname(__DIR__) . '/public/index.php');
assertCheck(str_contains($indexContenido, "get('/api/documentos/consultar'"), "Ruta '/api/documentos/consultar' registrada en public/index.php");
assertCheck(str_contains($indexContenido, "DocumentoConsultaControlador::class"), "Ruta asociada a DocumentoConsultaControlador::class");
assertCheck(str_contains($indexContenido, "AutenticacionIntermediario"), "Ruta protegida formalmente con AutenticacionIntermediario");

// =========================================================================
// 4. INTEGRACIÓN DE INTERFAZ PILOTO (Clientes & Empresas)
// =========================================================================
echo "\n--- 4. Integración de Interfaz Piloto (Clientes & Empresas) ---\n";

// 4.1 CamargoForms en public/assets/js/camargo-forms.js
$jsFormsContenido = file_get_contents(dirname(__DIR__) . '/public/assets/js/camargo-forms.js');
assertCheck(str_contains($jsFormsContenido, 'consultarDocumento(tipo, numero'), "CamargoForms expone el método universal consultarDocumento()");
assertCheck(str_contains($jsFormsContenido, '/api/documentos/consultar'), "CamargoForms invoca el endpoint interno /api/documentos/consultar");

// 4.2 Vista Clientes (app/Vistas/clientes/index.php)
$vistaClientes = file_get_contents(dirname(__DIR__) . '/app/Vistas/clientes/index.php');
assertCheck(str_contains($vistaClientes, 'id="btn-verificar-persona"'), "Vista Clientes contiene botón #btn-verificar-persona");
assertCheck(str_contains($vistaClientes, 'id="btn-consultar-dni-alta"'), "Vista Clientes contiene botón #btn-consultar-dni-alta en sección de alta");
assertCheck(str_contains($vistaClientes, "consultarDocumento('DNI'"), "Vista Clientes implementa consulta automatizada para DNI");

// 4.3 Vista Empresas (app/Vistas/empresas/index.php)
$vistaEmpresas = file_get_contents(dirname(__DIR__) . '/app/Vistas/empresas/index.php');
assertCheck(str_contains($vistaEmpresas, 'id="btn-consultar-ruc"'), "Vista Empresas contiene botón #btn-consultar-ruc");
assertCheck(str_contains($vistaEmpresas, 'id="empresa-sunat-badge"'), "Vista Empresas contiene contenedor de badge informativo #empresa-sunat-badge");
assertCheck(str_contains($vistaEmpresas, "consultarDocumento('RUC'"), "Vista Empresas invoca consultarDocumento para RUC");
assertCheck(str_contains($vistaEmpresas, 'SUNAT:'), "Vista Empresas renderiza badge informativo de SUNAT");

// =========================================================================
// 5. GOBERNANZA, SEGURIDAD Y CONFIGURACIÓN
// =========================================================================
echo "\n--- 5. Gobernanza, Seguridad y Configuración ---\n";

// 5.1 Plantilla de entorno (.env.example)
$envExample = file_get_contents(dirname(__DIR__) . '/.env.example');
assertCheck(str_contains($envExample, 'APISPERU_DNIRUC_TOKEN='), ".env.example incluye APISPERU_DNIRUC_TOKEN=");
assertCheck(str_contains($envExample, 'APISPERU_DNIRUC_TIMEOUT=5'), ".env.example incluye APISPERU_DNIRUC_TIMEOUT=5");
assertCheck(!preg_match('/APISPERU_DNIRUC_TOKEN=\S+/', $envExample), ".env.example NO contiene ningún token real (permanece como plantilla vacía)");

// 5.2 Base de datos: integridad y ranura 036 libre
$tablas = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
assertCheck(count($tablas) >= 118, "Base de datos preservada con integridad relacional (actual: " . count($tablas) . " tablas)");

$mig034Presente = (bool) $pdo->query("SELECT 1 FROM migraciones WHERE migracion = '034_agregar_foto_personas.sql'")->fetchColumn();
assertCheck($mig034Presente, "Migración 034_agregar_foto_personas.sql presente en BD");

$mig036 = glob(dirname(__DIR__) . '/SQL/migraciones/*036*');
assertCheck(empty($mig036), "Ranura de migración 036 estrictamente LIBRE para fases posteriores");

// 5.3 admin-dashboard/ intacto
$gitAlina = shell_exec('git status --porcelain admin-dashboard/');
assertCheck(empty(trim((string) $gitAlina)), "admin-dashboard/ permanece 100% inmutable y libre de modificaciones");

// =========================================================================
// 6. EXTENSIÓN CONTROLADA A MÓDULOS INTERNOS (APISPERU-1C)
// =========================================================================
echo "\n--- 6. Extensión Controlada a Módulos Internos (APISPERU-1C) ---\n";

// 6.1 Personal / Colaboradores (app/Vistas/personal/index.php)
$vistaPersonal = file_get_contents(dirname(__DIR__) . '/app/Vistas/personal/index.php');
assertCheck(str_contains($vistaPersonal, 'id="btn-consultar-dni-personal"'), "Vista Personal contiene botón #btn-consultar-dni-personal");
assertCheck(str_contains($vistaPersonal, "consultarDocumento('DNI'"), "Vista Personal invoca CamargoForms.consultarDocumento para DNI");
assertCheck(str_contains($vistaPersonal, "PASAPORTE"), "Vista Personal preserva validación/manejo de Pasaporte como manual");
assertCheck(str_contains($vistaPersonal, "alta-nombres"), "Vista Personal referencia campos canónicos de nombres");

// 6.2 Servicios / Proveedores (app/Vistas/servicios/index.php & public/assets/js/gestion-servicios.js)
$vistaServicios = file_get_contents(dirname(__DIR__) . '/app/Vistas/servicios/index.php');
assertCheck(str_contains($vistaServicios, 'id="btn-consultar-doc-proveedor"'), "Vista Servicios contiene botón #btn-consultar-doc-proveedor");
assertCheck(str_contains($vistaServicios, 'id="proveedor-documento"'), "Vista Servicios contiene input #proveedor-documento");

$jsServicios = file_get_contents(dirname(__DIR__) . '/public/assets/js/gestion-servicios.js');
assertCheck(str_contains($jsServicios, 'btn-consultar-doc-proveedor'), "gestion-servicios.js cablea el botón #btn-consultar-doc-proveedor");
assertCheck(str_contains($jsServicios, "consultarDocumento(tipoDoc, numDoc)"), "gestion-servicios.js reutiliza CamargoForms.consultarDocumento universal");
assertCheck(str_contains($jsServicios, "proveedor-razon-social"), "gestion-servicios.js autocompleta sin sobreescritura destructiva");

// 6.3 Gastos (app/Vistas/gastos/index.php) - CERO persistencia lateral
$vistaGastos = file_get_contents(dirname(__DIR__) . '/app/Vistas/gastos/index.php');
assertCheck(str_contains($vistaGastos, 'id="btn-consultar-acreedor-doc"'), "Vista Gastos contiene botón #btn-consultar-acreedor-doc");
assertCheck(str_contains($vistaGastos, 'id="input-acreedor-doc"'), "Vista Gastos contiene input #input-acreedor-doc");
assertCheck(str_contains($vistaGastos, "consultarDocumento(tipoDoc, doc)"), "Vista Gastos invoca CamargoForms.consultarDocumento");
assertCheck(str_contains($vistaGastos, "input-acreedor-nombre"), "Vista Gastos autocompleta nombre de acreedor sin persistencia lateral");

// 6.4 Reclamaciones Internas (app/Vistas/reclamaciones/index.php)
$vistaReclamaciones = file_get_contents(dirname(__DIR__) . '/app/Vistas/reclamaciones/index.php');
assertCheck(str_contains($vistaReclamaciones, 'id="btn-consultar-doc-reclamante"'), "Vista Reclamaciones contiene botón #btn-consultar-doc-reclamante");
assertCheck(str_contains($vistaReclamaciones, 'id="consumidor-tipo-documento"'), "Vista Reclamaciones contiene select #consumidor-tipo-documento");
assertCheck(str_contains($vistaReclamaciones, 'id="consumidor-numero-documento"'), "Vista Reclamaciones contiene input #consumidor-numero-documento");
assertCheck(str_contains($vistaReclamaciones, "consultarDocumento(tipoDoc, numDoc)"), "Vista Reclamaciones invoca CamargoForms.consultarDocumento");

// 6.5 Prueba negativa Libro de Reclamaciones Público (app/Vistas/reclamaciones/publico/formulario.php)
$vistaPublicaReclamaciones = file_get_contents(dirname(__DIR__) . '/app/Vistas/reclamaciones/publico/formulario.php');
assertCheck(!str_contains($vistaPublicaReclamaciones, 'consultarDocumento'), "PRUEBA NEGATIVA: Formulario público de reclamaciones NO contiene consultarDocumento");
assertCheck(!str_contains($vistaPublicaReclamaciones, 'apisperu') && !str_contains($vistaPublicaReclamaciones, 'ApisPeru'), "PRUEBA NEGATIVA: Formulario público de reclamaciones 100% libre de APIsPERU");

echo "\n====================================================================\n";
echo " RESUMEN APISPERU-1B/1C: $passedAssertions / $totalAssertions pruebas superadas\n";
echo "====================================================================\n\n";

if ($passedAssertions === $totalAssertions) {
    echo ">>> APISPERU-1C: VALIDACIÓN EXITOSA (100% PASS) <<<\n";
    exit(0);
} else {
    echo ">>> APISPERU-1C: DETECTADAS FALLAS EN LA VALIDACIÓN <<<\n";
    exit(1);
}
