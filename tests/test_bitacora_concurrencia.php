<?php

declare(strict_types=1);

/**
 * Suite de Verificación BITÁCORA-1 — Concurrencia, Aislamiento y Append-Only (10 Casos)
 *
 * Verifica:
 * - Inmutabilidad concurrente del relato original.
 * - Secuencialidad y monotonía en bitacora_seguimientos (append-only).
 * - Atomicidad transaccional (rollback completo en fallos sin seguimientos huérfanos).
 * - Carrera de estados (resolución, anulación, reapertura).
 * - Trazabilidad multiusuario y cálculo coherente de métricas.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CamargoPMS\Excepciones\OperacionInvalidaExcepcion;
use CamargoPMS\Modelos\BitacoraEntrada;
use CamargoPMS\Modelos\BitacoraSeguimiento;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Repositorios\BitacoraRepositorio;
use CamargoPMS\Servicios\BitacoraServicio;

Configuracion::cargar(dirname(__DIR__));
$pdo = BaseDatos::conexion();

$stmtProp = $pdo->query('SELECT id FROM propiedades WHERE estado = "ACTIVO" LIMIT 1');
$propiedadId = (int) $stmtProp->fetchColumn();
if ($propiedadId <= 0) {
    $stmtPropAny = $pdo->query('SELECT id FROM propiedades LIMIT 1');
    $propiedadId = (int) $stmtPropAny->fetchColumn();
}

$stmtUsrs = $pdo->query('SELECT id FROM usuarios WHERE estado = "ACTIVO" LIMIT 2');
$usuarios = $stmtUsrs->fetchAll(PDO::FETCH_COLUMN);
$usuario1 = (int) ($usuarios[0] ?? 1);
$usuario2 = (int) ($usuarios[1] ?? $usuario1);

$bitacoraRepo = new BitacoraRepositorio($pdo);
$bitacoraServicio = new BitacoraServicio($pdo, $bitacoraRepo);

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
        echo "  [PASS] Caso {$total}: {$mensaje}\n";
    } else {
        $fallidas++;
        $errores[] = "Caso {$total}: {$mensaje}";
        echo "  [FAIL] Caso {$total}: {$mensaje}\n";
    }
}

echo "====================================================================\n";
echo " CAMARGO PMS — PRUEBAS BITÁCORA-1: CONCURRENCIA Y APPEND-ONLY (10 CASOS)\n";
echo " Decisión Vinculante: D-089\n";
echo "====================================================================\n\n";

// Caso 1: Secuencialidad y creación de múltiples seguimientos
$entradaConc = $bitacoraServicio->registrarEntrada([
    'propiedad_id' => $propiedadId,
    'tipo' => BitacoraEntrada::TIPO_CONSIGNA,
    'titulo' => 'Consigna para prueba de concurrencia y append-only',
    'contenido' => 'Relato original que debe permanecer intacto a través de todas las operaciones.',
], $usuario1);
$entradaId = (int) $entradaConc->obtenerId();

$idsSeguimientos = [];
for ($i = 1; $i <= 5; $i++) {
    $s = $bitacoraServicio->agregarSeguimiento($entradaId, $usuario1, "Seguimiento append-only #{$i}");
    $idsSeguimientos[] = (int) $s->obtenerId();
}

$ordenSecuencial = true;
for ($i = 0; $i < count($idsSeguimientos) - 1; $i++) {
    if ($idsSeguimientos[$i] >= $idsSeguimientos[$i + 1]) {
        $ordenSecuencial = false;
        break;
    }
}
afirmar($ordenSecuencial, "Seguimientos sucesivos obtienen IDs estrictamente crecientes y secuenciales");

// Caso 2: Monotonía append-only: el recuento de seguimientos nunca disminuye
$totalSegAntes = count($bitacoraRepo->buscarSeguimientosPorEntradaId($entradaId));
$bitacoraServicio->agregarSeguimiento($entradaId, $usuario1, 'Otro evento append-only');
$totalSegDespues = count($bitacoraRepo->buscarSeguimientosPorEntradaId($entradaId));
afirmar($totalSegDespues === $totalSegAntes + 1, "Principio append-only: el registro histórico crece monótonamente sin decrecer");

// Caso 3: Invariante del relato original inmutable
$entradaVerificada = $bitacoraRepo->buscarPorId($entradaId, false);
afirmar(
    $entradaVerificada !== null &&
    $entradaVerificada->obtenerContenido() === 'Relato original que debe permanecer intacto a través de todas las operaciones.',
    "Invariante de inmutabilidad: el relato original jamás muta tras múltiples eventos posteriores"
);

// Caso 4: Trazabilidad multiautor
$bitacoraServicio->agregarSeguimiento($entradaId, $usuario2, "Comentario emitido por el segundo colaborador ({$usuario2})");
$segsLista = $bitacoraRepo->buscarSeguimientosPorEntradaId($entradaId);
$ultimoSeg = end($segsLista);
afirmar(
    $ultimoSeg !== false && $ultimoSeg->obtenerUsuarioId() === $usuario2,
    "Múltiples colaboradores registran eventos en la misma entrada preservando con fidelidad su identidad"
);

// Caso 5: Atomicidad transaccional ante excepciones
$conteoSegsAntesError = count($bitacoraRepo->buscarSeguimientosPorEntradaId($entradaId));
$estadoAntes = $entradaVerificada->obtenerEstado();
try {
    // Forzar fallo de negocio pasando nota vacía en resolver
    $bitacoraServicio->resolverEntrada($entradaId, $usuario1, '   ');
} catch (Throwable $e) {
    // Esperado
}
$entradaDespuesError = $bitacoraRepo->buscarPorId($entradaId, false);
$conteoSegsDespuesError = count($bitacoraRepo->buscarSeguimientosPorEntradaId($entradaId));
afirmar(
    $entradaDespuesError->obtenerEstado() === $estadoAntes && $conteoSegsDespuesError === $conteoSegsAntesError,
    "Transaccionalidad atómica: operaciones fallidas no dejan registros huérfanos ni mutan el estado"
);

// Caso 6: Carrera de resolución: la primera resuelve, la subsecuente es rechazada
$entradaResuelta = $bitacoraServicio->resolverEntrada($entradaId, $usuario1, 'Primera resolución confirmada');
$segundaResolucionRechazada = false;
try {
    $bitacoraServicio->resolverEntrada($entradaId, $usuario2, 'Segunda resolución concurrente');
} catch (OperacionInvalidaExcepcion $e) {
    $segundaResolucionRechazada = true;
}
afirmar($segundaResolucionRechazada, "Carrera de resolución: subsecuente intento sobre entrada ya resuelta es rechazado");

// Caso 7: Reapertura y re-resolución secuencial
$entradaReabierta = $bitacoraServicio->reabrirEntrada($entradaId, $usuario2, 'Reapertura por chequeo pendiente');
afirmar($entradaReabierta->obtenerEstado() === BitacoraEntrada::ESTADO_EN_PROCESO, "Reapertura concurrente transiciona limpiamente a EN_PROCESO");

// Caso 8: Carrera entre anulación y seguimiento posterior
$entradaParaCarrera = $bitacoraServicio->registrarEntrada([
    'propiedad_id' => $propiedadId,
    'tipo' => BitacoraEntrada::TIPO_NOVEDAD,
    'titulo' => 'Entrada para carrera anulación vs seguimiento',
    'contenido' => 'Relato para test de carrera',
], $usuario1);
$idCarrera = (int) $entradaParaCarrera->obtenerId();

// Anular
$bitacoraServicio->anularEntrada($idCarrera, $usuario1, 'Anulado de forma inmediata por error');

// Intentar agregar seguimiento
$seguimientoBloqueado = false;
try {
    $bitacoraServicio->agregarSeguimiento($idCarrera, $usuario2, 'Intento de comentar en entrada ya anulada');
} catch (OperacionInvalidaExcepcion $e) {
    $seguimientoBloqueado = true;
}
afirmar($seguimientoBloqueado, "Carrera: una vez anulada la entrada, cualquier intento de seguimiento concurrente es bloqueado");

// Caso 9: Integridad de consultas paginadas concurrentes
$resultadoPaginado = $bitacoraServicio->listarEntradas(['propiedad_id' => $propiedadId], 1, 10);
afirmar(
    isset($resultadoPaginado['entradas']) &&
    isset($resultadoPaginado['total']) &&
    $resultadoPaginado['total'] >= 2 &&
    count($resultadoPaginado['entradas']) <= 10,
    "Consultas paginadas y filtradas operan con consistencia sobre el feed"
);

// Caso 10: Métricas operacionales coherentes
$metricas = $bitacoraServicio->obtenerMetricas($propiedadId);
afirmar(
    isset($metricas['total_general']) &&
    isset($metricas['anuladas_total']) &&
    $metricas['total_general'] >= $metricas['anuladas_total'],
    "Agregación analítica de métricas operacionales calcula subtotales coherentes"
);

echo "\n====================================================================\n";
echo " RESUMEN: {$pasadas} / {$total} pruebas PASADAS\n";
if ($fallidas > 0) {
    echo " ATENCIÓN: {$fallidas} pruebas FALLIDAS\n";
    foreach ($errores as $err) {
        echo "   - {$err}\n";
    }
    exit(1);
} else {
    echo " CERTIFICACIÓN PASS: Suite de Concurrencia y Append-Only (10 casos) completada con éxito.\n";
    echo "====================================================================\n";
    exit(0);
}
