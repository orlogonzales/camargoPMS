<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios\CPE\Validacion;

use CamargoPMS\Excepciones\EsquemaCpeNoDisponibleExcepcion;
use CamargoPMS\Excepciones\IntegridadEsquemaCpeExcepcion;
use CamargoPMS\Excepciones\TipoDocumentoNoSoportadoExcepcion;

/**
 * Contrato para el aprovisionamiento y resolución de rutas de esquemas XSD locales confiables.
 */
interface ProveedorEsquemasCpeInterfaz
{
    /**
     * Retorna la ruta absoluta al archivo XSD raíz verificado para el tipo de comprobante dado.
     *
     * @param string $tipoComprobante Código según Catálogo 01 ('01', '03', '07', '08')
     * @return string Ruta absoluta del archivo XSD raíz en disco
     *
     * @throws TipoDocumentoNoSoportadoExcepcion Si el tipo de comprobante no es reconocido
     * @throws EsquemaCpeNoDisponibleExcepcion Si los esquemas requeridos no están aprovisionados
     * @throws IntegridadEsquemaCpeExcepcion Si algún esquema viola el hash SHA-256 o intenta path traversal
     */
    public function obtenerRutaEsquema(string $tipoComprobante): string;

    /**
     * Retorna la versión normativa de los esquemas que administra este proveedor.
     */
    public function obtenerVersionNormativa(): string;

    /**
     * Valida la integridad del árbol de esquemas contra el manifiesto inmutable.
     *
     * @return bool True si el 100% de los esquemas requeridos existen y coinciden en tamaño y hash SHA-256
     * @throws IntegridadEsquemaCpeExcepcion Si algún esquema está corrupto o adulterado
     * @throws EsquemaCpeNoDisponibleExcepcion Si faltan archivos del conjunto requerido
     */
    public function verificarIntegridad(): bool;
}
