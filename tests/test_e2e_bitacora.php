<?php

declare(strict_types=1);

/**
 * Suite de Verificación BITÁCORA-1 — Pruebas End-to-End (E2E) y API HTTP (10 Casos)
 *
 * Verifica el ciclo de vida completo a través del controlador HTTP/API:
 * - Consola web Alina: GET /operaciones/bitacora
 * - Endpoints JSON protegidos: listar, detalle, métricas
 * - Acciones de dominio con CSRF: crear, seguimiento, cambio de estado, resolver y anular
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CamargoPMS\Controladores\BitacoraControlador;
use CamargoPMS\Modelos\BitacoraEntrada;
use CamargoPMS\Modelos\BitacoraSeguimiento;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Repositorios\BitacoraRepositorio;
use CamargoPMS\Repositorios\PersonaRepositorio;
use CamargoPMS\Repositorios\SesionUsuarioRepositorio;
use CamargoPMS\Repositorios\UsuarioRepositorio;
use CamargoPMS\Servicios\AuditoriaServicio;
use CamargoPMS\Servicios\BitacoraServicio;
use CamargoPMS\Servicios\CsrfServicio;
use CamargoPMS\Servicios\SesionServicio;

Configuracion::cargar(dirname(__DIR__));
$pdo = BaseDatos::conexion();
SesionServicio::iniciarSesionPhp();

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
$csrfServicio = new CsrfServicio();
$sesionServicio = new SesionServicio($pdo);

$controlador = new BitacoraControlador($bitacoraServicio, $csrfServicio, $sesionServicio, null, $pdo);

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
echo " CAMARGO PMS — PRUEBAS E2E BITÁCORA-1 (10 CASOS HTTP/API)\n";
echo " Decisión Vinculante: D-089\n";
echo "====================================================================\n\n";

// Helper para crear una sesión activa simulada
function crearSesionActivaE2E(PDO $pdo, int $uid): string
{
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $ahora = date('Y-m-d H:i:s');
    $expira = date('Y-m-d H:i:s', time() + (30 * 60));

    $stmt = $pdo->prepare('
        INSERT INTO sesiones_usuario (usuario_id, token_hash, iniciada_en, ultima_actividad_en, expira_en, ip, user_agent)
        VALUES (:uid, :th, :ini, :act, :exp, "127.0.0.1", "E2E-Bitacora/1.0")
    ');
    $stmt->execute([
        'uid' => $uid,
        'th' => $tokenHash,
        'ini' => $ahora,
        'act' => $ahora,
        'exp' => $expira,
    ]);

    return $token;
}

$tokenSesion = crearSesionActivaE2E($pdo, $usuarioId);
$_SESSION[SesionServicio::CLAVE_SESION_TOKEN] = $tokenSesion;
$_SESSION[SesionServicio::CLAVE_USUARIO_ID] = $usuarioId;
$csrfValido = $csrfServicio->generarToken();

// Caso 1: GET /operaciones/bitacora -> Vista Alina
$respIndex = $controlador->index();
afirmar(
    $respIndex->obtenerCodigoEstado() === 200 &&
    str_contains($respIndex->obtenerCuerpo(), 'Libro de Guardia y Bitácora Operacional'),
    "GET /operaciones/bitacora renderiza la vista Alina con código HTTP 200"
);

// Caso 2: GET /api/operaciones/bitacora -> Listado JSON
$_GET = ['propiedad_id' => (string) $propiedadId, 'pagina' => '1', 'limite' => '10'];
$respListar = $controlador->apiListar();
$jsonListar = json_decode($respListar->obtenerCuerpo(), true);
afirmar(
    $respListar->obtenerCodigoEstado() === 200 &&
    ($jsonListar['ok'] ?? false) === true &&
    isset($jsonListar['datos']['entradas']),
    "GET /api/operaciones/bitacora retorna JSON estructurado con entradas y paginación"
);

// Caso 3: GET /api/operaciones/bitacora/metricas -> Métricas de turno
$_GET = ['propiedad_id' => (string) $propiedadId];
$respMetricas = $controlador->apiMetricas();
$jsonMetricas = json_decode($respMetricas->obtenerCuerpo(), true);
afirmar(
    $respMetricas->obtenerCodigoEstado() === 200 &&
    isset($jsonMetricas['datos']['total_general']) &&
    isset($jsonMetricas['datos']['pendientes']),
    "GET /api/operaciones/bitacora/metricas retorna agregaciones operacionales en tiempo real"
);

// Caso 4: POST /api/operaciones/bitacora sin CSRF -> 403
$_SERVER['CONTENT_TYPE'] = 'application/json';
$_SERVER['HTTP_X_CSRF_TOKEN'] = 'token_invalido_falso';
$_POST = [];
$respSinCsrf = $controlador->apiCrear();
afirmar($respSinCsrf->obtenerCodigoEstado() === 403, "POST /api/operaciones/bitacora sin token CSRF válido es bloqueado con HTTP 403");

// Caso 5: POST /api/operaciones/bitacora con datos válidos -> 201
$_SERVER['HTTP_X_CSRF_TOKEN'] = $csrfValido;
$_POST = [
    '_csrf_token' => $csrfValido,
    'propiedad_id' => $propiedadId,
    'tipo' => 'CONSIGNA',
    'prioridad' => 'ALTA',
    'titulo' => 'Consigna E2E: Custodia de paquete para habitación 102',
    'contenido' => 'Recepción recibe paquete de mensajería para entregar al huésped durante el check-in.',
    'turno' => 'TARDE',
    'fecha_operativa' => date('Y-m-d'),
];
$respCrear = $controlador->apiCrear();
$jsonCrear = json_decode($respCrear->obtenerCuerpo(), true);
$entradaCreadaId = (int) ($jsonCrear['datos']['id'] ?? 0);
afirmar(
    $respCrear->obtenerCodigoEstado() === 201 &&
    ($jsonCrear['ok'] ?? false) === true &&
    $entradaCreadaId > 0,
    "POST /api/operaciones/bitacora registra la entrada y responde con HTTP 201"
);

// Caso 6: GET /api/operaciones/bitacora/{id} -> Detalle con seguimientos
$respDetalle = $controlador->apiObtenerDetalle($entradaCreadaId);
$jsonDetalle = json_decode($respDetalle->obtenerCuerpo(), true);
afirmar(
    $respDetalle->obtenerCodigoEstado() === 200 &&
    ($jsonDetalle['datos']['id'] ?? 0) === $entradaCreadaId &&
    isset($jsonDetalle['datos']['seguimientos']),
    "GET /api/operaciones/bitacora/{id} retorna detalle enriquecido de la entrada"
);

// Caso 7: POST /api/operaciones/bitacora/{id}/seguimiento -> 201
$_POST = [
    '_csrf_token' => $csrfValido,
    'tipo_evento' => 'COMENTARIO',
    'contenido' => 'Seguimiento E2E: Se verificó la guía de entrega con el transportista.',
];
$respSeg = $controlador->apiAgregarSeguimiento($entradaCreadaId);
$jsonSeg = json_decode($respSeg->obtenerCuerpo(), true);
afirmar(
    $respSeg->obtenerCodigoEstado() === 201 &&
    ($jsonSeg['ok'] ?? false) === true &&
    ($jsonSeg['datos']['id'] ?? 0) > 0,
    "POST /api/operaciones/bitacora/{id}/seguimiento añade evento append-only con HTTP 201"
);

// Caso 8: POST /api/operaciones/bitacora/{id}/estado -> 200
$_POST = [
    '_csrf_token' => $csrfValido,
    'nuevo_estado' => 'EN_PROCESO',
    'nota' => 'Recepción notifica al botones para la entrega.',
];
$respEst = $controlador->apiCambiarEstado($entradaCreadaId);
$jsonEst = json_decode($respEst->obtenerCuerpo(), true);
afirmar(
    $respEst->obtenerCodigoEstado() === 200 &&
    ($jsonEst['datos']['estado'] ?? '') === 'EN_PROCESO',
    "POST /api/operaciones/bitacora/{id}/estado actualiza a EN_PROCESO con HTTP 200"
);

// Caso 9: POST /api/operaciones/bitacora/{id}/resolver -> 200
$_POST = [
    '_csrf_token' => $csrfValido,
    'nota_resolucion' => 'Paquete entregado personalmente al huésped titular a las 19:15 con firma.',
];
$respRes = $controlador->apiResolver($entradaCreadaId);
$jsonRes = json_decode($respRes->obtenerCuerpo(), true);
afirmar(
    $respRes->obtenerCodigoEstado() === 200 &&
    ($jsonRes['datos']['estado'] ?? '') === 'RESUELTA',
    "POST /api/operaciones/bitacora/{id}/resolver concluye la consigna con HTTP 200"
);

// Caso 10: POST /api/operaciones/bitacora/{id}/anular -> 200 (ANULAR != DELETE)
// Crear otra entrada para anularla por API
$_POST = [
    '_csrf_token' => $csrfValido,
    'propiedad_id' => $propiedadId,
    'tipo' => 'NOVEDAD',
    'prioridad' => 'BAJA',
    'titulo' => 'Novedad E2E para anulación supervisada',
    'contenido' => 'Registro emitido por error de pruebas.',
];
$respCrearParaAnular = $controlador->apiCrear();
$jsonCrearParaAnular = json_decode($respCrearParaAnular->obtenerCuerpo(), true);
$idParaAnular = (int) ($jsonCrearParaAnular['datos']['id'] ?? 0);

$_POST = [
    '_csrf_token' => $csrfValido,
    'motivo_anulacion' => 'Anulación supervisada certificada en prueba E2E (ANULAR != DELETE)',
];
$respAnul = $controlador->apiAnular($idParaAnular);
$jsonAnul = json_decode($respAnul->obtenerCuerpo(), true);
afirmar(
    $respAnul->obtenerCodigoEstado() === 200 &&
    ($jsonAnul['datos']['estado'] ?? '') === 'ANULADA',
    "POST /api/operaciones/bitacora/{id}/anular anula con preservación histórica (ANULAR != DELETE)"
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
    echo " CERTIFICACIÓN PASS: Suite E2E / API HTTP (10 casos) completada con éxito.\n";
    echo "====================================================================\n";
    exit(0);
}
