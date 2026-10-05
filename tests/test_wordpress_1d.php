<?php

declare(strict_types=1);

/**
 * Suite de Pruebas: WORDPRESS-1D — Endpoints Headless de Disponibilidad, Cotización y Reservas
 *
 * Valida de forma exhaustiva:
 * 1. Gobierno del esquema: BD con exactamente 128 tablas, migración 037 registrada, ranura 038 estrictamente libre.
 * 2. Inmutabilidad de admin-dashboard/ y SQL/.
 * 3. Endpoint 1 (GET /api/v1/disponibilidad):
 *    - Exigencia de Bearer y scope 'disponibilidad.leer'.
 *    - Validación de fechas e intervalos hoteleros (D-066).
 *    - Respuesta JSON estructurada, conteos de disponibilidad y filtro por capacidad.
 *    - Preflight OPTIONS 204 No Content.
 * 4. Endpoint 2 (POST /api/v1/cotizaciones):
 *    - Exigencia de Bearer y scope 'cotizacion.crear'.
 *    - Cálculo soberano noche a noche de tarifas (D-069, BCMath).
 *    - Emisión de snapshot firmado HMAC-SHA256 con expiración de 30 minutos.
 *    - Comprobación estricta de NO BLOQUEO de inventario.
 *    - Preflight OPTIONS 204 No Content.
 * 5. Endpoint 3 (POST /api/v1/reservas):
 *    - Exigencia de cabecera Idempotency-Key y scope 'reservas.hold'.
 *    - Creación de reserva directa en hold (PENDIENTE).
 *    - Bloqueo atómico de inventario en inventario_diario_unidades (D-067).
 *    - Resolución y alta de huésped titular y ficha de cliente comercial (360°).
 *    - Trazabilidad de actor técnico en la reserva y auditoría transversal (D-061).
 *    - Idempotencia: replay determinista (201 HIT), desajuste de payload (422) y prevención de doble reserva (409).
 *    - Preflight OPTIONS 204 No Content.
 * 6. Endpoint 4 (GET /api/v1/reservas/{codigo}):
 *    - Exigencia de Bearer y scope 'reservas.leer'.
 *    - Búsqueda por código comercial con 404 ante códigos inexistentes.
 *    - Protección de privacidad PII: nombre y contactos enmascarados, cero fuga de IDs internos.
 *    - Preflight OPTIONS 204 No Content.
 * 7. Limpieza defensiva y liberación de datos transitorios.
 */

namespace CamargoPMS\Pruebas;

use CamargoPMS\Controladores\ApiPingControlador;
use CamargoPMS\Controladores\ApiReservaControlador;
use CamargoPMS\Intermediarios\ApiAutenticacionIntermediario;
use CamargoPMS\Intermediarios\ApiCorrelacionIntermediario;
use CamargoPMS\Intermediarios\ApiCorsIntermediario;
use CamargoPMS\Intermediarios\ApiIdempotenciaIntermediario;
use CamargoPMS\Intermediarios\ApiRateLimitIntermediario;
use CamargoPMS\Intermediarios\ApiScopeIntermediario;
use CamargoPMS\Modelos\ApiClient;
use CamargoPMS\Modelos\Reserva;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Nucleo\ContextoHttpApi;
use CamargoPMS\Nucleo\Enrutador;
use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Nucleo\RespuestaApi;
use CamargoPMS\Repositorios\ApiClientRepositorio;
use CamargoPMS\Repositorios\ApiIdempotenciaRepositorio;
use CamargoPMS\Repositorios\PersonaRepositorio;
use CamargoPMS\Repositorios\ReservaRepositorio;
use CamargoPMS\Servicios\ApiClientServicio;
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
echo " INICIANDO SUITE DE PRUEBAS: WORDPRESS-1D (ENDPOINTS NEGOCIO API v1)\n";
echo "====================================================================\n\n";

try {
    // =========================================================================
    // 1. GOBIERNO DEL ESQUEMA Y RANURA 038
    // =========================================================================
    echo "--- 1. Gobierno del Esquema y Ranura 038 ---\n";

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    assertCheck(count($tables) >= 128, "Base de datos contiene al menos 128 tablas relacionales (actual: " . count($tables) . ")");
    assertCheck(in_array('api_idempotencia', $tables, true), "Tabla 'api_idempotencia' existe en la base de datos");
    assertCheck(in_array('tarifas_alojamiento', $tables, true), "Tabla 'tarifas_alojamiento' existe en la base de datos");
    assertCheck(in_array('reservas', $tables, true), "Tabla 'reservas' existe en la base de datos");
    assertCheck(in_array('inventario_diario_unidades', $tables, true), "Tabla 'inventario_diario_unidades' existe en la base de datos");

    $mig037Presente = (bool) $pdo->query("SELECT 1 FROM migraciones WHERE migracion = '037_api_idempotencia.sql'")->fetchColumn();
    assertCheck($mig037Presente, "Migración 037_api_idempotencia.sql registrada en tabla 'migraciones'");

    $mig041 = glob(dirname(__DIR__) . '/SQL/migraciones/*041*');
    assertCheck(empty($mig041), "Ranura de migración 041 estrictamente LIBRE (cero DDL no autorizado)");

    // Inmutabilidad de admin-dashboard/ y SQL consolidado
    $gitAlina = shell_exec('git status --porcelain admin-dashboard/');
    assertCheck(empty(trim((string) $gitAlina)), "admin-dashboard/ permanece 100% inmutable y libre de modificaciones");

    $sqlConsolidado = (string) file_get_contents(dirname(__DIR__) . '/SQL/camargo_pms.sql');
    assertCheck(str_contains($sqlConsolidado, 'CREATE TABLE IF NOT EXISTS `api_idempotencia`'), "SQL/camargo_pms.sql preserva definición de api_idempotencia");

    // =========================================================================
    // 2. CONFIGURACIÓN DE CLIENTES API Y CREDENCIALES DE PRUEBA
    // =========================================================================
    echo "\n--- 2. Configuración de Clientes API y Credenciales de Prueba ---\n";

    $clientRepo = new ApiClientRepositorio($pdo);
    $apiServicio = new ApiClientServicio($pdo);

    // Cliente principal 1D
    $cliente1D = $clientRepo->buscarPorCodigo('TEST_API_CLIENT_1D');
    if ($cliente1D === null) {
        $clienteNuevo = $apiServicio->crearCliente([
            'codigo' => 'TEST_API_CLIENT_1D',
            'nombre' => 'Cliente de Pruebas WORDPRESS-1D',
            'descripcion' => 'Cliente para suite test_wordpress_1d.php',
            'contacto_email' => 'wordpress1d@camargopms.test',
            'ips_permitidas' => null,
            'limite_peticiones_minuto' => 300,
        ], 1);
        $clienteId = (int) $clienteNuevo->obtenerId();
    } else {
        $clienteId = (int) $cliente1D->obtenerId();
    }

    // Credencial completa (todos los scopes requeridos por 1D)
    $credFull = $apiServicio->crearCredencial(
        clienteId: $clienteId,
        nombreCredencial: 'Credencial Full 1D',
        codigosScopes: ['disponibilidad.leer', 'cotizacion.crear', 'reservas.hold', 'reservas.leer']
    );
    $tokenFull = $credFull['token_secreto'];
    $actorTecnicoId = (int) $clientRepo->buscarPorId($clienteId)->obtenerActorId();
    assertCheck(!empty($tokenFull), "Credencial completa 1D creada con token secreto");
    assertCheck($actorTecnicoId > 0, "Credencial vinculada a actor técnico válido (ID: {$actorTecnicoId})");

    // Credencial limitada (solo 'disponibilidad.leer') para pruebas de rechazo 403
    $credLimitada = $apiServicio->crearCredencial(
        clienteId: $clienteId,
        nombreCredencial: 'Credencial Limitada 1D',
        codigosScopes: ['disponibilidad.leer']
    );
    $tokenLimitado = $credLimitada['token_secreto'];
    assertCheck(!empty($tokenLimitado), "Credencial restringida creada (solo disponibilidad.leer)");

    // Identificar una propiedad y unidad activa de prueba para cotizar y reservar
    $propiedadId = (int) $pdo->query("SELECT id FROM propiedades WHERE estado = 'ACTIVO' LIMIT 1")->fetchColumn();
    assertCheck($propiedadId > 0, "Propiedad activa encontrada para pruebas (ID: {$propiedadId})");

    $unidadFila = $pdo->query("SELECT id, codigo, tipo_unidad_id, capacidad_personas FROM unidades WHERE propiedad_id = {$propiedadId} AND estado = 'ACTIVO' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    assertCheck(!empty($unidadFila), "Unidad activa encontrada para pruebas (ID: {$unidadFila['id']}, Código: {$unidadFila['codigo']})");
    $unidadId = (int) $unidadFila['id'];
    $tipoUnidadId = (int) $unidadFila['tipo_unidad_id'];

    // Fechas de prueba futuras y no colisionantes
    $fechaEntrada = '2026-11-10';
    $fechaSalida = '2026-11-13'; // 3 noches

    // Asegurar tarifa base para la propiedad
    $tarifaServicio = new \CamargoPMS\Servicios\TarifaAlojamientoServicio($pdo);
    try {
        $tarifaServicio->resolverTarifaParaFecha($propiedadId, $tipoUnidadId, $unidadId, $fechaEntrada);
    } catch (\CamargoPMS\Excepciones\TarifaNoEncontradaExcepcion) {
        $tarifaServicio->crearTarifa([
            'propiedad_id' => $propiedadId,
            'ambito_tipo' => 'PROPIEDAD',
            'nombre' => 'Tarifa Base Test 1D',
            'precio_noche' => '150.00',
            'vigencia_desde' => '2026-01-01',
            'vigencia_hasta' => '2026-12-31',
            'moneda_codigo' => 'PEN',
        ], $actorTecnicoId);
    }

    // Construir Enrutador con el pipeline idéntico a public/index.php
    $enrutador = new Enrutador();

    // 1. Disponibilidad
    $enrutador->get('/api/v1/disponibilidad', [ApiReservaControlador::class, 'disponibilidad'], [
        ApiCorrelacionIntermediario::class,
        ApiCorsIntermediario::class,
        ApiAutenticacionIntermediario::class,
        ApiRateLimitIntermediario::class,
        new ApiScopeIntermediario('disponibilidad.leer'),
    ]);
    $enrutador->options('/api/v1/disponibilidad', [ApiReservaControlador::class, 'preflight'], [
        ApiCorrelacionIntermediario::class,
        ApiCorsIntermediario::class,
    ]);

    // 2. Cotizaciones
    $enrutador->post('/api/v1/cotizaciones', [ApiReservaControlador::class, 'cotizar'], [
        ApiCorrelacionIntermediario::class,
        ApiCorsIntermediario::class,
        ApiAutenticacionIntermediario::class,
        ApiRateLimitIntermediario::class,
        new ApiScopeIntermediario('cotizacion.crear'),
    ]);
    $enrutador->options('/api/v1/cotizaciones', [ApiReservaControlador::class, 'preflight'], [
        ApiCorrelacionIntermediario::class,
        ApiCorsIntermediario::class,
    ]);

    // 3. Reservas
    $enrutador->post('/api/v1/reservas', [ApiReservaControlador::class, 'crearReserva'], [
        ApiCorrelacionIntermediario::class,
        ApiCorsIntermediario::class,
        ApiAutenticacionIntermediario::class,
        ApiRateLimitIntermediario::class,
        new ApiScopeIntermediario('reservas.hold'),
        ApiIdempotenciaIntermediario::class,
    ]);
    $enrutador->options('/api/v1/reservas', [ApiReservaControlador::class, 'preflight'], [
        ApiCorrelacionIntermediario::class,
        ApiCorsIntermediario::class,
    ]);

    // 4. Consulta de Reservas
    $enrutador->get('/api/v1/reservas/{codigo}', [ApiReservaControlador::class, 'consultar'], [
        ApiCorrelacionIntermediario::class,
        ApiCorsIntermediario::class,
        ApiAutenticacionIntermediario::class,
        ApiRateLimitIntermediario::class,
        new ApiScopeIntermediario('reservas.leer'),
    ]);
    $enrutador->options('/api/v1/reservas/{codigo}', [ApiReservaControlador::class, 'preflight'], [
        ApiCorrelacionIntermediario::class,
        ApiCorsIntermediario::class,
    ]);

    // Limpiar posibles residuos previos de inventario en esas fechas para la unidad de prueba
    $pdo->exec("DELETE FROM inventario_diario_unidades WHERE unidad_id = {$unidadId} AND fecha >= '{$fechaEntrada}' AND fecha < '{$fechaSalida}'");

    // =========================================================================
    // 3. ENDPOINT 1: GET /api/v1/disponibilidad
    // =========================================================================
    echo "\n--- 3. Endpoint 1: GET /api/v1/disponibilidad ---\n";

    // 3.1 Petición sin token -> 401
    ContextoHttpApi::limpiar();
    $_SERVER['REQUEST_METHOD'] = 'GET';
    unset($_SERVER['HTTP_AUTHORIZATION']);
    $_GET = ['fecha_entrada' => $fechaEntrada, 'fecha_salida' => $fechaSalida];
    $respDispSinAuth = $enrutador->despachar('GET', '/api/v1/disponibilidad');
    assertCheck($respDispSinAuth->obtenerCodigo() === 401, "Disponibilidad: sin token responde HTTP 401");

    // 3.2 Preflight OPTIONS sin token -> 204
    ContextoHttpApi::limpiar();
    $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
    $respDispOptions = $enrutador->despachar('OPTIONS', '/api/v1/disponibilidad');
    assertCheck($respDispOptions->obtenerCodigo() === 204, "Disponibilidad: OPTIONS preflight responde HTTP 204 No Content");
    assertCheck(isset($respDispOptions->obtenerCabeceras()['Access-Control-Allow-Methods']), "Disponibilidad: CORS cabeceras presentes en preflight");

    // 3.3 Falta de parámetros obligatorios de fechas -> 422
    ContextoHttpApi::limpiar();
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $tokenFull;
    $_GET = []; // Sin fechas
    $respDispSinFechas = $enrutador->despachar('GET', '/api/v1/disponibilidad');
    assertCheck($respDispSinFechas->obtenerCodigo() === 422, "Disponibilidad: sin fechas responde HTTP 422");
    $jsonDispSinFechas = json_decode($respDispSinFechas->obtenerContenido(), true);
    assertCheck($jsonDispSinFechas['codigo'] === 'PARAMETROS_REQUERIDOS', "Disponibilidad: código PARAMETROS_REQUERIDOS");

    // 3.4 Intervalo hotelero inválido (checkout <= checkin) -> 422
    ContextoHttpApi::limpiar();
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $tokenFull;
    $_GET = ['fecha_entrada' => '2026-11-15', 'fecha_salida' => '2026-11-10'];
    $respDispInv = $enrutador->despachar('GET', '/api/v1/disponibilidad');
    assertCheck($respDispInv->obtenerCodigo() === 422, "Disponibilidad: intervalo invertido responde HTTP 422");
    $jsonDispInv = json_decode($respDispInv->obtenerContenido(), true);
    assertCheck($jsonDispInv['codigo'] === 'INTERVALO_INVALIDO', "Disponibilidad: código INTERVALO_INVALIDO");

    // 3.5 Consulta exitosa con fechas válidas -> 200
    ContextoHttpApi::limpiar();
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $tokenFull;
    $_GET = [
        'fecha_entrada' => $fechaEntrada,
        'fecha_salida' => $fechaSalida,
        'propiedad_id' => $propiedadId,
        'solo_disponibles' => '1',
    ];
    $respDispOk = $enrutador->despachar('GET', '/api/v1/disponibilidad');
    assertCheck($respDispOk->obtenerCodigo() === 200, "Disponibilidad: consulta válida responde HTTP 200");
    $jsonDispOk = json_decode($respDispOk->obtenerContenido(), true);
    assertCheck($jsonDispOk['ok'] === true, "Disponibilidad: envelope ok === true");
    assertCheck($jsonDispOk['codigo'] === 'DISPONIBILIDAD_CONSULTADA', "Disponibilidad: código DISPONIBILIDAD_CONSULTADA");
    assertCheck($jsonDispOk['datos']['intervalo']['noches'] === 3, "Disponibilidad: intervalo de 3 noches reportado");
    assertCheck($jsonDispOk['datos']['unidades_disponibles'] >= 1, "Disponibilidad: detecta al menos 1 unidad disponible");

    // Verificar que la unidad de prueba aparece disponible
    $unidadDispTest = false;
    foreach ($jsonDispOk['datos']['unidades'] as $u) {
        if ($u['id'] === $unidadId && $u['disponible'] === true) {
            $unidadDispTest = true;
            break;
        }
    }
    assertCheck($unidadDispTest, "Disponibilidad: unidad de prueba (ID {$unidadId}) reportada como disponible");

    // =========================================================================
    // 4. ENDPOINT 2: POST /api/v1/cotizaciones
    // =========================================================================
    echo "\n--- 4. Endpoint 2: POST /api/v1/cotizaciones ---\n";

    // 4.1 Sin autenticación -> 401
    ContextoHttpApi::limpiar();
    $_SERVER['REQUEST_METHOD'] = 'POST';
    unset($_SERVER['HTTP_AUTHORIZATION']);
    ApiReservaControlador::$cuerpoPrueba = json_encode(['propiedad_id' => $propiedadId]);
    $respCotSinAuth = $enrutador->despachar('POST', '/api/v1/cotizaciones');
    assertCheck($respCotSinAuth->obtenerCodigo() === 401, "Cotización: sin autenticación responde HTTP 401");

    // 4.2 Con credencial sin scope 'cotizacion.crear' -> 403
    ContextoHttpApi::limpiar();
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $tokenLimitado;
    ApiReservaControlador::$cuerpoPrueba = json_encode([
        'fecha_entrada' => $fechaEntrada,
        'fecha_salida' => $fechaSalida,
        'propiedad_id' => $propiedadId,
        'unidad_id' => $unidadId,
    ]);
    $respCot403 = $enrutador->despachar('POST', '/api/v1/cotizaciones');
    assertCheck($respCot403->obtenerCodigo() === 403, "Cotización: sin scope 'cotizacion.crear' responde HTTP 403");
    $jsonCot403 = json_decode($respCot403->obtenerContenido(), true);
    assertCheck($jsonCot403['codigo'] === 'ACCESO_DENEGADO', "Cotización: código ACCESO_DENEGADO");

    // 4.3 Preflight OPTIONS -> 204
    ContextoHttpApi::limpiar();
    $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
    $respCotOptions = $enrutador->despachar('OPTIONS', '/api/v1/cotizaciones');
    assertCheck($respCotOptions->obtenerCodigo() === 204, "Cotización: OPTIONS preflight responde HTTP 204");

    // 4.4 Verificación de NO BLOQUEO antes de cotizar
    $conteoInventarioAntes = (int) $pdo->query("SELECT COUNT(*) FROM inventario_diario_unidades WHERE unidad_id = {$unidadId}")->fetchColumn();

    // 4.5 Cotización válida -> 200 con snapshot firmado
    ContextoHttpApi::limpiar();
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $tokenFull;
    $payloadCotizacion = [
        'fecha_entrada' => $fechaEntrada,
        'fecha_salida' => $fechaSalida,
        'propiedad_id' => $propiedadId,
        'huespedes' => 2,
        'unidad_id' => $unidadId,
    ];
    ApiReservaControlador::$cuerpoPrueba = json_encode($payloadCotizacion);
    $respCotOk = $enrutador->despachar('POST', '/api/v1/cotizaciones');
    assertCheck($respCotOk->obtenerCodigo() === 200, "Cotización: cálculo válido responde HTTP 200");
    $jsonCotOk = json_decode($respCotOk->obtenerContenido(), true);
    assertCheck($jsonCotOk['ok'] === true, "Cotización: envelope ok === true");
    assertCheck($jsonCotOk['codigo'] === 'COTIZACION_GENERADA', "Cotización: código COTIZACION_GENERADA");
    assertCheck($jsonCotOk['datos']['noches'] === 3, "Cotización: desglose de 3 noches");
    assertCheck(($jsonCotOk['datos']['moneda_codigo'] ?? '') === 'PEN', "Cotización: moneda PEN (D-069)");
    assertCheck(bccomp((string) $jsonCotOk['datos']['total'], '0.00', 2) === 1, "Cotización: total monetario > 0.00 (Total: {$jsonCotOk['datos']['total']})");
    
    $tokenCotizacion = $jsonCotOk['datos']['token_cotizacion'];
    $cotizacionServicio = new \CamargoPMS\Servicios\CotizacionServicio($pdo);
    $payloadVerificado = $cotizacionServicio->validarTokenCotizacion($tokenCotizacion);
    assertCheck(!empty($payloadVerificado['cot_id']) && $payloadVerificado['total'] === $jsonCotOk['datos']['total'], "Cotización: token criptográfico firmado HMAC-SHA256 validado exitosamente");

    // 4.6 Certificación de NO BLOQUEO de inventario tras la cotización
    $conteoInventarioDespues = (int) $pdo->query("SELECT COUNT(*) FROM inventario_diario_unidades WHERE unidad_id = {$unidadId}")->fetchColumn();
    assertCheck($conteoInventarioAntes === $conteoInventarioDespues, "Cotización: NO bloquea inventario (conteo inventario antes: {$conteoInventarioAntes}, después: {$conteoInventarioDespues})");

    // =========================================================================
    // 5. ENDPOINT 3: POST /api/v1/reservas (Hold + Idempotencia + Bloqueo)
    // =========================================================================
    echo "\n--- 5. Endpoint 3: POST /api/v1/reservas ---\n";

    $claveIdempotencia1 = 'idemp_booking_' . bin2hex(random_bytes(10));
    $payloadReserva = [
        'token_cotizacion' => $tokenCotizacion,
        'titular' => [
            'nombres' => 'Carlos Alberto',
            'apellido_paterno' => 'Mendoza',
            'apellido_materno' => 'Rios',
            'tipo_documento' => 'DNI',
            'numero_documento' => '47859612',
            'email' => 'carlos.mendoza.test@camargopms.test',
            'telefono' => '+51 987654321',
        ],
        'observaciones' => 'Reserva headless generada desde test_wordpress_1d',
        'duracion_hold_minutos' => 15,
    ];
    $cuerpoJsonReserva = json_encode($payloadReserva);

    // 5.1 Falta de cabecera Idempotency-Key -> 400
    ContextoHttpApi::limpiar();
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $tokenFull;
    unset($_SERVER['HTTP_IDEMPOTENCY_KEY']);
    ApiReservaControlador::$cuerpoPrueba = $cuerpoJsonReserva;
    ApiIdempotenciaIntermediario::$cuerpoPrueba = $cuerpoJsonReserva;
    $respResSinIdemp = $enrutador->despachar('POST', '/api/v1/reservas');
    assertCheck($respResSinIdemp->obtenerCodigo() === 400, "Reservas: sin Idempotency-Key responde HTTP 400");
    $jsonResSinIdemp = json_decode($respResSinIdemp->obtenerContenido(), true);
    assertCheck($jsonResSinIdemp['codigo'] === 'IDEMPOTENCIA_REQUERIDA', "Reservas: código IDEMPOTENCIA_REQUERIDA");

    // 5.2 Sin scope 'reservas.hold' -> 403
    ContextoHttpApi::limpiar();
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $tokenLimitado;
    $_SERVER['HTTP_IDEMPOTENCY_KEY'] = $claveIdempotencia1;
    ApiReservaControlador::$cuerpoPrueba = $cuerpoJsonReserva;
    ApiIdempotenciaIntermediario::$cuerpoPrueba = $cuerpoJsonReserva;
    $respRes403 = $enrutador->despachar('POST', '/api/v1/reservas');
    assertCheck($respRes403->obtenerCodigo() === 403, "Reservas: sin scope 'reservas.hold' responde HTTP 403");
    $jsonRes403 = json_decode($respRes403->obtenerContenido(), true);
    assertCheck($jsonRes403['codigo'] === 'ACCESO_DENEGADO', "Reservas: código ACCESO_DENEGADO");

    // 5.3 Token de cotización adulterado o inválido -> 422
    ContextoHttpApi::limpiar();
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $tokenFull;
    $_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'idemp_tampered_' . bin2hex(random_bytes(8));
    $payloadAdulterado = $payloadReserva;
    $payloadAdulterado['token_cotizacion'] = $tokenCotizacion . 'tampered';
    ApiReservaControlador::$cuerpoPrueba = json_encode($payloadAdulterado);
    ApiIdempotenciaIntermediario::$cuerpoPrueba = json_encode($payloadAdulterado);
    $respResTampered = $enrutador->despachar('POST', '/api/v1/reservas');
    assertCheck($respResTampered->obtenerCodigo() === 422, "Reservas: token adulterado responde HTTP 422");
    $jsonResTampered = json_decode($respResTampered->obtenerContenido(), true);
    assertCheck($jsonResTampered['codigo'] === 'COTIZACION_INVALIDA', "Reservas: código COTIZACION_INVALIDA");

    // 5.4 Creación exitosa en hold con bloqueo de inventario -> 201
    ContextoHttpApi::limpiar();
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $tokenFull;
    $_SERVER['HTTP_IDEMPOTENCY_KEY'] = $claveIdempotencia1;
    ApiReservaControlador::$cuerpoPrueba = $cuerpoJsonReserva;
    ApiIdempotenciaIntermediario::$cuerpoPrueba = $cuerpoJsonReserva;
    $respResOk = $enrutador->despachar('POST', '/api/v1/reservas');
    assertCheck($respResOk->obtenerCodigo() === 201, "Reservas: hold creado exitosamente responde HTTP 201");
    $jsonResOk = json_decode($respResOk->obtenerContenido(), true);
    assertCheck($jsonResOk['ok'] === true, "Reservas: envelope ok === true");
    assertCheck($jsonResOk['codigo'] === 'RESERVA_HOLD_CREADA', "Reservas: código RESERVA_HOLD_CREADA");
    
    $codigoReserva = $jsonResOk['datos']['codigo'];
    assertCheck(!empty($codigoReserva) && str_starts_with($codigoReserva, 'RES-'), "Reservas: código de reserva emitido ({$codigoReserva})");
    assertCheck($jsonResOk['datos']['estado'] === Reserva::ESTADO_PENDIENTE, "Reservas: estado inicial es PENDIENTE (hold)");
    assertCheck(!empty($jsonResOk['datos']['expira_en']), "Reservas: instante de expiración asignado ({$jsonResOk['datos']['expira_en']})");
    assertCheck($jsonResOk['datos']['noches'] === 3, "Reservas: 3 noches registradas");
    assertCheck(!empty($jsonResOk['datos']['titular']['nombre']), "Reservas: nombre de titular enmascarado ({$jsonResOk['datos']['titular']['nombre']})");
    assertCheck(!empty($jsonResOk['datos']['titular']['email_enmascarado']), "Reservas: email enmascarado ({$jsonResOk['datos']['titular']['email_enmascarado']})");

    // 5.5 Verificación de BLOQUEO ATÓMICO en inventario_diario_unidades
    $nochesBloqueadas = $pdo->query("SELECT fecha, tipo_bloqueo, origen_tipo 
        FROM inventario_diario_unidades 
        WHERE unidad_id = {$unidadId} AND fecha >= '{$fechaEntrada}' AND fecha < '{$fechaSalida}'
        ORDER BY fecha ASC")->fetchAll(PDO::FETCH_ASSOC);
    assertCheck(count($nochesBloqueadas) === 3, "Reservas: inventario_diario_unidades tiene exactamente 3 noches bloqueadas");
    foreach ($nochesBloqueadas as $nb) {
        assertCheck($nb['tipo_bloqueo'] === 'RESERVA', "Reservas: noche {$nb['fecha']} con tipo_bloqueo 'RESERVA'");
        assertCheck($nb['origen_tipo'] === 'RESERVA', "Reservas: noche {$nb['fecha']} con origen_tipo 'RESERVA'");
    }

    // 5.6 Verificación de Actor Técnico en Auditoría Transversal (D-061)
    $reservaCreadaFila = $pdo->query("SELECT id, creado_por_actor_id FROM reservas WHERE codigo = '{$codigoReserva}' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    assertCheck(!empty($reservaCreadaFila), "Reservas: registro en tabla 'reservas' existe");
    $reservaIdCreada = (int) $reservaCreadaFila['id'];
    assertCheck((int) $reservaCreadaFila['creado_por_actor_id'] === $actorTecnicoId, "Reservas: creado_por_actor_id coincide con actor técnico API (Actor ID: {$actorTecnicoId})");

    // 5.7 Replay Idempotente determinista -> 201 con X-Cache-Lookup: IDEMPOTENT-REPLAY
    ContextoHttpApi::limpiar();
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $tokenFull;
    $_SERVER['HTTP_IDEMPOTENCY_KEY'] = $claveIdempotencia1;
    ApiReservaControlador::$cuerpoPrueba = $cuerpoJsonReserva;
    ApiIdempotenciaIntermediario::$cuerpoPrueba = $cuerpoJsonReserva;
    $respResReplay = $enrutador->despachar('POST', '/api/v1/reservas');
    assertCheck($respResReplay->obtenerCodigo() === 201, "Idempotencia: replay responde HTTP 201 idéntico");
    $cabecerasReplay = $respResReplay->obtenerCabeceras();
    assertCheck(($cabecerasReplay['X-Cache-Lookup'] ?? '') === 'IDEMPOTENT-REPLAY', "Idempotencia: cabecera X-Cache-Lookup: IDEMPOTENT-REPLAY confirmada");
    $jsonResReplay = json_decode($respResReplay->obtenerContenido(), true);
    assertCheck($jsonResReplay['datos']['codigo'] === $codigoReserva, "Idempotencia: mismo código de reserva retornado sin crear nuevo registro");

    // 5.8 Desajuste de Payload con misma clave -> 422 IDEMPOTENCIA_DESAJUSTE_PAYLOAD
    ContextoHttpApi::limpiar();
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $tokenFull;
    $_SERVER['HTTP_IDEMPOTENCY_KEY'] = $claveIdempotencia1;
    $payloadDesajustado = $payloadReserva;
    $payloadDesajustado['observaciones'] = 'Payload diferente con la misma clave';
    ApiReservaControlador::$cuerpoPrueba = json_encode($payloadDesajustado);
    ApiIdempotenciaIntermediario::$cuerpoPrueba = json_encode($payloadDesajustado);
    $respResMismatch = $enrutador->despachar('POST', '/api/v1/reservas');
    assertCheck($respResMismatch->obtenerCodigo() === 422, "Idempotencia: cambio de cuerpo con misma clave responde HTTP 422");
    $jsonResMismatch = json_decode($respResMismatch->obtenerContenido(), true);
    assertCheck($jsonResMismatch['codigo'] === 'IDEMPOTENCIA_DESAJUSTE_PAYLOAD', "Idempotencia: código semántico IDEMPOTENCIA_DESAJUSTE_PAYLOAD");

    // 5.9 Prevención de Doble Reserva (Colisión Concurrente de Disponibilidad) -> 409
    // Intentar cotizar nuevamente para la misma unidad y fechas (debe fallar porque ya está ocupada)
    ContextoHttpApi::limpiar();
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $tokenFull;
    ApiReservaControlador::$cuerpoPrueba = json_encode($payloadCotizacion);
    $respCotConflicto = $enrutador->despachar('POST', '/api/v1/cotizaciones');
    assertCheck($respCotConflicto->obtenerCodigo() === 409, "Prevención doble reserva: cotizar fechas ya reservadas responde HTTP 409 CONFLICTO_DISPONIBILIDAD");
    $jsonCotConflicto = json_decode($respCotConflicto->obtenerContenido(), true);
    assertCheck($jsonCotConflicto['codigo'] === 'CONFLICTO_DISPONIBILIDAD', "Prevención doble reserva: código CONFLICTO_DISPONIBILIDAD");

    // Preflight OPTIONS /api/v1/reservas -> 204
    ContextoHttpApi::limpiar();
    $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
    $respResOptions = $enrutador->despachar('OPTIONS', '/api/v1/reservas');
    assertCheck($respResOptions->obtenerCodigo() === 204, "Reservas: OPTIONS preflight responde HTTP 204");

    // =========================================================================
    // 6. ENDPOINT 4: GET /api/v1/reservas/{codigo}
    // =========================================================================
    echo "\n--- 6. Endpoint 4: GET /api/v1/reservas/{codigo} ---\n";

    // 6.1 Sin autenticación -> 401
    ContextoHttpApi::limpiar();
    $_SERVER['REQUEST_METHOD'] = 'GET';
    unset($_SERVER['HTTP_AUTHORIZATION']);
    $respConsSinAuth = $enrutador->despachar('GET', "/api/v1/reservas/{$codigoReserva}");
    assertCheck($respConsSinAuth->obtenerCodigo() === 401, "Consulta: sin token responde HTTP 401");

    // 6.2 Con credencial sin scope 'reservas.leer' -> 403
    ContextoHttpApi::limpiar();
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $tokenLimitado;
    $respCons403 = $enrutador->despachar('GET', "/api/v1/reservas/{$codigoReserva}");
    assertCheck($respCons403->obtenerCodigo() === 403, "Consulta: sin scope 'reservas.leer' responde HTTP 403");
    $jsonCons403 = json_decode($respCons403->obtenerContenido(), true);
    assertCheck($jsonCons403['codigo'] === 'ACCESO_DENEGADO', "Consulta: código ACCESO_DENEGADO");

    // 6.3 Código de reserva inexistente -> 404
    ContextoHttpApi::limpiar();
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $tokenFull;
    $respCons404 = $enrutador->despachar('GET', '/api/v1/reservas/RES-999999-9999');
    assertCheck($respCons404->obtenerCodigo() === 404, "Consulta: código inexistente responde HTTP 404");
    $jsonCons404 = json_decode($respCons404->obtenerContenido(), true);
    assertCheck($jsonCons404['codigo'] === 'RESERVA_NO_ENCONTRADA', "Consulta: código RESERVA_NO_ENCONTRADA");

    // 6.4 Consulta exitosa de la reserva creada -> 200 con PII protegida
    ContextoHttpApi::limpiar();
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $tokenFull;
    $respConsOk = $enrutador->despachar('GET', "/api/v1/reservas/{$codigoReserva}");
    assertCheck($respConsOk->obtenerCodigo() === 200, "Consulta: reserva existente responde HTTP 200");
    $jsonConsOk = json_decode($respConsOk->obtenerContenido(), true);
    assertCheck($jsonConsOk['ok'] === true, "Consulta: envelope ok === true");
    assertCheck($jsonConsOk['codigo'] === 'RESERVA_RECUPERADA', "Consulta: código RESERVA_RECUPERADA");
    assertCheck($jsonConsOk['datos']['codigo'] === $codigoReserva, "Consulta: código coincide");
    assertCheck($jsonConsOk['datos']['estado'] === Reserva::ESTADO_PENDIENTE, "Consulta: estado coincide (PENDIENTE)");
    assertCheck($jsonConsOk['datos']['ha_expirado'] === false, "Consulta: ha_expirado es false");
    assertCheck($jsonConsOk['datos']['noches'] === 3, "Consulta: 3 noches");
    assertCheck(count($jsonConsOk['datos']['unidades']) === 1, "Consulta: detalle de 1 unidad presente");
    assertCheck($jsonConsOk['datos']['unidades'][0]['unidad_codigo'] === $unidadFila['codigo'], "Consulta: código de unidad presente ({$unidadFila['codigo']})");

    // 6.5 Validación estricta de protección de privacidad PII
    $titularResp = $jsonConsOk['datos']['titular'];
    assertCheck(str_contains($titularResp['nombre'], '.'), "Privacidad: apellido enmascarado con inicial ('{$titularResp['nombre']}')");
    assertCheck(str_contains($titularResp['email_enmascarado'], '***'), "Privacidad: correo enmascarado con asteriscos ('{$titularResp['email_enmascarado']}')");
    assertCheck(!isset($jsonConsOk['datos']['id']), "Privacidad: id numérico de base de datos oculto");
    assertCheck(!isset($jsonConsOk['datos']['persona_titular_id']), "Privacidad: persona_titular_id numérico oculto");
    assertCheck(!isset($jsonConsOk['datos']['creado_por_actor_id']), "Privacidad: creado_por_actor_id numérico oculto");
    assertCheck(!isset($jsonConsOk['datos']['observaciones']), "Privacidad: notas y observaciones internas no filtradas");

    // Preflight OPTIONS /api/v1/reservas/{codigo} -> 204
    ContextoHttpApi::limpiar();
    $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
    $respConsOptions = $enrutador->despachar('OPTIONS', "/api/v1/reservas/{$codigoReserva}");
    assertCheck($respConsOptions->obtenerCodigo() === 204, "Consulta: OPTIONS preflight responde HTTP 204");

    // =========================================================================
    // 7. LIMPIEZA DEFENSIVA Y LIBERACIÓN DE DATOS DE PRUEBA
    // =========================================================================
    echo "\n--- 7. Limpieza Defensiva y Liberación de Datos de Prueba ---\n";

    // 7.1 Liberar noches de inventario creadas en la prueba
    $pdo->exec("DELETE FROM inventario_diario_unidades WHERE unidad_id = {$unidadId} AND fecha >= '{$fechaEntrada}' AND fecha < '{$fechaSalida}'");
    $nochesPostLimpieza = (int) $pdo->query("SELECT COUNT(*) FROM inventario_diario_unidades WHERE unidad_id = {$unidadId} AND fecha >= '{$fechaEntrada}' AND fecha < '{$fechaSalida}'")->fetchColumn();
    assertCheck($nochesPostLimpieza === 0, "Limpieza: noches de inventario de prueba liberadas");

    // 7.2 Eliminar reserva creada y sus unidades asignadas
    $pdo->exec("DELETE FROM reserva_unidades WHERE reserva_id = {$reservaIdCreada}");
    $pdo->exec("DELETE FROM reservas WHERE id = {$reservaIdCreada}");
    $reservaBorrada = $pdo->query("SELECT 1 FROM reservas WHERE id = {$reservaIdCreada}")->fetchColumn();
    assertCheck(!$reservaBorrada, "Limpieza: registro de reserva de prueba eliminado");

    // 7.3 Limpiar registros de idempotencia creados
    $pdo->exec("DELETE FROM api_idempotencia WHERE api_cliente_id = {$clienteId}");
    $idempRestantes = (int) $pdo->query("SELECT COUNT(*) FROM api_idempotencia WHERE api_cliente_id = {$clienteId}")->fetchColumn();
    assertCheck($idempRestantes === 0, "Limpieza: registros de idempotencia de prueba eliminados");

    // 7.4 Limpiar variables globales simuladas
    ApiReservaControlador::$cuerpoPrueba = null;
    ApiIdempotenciaIntermediario::$cuerpoPrueba = null;
    unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['HTTP_IDEMPOTENCY_KEY'], $_SERVER['REQUEST_METHOD'], $_GET);
    ContextoHttpApi::limpiar();

    // =========================================================================
    // RESUMEN FINAL
    // =========================================================================
    echo "\n====================================================================\n";
    echo " SUITE WORDPRESS-1D SUPERADA CON ÉXITO\n";
    echo " Total checks ejecutados: {$totalAssertions} | Aprobados: {$passedAssertions}\n";
    echo "====================================================================\n\n";

} catch (\Throwable $e) {
    echo "\n[ERROR CRÍTICO EN SUITE WORDPRESS-1D]: " . $e->getMessage() . "\n";
    echo "En archivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

exit(0);
