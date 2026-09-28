<?php
/**
 * Suite de Pruebas E2E (End-to-End) — HOUSEKEEPING-1 (D-083)
 *
 * Flujo Operacional Integral:
 * Ciclo de vida completo desde habitación sucia -> bloqueo de check-in ->
 * limpieza -> consumo amenities en Kardex -> rechazo en inspección ->
 * reproceso de retoque -> aprobación -> condición VR derivada -> check-in ->
 * check-out atómico (SUCIA + tarea SALIDA) -> circuito textil de lavandería con discrepancia.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Repositorios\HousekeepingRepositorio;
use CamargoPMS\Servicios\HousekeepingServicio;
use CamargoPMS\Servicios\AuditoriaServicio;
use CamargoPMS\Servicios\EstadiaServicio;
use CamargoPMS\Repositorios\EstadiaRepositorio;
use CamargoPMS\Repositorios\ReservaRepositorio;
use CamargoPMS\Repositorios\UnidadRepositorio;
use CamargoPMS\Repositorios\CuentaFolioRepositorio;
use CamargoPMS\Modelos\HousekeepingEstadoLimpieza;
use CamargoPMS\Modelos\HousekeepingTarea;
use CamargoPMS\Modelos\HousekeepingChecklistItem;
use CamargoPMS\Modelos\HousekeepingLoteLavanderia;
use CamargoPMS\Excepciones\UnidadNoListaExcepcion;
use CamargoPMS\Excepciones\ValidacionHousekeepingExcepcion;
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
echo " CAMARGO PMS — PRUEBAS END-TO-END (E2E) HOUSEKEEPING-1 (14 CASOS)\n";
echo " Flujo Operacional Real de Pisos, Limpieza, Inspección y Kardex\n";
echo " Decisión Vinculante: D-083\n";
echo "====================================================================\n\n";

$hkRepo = new HousekeepingRepositorio($pdo);
$auditoriaServicio = new AuditoriaServicio($pdo);
$hkServicio = new HousekeepingServicio($hkRepo, $auditoriaServicio, null, null, $pdo);

$estadiaRepo = new EstadiaRepositorio($pdo);
$reservaRepo = new ReservaRepositorio($pdo);
$unidadRepo = new UnidadRepositorio($pdo);
$folioRepo = new CuentaFolioRepositorio($pdo);

$estadiaServicio = new EstadiaServicio(
    $pdo,
    null,
    null,
    null,
    null,
    $auditoriaServicio,
    $hkServicio
);

// 1. SETUP DE DATOS OPERACIONALES
$stmtProp = $pdo->query('SELECT id FROM propiedades LIMIT 1');
$propiedadId = (int) $stmtProp->fetchColumn();
$stmtTu = $pdo->query('SELECT id FROM tipos_unidad LIMIT 1');
$tipoUnidadId = (int) $stmtTu->fetchColumn();

// Crear unidad de suite E2E
$pdo->prepare('INSERT INTO unidades (propiedad_id, tipo_unidad_id, codigo, nombre, piso_nivel, capacidad_personas, estado, creado_en) VALUES (?, ?, "HK-E2E-201", "Suite Presidencial E2E", "2", 2, "ACTIVO", NOW()) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)')->execute([$propiedadId, $tipoUnidadId]);
$unidadE2eId = (int) $pdo->lastInsertId();
$pdo->prepare("UPDATE mantenimiento_ordenes SET estado = 'COMPLETADA' WHERE unidad_id = ? AND estado IN ('PROGRAMADA', 'EN_PROCESO')")->execute([$unidadE2eId]);


// Crear Office y Lavandería para E2E
$pdo->prepare('INSERT INTO inventario_ubicaciones (propiedad_id, codigo, nombre, tipo, estado, creado_en) VALUES (?, "ALM-E2E-OFFICE", "Office Piso 2 E2E", "ALMACEN", "ACTIVO", NOW()) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)')->execute([$propiedadId]);
$almacenOfficeId = (int) $pdo->lastInsertId();

$pdo->prepare('INSERT INTO inventario_ubicaciones (propiedad_id, codigo, nombre, tipo, estado, creado_en) VALUES (?, "ALM-E2E-LAV", "Lavandería Express E2E", "CUSTODIA_EXTERNA", "ACTIVO", NOW()) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)')->execute([$propiedadId]);
$almacenLavId = (int) $pdo->lastInsertId();

$stmtUm = $pdo->query('SELECT id FROM inventario_unidades_medida LIMIT 1');
$umId = (int) $stmtUm->fetchColumn();

// Amenities (Kit Dental)
$pdo->prepare('INSERT INTO inventario_articulos (codigo_sku, nombre, categoria, unidad_medida_id, estado, creado_en) VALUES ("AMN-DEN-01", "Kit Dental Premium", "CONSUMIBLE_OPERATIVO", ?, "ACTIVO", NOW()) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)')->execute([$umId]);
$artDentalId = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO inventario_existencias (articulo_id, ubicacion_id, cantidad_actual, actualizado_en) VALUES (?, ?, 20.0000, NOW()) ON DUPLICATE KEY UPDATE cantidad_actual = 20.0000')->execute([$artDentalId, $almacenOfficeId]);

// Lencería (Toallas Baño)
$pdo->prepare('INSERT INTO inventario_articulos (codigo_sku, nombre, categoria, unidad_medida_id, estado, creado_en) VALUES ("TEX-TOA-01", "Toalla de Baño Felpa", "LENCERIA_BLANCOS", ?, "ACTIVO", NOW()) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)')->execute([$umId]);
$artToallaId = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO inventario_existencias (articulo_id, ubicacion_id, cantidad_actual, actualizado_en) VALUES (?, ?, 30.0000, NOW()) ON DUPLICATE KEY UPDATE cantidad_actual = 30.0000')->execute([$artToallaId, $almacenOfficeId]);
$pdo->prepare('INSERT INTO inventario_existencias (articulo_id, ubicacion_id, cantidad_actual, actualizado_en) VALUES (?, ?, 0.0000, NOW()) ON DUPLICATE KEY UPDATE cantidad_actual = 0.0000')->execute([$artToallaId, $almacenLavId]);

// Camarera y Supervisor
$pdo->prepare('INSERT INTO colaboradores (persona_id, codigo, estado, creado_en) VALUES (1, "COL-E2E-CAM", "ACTIVO", NOW()) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)')->execute();
$colabCamareraId = (int) $pdo->lastInsertId();

$actorId = 1;

// -------------------------------------------------------------------------
// E2E-01: Estado inicial SUCIA en registro 1:1
// -------------------------------------------------------------------------
$hkRepo->asegurarRegistroLimpieza($unidadE2eId, HousekeepingEstadoLimpieza::ESTADO_SUCIA);
$hkRepo->actualizarEstadoLimpieza($unidadE2eId, HousekeepingEstadoLimpieza::ESTADO_SUCIA);

$limpInicial = $hkRepo->obtenerLimpiezaUnidad($unidadE2eId);
afirmar(
    $limpInicial['estado_limpieza'] === HousekeepingEstadoLimpieza::ESTADO_SUCIA,
    'E2E-01: Unidad física HK-E2E-201 inicializada en estado SUCIA'
);

// -------------------------------------------------------------------------
// E2E-02: Intento de Check-in en habitación SUCIA es bloqueado (409)
// -------------------------------------------------------------------------
// Crear reserva para checkin
$codRes = 'RES-E2E-' . bin2hex(random_bytes(4));
$pdo->prepare('INSERT INTO reservas (codigo, persona_titular_id, fecha_entrada, fecha_salida, noches, estado, canal, origen, total, creado_en) VALUES (?, 1, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 DAY), 1, "CONFIRMADA", "PMS", "DIRECTO", 200.00, NOW())')->execute([$codRes]);
$reservaId = (int) $pdo->lastInsertId();

$pdo->prepare('INSERT INTO reserva_unidades (reserva_id, unidad_id, precio_unitario_noche, noches, subtotal, total, moneda_codigo, creado_en) VALUES (?, ?, 200.00, 1, 200.00, 200.00, "PEN", NOW())')->execute([$reservaId, $unidadE2eId]);
$reservaUnidadId = (int) $pdo->lastInsertId();

$datosCheckin = [
    'reserva_id' => $reservaId,
    'reserva_unidad_id' => $reservaUnidadId,
    'huespedes' => [
        ['persona_id' => 1, 'es_responsable' => true],
    ],
];

$bloqueadoCheckinSucia = false;
try {
    $estadiaServicio->realizarCheckin($datosCheckin, $actorId);
} catch (UnidadNoListaExcepcion $e) {
    $bloqueadoCheckinSucia = true;
}

afirmar(
    $bloqueadoCheckinSucia === true,
    'E2E-02: Check-in estrictamente bloqueado con UnidadNoListaExcepcion (409) debido a estado SUCIA'
);

// -------------------------------------------------------------------------
// E2E-03: Creación de Tarea de Limpieza y Asignación a Camarera
// -------------------------------------------------------------------------
$tarea = $hkServicio->crearTareaManual([
    'unidad_id' => $unidadE2eId,
    'tipo' => HousekeepingTarea::TIPO_SALIDA,
    'prioridad' => HousekeepingTarea::PRIORIDAD_ALTA,
    'notas' => 'Limpieza integral para huésped VIP',
], $actorId);

$tareaAsignada = $hkServicio->asignarTarea($tarea->obtenerId(), $colabCamareraId, null, $actorId);
afirmar(
    $tareaAsignada->obtenerEstado() === HousekeepingTarea::ESTADO_ASIGNADA && $tareaAsignada->obtenerCamareraColaboradorId() === $colabCamareraId,
    'E2E-03: Tarea de salida creada y asignada formalmente a camarera de pisos'
);

// -------------------------------------------------------------------------
// E2E-04: Inicio de Limpieza Física (EN_LIMPIEZA)
// -------------------------------------------------------------------------
$tareaIniciada = $hkServicio->iniciarLimpieza($tarea->obtenerId(), $actorId);
$limpEnProc = $hkRepo->obtenerLimpiezaUnidad($unidadE2eId);
afirmar(
    $tareaIniciada->obtenerEstado() === HousekeepingTarea::ESTADO_EN_PROCESO && $limpEnProc['estado_limpieza'] === HousekeepingEstadoLimpieza::ESTADO_EN_LIMPIEZA,
    'E2E-04: Labores iniciadas: tarea EN_PROCESO y unidad 1:1 en estado EN_LIMPIEZA'
);

// -------------------------------------------------------------------------
// E2E-05: Verificación en Rack Operacional (Condición Derivada)
// -------------------------------------------------------------------------
$rack = $hkServicio->obtenerRackOperacional($propiedadId);
$dtoUnidad = null;
foreach ($rack as $item) {
    if ($item->obtenerUnidadId() === $unidadE2eId) {
        $dtoUnidad = $item;
        break;
    }
}

afirmar(
    $dtoUnidad !== null && $dtoUnidad->obtenerCondicionDerivada() === 'VD' && $dtoUnidad->esAptaParaCheckin() === false,
    'E2E-05: Rack operacional proyecta dinámicamente estado operacional VD sin estar apta para check-in'
);

// -------------------------------------------------------------------------
// E2E-06: Reporte de Desperfecto de Mantenimiento desde Housekeeping
// -------------------------------------------------------------------------
$ordenMantenimiento = $hkServicio->reportarDesperfectoMantenimiento(
    $tarea->obtenerId(),
    [
        'titulo' => 'Fuga de agua detectada',
        'descripcion' => 'Grifo de ducha gotea en baño principal',
        'prioridad' => 'MEDIA',
        'afecta_disponibilidad' => false,
    ],
    $actorId
);

afirmar(
    $ordenMantenimiento !== null && (int)$ordenMantenimiento['unidad_id'] === $unidadE2eId,
    'E2E-06: Desperfecto reportado desde limpieza genera orden en mantenimiento_ordenes sin duplicar dominio'
);

// -------------------------------------------------------------------------
// E2E-07: Finalización de Limpieza con Reposición de Amenities (Kardex)
// -------------------------------------------------------------------------
$stockDentalAntes = (float) $pdo->query("SELECT cantidad_actual FROM inventario_existencias WHERE articulo_id = {$artDentalId} AND ubicacion_id = {$almacenOfficeId}")->fetchColumn();

$tareaFinalizada = $hkServicio->finalizarLimpieza($tarea->obtenerId(), [
    'notas_operario' => 'Habitación higienizada, aromatizada y provista de kits dentales',
    'consumos' => [
        ['articulo_id' => $artDentalId, 'almacen_origen_id' => $almacenOfficeId, 'cantidad' => '2.0000'],
    ],
], $actorId);

$stockDentalDespues = (float) $pdo->query("SELECT cantidad_actual FROM inventario_existencias WHERE articulo_id = {$artDentalId} AND ubicacion_id = {$almacenOfficeId}")->fetchColumn();

afirmar(
    $tareaFinalizada->obtenerEstado() === HousekeepingTarea::ESTADO_POR_INSPECCIONAR && ($stockDentalAntes - $stockDentalDespues) === 2.0,
    'E2E-07: Limpieza finalizada, tarea pasa a POR_INSPECCIONAR y Kardex debita exactamente 2 amenities'
);

// -------------------------------------------------------------------------
// E2E-08: Unidad en LIMPIA_POR_INSPECCIONAR (VCL) — Check-in sigue bloqueado
// -------------------------------------------------------------------------
$limpVcl = $hkRepo->obtenerLimpiezaUnidad($unidadE2eId);
$puedeCheckinVcl = $hkServicio->puedeCheckIn($unidadE2eId);

afirmar(
    $limpVcl['estado_limpieza'] === HousekeepingEstadoLimpieza::ESTADO_LIMPIA_POR_INSPECCIONAR && $puedeCheckinVcl === false,
    'E2E-08: Habitación en estado VCL (Limpia por Inspeccionar) continúa bloqueando el Check-in'
);

// -------------------------------------------------------------------------
// E2E-09: Inspección Rechaza Punto Crítico -> Reproceso a RETOQUE_REQUERIDO
// -------------------------------------------------------------------------
$chkItems = $hkRepo->obtenerChecklistTarea($tarea->obtenerId());
$itemsRechazo = [];
$criticoEncontrado = false;
foreach ($chkItems as $item) {
    if (!empty($item['es_critico_snapshot']) && !$criticoEncontrado) {
        $criticoEncontrado = true;
        $itemsRechazo[] = ['id' => $item['id'], 'resultado' => 'NO_CONFORME', 'observacion' => 'Toalla presenta mancha'];
    } else {
        $itemsRechazo[] = ['id' => $item['id'], 'resultado' => 'CONFORME'];
    }
}

$tareaRechazada = $hkServicio->inspeccionarTarea($tarea->obtenerId(), false, $itemsRechazo, 'Rechazo: cambiar toallas', $actorId);
$limpRetoque = $hkRepo->obtenerLimpiezaUnidad($unidadE2eId);

afirmar(
    $tareaRechazada->obtenerEstado() === HousekeepingTarea::ESTADO_RECHAZADA && $limpRetoque['estado_limpieza'] === HousekeepingEstadoLimpieza::ESTADO_RETOQUE_REQUERIDO,
    'E2E-09: Inspección rechazada por punto crítico: tarea en RECHAZADA y unidad en RETOQUE_REQUERIDO'
);

// -------------------------------------------------------------------------
// E2E-10: Ejecución de Retoque y Aprobación 100% Conforme (Habilitación VR)
// -------------------------------------------------------------------------
$hkServicio->iniciarLimpieza($tarea->obtenerId(), $actorId);
$hkServicio->finalizarLimpieza($tarea->obtenerId(), ['notas_operario' => 'Toallas cambiadas y verificadas'], $actorId);

$itemsAprobados = [];
foreach ($chkItems as $item) {
    $itemsAprobados[] = ['id' => $item['id'], 'resultado' => 'CONFORME', 'observacion' => 'Punto verificado y óptimo'];
}

$tareaAprobada = $hkServicio->inspeccionarTarea($tarea->obtenerId(), true, $itemsAprobados, 'Aprobación formal 100%', $actorId);
$limpAprobada = $hkRepo->obtenerLimpiezaUnidad($unidadE2eId);
$aptaVr = $hkServicio->puedeCheckIn($unidadE2eId);

afirmar(
    $tareaAprobada->obtenerEstado() === HousekeepingTarea::ESTADO_COMPLETADA &&
    $limpAprobada['estado_limpieza'] === HousekeepingEstadoLimpieza::ESTADO_LIMPIA_INSPECCIONADA &&
    $aptaVr === true,
    'E2E-10: Retoque aprobado: tarea COMPLETADA, unidad LIMPIA_INSPECCIONADA y condición derivada VR (Apta Check-in)'
);

// -------------------------------------------------------------------------
// E2E-11: Check-in Exitoso de la Estadía
// -------------------------------------------------------------------------
$estadia = $estadiaServicio->realizarCheckin($datosCheckin, $actorId);
$puedeCheckinOcupada = $hkServicio->puedeCheckIn($unidadE2eId);

afirmar(
    $estadia->obtenerEstado() === 'EN_CURSO' && $puedeCheckinOcupada === false,
    'E2E-11: Check-in ejecutado exitosamente; unidad ahora ocupada ya no admite nuevo check-in simultáneo'
);

// -------------------------------------------------------------------------
// E2E-12: Check-out Atómico: Estadía FINALIZADA + SUCIA + Tarea SALIDA
// -------------------------------------------------------------------------
$estadiaFinalizada = $estadiaServicio->realizarCheckout($estadia->obtenerId(), 'Checkout normal de huésped', $actorId);
$limpPostCheckout = $hkRepo->obtenerLimpiezaUnidad($unidadE2eId);
$tareaActiva = $hkRepo->obtenerTareaPorId((int)$limpPostCheckout['tarea_activa_id']);

afirmar(
    $estadiaFinalizada->obtenerEstado() === 'FINALIZADA' &&
    $limpPostCheckout['estado_limpieza'] === HousekeepingEstadoLimpieza::ESTADO_SUCIA &&
    $tareaActiva !== null && $tareaActiva['tipo_tarea'] === HousekeepingTarea::TIPO_SALIDA,
    'E2E-12: Check-out atómico: estadía FINALIZADA, habitación marcada SUCIA y generada tarea SALIDA correlativa'
);

// -------------------------------------------------------------------------
// E2E-13: Circuito Textil: Despacho a Lavandería Externa (Custodia Externa)
// -------------------------------------------------------------------------
$loteLav = $hkServicio->despacharLoteLavanderia([
    'propiedad_id' => $propiedadId,
    'almacen_origen_id' => $almacenOfficeId,
    'ubicacion_lavanderia_id' => $almacenLavId,
    'lineas' => [
        ['articulo_id' => $artToallaId, 'cantidad_enviada' => '10.0000'],
    ],
], $actorId);

$stockOfficeToalla = (float) $pdo->query("SELECT cantidad_actual FROM inventario_existencias WHERE articulo_id = {$artToallaId} AND ubicacion_id = {$almacenOfficeId}")->fetchColumn();
$stockLavToalla = (float) $pdo->query("SELECT cantidad_actual FROM inventario_existencias WHERE articulo_id = {$artToallaId} AND ubicacion_id = {$almacenLavId}")->fetchColumn();



afirmar(
    $loteLav->obtenerEstado() === HousekeepingLoteLavanderia::ESTADO_DESPACHADO &&
    $stockOfficeToalla === 20.0 && $stockLavToalla === 10.0,
    'E2E-13: Lote textil despachado con doble pata en Kardex (Office -> Custodia Externa Lavandería)'
);

// -------------------------------------------------------------------------
// E2E-14: Retorno Textil con Discrepancia y Registro de Merma (D-083)
// -------------------------------------------------------------------------
// Enviadas: 10. Recibidas: 8 limpias + 1 dañada (merma). Discrepancia: 1 perdida.
$lineasLote = $hkRepo->obtenerLineasLote($loteLav->obtenerId());
$lineaId = (int) $lineasLote[0]['id'];

$loteRetornado = $hkServicio->recibirLoteLavanderia(
    $loteLav->obtenerId(),
    [
        ['linea_id' => $lineaId, 'cantidad_recibida' => '8.0000', 'cantidad_merma' => '1.0000', 'observaciones' => '1 toalla extraviada'],
    ],
    'Recepción con faltante de 1 toalla',
    $actorId
);

$lineasFinales = $hkRepo->obtenerLineasLote($loteLav->obtenerId());
$difCalculada = (float) $lineasFinales[0]['cantidad_diferencia'];

afirmar(
    $loteRetornado->obtenerEstado() === HousekeepingLoteLavanderia::ESTADO_CON_DISCREPANCIA &&
    $difCalculada === 1.0,
    'E2E-14: Retorno textil procesa merma y diferencia calculada en columna virtual; lote CON_DISCREPANCIA'
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
    echo " RESULTADO: SUITE E2E HOUSEKEEPING-1 14/14 PASS EXITOSA\n";
    echo "====================================================================\n";
    exit(0);
}
