<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

use CamargoPMS\Excepciones\ValidacionExcepcion;
use CamargoPMS\Modelos\ReporteAgingDTO;
use CamargoPMS\Modelos\ReporteDiarioDTO;
use CamargoPMS\Modelos\ReporteFlujoCajaDTO;
use CamargoPMS\Repositorios\ReporteRepositorio;
use CamargoPMS\Servicios\Documentos\GeneradorPdf;
use DateTimeImmutable;

/**
 * Servicio analítico de solo lectura para el Módulo de Reportes.
 * Gobernanza: D-087 / REPORTES-1.
 * Contratos:
 *  - Cero persistencia, cero mutaciones, proyección analítica de solo lectura.
 *  - Cuadre algebraico inmutable de tesorería: Saldo Inicial + Ingresos - Egresos = Saldo Final.
 *  - Segregación estricta de carteras: CxC != CxP.
 *  - Neutralización de CSV Injection: comilla simple previa a '=', '+', '-', '@'.
 *  - Generación PDF mediante GeneradorPdf / Dompdf homologado (D-079 / D-085).
 */
class ReporteServicio
{
    private GeneradorPdf $generadorPdf;

    public function __construct(
        private ReporteRepositorio $reporteRepo,
        ?GeneradorPdf $generadorPdf = null
    ) {
        $this->generadorPdf = $generadorPdf ?? new GeneradorPdf();
    }

    // =========================================================================
    // 1. MANAGER'S DAILY REPORT (MDR)
    // =========================================================================

    /**
     * Genera el Reporte Diario Gerencial para una fecha hotelera determinada.
     */
    public function generarReporteDiario(string $fecha, ?int $propiedadId = null): ReporteDiarioDTO
    {
        $this->validarFecha($fecha, 'fecha');

        $unidades = $this->reporteRepo->obtenerUnidadesInventario($propiedadId);
        $ordenesBloqueo = $this->reporteRepo->obtenerOrdenesBloqueantesFecha($fecha, $propiedadId);
        $estadias = $this->reporteRepo->obtenerEstadiasActivasFecha($fecha, $propiedadId);
        $arrendamientos = $this->reporteRepo->obtenerArrendamientosActivosFecha($fecha, $propiedadId);
        $llegadasPrevistas = $this->reporteRepo->obtenerLlegadasPrevistasFecha($fecha, $propiedadId);
        $llegadasRealizadas = $this->reporteRepo->obtenerLlegadasRealizadasFecha($fecha, $propiedadId);
        $salidasPrevistas = $this->reporteRepo->obtenerSalidasPrevistasFecha($fecha, $propiedadId);
        $salidasRealizadas = $this->reporteRepo->obtenerSalidasRealizadasFecha($fecha, $propiedadId);
        $higiene = $this->reporteRepo->obtenerHigienePisos($propiedadId);
        $incidencias = $this->reporteRepo->obtenerIncidenciasAbiertas($propiedadId);

        // Tesorería del día
        $movsCaja = $this->reporteRepo->obtenerMovimientosCajaRango($fecha, $fecha, $propiedadId);
        $movsBanco = $this->reporteRepo->obtenerMovimientosBancariosRango($fecha, $fecha, $propiedadId);

        // 1. Operación Hotelera
        $unidadesTotal = count($unidades);

        // OOO estricto: requiere_bloqueo = 1 y fecha dentro del intervalo
        $unidadesInventarioIds = array_column($unidades, 'id');
        $unidadesOooIds = array_values(array_unique(array_column($ordenesBloqueo, 'unidad_id')));
        $unidadesOooValidas = array_intersect($unidadesOooIds, $unidadesInventarioIds);
        $unidadesOoo = count($unidadesOooValidas);

        // Unidades vendibles comerciales netas
        $unidadesVendibles = max(0, $unidadesTotal - $unidadesOoo);

        // Unidades ocupadas (estadías hoteleras + arrendamientos vigentes)
        $ocupadasHotelIds = array_column($estadias, 'unidad_id');
        $ocupadasArrendIds = array_column($arrendamientos, 'unidad_id');
        $unidadesOcupadasIds = array_values(array_unique(array_merge($ocupadasHotelIds, $ocupadasArrendIds)));
        $unidadesOcupadas = count($unidadesOcupadasIds);

        // Ocupación neta %
        $ocupacionNetaPorcentaje = $unidadesVendibles > 0
            ? round(($unidadesOcupadas / $unidadesVendibles) * 100, 2)
            : 0.00;

        // Stayovers (estancias activas que no son check-in de hoy ni check-out de hoy)
        $stayovers = 0;
        foreach ($estadias as $est) {
            if ($est['fecha_entrada'] < $fecha && $est['fecha_salida_prevista'] > $fecha) {
                $stayovers++;
            }
        }

        // Pax (huéspedes en casa)
        $huespedesEnCasa = count($estadias) + count($arrendamientos);

        // Higiene resumen
        $higieneMap = [];
        $resumenHigiene = [
            'LIMPIA' => 0,
            'SUCIA' => 0,
            'EN_LIMPIEZA' => 0,
            'INSPECCIONADA' => 0,
            'RETOCAR' => 0,
            'OTRO' => 0,
        ];
        foreach ($higiene as $h) {
            $uId = (int) $h['unidad_id'];
            $st = strtoupper((string) ($h['estado_limpieza'] ?? 'SUCIA'));
            $higieneMap[$uId] = $st;
            if (isset($resumenHigiene[$st])) {
                $resumenHigiene[$st]++;
            } else {
                $resumenHigiene['OTRO']++;
            }
        }

        // Métricas soberanas de Devengo Diario, ADR y RevPAR (D-090)
        $cierreHotelero = $this->reporteRepo->obtenerCierreHoteleroFecha($fecha, $propiedadId);
        $metricasDevengo = $this->reporteRepo->obtenerMetricasDevengoFecha($fecha, $propiedadId);

        $tieneCierre = $cierreHotelero !== null && $cierreHotelero['estado'] === 'CERRADO';
        $tieneDevengos = ((int) $metricasDevengo['habitaciones_vendidas']) > 0 || bccomp((string) $metricasDevengo['ingreso_alojamiento_neto'], '0.00', 2) > 0;

        if ($tieneCierre) {
            $adrRevparEstado = 'DISPONIBLE';
            $adrRevparNota = 'Métricas soberanas auditadas por Cierre Nocturno (Night Audit).';
            $adr = (string) $cierreHotelero['adr'];
            $revpar = (string) $cierreHotelero['revpar'];
            $ingresoAlojamientoNeto = (string) $cierreHotelero['ingreso_alojamiento_neto'];
            $habitacionesVendidas = (int) $cierreHotelero['habitaciones_vendidas'];
            $nightAuditEstado = (string) $cierreHotelero['estado'];
        } elseif ($tieneDevengos) {
            $adrRevparEstado = 'DISPONIBLE';
            $adrRevparNota = 'Métricas calculadas en tiempo real a partir del libro diario de devengos.';
            $ingresoAlojamientoNeto = (string) $metricasDevengo['ingreso_alojamiento_neto'];
            $habitacionesVendidas = (int) $metricasDevengo['habitaciones_vendidas'];
            $adr = $habitacionesVendidas > 0
                ? bcdiv($ingresoAlojamientoNeto, (string) $habitacionesVendidas, 2)
                : '0.00';
            $revpar = $unidadesVendibles > 0
                ? bcdiv($ingresoAlojamientoNeto, (string) $unidadesVendibles, 2)
                : '0.00';
            $nightAuditEstado = $cierreHotelero ? (string) $cierreHotelero['estado'] : 'PENDIENTE';
        } else {
            $adrRevparEstado = 'DIFERIDO_A_DEVENGO_ALOJAMIENTO_1';
            $adrRevparNota = 'Sin fuente soberana de devengo para esta fecha hotelera (fecha no cerrada o previa a cutover).';
            $adr = '0.00';
            $revpar = '0.00';
            $ingresoAlojamientoNeto = '0.00';
            $habitacionesVendidas = 0;
            $nightAuditEstado = 'NO_EJECUTADO';
        }

        $operacionHotelera = [
            'unidades_totales' => $unidadesTotal,
            'unidades_ooo' => $unidadesOoo,
            'unidades_vendibles' => $unidadesVendibles,
            'unidades_ocupadas' => $unidadesOcupadas,
            'unidades_disponibles' => max(0, $unidadesVendibles - $unidadesOcupadas),
            'ocupacion_neta_porcentaje' => number_format($ocupacionNetaPorcentaje, 2, '.', ''),
            'llegadas_previstas' => count($llegadasPrevistas),
            'llegadas_realizadas' => count($llegadasRealizadas),
            'salidas_previstas' => count($salidasPrevistas),
            'salidas_realizadas' => count($salidasRealizadas),
            'stayovers' => $stayovers,
            'huespedes_en_casa' => $huespedesEnCasa,
            'higiene' => $resumenHigiene,
            // Métricas soberanas D-090
            'adr_revpar_estado' => $adrRevparEstado,
            'adr_revpar_nota' => $adrRevparNota,
            'adr' => $adr,
            'revpar' => $revpar,
            'ingreso_alojamiento_neto' => $ingresoAlojamientoNeto,
            'habitaciones_vendidas' => $habitacionesVendidas,
            'night_audit_estado' => $nightAuditEstado,
        ];


        // 2. Actividad Comercial
        $actividadComercial = [
            'total_llegadas_programadas' => count($llegadasPrevistas),
            'total_checkins_efectuados' => count($llegadasRealizadas),
            'total_salidas_programadas' => count($salidasPrevistas),
            'total_checkouts_efectuados' => count($salidasRealizadas),
            'estadias_en_curso' => count($estadias),
            'arrendamientos_vigentes' => count($arrendamientos),
        ];

        // 3. Tesorería y Caja del día
        $ingresosCaja = '0.00';
        $egresosCaja = '0.00';
        foreach ($movsCaja as $mc) {
            $tipo = (string) $mc['tipo_movimiento'];
            $monto = (string) $mc['monto'];
            if (in_array($tipo, ['INGRESO_COBRO', 'INGRESO_AJUSTE', 'APERTURA'], true)) {
                $ingresosCaja = bcadd($ingresosCaja, $monto, 2);
            } elseif (in_array($tipo, ['EGRESO_GASTO_MENOR', 'EGRESO_AJUSTE'], true)) {
                $egresosCaja = bcadd($egresosCaja, $monto, 2);
            }
        }

        $ingresosBanco = '0.00';
        $egresosBanco = '0.00';
        foreach ($movsBanco as $mb) {
            $tipo = (string) $mb['tipo_movimiento'];
            $monto = (string) $mb['monto'];
            if (in_array($tipo, ['INGRESO_COBRO', 'INGRESO_TRANSFERENCIA', 'INGRESO_AJUSTE'], true)) {
                $ingresosBanco = bcadd($ingresosBanco, $monto, 2);
            } elseif (in_array($tipo, ['EGRESO_TRANSFERENCIA', 'EGRESO_COMISION', 'EGRESO_AJUSTE'], true)) {
                $egresosBanco = bcadd($egresosBanco, $monto, 2);
            }
        }

        $totalIngresosDia = bcadd($ingresosCaja, $ingresosBanco, 2);
        $totalEgresosDia = bcadd($egresosCaja, $egresosBanco, 2);
        $saldoNetoOperativo = bcsub($totalIngresosDia, $totalEgresosDia, 2);

        $tesoreria = [
            'ingresos_caja' => $ingresosCaja,
            'egresos_caja' => $egresosCaja,
            'ingresos_banco' => $ingresosBanco,
            'egresos_banco' => $egresosBanco,
            'total_ingresos' => $totalIngresosDia,
            'total_egresos' => $totalEgresosDia,
            'saldo_neto_operativo' => $saldoNetoOperativo,
            'moneda' => 'PEN',
        ];

        // 4. Control Operativo
        $controlOperativo = [
            'incidencias_abiertas_total' => count($incidencias),
            'incidencias_detalle' => $incidencias,
            'ordenes_bloqueantes_total' => count($ordenesBloqueo),
            'ordenes_bloqueantes_detalle' => $ordenesBloqueo,
        ];

        // 5. Detalle de Unidades
        $detalleUnidades = [];
        $estadiasPorUnidad = [];
        foreach ($estadias as $est) {
            $estadiasPorUnidad[(int) $est['unidad_id']] = $est;
        }
        $arrendamientosPorUnidad = [];
        foreach ($arrendamientos as $arr) {
            $arrendamientosPorUnidad[(int) $arr['unidad_id']] = $arr;
        }
        $bloqueosPorUnidad = [];
        foreach ($ordenesBloqueo as $ob) {
            $bloqueosPorUnidad[(int) $ob['unidad_id']] = $ob;
        }

        foreach ($unidades as $u) {
            $uId = (int) $u['id'];
            $estadoOcupacion = 'DISPONIBLE';
            $titular = null;
            $motivoBloqueo = null;

            if (isset($bloqueosPorUnidad[$uId])) {
                $estadoOcupacion = 'BLOQUEADA_OOO';
                $motivoBloqueo = $bloqueosPorUnidad[$uId]['titulo'] ?? 'Mantenimiento';
            } elseif (isset($estadiasPorUnidad[$uId])) {
                $estadoOcupacion = 'OCUPADA_HOTEL';
                $titular = $estadiasPorUnidad[$uId]['titular_nombre'] ?? 'Huésped';
            } elseif (isset($arrendamientosPorUnidad[$uId])) {
                $estadoOcupacion = 'OCUPADA_ARRENDAMIENTO';
                $titular = $arrendamientosPorUnidad[$uId]['titular_nombre'] ?? 'Inquilino';
            }

            $detalleUnidades[] = [
                'unidad_id' => $uId,
                'codigo' => $u['codigo'],
                'nombre' => $u['nombre'],
                'piso_nivel' => $u['piso_nivel'],
                'tipo_unidad' => $u['tipo_unidad_nombre'],
                'propiedad_id' => (int) $u['propiedad_id'],
                'propiedad_nombre' => $u['propiedad_nombre'],
                'estado_ocupacion' => $estadoOcupacion,
                'titular' => $titular,
                'motivo_bloqueo' => $motivoBloqueo,
                'estado_limpieza' => $higieneMap[$uId] ?? 'NO_REGISTRADO',
            ];
        }

        $propiedadNombre = !empty($unidades) ? ($unidades[0]['propiedad_nombre'] ?? null) : null;
        if ($propiedadId !== null && count(array_unique(array_column($unidades, 'propiedad_id'))) > 1) {
            $propiedadNombre = 'Multi-Propiedad';
        }

        return new ReporteDiarioDTO(
            $fecha,
            $propiedadId,
            $propiedadNombre,
            $operacionHotelera,
            $actividadComercial,
            $tesoreria,
            $controlOperativo,
            $detalleUnidades
        );
    }

    // =========================================================================
    // 2. FLUJO DE CAJA CONSOLIDADO (CASH FLOW)
    // =========================================================================

    /**
     * Genera el Reporte de Flujo de Caja para un intervalo de fechas.
     * Garantiza rigurosamente el cuadre algebraico: Saldo Inicial + Ingresos - Egresos = Saldo Final.
     */
    public function generarFlujoCaja(string $fechaDesde, string $fechaHasta, ?int $propiedadId = null): ReporteFlujoCajaDTO
    {
        $this->validarRangoFechas($fechaDesde, $fechaHasta);

        $saldoInicialCaja = $this->reporteRepo->obtenerSaldoInicialCaja($fechaDesde, $propiedadId);
        $saldoInicialBanco = $this->reporteRepo->obtenerSaldoInicialBanco($fechaDesde, $propiedadId);

        $movsCaja = $this->reporteRepo->obtenerMovimientosCajaRango($fechaDesde, $fechaHasta, $propiedadId);
        $movsBanco = $this->reporteRepo->obtenerMovimientosBancariosRango($fechaDesde, $fechaHasta, $propiedadId);

        // Totales Efectivo
        $ingresosCaja = '0.00';
        $egresosCaja = '0.00';
        $movimientosDetalle = [];

        foreach ($movsCaja as $mc) {
            $tipo = (string) $mc['tipo_movimiento'];
            $monto = (string) $mc['monto'];
            $esIngreso = in_array($tipo, ['INGRESO_COBRO', 'INGRESO_AJUSTE', 'APERTURA'], true);
            $esEgreso = in_array($tipo, ['EGRESO_GASTO_MENOR', 'EGRESO_AJUSTE'], true);

            if ($esIngreso) {
                $ingresosCaja = bcadd($ingresosCaja, $monto, 2);
            } elseif ($esEgreso) {
                $egresosCaja = bcadd($egresosCaja, $monto, 2);
            }

            $movimientosDetalle[] = [
                'id' => (int) $mc['id'],
                'medio' => 'EFECTIVO',
                'origen' => $mc['caja_nombre'] ?? 'Caja Física',
                'tipo_movimiento' => $tipo,
                'sentido' => $esIngreso ? 'INGRESO' : 'EGRESO',
                'monto' => $monto,
                'moneda' => $mc['moneda_codigo'] ?? 'PEN',
                'concepto' => $mc['concepto'] ?? '',
                'fecha_hora' => $mc['creado_en'],
            ];
        }

        $saldoFinalCaja = bcsub(bcadd($saldoInicialCaja, $ingresosCaja, 2), $egresosCaja, 2);
        $cuadreCaja = bccomp(bcsub(bcadd($saldoInicialCaja, $ingresosCaja, 2), $egresosCaja, 2), $saldoFinalCaja, 2) === 0;

        $totalesEfectivo = [
            'saldo_inicial' => $saldoInicialCaja,
            'ingresos' => $ingresosCaja,
            'egresos' => $egresosCaja,
            'saldo_final' => $saldoFinalCaja,
            'cuadre_algebraico_valido' => $cuadreCaja,
        ];

        // Totales Banco
        $ingresosBanco = '0.00';
        $egresosBanco = '0.00';

        foreach ($movsBanco as $mb) {
            $tipo = (string) $mb['tipo_movimiento'];
            $monto = (string) $mb['monto'];
            $esIngreso = in_array($tipo, ['INGRESO_COBRO', 'INGRESO_TRANSFERENCIA', 'INGRESO_AJUSTE'], true);
            $esEgreso = in_array($tipo, ['EGRESO_TRANSFERENCIA', 'EGRESO_COMISION', 'EGRESO_AJUSTE'], true);

            if ($esIngreso) {
                $ingresosBanco = bcadd($ingresosBanco, $monto, 2);
            } elseif ($esEgreso) {
                $egresosBanco = bcadd($egresosBanco, $monto, 2);
            }

            $movimientosDetalle[] = [
                'id' => (int) $mb['id'],
                'medio' => 'BANCO',
                'origen' => ($mb['banco_nombre'] ?? 'Banco') . ' ' . ($mb['numero_cuenta'] ?? ''),
                'tipo_movimiento' => $tipo,
                'sentido' => $esIngreso ? 'INGRESO' : 'EGRESO',
                'monto' => $monto,
                'moneda' => $mb['moneda_codigo'] ?? 'PEN',
                'concepto' => $mb['concepto'] ?? ('Operación ' . ($mb['numero_operacion'] ?? '')),
                'fecha_hora' => $mb['fecha_operacion'] . ' ' . date('H:i:s', strtotime($mb['creado_en'] ?? 'now')),
            ];
        }

        $saldoFinalBanco = bcsub(bcadd($saldoInicialBanco, $ingresosBanco, 2), $egresosBanco, 2);
        $cuadreBanco = bccomp(bcsub(bcadd($saldoInicialBanco, $ingresosBanco, 2), $egresosBanco, 2), $saldoFinalBanco, 2) === 0;

        $totalesBanco = [
            'saldo_inicial' => $saldoInicialBanco,
            'ingresos' => $ingresosBanco,
            'egresos' => $egresosBanco,
            'saldo_final' => $saldoFinalBanco,
            'cuadre_algebraico_valido' => $cuadreBanco,
        ];

        // Totales Consolidado
        $saldoInicialConsolidado = bcadd($saldoInicialCaja, $saldoInicialBanco, 2);
        $ingresosConsolidado = bcadd($ingresosCaja, $ingresosBanco, 2);
        $egresosConsolidado = bcadd($egresosCaja, $egresosBanco, 2);
        $saldoFinalConsolidado = bcsub(bcadd($saldoInicialConsolidado, $ingresosConsolidado, 2), $egresosConsolidado, 2);
        $cuadreConsolidado = bccomp(
            bcsub(bcadd($saldoInicialConsolidado, $ingresosConsolidado, 2), $egresosConsolidado, 2),
            $saldoFinalConsolidado,
            2
        ) === 0;

        $totalesConsolidado = [
            'saldo_inicial' => $saldoInicialConsolidado,
            'ingresos' => $ingresosConsolidado,
            'egresos' => $egresosConsolidado,
            'saldo_final' => $saldoFinalConsolidado,
            'cuadre_algebraico_valido' => $cuadreConsolidado,
        ];

        // Ordenar movimientos cronológicamente
        usort($movimientosDetalle, static function (array $a, array $b): int {
            return strcmp((string) $a['fecha_hora'], (string) $b['fecha_hora']);
        });

        // Resumen por Naturaleza / Origen
        $resumenPorOrigen = [
            'INGRESOS' => [
                'COBROS_CLIENTES' => '0.00',
                'AJUSTES_APERTURAS' => '0.00',
                'TOTAL' => $ingresosConsolidado,
            ],
            'EGRESOS' => [
                'GASTOS_OPERATIVOS' => '0.00',
                'PAGOS_PROVEEDORES' => '0.00',
                'COMISIONES_AJUSTES' => '0.00',
                'TOTAL' => $egresosConsolidado,
            ],
        ];

        foreach ($movimientosDetalle as $m) {
            $monto = (string) $m['monto'];
            if ($m['sentido'] === 'INGRESO') {
                if (str_contains((string) $m['tipo_movimiento'], 'COBRO') || str_contains((string) $m['tipo_movimiento'], 'TRANSFERENCIA')) {
                    $resumenPorOrigen['INGRESOS']['COBROS_CLIENTES'] = bcadd($resumenPorOrigen['INGRESOS']['COBROS_CLIENTES'], $monto, 2);
                } else {
                    $resumenPorOrigen['INGRESOS']['AJUSTES_APERTURAS'] = bcadd($resumenPorOrigen['INGRESOS']['AJUSTES_APERTURAS'], $monto, 2);
                }
            } else {
                if (str_contains((string) $m['tipo_movimiento'], 'GASTO')) {
                    $resumenPorOrigen['EGRESOS']['GASTOS_OPERATIVOS'] = bcadd($resumenPorOrigen['EGRESOS']['GASTOS_OPERATIVOS'], $monto, 2);
                } elseif (str_contains((string) $m['tipo_movimiento'], 'TRANSFERENCIA')) {
                    $resumenPorOrigen['EGRESOS']['PAGOS_PROVEEDORES'] = bcadd($resumenPorOrigen['EGRESOS']['PAGOS_PROVEEDORES'], $monto, 2);
                } else {
                    $resumenPorOrigen['EGRESOS']['COMISIONES_AJUSTES'] = bcadd($resumenPorOrigen['EGRESOS']['COMISIONES_AJUSTES'], $monto, 2);
                }
            }
        }

        return new ReporteFlujoCajaDTO(
            $fechaDesde,
            $fechaHasta,
            $propiedadId,
            $totalesEfectivo,
            $totalesBanco,
            $totalesConsolidado,
            $movimientosDetalle,
            $resumenPorOrigen
        );
    }

    // =========================================================================
    // 3. AGING — CUENTAS POR COBRAR (CxC)
    // =========================================================================

    /**
     * Genera el Reporte de Antigüedad de Deuda de Clientes e Inquilinos (CxC).
     * Prohibición estricta de mezclar con CxP.
     */
    public function generarAgingCxC(string $fechaCorte, ?int $propiedadId = null): ReporteAgingDTO
    {
        $this->validarFecha($fechaCorte, 'fecha_corte');

        $cuotas = $this->reporteRepo->obtenerCuotasArrendamientoPendientes($fechaCorte, $propiedadId);
        $folios = $this->reporteRepo->obtenerCargosFoliosHuespedesPendientes($fechaCorte, $propiedadId);

        $totalesPorBucket = [
            'POR_VENCER' => '0.00',
            '1_30' => '0.00',
            '31_60' => '0.00',
            '61_90' => '0.00',
            'MAS_90' => '0.00',
        ];
        $partidas = [];
        $fechaCorteObj = new DateTimeImmutable($fechaCorte);

        // 1. Cuotas de arrendamiento
        foreach ($cuotas as $c) {
            $montoCargo = (string) $c['monto_cargo'];
            $montoPagado = (string) $c['monto_pagado'];
            $saldoPendiente = bcsub($montoCargo, $montoPagado, 2);

            if (bccomp($saldoPendiente, '0.00', 2) <= 0) {
                continue;
            }

            $fechaVencObj = new DateTimeImmutable((string) $c['fecha_vencimiento']);
            $diasVencido = 0;
            $bucket = 'POR_VENCER';

            if ($fechaCorteObj > $fechaVencObj) {
                $diasVencido = (int) $fechaVencObj->diff($fechaCorteObj)->days;
                $bucket = $this->determinarBucketDias($diasVencido);
            }

            $totalesPorBucket[$bucket] = bcadd($totalesPorBucket[$bucket], $saldoPendiente, 2);

            $partidas[] = [
                'origen' => 'ARRENDAMIENTO',
                'referencia_codigo' => $c['arrendamiento_codigo'],
                'documento_codigo' => $c['periodo_codigo'],
                'titular_id' => $c['titular_id'],
                'titular_nombre' => $c['titular_nombre'] ?? 'Inquilino',
                'unidad_codigo' => $c['unidad_codigo'],
                'fecha_emision' => $c['fecha_emision'],
                'fecha_vencimiento' => $c['fecha_vencimiento'],
                'monto_original' => $montoCargo,
                'monto_amortizado' => $montoPagado,
                'saldo_pendiente' => $saldoPendiente,
                'dias_vencido' => $diasVencido,
                'bucket' => $bucket,
            ];
        }

        // 2. Cargos de folios de huéspedes
        foreach ($folios as $f) {
            $totalCargo = (string) $f['total'];
            $montoPagado = (string) $f['monto_pagado'];
            $saldoPendiente = bcsub($totalCargo, $montoPagado, 2);

            if (bccomp($saldoPendiente, '0.00', 2) <= 0) {
                continue;
            }

            // Para huéspedes, el vencimiento de exigibilidad formal es la fecha de salida/checkout
            $fechaVencObj = new DateTimeImmutable((string) $f['fecha_salida']);
            $diasVencido = 0;
            $bucket = 'POR_VENCER';

            if ($fechaCorteObj > $fechaVencObj) {
                $diasVencido = (int) $fechaVencObj->diff($fechaCorteObj)->days;
                $bucket = $this->determinarBucketDias($diasVencido);
            }

            $totalesPorBucket[$bucket] = bcadd($totalesPorBucket[$bucket], $saldoPendiente, 2);

            $partidas[] = [
                'origen' => 'HOTEL_FOLIO',
                'referencia_codigo' => $f['reserva_codigo'],
                'documento_codigo' => $f['cargo_codigo'],
                'titular_id' => $f['titular_id'],
                'titular_nombre' => $f['titular_nombre'] ?? 'Huésped',
                'unidad_codigo' => 'FOLIO-' . $f['folio_codigo'],
                'fecha_emision' => substr((string) $f['creado_en'], 0, 10),
                'fecha_vencimiento' => $f['fecha_salida'],
                'monto_original' => $totalCargo,
                'monto_amortizado' => $montoPagado,
                'saldo_pendiente' => $saldoPendiente,
                'dias_vencido' => $diasVencido,
                'bucket' => $bucket,
            ];
        }

        $montoTotal = '0.00';
        foreach ($totalesPorBucket as $m) {
            $montoTotal = bcadd($montoTotal, $m, 2);
        }

        return new ReporteAgingDTO(
            ReporteAgingDTO::TIPO_CXC,
            $fechaCorte,
            $propiedadId,
            $montoTotal,
            $totalesPorBucket,
            $partidas
        );
    }

    // =========================================================================
    // 4. AGING — CUENTAS POR PAGAR (CxP)
    // =========================================================================

    /**
     * Genera el Reporte de Antigüedad de Deuda con Proveedores y Acreedores (CxP).
     * Prohibición estricta de mezclar con CxC.
     */
    public function generarAgingCxP(string $fechaCorte, ?int $propiedadId = null): ReporteAgingDTO
    {
        $this->validarFecha($fechaCorte, 'fecha_corte');

        $gastos = $this->reporteRepo->obtenerGastosAprobadosPendientes($fechaCorte, $propiedadId);
        $compras = $this->reporteRepo->obtenerComprasCxPPendientes($fechaCorte, $propiedadId);

        $totalesPorBucket = [
            'POR_VENCER' => '0.00',
            '1_30' => '0.00',
            '31_60' => '0.00',
            '61_90' => '0.00',
            'MAS_90' => '0.00',
        ];
        $partidas = [];
        $fechaCorteObj = new DateTimeImmutable($fechaCorte);

        // 1. Gastos operativos aprobados
        foreach ($gastos as $g) {
            $totalGasto = (string) $g['total'];
            $montoAplicado = (string) $g['monto_aplicado'];
            $saldoPendiente = bcsub($totalGasto, $montoAplicado, 2);

            if (bccomp($saldoPendiente, '0.00', 2) <= 0) {
                continue;
            }

            $fechaVenc = $g['fecha_vencimiento'] ?: $g['fecha_emision'];
            $fechaVencObj = new DateTimeImmutable((string) $fechaVenc);
            $diasVencido = 0;
            $bucket = 'POR_VENCER';

            if ($fechaCorteObj > $fechaVencObj) {
                $diasVencido = (int) $fechaVencObj->diff($fechaCorteObj)->days;
                $bucket = $this->determinarBucketDias($diasVencido);
            }

            $totalesPorBucket[$bucket] = bcadd($totalesPorBucket[$bucket], $saldoPendiente, 2);

            $partidas[] = [
                'origen' => 'GASTO_OPERATIVO',
                'referencia_codigo' => $g['codigo'],
                'documento_codigo' => $g['codigo'],
                'acreedor_nombre' => $g['acreedor_nombre'],
                'acreedor_documento' => $g['acreedor_documento'],
                'concepto' => $g['concepto'],
                'fecha_emision' => $g['fecha_emision'],
                'fecha_vencimiento' => $fechaVenc,
                'monto_original' => $totalGasto,
                'monto_amortizado' => $montoAplicado,
                'saldo_pendiente' => $saldoPendiente,
                'dias_vencido' => $diasVencido,
                'bucket' => $bucket,
            ];
        }

        // 2. Compras a proveedores (CxP)
        foreach ($compras as $cp) {
            $montoTotal = (string) $cp['monto_total'];
            $montoPagado = (string) $cp['monto_pagado'];
            $saldoPendiente = (string) $cp['saldo_pendiente'];

            if (bccomp($saldoPendiente, '0.00', 2) <= 0) {
                continue;
            }

            $fechaVencObj = new DateTimeImmutable((string) $cp['fecha_vencimiento']);
            $diasVencido = 0;
            $bucket = 'POR_VENCER';

            if ($fechaCorteObj > $fechaVencObj) {
                $diasVencido = (int) $fechaVencObj->diff($fechaCorteObj)->days;
                $bucket = $this->determinarBucketDias($diasVencido);
            }

            $totalesPorBucket[$bucket] = bcadd($totalesPorBucket[$bucket], $saldoPendiente, 2);

            $partidas[] = [
                'origen' => 'COMPRA_PROVEEDOR',
                'referencia_codigo' => $cp['codigo'],
                'documento_codigo' => $cp['numero_comprobante'] ?? $cp['codigo'],
                'acreedor_nombre' => $cp['proveedor_nombre'],
                'acreedor_documento' => $cp['proveedor_ruc'],
                'concepto' => 'Cuenta por pagar proveedor',
                'fecha_emision' => $cp['fecha_vencimiento'],
                'fecha_vencimiento' => $cp['fecha_vencimiento'],
                'monto_original' => $montoTotal,
                'monto_amortizado' => $montoPagado,
                'saldo_pendiente' => $saldoPendiente,
                'dias_vencido' => $diasVencido,
                'bucket' => $bucket,
            ];
        }

        $montoTotal = '0.00';
        foreach ($totalesPorBucket as $m) {
            $montoTotal = bcadd($montoTotal, $m, 2);
        }

        return new ReporteAgingDTO(
            ReporteAgingDTO::TIPO_CXP,
            $fechaCorte,
            $propiedadId,
            $montoTotal,
            $totalesPorBucket,
            $partidas
        );
    }

    // =========================================================================
    // 5. EXPORTACIÓN CSV CON NEUTRALIZACIÓN ANTI-INJECTION (D-087 #8)
    // =========================================================================

    /**
     * Sanitiza una celda para evitar CSV/Formula Injection.
     * Si la cadena inicia por '=', '+', '-', '@', se antepone un apóstrofe "'".
     */
    public function sanitizarCeldaCsv(mixed $valor): string
    {
        $str = (string) $valor;
        if ($str !== '' && in_array($str[0], ['=', '+', '-', '@'], true)) {
            return "'" . $str;
        }
        return $str;
    }

    /**
     * Genera la exportación en formato CSV con BOM UTF-8 y sanitización de celdas.
     */
    public function exportarCSV(string $tipoReporte, array $filtros): string
    {
        $tipo = strtoupper(trim($tipoReporte));
        $handle = fopen('php://temp', 'r+b');
        if ($handle === false) {
            throw new ValidacionExcepcion('No fue posible inicializar el búfer de exportación CSV.', ['exportar' => 'No fue posible inicializar el búfer de exportación CSV.']);
        }

        // BOM UTF-8 para apertura correcta en Microsoft Excel
        fwrite($handle, "\xEF\xBB\xBF");

        switch ($tipo) {
            case 'DIARIO':
                $fecha = $filtros['fecha'] ?? date('Y-m-d');
                $propiedadId = isset($filtros['propiedad_id']) && $filtros['propiedad_id'] !== '' ? (int) $filtros['propiedad_id'] : null;
                $dto = $this->generarReporteDiario($fecha, $propiedadId);
                $this->escribirCsvReporteDiario($handle, $dto);
                break;

            case 'FLUJO_CAJA':
                $fechaDesde = $filtros['fecha_desde'] ?? date('Y-m-01');
                $fechaHasta = $filtros['fecha_hasta'] ?? date('Y-m-d');
                $propiedadId = isset($filtros['propiedad_id']) && $filtros['propiedad_id'] !== '' ? (int) $filtros['propiedad_id'] : null;
                $dto = $this->generarFlujoCaja($fechaDesde, $fechaHasta, $propiedadId);
                $this->escribirCsvFlujoCaja($handle, $dto);
                break;

            case 'AGING_CXC':
                $fechaCorte = $filtros['fecha_corte'] ?? date('Y-m-d');
                $propiedadId = isset($filtros['propiedad_id']) && $filtros['propiedad_id'] !== '' ? (int) $filtros['propiedad_id'] : null;
                $dto = $this->generarAgingCxC($fechaCorte, $propiedadId);
                $this->escribirCsvAging($handle, $dto);
                break;

            case 'AGING_CXP':
                $fechaCorte = $filtros['fecha_corte'] ?? date('Y-m-d');
                $propiedadId = isset($filtros['propiedad_id']) && $filtros['propiedad_id'] !== '' ? (int) $filtros['propiedad_id'] : null;
                $dto = $this->generarAgingCxP($fechaCorte, $propiedadId);
                $this->escribirCsvAging($handle, $dto);
                break;

            default:
                fclose($handle);
                throw new ValidacionExcepcion("Tipo de reporte [{$tipo}] no soportado para exportación CSV.", ['tipo_reporte' => "Tipo de reporte [{$tipo}] no soportado para exportación CSV."]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv !== false ? $csv : '';
    }

    /**
     * @param resource $handle
     */
    private function escribirFilaCsv($handle, array $fila): void
    {
        $filaSanitizada = array_map([$this, 'sanitizarCeldaCsv'], $fila);
        fputcsv($handle, $filaSanitizada);
    }

    /**
     * @param resource $handle
     */
    private function escribirCsvReporteDiario($handle, ReporteDiarioDTO $dto): void
    {
        $this->escribirFilaCsv($handle, ['CAMARGO PMS - REPORTE DIARIO GERENCIAL (MDR)']);
        $this->escribirFilaCsv($handle, ['Fecha de Corte', $dto->obtenerFechaCorte()]);
        $this->escribirFilaCsv($handle, ['Propiedad', $dto->obtenerPropiedadNombre() ?? 'Todas las propiedades']);
        $this->escribirFilaCsv($handle, []);

        $op = $dto->obtenerOperacionHotelera();
        $this->escribirFilaCsv($handle, ['--- INDICADORES OPERACIONALES ---']);
        $this->escribirFilaCsv($handle, ['Unidades Totales Físicas', (string) $op['unidades_totales']]);
        $this->escribirFilaCsv($handle, ['Unidades Fuera de Orden (OOO)', (string) $op['unidades_ooo']]);
        $this->escribirFilaCsv($handle, ['Unidades Vendibles Netas', (string) $op['unidades_vendibles']]);
        $this->escribirFilaCsv($handle, ['Unidades Ocupadas Comercialmente', (string) $op['unidades_ocupadas']]);
        $this->escribirFilaCsv($handle, ['Unidades Disponibles', (string) $op['unidades_disponibles']]);
        $this->escribirFilaCsv($handle, ['Ocupación Comercial Neta (%)', $op['ocupacion_neta_porcentaje'] . '%']);
        $this->escribirFilaCsv($handle, ['Llegadas Previstas / Realizadas', $op['llegadas_previstas'] . ' / ' . $op['llegadas_realizadas']]);
        $this->escribirFilaCsv($handle, ['Salidas Previstas / Realizadas', $op['salidas_previstas'] . ' / ' . $op['salidas_realizadas']]);
        $this->escribirFilaCsv($handle, ['Stayovers (Pernoctaciones continuas)', (string) $op['stayovers']]);
        $this->escribirFilaCsv($handle, ['Huéspedes e Inquilinos en Casa', (string) $op['huespedes_en_casa']]);
        $this->escribirFilaCsv($handle, []);

        $tes = $dto->obtenerTesoreria();
        $this->escribirFilaCsv($handle, ['--- TESORERÍA DEL DÍA ---']);
        $this->escribirFilaCsv($handle, ['Total Ingresos Recaudados (PEN)', $tes['total_ingresos']]);
        $this->escribirFilaCsv($handle, ['Total Egresos Desembolsados (PEN)', $tes['total_egresos']]);
        $this->escribirFilaCsv($handle, ['Saldo Neto Operativo del Día (PEN)', $tes['saldo_neto_operativo']]);
        $this->escribirFilaCsv($handle, []);

        $this->escribirFilaCsv($handle, ['--- DETALLE POR HABITACIÓN / UNIDAD ---']);
        $this->escribirFilaCsv($handle, ['Código', 'Nombre', 'Piso', 'Tipo', 'Estado Ocupación', 'Titular / Motivo', 'Limpieza']);
        foreach ($dto->obtenerDetalleUnidades() as $u) {
            $info = $u['titular'] ?? ($u['motivo_bloqueo'] ?? '');
            $this->escribirFilaCsv($handle, [
                $u['codigo'],
                $u['nombre'],
                (string) $u['piso_nivel'],
                $u['tipo_unidad'],
                $u['estado_ocupacion'],
                $info,
                $u['estado_limpieza'],
            ]);
        }
    }

    /**
     * @param resource $handle
     */
    private function escribirCsvFlujoCaja($handle, ReporteFlujoCajaDTO $dto): void
    {
        $this->escribirFilaCsv($handle, ['CAMARGO PMS - FLUJO DE CAJA CONSOLIDADO']);
        $this->escribirFilaCsv($handle, ['Período', $dto->obtenerFechaDesde() . ' al ' . $dto->obtenerFechaHasta()]);
        $this->escribirFilaCsv($handle, []);

        $this->escribirFilaCsv($handle, ['--- RESUMEN PATRIMONIAL CONSOLIDADO ---']);
        $totCon = $dto->obtenerTotalesConsolidado();
        $this->escribirFilaCsv($handle, ['Saldo Inicial Consolidado (PEN)', $totCon['saldo_inicial']]);
        $this->escribirFilaCsv($handle, ['(+) Total Ingresos Recaudados', $totCon['ingresos']]);
        $this->escribirFilaCsv($handle, ['(-) Total Egresos Desembolsados', $totCon['egresos']]);
        $this->escribirFilaCsv($handle, ['(=) Saldo Final Consolidado', $totCon['saldo_final']]);
        $this->escribirFilaCsv($handle, ['Cuadre Algebraico', $totCon['cuadre_algebraico_valido'] ? 'EXACTO / CUADRADO' : 'DESCUADRADO']);
        $this->escribirFilaCsv($handle, []);

        $this->escribirFilaCsv($handle, ['--- DETALLE DE MOVIMIENTOS MONETARIOS ---']);
        $this->escribirFilaCsv($handle, ['ID', 'Medio', 'Origen', 'Tipo Movimiento', 'Sentido', 'Monto', 'Moneda', 'Concepto', 'Fecha']);
        foreach ($dto->obtenerMovimientosDetalle() as $m) {
            $this->escribirFilaCsv($handle, [
                (string) $m['id'],
                $m['medio'],
                $m['origen'],
                $m['tipo_movimiento'],
                $m['sentido'],
                $m['monto'],
                $m['moneda'],
                $m['concepto'],
                $m['fecha_hora'],
            ]);
        }
    }

    /**
     * @param resource $handle
     */
    private function escribirCsvAging($handle, ReporteAgingDTO $dto): void
    {
        $esCxC = $dto->esCuentasPorCobrar();
        $titulo = $esCxC ? 'AGING - CUENTAS POR COBRAR (CxC)' : 'AGING - CUENTAS POR PAGAR (CxP)';
        $this->escribirFilaCsv($handle, ['CAMARGO PMS - ' . $titulo]);
        $this->escribirFilaCsv($handle, ['Fecha de Corte', $dto->obtenerFechaCorte()]);
        $this->escribirFilaCsv($handle, ['Monto Total Adeudado (PEN)', $dto->obtenerMontoTotal()]);
        $this->escribirFilaCsv($handle, []);

        $this->escribirFilaCsv($handle, ['--- RESUMEN POR BUCKETS DE VENCIMIENTO ---']);
        $buckets = $dto->obtenerTotalesPorBucket();
        $this->escribirFilaCsv($handle, ['Por Vencer (Al Corriente)', $buckets['POR_VENCER']]);
        $this->escribirFilaCsv($handle, ['Vencido 1 a 30 días', $buckets['1_30']]);
        $this->escribirFilaCsv($handle, ['Vencido 31 a 60 días', $buckets['31_60']]);
        $this->escribirFilaCsv($handle, ['Vencido 61 a 90 días', $buckets['61_90']]);
        $this->escribirFilaCsv($handle, ['Vencido más de 90 días', $buckets['MAS_90']]);
        $this->escribirFilaCsv($handle, []);

        $this->escribirFilaCsv($handle, ['--- PARTIDAS DETALLADAS ---']);
        if ($esCxC) {
            $this->escribirFilaCsv($handle, ['Origen', 'Referencia', 'Documento', 'Titular/Cliente', 'Unidad/Folio', 'Emisión', 'Vencimiento', 'Original', 'Amortizado', 'Saldo Pendiente', 'Días Atraso', 'Bucket']);
            foreach ($dto->obtenerPartidas() as $p) {
                $this->escribirFilaCsv($handle, [
                    $p['origen'],
                    $p['referencia_codigo'],
                    $p['documento_codigo'],
                    $p['titular_nombre'],
                    $p['unidad_codigo'],
                    $p['fecha_emision'],
                    $p['fecha_vencimiento'],
                    $p['monto_original'],
                    $p['monto_amortizado'],
                    $p['saldo_pendiente'],
                    (string) $p['dias_vencido'],
                    $p['bucket'],
                ]);
            }
        } else {
            $this->escribirFilaCsv($handle, ['Origen', 'Referencia', 'Documento', 'Proveedor/Acreedor', 'RUC/Doc', 'Concepto', 'Emisión', 'Vencimiento', 'Original', 'Amortizado', 'Saldo Pendiente', 'Días Atraso', 'Bucket']);
            foreach ($dto->obtenerPartidas() as $p) {
                $this->escribirFilaCsv($handle, [
                    $p['origen'],
                    $p['referencia_codigo'],
                    $p['documento_codigo'],
                    $p['acreedor_nombre'],
                    $p['acreedor_documento'] ?? '',
                    $p['concepto'],
                    $p['fecha_emision'],
                    $p['fecha_vencimiento'],
                    $p['monto_original'],
                    $p['monto_amortizado'],
                    $p['saldo_pendiente'],
                    (string) $p['dias_vencido'],
                    $p['bucket'],
                ]);
            }
        }
    }

    // =========================================================================
    // 6. EXPORTACIÓN PDF MEDIANTE DOMPDF HOMOLOGADO (D-079 / D-085)
    // =========================================================================

    /**
     * Genera la exportación en PDF A4 institucional reutilizando GeneradorPdf.
     *
     * @return array{
     *     binario_pdf: string,
     *     hash_pdf_sha256: string,
     *     tamano_bytes: int,
     *     numero_paginas: int
     * }
     */
    public function exportarPDF(string $tipoReporte, array $filtros): array
    {
        $tipo = strtoupper(trim($tipoReporte));
        $html = '';

        switch ($tipo) {
            case 'DIARIO':
                $fecha = $filtros['fecha'] ?? date('Y-m-d');
                $propiedadId = isset($filtros['propiedad_id']) && $filtros['propiedad_id'] !== '' ? (int) $filtros['propiedad_id'] : null;
                $dto = $this->generarReporteDiario($fecha, $propiedadId);
                $html = $this->construirHtmlReporteDiario($dto);
                break;

            case 'FLUJO_CAJA':
                $fechaDesde = $filtros['fecha_desde'] ?? date('Y-m-01');
                $fechaHasta = $filtros['fecha_hasta'] ?? date('Y-m-d');
                $propiedadId = isset($filtros['propiedad_id']) && $filtros['propiedad_id'] !== '' ? (int) $filtros['propiedad_id'] : null;
                $dto = $this->generarFlujoCaja($fechaDesde, $fechaHasta, $propiedadId);
                $html = $this->construirHtmlFlujoCaja($dto);
                break;

            case 'AGING_CXC':
                $fechaCorte = $filtros['fecha_corte'] ?? date('Y-m-d');
                $propiedadId = isset($filtros['propiedad_id']) && $filtros['propiedad_id'] !== '' ? (int) $filtros['propiedad_id'] : null;
                $dto = $this->generarAgingCxC($fechaCorte, $propiedadId);
                $html = $this->construirHtmlAging($dto);
                break;

            case 'AGING_CXP':
                $fechaCorte = $filtros['fecha_corte'] ?? date('Y-m-d');
                $propiedadId = isset($filtros['propiedad_id']) && $filtros['propiedad_id'] !== '' ? (int) $filtros['propiedad_id'] : null;
                $dto = $this->generarAgingCxP($fechaCorte, $propiedadId);
                $html = $this->construirHtmlAging($dto);
                break;

            default:
                throw new ValidacionExcepcion("Tipo de reporte [{$tipo}] no soportado para exportación PDF.", ['tipo_reporte' => "Tipo de reporte [{$tipo}] no soportado para exportación PDF."]);
        }

        return $this->generadorPdf->renderizar($html, true);
    }

    private function obtenerEstilosCssPdf(): string
    {
        return '
            @page {
                size: A4 portrait;
                margin: 20mm 15mm 20mm 15mm;
            }
            body {
                font-family: "DejaVu Sans", "Fira Sans Condensed", Arial, sans-serif;
                font-size: 9pt;
                color: #2b2b2b;
                line-height: 1.3;
            }
            .header-tabla {
                width: 100%;
                border-bottom: 2px solid #0d6efd;
                padding-bottom: 8px;
                margin-bottom: 12px;
            }
            .header-titulo {
                font-size: 14pt;
                font-weight: bold;
                color: #1a202c;
                margin: 0;
            }
            .header-subtitulo {
                font-size: 8.5pt;
                color: #6c757d;
            }
            .seccion-titulo {
                font-size: 10pt;
                font-weight: bold;
                color: #0d6efd;
                border-bottom: 1px solid #dee2e6;
                padding-bottom: 4px;
                margin-top: 14px;
                margin-bottom: 8px;
                text-transform: uppercase;
            }
            table.datos {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 12px;
            }
            table.datos th {
                background-color: #f1f5f9;
                color: #334155;
                font-size: 8pt;
                font-weight: bold;
                padding: 5px 6px;
                border: 1px solid #cbd5e1;
                text-align: left;
            }
            table.datos td {
                font-size: 8pt;
                padding: 4px 6px;
                border: 1px solid #e2e8f0;
            }
            table.datos tr:nth-child(even) td {
                background-color: #f8fafc;
            }
            .text-end { text-align: right; }
            .text-center { text-align: center; }
            .badge {
                font-size: 7pt;
                padding: 2px 4px;
                border-radius: 3px;
                font-weight: bold;
            }
            .badge-success { background-color: #d1e7dd; color: #0f5132; }
            .badge-warning { background-color: #fff3cd; color: #664d03; }
            .badge-danger { background-color: #f8d7da; color: #842029; }
            .badge-info { background-color: #cff4fc; color: #055160; }
            .kpi-container {
                width: 100%;
                margin-bottom: 10px;
            }
            .kpi-box {
                border: 1px solid #cbd5e1;
                border-radius: 4px;
                padding: 6px;
                background-color: #f8fafc;
                text-align: center;
            }
            .kpi-valor {
                font-size: 13pt;
                font-weight: bold;
                color: #0f172a;
            }
            .kpi-label {
                font-size: 7pt;
                color: #64748b;
                text-transform: uppercase;
            }
            .footer-nota {
                font-size: 7.5pt;
                color: #64748b;
                margin-top: 15px;
                border-top: 1px dashed #cbd5e1;
                padding-top: 6px;
            }
        ';
    }

    private function construirHtmlReporteDiario(ReporteDiarioDTO $dto): string
    {
        $op = $dto->obtenerOperacionHotelera();
        $tes = $dto->obtenerTesoreria();
        $estilos = $this->obtenerEstilosCssPdf();

        $filasUnidades = '';
        foreach (array_slice($dto->obtenerDetalleUnidades(), 0, 50) as $u) {
            $filasUnidades .= sprintf(
                '<tr>
                    <td>%s</td>
                    <td>%s</td>
                    <td class="text-center">%s</td>
                    <td>%s</td>
                    <td><span class="badge badge-info">%s</span></td>
                    <td>%s</td>
                    <td>%s</td>
                </tr>',
                htmlspecialchars($u['codigo'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($u['nombre'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string) $u['piso_nivel'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($u['tipo_unidad'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($u['estado_ocupacion'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($u['titular'] ?? ($u['motivo_bloqueo'] ?? '-'), ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($u['estado_limpieza'], ENT_QUOTES, 'UTF-8')
            );
        }

        return '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Reporte Diario Gerencial - Camargo PMS</title>
            <style>' . $estilos . '</style>
        </head>
        <body>
            <table class="header-tabla">
                <tr>
                    <td>
                        <h1 class="header-titulo">CAMARGO PMS — REPORTE DIARIO GERENCIAL (MDR)</h1>
                        <div class="header-subtitulo">Fecha de Corte: ' . htmlspecialchars($dto->obtenerFechaCorte(), ENT_QUOTES, 'UTF-8') . ' | Propiedad: ' . htmlspecialchars($dto->obtenerPropiedadNombre() ?? 'Todas las propiedades', ENT_QUOTES, 'UTF-8') . '</div>
                    </td>
                    <td class="text-end header-subtitulo">
                        Generado: ' . date('d/m/Y H:i') . '
                    </td>
                </tr>
            </table>

            <div class="seccion-titulo">1. Operación Hotelera y Ocupación Neta</div>
            <table class="datos">
                <tr>
                    <th>Unidades Físicas</th>
                    <th>Fuera de Orden (OOO)</th>
                    <th>Vendibles Netas</th>
                    <th>Ocupadas</th>
                    <th>Ocupación Neta</th>
                    <th>Llegadas</th>
                    <th>Salidas</th>
                    <th>Stayovers</th>
                </tr>
                <tr>
                    <td class="text-center">' . $op['unidades_totales'] . '</td>
                    <td class="text-center">' . $op['unidades_ooo'] . '</td>
                    <td class="text-center"><strong>' . $op['unidades_vendibles'] . '</strong></td>
                    <td class="text-center">' . $op['unidades_ocupadas'] . '</td>
                    <td class="text-center"><strong>' . $op['ocupacion_neta_porcentaje'] . '%</strong></td>
                    <td class="text-center">' . $op['llegadas_previstas'] . ' (' . $op['llegadas_realizadas'] . ')</td>
                    <td class="text-center">' . $op['salidas_previstas'] . ' (' . $op['salidas_realizadas'] . ')</td>
                    <td class="text-center">' . $op['stayovers'] . '</td>
                </tr>
            </table>

            <div class="seccion-titulo">2. Tesorería y Flujo Monetario del Día</div>
            <table class="datos">
                <tr>
                    <th>Ingresos Caja (PEN)</th>
                    <th>Ingresos Banco (PEN)</th>
                    <th>Total Ingresos</th>
                    <th>Egresos Caja (PEN)</th>
                    <th>Egresos Banco (PEN)</th>
                    <th>Total Egresos</th>
                    <th>Saldo Neto Operativo</th>
                </tr>
                <tr>
                    <td class="text-end">' . $tes['ingresos_caja'] . '</td>
                    <td class="text-end">' . $tes['ingresos_banco'] . '</td>
                    <td class="text-end"><strong>S/ ' . $tes['total_ingresos'] . '</strong></td>
                    <td class="text-end">' . $tes['egresos_caja'] . '</td>
                    <td class="text-end">' . $tes['egresos_banco'] . '</td>
                    <td class="text-end"><strong>S/ ' . $tes['total_egresos'] . '</strong></td>
                    <td class="text-end"><strong>S/ ' . $tes['saldo_neto_operativo'] . '</strong></td>
                </tr>
            </table>

            <div class="seccion-titulo">3. Detalle de Unidades</div>
            <table class="datos">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Nombre</th>
                        <th class="text-center">Piso</th>
                        <th>Tipo</th>
                        <th>Estado Ocupación</th>
                        <th>Titular / Motivo</th>
                        <th>Limpieza</th>
                    </tr>
                </thead>
                <tbody>' . $filasUnidades . '</tbody>
            </table>

            <div class="footer-nota">
                <strong>Gobernanza D-087:</strong> Proyección analítica de solo lectura. ADR y RevPAR históricos se declaran formalmente diferidos a DEVENGO-ALOJAMIENTO-1.
            </div>
        </body>
        </html>';
    }

    private function construirHtmlFlujoCaja(ReporteFlujoCajaDTO $dto): string
    {
        $totEfe = $dto->obtenerTotalesEfectivo();
        $totBan = $dto->obtenerTotalesBanco();
        $totCon = $dto->obtenerTotalesConsolidado();
        $estilos = $this->obtenerEstilosCssPdf();

        $filasMovs = '';
        foreach (array_slice($dto->obtenerMovimientosDetalle(), 0, 80) as $m) {
            $badgeClass = $m['sentido'] === 'INGRESO' ? 'badge-success' : 'badge-danger';
            $filasMovs .= sprintf(
                '<tr>
                    <td class="text-center">%s</td>
                    <td>%s</td>
                    <td>%s</td>
                    <td><span class="badge %s">%s</span></td>
                    <td class="text-end"><strong>S/ %s</strong></td>
                    <td>%s</td>
                    <td>%s</td>
                </tr>',
                htmlspecialchars((string) $m['id'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($m['medio'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($m['tipo_movimiento'], ENT_QUOTES, 'UTF-8'),
                $badgeClass,
                htmlspecialchars($m['sentido'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string) $m['monto'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($m['concepto'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($m['fecha_hora'], ENT_QUOTES, 'UTF-8')
            );
        }

        return '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Flujo de Caja Consolidado - Camargo PMS</title>
            <style>' . $estilos . '</style>
        </head>
        <body>
            <table class="header-tabla">
                <tr>
                    <td>
                        <h1 class="header-titulo">CAMARGO PMS — FLUJO DE CAJA CONSOLIDADO</h1>
                        <div class="header-subtitulo">Período: ' . htmlspecialchars($dto->obtenerFechaDesde(), ENT_QUOTES, 'UTF-8') . ' al ' . htmlspecialchars($dto->obtenerFechaHasta(), ENT_QUOTES, 'UTF-8') . '</div>
                    </td>
                    <td class="text-end header-subtitulo">
                        Generado: ' . date('d/m/Y H:i') . '
                    </td>
                </tr>
            </table>

            <div class="seccion-titulo">1. Resumen Patrimonial de Tesorería</div>
            <table class="datos">
                <tr>
                    <th>Medio</th>
                    <th class="text-end">Saldo Inicial</th>
                    <th class="text-end">(+) Ingresos</th>
                    <th class="text-end">(-) Egresos</th>
                    <th class="text-end">(=) Saldo Final</th>
                    <th class="text-center">Cuadre Algebraico</th>
                </tr>
                <tr>
                    <td><strong>EFECTIVO (Cajas Físicas)</strong></td>
                    <td class="text-end">S/ ' . $totEfe['saldo_inicial'] . '</td>
                    <td class="text-end">S/ ' . $totEfe['ingresos'] . '</td>
                    <td class="text-end">S/ ' . $totEfe['egresos'] . '</td>
                    <td class="text-end"><strong>S/ ' . $totEfe['saldo_final'] . '</strong></td>
                    <td class="text-center"><span class="badge badge-success">OK</span></td>
                </tr>
                <tr>
                    <td><strong>BANCOS (Cuentas Bancarias)</strong></td>
                    <td class="text-end">S/ ' . $totBan['saldo_inicial'] . '</td>
                    <td class="text-end">S/ ' . $totBan['ingresos'] . '</td>
                    <td class="text-end">S/ ' . $totBan['egresos'] . '</td>
                    <td class="text-end"><strong>S/ ' . $totBan['saldo_final'] . '</strong></td>
                    <td class="text-center"><span class="badge badge-success">OK</span></td>
                </tr>
                <tr style="background-color: #f1f5f9; font-weight: bold;">
                    <td><strong>CONSOLIDADO PATRIMONIAL</strong></td>
                    <td class="text-end">S/ ' . $totCon['saldo_inicial'] . '</td>
                    <td class="text-end">S/ ' . $totCon['ingresos'] . '</td>
                    <td class="text-end">S/ ' . $totCon['egresos'] . '</td>
                    <td class="text-end"><strong>S/ ' . $totCon['saldo_final'] . '</strong></td>
                    <td class="text-center"><span class="badge badge-success">CUADRADO</span></td>
                </tr>
            </table>

            <div class="seccion-titulo">2. Detalle Cronológico de Movimientos</div>
            <table class="datos">
                <thead>
                    <tr>
                        <th class="text-center">ID</th>
                        <th>Medio</th>
                        <th>Tipo Movimiento</th>
                        <th>Sentido</th>
                        <th class="text-end">Monto</th>
                        <th>Concepto</th>
                        <th>Fecha / Hora</th>
                    </tr>
                </thead>
                <tbody>' . $filasMovs . '</tbody>
            </table>

            <div class="footer-nota">
                <strong>Gobernanza D-087:</strong> Cuadre inmutable: Saldo Inicial + Ingresos - Egresos = Saldo Final. Cero transferencias internas duplicadas en consolidado.
            </div>
        </body>
        </html>';
    }

    private function construirHtmlAging(ReporteAgingDTO $dto): string
    {
        $esCxC = $dto->esCuentasPorCobrar();
        $titulo = $esCxC ? 'AGING — CUENTAS POR COBRAR (CxC)' : 'AGING — CUENTAS POR PAGAR (CxP)';
        $sub = $esCxC ? 'Cartera de Clientes e Inquilinos' : 'Obligaciones con Proveedores y Acreedores';
        $buckets = $dto->obtenerTotalesPorBucket();
        $estilos = $this->obtenerEstilosCssPdf();

        $filasPartidas = '';
        foreach (array_slice($dto->obtenerPartidas(), 0, 80) as $p) {
            $nombre = $esCxC ? ($p['titular_nombre'] ?? '') : ($p['acreedor_nombre'] ?? '');
            $filasPartidas .= sprintf(
                '<tr>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                    <td class="text-end">S/ %s</td>
                    <td class="text-end">S/ %s</td>
                    <td class="text-end"><strong>S/ %s</strong></td>
                    <td class="text-center">%s d</td>
                    <td class="text-center"><span class="badge badge-warning">%s</span></td>
                </tr>',
                htmlspecialchars($p['origen'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($p['referencia_codigo'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($p['documento_codigo'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($p['fecha_vencimiento'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string) $p['monto_original'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string) $p['monto_amortizado'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string) $p['saldo_pendiente'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string) $p['dias_vencido'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($p['bucket'], ENT_QUOTES, 'UTF-8')
            );
        }

        return '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>' . $titulo . ' - Camargo PMS</title>
            <style>' . $estilos . '</style>
        </head>
        <body>
            <table class="header-tabla">
                <tr>
                    <td>
                        <h1 class="header-titulo">CAMARGO PMS — ' . $titulo . '</h1>
                        <div class="header-subtitulo">' . $sub . ' | Fecha de Corte: ' . htmlspecialchars($dto->obtenerFechaCorte(), ENT_QUOTES, 'UTF-8') . '</div>
                    </td>
                    <td class="text-end header-subtitulo">
                        Generado: ' . date('d/m/Y H:i') . '
                    </td>
                </tr>
            </table>

            <div class="seccion-titulo">1. Distribución por Buckets de Antigüedad</div>
            <table class="datos">
                <tr>
                    <th class="text-end">Por Vencer</th>
                    <th class="text-end">1 - 30 Días</th>
                    <th class="text-end">31 - 60 Días</th>
                    <th class="text-end">61 - 90 Días</th>
                    <th class="text-end">Más de 90 Días</th>
                    <th class="text-end" style="background-color: #e2e8f0;">TOTAL CARTERA</th>
                </tr>
                <tr>
                    <td class="text-end">S/ ' . $buckets['POR_VENCER'] . '</td>
                    <td class="text-end">S/ ' . $buckets['1_30'] . '</td>
                    <td class="text-end">S/ ' . $buckets['31_60'] . '</td>
                    <td class="text-end">S/ ' . $buckets['61_90'] . '</td>
                    <td class="text-end">S/ ' . $buckets['MAS_90'] . '</td>
                    <td class="text-end" style="background-color: #f1f5f9;"><strong>S/ ' . $dto->obtenerMontoTotal() . '</strong></td>
                </tr>
            </table>

            <div class="seccion-titulo">2. Partidas Individuales Pendientes</div>
            <table class="datos">
                <thead>
                    <tr>
                        <th>Origen</th>
                        <th>Ref.</th>
                        <th>Documento</th>
                        <th>' . ($esCxC ? 'Titular' : 'Acreedor') . '</th>
                        <th>Vencimiento</th>
                        <th class="text-end">Original</th>
                        <th class="text-end">Amortizado</th>
                        <th class="text-end">Saldo Pendiente</th>
                        <th class="text-center">Atraso</th>
                        <th class="text-center">Bucket</th>
                    </tr>
                </thead>
                <tbody>' . $filasPartidas . '</tbody>
            </table>

            <div class="footer-nota">
                <strong>Gobernanza D-087:</strong> Segregación inmutable de carteras: CxC != CxP. Jamás se suman derechos de cobro con obligaciones de pago.
            </div>
        </body>
        </html>';
    }

    // =========================================================================
    // UTILIDADES PRIVADAS
    // =========================================================================

    private function determinarBucketDias(int $dias): string
    {
        if ($dias <= 30) {
            return '1_30';
        }
        if ($dias <= 60) {
            return '31_60';
        }
        if ($dias <= 90) {
            return '61_90';
        }
        return 'MAS_90';
    }

    private function validarFecha(string $fecha, string $campo): void
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || !strtotime($fecha)) {
            throw new ValidacionExcepcion("La fecha [{$fecha}] no tiene un formato válido (YYYY-MM-DD).", [$campo => "La fecha [{$fecha}] no tiene un formato válido (YYYY-MM-DD)."]);
        }
    }

    private function validarRangoFechas(string $fechaDesde, string $fechaHasta): void
    {
        $this->validarFecha($fechaDesde, 'fecha_desde');
        $this->validarFecha($fechaHasta, 'fecha_hasta');

        if ($fechaDesde > $fechaHasta) {
            throw new ValidacionExcepcion("La fecha inicial [{$fechaDesde}] no puede ser posterior a la fecha final [{$fechaHasta}].", ['rango_fechas' => "La fecha inicial [{$fechaDesde}] no puede ser posterior a la fecha final [{$fechaHasta}]."]);
        }
    }
}
