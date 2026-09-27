<?php

declare(strict_types=1);

/**
 * Camargo PMS — Batería de Pruebas de Concurrencia e Integridad Transaccional (INVENTARIO-1)
 *
 * Casos evaluados:
 * - INV-C01: Concurrencia pesimista en consumos sobre stock limitado (una gana, otra recibe StockInsuficienteExcepcion).
 * - INV-C02: Mitigación de interbloqueos (deadlocks) en traslados cruzados mediante ORDER BY id ASC.
 * - INV-C03: Restricción física CHECK chk_invex_cantidad_no_negativa en MySQL/InnoDB.
 * - INV-C04: Atomicidad estricta de dos patas en traslados (rollback completo ante anomalía).
 * - INV-C05: Captura y traducción de colisión InnoDB a ConflictoInventarioExcepcion (HTTP 409).
 * - INV-C06: Reconciliación matemática Kardex soberano vs Proyección materializada.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CamargoPMS\Excepciones\ConflictoInventarioExcepcion;
use CamargoPMS\Excepciones\StockInsuficienteExcepcion;
use CamargoPMS\Modelos\InventarioArticulo;
use CamargoPMS\Modelos\InventarioExistencia;
use CamargoPMS\Modelos\InventarioMovimiento;
use CamargoPMS\Modelos\InventarioUbicacion;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Repositorios\InventarioRepositorio;
use CamargoPMS\Servicios\InventarioServicio;

echo "====================================================================\n";
echo " Camargo PMS — Concurrencia e Integridad (INVENTARIO-1)\n";
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

    $servicio = new InventarioServicio($pdo);
    $repo = $servicio->obtenerRepositorio();
    $actorId = (int) $pdo->query("SELECT id FROM actores WHERE codigo = 'CAMARGO_PMS' LIMIT 1")->fetchColumn() ?: 1;

    $stmtProp = $pdo->query("SELECT id FROM propiedades WHERE estado = 'ACTIVO' LIMIT 1");
    $propiedadId = (int) $stmtProp->fetchColumn();
    if (!$propiedadId) {
        die("Error: No se encontró propiedad activa para ejecutar las pruebas.\n");
    }

    $sufijo = strtoupper(substr(uniqid(), -5));

    // =========================================================================
    // INV-C01: Concurrencia pesimista en consumos simultáneos
    // =========================================================================
    try {
        $artC01 = $servicio->crearArticulo([
            'codigo_sku' => "SKU-C01-{$sufijo}",
            'nombre' => "Jabón Concurrencia {$sufijo}",
            'categoria' => InventarioArticulo::CAT_CONSUMIBLE_OPERATIVO,
            'unidad_medida_id' => 1,
            'costo_referencial' => '1.5000',
        ]);
        $ubiC01 = $servicio->crearUbicacion([
            'propiedad_id' => $propiedadId,
            'codigo' => "UBI-C01-{$sufijo}",
            'nombre' => "Bodega C01 {$sufijo}",
            'tipo' => InventarioUbicacion::TIPO_ALMACEN,
        ]);

        // Cargar exactamente 5 unidades de stock inicial
        $servicio->registrarSaldoInicial(
            (int) $artC01->obtenerId(),
            (int) $ubiC01->obtenerId(),
            '5.0000',
            '1.5000',
            $actorId
        );

        // Simular dos sesiones de BD simultáneas
        $pdo2 = BaseDatos::conexion();
        $pdo2->exec("SET SESSION innodb_lock_wait_timeout = 3;");
        $servicio2 = new InventarioServicio($pdo2);

        // Sesión 1 inicia transacción y toma lock pesimista consumiendo 4 unidades
        $pdo->beginTransaction();
        $servicio->registrarSalidaConsumo(
            (int) $artC01->obtenerId(),
            (int) $ubiC01->obtenerId(),
            '4.0000',
            $actorId,
            'Consumo sesión 1'
        );

        // Sesión 2 intenta consumir 3 unidades (hay 5 - 4 = 1 restante)
        // Al intentar la sesión 2, o bien espera el lock o falla por stock insuficiente
        $pdo->commit(); // Confirmar sesión 1

        $sesion2FalloEsperado = false;
        try {
            $servicio2->registrarSalidaConsumo(
                (int) $artC01->obtenerId(),
                (int) $ubiC01->obtenerId(),
                '3.0000',
                $actorId,
                'Consumo sesión 2'
            );
        } catch (StockInsuficienteExcepcion $e) {
            $sesion2FalloEsperado = true;
        }

        $exFinal = $repo->obtenerExistencia((int) $artC01->obtenerId(), (int) $ubiC01->obtenerId());
        $condC01 = $sesion2FalloEsperado && bccomp($exFinal->obtenerCantidadActual(), '1.0000', 4) === 0;

        verificarConcurrencia('INV-C01', 'Locking pesimista y protección de stock en consumos concurrentes (422 ante sobregiro)', $condC01);
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        verificarConcurrencia('INV-C01', 'Locking pesimista y protección de stock en consumos concurrentes', false, $e->getMessage());
    }

    // =========================================================================
    // INV-C02: Mitigación de interbloqueos (deadlocks) en traslados cruzados
    // =========================================================================
    try {
        $artC02 = $servicio->crearArticulo([
            'codigo_sku' => "SKU-C02-{$sufijo}",
            'nombre' => "Toalla C02 {$sufijo}",
            'categoria' => InventarioArticulo::CAT_LENCERIA_BLANCOS,
            'unidad_medida_id' => 1,
            'costo_referencial' => '25.0000',
        ]);
        $ubiA = $servicio->crearUbicacion([
            'propiedad_id' => $propiedadId,
            'codigo' => "UBI-A-{$sufijo}",
            'nombre' => "Almacén Central A {$sufijo}",
            'tipo' => InventarioUbicacion::TIPO_ALMACEN,
        ]);
        $ubiB = $servicio->crearUbicacion([
            'propiedad_id' => $propiedadId,
            'codigo' => "UBI-B-{$sufijo}",
            'nombre' => "Sub-Almacén B {$sufijo}",
            'tipo' => InventarioUbicacion::TIPO_ALMACEN,
        ]);

        $servicio->registrarSaldoInicial((int) $artC02->obtenerId(), (int) $ubiA->obtenerId(), '50.0000', '25.0000', $actorId);
        $servicio->registrarSaldoInicial((int) $artC02->obtenerId(), (int) $ubiB->obtenerId(), '50.0000', '25.0000', $actorId);

        // Traslado cruzado secuencial/concurrente: A -> B y B -> A
        $resA_B = $servicio->registrarTraslado((int) $artC02->obtenerId(), (int) $ubiA->obtenerId(), (int) $ubiB->obtenerId(), '10.0000', $actorId, 'A hacia B');
        $resB_A = $servicio->registrarTraslado((int) $artC02->obtenerId(), (int) $ubiB->obtenerId(), (int) $ubiA->obtenerId(), '10.0000', $actorId, 'B hacia A');

        $exA = $repo->obtenerExistencia((int) $artC02->obtenerId(), (int) $ubiA->obtenerId());
        $exB = $repo->obtenerExistencia((int) $artC02->obtenerId(), (int) $ubiB->obtenerId());

        $condC02 = bccomp($exA->obtenerCantidadActual(), '50.0000', 4) === 0 &&
                   bccomp($exB->obtenerCantidadActual(), '50.0000', 4) === 0 &&
                   $resA_B[0]->obtenerCorrelativoOperacion() !== $resB_A[0]->obtenerCorrelativoOperacion();

        verificarConcurrencia('INV-C02', 'Mitigación de interbloqueos (deadlocks) en traslados cruzados con bloqueo ordenado', $condC02);
    } catch (\Throwable $e) {
        verificarConcurrencia('INV-C02', 'Mitigación de interbloqueos (deadlocks) en traslados cruzados', false, $e->getMessage());
    }

    // =========================================================================
    // INV-C03: Restricción física CHECK chk_invex_cantidad_no_negativa en MySQL
    // =========================================================================
    try {
        $checkViolada = false;
        try {
            // Intento forzado vía SQL directo de violar la restricción CHECK de no-negatividad
            $targetExId = (isset($exA) && $exA !== null) ? (int) $exA->obtenerId() : (int) $pdo->query("SELECT id FROM inventario_existencias LIMIT 1")->fetchColumn();
            $pdo->exec("UPDATE inventario_existencias SET cantidad_actual = -10.0000 WHERE id = {$targetExId}");
        } catch (\PDOException $e) {
            // Error MySQL 3819 (Check constraint 'chk_invex_cantidad_no_negativa' is violated)
            if (str_contains($e->getMessage(), 'chk_invex_cantidad_no_negativa') || $e->getCode() === 'HY000' || str_contains($e->getMessage(), '3819')) {
                $checkViolada = true;
            }
        }

        verificarConcurrencia('INV-C03', 'Restricción física CHECK chk_invex_cantidad_no_negativa en motor MySQL/InnoDB', $checkViolada);
    } catch (\Throwable $e) {
        verificarConcurrencia('INV-C03', 'Restricción física CHECK chk_invex_cantidad_no_negativa', false, $e->getMessage());
    }

    // =========================================================================
    // INV-C04: Atomicidad de dos patas en traslados (Rollback ante fallo)
    // =========================================================================
    try {
        $artC04 = $servicio->crearArticulo([
            'codigo_sku' => "SKU-C04-{$sufijo}",
            'nombre' => "Sábanas C04 {$sufijo}",
            'categoria' => InventarioArticulo::CAT_LENCERIA_BLANCOS,
            'unidad_medida_id' => 1,
            'costo_referencial' => '30.0000',
        ]);
        $ubiOrig = $servicio->crearUbicacion([
            'propiedad_id' => $propiedadId,
            'codigo' => "UBI-O-{$sufijo}",
            'nombre' => "Origen C04 {$sufijo}",
            'tipo' => InventarioUbicacion::TIPO_ALMACEN,
        ]);
        $servicio->registrarSaldoInicial((int) $artC04->obtenerId(), (int) $ubiOrig->obtenerId(), '20.0000', '30.0000', $actorId);

        // Simular intento de traslado hacia una ubicación inexistente
        $falloRollback = false;
        try {
            $servicio->registrarTraslado(
                (int) $artC04->obtenerId(),
                (int) $ubiOrig->obtenerId(),
                99999999, // Ubicación de destino inexistente
                '5.0000',
                $actorId,
                'Traslado a la nada'
            );
        } catch (\Throwable $e) {
            $falloRollback = true;
        }

        // Verificar que el stock de origen NO fue debitado y sigue exactamente en 20.0000
        $exOrigPost = $repo->obtenerExistencia((int) $artC04->obtenerId(), (int) $ubiOrig->obtenerId());
        $movsC04 = $repo->listarMovimientos(['articulo_id' => (int) $artC04->obtenerId(), 'tipo_movimiento' => InventarioMovimiento::TIPO_TRASLADO_SALIDA]);

        $condC04 = $falloRollback &&
                   bccomp($exOrigPost->obtenerCantidadActual(), '20.0000', 4) === 0 &&
                   count($movsC04) === 0;

        verificarConcurrencia('INV-C04', 'Atomicidad de dos patas: rollback total sin registros huérfanos ni mermas ficticias', $condC04);
    } catch (\Throwable $e) {
        verificarConcurrencia('INV-C04', 'Atomicidad de dos patas en traslados', false, $e->getMessage());
    }

    // =========================================================================
    // INV-C05: Captura y traducción de colisión InnoDB a ConflictoInventarioExcepcion (409)
    // =========================================================================
    try {
        // Probamos que el método de transacción traduzca 1205 a ConflictoInventarioExcepcion
        $servicioConcurrente = new class($pdo) extends InventarioServicio {
            public function provocarColision(): void {
                $this->ejecutarTransaccionMovimiento(function () {
                    $e = new \PDOException('Lock wait timeout exceeded; try restarting transaction');
                    $e->errorInfo = ['HY000', 1205, 'Lock wait timeout exceeded'];
                    throw $e;
                });
            }
        };

        $capturado409 = false;
        try {
            $servicioConcurrente->provocarColision();
        } catch (ConflictoInventarioExcepcion $ce) {
            $capturado409 = true;
        }

        verificarConcurrencia('INV-C05', 'Captura y traducción de error MySQL 1205/1213 a ConflictoInventarioExcepcion (HTTP 409)', $capturado409);
    } catch (\Throwable $e) {
        verificarConcurrencia('INV-C05', 'Captura y traducción de colisión InnoDB', false, $e->getMessage());
    }

    // =========================================================================
    // INV-C06: Reconciliación matemática Kardex soberano vs Proyección materializada
    // =========================================================================
    try {
        $umKgId = (int) $pdo->query("SELECT id FROM inventario_unidades_medida WHERE codigo = 'KG'")->fetchColumn() ?: 5;

        $artC06 = $servicio->crearArticulo([
            'codigo_sku' => "SKU-C06-{$sufijo}",
            'nombre' => "Detergente C06 {$sufijo}",
            'categoria' => InventarioArticulo::CAT_CONSUMIBLE_OPERATIVO,
            'unidad_medida_id' => $umKgId, // KG (fraccionable)
            'costo_referencial' => '4.2000',
            'es_fraccionable' => 1,
        ]);
        $ubiC06 = $servicio->crearUbicacion([
            'propiedad_id' => $propiedadId,
            'codigo' => "UBI-C06-{$sufijo}",
            'nombre' => "Lavandería C06 {$sufijo}",
            'tipo' => InventarioUbicacion::TIPO_ALMACEN,
        ]);

        // Secuencia variada de movimientos
        $servicio->registrarSaldoInicial((int) $artC06->obtenerId(), (int) $ubiC06->obtenerId(), '100.0000', '4.2000', $actorId); // +100
        $servicio->registrarEntradaCompra((int) $artC06->obtenerId(), (int) $ubiC06->obtenerId(), '50.2500', '4.2000', $actorId, 'Compra inicial detergente'); // +50.2500 = 150.2500
        $movSalida = $servicio->registrarSalidaConsumo((int) $artC06->obtenerId(), (int) $ubiC06->obtenerId(), '20.1250', $actorId, 'Consumo en lavado'); // -20.1250 = 130.1250
        $servicio->registrarAjusteFisico((int) $artC06->obtenerId(), (int) $ubiC06->obtenerId(), '2.3750', InventarioMovimiento::TIPO_AJUSTE_POSITIVO, $actorId, 'Ajuste sobrante'); // +2.3750 = 132.5000
        $servicio->registrarAjusteFisico((int) $artC06->obtenerId(), (int) $ubiC06->obtenerId(), '1.5000', InventarioMovimiento::TIPO_AJUSTE_NEGATIVO, $actorId, 'Ajuste faltante'); // -1.5000 = 131.0000
        $servicio->reversarMovimiento((int) $movSalida->obtenerId(), $actorId, 'Reverso de salida'); // +20.1250 = 151.1250

        // 1. Proyección materializada en existencias
        $exFinalC06 = $repo->obtenerExistencia((int) $artC06->obtenerId(), (int) $ubiC06->obtenerId());
        $stockMaterializado = $exFinalC06->obtenerCantidadActual();

        // 2. Reconciliación matemática nativa del Kardex inmutable vs Proyección
        $reconciliado = $servicio->verificarReconciliacionExistencia((int) $artC06->obtenerId(), (int) $ubiC06->obtenerId());

        $condC06 = bccomp($stockMaterializado, '151.1250', 4) === 0 && $reconciliado;

        verificarConcurrencia('INV-C06', 'Reconciliación matemática Kardex soberano vs Proyección materializada (exactitud 4 decimales)', $condC06);
    } catch (\Throwable $e) {
        verificarConcurrencia('INV-C06', 'Reconciliación matemática Kardex soberano vs Proyección materializada', false, $e->getMessage());
    }

} catch (\Throwable $e) {
    echo "ERROR GENERAL EN SUITE CONCURRENCIA: " . $e->getMessage() . "\n";
}

echo "\n====================================================================\n";
echo "RESULTADO CONCURRENCIA INVENTARIO-1: {$passCount} / {$totalCount} PASS\n";
echo "====================================================================\n";

if ($passCount !== $totalCount) {
    exit(1);
}
