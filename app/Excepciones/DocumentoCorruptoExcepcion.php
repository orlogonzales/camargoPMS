<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use RuntimeException;

/**
 * Excepción lanzada cuando un archivo PDF emitido ha sido alterado, corrompido o no existe en disco (D-079 #7, #9).
 */
class DocumentoCorruptoExcepcion extends RuntimeException
{
    private string $codigoFolio;
    private string $tipoIncidencia;

    public function __construct(string $codigoFolio, string $tipoIncidencia, string $mensaje = '')
    {
        $this->codigoFolio = $codigoFolio;
        $this->tipoIncidencia = $tipoIncidencia;

        $msg = $mensaje !== ''
            ? $mensaje
            : "Inconsistencia de integridad en el documento [{$codigoFolio}]: {$tipoIncidencia}. Se ha registrado una incidencia documental.";

        parent::__construct($msg, 500);
    }

    public function obtenerCodigoFolio(): string
    {
        return $this->codigoFolio;
    }

    public function obtenerTipoIncidencia(): string
    {
        return $this->tipoIncidencia;
    }
}
