<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios\CPE\DTO;

/**
 * Objeto de transferencia de datos inmutable que contiene la representación fiscal
 * normalizada y tipada de un CPE, lista para la serialización determinista en UBL 2.1.
 */
final class RepresentacionFiscalCpe
{
    /**
     * @param array<int, array{
     *     identificador: string,
     *     numero_cuota: int,
     *     monto: string,
     *     fecha_vencimiento: string
     * }> $cuotas
     * @param array<int, array{
     *     numero_linea: int,
     *     cantidad: string,
     *     unidad_medida: string,
     *     descripcion: string,
     *     codigo_producto_interno: ?string,
     *     codigo_producto_sunat: ?string,
     *     valor_unitario: string,
     *     precio_unitario: string,
     *     base_imponible: string,
     *     descuento_monto: string,
     *     monto_igv: string,
     *     tipo_afectacion_igv: string,
     *     tasa_igv: string,
     *     codigo_tributo: string,
     *     nombre_tributo: string,
     *     tipo_tributo_internacional: string,
     *     categoria_tributo: string,
     *     total_linea: string,
     *     cpe_hospedaje_id: ?int,
     *     fecha_consumo: ?string,
     *     propiedades_catalogo_55: array<int, array{codigo: string, nombre: string, valor: string}>
     * }> $lineas
     * @param array<int, array{
     *     tipo_documento: string,
     *     serie: string,
     *     correlativo: int,
     *     numero_completo: string,
     *     codigo_motivo: string,
     *     descripcion_motivo: string
     * }> $documentosRelacionados
     * @param array<int, array{
     *     id: int,
     *     numero_orden: int,
     *     nombres_apellidos: string,
     *     tipo_documento: string,
     *     numero_documento: string,
     *     pais_emision_pasaporte: string,
     *     pais_residencia: string,
     *     fecha_ingreso_pais: string,
     *     fecha_checkin: string,
     *     fecha_checkout: string,
     *     dias_permanencia: int
     * }> $hospedajes
     */
    public function __construct(
        public readonly string $tipoComprobante,
        public readonly string $serie,
        public readonly int $correlativo,
        public readonly string $idDocumento,
        public readonly string $fechaEmision,
        public readonly string $horaEmision,
        public readonly ?string $fechaVencimiento,
        public readonly string $monedaCodigo,
        public readonly string $formaPago,
        public readonly ?string $montoNetoPendiente,
        public readonly array $cuotas,
        public readonly string $emisorRuc,
        public readonly string $emisorRazonSocial,
        public readonly ?string $emisorNombreComercial,
        public readonly string $emisorCodigoEstablecimiento,
        public readonly ?string $emisorUbigeo,
        public readonly ?string $emisorDireccionFiscal,
        public readonly ?string $emisorDepartamento,
        public readonly ?string $emisorProvincia,
        public readonly ?string $emisorDistrito,
        public readonly string $emisorPaisCodigo,
        public readonly string $receptorTipoDocumento,
        public readonly string $receptorNumeroDocumento,
        public readonly string $receptorRazonSocial,
        public readonly ?string $receptorDireccionFiscal,
        public readonly ?string $receptorUbigeo,
        public readonly string $receptorPaisCodigo,
        public readonly bool $esExportacionHospedaje,
        public readonly array $hospedajes,
        public readonly array $documentosRelacionados,
        public readonly string $totalOperacionesGravadas,
        public readonly string $totalOperacionesExoneradas,
        public readonly string $totalOperacionesInafectas,
        public readonly string $totalOperacionesExportacion,
        public readonly string $totalOperacionesGratuitas,
        public readonly string $totalIgv,
        public readonly string $totalDescuentos,
        public readonly string $totalVenta,
        public readonly array $lineas
    ) {
    }

    public function esFactura(): bool
    {
        return $this->tipoComprobante === '01';
    }

    public function esBoleta(): bool
    {
        return $this->tipoComprobante === '03';
    }

    public function esNotaCredito(): bool
    {
        return $this->tipoComprobante === '07';
    }

    public function esNotaDebito(): bool
    {
        return $this->tipoComprobante === '08';
    }

    public function esCredito(): bool
    {
        return $this->formaPago === 'CREDITO';
    }

    public function esContado(): bool
    {
        return $this->formaPago === 'CONTADO';
    }
}
