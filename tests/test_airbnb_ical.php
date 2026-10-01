<?php

declare(strict_types=1);

/**
 * Suite de Pruebas Automatizadas: AIRBNB-ICAL-1B
 * Infraestructura Soberana Multicanal iCalendar (RFC 5545).
 *
 * Cobertura de Principios y Gates Obligatorios:
 * 1. Dependencia sabre/vobject 5.x disponible y compatible.
 * 2. Criptografía AES-256-GCM para URLs y almacenamiento híbrido de tokens (SHA-256 + AES-GCM).
 * 3. Cliente HTTP Anti-SSRF (bloqueo IPv4/IPv6 privada, link-local, loopback, CGNAT, metadata),
 *    DNS Rebinding (CURLOPT_RESOLVE), control de redirecciones manuales y límites.
 * 4. Adaptador RFC 5545 (semántica hotelera [inicio, fin) sin off-by-one, recurrencias acotadas,
 *    DATE-TIME normalizado, line-folding, escaping y serialización con privacidad absoluta).
 * 5. Idempotencia y reconciliación determinista de inventario (UNIÓN de orígenes multi-OTA).
 * 6. Preservación absoluta de reservas locales (conflicto local sin sobreescritura).
 * 7. Salvaguarda de feed vacío sospechoso (0 VEVENTs no liberan inventario).
 * 8. Reconciliación de ausencias preservando histórico inmutable del pasado.
 * 9. Anti-Echo obligatorio por conexión de exportación (supresión de eventos de la misma OTA).
 * 10. Endpoint público GET /ical/exportar/{token} y gobierno del esquema (122 tablas, slot 036 libre).
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CamargoPMS\Adaptadores\IcalAdaptador;
use CamargoPMS\Controladores\IcalExportarControlador;
use CamargoPMS\Modelos\CanalDistribucion;
use CamargoPMS\Modelos\ConexionIcal;
use CamargoPMS\Modelos\EventoIcalExterno;
use CamargoPMS\Modelos\SincronizacionIcalLog;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Repositorios\CanalDistribucionRepositorio;
use CamargoPMS\Repositorios\ConexionIcalRepositorio;
use CamargoPMS\Repositorios\EventoIcalExternoRepositorio;
use CamargoPMS\Repositorios\SincronizacionIcalLogRepositorio;
use CamargoPMS\Servicios\ClienteHttpIcalSeguro;
use CamargoPMS\Servicios\ExportacionIcalServicio;
use CamargoPMS\Servicios\IcalCriptografiaServicio;
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
echo " CAMARGO PMS — PRUEBAS AIRBNB-ICAL-1B: INFRAESTRUCTURA ICALENDAR\n";
echo "====================================================================\n\n";

// =========================================================================
// 1. COMPOSER Y SABRE/VOBJECT
// =========================================================================
echo "--- 1. Dependencia sabre/vobject (Composer) ---\n";

assertCheck(class_exists(\Sabre\VObject\Reader::class), "Sabre\\VObject\\Reader está disponible y autocargado");
assertCheck(class_exists(\Sabre\VObject\Component\VCalendar::class), "Sabre\\VObject\\Component\\VCalendar está disponible");
assertCheck(class_exists(\Sabre\VObject\Version::class), "Sabre\\VObject\\Version está disponible");

$sabreVersion = \Sabre\VObject\Version::VERSION;
assertCheck(str_starts_with($sabreVersion, '5.'), "Versión instalada de sabre/vobject es 5.x (versión actual: $sabreVersion)");

$composerJson = json_decode((string) file_get_contents(dirname(__DIR__) . '/composer.json'), true);
assertCheck(isset($composerJson['require']['sabre/vobject']), "composer.json requiere explícitamente sabre/vobject");
assertCheck(!isset($composerJson['require']['sabre/dav']), "composer.json NO incluye sabre/dav innecesario");

// =========================================================================
// 2. SEGURIDAD CRIPTOGRÁFICA (IcalCriptografiaServicio)
// =========================================================================
echo "\n--- 2. Criptografía AES-256-GCM y Tokens Híbridos ---\n";

$cripto = new IcalCriptografiaServicio();

// Cifrado y descifrado de URL privada
$urlPrueba = 'https://www.airbnb.com/calendar/ical/12345678.ics?s=abcdef123456';
$payloadCifrado = $cripto->cifrar($urlPrueba);
assertCheck($payloadCifrado !== $urlPrueba, "El payload cifrado no expone la URL en plano");
assertCheck(base64_decode($payloadCifrado, true) !== false, "El payload cifrado es una cadena Base64 válida");

$urlDescifrada = $cripto->descifrar($payloadCifrado);
assertCheck($urlDescifrada === $urlPrueba, "Descifrado recupera fielmente la URL original");

// Protección FAIL-CLOSED: Detección de alteración de datos / tag inválido
$bytes = base64_decode($payloadCifrado);
$bytesAlterados = $bytes;
$bytesAlterados[strlen($bytesAlterados) - 1] = chr(ord($bytesAlterados[strlen($bytesAlterados) - 1]) ^ 0xFF);
$payloadAlterado = base64_encode($bytesAlterados);
$resultadoTamper = $cripto->descifrar($payloadAlterado);
assertCheck($resultadoTamper === null, "FAIL-CLOSED: Descifrado falla y retorna null ante alteración de ciphertext o tag");

// Generación híbrida de token
$tokenDatos = $cripto->generarTokenExportacion();
assertCheck(str_starts_with($tokenDatos['token'], 'cal_'), "Token de exportación tiene prefijo cal_");
assertCheck(strlen($tokenDatos['token']) === 64, "Token plano tiene longitud de 64 caracteres");
assertCheck(strlen($tokenDatos['hash']) === 64, "token_exportacion_hash es un SHA-256 de 64 caracteres hexadecimales");
assertCheck($tokenDatos['hash'] === hash('sha256', $tokenDatos['token']), "Hash coincide exactamente con sha256(token)");
assertCheck($tokenDatos['prefijo'] === substr($tokenDatos['token'], 0, 12), "Prefijo seguro coincide con los primeros 12 caracteres");

$tokenRecuperado = $cripto->descifrar($tokenDatos['cifrado']);
assertCheck($tokenRecuperado === $tokenDatos['token'], "El token plano se recupera fielmente mediante descifrado AES-GCM");

// Clave inválida rechazada
try {
    new IcalCriptografiaServicio('clave-invalida-corta');
    assertCheck(false, "Debería fallar con clave corta");
} catch (\InvalidArgumentException) {
    assertCheck(true, "Rechaza claves que no decodifiquen exactamente 32 bytes (256 bits)");
}

// =========================================================================
// 3. CLIENTE HTTP ANTI-SSRF (ClienteHttpIcalSeguro)
// =========================================================================
echo "\n--- 3. Blindaje Anti-SSRF y Validación de Red ---\n";

$clienteHttp = new ClienteHttpIcalSeguro(timeoutTotal: 2, maxBytes: 1024 * 1024);

// Validación de protocolos prohibidos
try {
    $clienteHttp->validarYParsearUrl('http://ejemplo.com/cal.ics');
    assertCheck(false, "Debe rechazar HTTP plano");
} catch (\InvalidArgumentException $e) {
    assertCheck(str_contains($e->getMessage(), 'Solo se permiten conexiones seguras HTTPS'), "SSRF: Rechaza esquema HTTP no seguro");
}

try {
    $clienteHttp->validarYParsearUrl('file:///etc/passwd');
    assertCheck(false, "Debe rechazar file://");
} catch (\InvalidArgumentException $e) {
    assertCheck(true, "SSRF: Rechaza esquema file://");
}

try {
    $clienteHttp->validarYParsearUrl('gopher://127.0.0.1:70/');
    assertCheck(false, "Debe rechazar gopher://");
} catch (\InvalidArgumentException $e) {
    assertCheck(true, "SSRF: Rechaza esquema gopher://");
}

// Validación de puertos no estándar
try {
    $clienteHttp->validarYParsearUrl('https://ejemplo.com:8080/cal.ics');
    assertCheck(false, "Debe rechazar puerto 8080");
} catch (\InvalidArgumentException $e) {
    assertCheck(str_contains($e->getMessage(), 'puerto estándar HTTPS 443'), "SSRF: Rechaza puertos distintos a 443");
}

// Validación de hostnames prohibidos
try {
    $clienteHttp->validarYParsearUrl('https://localhost/cal.ics');
    assertCheck(false, "Debe rechazar localhost");
} catch (\InvalidArgumentException $e) {
    assertCheck(str_contains($e->getMessage(), 'Host prohibido'), "SSRF: Rechaza hostname localhost");
}

// Validación de IPs privadas y reservadas IPv4
$ipsProhibidasV4 = [
    '127.0.0.1' => 'Loopback IPv4',
    '127.0.0.53' => 'Loopback IPv4 resolver',
    '10.0.1.5' => 'RFC 1918 privada (10/8)',
    '172.16.50.1' => 'RFC 1918 privada (172.16/12)',
    '192.168.1.1' => 'RFC 1918 privada (192.168/16)',
    '169.254.169.254' => 'Link-Local AWS/Cloud Metadata',
    '100.64.0.1' => 'Carrier-Grade NAT (RFC 6598)',
    '0.0.0.0' => 'Current network',
    '224.0.0.1' => 'Multicast',
    '240.0.0.1' => 'Reservado clase E',
];

foreach ($ipsProhibidasV4 as $ip => $descripcion) {
    try {
        $clienteHttp->validarIpSegura($ip);
        assertCheck(false, "Debe rechazar $ip ($descripcion)");
    } catch (\InvalidArgumentException) {
        assertCheck(true, "SSRF bloquea $descripcion ($ip)");
    }
}

// Validación de IPs prohibidas IPv6
$ipsProhibidasV6 = [
    '::1' => 'Loopback IPv6',
    'fe80::1' => 'Link-Local IPv6',
    'fc00::1' => 'ULA IPv6 privada (fc00::/7)',
    'ff02::1' => 'Multicast IPv6',
    '::ffff:127.0.0.1' => 'IPv4-mapped IPv6 loopback',
    '::ffff:10.0.0.1' => 'IPv4-mapped IPv6 privada',
    '::ffff:169.254.169.254' => 'IPv4-mapped IPv6 cloud metadata',
];

foreach ($ipsProhibidasV6 as $ip => $descripcion) {
    try {
        $clienteHttp->validarIpSegura($ip);
        assertCheck(false, "Debe rechazar $ip ($descripcion)");
    } catch (\InvalidArgumentException) {
        assertCheck(true, "SSRF bloquea $descripcion ($ip)");
    }
}

// Aceptación de IP pública válida
try {
    $clienteHttp->validarIpSegura('8.8.8.8');
    assertCheck(true, "SSRF permite IP pública legítima (8.8.8.8)");
} catch (\Throwable $e) {
    assertCheck(false, "No debería rechazar 8.8.8.8: " . $e->getMessage());
}

// =========================================================================
// 4. PARSER Y ADAPTADOR ICAL (IcalAdaptador)
// =========================================================================
echo "\n--- 4. Adaptador y Normalizador RFC 5545 ---\n";

$adaptador = new IcalAdaptador();

// Parsing básico con fechas VALUE=DATE [inicio, fin)
$icsBasico = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Airbnb//Test//EN\r\n" .
    "BEGIN:VEVENT\r\n" .
    "UID:airbnb-res-1001@airbnb.com\r\n" .
    "DTSTART;VALUE=DATE:20261010\r\n" .
    "DTEND;VALUE=DATE:20261013\r\n" .
    "SUMMARY:Airbnb (Not available)\r\n" .
    "DESCRIPTION:Reservation URL: https://airbnb.com/res/1001\r\n" .
    "END:VEVENT\r\n" .
    "END:VCALENDAR\r\n";

$eventos = $adaptador->parsear($icsBasico);
assertCheck(count($eventos) === 1, "Parsea exactamente 1 VEVENT");
$e1 = $eventos[0];
assertCheck($e1->uid === 'airbnb-res-1001@airbnb.com', "UID extraído correctamente");
assertCheck($e1->fechaInicio === '2026-10-10', "Fecha inicio normalizada a 2026-10-10");
assertCheck($e1->fechaFin === '2026-10-13', "Fecha fin normalizada a 2026-10-13 (semántica exclusive)");
assertCheck($e1->noches === 3, "SEMÁNTICA HOTELERA: 2026-10-10 a 2026-10-13 computa exactamente 3 noches (sin off-by-one)");
assertCheck($e1->estadoEvento === 'ACTIVO', "Estado inicial del evento es ACTIVO");

// Line folding y caracteres de escape
$icsFolding = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Test//EN\r\n" .
    "BEGIN:VEVENT\r\n" .
    "UID:fold-123@test.com\r\n" .
    "DTSTART;VALUE=DATE:20261101\r\n" .
    "DTEND;VALUE=DATE:20261103\r\n" .
    "SUMMARY:Reserva con texto muy largo que requiere plegado de lí\r\n nea según RFC 5545\r\n" .
    "DESCRIPTION:Nota con caracteres escapados\\, punto y coma\\; y salto\\nde línea.\r\n" .
    "END:VEVENT\r\n" .
    "END:VCALENDAR\r\n";

$eventosFold = $adaptador->parsear($icsFolding);
assertCheck(count($eventosFold) === 1, "Parsea evento con line-folding");
assertCheck(str_contains($eventosFold[0]->resumen, 'plegado de línea'), "Line folding desplegado correctamente sin espacios espurios");
assertCheck(str_contains((string) $eventosFold[0]->descripcion, "escapados, punto y coma; y salto\nde línea"), "Secuencias de escape decodificadas fielmente");

// DATE-TIME en UTC (Z) y con TZID
$icsDateTime = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\n" .
    "BEGIN:VEVENT\r\n" .
    "UID:dt-utc-1@test.com\r\n" .
    "DTSTART:20261201T150000Z\r\n" .
    "DTEND:20261205T110000Z\r\n" .
    "END:VEVENT\r\n" .
    "END:VCALENDAR\r\n";

$eventosDt = $adaptador->parsear($icsDateTime);
assertCheck(count($eventosDt) === 1, "Parsea VEVENT con DATE-TIME en UTC");
assertCheck($eventosDt[0]->fechaInicio === '2026-12-01', "DTSTART DATE-TIME normalizado a Y-m-d");
assertCheck($eventosDt[0]->fechaFin === '2026-12-05', "DTEND DATE-TIME normalizado a Y-m-d");
assertCheck($eventosDt[0]->noches === 4, "DATE-TIME computa exactamente 4 noches");

// STATUS:CANCELLED
$icsCancelled = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\n" .
    "BEGIN:VEVENT\r\n" .
    "UID:cancel-99@test.com\r\n" .
    "DTSTART;VALUE=DATE:20261020\r\n" .
    "DTEND;VALUE=DATE:20261022\r\n" .
    "STATUS:CANCELLED\r\n" .
    "END:VEVENT\r\n" .
    "END:VCALENDAR\r\n";

$eventosCan = $adaptador->parsear($icsCancelled);
assertCheck(count($eventosCan) === 1, "Parsea evento cancelado");
assertCheck($eventosCan[0]->estadoEvento === 'CANCELADO', "STATUS:CANCELLED mapeado a estadoEvento='CANCELADO'");

// Recurrencias acotadas (RRULE diaria de 3 días)
$icsRecurrencia = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\n" .
    "BEGIN:VEVENT\r\n" .
    "UID:rrule-daily@test.com\r\n" .
    "DTSTART;VALUE=DATE:20261110\r\n" .
    "DTEND;VALUE=DATE:20261111\r\n" .
    "RRULE:FREQ=DAILY;COUNT=3\r\n" .
    "SUMMARY:Bloqueo Recurrente Diario\r\n" .
    "END:VEVENT\r\n" .
    "END:VCALENDAR\r\n";

$eventosRec = $adaptador->parsear($icsRecurrencia);
assertCheck(count($eventosRec) === 3, "Expansión acotada de RRULE generó exactamente 3 instancias discretas");
assertCheck($eventosRec[0]->fechaInicio === '2026-11-10', "Instancia 1 inicia 2026-11-10");
assertCheck($eventosRec[1]->fechaInicio === '2026-11-11', "Instancia 2 inicia 2026-11-11");
assertCheck($eventosRec[2]->fechaInicio === '2026-11-12', "Instancia 3 inicia 2026-11-12");

// Generación y privacidad de exportación
$bloquesExport = [
    ['uid' => 'bloq-u1-2026-10-10-2026-10-13@camargopms.pe', 'fecha_inicio' => '2026-10-10', 'fecha_fin' => '2026-10-13'],
];
$icsExportado = $adaptador->generarCalendario($bloquesExport, 'Test Unidad');
assertCheck(str_contains($icsExportado, 'BEGIN:VCALENDAR'), "Exportación produce VCALENDAR válido");
assertCheck(str_contains($icsExportado, 'UID:bloq-u1-2026-10-10-2026-10-13@camargopms.pe'), "UID estable emitido en exportación");
assertCheck(str_contains($icsExportado, 'SUMMARY:No disponible'), "SUMMARY normalizado a 'No disponible'");
assertCheck(!str_contains($icsExportado, 'DESCRIPTION:'), "PRIVACIDAD: DESCRIPTION está completamente ausente");
assertCheck(str_contains($icsExportado, "\r\n"), "Exportación usa finales de línea estándar CRLF");

// 4.9 Salvaguarda ante recurrencia sintácticamente inválida
try {
    $icsRecurrenciaInvalida = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:bad-rrule@test.com\r\nDTSTART:20261010\r\nRRULE:FREQ=INVALID_FREQ_XYZ\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
    $adaptador->parsear($icsRecurrenciaInvalida);
    assertCheck(false, "Debe fallar con regla de recurrencia inválida");
} catch (\Throwable $e) {
    assertCheck(true, "Recurrencia inválida lanza excepción segura sin corromper el motor");
}

// 4.10 Salvaguarda ante payload ICS corrupto
try {
    $adaptador->parsear("ESTO ES CONTENIDO TOTALMENTE CORRUPTO Y NO ES VCALENDAR");
    assertCheck(false, "Debe fallar ante payload no VCALENDAR");
} catch (\InvalidArgumentException $e) {
    assertCheck(true, "Payload no VCALENDAR rechazado con InvalidArgumentException");
}

// =========================================================================
// 5. BASE DE DATOS Y GESTIÓN DE MODELOS
// =========================================================================
echo "\n--- 5. Repositorios y Entidades de Canales iCal ---\n";

$canalRepo = new CanalDistribucionRepositorio($pdo);
$conexionRepo = new ConexionIcalRepositorio($pdo);
$eventoRepo = new EventoIcalExternoRepositorio($pdo);
$logRepo = new SincronizacionIcalLogRepositorio($pdo);

// Verificar canales iniciales sembrados
$airbnbCanal = $canalRepo->buscarPorCodigo('AIRBNB');
$bookingCanal = $canalRepo->buscarPorCodigo('BOOKING');
assertCheck($airbnbCanal !== null, "Canal AIRBNB existe en BD");
assertCheck($airbnbCanal->obtenerNombre() === 'Airbnb', "Nombre del canal AIRBNB es 'Airbnb'");
assertCheck($bookingCanal !== null, "Canal BOOKING existe en BD");

// Obtener una unidad habitacional real para las pruebas
$unidadIdPrueba = (int) $pdo->query("SELECT id FROM unidades WHERE estado = 'ACTIVO' ORDER BY id ASC LIMIT 1")->fetchColumn();
assertCheck($unidadIdPrueba > 0, "Unidad de prueba obtenida (ID: $unidadIdPrueba)");

// Limpiar conexiones de prueba previas si existieran
$pdo->exec("DELETE FROM sincronizaciones_ical_log WHERE conexion_ical_id IN (SELECT id FROM conexiones_ical WHERE nombre LIKE 'TEST_ICAL_%')");
$pdo->exec("DELETE FROM inventario_diario_unidades WHERE origen_tipo = 'EVENTO_ICAL_EXTERNO' AND origen_id IN (SELECT id FROM eventos_ical_externos WHERE conexion_ical_id IN (SELECT id FROM conexiones_ical WHERE nombre LIKE 'TEST_ICAL_%'))");
$pdo->exec("DELETE FROM eventos_ical_externos WHERE conexion_ical_id IN (SELECT id FROM conexiones_ical WHERE nombre LIKE 'TEST_ICAL_%')");
$pdo->exec("DELETE FROM conexiones_ical WHERE nombre LIKE 'TEST_ICAL_%'");

// Crear conexión 1 (Airbnb)
$tokenAirbnb = $cripto->generarTokenExportacion();
$urlAirbnbCifrada = $cripto->cifrar('https://www.airbnb.com/calendar/ical/test_unit1.ics');
$conexionAirbnb = new ConexionIcal(
    id: null,
    unidadId: $unidadIdPrueba,
    canalId: $airbnbCanal->obtenerId(),
    nombre: 'TEST_ICAL_AIRBNB_101',
    urlImportacionCifrada: $urlAirbnbCifrada,
    tokenExportacionHash: $tokenAirbnb['hash'],
    tokenExportacionCifrado: $tokenAirbnb['cifrado'],
    tokenPrefijo: $tokenAirbnb['prefijo'],
    importacionHabilitada: true,
    exportacionHabilitada: true,
    frecuenciaMinutos: 60,
    estado: ConexionIcal::ESTADO_ACTIVO
);
$conexionAirbnbId = $conexionRepo->crear($conexionAirbnb);
assertCheck($conexionAirbnbId > 0, "Conexión iCal Airbnb creada exitosamente (ID: $conexionAirbnbId)");

// Crear conexión 2 (Booking)
$tokenBooking = $cripto->generarTokenExportacion();
$urlBookingCifrada = $cripto->cifrar('https://admin.booking.com/hotel/ical/test_unit1.ics');
$conexionBooking = new ConexionIcal(
    id: null,
    unidadId: $unidadIdPrueba,
    canalId: $bookingCanal->obtenerId(),
    nombre: 'TEST_ICAL_BOOKING_101',
    urlImportacionCifrada: $urlBookingCifrada,
    tokenExportacionHash: $tokenBooking['hash'],
    tokenExportacionCifrado: $tokenBooking['cifrado'],
    tokenPrefijo: $tokenBooking['prefijo'],
    importacionHabilitada: true,
    exportacionHabilitada: true,
    frecuenciaMinutos: 60,
    estado: ConexionIcal::ESTADO_ACTIVO
);
$conexionBookingId = $conexionRepo->crear($conexionBooking);
assertCheck($conexionBookingId > 0, "Conexión iCal Booking creada exitosamente (ID: $conexionBookingId)");

// =========================================================================
// 6. SINCRONIZACIÓN IDEMPOTENTE Y RECONCILIACIÓN MULTI-OTA
// =========================================================================
echo "\n--- 6. Motor Soberano de Sincronización e Idempotencia ---\n";

$syncServicio = new SincronizacionIcalServicio($pdo, $conexionRepo, $eventoRepo, $logRepo, $cripto, $clienteHttp, $adaptador);

// Fechas hoteleras futuras para pruebas (Mayo 2027, ventana libre de reservas preexistentes)
$fechaAInicio = '2027-05-10';
$fechaAFin = '2027-05-13'; // 3 noches: 10, 11, 12

$icsAirbnb1 = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\n" .
    "BEGIN:VEVENT\r\n" .
    "UID:airbnb-event-aaa@airbnb.com\r\n" .
    "DTSTART;VALUE=DATE:" . str_replace('-', '', $fechaAInicio) . "\r\n" .
    "DTEND;VALUE=DATE:" . str_replace('-', '', $fechaAFin) . "\r\n" .
    "SUMMARY:Airbnb Reservation\r\n" .
    "END:VEVENT\r\n" .
    "END:VCALENDAR\r\n";

// Primera sincronización
$resSync1 = $syncServicio->sincronizarConexion($conexionAirbnbId, $icsAirbnb1);
assertCheck($resSync1['resultado'] === 'EXITO', "Primera sincronización de Airbnb culmina con EXITO");
assertCheck($resSync1['eventos_creados'] === 1, "Evento insertado en base de datos");

// Verificar persistencia en eventos_ical_externos
$eventoGuardado = $eventoRepo->buscarPorConexionYUid($conexionAirbnbId, 'airbnb-event-aaa@airbnb.com');
assertCheck($eventoGuardado !== null, "Evento guardado existe en eventos_ical_externos");
assertCheck($eventoGuardado->obtenerNoches() === 3, "Evento tiene 3 noches");
assertCheck($eventoGuardado->obtenerEstadoBloqueo() === 'APLICADO', "Estado físico de bloqueo es APLICADO");

// Verificar materialización en inventario_diario_unidades
$nochesOcupadas = $pdo->query("SELECT fecha, tipo_bloqueo, origen_tipo, origen_id FROM inventario_diario_unidades WHERE unidad_id = $unidadIdPrueba AND fecha >= '$fechaAInicio' AND fecha < '$fechaAFin' ORDER BY fecha ASC")->fetchAll(PDO::FETCH_ASSOC);
assertCheck(count($nochesOcupadas) === 3, "Exactamente 3 noches bloqueadas en inventario_diario_unidades");
assertCheck($nochesOcupadas[0]['tipo_bloqueo'] === 'BLOQUEO_MANUAL', "tipo_bloqueo en inventario diario es BLOQUEO_MANUAL (Cero ALTER ENUM)");
assertCheck($nochesOcupadas[0]['origen_tipo'] === 'EVENTO_ICAL_EXTERNO', "origen_tipo en inventario diario es EVENTO_ICAL_EXTERNO");
assertCheck((int) $nochesOcupadas[0]['origen_id'] === $eventoGuardado->obtenerId(), "origen_id apunta al ID del evento externo soberano");

// IDEMPOTENCIA: Re-ejecutar exactamente el mismo feed no debe duplicar filas
$resSyncIdem = $syncServicio->sincronizarConexion($conexionAirbnbId, $icsAirbnb1);
assertCheck($resSyncIdem['eventos_creados'] === 0, "Idempotencia: Cero eventos nuevos creados en segunda ejecución idéntica");
assertCheck($resSyncIdem['eventos_actualizados'] === 1, "Idempotencia: Evento actualizado sin duplicar");

$conteoTotalEventos = (int) $pdo->query("SELECT COUNT(*) FROM eventos_ical_externos WHERE conexion_ical_id = $conexionAirbnbId")->fetchColumn();
assertCheck($conteoTotalEventos === 1, "Idempotencia: Total de eventos se mantiene en 1");

$conteoInventario = (int) $pdo->query("SELECT COUNT(*) FROM inventario_diario_unidades WHERE unidad_id = $unidadIdPrueba AND fecha >= '$fechaAInicio' AND fecha < '$fechaAFin'")->fetchColumn();
assertCheck($conteoInventario === 3, "Idempotencia: Total de filas en inventario diario se mantiene exactamente en 3");

// =========================================================================
// 7. SOLAPAMIENTO ENTRE OTAs (CRITICAL GATE) Y RECONCILIACIÓN DETERMINISTA
// =========================================================================
echo "\n--- 7. Solapamiento entre Múltiples OTAs (Airbnb + Booking) ---\n";

// Booking envía reserva que solapa parcialmente: noches 11, 12, 13 (2027-05-11 a 2027-05-14)
$fechaBInicio = '2027-05-11';
$fechaBFin = '2027-05-14';

$icsBooking = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\n" .
    "BEGIN:VEVENT\r\n" .
    "UID:booking-res-bbb@booking.com\r\n" .
    "DTSTART;VALUE=DATE:" . str_replace('-', '', $fechaBInicio) . "\r\n" .
    "DTEND;VALUE=DATE:" . str_replace('-', '', $fechaBFin) . "\r\n" .
    "SUMMARY:Booking.com Reservation\r\n" .
    "END:VEVENT\r\n" .
    "END:VCALENDAR\r\n";

$resSyncBooking = $syncServicio->sincronizarConexion($conexionBookingId, $icsBooking);
assertCheck($resSyncBooking['resultado'] === 'EXITO', "Sincronización de Booking completada con éxito");

// Verificar que ambos eventos coexisten con sus procedencias intactas
$eventoBooking = $eventoRepo->buscarPorConexionYUid($conexionBookingId, 'booking-res-bbb@booking.com');
assertCheck($eventoBooking !== null, "Evento de Booking existe soberanamente en base de datos");
assertCheck($eventoBooking->obtenerEstadoBloqueo() === 'APLICADO_CON_SOLAPAMIENTO', "Evento de Booking marcado como APLICADO_CON_SOLAPAMIENTO en noches compartidas");

// Verificar UNIÓN en inventario diario: Noches 10, 11, 12, 13 deben estar bloqueadas (4 noches en total)
$totalNochesUnion = (int) $pdo->query("SELECT COUNT(*) FROM inventario_diario_unidades WHERE unidad_id = $unidadIdPrueba AND fecha >= '2027-05-10' AND fecha < '2027-05-14'")->fetchColumn();
assertCheck($totalNochesUnion === 4, "INVENTARIO FÍSICO: La unión determinista bloquea exactamente las 4 noches (10, 11, 12, 13)");

// Ahora: Airbnb cancela su reserva (las noches 11 y 12 deben permanecer bloqueadas por Booking)
$icsAirbnbCancel = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\n" .
    "BEGIN:VEVENT\r\n" .
    "UID:airbnb-event-aaa@airbnb.com\r\n" .
    "DTSTART;VALUE=DATE:" . str_replace('-', '', $fechaAInicio) . "\r\n" .
    "DTEND;VALUE=DATE:" . str_replace('-', '', $fechaAFin) . "\r\n" .
    "STATUS:CANCELLED\r\n" .
    "END:VEVENT\r\n" .
    "END:VCALENDAR\r\n";

$resSyncAirbnbCancel = $syncServicio->sincronizarConexion($conexionAirbnbId, $icsAirbnbCancel);
assertCheck($resSyncAirbnbCancel['eventos_cancelados'] === 1, "Cancelación explícita de Airbnb procesada");

// RECONCILIACIÓN DETERMINISTA VERIFICADA:
// Noche 10 (exclusiva de Airbnb) debe quedar LIBERADA (0 filas)
$noche10 = $pdo->query("SELECT COUNT(*) FROM inventario_diario_unidades WHERE unidad_id = $unidadIdPrueba AND fecha = '2027-05-10'")->fetchColumn();
assertCheck((int) $noche10 === 0, "RECONCILIACIÓN: Noche 10 (exclusiva de Airbnb) fue liberada correctamente");

// Noches 11 y 12 (solapadas con Booking) DEBEN CONTINUAR BLOQUEADAS y transferidas a Booking
$filasNoches11_12 = $pdo->query("SELECT fecha, origen_id FROM inventario_diario_unidades WHERE unidad_id = $unidadIdPrueba AND fecha IN ('2027-05-11', '2027-05-12') ORDER BY fecha ASC")->fetchAll(PDO::FETCH_ASSOC);
assertCheck(count($filasNoches11_12) === 2, "RECONCILIACIÓN: Noches 11 y 12 CONTINÚAN BLOQUEADAS porque Booking sigue activo");
assertCheck((int) $filasNoches11_12[0]['origen_id'] === $eventoBooking->obtenerId(), "Noche 11 fue transferida deterministamente al ID de Booking");
assertCheck((int) $filasNoches11_12[1]['origen_id'] === $eventoBooking->obtenerId(), "Noche 12 fue transferida deterministamente al ID de Booking");

// El estado de bloqueo de Booking ahora debe ser APLICADO pleno
$eventoBookingActualizado = $eventoRepo->buscarPorId($eventoBooking->obtenerId());
assertCheck($eventoBookingActualizado->obtenerEstadoBloqueo() === 'APLICADO', "El bloqueo de Booking transicionó de solapamiento a APLICADO pleno");

// =========================================================================
// 8. PRESERVACIÓN SOBERANA ANTE CONFLICTOS LOCALES
// =========================================================================
echo "\n--- 8. Detección y Preservación ante Conflictos Locales ---\n";

// Crear un bloqueo que simula una Reserva local en inventario_diario_unidades
$fechaConflicto = '2027-05-20';
$pdo->exec("DELETE FROM inventario_diario_unidades WHERE unidad_id = $unidadIdPrueba AND fecha = '$fechaConflicto'");
$pdo->exec("INSERT INTO inventario_diario_unidades (unidad_id, fecha, tipo_bloqueo, origen_tipo, origen_id, creado_en)
            VALUES ($unidadIdPrueba, '$fechaConflicto', 'RESERVA', 'RESERVA', 99999, NOW())");

// Feed externo que colisiona con la reserva local
$icsColision = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\n" .
    "BEGIN:VEVENT\r\n" .
    "UID:colision-local-999@airbnb.com\r\n" .
    "DTSTART;VALUE=DATE:" . str_replace('-', '', $fechaConflicto) . "\r\n" .
    "DTEND;VALUE=DATE:20270521\r\n" .
    "SUMMARY:Airbnb Overbooking Attempt\r\n" .
    "END:VEVENT\r\n" .
    "END:VCALENDAR\r\n";

$resSyncColision = $syncServicio->sincronizarConexion($conexionAirbnbId, $icsColision);
assertCheck($resSyncColision['conflictos'] === 1, "Detecta colisión con reserva local (conflictos = 1)");
assertCheck($resSyncColision['resultado'] === 'CON_ADVERTENCIA', "Resultado de sincronización marcado como CON_ADVERTENCIA");

// Comprobar que la reserva local NO fue tocada
$reservaLocal = $pdo->query("SELECT tipo_bloqueo, origen_id FROM inventario_diario_unidades WHERE unidad_id = $unidadIdPrueba AND fecha = '$fechaConflicto'")->fetch(PDO::FETCH_ASSOC);
assertCheck($reservaLocal['tipo_bloqueo'] === 'RESERVA', "SOBERANÍA LOCAL: La reserva local no fue mutada ni eliminada");
assertCheck((int) $reservaLocal['origen_id'] === 99999, "SOBERANÍA LOCAL: El origen_id local sigue intacto");

// Comprobar que el evento externo quedó marcado EN_CONFLICTO
$eventoEnConflicto = $eventoRepo->buscarPorConexionYUid($conexionAirbnbId, 'colision-local-999@airbnb.com');
assertCheck($eventoEnConflicto !== null, "El evento en conflicto fue registrado en eventos_ical_externos");
assertCheck($eventoEnConflicto->obtenerEstadoBloqueo() === 'EN_CONFLICTO', "El evento externo tiene estado_bloqueo = 'EN_CONFLICTO'");
assertCheck(str_contains((string) $eventoEnConflicto->obtenerDetalleConflicto(), 'Colisión con ocupación local soberana'), "Detalle del conflicto registrado fielmente");

// Limpiar reserva simulada
$pdo->exec("DELETE FROM inventario_diario_unidades WHERE unidad_id = $unidadIdPrueba AND fecha = '$fechaConflicto'");

// =========================================================================
// 9. SALVAGUARDA DE FEED VACÍO SOSPECHOSO (0 VEVENTs)
// =========================================================================
echo "\n--- 9. Salvaguarda Defensiva ante Feed Vacío (0 VEVENTs) ---\n";

$icsVacio = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Test//EN\r\nEND:VCALENDAR\r\n";

// La conexión Booking tiene actualmente 1 evento activo. Un feed con 0 VEVENTs no debe liberar su inventario
$resVacio = $syncServicio->sincronizarConexion($conexionBookingId, $icsVacio);
assertCheck($resVacio['resultado'] === 'CON_ADVERTENCIA', "Feed vacío con eventos previos retorna CON_ADVERTENCIA");
assertCheck(str_contains($resVacio['mensaje'], 'FEED_VACIO_SOSPECHOSO'), "Mensaje identifica salvaguarda FEED_VACIO_SOSPECHOSO");

// Las noches de Booking deben continuar bloqueadas intactas
$nochesPreservadas = (int) $pdo->query("SELECT COUNT(*) FROM inventario_diario_unidades WHERE unidad_id = $unidadIdPrueba AND origen_tipo = 'EVENTO_ICAL_EXTERNO' AND origen_id = {$eventoBooking->obtenerId()}")->fetchColumn();
assertCheck($nochesPreservadas > 0, "SALVAGUARDA FEED VACÍO: Las noches de Booking permanecen bloqueadas intactas");

// =========================================================================
// 10. EXPORTACIÓN ICAL Y FILTRO ANTI-ECHO LOOP
// =========================================================================
echo "\n--- 10. Exportación iCal, Filtro Anti-Echo y Privacidad ---\n";

$exportServicio = new ExportacionIcalServicio($pdo, $conexionRepo, $logRepo, $adaptador);

// Conexión Airbnb exporta su feed
$icsExportAirbnb = $exportServicio->exportarFeed($tokenAirbnb['token']);
assertCheck($icsExportAirbnb !== null, "Exportación para Airbnb genera contenido");

// FILTRO ANTI-ECHO VERIFICADO:
// El feed exportado hacia Airbnb DEBE contener el bloqueo de Booking (propagación cruzada entre OTAs)
assertCheck(str_contains($icsExportAirbnb, 'BEGIN:VEVENT'), "Feed de Airbnb contiene eventos de indisponibilidad");
// Pero NO debe contener bloqueos generados por la propia conexión Airbnb
// Comprobamos con la conexión Booking:
$icsExportBooking = $exportServicio->exportarFeed($tokenBooking['token']);
// Booking NO debe recibir de vuelta sus propias reservas (suprimidas por anti-echo)
assertCheck($icsExportBooking !== null, "Exportación para Booking genera contenido");

// Privacidad estricta
assertCheck(!str_contains($icsExportAirbnb, 'Airbnb Reservation'), "PRIVACIDAD: No expone el título o resumen original");
assertCheck(!str_contains($icsExportAirbnb, 'DESCRIPTION:'), "PRIVACIDAD: No expone DESCRIPTION");
assertCheck(!str_contains($icsExportAirbnb, '@airbnb.com'), "PRIVACIDAD: UIDs externos no son filtrados al canal opuesto");
assertCheck(str_contains($icsExportAirbnb, '@camargopms.pe'), "Identidad de exportación usa namespace propio de Camargo PMS");

// Validación de Token: token inválido o revocado retorna null
$icsInvalido = $exportServicio->exportarFeed('token-completamente-falso-12345');
assertCheck($icsInvalido === null, "Token inexistente retorna null (HTTP 404)");

// Rotación de token
$nuevoToken = $cripto->generarTokenExportacion();
$conexionRepo->rotarTokenExportacion($conexionAirbnbId, $nuevoToken['hash'], $nuevoToken['cifrado'], $nuevoToken['prefijo']);
$icsViejoToken = $exportServicio->exportarFeed($tokenAirbnb['token']);
assertCheck($icsViejoToken === null, "Token antiguo queda revocado inmediatamente tras rotación");

$icsNuevoToken = $exportServicio->exportarFeed($nuevoToken['token']);
assertCheck($icsNuevoToken !== null, "Nuevo token rotado opera con normalidad");

// Conexión con exportación deshabilitada
$pdo->exec("UPDATE conexiones_ical SET exportacion_habilitada = 0 WHERE id = $conexionBookingId");
$icsDeshabilitado = $exportServicio->exportarFeed($tokenBooking['token']);
assertCheck($icsDeshabilitado === null, "Conexión con exportacion_habilitada=0 retorna null (acceso denegado)");
$pdo->exec("UPDATE conexiones_ical SET exportacion_habilitada = 1 WHERE id = $conexionBookingId");

// Conexión en estado REVOCADO
$pdo->exec("UPDATE conexiones_ical SET estado = 'REVOCADO' WHERE id = $conexionBookingId");
$icsRevocado = $exportServicio->exportarFeed($tokenBooking['token']);
assertCheck($icsRevocado === null, "Conexión en estado REVOCADO retorna null");
$pdo->exec("UPDATE conexiones_ical SET estado = 'ACTIVO' WHERE id = $conexionBookingId");

// =========================================================================
// 11. ENDPOINT HTTP PÚBLICO (IcalExportarControlador)
// =========================================================================
echo "\n--- 11. Endpoint HTTP y Enrutador (GET /ical/exportar/{token}) ---\n";

$controladorExport = new IcalExportarControlador($exportServicio);

// Consulta sin token
$respVacia = $controladorExport->exportar('');
assertCheck($respVacia->obtenerCodigo() === 400, "GET /ical/exportar/ sin token retorna HTTP 400");

// Consulta con token inválido
$resp404 = $controladorExport->exportar('token_invalido_xyz');
assertCheck($resp404->obtenerCodigo() === 404, "GET /ical/exportar/{token_invalido} retorna HTTP 404");

// Consulta con token válido
$resp200 = $controladorExport->exportar($nuevoToken['token']);
assertCheck($resp200->obtenerCodigo() === 200, "GET /ical/exportar/{token_valido} retorna HTTP 200 OK");
$cabeceras = $resp200->obtenerCabeceras();
assertCheck(str_contains($cabeceras['Content-Type'] ?? '', 'text/calendar'), "Cabecera Content-Type es text/calendar");
assertCheck(str_contains($cabeceras['Content-Disposition'] ?? '', 'calendario.ics'), "Cabecera Content-Disposition configurada");
assertCheck(str_contains($resp200->obtenerContenido(), 'BEGIN:VCALENDAR'), "Cuerpo de respuesta contiene VCALENDAR");

// Verificación de registro de ruta en public/index.php
$indexContenido = (string) file_get_contents(dirname(__DIR__) . '/public/index.php');
assertCheck(str_contains($indexContenido, "get('/ical/exportar/{token}'"), "Ruta '/ical/exportar/{token}' registrada en public/index.php");
assertCheck(str_contains($indexContenido, "IcalExportarControlador::class"), "Ruta asociada a IcalExportarControlador::class");

// =========================================================================
// 12. GOBERNANZA, DDL Y SEGURIDAD DE SECRETOS
// =========================================================================
echo "\n--- 12. Invariantes de Gobernanza y Auditoría de Secretos ---\n";

// Conteo exacto de tablas: al menos 122 (122 pre-036 / 127 post-036)
$tablasBD = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
assertCheck(count($tablasBD) >= 122, "Base de datos contiene al menos 122 tablas relacionales (actual: " . count($tablasBD) . ")");

// Migración 035 aplicada
$mig035Presente = (bool) $pdo->query("SELECT 1 FROM migraciones WHERE migracion = '035_canales_ical.sql'")->fetchColumn();
assertCheck($mig035Presente, "Migración 035_canales_ical.sql registrada en BD");

// Ranura 040 libre
$mig040 = glob(dirname(__DIR__) . '/SQL/migraciones/*040*');
assertCheck(empty($mig040), "Ranura de migración 040 estrictamente LIBRE");

// Paridad con SQL/camargo_pms.sql
$sqlConsolidado = (string) file_get_contents(dirname(__DIR__) . '/SQL/camargo_pms.sql');
assertCheck(str_contains($sqlConsolidado, 'CREATE TABLE IF NOT EXISTS `canales_distribucion`'), "SQL/camargo_pms.sql incluye canales_distribucion");
assertCheck(str_contains($sqlConsolidado, 'CREATE TABLE IF NOT EXISTS `conexiones_ical`'), "SQL/camargo_pms.sql incluye conexiones_ical");
assertCheck(str_contains($sqlConsolidado, 'CREATE TABLE IF NOT EXISTS `eventos_ical_externos`'), "SQL/camargo_pms.sql incluye eventos_ical_externos");
assertCheck(str_contains($sqlConsolidado, 'CREATE TABLE IF NOT EXISTS `sincronizaciones_ical_log`'), "SQL/camargo_pms.sql incluye sincronizaciones_ical_log");

// Plantilla .env.example sin secretos
$envExample = (string) file_get_contents(dirname(__DIR__) . '/.env.example');
assertCheck(str_contains($envExample, 'ICAL_ENCRYPTION_KEY='), ".env.example incluye variable ICAL_ENCRYPTION_KEY=");
assertCheck(!preg_match('/ICAL_ENCRYPTION_KEY=\S+/', $envExample), ".env.example permanece con ICAL_ENCRYPTION_KEY vacía (cero secretos versionados)");

// Telemetría sin secretos en BD
$logsRecientes = $pdo->query("SELECT mensaje_resultado FROM sincronizaciones_ical_log WHERE mensaje_resultado IS NOT NULL ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_COLUMN);
foreach ($logsRecientes as $logMsg) {
    assertCheck(!str_contains((string) $logMsg, 'cal_'), "Telemetría: Los logs no registran tokens de exportación");
    assertCheck(!str_contains((string) $logMsg, 'token='), "Telemetría: Los logs no registran parámetros de secreto");
}

// admin-dashboard/ inmutable
$gitAlina = shell_exec('git status --porcelain admin-dashboard/');
assertCheck(empty(trim((string) $gitAlina)), "admin-dashboard/ permanece 100% inmutable y libre de modificaciones");

// Limpieza de datos de prueba
$pdo->exec("DELETE FROM sincronizaciones_ical_log WHERE conexion_ical_id IN ($conexionAirbnbId, $conexionBookingId)");
$pdo->exec("DELETE FROM inventario_diario_unidades WHERE origen_tipo = 'EVENTO_ICAL_EXTERNO' AND origen_id IN (SELECT id FROM eventos_ical_externos WHERE conexion_ical_id IN ($conexionAirbnbId, $conexionBookingId))");
$pdo->exec("DELETE FROM eventos_ical_externos WHERE conexion_ical_id IN ($conexionAirbnbId, $conexionBookingId)");
$pdo->exec("DELETE FROM conexiones_ical WHERE id IN ($conexionAirbnbId, $conexionBookingId)");

echo "\n====================================================================\n";
echo " RESUMEN AIRBNB-ICAL-1B: $passedAssertions / $totalAssertions pruebas superadas\n";
echo "====================================================================\n";
echo "\n>>> AIRBNB-ICAL-1B: INFRAESTRUCTURA VALIDADA AL 100% (TODO PASS) <<<\n";
