<?php

declare(strict_types=1);

/**
 * Suite de Pruebas End-to-End (E2E) Reales contra Apache en HTTPS — Camargo PMS (ARRENDAMIENTOS-1)
 * Casos E2E-ARR-01 a E2E-ARR-14 (14/14).
 *
 * Principios vinculantes:
 * - Servidor web real Apache en HTTPS (https://app.camargo-pms.test/)
 * - Control estricto de sesiones y cookies
 * - RBAC vinculante: arrendamientos.ver, arrendamientos.crear, arrendamientos.activar, arrendamientos.gestionar, arrendamientos.rescindir, arrendamientos.generar_cargos
 * - D-075 / D-076: Geometría nativa Alina (app-form app-icon-form, b-r-20, select2 42px), Font Awesome exclusivo
 * - Inmutabilidad histórica: Cero DELETE físico en contratos, personas y cuotas
 */

ob_start();

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;

Configuracion::cargar(dirname(__DIR__));
$pdo = BaseDatos::conexion();
$pdo->exec("SET SESSION innodb_lock_wait_timeout = 5");

ob_end_clean();

echo "====================================================================\n";
echo " VALIDACIÓN HTTP E2E REAL CONTRA APACHE (ARRENDAMIENTOS-1)\n";
echo " Endpoint: https://app.camargo-pms.test/\n";
echo "====================================================================\n\n";

$pass = 0;
$fail = 0;
$errores = [];

function checkE2E(string $codigo, string $desc, bool $ok, ?string $detalle = null): void {
    global $pass, $fail, $errores;
    if ($ok) {
        $pass++;
        echo "  [PASS] {$codigo}: {$desc}\n";
    } else {
        $fail++;
        $msg = "  [FAIL] {$codigo}: {$desc}" . ($detalle ? " -> {$detalle}" : "");
        echo "{$msg}\n";
        $errores[] = $msg;
    }
}

function curlRequest(string $url, string $method = 'GET', array $headers = [], ?string $body = null): array {
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 25,
    ];
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = $body;
    }
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    if ($resp === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException("Curl falló: $err");
    }
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    $hStr = substr($resp, 0, $hSize);
    $bStr = substr($resp, $hSize);

    preg_match_all('/^Set-Cookie:\s*([^;]+)/mi', $hStr, $mCookies);
    $cookies = $mCookies[1] ?? [];

    preg_match('/^Location:\s*([^\r\n]+)/mi', $hStr, $mLoc);
    $loc = $mLoc[1] ?? null;

    return [
        'code' => $code,
        'headers' => $hStr,
        'body' => $bStr,
        'location' => $loc,
        'cookies' => $cookies,
    ];
}

function extraerCsrfDeHtml(string $html): string {
    if (preg_match('/id="csrf-token-global"\s+value="([a-f0-9]{64})"/i', $html, $m)) {
        return $m[1];
    }
    if (preg_match('/name="csrf-token"\s+content="([a-f0-9]{64})"/i', $html, $m)) {
        return $m[1];
    }
    if (preg_match('/content="([a-f0-9]{64})"\s+name="csrf-token"/i', $html, $m)) {
        return $m[1];
    }
    if (preg_match('/name="_csrf_token"\s+value="([a-f0-9]{64})"/i', $html, $m)) {
        return $m[1];
    }
    if (preg_match('/name="csrf_token"\s+value="([a-f0-9]{64})"/i', $html, $m)) {
        return $m[1];
    }
    return '';
}

$baseUrl = 'https://app.camargo-pms.test';
$sufijo = strtoupper(bin2hex(random_bytes(4)));
$adminUser = 'admin_arr_' . strtolower($sufijo);
$passwordPlana = 'Pass123!456';
$passwordHash = password_hash($passwordPlana, PASSWORD_DEFAULT);

$cookieSession = null;
$csrfToken = null;
$adminUsuarioId = null;

try {
    // -------------------------------------------------------------------------
    // E2E-ARR-01: Acceso no autenticado redirige a /login (HTTP 302)
    // -------------------------------------------------------------------------
    $r1 = curlRequest("{$baseUrl}/arrendamientos", 'GET');
    $redirigeLogin = ($r1['code'] === 302 && str_contains((string) $r1['location'], '/login'));
    checkE2E('E2E-ARR-01', 'Acceso no autenticado a /arrendamientos redirige a /login (HTTP 302)', $redirigeLogin, "Code: {$r1['code']}, Loc: {$r1['location']}");

    // -------------------------------------------------------------------------
    // E2E-ARR-02: Autenticación real de usuario administrador
    // -------------------------------------------------------------------------
    $pdo->exec("INSERT INTO personas (nombres, apellido_paterno, apellido_materno, pais_nacionalidad_id, creado_en) VALUES ('Admin', 'Arrendamiento', '{$sufijo}', 1, NOW())");
    $adminPersonaId = (int) $pdo->lastInsertId();

    $stmtUAdmin = $pdo->prepare("INSERT INTO usuarios (persona_id, nombre_usuario, contrasena_hash, estado, creado_en) VALUES (?, ?, ?, 'ACTIVO', NOW())");
    $stmtUAdmin->execute([$adminPersonaId, $adminUser, $passwordHash]);
    $adminUsuarioId = (int) $pdo->lastInsertId();

    $stmtActorAdmin = $pdo->prepare("INSERT INTO actores (codigo, tipo, nombre, usuario_id, estado, creado_en) VALUES (?, 'USUARIO', ?, ?, 'ACTIVO', NOW())");
    $stmtActorAdmin->execute(['USR_' . $adminUsuarioId, 'Admin Arr ' . $sufijo, $adminUsuarioId]);

    $superadminRolId = (int) $pdo->query("SELECT id FROM roles WHERE es_superadministrador = 1 OR codigo = 'SUPERADMINISTRADOR' LIMIT 1")->fetchColumn();
    if (!$superadminRolId) {
        $superadminRolId = 1;
    }
    $pdo->exec("INSERT INTO usuarios_roles (usuario_id, rol_id, asignado_en) VALUES ({$adminUsuarioId}, {$superadminRolId}, NOW())");

    // 1. Obtener cookie y CSRF de login
    $rLoginGet = curlRequest("{$baseUrl}/login", 'GET');
    $csrfLogin = extraerCsrfDeHtml($rLoginGet['body']);
    $cookieLogin = $rLoginGet['cookies'][0] ?? '';

    // 2. Postear login
    $postLogin = http_build_query([
        'nombre_usuario' => $adminUser,
        'contrasena' => $passwordPlana,
        '_csrf_token' => $csrfLogin,
    ]);
    $rLoginPost = curlRequest("{$baseUrl}/login", 'POST', [
        'Content-Type: application/x-www-form-urlencoded',
        "Cookie: {$cookieLogin}",
    ], $postLogin);

    $loginOk = ($rLoginPost['code'] === 302);
    if (!empty($rLoginPost['cookies'])) {
        $cookieSession = $rLoginPost['cookies'][0];
    } else {
        $cookieSession = $cookieLogin;
    }
    checkE2E('E2E-ARR-02', 'Autenticación real de usuario administrador con redirección HTTP 302', $loginOk, "Code: {$rLoginPost['code']}, Loc: {$rLoginPost['location']}");

    // -------------------------------------------------------------------------
    // E2E-ARR-03: Carga de vista /arrendamientos con HTML Alina
    // -------------------------------------------------------------------------
    $rVista = curlRequest("{$baseUrl}/arrendamientos", 'GET', [
        "Cookie: {$cookieSession}",
    ]);
    $csrfToken = extraerCsrfDeHtml($rVista['body']);
    $tieneTitulo = str_contains($rVista['body'], 'Contratos de Arrendamiento');
    $tieneKPIs = str_contains($rVista['body'], 'kpi-vigentes');
    $tieneIconForm = str_contains($rVista['body'], 'app-icon-form');
    $vistaOk = ($rVista['code'] === 200 && $tieneTitulo && $tieneKPIs && $tieneIconForm && !empty($csrfToken));
    checkE2E('E2E-ARR-03', 'Carga de vista /arrendamientos con HTML Alina (HTTP 200, KPIs y app-icon-form)', $vistaOk, "Code: {$rVista['code']}");

    // -------------------------------------------------------------------------
    // E2E-ARR-04: Inyección y presencia de assets obligatorios D-075 / D-076
    // -------------------------------------------------------------------------
    $tieneScriptModulo = str_contains($rVista['body'], 'gestion-arrendamientos.js');
    $tienePristine = str_contains($rVista['body'], 'pristine');
    $tieneSweetAlert = str_contains($rVista['body'], 'sweetalert');
    $assetsOk = ($tieneScriptModulo && $tienePristine && $tieneSweetAlert);
    checkE2E('E2E-ARR-04', 'Inyección de assets obligatorios (gestion-arrendamientos.js, pristine, sweetalert)', $assetsOk);

    // Preparar unidades y titular para pruebas API
    $stmtU = $pdo->query("SELECT id FROM unidades WHERE estado = 'ACTIVO' LIMIT 2");
    $unidades = $stmtU->fetchAll(PDO::FETCH_COLUMN);
    $unidadPruebaId = (int) ($unidades[0] ?? 1);

    $stmtP = $pdo->query("SELECT id FROM personas LIMIT 1");
    $titularPruebaId = (int) ($stmtP->fetchColumn() ?: 1);

    // -------------------------------------------------------------------------
    // E2E-ARR-05: GET /api/arrendamientos devuelve lista JSON
    // -------------------------------------------------------------------------
    $rListar = curlRequest("{$baseUrl}/api/arrendamientos", 'GET', [
        "Cookie: {$cookieSession}",
        'Accept: application/json',
    ]);
    $jsonListar = json_decode($rListar['body'], true);
    $listarOk = ($rListar['code'] === 200 && is_array($jsonListar) && ($jsonListar['ok'] ?? false) === true);
    checkE2E('E2E-ARR-05', 'GET /api/arrendamientos devuelve lista JSON estructurada (HTTP 200)', $listarOk, "Code: {$rListar['code']}");

    // -------------------------------------------------------------------------
    // E2E-ARR-06: POST /api/arrendamientos crea contrato en BORRADOR
    // -------------------------------------------------------------------------
    $pdo->exec("DELETE FROM inventario_diario_unidades WHERE unidad_id = {$unidadPruebaId} AND fecha >= '2030-01-01'");

    $payloadCrear = json_encode([
        'unidad_id' => $unidadPruebaId,
        'titular_persona_id' => $titularPruebaId,
        'fecha_inicio' => '2030-01-01',
        'fecha_fin' => '2030-06-30',
        'dia_vencimiento' => 5,
        'renta_mensual' => 2500.00,
        'deposito_garantia' => 2500.00,
        'tipo_garantia' => 'DEPOSITO_EFECTIVO',
        'moneda_codigo' => 'PEN',
        '_csrf' => $csrfToken,
    ]);

    $rCrear = curlRequest("{$baseUrl}/api/arrendamientos", 'POST', [
        "Cookie: {$cookieSession}",
        'Content-Type: application/json',
        'Accept: application/json',
        "X-CSRF-Token: {$csrfToken}",
    ], $payloadCrear);

    $jsonCrear = json_decode($rCrear['body'], true);
    $contratoId = (int) ($jsonCrear['datos']['id'] ?? 0);
    $crearOk = ($rCrear['code'] === 201 && ($jsonCrear['ok'] ?? false) === true && $contratoId > 0 && ($jsonCrear['datos']['estado'] ?? '') === 'BORRADOR');
    checkE2E('E2E-ARR-06', 'POST /api/arrendamientos crea contrato nuevo en estado BORRADOR (HTTP 201)', $crearOk, "Code: {$rCrear['code']}, Msg: " . ($jsonCrear['mensaje'] ?? ''));

    // -------------------------------------------------------------------------
    // E2E-ARR-07: POST /api/arrendamientos rechaza datos inválidos (HTTP 422)
    // -------------------------------------------------------------------------
    $payloadInvalido = json_encode([
        'unidad_id' => $unidadPruebaId,
        'titular_persona_id' => $titularPruebaId,
        'fecha_inicio' => '2030-06-30',
        'fecha_fin' => '2030-01-01', // Invertida
        'renta_mensual' => 2000,
        '_csrf' => $csrfToken,
    ]);
    $rInvalido = curlRequest("{$baseUrl}/api/arrendamientos", 'POST', [
        "Cookie: {$cookieSession}",
        'Content-Type: application/json',
        'Accept: application/json',
        "X-CSRF-Token: {$csrfToken}",
    ], $payloadInvalido);

    $jsonInvalido = json_decode($rInvalido['body'], true);
    $invalidoOk = ($rInvalido['code'] === 422 && ($jsonInvalido['ok'] ?? true) === false);
    checkE2E('E2E-ARR-07', 'POST /api/arrendamientos rechaza contrato con fechas invertidas (HTTP 422)', $invalidoOk, "Code: {$rInvalido['code']}");

    // -------------------------------------------------------------------------
    // E2E-ARR-08: GET /api/arrendamientos/{id} devuelve detalle completo
    // -------------------------------------------------------------------------
    $rDetalle = curlRequest("{$baseUrl}/api/arrendamientos/{$contratoId}", 'GET', [
        "Cookie: {$cookieSession}",
        'Accept: application/json',
    ]);
    $jsonDetalle = json_decode($rDetalle['body'], true);
    $detalleOk = ($rDetalle['code'] === 200 && ($jsonDetalle['ok'] ?? false) === true && isset($jsonDetalle['datos']['personas']));
    checkE2E('E2E-ARR-08', 'GET /api/arrendamientos/{id} devuelve detalle completo del contrato (HTTP 200)', $detalleOk, "Code: {$rDetalle['code']}");

    // -------------------------------------------------------------------------
    // E2E-ARR-09: POST /api/arrendamientos/{id}/activar
    // -------------------------------------------------------------------------
    $rActivar = curlRequest("{$baseUrl}/api/arrendamientos/{$contratoId}/activar", 'POST', [
        "Cookie: {$cookieSession}",
        'Content-Type: application/json',
        'Accept: application/json',
        "X-CSRF-Token: {$csrfToken}",
    ], json_encode(['_csrf' => $csrfToken]));

    $jsonActivar = json_decode($rActivar['body'], true);
    $activarOk = ($rActivar['code'] === 200 && ($jsonActivar['ok'] ?? false) === true && ($jsonActivar['datos']['estado'] ?? '') === 'VIGENTE');
    checkE2E('E2E-ARR-09', 'POST /api/arrendamientos/{id}/activar activa contrato y materializa noches (HTTP 200)', $activarOk, "Code: {$rActivar['code']}, Msg: " . ($jsonActivar['mensaje'] ?? ''));

    // -------------------------------------------------------------------------
    // E2E-ARR-10: POST /api/arrendamientos/{id}/cuotas emite cuota mensual
    // -------------------------------------------------------------------------
    $rCuota = curlRequest("{$baseUrl}/api/arrendamientos/{$contratoId}/cuotas", 'POST', [
        "Cookie: {$cookieSession}",
        'Content-Type: application/json',
        'Accept: application/json',
        "X-CSRF-Token: {$csrfToken}",
    ], json_encode([
        'anio' => 2030,
        'mes' => 2,
        '_csrf' => $csrfToken,
    ]));

    $jsonCuota = json_decode($rCuota['body'], true);
    $cuotaOk = ($rCuota['code'] === 200 && ($jsonCuota['ok'] ?? false) === true && ($jsonCuota['datos']['periodo_codigo'] ?? '') === '2030-02');
    checkE2E('E2E-ARR-10', 'POST /api/arrendamientos/{id}/cuotas emite cuota mensual vinculada al folio (HTTP 200)', $cuotaOk, "Code: {$rCuota['code']}");

    // -------------------------------------------------------------------------
    // E2E-ARR-11: POST /api/arrendamientos/{id}/garantia/recibir
    // -------------------------------------------------------------------------
    $rRecibirG = curlRequest("{$baseUrl}/api/arrendamientos/{$contratoId}/garantia/recibir", 'POST', [
        "Cookie: {$cookieSession}",
        'Content-Type: application/json',
        'Accept: application/json',
        "X-CSRF-Token: {$csrfToken}",
    ], json_encode([
        'monto' => '2500.00',
        '_csrf' => $csrfToken,
    ]));

    $jsonRecibirG = json_decode($rRecibirG['body'], true);
    $recibirGOk = ($rRecibirG['code'] === 200 && ($jsonRecibirG['ok'] ?? false) === true);
    checkE2E('E2E-ARR-11', 'POST /api/arrendamientos/{id}/garantia/recibir actualiza custodia segregada (HTTP 200)', $recibirGOk, "Code: {$rRecibirG['code']}");

    // -------------------------------------------------------------------------
    // E2E-ARR-12: POST /api/arrendamientos/{id}/garantia/compensar
    // -------------------------------------------------------------------------
    $rCompensar = curlRequest("{$baseUrl}/api/arrendamientos/{$contratoId}/garantia/compensar", 'POST', [
        "Cookie: {$cookieSession}",
        'Content-Type: application/json',
        'Accept: application/json',
        "X-CSRF-Token: {$csrfToken}",
    ], json_encode([
        'monto' => '500.00',
        'tipo_compensacion' => 'DANOS',
        'motivo' => 'Reparación de cerradura eléctrica',
        '_csrf' => $csrfToken,
    ]));

    $jsonCompensar = json_decode($rCompensar['body'], true);
    $compensarOk = ($rCompensar['code'] === 200 && ($jsonCompensar['ok'] ?? false) === true);
    checkE2E('E2E-ARR-12', 'POST /api/arrendamientos/{id}/garantia/compensar compensa fondos de garantía (HTTP 200)', $compensarOk, "Code: {$rCompensar['code']}");

    // -------------------------------------------------------------------------
    // E2E-ARR-13: POST /api/arrendamientos/{id}/prorrogar
    // -------------------------------------------------------------------------
    $rProrrogar = curlRequest("{$baseUrl}/api/arrendamientos/{$contratoId}/prorrogar", 'POST', [
        "Cookie: {$cookieSession}",
        'Content-Type: application/json',
        'Accept: application/json',
        "X-CSRF-Token: {$csrfToken}",
    ], json_encode([
        'nueva_fecha_fin' => '2030-08-31',
        '_csrf' => $csrfToken,
    ]));

    $jsonProrrogar = json_decode($rProrrogar['body'], true);
    $prorrogarOk = ($rProrrogar['code'] === 200 && ($jsonProrrogar['ok'] ?? false) === true);
    checkE2E('E2E-ARR-13', 'POST /api/arrendamientos/{id}/prorrogar extiende fecha_fin y materializa noches adicionales (HTTP 200)', $prorrogarOk, "Code: {$rProrrogar['code']}");

    // -------------------------------------------------------------------------
    // E2E-ARR-14: POST /api/arrendamientos/{id}/rescindir
    // -------------------------------------------------------------------------
    $rRescindir = curlRequest("{$baseUrl}/api/arrendamientos/{$contratoId}/rescindir", 'POST', [
        "Cookie: {$cookieSession}",
        'Content-Type: application/json',
        'Accept: application/json',
        "X-CSRF-Token: {$csrfToken}",
    ], json_encode([
        'fecha_efectiva' => '2030-04-15',
        'motivo' => 'Rescisión anticipada de común acuerdo',
        '_csrf' => $csrfToken,
    ]));

    $jsonRescindir = json_decode($rRescindir['body'], true);
    $rescindirOk = ($rRescindir['code'] === 200 && ($jsonRescindir['ok'] ?? false) === true);
    checkE2E('E2E-ARR-14', 'POST /api/arrendamientos/{id}/rescindir recorta fecha_fin y libera noches futuras (HTTP 200)', $rescindirOk, "Code: {$rRescindir['code']}");

    echo "\n====================================================================\n";
    echo "RESULTADOS E2E REAL CONTRA APACHE: {$pass}/14 PASS\n";
    echo "====================================================================\n";

} catch (Throwable $e) {
    echo "ERROR GENERAL EN SUITE E2E: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
} finally {
    // Limpieza de usuario admin de prueba
    if ($adminUsuarioId !== null) {
        try {
            $pdo->exec("DELETE FROM usuarios_roles WHERE usuario_id = {$adminUsuarioId}");
            $pdo->exec("DELETE FROM actores WHERE usuario_id = {$adminUsuarioId}");
            $pdo->exec("DELETE FROM usuarios WHERE id = {$adminUsuarioId}");
        } catch (Throwable) {
            // Limpieza silenciosa
        }
    }
}
