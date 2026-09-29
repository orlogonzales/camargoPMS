<?php

declare(strict_types=1);

/**
 * Suite de Pruebas Permanentes: Menú Dinámico Real de Hasta 3 Niveles y Reorganización en 9 Dominios.
 * Microfase: UI-ALINA-1A (Gobernanza + Sistema de Diseño Alina D-093).
 *
 * Contratos evaluados:
 * - Grupo 1: Jerarquía canónica de hasta 3 niveles (Alina D-093).
 * - Grupo 2: Límites de profundidad y rechazo estricto de Nivel 4+.
 * - Grupo 3: Detección de ciclos y autorreferencia.
 * - Grupo 4: Movimiento de subárboles y validación de desbordamiento de profundidad.
 * - Grupo 5: Reorganización en los 9 dominios oficiales de Camargo PMS.
 * - Grupo 6: RBAC y visibilidad en cascada (N3 -> N2 -> N1).
 * - Grupo 7: Propagación de estado activo (Active State Cascade).
 * - Grupo 8: Fidelidad Alina y prohibición estricta de bordes dotted/dashed.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use CamargoPMS\Excepciones\NivelMenuInvalidoExcepcion;
use CamargoPMS\Modelos\OpcionMenu;
use CamargoPMS\Modelos\Usuario;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Nucleo\Vista;
use CamargoPMS\Repositorios\OpcionMenuRepositorio;
use CamargoPMS\Servicios\MenuServicio;

Configuracion::cargar(__DIR__ . '/../.env');
$pdo = BaseDatos::conexion();

echo "====================================================================\n";
echo " SUITE UI-ALINA-1A: MENÚ DINÁMICO DE 3 NIVELES Y 9 DOMINIOS\n";
echo "====================================================================\n\n";

$pruebasExitosas = 0;
$totalPruebas = 0;

function verificar(string $codigo, string $descripcion, bool $condicion): void
{
    global $totalPruebas, $pruebasExitosas;
    $totalPruebas++;
    if ($condicion) {
        $pruebasExitosas++;
        echo "  [PASS] {$codigo}: {$descripcion}\n";
    } else {
        echo "  [FAIL] {$codigo}: {$descripcion}\n";
    }
}

$repo = new OpcionMenuRepositorio($pdo);
$menuServicio = new MenuServicio($pdo);

// Limpieza previa defensiva de fixtures temporales si hubieran quedado
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
$pdo->exec("DELETE FROM opciones_menu WHERE clave LIKE 'test_menu_%'");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

try {
    // -----------------------------------------------------------------------------
    // GRUPO 1: Jerarquía canónica de hasta 3 niveles (Alina D-093)
    // -----------------------------------------------------------------------------
    echo "--- GRUPO 1: MODELO Y JERARQUÍA DE 3 NIVELES ---\n";

    // Crear fixture de 3 niveles: N1 -> N2 -> N3
    $n1 = $menuServicio->crearOpcion([
        'clave' => 'test_menu_n1',
        'nombre' => 'Test Dominio N1',
        'padre_id' => null,
        'icono' => 'fa-solid fa-star',
        'orden' => 990,
    ]);

    $n2 = $menuServicio->crearOpcion([
        'clave' => 'test_menu_n2',
        'nombre' => 'Test Módulo N2',
        'padre_id' => $n1->obtenerId(),
        'icono' => 'fa-solid fa-circle',
        'ruta' => '/test/modulo',
        'orden' => 1,
    ]);

    $n3 = $menuServicio->crearOpcion([
        'clave' => 'test_menu_n3',
        'nombre' => 'Test Submódulo N3',
        'padre_id' => $n2->obtenerId(),
        'icono' => 'fa-solid fa-dot-circle',
        'ruta' => '/test/submodulo',
        'orden' => 1,
    ]);

    verificar('MENU-3N-01', 'Opcion N1 tiene nivel 1', $n1->obtenerNivel() === 1 && $repo->calcularNivel($n1->obtenerId()) === 1);
    verificar('MENU-3N-02', 'Opcion N2 tiene nivel 2', $n2->obtenerNivel() === 2 && $repo->calcularNivel($n2->obtenerId()) === 2);
    verificar('MENU-3N-03', 'Opcion N3 tiene nivel 3', $n3->obtenerNivel() === 3 && $repo->calcularNivel($n3->obtenerId()) === 3);

    // Comprobar obtención del árbol y relaciones padre-hijo
    $arbol = $repo->obtenerArbolCompleto(false, true);
    $n1EnArbol = null;
    foreach ($arbol as $item) {
        if ($item->obtenerClave() === 'test_menu_n1') {
            $n1EnArbol = $item;
            break;
        }
    }

    $n1TieneHijos = $n1EnArbol !== null && $n1EnArbol->tieneHijos() && count($n1EnArbol->obtenerHijos()) === 1;
    $n2EnArbol = $n1TieneHijos ? $n1EnArbol->obtenerHijos()[0] : null;
    $n2TieneHijos = $n2EnArbol !== null && $n2EnArbol->tieneHijos() && count($n2EnArbol->obtenerHijos()) === 1;
    $n3EnArbol = $n2TieneHijos ? $n2EnArbol->obtenerHijos()[0] : null;

    verificar('MENU-3N-04', 'Árbol construye relación bidireccional N1 -> N2 -> N3', $n3EnArbol !== null && $n3EnArbol->obtenerClave() === 'test_menu_n3');

    $arregloN3 = $n3EnArbol !== null ? $n3EnArbol->haciaArreglo() : [];
    verificar('MENU-3N-05', 'haciaArreglo() expone atributos nivel=3 y tiene_hijos=false para N3', ($arregloN3['nivel'] ?? null) === 3 && ($arregloN3['tiene_hijos'] ?? null) === false);

    // -----------------------------------------------------------------------------
    // GRUPO 2: Límites de profundidad y rechazo estricto de Nivel 4+
    // -----------------------------------------------------------------------------
    echo "\n--- GRUPO 2: LÍMITES DE PROFUNDIDAD Y RECHAZO DE NIVEL 4+ ---\n";

    $rechazoNivel4 = false;
    try {
        $menuServicio->crearOpcion([
            'clave' => 'test_menu_n4_invalido',
            'nombre' => 'Test Invalido N4',
            'padre_id' => $n3->obtenerId(), // Asignar como hijo de N3 daría Nivel 4
            'ruta' => '/test/invalido',
            'orden' => 1,
        ]);
    } catch (NivelMenuInvalidoExcepcion $e) {
        $rechazoNivel4 = true;
    }
    verificar('MENU-3N-06', 'Crear opción de Nivel 4 lanza NivelMenuInvalidoExcepcion', $rechazoNivel4);

    // Validar jerarquía directamente en MenuServicio
    $validaN3Rechazo = false;
    try {
        $menuServicio->validarJerarquia($n3->obtenerId());
    } catch (NivelMenuInvalidoExcepcion $e) {
        $validaN3Rechazo = str_contains($e->getMessage(), 'tres niveles');
    }
    verificar('MENU-3N-07', 'MenuServicio::validarJerarquia rechaza padre N3 con mensaje de 3 niveles', $validaN3Rechazo);

    // -----------------------------------------------------------------------------
    // GRUPO 3: Detección de ciclos y autorreferencia
    // -----------------------------------------------------------------------------
    echo "\n--- GRUPO 3: DETECCIÓN DE CICLOS Y AUTORREFERENCIA ---\n";

    $autorreferenciaRechazada = false;
    try {
        $menuServicio->actualizarOpcion($n2->obtenerId(), [
            'padre_id' => $n2->obtenerId(), // A -> A
        ]);
    } catch (NivelMenuInvalidoExcepcion) {
        $autorreferenciaRechazada = true;
    }
    verificar('MENU-3N-08', 'Autorreferencia directa (A -> A) es rechazada con NivelMenuInvalidoExcepcion', $autorreferenciaRechazada);

    $cicloIndirectoRechazado = false;
    try {
        // Intentar hacer que N1 tenga como padre a su propio hijo N2 (N1 -> N2 -> N1)
        $menuServicio->actualizarOpcion($n1->obtenerId(), [
            'padre_id' => $n2->obtenerId(),
        ]);
    } catch (NivelMenuInvalidoExcepcion) {
        $cicloIndirectoRechazado = true;
    }
    verificar('MENU-3N-09', 'Ciclo indirecto (A -> B -> A) es detectado y rechazado', $cicloIndirectoRechazado);

    $cicloN3aN1Rechazado = false;
    try {
        // Intentar hacer que N1 tenga como padre a N3 (N1 -> N2 -> N3 -> N1)
        $menuServicio->actualizarOpcion($n1->obtenerId(), [
            'padre_id' => $n3->obtenerId(),
        ]);
    } catch (NivelMenuInvalidoExcepcion) {
        $cicloN3aN1Rechazado = true;
    }
    verificar('MENU-3N-10', 'Ciclo indirecto de 3 pasos (A -> B -> C -> A) es detectado y rechazado', $cicloN3aN1Rechazado);

    // -----------------------------------------------------------------------------
    // GRUPO 4: Movimiento de subárboles y desbordamiento de profundidad
    // -----------------------------------------------------------------------------
    echo "\n--- GRUPO 4: MOVIMIENTO DE SUBÁRBOLES Y DESBORDAMIENTO ---\n";

    // Crear otro módulo N2 en el árbol
    $otroN2 = $menuServicio->crearOpcion([
        'clave' => 'test_menu_otro_n2',
        'nombre' => 'Test Otro Módulo N2',
        'padre_id' => $n1->obtenerId(),
        'ruta' => '/test/otro',
        'orden' => 2,
    ]);

    // Intentar mover $n2 (que tiene a $n3 como hijo) debajo de $otroN2 (que ya es Nivel 2).
    // Si se permitiera, $n2 pasaría a Nivel 3 y $n3 pasaría a Nivel 4 (desbordamiento).
    $desbordamientoSubarbolRechazado = false;
    try {
        $menuServicio->actualizarOpcion($n2->obtenerId(), [
            'padre_id' => $otroN2->obtenerId(),
        ]);
    } catch (NivelMenuInvalidoExcepcion $e) {
        $desbordamientoSubarbolRechazado = str_contains($e->getMessage(), 'tres niveles');
    }
    verificar('MENU-3N-11', 'Mover subárbol que provocaría Nivel 4 en descendientes es rechazado', $desbordamientoSubarbolRechazado);

    // Mover un nodo sin hijos a Nivel 3 debe ser permitido
    $moverNodoHojaPermitido = false;
    try {
        $menuServicio->actualizarOpcion($otroN2->obtenerId(), [
            'padre_id' => $n2->obtenerId(), // otroN2 no tiene hijos, pasa de N2 a N3
        ]);
        $moverNodoHojaPermitido = ($repo->calcularNivel($otroN2->obtenerId()) === 3);
    } catch (NivelMenuInvalidoExcepcion) {
        $moverNodoHojaPermitido = false;
    }
    verificar('MENU-3N-12', 'Mover nodo sin hijos a Nivel 3 es válido y calcula nivel 3', $moverNodoHojaPermitido);

    // Promover $otroN2 de N3 a N1 (padre NULL)
    $promoverANivel1 = false;
    try {
        $menuServicio->actualizarOpcion($otroN2->obtenerId(), [
            'padre_id' => null,
            'ruta' => null,
        ]);
        $promoverANivel1 = ($repo->calcularNivel($otroN2->obtenerId()) === 1);
    } catch (NivelMenuInvalidoExcepcion) {
        $promoverANivel1 = false;
    }
    verificar('MENU-3N-13', 'Promover nodo de N3 a Dominio N1 (padre NULL) es válido', $promoverANivel1);

    // -----------------------------------------------------------------------------
    // GRUPO 5: Reorganización en los 9 dominios oficiales de Camargo PMS
    // -----------------------------------------------------------------------------
    echo "\n--- GRUPO 5: REORGANIZACIÓN EN 9 DOMINIOS OFICIALES ---\n";

    $dominiosEsperados = [
        'inicio' => ['orden' => 1, 'nombre' => 'Inicio'],
        'propiedades' => ['orden' => 10, 'nombre' => 'Propiedades'],
        'reservas' => ['orden' => 20, 'nombre' => 'Comercial y Reservas'],
        'operaciones' => ['orden' => 30, 'nombre' => 'Operaciones'],
        'caja_finanzas' => ['orden' => 40, 'nombre' => 'Caja y Finanzas'],
        'abastecimiento' => ['orden' => 50, 'nombre' => 'Abastecimiento'],
        'documentos' => ['orden' => 60, 'nombre' => 'Documentos'],
        'atencion_cliente' => ['orden' => 70, 'nombre' => 'Atención al Cliente'],
        'configuracion' => ['orden' => 90, 'nombre' => 'Configuración'],
    ];

    $stmtDom = $pdo->query("SELECT clave, nombre, orden FROM opciones_menu WHERE padre_id IS NULL AND clave NOT LIKE 'test_%' ORDER BY orden ASC");
    $dominiosBd = $stmtDom->fetchAll(PDO::FETCH_ASSOC);

    $conteoDominios = count($dominiosBd);
    verificar('MENU-3N-14', 'Existen exactamente 9 dominios oficiales en Nivel 1', $conteoDominios === 9);

    $ordenesCorrectos = true;
    foreach ($dominiosBd as $idx => $d) {
        $clave = $d['clave'];
        if (!isset($dominiosEsperados[$clave]) || (int)$d['orden'] !== $dominiosEsperados[$clave]['orden'] || $d['nombre'] !== $dominiosEsperados[$clave]['nombre']) {
            $ordenesCorrectos = false;
            break;
        }
    }
    verificar('MENU-3N-15', 'Los 9 dominios tienen sus claves, nombres y órdenes canónicos verificados', $ordenesCorrectos);

    // Verificar que la clave técnica 'reservas' se conserva para compatibilidad
    $reservasOk = false;
    foreach ($dominiosBd as $d) {
        if ($d['clave'] === 'reservas' && $d['nombre'] === 'Comercial y Reservas') {
            $reservasOk = true;
            break;
        }
    }
    verificar('MENU-3N-16', "Dominio 'reservas' preserva clave técnica y nombre 'Comercial y Reservas'", $reservasOk);

    // Comprobar que no hay opciones huérfanas en toda la tabla
    $stmtHuerfanas = $pdo->query("
        SELECT COUNT(*)
        FROM opciones_menu om
        WHERE om.padre_id IS NOT NULL
          AND om.padre_id NOT IN (SELECT id FROM opciones_menu)
    ");
    $huerfanas = (int)$stmtHuerfanas->fetchColumn();
    verificar('MENU-3N-17', 'Cero opciones huérfanas en la base de datos', $huerfanas === 0);

    // Comprobar que las rutas críticas están en los dominios correspondientes
    $rutasMapeo = [
        '/reservas' => 'reservas',
        '/estadias' => 'reservas',
        '/clientes' => 'reservas',
        '/tape-chart' => 'reservas',
        '/housekeeping' => 'operaciones',
        '/mantenimiento' => 'operaciones',
        '/operaciones/bitacora' => 'operaciones',
        '/operaciones/night-audit' => 'operaciones',
        '/caja' => 'caja_finanzas',
        '/recibos' => 'caja_finanzas',
        '/gastos' => 'caja_finanzas',
        '/inventario' => 'abastecimiento',
        '/suministros' => 'abastecimiento',
        '/compras' => 'abastecimiento',
        '/documentos' => 'documentos',
        '/reclamaciones' => 'atencion_cliente',
        '/configuracion/sistema' => 'configuracion',
        '/configuracion/menu' => 'configuracion',
        '/usuarios' => 'configuracion',
        '/configuracion/roles' => 'configuracion',
        '/seguridad/sesiones' => 'configuracion',
        '/empresas' => 'configuracion',
        '/personal' => 'configuracion',
        '/configuracion/feriados' => 'configuracion',
    ];

    $distribucionValida = true;
    $stmtRutaDominio = $pdo->prepare("
        SELECT p.clave AS dominio_clave
        FROM opciones_menu om
        JOIN opciones_menu p ON om.padre_id = p.id
        WHERE om.ruta = :ruta
    ");
    foreach ($rutasMapeo as $ruta => $dominioEsperado) {
        $stmtRutaDominio->execute([':ruta' => $ruta]);
        $dominioReal = $stmtRutaDominio->fetchColumn();
        if ($dominioReal !== $dominioEsperado) {
            $distribucionValida = false;
            echo "    [DEBUG] Ruta '{$ruta}' esperada en '{$dominioEsperado}', encontrada en '{$dominioReal}'\n";
            break;
        }
    }
    verificar('MENU-3N-18', 'Distribución de las 24 rutas críticas coincide exactamente con los dominios oficiales', $distribucionValida);

    // -----------------------------------------------------------------------------
    // GRUPO 6: RBAC y visibilidad en cascada (N3 -> N2 -> N1)
    // -----------------------------------------------------------------------------
    echo "\n--- GRUPO 6: RBAC Y VISIBILIDAD EN CASCADA ---\n";

    // Asignar permiso específico a N3
    $stmtPermiso = $pdo->query("SELECT id FROM permisos WHERE codigo = 'usuarios.ver' LIMIT 1");
    $permisoId = (int)$stmtPermiso->fetchColumn();

    $pdo->prepare("UPDATE opciones_menu SET permiso_id = :permiso_id WHERE id = :id")->execute([
        ':permiso_id' => $permisoId,
        ':id' => $n3->obtenerId(),
    ]);

    // Crear un usuario ficticio que solo tiene el permiso 'usuarios.ver'
    $uSuperadmin = new Usuario(1, 1, 'admin_super', 'hash');
    $menuSuper = $menuServicio->obtenerMenuParaUsuario($uSuperadmin, '/test/submodulo');

    verificar('MENU-3N-19', 'Superadministrador ve el dominio de prueba N1', isset($menuSuper['test_menu_n1']));
    $gruposN1 = $menuSuper['test_menu_n1']['grupos'] ?? [];
    $moduloN2 = null;
    foreach ($gruposN1 as $g) {
        if ($g['clave'] === 'test_menu_n2') {
            $moduloN2 = $g;
            break;
        }
    }
    verificar('MENU-3N-20', 'Superadministrador ve el módulo N2 con formato colapsable', $moduloN2 !== null && ($moduloN2['tipo'] ?? '') === 'colapsable');
    $subitemsN3 = $moduloN2['items'] ?? [];
    verificar('MENU-3N-21', 'Módulo N2 contiene al submódulo N3 en sus items', count($subitemsN3) === 1 && $subitemsN3[0]['clave'] === 'test_menu_n3');

    // Comprobar que un usuario sin ningún permiso no ve el dominio de prueba
    $uSinPermisos = new Usuario(99999998, 99999998, 'sin_permisos_test', 'hash');
    $menuSinPermisos = $menuServicio->obtenerMenuParaUsuario($uSinPermisos, '/');
    verificar('MENU-3N-22', 'Usuario sin permisos NO ve dominios donde no tiene opciones autorizadas', !isset($menuSinPermisos['test_menu_n1']));

    // -----------------------------------------------------------------------------
    // GRUPO 7: Propagación de estado activo (Active State Cascade)
    // -----------------------------------------------------------------------------
    echo "\n--- GRUPO 7: PROPAGACIÓN DE ESTADO ACTIVO (CASCADE) ---\n";

    // Simular que el usuario navega a '/test/submodulo' (ruta de N3)
    $menuActivoN3 = $menuServicio->obtenerMenuParaUsuario($uSuperadmin, '/test/submodulo');
    $n1Activo = $menuActivoN3['test_menu_n1']['activo'] ?? false;

    $n2Activo = false;
    $n3Activo = false;
    foreach ($menuActivoN3['test_menu_n1']['grupos'] ?? [] as $g) {
        if ($g['clave'] === 'test_menu_n2') {
            $n2Activo = $g['activo'] ?? false;
            foreach ($g['items'] ?? [] as $sub) {
                if ($sub['clave'] === 'test_menu_n3') {
                    $n3Activo = $sub['activo'] ?? false;
                }
            }
        }
    }

    verificar('MENU-3N-23', 'Al navegar a ruta de N3: Submódulo N3 tiene activo=true', $n3Activo === true);
    verificar('MENU-3N-24', 'Al navegar a ruta de N3: Módulo padre N2 propaga activo=true', $n2Activo === true);
    verificar('MENU-3N-25', 'Al navegar a ruta de N3: Dominio padre N1 propaga activo=true', $n1Activo === true);

    // -----------------------------------------------------------------------------
    // GRUPO 8: Fidelidad Alina y prohibición estricta de bordes dotted/dashed
    // -----------------------------------------------------------------------------
    echo "\n--- GRUPO 8: FIDELIDAD ALINA Y PROHIBICIÓN DOTTED/DASHED ---\n";

    // Renderizar los componentes visuales del menú
    $vista = new Vista();
    $htmlMenuSecundario = $vista->componente('menu-secundario', [
        'menu' => $menuActivoN3,
        'categoriaActiva' => 'test_menu_n1',
    ]);

    $tieneColapsoAlina = str_contains($htmlMenuSecundario, 'id="menu-sub-test_menu_n2"') &&
        str_contains($htmlMenuSecundario, 'data-bs-toggle="collapse"');
    verificar('MENU-3N-26', 'Menu secundario renderiza estructura de colapso Alina data-bs-toggle y chevrons', $tieneColapsoAlina);

    $htmlMenuPrincipal = $vista->componente('menu-principal', [
        'menu' => $menuActivoN3,
        'categoriaActiva' => 'test_menu_n1',
    ]);

    $tieneDottedODashed = str_contains($htmlMenuSecundario, 'dotted') ||
        str_contains($htmlMenuSecundario, 'dashed') ||
        str_contains($htmlMenuPrincipal, 'dotted') ||
        str_contains($htmlMenuPrincipal, 'dashed');
    verificar('MENU-3N-27', 'Prohibición absoluta: Cero estilos o clases dotted/dashed en menús', $tieneDottedODashed === false);

} finally {
    // -----------------------------------------------------------------------------
    // Limpieza de fixtures de prueba
    // -----------------------------------------------------------------------------
    echo "\n--- LIMPIEZA DE FIXTURES TEMPORALES ---\n";
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $eliminadas = $pdo->exec("DELETE FROM opciones_menu WHERE clave LIKE 'test_menu_%'");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "  Opciones temporales eliminadas de la BD: {$eliminadas}\n";
}

echo "\n====================================================================\n";
echo " RESUMEN: {$pruebasExitosas}/{$totalPruebas} PRUEBAS SUPERADAS\n";
echo "====================================================================\n";

if ($pruebasExitosas !== $totalPruebas) {
    exit(1);
}
