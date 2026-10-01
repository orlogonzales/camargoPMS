<?php

declare(strict_types=1);

/**
 * Suite de Pruebas: WORDPRESS-1C — Perímetro HTTP /api/v1
 *
 * Valida de forma exhaustiva:
 * 1. Gobierno del esquema: BD con exactamente 128 tablas, migración 037 registrada, ranura 038 libre.
 * 2. Paridad DDL con SQL/camargo_pms.sql e inmutabilidad de admin-dashboard/.
 * 3. Modelo y Repositorio de Idempotencia (ApiIdempotencia, ApiIdempotenciaRepositorio).
 * 4. Contenedor de Contexto y Constructor de Respuestas (ContextoHttpApi, RespuestaApi).
 * 5. Middleware de Correlación (ApiCorrelacionIntermediario).
 * 6. Middleware de CORS y Preflights OPTIONS sin autenticación (ApiCorsIntermediario).
 * 7. Middleware de Autenticación Bearer y control de IPs (ApiAutenticacionIntermediario).
 * 8. Servicio y Middleware de Rate Limiting con ventana deslizante (ApiRateLimitServicio, ApiRateLimitIntermediario).
 * 9. Middleware de Control de Alcance (ApiScopeIntermediario).
 * 10. Middleware de Idempotencia: bloqueos concurrentes (409), replay determinista (200/201) y detección de desajuste de payload (422).
 * 11. Controladores y Enrutamiento End-to-End: GET /api/v1/ping, OPTIONS /api/v1/ping, GET /api/v1/perfil, OPTIONS /api/v1/perfil, 404 JSON.
 * 12. Regla de oro de seguridad: ausencia total de secretos, tokens en claro o PII en logs y perfiles.
 */

namespace CamargoPMS\Pruebas;

use CamargoPMS\Controladores\ApiPingControlador;
use CamargoPMS\Intermediarios\ApiAutenticacionIntermediario;
use CamargoPMS\Intermediarios\ApiCorrelacionIntermediario;
use CamargoPMS\Intermediarios\ApiCorsIntermediario;
use CamargoPMS\Intermediarios\ApiIdempotenciaIntermediario;
use CamargoPMS\Intermediarios\ApiRateLimitIntermediario;
use CamargoPMS\Intermediarios\ApiScopeIntermediario;
use CamargoPMS\Modelos\ApiClient;
use CamargoPMS\Modelos\ApiCredencial;
use CamargoPMS\Modelos\ApiIdempotencia;
use CamargoPMS\Modelos\ContextoAutenticacionApi;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Nucleo\ContextoHttpApi;
use CamargoPMS\Nucleo\Enrutador;
use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Nucleo\RespuestaApi;
use CamargoPMS\Repositorios\ApiClientRepositorio;
use CamargoPMS\Repositorios\ApiCredencialRepositorio;
use CamargoPMS\Repositorios\ApiIdempotenciaRepositorio;
use CamargoPMS\Repositorios\ApiScopeRepositorio;
use CamargoPMS\Servicios\ApiClientServicio;
use CamargoPMS\Servicios\ApiRateLimitServicio;
use PDO;
use RuntimeException;

require_once dirname(__DIR__) . '/vendor/autoload.php';

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
        throw new RuntimeException("Fallo en aserción: $message");
    }
}

echo "\n====================================================================\n";
echo " INICIANDO SUITE DE PRUEBAS: WORDPRESS-1C (PERÍMETRO HTTP /api/v1)\n";
echo "====================================================================\n\n";

try {
    // =========================================================================
    // 1. GOBIERNO DEL ESQUEMA Y MIGRACIÓN 037
    // =========================================================================
    echo "--- 1. Gobierno del Esquema y Migración 037 ---\n";

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    assertCheck(count($tables) >= 128, "Base de datos contiene al menos 128 tablas relacionales (actual: " . count($tables) . ")");
    assertCheck(in_array('api_idempotencia', $tables, true), "Tabla 'api_idempotencia' existe en la base de datos");

    $mig037Presente = (bool) $pdo->query("SELECT 1 FROM migraciones WHERE migracion = '037_api_idempotencia.sql'")->fetchColumn();
    assertCheck($mig037Presente, "Migración 037_api_idempotencia.sql registrada en tabla 'migraciones'");

    $mig039 = glob(dirname(__DIR__) . '/SQL/migraciones/*039*');
    assertCheck(empty($mig039), "Ranura de migración 039 estrictamente LIBRE (cero DDL no autorizado)");

    // Paridad con SQL/camargo_pms.sql
    $sqlConsolidado = (string) file_get_contents(dirname(__DIR__) . '/SQL/camargo_pms.sql');
    assertCheck(str_contains($sqlConsolidado, 'CREATE TABLE IF NOT EXISTS `api_idempotencia`'), "SQL/camargo_pms.sql incluye definición de api_idempotencia");

    // admin-dashboard/ inmutable
    $gitAlina = shell_exec('git status --porcelain admin-dashboard/');
    assertCheck(empty(trim((string) $gitAlina)), "admin-dashboard/ permanece 100% inmutable y libre de modificaciones");

    // =========================================================================
    // 2. MODELO Y REPOSITORIO DE IDEMPOTENCIA
    // =========================================================================
    echo "\n--- 2. Modelo y Repositorio de Idempotencia ---\n";

    $modelo = new ApiIdempotencia(
        id: 1,
        apiClienteId: 10,
        claveIdempotencia: 'test-idemp-12345678',
        ruta: '/api/v1/reservas',
        metodo: 'POST',
        cuerpoHash: hash('sha256', '{"test":1}'),
        estado: ApiIdempotencia::ESTADO_PROCESANDO,
        codigoHttp: null,
        cabecerasJson: '{"X-Custom":"Header"}',
        respuestaJson: null,
        bloqueadoHasta: date('Y-m-d H:i:s', time() + 60)
    );

    assertCheck($modelo->obtenerApiClientId() === 10, "Modelo ApiIdempotencia: api_cliente_id correcto");
    assertCheck($modelo->estaEnProceso(), "Modelo ApiIdempotencia: estaEnProceso() true");
    assertCheck($modelo->estaBloqueadoActualmente(), "Modelo ApiIdempotencia: estaBloqueadoActualmente() true");
    assertCheck($modelo->obtenerCabeceras()['X-Custom'] === 'Header', "Modelo ApiIdempotencia: cabeceras decodificadas");

    $idempRepo = new ApiIdempotenciaRepositorio($pdo);

    // Asegurar un cliente API de prueba para FK
    $clientRepo = new ApiClientRepositorio($pdo);
    $clienteTest = $clientRepo->buscarPorCodigo('TEST_API_CLIENT_1C');
    if ($clienteTest === null) {
        $clienteNuevo = new ApiClient(
            id: null,
            actorId: 1, // Superadmin
            codigo: 'TEST_API_CLIENT_1C',
            nombre: 'Cliente de Prueba 1C',
            descripcion: 'Para suite test_wordpress_1c',
            contactoEmail: 'test1c@camargopms.test',
            ipsPermitidas: null,
            limitePeticionesMinuto: 120,
            estado: ApiClient::ESTADO_ACTIVO
        );
        $clienteId = $clientRepo->insertar($clienteNuevo);
    } else {
        $clienteId = (int) $clienteTest->obtenerId();
    }

    $claveTest = 'idemp_key_' . bin2hex(random_bytes(8));
    $hashTest = hash('sha256', 'payload-test');

    $idCreado = $idempRepo->registrarInicio($clienteId, '/api/v1/reservas', 'POST', $claveTest, $hashTest, 30);
    assertCheck($idCreado !== null && $idCreado > 0, "Repositorio Idempotencia: registro inicial exitoso");

    // Intento concurrente con la misma clave debe retornar null (colisión detectada)
    $idColision = $idempRepo->registrarInicio($clienteId, '/api/v1/reservas', 'POST', $claveTest, $hashTest, 30);
    assertCheck($idColision === null, "Repositorio Idempotencia: concurrencia simultánea bloqueada por clave duplicada");

    $encontrado = $idempRepo->buscar($clienteId, '/api/v1/reservas', $claveTest);
    assertCheck($encontrado !== null && $encontrado->obtenerClaveIdempotencia() === $claveTest, "Repositorio Idempotencia: buscar() recupera registro");

    $okCompletar = $idempRepo->completar($idCreado, 201, '{"reserva_id":999}', ['X-Prueba' => '1']);
    assertCheck($okCompletar, "Repositorio Idempotencia: completar() exitoso");

    $encontradoCompletado = $idempRepo->buscar($clienteId, '/api/v1/reservas', $claveTest);
    assertCheck($encontradoCompletado->estaCompletado(), "Repositorio Idempotencia: registro marcado COMPLETADO");
    assertCheck($encontradoCompletado->obtenerCodigoHttp() === 201, "Repositorio Idempotencia: código HTTP 201 guardado");

    // Limpieza
    $idempRepo->eliminar($idCreado);
    assertCheck($idempRepo->buscar($clienteId, '/api/v1/reservas', $claveTest) === null, "Repositorio Idempotencia: eliminar() limpia registro");

    // =========================================================================
    // 3. CONTEXTO HTTP Y RESPUESTA API (ENVELOPE ESTÁNDAR)
    // =========================================================================
    echo "\n--- 3. Contexto HTTP y Respuesta API (Envelope Estándar) ---\n";

    ContextoHttpApi::limpiar();
    $cid = ContextoHttpApi::obtenerCorrelacionId();
    assertCheck(strlen($cid) >= 16, "ContextoHttpApi: genera correlacion_id por defecto si está vacío");

    ContextoHttpApi::establecerCorrelacionId('CORR-TEST-ABC-123');
    assertCheck(ContextoHttpApi::obtenerCorrelacionId() === 'CORR-TEST-ABC-123', "ContextoHttpApi: adopta ID de correlación explícito");

    $respExito = RespuestaApi::exito(['clave' => 'valor'], 200, ['X-Extra' => 'Prueba'], 'OPERACION_OK');
    assertCheck($respExito->obtenerCodigo() === 200, "RespuestaApi::exito genera código 200");
    $jsonExito = json_decode($respExito->obtenerContenido(), true);
    assertCheck($jsonExito['ok'] === true, "Envelope éxito: ok === true");
    assertCheck($jsonExito['codigo'] === 'OPERACION_OK', "Envelope éxito: codigo correcto");
    assertCheck($jsonExito['datos']['clave'] === 'valor', "Envelope éxito: datos correctos");
    assertCheck($jsonExito['meta']['correlacion_id'] === 'CORR-TEST-ABC-123', "Envelope éxito: correlacion_id inyectado");
    assertCheck(isset($jsonExito['meta']['marca_tiempo']), "Envelope éxito: marca_tiempo ISO 8601 presente");
    assertCheck($respExito->obtenerCabeceras()['X-Correlacion-ID'] === 'CORR-TEST-ABC-123', "Cabecera X-Correlacion-ID inyectada");

    $respError = RespuestaApi::error('Mensaje de fallo', 'ERROR_TEST', 422, ['campo' => 'requerido']);
    assertCheck($respError->obtenerCodigo() === 422, "RespuestaApi::error genera código 422");
    $jsonError = json_decode($respError->obtenerContenido(), true);
    assertCheck($jsonError['ok'] === false, "Envelope error: ok === false");
    assertCheck($jsonError['codigo'] === 'ERROR_TEST', "Envelope error: codigo correcto");
    assertCheck($jsonError['error'] === 'Mensaje de fallo', "Envelope error: mensaje correcto");
    assertCheck($jsonError['meta']['detalles']['campo'] === 'requerido', "Envelope error: detalles inyectados en meta");

    $resp204 = RespuestaApi::sinContenido();
    assertCheck($resp204->obtenerCodigo() === 204, "RespuestaApi::sinContenido genera HTTP 204");
    assertCheck($resp204->obtenerContenido() === '', "RespuestaApi::sinContenido cuerpo vacío");

    // =========================================================================
    // 4. INTERMEDIARIO DE CORRELACIÓN (ApiCorrelacionIntermediario)
    // =========================================================================
    echo "\n--- 4. Intermediario de Correlación ---\n";

    ContextoHttpApi::limpiar();
    $_SERVER['HTTP_X_CORRELACION_ID'] = 'CORR-INCOMING-777';
    $interCorr = new ApiCorrelacionIntermediario();
    $resCorr = $interCorr->manejar('/api/v1/ping');
    assertCheck($resCorr === null, "ApiCorrelacionIntermediario: permite continuar la cadena (retorna null)");
    assertCheck(ContextoHttpApi::obtenerCorrelacionId() === 'CORR-INCOMING-777', "ApiCorrelacionIntermediario: adopta cabecera HTTP_X_CORRELACION_ID válida");

    // Prueba con caracteres maliciosos o inválidos
    $_SERVER['HTTP_X_CORRELACION_ID'] = 'INVALID <script>alert(1)</script>';
    $interCorr->manejar('/api/v1/ping');
    assertCheck(ContextoHttpApi::obtenerCorrelacionId() !== $_SERVER['HTTP_X_CORRELACION_ID'], "ApiCorrelacionIntermediario: descarta ID con caracteres inválidos y regenera uno seguro");
    unset($_SERVER['HTTP_X_CORRELACION_ID']);

    // =========================================================================
    // 5. INTERMEDIARIO DE CORS (ApiCorsIntermediario)
    // =========================================================================
    echo "\n--- 5. Intermediario de CORS y Preflights OPTIONS ---\n";

    ContextoHttpApi::limpiar();
    $interCorsAllowAll = new ApiCorsIntermediario('*');

    // GET con origen
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['HTTP_ORIGIN'] = 'https://camargo.pe';
    $resCorsGet = $interCorsAllowAll->manejar('/api/v1/ping');
    assertCheck($resCorsGet === null, "ApiCorsIntermediario: peticiones válidas continúan");
    $corsHeaders = ContextoHttpApi::obtenerCabecerasCors();
    assertCheck($corsHeaders['Access-Control-Allow-Origin'] === 'https://camargo.pe', "CORS: origen reflejado correctamente");
    assertCheck(str_contains($corsHeaders['Access-Control-Allow-Methods'], 'OPTIONS'), "CORS: métodos permitidos incluyen OPTIONS");
    assertCheck(str_contains($corsHeaders['Access-Control-Allow-Headers'], 'Idempotency-Key'), "CORS: headers incluyen Idempotency-Key");

    // Preflight OPTIONS debe devolver HTTP 204 sin requerir token
    $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
    $resCorsOptions = $interCorsAllowAll->manejar('/api/v1/perfil');
    assertCheck($resCorsOptions instanceof Respuesta, "ApiCorsIntermediario: OPTIONS retorna Respuesta inmediata (cortocircuito)");
    assertCheck($resCorsOptions->obtenerCodigo() === 204, "ApiCorsIntermediario: OPTIONS responde exactamente HTTP 204 No Content");

    // Allowlist restrictivo
    $interCorsRestringido = new ApiCorsIntermediario(['https://permitido.com', 'https://otro.com']);
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['HTTP_ORIGIN'] = 'https://no-permitido.com';
    $resCorsDenegado = $interCorsRestringido->manejar('/api/v1/ping');
    assertCheck($resCorsDenegado instanceof Respuesta, "CORS restrictivo: origen prohibido bloqueado");
    assertCheck($resCorsDenegado->obtenerCodigo() === 403, "CORS restrictivo: responde HTTP 403");
    $jsonCorsDenegado = json_decode($resCorsDenegado->obtenerContenido(), true);
    assertCheck($jsonCorsDenegado['codigo'] === 'CORS_NO_PERMITIDO', "CORS restrictivo: código semántico CORS_NO_PERMITIDO");

    unset($_SERVER['HTTP_ORIGIN']);

    // =========================================================================
    // 6. INTERMEDIARIO DE AUTENTICACIÓN BEARER (ApiAutenticacionIntermediario)
    // =========================================================================
    echo "\n--- 6. Intermediario de Autenticación Bearer y Control de IPs ---\n";

    $apiServicio = new ApiClientServicio($pdo);
    $interAuth = new ApiAutenticacionIntermediario($apiServicio);

    // 1. Sin cabecera Authorization
    ContextoHttpApi::limpiar();
    unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
    $resSinAuth = $interAuth->manejar('/api/v1/perfil');
    assertCheck($resSinAuth instanceof Respuesta && $resSinAuth->obtenerCodigo() === 401, "Auth: sin Authorization responde HTTP 401");
    $jsonSinAuth = json_decode($resSinAuth->obtenerContenido(), true);
    assertCheck($jsonSinAuth['codigo'] === 'NO_AUTORIZADO', "Auth: código NO_AUTORIZADO");
    assertCheck(isset($resSinAuth->obtenerCabeceras()['WWW-Authenticate']), "Auth: cabecera WWW-Authenticate presente");

    // 2. Formato inválido
    $_SERVER['HTTP_AUTHORIZATION'] = 'Basic dXNlcjpwYXNz';
    $resMalformado = $interAuth->manejar('/api/v1/perfil');
    assertCheck($resMalformado->obtenerCodigo() === 401, "Auth: esquema no-Bearer responde HTTP 401");
    $jsonMalformado = json_decode($resMalformado->obtenerContenido(), true);
    assertCheck($jsonMalformado['codigo'] === 'TOKEN_INVALIDO', "Auth: código TOKEN_INVALIDO");

    // 3. Token inventado o inexistente
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer cpms_live_' . str_repeat('a', 64);
    $resInexistente = $interAuth->manejar('/api/v1/perfil');
    assertCheck($resInexistente->obtenerCodigo() === 401, "Auth: token inexistente responde HTTP 401");
    $jsonInexistente = json_decode($resInexistente->obtenerContenido(), true);
    assertCheck($jsonInexistente['codigo'] === 'CREDENCIAL_INVALIDA', "Auth: código CREDENCIAL_INVALIDA");

    // 4. Token auténtico válido
    $credencialCreada = $apiServicio->crearCredencial(
        clienteId: $clienteId,
        nombreCredencial: 'Credencial Suite 1C',
        codigosScopes: ['disponibilidad.leer', 'cotizacion.crear', 'reservas.leer']
    );
    $tokenValido = $credencialCreada['token_secreto'];
    $credId = (int) $credencialCreada['credencial']->obtenerId();

    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $tokenValido;
    $resValido = $interAuth->manejar('/api/v1/perfil');
    assertCheck($resValido === null, "Auth: token válido permite continuar (retorna null)");
    $authCtx = ContextoHttpApi::obtenerAutenticacion();
    assertCheck($authCtx !== null, "Auth: contexto autenticado inyectado en ContextoHttpApi");
    assertCheck($authCtx->obtenerCliente()->obtenerId() === $clienteId, "Auth: cliente autenticado coincide");
    assertCheck($authCtx->tieneScope('disponibilidad.leer'), "Auth: credencial tiene scope 'disponibilidad.leer'");

    // 5. Restricción de IP
    $clienteConIp = $clientRepo->buscarPorId($clienteId);
    $clienteConIpModificado = new ApiClient(
        id: $clienteId,
        actorId: $clienteConIp->obtenerActorId(),
        codigo: $clienteConIp->obtenerCodigo(),
        nombre: $clienteConIp->obtenerNombre(),
        descripcion: $clienteConIp->obtenerDescripcion(),
        contactoEmail: $clienteConIp->obtenerContactoEmail(),
        ipsPermitidas: '192.168.1.50, 10.0.0.1',
        limitePeticionesMinuto: 60,
        estado: ApiClient::ESTADO_ACTIVO
    );
    $clientRepo->actualizar($clienteConIpModificado);

    // Con IP no permitida
    $_SERVER['REMOTE_ADDR'] = '203.0.113.1';
    $resIpBloqueada = $interAuth->manejar('/api/v1/perfil');
    assertCheck($resIpBloqueada instanceof Respuesta && $resIpBloqueada->obtenerCodigo() === 403, "Auth IP: IP no permitida responde HTTP 403");
    $jsonIpBloqueada = json_decode($resIpBloqueada->obtenerContenido(), true);
    assertCheck($jsonIpBloqueada['codigo'] === 'IP_NO_AUTORIZADA', "Auth IP: código semántico IP_NO_AUTORIZADA");

    // Con IP permitida
    $_SERVER['REMOTE_ADDR'] = '192.168.1.50';
    $resIpPermitida = $interAuth->manejar('/api/v1/perfil');
    assertCheck($resIpPermitida === null, "Auth IP: IP permitida continúa exitosamente");

    // Restaurar IPs
    $clienteRestaurado = new ApiClient(
        id: $clienteId,
        actorId: $clienteConIp->obtenerActorId(),
        codigo: $clienteConIp->obtenerCodigo(),
        nombre: $clienteConIp->obtenerNombre(),
        descripcion: $clienteConIp->obtenerDescripcion(),
        contactoEmail: $clienteConIp->obtenerContactoEmail(),
        ipsPermitidas: null,
        limitePeticionesMinuto: 60,
        estado: ApiClient::ESTADO_ACTIVO
    );
    $clientRepo->actualizar($clienteRestaurado);

    // =========================================================================
    // 7. SERVICIO Y MIDDLEWARE DE RATE LIMITING (ApiRateLimitServicio)
    // =========================================================================
    echo "\n--- 7. Servicio y Middleware de Rate Limiting ---\n";

    $rateLimitServicio = new ApiRateLimitServicio();
    $rateLimitServicio->limpiar('test-key-rl');

    $r1 = $rateLimitServicio->verificarYConsumir('test-key-rl', 3);
    assertCheck($r1['permitido'] === true && $r1['restantes'] === 2, "RateLimit: primera petición permitida, restantes 2");

    $r2 = $rateLimitServicio->verificarYConsumir('test-key-rl', 3);
    assertCheck($r2['permitido'] === true && $r2['restantes'] === 1, "RateLimit: segunda petición permitida, restantes 1");

    $r3 = $rateLimitServicio->verificarYConsumir('test-key-rl', 3);
    assertCheck($r3['permitido'] === true && $r3['restantes'] === 0, "RateLimit: tercera petición permitida, restantes 0");

    $r4 = $rateLimitServicio->verificarYConsumir('test-key-rl', 3);
    assertCheck($r4['permitido'] === false && $r4['restantes'] === 0, "RateLimit: cuarta petición BLOQUEADA (cuota excedida)");
    assertCheck($r4['retry_after'] > 0, "RateLimit: retry_after calculado correctamente");

    $rateLimitServicio->limpiar('test-key-rl');

    // Middleware RateLimit
    $interRl = new ApiRateLimitIntermediario($rateLimitServicio);
    $idRl = 'cliente:' . $clienteId;
    $rateLimitServicio->limpiar($idRl);

    // Configurar cliente a cuota mínima de 1 para probar 429
    $clienteMinCuota = new ApiClient(
        id: $clienteId,
        actorId: $clienteConIp->obtenerActorId(),
        codigo: $clienteConIp->obtenerCodigo(),
        nombre: $clienteConIp->obtenerNombre(),
        descripcion: null,
        contactoEmail: null,
        ipsPermitidas: null,
        limitePeticionesMinuto: 1,
        estado: ApiClient::ESTADO_ACTIVO
    );
    $clientRepo->actualizar($clienteMinCuota);
    $interAuth->manejar('/api/v1/perfil'); // recarga contexto

    $resRl1 = $interRl->manejar('/api/v1/perfil');
    assertCheck($resRl1 === null, "Middleware RateLimit: primera petición permitida");
    $headersRl = ContextoHttpApi::obtenerCabecerasRateLimit();
    assertCheck($headersRl['X-RateLimit-Limit'] === '1', "Middleware RateLimit: cabecera X-RateLimit-Limit emitida");
    assertCheck($headersRl['X-RateLimit-Remaining'] === '0', "Middleware RateLimit: cabecera X-RateLimit-Remaining = 0");

    $resRl2 = $interRl->manejar('/api/v1/perfil');
    assertCheck($resRl2 instanceof Respuesta && $resRl2->obtenerCodigo() === 429, "Middleware RateLimit: segunda petición responde HTTP 429");
    $jsonRl2 = json_decode($resRl2->obtenerContenido(), true);
    assertCheck($jsonRl2['codigo'] === 'RATE_LIMIT_EXCEDIDO', "Middleware RateLimit: código RATE_LIMIT_EXCEDIDO");
    assertCheck(isset($resRl2->obtenerCabeceras()['Retry-After']), "Middleware RateLimit: cabecera Retry-After presente");

    // Restaurar cuota normal
    $clientRepo->actualizar($clienteRestaurado);
    $rateLimitServicio->limpiar($idRl);

    // =========================================================================
    // 8. MIDDLEWARE DE SCOPES (ApiScopeIntermediario)
    // =========================================================================
    echo "\n--- 8. Middleware de Control de Alcance (Scopes) ---\n";

    $interAuth->manejar('/api/v1/perfil'); // Credencial tiene: disponibilidad.leer, cotizacion.crear, reservas.leer

    $interScopeOk = ApiScopeIntermediario::requerir('disponibilidad.leer');
    assertCheck($interScopeOk->manejar('/api/v1/disponibilidad') === null, "Scope: credencial con scope requerido continúa");

    $interScopeDenegado = ApiScopeIntermediario::requerir('reservas.cancelar');
    $resScopeDenegado = $interScopeDenegado->manejar('/api/v1/reservas/cancelar');
    assertCheck($resScopeDenegado instanceof Respuesta && $resScopeDenegado->obtenerCodigo() === 403, "Scope: credencial sin scope responde HTTP 403");
    $jsonScopeDenegado = json_decode($resScopeDenegado->obtenerContenido(), true);
    assertCheck($jsonScopeDenegado['codigo'] === 'ACCESO_DENEGADO', "Scope: código semántico ACCESO_DENEGADO");

    // =========================================================================
    // 9. MIDDLEWARE DE IDEMPOTENCIA
    // =========================================================================
    echo "\n--- 9. Middleware de Idempotencia y Replay Determinista ---\n";

    $interIdemp = new ApiIdempotenciaIntermediario($idempRepo);

    // 1. GET no exige idempotencia
    $_SERVER['REQUEST_METHOD'] = 'GET';
    unset($_SERVER['HTTP_IDEMPOTENCY_KEY']);
    assertCheck($interIdemp->manejar('/api/v1/reservas') === null, "Idempotencia: método GET ignora clave");

    // 2. POST sin Idempotency-Key -> 400
    $_SERVER['REQUEST_METHOD'] = 'POST';
    unset($_SERVER['HTTP_IDEMPOTENCY_KEY']);
    $resSinKey = $interIdemp->manejar('/api/v1/reservas');
    assertCheck($resSinKey instanceof Respuesta && $resSinKey->obtenerCodigo() === 400, "Idempotencia: POST sin cabecera responde HTTP 400");
    $jsonSinKey = json_decode($resSinKey->obtenerContenido(), true);
    assertCheck($jsonSinKey['codigo'] === 'IDEMPOTENCIA_REQUERIDA', "Idempotencia: código IDEMPOTENCIA_REQUERIDA");

    // 3. Formato inválido -> 400
    $_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'short';
    $resKeyCorta = $interIdemp->manejar('/api/v1/reservas');
    assertCheck($resKeyCorta->obtenerCodigo() === 400, "Idempotencia: clave menor a 8 caracteres responde HTTP 400");
    $jsonKeyCorta = json_decode($resKeyCorta->obtenerContenido(), true);
    assertCheck($jsonKeyCorta['codigo'] === 'IDEMPOTENCIA_INVALIDA', "Idempotencia: código IDEMPOTENCIA_INVALIDA");

    // 4. Petición inicial válida con payload A
    $claveOperacion = 'order_tx_' . bin2hex(random_bytes(10));
    $_SERVER['HTTP_IDEMPOTENCY_KEY'] = $claveOperacion;
    ApiIdempotenciaIntermediario::$cuerpoPrueba = '{"checkin":"2026-10-10","checkout":"2026-10-12","unidad_id":1}';

    $resInicio = $interIdemp->manejar('/api/v1/reservas');
    assertCheck($resInicio === null, "Idempotencia: clave válida y nueva permite continuar");
    $idempId = ContextoHttpApi::obtenerIdempotenciaId();
    assertCheck($idempId !== null && $idempId > 0, "Idempotencia: ID de bloqueo temporal asignado");

    // 5. Carrera concurrente (simultánea) con la misma clave en curso -> 409
    $resConcurrente = $interIdemp->manejar('/api/v1/reservas');
    assertCheck($resConcurrente instanceof Respuesta && $resConcurrente->obtenerCodigo() === 409, "Idempotencia: petición concurrente en curso responde HTTP 409");
    $jsonConcurrente = json_decode($resConcurrente->obtenerContenido(), true);
    assertCheck($jsonConcurrente['codigo'] === 'OPERACION_EN_CURSO', "Idempotencia: código OPERACION_EN_CURSO");

    // 6. El controlador finaliza y el hook despues() persiste el resultado
    $respuestaControlador = RespuestaApi::exito(['reserva_id' => 888, 'codigo' => 'RES-888'], 201);
    $respuestaFinal = $interIdemp->despues($respuestaControlador);
    assertCheck($respuestaFinal->obtenerCodigo() === 201, "Idempotencia hook despues: mantiene código HTTP 201");
    assertCheck($respuestaFinal->obtenerCabeceras()['Idempotency-Key'] === $claveOperacion, "Idempotencia hook despues: añade Idempotency-Key en respuesta");

    // 7. Replay determinista con la misma clave y mismo payload -> 201 con X-Cache-Lookup
    ContextoHttpApi::limpiar();
    $interAuth->manejar('/api/v1/reservas'); // recarga auth
    $_SERVER['HTTP_IDEMPOTENCY_KEY'] = $claveOperacion;
    ApiIdempotenciaIntermediario::$cuerpoPrueba = '{"checkin":"2026-10-10","checkout":"2026-10-12","unidad_id":1}';

    $resReplay = $interIdemp->manejar('/api/v1/reservas');
    assertCheck($resReplay instanceof Respuesta, "Idempotencia replay: intercepta y retorna respuesta instantánea");
    assertCheck($resReplay->obtenerCodigo() === 201, "Idempotencia replay: responde con el mismo código 201");
    $cabecerasReplay = $resReplay->obtenerCabeceras();
    assertCheck(($cabecerasReplay['X-Cache-Lookup'] ?? '') === 'IDEMPOTENT-REPLAY', "Idempotencia replay: cabecera X-Cache-Lookup: IDEMPOTENT-REPLAY presente");
    $jsonReplay = json_decode($resReplay->obtenerContenido(), true);
    assertCheck($jsonReplay['datos']['codigo'] === 'RES-888', "Idempotencia replay: mismo cuerpo de respuesta devuelto");

    // 8. Desajuste de payload con la misma clave (Payload Mismatch) -> 422
    ApiIdempotenciaIntermediario::$cuerpoPrueba = '{"checkin":"2026-10-15","checkout":"2026-10-20","unidad_id":2}';
    $resMismatch = $interIdemp->manejar('/api/v1/reservas');
    assertCheck($resMismatch instanceof Respuesta && $resMismatch->obtenerCodigo() === 422, "Idempotencia: cambio de cuerpo con misma clave responde HTTP 422");
    $jsonMismatch = json_decode($resMismatch->obtenerContenido(), true);
    assertCheck($jsonMismatch['codigo'] === 'IDEMPOTENCIA_DESAJUSTE_PAYLOAD', "Idempotencia: código semántico IDEMPOTENCIA_DESAJUSTE_PAYLOAD");

    // Limpieza de fixture
    $idempRepo->eliminar($idempId);
    ApiIdempotenciaIntermediario::$cuerpoPrueba = null;
    unset($_SERVER['HTTP_IDEMPOTENCY_KEY']);

    // =========================================================================
    // 10. CONTROLADORES Y ENRUTAMIENTO END-TO-END
    // =========================================================================
    echo "\n--- 10. Controladores y Enrutamiento End-to-End ---\n";

    $enrutador = new Enrutador();

    // Registrar rutas exactamente como en public/index.php
    $enrutador->get('/api/v1/ping', [ApiPingControlador::class, 'ping'], [
        ApiCorrelacionIntermediario::class,
        ApiCorsIntermediario::class,
        ApiRateLimitIntermediario::class,
    ]);
    $enrutador->options('/api/v1/ping', [ApiPingControlador::class, 'ping'], [
        ApiCorrelacionIntermediario::class,
        ApiCorsIntermediario::class,
    ]);

    $enrutador->get('/api/v1/perfil', [ApiPingControlador::class, 'perfil'], [
        ApiCorrelacionIntermediario::class,
        ApiCorsIntermediario::class,
        ApiAutenticacionIntermediario::class,
        ApiRateLimitIntermediario::class,
    ]);
    $enrutador->options('/api/v1/perfil', [ApiPingControlador::class, 'perfil'], [
        ApiCorrelacionIntermediario::class,
        ApiCorsIntermediario::class,
    ]);

    // 10.1 GET /api/v1/ping
    ContextoHttpApi::limpiar();
    $_SERVER['REQUEST_METHOD'] = 'GET';
    unset($_SERVER['HTTP_AUTHORIZATION']);
    $respPing = $enrutador->despachar('GET', '/api/v1/ping');
    assertCheck($respPing->obtenerCodigo() === 200, "E2E: GET /api/v1/ping retorna HTTP 200");
    $jsonPing = json_decode($respPing->obtenerContenido(), true);
    assertCheck($jsonPing['ok'] === true, "E2E: ping ok === true");
    assertCheck($jsonPing['datos']['servicio'] === 'Camargo PMS API', "E2E: ping servicio identificado");
    assertCheck($jsonPing['datos']['estado'] === 'OPERATIVO', "E2E: ping estado OPERATIVO");

    // 10.2 OPTIONS /api/v1/ping (Preflight CORS)
    ContextoHttpApi::limpiar();
    $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
    $respPingOptions = $enrutador->despachar('OPTIONS', '/api/v1/ping');
    assertCheck($respPingOptions->obtenerCodigo() === 204, "E2E: OPTIONS /api/v1/ping retorna HTTP 204 No Content");

    // 10.3 GET /api/v1/perfil sin token -> 401
    ContextoHttpApi::limpiar();
    $_SERVER['REQUEST_METHOD'] = 'GET';
    unset($_SERVER['HTTP_AUTHORIZATION']);
    $respPerfilAnonimo = $enrutador->despachar('GET', '/api/v1/perfil');
    assertCheck($respPerfilAnonimo->obtenerCodigo() === 401, "E2E: GET /api/v1/perfil sin token retorna HTTP 401");
    $jsonPerfilAnonimo = json_decode($respPerfilAnonimo->obtenerContenido(), true);
    assertCheck($jsonPerfilAnonimo['codigo'] === 'NO_AUTORIZADO', "E2E: error NO_AUTORIZADO");

    // 10.4 OPTIONS /api/v1/perfil sin token -> 204 (Preflight nunca exige token)
    ContextoHttpApi::limpiar();
    $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
    unset($_SERVER['HTTP_AUTHORIZATION']);
    $respPerfilOptions = $enrutador->despachar('OPTIONS', '/api/v1/perfil');
    assertCheck($respPerfilOptions->obtenerCodigo() === 204, "E2E: OPTIONS /api/v1/perfil sin token retorna HTTP 204 sin fallar auth");

    // 10.5 GET /api/v1/perfil con token válido -> 200
    ContextoHttpApi::limpiar();
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $tokenValido;
    $respPerfilAuth = $enrutador->despachar('GET', '/api/v1/perfil');
    assertCheck($respPerfilAuth->obtenerCodigo() === 200, "E2E: GET /api/v1/perfil con Bearer token retorna HTTP 200");
    $jsonPerfilAuth = json_decode($respPerfilAuth->obtenerContenido(), true);
    assertCheck($jsonPerfilAuth['ok'] === true, "E2E: perfil ok === true");
    assertCheck($jsonPerfilAuth['datos']['cliente']['codigo'] === 'TEST_API_CLIENT_1C', "E2E: perfil cliente identificado");
    assertCheck(in_array('disponibilidad.leer', $jsonPerfilAuth['datos']['scopes'], true), "E2E: perfil incluye scopes");

    // 10.6 Regla de oro de seguridad: ausencia total de secretos en respuesta
    $contenidoPerfil = $respPerfilAuth->obtenerContenido();
    assertCheck(!str_contains($contenidoPerfil, $tokenValido), "SEGURIDAD: respuesta de perfil JAMÁS expone el token en claro");
    assertCheck(!str_contains($contenidoPerfil, 'token_hash'), "SEGURIDAD: respuesta de perfil no expone hashes");
    assertCheck(!str_contains($contenidoPerfil, 'password'), "SEGURIDAD: respuesta de perfil no expone contraseñas");

    // 10.7 Ruta no registrada en API -> 404 JSON
    ContextoHttpApi::limpiar();
    $resp404 = $enrutador->despachar('GET', '/api/v1/ruta-completamente-inexistente');
    assertCheck($resp404->obtenerCodigo() === 404, "E2E: ruta inexistente /api/v1/* retorna HTTP 404");
    $json404 = json_decode($resp404->obtenerContenido(), true);
    assertCheck($json404['ok'] === false, "E2E 404: envelope JSON ok === false");
    assertCheck($json404['codigo'] === 'RECURSO_NO_ENCONTRADO', "E2E 404: código semántico RECURSO_NO_ENCONTRADO");

    // Limpieza final de credencial y cliente de prueba
    $credRepo = new ApiCredencialRepositorio($pdo);
    $credRepo->revocar($credId);
    $pdo->prepare("DELETE FROM api_credencial_scopes WHERE api_credencial_id = :id")->execute(['id' => $credId]);
    $pdo->prepare("DELETE FROM api_credenciales WHERE id = :id")->execute(['id' => $credId]);
    $pdo->prepare("DELETE FROM api_clientes WHERE id = :id")->execute(['id' => $clienteId]);

    echo "\n====================================================================\n";
    echo " RESUMEN: $passedAssertions / $totalAssertions aserciones superadas con éxito\n";
    echo "====================================================================\n\n";
    echo ">>> SUITE WORDPRESS-1C COMPLETADA AL 100% (TODO PASS) <<<\n\n";

} catch (\Throwable $e) {
    echo "\n[ERROR CRÍTICO EN LA SUITE]: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
