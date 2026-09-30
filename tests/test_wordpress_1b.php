<?php

declare(strict_types=1);

/**
 * Suite de Pruebas Automatizadas: WORDPRESS-1B
 * Modelo Soberano de Tarifas de Alojamiento, Clientes API y Cotización.
 *
 * Cobertura de Principios y Gates Obligatorios:
 * 1. Gobierno del esquema: BD con exactamente 127 tablas, migración 036 registrada.
 * 2. Catálogo de Scopes normalizados y permisos RBAC del PMS.
 * 3. Modelo y Servicio de Tarifas (TarifaAlojamientoServicio):
 *    - Validación de ámbitos (UNIDAD, TIPO_UNIDAD, PROPIEDAD).
 *    - Anti-solapamiento temporal estricto con bloqueo pesimista.
 *    - Precedencia jerárquica de resolución: UNIDAD > TIPO_UNIDAD > PROPIEDAD.
 * 4. Autoridad Monetaria de Cotización (CotizacionServicio):
 *    - Resolución noche a noche con aritmética BCMath (D-069).
 *    - Cero bloqueos de inventario durante cotización.
 *    - Firma HMAC-SHA256, verificación y detección de manipulación.
 *    - Integración end-to-end con ReservaServicio::crearReservaDesdeCotizacion.
 * 5. Separación Técnica de Integración (ApiClientServicio):
 *    - ACTOR INTEGRACION -> API_CLIENT -> CREDENCIAL TÉCNICA -> SCOPES.
 *    - Token CSPRNG con hash SHA-256 en BD (cero secretos en claro).
 *    - Autenticación O(1), verificación de scopes, rotación y revocación.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CamargoPMS\Excepciones\ConflictoDisponibilidadExcepcion;
use CamargoPMS\Excepciones\ConflictoTarifaExcepcion;
use CamargoPMS\Excepciones\CotizacionInvalidaExcepcion;
use CamargoPMS\Excepciones\TarifaNoEncontradaExcepcion;
use CamargoPMS\Excepciones\ValidacionTarifaExcepcion;
use CamargoPMS\Modelos\AmbitoTarifaAlojamiento;
use CamargoPMS\Modelos\CotizacionResumen;
use CamargoPMS\Modelos\Reserva;
use CamargoPMS\Modelos\TarifaAlojamiento;
use CamargoPMS\Modelos\TipoActor;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Repositorios\ApiClientRepositorio;
use CamargoPMS\Repositorios\ApiCredencialRepositorio;
use CamargoPMS\Repositorios\ApiScopeRepositorio;
use CamargoPMS\Repositorios\PropiedadRepositorio;
use CamargoPMS\Repositorios\TarifaAlojamientoRepositorio;
use CamargoPMS\Repositorios\TipoUnidadRepositorio;
use CamargoPMS\Repositorios\UnidadRepositorio;
use CamargoPMS\Servicios\ApiClientServicio;
use CamargoPMS\Servicios\CotizacionServicio;
use CamargoPMS\Servicios\DisponibilidadServicio;
use CamargoPMS\Servicios\PersonaServicio;
use CamargoPMS\Servicios\ReservaServicio;
use CamargoPMS\Servicios\TarifaAlojamientoServicio;

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

echo "\n====================================================================\n";
echo " INICIANDO SUITE DE PRUEBAS: WORDPRESS-1B (TARIFAS + CLIENTES API + COTIZACIÓN)\n";
echo "====================================================================\n\n";

try {
    // =========================================================================
    // 1. GOBIERNO DEL ESQUEMA Y MIGRACIÓN 036
    // =========================================================================
    echo "--- 1. Gobierno del Esquema y Migración 036 ---\n";

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    assertCheck(count($tables) === 127, "Base de datos contiene exactamente 127 tablas relacionales (actual: " . count($tables) . ")");

    $tablasNuevas = [
        'tarifas_alojamiento',
        'api_clientes',
        'api_credenciales',
        'api_scopes',
        'api_credencial_scopes',
    ];
    foreach ($tablasNuevas as $tabla) {
        assertCheck(in_array($tabla, $tables, true), "Tabla '$tabla' existe en el esquema");
    }

    $scopeRepo = new ApiScopeRepositorio($pdo);
    $scopesActivos = $scopeRepo->listarActivos();
    assertCheck(count($scopesActivos) >= 6, "Existen al menos 6 scopes canónicos registrados");

    $codigosScopes = array_map(static fn($s) => $s->obtenerCodigo(), $scopesActivos);
    assertCheck(in_array('disponibilidad.leer', $codigosScopes, true), "Scope 'disponibilidad.leer' presente");
    assertCheck(in_array('cotizacion.crear', $codigosScopes, true), "Scope 'cotizacion.crear' presente");
    assertCheck(in_array('reservas.hold', $codigosScopes, true), "Scope 'reservas.hold' presente");
    assertCheck(in_array('reservas.confirmar', $codigosScopes, true), "Scope 'reservas.confirmar' presente");
    assertCheck(in_array('reservas.cancelar', $codigosScopes, true), "Scope 'reservas.cancelar' presente");
    assertCheck(in_array('reservas.leer', $codigosScopes, true), "Scope 'reservas.leer' presente");

    // Verificar permisos RBAC
    $stmtPerm = $pdo->prepare("SELECT COUNT(*) FROM permisos WHERE codigo IN ('tarifas.ver', 'tarifas.gestionar', 'api_clientes.ver', 'api_clientes.gestionar')");
    $stmtPerm->execute();
    assertCheck((int) $stmtPerm->fetchColumn() === 4, "Los 4 permisos RBAC de WORDPRESS-1B existen en 'permisos'");

    // =========================================================================
    // 2. MODELO Y SERVICIO DE TARIFAS (TarifaAlojamientoServicio)
    // =========================================================================
    echo "\n--- 2. Modelo Soberano de Tarifas y Precedencia Jerárquica ---\n";

    $propiedadRepo = new PropiedadRepositorio($pdo);
    $unidadRepo = new UnidadRepositorio($pdo);
    $tipoUnidadRepo = new TipoUnidadRepositorio($pdo);
    $tarifaServicio = new TarifaAlojamientoServicio($pdo);

    // Obtener propiedad activa de prueba
    $propiedades = $propiedadRepo->listar(null, 'ACTIVO', null, 1, 0);
    assertCheck(!empty($propiedades), "Existe al menos una propiedad activa para pruebas");
    $propiedadId = (int) $propiedades[0]->obtenerId();

    $unidades = $unidadRepo->listarPorPropiedad($propiedadId, 'ACTIVO');
    assertCheck(!empty($unidades), "Existen unidades activas en la propiedad");
    $unidadId = (int) $unidades[0]->obtenerId();
    $tipoUnidadId = (int) $unidades[0]->obtenerTipoUnidadId();

    // Obtener actor del sistema para autoría
    $actorSistemaId = (int) $pdo->query("SELECT id FROM actores WHERE tipo = 'SISTEMA' LIMIT 1")->fetchColumn();

    // Limpieza previa de datos de prueba para idempotencia de la suite
    $pdo->exec("DELETE FROM tarifas_alojamiento WHERE propiedad_id = {$propiedadId}");
    $pdo->exec("DELETE FROM api_clientes WHERE codigo LIKE 'WP_MOTOR_%'");

    // A. Tarifa a nivel PROPIEDAD (Base general)
    $tarifaPropiedad = $tarifaServicio->crearTarifa([
        'propiedad_id' => $propiedadId,
        'ambito_tipo' => AmbitoTarifaAlojamiento::PROPIEDAD,
        'nombre' => 'Tarifa Base Propiedad 2026',
        'precio_noche' => '100.00',
        'vigencia_desde' => '2026-10-01',
        'vigencia_hasta' => '2026-12-31',
        'moneda_codigo' => 'PEN',
    ], $actorSistemaId);

    assertCheck($tarifaPropiedad->obtenerId() > 0, "Tarifa de PROPIEDAD creada exitosamente (ID: {$tarifaPropiedad->obtenerId()})");
    assertCheck($tarifaPropiedad->obtenerPrecioNoche() === '100.0000', "Precio normalizado con 4 decimales: 100.0000");

    // B. Anti-solapamiento en el mismo ámbito: intento de crear tarifa que se solapa debe fallar
    $solapamientoDetectado = false;
    try {
        $tarifaServicio->crearTarifa([
            'propiedad_id' => $propiedadId,
            'ambito_tipo' => AmbitoTarifaAlojamiento::PROPIEDAD,
            'nombre' => 'Tarifa Solapada Intrusiva',
            'precio_noche' => '120.00',
            'vigencia_desde' => '2026-11-01',
            'vigencia_hasta' => '2026-11-15',
            'moneda_codigo' => 'PEN',
        ], $actorSistemaId);
    } catch (ConflictoTarifaExcepcion $e) {
        $solapamientoDetectado = true;
    }
    assertCheck($solapamientoDetectado, "Anti-solapamiento rechaza tarifa con colisión temporal en mismo ámbito (ConflictoTarifaExcepcion)");

    // C. Tarifa no solapada (período adyacente o futuro) sí se permite
    $tarifaFutura = $tarifaServicio->crearTarifa([
        'propiedad_id' => $propiedadId,
        'ambito_tipo' => AmbitoTarifaAlojamiento::PROPIEDAD,
        'nombre' => 'Tarifa Verano 2027',
        'precio_noche' => '110.00',
        'vigencia_desde' => '2027-01-01',
        'vigencia_hasta' => '2027-03-31',
        'moneda_codigo' => 'PEN',
    ], $actorSistemaId);
    assertCheck($tarifaFutura->obtenerId() > 0, "Tarifa con rango temporal adyacente creada sin conflicto");

    // D. Validaciones de dominio
    $fechaInvalidaRechazada = false;
    try {
        $tarifaServicio->crearTarifa([
            'propiedad_id' => $propiedadId,
            'ambito_tipo' => AmbitoTarifaAlojamiento::PROPIEDAD,
            'nombre' => 'Tarifa Fechas Invertidas',
            'precio_noche' => '100.00',
            'vigencia_desde' => '2026-10-15',
            'vigencia_hasta' => '2026-10-10', // Menor que inicio
            'moneda_codigo' => 'PEN',
        ], $actorSistemaId);
    } catch (ValidacionTarifaExcepcion $e) {
        $fechaInvalidaRechazada = true;
    }
    assertCheck($fechaInvalidaRechazada, "Validación rechaza fecha fin anterior a fecha inicio");

    // E. Tarifa a nivel TIPO_UNIDAD (Prevalece sobre PROPIEDAD)
    $tarifaTipo = $tarifaServicio->crearTarifa([
        'propiedad_id' => $propiedadId,
        'ambito_tipo' => AmbitoTarifaAlojamiento::TIPO_UNIDAD,
        'tipo_unidad_id' => $tipoUnidadId,
        'nombre' => 'Tarifa Categoría Estándar',
        'precio_noche' => '150.00',
        'vigencia_desde' => '2026-10-01',
        'vigencia_hasta' => '2026-12-31',
        'moneda_codigo' => 'PEN',
    ], $actorSistemaId);
    assertCheck($tarifaTipo->obtenerId() > 0, "Tarifa de TIPO_UNIDAD creada (S/ 150.00)");

    // F. Tarifa a nivel UNIDAD (Override físico prevalece sobre TIPO_UNIDAD y PROPIEDAD)
    $tarifaUnidad = $tarifaServicio->crearTarifa([
        'propiedad_id' => $propiedadId,
        'ambito_tipo' => AmbitoTarifaAlojamiento::UNIDAD,
        'unidad_id' => $unidadId,
        'nombre' => 'Tarifa Override Habitación VIP',
        'precio_noche' => '220.00',
        'vigencia_desde' => '2026-12-01',
        'vigencia_hasta' => '2026-12-07', // Solo los primeros 7 días de diciembre
        'moneda_codigo' => 'PEN',
    ], $actorSistemaId);
    assertCheck($tarifaUnidad->obtenerId() > 0, "Tarifa override de UNIDAD creada (S/ 220.00 para 2026-12-01 a 2026-12-07)");

    // G. Comprobación de la Jerarquía de Resolución
    // Fecha 2026-12-05: la unidad tiene override -> Debe dar 220.00 (UNIDAD)
    $resuelta1 = $tarifaServicio->resolverTarifaParaFecha($propiedadId, $tipoUnidadId, $unidadId, '2026-12-05');
    assertCheck(
        $resuelta1->obtenerAmbitoTipo() === AmbitoTarifaAlojamiento::UNIDAD && bccomp($resuelta1->obtenerPrecioNoche(), '220.0000', 4) === 0,
        "Jerarquía 1: UNIDAD prevalece en fecha con override (Esperado 220.0000, obtenido {$resuelta1->obtenerPrecioNoche()})"
    );

    // Fecha 2026-12-15: la unidad NO tiene override en esa fecha, pero sí hay tarifa de tipo -> Debe dar 150.00 (TIPO_UNIDAD)
    $resuelta2 = $tarifaServicio->resolverTarifaParaFecha($propiedadId, $tipoUnidadId, $unidadId, '2026-12-15');
    assertCheck(
        $resuelta2->obtenerAmbitoTipo() === AmbitoTarifaAlojamiento::TIPO_UNIDAD && bccomp($resuelta2->obtenerPrecioNoche(), '150.0000', 4) === 0,
        "Jerarquía 2: TIPO_UNIDAD prevalece cuando no hay override de unidad (Esperado 150.0000, obtenido {$resuelta2->obtenerPrecioNoche()})"
    );

    // Consulta para otra unidad del hotel que NO tiene tarifa de tipo -> Debe caer a PROPIEDAD (100.00)
    $resuelta3 = $tarifaServicio->resolverTarifaParaFecha($propiedadId, 999999, null, '2026-12-15');
    assertCheck(
        $resuelta3->obtenerAmbitoTipo() === AmbitoTarifaAlojamiento::PROPIEDAD && bccomp($resuelta3->obtenerPrecioNoche(), '100.0000', 4) === 0,
        "Jerarquía 3: PROPIEDAD actúa como fallback cuando no hay tipo ni unidad (Esperado 100.0000, obtenido {$resuelta3->obtenerPrecioNoche()})"
    );

    // Consulta fuera de vigencia -> Lanza TarifaNoEncontradaExcepcion
    $tarifaNoEncontrada = false;
    try {
        $tarifaServicio->resolverTarifaParaFecha($propiedadId, $tipoUnidadId, $unidadId, '2025-01-01');
    } catch (TarifaNoEncontradaExcepcion $e) {
        $tarifaNoEncontrada = true;
    }
    assertCheck($tarifaNoEncontrada, "Fecha sin tarifa activa lanza TarifaNoEncontradaExcepcion");

    // =========================================================================
    // 3. AUTORIDAD MONETARIA DE COTIZACIÓN (CotizacionServicio)
    // =========================================================================
    echo "\n--- 3. Autoridad Monetaria de Cotización (CotizacionServicio) ---\n";

    $dispServicio = new DisponibilidadServicio($pdo);
    $cotizacionServicio = new CotizacionServicio(
        pdo: $pdo,
        disponibilidadServicio: $dispServicio,
        tarifaServicio: $tarifaServicio,
        propiedadRepo: $propiedadRepo,
        unidadRepo: $unidadRepo,
        tipoUnidadRepo: $tipoUnidadRepo
    );

    // Limpiar cualquier residuo de reservas de pruebas en diciembre para la unidad
    $pdo->exec("DELETE FROM inventario_diario_unidades WHERE unidad_id = {$unidadId} AND fecha >= '2026-12-01' AND fecha <= '2026-12-31'");

    // Contar bloqueos antes de cotizar
    $stmtCountBloqueos = $pdo->prepare('SELECT COUNT(*) FROM inventario_diario_unidades WHERE unidad_id = :u');
    $stmtCountBloqueos->execute(['u' => $unidadId]);
    $bloqueosAntes = (int) $stmtCountBloqueos->fetchColumn();

    // Cotizar estancia de 4 noches: del 2026-12-05 al 2026-12-09
    // Noches:
    // 2026-12-05: Override Unidad = 220.00
    // 2026-12-06: Override Unidad = 220.00
    // 2026-12-07: Override Unidad = 220.00
    // 2026-12-08: Tipo Unidad = 150.00
    // Total esperado: 220 + 220 + 220 + 150 = 810.00 PEN
    $cotizacion = $cotizacionServicio->cotizarEstancia([
        'propiedad_id' => $propiedadId,
        'unidad_id' => $unidadId,
        'fecha_entrada' => '2026-12-05',
        'fecha_salida' => '2026-12-09',
        'huespedes' => 2,
    ]);

    assertCheck($cotizacion instanceof CotizacionResumen, "Cotización retornó instancia de CotizacionResumen");
    assertCheck($cotizacion->obtenerNoches() === 4, "Cotización calculó exactamente 4 noches");
    assertCheck(count($cotizacion->obtenerDesgloseNoches()) === 4, "Desglose contiene exactamente 4 noches");

    $desglose = $cotizacion->obtenerDesgloseNoches();
    assertCheck(bccomp($desglose[0]->obtenerPrecioNoche(), '220.0000', 4) === 0, "Noche 1 (05/12): S/ 220.0000 (Override Unidad)");
    assertCheck(bccomp($desglose[1]->obtenerPrecioNoche(), '220.0000', 4) === 0, "Noche 2 (06/12): S/ 220.0000 (Override Unidad)");
    assertCheck(bccomp($desglose[2]->obtenerPrecioNoche(), '220.0000', 4) === 0, "Noche 3 (07/12): S/ 220.0000 (Override Unidad)");
    assertCheck(bccomp($desglose[3]->obtenerPrecioNoche(), '150.0000', 4) === 0, "Noche 4 (08/12): S/ 150.0000 (Tipo Unidad)");

    assertCheck($cotizacion->obtenerSubtotal() === '810.00', "Subtotal acumulado D-069 es exactamente S/ 810.00 (obtenido: {$cotizacion->obtenerSubtotal()})");
    assertCheck($cotizacion->obtenerImpuesto() === '0.00', "Impuesto D-069 regla provisional es S/ 0.00");
    assertCheck($cotizacion->obtenerTotal() === '810.00', "Total formal es S/ 810.00");
    assertCheck($cotizacion->obtenerTarifaPromedioNoche() === '202.50', "Tarifa promedio por noche es S/ 202.50 (810.00 / 4)");

    // Verificación de NO bloqueo de inventario
    $stmtCountBloqueos->execute(['u' => $unidadId]);
    $bloqueosDespues = (int) $stmtCountBloqueos->fetchColumn();
    assertCheck($bloqueosAntes === $bloqueosDespues, "INVENTARIO SOBERANO: La cotización NO insertó filas en inventario_diario_unidades");

    // Token firmado
    $tokenCotizacion = $cotizacion->obtenerTokenCotizacion();
    assertCheck(!empty($tokenCotizacion), "Cotización contiene token criptográfico firmado");

    // Validación de token intacto
    $payloadValidado = $cotizacionServicio->validarTokenCotizacion($tokenCotizacion);
    assertCheck($payloadValidado['total'] === '810.00', "Token verificado con éxito, total coincide con S/ 810.00");
    assertCheck($payloadValidado['checkin'] === '2026-12-05', "Token conserva fecha de entrada");

    // Detección de manipulación de firma
    $tokenManipulado = $tokenCotizacion . 'tampered';
    $tamperDetectado = false;
    try {
        $cotizacionServicio->validarTokenCotizacion($tokenManipulado);
    } catch (CotizacionInvalidaExcepcion $e) {
        $tamperDetectado = true;
    }
    assertCheck($tamperDetectado, "Firma HMAC detecta y rechaza token manipulado (CotizacionInvalidaExcepcion)");

    // =========================================================================
    // 4. INTEGRACIÓN END-TO-END CON RESERVAS (ReservaServicio)
    // =========================================================================
    echo "\n--- 4. Creación de Reserva desde Cotización Soberana ---\n";

    $personaServicio = new PersonaServicio($pdo);
    $reservaServicio = new ReservaServicio($pdo);

    // Crear persona titular de prueba
    $dniAleatorio = (string) rand(10000000, 99999999);
    $titular = $personaServicio->crearPersona(
        [
            'nombres' => 'Huésped WordPress',
            'apellido_paterno' => 'Directo',
            'apellido_materno' => 'Prueba',
        ],
        [
            'tipo_documento_id' => 1, // DNI
            'numero_documento' => $dniAleatorio,
            'pais_emisor_id' => 1,
        ],
        [
            [
                'tipo_contacto' => 'EMAIL',
                'valor' => "huesped_{$dniAleatorio}@test.com",
                'es_principal' => true,
            ],
        ]
    );
    $titularId = (int) $titular->obtenerId();
    assertCheck($titularId > 0, "Persona titular creada para reserva online (DNI: {$dniAleatorio})");

    // Crear reserva directa usando la cotización verificada
    $reserva = $reservaServicio->crearReservaDesdeCotizacion(
        cotizacion: $cotizacion,
        personaTitularId: $titularId,
        datosAdicionales: [
            'canal' => Reserva::CANAL_WEB,
            'origen' => 'WEB_DIRECTA',
            'duracion_hold_minutos' => 15,
        ]
    );

    assertCheck($reserva->obtenerId() > 0, "Reserva creada exitosamente desde cotización soberana (Código: {$reserva->obtenerCodigo()})");
    assertCheck($reserva->obtenerEstado() === Reserva::ESTADO_PENDIENTE, "Reserva queda en estado PENDIENTE (hold)");
    assertCheck($reserva->obtenerTotal() === '810.00', "Total de la reserva es exactamente S/ 810.00 procedente de la cotización");
    assertCheck($reserva->obtenerCanal() === Reserva::CANAL_WEB, "Canal de la reserva es 'WEB'");
    assertCheck($reserva->obtenerOrigen() === 'WEB_DIRECTA', "Origen de la reserva es 'WEB_DIRECTA'");
    assertCheck($reserva->obtenerExpiraEn() !== null, "Reserva cuenta con tiempo de expiración (expira_en)");

    // Comprobar que AHORA SÍ se bloquearon las 4 noches en inventario_diario_unidades
    $stmtVerifBloqueo = $pdo->prepare('SELECT COUNT(*) FROM inventario_diario_unidades WHERE origen_tipo = "RESERVA" AND origen_id = :r');
    $stmtVerifBloqueo->execute(['r' => $reserva->obtenerId()]);
    assertCheck((int) $stmtVerifBloqueo->fetchColumn() === 4, "INVENTARIO FÍSICO: Las 4 noches quedaron bloqueadas tras el hold");

    // =========================================================================
    // 5. SEPARACIÓN TÉCNICA DE INTEGRACIÓN (ApiClientServicio)
    // =========================================================================
    echo "\n--- 5. Clientes API, Credenciales CSPRNG y Scopes ---\n";

    $apiClientServicio = new ApiClientServicio($pdo);

    // A. Crear Cliente API
    $codigoCliente = 'WP_MOTOR_' . rand(1000, 9999);
    $cliente = $apiClientServicio->crearCliente([
        'codigo' => $codigoCliente,
        'nombre' => 'WordPress Web Oficial',
        'descripcion' => 'Motor de reservas directas en portal web',
        'contacto_email' => 'webmaster@hotelcamargo.com',
        'limite_peticiones_minuto' => 120,
    ], $actorSistemaId);

    assertCheck($cliente->obtenerId() > 0, "Cliente API creado exitosamente (ID: {$cliente->obtenerId()}, Código: {$cliente->obtenerCodigo()})");
    assertCheck($cliente->obtenerActorId() > 0, "Cliente API vinculado a Actor ID {$cliente->obtenerActorId()}");

    // Verificar que el actor vinculado es tipo INTEGRACION y NO usuario humano
    $stmtActor = $pdo->prepare('SELECT tipo, usuario_id FROM actores WHERE id = :id');
    $stmtActor->execute(['id' => $cliente->obtenerActorId()]);
    $actorDatos = $stmtActor->fetch(PDO::FETCH_ASSOC);
    assertCheck($actorDatos['tipo'] === TipoActor::INTEGRACION, "Actor vinculado tiene tipo 'INTEGRACION'");
    assertCheck($actorDatos['usuario_id'] === null, "Actor NO tiene cuenta de usuario humano ficticia (usuario_id NULL)");

    // B. Crear Credencial Técnica con Scopes
    $credencialData = $apiClientServicio->crearCredencial(
        clienteId: (int) $cliente->obtenerId(),
        nombreCredencial: 'Producción Web 2026',
        codigosScopes: ['disponibilidad.leer', 'cotizacion.crear', 'reservas.hold', 'reservas.confirmar'],
        expiraEn: null,
        actorCreadorId: $actorSistemaId
    );

    $tokenSecreto = $credencialData['token_secreto'];
    $credencial = $credencialData['credencial'];

    assertCheck(str_starts_with($tokenSecreto, 'cpms_live_'), "Token emitido inicia con prefijo 'cpms_live_'");
    assertCheck(strlen($tokenSecreto) === 58, "Token secreto tiene longitud y entropía adecuada (58 caracteres CSPRNG)");
    assertCheck($credencial->obtenerIdentificadorPublico() !== '', "Credencial posee identificador público (key_...)");
    assertCheck(count($credencialData['scopes']) === 4, "Credencial asignó exactamente 4 scopes");

    // Verificar que en base de datos NO se guardó el token en claro
    $stmtCheckDb = $pdo->prepare('SELECT token_hash, token_prefijo FROM api_credenciales WHERE id = :id');
    $stmtCheckDb->execute(['id' => $credencial->obtenerId()]);
    $dbCred = $stmtCheckDb->fetch(PDO::FETCH_ASSOC);
    assertCheck($dbCred['token_hash'] === hash('sha256', $tokenSecreto), "En base de datos se almacena el hash SHA-256");
    assertCheck(strlen($dbCred['token_hash']) === 64, "Hash SHA-256 es de 64 caracteres");
    assertCheck($dbCred['token_prefijo'] === substr($tokenSecreto, 0, 16), "Prefijo seguro almacenado para identificación");

    // C. Autenticación con Token Bearer O(1)
    $contexto = $apiClientServicio->autenticarToken($tokenSecreto);
    assertCheck($contexto !== null, "Autenticación exitosa con token Bearer en claro");
    assertCheck($contexto->obtenerCliente()->obtenerCodigo() === $codigoCliente, "Contexto resuelve cliente API correcto");
    assertCheck($contexto->obtenerActor()->obtenerTipo() === TipoActor::INTEGRACION, "Contexto resuelve actor de integración");
    assertCheck($contexto->tieneScope('disponibilidad.leer'), "Credencial posee scope 'disponibilidad.leer'");
    assertCheck($contexto->tieneScope('reservas.hold'), "Credencial posee scope 'reservas.hold'");
    assertCheck(!$contexto->tieneScope('reservas.cancelar'), "Credencial NO posee scope no asignado 'reservas.cancelar'");

    // Token inválido no autentica
    $contextoInvalido = $apiClientServicio->autenticarToken('cpms_live_invalido_12345678901234567890');
    assertCheck($contextoInvalido === null, "Token espurio es rechazado con null");

    // D. Rotación de Credencial
    $rotacion = $apiClientServicio->rotarCredencial((int) $credencial->obtenerId(), null, $actorSistemaId);
    $nuevoToken = $rotacion['token_secreto'];
    assertCheck($nuevoToken !== $tokenSecreto, "Rotación genera nuevo secreto CSPRNG");

    // El token antiguo fue revocado y ya no debe autenticar
    $authViejo = $apiClientServicio->autenticarToken($tokenSecreto);
    assertCheck($authViejo === null, "Token antiguo revocado durante rotación ya no autentica");

    // El nuevo token autentica inmediatamente con los mismos scopes
    $authNuevo = $apiClientServicio->autenticarToken($nuevoToken);
    assertCheck($authNuevo !== null, "Nuevo token rotado autentica exitosamente");
    assertCheck($authNuevo->tieneScope('cotizacion.crear'), "Nuevo token conserva scopes de la credencial rotada");

    // E. Revocación Manual
    $okRevocar = $apiClientServicio->revocarCredencial((int) $rotacion['credencial']->obtenerId(), 'Revocación por término de prueba', $actorSistemaId);
    assertCheck($okRevocar, "Revocación manual ejecutada con éxito");

    $authRevocado = $apiClientServicio->autenticarToken($nuevoToken);
    assertCheck($authRevocado === null, "Credencial revocada manualmente rechaza autenticación");

    // =========================================================================
    // RESUMEN FINAL
    // =========================================================================
    echo "\n====================================================================\n";
    echo " RESUMEN WORDPRESS-1B: $passedAssertions / $totalAssertions pruebas superadas\n";
    echo "====================================================================\n\n";

    if ($passedAssertions === $totalAssertions) {
        echo ">>> WORDPRESS-1B: MODELO SOBERANO VALIDADO AL 100% (TODO PASS) <<<\n\n";
    }

} catch (Throwable $e) {
    echo "\n[ERROR FATAL EN SUITE WORDPRESS-1B]: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
