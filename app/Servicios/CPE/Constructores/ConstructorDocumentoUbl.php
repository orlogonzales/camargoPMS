<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios\CPE\Constructores;

use CamargoPMS\Servicios\CPE\ConstantesUbl;
use CamargoPMS\Servicios\CPE\DTO\RepresentacionFiscalCpe;
use DOMDocument;
use DOMElement;

/**
 * Constructor determinista de documentos UBL 2.1 utilizando estrictamente la API DOM nativa de PHP.
 * Aplica inserción top-down para herencia limpia de namespaces y garantiza orden canónico.
 */
class ConstructorDocumentoUbl
{
    private DOMDocument $dom;

    public function __construct()
    {
        $this->dom = new DOMDocument('1.0', 'UTF-8');
        $this->dom->formatOutput = true;
        $this->dom->preserveWhiteSpace = false;
    }

    /**
     * Construye y retorna el DOMDocument canónico correspondiente a la representación fiscal.
     */
    public function construir(RepresentacionFiscalCpe $rep): DOMDocument
    {
        $tagRaiz = match ($rep->tipoComprobante) {
            ConstantesUbl::TIPO_FACTURA, ConstantesUbl::TIPO_BOLETA => 'Invoice',
            ConstantesUbl::TIPO_NOTA_CREDITO => 'CreditNote',
            ConstantesUbl::TIPO_NOTA_DEBITO => 'DebitNote',
        };

        $xmlnsRaiz = match ($rep->tipoComprobante) {
            ConstantesUbl::TIPO_FACTURA, ConstantesUbl::TIPO_BOLETA => ConstantesUbl::XMLNS_INVOICE,
            ConstantesUbl::TIPO_NOTA_CREDITO => ConstantesUbl::XMLNS_CREDIT_NOTE,
            ConstantesUbl::TIPO_NOTA_DEBITO => ConstantesUbl::XMLNS_DEBIT_NOTE,
        };

        $raiz = $this->dom->createElementNS($xmlnsRaiz, $tagRaiz);
        $this->dom->appendChild($raiz);

        // Declaraciones de Namespaces Oficiales UBL 2.1 en la Raíz
        $raiz->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cac', ConstantesUbl::XMLNS_CAC);
        $raiz->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cbc', ConstantesUbl::XMLNS_CBC);
        $raiz->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ccts', ConstantesUbl::XMLNS_CCTS);
        $raiz->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ds', ConstantesUbl::XMLNS_DS);
        $raiz->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ext', ConstantesUbl::XMLNS_EXT);
        $raiz->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:qdt', ConstantesUbl::XMLNS_QDT);
        $raiz->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:udt', ConstantesUbl::XMLNS_UDT);
        $raiz->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', ConstantesUbl::XMLNS_XSI);

        // 1. Contenedor de Extensiones UBL (Frontera de Firma - Vacío en C2)
        $this->construirUblExtensions($raiz);

        // 2. Metadatos de Cabecera
        $this->construirCabecera($raiz, $rep);

        // 3. DiscrepancyResponse y BillingReference (Para NC y ND)
        if ($rep->esNotaCredito() || $rep->esNotaDebito()) {
            $this->construirReferenciasModificadas($raiz, $rep);
        }

        // 4. Datos del Emisor (cac:AccountingSupplierParty)
        $this->construirEmisor($raiz, $rep);

        // 5. Datos del Receptor (cac:AccountingCustomerParty)
        $this->construirReceptor($raiz, $rep);

        // 6. Forma de Pago y Cuotas (cac:PaymentTerms)
        $this->construirPaymentTerms($raiz, $rep);

        // 7. Totales de Impuestos (cac:TaxTotal)
        $this->construirTaxTotal($raiz, $rep);

        // 8. Totales Monetarios (cac:LegalMonetaryTotal / cac:RequestedMonetaryTotal)
        $this->construirMonetaryTotal($raiz, $rep);

        // 9. Líneas del Comprobante
        $this->construirLineas($raiz, $rep);

        return $this->dom;
    }

    private function construirUblExtensions(DOMElement $padre): void
    {
        $exts = $this->dom->createElementNS(ConstantesUbl::XMLNS_EXT, 'ext:UBLExtensions');
        $padre->appendChild($exts);

        $ext = $this->dom->createElementNS(ConstantesUbl::XMLNS_EXT, 'ext:UBLExtension');
        $exts->appendChild($ext);

        $content = $this->dom->createElementNS(ConstantesUbl::XMLNS_EXT, 'ext:ExtensionContent');
        $ext->appendChild($content);
    }

    private function construirCabecera(DOMElement $padre, RepresentacionFiscalCpe $rep): void
    {
        $this->agregarCbc($padre, 'cbc:UBLVersionID', ConstantesUbl::UBL_VERSION_ID);
        $this->agregarCbc($padre, 'cbc:CustomizationID', ConstantesUbl::CUSTOMIZATION_ID);
        $this->agregarCbc($padre, 'cbc:ID', $rep->idDocumento);
        $this->agregarCbc($padre, 'cbc:IssueDate', $rep->fechaEmision);
        $this->agregarCbc($padre, 'cbc:IssueTime', $rep->horaEmision);

        if (($rep->esFactura() || $rep->esBoleta()) && $rep->fechaVencimiento !== null) {
            $this->agregarCbc($padre, 'cbc:DueDate', $rep->fechaVencimiento);
        }

        if ($rep->esFactura() || $rep->esBoleta()) {
            $tipoCode = $this->agregarCbc($padre, 'cbc:InvoiceTypeCode', $rep->tipoComprobante);
            $tipoCode->setAttribute('listAgencyName', 'PE:SUNAT');
            $tipoCode->setAttribute('listName', 'Tipo de Documento');
            $tipoCode->setAttribute('listURI', ConstantesUbl::URI_CATALOGO_01);
            $tipoCode->setAttribute('listID', $rep->esExportacionHospedaje ? '0200' : '0101');
        }

        $curr = $this->agregarCbc($padre, 'cbc:DocumentCurrencyCode', $rep->monedaCodigo);
        $curr->setAttribute('listAgencyName', 'United Nations Economic Commission for Europe');
        $curr->setAttribute('listID', 'ISO 4217 Alpha');
        $curr->setAttribute('listName', 'Currency');
    }

    private function construirReferenciasModificadas(DOMElement $padre, RepresentacionFiscalCpe $rep): void
    {
        if (empty($rep->documentosRelacionados)) {
            return;
        }

        $docPrinc = $rep->documentosRelacionados[0];

        // DiscrepancyResponse
        $disc = $this->dom->createElementNS(ConstantesUbl::XMLNS_CAC, 'cac:DiscrepancyResponse');
        $padre->appendChild($disc);

        $this->agregarCbc($disc, 'cbc:ReferenceID', $docPrinc['numero_completo']);

        $respCode = $this->agregarCbc($disc, 'cbc:ResponseCode', $docPrinc['codigo_motivo']);
        $respCode->setAttribute('listAgencyName', 'PE:SUNAT');
        $respCode->setAttribute(
            'listName',
            $rep->esNotaCredito() ? 'Tipo de nota de credito' : 'Tipo de nota de debito'
        );
        $respCode->setAttribute(
            'listURI',
            $rep->esNotaCredito() ? ConstantesUbl::URI_CATALOGO_09 : ConstantesUbl::URI_CATALOGO_10
        );

        $this->agregarCbc($disc, 'cbc:Description', $docPrinc['descripcion_motivo']);

        // BillingReference
        foreach ($rep->documentosRelacionados as $doc) {
            $billRef = $this->dom->createElementNS(ConstantesUbl::XMLNS_CAC, 'cac:BillingReference');
            $padre->appendChild($billRef);

            $invRef = $this->dom->createElementNS(ConstantesUbl::XMLNS_CAC, 'cac:InvoiceDocumentReference');
            $billRef->appendChild($invRef);

            $this->agregarCbc($invRef, 'cbc:ID', $doc['numero_completo']);

            $docType = $this->agregarCbc($invRef, 'cbc:DocumentTypeCode', $doc['tipo_documento']);
            $docType->setAttribute('listAgencyName', 'PE:SUNAT');
            $docType->setAttribute('listName', 'Tipo de Documento');
            $docType->setAttribute('listURI', ConstantesUbl::URI_CATALOGO_01);
        }
    }

    private function construirEmisor(DOMElement $padre, RepresentacionFiscalCpe $rep): void
    {
        $supplier = $this->dom->createElementNS(ConstantesUbl::XMLNS_CAC, 'cac:AccountingSupplierParty');
        $padre->appendChild($supplier);

        $party = $this->dom->createElementNS(ConstantesUbl::XMLNS_CAC, 'cac:Party');
        $supplier->appendChild($party);

        // PartyIdentification
        $partyId = $this->dom->createElementNS(ConstantesUbl::XMLNS_CAC, 'cac:PartyIdentification');
        $party->appendChild($partyId);

        $id = $this->agregarCbc($partyId, 'cbc:ID', $rep->emisorRuc);
        $id->setAttribute('schemeAgencyName', 'PE:SUNAT');
        $id->setAttribute('schemeID', ConstantesUbl::DOC_RUC);
        $id->setAttribute('schemeName', 'Documento de Identidad');
        $id->setAttribute('schemeURI', ConstantesUbl::URI_CATALOGO_06);

        // PartyName (Nombre Comercial si existe)
        if ($rep->emisorNombreComercial !== null) {
            $partyName = $this->dom->createElementNS(ConstantesUbl::XMLNS_CAC, 'cac:PartyName');
            $party->appendChild($partyName);
            $this->agregarCbc($partyName, 'cbc:Name', $rep->emisorNombreComercial);
        }

        // PartyLegalEntity
        $legal = $this->dom->createElementNS(ConstantesUbl::XMLNS_CAC, 'cac:PartyLegalEntity');
        $party->appendChild($legal);

        $this->agregarCbc($legal, 'cbc:RegistrationName', $rep->emisorRazonSocial);

        $address = $this->dom->createElementNS(ConstantesUbl::XMLNS_CAC, 'cac:RegistrationAddress');
        $legal->appendChild($address);

        if ($rep->emisorUbigeo !== null) {
            $this->agregarCbc($address, 'cbc:ID', $rep->emisorUbigeo);
        }

        $addrCode = $this->agregarCbc($address, 'cbc:AddressTypeCode', $rep->emisorCodigoEstablecimiento);
        $addrCode->setAttribute('listAgencyName', 'PE:SUNAT');
        $addrCode->setAttribute('listName', 'Establecimientos anexos');

        if ($rep->emisorDireccionFiscal !== null) {
            $line = $this->dom->createElementNS(ConstantesUbl::XMLNS_CAC, 'cac:AddressLine');
            $address->appendChild($line);
            $this->agregarCbc($line, 'cbc:Line', $rep->emisorDireccionFiscal);
        }

        $country = $this->dom->createElementNS(ConstantesUbl::XMLNS_CAC, 'cac:Country');
        $address->appendChild($country);

        $countryCode = $this->agregarCbc($country, 'cbc:IdentificationCode', $rep->emisorPaisCodigo);
        $countryCode->setAttribute('listAgencyName', 'United Nations Economic Commission for Europe');
        $countryCode->setAttribute('listID', 'ISO 3166-1');
        $countryCode->setAttribute('listName', 'Country');
    }

    private function construirReceptor(DOMElement $padre, RepresentacionFiscalCpe $rep): void
    {
        $customer = $this->dom->createElementNS(ConstantesUbl::XMLNS_CAC, 'cac:AccountingCustomerParty');
        $padre->appendChild($customer);

        $party = $this->dom->createElementNS(ConstantesUbl::XMLNS_CAC, 'cac:Party');
        $customer->appendChild($party);

        // PartyIdentification
        $partyId = $this->dom->createElementNS(ConstantesUbl::XMLNS_CAC, 'cac:PartyIdentification');
        $party->appendChild($partyId);

        $id = $this->agregarCbc($partyId, 'cbc:ID', $rep->receptorNumeroDocumento);
        $id->setAttribute('schemeAgencyName', 'PE:SUNAT');
        $id->setAttribute('schemeID', $rep->receptorTipoDocumento);
        $id->setAttribute('schemeName', 'Documento de Identidad');
        $id->setAttribute('schemeURI', ConstantesUbl::URI_CATALOGO_06);

        // PartyLegalEntity
        $legal = $this->dom->createElementNS(ConstantesUbl::XMLNS_CAC, 'cac:PartyLegalEntity');
        $party->appendChild($legal);

        $this->agregarCbc($legal, 'cbc:RegistrationName', $rep->receptorRazonSocial);

        if ($rep->receptorDireccionFiscal !== null) {
            $address = $this->dom->createElementNS(ConstantesUbl::XMLNS_CAC, 'cac:RegistrationAddress');
            $legal->appendChild($address);

            if ($rep->receptorUbigeo !== null) {
                $this->agregarCbc($address, 'cbc:ID', $rep->receptorUbigeo);
            }

            $line = $this->dom->createElementNS(ConstantesUbl::XMLNS_CAC, 'cac:AddressLine');
            $address->appendChild($line);
            $this->agregarCbc($line, 'cbc:Line', $rep->receptorDireccionFiscal);

            $country = $this->dom->createElementNS(ConstantesUbl::XMLNS_CAC, 'cac:Country');
            $address->appendChild($country);

            $countryCode = $this->agregarCbc($country, 'cbc:IdentificationCode', $rep->receptorPaisCodigo);
            $countryCode->setAttribute('listAgencyName', 'United Nations Economic Commission for Europe');
            $countryCode->setAttribute('listID', 'ISO 3166-1');
            $countryCode->setAttribute('listName', 'Country');
        }
    }

    private function construirPaymentTerms(DOMElement $padre, RepresentacionFiscalCpe $rep): void
    {
        // En Notas de Crédito, PaymentTerms solo aplica si es motivo 13 a crédito
        if ($rep->esNotaCredito()) {
            $esMotivo13 = false;
            foreach ($rep->documentosRelacionados as $doc) {
                if ($doc['codigo_motivo'] === '13') {
                    $esMotivo13 = true;
                    break;
                }
            }
            if (!$esMotivo13 || !$rep->esCredito()) {
                return;
            }
        }

        // En Notas de Débito, PaymentTerms no se emite
        if ($rep->esNotaDebito()) {
            return;
        }

        if ($rep->esContado()) {
            $terms = $this->dom->createElementNS(ConstantesUbl::XMLNS_CAC, 'cac:PaymentTerms');
            $padre->appendChild($terms);

            $this->agregarCbc($terms, 'cbc:ID', 'FormaPago');
            $this->agregarCbc($terms, 'cbc:PaymentMeansID', 'Contado');
            return;
        }

        if ($rep->esCredito()) {
            // Cabecera Crédito
            $terms = $this->dom->createElementNS(ConstantesUbl::XMLNS_CAC, 'cac:PaymentTerms');
            $padre->appendChild($terms);

            $this->agregarCbc($terms, 'cbc:ID', 'FormaPago');
            $this->agregarCbc($terms, 'cbc:PaymentMeansID', 'Credito');
            $amount = $this->agregarCbc($terms, 'cbc:Amount', $rep->montoNetoPendiente ?? '0.00');
            $amount->setAttribute('currencyID', $rep->monedaCodigo);

            // Cuotas ordenadas
            foreach ($rep->cuotas as $cuota) {
                $cTerms = $this->dom->createElementNS(ConstantesUbl::XMLNS_CAC, 'cac:PaymentTerms');
                $padre->appendChild($cTerms);

                $this->agregarCbc($cTerms, 'cbc:ID', 'FormaPago');
                $this->agregarCbc($cTerms, 'cbc:PaymentMeansID', $cuota['identificador']);
                $cAmount = $this->agregarCbc($cTerms, 'cbc:Amount', $cuota['monto']);
                $cAmount->setAttribute('currencyID', $rep->monedaCodigo);
                $this->agregarCbc($cTerms, 'cbc:PaymentDueDate', $cuota['fecha_vencimiento']);
            }
        }
    }

    private function construirTaxTotal(DOMElement $padre, RepresentacionFiscalCpe $rep): void
    {
        $taxTotal = $this->dom->createElementNS(ConstantesUbl::XMLNS_CAC, 'cac:TaxTotal');
        $padre->appendChild($taxTotal);

        $taxAmount = $this->agregarCbc($taxTotal, 'cbc:TaxAmount', $rep->totalIgv);
        $taxAmount->setAttribute('currencyID', $rep->monedaCodigo);

        // Subtotales por cada concepto
        if (bccomp($rep->totalOperacionesGravadas, '0.00', 2) > 0 || (bccomp($rep->totalOperacionesExportacion, '0.00', 2) === 0 && bccomp($rep->totalOperacionesExoneradas, '0.00', 2) === 0)) {
            $this->agregarTaxSubtotal(
                $taxTotal,
                $rep->monedaCodigo,
                $rep->totalOperacionesGravadas,
                $rep->totalIgv,
                '18.00',
                ConstantesUbl::TRIBUTO_IGV
            );
        }

        if (bccomp($rep->totalOperacionesExportacion, '0.00', 2) > 0) {
            $this->agregarTaxSubtotal(
                $taxTotal,
                $rep->monedaCodigo,
                $rep->totalOperacionesExportacion,
                '0.00',
                '0.00',
                ConstantesUbl::TRIBUTO_EXPORTACION
            );
        }

        if (bccomp($rep->totalOperacionesExoneradas, '0.00', 2) > 0) {
            $this->agregarTaxSubtotal(
                $taxTotal,
                $rep->monedaCodigo,
                $rep->totalOperacionesExoneradas,
                '0.00',
                '0.00',
                ConstantesUbl::TRIBUTO_EXONERADO
            );
        }

        if (bccomp($rep->totalOperacionesInafectas, '0.00', 2) > 0) {
            $this->agregarTaxSubtotal(
                $taxTotal,
                $rep->monedaCodigo,
                $rep->totalOperacionesInafectas,
                '0.00',
                '0.00',
                ConstantesUbl::TRIBUTO_INAFECTO
            );
        }
    }

    private function agregarTaxSubtotal(
        DOMElement $taxTotal,
        string $moneda,
        string $taxableAmount,
        string $taxAmountVal,
        string $percent,
        string $codTributo
    ): void {
        $detalle = ConstantesUbl::DETALLE_TRIBUTOS[$codTributo];

        $sub = $this->dom->createElementNS(ConstantesUbl::XMLNS_CAC, 'cac:TaxSubtotal');
        $taxTotal->appendChild($sub);

        $taxable = $this->agregarCbc($sub, 'cbc:TaxableAmount', $taxableAmount);
        $taxable->setAttribute('currencyID', $moneda);

        $amount = $this->agregarCbc($sub, 'cbc:TaxAmount', $taxAmountVal);
        $amount->setAttribute('currencyID', $moneda);

        $cat = $this->dom->createElementNS(ConstantesUbl::XMLNS_CAC, 'cac:TaxCategory');
        $sub->appendChild($cat);

        $this->agregarCbc($cat, 'cbc:ID', $detalle['categoria']);
        if (bccomp($percent, '0.00', 2) > 0) {
            $this->agregarCbc($cat, 'cbc:Percent', $percent);
        }

        $scheme = $this->dom->createElementNS(ConstantesUbl::XMLNS_CAC, 'cac:TaxScheme');
        $cat->appendChild($scheme);

        $sId = $this->agregarCbc($scheme, 'cbc:ID', $codTributo);
        $sId->setAttribute('schemeAgencyName', 'PE:SUNAT');
        $sId->setAttribute('schemeID', 'UN/ECE 5153');
        $sId->setAttribute('schemeName', 'Codigo de tributos');

        $this->agregarCbc($scheme, 'cbc:Name', $detalle['nombre']);
        $this->agregarCbc($scheme, 'cbc:TaxTypeCode', $detalle['tipo']);
    }

    private function construirMonetaryTotal(DOMElement $padre, RepresentacionFiscalCpe $rep): void
    {
        $tagTotal = $rep->esNotaDebito() ? 'cac:RequestedMonetaryTotal' : 'cac:LegalMonetaryTotal';
        $legal = $this->dom->createElementNS(ConstantesUbl::XMLNS_CAC, $tagTotal);
        $padre->appendChild($legal);

        $lineExt = $this->agregarCbc($legal, 'cbc:LineExtensionAmount', bcadd($rep->totalOperacionesGravadas, $rep->totalOperacionesExportacion, 2));
        $lineExt->setAttribute('currencyID', $rep->monedaCodigo);

        $taxInc = $this->agregarCbc($legal, 'cbc:TaxInclusiveAmount', $rep->totalVenta);
        $taxInc->setAttribute('currencyID', $rep->monedaCodigo);

        $payable = $this->agregarCbc($legal, 'cbc:PayableAmount', $rep->totalVenta);
        $payable->setAttribute('currencyID', $rep->monedaCodigo);
    }

    private function construirLineas(DOMElement $padre, RepresentacionFiscalCpe $rep): void
    {
        $tagLinea = match ($rep->tipoComprobante) {
            ConstantesUbl::TIPO_FACTURA, ConstantesUbl::TIPO_BOLETA => 'cac:InvoiceLine',
            ConstantesUbl::TIPO_NOTA_CREDITO => 'cac:CreditNoteLine',
            ConstantesUbl::TIPO_NOTA_DEBITO => 'cac:DebitNoteLine',
        };

        $tagCantidad = match ($rep->tipoComprobante) {
            ConstantesUbl::TIPO_FACTURA, ConstantesUbl::TIPO_BOLETA => 'cbc:InvoicedQuantity',
            ConstantesUbl::TIPO_NOTA_CREDITO => 'cbc:CreditedQuantity',
            ConstantesUbl::TIPO_NOTA_DEBITO => 'cbc:DebitedQuantity',
        };

        foreach ($rep->lineas as $l) {
            $linea = $this->dom->createElementNS(ConstantesUbl::XMLNS_CAC, $tagLinea);
            $padre->appendChild($linea);

            $this->agregarCbc($linea, 'cbc:ID', (string)$l['numero_linea']);

            $qty = $this->agregarCbc($linea, $tagCantidad, $l['cantidad']);
            $qty->setAttribute('unitCode', $l['unidad_medida']);

            $extAmount = $this->agregarCbc($linea, 'cbc:LineExtensionAmount', $l['base_imponible']);
            $extAmount->setAttribute('currencyID', $rep->monedaCodigo);

            // PricingReference (solo para Factura y Boleta)
            if ($rep->esFactura() || $rep->esBoleta()) {
                $pricing = $this->dom->createElementNS(ConstantesUbl::XMLNS_CAC, 'cac:PricingReference');
                $linea->appendChild($pricing);

                $altPrice = $this->dom->createElementNS(ConstantesUbl::XMLNS_CAC, 'cac:AlternativeConditionPrice');
                $pricing->appendChild($altPrice);

                $pAmount = $this->agregarCbc($altPrice, 'cbc:PriceAmount', $l['precio_unitario']);
                $pAmount->setAttribute('currencyID', $rep->monedaCodigo);

                $pType = $this->agregarCbc($altPrice, 'cbc:PriceTypeCode', '01');
                $pType->setAttribute('listAgencyName', 'PE:SUNAT');
                $pType->setAttribute('listName', 'Tipo de Precio');
                $pType->setAttribute('listURI', ConstantesUbl::URI_CATALOGO_16);
            }

            // TaxTotal de Línea
            $taxLine = $this->dom->createElementNS(ConstantesUbl::XMLNS_CAC, 'cac:TaxTotal');
            $linea->appendChild($taxLine);

            $tAmount = $this->agregarCbc($taxLine, 'cbc:TaxAmount', $l['monto_igv']);
            $tAmount->setAttribute('currencyID', $rep->monedaCodigo);

            $sub = $this->dom->createElementNS(ConstantesUbl::XMLNS_CAC, 'cac:TaxSubtotal');
            $taxLine->appendChild($sub);

            $taxable = $this->agregarCbc($sub, 'cbc:TaxableAmount', $l['base_imponible']);
            $taxable->setAttribute('currencyID', $rep->monedaCodigo);

            $sAmount = $this->agregarCbc($sub, 'cbc:TaxAmount', $l['monto_igv']);
            $sAmount->setAttribute('currencyID', $rep->monedaCodigo);

            $cat = $this->dom->createElementNS(ConstantesUbl::XMLNS_CAC, 'cac:TaxCategory');
            $sub->appendChild($cat);

            $this->agregarCbc($cat, 'cbc:Percent', $l['tasa_igv']);

            $exemption = $this->agregarCbc($cat, 'cbc:TaxExemptionReasonCode', $l['tipo_afectacion_igv']);
            $exemption->setAttribute('listAgencyName', 'PE:SUNAT');
            $exemption->setAttribute('listName', 'Afectacion del IGV');
            $exemption->setAttribute('listURI', ConstantesUbl::URI_CATALOGO_07);

            $scheme = $this->dom->createElementNS(ConstantesUbl::XMLNS_CAC, 'cac:TaxScheme');
            $cat->appendChild($scheme);

            $sId = $this->agregarCbc($scheme, 'cbc:ID', $l['codigo_tributo']);
            $sId->setAttribute('schemeAgencyName', 'PE:SUNAT');
            $sId->setAttribute('schemeID', 'UN/ECE 5153');
            $sId->setAttribute('schemeName', 'Codigo de tributos');

            $this->agregarCbc($scheme, 'cbc:Name', $l['nombre_tributo']);
            $this->agregarCbc($scheme, 'cbc:TaxTypeCode', $l['tipo_tributo_internacional']);

            // Item
            $item = $this->dom->createElementNS(ConstantesUbl::XMLNS_CAC, 'cac:Item');
            $linea->appendChild($item);

            $this->agregarCbc($item, 'cbc:Description', $l['descripcion']);

            if ($l['codigo_producto_interno'] !== null) {
                $sellerId = $this->dom->createElementNS(ConstantesUbl::XMLNS_CAC, 'cac:SellersItemIdentification');
                $item->appendChild($sellerId);
                $this->agregarCbc($sellerId, 'cbc:ID', $l['codigo_producto_interno']);
            }

            // Propiedades del Catálogo 55 (Beneficio de Hospedaje D.L. 919)
            foreach ($l['propiedades_catalogo_55'] as $prop) {
                $addProp = $this->dom->createElementNS(ConstantesUbl::XMLNS_CAC, 'cac:AdditionalItemProperty');
                $item->appendChild($addProp);

                $this->agregarCbc($addProp, 'cbc:Name', $prop['nombre']);

                $nameCode = $this->agregarCbc($addProp, 'cbc:NameCode', $prop['codigo']);
                $nameCode->setAttribute('listAgencyName', 'PE:SUNAT');
                $nameCode->setAttribute('listName', 'Propiedad del item');
                $nameCode->setAttribute('listURI', ConstantesUbl::URI_CATALOGO_55);

                $this->agregarCbc($addProp, 'cbc:Value', $prop['valor']);
            }

            // Price
            $price = $this->dom->createElementNS(ConstantesUbl::XMLNS_CAC, 'cac:Price');
            $linea->appendChild($price);

            $prAmount = $this->agregarCbc($price, 'cbc:PriceAmount', $l['valor_unitario']);
            $prAmount->setAttribute('currencyID', $rep->monedaCodigo);
        }
    }

    private function agregarCbc(DOMElement $padre, string $tag, string $valor): DOMElement
    {
        $el = $this->dom->createElementNS(ConstantesUbl::XMLNS_CBC, $tag);
        $nodoTexto = $this->dom->createTextNode($valor);
        $el->appendChild($nodoTexto);
        $padre->appendChild($el);

        return $el;
    }
}
