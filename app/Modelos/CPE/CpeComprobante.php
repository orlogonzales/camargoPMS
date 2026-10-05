<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos\CPE;

/**
 * Agregado raíz soberano que representa un Comprobante de Pago Electrónico (CPE) emitido bajo normativa SUNAT.
 */
class CpeComprobante
{
    /**
     * @param CpeLinea[] $lineas
     * @param CpeDocumentoRelacionado[] $documentosRelacionados
     */
    public function __construct(
        private ?int $id,
        private int $emisorEstablecimientoId,
        private int $serieId,
        private ?int $cuentaFolioId,
        private string $tipoComprobante,
        private string $serie,
        private int $correlativo,
        private string $codigoFolioCompleto,
        private string $claveIdempotencia,

        // Snapshot Fiscal Emisor (T0)
        private string $emisorRuc,
        private string $emisorRazonSocial,
        private ?string $emisorNombreComercial,
        private string $emisorDireccionFiscal,
        private string $emisorUbigeo,
        private string $emisorCodigoEstablecimiento,
        private string $emisorDepartamento,
        private string $emisorProvincia,
        private string $emisorDistrito,

        // Snapshot Fiscal Receptor (T0)
        private string $receptorTipoDocumento,
        private string $receptorNumeroDocumento,
        private string $receptorRazonSocial,
        private ?string $receptorDireccionFiscal,
        private ?string $receptorUbigeo,
        private ?string $receptorEmail,
        private string $receptorPaisCodigo = 'PE',

        // Snapshot Régimen de Hospedaje No Domiciliado (DL 919)
        private bool $esExportacionHospedaje = false,
        private ?string $hospedajeTamVirtualNumero = null,
        private ?string $hospedajeFechaIngresoPais = null,
        private ?int $hospedajeDiasPermanencia = null,
        private ?string $hospedajeLeyendaTributaria = null,

        // Moneda y Bases Imponibles / Totales
        private string $monedaCodigo = 'PEN',
        private ?string $tipoCambio = null,
        private string $totalOperacionesGravadas = '0.00',
        private string $totalOperacionesExoneradas = '0.00',
        private string $totalOperacionesInafectas = '0.00',
        private string $totalOperacionesExportacion = '0.00',
        private string $totalOperacionesGratuitas = '0.00',
        private string $totalIgv = '0.00',
        private string $totalDescuentos = '0.00',
        private string $totalVenta = '0.00',

        // 4 Ejes Ortogonales de Estado
        private string $estadoGeneracion = 'BORRADOR',
        private string $estadoTransmision = 'NO_INICIADO',
        private string $estadoFiscalSunat = 'PENDIENTE_ENVIO',
        private string $estadoRectificacion = 'ORIGINAL',

        // Huellas Técnicas y Rutas de Archivos en Storage
        private ?string $codigoHashCpe = null,
        private ?string $hashXmlSha256 = null,
        private ?string $hashPdfSha256 = null,
        private ?string $rutaArchivoXml = null,
        private ?string $rutaArchivoPdf = null,

        // Fechas, Usuario y Auditoría
        private ?string $fechaEmision = null,
        private ?string $fechaVencimiento = null,
        private int $creadoPorUsuarioId = 1,
        private ?string $creadoEn = null,
        private ?string $actualizadoEn = null,

        // Colecciones hijas del agregado
        private array $lineas = [],
        private array $documentosRelacionados = []
    ) {
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function fijarId(int $id): void
    {
        $this->id = $id;
    }

    public function obtenerEmisorEstablecimientoId(): int
    {
        return $this->emisorEstablecimientoId;
    }

    public function obtenerSerieId(): int
    {
        return $this->serieId;
    }

    public function obtenerCuentaFolioId(): ?int
    {
        return $this->cuentaFolioId;
    }

    public function obtenerTipoComprobante(): string
    {
        return $this->tipoComprobante;
    }

    public function obtenerSerie(): string
    {
        return $this->serie;
    }

    public function obtenerCorrelativo(): int
    {
        return $this->correlativo;
    }

    public function obtenerCodigoFolioCompleto(): string
    {
        return $this->codigoFolioCompleto;
    }

    public function asignarCorrelativo(int $correlativo, string $codigoFolioCompleto): void
    {
        $this->correlativo = $correlativo;
        $this->codigoFolioCompleto = $codigoFolioCompleto;
    }

    public function fijarFechaEmision(string $fechaEmision): void
    {
        $this->fechaEmision = $fechaEmision;
    }

    public function fijarEstadoGeneracion(string $estadoGeneracion): void
    {
        $this->estadoGeneracion = $estadoGeneracion;
    }

    public function fijarCreadoPorUsuarioId(int $usuarioId): void
    {
        $this->creadoPorUsuarioId = $usuarioId;
    }

    public function obtenerClaveIdempotencia(): string
    {
        return $this->claveIdempotencia;
    }

    // --- Snapshot Emisor T0 ---

    public function obtenerEmisorRuc(): string
    {
        return $this->emisorRuc;
    }

    public function obtenerEmisorRazonSocial(): string
    {
        return $this->emisorRazonSocial;
    }

    public function obtenerEmisorNombreComercial(): ?string
    {
        return $this->emisorNombreComercial;
    }

    public function obtenerEmisorDireccionFiscal(): string
    {
        return $this->emisorDireccionFiscal;
    }

    public function obtenerEmisorUbigeo(): string
    {
        return $this->emisorUbigeo;
    }

    public function obtenerEmisorCodigoEstablecimiento(): string
    {
        return $this->emisorCodigoEstablecimiento;
    }

    public function obtenerEmisorDepartamento(): string
    {
        return $this->emisorDepartamento;
    }

    public function obtenerEmisorProvincia(): string
    {
        return $this->emisorProvincia;
    }

    public function obtenerEmisorDistrito(): string
    {
        return $this->emisorDistrito;
    }

    /**
     * @return array<string, mixed>
     */
    public function obtenerSnapshotEmisor(): array
    {
        return [
            'ruc' => $this->emisorRuc,
            'razon_social' => $this->emisorRazonSocial,
            'nombre_comercial' => $this->emisorNombreComercial,
            'direccion_fiscal' => $this->emisorDireccionFiscal,
            'ubigeo' => $this->emisorUbigeo,
            'codigo_establecimiento' => $this->emisorCodigoEstablecimiento,
            'departamento' => $this->emisorDepartamento,
            'provincia' => $this->emisorProvincia,
            'distrito' => $this->emisorDistrito,
        ];
    }

    // --- Snapshot Receptor T0 ---

    public function obtenerReceptorTipoDocumento(): string
    {
        return $this->receptorTipoDocumento;
    }

    public function obtenerReceptorNumeroDocumento(): string
    {
        return $this->receptorNumeroDocumento;
    }

    public function obtenerReceptorRazonSocial(): string
    {
        return $this->receptorRazonSocial;
    }

    public function obtenerReceptorDireccionFiscal(): ?string
    {
        return $this->receptorDireccionFiscal;
    }

    public function obtenerReceptorUbigeo(): ?string
    {
        return $this->receptorUbigeo;
    }

    public function obtenerReceptorEmail(): ?string
    {
        return $this->receptorEmail;
    }

    public function obtenerReceptorPaisCodigo(): string
    {
        return $this->receptorPaisCodigo;
    }

    /**
     * @return array<string, mixed>
     */
    public function obtenerSnapshotReceptor(): array
    {
        return [
            'tipo_documento' => $this->receptorTipoDocumento,
            'numero_documento' => $this->receptorNumeroDocumento,
            'razon_social' => $this->receptorRazonSocial,
            'direccion_fiscal' => $this->receptorDireccionFiscal,
            'ubigeo' => $this->receptorUbigeo,
            'email' => $this->receptorEmail,
            'pais_codigo' => $this->receptorPaisCodigo,
        ];
    }

    // --- Régimen de Hospedaje No Domiciliado (DL 919) ---

    public function esExportacionHospedaje(): bool
    {
        return $this->esExportacionHospedaje;
    }

    public function obtenerHospedajeTamVirtualNumero(): ?string
    {
        return $this->hospedajeTamVirtualNumero;
    }

    public function obtenerHospedajeFechaIngresoPais(): ?string
    {
        return $this->hospedajeFechaIngresoPais;
    }

    public function obtenerHospedajeDiasPermanencia(): ?int
    {
        return $this->hospedajeDiasPermanencia;
    }

    public function obtenerHospedajeLeyendaTributaria(): ?string
    {
        return $this->hospedajeLeyendaTributaria;
    }

    // --- Moneda e Importes ---

    public function obtenerMonedaCodigo(): string
    {
        return $this->monedaCodigo;
    }

    public function obtenerTipoCambio(): ?string
    {
        return $this->tipoCambio;
    }

    public function obtenerTotalOperacionesGravadas(): string
    {
        return $this->totalOperacionesGravadas;
    }

    public function obtenerTotalOperacionesExoneradas(): string
    {
        return $this->totalOperacionesExoneradas;
    }

    public function obtenerTotalOperacionesInafectas(): string
    {
        return $this->totalOperacionesInafectas;
    }

    public function obtenerTotalOperacionesExportacion(): string
    {
        return $this->totalOperacionesExportacion;
    }

    public function obtenerTotalOperacionesGratuitas(): string
    {
        return $this->totalOperacionesGratuitas;
    }

    public function obtenerTotalIgv(): string
    {
        return $this->totalIgv;
    }

    public function obtenerTotalDescuentos(): string
    {
        return $this->totalDescuentos;
    }

    public function obtenerTotalVenta(): string
    {
        return $this->totalVenta;
    }

    // --- Ejes Ortogonales de Estado ---

    public function obtenerEstadoGeneracion(): string
    {
        return $this->estadoGeneracion;
    }

    public function esBorrador(): bool
    {
        return $this->estadoGeneracion === 'BORRADOR';
    }

    public function estaEmitido(): bool
    {
        return $this->estadoGeneracion === 'EMITIDO';
    }

    public function obtenerEstadoTransmision(): string
    {
        return $this->estadoTransmision;
    }

    public function estaTransmitido(): bool
    {
        return $this->estadoTransmision === 'TRANSMITIDO';
    }

    public function obtenerEstadoFiscalSunat(): string
    {
        return $this->estadoFiscalSunat;
    }

    public function estaAceptadoSunat(): bool
    {
        return in_array($this->estadoFiscalSunat, ['ACEPTADO', 'ACEPTADO_OBSERVADO'], true);
    }

    public function estaRechazadoSunat(): bool
    {
        return $this->estadoFiscalSunat === 'RECHAZADO';
    }

    public function obtenerEstadoRectificacion(): string
    {
        return $this->estadoRectificacion;
    }

    public function puedeModificarse(): bool
    {
        return $this->estadoGeneracion === 'BORRADOR';
    }

    // --- Huellas y Rutas Storage ---

    public function obtenerCodigoHashCpe(): ?string
    {
        return $this->codigoHashCpe;
    }

    public function obtenerHashXmlSha256(): ?string
    {
        return $this->hashXmlSha256;
    }

    public function obtenerHashPdfSha256(): ?string
    {
        return $this->hashPdfSha256;
    }

    public function obtenerRutaArchivoXml(): ?string
    {
        return $this->rutaArchivoXml;
    }

    public function obtenerRutaArchivoPdf(): ?string
    {
        return $this->rutaArchivoPdf;
    }

    // --- Fechas y Auditoría ---

    public function obtenerFechaEmision(): ?string
    {
        return $this->fechaEmision;
    }

    public function obtenerFechaVencimiento(): ?string
    {
        return $this->fechaVencimiento;
    }

    public function obtenerCreadoPorUsuarioId(): int
    {
        return $this->creadoPorUsuarioId;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function obtenerActualizadoEn(): ?string
    {
        return $this->actualizadoEn;
    }

    // --- Colecciones Hijas ---

    /**
     * @return CpeLinea[]
     */
    public function obtenerLineas(): array
    {
        return $this->lineas;
    }

    public function agregarLinea(CpeLinea $linea): void
    {
        $this->lineas[] = $linea;
    }

    /**
     * @param CpeLinea[] $lineas
     */
    public function fijarLineas(array $lineas): void
    {
        $this->lineas = $lineas;
    }

    /**
     * @return CpeDocumentoRelacionado[]
     */
    public function obtenerDocumentosRelacionados(): array
    {
        return $this->documentosRelacionados;
    }

    public function agregarDocumentoRelacionado(CpeDocumentoRelacionado $doc): void
    {
        $this->documentosRelacionados[] = $doc;
    }

    /**
     * @param CpeDocumentoRelacionado[] $documentosRelacionados
     */
    public function fijarDocumentosRelacionados(array $documentosRelacionados): void
    {
        $this->documentosRelacionados = $documentosRelacionados;
    }

    // --- Métodos de Ayuda Semántica ---

    public function esFactura(): bool
    {
        return $this->tipoComprobante === 'FACTURA';
    }

    public function esBoleta(): bool
    {
        return $this->tipoComprobante === 'BOLETA';
    }

    public function esNotaCredito(): bool
    {
        return $this->tipoComprobante === 'NOTA_CREDITO';
    }

    public function esNotaDebito(): bool
    {
        return $this->tipoComprobante === 'NOTA_DEBITO';
    }

    /**
     * @return array<string, mixed>
     */
    public function haciaArreglo(): array
    {
        return [
            'id' => $this->id,
            'emisor_establecimiento_id' => $this->emisorEstablecimientoId,
            'serie_id' => $this->serieId,
            'cuenta_folio_id' => $this->cuentaFolioId,
            'tipo_comprobante' => $this->tipoComprobante,
            'serie' => $this->serie,
            'correlativo' => $this->correlativo,
            'codigo_folio_completo' => $this->codigoFolioCompleto,
            'clave_idempotencia' => $this->claveIdempotencia,

            'emisor_ruc' => $this->emisorRuc,
            'emisor_razon_social' => $this->emisorRazonSocial,
            'emisor_nombre_comercial' => $this->emisorNombreComercial,
            'emisor_direccion_fiscal' => $this->emisorDireccionFiscal,
            'emisor_ubigeo' => $this->emisorUbigeo,
            'emisor_codigo_establecimiento' => $this->emisorCodigoEstablecimiento,
            'emisor_departamento' => $this->emisorDepartamento,
            'emisor_provincia' => $this->emisorProvincia,
            'emisor_distrito' => $this->emisorDistrito,

            'receptor_tipo_documento' => $this->receptorTipoDocumento,
            'receptor_numero_documento' => $this->receptorNumeroDocumento,
            'receptor_razon_social' => $this->receptorRazonSocial,
            'receptor_direccion_fiscal' => $this->receptorDireccionFiscal,
            'receptor_ubigeo' => $this->receptorUbigeo,
            'receptor_email' => $this->receptorEmail,
            'receptor_pais_codigo' => $this->receptorPaisCodigo,

            'es_exportacion_hospedaje' => $this->esExportacionHospedaje ? 1 : 0,
            'hospedaje_tam_virtual_numero' => $this->hospedajeTamVirtualNumero,
            'hospedaje_fecha_ingreso_pais' => $this->hospedajeFechaIngresoPais,
            'hospedaje_dias_permanencia' => $this->hospedajeDiasPermanencia,
            'hospedaje_leyenda_tributaria' => $this->hospedajeLeyendaTributaria,

            'moneda_codigo' => $this->monedaCodigo,
            'tipo_cambio' => $this->tipoCambio,
            'total_operaciones_gravadas' => $this->totalOperacionesGravadas,
            'total_operaciones_exoneradas' => $this->totalOperacionesExoneradas,
            'total_operaciones_inafectas' => $this->totalOperacionesInafectas,
            'total_operaciones_exportacion' => $this->totalOperacionesExportacion,
            'total_operaciones_gratuitas' => $this->totalOperacionesGratuitas,
            'total_igv' => $this->totalIgv,
            'total_descuentos' => $this->totalDescuentos,
            'total_venta' => $this->totalVenta,

            'estado_generacion' => $this->estadoGeneracion,
            'estado_transmision' => $this->estadoTransmision,
            'estado_fiscal_sunat' => $this->estadoFiscalSunat,
            'estado_rectificacion' => $this->estadoRectificacion,

            'codigo_hash_cpe' => $this->codigoHashCpe,
            'hash_xml_sha256' => $this->hashXmlSha256,
            'hash_pdf_sha256' => $this->hashPdfSha256,
            'ruta_archivo_xml' => $this->rutaArchivoXml,
            'ruta_archivo_pdf' => $this->rutaArchivoPdf,

            'fecha_emision' => $this->fechaEmision,
            'fecha_vencimiento' => $this->fechaVencimiento,
            'creado_por_usuario_id' => $this->creadoPorUsuarioId,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,

            'lineas' => array_map(static fn (CpeLinea $l): array => $l->haciaArreglo(), $this->lineas),
            'documentos_relacionados' => array_map(static fn (CpeDocumentoRelacionado $d): array => $d->haciaArreglo(), $this->documentosRelacionados),
        ];
    }

    /**
     * @param array<string, mixed> $datos
     * @param CpeLinea[] $lineas
     * @param CpeDocumentoRelacionado[] $documentosRelacionados
     */
    public static function desdeArreglo(array $datos, array $lineas = [], array $documentosRelacionados = []): self
    {
        return new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (int) ($datos['emisor_establecimiento_id'] ?? 0),
            (int) ($datos['serie_id'] ?? 0),
            isset($datos['cuenta_folio_id']) && $datos['cuenta_folio_id'] !== null ? (int) $datos['cuenta_folio_id'] : null,
            (string) ($datos['tipo_comprobante'] ?? 'FACTURA'),
            (string) ($datos['serie'] ?? ''),
            (int) ($datos['correlativo'] ?? 0),
            (string) ($datos['codigo_folio_completo'] ?? ''),
            (string) ($datos['clave_idempotencia'] ?? ''),

            (string) ($datos['emisor_ruc'] ?? ''),
            (string) ($datos['emisor_razon_social'] ?? ''),
            isset($datos['emisor_nombre_comercial']) && $datos['emisor_nombre_comercial'] !== null ? (string) $datos['emisor_nombre_comercial'] : null,
            (string) ($datos['emisor_direccion_fiscal'] ?? ''),
            (string) ($datos['emisor_ubigeo'] ?? ''),
            (string) ($datos['emisor_codigo_establecimiento'] ?? '0000'),
            (string) ($datos['emisor_departamento'] ?? ''),
            (string) ($datos['emisor_provincia'] ?? ''),
            (string) ($datos['emisor_distrito'] ?? ''),

            (string) ($datos['receptor_tipo_documento'] ?? '6'),
            (string) ($datos['receptor_numero_documento'] ?? ''),
            (string) ($datos['receptor_razon_social'] ?? ''),
            isset($datos['receptor_direccion_fiscal']) && $datos['receptor_direccion_fiscal'] !== null ? (string) $datos['receptor_direccion_fiscal'] : null,
            isset($datos['receptor_ubigeo']) && $datos['receptor_ubigeo'] !== null ? (string) $datos['receptor_ubigeo'] : null,
            isset($datos['receptor_email']) && $datos['receptor_email'] !== null ? (string) $datos['receptor_email'] : null,
            (string) ($datos['receptor_pais_codigo'] ?? 'PE'),

            !empty($datos['es_exportacion_hospedaje']),
            isset($datos['hospedaje_tam_virtual_numero']) && $datos['hospedaje_tam_virtual_numero'] !== null ? (string) $datos['hospedaje_tam_virtual_numero'] : null,
            isset($datos['hospedaje_fecha_ingreso_pais']) && $datos['hospedaje_fecha_ingreso_pais'] !== null ? (string) $datos['hospedaje_fecha_ingreso_pais'] : null,
            isset($datos['hospedaje_dias_permanencia']) && $datos['hospedaje_dias_permanencia'] !== null ? (int) $datos['hospedaje_dias_permanencia'] : null,
            isset($datos['hospedaje_leyenda_tributaria']) && $datos['hospedaje_leyenda_tributaria'] !== null ? (string) $datos['hospedaje_leyenda_tributaria'] : null,

            (string) ($datos['moneda_codigo'] ?? 'PEN'),
            isset($datos['tipo_cambio']) && $datos['tipo_cambio'] !== null ? (string) $datos['tipo_cambio'] : null,
            (string) ($datos['total_operaciones_gravadas'] ?? '0.00'),
            (string) ($datos['total_operaciones_exoneradas'] ?? '0.00'),
            (string) ($datos['total_operaciones_inafectas'] ?? '0.00'),
            (string) ($datos['total_operaciones_exportacion'] ?? '0.00'),
            (string) ($datos['total_operaciones_gratuitas'] ?? '0.00'),
            (string) ($datos['total_igv'] ?? '0.00'),
            (string) ($datos['total_descuentos'] ?? '0.00'),
            (string) ($datos['total_venta'] ?? '0.00'),

            (string) ($datos['estado_generacion'] ?? 'BORRADOR'),
            (string) ($datos['estado_transmision'] ?? 'NO_INICIADO'),
            (string) ($datos['estado_fiscal_sunat'] ?? 'PENDIENTE_ENVIO'),
            (string) ($datos['estado_rectificacion'] ?? 'ORIGINAL'),

            isset($datos['codigo_hash_cpe']) && $datos['codigo_hash_cpe'] !== null ? (string) $datos['codigo_hash_cpe'] : null,
            isset($datos['hash_xml_sha256']) && $datos['hash_xml_sha256'] !== null ? (string) $datos['hash_xml_sha256'] : null,
            isset($datos['hash_pdf_sha256']) && $datos['hash_pdf_sha256'] !== null ? (string) $datos['hash_pdf_sha256'] : null,
            isset($datos['ruta_archivo_xml']) && $datos['ruta_archivo_xml'] !== null ? (string) $datos['ruta_archivo_xml'] : null,
            isset($datos['ruta_archivo_pdf']) && $datos['ruta_archivo_pdf'] !== null ? (string) $datos['ruta_archivo_pdf'] : null,

            isset($datos['fecha_emision']) ? (string) $datos['fecha_emision'] : null,
            isset($datos['fecha_vencimiento']) && $datos['fecha_vencimiento'] !== null ? (string) $datos['fecha_vencimiento'] : null,
            (int) ($datos['creado_por_usuario_id'] ?? 1),
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            isset($datos['actualizado_en']) ? (string) $datos['actualizado_en'] : null,

            $lineas,
            $documentosRelacionados
        );
    }
}
