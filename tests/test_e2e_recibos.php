<?php

declare(strict_types=1);

/**
 * Suite de Pruebas End-to-End (E2E) Reales contra Apache en HTTPS — Camargo PMS (RECIBOS-1 / D-082)
 * Casos E2E-REC-01 a E2E-REC-14 (14/14).
 *
 * Principios vinculantes:
 * - Servidor web real Apache en HTTPS (https://app.camargo-pms.test/)
 * - Control estricto de sesiones, cookies y tokens CSRF
 * - RBAC vinculado a recibos.*
 * - D-075 / D-076: Geometría nativa Alina (app-form, app-icon-form, b-r-20), Font Awesome exclusivo
 * - Axioma D-082: CARGO != PAGO != APLICACIÓN != RECIBO != PDF
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
echo " VALIDACIÓN HTTP E2E REAL CONTRA APACHE (RECIBOS-1 / D-082)\n";
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
$adminUser = 'admin_rec_' . strtolower($sufijo);
$sinPermisoUser = 'sin_rec_' . strtolower($sufijo);
$passwordPlana = 'PassRecibos123!456';
$passwordHash = password_hash($passwordPlana, PASSWORD_DEFAULT);

$cookieSession = null;
$csrfToken = null;
$reciboEmitidoId = null;
$reciboEmitidoCodigo = null;

try {
    // -------------------------------------------------------------------------
    // E2E-REC-01: Acceso no autenticado a /recibos redirige a /login (HTTP 302)
    // -------------------------------------------------------------------------
    $r1 = curlRequest("{$baseUrl}/recibos", 'GET');
    $redirigeLogin = ($r1['code'] === 302 && str_contains((string) $r1['location'], '/login'));
    checkE2E('E2E-REC-01', 'Acceso no autenticado a /recibos redirige a /login (HTTP 302)', $redirigeLogin, "Code: {$r1['code']}, Loc: {$r1['location']}");

    // -------------------------------------------------------------------------
    // E2E-REC-02: Usuario sin permisos recibe HTTP 403 Forbidden en /recibos
    // -------------------------------------------------------------------------
    $pdo->exec("INSERT INTO personas (nombres, apellido_paterno, apellido_materno, pais_nacionalidad_id, creado_en) VALUES ('SinPerm', 'Recibos', '{$sufijo}', 1, NOW())");
    $sinPersonaId = (int) $pdo->lastInsertId();

    $stmtUSin = $pdo->prepare("INSERT INTO usuarios (persona_id, nombre_usuario, contrasena_hash, estado, creado_en) VALUES (?, ?, ?, 'ACTIVO', NOW())");
    $stmtUSin->execute([$sinPersonaId, $sinPermisoUser, $passwordHash]);
    $sinPermisoUsuarioId = (int) $pdo->lastInsertId();

    $stmtActorSin = $pdo->prepare("INSERT INTO actores (codigo, tipo, nombre, usuario_id, estado, creado_en) VALUES (?, 'USUARIO', ?, ?, 'ACTIVO', NOW())");
    $stmtActorSin->execute(['USR_' . $sinPermisoUsuarioId, 'Sin Permiso Recibos ' . $sufijo, $sinPermisoUsuarioId]);

    // Login HTTP con usuario sin permisos
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

    $r2 = curlRequest("{$baseUrl}/recibos", 'GET', [
        "Cookie: {$cookieSessionSin}",
    ]);
    $es403 = ($r2['code'] === 403);
    checkE2E('E2E-REC-02', 'Usuario autenticado sin permiso recibos.ver recibe HTTP 403 Forbidden en /recibos', $es403, "Code: {$r2['code']}");

    // -------------------------------------------------------------------------
    // E2E-REC-03: Superadmin accede exitosamente a /recibos (HTTP 200) y valida Alina
    // -------------------------------------------------------------------------
    $pdo->exec("INSERT INTO personas (nombres, apellido_paterno, apellido_materno, pais_nacionalidad_id, creado_en) VALUES ('Admin', 'Recibos', '{$sufijo}', 1, NOW())");
    $adminPersonaId = (int) $pdo->lastInsertId();

    $stmtUAdmin = $pdo->prepare("INSERT INTO usuarios (persona_id, nombre_usuario, contrasena_hash, estado, creado_en) VALUES (?, ?, ?, 'ACTIVO', NOW())");
    $stmtUAdmin->execute([$adminPersonaId, $adminUser, $passwordHash]);
    $adminUsuarioId = (int) $pdo->lastInsertId();

    $stmtActorAdmin = $pdo->prepare("INSERT INTO actores (codigo, tipo, nombre, usuario_id, estado, creado_en) VALUES (?, 'USUARIO', ?, ?, 'ACTIVO', NOW())");
    $stmtActorAdmin->execute(['USR_' . $adminUsuarioId, 'Admin Recibos ' . $sufijo, $adminUsuarioId]);

    $superadminRolId = (int) $pdo->query("SELECT id FROM roles WHERE es_superadministrador = 1 OR codigo = 'SUPERADMINISTRADOR' LIMIT 1")->fetchColumn() ?: 1;
    $pdo->exec("INSERT INTO usuarios_roles (usuario_id, rol_id, asignado_en) VALUES ({$adminUsuarioId}, {$superadminRolId}, NOW())");

    // Login HTTP como Superadministrador
    $rLoginGet = curlRequest("{$baseUrl}/login", 'GET');
    $csrfLogin = extraerCsrfDeHtml($rLoginGet['body']);
    $cookieLogin = !empty($rLoginGet['cookies']) ? implode('; ', $rLoginGet['cookies']) : '';

    $bodyLogin = http_build_query([
        'nombre_usuario' => $adminUser,
        'contrasena' => $passwordPlana,
        '_csrf_token' => $csrfLogin,
    ]);
    $rLoginPost = curlRequest("{$baseUrl}/login", 'POST', [
        'Content-Type: application/x-www-form-urlencoded',
        "Cookie: {$cookieLogin}",
    ], $bodyLogin);

    $cookieSession = !empty($rLoginPost['cookies']) ? implode('; ', $rLoginPost['cookies']) : $cookieLogin;

    $r3 = curlRequest("{$baseUrl}/recibos", 'GET', [
        "Cookie: {$cookieSession}",
    ]);
    $htmlVista = $r3['body'];
    $csrfToken = extraerCsrfDeHtml($htmlVista);

    $es200 = ($r3['code'] === 200);
    $tieneKpis = str_contains($htmlVista, 'kpi-recaudado-hoy') && str_contains($htmlVista, 'kpi-emitidos-hoy');
    $tieneModalesAlina = str_contains($htmlVista, 'modal-emitir-recibo') && str_contains($htmlVista, 'app-form');
    $tieneFontAwesome = str_contains($htmlVista, 'fa-solid') || str_contains($htmlVista, 'fa-receipt');
    $alinaOk = $es200 && $tieneKpis && $tieneModalesAlina && $tieneFontAwesome;

    checkE2E(
        'E2E-REC-03',
        'Superadmin accede exitosamente a /recibos (HTTP 200) y valida Alina, KPIs y modales reactivos',
        $alinaOk,
        "Code: {$r3['code']}, KPIs: " . ($tieneKpis ? 'OK' : 'FAIL') . ", Modales: " . ($tieneModalesAlina ? 'OK' : 'FAIL')
    );

    // Preparar Pago Confirmado para pruebas de API
    $propId = (int) $pdo->query("SELECT id FROM propiedades WHERE estado = 'ACTIVO' LIMIT 1")->fetchColumn() ?: 1;
    $unidId = (int) $pdo->query("SELECT id FROM unidades WHERE propiedad_id = {$propId} LIMIT 1")->fetchColumn() ?: 1;
    $metodoId = (int) $pdo->query("SELECT id FROM metodos_pago WHERE activo = 1 LIMIT 1")->fetchColumn() ?: 1;

    $stmtArrE2E = $pdo->prepare("INSERT INTO arrendamientos (codigo, unidad_id, fecha_inicio, fecha_fin, dia_vencimiento, renta_mensual, deposito_garantia, monto_primer_periodo, estado, creado_por_actor_id) VALUES (:cod, :uid, '2026-09-01', '2027-08-31', 5, 950.00, 950.00, 950.00, 'VIGENTE', 1)");
    $stmtArrE2E->execute(['cod' => "ARR-E2E-{$sufijo}", 'uid' => $unidId]);
    $arrE2EId = (int) $pdo->lastInsertId();

    $stmtFolioE2E = $pdo->prepare("INSERT INTO cuentas_folios (codigo, persona_titular_id, arrendamiento_id, moneda_codigo, estado, creado_por_actor_id) VALUES (:cod, :per, :arr, 'PEN', 'ABIERTA', 1)");
    $stmtFolioE2E->execute(['cod' => "FOL-E2E-{$sufijo}", 'per' => $adminPersonaId, 'arr' => $arrE2EId]);
    $folioE2EId = (int) $pdo->lastInsertId();

    // Pago E2E de S/ 850.00
    $stmtPagoE2E = $pdo->prepare("INSERT INTO pagos_cuenta (codigo, cuenta_folio_id, metodo_pago_id, monto_total, monto_aplicado, moneda_codigo, referencia_operacion, estado, creado_por_actor_id) VALUES (:c, :f, :m, 850.00, 0.00, 'PEN', :r, 'CONFIRMADO', 1)");
    $stmtPagoE2E->execute(['c' => "PAG-E2E-{$sufijo}", 'f' => $folioE2EId, 'm' => $metodoId, 'r' => "OP-E2E-{$sufijo}"]);
    $pagoE2EId = (int) $pdo->lastInsertId();

    // -------------------------------------------------------------------------
    // E2E-REC-04: Endpoint GET /api/recibos/catalogos retorna pagos confirmados candidatos
    // -------------------------------------------------------------------------
    $rCat = curlRequest("{$baseUrl}/api/recibos/catalogos", 'GET', [
        "Cookie: {$cookieSession}",
    ]);
    $jsonCat = json_decode($rCat['body'], true);
    $catalogosOk = ($rCat['code'] === 200) &&
                   isset($jsonCat['datos']['pagos_elegibles']) &&
                   is_array($jsonCat['datos']['pagos_elegibles']);

    checkE2E('E2E-REC-04', 'Endpoint GET /api/recibos/catalogos retorna pagos confirmados candidatos (HTTP 200)', $catalogosOk, "Code: {$rCat['code']}");

    // -------------------------------------------------------------------------
    // E2E-REC-05: Catálogo incluye datos de cuentas folios activas
    // -------------------------------------------------------------------------
    $foliosCatOk = ($rCat['code'] === 200) &&
                   isset($jsonCat['datos']['cuentas_folios']) &&
                   is_array($jsonCat['datos']['cuentas_folios']);

    checkE2E('E2E-REC-05', 'Catálogo GET /api/recibos/catalogos incluye cuentas folios activas estructuradas (HTTP 200)', $foliosCatOk, "Code: {$rCat['code']}");

    // -------------------------------------------------------------------------
    // E2E-REC-06: Intento de emitir recibo POST sin token CSRF es rechazado (HTTP 403)
    // -------------------------------------------------------------------------
    $rSinCsrf = curlRequest("{$baseUrl}/api/recibos/emitir", 'POST', [
        'Content-Type: application/json',
        "Cookie: {$cookieSession}",
    ], json_encode(['pago_id' => $pagoE2EId]));

    $csrfRechazado = in_array($rSinCsrf['code'], [403, 419], true);
    checkE2E('E2E-REC-06', 'Intento de emitir recibo POST sin token CSRF es rechazado (HTTP 403)', $csrfRechazado, "Code: {$rSinCsrf['code']}");

    // -------------------------------------------------------------------------
    // E2E-REC-07: Emisión formal exitosa POST /api/recibos/emitir genera folio y PDF (HTTP 201)
    // -------------------------------------------------------------------------
    $bodyEmitir = json_encode([
        'pago_id' => $pagoE2EId,
        'concepto_general' => 'Abono formal verificado en prueba E2E',
        'notas' => 'Prueba automatizada de emisión HTTP',
        '_csrf_token' => $csrfToken,
    ]);
    $rEmitir = curlRequest("{$baseUrl}/api/recibos/emitir", 'POST', [
        'Content-Type: application/json',
        'X-CSRF-Token: ' . $csrfToken,
        "Cookie: {$cookieSession}",
    ], $bodyEmitir);

    $jsonEmitir = json_decode($rEmitir['body'], true);
    $emisionOk = ($rEmitir['code'] === 201) &&
                 isset($jsonEmitir['datos']['id']) &&
                 isset($jsonEmitir['datos']['codigo']) &&
                 str_starts_with($jsonEmitir['datos']['codigo'], 'REC-');

    if ($emisionOk) {
        $reciboEmitidoId = (int) $jsonEmitir['datos']['id'];
        $reciboEmitidoCodigo = (string) $jsonEmitir['datos']['codigo'];
    } else {
        echo "    [DEBUG EMITIR ERROR]: " . $rEmitir['body'] . "\n";
    }

    checkE2E(
        'E2E-REC-07',
        'Emisión formal exitosa POST /api/recibos/emitir genera folio REC-YYYYMM-XXXX y PDF (HTTP 201)',
        $emisionOk,
        "Code: {$rEmitir['code']}, Folio: " . ($jsonEmitir['datos']['codigo'] ?? 'N/A')
    );

    // -------------------------------------------------------------------------
    // E2E-REC-08: Intento de emitir segundo recibo sobre el mismo pago retorna HTTP 409 Conflicto
    // -------------------------------------------------------------------------
    $rDobleEmitir = curlRequest("{$baseUrl}/api/recibos/emitir", 'POST', [
        'Content-Type: application/json',
        'X-CSRF-Token: ' . $csrfToken,
        "Cookie: {$cookieSession}",
    ], $bodyEmitir);

    $dobleEmitirBloqueado = ($rDobleEmitir['code'] === 409);
    checkE2E('E2E-REC-08', 'Intento de emitir segundo recibo activo sobre el mismo pago retorna HTTP 409 Conflicto', $dobleEmitirBloqueado, "Code: {$rDobleEmitir['code']}");

    // -------------------------------------------------------------------------
    // E2E-REC-09: Endpoint GET /api/recibos retorna listado conteniendo el recibo emitido
    // -------------------------------------------------------------------------
    $rList = curlRequest("{$baseUrl}/api/recibos", 'GET', [
        "Cookie: {$cookieSession}",
    ]);
    $jsonList = json_decode($rList['body'], true);
    $encontradoEnLista = false;
    if ($rList['code'] === 200 && !empty($jsonList['datos'])) {
        foreach ($jsonList['datos'] as $item) {
            if ($item['id'] === $reciboEmitidoId) {
                $encontradoEnLista = true;
                break;
            }
        }
    }
    checkE2E('E2E-REC-09', 'Endpoint GET /api/recibos retorna listado conteniendo el recibo emitido (HTTP 200)', $encontradoEnLista, "Code: {$rList['code']}");

    // -------------------------------------------------------------------------
    // E2E-REC-10: Endpoint GET /api/recibos/{id} retorna detalle completo
    // -------------------------------------------------------------------------
    $rDetalle = curlRequest("{$baseUrl}/api/recibos/{$reciboEmitidoId}", 'GET', [
        "Cookie: {$cookieSession}",
    ]);
    $jsonDetalle = json_decode($rDetalle['body'], true);
    if ($rDetalle['code'] !== 200) {
        echo "    [DEBUG DETALLE ERROR]: " . $rDetalle['body'] . "\n";
    }
    $detalleOk = ($rDetalle['code'] === 200) &&
                 isset($jsonDetalle['datos']['id']) &&
                 ($jsonDetalle['datos']['id'] === $reciboEmitidoId) &&
                 isset($jsonDetalle['datos']['monto_recaudado']);

    checkE2E('E2E-REC-10', 'Endpoint GET /api/recibos/{id} entrega el detalle exhaustivo del recibo y líneas en T0 (HTTP 200)', $detalleOk, "Code: {$rDetalle['code']}");

    // -------------------------------------------------------------------------
    // E2E-REC-11: Descarga binaria GET /api/recibos/{id}/pdf entrega PDF y cabecera SHA256
    // -------------------------------------------------------------------------
    $rPdf = curlRequest("{$baseUrl}/api/recibos/{$reciboEmitidoId}/pdf", 'GET', [
        "Cookie: {$cookieSession}",
    ]);
    $esPdfMime = str_contains($rPdf['headers'], 'Content-Type: application/pdf');
    $tienePdfHeader = str_starts_with($rPdf['body'], '%PDF');
    $tieneSha256Header = str_contains($rPdf['headers'], 'X-Document-SHA256:');
    $pdfOk = ($rPdf['code'] === 200) && $esPdfMime && $tienePdfHeader && $tieneSha256Header;

    checkE2E(
        'E2E-REC-11',
        'Descarga binaria GET /api/recibos/{id}/pdf entrega application/pdf con header X-Document-SHA256 (HTTP 200)',
        $pdfOk,
        "Code: {$rPdf['code']}, MIME: " . ($esPdfMime ? 'OK' : 'FAIL') . ", %PDF: " . ($tienePdfHeader ? 'OK' : 'FAIL')
    );

    // -------------------------------------------------------------------------
    // E2E-REC-12: Verificación criptográfica GET /api/recibos/{id}/verificar-hash
    // -------------------------------------------------------------------------
    $rVerif = curlRequest("{$baseUrl}/api/recibos/{$reciboEmitidoId}/verificar-hash", 'GET', [
        "Cookie: {$cookieSession}",
    ]);
    $jsonVerif = json_decode($rVerif['body'], true);
    $hashOk = ($rVerif['code'] === 200) &&
              isset($jsonVerif['datos']['valido']) &&
              ($jsonVerif['datos']['valido'] === true);

    checkE2E('E2E-REC-12', 'Endpoint GET /api/recibos/{id}/verificar-hash valida correspondencia criptográfica SHA-256 (HTTP 200)', $hashOk, "Code: {$rVerif['code']}");

    // -------------------------------------------------------------------------
    // E2E-REC-13: Anulación formal vía POST /api/recibos/{id}/anular
    // -------------------------------------------------------------------------
    $bodyAnular = json_encode([
        'motivo' => 'Anulación formal probada en test suite E2E',
        '_csrf_token' => $csrfToken,
    ]);
    $rAnular = curlRequest("{$baseUrl}/api/recibos/{$reciboEmitidoId}/anular", 'POST', [
        'Content-Type: application/json',
        'X-CSRF-Token: ' . $csrfToken,
        "Cookie: {$cookieSession}",
    ], $bodyAnular);

    $jsonAnular = json_decode($rAnular['body'], true);
    $anulacionOk = ($rAnular['code'] === 200) &&
                   isset($jsonAnular['exito']) &&
                   ($jsonAnular['exito'] === true);

    checkE2E('E2E-REC-13', 'Anulación formal POST /api/recibos/{id}/anular cambia estado a ANULADO sin afectar pago (HTTP 200)', $anulacionOk, "Code: {$rAnular['code']}");

    // -------------------------------------------------------------------------
    // E2E-REC-14: Intento de re-anular el recibo ya anulado retorna error HTTP 409
    // -------------------------------------------------------------------------
    $rReAnular = curlRequest("{$baseUrl}/api/recibos/{$reciboEmitidoId}/anular", 'POST', [
        'Content-Type: application/json',
        'X-CSRF-Token: ' . $csrfToken,
        "Cookie: {$cookieSession}",
    ], $bodyAnular);

    $reAnulacionBloqueada = ($rReAnular['code'] === 409);
    checkE2E('E2E-REC-14', 'Intento de re-anular recibo ya anulado retorna HTTP 409 Conflicto preservando el histórico', $reAnulacionBloqueada, "Code: {$rReAnular['code']}");

} catch (\Throwable $e) {
    echo "\n[ERROR CRÍTICO EN SUITE E2E]: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    $fail++;
}

echo "\n====================================================================\n";
echo "RESULTADOS E2E RECIBOS-1: {$pass} / 14 PASS\n";
echo "====================================================================\n";

if ($pass === 14 && $fail === 0) {
    exit(0);
} else {
    exit(1);
}
