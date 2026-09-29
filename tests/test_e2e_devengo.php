<?php

declare(strict_types=1);

/**
 * Suite de Verificación DEVENGO-ALOJAMIENTO-1 — Pruebas End-to-End (E2E) y API HTTP (10 Casos).
 * Gobernanza: D-090.
 *
 * Cubre:
 * - Consola web Alina: GET /operaciones/night-audit
 * - Endpoints JSON protegidos: historial, consulta de cierre, devengos por estadía
 * - Acciones de dominio con CSRF: ejecución de cierre y reversión supervisada
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CamargoPMS\Controladores\NightAuditControlador;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Servicios\CsrfServicio;
use CamargoPMS\Servicios\DevengoServicio;
use CamargoPMS\Servicios\NightAuditServicio;
use CamargoPMS\Servicios\SesionServicio;

Configuracion::cargar(dirname(__DIR__));
$pdo = BaseDatos::conexion();
SesionServicio::iniciarSesionPhp();

$total = 0;
$pasadas = 0;
$fallidas = 0;
$errores = [];

function afirmarE2E(bool $condicion, string $mensaje): void
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
echo " CAMARGO PMS — PRUEBAS E2E DEVENGO-ALOJAMIENTO-1 (10 CASOS HTTP/API)\n";
echo " Decisión Vinculante: D-090\n";
echo "====================================================================\n\n";

try {
    $stmtProp = $pdo->query('SELECT id FROM propiedades WHERE estado = "ACTIVO" LIMIT 1');
    $propiedadId = (int) $stmtProp->fetchColumn() ?: 1;

    $stmtUsr = $pdo->query('SELECT id FROM usuarios WHERE estado = "ACTIVO" LIMIT 1');
    $usuarioId = (int) $stmtUsr->fetchColumn() ?: 1;

    $stmtActor = $pdo->query('SELECT id FROM actores LIMIT 1');
    $actorId = (int) $stmtActor->fetchColumn() ?: 1;

    // Helper para crear una sesión activa simulada
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $ahora = date('Y-m-d H:i:s');
    $expira = date('Y-m-d H:i:s', time() + 1800);

    $stmtSes = $pdo->prepare('INSERT INTO sesiones_usuario (usuario_id, token_hash, iniciada_en, ultima_actividad_en, expira_en, ip, user_agent)
                              VALUES (:uid, :th, :ini, :act, :exp, "127.0.0.1", "E2E-Devengo/1.0")');
    $stmtSes->execute([
        'uid' => $usuarioId,
        'th' => $tokenHash,
        'ini' => $ahora,
        'act' => $ahora,
        'exp' => $expira,
    ]);

    $_SESSION[SesionServicio::CLAVE_SESION_TOKEN] = $token;
    $_SESSION[SesionServicio::CLAVE_USUARIO_ID] = $usuarioId;

    $csrfServicio = new CsrfServicio();
    $sesionServicio = new SesionServicio($pdo);
    $nightAuditServicio = new NightAuditServicio($pdo);
    $devengoServicio = new DevengoServicio($pdo);
    $controlador = new NightAuditControlador($nightAuditServicio, $devengoServicio, $csrfServicio, $sesionServicio, null, $pdo);

    $fechaAuditE2E = '2026-12-15';

    // Limpieza preventiva
    $pdo->exec('UPDATE devengos_alojamiento SET reverso_de_id = NULL WHERE estadia_id IN (SELECT id FROM estadias WHERE codigo LIKE "EST-DEV-E2E-%")');
    $pdo->exec('DELETE FROM devengos_alojamiento WHERE estadia_id IN (SELECT id FROM estadias WHERE codigo LIKE "EST-DEV-E2E-%") OR cierre_hotelero_id IN (SELECT id FROM cierres_hoteleros WHERE fecha_hotelera = "' . $fechaAuditE2E . '")');
    $pdo->exec('DELETE FROM cierres_hoteleros WHERE fecha_hotelera = "' . $fechaAuditE2E . '"');
    $pdo->exec('DELETE FROM estadias WHERE codigo LIKE "EST-DEV-E2E-%" OR reserva_id IN (SELECT id FROM reservas WHERE codigo LIKE "RES-DEV-E2E-%")');
    $pdo->exec('DELETE FROM reserva_unidades WHERE reserva_id IN (SELECT id FROM reservas WHERE codigo LIKE "RES-DEV-E2E-%")');
    $pdo->exec('DELETE FROM reservas WHERE codigo LIKE "RES-DEV-E2E-%"');

    // Fixture de estadía para el cierre
    $unidadId = (int) $pdo->query('SELECT id FROM unidades WHERE propiedad_id = ' . $propiedadId . ' AND estado = "ACTIVO" LIMIT 1')->fetchColumn() ?: 1;
    $personaId = (int) $pdo->query('SELECT id FROM personas LIMIT 1')->fetchColumn() ?: 1;

    $pdo->beginTransaction();
    $resCod = 'RES-DEV-E2E-' . uniqid();
    $pdo->prepare('INSERT INTO reservas (codigo, persona_titular_id, fecha_entrada, fecha_salida, noches, estado, canal, origen, moneda_codigo, subtotal, total)
                   VALUES (:c, :p, :fe, :fs, 2, "CONFIRMADA", "PMS", "DIRECTO", "PEN", 200.00, 200.00)')
        ->execute(['c' => $resCod, 'p' => $personaId, 'fe' => $fechaAuditE2E, 'fs' => '2026-12-17']);
    $resId = (int) $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO reserva_unidades (reserva_id, unidad_id, precio_unitario_noche, noches, subtotal, impuesto, total, moneda_codigo)
                   VALUES (:r, :u, 100.00, 2, 200.00, 0.00, 200.00, "PEN")')
        ->execute(['r' => $resId, 'u' => $unidadId]);
    $ruId = (int) $pdo->lastInsertId();

    $estCod = 'EST-DEV-E2E-' . uniqid();
    $pdo->prepare('INSERT INTO estadias (codigo, reserva_id, reserva_unidad_id, unidad_id, fecha_entrada, fecha_salida_prevista, estado, checkin_en, checkin_por_actor_id)
                   VALUES (:c, :r, :ru, :u, :fe, "2026-12-17", "EN_CURSO", :fe_hora, :a)')
        ->execute(['c' => $estCod, 'r' => $resId, 'ru' => $ruId, 'u' => $unidadId, 'fe' => $fechaAuditE2E, 'fe_hora' => $fechaAuditE2E . ' 14:00:00', 'a' => $actorId]);
    $estId = (int) $pdo->lastInsertId();
    $pdo->commit();

    // -------------------------------------------------------------------------
    // CASO 1: GET /operaciones/night-audit -> Vista Alina
    // -------------------------------------------------------------------------
    $respIndex = $controlador->index();
    afirmarE2E(
        $respIndex->obtenerCodigoEstado() === 200 &&
        str_contains($respIndex->obtenerCuerpo(), 'Auditoría Nocturna') &&
        str_contains($respIndex->obtenerCuerpo(), 'csrf_token'),
        'GET /operaciones/night-audit renderiza la vista Alina con código HTTP 200'
    );

    // -------------------------------------------------------------------------
    // CASO 2: GET /api/operaciones/night-audit/historial -> JSON
    // -------------------------------------------------------------------------
    $_GET = ['propiedad_id' => (string) $propiedadId, 'limite' => '10'];
    $respHistorial = $controlador->historial();
    $jsonHistorial = json_decode($respHistorial->obtenerCuerpo(), true);
    afirmarE2E(
        $respHistorial->obtenerCodigoEstado() === 200 &&
        isset($jsonHistorial['cierres']) &&
        is_array($jsonHistorial['cierres']),
        'GET /api/operaciones/night-audit/historial retorna JSON estructurado'
    );

    // -------------------------------------------------------------------------
    // CASO 3: GET /api/operaciones/night-audit/cierre para fecha no cerrada -> 404
    // -------------------------------------------------------------------------
    $_GET = ['propiedad_id' => (string) $propiedadId, 'fecha_hotelera' => '2099-01-01'];
    $respConsulta404 = $controlador->consultarCierre();
    $json404 = json_decode($respConsulta404->obtenerCuerpo(), true);
    afirmarE2E(
        $respConsulta404->obtenerCodigoEstado() === 404 &&
        ($json404['encontrado'] ?? true) === false,
        'GET /api/operaciones/night-audit/cierre retorna 404 cuando la fecha no ha sido auditada'
    );

    // -------------------------------------------------------------------------
    // CASO 4: POST /api/operaciones/night-audit/ejecutar sin CSRF -> 403
    // -------------------------------------------------------------------------
    $_SERVER['CONTENT_TYPE'] = 'application/json';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'token_falso_invalido';
    $_POST = [];
    $respSinCsrf = $controlador->ejecutar();
    afirmarE2E($respSinCsrf->obtenerCodigoEstado() === 403, 'POST /api/operaciones/night-audit/ejecutar sin token CSRF válido es bloqueado con HTTP 403');

    // -------------------------------------------------------------------------
    // CASO 5: POST /api/operaciones/night-audit/ejecutar con parámetros incompletos -> 422
    // -------------------------------------------------------------------------
    $csrfValido = $csrfServicio->generarToken();
    $_SERVER['HTTP_X_CSRF_TOKEN'] = $csrfValido;
    $_POST = [
        '_token' => $csrfValido,
        'propiedad_id' => 0, // Invalido
        'fecha_hotelera' => '',
    ];
    $respInvalido = $controlador->ejecutar();
    afirmarE2E($respInvalido->obtenerCodigoEstado() === 422, 'POST /api/operaciones/night-audit/ejecutar con parámetros incompletos responde HTTP 422');

    // -------------------------------------------------------------------------
    // CASO 6: POST /api/operaciones/night-audit/ejecutar con éxito -> 200
    // -------------------------------------------------------------------------
    $_POST = [
        '_token' => $csrfValido,
        'propiedad_id' => $propiedadId,
        'fecha_hotelera' => $fechaAuditE2E,
        'observaciones' => 'Cierre E2E Automatizado',
    ];
    $respCierre = $controlador->ejecutar();
    $jsonCierre = json_decode($respCierre->obtenerCuerpo(), true);
    afirmarE2E(
        $respCierre->obtenerCodigoEstado() === 200 &&
        isset($jsonCierre['cierre']['id']) &&
        $jsonCierre['cierre']['estado'] === 'CERRADO',
        'POST /api/operaciones/night-audit/ejecutar procesa el cierre y devuelve HTTP 200 con estado CERRADO'
    );

    // -------------------------------------------------------------------------
    // CASO 7: GET /api/operaciones/night-audit/cierre tras el cierre -> 200
    // -------------------------------------------------------------------------
    $_GET = ['propiedad_id' => (string) $propiedadId, 'fecha_hotelera' => $fechaAuditE2E];
    $respConsulta200 = $controlador->consultarCierre();
    $json200 = json_decode($respConsulta200->obtenerCuerpo(), true);
    afirmarE2E(
        $respConsulta200->obtenerCodigoEstado() === 200 &&
        ($json200['encontrado'] ?? false) === true &&
        $json200['cierre']['fecha_hotelera'] === $fechaAuditE2E,
        'GET /api/operaciones/night-audit/cierre recupera el snapshot congelado con HTTP 200'
    );

    // -------------------------------------------------------------------------
    // CASO 8: GET /api/operaciones/devengos/estadia/{id} -> 200
    // -------------------------------------------------------------------------
    $respDevengos = $controlador->devengosEstadia(['id' => $estId]);
    $jsonDevengos = json_decode($respDevengos->obtenerCuerpo(), true);
    $primerDevengo = $jsonDevengos['devengos'][0] ?? null;
    afirmarE2E(
        $respDevengos->obtenerCodigoEstado() === 200 &&
        !empty($jsonDevengos['devengos']) &&
        $primerDevengo !== null &&
        isset($primerDevengo['codigo']),
        'GET /api/operaciones/devengos/estadia/{id} retorna listado de devengos asociados a la estadía'
    );

    // -------------------------------------------------------------------------
    // CASO 9: POST /api/operaciones/devengos/revertir sin CSRF -> 403
    // -------------------------------------------------------------------------
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'token_csrf_invalido';
    $_POST = ['devengo_id' => $primerDevengo['id'] ?? 1, 'motivo' => 'Error de prueba'];
    $respRevSinCsrf = $controlador->revertirDevengo();
    afirmarE2E($respRevSinCsrf->obtenerCodigoEstado() === 403, 'POST /api/operaciones/devengos/revertir sin token CSRF es rechazado con HTTP 403');

    // -------------------------------------------------------------------------
    // CASO 10: POST /api/operaciones/devengos/revertir con éxito -> 200
    // -------------------------------------------------------------------------
    $csrfValidoRev = $csrfServicio->generarToken();
    $_SERVER['HTTP_X_CSRF_TOKEN'] = $csrfValidoRev;
    $_POST = [
        '_token' => $csrfValidoRev,
        'devengo_id' => (int) ($primerDevengo['id'] ?? 0),
        'motivo' => 'Corrección supervisada autorizada por jefe de recepción',
    ];
    $respRevExito = $controlador->revertirDevengo();
    $jsonRevExito = json_decode($respRevExito->obtenerCuerpo(), true);
    afirmarE2E(
        $respRevExito->obtenerCodigoEstado() === 200 &&
        ($jsonRevExito['devengo']['estado'] ?? '') === 'REVERTIDO',
        'POST /api/operaciones/devengos/revertir completa la reversión supervisada con estado REVERTIDO'
    );

} catch (Throwable $e) {
    $fallidas++;
    echo "\n[ERROR CRÍTICO E2E]: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
} finally {
    try {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $pdo->exec('UPDATE devengos_alojamiento SET reverso_de_id = NULL WHERE estadia_id IN (SELECT id FROM estadias WHERE codigo LIKE "EST-DEV-E2E-%")');
        $pdo->exec('DELETE FROM devengos_alojamiento WHERE estadia_id IN (SELECT id FROM estadias WHERE codigo LIKE "EST-DEV-E2E-%") OR cierre_hotelero_id IN (SELECT id FROM cierres_hoteleros WHERE fecha_hotelera = "2026-12-15")');
        $pdo->exec('DELETE FROM cierres_hoteleros WHERE fecha_hotelera = "2026-12-15"');
        $pdo->exec('DELETE FROM estadias WHERE codigo LIKE "EST-DEV-E2E-%" OR reserva_id IN (SELECT id FROM reservas WHERE codigo LIKE "RES-DEV-E2E-%")');
        $pdo->exec('DELETE FROM reserva_unidades WHERE reserva_id IN (SELECT id FROM reservas WHERE codigo LIKE "RES-DEV-E2E-%")');
        $pdo->exec('DELETE FROM reservas WHERE codigo LIKE "RES-DEV-E2E-%"');
    } catch (Throwable) {
    }
}

echo "\n====================================================================\n";
echo "RESULTADO E2E: $pasadas PASS | $fallidas FAIL\n";
echo "====================================================================\n";

if ($fallidas > 0) {
    exit(1);
}
exit(0);
