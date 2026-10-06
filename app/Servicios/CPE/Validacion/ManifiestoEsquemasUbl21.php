<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios\CPE\Validacion;

use CamargoPMS\Excepciones\TipoDocumentoNoSoportadoExcepcion;

/**
 * Manifiesto criptográfico inmutable de los esquemas oficiales OASIS UBL 2.1 / SUNAT.
 *
 * Registra los metadatos de integridad (tamaño en bytes, hash SHA-256 y relaciones de
 * dependencia directa) derivados del inventario oficial investigado y homologado (SFS v-2.1),
 * sin almacenar el contenido de los esquemas protegidos en el repositorio Git.
 */
final class ManifiestoEsquemasUbl21
{
    public const VERSION_NORMATIVA = 'UBL-2.1-SUNAT-20231204';

    /**
     * Mapeo normativo cerrado de tipos de comprobante (Catálogo 01) a su esquema raíz.
     * En UBL 2.1 tanto Factura (01) como Boleta (03) comparten el tipo raíz Invoice-2.
     */
    private const MAPEO_ESQUEMA_RAIZ = [
        '01' => 'maindoc/UBL-Invoice-2.1.xsd',
        '03' => 'maindoc/UBL-Invoice-2.1.xsd',
        '07' => 'maindoc/UBL-CreditNote-2.1.xsd',
        '08' => 'maindoc/UBL-DebitNote-2.1.xsd',
    ];

    /**
     * Grafo exhaustivo de esquemas canónicos del conjunto UBL 2.1 / SUNAT para comprobantes de pago.
     * Contiene las huellas SHA-256 exactas verificadas en el paquete oficial SFS v-2.1.
     *
     * @var array<string, array{bytes: int, sha256: string, es_raiz: bool, deps: string[]}>
     */
    private const GRAFO_ESQUEMAS = [
        // Esquemas de documento principal (maindoc)
        'maindoc/UBL-Invoice-2.1.xsd' => [
            'bytes' => 60178,
            'sha256' => '40fae8cb436f3a9506d7acce65ba162caef3b0bed4d5cbc0992b2153ded4edf4',
            'es_raiz' => true,
            'deps' => [
                'common/UBL-CommonAggregateComponents-2.1.xsd',
                'common/UBL-CommonBasicComponents-2.1.xsd',
                'common/UBL-CommonExtensionComponents-2.1.xsd',
            ],
        ],
        'maindoc/UBL-CreditNote-2.1.xsd' => [
            'bytes' => 57697,
            'sha256' => 'a54651b1225052f811bf2ba01346f13f2454e7cc3e0be290c91dd680dc7b7b1a',
            'es_raiz' => true,
            'deps' => [
                'common/UBL-CommonAggregateComponents-2.1.xsd',
                'common/UBL-CommonBasicComponents-2.1.xsd',
                'common/UBL-CommonExtensionComponents-2.1.xsd',
            ],
        ],
        'maindoc/UBL-DebitNote-2.1.xsd' => [
            'bytes' => 55434,
            'sha256' => '295d142102a2a0cd8b223a79b8314c0fe1b55055478221dd2f0ed7603fecf920',
            'es_raiz' => true,
            'deps' => [
                'common/UBL-CommonAggregateComponents-2.1.xsd',
                'common/UBL-CommonBasicComponents-2.1.xsd',
                'common/UBL-CommonExtensionComponents-2.1.xsd',
            ],
        ],

        // Componentes comunes requeridos recursivamente (common)
        'common/UBL-CommonAggregateComponents-2.1.xsd' => [
            'bytes' => 2420444,
            'sha256' => '939172ad8dd057cd403e7f763f6532184dd5ed4b9de24c42ebb35db4792ba613',
            'es_raiz' => false,
            'deps' => [
                'common/UBL-CommonBasicComponents-2.1.xsd',
                'common/UBL-CommonExtensionComponents-2.1.xsd',
            ],
        ],
        'common/UBL-CommonBasicComponents-2.1.xsd' => [
            'bytes' => 219895,
            'sha256' => 'bd4ad043ee1d9da1c7f8018dabf739cfafdfb59143d0d16b9ef769e6b7c408a7',
            'es_raiz' => false,
            'deps' => [
                'common/UBL-QualifiedDataTypes-2.1.xsd',
                'common/UBL-UnqualifiedDataTypes-2.1.xsd',
            ],
        ],
        'common/UBL-CommonExtensionComponents-2.1.xsd' => [
            'bytes' => 9491,
            'sha256' => 'ad7a4e490978adfbcfc5ec0bb20941cf11ac960ccf0c4de8791a7c731a8dbe87',
            'es_raiz' => false,
            'deps' => [
                'common/UBL-CommonBasicComponents-2.1.xsd',
                'common/UBL-ExtensionContentDataType-2.1.xsd',
            ],
        ],
        'common/UBL-CommonSignatureComponents-2.1.xsd' => [
            'bytes' => 5548,
            'sha256' => '3db472305f029bba5c1ae157bfd0178f715c3f9b94bd8e6c557dbce5e88da874',
            'es_raiz' => false,
            'deps' => [
                'common/UBL-CommonBasicComponents-2.1.xsd',
                'common/UBL-SignatureAggregateComponents-2.1.xsd',
            ],
        ],
        'common/UBL-CoreComponentParameters-2.1.xsd' => [
            'bytes' => 3173,
            'sha256' => '8be3379dbdcbcc7802fafdd16bac72c48fff1c0bb364213a31a17911dd06100b',
            'es_raiz' => false,
            'deps' => [],
        ],
        'common/UBL-ExtensionContentDataType-2.1.xsd' => [
            'bytes' => 4314,
            'sha256' => 'fcee77a11870208e6377ea6311b9f2a050bca24bdad8606ea02d71e9f9e72f8d',
            'es_raiz' => false,
            'deps' => [
                'common/UBL-CommonSignatureComponents-2.1.xsd',
                'common/UBL-XAdESv132-2.1.xsd',
                'common/UBL-XAdESv141-2.1.xsd',
                'common/UBL-xmldsig-core-schema-2.1.xsd',
            ],
        ],
        'common/UBL-QualifiedDataTypes-2.1.xsd' => [
            'bytes' => 3590,
            'sha256' => '7dcb156e610239c97ae70940cf4653b88e48c3595bf5f56a2204a32e2893e6cf',
            'es_raiz' => false,
            'deps' => [
                'common/UBL-UnqualifiedDataTypes-2.1.xsd',
            ],
        ],
        'common/UBL-SignatureAggregateComponents-2.1.xsd' => [
            'bytes' => 7784,
            'sha256' => '17bb6b62d709b4fd81449a37655af36aa6a1276ad4fdb1b2e249a5ed4b7c2172',
            'es_raiz' => false,
            'deps' => [
                'common/UBL-SignatureBasicComponents-2.1.xsd',
            ],
        ],
        'common/UBL-SignatureBasicComponents-2.1.xsd' => [
            'bytes' => 4207,
            'sha256' => 'cef924d7ba3d1d8ade14469325cde1364f8c174e46f0198ec02da8e9e748a489',
            'es_raiz' => false,
            'deps' => [
                'common/UBL-UnqualifiedDataTypes-2.1.xsd',
            ],
        ],
        'common/UBL-UnqualifiedDataTypes-2.1.xsd' => [
            'bytes' => 27300,
            'sha256' => '09052d406b4293e2a5f9c2bfee6df10ad4d8d5f0b36e24a6349d7f7936d89eb6',
            'es_raiz' => false,
            'deps' => [
                'common/CCTS_CCT_SchemaModule-2.1.xsd',
            ],
        ],
        'common/UBL-XAdESv132-2.1.xsd' => [
            'bytes' => 21664,
            'sha256' => 'a4f726bcf8cc3f7d9ffa4dab99e005535a8e8b60dced1e5d94578d2e05afa96e',
            'es_raiz' => false,
            'deps' => [
                'common/UBL-xmldsig-core-schema-2.1.xsd',
            ],
        ],
        'common/UBL-XAdESv141-2.1.xsd' => [
            'bytes' => 1316,
            'sha256' => '1fa4625e9cefcb7a9abb5ac1b64315547450031eece8a55bd584e4ba4b79dbc1',
            'es_raiz' => false,
            'deps' => [
                'common/UBL-XAdESv132-2.1.xsd',
                'common/UBL-xmldsig-core-schema-2.1.xsd',
            ],
        ],
        'common/UBL-xmldsig-core-schema-2.1.xsd' => [
            'bytes' => 10750,
            'sha256' => '101909c9f06456d61ddcc4fb982f1d40dc357b439f393b1a2eb46e42acd60809',
            'es_raiz' => false,
            'deps' => [],
        ],
        'common/UBLPE-SunatAggregateComponents-1.1.xsd' => [
            'bytes' => 48176,
            'sha256' => '138c7216706b11b1f988c66ea8372c95f5aa5ebe325c96f19dac16f9f6fdf368',
            'es_raiz' => false,
            'deps' => [
                'common/UBL-CommonBasicComponents-2.1.xsd',
                'common/UBL-CommonExtensionComponents-2.1.xsd',
            ],
        ],
        'common/CCTS_CCT_SchemaModule-2.1.xsd' => [
            'bytes' => 45268,
            'sha256' => 'dd546e4809df86b6445589f69f0d6c9df162840ae386574ddfc1da7638103e15',
            'es_raiz' => false,
            'deps' => [],
        ],
    ];

    /**
     * Retorna la ruta relativa del esquema raíz para un tipo de comprobante dado.
     *
     * @param string $tipoComprobante Código según Catálogo 01 ('01', '03', '07', '08')
     * @return string Ruta relativa (ej. 'maindoc/UBL-Invoice-2.1.xsd')
     * @throws TipoDocumentoNoSoportadoExcepcion Si el tipo no está mapeado
     */
    public static function obtenerEsquemaRaizParaTipoCpe(string $tipoComprobante): string
    {
        if (!isset(self::MAPEO_ESQUEMA_RAIZ[$tipoComprobante])) {
            throw new TipoDocumentoNoSoportadoExcepcion(
                "Tipo de comprobante '{$tipoComprobante}' no soportado para validación XSD UBL 2.1"
            );
        }
        return self::MAPEO_ESQUEMA_RAIZ[$tipoComprobante];
    }

    /**
     * Retorna la lista completa de todas las rutas relativas de esquemas canónicos reconocidos.
     *
     * @return string[]
     */
    public static function obtenerListaEsquemas(): array
    {
        return array_keys(self::GRAFO_ESQUEMAS);
    }

    /**
     * Retorna los metadatos oficiales de un esquema específico.
     *
     * @param string $rutaRelativa Ruta relativa canónica (ej. 'maindoc/UBL-Invoice-2.1.xsd')
     * @return array{bytes: int, sha256: string, es_raiz: bool, deps: string[]}|null
     */
    public static function obtenerMetadatos(string $rutaRelativa): ?array
    {
        $rutaNormalizada = str_replace('\\', '/', $rutaRelativa);
        return self::GRAFO_ESQUEMAS[$rutaNormalizada] ?? null;
    }

    /**
     * Resuelve el cierre transitivo de dependencias para un tipo de comprobante dado.
     *
     * @param string $tipoComprobante Código Catálogo 01 ('01', '03', '07', '08')
     * @return string[] Lista ordenada de rutas relativas necesarias
     */
    public static function obtenerClausuraDependenciasParaTipoCpe(string $tipoComprobante): array
    {
        $raiz = self::obtenerEsquemaRaizParaTipoCpe($tipoComprobante);
        $visitados = [];
        $cola = [$raiz];

        while (!empty($cola)) {
            $actual = array_shift($cola);
            if (isset($visitados[$actual])) {
                continue;
            }
            $visitados[$actual] = true;
            $meta = self::GRAFO_ESQUEMAS[$actual] ?? null;
            if ($meta !== null) {
                foreach ($meta['deps'] as $dep) {
                    if (!isset($visitados[$dep])) {
                        $cola[] = $dep;
                    }
                }
            }
        }

        return array_keys($visitados);
    }

    /**
     * Retorna el número total de esquemas definidos en el grafo canónico.
     */
    public static function obtenerTotalEsquemas(): int
    {
        return count(self::GRAFO_ESQUEMAS);
    }
}
