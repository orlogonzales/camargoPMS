<?php

declare(strict_types=1);

namespace CamargoPMS\Controladores;

use CamargoPMS\Excepciones\EntidadNoEncontradaExcepcion;
use CamargoPMS\Excepciones\OperacionInvalidaExcepcion;
use CamargoPMS\Modelos\BitacoraEntrada;
use CamargoPMS\Modelos\BitacoraSeguimiento;
use CamargoPMS\Modelos\Usuario;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Nucleo\Vista;
use CamargoPMS\Servicios\BitacoraServicio;
use CamargoPMS\Servicios\CsrfServicio;
use CamargoPMS\Servicios\SesionServicio;
use InvalidArgumentException;
use PDO;
use Throwable;

/**
 * Controlador HTTP y API para el Libro de Guardia y Bitácora Operacional (D-089 / BITÁCORA-1).
 *
 * Expone la interfaz visual Alina y endpoints JSON protegidos para la gestión de
 * novedades, consignas de turno, seguimientos append-only, resoluciones y anulaciones supervisadas.
 */
class BitacoraControlador
{
    private PDO $pdo;
    private BitacoraServicio $bitacoraServicio;
    private CsrfServicio $csrfServicio;
    private SesionServicio $sesionServicio;
    private Vista $vista;

    public function __construct(
        ?BitacoraServicio $bitacoraServicio = null,
        ?CsrfServicio $csrfServicio = null,
        ?SesionServicio $sesionServicio = null,
        ?Vista $vista = null,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->bitacoraServicio = $bitacoraServicio ?? new BitacoraServicio($this->pdo);
        $this->csrfServicio = $csrfServicio ?? new CsrfServicio();
        $this->sesionServicio = $sesionServicio ?? new SesionServicio($this->pdo);
        $this->vista = $vista ?? new Vista();
    }

    /**
     * Renderiza la consola operativa del Libro de Guardia (GET /operaciones/bitacora).
     */
    public function index(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $csrfToken = $this->csrfServicio->generarToken();

        // Cargar catálogo de propiedades activas
        $stmtP = $this->pdo->query('SELECT id, nombre, codigo FROM propiedades WHERE estado = "ACTIVO" ORDER BY nombre ASC');
        $propiedades = $stmtP->fetchAll(PDO::FETCH_ASSOC);

        // Cargar catálogo de unidades físicas
        $stmtU = $this->pdo->query('SELECT id, propiedad_id, codigo, nombre FROM unidades WHERE estado = "ACTIVO" ORDER BY codigo ASC');
        $unidades = $stmtU->fetchAll(PDO::FETCH_ASSOC);

        $metricas = $this->bitacoraServicio->obtenerMetricas();

        $html = $this->vista->renderizar('operaciones/bitacora', [
            'titulo' => 'Libro de Guardia y Bitácora — Camargo PMS',
            'usuario_actual' => $usuarioActual ? $usuarioActual->aArreglo() : null,
            'csrf_token' => $csrfToken,
            'propiedades' => $propiedades,
            'unidades' => $unidades,
            'metricas_iniciales' => $metricas,
            'tipos' => BitacoraEntrada::TIPOS_VALIDOS,
            'prioridades' => BitacoraEntrada::PRIORIDADES_VALIDAS,
            'turnos' => BitacoraEntrada::TURNOS_VALIDOS,
            'estados' => BitacoraEntrada::ESTADOS_VALIDOS,
        ]);

        return new Respuesta($html, 200);
    }

    /**
     * Endpoint API para consultar el feed filtrado y paginado (GET /api/operaciones/bitacora).
     */
    public function apiListar(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $pagina = isset($_GET['pagina']) && is_numeric($_GET['pagina']) ? max(1, (int) $_GET['pagina']) : 1;
        $limite = isset($_GET['limite']) && is_numeric($_GET['limite']) ? max(1, min(100, (int) $_GET['limite'])) : 20;

        $filtros = [];
        if (!empty($_GET['propiedad_id']) && is_numeric($_GET['propiedad_id'])) {
            $filtros['propiedad_id'] = (int) $_GET['propiedad_id'];
        }
        if (!empty($_GET['tipo'])) {
            $filtros['tipo'] = trim((string) $_GET['tipo']);
        }
        if (!empty($_GET['prioridad'])) {
            $filtros['prioridad'] = trim((string) $_GET['prioridad']);
        }
        if (!empty($_GET['turno'])) {
            $filtros['turno'] = trim((string) $_GET['turno']);
        }
        if (!empty($_GET['estado'])) {
            $filtros['estado'] = trim((string) $_GET['estado']);
        }
        if (!empty($_GET['unidad_id']) && is_numeric($_GET['unidad_id'])) {
            $filtros['unidad_id'] = (int) $_GET['unidad_id'];
        }
        if (!empty($_GET['fecha_operativa'])) {
            $filtros['fecha_operativa'] = trim((string) $_GET['fecha_operativa']);
        }
        if (!empty($_GET['fecha_desde'])) {
            $filtros['fecha_desde'] = trim((string) $_GET['fecha_desde']);
        }
        if (!empty($_GET['fecha_hasta'])) {
            $filtros['fecha_hasta'] = trim((string) $_GET['fecha_hasta']);
        }
        if (!empty($_GET['busqueda'])) {
            $filtros['busqueda'] = trim((string) $_GET['busqueda']);
        }
        if (isset($_GET['no_anuladas']) && $_GET['no_anuladas'] === '1') {
            $filtros['no_anuladas'] = true;
        }

        try {
            $resultado = $this->bitacoraServicio->listarEntradas($filtros, $pagina, $limite);
            $entradasArray = array_map(
                static fn(BitacoraEntrada $e) => $e->toArray(),
                $resultado['entradas']
            );

            $propiedadIdMetricas = !empty($filtros['propiedad_id']) ? (int) $filtros['propiedad_id'] : 0;
            $fechaMetricas = !empty($filtros['fecha_operativa']) ? (string) $filtros['fecha_operativa'] : null;
            $metricas = $this->bitacoraServicio->obtenerMetricas($propiedadIdMetricas, $fechaMetricas);

            return Respuesta::json([
                'ok' => true,
                'exito' => true,
                'datos' => [
                    'entradas' => $entradasArray,
                    'total' => $resultado['total'],
                    'pagina' => $resultado['pagina'],
                    'por_pagina' => $resultado['por_pagina'],
                    'total_paginas' => $resultado['total_paginas'],
                ],
                'metricas' => $metricas,
            ], 200);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Error al recuperar entradas de bitácora: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Endpoint API para consultar el detalle de una entrada con sus seguimientos (GET /api/operaciones/bitacora/{id}).
     */
    public function apiObtenerDetalle(string|int $id): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $entradaId = (int) $id;
        if ($entradaId <= 0) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Identificador de entrada no válido.',
            ], 400);
        }

        try {
            $entrada = $this->bitacoraServicio->obtenerEntrada($entradaId, true);

            return Respuesta::json([
                'ok' => true,
                'exito' => true,
                'datos' => $entrada->toArray(),
            ], 200);
        } catch (EntidadNoEncontradaExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => $e->getMessage(),
            ], 404);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Error al obtener detalle de la entrada: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Endpoint API para registrar una nueva entrada u orden de guardia (POST /api/operaciones/bitacora).
     */
    public function apiCrear(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $payload = $this->obtenerPayload();
        if (!$this->validarCsrf($payload)) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Token CSRF inválido o ausente.',
            ], 403);
        }

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        if ($usuarioActual === null) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'La sesión no es válida.',
            ], 401);
        }

        try {
            $usuarioId = (int) $usuarioActual->obtenerId();
            $nuevaEntrada = $this->bitacoraServicio->registrarEntrada($payload, $usuarioId);

            return Respuesta::json([
                'ok' => true,
                'exito' => true,
                'mensaje' => 'Novedad de bitácora registrada correctamente.',
                'datos' => $nuevaEntrada->toArray(),
            ], 201);
        } catch (InvalidArgumentException $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Error al registrar entrada en bitácora: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Endpoint API para añadir un seguimiento, nota o enmienda (POST /api/operaciones/bitacora/{id}/seguimiento).
     */
    public function apiAgregarSeguimiento(string|int $id): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $entradaId = (int) $id;
        if ($entradaId <= 0) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Identificador de entrada no válido.',
            ], 400);
        }

        $payload = $this->obtenerPayload();
        if (!$this->validarCsrf($payload)) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Token CSRF inválido o ausente.',
            ], 403);
        }

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        if ($usuarioActual === null) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'La sesión no es válida.',
            ], 401);
        }

        $contenido = trim((string) ($payload['contenido'] ?? ''));
        $tipoEvento = trim((string) ($payload['tipo_evento'] ?? BitacoraSeguimiento::TIPO_COMENTARIO));

        try {
            $usuarioId = (int) $usuarioActual->obtenerId();
            $seguimiento = $this->bitacoraServicio->agregarSeguimiento($entradaId, $usuarioId, $contenido, $tipoEvento);

            return Respuesta::json([
                'ok' => true,
                'exito' => true,
                'mensaje' => 'Seguimiento registrado exitosamente.',
                'datos' => $seguimiento->toArray(),
            ], 201);
        } catch (EntidadNoEncontradaExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => $e->getMessage(),
            ], 404);
        } catch (OperacionInvalidaExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => $e->getMessage(),
            ], 409);
        } catch (InvalidArgumentException $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Error al agregar seguimiento: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Endpoint API para cambiar el estado operativo (POST /api/operaciones/bitacora/{id}/estado).
     */
    public function apiCambiarEstado(string|int $id): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $entradaId = (int) $id;
        if ($entradaId <= 0) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Identificador de entrada no válido.',
            ], 400);
        }

        $payload = $this->obtenerPayload();
        if (!$this->validarCsrf($payload)) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Token CSRF inválido o ausente.',
            ], 403);
        }

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        if ($usuarioActual === null) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'La sesión no es válida.',
            ], 401);
        }

        $nuevoEstado = trim((string) ($payload['nuevo_estado'] ?? ''));
        $nota = isset($payload['nota']) ? trim((string) $payload['nota']) : null;

        try {
            $usuarioId = (int) $usuarioActual->obtenerId();
            $entradaActualizada = $this->bitacoraServicio->cambiarEstado($entradaId, $usuarioId, $nuevoEstado, $nota);

            return Respuesta::json([
                'ok' => true,
                'exito' => true,
                'mensaje' => "Estado actualizado a {$nuevoEstado}.",
                'datos' => $entradaActualizada->toArray(),
            ], 200);
        } catch (EntidadNoEncontradaExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => $e->getMessage(),
            ], 404);
        } catch (OperacionInvalidaExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => $e->getMessage(),
            ], 409);
        } catch (InvalidArgumentException $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Error al cambiar estado: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Endpoint API para resolver una consigna o incidencia (POST /api/operaciones/bitacora/{id}/resolver).
     */
    public function apiResolver(string|int $id): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $entradaId = (int) $id;
        if ($entradaId <= 0) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Identificador de entrada no válido.',
            ], 400);
        }

        $payload = $this->obtenerPayload();
        if (!$this->validarCsrf($payload)) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Token CSRF inválido o ausente.',
            ], 403);
        }

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        if ($usuarioActual === null) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'La sesión no es válida.',
            ], 401);
        }

        $notaResolucion = trim((string) ($payload['nota_resolucion'] ?? ''));

        try {
            $usuarioId = (int) $usuarioActual->obtenerId();
            $entradaResuelta = $this->bitacoraServicio->resolverEntrada($entradaId, $usuarioId, $notaResolucion);

            return Respuesta::json([
                'ok' => true,
                'exito' => true,
                'mensaje' => 'La entrada ha sido marcada como RESUELTA con éxito.',
                'datos' => $entradaResuelta->toArray(),
            ], 200);
        } catch (EntidadNoEncontradaExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => $e->getMessage(),
            ], 404);
        } catch (OperacionInvalidaExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => $e->getMessage(),
            ], 409);
        } catch (InvalidArgumentException $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Error al resolver entrada: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Endpoint API para reabrir una entrada previamente resuelta (POST /api/operaciones/bitacora/{id}/reabrir).
     */
    public function apiReabrir(string|int $id): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $entradaId = (int) $id;
        if ($entradaId <= 0) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Identificador de entrada no válido.',
            ], 400);
        }

        $payload = $this->obtenerPayload();
        if (!$this->validarCsrf($payload)) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Token CSRF inválido o ausente.',
            ], 403);
        }

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        if ($usuarioActual === null) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'La sesión no es válida.',
            ], 401);
        }

        $motivoReapertura = trim((string) ($payload['motivo_reapertura'] ?? ''));

        try {
            $usuarioId = (int) $usuarioActual->obtenerId();
            $entradaReabierta = $this->bitacoraServicio->reabrirEntrada($entradaId, $usuarioId, $motivoReapertura);

            return Respuesta::json([
                'ok' => true,
                'exito' => true,
                'mensaje' => 'La entrada ha sido reabierta operativamente.',
                'datos' => $entradaReabierta->toArray(),
            ], 200);
        } catch (EntidadNoEncontradaExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => $e->getMessage(),
            ], 404);
        } catch (OperacionInvalidaExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => $e->getMessage(),
            ], 409);
        } catch (InvalidArgumentException $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Error al reabrir entrada: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Endpoint API para anular con supervisión una entrada (POST /api/operaciones/bitacora/{id}/anular).
     * ANULAR != DELETE.
     */
    public function apiAnular(string|int $id): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $entradaId = (int) $id;
        if ($entradaId <= 0) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Identificador de entrada no válido.',
            ], 400);
        }

        $payload = $this->obtenerPayload();
        if (!$this->validarCsrf($payload)) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Token CSRF inválido o ausente.',
            ], 403);
        }

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        if ($usuarioActual === null) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'La sesión no es válida.',
            ], 401);
        }

        $motivoAnulacion = trim((string) ($payload['motivo_anulacion'] ?? ''));

        try {
            $usuarioId = (int) $usuarioActual->obtenerId();
            $entradaAnulada = $this->bitacoraServicio->anularEntrada($entradaId, $usuarioId, $motivoAnulacion);

            return Respuesta::json([
                'ok' => true,
                'exito' => true,
                'mensaje' => 'La entrada ha sido anulada con trazabilidad histórica.',
                'datos' => $entradaAnulada->toArray(),
            ], 200);
        } catch (EntidadNoEncontradaExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => $e->getMessage(),
            ], 404);
        } catch (OperacionInvalidaExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => $e->getMessage(),
            ], 409);
        } catch (InvalidArgumentException $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Error al anular entrada: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Endpoint API para consultar métricas de guardia (GET /api/operaciones/bitacora/metricas).
     */
    public function apiMetricas(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        try {
            $propiedadId = isset($_GET['propiedad_id']) && is_numeric($_GET['propiedad_id']) ? (int) $_GET['propiedad_id'] : 0;
            $fecha = !empty($_GET['fecha_operativa']) ? trim((string) $_GET['fecha_operativa']) : null;

            $metricas = $this->bitacoraServicio->obtenerMetricas($propiedadId, $fecha);

            return Respuesta::json([
                'ok' => true,
                'exito' => true,
                'datos' => $metricas,
            ], 200);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Error al consultar métricas de bitácora: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtiene el payload parseando JSON o $_POST según corresponda.
     *
     * @return array<string, mixed>
     */
    private function obtenerPayload(): array
    {
        $tipoContenido = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($tipoContenido, 'application/json')) {
            $cuerpo = file_get_contents('php://input');
            if ($cuerpo !== false && trim($cuerpo) !== '') {
                $decodificado = json_decode($cuerpo, true);
                if (is_array($decodificado)) {
                    return $decodificado;
                }
            }
        }

        return $_POST;
    }

    /**
     * Valida el token CSRF presente en el payload o cabeceras HTTP.
     *
     * @param array<string, mixed> $payload
     * @return bool
     */
    private function validarCsrf(array $payload): bool
    {
        $token = $payload['_csrf_token']
            ?? $payload['csrf_token']
            ?? $payload['_token']
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? $_SERVER['HTTP_X_XSRF_TOKEN']
            ?? null;

        if ($token === null || !is_string($token) || trim($token) === '') {
            return false;
        }

        return $this->csrfServicio->validarToken($token);
    }
}
