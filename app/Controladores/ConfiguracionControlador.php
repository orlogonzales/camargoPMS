<?php

declare(strict_types=1);

namespace CamargoPMS\Controladores;

use CamargoPMS\Excepciones\ConfiguracionNoEditableExcepcion;
use CamargoPMS\Excepciones\ConfiguracionNoEncontradaExcepcion;
use CamargoPMS\Excepciones\ValidacionExcepcion;
use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Nucleo\Vista;
use CamargoPMS\Servicios\AutorizacionServicio;
use CamargoPMS\Servicios\ConfiguracionServicio;
use CamargoPMS\Servicios\CsrfServicio;
use CamargoPMS\Servicios\SesionServicio;
use Throwable;

/**
 * Controlador administrativo y de endpoints JSON para la gestión de la configuración general y
 * parámetros funcionales del sistema (CONFIGURACIÓN-1).
 */
class ConfiguracionControlador
{
    private ConfiguracionServicio $configServicio;
    private SesionServicio $sesionServicio;
    private CsrfServicio $csrfServicio;
    private AutorizacionServicio $autorizacionServicio;
    private Vista $vista;

    public function __construct(
        ?ConfiguracionServicio $configServicio = null,
        ?SesionServicio $sesionServicio = null,
        ?CsrfServicio $csrfServicio = null,
        ?AutorizacionServicio $autorizacionServicio = null,
        ?Vista $vista = null
    ) {
        $this->configServicio = $configServicio ?? new ConfiguracionServicio();
        $this->sesionServicio = $sesionServicio ?? new SesionServicio();
        $this->csrfServicio = $csrfServicio ?? new CsrfServicio();
        $this->autorizacionServicio = $autorizacionServicio ?? new AutorizacionServicio();
        $this->vista = $vista ?? new Vista();
    }

    /**
     * Renderiza la página principal de configuración del sistema (GET /configuracion/sistema).
     *
     * @return Respuesta
     */
    public function index(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $usuarioActualId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : 0;

        $capacidades = [
            'puede_editar' => $this->autorizacionServicio->puede($usuarioActualId, 'configuracion.editar'),
        ];

        $agrupadasRaw = $this->configServicio->listarAgrupadas();
        $agrupadas = [];
        foreach ($agrupadasRaw as $grupo => $parametros) {
            $agrupadas[$grupo] = array_map(static fn($p) => $p->aArreglo(), $parametros);
        }

        $datos = [
            'titulo' => 'Camargo PMS — Configuración General del Sistema',
            'categoriaActiva' => 'configuracion',
            'migasPan' => [
                ['etiqueta' => 'Panel', 'url' => url_ruta('/'), 'activo' => false],
                ['etiqueta' => 'Configuración', 'url' => '#', 'activo' => false],
                ['etiqueta' => 'Configuración General', 'url' => url_ruta('/configuracion/sistema'), 'activo' => true],
            ],
            'capacidades' => $capacidades,
            'agrupadas' => $agrupadas,
            'csrf_token' => $this->csrfServicio->obtenerToken(),
        ];

        $html = $this->vista->renderizar('configuracion/sistema/index', $datos, 'principal');

        return new Respuesta($html, 200);
    }

    /**
     * Retorna el catálogo completo de parámetros agrupados por sección en formato JSON (GET /configuracion/sistema/datos).
     *
     * @return Respuesta
     */
    public function datosJson(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        try {
            $agrupadasRaw = $this->configServicio->listarAgrupadas();
            $agrupadas = [];
            foreach ($agrupadasRaw as $grupo => $parametros) {
                $agrupadas[$grupo] = array_map(static fn($p) => $p->aArreglo(), $parametros);
            }

            return Respuesta::json([
                'ok' => true,
                'exito' => true,
                'datos' => $agrupadas,
            ], 200);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Error al recuperar parámetros de configuración: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Guarda o actualiza parámetros de configuración funcional (POST/PUT /configuracion/sistema).
     * Soporta tanto actualización por lote (batch) como parámetro individual.
     *
     * @return Respuesta
     */
    public function guardar(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $payload = $this->obtenerPayload();
        if (!$this->validarCsrf($payload)) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => 'Token CSRF inválido o ausente.'], 403);
        }

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $ejecutorId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : null;

        // Determinar si es lote o parámetro individual
        $valoresAActualizar = [];
        if (isset($payload['configuraciones']) && is_array($payload['configuraciones'])) {
            $valoresAActualizar = $payload['configuraciones'];
        } elseif (isset($payload['clave'])) {
            $valoresAActualizar[(string) $payload['clave']] = $payload['valor'] ?? null;
        } else {
            // Filtrar claves que no son de control CSRF
            foreach ($payload as $k => $v) {
                if (!in_array($k, ['_csrf_token', 'csrf_token', '_token', '_method'], true)) {
                    $valoresAActualizar[$k] = $v;
                }
            }
        }

        if (empty($valoresAActualizar)) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'No se proporcionaron parámetros para actualizar.',
            ], 400);
        }

        try {
            $resultado = $this->configServicio->actualizarMultiples($valoresAActualizar, $ejecutorId);

            return Respuesta::json([
                'ok' => true,
                'exito' => true,
                'mensaje' => 'Configuración actualizada exitosamente.',
                'datos' => $resultado,
            ], 200);
        } catch (ConfiguracionNoEncontradaExcepcion $e) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => $e->getMessage()], 404);
        } catch (ConfiguracionNoEditableExcepcion $e) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => $e->getMessage()], 422);
        } catch (ValidacionExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => $e->getMessage(),
                'errores' => $e->obtenerErrores(),
            ], 422);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Error interno al actualizar la configuración: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Restaura un parámetro al valor predeterminado de fábrica (POST /configuracion/sistema/restaurar).
     *
     * @param string|null $clave
     * @return Respuesta
     */
    public function restaurar(?string $clave = null): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $payload = $this->obtenerPayload();
        if (!$this->validarCsrf($payload)) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => 'Token CSRF inválido o ausente.'], 403);
        }

        $claveFinal = $clave ?? ($payload['clave'] ?? null);
        if ($claveFinal === null || trim((string) $claveFinal) === '') {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'La clave de configuración es obligatoria para restaurar.',
            ], 400);
        }

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $ejecutorId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : null;

        try {
            $this->configServicio->restaurarPredeterminado((string) $claveFinal, $ejecutorId);

            $parametro = $this->configServicio->obtenerParametro((string) $claveFinal);

            return Respuesta::json([
                'ok' => true,
                'exito' => true,
                'mensaje' => "El parámetro '{$claveFinal}' ha sido restaurado a su valor predeterminado.",
                'datos' => $parametro ? $parametro->aArreglo() : null,
            ], 200);
        } catch (ConfiguracionNoEncontradaExcepcion $e) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => $e->getMessage()], 404);
        } catch (ConfiguracionNoEditableExcepcion $e) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Error interno al restaurar parámetro: ' . $e->getMessage(),
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
