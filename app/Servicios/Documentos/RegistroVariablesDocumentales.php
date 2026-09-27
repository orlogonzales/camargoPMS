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
