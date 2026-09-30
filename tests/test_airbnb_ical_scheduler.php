<?php

declare(strict_types=1);

/**
 * Camargo PMS — Suite de Pruebas Automatizadas para AIRBNB-ICAL-1D1.
 *
 * Cobertura de Contrato de Exclusión Soberana, Concurrencia UI ↔ CLI,
 * Detección/Saneamiento de Stale Runs, Selección de Conexiones Debidas,
 * Aislamiento de Fallos y Códigos de Salida del Scheduler CLI.
 */

define('RUTA_RAIZ', dirname(__DIR__));
define('RUTA_APP', RUTA_RAIZ . DIRECTORY_SEPARATOR . 'app');

require_once RUTA_RAIZ . '/vendor/autoload.php';
\CamargoPMS\Nucleo\Configuracion::cargar(RUTA_RAIZ);

use CamargoPMS\Controladores\CanalIcalControlador;
use CamargoPMS\Excepciones\ConexionIcalEnSincronizacionExcepcion;
use CamargoPMS\Modelos\CanalDistribucion;
use CamargoPMS\Modelos\ConexionIcal;
use CamargoPMS\Modelos\EventoIcalExterno;
use CamargoPMS\Modelos\SincronizacionIcalLog;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Repositorios\CanalDistribucionRepositorio;
use CamargoPMS\Repositorios\ConexionIcalRepositorio;
use CamargoPMS\Repositorios\EventoIcalExternoRepositorio;
use CamargoPMS\Repositorios\SincronizacionIcalLogRepositorio;
use CamargoPMS\Servicios\IcalCriptografiaServicio;
use CamargoPMS\Servicios\SincronizacionIcalServicio;

$totalChecks = 0;
$checksPassed = 0;

function assertCheck(bool $condition, string $description): void
{
    global $totalChecks, $checksPassed;
    $totalChecks++;
    if ($condition) {
        $checksPassed++;
        echo "  [PASS] $description\n";
    } else {
        echo "  [FAIL] $description\n";
        debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
    }
}

echo "====================================================================\n";
echo " CAMARGO PMS — PRUEBAS AIRBNB-ICAL-1D1: SCHEDULER & CONCURRENCIA\n";
echo "====================================================================\n\n";

$pdo = BaseDatos::conexion();
$canalRepo = new CanalDistribucionRepositorio($pdo);
$conexionRepo = new ConexionIcalRepositorio($pdo);
$eventoRepo = new EventoIcalExternoRepositorio($pdo);
$logRepo = new SincronizacionIcalLogRepositorio($pdo);
$cripto = new IcalCriptografiaServicio();
$syncServicio = new SincronizacionIcalServicio(
    pdo: $pdo,
    conexionRepo: $conexionRepo,
    eventoRepo: $eventoRepo,
    logRepo: $logRepo,
    criptoServicio: $cripto
);

// Obtenemos canal y unidad base
$canalAirbnb = $canalRepo->buscarPorCodigo('AIRBNB');
assertCheck($canalAirbnb !== null, "Canal AIRBNB existe en la base de datos");

$unidadId = (int) $pdo->query("SELECT id FROM unidades WHERE estado = 'ACTIVO' ORDER BY id ASC LIMIT 1")->fetchColumn();
assertCheck($unidadId > 0, "Unidad de prueba disponible obtenida (#$unidadId)");

// Creamos 2 conexiones dedicadas para pruebas de concurrencia e independencia
$tokenRawA = bin2hex(random_bytes(32));
$urlCifradaA = $cripto->cifrar("https://airbnb.com/calendar/ical/test_scheduler_a.ics");
$conexionA = new ConexionIcal(
    id: 0,
    unidadId: $unidadId,
    canalId: $canalAirbnb->obtenerId(),
    nombre: "Test Scheduler Conexión A",
    urlImportacionCifrada: $urlCifradaA,
    tokenExportacionHash: hash('sha256', $tokenRawA),
    tokenExportacionCifrado: $cripto->cifrar($tokenRawA),
    tokenPrefijo: substr($tokenRawA, 0, 8),
    importacionHabilitada: true,
    exportacionHabilitada: true,
    frecuenciaMinutos: 60,
    estado: ConexionIcal::ESTADO_ACTIVO
);
$idA = $conexionRepo->crear($conexionA);
assertCheck($idA > 0, "Conexión A creada (#$idA)");

$tokenRawB = bin2hex(random_bytes(32));
$urlCifradaB = $cripto->cifrar("https://airbnb.com/calendar/ical/test_scheduler_b.ics");
$conexionB = new ConexionIcal(
    id: 0,
    unidadId: $unidadId,
    canalId: $canalAirbnb->obtenerId(),
    nombre: "Test Scheduler Conexión B",
    urlImportacionCifrada: $urlCifradaB,
    tokenExportacionHash: hash('sha256', $tokenRawB),
    tokenExportacionCifrado: $cripto->cifrar($tokenRawB),
    tokenPrefijo: substr($tokenRawB, 0, 8),
    importacionHabilitada: true,
    exportacionHabilitada: true,
    frecuenciaMinutos: 30,
    estado: ConexionIcal::ESTADO_ACTIVO
);
$idB = $conexionRepo->crear($conexionB);
assertCheck($idB > 0, "Conexión B creada (#$idB)");

// Fechas hoteleras futuras (2028) para garantizar ventana libre de colisiones preexistentes
$fechaAInicio = '2028-08-01';
$fechaAFin = '2028-08-05';
$fechaBInicio = '2028-08-10';
$fechaBFin = '2028-08-15';

// Limpieza preventiva de inventario para las fechas de prueba
$pdo->prepare("DELETE FROM inventario_diario_unidades WHERE unidad_id = :uid AND fecha >= '2028-08-01' AND fecha <= '2028-08-20'")->execute(['uid' => $unidadId]);

$icsDummyA = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Test//ES\r\nBEGIN:VEVENT\r\nUID:event-sched-a-1\r\nDTSTART;VALUE=DATE:20280801\r\nDTEND;VALUE=DATE:20280805\r\nSUMMARY:Bloqueo A\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
$icsDummyB = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Test//ES\r\nBEGIN:VEVENT\r\nUID:event-sched-b-1\r\nDTSTART;VALUE=DATE:20280810\r\nDTEND;VALUE=DATE:20280815\r\nSUMMARY:Bloqueo B\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";

// =========================================================================
// 1. CONTRATO DE EXCLUSIÓN SOBERANA (GET_LOCK EN SERVICIO)
// =========================================================================
echo "\n--- 1. Contrato de Exclusión Soberana (GET_LOCK en Servicio) ---\n";

// Sincronización normal exitosa adquiere y libera el lock
$resA = $syncServicio->sincronizarConexion(
    conexionId: $idA,
    payloadIcsOpcional: $icsDummyA,
    origenEjecucion: SincronizacionIcalLog::ORIGEN_CLI
);
assertCheck($resA['resultado'] === 'EXITO', "Llamada directa al servicio sincroniza con EXITO");
assertCheck($resA['eventos_creados'] === 1, "Evento A-1 creado");

// Verificamos que el lock quedó liberado tras finalizar
$stmtLockCheck = $pdo->prepare("SELECT IS_FREE_LOCK(:lock_name)");
$stmtLockCheck->execute(['lock_name' => "camargo_ical_sync_{$idA}"]);
assertCheck((int) $stmtLockCheck->fetchColumn() === 1, "Lock MySQL fue liberado incondicionalmente al terminar sincronizarConexion()");

// =========================================================================
// 2. BLOQUEO CONCURRENTE ANTE SESIÓN ACTIVA (LOCK EN PROCESO EXTERNO)
// =========================================================================
echo "\n--- 2. Bloqueo Concurrente ante Sesión Externa ---\n";

// Abrimos una segunda conexión PDO separada para simular otro proceso concurrente
$pdo2 = new PDO(
    "mysql:host=" . \CamargoPMS\Nucleo\Configuracion::obtener('DB_HOST') .
    ";port=" . \CamargoPMS\Nucleo\Configuracion::obtener('DB_PORT') .
    ";dbname=" . \CamargoPMS\Nucleo\Configuracion::obtener('DB_DATABASE') .
    ";charset=utf8mb4",
    \CamargoPMS\Nucleo\Configuracion::obtener('DB_USERNAME'),
    \CamargoPMS\Nucleo\Configuracion::obtener('DB_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Proceso 2 adquiere el lock de la conexión A
$lockNameA = "camargo_ical_sync_{$idA}";
$stmtExtLock = $pdo2->prepare("SELECT GET_LOCK(:lock_name, 0)");
$stmtExtLock->execute(['lock_name' => $lockNameA]);
assertCheck((int) $stmtExtLock->fetchColumn() === 1, "Proceso 2 adquirió exitosamente GET_LOCK para Conexión A");

// Ahora el servicio principal intenta sincronizar Conexión A mientras Proceso 2 la retiene
$excepcionCapturada = false;
$codigoErrorCapturado = '';
try {
    $syncServicio->sincronizarConexion(
        conexionId: $idA,
        payloadIcsOpcional: $icsDummyA,
        origenEjecucion: SincronizacionIcalLog::ORIGEN_CRON
    );
} catch (ConexionIcalEnSincronizacionExcepcion $e) {
    $excepcionCapturada = true;
    $codigoErrorCapturado = $e->obtenerCodigoError();
}
assertCheck($excepcionCapturada, "Servicio lanza ConexionIcalEnSincronizacionExcepcion ante bloqueo MySQL activo");
assertCheck($codigoErrorCapturado === 'CONEXION_BLOQUEADA', "Código de error es CONEXION_BLOQUEADA");

// =========================================================================
// 3. INDEPENDENCIA ENTRE CONEXIONES DISTINTAS
// =========================================================================
echo "\n--- 3. Independencia entre Conexiones Distintas ---\n";

// Conexión A sigue bloqueada por Proceso 2. Sincronizamos Conexión B
$resB = $syncServicio->sincronizarConexion(
    conexionId: $idB,
    payloadIcsOpcional: $icsDummyB,
    origenEjecucion: SincronizacionIcalLog::ORIGEN_CRON
);
assertCheck($resB['resultado'] === 'EXITO', "Conexión B sincroniza con EXITO a pesar de que Conexión A está retenida");
assertCheck($resB['eventos_creados'] === 1, "Evento B-1 creado independientemente");

// =========================================================================
// 4. RECUPERACIÓN TRAS CRASH / CAÍDA ABRUPTA DE PROCESO
// =========================================================================
echo "\n--- 4. Recuperación tras Crash / Caída Abrupta de Proceso ---\n";

// Simulamos que el Proceso 2 muere súbitamente destruyendo sus statements y conexión PDO
$stmtExtLock = null;
$pdo2 = null; // Cierra socket MySQL

// Verificamos que MySQL liberó el lock de inmediato
$stmtLockPostCrash = $pdo->prepare("SELECT IS_FREE_LOCK(:lock_name)");
$stmtLockPostCrash->execute(['lock_name' => $lockNameA]);
assertCheck((int) $stmtLockPostCrash->fetchColumn() === 1, "MySQL liberó automáticamente GET_LOCK al morir el proceso cliente");

// Ahora la Conexión A puede sincronizarse nuevamente sin error
$resA2 = $syncServicio->sincronizarConexion(
    conexionId: $idA,
    payloadIcsOpcional: $icsDummyA,
    origenEjecucion: SincronizacionIcalLog::ORIGEN_CRON
);
assertCheck($resA2['resultado'] === 'EXITO', "Conexión A vuelve a sincronizar exitosamente tras liberación del lock");

// =========================================================================
// 5. DETECCIÓN Y SANEAMIENTO DE LOGS HUÉRFANOS (STALE RUNS)
// =========================================================================
echo "\n--- 5. Detección y Saneamiento de Logs Huérfanos (Stale Runs) ---\n";

// Insertamos un log artificial huérfano con finalizado_en NULL y antigüedad de 150 segundos
$stmtStale = $pdo->prepare("
    INSERT INTO sincronizaciones_ical_log (
        conexion_ical_id, tipo_operacion, origen_ejecucion, iniciado_en, resultado
    ) VALUES (
        :cid, 'IMPORTACION', 'CRON', NOW() - INTERVAL 150 SECOND, 'EXITO'
    )
");
$stmtStale->execute(['cid' => $idA]);
$staleLogId = (int) $pdo->lastInsertId();
assertCheck($staleLogId > 0, "Log huérfano artificial insertado (#$staleLogId, iniciado hace 150s, finalizado_en NULL)");

// Comprobamos que haySincronizacionEnCurso con umbral de 120s lo ignora defensivamente
assertCheck(!$logRepo->haySincronizacionEnCurso($idA, 120), "haySincronizacionEnCurso ignora el log huérfano de > 120 segundos (no hay bloqueo infinito)");

// Ejecutamos limpieza de logs huérfanos
$limpiados = $logRepo->limpiarLogsHuerfanos($idA, 120);
assertCheck($limpiados >= 1, "limpiarLogsHuerfanos normalizó al menos 1 registro");

// Verificamos el estado del log huérfano normalizado
$logNormalizado = $logRepo->buscarPorId($staleLogId);
assertCheck($logNormalizado !== null, "Log huérfano consultado");
assertCheck($logNormalizado->obtenerFinalizadoEn() !== null, "finalizado_en fue poblado con timestamp");
assertCheck($logNormalizado->obtenerResultado() === SincronizacionIcalLog::RESULTADO_ERROR, "resultado fue establecido en ERROR");
assertCheck(str_contains((string) $logNormalizado->obtenerMensajeResultado(), 'timeout/crash previo'), "mensaje_resultado documenta la interrupción abrupta");

// =========================================================================
// 6. INTEGRACIÓN Y TRADUCCIÓN EN CONTROLADOR HTTP (UI ALINA)
// =========================================================================
echo "\n--- 6. Integración y Traducción en Controlador HTTP (UI Alina) ---\n";

$csrfServicio = new \CamargoPMS\Servicios\CsrfServicio();
$autorizacionServicio = new \CamargoPMS\Servicios\AutorizacionServicio($pdo);
$stmtAdmin = $pdo->query("SELECT u.id, u.persona_id, u.nombre_usuario FROM usuarios u JOIN usuarios_roles ur ON ur.usuario_id = u.id JOIN roles r ON r.id = ur.rol_id WHERE r.codigo = 'SUPERADMINISTRADOR' AND u.estado = 'ACTIVO' LIMIT 1");
$adminRow = $stmtAdmin->fetch(PDO::FETCH_ASSOC);
$usuarioAdmin = new \CamargoPMS\Modelos\Usuario((int) $adminRow['id'], (int) $adminRow['persona_id'], (string) $adminRow['nombre_usuario'], 'hash');
$mockSesionAdmin = new class($usuarioAdmin) extends \CamargoPMS\Servicios\SesionServicio {
    public function __construct(private \CamargoPMS\Modelos\Usuario $u) {}
    public function validarSesionActual(): ?\CamargoPMS\Modelos\Usuario { return $this->u; }
};

$controlador = new CanalIcalControlador(
    pdo: $pdo,
    sesionServicio: $mockSesionAdmin,
    autorizacionServicio: $autorizacionServicio,
    csrfServicio: $csrfServicio,
    syncServicio: $syncServicio
);

// Simulamos lock retenido en BD para verificar que el controlador traduce la excepción a HTTP 409
$pdo3 = new PDO(
    "mysql:host=" . \CamargoPMS\Nucleo\Configuracion::obtener('DB_HOST') .
    ";port=" . \CamargoPMS\Nucleo\Configuracion::obtener('DB_PORT') .
    ";dbname=" . \CamargoPMS\Nucleo\Configuracion::obtener('DB_DATABASE') .
    ";charset=utf8mb4",
    \CamargoPMS\Nucleo\Configuracion::obtener('DB_USERNAME'),
    \CamargoPMS\Nucleo\Configuracion::obtener('DB_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$stmtLockHold = $pdo3->prepare("SELECT GET_LOCK(:lock_name, 0)");
$stmtLockHold->execute(['lock_name' => "camargo_ical_sync_{$idA}"]);

// Preparamos payload HTTP válido para el controlador
$tokenValidoCsrf = $csrfServicio->generarToken('canales_ical');
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['_csrf' => $tokenValidoCsrf];

$resHttp = $controlador->sincronizar($idA);
assertCheck($resHttp->obtenerCodigoEstado() === 409, "Controlador UI captura excepcion de lock y responde HTTP 409 Conflict");
$cuerpoHttp = json_decode($resHttp->obtenerContenido(), true);
assertCheck($cuerpoHttp['ok'] === false, "ok es false");
assertCheck($cuerpoHttp['codigo'] === 'CONEXION_BLOQUEADA', "codigo retornado es CONEXION_BLOQUEADA");

// Liberamos el lock de pdo3 y cerramos conexión
$stmtLockHold = null;
$pdo3 = null;

// =========================================================================
// 7. SELECCIÓN DE CONEXIONES DEBIDAS (listarDebidasParaSondeo)
// =========================================================================
echo "\n--- 7. Selección de Conexiones Debidas (listarDebidasParaSondeo) ---\n";

// Conexión A: fue sincronizada hace instantes (ultima_sincronizacion_en = NOW()), frecuencia = 60 min
// Conexión B: fue sincronizada hace instantes (ultima_sincronizacion_en = NOW()), frecuencia = 30 min
$debidasInmediatas = $conexionRepo->listarDebidasParaSondeo();
$idsDebidas = array_map(fn($c) => $c->obtenerId(), $debidasInmediatas);
assertCheck(!in_array($idA, $idsDebidas, true), "Conexión A (recién sincronizada) NO aparece en listarDebidasParaSondeo");
assertCheck(!in_array($idB, $idsDebidas, true), "Conexión B (recién sincronizada) NO aparece en listarDebidasParaSondeo");

// Simulamos que la Conexión B tiene última sincronización de hace 35 minutos (frecuencia = 30 min -> VENCIDA)
$pdo->prepare("UPDATE conexiones_ical SET ultima_sincronizacion_en = NOW() - INTERVAL 35 MINUTE WHERE id = :id")->execute(['id' => $idB]);

$debidasPostUpdate = $conexionRepo->listarDebidasParaSondeo();
$idsDebidas2 = array_map(fn($c) => $c->obtenerId(), $debidasPostUpdate);
assertCheck(in_array($idB, $idsDebidas2, true), "Conexión B (hace 35m con frecuencia 30m) SÍ aparece en listarDebidasParaSondeo");
assertCheck(!in_array($idA, $idsDebidas2, true), "Conexión A (recién sincronizada con frecuencia 60m) sigue fuera de debidas");

// Simulamos que la Conexión A nunca se ha sincronizado (ultima_sincronizacion_en = NULL)
$pdo->prepare("UPDATE conexiones_ical SET ultima_sincronizacion_en = NULL WHERE id = :id")->execute(['id' => $idA]);
$debidasPostNull = $conexionRepo->listarDebidasParaSondeo();
$idsDebidas3 = array_map(fn($c) => $c->obtenerId(), $debidasPostNull);
assertCheck(in_array($idA, $idsDebidas3, true), "Conexión A con ultima_sincronizacion_en = NULL SÍ aparece en debidas");

// Simulamos conexión pausada: debe quedar excluida aunque esté vencida
$pdo->prepare("UPDATE conexiones_ical SET estado = 'PAUSADO' WHERE id = :id")->execute(['id' => $idA]);
$debidasPostPausa = $conexionRepo->listarDebidasParaSondeo();
$idsDebidas4 = array_map(fn($c) => $c->obtenerId(), $debidasPostPausa);
assertCheck(!in_array($idA, $idsDebidas4, true), "Conexión PAUSADA queda excluida de listarDebidasParaSondeo");
$pdo->prepare("UPDATE conexiones_ical SET estado = 'ACTIVO' WHERE id = :id")->execute(['id' => $idA]);

// =========================================================================
// 8. CONTRATO DE EJECUCIÓN CLI (bin/sincronizar-ical.php)
// =========================================================================
echo "\n--- 8. Contrato de Ejecución CLI (bin/sincronizar-ical.php) ---\n";

// Ejecución con --ayuda (Exit code 0)
$outAyuda = [];
$codeAyuda = 0;
exec('php ' . escapeshellarg(RUTA_RAIZ . '/bin/sincronizar-ical.php') . ' --ayuda', $outAyuda, $codeAyuda);
assertCheck($codeAyuda === 0, "CLI --ayuda retorna exit code 0");
assertCheck(str_contains(implode("\n", $outAyuda), '--solo-debidas'), "CLI --ayuda incluye opción --solo-debidas");
assertCheck(str_contains(implode("\n", $outAyuda), 'Códigos de salida'), "CLI --ayuda describe los códigos de salida");

// Ejecución sin argumentos (Exit code 1)
$outSinArgs = [];
$codeSinArgs = 0;
exec('php ' . escapeshellarg(RUTA_RAIZ . '/bin/sincronizar-ical.php'), $outSinArgs, $codeSinArgs);
assertCheck($codeSinArgs === 1, "CLI sin argumentos retorna exit code 1 (error de uso)");

// Conexión puntual inexistente (Exit code 1)
$outInexistente = [];
$codeInexistente = 0;
exec('php ' . escapeshellarg(RUTA_RAIZ . '/bin/sincronizar-ical.php') . ' --conexion=99999999', $outInexistente, $codeInexistente);
assertCheck($codeInexistente === 1, "CLI sobre conexión inexistente retorna exit code 1");

// Test negativo de secretos en la salida CLI
$salidaCompleta = implode("\n", array_merge($outAyuda, $outSinArgs, $outInexistente));
assertCheck(!str_contains($salidaCompleta, 'ICAL_ENCRYPTION_KEY'), "Test Negativo: CLI NO filtra ICAL_ENCRYPTION_KEY");
assertCheck(!str_contains($salidaCompleta, 'token_exportacion_hash'), "Test Negativo: CLI NO filtra hash de tokens");
assertCheck(!str_contains($salidaCompleta, 'url_importacion_cifrada'), "Test Negativo: CLI NO filtra URLs cifradas");

// =========================================================================
// 9. AISLAMIENTO DE FALLOS EN LOTE (EXIT CODE 2 ANTE FALLOS PARCIALES)
// =========================================================================
echo "\n--- 9. Aislamiento de Fallos en Lote ---\n";

// Creamos una conexión temporal C con URL rota para forzar error en lote
$tokenRawC = bin2hex(random_bytes(32));
$urlCifradaC = $cripto->cifrar("https://invalid-host-for-failure-test-404.local/cal.ics");
$conexionC = new ConexionIcal(
    id: 0,
    unidadId: $unidadId,
    canalId: $canalAirbnb->obtenerId(),
    nombre: "Test Scheduler Conexión C (Fallo)",
    urlImportacionCifrada: $urlCifradaC,
    tokenExportacionHash: hash('sha256', $tokenRawC),
    tokenExportacionCifrado: $cripto->cifrar($tokenRawC),
    tokenPrefijo: substr($tokenRawC, 0, 8),
    importacionHabilitada: true,
    exportacionHabilitada: true,
    frecuenciaMinutos: 1,
    estado: ConexionIcal::ESTADO_ACTIVO
);
$idC = $conexionRepo->crear($conexionC);

// Ejecutamos sincronización puntual de C para verificar que maneja el error
$outC = [];
$codeC = 0;
exec('php ' . escapeshellarg(RUTA_RAIZ . '/bin/sincronizar-ical.php') . " --conexion=$idC", $outC, $codeC);
assertCheck($codeC === 1, "Conexión C con fallo técnico puntual retorna exit code 1");

// Ejecución por lote con fallo parcial (Conexión C con URL rota debe generar exit code 2)
$outLote = [];
$codeLote = 0;
exec('php ' . escapeshellarg(RUTA_RAIZ . '/bin/sincronizar-ical.php') . ' --solo-debidas --quiet', $outLote, $codeLote);
assertCheck($codeLote === 2, "Ejecución por lote (--solo-debidas) con fallo parcial retorna exit code 2");
assertCheck(!str_contains(implode("\n", $outLote), "====="), "Modo --quiet no emite cabeceras decorativas ni banners");

// Pausamos Conexión C y verificamos que el lote sin conexiones debidas retorna exit code 0
$pdo->prepare("UPDATE conexiones_ical SET estado = 'PAUSADO' WHERE id = :id")->execute(['id' => $idC]);
$outVacio = [];
$codeVacio = 0;
exec('php ' . escapeshellarg(RUTA_RAIZ . '/bin/sincronizar-ical.php') . ' --solo-debidas --quiet', $outVacio, $codeVacio);
assertCheck($codeVacio === 0, "Ejecución por lote sin conexiones debidas retorna exit code 0");

// =========================================================================
// 10. GOBERNANZA, DDL Y RECURSOS
// =========================================================================
echo "\n--- 10. Gobernanza y Esquema ---\n";

$totalTablas = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();
assertCheck($totalTablas >= 122, "Base de datos contiene al menos 122 tablas relacionales (actual: $totalTablas)");

$mig035Presente = (bool) $pdo->query("SELECT 1 FROM migraciones WHERE migracion = '035_canales_ical.sql'")->fetchColumn();
assertCheck($mig035Presente, "Migración 035_canales_ical.sql registrada en BD");

$migracion038Existe = file_exists(RUTA_RAIZ . '/SQL/migraciones/038_*.sql') || glob(RUTA_RAIZ . '/SQL/migraciones/038*.sql');
assertCheck(!$migracion038Existe, "Ranura 038 permanece estrictamente LIBRE (Cero DDL no autorizado)");

$adminDashboardModificado = false;
$outputGit = [];
exec('git status --porcelain admin-dashboard/', $outputGit);
assertCheck(empty($outputGit), "admin-dashboard/ permanece 100% inmutable y limpio");

// =========================================================================
// 11. CONTRATO OPERATIVO LINUX (bin/cron-ical.sh y bin/crontab-ical.template)
// =========================================================================
echo "\n--- 11. Contrato Operativo Linux (AIRBNB-ICAL-1D2) ---\n";

$cronScript = RUTA_RAIZ . '/bin/cron-ical.sh';
assertCheck(file_exists($cronScript), "Script bin/cron-ical.sh existe en el repositorio");

$cronContent = (string) file_get_contents($cronScript);
assertCheck(str_contains($cronContent, 'flock -n 200'), "bin/cron-ical.sh implementa prevención de solapamiento con flock -n");
assertCheck(str_contains($cronContent, '--solo-debidas'), "bin/cron-ical.sh invoca la opción --solo-debidas");
assertCheck(str_contains($cronContent, '--quiet'), "bin/cron-ical.sh invoca la opción --quiet");
assertCheck(str_contains($cronContent, '"${PHP_BIN}"') && str_contains($cronContent, '"${RUNNER_SCRIPT}"'), "bin/cron-ical.sh maneja rutas entrecomilladas tolerantes a espacios");

$crontabTemplate = RUTA_RAIZ . '/bin/crontab-ical.template';
assertCheck(file_exists($crontabTemplate), "Plantilla bin/crontab-ical.template existe");
$templateContent = (string) file_get_contents($crontabTemplate);
assertCheck(str_contains($templateContent, '*/5 * * * *'), "bin/crontab-ical.template documenta intervalo de 5 minutos");

// =========================================================================
// 12. CONTRATO OPERATIVO WINDOWS (bin/task-scheduler-ical.ps1 y .xml)
// =========================================================================
echo "\n--- 12. Contrato Operativo Windows (AIRBNB-ICAL-1D2) ---\n";

$psScript = RUTA_RAIZ . '/bin/task-scheduler-ical.ps1';
assertCheck(file_exists($psScript), "Script bin/task-scheduler-ical.ps1 existe en el repositorio");

$psContent = (string) file_get_contents($psScript);
assertCheck(str_contains($psContent, 'System.Threading.Mutex'), "bin/task-scheduler-ical.ps1 implementa prevención de solapamiento con Mutex");
assertCheck(str_contains($psContent, '--solo-debidas'), "bin/task-scheduler-ical.ps1 invoca la opción --solo-debidas");
assertCheck(str_contains($psContent, '--quiet'), "bin/task-scheduler-ical.ps1 invoca la opción --quiet");

$xmlTemplate = RUTA_RAIZ . '/bin/task-scheduler-ical.xml';
assertCheck(file_exists($xmlTemplate), "Plantilla bin/task-scheduler-ical.xml existe");
$xml = simplexml_load_file($xmlTemplate);
assertCheck($xml !== false, "bin/task-scheduler-ical.xml es un XML válido y bien formado");
assertCheck((string) $xml->Settings->MultipleInstancesPolicy === 'IgnoreNew', "Plantilla XML aplica política nativa MultipleInstancesPolicy = IgnoreNew");
assertCheck((string) $xml->Triggers->TimeTrigger->Repetition->Interval === 'PT5M', "Plantilla XML programa repetición cada 5 minutos (PT5M)");

// =========================================================================
// 13. EJECUCIÓN E2E SIN SESIÓN HTTP NI CSRF
// =========================================================================
echo "\n--- 13. Ejecución E2E sin Sesión HTTP ni CSRF ---\n";

$outCliE2E = [];
$codeCliE2E = 0;
exec('php ' . escapeshellarg(RUTA_RAIZ . '/bin/sincronizar-ical.php') . ' --solo-debidas --quiet', $outCliE2E, $codeCliE2E);
assertCheck($codeCliE2E === 0, "Ejecución CLI programada retorna exit code 0 sin requerir sesión");
assertCheck(empty($outCliE2E), "Ejecución CLI en modo --quiet no emite salida por stdout");
$cliRawOutput = implode("\n", $outCliE2E);
assertCheck(!str_contains(strtolower($cliRawOutput), 'set-cookie') && !str_contains(strtolower($cliRawOutput), 'phpsessid'), "Ejecución no genera ni depende de cookies de sesión");

// =========================================================================
// 14. TEST NEGATIVO DE SECRETOS EN ARTEFACTOS Y GUÍAS DE OPERACIÓN
// =========================================================================
echo "\n--- 14. Test Negativo de Secretos en Artefactos Operativos ---\n";

$archivosOperativos = [$cronScript, $crontabTemplate, $psScript, $xmlTemplate];
$fugasDetectadas = 0;
foreach ($archivosOperativos as $arch) {
    $contenido = (string) file_get_contents($arch);
    if (preg_match('/(ICAL_ENCRYPTION_KEY|password|token_exportacion)\s*[:=]\s*[\'"][^\'"]{10,}/i', $contenido)) {
        $fugasDetectadas++;
    }
}
assertCheck($fugasDetectadas === 0, "Test Negativo: Cero secretos o claves reales en scripts operativos de scheduler");

// =========================================================================
// 15. MANUAL OPERATIVO Y AUSENCIA DE DAEMONS
// =========================================================================
echo "\n--- 15. Manual Operativo y Arquitectura sin Daemons ---\n";

$manualDoc = RUTA_RAIZ . '/docs/gobernanza/SCHEDULER_ICAL.md';
assertCheck(file_exists($manualDoc), "Manual operativo docs/gobernanza/SCHEDULER_ICAL.md existe");

$manualContent = (string) file_get_contents($manualDoc);
assertCheck(str_contains($manualContent, 'flock') && str_contains($manualContent, 'IgnoreNew'), "Manual documenta flock en Linux e IgnoreNew en Windows");
assertCheck(str_contains($manualContent, 'NO VERIFICADO'), "Manual declara formalmente producción como NO VERIFICADO");
assertCheck(str_contains($manualContent, 'Disparador') && str_contains($manualContent, 'Autoridad Temporal'), "Manual documenta jerarquía vinculante de autoridades");

// Comprobamos que el script runner no contiene bucles infinitos (while (true) o similar para daemons)
$runnerContent = (string) file_get_contents(RUTA_RAIZ . '/bin/sincronizar-ical.php');
assertCheck(!str_contains($runnerContent, 'while (true)') && !str_contains($runnerContent, 'while(true)'), "bin/sincronizar-ical.php es episódico (cero bucles infinitos/daemons)");

// Comprobamos que index.php o controladores no tienen endpoints HTTP utilizados como cron
$enrutadorContent = (string) file_get_contents(RUTA_RAIZ . '/public/index.php');
assertCheck(!str_contains($enrutadorContent, '/cron') && !str_contains($enrutadorContent, '/scheduler'), "Enrutador public/index.php no expone falsos endpoints HTTP para cron");

// Cleanup defensivo de conexiones de prueba e inventario asociado
$pdo->prepare("DELETE FROM inventario_diario_unidades WHERE origen_tipo = 'EVENTO_ICAL_EXTERNO' AND origen_id IN (SELECT id FROM eventos_ical_externos WHERE conexion_ical_id IN (:a, :b, :c))")->execute(['a' => $idA, 'b' => $idB, 'c' => $idC]);
$pdo->prepare("DELETE FROM eventos_ical_externos WHERE conexion_ical_id IN (:a, :b, :c)")->execute(['a' => $idA, 'b' => $idB, 'c' => $idC]);
$pdo->prepare("DELETE FROM sincronizaciones_ical_log WHERE conexion_ical_id IN (:a, :b, :c)")->execute(['a' => $idA, 'b' => $idB, 'c' => $idC]);
$pdo->prepare("DELETE FROM conexiones_ical WHERE id IN (:a, :b, :c)")->execute(['a' => $idA, 'b' => $idB, 'c' => $idC]);

echo "\n====================================================================\n";
echo " RESUMEN AIRBNB-ICAL-1D: $checksPassed / $totalChecks pruebas superadas\n";
echo "====================================================================\n";

if ($checksPassed === $totalChecks) {
    echo "\n>>> AIRBNB-ICAL-1D (1D1 + 1D2): SCHEDULER, OPERACION Y CONCURRENCIA VALIDADO AL 100% (TODO PASS) <<<\n";
    exit(0);
} else {
    echo "\n>>> FALLOS DETECTADOS EN AIRBNB-ICAL-1D <<<\n";
    exit(1);
}
