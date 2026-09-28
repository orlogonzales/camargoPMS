<?php

declare(strict_types=1);

/**
 * Suite de Pruebas End-to-End (E2E) Reales contra Apache en HTTPS — TAPE-CHART-1 (D-084)
 * Casos E2E-TC-01 a E2E-TC-14 (14/14).
 *
 * Principios vinculantes:
 * - Servidor web real Apache en HTTPS (https://app.camargo-pms.test/)
 * - Control estricto de sesiones, cookies y RBAC de disponibilidad.ver
 * - D-075 / D-076: Geometría nativa Alina (app-wrapper, tape-chart-container, tabs)
 * - TAPE CHART != FUENTE DE VERDAD (solo lectura agregada)
 * - O(1) en consultas y tiempo sub-500ms
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
echo " VALIDACIÓN HTTP E2E REAL CONTRA APACHE (TAPE-CHART-1 / D-084)\n";
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
        CURLOPT_TIMEOUT => 30,
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
    $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
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
        'total_time' => $totalTime,
    ];
}

function extraerCsrfDeHtml(string $html): string {
    if (preg_match('/id="csrf-token-global"\s+value="([a-f0-9]{64})"/i', $html, $m)) {
        return $m[1];
    }
    if (preg_match('/name="csrf-token"\s+content="([a-f0-9]{64})"/i', $html, $m)) {
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
$adminUser = 'admin_tc_' . strtolower($sufijo);
$sinPermisoUser = 'sin_tc_' . strtolower($sufijo);
$passwordPlana = 'PassTapeChart123!456';
$passwordHash = password_hash($passwordPlana, PASSWORD_DEFAULT);

$cookieSessionAdmin = null;
$cookieSessionSin = null;

try {
    // -------------------------------------------------------------------------
    // E2E-TC-01: Acceso no autenticado a /tape-chart redirige a /login (HTTP 302)
    // -------------------------------------------------------------------------
    $r1 = curlRequest("{$baseUrl}/tape-chart", 'GET');
    $redirigeLogin = ($r1['code'] === 302 && str_contains((string) $r1['location'], '/login'));
    checkE2E('E2E-TC-01', 'Acceso no autenticado a /tape-chart redirige a /login (HTTP 302)', $redirigeLogin, "Code: {$r1['code']}, Loc: {$r1['location']}");

    // -------------------------------------------------------------------------
    // Crear usuarios de prueba (con y sin permisos)
    // -------------------------------------------------------------------------
    // 1. Usuario sin permiso
    $pdo->exec("INSERT INTO personas (nombres, apellido_paterno, apellido_materno, pais_nacionalidad_id, creado_en) VALUES ('SinPerm', 'TapeChart', '{$sufijo}', 1, NOW())");
    $sinPersonaId = (int) $pdo->lastInsertId();

    $stmtUSin = $pdo->prepare("INSERT INTO usuarios (persona_id, nombre_usuario, contrasena_hash, estado, creado_en) VALUES (?, ?, ?, 'ACTIVO', NOW())");
    $stmtUSin->execute([$sinPersonaId, $sinPermisoUser, $passwordHash]);
    $sinPermisoUsuarioId = (int) $pdo->lastInsertId();

    $stmtActorSin = $pdo->prepare("INSERT INTO actores (codigo, tipo, nombre, usuario_id, estado, creado_en) VALUES (?, 'USUARIO', ?, ?, 'ACTIVO', NOW())");
    $stmtActorSin->execute(['USR_' . $sinPermisoUsuarioId, 'Sin Permiso TC ' . $sufijo, $sinPermisoUsuarioId]);

    // Login HTTP sin permiso
    $rLoginSinGet = curlRequest("{$baseUrl}/login", 'GET');
    $csrfLoginSin = extraerCsrfDeHtml($rLoginSinGet['body']);
    $cookieLoginSin = $rLoginSinGet['cookies'][0] ?? '';

    $bodyLoginSin = http_build_query([
        'nombre_usuario' => $sinPermisoUser,
        'contrasena' => $passwordPlana,
        '_csrf_token' => $csrfLoginSin,
    ]);
    $rLoginSinPost = curlRequest("{$baseUrl}/login", 'POST', [
        'Content-Type: application/x-www-form-urlencoded',
        "Cookie: {$cookieLoginSin}",
    ], $bodyLoginSin);

    $cookieSessionSin = !empty($rLoginSinPost['cookies']) ? implode('; ', $rLoginSinPost['cookies']) : $cookieLoginSin;

    // -------------------------------------------------------------------------
    // E2E-TC-02: Usuario sin permiso disponibilidad.ver recibe HTTP 403 Forbidden
    // -------------------------------------------------------------------------
    $r2 = curlRequest("{$baseUrl}/tape-chart", 'GET', [
        "Cookie: {$cookieSessionSin}",
    ]);
    $es403 = ($r2['code'] === 403);
    checkE2E('E2E-TC-02', 'Usuario autenticado sin permiso disponibilidad.ver recibe HTTP 403 Forbidden', $es403, "Code: {$r2['code']}");

    // 2. Usuario Superadmin con todos los permisos
    $pdo->exec("INSERT INTO personas (nombres, apellido_paterno, apellido_materno, pais_nacionalidad_id, creado_en) VALUES ('Admin', 'TapeChart', '{$sufijo}', 1, NOW())");
    $adminPersonaId = (int) $pdo->lastInsertId();

    $stmtUAdmin = $pdo->prepare("INSERT INTO usuarios (persona_id, nombre_usuario, contrasena_hash, estado, creado_en) VALUES (?, ?, ?, 'ACTIVO', NOW())");
    $stmtUAdmin->execute([$adminPersonaId, $adminUser, $passwordHash]);
    $adminUsuarioId = (int) $pdo->lastInsertId();

    $stmtActorAdmin = $pdo->prepare("INSERT INTO actores (codigo, tipo, nombre, usuario_id, estado, creado_en) VALUES (?, 'USUARIO', ?, ?, 'ACTIVO', NOW())");
    $stmtActorAdmin->execute(['USR_' . $adminUsuarioId, 'Admin TC ' . $sufijo, $adminUsuarioId]);

    $superadminRolId = (int) $pdo->query("SELECT id FROM roles WHERE es_superadministrador = 1 OR codigo = 'SUPERADMINISTRADOR' LIMIT 1")->fetchColumn() ?: 1;
    $stmtRol = $pdo->prepare("INSERT INTO usuarios_roles (usuario_id, rol_id, asignado_por_usuario_id, asignado_en) VALUES (?, ?, NULL, NOW())");
    $stmtRol->execute([$adminUsuarioId, $superadminRolId]);

    // Login HTTP Admin
    $rLoginAdminGet = curlRequest("{$baseUrl}/login", 'GET');
    $csrfLoginAdmin = extraerCsrfDeHtml($rLoginAdminGet['body']);
    $cookieLoginAdmin = $rLoginAdminGet['cookies'][0] ?? '';

    $bodyLoginAdmin = http_build_query([
        'nombre_usuario' => $adminUser,
        'contrasena' => $passwordPlana,
        '_csrf_token' => $csrfLoginAdmin,
    ]);
    $rLoginAdminPost = curlRequest("{$baseUrl}/login", 'POST', [
        'Content-Type: application/x-www-form-urlencoded',
        "Cookie: {$cookieLoginAdmin}",
    ], $bodyLoginAdmin);

    $cookieSessionAdmin = !empty($rLoginAdminPost['cookies']) ? implode('; ', $rLoginAdminPost['cookies']) : $cookieLoginAdmin;

    // -------------------------------------------------------------------------
    // E2E-TC-03: Superadmin accede a /tape-chart con HTTP 200 OK
    // -------------------------------------------------------------------------
    $r3 = curlRequest("{$baseUrl}/tape-chart", 'GET', [
        "Cookie: {$cookieSessionAdmin}",
    ]);
    $es200Html = ($r3['code'] === 200 && str_contains($r3['body'], '<!DOCTYPE html>'));
    checkE2E('E2E-TC-03', 'Usuario con disponibilidad.ver accede exitosamente a /tape-chart (HTTP 200 HTML)', $es200Html, "Code: {$r3['code']}");

    // -------------------------------------------------------------------------
    // E2E-TC-04: Verificación visual de estructura Alina
    // -------------------------------------------------------------------------
    $tieneEstructuraAlina = str_contains($r3['body'], 'app-wrapper') &&
                            str_contains($r3['body'], 'tape-chart-container') &&
                            str_contains($r3['body'], 'Centro Operacional de Recepción');
    checkE2E('E2E-TC-04', 'HTML contiene layout nativo Alina (app-wrapper, tape-chart-container, título oficial)', $tieneEstructuraAlina);

    // -------------------------------------------------------------------------
    // E2E-TC-05: Inclusión de assets requeridos (camargo.css, tape-chart.js)
    // -------------------------------------------------------------------------
    $tieneAssets = str_contains($r3['body'], 'camargo.css') &&
                   str_contains($r3['body'], 'tape-chart.js');
    checkE2E('E2E-TC-05', 'HTML incluye assets vinculados obligatorios (camargo.css y tape-chart.js)', $tieneAssets);

    // -------------------------------------------------------------------------
    // E2E-TC-06: Pestañas operacionales duales (Tape Chart vs Rack de Hoy)
    // -------------------------------------------------------------------------
    $tienePestanas = str_contains($r3['body'], 'tab-calendario') &&
                     str_contains($r3['body'], 'tab-rack-hoy');
    checkE2E('E2E-TC-06', 'HTML contiene selectores de doble pestaña (Tablero Tape Chart y Rack Hotelero)', $tienePestanas);

    // -------------------------------------------------------------------------
    // E2E-TC-07: Endpoint API sin autenticación /tape-chart/datos es denegado
    // -------------------------------------------------------------------------
    $r7 = curlRequest("{$baseUrl}/tape-chart/datos", 'GET');
    $denegado7 = ($r7['code'] === 403 || $r7['code'] === 302 || $r7['code'] === 401);
    checkE2E('E2E-TC-07', 'Acceso no autenticado a API /tape-chart/datos es rechazado de forma segura', $denegado7, "Code: {$r7['code']}");

    // -------------------------------------------------------------------------
    // E2E-TC-08: API /tape-chart/datos autenticado retorna HTTP 200 con JSON
    // -------------------------------------------------------------------------
    $dtHoyLima = new DateTimeImmutable('now', new DateTimeZone('America/Lima'));
    $fDesde = $dtHoyLima->format('Y-m-d');
    $fHasta = $dtHoyLima->modify('+14 days')->format('Y-m-d');

    $r8 = curlRequest("{$baseUrl}/tape-chart/datos?fecha_desde={$fDesde}&fecha_hasta={$fHasta}", 'GET', [
        "Cookie: {$cookieSessionAdmin}",
    ]);
    $json8 = json_decode($r8['body'], true);
    $es200Json = ($r8['code'] === 200 && is_array($json8) && ($json8['ok'] ?? false) === true);
    checkE2E('E2E-TC-08', 'API GET /tape-chart/datos con parámetros válidos retorna HTTP 200 OK y JSON ok: true', $es200Json, "Code: {$r8['code']}");

    // -------------------------------------------------------------------------
    // E2E-TC-09: Estructura del JSON de proyección
    // -------------------------------------------------------------------------
    $tieneEstructuraProy = isset(
        $json8['datos']['propiedad'],
        $json8['datos']['horizonte'],
        $json8['datos']['columnas_fechas'],
        $json8['datos']['filas_unidades'],
        $json8['datos']['kpis_hoy']
    );
    checkE2E('E2E-TC-09', 'JSON de proyección contiene todos los nodos requeridos (propiedad, horizonte, columnas, unidades, kpis)', $tieneEstructuraProy);

    // -------------------------------------------------------------------------
    // E2E-TC-10: Validación de intervalo invertido (HTTP 422)
    // -------------------------------------------------------------------------
    $r10 = curlRequest("{$baseUrl}/tape-chart/datos?fecha_desde={$fHasta}&fecha_hasta={$fDesde}", 'GET', [
        "Cookie: {$cookieSessionAdmin}",
    ]);
    $json10 = json_decode($r10['body'], true);
    $es422Intervalo = ($r10['code'] === 422 && ($json10['codigo_error'] ?? '') === 'PARAMETRO_INVALIDO');
    checkE2E('E2E-TC-10', 'API rechaza intervalo invertido con HTTP 422 y código PARAMETRO_INVALIDO', $es422Intervalo, "Code: {$r10['code']}");

    // -------------------------------------------------------------------------
    // E2E-TC-11: Validación de horizonte máximo > 60 días (HTTP 422)
    // -------------------------------------------------------------------------
    $f70d = $dtHoyLima->modify('+70 days')->format('Y-m-d');
    $r11 = curlRequest("{$baseUrl}/tape-chart/datos?fecha_desde={$fDesde}&fecha_hasta={$f70d}", 'GET', [
        "Cookie: {$cookieSessionAdmin}",
    ]);
    $json11 = json_decode($r11['body'], true);
    $es422MaxDias = ($r11['code'] === 422 && ($json11['codigo_error'] ?? '') === 'PARAMETRO_INVALIDO');
    checkE2E('E2E-TC-11', 'API rechaza horizonte mayor a 60 días con HTTP 422 y código PARAMETRO_INVALIDO', $es422MaxDias, "Code: {$r11['code']}");

    // -------------------------------------------------------------------------
    // E2E-TC-12: Validación de propiedad inexistente (HTTP 422)
    // -------------------------------------------------------------------------
    $r12 = curlRequest("{$baseUrl}/tape-chart/datos?propiedad_id=999999&fecha_desde={$fDesde}&fecha_hasta={$fHasta}", 'GET', [
        "Cookie: {$cookieSessionAdmin}",
    ]);
    $json12 = json_decode($r12['body'], true);
    $es422PropInvalida = ($r12['code'] === 422 && ($json12['ok'] ?? true) === false);
    checkE2E('E2E-TC-12', 'API rechaza propiedad inexistente con HTTP 422 y mensaje explicativo', $es422PropInvalida, "Code: {$r12['code']}");

    // -------------------------------------------------------------------------
    // E2E-TC-13: API /tape-chart/rack-hoy autenticado retorna HTTP 200 con datos de rack
    // -------------------------------------------------------------------------
    $r13 = curlRequest("{$baseUrl}/tape-chart/rack-hoy", 'GET', [
        "Cookie: {$cookieSessionAdmin}",
    ]);
    $json13 = json_decode($r13['body'], true);
    $es200Rack = ($r13['code'] === 200 && is_array($json13) && ($json13['ok'] ?? false) === true && isset($json13['datos']['kpis_hoy']));
    checkE2E('E2E-TC-13', 'API GET /tape-chart/rack-hoy retorna HTTP 200 OK con KPIs y habitaciones del día', $es200Rack, "Code: {$r13['code']}");

    // -------------------------------------------------------------------------
    // E2E-TC-14: Rendimiento real en Apache HTTPS sub-500ms
    // -------------------------------------------------------------------------
    $tiempoS = $r8['total_time'];
    $tiempoMs = $tiempoS * 1000;
    $esSub500 = ($tiempoMs < 500.0);
    checkE2E('E2E-TC-14', sprintf('Tiempo de respuesta HTTP real en Apache HTTPS es sub-500ms (%.2f ms)', $tiempoMs), $esSub500);

} catch (Throwable $e) {
    $fail++;
    $msg = "Excepción durante pruebas E2E: " . $e->getMessage();
    echo "  [FAIL] {$msg}\n";
    $errores[] = $msg;
} finally {
    // Limpieza de usuarios temporales
    try {
        if (!empty($adminUsuarioId)) {
            $pdo->exec("DELETE FROM usuarios_roles WHERE usuario_id = {$adminUsuarioId}");
            $pdo->exec("DELETE FROM actores WHERE usuario_id = {$adminUsuarioId}");
            $pdo->exec("DELETE FROM usuarios WHERE id = {$adminUsuarioId}");
            $pdo->exec("DELETE FROM personas WHERE id = {$adminPersonaId}");
        }
        if (!empty($sinPermisoUsuarioId)) {
            $pdo->exec("DELETE FROM actores WHERE usuario_id = {$sinPermisoUsuarioId}");
            $pdo->exec("DELETE FROM usuarios WHERE id = {$sinPermisoUsuarioId}");
            $pdo->exec("DELETE FROM personas WHERE id = {$sinPersonaId}");
        }
    } catch (Throwable) {
        // Ignorar en cleanup
    }
}

echo "\n====================================================================\n";
echo "RESULTADOS E2E TAPE-CHART-1: {$pass} / 14 PASS\n";
if ($fail > 0) {
    echo "FALLIDAS: {$fail}\n";
    foreach ($errores as $err) {
        echo "  - {$err}\n";
    }
    exit(1);
}
echo "====================================================================\n";
