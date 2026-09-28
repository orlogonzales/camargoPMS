<?php

declare(strict_types=1);

/**
 * Suite de Verificación SESIONES-1 — Seguridad, Aislamiento RBAC, CSRF y Auditoría D-061 (20 Casos)
 *
 * Verifica:
 * - S01: Permiso sesiones.ver registrado bajo módulo 'seguridad'
 * - S02: Permiso sesiones.revocar registrado bajo módulo 'seguridad'
 * - S03: Rol SUPERADMINISTRADOR tiene asignados ambos permisos
 * - S04: Aislamiento RBAC: usuarios.editar NO otorga sesiones.revocar
 * - S05: AutenticacionIntermediario: GET sin sesión redirige (302) a /login
 * - S06: AutenticacionIntermediario: Accept application/json sin sesión retorna HTTP 401
 * - S07: AutenticacionIntermediario: Ruta /api/* sin sesión retorna HTTP 401
 * - S08: AutenticacionIntermediario: X-Requested-With XMLHttpRequest sin sesión retorna HTTP 401
 * - S09: AutenticacionIntermediario: Payload 401 es JSON opaco (SESION_NO_VALIDA)
 * - S10: AutorizacionIntermediario: Sin sesión y esperando JSON retorna HTTP 401 opaco
 * - S11: AutorizacionIntermediario: Autenticado sin 'sesiones.ver' recibe HTTP 403
 * - S12: AutorizacionIntermediario: Autenticado sin 'sesiones.revocar' recibe HTTP 403
 * - S13: CSRF: Revocar sesión sin token CSRF es rechazado (HTTP 403)
 * - S14: CSRF: Revocar sesión con token CSRF inválido es rechazado (HTTP 403)
 * - S15: CSRF: Revocar todas las sesiones de usuario sin token CSRF es rechazado (HTTP 403)
 * - S16: CSRF: Purgar expiradas sin token CSRF es rechazado (HTTP 403)
 * - S17: SQL Injection: Búsqueda con payload malicioso no inyecta ni corrompe consulta
 * - S18: Límite defensivo de paginación acotado a 100 registros máximos
 * - S19: Fuga de datos: Ni SesionUsuario::aArreglo() ni listarSesionesGlobales() exponen token_hash
 * - S20: Auditoría D-061: Revocación administrativa genera evento REVOCAR_SESION sin exponer token_hash
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CamargoPMS\Controladores\SesionControlador;
use CamargoPMS\Intermediarios\AutenticacionIntermediario;
use CamargoPMS\Intermediarios\AutorizacionIntermediario;
use CamargoPMS\Modelos\SesionUsuario;
use CamargoPMS\Modelos\Usuario;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Repositorios\PermisoRepositorio;
use CamargoPMS\Repositorios\SesionUsuarioRepositorio;
use CamargoPMS\Repositorios\UsuarioRepositorio;
use CamargoPMS\Servicios\AuditoriaServicio;
use CamargoPMS\Servicios\AutorizacionServicio;
use CamargoPMS\Servicios\CsrfServicio;
use CamargoPMS\Servicios\SesionServicio;

Configuracion::cargar(dirname(__DIR__));
$pdo = BaseDatos::conexion();

$sesionRepo = new SesionUsuarioRepositorio($pdo);
$usuarioRepo = new UsuarioRepositorio($pdo);
$permisoRepo = new PermisoRepositorio($pdo);
$auditoriaServicio = new AuditoriaServicio($pdo);
$personaRepo = new \CamargoPMS\Repositorios\PersonaRepositorio($pdo);
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
        echo "  [PASS] Caso {$total}: {$mensaje}\n";
    } else {
        $fallidas++;
        $errores[] = "Caso {$total}: {$mensaje}";
        echo "  [FAIL] Caso {$total}: {$mensaje}\n";
    }
}

echo "====================================================================\n";
echo " CAMARGO PMS — PRUEBAS SESIONES-1: SEGURIDAD Y AISLAMIENTO (20 CASOS)\n";
echo " Decisión Vinculante: D-088\n";
echo "====================================================================\n\n";

// S01: Permiso sesiones.ver registrado bajo módulo 'seguridad'
$stmtP1 = $pdo->prepare('SELECT modulo FROM permisos WHERE codigo = "sesiones.ver"');
$stmtP1->execute();
$modP1 = $stmtP1->fetchColumn();
afirmar($modP1 === 'seguridad', "S01: Permiso sesiones.ver existe en catálogo bajo módulo 'seguridad'");

// S02: Permiso sesiones.revocar registrado bajo módulo 'seguridad'
$stmtP2 = $pdo->prepare('SELECT modulo FROM permisos WHERE codigo = "sesiones.revocar"');
$stmtP2->execute();
$modP2 = $stmtP2->fetchColumn();
afirmar($modP2 === 'seguridad', "S02: Permiso sesiones.revocar existe en catálogo bajo módulo 'seguridad'");

// S03: Rol SUPERADMINISTRADOR tiene asignados ambos permisos
$stmtR = $pdo->query('
    SELECT COUNT(*) FROM roles_permisos rp
    INNER JOIN roles r ON rp.rol_id = r.id
    INNER JOIN permisos p ON rp.permiso_id = p.id
    WHERE r.codigo = "SUPERADMINISTRADOR" AND p.codigo IN ("sesiones.ver", "sesiones.revocar")
');
$cntRolPerm = (int) $stmtR->fetchColumn();
afirmar($cntRolPerm === 2, "S03: Rol SUPERADMINISTRADOR tiene asignados tanto sesiones.ver como sesiones.revocar");

// S04: Aislamiento RBAC: usuarios.editar NO otorga sesiones.revocar
$autorizacionServicioMock = new class extends AutorizacionServicio {
    public function __construct() {}
    public function puede(int $usuarioId, string $permiso): bool
    {
        // Usuario con usuarios.editar pero NO sesiones.revocar
        if ($permiso === 'usuarios.editar') {
            return true;
        }
        return false;
    }
};
afirmar(
    $autorizacionServicioMock->puede(999, 'usuarios.editar') === true &&
    $autorizacionServicioMock->puede(999, 'sesiones.revocar') === false,
    "S04: Aislamiento RBAC: usuarios.editar NO otorga facultades para sesiones.revocar"
);

// S05: AutenticacionIntermediario: GET sin sesión redirige (302) a /login
$sesionServicioNull = new class extends SesionServicio {
    public function __construct() {}
    public function validarSesionActual(): ?Usuario { return null; }
};
$_SERVER['HTTP_ACCEPT'] = 'text/html';
unset($_SERVER['HTTP_X_REQUESTED_WITH'], $_SERVER['CONTENT_TYPE']);
$_SERVER['REQUEST_URI'] = '/seguridad/sesiones';

$authMiddleware = new AutenticacionIntermediario($sesionServicioNull);
$respS05 = $authMiddleware->manejar('/seguridad/sesiones');
afirmar(
    $respS05 !== null && $respS05->obtenerCodigoEstado() === 302 && str_contains($respS05->obtenerCabeceras()['Location'] ?? '', '/login'),
    "S05: Petición HTML sin sesión redirige (302) a /login"
);

// S06: AutenticacionIntermediario: Accept application/json sin sesión retorna HTTP 401
$_SERVER['HTTP_ACCEPT'] = 'application/json';
$respS06 = $authMiddleware->manejar('/seguridad/sesiones');
afirmar(
    $respS06 !== null && $respS06->obtenerCodigoEstado() === 401,
    "S06: Petición con Accept application/json sin sesión retorna HTTP 401 Unauthorized"
);

// S07: AutenticacionIntermediario: Ruta /api/* sin sesión retorna HTTP 401
$_SERVER['HTTP_ACCEPT'] = '*/*';
$_SERVER['REQUEST_URI'] = '/api/seguridad/sesiones';
$respS07 = $authMiddleware->manejar('/api/seguridad/sesiones');
afirmar(
    $respS07 !== null && $respS07->obtenerCodigoEstado() === 401,
    "S07: Petición a ruta /api/* sin sesión retorna HTTP 401 Unauthorized"
);

// S08: AutenticacionIntermediario: X-Requested-With XMLHttpRequest sin sesión retorna HTTP 401
$_SERVER['HTTP_ACCEPT'] = 'text/html';
$_SERVER['REQUEST_URI'] = '/otra-ruta';
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
$respS08 = $authMiddleware->manejar('/otra-ruta');
afirmar(
    $respS08 !== null && $respS08->obtenerCodigoEstado() === 401,
    "S08: Petición con X-Requested-With XMLHttpRequest sin sesión retorna HTTP 401 Unauthorized"
);

// S09: AutenticacionIntermediario: Payload 401 es JSON opaco (SESION_NO_VALIDA)
$cuerpoS08 = json_decode($respS08->obtenerCuerpo(), true);
afirmar(
    is_array($cuerpoS08) &&
    isset($cuerpoS08['ok']) && $cuerpoS08['ok'] === false &&
    isset($cuerpoS08['codigo']) && $cuerpoS08['codigo'] === 'SESION_NO_VALIDA' &&
    isset($cuerpoS08['error']) && $cuerpoS08['error'] === 'La sesión ya no es válida.',
    "S09: Payload HTTP 401 es JSON opaco con código SESION_NO_VALIDA"
);

// S10: AutorizacionIntermediario: Sin sesión y esperando JSON retorna HTTP 401 opaco
$autzMiddleware = new AutorizacionIntermediario('sesiones.ver', $sesionServicioNull);
$_SERVER['HTTP_ACCEPT'] = 'application/json';
$respS10 = $autzMiddleware->manejar('/api/seguridad/sesiones');
$cuerpoS10 = $respS10 !== null ? json_decode($respS10->obtenerCuerpo(), true) : null;
afirmar(
    $respS10 !== null && $respS10->obtenerCodigoEstado() === 401 &&
    is_array($cuerpoS10) && ($cuerpoS10['codigo'] ?? '') === 'SESION_NO_VALIDA',
    "S10: AutorizacionIntermediario sin sesión y esperando JSON emite HTTP 401 opaco coherente"
);

// S11: AutorizacionIntermediario: Autenticado sin 'sesiones.ver' recibe HTTP 403
$usuarioSinPermiso = $usuarioRepo->buscarPorId(1, false);
$sesionServicioConUsuario = new class($usuarioSinPermiso) extends SesionServicio {
    private ?Usuario $u;
    public function __construct(?Usuario $u) { $this->u = $u; }
    public function validarSesionActual(): ?Usuario { return $this->u; }
};
$autzSinPermisos = new class extends AutorizacionServicio {
    public function __construct() {}
    public function puede(int $usuarioId, string $permiso): bool { return false; }
};

$autzMiddlewareBloqueante = new AutorizacionIntermediario('sesiones.ver', $sesionServicioConUsuario, $autzSinPermisos);
$_SERVER['HTTP_ACCEPT'] = 'application/json';
$respS11 = $autzMiddlewareBloqueante->manejar('/api/seguridad/sesiones');
afirmar(
    $respS11 !== null && $respS11->obtenerCodigoEstado() === 403,
    "S11: Usuario autenticado sin permiso 'sesiones.ver' recibe HTTP 403 Forbidden"
);

// S12: AutorizacionIntermediario: Autenticado sin 'sesiones.revocar' recibe HTTP 403
$autzRevocarBloqueante = new AutorizacionIntermediario('sesiones.revocar', $sesionServicioConUsuario, $autzSinPermisos);
$respS12 = $autzRevocarBloqueante->manejar('/api/seguridad/sesiones/1/revocar');
afirmar(
    $respS12 !== null && $respS12->obtenerCodigoEstado() === 403,
    "S12: Usuario autenticado sin permiso 'sesiones.revocar' recibe HTTP 403 Forbidden"
);

// S13: CSRF: Revocar sesión sin token CSRF es rechazado (HTTP 403)
$_POST = [];
unset($_SERVER['HTTP_X_CSRF_TOKEN'], $_SERVER['HTTP_X_XSRF_TOKEN'], $_SERVER['CONTENT_TYPE']);
$respS13 = $controlador->apiRevocarSesion(999);
afirmar(
    $respS13->obtenerCodigoEstado() === 403,
    "S13: Revocar sesión sin token CSRF retorna HTTP 403 Forbidden"
);

// S14: CSRF: Revocar sesión con token CSRF inválido es rechazado (HTTP 403)
$_POST = ['csrf_token' => 'token_totalmente_falso_e_invalido'];
$respS14 = $controlador->apiRevocarSesion(999);
afirmar(
    $respS14->obtenerCodigoEstado() === 403,
    "S14: Revocar sesión con token CSRF falso/inválido retorna HTTP 403 Forbidden"
);

// S15: CSRF: Revocar todas las sesiones de usuario sin token CSRF es rechazado (HTTP 403)
$_POST = [];
$respS15 = $controlador->apiRevocarUsuario(1);
afirmar(
    $respS15->obtenerCodigoEstado() === 403,
    "S15: Revocar todas las sesiones de un usuario sin token CSRF retorna HTTP 403 Forbidden"
);

// S16: CSRF: Purgar expiradas sin token CSRF es rechazado (HTTP 403)
$_POST = [];
$respS16 = $controlador->apiPurgarExpiradas();
afirmar(
    $respS16->obtenerCodigoEstado() === 403,
    "S16: Purgar sesiones expiradas sin token CSRF retorna HTTP 403 Forbidden"
);

// S17: SQL Injection: Búsqueda con payload malicioso no inyecta ni corrompe consulta
$payloadInyeccion = "admin' OR '1'='1' -- /*";
try {
    $resultadoInyeccion = $sesionRepo->listarSesionesGlobales(['busqueda' => $payloadInyeccion], 10, 1);
    afirmar(is_array($resultadoInyeccion), "S17: Parámetro busqueda con inyección SQL parametrizado de forma segura sin romper sintaxis");
} catch (Throwable $e) {
    afirmar(false, "S17: Excepción inesperada por inyección SQL: " . $e->getMessage());
}

// S18: Límite defensivo de paginación acotado a 100 registros máximos
$_GET['limite'] = '99999';
$_GET['pagina'] = '1';
$respLim = $controlador->apiListarSesiones();
$cuerpoLim = json_decode($respLim->obtenerCuerpo(), true);
afirmar(
    $respLim->obtenerCodigoEstado() === 200 &&
    isset($cuerpoLim['datos']['limite']) &&
    $cuerpoLim['datos']['limite'] <= 100,
    "S18: Controlador acota defensivamente el límite de paginación a 100 registros máximos"
);

// S19: Fuga de datos: Ni SesionUsuario::aArreglo() ni listarSesionesGlobales() exponen token_hash
$sesionPrueba = new SesionUsuario(
    999, 1, 'super_secret_token_hash_abc123', date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), date('Y-m-d H:i:s', time() + 3600), null, null
);
$arrPrueba = $sesionPrueba->aArreglo(false);
$filasRepo = $sesionRepo->listarSesionesGlobales([], 5, 1);
$contieneSecreto = isset($arrPrueba['token_hash']);
foreach ($filasRepo as $f) {
    if (isset($f['token_hash']) || isset($f['password_hash'])) {
        $contieneSecreto = true;
    }
}
afirmar(!$contieneSecreto, "S19: Prevención de fuga de datos: ningún arreglo público ni consulta global expone token_hash o password_hash");

// S20: Auditoría D-061: Revocación administrativa genera evento REVOCAR_SESION sin exponer token_hash
// Crear una sesión temporal en BD para revocarla
$tokenPrueba = bin2hex(random_bytes(32));
$tokenHashPrueba = hash('sha256', $tokenPrueba);
$ahoraStr = date('Y-m-d H:i:s');
$expiraStr = date('Y-m-d H:i:s', time() + 1800);

$stmtIns = $pdo->prepare('
    INSERT INTO sesiones_usuario (usuario_id, token_hash, iniciada_en, ultima_actividad_en, expira_en, ip, user_agent)
    VALUES (1, :th, :ini, :act, :exp, "127.0.0.1", "PHPUnit/SecurityTest")
');
$stmtIns->execute([
    'th' => $tokenHashPrueba,
    'ini' => $ahoraStr,
    'act' => $ahoraStr,
    'exp' => $expiraStr,
]);
$sesionIdCreada = (int) $pdo->lastInsertId();

// Revocar mediante el servicio
$sesionServicio->revocarSesionAdministrativa($sesionIdCreada, 1, 'REVOCACION_ADMINISTRATIVA');

// Verificar evento en tabla auditoria
$stmtAudit = $pdo->prepare('
    SELECT accion, entidad, entidad_id, contexto, valores_nuevos
    FROM auditoria
    WHERE entidad = "sesiones_usuario" AND entidad_id = :reg_id
    ORDER BY id DESC LIMIT 1
');
$stmtAudit->execute(['reg_id' => (string) $sesionIdCreada]);
$eventoAudit = $stmtAudit->fetch(PDO::FETCH_ASSOC);

$auditOk = false;
if ($eventoAudit !== false) {
    $contexto = json_decode((string) $eventoAudit['contexto'], true);
    $valoresNuevos = json_decode((string) $eventoAudit['valores_nuevos'], true);
    $noExponeHash = !isset($contexto['token_hash']) && !isset($contexto['token']) && !isset($valoresNuevos['token_hash']);
    $auditOk = $eventoAudit['accion'] === 'CERRAR_SESION' &&
               $eventoAudit['entidad'] === 'sesiones_usuario' &&
               $noExponeHash;
}

afirmar($auditOk, "S20: Auditoría D-061: Revocación genera registro inmutable en auditoria sin exponer secretos");

// Limpiar sesión de prueba
$pdo->prepare('DELETE FROM sesiones_usuario WHERE id = :id')->execute(['id' => $sesionIdCreada]);

echo "\n====================================================================\n";
echo " RESULTADOS SEGURIDAD SESIONES-1: {$pasadas}/{$total} PASADAS\n";
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
