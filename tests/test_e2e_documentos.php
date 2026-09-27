<?php

declare(strict_types=1);

/**
 * Suite de Pruebas End-to-End (E2E) Reales contra Apache en HTTPS — Camargo PMS (DOCUMENTOS-1 / D-079)
 * Casos E2E-DOC-01 a E2E-DOC-14 (14/14).
 *
 * Principios vinculantes:
 * - Servidor web real Apache en HTTPS (https://app.camargo-pms.test/)
 * - Control estricto de sesiones, cookies y tokens CSRF
 * - RBAC vinculado a documentos.* (ver, emitir, descargar, regenerar, anular, plantillas.gestionar)
 * - D-075 / D-076: Geometría nativa Alina (app-form, b-r-20), Font Awesome exclusivo
 * - Snapshots inmutables, hash SHA-256 de PDF físico y detección de discrepancias
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
echo " VALIDACIÓN HTTP E2E REAL CONTRA APACHE (DOCUMENTOS-1 / D-079)\n";
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
$adminUser = 'admin_doc_' . strtolower($sufijo);
$sinPermisoUser = 'sin_doc_' . strtolower($sufijo);
$passwordPlana = 'PassDoc123!456';
$passwordHash = password_hash($passwordPlana, PASSWORD_DEFAULT);

$cookieSession = null;
$csrfToken = null;
$adminUsuarioId = null;
$sinPermisoUsuarioId = null;

try {
    // -------------------------------------------------------------------------
    // E2E-DOC-01: Acceso no autenticado redirige a /login (HTTP 302)
    // -------------------------------------------------------------------------
    $r1 = curlRequest("{$baseUrl}/documentos", 'GET');
    $redirigeLogin = ($r1['code'] === 302 && str_contains((string) $r1['location'], '/login'));
    checkE2E('E2E-DOC-01', 'Acceso no autenticado a /documentos redirige a /login (HTTP 302)', $redirigeLogin, "Code: {$r1['code']}, Loc: {$r1['location']}");

    // -------------------------------------------------------------------------
    // E2E-DOC-02: Autenticación real de usuario administrador
    // -------------------------------------------------------------------------
    $pdo->exec("INSERT INTO personas (nombres, apellido_paterno, apellido_materno, pais_nacionalidad_id, creado_en) VALUES ('Admin', 'Documentos', '{$sufijo}', 1, NOW())");
    $adminPersonaId = (int) $pdo->lastInsertId();

    $stmtUAdmin = $pdo->prepare("INSERT INTO usuarios (persona_id, nombre_usuario, contrasena_hash, estado, creado_en) VALUES (?, ?, ?, 'ACTIVO', NOW())");
    $stmtUAdmin->execute([$adminPersonaId, $adminUser, $passwordHash]);
    $adminUsuarioId = (int) $pdo->lastInsertId();

    $stmtActorAdmin = $pdo->prepare("INSERT INTO actores (codigo, tipo, nombre, usuario_id, estado, creado_en) VALUES (?, 'USUARIO', ?, ?, 'ACTIVO', NOW())");
    $stmtActorAdmin->execute(['USR_' . $adminUsuarioId, 'Admin Doc ' . $sufijo, $adminUsuarioId]);

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

    $loginOk = ($rLoginPost['code'] === 302);
    if (!empty($rLoginPost['cookies'])) {
        $cookieSession = $rLoginPost['cookies'][0];
    } else {
        $cookieSession = $cookieLogin;
    }
    checkE2E('E2E-DOC-02', 'Autenticación HTTP real de usuario administrador (HTTP 302 tras login)', $loginOk, "Code: {$rLoginPost['code']}");

    // -------------------------------------------------------------------------
    // E2E-DOC-03: Usuario sin permisos recibe HTTP 403 en /documentos
    // -------------------------------------------------------------------------
    $pdo->exec("INSERT INTO personas (nombres, apellido_paterno, apellido_materno, pais_nacionalidad_id, creado_en) VALUES ('SinPerm', 'Documentos', '{$sufijo}', 1, NOW())");
    $sinPersonaId = (int) $pdo->lastInsertId();

    $stmtUSin = $pdo->prepare("INSERT INTO usuarios (persona_id, nombre_usuario, contrasena_hash, estado, creado_en) VALUES (?, ?, ?, 'ACTIVO', NOW())");
    $stmtUSin->execute([$sinPersonaId, $sinPermisoUser, $passwordHash]);
    $sinPermisoUsuarioId = (int) $pdo->lastInsertId();

    // Rol vacío sin permisos de documentos
    $pdo->exec("INSERT INTO roles (codigo, nombre, descripcion, es_superadministrador, estado, creado_en) VALUES ('ROL_SIN_DOC_{$sufijo}', 'Sin Documentos', 'Sin permisos', 0, 'ACTIVO', NOW())");
    $rolVacioId = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO usuarios_roles (usuario_id, rol_id, asignado_en) VALUES ({$sinPermisoUsuarioId}, {$rolVacioId}, NOW())");

    $rLoginGetSin = curlRequest("{$baseUrl}/login", 'GET');
    $csrfSin = extraerCsrfDeHtml($rLoginGetSin['body']);
    $cookieSin = $rLoginGetSin['cookies'][0] ?? '';

    $rLoginPostSin = curlRequest("{$baseUrl}/login", 'POST', [
        'Content-Type: application/x-www-form-urlencoded',
        "Cookie: {$cookieSin}",
    ], http_build_query([
        'nombre_usuario' => $sinPermisoUser,
        'contrasena' => $passwordPlana,
        '_csrf_token' => $csrfSin,
    ]));

    $cookieSessionSin = !empty($rLoginPostSin['cookies']) ? $rLoginPostSin['cookies'][0] : $cookieSin;
    $rSinDoc = curlRequest("{$baseUrl}/documentos", 'GET', ["Cookie: {$cookieSessionSin}"]);
    $es403 = ($rSinDoc['code'] === 403);
    checkE2E('E2E-DOC-03', 'Usuario autenticado sin permiso documentos.ver es rechazado con HTTP 403', $es403, "Code: {$rSinDoc['code']}");

    // -------------------------------------------------------------------------
    // E2E-DOC-04: Acceso autorizado a /documentos renderiza interfaz Alina
    // -------------------------------------------------------------------------
    $rDocVista = curlRequest("{$baseUrl}/documentos", 'GET', ["Cookie: {$cookieSession}"]);
    $csrfToken = extraerCsrfDeHtml($rDocVista['body']);
    $vistaOk = ($rDocVista['code'] === 200 &&
        str_contains($rDocVista['body'], 'Motor Documental y Generación PDF') &&
        str_contains($rDocVista['body'], 'tab-emitidos-btn') &&
        str_contains($rDocVista['body'], 'gestion-documentos.js'));
    checkE2E('E2E-DOC-04', 'Acceso autorizado a /documentos renderiza vista Alina con KPIs y pestañas (HTTP 200)', $vistaOk, "Code: {$rDocVista['code']}");

    // -------------------------------------------------------------------------
    // E2E-DOC-05: GET /api/documentos/plantillas retorna listado con versión activa
    // -------------------------------------------------------------------------
    $rPlantillas = curlRequest("{$baseUrl}/api/documentos/plantillas", 'GET', [
        "Cookie: {$cookieSession}",
        'Accept: application/json',
    ]);
    $jsonPlantillas = json_decode($rPlantillas['body'], true);
    $plantillasOk = ($rPlantillas['code'] === 200 &&
        isset($jsonPlantillas['datos']) &&
        count($jsonPlantillas['datos']) >= 1 &&
        $jsonPlantillas['datos'][0]['codigo'] === 'CONTRATO_ARRENDAMIENTO');
    checkE2E('E2E-DOC-05', 'GET /api/documentos/plantillas retorna catálogo con plantilla canónica activa (HTTP 200)', $plantillasOk, "Code: {$rPlantillas['code']}");

    // -------------------------------------------------------------------------
    // E2E-DOC-06: Emisión en modo borrador retorna PDF binario sin persistir
    // -------------------------------------------------------------------------
    $rBorrador = curlRequest("{$baseUrl}/api/arrendamientos/1/emitir-contrato", 'POST', [
        "Cookie: {$cookieSession}",
        'Content-Type: application/json',
        'Accept: application/json, application/pdf',
        "X-CSRF-Token: {$csrfToken}",
    ], json_encode(['es_borrador' => true]));

    $borradorOk = ($rBorrador['code'] === 200 &&
        str_starts_with($rBorrador['body'], '%PDF-1.') &&
        str_contains($rBorrador['headers'], 'application/pdf'));
    checkE2E('E2E-DOC-06', 'POST /api/arrendamientos/1/emitir-contrato en modo borrador retorna PDF con cabecera application/pdf (HTTP 200)', $borradorOk, "Code: {$rBorrador['code']}");

    // -------------------------------------------------------------------------
    // E2E-DOC-07: Emisión oficial de contrato legal genera folio y hash SHA-256
    // -------------------------------------------------------------------------
    $rOficial = curlRequest("{$baseUrl}/api/arrendamientos/1/emitir-contrato", 'POST', [
        "Cookie: {$cookieSession}",
        'Content-Type: application/json',
        'Accept: application/json',
        "X-CSRF-Token: {$csrfToken}",
    ], json_encode(['es_borrador' => false]));

    $jsonOficial = json_decode($rOficial['body'], true);
    $docCreado = $jsonOficial['documento'] ?? [];
    $docCreadoId = (int) ($docCreado['id'] ?? 0);
    $folioCreado = $docCreado['codigo_folio'] ?? '';
    $hashSha256 = $docCreado['hash_pdf_sha256'] ?? '';

    $oficialOk = ($rOficial['code'] === 201 &&
        $docCreadoId > 0 &&
        str_starts_with($folioCreado, 'DOC-ARR-') &&
        strlen($hashSha256) === 64);
    checkE2E('E2E-DOC-07', 'POST /api/arrendamientos/1/emitir-contrato oficial asigna folio atómico y hash SHA-256 (HTTP 201)', $oficialOk, "Code: {$rOficial['code']}, Folio: {$folioCreado}");

    // -------------------------------------------------------------------------
    // E2E-DOC-08: GET /api/documentos lista el nuevo documento emitido
    // -------------------------------------------------------------------------
    $rListDocs = curlRequest("{$baseUrl}/api/documentos", 'GET', [
        "Cookie: {$cookieSession}",
        'Accept: application/json',
    ]);
    $jsonDocs = json_decode($rListDocs['body'], true);
    $encontrado = false;
    foreach ($jsonDocs['datos'] ?? [] as $d) {
        if ($d['id'] === $docCreadoId) {
            $encontrado = true;
            break;
        }
    }
    $listDocsOk = ($rListDocs['code'] === 200 && $encontrado);
    checkE2E('E2E-DOC-08', 'GET /api/documentos retorna el documento recién emitido en el listado (HTTP 200)', $listDocsOk, "Code: {$rListDocs['code']}");

    // -------------------------------------------------------------------------
    // E2E-DOC-09: GET /api/documentos/{id}/descargar entrega el PDF binario
    // -------------------------------------------------------------------------
    $rDescarga = curlRequest("{$baseUrl}/api/documentos/{$docCreadoId}/descargar", 'GET', [
        "Cookie: {$cookieSession}",
    ]);
    $descargaOk = ($rDescarga['code'] === 200 &&
        str_starts_with($rDescarga['body'], '%PDF-1.') &&
        str_contains($rDescarga['headers'], 'Content-Disposition') &&
        hash('sha256', $rDescarga['body']) === $hashSha256);
    checkE2E('E2E-DOC-09', 'GET /api/documentos/{id}/descargar descarga el PDF con hash SHA-256 verificado (HTTP 200)', $descargaOk, "Code: {$rDescarga['code']}");

    // -------------------------------------------------------------------------
    // E2E-DOC-10: GET /api/documentos/{id}/verificar comprueba integridad en vivo
    // -------------------------------------------------------------------------
    $rVerificar = curlRequest("{$baseUrl}/api/documentos/{$docCreadoId}/verificar", 'GET', [
        "Cookie: {$cookieSession}",
        'Accept: application/json',
    ]);
    $jsonVerif = json_decode($rVerificar['body'], true);
    $verifOk = ($rVerificar['code'] === 200 &&
        ($jsonVerif['estado_integridad'] ?? '') === 'VALIDO' &&
        ($jsonVerif['hash_sha256'] ?? '') === $hashSha256);
    checkE2E('E2E-DOC-10', 'GET /api/documentos/{id}/verificar comprueba integridad física contra registro SHA-256 (HTTP 200)', $verifOk, "Code: {$rVerificar['code']}");

    // -------------------------------------------------------------------------
    // E2E-DOC-11: Creación de versión con payload inseguro es rechazada (HTTP 422)
    // -------------------------------------------------------------------------
    $rInsegura = curlRequest("{$baseUrl}/api/documentos/plantillas/1/versiones", 'POST', [
        "Cookie: {$cookieSession}",
        'Content-Type: application/json',
        'Accept: application/json',
        "X-CSRF-Token: {$csrfToken}",
    ], json_encode([
        'titulo_documento' => 'Versión Insegura',
        'cuerpo_html' => '<div onclick="bad()"><script>alert(1)</script></div>',
    ]));
    $inseguraOk = ($rInsegura['code'] === 422 && str_contains($rInsegura['body'], 'seguridad'));
    checkE2E('E2E-DOC-11', 'POST /api/documentos/plantillas/1/versiones rechaza etiquetas <script> y eventos JS (HTTP 422)', $inseguraOk, "Code: {$rInsegura['code']}");

    // -------------------------------------------------------------------------
    // E2E-DOC-12: Publicación de nueva versión V2 y conmutación atómica de versión activa
    // -------------------------------------------------------------------------
    $rNuevaVer = curlRequest("{$baseUrl}/api/documentos/plantillas/1/versiones", 'POST', [
        "Cookie: {$cookieSession}",
        'Content-Type: application/json',
        'Accept: application/json',
        "X-CSRF-Token: {$csrfToken}",
    ], json_encode([
        'titulo_documento' => 'CONTRATO DE ARRENDAMIENTO V2 REVISADO',
        'cuerpo_html' => '<div class="contrato"><h1>CONTRATO V2</h1><p>{{arrendatario.nombre_completo}} arrienda {{unidad.nombre}} por {{contrato.canon_monto}} {{contrato.canon_moneda}}.</p></div>',
        'notas_version' => 'Ajuste de cláusulas operacionales',
        'activar_inmediatamente' => false,
    ]));

    $jsonNuevaVer = json_decode($rNuevaVer['body'], true);
    $verCreadaId = (int) ($jsonNuevaVer['version']['id'] ?? 0);

    // Conmutar versión activa a la nueva versión creada
    $rActivar = curlRequest("{$baseUrl}/api/documentos/plantillas/1/versiones/{$verCreadaId}/activar", 'POST', [
        "Cookie: {$cookieSession}",
        'Content-Type: application/json',
        'Accept: application/json',
        "X-CSRF-Token: {$csrfToken}",
    ]);

    $v2Ok = ($rNuevaVer['code'] === 201 && $verCreadaId > 0 && $rActivar['code'] === 200);
    checkE2E('E2E-DOC-12', 'Publicación de versión inmutable V2 (HTTP 201) y activación atómica en InnoDB (HTTP 200)', $v2Ok, "Code: {$rNuevaVer['code']} / {$rActivar['code']}");

    // Restaurar versión 1 como activa
    $pdo->exec("UPDATE documento_plantilla_versiones SET es_activa = 0 WHERE plantilla_id = 1");
    $pdo->exec("UPDATE documento_plantilla_versiones SET es_activa = 1 WHERE id = 1 AND plantilla_id = 1");
    $pdo->exec("DELETE FROM documento_plantilla_versiones WHERE id = {$verCreadaId}");

    // -------------------------------------------------------------------------
    // E2E-DOC-13: Anulación formal de documento emitido preservando histórico
    // -------------------------------------------------------------------------
    $rAnular = curlRequest("{$baseUrl}/api/documentos/{$docCreadoId}/anular", 'POST', [
        "Cookie: {$cookieSession}",
        'Content-Type: application/json',
        'Accept: application/json',
        "X-CSRF-Token: {$csrfToken}",
    ], json_encode([
        'motivo' => 'Anulación voluntaria por acuerdo bilateral entre partes',
    ]));

    $jsonAnular = json_decode($rAnular['body'], true);
    $anularOk = ($rAnular['code'] === 200 && str_contains($jsonAnular['mensaje'] ?? '', 'anulado formalmente'));
    checkE2E('E2E-DOC-13', 'POST /api/documentos/{id}/anular revoca formalmente la validez legal preservando histórico (HTTP 200)', $anularOk, "Code: {$rAnular['code']}");

    // -------------------------------------------------------------------------
    // E2E-DOC-14: Detección de corrupción y regeneración asistida desde snapshot
    // -------------------------------------------------------------------------
    $rutaPdfFisico = dirname(__DIR__) . '/storage/' . str_replace('/', DIRECTORY_SEPARATOR, $docCreado['ruta_archivo_pdf']);
    $respaldoPdf = file_get_contents($rutaPdfFisico);

    // Corromper intencionalmente 1 byte
    file_put_contents($rutaPdfFisico, $respaldoPdf . '_TAMPERED');

    $rVerifCorrupto = curlRequest("{$baseUrl}/api/documentos/{$docCreadoId}/verificar", 'GET', [
        "Cookie: {$cookieSession}",
        'Accept: application/json',
    ]);
    $detectoCorrupto = ($rVerifCorrupto['code'] === 500);

    // Ejecutar regeneración asistida autorizada
    $rRegenerar = curlRequest("{$baseUrl}/api/documentos/{$docCreadoId}/regenerar", 'POST', [
        "Cookie: {$cookieSession}",
        'Content-Type: application/json',
        'Accept: application/json',
        "X-CSRF-Token: {$csrfToken}",
    ], json_encode([
        'motivo' => 'Restauración autorizada tras alerta de integridad',
    ]));

    $jsonRegen = json_decode($rRegenerar['body'], true);
    $regenOk = ($detectoCorrupto &&
        $rRegenerar['code'] === 200 &&
        file_exists($rutaPdfFisico) &&
        hash_file('sha256', $rutaPdfFisico) === ($jsonRegen['documento']['hash_pdf_sha256'] ?? ''));

    checkE2E('E2E-DOC-14', 'Detección de corrupción (HTTP 500) y regeneración controlada desde snapshot_html (HTTP 200)', $regenOk, "Detect: {$rVerifCorrupto['code']}, Regen: {$rRegenerar['code']}");

} catch (\Throwable $e) {
    echo "ERROR CATASTRÓFICO EN SUITE HTTP E2E:\n" . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    $fail++;
} finally {
    // Limpieza segura de usuarios de prueba sin violar FK de auditoría
    if ($adminUsuarioId) {
        $pdo->exec("UPDATE usuarios SET estado = 'BLOQUEADO' WHERE id = {$adminUsuarioId}");
    }
    if ($sinPermisoUsuarioId) {
        $pdo->exec("UPDATE usuarios SET estado = 'BLOQUEADO' WHERE id = {$sinPermisoUsuarioId}");
    }
}

echo "\n====================================================================\n";
echo "RESULTADOS HTTP E2E REAL CONTRA APACHE: {$pass} / 14 PASS\n";
echo "====================================================================\n";

if ($fail > 0) {
    echo "FALLOS E2E DETECTADOS:\n";
    foreach ($errores as $err) {
        echo "  - {$err}\n";
    }
    exit(1);
}

exit(0);
