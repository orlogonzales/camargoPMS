<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios\CPE\Generadores;

/**
 * Generador especializado para Notas de Débito Electrónicas (Tipo 08) en UBL 2.1.
 */
class GeneradorNotaDebitoUbl extends GeneradorUblBase
{
    public function soportaTipo(string $tipoComprobante): bool
    {
        return in_array(strtoupper(trim($tipoComprobante)), ['NOTA_DEBITO', '08'], true);
    }
}
