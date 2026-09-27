<?php

declare(strict_types=1);

/**
 * Suite de Verificación INVENTARIO-1 — Matriz Exhaustiva de 40 Pruebas de Dominio
 * 
 * Verifica las decisiones y contratos vinculantes de D-078:
 * - ARTÍCULO != EXISTENCIA != MOVIMIENTO != ACTIVO INDIVIDUAL.
 * - Kardex append-only soberano vs proyección materializada.
 * - Traslados de dos patas con correlativo único atómico.
 * - Moneda funcional desacoplada (PEN), cantidades DECIMAL(15,4), costos DECIMAL(15,2).
 * - Integración no incremental con MANTENIMIENTO-1.
 * - Mitigación pesimista de concurrencia y stock no negativo.
 * - Activos serializados sin existencias cuantitativas.
 * - Auditoría de dotaciones estándar vs realidad.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CamargoPMS\Excepciones\ArticuloNoEncontradoExcepcion;
use CamargoPMS\Excepciones\ConflictoInventarioExcepcion;
use CamargoPMS\Excepciones\MovimientoInvalidoExcepcion;
use CamargoPMS\Excepciones\OrdenTrabajoNoEncontradaExcepcion;
use CamargoPMS\Excepciones\StockInsuficienteExcepcion;
use CamargoPMS\Excepciones\UbicacionNoEncontradaExcepcion;
use CamargoPMS\Excepciones\ValidacionExcepcion;
use CamargoPMS\Modelos\InventarioActivo;
use CamargoPMS\Modelos\InventarioArticulo;
use CamargoPMS\Modelos\InventarioDotacionEstandar;
use CamargoPMS\Modelos\InventarioExistencia;
use CamargoPMS\Modelos\InventarioMovimiento;
use CamargoPMS\Modelos\InventarioUbicacion;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Servicios\InventarioServicio;

Configuracion::cargar(dirname(__DIR__));
$pdo = BaseDatos::conexion();
$servicio = new InventarioServicio($pdo);
$repo = $servicio->obtenerRepositorio();

$totalPruebas = 0;
$pruebasExitosas = 0;
$errores = [];

function assertTest(bool $condicion, string $codigo, string $descripcion): void {
    global $totalPruebas, $pruebasExitosas, $errores;
    $totalPruebas++;
    if ($condicion) {
        $pruebasExitosas++;
        echo "  [PASS] {$codigo}: {$descripcion}\n";
    } else {
        $errores[] = "{$codigo}: {$descripcion}";
        echo "  [FAIL] {$codigo}: {$descripcion}\n";
    }
}

echo "=====================================================================\n";
echo "CAMARGO PMS — SUITE FORMAL INVENTARIO-1 (MATRIZ 40 CASOS)\n";
echo "=====================================================================\n\n";

// Datos base del entorno
$propiedadId = (int) $pdo->query("SELECT id FROM propiedades WHERE estado = 'ACTIVO' LIMIT 1")->fetchColumn();
if (!$propiedadId) {
    die("Error: No hay propiedades activas para ejecutar la prueba.\n");
}

$unidadId = (int) $pdo->query("SELECT id FROM unidades WHERE propiedad_id = {$propiedadId} AND estado = 'ACTIVO' LIMIT 1")->fetchColumn();
if (!$unidadId) {
    $unidadId = (int) $pdo->query("SELECT id FROM unidades LIMIT 1")->fetchColumn();
}
if (!$unidadId) {
    die("Error: No hay unidades registradas para ejecutar la prueba.\n");
}

$actorId = (int) $pdo->query("SELECT id FROM actores WHERE codigo = 'CAMARGO_PMS' LIMIT 1")->fetchColumn() ?: 1;
$sufijo = strtoupper(substr(uniqid(), -5));

// =========================================================================
// GRUPO 1: ONTOLOGÍA Y CATÁLOGO DE ARTÍCULOS (INV-01 A INV-07)
// =========================================================================
echo "--- GRUPO 1: Ontología y Catálogo de Artículos ---\n";

// INV-01
$skuConsumible = "SKU-AMN-{$sufijo}";
$artConsumible = $servicio->crearArticulo([
    'codigo_sku' => $skuConsumible,
    'nombre' => 'Shampoo Hotelero 30ml',
    'categoria' => InventarioArticulo::CAT_CONSUMIBLE_OPERATIVO,
    'unidad_medida_id' => 1, // UND
    'costo_referencial' => '0.8500',
    'stock_minimo_alerta' => '50.0000',
]);
assertTest($artConsumible->obtenerId() > 0 && $artConsumible->esConsumible(), 'INV-01', 'Creación de consumible operativo con unidad de medida y SKU único');

// INV-02
$skuDuplicadoLanzado = false;
try {
    $servicio->crearArticulo([
        'codigo_sku' => $skuConsumible,
        'nombre' => 'Otro Shampoo',
        'categoria' => InventarioArticulo::CAT_CONSUMIBLE_OPERATIVO,
        'unidad_medida_id' => 1,
    ]);
} catch (ValidacionExcepcion $e) {
    $skuDuplicadoLanzado = true;
}
assertTest($skuDuplicadoLanzado, 'INV-02', 'Rechazo de artículo con código SKU duplicado');

// INV-03
$validacionInvalidaLanzada = false;
try {
    $servicio->crearArticulo([
        'codigo_sku' => "SKU-INV-{$sufijo}",
        'nombre' => '',
        'categoria' => 'CATEGORIA_INVENTADA',
        'unidad_medida_id' => 1,
    ]);
} catch (ValidacionExcepcion $e) {
    $validacionInvalidaLanzada = true;
}
assertTest($validacionInvalidaLanzada, 'INV-03', 'Rechazo de artículo sin nombre y con categoría no permitida');

// INV-04
$skuLenceria = "SKU-LEN-{$sufijo}";
$artLenceria = $servicio->crearArticulo([
    'codigo_sku' => $skuLenceria,
    'nombre' => 'Sábana King 300 hilos',
    'categoria' => InventarioArticulo::CAT_LENCERIA_BLANCOS,
    'unidad_medida_id' => 1, // UND
    'costo_referencial' => '45.0000',
]);
assertTest($artLenceria->esLenceria(), 'INV-04', 'Creación de artículo de lencería y blancos');

// INV-05
$skuRepuesto = "SKU-REP-{$sufijo}";
$artRepuesto = $servicio->crearArticulo([
    'codigo_sku' => $skuRepuesto,
    'nombre' => 'Foco LED 9W Luz Cálida',
    'categoria' => InventarioArticulo::CAT_REPUESTO_MANTENIMIENTO,
    'unidad_medida_id' => 1,
    'costo_referencial' => '6.5000',
]);
assertTest($artRepuesto->esRepuesto(), 'INV-05', 'Creación de repuesto técnico para mantenimiento');

// INV-06
$skuActivo = "SKU-ACT-{$sufijo}";
$artActivo = $servicio->crearArticulo([
    'codigo_sku' => $skuActivo,
    'nombre' => 'Smart TV 55 Pulgadas 4K',
    'categoria' => InventarioArticulo::CAT_ACTIVO_SERIALIZABLE,
    'unidad_medida_id' => 1,
    'costo_referencial' => '1450.0000',
]);
assertTest($artActivo->esSerializable() && !$artActivo->esCuantificable(), 'INV-06', 'Creación de artículo serializable sin stock cuantitativo');

// INV-07
$artActualizado = $servicio->actualizarArticulo((int) $artConsumible->obtenerId(), [
    'nombre' => 'Shampoo Hotelero Premium 35ml',
    'costo_referencial' => '0.9200',
]);
assertTest($artActualizado->obtenerNombre() === 'Shampoo Hotelero Premium 35ml' && bccomp($artActualizado->obtenerCostoReferencial(), '0.9200', 4) === 0, 'INV-07', 'Actualización de artículo con persistencia de campos');

// =========================================================================
// GRUPO 2: UBICACIONES POLIMÓRFICAS (INV-08 A INV-13)
// =========================================================================
echo "\n--- GRUPO 2: Ubicaciones Polimórficas ---\n";

// INV-08
$codBodega = "UBI-BOD-{$sufijo}";
$ubiBodega = $servicio->crearUbicacion([
    'propiedad_id' => $propiedadId,
    'codigo' => $codBodega,
    'nombre' => 'Bodega Central de Almacén',
    'tipo' => InventarioUbicacion::TIPO_ALMACEN,
]);
assertTest($ubiBodega->obtenerId() > 0 && $ubiBodega->esAlmacen(), 'INV-08', 'Creación de ubicación tipo ALMACEN');

// INV-09
$ubiHab = $servicio->asegurarUbicacionParaUnidad($unidadId);
assertTest($ubiHab->esUnidad() && $ubiHab->obtenerUnidadId() === $unidadId, 'INV-09', 'Creación de ubicación tipo UNIDAD asociada a habitación física');

// INV-10
$rechazoUnidadInvalida = false;
try {
    $servicio->crearUbicacion([
        'propiedad_id' => $propiedadId,
        'codigo' => "UBI-ERR-{$sufijo}",
        'nombre' => 'Habitación sin ID',
        'tipo' => InventarioUbicacion::TIPO_UNIDAD,
        'unidad_id' => null,
    ]);
} catch (ValidacionExcepcion $e) {
    $rechazoUnidadInvalida = true;
}
assertTest($rechazoUnidadInvalida, 'INV-10', 'Rechazo de ubicación tipo UNIDAD sin unidad_id');

// INV-11
$proveedorId = (int) $pdo->query("SELECT id FROM proveedores WHERE estado = 'ACTIVO' LIMIT 1")->fetchColumn();
$codCustodia = "UBI-LAV-{$sufijo}";
$ubiCustodia = $servicio->crearUbicacion([
    'propiedad_id' => $propiedadId,
    'codigo' => $codCustodia,
    'nombre' => 'Lavandería Externa El Sol',
    'tipo' => InventarioUbicacion::TIPO_CUSTODIA_EXTERNA,
    'proveedor_id' => $proveedorId ?: null,
]);
assertTest($ubiCustodia->esCustodiaExterna(), 'INV-11', 'Creación de ubicación tipo CUSTODIA_EXTERNA para taller o lavandería');

// INV-12
$duplicadoUbiLanzado = false;
try {
    $servicio->crearUbicacion([
        'propiedad_id' => $propiedadId,
        'codigo' => $codBodega,
        'nombre' => 'Bodega Repetida',
        'tipo' => InventarioUbicacion::TIPO_ALMACEN,
    ]);
} catch (ValidacionExcepcion $e) {
    $duplicadoUbiLanzado = true;
}
assertTest($duplicadoUbiLanzado, 'INV-12', 'Rechazo de ubicación con código duplicado');

// INV-13
$ubiHabReconsultada = $servicio->asegurarUbicacionParaUnidad($unidadId);
assertTest($ubiHabReconsultada->obtenerId() === $ubiHab->obtenerId(), 'INV-13', 'Idempotencia de asegurarUbicacionParaUnidad() sin duplicar registros');

// =========================================================================
// GRUPO 3: SALDOS INICIALES Y PROYECCIÓN MATERIALIZADA (INV-14 A INV-18)
// =========================================================================
echo "\n--- GRUPO 3: Saldos Iniciales y Proyección Materializada ---\n";

// INV-14
$movSaldoIni = $servicio->registrarSaldoInicial(
    (int) $artConsumible->obtenerId(),
    (int) $ubiBodega->obtenerId(),
    '100.0000',
    '0.8500',
    $actorId,
    'Saldo inicial de prueba'
);
assertTest($movSaldoIni->obtenerTipoMovimiento() === InventarioMovimiento::TIPO_SALDO_INICIAL, 'INV-14', 'Registro de SALDO_INICIAL crea movimiento formal inmutable');

// INV-15
$rechazoSaldoActivo = false;
try {
    $servicio->registrarSaldoInicial(
        (int) $artActivo->obtenerId(),
        (int) $ubiBodega->obtenerId(),
        '10.0000',
        '1450.0000',
        $actorId
    );
} catch (MovimientoInvalidoExcepcion $e) {
    $rechazoSaldoActivo = true;
}
assertTest($rechazoSaldoActivo, 'INV-15', 'Rechazo de saldo inicial cuantitativo sobre ACTIVO_SERIALIZABLE');

// INV-16
$exConsumible = $repo->obtenerExistencia((int) $artConsumible->obtenerId(), (int) $ubiBodega->obtenerId());
assertTest($exConsumible !== null && bccomp($exConsumible->obtenerCantidadActual(), '100.0000', 4) === 0, 'INV-16', 'Proyección materializada: inventario_existencias refleja exactamente el saldo inicial');

// INV-17
$servicio->registrarSaldoInicial(
    (int) $artConsumible->obtenerId(),
    (int) $ubiBodega->obtenerId(),
    '50.0000',
    '0.8500',
    $actorId,
    'Segundo saldo inicial acumulativo'
);
$exConsumible2 = $repo->obtenerExistencia((int) $artConsumible->obtenerId(), (int) $ubiBodega->obtenerId());
assertTest(bccomp($exConsumible2->obtenerCantidadActual(), '150.0000', 4) === 0, 'INV-17', 'Acumulación coherente de stock en proyección materializada (150.0000)');

// INV-18
$artKg = $servicio->crearArticulo([
    'codigo_sku' => "SKU-DTR-{$sufijo}",
    'nombre' => 'Detergente Industrial Granulado',
    'categoria' => InventarioArticulo::CAT_CONSUMIBLE_OPERATIVO,
    'unidad_medida_id' => 5, // KG (admite_decimales = 1)
    'costo_referencial' => '12.4500',
]);
$movKg = $servicio->registrarSaldoInicial(
    (int) $artKg->obtenerId(),
    (int) $ubiBodega->obtenerId(),
    '25.7500',
    '12.4500',
    $actorId
);
$exKg = $repo->obtenerExistencia((int) $artKg->obtenerId(), (int) $ubiBodega->obtenerId());
assertTest(bccomp($exKg->obtenerCantidadActual(), '25.7500', 4) === 0, 'INV-18', 'Precisión decimal exacta de 4 dígitos en unidades fraccionables (25.7500 KG)');

// =========================================================================
// GRUPO 4: ENTRADAS Y SALIDAS DE CONSUMO (INV-19 A INV-24)
// =========================================================================
echo "\n--- GRUPO 4: Entradas y Salidas de Consumo ---\n";

// INV-19
$movEntrada = $servicio->registrarEntradaCompra(
    (int) $artConsumible->obtenerId(),
    (int) $ubiBodega->obtenerId(),
    '50.0000',
    '0.9000',
    $actorId,
    'Compra de reposición'
);
$exConsumible3 = $repo->obtenerExistencia((int) $artConsumible->obtenerId(), (int) $ubiBodega->obtenerId());
assertTest(bccomp($exConsumible3->obtenerCantidadActual(), '200.0000', 4) === 0, 'INV-19', 'ENTRADA_COMPRA incrementa existencias exactamente a 200.0000');

// INV-20
$movConsumo = $servicio->registrarSalidaConsumo(
    (int) $artConsumible->obtenerId(),
    (int) $ubiBodega->obtenerId(),
    '30.0000',
    $actorId,
    'Dotación de amenities a camareras'
);
$exConsumible4 = $repo->obtenerExistencia((int) $artConsumible->obtenerId(), (int) $ubiBodega->obtenerId());
assertTest(bccomp($exConsumible4->obtenerCantidadActual(), '170.0000', 4) === 0, 'INV-20', 'SALIDA_CONSUMO decrementa existencias exactamente a 170.0000');

// INV-21
$stockInsuficienteLanzado = false;
try {
    $servicio->registrarSalidaConsumo(
        (int) $artConsumible->obtenerId(),
        (int) $ubiBodega->obtenerId(),
        '500.0000', // Disponible solo 170
        $actorId,
        'Consumo excesivo'
    );
} catch (StockInsuficienteExcepcion $e) {
    $stockInsuficienteLanzado = true;
}
assertTest($stockInsuficienteLanzado, 'INV-21', 'StockInsuficienteExcepcion (HTTP 422) ante salida que excede stock disponible');

// INV-22
$cantidadNegativaRechazada = false;
try {
    $servicio->registrarSalidaConsumo(
        (int) $artConsumible->obtenerId(),
        (int) $ubiBodega->obtenerId(),
        '-10.0000',
        $actorId,
        'Cantidad negativa'
    );
} catch (ValidacionExcepcion $e) {
    $cantidadNegativaRechazada = true;
}
assertTest($cantidadNegativaRechazada, 'INV-22', 'Rechazo de cantidad negativa con ValidacionExcepcion');

// INV-23
$decimalNoPermitidoRechazado = false;
try {
    // artConsumible tiene unidad UND (admite_decimales = 0)
    $servicio->registrarEntradaCompra(
        (int) $artConsumible->obtenerId(),
        (int) $ubiBodega->obtenerId(),
        '10.5000',
        '0.8500',
        $actorId,
        'Fraccionamiento inválido'
    );
} catch (ValidacionExcepcion $e) {
    $decimalNoPermitidoRechazado = true;
}
assertTest($decimalNoPermitidoRechazado, 'INV-23', 'Rechazo de decimales en unidades no fraccionables (UND)');

// INV-24
$decimalPermitido = true;
try {
    // artKg tiene unidad KG (admite_decimales = 1)
    $servicio->registrarSalidaConsumo(
        (int) $artKg->obtenerId(),
        (int) $ubiBodega->obtenerId(),
        '5.2500',
        $actorId,
        'Consumo fraccionado'
    );
} catch (Throwable $e) {
    $decimalPermitido = false;
}
assertTest($decimalPermitido, 'INV-24', 'Aceptación de decimales en unidades fraccionables (KG)');

// =========================================================================
// GRUPO 5: INTEGRACIÓN CON MANTENIMIENTO-1 (INV-25 A INV-28)
// =========================================================================
echo "\n--- GRUPO 5: Integración no incremental con Mantenimiento-1 ---\n";

// Crear una orden de mantenimiento preventiva para las pruebas
$stmtOT = $pdo->prepare(
    "INSERT INTO mantenimiento_ordenes (
        codigo, tipo, prioridad, propiedad_id, unidad_id, titulo, descripcion,
        requiere_bloqueo, fecha_programada_inicio, fecha_programada_fin,
        costo_mano_obra, costo_materiales, costo_total, estado, creado_por_actor_id
    ) VALUES (
        :codigo, 'CORRECTIVO', 'MEDIA', :propiedad_id, :unidad_id, 'Cambio de focos', 'Reemplazo en suite',
        0, CURDATE(), CURDATE(),
        20.00, 0.00, 20.00, 'EN_PROCESO', :actor_id
    )"
);
$codigoOT = "OT-TEST-{$sufijo}";
$stmtOT->execute([
    'codigo' => $codigoOT,
    'propiedad_id' => $propiedadId,
    'unidad_id' => $unidadId,
    'actor_id' => $actorId,
]);
$ordenId = (int) $pdo->lastInsertId();

// Preparar stock de repuestos
$servicio->registrarSaldoInicial(
    (int) $artRepuesto->obtenerId(),
    (int) $ubiBodega->obtenerId(),
    '20.0000',
    '6.5000',
    $actorId
);

// INV-25
$movMnt1 = $servicio->registrarSalidaMantenimiento(
    (int) $artRepuesto->obtenerId(),
    (int) $ubiBodega->obtenerId(),
    '2.0000',
    $ordenId,
    $actorId,
    'Focos para lámparas'
);
$exRepuesto = $repo->obtenerExistencia((int) $artRepuesto->obtenerId(), (int) $ubiBodega->obtenerId());
assertTest(bccomp($exRepuesto->obtenerCantidadActual(), '18.0000', 4) === 0, 'INV-25', 'SALIDA_MANTENIMIENTO decrementa stock de repuestos');

// INV-26
$costoMaterialesOT = (string) $pdo->query("SELECT costo_materiales FROM mantenimiento_ordenes WHERE id = {$ordenId}")->fetchColumn();
$costoTotalOT = (string) $pdo->query("SELECT costo_total FROM mantenimiento_ordenes WHERE id = {$ordenId}")->fetchColumn();
assertTest(bccomp($costoMaterialesOT, '13.00', 2) === 0 && bccomp($costoTotalOT, '33.00', 2) === 0, 'INV-26', 'Recálculo no incremental del costo de materiales de la orden de trabajo (S/ 13.00)');

// INV-27
$movMnt2 = $servicio->registrarSalidaMantenimiento(
    (int) $artRepuesto->obtenerId(),
    (int) $ubiBodega->obtenerId(),
    '1.0000',
    $ordenId,
    $actorId,
    'Foco adicional para baño'
);
$costoMaterialesOT2 = (string) $pdo->query("SELECT costo_materiales FROM mantenimiento_ordenes WHERE id = {$ordenId}")->fetchColumn();
$costoTotalOT2 = (string) $pdo->query("SELECT costo_total FROM mantenimiento_ordenes WHERE id = {$ordenId}")->fetchColumn();
assertTest(bccomp($costoMaterialesOT2, '19.50', 2) === 0 && bccomp($costoTotalOT2, '39.50', 2) === 0, 'INV-27', 'Segunda salida técnica acumula exactamente el costo soberano de materiales (S/ 19.50)');

// INV-28
$otInexistenteRechazada = false;
try {
    $servicio->registrarSalidaMantenimiento(
        (int) $artRepuesto->obtenerId(),
        (int) $ubiBodega->obtenerId(),
        '1.0000',
        9999999, // Inexistente
        $actorId,
        'Orden falsa'
    );
} catch (OrdenTrabajoNoEncontradaExcepcion $e) {
    $otInexistenteRechazada = true;
}
assertTest($otInexistenteRechazada, 'INV-28', 'Rechazo de salida de mantenimiento sobre orden inexistente (404)');

// =========================================================================
// GRUPO 6: TRASLADO ATÓMICO DE DOS PATAS (INV-29 A INV-32)
// =========================================================================
echo "\n--- GRUPO 6: Traslado Atómico de Dos Patas ---\n";

// Preparar stock de lencería en bodega
$servicio->registrarSaldoInicial(
    (int) $artLenceria->obtenerId(),
    (int) $ubiBodega->obtenerId(),
    '50.0000',
    '45.0000',
    $actorId
);

// INV-29
$traslado = $servicio->registrarTraslado(
    (int) $artLenceria->obtenerId(),
    (int) $ubiBodega->obtenerId(),
    (int) $ubiHab->obtenerId(),
    '10.0000',
    $actorId,
    'Dotación de sábanas limpias a habitación'
);
$movSalida = $traslado[0];
$movEntrada = $traslado[1];
$exBodegaLen = $repo->obtenerExistencia((int) $artLenceria->obtenerId(), (int) $ubiBodega->obtenerId());
$exHabLen = $repo->obtenerExistencia((int) $artLenceria->obtenerId(), (int) $ubiHab->obtenerId());

$consumoLenceriaRechazado = false;
try {
    $servicio->registrarSalidaConsumo(
        (int) $artLenceria->obtenerId(),
        (int) $ubiBodega->obtenerId(),
        '5.0000',
        $actorId,
        'Intento erróneo de consumir toallas/sábanas'
    );
} catch (MovimientoInvalidoExcepcion $e) {
    $consumoLenceriaRechazado = true;
}

$patrimonioTotalPreservado = bcadd($exBodegaLen->obtenerCantidadActual(), $exHabLen->obtenerCantidadActual(), 4) === '50.0000';

assertTest(
    $movSalida->obtenerTipoMovimiento() === InventarioMovimiento::TIPO_TRASLADO_SALIDA &&
    $movEntrada->obtenerTipoMovimiento() === InventarioMovimiento::TIPO_TRASLADO_ENTRADA &&
    bccomp($exBodegaLen->obtenerCantidadActual(), '40.0000', 4) === 0 &&
    bccomp($exHabLen->obtenerCantidadActual(), '10.0000', 4) === 0 &&
    $patrimonioTotalPreservado &&
    $consumoLenceriaRechazado,
    'INV-29',
    'Traslado atómico de lencería preserva patrimonio total (50.0000) y rechaza salida por consumo ordinario'
);

// INV-30
assertTest(
    $movSalida->obtenerCorrelativoOperacion() === $movEntrada->obtenerCorrelativoOperacion() &&
    $movEntrada->obtenerMovimientoReferenciaId() === $movSalida->obtenerId(),
    'INV-30',
    'Ambas patas comparten correlativo_operacion único y vinculación referencial'
);

// INV-31
$trasladoInsuficienteRechazado = false;
try {
    $servicio->registrarTraslado(
        (int) $artLenceria->obtenerId(),
        (int) $ubiBodega->obtenerId(),
        (int) $ubiHab->obtenerId(),
        '999.0000', // Insuficiente
        $actorId,
        'Traslado imposible'
    );
} catch (StockInsuficienteExcepcion $e) {
    $trasladoInsuficienteRechazado = true;
}
$exBodegaPostFallo = $repo->obtenerExistencia((int) $artLenceria->obtenerId(), (int) $ubiBodega->obtenerId());
assertTest(
    $trasladoInsuficienteRechazado && bccomp($exBodegaPostFallo->obtenerCantidadActual(), '40.0000', 4) === 0,
    'INV-31',
    'Traslado falla con StockInsuficienteExcepcion y revierte limpiamente ambas existencias'
);

// INV-32
$mismaUbiRechazada = false;
try {
    $servicio->registrarTraslado(
        (int) $artLenceria->obtenerId(),
        (int) $ubiBodega->obtenerId(),
        (int) $ubiBodega->obtenerId(),
        '5.0000',
        $actorId,
        'Misma ubicación'
    );
} catch (MovimientoInvalidoExcepcion $e) {
    $mismaUbiRechazada = true;
}
assertTest($mismaUbiRechazada, 'INV-32', 'Rechazo de traslado cuando origen y destino coinciden');

// =========================================================================
// GRUPO 7: AJUSTES FÍSICOS Y REVERSOS INMUTABLES (INV-33 A INV-36)
// =========================================================================
echo "\n--- GRUPO 7: Ajustes Físicos y Reversos Inmutables ---\n";

// INV-33
$movAjustePos = $servicio->registrarAjusteFisico(
    (int) $artConsumible->obtenerId(),
    (int) $ubiBodega->obtenerId(),
    '5.0000',
    InventarioMovimiento::TIPO_AJUSTE_POSITIVO,
    $actorId,
    'Sobrante detectado en inventario físico'
);
$exAjustePos = $repo->obtenerExistencia((int) $artConsumible->obtenerId(), (int) $ubiBodega->obtenerId());
assertTest(bccomp($exAjustePos->obtenerCantidadActual(), '175.0000', 4) === 0, 'INV-33', 'AJUSTE_POSITIVO incrementa existencias exactamente a 175.0000');

// INV-34
$movAjusteNeg = $servicio->registrarAjusteFisico(
    (int) $artConsumible->obtenerId(),
    (int) $ubiBodega->obtenerId(),
    '3.0000',
    InventarioMovimiento::TIPO_AJUSTE_NEGATIVO,
    $actorId,
    'Merma por empaque dañado'
);
$exAjusteNeg = $repo->obtenerExistencia((int) $artConsumible->obtenerId(), (int) $ubiBodega->obtenerId());
assertTest(bccomp($exAjusteNeg->obtenerCantidadActual(), '172.0000', 4) === 0, 'INV-34', 'AJUSTE_NEGATIVO decrementa existencias exactamente a 172.0000');

// INV-35
$reverso = $servicio->reversarMovimiento((int) $movConsumo->obtenerId(), $actorId, 'Consumo registrado por error');
$exPostReverso = $repo->obtenerExistencia((int) $artConsumible->obtenerId(), (int) $ubiBodega->obtenerId());
assertTest(
    $reverso->obtenerTipoMovimiento() === InventarioMovimiento::TIPO_REVERSO &&
    bccomp($exPostReverso->obtenerCantidadActual(), '202.0000', 4) === 0,
    'INV-35',
    'reversarMovimiento() neutraliza salida previa y reintegra stock con movimiento REVERSO'
);

// INV-36
$reversoDeReversoRechazado = false;
try {
    $servicio->reversarMovimiento((int) $reverso->obtenerId(), $actorId, 'Reversar el reverso');
} catch (MovimientoInvalidoExcepcion $e) {
    $reversoDeReversoRechazado = true;
}

$reversoDuplicadoRechazado = false;
try {
    $servicio->reversarMovimiento((int) $movConsumo->obtenerId(), $actorId, 'Segundo reverso del mismo consumo');
} catch (MovimientoInvalidoExcepcion $e) {
    $reversoDuplicadoRechazado = true;
}

assertTest(
    $reversoDeReversoRechazado && $reversoDuplicadoRechazado,
    'INV-36',
    'Rechazo estricto de reverso sobre REVERSO y de reversos duplicados sobre el mismo movimiento original'
);

// =========================================================================
// GRUPO 8: ACTIVOS SERIALIZABLES Y DOTACIONES (INV-37 A INV-40)
// =========================================================================
echo "\n--- GRUPO 8: Activos Serializables y Dotaciones ---\n";

// INV-37
$placaActivo = "PLACA-{$sufijo}-01";
$activo1 = $servicio->registrarActivo([
    'articulo_id' => $artActivo->obtenerId(),
    'codigo_placa' => $placaActivo,
    'numero_serie_fabricante' => "SN-SAMS-{$sufijo}",
    'marca' => 'Samsung',
    'modelo' => 'UN55CU7000',
    'propiedad_id' => $propiedadId,
    'ubicacion_id' => $ubiBodega->obtenerId(),
    'costo_adquisicion' => '1420.00',
], $actorId);
assertTest($activo1->estaDisponible() && $activo1->obtenerCodigoPlaca() === $placaActivo, 'INV-37', 'Registro de ACTIVO_SERIALIZABLE en estado DISPONIBLE');

// INV-38
$placaDuplicadaRechazada = false;
try {
    $servicio->registrarActivo([
        'articulo_id' => $artActivo->obtenerId(),
        'codigo_placa' => $placaActivo,
        'propiedad_id' => $propiedadId,
        'ubicacion_id' => $ubiBodega->obtenerId(),
    ], $actorId);
} catch (ValidacionExcepcion $e) {
    $placaDuplicadaRechazada = true;
}
assertTest($placaDuplicadaRechazada, 'INV-38', 'Rechazo de activo con placa patrimonial duplicada');

// INV-39
$activoAsignado = $servicio->asignarActivoAUnidad((int) $activo1->obtenerId(), (int) $ubiHab->obtenerId(), $actorId);
assertTest($activoAsignado->estaAsignado() && $activoAsignado->obtenerUbicacionId() === $ubiHab->obtenerId(), 'INV-39', 'Asignación de activo a habitación actualiza estado = ASIGNADO y ubicación física');

// INV-40
// Configurar dotación estándar para la unidad de prueba: 2 sábanas
$servicio->configurarDotacionEstandar([
    'unidad_id' => $unidadId,
    'articulo_id' => $artLenceria->obtenerId(),
    'cantidad_estandar' => '2.0000',
]);
$auditoria = $servicio->auditarDotacionUnidad($unidadId);
$itemSabana = null;
foreach ($auditoria as $item) {
    if ($item['articulo_id'] === $artLenceria->obtenerId()) {
        $itemSabana = $item;
        break;
    }
}
assertTest(
    $itemSabana !== null &&
    bccomp($itemSabana['cantidad_estandar'], '2.0000', 4) === 0 &&
    bccomp($itemSabana['cantidad_real'], '10.0000', 4) === 0 &&
    $itemSabana['cumple'] === true,
    'INV-40',
    'Auditoría de dotación contrasta estándar vs realidad física de existencias en habitación'
);

echo "\n=====================================================================\n";
echo "RESULTADO MATRIZ INVENTARIO-1: {$pruebasExitosas} / {$totalPruebas} PASS\n";
echo "=====================================================================\n";

if (!empty($errores)) {
    echo "\nERRORES ENCONTRADOS:\n";
    foreach ($errores as $err) {
        echo "  - {$err}\n";
    }
    exit(1);
}

exit(0);
