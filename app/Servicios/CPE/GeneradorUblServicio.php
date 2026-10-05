<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios\CPE;

use CamargoPMS\Excepciones\ValidacionFiscalExcepcion;
use CamargoPMS\Modelos\CPE\CpeComprobante;
use CamargoPMS\Servicios\CPE\Generadores\GeneradorBoletaUbl;
use CamargoPMS\Servicios\CPE\Generadores\GeneradorFacturaUbl;
use CamargoPMS\Servicios\CPE\Generadores\GeneradorNotaCreditoUbl;
use CamargoPMS\Servicios\CPE\Generadores\GeneradorNotaDebitoUbl;
use CamargoPMS\Servicios\CPE\Generadores\GeneradorUblInterfaz;
use DOMDocument;

/**
 * Servicio soberano despachador y coordinador de la generación de documentos UBL 2.1 SUNAT.
 * Delega la construcción canónica en generadores especializados según el tipo de comprobante.
 */
class GeneradorUblServicio
{
    /** @var GeneradorUblInterfaz[] */
    private array $generadores;

    public function __construct(
        ?GeneradorFacturaUbl $generadorFactura = null,
        ?GeneradorBoletaUbl $generadorBoleta = null,
        ?GeneradorNotaCreditoUbl $generadorNotaCredito = null,
        ?GeneradorNotaDebitoUbl $generadorNotaDebito = null
    ) {
        $this->generadores = [
            $generadorFactura ?? new GeneradorFacturaUbl(),
            $generadorBoleta ?? new GeneradorBoletaUbl(),
            $generadorNotaCredito ?? new GeneradorNotaCreditoUbl(),
            $generadorNotaDebito ?? new GeneradorNotaDebitoUbl(),
        ];
    }

    /**
     * Genera la cadena XML canónica en memoria sin firmar para el comprobante proporcionado.
     *
     * @throws ValidacionFiscalExcepcion Si el comprobante o sus datos no son conformes a norma.
     */
    public function generarXml(CpeComprobante $comprobante): string
    {
        return $this->obtenerGenerador($comprobante->obtenerTipoComprobante())->generarXml($comprobante);
    }

    /**
     * Genera el objeto DOMDocument estructurado para el comprobante proporcionado.
     *
     * @throws ValidacionFiscalExcepcion Si el comprobante o sus datos no son conformes a norma.
     */
    public function generarDom(CpeComprobante $comprobante): DOMDocument
    {
        return $this->obtenerGenerador($comprobante->obtenerTipoComprobante())->generarDom($comprobante);
    }

    /**
     * Obtiene el generador especializado correspondiente al tipo de comprobante.
     *
     * @throws ValidacionFiscalExcepcion Si el tipo no está soportado.
     */
    public function obtenerGenerador(string $tipoComprobante): GeneradorUblInterfaz
    {
        foreach ($this->generadores as $generador) {
            if ($generador->soportaTipo($tipoComprobante)) {
                return $generador;
            }
        }

        throw new ValidacionFiscalExcepcion(
            "No existe un generador UBL registrado para el tipo de comprobante '{$tipoComprobante}'."
        );
    }
}
