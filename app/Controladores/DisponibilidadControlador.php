<?php

declare(strict_types=1);

namespace CamargoPMS\Controladores;

use CamargoPMS\Excepciones\BloqueoNoEncontradoExcepcion;
use CamargoPMS\Excepciones\ConflictoDisponibilidadExcepcion;
use CamargoPMS\Excepciones\IntervaloInvalidoExcepcion;
use CamargoPMS\Excepciones\UnidadNoEncontradaExcepcion;
use CamargoPMS\Excepciones\ValidacionExcepcion;
use CamargoPMS\Modelos\BloqueoUnidad;
use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Nucleo\Vista;
use CamargoPMS\Repositorios\PropiedadRepositorio;
use CamargoPMS\Repositorios\TipoUnidadRepositorio;
use CamargoPMS\Servicios\AuditoriaServicio;
use CamargoPMS\Servicios\AutorizacionServicio;
use CamargoPMS\Servicios\CsrfServicio;
use CamargoPMS\Servicios\DisponibilidadServicio;
use CamargoPMS\Servicios\SesionServicio;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * Controlador para la consulta de disponibilidad, gestión de inventario diario y bloqueos.
 *
 * Principios vinculantes:
 * - D-066: INSTANTE ≠ FECHA HOTELERA ≠ HORARIO OPERACIONAL.
 * - D-067: Modelo Híbrido Sparse con UNIQUE(unidad_id, fecha) y transaccionalidad ACID.
 * - ConflictoDisponibilidadExcepcion se traduce a HTTP 409 Conflict.
 * - RBAC: disponibilidad.ver, disponibilidad.bloquear, disponibilidad.liberar.
 */
class DisponibilidadControlador
{
    private DisponibilidadServicio $disponibilidadServicio;
    private PropiedadRepositorio $propiedadRepositorio;
    private TipoUnidadRepositorio $tipoUnidadRepositorio;
    private SesionServicio $sesionServicio;
    private CsrfServicio $csrfServicio;
    private AutorizacionServicio $autorizacionServicio;
    private Vista $vista;

    public function __construct(
        ?DisponibilidadServicio $disponibilidadServicio = null,
        ?PropiedadRepositorio $propiedadRepositorio = null,
        ?TipoUnidadRepositorio $tipoUnidadRepositorio = null,
        ?SesionServicio $sesionServicio = null,
        ?CsrfServicio $csrfServicio = null,
        ?AutorizacionServicio $autorizacionServicio = null,
        ?Vista $vista = null
    ) {
        $this->disponibilidadServicio = $disponibilidadServicio ?? new DisponibilidadServicio();
        $this->propiedadRepositorio = $propiedadRepositorio ?? new PropiedadRepositorio();
        $this->tipoUnidadRepositorio = $tipoUnidadRepositorio ?? new TipoUnidadRepositorio(\CamargoPMS\Nucleo\BaseDatos::conexion());
        $this->sesionServicio = $sesionServicio ?? new SesionServicio();
        $this->csrfServicio = $csrfServicio ?? new CsrfServicio();
        $this->autorizacionServicio = $autorizacionServicio ?? new AutorizacionServicio();
        $this->vista = $vista ?? new Vista();
    }

    /**
     * Renderiza la interfaz principal del motor de disponibilidad (GET /disponibilidad).
     */
    public function index(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $usuarioActualId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioActualId, 'disponibilidad.ver')) {
            return new Respuesta($this->vista->renderizar('errores/error', [
                'titulo' => '403 — Acceso denegado',
                'codigo' => 403,
                'mensaje' => 'No tiene permisos para consultar la disponibilidad del inventario.',
            ], 'error'), 403);
        }

        $capacidades = [
            'puede_ver' => true,
            'puede_bloquear' => $this->autorizacionServicio->puede($usuarioActualId, 'disponibilidad.bloquear'),
            'puede_liberar' => $this->autorizacionServicio->puede($usuarioActualId, 'disponibilidad.liberar'),
        ];

        // Propiedades activas
        $propiedades = $this->propiedadRepositorio->listar(null, 'ACTIVO', null, 100, 0);
        $propiedadesFormateadas = array_map(static function ($p) {
            return [
                'id' => (int) $p->obtenerId(),
                'codigo' => $p->obtenerCodigo(),
                'nombre' => $p->obtenerNombre(),
                'zona_horaria' => $p->obtenerZonaHoraria(),
            ];
        }, $propiedades);

        // Tipos de unidad
        $tiposUnidad = $this->tipoUnidadRepositorio->listarActivos();
        $tiposFormateados = array_map(static function ($t) {
            return [
                'id' => (int) $t->obtenerId(),
                'codigo' => $t->obtenerCodigo(),
                'nombre' => $t->obtenerNombre(),
            ];
        }, $tiposUnidad);

        // Fechas por defecto en zona horaria del PMS
        $tzCentral = $this->disponibilidadServicio->resolverZonaHorariaPropiedad(null);
        $dtHoy = new DateTimeImmutable('now', new DateTimeZone($tzCentral));
        $fechaEntradaDefecto = $dtHoy->format('Y-m-d');
        $fechaSalidaDefecto = $dtHoy->modify('+1 day')->format('Y-m-d');
        $mesDefecto = $dtHoy->format('Y-m');

        $datos = [
            'titulo' => 'Camargo PMS — Motor de Disponibilidad e Inventario',
            'categoriaActiva' => 'propiedades',
            'subcategoriaActiva' => 'disponibilidad_calendario',
            'migasPan' => [
                ['etiqueta' => 'Panel', 'url' => url_ruta('/'), 'activo' => false],
                ['etiqueta' => 'Propiedades', 'url' => url_ruta('/propiedades'), 'activo' => false],
                ['etiqueta' => 'Disponibilidad', 'url' => url_ruta('/disponibilidad'), 'activo' => true],
            ],
            'capacidades' => $capacidades,
            'propiedades' => $propiedadesFormateadas,
            'tiposUnidad' => $tiposFormateados,
            'fechaEntradaDefecto' => $fechaEntradaDefecto,
            'fechaSalidaDefecto' => $fechaSalidaDefecto,
            'mesDefecto' => $mesDefecto,
            'zonaHorariaPMS' => $tzCentral,
            'csrf_token' => $this->csrfServicio->obtenerToken(),
        ];

        $html = $this->vista->renderizar('disponibilidad/index', $datos, 'principal');

        return new Respuesta($html, 200);
    }

    /**
     * Endpoint API para consultar la disponibilidad en un intervalo semiabierto [entrada, salida).
     * GET o POST /disponibilidad/consultar
     */
    public function consultar(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $usuarioActualId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioActualId, 'disponibilidad.ver')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Acceso denegado: permiso requerido disponibilidad.ver.'], 403);
        }

        $params = $_SERVER['REQUEST_METHOD'] === 'POST' ? $this->obtenerCuerpoPeticion() : $_GET;

        $fechaEntrada = isset($params['fecha_entrada']) ? trim((string) $params['fecha_entrada']) : '';
        $fechaSalida = isset($params['fecha_salida']) ? trim((string) $params['fecha_salida']) : '';
        $propiedadId = isset($params['propiedad_id']) && is_numeric($params['propiedad_id']) ? (int) $params['propiedad_id'] : null;
        $tipoUnidadId = isset($params['tipo_unidad_id']) && is_numeric($params['tipo_unidad_id']) ? (int) $params['tipo_unidad_id'] : null;
        $soloDisponibles = !empty($params['solo_disponibles']) && ($params['solo_disponibles'] === true || $params['solo_disponibles'] === '1' || $params['solo_disponibles'] === 'true');

        if ($fechaEntrada === '' || $fechaSalida === '') {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => 'Las fechas de entrada y salida son obligatorias.',
            ], 422);
        }

        try {
            $resultado = $this->disponibilidadServicio->consultarDisponibilidad(
                $fechaEntrada,
                $fechaSalida,
                $propiedadId,
                $tipoUnidadId,
                $soloDisponibles
            );

            return Respuesta::json([
                'ok' => true,
                'datos' => $resultado,
            ], 200);
        } catch (IntervaloInvalidoExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => $e->getMessage(),
                'codigo_error' => 'INTERVALO_INVALIDO',
            ], 422);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => 'Error al consultar disponibilidad: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Aplica un bloqueo de inventario manual o de mantenimiento (POST /disponibilidad/bloquear).
     */
    public function bloquear(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $usuarioActualId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioActualId, 'disponibilidad.bloquear')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Acceso denegado: permiso requerido disponibilidad.bloquear.'], 403);
        }

        $datos = $this->obtenerCuerpoPeticion();

        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Token CSRF inválido o ausente.'], 403);
        }

        $unidadId = isset($datos['unidad_id']) && is_numeric($datos['unidad_id']) ? (int) $datos['unidad_id'] : 0;
        $fechaInicio = isset($datos['fecha_inicio']) ? trim((string) $datos['fecha_inicio']) : '';
        $fechaFin = isset($datos['fecha_fin']) ? trim((string) $datos['fecha_fin']) : '';
        $motivo = isset($datos['motivo']) ? trim((string) $datos['motivo']) : '';
        $tipo = isset($datos['tipo']) ? trim((string) $datos['tipo']) : 'BLOQUEO_MANUAL';

        if ($unidadId <= 0) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Debe seleccionar una unidad válida.'], 422);
        }
        if ($fechaInicio === '' || $fechaFin === '') {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Las fechas de inicio y fin son obligatorias.'], 422);
        }
        if ($motivo === '') {
            return Respuesta::json(['ok' => false, 'mensaje' => 'El motivo del bloqueo es obligatorio.'], 422);
        }

        try {
            $bloqueo = $this->disponibilidadServicio->bloquearUnidad(
                $unidadId,
                $fechaInicio,
                $fechaFin,
                $motivo,
                $tipo,
                $usuarioActualId
            );

            return Respuesta::json([
                'ok' => true,
                'mensaje' => "Bloqueo de inventario aplicado con éxito del {$fechaInicio} al {$fechaFin} ({$bloqueo->obtenerNoches()} noches).",
                'datos' => $bloqueo->aArreglo(),
            ], 201);
        } catch (ConflictoDisponibilidadExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => $e->getMessage(),
                'codigo_error' => 'CONFLICTO_DISPONIBILIDAD',
                'unidad_id' => $e->obtenerUnidadId(),
                'fecha_conflicto' => $e->obtenerFechaConflicto(),
            ], 409);
        } catch (IntervaloInvalidoExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => $e->getMessage(),
                'codigo_error' => 'INTERVALO_INVALIDO',
            ], 422);
        } catch (UnidadNoEncontradaExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => $e->getMessage(),
                'codigo_error' => 'UNIDAD_NO_ENCONTRADA',
            ], 404);
        } catch (ValidacionExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => $e->getMessage(),
                'errores' => $e->obtenerErrores(),
                'codigo_error' => 'VALIDACION_FALLIDA',
            ], 422);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => 'Error inesperado al aplicar el bloqueo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Libera un bloqueo de unidad previamente aplicado (POST /disponibilidad/liberar).
     */
    public function liberar(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $usuarioActualId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioActualId, 'disponibilidad.liberar')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Acceso denegado: permiso requerido disponibilidad.liberar.'], 403);
        }

        $datos = $this->obtenerCuerpoPeticion();

        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Token CSRF inválido o ausente.'], 403);
        }

        $bloqueoId = isset($datos['bloqueo_id']) && is_numeric($datos['bloqueo_id']) ? (int) $datos['bloqueo_id'] : 0;

        if ($bloqueoId <= 0) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Identificador de bloqueo inválido.'], 422);
        }

        try {
            $bloqueoLiberado = $this->disponibilidadServicio->liberarBloqueo($bloqueoId, $usuarioActualId);

            return Respuesta::json([
                'ok' => true,
                'mensaje' => "El bloqueo ID {$bloqueoId} fue liberado exitosamente. Las fechas quedaron nuevamente disponibles.",
                'datos' => $bloqueoLiberado->aArreglo(),
            ], 200);
        } catch (BloqueoNoEncontradoExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => $e->getMessage(),
                'codigo_error' => 'BLOQUEO_NO_ENCONTRADO',
            ], 404);
        } catch (ValidacionExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => $e->getMessage(),
                'codigo_error' => 'VALIDACION_FALLIDA',
            ], 422);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => 'Error inesperado al liberar el bloqueo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retorna el listado paginado de bloqueos de unidad en JSON (GET /disponibilidad/bloqueos).
     */
    public function bloqueosJson(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $usuarioActualId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioActualId, 'disponibilidad.ver')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Acceso denegado: permiso requerido disponibilidad.ver.'], 403);
        }

        $filtros = [
            'propiedad_id' => isset($_GET['propiedad_id']) && is_numeric($_GET['propiedad_id']) ? (int) $_GET['propiedad_id'] : null,
            'unidad_id' => isset($_GET['unidad_id']) && is_numeric($_GET['unidad_id']) ? (int) $_GET['unidad_id'] : null,
            'estado' => isset($_GET['estado']) && in_array(strtoupper(trim((string) $_GET['estado'])), ['ACTIVO', 'LIBERADO'], true)
                ? strtoupper(trim((string) $_GET['estado']))
                : null,
            'tipo' => isset($_GET['tipo']) && in_array(strtoupper(trim((string) $_GET['tipo'])), ['BLOQUEO_MANUAL', 'MANTENIMIENTO'], true)
                ? strtoupper(trim((string) $_GET['tipo']))
                : null,
            'fecha_desde' => isset($_GET['fecha_desde']) ? trim((string) $_GET['fecha_desde']) : null,
            'fecha_hasta' => isset($_GET['fecha_hasta']) ? trim((string) $_GET['fecha_hasta']) : null,
        ];

        $pagina = isset($_GET['pagina']) && is_numeric($_GET['pagina']) ? max(1, (int) $_GET['pagina']) : 1;
        $limite = isset($_GET['limite']) && is_numeric($_GET['limite']) ? max(1, min(100, (int) $_GET['limite'])) : 15;

        try {
            $resultado = $this->disponibilidadServicio->listarBloqueos($filtros, $pagina, $limite);
            $formateados = array_map(static fn(BloqueoUnidad $b) => $b->aArreglo(), $resultado['bloqueos']);

            return Respuesta::json([
                'ok' => true,
                'datos' => $formateados,
                'paginacion' => [
                    'total' => $resultado['total'],
                    'pagina' => $resultado['pagina'],
                    'limite' => $resultado['limite'],
                    'paginas' => $resultado['paginas_totales'],
                ],
            ], 200);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => 'Error al listar los bloqueos: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retorna la matriz de ocupación mensual para una propiedad (GET /disponibilidad/matriz).
     */
    public function matrizJson(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $usuarioActualId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioActualId, 'disponibilidad.ver')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Acceso denegado: permiso requerido disponibilidad.ver.'], 403);
        }

        $propiedadId = isset($_GET['propiedad_id']) && is_numeric($_GET['propiedad_id']) ? (int) $_GET['propiedad_id'] : 0;
        $mes = isset($_GET['mes']) ? trim((string) $_GET['mes']) : date('Y-m');

        if ($propiedadId <= 0) {
            // Intentar tomar la primera propiedad activa
            $primeras = $this->propiedadRepositorio->listar(null, 'ACTIVO', null, 1, 0);
            if (!empty($primeras)) {
                $propiedadId = (int) $primeras[0]->obtenerId();
            } else {
                return Respuesta::json(['ok' => false, 'mensaje' => 'No hay propiedades activas para generar la matriz.'], 422);
            }
        }

        try {
            $matriz = $this->disponibilidadServicio->obtenerMatrizCalendario($propiedadId, $mes);

            return Respuesta::json([
                'ok' => true,
                'datos' => $matriz,
            ], 200);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => 'Error al generar la matriz de disponibilidad: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Extrae los datos del cuerpo de la petición ya sea form-data o JSON crudo.
     *
     * @return array<string, mixed>
     */
    private function obtenerCuerpoPeticion(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            $contenido = file_get_contents('php://input');
            $decodificado = json_decode($contenido, true);
            if (is_array($decodificado)) {
                return $decodificado;
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
