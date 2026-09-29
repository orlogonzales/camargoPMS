<?php

declare(strict_types=1);

/**
 * Matriz de 40 casos de prueba para el Libro Diario de Devengos de Alojamiento y Cierres Hoteleros.
 * Gobernanza: D-090 / DEVENGO-ALOJAMIENTO-1.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use CamargoPMS\Excepciones\CierreHoteleroInvalidoExcepcion;
use CamargoPMS\Excepciones\DevengoDuplicadoExcepcion;
use CamargoPMS\Excepciones\EntidadNoEncontradaExcepcion;
use CamargoPMS\Excepciones\OperacionInvalidaExcepcion;
use CamargoPMS\Modelos\CierreHotelero;
use CamargoPMS\Modelos\DevengoAlojamiento;
use CamargoPMS\Modelos\Estadia;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Repositorios\ActorAuditoriaRepositorio;
use CamargoPMS\Repositorios\CierreHoteleroRepositorio;
use CamargoPMS\Repositorios\DevengoAlojamientoRepositorio;
use CamargoPMS\Repositorios\EstadiaRepositorio;
use CamargoPMS\Repositorios\ReservaRepositorio;
use CamargoPMS\Servicios\DevengoServicio;
use CamargoPMS\Servicios\NightAuditServicio;

Configuracion::cargar(__DIR__ . '/..');
$pdo = BaseDatos::conexion();

$pass = 0;
$fail = 0;

function assertTest(bool $condition, string $description, ?string $detail = null): void
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
echo " MATRIZ 40: DEVENGO DIARIO DE ALOJAMIENTO Y AUDITORÍA NOCTURNA (D-090)\n";
echo "====================================================================\n";

try {
    // -------------------------------------------------------------------------
    // Preparación de datos de prueba en sandbox seguro
    // -------------------------------------------------------------------------
    $devengoRepo = new DevengoAlojamientoRepositorio($pdo);
    $cierreRepo = new CierreHoteleroRepositorio($pdo);
    $devengoServicio = new DevengoServicio($pdo);
    $nightAuditServicio = new NightAuditServicio($pdo);

    // Obtener propiedad y usuario de prueba
    $propId = (int) $pdo->query('SELECT id FROM propiedades WHERE estado = "ACTIVO" LIMIT 1')->fetchColumn() ?: 1;
    $unidadId = (int) $pdo->query('SELECT id FROM unidades WHERE propiedad_id = ' . $propId . ' AND estado = "ACTIVO" LIMIT 1')->fetchColumn() ?: 1;
    $personaId = (int) $pdo->query('SELECT id FROM personas LIMIT 1')->fetchColumn() ?: 1;
    $actorId = (int) $pdo->query('SELECT id FROM actores LIMIT 1')->fetchColumn() ?: 1;

    // Limpieza preventiva de ejecuciones anteriores
    $pdo->exec('UPDATE devengos_alojamiento SET reverso_de_id = NULL WHERE estadia_id IN (SELECT id FROM estadias WHERE codigo LIKE "EST-DEV-%")');
    $pdo->exec('DELETE FROM devengos_alojamiento WHERE estadia_id IN (SELECT id FROM estadias WHERE codigo LIKE "EST-DEV-%") OR cierre_hotelero_id IN (SELECT id FROM cierres_hoteleros WHERE observaciones LIKE "%Cierre regular de turno noche%")');
    $pdo->exec('DELETE FROM cierres_hoteleros WHERE observaciones LIKE "%Cierre regular de turno noche%" OR observaciones LIKE "%Reintento%"');
    $pdo->exec('DELETE FROM estadias WHERE codigo LIKE "EST-DEV-%"');
    $pdo->exec('DELETE FROM reserva_unidades WHERE reserva_id IN (SELECT id FROM reservas WHERE codigo LIKE "RES-DEV-%")');
    $pdo->exec('DELETE FROM reservas WHERE codigo LIKE "RES-DEV-%"');

    // Crear reserva y estadía de prueba de 3 noches: 2026-10-10 al 2026-10-13 (150 PEN / noche)
    $pdo->beginTransaction();

    $stmtRes = $pdo->prepare('INSERT INTO reservas (
        codigo, persona_titular_id, fecha_entrada, fecha_salida, noches, estado, canal, origen, moneda_codigo, subtotal, total
    ) VALUES (
        :cod, :pid, "2026-10-10", "2026-10-13", 3, "CONFIRMADA", "PMS", "DIRECTO", "PEN", 450.00, 450.00
    )');
    $resCod = 'RES-DEV-' . uniqid();
    $stmtRes->execute(['cod' => $resCod, 'pid' => $personaId]);
    $reservaId = (int) $pdo->lastInsertId();

    $stmtUni = $pdo->prepare('INSERT INTO reserva_unidades (
        reserva_id, unidad_id, precio_unitario_noche, noches, subtotal, impuesto, total, moneda_codigo
    ) VALUES (
        :rid, :uid, 150.00, 3, 450.00, 0.00, 450.00, "PEN"
    )');
    $stmtUni->execute(['rid' => $reservaId, 'uid' => $unidadId]);
    $reservaUnidadId = (int) $pdo->lastInsertId();

    $stmtEst = $pdo->prepare('INSERT INTO estadias (
        codigo, reserva_id, reserva_unidad_id, unidad_id, fecha_entrada, fecha_salida_prevista, estado, checkin_en, checkin_por_actor_id
    ) VALUES (
        :cod, :rid, :ruid, :uid, "2026-10-10", "2026-10-13", "EN_CURSO", "2026-10-10 14:00:00", :aid
    )');
    $estCod = 'EST-DEV-' . uniqid();
    $stmtEst->execute([
        'cod' => $estCod,
        'rid' => $reservaId,
        'ruid' => $reservaUnidadId,
        'uid' => $unidadId,
        'aid' => $actorId,
    ]);
    $estadiaId = (int) $pdo->lastInsertId();

    $pdo->commit();

    // -------------------------------------------------------------------------
    // GRUPO 1: Esquema, Integridad y Base de Datos (Casos 1 a 5)
    // -------------------------------------------------------------------------
    echo "\n--- GRUPO 1: Esquema, Integridad y Base de Datos ---\n";

    $stmtTablas = $pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = "BASE TABLE"');
    $totalTablas = (int) $stmtTablas->fetchColumn();
    assertTest($totalTablas >= 108, "CASO 01: La base de datos contiene al menos 108 tablas ($totalTablas registradas)");

    $stmtFloat = $pdo->query('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND data_type IN ("float", "double")');
    $floats = (int) $stmtFloat->fetchColumn();
    assertTest($floats === 0, 'CASO 02: Cero columnas FLOAT o DOUBLE prohibidas en el esquema');

    $chkCierres = $pdo->query('SHOW TABLES LIKE "cierres_hoteleros"')->fetchColumn();
    assertTest($chkCierres === 'cierres_hoteleros', 'CASO 03: Tabla cierres_hoteleros existe en el catálogo');

    $chkDevengos = $pdo->query('SHOW TABLES LIKE "devengos_alojamiento"')->fetchColumn();
    assertTest($chkDevengos === 'devengos_alojamiento', 'CASO 04: Tabla devengos_alojamiento existe en el catálogo');

    $stmtFk = $pdo->query('SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = DATABASE() AND constraint_type = "FOREIGN KEY"');
    $totalFks = (int) $stmtFk->fetchColumn();
    assertTest($totalFks >= 282, "CASO 05: Claves foráneas activas mayores o iguales a 282 (detectadas: $totalFks)");

    // -------------------------------------------------------------------------
    // GRUPO 2: Semántica Semiabierta y Límites de Fechas (Casos 6 a 12)
    // -------------------------------------------------------------------------
    echo "\n--- GRUPO 2: Semántica Semiabierta [fecha_entrada, fecha_salida) ---\n";

    $devNoche1 = $devengoServicio->devengarNoche($estadiaId, '2026-10-10', $actorId);
    assertTest($devNoche1 instanceof DevengoAlojamiento, 'CASO 06: Devengo exitoso de la noche 1 (2026-10-10)');
    assertTest($devNoche1->obtenerNocheIndice() === 1, 'CASO 07: Índice de noche 1 correcto');
    assertTest($devNoche1->obtenerImporteNeto() === '150.00', 'CASO 08: Importe neto 150.00 PEN en noche 1');

    $devNoche2 = $devengoServicio->devengarNoche($estadiaId, '2026-10-11', $actorId);
    assertTest($devNoche2->obtenerNocheIndice() === 2, 'CASO 09: Devengo exitoso de la noche 2 (2026-10-11) con índice 2');

    $devNoche3 = $devengoServicio->devengarNoche($estadiaId, '2026-10-12', $actorId);
    assertTest($devNoche3->obtenerNocheIndice() === 3, 'CASO 10: Devengo exitoso de la noche 3 (2026-10-12) con índice 3');

    $salidaRechazada = false;
    try {
        $devengoServicio->devengarNoche($estadiaId, '2026-10-13', $actorId);
    } catch (OperacionInvalidaExcepcion $e) {
        $salidaRechazada = true;
    }
    assertTest($salidaRechazada, 'CASO 11: Fecha de salida (2026-10-13) es rechazada categóricamente (no devenga noche)');

    $previaRechazada = false;
    try {
        $devengoServicio->devengarNoche($estadiaId, '2026-10-09', $actorId);
    } catch (OperacionInvalidaExcepcion $e) {
        $previaRechazada = true;
    }
    assertTest($previaRechazada, 'CASO 12: Fecha anterior al check-in (2026-10-09) es rechazada');

    // -------------------------------------------------------------------------
    // GRUPO 3: Idempotencia y Formato de Código (Casos 13 a 17)
    // -------------------------------------------------------------------------
    echo "\n--- GRUPO 3: Idempotencia y Formato de Código ---\n";

    $devReintento = $devengoServicio->devengarNoche($estadiaId, '2026-10-10', $actorId);
    assertTest($devReintento->obtenerId() === $devNoche1->obtenerId(), 'CASO 13: Idempotencia: re-ejecución devuelve el mismo devengo sin duplicar');

    assertTest(str_starts_with($devNoche1->obtenerCodigo(), 'DEV-'), 'CASO 14: Código de devengo inicia con prefijo DEV-');
    assertTest(strlen($devNoche1->obtenerCodigo()) >= 15, 'CASO 15: Longitud canónica del código de devengo');
    assertTest($devNoche1->obtenerMonedaCodigo() === 'PEN', 'CASO 16: Moneda funcional fijada a PEN');
    assertTest($devNoche1->estaDevengado(), 'CASO 17: Estado de devengo inicial es DEVENGADO');

    // -------------------------------------------------------------------------
    // GRUPO 4: Reversiones Supervisadas (Append-Only / D-061) (Casos 18 a 22)
    // -------------------------------------------------------------------------
    echo "\n--- GRUPO 4: Reversiones Supervisadas ---\n";

    $devRevertido = $devengoServicio->revertirDevengo((int) $devNoche3->obtenerId(), 'Ajuste comercial por inconformidad de ruido', $actorId);
    assertTest($devRevertido->estaRevertido(), 'CASO 18: Reversión supervisada cambia estado a REVERTIDO');
    assertTest($devRevertido->obtenerMotivoReversion() === 'Ajuste comercial por inconformidad de ruido', 'CASO 19: Motivo de reversión registrado inmutablemente');

    $segundaRevRechazada = false;
    try {
        $devengoServicio->revertirDevengo((int) $devNoche3->obtenerId(), 'Re-reversión', $actorId);
    } catch (OperacionInvalidaExcepcion) {
        $segundaRevRechazada = true;
    }
    assertTest($segundaRevRechazada, 'CASO 20: Intento de revertir devengo ya revertido es rechazado');

    $revSinMotivoRechazada = false;
    try {
        $devengoServicio->revertirDevengo((int) $devNoche2->obtenerId(), '   ', $actorId);
    } catch (OperacionInvalidaExcepcion) {
        $revSinMotivoRechazada = true;
    }
    assertTest($revSinMotivoRechazada, 'CASO 21: Reversión sin motivo justificado es rechazada');

    // Re-devengar la noche 3 tras la reversión -> genera secuencia 2
    $devNoche3Nueva = $devengoServicio->devengarNoche($estadiaId, '2026-10-12', $actorId);
    assertTest($devNoche3Nueva->obtenerSecuencia() === 2, 'CASO 22: Corrección posterior genera secuencia incremental 2 (append-only)');

    // -------------------------------------------------------------------------
    // GRUPO 5: Auditoría Nocturna (Night Audit) y Cierres (Casos 23 a 30)
    // -------------------------------------------------------------------------
    echo "\n--- GRUPO 5: Auditoría Nocturna (Night Audit) ---\n";

    $fechaAudit = '2026-10-10';
    $cierre = $nightAuditServicio->ejecutarCierre($propId, $fechaAudit, $actorId, 'Cierre regular de turno noche');
    assertTest($cierre instanceof CierreHotelero, 'CASO 23: Ejecución de Night Audit retorna entidad CierreHotelero');
    assertTest($cierre->estaCerrado(), 'CASO 24: Cierre hotelero finaliza en estado CERRADO');
    assertTest($cierre->obtenerTotalEstadiasProcesadas() >= 1, 'CASO 25: Estadías procesadas en el cierre mayor a cero');
    assertTest($cierre->obtenerTimezoneUtilizada() === 'America/Lima', 'CASO 26: Timezone registrada en el cierre es America/Lima');

    $cierreDuplicadoRechazado = false;
    try {
        $nightAuditServicio->ejecutarCierre($propId, $fechaAudit, $actorId, 'Reintento');
    } catch (CierreHoteleroInvalidoExcepcion) {
        $cierreDuplicadoRechazado = true;
    }
    assertTest($cierreDuplicadoRechazado, 'CASO 27: Cierre duplicado para misma propiedad y fecha es rechazado');

    $ultimoCierre = $nightAuditServicio->obtenerUltimoCierre($propId);
    assertTest($ultimoCierre !== null && $ultimoCierre->obtenerFechaHotelera() === $fechaAudit, 'CASO 28: Consulta de último cierre exitoso recupera fecha correcta');

    $cierrePorFecha = $nightAuditServicio->obtenerCierrePorFecha($propId, $fechaAudit);
    assertTest($cierrePorFecha !== null && $cierrePorFecha->obtenerId() === $cierre->obtenerId(), 'CASO 29: Búsqueda de cierre por propiedad y fecha');

    $cierresLista = $nightAuditServicio->listarCierres($propId, 10);
    assertTest(count($cierresLista) >= 1, 'CASO 30: Listado histórico de cierres hoteleros contiene elementos');

    // -------------------------------------------------------------------------
    // GRUPO 6: Trazabilidad D-061 y RBAC (Casos 31 a 35)
    // -------------------------------------------------------------------------
    echo "\n--- GRUPO 6: Trazabilidad D-061 y RBAC ---\n";

    $stmtAud = $pdo->prepare('SELECT COUNT(*) FROM auditoria WHERE accion = :acc');
    $stmtAud->execute(['acc' => 'DEVENGO_ALOJAMIENTO_GENERADO']);
    assertTest((int) $stmtAud->fetchColumn() >= 1, 'CASO 31: Evento DEVENGO_ALOJAMIENTO_GENERADO registrado en auditoría D-061');

    $stmtAud->execute(['acc' => 'DEVENGO_ALOJAMIENTO_REVERTIDO']);
    assertTest((int) $stmtAud->fetchColumn() >= 1, 'CASO 32: Evento DEVENGO_ALOJAMIENTO_REVERTIDO registrado en auditoría D-061');

    $stmtAud->execute(['acc' => 'NIGHT_AUDIT_COMPLETADO']);
    assertTest((int) $stmtAud->fetchColumn() >= 1, 'CASO 33: Evento NIGHT_AUDIT_COMPLETADO registrado en auditoría D-061');

    $stmtPerm = $pdo->query('SELECT COUNT(*) FROM permisos WHERE codigo IN ("devengo.ver", "devengo.ejecutar", "devengo.revertir", "night_audit.ver", "night_audit.ejecutar")');
    assertTest((int) $stmtPerm->fetchColumn() === 5, 'CASO 34: Los 5 permisos RBAC de devengo y auditoría nocturna existen');

    $stmtMenu = $pdo->query('SELECT COUNT(*) FROM opciones_menu WHERE clave = "operaciones_night_audit"');
    assertTest((int) $stmtMenu->fetchColumn() === 1, 'CASO 35: Opción de menú operaciones_night_audit registrada');

    // -------------------------------------------------------------------------
    // GRUPO 7: Modificaciones de Estadía e Integridad Histórica (Casos 36 a 40)
    // -------------------------------------------------------------------------
    echo "\n--- GRUPO 7: Modificaciones de Estadía e Inmutabilidad ---\n";

    $devengosEstadia = $devengoServicio->listarDevengosEstadia($estadiaId);
    assertTest(count($devengosEstadia) >= 3, 'CASO 36: Listado de devengos por estadía retorna histórico completo');

    assertTest($devNoche1->obtenerMetodoDevengo() === DevengoAlojamiento::METODO_DEV_NIGHT_AUDIT, 'CASO 37: Método de devengo NIGHT_AUDIT correctamente tipificado');

    // Comprobar devengo manual supervisado
    $devManual = $devengoServicio->devengarNoche($estadiaId, '2026-10-11', $actorId, DevengoAlojamiento::METODO_DEV_MANUAL_SUPERVISADO);
    assertTest($devManual !== null, 'CASO 38: Devengo idempotente manual preserva integridad');

    // Comprobación de que no se puede devengar para estadía inexistente
    $estadiaInexistenteRechazada = false;
    try {
        $devengoServicio->devengarNoche(999999, '2026-10-10', $actorId);
    } catch (EntidadNoEncontradaExcepcion) {
        $estadiaInexistenteRechazada = true;
    }
    assertTest($estadiaInexistenteRechazada, 'CASO 39: Estadía inexistente rechaza devengo con EntidadNoEncontradaExcepcion');

    // Comprobación de formato de fecha hotelera inválido en Night Audit
    $formatoFechaInvalido = false;
    try {
        $nightAuditServicio->ejecutarCierre($propId, '10-10-2026', $actorId);
    } catch (CierreHoteleroInvalidoExcepcion) {
        $formatoFechaInvalido = true;
    }
    assertTest($formatoFechaInvalido, 'CASO 40: Fecha hotelera en formato no ISO YYYY-MM-DD es rechazada en Night Audit');

} catch (Throwable $e) {
    $fail++;
    echo "\n[ERROR CRÍTICO EN EJECUCIÓN DE MATRIZ 40]: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
} finally {
    try {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $pdo->exec('UPDATE devengos_alojamiento SET reverso_de_id = NULL WHERE estadia_id IN (SELECT id FROM estadias WHERE codigo LIKE "EST-DEV-%")');
        $pdo->exec('DELETE FROM devengos_alojamiento WHERE estadia_id IN (SELECT id FROM estadias WHERE codigo LIKE "EST-DEV-%") OR cierre_hotelero_id IN (SELECT id FROM cierres_hoteleros WHERE observaciones LIKE "%Cierre regular de turno noche%")');
        $pdo->exec('DELETE FROM cierres_hoteleros WHERE observaciones LIKE "%Cierre regular de turno noche%" OR observaciones LIKE "%Reintento%"');
        $pdo->exec('DELETE FROM estadias WHERE codigo LIKE "EST-DEV-%"');
        $pdo->exec('DELETE FROM reserva_unidades WHERE reserva_id IN (SELECT id FROM reservas WHERE codigo LIKE "RES-DEV-%")');
        $pdo->exec('DELETE FROM reservas WHERE codigo LIKE "RES-DEV-%"');
    } catch (Throwable) {
    }
}

echo "\n====================================================================\n";
echo "RESULTADO MATRIZ 40: $pass PASS | $fail FAIL\n";
echo "====================================================================\n";

if ($fail > 0) {
    exit(1);
}
exit(0);
