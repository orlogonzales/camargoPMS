<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios\CPE;

use CamargoPMS\Modelos\CPE\CpeComprobante;
use CamargoPMS\Servicios\CPE\DTO\RepresentacionFiscalCpe;

/**
 * Servicio encargado de transformar el agregado de dominio CpeComprobante en
 * una Representación Fiscal Inmutable (DTO) lista para la construcción UBL 2.1.
 */
class MapeadorFiscalUbl
{
    public function mapear(CpeComprobante $cpe): RepresentacionFiscalCpe
    {
        $tipoDoc = ConstantesUbl::MAPA_TIPO_COMPROBANTE[strtoupper(trim($cpe->obtenerTipoComprobante()))];
        $idDocumento = sprintf('%s-%08d', $cpe->obtenerSerie(), $cpe->obtenerCorrelativo());

        // Snapshot Emisor
        $emisorRuc = trim($cpe->obtenerEmisorRuc());
        $emisorRazon = trim($cpe->obtenerEmisorRazonSocial());
        $emisorNombreComercial = $cpe->obtenerEmisorNombreComercial() !== null ? trim($cpe->obtenerEmisorNombreComercial()) : null;
        $emisorCodEstablecimiento = trim($cpe->obtenerEmisorCodigoEstablecimiento());
        $emisorUbigeo = trim($cpe->obtenerEmisorUbigeo()) !== '' ? trim($cpe->obtenerEmisorUbigeo()) : null;
        $emisorDireccion = trim($cpe->obtenerEmisorDireccionFiscal()) !== '' ? trim($cpe->obtenerEmisorDireccionFiscal()) : null;
        $emisorDepto = trim($cpe->obtenerEmisorDepartamento()) !== '' ? trim($cpe->obtenerEmisorDepartamento()) : null;
        $emisorProv = trim($cpe->obtenerEmisorProvincia()) !== '' ? trim($cpe->obtenerEmisorProvincia()) : null;
        $emisorDist = trim($cpe->obtenerEmisorDistrito()) !== '' ? trim($cpe->obtenerEmisorDistrito()) : null;

        // Snapshot Receptor
        $recTipoRaw = strtoupper(trim($cpe->obtenerReceptorTipoDocumento()));
        $recTipo = ConstantesUbl::MAPA_TIPO_DOCUMENTO_IDENTIDAD[$recTipoRaw] ?? ConstantesUbl::DOC_SIN_RUC;
        $recNumero = trim($cpe->obtenerReceptorNumeroDocumento());
        $recRazon = trim($cpe->obtenerReceptorRazonSocial());
        $recDireccion = $cpe->obtenerReceptorDireccionFiscal() !== null && trim($cpe->obtenerReceptorDireccionFiscal()) !== ''
            ? trim($cpe->obtenerReceptorDireccionFiscal())
            : null;
        $recUbigeo = $cpe->obtenerReceptorUbigeo() !== null && trim($cpe->obtenerReceptorUbigeo()) !== ''
            ? trim($cpe->obtenerReceptorUbigeo())
            : null;
        $recPais = strtoupper(trim($cpe->obtenerReceptorPaisCodigo())) !== ''
            ? strtoupper(trim($cpe->obtenerReceptorPaisCodigo()))
            : 'PE';

        // Fechas
        $fechaEmision = $cpe->obtenerFechaEmision() ?? date('Y-m-d');
        $horaEmision = '00:00:00';
        if ($cpe->obtenerCreadoEn() !== null) {
            $parts = explode(' ', $cpe->obtenerCreadoEn());
            if (isset($parts[1])) {
                $horaEmision = substr($parts[1], 0, 8);
            }
        }
        $fechaVencimiento = $cpe->obtenerFechaVencimiento();

        // Forma de Pago y Cuotas
        $formaPago = strtoupper(trim($cpe->obtenerFormaPago()));
        $montoNeto = $cpe->obtenerMontoNetoPendiente();
        $cuotasMapeadas = [];

        if ($formaPago === 'CREDITO') {
            $cuotas = $cpe->obtenerCuotas();
            usort($cuotas, fn($a, $b) => $a->obtenerNumeroCuota() <=> $b->obtenerNumeroCuota());

            foreach ($cuotas as $cuota) {
                $cuotasMapeadas[] = [
                    'identificador' => sprintf('Cuota%03d', $cuota->obtenerNumeroCuota()),
                    'numero_cuota' => $cuota->obtenerNumeroCuota(),
                    'monto' => $cuota->obtenerMonto(),
                    'fecha_vencimiento' => $cuota->obtenerFechaVencimiento(),
                ];
            }
        }

        // Mapeo de Hospedajes
        $hospedajesMapeados = [];
        foreach ($cpe->obtenerHospedajes() as $h) {
            if ($h->obtenerId() !== null) {
                $hospedajesMapeados[$h->obtenerId()] = [
                    'id' => $h->obtenerId(),
                    'numero_orden' => $h->obtenerNumeroOrden(),
                    'nombres_apellidos' => $h->obtenerNombresApellidos(),
                    'tipo_documento' => $h->obtenerTipoDocumento(),
                    'numero_documento' => $h->obtenerNumeroDocumento(),
                    'pais_emision_pasaporte' => $h->obtenerPaisEmisionPasaporte(),
                    'pais_residencia' => $h->obtenerPaisResidencia(),
                    'fecha_ingreso_pais' => $h->obtenerFechaIngresoPais(),
                    'fecha_checkin' => $h->obtenerFechaCheckin(),
                    'fecha_checkout' => $h->obtenerFechaCheckout(),
                    'dias_permanencia' => $h->obtenerDiasPermanencia(),
                ];
            }
        }

        // Mapeo de Líneas y Propiedades Catálogo 55
        $lineasMapeadas = [];
        $lineas = $cpe->obtenerLineas();
        usort($lineas, fn($a, $b) => $a->obtenerNumeroOrden() <=> $b->obtenerNumeroOrden());

        foreach ($lineas as $linea) {
            $afectacion = trim($linea->obtenerTipoAfectacionIgv());
            $codTributo = ConstantesUbl::MAPA_AFECTACION_A_TRIBUTO[$afectacion] ?? ConstantesUbl::TRIBUTO_IGV;
            $detalleTributo = ConstantesUbl::DETALLE_TRIBUTOS[$codTributo];

            $hospId = $linea->obtenerCpeHospedajeId();
            $props55 = [];

            if ($hospId !== null && isset($hospedajesMapeados[$hospId])) {
                $h = $hospedajesMapeados[$hospId];

                // 4000: País emisión pasaporte (solo si tipo_doc == '7')
                if ($h['tipo_documento'] === ConstantesUbl::DOC_PASAPORTE && $h['pais_emision_pasaporte'] !== '') {
                    $props55[] = [
                        'codigo' => ConstantesUbl::PROP_55_PAIS_EMISION_PASAPORTE,
                        'nombre' => ConstantesUbl::NOMBRES_CATALOGO_55[ConstantesUbl::PROP_55_PAIS_EMISION_PASAPORTE],
                        'valor' => $h['pais_emision_pasaporte'],
                    ];
                }

                // 4001: País residencia habitual
                $props55[] = [
                    'codigo' => ConstantesUbl::PROP_55_PAIS_RESIDENCIA,
                    'nombre' => ConstantesUbl::NOMBRES_CATALOGO_55[ConstantesUbl::PROP_55_PAIS_RESIDENCIA],
                    'valor' => $h['pais_residencia'],
                ];

                // 4002: Fecha de ingreso al país
                $props55[] = [
                    'codigo' => ConstantesUbl::PROP_55_FECHA_INGRESO_PAIS,
                    'nombre' => ConstantesUbl::NOMBRES_CATALOGO_55[ConstantesUbl::PROP_55_FECHA_INGRESO_PAIS],
                    'valor' => $h['fecha_ingreso_pais'],
                ];

                // 4003 / 4004 vs 4006
                if ($linea->obtenerFechaConsumo() === null) {
                    // Línea de Alojamiento
                    $props55[] = [
                        'codigo' => ConstantesUbl::PROP_55_FECHA_CHECKIN,
                        'nombre' => ConstantesUbl::NOMBRES_CATALOGO_55[ConstantesUbl::PROP_55_FECHA_CHECKIN],
                        'valor' => $h['fecha_checkin'],
                    ];
                    $props55[] = [
                        'codigo' => ConstantesUbl::PROP_55_FECHA_CHECKOUT,
                        'nombre' => ConstantesUbl::NOMBRES_CATALOGO_55[ConstantesUbl::PROP_55_FECHA_CHECKOUT],
                        'valor' => $h['fecha_checkout'],
                    ];
                } else {
                    // Línea de Consumo
                    $props55[] = [
                        'codigo' => ConstantesUbl::PROP_55_FECHA_CONSUMO,
                        'nombre' => ConstantesUbl::NOMBRES_CATALOGO_55[ConstantesUbl::PROP_55_FECHA_CONSUMO],
                        'valor' => $linea->obtenerFechaConsumo(),
                    ];
                }

                // 4005: Días acumulados de permanencia
                $props55[] = [
                    'codigo' => ConstantesUbl::PROP_55_DIAS_PERMANENCIA,
                    'nombre' => ConstantesUbl::NOMBRES_CATALOGO_55[ConstantesUbl::PROP_55_DIAS_PERMANENCIA],
                    'valor' => (string)$h['dias_permanencia'],
                ];

                // 4007: Nombres y apellidos del huésped
                $props55[] = [
                    'codigo' => ConstantesUbl::PROP_55_NOMBRES_HUESPED,
                    'nombre' => ConstantesUbl::NOMBRES_CATALOGO_55[ConstantesUbl::PROP_55_NOMBRES_HUESPED],
                    'valor' => $h['nombres_apellidos'],
                ];

                // 4008: Tipo documento identidad huésped
                $props55[] = [
                    'codigo' => ConstantesUbl::PROP_55_TIPO_DOC_HUESPED,
                    'nombre' => ConstantesUbl::NOMBRES_CATALOGO_55[ConstantesUbl::PROP_55_TIPO_DOC_HUESPED],
                    'valor' => $h['tipo_documento'],
                ];

                // 4009: Número documento identidad huésped
                $props55[] = [
                    'codigo' => ConstantesUbl::PROP_55_NUM_DOC_HUESPED,
                    'nombre' => ConstantesUbl::NOMBRES_CATALOGO_55[ConstantesUbl::PROP_55_NUM_DOC_HUESPED],
                    'valor' => $h['numero_documento'],
                ];

                // Ordenar canónicamente por código (4000 a 4009)
                usort($props55, fn($a, $b) => strcmp($a['codigo'], $b['codigo']));
            }

            $lineasMapeadas[] = [
                'numero_linea' => $linea->obtenerNumeroOrden(),
                'cantidad' => $linea->obtenerCantidad(),
                'unidad_medida' => strtoupper(trim($linea->obtenerUnidadMedida())),
                'descripcion' => $linea->obtenerDescripcion(),
                'codigo_producto_interno' => $linea->obtenerCodigoProductoInterno(),
                'codigo_producto_sunat' => $linea->obtenerCodigoProductoSunat(),
                'valor_unitario' => $linea->obtenerValorUnitario(),
                'precio_unitario' => $linea->obtenerPrecioUnitario(),
                'base_imponible' => $linea->obtenerBaseImponible(),
                'descuento_monto' => $linea->obtenerDescuentoMonto(),
                'monto_igv' => $linea->obtenerMontoIgv(),
                'tipo_afectacion_igv' => $afectacion,
                'tasa_igv' => $linea->obtenerTasaIgv(),
                'codigo_tributo' => $codTributo,
                'nombre_tributo' => $detalleTributo['nombre'],
                'tipo_tributo_internacional' => $detalleTributo['tipo'],
                'categoria_tributo' => $detalleTributo['categoria'],
                'total_linea' => $linea->obtenerTotalLinea(),
                'cpe_hospedaje_id' => $hospId,
                'fecha_consumo' => $linea->obtenerFechaConsumo(),
                'propiedades_catalogo_55' => $props55,
            ];
        }

        // Mapeo de Documentos Relacionados
        $docsRelMapeados = [];
        foreach ($cpe->obtenerDocumentosRelacionados() as $doc) {
            $tipoRel = ConstantesUbl::MAPA_TIPO_COMPROBANTE[strtoupper(trim($doc->obtenerTipoDocumentoRelacionado()))]
                ?? ConstantesUbl::TIPO_FACTURA;

            $docsRelMapeados[] = [
                'tipo_documento' => $tipoRel,
                'serie' => $doc->obtenerSerieRelacionada(),
                'correlativo' => $doc->obtenerCorrelativoRelacionado(),
                'numero_completo' => sprintf('%s-%08d', $doc->obtenerSerieRelacionada(), $doc->obtenerCorrelativoRelacionado()),
                'codigo_motivo' => trim($doc->obtenerCodigoTipoRelacion()),
                'descripcion_motivo' => trim($doc->obtenerDescripcionMotivo()),
            ];
        }

        return new RepresentacionFiscalCpe(
            tipoComprobante: $tipoDoc,
            serie: $cpe->obtenerSerie(),
            correlativo: $cpe->obtenerCorrelativo(),
            idDocumento: $idDocumento,
            fechaEmision: $fechaEmision,
            horaEmision: $horaEmision,
            fechaVencimiento: $fechaVencimiento,
            monedaCodigo: strtoupper(trim($cpe->obtenerMonedaCodigo())),
            formaPago: $formaPago,
            montoNetoPendiente: $montoNeto,
            cuotas: $cuotasMapeadas,
            emisorRuc: $emisorRuc,
            emisorRazonSocial: $emisorRazon,
            emisorNombreComercial: $emisorNombreComercial,
            emisorCodigoEstablecimiento: $emisorCodEstablecimiento,
            emisorUbigeo: $emisorUbigeo,
            emisorDireccionFiscal: $emisorDireccion,
            emisorDepartamento: $emisorDepto,
            emisorProvincia: $emisorProv,
            emisorDistrito: $emisorDist,
            emisorPaisCodigo: 'PE',
            receptorTipoDocumento: $recTipo,
            receptorNumeroDocumento: $recNumero,
            receptorRazonSocial: $recRazon,
            receptorDireccionFiscal: $recDireccion,
            receptorUbigeo: $recUbigeo,
            receptorPaisCodigo: $recPais,
            esExportacionHospedaje: $cpe->esExportacionHospedaje(),
            hospedajes: $hospedajesMapeados,
            documentosRelacionados: $docsRelMapeados,
            totalOperacionesGravadas: $cpe->obtenerTotalOperacionesGravadas(),
            totalOperacionesExoneradas: $cpe->obtenerTotalOperacionesExoneradas(),
            totalOperacionesInafectas: $cpe->obtenerTotalOperacionesInafectas(),
            totalOperacionesExportacion: $cpe->obtenerTotalOperacionesExportacion(),
            totalOperacionesGratuitas: $cpe->obtenerTotalOperacionesGratuitas(),
            totalIgv: $cpe->obtenerTotalIgv(),
            totalDescuentos: $cpe->obtenerTotalDescuentos(),
            totalVenta: $cpe->obtenerTotalVenta(),
            lineas: $lineasMapeadas
        );
    }
}
