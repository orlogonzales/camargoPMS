<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

use CamargoPMS\Modelos\Reclamacion;
use CamargoPMS\Repositorios\FeriadoRepositorio;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Servicio de dominio para el cómputo riguroso de plazos del Libro de Reclamaciones (RECLAMACIONES-1).
 *
 * Base regulatoria:
 * - Ley 29571 (Código de Protección y Defensa del Consumidor)
 * - D.S. 011-2011-PCM (Reglamento del Libro de Reclamaciones)
 * - Ley 31435: Plazo máximo de 15 días hábiles improrrogables
 * - D.S. 101-2022-PCM: Suspensión de hasta 5 días hábiles ante ofrecimiento de solución
 */
class CalculadorPlazosReclamacion
{
    public const PLAZO_LEGAL_DIAS_HABILES = 15;
    public const PLAZO_SUSPENSION_OFRECIMIENTO_DIAS_HABILES = 5;

    public function __construct(private FeriadoRepositorio $feriadoRepo)
    {
    }

    /**
     * Determina si una fecha dada es un día hábil según la legislación laboral peruana
     * (lunes a viernes excluyendo feriados nacionales y días no laborables aplicables al sector privado).
     *
     * @param DateTimeImmutable $fecha
     * @param array<string, string> $feriadosIndexados Array con fechas 'YYYY-MM-DD'
     * @return bool
     */
    public function esDiaHabil(DateTimeImmutable $fecha, array $feriadosIndexados): bool
    {
        $diaSemana = (int) $fecha->format('N'); // 1 = Lunes, 7 = Domingo
        if ($diaSemana >= 6) {
            return false;
        }

        $formatoFecha = $fecha->format('Y-m-d');
        return !isset($feriadosIndexados[$formatoFecha]);
    }

    /**
     * Suma una cantidad de días hábiles a partir del día hábil siguiente a la fecha dada.
     *
     * @param DateTimeImmutable|string $fechaInicio
     * @param int $diasHabiles
     * @return DateTimeImmutable
     */
    public function sumarDiasHabiles(DateTimeImmutable|string $fechaInicio, int $diasHabiles): DateTimeImmutable
    {
        if ($diasHabiles <= 0) {
            throw new InvalidArgumentException("La cantidad de días hábiles a sumar debe ser mayor a 0.");
        }

        $inicio = is_string($fechaInicio) ? new DateTimeImmutable($fechaInicio) : $fechaInicio;

        // Buscamos feriados en una ventana holgada de hasta 120 días posteriores
        $finVentana = $inicio->modify('+120 days');
        $feriados = $this->feriadoRepo->obtenerFechasFeriadosSectorPrivado(
            $inicio->format('Y-m-d'),
            $finVentana->format('Y-m-d')
        );

        $cursor = $inicio;
        $diasSumados = 0;

        while ($diasSumados < $diasHabiles) {
            $cursor = $cursor->modify('+1 day');
            if ($this->esDiaHabil($cursor, $feriados)) {
                $diasSumados++;
            }
        }

        return $cursor;
    }

    /**
     * Calcula la fecha límite legal inicial de 15 días hábiles para una reclamación.
     *
     * @param DateTimeImmutable|string $fechaInterposicion
     * @return string Formato 'YYYY-MM-DD'
     */
    public function calcularFechaLimiteLegalInicial(DateTimeImmutable|string $fechaInterposicion): string
    {
        $fechaLimite = $this->sumarDiasHabiles($fechaInterposicion, self::PLAZO_LEGAL_DIAS_HABILES);
        return $fechaLimite->format('Y-m-d');
    }

    /**
     * Calcula la fecha límite de respuesta de 5 días hábiles cuando se formula un ofrecimiento de solución.
     *
     * @param DateTimeImmutable|string $fechaOfrecimiento
     * @return string Formato 'YYYY-MM-DD'
     */
    public function calcularFechaLimiteOfrecimiento(DateTimeImmutable|string $fechaOfrecimiento): string
    {
        $fechaLimite = $this->sumarDiasHabiles($fechaOfrecimiento, self::PLAZO_SUSPENSION_OFRECIMIENTO_DIAS_HABILES);
        return $fechaLimite->format('Y-m-d');
    }

    /**
     * Cuenta cuántos días hábiles transcurrieron entre dos fechas (excluyendo la fecha inicial).
     *
     * @param DateTimeImmutable|string $fechaInicio
     * @param DateTimeImmutable|string $fechaFin
     * @return int
     */
    public function contarDiasHabilesTranscurridos(DateTimeImmutable|string $fechaInicio, DateTimeImmutable|string $fechaFin): int
    {
        $inicio = is_string($fechaInicio) ? new DateTimeImmutable(substr($fechaInicio, 0, 10)) : $fechaInicio;
        $fin = is_string($fechaFin) ? new DateTimeImmutable(substr($fechaFin, 0, 10)) : $fechaFin;

        if ($fin <= $inicio) {
            return 0;
        }

        $feriados = $this->feriadoRepo->obtenerFechasFeriadosSectorPrivado(
            $inicio->format('Y-m-d'),
            $fin->format('Y-m-d')
        );

        $cursor = $inicio;
        $dias = 0;

        while ($cursor < $fin) {
            $cursor = $cursor->modify('+1 day');
            if ($this->esDiaHabil($cursor, $feriados)) {
                $dias++;
            }
        }

        return $dias;
    }

    /**
     * Calcula la nueva fecha límite legal al reanudarse el plazo tras un ofrecimiento rechazado o expirado.
     *
     * @param DateTimeImmutable|string $fechaReanudacion
     * @param int $diasHabilesConsumidos
     * @return array{dias_restantes: int, nueva_fecha_limite: string}
     */
    public function calcularReanudacionPlazo(DateTimeImmutable|string $fechaReanudacion, int $diasHabilesConsumidos): array
    {
        $diasRestantes = max(1, self::PLAZO_LEGAL_DIAS_HABILES - $diasHabilesConsumidos);
        $nuevaFechaLimite = $this->sumarDiasHabiles($fechaReanudacion, $diasRestantes);

        return [
            'dias_restantes' => $diasRestantes,
            'nueva_fecha_limite' => $nuevaFechaLimite->format('Y-m-d'),
        ];
    }

    /**
     * Determina el semáforo y estado regulatorio derivado de la reclamación.
     *
     * @param Reclamacion $reclamacion
     * @param string|null $fechaReferencia 'YYYY-MM-DD', por defecto la fecha actual
     * @return array{
     *     codigo: string,
     *     color: string,
     *     etiqueta: string,
     *     dias_restantes_habiles: int,
     *     vencido: bool
     * }
     */
    public function calcularSemaforo(Reclamacion $reclamacion, ?string $fechaReferencia = null): array
    {
        $estado = $reclamacion->obtenerEstado();

        if (in_array($estado, [Reclamacion::ESTADO_ATENDIDO, Reclamacion::ESTADO_CONCLUIDO_POR_ACUERDO], true)) {
            return [
                'codigo' => 'CONCLUIDO',
                'color' => 'secondary',
                'etiqueta' => 'Concluido',
                'dias_restantes_habiles' => 0,
                'vencido' => false,
            ];
        }

        if ($estado === Reclamacion::ESTADO_ANULADO) {
            return [
                'codigo' => 'ANULADO',
                'color' => 'dark',
                'etiqueta' => 'Anulado',
                'dias_restantes_habiles' => 0,
                'vencido' => false,
            ];
        }

        if ($estado === Reclamacion::ESTADO_SUSPENDIDO_OFRECIMIENTO) {
            return [
                'codigo' => 'SUSPENDIDO',
                'color' => 'info',
                'etiqueta' => 'Suspendido (Ofrecimiento)',
                'dias_restantes_habiles' => 0,
                'vencido' => false,
            ];
        }

        // Para REGISTRADO o EN_PROCESO
        $hoyStr = $fechaReferencia ?? date('Y-m-d');
        $hoy = new DateTimeImmutable($hoyStr);
        $limite = new DateTimeImmutable($reclamacion->obtenerFechaLimiteLegal());

        if ($hoy > $limite) {
            $diasVencidos = $this->contarDiasHabilesTranscurridos($limite, $hoy);
            return [
                'codigo' => 'VENCIDO',
                'color' => 'danger',
                'etiqueta' => "Vencido (+{$diasVencidos} d.h.)",
                'dias_restantes_habiles' => -$diasVencidos,
                'vencido' => true,
            ];
        }

        $diasRestantes = $this->contarDiasHabilesTranscurridos($hoy, $limite);

        if ($diasRestantes <= 3) {
            return [
                'codigo' => 'POR_VENCER',
                'color' => 'warning',
                'etiqueta' => "Por vencer ({$diasRestantes} d.h.)",
                'dias_restantes_habiles' => $diasRestantes,
                'vencido' => false,
            ];
        }

        return [
            'codigo' => 'EN_PLAZO',
            'color' => 'success',
            'etiqueta' => "En plazo ({$diasRestantes} d.h.)",
            'dias_restantes_habiles' => $diasRestantes,
            'vencido' => false,
        ];
    }
}
