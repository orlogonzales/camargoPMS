<?php

declare(strict_types=1);

/**
 * Suite de Pruebas End-to-End (E2E) Reales contra Apache en HTTPS — Camargo PMS (INVENTARIO-1)
 * Casos E2E-INV-01 a E2E-INV-14 (14/14).
 *
 * Principios vinculantes:
 * - Servidor web real Apache en HTTPS (https://app.camargo-pms.test/)
 * - Control estricto de sesiones y cookies
 * - RBAC vinculante: inventario.ver, inventario.articulos.gestionar, inventario.ubicaciones.gestionar,
 *   inventario.movimientos.registrar, inventario.traslados.ejecutar, inventario.activos.gestionar,
 *   inventario.dotaciones.gestionar
 * - D-075 / D-076: Geometría nativa Alina (app-form app-icon-form, b-r-20, select2 42px), Font Awesome exclusivo
 * - Kardex inmutable vs proyección materializada
 * - Stock no negativo garantizado (HTTP 422)
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
echo " VALIDACIÓN HTTP E2E REAL CONTRA APACHE (INVENTARIO-1)\n";
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
$adminUser = 'admin_inv_' . strtolower($sufijo);
$sinPermisoUser = 'sin_inv_' . strtolower($sufijo);
$passwordPlana = 'PassInv123!456';
$passwordHash = password_hash($passwordPlana, PASSWORD_DEFAULT);

$cookieSession = null;
$csrfToken = null;
$adminUsuarioId = null;
$sinPermisoUsuarioId = null;

try {
    // -------------------------------------------------------------------------
    // E2E-INV-01: Acceso no autenticado redirige a /login (HTTP 302)
    // -------------------------------------------------------------------------
    $r1 = curlRequest("{$baseUrl}/inventario", 'GET');
    $redirigeLogin = ($r1['code'] === 302 && str_contains((string) $r1['location'], '/login'));
    checkE2E('E2E-INV-01', 'Acceso no autenticado a /inventario redirige a /login (HTTP 302)', $redirigeLogin, "Code: {$r1['code']}, Loc: {$r1['location']}");

    // -------------------------------------------------------------------------
    // E2E-INV-02: Autenticación real de usuario administrador
    // -------------------------------------------------------------------------
    $pdo->exec("INSERT INTO personas (nombres, apellido_paterno, apellido_materno, pais_nacionalidad_id, creado_en) VALUES ('Admin', 'Inventario', '{$sufijo}', 1, NOW())");
    $adminPersonaId = (int) $pdo->lastInsertId();

    $stmtUAdmin = $pdo->prepare("INSERT INTO usuarios (persona_id, nombre_usuario, contrasena_hash, estado, creado_en) VALUES (?, ?, ?, 'ACTIVO', NOW())");
    $stmtUAdmin->execute([$adminPersonaId, $adminUser, $passwordHash]);
    $adminUsuarioId = (int) $pdo->lastInsertId();

    $stmtActorAdmin = $pdo->prepare("INSERT INTO actores (codigo, tipo, nombre, usuario_id, estado, creado_en) VALUES (?, 'USUARIO', ?, ?, 'ACTIVO', NOW())");
    $stmtActorAdmin->execute(['USR_' . $adminUsuarioId, 'Admin Inv ' . $sufijo, $adminUsuarioId]);

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
        '_csrf_token' => $csrfLogin,
        'nombre_usuario' => $adminUser,
        'contrasena' => $passwordPlana,
    ]);

    $rLoginPost = curlRequest("{$baseUrl}/login", 'POST', [
        "Content-Type: application/x-www-form-urlencoded",
        "Cookie: {$cookieLogin}",
    ], $postLogin);

    $loginExitoso = ($rLoginPost['code'] === 302);
    if (!empty($rLoginPost['cookies'])) {
        $cookieSession = $rLoginPost['cookies'][0];
    } else {
        $cookieSession = $cookieLogin;
    }

    checkE2E('E2E-INV-02', 'Autenticación real de usuario administrador e inicio de sesión', $loginExitoso, "Code: {$rLoginPost['code']}");

    // -------------------------------------------------------------------------
    // E2E-INV-03: Renderizado de vista principal Alina /inventario (HTTP 200)
    // -------------------------------------------------------------------------
    $rVista = curlRequest("{$baseUrl}/inventario", 'GET', [
        "Cookie: {$cookieSession}",
    ]);

    $csrfToken = extraerCsrfDeHtml($rVista['body']);
    $tieneAlinaGeometria = str_contains($rVista['body'], 'b-r-20') &&
                           str_contains($rVista['body'], 'app-form') &&
                           str_contains($rVista['body'], 'tab-articulos');

    checkE2E('E2E-INV-03', 'Renderizado vista Alina /inventario (200) con geometría nativa b-r-20 y pestañas operativas', $rVista['code'] === 200 && $tieneAlinaGeometria, "Code: {$rVista['code']}, Token: " . substr($csrfToken, 0, 10));

    // -------------------------------------------------------------------------
    // E2E-INV-04: Control RBAC estricto (403 Forbidden para usuario sin permiso)
    // -------------------------------------------------------------------------
    $pdo->exec("INSERT INTO personas (nombres, apellido_paterno, apellido_materno, pais_nacionalidad_id, creado_en) VALUES ('Sin', 'Permiso', '{$sufijo}', 1, NOW())");
    $sinPersonaId = (int) $pdo->lastInsertId();

    $stmtUSin = $pdo->prepare("INSERT INTO usuarios (persona_id, nombre_usuario, contrasena_hash, estado, creado_en) VALUES (?, ?, ?, 'ACTIVO', NOW())");
    $stmtUSin->execute([$sinPersonaId, $sinPermisoUser, $passwordHash]);
    $sinPermisoUsuarioId = (int) $pdo->lastInsertId();

    // Crear rol restringido sin permisos de inventario
    $pdo->exec("INSERT INTO roles (codigo, nombre, descripcion, es_sistema, creado_en) VALUES ('ROL_RESTRINGIDO_{$sufijo}', 'Restringido {$sufijo}', 'Sin permisos', 0, NOW())");
    $rolRestringidoId = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO usuarios_roles (usuario_id, rol_id, asignado_en) VALUES ({$sinPermisoUsuarioId}, {$rolRestringidoId}, NOW())");

    // Login usuario restringido
    $rLoginSinGet = curlRequest("{$baseUrl}/login", 'GET');
    $csrfSin = extraerCsrfDeHtml($rLoginSinGet['body']);
    $cookieSinLogin = $rLoginSinGet['cookies'][0] ?? '';

    $rLoginSinPost = curlRequest("{$baseUrl}/login", 'POST', [
        "Content-Type: application/x-www-form-urlencoded",
        "Cookie: {$cookieSinLogin}",
    ], http_build_query([
        '_csrf_token' => $csrfSin,
        'nombre_usuario' => $sinPermisoUser,
        'contrasena' => $passwordPlana,
    ]));

    $cookieSessionSin = !empty($rLoginSinPost['cookies']) ? $rLoginSinPost['cookies'][0] : $cookieSinLogin;

    // Intento de crear artículo con usuario sin permisos
    $rRbac = curlRequest("{$baseUrl}/api/inventario/articulos", 'POST', [
        "Cookie: {$cookieSessionSin}",
        "X-CSRF-TOKEN: {$csrfSin}",
        "Content-Type: application/json",
    ], json_encode([
        'codigo_sku' => "SKU-HACK-{$sufijo}",
        'nombre' => 'Hack Articulo',
        'categoria' => 'CONSUMIBLE_OPERATIVO',
        'unidad_medida_id' => 1,
    ]));

    checkE2E('E2E-INV-04', 'Control RBAC estricto: 403 Forbidden a usuario sin permiso inventario.articulos.gestionar', $rRbac['code'] === 403, "Code: {$rRbac['code']}");

    // Cabeceras autenticadas de administrador para las pruebas restantes
    $adminHeaders = [
        "Cookie: {$cookieSession}",
        "X-CSRF-TOKEN: {$csrfToken}",
        "Content-Type: application/json",
    ];

    $stmtU = $pdo->query("SELECT u.id, u.propiedad_id FROM unidades u JOIN propiedades p ON p.id = u.propiedad_id WHERE u.estado = 'ACTIVO' AND p.estado = 'ACTIVO' LIMIT 1");
    $uData = $stmtU->fetch(PDO::FETCH_ASSOC);
    if ($uData) {
        $unidadId = (int) $uData['id'];
        $propiedadId = (int) $uData['propiedad_id'];
    } else {
        $propiedadId = (int) $pdo->query("SELECT id FROM propiedades WHERE estado = 'ACTIVO' LIMIT 1")->fetchColumn();
        $unidadId = (int) $pdo->query("SELECT id FROM unidades LIMIT 1")->fetchColumn();
    }

    // -------------------------------------------------------------------------
    // E2E-INV-05: Creación de artículo consumible vía POST /api/inventario/articulos (201)
    // -------------------------------------------------------------------------
    $skuArt = "SKU-E2E-{$sufijo}";
    $rCrearArt = curlRequest("{$baseUrl}/api/inventario/articulos", 'POST', $adminHeaders, json_encode([
        'codigo_sku' => $skuArt,
        'nombre' => "Jabón Líquido E2E {$sufijo}",
        'categoria' => 'CONSUMIBLE_OPERATIVO',
        'unidad_medida_id' => 1,
        'costo_referencial' => '2.5000',
        'stock_minimo_alerta' => '10.0000',
    ]));

    $jsonArt = json_decode($rCrearArt['body'], true);
    $articuloId = $jsonArt['datos']['id'] ?? 0;
    $artCreado = ($rCrearArt['code'] === 201 && $articuloId > 0 && ($jsonArt['datos']['codigo_sku'] ?? '') === $skuArt);

    checkE2E('E2E-INV-05', 'POST /api/inventario/articulos registra consumible operativo (HTTP 201)', $artCreado, "Code: {$rCrearArt['code']}");

    // -------------------------------------------------------------------------
    // E2E-INV-06: Creación de ubicaciones vía POST /api/inventario/ubicaciones (201)
    // -------------------------------------------------------------------------
    $rUbi1 = curlRequest("{$baseUrl}/api/inventario/ubicaciones", 'POST', $adminHeaders, json_encode([
        'propiedad_id' => $propiedadId,
        'codigo' => "UBI-E2E1-{$sufijo}",
        'nombre' => "Almacén Principal {$sufijo}",
        'tipo' => 'ALMACEN',
    ]));
    $jsonUbi1 = json_decode($rUbi1['body'], true);
    $ubiId1 = $jsonUbi1['datos']['id'] ?? 0;

    $rUbi2 = curlRequest("{$baseUrl}/api/inventario/ubicaciones", 'POST', $adminHeaders, json_encode([
        'propiedad_id' => $propiedadId,
        'codigo' => "UBI-E2E2-{$sufijo}",
        'nombre' => "Almacén Secundario {$sufijo}",
        'tipo' => 'ALMACEN',
    ]));
    $jsonUbi2 = json_decode($rUbi2['body'], true);
    $ubiId2 = $jsonUbi2['datos']['id'] ?? 0;

    checkE2E('E2E-INV-06', 'POST /api/inventario/ubicaciones crea almacenes físicos (HTTP 201)', $rUbi1['code'] === 201 && $ubiId1 > 0 && $ubiId2 > 0, "Ubi1: {$ubiId1}, Ubi2: {$ubiId2}");

    // -------------------------------------------------------------------------
    // E2E-INV-07: Registro de saldo inicial vía POST /api/inventario/movimientos/saldo-inicial (201)
    // -------------------------------------------------------------------------
    $rSaldo = curlRequest("{$baseUrl}/api/inventario/movimientos/saldo-inicial", 'POST', $adminHeaders, json_encode([
        'articulo_id' => $articuloId,
        'ubicacion_id' => $ubiId1,
        'cantidad' => '100.0000',
        'costo_unitario' => '2.5000',
    ]));
    $jsonSaldo = json_decode($rSaldo['body'], true);
    $saldoOk = ($rSaldo['code'] === 201 && ($jsonSaldo['datos']['tipo_movimiento'] ?? '') === 'SALDO_INICIAL');

    checkE2E('E2E-INV-07', 'POST /api/inventario/movimientos/saldo-inicial registra saldo formal e inicializa Kardex (HTTP 201)', $saldoOk, "Code: {$rSaldo['code']}");

    // -------------------------------------------------------------------------
    // E2E-INV-08: Entrada por compra vía POST /api/inventario/movimientos/entrada (201)
    // -------------------------------------------------------------------------
    $rEntrada = curlRequest("{$baseUrl}/api/inventario/movimientos/entrada", 'POST', $adminHeaders, json_encode([
        'articulo_id' => $articuloId,
        'ubicacion_id' => $ubiId1,
        'cantidad' => '50.0000',
        'costo_unitario' => '2.6000',
        'motivo' => 'Compra ordinaria E2E',
    ]));
    $jsonEntrada = json_decode($rEntrada['body'], true);
    $entradaOk = ($rEntrada['code'] === 201 && ($jsonEntrada['datos']['tipo_movimiento'] ?? '') === 'ENTRADA_COMPRA');

    // Verificar existencias acumuladas en GET /api/inventario/existencias
    $rEx = curlRequest("{$baseUrl}/api/inventario/existencias?articulo_id={$articuloId}&ubicacion_id={$ubiId1}", 'GET', $adminHeaders);
    $jsonEx = json_decode($rEx['body'], true);
    $cantActual = $jsonEx['datos'][0]['cantidad_actual'] ?? '0.0000';
    $acumulacionOk = bccomp($cantActual, '150.0000', 4) === 0;

    checkE2E('E2E-INV-08', 'POST /api/inventario/movimientos/entrada acumula existencias a 150.0000 (HTTP 201)', $entradaOk && $acumulacionOk, "Stock: {$cantActual}");

    // -------------------------------------------------------------------------
    // E2E-INV-09: Salida de consumo ordinario vía POST /api/inventario/movimientos/consumo (201)
    // -------------------------------------------------------------------------
    $rConsumo = curlRequest("{$baseUrl}/api/inventario/movimientos/consumo", 'POST', $adminHeaders, json_encode([
        'articulo_id' => $articuloId,
        'ubicacion_id' => $ubiId1,
        'cantidad' => '20.0000',
        'motivo' => 'Consumo operativo pisos E2E',
    ]));
    $jsonConsumo = json_decode($rConsumo['body'], true);
    $consumoOk = ($rConsumo['code'] === 201 && ($jsonConsumo['datos']['tipo_movimiento'] ?? '') === 'SALIDA_CONSUMO');

    checkE2E('E2E-INV-09', 'POST /api/inventario/movimientos/consumo descuenta stock operativo (HTTP 201)', $consumoOk, "Code: {$rConsumo['code']}");

    // -------------------------------------------------------------------------
    // E2E-INV-10: Rechazo de sobregiro con StockInsuficienteExcepcion (HTTP 422)
    // -------------------------------------------------------------------------
    $rSobregiro = curlRequest("{$baseUrl}/api/inventario/movimientos/consumo", 'POST', $adminHeaders, json_encode([
        'articulo_id' => $articuloId,
        'ubicacion_id' => $ubiId1,
        'cantidad' => '99999.0000', // Excede con creces el stock de 130
        'motivo' => 'Intento de sobregiro',
    ]));
    $jsonSobregiro = json_decode($rSobregiro['body'], true);
    $sobregiroRechazado = ($rSobregiro['code'] === 422 && str_contains(strtolower($jsonSobregiro['error'] ?? ''), 'stock insuficiente'));

    checkE2E('E2E-INV-10', 'Protección contra stock negativo rechaza sobregiro con HTTP 422 estructurado', $sobregiroRechazado, "Code: {$rSobregiro['code']}, Error: " . ($jsonSobregiro['error'] ?? ''));

    // -------------------------------------------------------------------------
    // E2E-INV-11: Traslado atómico entre dos almacenes vía POST /api/inventario/movimientos/traslado (201)
    // -------------------------------------------------------------------------
    $rTraslado = curlRequest("{$baseUrl}/api/inventario/movimientos/traslado", 'POST', $adminHeaders, json_encode([
        'articulo_id' => $articuloId,
        'ubicacion_origen_id' => $ubiId1,
        'ubicacion_destino_id' => $ubiId2,
        'cantidad' => '30.0000',
        'motivo' => 'Reabastecimiento de sub-almacén E2E',
    ]));
    $jsonTraslado = json_decode($rTraslado['body'], true);
    $trasladoOk = ($rTraslado['code'] === 201 && !empty($jsonTraslado['datos']['correlativo']));

    checkE2E('E2E-INV-11', 'POST /api/inventario/movimientos/traslado genera traslado de dos patas con correlativo único (HTTP 201)', $trasladoOk, "Correlativo: " . ($jsonTraslado['datos']['correlativo'] ?? 'N/A'));

    // -------------------------------------------------------------------------
    // E2E-INV-12: Registro de ajuste físico vía POST /api/inventario/movimientos/ajuste (201)
    // -------------------------------------------------------------------------
    $rAjuste = curlRequest("{$baseUrl}/api/inventario/movimientos/ajuste", 'POST', $adminHeaders, json_encode([
        'articulo_id' => $articuloId,
        'ubicacion_id' => $ubiId1,
        'cantidad_diferencia' => '5.0000',
        'tipo_ajuste' => 'AJUSTE_POSITIVO',
        'motivo' => 'Hallazgo en conteo físico E2E',
    ]));
    $jsonAjuste = json_decode($rAjuste['body'], true);
    $ajusteOk = ($rAjuste['code'] === 201 && ($jsonAjuste['datos']['tipo_movimiento'] ?? '') === 'AJUSTE_POSITIVO');

    checkE2E('E2E-INV-12', 'POST /api/inventario/movimientos/ajuste registra ajuste de inventario físico (HTTP 201)', $ajusteOk, "Code: {$rAjuste['code']}");

    // -------------------------------------------------------------------------
    // E2E-INV-13: Creación y asignación de ACTIVO_SERIALIZABLE (201 y 200)
    // -------------------------------------------------------------------------
    $skuTv = "SKU-TV-{$sufijo}";
    $rArtTv = curlRequest("{$baseUrl}/api/inventario/articulos", 'POST', $adminHeaders, json_encode([
        'codigo_sku' => $skuTv,
        'nombre' => "Smart TV 55 LG E2E {$sufijo}",
        'categoria' => 'ACTIVO_SERIALIZABLE',
        'unidad_medida_id' => 1,
        'costo_referencial' => '1600.0000',
    ]));
    $tvArtId = json_decode($rArtTv['body'], true)['datos']['id'] ?? 0;

    $placaE2E = "PLC-E2E-{$sufijo}";
    $rActivo = curlRequest("{$baseUrl}/api/inventario/activos", 'POST', $adminHeaders, json_encode([
        'articulo_id' => $tvArtId,
        'codigo_placa' => $placaE2E,
        'numero_serie_fabricante' => "SN-LG-{$sufijo}",
        'marca' => 'LG',
        'modelo' => 'OLED55C3',
        'propiedad_id' => $propiedadId,
        'ubicacion_id' => $ubiId1,
        'costo_adquisicion' => '1600.00',
    ]));
    $jsonActivo = json_decode($rActivo['body'], true);
    $activoId = $jsonActivo['datos']['id'] ?? 0;
    $activoCreado = ($rActivo['code'] === 201 && $activoId > 0 && ($jsonActivo['datos']['estado'] ?? '') === 'DISPONIBLE');

    // Asegurar ubicación de tipo UNIDAD para asignar el activo
    $stmtUbiHab = $pdo->query("SELECT id FROM inventario_ubicaciones WHERE unidad_id = {$unidadId} LIMIT 1");
    $ubiHabId = (int) $stmtUbiHab->fetchColumn();
    if ($ubiHabId === 0) {
        $pdo->exec("INSERT INTO inventario_ubicaciones (propiedad_id, codigo, nombre, tipo, unidad_id, creado_en) VALUES ({$propiedadId}, 'UBI-HAB-E2E-{$sufijo}', 'Habitacion {$sufijo}', 'UNIDAD', {$unidadId}, NOW())");
        $ubiHabId = (int) $pdo->lastInsertId();
    }

    $rAsignar = curlRequest("{$baseUrl}/api/inventario/activos/{$activoId}/asignar", 'POST', $adminHeaders, json_encode([
        'ubicacion_unidad_id' => $ubiHabId,
    ]));
    $jsonAsignar = json_decode($rAsignar['body'], true);
    $activoAsignado = ($rAsignar['code'] === 200 && ($jsonAsignar['datos']['estado'] ?? '') === 'ASIGNADO');

    checkE2E('E2E-INV-13', 'POST /api/inventario/activos registra y asigna activo serializable a habitación (HTTP 201/200)', $activoCreado && $activoAsignado, "Activo ID: {$activoId}, Estado: " . ($jsonAsignar['datos']['estado'] ?? 'N/A'));

    // -------------------------------------------------------------------------
    // E2E-INV-14: Auditoría de dotación estándar vs realidad vía GET /api/inventario/dotaciones/auditoria/{unidadId} (200)
    // -------------------------------------------------------------------------
    // Crear dotación estándar para la unidad
    curlRequest("{$baseUrl}/api/inventario/dotaciones", 'POST', $adminHeaders, json_encode([
        'unidad_id' => $unidadId,
        'articulo_id' => $articuloId,
        'cantidad_estandar' => '4.0000',
        'notas' => 'Dotación estándar E2E',
    ]));

    $rAuditoria = curlRequest("{$baseUrl}/api/inventario/dotaciones/auditoria/{$unidadId}", 'GET', $adminHeaders);
    $jsonAuditoria = json_decode($rAuditoria['body'], true);
    $auditoriaOk = ($rAuditoria['code'] === 200 && $jsonAuditoria['exito'] === true && is_array($jsonAuditoria['datos']));

    checkE2E('E2E-INV-14', 'GET /api/inventario/dotaciones/auditoria/{unidadId} devuelve matriz estándar vs realidad física (HTTP 200)', $auditoriaOk, "Code: {$rAuditoria['code']}, Items: " . count($jsonAuditoria['datos'] ?? []));

} catch (\Throwable $e) {
    echo "ERROR GENERAL EN SUITE E2E: " . $e->getMessage() . "\n";
    $fail++;
}

echo "\n====================================================================\n";
echo "RESULTADO E2E INVENTARIO-1: {$pass} / 14 PASS\n";
echo "====================================================================\n";

if ($fail > 0 || $pass !== 14) {
    exit(1);
}
