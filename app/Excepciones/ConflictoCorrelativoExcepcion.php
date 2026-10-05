<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

/**
 * Excepción lanzada cuando ocurre un conflicto irresoluble en la asignación o unicidad del correlativo fiscal.
 */
class ConflictoCorrelativoExcepcion extends CpeExcepcion
{
    protected $code = 409;
}
