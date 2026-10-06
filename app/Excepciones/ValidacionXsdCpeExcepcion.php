<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use Throwable;

/**
 * Excepción lanzada cuando un comprobante XML falla la validación estructural
 * contra los esquemas oficiales XSD de OASIS UBL 2.1 / SUNAT.
 */
class ValidacionXsdCpeExcepcion extends CpeExcepcion
{
    /** @var array<int, array{linea: int, columna: int, nivel: string, mensaje: string}> */
    private array $erroresEsquema;

    /**
     * @param string $mensaje Mensaje descriptivo del fallo
     * @param array<int, array{linea: int, columna: int, nivel: string, mensaje: string}> $erroresEsquema Lista de errores de libxml
     * @param int $codigo Código técnico de excepción (por defecto 422)
     * @param Throwable|null $anterior Excepción previa encadenada
     */
    public function __construct(
        string $mensaje = 'El comprobante XML no cumple con la estructura o restricciones del esquema XSD oficial',
        array $erroresEsquema = [],
        int $codigo = 422,
        ?Throwable $anterior = null
    ) {
        parent::__construct($mensaje, $codigo, $anterior);
        $this->erroresEsquema = $erroresEsquema;
    }

    /**
     * Retorna el detalle estructurado y sanitizado de los errores de validación XSD.
     *
     * @return array<int, array{linea: int, columna: int, nivel: string, mensaje: string}>
     */
    public function obtenerErroresEsquema(): array
    {
        return $this->erroresEsquema;
    }

    /**
     * Retorna el primer mensaje de error detectado para diagnóstico rápido.
     */
    public function obtenerPrimerError(): string
    {
        if (empty($this->erroresEsquema)) {
            return $this->getMessage();
        }
        $primero = $this->erroresEsquema[0];
        return sprintf('[Línea %d, Col %d] %s', $primero['linea'], $primero['columna'], $primero['mensaje']);
    }
}
