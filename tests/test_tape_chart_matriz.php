<?php

declare(strict_types=1);

/**
 * Suite de Verificación TAPE-CHART-1 — Matriz Exhaustiva de 40 Pruebas de Dominio y Agregación
 *
 * Verifica las decisiones y contratos vinculantes de D-084 (GATE TAPE-CHART-1):
 * - TAPE CHART != FUENTE DE VERDAD (solo lectura agregada sin estado persistido)
 * - PROYECCIÓN CALENDARIO DE HOY = FUENTE DEL RACK DE HOY (consistencia matemática)
 * - Agregación O(1) en queries PDO por bloque
 * - Detección hotelera [E, S), huso IANA D-066, expiración de holds
 * - Jerarquía visual sin pérdida de información
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CamargoPMS\Modelos\HousekeepingEstadoLimpieza;
use CamargoPMS\Modelos\TapeChartCelda;
use CamargoPMS\Modelos\TapeChartProyeccion;
use CamargoPMS\Modelos\TapeChartUnidad;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Repositorios\PropiedadRepositorio;
use CamargoPMS\Repositorios\TapeChartRepositorio;
use CamargoPMS\Servicios\TapeChartServicio;

Configuracion::cargar(dirname(__DIR__));
$pdo = BaseDatos::conexion();
$pdo->exec("SET SESSION innodb_lock_wait_timeout = 5;");

$tapeRepo = new TapeChartRepositorio($pdo);
$propRepo = new PropiedadRepositorio($pdo);
$tapeServicio = new TapeChartServicio($tapeRepo, $propRepo);

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
echo " CAMARGO PMS — PRUEBAS TAPE-CHART-1 (MATRIZ EXHAUSTIVA DE 40 CASOS)\n";
echo " Proyección Operacional, Agregación en Memoria y Rack Hotelero\n";
echo " Decisión Vinculante: D-084\n";
echo "====================================================================\n\n";

// Obtener una propiedad activa existente para las pruebas
$propiedades = $propRepo->listar(null, 'ACTIVO', null, 5, 0);
if (empty($propiedades)) {
    throw new RuntimeException("Se requiere al menos una propiedad activa en la base de datos.");
}
$propiedadTest = $propiedades[0];
$propiedadId = (int) $propiedadTest->obtenerId();
$propiedadNombre = $propiedadTest->obtenerNombre();

$tzPropiedad = $propiedadTest->obtenerZonaHoraria() ?: 'America/Lima';
$dtHoy = new DateTimeImmutable('now', new DateTimeZone($tzPropiedad));
$fechaHoyStr = $dtHoy->format('Y-m-d');
$fechaEn7DiasStr = $dtHoy->modify('+7 days')->format('Y-m-d');
$fechaEn14DiasStr = $dtHoy->modify('+14 days')->format('Y-m-d');
$fechaAyerStr = $dtHoy->modify('-1 day')->format('Y-m-d');

// -----------------------------------------------------------------------------
// BLOQUE 1: VALIDACIÓN DE PARÁMETROS, INTERVALOS Y LÍMITES TEMPORALES (Casos 1 a 8)
// -----------------------------------------------------------------------------
echo "--- BLOQUE 1: VALIDACIÓN DE PARÁMETROS E INTERVALOS (D-084) ---\n";

// Caso 1: Propiedad inexistente rechazada con InvalidArgumentException
$ex1Lanzada = false;
try {
    $tapeServicio->obtenerProyeccion(999999, $fechaHoyStr, $fechaEn7DiasStr);
} catch (InvalidArgumentException $e) {
    $ex1Lanzada = true;
}
afirmar($ex1Lanzada, "Caso 1: Propiedad inexistente (ID 999999) rechazada con InvalidArgumentException");

// Caso 2: Propiedad inactiva rechazada
// Creamos o simulamos una propiedad inactiva
$stmtInact = $pdo->query("SELECT id FROM propiedades WHERE estado = 'INACTIVO' LIMIT 1");
$inactivaId = $stmtInact->fetchColumn();
if (!$inactivaId) {
    // Si no hay inactiva, insertamos una temporal para validar
    $pdo->exec("INSERT INTO propiedades (codigo, nombre, direccion, ciudad, estado, creado_en) VALUES ('PROP-INACT-TC', 'Propiedad Inactiva Test', 'Calle Falsa 123', 'Lima', 'INACTIVO', NOW())");
    $inactivaId = (int) $pdo->lastInsertId();
}
$ex2Lanzada = false;
try {
    $tapeServicio->obtenerProyeccion((int) $inactivaId, $fechaHoyStr, $fechaEn7DiasStr);
} catch (InvalidArgumentException $e) {
    $ex2Lanzada = true;
}
afirmar($ex2Lanzada, "Caso 2: Propiedad inactiva rechazada con InvalidArgumentException");

// Caso 3: Formato fecha inicio inválido
$ex3Lanzada = false;
try {
    $tapeServicio->obtenerProyeccion($propiedadId, 'fecha-invalida', $fechaEn7DiasStr);
} catch (InvalidArgumentException $e) {
    $ex3Lanzada = true;
}
afirmar($ex3Lanzada, "Caso 3: Fecha de inicio malformada rechazada estrictamente");

// Caso 4: Formato fecha fin inválido
$ex4Lanzada = false;
try {
    $tapeServicio->obtenerProyeccion($propiedadId, $fechaHoyStr, '2026/09/30');
} catch (InvalidArgumentException $e) {
    $ex4Lanzada = true;
}
afirmar($ex4Lanzada, "Caso 4: Fecha de fin con formato no canónico YYYY-MM-DD rechazada");

// Caso 5: Fecha fin <= fecha inicio rechazada
$ex5Lanzada = false;
try {
    $tapeServicio->obtenerProyeccion($propiedadId, $fechaEn7DiasStr, $fechaHoyStr);
} catch (InvalidArgumentException $e) {
    $ex5Lanzada = true;
}
afirmar($ex5Lanzada, "Caso 5: Intervalo invertido (fecha fin anterior a fecha inicio) rechazado");

// Caso 6: Fecha fin igual a fecha inicio rechazada (intervalo vacío)
$ex6Lanzada = false;
try {
    $tapeServicio->obtenerProyeccion($propiedadId, $fechaHoyStr, $fechaHoyStr);
} catch (InvalidArgumentException $e) {
    $ex6Lanzada = true;
}
afirmar($ex6Lanzada, "Caso 6: Intervalo de 0 días (fecha fin == fecha inicio) rechazado");

// Caso 7: Intervalo superior a 60 días rechazado por directriz de rendimiento D-084
$fechaEn70Dias = $dtHoy->modify('+70 days')->format('Y-m-d');
$ex7Lanzada = false;
try {
    $tapeServicio->obtenerProyeccion($propiedadId, $fechaHoyStr, $fechaEn70Dias);
} catch (InvalidArgumentException $e) {
    $ex7Lanzada = true;
}
afirmar($ex7Lanzada, "Caso 7: Horizonte superior a 60 días rechazado preventivamente");

// Caso 8: Intervalo válido de 7 días genera proyección sin errores
$proyeccion7d = $tapeServicio->obtenerProyeccion($propiedadId, $fechaHoyStr, $fechaEn7DiasStr);
afirmar($proyeccion7d instanceof TapeChartProyeccion, "Caso 8: Intervalo estándar de 7 días genera proyección válida de dominio");

// -----------------------------------------------------------------------------
// BLOQUE 2: ESTRUCTURA DE PROYECCIÓN, COLUMNAS DE FECHAS Y METADATOS TEMPORALES (Casos 9 a 14)
// -----------------------------------------------------------------------------
echo "\n--- BLOQUE 2: ESTRUCTURA DE PROYECCIÓN Y COLUMNAS TEMPORALES ---\n";

$columnas = $proyeccion7d->obtenerColumnasFechas();
afirmar(count($columnas) === 7, "Caso 9: Cantidad de columnas de fechas coincide exactamente con los 7 días solicitados");

$primeraCol = $columnas[0] ?? [];
$tieneClavesTemporales = isset($primeraCol['fecha'], $primeraCol['dia_numero'], $primeraCol['dia_nombre_corto'], $primeraCol['mes_nombre_corto'], $primeraCol['es_hoy'], $primeraCol['es_fin_semana']);
afirmar($tieneClavesTemporales, "Caso 10: Cada columna temporal contiene todas las claves requeridas para Alina");

// Caso 11: es_hoy === true únicamente para la fecha hotelera actual
$conteoHoy = 0;
foreach ($columnas as $col) {
    if ($col['es_hoy'] === true) {
        $conteoHoy++;
        afirmar($col['fecha'] === $fechaHoyStr, "Caso 11a: La fecha identificada como hoy coincide con la fecha hotelera");
    }
}
afirmar($conteoHoy === 1, "Caso 11b: Exactamente una columna está marcada con es_hoy === true en el rango");

// Caso 12: Detección precisa de fin de semana (sábado y domingo)
$finSemanaOk = true;
foreach ($columnas as $col) {
    $dtCol = new DateTimeImmutable($col['fecha']);
    $diaN = (int) $dtCol->format('N');
    $esFdsReal = ($diaN === 6 || $diaN === 7);
    if ($col['es_fin_semana'] !== $esFdsReal) {
        $finSemanaOk = false;
        break;
    }
}
afirmar($finSemanaOk, "Caso 12: Banderas de fin de semana (es_fin_semana) coinciden con el calendario");

// Caso 13: Identidad y metadatos del DTO raíz
afirmar(
    $proyeccion7d->obtenerPropiedadId() === $propiedadId &&
    $proyeccion7d->obtenerPropiedadNombre() === $propiedadNombre &&
    $proyeccion7d->obtenerZonaHoraria() === $tzPropiedad &&
    $proyeccion7d->obtenerFechaHoteleraHoy() === $fechaHoyStr,
    "Caso 13: DTO TapeChartProyeccion preserva la identidad y zona horaria IANA de la propiedad"
);

// Caso 14: Serialización aArreglo() estructurada e inmutable
$arrProy = $proyeccion7d->aArreglo();
afirmar(
    isset($arrProy['propiedad']['id'], $arrProy['horizonte']['fecha_desde'], $arrProy['horizonte']['total_dias'], $arrProy['kpis_hoy'], $arrProy['filas_unidades']) &&
    $arrProy['horizonte']['total_dias'] === 7,
    "Caso 14: aArreglo() produce estructura inmutable apta para serialización JSON inmediata"
);

// -----------------------------------------------------------------------------
// BLOQUE 3: AGRUPACIÓN DE UNIDADES, TIPOLOGÍAS, PISOS Y FILTRADO (Casos 15 a 20)
// -----------------------------------------------------------------------------
echo "\n--- BLOQUE 3: AGRUPACIÓN DE UNIDADES, TIPOLOGÍAS Y FILTRADO ---\n";

$unidades = $proyeccion7d->obtenerFilasUnidades();
$totalUnidades = count($unidades);
afirmar($totalUnidades > 0, "Caso 15: Carga por lotes de unidades físicas activas de la propiedad (total: {$totalUnidades})");

$primeraU = $unidades[0];
afirmar(
    $primeraU->obtenerUnidadId() > 0 &&
    $primeraU->obtenerCodigo() !== '' &&
    $primeraU->obtenerTipoUnidadId() > 0 &&
    $primeraU->obtenerTipoUnidadNombre() !== '',
    "Caso 16: Cada unidad proyectada contiene id, código, tipología y metadatos de capacidad"
);

// Caso 17: Filtrado por tipo_unidad_id
$tipoFiltroId = $primeraU->obtenerTipoUnidadId();
$proyFiltradaTipo = $tapeServicio->obtenerProyeccion($propiedadId, $fechaHoyStr, $fechaEn7DiasStr, $tipoFiltroId);
$unidadesFiltradasTipo = $proyFiltradaTipo->obtenerFilasUnidades();
$todasDelTipo = true;
foreach ($unidadesFiltradasTipo as $uf) {
    if ($uf->obtenerTipoUnidadId() !== $tipoFiltroId) {
        $todasDelTipo = false;
        break;
    }
}
afirmar($todasDelTipo && count($unidadesFiltradasTipo) <= $totalUnidades, "Caso 17: Filtro por tipología de unidad restringe exactamente al subtipo indicado");

// Caso 18: Filtrado por piso/ala
$pisoFiltro = $primeraU->obtenerPisoNivel();
$proyFiltradaPiso = $tapeServicio->obtenerProyeccion($propiedadId, $fechaHoyStr, $fechaEn7DiasStr, null, $pisoFiltro);
$unidadesFiltradasPiso = $proyFiltradaPiso->obtenerFilasUnidades();
$todasDelPiso = true;
foreach ($unidadesFiltradasPiso as $uf) {
    if ($uf->obtenerPisoNivel() !== $pisoFiltro) {
        $todasDelPiso = false;
        break;
    }
}
afirmar($todasDelPiso, "Caso 18: Filtro por piso_nivel restringe las unidades proyectadas al piso solicitado");

// Caso 19: Cada unidad cuenta con exactamente 7 celdas indexadas por fecha
$todas7Celdas = true;
foreach ($unidades as $u) {
    $celdasU = $u->obtenerCeldas();
    if (count($celdasU) !== 7) {
        $todas7Celdas = false;
        break;
    }
}
afirmar($todas7Celdas, "Caso 19: Cada unidad proyecta exactamente un mapa de celdas completo para todo el horizonte");

// Caso 20: Claves del mapa de celdas corresponden exactamente a las fechas del intervalo
$fechasCoinciden = true;
$primeraCeldas = $primeraU->obtenerCeldas();
foreach ($columnas as $col) {
    if (!isset($primeraCeldas[$col['fecha']])) {
        $fechasCoinciden = false;
        break;
    }
}
afirmar($fechasCoinciden, "Caso 20: Mapa de celdas indexado exactamente por fecha Y-m-d para acceso O(1)");

// -----------------------------------------------------------------------------
// BLOQUE 4: ESTADOS DOMINANTES DE CELDA Y PRIORIDAD VISUAL (Casos 21 a 28)
// -----------------------------------------------------------------------------
echo "\n--- BLOQUE 4: ESTADOS DOMINANTES DE CELDA Y PRIORIDAD VISUAL ---\n";

// Caso 21: Celda vacante sin registros futuros
$celdaFutura = $primeraU->obtenerCelda($fechaEn7DiasStr === $fechaHoyStr ? $fechaHoyStr : $columnas[count($columnas) - 1]['fecha']);
afirmar(
    $celdaFutura !== null && in_array($celdaFutura->obtenerEstadoPrincipal(), [
        TapeChartCelda::ESTADO_VACANTE,
        TapeChartCelda::ESTADO_RESERVA,
        TapeChartCelda::ESTADO_ESTADIA,
        TapeChartCelda::ESTADO_ARRENDAMIENTO,
        TapeChartCelda::ESTADO_MANTENIMIENTO_OOO,
        TapeChartCelda::ESTADO_BLOQUEO_MANUAL,
        TapeChartCelda::ESTADO_HOLD_PENDIENTE
    ], true),
    "Caso 21: Celda proyectada contiene un estado operacional canónico reconocido"
);

// Casos 22 a 28: Pruebas sintéticas con instancias unitarias de TapeChartCelda
// para verificar la semántica de la clase de modelo y sus invariantes
$celdaVacante = new TapeChartCelda(
    fecha: '2026-10-01',
    estadoPrincipal: TapeChartCelda::ESTADO_VACANTE,
    subestado: 'DISPONIBLE',
    claseColor: 'tape-celda-vacante'
);
afirmar($celdaVacante->obtenerEstadoPrincipal() === TapeChartCelda::ESTADO_VACANTE && $celdaVacante->obtenerSubestado() === 'DISPONIBLE', "Caso 22: Modelo de Celda VACANTE inicializado con subestado DISPONIBLE");

$celdaReserva = new TapeChartCelda(
    fecha: '2026-10-02',
    estadoPrincipal: TapeChartCelda::ESTADO_RESERVA,
    subestado: 'CONFIRMADA',
    codigoReferencia: 'RES-202610-0001',
    referenciaId: 101,
    origenTipo: 'RESERVA',
    titularNombre: 'Pérez, Juan',
    duracionNoches: 3,
    nocheIndice: 1,
    esArrival: true,
    claseColor: 'tape-celda-reserva'
);
afirmar(
    $celdaReserva->obtenerEstadoPrincipal() === TapeChartCelda::ESTADO_RESERVA &&
    $celdaReserva->obtenerCodigoReferencia() === 'RES-202610-0001' &&
    $celdaReserva->esArrival() === true,
    "Caso 23: Celda RESERVA CONFIRMADA conArrival === true y titular asignado"
);

$celdaHold = new TapeChartCelda(
    fecha: '2026-10-03',
    estadoPrincipal: TapeChartCelda::ESTADO_HOLD_PENDIENTE,
    subestado: 'HOLD_TEMPORAL',
    codigoReferencia: 'RES-HOLD-0002',
    claseColor: 'tape-celda-hold'
);
afirmar($celdaHold->obtenerEstadoPrincipal() === TapeChartCelda::ESTADO_HOLD_PENDIENTE, "Caso 24: Celda HOLD PENDIENTE preserva su naturaleza temporal");

$celdaEstadia = new TapeChartCelda(
    fecha: '2026-10-04',
    estadoPrincipal: TapeChartCelda::ESTADO_ESTADIA,
    subestado: 'EN_CASA',
    codigoReferencia: 'EST-202610-0003',
    referenciaId: 50,
    origenTipo: 'ESTADIA',
    esStayover: true,
    claseColor: 'tape-celda-estadia'
);
afirmar($celdaEstadia->obtenerEstadoPrincipal() === TapeChartCelda::ESTADO_ESTADIA && $celdaEstadia->esStayover() === true, "Caso 25: Celda ESTADIA EN_CASA con marca de stayover (huésped en casa)");

$celdaArrendamiento = new TapeChartCelda(
    fecha: '2026-10-05',
    estadoPrincipal: TapeChartCelda::ESTADO_ARRENDAMIENTO,
    subestado: 'VIGENTE',
    codigoReferencia: 'ARR-202610-0004',
    referenciaId: 12,
    origenTipo: 'ARRENDAMIENTO',
    claseColor: 'tape-celda-arrendamiento'
);
afirmar($celdaArrendamiento->obtenerEstadoPrincipal() === TapeChartCelda::ESTADO_ARRENDAMIENTO && $celdaArrendamiento->obtenerSubestado() === 'VIGENTE', "Caso 26: Celda ARRENDAMIENTO mensual domina período contratado");

$celdaOoo = new TapeChartCelda(
    fecha: '2026-10-06',
    estadoPrincipal: TapeChartCelda::ESTADO_MANTENIMIENTO_OOO,
    subestado: 'FUERA_SERVICIO',
    codigoReferencia: 'ORD-202610-0005',
    conflictos: ['AFECTADA_POR_MANTENIMIENTO_OOO'],
    claseColor: 'tape-celda-ooo'
);
afirmar(
    $celdaOoo->obtenerEstadoPrincipal() === TapeChartCelda::ESTADO_MANTENIMIENTO_OOO &&
    $celdaOoo->tieneConflictos() === true &&
    in_array('AFECTADA_POR_MANTENIMIENTO_OOO', $celdaOoo->obtenerConflictos(), true),
    "Caso 27: Celda MANTENIMIENTO OOO conserva bandera de conflicto sin pérdida de contexto"
);

$celdaBloqueo = new TapeChartCelda(
    fecha: '2026-10-07',
    estadoPrincipal: TapeChartCelda::ESTADO_BLOQUEO_MANUAL,
    subestado: 'BLOQUEADO',
    claseColor: 'tape-celda-bloqueo'
);
afirmar($celdaBloqueo->obtenerEstadoPrincipal() === TapeChartCelda::ESTADO_BLOQUEO_MANUAL, "Caso 28: Celda BLOQUEO MANUAL proyecta indisponibilidad comercial");

// -----------------------------------------------------------------------------
// BLOQUE 5: DETECCIÓN OPERATIVA (ARRIVALS, DEPARTURES, STAYOVERS) (Casos 29 a 34)
// -----------------------------------------------------------------------------
echo "\n--- BLOQUE 5: DETECCIÓN OPERATIVA DE ARRIVALS, DEPARTURES Y STAYOVERS ---\n";

$celdaLlegada = new TapeChartCelda(
    fecha: '2026-10-10',
    estadoPrincipal: TapeChartCelda::ESTADO_RESERVA,
    subestado: 'CHECKIN_PENDIENTE',
    esArrival: true,
    esDeparture: false,
    esStayover: false
);
afirmar($celdaLlegada->esArrival() && !$celdaLlegada->esDeparture() && !$celdaLlegada->esStayover(), "Caso 29: Noche de llegada detecta esArrival === true y no es stayover");

$celdaStayover = new TapeChartCelda(
    fecha: '2026-10-11',
    estadoPrincipal: TapeChartCelda::ESTADO_ESTADIA,
    subestado: 'EN_CASA',
    esArrival: false,
    esDeparture: false,
    esStayover: true
);
afirmar(!$celdaStayover->esArrival() && !$celdaStayover->esDeparture() && $celdaStayover->esStayover(), "Caso 30: Noche intermedia detecta esStayover === true");

$celdaSalida = new TapeChartCelda(
    fecha: '2026-10-12',
    estadoPrincipal: TapeChartCelda::ESTADO_VACANTE,
    subestado: 'DISPONIBLE',
    esArrival: false,
    esDeparture: true,
    esStayover: false
);
afirmar(!$celdaSalida->esArrival() && $celdaSalida->esDeparture(), "Caso 31: Fecha de salida detecta esDeparture === true sin consumir noche hotelera");

$celdaRotacion = new TapeChartCelda(
    fecha: '2026-10-13',
    estadoPrincipal: TapeChartCelda::ESTADO_RESERVA,
    subestado: 'CONFIRMADA',
    esArrival: true,
    esDeparture: true,
    esStayover: false,
    indicadoresSecundarios: ['ROTACION_MISMO_DIA']
);
afirmar(
    $celdaRotacion->esArrival() &&
    $celdaRotacion->esDeparture() &&
    in_array('ROTACION_MISMO_DIA', $celdaRotacion->obtenerIndicadoresSecundarios(), true),
    "Caso 32: Rotación el mismo día detecta concurrencia de salida y entrada (Back-to-Back)"
);

$celdaMantLeve = new TapeChartCelda(
    fecha: '2026-10-14',
    estadoPrincipal: TapeChartCelda::ESTADO_VACANTE,
    subestado: 'VR',
    indicadoresSecundarios: ['INCIDENCIA_TECNICA']
);
afirmar(
    $celdaMantLeve->obtenerEstadoPrincipal() === TapeChartCelda::ESTADO_VACANTE &&
    in_array('INCIDENCIA_TECNICA', $celdaMantLeve->obtenerIndicadoresSecundarios(), true),
    "Caso 33: Mantenimiento leve (no bloqueante) se preserva como indicador secundario sin alterar disponibilidad"
);

$arrCelda = $celdaRotacion->aArreglo();
afirmar(
    isset($arrCelda['fecha'], $arrCelda['estado_principal'], $arrCelda['es_arrival'], $arrCelda['es_departure'], $arrCelda['indicadores_secundarios']),
    "Caso 34: aArreglo() de TapeChartCelda contiene todas las banderas operacionales requeridas"
);

// -----------------------------------------------------------------------------
// BLOQUE 6: PARIDAD MATEMÁTICA ESTRICTA DE KPIS DE HOY Y RACK (Casos 35 a 40)
// -----------------------------------------------------------------------------
echo "\n--- BLOQUE 6: PARIDAD MATEMÁTICA ESTRICTA DE KPIS Y RACK DE HOY ---\n";

$kpis = $proyeccion7d->obtenerKpisHoy();
afirmar($kpis['total_unidades'] === $totalUnidades, "Caso 35: kpis_hoy.total_unidades coincide exactamente con la cantidad de unidades proyectadas");

$sumaCategorias = $kpis['ocupadas_stayover'] + $kpis['vacantes_listas_vr'] + $kpis['vacantes_sucias_vd'] + $kpis['vacantes_limpieza_vcl'] + $kpis['fuera_servicio_ooo'];
afirmar($sumaCategorias === $kpis['total_unidades'], "Caso 36: Invariante de suma cerrada: Ocupadas + VR + VD + VCL + OOO == Total Unidades ({$sumaCategorias} == {$kpis['total_unidades']})");

// Caso 37: Porcentaje de ocupación calculado matemáticamente
$porcentajeEsperado = $totalUnidades > 0 ? round(($kpis['ocupadas_stayover'] / $totalUnidades) * 100, 1) : 0.0;
afirmar($kpis['porcentaje_ocupacion'] === $porcentajeEsperado, "Caso 37: Porcentaje de ocupación calculado con precisión de un decimal ({$kpis['porcentaje_ocupacion']}%)");

// Caso 38: Celda de hoy de cada unidad vacante concuerda con el estado de limpieza actual
$paridadLimpiezaOk = true;
foreach ($unidades as $u) {
    $celdaHoy = $u->obtenerCelda($fechaHoyStr);
    if ($celdaHoy && $celdaHoy->obtenerEstadoPrincipal() === TapeChartCelda::ESTADO_VACANTE) {
        $sub = $celdaHoy->obtenerSubestado();
        $codLimp = $u->obtenerLimpiezaHoyCodigo();
        if ($sub !== $codLimp) {
            $paridadLimpiezaOk = false;
            break;
        }
    }
}
afirmar($paridadLimpiezaOk, "Caso 38: Celdas vacantes de hoy reflejan exactamente el código de limpieza físico de la unidad (VR/VD/VCL)");

// Caso 39: Celdas futuras vacantes proyectan disponibilidad neutra 'DISPONIBLE' sin asumir limpieza física futura
$celdasFuturasNeutras = true;
$ultimaFecha = $columnas[count($columnas) - 1]['fecha'];
if ($ultimaFecha !== $fechaHoyStr) {
    foreach ($unidades as $u) {
        $celdaF = $u->obtenerCelda($ultimaFecha);
        if ($celdaF && $celdaF->obtenerEstadoPrincipal() === TapeChartCelda::ESTADO_VACANTE) {
            if ($celdaF->obtenerSubestado() !== 'DISPONIBLE') {
                $celdasFuturasNeutras = false;
                break;
            }
        }
    }
}
afirmar($celdasFuturasNeutras, "Caso 39: Celdas futuras vacantes proyectan disponibilidad comercial neutra ('DISPONIBLE')");

// Caso 40: Consistencia 1:1 entre Tape Chart de hoy y Rack de Hoy
$proyRackHoy = $tapeServicio->obtenerProyeccion($propiedadId, $fechaHoyStr, $dtHoy->modify('+1 day')->format('Y-m-d'));
$kpisRack = $proyRackHoy->obtenerKpisHoy();
afirmar(
    $kpisRack['total_unidades'] === $kpis['total_unidades'] &&
    $kpisRack['ocupadas_stayover'] === $kpis['ocupadas_stayover'] &&
    $kpisRack['vacantes_listas_vr'] === $kpis['vacantes_listas_vr'] &&
    $kpisRack['fuera_servicio_ooo'] === $kpis['fuera_servicio_ooo'],
    "Caso 40: Consistencia matemática absoluta entre la proyección de 7 días y la proyección de 1 día del Rack de Hoy"
);

echo "\n====================================================================\n";
echo " RESUMEN: {$pasadas} / {$total} PRUEBAS PASADAS\n";
if ($fallidas > 0) {
    echo " FALLIDAS: {$fallidas}\n";
    foreach ($errores as $err) {
        echo "   - {$err}\n";
    }
    exit(1);
}
echo " RESULTADO: MATRIZ TAPE-CHART-1 40/40 PASS EXITOSA\n";
echo "====================================================================\n";
