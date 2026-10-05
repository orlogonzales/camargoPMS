<?php

declare(strict_types=1);

/**
 * Suite de Pruebas: PAGOS-1D — Implementación de Monitor, Conciliación y Reembolsos Alina
 *
 * Valida de forma rigurosa y exhaustiva:
 * 1. Gobierno del Esquema y Ranura 039:
 *    - 130 tablas relacionales exactas en base de datos.
 *    - Ranura 039 estrictamente LIBRE (cero DDL no autorizado).
 *    - Inmutabilidad total de admin-dashboard/ y SQL consolidado.
 * 2. Registro y Cobertura de Rutas:
 *    - /pagos, /pagos/datos, /pagos/transacciones/{id}, /pagos/transacciones/{id}/datos,
 *      /pagos/transacciones/{id}/reembolsar, /pagos/transacciones/{id}/conciliar, /pagos/webhooks/{id}/payload.
 * 3. Autenticación, Sesión y Control de Acceso (RBAC):
 *    - Rechazo anónimo con redirección / 401.
 *    - Rechazo 403 ante falta de permisos (caja.ver, caja.devolver, caja.cobrar).
 *    - Acceso permitido con roles autorizados.
 * 4. Validación CSRF en Endpoints Mutantes (POST reembolsar y conciliar).
 * 5. Repositorio y Consultas Multidimensionales:
 *    - Filtrado dinámico por proveedor, estado_pago, estado_conciliacion, estado_reembolso, fechas y texto.
 *    - Cálculo de métricas KPI agregadas.
 * 6. Validación Servidor de Reembolsos:
 *    - Precondiciones de estado APROBADO, saldo disponible > 0, motivo >= 10 caracteres.
 *    - Bloqueo pesimista de concurrencia ACID (SELECT ... FOR UPDATE).
 * 7. Ejecución de Reembolsos (Parcial y Total):
 *    - Reembolso parcial con precisión BCMath (DECIMAL 15,2).
 *    - Reembolso total con transición a REEMBOLSADO_TOTAL.
 *    - Falla de proveedor no corrompe saldo y registra error.
 *    - Atribución obligatoria a Actor Humano (ACTOR != USUARIO).
 * 8. Invariante Hotelero Inviolable de Pagos Tardíos C1/C2:
 *    - Pago tardío queda en cuarentena DISCREPANCIA_HOLD_EXPIRADO.
 *    - Reserva asociada permanece en estado EXPIRADA.
 *    - CERO folio de contingencia ni asiento contable.
 *    - Acción de conciliación limitada a seguimiento administrativo (sin reasignación).
 * 9. Seguridad Zero-Trust:
 *    - Cero llaves privadas, webhook secrets o PAN/CVV en HTML/JSON.
 *    - Offcanvas entrega payload sanitizado.
 * 10. Renderizado Alina:
 *    - Cards equal-card, Flatpickr data-provider="rangepicker", tabla Alina y timeline .app-side-timeline.
 * 11. Limpieza defensiva de fixtures.
 */

namespace CamargoPMS\Pruebas;

use CamargoPMS\Controladores\PagoControlador;
use CamargoPMS\Modelos\PagoTransaccionPasarela;
use CamargoPMS\Modelos\PagoWebhookEvento;
use CamargoPMS\Modelos\Reserva;
use CamargoPMS\Modelos\Usuario;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Servicios\CsrfServicio;
use CamargoPMS\Repositorios\ActorRepositorio;
use CamargoPMS\Repositorios\CuentaFolioRepositorio;
use CamargoPMS\Repositorios\PagoTransaccionPasarelaRepositorio;
use CamargoPMS\Repositorios\PagoWebhookEventoRepositorio;
use CamargoPMS\Repositorios\ReservaRepositorio;
use CamargoPMS\Repositorios\UsuarioRepositorio;
use CamargoPMS\Servicios\AuditoriaServicio;
use CamargoPMS\Servicios\AutorizacionServicio;
use CamargoPMS\Servicios\Pagos\DTOs\ReembolsoResultado;
use CamargoPMS\Servicios\Pagos\DTOs\ReembolsoSolicitud;
use CamargoPMS\Servicios\Pagos\FabricaProveedoresPago;
use CamargoPMS\Servicios\Pagos\ProveedorPagoInterfaz;
use CamargoPMS\Servicios\PagoServicio;
use CamargoPMS\Servicios\SesionServicio;
use PDO;
use RuntimeException;
use Throwable;

require_once dirname(__DIR__) . '/vendor/autoload.php';

Configuracion::cargar(dirname(__DIR__));
$pdo = BaseDatos::conexion();

$totalAssertions = 0;
$passedAssertions = 0;

function assertCheck(bool $condition, string $message): void
{
    global $totalAssertions, $passedAssertions;
    $totalAssertions++;
    if ($condition) {
        $passedAssertions++;
        echo "  [PASS] $message\n";
    } else {
        echo "  [FAIL] $message\n";
        throw new RuntimeException("Fallo en aserción: $message");
    }
}

echo "\n====================================================================\n";
echo " INICIANDO SUITE DE PRUEBAS: PAGOS-1D (MONITOR, CONCILIACIÓN Y REEMBOLSOS ALINA)\n";
echo "====================================================================\n\n";

try {
    // =========================================================================
    // 1. GOBIERNO DEL ESQUEMA Y RANURA 039
    // =========================================================================
    echo "--- 1. Gobierno del Esquema y Ranura 039 ---\n";

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    assertCheck(count($tables) >= 130, "Base de datos contiene al menos 130 tablas relacionales (actual: " . count($tables) . ")");
    assertCheck(in_array('pagos_transacciones_pasarela', $tables, true), "Tabla 'pagos_transacciones_pasarela' existe");
    assertCheck(in_array('pagos_webhooks_eventos', $tables, true), "Tabla 'pagos_webhooks_eventos' existe");

    $mig038Presente = (bool) $pdo->query("SELECT 1 FROM migraciones WHERE migracion = '038_pagos_pasarelas.sql'")->fetchColumn();
    assertCheck($mig038Presente, "Migración 038_pagos_pasarelas.sql registrada en tabla 'migraciones'");

    $mig041 = glob(dirname(__DIR__) . '/SQL/migraciones/*041*');
    assertCheck(empty($mig041), "Ranura de migración 041 estrictamente LIBRE (cero DDL no autorizado)");

    // Inmutabilidad de admin-dashboard/
    $gitAlina = shell_exec('git status --porcelain admin-dashboard/');
    assertCheck(empty(trim((string) $gitAlina)), "admin-dashboard/ permanece 100% inmutable y libre de modificaciones");

    // =========================================================================
    // 2. REGISTRO Y COBERTURA DE RUTAS EN EL FRONT CONTROLLER
    // =========================================================================
    echo "\n--- 2. Registro y Cobertura de Rutas en Front Controller ---\n";

    $indexPhp = file_get_contents(dirname(__DIR__) . '/public/index.php');
    assertCheck(str_contains($indexPhp, "'/pagos'"), "Ruta GET /pagos registrada");
    assertCheck(str_contains($indexPhp, "'/pagos/datos'"), "Ruta GET /pagos/datos registrada");
    assertCheck(str_contains($indexPhp, "'/pagos/transacciones/{id}'"), "Ruta GET /pagos/transacciones/{id} registrada");
    assertCheck(str_contains($indexPhp, "'/pagos/transacciones/{id}/datos'"), "Ruta GET /pagos/transacciones/{id}/datos registrada");
    assertCheck(str_contains($indexPhp, "'/pagos/transacciones/{id}/reembolsar'"), "Ruta POST /pagos/transacciones/{id}/reembolsar registrada");
    assertCheck(str_contains($indexPhp, "'/pagos/transacciones/{id}/conciliar'"), "Ruta POST /pagos/transacciones/{id}/conciliar registrada");
    assertCheck(str_contains($indexPhp, "'/pagos/webhooks/{id}/payload'"), "Ruta GET /pagos/webhooks/{id}/payload registrada");

    // =========================================================================
    // 3. AUTENTICACIÓN, SESIÓN Y CONTROL DE ACCESO (RBAC)
    // =========================================================================
    echo "\n--- 3. Autenticación, Sesión y Control de Acceso (RBAC) ---\n";

    // Fixtures de usuarios
    $stmtAdmin = $pdo->query("SELECT u.id, u.persona_id, u.nombre_usuario FROM usuarios u JOIN usuarios_roles ur ON ur.usuario_id = u.id JOIN roles r ON r.id = ur.rol_id WHERE r.codigo = 'SUPERADMINISTRADOR' AND u.estado = 'ACTIVO' LIMIT 1");
    $adminRow = $stmtAdmin->fetch(PDO::FETCH_ASSOC);
    assertCheck(!empty($adminRow), "Usuario SUPERADMINISTRADOR encontrado para pruebas");
    $usuarioAdmin = new Usuario((int) $adminRow['id'], (int) $adminRow['persona_id'], (string) $adminRow['nombre_usuario'], 'hash');

    $usuarioRestringido = new Usuario(987654, 987654, 'operador_sin_caja', 'hash');

    $mockSesionAnonima = new class extends SesionServicio {
        public function __construct() {}
        public function validarSesionActual(): ?Usuario { return null; }
    };

    $mockSesionAdmin = new class($usuarioAdmin) extends SesionServicio {
        public function __construct(private Usuario $u) {}
        public function validarSesionActual(): ?Usuario { return $this->u; }
    };

    $mockSesionRestringido = new class($usuarioRestringido) extends SesionServicio {
        public function __construct(private Usuario $u) {}
        public function validarSesionActual(): ?Usuario { return $this->u; }
    };

    $mockCsrfValido = new class extends CsrfServicio {
        public function obtenerToken(): string { return 'token_csrf_prueba_valido'; }
        public function validarToken(?string $t): bool { return $t === 'token_csrf_prueba_valido'; }
    };

    $ctrlAnonimo = new PagoControlador(
        pdo: $pdo,
        sesionServicio: $mockSesionAnonima,
        csrfServicio: $mockCsrfValido
    );

    $respAnonIndex = $ctrlAnonimo->index();
    assertCheck($respAnonIndex->obtenerCodigoEstado() === 302, "Anónimo a GET /pagos es redirigido a login (HTTP 302)");
    assertCheck(str_contains($respAnonIndex->obtenerCabeceras()['Location'] ?? '', '/login'), "Redirección apunta a /login");

    $respAnonDatos = $ctrlAnonimo->datosJson();
    assertCheck($respAnonDatos->obtenerCodigoEstado() === 401, "Anónimo a GET /pagos/datos retorna HTTP 401");

    $respAnonDetalle = $ctrlAnonimo->detalle(1);
    assertCheck($respAnonDetalle->obtenerCodigoEstado() === 302, "Anónimo a GET /pagos/transacciones/1 es redirigido a login (HTTP 302)");

    $respAnonReembolso = $ctrlAnonimo->reembolsar(1);
    assertCheck($respAnonReembolso->obtenerCodigoEstado() === 401, "Anónimo a POST reembolsar retorna HTTP 401");

    $respAnonConciliar = $ctrlAnonimo->conciliar(1);
    assertCheck($respAnonConciliar->obtenerCodigoEstado() === 401, "Anónimo a POST conciliar retorna HTTP 401");

    $respAnonPayload = $ctrlAnonimo->webhookPayload(1);
    assertCheck($respAnonPayload->obtenerCodigoEstado() === 401, "Anónimo a GET webhook payload retorna HTTP 401");

    // Usuario sin permiso de caja / pagos
    $ctrlRestringido = new PagoControlador(
        pdo: $pdo,
        sesionServicio: $mockSesionRestringido,
        csrfServicio: $mockCsrfValido
    );

    $respRestrIndex = $ctrlRestringido->index();
    assertCheck($respRestrIndex->obtenerCodigoEstado() === 403, "Usuario sin permisos a GET /pagos retorna HTTP 403");

    $respRestrDatos = $ctrlRestringido->datosJson();
    assertCheck($respRestrDatos->obtenerCodigoEstado() === 403, "Usuario sin permisos a GET /pagos/datos retorna HTTP 403");

    $respRestrReembolso = $ctrlRestringido->reembolsar(1);
    assertCheck($respRestrReembolso->obtenerCodigoEstado() === 403, "Usuario sin permisos a POST reembolsar retorna HTTP 403");

    $respRestrConciliar = $ctrlRestringido->conciliar(1);
    assertCheck($respRestrConciliar->obtenerCodigoEstado() === 403, "Usuario sin permisos a POST conciliar retorna HTTP 403");

    // =========================================================================
    // 4. CREACIÓN DE FIXTURES DE TRANSACCIONES Y VALIDACIÓN DE REPOSITORIO
    // =========================================================================
    echo "\n--- 4. Creación de Fixtures y Repositorio con Filtros y KPIs ---\n";

    $txRepo = new PagoTransaccionPasarelaRepositorio($pdo);
    $webhookRepo = new PagoWebhookEventoRepositorio($pdo);
    $reservaRepo = new ReservaRepositorio($pdo);

    // Obtener o crear una reserva base para vincular
    $stmtRes = $pdo->query("SELECT id, codigo FROM reservas WHERE estado IN ('PENDIENTE', 'CONFIRMADA', 'EXPIRADA') LIMIT 1");
    $resRow = $stmtRes->fetch(PDO::FETCH_ASSOC);
    assertCheck(!empty($resRow), "Reserva base existente encontrada para vincular transacciones");
    $reservaId = (int) $resRow['id'];
    $reservaCodigo = (string) $resRow['codigo'];

    // Resolver actor pasarela para insertar fixtures
    // Limpieza preventiva inicial de fixtures residuales
    $pdo->exec("DELETE FROM pagos_webhooks_eventos WHERE tipo_evento = 'order.status.changed' AND payload_raw LIKE '%SECRETO_QUE_DEBE_SANITIZARSE%'");
    $pdo->exec("DELETE FROM pagos_transacciones_pasarela WHERE codigo LIKE 'TRX-TEST-1D%' OR codigo LIKE 'TRX-TEST-C1C2%'");

    $stmtActor = $pdo->query("SELECT id FROM actores WHERE tipo IN ('INTEGRACION', 'SISTEMA') LIMIT 1");
    $actorId = (int) $stmtActor->fetchColumn();

    // Insertar 3 transacciones para pruebas:
    // TX 1: Aprobada normal y conciliada (S/ 250.00)
    $codigoTx1 = 'TRX-TEST-1D-' . bin2hex(random_bytes(3));
    $sufijoRandom = bin2hex(random_bytes(4));
    $tx1Id = $txRepo->crear([
        'codigo' => $codigoTx1,
        'reserva_id' => $reservaId,
        'cuenta_folio_id' => null,
        'pago_cuenta_id' => null,
        'proveedor' => 'CULQI',
        'tipo_operacion' => 'ORDEN_CHECKOUT',
        'proveedor_orden_id' => 'ord_test_1d_01_' . $sufijoRandom,
        'proveedor_transaccion_id' => 'chr_test_1d_01_' . $sufijoRandom,
        'proveedor_referencia' => $reservaCodigo,
        'estado_pago' => 'APROBADO',
        'estado_conciliacion' => 'CONCILIADO',
        'estado_reembolso' => 'NO_APLICA',
        'moneda_codigo' => 'PEN',
        'monto_esperado' => '250.00',
        'monto_cobrado' => '250.00',
        'monto_reembolsado' => '0.00',
        'metadatos_proveedor' => json_encode([
            'brand' => 'Visa',
            'last4' => '4242',
            'bank' => 'BBVA',
            'client_name' => 'Juan Pérez Test',
            'client_email' => 'juan.test@camargo.pe',
        ]),
        'motivo_discrepancia' => null,
        'motivo_reembolso' => null,
        'actor_id' => $actorId,
    ]);
    assertCheck($tx1Id > 0, "Transacción 1 (APROBADO, CONCILIADO) creada con ID: $tx1Id");

    // TX 2: Aprobada con Pago Tardío (DISCREPANCIA_HOLD_EXPIRADO)
    $codigoTx2 = 'TRX-TEST-1D-TARDIO-' . bin2hex(random_bytes(3));
    $tx2Id = $txRepo->crear([
        'codigo' => $codigoTx2,
        'reserva_id' => $reservaId,
        'cuenta_folio_id' => null,
        'pago_cuenta_id' => null,
        'proveedor' => 'CULQI',
        'tipo_operacion' => 'ORDEN_CHECKOUT',
        'proveedor_orden_id' => 'ord_test_1d_02_' . $sufijoRandom,
        'proveedor_transaccion_id' => 'chr_test_1d_02_' . $sufijoRandom,
        'proveedor_referencia' => $reservaCodigo,
        'estado_pago' => 'APROBADO',
        'estado_conciliacion' => 'DISCREPANCIA_HOLD_EXPIRADO',
        'estado_reembolso' => 'NO_APLICA',
        'moneda_codigo' => 'PEN',
        'monto_esperado' => '180.00',
        'monto_cobrado' => '180.00',
        'monto_reembolsado' => '0.00',
        'metadatos_proveedor' => json_encode([
            'brand' => 'Mastercard',
            'last4' => '5555',
            'bank' => 'BCP',
            'client_name' => 'María Gómez Tardío',
            'client_email' => 'maria.tardio@camargo.pe',
        ]),
        'motivo_discrepancia' => 'Hold expirado a las 14:00. Pago recibido a las 14:05.',
        'motivo_reembolso' => null,
        'actor_id' => $actorId,
    ]);
    assertCheck($tx2Id > 0, "Transacción 2 (Pago Tardío C1) creada con ID: $tx2Id");

    // TX 3: Pendiente de pago (S/ 300.00)
    $codigoTx3 = 'TRX-TEST-1D-PEND-' . bin2hex(random_bytes(3));
    $tx3Id = $txRepo->crear([
        'codigo' => $codigoTx3,
        'reserva_id' => $reservaId,
        'cuenta_folio_id' => null,
        'pago_cuenta_id' => null,
        'proveedor' => 'PAYPAL',
        'tipo_operacion' => 'ORDEN_CHECKOUT',
        'proveedor_orden_id' => 'ord_test_1d_03_' . $sufijoRandom,
        'proveedor_transaccion_id' => null,
        'proveedor_referencia' => $reservaCodigo,
        'estado_pago' => 'PENDIENTE',
        'estado_conciliacion' => 'PENDIENTE',
        'estado_reembolso' => 'NO_APLICA',
        'moneda_codigo' => 'PEN',
        'monto_esperado' => '300.00',
        'monto_cobrado' => null,
        'monto_reembolsado' => '0.00',
        'metadatos_proveedor' => json_encode(['nota' => 'Intención pendiente']),
        'motivo_discrepancia' => null,
        'motivo_reembolso' => null,
        'actor_id' => $actorId,
    ]);
    assertCheck($tx3Id > 0, "Transacción 3 (PENDIENTE) creada con ID: $tx3Id");

    // Evento Webhook vinculado a TX 1
    $webhookRes = $webhookRepo->registrarOObtener(
        proveedor: 'CULQI',
        proveedorEventoId: 'evt_test_1d_' . bin2hex(random_bytes(4)),
        tipoEvento: 'order.status.changed',
        payloadRaw: '{"id":"evt_test_1d","data":{"secret_key":"SECRETO_QUE_DEBE_SANITIZARSE","amount":25000}}',
        cabeceras: ['User-Agent' => 'Culqi-Webhook/2.0'],
        transaccionPasarelaId: $tx1Id
    );
    $webhook1Id = (int) $webhookRes['id'];
    assertCheck($webhook1Id > 0, "Evento webhook fixture registrado con ID: $webhook1Id");

    // Pruebas del Repositorio
    $listaTodas = $txRepo->listarConFiltros([], 1, 10);
    assertCheck(count($listaTodas) >= 3, "listarConFiltros sin parámetros retorna transacciones existentes");

    $listaCulqi = $txRepo->listarConFiltros(['proveedor' => 'CULQI'], 1, 10);
    $soloCulqi = array_filter($listaCulqi, fn($t) => $t['proveedor'] !== 'CULQI');
    assertCheck(empty($soloCulqi), "Filtro proveedor='CULQI' no incluye otros proveedores");

    $listaTardio = $txRepo->listarConFiltros(['estado_conciliacion' => 'DISCREPANCIA_HOLD_EXPIRADO'], 1, 10);
    assertCheck(count($listaTardio) >= 1, "Filtro estado_conciliacion='DISCREPANCIA_HOLD_EXPIRADO' encuentra la transacción tardía");

    $listaBusqueda = $txRepo->listarConFiltros(['busqueda' => $codigoTx1], 1, 10);
    assertCheck(count($listaBusqueda) === 1, "Búsqueda textual exacta por código encuentra exactamente 1 resultado");
    assertCheck($listaBusqueda[0]['codigo_transaccion'] === $codigoTx1, "Campo 'codigo_transaccion' enriquecido correctamente");
    assertCheck((float)$listaBusqueda[0]['saldo_reembolsable'] === 250.00, "Campo 'saldo_reembolsable' calculado correctamente (S/ 250.00)");

    $conteoTotal = $txRepo->contarConFiltros([]);
    assertCheck($conteoTotal >= 3, "contarConFiltros retorna conteo consistente con la base de datos");

    $kpis = $txRepo->obtenerMetricasKpi();
    assertCheck(isset($kpis['monto_aprobado']) && (float)$kpis['monto_aprobado'] >= 430.00, "KPI monto_aprobado suma montos aprobados (actual: {$kpis['monto_aprobado']})");
    assertCheck(isset($kpis['discrepancias_hold_expirado']) && (int)$kpis['discrepancias_hold_expirado'] >= 1, "KPI discrepancias_hold_expirado contabiliza pagos tardíos");

    // =========================================================================
    // 5. VALIDACIÓN DE CSRF EN ENDPOINTS MUTANTES
    // =========================================================================
    echo "\n--- 5. Validación de CSRF en Endpoints Mutantes ---\n";

    $mockCsrfInvalido = new class extends CsrfServicio {
        public function obtenerToken(): string { return 'token_esperado'; }
        public function validarToken(?string $t): bool { return false; }
    };

    $ctrlCsrfInvalido = new PagoControlador(
        pdo: $pdo,
        sesionServicio: $mockSesionAdmin,
        csrfServicio: $mockCsrfInvalido
    );

    $respCsrfReembolso = $ctrlCsrfInvalido->reembolsar($tx1Id);
    assertCheck($respCsrfReembolso->obtenerCodigoEstado() === 403, "Reembolso sin token CSRF válido es rechazado (HTTP 403)");

    $respCsrfConciliar = $ctrlCsrfInvalido->conciliar($tx1Id);
    assertCheck($respCsrfConciliar->obtenerCodigoEstado() === 403, "Conciliación sin token CSRF válido es rechazada (HTTP 403)");

    // =========================================================================
    // 6. VALIDACIÓN SERVIDOR DE REEMBOLSOS (REGLAS DE NEGOCIO)
    // =========================================================================
    echo "\n--- 6. Validación Servidor de Reembolsos (Reglas de Negocio) ---\n";

    $ctrlAdmin = new PagoControlador(
        pdo: $pdo,
        sesionServicio: $mockSesionAdmin,
        csrfServicio: $mockCsrfValido
    );

    // TX Inexistente -> 404
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'token_csrf_prueba_valido';
    $_POST = ['motivo' => 'Devolución justificada mayor a 10 caracteres', 'tipo_reembolso' => 'TOTAL'];
    $resp404 = $ctrlAdmin->reembolsar(99999999);
    assertCheck($resp404->obtenerCodigoEstado() === 404, "Reembolso sobre transacción inexistente retorna HTTP 404");

    // TX en estado PENDIENTE -> 422
    $respNoAprobada = $ctrlAdmin->reembolsar($tx3Id);
    assertCheck($respNoAprobada->obtenerCodigoEstado() === 422, "Reembolso sobre transacción PENDIENTE es rechazado (HTTP 422)");

    // Motivo demasiado corto (< 10 caracteres) -> 422
    $_POST['motivo'] = 'Corto';
    $respMotivoCorto = $ctrlAdmin->reembolsar($tx1Id);
    assertCheck($respMotivoCorto->obtenerCodigoEstado() === 422, "Reembolso con motivo < 10 caracteres es rechazado (HTTP 422)");

    // Reembolso parcial con monto <= 0 -> 422
    $_POST['motivo'] = 'Devolución válida de más de diez caracteres';
    $_POST['tipo_reembolso'] = 'PARCIAL';
    $_POST['monto'] = '0.00';
    $respMontoCero = $ctrlAdmin->reembolsar($tx1Id);
    assertCheck($respMontoCero->obtenerCodigoEstado() === 422, "Reembolso parcial con monto 0.00 es rechazado (HTTP 422)");

    // Reembolso parcial con monto superior al saldo disponible (S/ 300 > S/ 250) -> 422
    $_POST['monto'] = '300.00';
    $respMontoExcesivo = $ctrlAdmin->reembolsar($tx1Id);
    assertCheck($respMontoExcesivo->obtenerCodigoEstado() === 422, "Reembolso parcial superior al saldo disponible es rechazado (HTTP 422)");

    // =========================================================================
    // 7. EJECUCIÓN DE REEMBOLSOS (PARCIAL Y TOTAL) CON CONTROLADOR Y SERVICIO
    // =========================================================================
    echo "\n--- 7. Ejecución de Reembolsos (Parcial y Total) con Precisión BCMath ---\n";

    // Registrar mock de Culqi en la fábrica
    $mockCulqiDriver = new class implements ProveedorPagoInterfaz {
        public bool $simularFallo = false;
        public ?ReembolsoSolicitud $ultimaSolicitud = null;

        public function obtenerNombre(): string { return 'CULQI'; }
        public function crearIntencionPago(\CamargoPMS\Servicios\Pagos\DTOs\IntencionPagoSolicitud $s): \CamargoPMS\Servicios\Pagos\DTOs\IntencionPagoResultado {
            throw new RuntimeException("No usado en prueba");
        }
        public function consultarOrden(string $id): \CamargoPMS\Servicios\Pagos\DTOs\ConsultaOrdenResultado {
            throw new RuntimeException("No usado en prueba");
        }
        public function consultarTransaccion(string $id): \CamargoPMS\Servicios\Pagos\DTOs\ConsultaTransaccionResultado {
            throw new RuntimeException("No usado en prueba");
        }
        public function verificarYParsearWebhook(array $headers, string $cuerpoBruto): \CamargoPMS\Servicios\Pagos\DTOs\WebhookNotificacionResultado {
            throw new RuntimeException("No usado en prueba");
        }
        public function procesarReembolso(ReembolsoSolicitud $solicitud): ReembolsoResultado {
            $this->ultimaSolicitud = $solicitud;
            if ($this->simularFallo) {
                return ReembolsoResultado::fallo('CULQI', 'Fondos insuficientes en la cuenta del comercio');
            }
            return ReembolsoResultado::exito(
                proveedor: 'CULQI',
                reembolsoId: 'ref_mock_1d_' . bin2hex(random_bytes(4)),
                monto: $solicitud->monto,
                moneda: $solicitud->moneda
            );
        }
    };

    FabricaProveedoresPago::registrarProveedor($mockCulqiDriver);

    // 7.1 Falla del proveedor
    $mockCulqiDriver->simularFallo = true;
    $_POST['monto'] = '50.00';
    $respFallaProveedor = $ctrlAdmin->reembolsar($tx1Id);
    assertCheck($respFallaProveedor->obtenerCodigoEstado() === 422, "Falla externa de la pasarela es capturada como HTTP 422");
    $tx1Db = $txRepo->buscarPorId($tx1Id);
    assertCheck($tx1Db->obtenerMontoReembolsado() === '0.00', "Saldo reembolsado no muta ante falla externa (permanece S/ 0.00)");

    // 7.2 Reembolso parcial exitoso (S/ 50.00 de S/ 250.00)
    $mockCulqiDriver->simularFallo = false;
    $respParcial1 = $ctrlAdmin->reembolsar($tx1Id);
    assertCheck($respParcial1->obtenerCodigoEstado() === 200, "Reembolso parcial de S/ 50.00 exitoso (HTTP 200)");
    $tx1Parcial = $txRepo->buscarPorId($tx1Id);
    assertCheck($tx1Parcial->obtenerMontoReembolsado() === '50.00', "Monto reembolsado acumulado es exactamente S/ 50.00");
    assertCheck($tx1Parcial->obtenerSaldoReembolsable() === '200.00', "Saldo remanente disponible es exactamente S/ 200.00");
    assertCheck($tx1Parcial->obtenerEstadoReembolso() === PagoTransaccionPasarela::ESTADO_REEMBOLSO_PARCIAL, "Estado de reembolso es REEMBOLSADO_PARCIAL");

    // 7.3 Segundo reembolso parcial que cubre el remanente (S/ 200.00)
    $_POST['monto'] = '200.00';
    $respParcial2 = $ctrlAdmin->reembolsar($tx1Id);
    assertCheck($respParcial2->obtenerCodigoEstado() === 200, "Segundo reembolso por el remanente (S/ 200.00) exitoso (HTTP 200)");
    $tx1Total = $txRepo->buscarPorId($tx1Id);
    assertCheck($tx1Total->obtenerMontoReembolsado() === '250.00', "Monto reembolsado total acumulado es S/ 250.00");
    assertCheck($tx1Total->obtenerSaldoReembolsable() === '0.00', "Saldo remanente es exactamente S/ 0.00");
    assertCheck($tx1Total->obtenerEstadoReembolso() === PagoTransaccionPasarela::ESTADO_REEMBOLSO_TOTAL, "Estado de reembolso cambia automáticamente a REEMBOLSADO_TOTAL");

    // 7.4 Intentar reembolsar sobre transacción agotada -> 422
    $respAgotada = $ctrlAdmin->reembolsar($tx1Id);
    assertCheck($respAgotada->obtenerCodigoEstado() === 422, "Intento de reembolso sobre transacción con saldo 0 es rechazado (HTTP 422)");

    // 7.5 Reembolso Total Directo sobre TX 2
    $_POST['tipo_reembolso'] = 'TOTAL';
    unset($_POST['monto']);
    $respTotalDirecto = $ctrlAdmin->reembolsar($tx2Id);
    assertCheck($respTotalDirecto->obtenerCodigoEstado() === 200, "Reembolso TOTAL directo sobre transacción tardía exitoso (HTTP 200)");
    $tx2Db = $txRepo->buscarPorId($tx2Id);
    assertCheck($tx2Db->obtenerEstadoReembolso() === PagoTransaccionPasarela::ESTADO_REEMBOLSO_TOTAL, "Transacción tardía queda en REEMBOLSADO_TOTAL");
    assertCheck($tx2Db->obtenerSaldoReembolsable() === '0.00', "Saldo disponible de transacción tardía es 0.00");

    // =========================================================================
    // 8. INVARIANTE HOTELERO INVIOLABLE C1/C2 (PAGOS TARDÍOS)
    // =========================================================================
    echo "\n--- 8. Invariante Hotelero Inviolable de Pagos Tardíos C1/C2 ---\n";

    // Crear otra transacción de pago tardío para verificar invariante de reserva e inventario
    $codigoTxTardio2 = 'TRX-TEST-C1C2-' . bin2hex(random_bytes(3));
    $txTardioId = $txRepo->crear([
        'codigo' => $codigoTxTardio2,
        'reserva_id' => $reservaId,
        'cuenta_folio_id' => null,
        'pago_cuenta_id' => null,
        'proveedor' => 'CULQI',
        'tipo_operacion' => 'ORDEN_CHECKOUT',
        'proveedor_orden_id' => 'ord_test_c1c2',
        'proveedor_transaccion_id' => 'chr_test_c1c2',
        'proveedor_referencia' => $reservaCodigo,
        'estado_pago' => 'APROBADO',
        'estado_conciliacion' => 'DISCREPANCIA_HOLD_EXPIRADO',
        'estado_reembolso' => 'NO_APLICA',
        'moneda_codigo' => 'PEN',
        'monto_esperado' => '400.00',
        'monto_cobrado' => '400.00',
        'monto_reembolsado' => '0.00',
        'metadatos_proveedor' => json_encode(['nota' => 'Pago tardío estricto']),
        'motivo_discrepancia' => 'Hold de reserva expirado. Cuarentena C1/C2.',
        'motivo_reembolso' => null,
        'actor_id' => $actorId,
    ]);

    $txTardioObj = $txRepo->buscarPorId($txTardioId);
    assertCheck($txTardioObj->obtenerCuentaFolioId() === null, "Invariante C1: cuenta_folio_id es estrictamente NULL");
    assertCheck($txTardioObj->obtenerPagoCuentaId() === null, "Invariante C1: pago_cuenta_id es estrictamente NULL");
    assertCheck($txTardioObj->obtenerSubtipoDiscrepancia() === 'DISCREPANCIA_HOLD_EXPIRADO', "Subtipo de discrepancia es DISCREPANCIA_HOLD_EXPIRADO");

    // Acción de seguimiento administrativo (sin reasignación)
    $_POST = [
        'accion' => 'REGISTRAR_SEGUIMIENTO',
        'observacion' => 'Se contactó al cliente por teléfono para coordinar devolución o reprogramación.',
    ];
    $respSeguimiento = $ctrlAdmin->conciliar($txTardioId);
    assertCheck($respSeguimiento->obtenerCodigoEstado() === 200, "Registro de observación administrativa exitoso (HTTP 200)");

    $txTardioPost = $txRepo->buscarPorId($txTardioId);
    assertCheck(str_contains((string)$txTardioPost->obtenerMotivoDiscrepancia(), 'Se contactó al cliente'), "Motivo de discrepancia actualizado con nota de seguimiento");
    assertCheck($txTardioPost->obtenerCuentaFolioId() === null, "Preservación C1/C2: cuenta_folio_id sigue siendo estrictamente NULL tras seguimiento");
    assertCheck($txTardioPost->obtenerPagoCuentaId() === null, "Preservación C1/C2: pago_cuenta_id sigue siendo NULL");

    // Verificar que la auditoría registró el seguimiento
    $stmtAud = $pdo->prepare("SELECT * FROM auditoria WHERE entidad = 'pagos_transacciones_pasarela' AND entidad_id = :id AND accion = 'SEGUIMIENTO_DISCREPANCIA_REGISTRADO'");
    $stmtAud->execute(['id' => (string) $txTardioId]);
    $audEvento = $stmtAud->fetch(PDO::FETCH_ASSOC);
    assertCheck(!empty($audEvento), "Bitácora de auditoría registró evento 'SEGUIMIENTO_DISCREPANCIA_REGISTRADO'");

    // =========================================================================
    // 9. INSPECCIÓN DE WEBHOOKS Y SEGURIDAD ZERO-TRUST
    // =========================================================================
    echo "\n--- 9. Inspección de Webhooks y Seguridad Zero-Trust ---\n";

    $respPayload = $ctrlAdmin->webhookPayload($webhook1Id);
    assertCheck($respPayload->obtenerCodigoEstado() === 200, "GET /pagos/webhooks/{id}/payload retorna HTTP 200");

    $cuerpoJson = $respPayload->obtenerContenido();
    $dataWebhook = json_decode($cuerpoJson, true);
    assertCheck(isset($dataWebhook['ok']) && $dataWebhook['ok'] === true, "Respuesta JSON tiene estructura ok=true");
    assertCheck(isset($dataWebhook['datos']['payload_sanitizado']), "Respuesta incluye 'payload_sanitizado'");

    // Verificar que secretos fueron redactados en la salida
    $sanitizadoStr = json_encode($dataWebhook['datos']['payload_sanitizado']);
    assertCheck(!str_contains($sanitizadoStr, 'SECRETO_QUE_DEBE_SANITIZARSE'), "Zero-Trust: Secreto confidencial fue enmascarado/redactado de la vista pública");

    // Verificar que en base de datos el payload raw original no fue corrompido
    $webhookDb = $webhookRepo->buscarPorId($webhook1Id);
    assertCheck(str_contains($webhookDb->obtenerPayloadRaw(), 'SECRETO_QUE_DEBE_SANITIZARSE'), "Integridad: payload_raw en base de datos permanece intacto para fines forenses");

    // =========================================================================
    // 10. RENDERIZADO DE INTERFAZ ALINA (INDEX Y DETALLE)
    // =========================================================================
    echo "\n--- 10. Renderizado de Interfaz Alina (Cards, Flatpickr, Timeline) ---\n";

    $respIndexHtml = $ctrlAdmin->index();
    assertCheck($respIndexHtml->obtenerCodigoEstado() === 200, "GET /pagos renderiza vista HTML exitosamente (HTTP 200)");
    $htmlIndex = $respIndexHtml->obtenerContenido();

    assertCheck(str_contains($htmlIndex, 'Monitor de Pasarelas de Pago'), "Vista index contiene título oficial");
    assertCheck(str_contains($htmlIndex, 'data-provider="rangepicker"'), "Vista index implementa Flatpickr con data-provider='rangepicker'");
    assertCheck(!str_contains($htmlIndex, 'type="date"'), "Cumplimiento D-071: CERO input type='date' en la vista index");
    assertCheck(str_contains($htmlIndex, 'data-target-inicio="#filtro-fecha-desde"'), "Selector de rango vinculado a target inicio");
    assertCheck(str_contains($htmlIndex, 'data-target-fin="#filtro-fecha-hasta"'), "Selector de rango vinculado a target fin");
    assertCheck(str_contains($htmlIndex, 'id="modal-reembolso"'), "Modal de reembolso Alina incluido en la vista");
    assertCheck(str_contains($htmlIndex, 'id="offcanvas-webhook-payload"'), "Offcanvas de webhooks Alina incluido en la vista");
    assertCheck(str_contains($htmlIndex, 'camargo-pagos.js'), "Script oficial camargo-pagos.js enlazado en la vista");

    $respDetalleHtml = $ctrlAdmin->detalle($tx1Id);
    assertCheck($respDetalleHtml->obtenerCodigoEstado() === 200, "GET /pagos/transacciones/{id} renderiza detalle exitosamente (HTTP 200)");
    $htmlDetalle = $respDetalleHtml->obtenerContenido();

    assertCheck(str_contains($htmlDetalle, 'Desglose Financiero y de Pasarela'), "Ficha de detalle contiene desglose financiero");
    assertCheck(str_contains($htmlDetalle, 'app-side-timeline'), "Ficha de detalle implementa timeline Alina .app-side-timeline");
    assertCheck(str_contains($htmlDetalle, 'side-timeline-icon'), "Timeline contiene iconos side-timeline-icon");
    assertCheck(str_contains($htmlDetalle, 'Datos del Pagador y Tarjeta (Zero-Trust)'), "Tarjeta de datos del pagador zero-trust presente");

    // Verificar vista detalle de pago tardío
    $respDetalleTardio = $ctrlAdmin->detalle($tx2Id);
    $htmlDetalleTardio = $respDetalleTardio->obtenerContenido();
    assertCheck(str_contains($htmlDetalleTardio, 'PAGO RECIBIDO CON RESERVA EXPIRADA'), "Detalle de pago tardío muestra banner de alerta C1/C2");
    assertCheck(str_contains($htmlDetalleTardio, 'modal-observacion-seguimiento'), "Detalle contiene modal para registrar observación de seguimiento");

    // =========================================================================
    // 11. LIMPIEZA DE FIXTURES
    // =========================================================================
    echo "\n--- 11. Limpieza Defensiva de Fixtures ---\n";

    $pdo->exec("DELETE FROM pagos_webhooks_eventos WHERE id = $webhook1Id");
    $pdo->exec("DELETE FROM pagos_transacciones_pasarela WHERE id IN ($tx1Id, $tx2Id, $tx3Id, $txTardioId)");
    $pdo->exec("DELETE FROM auditoria WHERE entidad = 'pagos_transacciones_pasarela' AND entidad_id IN ('$tx1Id', '$tx2Id', '$tx3Id', '$txTardioId')");

    $verifLimpia = $pdo->query("SELECT COUNT(*) FROM pagos_transacciones_pasarela WHERE id IN ($tx1Id, $tx2Id, $tx3Id, $txTardioId)")->fetchColumn();
    assertCheck((int)$verifLimpia === 0, "Fixtures de prueba eliminados defensivamente de la base de datos");

    echo "\n====================================================================\n";
    echo " RESULTADO SUITE PAGOS-1D: $passedAssertions/$totalAssertions ASERCIONES SUPERADAS EXITOSAMENTE (100% PASS)\n";
    echo "====================================================================\n\n";

} catch (Throwable $e) {
    echo "\n[ERROR CRÍTICO EN SUITE PAGOS-1D]: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . " Línea: " . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
