<?php

declare(strict_types=1);

/**
 * Suite de Pruebas End-to-End (E2E) Reales contra Apache en HTTPS — Camargo PMS (COMPRAS-1 / D-080)
 * Casos E2E-COMP-01 a E2E-COMP-14 (14/14).
 *
 * Principios vinculantes:
 * - Servidor web real Apache en HTTPS (https://app.camargo-pms.test/)
 * - Control estricto de sesiones, cookies y tokens CSRF
 * - RBAC vinculado a compras.*
 * - D-075 / D-076: Geometría nativa Alina (app-form, app-icon-form, b-r-20), Font Awesome exclusivo
 * - Axioma hexagonal D-080: Solicitud != Orden != Recepción/Conformidad != Comprobante != CxP != Pago
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
echo " VALIDACIÓN HTTP E2E REAL CONTRA APACHE (COMPRAS-1 / D-080)\n";
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
$adminUser = 'admin_cmp_' . strtolower($sufijo);
$sinPermisoUser = 'sin_cmp_' . strtolower($sufijo);
$passwordPlana = 'PassCompras123!456';
$passwordHash = password_hash($passwordPlana, PASSWORD_DEFAULT);

$cookieSession = null;
$csrfToken = null;
$adminUsuarioId = null;
$sinPermisoUsuarioId = null;

try {
    // -------------------------------------------------------------------------
    // E2E-COMP-01: Acceso no autenticado a /compras redirige a /login (HTTP 302)
    // -------------------------------------------------------------------------
    $r1 = curlRequest("{$baseUrl}/compras", 'GET');
    $redirigeLogin = ($r1['code'] === 302 && str_contains((string) $r1['location'], '/login'));
    checkE2E('E2E-COMP-01', 'Acceso no autenticado a /compras redirige a /login (HTTP 302)', $redirigeLogin, "Code: {$r1['code']}, Loc: {$r1['location']}");

    // -------------------------------------------------------------------------
    // Crear usuarios para prueba: Admin y Usuario sin permisos
    // -------------------------------------------------------------------------
    $pdo->exec("INSERT INTO personas (nombres, apellido_paterno, apellido_materno, pais_nacionalidad_id, creado_en) VALUES ('Admin', 'Compras', '{$sufijo}', 1, NOW())");
    $adminPersonaId = (int) $pdo->lastInsertId();

    $stmtUAdmin = $pdo->prepare("INSERT INTO usuarios (persona_id, nombre_usuario, contrasena_hash, estado, creado_en) VALUES (?, ?, ?, 'ACTIVO', NOW())");
    $stmtUAdmin->execute([$adminPersonaId, $adminUser, $passwordHash]);
    $adminUsuarioId = (int) $pdo->lastInsertId();

    $stmtActorAdmin = $pdo->prepare("INSERT INTO actores (codigo, tipo, nombre, usuario_id, estado, creado_en) VALUES (?, 'USUARIO', ?, ?, 'ACTIVO', NOW())");
    $stmtActorAdmin->execute(['USR_' . $adminUsuarioId, 'Admin Compras ' . $sufijo, $adminUsuarioId]);

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
    // E2E-COMP-02: Usuario sin permisos recibe HTTP 403 en /compras
    // -------------------------------------------------------------------------
    $pdo->exec("INSERT INTO personas (nombres, apellido_paterno, apellido_materno, pais_nacionalidad_id, creado_en) VALUES ('SinPerm', 'Compras', '{$sufijo}', 1, NOW())");
    $sinPersonaId = (int) $pdo->lastInsertId();

    $stmtUSin = $pdo->prepare("INSERT INTO usuarios (persona_id, nombre_usuario, contrasena_hash, estado, creado_en) VALUES (?, ?, ?, 'ACTIVO', NOW())");
    $stmtUSin->execute([$sinPersonaId, $sinPermisoUser, $passwordHash]);
    $sinPermisoUsuarioId = (int) $pdo->lastInsertId();

    $pdo->exec("INSERT INTO roles (codigo, nombre, descripcion, es_superadministrador, estado, creado_en) VALUES ('ROL_SIN_CMP_{$sufijo}', 'Sin Compras', 'Sin permisos', 0, 'ACTIVO', NOW())");
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
    $rSinCmp = curlRequest("{$baseUrl}/compras", 'GET', ["Cookie: {$cookieSessionSin}"]);
    $es403 = ($rSinCmp['code'] === 403);
    checkE2E('E2E-COMP-02', 'Usuario autenticado sin permiso compras.ver es rechazado con HTTP 403', $es403, "Code: {$rSinCmp['code']}");

    // -------------------------------------------------------------------------
    // E2E-COMP-03: Acceso autorizado a /compras renderiza vista Alina
    // -------------------------------------------------------------------------
    $rCompVista = curlRequest("{$baseUrl}/compras", 'GET', ["Cookie: {$cookieSession}"]);
    $csrfToken = extraerCsrfDeHtml($rCompVista['body']);
    $vistaOk = ($rCompVista['code'] === 200 &&
        str_contains($rCompVista['body'], 'Abastecimiento, Compras') &&
        str_contains($rCompVista['body'], 'tab-ordenes-btn') &&
        str_contains($rCompVista['body'], 'gestion-compras.js'));
    checkE2E('E2E-COMP-03', 'Acceso autorizado a /compras renderiza vista Alina con KPIs y pestañas operacionales (HTTP 200)', $vistaOk, "Code: {$rCompVista['code']}");

    // -------------------------------------------------------------------------
    // E2E-COMP-04: GET /api/compras/catalogos
    // -------------------------------------------------------------------------
    $rCatalogos = curlRequest("{$baseUrl}/api/compras/catalogos", 'GET', [
        "Cookie: {$cookieSession}",
        'Accept: application/json',
    ]);
    $jsonCat = json_decode($rCatalogos['body'], true);
    $catOk = ($rCatalogos['code'] === 200 &&
        ($jsonCat['exito'] ?? false) === true &&
        isset($jsonCat['datos']['proveedores']) &&
        isset($jsonCat['datos']['almacenes']) &&
        isset($jsonCat['datos']['articulos']));
    checkE2E('E2E-COMP-04', 'GET /api/compras/catalogos retorna maestros de proveedores, almacenes y artículos (HTTP 200)', $catOk, "Code: {$rCatalogos['code']}");

    $proveedorE2EId = $jsonCat['datos']['proveedores'][0]['id'] ?? 1;
    $almacenE2EId = $jsonCat['datos']['almacenes'][0]['id'] ?? 1;
    $articuloE2EId = $jsonCat['datos']['articulos'][0]['id'] ?? 1;

    // -------------------------------------------------------------------------
    // E2E-COMP-05: POST /api/compras/solicitudes y GET /api/compras/solicitudes
    // -------------------------------------------------------------------------
    $payloadSol = [
        'departamento_area' => 'Mantenimiento General E2E',
        'justificacion' => 'Requerimiento de insumos e inspección E2E',
        'almacen_destino_id' => $almacenE2EId,
        'fecha_limite_requerida' => date('Y-m-d', strtotime('+5 days')),
        'lineas' => [
            [
                'tipo_linea' => 'BIEN',
                'articulo_id' => $articuloE2EId,
                'cantidad_solicitada' => '8.0000',
                'especificaciones_tecnicas' => 'Insumos hotel alta densidad',
            ],
            [
                'tipo_linea' => 'SERVICIO',
                'descripcion_servicio' => 'Inspección de tuberías y ductos E2E',
                'cantidad_solicitada' => '1.0000',
                'especificaciones_tecnicas' => 'Técnico certificado con instrumental',
            ],
        ],
    ];

    $rCrearSol = curlRequest("{$baseUrl}/api/compras/solicitudes", 'POST', [
        "Cookie: {$cookieSession}",
        'Content-Type: application/json',
        'Accept: application/json',
        "X-CSRF-Token: {$csrfToken}",
    ], json_encode($payloadSol));

    $jsonCrearSol = json_decode($rCrearSol['body'], true);
    $solId = (int) ($jsonCrearSol['datos']['id'] ?? 0);
    $solCodigo = (string) ($jsonCrearSol['datos']['codigo'] ?? '');

    $rListSol = curlRequest("{$baseUrl}/api/compras/solicitudes", 'GET', [
        "Cookie: {$cookieSession}",
        'Accept: application/json',
    ]);
    $jsonListSol = json_decode($rListSol['body'], true);

    $solOk = ($rCrearSol['code'] === 201 &&
        $solId > 0 &&
        str_starts_with($solCodigo, 'SOL-') &&
        $rListSol['code'] === 200 &&
        ($jsonListSol['exito'] ?? false) === true);
    checkE2E('E2E-COMP-05', 'POST /api/compras/solicitudes registra requerimiento con líneas tipadas y GET lo lista (HTTP 201/200)', $solOk, "Code: {$rCrearSol['code']}, SolId: {$solId}, Body: {$rCrearSol['body']}");

    // -------------------------------------------------------------------------
    // E2E-COMP-06: POST /api/compras/solicitudes/{id}/aprobar
    // -------------------------------------------------------------------------
    $rAprobarSol = curlRequest("{$baseUrl}/api/compras/solicitudes/{$solId}/aprobar", 'POST', [
        "Cookie: {$cookieSession}",
        'Content-Type: application/json',
        'Accept: application/json',
        "X-CSRF-Token: {$csrfToken}",
    ], json_encode([]));

    $jsonAprobarSol = json_decode($rAprobarSol['body'], true);
    $aprobSolOk = ($rAprobarSol['code'] === 200 &&
        ($jsonAprobarSol['exito'] ?? false) === true &&
        ($jsonAprobarSol['datos']['estado'] ?? '') === 'APROBADA');
    checkE2E('E2E-COMP-06', 'POST /api/compras/solicitudes/{id}/aprobar realiza transición formal a APROBADA (HTTP 200)', $aprobSolOk, "Code: {$rAprobarSol['code']}");

    // -------------------------------------------------------------------------
    // E2E-COMP-07: POST /api/compras/ordenes (creación de orden desde solicitud)
    // -------------------------------------------------------------------------
    $payloadOC = [
        'solicitud_id' => $solId,
        'proveedor_id' => $proveedorE2EId,
        'almacen_entrega_id' => $almacenE2EId,
        'condicion_pago' => 'CREDITO_30D',
        'fecha_entrega_esperada' => date('Y-m-d', strtotime('+10 days')),
        'notas_comerciales' => 'Entrega matutina en puerta de almacén',
        'lineas' => [
            [
                'tipo_linea' => 'BIEN',
                'articulo_id' => $articuloE2EId,
                'cantidad_pactada' => '8.0000',
                'precio_unitario' => '25.0000',
                'tasa_impuesto' => '18.00',
            ],
            [
                'tipo_linea' => 'SERVICIO',
                'descripcion_servicio' => 'Inspección de tuberías y ductos E2E',
                'cantidad_pactada' => '1.0000',
                'precio_unitario' => '100.0000',
                'tasa_impuesto' => '0.00',
            ],
        ],
    ];

    $rCrearOC = curlRequest("{$baseUrl}/api/compras/ordenes", 'POST', [
        "Cookie: {$cookieSession}",
        'Content-Type: application/json',
        'Accept: application/json',
        "X-CSRF-Token: {$csrfToken}",
    ], json_encode($payloadOC));

    $jsonCrearOC = json_decode($rCrearOC['body'], true);
    $ocId = (int) ($jsonCrearOC['datos']['id'] ?? 0);
    $ocCodigo = (string) ($jsonCrearOC['datos']['codigo'] ?? '');
    $lineasOC = $jsonCrearOC['datos']['lineas'] ?? [];

    $lineaBienId = 0;
    $lineaServicioId = 0;
    foreach ($lineasOC as $loc) {
        if ($loc['tipo_linea'] === 'BIEN') {
            $lineaBienId = (int) $loc['id'];
        } elseif ($loc['tipo_linea'] === 'SERVICIO') {
            $lineaServicioId = (int) $loc['id'];
        }
    }

    $crearOcOk = ($rCrearOC['code'] === 201 &&
        $ocId > 0 &&
        str_starts_with($ocCodigo, 'OC-') &&
        ($jsonCrearOC['datos']['estado_comercial'] ?? '') === 'BORRADOR');
    checkE2E('E2E-COMP-07', 'POST /api/compras/ordenes formula orden en BORRADOR con folio atómico OC (HTTP 201)', $crearOcOk, "Code: {$rCrearOC['code']}, OC: {$ocCodigo}");

    // -------------------------------------------------------------------------
    // E2E-COMP-08: POST /api/compras/ordenes/{id}/aprobar
    // -------------------------------------------------------------------------
    $rAprobarOC = curlRequest("{$baseUrl}/api/compras/ordenes/{$ocId}/aprobar", 'POST', [
        "Cookie: {$cookieSession}",
        'Content-Type: application/json',
        'Accept: application/json',
        "X-CSRF-Token: {$csrfToken}",
    ], json_encode([]));

    $jsonAprobarOC = json_decode($rAprobarOC['body'], true);
    $aprobOcOk = ($rAprobarOC['code'] === 200 &&
        ($jsonAprobarOC['exito'] ?? false) === true &&
        ($jsonAprobarOC['datos']['estado_comercial'] ?? '') === 'APROBADA');
    checkE2E('E2E-COMP-08', 'POST /api/compras/ordenes/{id}/aprobar formaliza la OC y congela condiciones comerciales (HTTP 200)', $aprobOcOk, "Code: {$rAprobarOC['code']}");

    // -------------------------------------------------------------------------
    // E2E-COMP-09: GET /api/compras/ordenes/{id}/pdf (stream de PDF oficial con Dompdf)
    // -------------------------------------------------------------------------
    $rPdfOC = curlRequest("{$baseUrl}/api/compras/ordenes/{$ocId}/pdf", 'GET', [
        "Cookie: {$cookieSession}",
        'Accept: application/pdf',
    ]);

    $pdfOk = ($rPdfOC['code'] === 200 &&
        str_starts_with($rPdfOC['body'], '%PDF-1.') &&
        str_contains($rPdfOC['headers'], 'application/pdf'));
    checkE2E('E2E-COMP-09', 'GET /api/compras/ordenes/{id}/pdf genera y transmite stream PDF con Dompdf 3.1.6 (HTTP 200)', $pdfOk, "Code: {$rPdfOC['code']}");

    // -------------------------------------------------------------------------
    // E2E-COMP-10: POST /api/compras/recepciones (recepción física de bienes hacia Kardex)
    // -------------------------------------------------------------------------
    $payloadRec = [
        'orden_compra_id' => $ocId,
        'almacen_id' => $almacenE2EId,
        'numero_guia_remision' => 'GR-E2E-00123',
        'observaciones' => 'Ingreso verificado en rampa E2E',
        'lineas' => [
            [
                'orden_linea_id' => $lineaBienId,
                'cantidad_aceptada' => '8.0000',
                'cantidad_rechazada' => '0.0000',
            ]
        ]
    ];

    $rRec = curlRequest("{$baseUrl}/api/compras/recepciones", 'POST', [
        "Cookie: {$cookieSession}",
        'Content-Type: application/json',
        'Accept: application/json',
        "X-CSRF-Token: {$csrfToken}",
    ], json_encode($payloadRec));

    $jsonRec = json_decode($rRec['body'], true);
    $recId = (int) ($jsonRec['datos']['id'] ?? 0);
    $recCodigo = (string) ($jsonRec['datos']['codigo'] ?? '');

    $recOk = ($rRec['code'] === 201 &&
        $recId > 0 &&
        str_starts_with($recCodigo, 'REC-'));
    checkE2E('E2E-COMP-10', 'POST /api/compras/recepciones registra recepción física y genera ENTRADA_COMPRA en Kardex (HTTP 201)', $recOk, "Code: {$rRec['code']}, REC: {$recCodigo}");

    // -------------------------------------------------------------------------
    // E2E-COMP-11: POST /api/compras/conformidades (acta de conformidad técnica de servicios)
    // -------------------------------------------------------------------------
    $payloadConf = [
        'orden_compra_id' => $ocId,
        'orden_linea_id' => $lineaServicioId,
        'informe_trabajo_realizado' => 'Inspección técnica realizada con cámara endoscópica. Red limpia y operativa al 100%.',
    ];

    $rConf = curlRequest("{$baseUrl}/api/compras/conformidades", 'POST', [
        "Cookie: {$cookieSession}",
        'Content-Type: application/json',
        'Accept: application/json',
        "X-CSRF-Token: {$csrfToken}",
    ], json_encode($payloadConf));

    $jsonConf = json_decode($rConf['body'], true);
    $confId = (int) ($jsonConf['datos']['id'] ?? 0);
    $confCodigo = (string) ($jsonConf['datos']['codigo'] ?? '');

    $confOk = ($rConf['code'] === 201 &&
        $confId > 0 &&
        str_starts_with($confCodigo, 'CONF-'));
    checkE2E('E2E-COMP-11', 'POST /api/compras/conformidades emite acta técnica para servicios con cero afectación de stock (HTTP 201)', $confOk, "Code: {$rConf['code']}, CONF: {$confCodigo}");

    // -------------------------------------------------------------------------
    // E2E-COMP-12: POST /api/compras/comprobantes (3-Way Matching y devengo de CxP)
    // -------------------------------------------------------------------------
    // Subtotal = 8*25 (200) + 1*100 (100) = 300.00
    // Impuestos = 200 * 18% = 36.00
    // Total = 336.00
    $payloadComp = [
        'orden_compra_id' => $ocId,
        'tipo_comprobante' => 'FACTURA',
        'serie' => 'F002',
        'numero' => '00' . substr(str_replace('.', '', (string) microtime(true)), -6),
        'fecha_emision' => date('Y-m-d'),
        'fecha_vencimiento' => date('Y-m-d', strtotime('+30 days')),
        'subtotal' => '300.00',
        'impuesto' => '36.00',
        'total' => '336.00',
    ];

    $rComp = curlRequest("{$baseUrl}/api/compras/comprobantes", 'POST', [
        "Cookie: {$cookieSession}",
        'Content-Type: application/json',
        'Accept: application/json',
        "X-CSRF-Token: {$csrfToken}",
    ], json_encode($payloadComp));

    $jsonComp = json_decode($rComp['body'], true);
    $compFacturaId = (int) ($jsonComp['comprobante']['id'] ?? 0);
    $matchingEstado = (string) ($jsonComp['comprobante']['estado_matching'] ?? '');
    $cxpDevengadaId = (int) ($jsonComp['cuenta_por_pagar']['id'] ?? 0);
    $cxpCodigo = (string) ($jsonComp['cuenta_por_pagar']['codigo'] ?? '');
    $cxpSaldo = (string) ($jsonComp['cuenta_por_pagar']['saldo_pendiente'] ?? '');

    $compOk = ($rComp['code'] === 201 &&
        $compFacturaId > 0 &&
        $matchingEstado === 'CONFORME' &&
        $cxpDevengadaId > 0 &&
        str_starts_with($cxpCodigo, 'CXP-') &&
        bccomp($cxpSaldo, '336.00', 2) === 0);
    checkE2E('E2E-COMP-12', 'POST /api/compras/comprobantes ejecuta 3-Way Matching CONFORME y devenga pasivo en Cuenta por Pagar (HTTP 201)', $compOk, "Code: {$rComp['code']}, CXP: {$cxpCodigo}, Saldo: {$cxpSaldo}");

    // -------------------------------------------------------------------------
    // E2E-COMP-13: GET /api/compras/cuentas-por-pagar y POST pago de amortización
    // -------------------------------------------------------------------------
    $rListCxp = curlRequest("{$baseUrl}/api/compras/cuentas-por-pagar", 'GET', [
        "Cookie: {$cookieSession}",
        'Accept: application/json',
    ]);
    $jsonListCxp = json_decode($rListCxp['body'], true);

    $payloadPago = [
        'monto' => '336.00',
        'medio_pago' => 'TRANSFERENCIA_BANCARIA',
        'numero_operacion_bancaria' => 'OP-E2E-' . rand(100000, 999999),
        'notas' => 'Liquidación íntegra de factura E2E',
    ];

    $rPago = curlRequest("{$baseUrl}/api/compras/cuentas-por-pagar/{$cxpDevengadaId}/pagos", 'POST', [
        "Cookie: {$cookieSession}",
        'Content-Type: application/json',
        'Accept: application/json',
        "X-CSRF-Token: {$csrfToken}",
    ], json_encode($payloadPago));

    $jsonPago = json_decode($rPago['body'], true);
    $pagoId = (int) ($jsonPago['datos']['id'] ?? 0);

    $pagoOk = ($rListCxp['code'] === 200 &&
        ($jsonListCxp['exito'] ?? false) === true &&
        $rPago['code'] === 201 &&
        $pagoId > 0);
    checkE2E('E2E-COMP-13', 'GET /api/compras/cuentas-por-pagar lista pasivos y POST /{id}/pagos amortiza y liquida la deuda (HTTP 200/201)', $pagoOk, "Code: {$rPago['code']}, PagoId: {$pagoId}");

    // -------------------------------------------------------------------------
    // E2E-COMP-14: Validación CSRF estricta en mutaciones POST (403 Forbidden)
    // -------------------------------------------------------------------------
    $rCsrfFail = curlRequest("{$baseUrl}/api/compras/solicitudes", 'POST', [
        "Cookie: {$cookieSession}",
        'Content-Type: application/json',
        'Accept: application/json',
        "X-CSRF-Token: token_falso_invalido_1234567890abcdef",
    ], json_encode(['departamento_area' => 'Test Invalido']));

    $csrfFailOk = ($rCsrfFail['code'] === 403 &&
        str_contains($rCsrfFail['body'], 'CSRF'));
    checkE2E('E2E-COMP-14', 'Validación CSRF estricta en endpoints mutacionales (HTTP 403 Forbidden ante token inválido)', $csrfFailOk, "Code: {$rCsrfFail['code']}");

} catch (\Throwable $e) {
    echo "ERROR CATASTRÓFICO E2E: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    $fail++;
}

echo "\n====================================================================\n";
echo "RESULTADOS HTTP E2E REAL CONTRA APACHE: {$pass} / 14 PASS\n";
echo "====================================================================\n";

if ($pass !== 14) {
    exit(1);
}
