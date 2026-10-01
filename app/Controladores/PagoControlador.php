<?php

declare(strict_types=1);

namespace CamargoPMS\Controladores;

use CamargoPMS\Modelos\PagoTransaccionPasarela;
use CamargoPMS\Modelos\PagoWebhookEvento;
use CamargoPMS\Modelos\Usuario;
use CamargoPMS\Nucleo\Ayudante;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Nucleo\Vista;
use CamargoPMS\Repositorios\ActorAuditoriaRepositorio;
use CamargoPMS\Repositorios\CuentaFolioRepositorio;
use CamargoPMS\Repositorios\PagoTransaccionPasarelaRepositorio;
use CamargoPMS\Repositorios\PagoWebhookEventoRepositorio;
use CamargoPMS\Repositorios\ReservaRepositorio;
use CamargoPMS\Servicios\AuditoriaServicio;
use CamargoPMS\Servicios\AutorizacionServicio;
use CamargoPMS\Servicios\CsrfServicio;
use CamargoPMS\Servicios\PagoServicio;
use CamargoPMS\Servicios\SanitizadorAuditoria;
use CamargoPMS\Servicios\SesionServicio;
use PDO;
use Throwable;

/**
 * Controlador administrativo Alina para el monitor de transacciones de pasarela,
 * conciliación de discrepancias y gestión de reembolsos (PAGOS-1D).
 *
 * Principios vinculantes:
 * - ALINA DESIGN SYSTEM: Cards equal-card, tablas bordeadas/striped, badges con bordes sólidos continuos.
 * - SOBERANÍA DEL PMS: Cero lógica financiera en controladores; orquestación vía PagoServicio.
 * - INVARIANTE HOTELERO C1/C2: Pagos tardíos con hold expirado permanecen en cuarentena sin reactivar reservas.
 * - CERO SOBREVENTA: Prohibida la reasignación automática o arbitraria de pagos tardíos a nuevas reservas.
 * - ZERO-TRUST: Cero exposición de claves privadas, webhook secrets o datos de tarjeta bancaria (PAN/CVV).
 * - CONCURRENCIA DETERMINISTA: Locking SELECT ... FOR UPDATE en operaciones mutacionales.
 * - ACTOR != USUARIO: Acciones humanas imputadas al actor humano de sesión.
 */
class PagoControlador
{
    private PDO $pdo;
    private SesionServicio $sesionServicio;
    private AutorizacionServicio $autorizacionServicio;
    private CsrfServicio $csrfServicio;
    private AuditoriaServicio $auditoriaServicio;
    private PagoServicio $pagoServicio;
    private PagoTransaccionPasarelaRepositorio $txRepo;
    private PagoWebhookEventoRepositorio $webhookRepo;
    private ReservaRepositorio $reservaRepo;
    private CuentaFolioRepositorio $folioRepo;
    private SanitizadorAuditoria $sanitizador;
    private Vista $vista;

    public function __construct(
        ?PDO $pdo = null,
        ?SesionServicio $sesionServicio = null,
        ?AutorizacionServicio $autorizacionServicio = null,
        ?CsrfServicio $csrfServicio = null,
        ?AuditoriaServicio $auditoriaServicio = null,
        ?PagoServicio $pagoServicio = null,
        ?PagoTransaccionPasarelaRepositorio $txRepo = null,
        ?PagoWebhookEventoRepositorio $webhookRepo = null,
        ?ReservaRepositorio $reservaRepo = null,
        ?CuentaFolioRepositorio $folioRepo = null,
        ?SanitizadorAuditoria $sanitizador = null,
        ?Vista $vista = null
    ) {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->sesionServicio = $sesionServicio ?? new SesionServicio($this->pdo);
        $this->autorizacionServicio = $autorizacionServicio ?? new AutorizacionServicio($this->pdo);
        $this->csrfServicio = $csrfServicio ?? new CsrfServicio();
        $this->auditoriaServicio = $auditoriaServicio ?? new AuditoriaServicio($this->pdo);
        $this->txRepo = $txRepo ?? new PagoTransaccionPasarelaRepositorio($this->pdo);
        $this->webhookRepo = $webhookRepo ?? new PagoWebhookEventoRepositorio($this->pdo);
        $this->reservaRepo = $reservaRepo ?? new ReservaRepositorio($this->pdo);
        $this->folioRepo = $folioRepo ?? new CuentaFolioRepositorio($this->pdo);
        $this->sanitizador = $sanitizador ?? new SanitizadorAuditoria();
        $this->pagoServicio = $pagoServicio ?? new PagoServicio($this->pdo);
        $this->vista = $vista ?? new Vista();
    }

    /**
     * GET /pagos: Renderiza el monitor operativo principal de transacciones Alina.
     */
    public function index(): Respuesta
    {
        $usuario = $this->sesionServicio->validarSesionActual();
        if ($usuario === null) {
            return Respuesta::redirigir(Ayudante::ruta('/login?return=' . urlencode('/pagos')));
        }

        $usuarioId = (int) $usuario->obtenerId();
        if (!$this->puedeVer($usuarioId)) {
            $panel = new PanelControlador();
            return $panel->error(403);
        }

        $permisos = [
            'puede_ver' => true,
            'puede_reembolsar' => $this->puedeReembolsar($usuarioId),
            'puede_conciliar' => $this->puedeConciliar($usuarioId),
        ];

        $migasPan = [
            ['etiqueta' => 'Inicio', 'url' => Ayudante::ruta('/')],
            ['etiqueta' => 'Caja y Finanzas', 'url' => Ayudante::ruta('/caja')],
            ['etiqueta' => 'Pasarelas de Pago', 'activo' => true],
        ];

        $kpis = $this->txRepo->obtenerMetricasKpi();
        $transacciones = $this->txRepo->listarConFiltros([], 1, 20);
        $total = $this->txRepo->contarConFiltros([]);

        $html = $this->vista->renderizar('pagos/index', [
            'titulo' => 'Monitor de Pasarelas de Pago — Camargo PMS',
            'usuario' => $usuario,
            'permisos' => $permisos,
            'csrf_token' => $this->csrfServicio->obtenerToken(),
            'migasPan' => $migasPan,
            'categoriaActiva' => 'caja_finanzas',
            'kpis' => $kpis,
            'transacciones' => $transacciones,
            'paginacion' => [
                'pagina' => 1,
                'por_pagina' => 20,
                'total' => $total,
                'total_paginas' => (int) ceil($total / 20),
            ],
        ]);

        return new Respuesta($html);
    }

    /**
     * GET /pagos/datos: Retorna listado de transacciones, paginación y KPIs en JSON.
     */
    public function datosJson(): Respuesta
    {
        $usuario = $this->sesionServicio->validarSesionActual();
        if ($usuario === null) {
            return Respuesta::json(['ok' => false, 'error' => 'Sesión no válida.'], 401);
        }

        $usuarioId = (int) $usuario->obtenerId();
        if (!$this->puedeVer($usuarioId)) {
            return Respuesta::json(['ok' => false, 'error' => 'Acceso denegado: no cuentas con el permiso requerido.'], 403);
        }

        $filtros = [
            'proveedor' => isset($_GET['proveedor']) && is_string($_GET['proveedor']) ? trim($_GET['proveedor']) : null,
            'estado_pago' => isset($_GET['estado_pago']) && is_string($_GET['estado_pago']) ? trim($_GET['estado_pago']) : null,
            'estado_conciliacion' => isset($_GET['estado_conciliacion']) && is_string($_GET['estado_conciliacion']) ? trim($_GET['estado_conciliacion']) : null,
            'estado_reembolso' => isset($_GET['estado_reembolso']) && is_string($_GET['estado_reembolso']) ? trim($_GET['estado_reembolso']) : null,
            'fecha_desde' => isset($_GET['fecha_desde']) && is_string($_GET['fecha_desde']) ? trim($_GET['fecha_desde']) : null,
            'fecha_hasta' => isset($_GET['fecha_hasta']) && is_string($_GET['fecha_hasta']) ? trim($_GET['fecha_hasta']) : null,
            'busqueda' => isset($_GET['busqueda']) && is_string($_GET['busqueda']) ? trim($_GET['busqueda']) : null,
        ];

        $pagina = isset($_GET['pagina']) && is_numeric($_GET['pagina']) ? max(1, (int) $_GET['pagina']) : 1;
        $porPagina = isset($_GET['por_pagina']) && is_numeric($_GET['por_pagina']) ? max(1, min(100, (int) $_GET['por_pagina'])) : 20;

        $transacciones = $this->txRepo->listarConFiltros($filtros, $pagina, $porPagina);
        $total = $this->txRepo->contarConFiltros($filtros);
        $kpis = $this->txRepo->obtenerMetricasKpi();

        $transaccionesSanitizadas = array_map(function (array $tx) {
            if (!empty($tx['metadatos_proveedor'])) {
                $meta = is_string($tx['metadatos_proveedor']) ? json_decode($tx['metadatos_proveedor'], true) : $tx['metadatos_proveedor'];
                $tx['metadatos_proveedor'] = $this->sanitizarMetadatos($meta);
            }
            return $tx;
        }, $transacciones);

        return Respuesta::json([
            'ok' => true,
            'datos' => [
                'transacciones' => $transaccionesSanitizadas,
                'kpis' => $kpis,
                'paginacion' => [
                    'pagina' => $pagina,
                    'por_pagina' => $porPagina,
                    'total' => $total,
                    'total_paginas' => (int) ceil($total / $porPagina),
                ],
            ],
        ]);
    }

    /**
     * GET /pagos/transacciones/{id}: Renderiza la ficha integral de detalle con timeline Alina.
     */
    public function detalle(int $id): Respuesta
    {
        $usuario = $this->sesionServicio->validarSesionActual();
        if ($usuario === null) {
            return Respuesta::redirigir(Ayudante::ruta('/login?return=' . urlencode('/pagos/transacciones/' . $id)));
        }

        $usuarioId = (int) $usuario->obtenerId();
        if (!$this->puedeVer($usuarioId)) {
            $panel = new PanelControlador();
            return $panel->error(403);
        }

        $tx = $this->txRepo->buscarPorId($id);
        if ($tx === null) {
            $panel = new PanelControlador();
            return $panel->error(404);
        }

        $reserva = $tx->obtenerReservaId() ? $this->reservaRepo->buscarPorId($tx->obtenerReservaId(), false) : null;
        $folio = $tx->obtenerCuentaFolioId() ? $this->folioRepo->buscarPorId($tx->obtenerCuentaFolioId()) : null;
        $webhooks = $this->webhookRepo->buscarPorTransaccionId($id);

        $metadatos = $this->sanitizarMetadatos($tx->obtenerMetadatosProveedor());
        $lineaTiempo = $this->construirLineaTiempo($tx, $webhooks);

        $permisos = [
            'puede_ver' => true,
            'puede_reembolsar' => $this->puedeReembolsar($usuarioId),
            'puede_conciliar' => $this->puedeConciliar($usuarioId),
        ];

        $migasPan = [
            ['etiqueta' => 'Inicio', 'url' => Ayudante::ruta('/')],
            ['etiqueta' => 'Caja y Finanzas', 'url' => Ayudante::ruta('/caja')],
            ['etiqueta' => 'Pasarelas de Pago', 'url' => Ayudante::ruta('/pagos')],
            ['etiqueta' => $tx->obtenerCodigo(), 'activo' => true],
        ];

        $html = $this->vista->renderizar('pagos/detalle', [
            'titulo' => 'Detalle de Transacción ' . $tx->obtenerCodigo() . ' — Camargo PMS',
            'usuario' => $usuario,
            'permisos' => $permisos,
            'csrf_token' => $this->csrfServicio->obtenerToken(),
            'migasPan' => $migasPan,
            'categoriaActiva' => 'caja_finanzas',
            'transaccion' => $tx,
            'reserva' => $reserva,
            'folio' => $folio,
            'webhooks' => $webhooks,
            'metadatos' => $metadatos,
            'lineaTiempo' => $lineaTiempo,
        ]);

        return new Respuesta($html);
    }

    /**
     * GET /pagos/transacciones/{id}/datos: Retorna el detalle completo en JSON.
     */
    public function detalleJson(int $id): Respuesta
    {
        $usuario = $this->sesionServicio->validarSesionActual();
        if ($usuario === null) {
            return Respuesta::json(['ok' => false, 'error' => 'Sesión no válida.'], 401);
        }

        $usuarioId = (int) $usuario->obtenerId();
        if (!$this->puedeVer($usuarioId)) {
            return Respuesta::json(['ok' => false, 'error' => 'No autorizado.'], 403);
        }

        $tx = $this->txRepo->buscarPorId($id);
        if ($tx === null) {
            return Respuesta::json(['ok' => false, 'error' => 'Transacción no encontrada.'], 404);
        }

        $reserva = $tx->obtenerReservaId() ? $this->reservaRepo->buscarPorId($tx->obtenerReservaId(), false) : null;
        $folio = $tx->obtenerCuentaFolioId() ? $this->folioRepo->buscarPorId($tx->obtenerCuentaFolioId()) : null;
        $webhooks = $this->webhookRepo->buscarPorTransaccionId($id);

        $metadatos = $this->sanitizarMetadatos($tx->obtenerMetadatosProveedor());
        $lineaTiempo = $this->construirLineaTiempo($tx, $webhooks);

        $txArray = $tx->aArray();
        $txArray['metadatos_proveedor'] = $metadatos;

        return Respuesta::json([
            'ok' => true,
            'datos' => [
                'transaccion' => $txArray,
                'reserva' => $reserva ? [
                    'id' => $reserva->obtenerId(),
                    'codigo' => $reserva->obtenerCodigo(),
                    'estado' => $reserva->obtenerEstado(),
                    'expira_en' => $reserva->obtenerExpiraEn(),
                    'total_alojamiento' => $reserva->obtenerTotalAlojamiento(),
                ] : null,
                'folio' => $folio ? [
                    'id' => $folio->obtenerId(),
                    'codigo' => $folio->obtenerCodigo(),
                    'estado' => $folio->obtenerEstado(),
                ] : null,
                'linea_tiempo' => $lineaTiempo,
                'webhooks' => array_map(static fn(PagoWebhookEvento $w) => [
                    'id' => $w->obtenerId(),
                    'proveedor' => $w->obtenerProveedor(),
                    'tipo_evento' => $w->obtenerTipoEvento(),
                    'creado_en' => $w->obtenerCreadoEn(),
                    'procesado_en' => $w->obtenerProcesadoEn(),
                    'estado_procesamiento' => $w->obtenerEstadoProcesamiento(),
                    'codigo_http_respuesta' => $w->obtenerCodigoHttpRespuesta(),
                ], $webhooks),
            ],
        ]);
    }

    /**
     * POST /pagos/transacciones/{id}/reembolsar: Ejecuta un reembolso total o parcial ante la pasarela.
     */
    public function reembolsar(int $id): Respuesta
    {
        $usuario = $this->sesionServicio->validarSesionActual();
        if ($usuario === null) {
            return Respuesta::json(['ok' => false, 'error' => 'Sesión no válida.'], 401);
        }

        $usuarioId = (int) $usuario->obtenerId();
        if (!$this->puedeReembolsar($usuarioId)) {
            return Respuesta::json(['ok' => false, 'error' => 'Acceso denegado: se requiere permiso para reembolsar.'], 403);
        }

        $cuerpo = file_get_contents('php://input');
        $datos = json_decode($cuerpo ?: '{}', true) ?: $_POST;

        $errorCsrf = $this->validarCsrf($datos);
        if ($errorCsrf !== null) {
            return $errorCsrf;
        }

        $motivo = trim((string) ($datos['motivo'] ?? ''));
        if (mb_strlen($motivo) < 10) {
            return Respuesta::json([
                'ok' => false,
                'codigo' => 'MOTIVO_REEMBOLSO_REQUERIDO',
                'error' => 'El motivo del reembolso es obligatorio y debe contener al menos 10 caracteres explicativos.',
            ], 422);
        }

        $tipoReembolso = strtoupper(trim((string) ($datos['tipo_reembolso'] ?? 'TOTAL')));

        // Bloqueo pesimista ACID para concurrencia segura
        $this->pdo->beginTransaction();
        try {
            $tx = $this->txRepo->buscarPorIdParaActualizar($id);
            if ($tx === null) {
                $this->pdo->rollBack();
                return Respuesta::json(['ok' => false, 'error' => 'Transacción no encontrada.'], 404);
            }

            if (!$tx->estaAprobado()) {
                $this->pdo->rollBack();
                return Respuesta::json([
                    'ok' => false,
                    'codigo' => 'TRANSACCION_NO_APROBADA',
                    'error' => 'Solo se pueden reembolsar transacciones en estado APROBADO.',
                ], 422);
            }

            $montoCobrado = $tx->obtenerMontoCobrado() ?: $tx->obtenerMontoEsperado();
            $montoReembolsado = $tx->obtenerMontoReembolsado();
            $saldoDisponible = bcsub($montoCobrado, $montoReembolsado, 2);

            if (bccomp($saldoDisponible, '0.00', 2) <= 0) {
                $this->pdo->rollBack();
                return Respuesta::json([
                    'ok' => false,
                    'codigo' => 'SIN_SALDO_REEMBOLSABLE',
                    'error' => 'Esta transacción ya fue reembolsada en su totalidad.',
                ], 422);
            }

            if ($tipoReembolso === 'TOTAL') {
                $montoFinal = $saldoDisponible;
            } else {
                $montoInput = (string) ($datos['monto'] ?? '');
                if (!is_numeric($montoInput) || bccomp($montoInput, '0.00', 2) <= 0 || bccomp($montoInput, $saldoDisponible, 2) > 0) {
                    $this->pdo->rollBack();
                    return Respuesta::json([
                        'ok' => false,
                        'codigo' => 'MONTO_REEMBOLSO_INVALIDO',
                        'error' => "El monto de reembolso parcial debe ser mayor a 0 y no exceder el saldo disponible de S/ $saldoDisponible.",
                    ], 422);
                }
                $montoFinal = number_format((float) $montoInput, 2, '.', '');
            }

            // Liberamos el lock de BD antes de la llamada de red para evitar bloqueos largos
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return Respuesta::json(['ok' => false, 'error' => 'Error de concurrencia: ' . $e->getMessage()], 500);
        }

        // Ejecutar reembolso soberano con atribución humana
        $actorHumanoId = $this->resolverActorId($usuario);
        $resultado = $this->pagoServicio->procesarReembolso(
            transaccionId: $id,
            monto: $montoFinal,
            motivo: $motivo,
            actorId: $actorHumanoId
        );

        if (!$resultado['exito']) {
            return Respuesta::json([
                'ok' => false,
                'codigo' => $resultado['codigo'] ?? 'ERROR_PROVEEDOR_REEMBOLSO',
                'error' => $resultado['mensaje'] ?? 'Error al procesar el reembolso en la pasarela.',
            ], 422);
        }

        $kpisActualizados = $this->txRepo->obtenerMetricasKpi();
        $txActualizada = $this->txRepo->buscarPorId($id);

        return Respuesta::json([
            'ok' => true,
            'codigo' => 'REEMBOLSO_EXITOSO',
            'mensaje' => "Reembolso procesado exitosamente por S/ $montoFinal.",
            'datos' => [
                'transaccion' => $txActualizada?->aArray(),
                'kpis' => $kpisActualizados,
                'reembolso_id' => $resultado['reembolso_id'] ?? null,
                'monto' => $montoFinal,
            ],
        ]);
    }

    /**
     * POST /pagos/transacciones/{id}/conciliar: Registra seguimiento administrativo de discrepancias.
     *
     * REGLA VINCULANTE: NO reasigna dinero a una nueva reserva. C1/C2 inviolable.
     */
    public function conciliar(int $id): Respuesta
    {
        $usuario = $this->sesionServicio->validarSesionActual();
        if ($usuario === null) {
            return Respuesta::json(['ok' => false, 'error' => 'Sesión no válida.'], 401);
        }

        $usuarioId = (int) $usuario->obtenerId();
        if (!$this->puedeConciliar($usuarioId)) {
            return Respuesta::json(['ok' => false, 'error' => 'Acceso denegado: se requiere permiso para conciliar.'], 403);
        }

        $cuerpo = file_get_contents('php://input');
        $datos = json_decode($cuerpo ?: '{}', true) ?: $_POST;

        $errorCsrf = $this->validarCsrf($datos);
        if ($errorCsrf !== null) {
            return $errorCsrf;
        }

        $observacion = trim((string) ($datos['observacion'] ?? ''));
        if (mb_strlen($observacion) < 10) {
            return Respuesta::json([
                'ok' => false,
                'codigo' => 'OBSERVACION_REQUERIDA',
                'error' => 'La nota de seguimiento debe contener al menos 10 caracteres explicativos.',
            ], 422);
        }

        $tx = $this->txRepo->buscarPorId($id);
        if ($tx === null) {
            return Respuesta::json(['ok' => false, 'error' => 'Transacción no encontrada.'], 404);
        }

        $actorHumanoId = $this->resolverActorId($usuario);
        $motivoActual = $tx->obtenerMotivoDiscrepancia() ?: '';
        $nuevoMotivo = trim($motivoActual . "\n[" . date('Y-m-d H:i:s') . " - " . $usuario->obtenerNombreUsuario() . "]: " . $observacion);

        $this->txRepo->actualizar($id, [
            'motivo_discrepancia' => $nuevoMotivo,
        ]);

        $this->auditoriaServicio->registrar(
            accion: 'SEGUIMIENTO_DISCREPANCIA_REGISTRADO',
            modulo: 'pagos',
            entidad: 'pagos_transacciones_pasarela',
            entidadId: (string) $id,
            descripcion: "Nota de seguimiento registrada por {$usuario->obtenerNombreUsuario()}: $observacion",
            valoresAnteriores: ['motivo_discrepancia' => $motivoActual],
            valoresNuevos: ['motivo_discrepancia' => $nuevoMotivo],
            actor: $actorHumanoId
        );

        $txActualizada = $this->txRepo->buscarPorId($id);

        return Respuesta::json([
            'ok' => true,
            'codigo' => 'SEGUIMIENTO_REGISTRADO',
            'mensaje' => 'Seguimiento administrativo registrado exitosamente.',
            'datos' => [
                'transaccion' => $txActualizada?->aArray(),
                'kpis' => $this->txRepo->obtenerMetricasKpi(),
            ],
        ]);
    }

    /**
     * GET /pagos/webhooks/{id}/payload: Retorna evento webhook sanitizado sin secretos para Offcanvas Alina.
     */
    public function webhookPayload(int $id): Respuesta
    {
        $usuario = $this->sesionServicio->validarSesionActual();
        if ($usuario === null) {
            return Respuesta::json(['ok' => false, 'error' => 'Sesión no válida.'], 401);
        }

        $usuarioId = (int) $usuario->obtenerId();
        if (!$this->puedeVer($usuarioId)) {
            return Respuesta::json(['ok' => false, 'error' => 'No autorizado.'], 403);
        }

        $webhook = $this->webhookRepo->buscarPorId($id);
        if ($webhook === null) {
            return Respuesta::json(['ok' => false, 'error' => 'Evento webhook no encontrado.'], 404);
        }

        $payloadRaw = $webhook->obtenerPayloadRaw();
        $payloadArray = json_decode($payloadRaw, true) ?: ['raw' => $payloadRaw];
        $payloadSanitizado = $this->sanitizarMetadatos($payloadArray);

        $cabeceras = $webhook->obtenerCabeceras();
        $cabecerasSanitizadas = $this->sanitizarMetadatos($cabeceras);

        return Respuesta::json([
            'ok' => true,
            'datos' => [
                'id' => $webhook->obtenerId(),
                'proveedor' => $webhook->obtenerProveedor(),
                'proveedor_evento_id' => $webhook->obtenerProveedorEventoId(),
                'tipo_evento' => $webhook->obtenerTipoEvento(),
                'cuerpo_hash' => $webhook->obtenerCuerpoHash(),
                'creado_en' => $webhook->obtenerCreadoEn(),
                'procesado_en' => $webhook->obtenerProcesadoEn(),
                'estado_procesamiento' => $webhook->obtenerEstadoProcesamiento(),
                'codigo_http_respuesta' => $webhook->obtenerCodigoHttpRespuesta(),
                'error_detalle' => $webhook->obtenerErrorDetalle(),
                'payload_sanitizado' => $payloadSanitizado,
                'cabeceras_sanitizadas' => $cabecerasSanitizadas,
            ],
        ]);
    }

    private function puedeVer(int $usuarioId): bool
    {
        return $this->autorizacionServicio->puede($usuarioId, 'caja.ver')
            || $this->autorizacionServicio->puede($usuarioId, 'pagos.ver');
    }

    private function puedeReembolsar(int $usuarioId): bool
    {
        return $this->autorizacionServicio->puede($usuarioId, 'caja.devolver')
            || $this->autorizacionServicio->puede($usuarioId, 'pagos.reembolsar');
    }

    private function puedeConciliar(int $usuarioId): bool
    {
        return $this->autorizacionServicio->puede($usuarioId, 'caja.cobrar')
            || $this->autorizacionServicio->puede($usuarioId, 'pagos.conciliar');
    }

    private function resolverActorId(?Usuario $usuario): int
    {
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

        if (empty($token) || !$this->csrfServicio->validarToken((string) $token)) {
            return Respuesta::json([
                'ok' => false,
                'codigo' => 'CSRF_INVALIDO',
                'error' => 'Token de seguridad CSRF inválido o sesión expirada. Por favor recargue la página.',
            ], 403);
        }

        return null;
    }

    /**
     * Sanitiza recursivamente metadatos para presentación pública o JSON en UI (CERO SECRETOS).
     *
     * @param array<string|int, mixed>|null $metadatos
     * @return array<string|int, mixed>
     */
    private function sanitizarMetadatos(?array $metadatos): array
    {
        if (empty($metadatos)) {
            return [];
        }

        $sanitizado = $this->sanitizador->sanitizar($metadatos) ?? [];

        // Redacción adicional estricta de términos de pasarela y tarjetas
        $clavesProhibidas = [
            'llave_secreta', 'secret_key', 'private_key', 'webhook_secret',
            'cvv', 'cvc', 'pan', 'card_number', 'numero_tarjeta',
            'token', 'authorization', 'signature', 'firma', 'x-culqi-signature'
        ];

        return $this->limpiarClavesRecursivo($sanitizado, $clavesProhibidas);
    }

    /**
     * @param array<string|int, mixed> $datos
     * @param list<string> $clavesProhibidas
     * @return array<string|int, mixed>
     */
    private function limpiarClavesRecursivo(array $datos, array $clavesProhibidas): array
    {
        $resultado = [];
        foreach ($datos as $clave => $valor) {
            $claveLower = strtolower((string) $clave);
            $esProhibida = false;
            foreach ($clavesProhibidas as $prohibida) {
                if (str_contains($claveLower, $prohibida)) {
                    $esProhibida = true;
                    break;
                }
            }

            if ($esProhibida) {
                continue;
            }

            if (is_array($valor)) {
                $resultado[$clave] = $this->limpiarClavesRecursivo($valor, $clavesProhibidas);
            } else {
                $resultado[$clave] = $valor;
            }
        }
        return $resultado;
    }

    /**
     * Construye los hitos cronológicos del ciclo de vida para el timeline Alina.
     *
     * @param PagoTransaccionPasarela $tx
     * @param PagoWebhookEvento[] $webhooks
     * @return list<array<string, mixed>>
     */
    private function construirLineaTiempo(PagoTransaccionPasarela $tx, array $webhooks): array
    {
        $hitos = [];

        // 1. Intención de pago iniciada
        $hitos[] = [
            'titulo' => 'Intención de Pago Creada',
            'fecha' => $tx->obtenerCreadoEn(),
            'tipo' => 'primary',
            'icono' => 'fa-solid fa-file-invoice-dollar',
            'descripcion' => "Intención registrada con orden {$tx->obtenerProveedorOrdenId()} por S/ {$tx->obtenerMontoEsperado()} ante {$tx->obtenerProveedor()}.",
        ];

        // 2. Webhooks recibidos
        foreach (array_reverse($webhooks) as $w) {
            $tipo = $w->obtenerCodigoHttpRespuesta() === 200 ? 'info' : 'danger';
            $hitos[] = [
                'titulo' => "Webhook {$w->obtenerProveedor()} ({$w->obtenerTipoEvento()})",
                'fecha' => $w->obtenerCreadoEn(),
                'tipo' => $tipo,
                'icono' => 'fa-solid fa-bell',
                'descripcion' => "Notificación externa recibida. Estado procesamiento: {$w->obtenerEstadoProcesamiento()} (HTTP {$w->obtenerCodigoHttpRespuesta()}).",
            ];
        }

        // 3. Conciliación o Cuarentena
        if ($tx->estaAprobado()) {
            if ($tx->obtenerEstadoConciliacion() === PagoTransaccionPasarela::ESTADO_CONCILIACION_CONCILIADO) {
                $hitos[] = [
                    'titulo' => 'Pago Confirmado y Conciliado',
                    'fecha' => $tx->obtenerActualizadoEn(),
                    'tipo' => 'success',
                    'icono' => 'fa-solid fa-circle-check',
                    'descripcion' => "Cobro de S/ {$tx->obtenerMontoCobrado()} confirmado e imputado exitosamente al folio de la reserva.",
                ];
            } elseif ($tx->obtenerEstadoConciliacion() === PagoTransaccionPasarela::ESTADO_CONCILIACION_DISCREPANCIA_HOLD_EXPIRADO) {
                $hitos[] = [
                    'titulo' => 'Pago Tardío en Cuarentena (C1/C2)',
                    'fecha' => $tx->obtenerActualizadoEn(),
                    'tipo' => 'danger',
                    'icono' => 'fa-solid fa-triangle-exclamation',
                    'descripcion' => "El cobro fue aprobado pero el hold de la reserva expiró. Fondos en cuarentena sin folio contable ni reactivación de inventario.",
                ];
            } else {
                $hitos[] = [
                    'titulo' => 'Pago Aprobado con Discrepancia (' . $tx->obtenerEstadoConciliacion() . ')',
                    'fecha' => $tx->obtenerActualizadoEn(),
                    'tipo' => 'warning',
                    'icono' => 'fa-solid fa-triangle-exclamation',
                    'descripcion' => $tx->obtenerMotivoDiscrepancia() ?: 'Transacción en discrepancia pendiente de resolución administrativa.',
                ];
            }
        } elseif ($tx->obtenerEstadoPago() === PagoTransaccionPasarela::ESTADO_PAGO_FALLIDO) {
            $hitos[] = [
                'titulo' => 'Pago Fallido en Pasarela',
                'fecha' => $tx->obtenerActualizadoEn(),
                'tipo' => 'danger',
                'icono' => 'fa-solid fa-circle-xmark',
                'descripcion' => $tx->obtenerMotivoDiscrepancia() ?: 'El pago fue rechazado por la pasarela de pago.',
            ];
        }

        // 4. Reembolsos
        if ($tx->obtenerEstadoReembolso() !== PagoTransaccionPasarela::ESTADO_REEMBOLSO_NO_APLICA) {
            $tipoReembolso = ($tx->obtenerEstadoReembolso() === PagoTransaccionPasarela::ESTADO_REEMBOLSO_TOTAL) ? 'success' : 'warning';
            $hitos[] = [
                'titulo' => 'Reembolso Registrado (' . $tx->obtenerEstadoReembolso() . ')',
                'fecha' => $tx->obtenerActualizadoEn(),
                'tipo' => $tipoReembolso,
                'icono' => 'fa-solid fa-arrow-rotate-left',
                'descripcion' => "Monto total reembolsado: S/ {$tx->obtenerMontoReembolsado()}. Motivo: {$tx->obtenerMotivoReembolso()}",
            ];
        }

        return $hitos;
    }
}
