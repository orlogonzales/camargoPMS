<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

use CamargoPMS\Modelos\HousekeepingEstadoLimpieza;
use CamargoPMS\Modelos\TapeChartCelda;
use CamargoPMS\Modelos\TapeChartProyeccion;
use CamargoPMS\Modelos\TapeChartUnidad;
use CamargoPMS\Repositorios\PropiedadRepositorio;
use CamargoPMS\Repositorios\TapeChartRepositorio;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Servicio agregador por lotes para el Tape Chart y Rack Hotelero (TAPE-CHART-1 / D-084).
 *
 * Principios vinculantes:
 * - TAPE CHART != FUENTE DE VERDAD.
 * - O(1) en número de consultas SQL respecto al número de celdas (cero antipatrón N x M).
 * - PROYECCIÓN CALENDARIO DE HOY = FUENTE DEL RACK DE HOY (consistencia matemática absoluta).
 * - Prioridad visual sin destrucción de información (indicadores secundarios y conflictos).
 * - Semántica temporal hotelera D-066: intervalo semiabierto [E, S), fecha hotelera local de la propiedad.
 * - Cero doble conteo (reserva confirmada con estadía en curso proyecta únicamente la estadía).
 * - Verificación de expiración real de holds temporales.
 */
class TapeChartServicio
{
    private TapeChartRepositorio $tapeChartRepo;
    private PropiedadRepositorio $propiedadRepo;
    private ConfiguracionServicio $configuracionServicio;
    private AutorizacionServicio $autorizacionServicio;

    public function __construct(
        ?TapeChartRepositorio $tapeChartRepo = null,
        ?PropiedadRepositorio $propiedadRepo = null,
        ?ConfiguracionServicio $configuracionServicio = null,
        ?AutorizacionServicio $autorizacionServicio = null
    ) {
        $this->tapeChartRepo = $tapeChartRepo ?? new TapeChartRepositorio();
        $this->propiedadRepo = $propiedadRepo ?? new PropiedadRepositorio();
        $this->configuracionServicio = $configuracionServicio ?? new ConfiguracionServicio();
        $this->autorizacionServicio = $autorizacionServicio ?? new AutorizacionServicio();
    }

    /**
     * Genera la proyección consolidada en memoria del Tape Chart y Rack de Hoy.
     *
     * @param int $propiedadId ID primario de la propiedad contenedora
     * @param string $fechaDesde Fecha de inicio del intervalo (inclusive, formato Y-m-d)
     * @param string $fechaHasta Fecha de fin del intervalo (exclusive, formato Y-m-d)
     * @param int|null $tipoUnidadId Filtro opcional por tipología de unidad
     * @param string|null $pisoNivel Filtro opcional por piso/ala
     * @param int|null $usuarioActualId ID del usuario autenticado para resolución de capacidades RBAC
     * @return TapeChartProyeccion
     * @throws InvalidArgumentException Si las fechas o parámetros son inválidos
     */
    public function obtenerProyeccion(
        int $propiedadId,
        string $fechaDesde,
        string $fechaHasta,
        ?int $tipoUnidadId = null,
        ?string $pisoNivel = null,
        ?int $usuarioActualId = null
    ): TapeChartProyeccion {
        // 1. Validar propiedad
        $propiedad = $this->propiedadRepo->buscarPorId($propiedadId);
        if ($propiedad === null) {
            throw new InvalidArgumentException("La propiedad especificada (ID {$propiedadId}) no existe.");
        }
        if (!$propiedad->estaActiva()) {
            throw new InvalidArgumentException("La propiedad '{$propiedad->obtenerNombre()}' se encuentra inactiva.");
        }

        // 2. Resolver huso horario IANA y fecha hotelera actual bajo D-066
        $tzCentral = (string) $this->configuracionServicio->obtener('operacion.zona_horaria_predeterminada', 'America/Lima');
        $zonaHorariaStr = $propiedad->obtenerZonaHoraria() ?: $tzCentral;
        $tz = new DateTimeZone($zonaHorariaStr);
        $dtAhora = new DateTimeImmutable('now', $tz);
        $fechaHoteleraHoy = $dtAhora->format('Y-m-d');

        // 3. Normalizar y validar intervalo [fechaDesde, fechaHasta)
        $this->validarIntervalo($fechaDesde, $fechaHasta);

        // 4. Duración de hold configurada en minutos
        $duracionHoldMinutos = (int) $this->configuracionServicio->obtener('reservas.duracion_hold_minutos', 30);

        // =========================================================================
        // EXTRACCIÓN POR LOTES (EXACTAMENTE O(1) EN NÚMERO DE QUERIES RESPECTO A CELDAS)
        // =========================================================================
        $filasUnidades = $this->tapeChartRepo->obtenerUnidadesPropiedad($propiedadId, $tipoUnidadId, $pisoNivel);
        $filasInventario = $this->tapeChartRepo->obtenerInventarioRango($propiedadId, $fechaDesde, $fechaHasta);
        $filasReservas = $this->tapeChartRepo->obtenerReservasEnRango($propiedadId, $fechaDesde, $fechaHasta);
        $filasEstadias = $this->tapeChartRepo->obtenerEstadiasEnRango($propiedadId, $fechaDesde, $fechaHasta);
        $filasArrendamientos = $this->tapeChartRepo->obtenerArrendamientosEnRango($propiedadId, $fechaDesde, $fechaHasta);
        $filasMantenimiento = $this->tapeChartRepo->obtenerMantenimientoEnRango($propiedadId, $fechaDesde, $fechaHasta);
        $filasLimpieza = $this->tapeChartRepo->obtenerLimpiezaActual($propiedadId);

        // =========================================================================
        // INDEXACIÓN ASOCIATIVA EN MEMORIA (O(K))
        // =========================================================================

        // A. Limpieza actual por unidad
        $mapaLimpieza = [];
        foreach ($filasLimpieza as $limp) {
            $mapaLimpieza[(int) $limp['unidad_id']] = $limp;
        }

        // B. Inventario sparse mapeado por [unidad_id][fecha] => fila
        $mapaInventario = [];
        foreach ($filasInventario as $inv) {
            $uId = (int) $inv['unidad_id'];
            $f = (string) $inv['fecha'];
            $mapaInventario[$uId][$f] = $inv;
        }

        // C. Estadías mapeadas por unidad y por id
        $estadiasPorUnidad = [];
        $mapaEstadias = [];
        $reservaIdsConEstadia = [];
        foreach ($filasEstadias as $est) {
            $uId = (int) $est['unidad_id'];
            $eId = (int) $est['id'];
            $estadiasPorUnidad[$uId][] = $est;
            $mapaEstadias[$eId] = $est;
            if (!empty($est['reserva_id'])) {
                $reservaIdsConEstadia[(int) $est['reserva_id']] = $eId;
            }
        }

        // D. Reservas mapeadas por unidad (evaluando expiración de HOLD)
        $reservasPorUnidad = [];
        $mapaReservas = [];
        foreach ($filasReservas as $res) {
            $uId = (int) $res['unidad_id'];
            $rId = (int) $res['id'];

            // D-084 / Ajuste 8: Si es PENDIENTE/HOLD, verificar si ya expiró formalmente
            if ($res['estado'] === 'PENDIENTE') {
                $creacionDt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $res['fecha_creacion'] ?? '', $tz);
                if ($creacionDt) {
                    $expiracionDt = $creacionDt->modify("+{$duracionHoldMinutos} minutes");
                    if ($dtAhora > $expiracionDt) {
                        // El hold expiró en tiempo real, no se considera bloqueante
                        continue;
                    }
                }
            }

            // D-084 / Ajuste 6: Si la reserva ya tiene una estadía en curso asociada sobre esta unidad,
            // la estadía es el hecho dominante; la reserva no se duplica en ocupación
            if (isset($reservaIdsConEstadia[$rId])) {
                $estadiaAsociada = $mapaEstadias[$reservaIdsConEstadia[$rId]] ?? null;
                if ($estadiaAsociada && (int) $estadiaAsociada['unidad_id'] === $uId && $estadiaAsociada['estado'] === 'EN_CURSO') {
                    $res['tiene_estadia_activa'] = true;
                }
            }

            $reservasPorUnidad[$uId][] = $res;
            $mapaReservas[$rId] = $res;
        }

        // E. Arrendamientos mapeados por unidad
        $arrendamientosPorUnidad = [];
        $mapaArrendamientos = [];
        foreach ($filasArrendamientos as $arr) {
            $uId = (int) $arr['unidad_id'];
            $aId = (int) $arr['id'];
            $arrendamientosPorUnidad[$uId][] = $arr;
            $mapaArrendamientos[$aId] = $arr;
        }

        // F. Mantenimiento mapeado por unidad
        $mantenimientoPorUnidad = [];
        foreach ($filasMantenimiento as $mant) {
            $uId = (int) $mant['unidad_id'];
            $mantenimientoPorUnidad[$uId][] = $mant;
        }

        // =========================================================================
        // CONSTRUCCIÓN DE COLUMNAS DE FECHAS
        // =========================================================================
        $dtInicio = DateTimeImmutable::createFromFormat('!Y-m-d', $fechaDesde, $tz);
        $dtFin = DateTimeImmutable::createFromFormat('!Y-m-d', $fechaHasta, $tz);
        $diasTotales = (int) $dtInicio->diff($dtFin)->days;

        $columnasFechas = [];
        $fechasLista = [];
        for ($i = 0; $i < $diasTotales; $i++) {
            $dtDia = $dtInicio->modify("+{$i} days");
            $fStr = $dtDia->format('Y-m-d');
            $fechasLista[] = $fStr;

            $diaSemanaNum = (int) $dtDia->format('N');
            $nombresCortos = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];
            $mesesCortos = [1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic'];

            $columnasFechas[] = [
                'fecha' => $fStr,
                'dia_numero' => (int) $dtDia->format('j'),
                'dia_nombre_corto' => $nombresCortos[$diaSemanaNum] ?? '',
                'mes_nombre_corto' => $mesesCortos[(int) $dtDia->format('n')] ?? '',
                'es_hoy' => ($fStr === $fechaHoteleraHoy),
                'es_fin_semana' => ($diaSemanaNum === 6 || $diaSemanaNum === 7),
            ];
        }

        // =========================================================================
        // CAPACIDADES RBAC DEL USUARIO AUTENTICADO
        // =========================================================================
        $puedeCheckin = $usuarioActualId ? $this->autorizacionServicio->puede($usuarioActualId, 'estadias.crear') : false;
        $puedeCheckout = $usuarioActualId ? $this->autorizacionServicio->puede($usuarioActualId, 'estadias.checkout') : false;
        $puedeBloquear = $usuarioActualId ? $this->autorizacionServicio->puede($usuarioActualId, 'disponibilidad.bloquear') : false;
        $puedeLiberar = $usuarioActualId ? $this->autorizacionServicio->puede($usuarioActualId, 'disponibilidad.liberar') : false;
        $puedeVerReserva = $usuarioActualId ? $this->autorizacionServicio->puede($usuarioActualId, 'reservas.ver') : false;
        $puedeVerEstadia = $usuarioActualId ? $this->autorizacionServicio->puede($usuarioActualId, 'estadias.ver') : false;
        $puedeVerMantenimiento = $usuarioActualId ? $this->autorizacionServicio->puede($usuarioActualId, 'mantenimiento.ver') : false;
        $puedeGestionarLimpieza = $usuarioActualId ? $this->autorizacionServicio->puede($usuarioActualId, 'housekeeping.gestionar') : false;

        // =========================================================================
        // CONSTRUCCIÓN DE FILAS DE UNIDADES Y CELDAS
        // =========================================================================
        $filasUnidadesResultado = [];

        // Acumuladores deterministas para los KPIs de Hoy (Garantía de Consistencia)
        $kpiTotalUnidades = count($filasUnidades);
        $kpiOcupadasStayover = 0;
        $kpiLlegadasHoy = 0;
        $kpiSalidasHoy = 0;
        $kpiVacantesListasVR = 0;
        $kpiVacantesSuciasVD = 0;
        $kpiVacantesLimpiezaVCL = 0;
        $kpiFueraServicioOOO = 0;

        foreach ($filasUnidades as $uFila) {
            $unidadId = (int) $uFila['id'];

            // Limpieza actual de hoy de la unidad
            $limp = $mapaLimpieza[$unidadId] ?? null;
            $estadoLimpHoy = $limp ? $limp['estado_limpieza'] : HousekeepingEstadoLimpieza::ESTADO_SUCIA;
            $tareaActivaId = $limp && !empty($limp['tarea_activa_id']) ? (int) $limp['tarea_activa_id'] : null;

            [$codigoLimpHoy, $textoLimpHoy, $claseLimpHoy] = $this->resolverEtiquetaLimpieza($estadoLimpHoy);

            $unidadModelo = new TapeChartUnidad(
                $unidadId,
                $uFila['codigo'],
                $uFila['nombre'],
                $propiedadId,
                $uFila['propiedad_nombre'],
                (int) $uFila['tipo_unidad_id'],
                $uFila['tipo_unidad_nombre'],
                $uFila['piso_nivel'],
                (int) $uFila['capacidad_personas'],
                $codigoLimpHoy,
                $textoLimpHoy,
                $claseLimpHoy,
                $tareaActivaId
            );

            // Proyección celda por celda para cada fecha del horizonte
            foreach ($fechasLista as $fecha) {
                $esFechaHoy = ($fecha === $fechaHoteleraHoy);

                $celda = $this->resolverCelda(
                    $unidadId,
                    $fecha,
                    $esFechaHoy,
                    $codigoLimpHoy,
                    $mapaInventario[$unidadId][$fecha] ?? null,
                    $mantenimientoPorUnidad[$unidadId] ?? [],
                    $arrendamientosPorUnidad[$unidadId] ?? [],
                    $estadiasPorUnidad[$unidadId] ?? [],
                    $reservasPorUnidad[$unidadId] ?? [],
                    $puedeCheckin,
                    $puedeCheckout,
                    $puedeBloquear,
                    $puedeLiberar,
                    $puedeVerReserva,
                    $puedeVerEstadia,
                    $puedeVerMantenimiento,
                    $puedeGestionarLimpieza
                );

                $unidadModelo->agregarCelda($celda);

                // =====================================================================
                // AGREGACIÓN DE KPIS DE HOY DIRECTAMENTE DESDE LA CELDA DE HOY
                // =====================================================================
                if ($esFechaHoy) {
                    if ($celda->esArrival()) {
                        $kpiLlegadasHoy++;
                    }
                    if ($celda->esDeparture()) {
                        $kpiSalidasHoy++;
                    }

                    if (in_array($celda->obtenerEstadoPrincipal(), [TapeChartCelda::ESTADO_MANTENIMIENTO_OOO, TapeChartCelda::ESTADO_BLOQUEO_MANUAL], true)) {
                        $kpiFueraServicioOOO++;
                    } elseif ($celda->esStayover() || in_array($celda->obtenerEstadoPrincipal(), [TapeChartCelda::ESTADO_ESTADIA, TapeChartCelda::ESTADO_ARRENDAMIENTO], true)) {
                        $kpiOcupadasStayover++;
                    } else {
                        // Vacante, Reserva con llegada hoy o Hold: clasificar según higiene física de Housekeeping
                        if ($codigoLimpHoy === 'VR') {
                            $kpiVacantesListasVR++;
                        } elseif ($codigoLimpHoy === 'VD') {
                            $kpiVacantesSuciasVD++;
                        } elseif ($codigoLimpHoy === 'VCL') {
                            $kpiVacantesLimpiezaVCL++;
                        } else {
                            $kpiVacantesSuciasVD++;
                        }
                    }
                }
            }

            $filasUnidadesResultado[] = $unidadModelo;
        }

        // =========================================================================
        // CONSOLIDACIÓN DE KPIS DE HOY
        // =========================================================================
        $porcentajeOcupacion = $kpiTotalUnidades > 0
            ? round(($kpiOcupadasStayover / $kpiTotalUnidades) * 100, 1)
            : 0.0;

        $kpisHoy = [
            'total_unidades' => $kpiTotalUnidades,
            'ocupadas_stayover' => $kpiOcupadasStayover,
            'llegadas_hoy' => $kpiLlegadasHoy,
            'salidas_hoy' => $kpiSalidasHoy,
            'vacantes_listas_vr' => $kpiVacantesListasVR,
            'vacantes_sucias_vd' => $kpiVacantesSuciasVD,
            'vacantes_limpieza_vcl' => $kpiVacantesLimpiezaVCL,
            'fuera_servicio_ooo' => $kpiFueraServicioOOO,
            'porcentaje_ocupacion' => $porcentajeOcupacion,
        ];

        return new TapeChartProyeccion(
            $propiedadId,
            $propiedad->obtenerNombre(),
            $zonaHorariaStr,
            $fechaHoteleraHoy,
            $fechaDesde,
            $fechaHasta,
            $columnasFechas,
            $filasUnidadesResultado,
            $kpisHoy
        );
    }

    /**
     * Resuelve el estado dominante, indicadores secundarios y conflictos de una celda.
     *
     * @param int $unidadId
     * @param string $fecha
     * @param bool $esFechaHoy
     * @param string $limpHoyCodigo
     * @param array<string, mixed>|null $inventarioFila
     * @param array<int, array<string, mixed>> $mantenimientos
     * @param array<int, array<string, mixed>> $arrendamientos
     * @param array<int, array<string, mixed>> $estadias
     * @param array<int, array<string, mixed>> $reservas
     * @param bool $puedeCheckin
     * @param bool $puedeCheckout
     * @param bool $puedeBloquear
     * @param bool $puedeLiberar
     * @param bool $puedeVerReserva
     * @param bool $puedeVerEstadia
     * @param bool $puedeVerMantenimiento
     * @param bool $puedeGestionarLimpieza
     * @return TapeChartCelda
     */
    private function resolverCelda(
        int $unidadId,
        string $fecha,
        bool $esFechaHoy,
        string $limpHoyCodigo,
        ?array $inventarioFila,
        array $mantenimientos,
        array $arrendamientos,
        array $estadias,
        array $reservas,
        bool $puedeCheckin,
        bool $puedeCheckout,
        bool $puedeBloquear,
        bool $puedeLiberar,
        bool $puedeVerReserva,
        bool $puedeVerEstadia,
        bool $puedeVerMantenimiento,
        bool $puedeGestionarLimpieza
    ): TapeChartCelda {
        $indicadoresSecundarios = [];
        $conflictos = [];
        $accionesCandidatas = [];

        // 1. Evaluar si hay Mantenimiento bloqueante (OOO) en esta fecha
        $ordenBloqueante = null;
        $tieneIncidenciaTecnica = false;
        foreach ($mantenimientos as $mant) {
            if ((int) $mant['requiere_bloqueo'] === 1) {
                $inicioB = $mant['fecha_bloqueo_inicio'];
                $finB = $mant['fecha_bloqueo_fin'];
                if ($fecha >= $inicioB && $fecha < $finB) {
                    $ordenBloqueante = $mant;
                }
            } else {
                $tieneIncidenciaTecnica = true;
            }
        }

        if ($tieneIncidenciaTecnica) {
            $indicadoresSecundarios[] = 'INCIDENCIA_TECNICA';
        }

        // 2. Evaluar si hay Arrendamiento en esta fecha [inicio, fin)
        $arrendamientoActivo = null;
        foreach ($arrendamientos as $arr) {
            if ($arr['estado'] === 'VIGENTE' && $fecha >= $arr['fecha_inicio'] && $fecha < $arr['fecha_fin']) {
                $arrendamientoActivo = $arr;
                break;
            }
        }

        // 3. Evaluar si hay Estadía en esta fecha [entrada, salida)
        $estadiaActiva = null;
        $esArrivalEstadia = false;
        $esDepartureEstadia = false;
        $esStayoverEstadia = false;

        foreach ($estadias as $est) {
            $fEntrada = $est['fecha_entrada'];
            $fSalida = ($est['estado'] === 'FINALIZADA' && !empty($est['fecha_salida_real']))
                ? substr((string) $est['fecha_salida_real'], 0, 10)
                : $est['fecha_salida_programada'];

            // Detección de Llegada / Salida para la fecha
            if ($fEntrada === $fecha) {
                $esArrivalEstadia = true;
            }
            if ($fSalida === $fecha) {
                $esDepartureEstadia = true;
            }

            // Noche hotelera ocupada: intervalo semiabierto [fEntrada, fSalida)
            if ($fecha >= $fEntrada && $fecha < $fSalida) {
                $estadiaActiva = $est;
                if ($fEntrada < $fecha && $fSalida > $fecha) {
                    $esStayoverEstadia = true;
                }
            }
        }

        // 4. Evaluar si hay Reserva en esta fecha [entrada, salida)
        $reservaActiva = null;
        $esArrivalReserva = false;
        foreach ($reservas as $res) {
            $fEntrada = $res['fecha_entrada'];
            $fSalida = $res['fecha_salida'];

            if ($fEntrada === $fecha) {
                $esArrivalReserva = true;
            }

            // Noche ocupada: [fEntrada, fSalida)
            if ($fecha >= $fEntrada && $fecha < $fSalida) {
                // Si la reserva no está suplantada por una estadía activa en curso
                if (empty($res['tiene_estadia_activa'])) {
                    $reservaActiva = $res;
                }
            }
        }

        // D-084 / Ajuste 5: Cálculo matemático de Arrival y Departure
        $esArrival = $esArrivalEstadia || ($esArrivalReserva && !$estadiaActiva);
        $esDeparture = $esDepartureEstadia;
        $esStayover = $esStayoverEstadia;

        // Detección de Conflictos sin destrucción de información
        if ($ordenBloqueante !== null && ($reservaActiva !== null || $estadiaActiva !== null || $arrendamientoActivo !== null)) {
            $conflictos[] = 'AFECTADA_POR_MANTENIMIENTO_OOO';
        }
        if ($arrendamientoActivo !== null && ($reservaActiva !== null || $estadiaActiva !== null)) {
            $conflictos[] = 'CONFLICTO_ARRENDAMIENTO_CON_RESERVA';
        }

        // D-084: Resolución del Estado Principal según Jerarquía de Prioridad Visual
        if ($ordenBloqueante !== null) {
            // 1. MANTENIMIENTO_OOO
            if ($puedeVerMantenimiento) {
                $accionesCandidatas[] = 'VER_MANTENIMIENTO';
            }

            return new TapeChartCelda(
                fecha: $fecha,
                estadoPrincipal: TapeChartCelda::ESTADO_MANTENIMIENTO_OOO,
                subestado: 'OOO',
                codigoReferencia: $ordenBloqueante['codigo'],
                referenciaId: (int) $ordenBloqueante['id'],
                origenTipo: 'MANTENIMIENTO_ORDEN',
                titularNombre: 'Fuera de Servicio',
                titularDocumento: null,
                duracionNoches: 1,
                nocheIndice: 1,
                esInicioBloque: ($fecha === $ordenBloqueante['fecha_bloqueo_inicio']),
                esFinBloque: false,
                esArrival: false,
                esDeparture: false,
                esStayover: false,
                indicadoresSecundarios: $indicadoresSecundarios,
                conflictos: $conflictos,
                accionesCandidatas: $accionesCandidatas,
                claseColor: 'tape-celda-ooo'
            );
        }

        if ($arrendamientoActivo !== null) {
            // 2. ARRENDAMIENTO ACTIVO
            $nochesArr = max(1, (int) ((strtotime($arrendamientoActivo['fecha_fin']) - strtotime($arrendamientoActivo['fecha_inicio'])) / 86400));
            $nocheIdx = max(1, (int) ((strtotime($fecha) - strtotime($arrendamientoActivo['fecha_inicio'])) / 86400) + 1);

            $accionesCandidatas[] = 'VER_ARRENDAMIENTO';

            return new TapeChartCelda(
                fecha: $fecha,
                estadoPrincipal: TapeChartCelda::ESTADO_ARRENDAMIENTO,
                subestado: 'ARRENDADA',
                codigoReferencia: $arrendamientoActivo['codigo'],
                referenciaId: (int) $arrendamientoActivo['id'],
                origenTipo: 'ARRENDAMIENTO',
                titularNombre: $this->formatearNombreCorto($arrendamientoActivo['titular_apellido'], $arrendamientoActivo['titular_nombres']),
                titularDocumento: $arrendamientoActivo['titular_documento'] ?: null,
                duracionNoches: $nochesArr,
                nocheIndice: $nocheIdx,
                esInicioBloque: ($fecha === $arrendamientoActivo['fecha_inicio']),
                esFinBloque: false,
                esArrival: ($fecha === $arrendamientoActivo['fecha_inicio']),
                esDeparture: false,
                esStayover: true,
                indicadoresSecundarios: $indicadoresSecundarios,
                conflictos: $conflictos,
                accionesCandidatas: $accionesCandidatas,
                claseColor: 'tape-celda-arrendamiento'
            );
        }

        if ($estadiaActiva !== null) {
            // 3. ESTADÍA EN CURSO (IN-HOUSE)
            $nochesEst = max(1, (int) $estadiaActiva['noches']);
            $nocheIdx = max(1, (int) ((strtotime($fecha) - strtotime($estadiaActiva['fecha_entrada'])) / 86400) + 1);

            if ($puedeVerEstadia) {
                $accionesCandidatas[] = 'VER_ESTADIA';
            }
            if ($esDeparture && $puedeCheckout && $estadiaActiva['estado'] === 'EN_CURSO') {
                $accionesCandidatas[] = 'CHECKOUT_RAPIDO';
            }

            return new TapeChartCelda(
                fecha: $fecha,
                estadoPrincipal: TapeChartCelda::ESTADO_ESTADIA,
                subestado: ($estadiaActiva['estado'] === 'EN_CURSO') ? 'IN_HOUSE' : 'FINALIZADA',
                codigoReferencia: $estadiaActiva['codigo'],
                referenciaId: (int) $estadiaActiva['id'],
                origenTipo: 'ESTADIA',
                titularNombre: $this->formatearNombreCorto($estadiaActiva['responsable_apellido'], $estadiaActiva['responsable_nombres']),
                titularDocumento: $estadiaActiva['responsable_documento'] ?: null,
                duracionNoches: $nochesEst,
                nocheIndice: $nocheIdx,
                esInicioBloque: ($fecha === $estadiaActiva['fecha_entrada']),
                esFinBloque: false,
                esArrival: $esArrival,
                esDeparture: $esDeparture,
                esStayover: $esStayover,
                indicadoresSecundarios: $indicadoresSecundarios,
                conflictos: $conflictos,
                accionesCandidatas: $accionesCandidatas,
                claseColor: 'tape-celda-estadia'
            );
        }

        if ($reservaActiva !== null) {
            // 4. RESERVA CONFIRMADA O HOLD
            $esHold = ($reservaActiva['estado'] === 'PENDIENTE');
            $estadoP = $esHold ? TapeChartCelda::ESTADO_HOLD_PENDIENTE : TapeChartCelda::ESTADO_RESERVA;
            $subest = $esHold ? 'HOLD' : 'CONFIRMADA';
            $nochesRes = max(1, (int) $reservaActiva['noches']);
            $nocheIdx = max(1, (int) ((strtotime($fecha) - strtotime($reservaActiva['fecha_entrada'])) / 86400) + 1);

            if ($puedeVerReserva) {
                $accionesCandidatas[] = 'VER_RESERVA';
            }

            // Check-in rápido candidato si es hoy, llegada y está en VR
            if ($esFechaHoy && $esArrival && $limpHoyCodigo === 'VR' && $puedeCheckin && !$esHold) {
                $accionesCandidatas[] = 'CHECKIN_RAPIDO';
            }

            return new TapeChartCelda(
                fecha: $fecha,
                estadoPrincipal: $estadoP,
                subestado: $subest,
                codigoReferencia: $reservaActiva['codigo'],
                referenciaId: (int) $reservaActiva['id'],
                origenTipo: 'RESERVA',
                titularNombre: $this->formatearNombreCorto($reservaActiva['titular_apellido'], $reservaActiva['titular_nombres']),
                titularDocumento: $reservaActiva['titular_documento'] ?: null,
                duracionNoches: $nochesRes,
                nocheIndice: $nocheIdx,
                esInicioBloque: ($fecha === $reservaActiva['fecha_entrada']),
                esFinBloque: false,
                esArrival: $esArrival,
                esDeparture: false,
                esStayover: false,
                indicadoresSecundarios: $indicadoresSecundarios,
                conflictos: $conflictos,
                accionesCandidatas: $accionesCandidatas,
                claseColor: $esHold ? 'tape-celda-hold' : 'tape-celda-reserva'
            );
        }

        // 5. Bloqueo administrativo manual en inventario sparse
        if ($inventarioFila !== null && $inventarioFila['tipo_bloqueo'] === 'BLOQUEO_MANUAL') {
            if ($puedeLiberar) {
                $accionesCandidatas[] = 'LIBERAR_BLOQUEO';
            }

            return new TapeChartCelda(
                fecha: $fecha,
                estadoPrincipal: TapeChartCelda::ESTADO_BLOQUEO_MANUAL,
                subestado: 'BLOQUEADO',
                codigoReferencia: "BLOQ-{$inventarioFila['origen_id']}",
                referenciaId: (int) $inventarioFila['origen_id'],
                origenTipo: 'BLOQUEO_MANUAL',
                titularNombre: 'Bloqueo Manual',
                titularDocumento: null,
                duracionNoches: 1,
                nocheIndice: 1,
                esInicioBloque: true,
                esFinBloque: true,
                esArrival: false,
                esDeparture: false,
                esStayover: false,
                indicadoresSecundarios: $indicadoresSecundarios,
                conflictos: $conflictos,
                accionesCandidatas: $accionesCandidatas,
                claseColor: 'tape-celda-bloqueo'
            );
        }

        // 6. VACANTE / DISPONIBLE
        // D-084 / Ajuste 4: Proyectar VR/VD/VCL principalmente en Hoy
        $subestadoVacante = 'DISPONIBLE';
        $colorClase = 'tape-celda-vacante';

        if ($esFechaHoy) {
            $subestadoVacante = $limpHoyCodigo; // VR, VD, VCL
            if ($limpHoyCodigo === 'VR') {
                $colorClase = 'tape-celda-vr';
                if ($puedeCheckin && $esArrival) {
                    $accionesCandidatas[] = 'CHECKIN_RAPIDO';
                }
            } elseif ($limpHoyCodigo === 'VD') {
                $colorClase = 'tape-celda-vd';
                if ($puedeGestionarLimpieza) {
                    $accionesCandidatas[] = 'ASIGNAR_LIMPIEZA';
                }
            } elseif ($limpHoyCodigo === 'VCL') {
                $colorClase = 'tape-celda-vcl';
            }
        }

        if ($puedeBloquear) {
            $accionesCandidatas[] = 'BLOQUEAR';
        }
        $accionesCandidatas[] = 'NUEVA_RESERVA';

        return new TapeChartCelda(
            fecha: $fecha,
            estadoPrincipal: TapeChartCelda::ESTADO_VACANTE,
            subestado: $subestadoVacante,
            codigoReferencia: null,
            referenciaId: null,
            origenTipo: null,
            titularNombre: null,
            titularDocumento: null,
            duracionNoches: 1,
            nocheIndice: 1,
            esInicioBloque: true,
            esFinBloque: true,
            esArrival: $esArrival,
            esDeparture: $esDeparture,
            esStayover: false,
            indicadoresSecundarios: $indicadoresSecundarios,
            conflictos: $conflictos,
            accionesCandidatas: $accionesCandidatas,
            claseColor: $colorClase
        );
    }

    /**
     * Resuelve el código, texto legible y clase visual Alina para un estado de limpieza.
     *
     * @param string $estadoLimp
     * @return array{0: string, 1: string, 2: string}
     */
    private function resolverEtiquetaLimpieza(string $estadoLimp): array
    {
        return match ($estadoLimp) {
            HousekeepingEstadoLimpieza::ESTADO_LIMPIA_INSPECCIONADA => [
                'VR',
                'Limpia / Lista (VR)',
                'bg-light-success text-success',
            ],
            HousekeepingEstadoLimpieza::ESTADO_SUCIA => [
                'VD',
                'Sucia (VD)',
                'bg-light-danger text-danger',
            ],
            HousekeepingEstadoLimpieza::ESTADO_EN_LIMPIEZA => [
                'VCL',
                'En Limpieza',
                'bg-light-info text-info',
            ],
            HousekeepingEstadoLimpieza::ESTADO_LIMPIA_POR_INSPECCIONAR => [
                'VCL',
                'Por Inspeccionar',
                'bg-light-warning text-warning',
            ],
            HousekeepingEstadoLimpieza::ESTADO_RETOQUE_REQUERIDO => [
                'VD',
                'Retoque Requerido',
                'bg-light-danger text-danger',
            ],
            default => [
                'VD',
                'Sucia (VD)',
                'bg-light-danger text-danger',
            ],
        };
    }

    /**
     * Formatea un nombre para visualización mínima y segura en la celda (Ajuste 13).
     */
    private function formatearNombreCorto(?string $apellido, ?string $nombres): string
    {
        $apellido = trim((string) $apellido);
        $nombres = trim((string) $nombres);

        if ($apellido === '' && $nombres === '') {
            return 'Huésped';
        }

        if ($apellido !== '' && $nombres !== '') {
            $primerNombre = explode(' ', $nombres)[0];
            return "{$apellido}, {$primerNombre}";
        }

        return $apellido ?: $nombres;
    }

    /**
     * Valida la coherencia del intervalo hotelero de consulta.
     */
    private function validarIntervalo(string $desde, string $hasta): void
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
            throw new InvalidArgumentException('El formato de las fechas debe ser estrictamente YYYY-MM-DD.');
        }

        if ($hasta <= $desde) {
            throw new InvalidArgumentException('La fecha de fin debe ser estrictamente posterior a la fecha de inicio.');
        }

        $dias = (int) ((strtotime($hasta) - strtotime($desde)) / 86400);
        if ($dias > 60) {
            throw new InvalidArgumentException('El horizonte máximo de consulta para el Tape Chart es de 60 días.');
        }
    }
}
