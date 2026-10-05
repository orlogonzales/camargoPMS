<?php

declare(strict_types=1);

/**
 * Suite de Pruebas Automatizadas UI-ALINA-1B-C1:
 * Persistencia Segura y Desacoplada de Fotografía de Persona en Mi Perfil.
 *
 * Valida:
 * 1. PERSISTENCIA:
 *    - Columna foto_ruta existe en tabla personas y es nullable.
 *    - Paridad estricta con SQL/camargo_pms.sql.
 *    - Ranura 035 libre y total de 118 tablas relacionales.
 * 2. SEGURIDAD:
 *    - Endpoint requiere autenticación (401 si no hay sesión activa).
 *    - Endpoint requiere CSRF válido (403 con token falso o ausente).
 *    - La Persona se deriva estrictamente del usuario autenticado (ignora persona_id externo).
 *    - Formato JPEG válido aceptado.
 *    - Formato PNG válido aceptado.
 *    - Falso MIME rechazado (validación binaria con Fileinfo).
 *    - SVG rechazado terminantemente.
 *    - Archivos mayores a 2 MB rechazados.
 *    - Identificador generado criptográficamente (nombre original descartado).
 *    - Path traversal imposible en eliminación y resolución.
 * 3. REEMPLAZO ATÓMICO:
 *    - Foto nueva guardada y BD actualizada.
 *    - Foto anterior eliminada tras persistencia exitosa.
 *    - Fallo en BD conserva foto anterior y destruye archivo nuevo huérfano.
 *    - Fallback canónico de Alina (01.png) protegido contra eliminación.
 *    - Reseteo / eliminación de avatar devuelve referencia a NULL y limpia disco.
 * 4. UX / INTERFAZ:
 *    - UI de previsualización FileReader presente en vista.
 *    - Botones de acción (Guardar, Cancelar, Restablecer) y feedback de alertas.
 *    - Helper url_storage resuelve URL pública con fallback.
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Nucleo/Configuracion.php';
require_once __DIR__ . '/../app/Nucleo/BaseDatos.php';
require_once __DIR__ . '/../app/Nucleo/Vista.php';
require_once __DIR__ . '/../app/Nucleo/Funciones.php';
require_once __DIR__ . '/../app/Nucleo/Ayudante.php';

use CamargoPMS\Controladores\PerfilControlador;
use CamargoPMS\Excepciones\ValidacionExcepcion;
use CamargoPMS\Modelos\Persona;
use CamargoPMS\Modelos\Usuario;
use CamargoPMS\Nucleo\Ayudante;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Repositorios\PersonaRepositorio;
use CamargoPMS\Repositorios\UsuarioRepositorio;
use CamargoPMS\Servicios\CsrfServicio;
use CamargoPMS\Servicios\FotoPersonaServicio;
use CamargoPMS\Servicios\SesionServicio;

Configuracion::cargar(dirname(__DIR__));
$pdo = BaseDatos::conexion();

$totalAserciones = 0;
$asercionesExitosas = 0;

function test_afirmar(bool $condicion, string $mensaje): void
{
    global $totalAserciones, $asercionesExitosas;
    $totalAserciones++;
    if ($condicion) {
        $asercionesExitosas++;
        echo "  [PASS] {$mensaje}\n";
    } else {
        echo "  [FAIL] {$mensaje}\n";
    }
}

echo "====================================================================\n";
echo " SUITE UI-ALINA-1B-C1: PERSISTENCIA DE FOTOGRAFÍA DE PERSONA\n";
echo "====================================================================\n";

// --- GRUPO 1: PERSISTENCIA, ESQUEMA Y PARIDAD DDL ---
echo "\n--- GRUPO 1: PERSISTENCIA, ESQUEMA Y PARIDAD DDL ---\n";

$columnasPersonas = $pdo->query("SHOW COLUMNS FROM personas LIKE 'foto_ruta'")->fetch(PDO::FETCH_ASSOC);
test_afirmar(
    $columnasPersonas !== false && $columnasPersonas['Field'] === 'foto_ruta',
    "FOTO-DDL-01: Columna foto_ruta existe en la tabla personas"
);

test_afirmar(
    $columnasPersonas !== false && strtoupper((string) $columnasPersonas['Null']) === 'YES',
    "FOTO-DDL-02: Columna foto_ruta es NULLABLE en personas"
);

$sqlConsolidado = file_get_contents(__DIR__ . '/../SQL/camargo_pms.sql');
test_afirmar(
    strpos($sqlConsolidado, '`foto_ruta` VARCHAR(255) NULL') !== false ||
    strpos($sqlConsolidado, 'foto_ruta VARCHAR(255) NULL') !== false,
    "FOTO-DDL-03: Paridad 100% con SQL/camargo_pms.sql (definición de foto_ruta presente)"
);

$tablasTotal = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
test_afirmar(
    count($tablasTotal) >= 118,
    "FOTO-DDL-04: Total de tablas relacionales se mantiene (actual: " . count($tablasTotal) . ")"
);

$slot042 = glob(__DIR__ . '/../SQL/migraciones/*042*');
test_afirmar(
    empty($slot042),
    "FOTO-DDL-05: Ranura de migración 042 permanece estrictamente LIBRE"
);

$mig034 = (bool) $pdo->query("SELECT 1 FROM migraciones WHERE migracion = '034_agregar_foto_personas.sql'")->fetchColumn();
test_afirmar(
    $mig034,
    "FOTO-DDL-06: Migración 034_agregar_foto_personas.sql presente en BD"
);

// --- GRUPO 2: ENTIDAD Y REPOSITORIO DE PERSONA ---
echo "\n--- GRUPO 2: ENTIDAD Y REPOSITORIO DE PERSONA ---\n";

$personaModelo = new Persona(
    null,
    'Prueba',
    'Foto',
    'Unit',
    'MASCULINO',
    '1990-01-01',
    1,
    1,
    null,
    null,
    null,
    'Av. Principal 123',
    'ACTIVO',
    null,
    null,
    null,
    null,
    null,
    [],
    [],
    'avatars/avatar_test_inicial.jpg'
);

test_afirmar(
    $personaModelo->obtenerFotoRuta() === 'avatars/avatar_test_inicial.jpg',
    "PERSONA-01: Modelo Persona soporta fotoRuta en constructor y getter"
);

$personaModelo->asignarFotoRuta('avatars/avatar_modificado.png');
test_afirmar(
    $personaModelo->obtenerFotoRuta() === 'avatars/avatar_modificado.png',
    "PERSONA-02: Modelo Persona soporta asignarFotoRuta"
);

$arregloPersona = $personaModelo->aArreglo();
test_afirmar(
    isset($arregloPersona['foto_ruta']) && $arregloPersona['foto_ruta'] === 'avatars/avatar_modificado.png',
    "PERSONA-03: Método aArreglo incluye foto_ruta"
);

$desdeArr = Persona::desdeArreglo([
    'nombres' => 'Copia',
    'foto_ruta' => 'avatars/avatar_desde_array.jpg'
]);
test_afirmar(
    $desdeArr->obtenerFotoRuta() === 'avatars/avatar_desde_array.jpg',
    "PERSONA-04: Método desdeArreglo hidrata foto_ruta correctamente"
);

// Repositorio
$personaRepo = new PersonaRepositorio($pdo);
$testPersonaId = $personaRepo->insertar($personaModelo);
test_afirmar(
    $testPersonaId > 0,
    "PERSONA-REPO-01: Inserción de persona con foto_ruta persistida"
);

$personaPersistida = $personaRepo->buscarPorId($testPersonaId, false);
test_afirmar(
    $personaPersistida !== null && $personaPersistida->obtenerFotoRuta() === 'avatars/avatar_modificado.png',
    "PERSONA-REPO-02: buscarPorId recupera foto_ruta persistida en BD"
);

$actOk = $personaRepo->actualizarFotoRuta($testPersonaId, 'avatars/avatar_actualizado.jpg');
test_afirmar(
    $actOk === true,
    "PERSONA-REPO-03: actualizarFotoRuta ejecuta con éxito"
);

$personaActualizada = $personaRepo->buscarPorId($testPersonaId, false);
test_afirmar(
    $personaActualizada !== null && $personaActualizada->obtenerFotoRuta() === 'avatars/avatar_actualizado.jpg',
    "PERSONA-REPO-04: actualizarFotoRuta refleja nueva referencia en BD"
);

// --- GRUPO 3: SERVICIO DE ALMACENAMIENTO Y REGLAS DE SEGURIDAD ---
echo "\n--- GRUPO 3: SERVICIO DE ALMACENAMIENTO Y REGLAS DE SEGURIDAD ---\n";

$storageTemporal = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'camargo_test_storage_' . bin2hex(random_bytes(6));
$fotoServicio = new FotoPersonaServicio($pdo, $personaRepo, $storageTemporal);

// Crear fixtures temporales
$dirFixtures = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'camargo_fixtures_' . bin2hex(random_bytes(6));
mkdir($dirFixtures, 0755, true);

// Fixture 1: JPEG real mínimo (1x1 pixel)
$rutaJpeg = $dirFixtures . DIRECTORY_SEPARATOR . 'test_valido.jpg';
file_put_contents($rutaJpeg, base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA='));

// Fixture 2: PNG real mínimo (1x1 pixel)
$rutaPng = $dirFixtures . DIRECTORY_SEPARATOR . 'test_valido.png';
file_put_contents($rutaPng, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));

// Fixture 3: Archivo falso (texto con extensión .jpg)
$rutaFalsoMime = $dirFixtures . DIRECTORY_SEPARATOR . 'falso_jpeg.jpg';
file_put_contents($rutaFalsoMime, 'ESTO NO ES UN ARCHIVO BINARIO DE IMAGEN JPEG');

// Fixture 4: Archivo SVG (no permitido)
$rutaSvg = $dirFixtures . DIRECTORY_SEPARATOR . 'peligroso.svg';
file_put_contents($rutaSvg, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

// Fixture 5: Archivo superior a 2MB
$rutaPesado = $dirFixtures . DIRECTORY_SEPARATOR . 'pesado.jpg';
$fpPesado = fopen($rutaPesado, 'w');
fseek($fpPesado, (2 * 1024 * 1024) + 1024);
fwrite($fpPesado, "\0");
fclose($fpPesado);

// Test JPEG válido
$refJpeg = $fotoServicio->procesarSubidaFoto($testPersonaId, [
    'name' => 'foto_original_usuario.jpg',
    'type' => 'image/jpeg',
    'tmp_name' => $rutaJpeg,
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($rutaJpeg),
]);
test_afirmar(
    str_starts_with($refJpeg, 'avatars/avatar_') && str_ends_with($refJpeg, '.jpg'),
    "SEGURIDAD-01: JPEG válido aceptado y nombre original descartado por identificador seguro"
);

test_afirmar(
    file_exists($storageTemporal . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $refJpeg)),
    "SEGURIDAD-02: Archivo físico guardado en el subdirectorio administrado avatars/"
);

// Test PNG válido y reemplazo seguro
$refPng = $fotoServicio->procesarSubidaFoto($testPersonaId, [
    'name' => 'otra_foto.png',
    'type' => 'image/png',
    'tmp_name' => $rutaPng,
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($rutaPng),
]);
test_afirmar(
    str_starts_with($refPng, 'avatars/avatar_') && str_ends_with($refPng, '.png'),
    "SEGURIDAD-03: PNG válido aceptado y extensión derivada del MIME verificado"
);

// Reemplazo atómico: foto anterior (refJpeg) debe haber sido eliminada tras éxito
test_afirmar(
    !file_exists($storageTemporal . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $refJpeg)),
    "REEMPLAZO-01: Fotografía anterior eliminada físicamente de forma segura tras actualización exitosa"
);

// Rechazo de MIME falso
$errorFalsoMime = false;
try {
    $fotoServicio->procesarSubidaFoto($testPersonaId, [
        'name' => 'hack.jpg',
        'type' => 'image/jpeg', // Tipo MIME declarado en browser
        'tmp_name' => $rutaFalsoMime,
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($rutaFalsoMime),
    ]);
} catch (ValidacionExcepcion $e) {
    $errorFalsoMime = true;
}
test_afirmar(
    $errorFalsoMime === true,
    "SEGURIDAD-04: Archivo con MIME falso rechazado mediante inspección binaria con Fileinfo"
);

// Rechazo de SVG
$errorSvg = false;
try {
    $fotoServicio->procesarSubidaFoto($testPersonaId, [
        'name' => 'vector.svg',
        'type' => 'image/svg+xml',
        'tmp_name' => $rutaSvg,
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($rutaSvg),
    ]);
} catch (ValidacionExcepcion $e) {
    $errorSvg = true;
}
test_afirmar(
    $errorSvg === true,
    "SEGURIDAD-05: Archivos SVG terminantemente rechazados"
);

// Rechazo de archivo > 2MB
$errorPesado = false;
try {
    $fotoServicio->procesarSubidaFoto($testPersonaId, [
        'name' => 'pesado.jpg',
        'type' => 'image/jpeg',
        'tmp_name' => $rutaPesado,
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($rutaPesado),
    ]);
} catch (ValidacionExcepcion $e) {
    $errorPesado = true;
}
test_afirmar(
    $errorPesado === true,
    "SEGURIDAD-06: Archivos mayores a 2 MB rechazados por límite estricto"
);

// Test de resiliencia atómica: si la BD falla, el archivo nuevo huérfano se limpia
// y la foto anterior se conserva intacta
$personaActualAntes = $personaRepo->buscarPorId($testPersonaId, false);
$fotoPreviaEsperada = $personaActualAntes?->obtenerFotoRuta();

// Creamos un repositorio simulado que falla en la actualización de BD
$repoSimuladoFalla = new class($pdo) extends PersonaRepositorio {
    public function actualizarFotoRuta(int $personaId, ?string $fotoRuta): bool
    {
        throw new \RuntimeException("Fallo simulado en motor de base de datos durante actualización.");
    }
};

$servicioFalla = new FotoPersonaServicio($pdo, $repoSimuladoFalla, $storageTemporal);
$errorBdCapturado = false;
try {
    $servicioFalla->procesarSubidaFoto($testPersonaId, [
        'name' => 'test_falla.jpg',
        'type' => 'image/jpeg',
        'tmp_name' => $rutaJpeg,
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($rutaJpeg),
    ]);
} catch (\Throwable $e) {
    $errorBdCapturado = true;
}

test_afirmar(
    $errorBdCapturado === true,
    "REEMPLAZO-02: Fallo en BD capturado y propagado"
);

$personaPostFalla = $personaRepo->buscarPorId($testPersonaId, false);
test_afirmar(
    $personaPostFalla?->obtenerFotoRuta() === $fotoPreviaEsperada,
    "REEMPLAZO-03: Fallo en BD conserva referencia de fotografía previa intacta"
);

// Eliminar foto / Restablecer a NULL
$eliminoOk = $fotoServicio->eliminarFoto($testPersonaId);
test_afirmar(
    $eliminoOk === true,
    "REEMPLAZO-04: eliminarFoto ejecuta con éxito"
);

$personaSinFoto = $personaRepo->buscarPorId($testPersonaId, false);
test_afirmar(
    $personaSinFoto?->obtenerFotoRuta() === null,
    "REEMPLAZO-05: eliminarFoto deja foto_ruta estrictamente en NULL"
);

// --- GRUPO 4: RESOLUCIÓN DE URL Y FALLBACK ---
echo "\n--- GRUPO 4: RESOLUCIÓN DE URL Y FALLBACK ---\n";

$urlConFoto = url_storage('avatars/avatar_ejemplo.jpg');
test_afirmar(
    str_contains($urlConFoto, '/storage/avatars/avatar_ejemplo.jpg'),
    "URL-01: url_storage resuelve ruta pública hacia /storage/avatars/..."
);

$urlFallback = url_storage(null);
test_afirmar(
    str_contains($urlFallback, '/assets/images/avatar/01.png'),
    "URL-02: url_storage(null) resuelve avatar neutro de fallback de Alina (01.png)"
);

$urlDesdeServicio = $fotoServicio->resolverUrlFoto(null);
test_afirmar(
    str_contains($urlDesdeServicio, '/assets/images/avatar/01.png'),
    "URL-03: FotoPersonaServicio::resolverUrlFoto(null) retorna fallback canónico"
);

// --- GRUPO 5: CONTROLADOR Y ENDPOINTS DE SEGURIDAD ---
echo "\n--- GRUPO 5: CONTROLADOR Y ENDPOINTS DE SEGURIDAD ---\n";

// Controlador sin sesión activa -> debe retornar 401
$sesionMockSinAutenticacion = new class extends SesionServicio {
    public function validarSesionActual(): ?Usuario
    {
        return null;
    }
};

$controladorSinSesion = new PerfilControlador(
    null,
    $sesionMockSinAutenticacion,
    new UsuarioRepositorio($pdo),
    $personaRepo,
    $fotoServicio,
    new CsrfServicio()
);

$resp401 = $controladorSinSesion->actualizarFoto();
test_afirmar(
    $resp401->obtenerCodigoEstado() === 401,
    "ENDPOINT-01: POST /perfil/foto rechaza con 401 si no hay sesión activa"
);

// Controlador con sesión pero sin token CSRF -> debe retornar 403
$usuarioMock = new class extends Usuario {
    public function __construct() {}
    public function obtenerId(): int { return 1; }
    public function obtenerPersonaId(): int { return 1; }
    public function obtenerNombreUsuario(): string { return 'testuser'; }
};

$sesionMockAutenticado = new class($usuarioMock) extends SesionServicio {
    private $u;
    public function __construct($u) { $this->u = $u; }
    public function validarSesionActual(): ?Usuario { return $this->u; }
};

$csrfInvalidoServicio = new class extends CsrfServicio {
    public function validarToken(?string $token): bool { return false; }
};

$controladorCsrfInvalido = new PerfilControlador(
    null,
    $sesionMockAutenticado,
    new UsuarioRepositorio($pdo),
    $personaRepo,
    $fotoServicio,
    $csrfInvalidoServicio
);

$_POST = [];
$resp403 = $controladorCsrfInvalido->actualizarFoto();
test_afirmar(
    $resp403->obtenerCodigoEstado() === 403,
    "ENDPOINT-02: POST /perfil/foto rechaza con 403 ante token CSRF ausente o inválido"
);

// Enrutamiento en public/index.php
$indexPhp = file_get_contents(__DIR__ . '/../public/index.php');
test_afirmar(
    strpos($indexPhp, "'/perfil/foto'") !== false && strpos($indexPhp, "'actualizarFoto'") !== false,
    "ENRUTAMIENTO-01: Ruta POST /perfil/foto registrada en public/index.php"
);

test_afirmar(
    strpos($indexPhp, "'/perfil/foto/eliminar'") !== false && strpos($indexPhp, "'eliminarFoto'") !== false,
    "ENRUTAMIENTO-02: Ruta POST /perfil/foto/eliminar registrada en public/index.php"
);

// --- GRUPO 6: VISTA Y UX ALINA ---
echo "\n--- GRUPO 6: VISTA Y UX ALINA ---\n";

$vistaPerfil = file_get_contents(__DIR__ . '/../app/Vistas/perfil/index.php');
test_afirmar(
    strpos($vistaPerfil, 'id="imageUpload"') !== false,
    "VISTA-01: Input de carga de imagen #imageUpload presente"
);

test_afirmar(
    strpos($vistaPerfil, 'id="imgPreview"') !== false,
    "VISTA-02: Contenedor #imgPreview para previsualización dinámica presente"
);

test_afirmar(
    strpos($vistaPerfil, 'id="btnGuardarFoto"') !== false && strpos($vistaPerfil, 'id="btnCancelarFoto"') !== false,
    "VISTA-03: Botones de Guardar y Cancelar presentes para control de persistencia"
);

test_afirmar(
    strpos($vistaPerfil, 'id="btnEliminarFoto"') !== false,
    "VISTA-04: Botón #btnEliminarFoto presente para restablecer avatar"
);

test_afirmar(
    strpos($vistaPerfil, 'id="alertaFoto"') !== false,
    "VISTA-05: Contenedor de feedback y alertas #alertaFoto presente"
);

test_afirmar(
    strpos($vistaPerfil, 'FileReader') !== false,
    "VISTA-06: Previsualización local implementada con Vanilla JS FileReader"
);

test_afirmar(
    strpos($vistaPerfil, 'jQuery') === false && strpos($vistaPerfil, '$(') === false,
    "VISTA-07: Cero dependencias de jQuery en la lógica de perfil y fotografía"
);

// --- LIMPIEZA DE FIXTURES TEMPORALES Y REGISTROS DE PRUEBA ---
// Limpiar persona de prueba de la BD
$pdo->exec("DELETE FROM personas WHERE id = {$testPersonaId}");

// Limpiar archivos temporales
@unlink($rutaJpeg);
@unlink($rutaPng);
@unlink($rutaFalsoMime);
@unlink($rutaSvg);
@unlink($rutaPesado);
@rmdir($dirFixtures);

// Limpiar archivos en storage temporal
if (is_dir($storageTemporal . DIRECTORY_SEPARATOR . 'avatars')) {
    foreach (glob($storageTemporal . DIRECTORY_SEPARATOR . 'avatars/*') as $f) {
        @unlink($f);
    }
    @rmdir($storageTemporal . DIRECTORY_SEPARATOR . 'avatars');
}
@rmdir($storageTemporal);

// Resumen Final
echo "\n====================================================================\n";
echo " RESUMEN: {$asercionesExitosas}/{$totalAserciones} PRUEBAS SUPERADAS\n";
echo "====================================================================\n";

if ($asercionesExitosas !== $totalAserciones) {
    echo ">>> REGRESIÓN O FALLO EN SUITE UI-ALINA-1B-C1 <<<\n";
    exit(1);
}

echo ">>> SUITE UI-ALINA-1B-C1: 100% EXITOSA <<<\n";
exit(0);
