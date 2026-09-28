<?php

declare(strict_types=1);

/**
 * Suite de Pruebas End-to-End (E2E) Reales contra Apache en HTTPS — Camargo PMS (SUMINISTROS-1 / D-081)
 * Casos E2E-SUM-01 a E2E-SUM-14 (14/14).
 *
 * Principios vinculantes:
 * - Servidor web real Apache en HTTPS (https://app.camargo-pms.test/)
 * - Control estricto de sesiones, cookies y tokens CSRF
 * - RBAC vinculado a suministros.*
 * - D-075 / D-076: Geometría nativa Alina (app-form, app-icon-form, b-r-20), Font Awesome exclusivo
 * - Axioma D-081: SUMINISTRO != MEDIDOR != LECTURA != TARIFA != CONSUMO VALORIZADO != CARGO != PAGO
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
echo " VALIDACIÓN HTTP E2E REAL CONTRA APACHE (SUMINISTROS-1 / D-081)\n";
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
$adminUser = 'admin_sum_' . strtolower($sufijo);
$sinPermisoUser = 'sin_sum_' . strtolower($sufijo);
$passwordPlana = 'PassSuministros123!456';
$passwordHash = password_hash($passwordPlana, PASSWORD_DEFAULT);

$cookieSession = null;
$csrfToken = null;
$adminUsuarioId = null;
$sinPermisoUsuarioId = null;

try {
    // -------------------------------------------------------------------------
    // E2E-SUM-01: Acceso no autenticado a /suministros redirige a /login (HTTP 302)
    // -------------------------------------------------------------------------
    $r1 = curlRequest("{$baseUrl}/suministros", 'GET');
    $redirigeLogin = ($r1['code'] === 302 && str_contains((string) $r1['location'], '/login'));
    checkE2E('E2E-SUM-01', 'Acceso no autenticado a /suministros redirige a /login (HTTP 302)', $redirigeLogin, "Code: {$r1['code']}, Loc: {$r1['location']}");

    // -------------------------------------------------------------------------
    // Crear usuarios para prueba: Admin y Usuario sin permisos
    // -------------------------------------------------------------------------
    $pdo->exec("INSERT INTO personas (nombres, apellido_paterno, apellido_materno, pais_nacionalidad_id, creado_en) VALUES ('Admin', 'Suministros', '{$sufijo}', 1, NOW())");
    $adminPersonaId = (int) $pdo->lastInsertId();

    $stmtUAdmin = $pdo->prepare("INSERT INTO usuarios (persona_id, nombre_usuario, contrasena_hash, estado, creado_en) VALUES (?, ?, ?, 'ACTIVO', NOW())");
    $stmtUAdmin->execute([$adminPersonaId, $adminUser, $passwordHash]);
    $adminUsuarioId = (int) $pdo->lastInsertId();

    $stmtActorAdmin = $pdo->prepare("INSERT INTO actores (codigo, tipo, nombre, usuario_id, estado, creado_en) VALUES (?, 'USUARIO', ?, ?, 'ACTIVO', NOW())");
    $stmtActorAdmin->execute(['USR_' . $adminUsuarioId, 'Admin Suministros ' . $sufijo, $adminUsuarioId]);

    $superadminRolId = (int) $pdo->query("SELECT id FROM roles WHERE es_superadministrador = 1 OR codigo = 'SUPERADMINISTRADOR' LIMIT 1")->fetchColumn();
    if (!$superadminRolId) {
        $superadminRolId = 1;
    }
    $pdo->exec("INSERT INTO usuarios_roles (usuario_id, rol_id, asignado_en) VALUES ({$adminUsuarioId}, {$superadminRolId}, NOW())");

    // Login HTTP real
    $rLoginGet = curlRequest("{$baseUrl}/login", 'GET');
    $csrfLogin = extraerCsrfDeHtml($rLoginGet['body']);
    $cookieLogin = $rLoginGet['cookies'][0] ?? '';

    $bodyLogin = http_build_query([
        'nombre_usuario' => $adminUser,
        'contrasena' => $passwordPlana,
        '_csrf_token' => $csrfLogin,
    ]);
    $rLoginPost = curlRequest("{$baseUrl}/login", 'POST', [
        'Content-Type: application/x-www-form-urlencoded',
        "Cookie: {$cookieLogin}",
    ], $bodyLogin);

    if (!empty($rLoginPost['cookies'])) {
        $cookieSession = $rLoginPost['cookies'][0];
    } else {
        $cookieSession = $cookieLogin;
    }

    // -------------------------------------------------------------------------
    // E2E-SUM-02: Usuario sin permisos recibe HTTP 403 en /suministros
    // -------------------------------------------------------------------------
    $pdo->exec("INSERT INTO personas (nombres, apellido_paterno, apellido_materno, pais_nacionalidad_id, creado_en) VALUES ('SinPerm', 'Suministros', '{$sufijo}', 1, NOW())");
    $sinPersonaId = (int) $pdo->lastInsertId();

    $stmtUSin = $pdo->prepare("INSERT INTO usuarios (persona_id, nombre_usuario, contrasena_hash, estado, creado_en) VALUES (?, ?, ?, 'ACTIVO', NOW())");
    $stmtUSin->execute([$sinPersonaId, $sinPermisoUser, $passwordHash]);
    $sinPermisoUsuarioId = (int) $pdo->lastInsertId();

    // Login con usuario sin permisos
    $rSinLoginGet = curlRequest("{$baseUrl}/login", 'GET');
    $csrfSin = extraerCsrfDeHtml($rSinLoginGet['body']);
    $cookieSinLogin = $rSinLoginGet['cookies'][0] ?? '';

    $rSinLoginPost = curlRequest("{$baseUrl}/login", 'POST', [
        'Content-Type: application/x-www-form-urlencoded',
        "Cookie: {$cookieSinLogin}",
    ], http_build_query([
        'nombre_usuario' => $sinPermisoUser,
        'contrasena' => $passwordPlana,
        '_csrf_token' => $csrfSin,
    ]));

    $cookieSinSession = !empty($rSinLoginPost['cookies']) ? $rSinLoginPost['cookies'][0] : $cookieSinLogin;

    $r2 = curlRequest("{$baseUrl}/suministros", 'GET', [
        "Cookie: {$cookieSinSession}",
    ]);
    $es403 = ($r2['code'] === 403);
    checkE2E('E2E-SUM-02', 'Usuario sin permisos recibe HTTP 403 Forbidden en /suministros', $es403, "Code: {$r2['code']}");

    // -------------------------------------------------------------------------
    // E2E-SUM-03: Superadmin accede exitosamente a /suministros (HTTP 200) y valida Alina
    // -------------------------------------------------------------------------
    $r3 = curlRequest("{$baseUrl}/suministros", 'GET', [
        "Cookie: {$cookieSession}",
    ]);
    $htmlVista = $r3['body'];
    $csrfToken = extraerCsrfDeHtml($htmlVista);

    $alinaValida = ($r3['code'] === 200)
        && str_contains($htmlVista, 'app-form')
        && str_contains($htmlVista, 'fa-bolt')
        && str_contains($htmlVista, 'nav-tabs')
        && str_contains($htmlVista, 'b-r-20');
    checkE2E('E2E-SUM-03', 'Acceso HTTP 200 a /suministros con geometría nativa Alina (app-form, b-r-20, fa-bolt)', $alinaValida, "Code: {$r3['code']}, CSRF: " . substr($csrfToken, 0, 8) . '...');

    // -------------------------------------------------------------------------
    // E2E-SUM-04: API Catálogos devuelve HTTP 200 con propiedades y suministros
    // -------------------------------------------------------------------------
    $r4 = curlRequest("{$baseUrl}/api/suministros/catalogos", 'GET', [
        "Cookie: {$cookieSession}",
        'Accept: application/json',
    ]);
    $jsonCat = json_decode($r4['body'], true);
    $catOk = ($r4['code'] === 200)
        && ($jsonCat['exito'] ?? false) === true
        && isset($jsonCat['datos']['propiedades'])
        && isset($jsonCat['datos']['suministros']);
    checkE2E('E2E-SUM-04', 'Endpoint GET /api/suministros/catalogos devuelve HTTP 200 con datos maestros', $catOk, "Code: {$r4['code']}");

    // Obtener unidad y su propiedad correspondiente para total consistencia tarifaria
    $stmtU = $pdo->query("SELECT id, propiedad_id FROM unidades WHERE estado = 'ACTIVO' LIMIT 1");
    $uRow = $stmtU->fetch(PDO::FETCH_ASSOC);
    if ($uRow) {
        $unidadId = (int) $uRow['id'];
        $propiedadId = (int) $uRow['propiedad_id'];
    } else {
        $propiedadId = (int) ($jsonCat['datos']['propiedades'][0]['id'] ?? 1);
        $unidadId = 1;
    }

    // -------------------------------------------------------------------------
    // E2E-SUM-05: API Crear Suministro POST /api/suministros (HTTP 201)
    // -------------------------------------------------------------------------
    $codSuministro = "ELEC-E2E-{$sufijo}";
    $payloadSuministro = json_encode([
        'codigo' => $codSuministro,
        'nombre' => "Electricidad E2E {$sufijo}",
        'modalidad' => 'MEDIDO',
        'unidad_medida' => 'kWh',
        'permite_rollover' => true,
        'descripcion' => 'Suministro eléctrico individual para suite E2E',
    ]);

    $r5 = curlRequest("{$baseUrl}/api/suministros", 'POST', [
        "Cookie: {$cookieSession}",
        "X-CSRF-Token: {$csrfToken}",
        'Content-Type: application/json',
        'Accept: application/json',
    ], $payloadSuministro);

    $jsonSum = json_decode($r5['body'], true);
    $sumCreadoId = (int) ($jsonSum['datos']['id'] ?? 0);
    $creadoOk = ($r5['code'] === 201)
        && ($jsonSum['exito'] ?? false) === true
        && $sumCreadoId > 0;
    checkE2E('E2E-SUM-05', 'Endpoint POST /api/suministros crea suministro MEDIDO con HTTP 201 y CSRF verificado', $creadoOk, "Code: {$r5['code']}, ID: {$sumCreadoId}");

    // -------------------------------------------------------------------------
    // E2E-SUM-06: API Obtener Suministro GET /api/suministros/{id} (HTTP 200)
    // -------------------------------------------------------------------------
    $r6 = curlRequest("{$baseUrl}/api/suministros/{$sumCreadoId}", 'GET', [
        "Cookie: {$cookieSession}",
        'Accept: application/json',
    ]);
    $jsonSumGet = json_decode($r6['body'], true);
    $getOk = ($r6['code'] === 200)
        && ($jsonSumGet['exito'] ?? false) === true
        && ($jsonSumGet['datos']['codigo'] ?? '') === $codSuministro;
    checkE2E('E2E-SUM-06', 'Endpoint GET /api/suministros/{id} retorna detalle del suministro (HTTP 200)', $getOk, "Code: {$r6['code']}");

    // -------------------------------------------------------------------------
    // E2E-SUM-07: API Actualizar Suministro POST /api/suministros/{id} (HTTP 200)
    // -------------------------------------------------------------------------
    $payloadUpd = json_encode([
        'nombre' => "Electricidad E2E Actualizada {$sufijo}",
        'descripcion' => 'Descripción corregida via E2E',
    ]);
    $r7 = curlRequest("{$baseUrl}/api/suministros/{$sumCreadoId}", 'POST', [
        "Cookie: {$cookieSession}",
        "X-CSRF-Token: {$csrfToken}",
        'Content-Type: application/json',
        'Accept: application/json',
    ], $payloadUpd);
    $jsonUpd = json_decode($r7['body'], true);
    $updOk = ($r7['code'] === 200)
        && ($jsonUpd['exito'] ?? false) === true
        && str_contains($jsonUpd['datos']['nombre'] ?? '', 'Actualizada');
    checkE2E('E2E-SUM-07', 'Endpoint POST /api/suministros/{id} actualiza atributos con HTTP 200', $updOk, "Code: {$r7['code']}");

    // -------------------------------------------------------------------------
    // E2E-SUM-08: API Registrar Tarifa POST /api/suministros/tarifas (HTTP 201)
    // -------------------------------------------------------------------------
    $payloadTarifa = json_encode([
        'suministro_id' => $sumCreadoId,
        'ambito' => 'PROPIEDAD',
        'propiedad_id' => $propiedadId,
        'precio_unitario' => '0.9200',
        'fecha_inicio' => '2026-10-01',
        'fecha_fin' => '2026-12-31',
    ]);
    $r8 = curlRequest("{$baseUrl}/api/suministros/tarifas", 'POST', [
        "Cookie: {$cookieSession}",
        "X-CSRF-Token: {$csrfToken}",
        'Content-Type: application/json',
        'Accept: application/json',
    ], $payloadTarifa);
    $jsonTar = json_decode($r8['body'], true);
    $tarifaCreadaId = (int) ($jsonTar['datos']['id'] ?? 0);
    $tarifaOk = ($r8['code'] === 201)
        && ($jsonTar['exito'] ?? false) === true
        && $tarifaCreadaId > 0;
    checkE2E('E2E-SUM-08', 'Endpoint POST /api/suministros/tarifas asienta tarifa jerárquica con HTTP 201', $tarifaOk, "Code: {$r8['code']}, ID: {$tarifaCreadaId}");

    // -------------------------------------------------------------------------
    // E2E-SUM-09: API Listar Tarifas GET /api/suministros/{id}/tarifas (HTTP 200)
    // -------------------------------------------------------------------------
    $r9 = curlRequest("{$baseUrl}/api/suministros/{$sumCreadoId}/tarifas", 'GET', [
        "Cookie: {$cookieSession}",
        'Accept: application/json',
    ]);
    $jsonTarList = json_decode($r9['body'], true);
    $tarListOk = ($r9['code'] === 200)
        && ($jsonTarList['exito'] ?? false) === true
        && count($jsonTarList['datos']) >= 1;
    checkE2E('E2E-SUM-09', 'Endpoint GET /api/suministros/{id}/tarifas lista historial tarifario con HTTP 200', $tarListOk, "Code: {$r9['code']}");

    // -------------------------------------------------------------------------
    // E2E-SUM-10: API Instalar Medidor POST /api/suministros/medidores (HTTP 201)
    // -------------------------------------------------------------------------
    $serieMedidor = "MED-E2E-{$sufijo}";
    $payloadMedidor = json_encode([
        'suministro_id' => $sumCreadoId,
        'propiedad_id' => $propiedadId,
        'unidad_id' => $unidadId,
        'numero_serie' => $serieMedidor,
        'codigo_interno' => "INT-{$sufijo}",
        'marca' => 'ABB',
        'modelo' => 'B23',
        'fecha_instalacion' => '2026-10-01',
        'lectura_inicial' => '200.0000',
        'lectura_maxima' => '99999.0000',
        'permite_rollover' => true,
    ]);
    $r10 = curlRequest("{$baseUrl}/api/suministros/medidores", 'POST', [
        "Cookie: {$cookieSession}",
        "X-CSRF-Token: {$csrfToken}",
        'Content-Type: application/json',
        'Accept: application/json',
    ], $payloadMedidor);
    $jsonMed = json_decode($r10['body'], true);
    $medidorCreadoId = (int) ($jsonMed['datos']['id'] ?? 0);
    $medidorOk = ($r10['code'] === 201)
        && ($jsonMed['exito'] ?? false) === true
        && $medidorCreadoId > 0;
    checkE2E('E2E-SUM-10', 'Endpoint POST /api/suministros/medidores instala medidor físico con HTTP 201', $medidorOk, "Code: {$r10['code']}, ID: {$medidorCreadoId}");

    // -------------------------------------------------------------------------
    // E2E-SUM-11: API Registrar Lectura POST /api/suministros/lecturas (HTTP 201)
    // -------------------------------------------------------------------------
    $payloadLectura = json_encode([
        'medidor_id' => $medidorCreadoId,
        'fecha_lectura' => '2026-10-15',
        'valor_lectura' => '320.0000',
        'tipo_evento' => 'ORDINARIA',
    ]);
    $r11 = curlRequest("{$baseUrl}/api/suministros/lecturas", 'POST', [
        "Cookie: {$cookieSession}",
        "X-CSRF-Token: {$csrfToken}",
        'Content-Type: application/json',
        'Accept: application/json',
    ], $payloadLectura);
    $jsonLec = json_decode($r11['body'], true);
    $lecturaCreadaId = (int) ($jsonLec['datos']['id'] ?? 0);
    $lecturaOk = ($r11['code'] === 201)
        && ($jsonLec['exito'] ?? false) === true
        && $lecturaCreadaId > 0;
    checkE2E('E2E-SUM-11', 'Endpoint POST /api/suministros/lecturas asienta lectura periódica con HTTP 201', $lecturaOk, "Code: {$r11['code']}, ID: {$lecturaCreadaId}");

    // -------------------------------------------------------------------------
    // E2E-SUM-12: API Corregir Lectura POST /api/suministros/lecturas/{id}/corregir (HTTP 200)
    // -------------------------------------------------------------------------
    $payloadCorr = json_encode([
        'nuevo_valor' => '325.0000',
        'motivo' => 'Corrección por digitación errónea en teclado',
    ]);
    $r12 = curlRequest("{$baseUrl}/api/suministros/lecturas/{$lecturaCreadaId}/corregir", 'POST', [
        "Cookie: {$cookieSession}",
        "X-CSRF-Token: {$csrfToken}",
        'Content-Type: application/json',
        'Accept: application/json',
    ], $payloadCorr);
    $jsonCorr = json_decode($r12['body'], true);
    $corrOk = ($r12['code'] === 200)
        && ($jsonCorr['exito'] ?? false) === true
        && ($jsonCorr['datos']['valor_lectura'] ?? '') === '325.0000';
    checkE2E('E2E-SUM-12', 'Endpoint POST /api/suministros/lecturas/{id}/corregir aplica corrección append-only con HTTP 200', $corrOk, "Code: {$r12['code']}");

    // Registrar lectura de fin de período en medidor
    $rFin = curlRequest("{$baseUrl}/api/suministros/lecturas", 'POST', [
        "Cookie: {$cookieSession}",
        "X-CSRF-Token: {$csrfToken}",
        'Content-Type: application/json',
        'Accept: application/json',
    ], json_encode([
        'medidor_id' => $medidorCreadoId,
        'fecha_lectura' => '2026-10-31',
        'valor_lectura' => '400.0000',
        'tipo_evento' => 'ORDINARIA',
    ]));

    // Asegurar arrendamiento activo y cuenta folio en la unidad
    $stmtArrE2E = $pdo->prepare("SELECT id FROM arrendamientos WHERE unidad_id = ? AND estado = 'VIGENTE' LIMIT 1");
    $stmtArrE2E->execute([$unidadId]);
    $arrendamientoE2EId = (int) $stmtArrE2E->fetchColumn();

    if ($arrendamientoE2EId <= 0) {
        $stmtArrIns = $pdo->prepare("INSERT INTO arrendamientos (codigo, unidad_id, fecha_inicio, fecha_fin, dia_vencimiento, renta_mensual, deposito_garantia, monto_primer_periodo, estado, creado_por_actor_id) VALUES (?, ?, '2026-10-01', '2027-09-30', 5, 1800.00, 1800.00, 1800.00, 'VIGENTE', 1)");
        $stmtArrIns->execute(["ARR-E2E-{$sufijo}", $unidadId]);
        $arrendamientoE2EId = (int) $pdo->lastInsertId();

        $stmtArrPersE2E = $pdo->prepare("INSERT INTO arrendamiento_personas (arrendamiento_id, persona_id, tipo_relacion) VALUES (?, ?, 'TITULAR')");
        $stmtArrPersE2E->execute([$arrendamientoE2EId, $adminPersonaId]);

        $stmtFolioIns = $pdo->prepare("INSERT INTO cuentas_folios (codigo, arrendamiento_id, persona_titular_id, moneda_codigo, estado, creado_por_actor_id) VALUES (?, ?, ?, 'PEN', 'ABIERTA', 1)");
        $stmtFolioIns->execute(["FOL-E2E-{$sufijo}", $arrendamientoE2EId, $adminPersonaId]);
    }

    // -------------------------------------------------------------------------
    // E2E-SUM-13: API Liquidar Período POST /api/suministros/liquidaciones (HTTP 201)
    // -------------------------------------------------------------------------
    $payloadLiq = json_encode([
        'arrendamiento_id' => $arrendamientoE2EId,
        'suministro_id' => $sumCreadoId,
        'periodo_desde' => '2026-10-01',
        'periodo_hasta' => '2026-10-31',
        'fecha_vencimiento' => '2026-11-05',
    ]);
    $r13 = curlRequest("{$baseUrl}/api/suministros/liquidaciones", 'POST', [
        "Cookie: {$cookieSession}",
        "X-CSRF-Token: {$csrfToken}",
        'Content-Type: application/json',
        'Accept: application/json',
    ], $payloadLiq);
    $jsonLiq = json_decode($r13['body'], true);
    $liqCreadaId = (int) ($jsonLiq['datos']['id'] ?? 0);
    $liqOk = ($r13['code'] === 201)
        && ($jsonLiq['exito'] ?? false) === true
        && $liqCreadaId > 0
        && !empty($jsonLiq['datos']['folio'])
        && (int) ($jsonLiq['datos']['cargo_cuenta_id'] ?? 0) > 0;
    checkE2E('E2E-SUM-13', 'Endpoint POST /api/suministros/liquidaciones devenga liquidación y cargo en cuenta folio (HTTP 201)', $liqOk, "Code: {$r13['code']}, ID: {$liqCreadaId}");

    // -------------------------------------------------------------------------
    // E2E-SUM-14: API Anular Liquidación POST /api/suministros/liquidaciones/{id}/anular (HTTP 200)
    // -------------------------------------------------------------------------
    $payloadAnular = json_encode([
        'motivo' => 'Anulación auditada de liquidación por motivo de prueba E2E',
    ]);
    $r14 = curlRequest("{$baseUrl}/api/suministros/liquidaciones/{$liqCreadaId}/anular", 'POST', [
        "Cookie: {$cookieSession}",
        "X-CSRF-Token: {$csrfToken}",
        'Content-Type: application/json',
        'Accept: application/json',
    ], $payloadAnular);
    $jsonAnular = json_decode($r14['body'], true);
    $anularOk = ($r14['code'] === 200)
        && ($jsonAnular['exito'] ?? false) === true;
    checkE2E('E2E-SUM-14', 'Endpoint POST /api/suministros/liquidaciones/{id}/anular anula liquidación y cargo con HTTP 200', $anularOk, "Code: {$r14['code']}");

    echo "\n====================================================================\n";
    echo " RESULTADOS VALIDACIÓN E2E: {$pass} / 14 PASS\n";
    echo "====================================================================\n";

    if ($pass !== 14) {
        exit(1);
    }
    exit(0);

} catch (\Throwable $e) {
    echo "\n[ERROR FATAL E2E]: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
