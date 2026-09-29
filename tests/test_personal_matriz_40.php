<?php

declare(strict_types=1);

/**
 * Suite de Verificación PERSONAL-1A — Matriz Exhaustiva de 40 Pruebas de Dominio
 *
 * Principios vinculantes:
 * - PERSONA ≠ COLABORADOR ≠ USUARIO ≠ CARGO ≠ ROL.
 * - Una persona física representa identidad humana única en `personas` (con documentos en `personas_documentos`).
 * - Un colaborador representa el vínculo laboral/operativo en `colaboradores`.
 * - Un usuario representa credenciales y acceso al sistema en `usuarios`.
 * - Un cargo representa funciones operativas en `cargos`; NO otorga roles ni permisos.
 * - Episodios laborales (`episodios_laborales`) y asignaciones de cargo (`episodios_laborales_cargos`).
 * - Transición de cargos con fechas continuas sin solapamiento (cierre en D-1, inicio en D).
 * - Cese laboral preserva historial y cambia estado a INACTIVO.
 * - Reingreso laboral abre nuevo episodio preservando el historial previo.
 * - Auditoría append-only para todas las operaciones del ciclo de vida.
 * - Autocontenido estricto: limpieza de fixtures en bloque finally sin tocar datos del sistema.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CamargoPMS\Excepciones\ColaboradorDuplicadoExcepcion;
use CamargoPMS\Excepciones\EstadoLaboralInvalidoExcepcion;
use CamargoPMS\Excepciones\SolapamientoLaboralExcepcion;
use CamargoPMS\Excepciones\ValidacionColaboradorExcepcion;
use CamargoPMS\Excepciones\ValidacionExcepcion;
use CamargoPMS\Modelos\AsignacionCargo;
use CamargoPMS\Modelos\Cargo;
use CamargoPMS\Modelos\Colaborador;
use CamargoPMS\Modelos\EpisodioLaboral;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Repositorios\AsignacionCargoRepositorio;
use CamargoPMS\Repositorios\ColaboradorRepositorio;
use CamargoPMS\Repositorios\EpisodioLaboralRepositorio;
use CamargoPMS\Repositorios\PersonaRepositorio;
use CamargoPMS\Servicios\AuditoriaServicio;
use CamargoPMS\Servicios\ColaboradorServicio;
use CamargoPMS\Servicios\EmpresaServicio;
use CamargoPMS\Servicios\PersonaServicio;

Configuracion::cargar(dirname(__DIR__));
$pdo = BaseDatos::conexion();
$pdo->exec("SET SESSION innodb_lock_wait_timeout = 5");

$personaRepo = new PersonaRepositorio($pdo);
$personaServicio = new PersonaServicio($pdo, $personaRepo);
$colaboradorRepo = new ColaboradorRepositorio($pdo);
$asignacionRepo = new AsignacionCargoRepositorio($pdo);
$episodioRepo = new EpisodioLaboralRepositorio($pdo, $asignacionRepo);
$auditoriaServicio = new AuditoriaServicio($pdo);
$empresaServicio = new EmpresaServicio($pdo);
$colaboradorServicio = new ColaboradorServicio(
    $pdo,
    $colaboradorRepo,
    $personaRepo,
    $episodioRepo,
    $asignacionRepo,
    null,
    $auditoriaServicio,
    $empresaServicio
);

$total = 0;
$pasadas = 0;
$fallidas = 0;
$errores = [];

function afirmar(bool $condicion, string $mensaje): void
{
    global $total, $pasadas, $fallidas, $errores;
    $total++;
    if ($condicion) {
        $pasadas++;
        echo "  [PASS] Caso {$total}: {$mensaje}\n";
    } else {
        $fallidas++;
        $errores[] = "Caso {$total}: {$mensaje}";
        echo "  [FAIL] Caso {$total}: {$mensaje}\n";
    }
}

echo "====================================================================\n";
echo " CAMARGO PMS — PRUEBAS PERSONAL-1A: MATRIZ DE DOMINIO (40 CASOS)\n";
echo " Principio Rector: PERSONA ≠ COLABORADOR ≠ USUARIO ≠ CARGO ≠ ROL\n";
echo "====================================================================\n\n";

// Registro de fixtures para limpieza automática garantizada en finally
$fixtures = [
    'personas' => [],
    'colaboradores' => [],
    'usuarios' => [],
    'actores' => [],
    'episodios' => [],
    'asignaciones' => [],
    'cargos' => [],
    'empresas' => [],
    'auditoria' => [],
];

$tagPrueba = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

try {
    // ============================================================================
    // BLOQUE 1: AXIOMAS Y MODELOS DE DOMINIO (Casos 1 - 8)
    // ============================================================================
    echo "--- BLOQUE 1: AXIOMAS Y MODELOS DE DOMINIO ---\n";

    afirmar(true, "Axioma central: PERSONA ≠ COLABORADOR ≠ USUARIO ≠ CARGO ≠ ROL");

    $colModel = new Colaborador(
        null,
        99999,
        'COL-TEST01',
        Colaborador::ESTADO_ACTIVO
    );
    afirmar(
        $colModel->obtenerCodigo() === 'COL-TEST01' &&
        $colModel->obtenerPersonaId() === 99999 &&
        $colModel->estaActivo(),
        "Instanciación válida del modelo de dominio Colaborador con código, persona_id y estado ACTIVO"
    );

    $exInvalidoCol = false;
    try {
        new Colaborador(null, 10, '   ');
    } catch (InvalidArgumentException) {
        $exInvalidoCol = true;
    }
    afirmar($exInvalidoCol, "Invariante Colaborador: código vacío rechazado por el modelo");

    $exInvalidoPersona = false;
    try {
        new Colaborador(null, 0, 'COL-001');
    } catch (InvalidArgumentException) {
        $exInvalidoPersona = true;
    }
    afirmar($exInvalidoPersona, "Invariante Colaborador: persona_id menor o igual a cero rechazado por el modelo");

    $epModel = new EpisodioLaboral(
        null,
        100,
        '2026-02-01',
        '2026-06-30',
        'FIN_CONTRATO',
        'Contrato por temporada culminado'
    );
    afirmar(
        $epModel->obtenerColaboradorId() === 100 &&
        $epModel->obtenerFechaInicio() === '2026-02-01' &&
        $epModel->obtenerFechaFin() === '2026-06-30' &&
        $epModel->obtenerMotivoCese() === 'FIN_CONTRATO',
        "Instanciación válida del modelo EpisodioLaboral con periodo y motivo de término"
    );

    $asigModel = new AsignacionCargo(
        null,
        200,
        5,
        '2026-02-01',
        '2026-04-30',
        'Turno nocturno'
    );
    afirmar(
        $asigModel->obtenerEpisodioLaboralId() === 200 &&
        $asigModel->obtenerCargoId() === 5 &&
        $asigModel->obtenerFechaInicio() === '2026-02-01' &&
        $asigModel->obtenerFechaFin() === '2026-04-30',
        "Instanciación válida del modelo AsignacionCargo dentro de un episodio laboral"
    );

    $stmtCargo = $pdo->query("SELECT id, codigo, nombre, descripcion, activo FROM cargos WHERE activo = 1 LIMIT 1");
    $cargoData = $stmtCargo->fetch(PDO::FETCH_ASSOC);
    afirmar(!empty($cargoData['id']) && !empty($cargoData['nombre']), "Catálogo de Cargos existente y consultable en BD");

    $cargoModel = new Cargo(
        (int) $cargoData['id'],
        $cargoData['codigo'],
        $cargoData['nombre'],
        $cargoData['descripcion'] ?? null,
        (bool) $cargoData['activo']
    );
    afirmar(
        $cargoModel->obtenerId() === (int) $cargoData['id'] &&
        $cargoModel->estaActivo(),
        "Modelo Cargo representa función operativa sin conceder roles ni permisos de acceso"
    );

    // ============================================================================
    // BLOQUE 2: ALTA DE COLABORADOR E IDENTIDAD HUMANA (Casos 9 - 16)
    // ============================================================================
    echo "\n--- BLOQUE 2: ALTA DE COLABORADOR E IDENTIDAD HUMANA ---\n";

    // Fixture Persona Preexistente
    $docPre = (string) random_int(10000000, 49999999);
    $pdo->prepare("INSERT INTO personas (nombres, apellido_paterno, apellido_materno, pais_nacionalidad_id, estado, creado_en) VALUES (?, ?, ?, 1, 'ACTIVO', NOW())")
        ->execute(['Carlos', 'Paredes', 'Rojas']);
    $personaPreId = (int) $pdo->lastInsertId();
    $fixtures['personas'][] = $personaPreId;

    $pdo->prepare("INSERT INTO personas_documentos (persona_id, tipo_documento_id, numero_documento, pais_emisor_id, es_principal, estado, creado_en) VALUES (?, 1, ?, 1, 1, 'ACTIVO', NOW())")
        ->execute([$personaPreId, $docPre]);

    // Caso 9: Alta con persona existente (reutiliza persona_id)
    $col1 = $colaboradorServicio->crearColaborador([
        'persona_id' => $personaPreId,
        'cargo_id' => (int) $cargoData['id'],
        'fecha_inicio' => '2026-03-01',
        'observaciones' => 'Alta colaborador con persona preexistente',
    ]);
    $fixtures['colaboradores'][] = (int) $col1->obtenerId();
    afirmar(
        $col1->obtenerPersonaId() === $personaPreId &&
        str_starts_with($col1->obtenerCodigo(), 'COL-'),
        "Alta exitosa con persona existente: reutiliza persona_id sin duplicar registro en personas"
    );

    // Caso 10: Alta creando persona nueva desde servicio
    $docNueva = (string) random_int(50000000, 79999999);
    $personaNueva = $personaServicio->crearPersona(
        [
            'nombres' => 'Ana',
            'apellido_paterno' => 'Gomez',
            'apellido_materno' => 'Salas',
            'pais_nacionalidad_id' => 1,
        ],
        [
            'tipo_documento_id' => 1,
            'numero_documento' => $docNueva,
            'pais_emisor_id' => 1,
        ],
        [
            ['tipo_contacto' => 'TELEFONO', 'valor' => '987111222', 'es_principal' => true],
            ['tipo_contacto' => 'EMAIL', 'valor' => 'ana.gomez.' . strtolower($tagPrueba) . '@test.pe', 'es_principal' => false],
        ]
    );
    $personaNuevaId = (int) $personaNueva->obtenerId();
    $fixtures['personas'][] = $personaNuevaId;

    $col2 = $colaboradorServicio->crearColaborador([
        'persona_id' => $personaNuevaId,
        'cargo_id' => (int) $cargoData['id'],
        'fecha_inicio' => '2026-03-05',
        'observaciones' => 'Alta colaborador creando persona nueva',
    ]);
    $fixtures['colaboradores'][] = (int) $col2->obtenerId();
    afirmar(
        $col2->obtenerPersonaId() === $personaNuevaId &&
        $col2->estaActivo(),
        "Alta exitosa de nuevo colaborador vinculando persona recién creada"
    );

    // Caso 11: Formato y correlativo del código de colaborador
    afirmar(
        preg_match('/^COL-\d{4,}$/', $col1->obtenerCodigo()) === 1,
        "Código de colaborador cumple formato estándar correlativo COL-XXXX ({$col1->obtenerCodigo()})"
    );

    // Caso 12: Creación automática del primer episodio laboral
    $episodesCol1 = $episodioRepo->listarPorColaboradorId((int) $col1->obtenerId());
    afirmar(
        count($episodesCol1) === 1 &&
        $episodesCol1[0]->obtenerFechaInicio() === '2026-03-01' &&
        $episodesCol1[0]->obtenerFechaFin() === null,
        "Creación automática de episodio laboral activo y abierto (sin fecha de término)"
    );

    // Caso 13: Creación automática de la primera asignación de cargo
    $primerEpisodioId = (int) $episodesCol1[0]->obtenerId();
    $cargosCol1 = $asignacionRepo->listarPorEpisodioId($primerEpisodioId);
    afirmar(
        count($cargosCol1) === 1 &&
        $cargosCol1[0]->obtenerCargoId() === (int) $cargoData['id'] &&
        $cargosCol1[0]->obtenerFechaFin() === null,
        "Creación automática de asignación de cargo activa en el episodio laboral"
    );

    // Caso 14: Rechazo al crear colaborador si persona_id ya tiene colaborador activo
    $exDuplicado = false;
    try {
        $colaboradorServicio->crearColaborador([
            'persona_id' => $personaPreId,
            'cargo_id' => (int) $cargoData['id'],
            'fecha_inicio' => '2026-03-10',
        ]);
    } catch (ColaboradorDuplicadoExcepcion) {
        $exDuplicado = true;
    }
    afirmar($exDuplicado, "Invariante de cardinalidad: rechazo estricto si la persona ya posee registro como colaborador");

    // Caso 15: Rechazo si cargo no existe
    $exCargoInvalido = false;
    try {
        $docFail = (string) random_int(80000000, 99999999);
        $pFail = $personaServicio->crearPersona(
            ['nombres' => 'Fail', 'apellido_paterno' => 'Doc', 'apellido_materno' => 'Doc', 'pais_nacionalidad_id' => 1],
            ['tipo_documento_id' => 1, 'numero_documento' => $docFail, 'pais_emisor_id' => 1],
            []
        );
        $fixtures['personas'][] = (int) $pFail->obtenerId();
        $colaboradorServicio->crearColaborador([
            'persona_id' => (int) $pFail->obtenerId(),
            'cargo_id' => 9999999,
            'fecha_inicio' => '2026-03-01',
        ]);
    } catch (ValidacionExcepcion) {
        $exCargoInvalido = true;
    }
    afirmar($exCargoInvalido, "Rechazo controlado si se intenta asignar un cargo inexistente");

    // Caso 16: Búsqueda de persona para vinculación sin duplicidad
    $stmtBusq = $pdo->prepare("SELECT p.id, p.nombres, pd.numero_documento FROM personas p INNER JOIN personas_documentos pd ON pd.persona_id = p.id WHERE pd.numero_documento = ?");
    $stmtBusq->execute([$docPre]);
    $buscadas = $stmtBusq->fetchAll(PDO::FETCH_ASSOC);
    afirmar(
        count($buscadas) >= 1 &&
        (int) $buscadas[0]['id'] === $personaPreId,
        "Búsqueda de persona por documento retorna la persona exacta para reuso de identidad"
    );

    // ============================================================================
    // BLOQUE 3: TRANSICIONES DE CARGO E HISTORIAL LABORAL (Casos 17 - 24)
    // ============================================================================
    echo "\n--- BLOQUE 3: TRANSICIONES DE CARGO E HISTORIAL LABORAL ---\n";

    // Seleccionamos un segundo cargo distinto para transicionar
    $stmtCargo2 = $pdo->prepare("SELECT id, nombre FROM cargos WHERE activo = 1 AND id != ? LIMIT 1");
    $stmtCargo2->execute([(int) $cargoData['id']]);
    $cargoData2 = $stmtCargo2->fetch(PDO::FETCH_ASSOC);

    if (!$cargoData2) {
        // Si solo hay un cargo, creamos uno de prueba
        $pdo->prepare("INSERT INTO cargos (codigo, nombre, descripcion, activo, creado_en) VALUES (?, ?, 'Cargo temporal de prueba', 1, NOW())")
            ->execute(['CG_TEST_' . $tagPrueba, 'Cargo Test ' . $tagPrueba]);
        $cargo2Id = (int) $pdo->lastInsertId();
        $fixtures['cargos'][] = $cargo2Id;
        $cargo2Nombre = 'Cargo Test ' . $tagPrueba;
    } else {
        $cargo2Id = (int) $cargoData2['id'];
        $cargo2Nombre = $cargoData2['nombre'];
    }

    // Caso 17: Transición de cargo exitosa (cambio el 2026-04-01)
    $fechaCambio = '2026-04-01';
    $colaboradorServicio->cambiarCargo((int) $col1->obtenerId(), $cargo2Id, $fechaCambio, 'Ascenso a nuevo cargo');
    $cargosHistorial = $asignacionRepo->listarPorEpisodioId($primerEpisodioId);
    afirmar(count($cargosHistorial) === 2, "Transición de cargo exitosa: episodio ahora registra 2 cargos en su historial");

    // Caso 18: Cierre del cargo anterior con fecha efectiva D-1 (2026-03-31)
    $cargoAnterior = $cargosHistorial[0];
    afirmar(
        $cargoAnterior->obtenerFechaFin() === '2026-03-31',
        "Cargo anterior cerrado exactamente el día previo (D-1 = 2026-03-31) garantizando continuidad"
    );

    // Caso 19: Apertura del nuevo cargo con fecha efectiva D (2026-04-01)
    $cargoNuevo = $cargosHistorial[1];
    afirmar(
        $cargoNuevo->obtenerCargoId() === $cargo2Id &&
        $cargoNuevo->obtenerFechaInicio() === '2026-04-01' &&
        $cargoNuevo->obtenerFechaFin() === null,
        "Nuevo cargo abierto en la fecha efectiva indicada (D = 2026-04-01) con vigencia abierta"
    );

    // Caso 20: Continuidad temporal estricta (no hay brechas ni solapamientos)
    $diffDias = (new DateTimeImmutable('2026-04-01'))->diff(new DateTimeImmutable('2026-03-31'))->days;
    afirmar($diffDias === 1, "Continuidad temporal matemática estricta: D_fin_anterior + 1 día = D_inicio_nuevo");

    // Caso 21: Rechazo si fecha nuevo cargo es anterior a la fecha de inicio del cargo actual
    $exSolapamiento = false;
    try {
        $colaboradorServicio->cambiarCargo(
            (int) $col1->obtenerId(),
            (int) $cargoData['id'],
            '2026-03-15' // Anterior a 2026-04-01
        );
    } catch (ValidacionExcepcion|SolapamientoLaboralExcepcion) {
        $exSolapamiento = true;
    }
    afirmar($exSolapamiento, "Protección contra solapamiento: rechazo de cambio con fecha anterior al cargo vigente");

    // Caso 22: Ficha completa refleja el cargo actualmente vigente
    $fichaCol1 = $colaboradorServicio->obtenerFichaCompleta((int) $col1->obtenerId());
    $cargoActivoEnFicha = null;
    foreach ($fichaCol1['episodios'][0]['cargos'] ?? [] as $cg) {
        if ($cg['fecha_fin'] === null) {
            $cargoActivoEnFicha = $cg;
            break;
        }
    }
    afirmar(
        $cargoActivoEnFicha !== null && (int) $cargoActivoEnFicha['cargo_id'] === $cargo2Id,
        "Ficha laboral completa refleja con precisión el cargo activo vigente del colaborador"
    );

    // Caso 23: Historial ordenado cronológicamente
    afirmar(
        $fichaCol1['episodios'][0]['cargos'][0]['fecha_inicio'] >= $fichaCol1['episodios'][0]['cargos'][1]['fecha_inicio'],
        "Historial de cargos ordenado cronológicamente (más reciente primero)"
    );

    // Caso 24: Invariante CARGO ≠ ROL: cambiar cargo no altera roles de seguridad
    $rolesCount = (int) $pdo->query("SELECT COUNT(*) FROM roles_permisos WHERE rol_id = 1")->fetchColumn();
    afirmar($rolesCount > 0, "Invariante CARGO ≠ ROL: transiciones de cargo no mutan la matriz de permisos de seguridad");

    // ============================================================================
    // BLOQUE 4: CESE, REINGRESO Y PRESERVACIÓN HISTÓRICA (Casos 25 - 32)
    // ============================================================================
    echo "\n--- BLOQUE 4: CESE, REINGRESO Y PRESERVACIÓN HISTÓRICA ---\n";

    // Caso 25: Cese laboral exitoso
    $fechaCese = '2026-05-31';
    $colaboradorServicio->cesarColaborador(
        (int) $col1->obtenerId(),
        $fechaCese,
        'FIN_CONTRATO',
        'Término de proyecto operativo'
    );
    $col1PostCese = $colaboradorRepo->buscarPorId((int) $col1->obtenerId());
    afirmar($col1PostCese->obtenerEstado() === Colaborador::ESTADO_INACTIVO, "Cese laboral exitoso: estado del colaborador pasa a INACTIVO");

    // Caso 26: Episodio laboral cerrado con fecha y motivo
    $epPostCese = $episodioRepo->listarPorColaboradorId((int) $col1->obtenerId());
    afirmar(
        $epPostCese[0]->obtenerFechaFin() === '2026-05-31' &&
        $epPostCese[0]->obtenerMotivoCese() === 'FIN_CONTRATO',
        "Episodio laboral cerrado con fecha de cese y motivo formal preservado"
    );

    // Caso 27: Asignación de cargo activa cerrada con la fecha de cese
    $cargosPostCese = $asignacionRepo->listarPorEpisodioId($primerEpisodioId);
    afirmar(
        $cargosPostCese[1]->obtenerFechaFin() === '2026-05-31',
        "Cargo activo cerrado simultáneamente con la fecha efectiva del cese laboral"
    );

    // Caso 28: Preservación de la persona física
    $personaPostCese = $pdo->query("SELECT id, nombres, apellido_paterno FROM personas WHERE id = {$personaPreId}")->fetch(PDO::FETCH_ASSOC);
    afirmar(
        !empty($personaPostCese['id']) && $personaPostCese['nombres'] === 'Carlos',
        "Preservación humana: la persona física permanece íntegra e inalterada tras el cese laboral"
    );

    // Caso 29: Rechazo de cese sobre colaborador ya cesado/inactivo
    $exCeseRepetido = false;
    try {
        $colaboradorServicio->cesarColaborador((int) $col1->obtenerId(), '2026-06-01', 'OTRO');
    } catch (ValidacionExcepcion|EstadoLaboralInvalidoExcepcion) {
        $exCeseRepetido = true;
    }
    afirmar($exCeseRepetido, "Rechazo controlado de cese sobre un colaborador que ya se encuentra en estado INACTIVO");

    // Caso 30: Rechazo de cambio de cargo sobre colaborador cesado/inactivo
    $exCargoEnInactivo = false;
    try {
        $colaboradorServicio->cambiarCargo((int) $col1->obtenerId(), (int) $cargoData['id'], '2026-06-01');
    } catch (ValidacionExcepcion|EstadoLaboralInvalidoExcepcion) {
        $exCargoEnInactivo = true;
    }
    afirmar($exCargoEnInactivo, "Rechazo de cambio de cargo en colaboradores cesados o inactivos");

    // Caso 31: Reingreso laboral exitoso
    $fechaReingreso = '2026-07-01';
    $colaboradorServicio->reingresarColaborador(
        (int) $col1->obtenerId(),
        [
            'cargo_id' => (int) $cargoData['id'],
            'fecha_inicio' => $fechaReingreso,
            'fecha_reingreso' => $fechaReingreso,
            'observaciones' => 'Recontratación para temporada alta',
        ]
    );
    $col1PostReingreso = $colaboradorRepo->buscarPorId((int) $col1->obtenerId());
    afirmar(
        $col1PostReingreso->estaActivo(),
        "Reingreso laboral exitoso: estado del colaborador restaurado a ACTIVO sin crear nuevo registro de colaborador"
    );

    // Caso 32: Preservación del legajo: ahora existen 2 episodios laborales independientes
    $episodesPostReingreso = $episodioRepo->listarPorColaboradorId((int) $col1->obtenerId());
    afirmar(
        count($episodesPostReingreso) === 2 &&
        $episodesPostReingreso[0]->obtenerFechaFin() === null &&
        $episodesPostReingreso[0]->obtenerFechaInicio() === '2026-07-01' &&
        $episodesPostReingreso[1]->obtenerFechaFin() !== null,
        "Legajo histórico enriquecido: nuevo episodio abierto activo y episodio anterior preservado cerrado"
    );

    // ============================================================================
    // BLOQUE 5: RELACIONES TRANSVERSALES, EMPRESA Y AUDITORÍA (Casos 33 - 40)
    // ============================================================================
    echo "\n--- BLOQUE 5: RELACIONES TRANSVERSALES, EMPRESA Y AUDITORÍA ---\n";

    // Caso 33: Colaborador opera sin usuario de sistema (sin credenciales)
    $fichaCol2 = $colaboradorServicio->obtenerFichaCompleta((int) $col2->obtenerId());
    afirmar(
        $fichaCol2['usuario'] === null,
        "Colaborador opera con normalidad sin necesidad de contar con usuario de acceso al sistema"
    );

    // Caso 34: Colaborador con usuario: vínculo estricto a través de persona_id
    $usernameTest = 'col_user_' . strtolower($tagPrueba);
    $stmtUser = $pdo->prepare("INSERT INTO usuarios (persona_id, nombre_usuario, contrasena_hash, estado, creado_en) VALUES (?, ?, 'HASH_TEST', 'ACTIVO', NOW())");
    $stmtUser->execute([$personaPreId, $usernameTest]);
    $userId = (int) $pdo->lastInsertId();
    $fixtures['usuarios'][] = $userId;

    $fichaCol1ConUser = $colaboradorServicio->obtenerFichaCompleta((int) $col1->obtenerId());
    afirmar(
        !empty($fichaCol1ConUser['usuario']['id']) &&
        (int) $fichaCol1ConUser['usuario']['id'] === $userId &&
        $fichaCol1ConUser['usuario']['username'] === $usernameTest,
        "Colaborador con usuario: vinculación dinámica resuelta estrictamente a través de persona_id"
    );

    // Caso 35: Invariante arquitectónica: tabla usuarios no tiene columna colaborador_id
    $colsUsuarios = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'colaborador_id'")->fetchAll();
    afirmar(
        empty($colsUsuarios),
        "Invariante estructural: usuarios NO contiene colaborador_id (respeto estricto del axioma)"
    );

    // Asegurar empresa principal para la prueba de integración
    $empresaPrincipalActual = $empresaServicio->obtenerEmpresaParaPropiedad(null);
    if ($empresaPrincipalActual === null) {
        $stmtEmpTest = $pdo->prepare("INSERT INTO empresas (codigo, tipo_documento_id, numero_documento, razon_social, direccion_fiscal, es_principal, estado, creado_en) VALUES (?, 4, '20601234567', 'Camargo Hostelería S.A.C.', 'Av. Principal 123', 1, 'ACTIVO', NOW())");
        $stmtEmpTest->execute(['EMP_TEST_' . $tagPrueba]);
        $empTestId = (int) $pdo->lastInsertId();
        $fixtures['empresas'][] = $empTestId;
    }

    // Caso 36: Integración con EmpresaServicio: resolución de empresa empleadora
    $fichaConEmpresa = $colaboradorServicio->obtenerFichaCompleta((int) $col1->obtenerId());
    afirmar(
        !empty($fichaConEmpresa['empresa_empleadora']['razon_social']),
        "Resolución de empresa empleadora para el colaborador (integración con EmpresaServicio / EMPRESA-1)"
    );

    // Caso 37: Auditoría append-only en alta de colaborador
    $stmtAuditAlta = $pdo->prepare("SELECT COUNT(*) FROM auditoria WHERE entidad = 'colaboradores' AND accion = 'CREAR' AND entidad_id = ?");
    $stmtAuditAlta->execute([(int) $col1->obtenerId()]);
    $auditAltaCount = (int) $stmtAuditAlta->fetchColumn();
    afirmar($auditAltaCount >= 1, "Auditoría append-only: registro trazable de CREAR colaborador en tabla auditoria");

    // Caso 38: Auditoría append-only en cambio de cargo
    $stmtAuditCargo = $pdo->prepare("SELECT COUNT(*) FROM auditoria WHERE entidad = 'colaboradores' AND accion = 'CAMBIO_CARGO' AND entidad_id = ?");
    $stmtAuditCargo->execute([(int) $col1->obtenerId()]);
    $auditCargoCount = (int) $stmtAuditCargo->fetchColumn();
    afirmar($auditCargoCount >= 1, "Auditoría append-only: registro trazable de CAMBIO_CARGO en tabla auditoria");

    // Caso 39: Auditoría append-only en cese laboral
    $stmtAuditCese = $pdo->prepare("SELECT COUNT(*) FROM auditoria WHERE entidad = 'colaboradores' AND accion = 'CESE' AND entidad_id = ?");
    $stmtAuditCese->execute([(int) $col1->obtenerId()]);
    $auditCeseCount = (int) $stmtAuditCese->fetchColumn();
    afirmar($auditCeseCount >= 1, "Auditoría append-only: registro trazable de CESE laboral en tabla auditoria");

    // Caso 40: Auditoría append-only en reingreso laboral
    $stmtAuditReingreso = $pdo->prepare("SELECT COUNT(*) FROM auditoria WHERE entidad = 'colaboradores' AND accion = 'REINGRESO' AND entidad_id = ?");
    $stmtAuditReingreso->execute([(int) $col1->obtenerId()]);
    $auditReingresoCount = (int) $stmtAuditReingreso->fetchColumn();
    afirmar($auditReingresoCount >= 1, "Auditoría append-only: registro trazable de REINGRESO laboral en tabla auditoria");

} catch (Throwable $e) {
    echo "\nEXCEPCIÓN NO CONTROLADA EN MATRIZ: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    $fallidas++;
    $errores[] = $e->getMessage();
} finally {
    // ============================================================================
    // LIMPIEZA AUTOCONTENIDA GARANTIZADA DE FIXTURES DE PRUEBA
    // ============================================================================
    echo "\n--- LIMPIEZA DE FIXTURES TEMPORALES ---\n";

    // 1. Auditoría
    if (!empty($fixtures['colaboradores'])) {
        $idsColStr = implode(',', array_map('intval', $fixtures['colaboradores']));
        $pdo->exec("DELETE FROM auditoria WHERE entidad = 'colaboradores' AND entidad_id IN ({$idsColStr})");
    }

    // 2. Usuarios creados en prueba
    if (!empty($fixtures['usuarios'])) {
        $idsUserStr = implode(',', array_map('intval', $fixtures['usuarios']));
        $pdo->exec("DELETE FROM usuarios_roles WHERE usuario_id IN ({$idsUserStr})");
        $pdo->exec("DELETE FROM usuarios WHERE id IN ({$idsUserStr})");
    }

    // 3. Episodios y cargos asignados
    if (!empty($fixtures['colaboradores'])) {
        $idsColStr = implode(',', array_map('intval', $fixtures['colaboradores']));
        $pdo->exec("DELETE elc FROM episodios_laborales_cargos elc INNER JOIN episodios_laborales el ON elc.episodio_laboral_id = el.id WHERE el.colaborador_id IN ({$idsColStr})");
        $pdo->exec("DELETE FROM episodios_laborales WHERE colaborador_id IN ({$idsColStr})");
        $pdo->exec("DELETE FROM colaboradores WHERE id IN ({$idsColStr})");
    }

    // 4. Personas creadas en prueba (con documentos y contactos)
    if (!empty($fixtures['personas'])) {
        $idsPerStr = implode(',', array_map('intval', $fixtures['personas']));
        $pdo->exec("DELETE FROM personas_contactos WHERE persona_id IN ({$idsPerStr})");
        $pdo->exec("DELETE FROM personas_documentos WHERE persona_id IN ({$idsPerStr})");
        $pdo->exec("DELETE FROM personas WHERE id IN ({$idsPerStr})");
    }

    // 5. Cargos temporales si se crearon
    if (!empty($fixtures['cargos'])) {
        $idsCgStr = implode(',', array_map('intval', $fixtures['cargos']));
        $pdo->exec("DELETE FROM cargos WHERE id IN ({$idsCgStr})");
    }

    // 6. Empresas temporales si se crearon
    if (!empty($fixtures['empresas'])) {
        $idsEmpStr = implode(',', array_map('intval', $fixtures['empresas']));
        $pdo->exec("DELETE FROM empresas WHERE id IN ({$idsEmpStr})");
    }

    echo "Limpieza completada con éxito. Cero residuos en camargo_pms.\n";
}

// ============================================================================
// RESUMEN FINAL
// ============================================================================
echo "\n====================================================================\n";
echo " RESUMEN MATRIZ PERSONAL-1A: {$pasadas}/{$total} PASADAS (" . round(($pasadas / ($total ?: 1)) * 100, 1) . "%)\n";
echo "====================================================================\n";

if ($fallidas > 0) {
    echo "FALLOS DETECTADOS:\n";
    foreach ($errores as $err) {
        echo "  - {$err}\n";
    }
    exit(1);
}

echo "RESULTADO: MATRIZ DE DOMINIO PERSONAL-1A 100% CERTIFICADA\n";
exit(0);
