<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios\CPE\Validacion;

use CamargoPMS\Excepciones\TipoDocumentoNoSoportadoExcepcion;
use CamargoPMS\Excepciones\ValidacionXsdCpeExcepcion;
use CamargoPMS\Excepciones\XmlMalformadoCpeExcepcion;
use DOMDocument;
use LibXMLError;

/**
 * Validador estructural offline de Comprobantes de Pago Electrónicos UBL 2.1 (OASIS / SUNAT).
 *
 * Ejecuta la validación sintáctica de esquemas W3C XML Schema (XSD) mediante libxml nativo,
 * de solo lectura, sin alterar el contenido ni la firma digital del comprobante (byte-for-byte),
 * sin peticiones de red (LIBXML_NONET) y con manejo defensivo ante vectores XXE.
 */
class ValidadorXsdCpe
{
    private ProveedorEsquemasCpeInterfaz $proveedorEsquemas;

    public function __construct(?ProveedorEsquemasCpeInterfaz $proveedor = null)
    {
        $this->proveedorEsquemas = $proveedor ?? new ProveedorEsquemasCpeLocal();
    }

    /**
     * Valida un comprobante XML contra el esquema XSD oficial correspondiente a su tipo.
     *
     * @param string $xml Contenido XML completo del comprobante (con o sin firma)
     * @param string $tipoComprobante Código de comprobante según Catálogo 01 ('01', '03', '07', '08')
     * @return void
     *
     * @throws ValidacionXsdCpeExcepcion Si el comprobante incumple el esquema XSD
     * @throws XmlMalformadoCpeExcepcion Si el XML tiene errores sintácticos o inyecciones DTD/XXE
     * @throws TipoDocumentoNoSoportadoExcepcion Si el tipo de comprobante no es soportado
     * @throws \CamargoPMS\Excepciones\EsquemaCpeNoDisponibleExcepcion Si faltan esquemas
     * @throws \CamargoPMS\Excepciones\IntegridadEsquemaCpeExcepcion Si los esquemas están corruptos
     */
    public function validar(string $xml, string $tipoComprobante): void
    {
        if (trim($xml) === '') {
            throw new XmlMalformadoCpeExcepcion('El comprobante XML suministrado está vacío');
        }

        // Defensas preventivas ante ataques de entidad externa y XXE
        if (stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) {
            throw new XmlMalformadoCpeExcepcion(
                'El comprobante XML contiene declaraciones DOCTYPE o ENTITY no permitidas (riesgo XXE)'
            );
        }

        // Resuelve y verifica el esquema raíz confiable
        $rutaEsquema = $this->proveedorEsquemas->obtenerRutaEsquema($tipoComprobante);

        // Control aislado de errores internos de libxml
        $estadoAnteriorLibxml = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $dom = new DOMDocument();
            // Carga estrictamente en memoria sin red ni sustitución de entidades
            $cargado = @$dom->loadXML($xml, LIBXML_NONET);

            if (!$cargado) {
                $erroresXml = $this->formatearErroresLibxml(libxml_get_errors());
                throw new XmlMalformadoCpeExcepcion(
                    'Error de sintaxis al parsear el comprobante XML: ' . ($erroresXml[0]['mensaje'] ?? 'XML inválido')
                );
            }

            libxml_clear_errors();

            // Validación formal contra el esquema XSD local
            $esConforme = @$dom->schemaValidate($rutaEsquema);

            if (!$esConforme) {
                $erroresEsquema = $this->formatearErroresLibxml(libxml_get_errors());
                throw new ValidacionXsdCpeExcepcion(
                    "El comprobante tipo '{$tipoComprobante}' no cumple con el esquema XSD oficial UBL 2.1",
                    $erroresEsquema
                );
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($estadoAnteriorLibxml);
        }
    }

    /**
     * Evalúa si un comprobante XML es conforme con el esquema XSD sin lanzar excepción ante fallos de validación.
     *
     * @param string $xml Contenido XML a evaluar
     * @param string $tipoComprobante Código Catálogo 01
     * @return bool True si es estrictamente conforme con el XSD
     */
    public function esValido(string $xml, string $tipoComprobante): bool
    {
        try {
            $this->validar($xml, $tipoComprobante);
            return true;
        } catch (ValidacionXsdCpeExcepcion | XmlMalformadoCpeExcepcion) {
            return false;
        }
    }

    /**
     * Retorna la instancia del proveedor de esquemas configurado.
     */
    public function obtenerProveedor(): ProveedorEsquemasCpeInterfaz
    {
        return $this->proveedorEsquemas;
    }

    /**
     * Transforma y sanitiza la lista de errores crudos de libxml eliminando rutas internas del servidor.
     *
     * @param LibXMLError[] $errores
     * @return array<int, array{linea: int, columna: int, nivel: string, mensaje: string}>
     */
    private function formatearErroresLibxml(array $errores): array
    {
        $formateados = [];
        foreach ($errores as $err) {
            $nivel = match ($err->level) {
                LIBXML_ERR_WARNING => 'ADVERTENCIA',
                LIBXML_ERR_ERROR => 'ERROR',
                LIBXML_ERR_FATAL => 'FATAL',
                default => 'INFO',
            };

            // Sanitiza mensajes eliminando posibles rutas absolutas del servidor
            $mensaje = trim($err->message);
            $mensaje = preg_replace('/[a-zA-Z]:[\\\\\/][^: ]+/i', '[archivo_esquema]', $mensaje);
            $mensaje = preg_replace('/\/[a-zA-Z0-9_\-.\/]+\.xsd/i', '[archivo_esquema]', (string) $mensaje);

            $formateados[] = [
                'linea' => $err->line,
                'columna' => $err->column,
                'nivel' => $nivel,
                'mensaje' => (string) $mensaje,
            ];
        }
        return $formateados;
    }
}
