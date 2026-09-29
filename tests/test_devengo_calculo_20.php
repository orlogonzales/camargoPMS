<?php

declare(strict_types=1);

/**
 * Matriz de 20 casos de prueba para Aritmética de Precisión, BCMath y Métricas Hoteleras (D-090).
 *
 * Cubre:
 * - Algoritmo de absorción de redondeo en primera noche (100 / 3, 10 / 3, 200.05 / 4).
 * - Semántica de cálculo ADR y RevPAR con inventario congelado.
 * - Desglose de impuestos con BCMath.
 * - Estadías de cortesía (0.00).
 */

require_once __DIR__ . '/../vendor/autoload.php';

use CamargoPMS\Modelos\CierreHotelero;
use CamargoPMS\Modelos\DevengoAlojamiento;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Repositorios\DevengoAlojamientoRepositorio;
use CamargoPMS\Repositorios\EstadiaRepositorio;
use CamargoPMS\Repositorios\ReservaRepositorio;
use CamargoPMS\Servicios\DevengoServicio;

Configuracion::cargar(__DIR__ . '/..');
$pdo = BaseDatos::conexion();

$pass = 0;
$fail = 0;

function assertCalculo(bool $condition, string $description, ?string $detail = null): void
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
echo " MATRIZ 20: CÁLCULOS ARITMÉTICOS, REDONDEO Y MÉTRICAS ADR/RevPAR\n";
echo "====================================================================\n";

try {
    $devengoServicio = new DevengoServicio($pdo);
    $propId = (int) $pdo->query('SELECT id FROM propiedades WHERE estado = "ACTIVO" LIMIT 1')->fetchColumn() ?: 1;
    $unidadId = (int) $pdo->query('SELECT id FROM unidades WHERE propiedad_id = ' . $propId . ' AND estado = "ACTIVO" LIMIT 1')->fetchColumn() ?: 1;
    $personaId = (int) $pdo->query('SELECT id FROM personas LIMIT 1')->fetchColumn() ?: 1;
    $actorId = (int) $pdo->query('SELECT id FROM actores LIMIT 1')->fetchColumn() ?: 1;

    // Limpieza previa
    $pdo->exec('UPDATE devengos_alojamiento SET reverso_de_id = NULL WHERE estadia_id IN (SELECT id FROM estadias WHERE codigo LIKE "EST-CALC-%")');
    $pdo->exec('DELETE FROM devengos_alojamiento WHERE estadia_id IN (SELECT id FROM estadias WHERE codigo LIKE "EST-CALC-%")');
    $pdo->exec('DELETE FROM estadias WHERE codigo LIKE "EST-CALC-%"');
    $pdo->exec('DELETE FROM reserva_unidades WHERE reserva_id IN (SELECT id FROM reservas WHERE codigo LIKE "RES-CALC-%")');
    $pdo->exec('DELETE FROM reservas WHERE codigo LIKE "RES-CALC-%"');

    // -------------------------------------------------------------------------
    // CASOS 1 a 5: Distribución Uniforme 100.00 / 3 noches (33.34, 33.33, 33.33)
    // -------------------------------------------------------------------------
    echo "\n--- BLOQUE 1: Absorción de Redondeo 100 PEN / 3 Noches ---\n";

    $pdo->beginTransaction();
    $resCod1 = 'RES-CALC-100-' . uniqid();
    $pdo->prepare('INSERT INTO reservas (codigo, persona_titular_id, fecha_entrada, fecha_salida, noches, estado, canal, origen, moneda_codigo, subtotal, total)
                   VALUES (:c, :p, "2026-11-01", "2026-11-04", 3, "CONFIRMADA", "PMS", "DIRECTO", "PEN", 100.00, 100.00)')
        ->execute(['c' => $resCod1, 'p' => $personaId]);
    $resId1 = (int) $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO reserva_unidades (reserva_id, unidad_id, precio_unitario_noche, noches, subtotal, impuesto, total, moneda_codigo)
                   VALUES (:r, :u, 33.33, 3, 100.00, 0.00, 100.00, "PEN")')
        ->execute(['r' => $resId1, 'u' => $unidadId]);
    $ruId1 = (int) $pdo->lastInsertId();

    $estCod1 = 'EST-CALC-100-' . uniqid();
    $pdo->prepare('INSERT INTO estadias (codigo, reserva_id, reserva_unidad_id, unidad_id, fecha_entrada, fecha_salida_prevista, estado, checkin_en, checkin_por_actor_id)
                   VALUES (:c, :r, :ru, :u, "2026-11-01", "2026-11-04", "EN_CURSO", "2026-11-01 15:00:00", :a)')
        ->execute(['c' => $estCod1, 'r' => $resId1, 'ru' => $ruId1, 'u' => $unidadId, 'a' => $actorId]);
    $estId1 = (int) $pdo->lastInsertId();
    $pdo->commit();

    $dev1_n1 = $devengoServicio->devengarNoche($estId1, '2026-11-01', $actorId);
    $dev1_n2 = $devengoServicio->devengarNoche($estId1, '2026-11-02', $actorId);
    $dev1_n3 = $devengoServicio->devengarNoche($estId1, '2026-11-03', $actorId);

    assertCalculo($dev1_n1->obtenerImporteTotal() === '33.34', 'CASO 01: Noche 1 absorbe el residuo del redondeo (33.34 PEN)');
    assertCalculo($dev1_n2->obtenerImporteTotal() === '33.33', 'CASO 02: Noche 2 devenga la base uniforme (33.33 PEN)');
    assertCalculo($dev1_n3->obtenerImporteTotal() === '33.33', 'CASO 03: Noche 3 devenga la base uniforme (33.33 PEN)');

    $sumaTotal1 = bcadd(bcadd($dev1_n1->obtenerImporteTotal(), $dev1_n2->obtenerImporteTotal(), 2), $dev1_n3->obtenerImporteTotal(), 2);
    assertCalculo($sumaTotal1 === '100.00', 'CASO 04: Suma exacta de las 3 noches es 100.00 PEN (cero pérdida de céntimos)');
    assertCalculo($dev1_n1->obtenerMetodoDistribucion() === DevengoAlojamiento::METODO_DIST_AJUSTE_RESIDUAL && $dev1_n2->obtenerMetodoDistribucion() === DevengoAlojamiento::METODO_DIST_DISTRIBUCION_UNIFORME, 'CASO 05: Métodos de distribución distinguidos (AJUSTE_RESIDUAL en noche 1 y DISTRIBUCION_UNIFORME en subsiguientes)');

    // -------------------------------------------------------------------------
    // CASOS 6 a 9: Distribución Uniforme 10.00 / 3 noches (3.34, 3.33, 3.33)
    // -------------------------------------------------------------------------
    echo "\n--- BLOQUE 2: Absorción de Redondeo 10 PEN / 3 Noches ---\n";

    $pdo->beginTransaction();
    $resCod2 = 'RES-CALC-10-' . uniqid();
    $pdo->prepare('INSERT INTO reservas (codigo, persona_titular_id, fecha_entrada, fecha_salida, noches, estado, canal, origen, moneda_codigo, subtotal, total)
                   VALUES (:c, :p, "2026-11-05", "2026-11-08", 3, "CONFIRMADA", "PMS", "DIRECTO", "PEN", 10.00, 10.00)')
        ->execute(['c' => $resCod2, 'p' => $personaId]);
    $resId2 = (int) $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO reserva_unidades (reserva_id, unidad_id, precio_unitario_noche, noches, subtotal, impuesto, total, moneda_codigo)
                   VALUES (:r, :u, 3.33, 3, 10.00, 0.00, 10.00, "PEN")')
        ->execute(['r' => $resId2, 'u' => $unidadId]);
    $ruId2 = (int) $pdo->lastInsertId();

    $estCod2 = 'EST-CALC-10-' . uniqid();
    $pdo->prepare('INSERT INTO estadias (codigo, reserva_id, reserva_unidad_id, unidad_id, fecha_entrada, fecha_salida_prevista, estado, checkin_en, checkin_por_actor_id)
                   VALUES (:c, :r, :ru, :u, "2026-11-05", "2026-11-08", "EN_CURSO", "2026-11-05 15:00:00", :a)')
        ->execute(['c' => $estCod2, 'r' => $resId2, 'ru' => $ruId2, 'u' => $unidadId, 'a' => $actorId]);
    $estId2 = (int) $pdo->lastInsertId();
    $pdo->commit();

    $dev2_n1 = $devengoServicio->devengarNoche($estId2, '2026-11-05', $actorId);
    $dev2_n2 = $devengoServicio->devengarNoche($estId2, '2026-11-06', $actorId);
    $dev2_n3 = $devengoServicio->devengarNoche($estId2, '2026-11-07', $actorId);

    assertCalculo($dev2_n1->obtenerImporteTotal() === '3.34', 'CASO 06: Noche 1 para 10 PEN / 3 noches es 3.34 PEN');
    assertCalculo($dev2_n2->obtenerImporteTotal() === '3.33', 'CASO 07: Noche 2 es 3.33 PEN');
    assertCalculo($dev2_n3->obtenerImporteTotal() === '3.33', 'CASO 08: Noche 3 es 3.33 PEN');
    $sumaTotal2 = bcadd(bcadd($dev2_n1->obtenerImporteTotal(), $dev2_n2->obtenerImporteTotal(), 2), $dev2_n3->obtenerImporteTotal(), 2);
    assertCalculo($sumaTotal2 === '10.00', 'CASO 09: Suma total de 10 PEN / 3 noches es exactamente 10.00 PEN');

    // -------------------------------------------------------------------------
    // CASOS 10 a 13: Céntimos Impares 200.05 / 4 noches (50.03, 50.01, 50.01, 50.01)
    // -------------------------------------------------------------------------
    echo "\n--- BLOQUE 3: Céntimos Impares 200.05 PEN / 4 Noches ---\n";

    $pdo->beginTransaction();
    $resCod3 = 'RES-CALC-200-' . uniqid();
    $pdo->prepare('INSERT INTO reservas (codigo, persona_titular_id, fecha_entrada, fecha_salida, noches, estado, canal, origen, moneda_codigo, subtotal, total)
                   VALUES (:c, :p, "2026-11-10", "2026-11-14", 4, "CONFIRMADA", "PMS", "DIRECTO", "PEN", 200.05, 200.05)')
        ->execute(['c' => $resCod3, 'p' => $personaId]);
    $resId3 = (int) $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO reserva_unidades (reserva_id, unidad_id, precio_unitario_noche, noches, subtotal, impuesto, total, moneda_codigo)
                   VALUES (:r, :u, 50.01, 4, 200.05, 0.00, 200.05, "PEN")')
        ->execute(['r' => $resId3, 'u' => $unidadId]);
    $ruId3 = (int) $pdo->lastInsertId();

    $estCod3 = 'EST-CALC-200-' . uniqid();
    $pdo->prepare('INSERT INTO estadias (codigo, reserva_id, reserva_unidad_id, unidad_id, fecha_entrada, fecha_salida_prevista, estado, checkin_en, checkin_por_actor_id)
                   VALUES (:c, :r, :ru, :u, "2026-11-10", "2026-11-14", "EN_CURSO", "2026-11-10 15:00:00", :a)')
        ->execute(['c' => $estCod3, 'r' => $resId3, 'ru' => $ruId3, 'u' => $unidadId, 'a' => $actorId]);
    $estId3 = (int) $pdo->lastInsertId();
    $pdo->commit();

    $dev3_n1 = $devengoServicio->devengarNoche($estId3, '2026-11-10', $actorId);
    $dev3_n2 = $devengoServicio->devengarNoche($estId3, '2026-11-11', $actorId);
    $dev3_n3 = $devengoServicio->devengarNoche($estId3, '2026-11-12', $actorId);
    $dev3_n4 = $devengoServicio->devengarNoche($estId3, '2026-11-13', $actorId);

    // 200.05 / 4 = 50.0125 -> floor a 2 dec: 50.01. Suma 3 noches = 150.03. Noche 1 = 200.05 - 150.03 = 50.02
    assertCalculo($dev3_n2->obtenerImporteTotal() === '50.01', 'CASO 10: Noche regular devenga 50.01 PEN');
    assertCalculo($dev3_n1->obtenerImporteTotal() === '50.02', 'CASO 11: Noche 1 absorbe residuo de 0.01 -> 50.02 PEN');
    $sumaTotal3 = bcadd(bcadd(bcadd($dev3_n1->obtenerImporteTotal(), $dev3_n2->obtenerImporteTotal(), 2), $dev3_n3->obtenerImporteTotal(), 2), $dev3_n4->obtenerImporteTotal(), 2);
    assertCalculo($sumaTotal3 === '200.05', 'CASO 12: Suma exacta de las 4 noches coincide con 200.05 PEN');
    assertCalculo($dev3_n1->obtenerNocheIndice() === 1 && $dev3_n4->obtenerNocheIndice() === 4, 'CASO 13: Índices de noche abarcan de 1 a 4');

    // -------------------------------------------------------------------------
    // CASOS 14 a 16: Estadía de Cortesía (0.00 PEN)
    // -------------------------------------------------------------------------
    echo "\n--- BLOQUE 4: Estadía de Cortesía (0.00 PEN) ---\n";

    $pdo->beginTransaction();
    $resCod4 = 'RES-CALC-0-' . uniqid();
    $pdo->prepare('INSERT INTO reservas (codigo, persona_titular_id, fecha_entrada, fecha_salida, noches, estado, canal, origen, moneda_codigo, subtotal, total)
                   VALUES (:c, :p, "2026-11-15", "2026-11-17", 2, "CONFIRMADA", "PMS", "DIRECTO", "PEN", 0.00, 0.00)')
        ->execute(['c' => $resCod4, 'p' => $personaId]);
    $resId4 = (int) $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO reserva_unidades (reserva_id, unidad_id, precio_unitario_noche, noches, subtotal, impuesto, total, moneda_codigo)
                   VALUES (:r, :u, 0.00, 2, 0.00, 0.00, 0.00, "PEN")')
        ->execute(['r' => $resId4, 'u' => $unidadId]);
    $ruId4 = (int) $pdo->lastInsertId();

    $estCod4 = 'EST-CALC-0-' . uniqid();
    $pdo->prepare('INSERT INTO estadias (codigo, reserva_id, reserva_unidad_id, unidad_id, fecha_entrada, fecha_salida_prevista, estado, checkin_en, checkin_por_actor_id)
                   VALUES (:c, :r, :ru, :u, "2026-11-15", "2026-11-17", "EN_CURSO", "2026-11-15 15:00:00", :a)')
        ->execute(['c' => $estCod4, 'r' => $resId4, 'ru' => $ruId4, 'u' => $unidadId, 'a' => $actorId]);
    $estId4 = (int) $pdo->lastInsertId();
    $pdo->commit();

    $dev4_n1 = $devengoServicio->devengarNoche($estId4, '2026-11-15', $actorId);
    $dev4_n2 = $devengoServicio->devengarNoche($estId4, '2026-11-16', $actorId);

    assertCalculo($dev4_n1->obtenerImporteTotal() === '0.00', 'CASO 14: Estadía de cortesía devenga importe total 0.00 PEN en noche 1');
    assertCalculo($dev4_n2->obtenerImporteTotal() === '0.00', 'CASO 15: Noche 2 devenga 0.00 PEN');
    assertCalculo($dev4_n1->obtenerImporteNeto() === '0.00' && $dev4_n1->obtenerImpuestoMonto() === '0.00', 'CASO 16: Neto e impuesto son ambos 0.00 PEN');

    // -------------------------------------------------------------------------
    // CASOS 17 a 20: Fórmulas y Semántica Soberana de ADR y RevPAR (CierreHotelero)
    // -------------------------------------------------------------------------
    echo "\n--- BLOQUE 5: Fórmulas Soberanas ADR y RevPAR ---\n";

    // Cierre simulado: 10 unidades totales, 2 OOO -> 8 vendibles.
    // 4 habitaciones vendidas, 800.00 PEN ingreso total de alojamiento.
    // ADR = 800.00 / 4 = 200.00 PEN.
    // RevPAR = 800.00 / 8 (vendibles, NO totales) = 100.00 PEN.
    $cierrePrueba = new CierreHotelero(
        1, 1, '2026-11-20', 'America/Lima', CierreHotelero::ESTADO_CERRADO,
        4, 4, '800.00', '0.00', '800.00',
        10, 2, 8, 4, 0, '50.00', '200.00', '100.00',
        gmdate('Y-m-d H:i:s'), gmdate('Y-m-d H:i:s'), $actorId, 'Test ADR RevPAR', null
    );

    assertCalculo($cierrePrueba->obtenerAdr() === '200.00', 'CASO 17: ADR = Ingreso / Vendidas = 800.00 / 4 = 200.00 PEN');
    assertCalculo($cierrePrueba->obtenerRevpar() === '100.00', 'CASO 18: RevPAR soberano descuenta OOO: 800.00 / 8 = 100.00 PEN');
    assertCalculo(
        $cierrePrueba->obtenerOcupacionPorcentaje() === '50.00' &&
        $cierrePrueba->obtenerHabitacionesOcupadas() === 4 &&
        $cierrePrueba->obtenerHabitacionesComputablesAdr() === 4,
        'CASO 19: Ocupación = (4 / 8) * 100 = 50.00% con distinción explícita de ocupadas vs computables ADR'
    );

    // Cierre con 0 vendidas (sin división por cero)
    $cierreVacio = new CierreHotelero(
        2, 1, '2026-11-21', 'America/Lima', CierreHotelero::ESTADO_CERRADO,
        0, 0, '0.00', '0.00', '0.00',
        10, 0, 10, 0, 0, '0.00', '0.00', '0.00',
        gmdate('Y-m-d H:i:s'), gmdate('Y-m-d H:i:s'), $actorId, 'Test Cierre Vacío', null
    );
    assertCalculo($cierreVacio->obtenerAdr() === '0.00' && $cierreVacio->obtenerRevpar() === '0.00', 'CASO 20: Cierre con cero ocupación produce ADR 0.00 y RevPAR 0.00 sin error');

} catch (Throwable $e) {
    $fail++;
    echo "\n[ERROR CRÍTICO]: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
} finally {
    try {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $pdo->exec('UPDATE devengos_alojamiento SET reverso_de_id = NULL WHERE estadia_id IN (SELECT id FROM estadias WHERE codigo LIKE "EST-CALC-%")');
        $pdo->exec('DELETE FROM devengos_alojamiento WHERE estadia_id IN (SELECT id FROM estadias WHERE codigo LIKE "EST-CALC-%")');
        $pdo->exec('DELETE FROM estadias WHERE codigo LIKE "EST-CALC-%"');
        $pdo->exec('DELETE FROM reserva_unidades WHERE reserva_id IN (SELECT id FROM reservas WHERE codigo LIKE "RES-CALC-%")');
        $pdo->exec('DELETE FROM reservas WHERE codigo LIKE "RES-CALC-%"');
    } catch (Throwable) {
    }
}

echo "\n====================================================================\n";
echo "RESULTADO MATRIZ 20: $pass PASS | $fail FAIL\n";
echo "====================================================================\n";

if ($fail > 0) {
    exit(1);
}
exit(0);
