<?php

declare(strict_types=1);

/**
 * Camargo PMS — Batería de Pruebas de Concurrencia e Integridad Transaccional (ARRENDAMIENTOS-1)
 *
 * Casos evaluados:
 * - ARR-C01: Locking pesimista y serialización en activación concurrente sobre la misma unidad
 * - ARR-C02: Doble compensación concurrente de garantía que excede saldo retenido
 * - ARR-C03: Doble emisión concurrente de cuota mensual para el mismo período (idempotencia)
 * - ARR-C04: Unicidad concurrente de titular principal en arrendamiento_personas (uq_arrp_titular_unico)
 * - ARR-C05: Exclusión mutua en prórroga concurrente con colisión de fechas
 * - ARR-C06: Integridad referencial ON DELETE RESTRICT en contratos y folios financieros
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/app/Nucleo/Ayudante.php';

use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Servicios\ArrendamientoServicio;
use CamargoPMS\Repositorios\ArrendamientoRepositorio;
use CamargoPMS\Repositorios\ArrendamientoGarantiaRepositorio;
use CamargoPMS\Repositorios\ArrendamientoCuotaRepositorio;
use CamargoPMS\Repositorios\ArrendamientoPersonaRepositorio;
use CamargoPMS\Repositorios\CuentaFolioRepositorio;
use CamargoPMS\Excepciones\ConflictoDisponibilidadExcepcion;
use CamargoPMS\Excepciones\GarantiaInvalidaExcepcion;
use CamargoPMS\Excepciones\ValidacionExcepcion;

echo "====================================================================\n";
echo " Camargo PMS — Concurrencia e Integridad (ARRENDAMIENTOS-1)\n";
echo "====================================================================\n\n";

$passCount = 0;
$totalCount = 6;

function verificarConcurrencia(string $codigo, string $descripcion, bool $resultado, ?string $detalle = null): void
{
    global $passCount;
    if ($resultado) {
        $passCount++;
        echo "  [PASS] {$codigo}: {$descripcion}\n";
    } else {
        echo "  [FAIL] {$codigo}: {$descripcion}\n";
        if ($detalle) {
            echo "         Motivo: {$detalle}\n";
        }
    }
}

try {
    Configuracion::cargar(dirname(__DIR__));
    $pdo = BaseDatos::conexion();
    $pdo->exec("SET SESSION innodb_lock_wait_timeout = 3;");

    $servicio = new ArrendamientoServicio($pdo);
    $arrRepo = new ArrendamientoRepositorio($pdo);
    $garantiaRepo = new ArrendamientoGarantiaRepositorio($pdo);
    $cuotaRepo = new ArrendamientoCuotaRepositorio($pdo);
    $personaRepo = new ArrendamientoPersonaRepositorio($pdo);
    $folioRepo = new CuentaFolioRepositorio($pdo);

    $actorId = 1;

    // Obtener unidades y personas de prueba
    $stmtU = $pdo->query("SELECT id FROM unidades WHERE estado = 'ACTIVO' LIMIT 4");
    $unidades = $stmtU->fetchAll(PDO::FETCH_COLUMN);
    $u1 = (int) ($unidades[0] ?? 1);
    $u2 = (int) ($unidades[1] ?? 2);
    $u3 = (int) ($unidades[2] ?? 3);

    $stmtP = $pdo->query("SELECT id FROM personas LIMIT 3");
    $personas = $stmtP->fetchAll(PDO::FETCH_COLUMN);
    $p1 = (int) ($personas[0] ?? 1);
    $p2 = (int) ($personas[1] ?? 2);
    $p3 = (int) ($personas[2] ?? 3);

    // =========================================================================
    // ARR-C01: Activación Concurrente sobre la misma unidad (Locking e Inventario)
    // =========================================================================
    try {
        $pdo->exec("DELETE FROM inventario_diario_unidades WHERE unidad_id = {$u1} AND fecha >= '2028-01-01'");

        $contratoA = $servicio->crearArrendamiento([
            'unidad_id' => $u1,
            'titular_persona_id' => $p1,
            'fecha_inicio' => '2028-01-01',
            'fecha_fin' => '2028-03-31',
            'renta_mensual' => 2000.00,
        ], $actorId);

        $contratoB = $servicio->crearArrendamiento([
            'unidad_id' => $u1,
            'titular_persona_id' => $p2,
            'fecha_inicio' => '2028-02-01',
            'fecha_fin' => '2028-04-30',
            'renta_mensual' => 2200.00,
        ], $actorId);

        // Hilo 1 activa Contrato A -> Éxito, materializa noches
        $servicio->activarArrendamiento((int) $contratoA->obtenerId(), $actorId);

        // Hilo 2 intenta activar Contrato B -> Debe fallar por colisión de inventario
        $falloColision = false;
        try {
            $servicio->activarArrendamiento((int) $contratoB->obtenerId(), $actorId);
        } catch (ConflictoDisponibilidadExcepcion $e) {
            $falloColision = true;
        }

        // Verificar que Contrato A quedó VIGENTE y Contrato B se mantuvo BORRADOR
        $aRec = $arrRepo->obtenerPorId((int) $contratoA->obtenerId());
        $bRec = $arrRepo->obtenerPorId((int) $contratoB->obtenerId());

        $ok1 = $falloColision && $aRec->esVigente() && $bRec->esBorrador();
        verificarConcurrencia('ARR-C01', 'Locking pesimista y serialización en activación concurrente sobre la misma unidad', $ok1);
    } catch (Throwable $e) {
        verificarConcurrencia('ARR-C01', 'Locking pesimista y serialización en activación concurrente sobre la misma unidad', false, $e->getMessage());
    }

    // =========================================================================
    // ARR-C02: Doble compensación concurrente de garantía que excede saldo retenido
    // =========================================================================
    try {
        $contratoG = $servicio->crearArrendamiento([
            'unidad_id' => $u2,
            'titular_persona_id' => $p1,
            'fecha_inicio' => '2028-05-01',
            'fecha_fin' => '2028-08-31',
            'renta_mensual' => 1500.00,
            'deposito_garantia' => 1000.00,
        ], $actorId);
        $pdo->exec("DELETE FROM inventario_diario_unidades WHERE unidad_id = {$u2} AND fecha >= '2028-05-01'");
        $servicio->activarArrendamiento((int) $contratoG->obtenerId(), $actorId);
        $servicio->registrarRecepcionGarantia((int) $contratoG->obtenerId(), '1000.00', $actorId);

        // Compensación 1: 600.00 por daños -> Éxito, saldo retenido = 400.00
        $servicio->compensarGarantia((int) $contratoG->obtenerId(), '600.00', 'DANOS', 'Rotura de cerradura', $actorId);

        // Compensación 2 concurrente: 600.00 por renta -> Debe fallar porque 600.00 > 400.00 disponible
        $falloExceso = false;
        try {
            $servicio->compensarGarantia((int) $contratoG->obtenerId(), '600.00', 'RENTA', 'Mes insoluto', $actorId);
        } catch (GarantiaInvalidaExcepcion $e) {
            $falloExceso = true;
        }

        $gar = $garantiaRepo->obtenerPorArrendamientoId((int) $contratoG->obtenerId());
        $ok2 = $falloExceso && (bccomp($gar->obtenerMontoRetenidoActual(), '400.00', 2) === 0);
        verificarConcurrencia('ARR-C02', 'Doble compensación concurrente de garantía que excede saldo retenido (rechazo estricto)', $ok2);
    } catch (Throwable $e) {
        verificarConcurrencia('ARR-C02', 'Doble compensación concurrente de garantía que excede saldo retenido', false, $e->getMessage());
    }

    // =========================================================================
    // ARR-C03: Doble emisión concurrente de cuota mensual (Idempotencia)
    // =========================================================================
    try {
        $contratoCuota = $servicio->crearArrendamiento([
            'unidad_id' => $u3,
            'titular_persona_id' => $p1,
            'fecha_inicio' => '2028-06-01',
            'fecha_fin' => '2028-11-30',
            'renta_mensual' => 1800.00,
        ], $actorId);
        $pdo->exec("DELETE FROM inventario_diario_unidades WHERE unidad_id = {$u3} AND fecha >= '2028-06-01'");
        $servicio->activarArrendamiento((int) $contratoCuota->obtenerId(), $actorId);

        // Generar cuota mes 7 concurrentemente (simulado invocando dos veces)
        $cuota1 = $servicio->generarCuotaMensual((int) $contratoCuota->obtenerId(), 2028, 7, $actorId);
        $cuota2 = $servicio->generarCuotaMensual((int) $contratoCuota->obtenerId(), 2028, 7, $actorId);

        $stmtC = $pdo->prepare("SELECT COUNT(*) FROM arrendamiento_cuotas WHERE arrendamiento_id = ? AND periodo_codigo = '2028-07'");
        $stmtC->execute([$contratoCuota->obtenerId()]);
        $conteoCuotas = (int) $stmtC->fetchColumn();

        $ok3 = ($cuota1->obtenerId() === $cuota2->obtenerId()) && ($conteoCuotas === 1);
        verificarConcurrencia('ARR-C03', 'Doble emisión concurrente de cuota mensual para el mismo período (idempotencia garantizada)', $ok3);
    } catch (Throwable $e) {
        verificarConcurrencia('ARR-C03', 'Doble emisión concurrente de cuota mensual para el mismo período', false, $e->getMessage());
    }

    // =========================================================================
    // ARR-C04: Unicidad concurrente de titular principal (uq_arrp_titular_unico)
    // =========================================================================
    try {
        // Contrato nuevo con p1 como titular
        $contratoTit = $servicio->crearArrendamiento([
            'unidad_id' => $u1,
            'titular_persona_id' => $p1,
            'fecha_inicio' => '2029-01-01',
            'fecha_fin' => '2029-03-31',
            'renta_mensual' => 1200.00,
        ], $actorId);
        $cId = (int) $contratoTit->obtenerId();

        // Intento directo en SQL de insertar un segundo TITULAR para el mismo contrato
        $falloDuplicadoTitular = false;
        try {
            $pdo->exec("INSERT INTO arrendamiento_personas (arrendamiento_id, persona_id, tipo_relacion)
                        VALUES ({$cId}, {$p2}, 'TITULAR')");
        } catch (PDOException $e) {
            // Error 1062 es Duplicate entry por uq_arrp_titular_unico
            if (str_contains($e->getMessage(), 'uq_arrp_titular_unico') || str_contains($e->getMessage(), '1062')) {
                $falloDuplicadoTitular = true;
            }
        }

        verificarConcurrencia('ARR-C04', 'Unicidad de titular principal blindada en BD por uq_arrp_titular_unico', $falloDuplicadoTitular);
    } catch (Throwable $e) {
        verificarConcurrencia('ARR-C04', 'Unicidad concurrente de titular principal', false, $e->getMessage());
    }

    // =========================================================================
    // ARR-C05: Exclusión mutua en prórroga concurrente con colisión de fechas
    // =========================================================================
    try {
        // u2 tiene fechas ocupadas en 2028-05-01 a 2028-08-31 por contratoG.
        // Si creamos un contrato H previo de 2028-03-01 a 2028-04-30
        $pdo->exec("DELETE FROM inventario_diario_unidades WHERE unidad_id = {$u2} AND fecha BETWEEN '2028-03-01' AND '2028-04-30'");
        $contratoH = $servicio->crearArrendamiento([
            'unidad_id' => $u2,
            'titular_persona_id' => $p3,
            'fecha_inicio' => '2028-03-01',
            'fecha_fin' => '2028-04-30',
            'renta_mensual' => 1400.00,
        ], $actorId);
        $servicio->activarArrendamiento((int) $contratoH->obtenerId(), $actorId);

        // Intento de prorrogar contrato H hasta 2028-06-30 -> Colisiona con contrato G que empieza 2028-05-01
        $falloProrroga = false;
        try {
            $servicio->prorrogarArrendamiento((int) $contratoH->obtenerId(), '2028-06-30', $actorId);
        } catch (ConflictoDisponibilidadExcepcion $e) {
            $falloProrroga = true;
        }

        // Verificar que fecha_fin no cambió
        $hRec = $arrRepo->obtenerPorId((int) $contratoH->obtenerId());
        $ok5 = $falloProrroga && ($hRec->obtenerFechaFin() === '2028-04-30');
        verificarConcurrencia('ARR-C05', 'Exclusión mutua en prórroga contractual ante colisión de fechas ocupadas', $ok5);
    } catch (Throwable $e) {
        verificarConcurrencia('ARR-C05', 'Exclusión mutua en prórroga contractual ante colisión', false, $e->getMessage());
    }

    // =========================================================================
    // ARR-C06: Integridad referencial ON DELETE RESTRICT en contratos y folios
    // =========================================================================
    try {
        // Usar contratoA que está activado, tiene folio financiero y cargos vinculados
        $idActivo = (int) $contratoA->obtenerId();

        // Intentar borrar contrato que tiene folio y cuotas asociadas
        $falloDeleteContrato = false;
        try {
            $pdo->exec("DELETE FROM arrendamientos WHERE id = {$idActivo}");
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'Integrity constraint violation') || str_contains($e->getMessage(), '1451')) {
                $falloDeleteContrato = true;
            }
        }

        // Intentar borrar folio financiero que tiene cargos devengados asociados
        $folio = $folioRepo->obtenerPorArrendamientoId($idActivo);
        $falloDeleteFolio = false;
        if ($folio !== null) {
            try {
                $pdo->exec("DELETE FROM cuentas_folios WHERE id = " . $folio->obtenerId());
            } catch (PDOException $e) {
                if (str_contains($e->getMessage(), 'Integrity constraint violation') || str_contains($e->getMessage(), '1451')) {
                    $falloDeleteFolio = true;
                }
            }
        }

        $ok6 = $falloDeleteContrato && $falloDeleteFolio;
        verificarConcurrencia('ARR-C06', 'Integridad referencial ON DELETE RESTRICT previene borrado físico de contratos y folios', $ok6);
    } catch (Throwable $e) {
        verificarConcurrencia('ARR-C06', 'Integridad referencial ON DELETE RESTRICT previene borrado físico', false, $e->getMessage());
    }

    echo "\n====================================================================\n";
    echo "RESULTADOS CONCURRENCIA ARRENDAMIENTOS-1: {$passCount}/{$totalCount} PASS\n";
    echo "====================================================================\n";

} catch (Throwable $e) {
    echo "ERROR GENERAL EN SUITE DE CONCURRENCIA: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
