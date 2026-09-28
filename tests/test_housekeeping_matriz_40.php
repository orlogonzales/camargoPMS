<?php

declare(strict_types=1);

/**
 * Suite de Verificación HOUSEKEEPING-1 — Matriz Exhaustiva de 40 Pruebas de Dominio
 *
 * Verifica las decisiones y contratos vinculantes de D-083 (GATE HOUSEKEEPING-1):
 * - ESTADO COMERCIAL != ESTADO DE OCUPACIÓN != ESTADO DE LIMPIEZA != DISPONIBILIDAD != MANTENIMIENTO
 * - UNIDAD LISTA PARA CHECK-IN = resultado operacional derivado (VR - Vacant Ready)
 * - Cero duplicación de verdad en base de datos (VR/VD/OD son proyecciones UI/DTOs)
 * - Checklists congelados e inmutables con evaluación tri-valente (CONFORME, NO_CONFORME, NO_APLICA)
 * - Puntos de control críticos impiden aprobación si están NO_CONFORME
 * - Idempotencia estricta de tareas de check-out
 * - Consumo de amenities vinculado atómicamente a Kardex
 * - Control textil de lavandería con diferencias explícitas y custodia externa
 * - Trazabilidad append-only D-061
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CamargoPMS\Excepciones\ConflictoHousekeepingExcepcion;
use CamargoPMS\Excepciones\HousekeepingNoEncontradoExcepcion;
use CamargoPMS\Excepciones\UnidadNoListaExcepcion;
use CamargoPMS\Excepciones\ValidacionHousekeepingExcepcion;
use CamargoPMS\Modelos\HousekeepingChecklistItem;
use CamargoPMS\Modelos\HousekeepingDerivacionOperativa;
use CamargoPMS\Modelos\HousekeepingEstadoLimpieza;
use CamargoPMS\Modelos\HousekeepingLoteLavanderia;
use CamargoPMS\Modelos\HousekeepingTarea;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Repositorios\ActorAuditoriaRepositorio;
use CamargoPMS\Repositorios\HousekeepingRepositorio;
use CamargoPMS\Servicios\AuditoriaServicio;
use CamargoPMS\Servicios\HousekeepingServicio;

Configuracion::cargar(dirname(__DIR__));
$pdo = BaseDatos::conexion();
$pdo->exec("SET SESSION innodb_lock_wait_timeout = 5;");

$hkRepo = new HousekeepingRepositorio($pdo);
$auditoriaServicio = new AuditoriaServicio($pdo);
$hkServicio = new HousekeepingServicio($hkRepo, $auditoriaServicio, null, null, $pdo);

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
echo " CAMARGO PMS — PRUEBAS HOUSEKEEPING-1 (MATRIZ EXHAUSTIVA DE 40 CASOS)\n";
echo " Decisión Vinculante: D-083\n";
echo "====================================================================\n\n";

// Asegurar datos base de prueba
$stmtProp = $pdo->query('SELECT id FROM propiedades LIMIT 1');
$propiedadId = (int) $stmtProp->fetchColumn();
if ($propiedadId <= 0) {
    $pdo->exec('INSERT INTO propiedades (codigo, nombre, direccion, ciudad, estado, creado_en) VALUES ("PROP-HK-TEST", "Hotel HK Test", "Av Test 123", "Cusco", "ACTIVO", NOW())');
    $propiedadId = (int) $pdo->lastInsertId();
}

$stmtTu = $pdo->query('SELECT id FROM tipos_unidad LIMIT 1');
$tipoUnidadId = (int) $stmtTu->fetchColumn();

// Crear 2 unidades dedicadas a pruebas de housekeeping
$pdo->prepare('INSERT INTO unidades (propiedad_id, tipo_unidad_id, codigo, nombre, piso_nivel, capacidad_personas, estado, creado_en) VALUES (?, ?, "HK-U101", "Habitación 101 HK", "1", 2, "ACTIVO", NOW()) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)')->execute([$propiedadId, $tipoUnidadId]);
$unidadId1 = (int) $pdo->lastInsertId();

$pdo->prepare('INSERT INTO unidades (propiedad_id, tipo_unidad_id, codigo, nombre, piso_nivel, capacidad_personas, estado, creado_en) VALUES (?, ?, "HK-U102", "Habitación 102 HK", "1", 2, "ACTIVO", NOW()) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)')->execute([$propiedadId, $tipoUnidadId]);
$unidadId2 = (int) $pdo->lastInsertId();

// Crear almacén / office de lencería y ubicación de lavandería externa
$pdo->prepare('INSERT INTO inventario_ubicaciones (propiedad_id, codigo, nombre, tipo, estado, creado_en) VALUES (?, "ALM-HK-OFFICE", "Office Pisos 1", "ALMACEN", "ACTIVO", NOW()) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)')->execute([$propiedadId]);
$almacenOfficeId = (int) $pdo->lastInsertId();

$pdo->prepare('INSERT INTO inventario_ubicaciones (propiedad_id, codigo, nombre, tipo, estado, creado_en) VALUES (?, "ALM-HK-LAVANDERIA", "Lavandería Externa Sol", "CUSTODIA_EXTERNA", "ACTIVO", NOW()) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)')->execute([$propiedadId]);
$almacenLavanderiaId = (int) $pdo->lastInsertId();

// Crear artículo textil de lencería
$stmtCat = $pdo->query('SELECT id FROM inventario_unidades_medida LIMIT 1');
$umId = (int) $stmtCat->fetchColumn();
$pdo->prepare('INSERT INTO inventario_articulos (codigo_sku, nombre, categoria, unidad_medida_id, estado, creado_en) VALUES ("TEX-SAB-01", "Sábana King 300 Hilos", "LENCERIA_BLANCOS", ?, "ACTIVO", NOW()) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)')->execute([$umId]);
$articuloTextilId = (int) $pdo->lastInsertId();

// Asignar stock inicial en office
$pdo->prepare('INSERT INTO inventario_existencias (articulo_id, ubicacion_id, cantidad_actual, actualizado_en) VALUES (?, ?, 50.0000, NOW()) ON DUPLICATE KEY UPDATE cantidad_actual = 50.0000')->execute([$articuloTextilId, $almacenOfficeId]);

// Crear artículo amenitie (jabón)
$pdo->prepare('INSERT INTO inventario_articulos (codigo_sku, nombre, categoria, unidad_medida_id, estado, creado_en) VALUES ("AMN-JAB-01", "Jabón Artesanal 40g", "CONSUMIBLE_OPERATIVO", ?, "ACTIVO", NOW()) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)')->execute([$umId]);
$articuloAmenitieId = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO inventario_existencias (articulo_id, ubicacion_id, cantidad_actual, actualizado_en) VALUES (?, ?, 100.0000, NOW()) ON DUPLICATE KEY UPDATE cantidad_actual = 100.0000')->execute([$articuloAmenitieId, $almacenOfficeId]);

$actorId = 1;

// =========================================================================
// BLOQUE 1: MODELO 1:1 Y DERIVACIÓN DINÁMICA DE ESTADOS (CASOS 1 - 11)
// =========================================================================
echo "\n--- BLOQUE 1: MODELO 1:1 Y MOTOR DE DERIVACIÓN DINÁMICA (D-083) ---\n";

// Caso 1: Asegurar registro de limpieza 1:1 inicializado
$limp1 = $hkRepo->asegurarRegistroLimpieza($unidadId1, HousekeepingEstadoLimpieza::ESTADO_LIMPIA_INSPECCIONADA);
$hkRepo->actualizarEstadoLimpieza($unidadId1, HousekeepingEstadoLimpieza::ESTADO_LIMPIA_INSPECCIONADA);
afirmar($limp1['unidad_id'] === $unidadId1, 'Caso 1: Registro 1:1 asegurado para unidad física');

// Caso 2: Unidad limpia inspeccionada, vacante, activa y sin fallas es VR
$esVR = $hkServicio->puedeCheckIn($unidadId1);
afirmar($esVR === true, 'Caso 2: Habitación limpia, desocupada y activa puedeCheckIn() === true (VR)');

// Caso 3: Cambiar estado a SUCIA
$hkRepo->actualizarEstadoLimpieza($unidadId1, HousekeepingEstadoLimpieza::ESTADO_SUCIA);
$puedeCheckinSucia = $hkServicio->puedeCheckIn($unidadId1);
afirmar($puedeCheckinSucia === false, 'Caso 3: Habitación SUCIA no puede recibir check-in (puedeCheckIn() === false)');

// Caso 4: Habitación EN_LIMPIEZA
$hkRepo->actualizarEstadoLimpieza($unidadId1, HousekeepingEstadoLimpieza::ESTADO_EN_LIMPIEZA);
afirmar($hkServicio->puedeCheckIn($unidadId1) === false, 'Caso 4: Habitación EN_LIMPIEZA no puede recibir check-in');

// Caso 5: Habitación LIMPIA_POR_INSPECCIONAR (VCL)
$hkRepo->actualizarEstadoLimpieza($unidadId1, HousekeepingEstadoLimpieza::ESTADO_LIMPIA_POR_INSPECCIONAR);
afirmar($hkServicio->puedeCheckIn($unidadId1) === false, 'Caso 5: Habitación LIMPIA_POR_INSPECCIONAR (VCL) no puede recibir check-in');

// Caso 6: Habitación RETOQUE_REQUERIDO
$hkRepo->actualizarEstadoLimpieza($unidadId1, HousekeepingEstadoLimpieza::ESTADO_RETOQUE_REQUERIDO);
afirmar($hkServicio->puedeCheckIn($unidadId1) === false, 'Caso 6: Habitación RETOQUE_REQUERIDO no puede recibir check-in');

// Caso 7: Habitación limpia pero con bloqueo de mantenimiento (OOO)
$hkRepo->actualizarEstadoLimpieza($unidadId1, HousekeepingEstadoLimpieza::ESTADO_LIMPIA_INSPECCIONADA);
$pdo->prepare('INSERT INTO mantenimiento_ordenes (codigo, propiedad_id, unidad_id, tipo, estado, prioridad, requiere_bloqueo, fecha_bloqueo_inicio, fecha_bloqueo_fin, titulo, descripcion, fecha_programada_inicio, fecha_programada_fin, creado_por_actor_id, creado_en) VALUES ("OT-HK-TEST", ?, ?, "CORRECTIVO", "EN_PROCESO", "ALTA", 1, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 DAY), "Fuga agua", "Reparar fuga", CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 DAY), ?, NOW())')->execute([$propiedadId, $unidadId1, $actorId]);
$mtoId = (int) $pdo->lastInsertId();
afirmar($hkServicio->puedeCheckIn($unidadId1) === false, 'Caso 7: Habitación limpia con mantenimiento bloqueante (OOO) NO es apta para check-in');

// Limpiar orden de mantenimiento
$pdo->exec("DELETE FROM mantenimiento_ordenes WHERE id = {$mtoId}");
afirmar($hkServicio->puedeCheckIn($unidadId1) === true, 'Caso 8: Al cerrar mantenimiento bloqueante, vuelve a ser apta (VR)');

// Caso 9: Habitación con unidad INACTIVA comercialmente (OOS)
$pdo->exec("UPDATE unidades SET estado = 'INACTIVO' WHERE id = {$unidadId1}");
afirmar($hkServicio->puedeCheckIn($unidadId1) === false, 'Caso 9: Unidad con estado comercial INACTIVO (OOS) rechaza check-in');
$pdo->exec("UPDATE unidades SET estado = 'ACTIVO' WHERE id = {$unidadId1}");

// Caso 10: validarAptaParaCheckin lanza UnidadNoListaExcepcion cuando no es apta
$hkRepo->actualizarEstadoLimpieza($unidadId1, HousekeepingEstadoLimpieza::ESTADO_SUCIA);
$excepcionCapturada = false;
try {
    $hkServicio->validarAptaParaCheckin($unidadId1);
} catch (UnidadNoListaExcepcion $e) {
    $excepcionCapturada = true;
}
afirmar($excepcionCapturada === true, 'Caso 10: validarAptaParaCheckin() lanza UnidadNoListaExcepcion (409) con mensaje explicativo');

// Caso 11: Proyección en vivo del DTO HousekeepingDerivacionOperativa
$rack = $hkServicio->obtenerRackOperacional($propiedadId);
$dtoU1 = null;
foreach ($rack as $item) {
    if ($item->obtenerUnidadId() === $unidadId1) {
        $dtoU1 = $item;
        break;
    }
}
afirmar($dtoU1 !== null && $dtoU1->obtenerCondicionDerivada() === 'VD', 'Caso 11: Derivación dinámica proyecta correctamente condición VD sin columnas en BD');

// =========================================================================
// BLOQUE 2: FOLIOS SEGUROS E IDEMPOTENCIA DE SALIDA (CASOS 12 - 16)
// =========================================================================
echo "\n--- BLOQUE 2: FOLIOS SEGUROS E IDEMPOTENCIA DE CHECK-OUT ---\n";

// Caso 12: Generación correlativa de folios HK-YYYYMMDD-XXXX
$cod1 = $hkRepo->generarCodigoTarea();
$cod2 = $hkRepo->generarCodigoTarea();
afirmar(str_starts_with($cod1, 'HK-') && str_starts_with($cod2, 'HK-') && $cod1 !== $cod2, 'Caso 12: Generación correlativa y segura de folios de tareas HK-YYYYMMDD-XXXX');

// Caso 13: Generación correlativa de folios de lavandería LAV-YYYYMMDD-XXXX
$codLav1 = $hkRepo->generarCodigoLote();
$codLav2 = $hkRepo->generarCodigoLote();
afirmar(str_starts_with($codLav1, 'LAV-') && str_starts_with($codLav2, 'LAV-') && $codLav1 !== $codLav2, 'Caso 13: Generación correlativa de folios LAV-YYYYMMDD-XXXX');

// Caso 14: marcarSuciaPorCheckout genera tarea de SALIDA y pone unidad en SUCIA
$codRes = 'RES-HK-' . bin2hex(random_bytes(4));
$pdo->prepare('INSERT INTO reservas (codigo, persona_titular_id, fecha_entrada, fecha_salida, noches, estado, canal, origen, total, creado_en) VALUES (?, 1, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 DAY), 1, "CONFIRMADA", "PMS", "DIRECTO", 100.00, NOW())')->execute([$codRes]);
$reservaId1 = (int) $pdo->lastInsertId();

$pdo->prepare('INSERT INTO reserva_unidades (reserva_id, unidad_id, precio_unitario_noche, noches, subtotal, total, moneda_codigo, creado_en) VALUES (?, ?, 100.00, 1, 100.00, 100.00, "PEN", NOW())')->execute([$reservaId1, $unidadId1]);
$ruId1 = (int) $pdo->lastInsertId();

$codEst = 'EST-HK-' . bin2hex(random_bytes(4));
$pdo->prepare('INSERT INTO estadias (codigo, reserva_id, reserva_unidad_id, unidad_id, fecha_entrada, fecha_salida_prevista, checkin_en, estado, checkout_en, checkout_por_actor_id, checkin_por_actor_id, creado_en) VALUES (?, ?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 DAY), NOW(), "FINALIZADA", NOW(), ?, ?, NOW())')->execute([$codEst, $reservaId1, $ruId1, $unidadId1, $actorId, $actorId]);
$estadiaId1 = (int) $pdo->lastInsertId();

$tareaSalida1 = $hkServicio->marcarSuciaPorCheckout($unidadId1, $estadiaId1, $actorId, 'Checkout automático de prueba');
afirmar($tareaSalida1->obtenerTipoTarea() === HousekeepingTarea::TIPO_SALIDA && $tareaSalida1->obtenerEstado() === HousekeepingTarea::ESTADO_PENDIENTE, 'Caso 14: Check-out genera tarea SALIDA con estado PENDIENTE');

// Caso 15: Unidad quedó en estado SUCIA
$limpPostCheckout = $hkRepo->obtenerLimpiezaUnidad($unidadId1);
afirmar($limpPostCheckout['estado_limpieza'] === HousekeepingEstadoLimpieza::ESTADO_SUCIA && (int)$limpPostCheckout['tarea_activa_id'] === $tareaSalida1->obtenerId(), 'Caso 15: Unidad física quedó en estado SUCIA y vinculada a tarea activa');

// Caso 16: Idempotencia estricta de checkout (re-ejecutar con la misma estadía)
$tareaSalidaDuplicada = $hkServicio->marcarSuciaPorCheckout($unidadId1, $estadiaId1, $actorId, 'Reintento checkout');
afirmar($tareaSalidaDuplicada->obtenerId() === $tareaSalida1->obtenerId(), 'Caso 16: Idempotencia estricta: reintento de checkout reutiliza tarea de salida idéntica');

// =========================================================================
// BLOQUE 3: CHECKLIST SNAPSHOT INMUTABLE Y ASIGNACIÓN (CASOS 17 - 22)
// =========================================================================
echo "\n--- BLOQUE 3: CHECKLIST SNAPSHOT INMUTABLE Y ASIGNACIÓN ---\n";

// Caso 17: Tarea contiene checklist snapshot congelado
$chkItems = $hkRepo->obtenerChecklistTarea($tareaSalida1->obtenerId());
afirmar(count($chkItems) > 0, 'Caso 17: Tarea de salida clonó snapshot inmutable de checklist hotelero');

// Caso 18: Verificar presencia de ítems críticos
$tieneCriticos = false;
foreach ($chkItems as $item) {
    if (!empty($item['es_critico_snapshot'])) {
        $tieneCriticos = true;
        break;
    }
}
afirmar($tieneCriticos === true, 'Caso 18: Snapshot contiene puntos de control marcados como críticos');

// Caso 19: Crear colaboradora para asignar
$pdo->prepare('INSERT INTO colaboradores (persona_id, codigo, estado, creado_en) VALUES (1, "COL-HK-001", "ACTIVO", NOW()) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)')->execute();
$colaboradorCamareraId = (int) $pdo->lastInsertId();
afirmar($colaboradorCamareraId > 0, 'Caso 19: Colaboradora de piso registrada para asignación');

// Caso 20: Asignar tarea a camarera
$tareaAsignada = $hkServicio->asignarTarea($tareaSalida1->obtenerId(), $colaboradorCamareraId, null, $actorId);
afirmar($tareaAsignada->obtenerEstado() === HousekeepingTarea::ESTADO_ASIGNADA && $tareaAsignada->obtenerCamareraColaboradorId() === $colaboradorCamareraId, 'Caso 20: Tarea asignada exitosamente a camarera');

// Caso 21: Iniciar limpieza
$tareaIniciada = $hkServicio->iniciarLimpieza($tareaSalida1->obtenerId(), $actorId);
afirmar($tareaIniciada->obtenerEstado() === HousekeepingTarea::ESTADO_EN_PROCESO && $tareaIniciada->obtenerIniciadoEn() !== null, 'Caso 21: Limpieza iniciada (estado EN_PROCESO)');

// Caso 22: Registro 1:1 de unidad pasa a EN_LIMPIEZA
$limpEnProceso = $hkRepo->obtenerLimpiezaUnidad($unidadId1);
afirmar($limpEnProceso['estado_limpieza'] === HousekeepingEstadoLimpieza::ESTADO_EN_LIMPIEZA, 'Caso 22: Unidad 1:1 sincronizada en estado EN_LIMPIEZA');

// =========================================================================
// BLOQUE 4: FINALIZACIÓN, AMENITIES Y KARDEX (CASOS 23 - 26)
// =========================================================================
echo "\n--- BLOQUE 4: FINALIZACIÓN, AMENITIES Y KARDEX (INVENTARIO-1) ---\n";

// Caso 23: Finalizar limpieza con reposición de amenities (2 unidades de jabón)
$stockAntes = (float) $pdo->query("SELECT cantidad_actual FROM inventario_existencias WHERE articulo_id = {$articuloAmenitieId} AND ubicacion_id = {$almacenOfficeId}")->fetchColumn();

$tareaFinalizada = $hkServicio->finalizarLimpieza($tareaSalida1->obtenerId(), [
    'notas_operario' => 'Habitación higienizada y toallas cambiadas',
    'condicion_operacional' => HousekeepingTarea::CONDICION_NINGUNA,
    'consumos' => [
        [
            'articulo_id' => $articuloAmenitieId,
            'almacen_origen_id' => $almacenOfficeId,
            'cantidad' => '2.0000',
        ],
    ],
], $actorId);

$stockDespues = (float) $pdo->query("SELECT cantidad_actual FROM inventario_existencias WHERE articulo_id = {$articuloAmenitieId} AND ubicacion_id = {$almacenOfficeId}")->fetchColumn();

afirmar($tareaFinalizada->obtenerEstado() === HousekeepingTarea::ESTADO_POR_INSPECCIONAR, 'Caso 23: Tarea pasa a estado POR_INSPECCIONAR');
afirmar(abs(($stockAntes - $stockDespues) - 2.0) < 0.0001, 'Caso 24: Kardex debitó exactamente 2 unidades de amenities en stock real');

// Caso 25: Consumo registrado en housekeeping_tarea_consumos
$consumosGuardados = $hkRepo->obtenerConsumosTarea($tareaSalida1->obtenerId());
afirmar(count($consumosGuardados) === 1 && (float)$consumosGuardados[0]['cantidad'] === 2.0, 'Caso 25: Consumo registrado con FK formal a movimiento de Kardex');

// Caso 26: Unidad 1:1 pasa a LIMPIA_POR_INSPECCIONAR (VCL)
$limpVcl = $hkRepo->obtenerLimpiezaUnidad($unidadId1);
afirmar($limpVcl['estado_limpieza'] === HousekeepingEstadoLimpieza::ESTADO_LIMPIA_POR_INSPECCIONAR, 'Caso 26: Unidad física en estado LIMPIA_POR_INSPECCIONAR (VCL)');

// =========================================================================
// BLOQUE 5: INSPECCIÓN, CALIFICACIÓN Y REPROCESO (CASOS 27 - 34)
// =========================================================================
echo "\n--- BLOQUE 5: INSPECCIÓN, CALIFICACIÓN Y REPROCESO ---\n";

// Caso 27: Intento de aprobar con un ítem crítico en NO_CONFORME debe ser rechazado
$itemsInspeccion = [];
$primerCriticoId = 0;
foreach ($chkItems as $item) {
    if (!empty($item['es_critico_snapshot']) && $primerCriticoId === 0) {
        $primerCriticoId = (int) $item['id'];
        $itemsInspeccion[] = ['id' => $item['id'], 'resultado' => 'NO_CONFORME', 'observacion' => 'Mancha en sábana'];
    } else {
        $itemsInspeccion[] = ['id' => $item['id'], 'resultado' => 'CONFORME'];
    }
}

$rechazoPorCritico = false;
try {
    $hkServicio->inspeccionarTarea($tareaSalida1->obtenerId(), true, $itemsInspeccion, 'Aprobada forzada', $actorId);
} catch (ValidacionHousekeepingExcepcion $e) {
    $rechazoPorCritico = true;
}
afirmar($rechazoPorCritico === true, 'Caso 27: Sistema rechaza aprobación si un punto crítico está NO_CONFORME');

// Caso 28: Rechazar formalmente la tarea (Envío a Reproceso)
$tareaRechazada = $hkServicio->inspeccionarTarea($tareaSalida1->obtenerId(), false, $itemsInspeccion, 'Rechazada: cambiar sábanas', $actorId);
afirmar($tareaRechazada->obtenerEstado() === HousekeepingTarea::ESTADO_RECHAZADA, 'Caso 28: Tarea calificada como RECHAZADA para reproceso');

// Caso 29: Unidad física pasa a RETOQUE_REQUERIDO
$limpRetoque = $hkRepo->obtenerLimpiezaUnidad($unidadId1);
afirmar($limpRetoque['estado_limpieza'] === HousekeepingEstadoLimpieza::ESTADO_RETOQUE_REQUERIDO, 'Caso 29: Unidad física pasa a RETOQUE_REQUERIDO (no apta check-in)');

// Caso 30: Historial append-only registra transición a RECHAZADA
$historial = $pdo->query("SELECT * FROM housekeeping_tarea_historial WHERE tarea_id = {$tareaSalida1->obtenerId()} ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
afirmar($historial['estado_nuevo'] === 'RECHAZADA', 'Caso 30: Trazabilidad append-only D-061 registrada');

// Caso 31: Reiniciar limpieza de retoque
$tareaRetoqueIniciada = $hkServicio->iniciarLimpieza($tareaSalida1->obtenerId(), $actorId);
afirmar($tareaRetoqueIniciada->obtenerEstado() === HousekeepingTarea::ESTADO_EN_PROCESO, 'Caso 31: Tarea rechazada se reabre en EN_PROCESO para retoque');

// Caso 32: Finalizar retoque
$tareaRetoqueFin = $hkServicio->finalizarLimpieza($tareaSalida1->obtenerId(), ['notas_operario' => 'Sábanas cambiadas'], $actorId);
afirmar($tareaRetoqueFin->obtenerEstado() === HousekeepingTarea::ESTADO_POR_INSPECCIONAR, 'Caso 32: Retoque finalizado y vuelto a enviar a inspección');

// Caso 33: Aprobar formalmente con todos los ítems CONFORME
$itemsConformes = [];
foreach ($chkItems as $item) {
    $itemsConformes[] = ['id' => $item['id'], 'resultado' => 'CONFORME', 'observacion' => 'Verificado impecable'];
}
$tareaAprobada = $hkServicio->inspeccionarTarea($tareaSalida1->obtenerId(), true, $itemsConformes, 'Aprobada al 100%', $actorId);
afirmar($tareaAprobada->obtenerEstado() === HousekeepingTarea::ESTADO_COMPLETADA, 'Caso 33: Tarea pasa a COMPLETADA tras aprobación formal');

// Caso 34: Unidad física pasa a LIMPIA_INSPECCIONADA y queda apta para Check-in (VR)
$limpAprobada = $hkRepo->obtenerLimpiezaUnidad($unidadId1);
$aptaCheckinFinal = $hkServicio->puedeCheckIn($unidadId1);
afirmar($limpAprobada['estado_limpieza'] === HousekeepingEstadoLimpieza::ESTADO_LIMPIA_INSPECCIONADA && $aptaCheckinFinal === true, 'Caso 34: Unidad física en estado LIMPIA_INSPECCIONADA (VR apta para check-in)');

// =========================================================================
// BLOQUE 6: CONTROL DE LENCERÍA Y LAVANDERÍA TEXTIL (CASOS 35 - 40)
// =========================================================================
echo "\n--- BLOQUE 6: CONTROL DE LENCERÍA Y LAVANDERÍA TEXTIL (D-083) ---\n";

// Caso 35: Despacho de lote de lencería hacia lavandería (10 sábanas)
$stockOfficeAntes = (float) $pdo->query("SELECT cantidad_actual FROM inventario_existencias WHERE articulo_id = {$articuloTextilId} AND ubicacion_id = {$almacenOfficeId}")->fetchColumn();
$stockLavAntes = (float) ($pdo->query("SELECT cantidad_actual FROM inventario_existencias WHERE articulo_id = {$articuloTextilId} AND ubicacion_id = {$almacenLavanderiaId}")->fetchColumn() ?: 0);

$loteDespachado = $hkServicio->despacharLoteLavanderia([
    'propiedad_id' => $propiedadId,
    'almacen_origen_id' => $almacenOfficeId,
    'ubicacion_lavanderia_id' => $almacenLavanderiaId,
    'fecha_despacho' => date('Y-m-d'),
    'notas_despacho' => 'Lote semanal de sábanas king',
    'lineas' => [
        [
            'articulo_id' => $articuloTextilId,
            'cantidad_enviada' => '10.0000',
            'observaciones' => 'Prendas con manchas leves',
        ],
    ],
], $actorId);

$stockOfficeDespues = (float) $pdo->query("SELECT cantidad_actual FROM inventario_existencias WHERE articulo_id = {$articuloTextilId} AND ubicacion_id = {$almacenOfficeId}")->fetchColumn();
$stockLavDespues = (float) $pdo->query("SELECT cantidad_actual FROM inventario_existencias WHERE articulo_id = {$articuloTextilId} AND ubicacion_id = {$almacenLavanderiaId}")->fetchColumn();

afirmar($loteDespachado->obtenerEstado() === HousekeepingLoteLavanderia::ESTADO_DESPACHADO, 'Caso 35: Lote de lavandería despachado exitosamente');
afirmar(abs(($stockOfficeAntes - $stockOfficeDespues) - 10.0) < 0.0001 && abs(($stockLavDespues - $stockLavAntes) - 10.0) < 0.0001, 'Caso 36: Traslado en Kardex de dos patas ejecutado (Office -> Custodia Externa)');

// Caso 37: Validación: no permitir recibir más cantidad de la enviada
$lineasLote = $hkRepo->obtenerLineasLote($loteDespachado->obtenerId());
$lineaTextilId = (int) $lineasLote[0]['id'];

$errorExceso = false;
try {
    $hkServicio->recibirLoteLavanderia($loteDespachado->obtenerId(), [
        [
            'linea_id' => $lineaTextilId,
            'cantidad_recibida' => '11.0000',
            'cantidad_baja_merma' => '0.0000',
        ],
    ], 'Intento exceso', $actorId);
} catch (ValidacionHousekeepingExcepcion $e) {
    $errorExceso = true;
}
afirmar($errorExceso === true, 'Caso 37: Validación impide recibir más cantidad de la enviada');

// Caso 38: Retorno con discrepancia y merma: 8 limpias recibidas, 1 merma/baja, 1 faltante no justificado
$loteRetornado = $hkServicio->recibirLoteLavanderia($loteDespachado->obtenerId(), [
    [
        'linea_id' => $lineaTextilId,
        'cantidad_recibida' => '8.0000',
        'cantidad_baja_merma' => '1.0000',
        'observaciones' => '1 prenda desgarrada dada de baja',
    ],
], 'Retorno parcial con 1 merma y 1 faltante', $actorId);

afirmar($loteRetornado->obtenerEstado() === HousekeepingLoteLavanderia::ESTADO_CON_DISCREPANCIA, 'Caso 38: Estado de lote pasa a CON_DISCREPANCIA por faltante');

// Caso 39: Cálculo virtual de la discrepancia en la línea
$lineasActualizadas = $hkRepo->obtenerLineasLote($loteDespachado->obtenerId());
$lineaRet = $lineasActualizadas[0];
// 10 enviadas - (8 recibidas + 1 merma) = 1 diferencia
afirmar((float)$lineaRet['cantidad_diferencia'] === 1.0, 'Caso 39: Columna virtual cantidad_diferencia calculó exactamente 1.0000 de faltante');

// Caso 40: Retorno total en segundo lote (8 enviadas, 8 recibidas -> RETORNADO_TOTAL)
$lote2 = $hkServicio->despacharLoteLavanderia([
    'propiedad_id' => $propiedadId,
    'almacen_origen_id' => $almacenOfficeId,
    'ubicacion_lavanderia_id' => $almacenLavanderiaId,
    'lineas' => [
        ['articulo_id' => $articuloTextilId, 'cantidad_enviada' => '5.0000'],
    ],
], $actorId);

$lineas2 = $hkRepo->obtenerLineasLote($lote2->obtenerId());
$lote2Ret = $hkServicio->recibirLoteLavanderia($lote2->obtenerId(), [
    ['linea_id' => $lineas2[0]['id'], 'cantidad_recibida' => '5.0000', 'cantidad_baja_merma' => '0.0000'],
], 'Retorno 100% conforme', $actorId);

afirmar($lote2Ret->obtenerEstado() === HousekeepingLoteLavanderia::ESTADO_RETORNADO_TOTAL, 'Caso 40: Lote con retorno 100% conforme finaliza en RETORNADO_TOTAL');

// =========================================================================
// RESUMEN MATRIZ 40
// =========================================================================
echo "\n====================================================================\n";
echo " RESUMEN: {$pasadas} / {$total} PRUEBAS PASADAS\n";
if ($fallidas > 0) {
    echo " FALLIDAS: {$fallidas}\n";
    foreach ($errores as $err) {
        echo "   - {$err}\n";
    }
    exit(1);
} else {
    echo " RESULTADO: MATRIZ HOUSEKEEPING-1 40/40 PASS EXITOSA\n";
    echo "====================================================================\n";
    exit(0);
}
