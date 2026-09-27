<?php

declare(strict_types=1);

/**
 * Suite de Verificación MANTENIMIENTO-1 — Matriz Exhaustiva de 40 Pruebas de Dominio
 * 
 * Verifica las 11 decisiones vinculantes de D-077:
 * - Separación ontológica: INCIDENCIA != ORDEN DE TRABAJO != BLOQUEO OPERATIVO.
 * - Coherencia estricta de bloqueo en BD (requiere_bloqueo, fechas).
 * - Protección anticipada de inventario en PROGRAMADA.
 * - Clasificación en inventario: MANTENIMIENTO / MANTENIMIENTO_ORDEN.
 * - Responsables (interno/externo/mixto) validados en servicio.
 * - Máquinas de estado desacopladas.
 * - Preservación histórica al liberar (noches pasadas intactas).
 * - Costos gestionados con BCMath.
 * - Relación N:M entre órdenes e incidencias.
 * - Manejo canónico de excepciones (409 ConflictoDisponibilidadExcepcion).
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/app/Nucleo/Ayudante.php';

use CamargoPMS\Excepciones\ConflictoDisponibilidadExcepcion;
use CamargoPMS\Excepciones\EstadoMantenimientoInvalidoExcepcion;
use CamargoPMS\Excepciones\IncidenciaNoEncontradaExcepcion;
use CamargoPMS\Excepciones\OrdenTrabajoNoEncontradaExcepcion;
use CamargoPMS\Excepciones\ValidacionExcepcion;
use CamargoPMS\Modelos\Incidencia;
use CamargoPMS\Modelos\MantenimientoHistorialEstado;
use CamargoPMS\Modelos\OrdenTrabajo;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Servicios\MantenimientoServicio;

Configuracion::cargar(dirname(__DIR__));
$pdo = BaseDatos::conexion();

$totalPruebas = 0;
$pruebasExitosas = 0;
$errores = [];

function assertTest(bool $condicion, string $codigo, string $descripcion): void {
    global $totalPruebas, $pruebasExitosas, $errores;
    $totalPruebas++;
    if ($condicion) {
        $pruebasExitosas++;
        echo "  [PASS] {$codigo}: {$descripcion}\n";
    } else {
        $errores[] = "{$codigo}: {$descripcion}";
        echo "  [FAIL] {$codigo}: {$descripcion}\n";
    }
}

echo "=====================================================================\n";
echo "CAMARGO PMS — SUITE FORMAL MANTENIMIENTO-1 (MATRIZ 40 CASOS)\n";
echo "=====================================================================\n\n";

// Asegurar datos base
$stmtProp = $pdo->query("SELECT id FROM propiedades WHERE estado = 'ACTIVO' LIMIT 1");
$propiedadId = (int) $stmtProp->fetchColumn();

$stmtUni = $pdo->query("SELECT id FROM unidades WHERE propiedad_id = {$propiedadId} AND estado = 'ACTIVO' LIMIT 2");
$unidades = $stmtUni->fetchAll(PDO::FETCH_COLUMN);
$unidadId1 = (int) ($unidades[0] ?? 1);
$unidadId2 = (int) ($unidades[1] ?? 2);

$stmtPer = $pdo->query("SELECT id FROM personas LIMIT 1");
$personaId = (int) $stmtPer->fetchColumn();

$stmtCol = $pdo->query("SELECT id FROM colaboradores WHERE estado = 'ACTIVO' LIMIT 1");
$colaboradorId = (int) $stmtCol->fetchColumn();
if ($colaboradorId === 0) {
    $stmtPerLibre = $pdo->query("SELECT p.id FROM personas p LEFT JOIN colaboradores c ON c.persona_id = p.id WHERE c.id IS NULL LIMIT 1");
    $perId = (int) $stmtPerLibre->fetchColumn();
    if ($perId === 0) {
        $pdo->exec("INSERT INTO personas (nombres, apellido_paterno, apellido_materno, pais_nacionalidad_id, estado) VALUES ('Técnico', 'Mantenimiento', 'PMS', 1, 'ACTIVO')");
        $perId = (int) $pdo->lastInsertId();
    }
    $pdo->exec("INSERT INTO colaboradores (persona_id, codigo, estado) VALUES ({$perId}, 'COL-MNT-001', 'ACTIVO')");
    $colaboradorId = (int) $pdo->lastInsertId();
}

$stmtProv = $pdo->query("SELECT id FROM proveedores WHERE estado = 'ACTIVO' LIMIT 1");
$proveedorId = (int) $stmtProv->fetchColumn();
if ($proveedorId === 0) {
    $pdo->exec("INSERT INTO proveedores (tipo_proveedor, persona_id, razon_social, estado, creado_por_actor_id) VALUES ('EMPRESA', NULL, 'Servicios Técnicos SAC', 'ACTIVO', 1)");
    $proveedorId = (int) $pdo->lastInsertId();
}

$actorId = 1;

$servicio = new MantenimientoServicio($pdo);

// Limpiar inventario diario residual de pruebas previas para evitar falsos positivos
$pdo->exec("DELETE FROM inventario_diario_unidades WHERE origen_tipo = 'MANTENIMIENTO_ORDEN' OR fecha >= '2026-12-01'");

// -----------------------------------------------------------------------------
// GRUPO 1: MODELOS DE DOMINIO E INSTANCIACIÓN (CASOS 01 A 05)
// -----------------------------------------------------------------------------
echo "--- GRUPO 1: Modelos de Dominio y Métodos (MNT-01 a MNT-05) ---\n";

// MNT-01: Instanciación de Incidencia
$incModelo = new Incidencia(
    1, 'INC-TEST-0001', $propiedadId, $unidadId1, $personaId,
    'PLOMERIA', 'MEDIA', 'Fuga de agua', 'Goteo constante en lavabo',
    'Baño', 'REPORTADA', null, $actorId
);
assertTest($incModelo->obtenerCodigo() === 'INC-TEST-0001' && $incModelo->estaAbierta(), 'MNT-01', 'Instanciación de Incidencia y estado abierto inicial');

// MNT-02: Serialización y Deserialización de Incidencia
$arrInc = $incModelo->aArreglo();
$incRestaurada = Incidencia::desdeArreglo($arrInc);
assertTest($incRestaurada->obtenerTitulo() === 'Fuga de agua' && $incRestaurada->obtenerCategoria() === 'PLOMERIA', 'MNT-02', 'Serialización y deserialización fidedigna de Incidencia');

// MNT-03: Instanciación de Orden de Trabajo
$otModelo = new OrdenTrabajo(
    1, 'OT-TEST-0001', 'CORRECTIVO', 'ALTA', $propiedadId, $unidadId1,
    'Reparar tubería', 'Cambio de empaques', 'INTERNO', $colaboradorId, null, null,
    true, '2026-12-01', '2026-12-05', '2026-12-01', '2026-12-05', null, null,
    '150.00', '100.00', '50.00', '150.00', 'PEN', 'BORRADOR', $actorId
);
assertTest($otModelo->obtenerCodigo() === 'OT-TEST-0001' && $otModelo->requiereBloqueo() && $otModelo->estaEnBorrador(), 'MNT-03', 'Instanciación de OrdenTrabajo con flag de bloqueo');

// MNT-04: Serialización y Deserialización de OrdenTrabajo
$arrOt = $otModelo->aArreglo();
$otRestaurada = OrdenTrabajo::desdeArreglo($arrOt);
assertTest($otRestaurada->obtenerCostoTotal() === '150.00' && $otRestaurada->obtenerTipo() === 'CORRECTIVO', 'MNT-04', 'Serialización y deserialización fidedigna de OrdenTrabajo');

// MNT-05: Modelo MantenimientoHistorialEstado
$histModelo = new MantenimientoHistorialEstado(null, 'ORDEN_TRABAJO', 1, 'BORRADOR', 'PROGRAMADA', 'Aprobada', $actorId);
assertTest($histModelo->obtenerEntidadTipo() === 'ORDEN_TRABAJO' && $histModelo->obtenerEstadoNuevo() === 'PROGRAMADA', 'MNT-05', 'Instanciación de historial inmutable de estados');

// -----------------------------------------------------------------------------
// GRUPO 2: GESTIÓN DE INCIDENCIAS TÉCNICAS (CASOS 06 A 12)
// -----------------------------------------------------------------------------
echo "\n--- GRUPO 2: Gestión de Incidencias Técnicas (MNT-06 a MNT-12) ---\n";

// MNT-06: Reportar incidencia válida
$inc1 = $servicio->reportarIncidencia([
    'propiedad_id' => $propiedadId,
    'unidad_id' => $unidadId1,
    'reportado_por_persona_id' => $personaId,
    'categoria' => 'ELECTRICIDAD',
    'severidad' => 'ALTA',
    'titulo' => 'Cortocircuito en tomacorriente',
    'descripcion' => 'Chispas visibles al conectar secador',
    'ubicacion_detallada' => 'Pared lateral derecha',
], $actorId);
assertTest(str_starts_with($inc1->obtenerCodigo(), 'INC-') && $inc1->obtenerEstado() === 'REPORTADA', 'MNT-06', 'Reporte exitoso de incidencia técnica en estado REPORTADA');

// MNT-07: Invariante: Una incidencia JAMÁS bloquea inventario
$stmtBloqInc = $pdo->prepare("SELECT COUNT(*) FROM inventario_diario_unidades WHERE origen_id = ? AND origen_tipo LIKE '%INCIDENCIA%'");
$stmtBloqInc->execute([$inc1->obtenerId()]);
assertTest((int) $stmtBloqInc->fetchColumn() === 0, 'MNT-07', 'Invariante ontológico: La incidencia reportada NO genera registros en inventario diario');

// MNT-08: Validación de campos requeridos en incidencia (título corto)
$errorTitulo = false;
try {
    $servicio->reportarIncidencia([
        'propiedad_id' => $propiedadId,
        'reportado_por_persona_id' => $personaId,
        'titulo' => 'ab',
        'descripcion' => 'Descripción válida',
    ], $actorId);
} catch (ValidacionExcepcion $e) {
    $errorTitulo = true;
}
assertTest($errorTitulo, 'MNT-08', 'Rechazo de incidencia con título menor a 3 caracteres');

// MNT-09: Pasar incidencia a evaluación técnica
$incEvaluada = $servicio->evaluarIncidencia((int) $inc1->obtenerId(), $actorId);
assertTest($incEvaluada->obtenerEstado() === 'EN_EVALUACION', 'MNT-09', 'Transición válida de REPORTADA a EN_EVALUACION');

// MNT-10: Resolver incidencia directa con motivo
$inc2 = $servicio->reportarIncidencia([
    'propiedad_id' => $propiedadId,
    'unidad_id' => $unidadId1,
    'reportado_por_persona_id' => $personaId,
    'categoria' => 'CERRAJERIA',
    'severidad' => 'BAJA',
    'titulo' => 'Pestillo trabado',
    'descripcion' => 'Cerradura de baño con dificultad',
], $actorId);
$incResuelta = $servicio->resolverIncidenciaDirecta((int) $inc2->obtenerId(), 'Se lubricó mecanismo in situ', $actorId);
assertTest($incResuelta->obtenerEstado() === 'RESUELTA_DIRECTA' && $incResuelta->obtenerMotivoCierre() !== null, 'MNT-10', 'Resolución directa in situ con motivo registrado');

// MNT-11: Desestimar incidencia con motivo justificado
$inc3 = $servicio->reportarIncidencia([
    'propiedad_id' => $propiedadId,
    'reportado_por_persona_id' => $personaId,
    'titulo' => 'Alarma de humo parpadea',
    'descripcion' => 'Luz verde intermitente',
], $actorId);
$incDesestimada = $servicio->desestimarIncidencia((int) $inc3->obtenerId(), 'Funcionamiento normal según manual técnico', $actorId);
assertTest($incDesestimada->obtenerEstado() === 'DESESTIMADA', 'MNT-11', 'Desestimación justificada de incidencia');

// MNT-12: Transición inválida sobre incidencia ya cerrada
$errorCierre = false;
try {
    $servicio->evaluarIncidencia((int) $inc3->obtenerId(), $actorId);
} catch (EstadoMantenimientoInvalidoExcepcion $e) {
    $errorCierre = true;
}
assertTest($errorCierre, 'MNT-12', 'Rechazo de evaluación sobre incidencia ya cerrada (DESESTIMADA)');

// -----------------------------------------------------------------------------
// GRUPO 3: FORMULACIÓN DE ÓRDENES DE TRABAJO (CASOS 13 A 18)
// -----------------------------------------------------------------------------
echo "\n--- GRUPO 3: Formulación de Órdenes de Trabajo (MNT-13 a MNT-18) ---\n";

// MNT-13: Crear orden de trabajo en BORRADOR sin bloqueo
$ot1 = $servicio->crearOrden([
    'propiedad_id' => $propiedadId,
    'unidad_id' => $unidadId1,
    'tipo' => 'CORRECTIVO',
    'prioridad' => 'MEDIA',
    'titulo' => 'Cambio de bombilla',
    'descripcion' => 'Bombilla quemada en velador',
    'tipo_asignacion' => 'INTERNO',
    'colaborador_asignado_id' => $colaboradorId,
    'requiere_bloqueo' => 0,
    'fecha_programada_inicio' => '2026-11-10',
    'fecha_programada_fin' => '2026-11-10',
], $actorId);
assertTest($ot1->obtenerEstado() === 'BORRADOR' && !$ot1->requiereBloqueo(), 'MNT-13', 'Creación de orden de trabajo sin bloqueo en estado BORRADOR');

// MNT-14: Invariante: Una orden en BORRADOR JAMÁS bloquea inventario
$stmtBloqOt = $pdo->prepare("SELECT COUNT(*) FROM inventario_diario_unidades WHERE origen_id = ? AND origen_tipo = 'MANTENIMIENTO_ORDEN'");
$stmtBloqOt->execute([$ot1->obtenerId()]);
assertTest((int) $stmtBloqOt->fetchColumn() === 0, 'MNT-14', 'Invariante: Orden en BORRADOR no genera filas en inventario_diario_unidades');

// MNT-15: Coherencia de Bloqueo en BD: requiere_bloqueo = 1 exige unidad_id y fechas
$errorBloqSinUnidad = false;
try {
    $servicio->crearOrden([
        'propiedad_id' => $propiedadId,
        'unidad_id' => null,
        'titulo' => 'Pintura general',
        'descripcion' => 'Fachada',
        'requiere_bloqueo' => 1,
        'fecha_programada_inicio' => '2026-11-10',
        'fecha_programada_fin' => '2026-11-15',
    ], $actorId);
} catch (ValidacionExcepcion $e) {
    $errorBloqSinUnidad = true;
}
assertTest($errorBloqSinUnidad, 'MNT-15', 'Validación estricta: requiere_bloqueo = 1 exige unidad_id');

// MNT-16: Coherencia de Bloqueo: fecha_fin > fecha_inicio
$errorFechasInversas = false;
try {
    $servicio->crearOrden([
        'propiedad_id' => $propiedadId,
        'unidad_id' => $unidadId1,
        'titulo' => 'Lapeado de piso',
        'descripcion' => 'Pisos',
        'requiere_bloqueo' => 1,
        'fecha_programada_inicio' => '2026-11-15',
        'fecha_programada_fin' => '2026-11-10',
    ], $actorId);
} catch (ValidacionExcepcion $e) {
    $errorFechasInversas = true;
}
assertTest($errorFechasInversas, 'MNT-16', 'Validación estricta: rechazo de intervalo con fecha_fin < fecha_inicio');

// MNT-17: Crear orden preventiva sin incidencias
$otPrev = $servicio->crearOrden([
    'propiedad_id' => $propiedadId,
    'unidad_id' => $unidadId2,
    'tipo' => 'PREVENTIVO',
    'prioridad' => 'BAJA',
    'titulo' => 'Mantenimiento preventivo de aire acondicionado',
    'descripcion' => 'Limpieza de filtros y medición de refrigerante',
    'tipo_asignacion' => 'EXTERNO',
    'proveedor_id' => $proveedorId,
    'requiere_bloqueo' => 0,
    'fecha_programada_inicio' => '2026-11-20',
    'fecha_programada_fin' => '2026-11-20',
], $actorId);
assertTest($otPrev->obtenerTipo() === 'PREVENTIVO' && empty($otPrev->obtenerIncidenciasAsociadas()), 'MNT-17', 'Orden de trabajo preventiva formulada sin incidencias asociadas');

// MNT-18: Vinculación N:M: Orden con múltiples incidencias asociadas
$incVinculable1 = $servicio->reportarIncidencia(['propiedad_id' => $propiedadId, 'unidad_id' => $unidadId1, 'reportado_por_persona_id' => $personaId, 'titulo' => 'Vidrio rajado', 'descripcion' => 'Ventana'], $actorId);
$incVinculable2 = $servicio->reportarIncidencia(['propiedad_id' => $propiedadId, 'unidad_id' => $unidadId1, 'reportado_por_persona_id' => $personaId, 'titulo' => 'Manija rota', 'descripcion' => 'Ventana'], $actorId);
$otVinculada = $servicio->crearOrden([
    'propiedad_id' => $propiedadId,
    'unidad_id' => $unidadId1,
    'tipo' => 'CORRECTIVO',
    'titulo' => 'Reparación integral de ventana',
    'descripcion' => 'Vidrio y cerrajería',
    'tipo_asignacion' => 'INTERNO',
    'colaborador_asignado_id' => $colaboradorId,
    'fecha_programada_inicio' => '2026-11-25',
    'fecha_programada_fin' => '2026-11-26',
    'incidencias_ids' => [$incVinculable1->obtenerId(), $incVinculable2->obtenerId()],
], $actorId);
$inc1Check = $servicio->obtenerIncidencia((int) $incVinculable1->obtenerId());
assertTest(count($otVinculada->obtenerIncidenciasAsociadas()) === 2 && $inc1Check->obtenerEstado() === 'CONVERTIDA_A_ORDEN', 'MNT-18', 'Vinculación N:M: incidencias pasan a CONVERTIDA_A_ORDEN');

// -----------------------------------------------------------------------------
// GRUPO 4: PROGRAMACIÓN Y PROTECCIÓN ANTICIPADA DE INVENTARIO (CASOS 19 A 25)
// -----------------------------------------------------------------------------
echo "\n--- GRUPO 4: Programación y Bloqueo Operativo (MNT-19 a MNT-25) ---\n";

// MNT-19: Programar orden SIN bloqueo no materializa inventario
$otProgSinBloqueo = $servicio->programarOrden((int) $ot1->obtenerId(), [], $actorId);
$stmtCountSin = $pdo->prepare("SELECT COUNT(*) FROM inventario_diario_unidades WHERE origen_id = ? AND origen_tipo = 'MANTENIMIENTO_ORDEN'");
$stmtCountSin->execute([$ot1->obtenerId()]);
assertTest($otProgSinBloqueo->obtenerEstado() === 'PROGRAMADA' && (int) $stmtCountSin->fetchColumn() === 0, 'MNT-19', 'Orden sin bloqueo programada no altera inventario_diario_unidades');

// MNT-20: Crear orden CON bloqueo para fechas específicas
$otConBloqueo = $servicio->crearOrden([
    'propiedad_id' => $propiedadId,
    'unidad_id' => $unidadId1,
    'tipo' => 'CORRECTIVO',
    'prioridad' => 'ALTA',
    'titulo' => 'Cambio de tubería de agua caliente',
    'descripcion' => 'Rotura empotrada en pared',
    'tipo_asignacion' => 'INTERNO',
    'colaborador_asignado_id' => $colaboradorId,
    'requiere_bloqueo' => 1,
    'fecha_programada_inicio' => '2026-12-10',
    'fecha_programada_fin' => '2026-12-14',
    'fecha_bloqueo_inicio' => '2026-12-10',
    'fecha_bloqueo_fin' => '2026-12-14',
], $actorId);
assertTest($otConBloqueo->requiereBloqueo() && $otConBloqueo->obtenerFechaBloqueoInicio() === '2026-12-10', 'MNT-20', 'Orden con requerimiento de bloqueo creada en BORRADOR');

// MNT-21: Programar orden con bloqueo materializa exactamente el intervalo semiabierto [10, 14) -> 4 noches
$otProgConBloqueo = $servicio->programarOrden((int) $otConBloqueo->obtenerId(), [], $actorId);
$stmtNoches = $pdo->prepare("SELECT fecha, tipo_bloqueo, origen_tipo FROM inventario_diario_unidades WHERE origen_id = ? AND origen_tipo = 'MANTENIMIENTO_ORDEN' ORDER BY fecha ASC");
$stmtNoches->execute([$otConBloqueo->obtenerId()]);
$nochesMaterializadas = $stmtNoches->fetchAll(PDO::FETCH_ASSOC);

$fechasEsperadas = ['2026-12-10', '2026-12-11', '2026-12-12', '2026-12-13'];
$fechasReales = array_column($nochesMaterializadas, 'fecha');
assertTest(
    $fechasReales === $fechasEsperadas &&
    $nochesMaterializadas[0]['tipo_bloqueo'] === 'MANTENIMIENTO' &&
    $nochesMaterializadas[0]['origen_tipo'] === 'MANTENIMIENTO_ORDEN',
    'MNT-21',
    'Protección anticipada: Materialización de 4 noches en intervalo semiabierto con tipo MANTENIMIENTO y origen MANTENIMIENTO_ORDEN'
);

// MNT-22: Semántica hotelera: la fecha fin (2026-12-14) queda LIBRE
$stmtNocheFin = $pdo->prepare("SELECT COUNT(*) FROM inventario_diario_unidades WHERE unidad_id = ? AND fecha = '2026-12-14'");
$stmtNocheFin->execute([$unidadId1]);
assertTest((int) $stmtNocheFin->fetchColumn() === 0, 'MNT-22', 'Semántica semiabierta: Fecha fin 2026-12-14 queda disponible para check-in');

// MNT-23: Rechazo de solapamiento: Intentar programar otra orden bloqueante en fecha ocupada arroja ConflictoDisponibilidadExcepcion (409)
$otConflicto = $servicio->crearOrden([
    'propiedad_id' => $propiedadId,
    'unidad_id' => $unidadId1,
    'tipo' => 'CORRECTIVO',
    'titulo' => 'Pintura urgente',
    'descripcion' => 'Reparación de pintura',
    'tipo_asignacion' => 'INTERNO',
    'colaborador_asignado_id' => $colaboradorId,
    'requiere_bloqueo' => 1,
    'fecha_programada_inicio' => '2026-12-12',
    'fecha_programada_fin' => '2026-12-16',
], $actorId);

$conflictoDetectado = false;
try {
    $servicio->programarOrden((int) $otConflicto->obtenerId(), [], $actorId);
} catch (ConflictoDisponibilidadExcepcion $e) {
    $conflictoDetectado = true;
}
assertTest($conflictoDetectado, 'MNT-23', 'Prevención de colisión: ConflictoDisponibilidadExcepcion emitida al intentar programar en fecha ocupada');

// MNT-24: Regla de Responsables (Ajuste 4): Orden con asignación INTERNA exige colaborador al programar
$otSinColab = $servicio->crearOrden([
    'propiedad_id' => $propiedadId,
    'unidad_id' => $unidadId2,
    'titulo' => 'Revisión técnica',
    'descripcion' => 'Revisión sin asignar',
    'tipo_asignacion' => 'INTERNO',
    'fecha_programada_inicio' => '2026-12-20',
    'fecha_programada_fin' => '2026-12-21',
], $actorId);

$errorColab = false;
try {
    $servicio->programarOrden((int) $otSinColab->obtenerId(), [], $actorId);
} catch (ValidacionExcepcion $e) {
    $errorColab = true;
}
assertTest($errorColab, 'MNT-24', 'Validación de responsable: tipo_asignacion INTERNO exige colaborador al programar');

// MNT-25: Regla de Responsables: Orden con asignación EXTERNA exige proveedor al programar
$otSinProv = $servicio->crearOrden([
    'propiedad_id' => $propiedadId,
    'unidad_id' => $unidadId2,
    'titulo' => 'Revisión externa',
    'descripcion' => 'Revisión sin proveedor',
    'tipo_asignacion' => 'EXTERNO',
    'fecha_programada_inicio' => '2026-12-20',
    'fecha_programada_fin' => '2026-12-21',
], $actorId);

$errorProv = false;
try {
    $servicio->programarOrden((int) $otSinProv->obtenerId(), [], $actorId);
} catch (ValidacionExcepcion $e) {
    $errorProv = true;
}
assertTest($errorProv, 'MNT-25', 'Validación de responsable: tipo_asignacion EXTERNO exige proveedor al programar');

// -----------------------------------------------------------------------------
// GRUPO 5: EJECUCIÓN, COSTEO CON BCMATH Y PRÓRROGA (CASOS 26 A 32)
// -----------------------------------------------------------------------------
echo "\n--- GRUPO 5: Ejecución, Costeo con BCMath y Prórroga (MNT-26 a MNT-32) ---\n";

// MNT-26: Iniciar ejecución de trabajos (PROGRAMADA -> EN_PROCESO)
$otIniciada = $servicio->iniciarEjecucion((int) $otProgConBloqueo->obtenerId(), $actorId);
assertTest($otIniciada->obtenerEstado() === 'EN_PROCESO' && $otIniciada->obtenerFechaEjecucionInicio() !== null, 'MNT-26', 'Inicio formal de labores y registro de fecha_ejecucion_inicio');

// MNT-27: Transición inválida: No se puede iniciar una orden en BORRADOR directamente
$errorInicioBorrador = false;
try {
    $servicio->iniciarEjecucion((int) $otConflicto->obtenerId(), $actorId);
} catch (EstadoMantenimientoInvalidoExcepcion $e) {
    $errorInicioBorrador = true;
}
assertTest($errorInicioBorrador, 'MNT-27', 'Rechazo de inicio de ejecución sobre orden en BORRADOR sin previa programación');

// MNT-28: Asentar costos reales con BCMath
$otCostos = $servicio->registrarCostos((int) $otIniciada->obtenerId(), '85.50', '120.25', $actorId);
assertTest($otCostos->obtenerCostoMateriales() === '85.50' && $otCostos->obtenerCostoManoObra() === '120.25' && $otCostos->obtenerCostoTotal() === '205.75', 'MNT-28', 'Cálculo aritmético de costo total (85.50 + 120.25 = 205.75) con BCMath');

// MNT-29: Rechazo de costos negativos
$errorCostoNegativo = false;
try {
    $servicio->registrarCostos((int) $otIniciada->obtenerId(), '-10.00', '50.00', $actorId);
} catch (ValidacionExcepcion $e) {
    $errorCostoNegativo = true;
}
assertTest($errorCostoNegativo, 'MNT-29', 'Validación: Rechazo de costos negativos en materiales o mano de obra');

// MNT-30: Prorrogar bloqueo operativo: Extender del 2026-12-14 al 2026-12-16 (+ 2 noches: 14 y 15)
$otProrrogada = $servicio->prorrogarBloqueo((int) $otIniciada->obtenerId(), '2026-12-16', $actorId);
$stmtNochesProrroga = $pdo->prepare("SELECT fecha FROM inventario_diario_unidades WHERE origen_id = ? ORDER BY fecha ASC");
$stmtNochesProrroga->execute([$otIniciada->obtenerId()]);
$fechasProrrogadas = $stmtNochesProrroga->fetchAll(PDO::FETCH_COLUMN);
$esperadasProrroga = ['2026-12-10', '2026-12-11', '2026-12-12', '2026-12-13', '2026-12-14', '2026-12-15'];
assertTest($fechasProrrogadas === $esperadasProrroga && $otProrrogada->obtenerFechaBloqueoFin() === '2026-12-16', 'MNT-30', 'Prórroga de inhabilitación: 2 noches adicionales materializadas [10, 16)');

// MNT-31: Rechazo de prórroga con fecha fin anterior o igual
$errorProrrogaInvalida = false;
try {
    $servicio->prorrogarBloqueo((int) $otIniciada->obtenerId(), '2026-12-15', $actorId);
} catch (ValidacionExcepcion $e) {
    $errorProrrogaInvalida = true;
}
assertTest($errorProrrogaInvalida, 'MNT-31', 'Validación de prórroga: nueva fecha fin debe ser estrictamente posterior');

// MNT-32: Rechazo de prórroga en orden que no requiere bloqueo
$errorProrrogaSinBloq = false;
try {
    $servicio->prorrogarBloqueo((int) $otProgSinBloqueo->obtenerId(), '2026-11-15', $actorId);
} catch (ValidacionExcepcion $e) {
    $errorProrrogaSinBloq = true;
}
assertTest($errorProrrogaSinBloq, 'MNT-32', 'Rechazo de prórroga sobre orden sin requerimiento de bloqueo');

// -----------------------------------------------------------------------------
// GRUPO 6: CULMINACIÓN, CANCELACIÓN Y PRESERVACIÓN HISTÓRICA (CASOS 33 A 38)
// -----------------------------------------------------------------------------
echo "\n--- GRUPO 6: Culminación, Cancelación y Preservación Histórica (MNT-33 a MNT-38) ---\n";

// MNT-33: Completar orden exige notas de cierre
$errorNotasCierre = false;
try {
    $servicio->completarOrden((int) $otIniciada->obtenerId(), 'abc', null, $actorId);
} catch (ValidacionExcepcion $e) {
    $errorNotasCierre = true;
}
assertTest($errorNotasCierre, 'MNT-33', 'Validación: Completar orden exige notas de cierre de al menos 5 caracteres');

// MNT-34: Completar orden con fecha de liberación futura libera noches futuras y preserva pasadas (Ajuste 6)
// Simulamos que la orden tenía noches pasadas simulando fecha efectiva 2026-12-12
$otCompletada = $servicio->completarOrden((int) $otIniciada->obtenerId(), 'Trabajo culminado y probado con presión de red', '2026-12-12', $actorId);
$stmtNochesRestantes = $pdo->prepare("SELECT fecha FROM inventario_diario_unidades WHERE origen_id = ? ORDER BY fecha ASC");
$stmtNochesRestantes->execute([$otIniciada->obtenerId()]);
$nochesConservadas = $stmtNochesRestantes->fetchAll(PDO::FETCH_COLUMN);
// Deben conservarse 2026-12-10 y 2026-12-11 (< 2026-12-12); las noches >= 2026-12-12 se liberaron
assertTest(
    $otCompletada->obtenerEstado() === 'COMPLETADA' &&
    $nochesConservadas === ['2026-12-10', '2026-12-11'],
    'MNT-34',
    'Preservación histórica en culminación: Noches pasadas [10, 11] se preservan; noches futuras [12..15] se liberan'
);

// MNT-35: Cancelar orden en BORRADOR
$otBorrar = $servicio->crearOrden([
    'propiedad_id' => $propiedadId,
    'unidad_id' => $unidadId2,
    'titulo' => 'Revisión descartada',
    'descripcion' => 'Descartada',
    'tipo_asignacion' => 'INTERNO',
    'fecha_programada_inicio' => '2026-12-25',
    'fecha_programada_fin' => '2026-12-26',
], $actorId);
$otCanceladaBorrador = $servicio->cancelarOrden((int) $otBorrar->obtenerId(), 'Ya no se requiere la revisión', $actorId);
assertTest($otCanceladaBorrador->obtenerEstado() === 'CANCELADA', 'MNT-35', 'Cancelación de orden en BORRADOR con motivo justificado');

// MNT-36: Cancelar orden PROGRAMADA con bloqueo libera todas sus noches si no ha iniciado
$otParaCancelar = $servicio->crearOrden([
    'propiedad_id' => $propiedadId,
    'unidad_id' => $unidadId2,
    'tipo' => 'CORRECTIVO',
    'prioridad' => 'ALTA',
    'titulo' => 'Reparación a cancelar',
    'descripcion' => 'Se cancelará',
    'tipo_asignacion' => 'INTERNO',
    'colaborador_asignado_id' => $colaboradorId,
    'requiere_bloqueo' => 1,
    'fecha_programada_inicio' => '2027-01-10',
    'fecha_programada_fin' => '2027-01-13',
], $actorId);
$servicio->programarOrden((int) $otParaCancelar->obtenerId(), [], $actorId);
$otCanceladaProg = $servicio->cancelarOrden((int) $otParaCancelar->obtenerId(), 'Cancelado por reprogramación general', $actorId);

$stmtNochesCanc = $pdo->prepare("SELECT COUNT(*) FROM inventario_diario_unidades WHERE origen_id = ?");
$stmtNochesCanc->execute([$otParaCancelar->obtenerId()]);
assertTest($otCanceladaProg->obtenerEstado() === 'CANCELADA' && (int) $stmtNochesCanc->fetchColumn() === 0, 'MNT-36', 'Cancelación de orden PROGRAMADA libera atómicamente el 100% de noches bloqueadas');

// MNT-37: Rechazo de cancelación sin motivo
$errorSinMotivo = false;
try {
    $servicio->cancelarOrden((int) $otPrev->obtenerId(), 'abc', $actorId);
} catch (ValidacionExcepcion $e) {
    $errorSinMotivo = true;
}
assertTest($errorSinMotivo, 'MNT-37', 'Validación: Cancelación exige motivo de al menos 5 caracteres');

// MNT-38: Rechazo de cancelación sobre orden ya COMPLETADA
$errorCancCompletada = false;
try {
    $servicio->cancelarOrden((int) $otCompletada->obtenerId(), 'Motivo tardío de cancelación', $actorId);
} catch (EstadoMantenimientoInvalidoExcepcion $e) {
    $errorCancCompletada = true;
}
assertTest($errorCancCompletada, 'MNT-38', 'Invariante: No se puede cancelar una orden ya COMPLETADA');

// -----------------------------------------------------------------------------
// GRUPO 7: AUDITORÍA INMUTABLE Y ESTADÍSTICAS (CASOS 39 A 40)
// -----------------------------------------------------------------------------
echo "\n--- GRUPO 7: Auditoría y Métricas (MNT-39 a MNT-40) ---\n";

// MNT-39: Historial inmutable de estados (D-061 / D-077)
$historialOt = $servicio->obtenerHistorial('ORDEN_TRABAJO', (int) $otIniciada->obtenerId());
$transiciones = array_map(static fn($h) => $h->obtenerEstadoAnterior() . '->' . $h->obtenerEstadoNuevo(), $historialOt);
// Esperado: BORRADOR->BORRADOR, BORRADOR->PROGRAMADA, PROGRAMADA->EN_PROCESO, EN_PROCESO->COMPLETADA
assertTest(count($historialOt) >= 4 && in_array('EN_PROCESO->COMPLETADA', $transiciones, true), 'MNT-39', 'Trazabilidad inmutable de estados registrada en mantenimiento_historial_estados');

// MNT-40: Consulta de estadísticas y métricas para panel
$stats = $servicio->obtenerEstadisticas();
assertTest(
    isset($stats['incidencias_abiertas'], $stats['ordenes_en_proceso'], $stats['unidades_bloqueadas'], $stats['preventivos_mes']) &&
    is_int($stats['incidencias_abiertas']),
    'MNT-40',
    'Generación correcta de métricas e indicadores de mantenimiento'
);

echo "\n=====================================================================\n";
echo "RESULTADOS SUITE MANTENIMIENTO-1 (MATRIZ 40 CASOS):\n";
echo "Total Pruebas: {$totalPruebas}\n";
echo "Exitosas:      {$pruebasExitosas}\n";
echo "Fallidas:      " . count($errores) . "\n";
echo "=====================================================================\n";

if (count($errores) > 0) {
    echo "\nERRORES DETECTADOS:\n";
    foreach ($errores as $err) {
        echo "  - {$err}\n";
    }
    exit(1);
}

exit(0);
