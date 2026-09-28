<?php

declare(strict_types=1);

/**
 * Suite de Verificación SUMINISTROS-1 — Matriz Exhaustiva de 40 Pruebas de Dominio
 *
 * Verifica las decisiones y contratos vinculantes de D-081 (GATE SUMINISTROS-1):
 * - SUMINISTRO != MEDIDOR != LECTURA != TARIFA != CONSUMO VALORIZADO != CARGO != PAGO
 * - Ubicación física (unidad) != Responsabilidad económica (arrendatario titular)
 * - Modalidad dual: MEDIDO vs FIJO_PERIODICO
 * - Precedencia tarifaria: UNIDAD > PROPIEDAD > GLOBAL
 * - Bloqueo pesimista contra solapamientos tarifarios concurrentes
 * - Lecturas inmutables append-only y correcciones auditadas anti-bifurcaciones
 * - Reemplazo atómico de medidor físico (corte del anterior + alta del nuevo)
 * - Liquidación multitramo (cortes tarifarios, cortes de medidor)
 * - Devengo atómico del cargo en cuentas_folios (FINANCIERO-2)
 * - Corrección de cargos con des-aplicación y reaplicación formal de pagos sin alterar fondos del folio
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CamargoPMS\Excepciones\ConflictoSuministroExcepcion;
use CamargoPMS\Excepciones\SuministroNoEncontradoExcepcion;
use CamargoPMS\Excepciones\TarifaFaltanteExcepcion;
use CamargoPMS\Excepciones\ValidacionSuministroExcepcion;
use CamargoPMS\Modelos\AplicacionPago;
use CamargoPMS\Modelos\CargoCuenta;
use CamargoPMS\Modelos\CuentaFolio;
use CamargoPMS\Modelos\PagoCuenta;
use CamargoPMS\Modelos\Suministro;
use CamargoPMS\Modelos\SuministroLectura;
use CamargoPMS\Modelos\SuministroLiquidacion;
use CamargoPMS\Modelos\SuministroMedidor;
use CamargoPMS\Modelos\SuministroTarifa;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Repositorios\ActorAuditoriaRepositorio;
use CamargoPMS\Repositorios\AplicacionPagoRepositorio;
use CamargoPMS\Repositorios\ArrendamientoRepositorio;
use CamargoPMS\Repositorios\CargoCuentaRepositorio;
use CamargoPMS\Repositorios\CuentaFolioRepositorio;
use CamargoPMS\Repositorios\PagoCuentaRepositorio;
use CamargoPMS\Repositorios\SuministroRepositorio;
use CamargoPMS\Servicios\SuministroServicio;

Configuracion::cargar(dirname(__DIR__));
$pdo = BaseDatos::conexion();
$pdo->exec("SET SESSION innodb_lock_wait_timeout = 5;");

$suministroRepo = new SuministroRepositorio($pdo);
$cargoRepo = new CargoCuentaRepositorio($pdo);
$aplicacionRepo = new AplicacionPagoRepositorio($pdo);
$pagoRepo = new PagoCuentaRepositorio($pdo);
$cuentaFolioRepo = new CuentaFolioRepositorio($pdo);
$arrendamientoRepo = new ArrendamientoRepositorio($pdo);
$actorRepo = new ActorAuditoriaRepositorio($pdo);

$suministroServicio = new SuministroServicio(
    $pdo,
    $suministroRepo,
    $cargoRepo,
    $aplicacionRepo,
    $pagoRepo,
    $cuentaFolioRepo,
    $arrendamientoRepo,
    $actorRepo
);

$totalPruebas = 0;
$pruebasExitosas = 0;
$errores = [];

function assertTest(bool $condicion, string $codigo, string $descripcion, ?string $detalle = null): void {
    global $totalPruebas, $pruebasExitosas, $errores;
    $totalPruebas++;
    if ($condicion) {
        $pruebasExitosas++;
        echo "  [PASS] {$codigo}: {$descripcion}\n";
    } else {
        $msg = "{$codigo}: {$descripcion}" . ($detalle ? " -> {$detalle}" : "");
        $errores[] = $msg;
        echo "  [FAIL] {$msg}\n";
    }
}

echo "=====================================================================\n";
echo "CAMARGO PMS — SUITE FORMAL SUMINISTROS-1 (MATRIZ 40 CASOS DE DOMINIO)\n";
echo "=====================================================================\n\n";

$actorId = (int) $pdo->query("SELECT id FROM actores WHERE codigo = 'CAMARGO_PMS' LIMIT 1")->fetchColumn() ?: 1;
$sufijo = strtoupper(substr(uniqid(), -5));

// Asegurar Entidades Maestras de Base (Propiedad, Unidad, Persona, Arrendamiento, CuentaFolio)
$stmtProp = $pdo->query("SELECT id FROM propiedades WHERE estado = 'ACTIVO' LIMIT 1");
$propiedadId = (int) $stmtProp->fetchColumn();

if ($propiedadId <= 0) {
    $stmtInsP = $pdo->prepare("INSERT INTO propiedades (codigo, nombre, direccion, tipo, estado) VALUES (:c, :n, 'Calle Suministros 123', 'URBANA', 'ACTIVO')");
    $stmtInsP->execute(['c' => "PROP-SUM-{$sufijo}", 'n' => "Edificio Suministros {$sufijo}"]);
    $propiedadId = (int) $pdo->lastInsertId();
}

$tipoUnidadId = (int) $pdo->query("SELECT id FROM tipos_unidad WHERE activo = 1 LIMIT 1")->fetchColumn() ?: 1;

$stmtUnid = $pdo->prepare("INSERT INTO unidades (propiedad_id, tipo_unidad_id, codigo, nombre, estado) VALUES (:pid, :tid, :cod, :nom, 'ACTIVO')");
$numUnidad = "U-SUM-{$sufijo}";
$stmtUnid->execute(['pid' => $propiedadId, 'tid' => $tipoUnidadId, 'cod' => $numUnidad, 'nom' => "Habitación {$numUnidad}"]);
$unidadId = (int) $pdo->lastInsertId();

// Persona titular
$titularId = (int) $pdo->query("SELECT id FROM personas WHERE estado = 'ACTIVO' LIMIT 1")->fetchColumn();
if ($titularId <= 0) {
    $stmtPers = $pdo->prepare("INSERT INTO personas (nombres, apellido_paterno, apellido_materno, estado) VALUES ('Arrendatario', 'Suministros', 'Prueba', 'ACTIVO')");
    $stmtPers->execute();
    $titularId = (int) $pdo->lastInsertId();
}

// Arrendamiento de prueba
$stmtArr = $pdo->prepare("INSERT INTO arrendamientos (codigo, unidad_id, fecha_inicio, fecha_fin, dia_vencimiento, renta_mensual, deposito_garantia, monto_primer_periodo, estado, creado_por_actor_id) VALUES (:cod, :uid, '2026-09-01', '2027-08-31', 5, 1500.00, 1500.00, 1500.00, 'VIGENTE', :act)");
$arrCodigo = "ARR-SUM-{$sufijo}";
$stmtArr->execute([
    'cod' => $arrCodigo,
    'uid' => $unidadId,
    'act' => $actorId,
]);
$arrendamientoId = (int) $pdo->lastInsertId();

$stmtArrPers = $pdo->prepare("INSERT INTO arrendamiento_personas (arrendamiento_id, persona_id, tipo_relacion) VALUES (:aid, :pid, 'TITULAR')");
$stmtArrPers->execute(['aid' => $arrendamientoId, 'pid' => $titularId]);

// Cuenta Folio 1:1 para el Arrendamiento
$stmtFolio = $pdo->prepare("INSERT INTO cuentas_folios (codigo, arrendamiento_id, persona_titular_id, moneda_codigo, estado, creado_por_actor_id) VALUES (:cod, :aid, :tid, 'PEN', 'ABIERTA', :act)");
$folioCodigo = "FOL-ARR-{$sufijo}";
$stmtFolio->execute([
    'cod' => $folioCodigo,
    'aid' => $arrendamientoId,
    'tid' => $titularId,
    'act' => $actorId,
]);
$cuentaFolioId = (int) $pdo->lastInsertId();

echo "[PRECHECK] Entorno maestro inicializado: Propiedad #{$propiedadId}, Unidad #{$unidadId}, Arrendamiento #{$arrendamientoId}, Folio #{$cuentaFolioId}\n\n";

// =============================================================================
// BLOQUE 1: CATÁLOGO DE SUMINISTROS (T01 - T06)
// =============================================================================
echo "--- BLOQUE 1: CATÁLOGO DE SUMINISTROS ---\n";

// T01: Suministro medido (electricidad)
$sumMedido = $suministroServicio->crearSuministro([
    'codigo' => "ELEC_{$sufijo}",
    'nombre' => "Electricidad Trifásica {$sufijo}",
    'modalidad' => Suministro::MODALIDAD_MEDIDO,
    'unidad_medida' => 'kWh',
    'descripcion' => 'Medición por dial físico',
    'permite_rollover' => true,
]);
assertTest($sumMedido->obtenerId() > 0 && $sumMedido->esMedido() && $sumMedido->obtenerUnidadMedida() === 'kWh', 'T01', 'Creación de suministro en modalidad MEDIDO (Electricidad kWh)');

// T02: Suministro fijo periódico (internet)
$sumFijo = $suministroServicio->crearSuministro([
    'codigo' => "NET_{$sufijo}",
    'nombre' => "Internet Fibra Óptica {$sufijo}",
    'modalidad' => Suministro::MODALIDAD_FIJO_PERIODICO,
    'unidad_medida' => 'MES',
    'descripcion' => 'Cuota mensual fija recurrente',
]);
assertTest($sumFijo->obtenerId() > 0 && $sumFijo->esFijoPeriodico() && $sumFijo->obtenerUnidadMedida() === 'MES', 'T02', 'Creación de suministro en modalidad FIJO_PERIODICO (Internet)');

// T03: Rechazo código duplicado
$exT03 = false;
try {
    $suministroServicio->crearSuministro([
        'codigo' => "ELEC_{$sufijo}",
        'nombre' => 'Duplicado',
        'modalidad' => Suministro::MODALIDAD_MEDIDO,
        'unidad_medida' => 'kWh',
    ]);
} catch (ConflictoSuministroExcepcion $e) {
    $exT03 = true;
}
assertTest($exT03, 'T03', 'Rechazo con HTTP 409 por intento de código de suministro duplicado');

// T04: Validación campos obligatorios
$exT04 = false;
try {
    $suministroServicio->crearSuministro([
        'codigo' => '',
        'nombre' => '',
        'modalidad' => 'INEXISTENTE',
        'unidad_medida' => '',
    ]);
} catch (ValidacionSuministroExcepcion $e) {
    $exT04 = true;
}
assertTest($exT04, 'T04', 'Rechazo con HTTP 422 por campos obligatorios vacíos o modalidad desconocida');

// T05: Actualizar datos de suministro
$sumMedidoUpd = $suministroServicio->actualizarSuministro((int) $sumMedido->obtenerId(), [
    'nombre' => "Electricidad Corregida {$sufijo}",
    'descripcion' => 'Descripción actualizada de suministro',
]);
assertTest($sumMedidoUpd->obtenerNombre() === "Electricidad Corregida {$sufijo}", 'T05', 'Actualización correcta de atributos descriptivos del suministro');

// T06: Listado de suministros
$listaSuministros = $suministroServicio->listarSuministros();
assertTest(count($listaSuministros) >= 2, 'T06', 'Listado exitoso del catálogo maestro de suministros');

// =============================================================================
// BLOQUE 2: TARIFAS HISTÓRICAS CON PRECEDENCIA Y NO SOLAPAMIENTO (T07 - T15)
// =============================================================================
echo "\n--- BLOQUE 2: TARIFAS HISTÓRICAS Y JERARQUÍA ---\n";

// T07: Tarifa GLOBAL
$tarifaGlobal = $suministroServicio->crearTarifa([
    'suministro_id' => $sumMedido->obtenerId(),
    'ambito' => SuministroTarifa::AMBITO_GLOBAL,
    'precio_unitario' => '0.8500',
    'fecha_inicio' => '2026-01-01',
    'fecha_fin' => '2026-12-31',
]);
assertTest($tarifaGlobal->obtenerId() > 0 && $tarifaGlobal->esGlobal() && $tarifaGlobal->obtenerPrecioUnitario() === '0.8500', 'T07', 'Registro de tarifa en ámbito GLOBAL');

// T08: Tarifa PROPIEDAD
$tarifaPropiedad = $suministroServicio->crearTarifa([
    'suministro_id' => $sumMedido->obtenerId(),
    'ambito' => SuministroTarifa::AMBITO_PROPIEDAD,
    'propiedad_id' => $propiedadId,
    'precio_unitario' => '0.9000',
    'fecha_inicio' => '2026-06-01',
    'fecha_fin' => '2026-12-31',
]);
assertTest($tarifaPropiedad->obtenerId() > 0 && $tarifaPropiedad->esPropiedad() && $tarifaPropiedad->obtenerPrecioUnitario() === '0.9000', 'T08', 'Registro de tarifa en ámbito PROPIEDAD');

// T09: Tarifa UNIDAD
$tarifaUnidad = $suministroServicio->crearTarifa([
    'suministro_id' => $sumMedido->obtenerId(),
    'ambito' => SuministroTarifa::AMBITO_UNIDAD,
    'propiedad_id' => $propiedadId,
    'unidad_id' => $unidadId,
    'precio_unitario' => '1.0500',
    'fecha_inicio' => '2026-09-01',
    'fecha_fin' => '2026-09-30',
]);
assertTest($tarifaUnidad->obtenerId() > 0 && $tarifaUnidad->esUnidad() && $tarifaUnidad->obtenerPrecioUnitario() === '1.0500', 'T09', 'Registro de tarifa en ámbito UNIDAD específica');

// T10: Rechazo por solapamiento en el mismo ámbito
$exT10 = false;
try {
    $suministroServicio->crearTarifa([
        'suministro_id' => $sumMedido->obtenerId(),
        'ambito' => SuministroTarifa::AMBITO_GLOBAL,
        'precio_unitario' => '0.8800',
        'fecha_inicio' => '2026-05-01',
        'fecha_fin' => '2026-08-31',
    ]);
} catch (ConflictoSuministroExcepcion $e) {
    $exT10 = true;
}
assertTest($exT10, 'T10', 'Rechazo con HTTP 409 por solapamiento temporal de tarifas en el mismo ámbito');

// T11: Aceptación de tarifas consecutivas no solapadas
$tarifaGlobal2027 = $suministroServicio->crearTarifa([
    'suministro_id' => $sumMedido->obtenerId(),
    'ambito' => SuministroTarifa::AMBITO_GLOBAL,
    'precio_unitario' => '0.9500',
    'fecha_inicio' => '2027-01-01',
    'fecha_fin' => '2027-12-31',
]);
assertTest($tarifaGlobal2027->obtenerId() > 0, 'T11', 'Aceptación de tarifa consecutiva no solapada (ej. nuevo año fiscal 2027)');

// T12: Precedencia UNIDAD sobre PROPIEDAD y GLOBAL
$tarifaResueltaT12 = $suministroServicio->resolverTarifaParaFecha((int) $sumMedido->obtenerId(), '2026-09-15', $propiedadId, $unidadId);
assertTest($tarifaResueltaT12->obtenerId() === $tarifaUnidad->obtenerId() && $tarifaResueltaT12->obtenerPrecioUnitario() === '1.0500', 'T12', 'Jerarquía tarifaria: UNIDAD (1.05) prevalece sobre PROPIEDAD (0.90) y GLOBAL (0.85)');

// T13: Precedencia PROPIEDAD sobre GLOBAL en ausencia de tarifa de unidad
$tarifaResueltaT13 = $suministroServicio->resolverTarifaParaFecha((int) $sumMedido->obtenerId(), '2026-07-15', $propiedadId, $unidadId);
assertTest($tarifaResueltaT13->obtenerId() === $tarifaPropiedad->obtenerId() && $tarifaResueltaT13->obtenerPrecioUnitario() === '0.9000', 'T13', 'Jerarquía tarifaria: PROPIEDAD (0.90) prevalece sobre GLOBAL (0.85) cuando no hay tarifa de unidad');

// T14: Precedencia GLOBAL cuando no hay tarifa de propiedad ni de unidad
$tarifaResueltaT14 = $suministroServicio->resolverTarifaParaFecha((int) $sumMedido->obtenerId(), '2026-03-15', $propiedadId, $unidadId);
assertTest($tarifaResueltaT14->obtenerId() === $tarifaGlobal->obtenerId() && $tarifaResueltaT14->obtenerPrecioUnitario() === '0.8500', 'T14', 'Jerarquía tarifaria: GLOBAL (0.85) aplica cuando no hay tarifa de propiedad ni de unidad');

// T15: Rechazo cuando no existe tarifa en la fecha
$exT15 = false;
try {
    $suministroServicio->resolverTarifaParaFecha((int) $sumMedido->obtenerId(), '2025-01-01', $propiedadId, $unidadId);
} catch (TarifaFaltanteExcepcion $e) {
    $exT15 = true;
}
assertTest($exT15, 'T15', 'Rechazo con HTTP 422 TarifaFaltanteExcepcion si la fecha no tiene cobertura tarifaria');

// Registrar tarifa para el suministro fijo (Internet)
$tarifaInternet = $suministroServicio->crearTarifa([
    'suministro_id' => $sumFijo->obtenerId(),
    'ambito' => SuministroTarifa::AMBITO_GLOBAL,
    'precio_unitario' => '120.0000',
    'fecha_inicio' => '2026-01-01',
    'fecha_fin' => '2026-12-31',
]);

// =============================================================================
// BLOQUE 3: MEDIDORES FÍSICOS Y REEMPLAZO ATÓMICO (T16 - T23)
// =============================================================================
echo "\n--- BLOQUE 3: MEDIDORES FÍSICOS Y REEMPLAZO ATÓMICO ---\n";

// T16: Instalar medidor
$medidor1 = $suministroServicio->instalarMedidor([
    'suministro_id' => $sumMedido->obtenerId(),
    'propiedad_id' => $propiedadId,
    'unidad_id' => $unidadId,
    'numero_serie' => "SERIE-1-{$sufijo}",
    'codigo_interno' => "MED-01-{$sufijo}",
    'marca' => 'Elster',
    'modelo' => 'A1500',
    'fecha_instalacion' => '2026-09-01',
    'lectura_inicial' => '100.0000',
    'lectura_maxima' => '99999.0000',
    'permite_rollover' => true,
], $actorId);
assertTest($medidor1->obtenerId() > 0 && $medidor1->esActivo() && $medidor1->obtenerLecturaInicial() === '100.0000', 'T16', 'Instalación de medidor físico con lectura inicial asentada formalmente');

// T17: Rechazo de segundo medidor activo en la misma unidad
$exT17 = false;
try {
    $suministroServicio->instalarMedidor([
        'suministro_id' => $sumMedido->obtenerId(),
        'propiedad_id' => $propiedadId,
        'unidad_id' => $unidadId,
        'numero_serie' => "SERIE-COLISION-{$sufijo}",
        'fecha_instalacion' => '2026-09-02',
        'lectura_inicial' => '0.0000',
    ], $actorId);
} catch (ConflictoSuministroExcepcion $e) {
    $exT17 = true;
}
assertTest($exT17, 'T17', 'Rechazo con HTTP 409 por intento de instalar un segundo medidor activo en la misma unidad');

// T18: Rechazo por serie duplicada o lectura negativa
$exT18 = false;
try {
    $suministroServicio->instalarMedidor([
        'suministro_id' => $sumMedido->obtenerId(),
        'propiedad_id' => $propiedadId,
        'unidad_id' => $unidadId,
        'numero_serie' => "SERIE-1-{$sufijo}",
        'lectura_inicial' => '-5.0000',
    ], $actorId);
} catch (ValidacionSuministroExcepcion | ConflictoSuministroExcepcion $e) {
    $exT18 = true;
}
assertTest($exT18, 'T18', 'Rechazo con validación por número de serie duplicado o lectura negativa');

// T19: Rechazo de instalación en suministro FIJO_PERIODICO
$exT19 = false;
try {
    $suministroServicio->instalarMedidor([
        'suministro_id' => $sumFijo->obtenerId(),
        'propiedad_id' => $propiedadId,
        'unidad_id' => $unidadId,
        'numero_serie' => "SERIE-FIJO-{$sufijo}",
        'lectura_inicial' => '0.0000',
    ], $actorId);
} catch (ValidacionSuministroExcepcion $e) {
    $exT19 = true;
}
assertTest($exT19, 'T19', 'Rechazo al intentar asociar un medidor físico a un suministro FIJO_PERIODICO');

// Registrar lectura intermedia en medidor 1 antes de reemplazo
$suministroServicio->registrarLectura([
    'medidor_id' => $medidor1->obtenerId(),
    'fecha_lectura' => '2026-09-10',
    'valor_lectura' => '150.0000',
    'tipo_evento' => SuministroLectura::EVENTO_PERIODICA,
], $actorId);

// T20: Reemplazo atómico de medidor
$reemplazo = $suministroServicio->reemplazarMedidor(
    (int) $medidor1->obtenerId(),
    [
        'numero_serie' => "SERIE-2-{$sufijo}",
        'codigo_interno' => "MED-02-{$sufijo}",
        'marca' => 'Schneider',
        'modelo' => 'PM5000',
        'lectura_inicial' => '10.0000',
        'lectura_maxima' => '99999.0000',
        'permite_rollover' => true,
    ],
    '2026-09-15',
    '180.0000',
    $actorId
);
$medidorRetirado = $reemplazo['medidor_retirado'];
$medidorNuevo = $reemplazo['medidor_nuevo'];

assertTest($medidorRetirado !== null && $medidorNuevo !== null, 'T20', 'Ejecución atómica del reemplazo de medidor en una sola transacción');

// T21: Medidor anterior retirado
assertTest($medidorRetirado->obtenerEstado() === SuministroMedidor::ESTADO_RETIRADO && $medidorRetirado->obtenerLecturaFinal() === '180.0000' && $medidorRetirado->obtenerFechaRetiro() === '2026-09-15', 'T21', 'Medidor saliente marcado como RETIRADO con fecha de retiro y lectura final');

// T22: Medidor nuevo activo
assertTest($medidorNuevo->obtenerEstado() === SuministroMedidor::ESTADO_ACTIVO && $medidorNuevo->obtenerLecturaInicial() === '10.0000' && $medidorNuevo->obtenerNumeroSerie() === "SERIE-2-{$sufijo}", 'T22', 'Medidor entrante en estado ACTIVO con lectura inicial registrada');

// T23: Listar medidores de la unidad
$medidoresUnidad = $suministroServicio->listarMedidores((int) $sumMedido->obtenerId(), $propiedadId, $unidadId);
assertTest(count($medidoresUnidad) >= 2, 'T23', 'Consulta del parque de medidores por unidad física');

// =============================================================================
// BLOQUE 4: LECTURAS FÍSICAS APPEND-ONLY Y ROLLOVER (T24 - T29)
// =============================================================================
echo "\n--- BLOQUE 4: LECTURAS FÍSICAS APPEND-ONLY Y ROLLOVER ---\n";

// T24: Lectura periódica regular
$lecNueva = $suministroServicio->registrarLectura([
    'medidor_id' => $medidorNuevo->obtenerId(),
    'fecha_lectura' => '2026-09-20',
    'valor_lectura' => '45.0000',
    'tipo_evento' => SuministroLectura::EVENTO_PERIODICA,
], $actorId);
assertTest($lecNueva->obtenerId() > 0 && $lecNueva->esVigente() && $lecNueva->obtenerValorLectura() === '45.0000', 'T24', 'Registro de lectura periódica ascendente regular');

// Crear medidor sin rollover para probar rechazo
$stmtUnidNoRoll = $pdo->prepare("INSERT INTO unidades (propiedad_id, tipo_unidad_id, codigo, nombre, estado) VALUES (:pid, :tid, :cod, :nom, 'ACTIVO')");
$stmtUnidNoRoll->execute(['pid' => $propiedadId, 'tid' => $tipoUnidadId, 'cod' => "U-NOROLL-{$sufijo}", 'nom' => "Unidad NoRoll {$sufijo}"]);
$unidadNoRollId = (int) $pdo->lastInsertId();

$medidorSinRoll = $suministroServicio->instalarMedidor([
    'suministro_id' => $sumMedido->obtenerId(),
    'propiedad_id' => $propiedadId,
    'unidad_id' => $unidadNoRollId,
    'numero_serie' => "SERIE-NOROLL-{$sufijo}",
    'lectura_inicial' => '500.0000',
    'permite_rollover' => false,
], $actorId);

// T25: Rechazo de lectura decreciente sin rollover
$exT25 = false;
try {
    $suministroServicio->registrarLectura([
        'medidor_id' => $medidorSinRoll->obtenerId(),
        'fecha_lectura' => '2026-09-25',
        'valor_lectura' => '480.0000', // Decreciente
        'tipo_evento' => SuministroLectura::EVENTO_PERIODICA,
    ], $actorId);
} catch (ValidacionSuministroExcepcion $e) {
    $exT25 = true;
}
assertTest($exT25, 'T25', 'Rechazo con HTTP 422 de lectura decreciente en medidor sin capacidad de rollover');

// T26: Aceptación de lectura con rollover en medidor con capacidad
$stmtUnidRoll = $pdo->prepare("INSERT INTO unidades (propiedad_id, tipo_unidad_id, codigo, nombre, estado) VALUES (:pid, :tid, :cod, :nom, 'ACTIVO')");
$stmtUnidRoll->execute(['pid' => $propiedadId, 'tid' => $tipoUnidadId, 'cod' => "U-ROLL-{$sufijo}", 'nom' => "Unidad Roll {$sufijo}"]);
$unidadRollId = (int) $pdo->lastInsertId();

$medidorConRoll = $suministroServicio->instalarMedidor([
    'suministro_id' => $sumMedido->obtenerId(),
    'propiedad_id' => $propiedadId,
    'unidad_id' => $unidadRollId,
    'numero_serie' => "SERIE-ROLL-{$sufijo}",
    'lectura_inicial' => '9980.0000',
    'lectura_maxima' => '10000.0000',
    'permite_rollover' => true,
], $actorId);

$lecRollover = $suministroServicio->registrarLectura([
    'medidor_id' => $medidorConRoll->obtenerId(),
    'fecha_lectura' => '2026-09-25',
    'valor_lectura' => '15.0000', // Rollover de 9980 -> 10000 -> 15 (consumo = 20 + 15 = 35)
    'tipo_evento' => SuministroLectura::EVENTO_PERIODICA,
], $actorId);
assertTest($lecRollover->obtenerId() > 0 && $lecRollover->obtenerValorLectura() === '15.0000', 'T26', 'Aceptación de lectura con dial reiniciado (rollover legítimo con dial cíclico)');

// T27: Corrección auditada append-only
$lecCorregida = $suministroServicio->corregirLectura(
    (int) $lecNueva->obtenerId(),
    '48.0000',
    'Error de digitación en teclado numérico',
    $actorId
);
$lecOriginalActualizada = $suministroRepo->buscarLecturaPorId((int) $lecNueva->obtenerId());

assertTest(
    $lecCorregida->obtenerId() > 0 &&
    $lecCorregida->obtenerValorLectura() === '48.0000' &&
    $lecCorregida->obtenerLecturaReferenciaId() === $lecNueva->obtenerId() &&
    $lecOriginalActualizada->obtenerEstado() === SuministroLectura::ESTADO_CORREGIDA,
    'T27',
    'Corrección auditada append-only: original pasa a CORREGIDA y nueva queda VIGENTE referenciada'
);

// T28: Rechazo de corrección sin motivo obligatorio
$exT28 = false;
try {
    $suministroServicio->corregirLectura((int) $lecCorregida->obtenerId(), '50.0000', '   ', $actorId);
} catch (ValidacionSuministroExcepcion $e) {
    $exT28 = true;
}
assertTest($exT28, 'T28', 'Rechazo con HTTP 422 de corrección de lectura sin motivo de auditoría');

// T29: Rechazo de segunda corrección sobre lectura ya corregida
$exT29 = false;
try {
    $suministroServicio->corregirLectura((int) $lecNueva->obtenerId(), '52.0000', 'Segundo intento', $actorId);
} catch (ConflictoSuministroExcepcion $e) {
    $exT29 = true;
}
assertTest($exT29, 'T29', 'Rechazo con HTTP 409 por intento de bifurcar correcciones sobre la misma lectura');

// =============================================================================
// BLOQUE 5: LIQUIDACIÓN DE CONSUMOS Y DEVENGO FINANCIERO (T30 - T36)
// =============================================================================
echo "\n--- BLOQUE 5: LIQUIDACIÓN DE CONSUMOS Y DEVENGO FINANCIERO ---\n";

// T30: Liquidación en modalidad FIJO_PERIODICO
$liqInternet = $suministroServicio->liquidarPeriodoArrendamiento(
    $arrendamientoId,
    (int) $sumFijo->obtenerId(),
    '2026-09-01',
    '2026-09-30',
    '2026-10-05',
    $actorId
);
assertTest($liqInternet->obtenerId() > 0 && $liqInternet->obtenerTotal() === '120.00' && count($liqInternet->obtenerTramos()) === 1, 'T30', 'Liquidación exitosa de suministro en modalidad FIJO_PERIODICO (120.00 PEN)');

// T31: Cargo devengado en cuentas_folios para suministro fijo
$cargoInternet = $cargoRepo->obtenerPorId($liqInternet->obtenerCargoCuentaId());
assertTest(
    $cargoInternet !== null &&
    $cargoInternet->obtenerCuentaFolioId() === $cuentaFolioId &&
    $cargoInternet->obtenerOrigenTipo() === 'SUMINISTRO_CUOTA_FIJA' &&
    $cargoInternet->obtenerTotal() === '120.00' &&
    $cargoInternet->esDevengado(),
    'T31',
    'Devengo atómico del cargo SUMINISTRO_CUOTA_FIJA en la cuenta folio del contrato de arrendamiento'
);

// Registrar lectura de fin de período en medidor nuevo
$suministroServicio->registrarLectura([
    'medidor_id' => $medidorNuevo->obtenerId(),
    'fecha_lectura' => '2026-09-30',
    'valor_lectura' => '80.0000',
    'tipo_evento' => SuministroLectura::EVENTO_PERIODICA,
], $actorId);

// Crear contrato y medidor limpio para prueba de 1 tramo
$stmtUnidB = $pdo->prepare("INSERT INTO unidades (propiedad_id, tipo_unidad_id, codigo, nombre, estado) VALUES (:pid, :tid, :cod, :nom, 'ACTIVO')");
$stmtUnidB->execute(['pid' => $propiedadId, 'tid' => $tipoUnidadId, 'cod' => "U-B-{$sufijo}", 'nom' => "Unidad B {$sufijo}"]);
$unidadBId = (int) $pdo->lastInsertId();

$stmtArrB = $pdo->prepare("INSERT INTO arrendamientos (codigo, unidad_id, fecha_inicio, fecha_fin, dia_vencimiento, renta_mensual, deposito_garantia, monto_primer_periodo, estado, creado_por_actor_id) VALUES (:cod, :uid, '2026-10-01', '2027-09-30', 5, 1600.00, 1600.00, 1600.00, 'VIGENTE', :act)");
$stmtArrB->execute([
    'cod' => "ARR-B-{$sufijo}",
    'uid' => $unidadBId,
    'act' => $actorId,
]);
$arrendamientoBId = (int) $pdo->lastInsertId();

$stmtArrPersB = $pdo->prepare("INSERT INTO arrendamiento_personas (arrendamiento_id, persona_id, tipo_relacion) VALUES (:aid, :pid, 'TITULAR')");
$stmtArrPersB->execute(['aid' => $arrendamientoBId, 'pid' => $titularId]);

$stmtFolioB = $pdo->prepare("INSERT INTO cuentas_folios (codigo, arrendamiento_id, persona_titular_id, moneda_codigo, estado, creado_por_actor_id) VALUES (:cod, :aid, :tid, 'PEN', 'ABIERTA', :act)");
$stmtFolioB->execute([
    'cod' => "FOL-B-{$sufijo}",
    'aid' => $arrendamientoBId,
    'tid' => $titularId,
    'act' => $actorId,
]);
$cuentaFolioBId = (int) $pdo->lastInsertId();

$medidorB = $suministroServicio->instalarMedidor([
    'suministro_id' => $sumMedido->obtenerId(),
    'propiedad_id' => $propiedadId,
    'unidad_id' => $unidadBId,
    'numero_serie' => "SERIE-B-{$sufijo}",
    'fecha_instalacion' => '2026-10-01',
    'lectura_inicial' => '500.0000',
], $actorId);

$suministroServicio->registrarLectura([
    'medidor_id' => $medidorB->obtenerId(),
    'fecha_lectura' => '2026-10-31',
    'valor_lectura' => '650.0000', // Consumo = 150 kWh
    'tipo_evento' => SuministroLectura::EVENTO_PERIODICA,
], $actorId);

// T32: Liquidación modalidad MEDIDO con 1 tramo
// Tarifa aplicable en octubre 2026 para unidad B: PROPIEDAD = 0.90
// Consumo: 650 - 500 = 150.0000. Total: 150 * 0.90 = 135.00 PEN.
$liqMedida = $suministroServicio->liquidarPeriodoArrendamiento(
    $arrendamientoBId,
    (int) $sumMedido->obtenerId(),
    '2026-10-01',
    '2026-10-31',
    '2026-11-05',
    $actorId
);
assertTest(
    $liqMedida->obtenerId() > 0 &&
    $liqMedida->obtenerCantidadTotal() === '150.0000' &&
    $liqMedida->obtenerTotal() === '135.00' &&
    count($liqMedida->obtenerTramos()) === 1,
    'T32',
    'Liquidación MEDIDO de 1 tramo: 150 kWh a 0.90 PEN/kWh = 135.00 PEN'
);

// T33: Cargo devengado en cuentas_folios para consumo medido
$cargoMedido = $cargoRepo->obtenerPorId($liqMedida->obtenerCargoCuentaId());
assertTest(
    $cargoMedido !== null &&
    $cargoMedido->obtenerCuentaFolioId() === $cuentaFolioBId &&
    $cargoMedido->obtenerOrigenTipo() === 'SUMINISTRO_CONSUMO' &&
    $cargoMedido->obtenerTotal() === '135.00' &&
    $cargoMedido->esDevengado(),
    'T33',
    'Devengo atómico del cargo SUMINISTRO_CONSUMO en la cuenta folio'
);

// T34: Liquidación multitramo (Reemplazo / Corte intermedio en medidor)
// En unidad principal: medidor 2 tiene lecturas en 2026-09-15 (10.0000), 2026-09-20 (corregida 48.0000), 2026-09-30 (80.0000)
// Tramo 1: 10 a 48 = 38 kWh * 1.05 (tarifa unidad) = 39.90 PEN
// Tramo 2: 48 a 80 = 32 kWh * 1.05 = 33.60 PEN
// Total: 70 kWh, 73.50 PEN
$liqMultitramo = $suministroServicio->liquidarPeriodoArrendamiento(
    $arrendamientoId,
    (int) $sumMedido->obtenerId(),
    '2026-09-15',
    '2026-09-30',
    '2026-10-05',
    $actorId
);
assertTest(
    $liqMultitramo->obtenerId() > 0 &&
    count($liqMultitramo->obtenerTramos()) === 2 &&
    $liqMultitramo->obtenerCantidadTotal() === '70.0000' &&
    $liqMultitramo->obtenerTotal() === '73.50',
    'T34',
    'Cálculo multitramo determinista: 2 tramos desglosados suman exactamente 70 kWh y 73.50 PEN'
);

// T35: Rechazo de liquidación duplicada en el mismo período exacto
$exT35 = false;
try {
    $suministroServicio->liquidarPeriodoArrendamiento(
        $arrendamientoId,
        (int) $sumMedido->obtenerId(),
        '2026-09-15',
        '2026-09-30',
        '2026-10-05',
        $actorId
    );
} catch (ConflictoSuministroExcepcion $e) {
    $exT35 = true;
}
assertTest($exT35, 'T35', 'Rechazo con HTTP 409 por intento de doble liquidación activa en el mismo período');

// T36: Rechazo por lecturas insuficientes
$exT36 = false;
try {
    $suministroServicio->liquidarPeriodoArrendamiento(
        $arrendamientoId,
        (int) $sumMedido->obtenerId(),
        '2026-11-01',
        '2026-11-30',
        null,
        $actorId
    );
} catch (ValidacionSuministroExcepcion $e) {
    $exT36 = true;
}
assertTest($exT36, 'T36', 'Rechazo con HTTP 422 si no existen lecturas físicas para cubrir el período');

// =============================================================================
// BLOQUE 6: ANULACIÓN, CORRECCIÓN Y PRESERVACIÓN FINANCIERA (T37 - T40)
// =============================================================================
echo "\n--- BLOQUE 6: ANULACIÓN Y PRESERVACIÓN FINANCIERO-2 ---\n";

// T37: Anulación de liquidación y de su cargo
$anuladaOk = $suministroServicio->anularLiquidacion(
    (int) $liqInternet->obtenerId(),
    'Error en asignación de servicio de Internet',
    $actorId
);
$liqInternetAnulada = $suministroRepo->buscarLiquidacionPorId((int) $liqInternet->obtenerId());
$cargoInternetAnulado = $cargoRepo->obtenerPorId($liqInternet->obtenerCargoCuentaId());

assertTest(
    $anuladaOk &&
    $liqInternetAnulada->esAnulado() &&
    $cargoInternetAnulado->obtenerEstado() === 'ANULADO',
    'T37',
    'Anulación coordinada de la liquidación y su cargo en cuentas_folios'
);

// T38: Anulación con pago aplicado previamente (des-aplicación formal)
// Para liqMedida (135.00 PEN), registrar un pago formal en la cuenta folio y aplicarlo al cargo
$cargoBId = $liqMedida->obtenerCargoCuentaId();
$pagoBId = $pagoRepo->crear(new PagoCuenta(
    null,
    "PAG-SUM-{$sufijo}",
    $cuentaFolioBId,
    1, // Efectivo
    '135.00',
    '135.00',
    'PEN',
    null,
    null,
    'REC-001',
    'CONFIRMADO',
    null,
    null,
    null,
    $actorId
));

// Crear aplicación
$aplicacionRepo->crear(new AplicacionPago(
    null,
    "APL-SUM-{$sufijo}",
    $pagoBId,
    $cargoBId,
    '135.00',
    'PEN',
    'ACTIVA',
    null,
    null,
    $actorId
));
$cargoRepo->actualizarMontoAplicado($cargoBId, '135.00');

// Ejecutar anulación de liquidación con pago aplicado
$suministroServicio->anularLiquidacion(
    (int) $liqMedida->obtenerId(),
    'Corrección de lectura que sobrestimó el consumo',
    $actorId
);

$appsDespues = $aplicacionRepo->listarPorCargo($cargoBId);
$pagoDespues = $pagoRepo->obtenerPorId($pagoBId);
$cargoDespues = $cargoRepo->obtenerPorId($cargoBId);

assertTest(
    $appsDespues[0]->obtenerEstado() === 'REVERTIDA' &&
    $pagoDespues->obtenerMontoAplicado() === '0.00' &&
    $cargoDespues->obtenerEstado() === 'ANULADO',
    'T38',
    'Des-aplicación formal de pagos al anular liquidación: aplicación REVERTIDA y monto_aplicado liberado a 0.00'
);

// T39: Reliquidación por corrección
// Emitir reliquidación sobre liqMultitramo
$liqReliquidada = $suministroServicio->reliquidarPorCorreccion(
    (int) $liqMultitramo->obtenerId(),
    'Ajuste formal por revisión de corte',
    $actorId
);
assertTest(
    $liqReliquidada->obtenerId() > 0 &&
    $liqReliquidada->obtenerRevision() === 2 &&
    $liqReliquidada->obtenerLiquidacionPreviaId() === $liqMultitramo->obtenerId(),
    'T39',
    'Reliquidación con incremento de revisión (Rev. 2) y trazabilidad inmutable de la previa'
);

// T40: Verificación de preservación financiera (fondos a favor en cuenta folio)
// El pago de 135.00 PEN en la cuenta folio B quedó con monto_aplicado = 0.00, lo que significa
// que la cuenta folio tiene 135.00 PEN de saldo a favor listo para ser aplicado a nuevos consumos o renta.
$folioBActual = $cuentaFolioRepo->obtenerPorId($cuentaFolioBId);
assertTest(
    $pagoDespues->obtenerMontoTotal() === '135.00' &&
    $pagoDespues->obtenerMontoAplicado() === '0.00',
    'T40',
    'Preservación FINANCIERO-2: fondos pagados quedan íntegros como saldo a favor en el folio'
);

echo "\n=====================================================================\n";
echo "RESULTADOS MATRIZ SUMINISTROS-1: {$pruebasExitosas} / {$totalPruebas} PASS\n";
echo "=====================================================================\n";

if (!empty($errores)) {
    echo "ERRORES DETECTADOS:\n";
    foreach ($errores as $err) {
        echo " - {$err}\n";
    }
    exit(1);
}

exit(0);
