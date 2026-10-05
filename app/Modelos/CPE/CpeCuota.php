<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos\CPE;

use CamargoPMS\Excepciones\ValidacionFiscalExcepcion;
use DateTimeImmutable;

/**
 * Entidad de dominio que representa una cuota fiscal inmutable de crédito comercial (R.S. N.° 193-2020/SUNAT).
 */
class CpeCuota
{
    public function __construct(
        private ?int $id,
        private ?int $cpeId,
        private int $numeroCuota,
        private string $monto,
        private string $fechaVencimiento,
        private ?string $creadoEn = null
    ) {
        $this->validar();
    }

    private function validar(): void
    {
        if ($this->numeroCuota <= 0) {
            throw new ValidacionFiscalExcepcion('El número ordinal de la cuota fiscal debe ser estrictamente positivo (> 0).');
        }

        if ($this->numeroCuota > 999) {
            throw new ValidacionFiscalExcepcion("El número ordinal de la cuota fiscal {$this->numeroCuota} excede el límite estándar SUNAT de 3 dígitos (máximo 999).");
        }

        if (bccomp($this->monto, '0.00', 2) <= 0) {
            throw new ValidacionFiscalExcepcion("El importe de la cuota {$this->numeroCuota} debe ser estrictamente positivo (> 0.00).");
        }

        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $this->fechaVencimiento);
        if ($dt === false || $dt->format('Y-m-d') !== $this->fechaVencimiento) {
            throw new ValidacionFiscalExcepcion("La fecha de vencimiento '{$this->fechaVencimiento}' de la cuota no cumple con el formato AAAA-MM-DD.");
        }
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function fijarId(int $id): void
    {
        $this->id = $id;
    }

    public function obtenerCpeId(): ?int
    {
        return $this->cpeId;
    }

    public function fijarCpeId(int $cpeId): void
    {
        $this->cpeId = $cpeId;
    }

    public function obtenerNumeroCuota(): int
    {
        return $this->numeroCuota;
    }

    public function obtenerMonto(): string
    {
        return $this->monto;
    }

    public function obtenerFechaVencimiento(): string
    {
        return $this->fechaVencimiento;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    /**
     * Deriva determinísticamente el código formal UBL según catálogo SUNAT (ej. Cuota001, Cuota002).
     *
     * @throws ValidacionFiscalExcepcion Si el número excede la numeración representable según el estándar.
     */
    public function obtenerCodigoCuota(): string
    {
        if ($this->numeroCuota > 999) {
            throw new ValidacionFiscalExcepcion("El número de cuota {$this->numeroCuota} excede el límite estándar SUNAT de 3 dígitos (Cuota999).");
        }

        return sprintf('Cuota%03d', $this->numeroCuota);
    }

    /**
     * @return array<string, mixed>
     */
    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'cpe_id' => $this->cpeId,
            'numero_cuota' => $this->numeroCuota,
            'codigo_cuota' => $this->obtenerCodigoCuota(),
            'monto' => $this->monto,
            'fecha_vencimiento' => $this->fechaVencimiento,
            'creado_en' => $this->creadoEn,
        ];
    }

    /**
     * Alias compatible con convención de CpeLinea haciaArreglo().
     *
     * @return array<string, mixed>
     */
    public function haciaArreglo(): array
    {
        return $this->aArreglo();
    }

    /**
     * @param array<string, mixed> $datos
     */
    public static function desdeArreglo(array $datos): self
    {
        return new self(
            id: isset($datos['id']) ? (int) $datos['id'] : null,
            cpeId: isset($datos['cpe_id']) ? (int) $datos['cpe_id'] : null,
            numeroCuota: (int) ($datos['numero_cuota'] ?? 1),
            monto: (string) ($datos['monto'] ?? '0.00'),
            fechaVencimiento: (string) ($datos['fecha_vencimiento'] ?? ''),
            creadoEn: isset($datos['creado_en']) ? (string) $datos['creado_en'] : null
        );
    }
}
