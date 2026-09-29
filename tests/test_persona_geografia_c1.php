<?php

declare(strict_types=1);

/**
 * Suite de Verificación PERSONAL-1A-C1 — Persona Completa y Geografía Normalizada.
 *
 * Principios vinculantes:
 * - PERSONA ≠ COLABORADOR ≠ USUARIO ≠ CARGO ≠ ROL
 * - NACIONALIDAD ≠ PAÍS DE RESIDENCIA
 * - Geografía relacional INEI en base de datos: departamentos -> provincias -> distritos (cero JSON operacional)
 * - Cascada geográfica estricta para Perú y datos libres para el extranjero
 * - Género y fecha de nacimiento normalizados con validaciones estrictas
 * - Autocontenido estricto: limpieza de fixtures en bloque finally
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CamargoPMS\Excepciones\ValidacionExcepcion;
use CamargoPMS\Modelos\Persona;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Repositorios\GeografiaRepositorio;
use CamargoPMS\Repositorios\PersonaRepositorio;
use CamargoPMS\Repositorios\ColaboradorRepositorio;
use CamargoPMS\Servicios\GeografiaServicio;
use CamargoPMS\Servicios\PersonaServicio;
use CamargoPMS\Servicios\ColaboradorServicio;
use CamargoPMS\Servicios\EmpresaServicio;
use CamargoPMS\Servicios\AuditoriaServicio;

Configuracion::cargar(dirname(__DIR__));
$pdo = BaseDatos::conexion();
$pdo->exec("SET SESSION innodb_lock_wait_timeout = 5");

$geografiaRepo = new GeografiaRepositorio($pdo);
$geografiaServicio = new GeografiaServicio($geografiaRepo);
$personaRepo = new PersonaRepositorio($pdo, null, null, null, $geografiaRepo);
$personaServicio = new PersonaServicio($pdo, $personaRepo, null, null, null, null, $geografiaServicio);
$empresaServicio = new EmpresaServicio($pdo);
$colaboradorRepo = new ColaboradorRepositorio($pdo, $personaRepo);
$colaboradorServicio = new ColaboradorServicio(
    $pdo,
    $colaboradorRepo,
    $personaRepo,
    null,
    null,
    null,
    new AuditoriaServicio($pdo),
    $empresaServicio
);

echo "====================================================================\n";
echo " CAMARGO PMS — PRUEBAS PERSONAL-1A-C1: PERSONA COMPLETA Y GEOGRAFÍA\n";
echo " Principio Rector: PERSONA ≠ COLABORADOR ≠ USUARIO ≠ CARGO ≠ ROL\n";
echo "====================================================================\n\n";

$pruebasPasadas = 0;
$pruebasTotales = 0;
$personasLimpieza = [];
$colaboradoresLimpieza = [];
$usuariosLimpieza = [];
$actoresLimpieza = [];

function afirmar(bool $condicion, string $descripcion): void
{
    global $pruebasPasadas, $pruebasTotales;
    $pruebasTotales++;
    if ($condicion) {
        $pruebasPasadas++;
        echo "  [PASS] Caso {$pruebasTotales}: {$descripcion}\n";
    } else {
        echo "  [FAIL] Caso {$pruebasTotales}: {$descripcion}\n";
    }
}

try {
    // -------------------------------------------------------------------------
    echo "--- BLOQUE 1: CATÁLOGOS GEOGRÁFICOS RELACIONALES (INEI) ---\n";
    // -------------------------------------------------------------------------

    // Caso 1: Conteo exacto de departamentos
    $totalDepartamentos = (int) $pdo->query('SELECT COUNT(*) FROM departamentos')->fetchColumn();
    afirmar($totalDepartamentos === 25, "Catálogo oficial contiene exactamente 25 departamentos INEI ({$totalDepartamentos})");

    // Caso 2: Conteo exacto de provincias
    $totalProvincias = (int) $pdo->query('SELECT COUNT(*) FROM provincias')->fetchColumn();
    afirmar($totalProvincias === 196, "Catálogo oficial contiene exactamente 196 provincias INEI ({$totalProvincias})");

    // Caso 3: Conteo exacto de distritos
    $totalDistritos = (int) $pdo->query('SELECT COUNT(*) FROM distritos')->fetchColumn();
    afirmar($totalDistritos === 1874, "Catálogo oficial contiene exactamente 1,874 distritos INEI ({$totalDistritos})");

    // Caso 4: Resolución dinámica del país predeterminado sin hardcodear ID
    $paisDefault = $geografiaServicio->obtenerPaisDefault();
    afirmar(
        $paisDefault['codigo_iso2'] === 'PE' && $paisDefault['nombre'] === 'Perú',
        "Resolución dinámica de país base por ISO 'PE': {$paisDefault['nombre']} (ID: {$paisDefault['id']})"
    );

    // Caso 5: Derivación relacional de jerarquía territorial desde distrito_id (Miraflores, Lima: UBIGEO 150122)
    $stmtMiraflores = $pdo->prepare("SELECT id FROM distritos WHERE codigo_ubigeo = '150122' LIMIT 1");
    $stmtMiraflores->execute();
    $distritoMirafloresId = (int) $stmtMiraflores->fetchColumn();

    $jerarquia = $geografiaServicio->obtenerJerarquiaPorDistritoId($distritoMirafloresId);
    afirmar(
        $jerarquia !== null &&
        $jerarquia['departamento_nombre'] === 'Lima' &&
        $jerarquia['provincia_nombre'] === 'Lima' &&
        $jerarquia['distrito_nombre'] === 'Miraflores' &&
        $jerarquia['distrito_codigo_ubigeo'] === '150122',
        "Jerarquía relacional derivada: {$jerarquia['distrito_nombre']} -> {$jerarquia['provincia_nombre']} -> {$jerarquia['departamento_nombre']} (UBIGEO: {$jerarquia['distrito_codigo_ubigeo']})"
    );

    // -------------------------------------------------------------------------
    echo "\n--- BLOQUE 2: CONTRATO DE PERSONA COMPLETA Y VALIDACIONES ---\n";
    // -------------------------------------------------------------------------

    // Caso 6: Creación de Persona completa para residente en Perú
    $persona1 = $personaServicio->crearPersona([
        'nombres' => 'Valeria Sofía',
        'apellido_paterno' => 'Mendoza',
        'apellido_materno' => 'Castillo',
        'genero' => 'FEMENINO',
        'fecha_nacimiento' => '1992-08-20',
        'pais_nacionalidad_id' => (int) $paisDefault['id'],
        'pais_residencia_id' => (int) $paisDefault['id'],
        'distrito_id' => $distritoMirafloresId,
        'direccion' => 'Av. Larco 456, Depto 302',
    ], [
        'tipo_documento_id' => 1, // DNI
        'numero_documento' => '78451296',
        'pais_emisor_id' => (int) $paisDefault['id'],
    ], [
        [
            'tipo_contacto' => 'TELEFONO',
            'valor' => '987123456',
            'es_whatsapp' => true,
            'es_principal' => true,
        ],
        [
            'tipo_contacto' => 'EMAIL',
            'valor' => 'valeria.mendoza@pms-test.pe',
            'es_whatsapp' => false,
            'es_principal' => false,
        ]
    ]);
    $personasLimpieza[] = (int) $persona1->obtenerId();

    afirmar(
        $persona1->obtenerId() !== null &&
        $persona1->obtenerGenero() === 'FEMENINO' &&
        $persona1->obtenerFechaNacimiento() === '1992-08-20' &&
        $persona1->obtenerDistritoNombre() === 'Miraflores' &&
        $persona1->obtenerProvinciaNombre() === 'Lima' &&
        $persona1->obtenerDepartamentoNombre() === 'Lima' &&
        $persona1->obtenerUbigeo() === '150122',
        "Persona creada con atributos completos e hidratación de jerarquía territorial INEI"
    );

    // Caso 7: Preservación independiente de nombres, apellido paterno y apellido materno
    afirmar(
        $persona1->obtenerNombres() === 'Valeria Sofía' &&
        $persona1->obtenerApellidoPaterno() === 'Mendoza' &&
        $persona1->obtenerApellidoMaterno() === 'Castillo' &&
        $persona1->obtenerNombreCompleto() === 'Valeria Sofía Mendoza Castillo',
        "Apellidos paterno y materno se almacenan desacoplados sin concatenación destructiva"
    );

    // Caso 8: Distinción estricta NACIONALIDAD ≠ PAÍS DE RESIDENCIA
    // Persona extranjera (Colombiana) que reside legalmente en Perú
    $stmtColombia = $pdo->prepare("SELECT id FROM paises WHERE codigo_iso2 = 'CO' LIMIT 1");
    $stmtColombia->execute();
    $paisColombiaId = (int) $stmtColombia->fetchColumn();

    $persona2 = $personaServicio->crearPersona([
        'nombres' => 'Camilo Andrés',
        'apellido_paterno' => 'Restrepo',
        'apellido_materno' => 'Ospina',
        'genero' => 'MASCULINO',
        'fecha_nacimiento' => '1988-11-04',
        'pais_nacionalidad_id' => $paisColombiaId,
        'pais_residencia_id' => (int) $paisDefault['id'], // Reside en Perú
        'distrito_id' => $distritoMirafloresId,
        'direccion' => 'Calle Schell 120',
    ], [
        'tipo_documento_id' => 3, // Carnet de Extranjería / Pasaporte
        'numero_documento' => 'CE994821',
        'pais_emisor_id' => (int) $paisDefault['id'],
    ]);
    $personasLimpieza[] = (int) $persona2->obtenerId();

    afirmar(
        $persona2->obtenerPaisNacionalidadId() === $paisColombiaId &&
        $persona2->obtenerPaisResidenciaId() === (int) $paisDefault['id'] &&
        $persona2->obtenerPaisNacionalidadId() !== $persona2->obtenerPaisResidenciaId(),
        "Principio NACIONALIDAD ≠ PAÍS DE RESIDENCIA verificado (Colombiano domiciliado en Perú)"
    );

    // Caso 9: Residencia en el extranjero (distrito_id = NULL, región y ciudad textuales libres)
    $stmtUsa = $pdo->prepare("SELECT id FROM paises WHERE codigo_iso2 = 'US' LIMIT 1");
    $stmtUsa->execute();
    $paisUsaId = (int) $stmtUsa->fetchColumn();

    $personaExtranjera = $personaServicio->crearPersona([
        'nombres' => 'Michael John',
        'apellido_paterno' => 'Smith',
        'apellido_materno' => null,
        'genero' => 'MASCULINO',
        'fecha_nacimiento' => '1985-03-12',
        'pais_nacionalidad_id' => $paisUsaId,
        'pais_residencia_id' => $paisColombiaId,
        'distrito_id' => null,
        'region_residencia_extranjera' => 'Antioquia',
        'ciudad_residencia_extranjera' => 'Medellín',
        'direccion' => 'El Poblado Carrera 43A # 1-50',
    ], [
        'tipo_documento_id' => 2, // Pasaporte
        'numero_documento' => 'PASSUSA8841',
        'pais_emisor_id' => $paisUsaId,
    ]);
    $personasLimpieza[] = (int) $personaExtranjera->obtenerId();

    afirmar(
        $personaExtranjera->obtenerDistritoId() === null &&
        $personaExtranjera->obtenerRegionResidenciaExtranjera() === 'Antioquia' &&
        $personaExtranjera->obtenerCiudadResidenciaExtranjera() === 'Medellín',
        "Persona con domicilio en el extranjero: distrito_id nulo con región y ciudad extranjeras válidas"
    );

    // Caso 10: Invariante: Rechazo de género inválido
    $generoRechazado = false;
    try {
        $personaServicio->crearPersona([
            'nombres' => 'Persona',
            'apellido_paterno' => 'Test',
            'genero' => 'INVALIDO_XYZ',
            'pais_residencia_id' => (int) $paisDefault['id'],
            'distrito_id' => $distritoMirafloresId,
        ]);
    } catch (ValidacionExcepcion $e) {
        $generoRechazado = true;
    }
    afirmar($generoRechazado, "Rechazo estricto con ValidacionExcepcion si el género no es válido");

    // Caso 11: Invariante: Rechazo de fecha de nacimiento futura
    $fechaFuturaRechazada = false;
    try {
        $personaServicio->crearPersona([
            'nombres' => 'Viajero',
            'apellido_paterno' => 'Futuro',
            'genero' => 'MASCULINO',
            'fecha_nacimiento' => date('Y-m-d', strtotime('+1 year')),
            'pais_residencia_id' => (int) $paisDefault['id'],
            'distrito_id' => $distritoMirafloresId,
        ]);
    } catch (ValidacionExcepcion $e) {
        $fechaFuturaRechazada = true;
    }
    afirmar($fechaFuturaRechazada, "Rechazo estricto con ValidacionExcepcion si la fecha de nacimiento es futura");

    // Caso 12: Invariante de consistencia territorial Perú:
    // Rechazo si el distrito no coincide con la provincia enviada
    $stmtDepLima = $pdo->prepare("SELECT id FROM departamentos WHERE codigo_ubigeo = '15' LIMIT 1");
    $stmtDepLima->execute();
    $depLimaId = (int) $stmtDepLima->fetchColumn();

    $stmtProvBarranca = $pdo->prepare("SELECT id FROM provincias WHERE codigo_ubigeo = '1502' LIMIT 1");
    $stmtProvBarranca->execute();
    $provBarrancaId = (int) $stmtProvBarranca->fetchColumn();

    $inconsistenciaRechazada = false;
    try {
        $personaServicio->crearPersona([
            'nombres' => 'Error',
            'apellido_paterno' => 'Jerarquia',
            'genero' => 'FEMENINO',
            'pais_residencia_id' => (int) $paisDefault['id'],
            'departamento_id' => $depLimaId,
            'provincia_id' => $provBarrancaId, // Barranca (distrito Miraflores pertenece a Lima prov)
            'distrito_id' => $distritoMirafloresId,
        ]);
    } catch (ValidacionExcepcion $e) {
        $inconsistenciaRechazada = true;
    }
    afirmar($inconsistenciaRechazada, "Rechazo de inconsistencia territorial cuando distrito_id no pertenece a provincia_id");

    // Caso 13: Invariante extranjero: Rechazo si se envía distrito_id peruano con país extranjero
    $extranjeroConDistritoRechazado = false;
    try {
        $personaServicio->crearPersona([
            'nombres' => 'Error',
            'apellido_paterno' => 'Extranjero',
            'genero' => 'MASCULINO',
            'pais_residencia_id' => $paisColombiaId,
            'distrito_id' => $distritoMirafloresId, // Ilegal para país != Perú
        ]);
    } catch (ValidacionExcepcion $e) {
        $extranjeroConDistritoRechazado = true;
    }
    afirmar($extranjeroConDistritoRechazado, "Rechazo si se asigna distrito_id peruano a un país de residencia extranjero");

    // Caso 14: Invariante Perú: Rechazo si se envían textos extranjeros para residente en Perú
    $peruConTextoExtranjeroRechazado = false;
    try {
        $personaServicio->crearPersona([
            'nombres' => 'Error',
            'apellido_paterno' => 'Peruano',
            'genero' => 'FEMENINO',
            'pais_residencia_id' => (int) $paisDefault['id'],
            'distrito_id' => $distritoMirafloresId,
            'region_residencia_extranjera' => 'Texto Ilegal',
        ]);
    } catch (ValidacionExcepcion $e) {
        $peruConTextoExtranjeroRechazado = true;
    }
    afirmar($peruConTextoExtranjeroRechazado, "Rechazo si se envía region_residencia_extranjera cuando país es Perú");

    // -------------------------------------------------------------------------
    echo "\n--- BLOQUE 3: INTEGRACIÓN CON PERSONAL / COLABORADOR ---\n";
    // -------------------------------------------------------------------------

    // Caso 15: Alta de Colaborador vinculando la persona completa
    $colaborador = $colaboradorServicio->crearColaborador([
        'persona_id' => (int) $persona1->obtenerId(),
        'cargo_id' => 1,
        'fecha_inicio' => date('Y-m-d'),
        'observaciones' => 'Alta con persona completa (PERSONAL-1A-C1)'
    ], 1);
    $colaboradoresLimpieza[] = (int) $colaborador->obtenerId();

    afirmar(
        $colaborador->obtenerId() !== null && $colaborador->obtenerPersonaId() === (int) $persona1->obtenerId(),
        "Colaborador registrado exitosamente vinculando la Persona de identidad soberana"
    );

    // Caso 16: Ficha completa consolidada contiene la jerarquía territorial de la Persona
    $ficha = $colaboradorServicio->obtenerFichaCompleta((int) $colaborador->obtenerId());
    afirmar(
        $ficha !== null &&
        isset($ficha['persona']) &&
        $ficha['persona']['genero'] === 'FEMENINO' &&
        $ficha['persona']['distrito'] === 'Miraflores' &&
        $ficha['persona']['provincia'] === 'Lima' &&
        $ficha['persona']['departamento'] === 'Lima' &&
        $ficha['persona']['ubigeo'] === '150122' &&
        $ficha['persona']['es_whatsapp'] === true,
        "Ficha consolidada del colaborador expone género, jerarquía territorial y contacto WhatsApp"
    );

    // Caso 17: Actualización de Persona mediante el servicio
    $personaActualizada = $personaServicio->actualizarPersona((int) $persona1->obtenerId(), [
        'genero' => 'NO_ESPECIFICADO',
        'direccion' => 'Nueva Dirección 789'
    ]);
    afirmar(
        $personaActualizada->obtenerGenero() === 'NO_ESPECIFICADO' &&
        $personaActualizada->obtenerDireccion() === 'Nueva Dirección 789',
        "Actualización exitosa de atributos de Persona a través de PersonaServicio"
    );

    // Caso 18: Ficha refrescada refleja la actualización de la Persona
    $fichaActualizada = $colaboradorServicio->obtenerFichaCompleta((int) $colaborador->obtenerId());
    afirmar(
        $fichaActualizada['persona']['genero'] === 'NO_ESPECIFICADO' &&
        $fichaActualizada['persona']['direccion'] === 'Nueva Dirección 789',
        "Ficha del colaborador refleja reactivamente los cambios en el maestro de Persona"
    );

    // -------------------------------------------------------------------------
    echo "\n--- BLOQUE 4: ENDPOINTS HTTP Y RBAC (E2E) ---\n";
    // -------------------------------------------------------------------------

    // Crear usuario Superadministrador temporal para autenticación E2E
    $sufijo = strtoupper(bin2hex(random_bytes(4)));
    $adminUser = 'admin_geo_' . strtolower($sufijo);
    $passwordPlana = 'PassGeo123!';
    $passwordHash = password_hash($passwordPlana, PASSWORD_DEFAULT);

    $pdo->prepare("INSERT INTO personas (nombres, apellido_paterno, apellido_materno, pais_nacionalidad_id, estado, creado_en) VALUES ('Admin', 'Geo', '{$sufijo}', 1, 'ACTIVO', NOW())")->execute();
    $adminPersonaId = (int) $pdo->lastInsertId();
    $personasLimpieza[] = $adminPersonaId;

    $stmtUAdmin = $pdo->prepare("INSERT INTO usuarios (persona_id, nombre_usuario, contrasena_hash, estado, creado_en) VALUES (?, ?, ?, 'ACTIVO', NOW())");
    $stmtUAdmin->execute([$adminPersonaId, $adminUser, $passwordHash]);
    $adminUsuarioId = (int) $pdo->lastInsertId();
    $usuariosLimpieza[] = $adminUsuarioId;

    $stmtActorAdmin = $pdo->prepare("INSERT INTO actores (codigo, tipo, nombre, usuario_id, estado, creado_en) VALUES (?, 'USUARIO', ?, ?, 'ACTIVO', NOW())");
    $stmtActorAdmin->execute(['USR_' . $adminUsuarioId, 'Admin Geo ' . $sufijo, $adminUsuarioId]);
    $actoresLimpieza[] = (int) $pdo->lastInsertId();

    $superadminRolId = (int) $pdo->query("SELECT id FROM roles WHERE es_superadministrador = 1 OR codigo = 'SUPERADMINISTRADOR' LIMIT 1")->fetchColumn() ?: 1;
    $pdo->exec("INSERT INTO usuarios_roles (usuario_id, rol_id, asignado_en) VALUES ({$adminUsuarioId}, {$superadminRolId}, NOW())");

    $cookieFile = tempnam(sys_get_temp_dir(), 'pms_cookie_');
    $baseUrl = 'https://app.camargo-pms.test';

    // 1. Obtener cookie y CSRF desde login
    $ch = curl_init("{$baseUrl}/login");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
    ]);
    $loginHtml = curl_exec($ch);
    curl_close($ch);

    preg_match('/name=["\']_csrf_token["\']\s+value=["\']([^"\']+)["\']/', (string) $loginHtml, $matches);
    $csrfToken = $matches[1] ?? '';

    // 2. Autenticar vía POST
    $ch = curl_init("{$baseUrl}/login");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'nombre_usuario' => $adminUser,
            'contrasena' => $passwordPlana,
            '_csrf_token' => $csrfToken,
        ]),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
    ]);
    curl_exec($ch);
    curl_close($ch);

    // Caso 19: API GET /api/geografia/pais-default
    $ch = curl_init("{$baseUrl}/api/geografia/pais-default");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_COOKIEFILE => $cookieFile,
    ]);
    $resPaisRaw = (string) curl_exec($ch);
    $resPaisJson = json_decode($resPaisRaw, true);
    $httpCodePais = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    afirmar(
        $httpCodePais === 200 && ($resPaisJson['exito'] ?? false) && ($resPaisJson['datos']['codigo_iso2'] ?? '') === 'PE',
        "Endpoint HTTP GET /api/geografia/pais-default retorna 200 y país Perú (PE)"
    );

    // Caso 20: API GET /api/geografia/departamentos
    $ch = curl_init("{$baseUrl}/api/geografia/departamentos");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_COOKIEFILE => $cookieFile,
    ]);
    $resDepRaw = (string) curl_exec($ch);
    $resDepJson = json_decode($resDepRaw, true);
    $httpCodeDep = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    afirmar(
        $httpCodeDep === 200 && ($resDepJson['exito'] ?? false) && count($resDepJson['datos'] ?? []) === 25,
        "Endpoint HTTP GET /api/geografia/departamentos retorna 200 y los 25 departamentos INEI"
    );

    @unlink($cookieFile);

} catch (Throwable $e) {
    echo "\n[ERROR INESPERADO]: " . $e->getMessage() . "\n";
    if ($e instanceof \CamargoPMS\Excepciones\ValidacionExcepcion) {
        echo "Errores de validación:\n";
        print_r($e->obtenerErrores());
    }
    echo $e->getTraceAsString() . "\n";
} finally {
    // -------------------------------------------------------------------------
    echo "\n--- LIMPIEZA DE FIXTURES TEMPORALES ---\n";
    // -------------------------------------------------------------------------
    try {
        if (!empty($usuariosLimpieza)) {
            $uStr = implode(',', array_map('intval', $usuariosLimpieza));
            $pdo->exec("DELETE FROM sesiones_usuario WHERE usuario_id IN ({$uStr})");
            $pdo->exec("DELETE FROM usuarios_roles WHERE usuario_id IN ({$uStr})");
            $pdo->exec("DELETE a FROM auditoria a INNER JOIN actores ac ON a.actor_id = ac.id WHERE ac.usuario_id IN ({$uStr})");
            $pdo->exec("DELETE FROM actores WHERE usuario_id IN ({$uStr})");
            $pdo->exec("DELETE FROM usuarios WHERE id IN ({$uStr})");
        }
        foreach ($colaboradoresLimpieza as $cid) {
            $pdo->exec("DELETE FROM auditoria WHERE entidad = 'colaboradores' AND entidad_id = {$cid}");
            $pdo->exec("DELETE FROM episodios_laborales_cargos WHERE episodio_laboral_id IN (SELECT id FROM episodios_laborales WHERE colaborador_id = {$cid})");
            $pdo->exec("DELETE FROM episodios_laborales WHERE colaborador_id = {$cid}");
            $pdo->exec("DELETE FROM colaboradores WHERE id = {$cid}");
        }
        foreach ($personasLimpieza as $pid) {
            $pdo->exec("DELETE FROM auditoria WHERE entidad = 'personas' AND entidad_id = {$pid}");
            $pdo->exec("DELETE FROM personas_documentos WHERE persona_id = {$pid}");
            $pdo->exec("DELETE FROM personas_contactos WHERE persona_id = {$pid}");
            $pdo->exec("DELETE FROM personas WHERE id = {$pid}");
        }
        echo "Limpieza completada con éxito. Cero residuos en camargo_pms.\n";
    } catch (Throwable $eClean) {
        echo "Aviso en limpieza: " . $eClean->getMessage() . "\n";
    }
}

echo "\n====================================================================\n";
echo " RESUMEN PERSONAL-1A-C1: {$pruebasPasadas}/{$pruebasTotales} PASADAS (" . round(($pruebasPasadas / max(1, $pruebasTotales)) * 100) . "%)\n";
echo "====================================================================\n";

if ($pruebasPasadas === $pruebasTotales && $pruebasTotales >= 20) {
    echo "RESULTADO: SUITE PERSONAL-1A-C1 100% HOMOLOGADA Y CERTIFICADA\n";
    exit(0);
} else {
    echo "RESULTADO: FALLAS EN SUITE PERSONAL-1A-C1\n";
    exit(1);
}
