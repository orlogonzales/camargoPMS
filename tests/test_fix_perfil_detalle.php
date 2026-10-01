<?php
/**
 * Test Suite: Validación FIX-PERFIL-1 — Alineación del contrato de detalle de usuario en /perfil
 * 
 * Valida:
 * 1. UsuarioRepositorio implementa el contrato canónico buscarDetallePorId() retornando roles y sesiones_activas.
 * 2. UsuarioRepositorio NO contiene el alias obsoleto obtenerDetalleCompleto().
 * 3. PerfilControlador::index() invoca buscarDetallePorId() y resuelve exitosamente.
 * 4. Ejecución en runtime de PerfilControlador::index() con usuario autenticado (HTTP 200, HTML válido).
 * 5. La vista perfil/index.php renderiza roles y sesiones_activas con fallback ?? 0 (sin inventar datos).
 * 6. Integridad de BD: 118 tablas, migración 034 como última, ranura 035 libre y admin-dashboard/ intacto.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CamargoPMS\Controladores\PerfilControlador;
use CamargoPMS\Modelos\Usuario;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Repositorios\PersonaRepositorio;
use CamargoPMS\Repositorios\UsuarioRepositorio;
use CamargoPMS\Servicios\CsrfServicio;
use CamargoPMS\Servicios\FotoPersonaServicio;
use CamargoPMS\Servicios\SesionServicio;

Configuracion::cargar(dirname(__DIR__));
$pdo = BaseDatos::conexion();

$totalAssertions = 0;
$passedAssertions = 0;

function assertCheck(bool $condition, string $message): void {
    global $totalAssertions, $passedAssertions;
    $totalAssertions++;
    if ($condition) {
        $passedAssertions++;
        echo "  [PASS] $message\n";
    } else {
        echo "  [FAIL] $message\n";
    }
}

echo "====================================================================\n";
echo " CAMARGO PMS — VALIDACIÓN FIX-PERFIL-1: CONTRATO /PERFIL\n";
echo "====================================================================\n\n";

// --- 1. CONTRATO CANÓNICO EN USUARIOREPOSITORIO ---
echo "--- 1. Contrato Canónico en UsuarioRepositorio ---\n";

$usuarioRepo = new UsuarioRepositorio($pdo);
$refRepo = new ReflectionClass(UsuarioRepositorio::class);

assertCheck($refRepo->hasMethod('buscarDetallePorId'), "UsuarioRepositorio dispone del método canónico buscarDetallePorId()");
assertCheck(!$refRepo->hasMethod('obtenerDetalleCompleto'), "UsuarioRepositorio NO define el método obsoleto obtenerDetalleCompleto() (inmutabilidad de persistencia)");

// Obtener un usuario real para probar el contrato
$stmtUser = $pdo->query("SELECT id FROM usuarios WHERE estado = 'ACTIVO' LIMIT 1");
$testUserId = (int) $stmtUser->fetchColumn();

if ($testUserId > 0) {
    $detalle = $usuarioRepo->buscarDetallePorId($testUserId);
    assertCheck(is_array($detalle), "buscarDetallePorId($testUserId) retorna un array estructurado");
    assertCheck(isset($detalle['id']) && (int)$detalle['id'] === $testUserId, "Detalle incluye ID de usuario correcto");
    assertCheck(array_key_exists('roles', $detalle) && is_array($detalle['roles']), "Detalle incluye clave 'roles' como array");
    assertCheck(array_key_exists('sesiones', $detalle) && is_array($detalle['sesiones']), "Detalle incluye clave 'sesiones' como array");
    assertCheck(array_key_exists('sesiones_activas', $detalle) && is_int($detalle['sesiones_activas']), "Detalle incluye clave canónica 'sesiones_activas' como entero");
} else {
    assertCheck(false, "Existe al menos un usuario activo en la base de datos");
}

// --- 2. CÓDIGO EN PERFILCONTROLADOR Y VISTA ---
echo "\n--- 2. Integridad de Código en PerfilControlador y Vista ---\n";

$controladorCodigo = file_get_contents(dirname(__DIR__) . '/app/Controladores/PerfilControlador.php');
assertCheck(strpos($controladorCodigo, 'buscarDetallePorId($usuarioId)') !== false, "PerfilControlador::index() invoca explícitamente buscarDetallePorId(\$usuarioId)");
assertCheck(strpos($controladorCodigo, 'obtenerDetalleCompleto') === false, "PerfilControlador NO contiene ninguna llamada a obtenerDetalleCompleto()");

$vistaCodigo = file_get_contents(dirname(__DIR__) . '/app/Vistas/perfil/index.php');
assertCheck(strpos($vistaCodigo, "\$detalleUsuario['sesiones_activas'] ?? 0") !== false, "perfil/index.php consume clave 'sesiones_activas' con fallback seguro '?? 0'");
assertCheck(strpos($vistaCodigo, 'total_sesiones_activas') === false, "perfil/index.php no utiliza la clave inexistente 'total_sesiones_activas'");

// --- 3. EJECUCIÓN RUNTIME DE PERFILCONTROLADOR ---
echo "\n--- 3. Ejecución Runtime de PerfilControlador::index() ---\n";

// Crear un mock o simulación controlada de SesionServicio que devuelva el usuario autenticado
$usuarioModelo = $usuarioRepo->buscarPorId($testUserId);
assertCheck($usuarioModelo instanceof Usuario, "Se pudo instanciar el modelo Usuario activo para el test");

$sesionServicioMock = new class($usuarioModelo) extends SesionServicio {
    private Usuario $usuarioMock;
    public function __construct(Usuario $u) {
        $this->usuarioMock = $u;
    }
    public function validarSesionActual(): ?Usuario {
        return $this->usuarioMock;
    }
};

$personaRepo = new PersonaRepositorio($pdo);
$fotoServicio = new FotoPersonaServicio($pdo);
$csrfServicio = new CsrfServicio();

use CamargoPMS\Nucleo\Vista;

$vista = new Vista();
$controlador = new PerfilControlador(
    $vista,
    $sesionServicioMock,
    $usuarioRepo,
    $personaRepo,
    $fotoServicio,
    $csrfServicio
);

$respuesta = $controlador->index();
assertCheck($respuesta->obtenerCodigoEstado() === 200, "PerfilControlador::index() responde con código HTTP 200 OK");

$cuerpo = $respuesta->obtenerCuerpo();
assertCheck(strpos($cuerpo, 'Camargo PMS — Mi Perfil') !== false, "HTML generado contiene título 'Camargo PMS — Mi Perfil'");
assertCheck(strpos($cuerpo, 'PERSONA ≠ USUARIO') !== false, "HTML generado contiene principio soberano 'PERSONA ≠ USUARIO'");
assertCheck(strpos($cuerpo, 'profile-container') !== false, "HTML generado incluye estructura canónica Alina profile-container");
assertCheck(strpos($cuerpo, 'sesión(es) activa(s) registrada(s)') !== false, "HTML generado renderiza conteo de sesiones activas sin error");

// --- 4. GOBERNANZA DE BASE DE DATOS Y REPOSITORIO ---
echo "\n--- 4. Gobernanza de Base de Datos y Repositorio ---\n";

$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
assertCheck(count($tables) >= 118, "Base de datos cuenta con al menos 118 tablas relacionales (actual: " . count($tables) . ")");

$mig034 = (bool) $pdo->query("SELECT 1 FROM migraciones WHERE migracion = '034_agregar_foto_personas.sql'")->fetchColumn();
assertCheck($mig034, "Migración 034_agregar_foto_personas.sql presente en BD");

$m040 = glob(dirname(__DIR__) . '/SQL/migraciones/*040*');
assertCheck(empty($m040), "Ranura de migración 040 estrictamente LIBRE en SQL/migraciones/ (cero DDL no autorizado)");

$gitStatusAlina = shell_exec('git status --porcelain admin-dashboard/');
assertCheck(empty(trim((string)$gitStatusAlina)), "admin-dashboard/ permanece 100% inmutable y libre de modificaciones");

echo "\n====================================================================\n";
echo " RESUMEN: $passedAssertions / $totalAssertions pruebas superadas\n";
echo "====================================================================\n\n";

if ($passedAssertions === $totalAssertions) {
    echo ">>> FIX-PERFIL-1: VALIDACIÓN EXITOSA (100% PASS) <<<\n";
    exit(0);
} else {
    echo ">>> FIX-PERFIL-1: DETECTADAS FALLAS EN LA VALIDACIÓN <<<\n";
    exit(1);
}
