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
     * Muestra la pantalla de gestión de usuarios (protegida por autorización usuarios.ver).
     *
     * @return Respuesta
     */
    public function usuarios(): Respuesta
    {
        $datos = [
            'titulo' => 'Camargo PMS — Gestión de Usuarios',
            'categoriaActiva' => 'administracion',
            'migasPan' => [
                ['etiqueta' => 'Panel', 'url' => url_ruta('/'), 'activo' => false],
                ['etiqueta' => 'Administración', 'url' => '#', 'activo' => false],
                ['etiqueta' => 'Usuarios', 'url' => url_ruta('/usuarios'), 'activo' => true],
            ],
            'menuEstatico' => $this->obtenerMenuEstaticoPrueba(),
            'sistema' => [
                'nombre' => 'Camargo PMS',
                'version' => 'Fase ROLES-1',
                'entorno' => 'Desarrollo Local',
                'phpVersion' => PHP_VERSION,
            ],
        ];

        $html = $this->vista->renderizar('panel/inicio', $datos, 'principal');

        return new Respuesta($html, 200);
    }

    /**
     * Muestra una vista controlada para errores HTTP reutilizando la plantilla aislada de Alina.
     *
     * @param int $codigo Código de estado HTTP (400, 403, 404, 500, 503).
     * @param string|null $mensaje Mensaje contextual seguro para el usuario.
     * @return Respuesta
     */
    public function error(int $codigo = 404, ?string $mensaje = null): Respuesta
    {
        $mensajesDefecto = [
            400 => [
                'titulo' => 'Solicitud Incorrecta',
                'mensaje' => 'La solicitud no pudo ser procesada debido a una sintaxis o formato no válido.'
            ],
            403 => [
                'titulo' => 'Acceso Denegado',
                'mensaje' => 'No cuentas con autorización suficiente para visualizar o manipular este recurso.'
            ],
            404 => [
                'titulo' => 'Página No Encontrada',
                'mensaje' => 'El enlace o recurso solicitado no existe, ha cambiado de ruta o no se encuentra disponible.'
            ],
            500 => [
                'titulo' => 'Error del Servidor',
                'mensaje' => 'Ocurrió una condición inesperada al procesar la solicitud. El equipo técnico ha sido notificado.'
            ],
            503 => [
                'titulo' => 'Servicio No Disponible',
                'mensaje' => 'El sistema se encuentra temporalmente fuera de servicio por mantenimiento o capacidad.'
            ],
        ];

        $info = $mensajesDefecto[$codigo] ?? $mensajesDefecto[404];

        $datos = [
            'codigo' => $codigo,
            'titulo' => $info['titulo'] . ' — Camargo PMS',
            'mensaje' => $mensaje ?? $info['mensaje'],
            'accion' => 'Volver al Inicio',
            'urlRetorno' => url_ruta('/'),
            'imagen' => url_asset("images/error/error-{$codigo}.png"),
        ];

        $html = $this->vista->renderizar('errores/error', $datos, 'error');

        return new Respuesta($html, $codigo);
    }

    /**
     * Muestra la vista controlada para páginas o rutas no encontradas (404).
     *
     * @return Respuesta
     */
    public function paginaNoEncontrada(): Respuesta
    {
        return $this->error(404);
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
