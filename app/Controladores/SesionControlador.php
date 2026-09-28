<?php

declare(strict_types=1);

namespace CamargoPMS\Controladores;

use CamargoPMS\Excepciones\EntidadNoEncontradaExcepcion;
use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Nucleo\Vista;
use CamargoPMS\Servicios\CsrfServicio;
use CamargoPMS\Servicios\SesionServicio;
use Throwable;

/**
 * Controlador para la supervisión analítica de sesiones de usuario,
 * monitoreo de concurrencia y revocación administrativa (D-088 / SESIONES-1).
 */
class SesionControlador
{
    private SesionServicio $sesionServicio;
    private CsrfServicio $csrfServicio;

    public function __construct(
        ?SesionServicio $sesionServicio = null,
        ?CsrfServicio $csrfServicio = null
    ) {
        $this->sesionServicio = $sesionServicio ?? new SesionServicio();
        $this->csrfServicio = $csrfServicio ?? new CsrfServicio();
    }

    /**
     * Muestra la consola principal de monitoreo de sesiones (GET /seguridad/sesiones).
     *
     * @return Respuesta
     */
    public function mostrarConsolaSesiones(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $csrfToken = $this->csrfServicio->generarToken();
        $metricas = $this->sesionServicio->obtenerResumenMetricas();

        $vista = new Vista();
        $html = $vista->renderizar('seguridad/sesiones', [
            'titulo' => 'Monitor de Sesiones y Concurrencia — Camargo PMS',
            'usuario_actual' => $usuarioActual ? $usuarioActual->aArreglo() : null,
            'metricas_iniciales' => $metricas,
            'csrf_token' => $csrfToken,
            'minutos_inactividad' => $this->sesionServicio->obtenerMinutosInactividad(),
            'horas_duracion_maxima' => $this->sesionServicio->obtenerHorasDuracionMaxima(),
        ]);

        return new Respuesta($html, 200);
    }

    /**
     * Endpoint API para consultar el listado paginado y filtrado de sesiones (GET /api/seguridad/sesiones).
     *
     * @return Respuesta
     */
    public function apiListarSesiones(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $pagina = isset($_GET['pagina']) && is_numeric($_GET['pagina']) ? max(1, (int) $_GET['pagina']) : 1;
        $limite = isset($_GET['limite']) && is_numeric($_GET['limite']) ? max(1, min(100, (int) $_GET['limite'])) : 20;

        $filtros = [];
        if (!empty($_GET['estado'])) {
            $filtros['estado'] = trim((string) $_GET['estado']);
        }
        if (!empty($_GET['presencia'])) {
            $filtros['presencia'] = trim((string) $_GET['presencia']);
        }
        if (!empty($_GET['busqueda'])) {
            $filtros['busqueda'] = trim((string) $_GET['busqueda']);
        }
        if (!empty($_GET['usuario_id']) && is_numeric($_GET['usuario_id'])) {
            $filtros['usuario_id'] = (int) $_GET['usuario_id'];
        }

        try {
            $datos = $this->sesionServicio->listarSesionesGlobales($filtros, $limite, $pagina);

            return Respuesta::json([
                'ok' => true,
                'exito' => true,
                'datos' => $datos,
            ], 200);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Error al recuperar listado de sesiones: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Endpoint API para obtener métricas de concurrencia en tiempo real (GET /api/seguridad/sesiones/metricas).
     *
     * @return Respuesta
     */
    public function apiMetricas(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        try {
            $metricas = $this->sesionServicio->obtenerResumenMetricas();

            return Respuesta::json([
                'ok' => true,
                'exito' => true,
                'datos' => $metricas,
            ], 200);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Error al consultar métricas de sesiones: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Endpoint API para revocar administrativamente una sesión concreta (POST /api/seguridad/sesiones/{id}/revocar).
     *
     * @param string|int $id
     * @return Respuesta
     */
    public function apiRevocarSesion(string|int $id): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $sesionId = (int) $id;
        if ($sesionId <= 0) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Identificador de sesión inválido.',
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
                'error' => 'La sesión ya no es válida.',
                'codigo' => 'SESION_NO_VALIDA',
            ], 401);
        }

        $motivo = isset($payload['motivo']) && is_string($payload['motivo']) && trim($payload['motivo']) !== ''
            ? trim($payload['motivo'])
            : 'REVOCACION_ADMINISTRATIVA';

        try {
            $resultado = $this->sesionServicio->revocarSesionAdministrativa(
                $sesionId,
                (int) $usuarioActual->obtenerId(),
                $motivo
            );

            return Respuesta::json([
                'ok' => true,
                'exito' => true,
                'mensaje' => $resultado['mensaje'],
                'datos' => [
                    'sesion_id' => $sesionId,
                    'es_sesion_actual' => $resultado['es_sesion_actual'],
                    'ya_revocada' => $resultado['ya_revocada'],
                ],
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
                'error' => 'Error al revocar sesión: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Endpoint API para revocar todas las sesiones activas de un usuario (POST /api/seguridad/sesiones/usuario/{usuarioId}/revocar-todas).
     *
     * @param string|int $usuarioId
     * @return Respuesta
     */
    public function apiRevocarUsuario(string|int $usuarioId): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $uid = (int) $usuarioId;
        if ($uid <= 0) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Identificador de usuario inválido.',
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
                'error' => 'La sesión ya no es válida.',
                'codigo' => 'SESION_NO_VALIDA',
            ], 401);
        }

        $motivo = isset($payload['motivo']) && is_string($payload['motivo']) && trim($payload['motivo']) !== ''
            ? trim($payload['motivo'])
            : 'REVOCACION_ADMINISTRATIVA';

        try {
            $resultado = $this->sesionServicio->revocarTodasDeUsuarioAdministrativa(
                $uid,
                (int) $usuarioActual->obtenerId(),
                $motivo
            );

            return Respuesta::json([
                'ok' => true,
                'exito' => true,
                'mensaje' => $resultado['mensaje'],
                'datos' => [
                    'usuario_id' => $uid,
                    'es_sesion_actual' => $resultado['es_sesion_actual'],
                    'sesiones_revocadas' => $resultado['sesiones_revocadas'],
                ],
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
                'error' => 'Error al revocar sesiones del usuario: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Endpoint API para purgar/marcar sesiones expiradas en lote (POST /api/seguridad/sesiones/purgar-expiradas).
     *
     * @return Respuesta
     */
    public function apiPurgarExpiradas(): Respuesta
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
                'error' => 'La sesión ya no es válida.',
                'codigo' => 'SESION_NO_VALIDA',
            ], 401);
        }

        try {
            $totalPurgadas = $this->sesionServicio->purgarExpiradasLote();

            return Respuesta::json([
                'ok' => true,
                'exito' => true,
                'mensaje' => "Se actualizaron {$totalPurgadas} sesiones expiradas exitosamente.",
                'datos' => [
                    'total_purgadas' => $totalPurgadas,
                ],
            ], 200);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Error al purgar sesiones expiradas: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtiene el payload de la petición parseando JSON o $_POST según corresponda.
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
