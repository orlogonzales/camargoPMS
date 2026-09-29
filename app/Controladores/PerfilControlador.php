<?php

declare(strict_types=1);

namespace CamargoPMS\Controladores;

use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Nucleo\Vista;
use CamargoPMS\Repositorios\PersonaRepositorio;
use CamargoPMS\Repositorios\UsuarioRepositorio;
use CamargoPMS\Servicios\SesionServicio;

/**
 * Controlador de Perfil de Usuario para Camargo PMS (UI-ALINA-1B).
 *
 * Basado en la interfaz y componentes de Alina profile.html.
 * Aplica estrictamente el principio arquitectónico:
 * PERSONA ≠ USUARIO
 *
 * No duplica operaciones AUTH ni gestión masiva de sesiones.
 */
class PerfilControlador
{
    private Vista $vista;
    private SesionServicio $sesionServicio;
    private UsuarioRepositorio $usuarioRepo;
    private PersonaRepositorio $personaRepo;

    public function __construct(
        ?Vista $vista = null,
        ?SesionServicio $sesionServicio = null,
        ?UsuarioRepositorio $usuarioRepo = null,
        ?PersonaRepositorio $personaRepo = null
    ) {
        $this->vista = $vista ?? new Vista();
        $this->sesionServicio = $sesionServicio ?? new SesionServicio();
        $this->usuarioRepo = $usuarioRepo ?? new UsuarioRepositorio();
        $this->personaRepo = $personaRepo ?? new PersonaRepositorio();
    }

    /**
     * Muestra la pantalla de perfil del usuario autenticado actual.
     *
     * @return Respuesta
     */
    public function index(): Respuesta
    {
        $usuarioActual = $this->sesionServicio->validarSesionActual();
        if ($usuarioActual === null) {
            return Respuesta::redireccionar(url_ruta('/login'));
        }

        $usuarioId = (int) $usuarioActual->obtenerId();
        $personaId = (int) $usuarioActual->obtenerPersonaId();

        // 1. Cargar datos técnicos y roles del Usuario (cuenta de acceso)
        $detalleUsuario = $this->usuarioRepo->obtenerDetalleCompleto($usuarioId);

        // 2. Cargar datos de la Persona humana vinculada (sujeto natural)
        $persona = $this->personaRepo->buscarPorId($personaId, true);

        // 3. Preparar datos para la vista respetando PERSONA ≠ USUARIO
        $datos = [
            'titulo' => 'Camargo PMS — Mi Perfil',
            'categoriaActiva' => 'inicio',
            'migasPan' => [
                ['etiqueta' => 'Inicio', 'url' => url_ruta('/')],
                ['etiqueta' => 'Mi Perfil', 'activo' => true],
            ],
            'usuario' => $usuarioActual,
            'detalleUsuario' => $detalleUsuario,
            'persona' => $persona,
        ];

        $html = $this->vista->renderizar('perfil/index', $datos, 'principal');

        return new Respuesta($html, 200);
    }
}
