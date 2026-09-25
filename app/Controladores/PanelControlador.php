<?php

declare(strict_types=1);

namespace CamargoPMS\Controladores;

use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Nucleo\Vista;

/**
 * Controlador para la presentación inicial y de comprobación de Camargo PMS en UI-0.
 *
 * Prepara exclusivamente datos de presentación y delega en el motor de vistas.
 * No accede a base de datos, PDO ni servicios de dominio conforme a las prohibiciones de UI-0.
 */
final class PanelControlador
{
    private Vista $vista;

    public function __construct()
    {
        $this->vista = new Vista();
    }

    /**
     * Muestra la pantalla inicial neutra de verificación arquitectónica.
     *
     * @return Respuesta
     */
    public function inicio(): Respuesta
    {
        $datos = [
            'titulo' => 'Camargo PMS — Panel Principal',
            'categoriaActiva' => 'inicio',
            'migasPan' => [
                ['etiqueta' => 'Panel', 'url' => url_ruta('/'), 'activo' => false],
                ['etiqueta' => 'Comprobación de Layout', 'url' => url_ruta('/'), 'activo' => true],
            ],
            'menuEstatico' => $this->obtenerMenuEstaticoPrueba(),
            'sistema' => [
                'nombre' => 'Camargo PMS',
                'version' => 'Fase UI-0',
                'entorno' => 'Desarrollo Local',
                'phpVersion' => PHP_VERSION,
            ],
        ];

        $html = $this->vista->renderizar('panel/inicio', $datos, 'principal');

        return new Respuesta($html, 200);
    }

    /**
     * Muestra la vista controlada para páginas o rutas no encontradas (404).
     *
     * @return Respuesta
     */
    public function paginaNoEncontrada(): Respuesta
    {
        $datos = [
            'titulo' => 'Página No Encontrada — Camargo PMS',
            'categoriaActiva' => 'inicio',
            'migasPan' => [
                ['etiqueta' => 'Inicio', 'url' => url_ruta('/'), 'activo' => false],
                ['etiqueta' => 'Error 404', 'url' => '', 'activo' => true],
            ],
            'menuEstatico' => $this->obtenerMenuEstaticoPrueba(),
        ];

        $html = $this->vista->renderizar('errores/404', $datos, 'principal');

        return new Respuesta($html, 404);
    }

    /**
     * Estructura estática de prueba que reproduce fielmente el contrato de navegación Alina.
     * Cada clave primaria vincula con un grupo secundario idéntico.
     *
     * @return array<string, array<string, mixed>>
     */
    private function obtenerMenuEstaticoPrueba(): array
    {
        return [
            'inicio' => [
                'clave' => 'inicio',
                'etiqueta' => 'Inicio',
                'icono' => 'ti ti-smart-home',
                'activo' => true,
                'grupos' => [
                    [
                        'tipo' => 'simple',
                        'titulo' => 'Panel Principal',
                        'url' => url_ruta('/'),
                        'icono' => 'ti ti-dashboard',
                        'activo' => true,
                    ]
                ]
            ],
            'operaciones' => [
                'clave' => 'operaciones',
                'etiqueta' => 'Operaciones',
                'icono' => 'ti ti-calendar-event',
                'activo' => false,
                'grupos' => [
                    [
                        'tipo' => 'colapsable',
                        'id' => 'submenu-operaciones',
                        'titulo' => 'Ocupación y Estancias',
                        'icono' => 'ti ti-calendar',
                        'items' => [
                            ['titulo' => 'Disponibilidad (Próximamente)', 'url' => '#'],
                            ['titulo' => 'Reservas (Próximamente)', 'url' => '#'],
                            ['titulo' => 'Estadías (Próximamente)', 'url' => '#'],
                        ]
                    ]
                ]
            ],
            'propiedades' => [
                'clave' => 'propiedades',
                'etiqueta' => 'Inmuebles',
                'icono' => 'ti ti-building',
                'activo' => false,
                'grupos' => [
                    [
                        'tipo' => 'colapsable',
                        'id' => 'submenu-propiedades',
                        'titulo' => 'Catálogo Inmobiliario',
                        'icono' => 'ti ti-building-community',
                        'items' => [
                            ['titulo' => 'Propiedades (Próximamente)', 'url' => '#'],
                            ['titulo' => 'Unidades (Próximamente)', 'url' => '#'],
                        ]
                    ]
                ]
            ],
            'personas' => [
                'clave' => 'personas',
                'etiqueta' => 'Personas',
                'icono' => 'ti ti-users',
                'activo' => false,
                'grupos' => [
                    [
                        'tipo' => 'colapsable',
                        'id' => 'submenu-personas',
                        'titulo' => 'Contactos y Proveedores',
                        'icono' => 'ti ti-user-check',
                        'items' => [
                            ['titulo' => 'Directorio de Personas (Próximamente)', 'url' => '#'],
                            ['titulo' => 'Proveedores de Servicios (Próximamente)', 'url' => '#'],
                        ]
                    ]
                ]
            ],
            'finanzas' => [
                'clave' => 'finanzas',
                'etiqueta' => 'Finanzas',
                'icono' => 'ti ti-cash',
                'activo' => false,
                'grupos' => [
                    [
                        'tipo' => 'colapsable',
                        'id' => 'submenu-finanzas',
                        'titulo' => 'Caja y Cobros',
                        'icono' => 'ti ti-receipt',
                        'items' => [
                            ['titulo' => 'Movimientos de Caja (Próximamente)', 'url' => '#'],
                            ['titulo' => 'Cobros y Pagos (Próximamente)', 'url' => '#'],
                        ]
                    ]
                ]
            ],
            'administracion' => [
                'clave' => 'administracion',
                'etiqueta' => 'Configuración',
                'icono' => 'ti ti-settings',
                'activo' => false,
                'grupos' => [
                    [
                        'tipo' => 'colapsable',
                        'id' => 'submenu-administracion',
                        'titulo' => 'Ajustes del Sistema',
                        'icono' => 'ti ti-adjustments',
                        'items' => [
                            ['titulo' => 'Empresa y Parámetros (Próximamente)', 'url' => '#'],
                            ['titulo' => 'Plantillas Documentales (Próximamente)', 'url' => '#'],
                        ]
                    ]
                ]
            ]
        ];
    }
}
