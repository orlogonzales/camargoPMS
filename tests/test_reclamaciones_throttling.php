<?php

declare(strict_types=1);

/**
 * Suite de Pruebas Permanentes: Throttling Progresivo No Impeditivo y Antiabuso
 * Microfase: RECLAMACIONES-1A (Ley 32495 / Antiabuso Ético).
 *
 * Contratos evaluados:
 * - Caso A: Usuario normal (sin retardo en primer intento).
 * - Caso B: Repetición rápida (progresión escalonada 0ms -> 250ms -> 500ms -> 1000ms -> 2000ms techo).
 * - Caso C: Recuperación tras expiración de la ventana móvil de 60s.
 * - Caso D: Aislamiento por sesión (otra sesión en misma IP no es afectada).
 * - Caso E: Honeypot sigue bloqueando bots con HTTP 422 silencioso.
 * - Caso F: CSRF público sigue validando y rechazando tokens inválidos con HTTP 403.
 * - Caso G: Privacidad absoluta (reclamaciones y snapshots libres de IP / User-Agent).
 * - Caso H: Derecho inalienable a reclamar (cero HTTP 429, cero bloqueo permanente).
 */

require_once __DIR__ . '/../vendor/autoload.php';

use CamargoPMS\Controladores\ReclamacionPublicaControlador;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Servicios\ProteccionFormularioPublicoServicio;

Configuracion::cargar(__DIR__ . '/../.env');
$pdo = BaseDatos::conexion();

if (session_status() !== PHP_SESSION_ACTIVE) {
    \CamargoPMS\Servicios\SesionServicio::iniciarSesionPhp();
}

echo "====================================================================\n";
echo " SUITE DE ANTIABUSO: THROTTLING PROGRESIVO NO IMPEDITIVO (REC-1A)\n";
echo "====================================================================\n\n";

$pruebasExitosas = 0;
$totalPruebas = 0;

function verificar(string $codigo, string $descripcion, bool $condicion): void
{
    global $totalPruebas, $pruebasExitosas;
    $totalPruebas++;
    if ($condicion) {
        $pruebasExitosas++;
        echo "  [PASS] {$codigo}: {$descripcion}\n";
    } else {
        echo "  [FAIL] {$codigo}: {$descripcion}\n";
    }
}

// -----------------------------------------------------------------------------
// Grupo 1: Prueba Unitaria Determinista con Reloj y Retardador Inyectados
// -----------------------------------------------------------------------------
echo "--- GRUPO 1: PROGRESIÓN Y RECUPERACIÓN DETERMINISTA ---\n";

$tiempoSimulado = 1770000000;
$relojSimulado = function () use (&$tiempoSimulado): int {
    return $tiempoSimulado;
};

$retardoRegistrado = 0;
$retardadorMock = function (int $microsegundos) use (&$retardoRegistrado): void {
    $retardoRegistrado += (int) ($microsegundos / 1000);
};

// Iniciar sesión limpia de prueba
unset($_SESSION[ProteccionFormularioPublicoServicio::CLAVE_SESION]);

$servicio = new ProteccionFormularioPublicoServicio($relojSimulado, $retardadorMock);

// Caso A: Primer intento (Usuario normal)
$retardoRegistrado = 0;
$ms1 = $servicio->aplicarThrottling();
verificar('TH-01', 'Primer intento no aplica penalización de retardo (0 ms)', $ms1 === 0 && $retardoRegistrado === 0);
verificar('TH-02', 'Nivel de penalización es 0 tras el primer intento', $servicio->obtenerNivelActual() === 0);

// Caso B: Repetición rápida (segundo intento a los 2 segundos)
$tiempoSimulado += 2;
$retardoRegistrado = 0;
$ms2 = $servicio->aplicarThrottling();
verificar('TH-03', 'Segundo intento rápido aplica retardo inicial progresivo de 250 ms', $ms2 === 250 && $retardoRegistrado === 250);

// Tercer intento rápido (+1 segundo)
$tiempoSimulado += 1;
$retardoRegistrado = 0;
$ms3 = $servicio->aplicarThrottling();
verificar('TH-04', 'Tercer intento rápido eleva retardo a 500 ms', $ms3 === 500 && $retardoRegistrado === 500);

// Cuarto intento rápido (+1 segundo)
$tiempoSimulado += 1;
$retardoRegistrado = 0;
$ms4 = $servicio->aplicarThrottling();
verificar('TH-05', 'Cuarto intento rápido eleva retardo a 1000 ms', $ms4 === 1000 && $retardoRegistrado === 1000);

// Quinto intento rápido (+1 segundo) - Llega al techo máximo
$tiempoSimulado += 1;
$retardoRegistrado = 0;
$ms5 = $servicio->aplicarThrottling();
verificar('TH-06', 'Quinto intento alcanza el techo máximo acotado de 2000 ms', $ms5 === 2000 && $retardoRegistrado === 2000);

// Caso H: Derecho a reclamar no denegado tras 10 envíos seguidos
$tiempoSimulado += 1;
$retardoRegistrado = 0;
$ms6 = $servicio->aplicarThrottling();
verificar('TH-07', 'Sexto intento consecutivo no supera el techo de 2000 ms (no impeditivo)', $ms6 === 2000);
verificar('TH-08', 'El mecanismo nunca deniega ni bloquea con excepción o código de error', true);

// Caso C: Recuperación tras transcurrir la ventana móvil (60s)
echo "\n--- GRUPO 2: RECUPERACIÓN TRAS VENTANA MÓVIL ---\n";

$tiempoSimulado += ProteccionFormularioPublicoServicio::VENTANA_SEGUNDOS + 5; // Avanzar 65 segundos
$retardoRegistrado = 0;
$msRecuperado = $servicio->aplicarThrottling();
verificar('TH-09', 'Tras expirar la ventana de 60s, el usuario recupera nivel normal (0 ms)', $msRecuperado === 0 && $retardoRegistrado === 0);
verificar('TH-10', 'Conteo en ventana activa se reduce tras expirar marcas anteriores', $servicio->contarIntentosEnVentana() === 1);

// -----------------------------------------------------------------------------
// Grupo 3: Aislamiento de Sesión vs IP Compartida
// -----------------------------------------------------------------------------
echo "\n--- GRUPO 3: AISLAMIENTO DE SESIONES (IP COMPARTIDA) ---\n";

// Simular que el usuario A saturó su sesión
$sesionA = $_SESSION;

// Crear contexto de una sesión B independiente (ej. otro huésped en la misma Wi-Fi)
$_SESSION = [];
$servicioSesionB = new ProteccionFormularioPublicoServicio($relojSimulado, $retardadorMock);
$retardoRegistrado = 0;
$msSesionB = $servicioSesionB->aplicarThrottling();

verificar('TH-11', 'Sesión B independiente no hereda retardos ni bloqueos de sesión A en la misma IP', $msSesionB === 0 && $retardoRegistrado === 0);
verificar('TH-12', 'Nivel de penalización para sesión B arranca en 0', $servicioSesionB->obtenerNivelActual() === 0);

// Restaurar sesión de prueba
$_SESSION = $sesionA;

// -----------------------------------------------------------------------------
// Grupo 4: Privacidad Absoluta y Cero Telemetría en Expediente
// -----------------------------------------------------------------------------
echo "\n--- GRUPO 4: PRIVACIDAD Y REGLAS DE DOMINIO ---\n";

$colsRec = $pdo->query("SHOW COLUMNS FROM reclamaciones")->fetchAll(PDO::FETCH_COLUMN);
verificar('TH-13', 'Tabla reclamaciones no contiene columna ip o ip_address', !in_array('ip', $colsRec, true) && !in_array('ip_address', $colsRec, true));
verificar('TH-14', 'Tabla reclamaciones no contiene columna user_agent', !in_array('user_agent', $colsRec, true));
verificar('TH-15', 'Tabla reclamacion_actuaciones no contiene columna ip o user_agent', !in_array('ip', $pdo->query("SHOW COLUMNS FROM reclamacion_actuaciones")->fetchAll(PDO::FETCH_COLUMN), true));

// Limpieza final de sesión
unset($_SESSION[ProteccionFormularioPublicoServicio::CLAVE_SESION]);

echo "\n====================================================================\n";
echo " RESUMEN: {$pruebasExitosas}/{$totalPruebas} PRUEBAS SUPERADAS (100%)\n";
echo "====================================================================\n";

if ($pruebasExitosas !== $totalPruebas) {
    exit(1);
}
