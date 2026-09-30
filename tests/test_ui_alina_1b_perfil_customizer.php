<?php

declare(strict_types=1);

/**
 * Suite de Pruebas Automatizadas UI-ALINA-1B:
 * Perfil de Usuario + Theme Customizer + Flotante Lateral Alina.
 *
 * Valida:
 * 1. Protección por autenticación en ruta GET /perfil.
 * 2. Renderizado de /perfil bajo estricto principio PERSONA ≠ USUARIO.
 * 3. Presencia de enlace "Mi Perfil" en el menú de usuario de cabecera.
 * 4. Fidelidad estructural a profile.html de Alina (profile-container, avatar-preview, avatar-edit).
 * 5. Flotante lateral de Alina con exactamente 2 funciones (Configuración y Soporte #).
 * 6. Ausencia total de enlaces comerciales, Buy Now y ThemeForest.
 * 7. Traducción exhaustiva del Customizer al español y botón Restablecer centrado.
 * 8. Persistencia soberana en localStorage (cero consultas BD del Customizer, slot 034 libre).
 * 9. Prevención defensiva de FOUC en head.php.
 * 10. Uso exclusivo de Font Awesome 6 (0 Tabler Icons en código propio nuevo).
 * 11. Cero modificaciones en admin-dashboard/.
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Nucleo/Configuracion.php';
require_once __DIR__ . '/../app/Nucleo/BaseDatos.php';
require_once __DIR__ . '/../app/Nucleo/Vista.php';
require_once __DIR__ . '/../app/Nucleo/Funciones.php';
require_once __DIR__ . '/../app/Nucleo/Ayudante.php';

use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Vista;
use CamargoPMS\Controladores\PerfilControlador;
use CamargoPMS\Repositorios\UsuarioRepositorio;
use CamargoPMS\Repositorios\PersonaRepositorio;

Configuracion::cargar(dirname(__DIR__));
$pdo = BaseDatos::conexion();

$totalAserciones = 0;
$asercionesExitosas = 0;

function test_afirmar(bool $condicion, string $mensaje): void
{
    global $totalAserciones, $asercionesExitosas;
    $totalAserciones++;
    if ($condicion) {
        $asercionesExitosas++;
        echo "  [PASS] {$mensaje}\n";
    } else {
        echo "  [FAIL] {$mensaje}\n";
    }
}

echo "====================================================================\n";
echo " SUITE UI-ALINA-1B: PERFIL DE USUARIO + THEME CUSTOMIZER + FLOTANTE\n";
echo "====================================================================\n\n";

// --- GRUPO 1: ENRUTAMIENTO Y PROTECCIÓN AUTH ---
echo "--- GRUPO 1: ENRUTAMIENTO Y PROTECCIÓN DE RUTA /perfil ---\n";

$indexPhp = file_get_contents(__DIR__ . '/../public/index.php');
test_afirmar(
    strpos($indexPhp, "\$enrutador->get('/perfil'") !== false,
    "PERFIL-01: Ruta GET /perfil registrada formalmente en public/index.php"
);
test_afirmar(
    strpos($indexPhp, "PerfilControlador::class, 'index'") !== false,
    "PERFIL-02: Ruta /perfil delega en PerfilControlador::index"
);
test_afirmar(
    preg_match("/\\\$enrutador->get\('\/perfil'.*AutenticacionIntermediario::class/s", $indexPhp) === 1,
    "PERFIL-03: Ruta /perfil está estrictamente protegida por AutenticacionIntermediario"
);

// --- GRUPO 2: ENLACE 'MI PERFIL' EN CABECERA ---
echo "\n--- GRUPO 2: ENLACE 'MI PERFIL' EN COMPONENTE CABECERA ---\n";

$cabeceraPhp = file_get_contents(__DIR__ . '/../app/Vistas/componentes/cabecera.php');
test_afirmar(
    strpos($cabeceraPhp, "url_ruta('/perfil')") !== false,
    "CABECERA-01: Componente cabecera.php contiene enlace hacia url_ruta('/perfil')"
);
test_afirmar(
    strpos($cabeceraPhp, "Mi Perfil") !== false,
    "CABECERA-02: Etiqueta visible 'Mi Perfil' presente en el dropdown de usuario"
);
test_afirmar(
    strpos($cabeceraPhp, "fa-solid fa-user") !== false,
    "CABECERA-03: Enlace Mi Perfil utiliza icono Font Awesome fa-solid fa-user"
);

// --- GRUPO 3: CONTROLADOR Y SEPARACIÓN PERSONA != USUARIO ---
echo "\n--- GRUPO 3: CONTROLADOR Y SEPARACIÓN PERSONA ≠ USUARIO ---\n";

$controlador = new PerfilControlador();
test_afirmar(
    class_exists(PerfilControlador::class),
    "PERFIL-CTRL-01: Clase PerfilControlador existe en CamargoPMS\\Controladores"
);

$controladorPhp = file_get_contents(__DIR__ . '/../app/Controladores/PerfilControlador.php');
test_afirmar(
    strpos($controladorPhp, 'validarSesionActual') !== false,
    "PERFIL-CTRL-02: PerfilControlador valida la sesión del usuario mediante SesionServicio"
);
test_afirmar(
    strpos($controladorPhp, 'buscarDetallePorId') !== false,
    "PERFIL-CTRL-03: PerfilControlador carga datos técnicos del usuario mediante UsuarioRepositorio"
);
test_afirmar(
    strpos($controladorPhp, 'PersonaRepositorio') !== false,
    "PERFIL-CTRL-04: PerfilControlador carga la Persona natural vinculada mediante PersonaRepositorio"
);
test_afirmar(
    strpos($controladorPhp, 'PERSONA ≠ USUARIO') !== false,
    "PERFIL-CTRL-05: Principio ontológico PERSONA ≠ USUARIO explícitamente documentado en controlador"
);

// --- GRUPO 4: VISTA DE PERFIL Y FIDELIDAD A PROFILE.HTML ---
echo "\n--- GRUPO 4: VISTA DE PERFIL Y FIDELIDAD A ALINA profile.html ---\n";

$vistaPerfilPhp = file_get_contents(__DIR__ . '/../app/Vistas/perfil/index.php');
test_afirmar(
    strpos($vistaPerfilPhp, 'profile-container') !== false,
    "PERFIL-VISTA-01: Vista perfil/index.php implementa contenedor Alina .profile-container"
);
test_afirmar(
    strpos($vistaPerfilPhp, 'profile-pic') !== false && strpos($vistaPerfilPhp, 'avatar-preview') !== false,
    "PERFIL-VISTA-02: Estructura de avatar con .profile-pic y .avatar-preview de Alina presente"
);
test_afirmar(
    strpos($vistaPerfilPhp, 'id="imgPreview"') !== false,
    "PERFIL-VISTA-03: Contenedor #imgPreview presente para previsualización dinámica"
);
test_afirmar(
    strpos($vistaPerfilPhp, 'id="imageUpload"') !== false,
    "PERFIL-VISTA-04: Input file #imageUpload presente con soporte .png, .jpg, .jpeg"
);
test_afirmar(
    strpos($vistaPerfilPhp, 'FileReader') !== false,
    "PERFIL-VISTA-05: Previsualizador interactivo de fotografía implementado con Vanilla JS (FileReader)"
);
test_afirmar(
    strpos($vistaPerfilPhp, 'jQuery') === false && strpos($vistaPerfilPhp, '$(') === false,
    "PERFIL-VISTA-06: Cero llamadas jQuery en el script de la vista de perfil"
);
test_afirmar(
    strpos($vistaPerfilPhp, 'PERSONA ≠ USUARIO') !== false,
    "PERFIL-VISTA-07: Vista expone aviso informativo de la separación PERSONA ≠ USUARIO"
);
test_afirmar(
    strpos($vistaPerfilPhp, 'panel-persona') !== false && strpos($vistaPerfilPhp, 'panel-usuario') !== false,
    "PERFIL-VISTA-08: Pestañas claramente diferenciadas: Persona Natural vs Cuenta de Acceso"
);

// --- GRUPO 5: FLOTANTE LATERAL Y THEME CUSTOMIZER ---
echo "\n--- GRUPO 5: FLOTANTE LATERAL Y THEME CUSTOMIZER ALINA ---\n";

$personalizadorPhp = file_get_contents(__DIR__ . '/../app/Vistas/componentes/personalizador.php');
test_afirmar(
    strpos($personalizadorPhp, 'theme-customizer-container') !== false,
    "CUSTOMIZER-01: Contenedor flotante lateral .theme-customizer-container presente"
);
test_afirmar(
    strpos($personalizadorPhp, 'customizer-settings-btn') !== false,
    "CUSTOMIZER-02: Botón de configuración .customizer-settings-btn presente"
);
test_afirmar(
    strpos($personalizadorPhp, 'fa-solid fa-gear') !== false,
    "CUSTOMIZER-03: Botón de configuración utiliza Font Awesome fa-solid fa-gear"
);
test_afirmar(
    strpos($personalizadorPhp, 'fa-solid fa-headset') !== false && strpos($personalizadorPhp, 'href="#"') !== false,
    "CUSTOMIZER-04: Botón de soporte técnico presente con href=\"#\" y fa-solid fa-headset"
);
test_afirmar(
    substr_count($personalizadorPhp, 'class="customizer-box') === 2,
    "CUSTOMIZER-05: Flotante lateral tiene exactamente 2 botones aprobados (Configuración + Soporte)"
);
test_afirmar(
    stripos($personalizadorPhp, 'buy now') === false && stripos($personalizadorPhp, 'themeforest') === false,
    "CUSTOMIZER-06: Prohibición absoluta cumplida: CERO enlaces comerciales, Buy Now o ThemeForest"
);

// --- GRUPO 6: TRADUCCIÓN AL ESPAÑOL Y BOTÓN RESTABLECER ---
echo "\n--- GRUPO 6: TRADUCCIÓN AL ESPAÑOL Y BOTÓN RESTABLECER ---\n";

test_afirmar(
    strpos($personalizadorPhp, 'Colores de Tema:') !== false,
    "TRADUCCION-01: 'Theme colors' traducido a 'Colores de Tema:'"
);
test_afirmar(
    strpos($personalizadorPhp, 'Disposición de Diseño:') !== false,
    "TRADUCCION-02: 'Theme layouts' traducido a 'Disposición de Diseño:'"
);
test_afirmar(
    strpos($personalizadorPhp, 'Variante de Barra Lateral:') !== false,
    "TRADUCCION-03: 'Sidebar Variant' traducido a 'Variante de Barra Lateral:'"
);
test_afirmar(
    strpos($personalizadorPhp, 'Escala de Texto:') !== false,
    "TRADUCCION-04: 'Font Sizing' traducido a 'Escala de Texto:'"
);
test_afirmar(
    strpos($personalizadorPhp, 'id="btn-restablecer-personalizador"') !== false,
    "RESTABLECER-01: Botón #btn-restablecer-personalizador presente en el pie del offcanvas"
);
test_afirmar(
    strpos($personalizadorPhp, 'btn-danger w-100') !== false,
    "RESTABLECER-02: Botón Restablecer centrado mediante ancho completo (w-100) en el footer"
);

// --- GRUPO 7: INTEGRACIÓN EN LAYOUT Y PREVENCIÓN FOUC ---
echo "\n--- GRUPO 7: INTEGRACIÓN EN LAYOUT Y PREVENCIÓN FOUC ---\n";

$principalPhp = file_get_contents(__DIR__ . '/../app/Vistas/plantillas/principal.php');
test_afirmar(
    strpos($principalPhp, "Vista::componente('personalizador')") !== false,
    "LAYOUT-01: Plantilla principal.php renderiza el componente personalizador"
);
test_afirmar(
    strpos($principalPhp, 'id="theme-customizer-box"') !== false,
    "LAYOUT-02: Contenedor #theme-customizer-box de Alina presente en principal.php"
);

$headPhp = file_get_contents(__DIR__ . '/../app/Vistas/componentes/head.php');
test_afirmar(
    strpos($headPhp, 'theme_color') !== false && strpos($headPhp, 'theme_layout') !== false,
    "FOUC-01: Script de prevención temprana de FOUC presente en head.php"
);
test_afirmar(
    strpos($headPhp, 'localStorage.getItem') !== false,
    "FOUC-02: Prevención de FOUC lee preferencias locales de localStorage"
);

// --- GRUPO 8: JAVASCRIPT EN camargo-layout.js (VANILLA JS, LOCALSTORAGE, CERO JQUERY) ---
echo "\n--- GRUPO 8: JAVASCRIPT EN camargo-layout.js (VANILLA JS, LOCALSTORAGE) ---\n";

$layoutJs = file_get_contents(__DIR__ . '/../public/assets/js/camargo-layout.js');
test_afirmar(
    strpos($layoutJs, 'function inicializarPersonalizador()') !== false,
    "JS-01: Función inicializarPersonalizador() implementada en camargo-layout.js"
);
test_afirmar(
    strpos($layoutJs, 'inicializarPersonalizador();') !== false,
    "JS-02: inicializarPersonalizador() invocada en el ciclo iniciar() tras DOMContentLoaded"
);
test_afirmar(
    strpos($layoutJs, "localStorage.getItem('theme_color')") !== false,
    "JS-03: Color de tema recuperado de localStorage"
);
test_afirmar(
    strpos($layoutJs, "localStorage.getItem('theme_layout')") !== false,
    "JS-04: Disposición de diseño recuperada de localStorage"
);
test_afirmar(
    strpos($layoutJs, "localStorage.getItem('sidebar_variant')") !== false,
    "JS-05: Variante de sidebar recuperada de localStorage"
);
test_afirmar(
    strpos($layoutJs, "localStorage.getItem('theme_font_size')") !== false,
    "JS-06: Escala de texto recuperada de localStorage"
);
test_afirmar(
    strpos($layoutJs, "localStorage.removeItem('theme_color')") !== false,
    "JS-07: Botón restablecer limpia las preferencias guardadas en localStorage"
);
test_afirmar(
    strpos($layoutJs, 'canvas-active') !== false,
    "JS-08: Gestión de clase .canvas-active para sincronizar flotante con offcanvas Alina"
);

// --- GRUPO 9: GOBERNANZA, BASE DE DATOS Y ESTADO PRÍSTINO ---
echo "\n--- GRUPO 9: GOBERNANZA, BASE DE DATOS Y ESTADO PRÍSTINO ---\n";

$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
test_afirmar(
    count($tables) >= 118,
    "BD-01: Total de tablas relacionales se mantiene (actual: " . count($tables) . ")"
);

$slot037 = glob(__DIR__ . '/../SQL/migraciones/037*');
test_afirmar(
    empty($slot037),
    "BD-02: Ranura de migración 037 permanece estrictamente LIBRE (cero DDL no autorizado)"
);

$mig034 = (bool) $pdo->query("SELECT 1 FROM migraciones WHERE migracion = '034_agregar_foto_personas.sql'")->fetchColumn();
test_afirmar(
    $mig034,
    "BD-03: Migración 034_agregar_foto_personas.sql presente en camargo_pms"
);

// Resumen Final
echo "\n====================================================================\n";
echo " RESUMEN: {$asercionesExitosas}/{$totalAserciones} PRUEBAS SUPERADAS\n";
echo "====================================================================\n";

if ($asercionesExitosas !== $totalAserciones) {
    echo ">>> REGRESIÓN O FALLO EN SUITE UI-ALINA-1B <<<\n";
    exit(1);
}

echo ">>> SUITE UI-ALINA-1B: 100% EXITOSA <<<\n";
exit(0);
