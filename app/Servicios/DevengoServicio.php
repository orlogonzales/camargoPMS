<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

use CamargoPMS\Excepciones\DevengoDuplicadoExcepcion;
use CamargoPMS\Excepciones\EntidadNoEncontradaExcepcion;
use CamargoPMS\Excepciones\OperacionInvalidaExcepcion;
use CamargoPMS\Modelos\CargoCuenta;
use CamargoPMS\Modelos\DevengoAlojamiento;
use CamargoPMS\Modelos\Estadia;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Repositorios\ActorAuditoriaRepositorio;
use CamargoPMS\Repositorios\CargoCuentaRepositorio;
use CamargoPMS\Repositorios\DevengoAlojamientoRepositorio;
use CamargoPMS\Repositorios\EstadiaRepositorio;
use CamargoPMS\Repositorios\ReservaRepositorio;
use DateTimeImmutable;
use PDO;
use Throwable;

/**
 * Servicio de dominio que gobierna el reconocimiento económico diario de alojamiento por noche (D-090).
 *
 * Contratos:
 * - DEVENGO != CARGO != PAGO.
 * - Intervalo semiabierto [fecha_entrada, fecha_salida).
 * - Algoritmo determinista BCMath con absorción de residuos en noche 1 si aplica fallback.
 * - Idempotencia transaccional pesimista.
 * - Trazabilidad D-061.
 */
class DevengoServicio
{
    private DevengoAlojamientoRepositorio $devengoRepo;
    private EstadiaRepositorio $estadiaRepo;
    private ReservaRepositorio $reservaRepo;
    private CargoCuentaRepositorio $cargoRepo;
    private AuditoriaServicio $auditoriaServicio;
    private ActorAuditoriaRepositorio $actorRepo;

    public function __construct(
        private PDO $pdo,
        ?DevengoAlojamientoRepositorio $devengoRepo = null,
        ?EstadiaRepositorio $estadiaRepo = null,
        ?ReservaRepositorio $reservaRepo = null,
        ?CargoCuentaRepositorio $cargoRepo = null,
        ?AuditoriaServicio $auditoriaServicio = null,
        ?ActorAuditoriaRepositorio $actorRepo = null
    ) {
        $this->devengoRepo = $devengoRepo ?? new DevengoAlojamientoRepositorio($pdo);
        $this->estadiaRepo = $estadiaRepo ?? new EstadiaRepositorio($pdo);
        $this->reservaRepo = $reservaRepo ?? new ReservaRepositorio($pdo);
        $this->cargoRepo = $cargoRepo ?? new CargoCuentaRepositorio($pdo);
        $this->auditoriaServicio = $auditoriaServicio ?? new AuditoriaServicio($pdo);
        $this->actorRepo = $actorRepo ?? new ActorAuditoriaRepositorio($pdo);
    }

    private function resolverActorId(?int $actorOUsuarioId): int
    {
        if ($actorOUsuarioId !== null && $actorOUsuarioId > 0) {
            $actor = $this->actorRepo->buscarPorId($actorOUsuarioId);
            if ($actor !== null) {
                return (int) $actor->obtenerId();
            }
            $actorHumano = $this->actorRepo->buscarPorUsuarioId($actorOUsuarioId);
            if ($actorHumano !== null) {
                return (int) $actorHumano->obtenerId();
            }
        }

        $sistema = $this->actorRepo->buscarPorCodigo('CAMARGO_PMS');
        if ($sistema !== null) {
            return (int) $sistema->obtenerId();
        }

        return 1;
    }

    /**
     * Calcula la tarifa nocturna para una fecha hotelera específica respetando la jerarquía D-090:
     * 1. Tarifa/snapshot contractual explícito por fecha, si existe.
     * 2. Desglose nocturno soberano pactado en reserva_unidades.
     * 3. Fallback de distribución uniforme con absorción de residuos de redondeo en la primera noche.
     *
     * @return array<string, mixed>
     */
    public function calcularTarifaNoche(Estadia $estadia, string $fechaHotelera, int $nocheIndice, int $totalNoches): array
    {
        $reservaUnidad = $this->reservaRepo->buscarUnidadPorId($estadia->obtenerReservaUnidadId());
        if ($reservaUnidad === null) {
            throw new EntidadNoEncontradaExcepcion('Unidad de reserva', $estadia->obtenerReservaUnidadId());
        }

        $precioUnitario = bcadd((string) $reservaUnidad->obtenerPrecioUnitarioNoche(), '0.00', 2);
        $totalUnidad = bcadd((string) $reservaUnidad->obtenerTotal(), '0.00', 2);
        $nochesReserva = max(1, $reservaUnidad->obtenerNoches());
        $moneda = $reservaUnidad->obtenerMonedaCodigo() ?: 'PEN';

        // Comprobación de cortesía
        $esCortesia = bccomp($precioUnitario, '0.00', 2) === 0 && bccomp($totalUnidad, '0.00', 2) === 0;

        // Comprobación de tarifa uniforme exacta sin residuo
        $totalCalculado = bcmul($precioUnitario, (string) $nochesReserva, 2);
        $esExacta = bccomp($totalCalculado, $totalUnidad, 2) === 0;

        if ($esExacta) {
            $tarifaBase = $precioUnitario;
            $metodoDist = DevengoAlojamiento::METODO_DIST_TARIFA_EXPLICITA;
            $origenTarifa = DevengoAlojamiento::ORIGEN_TARIFA_NOCTURNA_PACTADA;
        } else {
            // Fallback con absorción de residuo en la primera noche
            $tarifaBaseTruncada = bcdiv($totalUnidad, (string) $nochesReserva, 2);
            $totalAcumulado = bcmul($tarifaBaseTruncada, (string) $nochesReserva, 2);
            $residuo = bcsub($totalUnidad, $totalAcumulado, 2);

            if ($nocheIndice === 1 && bccomp($residuo, '0.00', 2) !== 0) {
                $tarifaBase = bcadd($tarifaBaseTruncada, $residuo, 2);
                $metodoDist = DevengoAlojamiento::METODO_DIST_AJUSTE_RESIDUAL;
            } else {
                $tarifaBase = $tarifaBaseTruncada;
                $metodoDist = DevengoAlojamiento::METODO_DIST_DISTRIBUCION_UNIFORME;
            }
            $origenTarifa = DevengoAlojamiento::ORIGEN_DISTRIBUCION_CONTRATO_UNIFORME;
        }

        $descuento = '0.00';
        $impuesto = '0.00'; // IGV o impuestos hoteleros si aplica
        $neto = $tarifaBase;
        $total = bcadd(bcsub($neto, $descuento, 2), $impuesto, 2);

        $snapshot = [
            'precio_unitario_pactado' => $precioUnitario,
            'total_contratado' => $totalUnidad,
            'noches_contratadas' => $nochesReserva,
            'noche_indice' => $nocheIndice,
            'total_noches_estadia' => $totalNoches,
            'tarifa_noche_calculada' => $tarifaBase,
            'fecha_hotelera' => $fechaHotelera,
        ];

        return [
            'tarifa_base_noche' => $tarifaBase,
            'descuento_monto' => $descuento,
            'impuesto_monto' => $impuesto,
            'importe_neto' => $neto,
            'importe_total' => $total,
            'moneda_codigo' => $moneda,
            'es_cortesia' => $esCortesia,
            'origen_tarifa' => $origenTarifa,
            'metodo_distribucion' => $metodoDist,
            'tarifa_snapshot' => $snapshot,
        ];
    }

    /**
     * Devenga económicamente una noche hotelera específica para una estadía.
     * Idempotente: si la noche ya está devengada, retorna el registro existente sin duplicar.
     */
    public function devengarNoche(
        int $estadiaId,
        string $fechaHotelera,
        int $actorId,
        string $metodo = DevengoAlojamiento::METODO_DEV_NIGHT_AUDIT,
        ?int $cierreId = null,
        string $timezone = 'America/Lima'
    ): DevengoAlojamiento {
        $actorIdFinal = $this->resolverActorId($actorId);

        // 1. Obtener estadía
        $estadia = $this->estadiaRepo->buscarPorId($estadiaId);
        if ($estadia === null) {
            throw new EntidadNoEncontradaExcepcion('Estadía', $estadiaId);
        }

        // 2. Validar semántica semiabierta [fecha_entrada, fecha_salida)
        $fEntrada = $estadia->obtenerFechaEntrada();
        $fSalida = $estadia->obtenerFechaSalidaPrevista();

        if ($fechaHotelera < $fEntrada || $fechaHotelera >= $fSalida) {
            throw new OperacionInvalidaExcepcion(
                "La fecha hotelera {$fechaHotelera} está fuera del intervalo de la estadía [{$fEntrada}, {$fSalida})."
            );
        }

        // 3. Comprobar idempotencia previa (bajo bloqueo si estamos en transacción)
        $devengoExistente = $this->devengoRepo->buscarActivoPorEstadiaYFecha($estadiaId, $fechaHotelera, true);
        if ($devengoExistente !== null) {
            return $devengoExistente;
        }

        // 4. Calcular índice de noche y noches totales
        $dEntrada = new DateTimeImmutable($fEntrada);
        $dFecha = new DateTimeImmutable($fechaHotelera);
        $dSalida = new DateTimeImmutable($fSalida);

        $nocheIndice = (int) $dEntrada->diff($dFecha)->days + 1;
        $totalNoches = max(1, (int) $dEntrada->diff($dSalida)->days);

        // 5. Calcular tarifa y desglose de importes
        $calc = $this->calcularTarifaNoche($estadia, $fechaHotelera, $nocheIndice, $totalNoches);

        // 6. Obtener cargo en cuenta asociado para trazabilidad
        $cargo = $this->cargoRepo->obtenerPorOrigen('ALOJAMIENTO_NOCHES', $estadia->obtenerReservaUnidadId());
        $cargoCuentaId = $cargo !== null ? (int) $cargo->obtenerId() : null;

        // 7. Secuencia (1 si es nuevo, o mayor si hubo reversiones previas)
        $ultimaSec = $this->devengoRepo->obtenerUltimaSecuencia($estadiaId, $fechaHotelera);
        $secuencia = $ultimaSec + 1;

        // 8. Crear entidad
        $codigo = $this->devengoRepo->generarSiguienteCodigo();
        $ahoraUtc = gmdate('Y-m-d H:i:s');

        // Resolver propiedad_id desde la unidad
        $unidadId = $estadia->obtenerUnidadId();
        $stmtProp = $this->pdo->prepare('SELECT propiedad_id FROM unidades WHERE id = :uid LIMIT 1');
        $stmtProp->execute(['uid' => $unidadId]);
        $propiedadId = (int) $stmtProp->fetchColumn() ?: 1;

        $devengo = new DevengoAlojamiento(
            null,
            $codigo,
            $cierreId,
            $estadiaId,
            $estadia->obtenerReservaId(),
            $estadia->obtenerReservaUnidadId(),
            $unidadId,
            $propiedadId,
            $cargoCuentaId,
            $fechaHotelera,
            $nocheIndice,
            $totalNoches,
            $secuencia,
            $calc['tarifa_base_noche'],
            $calc['descuento_monto'],
            $calc['impuesto_monto'],
            $calc['importe_neto'],
            $calc['importe_total'],
            $calc['moneda_codigo'],
            $calc['es_cortesia'],
            $calc['origen_tarifa'],
            $calc['metodo_distribucion'],
            $timezone,
            $calc['tarifa_snapshot'],
            DevengoAlojamiento::ESTADO_DEVENGADO,
            $metodo,
            null,
            null,
            $ahoraUtc,
            $actorIdFinal
        );

        try {
            $id = $this->devengoRepo->crear($devengo);
            $devengo->fijarId($id);
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), '1062')) {
                throw new DevengoDuplicadoExcepcion(
                    "Colisión concurrente: la estadía {$estadiaId} ya cuenta con devengo en {$fechaHotelera}.",
                    $e
                );
            }
            throw $e;
        }

        // 9. Trazabilidad D-061
        $this->auditoriaServicio->registrar(
            'DEVENGO_ALOJAMIENTO_GENERADO',
            'operaciones',
            'devengos_alojamiento',
            (string) $id,
            "Devengo generado código {$codigo}",
            null,
            [
                'codigo' => $codigo,
                'estadia_id' => $estadiaId,
                'fecha_hotelera' => $fechaHotelera,
                'noche_indice' => $nocheIndice,
                'total_noches' => $totalNoches,
                'importe_neto' => $calc['importe_neto'],
                'metodo_devengo' => $metodo,
                'cierre_id' => $cierreId,
            ],
            null,
            $actorIdFinal
        );

        return $devengo;
    }

    /**
     * Revierte un devengo de alojamiento por corrección operacional supervisada (D-090).
     * Principio: REVERTIR != DELETE (Preservación histórica inmutable).
     */
    public function revertirDevengo(int $devengoId, string $motivo, int $actorId): DevengoAlojamiento
    {
        $actorIdFinal = $this->resolverActorId($actorId);

        if (trim($motivo) === '') {
            throw new OperacionInvalidaExcepcion('El motivo de reversión es obligatorio.');
        }

        $devengo = $this->devengoRepo->buscarPorId($devengoId);
        if ($devengo === null) {
            throw new EntidadNoEncontradaExcepcion('Devengo', $devengoId);
        }

        if ($devengo->estaRevertido()) {
            throw new OperacionInvalidaExcepcion("El devengo ID {$devengoId} ya se encuentra revertido.");
        }

        $ahoraUtc = gmdate('Y-m-d H:i:s');
        $this->devengoRepo->actualizarEstado(
            $devengoId,
            DevengoAlojamiento::ESTADO_REVERTIDO,
            $actorIdFinal,
            $motivo,
            $ahoraUtc
        );

        // Registrar auditoría D-061
        $this->auditoriaServicio->registrar(
            'DEVENGO_ALOJAMIENTO_REVERTIDO',
            'operaciones',
            'devengos_alojamiento',
            (string) $devengoId,
            "Devengo revertido código {$devengo->obtenerCodigo()}: {$motivo}",
            null,
            [
                'codigo' => $devengo->obtenerCodigo(),
                'estadia_id' => $devengo->obtenerEstadiaId(),
                'fecha_hotelera' => $devengo->obtenerFechaHotelera(),
                'importe_revertido' => $devengo->obtenerImporteNeto(),
                'motivo' => $motivo,
            ],
            null,
            $actorIdFinal
        );

        return $this->devengoRepo->buscarPorId($devengoId);
    }

    /**
     * @return array<DevengoAlojamiento>
     */
    public function listarDevengosEstadia(int $estadiaId): array
    {
        return $this->devengoRepo->listarPorEstadia($estadiaId);
    }
}
