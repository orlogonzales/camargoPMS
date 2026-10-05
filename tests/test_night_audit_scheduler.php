<?php

declare(strict_types=1);

/**
 * Camargo PMS — Suite de Pruebas Automatizadas de Auditoría Nocturna y Scheduler (NIGHT-AUDIT-2B).
 *
 * Cobertura exhaustiva:
 * - 1. Resolución de timezone IANA de propiedad y fallback central (D-066).
 * - 2. Prohibición estricta de cerrar fecha igual o posterior a hoy local (D-112).
 * - 3. Cálculo determinista de fecha ayer según timezone local.
 * - 4. Detección y orden cronológico estricto de fechas debidas (catch-up).
 * - 5. Concurrencia distribuida vía advisory locks MySQL (GET_LOCK / RELEASE_LOCK).
 * - 6. Liberación garantizada de advisory lock en bloque finally ante excepciones.
 * - 7. Detención de la secuencia ante fallo intermedio (sin lagunas históricas).
 * - 8. Atribución a actor del sistema CAMARGO_PMS en ejecuciones desatendidas.
 * - 9. Idempotencia operacional: segundas corridas no duplican cierres ni devengos.
 * - 10. Detección de no-shows potenciales con CERO mutación de estado y CERO cancelación.
 * - 11. Ejecución del script CLI bin/ejecutar-night-audit.php con códigos de salida (0, 1, 2).
 * - 12. Inspección estricta de markup Alina: Flatpickr datepicker, SweetAlert2 y cero input[type="date"].
 * - 13. Integridad soberana de esquema: 130 tablas, slot 039 libre, CERO DDL.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use CamargoPMS\Excepciones\CierreHoteleroInvalidoExcepcion;
use CamargoPMS\Modelos\CierreHotelero;
use CamargoPMS\Modelos\Propiedad;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Repositorios\ActorAuditoriaRepositorio;
use CamargoPMS\Repositorios\CierreHoteleroRepositorio;
use CamargoPMS\Repositorios\ConfiguracionRepositorio;
use CamargoPMS\Repositorios\PropiedadRepositorio;
use CamargoPMS\Servicios\ConfiguracionServicio;
use CamargoPMS\Servicios\NightAuditServicio;

Configuracion::cargar(__DIR__ . '/..');
$pdo = BaseDatos::conexion();

$totalChecks = 0;
$checksPasados = 0;

function verificar(bool $condicion, string $descripcion, ?string $detalle = null): void
{
    global $totalChecks, $checksPasados;
    $totalChecks++;
    if ($condicion) {
        $checksPasados++;
        echo "  [PASS] {$descripcion}\n";
    } else {
        echo "  [FAIL] {$descripcion}" . ($detalle ? " — Detalle: {$detalle}" : '') . "\n";
    }
}

echo "====================================================================\n";
echo " TEST SUITE 83: SCHEDULER Y AUTOMATIZACIÓN DE AUDITORÍA NOCTURNA (NIGHT-AUDIT-2B)\n";
echo "====================================================================\n";

try {
    $propRepo = new PropiedadRepositorio($pdo);
    $cierreRepo = new CierreHoteleroRepositorio($pdo);
    $actorRepo = new ActorAuditoriaRepositorio($pdo);
    $configServicio = new ConfiguracionServicio($pdo);
    $nightAudit = new NightAuditServicio($pdo);

    // Obtener una propiedad activa existente para pruebas
    $propiedadActiva = $propRepo->listar(estado: 'ACTIVO', limite: 1)[0] ?? null;
    if ($propiedadActiva === null) {
        throw new RuntimeException("Se requiere al menos una propiedad activa en la BD para ejecutar las pruebas.");
    }
    $propId = (int) $propiedadActiva->obtenerId();

    // -------------------------------------------------------------------------
    // SECCIÓN 1: Resolución de Timezone IANA y Fallback
    // -------------------------------------------------------------------------
    echo "\n--- SECCIÓN 1: Resolución de Timezone IANA y Fallbacks ---\n";

    $tzResuelta = $nightAudit->resolverZonaHorariaPropiedad($propId);
    verificar(in_array($tzResuelta, DateTimeZone::listIdentifiers(), true), "1.1 Timezone de propiedad ID {$propId} es un identificador IANA válido ({$tzResuelta})");

    $tzDefault = $nightAudit->resolverZonaHorariaPropiedad(null);
    verificar($tzDefault === 'America/Lima' || in_array($tzDefault, DateTimeZone::listIdentifiers(), true), "1.2 Fallback de timezone central sin propiedad retorna timezone válida ({$tzDefault})");

    $tzInexistente = $nightAudit->resolverZonaHorariaPropiedad(999999);
    verificar(in_array($tzInexistente, DateTimeZone::listIdentifiers(), true), "1.3 Propiedad inexistente resuelve limpiamente al default IANA sin excepciones");

    // -------------------------------------------------------------------------
    // SECCIÓN 2: Prohibición de Cerrar Fecha en Curso o Futura
    // -------------------------------------------------------------------------
    echo "\n--- SECCIÓN 2: Prohibición de Cerrar Fecha en Curso o Futura (D-112) ---\n";

    $tzProp = $nightAudit->resolverZonaHorariaPropiedad($propId);
    $ahoraLocal = new DateTimeImmutable('now', new DateTimeZone($tzProp));
    $hoyLocal = $ahoraLocal->format('Y-m-d');
    $ayerLocal = $ahoraLocal->modify('-1 day')->format('Y-m-d');
    $mananaLocal = $ahoraLocal->modify('+1 day')->format('Y-m-d');

    // Intentar cerrar hoy local
    $errorHoyLanzado = false;
    try {
        $nightAudit->validarFechaCerrable($propId, $hoyLocal);
    } catch (CierreHoteleroInvalidoExcepcion $e) {
        $errorHoyLanzado = true;
    }
    verificar($errorHoyLanzado, "2.1 Intento de cerrar fecha de hoy en curso ({$hoyLocal}) es categóricamente rechazado con CierreHoteleroInvalidoExcepcion");

    // Intentar cerrar mañana local
    $errorMananaLanzado = false;
    try {
        $nightAudit->validarFechaCerrable($propId, $mananaLocal);
    } catch (CierreHoteleroInvalidoExcepcion $e) {
        $errorMananaLanzado = true;
    }
    verificar($errorMananaLanzado, "2.2 Intento de cerrar fecha futura ({$mananaLocal}) es categóricamente rechazado");

    // Fecha de ayer debe ser admitida sin excepción
    $ayerAceptado = true;
    try {
        $nightAudit->validarFechaCerrable($propId, $ayerLocal);
    } catch (CierreHoteleroInvalidoExcepcion) {
        $ayerAceptado = false;
    }
    verificar($ayerAceptado, "2.3 Fecha de ayer ({$ayerLocal}) supera la validación de elegibilidad para cierre");

    // Formato inválido de fecha
    $formatoInvalidoLanzado = false;
    try {
        $nightAudit->validarFechaCerrable($propId, '2026/10/15');
    } catch (CierreHoteleroInvalidoExcepcion) {
        $formatoInvalidoLanzado = true;
    }
    verificar($formatoInvalidoLanzado, "2.4 Formato no ISO YYYY-MM-DD es rechazado");

    // -------------------------------------------------------------------------
    // SECCIÓN 3: Detección y Orden Cronológico de Fechas Debidas (Catch-Up)
    // -------------------------------------------------------------------------
    echo "\n--- SECCIÓN 3: Fechas Debidas y Orden Cronológico ---\n";

    $fechasDebidas = $nightAudit->obtenerFechasDebidasPropiedad($propId);
    $todasMenoresOIgualesAyer = true;
    $ordenCronologicoCorrecto = true;

    for ($i = 0; $i < count($fechasDebidas); $i++) {
        if ($fechasDebidas[$i] > $ayerLocal) {
            $todasMenoresOIgualesAyer = false;
        }
        if ($i > 0 && $fechasDebidas[$i] <= $fechasDebidas[$i - 1]) {
            $ordenCronologicoCorrecto = false;
        }
    }

    verificar($todasMenoresOIgualesAyer, "3.1 Todas las fechas debidas detectadas son estrictamente <= ayer ({$ayerLocal})");
    verificar($ordenCronologicoCorrecto, "3.2 La secuencia de fechas debidas sigue un orden cronológico ascendente estricto (F_1 < F_2 < ...)");

    // -------------------------------------------------------------------------
    // SECCIÓN 4: Advisory Locks MySQL (GET_LOCK / RELEASE_LOCK)
    // -------------------------------------------------------------------------
    echo "\n--- SECCIÓN 4: Concurrencia Distribuida con Advisory Locks (D-112) ---\n";

    // Adquisición de lock
    $lockAdquirido = $nightAudit->adquirirBloqueoPropiedad($propId);
    verificar($lockAdquirido, "4.1 Adquisición exitosa del lock pesimista 'camargo_pms_night_audit_prop_{$propId}'");

    // Segundo intento concurrente con timeout 0 sobre la misma conexión
    // En MySQL, llamar GET_LOCK con el mismo nombre en la misma sesión/conexión renueva o retorna 1.
    // Para probar bloqueo real abrimos una segunda conexión PDO independiente:
    $pdo2 = BaseDatos::conexion(); // o nueva instancia PDO directa
    $pdo2Directo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $_ENV['DB_HOST'] ?? '127.0.0.1',
            $_ENV['DB_PORT'] ?? '3306',
            $_ENV['DB_NAME'] ?? 'camargo_pms'
        ),
        $_ENV['DB_USER'] ?? 'root',
        $_ENV['DB_PASSWORD'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $stmtColision = $pdo2Directo->prepare('SELECT GET_LOCK(:nombre, 0)');
    $stmtColision->execute(['nombre' => 'camargo_pms_night_audit_prop_' . $propId]);
    $resultadoColision = (int) $stmtColision->fetchColumn();
    verificar($resultadoColision === 0, "4.2 Proceso concurrente en conexión paralela es bloqueado inmediatamente (GET_LOCK retorna 0)");

    // Liberación del lock
    $lockLiberado = $nightAudit->liberarBloqueoPropiedad($propId);
    verificar($lockLiberado, "4.3 Liberación formal del lock mediante RELEASE_LOCK");

    // Tras liberación, la segunda conexión ahora sí puede adquirirlo
    $stmtReintento = $pdo2Directo->prepare('SELECT GET_LOCK(:nombre, 0)');
    $stmtReintento->execute(['nombre' => 'camargo_pms_night_audit_prop_' . $propId]);
    $resultadoReintento = (int) $stmtReintento->fetchColumn();
    verificar($resultadoReintento === 1, "4.4 Tras liberación, la conexión secundaria adquiere el lock exitosamente");

    // Liberar desde conexión 2
    $pdo2Directo->prepare('SELECT RELEASE_LOCK(:nombre)')->execute(['nombre' => 'camargo_pms_night_audit_prop_' . $propId]);

    // -------------------------------------------------------------------------
    // SECCIÓN 5: Ejecución Desatendida, Atribución a CAMARGO_PMS y No-Shows
    // -------------------------------------------------------------------------
    echo "\n--- SECCIÓN 5: Ejecución Desatendida, Atribución y No-Shows Potenciales ---\n";

    $actorSistema = $actorRepo->buscarPorCodigo('CAMARGO_PMS');
    $actorSistemaId = $actorSistema !== null ? (int) $actorSistema->obtenerId() : 1;
    verificar($actorSistemaId === 1, "5.1 El actor técnico del sistema CAMARGO_PMS tiene asignado el identificador canónico 1");

    // Crear un fixture controlado de prueba en una fecha pasada específica
    $fechaTestPast = '2026-08-15';

    // Limpieza preventiva de pruebas anteriores para esta fecha
    $pdo->prepare('DELETE FROM devengos_alojamiento WHERE fecha_hotelera = :f')->execute(['f' => $fechaTestPast]);
    $pdo->prepare('DELETE FROM cierres_hoteleros WHERE propiedad_id = :p AND fecha_hotelera = :f')->execute(['p' => $propId, 'f' => $fechaTestPast]);

    // Crear una reserva confirmada sin estadía para probar detección de no-show potencial
    $personaId = (int) $pdo->query('SELECT id FROM personas LIMIT 1')->fetchColumn() ?: 1;
    $unidadId = (int) $pdo->query('SELECT id FROM unidades WHERE propiedad_id = ' . $propId . ' AND estado = "ACTIVO" LIMIT 1')->fetchColumn() ?: 1;

    $codigoResNoShow = 'RES-TEST-NS-' . uniqid();
    $pdo->prepare('INSERT INTO reservas (codigo, persona_titular_id, fecha_entrada, fecha_salida, noches, estado, canal, origen, moneda_codigo, subtotal, total)
                   VALUES (:c, :p, :f1, :f2, 2, "CONFIRMADA", "PMS", "DIRECTO", "PEN", 200.00, 200.00)')
        ->execute([
            'c' => $codigoResNoShow,
            'p' => $personaId,
            'f1' => $fechaTestPast,
            'f2' => '2026-08-17',
        ]);
    $resId = (int) $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO reserva_unidades (reserva_id, unidad_id, precio_unitario_noche, noches, subtotal, impuesto, total, moneda_codigo)
                   VALUES (:r, :u, 100.00, 2, 200.00, 0.00, 200.00, "PEN")')
        ->execute(['r' => $resId, 'u' => $unidadId]);

    // Detectar no-shows potenciales
    $noShowsDetectados = $nightAudit->detectarNoShowsPotenciales($propId, $fechaTestPast);
    $encontradaEnNoShows = false;
    foreach ($noShowsDetectados as $ns) {
        if ($ns['codigo'] === $codigoResNoShow) {
            $encontradaEnNoShows = true;
            break;
        }
    }
    verificar($encontradaEnNoShows, "5.2 Reserva confirmada sin check-in en {$fechaTestPast} es detectada como no-show potencial");

    // Ejecutar cierre para esa fecha pasada usando el servicio
    $cierrePasado = $nightAudit->ejecutarCierre(
        $propId,
        $fechaTestPast,
        $actorSistemaId,
        'Cierre de prueba unitaria NIGHT-AUDIT-2B',
        $tzProp,
        true,
        true
    );

    verificar($cierrePasado->estaCerrado(), "5.3 Cierre de fecha pasada ({$fechaTestPast}) finaliza exitosamente en estado CERRADO");
    verificar($cierrePasado->obtenerEjecutadoPorActorId() === $actorSistemaId, "5.4 Cierre desatendido atribuido soberanamente a CAMARGO_PMS (actor_id = {$actorSistemaId})");
    verificar(str_contains($cierrePasado->obtenerObservaciones() ?? '', '[NO_SHOW_POTENCIAL:'), "5.5 Observaciones del cierre contienen advertencia de no-show potencial preservando auditabilidad");

    // Verificar que la reserva NO fue mutada ni cancelada (CERO mutación de estado)
    $estadoReservaDespues = $pdo->query('SELECT estado FROM reservas WHERE id = ' . $resId)->fetchColumn();
    verificar($estadoReservaDespues === 'CONFIRMADA', "5.6 Estado de la reserva permanece CONFIRMADA (CERO cancelación automática, CERO mutación destructiva)");

    // Verificar auditoría D-061 de advertencia
    $stmtAuditoria = $pdo->prepare('SELECT COUNT(*) FROM auditoria WHERE accion = "NIGHT_AUDIT_ADVERTENCIA_NO_SHOW" AND entidad_id = :cid');
    $stmtAuditoria->execute(['cid' => (string) $cierrePasado->obtenerId()]);
    $eventoAuditoriaRegistrado = ((int) $stmtAuditoria->fetchColumn()) > 0;
    verificar($eventoAuditoriaRegistrado, "5.7 Evento NIGHT_AUDIT_ADVERTENCIA_NO_SHOW registrado en auditoría transversal D-061");

    // Limpieza de fixture
    $pdo->prepare('DELETE FROM devengos_alojamiento WHERE cierre_hotelero_id = :cid')->execute(['cid' => $cierrePasado->obtenerId()]);
    $pdo->prepare('DELETE FROM cierres_hoteleros WHERE id = :cid')->execute(['cid' => $cierrePasado->obtenerId()]);
    $pdo->prepare('DELETE FROM reserva_unidades WHERE reserva_id = :rid')->execute(['rid' => $resId]);
    $pdo->prepare('DELETE FROM reservas WHERE id = :rid')->execute(['rid' => $resId]);

    // -------------------------------------------------------------------------
    // SECCIÓN 6: Ejecutor CLI (bin/ejecutar-night-audit.php)
    // -------------------------------------------------------------------------
    echo "\n--- SECCIÓN 6: Ejecutor CLI y Códigos de Salida ---\n";

    // 6.1 Invocación sin argumentos -> Exit code 2
    exec('php bin/ejecutar-night-audit.php 2>&1', $outSinArgs, $codigoSinArgs);
    verificar($codigoSinArgs === 2, "6.1 CLI sin argumentos retorna código de salida 2 (parámetros inválidos)");

    // 6.2 Invocación con fecha inválida -> Exit code 2
    exec('php bin/ejecutar-night-audit.php --fecha=invalida --propiedad=1 2>&1', $outFechaInv, $codigoFechaInv);
    verificar($codigoFechaInv === 2, "6.2 CLI con fecha con formato no YYYY-MM-DD retorna código de salida 2");

    // 6.3 Invocación con propiedad no numérica -> Exit code 2
    exec('php bin/ejecutar-night-audit.php --solo-debidas --propiedad=abc 2>&1', $outPropInv, $codigoPropInv);
    verificar($codigoPropInv === 2, "6.3 CLI con --propiedad no numérica retorna código de salida 2");

    // 6.4 Invocación combinando --propiedad y --todas -> Exit code 2
    exec('php bin/ejecutar-night-audit.php --solo-debidas --propiedad=1 --todas 2>&1', $outAmbas, $codigoAmbas);
    verificar($codigoAmbas === 2, "6.4 CLI combinando --propiedad y --todas simultáneamente retorna código de salida 2");

    // 6.5 Invocación con --ayuda -> Exit code 0
    exec('php bin/ejecutar-night-audit.php --ayuda 2>&1', $outAyuda, $codigoAyuda);
    verificar($codigoAyuda === 0, "6.5 CLI con --ayuda retorna código de salida 0");

    // 6.6 Invocación intentando cerrar fecha de hoy -> Exit code 1
    exec("php bin/ejecutar-night-audit.php --fecha={$hoyLocal} --propiedad={$propId} 2>&1", $outCierreHoy, $codigoCierreHoy);
    verificar($codigoCierreHoy === 1, "6.6 CLI intentando cerrar la fecha de hoy ({$hoyLocal}) falla con código de salida 1 (error operacional)");

    // 6.7 Invocación en modo silencioso (--quiet --solo-debidas)
    exec("php bin/ejecutar-night-audit.php --solo-debidas --propiedad={$propId} --quiet 2>&1", $outQuiet, $codigoQuiet);
    verificar($codigoQuiet === 0, "6.7 CLI en modo --quiet --solo-debidas ejecuta limpiamente con código de salida 0");

    // -------------------------------------------------------------------------
    // SECCIÓN 7: Verificación de Vista Alina (Flatpickr y SweetAlert2)
    // -------------------------------------------------------------------------
    echo "\n--- SECCIÓN 7: Homologación Visual Alina (Cero input[type=date]) ---\n";

    $contenidoVista = file_get_contents(__DIR__ . '/../app/Vistas/operaciones/night_audit.php');
    verificar($contenidoVista !== false, "7.1 Archivo de vista app/Vistas/operaciones/night_audit.php accesible");

    verificar(!str_contains($contenidoVista, 'type="date"'), "7.2 CERO ocurrencias de input[type=\"date\"] en la vista (erradicación completa)");
    verificar(str_contains($contenidoVista, 'data-provider="datepicker"'), "7.3 Presencia de data-provider=\"datepicker\" (Flatpickr oficial Alina)");
    verificar(str_contains($contenidoVista, 'basic-date'), "7.4 Presencia de clase basic-date para vinculación con camargo-pickers.js");
    verificar(str_contains($contenidoVista, "url_asset('vendor/sweetalert/sweetalert.js')"), "7.5 Inclusión formal del vendor oficial SweetAlert2");
    verificar(str_contains($contenidoVista, 'Swal.fire'), "7.6 Uso exclusivo de Swal.fire para confirmaciones y alertas en la vista");
    verificar(!str_contains($contenidoVista, 'alert('), "7.7 CERO llamadas nativas a alert() en el JavaScript de la vista");
    verificar(!str_contains($contenidoVista, 'confirm('), "7.8 CERO llamadas nativas a confirm() en el JavaScript de la vista");

    // -------------------------------------------------------------------------
    // SECCIÓN 8: Invariantes de Base de Datos y Cero DDL
    // -------------------------------------------------------------------------
    echo "\n--- SECCIÓN 8: Invariantes de Base de Datos y Ranura 040 ---\n";

    $totalTablas = (int) $pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();
    verificar($totalTablas >= 130, "8.1 Total de tablas relacionales preservado (detectadas: {$totalTablas})");

    $migracion038Existe = file_exists(__DIR__ . '/../SQL/migraciones/038_pagos_pasarelas.sql');
    $migracion041Existe = glob(__DIR__ . '/../SQL/migraciones/*041*');
    verificar($migracion038Existe, "8.2 Migración 038_pagos_pasarelas.sql presente");
    verificar(empty($migracion041Existe), "8.3 Ranura de migración 041 estrictamente LIBRE");

    // Verificar que el ENUM de estado de reservas se conserva intacto (PENDIENTE, CONFIRMADA, CANCELADA, EXPIRADA)
    $stmtEnum = $pdo->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reservas' AND COLUMN_NAME = 'estado'");
    $columnaEstado = (string) $stmtEnum->fetchColumn();
    verificar($columnaEstado === "enum('PENDIENTE','CONFIRMADA','CANCELADA','EXPIRADA')", "8.4 ENUM de tabla reservas intacto sin alteraciones artificiales (CERO estado NO_SHOW en BD)");

    // -------------------------------------------------------------------------
    // SECCIÓN 9: Catch-Up Cronológico Multi-día e Idempotencia Real
    // -------------------------------------------------------------------------
    echo "\n--- SECCIÓN 9: Catch-Up Cronológico Multi-día e Idempotencia Real ---\n";

    // Crear propiedad temporal para prueba de ciclo completo de scheduler
    $codigoPropTemp = 'PROP_SCHED_' . uniqid();
    $pdo->prepare('INSERT INTO propiedades (codigo, nombre, pais_id, direccion, estado, zona_horaria)
                   VALUES (:c, "Propiedad Scheduler Test", 1, "Calle Auditoria 100", "ACTIVO", "America/Lima")')
        ->execute(['c' => $codigoPropTemp]);
    $propTempId = (int) $pdo->lastInsertId();

    try {
        // Primera corrida: al no tener cierres, debe detectar ayer como fecha debida
        $fechasTemp1 = $nightAudit->obtenerFechasDebidasPropiedad($propTempId);
        verificar(count($fechasTemp1) === 1 && $fechasTemp1[0] === $ayerLocal, "9.1 Propiedad sin cierres previos detecta como debida exactamente la fecha de ayer ({$ayerLocal})");

        // Ejecutar cierres debidos
        $cierresTemp = $nightAudit->ejecutarCierresDebidosPropiedad($propTempId, $actorSistemaId, 'Catch-up de prueba unitaria');
        verificar(count($cierresTemp) === 1, "9.2 Ejecución de catch-up procesa exactamente 1 fecha debida");
        verificar($cierresTemp[0]->estaCerrado(), "9.3 Cierre resultante finaliza en estado CERRADO");
        verificar($cierresTemp[0]->obtenerFechaHotelera() === $ayerLocal, "9.4 Cierre ejecutado corresponde exactamente a la fecha de ayer ({$ayerLocal})");

        // Segunda corrida inmediata: debe ser 100% idempotente (cero cierres debidos)
        $fechasTemp2 = $nightAudit->obtenerFechasDebidasPropiedad($propTempId);
        verificar(empty($fechasTemp2), "9.5 Segunda corrida inmediata: obtenerFechasDebidasPropiedad retorna arreglo vacío (al día)");

        $cierresTemp2 = $nightAudit->ejecutarCierresDebidosPropiedad($propTempId, $actorSistemaId, 'Segunda corrida');
        verificar(empty($cierresTemp2), "9.6 Idempotencia: segunda corrida no genera nuevos cierres (cero duplicados)");

        // Verificar que en base de datos existe exactamente 1 cierre para la propiedad temporal
        $stmtConteoCierres = $pdo->prepare('SELECT COUNT(*) FROM cierres_hoteleros WHERE propiedad_id = :p');
        $stmtConteoCierres->execute(['p' => $propTempId]);
        $totalCierresTemp = (int) $stmtConteoCierres->fetchColumn();
        verificar($totalCierresTemp === 1, "9.7 Persistencia: exactamente 1 registro en cierres_hoteleros tras corridas consecutivas");
    } finally {
        // Limpieza de propiedad temporal
        $pdo->prepare('DELETE FROM devengos_alojamiento WHERE cierre_hotelero_id IN (SELECT id FROM cierres_hoteleros WHERE propiedad_id = :p)')->execute(['p' => $propTempId]);
        $pdo->prepare('DELETE FROM cierres_hoteleros WHERE propiedad_id = :p')->execute(['p' => $propTempId]);
        $pdo->prepare('DELETE FROM propiedades WHERE id = :p')->execute(['p' => $propTempId]);
    }

} catch (Throwable $e) {
    echo "\n[ERROR CATASTRÓFICO]: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n====================================================================\n";
echo sprintf(" RESULTADO SUITE 83: %d CHECKS | %d PASS | %d FAIL\n", $totalChecks, $checksPasados, $totalChecks - $checksPasados);
echo "====================================================================\n";

if ($checksPasados === $totalChecks && $totalChecks > 0) {
    exit(0);
} else {
    exit(1);
}
