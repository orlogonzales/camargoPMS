<?php

declare(strict_types=1);

/**
 * Suite de Verificación TAPE-CHART-1 — Pruebas de Rendimiento y Queries O(1) (6 Casos)
 *
 * Principios vinculantes de D-084:
 * - O(1) en número de consultas SQL respecto al número de celdas (cero antipatrón N x M).
 * - Rendimiento sub-segundo en procesamiento en memoria.
 * - Escenarios de estrés: 20x7 (140 celdas), 20x14 (280 celdas), 20x30 (600 celdas),
 *   50x30 (1,500 celdas), 100x30 (3,000 celdas).
 * - Demostración de invarianza de queries ante crecimiento de unidades y días.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CamargoPMS\Modelos\HousekeepingEstadoLimpieza;
use CamargoPMS\Modelos\Propiedad;
use CamargoPMS\Modelos\TapeChartProyeccion;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Repositorios\PropiedadRepositorio;
use CamargoPMS\Repositorios\TapeChartRepositorio;
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
echo " CAMARGO PMS — PRUEBAS DE RENDIMIENTO Y QUERIES O(1) (6 CASOS)\n";
echo " Tape Chart y Rack Hotelero Unificado (TAPE-CHART-1 / D-084)\n";
echo "====================================================================\n\n";

/**
 * Repositorio instrumentado para conteo estricto de queries y generación de volumen sintético.
 */
class TapeChartRepositorioInstrumentado extends TapeChartRepositorio
{
    public int $conteoConsultas = 0;
    private int $cantidadUnidadesSinteticas;

    public function __construct(int $cantidadUnidadesSinteticas = 20)
    {
        $this->cantidadUnidadesSinteticas = $cantidadUnidadesSinteticas;
    }

    public function obtenerUnidadesPropiedad(int $propiedadId, ?int $tipoUnidadId = null, ?string $pisoNivel = null): array
    {
        $this->conteoConsultas++;
        $unidades = [];
        for ($i = 1; $i <= $this->cantidadUnidadesSinteticas; $i++) {
            $piso = (string) (intdiv($i - 1, 10) + 1);
            $num = str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $unidades[] = [
                'id' => $i,
                'codigo' => $num,
                'nombre' => "Habitación {$num}",
                'propiedad_id' => $propiedadId,
                'propiedad_nombre' => 'Hotel Stress Test',
                'propiedad_zona_horaria' => 'America/Lima',
                'tipo_unidad_id' => 1,
                'tipo_unidad_nombre' => 'HABITACION',
                'piso_nivel' => $piso,
                'capacidad_personas' => 2,
                'estado' => 'ACTIVO',
            ];
        }
        return $unidades;
    }

    public function obtenerInventarioRango(int $propiedadId, string $fechaDesde, string $fechaHasta): array
    {
        $this->conteoConsultas++;
        return [];
    }

    public function obtenerReservasEnRango(int $propiedadId, string $fechaDesde, string $fechaHasta): array
    {
        $this->conteoConsultas++;
        $reservas = [];
        // Generar 1 reserva cada 3 unidades
        for ($i = 1; $i <= $this->cantidadUnidadesSinteticas; $i += 3) {
            $reservas[] = [
                'id' => 1000 + $i,
                'codigo' => "RES-STRESS-{$i}",
                'estado' => 'CONFIRMADA',
                'fecha_creacion' => $fechaDesde . ' 12:00:00',
                'expira_en' => null,
                'moneda_codigo' => 'PEN',
                'total' => '500.00',
                'persona_titular_id' => $i,
                'titular_nombre_completo' => "Huésped Sintético {$i}",
                'titular_apellido' => "Sintético {$i}",
                'titular_nombres' => "Huésped",
                'titular_documento' => "1000000{$i}",
                'reserva_unidad_id' => 2000 + $i,
                'unidad_id' => $i,
                'fecha_entrada' => $fechaDesde,
                'fecha_salida' => $fechaHasta,
                'noches' => 5,
                'precio_por_noche' => '100.00',
                'total_linea' => '500.00',
            ];
        }
        return $reservas;
    }

    public function obtenerEstadiasEnRango(int $propiedadId, string $fechaDesde, string $fechaHasta): array
    {
        $this->conteoConsultas++;
        $estadias = [];
        // Generar 1 estadía en curso cada 4 unidades
        for ($i = 2; $i <= $this->cantidadUnidadesSinteticas; $i += 4) {
            $estadias[] = [
                'id' => 3000 + $i,
                'codigo' => "EST-STRESS-{$i}",
                'reserva_id' => null,
                'reserva_unidad_id' => null,
                'unidad_id' => $i,
                'fecha_entrada' => $fechaDesde,
                'fecha_salida_programada' => $fechaHasta,
                'fecha_salida_real' => null,
                'estado' => 'EN_CURSO',
                'noches' => 5,
                'reserva_codigo' => null,
                'titular_reserva_nombre' => null,
                'titular_huesped_responsable' => "Alojado {$i}",
                'responsable_apellido' => "Alojado {$i}",
                'responsable_nombres' => "Cliente",
                'responsable_documento' => "2000000{$i}",
            ];
        }
        return $estadias;
    }

    public function obtenerArrendamientosEnRango(int $propiedadId, string $fechaDesde, string $fechaHasta): array
    {
        $this->conteoConsultas++;
        return [];
    }

    public function obtenerMantenimientoEnRango(int $propiedadId, string $fechaDesde, string $fechaHasta): array
    {
        $this->conteoConsultas++;
        $mant = [];
        // 1 orden OOO en unidad 5 si existe
        if ($this->cantidadUnidadesSinteticas >= 5) {
            $mant[] = [
                'id' => 9005,
                'codigo' => 'OT-STRESS-05',
                'unidad_id' => 5,
                'tipo' => 'CORRECTIVO',
                'prioridad' => 'ALTA',
                'estado' => 'EN_PROCESO',
                'requiere_bloqueo' => 1,
                'fecha_bloqueo_inicio' => $fechaDesde,
                'fecha_bloqueo_fin' => $fechaHasta,
                'descripcion_trabajo' => 'Mantenimiento de prueba de estrés',
            ];
        }
        return $mant;
    }

    public function obtenerLimpiezaActual(int $propiedadId): array
    {
        $this->conteoConsultas++;
        $limp = [];
        for ($i = 1; $i <= $this->cantidadUnidadesSinteticas; $i++) {
            $limp[] = [
                'unidad_id' => $i,
                'estado_limpieza' => ($i % 2 === 0) ? HousekeepingEstadoLimpieza::ESTADO_LIMPIA_INSPECCIONADA : HousekeepingEstadoLimpieza::ESTADO_SUCIA,
                'tarea_activa_id' => null,
                'ultima_limpieza_en' => null,
                'ultima_inspeccion_en' => null,
                'tarea_codigo' => null,
                'tarea_tipo' => null,
                'tarea_prioridad' => null,
                'tarea_estado' => null,
                'camarera_nombre' => null,
            ];
        }
        return $limp;
    }
}

class PropiedadRepoMock extends PropiedadRepositorio
{
    public function __construct()
    {
    }
    public function buscarPorId(int $id): ?Propiedad
    {
        return new Propiedad(
            id: $id,
            codigo: 'PROP-STRESS',
            nombre: 'Hotel de Estrés',
            estado: 'ACTIVO',
            zonaHoraria: 'America/Lima'
        );
    }
}

$tz = new DateTimeZone('America/Lima');
$dtBase = new DateTimeImmutable('now', $tz);
$fInicio = $dtBase->format('Y-m-d');
$f7d = $dtBase->modify('+7 days')->format('Y-m-d');
$f14d = $dtBase->modify('+14 days')->format('Y-m-d');
$f30d = $dtBase->modify('+30 days')->format('Y-m-d');

// -----------------------------------------------------------------------------
// ESCENARIO 1: 20 Unidades x 7 Días (140 celdas)
// -----------------------------------------------------------------------------
echo "--- ESCENARIO 1: 20 Unidades x 7 Días (140 celdas) ---\n";
$repo1 = new TapeChartRepositorioInstrumentado(20);
$serv1 = new TapeChartServicio($repo1, new PropiedadRepoMock());

$t0 = microtime(true);
$proy1 = $serv1->obtenerProyeccion(1, $fInicio, $f7d);
$tiempoMs1 = (microtime(true) - $t0) * 1000;

$totalCeldas1 = 20 * 7;
afirmar(
    $proy1 instanceof TapeChartProyeccion &&
    $repo1->conteoConsultas <= 7 &&
    $tiempoMs1 < 100.0,
    sprintf("Escenario 1: 140 celdas calculadas en %.2f ms (límite 100ms) con exactamente %d queries PDO (<= 7)", $tiempoMs1, $repo1->conteoConsultas)
);

// -----------------------------------------------------------------------------
// ESCENARIO 2: 20 Unidades x 14 Días (280 celdas)
// -----------------------------------------------------------------------------
echo "\n--- ESCENARIO 2: 20 Unidades x 14 Días (280 celdas) ---\n";
$repo2 = new TapeChartRepositorioInstrumentado(20);
$serv2 = new TapeChartServicio($repo2, new PropiedadRepoMock());

$t0 = microtime(true);
$proy2 = $serv2->obtenerProyeccion(1, $fInicio, $f14d);
$tiempoMs2 = (microtime(true) - $t0) * 1000;

afirmar(
    $proy2 instanceof TapeChartProyeccion &&
    $repo2->conteoConsultas <= 7 &&
    $tiempoMs2 < 150.0,
    sprintf("Escenario 2: 280 celdas calculadas en %.2f ms (límite 150ms) con exactamente %d queries PDO (<= 7)", $tiempoMs2, $repo2->conteoConsultas)
);

// -----------------------------------------------------------------------------
// ESCENARIO 3: 20 Unidades x 30 Días (600 celdas)
// -----------------------------------------------------------------------------
echo "\n--- ESCENARIO 3: 20 Unidades x 30 Días (600 celdas) ---\n";
$repo3 = new TapeChartRepositorioInstrumentado(20);
$serv3 = new TapeChartServicio($repo3, new PropiedadRepoMock());

$t0 = microtime(true);
$proy3 = $serv3->obtenerProyeccion(1, $fInicio, $f30d);
$tiempoMs3 = (microtime(true) - $t0) * 1000;

afirmar(
    $proy3 instanceof TapeChartProyeccion &&
    $repo3->conteoConsultas <= 7 &&
    $tiempoMs3 < 250.0,
    sprintf("Escenario 3: 600 celdas calculadas en %.2f ms (límite 250ms) con exactamente %d queries PDO (<= 7)", $tiempoMs3, $repo3->conteoConsultas)
);

// -----------------------------------------------------------------------------
// ESCENARIO 4: 50 Unidades x 30 Días (1,500 celdas)
// -----------------------------------------------------------------------------
echo "\n--- ESCENARIO 4: 50 Unidades x 30 Días (1,500 celdas) ---\n";
$repo4 = new TapeChartRepositorioInstrumentado(50);
$serv4 = new TapeChartServicio($repo4, new PropiedadRepoMock());

$t0 = microtime(true);
$proy4 = $serv4->obtenerProyeccion(1, $fInicio, $f30d);
$tiempoMs4 = (microtime(true) - $t0) * 1000;

afirmar(
    $proy4 instanceof TapeChartProyeccion &&
    $repo4->conteoConsultas <= 7 &&
    $tiempoMs4 < 400.0,
    sprintf("Escenario 4: 1,500 celdas calculadas en %.2f ms (límite 400ms) con exactamente %d queries PDO (<= 7)", $tiempoMs4, $repo4->conteoConsultas)
);

// -----------------------------------------------------------------------------
// ESCENARIO 5: 100 Unidades x 30 Días (3,000 celdas)
// -----------------------------------------------------------------------------
echo "\n--- ESCENARIO 5: 100 Unidades x 30 Días (3,000 celdas) ---\n";
$repo5 = new TapeChartRepositorioInstrumentado(100);
$serv5 = new TapeChartServicio($repo5, new PropiedadRepoMock());

$t0 = microtime(true);
$proy5 = $serv5->obtenerProyeccion(1, $fInicio, $f30d);
$tiempoMs5 = (microtime(true) - $t0) * 1000;

afirmar(
    $proy5 instanceof TapeChartProyeccion &&
    $repo5->conteoConsultas <= 7 &&
    $tiempoMs5 < 700.0,
    sprintf("Escenario 5: 3,000 celdas calculadas en %.2f ms (límite 700ms) con exactamente %d queries PDO (<= 7)", $tiempoMs5, $repo5->conteoConsultas)
);

// -----------------------------------------------------------------------------
// ESCENARIO 6: Demostración Matemática de Invarianza O(1) en Consultas SQL
// -----------------------------------------------------------------------------
echo "\n--- ESCENARIO 6: Invarianza O(1) de Consultas SQL ---\n";
// Se compara el conteo de queries entre:
// Escenario A: 20 unidades x 7 días (140 celdas)
// Escenario B: 100 unidades x 30 días (3,000 celdas)
// Factor de escala en celdas: 3,000 / 140 = 21.4x
$escalaCeldas = 3000 / 140;
$queriesA = $repo1->conteoConsultas;
$queriesB = $repo5->conteoConsultas;

afirmar(
    $queriesA === 7 && $queriesB === 7 && ($queriesA === $queriesB),
    sprintf(
        "Escenario 6: Invarianza O(1) certificada: %.1fx aumento en celdas (140 -> 3,000) produjo exactamente 0 consultas adicionales (%d == %d == 7 queries)",
        $escalaCeldas,
        $queriesA,
        $queriesB
    )
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
echo " RESULTADO: 6/6 PRUEBAS DE RENDIMIENTO Y QUERIES O(1) PASS EXITOSA\n";
echo "====================================================================\n";
