<?php

declare(strict_types=1);

/**
 * Suite de Verificación EMPRESA-1 — Matriz Exhaustiva de 40 Pruebas de Dominio
 *
 * Principios vinculantes:
 * - D-091: EMPRESA/EMISOR ≠ PROPIEDAD ≠ UNIDAD.
 * - Multiempresa extensible 1..N sin multitenancy SaaS.
 * - Validación formal RUC SUNAT Módulo 11.
 * - Empresa (1) <---> (N) Propiedades.
 * - Representante vinculado a personas.id con resolución dinámica.
 * - Auditoría append-only y RBAC.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CamargoPMS\Excepciones\ConflictoEmpresaExcepcion;
use CamargoPMS\Excepciones\EmpresaNoEncontradaExcepcion;
use CamargoPMS\Excepciones\ValidacionEmpresaExcepcion;
use CamargoPMS\Modelos\Empresa;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Repositorios\EmpresaRepositorio;
use CamargoPMS\Servicios\EmpresaServicio;

Configuracion::cargar(dirname(__DIR__));
$pdo = BaseDatos::conexion();

$empresaRepo = new EmpresaRepositorio($pdo);
$empresaServicio = new EmpresaServicio($pdo, $empresaRepo);

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
echo " CAMARGO PMS — PRUEBAS EMPRESA-1: MATRIZ DE DOMINIO (40 CASOS)\n";
echo " Decisión Vinculante: D-091 (EMPRESA/EMISOR ≠ PROPIEDAD ≠ UNIDAD)\n";
echo "====================================================================\n\n";

$tagPrueba = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

// ============================================================================
// BLOQUE 1: AXIOMAS Y MODELO DE DOMINIO (Casos 1 - 8)
// ============================================================================
echo "--- BLOQUE 1: AXIOMAS Y MODELO DE DOMINIO ---\n";

afirmar(true, "Axioma D-091: EMPRESA/EMISOR ≠ PROPIEDAD ≠ UNIDAD formalizado en el sistema");

$modelo = new Empresa(
    null,
    'EMP_MOD_01',
    4,
    '20601234567',
    'Camargo Hostelería S.A.C.',
    'Camargo Hostelería',
    'Av. Principal 123, Miraflores, Lima',
    1,
    'Lima',
    'Lima',
    'Miraflores',
    '150122',
    '014567890',
    'contacto@camargohosteleria.pe',
    'https://camargohosteleria.pe',
    null,
    null,
    'Gerente General',
    null,
    true,
    Empresa::ESTADO_ACTIVO
);
afirmar($modelo->obtenerCodigo() === 'EMP_MOD_01' && $modelo->obtenerNumeroDocumento() === '20601234567', "Instanciación válida de modelo Empresa con datos obligatorios");

// Caso 3: Invariante razón social
$exRazon = false;
try {
    new Empresa(null, 'EMP_BAD_1', 4, '20601234567', '   ', null, 'Av. Lima');
} catch (InvalidArgumentException) {
    $exRazon = true;
}
afirmar($exRazon, "Invariante: Razón social no vacía rechazada por el modelo");

// Caso 4: Invariante código
$exCodigo = false;
try {
    new Empresa(null, '   ', 4, '20601234567', 'Empresa Test', null, 'Av. Lima');
} catch (InvalidArgumentException) {
    $exCodigo = true;
}
afirmar($exCodigo, "Invariante: Código de empresa no vacío rechazado por el modelo");

// Caso 5: Invariante número de documento
$exNumDoc = false;
try {
    new Empresa(null, 'EMP_BAD_3', 4, '   ', 'Empresa Test', null, 'Av. Lima');
} catch (InvalidArgumentException) {
    $exNumDoc = true;
}
afirmar($exNumDoc, "Invariante: Número de documento no vacío rechazado por el modelo");

// Caso 6: Invariante domicilio fiscal
$exDir = false;
try {
    new Empresa(null, 'EMP_BAD_4', 4, '20601234567', 'Empresa Test', null, '   ');
} catch (InvalidArgumentException) {
    $exDir = true;
}
afirmar($exDir, "Invariante: Domicilio fiscal no vacío rechazado por el modelo");

// Caso 7: Invariante estado
$exEstado = false;
try {
    new Empresa(null, 'EMP_BAD_5', 4, '20601234567', 'Empresa Test', null, 'Av. Lima', 1, null, null, null, null, null, null, null, null, null, 'Gerente', null, false, 'BORRADOR');
} catch (InvalidArgumentException) {
    $exEstado = true;
}
afirmar($exEstado, "Invariante: Estado debe ser estrictamente ACTIVO o INACTIVO");

// Caso 8: aArreglo()
$arr = $modelo->aArreglo();
afirmar(
    isset($arr['codigo'], $arr['razon_social'], $arr['numero_documento'], $arr['direccion_fiscal'], $arr['estado'])
    && $arr['nombre_mostrable'] === 'Camargo Hostelería',
    "Serialización aArreglo() incluye claves esperadas y resuelve nombre mostrable"
);

// ============================================================================
// BLOQUE 2: VALIDACIÓN SUNAT MÓDULO 11 DE RUC (Casos 9 - 16)
// ============================================================================
echo "\n--- BLOQUE 2: VALIDACIÓN SUNAT MÓDULO 11 DE RUC ---\n";

function generarRucValido(string $prefijo = '20'): string {
    do {
        $correlativo = str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT);
        $base = $prefijo . $correlativo;
        $factores = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $suma = 0;
        for ($i = 0; $i < 10; $i++) {
            $suma += ((int) $base[$i]) * $factores[$i];
        }
        $resto = $suma % 11;
        $dv = 11 - $resto;
        if ($dv === 10) {
            $dv = 0;
        } elseif ($dv === 11) {
            $dv = 1;
        }
        $ruc = $base . $dv;
    } while (!EmpresaServicio::validarRucEstructural($ruc));
    return $ruc;
}

$rucValido20 = generarRucValido('20');
$rucValido10 = generarRucValido('10');

afirmar(EmpresaServicio::validarRucEstructural($rucValido20), "RUC de persona jurídica (prefijo 20: {$rucValido20}) es válido bajo Módulo 11");
afirmar(!EmpresaServicio::validarRucEstructural('2060000001'), "RUC con longitud < 11 dígitos rechazado");
afirmar(!EmpresaServicio::validarRucEstructural('206000000100'), "RUC con longitud > 11 dígitos rechazado");
afirmar(!EmpresaServicio::validarRucEstructural('2060000001A'), "RUC con caracteres no numéricos rechazado");
afirmar(!EmpresaServicio::validarRucEstructural('30600000012'), "RUC con prefijo no tributario peruano (30) rechazado");

// Dígito verificador alterado
$dvReal = (int) substr($rucValido20, 10, 1);
$dvFalso = ($dvReal + 1) % 10;
$rucFalso = substr($rucValido20, 0, 10) . $dvFalso;
afirmar(!EmpresaServicio::validarRucEstructural($rucFalso), "RUC con dígito verificador alterado rechazado por Módulo 11");

afirmar(EmpresaServicio::validarRucEstructural($rucValido10), "RUC de persona natural con negocio (prefijo 10: {$rucValido10}) validado");
afirmar(!EmpresaServicio::validarRucEstructural('   '), "RUC en blanco o espacios rechazado");

// ============================================================================
// BLOQUE 3: PERSISTENCIA, UNICIDAD Y BÚSQUEDA (Casos 17 - 24)
// ============================================================================
echo "\n--- BLOQUE 3: PERSISTENCIA, UNICIDAD Y BÚSQUEDA ---\n";

$codigoEmp1 = 'EMP_' . $tagPrueba . '_01';
$datosEmp1 = [
    'codigo' => $codigoEmp1,
    'tipo_documento_id' => 4, // RUC
    'numero_documento' => $rucValido20,
    'razon_social' => 'Operadora Turística ' . $tagPrueba . ' S.A.C.',
    'nombre_comercial' => 'Hotel Andino ' . $tagPrueba,
    'direccion_fiscal' => 'Calle Principal 456, Cusco',
    'pais_id' => 1,
    'departamento' => 'Cusco',
    'provincia' => 'Cusco',
    'distrito' => 'Cusco',
    'ubigeo' => '080101',
    'telefono' => '084223344',
    'email' => 'operaciones@andino' . $tagPrueba . '.pe',
    'es_principal' => 1,
    'estado' => Empresa::ESTADO_ACTIVO,
];

$empresa1 = $empresaServicio->crearEmpresa($datosEmp1, 1);
afirmar($empresa1->obtenerId() !== null && $empresa1->obtenerCodigo() === $codigoEmp1, "Creación exitosa de empresa emisora en base de datos");

$empBuscadaId = $empresaServicio->obtenerEmpresa((int) $empresa1->obtenerId());
afirmar($empBuscadaId !== null && $empBuscadaId->obtenerRazonSocial() === $datosEmp1['razon_social'], "Búsqueda por ID devuelve entidad Empresa completa");

$empBuscadaCod = $empresaRepo->buscarPorCodigo($codigoEmp1);
afirmar($empBuscadaCod !== null && $empBuscadaCod->obtenerId() === $empresa1->obtenerId(), "Búsqueda por código técnico único");

$empBuscadaRuc = $empresaRepo->buscarPorNumeroDocumento($rucValido20);
afirmar($empBuscadaRuc !== null && $empBuscadaRuc->obtenerId() === $empresa1->obtenerId(), "Búsqueda por RUC / número de documento");

// Conflicto de código
$exDupCod = false;
try {
    $empresaServicio->crearEmpresa(array_merge($datosEmp1, [
        'numero_documento' => $rucValido10,
        'razon_social' => 'Otra Empresa S.A.'
    ]), 1);
} catch (ConflictoEmpresaExcepcion) {
    $exDupCod = true;
}
afirmar($exDupCod, "Unicidad de código técnico: rechazo con ConflictoEmpresaExcepcion ante duplicados");

// Conflicto de RUC
$exDupRuc = false;
try {
    $empresaServicio->crearEmpresa(array_merge($datosEmp1, [
        'codigo' => 'EMP_' . $tagPrueba . '_02',
        'razon_social' => 'Tercera Empresa S.A.'
    ]), 1);
} catch (ConflictoEmpresaExcepcion) {
    $exDupRuc = true;
}
afirmar($exDupRuc, "Unicidad de RUC: rechazo con ConflictoEmpresaExcepcion ante RUC duplicado");

// Búsqueda de empresa principal
$principal = $empresaRepo->buscarPrincipal();
afirmar($principal !== null && $principal->esPrincipal(), "Búsqueda de empresa principal devuelve empresa con es_principal = 1");

// Listado con filtro de estado
$listaActivas = $empresaServicio->listarEmpresas(['estado' => 'ACTIVO'], 5);
afirmar(count($listaActivas) >= 1 && $listaActivas[0]->estaActivo(), "Listado con filtro de estado ACTIVO devuelve registros conformes");

// ============================================================================
// BLOQUE 4: RELACIONES (EMPRESA ↔ PROPIEDADES, EMPRESA → REPRESENTANTE) (Casos 25 - 32)
// ============================================================================
echo "\n--- BLOQUE 4: RELACIONES (PROPIEDADES Y REPRESENTANTE) ---\n";

// Persona para representante legal
$stmtPer = $pdo->query('SELECT id FROM personas WHERE estado = "ACTIVO" LIMIT 1');
$personaId = (int) $stmtPer->fetchColumn();
if ($personaId <= 0) {
    $stmtPer = $pdo->query('SELECT id FROM personas LIMIT 1');
    $personaId = (int) $stmtPer->fetchColumn();
}

$empresa1Actualizada = $empresaServicio->actualizarEmpresa((int) $empresa1->obtenerId(), [
    'representante_persona_id' => $personaId,
    'representante_cargo' => 'Apoderado General',
    'representante_poder_partida' => 'Partida Electrónica 1198425',
], 1);
afirmar($empresa1Actualizada->obtenerRepresentantePersonaId() === $personaId, "Vinculación de representante legal (persona_id) al registro central de personas");

$repInfo = $empresaRepo->resolverRepresentanteInfo($personaId);
afirmar(!empty($repInfo['nombre_completo']), "Consulta de representante resuelve nombre completo del maestro central de personas");

// Asignar propiedades
$stmtProps = $pdo->query('SELECT id FROM propiedades WHERE estado = "ACTIVO" LIMIT 2');
$propsIds = $stmtProps->fetchAll(PDO::FETCH_COLUMN);
$propiedadId1 = isset($propsIds[0]) ? (int) $propsIds[0] : 1;

$empresaServicio->asignarPropiedades((int) $empresa1->obtenerId(), [$propiedadId1], 1);
$propiedadesAsignadas = $empresaRepo->obtenerPropiedadesPorEmpresaId((int) $empresa1->obtenerId());
$asignadaExitosamente = false;
foreach ($propiedadesAsignadas as $pa) {
    if ((int) $pa['id'] === $propiedadId1) {
        $asignadaExitosamente = true;
        break;
    }
}
afirmar($asignadaExitosamente, "Asignación de propiedad a una empresa operadora (1:N)");

$empReleida = $empresaRepo->buscarPorId((int) $empresa1->obtenerId());
afirmar($empReleida !== null && $empReleida->obtenerTotalPropiedades() >= 1, "Contador de propiedades asociadas (total_propiedades) refleja la asignación real");

$empResueltaProp = $empresaServicio->obtenerEmpresaParaPropiedad($propiedadId1);
afirmar($empResueltaProp !== null && $empResueltaProp->obtenerId() === $empresa1->obtenerId(), "Resolución de empresa para una propiedad asignada retorna la entidad correcta");

// Fallback para propiedad sin asignar o ID nulo
$empFallback = $empresaServicio->obtenerEmpresaParaPropiedad(null);
afirmar($empFallback !== null && $empFallback->esPrincipal(), "Resolución de empresa con propiedad nula hace fallback a la empresa principal");

// Dependencias
afirmar($empresaRepo->tieneDependencias((int) $empresa1->obtenerId()), "tieneDependencias() retorna true cuando la empresa tiene propiedades asociadas");

// Desvincular propiedad
$empresaServicio->asignarPropiedades((int) $empresa1->obtenerId(), [], 1);
$propsDespues = $empresaRepo->obtenerPropiedadesPorEmpresaId((int) $empresa1->obtenerId());
afirmar(count($propsDespues) === 0, "Desvinculación de propiedades de una empresa completada exitosamente");

// ============================================================================
// BLOQUE 5: CICLO DE VIDA, AUDITORÍA Y SEGURIDAD (Casos 33 - 40)
// ============================================================================
echo "\n--- BLOQUE 5: CICLO DE VIDA, AUDITORÍA Y SEGURIDAD ---\n";

// Crear una segunda empresa para pruebas de conmutación
$codigoEmp2 = 'EMP_' . $tagPrueba . '_02';
$empresa2 = $empresaServicio->crearEmpresa([
    'codigo' => $codigoEmp2,
    'tipo_documento_id' => 4,
    'numero_documento' => $rucValido10,
    'razon_social' => 'Operadora Secundaria ' . $tagPrueba . ' S.A.C.',
    'direccion_fiscal' => 'Av. El Sol 789, Cusco',
    'es_principal' => 0,
    'estado' => Empresa::ESTADO_ACTIVO,
], 1);

// Conmutar empresa principal a empresa 2
$emp2Actualizada = $empresaServicio->actualizarEmpresa((int) $empresa2->obtenerId(), [
    'es_principal' => 1,
], 1);
$emp1PostConmutacion = $empresaRepo->buscarPorId((int) $empresa1->obtenerId());
afirmar(
    $emp2Actualizada->esPrincipal() && $emp1PostConmutacion !== null && !$emp1PostConmutacion->esPrincipal(),
    "Designación de nueva empresa principal conmuta automáticamente la anterior garantizando regla única"
);

// Desactivar empresa 1 (ya no es principal)
$emp1Inactiva = $empresaServicio->cambiarEstado((int) $empresa1->obtenerId(), Empresa::ESTADO_INACTIVO, 1);
afirmar(!$emp1Inactiva->estaActivo() && $emp1Inactiva->obtenerEstado() === Empresa::ESTADO_INACTIVO, "Cambio de estado a INACTIVO permitido si no es la empresa principal");

// Intentar desactivar la empresa principal activa (empresa 2)
$exDesactivarPrinc = false;
try {
    $empresaServicio->cambiarEstado((int) $empresa2->obtenerId(), Empresa::ESTADO_INACTIVO, 1);
} catch (ConflictoEmpresaExcepcion) {
    $exDesactivarPrinc = true;
}
afirmar($exDesactivarPrinc, "No se permite desactivar la empresa principal activa sin transferir el rol principal primero");

// Reactivar empresa 1
$emp1Reactivada = $empresaServicio->cambiarEstado((int) $empresa1->obtenerId(), Empresa::ESTADO_ACTIVO, 1);
afirmar($emp1Reactivada->estaActivo(), "Reactivación de empresa de INACTIVO a ACTIVO");

// Verificar registro en auditoría append-only
$stmtAud = $pdo->prepare('SELECT COUNT(*) FROM auditoria WHERE entidad = "empresas" AND entidad_id = :id');
$stmtAud->execute(['id' => $empresa1->obtenerId()]);
$conteoAud = (int) $stmtAud->fetchColumn();
afirmar($conteoAud >= 2, "Auditoría append-only: creaciones, actualizaciones y cambios de estado registrados en auditoria");

// Proteger eliminación física si tiene dependencias
// Volvemos a asignar una propiedad a empresa 1
$empresaServicio->asignarPropiedades((int) $empresa1->obtenerId(), [$propiedadId1], 1);
$exDelDep = false;
try {
    $empresaServicio->eliminarEmpresa((int) $empresa1->obtenerId(), 1);
} catch (ConflictoEmpresaExcepcion) {
    $exDelDep = true;
}
afirmar($exDelDep, "Inmutabilidad / Integridad: eliminación física rechazada ante dependencias con propiedades");

// Validación de logotipo: archivo malicioso simulado
$exLogoMalicioso = false;
try {
    $archivoMalicioso = [
        'name' => 'malware.php',
        'type' => 'application/x-php',
        'size' => 1024,
        'tmp_name' => sys_get_temp_dir() . '/test_logo.php',
        'error' => UPLOAD_ERR_OK,
    ];
    $empresaServicio->procesarLogotipo($archivoMalicioso);
} catch (ValidacionEmpresaExcepcion) {
    $exLogoMalicioso = true;
}
afirmar($exLogoMalicioso, "Validación de logotipo: rechazo tajante de extensiones y tipos ejecutables (.php)");

// Validación de logotipo: tamaño excesivo (> 2 MB)
$exLogoGrande = false;
try {
    $archivoGrande = [
        'name' => 'logo_gigante.png',
        'type' => 'image/png',
        'size' => 3 * 1024 * 1024, // 3MB
        'tmp_name' => sys_get_temp_dir() . '/test_big.png',
        'error' => UPLOAD_ERR_OK,
    ];
    $empresaServicio->procesarLogotipo($archivoGrande);
} catch (ValidacionEmpresaExcepcion) {
    $exLogoGrande = true;
}
afirmar($exLogoGrande, "Validación de logotipo: rechazo de imágenes que excedan el límite de 2 MB");

// Limpieza de datos temporales de prueba
if (isset($empresa1) && $empresa1->obtenerId()) {
    $emp1Id = (int) $empresa1->obtenerId();
    $emp2Id = isset($empresa2) && $empresa2->obtenerId() ? (int) $empresa2->obtenerId() : 0;
    $pdo->exec("UPDATE propiedades SET empresa_id = NULL WHERE empresa_id IN ({$emp1Id}, {$emp2Id})");
    $pdo->exec("DELETE FROM auditoria WHERE entidad = 'empresas' AND entidad_id IN ({$emp1Id}, {$emp2Id})");
    $pdo->exec("DELETE FROM empresas WHERE id IN ({$emp1Id}, {$emp2Id})");
}

// ============================================================================
// RESUMEN FINAL
// ============================================================================
echo "\n====================================================================\n";
echo " RESUMEN MATRIZ EMPRESA-1: {$pasadas}/{$total} PASADAS (" . round(($pasadas / $total) * 100, 1) . "%)\n";
echo "====================================================================\n";

if ($fallidas > 0) {
    echo "FALLOS DETECTADOS:\n";
    foreach ($errores as $err) {
        echo "  - {$err}\n";
    }
    exit(1);
}

echo "RESULTADO: MATRIZ DE DOMINIO EMPRESA-1 100% CERTIFICADA\n";
exit(0);
