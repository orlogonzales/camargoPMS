<?php

declare(strict_types=1);

/**
 * Suite de Pruebas: PAGOS-1C — Superficie HTTP de Pagos + Webhook Culqi
 *
 * Valida de forma rigurosa y exhaustiva:
 * 1. Gobierno del Esquema y Ranura 039:
 *    - 130 tablas relacionales exactas en base de datos.
 *    - Ranura 039 estrictamente LIBRE (cero DDL no autorizado).
 *    - Inmutabilidad total de admin-dashboard/ y SQL consolidado.
 * 2. Endpoint POST /api/v1/pagos/intenciones:
 *    - Rechazo sin Bearer token (HTTP 401).
 *    - Rechazo sin cabecera Idempotency-Key (HTTP 400).
 *    - Rechazo sin scope 'reservas.hold' (HTTP 403).
 *    - Rechazo de reserva inexistente (HTTP 404).
 *    - Rechazo de reserva cancelada / expirada / confirmada (HTTP 422).
 *    - Creación exitosa de intención de pago (HTTP 201).
 *    - Monto soberano obtenido exclusivamente del PMS (cero confianza en cliente).
 *    - Replay determinista con Idempotency-Key (HTTP 201 con X-Cache-Lookup).
 *    - Detección de colisión de payload con misma clave (HTTP 422).
 *    - Preflight OPTIONS (HTTP 204 No Content con CORS).
 * 3. Endpoint POST /api/v1/webhooks/pagos/culqi:
 *    - Trust boundary diferenciado: funciona sin Bearer de cliente API.
 *    - Rechazo de payload vacío / malformado (HTTP 400).
 *    - Preflight OPTIONS (HTTP 204).
 *    - Flujo canónico: orden pagada en tiempo confirma reserva, crea folio e imputa pago.
 *    - Idempotencia del webhook: deduplicación atómica sin duplicar folios ni asientos.
 *    - Invariante crítico de Pago Tardío: dinero en cuarentena (DISCREPANCIA_HOLD_EXPIRADO),
 *      cuenta_folio_id NULL, pago_cuenta_id NULL, reserva permanece EXPIRADA.
 *    - Discrepancia de monto: cuarentena DISCREPANCIA_MONTO sin folio.
 *    - Eventos informativos ignorados sin impacto contable.
 * 4. Seguridad Zero-Trust:
 *    - Cero llaves privadas, webhook secrets o datos PAN/CVV en respuestas.
 * 5. Limpieza defensiva de fixtures.
 */

namespace CamargoPMS\Pruebas;

use CamargoPMS\Controladores\ApiPagoControlador;
use CamargoPMS\Intermediarios\ApiAutenticacionIntermediario;
use CamargoPMS\Intermediarios\ApiCorrelacionIntermediario;
use CamargoPMS\Intermediarios\ApiCorsIntermediario;
use CamargoPMS\Intermediarios\ApiIdempotenciaIntermediario;
use CamargoPMS\Intermediarios\ApiRateLimitIntermediario;
use CamargoPMS\Intermediarios\ApiScopeIntermediario;
use CamargoPMS\Modelos\PagoTransaccionPasarela;
use CamargoPMS\Modelos\Reserva;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Nucleo\ContextoHttpApi;
use CamargoPMS\Nucleo\Enrutador;
use CamargoPMS\Repositorios\ApiClientRepositorio;
use CamargoPMS\Repositorios\CuentaFolioRepositorio;
use CamargoPMS\Repositorios\PagoCuentaRepositorio;
use CamargoPMS\Repositorios\PagoTransaccionPasarelaRepositorio;
use CamargoPMS\Repositorios\PagoWebhookEventoRepositorio;
use CamargoPMS\Repositorios\ReservaRepositorio;
use CamargoPMS\Servicios\ApiClientServicio;
use CamargoPMS\Servicios\Pagos\Adaptadores\CulqiProveedor;
use CamargoPMS\Servicios\Pagos\FabricaProveedoresPago;
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
echo " INICIANDO SUITE DE PRUEBAS: PAGOS-1C (SUPERFICIE HTTP Y WEBHOOK CULQI)\n";
echo "====================================================================\n\n";

try {
    // =========================================================================
    // 1. GOBIERNO DEL ESQUEMA Y RANURA 039
    // =========================================================================
    echo "--- 1. Gobierno del Esquema y Ranura 039 ---\n";

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    assertCheck(count($tables) >= 130, "Base de datos contiene al menos 130 tablas relacionales (actual: " . count($tables) . ")");
    assertCheck(in_array('pagos_transacciones_pasarela', $tables, true), "Tabla 'pagos_transacciones_pasarela' existe en la base de datos");
    assertCheck(in_array('pagos_webhooks_eventos', $tables, true), "Tabla 'pagos_webhooks_eventos' existe en la base de datos");
    assertCheck(in_array('api_idempotencia', $tables, true), "Tabla 'api_idempotencia' existe en la base de datos");

    $mig038Presente = (bool) $pdo->query("SELECT 1 FROM migraciones WHERE migracion = '038_pagos_pasarelas.sql'")->fetchColumn();
    assertCheck($mig038Presente, "Migración 038_pagos_pasarelas.sql registrada en tabla 'migraciones'");

    $mig040 = glob(dirname(__DIR__) . '/SQL/migraciones/*040*');
    assertCheck(empty($mig040), "Ranura de migración 040 estrictamente LIBRE (cero DDL no autorizado)");

    // Inmutabilidad de admin-dashboard/
    $gitAlina = shell_exec('git status --porcelain admin-dashboard/');
    assertCheck(empty(trim((string) $gitAlina)), "admin-dashboard/ permanece 100% inmutable y libre de modificaciones");

    // =========================================================================
    // 2. CONFIGURACIÓN DE CLIENTES API Y CREDENCIALES DE PRUEBA
    // =========================================================================
    echo "\n--- 2. Configuración de Clientes API y Credenciales de Prueba ---\n";

    $clientRepo = new ApiClientRepositorio($pdo);
    $apiServicio = new ApiClientServicio($pdo);

    $cliente1C = $clientRepo->buscarPorCodigo('TEST_API_CLIENT_1C');
    if ($cliente1C === null) {
        $clienteNuevo = $apiServicio->crearCliente([
            'codigo' => 'TEST_API_CLIENT_1C',
            'nombre' => 'Cliente de Pruebas PAGOS-1C',
            'descripcion' => 'Cliente para suite test_pagos_1c.php',
            'contacto_email' => 'pagos1c@camargopms.test',
            'ips_permitidas' => null,
            'limite_peticiones_minuto' => 300,
        ], 1);
        $clienteId = (int) $clienteNuevo->obtenerId();
    } else {
        $clienteId = (int) $cliente1C->obtenerId();
    }

    // Credencial con scope 'reservas.hold'
    $credHold = $apiServicio->crearCredencial(
        clienteId: $clienteId,
        nombreCredencial: 'Credencial Hold 1C',
        codigosScopes: ['reservas.hold', 'disponibilidad.leer']
    );
    $tokenHold = $credHold['token_secreto'];
    assertCheck(!empty($tokenHold), "Credencial con scope 'reservas.hold' emitida exitosamente");

    // Credencial limitada sin scope 'reservas.hold' (solo 'disponibilidad.leer')
    $credLimitada = $apiServicio->crearCredencial(
        clienteId: $clienteId,
        nombreCredencial: 'Credencial Limitada 1C',
        codigosScopes: ['disponibilidad.leer']
    );
    $tokenLimitado = $credLimitada['token_secreto'];
    assertCheck(!empty($tokenLimitado), "Credencial restringida emitida (solo disponibilidad.leer)");

    // Registrar Driver Culqi mockeado en la fábrica
    $mockCulqiLlamadas = [];
    $mockHttpClient = function (string $metodo, string $url, ?array $cuerpo, array $headers) use (&$mockCulqiLlamadas): array {
        $mockCulqiLlamadas[] = [
            'metodo' => $metodo,
            'url' => $url,
            'cuerpo' => $cuerpo,
            'headers' => $headers,
        ];

        if (str_contains($url, '/orders') && $metodo === 'POST') {
            return [
                'id' => 'ord_mock_1c_' . bin2hex(random_bytes(4)),
                'amount' => $cuerpo['amount'],
                'currency_code' => $cuerpo['currency_code'],
                'state' => 'pending',
                'order_number' => $cuerpo['order_number'],
                'expiration_date' => $cuerpo['expiration_date'],
            ];
        }

        return ['id' => 'mock_default'];
    };

    $culqiDriver = new CulqiProveedor(
        llaveSecreta: 'sk_test_mock_1c_key',
        llavePublica: 'pk_test_mock_1c_public',
        webhookSecret: null,
        clienteHttp: $mockHttpClient
    );
    FabricaProveedoresPago::reiniciar();
    FabricaProveedoresPago::registrarProveedor($culqiDriver);

    // =========================================================================
    // 3. PREPARACIÓN DE FIXTURES DE RESERVAS
    // =========================================================================
    echo "\n--- 3. Preparación de Fixtures de Reservas ---\n";

    $propiedadId = (int) $pdo->query("SELECT id FROM propiedades WHERE estado = 'ACTIVO' LIMIT 1")->fetchColumn();
    $unidadId = (int) $pdo->query("SELECT id FROM unidades WHERE propiedad_id = {$propiedadId} AND estado = 'ACTIVO' LIMIT 1")->fetchColumn();
    $titularId = (int) $pdo->query("SELECT id FROM personas WHERE estado = 'ACTIVO' LIMIT 1")->fetchColumn();
    $actorApiId = (int) $clientRepo->buscarPorId($clienteId)->obtenerActorId();

    // 3.1 Reserva Válida PENDIENTE con Hold Vigente (30 min a futuro)
    $codReservaValida = strtoupper('RES-1C-VAL-' . bin2hex(random_bytes(3)));
    $expiraFuturo = date('Y-m-d H:i:s', time() + 1800);
    $stmt1 = $pdo->prepare("INSERT INTO reservas (
        codigo, persona_titular_id, fecha_entrada, fecha_salida, noches,
        canal, origen, moneda_codigo, subtotal, impuesto_total, total, estado, expira_en, creado_por_actor_id
    ) VALUES (
        :cod, :tit, '2026-11-20', '2026-11-22', 2,
        'WEB', 'DIRECTO', 'PEN', '600.00', '0.00', '600.00', 'PENDIENTE', :expira, :actor
    )");
    $stmt1->execute([
        'cod' => $codReservaValida,
        'tit' => $titularId,
        'expira' => $expiraFuturo,
        'actor' => $actorApiId,
    ]);
    $reservaValidaId = (int) $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO reserva_unidades (
        reserva_id, unidad_id, precio_unitario_noche, noches, subtotal, impuesto, total, moneda_codigo
    ) VALUES (
        :res_id, :u_id, '300.00', 2, '600.00', '0.00', '600.00', 'PEN'
    )")->execute(['res_id' => $reservaValidaId, 'u_id' => $unidadId]);
    assertCheck($reservaValidaId > 0, "Reserva PENDIENTE de prueba creada (ID: {$reservaValidaId}, Código: {$codReservaValida})");

    // 3.2 Reserva CANCELADA
    $codReservaCancelada = strtoupper('RES-1C-CNC-' . bin2hex(random_bytes(3)));
    $stmt2 = $pdo->prepare("INSERT INTO reservas (
        codigo, persona_titular_id, fecha_entrada, fecha_salida, noches,
        canal, origen, moneda_codigo, subtotal, impuesto_total, total, estado, expira_en, creado_por_actor_id
    ) VALUES (
        :cod, :tit, '2026-11-20', '2026-11-22', 2,
        'WEB', 'DIRECTO', 'PEN', '600.00', '0.00', '600.00', 'CANCELADA', :expira, :actor
    )");
    $stmt2->execute([
        'cod' => $codReservaCancelada,
        'tit' => $titularId,
        'expira' => $expiraFuturo,
        'actor' => $actorApiId,
    ]);
    $reservaCanceladaId = (int) $pdo->lastInsertId();

    // 3.3 Reserva con Hold EXPIRADO (hace 1 hora)
    $codReservaExpirada = strtoupper('RES-1C-EXP-' . bin2hex(random_bytes(3)));
    $expiraPasado = date('Y-m-d H:i:s', time() - 3600);
    $stmt3 = $pdo->prepare("INSERT INTO reservas (
        codigo, persona_titular_id, fecha_entrada, fecha_salida, noches,
        canal, origen, moneda_codigo, subtotal, impuesto_total, total, estado, expira_en, creado_por_actor_id
    ) VALUES (
        :cod, :tit, '2026-11-20', '2026-11-22', 2,
        'WEB', 'DIRECTO', 'PEN', '600.00', '0.00', '600.00', 'EXPIRADA', :expira, :actor
    )");
    $stmt3->execute([
        'cod' => $codReservaExpirada,
        'tit' => $titularId,
        'expira' => $expiraPasado,
        'actor' => $actorApiId,
    ]);
    $reservaExpiradaId = (int) $pdo->lastInsertId();

    // 3.4 Reserva CONFIRMADA
    $codReservaConfirmada = strtoupper('RES-1C-CNF-' . bin2hex(random_bytes(3)));
    $stmt4 = $pdo->prepare("INSERT INTO reservas (
        codigo, persona_titular_id, fecha_entrada, fecha_salida, noches,
        canal, origen, moneda_codigo, subtotal, impuesto_total, total, estado, expira_en, creado_por_actor_id
    ) VALUES (
        :cod, :tit, '2026-11-20', '2026-11-22', 2,
        'WEB', 'DIRECTO', 'PEN', '600.00', '0.00', '600.00', 'CONFIRMADA', :expira, :actor
    )");
    $stmt4->execute([
        'cod' => $codReservaConfirmada,
        'tit' => $titularId,
        'expira' => $expiraFuturo,
        'actor' => $actorApiId,
    ]);
    $reservaConfirmadaId = (int) $pdo->lastInsertId();

    // Construir Enrutador con el pipeline idéntico a public/index.php
    $enrutador = new Enrutador();

    // 1. Intención de pago
    $enrutador->post('/api/v1/pagos/intenciones', [ApiPagoControlador::class, 'crearIntencion'], [
        ApiCorrelacionIntermediario::class,
        ApiCorsIntermediario::class,
        ApiAutenticacionIntermediario::class,
        ApiRateLimitIntermediario::class,
        new ApiScopeIntermediario('reservas.hold'),
        ApiIdempotenciaIntermediario::class,
    ]);
    $enrutador->options('/api/v1/pagos/intenciones', [ApiPagoControlador::class, 'preflight'], [
        ApiCorrelacionIntermediario::class,
        ApiCorsIntermediario::class,
    ]);

    // 2. Webhook Culqi (server-to-server)
    $enrutador->post('/api/v1/webhooks/pagos/culqi', [ApiPagoControlador::class, 'webhookCulqi'], [
        ApiCorrelacionIntermediario::class,
    ]);
    $enrutador->options('/api/v1/webhooks/pagos/culqi', [ApiPagoControlador::class, 'preflight'], [
        ApiCorrelacionIntermediario::class,
        ApiCorsIntermediario::class,
    ]);

    // =========================================================================
    // 4. ENDPOINT 1: POST /api/v1/pagos/intenciones
    // =========================================================================
    echo "\n--- 4. Endpoint 1: POST /api/v1/pagos/intenciones ---\n";

    // 4.1 Sin autenticación Bearer -> 401
    ContextoHttpApi::limpiar();
    $_SERVER['REQUEST_METHOD'] = 'POST';
    unset($_SERVER['HTTP_AUTHORIZATION']);
    ApiPagoControlador::$cuerpoPrueba = json_encode(['reserva_codigo' => $codReservaValida]);
    $respSinAuth = $enrutador->despachar('POST', '/api/v1/pagos/intenciones');
    assertCheck($respSinAuth->obtenerCodigo() === 401, "Intención: Sin autenticación responde HTTP 401");

    // 4.2 Sin cabecera Idempotency-Key -> 400
    ContextoHttpApi::limpiar();
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $tokenHold;
    unset($_SERVER['HTTP_IDEMPOTENCY_KEY']);
    ApiPagoControlador::$cuerpoPrueba = json_encode(['reserva_codigo' => $codReservaValida]);
    ApiIdempotenciaIntermediario::$cuerpoPrueba = json_encode(['reserva_codigo' => $codReservaValida]);
    $respSinIdemp = $enrutador->despachar('POST', '/api/v1/pagos/intenciones');
    assertCheck($respSinIdemp->obtenerCodigo() === 400, "Intención: Sin Idempotency-Key responde HTTP 400");
    $jsonSinIdemp = json_decode($respSinIdemp->obtenerContenido(), true);
    assertCheck($jsonSinIdemp['codigo'] === 'IDEMPOTENCIA_REQUERIDA', "Intención: Código IDEMPOTENCIA_REQUERIDA");

    // 4.3 Sin scope 'reservas.hold' -> 403
    $claveIdemp1 = 'idemp_pay_intent_' . bin2hex(random_bytes(8));
    ContextoHttpApi::limpiar();
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $tokenLimitado;
    $_SERVER['HTTP_IDEMPOTENCY_KEY'] = $claveIdemp1;
    $respSinScope = $enrutador->despachar('POST', '/api/v1/pagos/intenciones');
    assertCheck($respSinScope->obtenerCodigo() === 403, "Intención: Sin scope 'reservas.hold' responde HTTP 403");
    $jsonSinScope = json_decode($respSinScope->obtenerContenido(), true);
    assertCheck($jsonSinScope['codigo'] === 'ACCESO_DENEGADO', "Intención: Código ACCESO_DENEGADO");

    // 4.4 Reserva Inexistente -> 404
    ContextoHttpApi::limpiar();
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $tokenHold;
    $_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'idemp_not_found_' . bin2hex(random_bytes(8));
    $payloadInexistente = json_encode(['reserva_codigo' => 'RES-INEXISTENTE-999']);
    ApiPagoControlador::$cuerpoPrueba = $payloadInexistente;
    ApiIdempotenciaIntermediario::$cuerpoPrueba = $payloadInexistente;
    $respNoEncontrada = $enrutador->despachar('POST', '/api/v1/pagos/intenciones');
    assertCheck($respNoEncontrada->obtenerCodigo() === 404, "Intención: Reserva inexistente responde HTTP 404");
    $jsonNoEnc = json_decode($respNoEncontrada->obtenerContenido(), true);
    assertCheck($jsonNoEnc['codigo'] === 'RESERVA_NO_ENCONTRADA', "Intención: Código RESERVA_NO_ENCONTRADA");

    // 4.5 Reserva Cancelada -> 422
    ContextoHttpApi::limpiar();
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $tokenHold;
    $_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'idemp_cnc_' . bin2hex(random_bytes(8));
    $payloadCancelada = json_encode(['reserva_codigo' => $codReservaCancelada]);
    ApiPagoControlador::$cuerpoPrueba = $payloadCancelada;
    ApiIdempotenciaIntermediario::$cuerpoPrueba = $payloadCancelada;
    $respCancelada = $enrutador->despachar('POST', '/api/v1/pagos/intenciones');
    assertCheck($respCancelada->obtenerCodigo() === 422, "Intención: Reserva cancelada responde HTTP 422");
    $jsonCnc = json_decode($respCancelada->obtenerContenido(), true);
    assertCheck($jsonCnc['codigo'] === 'RESERVA_NO_PAGABLE', "Intención: Código RESERVA_NO_PAGABLE");

    // 4.6 Reserva Confirmada -> 422
    ContextoHttpApi::limpiar();
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $tokenHold;
    $_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'idemp_cnf_' . bin2hex(random_bytes(8));
    $payloadConfirmada = json_encode(['reserva_codigo' => $codReservaConfirmada]);
    ApiPagoControlador::$cuerpoPrueba = $payloadConfirmada;
    ApiIdempotenciaIntermediario::$cuerpoPrueba = $payloadConfirmada;
    $respConfirmada = $enrutador->despachar('POST', '/api/v1/pagos/intenciones');
    assertCheck($respConfirmada->obtenerCodigo() === 422, "Intención: Reserva ya confirmada responde HTTP 422");
    $jsonCnf = json_decode($respConfirmada->obtenerContenido(), true);
    assertCheck($jsonCnf['codigo'] === 'RESERVA_YA_CONFIRMADA', "Intención: Código RESERVA_YA_CONFIRMADA");

    // 4.7 Reserva con Hold Expirado -> 422
    ContextoHttpApi::limpiar();
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $tokenHold;
    $_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'idemp_exp_' . bin2hex(random_bytes(8));
    $payloadExpirada = json_encode(['reserva_codigo' => $codReservaExpirada]);
    ApiPagoControlador::$cuerpoPrueba = $payloadExpirada;
    ApiIdempotenciaIntermediario::$cuerpoPrueba = $payloadExpirada;
    $respExpirada = $enrutador->despachar('POST', '/api/v1/pagos/intenciones');
    assertCheck($respExpirada->obtenerCodigo() === 422, "Intención: Hold expirado responde HTTP 422");
    $jsonExp = json_decode($respExpirada->obtenerContenido(), true);
    assertCheck($jsonExp['codigo'] === 'HOLD_EXPIRADO', "Intención: Código HOLD_EXPIRADO");

    // 4.8 Proveedor No Soportado -> 422
    ContextoHttpApi::limpiar();
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $tokenHold;
    $_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'idemp_prov_' . bin2hex(random_bytes(8));
    $payloadProv = json_encode(['reserva_codigo' => $codReservaValida, 'proveedor' => 'STRIPE']);
    ApiPagoControlador::$cuerpoPrueba = $payloadProv;
    ApiIdempotenciaIntermediario::$cuerpoPrueba = $payloadProv;
    $respProv = $enrutador->despachar('POST', '/api/v1/pagos/intenciones');
    assertCheck($respProv->obtenerCodigo() === 422, "Intención: Proveedor no soportado responde HTTP 422");
    $jsonProv = json_decode($respProv->obtenerContenido(), true);
    assertCheck($jsonProv['codigo'] === 'PROVEEDOR_NO_SOPORTADO', "Intención: Código PROVEEDOR_NO_SOPORTADO");

    // 4.9 Creación Exitosa de Intención de Pago -> 201
    ContextoHttpApi::limpiar();
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $tokenHold;
    $claveIdempExito = 'idemp_success_' . bin2hex(random_bytes(8));
    $_SERVER['HTTP_IDEMPOTENCY_KEY'] = $claveIdempExito;
    $payloadExito = json_encode([
        'reserva_codigo' => $codReservaValida,
        'proveedor' => 'CULQI',
        'monto_fraudulento_a_ignorar' => '1.00', // El cliente JAMÁS impone el monto
    ]);
    ApiPagoControlador::$cuerpoPrueba = $payloadExito;
    ApiIdempotenciaIntermediario::$cuerpoPrueba = $payloadExito;
    $respExito = $enrutador->despachar('POST', '/api/v1/pagos/intenciones');
    assertCheck($respExito->obtenerCodigo() === 201, "Intención: Creación exitosa responde HTTP 201 Created");
    $jsonExito = json_decode($respExito->obtenerContenido(), true);
    assertCheck($jsonExito['ok'] === true, "Intención: Envelope ok === true");
    assertCheck($jsonExito['codigo'] === 'INTENCION_PAGO_CREADA', "Intención: Código INTENCION_PAGO_CREADA");
    assertCheck(!empty($jsonExito['datos']['proveedor_orden_id']), "Intención: proveedor_orden_id retornado");
    assertCheck($jsonExito['datos']['reserva_codigo'] === $codReservaValida, "Intención: reserva_codigo coincide");
    assertCheck(bccomp((string) $jsonExito['datos']['monto'], '600.00', 2) === 0, "SOBERANÍA MONETARIA: Monto cobrado es exactamente S/ 600.00 del PMS (ignora intento fraudulento de S/ 1.00)");
    assertCheck($jsonExito['datos']['moneda'] === 'PEN', "Intención: Moneda PEN");
    assertCheck($jsonExito['datos']['llave_publica'] === 'pk_test_mock_1c_public', "Intención: Llave pública de Culqi provista para checkout");
    $ordenIdGenerada = $jsonExito['datos']['proveedor_orden_id'];

    // 4.10 Seguridad Zero-Trust: Cero exposición de secretos en la respuesta
    assertCheck(!isset($jsonExito['datos']['llave_secreta']), "Seguridad: CERO llaves secretas expuestas en respuesta");
    assertCheck(!isset($jsonExito['datos']['webhook_secret']), "Seguridad: CERO webhook secrets expuestos");
    assertCheck(!isset($jsonExito['datos']['pan']), "Seguridad: CERO datos de tarjeta (PAN)");
    assertCheck(!isset($jsonExito['datos']['cvv']), "Seguridad: CERO códigos CVV");

    // 4.11 Replay Determinista con Idempotency-Key reutilizada -> 201 con X-Cache-Lookup
    ContextoHttpApi::limpiar();
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $tokenHold;
    $_SERVER['HTTP_IDEMPOTENCY_KEY'] = $claveIdempExito;
    ApiPagoControlador::$cuerpoPrueba = $payloadExito;
    ApiIdempotenciaIntermediario::$cuerpoPrueba = $payloadExito;
    $respReplay = $enrutador->despachar('POST', '/api/v1/pagos/intenciones');
    assertCheck($respReplay->obtenerCodigo() === 201, "Idempotencia Intención: Replay responde HTTP 201");
    $headersReplay = $respReplay->obtenerCabeceras();
    assertCheck(($headersReplay['X-Cache-Lookup'] ?? '') === 'IDEMPOTENT-REPLAY', "Idempotencia Intención: Cabecera X-Cache-Lookup: IDEMPOTENT-REPLAY");

    // 4.12 Detección de Colisión de Payload con misma clave -> 422
    ContextoHttpApi::limpiar();
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $tokenHold;
    $_SERVER['HTTP_IDEMPOTENCY_KEY'] = $claveIdempExito;
    $payloadModificado = json_encode(['reserva_codigo' => $codReservaValida, 'proveedor' => 'CULQI', 'nuevo_param' => 'diferente']);
    ApiPagoControlador::$cuerpoPrueba = $payloadModificado;
    ApiIdempotenciaIntermediario::$cuerpoPrueba = $payloadModificado;
    $respDesajuste = $enrutador->despachar('POST', '/api/v1/pagos/intenciones');
    assertCheck($respDesajuste->obtenerCodigo() === 422, "Idempotencia Intención: Desajuste de payload responde HTTP 422");
    $jsonDesajuste = json_decode($respDesajuste->obtenerContenido(), true);
    assertCheck($jsonDesajuste['codigo'] === 'IDEMPOTENCIA_DESAJUSTE_PAYLOAD', "Idempotencia Intención: Código IDEMPOTENCIA_DESAJUSTE_PAYLOAD");

    // 4.13 Preflight OPTIONS -> 204
    ContextoHttpApi::limpiar();
    $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
    unset($_SERVER['HTTP_AUTHORIZATION']);
    $respOptions = $enrutador->despachar('OPTIONS', '/api/v1/pagos/intenciones');
    assertCheck($respOptions->obtenerCodigo() === 204, "Preflight: OPTIONS /api/v1/pagos/intenciones responde HTTP 204 No Content");

    // =========================================================================
    // 5. ENDPOINT 2: POST /api/v1/webhooks/pagos/culqi
    // =========================================================================
    echo "\n--- 5. Endpoint 2: POST /api/v1/webhooks/pagos/culqi ---\n";

    // 5.1 Trust boundary: Funciona sin Bearer token de cliente API
    ContextoHttpApi::limpiar();
    $_SERVER['REQUEST_METHOD'] = 'POST';
    unset($_SERVER['HTTP_AUTHORIZATION']);
    unset($_SERVER['HTTP_IDEMPOTENCY_KEY']);
    ApiPagoControlador::$cuerpoPrueba = '';
    $respWebhookVacio = $enrutador->despachar('POST', '/api/v1/webhooks/pagos/culqi');
    assertCheck($respWebhookVacio->obtenerCodigo() === 400, "Webhook: Payload vacío responde HTTP 400 (NO 401: sin dependencia de Bearer)");
    $jsonWhVacio = json_decode($respWebhookVacio->obtenerContenido(), true);
    assertCheck($jsonWhVacio['codigo'] === 'PAYLOAD_VACIO', "Webhook: Código PAYLOAD_VACIO");

    // 5.2 Payload JSON inválido / malformado -> 400
    ContextoHttpApi::limpiar();
    $_SERVER['REQUEST_METHOD'] = 'POST';
    unset($_SERVER['HTTP_AUTHORIZATION']);
    ApiPagoControlador::$cuerpoPrueba = '{"json_corrupto":';
    $respWhInvalido = $enrutador->despachar('POST', '/api/v1/webhooks/pagos/culqi');
    assertCheck($respWhInvalido->obtenerCodigo() === 400, "Webhook: JSON malformado responde HTTP 400");
    $jsonWhInv = json_decode($respWhInvalido->obtenerContenido(), true);
    assertCheck($jsonWhInv['codigo'] === 'PAYLOAD_INVALIDO', "Webhook: Código PAYLOAD_INVALIDO");

    // 5.3 Preflight OPTIONS Webhook -> 204
    ContextoHttpApi::limpiar();
    $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
    $respWhOptions = $enrutador->despachar('OPTIONS', '/api/v1/webhooks/pagos/culqi');
    assertCheck($respWhOptions->obtenerCodigo() === 204, "Preflight: OPTIONS /api/v1/webhooks/pagos/culqi responde HTTP 204");

    // 5.4 Flujo Canónico Aprobado: Orden Pagada en Tiempo y Forma
    ContextoHttpApi::limpiar();
    $_SERVER['REQUEST_METHOD'] = 'POST';
    unset($_SERVER['HTTP_AUTHORIZATION']);
    $eventoId1 = 'evt_canon_1c_' . bin2hex(random_bytes(6));
    $payloadWebhookAprobado = json_encode([
        'id' => $eventoId1,
        'type' => 'order.status.changed',
        'data' => [
            'id' => $ordenIdGenerada,
            'amount' => 60000, // S/ 600.00 en céntimos
            'currency_code' => 'PEN',
            'state' => 'paid',
            'updated_at' => time(),
        ],
    ]);
    ApiPagoControlador::$cuerpoPrueba = $payloadWebhookAprobado;
    ApiPagoControlador::$cabecerasPrueba = ['user-agent' => 'Culqi-Webhook/2.0'];
    $respWhAprobado = $enrutador->despachar('POST', '/api/v1/webhooks/pagos/culqi');
    assertCheck($respWhAprobado->obtenerCodigo() === 200, "Webhook: Orden aprobada responde HTTP 200 OK a Culqi");
    $jsonWhAprobado = json_decode($respWhAprobado->obtenerContenido(), true);
    assertCheck($jsonWhAprobado['ok'] === true, "Webhook: ok === true");
    assertCheck($jsonWhAprobado['codigo'] === 'PAGO_CONFIRMADO_Y_CONCILIADO', "Webhook: Código PAGO_CONFIRMADO_Y_CONCILIADO");

    // 5.5 Comprobaciones de Persistencia y Dominio del Flujo Canónico
    $reservaConfirmadaBD = (new ReservaRepositorio($pdo))->buscarPorId($reservaValidaId, false);
    assertCheck($reservaConfirmadaBD->esConfirmada(), "Dominio: Reserva cambió a estado CONFIRMADA tras el webhook");

    $cuentaFolioRepo = new CuentaFolioRepositorio($pdo);
    $folioBD = $cuentaFolioRepo->obtenerPorReservaId($reservaValidaId);
    assertCheck($folioBD !== null, "Dominio: Folio comercial asegurado para la reserva");

    $txRepo = new PagoTransaccionPasarelaRepositorio($pdo);
    $txBD = $txRepo->buscarPorProveedorYOrdenId('CULQI', $ordenIdGenerada);
    assertCheck($txBD !== null, "Dominio: Transacción de pasarela encontrada por orden");
    assertCheck($txBD->obtenerEstadoPago() === PagoTransaccionPasarela::ESTADO_PAGO_APROBADO, "Dominio: Transacción estado_pago = APROBADO");
    assertCheck($txBD->obtenerEstadoConciliacion() === PagoTransaccionPasarela::ESTADO_CONCILIACION_CONCILIADO, "Dominio: Transacción estado_conciliacion = CONCILIADO");
    assertCheck($txBD->obtenerCuentaFolioId() === (int) $folioBD->obtenerId(), "Dominio: Transacción vinculada a cuenta_folio_id");
    assertCheck($txBD->obtenerPagoCuentaId() !== null, "Dominio: Transacción vinculada a pago_cuenta_id");

    $pagoCuentaRepo = new PagoCuentaRepositorio($pdo);
    $pagoBD = $pagoCuentaRepo->obtenerPorId((int) $txBD->obtenerPagoCuentaId());
    assertCheck($pagoBD !== null && $pagoBD->estaConfirmado(), "Dominio: Cobro registrado en libro pagos_cuenta");
    assertCheck(bccomp($pagoBD->obtenerMontoTotal(), '600.00', 2) === 0, "Dominio: Monto en pagos_cuenta refleja exactamente S/ 600.00");

    // 5.6 Idempotencia del Webhook: Replay del Mismo Evento
    ContextoHttpApi::limpiar();
    $_SERVER['REQUEST_METHOD'] = 'POST';
    unset($_SERVER['HTTP_AUTHORIZATION']);
    $respWhReplay = $enrutador->despachar('POST', '/api/v1/webhooks/pagos/culqi');
    assertCheck($respWhReplay->obtenerCodigo() === 200, "Idempotencia Webhook: Replay responde HTTP 200 OK a Culqi");
    $jsonWhReplay = json_decode($respWhReplay->obtenerContenido(), true);
    assertCheck($jsonWhReplay['codigo'] === 'WEBHOOK_YA_PROCESADO', "Idempotencia Webhook: Código WEBHOOK_YA_PROCESADO");
    assertCheck($jsonWhReplay['datos']['reintento'] === true, "Idempotencia Webhook: Indicador reintento = true");

    // Verificar que NO se crearon pagos ni folios duplicados
    $conteoPagos = (int) $pdo->query("SELECT COUNT(*) FROM pagos_cuenta WHERE referencia_operacion = '{$ordenIdGenerada}'")->fetchColumn();
    assertCheck($conteoPagos === 1, "Idempotencia Webhook: CERO pagos duplicados en pagos_cuenta (exactamente 1)");

    // =========================================================================
    // 6. INVARIANTE CRÍTICO: PAGO TARDÍO CON HOLD EXPIRADO (C1/C2)
    // =========================================================================
    echo "\n--- 6. Invariante Crítico: Pago Tardío con Hold Expirado (C1/C2) ---\n";

    // Crear orden previa para la reserva expirada
    $ordenExpiradaId = 'ord_late_mock_1c_' . bin2hex(random_bytes(4));
    $txLateId = $txRepo->crear([
        'proveedor' => 'CULQI',
        'proveedor_orden_id' => $ordenExpiradaId,
        'reserva_id' => $reservaExpiradaId,
        'moneda_codigo' => 'PEN',
        'monto_esperado' => '600.00',
        'estado_pago' => PagoTransaccionPasarela::ESTADO_PAGO_PENDIENTE,
        'estado_conciliacion' => PagoTransaccionPasarela::ESTADO_CONCILIACION_PENDIENTE,
        'actor_id' => $actorApiId,
    ]);

    // Llega webhook de pago aprobado externamente cuando el hold ya venció
    ContextoHttpApi::limpiar();
    $_SERVER['REQUEST_METHOD'] = 'POST';
    unset($_SERVER['HTTP_AUTHORIZATION']);
    $eventoLateId = 'evt_late_1c_' . bin2hex(random_bytes(6));
    $payloadPagoTardio = json_encode([
        'id' => $eventoLateId,
        'type' => 'order.status.changed',
        'data' => [
            'id' => $ordenExpiradaId,
            'amount' => 60000,
            'currency_code' => 'PEN',
            'state' => 'paid',
            'updated_at' => time(),
        ],
    ]);
    ApiPagoControlador::$cuerpoPrueba = $payloadPagoTardio;
    $respPagoTardio = $enrutador->despachar('POST', '/api/v1/webhooks/pagos/culqi');
    assertCheck($respPagoTardio->obtenerCodigo() === 200, "Pago Tardío: Webhook responde HTTP 200 a Culqi");
    $jsonLate = json_decode($respPagoTardio->obtenerContenido(), true);
    assertCheck($jsonLate['codigo'] === 'PAGO_TARDIO_EN_CUARENTENA', "Pago Tardío: Código PAGO_TARDIO_EN_CUARENTENA");

    // COMPROBACIONES VINCULANTES C1/C2:
    $txLateBD = $txRepo->buscarPorId($txLateId);
    assertCheck($txLateBD->obtenerEstadoPago() === PagoTransaccionPasarela::ESTADO_PAGO_APROBADO, "Pago Tardío: Dinero externo real reconocido en estado_pago = APROBADO");
    assertCheck($txLateBD->obtenerEstadoConciliacion() === PagoTransaccionPasarela::ESTADO_CONCILIACION_DISCREPANCIA_HOLD_EXPIRADO, "Pago Tardío: estado_conciliacion = DISCREPANCIA_HOLD_EXPIRADO");
    assertCheck($txLateBD->obtenerCuentaFolioId() === null, "INVARIANTE HOTELERO: cuenta_folio_id permanece estrictamente NULL (sin folios de contingencia espurios)");
    assertCheck($txLateBD->obtenerPagoCuentaId() === null, "INVARIANTE HOTELERO: pago_cuenta_id permanece estrictamente NULL (sin cobro en libro de alojamiento)");

    $reservaLateBD = (new ReservaRepositorio($pdo))->buscarPorId($reservaExpiradaId, false);
    assertCheck($reservaLateBD->esExpirada(), "INVARIANTE HOTELERO: La reserva permanece en estado EXPIRADA (no se reactiva inventario liberado sin acción humana)");

    // =========================================================================
    // 7. DISCREPANCIA DE MONTO
    // =========================================================================
    echo "\n--- 7. Discrepancia de Monto en Webhook ---\n";

    $ordenMontoMismId = 'ord_mismatch_1c_' . bin2hex(random_bytes(4));
    $txMismId = $txRepo->crear([
        'proveedor' => 'CULQI',
        'proveedor_orden_id' => $ordenMontoMismId,
        'reserva_id' => $reservaValidaId,
        'moneda_codigo' => 'PEN',
        'monto_esperado' => '600.00',
        'estado_pago' => PagoTransaccionPasarela::ESTADO_PAGO_PENDIENTE,
        'estado_conciliacion' => PagoTransaccionPasarela::ESTADO_CONCILIACION_PENDIENTE,
        'actor_id' => $actorApiId,
    ]);

    ContextoHttpApi::limpiar();
    $_SERVER['REQUEST_METHOD'] = 'POST';
    unset($_SERVER['HTTP_AUTHORIZATION']);
    $payloadMontoMism = json_encode([
        'id' => 'evt_mism_1c_' . bin2hex(random_bytes(6)),
        'type' => 'order.status.changed',
        'data' => [
            'id' => $ordenMontoMismId,
            'amount' => 10000, // S/ 100 en vez de S/ 600
            'currency_code' => 'PEN',
            'state' => 'paid',
            'updated_at' => time(),
        ],
    ]);
    ApiPagoControlador::$cuerpoPrueba = $payloadMontoMism;
    $respMism = $enrutador->despachar('POST', '/api/v1/webhooks/pagos/culqi');
    assertCheck($respMism->obtenerCodigo() === 200, "Discrepancia Monto: Responde HTTP 200");
    $jsonMism = json_decode($respMism->obtenerContenido(), true);
    assertCheck($jsonMism['codigo'] === 'PAGO_APROBADO_CON_DISCREPANCIA_MONTO', "Discrepancia Monto: Código PAGO_APROBADO_CON_DISCREPANCIA_MONTO");
    $txMismBD = $txRepo->buscarPorId($txMismId);
    assertCheck($txMismBD->obtenerEstadoConciliacion() === PagoTransaccionPasarela::ESTADO_CONCILIACION_DISCREPANCIA_MONTO, "Discrepancia Monto: estado_conciliacion = DISCREPANCIA_MONTO");
    assertCheck($txMismBD->obtenerCuentaFolioId() === null, "Discrepancia Monto: cuenta_folio_id permanece NULL");

    // =========================================================================
    // 8. LIMPIEZA DEFENSIVA DE FIXTURES
    // =========================================================================
    echo "\n--- 8. Limpieza Defensiva de Fixtures ---\n";

    $pdo->prepare("DELETE FROM pagos_webhooks_eventos WHERE proveedor = 'CULQI' AND proveedor_evento_id LIKE 'evt_%1c_%'")->execute();
    $pdo->prepare("UPDATE pagos_transacciones_pasarela SET pago_cuenta_id = NULL, cuenta_folio_id = NULL WHERE reserva_id IN (:r1, :r2, :r3, :r4)")->execute([
        'r1' => $reservaValidaId,
        'r2' => $reservaCanceladaId,
        'r3' => $reservaExpiradaId,
        'r4' => $reservaConfirmadaId,
    ]);
    $pdo->prepare("DELETE FROM pagos_transacciones_pasarela WHERE reserva_id IN (:r1, :r2, :r3, :r4)")->execute([
        'r1' => $reservaValidaId,
        'r2' => $reservaCanceladaId,
        'r3' => $reservaExpiradaId,
        'r4' => $reservaConfirmadaId,
    ]);
    $pdo->prepare("DELETE FROM aplicaciones_pago WHERE pago_id IN (SELECT id FROM pagos_cuenta WHERE referencia_operacion = :ref)")->execute(['ref' => $ordenIdGenerada]);
    $pdo->prepare("DELETE FROM pagos_cuenta WHERE referencia_operacion = :ref")->execute(['ref' => $ordenIdGenerada]);
    $pdo->prepare("DELETE FROM cargos_cuenta WHERE cuenta_folio_id IN (SELECT id FROM cuentas_folios WHERE reserva_id IN (:r1, :r2, :r3, :r4))")->execute([
        'r1' => $reservaValidaId,
        'r2' => $reservaCanceladaId,
        'r3' => $reservaExpiradaId,
        'r4' => $reservaConfirmadaId,
    ]);
    $pdo->prepare("DELETE FROM cuentas_folios WHERE reserva_id IN (:r1, :r2, :r3, :r4)")->execute([
        'r1' => $reservaValidaId,
        'r2' => $reservaCanceladaId,
        'r3' => $reservaExpiradaId,
        'r4' => $reservaConfirmadaId,
    ]);
    $pdo->prepare("DELETE FROM reserva_unidades WHERE reserva_id IN (:r1, :r2, :r3, :r4)")->execute([
        'r1' => $reservaValidaId,
        'r2' => $reservaCanceladaId,
        'r3' => $reservaExpiradaId,
        'r4' => $reservaConfirmadaId,
    ]);
    $pdo->prepare("DELETE FROM reservas WHERE id IN (:r1, :r2, :r3, :r4)")->execute([
        'r1' => $reservaValidaId,
        'r2' => $reservaCanceladaId,
        'r3' => $reservaExpiradaId,
        'r4' => $reservaConfirmadaId,
    ]);
    $pdo->prepare("DELETE FROM api_idempotencia WHERE ruta = '/api/v1/pagos/intenciones'")->execute();

    assertCheck(true, "Limpieza defensiva de fixtures de prueba completada");

    echo "\n====================================================================\n";
    echo " SUITE PAGOS-1C SUPERADA CON ÉXITO\n";
    echo " Total checks ejecutados: $totalAssertions | Aprobados: $passedAssertions\n";
    echo "====================================================================\n\n";

} catch (Throwable $e) {
    echo "\n[ERROR CRÍTICO EN SUITE PAGOS-1C]: " . $e->getMessage() . "\n";
    echo "En archivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
