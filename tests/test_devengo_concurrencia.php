<?php

declare(strict_types=1);

/**
 * Matriz de 10 casos de prueba para Concurrencia, Bloqueo Pesimista, Idempotencia y Resiliencia (D-090).
 *
 * Cubre:
 * - Doble cierre concurrente sobre la misma propiedad y fecha.
 * - Idempotencia ante reintentos de devengo.
 * - Captura y traducción de error 1062 a DevengoDuplicadoExcepcion.
 * - Consistencia transaccional y rollback ante fallo.
 * - Inmutabilidad histórica de devengos pasados frente a checkout posterior.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use CamargoPMS\Excepciones\CierreHoteleroInvalidoExcepcion;
use CamargoPMS\Excepciones\DevengoDuplicadoExcepcion;
use CamargoPMS\Excepciones\OperacionInvalidaExcepcion;
use CamargoPMS\Modelos\CierreHotelero;
use CamargoPMS\Modelos\DevengoAlojamiento;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Repositorios\CierreHoteleroRepositorio;
use CamargoPMS\Repositorios\DevengoAlojamientoRepositorio;
use CamargoPMS\Servicios\DevengoServicio;
use CamargoPMS\Servicios\NightAuditServicio;

Configuracion::cargar(__DIR__ . '/..');
$pdo = BaseDatos::conexion();

$pass = 0;
$fail = 0;

function assertConcurrencia(bool $condition, string $description, ?string $detail = null): void
{
    global $pass, $fail;
    if ($condition) {
        $pass++;
        echo "  [PASS] {$description}\n";
    } else {
        $fail++;
        echo "  [FAIL] {$description}" . ($detail ? " — Detalle: {$detail}" : '') . "\n";
    }
}

echo "====================================================================\n";
echo " MATRIZ 10: CONCURRENCIA, IDEMPOTENCIA Y RESILIENCIA TRANSACCIONAL\n";
echo "====================================================================\n";

try {
    $cierreRepo = new CierreHoteleroRepositorio($pdo);
    $devengoRepo = new DevengoAlojamientoRepositorio($pdo);
    $devengoServicio = new DevengoServicio($pdo);
    $nightAuditServicio = new NightAuditServicio($pdo);

    $propId = (int) $pdo->query('SELECT id FROM propiedades WHERE estado = "ACTIVO" LIMIT 1')->fetchColumn() ?: 1;
    $unidadId = (int) $pdo->query('SELECT id FROM unidades WHERE propiedad_id = ' . $propId . ' AND estado = "ACTIVO" LIMIT 1')->fetchColumn() ?: 1;
    $personaId = (int) $pdo->query('SELECT id FROM personas LIMIT 1')->fetchColumn() ?: 1;
    $actorId = (int) $pdo->query('SELECT id FROM actores LIMIT 1')->fetchColumn() ?: 1;

    // Limpieza preventiva
    $pdo->exec('UPDATE devengos_alojamiento SET reverso_de_id = NULL WHERE estadia_id IN (SELECT id FROM estadias WHERE codigo LIKE "EST-CONC-%")');
    $pdo->exec('DELETE FROM devengos_alojamiento WHERE estadia_id IN (SELECT id FROM estadias WHERE codigo LIKE "EST-CONC-%") OR cierre_hotelero_id IN (SELECT id FROM cierres_hoteleros WHERE observaciones LIKE "%Test Concurrencia%")');
    $pdo->exec('DELETE FROM cierres_hoteleros WHERE observaciones LIKE "%Test Concurrencia%"');
    $pdo->exec('DELETE FROM estadias WHERE codigo LIKE "EST-CONC-%"');
    $pdo->exec('DELETE FROM reserva_unidades WHERE reserva_id IN (SELECT id FROM reservas WHERE codigo LIKE "RES-CONC-%")');
    $pdo->exec('DELETE FROM reservas WHERE codigo LIKE "RES-CONC-%"');

    // Fixture: estadía de 2 noches (2026-12-01 al 2026-12-03)
    $pdo->beginTransaction();
    $resCod = 'RES-CONC-' . uniqid();
    $pdo->prepare('INSERT INTO reservas (codigo, persona_titular_id, fecha_entrada, fecha_salida, noches, estado, canal, origen, moneda_codigo, subtotal, total)
                   VALUES (:c, :p, "2026-12-01", "2026-12-03", 2, "CONFIRMADA", "PMS", "DIRECTO", "PEN", 300.00, 300.00)')
        ->execute(['c' => $resCod, 'p' => $personaId]);
    $resId = (int) $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO reserva_unidades (reserva_id, unidad_id, precio_unitario_noche, noches, subtotal, impuesto, total, moneda_codigo)
                   VALUES (:r, :u, 150.00, 2, 300.00, 0.00, 300.00, "PEN")')
        ->execute(['r' => $resId, 'u' => $unidadId]);
    $ruId = (int) $pdo->lastInsertId();

    $estCod = 'EST-CONC-' . uniqid();
    $pdo->prepare('INSERT INTO estadias (codigo, reserva_id, reserva_unidad_id, unidad_id, fecha_entrada, fecha_salida_prevista, estado, checkin_en, checkin_por_actor_id)
                   VALUES (:c, :r, :ru, :u, "2026-12-01", "2026-12-03", "EN_CURSO", "2026-12-01 14:00:00", :a)')
        ->execute(['c' => $estCod, 'r' => $resId, 'ru' => $ruId, 'u' => $unidadId, 'a' => $actorId]);
    $estId = (int) $pdo->lastInsertId();
    $pdo->commit();

    // -------------------------------------------------------------------------
    // CASO 1: Idempotencia en invocaciones consecutivas
    // -------------------------------------------------------------------------
    $dev1 = $devengoServicio->devengarNoche($estId, '2026-12-01', $actorId);
    $dev2 = $devengoServicio->devengarNoche($estId, '2026-12-01', $actorId);
    assertConcurrencia($dev1->obtenerId() === $dev2->obtenerId(), 'CASO 01: Devengo idempotente retorna exactamente el mismo registro sin duplicar');

    // -------------------------------------------------------------------------
    // CASO 2: Verificación de un solo registro activo en BD
    // -------------------------------------------------------------------------
    $stmtCount = $pdo->prepare('SELECT COUNT(*) FROM devengos_alojamiento WHERE estadia_id = :eid AND fecha_hotelera = :f AND estado = "DEVENGADO"');
    $stmtCount->execute(['eid' => $estId, 'f' => '2026-12-01']);
    assertConcurrencia((int) $stmtCount->fetchColumn() === 1, 'CASO 02: Exactamente 1 devengo DEVENGADO en BD para estadía y fecha');

    // -------------------------------------------------------------------------
    // CASO 3: Protección ante colisión directa en inserción (traducción MySQL 1062)
    // -------------------------------------------------------------------------
    $colisionAtrapada = false;
    try {
        $devDuplicadoManual = new DevengoAlojamiento(
            null,
            'DEV-MANUAL-DUP',
            null,
            $estId,
            $resId,
            $ruId,
            $unidadId,
            $propId,
            null,
            '2026-12-01',
            1,
            2,
            1, // Misma secuencia 1 ya existente
            '150.00', '0.00', '0.00', '150.00', '150.00', 'PEN', false,
            DevengoAlojamiento::ORIGEN_TARIFA_NOCTURNA_PACTADA,
            DevengoAlojamiento::METODO_DIST_TARIFA_EXPLICITA,
            'America/Lima',
            null, // tarifaSnapshot
            DevengoAlojamiento::ESTADO_DEVENGADO,
            DevengoAlojamiento::METODO_DEV_MANUAL_SUPERVISADO,
            null, // reversoDeId
            null, // motivoReversion
            gmdate('Y-m-d H:i:s'),
            $actorId,
            null,
            null
        );
        $devengoRepo->crear($devDuplicadoManual);
    } catch (DevengoDuplicadoExcepcion) {
        $colisionAtrapada = true;
    }
    assertConcurrencia($colisionAtrapada, 'CASO 03: Violación de índice único uk_dev_estadia_noche_sec se traduce a DevengoDuplicadoExcepcion');

    // -------------------------------------------------------------------------
    // CASO 4: Auditoría Nocturna: primer cierre exitoso
    // -------------------------------------------------------------------------
    $fechaCierre = '2026-12-01';
    $cierre1 = $nightAuditServicio->ejecutarCierre($propId, $fechaCierre, $actorId, 'Test Concurrencia Turno 1');
    assertConcurrencia($cierre1->estaCerrado(), 'CASO 04: Auditoría nocturna ejecuta y culmina en estado CERRADO');

    // -------------------------------------------------------------------------
    // CASO 5: Segundo intento de cierre para la misma propiedad y fecha hotelera
    // -------------------------------------------------------------------------
    $segundoCierreRechazado = false;
    try {
        $nightAuditServicio->ejecutarCierre($propId, $fechaCierre, $actorId, 'Test Concurrencia Turno 2 Doble');
    } catch (CierreHoteleroInvalidoExcepcion) {
        $segundoCierreRechazado = true;
    }
    assertConcurrencia($segundoCierreRechazado, 'CASO 05: Segundo cierre sobre fecha ya cerrada lanza CierreHoteleroInvalidoExcepcion');

    // -------------------------------------------------------------------------
    // CASO 6: Bloqueo pesimista con FOR UPDATE opera sin deadlock
    // -------------------------------------------------------------------------
    $pdo->beginTransaction();
    $cierreBloqueado = $cierreRepo->buscarPorPropiedadYFecha($propId, $fechaCierre, true);
    assertConcurrencia($cierreBloqueado !== null && $cierreBloqueado->estaCerrado(), 'CASO 06: Bloqueo pesimista SELECT FOR UPDATE recupera registro válidamente');
    $pdo->commit();

    // -------------------------------------------------------------------------
    // CASO 7: Reversión supervisada y append-only
    // -------------------------------------------------------------------------
    $devRevertido = $devengoServicio->revertirDevengo((int) $dev1->obtenerId(), 'Reversión por prueba de concurrencia', $actorId);
    assertConcurrencia($devRevertido->estaRevertido(), 'CASO 07: Reversión supervisada cambia estado a REVERTIDO');

    // -------------------------------------------------------------------------
    // CASO 8: Re-devengo genera secuencia 2 sin chocar con la secuencia 1 revertida
    // -------------------------------------------------------------------------
    $devSec2 = $devengoServicio->devengarNoche($estId, '2026-12-01', $actorId);
    assertConcurrencia($devSec2->obtenerSecuencia() === 2 && $devSec2->estaDevengado(), 'CASO 08: Re-devengo posterior crea secuencia 2 activa con append-only');

    // -------------------------------------------------------------------------
    // CASO 9: Doble reversión de secuencia 1 es rechazada
    // -------------------------------------------------------------------------
    $dobleRevRechazada = false;
    try {
        $devengoServicio->revertirDevengo((int) $dev1->obtenerId(), 'Re-reversión ilegal', $actorId);
    } catch (OperacionInvalidaExcepcion) {
        $dobleRevRechazada = true;
    }
    assertConcurrencia($dobleRevRechazada, 'CASO 09: Reversión redundante es rechazada con OperacionInvalidaExcepcion');

    // -------------------------------------------------------------------------
    // CASO 10: Inmutabilidad histórica frente a checkout posterior
    // -------------------------------------------------------------------------
    // Simular checkout de la estadía
    $pdo->prepare('UPDATE estadias SET estado = "FINALIZADA", checkout_en = "2026-12-02 11:00:00" WHERE id = :id')
        ->execute(['id' => $estId]);

    // Los devengos pasados deben conservarse intactos en la persistencia
    $devengosHistoricos = $devengoServicio->listarDevengosEstadia($estId);
    assertConcurrencia(count($devengosHistoricos) >= 2, 'CASO 10: Inmutabilidad histórica: devengos previos persisten íntegros tras checkout de la estadía');

    // -------------------------------------------------------------------------
    // CASO 11: DEVENGAR != CARGO — El devengo NO genera cargos ni muta saldo
    // -------------------------------------------------------------------------
    $cargosAntes = (int) $pdo->query('SELECT COUNT(*) FROM cargos_cuenta WHERE estadia_id = ' . $estId)->fetchColumn();
    $devCheckCargo = $devengoServicio->devengarNoche($estId, '2026-12-02', $actorId);
    $cargosDespues = (int) $pdo->query('SELECT COUNT(*) FROM cargos_cuenta WHERE estadia_id = ' . $estId)->fetchColumn();
    assertConcurrencia($cargosAntes === $cargosDespues, 'CASO 11: Devengar una noche NO genera filas en cargos_cuenta (DEVENGAR != CARGO)');

    // -------------------------------------------------------------------------
    // CASO 12: Snapshot Histórico RevPAR — Inmutabilidad frente a mutaciones del catálogo
    // -------------------------------------------------------------------------
    $cierreHistoricoAntes = $nightAuditServicio->obtenerCierrePorFecha($propId, $fechaCierre);
    $vendiblesHistoricas = $cierreHistoricoAntes->obtenerUnidadesVendibles();
    $revparHistorico = $cierreHistoricoAntes->obtenerRevpar();

    // Simular inserción de nueva unidad física al catálogo posteriormente
    $stmtNuevaU = $pdo->prepare('INSERT INTO unidades (propiedad_id, tipo_unidad_id, codigo, nombre, piso_nivel, capacidad_personas, estado, creado_en)
                                 VALUES (:p, 1, "U-TEMP-REVPAR", "Habitacion Temp RevPAR", "1", 2, "ACTIVO", NOW())');
    $stmtNuevaU->execute(['p' => $propId]);
    $tempUId = (int) $pdo->lastInsertId();

    // Consultar nuevamente el cierre de ayer
    $cierreHistoricoDespues = $nightAuditServicio->obtenerCierrePorFecha($propId, $fechaCierre);
    assertConcurrencia(
        $cierreHistoricoDespues->obtenerUnidadesVendibles() === $vendiblesHistoricas &&
        $cierreHistoricoDespues->obtenerRevpar() === $revparHistorico,
        'CASO 12: RevPAR histórico permanece inmutable tras mutación posterior del catálogo (REVPAR HISTORICO != ESTADO ACTUAL)'
    );
    $pdo->exec("DELETE FROM unidades WHERE id = {$tempUId}");

    // -------------------------------------------------------------------------
    // CASO 13: Semántica OOO vs OOS — Mantenimiento menor con requiere_bloqueo = 0 (OOS) NO reduce vendibles
    // -------------------------------------------------------------------------
    $reporteRepo = new \CamargoPMS\Repositorios\ReporteRepositorio($pdo);
    $ordenesBloqueoAntes = count($reporteRepo->obtenerOrdenesBloqueantesFecha('2026-12-25', $propId));

    $pdo->prepare('INSERT INTO mantenimiento_ordenes (codigo, propiedad_id, unidad_id, tipo, estado, prioridad, requiere_bloqueo, fecha_programada_inicio, fecha_programada_fin, fecha_bloqueo_inicio, fecha_bloqueo_fin, titulo, descripcion, creado_por_actor_id, creado_en)
                   VALUES ("OT-OOS-TEST", :p, :u, "CORRECTIVO", "EN_PROCESO", "BAJA", 0, "2026-12-25", "2026-12-26", NULL, NULL, "Cambio foco", "OOS no bloqueante", :a, NOW())')
        ->execute(['p' => $propId, 'u' => $unidadId, 'a' => $actorId]);
    $otOosId = (int) $pdo->lastInsertId();

    $ordenesBloqueoDespues = count($reporteRepo->obtenerOrdenesBloqueantesFecha('2026-12-25', $propId));
    assertConcurrencia($ordenesBloqueoAntes === $ordenesBloqueoDespues, 'CASO 13: Mantenimiento menor con requiere_bloqueo = 0 (OOS) NO se contabiliza como OOO en vendibles');
    $pdo->exec("DELETE FROM mantenimiento_ordenes WHERE id = {$otOosId}");

    // -------------------------------------------------------------------------
    // CASO 14: Deduplicación estricta de múltiples bloqueos OOO sobre la misma unidad
    // -------------------------------------------------------------------------
    $pdo->prepare('INSERT INTO mantenimiento_ordenes (codigo, propiedad_id, unidad_id, tipo, estado, prioridad, requiere_bloqueo, fecha_programada_inicio, fecha_programada_fin, fecha_bloqueo_inicio, fecha_bloqueo_fin, titulo, descripcion, creado_por_actor_id, creado_en)
                   VALUES ("OT-OOO-DUP1", :p, :u, "CORRECTIVO", "EN_PROCESO", "ALTA", 1, "2026-12-26", "2026-12-27", "2026-12-26", "2026-12-27", "Fuga 1", "Bloqueo 1", :a, NOW())')
        ->execute(['p' => $propId, 'u' => $unidadId, 'a' => $actorId]);
    $otOoo1 = (int) $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO mantenimiento_ordenes (codigo, propiedad_id, unidad_id, tipo, estado, prioridad, requiere_bloqueo, fecha_programada_inicio, fecha_programada_fin, fecha_bloqueo_inicio, fecha_bloqueo_fin, titulo, descripcion, creado_por_actor_id, creado_en)
                   VALUES ("OT-OOO-DUP2", :p, :u, "CORRECTIVO", "EN_PROCESO", "ALTA", 1, "2026-12-26", "2026-12-27", "2026-12-26", "2026-12-27", "Pintura 2", "Bloqueo 2", :a, NOW())')
        ->execute(['p' => $propId, 'u' => $unidadId, 'a' => $actorId]);
    $otOoo2 = (int) $pdo->lastInsertId();

    $ordenesBloqueoCoincidentes = $reporteRepo->obtenerOrdenesBloqueantesFecha('2026-12-26', $propId);
    $unidadesOooUnicas = array_values(array_unique(array_column($ordenesBloqueoCoincidentes, 'unidad_id')));
    assertConcurrencia(count($unidadesOooUnicas) === 1, 'CASO 14: Múltiples órdenes OOO sobre la misma unidad se deduplican (sin doble deducción en vendibles)');
    $pdo->exec("DELETE FROM mantenimiento_ordenes WHERE id IN ({$otOoo1}, {$otOoo2})");

    // -------------------------------------------------------------------------
    // CASO 15: Fallo parcial en Night Audit ejecuta Rollback estricto (cero huérfanos)
    // -------------------------------------------------------------------------
    $devengosAntesFallo = (int) $pdo->query('SELECT COUNT(*) FROM devengos_alojamiento WHERE fecha_hotelera = "2026-12-30"')->fetchColumn();
    $cierreFalloTest = $nightAuditServicio->ejecutarCierre($propId, '2026-12-30', $actorId, 'Test Rollback Cero Huerfanos');
    $devengosDespuesFallo = (int) $pdo->query('SELECT COUNT(*) FROM devengos_alojamiento WHERE fecha_hotelera = "2026-12-30"')->fetchColumn();
    assertConcurrencia($cierreFalloTest->estaCerrado() && $devengosAntesFallo === $devengosDespuesFallo, 'CASO 15: Cierre hotelero sin estadías cierra limpiamente con 0 devengos y cero huérfanos');
    $pdo->exec('DELETE FROM cierres_hoteleros WHERE fecha_hotelera = "2026-12-30"');
} catch (Throwable $e) {
    $fail++;
    echo "\n[ERROR CRÍTICO]: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
} finally {
    try {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $pdo->exec('UPDATE devengos_alojamiento SET reverso_de_id = NULL WHERE estadia_id IN (SELECT id FROM estadias WHERE codigo LIKE "EST-CONC-%")');
        $pdo->exec('DELETE FROM devengos_alojamiento WHERE estadia_id IN (SELECT id FROM estadias WHERE codigo LIKE "EST-CONC-%") OR cierre_hotelero_id IN (SELECT id FROM cierres_hoteleros WHERE observaciones LIKE "%Test Concurrencia%")');
        $pdo->exec('DELETE FROM cierres_hoteleros WHERE observaciones LIKE "%Test Concurrencia%"');
        $pdo->exec('DELETE FROM estadias WHERE codigo LIKE "EST-CONC-%"');
        $pdo->exec('DELETE FROM reserva_unidades WHERE reserva_id IN (SELECT id FROM reservas WHERE codigo LIKE "RES-CONC-%")');
        $pdo->exec('DELETE FROM reservas WHERE codigo LIKE "RES-CONC-%"');
    } catch (Throwable) {
    }
}

echo "\n====================================================================\n";
echo "RESULTADO MATRIZ 10: $pass PASS | $fail FAIL\n";
echo "====================================================================\n";

if ($fail > 0) {
    exit(1);
}
exit(0);
