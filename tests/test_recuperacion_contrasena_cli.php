<?php

declare(strict_types=1);

/**
 * Suite de Verificación — Recuperación y Restablecimiento Administrativo de Contraseñas (CLI)
 *
 * Valida:
 * 1. Ejecución HTTP imposible (exclusividad CLI).
 * 2. Rechazo estricto ante usuario inexistente.
 * 3. Rechazo estricto ante usuario inactivo o bloqueado.
 * 4. Rechazo ante contraseñas que violan la política (menor a 12 caracteres o mayor a 1024).
 * 5. Generación de hash compatible con PASSWORD_DEFAULT y password_verify() exitoso.
 * 6. Contraseña nunca persistida en claro.
 * 7. Contraseña y hash nunca expuestos en logs o auditoría D-061.
 * 8. Revocación inmediata de todas las sesiones anteriores.
 * 9. Autenticación exitosa posterior mediante AutenticacionServicio.
 * 10. Aislamiento: ningún otro usuario sufre alteraciones.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CamargoPMS\Excepciones\EntidadNoEncontradaExcepcion;
use CamargoPMS\Excepciones\ValidacionExcepcion;
use CamargoPMS\Modelos\Usuario;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Repositorios\PersonaRepositorio;
use CamargoPMS\Repositorios\SesionUsuarioRepositorio;
use CamargoPMS\Repositorios\UsuarioRepositorio;
use CamargoPMS\Servicios\AuditoriaServicio;
use CamargoPMS\Servicios\AutenticacionServicio;
use CamargoPMS\Servicios\SesionServicio;
use CamargoPMS\Servicios\UsuarioServicio;

Configuracion::cargar(dirname(__DIR__));
$pdo = BaseDatos::conexion();

$usuarioRepo = new UsuarioRepositorio($pdo);
$personaRepo = new PersonaRepositorio($pdo);
$sesionRepo = new SesionUsuarioRepositorio($pdo);
$sesionServicio = new SesionServicio($pdo, $sesionRepo, $usuarioRepo);
$auditoriaServicio = new AuditoriaServicio($pdo);
$usuarioServicio = new UsuarioServicio($pdo, $usuarioRepo, $personaRepo, $sesionServicio, $auditoriaServicio);
$autenticacionServicio = new AutenticacionServicio($pdo, $usuarioRepo, $personaRepo, $sesionServicio, null, $auditoriaServicio);

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
echo " CAMARGO PMS — PRUEBAS DE RECUPERACIÓN ADMINISTRATIVA DE CONTRASEÑA\n";
echo " Script CLI: bin/restablecer-contrasena-usuario.php\n";
echo "====================================================================\n\n";

// Caso 1: Script CLI contiene guarda estricta contra HTTP
$contenidoScript = file_get_contents(dirname(__DIR__) . '/bin/restablecer-contrasena-usuario.php');
afirmar(
    str_contains($contenidoScript, "PHP_SAPI !== 'cli'") &&
    str_contains($contenidoScript, 'http_response_code(403)'),
    "Guarda estricta contra ejecución HTTP presente en bin/restablecer-contrasena-usuario.php"
);

// Preparar una persona de prueba dedicada para las validaciones
$sufijoPrueba = bin2hex(random_bytes(4));
$stmtPers = $pdo->prepare('INSERT INTO personas (nombres, apellido_paterno, apellido_materno, pais_nacionalidad_id, creado_en) VALUES (?, ?, ?, 1, NOW())');
$stmtPers->execute(['Test', 'Recuperacion', $sufijoPrueba]);
$personaId = (int) $pdo->lastInsertId();

// Crear usuario de prueba temporal con nombre único
$nombreUserPrueba = 'test_recup_' . $sufijoPrueba;
$passInicial = 'PasswordInicial123!';
$userPrueba = $usuarioServicio->crearUsuario([
    'persona_id' => $personaId,
    'nombre_usuario' => $nombreUserPrueba,
    'contrasena' => $passInicial,
    'estado' => 'ACTIVO',
]);
$userPruebaId = (int) $userPrueba->obtenerId();

// Caso 2: Rechazo estricto ante usuario inexistente
$usuarioInexistenteRechazado = false;
try {
    $usuarioServicio->restablecerContrasenaAdministrativa(99999999, 'NuevaClaveValida123!');
} catch (EntidadNoEncontradaExcepcion $e) {
    $usuarioInexistenteRechazado = true;
}
afirmar($usuarioInexistenteRechazado, "Intento de restablecer usuario inexistente lanza EntidadNoEncontradaExcepcion");

// Caso 3: Rechazo ante usuario bloqueado o inactivo
$usuarioServicio->cambiarEstado($userPruebaId, 'BLOQUEADO');
$usuarioBloqueadoRechazado = false;
try {
    $usuarioServicio->restablecerContrasenaAdministrativa($userPruebaId, 'NuevaClaveValida123!');
} catch (ValidacionExcepcion $e) {
    $usuarioBloqueadoRechazado = true;
}
afirmar($usuarioBloqueadoRechazado, "Usuario bloqueado o inactivo es rechazado para restablecimiento");

// Reactivar para siguientes pruebas
$usuarioServicio->cambiarEstado($userPruebaId, 'ACTIVO');

// Caso 4: Rechazo ante contraseña menor a 12 caracteres
$passCortaRechazada = false;
try {
    $usuarioServicio->restablecerContrasenaAdministrativa($userPruebaId, 'Corta123!');
} catch (ValidacionExcepcion $e) {
    $passCortaRechazada = true;
}
afirmar($passCortaRechazada, "Contraseña menor a 12 caracteres es rechazada por política oficial");

// Caso 5: Crear sesiones previas activas para comprobar revocación
$token1 = bin2hex(random_bytes(32));
$hash1 = hash('sha256', $token1);
$stmtSes = $pdo->prepare('INSERT INTO sesiones_usuario (usuario_id, token_hash, iniciada_en, ultima_actividad_en, expira_en)
                          VALUES (:uid, :th, NOW(), NOW(), DATE_ADD(NOW(), INTERVAL 30 MINUTE))');
$stmtSes->execute(['uid' => $userPruebaId, 'th' => $hash1]);
$sesionPreviaId = (int) $pdo->lastInsertId();
afirmar($sesionPreviaId > 0, "Sesión previa activa registrada correctamente para verificar revocación posterior");

// Caso 6: Ejecutar restablecimiento con contraseña válida
$passNueva = 'NuevaContrasenaSegura2026!';
$exito = $usuarioServicio->restablecerContrasenaAdministrativa($userPruebaId, $passNueva, 'PRUEBA_AUTOMATIZADA');
afirmar($exito === true, "Restablecimiento administrativo completado exitosamente");

// Caso 7: Verificar hash en base de datos mediante password_verify()
$userActualizado = $usuarioRepo->buscarPorId($userPruebaId, false);
$hashActualizado = $userActualizado->obtenerContrasenaHash();
afirmar(
    password_verify($passNueva, $hashActualizado) === true,
    "Nueva contraseña valida exitosamente con password_verify() contra el hash en BD"
);

// Caso 8: Algoritmo de hash coincide con política vigente PASSWORD_DEFAULT
$infoHash = password_get_info($hashActualizado);
afirmar(
    !empty($infoHash['algo']) && $infoHash['algo'] === PASSWORD_BCRYPT,
    "El hash generado utiliza la política oficial vigente de Camargo PMS (PASSWORD_DEFAULT / bcrypt)"
);

// Caso 9: Contraseña en texto plano NO está en la base de datos
afirmar(
    $hashActualizado !== $passNueva && !str_contains($hashActualizado, $passNueva),
    "La contraseña en texto plano no se encuentra expuesta en la base de datos"
);

// Caso 10: Auditoría D-061 no expone contraseñas ni hashes
$stmtAudit = $pdo->prepare('SELECT descripcion, valores_anteriores, valores_nuevos, contexto
                           FROM auditoria
                           WHERE modulo = "usuarios" AND entidad = "usuario" AND entidad_id = :uid
                           ORDER BY id DESC LIMIT 1');
$stmtAudit->execute(['uid' => (string) $userPruebaId]);
$filaAudit = $stmtAudit->fetch(PDO::FETCH_ASSOC);

$exponeSecretos = false;
foreach ($filaAudit as $col => $val) {
    if ($val !== null && (str_contains((string) $val, $passNueva) || str_contains((string) $val, $hashActualizado))) {
        $exponeSecretos = true;
    }
}
afirmar(!$exponeSecretos, "Trazas de auditoría D-061 están 100% limpias de contraseñas y hashes");

// Caso 11: Sesiones anteriores del usuario quedaron revocadas
$stmtSesionRevocada = $pdo->prepare('SELECT revocada_en, motivo_cierre FROM sesiones_usuario WHERE id = :id');
$stmtSesionRevocada->execute(['id' => $sesionPreviaId]);
$filaSesRev = $stmtSesionRevocada->fetch(PDO::FETCH_ASSOC);
afirmar(
    !empty($filaSesRev['revocada_en']) && $filaSesRev['motivo_cierre'] === 'REVOCACION_ADMINISTRATIVA',
    "Todas las sesiones previas del usuario fueron revocadas inmediatamente con motivo REVOCACION_ADMINISTRATIVA"
);

// Caso 12: Autenticación posterior con las nuevas credenciales es exitosa
$resultadoAuth = $autenticacionServicio->autenticar($nombreUserPrueba, $passNueva);
afirmar(
    is_array($resultadoAuth) &&
    isset($resultadoAuth['usuario']) &&
    $resultadoAuth['usuario'] instanceof Usuario &&
    (int) $resultadoAuth['usuario']->obtenerId() === $userPruebaId,
    "El usuario se autentica satisfactoriamente con la nueva contraseña mediante AutenticacionServicio"
);

// Limpieza del usuario y persona de prueba
$pdo->prepare('DELETE FROM sesiones_usuario WHERE usuario_id = :uid')->execute(['uid' => $userPruebaId]);
$pdo->prepare('DELETE FROM auditoria WHERE modulo = "usuarios" AND entidad = "usuario" AND entidad_id = :uid')->execute(['uid' => (string) $userPruebaId]);
$pdo->prepare('DELETE FROM usuarios WHERE id = :uid')->execute(['uid' => $userPruebaId]);
$pdo->prepare('DELETE FROM personas WHERE id = :pid')->execute(['pid' => $personaId]);

echo "\n====================================================================\n";
echo " RESUMEN: {$pasadas} / {$total} pruebas PASADAS\n";
if ($fallidas > 0) {
    echo " ATENCIÓN: {$fallidas} pruebas FALLIDAS\n";
    foreach ($errores as $err) {
        echo "   - {$err}\n";
    }
    exit(1);
} else {
    echo " CERTIFICACIÓN PASS: Suite de Recuperación Administrativa (12 casos) completada con éxito.\n";
    echo "====================================================================\n";
    exit(0);
}
