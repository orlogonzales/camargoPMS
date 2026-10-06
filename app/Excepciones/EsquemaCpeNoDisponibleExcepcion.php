<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use Throwable;

/**
 * Excepción lanzada cuando los esquemas XSD requeridos para validar un CPE
 * no se encuentran aprovisionados o no están disponibles en el almacenamiento local.
 */
class EsquemaCpeNoDisponibleExcepcion extends CpeExcepcion
{
    private string $esquemaRequerido;

    /**
     * @param string $mensaje Mensaje descriptivo
     * @param string $esquemaRequerido Ruta o identificador del esquema faltante
     * @param int $codigo Código técnico de excepción
     * @param Throwable|null $anterior Excepción previa encadenada
     */
    public function __construct(
        string $mensaje = 'Los esquemas XSD requeridos no están aprovisionados en el servidor',
        string $esquemaRequerido = '',
        int $codigo = 500,
        ?Throwable $anterior = null
    ) {
        parent::__construct($mensaje, $codigo, $anterior);
        $this->esquemaRequerido = $esquemaRequerido;
    }

    /**
     * Retorna el identificador o ruta del esquema no disponible.
     */
    public function obtenerEsquemaRequerido(): string
    {
        return $this->esquemaRequerido;
    }
}
