<?php

declare(strict_types=1);

/**
 * Suite de Pruebas End-to-End (E2E) Reales contra Apache en HTTPS — Camargo PMS (CLIENTES-1 / CLIENTES-1A)
 * Casos E2E-CLI-01 a E2E-CLI-18 (18/18).
 *
 * Principios vinculantes:
 * - PERSONA ≠ CLIENTE pero CLIENTE -> PERSONA.
 * - Servidor web real Apache en HTTPS (https://app.camargo-pms.test/).
 * - Sesión autenticada, cookies y protección CSRF.
 * - RBAC vinculado a clientes.ver, clientes.crear, clientes.editar, clientes.bloquear.
 * - Ciclo completo: Alta persona existente, duplicado 409, alta nueva persona atómica,
 *   Ficha 360° completa, edición comercial, bloqueo con/sin motivo y reactivación.
 * - Integración permanente en menú dinámico Alina bajo categoría reservas (CLIENTES-1A).
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
echo " VALIDACIÓN HTTP E2E REAL CONTRA APACHE (CLIENTES-1 / CLIENTES-1A)\n";
echo " Endpoint: https://app.camargo-pms.test/\n";
echo " Principio Rector: PERSONA ≠ CLIENTE pero CLIENTE -> PERSONA\n";
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
$adminUser = 'admin_cli_' . strtolower($sufijo);
$sinPermisoUser = 'sin_cli_' . strtolower($sufijo);
$passwordPlana = 'PassCli123!456';
$passwordHash = password_hash($passwordPlana, PASSWORD_DEFAULT);

$cookieSession = null;
$csrfToken = null;
$adminUsuarioId = null;
$sinPermisoUsuarioId = null;
$clienteCreadoId1 = null;
$clienteCreadoId2 = null;

$fixtures = [
    'clientes' => [],
    'personas' => [],
    'usuarios' => [],
    'actores' => [],
    'roles' => [],
    'auditoria' => [],
];

// Limpieza preventiva de fixtures de ejecuciones previas interrumpidas
try {
    $leftoverUsers = $pdo->query("SELECT id FROM usuarios WHERE nombre_usuario LIKE 'admin_cli_%' OR nombre_usuario LIKE 'sin_cli_%'")->fetchAll(PDO::FETCH_COLUMN);
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

try {
    // -------------------------------------------------------------------------
    // E2E-CLI-01: Acceso no autenticado a /clientes redirige a /login (HTTP 302)
    // -------------------------------------------------------------------------
    $r1 = curlRequest("{$baseUrl}/clientes", 'GET');
    $redirigeLogin = ($r1['code'] === 302 && str_contains((string) $r1['location'], '/login'));
    checkE2E('E2E-CLI-01', 'Acceso no autenticado a /clientes redirige a /login (HTTP 302)', $redirigeLogin, "Code: {$r1['code']}, Loc: {$r1['location']}");

    // -------------------------------------------------------------------------
    // E2E-CLI-02: Autenticación real de usuario administrador
    // -------------------------------------------------------------------------
    $pdo->prepare("INSERT INTO personas (nombres, apellido_paterno, apellido_materno, pais_nacionalidad_id, estado, creado_en) VALUES ('Admin', 'Clientes', '{$sufijo}', 1, 'ACTIVO', NOW())")->execute();
    $adminPersonaId = (int) $pdo->lastInsertId();
    $fixtures['personas'][] = $adminPersonaId;

    $stmtUAdmin = $pdo->prepare("INSERT INTO usuarios (persona_id, nombre_usuario, contrasena_hash, estado, creado_en) VALUES (?, ?, ?, 'ACTIVO', NOW())");
    $stmtUAdmin->execute([$adminPersonaId, $adminUser, $passwordHash]);
    $adminUsuarioId = (int) $pdo->lastInsertId();
    $fixtures['usuarios'][] = $adminUsuarioId;

    $stmtActorAdmin = $pdo->prepare("INSERT INTO actores (codigo, tipo, nombre, usuario_id, estado, creado_en) VALUES (?, 'USUARIO', ?, ?, 'ACTIVO', NOW())");
    $stmtActorAdmin->execute(['USR_' . $adminUsuarioId, 'Admin Cli ' . $sufijo, $adminUsuarioId]);
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
    checkE2E('E2E-CLI-02', 'Autenticación real de usuario administrador con sesión y redirección', $loginExitoso, "Code: {$rPostLogin['code']}, Loc: {$rPostLogin['location']}");

    // -------------------------------------------------------------------------
    // E2E-CLI-03: Acceso HTTP 200 a /clientes y renderizado de vista Alina con CSRF
    // -------------------------------------------------------------------------
    $rClientes = curlRequest("{$baseUrl}/clientes", 'GET', [
        "Cookie: {$cookieSession}",
    ]);
    $csrfToken = extraerCsrfDeHtml($rClientes['body']);
    $vistaOk = ($rClientes['code'] === 200) &&
               str_contains($rClientes['body'], 'PERSONA ≠ CLIENTE') &&
               str_contains($rClientes['body'], 'tabla-clientes') &&
               !empty($csrfToken);
    checkE2E('E2E-CLI-03', 'Acceso HTTP 200 a /clientes y renderizado de vista Alina con token CSRF', $vistaOk, "Code: {$rClientes['code']}, CSRF: " . substr($csrfToken, 0, 8) . "...");

    // -------------------------------------------------------------------------
    // E2E-CLI-04: Usuario sin permisos de clientes recibe HTTP 403 Forbidden
    // -------------------------------------------------------------------------
    $pdo->prepare("INSERT INTO personas (nombres, apellido_paterno, apellido_materno, pais_nacionalidad_id, estado, creado_en) VALUES ('Sin', 'Permiso', '{$sufijo}', 1, 'ACTIVO', NOW())")->execute();
    $sinPermisoPersonaId = (int) $pdo->lastInsertId();
    $fixtures['personas'][] = $sinPermisoPersonaId;

    $stmtUSin = $pdo->prepare("INSERT INTO usuarios (persona_id, nombre_usuario, contrasena_hash, estado, creado_en) VALUES (?, ?, ?, 'ACTIVO', NOW())");
    $stmtUSin->execute([$sinPermisoPersonaId, $sinPermisoUser, $passwordHash]);
    $sinPermisoUsuarioId = (int) $pdo->lastInsertId();
    $fixtures['usuarios'][] = $sinPermisoUsuarioId;

    $stmtActorSin = $pdo->prepare("INSERT INTO actores (codigo, tipo, nombre, usuario_id, estado, creado_en) VALUES (?, 'USUARIO', ?, ?, 'ACTIVO', NOW())");
    $stmtActorSin->execute(['USR_' . $sinPermisoUsuarioId, 'Sin Permiso ' . $sufijo, $sinPermisoUsuarioId]);
    $fixtures['actores'][] = (int) $pdo->lastInsertId();

    // Rol vacío sin permisos
    $pdo->prepare("INSERT INTO roles (codigo, nombre, descripcion, es_superadministrador, estado, creado_en) VALUES (?, 'Rol Vacio E2E', 'Sin permisos', 0, 'ACTIVO', NOW())")->execute(['ROL_VACIO_' . $sufijo]);
    $rolVacioId = (int) $pdo->lastInsertId();
    $fixtures['roles'][] = $rolVacioId;
    $pdo->exec("INSERT INTO usuarios_roles (usuario_id, rol_id, asignado_en) VALUES ({$sinPermisoUsuarioId}, {$rolVacioId}, NOW())");

    $rGetLoginSin = curlRequest("{$baseUrl}/login", 'GET');
    $cookieSinPre = $rGetLoginSin['cookies'][0] ?? '';
    $csrfSin = extraerCsrfDeHtml($rGetLoginSin['body']);

    $rPostLoginSin = curlRequest("{$baseUrl}/login", 'POST', [
        'Content-Type: application/x-www-form-urlencoded',
        "Cookie: {$cookieSinPre}",
    ], http_build_query([
        '_csrf_token' => $csrfSin,
        'nombre_usuario' => $sinPermisoUser,
        'contrasena' => $passwordPlana,
    ]));
    $cookieSessionSin = $rPostLoginSin['cookies'][0] ?? $cookieSinPre;

    $rClientesSin = curlRequest("{$baseUrl}/clientes", 'GET', [
        "Cookie: {$cookieSessionSin}",
    ]);
    $rechazado403 = ($rClientesSin['code'] === 403);
    checkE2E('E2E-CLI-04', 'Usuario sin permiso clientes.ver recibe HTTP 403 Forbidden', $rechazado403, "Code: {$rClientesSin['code']}");

    // -------------------------------------------------------------------------
    // E2E-CLI-05: API GET /api/clientes retorna HTTP 200 y JSON paginado
    // -------------------------------------------------------------------------
    $rListar = curlRequest("{$baseUrl}/api/clientes", 'GET', [
        "Cookie: {$cookieSession}",
        'Accept: application/json',
    ]);
    $jsonListar = json_decode($rListar['body'], true);
    $listarOk = ($rListar['code'] === 200) &&
                isset($jsonListar['exito']) &&
                $jsonListar['exito'] === true &&
                isset($jsonListar['datos']) &&
                is_array($jsonListar['datos']);
    checkE2E('E2E-CLI-05', 'API GET /api/clientes retorna HTTP 200 y estructura JSON paginada', $listarOk, "Code: {$rListar['code']}");

    // -------------------------------------------------------------------------
    // E2E-CLI-06: API POST /api/clientes sin CSRF es rechazado con HTTP 403
    // -------------------------------------------------------------------------
    $rSinCsrf = curlRequest("{$baseUrl}/api/clientes", 'POST', [
        "Cookie: {$cookieSession}",
        'Content-Type: application/json',
        'Accept: application/json',
    ], json_encode([
        'modo_alta' => 'existente',
        'persona_id' => $adminPersonaId,
    ]));
    $sinCsrfRechazado = ($rSinCsrf['code'] === 403);
    checkE2E('E2E-CLI-06', 'API POST /api/clientes sin token CSRF es rechazado con HTTP 403', $sinCsrfRechazado, "Code: {$rSinCsrf['code']}");

    // -------------------------------------------------------------------------
    // E2E-CLI-07: API POST /api/clientes con datos inválidos retorna HTTP 422
    // -------------------------------------------------------------------------
    $rInvalido = curlRequest("{$baseUrl}/api/clientes", 'POST', [
        "Cookie: {$cookieSession}",
        "X-CSRF-Token: {$csrfToken}",
        'Content-Type: application/json',
        'Accept: application/json',
    ], json_encode([
        'modo_alta' => 'existente',
        'persona_id' => 99999999, // Inexistente
    ]));
    $invalidoRechazado = ($rInvalido['code'] === 422);
    checkE2E('E2E-CLI-07', 'API POST /api/clientes con persona inexistente retorna HTTP 422', $invalidoRechazado, "Code: {$rInvalido['code']}");

    // -------------------------------------------------------------------------
    // E2E-CLI-08: API POST /api/clientes vinculando Persona existente (HTTP 201)
    // -------------------------------------------------------------------------
    // Creamos persona base para vincular
    $pdo->prepare("INSERT INTO personas (nombres, apellido_paterno, apellido_materno, pais_nacionalidad_id, estado, creado_en) VALUES ('Persona', 'Existente', '{$sufijo}', 1, 'ACTIVO', NOW())")->execute();
    $personaExistenteId = (int) $pdo->lastInsertId();
    $fixtures['personas'][] = $personaExistenteId;

    $catEstandarId = (int) $pdo->query("SELECT id FROM cliente_categorias WHERE codigo = 'ESTANDAR'")->fetchColumn();

    $rAlta1 = curlRequest("{$baseUrl}/api/clientes", 'POST', [
        "Cookie: {$cookieSession}",
        "X-CSRF-Token: {$csrfToken}",
        'Content-Type: application/json',
        'Accept: application/json',
    ], json_encode([
        'modo_alta' => 'existente',
        'persona_id' => $personaExistenteId,
        'categoria_id' => $catEstandarId,
        'canal_captacion' => 'BOOKING',
        'preferencias' => 'Piso alto y cama king',
        'observaciones' => 'Cliente fidelizado',
    ]));

    $jsonAlta1 = json_decode($rAlta1['body'], true);
    $alta1Ok = ($rAlta1['code'] === 201) &&
               isset($jsonAlta1['exito']) &&
               $jsonAlta1['exito'] === true &&
               isset($jsonAlta1['datos']['id']);

    if ($alta1Ok) {
        $clienteCreadoId1 = (int) $jsonAlta1['datos']['id'];
        $fixtures['clientes'][] = $clienteCreadoId1;
    }
    checkE2E('E2E-CLI-08', 'API POST /api/clientes vinculando Persona existente retorna HTTP 201', $alta1Ok, "Code: {$rAlta1['code']}");

    // -------------------------------------------------------------------------
    // E2E-CLI-09: API POST /api/clientes duplicado 1:1 retorna HTTP 409 Conflict
    // -------------------------------------------------------------------------
    $rDuplicado = curlRequest("{$baseUrl}/api/clientes", 'POST', [
        "Cookie: {$cookieSession}",
        "X-CSRF-Token: {$csrfToken}",
        'Content-Type: application/json',
        'Accept: application/json',
    ], json_encode([
        'modo_alta' => 'existente',
        'persona_id' => $personaExistenteId,
        'categoria_id' => $catEstandarId,
    ]));
    $duplicado409 = ($rDuplicado['code'] === 409);
    checkE2E('E2E-CLI-09', 'API POST /api/clientes duplicado para la misma persona retorna HTTP 409', $duplicado409, "Code: {$rDuplicado['code']}");

    // -------------------------------------------------------------------------
    // E2E-CLI-10: API POST /api/clientes con nueva Persona atómica (HTTP 201)
    // -------------------------------------------------------------------------
    $docE2E = (string) random_int(10000000, 99999999);
    $rAlta2 = curlRequest("{$baseUrl}/api/clientes", 'POST', [
        "Cookie: {$cookieSession}",
        "X-CSRF-Token: {$csrfToken}",
        'Content-Type: application/json',
        'Accept: application/json',
    ], json_encode([
        'modo_alta' => 'nueva',
        'nombres' => 'Clara',
        'apellido_paterno' => 'Mendoza',
        'apellido_materno' => $sufijo,
        'genero' => 'FEMENINO',
        'fecha_nacimiento' => '1992-05-14',
        'tipo_documento_id' => 1,
        'numero_documento' => $docE2E,
        'telefono' => '987654321',
        'email' => 'clara.' . strtolower($sufijo) . '@correo.com',
        'categoria_id' => $catEstandarId,
        'canal_captacion' => 'DIRECTO',
        'preferencias' => 'Vegetariana',
        'observaciones' => 'Alta integral E2E',
    ]));

    $jsonAlta2 = json_decode($rAlta2['body'], true);
    $alta2Ok = ($rAlta2['code'] === 201) &&
               isset($jsonAlta2['exito']) &&
               $jsonAlta2['exito'] === true &&
               isset($jsonAlta2['datos']['id']);

    if ($alta2Ok) {
        $clienteCreadoId2 = (int) $jsonAlta2['datos']['id'];
        $fixtures['clientes'][] = $clienteCreadoId2;
        $personaCreadaId2 = (int) $jsonAlta2['datos']['persona_id'];
        $fixtures['personas'][] = $personaCreadaId2;
    }
    checkE2E('E2E-CLI-10', 'API POST /api/clientes con Persona nueva crea atómicamente Persona y Cliente (HTTP 201)', $alta2Ok, "Code: {$rAlta2['code']}");

    // -------------------------------------------------------------------------
    // E2E-CLI-11: Vista HTTP 200 /clientes/{id} Ficha Integral 360° con 8 pestañas
    // -------------------------------------------------------------------------
    $rFichaHtml = curlRequest("{$baseUrl}/clientes/{$clienteCreadoId2}", 'GET', [
        "Cookie: {$cookieSession}",
    ]);
    $fichaHtmlOk = ($rFichaHtml['code'] === 200) &&
                   str_contains($rFichaHtml['body'], 'tab-resumen') &&
                   str_contains($rFichaHtml['body'], 'tab-reservas') &&
                   str_contains($rFichaHtml['body'], 'tab-estadias') &&
                   str_contains($rFichaHtml['body'], 'tab-arrendamientos') &&
                   str_contains($rFichaHtml['body'], 'tab-cuenta') &&
                   str_contains($rFichaHtml['body'], 'tab-servicios') &&
                   str_contains($rFichaHtml['body'], 'tab-documentos') &&
                   str_contains($rFichaHtml['body'], 'tab-notas');
    checkE2E('E2E-CLI-11', 'Vista HTTP 200 /clientes/{id} Ficha Integral 360° renderiza 8 pestañas', $fichaHtmlOk, "Code: {$rFichaHtml['code']}");

    // -------------------------------------------------------------------------
    // E2E-CLI-12: API GET /api/clientes/{id} retorna JSON Ficha 360° soberana
    // -------------------------------------------------------------------------
    $rFichaApi = curlRequest("{$baseUrl}/api/clientes/{$clienteCreadoId2}", 'GET', [
        "Cookie: {$cookieSession}",
        'Accept: application/json',
    ]);
    $jsonFicha = json_decode($rFichaApi['body'], true);
    $fichaApiOk = ($rFichaApi['code'] === 200) &&
                  isset($jsonFicha['exito']) &&
                  $jsonFicha['exito'] === true &&
                  isset($jsonFicha['datos']['cliente']) &&
                  isset($jsonFicha['datos']['persona']) &&
                  isset($jsonFicha['datos']['estado_cuenta']);
    checkE2E('E2E-CLI-12', 'API GET /api/clientes/{id} retorna HTTP 200 con payload de Ficha 360° soberana', $fichaApiOk, "Code: {$rFichaApi['code']}");

    // -------------------------------------------------------------------------
    // E2E-CLI-13: API PUT /api/clientes/{id} actualiza perfil comercial (HTTP 200)
    // -------------------------------------------------------------------------
    $catVipId = (int) $pdo->query("SELECT id FROM cliente_categorias WHERE codigo = 'VIP'")->fetchColumn();
    $rEdit = curlRequest("{$baseUrl}/api/clientes/{$clienteCreadoId2}", 'PUT', [
        "Cookie: {$cookieSession}",
        "X-CSRF-Token: {$csrfToken}",
        'Content-Type: application/json',
        'Accept: application/json',
    ], json_encode([
        'categoria_id' => $catVipId,
        'canal_captacion' => 'RECOMENDACION',
        'preferencias' => 'Vino tinto de bienvenida',
        'observaciones' => 'Ascendido a VIP por fidelidad',
    ]));
    $jsonEdit = json_decode($rEdit['body'], true);
    $editOk = ($rEdit['code'] === 200) &&
              isset($jsonEdit['exito']) &&
              $jsonEdit['exito'] === true &&
              $jsonEdit['datos']['categoria_codigo'] === 'VIP';
    checkE2E('E2E-CLI-13', 'API PUT /api/clientes/{id} actualiza categoría a VIP y preferencias (HTTP 200)', $editOk, "Code: {$rEdit['code']}");

    // -------------------------------------------------------------------------
    // E2E-CLI-14: API POST /api/clientes/{id}/bloquear sin motivo retorna HTTP 422
    // -------------------------------------------------------------------------
    $rBloqSinMotivo = curlRequest("{$baseUrl}/api/clientes/{$clienteCreadoId2}/bloquear", 'POST', [
        "Cookie: {$cookieSession}",
        "X-CSRF-Token: {$csrfToken}",
        'Content-Type: application/json',
        'Accept: application/json',
    ], json_encode([
        'motivo_bloqueo' => '',
    ]));
    $bloqSinMotivo422 = ($rBloqSinMotivo['code'] === 422);
    checkE2E('E2E-CLI-14', 'API POST /api/clientes/{id}/bloquear sin motivo justificado retorna HTTP 422', $bloqSinMotivo422, "Code: {$rBloqSinMotivo['code']}");

    // -------------------------------------------------------------------------
    // E2E-CLI-15: API POST /api/clientes/{id}/bloquear con motivo bloquea cliente
    // -------------------------------------------------------------------------
    $rBloqConMotivo = curlRequest("{$baseUrl}/api/clientes/{$clienteCreadoId2}/bloquear", 'POST', [
        "Cookie: {$cookieSession}",
        "X-CSRF-Token: {$csrfToken}",
        'Content-Type: application/json',
        'Accept: application/json',
    ], json_encode([
        'motivo_bloqueo' => 'Comportamiento hostil reiterado hacia el personal en estadía previa',
    ]));
    $jsonBloq = json_decode($rBloqConMotivo['body'], true);
    $bloqOk = ($rBloqConMotivo['code'] === 200) &&
              isset($jsonBloq['exito']) &&
              $jsonBloq['exito'] === true &&
              $jsonBloq['datos']['estado'] === 'BLOQUEADO';

    // Comprobamos banner visual en Ficha 360°
    $rFichaBloqHtml = curlRequest("{$baseUrl}/clientes/{$clienteCreadoId2}", 'GET', [
        "Cookie: {$cookieSession}",
    ]);
    $bannerVisible = ($rFichaBloqHtml['code'] === 200) &&
                     str_contains($rFichaBloqHtml['body'], 'CLIENTE BLOQUEADO') &&
                     str_contains($rFichaBloqHtml['body'], 'Comportamiento hostil');
    checkE2E('E2E-CLI-15', 'API POST /api/clientes/{id}/bloquear bloquea cliente y muestra banner de alerta', ($bloqOk && $bannerVisible), "Code: {$rBloqConMotivo['code']}, Banner: " . ($bannerVisible ? 'OK' : 'FAIL'));

    // -------------------------------------------------------------------------
    // E2E-CLI-16: API POST /api/clientes/{id}/desbloquear reactiva cliente a ACTIVO
    // -------------------------------------------------------------------------
    $rReactivar = curlRequest("{$baseUrl}/api/clientes/{$clienteCreadoId2}/desbloquear", 'POST', [
        "Cookie: {$cookieSession}",
        "X-CSRF-Token: {$csrfToken}",
        'Content-Type: application/json',
        'Accept: application/json',
    ], json_encode([]));
    $jsonReact = json_decode($rReactivar['body'], true);
    $reactOk = ($rReactivar['code'] === 200) &&
               isset($jsonReact['exito']) &&
               $jsonReact['exito'] === true &&
               $jsonReact['datos']['estado'] === 'ACTIVO' &&
               $jsonReact['datos']['motivo_bloqueo'] === null;
    checkE2E('E2E-CLI-16', 'API POST /api/clientes/{id}/desbloquear reactiva cliente y limpia motivo (HTTP 200)', $reactOk, "Code: {$rReactivar['code']}");

    // -------------------------------------------------------------------------
    // E2E-CLI-17: Menú dinámico Alina renderiza opción Clientes para usuario autorizado
    // -------------------------------------------------------------------------
    $rMenuAuth = curlRequest("{$baseUrl}/clientes", 'GET', [
        "Cookie: {$cookieSession}",
    ]);
    $menuAuthOk = ($rMenuAuth['code'] === 200) &&
                  str_contains($rMenuAuth['body'], 'href="/clientes"') &&
                  str_contains($rMenuAuth['body'], 'fa-solid fa-users') &&
                  str_contains($rMenuAuth['body'], 'Clientes') &&
                  str_contains($rMenuAuth['body'], 'id="reservas"');
    checkE2E('E2E-CLI-17', 'Menú dinámico Alina renderiza opción Clientes con icono fa-solid fa-users bajo reservas', $menuAuthOk, "Code: {$rMenuAuth['code']}");

    // -------------------------------------------------------------------------
    // E2E-CLI-18: Menú dinámico Alina excluye opción Clientes para usuario sin permiso clientes.ver
    // -------------------------------------------------------------------------
    $rMenuSin = curlRequest("{$baseUrl}/", 'GET', [
        "Cookie: {$cookieSessionSin}",
    ]);
    $menuSinExcluye = ($rMenuSin['code'] === 200) &&
                      !str_contains($rMenuSin['body'], 'href="/clientes"');
    checkE2E('E2E-CLI-18', 'Menú dinámico Alina excluye /clientes para usuario sin permiso clientes.ver (MENÚ ≠ AUTORIZACIÓN)', $menuSinExcluye, "Code: {$rMenuSin['code']}");

} catch (Throwable $e) {
    echo "\nEXCEPCIÓN NO CONTROLADA EN E2E: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    $fail++;
    $errores[] = "Excepción: " . $e->getMessage();
} finally {
    // ============================================================================
    // LIMPIEZA AUTOCONTENIDA GARANTIZADA DE FIXTURES DE PRUEBA
    // ============================================================================
    echo "\n--- LIMPIEZA DE FIXTURES E2E TEMPORALES ---\n";

    if (!empty($fixtures['clientes'])) {
        $idsCliStr = implode(',', array_map('intval', $fixtures['clientes']));
        $pdo->exec("DELETE FROM auditoria WHERE entidad = 'clientes' AND entidad_id IN ({$idsCliStr})");
        $pdo->exec("DELETE FROM clientes WHERE id IN ({$idsCliStr})");
    }

    if (!empty($fixtures['personas'])) {
        $idsPerStr = implode(',', array_map('intval', $fixtures['personas']));
        $pdo->exec("DELETE a FROM auditoria a INNER JOIN clientes c ON a.entidad_id = c.id WHERE a.entidad = 'clientes' AND c.persona_id IN ({$idsPerStr})");
        $pdo->exec("DELETE FROM clientes WHERE persona_id IN ({$idsPerStr})");
    }

    if (!empty($fixtures['roles'])) {
        $idsRolStr = implode(',', array_map('intval', $fixtures['roles']));
        $pdo->exec("DELETE FROM usuarios_roles WHERE rol_id IN ({$idsRolStr})");
        $pdo->exec("DELETE FROM roles WHERE id IN ({$idsRolStr})");
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
echo " RESUMEN E2E CLIENTES-1: {$pass}/18 CASOS PASADOS (" . round(($pass / 18) * 100, 1) . "%)\n";
echo "====================================================================\n";

if ($fail > 0) {
    echo "FALLOS E2E DETECTADOS:\n";
    foreach ($errores as $err) {
        echo "  - {$err}\n";
    }
    exit(1);
}

echo "RESULTADO: SUITE E2E CLIENTES-1 100% HOMOLOGADA Y CERTIFICADA\n";
exit(0);
