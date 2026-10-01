<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

use CamargoPMS\Modelos\PagoTransaccionPasarela;
use CamargoPMS\Modelos\PagoWebhookEvento;
use CamargoPMS\Modelos\Reserva;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Repositorios\ActorAuditoriaRepositorio;
use CamargoPMS\Repositorios\CuentaFolioRepositorio;
use CamargoPMS\Repositorios\PagoCuentaRepositorio;
use CamargoPMS\Repositorios\PagoTransaccionPasarelaRepositorio;
use CamargoPMS\Repositorios\PagoWebhookEventoRepositorio;
use CamargoPMS\Repositorios\PersonaRepositorio;
use CamargoPMS\Repositorios\ReservaRepositorio;
use CamargoPMS\Servicios\Pagos\DTOs\IntencionPagoSolicitud;
use CamargoPMS\Servicios\Pagos\DTOs\ReembolsoSolicitud;
use CamargoPMS\Servicios\Pagos\DTOs\WebhookNotificacionResultado;
use CamargoPMS\Servicios\Pagos\FabricaProveedoresPago;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Orquestador Soberano del Dominio de Pagos Externos (PAGOS-1B).
 *
 * Responsabilidades vinculantes (D-107):
 * 1. Orquesta la emisión de intenciones de cobro ante pasarelas externas.
 * 2. Procesa webhooks de forma atómica e idempotente con bloqueos pesimistas SELECT ... FOR UPDATE.
 * 3. Valida estados de hold y vigencia temporal de reservas directas.
 * 4. Aplica el invariante de pagos tardíos: el dinero externo queda registrado en cuarentena
 *    (DISCREPANCIA_HOLD_EXPIRADO) sin crear folios artificiales ni mutar inventario liberado.
 * 5. Si el pago es en tiempo y forma, confirma la reserva e imputa contablemente el cobro en el libro de folios.
 */
class PagoServicio
{
    private PDO $pdo;
    private PagoTransaccionPasarelaRepositorio $txRepo;
    private PagoWebhookEventoRepositorio $webhookRepo;
    private ReservaRepositorio $reservaRepo;
    private PersonaRepositorio $personaRepo;
    private CuentaFolioRepositorio $folioRepo;
    private PagoCuentaRepositorio $pagoCuentaRepo;
    private ReservaServicio $reservaServicio;
    private CuentaFolioServicio $cuentaFolioServicio;
    private ActorAuditoriaRepositorio $actorRepo;

    public function __construct(
        ?PDO $pdo = null,
        ?PagoTransaccionPasarelaRepositorio $txRepo = null,
        ?PagoWebhookEventoRepositorio $webhookRepo = null,
        ?ReservaRepositorio $reservaRepo = null,
        ?PersonaRepositorio $personaRepo = null,
        ?CuentaFolioRepositorio $folioRepo = null,
        ?PagoCuentaRepositorio $pagoCuentaRepo = null,
        ?ReservaServicio $reservaServicio = null,
        ?CuentaFolioServicio $cuentaFolioServicio = null,
        ?ActorAuditoriaRepositorio $actorRepo = null
    ) {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->txRepo = $txRepo ?? new PagoTransaccionPasarelaRepositorio($this->pdo);
        $this->webhookRepo = $webhookRepo ?? new PagoWebhookEventoRepositorio($this->pdo);
        $this->reservaRepo = $reservaRepo ?? new ReservaRepositorio($this->pdo);
        $this->personaRepo = $personaRepo ?? new PersonaRepositorio($this->pdo);
        $this->folioRepo = $folioRepo ?? new CuentaFolioRepositorio($this->pdo);
        $this->pagoCuentaRepo = $pagoCuentaRepo ?? new PagoCuentaRepositorio($this->pdo);
        $this->reservaServicio = $reservaServicio ?? new ReservaServicio($this->pdo);
        $this->cuentaFolioServicio = $cuentaFolioServicio ?? new CuentaFolioServicio($this->pdo);
        $this->actorRepo = $actorRepo ?? new ActorAuditoriaRepositorio($this->pdo);
    }

    /**
     * Inicia una intención de pago para una reserva comercial directa en estado PENDIENTE.
     *
     * @param int $reservaId ID de la reserva a pagar.
     * @param string $proveedor Nombre del proveedor ('CULQI', 'IZIPAY', 'PAYPAL').
     * @param int|null $actorId Actor que solicita la intención (e.g. INTEGRACION).
     * @return array<string, mixed> Envelope del resultado con la transacción creada.
     */
    public function crearIntencionPagoParaReserva(int $reservaId, string $proveedor = 'CULQI', ?int $actorId = null): array
    {
        $reserva = $this->reservaRepo->buscarPorId($reservaId, true);
        if ($reserva === null) {
            return [
                'exito' => false,
                'codigo' => 'RESERVA_NO_ENCONTRADA',
                'mensaje' => "No se encontró la reserva con ID $reservaId",
            ];
        }

        if (!$reserva->esPendiente()) {
            return [
                'exito' => false,
                'codigo' => 'ESTADO_RESERVA_INVALIDO',
                'mensaje' => "La reserva {$reserva->obtenerCodigo()} se encuentra en estado '{$reserva->obtenerEstado()}' y no admite nuevos pagos.",
            ];
        }

        if ($reserva->haExpirado()) {
            return [
                'exito' => false,
                'codigo' => 'HOLD_EXPIRADO',
                'mensaje' => "El tiempo de retención de la reserva {$reserva->obtenerCodigo()} ha vencido.",
            ];
        }

        // Obtener datos del huésped titular
        $titular = $this->personaRepo->buscarPorId((int) $reserva->obtenerPersonaTitularId(), true);
        $emailContacto = $titular?->obtenerContactoPrincipal('EMAIL')?->obtenerValor();
        $telefonoContacto = $titular?->obtenerContactoPrincipal('TELEFONO')?->obtenerValor();
        $email = !empty($emailContacto) ? $emailContacto : 'cliente@camargopms.test';
        $telefono = !empty($telefonoContacto) ? $telefonoContacto : '999999999';
        $nombreCompleto = $titular !== null ? $titular->obtenerNombreCompleto() : 'Huésped Camargo';

        $driver = FabricaProveedoresPago::obtenerProveedor($proveedor);

        $solicitud = new IntencionPagoSolicitud(
            reservaId: $reservaId,
            reservaCodigo: $reserva->obtenerCodigo(),
            monto: $reserva->obtenerTotal(),
            moneda: 'PEN',
            descripcion: "Reserva Camargo PMS {$reserva->obtenerCodigo()} ({$reserva->obtenerNoches()} noches)",
            clienteEmail: $email,
            clienteNombre: $nombreCompleto,
            clienteTelefono: $telefono,
            expiraEn: $reserva->obtenerExpiraEn(),
            metadatos: [
                'reserva_id' => $reservaId,
                'reserva_codigo' => $reserva->obtenerCodigo(),
                'noches' => $reserva->obtenerNoches(),
            ]
        );

        $resultado = $driver->crearIntencionPago($solicitud);

        if (!$resultado->exitoso) {
            return [
                'exito' => false,
                'codigo' => 'ERROR_PROVEEDOR_PAGO',
                'mensaje' => $resultado->mensajeError,
            ];
        }

        $actorFinalId = $actorId ?? $this->resolverActorPasarelaId();

        // Registrar transacción de pasarela en base de datos
        $txId = $this->txRepo->crear([
            'reserva_id' => $reservaId,
            'proveedor' => $proveedor,
            'tipo_operacion' => PagoTransaccionPasarela::OPERACION_ORDEN_CHECKOUT,
            'proveedor_orden_id' => $resultado->proveedorOrdenId,
            'proveedor_referencia' => $reserva->obtenerCodigo(),
            'moneda_codigo' => $resultado->moneda ?? 'PEN',
            'monto_esperado' => $resultado->monto ?? $reserva->obtenerTotal(),
            'estado_pago' => PagoTransaccionPasarela::ESTADO_PAGO_INICIADO,
            'estado_conciliacion' => PagoTransaccionPasarela::ESTADO_CONCILIACION_PENDIENTE,
            'estado_reembolso' => PagoTransaccionPasarela::ESTADO_REEMBOLSO_NO_APLICA,
            'metadatos_proveedor' => $resultado->metadatos,
            'actor_id' => $actorFinalId,
        ]);

        $this->registrarAuditoria(
            accion: 'INTENCION_PAGO_CREADA',
            entidadId: (string) $txId,
            descripcion: "Intención de pago creada ante $proveedor para reserva {$reserva->obtenerCodigo()}. Orden: {$resultado->proveedorOrdenId}",
            valoresNuevos: [
                'transaccion_id' => $txId,
                'proveedor' => $proveedor,
                'orden_id' => $resultado->proveedorOrdenId,
                'monto' => $resultado->monto,
            ],
            actorId: $actorFinalId
        );

        return [
            'exito' => true,
            'codigo' => 'INTENCION_PAGO_CREADA',
            'transaccion_id' => $txId,
            'proveedor' => $proveedor,
            'proveedor_orden_id' => $resultado->proveedorOrdenId,
            'token_transaccion' => $resultado->tokenTransaccion,
            'monto' => $resultado->monto,
            'moneda' => $resultado->moneda,
            'fecha_expiracion' => $resultado->fechaExpiracion,
            'metadatos' => $resultado->metadatos,
        ];
    }

    /**
     * Procesa una notificación webhook entrante de forma atómica e idempotente.
     *
     * @param string $proveedor Identificador del proveedor ('CULQI', 'IZIPAY', 'PAYPAL').
     * @param array<string, string> $headers Cabeceras HTTP de la petición.
     * @param string $cuerpoBruto Contenido crudo del cuerpo HTTP.
     * @return array<string, mixed> Resultado del procesamiento.
     */
    public function procesarWebhook(string $proveedor, array $headers, string $cuerpoBruto): array
    {
        $driver = FabricaProveedoresPago::obtenerProveedor($proveedor);
        $notificacion = $driver->verificarYParsearWebhook($headers, $cuerpoBruto);

        if (!$notificacion->valido) {
            return [
                'exito' => false,
                'codigo' => 'WEBHOOK_INVALIDO',
                'mensaje' => $notificacion->motivoError,
            ];
        }

        // Idempotencia de evento
        $registroWebhook = $this->webhookRepo->registrarOObtener(
            proveedor: $proveedor,
            proveedorEventoId: $notificacion->proveedorEventoId,
            tipoEvento: $notificacion->tipoEvento,
            payloadRaw: $cuerpoBruto,
            cabeceras: $headers
        );

        $webhookId = (int) $registroWebhook['id'];

        // Si ya fue procesado previamente, responder con replay exitoso (idempotencia)
        if (!$registroWebhook['es_nuevo']) {
            $this->webhookRepo->marcarDuplicadoOmitido($webhookId);
            return [
                'exito' => true,
                'codigo' => 'WEBHOOK_YA_PROCESADO',
                'mensaje' => 'El evento ya fue procesado previamente en el sistema.',
                'reintento' => true,
            ];
        }

        // Localizar la transacción asociada
        $tx = null;
        if (!empty($notificacion->proveedorOrdenId)) {
            $tx = $this->txRepo->buscarPorProveedorYOrdenId($proveedor, $notificacion->proveedorOrdenId);
        }
        if ($tx === null && !empty($notificacion->proveedorTransaccionId)) {
            $tx = $this->txRepo->buscarPorProveedorYTransaccionId($proveedor, $notificacion->proveedorTransaccionId);
        }

        if ($tx === null) {
            $this->webhookRepo->marcarError($webhookId, 'No se encontró transacción asociada al webhook', 200);
            return [
                'exito' => true,
                'codigo' => 'WEBHOOK_IGNORADO_SIN_TRANSACCION',
                'mensaje' => 'No se encontró transacción de pasarela correspondiente al identificador recibido.',
            ];
        }

        $txId = (int) $tx->obtenerId();

        // Si el evento no representa un cambio de estado financiero relevante
        if ($notificacion->estadoNormalizado === WebhookNotificacionResultado::ESTADO_IGNORADO) {
            $this->webhookRepo->marcarProcesado($webhookId, $txId);
            return [
                'exito' => true,
                'codigo' => 'WEBHOOK_IGNORADO_INFORMATIVO',
                'mensaje' => 'Evento informativo recibido sin cambios contables requeridos.',
            ];
        }

        // Iniciar transacción ACID para actualización atómica
        $this->pdo->beginTransaction();
        try {
            // Bloqueo pesimista de la transacción de pasarela
            $txBloqueada = $this->txRepo->buscarPorIdParaActualizar($txId);
            if ($txBloqueada === null) {
                throw new RuntimeException("Transacción $txId no accesible bajo bloqueo");
            }

            // Si ya está aprobada, asegurar idempotencia contable
            if ($txBloqueada->estaAprobado()) {
                $this->webhookRepo->marcarProcesado($webhookId, $txId);
                $this->pdo->commit();
                return [
                    'exito' => true,
                    'codigo' => 'TRANSACCION_YA_APROBADA',
                    'mensaje' => 'La transacción ya se encontraba aprobada y conciliada.',
                ];
            }

            // Manejo de eventos de fallo o expiración
            if ($notificacion->estadoNormalizado === WebhookNotificacionResultado::ESTADO_FALLIDO) {
                $this->txRepo->actualizar($txId, [
                    'estado_pago' => PagoTransaccionPasarela::ESTADO_PAGO_FALLIDO,
                    'motivo_discrepancia' => $notificacion->motivoError,
                    'proveedor_transaccion_id' => $notificacion->proveedorTransaccionId,
                ]);
                $this->webhookRepo->marcarProcesado($webhookId, $txId);
                $this->pdo->commit();
                return [
                    'exito' => true,
                    'codigo' => 'PAGO_FALLIDO_REGISTRADO',
                    'mensaje' => 'Transacción marcada como fallida.',
                ];
            }

            if ($notificacion->estadoNormalizado === WebhookNotificacionResultado::ESTADO_EXPIRADO) {
                $this->txRepo->actualizar($txId, [
                    'estado_pago' => PagoTransaccionPasarela::ESTADO_PAGO_EXPIRADO,
                ]);
                $this->webhookRepo->marcarProcesado($webhookId, $txId);
                $this->pdo->commit();
                return [
                    'exito' => true,
                    'codigo' => 'PAGO_EXPIRADO_REGISTRADO',
                    'mensaje' => 'Transacción marcada como expirada.',
                ];
            }

            // APROBACIÓN DE PAGO
            if ($notificacion->estadoNormalizado === WebhookNotificacionResultado::ESTADO_APROBADO) {
                $reservaId = $txBloqueada->obtenerReservaId();

                // Bloqueo pesimista de la reserva
                $stmtReserva = $this->pdo->prepare('SELECT * FROM reservas WHERE id = :id FOR UPDATE');
                $stmtReserva->execute(['id' => $reservaId]);
                $filaReserva = $stmtReserva->fetch(PDO::FETCH_ASSOC);

                if (!$filaReserva) {
                    throw new RuntimeException("Reserva asociada $reservaId no existe");
                }

                $reservaObj = $this->reservaRepo->buscarPorId($reservaId, false);
                $ahora = date('Y-m-d H:i:s');
                $montoEfectivo = $notificacion->monto ?? $txBloqueada->obtenerMontoEsperado();

                // Validación 1: Discrepancia de Monto
                if ($notificacion->monto !== null && bccomp($notificacion->monto, $txBloqueada->obtenerMontoEsperado(), 2) !== 0) {
                    $this->txRepo->actualizar($txId, [
                        'estado_pago' => PagoTransaccionPasarela::ESTADO_PAGO_APROBADO,
                        'estado_conciliacion' => PagoTransaccionPasarela::ESTADO_CONCILIACION_DISCREPANCIA_MONTO,
                        'monto_cobrado' => $notificacion->monto,
                        'proveedor_transaccion_id' => $notificacion->proveedorTransaccionId,
                        'motivo_discrepancia' => "Discrepancia en monto: recibido {$notificacion->monto}, esperado {$txBloqueada->obtenerMontoEsperado()}",
                    ]);

                    $this->registrarAuditoria(
                        accion: 'DISCREPANCIA_PAGO_MONTO',
                        entidadId: (string) $txId,
                        descripcion: "Pago aprobado con discrepancia de monto. Recibido: {$notificacion->monto}, Esperado: {$txBloqueada->obtenerMontoEsperado()}",
                        valoresNuevos: ['estado_conciliacion' => 'DISCREPANCIA_MONTO']
                    );

                    $this->webhookRepo->marcarProcesado($webhookId, $txId);
                    $this->pdo->commit();

                    return [
                        'exito' => true,
                        'codigo' => 'PAGO_APROBADO_CON_DISCREPANCIA_MONTO',
                        'mensaje' => 'Pago aprobado externamente pero puesto en cuarentena por discrepancia de importe.',
                    ];
                }

                // Validación 2: Invariante de Pago Tardío (Hold Expirado)
                $estaExpirada = ($filaReserva['estado'] === Reserva::ESTADO_EXPIRADA) ||
                    (!empty($filaReserva['expira_en']) && $filaReserva['expira_en'] < $ahora);

                if ($estaExpirada) {
                    // PAGO TARDÍO:
                    // Se registra el cobro aprobado de la pasarela, pero NO se crea folio contable
                    // ni se confirma la reserva ni se altera inventario liberado.
                    $this->txRepo->actualizar($txId, [
                        'estado_pago' => PagoTransaccionPasarela::ESTADO_PAGO_APROBADO,
                        'estado_conciliacion' => PagoTransaccionPasarela::ESTADO_CONCILIACION_DISCREPANCIA_HOLD_EXPIRADO,
                        'monto_cobrado' => $montoEfectivo,
                        'proveedor_transaccion_id' => $notificacion->proveedorTransaccionId,
                        'motivo_discrepancia' => "Pago recibido tras la expiración del hold ({$filaReserva['expira_en']}). Transacción en cuarentena para conciliación administrativa.",
                    ]);

                    if ($filaReserva['estado'] !== Reserva::ESTADO_EXPIRADA) {
                        $this->reservaRepo->cambiarEstado($reservaId, Reserva::ESTADO_EXPIRADA);
                    }

                    $this->registrarAuditoria(
                        accion: 'PAGO_TARDIO_HOLD_EXPIRADO',
                        entidadId: (string) $txId,
                        descripcion: "Pago de pasarela {$notificacion->proveedorTransaccionId} recibido sobre reserva expirada {$reservaObj->obtenerCodigo()}. Se establece DISCREPANCIA_HOLD_EXPIRADO sin folio.",
                        valoresNuevos: [
                            'estado_pago' => 'APROBADO',
                            'estado_conciliacion' => 'DISCREPANCIA_HOLD_EXPIRADO',
                            'monto_cobrado' => $montoEfectivo,
                        ]
                    );

                    $this->webhookRepo->marcarProcesado($webhookId, $txId);
                    $this->pdo->commit();

                    return [
                        'exito' => true,
                        'codigo' => 'PAGO_TARDIO_EN_CUARENTENA',
                        'mensaje' => 'El pago fue aprobado externamente pero la reserva ya expiró. Dinero en cuarentena para conciliación/reembolso.',
                    ];
                }

                // FLUJO CANÓNICO DE CONFIRMACIÓN EN TIEMPO Y FORMA
                // 1. Confirmar reserva (cambia estado a CONFIRMADA y genera cargos de alojamiento en folio)
                $actorTecnicoId = $this->resolverActorPasarelaId();
                $this->reservaRepo->cambiarEstado($reservaId, Reserva::ESTADO_CONFIRMADA, [
                    'actor_id' => $actorTecnicoId,
                ]);

                // 2. Asegurar folio y cargos de alojamiento
                $cargosAlojamiento = $this->cuentaFolioServicio->generarCargosAlojamiento($reservaId, $actorTecnicoId);
                $folio = $this->folioRepo->obtenerPorReservaId($reservaId);

                if ($folio === null) {
                    throw new RuntimeException("No se pudo obtener el folio para la reserva $reservaId");
                }

                $folioId = (int) $folio->obtenerId();

                // 3. Imputar el cobro en el libro de pagos_cuenta
                $metodoPagoId = $this->resolverMetodoPagoPasarelaId();
                $pagoCuenta = $this->cuentaFolioServicio->registrarPago(
                    folioId: $folioId,
                    metodoPagoId: $metodoPagoId,
                    montoTotal: $montoEfectivo,
                    referenciaOperacion: $notificacion->proveedorTransaccionId ?? $txBloqueada->obtenerProveedorOrdenId(),
                    sesionCajaId: null,
                    cuentaBancariaId: null,
                    actorId: $actorTecnicoId
                );

                $pagoCuentaId = (int) $pagoCuenta->obtenerId();

                // 4. Imputar/aplicar pago contra los cargos de alojamiento devengados
                $montoRestantePorAplicar = $montoEfectivo;
                foreach ($cargosAlojamiento as $cargo) {
                    $pendienteCargo = $cargo->calcularSaldoPendiente();
                    if (bccomp($pendienteCargo, '0.00', 2) > 0 && bccomp($montoRestantePorAplicar, '0.00', 2) > 0) {
                        $montoAAplicar = bccomp($montoRestantePorAplicar, $pendienteCargo, 2) >= 0 ? $pendienteCargo : $montoRestantePorAplicar;
                        $this->cuentaFolioServicio->aplicarPago(
                            pagoId: $pagoCuentaId,
                            cargoId: (int) $cargo->obtenerId(),
                            montoAplicar: $montoAAplicar,
                            actorId: $actorTecnicoId
                        );
                        $montoRestantePorAplicar = bcsub($montoRestantePorAplicar, $montoAAplicar, 2);
                    }
                }

                // 5. Actualizar transacción de pasarela con folio y estado conciliado
                $this->txRepo->actualizar($txId, [
                    'estado_pago' => PagoTransaccionPasarela::ESTADO_PAGO_APROBADO,
                    'estado_conciliacion' => PagoTransaccionPasarela::ESTADO_CONCILIACION_CONCILIADO,
                    'cuenta_folio_id' => $folioId,
                    'pago_cuenta_id' => $pagoCuentaId,
                    'monto_cobrado' => $montoEfectivo,
                    'proveedor_transaccion_id' => $notificacion->proveedorTransaccionId,
                ]);

                // 6. Auditoría transversal
                $this->registrarAuditoria(
                    accion: 'PAGO_PASARELA_CONCILIADO',
                    entidadId: (string) $txId,
                    descripcion: "Pago aprobado e imputado en folio {$folio->obtenerCodigo()} para reserva {$reservaObj->obtenerCodigo()}. Tx: {$notificacion->proveedorTransaccionId}",
                    valoresNuevos: [
                        'transaccion_id' => $txId,
                        'folio_id' => $folioId,
                        'pago_cuenta_id' => $pagoCuentaId,
                        'monto_cobrado' => $montoEfectivo,
                    ],
                    actorId: $actorTecnicoId
                );

                $this->webhookRepo->marcarProcesado($webhookId, $txId);
                $this->pdo->commit();

                return [
                    'exito' => true,
                    'codigo' => 'PAGO_CONFIRMADO_Y_CONCILIADO',
                    'mensaje' => "Pago confirmado y reserva {$reservaObj->obtenerCodigo()} confirmada exitosamente.",
                    'transaccion_id' => $txId,
                    'reserva_id' => $reservaId,
                    'cuenta_folio_id' => $folioId,
                    'pago_cuenta_id' => $pagoCuentaId,
                ];
            }

            $this->pdo->commit();
            return [
                'exito' => true,
                'codigo' => 'WEBHOOK_PROCESADO',
                'mensaje' => 'Webhook procesado sin cambios de estado.',
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->webhookRepo->marcarError($webhookId, $e->getMessage(), 500);
            throw $e;
        }
    }

    /**
     * Consulta el estado de una transacción de pasarela.
     */
    public function consultarEstadoTransaccion(int $transaccionId): ?array
    {
        $tx = $this->txRepo->buscarPorId($transaccionId);
        return $tx?->aArray();
    }

    /**
     * Procesa una devolución o reembolso sobre una transacción de pasarela aprobada.
     */
    public function procesarReembolso(
        int $transaccionId,
        ?string $monto = null,
        string $motivo = 'Solicitud de cliente',
        ?int $actorId = null
    ): array {
        $tx = $this->txRepo->buscarPorId($transaccionId);
        if ($tx === null) {
            return [
                'exito' => false,
                'codigo' => 'TRANSACCION_NO_ENCONTRADA',
                'mensaje' => "No se encontró la transacción de pasarela $transaccionId",
            ];
        }

        if (!$tx->estaAprobado()) {
            return [
                'exito' => false,
                'codigo' => 'TRANSACCION_NO_APROBADA',
                'mensaje' => 'Solo se pueden reembolsar transacciones en estado APROBADO.',
            ];
        }

        $montoReembolso = $monto ?? ($tx->obtenerMontoCobrado() ?: $tx->obtenerMontoEsperado());
        $proveedorTxId = $tx->obtenerProveedorTransaccionId();

        if (empty($proveedorTxId)) {
            return [
                'exito' => false,
                'codigo' => 'SIN_TRANSACCION_PROVEEDOR',
                'mensaje' => 'La transacción no posee un identificador de cargo del proveedor para reembolsar.',
            ];
        }

        $driver = FabricaProveedoresPago::obtenerProveedor($tx->obtenerProveedor());
        $solicitud = new ReembolsoSolicitud(
            proveedorTransaccionId: $proveedorTxId,
            monto: $montoReembolso,
            moneda: $tx->obtenerMonedaCodigo(),
            motivo: $motivo,
            metadatos: ['transaccion_id' => $transaccionId]
        );

        $resultado = $driver->procesarReembolso($solicitud);

        if (!$resultado->exitoso) {
            $this->txRepo->actualizar($transaccionId, [
                'estado_reembolso' => PagoTransaccionPasarela::ESTADO_REEMBOLSO_FALLIDO,
                'motivo_reembolso' => $resultado->mensajeError,
            ]);

            return [
                'exito' => false,
                'codigo' => 'ERROR_PROVEEDOR_REEMBOLSO',
                'mensaje' => $resultado->mensajeError,
            ];
        }

        $totalReembolsadoAcumulado = bcadd($tx->obtenerMontoReembolsado(), $montoReembolso, 2);
        $montoBase = $tx->obtenerMontoCobrado() ?: $tx->obtenerMontoEsperado();

        $nuevoEstadoReembolso = (bccomp($totalReembolsadoAcumulado, $montoBase, 2) >= 0)
            ? PagoTransaccionPasarela::ESTADO_REEMBOLSO_TOTAL
            : PagoTransaccionPasarela::ESTADO_REEMBOLSO_PARCIAL;

        $this->txRepo->actualizar($transaccionId, [
            'estado_reembolso' => $nuevoEstadoReembolso,
            'monto_reembolsado' => $totalReembolsadoAcumulado,
            'motivo_reembolso' => $motivo,
        ]);

        $this->registrarAuditoria(
            accion: 'REEMBOLSO_PASARELA_PROCESADO',
            entidadId: (string) $transaccionId,
            descripcion: "Reembolso procesado ante {$tx->obtenerProveedor()} por monto {$montoReembolso}. ID: {$resultado->reembolsoId}",
            valoresNuevos: [
                'estado_reembolso' => $nuevoEstadoReembolso,
                'reembolso_id' => $resultado->reembolsoId,
                'monto' => $montoReembolso,
            ],
            actorId: $actorId
        );

        return [
            'exito' => true,
            'codigo' => 'REEMBOLSO_EXITOSO',
            'transaccion_id' => $transaccionId,
            'reembolso_id' => $resultado->reembolsoId,
            'monto' => $montoReembolso,
            'estado_reembolso' => $nuevoEstadoReembolso,
        ];
    }

    private function resolverMetodoPagoPasarelaId(): int
    {
        $stmt = $this->pdo->query("SELECT id FROM metodos_pago WHERE tipo_destino = 'PASARELA_INTERMEDIARIO' AND activo = 1 ORDER BY id ASC LIMIT 1");
        $id = $stmt->fetchColumn();
        return $id ? (int) $id : 2; // Default a TARJETA_CREDITO (ID 2)
    }

    private function resolverActorPasarelaId(): int
    {
        $stmt = $this->pdo->query("SELECT id FROM actores WHERE codigo = 'PASARELA_CULQI' OR tipo = 'PROVEEDOR_PAGO' LIMIT 1");
        $id = $stmt->fetchColumn();
        if ($id) {
            return (int) $id;
        }

        $stmtSis = $this->pdo->query("SELECT id FROM actores WHERE tipo = 'SISTEMA' LIMIT 1");
        $idSis = $stmtSis->fetchColumn();
        return $idSis ? (int) $idSis : 1;
    }

    private function registrarAuditoria(
        string $accion,
        string $entidadId,
        string $descripcion,
        array $valoresNuevos = [],
        ?int $actorId = null
    ): void {
        try {
            $actorFinalId = $actorId ?? $this->resolverActorPasarelaId();
            $sql = 'INSERT INTO auditoria (
                        actor_id,
                        accion,
                        modulo,
                        entidad,
                        entidad_id,
                        descripcion,
                        valores_nuevos,
                        creado_en
                    ) VALUES (
                        :actor_id,
                        :accion,
                        :modulo,
                        :entidad,
                        :entidad_id,
                        :descripcion,
                        :valores_nuevos,
                        NOW()
                    )';

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'actor_id' => $actorFinalId,
                'accion' => $accion,
                'modulo' => 'pagos',
                'entidad' => 'pagos_transacciones_pasarela',
                'entidad_id' => $entidadId,
                'descripcion' => $descripcion,
                'valores_nuevos' => json_encode($valoresNuevos, JSON_UNESCAPED_UNICODE),
            ]);
        } catch (Throwable) {
            // Auditoría defensiva fail-safe
        }
    }
}
