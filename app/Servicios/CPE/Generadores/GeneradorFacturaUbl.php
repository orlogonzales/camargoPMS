<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios\CPE\Generadores;

/**
 * Generador especializado para Facturas Electrónicas (Tipo 01) en UBL 2.1.
 */
class GeneradorFacturaUbl extends GeneradorUblBase
{
    public function soportaTipo(string $tipoComprobante): bool
    {
        return in_array(strtoupper(trim($tipoComprobante)), ['FACTURA', '01'], true);
    }
}
