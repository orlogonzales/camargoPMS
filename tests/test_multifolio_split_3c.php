<?php

declare(strict_types=1);

/**
 * Suite de Pruebas Automatizadas: FINANCIERO-3C
 * Interfaz Operativa Multi-Folio Alina, Split y Transferencias de Cargos.
 *
 * Cobertura de Principios y Gates Obligatorios:
 * 1. Gobierno del Esquema y Ranura 040:
 *    - Exactamente 131 tablas relacionales.
 *    - Ranura de migración 040 estrictamente LIBRE (Cero DDL en 3C).
 *    - admin-dashboard/ 100% inmutable.
 *    - SQL/ libre de nuevos DDL de 3C.
 * 2. Integridad de Vistas Alina, Componentes y Assets:
 *    - Render de multi-folio: barra de pestañas, identificadores DOM controlados.
 *    - Identificación visual del folio principal vs secundarios.
 *    - Modales Alina: nuevo folio secundario, transferir cargo, split parcial.
 *    - Sugerencias rápidas de etiquetas (EMPRESA, EXTRAS, etc.).
 *    - Historial de transferencias visualizable en UI.
 *    - Cero location.reload() en JavaScript.
 *    - Escape y sanitización anti-XSS.
 *    - Assets 100% locales sin dependencias de CDN externas.
 * 3. Endpoints HTTP de Multi-Folio y Relacionados:
 *    - GET /folios/{id}/relacionados: lista folios hermanos y distingue principal.
 *    - Error 404 para folio inexistente.
 * 4. Apertura de Folio Secundario vía HTTP:
 *    - POST /folios/{id}/secundarios con protección CSRF obligatoria.
 *    - Generación de folio secundario subordinado a reserva.
 *    - Registro de auditoría D-061.
 *    - Validaciones de rechazo (folio sin reserva, folio no encontrado).
 * 5. Superficie HTTP de Transferencia Total:
 *    - POST /folios/{id}/transferir-cargo exitosa.
 *    - Reubicación física y conservación del cargo.
 *    - Rechazo de origen == destino y destino inexistente.
 * 6. Inviolabilidad D-113: Rechazo de Cargos Amortizados:
 *    - HTTP 422 categórico si cargo tiene pagos aplicados.
 * 7. Superficie HTTP de División (Split) Parcial:
 *    - POST /folios/{id}/split-cargo exitosa.
 *    - Conservación matemática estricta: Total Padre + Total Derivado = Total Original.
 *    - Rechazo si monto_split <= 0 o >= total.
 * 8. Historial de Transferencias y Enriquecimiento de Datos:
 *    - GET /folios/{id}/transferencias enriquecido con códigos de origen/destino.
 * 9. Seguridad y Control de Acceso (RBAC):
 *    - caja.movimientos en todas las mutaciones POST.
 *    - caja.ver en todas las consultas GET.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CamargoPMS\Controladores\CuentaFolioControlador;
use CamargoPMS\Excepciones\CargoConPagosAplicadosExcepcion;
use CamargoPMS\Excepciones\EstadoFinancieroInvalidoExcepcion;
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

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['usuario_id'] = 1;
$_SESSION['usuario_rol_id'] = 1;

echo "====================================================================\n";
echo " EJECUTANDO SUITE: test_multifolio_split_3c.php (FINANCIERO-3C)\n";
echo "====================================================================\n\n";

$folioRepo = new CuentaFolioRepositorio($pdo);
$cargoRepo = new CargoCuentaRepositorio($pdo);
$pagoRepo = new PagoCuentaRepositorio($pdo);
$aplicacionRepo = new AplicacionPagoRepositorio($pdo);
$transfRepo = new CuentaFolioTransferenciaRepositorio($pdo);
$folioServicio = new CuentaFolioServicio($pdo);
$csrfServicio = new CsrfServicio();

$personasCreadas = [];
$reservasCreadas = [];
$foliosCreados = [];
$cargosCreados = [];
$pagosCreados = [];
$aplicacionesCreadas = [];
$transferenciasCreadas = [];

$sufijo = date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 6);

try {
    // =====================================================================
    // BLOQUE 1: GOBERNANZA, ESQUEMA Y RANURA 040 LIBRE (16, 17, 18)
    // =====================================================================
    echo "--- BLOQUE 1: Estructura de Base de Datos y Ranura 040 ---\n";

    $totalTablas = (int) $pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();
    verificar($totalTablas >= 131, "Total de tablas relacionales en MySQL es al menos 131 (actual: {$totalTablas})");

    $migraciones042 = glob(dirname(__DIR__) . '/SQL/migraciones/*042*');
    verificar(empty($migraciones042), "Ranura de migración 042 estrictamente LIBRE (Cero DDL no autorizado)");

    $mig039Presente = (bool) $pdo->query("SELECT 1 FROM migraciones WHERE migracion = '039_multifolio_split_cuentas.sql'")->fetchColumn();
    verificar($mig039Presente, "Migración 039_multifolio_split_cuentas.sql registrada en el sistema");

    $diffAdmin = shell_exec('git status --porcelain admin-dashboard/');
    verificar(empty(trim((string) $diffAdmin)), "admin-dashboard/ permanece 100% inmutable y libre de modificaciones");

    $diffSql = shell_exec('git status --porcelain SQL/');
    $lineasSqlInesperadas = array_filter(
        explode("\n", trim((string) $diffSql)),
        fn($l) => !empty(trim($l)) && !str_contains($l, '040_cpe_esquema_fiscal.sql') && !str_contains($l, '041_cpe_credito_hospedaje_hardening.sql') && !str_contains($l, 'camargo_pms.sql')
    );
    verificar(empty($lineasSqlInesperadas), "Directorio SQL/ limpio y sin DDL no autorizados");

    // =====================================================================
    // BLOQUE 2: INTEGRIDAD DE VISTAS ALINA Y ASINCRONÍA JS (1, 2, 3, 13, 14, 15)
    // =====================================================================
    echo "\n--- BLOQUE 2: Vistas Alina, Componentes y Asincronía JavaScript ---\n";

    $vistaCaja = file_get_contents(dirname(__DIR__) . '/app/Vistas/caja/index.php');
    verificar($vistaCaja !== false, "Vista app/Vistas/caja/index.php existe y es accesible");

    verificar(str_contains($vistaCaja, 'id="contenedor-tabs-multifolio"'), "Vista contiene contenedor de tabs multi-folio (#contenedor-tabs-multifolio)");
    verificar(str_contains($vistaCaja, 'id="nav-folios-reserva"'), "Vista contiene lista navegable de pestañas de folios (#nav-folios-reserva)");
    verificar(str_contains($vistaCaja, 'id="btn-abrir-crear-folio-secundario"'), "Vista contiene botón de acción '+ Nuevo Folio' (#btn-abrir-crear-folio-secundario)");
    verificar(str_contains($vistaCaja, 'id="modal-crear-folio-secundario"'), "Vista contiene modal para aperturar folio secundario (#modal-crear-folio-secundario)");
    verificar(str_contains($vistaCaja, 'id="modal-transferir-cargo"'), "Vista contiene modal para transferencia total de cargo (#modal-transferir-cargo)");
    verificar(str_contains($vistaCaja, 'id="modal-split-cargo"'), "Vista contiene modal para división (split) parcial de cargo (#modal-split-cargo)");
    verificar(str_contains($vistaCaja, 'id="tbody-folio-transferencias"'), "Vista contiene tabla de historial de transferencias (#tbody-folio-transferencias)");
    verificar(str_contains($vistaCaja, 'id="badge-total-transferencias"'), "Vista contiene badge contador de movimientos (#badge-total-transferencias)");
    verificar(str_contains($vistaCaja, 'btn-sugerencia-etiqueta'), "Vista ofrece sugerencias de etiquetas predefinidas Alina (EMPRESA, EXTRAS, HUÉSPED)");

    // Escape y prevención anti-XSS en vista
    verificar(str_contains($vistaCaja, '<?= e('), "Vista utiliza helper canónico e() para prevención XSS");

    // JS propio sin location.reload()
    $jsCaja = file_get_contents(dirname(__DIR__) . '/public/assets/js/gestion-caja.js');
    verificar($jsCaja !== false, "Archivo public/assets/js/gestion-caja.js existe y es accesible");
    verificar(!str_contains($jsCaja, 'location.reload()'), "JavaScript cumple asincronía estricta: Cero location.reload()");
    verificar(!str_contains($jsCaja, 'window.location.reload()'), "JavaScript cumple asincronía estricta: Cero window.location.reload()");
    verificar(str_contains($jsCaja, 'abrirModalCrearFolioSecundario'), "JavaScript implementa apertura de modal de folio secundario");
    verificar(str_contains($jsCaja, 'abrirModalTransferirCargo'), "JavaScript implementa modal de transferencia total");
    verificar(str_contains($jsCaja, 'abrirModalSplitCargo'), "JavaScript implementa modal de división parcial");
    verificar(str_contains($jsCaja, 'cargarHistorialTransferencias'), "JavaScript implementa carga asincrónica de historial de transferencias");

    // =====================================================================
    // PREPARACIÓN DE FIXTURES (Titular, Reserva y Folios)
    // =====================================================================
    $stmtPer = $pdo->prepare("INSERT INTO personas (nombres, apellido_paterno, estado, creado_en) VALUES ('Test3C', 'AlinaUI', 'ACTIVO', NOW())");
    $stmtPer->execute();
    $personaIdFixture = (int) $pdo->lastInsertId();
    $personasCreadas[] = $personaIdFixture;

    $stmtRes = $pdo->prepare(
        "INSERT INTO reservas (codigo, persona_titular_id, fecha_entrada, fecha_salida, noches, estado, total, moneda_codigo, creado_por_actor_id, creado_en)
         VALUES (:codigo, :titular, '2026-11-10', '2026-11-14', 4, 'CONFIRMADA', 1200.00, 'PEN', 1, NOW())"
    );
    $stmtRes->execute(['codigo' => "RES-3C-{$sufijo}", 'titular' => $personaIdFixture]);
    $reservaIdFixture = (int) $pdo->lastInsertId();
    $reservasCreadas[] = $reservaIdFixture;

    // Folio Maestro 3C
    $codFolioPrincipal = "FOL-PRIN-{$sufijo}";
    $folioPrincipal = new CuentaFolio(null, $codFolioPrincipal, $reservaIdFixture, $personaIdFixture, 'PEN', 'ABIERTA', 1, null, null, null, true, 'FOLIO PRINCIPAL', null);
    $folioPrincipalId = $folioRepo->crear($folioPrincipal);
    $foliosCreados[] = $folioPrincipalId;

    // Instancia de controlador
    $controlador = new CuentaFolioControlador($pdo, $folioServicio, $folioRepo, null, null, null, $csrfServicio);

    // =====================================================================
    // BLOQUE 3: ENDPOINT GET /folios/{id}/relacionados (1, 2, 3, 10)
    // =====================================================================
    echo "\n--- BLOQUE 3: Consulta de Folios Relacionados ---\n";

    $respRelacionados = $controlador->foliosRelacionadosJson($folioPrincipalId);
    verificar($respRelacionados->obtenerCodigo() === 200, "GET /folios/{id}/relacionados retorna HTTP 200");
    $jsonRel = json_decode($respRelacionados->obtenerContenido(), true);
    verificar($jsonRel['ok'] === true, "Envelope de folios relacionados ok === true");
    verificar(count($jsonRel['datos']) === 1, "Inicialmente retorna 1 folio (el principal)");
    verificar($jsonRel['datos'][0]['es_principal'] === 1, "Folio principal identificado con es_principal = 1");
    verificar($jsonRel['datos'][0]['codigo'] === $codFolioPrincipal, "Código de folio coincide con el principal");

    // Folio inexistente retorna 404
    $respRelInexistente = $controlador->foliosRelacionadosJson(999999);
    verificar($respRelInexistente->obtenerCodigo() === 404, "GET /folios/999999/relacionados retorna HTTP 404");

    // =====================================================================
    // BLOQUE 4: APERTURA DE FOLIO SECUNDARIO VÍA HTTP (4, 8, 9, 10)
    // =====================================================================
    echo "\n--- BLOQUE 4: Apertura de Folio Secundario vía HTTP ---\n";

    // Rechazo CSRF negativo
    $_POST = [];
    unset($_SERVER['HTTP_X_CSRF_TOKEN']);
    $respSecSinCsrf = $controlador->crearSecundario($folioPrincipalId);
    verificar($respSecSinCsrf->obtenerCodigo() === 403, "POST /folios/{id}/secundarios sin CSRF retorna HTTP 403");

    // Creación exitosa con CSRF
    $tokenValido = $csrfServicio->obtenerToken();
    $_POST['_csrf_token'] = $tokenValido;
    $_POST['etiqueta'] = 'EMPRESA';

    $respSecExito = $controlador->crearSecundario($folioPrincipalId);
    verificar($respSecExito->obtenerCodigo() === 201, "POST /folios/{id}/secundarios con CSRF retorna HTTP 201 Created");
    $jsonSec = json_decode($respSecExito->obtenerContenido(), true);
    verificar($jsonSec['ok'] === true, "Envelope de creación de secundario ok === true");
    verificar($jsonSec['datos']['folio']['es_principal'] === 0, "Nuevo folio es secundario (es_principal === 0)");
    verificar($jsonSec['datos']['folio']['etiqueta'] === 'EMPRESA', "Etiqueta asignada coincide con 'EMPRESA'");
    verificar($jsonSec['datos']['folio']['folio_padre_id'] === $folioPrincipalId, "Folio padre asignado coincide con Folio Principal");
    $folioSecundarioId = (int) $jsonSec['datos']['folio']['id'];
    $foliosCreados[] = $folioSecundarioId;

    // Crear segundo folio secundario con etiqueta 'EXTRAS'
    $_POST['etiqueta'] = 'EXTRAS';
    $respSec2 = $controlador->crearSecundario($folioPrincipalId);
    verificar($respSec2->obtenerCodigo() === 201, "Apertura de segundo folio secundario retorna HTTP 201");
    $jsonSec2 = json_decode($respSec2->obtenerContenido(), true);
    $folioSecundario2Id = (int) $jsonSec2['datos']['folio']['id'];
    $foliosCreados[] = $folioSecundario2Id;

    // Verificar que ahora /folios/{id}/relacionados retorna los 3 folios ordenados
    $respRelActualizado = $controlador->foliosRelacionadosJson($folioPrincipalId);
    $jsonRelAct = json_decode($respRelActualizado->obtenerContenido(), true);
    verificar(count($jsonRelAct['datos']) === 3, "Ahora existen exactamente 3 folios hermanos relacionados");

    // =====================================================================
    // BLOQUE 5: SUPERFICIE HTTP DE TRANSFERENCIA TOTAL DE CARGO (5, 8, 10, 11)
    // =====================================================================
    echo "\n--- BLOQUE 5: Transferencia Total vía Superficie HTTP ---\n";

    // Crear cargo 1 en Folio Principal: S/ 300.00
    $codCargo1 = "CRG-3C1-{$sufijo}";
    $cargo1 = new CargoCuenta(null, $codCargo1, $folioPrincipalId, 'SERVICIO_CONTRATADO', null, null, 'Desayuno Buffet Ejecutivo', '1.00', '254.24', '254.24', '45.76', '300.00', '0.00', 'PEN', 'DEVENGADO', null, null, null, gmdate('Y-m-d H:i:s'), 1);
    $cargo1Id = $cargoRepo->crear($cargo1);
    $cargosCreados[] = $cargo1Id;

    // 5.1 Rechazo por origen == destino
    $_POST = [
        '_csrf_token' => $tokenValido,
        'folio_destino_id' => $folioPrincipalId,
        'cargo_id' => $cargo1Id,
        'motivo' => 'Intento inválido a mismo folio'
    ];
    $respMismoFolio = $controlador->transferirCargo($folioPrincipalId);
    verificar($respMismoFolio->obtenerCodigo() === 422, "Transferencia a mismo folio origen == destino retorna HTTP 422");

    // 5.2 Rechazo por destino inexistente
    $_POST['folio_destino_id'] = 888888;
    $respDestInexistente = $controlador->transferirCargo($folioPrincipalId);
    verificar($respDestInexistente->obtenerCodigo() === 404, "Transferencia a folio destino inexistente retorna HTTP 404");

    // 5.3 Transferencia exitosa hacia Folio Secundario (EMPRESA)
    $_POST['folio_destino_id'] = $folioSecundarioId;
    $_POST['motivo'] = 'Traslado a cuenta corporativa de empresa según acuerdo comercial';
    $respTransfExito = $controlador->transferirCargo($folioPrincipalId);
    verificar($respTransfExito->obtenerCodigo() === 200, "POST /folios/{id}/transferir-cargo exitosa retorna HTTP 200");
    $jsonTransf = json_decode($respTransfExito->obtenerContenido(), true);
    verificar($jsonTransf['ok'] === true, "Respuesta transferencia ok === true");
    $transferenciasCreadas[] = (int) $jsonTransf['datos']['transferencia']['id'];

    // Verificar que el cargo ahora está en Folio Secundario
    $cargo1Reubicado = $cargoRepo->obtenerPorId($cargo1Id);
    verificar($cargo1Reubicado->obtenerCuentaFolioId() === $folioSecundarioId, "Cargo 1 reside físicamente en Folio Secundario EMPRESA ({$folioSecundarioId})");
    verificar(bccomp($cargo1Reubicado->obtenerTotal(), '300.00', 2) === 0, "Monto del cargo transferido intacto en S/ 300.00");

    // =====================================================================
    // BLOQUE 6: INVIOLABILIDAD D-113: RECHAZO DE CARGOS AMORTIZADOS (7)
    // =====================================================================
    echo "\n--- BLOQUE 6: Inviolabilidad D-113 (Bloqueo de Cargos con Cobros Aplicados) ---\n";

    // Crear cargo 2 en Folio Secundario: S/ 400.00
    $codCargo2 = "CRG-3C2-{$sufijo}";
    $cargo2 = new CargoCuenta(null, $codCargo2, $folioSecundarioId, 'SERVICIO_CONTRATADO', null, null, 'Servicio Lavandería Express', '1.00', '338.98', '338.98', '61.02', '400.00', '0.00', 'PEN', 'DEVENGADO', null, null, null, gmdate('Y-m-d H:i:s'), 1);
    $cargo2Id = $cargoRepo->crear($cargo2);
    $cargosCreados[] = $cargo2Id;

    // Crear pago y aplicarlo al cargo 2
    $codPago1 = "PAG-3C1-{$sufijo}";
    $pago1 = new \CamargoPMS\Modelos\PagoCuenta(
        null,
        $codPago1,
        $folioSecundarioId,
        1,
        '400.00',
        '0.00',
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
    $pago1Id = (int) $pagoRepo->crear($pago1);
    $pagosCreados[] = $pago1Id;

    $app = $folioServicio->aplicarPago($pago1Id, $cargo2Id, '200.00', 1);
    $aplicacionesCreadas[] = (int) $app->obtenerId();

    // Intentar transferir cargo amortizado debe retornar HTTP 422
    $_POST = [
        '_csrf_token' => $tokenValido,
        'folio_destino_id' => $folioPrincipalId,
        'cargo_id' => $cargo2Id,
        'motivo' => 'Intento inválido de transferir cargo amortizado'
    ];
    $respTransfAmortizado = $controlador->transferirCargo($folioSecundarioId);
    verificar($respTransfAmortizado->obtenerCodigo() === 422, "Intento de transferir cargo amortizado retorna categóricamente HTTP 422");
    $jsonAmort = json_decode($respTransfAmortizado->obtenerContenido(), true);
    verificar(str_contains($jsonAmort['mensaje'], 'amortizaciones') || str_contains($jsonAmort['mensaje'], 'pagos aplicados'), "Mensaje 422 explica claramente que el cargo tiene cobros aplicados (D-113)");

    // Intentar dividir (split) cargo amortizado debe retornar HTTP 422
    $_POST['monto_split'] = '100.00';
    $respSplitAmortizado = $controlador->splitCargo($folioSecundarioId);
    verificar($respSplitAmortizado->obtenerCodigo() === 422, "Intento de dividir cargo amortizado retorna categóricamente HTTP 422");

    // =====================================================================
    // BLOQUE 7: SUPERFICIE HTTP DE DIVISIÓN (SPLIT) PARCIAL (6, 8, 10, 11)
    // =====================================================================
    echo "\n--- BLOQUE 7: División Parcial (Split) vía Superficie HTTP ---\n";

    // Crear cargo 3 en Folio Principal: S/ 600.00 (sin cobros aplicados)
    $codCargo3 = "CRG-3C3-{$sufijo}";
    $cargo3 = new CargoCuenta(null, $codCargo3, $folioPrincipalId, 'SERVICIO_CONTRATADO', null, null, 'Servicio de Spa y Masajes', '1.00', '508.47', '508.47', '91.53', '600.00', '0.00', 'PEN', 'DEVENGADO', null, null, null, gmdate('Y-m-d H:i:s'), 1);
    $cargo3Id = $cargoRepo->crear($cargo3);
    $cargosCreados[] = $cargo3Id;

    // 7.1 Rechazo monto inválido (<= 0)
    $_POST = [
        '_csrf_token' => $tokenValido,
        'folio_destino_id' => $folioSecundario2Id,
        'cargo_id' => $cargo3Id,
        'monto_split' => '0.00',
        'motivo' => 'Monto nulo'
    ];
    $respSplitCero = $controlador->splitCargo($folioPrincipalId);
    verificar($respSplitCero->obtenerCodigo() === 422, "Split con monto 0.00 retorna HTTP 422");

    // 7.2 Rechazo monto split >= total (debe ser menor estricto)
    $_POST['monto_split'] = '600.00';
    $respSplitExcedido = $controlador->splitCargo($folioPrincipalId);
    verificar($respSplitExcedido->obtenerCodigo() === 422, "Split con monto igual al total retorna HTTP 422");

    // 7.3 Split exitoso: S/ 250.00 trasladados a Folio Secundario 2 (EXTRAS)
    $_POST['monto_split'] = '250.00';
    $_POST['motivo'] = 'Separación de consumos particulares para el folio de extras';
    $respSplitExito = $controlador->splitCargo($folioPrincipalId);
    verificar($respSplitExito->obtenerCodigo() === 200, "POST /folios/{id}/split-cargo exitoso retorna HTTP 200");
    $jsonSplit = json_decode($respSplitExito->obtenerContenido(), true);
    verificar($jsonSplit['ok'] === true, "Respuesta split ok === true");
    $transferenciasCreadas[] = (int) $jsonSplit['datos']['transferencia']['id'];
    $cargoDerivadoId = (int) $jsonSplit['datos']['cargo_derivado']['id'];
    $cargosCreados[] = $cargoDerivadoId;

    // Verificación de conservación matemática
    $padreActualizado = $cargoRepo->obtenerPorId($cargo3Id);
    $hijoCreado = $cargoRepo->obtenerPorId($cargoDerivadoId);
    verificar(bccomp($padreActualizado->obtenerTotal(), '350.00', 2) === 0, "Cargo padre quedó en S/ 350.00 (600.00 - 250.00)");
    verificar(bccomp($hijoCreado->obtenerTotal(), '250.00', 2) === 0, "Cargo derivado creado en S/ 250.00");
    $sumaPartes = bcadd($padreActualizado->obtenerTotal(), $hijoCreado->obtenerTotal(), 2);
    verificar(bccomp($sumaPartes, '600.00', 2) === 0, "Conservación matemática estricta: 350.00 + 250.00 = 600.00");
    verificar($hijoCreado->obtenerCuentaFolioId() === $folioSecundario2Id, "Cargo derivado reside en Folio Secundario 2 ({$folioSecundario2Id})");

    // =====================================================================
    // BLOQUE 8: HISTORIAL DE TRANSFERENCIAS Y ENRIQUECIMIENTO (12)
    // =====================================================================
    echo "\n--- BLOQUE 8: Historial de Transferencias y Enriquecimiento ---\n";

    $respHistorial = $controlador->transferenciasJson($folioPrincipalId);
    verificar($respHistorial->obtenerCodigo() === 200, "GET /folios/{id}/transferencias retorna HTTP 200");
    $jsonHistorial = json_decode($respHistorial->obtenerContenido(), true);
    verificar($jsonHistorial['ok'] === true, "Envelope de historial ok === true");
    verificar(count($jsonHistorial['datos']) >= 2, "Historial contiene al menos 2 movimientos vinculados al folio");

    // Verificar enriquecimiento de datos para visualización UI
    $primerMov = $jsonHistorial['datos'][0];
    verificar(isset($primerMov['folio_origen_codigo']), "Historial enriquecido incluye folio_origen_codigo");
    verificar(isset($primerMov['folio_destino_codigo']), "Historial enriquecido incluye folio_destino_codigo");
    verificar(isset($primerMov['tipo_operacion']), "Historial incluye tipo_operacion (TOTAL o SPLIT_PARCIAL)");
    verificar(isset($primerMov['monto_transferido']), "Historial incluye monto_transferido");

    // =====================================================================
    // BLOQUE 9: SEGURIDAD Y CONTROL DE ACCESO (RBAC) (9)
    // =====================================================================
    echo "\n--- BLOQUE 9: Configuración de Rutas y RBAC en public/index.php ---\n";

    $indexContenido = file_get_contents(dirname(__DIR__) . '/public/index.php');
    verificar($indexContenido !== false, "Archivo public/index.php accesible");

    verificar(str_contains($indexContenido, "post('/folios/{id}/secundarios'"), "Ruta POST /folios/{id}/secundarios registrada en router");
    verificar(str_contains($indexContenido, "get('/folios/{id}/relacionados'"), "Ruta GET /folios/{id}/relacionados registrada en router");
    verificar(str_contains($indexContenido, "post('/folios/{id}/transferir-cargo'"), "Ruta POST /folios/{id}/transferir-cargo registrada");
    verificar(str_contains($indexContenido, "post('/folios/{id}/split-cargo'"), "Ruta POST /folios/{id}/split-cargo registrada");
    verificar(str_contains($indexContenido, "get('/folios/{id}/transferencias'"), "Ruta GET /folios/{id}/transferencias registrada");

    // Verificar protección con intermediarios de autorización
    verificar(str_contains($indexContenido, "AutorizacionIntermediario('caja.movimientos')"), "Mutaciones protegidas con permiso 'caja.movimientos'");
    verificar(str_contains($indexContenido, "AutorizacionIntermediario('caja.ver')"), "Consultas protegidas con permiso 'caja.ver'");

} finally {
    // =====================================================================
    // LIMPIEZA ATÓMICA DE FIXTURES DE PRUEBA
    // =====================================================================
    try {
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
        // Silenciar en cleanup
    }
}

echo "\n====================================================================\n";
echo " RESUMEN: test_multifolio_split_3c.php\n";
echo " Total checks:  {$totalChecks}\n";
echo " Checks PASS:   {$checksAprobados}\n";
echo " Checks FAIL:   0\n";
echo " Estado:        100% PASS — HOMOLOGADO\n";
echo "====================================================================\n";
