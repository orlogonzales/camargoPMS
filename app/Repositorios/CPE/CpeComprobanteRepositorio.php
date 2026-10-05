<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios\CPE;

use CamargoPMS\Modelos\CPE\CpeComprobante;
use CamargoPMS\Modelos\CPE\CpeDocumentoRelacionado;
use CamargoPMS\Modelos\CPE\CpeLinea;
use PDO;
use PDOException;

/**
 * Repositorio del agregado raíz de Comprobantes de Pago Electrónicos (CPE).
 * Persiste y reconstruye comprobantes, líneas tributarias y documentos vinculados.
 * No gestiona transacciones de forma autónoma (principio vincualente: quien abre la transacción la cierra).
 */
class CpeComprobanteRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Guarda el agregado completo (comprobante, líneas, atribuciones de cargos y documentos relacionados).
     * Debe invocarse dentro del contexto transaccional coordinado por el servicio de dominio.
     *
     * @throws PDOException Si ocurre un error de persistencia o colisión de unicidad.
     */
    public function guardar(CpeComprobante $cpe): int
    {
        $stmtCpe = $this->pdo->prepare(
            'INSERT INTO cpe_comprobantes (
                emisor_establecimiento_id, serie_id, cuenta_folio_id, tipo_comprobante,
                serie, correlativo, codigo_folio_completo, clave_idempotencia,
                emisor_ruc, emisor_razon_social, emisor_nombre_comercial, emisor_direccion_fiscal,
                emisor_ubigeo, emisor_codigo_establecimiento, emisor_departamento, emisor_provincia, emisor_distrito,
                receptor_tipo_documento, receptor_numero_documento, receptor_razon_social,
                receptor_direccion_fiscal, receptor_ubigeo, receptor_email, receptor_pais_codigo,
                es_exportacion_hospedaje, hospedaje_tam_virtual_numero, hospedaje_fecha_ingreso_pais,
                hospedaje_dias_permanencia, hospedaje_leyenda_tributaria,
                moneda_codigo, tipo_cambio, total_operaciones_gravadas, total_operaciones_exoneradas,
                total_operaciones_inafectas, total_operaciones_exportacion, total_operaciones_gratuitas,
                total_igv, total_descuentos, total_venta,
                estado_generacion, estado_transmision, estado_fiscal_sunat, estado_rectificacion,
                codigo_hash_cpe, hash_xml_sha256, hash_pdf_sha256, ruta_archivo_xml, ruta_archivo_pdf,
                fecha_emision, fecha_vencimiento, creado_por_usuario_id, creado_en, actualizado_en
            ) VALUES (
                :emisor_establecimiento_id, :serie_id, :cuenta_folio_id, :tipo_comprobante,
                :serie, :correlativo, :codigo_folio_completo, :clave_idempotencia,
                :emisor_ruc, :emisor_razon_social, :emisor_nombre_comercial, :emisor_direccion_fiscal,
                :emisor_ubigeo, :emisor_codigo_establecimiento, :emisor_departamento, :emisor_provincia, :emisor_distrito,
                :receptor_tipo_documento, :receptor_numero_documento, :receptor_razon_social,
                :receptor_direccion_fiscal, :receptor_ubigeo, :receptor_email, :receptor_pais_codigo,
                :es_exportacion_hospedaje, :hospedaje_tam_virtual_numero, :hospedaje_fecha_ingreso_pais,
                :hospedaje_dias_permanencia, :hospedaje_leyenda_tributaria,
                :moneda_codigo, :tipo_cambio, :total_operaciones_gravadas, :total_operaciones_exoneradas,
                :total_operaciones_inafectas, :total_operaciones_exportacion, :total_operaciones_gratuitas,
                :total_igv, :total_descuentos, :total_venta,
                :estado_generacion, :estado_transmision, :estado_fiscal_sunat, :estado_rectificacion,
                :codigo_hash_cpe, :hash_xml_sha256, :hash_pdf_sha256, :ruta_archivo_xml, :ruta_archivo_pdf,
                :fecha_emision, :fecha_vencimiento, :creado_por_usuario_id, NOW(), NOW()
            )'
        );

        $stmtCpe->execute([
            'emisor_establecimiento_id' => $cpe->obtenerEmisorEstablecimientoId(),
            'serie_id' => $cpe->obtenerSerieId(),
            'cuenta_folio_id' => $cpe->obtenerCuentaFolioId(),
            'tipo_comprobante' => $cpe->obtenerTipoComprobante(),
            'serie' => $cpe->obtenerSerie(),
            'correlativo' => $cpe->obtenerCorrelativo(),
            'codigo_folio_completo' => $cpe->obtenerCodigoFolioCompleto(),
            'clave_idempotencia' => $cpe->obtenerClaveIdempotencia(),
            'emisor_ruc' => $cpe->obtenerEmisorRuc(),
            'emisor_razon_social' => $cpe->obtenerEmisorRazonSocial(),
            'emisor_nombre_comercial' => $cpe->obtenerEmisorNombreComercial(),
            'emisor_direccion_fiscal' => $cpe->obtenerEmisorDireccionFiscal(),
            'emisor_ubigeo' => $cpe->obtenerEmisorUbigeo(),
            'emisor_codigo_establecimiento' => $cpe->obtenerEmisorCodigoEstablecimiento(),
            'emisor_departamento' => $cpe->obtenerEmisorDepartamento(),
            'emisor_provincia' => $cpe->obtenerEmisorProvincia(),
            'emisor_distrito' => $cpe->obtenerEmisorDistrito(),
            'receptor_tipo_documento' => $cpe->obtenerReceptorTipoDocumento(),
            'receptor_numero_documento' => $cpe->obtenerReceptorNumeroDocumento(),
            'receptor_razon_social' => $cpe->obtenerReceptorRazonSocial(),
            'receptor_direccion_fiscal' => $cpe->obtenerReceptorDireccionFiscal(),
            'receptor_ubigeo' => $cpe->obtenerReceptorUbigeo(),
            'receptor_email' => $cpe->obtenerReceptorEmail(),
            'receptor_pais_codigo' => $cpe->obtenerReceptorPaisCodigo(),
            'es_exportacion_hospedaje' => $cpe->esExportacionHospedaje() ? 1 : 0,
            'hospedaje_tam_virtual_numero' => $cpe->obtenerHospedajeTamVirtualNumero(),
            'hospedaje_fecha_ingreso_pais' => $cpe->obtenerHospedajeFechaIngresoPais(),
            'hospedaje_dias_permanencia' => $cpe->obtenerHospedajeDiasPermanencia(),
            'hospedaje_leyenda_tributaria' => $cpe->obtenerHospedajeLeyendaTributaria(),
            'moneda_codigo' => $cpe->obtenerMonedaCodigo(),
            'tipo_cambio' => $cpe->obtenerTipoCambio(),
            'total_operaciones_gravadas' => $cpe->obtenerTotalOperacionesGravadas(),
            'total_operaciones_exoneradas' => $cpe->obtenerTotalOperacionesExoneradas(),
            'total_operaciones_inafectas' => $cpe->obtenerTotalOperacionesInafectas(),
            'total_operaciones_exportacion' => $cpe->obtenerTotalOperacionesExportacion(),
            'total_operaciones_gratuitas' => $cpe->obtenerTotalOperacionesGratuitas(),
            'total_igv' => $cpe->obtenerTotalIgv(),
            'total_descuentos' => $cpe->obtenerTotalDescuentos(),
            'total_venta' => $cpe->obtenerTotalVenta(),
            'estado_generacion' => $cpe->obtenerEstadoGeneracion(),
            'estado_transmision' => $cpe->obtenerEstadoTransmision(),
            'estado_fiscal_sunat' => $cpe->obtenerEstadoFiscalSunat(),
            'estado_rectificacion' => $cpe->obtenerEstadoRectificacion(),
            'codigo_hash_cpe' => $cpe->obtenerCodigoHashCpe(),
            'hash_xml_sha256' => $cpe->obtenerHashXmlSha256(),
            'hash_pdf_sha256' => $cpe->obtenerHashPdfSha256(),
            'ruta_archivo_xml' => $cpe->obtenerRutaArchivoXml(),
            'ruta_archivo_pdf' => $cpe->obtenerRutaArchivoPdf(),
            'fecha_emision' => $cpe->obtenerFechaEmision() ?? date('Y-m-d H:i:s'),
            'fecha_vencimiento' => $cpe->obtenerFechaVencimiento(),
            'creado_por_usuario_id' => $cpe->obtenerCreadoPorUsuarioId(),
        ]);

        $cpeId = (int) $this->pdo->lastInsertId();
        $cpe->fijarId($cpeId);

        // Guardar líneas fiscales asociadas
        $stmtLinea = $this->pdo->prepare(
            'INSERT INTO cpe_lineas (
                cpe_id, numero_orden, codigo_producto_interno, codigo_producto_sunat,
                descripcion, unidad_medida, cantidad, valor_unitario, precio_unitario,
                descuento_monto, base_imponible, tipo_afectacion_igv, tasa_igv,
                monto_igv, total_linea, creado_en
            ) VALUES (
                :cpe_id, :numero_orden, :codigo_producto_interno, :codigo_producto_sunat,
                :descripcion, :unidad_medida, :cantidad, :valor_unitario, :precio_unitario,
                :descuento_monto, :base_imponible, :tipo_afectacion_igv, :tasa_igv,
                :monto_igv, :total_linea, NOW()
            )'
        );

        $stmtCargo = $this->pdo->prepare(
            'INSERT INTO cpe_linea_cargos (
                cpe_linea_id, cargo_cuenta_id, cantidad_atribuida, monto_atribuido, creado_en
            ) VALUES (
                :cpe_linea_id, :cargo_cuenta_id, :cantidad_atribuida, :monto_atribuido, NOW()
            )'
        );

        foreach ($cpe->obtenerLineas() as $linea) {
            $linea->fijarCpeId($cpeId);
            $stmtLinea->execute([
                'cpe_id' => $cpeId,
                'numero_orden' => $linea->obtenerNumeroOrden(),
                'codigo_producto_interno' => $linea->obtenerCodigoProductoInterno(),
                'codigo_producto_sunat' => $linea->obtenerCodigoProductoSunat(),
                'descripcion' => $linea->obtenerDescripcion(),
                'unidad_medida' => $linea->obtenerUnidadMedida(),
                'cantidad' => $linea->obtenerCantidad(),
                'valor_unitario' => $linea->obtenerValorUnitario(),
                'precio_unitario' => $linea->obtenerPrecioUnitario(),
                'descuento_monto' => $linea->obtenerDescuentoMonto(),
                'base_imponible' => $linea->obtenerBaseImponible(),
                'tipo_afectacion_igv' => $linea->obtenerTipoAfectacionIgv(),
                'tasa_igv' => $linea->obtenerTasaIgv(),
                'monto_igv' => $linea->obtenerMontoIgv(),
                'total_linea' => $linea->obtenerTotalLinea(),
            ]);

            $lineaId = (int) $this->pdo->lastInsertId();
            $linea->fijarId($lineaId);

            // Persistir atribución M:N a cargos de cuenta si existen
            foreach ($linea->obtenerCargosAtribuidos() as $atribucion) {
                $stmtCargo->execute([
                    'cpe_linea_id' => $lineaId,
                    'cargo_cuenta_id' => $atribucion['cargo_cuenta_id'],
                    'cantidad_atribuida' => $atribucion['cantidad_atribuida'],
                    'monto_atribuido' => $atribucion['monto_atribuido'],
                ]);
            }
        }

        // Guardar documentos relacionados (ej. notas rectificatorias)
        $stmtDocRel = $this->pdo->prepare(
            'INSERT INTO cpe_documentos_relacionados (
                cpe_id, cpe_relacionado_id, tipo_documento_relacionado, serie_relacionada,
                correlativo_relacionado, fecha_emision_relacionada, codigo_tipo_relacion,
                descripcion_motivo, monto_ajustado, creado_en
            ) VALUES (
                :cpe_id, :cpe_relacionado_id, :tipo_documento_relacionado, :serie_relacionada,
                :correlativo_relacionado, :fecha_emision_relacionada, :codigo_tipo_relacion,
                :descripcion_motivo, :monto_ajustado, NOW()
            )'
        );

        foreach ($cpe->obtenerDocumentosRelacionados() as $docRel) {
            $docRel->fijarCpeId($cpeId);
            $stmtDocRel->execute([
                'cpe_id' => $cpeId,
                'cpe_relacionado_id' => $docRel->obtenerCpeRelacionadoId(),
                'tipo_documento_relacionado' => $docRel->obtenerTipoDocumentoRelacionado(),
                'serie_relacionada' => $docRel->obtenerSerieRelacionada(),
                'correlativo_relacionado' => $docRel->obtenerCorrelativoRelacionado(),
                'fecha_emision_relacionada' => $docRel->obtenerFechaEmisionRelacionada(),
                'codigo_tipo_relacion' => $docRel->obtenerCodigoTipoRelacion(),
                'descripcion_motivo' => $docRel->obtenerDescripcionMotivo(),
                'monto_ajustado' => $docRel->obtenerMontoAjustado(),
            ]);

            $docRelId = (int) $this->pdo->lastInsertId();
            $docRel->fijarId($docRelId);
        }

        return $cpeId;
    }

    /**
     * Obtiene el agregado completo de un CPE por su ID primario, reconstruyendo líneas y relaciones.
     */
    public function obtenerPorId(int $id): ?CpeComprobante
    {
        $stmtCpe = $this->pdo->prepare('SELECT * FROM cpe_comprobantes WHERE id = :id');
        $stmtCpe->execute(['id' => $id]);

        $filaCpe = $stmtCpe->fetch(PDO::FETCH_ASSOC);
        if (!$filaCpe) {
            return null;
        }

        // Cargar líneas fiscales y sus cargos atribuidos
        $stmtLineas = $this->pdo->prepare('SELECT * FROM cpe_lineas WHERE cpe_id = :cpe_id ORDER BY numero_orden ASC');
        $stmtLineas->execute(['cpe_id' => $id]);
        $filasLineas = $stmtLineas->fetchAll(PDO::FETCH_ASSOC);

        $stmtCargos = $this->pdo->prepare('SELECT * FROM cpe_linea_cargos WHERE cpe_linea_id = :linea_id ORDER BY id ASC');

        $lineas = [];
        foreach ($filasLineas as $filaL) {
            $stmtCargos->execute(['linea_id' => (int) $filaL['id']]);
            $filasCargos = $stmtCargos->fetchAll(PDO::FETCH_ASSOC);

            $cargosAtribuidos = [];
            foreach ($filasCargos as $fc) {
                $cargosAtribuidos[] = [
                    'cargo_cuenta_id' => (int) $fc['cargo_cuenta_id'],
                    'cantidad_atribuida' => (string) $fc['cantidad_atribuida'],
                    'monto_atribuido' => (string) $fc['monto_atribuido'],
                ];
            }

            $linea = CpeLinea::desdeArreglo($filaL);
            $linea->fijarCargosAtribuidos($cargosAtribuidos);
            $lineas[] = $linea;
        }

        // Cargar documentos relacionados
        $stmtDocRel = $this->pdo->prepare('SELECT * FROM cpe_documentos_relacionados WHERE cpe_id = :cpe_id ORDER BY id ASC');
        $stmtDocRel->execute(['cpe_id' => $id]);
        $filasDocRel = $stmtDocRel->fetchAll(PDO::FETCH_ASSOC);

        $documentosRelacionados = [];
        foreach ($filasDocRel as $fdr) {
            $documentosRelacionados[] = CpeDocumentoRelacionado::desdeArreglo($fdr);
        }

        return CpeComprobante::desdeArreglo($filaCpe, $lineas, $documentosRelacionados);
    }

    /**
     * Busca un comprobante por su cuarteto fiscal único (establecimiento, tipo, serie, correlativo).
     */
    public function obtenerPorNumeroFiscal(
        int $establecimientoId,
        string $tipoComprobante,
        string $serie,
        int $correlativo
    ): ?CpeComprobante {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM cpe_comprobantes
             WHERE emisor_establecimiento_id = :estab_id
               AND tipo_comprobante = :tipo
               AND serie = :serie
               AND correlativo = :correlativo'
        );
        $stmt->execute([
            'estab_id' => $establecimientoId,
            'tipo' => $tipoComprobante,
            'serie' => $serie,
            'correlativo' => $correlativo,
        ]);

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fila) {
            return null;
        }

        return $this->obtenerPorId((int) $fila['id']);
    }

    /**
     * Busca un comprobante existente a partir del par (emisor_establecimiento_id, clave_idempotencia).
     */
    public function buscarPorIdempotencia(int $establecimientoId, string $claveIdempotencia): ?CpeComprobante
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM cpe_comprobantes
             WHERE emisor_establecimiento_id = :estab_id
               AND clave_idempotencia = :clave'
        );
        $stmt->execute([
            'estab_id' => $establecimientoId,
            'clave' => $claveIdempotencia,
        ]);

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fila) {
            return null;
        }

        return $this->obtenerPorId((int) $fila['id']);
    }

    /**
     * Lista todos los comprobantes emitidos contra una cuenta/folio hotelero específico.
     *
     * @return array<CpeComprobante>
     */
    public function listarPorFolioId(int $folioId): array
    {
        $stmt = $this->pdo->prepare('SELECT id FROM cpe_comprobantes WHERE cuenta_folio_id = :folio_id ORDER BY id ASC');
        $stmt->execute(['folio_id' => $folioId]);

        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $comprobantes = [];
        foreach ($filas as $fila) {
            $cpe = $this->obtenerPorId((int) $fila['id']);
            if ($cpe !== null) {
                $comprobantes[] = $cpe;
            }
        }

        return $comprobantes;
    }

    /**
     * Actualiza los estados ortogonales de un comprobante.
     *
     * @param array<string, string> $estados Arreglo asociativo con claves como 'estado_generacion', 'estado_transmision', 'estado_fiscal_sunat', 'estado_rectificacion'.
     */
    public function actualizarEstados(int $cpeId, array $estados): bool
    {
        $setClauses = [];
        $params = ['id' => $cpeId];

        $camposPermitidos = [
            'estado_generacion',
            'estado_transmision',
            'estado_fiscal_sunat',
            'estado_rectificacion',
        ];

        foreach ($camposPermitidos as $campo) {
            if (isset($estados[$campo])) {
                $setClauses[] = "$campo = :$campo";
                $params[$campo] = $estados[$campo];
            }
        }

        if (empty($setClauses)) {
            return false;
        }

        $setClauses[] = 'actualizado_en = NOW()';
        $sql = 'UPDATE cpe_comprobantes SET ' . implode(', ', $setClauses) . ' WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Actualiza las rutas en storage y hashes criptográficos de los artefactos generados (XML, PDF).
     */
    public function actualizarArchivosYHash(
        int $cpeId,
        ?string $codigoHashCpe,
        ?string $hashXml,
        ?string $hashPdf,
        ?string $rutaXml,
        ?string $rutaPdf
    ): bool {
        $stmt = $this->pdo->prepare(
            'UPDATE cpe_comprobantes SET
                codigo_hash_cpe = :codigo_hash_cpe,
                hash_xml_sha256 = :hash_xml,
                hash_pdf_sha256 = :hash_pdf,
                ruta_archivo_xml = :ruta_xml,
                ruta_archivo_pdf = :ruta_pdf,
                actualizado_en = NOW()
             WHERE id = :id'
        );

        return $stmt->execute([
            'id' => $cpeId,
            'codigo_hash_cpe' => $codigoHashCpe,
            'hash_xml' => $hashXml,
            'hash_pdf' => $hashPdf,
            'ruta_xml' => $rutaXml,
            'ruta_pdf' => $rutaPdf,
        ]);
    }
}
