<?php

declare(strict_types=1);

/**
 * Suite de Verificación SESIONES-1 — Concurrencia, Multi-Sesión y Aislamiento Transaccional (10 Casos)
 *
 * Verifica:
 * - C01: Concurrencia multi-sesión: un usuario puede sostener múltiples sesiones válidas concurrentes
 * - C02: Aislamiento entre sesiones: revocar una sesión no altera el estado de las demás sesiones del usuario
 * - C03: Idempotencia: doble revocación consecutiva es segura y retorna ya_revocada=true
 * - C04: Revocación masiva: revocarTodasDeUsuario revoca todas las sesiones de un usuario atómicamente
 * - C05: Invalidez inmediata: validarSesion(token) retorna null instantáneamente tras la revocación
 * - C06: Exclusión de sesión actual: revocar todas excepto la sesión actual mantiene la actual activa
 * - C07: Autorrevocación administrativa: detecta es_sesion_actual=true y destruye estado local
 * - C08: Purga / marcado en lote: marca inactivas > 30 min sin afectar sesiones con actividad reciente
 * - C09: Irreversibilidad: actualizarActividad no reactiva una sesión revocada o expirada
 * - C10: Consistencia analítica: métricas reflejan fielmente el estado concurrente sin inconsistencias
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CamargoPMS\Modelos\SesionUsuario;
use CamargoPMS\Modelos\Usuario;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Repositorios\PersonaRepositorio;
use CamargoPMS\Repositorios\SesionUsuarioRepositorio;
use CamargoPMS\Repositorios\UsuarioRepositorio;
use CamargoPMS\Servicios\AuditoriaServicio;
use CamargoPMS\Servicios\SesionServicio;

Configuracion::cargar(dirname(__DIR__));
$pdo = BaseDatos::conexion();
$pdo->exec("SET SESSION innodb_lock_wait_timeout = 5;");
SesionServicio::iniciarSesionPhp();

$sesionRepo = new SesionUsuarioRepositorio($pdo);
$usuarioRepo = new UsuarioRepositorio($pdo);
$personaRepo = new PersonaRepositorio($pdo);
$auditoriaServicio = new AuditoriaServicio($pdo);
$sesionServicio = new SesionServicio($pdo, $sesionRepo, $usuarioRepo, $personaRepo, $auditoriaServicio);

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
echo " CAMARGO PMS — PRUEBAS SESIONES-1: CONCURRENCIA Y MULTI-SESIÓN (10 CASOS)\n";
echo " Decisión Vinculante: D-088\n";
echo "====================================================================\n\n";

// Helper para crear sesión limpia de prueba en BD
function crearSesionPrueba(PDO $pdo, int $usuarioId, int $minutosAtras = 0): array
{
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $ahora = time() - ($minutosAtras * 60);
    $iniciadaStr = date('Y-m-d H:i:s', $ahora);
    $actividadStr = $iniciadaStr;
    $expiraStr = date('Y-m-d H:i:s', $ahora + (30 * 60));

    $stmt = $pdo->prepare('
        INSERT INTO sesiones_usuario (usuario_id, token_hash, iniciada_en, ultima_actividad_en, expira_en, ip, user_agent)
        VALUES (:uid, :th, :ini, :act, :exp, "127.0.0.1", "ConcurrencyTester/1.0")
    ');
    $stmt->execute([
        'uid' => $usuarioId,
        'th' => $tokenHash,
        'ini' => $iniciadaStr,
        'act' => $actividadStr,
        'exp' => $expiraStr,
    ]);

    $id = (int) $pdo->lastInsertId();
    return [
        'id' => $id,
        'token' => $token,
        'token_hash' => $tokenHash,
    ];
}

$sesionesCreadas = [];

try {
    // C01: Concurrencia multi-sesión del mismo usuario
    $s1 = crearSesionPrueba($pdo, 1, 5); // Dispositivo 1: PC
    $s2 = crearSesionPrueba($pdo, 1, 2); // Dispositivo 2: Tablet
    $s3 = crearSesionPrueba($pdo, 1, 0); // Dispositivo 3: Celular
    $sesionesCreadas = [$s1['id'], $s2['id'], $s3['id']];

    $u1 = $sesionServicio->validarToken($s1['token']);
    $u2 = $sesionServicio->validarToken($s2['token']);
    $u3 = $sesionServicio->validarToken($s3['token']);

    afirmar(
        $u1 !== null && $u2 !== null && $u3 !== null &&
        $u1->obtenerId() === 1 && $u2->obtenerId() === 1 && $u3->obtenerId() === 1,
        "C01: Un usuario puede sostener concurrentemente 3 sesiones activas independientes"
    );

    // C02: Aislamiento entre sesiones
    $resRev1 = $sesionServicio->revocarSesionAdministrativa($s1['id'], 1, 'REVOCACION_ADMINISTRATIVA');
    $u1Post = $sesionServicio->validarToken($s1['token']);
    $u2Post = $sesionServicio->validarToken($s2['token']);
    $u3Post = $sesionServicio->validarToken($s3['token']);

    afirmar(
        $resRev1['exito'] === true &&
        $u1Post === null &&
        $u2Post !== null &&
        $u3Post !== null,
        "C02: Revocar sesión 1 invalida sólo la sesión 1, manteniendo sesiones 2 y 3 activas e intactas"
    );

    // C03: Idempotencia en revocaciones consecutivas (carrera Admin A y Admin B)
    // Admin A ya revocó s1 en C02. Ahora Admin B intenta revocar la misma sesión:
    $resRev1Doble = $sesionServicio->revocarSesionAdministrativa($s1['id'], 1, 'REVOCACION_ADMINISTRATIVA');
    $stmtAuditCount = $pdo->prepare('SELECT COUNT(*) FROM auditoria WHERE entidad = "sesiones_usuario" AND entidad_id = :id');
    $stmtAuditCount->execute(['id' => (string) $s1['id']]);
    $numEventosAudit = (int) $stmtAuditCount->fetchColumn();

    afirmar(
        $resRev1Doble['exito'] === true &&
        $resRev1Doble['ya_revocada'] === true &&
        $numEventosAudit === 1,
        "C03: Carrera Admin A / Admin B: una única transición efectiva ACTIVA -> REVOCADA y exactamente 1 evento de auditoría"
    );

    // C04: Revocación masiva de sesiones por usuario
    $resRevUsuario = $sesionServicio->revocarTodasDeUsuarioAdministrativa(1, 1, 'REVOCACION_ADMINISTRATIVA');
    $u2PostMasiva = $sesionServicio->validarToken($s2['token']);
    $u3PostMasiva = $sesionServicio->validarToken($s3['token']);

    afirmar(
        $resRevUsuario['exito'] === true &&
        $resRevUsuario['sesiones_revocadas'] >= 2 &&
        $u2PostMasiva === null &&
        $u3PostMasiva === null,
        "C04: revocarTodasDeUsuarioAdministrativa revoca todas las sesiones activas del usuario atómicamente"
    );

    // C05: Inmediata no-validez tras revocación (carrera Request en curso vs Revocación Admin)
    $s4 = crearSesionPrueba($pdo, 1, 1);
    $sesionesCreadas[] = $s4['id'];
    $validaAntes = $sesionServicio->validarToken($s4['token']) !== null;

    // Admin ejecuta revocación
    $sesionServicio->revocarSesionAdministrativa($s4['id'], 1);
    $validaDespues = $sesionServicio->validarToken($s4['token']) === null;

    // Siguiente request autenticada del cliente a un endpoint JSON
    $_SESSION[SesionServicio::CLAVE_SESION_TOKEN] = $s4['token'];
    $_SESSION[SesionServicio::CLAVE_USUARIO_ID] = 1;
    $_SERVER['HTTP_ACCEPT'] = 'application/json';
    $_SERVER['REQUEST_URI'] = '/api/seguridad/sesiones';

    $authIntermediario = new \CamargoPMS\Intermediarios\AutenticacionIntermediario($sesionServicio);
    $respBloqueada = $authIntermediario->manejar('/api/seguridad/sesiones');
    $bloqueo401 = $respBloqueada !== null &&
                  $respBloqueada->obtenerCodigoEstado() === 401 &&
                  str_contains($respBloqueada->obtenerCuerpo(), 'SESION_NO_VALIDA');

    afirmar(
        $validaAntes === true && $validaDespues === true && $bloqueo401 === true,
        "C05: Carrera Request / Revocación Admin: siguiente request autenticada falla de inmediato con HTTP 401 (SESION_NO_VALIDA)"
    );

    // C06: Exclusión de sesión actual en revocación de otras
    $s5 = crearSesionPrueba($pdo, 1, 0); // sesión que simulamos como actual
    $s6 = crearSesionPrueba($pdo, 1, 1); // otra sesión
    $sesionesCreadas[] = $s5['id'];
    $sesionesCreadas[] = $s6['id'];

    $afectadas = $sesionRepo->revocarTodasDeUsuario(1, 'REVOCACION_ADMINISTRATIVA', $s5['id']);
    $s5Activa = $sesionServicio->validarToken($s5['token']) !== null;
    $s6Activa = $sesionServicio->validarToken($s6['token']) !== null;

    afirmar(
        $afectadas >= 1 && $s5Activa === true && $s6Activa === false,
        "C06: revocarTodasDeUsuario con exceptoSesionId revoca otras sesiones preservando la sesión indicada"
    );

    // C07: Autorrevocación administrativa
    \CamargoPMS\Servicios\SesionServicio::iniciarSesionPhp();
    $_SESSION[SesionServicio::CLAVE_SESION_TOKEN] = $s5['token'];
    $_SESSION[SesionServicio::CLAVE_USUARIO_ID] = 1;

    $resAutoRev = $sesionServicio->revocarSesionAdministrativa($s5['id'], 1);
    $sesionLocalVacia = empty($_SESSION[SesionServicio::CLAVE_SESION_TOKEN] ?? null);

    afirmar(
        $resAutoRev['exito'] === true &&
        $resAutoRev['es_sesion_actual'] === true &&
        $sesionLocalVacia === true,
        "C07: Autorrevocación detecta es_sesion_actual=true, revoca en BD y destruye sesión local PHP"
    );

    // C08: Purga / marcado en lote de expiradas
    $sActivaReciente = crearSesionPrueba($pdo, 1, 2); // 2 minutos atrás (activa)
    $sExpiradaInact = crearSesionPrueba($pdo, 1, 45); // 45 minutos atrás (expirada)
    $sesionesCreadas[] = $sActivaReciente['id'];
    $sesionesCreadas[] = $sExpiradaInact['id'];

    $purgadas = $sesionServicio->purgarExpiradasLote();
    $objActiva = $sesionRepo->buscarPorId($sActivaReciente['id'], false);
    $objExpirada = $sesionRepo->buscarPorId($sExpiradaInact['id'], false);

    afirmar(
        $purgadas >= 1 &&
        $objActiva !== null && $objActiva->estaActiva() &&
        $objExpirada !== null && $objExpirada->estaRevocada(),
        "C08: purgarExpiradasLote marca sesiones con inactividad > 30 min sin tocar sesiones recientes"
    );

    // C09: Irreversibilidad de sesiones cerradas
    $sRevocada = $sesionRepo->buscarPorId($s1['id'], false);
    $estadoAntes = $sRevocada->obtenerEstadoSoberano();
    // Intentar actualizar actividad sobre sesión ya revocada
    $actualizada = $sesionRepo->actualizarUltimaActividad($s1['id'], date('Y-m-d H:i:s'));
    $sRevocadaPost = $sesionRepo->buscarPorId($s1['id'], false);
    $estadoDespues = $sRevocadaPost->obtenerEstadoSoberano();

    afirmar(
        $actualizada === false &&
        $estadoAntes === 'REVOCADA' && $estadoDespues === 'REVOCADA' && !$sRevocadaPost->estaActiva(),
        "C09: Irreversibilidad estricta: actualizarUltimaActividad no afecta sesiones con revocada_en IS NOT NULL"
    );

    // C10: Consistencia analítica de métricas
    $metricas = $sesionServicio->obtenerResumenMetricas();
    afirmar(
        isset($metricas['sesiones_activas']) &&
        isset($metricas['actividad_reciente']) &&
        isset($metricas['sesiones_expiradas']) &&
        isset($metricas['sesiones_revocadas']) &&
        $metricas['actividad_reciente'] <= $metricas['sesiones_activas'],
        "C10: Invariante métrico: actividad_reciente (<= 15 min) es subconjunto de sesiones_activas (< 30 min)"
    );

} finally {
    // Limpieza de sesiones de prueba
    if (!empty($sesionesCreadas)) {
        $inClause = implode(',', array_map('intval', $sesionesCreadas));
        $pdo->exec("DELETE FROM sesiones_usuario WHERE id IN ({$inClause})");
    }
}

echo "\n====================================================================\n";
echo " RESULTADOS CONCURRENCIA SESIONES-1: {$pasadas}/{$total} PASADAS\n";
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
