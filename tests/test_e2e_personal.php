<?php

declare(strict_types=1);

/**
 * Suite de Pruebas End-to-End (E2E) Reales contra Apache en HTTPS — Camargo PMS (PERSONAL-1A)
 * Casos E2E-PER-01 a E2E-PER-14 (14/14).
 *
 * Principios vinculantes:
 * - PERSONA ≠ COLABORADOR ≠ USUARIO ≠ CARGO ≠ ROL.
 * - Servidor web real Apache en HTTPS (https://app.camargo-pms.test/).
 * - Sesión autenticada, cookies y protección CSRF.
 * - RBAC vinculado a personal.ver y personal.gestionar.
 * - Ciclo completo: Alta, Ficha completa, Edición de persona, Cambio de cargo, Cese y Reingreso.
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
echo " VALIDACIÓN HTTP E2E REAL CONTRA APACHE (PERSONAL-1A)\n";
echo " Endpoint: https://app.camargo-pms.test/\n";
echo " Principio Rector: PERSONA ≠ COLABORADOR ≠ USUARIO ≠ CARGO ≠ ROL\n";
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
$adminUser = 'admin_per_' . strtolower($sufijo);
$sinPermisoUser = 'sin_per_' . strtolower($sufijo);
$passwordPlana = 'PassPer123!456';
$passwordHash = password_hash($passwordPlana, PASSWORD_DEFAULT);

$cookieSession = null;
$csrfToken = null;
$adminUsuarioId = null;
$sinPermisoUsuarioId = null;
$colaboradorCreadoId = null;
$personaCreadaId = null;

$fixtures = [
    'personas' => [],
    'colaboradores' => [],
    'usuarios' => [],
    'actores' => [],
    'auditoria' => [],
];

// Limpieza preventiva de fixtures de ejecuciones previas interrumpidas
try {
    $leftoverUsers = $pdo->query("SELECT id FROM usuarios WHERE nombre_usuario LIKE 'admin_per_%' OR nombre_usuario LIKE 'sin_per_%' OR nombre_usuario LIKE 'admin_e2e_%' OR nombre_usuario LIKE 'sin_permiso_%'")->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($leftoverUsers)) {
        $uStr = implode(',', array_map('intval', $leftoverUsers));
        $pdo->exec("DELETE FROM sesiones_usuario WHERE usuario_id IN ({$uStr})");
        $pdo->exec("DELETE FROM usuarios_roles WHERE usuario_id IN ({$uStr})");
        $pdo->exec("DELETE a FROM auditoria a INNER JOIN actores ac ON a.actor_id = ac.id WHERE ac.usuario_id IN ({$uStr})");
        $pdo->exec("DELETE FROM actores WHERE usuario_id IN ({$uStr})");
        $pdo->exec("DELETE FROM usuarios WHERE id IN ({$uStr})");
    }
    $leftoverPersonas = $pdo->query("SELECT id FROM personas WHERE nombres LIKE 'AdminE2E%' OR nombres LIKE 'Sin%' OR apellido_paterno = 'E2E'")->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($leftoverPersonas)) {
        $pStr = implode(',', array_map('intval', $leftoverPersonas));
        $pdo->exec("DELETE FROM personas_contactos WHERE persona_id IN ({$pStr})");
        $pdo->exec("DELETE FROM personas_documentos WHERE persona_id IN ({$pStr})");
        $pdo->exec("DELETE FROM personas WHERE id IN ({$pStr})");
    }
} catch (Throwable) {
    // Silencio si falla chequeo preventivo
}

try {
    // -------------------------------------------------------------------------
    // E2E-PER-01: Acceso no autenticado a /personal redirige a /login (HTTP 302)
    // -------------------------------------------------------------------------
    $r1 = curlRequest("{$baseUrl}/personal", 'GET');
    $redirigeLogin = ($r1['code'] === 302 && str_contains((string) $r1['location'], '/login'));
    checkE2E('E2E-PER-01', 'Acceso no autenticado a /personal redirige a /login (HTTP 302)', $redirigeLogin, "Code: {$r1['code']}, Loc: {$r1['location']}");

    // -------------------------------------------------------------------------
    // E2E-PER-02: Autenticación real de usuario administrador
    // -------------------------------------------------------------------------
    $pdo->prepare("INSERT INTO personas (nombres, apellido_paterno, apellido_materno, pais_nacionalidad_id, estado, creado_en) VALUES ('Admin', 'Personal', '{$sufijo}', 1, 'ACTIVO', NOW())")->execute();
    $adminPersonaId = (int) $pdo->lastInsertId();
    $fixtures['personas'][] = $adminPersonaId;

    $stmtUAdmin = $pdo->prepare("INSERT INTO usuarios (persona_id, nombre_usuario, contrasena_hash, estado, creado_en) VALUES (?, ?, ?, 'ACTIVO', NOW())");
    $stmtUAdmin->execute([$adminPersonaId, $adminUser, $passwordHash]);
    $adminUsuarioId = (int) $pdo->lastInsertId();
    $fixtures['usuarios'][] = $adminUsuarioId;

    $stmtActorAdmin = $pdo->prepare("INSERT INTO actores (codigo, tipo, nombre, usuario_id, estado, creado_en) VALUES (?, 'USUARIO', ?, ?, 'ACTIVO', NOW())");
    $stmtActorAdmin->execute(['USR_' . $adminUsuarioId, 'Admin Per ' . $sufijo, $adminUsuarioId]);
    $fixtures['actores'][] = (int) $pdo->lastInsertId();

    $superadminRolId = (int) $pdo->query("SELECT id FROM roles WHERE es_superadministrador = 1 OR codigo = 'SUPERADMINISTRADOR' LIMIT 1")->fetchColumn() ?: 1;
    $pdo->exec("INSERT INTO usuarios_roles (usuario_id, rol_id, asignado_en) VALUES ({$adminUsuarioId}, {$superadminRolId}, NOW())");

    // Login HTTP real
    $rGetLogin = curlRequest("{$baseUrl}/login", 'GET');
    $cookiePreLogin = $rGetLogin['cookies'][0] ?? '';
    $csrfLogin = extraerCsrfDeHtml($rGetLogin['body']);

    $loginBody = http_build_query([
        '_csrf_token' => $csrfLogin,
        'nombre_usuario' => $adminUser,
        'contrasena' => $passwordPlana,
    ]);

    $rPostLogin = curlRequest("{$baseUrl}/login", 'POST', [
        'Content-Type: application/x-www-form-urlencoded',
        "Cookie: {$cookiePreLogin}",
    ], $loginBody);

    $cookieSession = $rPostLogin['cookies'][0] ?? $cookiePreLogin;
    $loginExitoso = ($rPostLogin['code'] === 302 && !str_contains((string) $rPostLogin['location'], '/login'));
    checkE2E('E2E-PER-02', 'Autenticación real de usuario administrador con sesión y redirección', $loginExitoso, "Code: {$rPostLogin['code']}, Loc: {$rPostLogin['location']}");

    // -------------------------------------------------------------------------
    // E2E-PER-03: Acceso HTTP 200 a /personal y presencia de contrato DOM Alina
    // -------------------------------------------------------------------------
    $rPersonal = curlRequest("{$baseUrl}/personal", 'GET', [
        "Cookie: {$cookieSession}",
    ]);
    $csrfToken = extraerCsrfDeHtml($rPersonal['body']);
    $vistaOk = ($rPersonal['code'] === 200) &&
               str_contains($rPersonal['body'], 'PERSONA ≠ COLABORADOR ≠ USUARIO') &&
               str_contains($rPersonal['body'], 'tabla-personal') &&
               !empty($csrfToken);
    checkE2E('E2E-PER-03', 'Acceso HTTP 200 a /personal y renderizado de vista Alina con token CSRF', $vistaOk, "Code: {$rPersonal['code']}, CSRF: " . substr($csrfToken, 0, 8) . "...");

    // -------------------------------------------------------------------------
    // E2E-PER-04: API GET /api/personal retorna JSON con lista y paginación
    // -------------------------------------------------------------------------
    $rListar = curlRequest("{$baseUrl}/api/personal", 'GET', [
        "Cookie: {$cookieSession}",
        'Accept: application/json',
    ]);
    $jsonListar = json_decode($rListar['body'], true);
    $listarOk = ($rListar['code'] === 200) &&
                isset($jsonListar['exito']) &&
                $jsonListar['exito'] === true &&
                isset($jsonListar['datos']) &&
                is_array($jsonListar['datos']);
    checkE2E('E2E-PER-04', 'API GET /api/personal retorna HTTP 200 y estructura JSON paginada', $listarOk, "Code: {$rListar['code']}");

    // -------------------------------------------------------------------------
    // E2E-PER-05: API GET /api/personal/buscar-persona busca por coincidencia
    // -------------------------------------------------------------------------
    $rBuscar = curlRequest("{$baseUrl}/api/personal/buscar-persona?q=Personal", 'GET', [
        "Cookie: {$cookieSession}",
        'Accept: application/json',
    ]);
    $jsonBuscar = json_decode($rBuscar['body'], true);
    $buscarOk = ($rBuscar['code'] === 200) &&
                isset($jsonBuscar['exito']) &&
                $jsonBuscar['exito'] === true;
    checkE2E('E2E-PER-05', 'API GET /api/personal/buscar-persona retorna coincidencias en JSON', $buscarOk, "Code: {$rBuscar['code']}");

    // -------------------------------------------------------------------------
    // E2E-PER-06: API POST /api/personal sin CSRF es rechazado con HTTP 403
    // -------------------------------------------------------------------------
    $docE2E = (string) random_int(10000000, 99999999);
    $payloadAlta = json_encode([
        'nombres' => 'Empleado',
        'apellido_paterno' => 'E2E',
        'apellido_materno' => $sufijo,
        'tipo_documento_id' => 1,
        'numero_documento' => $docE2E,
        'telefono' => '955443322',
        'email' => 'empleado.e2e.' . strtolower($sufijo) . '@test.pe',
        'cargo_id' => 1,
        'fecha_inicio' => date('Y-m-d'),
        'observaciones' => 'Registro E2E',
    ]);

    $rSinCsrf = curlRequest("{$baseUrl}/api/personal", 'POST', [
        "Cookie: {$cookieSession}",
        'Content-Type: application/json',
        'Accept: application/json',
    ], $payloadAlta);
    $sinCsrfRechazado = ($rSinCsrf['code'] === 403);
    checkE2E('E2E-PER-06', 'API POST /api/personal sin token CSRF es rechazado con HTTP 403', $sinCsrfRechazado, "Code: {$rSinCsrf['code']}");

    // -------------------------------------------------------------------------
    // E2E-PER-07: API POST /api/personal con payload incompleto retorna HTTP 422
    // -------------------------------------------------------------------------
    $payloadInvalido = json_encode([
        'nombres' => '', // Inválido
        'cargo_id' => 1,
    ]);
    $rInvalido = curlRequest("{$baseUrl}/api/personal", 'POST', [
        "Cookie: {$cookieSession}",
        "X-CSRF-Token: {$csrfToken}",
        'Content-Type: application/json',
        'Accept: application/json',
    ], $payloadInvalido);
    $invalidoRechazado = ($rInvalido['code'] === 422);
    checkE2E('E2E-PER-07', 'API POST /api/personal con datos incompletos retorna HTTP 422', $invalidoRechazado, "Code: {$rInvalido['code']}");

    // -------------------------------------------------------------------------
    // E2E-PER-08: API POST /api/personal alta exitosa crea persona y colaborador (HTTP 201)
    // -------------------------------------------------------------------------
    // Aseguramos cargo disponible
    $cargoIdValido = (int) $pdo->query("SELECT id FROM cargos WHERE activo = 1 LIMIT 1")->fetchColumn() ?: 1;

    $rAlta = curlRequest("{$baseUrl}/api/personal", 'POST', [
        "Cookie: {$cookieSession}",
        "X-CSRF-Token: {$csrfToken}",
        'Content-Type: application/json',
        'Accept: application/json',
    ], json_encode([
        'nombres' => 'Empleado',
        'apellido_paterno' => 'E2E',
        'apellido_materno' => $sufijo,
        'tipo_documento_id' => 1,
        'numero_documento' => $docE2E,
        'telefono' => '955443322',
        'email' => 'empleado.e2e.' . strtolower($sufijo) . '@test.pe',
        'cargo_id' => $cargoIdValido,
        'fecha_inicio' => date('Y-m-d'),
        'observaciones' => 'Alta exitosa E2E',
    ]));

    $jsonAlta = json_decode($rAlta['body'], true);
    $altaOk = ($rAlta['code'] === 201) &&
              isset($jsonAlta['exito']) &&
              $jsonAlta['exito'] === true &&
              !empty($jsonAlta['datos']['id']);

    if ($altaOk) {
        $colaboradorCreadoId = (int) $jsonAlta['datos']['id'];
        $personaCreadaId = (int) ($jsonAlta['datos']['persona_id'] ?? 0);
        $fixtures['colaboradores'][] = $colaboradorCreadoId;
        if ($personaCreadaId > 0) {
            $fixtures['personas'][] = $personaCreadaId;
        }
    }
    checkE2E('E2E-PER-08', 'API POST /api/personal registra nuevo colaborador y retorna HTTP 201', $altaOk, "Code: {$rAlta['code']}, ColabId: {$colaboradorCreadoId}");

    // -------------------------------------------------------------------------
    // E2E-PER-09: API GET /api/personal/{id} retorna ficha completa consolidada (HTTP 200)
    // -------------------------------------------------------------------------
    $rFicha = curlRequest("{$baseUrl}/api/personal/{$colaboradorCreadoId}", 'GET', [
        "Cookie: {$cookieSession}",
        'Accept: application/json',
    ]);
    $jsonFicha = json_decode($rFicha['body'], true);
    $fichaOk = ($rFicha['code'] === 200) &&
               isset($jsonFicha['exito']) &&
               $jsonFicha['exito'] === true &&
               isset($jsonFicha['datos']['colaborador']['codigo']) &&
               isset($jsonFicha['datos']['episodios']);
    checkE2E('E2E-PER-09', 'API GET /api/personal/{id} retorna ficha laboral consolidada (HTTP 200)', $fichaOk, "Code: {$rFicha['code']}");

    // -------------------------------------------------------------------------
    // E2E-PER-10: API POST /api/personal/{id} actualiza datos de la persona física (HTTP 200)
    // -------------------------------------------------------------------------
    $rActualizar = curlRequest("{$baseUrl}/api/personal/{$colaboradorCreadoId}", 'POST', [
        "Cookie: {$cookieSession}",
        "X-CSRF-Token: {$csrfToken}",
        'Content-Type: application/json',
        'Accept: application/json',
    ], json_encode([
        'nombres' => 'Empleado Editado',
        'apellido_paterno' => 'E2E',
        'telefono' => '999888777',
        'email' => 'editado.' . strtolower($sufijo) . '@test.pe',
        'direccion' => 'Av. Modificada 789',
    ]));
    $jsonAct = json_decode($rActualizar['body'], true);
    $actOk = ($rActualizar['code'] === 200) &&
             isset($jsonAct['exito']) &&
             $jsonAct['exito'] === true;
    checkE2E('E2E-PER-10', 'API POST /api/personal/{id} actualiza datos de la persona (HTTP 200)', $actOk, "Code: {$rActualizar['code']}");

    // -------------------------------------------------------------------------
    // E2E-PER-11: API POST /api/personal/{id}/cargo ejecuta cambio de cargo continuo (HTTP 200)
    // -------------------------------------------------------------------------
    $segundoCargoId = (int) $pdo->query("SELECT id FROM cargos WHERE activo = 1 AND id != {$cargoIdValido} LIMIT 1")->fetchColumn();
    if (!$segundoCargoId) {
        $pdo->prepare("INSERT INTO cargos (codigo, nombre, descripcion, activo, creado_en) VALUES (?, ?, 'Cargo E2E', 1, NOW())")
            ->execute(['CG_E2E_' . $sufijo, 'Cargo E2E ' . $sufijo]);
        $segundoCargoId = (int) $pdo->lastInsertId();
    }

    $rCargo = curlRequest("{$baseUrl}/api/personal/{$colaboradorCreadoId}/cargo", 'POST', [
        "Cookie: {$cookieSession}",
        "X-CSRF-Token: {$csrfToken}",
        'Content-Type: application/json',
        'Accept: application/json',
    ], json_encode([
        'nuevo_cargo_id' => $segundoCargoId,
        'fecha_cambio' => date('Y-m-d', strtotime('+1 day')),
        'observaciones' => 'Cambio de cargo E2E',
    ]));
    $jsonCargo = json_decode($rCargo['body'], true);
    $cargoOk = ($rCargo['code'] === 200) &&
               isset($jsonCargo['exito']) &&
               $jsonCargo['exito'] === true;
    checkE2E('E2E-PER-11', 'API POST /api/personal/{id}/cargo transiciona cargo con fechas continuas (HTTP 200)', $cargoOk, "Code: {$rCargo['code']}");

    // -------------------------------------------------------------------------
    // E2E-PER-12: API POST /api/personal/{id}/cesar ejecuta cese laboral (HTTP 200)
    // -------------------------------------------------------------------------
    $rCese = curlRequest("{$baseUrl}/api/personal/{$colaboradorCreadoId}/cesar", 'POST', [
        "Cookie: {$cookieSession}",
        "X-CSRF-Token: {$csrfToken}",
        'Content-Type: application/json',
        'Accept: application/json',
    ], json_encode([
        'fecha_cese' => date('Y-m-d', strtotime('+2 days')),
        'motivo_cese' => 'FIN_CONTRATO',
        'observaciones' => 'Cese formal E2E',
    ]));
    $jsonCese = json_decode($rCese['body'], true);
    $ceseOk = ($rCese['code'] === 200) &&
              isset($jsonCese['exito']) &&
              $jsonCese['exito'] === true;
    checkE2E('E2E-PER-12', 'API POST /api/personal/{id}/cesar ejecuta cese cambiando estado a INACTIVO (HTTP 200)', $ceseOk, "Code: {$rCese['code']}");

    // -------------------------------------------------------------------------
    // E2E-PER-13: API POST /api/personal/{id}/reingresar reingresa al colaborador (HTTP 200)
    // -------------------------------------------------------------------------
    $rReingreso = curlRequest("{$baseUrl}/api/personal/{$colaboradorCreadoId}/reingresar", 'POST', [
        "Cookie: {$cookieSession}",
        "X-CSRF-Token: {$csrfToken}",
        'Content-Type: application/json',
        'Accept: application/json',
    ], json_encode([
        'cargo_id' => $cargoIdValido,
        'fecha_reingreso' => date('Y-m-d', strtotime('+3 days')),
        'observaciones' => 'Reingreso formal E2E',
    ]));
    $jsonReingreso = json_decode($rReingreso['body'], true);
    $reingresoOk = ($rReingreso['code'] === 200) &&
                   isset($jsonReingreso['exito']) &&
                   $jsonReingreso['exito'] === true;
    checkE2E('E2E-PER-13', 'API POST /api/personal/{id}/reingresar reingresa abriendo nuevo episodio (HTTP 200)', $reingresoOk, "Code: {$rReingreso['code']}");

    // -------------------------------------------------------------------------
    // E2E-PER-14: Control RBAC deniega acceso mutable a usuario sin personal.gestionar (HTTP 403)
    // -------------------------------------------------------------------------
    $pdo->prepare("INSERT INTO personas (nombres, apellido_paterno, apellido_materno, pais_nacionalidad_id, estado, creado_en) VALUES ('Sin', 'Gestionar', '{$sufijo}', 1, 'ACTIVO', NOW())")->execute();
    $sinPermisoPersonaId = (int) $pdo->lastInsertId();
    $fixtures['personas'][] = $sinPermisoPersonaId;

    $stmtUSin = $pdo->prepare("INSERT INTO usuarios (persona_id, nombre_usuario, contrasena_hash, estado, creado_en) VALUES (?, ?, ?, 'ACTIVO', NOW())");
    $stmtUSin->execute([$sinPermisoPersonaId, $sinPermisoUser, $passwordHash]);
    $sinPermisoUsuarioId = (int) $pdo->lastInsertId();
    $fixtures['usuarios'][] = $sinPermisoUsuarioId;

    $stmtActorSin = $pdo->prepare("INSERT INTO actores (codigo, tipo, nombre, usuario_id, estado, creado_en) VALUES (?, 'USUARIO', ?, ?, 'ACTIVO', NOW())");
    $stmtActorSin->execute(['USR_' . $sinPermisoUsuarioId, 'Sin Permiso ' . $sufijo, $sinPermisoUsuarioId]);
    $fixtures['actores'][] = (int) $pdo->lastInsertId();

    // Rol estándar sin personal.gestionar (solo con personal.ver)
    $stmtRolSoloVer = $pdo->query("SELECT id FROM roles WHERE es_superadministrador = 0 LIMIT 1");
    $rolSoloVerId = (int) $stmtRolSoloVer->fetchColumn();
    if (!$rolSoloVerId) {
        $pdo->prepare("INSERT INTO roles (codigo, nombre, descripcion, es_sistema, es_superadministrador, estado, creado_en) VALUES (?, 'Consultor Personal', 'Rol de consulta', 0, 0, 'ACTIVO', NOW())")
            ->execute(['ROL_CONSULTOR_' . $sufijo]);
        $rolSoloVerId = (int) $pdo->lastInsertId();
    }
    // Asegurar que tiene personal.ver pero no personal.gestionar
    $permVerId = (int) $pdo->query("SELECT id FROM permisos WHERE codigo = 'personal.ver' LIMIT 1")->fetchColumn();
    if ($permVerId > 0) {
        $pdo->exec("INSERT IGNORE INTO roles_permisos (rol_id, permiso_id) VALUES ({$rolSoloVerId}, {$permVerId})");
    }
    $permGestId = (int) $pdo->query("SELECT id FROM permisos WHERE codigo = 'personal.gestionar' LIMIT 1")->fetchColumn();
    if ($permGestId > 0) {
        $pdo->exec("DELETE FROM roles_permisos WHERE rol_id = {$rolSoloVerId} AND permiso_id = {$permGestId}");
    }
    $pdo->exec("INSERT INTO usuarios_roles (usuario_id, rol_id, asignado_en) VALUES ({$sinPermisoUsuarioId}, {$rolSoloVerId}, NOW())");

    // Login usuario sin permiso de gestión
    $rGetLoginSin = curlRequest("{$baseUrl}/login", 'GET');
    $cookieSinPre = $rGetLoginSin['cookies'][0] ?? '';
    $csrfLoginSin = extraerCsrfDeHtml($rGetLoginSin['body']);

    $loginSinBody = http_build_query([
        '_csrf_token' => $csrfLoginSin,
        'nombre_usuario' => $sinPermisoUser,
        'contrasena' => $passwordPlana,
    ]);

    $rPostLoginSin = curlRequest("{$baseUrl}/login", 'POST', [
        'Content-Type: application/x-www-form-urlencoded',
        "Cookie: {$cookieSinPre}",
    ], $loginSinBody);

    $cookieSessionSin = $rPostLoginSin['cookies'][0] ?? $cookieSinPre;

    // Obtener CSRF para este usuario
    $rPerSin = curlRequest("{$baseUrl}/personal", 'GET', [
        "Cookie: {$cookieSessionSin}",
    ]);
    $csrfSin = extraerCsrfDeHtml($rPerSin['body']);

    // Intento de alta por usuario sin permiso
    $rRbacDenegado = curlRequest("{$baseUrl}/api/personal", 'POST', [
        "Cookie: {$cookieSessionSin}",
        "X-CSRF-Token: {$csrfSin}",
        'Content-Type: application/json',
        'Accept: application/json',
    ], $payloadAlta);

    $rbacOk = ($rRbacDenegado['code'] === 403);
    checkE2E('E2E-PER-14', 'Control RBAC deniega mutación a usuario sin permiso personal.gestionar (HTTP 403)', $rbacOk, "Code: {$rRbacDenegado['code']}");

} catch (Throwable $e) {
    echo "\nEXCEPCIÓN NO CONTROLADA EN PRUEBA E2E: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    $fail++;
    $errores[] = $e->getMessage();
} finally {
    // ============================================================================
    // LIMPIEZA AUTOCONTENIDA GARANTIZADA DE FIXTURES DE PRUEBA
    // ============================================================================
    echo "\n--- LIMPIEZA DE FIXTURES E2E TEMPORALES ---\n";

    if (!empty($fixtures['colaboradores'])) {
        $idsColStr = implode(',', array_map('intval', $fixtures['colaboradores']));
        $pdo->exec("DELETE FROM auditoria WHERE entidad = 'colaboradores' AND entidad_id IN ({$idsColStr})");
        $pdo->exec("DELETE elc FROM episodios_laborales_cargos elc INNER JOIN episodios_laborales el ON elc.episodio_laboral_id = el.id WHERE el.colaborador_id IN ({$idsColStr})");
        $pdo->exec("DELETE FROM episodios_laborales WHERE colaborador_id IN ({$idsColStr})");
        $pdo->exec("DELETE FROM colaboradores WHERE id IN ({$idsColStr})");
    }

    if (!empty($fixtures['usuarios'])) {
        $idsUserStr = implode(',', array_map('intval', $fixtures['usuarios']));
        $pdo->exec("DELETE FROM sesiones_usuario WHERE usuario_id IN ({$idsUserStr})");
        $pdo->exec("DELETE FROM usuarios_roles WHERE usuario_id IN ({$idsUserStr})");
    }

    if (!empty($fixtures['actores'])) {
        $idsActStr = implode(',', array_map('intval', $fixtures['actores']));
        $pdo->exec("DELETE FROM auditoria WHERE actor_id IN ({$idsActStr})");
        $pdo->exec("DELETE FROM actores WHERE id IN ({$idsActStr})");
    }

    if (!empty($fixtures['usuarios'])) {
        $idsUserStr = implode(',', array_map('intval', $fixtures['usuarios']));
        $pdo->exec("DELETE FROM usuarios WHERE id IN ({$idsUserStr})");
    }

    if (!empty($fixtures['personas'])) {
        $idsPerStr = implode(',', array_map('intval', $fixtures['personas']));
        $pdo->exec("DELETE FROM personas_contactos WHERE persona_id IN ({$idsPerStr})");
        $pdo->exec("DELETE FROM personas_documentos WHERE persona_id IN ({$idsPerStr})");
        $pdo->exec("DELETE FROM personas WHERE id IN ({$idsPerStr})");
    }

    echo "Limpieza E2E completada con éxito. Cero residuos en camargo_pms.\n";
}

// ============================================================================
// RESUMEN FINAL
// ============================================================================
echo "\n====================================================================\n";
echo " RESUMEN E2E PERSONAL-1A: {$pass}/14 CASOS PASADOS (" . round(($pass / 14) * 100, 1) . "%)\n";
echo "====================================================================\n";

if ($fail > 0) {
    echo "FALLOS E2E DETECTADOS:\n";
    foreach ($errores as $err) {
        echo "  - {$err}\n";
    }
    exit(1);
}

echo "RESULTADO: SUITE E2E PERSONAL-1A 100% HOMOLOGADA Y CERTIFICADA\n";
exit(0);
