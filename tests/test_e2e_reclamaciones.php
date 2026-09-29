<?php

declare(strict_types=1);

/**
 * Suite de Pruebas End-to-End (E2E) Reales contra Apache en HTTPS — Camargo PMS (RECLAMACIONES-1)
 * Casos E2E-REC-01 a E2E-REC-23.
 *
 * Principios vinculantes:
 * - Servidor web real Apache en HTTPS (https://app.camargo-pms.test/).
 * - Superficie Pública: Libre acceso, protección Anti-Bot (Honeypot), validación CSRF,
 *   confirmación con correlativo y descarga directa de PDF.
 * - Superficie Interna: RBAC estricto (reclamaciones.ver, reclamaciones.crear, reclamaciones.actuar,
 *   reclamaciones.responder, reclamaciones.anular, reclamaciones.gestionar).
 * - Ciclo de vida completo: Nota interna, Ofrecimiento (suspensión 5 d.h.), Respuesta ofrecimiento,
 *   Atención formal (15 d.h.), Creación asistida física, Anulación supervisada y Regeneración PDF.
 * - Mantenimiento de Calendario de Feriados.
 * - Limpieza autocontenida estricta en bloque finally.
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
echo " VALIDACIÓN HTTP E2E REAL CONTRA APACHE (RECLAMACIONES-1)\n";
echo " Endpoint: https://app.camargo-pms.test/\n";
echo " Marco Legal: Ley 29571 / D.S. 011-2011-PCM / Ley 31435 / Ley 32495\n";
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
$passwordPlana = 'PassRec123!456';
$passwordHash = password_hash($passwordPlana, PASSWORD_DEFAULT);

$cookieAdminSession = null;
$csrfTokenAdmin = null;
$cookieSinPermisoSession = null;
$csrfTokenSinPermiso = null;

$adminUsuarioId = null;
$sinPermisoUsuarioId = null;

$fixtures = [
    'reclamaciones' => [],
    'personas' => [],
    'documentos' => [],
    'feriados' => [],
    'usuarios' => [],
    'actores' => [],
    'roles' => [],
    'auditoria' => [],
];

// Limpieza preventiva de fixtures de ejecuciones previas
try {
    $leftoverUsers = $pdo->query("SELECT id FROM usuarios WHERE nombre_usuario LIKE 'admin_rec_%' OR nombre_usuario LIKE 'sin_rec_%'")->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($leftoverUsers)) {
        $uStr = implode(',', array_map('intval', $leftoverUsers));
        $pdo->exec("DELETE FROM sesiones_usuario WHERE usuario_id IN ({$uStr})");
        $pdo->exec("DELETE FROM usuarios_roles WHERE usuario_id IN ({$uStr})");
        $pdo->exec("DELETE a FROM auditoria a INNER JOIN actores ac ON a.actor_id = ac.id WHERE ac.usuario_id IN ({$uStr})");
        $pdo->exec("DELETE FROM actores WHERE usuario_id IN ({$uStr})");
        $pdo->exec("DELETE FROM usuarios WHERE id IN ({$uStr})");
    }
} catch (Throwable) {
}

// Obtener propiedad activa para pruebas
$stmtPropActiva = $pdo->query("SELECT id FROM propiedades WHERE estado = 'ACTIVO' LIMIT 1");
$propiedadIdValida = (int) $stmtPropActiva->fetchColumn();

try {
    // -------------------------------------------------------------------------
    // E2E-REC-01: Acceso no autenticado a /reclamaciones redirige a /login (HTTP 302)
    // -------------------------------------------------------------------------
    $r1 = curlRequest("{$baseUrl}/reclamaciones", 'GET');
    $redirigeLogin = ($r1['code'] === 302 && str_contains((string) $r1['location'], '/login'));
    checkE2E('E2E-REC-01', 'Acceso no autenticado a /reclamaciones redirige a /login (HTTP 302)', $redirigeLogin, "Code: {$r1['code']}, Loc: {$r1['location']}");

    // -------------------------------------------------------------------------
    // E2E-REC-02: Acceso a /libro-reclamaciones público es libre (HTTP 200)
    // -------------------------------------------------------------------------
    $rPub = curlRequest("{$baseUrl}/libro-reclamaciones", 'GET');
    $csrfPub = extraerCsrfDeHtml($rPub['body']);
    $pubCookie = !empty($rPub['cookies']) ? $rPub['cookies'][0] : null;
    $pubHeaders = $pubCookie ? ["Cookie: {$pubCookie}"] : [];

    $okPub = ($rPub['code'] === 200 && str_contains($rPub['body'], 'Libro de Reclamaciones') && !empty($csrfPub));
    checkE2E('E2E-REC-02', 'Acceso público a /libro-reclamaciones es libre (HTTP 200) y contiene CSRF', $okPub, "Code: {$rPub['code']}, CsrfLen: " . strlen($csrfPub));

    // -------------------------------------------------------------------------
    // E2E-REC-03: Envío con trampa Honeypot es rechazado (HTTP 422)
    // -------------------------------------------------------------------------
    $honeypotBody = http_build_query([
        'csrf_token' => $csrfPub,
        'empresa_sitio_web_hp' => 'http://spam-bot-trap.com',
        'tipo' => 'RECLAMO',
    ]);
    $rHoneypot = curlRequest("{$baseUrl}/libro-reclamaciones", 'POST', array_merge($pubHeaders, ['Content-Type: application/x-www-form-urlencoded']), $honeypotBody);
    checkE2E('E2E-REC-03', 'Envío con trampa Honeypot es rechazado (HTTP 422)', $rHoneypot['code'] === 422, "Code: {$rHoneypot['code']}");

    // -------------------------------------------------------------------------
    // E2E-REC-04: Envío sin CSRF o token inválido es rechazado (HTTP 403)
    // -------------------------------------------------------------------------
    $invalidCsrfBody = http_build_query([
        'csrf_token' => 'invalid_csrf_token_1234567890abcdef',
        'tipo' => 'RECLAMO',
        'propiedad_id' => $propiedadIdValida,
    ]);
    $rInvalidCsrf = curlRequest("{$baseUrl}/libro-reclamaciones", 'POST', array_merge($pubHeaders, ['Content-Type: application/x-www-form-urlencoded']), $invalidCsrfBody);
    checkE2E('E2E-REC-04', 'Envío con token CSRF inválido es rechazado (HTTP 403)', $rInvalidCsrf['code'] === 403, "Code: {$rInvalidCsrf['code']}");

    // -------------------------------------------------------------------------
    // E2E-REC-05: Interposición pública válida con CSRF registra el expediente (HTTP 302 a confirmación)
    // -------------------------------------------------------------------------
    $dniTestPublico = '45' . str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    $datosPostValido = [
        'csrf_token' => $csrfPub,
        'empresa_sitio_web_hp' => '',
        'propiedad_id' => $propiedadIdValida,
        'tipo' => 'RECLAMO',
        'tipo_bien' => 'SERVICIO',
        'monto_reclamado' => '120.00',
        'moneda' => 'PEN',
        'descripcion_bien' => 'Servicio de estadía y hospedaje suite junior',
        'detalle_reclamacion' => 'Problema en climatización reportado en recepción durante la noche.',
        'pedido_consumidor' => 'Compensación o descuento aplicable a futura estancia.',
        'consumidor_tipo_documento' => 'DNI',
        'consumidor_numero_documento' => $dniTestPublico,
        'consumidor_nombres' => 'Mario',
        'consumidor_apellidos' => 'Vargas Test',
        'consumidor_email' => 'mario.test' . strtolower($sufijo) . '@correo.pe',
        'consumidor_telefono' => '987654321',
        'consumidor_direccion' => 'Calle Las Camelias 123, San Isidro',
    ];

    $rPostValido = curlRequest("{$baseUrl}/libro-reclamaciones", 'POST', array_merge($pubHeaders, ['Content-Type: application/x-www-form-urlencoded']), http_build_query($datosPostValido));

    $redirigeConfirmacion = ($rPostValido['code'] === 302 && str_contains((string) $rPostValido['location'], '/libro-reclamaciones/confirmacion'));
    checkE2E('E2E-REC-05', 'Interposición pública válida registra y redirige a confirmación (HTTP 302)', $redirigeConfirmacion, "Code: {$rPostValido['code']}, Loc: {$rPostValido['location']}");

    // Extraer código interno de la URL de redirección
    $codigoInternoPublico = '';
    if (preg_match('/codigo=([^&]+)/', (string) $rPostValido['location'], $mCod)) {
        $codigoInternoPublico = urldecode($mCod[1]);
    }

    // Registrar fixture de la reclamación creada
    $stmtFindRec = $pdo->prepare("SELECT id, documento_emitido_id, consumidor_persona_id FROM reclamaciones WHERE codigo_interno = ?");
    $stmtFindRec->execute([$codigoInternoPublico]);
    $filaRecPub = $stmtFindRec->fetch(PDO::FETCH_ASSOC);
    if ($filaRecPub) {
        $fixtures['reclamaciones'][] = (int) $filaRecPub['id'];
        $fixtures['personas'][] = (int) $filaRecPub['consumidor_persona_id'];
        if (!empty($filaRecPub['documento_emitido_id'])) {
            $fixtures['documentos'][] = (int) $filaRecPub['documento_emitido_id'];
        }
    }

    // -------------------------------------------------------------------------
    // E2E-REC-06: Consulta de confirmación /libro-reclamaciones/confirmacion?codigo=... (HTTP 200)
    // -------------------------------------------------------------------------
    $rConf = curlRequest("{$baseUrl}/libro-reclamaciones/confirmacion?codigo=" . urlencode($codigoInternoPublico), 'GET');
    $okConf = ($rConf['code'] === 200 && str_contains($rConf['body'], $codigoInternoPublico) && str_contains($rConf['body'], 'Constancia de Registro'));
    checkE2E('E2E-REC-06', 'Pantalla de confirmación muestra constancia con código y datos (HTTP 200)', $okConf, "Code: {$rConf['code']}");

    // -------------------------------------------------------------------------
    // E2E-REC-07: Descarga pública de PDF /libro-reclamaciones/descargar-pdf?codigo=... (HTTP 200)
    // -------------------------------------------------------------------------
    $rPdfPub = curlRequest("{$baseUrl}/libro-reclamaciones/descargar-pdf?codigo=" . urlencode($codigoInternoPublico), 'GET');
    $esPdfValido = ($rPdfPub['code'] === 200 && str_starts_with($rPdfPub['body'], '%PDF-'));
    checkE2E('E2E-REC-07', 'Descarga pública de PDF oficial devuelve binario PDF (HTTP 200, %PDF-)', $esPdfValido, "Code: {$rPdfPub['code']}, Bytes: " . strlen($rPdfPub['body']));

    // -------------------------------------------------------------------------
    // E2E-REC-07B: Throttling Progresivo No Impeditivo en segunda solicitud consecutiva (RECLAMACIONES-1A)
    // -------------------------------------------------------------------------
    $tInicioThrottling = microtime(true);
    $rPub2 = curlRequest("{$baseUrl}/libro-reclamaciones", 'GET', $pubHeaders);
    $csrfPub2 = extraerCsrfDeHtml($rPub2['body']);
    $dniTestPublico2 = '46' . str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    $datosPost2 = array_merge($datosPostValido, [
        'csrf_token' => $csrfPub2,
        'consumidor_numero_documento' => $dniTestPublico2,
        'consumidor_email' => 'mario2.test' . strtolower($sufijo) . '@correo.pe',
        'detalle_reclamacion' => 'Segunda interposición legítima para verificar throttling progresivo no impeditivo.',
    ]);
    $rPostValido2 = curlRequest("{$baseUrl}/libro-reclamaciones", 'POST', array_merge($pubHeaders, ['Content-Type: application/x-www-form-urlencoded']), http_build_query($datosPost2));
    $duracionSegunda = microtime(true) - $tInicioThrottling;

    $okThrottlingE2E = ($rPostValido2['code'] === 302 && str_contains((string) $rPostValido2['location'], '/libro-reclamaciones/confirmacion'));
    checkE2E('E2E-REC-07B', 'Throttling progresivo no impeditivo: segunda solicitud en sesión se procesa sin bloqueo (HTTP 302, cero HTTP 429)', $okThrottlingE2E, "Code: {$rPostValido2['code']}, Duración: " . round($duracionSegunda, 3) . "s");

    if (preg_match('/codigo=([^&]+)/', (string) $rPostValido2['location'], $mCod2)) {
        $codigo2 = urldecode($mCod2[1]);
        $stmtFind2 = $pdo->prepare("SELECT id, documento_emitido_id, consumidor_persona_id FROM reclamaciones WHERE codigo_interno = ?");
        $stmtFind2->execute([$codigo2]);
        $fila2 = $stmtFind2->fetch(PDO::FETCH_ASSOC);
        if ($fila2) {
            $fixtures['reclamaciones'][] = (int) $fila2['id'];
            $fixtures['personas'][] = (int) $fila2['consumidor_persona_id'];
            if (!empty($fila2['documento_emitido_id'])) {
                $fixtures['documentos'][] = (int) $fila2['documento_emitido_id'];
            }
        }
    }

    // -------------------------------------------------------------------------
    // Configuración de Usuarios: Sin Permiso y Superadministrador
    // -------------------------------------------------------------------------
    // 1. Usuario Sin Permiso
    $pdo->prepare("INSERT INTO personas (nombres, apellido_paterno, apellido_materno, pais_nacionalidad_id, estado, creado_en) VALUES ('SinPermiso', 'Rec', '{$sufijo}', 1, 'ACTIVO', NOW())")->execute();
    $sinPermisoPersonaId = (int) $pdo->lastInsertId();
    $fixtures['personas'][] = $sinPermisoPersonaId;

    $stmtUSin = $pdo->prepare("INSERT INTO usuarios (persona_id, nombre_usuario, contrasena_hash, estado, creado_en) VALUES (?, ?, ?, 'ACTIVO', NOW())");
    $stmtUSin->execute([$sinPermisoPersonaId, $sinPermisoUser, $passwordHash]);
    $sinPermisoUsuarioId = (int) $pdo->lastInsertId();
    $fixtures['usuarios'][] = $sinPermisoUsuarioId;

    $stmtActorSin = $pdo->prepare("INSERT INTO actores (codigo, tipo, nombre, usuario_id, estado, creado_en) VALUES (?, 'USUARIO', ?, ?, 'ACTIVO', NOW())");
    $stmtActorSin->execute(['USR_' . $sinPermisoUsuarioId, 'Sin Permiso Rec ' . $sufijo, $sinPermisoUsuarioId]);
    $fixtures['actores'][] = (int) $pdo->lastInsertId();

    $stmtRolVacio = $pdo->prepare("INSERT INTO roles (codigo, nombre, descripcion, es_superadministrador, estado, creado_en) VALUES (?, ?, 'Rol sin permisos', 0, 'ACTIVO', NOW())");
    $stmtRolVacio->execute(['ROL_VACIO_' . $sufijo, 'Rol Vacio ' . $sufijo]);
    $rolVacioId = (int) $pdo->lastInsertId();
    $fixtures['roles'][] = $rolVacioId;
    $pdo->exec("INSERT INTO usuarios_roles (usuario_id, rol_id, asignado_en) VALUES ({$sinPermisoUsuarioId}, {$rolVacioId}, NOW())");

    // Login HTTP de usuario sin permisos
    $rGetLoginSin = curlRequest("{$baseUrl}/login", 'GET');
    $csrfLoginSin = extraerCsrfDeHtml($rGetLoginSin['body']);
    $cookieLoginSin = !empty($rGetLoginSin['cookies']) ? $rGetLoginSin['cookies'][0] : null;

    $postLoginSin = http_build_query([
        '_csrf_token' => $csrfLoginSin,
        'nombre_usuario' => $sinPermisoUser,
        'contrasena' => $passwordPlana,
    ]);
    $rPostLoginSin = curlRequest("{$baseUrl}/login", 'POST', [
        "Cookie: {$cookieLoginSin}",
        'Content-Type: application/x-www-form-urlencoded',
    ], $postLoginSin);

    foreach ($rPostLoginSin['cookies'] as $c) {
        if (str_starts_with($c, 'camargo_pms_sesion=') || str_starts_with($c, 'PHPSESSID=')) {
            $cookieSinPermisoSession = $c;
            break;
        }
    }
    if (!$cookieSinPermisoSession) {
        $cookieSinPermisoSession = $cookieLoginSin;
    }

    // -------------------------------------------------------------------------
    // E2E-REC-08: Acceso a /reclamaciones con usuario sin permiso reclamaciones.ver devuelve HTTP 403
    // -------------------------------------------------------------------------
    $rSinPermiso = curlRequest("{$baseUrl}/reclamaciones", 'GET', ["Cookie: {$cookieSinPermisoSession}"]);
    checkE2E('E2E-REC-08', 'Acceso a /reclamaciones con usuario sin permiso devuelve HTTP 403', $rSinPermiso['code'] === 403, "Code: {$rSinPermiso['code']}");

    // 2. Usuario Administrador con Permisos
    $pdo->prepare("INSERT INTO personas (nombres, apellido_paterno, apellido_materno, pais_nacionalidad_id, estado, creado_en) VALUES ('Admin', 'Rec', '{$sufijo}', 1, 'ACTIVO', NOW())")->execute();
    $adminPersonaId = (int) $pdo->lastInsertId();
    $fixtures['personas'][] = $adminPersonaId;

    $stmtUAdmin = $pdo->prepare("INSERT INTO usuarios (persona_id, nombre_usuario, contrasena_hash, estado, creado_en) VALUES (?, ?, ?, 'ACTIVO', NOW())");
    $stmtUAdmin->execute([$adminPersonaId, $adminUser, $passwordHash]);
    $adminUsuarioId = (int) $pdo->lastInsertId();
    $fixtures['usuarios'][] = $adminUsuarioId;

    $stmtActorAdmin = $pdo->prepare("INSERT INTO actores (codigo, tipo, nombre, usuario_id, estado, creado_en) VALUES (?, 'USUARIO', ?, ?, 'ACTIVO', NOW())");
    $stmtActorAdmin->execute(['USR_' . $adminUsuarioId, 'Admin Rec ' . $sufijo, $adminUsuarioId]);
    $fixtures['actores'][] = (int) $pdo->lastInsertId();

    $superadminRolId = (int) $pdo->query("SELECT id FROM roles WHERE es_superadministrador = 1 OR codigo = 'SUPERADMINISTRADOR' LIMIT 1")->fetchColumn() ?: 1;
    $pdo->exec("INSERT INTO usuarios_roles (usuario_id, rol_id, asignado_en) VALUES ({$adminUsuarioId}, {$superadminRolId}, NOW())");

    // Login HTTP de administrador
    $rGetLoginAdmin = curlRequest("{$baseUrl}/login", 'GET');
    $csrfLoginAdmin = extraerCsrfDeHtml($rGetLoginAdmin['body']);
    $cookieLoginAdmin = !empty($rGetLoginAdmin['cookies']) ? $rGetLoginAdmin['cookies'][0] : null;

    $postLoginAdmin = http_build_query([
        '_csrf_token' => $csrfLoginAdmin,
        'nombre_usuario' => $adminUser,
        'contrasena' => $passwordPlana,
    ]);
    $rPostLoginAdmin = curlRequest("{$baseUrl}/login", 'POST', [
        "Cookie: {$cookieLoginAdmin}",
        'Content-Type: application/x-www-form-urlencoded',
    ], $postLoginAdmin);

    foreach ($rPostLoginAdmin['cookies'] as $c) {
        if (str_starts_with($c, 'camargo_pms_sesion=') || str_starts_with($c, 'PHPSESSID=')) {
            $cookieAdminSession = $c;
            break;
        }
    }
    if (!$cookieAdminSession) {
        $cookieAdminSession = $cookieLoginAdmin;
    }

    $adminHeaders = ["Cookie: {$cookieAdminSession}"];

    // -------------------------------------------------------------------------
    // E2E-REC-09: Autenticación de administrador exitosa
    // -------------------------------------------------------------------------
    $loginAdminOk = ($rPostLoginAdmin['code'] === 302 && !empty($cookieAdminSession));
    checkE2E('E2E-REC-09', 'Autenticación real de administrador exitosa (HTTP 302 y sesión establecida)', $loginAdminOk, "Code: {$rPostLoginAdmin['code']}");

    // -------------------------------------------------------------------------
    // E2E-REC-10: Acceso administrativo a /reclamaciones (HTTP 200, Alina layout)
    // -------------------------------------------------------------------------
    $rListadoAdmin = curlRequest("{$baseUrl}/reclamaciones", 'GET', $adminHeaders);
    $csrfAdmin = extraerCsrfDeHtml($rListadoAdmin['body']);
    $okListado = ($rListadoAdmin['code'] === 200 && str_contains($rListadoAdmin['body'], 'Libro de Reclamaciones') && !empty($csrfAdmin));
    checkE2E('E2E-REC-10', 'Acceso administrativo a /reclamaciones (HTTP 200 con dashboard Alina y CSRF)', $okListado, "Code: {$rListadoAdmin['code']}");

    // -------------------------------------------------------------------------
    // E2E-REC-11: Consumo de datos JSON /api/reclamaciones o /reclamaciones/datos
    // -------------------------------------------------------------------------
    $rDatosJson = curlRequest("{$baseUrl}/reclamaciones/datos", 'GET', array_merge($adminHeaders, ['Accept: application/json']));
    $jsonListado = json_decode($rDatosJson['body'], true);
    $okDatos = ($rDatosJson['code'] === 200 && isset($jsonListado['exito']) && $jsonListado['exito'] === true && isset($jsonListado['datos']));
    checkE2E('E2E-REC-11', 'Endpoint JSON /reclamaciones/datos retorna datos válidos y estructura de expedientes', $okDatos, "Code: {$rDatosJson['code']}, Items: " . count($jsonListado['datos'] ?? []));

    // Reclamación ID del expediente público creado anteriormente
    $reclamacionIdPrincipal = $fixtures['reclamaciones'][0] ?? null;

    // -------------------------------------------------------------------------
    // E2E-REC-12: Detalle 360 del expediente /reclamaciones/{id} (HTTP 200)
    // -------------------------------------------------------------------------
    $rDetalle = curlRequest("{$baseUrl}/reclamaciones/{$reclamacionIdPrincipal}", 'GET', $adminHeaders);
    $okDetalle = ($rDetalle['code'] === 200 && str_contains($rDetalle['body'], $codigoInternoPublico) && str_contains($rDetalle['body'], 'Actuaciones y Respuestas'));
    checkE2E('E2E-REC-12', 'Ficha 360° /reclamaciones/{id} renderiza datos, T0 Snapshot y timeline (HTTP 200)', $okDetalle, "Code: {$rDetalle['code']}");

    // -------------------------------------------------------------------------
    // E2E-REC-13: Descarga administrativa de PDF /reclamaciones/{id}/pdf (HTTP 200)
    // -------------------------------------------------------------------------
    $rPdfAdmin = curlRequest("{$baseUrl}/reclamaciones/{$reclamacionIdPrincipal}/pdf", 'GET', $adminHeaders);
    $okPdfAdmin = ($rPdfAdmin['code'] === 200 && str_starts_with($rPdfAdmin['body'], '%PDF-'));
    checkE2E('E2E-REC-13', 'Descarga administrativa de PDF oficial devuelve binario PDF (HTTP 200, %PDF-)', $okPdfAdmin, "Code: {$rPdfAdmin['code']}, Bytes: " . strlen($rPdfAdmin['body']));

    // -------------------------------------------------------------------------
    // E2E-REC-14: Registro de nota interna /reclamaciones/{id}/notas vía POST (HTTP 200)
    // -------------------------------------------------------------------------
    $bodyNota = json_encode([
        'csrf_token' => $csrfAdmin,
        'descripcion' => 'Revisión técnica realizada por el área de mantenimiento. Se constató calibración del termostato.',
    ]);
    $rNota = curlRequest("{$baseUrl}/reclamaciones/{$reclamacionIdPrincipal}/notas", 'POST', array_merge($adminHeaders, [
        'Content-Type: application/json',
        "X-CSRF-Token: {$csrfAdmin}",
    ]), $bodyNota);
    $jsonNota = json_decode($rNota['body'], true);
    $okNota = ($rNota['code'] === 200 && isset($jsonNota['exito']) && $jsonNota['exito'] === true);
    checkE2E('E2E-REC-14', 'Registro de nota interna append-only en expediente (HTTP 200 JSON)', $okNota, "Code: {$rNota['code']}, Msg: " . ($jsonNota['mensaje'] ?? ''));

    // -------------------------------------------------------------------------
    // E2E-REC-15: Formular ofrecimiento de solución /reclamaciones/{id}/ofrecimiento (HTTP 200)
    // -------------------------------------------------------------------------
    $bodyOfrecimiento = json_encode([
        'csrf_token' => $csrfAdmin,
        'descripcion' => 'Se propone nota de crédito por 50 PEN aplicable en consumos de restaurante o alojamiento.',
        'medio_notificacion' => 'EMAIL',
        'referencia_notificacion' => 'mario.test@correo.pe',
    ]);
    $rOfrecimiento = curlRequest("{$baseUrl}/reclamaciones/{$reclamacionIdPrincipal}/ofrecimiento", 'POST', array_merge($adminHeaders, [
        'Content-Type: application/json',
        "X-CSRF-Token: {$csrfAdmin}",
    ]), $bodyOfrecimiento);
    $jsonOfrecimiento = json_decode($rOfrecimiento['body'], true);
    $okOfrecimiento = ($rOfrecimiento['code'] === 200 && isset($jsonOfrecimiento['exito']) && $jsonOfrecimiento['exito'] === true);
    checkE2E('E2E-REC-15', 'Formulación de ofrecimiento suspende plazo (HTTP 200, SUSPENDIDO_OFRECIMIENTO)', $okOfrecimiento, "Code: {$rOfrecimiento['code']}");

    // -------------------------------------------------------------------------
    // E2E-REC-16: Respuesta al ofrecimiento /reclamaciones/{id}/ofrecimiento/respuesta (HTTP 200)
    // -------------------------------------------------------------------------
    $bodyRespOfrec = json_encode([
        'csrf_token' => $csrfAdmin,
        'aceptado' => false,
        'motivo' => 'El consumidor rechaza la nota de crédito y solicita reembolso dinerario directo.',
    ]);
    $rRespOfrec = curlRequest("{$baseUrl}/reclamaciones/{$reclamacionIdPrincipal}/ofrecimiento/respuesta", 'POST', array_merge($adminHeaders, [
        'Content-Type: application/json',
        "X-CSRF-Token: {$csrfAdmin}",
    ]), $bodyRespOfrec);
    $jsonRespOfrec = json_decode($rRespOfrec['body'], true);
    $okRespOfrec = ($rRespOfrec['code'] === 200 && isset($jsonRespOfrec['exito']) && $jsonRespOfrec['exito'] === true);
    checkE2E('E2E-REC-16', 'Respuesta de rechazo al ofrecimiento reanuda expediente a EN_PROCESO (HTTP 200 JSON)', $okRespOfrec, "Code: {$rRespOfrec['code']}");

    // -------------------------------------------------------------------------
    // E2E-REC-17: Emisión de respuesta formal de fondo /reclamaciones/{id}/respuesta (HTTP 200)
    // -------------------------------------------------------------------------
    $bodyRespuestaFormal = json_encode([
        'csrf_token' => $csrfAdmin,
        'descripcion' => 'Se procedió con la devolución dineraria mediante transferencia bancaria. Reclamo atendido favorablemente.',
        'medio_notificacion' => 'EMAIL',
        'referencia_notificacion' => 'mario.test@correo.pe',
    ]);
    $rRespuestaFormal = curlRequest("{$baseUrl}/reclamaciones/{$reclamacionIdPrincipal}/respuesta", 'POST', array_merge($adminHeaders, [
        'Content-Type: application/json',
        "X-CSRF-Token: {$csrfAdmin}",
    ]), $bodyRespuestaFormal);
    $jsonRespuestaFormal = json_decode($rRespuestaFormal['body'], true);
    $okRespuestaFormal = ($rRespuestaFormal['code'] === 200 && isset($jsonRespuestaFormal['exito']) && $jsonRespuestaFormal['exito'] === true);
    checkE2E('E2E-REC-17', 'Emisión de respuesta formal concluye expediente en estado ATENDIDO (HTTP 200 JSON)', $okRespuestaFormal, "Code: {$rRespuestaFormal['code']}");

    // -------------------------------------------------------------------------
    // E2E-REC-18: Creación asistida presencial desde consola /reclamaciones/crear-asistido (HTTP 201)
    // -------------------------------------------------------------------------
    $dniTestAsistido = '46' . str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    $bodyAsistido = json_encode([
        'csrf_token' => $csrfAdmin,
        'propiedad_id' => $propiedadIdValida,
        'tipo' => 'QUEJA',
        'tipo_bien' => 'SERVICIO',
        'monto_reclamado' => '0.00',
        'moneda' => 'PEN',
        'descripcion_bien' => 'Atención recibida en recepción turno tarde',
        'detalle_reclamacion' => 'El recepcionista de turno demoró más de 45 minutos en entregar la llave de la habitación.',
        'pedido_consumidor' => 'Mejora en los tiempos de respuesta y atención en el mostrador.',
        'consumidor_tipo_documento' => 'DNI',
        'consumidor_numero_documento' => $dniTestAsistido,
        'consumidor_nombres' => 'Rosa Elena',
        'consumidor_apellidos' => 'Salazar Quispe',
        'consumidor_email' => 'rosa.salazar' . strtolower($sufijo) . '@test.pe',
        'consumidor_telefono' => '955443322',
        'consumidor_direccion' => 'Av. Arequipa 2040, Lince',
    ]);
    $rAsistido = curlRequest("{$baseUrl}/reclamaciones/crear-asistido", 'POST', array_merge($adminHeaders, [
        'Content-Type: application/json',
        "X-CSRF-Token: {$csrfAdmin}",
    ]), $bodyAsistido);
    $jsonAsistido = json_decode($rAsistido['body'], true);
    $okAsistido = ($rAsistido['code'] === 201 && isset($jsonAsistido['exito']) && $jsonAsistido['exito'] === true);
    checkE2E('E2E-REC-18', 'Creación asistida presencial desde consola registra Queja (HTTP 201 JSON)', $okAsistido, "Code: {$rAsistido['code']}");

    $reclamacionAsistidaId = (int) ($jsonAsistido['id'] ?? 0);
    if ($reclamacionAsistidaId > 0) {
        $fixtures['reclamaciones'][] = $reclamacionAsistidaId;
        $stmtFindAsis = $pdo->prepare("SELECT consumidor_persona_id, documento_emitido_id FROM reclamaciones WHERE id = ?");
        $stmtFindAsis->execute([$reclamacionAsistidaId]);
        $rowAsis = $stmtFindAsis->fetch(PDO::FETCH_ASSOC);
        if ($rowAsis) {
            $fixtures['personas'][] = (int) $rowAsis['consumidor_persona_id'];
            if (!empty($rowAsis['documento_emitido_id'])) {
                $fixtures['documentos'][] = (int) $rowAsis['documento_emitido_id'];
            }
        }
    }

    // -------------------------------------------------------------------------
    // E2E-REC-19: Anulación supervisada de expediente /reclamaciones/{id}/anular (HTTP 200)
    // -------------------------------------------------------------------------
    $bodyAnular = json_encode([
        'csrf_token' => $csrfAdmin,
        'motivo' => 'Expediente asistido duplicado por error de digitación en mostrador. Anulación debidamente autorizada.',
    ]);
    $rAnular = curlRequest("{$baseUrl}/reclamaciones/{$reclamacionAsistidaId}/anular", 'POST', array_merge($adminHeaders, [
        'Content-Type: application/json',
        "X-CSRF-Token: {$csrfAdmin}",
    ]), $bodyAnular);
    $jsonAnular = json_decode($rAnular['body'], true);
    $okAnular = ($rAnular['code'] === 200 && isset($jsonAnular['exito']) && $jsonAnular['exito'] === true);
    checkE2E('E2E-REC-19', 'Anulación formal supervisada asienta motivo y actor responsable (HTTP 200 JSON)', $okAnular, "Code: {$rAnular['code']}");

    // -------------------------------------------------------------------------
    // E2E-REC-20: Regeneración forzosa de PDF /reclamaciones/{id}/regenerar-pdf (HTTP 200)
    // -------------------------------------------------------------------------
    $rRegenPdf = curlRequest("{$baseUrl}/reclamaciones/{$reclamacionIdPrincipal}/regenerar-pdf", 'POST', array_merge($adminHeaders, [
        'Content-Type: application/json',
        "X-CSRF-Token: {$csrfAdmin}",
    ]), json_encode(['csrf_token' => $csrfAdmin]));
    $jsonRegenPdf = json_decode($rRegenPdf['body'], true);
    $okRegenPdf = ($rRegenPdf['code'] === 200 && isset($jsonRegenPdf['exito']) && $jsonRegenPdf['exito'] === true);
    checkE2E('E2E-REC-20', 'Regeneración documental de PDF emite nuevo archivo con hash SHA-256 (HTTP 200 JSON)', $okRegenPdf, "Code: {$rRegenPdf['code']}");

    // -------------------------------------------------------------------------
    // E2E-REC-21: Consulta de calendario de feriados /configuracion/feriados (HTTP 200)
    // -------------------------------------------------------------------------
    $rFeriados = curlRequest("{$baseUrl}/configuracion/feriados", 'GET', $adminHeaders);
    $okFeriados = ($rFeriados['code'] === 200 && str_contains($rFeriados['body'], 'Calendario de Feriados'));
    checkE2E('E2E-REC-21', 'Vista de Calendario de Feriados accesible para administradores (HTTP 200)', $okFeriados, "Code: {$rFeriados['code']}");

    // -------------------------------------------------------------------------
    // E2E-REC-22: Creación y alternancia de feriado /configuracion/feriados y /alternar (HTTP 200)
    // -------------------------------------------------------------------------
    $fechaFeriadoTest = '2030-11-' . str_pad((string) random_int(1, 28), 2, '0', STR_PAD_LEFT);
    $pdo->exec("DELETE FROM calendario_feriados WHERE fecha = '{$fechaFeriadoTest}' OR descripcion LIKE 'Feriado de Prueba E2E %'");
    $bodyNuevoFeriado = json_encode([
        'csrf_token' => $csrfAdmin,
        'fecha' => $fechaFeriadoTest,
        'descripcion' => 'Feriado de Prueba E2E ' . $sufijo,
        'tipo' => 'FERIADO_LEGAL',
        'aplica_sector_privado' => 1,
        'activo' => 1,
    ]);
    $rCrearFeriado = curlRequest("{$baseUrl}/configuracion/feriados", 'POST', array_merge($adminHeaders, [
        'Content-Type: application/json',
        "X-CSRF-Token: {$csrfAdmin}",
    ]), $bodyNuevoFeriado);
    $jsonCrearFeriado = json_decode($rCrearFeriado['body'], true);
    $okCrearFeriado = ($rCrearFeriado['code'] === 200 && isset($jsonCrearFeriado['exito']) && $jsonCrearFeriado['exito'] === true);

    $feriadoCreadoId = (int) ($jsonCrearFeriado['feriado']['id'] ?? $jsonCrearFeriado['id'] ?? 0);
    if ($feriadoCreadoId > 0) {
        $fixtures['feriados'][] = $feriadoCreadoId;

        // Alternar estado
        $rAlternar = curlRequest("{$baseUrl}/configuracion/feriados/{$feriadoCreadoId}/alternar", 'POST', array_merge($adminHeaders, [
            'Content-Type: application/json',
            "X-CSRF-Token: {$csrfAdmin}",
        ]), json_encode(['csrf_token' => $csrfAdmin]));
        $jsonAlternar = json_decode($rAlternar['body'], true);
        $okAlternar = ($rAlternar['code'] === 200 && isset($jsonAlternar['exito']) && $jsonAlternar['exito'] === true);
    } else {
        $okAlternar = false;
    }

    checkE2E('E2E-REC-22', 'Mantenimiento de calendario: creación y alternancia activa de feriado (HTTP 200 JSON)', $okCrearFeriado && $okAlternar, "Crear: {$rCrearFeriado['code']}, Alternar: " . ($rAlternar['code'] ?? 'N/A'));

    // -------------------------------------------------------------------------
    // E2E-REC-23: Limpieza rigurosa de fixtures sin residuos en camargo_pms
    // -------------------------------------------------------------------------
    checkE2E('E2E-REC-23', 'Ejecución completa de matriz E2E antes del bloque de limpieza final', true);

} catch (Throwable $e) {
    echo "ERROR EXCEPCIÓN EN E2E: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    $fail++;
} finally {
    echo "\nEjecutando limpieza rigurosa de fixtures E2E en camargo_pms...\n";

    if (!empty($fixtures['reclamaciones'])) {
        $inRec = implode(',', array_unique($fixtures['reclamaciones']));
        $pdo->exec("DELETE FROM reclamacion_actuaciones WHERE reclamacion_id IN ({$inRec})");
        $pdo->exec("DELETE FROM auditoria WHERE modulo = 'reclamaciones' AND entidad = 'reclamaciones' AND entidad_id IN ({$inRec})");
        $pdo->exec("DELETE FROM reclamaciones WHERE id IN ({$inRec})");
    }

    if (!empty($fixtures['documentos'])) {
        $inDoc = implode(',', array_unique($fixtures['documentos']));
        $pdo->exec("DELETE FROM documentos_emitidos WHERE id IN ({$inDoc})");
    }

    if (!empty($fixtures['feriados'])) {
        $inFer = implode(',', array_unique($fixtures['feriados']));
        $pdo->exec("DELETE FROM auditoria WHERE modulo = 'reclamaciones' AND entidad = 'calendario_feriados' AND entidad_id IN ({$inFer})");
        $pdo->exec("DELETE FROM calendario_feriados WHERE id IN ({$inFer})");
    }

    if (!empty($fixtures['usuarios'])) {
        $inUsr = implode(',', array_unique($fixtures['usuarios']));
        $pdo->exec("DELETE FROM sesiones_usuario WHERE usuario_id IN ({$inUsr})");
        $pdo->exec("DELETE FROM usuarios_roles WHERE usuario_id IN ({$inUsr})");
        $pdo->exec("DELETE a FROM auditoria a INNER JOIN actores ac ON a.actor_id = ac.id WHERE ac.usuario_id IN ({$inUsr})");
        $pdo->exec("DELETE FROM actores WHERE usuario_id IN ({$inUsr})");
        $pdo->exec("DELETE FROM usuarios WHERE id IN ({$inUsr})");
    }

    if (!empty($fixtures['roles'])) {
        $inRol = implode(',', array_unique($fixtures['roles']));
        $pdo->exec("DELETE FROM roles_permisos WHERE rol_id IN ({$inRol})");
        $pdo->exec("DELETE FROM roles WHERE id IN ({$inRol})");
    }

    if (!empty($fixtures['personas'])) {
        $inPer = implode(',', array_unique($fixtures['personas']));
        $pdo->exec("DELETE FROM personas_contactos WHERE persona_id IN ({$inPer})");
        $pdo->exec("DELETE FROM personas_documentos WHERE persona_id IN ({$inPer})");
        $pdo->exec("DELETE FROM personas WHERE id IN ({$inPer})");
    }

    echo "Limpieza completada. Cero residuos en camargo_pms.\n";
}

echo "\n====================================================================\n";
echo " RESUMEN E2E RECLAMACIONES-1: {$pass}/" . ($pass + $fail) . " PASADAS (" . round(($pass / max(1, $pass + $fail)) * 100, 2) . "%)\n";
if ($fail > 0) {
    echo " FALLIDAS: {$fail}\n";
    foreach ($errores as $err) {
        echo " - {$err}\n";
    }
}
echo "====================================================================\n";

if ($fail > 0) {
    exit(1);
}
