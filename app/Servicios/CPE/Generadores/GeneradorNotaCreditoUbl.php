<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios\CPE\Generadores;

/**
 * Generador especializado para Notas de Crédito Electrónicas (Tipo 07) en UBL 2.1.
 */
class GeneradorNotaCreditoUbl extends GeneradorUblBase
{
    public function soportaTipo(string $tipoComprobante): bool
    {
        return in_array(strtoupper(trim($tipoComprobante)), ['NOTA_CREDITO', '07'], true);
    }
}
