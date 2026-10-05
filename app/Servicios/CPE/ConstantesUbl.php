<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios\CPE;

/**
 * Constantes oficiales y mapeos de catálogos SUNAT para la especificación UBL 2.1.
 */
final class ConstantesUbl
{
    // --- Namespaces XML Oficiales UBL 2.1 y SUNAT ---
    public const string XMLNS_INVOICE = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';
    public const string XMLNS_CREDIT_NOTE = 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2';
    public const string XMLNS_DEBIT_NOTE = 'urn:oasis:names:specification:ubl:schema:xsd:DebitNote-2';
    public const string XMLNS_CAC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
    public const string XMLNS_CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';
    public const string XMLNS_CCTS = 'urn:un:unece:uncefact:documentation:2';
    public const string XMLNS_DS = 'http://www.w3.org/2000/09/xmldsig#';
    public const string XMLNS_EXT = 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2';
    public const string XMLNS_QDT = 'urn:oasis:names:specification:ubl:schema:xsd:QualifiedDatatypes-2';
    public const string XMLNS_UDT = 'urn:un:unece:uncefact:data:specification:UnqualifiedDataTypesSchemaModule:2';
    public const string XMLNS_XSI = 'http://www.w3.org/2001/XMLSchema-instance';

    // --- Versiones de Especificación UBL / SUNAT ---
    public const string UBL_VERSION_ID = '2.1';
    public const string CUSTOMIZATION_ID = '2.0';

    // --- Catálogo 01: Códigos de Tipo de Comprobante ---
    public const string TIPO_FACTURA = '01';
    public const string TIPO_BOLETA = '03';
    public const string TIPO_NOTA_CREDITO = '07';
    public const string TIPO_NOTA_DEBITO = '08';

    public const array MAPA_TIPO_COMPROBANTE = [
        'FACTURA' => self::TIPO_FACTURA,
        'BOLETA' => self::TIPO_BOLETA,
        'NOTA_CREDITO' => self::TIPO_NOTA_CREDITO,
        'NOTA_DEBITO' => self::TIPO_NOTA_DEBITO,
        '01' => self::TIPO_FACTURA,
        '03' => self::TIPO_BOLETA,
        '07' => self::TIPO_NOTA_CREDITO,
        '08' => self::TIPO_NOTA_DEBITO,
    ];

    // --- Catálogo 06: Tipos de Documento de Identidad ---
    public const string DOC_SIN_RUC = '0';
    public const string DOC_DNI = '1';
    public const string DOC_CARNET_EXTRANJERIA = '4';
    public const string DOC_RUC = '6';
    public const string DOC_PASAPORTE = '7';

    public const array MAPA_TIPO_DOCUMENTO_IDENTIDAD = [
        'RUC' => self::DOC_RUC,
        '6' => self::DOC_RUC,
        'DNI' => self::DOC_DNI,
        '1' => self::DOC_DNI,
        'CARNET_EXTRANJERIA' => self::DOC_CARNET_EXTRANJERIA,
        'CE' => self::DOC_CARNET_EXTRANJERIA,
        '4' => self::DOC_CARNET_EXTRANJERIA,
        'PASAPORTE' => self::DOC_PASAPORTE,
        'PAS' => self::DOC_PASAPORTE,
        '7' => self::DOC_PASAPORTE,
        'SIN_DOCUMENTO' => self::DOC_SIN_RUC,
        '0' => self::DOC_SIN_RUC,
        '-' => self::DOC_SIN_RUC,
    ];

    // --- Catálogo 05: Códigos de Tipos de Tributos ---
    public const string TRIBUTO_IGV = '1000';
    public const string TRIBUTO_EXPORTACION = '9995';
    public const string TRIBUTO_GRATUITO = '9996';
    public const string TRIBUTO_EXONERADO = '9997';
    public const string TRIBUTO_INAFECTO = '9998';

    public const array DETALLE_TRIBUTOS = [
        self::TRIBUTO_IGV => [
            'nombre' => 'IGV',
            'tipo' => 'VAT',
            'categoria' => 'S',
        ],
        self::TRIBUTO_EXPORTACION => [
            'nombre' => 'EXP',
            'tipo' => 'FRE',
            'categoria' => 'E',
        ],
        self::TRIBUTO_GRATUITO => [
            'nombre' => 'GRA',
            'tipo' => 'FRE',
            'categoria' => 'Z',
        ],
        self::TRIBUTO_EXONERADO => [
            'nombre' => 'EXO',
            'tipo' => 'VAT',
            'categoria' => 'E',
        ],
        self::TRIBUTO_INAFECTO => [
            'nombre' => 'INA',
            'tipo' => 'FRE',
            'categoria' => 'O',
        ],
    ];

    // --- Catálogo 07: Afectación al IGV -> Tributo Asociado ---
    public const array MAPA_AFECTACION_A_TRIBUTO = [
        '10' => self::TRIBUTO_IGV,           // Gravado - Operación Onerosa
        '11' => self::TRIBUTO_GRATUITO,      // Gravado - Retiro por premio
        '12' => self::TRIBUTO_GRATUITO,      // Gravado - Retiro por donación
        '20' => self::TRIBUTO_EXONERADO,     // Exonerado - Operación Onerosa
        '30' => self::TRIBUTO_INAFECTO,      // Inafecto - Operación Onerosa
        '40' => self::TRIBUTO_EXPORTACION,   // Exportación de bienes o servicios
    ];

    // --- Catálogo 03: Unidades de Medida Permitidas ---
    public const string UNIDAD_SERVICIO = 'ZZ';
    public const string UNIDAD_BIEN = 'NIU';

    public const array UNIDADES_VALIDAS = [
        'ZZ',
        'NIU',
        'KGM',
        'LTR',
        'BX',
        'SET',
    ];

    // --- Catálogo 55: Propiedades del Ítem (Beneficio Hospedajes - D.L. 919) ---
    public const string PROP_55_PAIS_EMISION_PASAPORTE = '4000';
    public const string PROP_55_PAIS_RESIDENCIA = '4001';
    public const string PROP_55_FECHA_INGRESO_PAIS = '4002';
    public const string PROP_55_FECHA_CHECKIN = '4003';
    public const string PROP_55_FECHA_CHECKOUT = '4004';
    public const string PROP_55_DIAS_PERMANENCIA = '4005';
    public const string PROP_55_FECHA_CONSUMO = '4006';
    public const string PROP_55_NOMBRES_HUESPED = '4007';
    public const string PROP_55_TIPO_DOC_HUESPED = '4008';
    public const string PROP_55_NUM_DOC_HUESPED = '4009';

    public const array NOMBRES_CATALOGO_55 = [
        self::PROP_55_PAIS_EMISION_PASAPORTE => 'Codigo de pais de emision del pasaporte',
        self::PROP_55_PAIS_RESIDENCIA => 'Codigo de pais de residencia habitual del sujeto no domiciliado',
        self::PROP_55_FECHA_INGRESO_PAIS => 'Fecha de ingreso al pais',
        self::PROP_55_FECHA_CHECKIN => 'Fecha de ingreso al establecimiento',
        self::PROP_55_FECHA_CHECKOUT => 'Fecha de salida del establecimiento',
        self::PROP_55_DIAS_PERMANENCIA => 'Dias acumulados de permanencia en el pais',
        self::PROP_55_FECHA_CONSUMO => 'Fecha de consumo',
        self::PROP_55_NOMBRES_HUESPED => 'Nombres y apellidos del huesped',
        self::PROP_55_TIPO_DOC_HUESPED => 'Tipo documento identidad huesped',
        self::PROP_55_NUM_DOC_HUESPED => 'Numero documento identidad huesped',
    ];

    // --- URIs de Catálogos Oficiales SUNAT ---
    public const string URI_CATALOGO_01 = 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo01';
    public const string URI_CATALOGO_06 = 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06';
    public const string URI_CATALOGO_07 = 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo07';
    public const string URI_CATALOGO_09 = 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo09';
    public const string URI_CATALOGO_10 = 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo10';
    public const string URI_CATALOGO_16 = 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo16';
    public const string URI_CATALOGO_51 = 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo51';
    public const string URI_CATALOGO_55 = 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo55';

    private function __construct()
    {
    }
}
