<?php

declare(strict_types=1);

/**
 * Suite de Pruebas End-to-End (E2E) Reales contra Apache en HTTPS — Camargo PMS (MANTENIMIENTO-1)
 * Casos E2E-MNT-01 a E2E-MNT-14 (14/14).
 *
 * Principios vinculantes:
 * - Servidor web real Apache en HTTPS (https://app.camargo-pms.test/)
 * - Control estricto de sesiones y cookies
 * - RBAC vinculante: mantenimiento.ver, mantenimiento.incidencias.reportar, mantenimiento.incidencias.gestionar,
 *   mantenimiento.ordenes.crear, mantenimiento.ordenes.programar, mantenimiento.ordenes.ejecutar,
 *   mantenimiento.ordenes.cerrar, mantenimiento.ordenes.cancelar
 * - D-075 / D-076: Geometría nativa Alina (app-form app-icon-form, b-r-20, select2 42px), Font Awesome exclusivo
 * - Coexistencia con Disponibilidad e Inventario Diario: Noches semiabiertas [inicio, fin)
 * - Trazabilidad D-061: Historial inmutable de estados y costos auditados
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
echo " VALIDACIÓN HTTP E2E REAL CONTRA APACHE (MANTENIMIENTO-1)\n";
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
$adminUser = 'admin_mnt_' . strtolower($sufijo);
$passwordPlana = 'PassMnt123!456';
$passwordHash = password_hash($passwordPlana, PASSWORD_DEFAULT);

$cookieSession = null;
$csrfToken = null;
$adminUsuarioId = null;

try {
    // -------------------------------------------------------------------------
    // E2E-MNT-01: Acceso no autenticado redirige a /login (HTTP 302)
    // -------------------------------------------------------------------------
    $r1 = curlRequest("{$baseUrl}/mantenimiento", 'GET');
    $redirigeLogin = ($r1['code'] === 302 && str_contains((string) $r1['location'], '/login'));
    checkE2E('E2E-MNT-01', 'Acceso no autenticado a /mantenimiento redirige a /login (HTTP 302)', $redirigeLogin, "Code: {$r1['code']}, Loc: {$r1['location']}");

    // -------------------------------------------------------------------------
    // E2E-MNT-02: Autenticación real de usuario administrador
    // -------------------------------------------------------------------------
    $pdo->exec("INSERT INTO personas (nombres, apellido_paterno, apellido_materno, pais_nacionalidad_id, creado_en) VALUES ('Admin', 'Mantenimiento', '{$sufijo}', 1, NOW())");
    $adminPersonaId = (int) $pdo->lastInsertId();

    $stmtUAdmin = $pdo->prepare("INSERT INTO usuarios (persona_id, nombre_usuario, contrasena_hash, estado, creado_en) VALUES (?, ?, ?, 'ACTIVO', NOW())");
    $stmtUAdmin->execute([$adminPersonaId, $adminUser, $passwordHash]);
    $adminUsuarioId = (int) $pdo->lastInsertId();

    $stmtActorAdmin = $pdo->prepare("INSERT INTO actores (codigo, tipo, nombre, usuario_id, estado, creado_en) VALUES (?, 'USUARIO', ?, ?, 'ACTIVO', NOW())");
    $stmtActorAdmin->execute(['USR_' . $adminUsuarioId, 'Admin Mnt ' . $sufijo, $adminUsuarioId]);

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
    checkE2E('E2E-MNT-02', 'Autenticación real de usuario administrador con redirección HTTP 302', $loginOk, "Code: {$rLoginPost['code']}, Loc: {$rLoginPost['location']}");

    // -------------------------------------------------------------------------
    // E2E-MNT-03: Carga de vista /mantenimiento con HTML Alina
    // -------------------------------------------------------------------------
    // E2E-MNT-03: Carga de vista /mantenimiento con HTML Alina
    // -------------------------------------------------------------------------
    $rVista = curlRequest("{$baseUrl}/mantenimiento", 'GET', [
        "Cookie: {$cookieSession}",
    ]);
    $csrfToken = extraerCsrfDeHtml($rVista['body']);
    $tieneTitulo = str_contains($rVista['body'], 'Mantenimiento e Incidencias');
    $tieneKPIs = str_contains($rVista['body'], 'kpi-incidencias-abiertas');
    $tieneIconForm = str_contains($rVista['body'], 'app-icon-form');
    $tieneModales = str_contains($rVista['body'], 'modal-reportar-incidencia') && str_contains($rVista['body'], 'modal-crear-orden');
    $vistaOk = ($rVista['code'] === 200 && $tieneTitulo && $tieneKPIs && $tieneIconForm && $tieneModales && !empty($csrfToken));
    checkE2E('E2E-MNT-03', 'Carga de vista /mantenimiento con HTML Alina (HTTP 200, KPIs, app-icon-form y modales)', $vistaOk, "Code: {$rVista['code']}");

    // -------------------------------------------------------------------------
    // E2E-MNT-04: Inyección y presencia de assets obligatorios D-075 / D-076
    // -------------------------------------------------------------------------
    $tieneScriptModulo = str_contains($rVista['body'], 'gestion-mantenimiento.js');
    $tienePristine = str_contains($rVista['body'], 'pristine');
    $tieneSweetAlert = str_contains($rVista['body'], 'sweetalert');
    $assetsOk = ($tieneScriptModulo && $tienePristine && $tieneSweetAlert);
    checkE2E('E2E-MNT-04', 'Inyección de assets obligatorios (gestion-mantenimiento.js, pristine, sweetalert)', $assetsOk);

    // Preparar propiedades, unidades y colaboradores para pruebas API
    $stmtProp = $pdo->query("SELECT id FROM propiedades WHERE estado = 'ACTIVO' LIMIT 1");
    $propiedadId = (int) $stmtProp->fetchColumn();

    $stmtU = $pdo->prepare("SELECT id FROM unidades WHERE propiedad_id = ? AND estado = 'ACTIVO' LIMIT 2");
    $stmtU->execute([$propiedadId]);
    $unidades = $stmtU->fetchAll(PDO::FETCH_COLUMN);
    $unidadPruebaId = (int) ($unidades[0] ?? 1);

    // Asegurar colaborador activo
    $colaboradorId = (int) $pdo->query("SELECT id FROM colaboradores WHERE estado = 'ACTIVO' LIMIT 1")->fetchColumn();
    if (!$colaboradorId) {
        $pdo->exec("INSERT INTO personas (nombres, apellido_paterno, apellido_materno, pais_nacionalidad_id, creado_en) VALUES ('Tecnico', 'Mantenimiento', '{$sufijo}', 1, NOW())");
        $tecPersonaId = (int) $pdo->lastInsertId();
        $pdo->exec("INSERT INTO colaboradores (persona_id, cargo, estado, creado_en) VALUES ({$tecPersonaId}, 'Técnico de Mantenimiento', 'ACTIVO', NOW())");
        $colaboradorId = (int) $pdo->lastInsertId();
    }

    // -------------------------------------------------------------------------
    // E2E-MNT-05: GET /api/mantenimiento/incidencias devuelve lista JSON
    // -------------------------------------------------------------------------
    $rListarInc = curlRequest("{$baseUrl}/api/mantenimiento/incidencias", 'GET', [
        "Cookie: {$cookieSession}",
        'Accept: application/json',
    ]);
    $jsonListarInc = json_decode($rListarInc['body'], true);
    $listarIncOk = ($rListarInc['code'] === 200 && is_array($jsonListarInc) && ($jsonListarInc['ok'] ?? false) === true);
    checkE2E('E2E-MNT-05', 'GET /api/mantenimiento/incidencias devuelve lista JSON estructurada (HTTP 200)', $listarIncOk, "Code: {$rListarInc['code']}");

    // -------------------------------------------------------------------------
    // E2E-MNT-06: POST /api/mantenimiento/incidencias crea incidencia
    // -------------------------------------------------------------------------
    $payloadInc = json_encode([
        'propiedad_id' => $propiedadId,
        'unidad_id' => $unidadPruebaId,
        'reportado_por_persona_id' => $adminPersonaId,
        'titulo' => 'Fuga de agua en lavamanos de suite ' . $sufijo,
        'descripcion' => 'Goteo continuo que requiere reemplazo de grifería y sello de desagüe',
        'categoria' => 'PLOMERIA',
        'severidad' => 'ALTA',
        'prioridad' => 'URGENTE',
        '_csrf' => $csrfToken,
    ]);

    $rCrearInc = curlRequest("{$baseUrl}/api/mantenimiento/incidencias", 'POST', [
        "Cookie: {$cookieSession}",
        'Content-Type: application/json',
        'Accept: application/json',
        "X-CSRF-Token: {$csrfToken}",
    ], $payloadInc);

    $jsonCrearInc = json_decode($rCrearInc['body'], true);
    $incidenciaId = (int) ($jsonCrearInc['datos']['id'] ?? 0);
    $crearIncOk = ($rCrearInc['code'] === 201 && ($jsonCrearInc['ok'] ?? false) === true && $incidenciaId > 0 && ($jsonCrearInc['datos']['estado'] ?? '') === 'REPORTADA');
    checkE2E('E2E-MNT-06', 'POST /api/mantenimiento/incidencias registra incidencia con severidad ALTA (HTTP 201)', $crearIncOk, "Code: {$rCrearInc['code']}, ID: {$incidenciaId}");

    // -------------------------------------------------------------------------
    // E2E-MNT-07: GET /api/mantenimiento/ordenes devuelve lista JSON
    // -------------------------------------------------------------------------
    $rListarOrd = curlRequest("{$baseUrl}/api/mantenimiento/ordenes", 'GET', [
        "Cookie: {$cookieSession}",
        'Accept: application/json',
    ]);
    $jsonListarOrd = json_decode($rListarOrd['body'], true);
    $listarOrdOk = ($rListarOrd['code'] === 200 && is_array($jsonListarOrd) && ($jsonListarOrd['ok'] ?? false) === true);
    checkE2E('E2E-MNT-07', 'GET /api/mantenimiento/ordenes devuelve lista JSON estructurada (HTTP 200)', $listarOrdOk, "Code: {$rListarOrd['code']}");

    // -------------------------------------------------------------------------
    // E2E-MNT-08: POST /api/mantenimiento/ordenes formula orden en BORRADOR vinculando incidencia
    // -------------------------------------------------------------------------
    $payloadOrden = json_encode([
        'propiedad_id' => $propiedadId,
        'unidad_id' => $unidadPruebaId,
        'tipo' => 'CORRECTIVO',
        'prioridad' => 'URGENTE',
        'titulo' => 'Reemplazo integral de grifería y sellos ' . $sufijo,
        'descripcion' => 'Desmontaje de tubería averiada y cambio por repuesto original',
        'tipo_asignacion' => 'INTERNO',
        'colaborador_asignado_id' => $colaboradorId,
        'fecha_programada_inicio' => '2031-05-10',
        'fecha_programada_fin' => '2031-05-14',
        'requiere_bloqueo' => 0,
        'incidencias_ids' => [$incidenciaId],
        '_csrf' => $csrfToken,
    ]);

    $rCrearOrd = curlRequest("{$baseUrl}/api/mantenimiento/ordenes", 'POST', [
        "Cookie: {$cookieSession}",
        'Content-Type: application/json',
        'Accept: application/json',
        "X-CSRF-Token: {$csrfToken}",
    ], $payloadOrden);

    $jsonCrearOrd = json_decode($rCrearOrd['body'], true);
    $ordenId = (int) ($jsonCrearOrd['datos']['id'] ?? 0);
    $crearOrdOk = ($rCrearOrd['code'] === 201 && ($jsonCrearOrd['ok'] ?? false) === true && $ordenId > 0 && ($jsonCrearOrd['datos']['estado'] ?? '') === 'BORRADOR');
    checkE2E('E2E-MNT-08', 'POST /api/mantenimiento/ordenes formula orden en BORRADOR vinculando incidencia (HTTP 201)', $crearOrdOk, "Code: {$rCrearOrd['code']}, ID: {$ordenId}");

    // -------------------------------------------------------------------------
    // E2E-MNT-09: POST /api/mantenimiento/ordenes rechaza datos inválidos (HTTP 422)
    // -------------------------------------------------------------------------
    $payloadInvalido = json_encode([
        'propiedad_id' => $propiedadId,
        'unidad_id' => $unidadPruebaId,
        'tipo' => 'TIPO_INEXISTENTE',
        'prioridad' => 'MEDIA',
        'titulo' => '', // Inválido por título vacío
        '_csrf' => $csrfToken,
    ]);

    $rInvalido = curlRequest("{$baseUrl}/api/mantenimiento/ordenes", 'POST', [
        "Cookie: {$cookieSession}",
        'Content-Type: application/json',
        'Accept: application/json',
        "X-CSRF-Token: {$csrfToken}",
    ], $payloadInvalido);

    $jsonInvalido = json_decode($rInvalido['body'], true);
    $invalidoOk = ($rInvalido['code'] === 422 && ($jsonInvalido['ok'] ?? true) === false);
    checkE2E('E2E-MNT-09', 'POST /api/mantenimiento/ordenes rechaza datos inválidos con código 422', $invalidoOk, "Code: {$rInvalido['code']}");

    // -------------------------------------------------------------------------
    // E2E-MNT-10: POST /api/mantenimiento/ordenes/{id}/programar con bloqueo físico
    // -------------------------------------------------------------------------
    $fechaProgIni = '2031-05-10';
    $fechaProgFin = '2031-05-14';
    $pdo->exec("DELETE FROM inventario_diario_unidades WHERE unidad_id = {$unidadPruebaId} AND fecha >= '2031-01-01'");

    $payloadProg = json_encode([
        'fecha_programada_inicio' => $fechaProgIni,
        'fecha_programada_fin' => $fechaProgFin,
        'requiere_bloqueo' => 1,
        'fecha_bloqueo_inicio' => $fechaProgIni,
        'fecha_bloqueo_fin' => $fechaProgFin,
        '_csrf' => $csrfToken,
    ]);

    $rProg = curlRequest("{$baseUrl}/api/mantenimiento/ordenes/{$ordenId}/programar", 'POST', [
        "Cookie: {$cookieSession}",
        'Content-Type: application/json',
        'Accept: application/json',
        "X-CSRF-Token: {$csrfToken}",
    ], $payloadProg);

    $jsonProg = json_decode($rProg['body'], true);
    $cantNochesProg = (int) $pdo->query("SELECT COUNT(*) FROM inventario_diario_unidades WHERE unidad_id = {$unidadPruebaId} AND fecha >= '{$fechaProgIni}' AND fecha < '{$fechaProgFin}' AND tipo_bloqueo = 'MANTENIMIENTO'")->fetchColumn();

    $progOk = ($rProg['code'] === 200 && ($jsonProg['ok'] ?? false) === true && ($jsonProg['datos']['estado'] ?? '') === 'PROGRAMADA' && $cantNochesProg === 4);
    checkE2E('E2E-MNT-10', 'POST /api/mantenimiento/ordenes/{id}/programar asegura bloqueo semiabierto en inventario (HTTP 200)', $progOk, "Code: {$rProg['code']}, Noches: {$cantNochesProg}");

    // -------------------------------------------------------------------------
    // E2E-MNT-11: GET /api/mantenimiento/ordenes/{id} devuelve detalle con historial
    // -------------------------------------------------------------------------
    $rDetalle = curlRequest("{$baseUrl}/api/mantenimiento/ordenes/{$ordenId}", 'GET', [
        "Cookie: {$cookieSession}",
        'Accept: application/json',
    ]);
    $jsonDetalle = json_decode($rDetalle['body'], true);
    $tieneHistorial = !empty($jsonDetalle['datos']['historial_estados']);
    $tieneIncidencias = !empty($jsonDetalle['datos']['incidencias_asociadas']) || !empty($jsonDetalle['datos']['incidencias']);
    $detalleOk = ($rDetalle['code'] === 200 && ($jsonDetalle['ok'] ?? false) === true && $tieneHistorial && $tieneIncidencias);
    checkE2E('E2E-MNT-11', 'GET /api/mantenimiento/ordenes/{id} devuelve detalle completo con historial de estados (HTTP 200)', $detalleOk, "Code: {$rDetalle['code']}");

    // -------------------------------------------------------------------------
    // E2E-MNT-12: POST /api/mantenimiento/ordenes/{id}/estado pasa a EN_PROCESO
    // -------------------------------------------------------------------------
    $rIniciar = curlRequest("{$baseUrl}/api/mantenimiento/ordenes/{$ordenId}/estado", 'POST', [
        "Cookie: {$cookieSession}",
        'Content-Type: application/json',
        'Accept: application/json',
        "X-CSRF-Token: {$csrfToken}",
    ], json_encode(['_csrf' => $csrfToken]));

    $jsonIniciar = json_decode($rIniciar['body'], true);
    $iniciarOk = ($rIniciar['code'] === 200 && ($jsonIniciar['ok'] ?? false) === true && ($jsonIniciar['datos']['estado'] ?? '') === 'EN_PROCESO');
    checkE2E('E2E-MNT-12', 'POST /api/mantenimiento/ordenes/{id}/estado inicia formalmente ejecución (EN_PROCESO) (HTTP 200)', $iniciarOk, "Code: {$rIniciar['code']}");

    // -------------------------------------------------------------------------
    // E2E-MNT-13: POST /api/mantenimiento/ordenes/{id}/costos registra montos DECIMAL(15,2)
    // -------------------------------------------------------------------------
    $rCostos = curlRequest("{$baseUrl}/api/mantenimiento/ordenes/{$ordenId}/costos", 'POST', [
        "Cookie: {$cookieSession}",
        'Content-Type: application/json',
        'Accept: application/json',
        "X-CSRF-Token: {$csrfToken}",
    ], json_encode([
        'costo_materiales' => '345.50',
        'costo_mano_obra' => '250.00',
        '_csrf' => $csrfToken,
    ]));

    $jsonCostos = json_decode($rCostos['body'], true);
    $totalCalculado = $jsonCostos['datos']['costo_total'] ?? '0.00';
    $costosOk = ($rCostos['code'] === 200 && ($jsonCostos['ok'] ?? false) === true && bccomp((string) $totalCalculado, '595.50', 2) === 0);
    checkE2E('E2E-MNT-13', 'POST /api/mantenimiento/ordenes/{id}/costos liquida costos en DECIMAL con BCMath (HTTP 200)', $costosOk, "Code: {$rCostos['code']}, Total: {$totalCalculado}");

    // -------------------------------------------------------------------------
    // E2E-MNT-14: Extensión y liberación respetando noches consumidas (HTTP 200)
    // -------------------------------------------------------------------------
    // Primero prorrogamos hasta 2031-05-16 (+2 noches)
    $rProrroga = curlRequest("{$baseUrl}/api/mantenimiento/ordenes/{$ordenId}/prorrogar-bloqueo", 'POST', [
        "Cookie: {$cookieSession}",
        'Content-Type: application/json',
        'Accept: application/json',
        "X-CSRF-Token: {$csrfToken}",
    ], json_encode([
        'nueva_fecha_bloqueo_fin' => '2031-05-16',
        '_csrf' => $csrfToken,
    ]));
    $jsonProrroga = json_decode($rProrroga['body'], true);
    $prorrogaOk = ($rProrroga['code'] === 200 && ($jsonProrroga['ok'] ?? false) === true);

    // Luego completamos la orden liberando noches
    $rCompletar = curlRequest("{$baseUrl}/api/mantenimiento/ordenes/{$ordenId}/completar", 'POST', [
        "Cookie: {$cookieSession}",
        'Content-Type: application/json',
        'Accept: application/json',
        "X-CSRF-Token: {$csrfToken}",
    ], json_encode([
        'notas_cierre' => 'Trabajos de fontanería finalizados con prueba de presión positiva',
        'fecha_efectiva_liberacion' => '2031-05-12', // Liberación anticipada
        '_csrf' => $csrfToken,
    ]));
    $jsonCompletar = json_decode($rCompletar['body'], true);

    // Noches [2031-05-10, 2031-05-12) consumidas (2 noches), noches [2031-05-12, 2031-05-16) liberadas (0 bloqueadas)
    $nochesBloqueadasRestantes = (int) $pdo->query("SELECT COUNT(*) FROM inventario_diario_unidades WHERE unidad_id = {$unidadPruebaId} AND fecha >= '2031-05-12' AND fecha < '2031-05-16' AND tipo_bloqueo = 'MANTENIMIENTO'")->fetchColumn();
    $nochesConsumidas = (int) $pdo->query("SELECT COUNT(*) FROM inventario_diario_unidades WHERE unidad_id = {$unidadPruebaId} AND fecha >= '2031-05-10' AND fecha < '2031-05-12' AND tipo_bloqueo = 'MANTENIMIENTO'")->fetchColumn();

    $completarOk = ($prorrogaOk && $rCompletar['code'] === 200 && ($jsonCompletar['ok'] ?? false) === true &&
                    ($jsonCompletar['datos']['estado'] ?? '') === 'COMPLETADA' &&
                    $nochesBloqueadasRestantes === 0 && $nochesConsumidas === 2);

    checkE2E('E2E-MNT-14', 'Prórroga y finalización de orden libera inventario respetando consumo histórico (HTTP 200)', $completarOk, "Code: {$rCompletar['code']}, Consumidas: {$nochesConsumidas}, Restantes: {$nochesBloqueadasRestantes}");

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
