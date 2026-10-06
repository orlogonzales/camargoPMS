<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use Throwable;

/**
 * Excepción lanzada cuando un archivo de esquema XSD viola las restricciones de integridad
 * criptográfica (SHA-256 no coincide con el manifiesto) o intenta un escape de directorio.
 */
class IntegridadEsquemaCpeExcepcion extends CpeExcepcion
{
    private string $rutaEsquema;
    private string $hashEsperado;
    private string $hashCalculado;

    /**
     * @param string $mensaje Mensaje descriptivo
     * @param string $rutaEsquema Ruta del archivo de esquema evaluado
     * @param string $hashEsperado Hash SHA-256 declarado en el manifiesto oficial
     * @param string $hashCalculado Hash SHA-256 obtenido del archivo en disco
     * @param int $codigo Código técnico de excepción
     * @param Throwable|null $anterior Excepción previa encadenada
     */
    public function __construct(
        string $mensaje = 'El archivo de esquema XSD no coincide con la huella criptográfica de integridad oficial',
        string $rutaEsquema = '',
        string $hashEsperado = '',
        string $hashCalculado = '',
        int $codigo = 500,
        ?Throwable $anterior = null
    ) {
        parent::__construct($mensaje, $codigo, $anterior);
        $this->rutaEsquema = $rutaEsquema;
        $this->hashEsperado = $hashEsperado;
        $this->hashCalculado = $hashCalculado;
    }

    public function obtenerRutaEsquema(): string
    {
        return $this->rutaEsquema;
    }

    public function obtenerHashEsperado(): string
    {
        return $this->hashEsperado;
    }

    public function obtenerHashCalculado(): string
    {
        return $this->hashCalculado;
    }
}
