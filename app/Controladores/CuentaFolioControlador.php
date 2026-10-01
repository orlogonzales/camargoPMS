<?php

declare(strict_types=1);

namespace CamargoPMS\Controladores;

use CamargoPMS\Excepciones\CargoConPagosAplicadosExcepcion;
use CamargoPMS\Excepciones\CargoNoEncontradoExcepcion;
use CamargoPMS\Excepciones\CuentaFolioNoEncontradaExcepcion;
use CamargoPMS\Excepciones\EstadoFinancieroInvalidoExcepcion;
use CamargoPMS\Excepciones\MontoInvalidoExcepcion;
use CamargoPMS\Excepciones\TransferenciaFolioInvalidaExcepcion;
use CamargoPMS\Modelos\Usuario;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Repositorios\ActorAuditoriaRepositorio;
use CamargoPMS\Repositorios\CuentaFolioRepositorio;
use CamargoPMS\Servicios\AuditoriaServicio;
use CamargoPMS\Servicios\AutorizacionServicio;
use CamargoPMS\Servicios\CsrfServicio;
use CamargoPMS\Servicios\CuentaFolioServicio;
use CamargoPMS\Servicios\SesionServicio;
use PDO;
use Throwable;

/**
 * Controlador administrativo para la gestión de cuentas folio, transferencias
 * totales y división (split) de cargos entre folios (FINANCIERO-3B).
 *
 * Principios vinculantes:
 * - SOBERANÍA DEL PMS: Cero lógica contable en controlador; orquestación en CuentaFolioServicio.
 * - CONCURRENCIA DETERMINISTA: Locking SELECT ... FOR UPDATE ordenado por ID en servicios.
 * - INVIOLABILIDAD FINANCIERA: Cero split o traslado de cargos con amortizaciones activas.
 * - AUDITORÍA TRANSVERSAL: D-061 (ACTOR != USUARIO).
 * - PROTECCIÓN CSRF: Requerida en todas las mutaciones POST.
 */
class CuentaFolioControlador
{
    private PDO $pdo;
    private CuentaFolioServicio $cuentaFolioServicio;
    private CuentaFolioRepositorio $folioRepo;
    private SesionServicio $sesionServicio;
    private AutorizacionServicio $autorizacionServicio;
    private AuditoriaServicio $auditoriaServicio;
    private CsrfServicio $csrfServicio;

    public function __construct(
        ?PDO $pdo = null,
        ?CuentaFolioServicio $cuentaFolioServicio = null,
        ?CuentaFolioRepositorio $folioRepo = null,
        ?SesionServicio $sesionServicio = null,
        ?AutorizacionServicio $autorizacionServicio = null,
        ?AuditoriaServicio $auditoriaServicio = null,
        ?CsrfServicio $csrfServicio = null
    ) {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->cuentaFolioServicio = $cuentaFolioServicio ?? new CuentaFolioServicio($this->pdo);
        $this->folioRepo = $folioRepo ?? new CuentaFolioRepositorio($this->pdo);
        $this->sesionServicio = $sesionServicio ?? new SesionServicio($this->pdo);
        $this->autorizacionServicio = $autorizacionServicio ?? new AutorizacionServicio($this->pdo);
        $this->auditoriaServicio = $auditoriaServicio ?? new AuditoriaServicio($this->pdo);
        $this->csrfServicio = $csrfServicio ?? new CsrfServicio();
    }

    /**
     * Endpoint POST: Ejecuta la transferencia total de un cargo a otro folio.
     * Ruta: POST /folios/{id}/transferir-cargo
     */
    public function transferirCargo(int|string $id): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        $actorId = $this->resolverActorId($usuario);

        $datos = $this->obtenerCuerpoPeticion();

        if (!$this->validarCsrf($datos)) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => 'Token CSRF inválido o ausente.',
            ], 403);
        }

        $folioOrigenId = (int) $id;
        $folioDestinoId = isset($datos['folio_destino_id']) ? (int) $datos['folio_destino_id'] : 0;
        $cargoId = isset($datos['cargo_id']) ? (int) $datos['cargo_id'] : 0;
        $motivo = isset($datos['motivo']) ? (string) $datos['motivo'] : '';

        if ($folioDestinoId <= 0 || $cargoId <= 0 || trim($motivo) === '') {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => 'Parámetros obligatorios incompletos: folio_destino_id, cargo_id y motivo son requeridos.',
            ], 422);
        }

        try {
            $transferencia = $this->cuentaFolioServicio->transferirCargo(
                $folioOrigenId,
                $folioDestinoId,
                $cargoId,
                $motivo,
                $usuario ? (int) $usuario->obtenerId() : null,
                $actorId
            );

            // Trazabilidad D-061
            try {
                $this->auditoriaServicio->registrarEvento(
                    $actorId,
                    'FOLIO_TRANSFERENCIA_TOTAL',
                    'cuenta_folio_transferencias_cargos',
                    (int) $transferencia->obtenerId(),
                    [
                        'codigo' => $transferencia->obtenerCodigo(),
                        'folio_origen_id' => $folioOrigenId,
                        'folio_destino_id' => $folioDestinoId,
                        'cargo_id' => $cargoId,
                        'monto' => $transferencia->obtenerMontoTransferido(),
                        'motivo' => $motivo,
                    ]
                );
            } catch (Throwable) {
                // Trazabilidad no impeditiva
            }

            return Respuesta::json([
                'ok' => true,
                'mensaje' => 'Cargo transferido exitosamente al folio de destino.',
                'datos' => [
                    'transferencia' => $transferencia->haciaArreglo(),
                ],
            ], 200);
        } catch (CuentaFolioNoEncontradaExcepcion | CargoNoEncontradoExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => $e->getMessage(),
            ], 404);
        } catch (CargoConPagosAplicadosExcepcion | TransferenciaFolioInvalidaExcepcion | EstadoFinancieroInvalidoExcepcion | MontoInvalidoExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => 'Error interno al transferir cargo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Endpoint POST: Ejecuta la división parcial (split) de un cargo entre folios.
     * Ruta: POST /folios/{id}/split-cargo
     */
    public function splitCargo(int|string $id): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        $actorId = $this->resolverActorId($usuario);

        $datos = $this->obtenerCuerpoPeticion();

        if (!$this->validarCsrf($datos)) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => 'Token CSRF inválido o ausente.',
            ], 403);
        }

        $folioOrigenId = (int) $id;
        $folioDestinoId = isset($datos['folio_destino_id']) ? (int) $datos['folio_destino_id'] : 0;
        $cargoId = isset($datos['cargo_id']) ? (int) $datos['cargo_id'] : 0;
        $montoSplit = isset($datos['monto_split']) ? (string) $datos['monto_split'] : '';
        $motivo = isset($datos['motivo']) ? (string) $datos['motivo'] : '';

        if ($folioDestinoId <= 0 || $cargoId <= 0 || trim($montoSplit) === '' || trim($motivo) === '') {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => 'Parámetros obligatorios incompletos: folio_destino_id, cargo_id, monto_split y motivo son requeridos.',
            ], 422);
        }

        try {
            $resultado = $this->cuentaFolioServicio->splitCargo(
                $folioOrigenId,
                $folioDestinoId,
                $cargoId,
                $montoSplit,
                $motivo,
                $usuario ? (int) $usuario->obtenerId() : null,
                $actorId
            );

            // Trazabilidad D-061
            try {
                $this->auditoriaServicio->registrarEvento(
                    $actorId,
                    'FOLIO_SPLIT_PARCIAL',
                    'cuenta_folio_transferencias_cargos',
                    (int) $resultado['transferencia']->obtenerId(),
                    [
                        'codigo' => $resultado['transferencia']->obtenerCodigo(),
                        'folio_origen_id' => $folioOrigenId,
                        'folio_destino_id' => $folioDestinoId,
                        'cargo_padre_id' => $cargoId,
                        'cargo_derivado_id' => $resultado['cargo_derivado']->obtenerId(),
                        'monto_split' => $montoSplit,
                        'motivo' => $motivo,
                    ]
                );
            } catch (Throwable) {
                // Trazabilidad no impeditiva
            }

            return Respuesta::json([
                'ok' => true,
                'mensaje' => 'Cargo dividido exitosamente entre los folios.',
                'datos' => [
                    'transferencia' => $resultado['transferencia']->haciaArreglo(),
                    'cargo_padre' => $resultado['cargo_padre']->haciaArreglo(),
                    'cargo_derivado' => $resultado['cargo_derivado']->haciaArreglo(),
                ],
            ], 200);
        } catch (CuentaFolioNoEncontradaExcepcion | CargoNoEncontradoExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => $e->getMessage(),
            ], 404);
        } catch (CargoConPagosAplicadosExcepcion | TransferenciaFolioInvalidaExcepcion | EstadoFinancieroInvalidoExcepcion | MontoInvalidoExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => 'Error interno al dividir cargo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Endpoint POST: Apertura un folio secundario para la cuenta / reserva del folio dado.
     * Ruta: POST /folios/{id}/secundarios
     */
    public function crearSecundario(int|string $id): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        $actorId = $this->resolverActorId($usuario);

        $datos = $this->obtenerCuerpoPeticion();

        if (!$this->validarCsrf($datos)) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => 'Token CSRF inválido o ausente.',
            ], 403);
        }

        $folioId = (int) $id;
        $folioBase = $this->folioRepo->obtenerPorId($folioId);
        if ($folioBase === null) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => "Folio base ID {$folioId} no encontrado.",
            ], 404);
        }

        $reservaId = $folioBase->obtenerReservaId();
        if ($reservaId === null) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => 'El folio no está asociado a una reserva válida para aperturar un folio secundario.',
            ], 422);
        }

        $etiqueta = isset($datos['etiqueta']) ? trim((string) $datos['etiqueta']) : 'FOLIO SECUNDARIO';
        if ($etiqueta === '') {
            $etiqueta = 'FOLIO SECUNDARIO';
        }

        $personaTitularId = isset($datos['persona_titular_id']) && (int) $datos['persona_titular_id'] > 0
            ? (int) $datos['persona_titular_id']
            : (int) $folioBase->obtenerPersonaTitularId();

        try {
            $secundario = $this->cuentaFolioServicio->crearFolioSecundario(
                $reservaId,
                $personaTitularId,
                $etiqueta,
                $actorId
            );

            // Trazabilidad D-061
            try {
                $this->auditoriaServicio->registrarEvento(
                    $actorId,
                    'FOLIO_SECUNDARIO_CREADO',
                    'cuentas_folios',
                    (int) $secundario->obtenerId(),
                    [
                        'codigo' => $secundario->obtenerCodigo(),
                        'reserva_id' => $reservaId,
                        'etiqueta' => $etiqueta,
                        'persona_titular_id' => $personaTitularId,
                        'folio_padre_id' => $secundario->obtenerFolioPadreId(),
                    ]
                );
            } catch (Throwable) {
                // Trazabilidad no impeditiva
            }

            return Respuesta::json([
                'ok' => true,
                'mensaje' => "Folio secundario {$secundario->obtenerCodigo()} aperturado exitosamente.",
                'datos' => [
                    'folio' => $secundario->haciaArreglo(),
                ],
            ], 201);
        } catch (CuentaFolioNoEncontradaExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (EstadoFinancieroInvalidoExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => 'Error interno al aperturar folio secundario: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Endpoint GET: Retorna los folios relacionados (hermanos) de una misma reserva o cuenta.
     * Ruta: GET /folios/{id}/relacionados
     */
    public function foliosRelacionadosJson(int|string $id): Respuesta
    {
        $folioId = (int) $id;
        $folio = $this->folioRepo->obtenerPorId($folioId);
        if ($folio === null) {
            return Respuesta::json(['ok' => false, 'mensaje' => "Folio ID {$folioId} no encontrado"], 404);
        }

        try {
            if ($folio->obtenerReservaId() !== null) {
                $folios = $this->cuentaFolioServicio->obtenerFoliosReserva((int) $folio->obtenerReservaId());
            } elseif ($folio->obtenerArrendamientoId() !== null) {
                $folios = $this->folioRepo->listarPorArrendamientoId((int) $folio->obtenerArrendamientoId());
            } else {
                $padreId = $folio->obtenerFolioPadreId() ?? $folioId;
                $padre = $this->folioRepo->obtenerPorId($padreId);
                $hijos = $this->folioRepo->listarHijos($padreId);
                $folios = array_values(array_filter(array_merge($padre ? [$padre] : [], $hijos)));
            }

            return Respuesta::json([
                'ok' => true,
                'datos' => array_map(fn($f) => $f->haciaArreglo(), $folios),
            ], 200);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => 'Error al consultar folios relacionados: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Endpoint GET: Retorna el historial de transferencias vinculadas a un folio.
     * Ruta: GET /folios/{id}/transferencias
     */
    public function transferenciasJson(int|string $id): Respuesta
    {
        $folioId = (int) $id;
        $folio = $this->folioRepo->obtenerPorId($folioId);
        if ($folio === null) {
            return Respuesta::json(['ok' => false, 'mensaje' => "Folio ID {$folioId} no encontrado"], 404);
        }

        try {
            $transferencias = $this->cuentaFolioServicio->obtenerTransferenciasFolio($folioId);
            $datos = [];
            foreach ($transferencias as $t) {
                $item = $t->haciaArreglo();
                $folioOrig = $this->folioRepo->obtenerPorId($t->obtenerCuentaFolioOrigenId());
                $folioDest = $this->folioRepo->obtenerPorId($t->obtenerCuentaFolioDestinoId());
                $item['folio_origen_codigo'] = $folioOrig ? $folioOrig->obtenerCodigo() : "FOL-{$t->obtenerCuentaFolioOrigenId()}";
                $item['folio_origen_etiqueta'] = $folioOrig ? $folioOrig->obtenerEtiqueta() : '';
                $item['folio_destino_codigo'] = $folioDest ? $folioDest->obtenerCodigo() : "FOL-{$t->obtenerCuentaFolioDestinoId()}";
                $item['folio_destino_etiqueta'] = $folioDest ? $folioDest->obtenerEtiqueta() : '';
                $datos[] = $item;
            }

            return Respuesta::json([
                'ok' => true,
                'datos' => $datos,
            ], 200);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al obtener transferencias: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Endpoint GET: Retorna los cargos de un folio.
     * Ruta: GET /folios/{id}/cargos
     */
    public function cargosJson(int|string $id): Respuesta
    {
        $folioId = (int) $id;
        $folio = $this->folioRepo->obtenerPorId($folioId);
        if ($folio === null) {
            return Respuesta::json(['ok' => false, 'mensaje' => "Folio ID {$folioId} no encontrado"], 404);
        }

        try {
            $cargos = $this->cuentaFolioServicio->obtenerCargosFolio($folioId);
            return Respuesta::json([
                'ok' => true,
                'datos' => array_map(fn($c) => $c->haciaArreglo(), $cargos),
            ], 200);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al obtener cargos: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Endpoint GET: Retorna el estado de cuenta y saldos completos de un folio.
     * Ruta: GET /folios/{id}/datos
     */
    public function detalleJson(int|string $id): Respuesta
    {
        $folioId = (int) $id;
        try {
            $estadoCuenta = $this->cuentaFolioServicio->obtenerEstadoCuenta($folioId);
            return Respuesta::json([
                'ok' => true,
                'datos' => $estadoCuenta,
            ], 200);
        } catch (CuentaFolioNoEncontradaExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al obtener estado de cuenta: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Obtiene y decodifica el cuerpo de la petición.
     *
     * @return array<string, mixed>
     */
    private function obtenerCuerpoPeticion(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
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
     * Valida el token CSRF presente en payload o cabeceras HTTP.
     *
     * @param array<string, mixed> $payload
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

    /**
     * Resuelve canónicamente el ID de actor ejecutor (D-061: ACTOR != USUARIO).
     */
    private function resolverActorId(?Usuario $usuario = null): int
    {
        if ($usuario !== null && $usuario->obtenerId() !== null) {
            try {
                $actor = $this->auditoriaServicio->obtenerOAsegurarActorUsuario((int) $usuario->obtenerId(), $this->pdo);
                if ($actor && $actor->obtenerId() !== null) {
                    return (int) $actor->obtenerId();
                }
            } catch (Throwable) {
                // Fallback
            }
        }

        try {
            $repo = new ActorAuditoriaRepositorio($this->pdo);
            $sistema = $repo->buscarPorCodigo('CAMARGO_PMS');
            if ($sistema && $sistema->obtenerId() !== null) {
                return (int) $sistema->obtenerId();
            }
        } catch (Throwable) {
            // Fallback
        }

        return 1;
    }
}
