<?php

declare(strict_types=1);

/**
 * Suite de Pruebas: FINANCIERO-3A — Base de Datos y Modelo de Dominio Multi-Folio
 *
 * Valida:
 * 1. Conteo exacto de 131 tablas relacionales y presencia de la migración 039.
 * 2. Integridad del esquema: cuentas_folios (columnas virtuales, es_principal, etiqueta, folio_padre_id),
 *    cargos_cuenta (cargo_padre_id) y cuenta_folio_transferencias_cargos.
 * 3. Preservación retroactiva de folios históricos como folios principales.
 * 4. Garantía a nivel de motor de un único folio principal activo por reserva/arrendamiento (MySQL 1062).
 * 5. Apertura permitida de N folios secundarios (es_principal = 0) bajo la misma reserva.
 * 6. Consultas de repositorio y compatibilidad hacia atrás (obtenerPorReservaId, listarPorReservaId).
 * 7. Modelos de dominio y serialización de CuentaFolio, CargoCuenta y CuentaFolioTransferenciaCargo.
 * 8. Restricciones relacionales y CHECKs en la tabla de transferencias (chk_trc_folios_distintos, etc.).
 * 9. Servicio de dominio CuentaFolioServicio: crearFolioSecundario y validación de invariantes.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Modelos\CuentaFolio;
use CamargoPMS\Modelos\CargoCuenta;
use CamargoPMS\Modelos\CuentaFolioTransferenciaCargo;
use CamargoPMS\Repositorios\CuentaFolioRepositorio;
use CamargoPMS\Repositorios\CargoCuentaRepositorio;
use CamargoPMS\Repositorios\CuentaFolioTransferenciaRepositorio;
use CamargoPMS\Servicios\CuentaFolioServicio;
use CamargoPMS\Excepciones\EstadoFinancieroInvalidoExcepcion;
use CamargoPMS\Excepciones\CuentaFolioNoEncontradaExcepcion;

Configuracion::cargar(dirname(__DIR__));
$pdo = BaseDatos::conexion();

$totalChecks = 0;
$passedChecks = 0;
$failedChecks = 0;

function verificar(string $descripcion, bool $condicion): void
{
    global $totalChecks, $passedChecks, $failedChecks;
    $totalChecks++;
    if ($condicion) {
        $passedChecks++;
        echo "  [PASS] {$descripcion}\n";
    } else {
        $failedChecks++;
        echo "  [FAIL] {$descripcion}\n";
    }
}

echo "====================================================================\n";
echo " EJECUTANDO SUITE: test_multifolio_split_3a.php (FINANCIERO-3A)\n";
echo "====================================================================\n\n";

// --------------------------------------------------------------------
// BLOQUE 1: Estructura de Base de Datos y Ranura 039
// --------------------------------------------------------------------
echo "--- BLOQUE 1: Estructura de Base de Datos y Ranura 039 ---\n";

$tablas = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
verificar("Total de tablas relacionales en MySQL es exactamente 131", count($tablas) === 131);
verificar("Tabla 'cuenta_folio_transferencias_cargos' existe en el motor", in_array('cuenta_folio_transferencias_cargos', $tablas, true));

$stmtMig = $pdo->prepare("SELECT COUNT(*) FROM migraciones WHERE migracion = '039_multifolio_split_cuentas.sql'");
$stmtMig->execute();
verificar("Migración 039_multifolio_split_cuentas.sql registrada en tabla migraciones", (int) $stmtMig->fetchColumn() === 1);

// Verificar columnas en cuentas_folios
$colFolios = $pdo->query("SHOW COLUMNS FROM cuentas_folios")->fetchAll(PDO::FETCH_COLUMN);
verificar("Columna 'es_principal' existe en cuentas_folios", in_array('es_principal', $colFolios, true));
verificar("Columna 'etiqueta' existe en cuentas_folios", in_array('etiqueta', $colFolios, true));
verificar("Columna 'folio_padre_id' existe en cuentas_folios", in_array('folio_padre_id', $colFolios, true));
verificar("Columna virtual 'folio_principal_reserva_idx' existe en cuentas_folios", in_array('folio_principal_reserva_idx', $colFolios, true));
verificar("Columna virtual 'folio_principal_arrendamiento_idx' existe en cuentas_folios", in_array('folio_principal_arrendamiento_idx', $colFolios, true));

// Verificar columna en cargos_cuenta
$colCargos = $pdo->query("SHOW COLUMNS FROM cargos_cuenta")->fetchAll(PDO::FETCH_COLUMN);
verificar("Columna 'cargo_padre_id' existe en cargos_cuenta", in_array('cargo_padre_id', $colCargos, true));

// --------------------------------------------------------------------
// BLOQUE 2: Preservación Retroactiva del Historial
// --------------------------------------------------------------------
echo "\n--- BLOQUE 2: Preservación de Folios Históricos como Principales ---\n";

$stmtHist = $pdo->query("SELECT COUNT(*) FROM cuentas_folios WHERE es_principal = 0");
$secundariosViejos = (int) $stmtHist->fetchColumn();
verificar("Cero folios pre-existentes quedaron degradados a secundarios", $secundariosViejos === 0);

$stmtEtq = $pdo->query("SELECT COUNT(*) FROM cuentas_folios WHERE etiqueta != 'FOLIO PRINCIPAL'");
$etiquetasNoDefault = (int) $stmtEtq->fetchColumn();
verificar("Todos los folios históricos poseen etiqueta canónica 'FOLIO PRINCIPAL'", $etiquetasNoDefault === 0);

try {
    // --------------------------------------------------------------------
    // BLOQUE 3: Regla de Oro - Unicidad del Folio Principal por Reserva
    // --------------------------------------------------------------------
    echo "\n--- BLOQUE 3: Unicidad del Folio Maestro Activo (MySQL Error 1062) ---\n";

    $sufijo = date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 6);

    // Crear Persona de prueba
    $stmtPer = $pdo->prepare("INSERT INTO personas (nombres, apellido_paterno, estado, creado_en) VALUES ('Audit', 'MultiFolio', 'ACTIVO', NOW())");
    $stmtPer->execute();
    $personaId = (int) $pdo->lastInsertId();

    // Crear Reserva de prueba
    $stmtRes = $pdo->prepare(
        "INSERT INTO reservas (codigo, persona_titular_id, fecha_entrada, fecha_salida, noches, estado, total, moneda_codigo, creado_por_actor_id, creado_en)
         VALUES (:codigo, :titular, '2026-11-01', '2026-11-05', 4, 'CONFIRMADA', 800.00, 'PEN', 1, NOW())"
    );
    $stmtRes->execute(['codigo' => "RES-MF3A-{$sufijo}", 'titular' => $personaId]);
    $reservaId = (int) $pdo->lastInsertId();

    $folioRepo = new CuentaFolioRepositorio($pdo);
    $cargoRepo = new CargoCuentaRepositorio($pdo);
    $transferenciaRepo = new CuentaFolioTransferenciaRepositorio($pdo);
    $servicio = new CuentaFolioServicio($pdo);

    // Crear Folio Maestro A
    $folioA = new CuentaFolio(
        null,
        "FOL-A-{$sufijo}",
        $reservaId,
        $personaId,
        'PEN',
        'ABIERTA',
        1,
        null,
        null,
        null,
        true,
        'FOLIO PRINCIPAL'
    );
    $folioAId = $folioRepo->crear($folioA);
    verificar("Creación exitosa de Folio Maestro A (es_principal = 1)", $folioAId > 0);

    // Intentar crear un SEGUNDO Folio Maestro para la MISMA reserva (debe fallar con SQLSTATE 23000 / error 1062)
    $errorUnicidad = false;
    try {
        $folioA2 = new CuentaFolio(
            null,
            "FOL-A2-{$sufijo}",
            $reservaId,
            $personaId,
            'PEN',
            'ABIERTA',
            1,
            null,
            null,
            null,
            true,
            'SEGUNDO MAESTRO ILEGAL'
        );
        $folioRepo->crear($folioA2);
    } catch (\Throwable $e) {
        $errorUnicidad = true;
    }
    verificar("Motor InnoDB rechaza físicamente segundo folio principal activo (uq_ctaf_folio_principal_reserva)", $errorUnicidad);

    // --------------------------------------------------------------------
    // BLOQUE 4: Creación de N Folios Secundarios (es_principal = 0)
    // --------------------------------------------------------------------
    echo "\n--- BLOQUE 4: Creación de Folios Secundarios 1:N ---\n";

    // Crear Persona co-huésped
    $stmtPer2 = $pdo->prepare("INSERT INTO personas (nombres, apellido_paterno, estado, creado_en) VALUES ('CoHuesped', 'MultiFolio', 'ACTIVO', NOW())");
    $stmtPer2->execute();
    $persona2Id = (int) $pdo->lastInsertId();

    // Folio Secundario B (Extras huésped)
    $folioB = new CuentaFolio(
        null,
        "FOL-B-{$sufijo}",
        $reservaId,
        $persona2Id,
        'PEN',
        'ABIERTA',
        1,
        null,
        null,
        null,
        false,
        'FOLIO B - CONSUMOS PERSONALES',
        $folioAId
    );
    $folioBId = $folioRepo->crear($folioB);
    verificar("Creación exitosa de Folio Secundario B para la misma reserva", $folioBId > 0);

    // Folio Secundario C (Empresa B2B)
    $folioC = new CuentaFolio(
        null,
        "FOL-C-{$sufijo}",
        $reservaId,
        $personaId,
        'PEN',
        'ABIERTA',
        1,
        null,
        null,
        null,
        false,
        'FOLIO C - GASTOS CORPORATIVOS',
        $folioAId
    );
    $folioCId = $folioRepo->crear($folioC);
    verificar("Creación exitosa de Folio Secundario C para la misma reserva (cardinalidad 1:3 lograda)", $folioCId > 0);

    // --------------------------------------------------------------------
    // BLOQUE 5: Consultas de Repositorio y Compatibilidad hacia Atrás
    // --------------------------------------------------------------------
    echo "\n--- BLOQUE 5: Métodos de Consulta y Compatibilidad ---\n";

    // obtenerPorReservaId devuelve el folio principal
    $folioObtenido = $folioRepo->obtenerPorReservaId($reservaId);
    verificar("obtenerPorReservaId() retorna el folio maestro (compatibilidad pre-existente)", $folioObtenido !== null && $folioObtenido->esPrincipal() && $folioObtenido->obtenerId() === $folioAId);

    // obtenerPrincipalPorReservaId devuelve exactamente el maestro
    $folioPrincipal = $folioRepo->obtenerPrincipalPorReservaId($reservaId);
    verificar("obtenerPrincipalPorReservaId() retorna el folio maestro", $folioPrincipal !== null && $folioPrincipal->obtenerId() === $folioAId);

    // listarPorReservaId retorna los 3 folios ordenados por es_principal DESC
    $listaFolios = $folioRepo->listarPorReservaId($reservaId);
    verificar("listarPorReservaId() devuelve exactamente 3 folios vinculados", count($listaFolios) === 3);
    verificar("El primer elemento de la lista es el folio principal", $listaFolios[0]->esPrincipal() === true);
    verificar("Los elementos siguientes son folios secundarios", $listaFolios[1]->esPrincipal() === false && $listaFolios[2]->esPrincipal() === false);

    // listarHijos retorna los secundarios que apuntan al padre A
    $hijosA = $folioRepo->listarHijos($folioAId);
    verificar("listarHijos(folioAId) retorna 2 folios secundarios subordinados", count($hijosA) === 2);

    // --------------------------------------------------------------------
    // BLOQUE 6: Soporte de Split de Cargos en cargos_cuenta
    // --------------------------------------------------------------------
    echo "\n--- BLOQUE 6: Soporte de Split de Cargos en cargos_cuenta ---\n";

    // Crear Cargo Padre en Folio A
    $cargoPadre = new CargoCuenta(
        null,
        "CRG-P-{$sufijo}",
        $folioAId,
        'ALOJAMIENTO_NOCHES',
        null,
        null,
        'Alojamiento Hab. 101 - 4 Noches',
        '4.00',
        '200.00',
        '800.00',
        '0.00',
        '800.00',
        '0.00',
        'PEN',
        'DEVENGADO',
        null,
        null,
        null,
        date('Y-m-d H:i:s'),
        1
    );
    $cargoPadreId = $cargoRepo->crear($cargoPadre);
    verificar("Cargo original (padre) creado en Folio A", $cargoPadreId > 0);

    $cargoRecuperado = $cargoRepo->obtenerPorId($cargoPadreId);
    verificar("Cargo padre tiene cargo_padre_id = NULL y esCargoHijo() = false", $cargoRecuperado !== null && $cargoRecuperado->obtenerCargoPadreId() === null && !$cargoRecuperado->esCargoHijo());

    // Crear Cargo Hijo derivado de Split en Folio B
    $cargoHijo = new CargoCuenta(
        null,
        "CRG-H-{$sufijo}",
        $folioBId,
        'ALOJAMIENTO_NOCHES',
        null,
        null,
        'Alojamiento Hab. 101 - 2 Noches (Split Huésped)',
        '2.00',
        '200.00',
        '400.00',
        '0.00',
        '400.00',
        '0.00',
        'PEN',
        'DEVENGADO',
        null,
        null,
        null,
        date('Y-m-d H:i:s'),
        1,
        null,
        null,
        $cargoPadreId
    );
    $cargoHijoId = $cargoRepo->crear($cargoHijo);
    verificar("Cargo derivado (hijo) creado en Folio B referenciando cargo padre", $cargoHijoId > 0);

    $cargoHijoRecuperado = $cargoRepo->obtenerPorId($cargoHijoId);
    verificar("Cargo hijo tiene cargo_padre_id correcto y esCargoHijo() = true", $cargoHijoRecuperado !== null && $cargoHijoRecuperado->obtenerCargoPadreId() === $cargoPadreId && $cargoHijoRecuperado->esCargoHijo());

    // Listar hijos desde el repositorio
    $hijosCargo = $cargoRepo->listarHijos($cargoPadreId);
    verificar("listarHijos() encuentra el cargo hijo vinculado", count($hijosCargo) === 1 && $hijosCargo[0]->obtenerId() === $cargoHijoId);

    // --------------------------------------------------------------------
    // BLOQUE 7: Tabla Inmutable de Trazabilidad de Transferencias
    // --------------------------------------------------------------------
    echo "\n--- BLOQUE 7: Tabla de Transferencias cuenta_folio_transferencias_cargos ---\n";

    // Intento de transferencia con origen y destino IDÉNTICOS (debe fallar por chk_trc_folios_distintos)
    $errorMismoFolio = false;
    try {
        $tIlegal = new CuentaFolioTransferenciaCargo(
            null,
            "TRC-ERR1-{$sufijo}",
            $cargoPadreId,
            null,
            $folioAId,
            $folioAId, // Mismo folio
            '100.00',
            'TOTAL',
            'Transferencia a sí mismo',
            1
        );
        $transferenciaRepo->crear($tIlegal);
    } catch (\Throwable $e) {
        $errorMismoFolio = true;
    }
    verificar("Motor InnoDB rechaza transferencia con mismo folio origen y destino (chk_trc_folios_distintos)", $errorMismoFolio);

    // Intento de transferencia con monto <= 0 (debe fallar por chk_trc_monto_positivo)
    $errorMontoCero = false;
    try {
        $tMontoCero = new CuentaFolioTransferenciaCargo(
            null,
            "TRC-ERR2-{$sufijo}",
            $cargoPadreId,
            null,
            $folioAId,
            $folioBId,
            '0.00', // Monto cero
            'TOTAL',
            'Transferencia de cero soles',
            1
        );
        $transferenciaRepo->crear($tMontoCero);
    } catch (\Throwable $e) {
        $errorMontoCero = true;
    }
    verificar("Motor InnoDB rechaza transferencia con monto <= 0.00 (chk_trc_monto_positivo)", $errorMontoCero);

    // Registro exitoso de transferencia split
    $codigoTrc = $transferenciaRepo->generarSiguienteCodigo();
    $tValida = new CuentaFolioTransferenciaCargo(
        null,
        $codigoTrc,
        $cargoPadreId,
        $cargoHijoId,
        $folioAId,
        $folioBId,
        '400.00',
        'SPLIT_PARCIAL',
        'División 50/50 de estadía solicitada por huésped en check-in',
        1
    );
    $trcId = $transferenciaRepo->crear($tValida);
    verificar("Registro exitoso de auditoría inmutable en cuenta_folio_transferencias_cargos", $trcId > 0);

    $trcRecuperada = $transferenciaRepo->obtenerPorId($trcId);
    verificar("Trazabilidad recuperada por ID con código canónico TRC-*", $trcRecuperada !== null && $trcRecuperada->obtenerCodigo() === $codigoTrc);
    verificar("Trazabilidad confirma tipo de operación SPLIT_PARCIAL y montos", $trcRecuperada !== null && $trcRecuperada->esSplitParcial() && $trcRecuperada->obtenerMontoTransferido() === '400.00');

    $listPorFolio = $transferenciaRepo->listarPorFolio($folioAId);
    verificar("listarPorFolio(folioAId) retorna la transferencia auditada", count($listPorFolio) >= 1 && $listPorFolio[0]->obtenerId() === $trcId);

    $listPorCargo = $transferenciaRepo->listarPorCargo($cargoPadreId);
    verificar("listarPorCargo(cargoPadreId) retorna el registro de auditoría", count($listPorCargo) >= 1 && $listPorCargo[0]->obtenerId() === $trcId);

    // --------------------------------------------------------------------
    // BLOQUE 8: Servicio de Dominio CuentaFolioServicio
    // --------------------------------------------------------------------
    echo "\n--- BLOQUE 8: Servicio de Dominio CuentaFolioServicio ---\n";

    // Crear reserva 2 con servicio
    $stmtRes2 = $pdo->prepare(
        "INSERT INTO reservas (codigo, persona_titular_id, fecha_entrada, fecha_salida, noches, estado, total, moneda_codigo, creado_por_actor_id, creado_en)
         VALUES (:codigo, :titular, '2026-11-10', '2026-11-12', 2, 'CONFIRMADA', 300.00, 'PEN', 1, NOW())"
    );
    $stmtRes2->execute(['codigo' => "RES-SRV-{$sufijo}", 'titular' => $personaId]);
    $reserva2Id = (int) $pdo->lastInsertId();

    // Asegurar folio raíz
    $folioSrvMaestro = $servicio->crearOAsegurarFolioReserva($reserva2Id, 1);
    verificar("crearOAsegurarFolioReserva() crea folio raíz maestro con es_principal = true", $folioSrvMaestro->esPrincipal());

    // Apertura de secundario vía servicio
    $folioSrvSecundario = $servicio->crearFolioSecundario($reserva2Id, $persona2Id, 'FOLIO EXTRAS BAR', 1);
    verificar("crearFolioSecundario() crea secundario con es_principal = false", !$folioSrvSecundario->esPrincipal());
    verificar("crearFolioSecundario() asigna etiqueta solicitada 'FOLIO EXTRAS BAR'", $folioSrvSecundario->obtenerEtiqueta() === 'FOLIO EXTRAS BAR');
    verificar("crearFolioSecundario() vincula folio_padre_id al maestro", $folioSrvSecundario->obtenerFolioPadreId() === $folioSrvMaestro->obtenerId());

    // Total de folios de la reserva 2
    $foliosRes2 = $servicio->obtenerFoliosReserva($reserva2Id);
    verificar("obtenerFoliosReserva() retorna 2 folios para la reserva 2", count($foliosRes2) === 2);

} finally {
    try {
        if (isset($trcId) && $trcId > 0) {
            $pdo->exec("DELETE FROM cuenta_folio_transferencias_cargos WHERE id = {$trcId}");
        }
        if (isset($cargoHijoId) && $cargoHijoId > 0) {
            $pdo->exec("DELETE FROM cargos_cuenta WHERE id = {$cargoHijoId}");
        }
        if (isset($cargoPadreId) && $cargoPadreId > 0) {
            $pdo->exec("DELETE FROM cargos_cuenta WHERE id = {$cargoPadreId}");
        }
        $foliosABorrar = [];
        if (isset($folioSrvSecundario)) {
            $foliosABorrar[] = $folioSrvSecundario->obtenerId();
        }
        if (isset($folioCId)) {
            $foliosABorrar[] = $folioCId;
        }
        if (isset($folioBId)) {
            $foliosABorrar[] = $folioBId;
        }
        if (!empty($foliosABorrar)) {
            $pdo->exec("DELETE FROM cuentas_folios WHERE id IN (" . implode(',', $foliosABorrar) . ")");
        }
        $foliosPadresABorrar = [];
        if (isset($folioSrvMaestro)) {
            $foliosPadresABorrar[] = $folioSrvMaestro->obtenerId();
        }
        if (isset($folioAId)) {
            $foliosPadresABorrar[] = $folioAId;
        }
        if (!empty($foliosPadresABorrar)) {
            $pdo->exec("DELETE FROM cuentas_folios WHERE id IN (" . implode(',', $foliosPadresABorrar) . ")");
        }
        $reservasABorrar = [];
        if (isset($reservaId)) {
            $reservasABorrar[] = $reservaId;
        }
        if (isset($reserva2Id)) {
            $reservasABorrar[] = $reserva2Id;
        }
        if (!empty($reservasABorrar)) {
            $pdo->exec("DELETE FROM reservas WHERE id IN (" . implode(',', $reservasABorrar) . ")");
        }
        $personasABorrar = [];
        if (isset($personaId)) {
            $personasABorrar[] = $personaId;
        }
        if (isset($persona2Id)) {
            $personasABorrar[] = $persona2Id;
        }
        if (!empty($personasABorrar)) {
            $pdo->exec("DELETE FROM personas WHERE id IN (" . implode(',', $personasABorrar) . ")");
        }
    } catch (\Throwable $e) {
        // En pruebas no interrumpir la presentación de resultados
    }
}

// --------------------------------------------------------------------
// RESUMEN FINAL
// --------------------------------------------------------------------
echo "\n====================================================================\n";
echo " RESUMEN: test_multifolio_split_3a.php\n";
echo " Total checks:  {$totalChecks}\n";
echo " Checks PASS:   {$passedChecks}\n";
echo " Checks FAIL:   {$failedChecks}\n";
echo " Estado:        " . ($failedChecks === 0 ? "100% PASS — HOMOLOGADO" : "FALLOS DETECTADOS") . "\n";
echo "====================================================================\n";

if ($failedChecks > 0) {
    exit(1);
}
exit(0);
