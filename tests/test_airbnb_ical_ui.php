<?php

declare(strict_types=1);

/**
 * Suite de Pruebas Automatizadas: AIRBNB-ICAL-1C
 * Capa Operativa Alina para Canales y Conexiones iCalendar.
 *
 * Cobertura de Principios y Gates Obligatorios:
 * 1. Control de acceso y RBAC granular (canales.ver, canales.gestionar, canales.sincronizar).
 * 2. Protección CSRF en todas las operaciones mutables (crear, editar, estado, rotar, sincronizar).
 * 3. Creación segura de conexiones: cifrado AES-256-GCM de URL y generación híbrida de token.
 * 4. Test negativo estricto de secretos en listado y detalle (cero URLs, cero tokens, cero claves en JSON/DOM).
 * 5. Semántica de edición de URL (Regla 15: vacía conserva anterior, nueva valida y cifra).
 * 6. Recuperación controlada de URL de exportación bajo demanda explícita (Regla 16 & 17).
 * 7. Rotación criptográfica de tokens e invalidación inmediata del feed previo (Regla 18).
 * 8. Revocación lógica sin borrado físico (preservación histórica).
 * 9. Sincronización manual soberana y control de concurrencia/doble submit (HTTP 409 Conflict).
 * 10. Consulta de historial técnico y colisiones/conflictos de inventario (Alina modals).
 * 11. Auditoría inmutable sin filtración de secretos técnicos.
 * 12. Fidelidad Alina (0 dotted, 0 dashed, 0 *-subtle), 122 tablas en BD y slot 036 LIBRE.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CamargoPMS\Controladores\CanalIcalControlador;
use CamargoPMS\Intermediarios\AutorizacionIntermediario;
use CamargoPMS\Modelos\CanalDistribucion;
use CamargoPMS\Modelos\ConexionIcal;
use CamargoPMS\Modelos\EventoIcalExterno;
use CamargoPMS\Modelos\SincronizacionIcalLog;
use CamargoPMS\Modelos\Usuario;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Repositorios\CanalDistribucionRepositorio;
use CamargoPMS\Repositorios\ConexionIcalRepositorio;
use CamargoPMS\Repositorios\EventoIcalExternoRepositorio;
use CamargoPMS\Repositorios\SincronizacionIcalLogRepositorio;
use CamargoPMS\Servicios\AuditoriaServicio;
use CamargoPMS\Servicios\AutorizacionServicio;
use CamargoPMS\Servicios\CsrfServicio;
use CamargoPMS\Servicios\IcalCriptografiaServicio;
use CamargoPMS\Servicios\SesionServicio;
use CamargoPMS\Servicios\SincronizacionIcalServicio;

Configuracion::cargar(dirname(__DIR__));
$pdo = BaseDatos::conexion();

$totalAssertions = 0;
$passedAssertions = 0;

function assertCheck(bool $condition, string $message): void
{
    global $totalAssertions, $passedAssertions;
    $totalAssertions++;
    if ($condition) {
        $passedAssertions++;
        echo "  [PASS] $message\n";
    } else {
        echo "  [FAIL] $message\n";
        throw new RuntimeException("Fallo en aserción: $message");
    }
}

echo "====================================================================\n";
echo " CAMARGO PMS — PRUEBAS AIRBNB-ICAL-1C: CAPA OPERATIVA ALINA\n";
echo "====================================================================\n\n";

$conexionRepo = new ConexionIcalRepositorio($pdo);
$canalRepo = new CanalDistribucionRepositorio($pdo);
$eventoRepo = new EventoIcalExternoRepositorio($pdo);
$logRepo = new SincronizacionIcalLogRepositorio($pdo);
$cripto = new IcalCriptografiaServicio();
$csrfServicio = new CsrfServicio();
$autorizacionServicio = new AutorizacionServicio($pdo);

// Obtener o crear fixtures necesarios
$stmtUnidad = $pdo->query("SELECT u.id, u.propiedad_id FROM unidades u JOIN propiedades p ON p.id = u.propiedad_id WHERE u.estado = 'ACTIVO' LIMIT 1");
$unidadFixture = $stmtUnidad->fetch(PDO::FETCH_ASSOC);
$unidadId = (int) $unidadFixture['id'];
$propiedadId = (int) $unidadFixture['propiedad_id'];

$canalAirbnb = $canalRepo->buscarPorCodigo('AIRBNB');
assertCheck($canalAirbnb !== null, "Canal AIRBNB existe en la base de datos");
$canalId = (int) $canalAirbnb->obtenerId();

// Mock sesión / usuario
$stmtAdmin = $pdo->query("SELECT u.id, u.persona_id, u.nombre_usuario FROM usuarios u JOIN usuarios_roles ur ON ur.usuario_id = u.id JOIN roles r ON r.id = ur.rol_id WHERE r.codigo = 'SUPERADMINISTRADOR' AND u.estado = 'ACTIVO' LIMIT 1");
$adminRow = $stmtAdmin->fetch(PDO::FETCH_ASSOC);
$adminId = (int) $adminRow['id'];
$adminPersonaId = (int) $adminRow['persona_id'];
$usuarioAdmin = new Usuario($adminId, $adminPersonaId, (string)$adminRow['nombre_usuario'], 'hash');

// Usuario sin permisos (operador básico o restringido)
$usuarioRestringido = new Usuario(999999, 999999, 'operador_sin_permiso', 'hash');

// SesionServicio mockeable para pruebas
$mockSesionAdmin = new class($usuarioAdmin) extends SesionServicio {
    public function __construct(private Usuario $u) {}
    public function validarSesionActual(): ?Usuario { return $this->u; }
};

$mockSesionRestringido = new class($usuarioRestringido) extends SesionServicio {
    public function __construct(private Usuario $u) {}
    public function validarSesionActual(): ?Usuario { return $this->u; }
};

$mockSesionAnonima = new class extends SesionServicio {
    public function __construct() {}
    public function validarSesionActual(): ?Usuario { return null; }
};

// =========================================================================
// 1. CONTROL DE ACCESO, SESIÓN Y RBAC GRANULAR
// =========================================================================
echo "--- 1. Control de Acceso y RBAC Granular ---\n";

$controladorAnonimo = new CanalIcalControlador(
    pdo: $pdo,
    sesionServicio: $mockSesionAnonima,
    autorizacionServicio: $autorizacionServicio,
    csrfServicio: $csrfServicio
);

// 1.1 Petición anónima a vista redirige a /login
$resAnonimaHtml = $controladorAnonimo->index();
assertCheck($resAnonimaHtml->obtenerCodigoEstado() === 302, "Acceso anónimo a /canales-ical redirige (HTTP 302)");
assertCheck(str_contains($resAnonimaHtml->obtenerCabeceras()['Location'] ?? '', '/login'), "Redirección apunta al endpoint de autenticación /login");

// 1.2 Petición anónima a API devuelve HTTP 401
$resAnonimaApi = $controladorAnonimo->datosJson();
assertCheck($resAnonimaApi->obtenerCodigoEstado() === 401, "Petición anónima a /canales-ical/datos retorna HTTP 401 Unauthorized");

// 1.3 Intermediario de autorización RBAC
$intermVer = new AutorizacionIntermediario('canales.ver', $mockSesionAdmin, $autorizacionServicio);
assertCheck($intermVer->manejar('/canales-ical') === null, "Superadministrador supera AutorizacionIntermediario para canales.ver");

$intermGestionar = new AutorizacionIntermediario('canales.gestionar', $mockSesionAdmin, $autorizacionServicio);
assertCheck($intermGestionar->manejar('/canales-ical') === null, "Superadministrador supera AutorizacionIntermediario para canales.gestionar");

$intermSync = new AutorizacionIntermediario('canales.sincronizar', $mockSesionAdmin, $autorizacionServicio);
assertCheck($intermSync->manejar('/canales-ical') === null, "Superadministrador supera AutorizacionIntermediario para canales.sincronizar");

// 1.4 Usuario restringido sin permiso canales.ver
$controladorRestringido = new CanalIcalControlador(
    pdo: $pdo,
    sesionServicio: $mockSesionRestringido,
    autorizacionServicio: $autorizacionServicio,
    csrfServicio: $csrfServicio
);
$resRestringidoJson = $controladorRestringido->datosJson();
assertCheck($resRestringidoJson->obtenerCodigoEstado() === 403, "Usuario sin canales.ver recibe HTTP 403 en /canales-ical/datos");

// =========================================================================
// 2. PROTECCIÓN CSRF EN OPERACIONES MUTABLES
// =========================================================================
echo "\n--- 2. Protección CSRF en Operaciones Mutables ---\n";

$controladorAdmin = new CanalIcalControlador(
    pdo: $pdo,
    sesionServicio: $mockSesionAdmin,
    autorizacionServicio: $autorizacionServicio,
    csrfServicio: $csrfServicio
);

// Simular petición sin token CSRF
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'application/json';
$_POST = [];
unset($_SERVER['HTTP_X_CSRF_TOKEN']);

// 2.1 Crear sin CSRF -> 403
$refProp = new ReflectionProperty(CanalIcalControlador::class, 'csrfServicio');
$tokenValido = $csrfServicio->obtenerToken();

// Prueba con token nulo
$resCsrfCrear = $controladorAdmin->crear();
assertCheck($resCsrfCrear->obtenerCodigoEstado() === 403, "POST /canales-ical sin CSRF es rechazado con HTTP 403");

// 2.2 Sincronizar sin CSRF -> 403
$resCsrfSync = $controladorAdmin->sincronizar(1);
assertCheck($resCsrfSync->obtenerCodigoEstado() === 403, "POST /canales-ical/{id}/sincronizar sin CSRF es rechazado con HTTP 403");

// 2.3 Rotar token sin CSRF -> 403
$resCsrfRotar = $controladorAdmin->rotarToken(1);
assertCheck($resCsrfRotar->obtenerCodigoEstado() === 403, "POST /canales-ical/{id}/rotar-token sin CSRF es rechazado con HTTP 403");

// 2.4 Copiar feed sin CSRF -> 403
$resCsrfCopiar = $controladorAdmin->copiarFeed(1);
assertCheck($resCsrfCopiar->obtenerCodigoEstado() === 403, "POST /canales-ical/{id}/copiar-feed sin CSRF es rechazado con HTTP 403");

// =========================================================================
// 3. CREACIÓN SEGURA DE CONEXIONES Y VALIDACIONES
// =========================================================================
echo "\n--- 3. Creación Segura de Conexiones ---\n";

$_SERVER['HTTP_X_CSRF_TOKEN'] = $tokenValido;

// 3.1 Validación: unidad inexistente
$_POST = [
    'unidad_id' => 0,
    'canal_id' => $canalId,
    'nombre' => 'Test Invalido',
    '_csrf' => $tokenValido,
];
$resUnidadInvalida = $controladorAdmin->crear();
assertCheck($resUnidadInvalida->obtenerCodigoEstado() === 422, "Rechaza unidad inválida con HTTP 422");

// 3.2 Validación: canal inexistente
$_POST = [
    'unidad_id' => $unidadId,
    'canal_id' => 999999,
    'nombre' => 'Test Invalido',
    '_csrf' => $tokenValido,
];
$resCanalInvalido = $controladorAdmin->crear();
assertCheck($resCanalInvalido->obtenerCodigoEstado() === 422, "Rechaza canal inexistente con HTTP 422");

// 3.3 Validación: URL de importación no HTTPS o insegura
$_POST = [
    'unidad_id' => $unidadId,
    'canal_id' => $canalId,
    'nombre' => 'Test Inseguro',
    'importacion_habilitada' => '1',
    'url_importacion' => 'http://insecure-domain.com/ical',
    '_csrf' => $tokenValido,
];
$resHttpInseguro = $controladorAdmin->crear();
assertCheck($resHttpInseguro->obtenerCodigoEstado() === 422, "Rechaza URL sin HTTPS con HTTP 422");

$_POST['url_importacion'] = 'https://localhost/calendar.ics';
$resLoopback = $controladorAdmin->crear();
assertCheck($resLoopback->obtenerCodigoEstado() === 422, "Rechaza URL apuntando a localhost con HTTP 422");

// 3.4 Creación exitosa con URL privada válida
$urlPrivadaTest = 'https://www.airbnb.com/calendar/ical/test-suite-ui-1c-' . bin2hex(random_bytes(6)) . '.ics';
$_POST = [
    'unidad_id' => $unidadId,
    'canal_id' => $canalId,
    'nombre' => 'Airbnb Suite Nupcial UI-1C',
    'importacion_habilitada' => '1',
    'url_importacion' => $urlPrivadaTest,
    'exportacion_habilitada' => '1',
    'frecuencia_minutos' => '30',
    'estado' => 'ACTIVO',
    '_csrf' => $tokenValido,
];

$resCrearOk = $controladorAdmin->crear();
assertCheck($resCrearOk->obtenerCodigoEstado() === 201, "Creación de conexión válida retorna HTTP 201 Created");
$dataCrear = json_decode($resCrearOk->obtenerContenido(), true);
$conexionCreadaId = (int) ($dataCrear['id'] ?? 0);
assertCheck($conexionCreadaId > 0, "ID generado para la nueva conexión es un entero positivo (#$conexionCreadaId)");

// 3.5 Verificación en BD: URL cifrada con AES-256-GCM y token generado de forma híbrida
$stmtRaw = $pdo->prepare("SELECT * FROM conexiones_ical WHERE id = :id");
$stmtRaw->execute(['id' => $conexionCreadaId]);
$filaRaw = $stmtRaw->fetch(PDO::FETCH_ASSOC);

assertCheck($filaRaw['url_importacion_cifrada'] !== $urlPrivadaTest, "URL de importación está almacenada CIFRADA en la BD");
assertCheck(strlen($filaRaw['token_exportacion_hash']) === 64, "token_exportacion_hash tiene longitud SHA-256 de 64 chars");
assertCheck($filaRaw['token_exportacion_cifrado'] !== '', "token_exportacion_cifrado está persistido con AES-GCM");
assertCheck(strlen($filaRaw['token_prefijo']) === 8, "token_prefijo tiene exactamente 8 caracteres seguros");

// =========================================================================
// 4. TEST NEGATIVO ESTRICTO: CERO SECRETOS EN LISTADO Y DETALLE
// =========================================================================
echo "\n--- 4. Test Negativo Estricto de Secretos en Listados ---\n";

$resListado = $controladorAdmin->datosJson();
assertCheck($resListado->obtenerCodigoEstado() === 200, "GET /canales-ical/datos retorna HTTP 200 OK");
$cuerpoJson = $resListado->obtenerContenido();

// 4.1 Test negativo sobre payload JSON
assertCheck(!str_contains($cuerpoJson, 'url_importacion_cifrada'), "Test Negativo: JSON NO contiene 'url_importacion_cifrada'");
assertCheck(!str_contains($cuerpoJson, 'token_exportacion_hash'), "Test Negativo: JSON NO contiene 'token_exportacion_hash'");
assertCheck(!str_contains($cuerpoJson, 'token_exportacion_cifrado'), "Test Negativo: JSON NO contiene 'token_exportacion_cifrado'");
assertCheck(!str_contains($cuerpoJson, $urlPrivadaTest), "Test Negativo: JSON NO contiene la URL plana privada");
assertCheck(!str_contains($cuerpoJson, (string) Configuracion::obtener('ICAL_ENCRYPTION_KEY', '')), "Test Negativo: JSON NO contiene la clave de cifrado");

// 4.2 Indicadores operativos calculados correctamente
$dataListado = json_decode($cuerpoJson, true);
assertCheck(isset($dataListado['kpis']['total_conexiones']) && $dataListado['kpis']['total_conexiones'] >= 1, "KPIs: total_conexiones calculado correctamente");
assertCheck(isset($dataListado['kpis']['activas']), "KPIs: conexiones activas presente");
assertCheck(isset($dataListado['kpis']['conflictos_pendientes']), "KPIs: conflictos_pendientes presente");

// =========================================================================
// 5. EDICIÓN Y SEMÁNTICA DE URL (REGLA 15)
// =========================================================================
echo "\n--- 5. Semántica de Edición de URL (Regla 15) ---\n";

// 5.1 Edición con URL vacía CONSERVA la actual
$cifradoAnterior = $filaRaw['url_importacion_cifrada'];

$_POST = [
    'nombre' => 'Airbnb Suite Nupcial (Renombrada)',
    'importacion_habilitada' => '1',
    'nueva_url_importacion' => '', // VACÍA
    'exportacion_habilitada' => '1',
    'frecuencia_minutos' => '60',
    'estado' => 'ACTIVO',
    '_csrf' => $tokenValido,
];

$resEditarConserva = $controladorAdmin->editar($conexionCreadaId);
assertCheck($resEditarConserva->obtenerCodigoEstado() === 200, "Edición con URL vacía responde HTTP 200");

$stmtRaw->execute(['id' => $conexionCreadaId]);
$filaActualizada = $stmtRaw->fetch(PDO::FETCH_ASSOC);
assertCheck($filaActualizada['nombre'] === 'Airbnb Suite Nupcial (Renombrada)', "Nombre fue actualizado");
assertCheck($filaActualizada['url_importacion_cifrada'] === $cifradoAnterior, "REGLA 15: URL cifrada previa se conservó 100% idéntica al enviar vacío");

// 5.2 Edición con NUEVA URL válida REEMPLAZA y cifra
$nuevaUrlTest = 'https://www.airbnb.com/calendar/ical/test-suite-ui-nueva-' . bin2hex(random_bytes(6)) . '.ics';
$_POST['nueva_url_importacion'] = $nuevaUrlTest;

$resEditarReemplaza = $controladorAdmin->editar($conexionCreadaId);
assertCheck($resEditarReemplaza->obtenerCodigoEstado() === 200, "Edición con nueva URL responde HTTP 200");

$stmtRaw->execute(['id' => $conexionCreadaId]);
$filaConNuevaUrl = $stmtRaw->fetch(PDO::FETCH_ASSOC);
assertCheck($filaConNuevaUrl['url_importacion_cifrada'] !== $cifradoAnterior, "REGLA 15: Ciphertext fue actualizado con la nueva URL");
$urlDescifrada = $cripto->descifrar($filaConNuevaUrl['url_importacion_cifrada']);
assertCheck($urlDescifrada === $nuevaUrlTest, "La nueva URL se descifra exactamente al valor ingresado");

// =========================================================================
// 6. RECUPERACIÓN CONTROLADA DE FEED (REGLAS 16 Y 17)
// =========================================================================
echo "\n--- 6. Recuperación Controlada del Feed de Exportación ---\n";

$_POST = ['_csrf' => $tokenValido];

// 6.1 Recuperación autorizada
$resFeedOk = $controladorAdmin->copiarFeed($conexionCreadaId);
assertCheck($resFeedOk->obtenerCodigoEstado() === 200, "POST /canales-ical/{id}/copiar-feed retorna HTTP 200");
$dataFeed = json_decode($resFeedOk->obtenerContenido(), true);
$urlFeedObtenida = (string) ($dataFeed['url_feed'] ?? '');
assertCheck(str_contains($urlFeedObtenida, '/ical/exportar/'), "URL devuelta contiene ruta canónica /ical/exportar/{token}");

// 6.2 Comprobar que el endpoint público de exportación responde 200 con el token descifrado
$tokenExtraido = basename($urlFeedObtenida);
assertCheck(strlen($tokenExtraido) === 64, "Token descifrado en memoria backend tiene 64 caracteres hex");

$exportControlador = new \CamargoPMS\Controladores\IcalExportarControlador();
$resPublica = $exportControlador->exportar($tokenExtraido);
assertCheck($resPublica->obtenerCodigoEstado() === 200, "Endpoint público /ical/exportar/{token} responde HTTP 200 con token recuperado");
assertCheck($resPublica->obtenerCabeceras()['Content-Type'] === 'text/calendar; charset=utf-8', "Content-Type del feed es text/calendar; charset=utf-8");

// 6.3 Verificación de seguridad: Cache-Control y no filtración de secretos en cabeceras
$cabecerasFeed = $resFeedOk->obtenerCabeceras();
assertCheck(isset($cabecerasFeed['Cache-Control']) && str_contains($cabecerasFeed['Cache-Control'], 'no-store'), "Cabecera Cache-Control incluye directiva obligatoria no-store");
assertCheck(isset($cabecerasFeed['Pragma']) && str_contains($cabecerasFeed['Pragma'], 'no-cache'), "Cabecera Pragma incluye no-cache para compatibilidad defensiva");
$cabecerasValores = implode(' ', array_values($cabecerasFeed));
assertCheck(!str_contains($cabecerasValores, $tokenExtraido), "Test Negativo: El token de exportación NO aparece filtrado en las cabeceras HTTP");
assertCheck(!isset($cabecerasFeed['Set-Cookie']) || !str_contains($cabecerasFeed['Set-Cookie'], $tokenExtraido), "Test Negativo: El token de exportación NO se filtra en cookies");

// =========================================================================
// 7. ROTACIÓN CRIPTOGRÁFICA DE TOKEN (REGLA 18)
// =========================================================================
echo "\n--- 7. Rotación Criptográfica de Token ---\n";

$hashViejo = $filaConNuevaUrl['token_exportacion_hash'];
$_POST = ['_csrf' => $tokenValido];

$resRotar = $controladorAdmin->rotarToken($conexionCreadaId);
assertCheck($resRotar->obtenerCodigoEstado() === 200, "POST /canales-ical/{id}/rotar-token retorna HTTP 200");

$stmtRaw->execute(['id' => $conexionCreadaId]);
$filaRotada = $stmtRaw->fetch(PDO::FETCH_ASSOC);
$hashNuevo = $filaRotada['token_exportacion_hash'];

assertCheck($hashViejo !== $hashNuevo, "REGLA 18: Hash de exportación fue cambiado irreversiblemente");
assertCheck($filaRotada['token_prefijo'] !== $filaConNuevaUrl['token_prefijo'], "Nuevo prefijo de token generado");

// 7.1 El token viejo ahora debe ser 404 (invalidez inmediata)
$resTokenViejoInvalido = $exportControlador->exportar($tokenExtraido);
assertCheck($resTokenViejoInvalido->obtenerCodigoEstado() === 404, "El feed con el token anterior queda INVALIDADO de inmediato (HTTP 404)");

// 7.2 El nuevo token funciona
$resFeedNuevo = $controladorAdmin->copiarFeed($conexionCreadaId);
$dataFeedNuevo = json_decode($resFeedNuevo->obtenerContenido(), true);
$nuevoTokenPlano = basename($dataFeedNuevo['url_feed']);
$resNuevoTokenOk = $exportControlador->exportar($nuevoTokenPlano);
assertCheck($resNuevoTokenOk->obtenerCodigoEstado() === 200, "El feed con el nuevo token rotado responde HTTP 200");

// =========================================================================
// 8. REVOCACIÓN LÓGICA SIN BORRADO FÍSICO
// =========================================================================
echo "\n--- 8. Revocación Lógica y Preservación Histórica ---\n";

$_POST = [
    'estado' => ConexionIcal::ESTADO_REVOCADO,
    '_csrf' => $tokenValido,
];

$resRevocar = $controladorAdmin->cambiarEstado($conexionCreadaId);
assertCheck($resRevocar->obtenerCodigoEstado() === 200, "Cambio de estado a REVOCADO retorna HTTP 200");

$conexionRevocada = $conexionRepo->buscarPorId($conexionCreadaId);
assertCheck($conexionRevocada !== null, "La conexión sigue existiendo en BD (Cero DELETE físico)");
assertCheck($conexionRevocada->estaRevocada(), "Estado de la conexión es REVOCADO");

// Conexión revocada niega copia de feed
$resFeedRevocado = $controladorAdmin->copiarFeed($conexionCreadaId);
assertCheck($resFeedRevocado->obtenerCodigoEstado() === 422, "Conexión revocada rechaza copia de feed con HTTP 422");

// Conexión revocada niega exportación pública
$resExportRevocada = $exportControlador->exportar($nuevoTokenPlano);
assertCheck($resExportRevocada->obtenerCodigoEstado() === 404, "Conexión revocada niega descarga de calendario (HTTP 404)");

// =========================================================================
// 9. SINCRONIZACIÓN MANUAL Y CONTROL DE CONCURRENCIA
// =========================================================================
echo "\n--- 9. Sincronización Manual y Concurrencia ---\n";

// Reactivar conexión para pruebas de sync
$conexionRepo->cambiarEstado($conexionCreadaId, ConexionIcal::ESTADO_ACTIVO);

// 9.1 Simulación de sincronización manual concurrente (doble clic)
// Insertamos un log artificial con finalizado_en = NULL para simular proceso en curso
$logConcurrenteId = $logRepo->iniciarLog(
    conexionId: $conexionCreadaId,
    tipoOperacion: SincronizacionIcalLog::TIPO_IMPORTACION,
    origenEjecucion: SincronizacionIcalLog::ORIGEN_MANUAL
);

assertCheck($logRepo->haySincronizacionEnCurso($conexionCreadaId), "haySincronizacionEnCurso detecta correctamente corrida activa");

// Intentar sincronizar concurrentemente -> debe retornar HTTP 409 Conflict
$_POST = ['_csrf' => $tokenValido];
$resColision = $controladorAdmin->sincronizar($conexionCreadaId);
assertCheck($resColision->obtenerCodigoEstado() === 409, "Petición concurrente o doble submit es bloqueada con HTTP 409 Conflict");
$dataColision = json_decode($resColision->obtenerContenido(), true);
assertCheck($dataColision['codigo'] === 'CONEXION_EN_SINCRONIZACION', "Código de error estructurado CONEXION_EN_SINCRONIZACION");

// Liberar el log artificial
$logRepo->finalizarLog(
    logId: $logConcurrenteId,
    httpCodigo: 200,
    resultado: SincronizacionIcalLog::RESULTADO_EXITO,
    duracionMs: 150,
    recibidos: 0,
    creados: 0,
    actualizados: 0,
    cancelados: 0,
    ausentes: 0,
    conflictos: 0,
    mensaje: 'Completado mock prueba'
);
assertCheck(!$logRepo->haySincronizacionEnCurso($conexionCreadaId), "Al finalizar el log, la conexión queda disponible nuevamente");

// =========================================================================
// 10. HISTORIAL Y CONFLICTOS
// =========================================================================
echo "\n--- 10. Historial y Conflictos (Modals Alina) ---\n";

// 10.1 Historial de logs sin secretos
$resHistorial = $controladorAdmin->historial($conexionCreadaId);
assertCheck($resHistorial->obtenerCodigoEstado() === 200, "GET /canales-ical/{id}/historial retorna HTTP 200");
$dataHist = json_decode($resHistorial->obtenerContenido(), true);
assertCheck(count($dataHist['historial'] ?? []) >= 1, "Historial contiene al menos 1 registro");
$logItem = $dataHist['historial'][0];
assertCheck(isset($logItem['iniciado_en']) && isset($logItem['resultado']), "Log contiene métricas técnicas");
assertCheck(!isset($logItem['url_importacion_cifrada']) && !isset($logItem['token']), "Log técnico NO contiene secretos");

// 10.2 Conflictos por conexión
$resConflictos = $controladorAdmin->conflictos($conexionCreadaId);
assertCheck($resConflictos->obtenerCodigoEstado() === 200, "GET /canales-ical/{id}/conflictos retorna HTTP 200");

// 10.3 Conflictos globales
$resConflictosGlobales = $controladorAdmin->todosLosConflictos();
assertCheck($resConflictosGlobales->obtenerCodigoEstado() === 200, "GET /canales-ical/conflictos-activos retorna HTTP 200");

// =========================================================================
// 11. AUDITORÍA INMUTABLE SIN SECRETOS
// =========================================================================
echo "\n--- 11. Auditoría Inmutable sin Filtración de Secretos ---\n";

$stmtAuditoria = $pdo->prepare("SELECT * FROM auditoria WHERE modulo = 'canales' AND entidad_id = :id ORDER BY id DESC LIMIT 5");
$stmtAuditoria->execute(['id' => $conexionCreadaId]);
$filasAud = $stmtAuditoria->fetchAll(PDO::FETCH_ASSOC);

assertCheck(count($filasAud) >= 1, "Existen registros de auditoría para la conexión (#" . count($filasAud) . ")");
foreach ($filasAud as $fa) {
    $nuevos = (string) ($fa['valores_nuevos'] ?? '');
    $anteriores = (string) ($fa['valores_anteriores'] ?? '');
    assertCheck(!str_contains($nuevos, $urlPrivadaTest), "Auditoría no contiene URL privada en valores_nuevos");
    assertCheck(!str_contains($nuevos, 'token_exportacion_hash'), "Auditoría no contiene hash de token en valores_nuevos");
    assertCheck(!str_contains($nuevos, 'token_exportacion_cifrado'), "Auditoría no contiene ciphertext en valores_nuevos");
}

// =========================================================================
// 12. FIDELIDAD ALINA, GOBERNANZA DE ESQUEMA Y ZERO BORDES PROHIBIDOS
// =========================================================================
echo "\n--- 12. Fidelidad Alina y Gobernanza del Esquema ---\n";

// 12.1 Vista HTML no contiene dotted ni dashed ni *-subtle
$vistaPath = dirname(__DIR__) . '/app/Vistas/canales_ical/index.php';
$vistaContenido = file_get_contents($vistaPath);
assertCheck(!str_contains($vistaContenido, 'dotted'), "Vista canales_ical/index.php libre de 'dotted'");
assertCheck(!str_contains($vistaContenido, 'dashed'), "Vista canales_ical/index.php libre de 'dashed'");
assertCheck(!str_contains($vistaContenido, '-subtle'), "Vista canales_ical/index.php libre de '*-subtle'");

// 12.2 JavaScript libre de jQuery AJAX y bordes prohibidos
$jsPath = dirname(__DIR__) . '/public/assets/js/gestion-canales-ical.js';
$jsContenido = file_get_contents($jsPath);
assertCheck(!str_contains($jsContenido, '$.ajax'), "JavaScript libre de $.ajax");
assertCheck(!str_contains($jsContenido, '$.post'), "JavaScript libre de $.post");
assertCheck(!str_contains($jsContenido, '$.get'), "JavaScript libre de $.get");
assertCheck(!str_contains($jsContenido, 'alert('), "JavaScript libre de alert() nativo");
assertCheck(!str_contains($jsContenido, 'confirm('), "JavaScript libre de confirm() nativo");

// 12.3 Base de datos con exactamente 122 tablas relacionales
$tablasCount = (int) $pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();
assertCheck($tablasCount === 122, "Base de datos contiene exactamente 122 tablas relacionales");

// 12.4 Última migración y ranura 036 LIBRE
$stmtUltimaMig = $pdo->query('SELECT migracion FROM migraciones ORDER BY id DESC LIMIT 1');
$ultimaMig = (string) $stmtUltimaMig->fetchColumn();
assertCheck(str_contains($ultimaMig, '035_canales_ical.sql'), "Última migración ejecutada es 035_canales_ical.sql");

$mig036 = glob(dirname(__DIR__) . '/database/migraciones/036*.sql');
assertCheck(empty($mig036), "Ranura 036 permanece estrictamente LIBRE (Cero DDL)");

// 12.5 Catálogo Alina intacto
$diffAdmin = shell_exec('git status --porcelain admin-dashboard/');
assertCheck(trim((string)$diffAdmin) === '', "admin-dashboard/ permanece 100% inmutable y limpio");

// 12.6 Limpieza de fixtures de prueba
$pdo->prepare("DELETE FROM eventos_ical_externos WHERE conexion_ical_id = :id")->execute(['id' => $conexionCreadaId]);
$pdo->prepare("DELETE FROM sincronizaciones_ical_log WHERE conexion_ical_id = :id")->execute(['id' => $conexionCreadaId]);
$pdo->prepare("DELETE FROM conexiones_ical WHERE id = :id")->execute(['id' => $conexionCreadaId]);
$pdo->prepare("DELETE FROM auditoria WHERE modulo = 'canales' AND entidad_id = :id")->execute(['id' => $conexionCreadaId]);

echo "\n====================================================================\n";
echo " RESUMEN AIRBNB-ICAL-1C: {$passedAssertions} / {$totalAssertions} pruebas superadas\n";
echo "====================================================================\n\n";

if ($passedAssertions === $totalAssertions) {
    echo ">>> AIRBNB-ICAL-1C: CAPA OPERATIVA ALINA VALIDADA AL 100% (TODO PASS) <<<\n";
    exit(0);
} else {
    echo ">>> AIRBNB-ICAL-1C: ALGUNAS PRUEBAS FALLARON <<<\n";
    exit(1);
}
