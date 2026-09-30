<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Catálogo canónico de ámbitos de granularidad para tarifas de alojamiento.
 *
 * Precedencia jerárquica de resolución obligatoria:
 * UNIDAD (1) > TIPO_UNIDAD (2) > PROPIEDAD (3)
 */
final class AmbitoTarifaAlojamiento
{
    public const UNIDAD = 'UNIDAD';
    public const TIPO_UNIDAD = 'TIPO_UNIDAD';
    public const PROPIEDAD = 'PROPIEDAD';

    public const TODOS = [
        self::UNIDAD,
        self::TIPO_UNIDAD,
        self::PROPIEDAD,
    ];

    public static function esValido(string $ambito): bool
    {
        return in_array(strtoupper(trim($ambito)), self::TODOS, true);
    }
}
