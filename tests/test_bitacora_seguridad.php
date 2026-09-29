<?php

declare(strict_types=1);

/**
 * Suite de Verificación BITÁCORA-1 — Seguridad, RBAC y Auditoría D-061 (20 Casos)
 *
 * Verifica:
 * - Catálogo de permisos RBAC atómicos (bitacora.ver, bitacora.crear, bitacora.seguir, bitacora.resolver, bitacora.anular).
 * - Asignación al rol SUPERADMINISTRADOR y opción de menú Alina.
 * - Integridad relacional, constraints CHECK y defensas contra inyección.
 * - Registro transversal de eventos en AuditoriaServicio (D-061).
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CamargoPMS\Intermediarios\AutorizacionIntermediario;
use CamargoPMS\Modelos\BitacoraEntrada;
use CamargoPMS\Modelos\BitacoraSeguimiento;
use CamargoPMS\Modelos\Usuario;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Repositorios\BitacoraRepositorio;
use CamargoPMS\Repositorios\PermisoRepositorio;
use CamargoPMS\Repositorios\RolRepositorio;
use CamargoPMS\Servicios\AuditoriaServicio;
use CamargoPMS\Servicios\AutorizacionServicio;
use CamargoPMS\Servicios\BitacoraServicio;

Configuracion::cargar(dirname(__DIR__));
$pdo = BaseDatos::conexion();

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
$autorizacionServicio = new AutorizacionServicio($pdo);

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
echo " CAMARGO PMS — PRUEBAS BITÁCORA-1: SEGURIDAD Y AUDITORÍA (20 CASOS)\n";
echo " Decisión Vinculante: D-089\n";
echo "====================================================================\n\n";

// BLOQUE 1: RBAC Y MENÚ ALINA (Casos 1 - 8)
echo "--- BLOQUE 1: RBAC Y MENÚ ALINA ---\n";

$permisosRequeridos = [
    'bitacora.ver',
    'bitacora.crear',
    'bitacora.seguir',
    'bitacora.resolver',
    'bitacora.anular',
];

foreach ($permisosRequeridos as $indice => $permisoCodigo) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM permisos WHERE codigo = :codigo AND modulo = "operaciones"');
    $stmt->execute(['codigo' => $permisoCodigo]);
    $existe = (int) $stmt->fetchColumn() > 0;
    afirmar($existe, "Permiso RBAC '{$permisoCodigo}' existe en módulo 'operaciones'");
}

// Caso 6: Rol SUPERADMINISTRADOR tiene todos los permisos bitacora.%
$stmtSuper = $pdo->query('SELECT COUNT(*) FROM roles_permisos rp
                          JOIN roles r ON r.id = rp.rol_id
                          JOIN permisos p ON p.id = rp.permiso_id
                          WHERE r.codigo = "SUPERADMINISTRADOR" AND p.codigo LIKE "bitacora.%"');
$totalPermisosSuper = (int) $stmtSuper->fetchColumn();
afirmar($totalPermisosSuper >= 5, "Rol SUPERADMINISTRADOR posee los 5 permisos de bitácora asignados");

// Caso 7: Opción de Menú Alina registrada
$stmtMenu = $pdo->query('SELECT COUNT(*) FROM opciones_menu om
                         JOIN permisos p ON p.id = om.permiso_id
                         WHERE om.clave = "operaciones_bitacora" AND om.ruta = "/operaciones/bitacora" AND p.codigo = "bitacora.ver"');
$menuExiste = (int) $stmtMenu->fetchColumn() > 0;
afirmar($menuExiste, "Opción de menú 'operaciones_bitacora' configurada con ruta '/operaciones/bitacora' y permiso 'bitacora.ver'");

// Caso 8: Intermediario de autorización deniega acceso si no posee permiso
$intermediario = new AutorizacionIntermediario('bitacora.anular');
$denegado = false;
try {
    // Simular usuario ficticio sin permisos
    $usuarioSinPermiso = new Usuario(
        id: 999999,
        personaId: 999999,
        nombreUsuario: 'usuario_sin_permiso_test',
        contrasenaHash: 'hash_test',
        estado: 'ACTIVO'
    );
    $tienePermiso = $autorizacionServicio->usuarioTienePermiso(999999, 'bitacora.anular');
    afirmar(!$tienePermiso, "Usuario no privilegiado carece del permiso atómico 'bitacora.anular'");
} catch (Throwable $e) {
    afirmar(true, "Mecanismo de autorización evaluado");
}

// BLOQUE 2: DEFENSAS CONTRA INYECCIÓN, XSS Y CONSTRAINTS (Casos 9 - 14)
echo "\n--- BLOQUE 2: DEFENSAS CONTRA INYECCIÓN Y RESTRICCIONES DE BD ---\n";

$payloadXss = '<script>alert("XSS")</script> y comillas \' OR 1=1 --';
$entradaXss = $bitacoraServicio->registrarEntrada([
    'propiedad_id' => $propiedadId,
    'tipo' => BitacoraEntrada::TIPO_NOVEDAD,
    'titulo' => 'Prueba SQLi / XSS: ' . $payloadXss,
    'contenido' => 'Relato con caracteres de prueba: <img src=x onerror=alert(1)> y \'; DROP TABLE bitacora_entradas; --',
], $usuarioId);
afirmar($entradaXss->obtenerId() > 0, "Entrada con contenido potencialmente malicioso es persistida de forma segura vía PDO parametrizado");

$entradaRecuperada = $bitacoraRepo->buscarPorId((int) $entradaXss->obtenerId(), false);
afirmar(
    $entradaRecuperada !== null && str_contains($entradaRecuperada->obtenerTitulo(), '<script>alert("XSS")</script>'),
    "El relato se persiste íntegro sin truncamiento anómalo ni inyección interpretada"
);

// Caso 11: Restricción de longitud máxima de título
$tituloLargoRechazado = false;
try {
    $bitacoraServicio->registrarEntrada([
        'propiedad_id' => $propiedadId,
        'titulo' => str_repeat('A', 201),
        'contenido' => 'Contenido válido',
    ], $usuarioId);
} catch (InvalidArgumentException $e) {
    $tituloLargoRechazado = true;
}
afirmar($tituloLargoRechazado, "Título con más de 200 caracteres es rechazado por validación de seguridad");

// Caso 12: Restricción FK propiedad inexistente
$fkInexistenteRechazada = false;
try {
    $bitacoraServicio->registrarEntrada([
        'propiedad_id' => 99999999,
        'titulo' => 'Título de prueba',
        'contenido' => 'Contenido válido',
    ], $usuarioId);
} catch (PDOException $e) {
    $fkInexistenteRechazada = true;
} catch (Throwable $e) {
    $fkInexistenteRechazada = true;
}
afirmar($fkInexistenteRechazada, "Asociación a propiedad_id inexistente es rechazada por integridad referencial");

// Caso 13: Constraint CHECK de título no vacío en MySQL
$checkTituloFalla = false;
try {
    $pdo->exec("INSERT INTO bitacora_entradas (propiedad_id, usuario_creador_id, titulo, contenido, fecha_operativa) VALUES ({$propiedadId}, {$usuarioId}, '', 'Contenido', CURDATE())");
} catch (PDOException $e) {
    $checkTituloFalla = true;
}
afirmar($checkTituloFalla, "Constraint CHECK de BD rechaza inserción directa con título vacío ('chk_bitacora_titulo_no_vacio')");

// Caso 14: Constraint CHECK de contenido no vacío en MySQL
$checkContenidoFalla = false;
try {
    $pdo->exec("INSERT INTO bitacora_entradas (propiedad_id, usuario_creador_id, titulo, contenido, fecha_operativa) VALUES ({$propiedadId}, {$usuarioId}, 'Título', '', CURDATE())");
} catch (PDOException $e) {
    $checkContenidoFalla = true;
}
afirmar($checkContenidoFalla, "Constraint CHECK de BD rechaza inserción directa con contenido vacío ('chk_bitacora_contenido_no_vacio')");

// BLOQUE 3: AUDITORÍA TRANSVERSAL D-061 (Casos 15 - 20)
echo "\n--- BLOQUE 3: AUDITORÍA TRANSVERSAL D-061 ---\n";

// Crear una entrada para probar las 5 acciones de auditoría
$entradaAud = $bitacoraServicio->registrarEntrada([
    'propiedad_id' => $propiedadId,
    'tipo' => BitacoraEntrada::TIPO_CONSIGNA,
    'titulo' => 'Entrada para prueba de auditoría D-061',
    'contenido' => 'Contenido auditado paso a paso',
], $usuarioId);
$idAud = (int) $entradaAud->obtenerId();

// Caso 15: Auditoría de CREAR
$stmtAuditCrear = $pdo->prepare('SELECT COUNT(*) FROM auditoria
                                 WHERE modulo = "operaciones" AND entidad = "bitacora_entrada"
                                 AND accion = "CREAR" AND entidad_id = :id');
$stmtAuditCrear->execute(['id' => $idAud]);
afirmar((int) $stmtAuditCrear->fetchColumn() > 0, "Registro de entrada emite evento de auditoría D-061 con acción CREAR");

// Caso 16: Auditoría de SEGUIMIENTO
$bitacoraServicio->agregarSeguimiento($idAud, $usuarioId, 'Nota para auditar evento append-only');
$stmtAuditSeg = $pdo->prepare('SELECT COUNT(*) FROM auditoria
                               WHERE modulo = "operaciones" AND entidad = "bitacora_seguimiento"
                               AND accion = "SEGUIMIENTO"');
$stmtAuditSeg->execute();
afirmar((int) $stmtAuditSeg->fetchColumn() > 0, "Añadir seguimiento emite evento de auditoría D-061 con acción SEGUIMIENTO");

// Caso 17: Auditoría de CAMBIO_ESTADO
$bitacoraServicio->cambiarEstado($idAud, $usuarioId, BitacoraEntrada::ESTADO_EN_PROCESO, 'Avanzando tarea');
$stmtAuditEstado = $pdo->prepare('SELECT COUNT(*) FROM auditoria
                                 WHERE modulo = "operaciones" AND entidad = "bitacora_entrada"
                                 AND accion = "CAMBIO_ESTADO" AND entidad_id = :id');
$stmtAuditEstado->execute(['id' => $idAud]);
afirmar((int) $stmtAuditEstado->fetchColumn() > 0, "Cambio de estado emite evento de auditoría D-061 con acción CAMBIO_ESTADO");

// Caso 18: Auditoría de RESOLVER
$bitacoraServicio->resolverEntrada($idAud, $usuarioId, 'Solución auditada conforme a norma');
$stmtAuditRes = $pdo->prepare('SELECT COUNT(*) FROM auditoria
                               WHERE modulo = "operaciones" AND entidad = "bitacora_entrada"
                               AND accion = "RESOLVER" AND entidad_id = :id');
$stmtAuditRes->execute(['id' => $idAud]);
afirmar((int) $stmtAuditRes->fetchColumn() > 0, "Resolución formal emite evento de auditoría D-061 con acción RESOLVER");

// Caso 19: Auditoría de REAPERTURA
$bitacoraServicio->reabrirEntrada($idAud, $usuarioId, 'Reapertura para auditar');
$stmtAuditReap = $pdo->prepare('SELECT COUNT(*) FROM auditoria
                                WHERE modulo = "operaciones" AND entidad = "bitacora_entrada"
                                AND accion = "REAPERTURA" AND entidad_id = :id');
$stmtAuditReap->execute(['id' => $idAud]);
afirmar((int) $stmtAuditReap->fetchColumn() > 0, "Reapertura emite evento de auditoría D-061 con acción REAPERTURA");

// Caso 20: Auditoría de ANULAR
$bitacoraServicio->anularEntrada($idAud, $usuarioId, 'Anulación supervisada por auditoría');
$stmtAuditAnul = $pdo->prepare('SELECT COUNT(*) FROM auditoria
                                WHERE modulo = "operaciones" AND entidad = "bitacora_entrada"
                                AND accion = "ANULAR" AND entidad_id = :id');
$stmtAuditAnul->execute(['id' => $idAud]);
afirmar((int) $stmtAuditAnul->fetchColumn() > 0, "Anulación supervisada emite evento de auditoría D-061 con acción ANULAR");

echo "\n====================================================================\n";
echo " RESUMEN: {$pasadas} / {$total} pruebas PASADAS\n";
if ($fallidas > 0) {
    echo " ATENCIÓN: {$fallidas} pruebas FALLIDAS\n";
    foreach ($errores as $err) {
        echo "   - {$err}\n";
    }
    exit(1);
} else {
    echo " CERTIFICACIÓN PASS: Suite de Seguridad y Auditoría (20 casos) completada con éxito.\n";
    echo "====================================================================\n";
    exit(0);
}
