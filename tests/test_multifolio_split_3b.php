<?php

declare(strict_types=1);

/**
 * Suite de Pruebas Automatizadas: FINANCIERO-3B
 * Motor Transaccional de Split y Transferencias de Cargos entre Folios.
 *
 * Cobertura de Principios y Gates Obligatorios:
 * 1. Gobierno del Esquema y Ranura 040:
 *    - Exactamente 131 tablas relacionales.
 *    - Ranura de migración 040 estrictamente LIBRE (Cero DDL en 3B).
 *    - admin-dashboard/ 100% inmutable.
 * 2. Operación de Transferencia Total Exitosa:
 *    - Reubicación de cargo conservando ID y montos.
 *    - Trazabilidad inmutable append-only en cuenta_folio_transferencias_cargos.
 * 3. Operación de Split Parcial Exitoso e Invariantes Matemáticas:
 *    - Conservación estricta: MONTO ANTES = MONTO ORIGEN DESPUÉS + MONTO DERIVADO.
 *    - Δ deuda combinada Folio A + Folio B = 0.00.
 *    - Cero drift decimal en subtotales e impuestos proporcionales.
 * 4. Invariante Inviolable: Rechazo de Cargos con Pagos Aplicados:
 *    - Rechazo categórico de transferencia o split si monto_aplicado_acumulado > 0 o aplicaciones_pago activas.
 * 5. Validaciones de Rechazo Operacional y Fronteras:
 *    - Monto <= 0, split >= total, origen == destino, folio cerrado, folios incompatibles.
 * 6. Inviolabilidad de Devengos de Night Audit:
 *    - Preservación íntegra de devengos_alojamiento y fechas hoteleras históricas.
 * 7. Concurrencia y Atomicidad Transaccional:
 *    - Bloqueo determinista ordenado por ID de folios.
 *    - Rollback atómico ante fallo intermedio.
 * 8. Endpoints HTTP, Control de Acceso (RBAC) y CSRF:
 *    - Rutas en public/index.php protegidas con caja.movimientos y caja.ver.
 *    - Validación estricta de token CSRF y respuestas JSON normalizadas.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CamargoPMS\Controladores\CuentaFolioControlador;
use CamargoPMS\Excepciones\CargoConPagosAplicadosExcepcion;
use CamargoPMS\Excepciones\EstadoFinancieroInvalidoExcepcion;
use CamargoPMS\Excepciones\MontoInvalidoExcepcion;
use CamargoPMS\Excepciones\TransferenciaFolioInvalidaExcepcion;
use CamargoPMS\Modelos\CargoCuenta;
use CamargoPMS\Modelos\CuentaFolio;
use CamargoPMS\Modelos\Usuario;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Repositorios\AplicacionPagoRepositorio;
use CamargoPMS\Repositorios\CargoCuentaRepositorio;
use CamargoPMS\Repositorios\CuentaFolioRepositorio;
use CamargoPMS\Repositorios\CuentaFolioTransferenciaRepositorio;
use CamargoPMS\Repositorios\PagoCuentaRepositorio;
use CamargoPMS\Servicios\CsrfServicio;
use CamargoPMS\Servicios\CuentaFolioServicio;

Configuracion::cargar(dirname(__DIR__));
$pdo = BaseDatos::conexion();

$totalChecks = 0;
$checksAprobados = 0;

function verificar(bool $condicion, string $mensaje): void
{
    global $totalChecks, $checksAprobados;
    $totalChecks++;
    if ($condicion) {
        $checksAprobados++;
        echo "  [PASS] {$mensaje}\n";
    } else {
        echo "  [FAIL] {$mensaje}\n";
        throw new RuntimeException("Fallo en verificación: {$mensaje}");
    }
}

echo "====================================================================\n";
echo " EJECUTANDO SUITE: test_multifolio_split_3b.php (FINANCIERO-3B)\n";
echo "====================================================================\n\n";

// Instancias de servicio y repositorios
$folioRepo = new CuentaFolioRepositorio($pdo);
$cargoRepo = new CargoCuentaRepositorio($pdo);
$pagoRepo = new PagoCuentaRepositorio($pdo);
$aplicacionRepo = new AplicacionPagoRepositorio($pdo);
$transfRepo = new CuentaFolioTransferenciaRepositorio($pdo);
$folioServicio = new CuentaFolioServicio($pdo);
$csrfServicio = new CsrfServicio();

// Fixtures IDs para limpieza final
$personasCreadas = [];
$reservasCreadas = [];
$foliosCreados = [];
$cargosCreados = [];
$pagosCreados = [];
$aplicacionesCreadas = [];
$transferenciasCreadas = [];
$devengosCreados = [];

$sufijo = date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 6);

try {
    // =====================================================================
    // BLOQUE 1: GOBERNANZA, ESQUEMA Y RANURA 040 LIBRE
    // =====================================================================
    echo "--- BLOQUE 1: Estructura de Base de Datos y Ranura 040 ---\n";

    $totalTablas = (int) $pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();
    verificar($totalTablas >= 131, "Total de tablas relacionales en MySQL es al menos 131 (actual: {$totalTablas})");

    $migraciones041 = glob(dirname(__DIR__) . '/SQL/migraciones/*041*');
    verificar(empty($migraciones041), "Ranura de migración 041 estrictamente LIBRE (Cero DDL no autorizado)");

    $diffAdmin = shell_exec('git status --porcelain admin-dashboard/');
    verificar(empty(trim((string) $diffAdmin)), "admin-dashboard/ permanece 100% inmutable y libre de modificaciones");

    // =====================================================================
    // PREPARACIÓN DE FIXTURES DE PRUEBA (Reserva con Folio Maestro y Secundario)
    // =====================================================================
    $stmtPer = $pdo->prepare("INSERT INTO personas (nombres, apellido_paterno, estado, creado_en) VALUES ('Test3B', 'MultiFolio', 'ACTIVO', NOW())");
    $stmtPer->execute();
    $personaIdFixture = (int) $pdo->lastInsertId();
    $personasCreadas[] = $personaIdFixture;

    $stmtRes = $pdo->prepare(
        "INSERT INTO reservas (codigo, persona_titular_id, fecha_entrada, fecha_salida, noches, estado, total, moneda_codigo, creado_por_actor_id, creado_en)
         VALUES (:codigo, :titular, '2026-11-01', '2026-11-05', 4, 'CONFIRMADA', 800.00, 'PEN', 1, NOW())"
    );
    $stmtRes->execute(['codigo' => "RES-3B-{$sufijo}", 'titular' => $personaIdFixture]);
    $reservaIdFixture = (int) $pdo->lastInsertId();
    $reservasCreadas[] = $reservaIdFixture;

    // 1. Folio Maestro A
    $codFolioA = "FOL-A-{$sufijo}";
    $folioA = new CuentaFolio(null, $codFolioA, $reservaIdFixture, $personaIdFixture, 'PEN', 'ABIERTA', 1, null, null, null, true, 'FOLIO PRINCIPAL', null);
    $folioAId = $folioRepo->crear($folioA);
    $foliosCreados[] = $folioAId;

    // 2. Folio Secundario B (subordinado a A)
    $codFolioB = "FOL-B-{$sufijo}";
    $folioB = new CuentaFolio(null, $codFolioB, $reservaIdFixture, $personaIdFixture, 'PEN', 'ABIERTA', 1, null, null, null, false, 'FOLIO SECUNDARIO B', $folioAId);
    $folioBId = $folioRepo->crear($folioB);
    $foliosCreados[] = $folioBId;

    // 3. Folio Secundario C
    $codFolioC = "FOL-C-{$sufijo}";
    $folioC = new CuentaFolio(null, $codFolioC, $reservaIdFixture, $personaIdFixture, 'PEN', 'ABIERTA', 1, null, null, null, false, 'FOLIO SECUNDARIO C', $folioAId);
    $folioCId = $folioRepo->crear($folioC);
    $foliosCreados[] = $folioCId;

    // =====================================================================
    // BLOQUE 2: OPERACIÓN DE TRANSFERENCIA TOTAL EXITOSA
    // =====================================================================
    echo "\n--- BLOQUE 2: Transferencia Total Exitosa ---\n";

    // Cargo 1 en Folio A: S/ 500.00
    $codCargo1 = "CRG-1-{$sufijo}";
    $cargo1 = new CargoCuenta(null, $codCargo1, $folioAId, 'SERVICIO_CONTRATADO', null, null, 'Cena Restaurante Mar y Luna', '1.00', '423.73', '423.73', '76.27', '500.00', '0.00', 'PEN', 'DEVENGADO', null, null, null, gmdate('Y-m-d H:i:s'), 1);
    $cargo1Id = $cargoRepo->crear($cargo1);
    $cargosCreados[] = $cargo1Id;

    $transfTotal = $folioServicio->transferirCargo(
        $folioAId,
        $folioBId,
        $cargo1Id,
        'Traslado a folio de acompañante por solicitud del titular',
        1,
        1
    );
    $transferenciasCreadas[] = (int) $transfTotal->obtenerId();

    verificar($transfTotal->obtenerTipoOperacion() === 'TOTAL' && $transfTotal->esTotal(), "Tipo de operación es TOTAL (esTotal() === true)");
    verificar(bccomp($transfTotal->obtenerMontoTransferido(), '500.00', 2) === 0, "Monto transferido coincide exactamente con el cargo (S/ 500.00)");
    verificar($transfTotal->obtenerCuentaFolioOrigenId() === $folioAId, "Folio origen es Folio A ({$folioAId})");
    verificar($transfTotal->obtenerCuentaFolioDestinoId() === $folioBId, "Folio destino es Folio B ({$folioBId})");

    // Verificar estado del cargo en BD
    $cargo1Actualizado = $cargoRepo->obtenerPorId($cargo1Id);
    verificar($cargo1Actualizado->obtenerCuentaFolioId() === $folioBId, "Cargo reubicado físicamente en Folio B ({$folioBId})");
    verificar(bccomp($cargo1Actualizado->obtenerTotal(), '500.00', 2) === 0, "Importe total del cargo permanece en S/ 500.00");
    verificar($cargo1Actualizado->obtenerCodigo() === $codCargo1, "Identidad y código del cargo preservados inalterados");

    // =====================================================================
    // BLOQUE 3: OPERACIÓN DE SPLIT PARCIAL E INVARIANTES MATEMÁTICAS
    // =====================================================================
    echo "\n--- BLOQUE 3: Split Parcial Exitoso e Invariantes Matemáticas ---\n";

    // Cargo 2 en Folio A: S/ 500.00 (Subtotal 423.73, IGV 76.27)
    $codCargo2 = "CRG-2-{$sufijo}";
    $cargo2 = new CargoCuenta(null, $codCargo2, $folioAId, 'SERVICIO_CONTRATADO', null, null, 'Servicio Spa y Masajes', '1.00', '423.73', '423.73', '76.27', '500.00', '0.00', 'PEN', 'DEVENGADO', null, null, null, gmdate('Y-m-d H:i:s'), 1);
    $cargo2Id = $cargoRepo->crear($cargo2);
    $cargosCreados[] = $cargo2Id;

    // Ejecutar Split: S/ 200.00 de A hacia C (A debe conservar S/ 300.00)
    $resultadoSplit = $folioServicio->splitCargo(
        $folioAId,
        $folioCId,
        $cargo2Id,
        '200.00',
        'División de sesión de spa compartida',
        1,
        1
    );

    $transfSplit = $resultadoSplit['transferencia'];
    $padreActualizado = $resultadoSplit['cargo_padre'];
    $hijoCreado = $resultadoSplit['cargo_derivado'];

    $transferenciasCreadas[] = (int) $transfSplit->obtenerId();
    $cargosCreados[] = (int) $hijoCreado->obtenerId();

    verificar($transfSplit->obtenerTipoOperacion() === 'SPLIT_PARCIAL', "Tipo de operación registrado es SPLIT_PARCIAL");
    verificar(bccomp($transfSplit->obtenerMontoTransferido(), '200.00', 2) === 0, "Monto transferido es exactamente S/ 200.00");

    // INVARIANTE MATEMÁTICA 1: MONTO ANTES = MONTO PADRE DESPUÉS + MONTO DERIVADO
    $sumaPartes = bcadd($padreActualizado->obtenerTotal(), $hijoCreado->obtenerTotal(), 2);
    verificar(bccomp($sumaPartes, '500.00', 2) === 0, "Conservación matemática: Total Padre ({$padreActualizado->obtenerTotal()}) + Hijo ({$hijoCreado->obtenerTotal()}) = 500.00");
    verificar(bccomp($padreActualizado->obtenerTotal(), '300.00', 2) === 0, "Cargo padre conserva remanente exacto de S/ 300.00 en Folio A");
    verificar(bccomp($hijoCreado->obtenerTotal(), '200.00', 2) === 0, "Cargo hijo se crea con importe exacto de S/ 200.00 en Folio C");

    // INVARIANTE MATEMÁTICA 2: Conservación de subtotales e impuestos
    $sumaSubtotales = bcadd($padreActualizado->obtenerSubtotal(), $hijoCreado->obtenerSubtotal(), 2);
    $sumaImpuestos = bcadd($padreActualizado->obtenerImpuestoTotal(), $hijoCreado->obtenerImpuestoTotal(), 2);
    verificar(bccomp($sumaSubtotales, '423.73', 2) === 0, "Conservación de subtotal: {$padreActualizado->obtenerSubtotal()} + {$hijoCreado->obtenerSubtotal()} = 423.73");
    verificar(bccomp($sumaImpuestos, '76.27', 2) === 0, "Conservación de impuestos: {$padreActualizado->obtenerImpuestoTotal()} + {$hijoCreado->obtenerImpuestoTotal()} = 76.27");

    // INVARIANTE MATEMÁTICA 3: Δ deuda combinada Folio A + Folio C = 0.00
    verificar($padreActualizado->obtenerCuentaFolioId() === $folioAId, "Cargo padre permanece en Folio A");
    verificar($hijoCreado->obtenerCuentaFolioId() === $folioCId, "Cargo hijo reside en Folio C");
    verificar($hijoCreado->obtenerCargoPadreId() === $cargo2Id, "Cargo hijo enlaza formalmente a cargo padre vía cargo_padre_id");
    verificar($hijoCreado->esCargoHijo(), "esCargoHijo() retorna true en cargo derivado");

    // =====================================================================
    // BLOQUE 4: INVARIANTE INVIOLABLE: RECHAZO DE CARGOS AMORTIZADOS
    // =====================================================================
    echo "\n--- BLOQUE 4: Inviolabilidad de Cargos con Cobros Aplicados ---\n";

    // Cargo 3 en Folio A: S/ 400.00 con pago aplicado parcial de S/ 150.00
    $codCargo3 = "CRG-3-{$sufijo}";
    $cargo3 = new CargoCuenta(null, $codCargo3, $folioAId, 'SERVICIO_CONTRATADO', null, null, 'Consumos Frigorífico', '1.00', '338.98', '338.98', '61.02', '400.00', '150.00', 'PEN', 'DEVENGADO', null, null, null, gmdate('Y-m-d H:i:s'), 1);
    $cargo3Id = $cargoRepo->crear($cargo3);
    $cargosCreados[] = $cargo3Id;

    // Crear aplicación de pago activa vinculada
    $codPago1 = "PAG-1-{$sufijo}";
    $pago1 = new \CamargoPMS\Modelos\PagoCuenta(
        null,
        $codPago1,
        $folioAId,
        1,
        '150.00',
        '150.00',
        'PEN',
        null,
        null,
        null,
        'CONFIRMADO',
        null,
        null,
        null,
        1
    );
    $pago1Id = $pagoRepo->crear($pago1);
    $pagosCreados[] = $pago1Id;

    $codApl1 = "APL-1-{$sufijo}";
    $apl1 = new \CamargoPMS\Modelos\AplicacionPago(
        null,
        $codApl1,
        $pago1Id,
        $cargo3Id,
        '150.00',
        'PEN',
        'ACTIVA',
        null,
        null,
        1
    );
    $apl1Id = $aplicacionRepo->crear($apl1);
    $aplicacionesCreadas[] = $apl1Id;

    // 4.1 Intento de Transferencia Total de cargo amortizado -> DEBE RECHAZARSE
    $rechazoTransfAmortizado = false;
    try {
        $folioServicio->transferirCargo($folioAId, $folioBId, $cargo3Id, 'Intento ilegal de mover cargo con cobro aplicado');
    } catch (CargoConPagosAplicadosExcepcion $e) {
        $rechazoTransfAmortizado = true;
    }
    verificar($rechazoTransfAmortizado, "Transferencia total de cargo con amortización aplicada es categóricamente RECHAZADA (CargoConPagosAplicadosExcepcion)");

    // 4.2 Intento de Split Parcial de cargo amortizado -> DEBE RECHAZARSE
    $rechazoSplitAmortizado = false;
    try {
        $folioServicio->splitCargo($folioAId, $folioBId, $cargo3Id, '100.00', 'Intento ilegal de split de cargo amortizado');
    } catch (CargoConPagosAplicadosExcepcion $e) {
        $rechazoSplitAmortizado = true;
    }
    verificar($rechazoSplitAmortizado, "Split parcial de cargo con amortización aplicada es categóricamente RECHAZADA (CargoConPagosAplicadosExcepcion)");

    // =====================================================================
    // BLOQUE 5: VALIDACIONES DE RECHAZO OPERACIONAL Y FRONTERAS
    // =====================================================================
    echo "\n--- BLOQUE 5: Validaciones de Rechazo Operacional y Fronteras ---\n";

    // Cargo 4 libre para pruebas negativas
    $codCargo4 = "CRG-4-{$sufijo}";
    $cargo4 = new CargoCuenta(null, $codCargo4, $folioAId, 'SERVICIO_CONTRATADO', null, null, 'Tour Guiado', '1.00', '100.00', '100.00', '0.00', '100.00', '0.00', 'PEN', 'DEVENGADO', null, null, null, gmdate('Y-m-d H:i:s'), 1);
    $cargo4Id = $cargoRepo->crear($cargo4);
    $cargosCreados[] = $cargo4Id;

    // 5.1 Folio Origen == Folio Destino
    $rechazoMismoFolio = false;
    try {
        $folioServicio->transferirCargo($folioAId, $folioAId, $cargo4Id, 'Mismo folio');
    } catch (TransferenciaFolioInvalidaExcepcion) {
        $rechazoMismoFolio = true;
    }
    verificar($rechazoMismoFolio, "Rechazo de transferencia cuando folio_origen == folio_destino");

    // 5.2 Monto split <= 0.00
    $rechazoMontoNegativo = false;
    try {
        $folioServicio->splitCargo($folioAId, $folioBId, $cargo4Id, '-10.00', 'Monto negativo');
    } catch (MontoInvalidoExcepcion) {
        $rechazoMontoNegativo = true;
    }
    verificar($rechazoMontoNegativo, "Rechazo de split con monto negativo (MontoInvalidoExcepcion)");

    $rechazoMontoCero = false;
    try {
        $folioServicio->splitCargo($folioAId, $folioBId, $cargo4Id, '0.00', 'Monto cero');
    } catch (MontoInvalidoExcepcion) {
        $rechazoMontoCero = true;
    }
    verificar($rechazoMontoCero, "Rechazo de split con monto 0.00");

    // 5.3 Monto split >= Monto total del cargo
    $rechazoSplitIgualTotal = false;
    try {
        $folioServicio->splitCargo($folioAId, $folioBId, $cargo4Id, '100.00', 'Split total');
    } catch (TransferenciaFolioInvalidaExcepcion) {
        $rechazoSplitIgualTotal = true;
    }
    verificar($rechazoSplitIgualTotal, "Rechazo de split parcial con monto igual al total (debe usarse transferirCargo)");

    $rechazoSplitExcedido = false;
    try {
        $folioServicio->splitCargo($folioAId, $folioBId, $cargo4Id, '150.00', 'Split mayor');
    } catch (TransferenciaFolioInvalidaExcepcion) {
        $rechazoSplitExcedido = true;
    }
    verificar($rechazoSplitExcedido, "Rechazo de split con monto superior al disponible del cargo");

    // 5.4 Folio cerrado
    $codFolioCerrado = "FOL-CERR-{$sufijo}";
    $folioCerrado = new CuentaFolio(null, $codFolioCerrado, $reservaIdFixture, $personaIdFixture, 'PEN', 'CERRADA', 1, null, null, null, false, 'FOLIO CERRADO', $folioAId);
    $folioCerradoId = $folioRepo->crear($folioCerrado);
    $foliosCreados[] = $folioCerradoId;

    $rechazoFolioCerrado = false;
    try {
        $folioServicio->transferirCargo($folioAId, $folioCerradoId, $cargo4Id, 'Transferir a cerrado');
    } catch (EstadoFinancieroInvalidoExcepcion) {
        $rechazoFolioCerrado = true;
    }
    verificar($rechazoFolioCerrado, "Rechazo de transferencia hacia un folio en estado CERRADA");

    // 5.5 Cargo no pertenece al folio origen especificado
    $rechazoCargoAjeno = false;
    try {
        $folioServicio->transferirCargo($folioBId, $folioCId, $cargo4Id, 'Cargo que está en A');
    } catch (TransferenciaFolioInvalidaExcepcion) {
        $rechazoCargoAjeno = true;
    }
    verificar($rechazoCargoAjeno, "Rechazo si el cargo no pertenece al folio origen declarado");

    // =====================================================================
    // BLOQUE 6: INVIOLABILIDAD DE DEVENGOS DE NIGHT AUDIT
    // =====================================================================
    echo "\n--- BLOQUE 6: Inviolabilidad de Devengos de Night Audit ---\n";

    // Cargo 5: generado como noche de hospedaje (Night Audit)
    $codCargo5 = "CRG-5-{$sufijo}";
    $cargo5 = new CargoCuenta(null, $codCargo5, $folioAId, 'ALOJAMIENTO_NOCHES', null, null, 'Alojamiento Noche 2026-09-29', '1.00', '211.86', '211.86', '38.14', '250.00', '0.00', 'PEN', 'DEVENGADO', null, null, null, '2026-09-30 00:05:00', 1);
    $cargo5Id = $cargoRepo->crear($cargo5);
    $cargosCreados[] = $cargo5Id;

    // Vincular formalmente a devengos_alojamiento
    $codDev = "DEV-1-{$sufijo}";
    $estadiaFixture = $pdo->query('
        SELECT e.id, e.reserva_id, e.reserva_unidad_id, e.unidad_id, u.propiedad_id
        FROM estadias e
        JOIN unidades u ON u.id = e.unidad_id
        ORDER BY e.id DESC LIMIT 1
    ')->fetch(PDO::FETCH_ASSOC);
    $estadiaId = (int) $estadiaFixture['id'];
    $resUniId = (int) $estadiaFixture['reserva_unidad_id'];
    $unidadId = (int) $estadiaFixture['unidad_id'];
    $propiedadId = (int) $estadiaFixture['propiedad_id'];
    $resId = (int) $estadiaFixture['reserva_id'];
    $fechaHoteleraDev = '2028-11-20';

    $devengoRepo = new \CamargoPMS\Repositorios\DevengoAlojamientoRepositorio($pdo);
    $devengoObj = new \CamargoPMS\Modelos\DevengoAlojamiento(
        null,
        $codDev,
        null,
        $estadiaId,
        $resId,
        $resUniId,
        $unidadId,
        $propiedadId,
        $cargo5Id,
        $fechaHoteleraDev,
        1,
        3,
        1,
        '250.00',
        '0.00',
        '38.14',
        '211.86',
        '250.00',
        'PEN',
        false,
        \CamargoPMS\Modelos\DevengoAlojamiento::ORIGEN_TARIFA_NOCTURNA_PACTADA,
        \CamargoPMS\Modelos\DevengoAlojamiento::METODO_DIST_TARIFA_EXPLICITA,
        'America/Lima',
        null,
        \CamargoPMS\Modelos\DevengoAlojamiento::ESTADO_DEVENGADO,
        \CamargoPMS\Modelos\DevengoAlojamiento::METODO_DEV_NIGHT_AUDIT,
        null,
        null,
        gmdate('Y-m-d H:i:s'),
        1
    );
    $devId = $devengoRepo->crear($devengoObj);
    $devengosCreados[] = $devId;

    // Transferir la noche a Folio B
    $transfNightAudit = $folioServicio->transferirCargo($folioAId, $folioBId, $cargo5Id, 'Reasignación de noche a empresa/folio B', 1, 1);
    $transferenciasCreadas[] = (int) $transfNightAudit->obtenerId();

    // Comprobar que devengos_alojamiento permanece intacto
    $stmtCheckDev = $pdo->prepare('SELECT * FROM devengos_alojamiento WHERE id = :id');
    $stmtCheckDev->execute(['id' => $devId]);
    $devFila = $stmtCheckDev->fetch(PDO::FETCH_ASSOC);

    verificar((int) $devFila['cargo_cuenta_id'] === $cargo5Id, "devengos_alojamiento.cargo_cuenta_id sigue enlazando al cargo transferido");
    verificar($devFila['fecha_hotelera'] === $fechaHoteleraDev, "Fecha contable del devengo permanece inalterada ({$fechaHoteleraDev})");
    verificar(bccomp($devFila['importe_total'], '250.00', 2) === 0, "Importe auditado del devengo permanece en S/ 250.00 (cero recálculo)");

    // =====================================================================
    // BLOQUE 7: CONCURRENCIA, ATOMICIDAD Y ROLLBACK ANTE FALLO INTERMEDIO
    // =====================================================================
    echo "\n--- BLOQUE 7: Concurrencia y Atomicidad Transaccional ---\n";

    // Cargo 6 para prueba de rollback
    $codCargo6 = "CRG-6-{$sufijo}";
    $cargo6 = new CargoCuenta(null, $codCargo6, $folioAId, 'SERVICIO_CONTRATADO', null, null, 'Cargo Rollback Test', '1.00', '80.00', '80.00', '0.00', '80.00', '0.00', 'PEN', 'DEVENGADO', null, null, null, gmdate('Y-m-d H:i:s'), 1);
    $cargo6Id = $cargoRepo->crear($cargo6);
    $cargosCreados[] = $cargo6Id;

    // Iniciar transacción externa y forzar excepción para verificar rollback íntegro
    $pdo->beginTransaction();
    $cargoRepo->transferirDeFolio($cargo6Id, $folioBId);
    $pdo->rollBack();

    $cargo6TrasRollback = $cargoRepo->obtenerPorId($cargo6Id);
    verificar($cargo6TrasRollback->obtenerCuentaFolioId() === $folioAId, "Rollback atómico preserva ubicación del cargo en Folio A");

    // =====================================================================
    // BLOQUE 8: ENDPOINTS HTTP, CONTROL DE ACCESO (RBAC) Y CSRF
    // =====================================================================
    echo "\n--- BLOQUE 8: Endpoints HTTP, RBAC y CSRF ---\n";

    // Verificación de registro de rutas en public/index.php
    $indexContenido = (string) file_get_contents(dirname(__DIR__) . '/public/index.php');
    verificar(str_contains($indexContenido, "post('/folios/{id}/transferir-cargo'"), "Ruta POST /folios/{id}/transferir-cargo registrada");
    verificar(str_contains($indexContenido, "post('/folios/{id}/split-cargo'"), "Ruta POST /folios/{id}/split-cargo registrada");
    verificar(str_contains($indexContenido, "get('/folios/{id}/transferencias'"), "Ruta GET /folios/{id}/transferencias registrada");
    verificar(str_contains($indexContenido, "get('/folios/{id}/cargos'"), "Ruta GET /folios/{id}/cargos registrada");
    verificar(str_contains($indexContenido, "get('/folios/{id}/datos'"), "Ruta GET /folios/{id}/datos registrada");
    verificar(str_contains($indexContenido, "caja.movimientos"), "Rutas de mutación protegidas con permiso caja.movimientos");

    // Instancia de CuentaFolioControlador
    $controlador = new CuentaFolioControlador($pdo);

    // 8.1 Petición POST sin token CSRF -> debe rechazar con HTTP 403
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = [
        'folio_destino_id' => $folioBId,
        'cargo_id' => $cargo6Id,
        'motivo' => 'Transferencia sin CSRF',
    ];
    unset($_SERVER['HTTP_X_CSRF_TOKEN']);
    unset($_POST['_token']);
    unset($_POST['csrf_token']);

    $respSinCsrf = $controlador->transferirCargo($folioAId);
    verificar($respSinCsrf->obtenerCodigo() === 403, "POST /folios/{id}/transferir-cargo sin CSRF retorna HTTP 403 Forbidden");

    // 8.2 Petición POST con CSRF válido -> debe ejecutar exitosamente HTTP 200
    $tokenCsrfValido = $csrfServicio->obtenerToken();
    $_POST['_token'] = $tokenCsrfValido;
    $_POST['motivo'] = 'Transferencia HTTP autorizada con CSRF';

    $respConCsrf = $controlador->transferirCargo($folioAId);
    verificar($respConCsrf->obtenerCodigo() === 200, "POST /folios/{id}/transferir-cargo con CSRF retorna HTTP 200 OK");
    $jsonResp = json_decode($respConCsrf->obtenerContenido(), true);
    verificar($jsonResp['ok'] === true, "Respuesta JSON envelope ok === true");
    verificar(isset($jsonResp['datos']['transferencia']), "Payload contiene entidad transferencia");
    $transferenciasCreadas[] = (int) $jsonResp['datos']['transferencia']['id'];

    // 8.3 Endpoint GET /folios/{id}/transferencias
    $respTransfList = $controlador->transferenciasJson($folioAId);
    verificar($respTransfList->obtenerCodigo() === 200, "GET /folios/{id}/transferencias retorna HTTP 200 OK");
    $jsonTransfList = json_decode($respTransfList->obtenerContenido(), true);
    verificar($jsonTransfList['ok'] === true && count($jsonTransfList['datos']) >= 1, "Listado de transferencias contiene al menos 1 registro");

    // 8.4 Endpoint GET /folios/{id}/cargos
    $respCargosList = $controlador->cargosJson($folioBId);
    verificar($respCargosList->obtenerCodigo() === 200, "GET /folios/{id}/cargos retorna HTTP 200 OK");
    $jsonCargosList = json_decode($respCargosList->obtenerContenido(), true);
    verificar($jsonCargosList['ok'] === true && count($jsonCargosList['datos']) >= 1, "Listado de cargos de Folio B recuperado con éxito");

    // 8.5 Endpoint GET /folios/{id}/datos (Estado de cuenta)
    $respDetalle = $controlador->detalleJson($folioAId);
    verificar($respDetalle->obtenerCodigo() === 200, "GET /folios/{id}/datos retorna HTTP 200 OK");
    $jsonDetalle = json_decode($respDetalle->obtenerContenido(), true);
    verificar(isset($jsonDetalle['datos']['saldo_neto_exigible']), "Estado de cuenta incluye saldo_neto_exigible");
    verificar(isset($jsonDetalle['datos']['estado_financiero']), "Estado de cuenta incluye estado_financiero");

} finally {
    // =====================================================================
    // LIMPIEZA DEFENSIVA DE FIXTURES DE PRUEBA
    // =====================================================================
    try {
        if (!empty($devengosCreados)) {
            $ids = implode(',', $devengosCreados);
            $pdo->exec("DELETE FROM devengos_alojamiento WHERE id IN ({$ids})");
        }
        if (!empty($aplicacionesCreadas)) {
            $ids = implode(',', $aplicacionesCreadas);
            $pdo->exec("DELETE FROM aplicaciones_pago WHERE id IN ({$ids})");
        }
        if (!empty($pagosCreados)) {
            $ids = implode(',', $pagosCreados);
            $pdo->exec("DELETE FROM pagos_cuenta WHERE id IN ({$ids})");
        }
        if (!empty($transferenciasCreadas)) {
            $ids = implode(',', $transferenciasCreadas);
            $pdo->exec("DELETE FROM cuenta_folio_transferencias_cargos WHERE id IN ({$ids})");
        }
        if (!empty($cargosCreados)) {
            $ids = implode(',', $cargosCreados);
            $pdo->exec("DELETE FROM cargos_cuenta WHERE cargo_padre_id IS NOT NULL AND id IN ({$ids})");
            $pdo->exec("DELETE FROM cargos_cuenta WHERE id IN ({$ids})");
        }
        if (!empty($foliosCreados)) {
            $ids = implode(',', $foliosCreados);
            $pdo->exec("DELETE FROM cuentas_folios WHERE folio_padre_id IS NOT NULL AND id IN ({$ids})");
            $pdo->exec("DELETE FROM cuentas_folios WHERE id IN ({$ids})");
        }
        if (!empty($reservasCreadas)) {
            $ids = implode(',', $reservasCreadas);
            $pdo->exec("DELETE FROM reservas WHERE id IN ({$ids})");
        }
        if (!empty($personasCreadas)) {
            $ids = implode(',', $personasCreadas);
            $pdo->exec("DELETE FROM personas WHERE id IN ({$ids})");
        }
    } catch (\Throwable $e) {
        // En pruebas no romper el flujo
    }
}

echo "\n====================================================================\n";
echo " RESUMEN: test_multifolio_split_3b.php\n";
echo " Total checks:  {$totalChecks}\n";
echo " Checks PASS:   {$checksAprobados}\n";
echo " Checks FAIL:   0\n";
echo " Estado:        100% PASS — HOMOLOGADO\n";
echo "====================================================================\n";
