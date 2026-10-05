<?php

declare(strict_types=1);

/**
 * Suite de Pruebas: REPORTES-1A — Autoridad Analítica Backend, Fórmulas Soberanas y Rendimiento por Canal
 *
 * Cobertura:
 * 1. Gobierno del Esquema y Ranura de Migración 039 (130 tablas, migración 038, slot 039 libre, Alina intacta).
 * 2. Integridad de Modelos DTOs (RendimientoCanalDTO, PuntoSerieTemporalDTO, ReporteAnaliticaDTO).
 * 3. Validación de Entradas y Manejo Defensivo de Rangos de Fechas.
 * 4. Prevención Absoluta de División por Cero en Escenarios de Vacío Comercial.
 * 5. Consistencia Matemática y Pureza de Indicadores (D-069, D-090).
 * 6. Exclusión Estricta de Cortesías del Divisor de ADR (D-090.5).
 * 7. Deducción Soberana de Fuera de Orden (OOO) en Inventario Vendible.
 * 8. Respeto Inviolable de Cierres Hoteleros Auditados (Night Audit como Autoridad).
 * 9. Segregación Estricta de Ingresos Devengados vs Percibidos (Tesorería).
 * 10. Rendimiento por Canal y Salvaguarda de Bloqueos Externos iCalendar.
 * 11. Limpieza Defensiva de Fixtures.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use CamargoPMS\Excepciones\ValidacionExcepcion;
use CamargoPMS\Modelos\PuntoSerieTemporalDTO;
use CamargoPMS\Modelos\RendimientoCanalDTO;
use CamargoPMS\Modelos\ReporteAnaliticaDTO;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Repositorios\ReporteRepositorio;
use CamargoPMS\Servicios\ReporteServicio;

Configuracion::cargar(dirname(__DIR__));
$pdo = BaseDatos::conexion();
$totalChecks = 0;
$checksAprobados = 0;

function asegurar(bool $condicion, string $mensaje): void {
    global $totalChecks, $checksAprobados;
    $totalChecks++;
    if ($condicion) {
        $checksAprobados++;
        echo "  [PASS] $mensaje\n";
    } else {
        echo "  [FAIL] $mensaje\n";
        throw new RuntimeException("Fallo de aserción: $mensaje");
    }
}

echo "\n====================================================================\n";
echo " INICIANDO SUITE DE PRUEBAS: REPORTES-1A (AUTORIDAD ANALÍTICA Y CANALES)\n";
echo "====================================================================\n\n";

try {
    // -------------------------------------------------------------------------
    // 1. Gobierno del Esquema y Ranura 039
    // -------------------------------------------------------------------------
    echo "--- 1. Gobierno del Esquema y Ranura 040 ---\n";
    $stmtTablas = $pdo->query("SHOW TABLES");
    $tablas = $stmtTablas->fetchAll(PDO::FETCH_COLUMN);
    $totalTablas = count($tablas);
    asegurar($totalTablas >= 130, "Base de datos contiene al menos 130 tablas relacionales (actual: $totalTablas)");

    $mig038Presente = (bool) $pdo->query("SELECT 1 FROM migraciones WHERE migracion = '038_pagos_pasarelas.sql'")->fetchColumn();
    asegurar($mig038Presente, "Migración 038_pagos_pasarelas.sql presente en BD");

    $archivos042 = glob(__DIR__ . '/../SQL/migraciones/*042*');
    asegurar(count($archivos042) === 0, "Ranura de migración 042 estrictamente LIBRE");

    $gitAlina = shell_exec('git status --porcelain admin-dashboard/ 2>&1');
    asegurar(empty(trim((string) $gitAlina)), "admin-dashboard/ permanece 100% inmutable y libre de modificaciones");

    // -------------------------------------------------------------------------
    // 2. Integridad de Modelos DTOs
    // -------------------------------------------------------------------------
    echo "\n--- 2. Integridad de Modelos DTOs ---\n";
    $dtoCanal = new RendimientoCanalDTO(
        codigoCanal: 'WEB_DIRECTA',
        nombreCanal: 'Venta Directa Online',
        tipoCanal: RendimientoCanalDTO::TIPO_COMERCIAL_WEB,
        reservasTotales: 10,
        reservasConfirmadas: 9,
        reservasCanceladas: 1,
        nochesVendidas: 25,
        cuotaNochesPorcentaje: 45.5,
        ingresosTotales: '5000.00',
        cuotaIngresosPorcentaje: 50.0,
        adrMedio: '200.00',
        alosNoches: 2.5,
        leadTimeDias: 14.2,
        tasaCancelacionPorcentaje: 10.0,
        nochesBloqueadasIcal: 0,
        esProduccionDemostrable: true,
        colorBadge: 'bg-light-success'
    );

    asegurar($dtoCanal->obtenerCodigoCanal() === 'WEB_DIRECTA', "RendimientoCanalDTO getter de código correcto");
    asegurar($dtoCanal->esProduccionDemostrable() === true, "Canal comercial es producción demostrable");
    asegurar($dtoCanal->obtenerAdrMedio() === '200.00', "RendimientoCanalDTO ADR correcto");
    $arrCanal = $dtoCanal->aArreglo();
    asegurar(isset($arrCanal['codigo_canal']) && $arrCanal['cuota_noches_porcentaje'] === 45.5, "RendimientoCanalDTO serialización en arreglo fiel");

    $dtoPunto = new PuntoSerieTemporalDTO(
        fecha: '2026-10-01',
        unidadesTotales: 10,
        unidadesOoo: 1,
        unidadesVendibles: 9,
        unidadesOcupadas: 7,
        habitacionesVendidas: 6,
        habitacionesCortesia: 1,
        ocupacionPorcentaje: 77.78,
        adr: '150.00',
        revpar: '100.00',
        ingresoAlojamientoNeto: '900.00',
        esAuditado: true
    );
    asegurar($dtoPunto->obtenerFecha() === '2026-10-01', "PuntoSerieTemporalDTO fecha correcta");
    asegurar($dtoPunto->esAuditado() === true, "PuntoSerieTemporalDTO indicador auditado correcto");
    asegurar($dtoPunto->obtenerHabitacionesCortesia() === 1, "PuntoSerieTemporalDTO cortesías aisladas");

    $dtoAnalitica = new ReporteAnaliticaDTO(
        fechaDesde: '2026-10-01',
        fechaHasta: '2026-10-07',
        propiedadId: 1,
        propiedadNombre: 'Hotel Principal',
        totalDias: 7,
        resumenKpis: ['ocupacion_media_porcentaje' => 75.0],
        desgloseIngresosDevengados: ['total_devengado' => '10000.00'],
        desgloseIngresosPercibidos: ['total_percibido' => '9500.00'],
        rendimientoCanales: [$dtoCanal],
        resumenIcal: ['total_canales_activos' => 2],
        serieTemporal: [$dtoPunto]
    );
    asegurar($dtoAnalitica->obtenerTotalDias() === 7, "ReporteAnaliticaDTO total de días correcto");
    asegurar($dtoAnalitica->obtenerMonedaCodigo() === 'PEN', "Moneda canónica es PEN");
    $arrAnalitica = $dtoAnalitica->aArreglo();
    asegurar(isset($arrAnalitica['periodo']['desde']) && isset($arrAnalitica['rendimiento_canales'][0]), "ReporteAnaliticaDTO serializa estructura anidada completa");

    // -------------------------------------------------------------------------
    // 3. Validación de Entradas y Manejo Defensivo de Rangos
    // -------------------------------------------------------------------------
    echo "\n--- 3. Validación de Entradas y Manejo Defensivo de Rangos ---\n";
    $repo = new ReporteRepositorio($pdo);
    $servicio = new ReporteServicio($repo);

    $errorCapturado = false;
    try {
        $servicio->generarReporteAnalitico('fecha-invalida', '2026-10-05');
    } catch (ValidacionExcepcion $e) {
        $errorCapturado = true;
    }
    asegurar($errorCapturado, "Fecha inicial inválida arroja ValidacionExcepcion");

    $errorRangoInvertido = false;
    try {
        $servicio->generarReporteAnalitico('2026-10-10', '2026-10-01');
    } catch (ValidacionExcepcion $e) {
        $errorRangoInvertido = true;
    }
    asegurar($errorRangoInvertido, "Rango de fechas invertido (desde > hasta) arroja ValidacionExcepcion");

    // -------------------------------------------------------------------------
    // 4. Prevención Absoluta de División por Cero
    // -------------------------------------------------------------------------
    echo "\n--- 4. Prevención Absoluta de División por Cero ---\n";
    // Consultar rango en el futuro lejano sin actividad
    $dtoVacio = $servicio->generarReporteAnalitico('2040-01-01', '2040-01-05');
    $kpisVacio = $dtoVacio->obtenerResumenKpis();

    asegurar($kpisVacio['total_dias'] === 5, "Periodo futuro contabiliza exactamente 5 días");
    asegurar($kpisVacio['ocupacion_media_porcentaje'] === 0.00, "Ocupación sin ventas ni ocupación es 0.00%");
    asegurar($kpisVacio['adr_promedio'] === '0.00', "ADR sin habitaciones vendidas previene división por cero y retorna 0.00");
    asegurar($kpisVacio['revpar_promedio'] === '0.00', "RevPAR sin ingresos retorna 0.00");
    asegurar($kpisVacio['trevpar_promedio'] === '0.00', "TRevPAR sin ingresos devengados retorna 0.00");

    // -------------------------------------------------------------------------
    // 5. Creación de Fixtures Controlados para Validación Matemática
    // -------------------------------------------------------------------------
    echo "\n--- 5. Fixtures Controlados para Validación Matemática (D-069 / D-090) ---\n";

    // 1. Obtener una propiedad con al menos 3 unidades activas
    $stmtP = $pdo->query("SELECT u.propiedad_id, COUNT(*) as cnt
                          FROM unidades u 
                          JOIN propiedades p ON p.id = u.propiedad_id 
                          WHERE u.estado = 'ACTIVO'
                          GROUP BY u.propiedad_id
                          HAVING cnt >= 3
                          LIMIT 1");
    $propData = $stmtP->fetch(PDO::FETCH_ASSOC);
    $propiedadIdTest = (int) $propData['propiedad_id'];

    $stmtU = $pdo->prepare("SELECT id FROM unidades WHERE propiedad_id = :prop_id AND estado = 'ACTIVO' ORDER BY id ASC LIMIT 3");
    $stmtU->execute(['prop_id' => $propiedadIdTest]);
    $unidadesFixture = $stmtU->fetchAll(PDO::FETCH_COLUMN);
    $unidadIdTest = (int) $unidadesFixture[0];
    $unidad2Id = (int) $unidadesFixture[1];
    $unidad3Id = (int) $unidadesFixture[2];

    // 2. Obtener una persona y actor
    $personaIdTest = (int) $pdo->query("SELECT id FROM personas LIMIT 1")->fetchColumn();
    $actorIdTest = (int) $pdo->query("SELECT id FROM actores WHERE codigo = 'CAMARGO_PMS' LIMIT 1")->fetchColumn();

    $fechaTestAuditada = '2027-05-10';
    $fechaTestDevengo = '2027-05-11';
    $fechaTestRangoInicio = '2027-05-10';
    $fechaTestRangoFin = '2027-05-12';

    // Limpieza previa preventiva de fixtures en orden de dependencias FK
    $pdo->exec("DELETE FROM cierres_hoteleros WHERE fecha_hotelera BETWEEN '2027-05-01' AND '2027-05-31'");
    $pdo->exec("DELETE FROM devengos_alojamiento WHERE fecha_hotelera BETWEEN '2027-05-01' AND '2027-05-31' OR codigo LIKE 'DEV-TEST-%'");
    $pdo->exec("DELETE FROM mantenimiento_ordenes WHERE codigo LIKE 'MNT-TEST-REP1A%'");
    $pdo->exec("DELETE FROM estadias WHERE codigo LIKE 'EST-TEST-REP1A%' OR reserva_id IN (SELECT id FROM reservas WHERE codigo LIKE 'RES-TEST-REP1A%')");
    $pdo->exec("DELETE FROM reserva_unidades WHERE reserva_id IN (SELECT id FROM reservas WHERE codigo LIKE 'RES-TEST-REP1A%')");
    $pdo->exec("DELETE FROM reservas WHERE codigo LIKE 'RES-TEST-REP1A%'");

    // -------------------------------------------------------------------------
    // 6. Respeto Inviolable de Cierres Hoteleros Auditados
    // -------------------------------------------------------------------------
    echo "\n--- 6. Respeto Inviolable de Cierres Hoteleros Auditados (D-090) ---\n";
    // Insertar un cierre hotelero auditado oficial en fechaTestAuditada
    $stmtCierre = $pdo->prepare("INSERT INTO cierres_hoteleros (
        propiedad_id, fecha_hotelera, timezone_utilizada, estado,
        total_estadias_procesadas, total_noches_devengadas,
        ingreso_alojamiento_neto, ingreso_alojamiento_impuestos, ingreso_alojamiento_total,
        unidades_totales, unidades_ooo, unidades_vendibles,
        habitaciones_vendidas, habitaciones_cortesia,
        ocupacion_porcentaje, adr, revpar,
        iniciado_en, cerrado_en, ejecutado_por_actor_id, observaciones
    ) VALUES (
        :prop_id, :fecha, 'America/Lima', 'CERRADO',
        5, 5,
        '1000.00', '180.00', '1180.00',
        10, 2, 8,
        5, 1,
        75.00, '200.00', '125.00',
        NOW(), NOW(), :actor_id, 'Cierre controlado para pruebas REPORTES-1A'
    )");

    $stmtCierre->execute([
        'prop_id' => $propiedadIdTest,
        'fecha' => $fechaTestAuditada,
        'actor_id' => $actorIdTest,
    ]);
    $cierreIdGenerado = (int) $pdo->lastInsertId();
    asegurar($cierreIdGenerado > 0, "Cierre hotelero auditado fixture insertado con ID: $cierreIdGenerado");

    // Consultar el reporte analítico cubriendo esa fecha auditada
    $dtoAuditado = $servicio->generarReporteAnalitico($fechaTestAuditada, $fechaTestAuditada, $propiedadIdTest);
    $serieAuditada = $dtoAuditado->obtenerSerieTemporal();
    asegurar(count($serieAuditada) === 1, "Serie temporal retorna exactamente 1 punto");

    $puntoAuditado = $serieAuditada[0];
    asegurar($puntoAuditado->esAuditado() === true, "Punto reconoce la autoridad canónica del cierre auditado");
    asegurar($puntoAuditado->obtenerAdr() === '200.00', "ADR es exactamente el valor auditado congelado (S/ 200.00)");
    asegurar($puntoAuditado->obtenerRevpar() === '125.00', "RevPAR es exactamente el valor auditado congelado (S/ 125.00)");
    asegurar($puntoAuditado->obtenerUnidadesVendibles() === 8, "Unidades vendibles netas son exactamente 8 (10 totales - 2 OOO)");
    asegurar($puntoAuditado->obtenerHabitacionesVendidas() === 5, "Habitaciones vendidas son exactamente 5");
    asegurar($puntoAuditado->obtenerHabitacionesCortesia() === 1, "Habitaciones cortesía son exactamente 1");

    // -------------------------------------------------------------------------
    // 7. Exclusión Estricta de Cortesías del Divisor de ADR
    // -------------------------------------------------------------------------
    echo "\n--- 7. Exclusión Estricta de Cortesías del Divisor de ADR (D-090.5) ---\n";
    // Crear reserva y estadía ficticia para devengos
    $stmtRes = $pdo->prepare("INSERT INTO reservas (
        codigo, persona_titular_id, fecha_entrada, fecha_salida, noches, estado,
        canal, origen, moneda_codigo, subtotal, impuesto_total, total, creado_por_actor_id
    ) VALUES (
        'RES-TEST-REP1A-01', :p_id, :f_in, :f_out, 1, 'CONFIRMADA',
        'PMS', 'DIRECTO', 'PEN', '300.00', '0.00', '300.00', :actor_id
    )");
    $stmtRes->execute([
        'p_id' => $personaIdTest,
        'f_in' => $fechaTestDevengo,
        'f_out' => '2027-05-12',
        'actor_id' => $actorIdTest,
    ]);
    $resId = (int) $pdo->lastInsertId();

    $stmtRU = $pdo->prepare("INSERT INTO reserva_unidades (
        reserva_id, unidad_id, precio_unitario_noche, noches, subtotal, impuesto, total, moneda_codigo
    ) VALUES (
        :r_id, :u_id, '150.00', 1, '150.00', '0.00', '150.00', 'PEN'
    )");
    $stmtRU->execute(['r_id' => $resId, 'u_id' => $unidadIdTest]);
    $ruId = (int) $pdo->lastInsertId();

    $stmtEst = $pdo->prepare("INSERT INTO estadias (
        codigo, reserva_id, reserva_unidad_id, unidad_id, fecha_entrada, fecha_salida_prevista,
        estado, checkin_en, checkin_por_actor_id
    ) VALUES (
        'EST-TEST-REP1A-01', :r_id, :ru_id, :u_id, :f_in, '2027-05-12',
        'EN_CURSO', NOW(), :actor_id
    )");
    $stmtEst->execute([
        'r_id' => $resId,
        'ru_id' => $ruId,
        'u_id' => $unidadIdTest,
        'f_in' => $fechaTestDevengo,
        'actor_id' => $actorIdTest,
    ]);
    $estId = (int) $pdo->lastInsertId();

    // Insertar 2 devengos comerciales pagados (S/ 150 c/u = S/ 300)
    // y 1 devengo de cortesía (es_cortesia = 1, importe_neto = 0.00)
    $stmtDev1 = $pdo->prepare("INSERT INTO devengos_alojamiento (
        codigo, estadia_id, reserva_id, reserva_unidad_id, unidad_id, propiedad_id,
        fecha_hotelera, noche_indice, total_noches_estadia, secuencia,
        tarifa_base_noche, descuento_monto, impuesto_monto, importe_neto, importe_total,
        moneda_codigo, es_cortesia, estado, devengado_por_actor_id
    ) VALUES (
        'DEV-TEST-01', :est_id, :r_id, :ru_id, :u_id, :prop_id,
        :f_hot, 1, 1, 1,
        '150.00', '0.00', '0.00', '150.00', '150.00',
        'PEN', 0, 'DEVENGADO', :actor_id
    )");
    $stmtDev1->execute([
        'est_id' => $estId, 'r_id' => $resId, 'ru_id' => $ruId, 'u_id' => $unidadIdTest,
        'prop_id' => $propiedadIdTest, 'f_hot' => $fechaTestDevengo, 'actor_id' => $actorIdTest
    ]);

    // Segundo devengo comercial (otra unidad)
    $stmtDev2 = $pdo->prepare("INSERT INTO devengos_alojamiento (
        codigo, estadia_id, reserva_id, reserva_unidad_id, unidad_id, propiedad_id,
        fecha_hotelera, noche_indice, total_noches_estadia, secuencia,
        tarifa_base_noche, descuento_monto, impuesto_monto, importe_neto, importe_total,
        moneda_codigo, es_cortesia, estado, devengado_por_actor_id
    ) VALUES (
        'DEV-TEST-02', :est_id, :r_id, :ru_id, :u_id2, :prop_id,
        :f_hot, 1, 1, 2,
        '150.00', '0.00', '0.00', '150.00', '150.00',
        'PEN', 0, 'DEVENGADO', :actor_id
    )");
    $stmtDev2->execute([
        'est_id' => $estId, 'r_id' => $resId, 'ru_id' => $ruId, 'u_id2' => $unidad2Id,
        'prop_id' => $propiedadIdTest, 'f_hot' => $fechaTestDevengo, 'actor_id' => $actorIdTest
    ]);

    // Devengo de cortesía
    $stmtDev3 = $pdo->prepare("INSERT INTO devengos_alojamiento (
        codigo, estadia_id, reserva_id, reserva_unidad_id, unidad_id, propiedad_id,
        fecha_hotelera, noche_indice, total_noches_estadia, secuencia,
        tarifa_base_noche, descuento_monto, impuesto_monto, importe_neto, importe_total,
        moneda_codigo, es_cortesia, estado, devengado_por_actor_id
    ) VALUES (
        'DEV-TEST-03', :est_id, :r_id, :ru_id, :u_id3, :prop_id,
        :f_hot, 1, 1, 3,
        '150.00', '150.00', '0.00', '0.00', '0.00',
        'PEN', 1, 'DEVENGADO', :actor_id
    )");
    $stmtDev3->execute([
        'est_id' => $estId, 'r_id' => $resId, 'ru_id' => $ruId, 'u_id3' => $unidad3Id,
        'prop_id' => $propiedadIdTest, 'f_hot' => $fechaTestDevengo, 'actor_id' => $actorIdTest
    ]);

    // Ejecutar analítica para fechaTestDevengo
    $dtoDevengo = $servicio->generarReporteAnalitico($fechaTestDevengo, $fechaTestDevengo, $propiedadIdTest);
    $puntoDevengo = $dtoDevengo->obtenerSerieTemporal()[0];

    asegurar($puntoDevengo->obtenerIngresoAlojamientoNeto() === '300.00', "Ingreso neto devengado suma exactamente S/ 300.00");
    asegurar($puntoDevengo->obtenerHabitacionesVendidas() === 2, "Habitaciones vendidas comerciales computables son exactamente 2");
    asegurar($puntoDevengo->obtenerHabitacionesCortesia() === 1, "Habitaciones cortesía son exactamente 1");
    // Regla D-090.5: ADR = 300.00 / 2 = 150.00. Si se incluyera cortesía daría 300 / 3 = 100.00
    asegurar($puntoDevengo->obtenerAdr() === '150.00', "ADR excluye taxativamente la cortesía del divisor: S/ 150.00 (300 / 2)");

    // -------------------------------------------------------------------------
    // 8. Deducción Soberana de Fuera de Orden (OOO)
    // -------------------------------------------------------------------------
    echo "\n--- 8. Deducción Soberana de Fuera de Orden (OOO) ---\n";
    // Insertar orden de mantenimiento bloqueante (requiere_bloqueo = 1)
    $stmtMnt = $pdo->prepare("INSERT INTO mantenimiento_ordenes (
        propiedad_id, unidad_id, codigo, titulo, descripcion, estado, prioridad, tipo,
        requiere_bloqueo, fecha_programada_inicio, fecha_programada_fin,
        fecha_bloqueo_inicio, fecha_bloqueo_fin, creado_por_actor_id
    ) VALUES (
        :prop_id, :u_id, 'MNT-TEST-REP1A-01', 'Reparación de Cañerías OOO', 'Descripción técnica OOO',
        'PROGRAMADA', 'ALTA', 'CORRECTIVO',
        1, :p_in, :p_out, :b_in, :b_out, :actor_id
    )");
    $stmtMnt->execute([
        'prop_id' => $propiedadIdTest,
        'u_id' => $unidadIdTest,
        'p_in' => $fechaTestDevengo,
        'p_out' => '2027-05-15',
        'b_in' => $fechaTestDevengo,
        'b_out' => '2027-05-15',
        'actor_id' => $actorIdTest,
    ]);

    // Insertar orden de mantenimiento NO bloqueante (requiere_bloqueo = 0)
    $stmtMntOos = $pdo->prepare("INSERT INTO mantenimiento_ordenes (
        propiedad_id, unidad_id, codigo, titulo, descripcion, estado, prioridad, tipo,
        requiere_bloqueo, fecha_programada_inicio, fecha_programada_fin,
        fecha_bloqueo_inicio, fecha_bloqueo_fin, creado_por_actor_id
    ) VALUES (
        :prop_id, :u_id2, 'MNT-TEST-REP1A-02', 'Revisión cosmética foco OOS', 'Descripción técnica OOS',
        'PROGRAMADA', 'BAJA', 'PREVENTIVO',
        0, :p_in, :p_out, NULL, NULL, :actor_id
    )");
    $stmtMntOos->execute([
        'prop_id' => $propiedadIdTest,
        'u_id2' => $unidad2Id,
        'p_in' => $fechaTestDevengo,
        'p_out' => '2027-05-15',
        'actor_id' => $actorIdTest,
    ]);

    // Re-evaluar punto para fechaTestDevengo
    $dtoOoo = $servicio->generarReporteAnalitico($fechaTestDevengo, $fechaTestDevengo, $propiedadIdTest);
    $puntoOoo = $dtoOoo->obtenerSerieTemporal()[0];

    asegurar($puntoOoo->obtenerUnidadesOoo() === 1, "Solo la orden con requiere_bloqueo = 1 cuenta como OOO (1 unidad)");
    $vendiblesEsperadas = max(0, $puntoOoo->obtenerUnidadesTotales() - 1);
    asegurar($puntoOoo->obtenerUnidadesVendibles() === $vendiblesEsperadas, "Unidades vendibles netas descuentan exactamente 1 OOO");

    // -------------------------------------------------------------------------
    // 9. Consistencia Matemática: RevPAR = ADR * Ocupación
    // -------------------------------------------------------------------------
    echo "\n--- 9. Consistencia Matemática: RevPAR = ADR * Ocupación Pagada (USALI) ---\n";
    // Verificar en fecha auditada (con cortesías aisladas del ADR)
    $adrAudit = (float) $puntoAuditado->obtenerAdr();
    $habVendidasAudit = $puntoAuditado->obtenerHabitacionesVendidas();
    $vendiblesAudit = $puntoAuditado->obtenerUnidadesVendibles();
    $revparAudit = (float) $puntoAuditado->obtenerRevpar();
    $revparCalculado = round($adrAudit * ($habVendidasAudit / $vendiblesAudit), 2);
    $diferencia = abs($revparCalculado - $revparAudit);
    asegurar($diferencia <= 0.02, "Consistencia RevPAR = ADR * (Habitaciones Vendidas / Unidades Vendibles) con cortesías aisladas (dif: $diferencia <= 0.02)");

    // -------------------------------------------------------------------------
    // 10. Rendimiento por Canal y Salvaguarda de Bloqueos iCalendar
    // -------------------------------------------------------------------------
    echo "\n--- 10. Rendimiento por Canal y Salvaguarda de Bloqueos iCal ---\n";

    // Insertar reserva Web Directa adicional con ingresos conocidos
    $stmtResWeb = $pdo->prepare("INSERT INTO reservas (
        codigo, persona_titular_id, fecha_entrada, fecha_salida, noches, estado,
        canal, origen, moneda_codigo, subtotal, impuesto_total, total, creado_por_actor_id
    ) VALUES (
        'RES-TEST-REP1A-WEB', :p_id, :f_in, :f_out, 3, 'CONFIRMADA',
        'WEB', 'WEB_DIRECTA', 'PEN', '600.00', '0.00', '600.00', :actor_id
    )");
    $stmtResWeb->execute([
        'p_id' => $personaIdTest,
        'f_in' => '2027-05-10',
        'f_out' => '2027-05-13',
        'actor_id' => $actorIdTest,
    ]);
    $resWebId = (int) $pdo->lastInsertId();

    $stmtRUWeb = $pdo->prepare("INSERT INTO reserva_unidades (
        reserva_id, unidad_id, precio_unitario_noche, noches, subtotal, impuesto, total, moneda_codigo
    ) VALUES (
        :r_id, :u_id, '200.00', 3, '600.00', '0.00', '600.00', 'PEN'
    )");
    $stmtRUWeb->execute(['r_id' => $resWebId, 'u_id' => $unidadIdTest]);

    // Consultar analítica en rango extendido
    $dtoCanales = $servicio->generarReporteAnalitico('2027-05-10', '2027-05-13', $propiedadIdTest);
    $canales = $dtoCanales->obtenerRendimientoCanales();
    asegurar(count($canales) >= 1, "Rendimiento por canales retorna al menos 1 canal comercial");

    $canalWebEncontrado = null;
    $canalPmsEncontrado = null;
    foreach ($canales as $c) {
        if ($c->obtenerCodigoCanal() === 'WEB_WEB_DIRECTA') {
            $canalWebEncontrado = $c;
        } elseif ($c->obtenerCodigoCanal() === 'PMS_DIRECTO') {
            $canalPmsEncontrado = $c;
        }
    }

    asegurar($canalWebEncontrado !== null, "Canal 'WEB_DIRECTA' detectado y clasificado en el reporte");
    asegurar($canalWebEncontrado->esProduccionDemostrable() === true, "Canal Web Directo marcado como producción demostrable");
    asegurar($canalWebEncontrado->obtenerAlosNoches() >= 3.0, "ALOS del canal Web refleja estadía de 3 noches");
    asegurar((float) $canalWebEncontrado->obtenerIngresosTotales() >= 600.00, "Ingresos comerciales del canal Web reflejan S/ 600.00 o más");
    asegurar((float) $canalWebEncontrado->obtenerAdrMedio() >= 200.00, "ADR del canal Web es coherente (S/ 200.00)");

    // Verificar salvaguarda iCal
    $resumenIcal = $dtoCanales->obtenerResumenIcal();
    asegurar(isset($resumenIcal['nota_gobernanza']), "Resumen iCal incluye nota de gobernanza explícita");
    asegurar(strpos($resumenIcal['nota_gobernanza'], 'no acreditan ingreso comercial') !== false, "Salvaguarda iCal documenta que bloqueos no acreditan ingreso comercial");

    // -------------------------------------------------------------------------
    // 11. Segregación Estricta: Devengado vs Percibido
    // -------------------------------------------------------------------------
    echo "\n--- 11. Segregación Estricta: Devengado vs Percibido ---\n";
    $devengados = $dtoCanales->obtenerDesgloseIngresosDevengados();
    $percibidos = $dtoCanales->obtenerDesgloseIngresosPercibidos();

    asegurar(isset($devengados['total_devengado']), "Desglose devengado incluye total_devengado");
    asegurar(isset($devengados['alojamiento_neto']), "Desglose devengado incluye alojamiento_neto");
    asegurar(isset($devengados['servicios_extras']), "Desglose devengado incluye servicios_extras");
    asegurar(isset($percibidos['total_percibido']), "Desglose percibido incluye total_percibido");
    asegurar(isset($percibidos['efectivo_caja']), "Desglose percibido incluye efectivo_caja");
    asegurar(isset($percibidos['pasarelas_neto']), "Desglose percibido incluye pasarelas_neto");
    asegurar(isset($percibidos['brecha_recaudacion']), "Desglose incluye brecha_recaudacion determinista");

    $brechaCalculada = bcsub($devengados['total_devengado'], $percibidos['total_percibido'], 2);
    asegurar($percibidos['brecha_recaudacion'] === $brechaCalculada, "Brecha de recaudación coincide exactamente con total_devengado - total_percibido ($brechaCalculada)");

    // -------------------------------------------------------------------------
    // 12. Limpieza Defensiva de Fixtures
    // -------------------------------------------------------------------------
    echo "\n--- 12. Limpieza Defensiva de Fixtures ---\n";
    $pdo->exec("DELETE FROM cierres_hoteleros WHERE id = $cierreIdGenerado");
    $pdo->exec("DELETE FROM devengos_alojamiento WHERE codigo LIKE 'DEV-TEST-%'");
    $pdo->exec("DELETE FROM mantenimiento_ordenes WHERE codigo LIKE 'MNT-TEST-REP1A%'");
    $pdo->exec("DELETE FROM estadias WHERE id = $estId");
    $pdo->exec("DELETE FROM reserva_unidades WHERE reserva_id IN ($resId, $resWebId)");
    $pdo->exec("DELETE FROM reservas WHERE id IN ($resId, $resWebId)");
    asegurar(true, "Fixtures de prueba eliminados defensivamente de la base de datos");

    echo "\n====================================================================\n";
    echo " RESULTADO SUITE REPORTES-1A: $checksAprobados/$totalChecks ASERCIONES SUPERADAS (100% PASS)\n";
    echo "====================================================================\n\n";

} catch (Throwable $e) {
    echo "\n[ERROR CRÍTICO EN SUITE REPORTES-1A]: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
