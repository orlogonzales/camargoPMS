<?php

declare(strict_types=1);

namespace CamargoPMS\Controladores;

use CamargoPMS\Modelos\Feriado;
use CamargoPMS\Modelos\Usuario;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Nucleo\Vista;
use CamargoPMS\Repositorios\ActorAuditoriaRepositorio;
use CamargoPMS\Servicios\AutorizacionServicio;
use CamargoPMS\Servicios\CsrfServicio;
use CamargoPMS\Servicios\ReclamacionServicio;
use CamargoPMS\Servicios\SesionServicio;
use PDO;
use Throwable;

/**
 * Controlador para la gestión y mantenimiento del Calendario de Feriados y Días No Laborables (RECLAMACIONES-1).
 */
class FeriadoControlador
{
    private Vista $vista;
    private PDO $pdo;
    private ReclamacionServicio $reclamacionServicio;
    private SesionServicio $sesionServicio;
    private AutorizacionServicio $autorizacionServicio;
    private CsrfServicio $csrfServicio;

    public function __construct(
        ?Vista $vista = null,
        ?PDO $pdo = null,
        ?ReclamacionServicio $reclamacionServicio = null,
        ?SesionServicio $sesionServicio = null,
        ?AutorizacionServicio $autorizacionServicio = null,
        ?CsrfServicio $csrfServicio = null
    ) {
        $this->vista = $vista ?? new Vista();
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->reclamacionServicio = $reclamacionServicio ?? new ReclamacionServicio($this->pdo);
        $this->sesionServicio = $sesionServicio ?? new SesionServicio($this->pdo);
        $this->autorizacionServicio = $autorizacionServicio ?? new AutorizacionServicio($this->pdo);
        $this->csrfServicio = $csrfServicio ?? new CsrfServicio();
    }

    private function obtenerUsuarioAutenticado(): ?Usuario
    {
        SesionServicio::iniciarSesionPhp();
        return $this->sesionServicio->validarSesionActual();
    }

    private function resolverActorId(): int
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if ($usuario !== null && $usuario->obtenerId() !== null) {
            $actorRepo = new ActorAuditoriaRepositorio($this->pdo);
            $actor = $actorRepo->buscarPorUsuarioId((int) $usuario->obtenerId());
            if ($actor !== null && $actor->obtenerId() !== null) {
                return (int) $actor->obtenerId();
            }
        }
        return 1;
    }

    private function validarCsrf(?array $datos = null): ?Respuesta
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? $_POST['csrf_token']
            ?? $_POST['_csrf_token']
            ?? ($datos['csrf_token'] ?? null)
            ?? ($datos['_csrf_token'] ?? null);

        if (!$token || !$this->csrfServicio->validarToken((string) $token)) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'Token CSRF inválido o expirado.'], 403);
        }

        return null;
    }

    private function leerEntrada(): array
    {
        $raw = file_get_contents('php://input');
        if (!empty($raw)) {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                return array_merge($_POST, $json);
            }
        }
        return $_POST;
    }

    /**
     * Muestra la vista de configuración del calendario de feriados (GET /configuracion/feriados).
     */
    public function index(): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if ($usuario === null) {
            return Respuesta::redirigir('/login');
        }

        $usuarioId = (int) $usuario->obtenerId();
        if (!$this->autorizacionServicio->puede($usuarioId, 'reclamaciones.gestionar')) {
            return new Respuesta($this->vista->renderizar('errores/403', [
                'titulo' => '403 — Acceso Denegado',
                'mensaje' => 'No cuenta con permisos para gestionar el calendario de feriados.',
                'usuario' => $usuario,
            ]), 403);
        }

        $anioActual = (int) date('Y');
        $feriados = $this->reclamacionServicio->listarFeriados(['anio' => $anioActual]);

        $html = $this->vista->renderizar('configuracion/feriados/index', [
            'titulo' => 'Calendario de Feriados — Camargo PMS',
            'categoriaActiva' => 'configuracion',
            'subcategoriaActiva' => 'config_feriados',
            'migasPan' => [
                ['etiqueta' => 'Panel', 'url' => url_ruta('/'), 'activo' => false],
                ['etiqueta' => 'Configuración', 'url' => url_ruta('/configuracion'), 'activo' => false],
                ['etiqueta' => 'Calendario de Feriados', 'url' => url_ruta('/configuracion/feriados'), 'activo' => true],
            ],
            'usuario' => $usuario,
            'anioActual' => $anioActual,
            'feriados' => $feriados,
            'csrf_token' => $this->csrfServicio->generarToken(),
        ]);

        return new Respuesta($html);
    }

    /**
     * Listado API JSON de feriados por año o filtros (GET /configuracion/feriados/datos).
     */
    public function apiListar(): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if ($usuario === null) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'No autenticado.'], 401);
        }

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'reclamaciones.gestionar')) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'Acceso denegado.'], 403);
        }

        $anio = !empty($_GET['anio']) ? (int) $_GET['anio'] : (int) date('Y');
        $feriados = $this->reclamacionServicio->listarFeriados(['anio' => $anio]);

        return Respuesta::json([
            'exito' => true,
            'datos' => array_map(fn(Feriado $f) => $f->aArreglo(), $feriados),
        ]);
    }

    /**
     * Guarda o edita un feriado en el calendario (POST /configuracion/feriados).
     */
    public function guardar(): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if ($usuario === null) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'No autenticado.'], 401);
        }

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'reclamaciones.gestionar')) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'Acceso denegado.'], 403);
        }

        $datos = $this->leerEntrada();
        if ($errorCsrf = $this->validarCsrf($datos)) {
            return $errorCsrf;
        }

        try {
            $actorId = $this->resolverActorId();
            $feriado = $this->reclamacionServicio->guardarFeriado($datos, $actorId);

            return Respuesta::json([
                'exito' => true,
                'mensaje' => 'Feriado guardado correctamente.',
                'feriado' => $feriado->aArreglo(),
            ]);
        } catch (Throwable $e) {
            error_log("Error en guardar feriado: " . $e->getMessage());
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 422);
        }
    }

    /**
     * Alterna el estado activo de un feriado (POST /configuracion/feriados/{id}/alternar).
     */
    public function alternarEstado(string|int|array $id): Respuesta
    {
        $id = (int) (is_array($id) ? ($id['id'] ?? 0) : $id);
        $usuario = $this->obtenerUsuarioAutenticado();
        if ($usuario === null) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'No autenticado.'], 401);
        }

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'reclamaciones.gestionar')) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'Acceso denegado.'], 403);
        }

        $datos = $this->leerEntrada();
        if ($errorCsrf = $this->validarCsrf($datos)) {
            return $errorCsrf;
        }

        try {
            $actorId = $this->resolverActorId();
            $this->reclamacionServicio->alternarEstadoFeriado($id, $actorId);

            return Respuesta::json([
                'exito' => true,
                'mensaje' => 'Estado de feriado actualizado.',
            ]);
        } catch (Throwable $e) {
            error_log("Error en alternarEstado: " . $e->getMessage());
            return Respuesta::json(['exito' => false, 'mensaje' => 'Error al alternar estado.'], 500);
        }
    }
}
