<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

use CamargoPMS\Excepciones\ArrendamientoNoEncontradoExcepcion;
use CamargoPMS\Excepciones\ConflictoDisponibilidadExcepcion;
use CamargoPMS\Excepciones\EstadoArrendamientoInvalidoExcepcion;
use CamargoPMS\Excepciones\GarantiaInvalidaExcepcion;
use CamargoPMS\Excepciones\UnidadNoEncontradaExcepcion;
use CamargoPMS\Excepciones\ValidacionExcepcion;
use CamargoPMS\Modelos\Arrendamiento;
use CamargoPMS\Modelos\ArrendamientoCuota;
use CamargoPMS\Modelos\ArrendamientoGarantia;
use CamargoPMS\Modelos\ArrendamientoHistorialEstado;
use CamargoPMS\Modelos\ArrendamientoPersona;
use CamargoPMS\Modelos\CargoCuenta;
use CamargoPMS\Modelos\CuentaFolio;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Repositorios\ArrendamientoCuotaRepositorio;
use CamargoPMS\Repositorios\ArrendamientoGarantiaRepositorio;
use CamargoPMS\Repositorios\ArrendamientoHistorialEstadoRepositorio;
use CamargoPMS\Repositorios\ArrendamientoPersonaRepositorio;
use CamargoPMS\Repositorios\ArrendamientoRepositorio;
use CamargoPMS\Repositorios\CargoCuentaRepositorio;
use CamargoPMS\Repositorios\CuentaFolioRepositorio;
use CamargoPMS\Repositorios\DisponibilidadRepositorio;
use CamargoPMS\Repositorios\PersonaRepositorio;
use CamargoPMS\Repositorios\UnidadRepositorio;
use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use PDO;

/**
 * Servicio de dominio central para la gestión de contratos de arrendamiento patrimonial.
 * 
 * Reglas vinculantes D-076:
 * - Separación ontológica: RESERVA != ESTADÍA != ARRENDAMIENTO.
 * - Temporalidad a plazo cerrado en V1 (fecha_fin NOT NULL, fecha_fin > fecha_inicio).
 * - Semántica hotelera semiabierta [fecha_inicio, fecha_fin). fecha_fin queda libre para nuevas reservas.
 * - Materialización sparse total en inventario_diario_unidades (tipo_bloqueo = 'ARRENDAMIENTO').
 * - Idempotencia recurrente en cuotas de renta.
 * - Custodia segregada de garantía con saldo reconstructible.
 * - Compatibilidad estricta con folios y FINANCIERO-2.
 */
class ArrendamientoServicio
{
    private PDO $pdo;
    private ArrendamientoRepositorio $arrendamientoRepo;
    private ArrendamientoPersonaRepositorio $personaRepo;
    private ArrendamientoCuotaRepositorio $cuotaRepo;
    private ArrendamientoGarantiaRepositorio $garantiaRepo;
    private ArrendamientoHistorialEstadoRepositorio $historialRepo;
    private DisponibilidadRepositorio $disponibilidadRepo;
    private CuentaFolioRepositorio $cuentaFolioRepo;
    private CargoCuentaRepositorio $cargoCuentaRepo;
    private UnidadRepositorio $unidadRepo;
    private PersonaRepositorio $personaTitularRepo;

    public function __construct(
        ?PDO $pdo = null,
        ?ArrendamientoRepositorio $arrendamientoRepo = null,
        ?ArrendamientoPersonaRepositorio $personaRepo = null,
        ?ArrendamientoCuotaRepositorio $cuotaRepo = null,
        ?ArrendamientoGarantiaRepositorio $garantiaRepo = null,
        ?ArrendamientoHistorialEstadoRepositorio $historialRepo = null,
        ?DisponibilidadRepositorio $disponibilidadRepo = null,
        ?CuentaFolioRepositorio $cuentaFolioRepo = null,
        ?CargoCuentaRepositorio $cargoCuentaRepo = null,
        ?UnidadRepositorio $unidadRepo = null,
        ?PersonaRepositorio $personaTitularRepo = null
    ) {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->arrendamientoRepo = $arrendamientoRepo ?? new ArrendamientoRepositorio($this->pdo);
        $this->personaRepo = $personaRepo ?? new ArrendamientoPersonaRepositorio($this->pdo);
        $this->cuotaRepo = $cuotaRepo ?? new ArrendamientoCuotaRepositorio($this->pdo);
        $this->garantiaRepo = $garantiaRepo ?? new ArrendamientoGarantiaRepositorio($this->pdo);
        $this->historialRepo = $historialRepo ?? new ArrendamientoHistorialEstadoRepositorio($this->pdo);
        $this->disponibilidadRepo = $disponibilidadRepo ?? new DisponibilidadRepositorio($this->pdo);
        $this->cuentaFolioRepo = $cuentaFolioRepo ?? new CuentaFolioRepositorio($this->pdo);
        $this->cargoCuentaRepo = $cargoCuentaRepo ?? new CargoCuentaRepositorio($this->pdo);
        $this->unidadRepo = $unidadRepo ?? new UnidadRepositorio($this->pdo);
        $this->personaTitularRepo = $personaTitularRepo ?? new PersonaRepositorio($this->pdo);
    }

    /**
     * Registra un nuevo contrato de arrendamiento en estado BORRADOR con su titular y partes.
     * 
     * @param array<string, mixed> $datos
     */
    public function crearArrendamiento(array $datos, int $actorId): Arrendamiento
    {
        $unidadId = (int) ($datos['unidad_id'] ?? 0);
        $unidad = $this->unidadRepo->buscarPorId($unidadId);
        if (!$unidad) {
            throw new UnidadNoEncontradaExcepcion("La unidad habitacional con ID {$unidadId} no existe.");
        }
        if (!$unidad->estaActiva()) {
            throw new ValidacionExcepcion("La unidad {$unidad->obtenerCodigo()} se encuentra inactiva.");
        }

        $fechaInicioStr = trim((string) ($datos['fecha_inicio'] ?? ''));
        $fechaFinStr = trim((string) ($datos['fecha_fin'] ?? ''));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaInicioStr) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaFinStr)) {
            throw new ValidacionExcepcion('Las fechas de inicio y fin deben tener formato YYYY-MM-DD válido.');
        }

        $fechaInicio = new DateTimeImmutable($fechaInicioStr);
        $fechaFin = new DateTimeImmutable($fechaFinStr);

        if ($fechaFin <= $fechaInicio) {
            throw new ValidacionExcepcion('La fecha de fin debe ser posterior a la fecha de inicio.');
        }

        $diaVencimiento = (int) ($datos['dia_vencimiento'] ?? 1);
        if ($diaVencimiento < 1 || $diaVencimiento > 31) {
            throw new ValidacionExcepcion('El día de vencimiento contractual debe estar entre 1 y 31.');
        }

        $rentaMensual = number_format((float) ($datos['renta_mensual'] ?? 0), 2, '.', '');
        if (bccomp($rentaMensual, '0.00', 2) <= 0) {
            throw new ValidacionExcepcion('La renta mensual pactada debe ser mayor a 0.00.');
        }

        $depositoGarantia = number_format((float) ($datos['deposito_garantia'] ?? 0), 2, '.', '');
        if (bccomp($depositoGarantia, '0.00', 2) < 0) {
            throw new ValidacionExcepcion('El depósito de garantía no puede ser negativo.');
        }

        $titularPersonaId = (int) ($datos['titular_persona_id'] ?? 0);
        if ($titularPersonaId <= 0 || !$this->personaTitularRepo->buscarPorId($titularPersonaId)) {
            throw new ValidacionExcepcion('Debe especificar un titular contractual válido registrado en el sistema.');
        }

        // Determinar prorrateo del primer período
        $esProrrateado = !empty($datos['es_primer_mes_prorrateado']);
        $diaInicio = (int) $fechaInicio->format('j');
        if ($diaInicio > 1 && !isset($datos['es_primer_mes_prorrateado'])) {
            $esProrrateado = true;
        }

        if (isset($datos['monto_primer_periodo']) && is_numeric($datos['monto_primer_periodo'])) {
            $montoPrimerPeriodo = number_format((float) $datos['monto_primer_periodo'], 2, '.', '');
        } else {
            if ($esProrrateado) {
                $diasMes = (int) cal_days_in_month(CAL_GREGORIAN, (int) $fechaInicio->format('n'), (int) $fechaInicio->format('Y'));
                $diasOcupados = $diasMes - $diaInicio + 1;
                $rentaDiaria = bcdiv($rentaMensual, (string) $diasMes, 6);
                $montoPrimerPeriodo = bcmul($rentaDiaria, (string) $diasOcupados, 2);
            } else {
                $montoPrimerPeriodo = $rentaMensual;
            }
        }

        $codigo = $this->arrendamientoRepo->generarSiguienteCodigo();
        $monedaCodigo = trim((string) ($datos['moneda_codigo'] ?? 'PEN'));
        $notas = isset($datos['notas_adicionales']) ? trim((string) $datos['notas_adicionales']) : null;
        $arrendamientoAnteriorId = isset($datos['arrendamiento_anterior_id']) && (int) $datos['arrendamiento_anterior_id'] > 0 
            ? (int) $datos['arrendamiento_anterior_id'] 
            : null;

        $arrendamiento = new Arrendamiento(
            null,
            $codigo,
            $unidadId,
            $arrendamientoAnteriorId,
            $fechaInicioStr,
            $fechaFinStr,
            $diaVencimiento,
            $rentaMensual,
            $depositoGarantia,
            $montoPrimerPeriodo,
            $esProrrateado,
            $monedaCodigo,
            'BORRADOR',
            null,
            null,
            null,
            $notas,
            $actorId
        );

        $this->pdo->beginTransaction();
        try {
            $arrendamientoId = $this->arrendamientoRepo->crear($arrendamiento);

            // Asociar Titular
            $titularRelacion = new ArrendamientoPersona(
                $arrendamientoId,
                $titularPersonaId,
                'TITULAR',
                'Titular del contrato de arrendamiento'
            );
            $this->personaRepo->asociar($titularRelacion);

            // Asociar cotitulares u ocupantes si fueron suministrados
            if (!empty($datos['cotitulares']) && is_array($datos['cotitulares'])) {
                foreach ($datos['cotitulares'] as $cotitularId) {
                    $cId = (int) $cotitularId;
                    if ($cId > 0 && $cId !== $titularPersonaId && $this->personaTitularRepo->buscarPorId($cId)) {
                        $this->personaRepo->asociar(new ArrendamientoPersona($arrendamientoId, $cId, 'COTITULAR'));
                    }
                }
            }

            if (!empty($datos['ocupantes']) && is_array($datos['ocupantes'])) {
                foreach ($datos['ocupantes'] as $ocupanteId) {
                    $oId = (int) $ocupanteId;
                    if ($oId > 0 && $oId !== $titularPersonaId && $this->personaTitularRepo->buscarPorId($oId)) {
                        $this->personaRepo->asociar(new ArrendamientoPersona($arrendamientoId, $oId, 'OCUPANTE'));
                    }
                }
            }

            // Trazabilidad inmutable de creación
            $this->historialRepo->registrar(new ArrendamientoHistorialEstado(
                null,
                $arrendamientoId,
                'BORRADOR',
                'BORRADOR',
                'Creación de borrador contractual de arrendamiento',
                $actorId
            ));

            $this->pdo->commit();

            return $this->arrendamientoRepo->obtenerPorId($arrendamientoId);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Agrega una persona (COTITULAR u OCUPANTE) a un contrato de arrendamiento.
     */
    public function agregarPersona(
        int $arrendamientoId,
        int $personaId,
        string $tipoRelacion,
        ?string $observaciones,
        int $actorId
    ): void {
        $arrendamiento = $this->arrendamientoRepo->obtenerPorId($arrendamientoId);
        if (!$arrendamiento) {
            throw new ArrendamientoNoEncontradoExcepcion();
        }
        if ($arrendamiento->esFinalizado() || $arrendamiento->esCancelado() || $arrendamiento->esRescindido()) {
            throw new EstadoArrendamientoInvalidoExcepcion($arrendamiento->obtenerEstado(), 'AGREGAR_PERSONA');
        }

        if (!in_array($tipoRelacion, ['COTITULAR', 'OCUPANTE'], true)) {
            throw new ValidacionExcepcion("Solo se permite asociar cotitulares u ocupantes. El titular es único.");
        }

        if (!$this->personaTitularRepo->buscarPorId($personaId)) {
            throw new ValidacionExcepcion("La persona con ID {$personaId} no existe.");
        }

        if ($this->personaRepo->existePersonaEnArrendamiento($arrendamientoId, $personaId)) {
            throw new ValidacionExcepcion("La persona ya se encuentra vinculada a este contrato de arrendamiento.");
        }

        $this->personaRepo->asociar(new ArrendamientoPersona(
            $arrendamientoId,
            $personaId,
            $tipoRelacion,
            $observaciones
        ));
    }

    /**
     * Remueve a una persona (COTITULAR u OCUPANTE) del contrato. El TITULAR no se puede remover.
     */
    public function quitarPersona(int $arrendamientoId, int $personaId, int $actorId): void
    {
        $titular = $this->personaRepo->obtenerTitular($arrendamientoId);
        if ($titular && $titular->obtenerPersonaId() === $personaId) {
            throw new ValidacionExcepcion('No se puede remover al titular del contrato de arrendamiento.');
        }

        $this->personaRepo->eliminar($arrendamientoId, $personaId);
    }

    /**
     * Activa el contrato de arrendamiento:
     * - Bloqueo pesimista FOR UPDATE.
     * - Verificación de ausencia de colisiones en intervalo semiabierto [fecha_inicio, fecha_fin).
     * - Materialización total de noches en inventario_diario_unidades con tipo_bloqueo = 'ARRENDAMIENTO'.
     * - Creación de CuentaFolio financiera (arrendamiento_id NOT NULL, reserva_id NULL).
     * - Creación de Custodia Segregada de Garantía (arrendamiento_garantias).
     * - Generación de primer cargo y cuota de renta.
     * - Generación de cargo por depósito en garantía (si aplica).
     * - Transición a VIGENTE e historial inmutable.
     * 
     * @return array<string, mixed>
     */
    public function activarArrendamiento(int $arrendamientoId, int $actorId): array
    {
        $this->pdo->beginTransaction();
        try {
            $arrendamiento = $this->arrendamientoRepo->obtenerPorId($arrendamientoId, true);
            if (!$arrendamiento) {
                throw new ArrendamientoNoEncontradoExcepcion();
            }

            if (!$arrendamiento->esBorrador()) {
                throw new EstadoArrendamientoInvalidoExcepcion($arrendamiento->obtenerEstado(), 'ACTIVAR');
            }

            $titular = $this->personaRepo->obtenerTitular($arrendamientoId);
            if (!$titular) {
                throw new ValidacionExcepcion('El arrendamiento no cuenta con un titular contractual asignado.');
            }

            // Calcular noches en intervalo semiabierto [fecha_inicio, fecha_fin)
            $inicio = new DateTimeImmutable($arrendamiento->obtenerFechaInicio());
            $fin = new DateTimeImmutable($arrendamiento->obtenerFechaFin());
            $intervalo = new DateInterval('P1D');
            $periodo = new DatePeriod($inicio, $intervalo, $fin);

            $noches = [];
            foreach ($periodo as $dt) {
                $noches[] = $dt->format('Y-m-d');
            }

            if (empty($noches)) {
                throw new ValidacionExcepcion('El intervalo de fechas no genera ninguna noche para materializar.');
            }

            // Bloqueo pesimista y verificación de colisión atómica en BD
            $unidadId = $arrendamiento->obtenerUnidadId();
            $inPlaceholders = implode(',', array_fill(0, count($noches), '?'));
            $sqlCheck = "SELECT fecha, tipo_bloqueo, origen_tipo 
                         FROM inventario_diario_unidades 
                         WHERE unidad_id = ? AND fecha IN ($inPlaceholders) 
                         FOR UPDATE";
            $stmtCheck = $this->pdo->prepare($sqlCheck);
            $stmtCheck->execute(array_merge([$unidadId], $noches));
            $colisiones = $stmtCheck->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($colisiones)) {
                $fechaColision = $colisiones[0]['fecha'];
                $tipoColision = $colisiones[0]['tipo_bloqueo'];
                throw new ConflictoDisponibilidadExcepcion(
                    $unidadId,
                    $fechaColision,
                    "Colisión de disponibilidad: la unidad habitacional no está libre en la noche {$fechaColision} ({$tipoColision})."
                );
            }

            // Materializar cada noche sparse en inventario_diario_unidades
            foreach ($noches as $fechaNoche) {
                $this->disponibilidadRepo->insertarInventarioNoche(
                    $unidadId,
                    $fechaNoche,
                    'ARRENDAMIENTO',
                    'ARRENDAMIENTO',
                    $arrendamientoId
                );
            }

            // 1. Crear Folio Financiero
            $folioCodigo = $this->cuentaFolioRepo->generarSiguienteCodigo();
            $folio = new CuentaFolio(
                null,
                $folioCodigo,
                null, // reserva_id es NULL
                $titular->obtenerPersonaId(),
                $arrendamiento->obtenerMonedaCodigo(),
                'ABIERTA',
                $actorId,
                $arrendamientoId // arrendamiento_id
            );
            $folioId = $this->cuentaFolioRepo->crear($folio);

            // 2. Crear Custodia Segregada de Garantía
            $depositoPactado = $arrendamiento->obtenerDepositoGarantia();
            $estadoGarantia = bccomp($depositoPactado, '0.00', 2) > 0 ? 'PENDIENTE' : 'LIQUIDADA';
            $garantia = new ArrendamientoGarantia(
                null,
                $arrendamientoId,
                $depositoPactado,
                '0.00',
                '0.00',
                '0.00',
                '0.00',
                '0.00',
                $estadoGarantia
            );
            $garantiaId = $this->garantiaRepo->crear($garantia);

            // 3. Crear Cargo Inicial y Cuota de Renta
            $anioInicio = (int) $inicio->format('Y');
            $mesInicio = (int) $inicio->format('n');
            $periodoCodigo = sprintf('%04d-%02d', $anioInicio, $mesInicio);
            $tipoCuota = $arrendamiento->esPrimerMesProrrateado() ? 'CUOTA_PRORRATEADA' : 'RENTA_MENSUAL';

            $cargoRentaCodigo = $this->cargoCuentaRepo->generarSiguienteCodigo();
            $conceptoRenta = "Renta Arrendamiento {$arrendamiento->obtenerCodigo()} - Período {$periodoCodigo}";
            $cargoRenta = new CargoCuenta(
                null,
                $cargoRentaCodigo,
                $folioId,
                'RENTA_ARRENDAMIENTO',
                $arrendamientoId,
                null, // estadia_id
                $conceptoRenta,
                '1.00',
                $arrendamiento->obtenerMontoPrimerPeriodo(),
                $arrendamiento->obtenerMontoPrimerPeriodo(),
                '0.00',
                $arrendamiento->obtenerMontoPrimerPeriodo(),
                '0.00',
                $arrendamiento->obtenerMonedaCodigo(),
                'DEVENGADO',
                null,
                null,
                null,
                date('Y-m-d H:i:s'),
                $actorId
            );
            $cargoRentaId = $this->cargoCuentaRepo->crear($cargoRenta);

            // Calcular fecha de vencimiento contractual
            $diasMes = (int) cal_days_in_month(CAL_GREGORIAN, $mesInicio, $anioInicio);
            $diaVenc = min($arrendamiento->obtenerDiaVencimiento(), $diasMes);
            $fechaVencimiento = sprintf('%04d-%02d-%02d', $anioInicio, $mesInicio, $diaVenc);
            $fechaEmision = $arrendamiento->obtenerFechaInicio();

            $cuotaInicial = new ArrendamientoCuota(
                null,
                $arrendamientoId,
                $anioInicio,
                $mesInicio,
                $periodoCodigo,
                $tipoCuota,
                $fechaEmision,
                $fechaVencimiento,
                $arrendamiento->obtenerMontoPrimerPeriodo(),
                $cargoRentaId,
                'PENDIENTE'
            );
            $cuotaInicialId = $this->cuotaRepo->crear($cuotaInicial);

            // 4. Si hay depósito pactado > 0, crear cargo en folio por la garantía
            $cargoGarantiaId = null;
            if (bccomp($depositoPactado, '0.00', 2) > 0) {
                $cargoGarantiaCodigo = $this->cargoCuentaRepo->generarSiguienteCodigo();
                $conceptoGarantia = "Depósito en Garantía - Arrendamiento {$arrendamiento->obtenerCodigo()}";
                $cargoGarantia = new CargoCuenta(
                    null,
                    $cargoGarantiaCodigo,
                    $folioId,
                    'DEPOSITO_GARANTIA',
                    $arrendamientoId,
                    null,
                    $conceptoGarantia,
                    '1.00',
                    $depositoPactado,
                    $depositoPactado,
                    '0.00',
                    $depositoPactado,
                    '0.00',
                    $arrendamiento->obtenerMonedaCodigo(),
                    'DEVENGADO',
                    null,
                    null,
                    null,
                    date('Y-m-d H:i:s'),
                    $actorId
                );
                $cargoGarantiaId = $this->cargoCuentaRepo->crear($cargoGarantia);
            }

            // 5. Transición a VIGENTE
            $this->arrendamientoRepo->actualizarEstado($arrendamientoId, 'VIGENTE');

            // 6. Registro inmutable en historial
            $this->historialRepo->registrar(new ArrendamientoHistorialEstado(
                null,
                $arrendamientoId,
                'BORRADOR',
                'VIGENTE',
                'Activación de contrato de arrendamiento y materialización de inventario diario',
                $actorId
            ));

            $this->pdo->commit();

            return [
                'arrendamiento_id' => $arrendamientoId,
                'estado' => 'VIGENTE',
                'folio_id' => $folioId,
                'folio_codigo' => $folioCodigo,
                'garantia_id' => $garantiaId,
                'cuota_inicial_id' => $cuotaInicialId,
                'cargo_renta_id' => $cargoRentaId,
                'cargo_garantia_id' => $cargoGarantiaId,
                'noches_materializadas' => count($noches),
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Emite una cuota mensual recurrente de forma IDEMPOTENTE.
     */
    public function generarCuotaMensual(int $arrendamientoId, int $anio, int $mes, int $actorId): ArrendamientoCuota
    {
        $this->pdo->beginTransaction();
        try {
            $arrendamiento = $this->arrendamientoRepo->obtenerPorId($arrendamientoId, true);
            if (!$arrendamiento) {
                throw new ArrendamientoNoEncontradoExcepcion();
            }

            if (!$arrendamiento->esVigente()) {
                throw new EstadoArrendamientoInvalidoExcepcion($arrendamiento->obtenerEstado(), 'GENERAR_CUOTA');
            }

            if ($mes < 1 || $mes > 12) {
                throw new ValidacionExcepcion("Mes inválido: {$mes}.");
            }

            // Validar que el período solicitado esté comprendido en el rango contractual
            $fechaInicio = new DateTimeImmutable($arrendamiento->obtenerFechaInicio());
            $fechaFin = new DateTimeImmutable($arrendamiento->obtenerFechaFin());
            $fechaPeriodoInicio = new DateTimeImmutable(sprintf('%04d-%02d-01', $anio, $mes));

            if ($fechaPeriodoInicio < new DateTimeImmutable($fechaInicio->format('Y-m-01')) ||
                $fechaPeriodoInicio > new DateTimeImmutable($fechaFin->format('Y-m-01'))) {
                throw new ValidacionExcepcion("El período {$anio}-{$mes} excede la vigencia del contrato ({$arrendamiento->obtenerFechaInicio()} a {$arrendamiento->obtenerFechaFin()}).");
            }

            // Blindaje de Idempotencia en capa PHP antes de intentar inserción
            $cuotaExistente = $this->cuotaRepo->obtenerPorPeriodoYTipo($arrendamientoId, $anio, $mes, 'RENTA_MENSUAL');
            if ($cuotaExistente) {
                $this->pdo->commit();
                return $cuotaExistente;
            }

            // Obtener folio asociado
            $folio = $this->cuentaFolioRepo->obtenerPorArrendamientoId($arrendamientoId);
            if (!$folio) {
                throw new ValidacionExcepcion("El contrato {$arrendamiento->obtenerCodigo()} no posee folio financiero activo.");
            }

            $periodoCodigo = sprintf('%04d-%02d', $anio, $mes);
            $diasMes = (int) cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
            $diaVenc = min($arrendamiento->obtenerDiaVencimiento(), $diasMes);
            $fechaVencimiento = sprintf('%04d-%02d-%02d', $anio, $mes, $diaVenc);
            $fechaEmision = sprintf('%04d-%02d-01', $anio, $mes);

            // Crear cargo en folio
            $cargoCodigo = $this->cargoCuentaRepo->generarSiguienteCodigo();
            $concepto = "Renta Arrendamiento {$arrendamiento->obtenerCodigo()} - Período {$periodoCodigo}";
            $cargo = new CargoCuenta(
                null,
                $cargoCodigo,
                (int) $folio->obtenerId(),
                'RENTA_ARRENDAMIENTO',
                $arrendamientoId,
                null,
                $concepto,
                '1.00',
                $arrendamiento->obtenerRentaMensual(),
                $arrendamiento->obtenerRentaMensual(),
                '0.00',
                $arrendamiento->obtenerRentaMensual(),
                '0.00',
                $arrendamiento->obtenerMonedaCodigo(),
                'DEVENGADO',
                null,
                null,
                null,
                date('Y-m-d H:i:s'),
                $actorId
            );
            $cargoId = $this->cargoCuentaRepo->crear($cargo);

            // Crear cuota en arrendamiento_cuotas
            $cuota = new ArrendamientoCuota(
                null,
                $arrendamientoId,
                $anio,
                $mes,
                $periodoCodigo,
                'RENTA_MENSUAL',
                $fechaEmision,
                $fechaVencimiento,
                $arrendamiento->obtenerRentaMensual(),
                $cargoId,
                'PENDIENTE'
            );
            $cuotaId = $this->cuotaRepo->crear($cuota);

            $this->pdo->commit();

            return $this->cuotaRepo->obtenerPorId($cuotaId);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Registra la recepción efectiva del depósito de garantía en la custodia segregada.
     */
    public function registrarRecepcionGarantia(int $arrendamientoId, string $monto, int $actorId): void
    {
        if (bccomp($monto, '0.00', 2) <= 0) {
            throw new ValidacionExcepcion('El monto de garantía recibido debe ser mayor a 0.00.');
        }

        $this->pdo->beginTransaction();
        try {
            $garantia = $this->garantiaRepo->obtenerPorArrendamientoId($arrendamientoId, true);
            if (!$garantia) {
                throw new GarantiaInvalidaExcepcion("No existe registro de garantía para el arrendamiento {$arrendamientoId}.");
            }

            $nuevoRecibido = bcadd($garantia->obtenerMontoRecibido(), $monto, 2);
            $nuevoRetenido = bcadd($garantia->obtenerMontoRetenidoActual(), $monto, 2);

            $this->garantiaRepo->actualizarSaldos(
                (int) $garantia->obtenerId(),
                $nuevoRecibido,
                $nuevoRetenido,
                $garantia->obtenerMontoCompensadoDanos(),
                $garantia->obtenerMontoCompensadoRenta(),
                $garantia->obtenerMontoDevuelto(),
                'CUSTODIADA'
            );

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Compensa fondos retenidos de garantía por concepto de daños físicos o impago de renta.
     * 
     * @param string $tipoCompensacion 'DANOS' | 'RENTA'
     */
    public function compensarGarantia(
        int $arrendamientoId,
        string $monto,
        string $tipoCompensacion,
        string $motivo,
        int $actorId
    ): void {
        if (bccomp($monto, '0.00', 2) <= 0) {
            throw new ValidacionExcepcion('El monto a compensar debe ser mayor a 0.00.');
        }
        if (!in_array($tipoCompensacion, ['DANOS', 'RENTA'], true)) {
            throw new ValidacionExcepcion("El tipo de compensación debe ser 'DANOS' o 'RENTA'.");
        }
        if (trim($motivo) === '') {
            throw new ValidacionExcepcion('Debe fundamentar el motivo de la compensación de fondos de garantía.');
        }

        $this->pdo->beginTransaction();
        try {
            $garantia = $this->garantiaRepo->obtenerPorArrendamientoId($arrendamientoId, true);
            if (!$garantia) {
                throw new GarantiaInvalidaExcepcion();
            }

            if (bccomp($monto, $garantia->obtenerMontoRetenidoActual(), 2) > 0) {
                throw new GarantiaInvalidaExcepcion(
                    "Monto a compensar ({$monto}) supera el saldo retenido en custodia ({$garantia->obtenerMontoRetenidoActual()})."
                );
            }

            $nuevoRetenido = bcsub($garantia->obtenerMontoRetenidoActual(), $monto, 2);
            $nuevoCompDanos = $garantia->obtenerMontoCompensadoDanos();
            $nuevoCompRenta = $garantia->obtenerMontoCompensadoRenta();

            if ($tipoCompensacion === 'DANOS') {
                $nuevoCompDanos = bcadd($nuevoCompDanos, $monto, 2);
            } else {
                $nuevoCompRenta = bcadd($nuevoCompRenta, $monto, 2);
            }

            $nuevoEstado = bccomp($nuevoRetenido, '0.00', 2) === 0 ? 'LIQUIDADA' : 'COMPENSADA_PARCIAL';

            $this->garantiaRepo->actualizarSaldos(
                (int) $garantia->obtenerId(),
                $garantia->obtenerMontoRecibido(),
                $nuevoRetenido,
                $nuevoCompDanos,
                $nuevoCompRenta,
                $garantia->obtenerMontoDevuelto(),
                $nuevoEstado
            );

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Registra la devolución efectiva de fondos en custodia al arrendatario.
     */
    public function devolverGarantia(int $arrendamientoId, string $monto, string $motivo, int $actorId): void
    {
        if (bccomp($monto, '0.00', 2) <= 0) {
            throw new ValidacionExcepcion('El monto a devolver debe ser mayor a 0.00.');
        }

        $this->pdo->beginTransaction();
        try {
            $garantia = $this->garantiaRepo->obtenerPorArrendamientoId($arrendamientoId, true);
            if (!$garantia) {
                throw new GarantiaInvalidaExcepcion();
            }

            if (bccomp($monto, $garantia->obtenerMontoRetenidoActual(), 2) > 0) {
                throw new GarantiaInvalidaExcepcion(
                    "Monto a devolver ({$monto}) supera el saldo disponible retenido ({$garantia->obtenerMontoRetenidoActual()})."
                );
            }

            $nuevoRetenido = bcsub($garantia->obtenerMontoRetenidoActual(), $monto, 2);
            $nuevoDevuelto = bcadd($garantia->obtenerMontoDevuelto(), $monto, 2);
            $nuevoEstado = bccomp($nuevoRetenido, '0.00', 2) === 0 ? 'LIQUIDADA' : 'COMPENSADA_PARCIAL';

            $this->garantiaRepo->actualizarSaldos(
                (int) $garantia->obtenerId(),
                $garantia->obtenerMontoRecibido(),
                $nuevoRetenido,
                $garantia->obtenerMontoCompensadoDanos(),
                $garantia->obtenerMontoCompensadoRenta(),
                $nuevoDevuelto,
                $nuevoEstado
            );

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Prorroga un contrato vigente ampliando su fecha_fin y materializando las nuevas noches.
     */
    public function prorrogarArrendamiento(int $arrendamientoId, string $nuevaFechaFin, int $actorId): void
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $nuevaFechaFin)) {
            throw new ValidacionExcepcion('Formato de nueva fecha fin inválido (debe ser YYYY-MM-DD).');
        }

        $this->pdo->beginTransaction();
        try {
            $arrendamiento = $this->arrendamientoRepo->obtenerPorId($arrendamientoId, true);
            if (!$arrendamiento) {
                throw new ArrendamientoNoEncontradoExcepcion();
            }

            if (!$arrendamiento->esVigente()) {
                throw new EstadoArrendamientoInvalidoExcepcion($arrendamiento->obtenerEstado(), 'PRORROGAR');
            }

            $fechaFinActual = new DateTimeImmutable($arrendamiento->obtenerFechaFin());
            $fechaFinNueva = new DateTimeImmutable($nuevaFechaFin);

            if ($fechaFinNueva <= $fechaFinActual) {
                throw new ValidacionExcepcion("La nueva fecha fin ({$nuevaFechaFin}) debe ser estrictamente posterior a la actual ({$arrendamiento->obtenerFechaFin()}).");
            }

            // Intervalo del tramo prorrogado: semiabierto [fechaFinActual, fechaFinNueva)
            $intervalo = new DateInterval('P1D');
            $periodoExtra = new DatePeriod($fechaFinActual, $intervalo, $fechaFinNueva);

            $nochesExtra = [];
            foreach ($periodoExtra as $dt) {
                $nochesExtra[] = $dt->format('Y-m-d');
            }

            if (!empty($nochesExtra)) {
                $unidadId = $arrendamiento->obtenerUnidadId();
                $inPlaceholders = implode(',', array_fill(0, count($nochesExtra), '?'));
                $sqlCheck = "SELECT fecha, tipo_bloqueo FROM inventario_diario_unidades WHERE unidad_id = ? AND fecha IN ($inPlaceholders) FOR UPDATE";
                $stmtCheck = $this->pdo->prepare($sqlCheck);
                $stmtCheck->execute(array_merge([$unidadId], $nochesExtra));
                $colisiones = $stmtCheck->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($colisiones)) {
                    throw new ConflictoDisponibilidadExcepcion(
                        $unidadId,
                        (string) $colisiones[0]['fecha'],
                        "No se puede prorrogar: la unidad no está libre en la noche {$colisiones[0]['fecha']} ({$colisiones[0]['tipo_bloqueo']})."
                    );
                }

                foreach ($nochesExtra as $fechaNoche) {
                    $this->disponibilidadRepo->insertarInventarioNoche(
                        $unidadId,
                        $fechaNoche,
                        'ARRENDAMIENTO',
                        'ARRENDAMIENTO',
                        $arrendamientoId
                    );
                }
            }

            $this->arrendamientoRepo->actualizarFechaFin($arrendamientoId, $nuevaFechaFin);

            $this->historialRepo->registrar(new ArrendamientoHistorialEstado(
                null,
                $arrendamientoId,
                'VIGENTE',
                'VIGENTE',
                "Prórroga de contrato hasta el {$nuevaFechaFin}",
                $actorId
            ));

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Rescisión anticipada con causa:
     * - Las noches futuras (>= fechaEfectiva) se liberan eliminándose de inventario_diario_unidades.
     * - Las noches pasadas (< fechaEfectiva) quedan intactas en inventario.
     * - Actualiza estado a RESCINDIDO e historial inmutable.
     */
    public function rescindirArrendamiento(
        int $arrendamientoId,
        string $fechaEfectiva,
        string $motivo,
        int $actorId
    ): void {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaEfectiva)) {
            throw new ValidacionExcepcion('Formato de fecha efectiva inválido (debe ser YYYY-MM-DD).');
        }
        if (trim($motivo) === '') {
            throw new ValidacionExcepcion('El motivo de la rescisión anticipada es obligatorio.');
        }

        $this->pdo->beginTransaction();
        try {
            $arrendamiento = $this->arrendamientoRepo->obtenerPorId($arrendamientoId, true);
            if (!$arrendamiento) {
                throw new ArrendamientoNoEncontradoExcepcion();
            }

            if (!$arrendamiento->esVigente()) {
                throw new EstadoArrendamientoInvalidoExcepcion($arrendamiento->obtenerEstado(), 'RESCINDIR');
            }

            $dtInicio = new DateTimeImmutable($arrendamiento->obtenerFechaInicio());
            $dtFin = new DateTimeImmutable($arrendamiento->obtenerFechaFin());
            $dtEfectiva = new DateTimeImmutable($fechaEfectiva);

            if ($dtEfectiva < $dtInicio || $dtEfectiva > $dtFin) {
                throw new ValidacionExcepcion("La fecha de rescisión debe encontrarse dentro de la vigencia del contrato.");
            }

            // Liberar solo noches futuras a partir de la fecha efectiva
            $this->disponibilidadRepo->eliminarInventarioPorOrigenDesdeFecha(
                'ARRENDAMIENTO',
                $arrendamientoId,
                $fechaEfectiva
            );

            // Si la fecha efectiva es anterior a la fecha fin pactada, recortar la fecha fin
            if ($dtEfectiva < $dtFin) {
                $this->arrendamientoRepo->actualizarFechaFin($arrendamientoId, $fechaEfectiva);
            }

            $this->arrendamientoRepo->actualizarEstado($arrendamientoId, 'RESCINDIDO', $motivo, $actorId);

            $this->historialRepo->registrar(new ArrendamientoHistorialEstado(
                null,
                $arrendamientoId,
                'VIGENTE',
                'RESCINDIDO',
                "Rescisión contractual: {$motivo}",
                $actorId
            ));

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Finaliza contractualmente un arrendamiento por cumplimiento regular de plazo.
     */
    public function finalizarArrendamiento(int $arrendamientoId, int $actorId): void
    {
        $this->pdo->beginTransaction();
        try {
            $arrendamiento = $this->arrendamientoRepo->obtenerPorId($arrendamientoId, true);
            if (!$arrendamiento) {
                throw new ArrendamientoNoEncontradoExcepcion();
            }

            if (!$arrendamiento->esVigente()) {
                throw new EstadoArrendamientoInvalidoExcepcion($arrendamiento->obtenerEstado(), 'FINALIZAR');
            }

            $this->arrendamientoRepo->actualizarEstado($arrendamientoId, 'FINALIZADO');

            $this->historialRepo->registrar(new ArrendamientoHistorialEstado(
                null,
                $arrendamientoId,
                'VIGENTE',
                'FINALIZADO',
                'Finalización regular de contrato de arrendamiento',
                $actorId
            ));

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Cancela un contrato en estado BORRADOR.
     */
    public function cancelarBorrador(int $arrendamientoId, string $motivo, int $actorId): void
    {
        $this->pdo->beginTransaction();
        try {
            $arrendamiento = $this->arrendamientoRepo->obtenerPorId($arrendamientoId, true);
            if (!$arrendamiento) {
                throw new ArrendamientoNoEncontradoExcepcion();
            }

            if (!$arrendamiento->esBorrador()) {
                throw new EstadoArrendamientoInvalidoExcepcion($arrendamiento->obtenerEstado(), 'CANCELAR_BORRADOR');
            }

            $this->arrendamientoRepo->actualizarEstado($arrendamientoId, 'CANCELADO', $motivo, $actorId);

            $this->historialRepo->registrar(new ArrendamientoHistorialEstado(
                null,
                $arrendamientoId,
                'BORRADOR',
                'CANCELADO',
                "Cancelación de borrador: {$motivo}",
                $actorId
            ));

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Consulta detallada de un arrendamiento con sus partes, cuotas, garantía e historial.
     * 
     * @return array<string, mixed>
     */
    public function obtenerDetalleCompleto(int $arrendamientoId): array
    {
        $arrendamiento = $this->arrendamientoRepo->obtenerPorId($arrendamientoId);
        if (!$arrendamiento) {
            throw new ArrendamientoNoEncontradoExcepcion();
        }

        $personas = $this->personaRepo->listarPorArrendamientoId($arrendamientoId);
        $cuotas = $this->cuotaRepo->listarPorArrendamientoId($arrendamientoId);
        $garantia = $this->garantiaRepo->obtenerPorArrendamientoId($arrendamientoId);
        $historial = $this->historialRepo->listarPorArrendamientoId($arrendamientoId);
        $folio = $this->cuentaFolioRepo->obtenerPorArrendamientoId($arrendamientoId);

        return [
            'arrendamiento' => $arrendamiento->haciaArreglo(),
            'personas' => array_map(fn($p) => $p->haciaArreglo(), $personas),
            'cuotas' => array_map(fn($c) => $c->haciaArreglo(), $cuotas),
            'garantia' => $garantia ? $garantia->haciaArreglo() : null,
            'historial' => array_map(fn($h) => $h->haciaArreglo(), $historial),
            'folio' => $folio ? $folio->haciaArreglo() : null,
        ];
    }
}
