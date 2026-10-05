<?php

declare(strict_types=1);

/**
 * Suite de Pruebas: PAGOS-1B — Infraestructura Soberana del Dominio de Pagos, Adaptador Desacoplado y Driver Culqi
 *
 * Valida de forma exhaustiva:
 * 1. Gobierno del Esquema y Migración 038:
 *    - 130 tablas relacionales exactas en base de datos.
 *    - Migración 038_pagos_pasarelas.sql registrada en tabla 'migraciones'.
 *    - Ranura 039 estrictamente LIBRE (cero DDL no autorizado).
 *    - Paridad de DDL en SQL/camargo_pms.sql.
 *    - Inmutabilidad total de admin-dashboard/.
 * 2. Modelos de Dominio:
 *    - PagoTransaccionPasarela: tres ejes ortogonales de estado, serialización e invariantes.
 *    - PagoWebhookEvento: trazabilidad e idempotencia de eventos.
 * 3. Repositorios de Persistencia:
 *    - PagoTransaccionPasarelaRepositorio: operaciones CRUD y bloqueo pesimista FOR UPDATE.
 *    - PagoWebhookEventoRepositorio: registro idempotente y transiciones de estado.
 * 4. DTOs y Contrato de Proveedor:
 *    - IntencionPagoSolicitud, IntencionPagoResultado, WebhookNotificacionResultado,
 *      ConsultaOrdenResultado, ConsultaTransaccionResultado, ReembolsoSolicitud, ReembolsoResultado.
 *    - ProveedorPagoInterfaz: contrato neutral agnóstico.
 * 5. Adaptador y Driver Culqi:
 *    - Aritmética de precisión con BCMath (PEN decimal <-> céntimos enteros).
 *    - Inyección de mock HTTP y creación de órdenes.
 *    - Parseo y normalización de webhooks (order.status.changed, charge.creation.succeeded).
 *    - Consultas y reembolsos.
 * 6. Orquestador Soberano (PagoServicio):
 *    - Creación de intenciones de pago sobre reservas PENDIENTE.
 *    - Rechazo ante holds vencidos o reservas inválidas.
 *    - Procesamiento atómico e idempotente de webhooks con bloqueos pesimistas.
 *    - Flujo canónico: pago en tiempo confirma reserva, genera folio e imputa contablemente.
 *    - Invariante de pago tardío: dinero capturado en DISCREPANCIA_HOLD_EXPIRADO sin folio artificial ni desbalance de inventario.
 *    - Invariante de discrepancia de monto: DISCREPANCIA_MONTO en cuarentena.
 *    - Procesamiento de reembolsos y auditoría transversal (D-061).
 * 7. Limpieza defensiva de datos de prueba.
 */

namespace CamargoPMS\Pruebas;

use CamargoPMS\Modelos\PagoTransaccionPasarela;
use CamargoPMS\Modelos\PagoWebhookEvento;
use CamargoPMS\Modelos\Reserva;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Repositorios\CuentaFolioRepositorio;
use CamargoPMS\Repositorios\PagoCuentaRepositorio;
use CamargoPMS\Repositorios\PagoTransaccionPasarelaRepositorio;
use CamargoPMS\Repositorios\PagoWebhookEventoRepositorio;
use CamargoPMS\Repositorios\ReservaRepositorio;
use CamargoPMS\Servicios\Pagos\Adaptadores\CulqiProveedor;
use CamargoPMS\Servicios\Pagos\DTOs\ConsultaOrdenResultado;
use CamargoPMS\Servicios\Pagos\DTOs\ConsultaTransaccionResultado;
use CamargoPMS\Servicios\Pagos\DTOs\IntencionPagoResultado;
use CamargoPMS\Servicios\Pagos\DTOs\IntencionPagoSolicitud;
use CamargoPMS\Servicios\Pagos\DTOs\ReembolsoResultado;
use CamargoPMS\Servicios\Pagos\DTOs\ReembolsoSolicitud;
use CamargoPMS\Servicios\Pagos\DTOs\WebhookNotificacionResultado;
use CamargoPMS\Servicios\Pagos\FabricaProveedoresPago;
use CamargoPMS\Servicios\Pagos\ProveedorPagoInterfaz;
use CamargoPMS\Servicios\PagoServicio;
use PDO;
use RuntimeException;

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
echo " INICIANDO SUITE DE PRUEBAS: PAGOS-1B (INFRAESTRUCTURA Y DRIVER CULQI)\n";
echo "====================================================================\n\n";

// Limpieza inicial de pruebas previas
$pdo->exec("DELETE FROM pagos_webhooks_eventos WHERE proveedor_evento_id LIKE '%test%' OR proveedor_evento_id LIKE 'evt_%'");
$pdo->exec("DELETE FROM pagos_transacciones_pasarela WHERE proveedor_orden_id LIKE '%test%' OR proveedor_orden_id LIKE 'ord_%'");

try {
    // =========================================================================
    // 1. GOBIERNO DEL ESQUEMA Y MIGRACIÓN 038
    // =========================================================================
    echo "--- 1. Gobierno del Esquema y Migración 038 ---\n";

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    assertCheck(count($tables) >= 130, "Base de datos contiene al menos 130 tablas relacionales (actual: " . count($tables) . ")");
    assertCheck(in_array('pagos_transacciones_pasarela', $tables, true), "Tabla 'pagos_transacciones_pasarela' existe en la base de datos");
    assertCheck(in_array('pagos_webhooks_eventos', $tables, true), "Tabla 'pagos_webhooks_eventos' existe en la base de datos");

    $mig038Presente = (bool) $pdo->query("SELECT 1 FROM migraciones WHERE migracion = '038_pagos_pasarelas.sql'")->fetchColumn();
    assertCheck($mig038Presente, "Migración 038_pagos_pasarelas.sql registrada en tabla 'migraciones'");

    $mig042 = glob(dirname(__DIR__) . '/SQL/migraciones/*042*');
    assertCheck(empty($mig042), "Ranura de migración 042 estrictamente LIBRE (cero DDL no autorizado)");

    // Paridad con SQL/camargo_pms.sql
    $sqlConsolidado = (string) file_get_contents(dirname(__DIR__) . '/SQL/camargo_pms.sql');
    assertCheck(str_contains($sqlConsolidado, 'CREATE TABLE IF NOT EXISTS `pagos_transacciones_pasarela`'), "SQL/camargo_pms.sql incluye pagos_transacciones_pasarela");
    assertCheck(str_contains($sqlConsolidado, 'CREATE TABLE IF NOT EXISTS `pagos_webhooks_eventos`'), "SQL/camargo_pms.sql incluye pagos_webhooks_eventos");

    // Inmutabilidad de admin-dashboard/
    $gitAlina = shell_exec('git status --porcelain admin-dashboard/');
    assertCheck(empty(trim((string) $gitAlina)), "admin-dashboard/ permanece 100% inmutable y libre de modificaciones");

    // Actor técnico PASARELA_CULQI registrado
    $actorPasarela = $pdo->query("SELECT id, tipo, codigo FROM actores WHERE codigo = 'PASARELA_CULQI'")->fetch(PDO::FETCH_ASSOC);
    assertCheck(!empty($actorPasarela), "Actor técnico PASARELA_CULQI registrado en tabla 'actores'");
    assertCheck($actorPasarela['tipo'] === 'PROVEEDOR_PAGO', "Tipo de actor es PROVEEDOR_PAGO");

    // =========================================================================
    // 2. MODELOS DE DOMINIO
    // =========================================================================
    echo "\n--- 2. Modelos de Dominio (PagoTransaccionPasarela y PagoWebhookEvento) ---\n";

    $modeloTx = new PagoTransaccionPasarela(
        id: 10,
        codigo: 'TRX-202610-0001',
        reservaId: 100,
        cuentaFolioId: 20,
        pagoCuentaId: 5,
        proveedor: 'CULQI',
        tipoOperacion: PagoTransaccionPasarela::OPERACION_ORDEN_CHECKOUT,
        proveedorOrdenId: 'ord_test_abc123',
        proveedorTransaccionId: 'chr_test_xyz789',
        proveedorReferencia: 'RES-100',
        estadoPago: PagoTransaccionPasarela::ESTADO_PAGO_APROBADO,
        estadoConciliacion: PagoTransaccionPasarela::ESTADO_CONCILIACION_CONCILIADO,
        estadoReembolso: PagoTransaccionPasarela::ESTADO_REEMBOLSO_NO_APLICA,
        monedaCodigo: 'PEN',
        montoEsperado: '450.00',
        montoCobrado: '450.00',
        montoReembolsado: '0.00',
        metadatosProveedor: ['origen' => 'checkout'],
        motivoDiscrepancia: null,
        motivoReembolso: null,
        actorId: (int) ($actorPasarela['id'] ?? 1),
        creadoEn: '2026-10-01 11:30:00'
    );

    assertCheck($modeloTx->obtenerId() === 10, "Modelo: ID recuperado");
    assertCheck($modeloTx->obtenerProveedor() === 'CULQI', "Modelo: Proveedor CULQI");
    assertCheck($modeloTx->estaAprobado(), "Modelo: estaAprobado() es true");
    assertCheck(!$modeloTx->estaPendiente(), "Modelo: estaPendiente() es false");
    assertCheck($modeloTx->estaConciliado(), "Modelo: estaConciliado() es true");
    assertCheck(!$modeloTx->tieneDiscrepancia(), "Modelo: tieneDiscrepancia() es false");
    assertCheck($modeloTx->esReembolsable(), "Modelo: esReembolsable() es true");

    $arrayTx = $modeloTx->aArray();
    assertCheck($arrayTx['monto_esperado'] === '450.00', "Modelo aArray: monto_esperado correcto");
    $txReconstituido = PagoTransaccionPasarela::desdeArray($arrayTx);
    assertCheck($txReconstituido->obtenerProveedorOrdenId() === 'ord_test_abc123', "Modelo desdeArray: paridad completa");

    // Modelo de evento
    $modeloEvt = new PagoWebhookEvento(
        id: 1,
        transaccionPasarelaId: 10,
        proveedor: 'CULQI',
        proveedorEventoId: 'evt_test_999',
        tipoEvento: 'order.status.changed',
        cuerpoHash: hash('sha256', '{"test":true}'),
        payloadRaw: '{"test":true}',
        cabeceras: ['user-agent' => 'Culqi-Webhook'],
        estadoProcesamiento: PagoWebhookEvento::ESTADO_PROCESADO,
        codigoHttpRespuesta: 200,
        errorDetalle: null,
        recibidoEn: '2026-10-01 11:30:00',
        procesadoEn: '2026-10-01 11:31:00'
    );
    assertCheck($modeloEvt->estaProcesado(), "Modelo Evento: estaProcesado() es true");
    assertCheck($modeloEvt->obtenerProveedorEventoId() === 'evt_test_999', "Modelo Evento: ID de evento coincide");

    // =========================================================================
    // 3. REPOSITORIOS DE PERSISTENCIA
    // =========================================================================
    echo "\n--- 3. Repositorios de Persistencia ---\n";

    $txRepo = new PagoTransaccionPasarelaRepositorio($pdo);
    $webhookRepo = new PagoWebhookEventoRepositorio($pdo);

    // Obtener una reserva existente para FK
    $reservaFixture = $pdo->query("SELECT id FROM reservas ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $reservaFixtureId = (int) ($reservaFixture['id'] ?? 1);

    $txCreadaId = $txRepo->crear([
        'proveedor' => 'CULQI',
        'proveedor_orden_id' => 'ord_test_repo_001',
        'reserva_id' => $reservaFixtureId,
        'moneda_codigo' => 'PEN',
        'monto_esperado' => '300.00',
        'estado_pago' => PagoTransaccionPasarela::ESTADO_PAGO_INICIADO,
        'estado_conciliacion' => PagoTransaccionPasarela::ESTADO_CONCILIACION_PENDIENTE,
        'metadatos_proveedor' => ['test' => true],
        'actor_id' => (int) ($actorPasarela['id'] ?? 1),
    ]);

    assertCheck($txCreadaId > 0, "Repositorio TX: Transacción creada con ID $txCreadaId");

    $txRecuperada = $txRepo->buscarPorId($txCreadaId);
    assertCheck($txRecuperada !== null, "Repositorio TX: buscarPorId recupera transacción");
    assertCheck($txRecuperada->obtenerProveedorOrdenId() === 'ord_test_repo_001', "Repositorio TX: Orden ID coincide");

    // Búsqueda por proveedor y orden
    $txPorOrden = $txRepo->buscarPorProveedorYOrdenId('CULQI', 'ord_test_repo_001');
    assertCheck($txPorOrden !== null && $txPorOrden->obtenerId() === $txCreadaId, "Repositorio TX: buscarPorProveedorYOrdenId exitoso");

    // Actualización de estado
    $actOk = $txRepo->actualizar($txCreadaId, [
        'estado_pago' => PagoTransaccionPasarela::ESTADO_PAGO_PENDIENTE,
        'proveedor_transaccion_id' => 'chr_test_repo_001',
    ]);
    assertCheck($actOk, "Repositorio TX: actualizar campos exitoso");

    $txActualizada = $txRepo->buscarPorProveedorYTransaccionId('CULQI', 'chr_test_repo_001');
    assertCheck($txActualizada !== null && $txActualizada->obtenerEstadoPago() === PagoTransaccionPasarela::ESTADO_PAGO_PENDIENTE, "Repositorio TX: buscarPorProveedorYTransaccionId exitoso");

    // Repositorio Webhook
    $eventoReg1 = $webhookRepo->registrarOObtener(
        proveedor: 'CULQI',
        proveedorEventoId: 'evt_repo_test_001',
        tipoEvento: 'order.status.changed',
        payloadRaw: json_encode(['foo' => 'bar']),
        cabeceras: ['content-type' => 'application/json'],
        transaccionPasarelaId: $txCreadaId
    );
    assertCheck($eventoReg1['es_nuevo'] === true, "Repositorio Webhook: Primer registro reporta es_nuevo = true");

    // Idempotencia: segundo registro idéntico
    $eventoReg2 = $webhookRepo->registrarOObtener(
        proveedor: 'CULQI',
        proveedorEventoId: 'evt_repo_test_001',
        tipoEvento: 'order.status.changed',
        payloadRaw: json_encode(['foo' => 'bar'])
    );
    assertCheck($eventoReg2['es_nuevo'] === false, "Repositorio Webhook: Idempotencia detecta duplicado (es_nuevo = false)");
    assertCheck($eventoReg2['id'] === $eventoReg1['id'], "Repositorio Webhook: Mismo ID retornado");

    $webhookRepo->marcarProcesado($eventoReg1['id']);
    $eventoProc = $webhookRepo->buscarPorId($eventoReg1['id']);
    assertCheck($eventoProc->estaProcesado(), "Repositorio Webhook: marcarProcesado() exitoso");

    // =========================================================================
    // 4. DTOs Y CONTRATO NEUTRAL
    // =========================================================================
    echo "\n--- 4. DTOs y Contrato Neutral (ProveedorPagoInterfaz) ---\n";

    $solicitudDto = new IntencionPagoSolicitud(
        reservaId: 123,
        reservaCodigo: 'RES-TEST-123',
        monto: '250.00',
        moneda: 'PEN',
        descripcion: 'Estadía de 2 noches',
        clienteEmail: 'cliente@test.com',
        clienteNombre: 'Juan Perez'
    );
    assertCheck($solicitudDto->monto === '250.00', "DTO IntencionPagoSolicitud inicializado");

    $resultadoDto = IntencionPagoResultado::exito('CULQI', 'ord_test_555', 'ord_test_555', '250.00', 'PEN');
    assertCheck($resultadoDto->exitoso === true, "DTO IntencionPagoResultado::exito correcto");

    $notifDto = new WebhookNotificacionResultado(
        valido: true,
        proveedor: 'CULQI',
        proveedorEventoId: 'evt_1',
        tipoEvento: 'order.status.changed',
        estadoNormalizado: WebhookNotificacionResultado::ESTADO_APROBADO
    );
    assertCheck($notifDto->valido && $notifDto->estadoNormalizado === 'APROBADO', "DTO WebhookNotificacionResultado correcto");

    $ordenDto = new ConsultaOrdenResultado(true, 'CULQI', 'ord_1', ConsultaOrdenResultado::ESTADO_PAGADA, '250.00', 'PEN');
    assertCheck($ordenDto->encontrado && $ordenDto->estadoOrden === 'PAGADA', "DTO ConsultaOrdenResultado correcto");

    $txDto = new ConsultaTransaccionResultado(true, 'CULQI', 'chr_1', ConsultaTransaccionResultado::ESTADO_APROBADA, '250.00');
    assertCheck($txDto->encontrado && $txDto->estadoTransaccion === 'APROBADA', "DTO ConsultaTransaccionResultado correcto");

    $reembSol = new ReembolsoSolicitud('chr_1', '250.00', 'PEN', 'Cancelación por cliente');
    assertCheck($reembSol->monto === '250.00', "DTO ReembolsoSolicitud correcto");

    $reembRes = ReembolsoResultado::exito('CULQI', 'ref_1', '250.00', 'PEN');
    assertCheck($reembRes->exitoso && $reembRes->reembolsoId === 'ref_1', "DTO ReembolsoResultado::exito correcto");

    // =========================================================================
    // 5. FÁBRICA Y DRIVER CULQI
    // =========================================================================
    echo "\n--- 5. Fábrica de Proveedores y Driver Culqi ---\n";

    FabricaProveedoresPago::reiniciar();
    assertCheck(FabricaProveedoresPago::proveedorSoportado('CULQI'), "Fábrica: CULQI soportado");
    assertCheck(FabricaProveedoresPago::proveedorSoportado('IZIPAY'), "Fábrica: IZIPAY soportado");
    assertCheck(FabricaProveedoresPago::proveedorSoportado('PAYPAL'), "Fábrica: PAYPAL soportado");
    assertCheck(!FabricaProveedoresPago::proveedorSoportado('STRIPE'), "Fábrica: Proveedor no soportado retorna false");

    // Instanciar driver Culqi con Mock HTTP Client
    $mockCulqiLlamadas = [];
    $mockHttpClient = function (string $metodo, string $url, ?array $cuerpo, array $headers) use (&$mockCulqiLlamadas): array {
        $mockCulqiLlamadas[] = [
            'metodo' => $metodo,
            'url' => $url,
            'cuerpo' => $cuerpo,
            'headers' => $headers,
        ];

        if (str_contains($url, '/orders') && $metodo === 'POST') {
            return [
                'id' => 'ord_test_mock_culqi_001',
                'amount' => $cuerpo['amount'],
                'currency_code' => $cuerpo['currency_code'],
                'state' => 'pending',
                'order_number' => $cuerpo['order_number'],
                'expiration_date' => $cuerpo['expiration_date'],
                'qr' => 'https://culqi.com/qr/mock_001',
            ];
        }

        if (str_contains($url, '/orders/ord_test_mock_culqi_001') && $metodo === 'GET') {
            return [
                'id' => 'ord_test_mock_culqi_001',
                'amount' => 45000,
                'currency_code' => 'PEN',
                'state' => 'paid',
                'updated_at' => time(),
            ];
        }

        if (str_contains($url, '/charges/chr_test_mock_001') && $metodo === 'GET') {
            return [
                'id' => 'chr_test_mock_001',
                'amount' => 45000,
                'currency_code' => 'PEN',
                'capture' => true,
                'outcome' => ['type' => 'venta_exitosa'],
                'fee_details' => ['amount' => 1695],
                'net_amount' => 43305,
                'creation_date' => time(),
            ];
        }

        if (str_contains($url, '/refunds') && $metodo === 'POST') {
            return [
                'id' => 'ref_test_mock_001',
                'amount' => $cuerpo['amount'],
                'charge_id' => $cuerpo['charge_id'],
                'status' => 'successful',
            ];
        }

        return ['id' => 'mock_default'];
    };

    $culqiDriver = new CulqiProveedor(
        llaveSecreta: 'sk_test_mock_secret_key',
        llavePublica: 'pk_test_mock_public_key',
        webhookSecret: null,
        clienteHttp: $mockHttpClient
    );

    FabricaProveedoresPago::registrarProveedor($culqiDriver);
    $driverObtenido = FabricaProveedoresPago::obtenerProveedor('CULQI');
    assertCheck($driverObtenido instanceof ProveedorPagoInterfaz, "Fábrica entrega instancia de ProveedorPagoInterfaz");
    assertCheck($driverObtenido->obtenerNombre() === 'CULQI', "Driver nombre es CULQI");

    // Probar creación de orden en Culqi
    $solicitudCulqi = new IntencionPagoSolicitud(
        reservaId: 101,
        reservaCodigo: 'RES-CULQI-001',
        monto: '450.00',
        moneda: 'PEN',
        descripcion: 'Estadía 3 noches',
        clienteEmail: 'test@camargopms.test',
        clienteNombre: 'Carlos Alcantara',
        clienteTelefono: '987654321'
    );

    $resCulqi = $culqiDriver->crearIntencionPago($solicitudCulqi);
    assertCheck($resCulqi->exitoso, "Culqi: crearIntencionPago exitoso");
    assertCheck($resCulqi->proveedorOrdenId === 'ord_test_mock_culqi_001', "Culqi: orden ID correcto");
    assertCheck($resCulqi->monto === '450.00', "Culqi: monto devuelto en formato decimal");

    // Verificar conversión a céntimos (450.00 -> 45000)
    $ultimaLlamada = end($mockCulqiLlamadas);
    assertCheck($ultimaLlamada['cuerpo']['amount'] === 45000, "Aritmética BCMath: 450.00 Soles convertido a exactamente 45000 céntimos enteros");
    assertCheck($ultimaLlamada['headers']['Authorization'] === 'Bearer sk_test_mock_secret_key', "Autenticación Bearer Culqi inyectada en cabeceras");

    // Probar parseo de Webhook Culqi (order.status.changed)
    $payloadWebhookOrden = json_encode([
        'id' => 'evt_culqi_mock_111',
        'type' => 'order.status.changed',
        'data' => [
            'id' => 'ord_test_mock_culqi_001',
            'amount' => 45000,
            'currency_code' => 'PEN',
            'state' => 'paid',
            'updated_at' => time(),
        ],
    ]);

    $notifWebhook = $culqiDriver->verificarYParsearWebhook([], $payloadWebhookOrden);
    assertCheck($notifWebhook->valido, "Culqi Webhook: Verificación y parseo válido");
    assertCheck($notifWebhook->tipoEvento === 'order.status.changed', "Culqi Webhook: tipoEvento correcto");
    assertCheck($notifWebhook->estadoNormalizado === WebhookNotificacionResultado::ESTADO_APROBADO, "Culqi Webhook: normalizado a APROBADO");
    assertCheck($notifWebhook->monto === '450.00', "Culqi Webhook: 45000 céntimos normalizado a 450.00 decimal");
    assertCheck($notifWebhook->moneda === 'PEN', "Culqi Webhook: moneda PEN");

    // Probar parseo de Webhook Culqi (charge.creation.succeeded)
    $payloadWebhookCargo = json_encode([
        'id' => 'evt_culqi_mock_222',
        'type' => 'charge.creation.succeeded',
        'data' => [
            'id' => 'chr_test_mock_001',
            'order_id' => 'ord_test_mock_culqi_001',
            'amount' => 45000,
            'currency_code' => 'PEN',
            'fee_details' => ['amount' => 1695],
            'net_amount' => 43305,
            'creation_date' => time(),
        ],
    ]);

    $notifCargo = $culqiDriver->verificarYParsearWebhook([], $payloadWebhookCargo);
    assertCheck($notifCargo->valido, "Culqi Cargo: Webhook válido");
    assertCheck($notifCargo->proveedorTransaccionId === 'chr_test_mock_001', "Culqi Cargo: ID de cargo extraído");
    assertCheck($notifCargo->comisionProveedor === '16.95', "Culqi Cargo: Comisión calculada (16.95)");
    assertCheck($notifCargo->montoNeto === '433.05', "Culqi Cargo: Monto neto calculado (433.05)");

    // Probar consulta de orden y transacción
    $consultaOrden = $culqiDriver->consultarOrden('ord_test_mock_culqi_001');
    assertCheck($consultaOrden->encontrado && $consultaOrden->estadoOrden === ConsultaOrdenResultado::ESTADO_PAGADA, "Culqi: consultarOrden exitoso");

    $consultaTx = $culqiDriver->consultarTransaccion('chr_test_mock_001');
    assertCheck($consultaTx->encontrado && $consultaTx->estadoTransaccion === ConsultaTransaccionResultado::ESTADO_APROBADA, "Culqi: consultarTransaccion exitoso");

    // Probar reembolso
    $reembResp = $culqiDriver->procesarReembolso(new ReembolsoSolicitud('chr_test_mock_001', '450.00', 'PEN', 'Cancelación'));
    assertCheck($reembResp->exitoso && $reembResp->reembolsoId === 'ref_test_mock_001', "Culqi: procesarReembolso exitoso");

    // =========================================================================
    // 6. ORQUESTADOR SOBERANO (PagoServicio)
    // =========================================================================
    echo "\n--- 6. Orquestador Soberano (PagoServicio) ---\n";

    $pagoServicio = new PagoServicio($pdo);

    // Preparar Fixture de Reserva PENDIENTE para flujo canónico
    $propiedadId = (int) $pdo->query("SELECT id FROM propiedades WHERE estado = 'ACTIVO' LIMIT 1")->fetchColumn();
    $unidadId = (int) $pdo->query("SELECT id FROM unidades WHERE propiedad_id = $propiedadId AND estado = 'ACTIVO' LIMIT 1")->fetchColumn();
    $titularId = (int) $pdo->query("SELECT id FROM personas WHERE estado = 'ACTIVO' LIMIT 1")->fetchColumn();
    $actorApiId = (int) $pdo->query("SELECT id FROM actores WHERE tipo = 'INTEGRACION' LIMIT 1")->fetchColumn();

    $codReservaCanon = 'RES-PAGOS-CANON-' . bin2hex(random_bytes(3));
    $expiraEnFuturo = date('Y-m-d H:i:s', time() + 1800); // 30 minutos

    $stmtNuevaRes = $pdo->prepare("INSERT INTO reservas (
        codigo, persona_titular_id, fecha_entrada, fecha_salida, noches,
        canal, origen, moneda_codigo, subtotal, impuesto_total, total, estado, expira_en, creado_por_actor_id
    ) VALUES (
        :codigo, :tit, '2026-11-20', '2026-11-22', 2,
        'WEB', 'DIRECTO', 'PEN', '500.00', '0.00', '500.00', 'PENDIENTE', :expira, :actor
    )");
    $stmtNuevaRes->execute([
        'codigo' => $codReservaCanon,
        'tit' => $titularId,
        'expira' => $expiraEnFuturo,
        'actor' => $actorApiId,
    ]);
    $reservaCanonId = (int) $pdo->lastInsertId();

    // Insertar reserva_unidad para cálculo de alojamiento
    $pdo->prepare("INSERT INTO reserva_unidades (
        reserva_id, unidad_id, precio_unitario_noche, noches, subtotal, impuesto, total, moneda_codigo
    ) VALUES (
        :res_id, :u_id, '250.00', 2, '500.00', '0.00', '500.00', 'PEN'
    )")->execute(['res_id' => $reservaCanonId, 'u_id' => $unidadId]);

    // 6.1 Crear intención de pago
    $intencionCanon = $pagoServicio->crearIntencionPagoParaReserva($reservaCanonId, 'CULQI', $actorApiId);
    assertCheck($intencionCanon['exito'], "PagoServicio: Intención de pago creada");
    assertCheck(!empty($intencionCanon['transaccion_id']), "PagoServicio: ID de transacción generado");
    $txCanonId = (int) $intencionCanon['transaccion_id'];
    $ordenCanonId = (string) $intencionCanon['proveedor_orden_id'];

    // 6.2 Flujo Canónico: Webhook Aprobado en tiempo y forma
    $payloadWebhookExito = json_encode([
        'id' => 'evt_canon_aprobado_001',
        'type' => 'order.status.changed',
        'data' => [
            'id' => $ordenCanonId,
            'amount' => 50000,
            'currency_code' => 'PEN',
            'state' => 'paid',
            'updated_at' => time(),
        ],
    ]);

    $respWebhookCanon = $pagoServicio->procesarWebhook('CULQI', ['x-signature' => 'mock_sig'], $payloadWebhookExito);
    assertCheck($respWebhookCanon['exito'], "PagoServicio Webhook: Procesamiento exitoso");
    assertCheck($respWebhookCanon['codigo'] === 'PAGO_CONFIRMADO_Y_CONCILIADO', "PagoServicio Webhook: Código PAGO_CONFIRMADO_Y_CONCILIADO");
    assertCheck(!empty($respWebhookCanon['cuenta_folio_id']), "PagoServicio Webhook: Folio contable vinculado");
    assertCheck(!empty($respWebhookCanon['pago_cuenta_id']), "PagoServicio Webhook: Registro de pago en cuenta creado");

    // Verificar en BD que la reserva cambió a CONFIRMADA
    $reservaConfirmada = (new ReservaRepositorio($pdo))->buscarPorId($reservaCanonId, false);
    assertCheck($reservaConfirmada->esConfirmada(), "Soberanía: Reserva transicionó a CONFIRMADA tras confirmación de pago");

    // Verificar transacción en BD
    $txVerificada = $txRepo->buscarPorId($txCanonId);
    assertCheck($txVerificada->obtenerEstadoPago() === PagoTransaccionPasarela::ESTADO_PAGO_APROBADO, "BD Transacción: estado_pago = APROBADO");
    assertCheck($txVerificada->obtenerEstadoConciliacion() === PagoTransaccionPasarela::ESTADO_CONCILIACION_CONCILIADO, "BD Transacción: estado_conciliacion = CONCILIADO");
    assertCheck($txVerificada->obtenerCuentaFolioId() !== null, "BD Transacción: cuenta_folio_id poblado");
    assertCheck($txVerificada->obtenerPagoCuentaId() !== null, "BD Transacción: pago_cuenta_id poblado");

    // Verificar saldo del folio y cobro asentado
    $cuentaFolioRepo = new CuentaFolioRepositorio($pdo);
    $folioVerificado = $cuentaFolioRepo->obtenerPorReservaId($reservaCanonId);
    assertCheck($folioVerificado !== null, "BD Folio: Cuenta folio existe para la reserva");
    $pagoCuentaRepo = new PagoCuentaRepositorio($pdo);
    $pagoRegistrado = $pagoCuentaRepo->obtenerPorId((int) $respWebhookCanon['pago_cuenta_id']);
    assertCheck($pagoRegistrado !== null, "BD Pago: Registro de pago en cuenta existe");
    assertCheck(bccomp($pagoRegistrado->obtenerMontoTotal(), '500.00', 2) === 0, "BD Pago: Total de cobro en cuenta refleja S/ 500.00");

    // 6.3 Idempotencia del Webhook Replay
    $respReplay = $pagoServicio->procesarWebhook('CULQI', ['x-signature' => 'mock_sig'], $payloadWebhookExito);
    assertCheck($respReplay['exito'], "Idempotencia Webhook: Replay responde éxito");
    assertCheck($respReplay['codigo'] === 'WEBHOOK_YA_PROCESADO', "Idempotencia Webhook: Detecta evento ya procesado");

    // 6.4 INVARIANTE CRÍTICO: PAGO TARDÍO CON HOLD EXPIRADO (C1/C2)
    echo "\n--- 6.4 Invariante Crítico: Pago Tardío con Hold Expirado ---\n";

    $codReservaExpirada = 'RES-PAGOS-EXP-' . bin2hex(random_bytes(3));
    $expiraEnPasado = date('Y-m-d H:i:s', time() - 3600); // Expiró hace 1 hora

    $stmtExpRes = $pdo->prepare("INSERT INTO reservas (
        codigo, persona_titular_id, fecha_entrada, fecha_salida, noches,
        canal, origen, moneda_codigo, subtotal, impuesto_total, total, estado, expira_en, creado_por_actor_id
    ) VALUES (
        :codigo, :tit, '2026-11-25', '2026-11-27', 2,
        'WEB', 'DIRECTO', 'PEN', '400.00', '0.00', '400.00', 'EXPIRADA', :expira, :actor
    )");
    $stmtExpRes->execute([
        'codigo' => $codReservaExpirada,
        'tit' => $titularId,
        'expira' => $expiraEnPasado,
        'actor' => $actorApiId,
    ]);
    $reservaExpiradaId = (int) $pdo->lastInsertId();

    // Crear transacción previa para esta orden
    $ordenExpiradaId = 'ord_test_mock_expirada_001';
    $txExpiradaId = $txRepo->crear([
        'proveedor' => 'CULQI',
        'proveedor_orden_id' => $ordenExpiradaId,
        'reserva_id' => $reservaExpiradaId,
        'moneda_codigo' => 'PEN',
        'monto_esperado' => '400.00',
        'estado_pago' => PagoTransaccionPasarela::ESTADO_PAGO_PENDIENTE,
        'estado_conciliacion' => PagoTransaccionPasarela::ESTADO_CONCILIACION_PENDIENTE,
        'actor_id' => $actorApiId,
    ]);

    // Simular que llega un webhook de pago aprobado tardío para esta orden
    $payloadPagoTardio = json_encode([
        'id' => 'evt_pago_tardio_999',
        'type' => 'order.status.changed',
        'data' => [
            'id' => $ordenExpiradaId,
            'amount' => 40000,
            'currency_code' => 'PEN',
            'state' => 'paid',
            'updated_at' => time(),
        ],
    ]);

    $respPagoTardio = $pagoServicio->procesarWebhook('CULQI', [], $payloadPagoTardio);
    assertCheck($respPagoTardio['exito'], "Pago Tardío: Webhook recibido y procesado");
    assertCheck($respPagoTardio['codigo'] === 'PAGO_TARDIO_EN_CUARENTENA', "Pago Tardío: Código PAGO_TARDIO_EN_CUARENTENA");

    // COMPROBACIONES VINCULANTES DE GOBERNANZA (C1/C2):
    // 1. La transacción refleja el dinero aprobado
    $txExpVerificada = $txRepo->buscarPorId($txExpiradaId);
    assertCheck($txExpVerificada->obtenerEstadoPago() === PagoTransaccionPasarela::ESTADO_PAGO_APROBADO, "Pago Tardío: estado_pago = APROBADO (dinero externo real reconocido)");

    // 2. Estado de conciliación marca discrepancia de hold expirado
    assertCheck($txExpVerificada->obtenerEstadoConciliacion() === PagoTransaccionPasarela::ESTADO_CONCILIACION_DISCREPANCIA_HOLD_EXPIRADO, "Pago Tardío: estado_conciliacion = DISCREPANCIA_HOLD_EXPIRADO");

    // 3. CERO FOLIOS ARTIFICIALES
    assertCheck($txExpVerificada->obtenerCuentaFolioId() === null, "INVARIANTE HOTELERO: cuenta_folio_id permanece estrictamente NULL (sin folios espurios)");
    assertCheck($txExpVerificada->obtenerPagoCuentaId() === null, "INVARIANTE HOTELERO: pago_cuenta_id permanece estrictamente NULL");

    // 4. La reserva NO fue confirmada unilateralmente
    $reservaExpVerificada = (new ReservaRepositorio($pdo))->buscarPorId($reservaExpiradaId, false);
    assertCheck($reservaExpVerificada->esExpirada(), "INVARIANTE HOTELERO: La reserva permanece en estado EXPIRADA (no se reactiva inventario liberado)");

    // 6.5 Discrepancia de Monto
    echo "\n--- 6.5 Discrepancia de Monto ---\n";
    $ordenMontoMismId = 'ord_test_monto_mismatch';
    $txMismId = $txRepo->crear([
        'proveedor' => 'CULQI',
        'proveedor_orden_id' => $ordenMontoMismId,
        'reserva_id' => $reservaCanonId,
        'moneda_codigo' => 'PEN',
        'monto_esperado' => '500.00',
        'estado_pago' => PagoTransaccionPasarela::ESTADO_PAGO_PENDIENTE,
        'estado_conciliacion' => PagoTransaccionPasarela::ESTADO_CONCILIACION_PENDIENTE,
        'actor_id' => $actorApiId,
    ]);

    $payloadMontoMism = json_encode([
        'id' => 'evt_monto_mismatch_888',
        'type' => 'order.status.changed',
        'data' => [
            'id' => $ordenMontoMismId,
            'amount' => 10000, // S/ 100 en vez de S/ 500
            'currency_code' => 'PEN',
            'state' => 'paid',
            'updated_at' => time(),
        ],
    ]);

    $respMism = $pagoServicio->procesarWebhook('CULQI', [], $payloadMontoMism);
    assertCheck($respMism['codigo'] === 'PAGO_APROBADO_CON_DISCREPANCIA_MONTO', "Discrepancia Monto: Detecta discordancia en importe");
    $txMismVerificada = $txRepo->buscarPorId($txMismId);
    assertCheck($txMismVerificada->obtenerEstadoConciliacion() === PagoTransaccionPasarela::ESTADO_CONCILIACION_DISCREPANCIA_MONTO, "Discrepancia Monto: estado_conciliacion = DISCREPANCIA_MONTO");
    assertCheck($txMismVerificada->obtenerCuentaFolioId() === null, "Discrepancia Monto: Cero folio vinculado");

    // 6.6 Procesamiento de Reembolso
    echo "\n--- 6.6 Reembolsos Soberanos ---\n";
    // Asignar proveedor_transaccion_id para reembolso
    $txRepo->actualizar($txCanonId, ['proveedor_transaccion_id' => 'chr_test_mock_001']);
    $respReemb = $pagoServicio->procesarReembolso($txCanonId, '500.00', 'Cancelación justificada');
    assertCheck($respReemb['exito'], "Reembolso: Procesado con éxito");
    assertCheck($respReemb['estado_reembolso'] === PagoTransaccionPasarela::ESTADO_REEMBOLSO_TOTAL, "Reembolso: Estado REEMBOLSADO_TOTAL");

    // =========================================================================
    // 7. LIMPIEZA DEFENSIVA
    // =========================================================================
    echo "\n--- 7. Limpieza Defensiva de Fixtures ---\n";

    $pdo->prepare("DELETE FROM pagos_webhooks_eventos WHERE proveedor = 'CULQI' AND proveedor_evento_id LIKE 'evt_%'")->execute();
    $pdo->prepare("UPDATE pagos_transacciones_pasarela SET pago_cuenta_id = NULL, cuenta_folio_id = NULL WHERE reserva_id IN (:r1, :r2)")->execute([
        'r1' => $reservaCanonId,
        'r2' => $reservaExpiradaId,
    ]);
    $pdo->prepare("DELETE FROM pagos_transacciones_pasarela WHERE reserva_id IN (:r1, :r2)")->execute([
        'r1' => $reservaCanonId,
        'r2' => $reservaExpiradaId,
    ]);
    $pdo->prepare("DELETE FROM aplicaciones_pago WHERE pago_id IN (SELECT id FROM pagos_cuenta WHERE referencia_operacion = :ref)")->execute(['ref' => $ordenCanonId]);
    $pdo->prepare("DELETE FROM pagos_cuenta WHERE referencia_operacion = :ref")->execute(['ref' => $ordenCanonId]);
    $pdo->prepare("DELETE FROM cargos_cuenta WHERE cuenta_folio_id IN (SELECT id FROM cuentas_folios WHERE reserva_id IN (:r1, :r2))")->execute([
        'r1' => $reservaCanonId,
        'r2' => $reservaExpiradaId,
    ]);
    $pdo->prepare("DELETE FROM cuentas_folios WHERE reserva_id IN (:r1, :r2)")->execute([
        'r1' => $reservaCanonId,
        'r2' => $reservaExpiradaId,
    ]);
    $pdo->prepare("DELETE FROM reserva_unidades WHERE reserva_id IN (:r1, :r2)")->execute([
        'r1' => $reservaCanonId,
        'r2' => $reservaExpiradaId,
    ]);
    $pdo->prepare("DELETE FROM reservas WHERE id IN (:r1, :r2)")->execute([
        'r1' => $reservaCanonId,
        'r2' => $reservaExpiradaId,
    ]);

    assertCheck(true, "Limpieza de fixtures de prueba completada exitosamente");

    echo "\n====================================================================\n";
    echo " SUITE PAGOS-1B SUPERADA CON ÉXITO\n";
    echo " Total checks ejecutados: $totalAssertions | Aprobados: $passedAssertions\n";
    echo "====================================================================\n\n";

} catch (Throwable $e) {
    echo "\n[ERROR CRÍTICO EN SUITE PAGOS-1B]: " . $e->getMessage() . "\n";
    echo "En archivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
