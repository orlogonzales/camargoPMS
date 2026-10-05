<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios\CPE\Generadores;

use CamargoPMS\Excepciones\ValidacionFiscalExcepcion;
use CamargoPMS\Modelos\CPE\CpeComprobante;
use CamargoPMS\Servicios\CPE\Constructores\ConstructorDocumentoUbl;
use CamargoPMS\Servicios\CPE\MapeadorFiscalUbl;
use CamargoPMS\Servicios\CPE\ValidadorFiscalUbl;
use DOMDocument;

/**
 * Base abstracta para los generadores especializados UBL 2.1.
 * Coordina la validación pre-XML, el mapeo fiscal DTO y la construcción del DOM.
 */
abstract class GeneradorUblBase implements GeneradorUblInterfaz
{
    public function __construct(
        protected ?ValidadorFiscalUbl $validador = null,
        protected ?MapeadorFiscalUbl $mapeador = null
    ) {
        $this->validador = $validador ?? new ValidadorFiscalUbl();
        $this->mapeador = $mapeador ?? new MapeadorFiscalUbl();
    }

    public function generarDom(CpeComprobante $comprobante): DOMDocument
    {
        $tipo = strtoupper(trim($comprobante->obtenerTipoComprobante()));
        if (!$this->soportaTipo($tipo)) {
            throw new ValidacionFiscalExcepcion(
                "El generador " . static::class . " no soporta el tipo de comprobante '{$tipo}'."
            );
        }

        // 1. Validación previa del contrato fiscal
        $this->validador->validar($comprobante);

        // 2. Mapeo a Representación Fiscal Inmutable (DTO)
        $dto = $this->mapeador->mapear($comprobante);

        // 3. Construcción del DOMDocument canónico
        $builder = new ConstructorDocumentoUbl();
        return $builder->construir($dto);
    }

    public function generarXml(CpeComprobante $comprobante): string
    {
        $dom = $this->generarDom($comprobante);
        $xml = $dom->saveXML();

        if ($xml === false) {
            throw new ValidacionFiscalExcepcion("Error al serializar el documento DOM a cadena XML.");
        }

        return $xml;
    }
}
