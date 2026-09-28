<?php

declare(strict_types=1);

/**
 * Suite de Verificación SESIONES-1 — Pruebas End-to-End (E2E) y Ciclo de Vida HTTP/API (10 Casos)
 *
 * SESIONES-1 / D-088.
 * Verifica la consola web Alina, endpoints JSON de listado, métricas, revocación y purga.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CamargoPMS\Controladores\SesionControlador;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Repositorios\PersonaRepositorio;
use CamargoPMS\Repositorios\SesionUsuarioRepositorio;
use CamargoPMS\Repositorios\UsuarioRepositorio;
use CamargoPMS\Servicios\AuditoriaServicio;
use CamargoPMS\Servicios\CsrfServicio;
use CamargoPMS\Servicios\SesionServicio;

Configuracion::cargar(dirname(__DIR__));
$pdo = BaseDatos::conexion();
SesionServicio::iniciarSesionPhp();

$sesionRepo = new SesionUsuarioRepositorio($pdo);
$usuarioRepo = new UsuarioRepositorio($pdo);
$personaRepo = new PersonaRepositorio($pdo);
$auditoriaServicio = new AuditoriaServicio($pdo);
$sesionServicio = new SesionServicio($pdo, $sesionRepo, $usuarioRepo, $personaRepo, $auditoriaServicio);
$csrfServicio = new CsrfServicio();
$controlador = new SesionControlador($sesionServicio, $csrfServicio);

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
echo " CAMARGO PMS — PRUEBAS E2E SESIONES-1 (10 CASOS HTTP/API)\n";
echo " Decisión Vinculante: D-088\n";
echo "====================================================================\n\n";

// Helper para crear sesiones temporales
function crearSesionE2E(PDO $pdo, int $usuarioId, int $minutosAtras = 0): array
{
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $ahora = time() - ($minutosAtras * 60);
    $iniciadaStr = date('Y-m-d H:i:s', $ahora);
    $actividadStr = $iniciadaStr;
    $expiraStr = date('Y-m-d H:i:s', $ahora + (30 * 60));

    $stmt = $pdo->prepare('
        INSERT INTO sesiones_usuario (usuario_id, token_hash, iniciada_en, ultima_actividad_en, expira_en, ip, user_agent)
        VALUES (:uid, :th, :ini, :act, :exp, "127.0.0.1", "E2ETester/1.0")
    ');
    $stmt->execute([
        'uid' => $usuarioId,
        'th' => $tokenHash,
        'ini' => $iniciadaStr,
        'act' => $actividadStr,
        'exp' => $expiraStr,
    ]);

    return [
        'id' => (int) $pdo->lastInsertId(),
        'token' => $token,
        'token_hash' => $tokenHash,
    ];
}

$sesionesLimpieza = [];

try {
    // Preparar sesión activa del usuario administrador para simular contexto web
    $adminSesion = crearSesionE2E($pdo, 1, 0);
    $sesionesLimpieza[] = $adminSesion['id'];

    $_SESSION[SesionServicio::CLAVE_SESION_TOKEN] = $adminSesion['token'];
    $_SESSION[SesionServicio::CLAVE_USUARIO_ID] = 1;
    $csrfTokenValido = $csrfServicio->generarToken();

    // -------------------------------------------------------------------------
    // E2E-01: Carga de Vista Alina /seguridad/sesiones
    // -------------------------------------------------------------------------
    $respConsola = $controlador->mostrarConsolaSesiones();
    $htmlConsola = $respConsola->obtenerCuerpo();
    afirmar(
        $respConsola->obtenerCodigoEstado() === 200 &&
        str_contains($htmlConsola, 'id="app-sesiones"') &&
        str_contains($htmlConsola, 'tabla-sesiones') &&
        str_contains($htmlConsola, 'filtro-estado') &&
        !str_contains($htmlConsola, 'fonts.googleapis.com'),
        "Caso 1: GET /seguridad/sesiones renderiza consola Alina con estructura requerida y sin Google Fonts"
    );

    // -------------------------------------------------------------------------
    // E2E-02: Endpoint API GET /api/seguridad/sesiones (Listado por defecto)
    // -------------------------------------------------------------------------
    $_GET = [];
    $respListado = $controlador->apiListarSesiones();
    $jsonListado = json_decode($respListado->obtenerCuerpo(), true);
    afirmar(
        $respListado->obtenerCodigoEstado() === 200 &&
        isset($jsonListado['ok']) && $jsonListado['ok'] === true &&
        isset($jsonListado['datos']['items']) && is_array($jsonListado['datos']['items']) &&
        isset($jsonListado['datos']['total']) &&
        isset($jsonListado['datos']['pagina']) &&
        isset($jsonListado['datos']['limite']),
        "Caso 2: GET /api/seguridad/sesiones retorna estructura JSON paginada completa"
    );

    // -------------------------------------------------------------------------
    // E2E-03: Filtrado por Estado (ACTIVA)
    // -------------------------------------------------------------------------
    $_GET = ['estado' => 'ACTIVA'];
    $respFiltroEstado = $controlador->apiListarSesiones();
    $jsonFiltroEstado = json_decode($respFiltroEstado->obtenerCuerpo(), true);
    $soloActivas = true;
    if (!empty($jsonFiltroEstado['datos']['items'])) {
        foreach ($jsonFiltroEstado['datos']['items'] as $item) {
            if ($item['estado_sesion'] !== 'ACTIVA') {
                $soloActivas = false;
                break;
            }
        }
    }
    afirmar(
        $respFiltroEstado->obtenerCodigoEstado() === 200 && $soloActivas,
        "Caso 3: GET /api/seguridad/sesiones?estado=ACTIVA filtra únicamente sesiones en estado ACTIVA"
    );

    // -------------------------------------------------------------------------
    // E2E-04: Filtrado por Presencia (PRESENCIA_RECIENTE)
    // -------------------------------------------------------------------------
    $_GET = ['presencia' => 'PRESENCIA_RECIENTE'];
    $respFiltroPresencia = $controlador->apiListarSesiones();
    $jsonFiltroPresencia = json_decode($respFiltroPresencia->obtenerCuerpo(), true);
    $soloRecientes = true;
    if (!empty($jsonFiltroPresencia['datos']['items'])) {
        foreach ($jsonFiltroPresencia['datos']['items'] as $item) {
            if ($item['presencia_reciente'] !== 'PRESENCIA_RECIENTE') {
                $soloRecientes = false;
                break;
            }
        }
    }
    afirmar(
        $respFiltroPresencia->obtenerCodigoEstado() === 200 && $soloRecientes,
        "Caso 4: GET /api/seguridad/sesiones?presencia=PRESENCIA_RECIENTE filtra sesiones con presencia <= 15 min"
    );

    // -------------------------------------------------------------------------
    // E2E-05: Filtrado por Usuario (usuario_id = 1)
    // -------------------------------------------------------------------------
    $_GET = ['usuario_id' => '1'];
    $respFiltroUsuario = $controlador->apiListarSesiones();
    $jsonFiltroUsuario = json_decode($respFiltroUsuario->obtenerCuerpo(), true);
    $soloUsuario1 = true;
    if (!empty($jsonFiltroUsuario['datos']['items'])) {
        foreach ($jsonFiltroUsuario['datos']['items'] as $item) {
            if ((int) $item['usuario_id'] !== 1) {
                $soloUsuario1 = false;
                break;
            }
        }
    }
    afirmar(
        $respFiltroUsuario->obtenerCodigoEstado() === 200 && $soloUsuario1,
        "Caso 5: GET /api/seguridad/sesiones?usuario_id=1 restringe la consulta al usuario especificado"
    );

    // -------------------------------------------------------------------------
    // E2E-06: Endpoint API GET /api/seguridad/sesiones/metricas
    // -------------------------------------------------------------------------
    $respMetricas = $controlador->apiMetricas();
    $jsonMetricas = json_decode($respMetricas->obtenerCuerpo(), true);
    afirmar(
        $respMetricas->obtenerCodigoEstado() === 200 &&
        isset($jsonMetricas['ok']) && $jsonMetricas['ok'] === true &&
        isset($jsonMetricas['datos']['sesiones_activas']) &&
        isset($jsonMetricas['datos']['actividad_reciente']) &&
        isset($jsonMetricas['datos']['sesiones_expiradas']) &&
        isset($jsonMetricas['datos']['sesiones_revocadas']),
        "Caso 6: GET /api/seguridad/sesiones/metricas entrega métricas operativas en tiempo real"
    );

    // -------------------------------------------------------------------------
    // E2E-07: Endpoint API POST /api/seguridad/sesiones/{id}/revocar (Revocación exitosa)
    // -------------------------------------------------------------------------
    $sesionObjetivo = crearSesionE2E($pdo, 1, 3);
    $sesionesLimpieza[] = $sesionObjetivo['id'];

    $_POST = [
        'csrf_token' => $csrfTokenValido,
        'motivo' => 'REVOCACION_ADMINISTRATIVA',
    ];
    $_SERVER['HTTP_ACCEPT'] = 'application/json';

    $respRevocar = $controlador->apiRevocarSesion($sesionObjetivo['id']);
    $jsonRevocar = json_decode($respRevocar->obtenerCuerpo(), true);
    $objPostRev = $sesionRepo->buscarPorId($sesionObjetivo['id'], false);

    afirmar(
        $respRevocar->obtenerCodigoEstado() === 200 &&
        isset($jsonRevocar['ok']) && $jsonRevocar['ok'] === true &&
        isset($jsonRevocar['datos']['es_sesion_actual']) && $jsonRevocar['datos']['es_sesion_actual'] === false &&
        $objPostRev !== null && $objPostRev->estaRevocada(),
        "Caso 7: POST /api/seguridad/sesiones/{id}/revocar con CSRF revoca la sesión exitosamente"
    );

    // -------------------------------------------------------------------------
    // E2E-08: Endpoint API POST /api/seguridad/sesiones/{id}/revocar (ID inexistente)
    // -------------------------------------------------------------------------
    $_POST = ['csrf_token' => $csrfTokenValido];
    $respRevInexistente = $controlador->apiRevocarSesion(99999999);
    $jsonRevInexistente = json_decode($respRevInexistente->obtenerCuerpo(), true);
    afirmar(
        $respRevInexistente->obtenerCodigoEstado() === 404 &&
        isset($jsonRevInexistente['ok']) && $jsonRevInexistente['ok'] === false,
        "Caso 8: POST /api/seguridad/sesiones/{id}/revocar con ID inexistente retorna HTTP 404 controlado"
    );

    // -------------------------------------------------------------------------
    // E2E-09: Endpoint API POST /api/seguridad/sesiones/usuario/{usuarioId}/revocar-todas
    // -------------------------------------------------------------------------
    $sExtra1 = crearSesionE2E($pdo, 1, 1);
    $sExtra2 = crearSesionE2E($pdo, 1, 2);
    $sesionesLimpieza[] = $sExtra1['id'];
    $sesionesLimpieza[] = $sExtra2['id'];

    $_POST = [
        'csrf_token' => $csrfTokenValido,
        'motivo' => 'REVOCACION_ADMINISTRATIVA',
    ];
    $respRevTodas = $controlador->apiRevocarUsuario(1);
    $jsonRevTodas = json_decode($respRevTodas->obtenerCuerpo(), true);

    afirmar(
        $respRevTodas->obtenerCodigoEstado() === 200 &&
        isset($jsonRevTodas['ok']) && $jsonRevTodas['ok'] === true &&
        isset($jsonRevTodas['datos']['sesiones_revocadas']) &&
        $jsonRevTodas['datos']['sesiones_revocadas'] >= 2,
        "Caso 9: POST /api/seguridad/sesiones/usuario/{id}/revocar-todas revoca todas las sesiones del usuario"
    );

    // -------------------------------------------------------------------------
    // E2E-10: Endpoint API POST /api/seguridad/sesiones/purgar-expiradas
    // -------------------------------------------------------------------------
    // Re-crear sesión de admin para poder invocar purga tras haber revocado todas las de usuario 1
    $adminSesion2 = crearSesionE2E($pdo, 1, 0);
    $sesionesLimpieza[] = $adminSesion2['id'];
    $_SESSION[SesionServicio::CLAVE_SESION_TOKEN] = $adminSesion2['token'];
    $_SESSION[SesionServicio::CLAVE_USUARIO_ID] = 1;
    $csrfTokenValido2 = $csrfServicio->generarToken();

    $_POST = ['csrf_token' => $csrfTokenValido2];
    $respPurga = $controlador->apiPurgarExpiradas();
    $jsonPurga = json_decode($respPurga->obtenerCuerpo(), true);

    afirmar(
        $respPurga->obtenerCodigoEstado() === 200 &&
        isset($jsonPurga['ok']) && $jsonPurga['ok'] === true &&
        isset($jsonPurga['datos']['total_purgadas']),
        "Caso 10: POST /api/seguridad/sesiones/purgar-expiradas ejecuta purga en lote de sesiones expiradas"
    );

} finally {
    // Limpieza de sesiones de prueba
    if (!empty($sesionesLimpieza)) {
        $inClause = implode(',', array_map('intval', $sesionesLimpieza));
        $pdo->exec("DELETE FROM sesiones_usuario WHERE id IN ({$inClause})");
    }
}

echo "\n====================================================================\n";
echo " RESULTADOS E2E SESIONES-1: {$pasadas}/{$total} PASADAS\n";
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
