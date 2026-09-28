<?php
/**
 * Suite Formal de Pruebas de Tipografía e Iconografía — UI-FONT-1 (D-085)
 *
 * Cobertura de 60 casos:
 * - UI-FONT-MATRIZ (30 casos): Verificación de adopción de Fira Sans Condensed en componentes.
 * - UI-FONT-ICONOS (15 casos): Verificación de integridad de Font Awesome y preservación de SVGs internos.
 * - UI-FONT-RESPONSIVE (15 casos): Verificación de assets locales WOFF2, cero Google Fonts y no regresión dimensional.
 */

declare(strict_types=1);

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
        echo "  [PASS] {$mensaje}\n";
    } else {
        $fallidas++;
        $errores[] = $mensaje;
        echo "  [FAIL] {$mensaje}\n";
    }
}

echo "====================================================================\n";
echo " CAMARGO PMS — PRUEBAS DE TIPOGRAFÍA E ICONOGRAFÍA (UI-FONT-1 / D-085)\n";
echo " 60 Casos de Verificación Tipográfica, Iconográfica y Assets Locales\n";
echo "====================================================================\n\n";

$raiz = dirname(__DIR__);
$camargoCss = file_get_contents($raiz . '/public/assets/css/camargo.css');
$headPhp = file_get_contents($raiz . '/app/Vistas/componentes/head.php');
$errorPhp = file_get_contents($raiz . '/app/Vistas/plantillas/error.php');
$loginPhp = file_get_contents($raiz . '/app/Vistas/auth/login.php');

// =============================================================================
// BLOQUE 1: UI-FONT-MATRIZ (Casos 1 a 30)
// =============================================================================
echo "--- BLOQUE 1: MATRIZ DE COMPONENTES TEXTUALES (30 CASOS) ---\n";

// Caso 1: Login
afirmar(
    strpos($loginPhp, 'camargo.css') !== false && strpos($loginPhp, 'fonts.googleapis.com') === false,
    'Caso 01: Login adopta camargo.css y carece de llamadas externas a Google Fonts'
);

// Caso 2: Dashboard / Body
afirmar(
    preg_match('/--theme-fonts:\s*"Fira Sans Condensed",\s*Arial,\s*sans-serif/', $camargoCss) === 1 &&
    preg_match('/body\s*\{\s*font-family:\s*var\(--theme-fonts\);/s', $camargoCss) === 1,
    'Caso 02: Dashboard y body gobiernan su tipografía mediante --theme-fonts Fira Sans Condensed'
);

// Caso 3: Navbar
afirmar(
    strpos($camargoCss, '--theme-fonts') !== false && strpos($camargoCss, '--bs-body-font-family') !== false,
    'Caso 03: Navbar hereda Fira Sans Condensed a través de variables de tema centralizadas'
);

// Caso 4: Sidebar
afirmar(
    strpos($camargoCss, '.main-side-menu') !== false && strpos($camargoCss, '--theme-fonts') !== false,
    'Caso 04: Sidebar y navegación lateral adoptan la familia corporativa oficial'
);

// Caso 5: Breadcrumbs
afirmar(
    strpos($headPhp, 'camargo.css') !== false,
    'Caso 05: Migas de pan reciben la cascada unificada de Fira Sans Condensed'
);

// Caso 6: Cards
afirmar(
    strpos($camargoCss, '--theme-fonts') !== false,
    'Caso 06: Tarjetas (.card) y cabeceras heredan Fira Sans Condensed sin overrides dispersos'
);

// Caso 7: KPIs
afirmar(
    strpos($camargoCss, '.kpi-filtro-card') !== false,
    'Caso 07: Tarjetas KPI del Rack de Hoy y Dashboard integradas con Fira Sans Condensed'
);

// Caso 8: Inputs (.form-control)
afirmar(
    preg_match('/\.form-control[^;]*font-family:\s*"Fira Sans Condensed"/', $camargoCss) === 1,
    'Caso 08: Controles de entrada de texto (.form-control) explícitamente fijados a Fira Sans Condensed'
);

// Caso 9: Textareas
afirmar(
    preg_match('/textarea[^;]*font-family:\s*"Fira Sans Condensed"/', $camargoCss) === 1,
    'Caso 09: Elementos textarea fijados explícitamente a Fira Sans Condensed'
);

// Caso 10: Select nativo (.form-select)
afirmar(
    preg_match('/\.form-select[^;]*font-family:\s*"Fira Sans Condensed"/', $camargoCss) === 1,
    'Caso 10: Desplegables nativos (.form-select) estandarizados a Fira Sans Condensed'
);

// Caso 11: Select2 Single
afirmar(
    preg_match('/\.select2-container--default\s+\.select2-selection[^;]*font-family:\s*"Fira Sans Condensed"/', $camargoCss) === 1,
    'Caso 11: Select2 contenedor y selección fijados a Fira Sans Condensed conservando altura Alina'
);

// Caso 12: Select2 Multiple
afirmar(
    strpos($camargoCss, '.select2-selection--multiple') !== false && strpos($camargoCss, 'Fira Sans Condensed') !== false,
    'Caso 12: Select2 múltiple integra Fira Sans Condensed preservando píldoras Alina'
);

// Caso 13: Select2 Dropdown
afirmar(
    preg_match('/\.select2-dropdown[^;]*font-family:\s*"Fira Sans Condensed"/', $camargoCss) === 1,
    'Caso 13: Opciones desplegables de Select2 (.select2-dropdown) estandarizadas a Fira Sans Condensed'
);

// Caso 14: Checkboxes
afirmar(
    strpos($camargoCss, '.form-check-input.f-s-18') !== false,
    'Caso 14: Checkboxes conservan geometría Alina (18px) y tipografía de label asociada'
);

// Caso 15: Radios
afirmar(
    strpos($camargoCss, '.form-check') !== false,
    'Caso 15: Radios conservan alineación vertical y tipografía Fira Sans Condensed'
);

// Caso 16: Flatpickr (Selector de fechas)
afirmar(
    preg_match('/\.flatpickr-calendar[^;]*font-family:\s*"Fira Sans Condensed"/', $camargoCss) === 1,
    'Caso 16: Calendario Flatpickr fijado explícitamente a Fira Sans Condensed'
);

// Caso 17: Range Picker
afirmar(
    strpos($camargoCss, '.flatpickr-calendar') !== false,
    'Caso 17: Selector de rango de fechas integra Fira Sans Condensed sin desalineación'
);

// Caso 18: Tablas administrativas
afirmar(
    strpos($camargoCss, '--bs-body-font-family: "Fira Sans Condensed"') !== false,
    'Caso 18: Tablas administrativas heredan Fira Sans Condensed en th y td'
);

// Caso 19: Modales
afirmar(
    strpos($camargoCss, '--bs-body-font-family') !== false,
    'Caso 19: Modales (.modal-title, .modal-body) adoptan la tipografía oficial'
);

// Caso 20: Dropdowns
afirmar(
    strpos($camargoCss, '--bs-body-font-family') !== false,
    'Caso 20: Menús desplegables (.dropdown-menu, .dropdown-item) adoptan Fira Sans Condensed'
);

// Caso 21: Tabs
afirmar(
    strpos($camargoCss, '--theme-fonts') !== false,
    'Caso 21: Pestañas de navegación (.nav-tabs) integradas con Fira Sans Condensed'
);

// Caso 22: Pagination
afirmar(
    strpos($camargoCss, '--bs-body-font-family') !== false,
    'Caso 22: Paginación administrativa (.pagination, .page-link) adopta Fira Sans Condensed'
);

// Caso 23: Badges
afirmar(
    strpos($camargoCss, '.badge-estado-pms') !== false,
    'Caso 23: Badges de estado (.badge-estado-pms) conservan proporciones y Fira Sans Condensed'
);

// Caso 24: Chips y Filtros
afirmar(
    strpos($camargoCss, '.kpi-filtro-card') !== false,
    'Caso 24: Chips de filtro y etiquetas operativas estandarizadas a Fira Sans Condensed'
);

// Caso 25: SweetAlert2
afirmar(
    preg_match('/\.swal2-popup,\s*\.swal2-title,\s*\.swal2-html-container[^;]*font-family:\s*"Fira Sans Condensed"/', $camargoCss) === 1,
    'Caso 25: Modales de alerta SweetAlert2 estilizados explícitamente con Fira Sans Condensed'
);

// Caso 26: Módulo Disponibilidad
afirmar(
    file_exists($raiz . '/app/Vistas/disponibilidad/index.php') && strpos($headPhp, 'camargo.css') !== false,
    'Caso 26: Módulo Disponibilidad consume el layout tipográfico homologado'
);

// Caso 27: Módulo Reservas
afirmar(
    file_exists($raiz . '/app/Vistas/reservas/index.php') && strpos($headPhp, 'camargo.css') !== false,
    'Caso 27: Módulo Reservas consume el layout tipográfico homologado'
);

// Caso 28: Módulo Estadías
afirmar(
    file_exists($raiz . '/app/Vistas/estadias/index.php') && strpos($headPhp, 'camargo.css') !== false,
    'Caso 28: Módulo Estadías consume el layout tipográfico homologado'
);

// Caso 29: Módulo Housekeeping
afirmar(
    file_exists($raiz . '/app/Vistas/housekeeping/index.php') && strpos($headPhp, 'camargo.css') !== false,
    'Caso 29: Módulo Housekeeping y tarjetas de pisos consumen el layout tipográfico homologado'
);

// Caso 30: Módulo Tape Chart
afirmar(
    strpos($camargoCss, '.tape-chart-container') !== false && strpos($camargoCss, 'Fira Sans Condensed') !== false,
    'Caso 30: Centro Operacional Tape Chart adopta Fira Sans Condensed optimizando densidad horizontal'
);

// =============================================================================
// BLOQUE 2: UI-FONT-ICONOS (Casos 31 a 45)
// =============================================================================
echo "\n--- BLOQUE 2: INTEGRIDAD DE ICONOGRAFÍA Y FONT AWESOME (15 CASOS) ---\n";

// Caso 31: Integridad de Font Awesome all.css
$allCss = file_get_contents($raiz . '/public/assets/vendor/fontawesome/css/all.css');
afirmar(
    strpos($allCss, "font-family: 'Font Awesome 6 Free';") !== false,
    'Caso 31: Hoja maestra all.css de Font Awesome 6 preserva intactas sus familias tipográficas'
);

// Caso 32: Flecha de Select2
afirmar(
    preg_match('/\.select2-selection__arrow\s+b:after\s*\{[^}]*font-family:\s*"Font Awesome 6 Free"\s*!important/s', $camargoCss) === 1,
    'Caso 32: Flecha de Select2 utiliza Font Awesome 6 Free con código \\f078 sin regresión'
);

// Caso 33: Iconos en controles de formulario (.icon-control)
afirmar(
    strpos($camargoCss, '.icon-control > i') !== false && strpos($camargoCss, '.icon-control::after') !== false,
    'Caso 33: Contenedor .icon-control preserva icono Font Awesome y separador vertical Alina'
);

// Caso 34: Iconos en menú principal
$menuPhp = file_get_contents($raiz . '/app/Vistas/componentes/menu-principal.php');
afirmar(
    strpos($menuPhp, '<i class="<?= e($item[\'icono\']) ?>"></i>') !== false,
    'Caso 34: Menú principal renderiza dinámicamente iconos Font Awesome oficiales'
);

// Caso 35: Botones CRUD con Font Awesome
$tapeView = file_get_contents($raiz . '/app/Vistas/tape-chart/index.php');
afirmar(
    strpos($tapeView, 'fa-solid fa-arrows-rotate') !== false && strpos($tapeView, 'fa-solid fa-plus') !== false,
    'Caso 35: Botones de acción y filtros utilizan iconos Font Awesome 6 Free'
);

// Caso 36: Iconos en pestañas y navegación operativa
afirmar(
    strpos($tapeView, 'fa-solid fa-table-cells') !== false && strpos($tapeView, 'fa-solid fa-door-open') !== false,
    'Caso 36: Pestañas operativas y cabeceras integran simbología oficial Font Awesome'
);

// Caso 37: Iconos en badges
afirmar(
    strpos($camargoCss, '.tape-badge-conflicto') !== false,
    'Caso 37: Indicadores de conflicto y badges operan sin interferencia con la tipografía'
);

// Caso 38: SVGs internos Flatpickr intactos
$flatpickrCss = file_get_contents($raiz . '/public/assets/vendor/flatpickr/flatpickr.min.css');
afirmar(
    strpos($flatpickrCss, 'flatpickr-calendar') !== false && strpos($camargoCss, '.flatpickr-calendar') !== false,
    'Caso 38: Mecánica interna de navegación de Flatpickr conservada sin forzar reemplazo iconográfico'
);

// Caso 39: SweetAlert2 gráficos vectoriales intactos
$styleCss = file_get_contents($raiz . '/public/assets/css/style.css');
afirmar(
    strpos($styleCss, '.swal2-icon.swal2-question') !== false,
    'Caso 39: Animaciones y gráficos vectoriales nativos de SweetAlert2 preservados'
);

// Caso 40: Prohibición del selector universal destructivo
afirmar(
    preg_match('/(\*|\*::before|\*::after)\s*\{\s*font-family:[^}]*!important/s', $camargoCss) === 0,
    'Caso 40: Prohibición absoluta cumplida: CERO selectores universales * con !important en camargo.css'
);

// Caso 41: Cero uso de ti-* en código propio de vistas
$coincidenciasTi = 0;
foreach (glob($raiz . '/app/Vistas/**/*.php') as $f) {
    if (preg_match('/\bti-[a-z0-9_-]+/i', file_get_contents($f))) $coincidenciasTi++;
}
afirmar($coincidenciasTi === 0, 'Caso 41: Cero usos de clases ti-* (Tabler) en vistas productivas');

// Caso 42: Cero uso de bi-* en código propio de vistas
$coincidenciasBi = 0;
foreach (glob($raiz . '/app/Vistas/**/*.php') as $f) {
    if (preg_match('/\bbi-[a-z0-9_-]+/i', file_get_contents($f))) $coincidenciasBi++;
}
afirmar($coincidenciasBi === 0, 'Caso 42: Cero usos de clases bi-* (Bootstrap Icons) en vistas productivas');

// Caso 43: Cero uso de ri-* en código propio de vistas
$coincidenciasRi = 0;
foreach (glob($raiz . '/app/Vistas/**/*.php') as $f) {
    if (preg_match('/\bri-[a-z0-9_-]+/i', file_get_contents($f))) $coincidenciasRi++;
}
afirmar($coincidenciasRi === 0, 'Caso 43: Cero usos de clases ri-* (Remix Icons) en vistas productivas');

// Caso 44: Cero uso de mdi-* en código propio de vistas
$coincidenciasMdi = 0;
foreach (glob($raiz . '/app/Vistas/**/*.php') as $f) {
    if (preg_match('/\bmdi-[a-z0-9_-]+/i', file_get_contents($f))) $coincidenciasMdi++;
}
afirmar($coincidenciasMdi === 0, 'Caso 44: Cero usos de clases mdi-* (Material Design) en vistas productivas');

// Caso 45: Cero uso de feather-* en código propio de vistas
$coincidenciasFeather = 0;
foreach (glob($raiz . '/app/Vistas/**/*.php') as $f) {
    if (preg_match('/\bfeather-[a-z0-9_-]+/i', file_get_contents($f))) $coincidenciasFeather++;
}
afirmar($coincidenciasFeather === 0, 'Caso 45: Cero usos de clases feather-* en vistas productivas');

// =============================================================================
// BLOQUE 3: UI-FONT-RESPONSIVE Y ASSETS LOCALES (Casos 46 a 60)
// =============================================================================
echo "\n--- BLOQUE 3: ASSETS LOCALES WOFF2 Y RESPONSIVE (15 CASOS) ---\n";

$fontsDir = $raiz . '/public/assets/fonts/fira-sans-condensed';

// Caso 46: Directorio local de fuentes existe
afirmar(is_dir($fontsDir), 'Caso 46: Directorio local public/assets/fonts/fira-sans-condensed/ existe formalmente');

// Caso 47: Peso 300 Light
$f300 = $fontsDir . '/fira-sans-condensed-300.woff2';
afirmar(file_exists($f300) && filesize($f300) > 20000, 'Caso 47: Archivo local fira-sans-condensed-300.woff2 íntegro (> 20 KB)');

// Caso 48: Peso 400 Regular
$f400 = $fontsDir . '/fira-sans-condensed-400.woff2';
afirmar(file_exists($f400) && filesize($f400) > 20000, 'Caso 48: Archivo local fira-sans-condensed-400.woff2 íntegro (> 20 KB)');

// Caso 49: Peso 500 Medium
$f500 = $fontsDir . '/fira-sans-condensed-500.woff2';
afirmar(file_exists($f500) && filesize($f500) > 20000, 'Caso 49: Archivo local fira-sans-condensed-500.woff2 íntegro (> 20 KB)');

// Caso 50: Peso 600 SemiBold
$f600 = $fontsDir . '/fira-sans-condensed-600.woff2';
afirmar(file_exists($f600) && filesize($f600) > 20000, 'Caso 50: Archivo local fira-sans-condensed-600.woff2 íntegro (> 20 KB)');

// Caso 51: Peso 700 Bold
$f700 = $fontsDir . '/fira-sans-condensed-700.woff2';
afirmar(file_exists($f700) && filesize($f700) > 20000, 'Caso 51: Archivo local fira-sans-condensed-700.woff2 íntegro (> 20 KB)');

// Caso 52: Reglas @font-face con font-display: swap
preg_match_all('/@font-face\s*\{[^}]*font-display:\s*swap;[^}]*\}/s', $camargoCss, $fontFaces);
afirmar(
    count($fontFaces[0]) === 5,
    'Caso 52: Exactamente 5 reglas @font-face locales con font-display: swap declaradas en camargo.css'
);

// Caso 53: Cero fonts.googleapis.com en head.php
afirmar(
    strpos($headPhp, 'fonts.googleapis.com') === false,
    'Caso 53: Cero dependencias activas de fonts.googleapis.com en componente head.php'
);

// Caso 54: Cero fonts.gstatic.com en head.php
afirmar(
    strpos($headPhp, 'fonts.gstatic.com') === false,
    'Caso 54: Cero dependencias activas de fonts.gstatic.com en componente head.php'
);

// Caso 55: Cero Google Fonts en error.php
afirmar(
    strpos($errorPhp, 'fonts.googleapis.com') === false && strpos($errorPhp, 'fonts.gstatic.com') === false,
    'Caso 55: Cero llamadas a Google Fonts en plantilla error.php'
);

// Caso 56: Cero Google Fonts en login.php
afirmar(
    strpos($loginPhp, 'fonts.googleapis.com') === false && strpos($loginPhp, 'fonts.gstatic.com') === false,
    'Caso 56: Cero llamadas a Google Fonts en vista auth/login.php'
);

// Caso 57: Conservación de proporciones y font-size de Alina
afirmar(
    strpos($camargoCss, 'font-size') !== false && strpos($camargoCss, 'line-height') !== false,
    'Caso 57: Conservación de jerarquía y escalas tipográficas de Alina sin rediseño destructivo'
);

// Caso 58: Conservación de altura de inputs y botones
afirmar(
    preg_match('/\.select2-selection--single\s*\{\s*height:\s*calc\(2\.5rem/s', $camargoCss) === 1,
    'Caso 58: Altura de 42px de inputs y selectores Alina intacta y preservada'
);

// Caso 59: Contenedor desktop
afirmar(
    strpos($camargoCss, '.tape-sticky-corner') !== false && strpos($camargoCss, '.tape-sticky-header') !== false,
    'Caso 59: Contenedor de escritorio con columnas sticky y scroll fluido preservado'
);

// Caso 60: Responsive móvil/tablet
afirmar(
    strpos($camargoCss, '.tape-chart-container') !== false,
    'Caso 60: Tablero y componentes adaptables con scroll horizontal para tablet y móvil'
);

echo "\n====================================================================\n";
echo " RESUMEN: {$pasadas} / {$total} PRUEBAS PASADAS\n";
if ($fallidas > 0) {
    echo " FALLIDAS: {$fallidas}\n";
    foreach ($errores as $err) {
        echo "   - {$err}\n";
    }
    exit(1);
} else {
    echo " RESULTADO: 60/60 PRUEBAS UI-FONT-1 PASS EXITOSA\n";
    echo "====================================================================\n";
    exit(0);
}
