<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos\CPE;

use CamargoPMS\Excepciones\ValidacionFiscalExcepcion;
use DateTimeImmutable;

/**
 * Entidad de dominio que representa el snapshot T0 inmutable del huésped no domiciliado y su estancia (D.L. 919 / Catálogo 55).
 * Ontológicamente independiente del receptor del comprobante comercial (permite Receptor Empresa != Huésped Turista).
 */
class CpeHospedajeFiscal
{
    public function __construct(
        private ?int $id,
        private ?int $cpeId,
        private int $numeroOrden,
        private string $nombresApellidos = '',
        private string $tipoDocumento = '',
        private string $numeroDocumento = '',
        private string $paisEmisionPasaporte = '',
        private string $paisResidencia = '',
        private string $fechaIngresoPais = '',
        private string $fechaCheckin = '',
        private string $fechaCheckout = '',
        private int $diasPermanencia = 1,
        private ?string $tamVirtualNumero = null,
        private ?string $creadoEn = null,
        ?string $huespedNombreCompleto = null,
        ?string $huespedTipoDocumento = null,
        ?string $huespedNumeroDocumento = null,
        ?string $huespedPaisEmisionPasaporte = null,
        ?string $huespedPaisResidencia = null,
        ?string $fechaConsumo = null,
        ?string $paqueteTuristicoDocumento = null
    ) {
        if ($huespedNombreCompleto !== null && $this->nombresApellidos === '') {
            $this->nombresApellidos = $huespedNombreCompleto;
        }
        if ($huespedTipoDocumento !== null && $this->tipoDocumento === '') {
            $this->tipoDocumento = $huespedTipoDocumento;
        }
        if ($huespedNumeroDocumento !== null && $this->numeroDocumento === '') {
            $this->numeroDocumento = $huespedNumeroDocumento;
        }
        if ($huespedPaisEmisionPasaporte !== null && $this->paisEmisionPasaporte === '') {
            $this->paisEmisionPasaporte = $huespedPaisEmisionPasaporte;
        }
        if ($huespedPaisResidencia !== null && $this->paisResidencia === '') {
            $this->paisResidencia = $huespedPaisResidencia;
        }
        $this->validar();
    }

    private function validar(): void
    {
        if ($this->numeroOrden <= 0) {
            throw new ValidacionFiscalExcepcion('El número de orden del huésped fiscal debe ser estrictamente positivo (> 0).');
        }

        if (trim($this->nombresApellidos) === '') {
            throw new ValidacionFiscalExcepcion('Los nombres y apellidos del huésped fiscal no pueden ser una cadena vacía.');
        }

        $tipoDoc = strtoupper(trim($this->tipoDocumento));
        if ($tipoDoc === 'PAS' || $tipoDoc === 'PASAPORTE') {
            $tipoDoc = '7';
        } elseif ($tipoDoc === 'CE' || $tipoDoc === 'CARNET_EXTRANJERIA') {
            $tipoDoc = '4';
        } elseif ($tipoDoc === 'DNI') {
            $tipoDoc = '1';
        }
        $this->tipoDocumento = $tipoDoc;

        if ($this->tipoDocumento === '') {
            throw new ValidacionFiscalExcepcion('El tipo de documento de identidad del huésped fiscal es obligatorio.');
        }

        if (trim($this->numeroDocumento) === '') {
            throw new ValidacionFiscalExcepcion('El número de documento de identidad del huésped fiscal es obligatorio.');
        }

        $paisEmision = strtoupper(trim($this->paisEmisionPasaporte));
        if (strlen($paisEmision) !== 2) {
            throw new ValidacionFiscalExcepcion("El código de país emisor del pasaporte '{$this->paisEmisionPasaporte}' debe tener exactamente 2 caracteres (ISO 3166-1 alfa-2).");
        }
        $this->paisEmisionPasaporte = $paisEmision;

        $paisResidencia = strtoupper(trim($this->paisResidencia));
        if (strlen($paisResidencia) !== 2) {
            throw new ValidacionFiscalExcepcion("El código de país de residencia habitual '{$this->paisResidencia}' debe tener exactamente 2 caracteres (ISO 3166-1 alfa-2).");
        }
        $this->paisResidencia = $paisResidencia;

        $dtIngreso = DateTimeImmutable::createFromFormat('Y-m-d', $this->fechaIngresoPais);
        if ($dtIngreso === false || $dtIngreso->format('Y-m-d') !== $this->fechaIngresoPais) {
            throw new ValidacionFiscalExcepcion("La fecha de ingreso al país '{$this->fechaIngresoPais}' debe cumplir con el formato AAAA-MM-DD.");
        }

        $dtCheckin = DateTimeImmutable::createFromFormat('Y-m-d', $this->fechaCheckin);
        if ($dtCheckin === false || $dtCheckin->format('Y-m-d') !== $this->fechaCheckin) {
            throw new ValidacionFiscalExcepcion("La fecha de check-in '{$this->fechaCheckin}' debe cumplir con el formato AAAA-MM-DD.");
        }

        $dtCheckout = DateTimeImmutable::createFromFormat('Y-m-d', $this->fechaCheckout);
        if ($dtCheckout === false || $dtCheckout->format('Y-m-d') !== $this->fechaCheckout) {
            throw new ValidacionFiscalExcepcion("La fecha de check-out '{$this->fechaCheckout}' debe cumplir con el formato AAAA-MM-DD.");
        }

        if ($dtCheckout < $dtCheckin) {
            throw new ValidacionFiscalExcepcion("La fecha de check-out '{$this->fechaCheckout}' no puede ser cronológicamente anterior a la fecha de check-in '{$this->fechaCheckin}'.");
        }

        if ($this->diasPermanencia <= 0 || $this->diasPermanencia > 60) {
            throw new ValidacionFiscalExcepcion("Los días de permanencia continua en el país ({$this->diasPermanencia}) deben ser mayores a 0 y no exceder los 60 días legalmente amparados por el D.L. 919.");
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

    public function obtenerNumeroOrden(): int
    {
        return $this->numeroOrden;
    }

    public function obtenerNombresApellidos(): string
    {
        return $this->nombresApellidos;
    }

    public function obtenerHuespedNombreCompleto(): string
    {
        return $this->nombresApellidos;
    }

    public function obtenerTipoDocumento(): string
    {
        return $this->tipoDocumento;
    }

    public function obtenerHuespedTipoDocumento(): string
    {
        return $this->tipoDocumento;
    }

    public function obtenerNumeroDocumento(): string
    {
        return $this->numeroDocumento;
    }

    public function obtenerHuespedNumeroDocumento(): string
    {
        return $this->numeroDocumento;
    }

    public function obtenerPaisEmisionPasaporte(): string
    {
        return $this->paisEmisionPasaporte;
    }

    public function obtenerHuespedPaisEmisionPasaporte(): string
    {
        return $this->paisEmisionPasaporte;
    }

    public function obtenerPaisResidencia(): string
    {
        return $this->paisResidencia;
    }

    public function obtenerHuespedPaisResidencia(): string
    {
        return $this->paisResidencia;
    }

    public function obtenerFechaIngresoPais(): string
    {
        return $this->fechaIngresoPais;
    }

    public function obtenerFechaCheckin(): string
    {
        return $this->fechaCheckin;
    }

    public function obtenerFechaCheckout(): string
    {
        return $this->fechaCheckout;
    }

    public function obtenerDiasPermanencia(): int
    {
        return $this->diasPermanencia;
    }

    public function obtenerTamVirtualNumero(): ?string
    {
        return $this->tamVirtualNumero;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    /**
     * @return array<string, mixed>
     */
    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'cpe_id' => $this->cpeId,
            'numero_orden' => $this->numeroOrden,
            'nombres_apellidos' => $this->nombresApellidos,
            'tipo_documento' => $this->tipoDocumento,
            'numero_documento' => $this->numeroDocumento,
            'pais_emision_pasaporte' => $this->paisEmisionPasaporte,
            'pais_residencia' => $this->paisResidencia,
            'fecha_ingreso_pais' => $this->fechaIngresoPais,
            'fecha_checkin' => $this->fechaCheckin,
            'fecha_checkout' => $this->fechaCheckout,
            'dias_permanencia' => $this->diasPermanencia,
            'tam_virtual_numero' => $this->tamVirtualNumero,
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
            numeroOrden: (int) ($datos['numero_orden'] ?? 1),
            nombresApellidos: (string) ($datos['huesped_nombre_completo'] ?? $datos['nombres_apellidos'] ?? ''),
            tipoDocumento: (string) ($datos['huesped_tipo_documento'] ?? $datos['tipo_documento'] ?? 'PAS'),
            numeroDocumento: (string) ($datos['huesped_numero_documento'] ?? $datos['numero_documento'] ?? ''),
            paisEmisionPasaporte: (string) ($datos['huesped_pais_emision_pasaporte'] ?? $datos['pais_emision_pasaporte'] ?? 'US'),
            paisResidencia: (string) ($datos['huesped_pais_residencia'] ?? $datos['pais_residencia'] ?? 'US'),
            fechaIngresoPais: (string) ($datos['fecha_ingreso_pais'] ?? ''),
            fechaCheckin: (string) ($datos['fecha_checkin'] ?? ''),
            fechaCheckout: (string) ($datos['fecha_checkout'] ?? ''),
            diasPermanencia: (int) ($datos['dias_permanencia'] ?? 1),
            tamVirtualNumero: isset($datos['tam_virtual_numero']) ? (string) $datos['tam_virtual_numero'] : null,
            creadoEn: isset($datos['creado_en']) ? (string) $datos['creado_en'] : null
        );
    }
}
