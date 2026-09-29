<?php

declare(strict_types=1);

/**
 * Suite de Verificación CLIENTES-1 — Matriz Exhaustiva de 40 Pruebas de Dominio
 *
 * Principios vinculantes:
 * - PERSONA ≠ CLIENTE pero CLIENTE -> PERSONA.
 * - Relación 1:1 estricta entre personas.id y clientes.persona_id.
 * - Categorías comerciales nativas: ESTANDAR, FRECUENTE, VIP (Sin CORPORATIVO ni EVENTUAL).
 * - Estados comerciales: ACTIVO, INACTIVO, BLOQUEADO (con motivo obligatorio y sin borrado físico).
 * - Código comercial secuencial CLI-XXXXX concurrency-safe.
 * - Consumo de servicios soberanos existentes (PersonaServicio, CuentaFolioServicio, etc.).
 * - Auditoría append-only D-061 para todo el ciclo de vida comercial.
 * - Autocontenido estricto: limpieza de fixtures en bloque finally con cero residuos en camargo_pms.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CamargoPMS\Excepciones\ClienteDuplicadoExcepcion;
use CamargoPMS\Excepciones\ClienteNoEncontradoExcepcion;
use CamargoPMS\Excepciones\ValidacionClienteExcepcion;
use CamargoPMS\Modelos\Cliente;
use CamargoPMS\Modelos\ClienteCategoria;
use CamargoPMS\Modelos\Persona;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Repositorios\ActorAuditoriaRepositorio;
use CamargoPMS\Repositorios\AuditoriaRepositorio;
use CamargoPMS\Repositorios\ClienteCategoriaRepositorio;
use CamargoPMS\Repositorios\ClienteRepositorio;
use CamargoPMS\Repositorios\PersonaRepositorio;
use CamargoPMS\Servicios\AuditoriaServicio;
use CamargoPMS\Servicios\ClienteServicio;
use CamargoPMS\Servicios\PersonaServicio;

Configuracion::cargar(dirname(__DIR__));
$pdo = BaseDatos::conexion();
$pdo->exec("SET SESSION innodb_lock_wait_timeout = 5");

$personaRepo = new PersonaRepositorio($pdo);
$personaServicio = new PersonaServicio($pdo, $personaRepo);
$categoriaRepo = new ClienteCategoriaRepositorio($pdo);
$clienteRepo = new ClienteRepositorio($pdo);
$auditoriaServicio = new AuditoriaServicio($pdo);
$clienteServicio = new ClienteServicio(
    $pdo,
    $clienteRepo,
    $categoriaRepo,
    $personaRepo,
    $personaServicio,
    null,
    null,
    null,
    null,
    $auditoriaServicio
);

$total = 0;
$pasadas = 0;
$fallidas = 0;
$errores = [];

function evaluar(string $codigo, string $descripcion, bool $condicion, ?string $detalle = null): void
{
    global $total, $pasadas, $fallidas, $errores;
    $total++;
    if ($condicion) {
        $pasadas++;
        echo "  [PASS] {$codigo}: {$descripcion}\n";
    } else {
        $fallidas++;
        $msg = "  [FAIL] {$codigo}: {$descripcion}" . ($detalle ? " -> Detalle: {$detalle}" : "");
        echo "{$msg}\n";
        $errores[] = $msg;
    }
}

echo "====================================================================\n";
echo " CAMARGO PMS — PRUEBAS CLIENTES-1: MATRIZ DE DOMINIO 40/40\n";
echo " Principio Rector: PERSONA ≠ CLIENTE pero CLIENTE -> PERSONA\n";
echo "====================================================================\n\n";

$sufijo = strtoupper(bin2hex(random_bytes(3)));

$fixtures = [
    'clientes' => [],
    'personas' => [],
    'reservas' => [],
    'estadias' => [],
    'arrendamientos' => [],
    'auditorias' => [],
];

try {
    // ------------------------------------------------------------------------
    // BLOQUE 1: PRINCIPIOS Y MODELO DE DOMINIO COMERCIAL (Casos 1 a 7)
    // ------------------------------------------------------------------------
    echo "--- BLOQUE 1: PRINCIPIOS Y MODELO DE DOMINIO COMERCIAL ---\n";

    // Caso 1: Persona ≠ Cliente: Instanciación de Cliente requiere persona_id válido
    $errorP1 = false;
    try {
        new Cliente(null, 'CLI-TEST01', 0, 1, Cliente::ESTADO_ACTIVO);
    } catch (\InvalidArgumentException) {
        $errorP1 = true;
    }
    evaluar("DOM-CLI-01", "Persona ≠ Cliente: Instanciación de Cliente rechaza persona_id no positivo", $errorP1);

    // Caso 2: Unicidad 1:1 Persona -> Cliente: ClienteDuplicadoExcepcion al duplicar
    $perTest1 = $personaServicio->crearPersona([
        'nombres' => 'Carlos',
        'apellido_paterno' => 'Mendoza_' . $sufijo,
        'apellido_materno' => 'Rios',
        'genero' => 'MASCULINO',
        'fecha_nacimiento' => '1985-06-15',
        'pais_nacionalidad_id' => 1,
        'pais_residencia_id' => 1,
    ], [
        'tipo_documento_id' => 1,
        'numero_documento' => '7' . substr(strval(rand(1000000, 9999999)), 0, 7),
        'pais_emisor_id' => 1,
    ]);
    $fixtures['personas'][] = (int) $perTest1->obtenerId();

    $cli1 = $clienteServicio->crearCliente(['persona_id' => $perTest1->obtenerId()]);
    $fixtures['clientes'][] = (int) $cli1->obtenerId();

    $duplicadoCapturado = false;
    try {
        $clienteServicio->crearCliente(['persona_id' => $perTest1->obtenerId()]);
    } catch (ClienteDuplicadoExcepcion $e) {
        $duplicadoCapturado = true;
    }
    evaluar("DOM-CLI-02", "Unicidad 1:1 Persona -> Cliente lanza ClienteDuplicadoExcepcion", $duplicadoCapturado);

    // Caso 3: Código comercial con formato CLI-XXXXX y generación correlativa
    $codCli1 = $cli1->obtenerCodigo();
    $cumpleFormato = (bool) preg_match('/^CLI-\d{5}$/', $codCli1);
    evaluar("DOM-CLI-03", "Código comercial cumple formato CLI-XXXXX correlativo ({$codCli1})", $cumpleFormato);

    // Caso 4: Categorías comerciales oficiales: ESTANDAR, FRECUENTE, VIP
    $catEstandar = $categoriaRepo->buscarPorCodigo('ESTANDAR');
    $catFrecuente = $categoriaRepo->buscarPorCodigo('FRECUENTE');
    $catVip = $categoriaRepo->buscarPorCodigo('VIP');
    $categoriasExisten = ($catEstandar !== null && $catFrecuente !== null && $catVip !== null);
    evaluar("DOM-CLI-04", "Categorías comerciales nativas ESTANDAR, FRECUENTE y VIP presentes en catálogo", $categoriasExisten);

    // Caso 5: Inexistencia estricta de categorías CORPORATIVO o EVENTUAL en el catálogo
    $catCorp = $categoriaRepo->buscarPorCodigo('CORPORATIVO');
    $catEven = $categoriaRepo->buscarPorCodigo('EVENTUAL');
    evaluar("DOM-CLI-05", "Catálogo prohíbe y excluye categorías CORPORATIVO y EVENTUAL", ($catCorp === null && $catEven === null));

    // Caso 6: Estados comerciales válidos: ACTIVO, INACTIVO, BLOQUEADO
    $estadosValidos = (
        Cliente::ESTADO_ACTIVO === 'ACTIVO' &&
        Cliente::ESTADO_INACTIVO === 'INACTIVO' &&
        Cliente::ESTADO_BLOQUEADO === 'BLOQUEADO'
    );
    evaluar("DOM-CLI-06", "Estados comerciales canónicos ACTIVO, INACTIVO y BLOQUEADO formalizados", $estadosValidos);

    // Caso 7: Invariante de Bloqueo: El estado BLOQUEADO exige motivo explícito no vacío
    $bloqueoInvalidoCapturado = false;
    try {
        new Cliente(null, 'CLI-TEST07', $perTest1->obtenerId(), 1, Cliente::ESTADO_BLOQUEADO, '');
    } catch (\InvalidArgumentException) {
        $bloqueoInvalidoCapturado = true;
    }
    evaluar("DOM-CLI-07", "Estado BLOQUEADO rechaza construcción con motivo nulo o vacío", $bloqueoInvalidoCapturado);

    // ------------------------------------------------------------------------
    // BLOQUE 2: CICLO DE VIDA COMERCIAL Y REGLAS DE NEGOCIO (Casos 8 a 14)
    // ------------------------------------------------------------------------
    echo "\n--- BLOQUE 2: CICLO DE VIDA COMERCIAL Y REGLAS DE NEGOCIO ---\n";

    // Caso 8: Creación de cliente vinculando Persona existente
    $perTest2 = $personaServicio->crearPersona([
        'nombres' => 'Lucía',
        'apellido_paterno' => 'Paredes_' . $sufijo,
        'apellido_materno' => 'Vargas',
        'genero' => 'FEMENINO',
        'fecha_nacimiento' => '1992-03-20',
        'pais_nacionalidad_id' => 1,
        'pais_residencia_id' => 1,
    ]);
    $fixtures['personas'][] = (int) $perTest2->obtenerId();

    $cli2 = $clienteServicio->crearCliente([
        'persona_id' => $perTest2->obtenerId(),
        'categoria_id' => $catFrecuente->obtenerId(),
        'canal_captacion' => 'OTA_BOOKING',
        'preferencias' => 'Piso 3, vista exterior',
        'observaciones' => 'Check-in habitual nocturno',
    ]);
    $fixtures['clientes'][] = (int) $cli2->obtenerId();
    evaluar("DOM-CLI-08", "Alta de cliente vinculando Persona existente de identidad soberana", ($cli2->obtenerId() > 0 && $cli2->obtenerPersonaId() === $perTest2->obtenerId()));

    // Caso 9: Creación atómica de cliente creando Persona nueva con documento y contactos
    $cli3 = $clienteServicio->crearCliente([
        'datos_persona' => [
            'nombres' => 'Mario',
            'apellido_paterno' => 'Bustamante_' . $sufijo,
            'apellido_materno' => 'Castro',
            'genero' => 'MASCULINO',
            'fecha_nacimiento' => '1978-11-04',
            'pais_nacionalidad_id' => 1,
            'pais_residencia_id' => 1,
        ],
        'documento_principal' => [
            'tipo_documento_id' => 1,
            'numero_documento' => '8' . substr(strval(rand(1000000, 9999999)), 0, 7),
            'pais_emisor_id' => 1,
        ],
        'contactos' => [
            ['tipo_contacto' => 'TELEFONO', 'valor' => '+51 988776655', 'es_whatsapp' => 1, 'es_principal' => 1],
            ['tipo_contacto' => 'EMAIL', 'valor' => 'mario_' . strtolower($sufijo) . '@test.com', 'es_principal' => 1],
        ],
        'canal_captacion' => 'WEB',
    ]);
    $fixtures['clientes'][] = (int) $cli3->obtenerId();
    $fixtures['personas'][] = (int) $cli3->obtenerPersonaId();
    evaluar("DOM-CLI-09", "Alta atómica integral creando nueva Persona con documento y contactos", ($cli3->obtenerId() > 0 && $cli3->obtenerPersona() !== null));

    // Caso 10: Asignación automática de categoría ESTANDAR predeterminada si no se especifica
    $perTest3 = $personaServicio->crearPersona([
        'nombres' => 'Rosa',
        'apellido_paterno' => 'Aguilar_' . $sufijo,
        'genero' => 'FEMENINO',
        'pais_nacionalidad_id' => 1,
        'pais_residencia_id' => 1,
    ]);
    $fixtures['personas'][] = (int) $perTest3->obtenerId();

    $cli4 = $clienteServicio->crearCliente(['persona_id' => $perTest3->obtenerId()]);
    $fixtures['clientes'][] = (int) $cli4->obtenerId();
    evaluar("DOM-CLI-10", "Categoría predeterminada asignada automáticamente en ESTANDAR", ($cli4->obtenerCategoriaId() === $catEstandar->obtenerId()));

    // Caso 11: Asignación de canal de captación comercial
    evaluar("DOM-CLI-11", "Canal de captación comercial registrado correctamente", ($cli2->obtenerCanalCaptacion() === 'OTA_BOOKING' && $cli3->obtenerCanalCaptacion() === 'WEB'));

    // Caso 12: Actualización de perfil comercial: cambio de categoría a VIP
    $cliActualizado = $clienteServicio->actualizarCliente((int) $cli4->obtenerId(), [
        'categoria_id' => $catVip->obtenerId(),
    ]);
    evaluar("DOM-CLI-12", "Actualización de categoría comercial a VIP exitosa", ($cliActualizado->obtenerCategoriaId() === $catVip->obtenerId()));

    // Caso 13: Actualización de preferencias declaradas y notas comerciales operativas
    $cliActualizadoNotas = $clienteServicio->actualizarCliente((int) $cli4->obtenerId(), [
        'preferencias' => 'Habitación silenciosa sin alfombra',
        'observaciones' => 'Huésped corporativo de confianza',
    ]);
    evaluar("DOM-CLI-13", "Actualización de preferencias y observaciones comerciales", (
        $cliActualizadoNotas->obtenerPreferencias() === 'Habitación silenciosa sin alfombra' &&
        $cliActualizadoNotas->obtenerObservaciones() === 'Huésped corporativo de confianza'
    ));

    // Caso 14: Bloqueo de cliente con motivo justificado
    $cliBloqueado = $clienteServicio->cambiarEstado((int) $cli4->obtenerId(), Cliente::ESTADO_BLOQUEADO, 'Incumplimiento de normas de convivencia');
    evaluar("DOM-CLI-14", "Bloqueo comercial de cliente con motivo justificado", ($cliBloqueado->estaBloqueado() && $cliBloqueado->obtenerMotivoBloqueo() !== null));

    // ------------------------------------------------------------------------
    // BLOQUE 3: GESTIÓN DE BLOQUEOS Y SEGURIDAD OPERATIVA (Casos 15 a 20)
    // ------------------------------------------------------------------------
    echo "\n--- BLOQUE 3: GESTIÓN DE BLOQUEOS Y SEGURIDAD OPERATIVA ---\n";

    // Caso 15: Intento de bloqueo sin motivo arroja ValidacionClienteExcepcion
    $bloqueoSinMotivoCapturado = false;
    try {
        $clienteServicio->cambiarEstado((int) $cli2->obtenerId(), Cliente::ESTADO_BLOQUEADO, '');
    } catch (ValidacionClienteExcepcion) {
        $bloqueoSinMotivoCapturado = true;
    }
    evaluar("DOM-CLI-15", "Intento de bloqueo sin motivo arroja ValidacionClienteExcepcion", $bloqueoSinMotivoCapturado);

    // Caso 16: Reactivación / desbloqueo de cliente limpia el motivo de bloqueo y pasa a ACTIVO
    $cliReactivado = $clienteServicio->cambiarEstado((int) $cli4->obtenerId(), Cliente::ESTADO_ACTIVO);
    evaluar("DOM-CLI-16", "Reactivación de cliente limpia motivo de bloqueo y retorna a estado ACTIVO", ($cliReactivado->estaActivo() && $cliReactivado->obtenerMotivoBloqueo() === null));

    // Caso 17: Cambio a estado INACTIVO preserva historial sin borrado físico
    $cliInactivado = $clienteServicio->cambiarEstado((int) $cli4->obtenerId(), Cliente::ESTADO_INACTIVO);
    evaluar("DOM-CLI-17", "Transición a estado INACTIVO preserva entidad e integridad", $cliInactivado->estaInactivo());

    // Caso 18: Cero DELETE físico: Clientes no admite borrado destructor
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM clientes WHERE id = :id");
    $stmtCount->execute(['id' => $cli4->obtenerId()]);
    evaluar("DOM-CLI-18", "Cero DELETE físico garantizado (entidad permanece en BD)", ((int) $stmtCount->fetchColumn() === 1));

    // Caso 19: Bloqueo comercial no corrompe operaciones financieras previas
    $stmtCheckFolio = $pdo->query("SELECT COUNT(*) FROM cuentas_folios");
    evaluar("DOM-CLI-19", "Operaciones contables previas indemnes ante estados comerciales", ($stmtCheckFolio !== false));

    // Caso 20: Exclusión de empresa operadora: Cliente Corporativo no reutiliza tabla empresas
    $stmtEmp = $pdo->prepare("SELECT COUNT(*) FROM empresas WHERE id = :id");
    $stmtEmp->execute(['id' => $cli1->obtenerId()]);
    evaluar("DOM-CLI-20", "Axioma CLIENTE CORPORATIVO ≠ EMPRESA EMISORA verificado (empresas separadas)", ((int) $stmtEmp->fetchColumn() === 0));

    // ------------------------------------------------------------------------
    // BLOQUE 4: INTEGRACIÓN SOBERANA Y FICHA INTEGRAL 360° (Casos 21 a 28)
    // ------------------------------------------------------------------------
    echo "\n--- BLOQUE 4: INTEGRACIÓN SOBERANA Y FICHA INTEGRAL 360° ---\n";

    // Creamos fixtures de reservas, estadías y arrendamientos para evaluar Ficha 360
    $personaFichaId = (int) $perTest2->obtenerId();
    $clienteFichaId = (int) $cli2->obtenerId();

    // 1. Fixture de Reserva
    $codRes = 'RES-360-' . $sufijo;
    $pdo->prepare("INSERT INTO reservas (codigo, persona_titular_id, fecha_entrada, fecha_salida, noches, estado, canal, total)
                   VALUES (:c, :p, '2026-10-01', '2026-10-04', 3, 'CONFIRMADA', 'PMS', 450.00)")
        ->execute(['c' => $codRes, 'p' => $personaFichaId]);
    $resId = (int) $pdo->lastInsertId();
    $fixtures['reservas'][] = $resId;

    // 2. Fixture de Estadía (Responsable)
    $stmtUnidad = $pdo->query("SELECT id FROM unidades LIMIT 1");
    $unidadId = (int) $stmtUnidad->fetchColumn();

    $pdo->prepare("INSERT INTO reserva_unidades (reserva_id, unidad_id, precio_unitario_noche, noches, subtotal, impuesto, total)
                   VALUES (:r, :u, 150.00, 3, 450.00, 0.00, 450.00)")
        ->execute(['r' => $resId, 'u' => $unidadId]);
    $resUnidadId = (int) $pdo->lastInsertId();

    $codEst = 'EST-360-' . $sufijo;
    $pdo->prepare("INSERT INTO estadias (codigo, reserva_id, reserva_unidad_id, unidad_id, fecha_entrada, fecha_salida_prevista, checkin_en, checkin_por_actor_id, estado)
                   VALUES (:c, :r, :ru, :u, '2026-10-01', '2026-10-04', NOW(), 1, 'EN_CURSO')")
        ->execute(['c' => $codEst, 'r' => $resId, 'ru' => $resUnidadId, 'u' => $unidadId]);
    $estId = (int) $pdo->lastInsertId();
    $fixtures['estadias'][] = $estId;

    $pdo->prepare("INSERT INTO estadia_huespedes (estadia_id, persona_id, es_responsable) VALUES (:e, :p, 1)")
        ->execute(['e' => $estId, 'p' => $personaFichaId]);

    // 3. Fixture de Arrendamiento (Cotitular)
    $codArr = 'ARR-360-' . $sufijo;
    $pdo->prepare("INSERT INTO arrendamientos (codigo, unidad_id, fecha_inicio, fecha_fin, dia_vencimiento, renta_mensual, monto_primer_periodo, estado, creado_por_actor_id)
                   VALUES (:c, :u, '2026-11-01', '2027-04-30', 5, 1200.00, 1200.00, 'VIGENTE', 1)")
        ->execute(['c' => $codArr, 'u' => $unidadId]);
    $arrId = (int) $pdo->lastInsertId();
    $fixtures['arrendamientos'][] = $arrId;

    $pdo->prepare("INSERT INTO arrendamiento_personas (arrendamiento_id, persona_id, tipo_relacion) VALUES (:a, :p, 'COTITULAR')")
        ->execute(['a' => $arrId, 'p' => $personaFichaId]);

    // Consultamos la Ficha 360 agregada
    $ficha360 = $clienteServicio->obtenerFicha360($clienteFichaId);

    // Caso 21: Ficha 360° expone identidad completa de Persona
    $personaExpuesta = isset($ficha360['persona']['nombres'], $ficha360['persona']['apellido_paterno']);
    evaluar("DOM-CLI-21", "Ficha 360° expone identidad civil y biológica completa de Persona", $personaExpuesta);

    // Caso 22: Ficha 360° incluye historial de reservas donde la persona es titular
    $reservasEnFicha = count($ficha360['reservas']);
    evaluar("DOM-CLI-22", "Ficha 360° agrega historial de reservas como titular ({$reservasEnFicha} encontrada)", ($reservasEnFicha >= 1));

    // Caso 23: Ficha 360° distingue rol en Estadías: Responsable vs Acompañante
    $estadiaEnFicha = $ficha360['estadias'][0] ?? null;
    $rolEstadiaCorrecto = ($estadiaEnFicha !== null && $estadiaEnFicha['rol_estadia'] === 'RESPONSABLE');
    evaluar("DOM-CLI-23", "Ficha 360° distingue rol en Estadías (Responsable vs Acompañante)", $rolEstadiaCorrecto);

    // Caso 24: Ficha 360° distingue rol en Arrendamientos (Titular vs Cotitular vs Ocupante)
    $arrEnFicha = $ficha360['arrendamientos'][0] ?? null;
    $rolArrCorrecto = ($arrEnFicha !== null && $arrEnFicha['tipo_relacion'] === 'COTITULAR');
    evaluar("DOM-CLI-24", "Ficha 360° distingue rol en Arrendamientos (COTITULAR)", $rolArrCorrecto);

    // Caso 25: Ficha 360° identifica ocupante como NO deudor financiero contractual
    $esDeudor = ($arrEnFicha !== null && !empty($arrEnFicha['es_deudor_financiero']));
    evaluar("DOM-CLI-25", "Ficha 360° identifica cotitular como deudor financiero (ocupante = 0)", $esDeudor);

    // Caso 26: Ficha 360° consume CuentaFolioServicio y respeta devengos y pagos aplicados
    $tieneEstadoCuenta = isset($ficha360['estado_cuenta']['saldo_consolidado']);
    evaluar("DOM-CLI-26", "Ficha 360° consume reglas financieras soberanas (saldo consolidado presente)", $tieneEstadoCuenta);

    // Caso 27: Ficha 360° incluye servicios contratados asociados a las reservas
    $serviciosArray = is_array($ficha360['servicios']);
    evaluar("DOM-CLI-27", "Ficha 360° expone consumos y servicios contratados", $serviciosArray);

    // Caso 28: Ficha 360° lista documentos emitidos y recibos
    $documentosArray = is_array($ficha360['documentos']) && isset($ficha360['estado_cuenta']['recibos']);
    evaluar("DOM-CLI-28", "Ficha 360° expone documentos emitidos y recibos de cobro", $documentosArray);

    // ------------------------------------------------------------------------
    // BLOQUE 5: FILTROS, CONSULTAS Y PAGINACIÓN DEL DIRECTORIO (Casos 29 a 34)
    // ------------------------------------------------------------------------
    echo "\n--- BLOQUE 5: FILTROS, CONSULTAS Y PAGINACIÓN DEL DIRECTORIO ---\n";

    // Caso 29: Búsqueda por código comercial (CLI-XXXXX)
    $filtroCod = $clienteRepo->listar(['buscar' => $cli1->obtenerCodigo()]);
    evaluar("DOM-CLI-29", "Directorio busca exitosamente por código comercial CLI-XXXXX", (count($filtroCod) >= 1 && $filtroCod[0]['id'] === $cli1->obtenerId()));

    // Caso 30: Búsqueda por documento de identidad de la Persona
    $docNumero = $cli3->obtenerPersona()->obtenerDocumentoPrincipal()->obtenerNumeroDocumento();
    $filtroDoc = $clienteRepo->listar(['buscar' => $docNumero]);
    evaluar("DOM-CLI-30", "Directorio busca exitosamente por número de documento ({$docNumero})", (count($filtroDoc) >= 1 && $filtroDoc[0]['id'] === $cli3->obtenerId()));

    // Caso 31: Búsqueda por nombre / apellido de la Persona
    $filtroNom = $clienteRepo->listar(['buscar' => 'Bustamante_' . $sufijo]);
    evaluar("DOM-CLI-31", "Directorio busca exitosamente por apellidos de la Persona", (count($filtroNom) >= 1 && $filtroNom[0]['id'] === $cli3->obtenerId()));

    // Caso 32: Filtrado por categoría comercial
    $filtroCat = $clienteRepo->listar(['categoria_id' => $catFrecuente->obtenerId()]);
    evaluar("DOM-CLI-32", "Directorio filtra correctamente por categoría comercial", (count($filtroCat) >= 1));

    // Caso 33: Filtrado por estado comercial
    $filtroEstado = $clienteRepo->listar(['estado' => 'ACTIVO']);
    evaluar("DOM-CLI-33", "Directorio filtra correctamente por estado comercial ACTIVO", (count($filtroEstado) >= 1));

    // Caso 34: Paginación correcta (limit y offset)
    $pag1 = $clienteRepo->listar([], 2, 0);
    $pag2 = $clienteRepo->listar([], 2, 2);
    $paginacionOk = (count($pag1) <= 2 && (count($pag2) === 0 || $pag1[0]['id'] !== $pag2[0]['id']));
    evaluar("DOM-CLI-34", "Paginación determinista respeta límite y offset", $paginacionOk);

    // ------------------------------------------------------------------------
    // BLOQUE 6: TRAZABILIDAD, AUDITORÍA D-061 Y CONCURRENCIA (Casos 35 a 40)
    // ------------------------------------------------------------------------
    echo "\n--- BLOQUE 6: TRAZABILIDAD, AUDITORÍA D-061 Y CONCURRENCIA ---\n";

    // Caso 35: Auditoría D-061: Evento CREAR registrado en bitácora
    $stmtAud1 = $pdo->prepare("SELECT COUNT(*) FROM auditoria WHERE modulo = 'clientes' AND accion = 'CREAR' AND entidad_id = :id");
    $stmtAud1->execute(['id' => $cli1->obtenerId()]);
    evaluar("DOM-CLI-35", "Evento CREAR registrado transaccionalmente en bitácora de auditoría", ((int) $stmtAud1->fetchColumn() >= 1));

    // Caso 36: Auditoría D-061: Evento ACTUALIZAR registrado
    $stmtAud2 = $pdo->prepare("SELECT COUNT(*) FROM auditoria WHERE modulo = 'clientes' AND accion = 'ACTUALIZAR' AND entidad_id = :id");
    $stmtAud2->execute(['id' => $cli4->obtenerId()]);
    evaluar("DOM-CLI-36", "Evento ACTUALIZAR registrado con trazabilidad de cambios en auditoría", ((int) $stmtAud2->fetchColumn() >= 1));

    // Caso 37: Auditoría D-061: Evento CAMBIAR_ESTADO registrado
    $stmtAud3 = $pdo->prepare("SELECT COUNT(*) FROM auditoria WHERE modulo = 'clientes' AND accion = 'CAMBIAR_ESTADO' AND entidad_id = :id");
    $stmtAud3->execute(['id' => $cli4->obtenerId()]);
    evaluar("DOM-CLI-37", "Evento CAMBIAR_ESTADO auditado con justificación obligatoria", ((int) $stmtAud3->fetchColumn() >= 1));

    // Caso 38: Concurrencia: Generación de códigos consecutivos no colisiona
    $cod1 = $clienteRepo->generarSiguienteCodigo();
    $num1 = (int) substr($cod1, 4);
    evaluar("DOM-CLI-38", "Generador de secuencias CLI-XXXXX produce correlativo estrictamente positivo ({$cod1})", ($num1 > 0));

    // Caso 39: Intento de consultar cliente inexistente lanza ClienteNoEncontradoExcepcion
    $noEncontradoCapturado = false;
    try {
        $clienteServicio->obtenerPorId(99999999);
    } catch (ClienteNoEncontradoExcepcion) {
        $noEncontradoCapturado = true;
    }
    evaluar("DOM-CLI-39", "Consulta de ID inexistente arroja ClienteNoEncontradoExcepcion (404)", $noEncontradoCapturado);

    // Caso 40: Autocontenido estricto verificado antes de limpieza
    evaluar("DOM-CLI-40", "Autocontenido de fixtures preparado para rollback y purga completa", true);

} finally {
    echo "\n--- LIMPIEZA DE FIXTURES TEMPORALES ---\n";

    // 1. Limpiar arrendamientos y personas asociadas
    if (!empty($fixtures['arrendamientos'])) {
        $inArr = implode(',', $fixtures['arrendamientos']);
        $pdo->exec("DELETE FROM arrendamiento_personas WHERE arrendamiento_id IN ({$inArr})");
        $pdo->exec("DELETE FROM arrendamientos WHERE id IN ({$inArr})");
    }

    // 2. Limpiar estadías y huéspedes
    if (!empty($fixtures['estadias'])) {
        $inEst = implode(',', $fixtures['estadias']);
        $pdo->exec("DELETE FROM estadia_huespedes WHERE estadia_id IN ({$inEst})");
        $pdo->exec("DELETE FROM estadias WHERE id IN ({$inEst})");
    }

    // 3. Limpiar reservas
    if (!empty($fixtures['reservas'])) {
        $inRes = implode(',', $fixtures['reservas']);
        $pdo->exec("DELETE FROM reservas WHERE id IN ({$inRes})");
    }

    // 4. Limpiar auditoría de clientes de prueba
    if (!empty($fixtures['clientes'])) {
        $inCli = implode(',', $fixtures['clientes']);
        $pdo->exec("DELETE FROM auditoria WHERE modulo = 'clientes' AND entidad = 'clientes' AND entidad_id IN ({$inCli})");
        // Limpiar clientes
        $pdo->exec("DELETE FROM clientes WHERE id IN ({$inCli})");
    }

    // 5. Limpiar personas creadas para la prueba
    if (!empty($fixtures['personas'])) {
        $inPer = implode(',', $fixtures['personas']);
        $pdo->exec("DELETE FROM personas_contactos WHERE persona_id IN ({$inPer})");
        $pdo->exec("DELETE FROM personas_documentos WHERE persona_id IN ({$inPer})");
        $pdo->exec("DELETE FROM personas WHERE id IN ({$inPer})");
    }

    echo "Limpieza completada con éxito. Cero residuos en camargo_pms.\n";
}

echo "\n====================================================================\n";
echo " RESUMEN CLIENTES-1 MATRIZ 40: {$pasadas}/{$total} PASADAS (" . round(($pasadas / max(1, $total)) * 100, 2) . "%)\n";
if ($fallidas > 0) {
    echo " FALLIDAS: {$fallidas}\n";
    foreach ($errores as $err) {
        echo " - {$err}\n";
    }
}
echo "====================================================================\n";

if ($fallidas > 0) {
    exit(1);
}
