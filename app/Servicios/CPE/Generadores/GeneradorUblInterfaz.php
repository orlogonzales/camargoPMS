<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios\CPE\Generadores;

use CamargoPMS\Modelos\CPE\CpeComprobante;
use DOMDocument;

/**
 * Contrato formal para los generadores especializados de documentos XML UBL 2.1.
 */
interface GeneradorUblInterfaz
{
    /**
     * Determina si el generador soporta el tipo de comprobante dado.
     */
    public function soportaTipo(string $tipoComprobante): bool;

    /**
     * Transforma un comprobante fiscal en su representación canónica DOMDocument UBL 2.1.
     */
    public function generarDom(CpeComprobante $comprobante): DOMDocument;

    /**
     * Genera la cadena XML canónica sin firmar formateada en UTF-8.
     */
    public function generarXml(CpeComprobante $comprobante): string;
}
