<?php

declare(strict_types=1);

/**
 * Suite de Verificación TAPE-CHART-1 — 10 Casos Críticos de Dominio y Proyección Operacional
 *
 * Principios vinculantes de D-084:
 * 1. Deduplicación de Reserva -> Estadía (cero doble conteo).
 * 2. Semántica de intervalo semiabierto [E, S): fecha de salida no consume noche hotelera.
 * 3. Arrendamiento patrimonial vigente dominando celda (impide falso vacante).
 * 4. Expiración en tiempo real de holds de reservas pendientes.
 * 5. Mantenimiento OOO con bloqueo físico activo (requiere_bloqueo = 1).
 * 6. Mantenimiento leve no bloqueante (requiere_bloqueo = 0) preservando disponibilidad.
 * 7. Vacante Hoy en VR (Limpia e inspeccionada) apta para check-in.
 * 8. Vacante Hoy en VD (Sucia) no apta para check-in.
 * 9. Vacante Hoy en VCL (En limpieza / por inspeccionar) no apta para check-in.
 * 10. Colisión OOO bloqueante + reserva confirmada simultánea con preservación de conflicto.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CamargoPMS\Modelos\HousekeepingEstadoLimpieza;
use CamargoPMS\Modelos\Propiedad;
use CamargoPMS\Modelos\TapeChartCelda;
use CamargoPMS\Modelos\TapeChartProyeccion;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Repositorios\PropiedadRepositorio;
use CamargoPMS\Repositorios\TapeChartRepositorio;
use CamargoPMS\Servicios\AutorizacionServicio;
use CamargoPMS\Servicios\ConfiguracionServicio;
use CamargoPMS\Servicios\TapeChartServicio;

Configuracion::cargar(dirname(__DIR__));
$pdo = BaseDatos::conexion();

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
echo " CAMARGO PMS — PRUEBAS DE PROYECCIÓN Y REGLAS DE DOMINIO (10 CASOS)\n";
echo " Tape Chart y Rack Hotelero Unificado (TAPE-CHART-1 / D-084)\n";
echo "====================================================================\n\n";

// Repositorio Mock / Simulador controlado para aislar los 10 escenarios sin efectos secundarios
class TapeChartRepositorioSimulado extends TapeChartRepositorio
{
    public array $unidadesSimuladas = [];
    public array $inventarioSimulado = [];
    public array $reservasSimuladas = [];
    public array $estadiasSimuladas = [];
    public array $arrendamientosSimulados = [];
    public array $mantenimientoSimulado = [];
    public array $limpiezaSimulada = [];

    public function __construct()
    {
        // No requiere conexión activa para datos simulados
    }

    public function obtenerUnidadesPropiedad(int $propiedadId, ?int $tipoUnidadId = null, ?string $pisoNivel = null): array
    {
        return $this->unidadesSimuladas;
    }

    public function obtenerInventarioRango(int $propiedadId, string $fechaDesde, string $fechaHasta): array
    {
        return $this->inventarioSimulado;
    }

    public function obtenerReservasEnRango(int $propiedadId, string $fechaDesde, string $fechaHasta): array
    {
        return $this->reservasSimuladas;
    }

    public function obtenerEstadiasEnRango(int $propiedadId, string $fechaDesde, string $fechaHasta): array
    {
        return $this->estadiasSimuladas;
    }

    public function obtenerArrendamientosEnRango(int $propiedadId, string $fechaDesde, string $fechaHasta): array
    {
        return $this->arrendamientosSimulados;
    }

    public function obtenerMantenimientoEnRango(int $propiedadId, string $fechaDesde, string $fechaHasta): array
    {
        return $this->mantenimientoSimulado;
    }

    public function obtenerLimpiezaActual(int $propiedadId): array
    {
        return $this->limpiezaSimulada;
    }
}

class PropiedadRepositorioSimulado extends PropiedadRepositorio
{
    public function __construct()
    {
    }

    public function buscarPorId(int $id): ?Propiedad
    {
        return new Propiedad(
            id: $id,
            codigo: 'PROP-TEST',
            nombre: 'Propiedad Test Simulación',
            estado: 'ACTIVO',
            zonaHoraria: 'America/Lima'
        );
    }
}

$tzLima = new DateTimeZone('America/Lima');
$dtHoy = new DateTimeImmutable('now', $tzLima);
$fHoy = $dtHoy->format('Y-m-d');
$fManana = $dtHoy->modify('+1 day')->format('Y-m-d');
$fPasado = $dtHoy->modify('+2 days')->format('Y-m-d');
$fEn3Dias = $dtHoy->modify('+3 days')->format('Y-m-d');
$fEn4Dias = $dtHoy->modify('+4 days')->format('Y-m-d');
$fEn7Dias = $dtHoy->modify('+7 days')->format('Y-m-d');

// Unidad base de prueba
$unidadBase = [
    'id' => 10,
    'codigo' => '101',
    'nombre' => 'Habitación Estándar 101',
    'propiedad_id' => 1,
    'propiedad_nombre' => 'Propiedad Test Simulación',
    'propiedad_zona_horaria' => 'America/Lima',
    'tipo_unidad_id' => 1,
    'tipo_unidad_nombre' => 'HABITACION',
    'piso_nivel' => '1',
    'capacidad_personas' => 2,
    'estado' => 'ACTIVO',
];

// -----------------------------------------------------------------------------
// CASO 1: Deduplicación Reserva -> Estadía en curso (cero doble conteo)
// -----------------------------------------------------------------------------
echo "--- CASO 1: Deduplicación Reserva -> Estadía en curso ---\n";
$repoSim1 = new TapeChartRepositorioSimulado();
$repoSim1->unidadesSimuladas = [$unidadBase];
$repoSim1->limpiezaSimulada = [['unidad_id' => 10, 'estado_limpieza' => HousekeepingEstadoLimpieza::ESTADO_LIMPIA_INSPECCIONADA]];
// Reserva 50 sobre unidad 10
$repoSim1->reservasSimuladas = [[
    'id' => 50,
    'codigo' => 'RES-2026-0050',
    'estado' => 'CONFIRMADA',
    'fecha_creacion' => $fHoy . ' 10:00:00',
    'expira_en' => null,
    'moneda_codigo' => 'PEN',
    'total' => '300.00',
    'persona_titular_id' => 1,
    'titular_nombre_completo' => 'Pérez Juan',
    'titular_apellido' => 'Pérez',
    'titular_nombres' => 'Juan',
    'titular_documento' => '12345678',
    'reserva_unidad_id' => 501,
    'unidad_id' => 10,
    'fecha_entrada' => $fHoy,
    'fecha_salida' => $fPasado,
    'noches' => 2,
    'precio_por_noche' => '150.00',
    'total_linea' => '300.00',
]];
// Estadía en curso vinculada a la misma reserva 50
$repoSim1->estadiasSimuladas = [[
    'id' => 70,
    'codigo' => 'EST-2026-0070',
    'reserva_id' => 50,
    'reserva_unidad_id' => 501,
    'unidad_id' => 10,
    'fecha_entrada' => $fHoy,
    'fecha_salida_programada' => $fPasado,
    'fecha_salida_real' => null,
    'estado' => 'EN_CURSO',
    'noches' => 2,
    'reserva_codigo' => 'RES-2026-0050',
    'titular_reserva_nombre' => 'Pérez Juan',
    'titular_huesped_responsable' => 'Pérez Juan',
    'responsable_apellido' => 'Pérez',
    'responsable_nombres' => 'Juan',
    'responsable_documento' => '12345678',
]];

$servicio1 = new TapeChartServicio($repoSim1, new PropiedadRepositorioSimulado());
$proy1 = $servicio1->obtenerProyeccion(1, $fHoy, $fEn7Dias);
$celdaHoy1 = $proy1->obtenerFilasUnidades()[0]->obtenerCelda($fHoy);
$kpis1 = $proy1->obtenerKpisHoy();

afirmar(
    $celdaHoy1->obtenerEstadoPrincipal() === TapeChartCelda::ESTADO_ESTADIA &&
    $celdaHoy1->obtenerCodigoReferencia() === 'EST-2026-0070' &&
    $kpis1['ocupadas_stayover'] === 1 &&
    $kpis1['vacantes_listas_vr'] === 0,
    "Caso 1: Reserva convertida en Estadía es dominada por la estadía; cero doble conteo en celda y KPIs"
);

// -----------------------------------------------------------------------------
// CASO 2: Check-out en intervalo [E, S) no consume noche hotelera
// -----------------------------------------------------------------------------
echo "\n--- CASO 2: Semántica de salida hotelera [E, S) ---\n";
// Para la estadía de 2 noches [fHoy, fPasado):
// Noche 1 = fHoy (ocupada por estadía)
// Noche 2 = fManana (ocupada por estadía)
// Día de salida = fPasado (no consume noche; celda fPasado es VACANTE o disponible)
$celdaSalida2 = $proy1->obtenerFilasUnidades()[0]->obtenerCelda($fPasado);
afirmar(
    $celdaSalida2->obtenerEstadoPrincipal() === TapeChartCelda::ESTADO_VACANTE &&
    $celdaSalida2->esDeparture() === true,
    "Caso 2: En la fecha de check-out, la unidad no consume noche hotelera (esDeparture === true y estado VACANTE)"
);

// -----------------------------------------------------------------------------
// CASO 3: Arrendamiento patrimonial vigente dominando celda
// -----------------------------------------------------------------------------
echo "\n--- CASO 3: Arrendamiento patrimonial vigente ---\n";
$repoSim3 = new TapeChartRepositorioSimulado();
$repoSim3->unidadesSimuladas = [$unidadBase];
$repoSim3->limpiezaSimulada = [['unidad_id' => 10, 'estado_limpieza' => HousekeepingEstadoLimpieza::ESTADO_LIMPIA_INSPECCIONADA]];
$repoSim3->arrendamientosSimulados = [[
    'id' => 88,
    'codigo' => 'ARR-2026-0088',
    'unidad_id' => 10,
    'fecha_inicio' => $fHoy,
    'fecha_fin' => $dtHoy->modify('+30 days')->format('Y-m-d'),
    'estado' => 'VIGENTE',
    'renta_mensual' => '2500.00',
    'moneda_codigo' => 'PEN',
    'titular_nombre_completo' => 'Gómez María',
    'titular_apellido' => 'Gómez',
    'titular_nombres' => 'María',
    'titular_documento' => '87654321',
]];

$servicio3 = new TapeChartServicio($repoSim3, new PropiedadRepositorioSimulado());
$proy3 = $servicio3->obtenerProyeccion(1, $fHoy, $fEn7Dias);
$celdaHoy3 = $proy3->obtenerFilasUnidades()[0]->obtenerCelda($fHoy);
$kpis3 = $proy3->obtenerKpisHoy();

afirmar(
    $celdaHoy3->obtenerEstadoPrincipal() === TapeChartCelda::ESTADO_ARRENDAMIENTO &&
    $celdaHoy3->obtenerCodigoReferencia() === 'ARR-2026-0088' &&
    $celdaHoy3->obtenerSubestado() === 'ARRENDADA' &&
    $kpis3['ocupadas_stayover'] === 1,
    "Caso 3: Arrendamiento mensual vigente domina el horizonte e impide falso vacante en tablero y KPIs"
);

// -----------------------------------------------------------------------------
// CASO 4: Reserva HOLD expirada no bloquea visualmente
// -----------------------------------------------------------------------------
echo "\n--- CASO 4: Reserva HOLD temporal expirada ---\n";
$repoSim4 = new TapeChartRepositorioSimulado();
$repoSim4->unidadesSimuladas = [$unidadBase];
$repoSim4->limpiezaSimulada = [['unidad_id' => 10, 'estado_limpieza' => HousekeepingEstadoLimpieza::ESTADO_LIMPIA_INSPECCIONADA]];
// Reserva creada hace 45 minutos (hold configurado a 30 min)
$hace45Min = $dtHoy->modify('-45 minutes')->format('Y-m-d H:i:s');
$repoSim4->reservasSimuladas = [[
    'id' => 99,
    'codigo' => 'RES-HOLD-EXPIRADA',
    'estado' => 'PENDIENTE',
    'fecha_creacion' => $hace45Min,
    'expira_en' => $dtHoy->modify('-15 minutes')->format('Y-m-d H:i:s'),
    'moneda_codigo' => 'PEN',
    'total' => '200.00',
    'persona_titular_id' => 2,
    'titular_nombre_completo' => 'Sánchez Carlos',
    'titular_apellido' => 'Sánchez',
    'titular_nombres' => 'Carlos',
    'titular_documento' => '11223344',
    'reserva_unidad_id' => 991,
    'unidad_id' => 10,
    'fecha_entrada' => $fHoy,
    'fecha_salida' => $fEn3Dias,
    'noches' => 3,
    'precio_por_noche' => '100.00',
    'total_linea' => '200.00',
]];

$servicio4 = new TapeChartServicio($repoSim4, new PropiedadRepositorioSimulado());
$proy4 = $servicio4->obtenerProyeccion(1, $fHoy, $fEn7Dias);
$celdaHoy4 = $proy4->obtenerFilasUnidades()[0]->obtenerCelda($fHoy);

afirmar(
    $celdaHoy4->obtenerEstadoPrincipal() === TapeChartCelda::ESTADO_VACANTE &&
    $celdaHoy4->obtenerSubestado() === 'VR',
    "Caso 4: Hold pendiente expirado en tiempo real no bloquea la celda (se proyecta VACANTE VR)"
);

// -----------------------------------------------------------------------------
// CASO 5: Mantenimiento OOO con requiere_bloqueo = 1
// -----------------------------------------------------------------------------
echo "\n--- CASO 5: Mantenimiento OOO bloqueante ---\n";
$repoSim5 = new TapeChartRepositorioSimulado();
$repoSim5->unidadesSimuladas = [$unidadBase];
$repoSim5->limpiezaSimulada = [['unidad_id' => 10, 'estado_limpieza' => HousekeepingEstadoLimpieza::ESTADO_LIMPIA_INSPECCIONADA]];
$repoSim5->mantenimientoSimulado = [[
    'id' => 301,
    'codigo' => 'OT-2026-0301',
    'unidad_id' => 10,
    'tipo' => 'CORRECTIVO',
    'prioridad' => 'URGENTE',
    'estado' => 'EN_PROCESO',
    'requiere_bloqueo' => 1,
    'fecha_bloqueo_inicio' => $fHoy,
    'fecha_bloqueo_fin' => $fEn3Dias,
    'descripcion_trabajo' => 'Reparación de tubería rota e inundación',
]];

$servicio5 = new TapeChartServicio($repoSim5, new PropiedadRepositorioSimulado());
$proy5 = $servicio5->obtenerProyeccion(1, $fHoy, $fEn7Dias);
$celdaHoy5 = $proy5->obtenerFilasUnidades()[0]->obtenerCelda($fHoy);
$kpis5 = $proy5->obtenerKpisHoy();

afirmar(
    $celdaHoy5->obtenerEstadoPrincipal() === TapeChartCelda::ESTADO_MANTENIMIENTO_OOO &&
    $celdaHoy5->obtenerCodigoReferencia() === 'OT-2026-0301' &&
    $kpis5['fuera_servicio_ooo'] === 1 &&
    $kpis5['vacantes_listas_vr'] === 0,
    "Caso 5: Orden de mantenimiento OOO (requiere_bloqueo = 1) inhabilita físicamente la unidad en calendario y KPIs"
);

// -----------------------------------------------------------------------------
// CASO 6: Mantenimiento leve no bloqueante (requiere_bloqueo = 0)
// -----------------------------------------------------------------------------
echo "\n--- CASO 6: Mantenimiento leve no bloqueante ---\n";
$repoSim6 = new TapeChartRepositorioSimulado();
$repoSim6->unidadesSimuladas = [$unidadBase];
$repoSim6->limpiezaSimulada = [['unidad_id' => 10, 'estado_limpieza' => HousekeepingEstadoLimpieza::ESTADO_LIMPIA_INSPECCIONADA]];
$repoSim6->mantenimientoSimulado = [[
    'id' => 302,
    'codigo' => 'OT-2026-0302',
    'unidad_id' => 10,
    'tipo' => 'PREVENTIVO',
    'prioridad' => 'BAJA',
    'estado' => 'EN_PROCESO',
    'requiere_bloqueo' => 0,
    'fecha_bloqueo_inicio' => null,
    'fecha_bloqueo_fin' => null,
    'descripcion_trabajo' => 'Ajuste menor de bisagra de placard',
]];

$servicio6 = new TapeChartServicio($repoSim6, new PropiedadRepositorioSimulado());
$proy6 = $servicio6->obtenerProyeccion(1, $fHoy, $fEn7Dias);
$celdaHoy6 = $proy6->obtenerFilasUnidades()[0]->obtenerCelda($fHoy);
$kpis6 = $proy6->obtenerKpisHoy();

afirmar(
    $celdaHoy6->obtenerEstadoPrincipal() === TapeChartCelda::ESTADO_VACANTE &&
    $celdaHoy6->obtenerSubestado() === 'VR' &&
    in_array('INCIDENCIA_TECNICA', $celdaHoy6->obtenerIndicadoresSecundarios(), true) &&
    $kpis6['fuera_servicio_ooo'] === 0 &&
    $kpis6['vacantes_listas_vr'] === 1,
    "Caso 6: Mantenimiento leve (requiere_bloqueo = 0) no bloquea la celda y conserva indicador secundario INCIDENCIA_TECNICA"
);

// -----------------------------------------------------------------------------
// CASO 7: Vacante Hoy en VR (Limpia e inspeccionada)
// -----------------------------------------------------------------------------
echo "\n--- CASO 7: Vacante Hoy en VR (Limpia e inspeccionada) ---\n";
$repoSim7 = new TapeChartRepositorioSimulado();
$repoSim7->unidadesSimuladas = [$unidadBase];
$repoSim7->limpiezaSimulada = [['unidad_id' => 10, 'estado_limpieza' => HousekeepingEstadoLimpieza::ESTADO_LIMPIA_INSPECCIONADA]];

$servicio7 = new TapeChartServicio($repoSim7, new PropiedadRepositorioSimulado());
$proy7 = $servicio7->obtenerProyeccion(1, $fHoy, $fEn7Dias);
$celdaHoy7 = $proy7->obtenerFilasUnidades()[0]->obtenerCelda($fHoy);
$kpis7 = $proy7->obtenerKpisHoy();

afirmar(
    $celdaHoy7->obtenerEstadoPrincipal() === TapeChartCelda::ESTADO_VACANTE &&
    $celdaHoy7->obtenerSubestado() === 'VR' &&
    $celdaHoy7->obtenerClaseColor() === 'tape-celda-vr' &&
    $kpis7['vacantes_listas_vr'] === 1,
    "Caso 7: Habitación desocupada con limpieza LIMPIA_INSPECCIONADA proyecta condición VR (Limpia y lista)"
);

// -----------------------------------------------------------------------------
// CASO 8: Vacante Hoy en VD (Sucia)
// -----------------------------------------------------------------------------
echo "\n--- CASO 8: Vacante Hoy en VD (Sucia) ---\n";
$repoSim8 = new TapeChartRepositorioSimulado();
$repoSim8->unidadesSimuladas = [$unidadBase];
$repoSim8->limpiezaSimulada = [['unidad_id' => 10, 'estado_limpieza' => HousekeepingEstadoLimpieza::ESTADO_SUCIA]];

$servicio8 = new TapeChartServicio($repoSim8, new PropiedadRepositorioSimulado());
$proy8 = $servicio8->obtenerProyeccion(1, $fHoy, $fEn7Dias);
$celdaHoy8 = $proy8->obtenerFilasUnidades()[0]->obtenerCelda($fHoy);
$kpis8 = $proy8->obtenerKpisHoy();

afirmar(
    $celdaHoy8->obtenerEstadoPrincipal() === TapeChartCelda::ESTADO_VACANTE &&
    $celdaHoy8->obtenerSubestado() === 'VD' &&
    $celdaHoy8->obtenerClaseColor() === 'tape-celda-vd' &&
    $kpis8['vacantes_sucias_vd'] === 1,
    "Caso 8: Habitación desocupada con estado SUCIA proyecta condición VD (Sucia / pendiente)"
);

// -----------------------------------------------------------------------------
// CASO 9: Vacante Hoy en VCL (En limpieza o por inspeccionar)
// -----------------------------------------------------------------------------
echo "\n--- CASO 9: Vacante Hoy en VCL (En proceso de limpieza) ---\n";
$repoSim9 = new TapeChartRepositorioSimulado();
$repoSim9->unidadesSimuladas = [$unidadBase];
$repoSim9->limpiezaSimulada = [['unidad_id' => 10, 'estado_limpieza' => HousekeepingEstadoLimpieza::ESTADO_EN_LIMPIEZA]];

$servicio9 = new TapeChartServicio($repoSim9, new PropiedadRepositorioSimulado());
$proy9 = $servicio9->obtenerProyeccion(1, $fHoy, $fEn7Dias);
$celdaHoy9 = $proy9->obtenerFilasUnidades()[0]->obtenerCelda($fHoy);
$kpis9 = $proy9->obtenerKpisHoy();

afirmar(
    $celdaHoy9->obtenerEstadoPrincipal() === TapeChartCelda::ESTADO_VACANTE &&
    $celdaHoy9->obtenerSubestado() === 'VCL' &&
    $celdaHoy9->obtenerClaseColor() === 'tape-celda-vcl' &&
    $kpis9['vacantes_limpieza_vcl'] === 1,
    "Caso 9: Habitación en proceso de limpieza proyecta condición VCL (En limpieza activa)"
);

// -----------------------------------------------------------------------------
// CASO 10: Colisión OOO bloqueante + Reserva confirmada simultánea
// -----------------------------------------------------------------------------
echo "\n--- CASO 10: Colisión crítica OOO bloqueante + Reserva confirmada ---\n";
$repoSim10 = new TapeChartRepositorioSimulado();
$repoSim10->unidadesSimuladas = [$unidadBase];
$repoSim10->limpiezaSimulada = [['unidad_id' => 10, 'estado_limpieza' => HousekeepingEstadoLimpieza::ESTADO_LIMPIA_INSPECCIONADA]];
// Reserva confirmada para hoy y mañana
$repoSim10->reservasSimuladas = [[
    'id' => 77,
    'codigo' => 'RES-COLISION-0077',
    'estado' => 'CONFIRMADA',
    'fecha_creacion' => $fHoy . ' 08:00:00',
    'expira_en' => null,
    'moneda_codigo' => 'PEN',
    'total' => '250.00',
    'persona_titular_id' => 5,
    'titular_nombre_completo' => 'Flores Ana',
    'titular_apellido' => 'Flores',
    'titular_nombres' => 'Ana',
    'titular_documento' => '44556677',
    'reserva_unidad_id' => 771,
    'unidad_id' => 10,
    'fecha_entrada' => $fHoy,
    'fecha_salida' => $fPasado,
    'noches' => 2,
    'precio_por_noche' => '125.00',
    'total_linea' => '250.00',
]];
// Mantenimiento bloqueante coincidente en las mismas fechas
$repoSim10->mantenimientoSimulado = [[
    'id' => 999,
    'codigo' => 'OT-COLISION-0999',
    'unidad_id' => 10,
    'tipo' => 'CORRECTIVO',
    'prioridad' => 'URGENTE',
    'estado' => 'EN_PROCESO',
    'requiere_bloqueo' => 1,
    'fecha_bloqueo_inicio' => $fHoy,
    'fecha_bloqueo_fin' => $fPasado,
    'descripcion_trabajo' => 'Cierre forzado por falla eléctrica general',
]];

$servicio10 = new TapeChartServicio($repoSim10, new PropiedadRepositorioSimulado());
$proy10 = $servicio10->obtenerProyeccion(1, $fHoy, $fEn7Dias);
$celdaHoy10 = $proy10->obtenerFilasUnidades()[0]->obtenerCelda($fHoy);

afirmar(
    $celdaHoy10->obtenerEstadoPrincipal() === TapeChartCelda::ESTADO_MANTENIMIENTO_OOO &&
    $celdaHoy10->tieneConflictos() === true &&
    in_array('AFECTADA_POR_MANTENIMIENTO_OOO', $celdaHoy10->obtenerConflictos(), true),
    "Caso 10: Jerarquía visual otorga prioridad a OOO bloqueante pero preserva bandera de conflicto AFECTADA_POR_MANTENIMIENTO_OOO"
);

echo "\n====================================================================\n";
echo " RESUMEN: {$pasadas} / {$total} PRUEBAS PASADAS\n";
if ($fallidas > 0) {
    echo " FALLIDAS: {$fallidas}\n";
    foreach ($errores as $err) {
        echo "   - {$err}\n";
    }
    exit(1);
}
echo " RESULTADO: 10/10 REGLAS DE DOMINIO Y PROYECCIÓN PASS EXITOSA\n";
echo "====================================================================\n";
