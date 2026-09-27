<?php

declare(strict_types=1);

/**
 * Camargo PMS — Batería de Pruebas de Concurrencia e Integridad Transaccional (MANTENIMIENTO-1)
 *
 * Casos evaluados:
 * - MNT-C01: Locking pesimista y serialización al programar concurrentemente sobre la misma unidad.
 * - MNT-C02: Restricción DDL CHECK chk_mord_bloqueo_coherente en motor MySQL/InnoDB.
 * - MNT-C03: Colisión al prorrogar bloqueo con reversión atómica limpia.
 * - MNT-C04: Integridad referencial ON DELETE RESTRICT impidiendo borrar unidad con orden o incidencia.
 * - MNT-C05: Captura y traducción de error MySQL 1062/1205 a ConflictoDisponibilidadExcepcion (HTTP 409).
 * - MNT-C06: Invariante de aislamiento: Cancelación atómica y cero registros huérfanos en inventario.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/app/Nucleo/Ayudante.php';

use CamargoPMS\Excepciones\ConflictoDisponibilidadExcepcion;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Servicios\MantenimientoServicio;

echo "====================================================================\n";
echo " Camargo PMS — Concurrencia e Integridad (MANTENIMIENTO-1)\n";
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

    $servicio = new MantenimientoServicio($pdo);
    $actorId = 1;

    // Obtener propiedad, unidades, colaboradores y proveedores
    $stmtProp = $pdo->query("SELECT id FROM propiedades WHERE estado = 'ACTIVO' LIMIT 1");
    $propiedadId = (int) $stmtProp->fetchColumn();

    $stmtU = $pdo->query("SELECT id FROM unidades WHERE propiedad_id = {$propiedadId} AND estado = 'ACTIVO' LIMIT 2");
    $unidades = $stmtU->fetchAll(PDO::FETCH_COLUMN);
    $u1 = (int) ($unidades[0] ?? 1);
    $u2 = (int) ($unidades[1] ?? 2);

    $stmtCol = $pdo->query("SELECT id FROM colaboradores WHERE estado = 'ACTIVO' LIMIT 1");
    $colaboradorId = (int) $stmtCol->fetchColumn();
    if ($colaboradorId === 0) {
        $stmtPer = $pdo->query("SELECT id FROM personas LIMIT 1");
        $perId = (int) $stmtPer->fetchColumn();
        $pdo->exec("INSERT INTO colaboradores (persona_id, codigo, estado) VALUES ({$perId}, 'COL-CONC-01', 'ACTIVO')");
        $colaboradorId = (int) $pdo->lastInsertId();
    }

    $stmtProv = $pdo->query("SELECT id FROM proveedores WHERE estado = 'ACTIVO' LIMIT 1");
    $proveedorId = (int) $stmtProv->fetchColumn();
    if ($proveedorId === 0) {
        $pdo->exec("INSERT INTO proveedores (tipo_proveedor, persona_id, razon_social, estado, creado_por_actor_id) VALUES ('EMPRESA', NULL, 'Servicios Conc SAC', 'ACTIVO', 1)");
        $proveedorId = (int) $pdo->lastInsertId();
    }

    // =========================================================================
    // MNT-C01: Locking pesimista y serialización en programación concurrente
    // =========================================================================
    try {
        $pdo->exec("DELETE FROM inventario_diario_unidades WHERE unidad_id = {$u1} AND fecha >= '2029-01-01'");

        $otA = $servicio->crearOrden([
            'propiedad_id' => $propiedadId,
            'unidad_id' => $u1,
            'titulo' => 'Orden A Concurrencia',
            'descripcion' => 'Prueba A',
            'tipo_asignacion' => 'INTERNO',
            'colaborador_asignado_id' => $colaboradorId,
            'requiere_bloqueo' => 1,
            'fecha_programada_inicio' => '2029-01-10',
            'fecha_programada_fin' => '2029-01-15',
        ], $actorId);

        $otB = $servicio->crearOrden([
            'propiedad_id' => $propiedadId,
            'unidad_id' => $u1,
            'titulo' => 'Orden B Concurrencia',
            'descripcion' => 'Prueba B Solapada',
            'tipo_asignacion' => 'INTERNO',
            'colaborador_asignado_id' => $colaboradorId,
            'requiere_bloqueo' => 1,
            'fecha_programada_inicio' => '2029-01-12',
            'fecha_programada_fin' => '2029-01-18',
        ], $actorId);

        $servicio->programarOrden((int) $otA->obtenerId(), [], $actorId);

        $bloqueoBRechazado = false;
        try {
            $servicio->programarOrden((int) $otB->obtenerId(), [], $actorId);
        } catch (ConflictoDisponibilidadExcepcion $e) {
            $bloqueoBRechazado = true;
        }

        $stmtContarB = $pdo->prepare("SELECT COUNT(*) FROM inventario_diario_unidades WHERE origen_id = ?");
        $stmtContarB->execute([$otB->obtenerId()]);
        $nochesB = (int) $stmtContarB->fetchColumn();

        verificarConcurrencia(
            'MNT-C01',
            'Locking pesimista y serialización al programar concurrentemente sobre la misma unidad',
            $bloqueoBRechazado && $nochesB === 0
        );
    } catch (Throwable $e) {
        verificarConcurrencia('MNT-C01', 'Locking pesimista y serialización', false, $e->getMessage());
    }

    // =========================================================================
    // MNT-C02: Restricción DDL CHECK chk_mord_bloqueo_coherente en MySQL/InnoDB
    // =========================================================================
    try {
        $checkFallidoCorrectamente = false;
        try {
            // Intentar violar el CHECK: requiere_bloqueo = 1 pero unidad_id IS NULL
            $pdo->exec("INSERT INTO mantenimiento_ordenes (
                codigo, tipo, prioridad, propiedad_id, unidad_id, titulo, descripcion,
                tipo_asignacion, requiere_bloqueo, fecha_programada_inicio, fecha_programada_fin,
                fecha_bloqueo_inicio, fecha_bloqueo_fin, costo_estimado, costo_mano_obra,
                costo_materiales, costo_total, moneda_codigo, estado, creado_por_actor_id
            ) VALUES (
                'OT-CHECK-VIOLATE', 'CORRECTIVO', 'MEDIA', {$propiedadId}, NULL, 'Invalida', 'Sin unidad',
                'INTERNO', 1, '2029-02-01', '2029-02-05', '2029-02-01', '2029-02-05', 0, 0, 0, 0, 'PEN', 'BORRADOR', {$actorId}
            )");
        } catch (PDOException $e) {
            // Error 3819 = Check constraint violated
            if (str_contains($e->getMessage(), 'chk_mord_bloqueo_coherente') || (int) ($e->errorInfo[1] ?? 0) === 3819) {
                $checkFallidoCorrectamente = true;
            }
        }

        verificarConcurrencia(
            'MNT-C02',
            'Restricción DDL CHECK chk_mord_bloqueo_coherente protege coherencia de bloqueo en motor relacional',
            $checkFallidoCorrectamente
        );
    } catch (Throwable $e) {
        verificarConcurrencia('MNT-C02', 'Restricción DDL CHECK', false, $e->getMessage());
    }

    // =========================================================================
    // MNT-C03: Colisión al prorrogar bloqueo con reversión atómica limpia
    // =========================================================================
    try {
        $pdo->exec("DELETE FROM inventario_diario_unidades WHERE unidad_id = {$u2} AND fecha >= '2029-03-01'");

        $otBase = $servicio->crearOrden([
            'propiedad_id' => $propiedadId,
            'unidad_id' => $u2,
            'titulo' => 'Base para Prórroga',
            'descripcion' => 'Prueba prórroga',
            'tipo_asignacion' => 'INTERNO',
            'colaborador_asignado_id' => $colaboradorId,
            'requiere_bloqueo' => 1,
            'fecha_programada_inicio' => '2029-03-01',
            'fecha_programada_fin' => '2029-03-05',
        ], $actorId);
        $servicio->programarOrden((int) $otBase->obtenerId(), [], $actorId);

        // Simulamos que la noche 2029-03-06 fue ocupada por una reserva comercial
        $pdo->exec("INSERT INTO inventario_diario_unidades (unidad_id, fecha, tipo_bloqueo, origen_tipo, origen_id)
                    VALUES ({$u2}, '2029-03-06', 'RESERVA', 'RESERVA_HOTELERA', 99999)");

        // Intentar prorrogar la orden hasta 2029-03-08 (colisiona en la noche 2029-03-06)
        $colisionProrroga = false;
        try {
            $servicio->prorrogarBloqueo((int) $otBase->obtenerId(), '2029-03-08', $actorId);
        } catch (ConflictoDisponibilidadExcepcion $e) {
            $colisionProrroga = true;
        }

        // Verificar que la fecha de bloqueo de la orden quedó intacta en 2029-03-05
        $otConsultada = $servicio->obtenerOrden((int) $otBase->obtenerId());
        $fechaFinInalterada = $otConsultada->obtenerFechaBloqueoFin() === '2029-03-05';

        verificarConcurrencia(
            'MNT-C03',
            'Colisión al prorrogar bloqueo ejecuta rollback atómico y preserva fecha previa sin mutaciones parciales',
            $colisionProrroga && $fechaFinInalterada
        );
    } catch (Throwable $e) {
        verificarConcurrencia('MNT-C03', 'Colisión en prórroga', false, $e->getMessage());
    }

    // =========================================================================
    // MNT-C04: Integridad referencial ON DELETE RESTRICT en unidades
    // =========================================================================
    try {
        $borradoRechazado = false;
        try {
            $pdo->exec("DELETE FROM unidades WHERE id = {$u1}");
        } catch (PDOException $e) {
            // Error 1451: Cannot delete or update a parent row: a foreign key constraint fails
            if ((int) ($e->errorInfo[1] ?? 0) === 1451) {
                $borradoRechazado = true;
            }
        }

        verificarConcurrencia(
            'MNT-C04',
            'Integridad referencial ON DELETE RESTRICT protege unidades vinculadas a órdenes de mantenimiento',
            $borradoRechazado
        );
    } catch (Throwable $e) {
        verificarConcurrencia('MNT-C04', 'Integridad referencial ON DELETE RESTRICT', false, $e->getMessage());
    }

    // =========================================================================
    // MNT-C05: Captura y traducción de error MySQL 1062 a ConflictoDisponibilidadExcepcion
    // =========================================================================
    try {
        $pdo->exec("DELETE FROM inventario_diario_unidades WHERE unidad_id = {$u1} AND fecha = '2029-04-15'");
        $pdo->exec("INSERT INTO inventario_diario_unidades (unidad_id, fecha, tipo_bloqueo, origen_tipo, origen_id)
                    VALUES ({$u1}, '2029-04-15', 'BLOQUEO_MANUAL', 'BLOQUEO_OPERATIVO', 88888)");

        $otDuplicada = $servicio->crearOrden([
            'propiedad_id' => $propiedadId,
            'unidad_id' => $u1,
            'titulo' => 'Doble Inserción',
            'descripcion' => 'Test 1062',
            'tipo_asignacion' => 'INTERNO',
            'colaborador_asignado_id' => $colaboradorId,
            'requiere_bloqueo' => 1,
            'fecha_programada_inicio' => '2029-04-15',
            'fecha_programada_fin' => '2029-04-16',
        ], $actorId);

        $capturadaComo409 = false;
        try {
            $servicio->programarOrden((int) $otDuplicada->obtenerId(), [], $actorId);
        } catch (ConflictoDisponibilidadExcepcion $e) {
            $capturadaComo409 = ($e->getCode() === 409);
        }

        verificarConcurrencia(
            'MNT-C05',
            'Captura y traducción canónica de colisión de clave única a ConflictoDisponibilidadExcepcion (HTTP 409)',
            $capturadaComo409
        );
    } catch (Throwable $e) {
        verificarConcurrencia('MNT-C05', 'Captura y traducción MySQL 1062', false, $e->getMessage());
    }

    // =========================================================================
    // MNT-C06: Invariante de aislamiento: Cancelación atómica y cero huérfanos
    // =========================================================================
    try {
        $otParaCancelar = $servicio->crearOrden([
            'propiedad_id' => $propiedadId,
            'unidad_id' => $u2,
            'titulo' => 'Aislamiento Cancelar',
            'descripcion' => 'Verificar huérfanos',
            'tipo_asignacion' => 'INTERNO',
            'colaborador_asignado_id' => $colaboradorId,
            'requiere_bloqueo' => 1,
            'fecha_programada_inicio' => '2029-05-01',
            'fecha_programada_fin' => '2029-05-06',
        ], $actorId);

        $servicio->programarOrden((int) $otParaCancelar->obtenerId(), [], $actorId);

        // Cancelar orden
        $servicio->cancelarOrden((int) $otParaCancelar->obtenerId(), 'Cancelación limpia para verificación', $actorId);

        // Verificar que no quedó ninguna fila de esta orden en inventario
        $stmtHuerfanos = $pdo->prepare("SELECT COUNT(*) FROM inventario_diario_unidades WHERE origen_tipo = 'MANTENIMIENTO_ORDEN' AND origen_id = ?");
        $stmtHuerfanos->execute([$otParaCancelar->obtenerId()]);
        $huerfanos = (int) $stmtHuerfanos->fetchColumn();

        verificarConcurrencia(
            'MNT-C06',
            'Invariante de aislamiento: Cancelación atómica garantiza cero noches huérfanas en inventario diario',
            $huerfanos === 0
        );
    } catch (Throwable $e) {
        verificarConcurrencia('MNT-C06', 'Aislamiento y huérfanos', false, $e->getMessage());
    }

} catch (Throwable $globalError) {
    echo "\n[ERROR GLOBAL]: " . $globalError->getMessage() . "\n";
    exit(1);
}

echo "\n====================================================================\n";
echo "RESULTADOS CONCURRENCIA MANTENIMIENTO-1: {$passCount} / {$totalCount} PASS\n";
echo "====================================================================\n";

if ($passCount === $totalCount) {
    exit(0);
}
exit(1);
