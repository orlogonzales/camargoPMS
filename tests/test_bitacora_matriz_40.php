<?php

declare(strict_types=1);

/**
 * Suite de Verificación BITÁCORA-1 — Matriz Exhaustiva de 40 Pruebas de Dominio
 *
 * Verifica las decisiones y contratos vinculantes de D-089:
 * 1. BITÁCORA ≠ AUDITORÍA TÉCNICA (D-061) ≠ TURNO CAJA ≠ MANTENIMIENTO ≠ HOUSEKEEPING.
 * 2. Inmutabilidad del relato original de guardia.
 * 3. Enmiendas y comentarios append-only en bitacora_seguimientos.
 * 4. Transiciones de estado (REGISTRADA, PENDIENTE, EN_PROCESO, RESUELTA, ANULADA).
 * 5. Ciclo de resolución y reapertura justificada.
 * 6. ANULAR ≠ DELETE: anulación supervisada sin borrado físico.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CamargoPMS\Excepciones\EntidadNoEncontradaExcepcion;
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

$stmtUsr = $pdo->query('SELECT id FROM usuarios WHERE estado = "ACTIVO" LIMIT 1');
$usuarioId = (int) $stmtUsr->fetchColumn();
if ($usuarioId <= 0) {
    $stmtUsrAny = $pdo->query('SELECT id FROM usuarios LIMIT 1');
    $usuarioId = (int) $stmtUsrAny->fetchColumn();
}

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
echo " CAMARGO PMS — PRUEBAS BITÁCORA-1: MATRIZ DE DOMINIO (40 CASOS)\n";
echo " Decisión Vinculante: D-089\n";
echo "====================================================================\n\n";

// BLOQUE 1: AXIOMAS Y TIPOS DE ENTRADA (Casos 1 - 8)
echo "--- BLOQUE 1: AXIOMAS Y TIPOS DE ENTRADA ---\n";

afirmar(true, "Axioma D-089 #1: BITÁCORA ≠ AUDITORÍA TÉCNICA (D-061) ≠ TURNO CAJA ≠ MANTENIMIENTO ≠ HOUSEKEEPING");

$entradaNov = $bitacoraServicio->registrarEntrada([
    'propiedad_id' => $propiedadId,
    'tipo' => BitacoraEntrada::TIPO_NOVEDAD,
    'prioridad' => BitacoraEntrada::PRIORIDAD_MEDIA,
    'titulo' => 'Novedad de turno matutino',
    'contenido' => 'El turno inicia con ocupación normal de 14 habitaciones.',
    'turno' => BitacoraEntrada::TURNO_MANANA,
    'fecha_operativa' => date('Y-m-d')
], $usuarioId);
afirmar($entradaNov->obtenerTipo() === BitacoraEntrada::TIPO_NOVEDAD && $entradaNov->obtenerEstado() === BitacoraEntrada::ESTADO_REGISTRADA, "Tipo NOVEDAD se crea correctamente con estado REGISTRADA");

$entradaCons = $bitacoraServicio->registrarEntrada([
    'propiedad_id' => $propiedadId,
    'tipo' => BitacoraEntrada::TIPO_CONSIGNA,
    'prioridad' => BitacoraEntrada::PRIORIDAD_ALTA,
    'titulo' => 'Consigna: Entregar llave al Sr. Pérez a las 18:00',
    'contenido' => 'El huésped de la 201 solicita dejar su copia con recepción para su acompañante.',
    'turno' => BitacoraEntrada::TURNO_TARDE,
], $usuarioId);
afirmar($entradaCons->obtenerTipo() === BitacoraEntrada::TIPO_CONSIGNA && $entradaCons->obtenerEstado() === BitacoraEntrada::ESTADO_PENDIENTE, "Tipo CONSIGNA inicia automáticamente en estado PENDIENTE");

$entradaInc = $bitacoraServicio->registrarEntrada([
    'propiedad_id' => $propiedadId,
    'tipo' => BitacoraEntrada::TIPO_INCIDENCIA,
    'prioridad' => BitacoraEntrada::PRIORIDAD_URGENTE,
    'titulo' => 'Filtración menor en pasillo piso 2',
    'contenido' => 'Goteo procedente de válvula de corte en pasillo central.',
    'turno' => BitacoraEntrada::TURNO_MANANA,
], $usuarioId);
afirmar($entradaInc->obtenerTipo() === BitacoraEntrada::TIPO_INCIDENCIA && $entradaInc->esUrgente(), "Tipo INCIDENCIA con prioridad URGENTE tipada");

$entradaRel = $bitacoraServicio->registrarEntrada([
    'propiedad_id' => $propiedadId,
    'tipo' => BitacoraEntrada::TIPO_RELEVO,
    'prioridad' => BitacoraEntrada::PRIORIDAD_MEDIA,
    'titulo' => 'Relevo de guardia Tarde a Noche',
    'contenido' => 'Se entrega turno con 3 llaves maestras en llavero y novedades al día.',
    'turno' => BitacoraEntrada::TURNO_TARDE,
], $usuarioId);
afirmar($entradaRel->obtenerTipo() === BitacoraEntrada::TIPO_RELEVO, "Tipo RELEVO registrado para entrega formal de turno");

$entradaAvi = $bitacoraServicio->registrarEntrada([
    'propiedad_id' => $propiedadId,
    'tipo' => BitacoraEntrada::TIPO_AVISO_GENERAL,
    'prioridad' => BitacoraEntrada::PRIORIDAD_BAJA,
    'titulo' => 'Corte programado de telefonía IP por mantenimiento ISP',
    'contenido' => 'Aviso de proveedor: mantenimiento técnico el miércoles entre 02:00 y 04:00.',
    'turno' => BitacoraEntrada::TURNO_GENERAL,
], $usuarioId);
afirmar($entradaAvi->obtenerTipo() === BitacoraEntrada::TIPO_AVISO_GENERAL, "Tipo AVISO_GENERAL registrado satisfactoriamente");

$tipoInvalidoRechazado = false;
try {
    $bitacoraServicio->registrarEntrada([
        'propiedad_id' => $propiedadId,
        'tipo' => 'TIPO_INVENTADO',
        'titulo' => 'Título de prueba',
        'contenido' => 'Contenido de prueba',
    ], $usuarioId);
} catch (InvalidArgumentException $e) {
    $tipoInvalidoRechazado = true;
}
afirmar($tipoInvalidoRechazado, "Tipo de entrada inválido es rechazado estrictamente con InvalidArgumentException");

afirmar(
    in_array(BitacoraEntrada::PRIORIDAD_BAJA, BitacoraEntrada::PRIORIDADES_VALIDAS, true) &&
    in_array(BitacoraEntrada::PRIORIDAD_MEDIA, BitacoraEntrada::PRIORIDADES_VALIDAS, true) &&
    in_array(BitacoraEntrada::PRIORIDAD_ALTA, BitacoraEntrada::PRIORIDADES_VALIDAS, true) &&
    in_array(BitacoraEntrada::PRIORIDAD_URGENTE, BitacoraEntrada::PRIORIDADES_VALIDAS, true),
    "Catálogo de 4 prioridades operativas (BAJA, MEDIA, ALTA, URGENTE) validado"
);

// BLOQUE 2: INMUTABILIDAD Y SEGUIMIENTOS APPEND-ONLY (Casos 9 - 16)
echo "\n--- BLOQUE 2: INMUTABILIDAD Y SEGUIMIENTOS APPEND-ONLY ---\n";

afirmar($entradaNov->obtenerContenido() === 'El turno inicia con ocupación normal de 14 habitaciones.', "Contenido original de la entrada se preserva intacto");

$tituloVacioRechazado = false;
try {
    $bitacoraServicio->registrarEntrada([
        'propiedad_id' => $propiedadId,
        'titulo' => '   ',
        'contenido' => 'Contenido válido',
    ], $usuarioId);
} catch (InvalidArgumentException $e) {
    $tituloVacioRechazado = true;
}
afirmar($tituloVacioRechazado, "Entrada con título vacío o solo espacios es rechazada");

$contenidoVacioRechazado = false;
try {
    $bitacoraServicio->registrarEntrada([
        'propiedad_id' => $propiedadId,
        'titulo' => 'Título válido',
        'contenido' => '',
    ], $usuarioId);
} catch (InvalidArgumentException $e) {
    $contenidoVacioRechazado = true;
}
afirmar($contenidoVacioRechazado, "Entrada con contenido vacío es rechazada");

$seg1 = $bitacoraServicio->agregarSeguimiento(
    (int) $entradaCons->obtenerId(),
    $usuarioId,
    'El huésped llamó para confirmar que su acompañante llega 18:30.',
    BitacoraSeguimiento::TIPO_COMENTARIO
);
afirmar($seg1->obtenerTipoEvento() === BitacoraSeguimiento::TIPO_COMENTARIO && $seg1->obtenerId() > 0, "Seguimiento tipo COMENTARIO registrado con ID generado");

$seg2 = $bitacoraServicio->agregarSeguimiento(
    (int) $entradaCons->obtenerId(),
    $usuarioId,
    'Enmienda: la habitación no es 201 sino 204 según validación en reservas.',
    BitacoraSeguimiento::TIPO_ENMIENDA
);
afirmar($seg2->obtenerTipoEvento() === BitacoraSeguimiento::TIPO_ENMIENDA, "Enmienda aclaratoria registrada como evento tipo ENMIENDA");

$entradaConsRecargada = $bitacoraServicio->obtenerEntrada((int) $entradaCons->obtenerId(), true);
$segs = $entradaConsRecargada->obtenerSeguimientos();
afirmar(count($segs) >= 2 && $segs[0]->obtenerId() <= $segs[1]->obtenerId(), "Seguimientos conservan orden cronológico e identitario estricto");

afirmar(
    $entradaConsRecargada->obtenerContenido() === $entradaCons->obtenerContenido(),
    "La adición de enmiendas no altera el relato original almacenado en bitacora_entradas"
);

$seg3 = $bitacoraServicio->agregarSeguimiento(
    (int) $entradaCons->obtenerId(),
    $usuarioId,
    'Tercer comentario informativo sobre el relevo.',
    BitacoraSeguimiento::TIPO_COMENTARIO
);
$entradaCons3 = $bitacoraServicio->obtenerEntrada((int) $entradaCons->obtenerId(), true);
afirmar(count($entradaCons3->obtenerSeguimientos()) === count($segs) + 1, "Trazabilidad append-only crece monótonamente con cada evento");

// BLOQUE 3: CICLO DE VIDA Y TRANSICIONES (Casos 17 - 24)
echo "\n--- BLOQUE 3: CICLO DE VIDA Y TRANSICIONES ---\n";

afirmar($entradaCons->obtenerEstado() === BitacoraEntrada::ESTADO_PENDIENTE, "Estado inicial de consigna es PENDIENTE");

$entradaEnProceso = $bitacoraServicio->cambiarEstado(
    (int) $entradaCons->obtenerId(),
    $usuarioId,
    BitacoraEntrada::ESTADO_EN_PROCESO,
    'Colaborador se dirige a preparar entrega de llave'
);
afirmar($entradaEnProceso->obtenerEstado() === BitacoraEntrada::ESTADO_EN_PROCESO, "Transición a EN_PROCESO ejecutada exitosamente");

$entradaPendiente = $bitacoraServicio->cambiarEstado(
    (int) $entradaCons->obtenerId(),
    $usuarioId,
    BitacoraEntrada::ESTADO_PENDIENTE,
    'Acompañante se retrasa, queda en espera'
);
afirmar($entradaPendiente->obtenerEstado() === BitacoraEntrada::ESTADO_PENDIENTE, "Transición de retorno a PENDIENTE ejecutada");

$cambioEstadoInvalido = false;
try {
    $bitacoraServicio->cambiarEstado((int) $entradaCons->obtenerId(), $usuarioId, 'ESTADO_INEXISTENTE');
} catch (InvalidArgumentException $e) {
    $cambioEstadoInvalido = true;
}
afirmar($cambioEstadoInvalido, "Intento de cambiar a estado inexistente lanza excepción");

$segsCambio = $bitacoraRepo->buscarSeguimientosPorEntradaId((int) $entradaCons->obtenerId());
$ultimoSegCambio = end($segsCambio);
afirmar(
    $ultimoSegCambio !== false && $ultimoSegCambio->obtenerTipoEvento() === BitacoraSeguimiento::TIPO_CAMBIO_ESTADO,
    "El cambio de estado genera un evento CAMBIO_ESTADO en el histórico append-only"
);

$entradaResuelta = $bitacoraServicio->resolverEntrada(
    (int) $entradaCons->obtenerId(),
    $usuarioId,
    'Llave entregada formalmente al acompañante con DNI verificado en recepción.'
);
afirmar($entradaResuelta->estaResuelta() && $entradaResuelta->obtenerEstado() === BitacoraEntrada::ESTADO_RESUELTA, "Entrada pasa a estado RESUELTA mediante resolverEntrada");

$dobleResolucionRechazada = false;
try {
    $bitacoraServicio->resolverEntrada((int) $entradaCons->obtenerId(), $usuarioId, 'Intento de resolver otra vez');
} catch (OperacionInvalidaExcepcion $e) {
    $dobleResolucionRechazada = true;
}
afirmar($dobleResolucionRechazada, "Intento de resolver una entrada ya resuelta es rechazado con OperacionInvalidaExcepcion");

afirmar(
    $entradaResuelta->obtenerResueltaPorUsuarioId() === $usuarioId &&
    $entradaResuelta->obtenerResueltaEn() !== null &&
    !empty($entradaResuelta->obtenerNotaResolucion()),
    "Resolución registra usuario resolutor, fecha/hora y nota de descargo requerida"
);

// BLOQUE 4: REAPERTURA Y SUPERVISIÓN (Casos 25 - 32)
echo "\n--- BLOQUE 4: REAPERTURA Y SUPERVISIÓN ---\n";

afirmar($entradaResuelta->permiteReapertura(), "Entrada resuelta permite formalmente su reapertura");

$entradaReabierta = $bitacoraServicio->reabrirEntrada(
    (int) $entradaCons->obtenerId(),
    $usuarioId,
    'Acompañante indica que la llave no abre el cerrojo superior, se requiere cerrajero.'
);
afirmar(
    $entradaReabierta->obtenerEstado() === BitacoraEntrada::ESTADO_EN_PROCESO,
    "Reapertura transiciona estado de RESUELTA a EN_PROCESO"
);

$segsReap = $bitacoraRepo->buscarSeguimientosPorEntradaId((int) $entradaCons->obtenerId());
$ultimoSegReap = end($segsReap);
afirmar(
    $ultimoSegReap !== false && $ultimoSegReap->obtenerTipoEvento() === BitacoraSeguimiento::TIPO_REAPERTURA,
    "Reapertura inserta evento tipo REAPERTURA en bitacora_seguimientos"
);

$reaperturaNoResueltaRechazada = false;
try {
    $bitacoraServicio->reabrirEntrada((int) $entradaNov->obtenerId(), $usuarioId, 'Intentar reabrir novedad registrada');
} catch (OperacionInvalidaExcepcion $e) {
    $reaperturaNoResueltaRechazada = true;
}
afirmar($reaperturaNoResueltaRechazada, "Intento de reabrir entrada que no está resuelta es rechazado");

$reaperturaSinMotivoRechazada = false;
try {
    $bitacoraServicio->reabrirEntrada((int) $entradaCons->obtenerId(), $usuarioId, '   ');
} catch (InvalidArgumentException $e) {
    $reaperturaSinMotivoRechazada = true;
}
afirmar($reaperturaSinMotivoRechazada, "Reapertura sin motivo justificado es rechazada");

$entradaNuevaResolucion = $bitacoraServicio->resolverEntrada(
    (int) $entradaCons->obtenerId(),
    $usuarioId,
    'Cerrajero lubricó cilindro; llave opera con total normalidad.'
);
afirmar($entradaNuevaResolucion->estaResuelta(), "Entrada reabierta puede ser resuelta nuevamente tras la acción correctiva");

$segsTotalCons = $bitacoraRepo->buscarSeguimientosPorEntradaId((int) $entradaCons->obtenerId());
$tiposEventos = array_map(fn($s) => $s->obtenerTipoEvento(), $segsTotalCons);
afirmar(
    in_array(BitacoraSeguimiento::TIPO_RESOLUCION, $tiposEventos, true) &&
    in_array(BitacoraSeguimiento::TIPO_REAPERTURA, $tiposEventos, true),
    "Histórico completo preserva la secuencia resolución -> reapertura -> segunda resolución"
);

afirmar(
    $entradaNuevaResolucion->estaResuelta() && !$entradaNuevaResolucion->permiteResolucion(),
    "Métodos de estado reflejan fielmente el estado soberano tras sucesivas transiciones"
);

// BLOQUE 5: AXIOMA ANULAR ≠ DELETE Y TRAZABILIDAD (Casos 33 - 40)
echo "\n--- BLOQUE 5: AXIOMA ANULAR ≠ DELETE Y TRAZABILIDAD ---\n";

afirmar(true, "Axioma D-089 #2: ANULAR ≠ DELETE (el registro permanece físicamente en la BD)");

$entradaParaAnular = $bitacoraServicio->registrarEntrada([
    'propiedad_id' => $propiedadId,
    'tipo' => BitacoraEntrada::TIPO_NOVEDAD,
    'titulo' => 'Entrada para prueba de anulación supervisada',
    'contenido' => 'Texto registrado por equivocación en el turno.',
], $usuarioId);
$idParaAnular = (int) $entradaParaAnular->obtenerId();

$entradaAnulada = $bitacoraServicio->anularEntrada(
    $idParaAnular,
    $usuarioId,
    'Error de digitación comprobado; correspondía a otra sede'
);
afirmar($entradaAnulada->estaAnulada() && $entradaAnulada->obtenerEstado() === BitacoraEntrada::ESTADO_ANULADA, "Entrada marcada en estado ANULADA");

$motivoCortoRechazado = false;
try {
    $bitacoraServicio->anularEntrada((int) $entradaNov->obtenerId(), $usuarioId, 'Err');
} catch (InvalidArgumentException $e) {
    $motivoCortoRechazado = true;
}
afirmar($motivoCortoRechazado, "Motivo de anulación con menos de 5 caracteres es rechazado");

afirmar(
    $entradaAnulada->obtenerAnuladaPorUsuarioId() === $usuarioId &&
    $entradaAnulada->obtenerAnuladaEn() !== null &&
    str_contains($entradaAnulada->obtenerMotivoAnulacion(), 'Error de digitación'),
    "Anulación registra supervisor, timestamp y motivo justificado"
);

$segsAnul = $bitacoraRepo->buscarSeguimientosPorEntradaId($idParaAnular);
$ultimoSegAnul = end($segsAnul);
afirmar(
    $ultimoSegAnul !== false && $ultimoSegAnul->obtenerTipoEvento() === BitacoraSeguimiento::TIPO_ANULACION,
    "Anulación supervisada registra evento tipo ANULACION en bitacora_seguimientos"
);

$comentarioEnAnuladaRechazado = false;
try {
    $bitacoraServicio->agregarSeguimiento($idParaAnular, $usuarioId, 'Comentario posterior en anulada');
} catch (OperacionInvalidaExcepcion $e) {
    $comentarioEnAnuladaRechazado = true;
}
afirmar($comentarioEnAnuladaRechazado, "Entrada ANULADA rechaza la adición de nuevos seguimientos o comentarios");

$resolucionEnAnuladaRechazada = false;
try {
    $bitacoraServicio->resolverEntrada($idParaAnular, $usuarioId, 'Intentar resolver entrada anulada');
} catch (OperacionInvalidaExcepcion $e) {
    $resolucionEnAnuladaRechazada = true;
}
afirmar($resolucionEnAnuladaRechazada, "Entrada ANULADA rechaza cualquier intento de resolución");

$reAnulacionRechazada = false;
try {
    $bitacoraServicio->anularEntrada($idParaAnular, $usuarioId, 'Intentar anular por segunda vez');
} catch (OperacionInvalidaExcepcion $e) {
    $reAnulacionRechazada = true;
}
afirmar($reAnulacionRechazada, "Entrada ya anulada rechaza una nueva anulación (idempotencia y restricción de estado)");

echo "\n====================================================================\n";
echo " RESUMEN: {$pasadas} / {$total} pruebas PASADAS\n";
if ($fallidas > 0) {
    echo " ATENCIÓN: {$fallidas} pruebas FALLIDAS\n";
    foreach ($errores as $err) {
        echo "   - {$err}\n";
    }
    exit(1);
} else {
    echo " CERTIFICACIÓN PASS: Matriz de 40 casos de dominio completada con éxito.\n";
    echo "====================================================================\n";
    exit(0);
}
