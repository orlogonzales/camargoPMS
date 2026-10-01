<?php

declare(strict_types=1);

namespace CamargoPMS\Controladores;

use CamargoPMS\Excepciones\CierreHoteleroInvalidoExcepcion;
use CamargoPMS\Excepciones\DevengoDuplicadoExcepcion;
use CamargoPMS\Excepciones\EntidadNoEncontradaExcepcion;
use CamargoPMS\Excepciones\OperacionInvalidaExcepcion;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Nucleo\Vista;
use CamargoPMS\Servicios\CsrfServicio;
use CamargoPMS\Servicios\DevengoServicio;
use CamargoPMS\Servicios\NightAuditServicio;
use CamargoPMS\Servicios\SesionServicio;
use PDO;
use Throwable;

/**
 * Controlador HTTP y API para la Auditoría Nocturna (Night Audit) y Devengos de Alojamiento (D-090).
 */
class NightAuditControlador
{
    private PDO $pdo;
    private NightAuditServicio $nightAuditServicio;
    private DevengoServicio $devengoServicio;
    private CsrfServicio $csrfServicio;
    private SesionServicio $sesionServicio;
    private Vista $vista;

    public function __construct(
        ?NightAuditServicio $nightAuditServicio = null,
        ?DevengoServicio $devengoServicio = null,
        ?CsrfServicio $csrfServicio = null,
        ?SesionServicio $sesionServicio = null,
        ?Vista $vista = null,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->nightAuditServicio = $nightAuditServicio ?? new NightAuditServicio($this->pdo);
        $this->devengoServicio = $devengoServicio ?? new DevengoServicio($this->pdo);
        $this->csrfServicio = $csrfServicio ?? new CsrfServicio();
        $this->sesionServicio = $sesionServicio ?? new SesionServicio($this->pdo);
        $this->vista = $vista ?? new Vista();
    }

    /**
     * Renderiza la consola operativa de Auditoría Nocturna (GET /operaciones/night-audit).
     */
    public function index(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $csrfToken = $this->csrfServicio->generarToken();

        // Cargar catálogo de propiedades activas
        $stmtPropiedades = $this->pdo->query('SELECT id, nombre, codigo FROM propiedades WHERE estado = "ACTIVO" ORDER BY id ASC');
        $propiedades = $stmtPropiedades->fetchAll(PDO::FETCH_ASSOC);

        $propiedadSeleccionadaId = !empty($propiedades) ? (int) $propiedades[0]['id'] : 1;

        // Cargar cierres recientes
        $cierres = $this->nightAuditServicio->listarCierres($propiedadSeleccionadaId, 15);
        $ultimoCierre = $this->nightAuditServicio->obtenerUltimoCierre($propiedadSeleccionadaId);

        $tz = $this->nightAuditServicio->resolverZonaHorariaPropiedad($propiedadSeleccionadaId);
        $ayer = (new \DateTimeImmutable('now', new \DateTimeZone($tz)))->modify('-1 day')->format('Y-m-d');

        $fechaSiguienteSugerida = $ultimoCierre !== null
            ? date('Y-m-d', strtotime($ultimoCierre->obtenerFechaHotelera() . ' +1 day'))
            : $ayer;

        if ($fechaSiguienteSugerida > $ayer) {
            $fechaSiguienteSugerida = $ayer;
        }

        $contenido = $this->vista->renderizar('operaciones/night_audit', [
            'titulo' => 'Auditoría Nocturna — Camargo PMS',
            'usuario_actual' => $usuarioActual ? $usuarioActual->aArreglo() : null,
            'csrf_token' => $csrfToken,
            'propiedades' => $propiedades,
            'propiedad_id' => $propiedadSeleccionadaId,
            'cierres' => array_map(fn($c) => $c->aArray(), $cierres),
            'ultimo_cierre' => $ultimoCierre ? $ultimoCierre->aArray() : null,
            'fecha_sugerida' => $fechaSiguienteSugerida,
            'migasPan' => [
                ['etiqueta' => 'Operaciones', 'url' => '/operaciones'],
                ['etiqueta' => 'Auditoría Nocturna', 'activo' => true],
            ],
            'categoriaActiva' => 'operaciones',
        ]);

        return new Respuesta($contenido, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /**
     * Ejecuta el cierre de fecha hotelera (POST /api/operaciones/night-audit/ejecutar).
     */
    public function ejecutar(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        if ($usuarioActual === null) {
            return Respuesta::json(['error' => 'No autenticado'], 401);
        }

        $input = $this->obtenerPayload();
        if (!$this->validarCsrf($input)) {
            return Respuesta::json(['error' => 'Token CSRF inválido o expirado'], 403);
        }

        $propiedadId = (int) ($input['propiedad_id'] ?? 0);
        $fechaHotelera = trim((string) ($input['fecha_hotelera'] ?? ''));
        $observaciones = isset($input['observaciones']) && trim((string) $input['observaciones']) !== ''
            ? trim((string) $input['observaciones'])
            : null;
        $timezone = isset($input['timezone']) && trim((string) $input['timezone']) !== ''
            ? trim((string) $input['timezone'])
            : null;

        if ($propiedadId <= 0 || $fechaHotelera === '') {
            return Respuesta::json(['error' => 'La propiedad y la fecha hotelera son obligatorias.'], 422);
        }

        try {
            $cierre = $this->nightAuditServicio->ejecutarCierre(
                $propiedadId,
                $fechaHotelera,
                (int) $usuarioActual->obtenerId(),
                $observaciones,
                $timezone,
                true,
                false
            );

            return Respuesta::json([
                'mensaje' => "Cierre de fecha hotelera {$fechaHotelera} completado con éxito.",
                'cierre' => $cierre->aArray(),
            ], 200);
        } catch (CierreHoteleroInvalidoExcepcion $e) {
            return Respuesta::json(['error' => $e->getMessage()], 422);
        } catch (DevengoDuplicadoExcepcion $e) {
            return Respuesta::json(['error' => $e->getMessage()], 409);
        } catch (Throwable $e) {
            return Respuesta::json(['error' => 'Error interno al procesar el cierre: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Consulta el cierre de una fecha específica (GET /api/operaciones/night-audit/cierre).
     */
    public function consultarCierre(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        if ($usuarioActual === null) {
            return Respuesta::json(['error' => 'No autenticado'], 401);
        }

        $propiedadId = (int) ($_GET['propiedad_id'] ?? 0);
        $fechaHotelera = trim((string) ($_GET['fecha_hotelera'] ?? ''));

        if ($propiedadId <= 0 || $fechaHotelera === '') {
            return Respuesta::json(['error' => 'Parámetros propiedad_id y fecha_hotelera requeridos.'], 422);
        }

        $cierre = $this->nightAuditServicio->obtenerCierrePorFecha($propiedadId, $fechaHotelera);
        if ($cierre === null) {
            return Respuesta::json(['error' => 'No existe cierre para la fecha indicada.', 'encontrado' => false], 404);
        }

        return Respuesta::json([
            'encontrado' => true,
            'cierre' => $cierre->aArray(),
        ], 200);
    }

    /**
     * Consulta el historial de cierres (GET /api/operaciones/night-audit/historial).
     */
    public function historial(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        if ($usuarioActual === null) {
            return Respuesta::json(['error' => 'No autenticado'], 401);
        }

        $propiedadId = (int) ($_GET['propiedad_id'] ?? 1);
        $limite = max(1, min(100, (int) ($_GET['limite'] ?? 30)));

        $cierres = $this->nightAuditServicio->listarCierres($propiedadId, $limite);

        return Respuesta::json([
            'cierres' => array_map(fn($c) => $c->aArray(), $cierres),
        ], 200);
    }

    /**
     * Lista los devengos diarios de una estadía (GET /api/operaciones/devengos/estadia/{id}).
     */
    public function devengosEstadia(array $parametros): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        if ($usuarioActual === null) {
            return Respuesta::json(['error' => 'No autenticado'], 401);
        }

        $estadiaId = (int) ($parametros['id'] ?? 0);
        if ($estadiaId <= 0) {
            return Respuesta::json(['error' => 'Identificador de estadía inválido.'], 422);
        }

        $devengos = $this->devengoServicio->listarDevengosEstadia($estadiaId);

        return Respuesta::json([
            'estadia_id' => $estadiaId,
            'devengos' => array_map(fn($d) => $d->aArray(), $devengos),
        ], 200);
    }

    /**
     * Revierte un devengo por corrección supervisada (POST /api/operaciones/devengos/revertir).
     */
    public function revertirDevengo(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        if ($usuarioActual === null) {
            return Respuesta::json(['error' => 'No autenticado'], 401);
        }

        $input = $this->obtenerPayload();
        if (!$this->validarCsrf($input)) {
            return Respuesta::json(['error' => 'Token CSRF inválido o expirado'], 403);
        }

        $devengoId = (int) ($input['devengo_id'] ?? 0);
        $motivo = trim((string) ($input['motivo'] ?? ''));

        if ($devengoId <= 0 || $motivo === '') {
            return Respuesta::json(['error' => 'Identificador de devengo y motivo justificado requeridos.'], 422);
        }

        try {
            $devengo = $this->devengoServicio->revertirDevengo($devengoId, $motivo, (int) $usuarioActual->obtenerId());

            return Respuesta::json([
                'mensaje' => "Devengo {$devengo->obtenerCodigo()} revertido correctamente.",
                'devengo' => $devengo->aArray(),
            ], 200);
        } catch (EntidadNoEncontradaExcepcion $e) {
            return Respuesta::json(['error' => $e->getMessage()], 404);
        } catch (OperacionInvalidaExcepcion $e) {
            return Respuesta::json(['error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['error' => 'Error al revertir devengo: ' . $e->getMessage()], 500);
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
     */
    private function validarCsrf(array $payload): bool
    {
        $token = (string) ($payload['_csrf_token']
            ?? $payload['csrf_token']
            ?? $payload['_token']
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? $_SERVER['HTTP_X_XSRF_TOKEN']
            ?? '');

        return $token !== '' && $this->csrfServicio->validarToken($token);
    }
}
