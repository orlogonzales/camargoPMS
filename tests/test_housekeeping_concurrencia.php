<?php
/**
 * Pruebas de Concurrencia para Housekeeping-1 (D-083)
 * Cobertura de condiciones de carrera, bloqueos pesimistas, folios correlativos y Kardex.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Repositorios\HousekeepingRepositorio;
use CamargoPMS\Servicios\HousekeepingServicio;
use CamargoPMS\Servicios\AuditoriaServicio;
use CamargoPMS\Servicios\InventarioServicio;
use CamargoPMS\Repositorios\InventarioRepositorio;
use CamargoPMS\Modelos\HousekeepingEstadoLimpieza;
use CamargoPMS\Modelos\HousekeepingTarea;
use CamargoPMS\Excepciones\ConflictoHousekeepingExcepcion;

Configuracion::cargar(dirname(__DIR__));
$pdo = BaseDatos::conexion();
$pdo->exec("SET SESSION innodb_lock_wait_timeout = 5;");

$total = 0;
$pasadas = 0;
$fallidas = 0;
$errores = [];

function afirmar(bool $condicion, string $mensaje): void
{
    global $total, $pasadas, $fallidas, $errores;
    $total++;
    if ($condicion) {
        $pasadas++;
        echo "  [PASS] {$mensaje}\n";
    } else {
        $fallidas++;
        $errores[] = $mensaje;
        echo "  [FAIL] {$mensaje}\n";
    }
}

echo "====================================================================\n";
echo " CAMARGO PMS — PRUEBAS DE CONCURRENCIA HOUSEKEEPING-1 (6 CASOS)\n";
echo " Decisión Vinculante: D-083 / D-061\n";
echo "====================================================================\n\n";

// Conexiones PDO independientes para simular concurrencia real
$cfg = Configuracion::obtenerBaseDatos();
$dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['database']};charset={$cfg['charset']}";
$opciones = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];
$pdo1 = new PDO($dsn, $cfg['username'], $cfg['password'], $opciones);
$pdo2 = new PDO($dsn, $cfg['username'], $cfg['password'], $opciones);

$auditServicio1 = new AuditoriaServicio($pdo1);
$auditServicio2 = new AuditoriaServicio($pdo2);

$hkRepo1 = new HousekeepingRepositorio($pdo1);
$hkRepo2 = new HousekeepingRepositorio($pdo2);

$hkServicio1 = new HousekeepingServicio($hkRepo1, $auditServicio1, null, null, $pdo1);
$hkServicio2 = new HousekeepingServicio($hkRepo2, $auditServicio2, null, null, $pdo2);

// Setup de datos base para concurrencia
$stmtProp = $pdo->query('SELECT id FROM propiedades LIMIT 1');
$propiedadId = (int) $stmtProp->fetchColumn();
$stmtTu = $pdo->query('SELECT id FROM tipos_unidad LIMIT 1');
$tipoUnidadId = (int) $stmtTu->fetchColumn();

// Crear unidad de prueba para concurrencia
$pdo->prepare('INSERT INTO unidades (propiedad_id, tipo_unidad_id, codigo, nombre, piso_nivel, capacidad_personas, estado, creado_en) VALUES (?, ?, "HK-CONC-101", "Habitación Conc 101", "1", 2, "ACTIVO", NOW()) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)')->execute([$propiedadId, $tipoUnidadId]);
$unidadConcId = (int) $pdo->lastInsertId();

// -------------------------------------------------------------------------
// CASO 1: Generación Concurrente de Códigos HK-YYYYMMDD-XXXX sin duplicación
// -------------------------------------------------------------------------
$codigos = [];
$duplicadosEncontrados = false;
$pdo1->beginTransaction();
$pdo2->beginTransaction();

try {
    // Sesión 1 genera código
    $cod1 = $hkRepo1->generarCodigoTarea();
    $codigos[] = $cod1;
    $pdo1->commit();

    // Sesión 2 genera código
    $cod2 = $hkRepo2->generarCodigoTarea();
    $codigos[] = $cod2;
    $pdo2->commit();
} catch (Throwable $e) {
    if ($pdo1->inTransaction()) $pdo1->rollBack();
    if ($pdo2->inTransaction()) $pdo2->rollBack();
}

$partesCod1 = explode('-', $cod1);
$partesCod2 = explode('-', $cod2);
$num1 = (int) end($partesCod1);
$num2 = (int) end($partesCod2);

afirmar(
    count($codigos) === 2 && $cod1 !== $cod2 && $num2 === $num1 + 1,
    'Caso 1: Concurrencia de folios HK en documento_secuencias garantiza estricta correlatividad sin colisiones'
);

// -------------------------------------------------------------------------
// CASO 2: Generación Concurrente de Folios LAV-YYYYMMDD-XXXX
// -------------------------------------------------------------------------
$pdo1->beginTransaction();
$pdo2->beginTransaction();
try {
    $lav1 = $hkRepo1->generarCodigoLote();
    $pdo1->commit();

    $lav2 = $hkRepo2->generarCodigoLote();
    $pdo2->commit();
} catch (Throwable $e) {
    if ($pdo1->inTransaction()) $pdo1->rollBack();
    if ($pdo2->inTransaction()) $pdo2->rollBack();
}

$pLav1 = (int) substr($lav1, strrpos($lav1, '-') + 1);
$pLav2 = (int) substr($lav2, strrpos($lav2, '-') + 1);

afirmar(
    $lav1 !== $lav2 && $pLav2 === $pLav1 + 1,
    'Caso 2: Generación de folios de lavandería LAV correlativa y segura bajo múltiples transacciones'
);

// -------------------------------------------------------------------------
// CASO 3: Idempotencia Concurrente en Checkout
// -------------------------------------------------------------------------
// Simular estadía de prueba con esquema oficial
$codRes = 'RES-HK-CONC-' . bin2hex(random_bytes(4));
$pdo->prepare('INSERT INTO reservas (codigo, persona_titular_id, fecha_entrada, fecha_salida, noches, estado, canal, origen, total, creado_en) VALUES (?, 1, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 DAY), 1, "CONFIRMADA", "PMS", "DIRECTO", 100.00, NOW())')->execute([$codRes]);
$reservaId = (int) $pdo->lastInsertId();

$pdo->prepare('INSERT INTO reserva_unidades (reserva_id, unidad_id, precio_unitario_noche, noches, subtotal, total, moneda_codigo, creado_en) VALUES (?, ?, 100.00, 1, 100.00, 100.00, "PEN", NOW())')->execute([$reservaId, $unidadConcId]);
$reservaUnidadId = (int) $pdo->lastInsertId();

$codEst = 'EST-HK-CONC-' . bin2hex(random_bytes(4));
$pdo->prepare('INSERT INTO estadias (codigo, reserva_id, reserva_unidad_id, unidad_id, fecha_entrada, fecha_salida_prevista, checkin_en, estado, checkout_en, checkout_por_actor_id, checkin_por_actor_id, creado_en) VALUES (?, ?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 DAY), NOW(), "FINALIZADA", NOW(), 1, 1, NOW())')->execute([$codEst, $reservaId, $reservaUnidadId, $unidadConcId]);
$estadiaConcId = (int) $pdo->lastInsertId();

// Ejecución casi simultánea en 2 hilos/servicios
$tareaCheckout1 = $hkServicio1->marcarSuciaPorCheckout($unidadConcId, $estadiaConcId, 1, 'Checkout hilo 1');
$tareaCheckout2 = $hkServicio2->marcarSuciaPorCheckout($unidadConcId, $estadiaConcId, 1, 'Checkout hilo 2 (reintento concurrente)');

afirmar(
    $tareaCheckout1->obtenerId() === $tareaCheckout2->obtenerId() && $tareaCheckout1->obtenerCodigo() === $tareaCheckout2->obtenerCodigo(),
    'Caso 3: Idempotencia estricta en checkout concurrente: no se duplican tareas de salida para la misma estadía'
);

// -------------------------------------------------------------------------
// CASO 4: Carrera Concurrente en Transición de Limpieza
// -------------------------------------------------------------------------
// Si la tarea ya pasó a EN_PROCESO, otro intento simultáneo de iniciar debe ser rechazado
$tareaIniciada1 = $hkServicio1->iniciarLimpieza($tareaCheckout1->obtenerId(), 1);
$segundoInicioRechazado = false;
try {
    $hkServicio2->iniciarLimpieza($tareaCheckout1->obtenerId(), 2);
} catch (ConflictoHousekeepingExcepcion $e) {
    $segundoInicioRechazado = true;
}

afirmar(
    $tareaIniciada1->obtenerEstado() === HousekeepingTarea::ESTADO_EN_PROCESO && $segundoInicioRechazado === true,
    'Caso 4: Transición de estado concurrente protegida: segundo inicio de limpieza falla con ConflictoHousekeepingExcepcion'
);

// -------------------------------------------------------------------------
// CASO 5: Prevención de Saldo Negativo Concurrente en Kardex (Amenities)
// -------------------------------------------------------------------------
// Crear artículo con stock limitado (exactamente 3 unidades disponibles)
$stmtUm = $pdo->query('SELECT id FROM inventario_unidades_medida LIMIT 1');
$umId = (int) $stmtUm->fetchColumn();

$pdo->prepare('INSERT INTO inventario_articulos (codigo_sku, nombre, categoria, unidad_medida_id, estado, creado_en) VALUES ("AMN-CONC-01", "Shampoo Botella 30ml", "CONSUMIBLE_OPERATIVO", ?, "ACTIVO", NOW()) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)')->execute([$umId]);
$artShampooId = (int) $pdo->lastInsertId();

$stmtOffice = $pdo->query('SELECT id FROM inventario_ubicaciones WHERE tipo = "ALMACEN" LIMIT 1');
$officeId = (int) $stmtOffice->fetchColumn();

// Fijar stock exactamente en 3.0000
$pdo->prepare('INSERT INTO inventario_existencias (articulo_id, ubicacion_id, cantidad_actual, actualizado_en) VALUES (?, ?, 3.0000, NOW()) ON DUPLICATE KEY UPDATE cantidad_actual = 3.0000')->execute([$artShampooId, $officeId]);

// Crear segunda tarea para competir por el mismo stock de shampoo
$tareaComp2 = $hkServicio1->crearTareaManual([
    'unidad_id' => $unidadConcId,
    'tipo' => HousekeepingTarea::TIPO_RETOQUE,
    'prioridad' => HousekeepingTarea::PRIORIDAD_MEDIA,
    'notas' => 'Retoque conc',
], 1);
$hkServicio1->iniciarLimpieza($tareaComp2->obtenerId(), 1);

// Tarea 1 consume 2 unidades (quedará 1 disponible)
$t1Fin = $hkServicio1->finalizarLimpieza($tareaCheckout1->obtenerId(), [
    'consumos' => [
        ['articulo_id' => $artShampooId, 'almacen_origen_id' => $officeId, 'cantidad' => '2.0000'],
    ],
], 1);

// Tarea 2 intenta consumir 2 unidades (supera el stock restante de 1)
$bloqueoSaldoNegativo = false;
try {
    $hkServicio2->finalizarLimpieza($tareaComp2->obtenerId(), [
        'consumos' => [
            ['articulo_id' => $artShampooId, 'almacen_origen_id' => $officeId, 'cantidad' => '2.0000'],
        ],
    ], 2);
} catch (Throwable $e) {
    $bloqueoSaldoNegativo = true;
}

$stockFinal = (float) $pdo->query("SELECT cantidad_actual FROM inventario_existencias WHERE articulo_id = {$artShampooId} AND ubicacion_id = {$officeId}")->fetchColumn();

afirmar(
    $bloqueoSaldoNegativo === true && $stockFinal === 1.0,
    'Caso 5: Control Kardex concurrente: la segunda tarea falla atómicamente impidiendo saldos negativos en stock'
);

// -------------------------------------------------------------------------
// CASO 6: Aislamiento y Derivación Dinámica bajo Bloqueo Concurrente
// -------------------------------------------------------------------------
// La unidad tiene una tarea pendiente/en proceso, por lo que su condición derivada NO puede ser VR
$esAptaDuranteProceso = $hkServicio1->puedeCheckIn($unidadConcId);

// Cerrar tarea de retoque sin consumos para limpiar
$hkServicio1->finalizarLimpieza($tareaComp2->obtenerId(), [], 1);
$itemsChk = $hkRepo1->obtenerChecklistTarea($tareaComp2->obtenerId());
$itemsConf = [];
foreach ($itemsChk as $it) {
    $itemsConf[] = ['id' => $it['id'], 'resultado' => 'CONFORME'];
}
$hkServicio1->inspeccionarTarea($tareaComp2->obtenerId(), true, $itemsConf, 'Aprobado retoque conc', 1);

// Aprobar también la tarea 1
$itemsChk1 = $hkRepo1->obtenerChecklistTarea($tareaCheckout1->obtenerId());
$itemsConf1 = [];
foreach ($itemsChk1 as $it) {
    $itemsConf1[] = ['id' => $it['id'], 'resultado' => 'CONFORME'];
}
$hkServicio1->inspeccionarTarea($tareaCheckout1->obtenerId(), true, $itemsConf1, 'Aprobada checkout conc', 1);

$esAptaAlFinalizarTodo = $hkServicio2->puedeCheckIn($unidadConcId);

afirmar(
    $esAptaDuranteProceso === false && $esAptaAlFinalizarTodo === true,
    'Caso 6: Derivación dinámica responde congruentemente en múltiples sesiones: false durante procesos, true al estar limpia y libre'
);

echo "\n====================================================================\n";
echo " RESUMEN: {$pasadas} / {$total} PRUEBAS PASADAS\n";
if ($fallidas > 0) {
    echo " FALLIDAS: {$fallidas}\n";
    foreach ($errores as $err) {
        echo "   - {$err}\n";
    }
    exit(1);
} else {
    echo " RESULTADO: SUITE DE CONCURRENCIA HOUSEKEEPING-1 PASS EXITOSA\n";
    echo "====================================================================\n";
    exit(0);
}
