<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios\CPE;

use CamargoPMS\Excepciones\CaracterInvalidoXmlExcepcion;
use CamargoPMS\Excepciones\ValidacionFiscalExcepcion;
use CamargoPMS\Modelos\CPE\CpeComprobante;
use CamargoPMS\Modelos\CPE\CpeHospedajeFiscal;
use DateTimeImmutable;

/**
 * Validador exhaustivo del contrato normativo fiscal antes de la serialización en UBL 2.1.
 * Separa formalmente las reglas de validación de negocio de la construcción del DOM XML.
 */
class ValidadorFiscalUbl
{
    /**
     * Valida integralmente el agregado de dominio CpeComprobante antes de su transformación a UBL.
     *
     * @throws ValidacionFiscalExcepcion Si algún dato fiscal es inválido, contradictorio o inconsistente.
     * @throws CaracterInvalidoXmlExcepcion Si algún campo de texto contiene caracteres no conformes con XML 1.0.
     */
    public function validar(CpeComprobante $cpe): void
    {
        $this->validarCaracteresXmlSeguros($cpe);
        $tipoDoc = $this->validarTipoComprobante($cpe);
        $this->validarMoneda($cpe);
        $this->validarEmisor($cpe);
        $this->validarReceptor($cpe, $tipoDoc);
        $this->validarFormaPagoYCuotas($cpe, $tipoDoc);
        $this->validarLineas($cpe);
        $this->validarSubdominioHospedajeDl919($cpe);
        $this->validarDocumentosRelacionados($cpe, $tipoDoc);
        $this->validarConsistenciaTotales($cpe);
    }

    /**
     * Verifica que ninguna cadena contenga caracteres de control incompatibles con XML 1.0.
     * Permitidos por XML 1.0: #x9 (Tab), #xA (LF), #xD (CR), [#x20-#xD7FF], [#xE000-#xFFFD], [#x10000-#x10FFFF].
     */
    private function validarCaracteresXmlSeguros(CpeComprobante $cpe): void
    {
        $textosAValidar = [
            'serie' => $cpe->obtenerSerie(),
            'emisor_razon_social' => $cpe->obtenerEmisorRazonSocial(),
            'emisor_direccion_fiscal' => $cpe->obtenerEmisorDireccionFiscal(),
            'receptor_razon_social' => $cpe->obtenerReceptorRazonSocial(),
            'receptor_direccion_fiscal' => $cpe->obtenerReceptorDireccionFiscal() ?? '',
        ];

        foreach ($cpe->obtenerLineas() as $i => $linea) {
            $textosAValidar["linea_{$i}_descripcion"] = $linea->obtenerDescripcion();
        }

        foreach ($cpe->obtenerHospedajes() as $i => $hosp) {
            $textosAValidar["hospedaje_{$i}_nombres"] = $hosp->obtenerNombresApellidos();
        }

        foreach ($cpe->obtenerDocumentosRelacionados() as $i => $doc) {
            $textosAValidar["doc_rel_{$i}_motivo"] = $doc->obtenerDescripcionMotivo();
        }

        $patronInvalido = '/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u';

        foreach ($textosAValidar as $campo => $valor) {
            if ($valor !== '' && preg_match($patronInvalido, $valor)) {
                throw new CaracterInvalidoXmlExcepcion(
                    "El campo fiscal '{$campo}' contiene caracteres no permitidos por la norma XML 1.0."
                );
            }
        }
    }

    private function validarTipoComprobante(CpeComprobante $cpe): string
    {
        $tipo = strtoupper(trim($cpe->obtenerTipoComprobante()));
        if (!isset(ConstantesUbl::MAPA_TIPO_COMPROBANTE[$tipo])) {
            throw new ValidacionFiscalExcepcion("El tipo de comprobante '{$tipo}' no es válido para UBL 2.1.");
        }

        return ConstantesUbl::MAPA_TIPO_COMPROBANTE[$tipo];
    }

    private function validarMoneda(CpeComprobante $cpe): void
    {
        $moneda = strtoupper(trim($cpe->obtenerMonedaCodigo()));
        if (!in_array($moneda, ['PEN', 'USD'], true)) {
            throw new ValidacionFiscalExcepcion("La moneda '{$moneda}' no está permitida. Solo se soportan PEN y USD.");
        }
    }

    private function validarEmisor(CpeComprobante $cpe): void
    {
        $ruc = trim($cpe->obtenerEmisorRuc());
        if (strlen($ruc) !== 11 || !ctype_digit($ruc)) {
            throw new ValidacionFiscalExcepcion("El RUC del emisor '{$ruc}' debe contener exactamente 11 dígitos numéricos.");
        }

        if (trim($cpe->obtenerEmisorRazonSocial()) === '') {
            throw new ValidacionFiscalExcepcion('La razón social del emisor es obligatoria.');
        }

        $codEstablecimiento = trim($cpe->obtenerEmisorCodigoEstablecimiento());
        if (strlen($codEstablecimiento) !== 4 || !ctype_digit($codEstablecimiento)) {
            throw new ValidacionFiscalExcepcion("El código de establecimiento emisor '{$codEstablecimiento}' debe tener 4 dígitos.");
        }
    }

    private function validarReceptor(CpeComprobante $cpe, string $tipoDoc): void
    {
        $tipoDocRecRaw = strtoupper(trim($cpe->obtenerReceptorTipoDocumento()));
        if (!isset(ConstantesUbl::MAPA_TIPO_DOCUMENTO_IDENTIDAD[$tipoDocRecRaw])) {
            throw new ValidacionFiscalExcepcion("El tipo de documento del receptor '{$tipoDocRecRaw}' no es válido.");
        }

        $tipoDocRec = ConstantesUbl::MAPA_TIPO_DOCUMENTO_IDENTIDAD[$tipoDocRecRaw];
        $numDoc = trim($cpe->obtenerReceptorNumeroDocumento());

        if ($tipoDoc === ConstantesUbl::TIPO_FACTURA) {
            if ($tipoDocRec !== ConstantesUbl::DOC_RUC) {
                throw new ValidacionFiscalExcepcion("Para una Factura (01), el receptor debe contar obligatoriamente con RUC (Catálogo 06 código '6'). Se recibió '{$tipoDocRecRaw}'.");
            }
            if (strlen($numDoc) !== 11 || !ctype_digit($numDoc)) {
                throw new ValidacionFiscalExcepcion("El RUC del receptor '{$numDoc}' debe tener exactamente 11 dígitos numéricos.");
            }
        }

        if (trim($cpe->obtenerReceptorRazonSocial()) === '') {
            throw new ValidacionFiscalExcepcion('La razón social o nombre del receptor es obligatorio.');
        }
    }

    private function validarFormaPagoYCuotas(CpeComprobante $cpe, string $tipoDoc): void
    {
        $formaPago = strtoupper(trim($cpe->obtenerFormaPago()));
        if (!in_array($formaPago, ['CONTADO', 'CREDITO'], true)) {
            throw new ValidacionFiscalExcepcion("La forma de pago '{$formaPago}' no es válida. Debe ser CONTADO o CREDITO.");
        }

        if ($formaPago === 'CREDITO') {
            $montoNeto = $cpe->obtenerMontoNetoPendiente();
            if ($montoNeto === null || bccomp($montoNeto, '0.00', 2) <= 0) {
                throw new ValidacionFiscalExcepcion('Una operación al CREDITO exige declarar un monto_neto_pendiente estrictamente positivo (> 0.00).');
            }

            $cuotas = $cpe->obtenerCuotas();
            if (empty($cuotas)) {
                throw new ValidacionFiscalExcepcion('Una operación al CREDITO exige al menos una cuota programada.');
            }

            // Ordenar cuotas determinísticamente por numeroCuota ASC
            usort($cuotas, fn($a, $b) => $a->obtenerNumeroCuota() <=> $b->obtenerNumeroCuota());

            $sumaCuotas = '0.00';
            $ultimoNumero = 0;
            $ultimaFecha = null;

            foreach ($cuotas as $cuota) {
                $num = $cuota->obtenerNumeroCuota();
                if ($num <= 0) {
                    throw new ValidacionFiscalExcepcion("El número de cuota {$num} debe ser estrictamente positivo (> 0).");
                }
                if ($num > 999) {
                    throw new ValidacionFiscalExcepcion("El número de cuota {$num} excede la capacidad técnica SUNAT de 3 dígitos (máximo 999).");
                }
                if ($num !== $ultimoNumero + 1) {
                    throw new ValidacionFiscalExcepcion("Las cuotas deben formar una secuencia continua ascendente sin huecos ni duplicados. Se esperaba {$num}, anterior {$ultimoNumero}.");
                }
                $ultimoNumero = $num;

                $montoCuota = $cuota->obtenerMonto();
                if (bccomp($montoCuota, '0.00', 2) <= 0) {
                    throw new ValidacionFiscalExcepcion("El monto de la cuota {$num} debe ser estrictamente positivo.");
                }
                $sumaCuotas = bcadd($sumaCuotas, $montoCuota, 2);

                $fechaVenc = $cuota->obtenerFechaVencimiento();
                $dtVenc = DateTimeImmutable::createFromFormat('Y-m-d', $fechaVenc);
                if ($dtVenc === false || $dtVenc->format('Y-m-d') !== $fechaVenc) {
                    throw new ValidacionFiscalExcepcion("La fecha de vencimiento '{$fechaVenc}' de la cuota {$num} debe tener formato AAAA-MM-DD.");
                }

                if ($ultimaFecha !== null && $fechaVenc < $ultimaFecha) {
                    throw new ValidacionFiscalExcepcion("Las fechas de vencimiento de las cuotas deben tener orden cronológico no decreciente.");
                }
                $ultimaFecha = $fechaVenc;
            }

            if (bccomp($sumaCuotas, $montoNeto, 2) !== 0) {
                throw new ValidacionFiscalExcepcion("La suma de las cuotas ({$sumaCuotas}) no coincide con el monto neto pendiente ({$montoNeto}).");
            }
        }
    }

    private function validarLineas(CpeComprobante $cpe): void
    {
        $lineas = $cpe->obtenerLineas();
        if (empty($lineas)) {
            throw new ValidacionFiscalExcepcion('El comprobante debe contener al menos una línea fiscal de bien o servicio.');
        }

        // Ordenar líneas por numeroOrden ASC
        usort($lineas, fn($a, $b) => $a->obtenerNumeroOrden() <=> $b->obtenerNumeroOrden());

        $ultimoOrden = 0;
        foreach ($lineas as $linea) {
            $orden = $linea->obtenerNumeroOrden();
            if ($orden !== $ultimoOrden + 1) {
                throw new ValidacionFiscalExcepcion("Las líneas deben estar numeradas en secuencia continua 1..N sin huecos ni duplicados. Se esperaba {$orden}, anterior {$ultimoOrden}.");
            }
            $ultimoOrden = $orden;

            if (trim($linea->obtenerDescripcion()) === '') {
                throw new ValidacionFiscalExcepcion("La descripción de la línea {$orden} es obligatoria.");
            }

            $unidad = strtoupper(trim($linea->obtenerUnidadMedida()));
            if (!in_array($unidad, ConstantesUbl::UNIDADES_VALIDAS, true)) {
                throw new ValidacionFiscalExcepcion("La unidad de medida '{$unidad}' en la línea {$orden} no es válida según el Catálogo 03.");
            }

            if (bccomp($linea->obtenerCantidad(), '0.0000', 4) <= 0) {
                throw new ValidacionFiscalExcepcion("La cantidad en la línea {$orden} debe ser estrictamente positiva (> 0).");
            }

            $afectacion = trim($linea->obtenerTipoAfectacionIgv());
            if (!isset(ConstantesUbl::MAPA_AFECTACION_A_TRIBUTO[$afectacion])) {
                throw new ValidacionFiscalExcepcion("El tipo de afectación al IGV '{$afectacion}' en la línea {$orden} no es válido.");
            }
        }
    }

    private function validarSubdominioHospedajeDl919(CpeComprobante $cpe): void
    {
        $esDl919 = $cpe->esExportacionHospedaje();
        $lineas = $cpe->obtenerLineas();
        $hospedajes = $cpe->obtenerHospedajes();

        // Mapear hospedajes por ID para verificación O(1) de claves foráneas en memoria
        $mapaHospedajes = [];
        foreach ($hospedajes as $h) {
            if ($h->obtenerId() !== null) {
                $mapaHospedajes[$h->obtenerId()] = $h;
            }
        }

        $hayLineasHospedaje = false;

        foreach ($lineas as $linea) {
            $hospedajeId = $linea->obtenerCpeHospedajeId();

            if ($hospedajeId !== null) {
                $hayLineasHospedaje = true;

                // 1. Debe existir en la colección de hospedajes del comprobante (previene cross-CPE)
                if (!isset($mapaHospedajes[$hospedajeId])) {
                    throw new ValidacionFiscalExcepcion(
                        "La línea {$linea->obtenerNumeroOrden()} referencia a un hospedaje ID {$hospedajeId} que no pertenece a este comprobante (infracción cross-CPE)."
                    );
                }

                $hospedaje = $mapaHospedajes[$hospedajeId];

                // 2. La afectación debe ser '40' (Exportación de bienes o servicios)
                if ($linea->obtenerTipoAfectacionIgv() !== '40') {
                    throw new ValidacionFiscalExcepcion(
                        "La línea {$linea->obtenerNumeroOrden()} vinculada al régimen de hospedaje D.L. 919 debe tener tipo_afectacion_igv = '40'. Se encontró '{$linea->obtenerTipoAfectacionIgv()}'."
                    );
                }

                // 3. Documento del huésped debe ser '7' (Pasaporte) o '4' (CE), jamás '1' (DNI)
                $tipoDocHuesped = $hospedaje->obtenerTipoDocumento();
                if (!in_array($tipoDocHuesped, ['7', '4'], true)) {
                    throw new ValidacionFiscalExcepcion(
                        "El huésped fiscal en D.L. 919 debe identificarse con Pasaporte ('7') o Carnet de Extranjería ('4'). No se permite DNI ('{$tipoDocHuesped}')."
                    );
                }

                // 4. País de residencia habitual debe ser diferente de 'PE'
                if ($hospedaje->obtenerPaisResidencia() === 'PE') {
                    throw new ValidacionFiscalExcepcion(
                        "El huésped en el régimen D.L. 919 debe ser un sujeto no domiciliado (país de residencia diferente de PE)."
                    );
                }

                // 5. Si es Pasaporte, país emisor es obligatorio y de 2 letras
                if ($tipoDocHuesped === '7') {
                    $paisEmision = $hospedaje->obtenerPaisEmisionPasaporte();
                    if (strlen($paisEmision) !== 2) {
                        throw new ValidacionFiscalExcepcion("El código de país emisor del pasaporte debe tener 2 letras ISO 3166-1.");
                    }
                }

                // 6. Permanencia <= 60 días
                if ($hospedaje->obtenerDiasPermanencia() > 60) {
                    throw new ValidacionFiscalExcepcion(
                        "El huésped registra {$hospedaje->obtenerDiasPermanencia()} días de permanencia, superando el límite legal de 60 días para el beneficio D.L. 919."
                    );
                }

                // 7. Si es línea de consumo, fecha_consumo debe estar dentro de checkin y checkout
                $fechaConsumo = $linea->obtenerFechaConsumo();
                if ($fechaConsumo !== null) {
                    $checkin = $hospedaje->obtenerFechaCheckin();
                    $checkout = $hospedaje->obtenerFechaCheckout();
                    if ($fechaConsumo < $checkin || $fechaConsumo > $checkout) {
                        throw new ValidacionFiscalExcepcion(
                            "La fecha de consumo '{$fechaConsumo}' en la línea {$linea->obtenerNumeroOrden()} se encuentra fuera del rango de estancia en el establecimiento ({$checkin} a {$checkout})."
                        );
                    }
                }
            }
        }

        // Si el comprobante declara es_exportacion_hospedaje = 1, debe tener al menos una línea con cpe_hospedaje_id
        if ($esDl919 && !$hayLineasHospedaje) {
            throw new ValidacionFiscalExcepcion(
                'El comprobante está marcado como es_exportacion_hospedaje = true, pero no posee ninguna línea vinculada a un registro de huésped fiscal.'
            );
        }
    }

    private function validarDocumentosRelacionados(CpeComprobante $cpe, string $tipoDoc): void
    {
        $docsRel = $cpe->obtenerDocumentosRelacionados();

        if ($tipoDoc === ConstantesUbl::TIPO_NOTA_CREDITO || $tipoDoc === ConstantesUbl::TIPO_NOTA_DEBITO) {
            if (empty($docsRel)) {
                throw new ValidacionFiscalExcepcion('Una Nota de Crédito o Débito debe referenciar obligatoriamente al menos a un comprobante modificado.');
            }

            $doc = $docsRel[0];
            if (trim($doc->obtenerSerieRelacionada()) === '' || $doc->obtenerCorrelativoRelacionado() <= 0) {
                throw new ValidacionFiscalExcepcion('El documento relacionado debe contener serie y correlativo válidos.');
            }

            $motivo = trim($doc->obtenerCodigoTipoRelacion());
            if ($tipoDoc === ConstantesUbl::TIPO_NOTA_CREDITO) {
                $motivosValidos = ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12', '13'];
                if (!in_array($motivo, $motivosValidos, true)) {
                    throw new ValidacionFiscalExcepcion("El código de motivo '{$motivo}' de la Nota de Crédito no es válido según el Catálogo 09.");
                }

                // Motivo 13: Ajustes - montos y/o fechas de pago exige PaymentTerms y cuotas
                if ($motivo === '13') {
                    if (!$cpe->esCredito()) {
                        throw new ValidacionFiscalExcepcion("Una Nota de Crédito emitida bajo el motivo 13 exige declarar forma de pago CREDITO con reprogramación de cuotas.");
                    }
                    if (empty($cpe->obtenerCuotas())) {
                        throw new ValidacionFiscalExcepcion("Una Nota de Crédito emitida bajo el motivo 13 exige al menos una cuota de reprogramación.");
                    }
                }
            } else {
                $motivosValidosNd = ['01', '02', '03'];
                if (!in_array($motivo, $motivosValidosNd, true)) {
                    throw new ValidacionFiscalExcepcion("El código de motivo '{$motivo}' de la Nota de Débito no es válido según el Catálogo 10.");
                }
            }
        }
    }

    private function validarConsistenciaTotales(CpeComprobante $cpe): void
    {
        $sumaGravadas = '0.00';
        $sumaExportacion = '0.00';
        $sumaIgv = '0.00';
        $sumaTotalVenta = '0.00';

        foreach ($cpe->obtenerLineas() as $linea) {
            $afectacion = $linea->obtenerTipoAfectacionIgv();
            $base = $linea->obtenerBaseImponible();
            $igv = $linea->obtenerMontoIgv();
            $total = $linea->obtenerTotalLinea();

            if ($afectacion === '10') {
                $sumaGravadas = bcadd($sumaGravadas, $base, 2);
            } elseif ($afectacion === '40') {
                $sumaExportacion = bcadd($sumaExportacion, $base, 2);
            }

            $sumaIgv = bcadd($sumaIgv, $igv, 2);
            $sumaTotalVenta = bcadd($sumaTotalVenta, $total, 2);
        }

        // Comparar con cabecera (tolerancia 0.01 por posibles redondeos en líneas múltiples)
        if (abs((float)bcsub($sumaGravadas, $cpe->obtenerTotalOperacionesGravadas(), 2)) > 0.05) {
            throw new ValidacionFiscalExcepcion("La sumatoria de operaciones gravadas de las líneas ({$sumaGravadas}) difiere de la cabecera ({$cpe->obtenerTotalOperacionesGravadas()}).");
        }

        if (abs((float)bcsub($sumaExportacion, $cpe->obtenerTotalOperacionesExportacion(), 2)) > 0.05) {
            throw new ValidacionFiscalExcepcion("La sumatoria de exportación de las líneas ({$sumaExportacion}) difiere de la cabecera ({$cpe->obtenerTotalOperacionesExportacion()}).");
        }

        if (abs((float)bcsub($sumaIgv, $cpe->obtenerTotalIgv(), 2)) > 0.05) {
            throw new ValidacionFiscalExcepcion("La sumatoria de IGV de las líneas ({$sumaIgv}) difiere de la cabecera ({$cpe->obtenerTotalIgv()}).");
        }

        if (abs((float)bcsub($sumaTotalVenta, $cpe->obtenerTotalVenta(), 2)) > 0.05) {
            throw new ValidacionFiscalExcepcion("La sumatoria de total venta de las líneas ({$sumaTotalVenta}) difiere de la cabecera ({$cpe->obtenerTotalVenta()}).");
        }
    }
}
