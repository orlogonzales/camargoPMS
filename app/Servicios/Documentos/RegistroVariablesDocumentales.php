<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios\Documentos;

use CamargoPMS\Excepciones\VariableDocumentalDesconocidaExcepcion;
use CamargoPMS\Excepciones\VariableDocumentalFaltanteExcepcion;

/**
 * Registro central de variables tipadas y shortcodes autorizados para documentos (D-079 #10, #11).
 *
 * Impide la resolución dinámica arbitraria tipo $objeto->$propiedad y garantiza que toda
 * variable sea validada sintácticamente, formateada según su tipo y escapada para HTML.
 */
class RegistroVariablesDocumentales
{
    /**
     * Catálogo maestro de variables por tipo de origen documental.
     * Estructura: [origen_tipo => [variable => ['tipo' => ..., 'requerido' => bool, 'descripcion' => ...]]]
     */
    private const VARIABLES_AUTORIZADAS = [
        'ARRENDAMIENTO' => [
            // Contrato
            'contrato.numero' => ['tipo' => 'string', 'requerido' => true],
            'contrato.fecha_inicio' => ['tipo' => 'date', 'requerido' => true],
            'contrato.fecha_fin' => ['tipo' => 'date', 'requerido' => true],
            'contrato.duracion_meses' => ['tipo' => 'int', 'requerido' => true],
            'contrato.dia_corte_pago' => ['tipo' => 'int', 'requerido' => true],
            'contrato.canon_monto' => ['tipo' => 'money', 'requerido' => true],
            'contrato.canon_moneda' => ['tipo' => 'string', 'requerido' => true],
            'contrato.canon_texto' => ['tipo' => 'string', 'requerido' => true],
            'garantia.monto' => ['tipo' => 'money', 'requerido' => true],
            'garantia.texto' => ['tipo' => 'string', 'requerido' => true],

            // Arrendador (Empresa)
            'arrendador.razon_social' => ['tipo' => 'string', 'requerido' => true],
            'arrendador.ruc' => ['tipo' => 'string', 'requerido' => true],
            'arrendador.representante_legal' => ['tipo' => 'string', 'requerido' => true],
            'arrendador.representante_dni' => ['tipo' => 'string', 'requerido' => true],
            'arrendador.domicilio_legal' => ['tipo' => 'string', 'requerido' => true],

            // Arrendatario (Huésped / Inquilino)
            'arrendatario.nombre_completo' => ['tipo' => 'string', 'requerido' => true],
            'arrendatario.tipo_documento' => ['tipo' => 'string', 'requerido' => true],
            'arrendatario.numero_documento' => ['tipo' => 'string', 'requerido' => true],
            'arrendatario.email' => ['tipo' => 'string', 'requerido' => true],
            'arrendatario.telefono' => ['tipo' => 'string', 'requerido' => true],

            // Inmueble
            'propiedad.nombre' => ['tipo' => 'string', 'requerido' => true],
            'propiedad.direccion' => ['tipo' => 'string', 'requerido' => true],
            'unidad.nombre' => ['tipo' => 'string', 'requerido' => true],
            'unidad.tipologia' => ['tipo' => 'string', 'requerido' => true],

            // Emisión y Sistema
            'emision.fecha' => ['tipo' => 'date', 'requerido' => true],
            'documento.folio' => ['tipo' => 'string', 'requerido' => false],

            // Bloques HTML controlados
            'bloque.inventario_dotacion' => ['tipo' => 'html', 'requerido' => false],
            'bloque.firmas_partes' => ['tipo' => 'html', 'requerido' => false],
        ],
        'COMPRA' => [
            // Identificación y Emisión
            'documento.folio' => ['tipo' => 'string', 'requerido' => false],
            'orden.codigo' => ['tipo' => 'string', 'requerido' => true],
            'orden.fecha' => ['tipo' => 'date', 'requerido' => true],
            'orden.fecha_entrega' => ['tipo' => 'string', 'requerido' => false],
            'orden.condicion_pago' => ['tipo' => 'string', 'requerido' => true],
            'orden.notas' => ['tipo' => 'string', 'requerido' => false],
            'emision.fecha' => ['tipo' => 'date', 'requerido' => false],

            // Proveedor
            'proveedor.razon_social' => ['tipo' => 'string', 'requerido' => true],
            'proveedor.numero_documento' => ['tipo' => 'string', 'requerido' => true],
            'proveedor.contacto' => ['tipo' => 'string', 'requerido' => false],
            'proveedor.telefono' => ['tipo' => 'string', 'requerido' => false],
            'proveedor.email' => ['tipo' => 'string', 'requerido' => false],
            'proveedor.direccion' => ['tipo' => 'string', 'requerido' => false],

            // Almacén / Entrega
            'almacen.nombre' => ['tipo' => 'string', 'requerido' => false],
            'almacen.direccion' => ['tipo' => 'string', 'requerido' => false],

            // Comprador (Empresa)
            'comprador.razon_social' => ['tipo' => 'string', 'requerido' => false],
            'comprador.ruc' => ['tipo' => 'string', 'requerido' => false],
            'comprador.direccion' => ['tipo' => 'string', 'requerido' => false],

            // Detalle y Totales
            'tabla_lineas' => ['tipo' => 'html', 'requerido' => true],
            'totales.moneda' => ['tipo' => 'string', 'requerido' => true],
            'totales.subtotal' => ['tipo' => 'money', 'requerido' => true],
            'totales.impuesto' => ['tipo' => 'money', 'requerido' => true],
            'totales.total' => ['tipo' => 'money', 'requerido' => true],
            'totales.texto' => ['tipo' => 'string', 'requerido' => true],
        ],
        'RECIBO' => [
            // Identificación y Emisión
            'documento.folio' => ['tipo' => 'string', 'requerido' => true],
            'emision.fecha' => ['tipo' => 'date', 'requerido' => true],
            'emision.hora' => ['tipo' => 'string', 'requerido' => true],
            'emision.actor' => ['tipo' => 'string', 'requerido' => false],

            // Emisor (Empresa)
            'emisor.razon_social' => ['tipo' => 'string', 'requerido' => false],
            'emisor.ruc' => ['tipo' => 'string', 'requerido' => false],
            'emisor.nombre_comercial' => ['tipo' => 'string', 'requerido' => false],
            'emisor.direccion_fiscal' => ['tipo' => 'string', 'requerido' => false],

            // Cliente / Titular
            'cliente.nombre_completo' => ['tipo' => 'string', 'requerido' => true],
            'cliente.tipo_documento' => ['tipo' => 'string', 'requerido' => true],
            'cliente.numero_documento' => ['tipo' => 'string', 'requerido' => true],
            'cliente.email' => ['tipo' => 'string', 'requerido' => false],
            'cliente.telefono' => ['tipo' => 'string', 'requerido' => false],

            // Origen Contractual / Inmueble
            'folio.codigo' => ['tipo' => 'string', 'requerido' => true],
            'propiedad.nombre' => ['tipo' => 'string', 'requerido' => true],
            'propiedad.direccion' => ['tipo' => 'string', 'requerido' => false],
            'unidad.nombre' => ['tipo' => 'string', 'requerido' => false],
            'contrato.codigo' => ['tipo' => 'string', 'requerido' => false],
            'reserva.codigo' => ['tipo' => 'string', 'requerido' => false],

            // Hecho Económico de Cobro
            'pago.codigo' => ['tipo' => 'string', 'requerido' => true],
            'pago.metodo' => ['tipo' => 'string', 'requerido' => true],
            'pago.medio_detalle' => ['tipo' => 'string', 'requerido' => false],
            'pago.referencia_operacion' => ['tipo' => 'string', 'requerido' => false],
            'pago.moneda' => ['tipo' => 'string', 'requerido' => true],
            'pago.monto_recaudado' => ['tipo' => 'money', 'requerido' => true],
            'pago.monto_texto' => ['tipo' => 'string', 'requerido' => true],

            // Tabla Estructurada de Amortizaciones
            'tabla_amortizaciones' => ['tipo' => 'html', 'requerido' => true],

            // Resumen y Saldos en T0
            'totales.monto_imputado' => ['tipo' => 'money', 'requerido' => true],
            'totales.monto_no_aplicado_pago' => ['tipo' => 'money', 'requerido' => true],
            'totales.saldo_pendiente_folio_despues' => ['tipo' => 'money', 'requerido' => true],
            'totales.saldo_favor_folio_despues' => ['tipo' => 'money', 'requerido' => true],
        ],
    ];

    /**
     * Valida que todos los shortcodes {{...}} presentes en el HTML pertenezcan al catálogo autorizado.
     *
     * @throws VariableDocumentalDesconocidaExcepcion si se detecta un shortcode no registrado.
     */
    public function validarShortcodesEnHtml(string $html, string $origenTipo, string $plantillaCodigo = ''): void
    {
        $origenTipo = strtoupper(trim($origenTipo));
        $catalogo = self::VARIABLES_AUTORIZADAS[$origenTipo] ?? [];

        preg_match_all('/\{\{\s*([a-zA-Z0-9_\.]+)\s*\}\}/', $html, $matches);
        $variablesEncontradas = array_unique($matches[1] ?? []);

        foreach ($variablesEncontradas as $var) {
            if (!isset($catalogo[$var])) {
                throw new VariableDocumentalDesconocidaExcepcion($var, $plantillaCodigo);
            }
        }
    }

    /**
     * Valida que las variables requeridas estén presentes y formatea los valores de forma segura.
     *
     * @param string $origenTipo
     * @param array<string, mixed> $datosCrudos
     * @param int $origenId
     * @return array<string, string> Mapa [variable => valor_seguro_formateado]
     * @throws VariableDocumentalFaltanteExcepcion si falta una variable requerida.
     */
    public function resolverVariables(string $origenTipo, array $datosCrudos, int $origenId = 0): array
    {
        $origenTipo = strtoupper(trim($origenTipo));
        $catalogo = self::VARIABLES_AUTORIZADAS[$origenTipo] ?? [];

        $datosResueltos = [];

        foreach ($catalogo as $var => $def) {
            $esRequerida = $def['requerido'] ?? false;
            $tipo = $def['tipo'] ?? 'string';

            if (!array_key_exists($var, $datosCrudos) || $datosCrudos[$var] === null || $datosCrudos[$var] === '') {
                if ($esRequerida) {
                    throw new VariableDocumentalFaltanteExcepcion($var, $origenTipo, $origenId);
                }
                $datosResueltos[$var] = '';
                continue;
            }

            $valorCrudo = (string) $datosCrudos[$var];

            // Si es un bloque HTML controlado, se permite el paso de tags seguros ya construidos
            if ($tipo === 'html') {
                $datosResueltos[$var] = $valorCrudo;
            } else {
                // Escapado estricto contra inyección HTML/XSS
                $datosResueltos[$var] = htmlspecialchars($valorCrudo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
        }

        return $datosResueltos;
    }

    /**
     * Retorna el catálogo de variables permitidas para un origen dado.
     */
    public function obtenerCatalogoPorOrigen(string $origenTipo): array
    {
        return self::VARIABLES_AUTORIZADAS[strtoupper(trim($origenTipo))] ?? [];
    }
}
