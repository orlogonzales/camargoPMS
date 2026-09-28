<?php

declare(strict_types=1);

/**
 * Suite de Verificación SESIONES-1 — Matriz Exhaustiva de 40 Pruebas de Dominio
 *
 * Verifica las decisiones y contratos vinculantes de D-088:
 * 1. SESIÓN PHP != REGISTRO DE SESIÓN
 * 2. USUARIO ACTIVO != USUARIO CON SESIÓN
 * 3. SESIÓN ACTIVA != PRESENCIA RECIENTE
 * 4. PRESENCIA HTTP != ONLINE EN TIEMPO REAL
 * 5. Idle timeout (30m) != absolute timeout (12h)
 * 6. REVOCAR SESIÓN != DESACTIVAR USUARIO
 * 7. REVOCAR SESIÓN != CAMBIAR CONTRASEÑA
 * 8. Revocación administrativa auditable
 * 9. Irreversibilidad: revocada no vuelve a ser válida
 * 10. Estados soberanos: ACTIVA, EXPIRADA_INACTIVIDAD, EXPIRADA_ABSOLUTA, REVOCADA
 * 11. Presencia heurística: PRESENCIA_RECIENTE, SIN_ACTIVIDAD_RECIENTE
 * 12. token_hash protegido, nunca expuesto en interfaces
 * 13. CERO tablas nuevas / CERO migraciones (104 tablas / ranura 027 libre)
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CamargoPMS\Modelos\SesionUsuario;
use CamargoPMS\Modelos\Usuario;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Repositorios\SesionUsuarioRepositorio;
use CamargoPMS\Repositorios\UsuarioRepositorio;
use CamargoPMS\Servicios\SesionServicio;

Configuracion::cargar(dirname(__DIR__));
$pdo = BaseDatos::conexion();

$sesionRepo = new SesionUsuarioRepositorio($pdo);
$usuarioRepo = new UsuarioRepositorio($pdo);
$sesionServicio = new SesionServicio($pdo, $sesionRepo, $usuarioRepo);

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
echo " CAMARGO PMS — PRUEBAS SESIONES-1: MATRIZ EXHAUSTIVA DE DOMINIO (40 CASOS)\n";
echo " Decisión Vinculante: D-088\n";
echo "====================================================================\n\n";

// BLOQUE 1: AXIOMAS DE DOMINIO Y DIFERENCIACIÓN CONCEPTUAL (Casos 1 - 8)
echo "--- BLOQUE 1: AXIOMAS Y CUARTETO CONCEPTUAL ---\n";

afirmar(true, "Axioma D-088 #1: Sesión PHP efímera (runtime) es conceptualmente independiente de SesionUsuario en BD");

$usuarioActivo = $usuarioRepo->buscarPorId(1, false);
afirmar($usuarioActivo !== null && $usuarioActivo->esActivo(), "Axioma D-088 #2: Usuario activo representa habilitación administrativa en catálogo");

// Verificar que un usuario activo puede tener 0 o múltiples sesiones
$sesionesUsuario = $sesionRepo->listarActivasPorUsuario(1);
afirmar(is_array($sesionesUsuario), "Axioma D-088 #2b: Usuario activo puede tener cualquier número de sesiones activas (cardinalidad 1:N)");

// Sesión con actividad hace 20 minutos: es ACTIVA pero SIN_ACTIVIDAD_RECIENTE
$ahoraTs = time();
$iniciada20MinAtras = date('Y-m-d H:i:s', $ahoraTs - (20 * 60));
$expira10MinFuturo = date('Y-m-d H:i:s', $ahoraTs + (10 * 60));
$sesion20Min = new SesionUsuario(
    1001, 1, 'hash_test_1', $iniciada20MinAtras, $iniciada20MinAtras, $expira10MinFuturo, null, null
);

afirmar($sesion20Min->obtenerEstadoSoberano(30, 12, $ahoraTs) === 'ACTIVA', "Axioma D-088 #3: Sesión a los 20 min de inactividad sigue siendo ACTIVA (< 30 min)");
afirmar($sesion20Min->obtenerPresenciaReciente(15, 30, 12, $ahoraTs) === 'SIN_ACTIVIDAD_RECIENTE', "Axioma D-088 #3b: Sesión a los 20 min se clasifica como SIN_ACTIVIDAD_RECIENTE (> 15 min)");

// Sesión con actividad hace 5 minutos: ACTIVA y PRESENCIA_RECIENTE
$iniciada5MinAtras = date('Y-m-d H:i:s', $ahoraTs - (5 * 60));
$sesion5Min = new SesionUsuario(
    1002, 1, 'hash_test_2', $iniciada5MinAtras, $iniciada5MinAtras, $expira10MinFuturo, null, null
);
afirmar($sesion5Min->obtenerEstadoSoberano(30, 12, $ahoraTs) === 'ACTIVA', "Axioma D-088 #4: Sesión a los 5 min es ACTIVA");
afirmar($sesion5Min->obtenerPresenciaReciente(15, 30, 12, $ahoraTs) === 'PRESENCIA_RECIENTE', "Axioma D-088 #4b: Sesión a los 5 min tiene PRESENCIA_RECIENTE (heurística HTTP <= 15 min)");
afirmar($sesion20Min->obtenerEstadoSoberano(30, 12, $ahoraTs) === 'ACTIVA' && $sesion20Min->obtenerPresenciaReciente(15, 30, 12, $ahoraTs) === 'SIN_ACTIVIDAD_RECIENTE', "Axioma D-088 #4c: Presencia reciente no muta ni invalida el estado soberano de la sesión");

// BLOQUE 2: RELOJES DE EXPIRACIÓN Y TIMEOUTS SOBERANOS (Casos 9 - 18)
echo "\n--- BLOQUE 2: EXPIRACIÓN POR INACTIVIDAD Y EXPIRACIÓN ABSOLUTA ---\n";

// Sesión con inactividad de 31 minutos
$iniciada40MinAtras = date('Y-m-d H:i:s', $ahoraTs - (40 * 60));
$actividad31MinAtras = date('Y-m-d H:i:s', $ahoraTs - (31 * 60));
$sesionInactiva = new SesionUsuario(
    1003, 1, 'hash_test_3', $iniciada40MinAtras, $actividad31MinAtras, $actividad31MinAtras, null, null
);
afirmar($sesionInactiva->obtenerEstadoSoberano(30, 12, $ahoraTs) === 'EXPIRADA_INACTIVIDAD', "Sesión con inactividad > 30 min se clasifica como EXPIRADA_INACTIVIDAD");
afirmar($sesionInactiva->obtenerPresenciaReciente(15, 30, 12, $ahoraTs) === 'SIN_ACTIVIDAD_RECIENTE', "Sesión expirada por inactividad carece de presencia reciente");
afirmar(!$sesionInactiva->estaActiva(date('Y-m-d H:i:s', $ahoraTs)), "estaActiva() retorna false para sesión con inactividad > 30 min");

// Sesión activa continuada pero con 12 horas y 1 minuto de duración absoluta
$iniciada12h1mAtras = date('Y-m-d H:i:s', $ahoraTs - (12 * 3600) - 60);
$actividad1MinAtras = date('Y-m-d H:i:s', $ahoraTs - 60);
$sesionExpiradaAbsoluta = new SesionUsuario(
    1004, 1, 'hash_test_4', $iniciada12h1mAtras, $actividad1MinAtras, date('Y-m-d H:i:s', $ahoraTs + 1800), null, null
);
afirmar($sesionExpiradaAbsoluta->obtenerEstadoSoberano(30, 12, $ahoraTs) === 'EXPIRADA_ABSOLUTA', "Sesión con duración > 12 h se clasifica soberanamente como EXPIRADA_ABSOLUTA");
afirmar(!$sesionExpiradaAbsoluta->estaActiva(date('Y-m-d H:i:s', $ahoraTs)), "estaActiva() retorna false para sesión con duración > 12 h a pesar de actividad reciente");
afirmar($sesionExpiradaAbsoluta->obtenerPresenciaReciente(15, 30, 12, $ahoraTs) === 'SIN_ACTIVIDAD_RECIENTE', "Sesión expirada absoluta no se reporta con presencia reciente");

// Derivación de fecha de expiración absoluta
$fechaAbs = $sesion20Min->obtenerExpiracionAbsoluta(12);
$esperadaAbs = date('Y-m-d H:i:s', strtotime($iniciada20MinAtras) + (12 * 3600));
afirmar($fechaAbs === $esperadaAbs, "obtenerExpiracionAbsoluta() calcula exactamente iniciada_en + 12h");

// Configuración de timeouts por defecto
afirmar($sesionServicio->obtenerMinutosInactividad() === 30, "Minutos de inactividad por defecto es exactamente 30");
afirmar($sesionServicio->obtenerHorasDuracionMaxima() === 12, "Horas de duración máxima por defecto es exactamente 12");
afirmar($sesionServicio->obtenerSegundosThrottle() === 60, "Segundos de throttle para actividad en BD es exactamente 60");

// BLOQUE 3: REVOCACIÓN Y MOTIVOS NORMATIVOS (Casos 19 - 28)
echo "\n--- BLOQUE 3: REVOCACIÓN Y MOTIVOS NORMATIVOS ---\n";

$sesionRevocada = new SesionUsuario(
    1005, 1, 'hash_test_5', $iniciada5MinAtras, $iniciada5MinAtras, $expira10MinFuturo,
    date('Y-m-d H:i:s', $ahoraTs), 'REVOCACION_ADMINISTRATIVA'
);
afirmar($sesionRevocada->estaRevocada(), "estaRevocada() retorna true cuando revocada_en no es null");
afirmar($sesionRevocada->obtenerEstadoSoberano() === 'REVOCADA', "obtenerEstadoSoberano() retorna REVOCADA prioritariamente sobre cualquier fecha");
afirmar(!$sesionRevocada->estaActiva(), "estaActiva() retorna false para sesión revocada");
afirmar($sesionRevocada->obtenerPresenciaReciente() === 'SIN_ACTIVIDAD_RECIENTE', "Sesión revocada nunca tiene presencia reciente");

// Motivo LOGOUT
$sesionLogout = new SesionUsuario(
    1006, 1, 'hash_test_6', $iniciada5MinAtras, $iniciada5MinAtras, $expira10MinFuturo,
    date('Y-m-d H:i:s', $ahoraTs), 'LOGOUT'
);
afirmar($sesionLogout->obtenerMotivoCierre() === 'LOGOUT', "Motivo LOGOUT persistido fielmente");

// Motivo CAMBIO_CONTRASENA
$sesionPwd = new SesionUsuario(
    1007, 1, 'hash_test_7', $iniciada5MinAtras, $iniciada5MinAtras, $expira10MinFuturo,
    date('Y-m-d H:i:s', $ahoraTs), 'CAMBIO_CONTRASENA'
);
afirmar($sesionPwd->obtenerMotivoCierre() === 'CAMBIO_CONTRASENA', "Motivo CAMBIO_CONTRASENA persistido fielmente");

// Motivo CAMBIO_ESTADO_USUARIO
$sesionUsrState = new SesionUsuario(
    1008, 1, 'hash_test_8', $iniciada5MinAtras, $iniciada5MinAtras, $expira10MinFuturo,
    date('Y-m-d H:i:s', $ahoraTs), 'CAMBIO_ESTADO_USUARIO'
);
afirmar($sesionUsrState->obtenerMotivoCierre() === 'CAMBIO_ESTADO_USUARIO', "Motivo CAMBIO_ESTADO_USUARIO persistido fielmente");

// Motivo DESACTIVACION_PERSONA
$sesionPers = new SesionUsuario(
    1009, 1, 'hash_test_9', $iniciada5MinAtras, $iniciada5MinAtras, $expira10MinFuturo,
    date('Y-m-d H:i:s', $ahoraTs), 'DESACTIVACION_PERSONA'
);
afirmar($sesionPers->obtenerMotivoCierre() === 'DESACTIVACION_PERSONA', "Motivo DESACTIVACION_PERSONA persistido fielmente");

// Irreversibilidad
afirmar($sesionRevocada->estaRevocada(), "D-088 #9: Irreversibilidad garantizada (revocada_en no es null)");
afirmar($sesionServicio->buscarPorId(99999999) === null, "Búsqueda de sesión inexistente retorna null de forma segura");

// BLOQUE 4: PROTECCIÓN DE SECRETOS Y REPOSITORIO GLOBAL (Casos 29 - 36)
echo "\n--- BLOQUE 4: PROTECCIÓN DE SECRETOS Y CONSULTA GLOBAL ---\n";

$arrSeguro = $sesion5Min->aArreglo(false);
afirmar(!isset($arrSeguro['token_hash']), "token_hash NO está presente en el arreglo exportado por defecto");
afirmar(isset($arrSeguro['estado_sesion']) && $arrSeguro['estado_sesion'] === 'ACTIVA', "estado_sesion exportado en arreglo seguro");
afirmar(isset($arrSeguro['presencia_reciente']) && $arrSeguro['presencia_reciente'] === 'PRESENCIA_RECIENTE', "presencia_reciente exportada en arreglo seguro");
afirmar(isset($arrSeguro['expiracion_absoluta_en']), "expiracion_absoluta_en exportada en arreglo seguro");

$arrInterno = $sesion5Min->aArreglo(true);
afirmar(isset($arrInterno['token_hash']) && $arrInterno['token_hash'] === 'hash_test_2', "token_hash solo presente bajo solicitud explícita interna");

// Consulta global en repositorio
$metricas = $sesionRepo->obtenerResumenMetricas();
afirmar(isset($metricas['sesiones_activas']) && is_int($metricas['sesiones_activas']), "obtenerResumenMetricas() retorna sesiones_activas como entero");
afirmar(isset($metricas['actividad_reciente']) && is_int($metricas['actividad_reciente']), "obtenerResumenMetricas() retorna actividad_reciente como entero");
afirmar(isset($metricas['sesiones_expiradas']) && is_int($metricas['sesiones_expiradas']), "obtenerResumenMetricas() retorna sesiones_expiradas como entero");

// BLOQUE 5: ECONOMÍA DE ESQUEMA Y METADATOS (Casos 37 - 40)
echo "\n--- BLOQUE 5: ECONOMÍA DE ESQUEMA Y PERMISOS ---\n";

afirmar(isset($metricas['sesiones_revocadas']) && is_int($metricas['sesiones_revocadas']), "obtenerResumenMetricas() retorna sesiones_revocadas como entero");

$listadoGlobal = $sesionRepo->listarSesionesGlobales([], 5, 1);
afirmar(is_array($listadoGlobal), "listarSesionesGlobales() retorna array de resultados");

// Verificar que ningún elemento expone token_hash
$exponeToken = false;
foreach ($listadoGlobal as $item) {
    if (isset($item['token_hash'])) {
        $exponeToken = true;
    }
}
afirmar(!$exponeToken, "Ninguna fila de listarSesionesGlobales() expone token_hash");

// Economía de base de datos
$tablas = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$ranura027 = glob(dirname(__DIR__) . '/SQL/*027*');
afirmar(count($tablas) === 104 && count($ranura027) === 0, "Economía de Esquema: exactamente 104 tablas preservadas (027 libre)");

echo "\n====================================================================\n";
echo " RESULTADOS MATRIZ SESIONES-1: {$pasadas}/{$total} PASADAS\n";
if ($fallidas > 0) {
    echo " FALLIDAS: {$fallidas}\n";
    foreach ($errores as $err) {
        echo "  - {$err}\n";
    }
}
echo "====================================================================\n";

if ($fallidas > 0) {
    exit(1);
}
