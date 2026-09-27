<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

use CamargoPMS\Excepciones\CompraNoEncontradaExcepcion;
use CamargoPMS\Excepciones\ConflictoCompraExcepcion;
use CamargoPMS\Excepciones\OrdenNoModificableExcepcion;
use CamargoPMS\Excepciones\ValidacionCompraExcepcion;
use CamargoPMS\Modelos\CompraComprobante;
use CamargoPMS\Modelos\CompraComprobanteAplicacion;
use CamargoPMS\Modelos\CompraConformidad;
use CamargoPMS\Modelos\CompraOrden;
use CamargoPMS\Modelos\CompraOrdenLinea;
use CamargoPMS\Modelos\CompraRecepcion;
use CamargoPMS\Modelos\CompraRecepcionLinea;
use CamargoPMS\Modelos\CompraSolicitud;
use CamargoPMS\Modelos\CompraSolicitudLinea;
use CamargoPMS\Modelos\CuentaPorPagar;
use CamargoPMS\Modelos\CxpPago;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Repositorios\CompraRepositorio;
use DateTimeImmutable;
use PDO;
use Throwable;

/**
 * Servicio de Dominio para Abastecimiento y Compras (COMPRAS-1 / D-080).
 *
 * Orquesta de forma desacoplada y soberana:
 * SOLICITUD != ORDEN != RECEPCION/CONFORMIDAD != COMPROBANTE != CUENTA POR PAGAR != PAGO
 * ORDEN != MOVIMIENTO DE INVENTARIO != GASTO != PAGO
 */
class CompraServicio
{
    private PDO $pdo;
    private CompraRepositorio $compraRepo;
    private InventarioServicio $inventarioServicio;
    private CajaServicio $cajaServicio;
    private DocumentoServicio $documentoServicio;

    public function __construct(
        ?PDO $pdo = null,
        ?CompraRepositorio $compraRepo = null,
        ?InventarioServicio $inventarioServicio = null,
        ?CajaServicio $cajaServicio = null,
        ?DocumentoServicio $documentoServicio = null
    ) {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->compraRepo = $compraRepo ?? new CompraRepositorio($this->pdo);
        $this->inventarioServicio = $inventarioServicio ?? new InventarioServicio($this->pdo);
        $this->cajaServicio = $cajaServicio ?? new CajaServicio($this->pdo);
        $this->documentoServicio = $documentoServicio ?? new DocumentoServicio(new \CamargoPMS\Repositorios\DocumentoRepositorio($this->pdo));
    }

    public function obtenerRepositorio(): CompraRepositorio
    {
        return $this->compraRepo;
    }

    // =========================================================================
    // 1. SOLICITUDES DE COMPRA (REQUERIMIENTOS INTERNOS)
    // =========================================================================

    public function crearSolicitud(array $datos, int $actorId): CompraSolicitud
    {
        $area = trim((string) ($datos['departamento_area'] ?? ''));
        if ($area === '') {
            throw new ValidacionCompraExcepcion('El departamento o área solicitante es obligatorio.');
        }

        $lineas = $datos['lineas'] ?? [];
        if (!is_array($lineas) || empty($lineas)) {
            throw new ValidacionCompraExcepcion('La solicitud debe contener al menos una línea de bien o servicio.');
        }

        $fechaLimite = !empty($datos['fecha_limite_requerida']) ? trim((string) $datos['fecha_limite_requerida']) : date('Y-m-d', strtotime('+7 days'));
        $justificacion = !empty($datos['justificacion']) ? trim((string) $datos['justificacion']) : "Requerimiento interno para {$area}";
        $almacenId = !empty($datos['almacen_destino_id']) ? (int) $datos['almacen_destino_id'] : null;
        $unidadId = !empty($datos['unidad_destino_id']) ? (int) $datos['unidad_destino_id'] : null;

        $this->pdo->beginTransaction();
        try {
            $codigoFolio = $this->compraRepo->obtenerSiguienteFolio('SOLICITUD_COMPRA', 'SOL');

            $solicitud = new CompraSolicitud(
                null,
                $codigoFolio,
                $area,
                $almacenId,
                $unidadId,
                $fechaLimite,
                $justificacion,
                CompraSolicitud::ESTADO_PENDIENTE,
                null,
                $actorId,
                null
            );

            $solicitudId = $this->compraRepo->crearSolicitud($solicitud);

            foreach ($lineas as $idx => $l) {
                $tipo = strtoupper(trim((string) ($l['tipo_linea'] ?? 'BIEN')));
                if (!in_array($tipo, ['BIEN', 'SERVICIO'], true)) {
                    throw new ValidacionCompraExcepcion("Tipo de línea inválido en el ítem #" . ($idx + 1));
                }

                $articuloId = null;
                $descServicio = null;
                if ($tipo === 'BIEN') {
                    $articuloId = !empty($l['articulo_id']) ? (int) $l['articulo_id'] : null;
                    if ($articuloId === null || $articuloId <= 0) {
                        throw new ValidacionCompraExcepcion("El artículo es obligatorio para líneas de tipo BIEN (ítem #" . ($idx + 1) . ").");
                    }
                } else {
                    $descServicio = trim((string) ($l['descripcion_servicio'] ?? ''));
                    if ($descServicio === '') {
                        throw new ValidacionCompraExcepcion("La descripción del servicio es obligatoria para líneas de tipo SERVICIO (ítem #" . ($idx + 1) . ").");
                    }
                }

                $cantidad = (string) ($l['cantidad_solicitada'] ?? '0');
                if (!is_numeric($cantidad) || bccomp($cantidad, '0.0000', 4) <= 0) {
                    throw new ValidacionCompraExcepcion("La cantidad solicitada debe ser estrictamente mayor a 0 (ítem #" . ($idx + 1) . ").");
                }

                $especificaciones = !empty($l['especificaciones_tecnicas']) ? trim((string) $l['especificaciones_tecnicas']) : null;

                $solLinea = new CompraSolicitudLinea(
                    null,
                    $solicitudId,
                    $tipo,
                    $articuloId,
                    $descServicio,
                    number_format((float) $cantidad, 4, '.', ''),
                    $especificaciones
                );

                $this->compraRepo->crearSolicitudLinea($solLinea);
            }

            $this->compraRepo->registrarTransicionEstado(
                'SOLICITUD',
                $solicitudId,
                null,
                CompraSolicitud::ESTADO_PENDIENTE,
                $actorId,
                'Registro inicial de requerimiento de compra'
            );

            $this->pdo->commit();
            return $this->compraRepo->buscarSolicitudPorId($solicitudId);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function aprobarSolicitud(int $solicitudId, int $actorId, ?string $correlacionId = null): CompraSolicitud
    {
        $this->pdo->beginTransaction();
        try {
            $solicitud = $this->compraRepo->buscarSolicitudPorId($solicitudId);
            if (!$solicitud) {
                throw new CompraNoEncontradaExcepcion("Solicitud de compra ID [{$solicitudId}] no encontrada.");
            }

            if ($solicitud->obtenerEstado() !== CompraSolicitud::ESTADO_PENDIENTE) {
                throw new ConflictoCompraExcepcion("La solicitud [{$solicitud->obtenerCodigo()}] se encuentra en estado [{$solicitud->obtenerEstado()}] y no puede ser aprobada.");
            }

            $this->compraRepo->actualizarEstadoSolicitud($solicitudId, CompraSolicitud::ESTADO_APROBADA, null, $actorId);

            $this->compraRepo->registrarTransicionEstado(
                'SOLICITUD',
                $solicitudId,
                CompraSolicitud::ESTADO_PENDIENTE,
                CompraSolicitud::ESTADO_APROBADA,
                $actorId,
                'Aprobación de requerimiento de compra',
                $correlacionId
            );

            $this->pdo->commit();
            return $this->compraRepo->buscarSolicitudPorId($solicitudId);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function rechazarSolicitud(int $solicitudId, string $motivo, int $actorId, ?string $correlacionId = null): CompraSolicitud
    {
        $motivoTrim = trim($motivo);
        if ($motivoTrim === '') {
            throw new ValidacionCompraExcepcion('El motivo de rechazo es estrictamente obligatorio.');
        }

        $this->pdo->beginTransaction();
        try {
            $solicitud = $this->compraRepo->buscarSolicitudPorId($solicitudId);
            if (!$solicitud) {
                throw new CompraNoEncontradaExcepcion("Solicitud de compra ID [{$solicitudId}] no encontrada.");
            }

            if ($solicitud->obtenerEstado() !== CompraSolicitud::ESTADO_PENDIENTE) {
                throw new ConflictoCompraExcepcion("La solicitud [{$solicitud->obtenerCodigo()}] ya se encuentra en estado [{$solicitud->obtenerEstado()}].");
            }

            $this->compraRepo->actualizarEstadoSolicitud($solicitudId, CompraSolicitud::ESTADO_RECHAZADA, $motivoTrim, $actorId);

            $this->compraRepo->registrarTransicionEstado(
                'SOLICITUD',
                $solicitudId,
                CompraSolicitud::ESTADO_PENDIENTE,
                CompraSolicitud::ESTADO_RECHAZADA,
                $actorId,
                $motivoTrim,
                $correlacionId
            );

            $this->pdo->commit();
            return $this->compraRepo->buscarSolicitudPorId($solicitudId);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function obtenerSolicitud(int $id): CompraSolicitud
    {
        $sol = $this->compraRepo->buscarSolicitudPorId($id);
        if (!$sol) {
            throw new CompraNoEncontradaExcepcion("Solicitud de compra ID [{$id}] no encontrada.");
        }
        return $sol;
    }

    public function listarSolicitudes(array $filtros = []): array
    {
        return $this->compraRepo->listarSolicitudes($filtros);
    }

    // =========================================================================
    // 2. ÓRDENES DE COMPRA (COMPROMISO COMERCIAL INMUTABLE)
    // =========================================================================

    public function crearOrden(array $datos, int $actorId): CompraOrden
    {
        $proveedorId = (int) ($datos['proveedor_id'] ?? 0);
        if ($proveedorId <= 0) {
            throw new ValidacionCompraExcepcion('Debe seleccionar un proveedor válido.');
        }

        // Validar existencia de proveedor
        $stmtProv = $this->pdo->prepare('SELECT id, estado FROM proveedores WHERE id = :id');
        $stmtProv->execute(['id' => $proveedorId]);
        $provRow = $stmtProv->fetch(PDO::FETCH_ASSOC);
        if (!$provRow) {
            throw new ValidacionCompraExcepcion("El proveedor ID [{$proveedorId}] no existe.");
        }
        if (isset($provRow['estado']) && $provRow['estado'] !== 'ACTIVO') {
            throw new ValidacionCompraExcepcion("El proveedor seleccionado no se encuentra en estado ACTIVO.");
        }

        $solicitudId = !empty($datos['solicitud_id']) ? (int) $datos['solicitud_id'] : null;
        if ($solicitudId !== null) {
            $sol = $this->compraRepo->buscarSolicitudPorId($solicitudId);
            if (!$sol) {
                throw new ValidacionCompraExcepcion("La solicitud vinculada [{$solicitudId}] no existe.");
            }
            if ($sol->obtenerEstado() !== CompraSolicitud::ESTADO_APROBADA) {
                throw new ConflictoCompraExcepcion("La solicitud [{$sol->obtenerCodigo()}] debe estar APROBADA para originar una orden.");
            }
        }

        $lineas = $datos['lineas'] ?? [];
        if (!is_array($lineas) || empty($lineas)) {
            throw new ValidacionCompraExcepcion('La orden de compra debe contener al menos una línea pactada.');
        }

        $moneda = 'PEN'; // D-080 #2
        $condicionPagoRaw = strtoupper(trim((string) ($datos['condicion_pago'] ?? 'CONTADO')));
        $mapCondiciones = [
            'CONTADO' => 'CONTADO',
            'CREDITO_15D' => 'CREDITO_15D',
            'CREDITO_15_DIAS' => 'CREDITO_15D',
            'CREDITO_30D' => 'CREDITO_30D',
            'CREDITO_30_DIAS' => 'CREDITO_30D',
            'ADELANTADO' => 'ADELANTADO',
            'ANTICIPADO' => 'ADELANTADO',
        ];
        $condicionPago = $mapCondiciones[$condicionPagoRaw] ?? 'CONTADO';

        $almacenEntregaId = !empty($datos['almacen_entrega_id']) ? (int) $datos['almacen_entrega_id'] : null;
        $fechaEntrega = !empty($datos['fecha_entrega_esperada']) ? trim((string) $datos['fecha_entrega_esperada']) : date('Y-m-d', strtotime('+15 days'));
        $notas = !empty($datos['notas_comerciales']) ? trim((string) $datos['notas_comerciales']) : null;

        $this->pdo->beginTransaction();
        try {
            $codigoFolio = $this->compraRepo->obtenerSiguienteFolio('ORDEN_COMPRA', 'OC');

            $subtotalAcum = '0.00';
            $impuestoAcum = '0.00';
            $descuentoAcum = '0.00';
            $lineasProcesadas = [];

            foreach ($lineas as $idx => $l) {
                $tipo = strtoupper(trim((string) ($l['tipo_linea'] ?? 'BIEN')));
                if (!in_array($tipo, ['BIEN', 'SERVICIO'], true)) {
                    throw new ValidacionCompraExcepcion("Tipo de línea inválido en el ítem #" . ($idx + 1));
                }

                $articuloId = null;
                $descServicio = null;
                if ($tipo === 'BIEN') {
                    $articuloId = !empty($l['articulo_id']) ? (int) $l['articulo_id'] : null;
                    if ($articuloId === null || $articuloId <= 0) {
                        throw new ValidacionCompraExcepcion("El artículo es obligatorio para bienes (ítem #" . ($idx + 1) . ").");
                    }
                } else {
                    $descServicio = trim((string) ($l['descripcion_servicio'] ?? ''));
                    if ($descServicio === '') {
                        throw new ValidacionCompraExcepcion("La descripción del servicio es obligatoria (ítem #" . ($idx + 1) . ").");
                    }
                }

                $cantidad = (string) ($l['cantidad_pactada'] ?? '0');
                if (!is_numeric($cantidad) || bccomp($cantidad, '0.0000', 4) <= 0) {
                    throw new ValidacionCompraExcepcion("La cantidad pactada debe ser mayor a 0 (ítem #" . ($idx + 1) . ").");
                }

                $precioUnit = (string) ($l['precio_unitario'] ?? '0');
                if (!is_numeric($precioUnit) || bccomp($precioUnit, '0.0000', 4) < 0) {
                    throw new ValidacionCompraExcepcion("El precio unitario no puede ser negativo (ítem #" . ($idx + 1) . ").");
                }

                // Cálculo con BCMath
                $subtotalLinea = bcmul($cantidad, $precioUnit, 2);

                // Manejo de impuestos sin asumir 18% obligatorio si se especifica tasa
                $tasaImpuesto = isset($l['tasa_impuesto']) && is_numeric($l['tasa_impuesto']) ? (string) $l['tasa_impuesto'] : '0.00';
                if (bccomp($tasaImpuesto, '0.00', 2) > 0) {
                    $factor = bcdiv($tasaImpuesto, '100', 4);
                    $impuestoLinea = bcmul($subtotalLinea, $factor, 2);
                } else {
                    $impuestoLinea = isset($l['impuesto_linea']) && is_numeric($l['impuesto_linea']) ? (string) $l['impuesto_linea'] : '0.00';
                }

                $totalLinea = bcadd($subtotalLinea, $impuestoLinea, 2);

                $subtotalAcum = bcadd($subtotalAcum, $subtotalLinea, 2);
                $impuestoAcum = bcadd($impuestoAcum, $impuestoLinea, 2);

                $lineasProcesadas[] = [
                    'tipo_linea' => $tipo,
                    'articulo_id' => $articuloId,
                    'descripcion_servicio' => $descServicio,
                    'cantidad_pactada' => number_format((float) $cantidad, 4, '.', ''),
                    'precio_unitario' => number_format((float) $precioUnit, 4, '.', ''),
                    'subtotal_linea' => $subtotalLinea,
                    'impuesto_linea' => $impuestoLinea,
                    'total_linea' => $totalLinea,
                ];
            }

            $totalAcum = bcadd($subtotalAcum, $impuestoAcum, 2);
            $totalAcum = bcsub($totalAcum, $descuentoAcum, 2);

            $orden = new CompraOrden(
                null,
                $codigoFolio,
                $solicitudId,
                $proveedorId,
                $moneda,
                $condicionPago,
                $almacenEntregaId,
                $fechaEntrega,
                $notas,
                $subtotalAcum,
                $impuestoAcum,
                $descuentoAcum,
                $totalAcum,
                CompraOrden::ESTADO_BORRADOR,
                CompraOrden::RECEPCION_SIN_RECEPCION,
                CompraOrden::FACTURACION_SIN_FACTURAR,
                CompraOrden::PAGO_PENDIENTE,
                $actorId,
                null,
                null
            );

            $ordenId = $this->compraRepo->crearOrden($orden);

            foreach ($lineasProcesadas as $lp) {
                $lineaObj = new CompraOrdenLinea(
                    null,
                    $ordenId,
                    $lp['tipo_linea'],
                    $lp['articulo_id'],
                    $lp['descripcion_servicio'],
                    $lp['cantidad_pactada'],
                    $lp['precio_unitario'],
                    $lp['subtotal_linea'],
                    $lp['impuesto_linea'],
                    $lp['total_linea'],
                    '0.0000'
                );
                $this->compraRepo->crearOrdenLinea($lineaObj);
            }

            $this->compraRepo->registrarTransicionEstado(
                'ORDEN_COMPRA',
                $ordenId,
                null,
                CompraOrden::ESTADO_BORRADOR,
                $actorId,
                'Formulación inicial de orden de compra'
            );

            $this->pdo->commit();
            return $this->compraRepo->buscarOrdenPorId($ordenId);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function aprobarOrden(int $ordenId, int $actorId, ?string $correlacionId = null): CompraOrden
    {
        $this->pdo->beginTransaction();
        try {
            $orden = $this->compraRepo->buscarOrdenPorId($ordenId);
            if (!$orden) {
                throw new CompraNoEncontradaExcepcion("Orden de compra ID [{$ordenId}] no encontrada.");
            }

            if ($orden->obtenerEstadoComercial() !== CompraOrden::ESTADO_BORRADOR) {
                throw new ConflictoCompraExcepcion("La orden [{$orden->obtenerCodigo()}] está en estado [{$orden->obtenerEstadoComercial()}] y no puede ser aprobada.");
            }

            $this->compraRepo->actualizarEstadoComercialOrden($ordenId, CompraOrden::ESTADO_APROBADA, $actorId);

            $this->compraRepo->registrarTransicionEstado(
                'ORDEN_COMPRA',
                $ordenId,
                CompraOrden::ESTADO_BORRADOR,
                CompraOrden::ESTADO_APROBADA,
                $actorId,
                'Aprobación formal y congelamiento de condiciones comerciales',
                $correlacionId
            );

            $this->pdo->commit();
            return $this->compraRepo->buscarOrdenPorId($ordenId);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function cancelarOrden(int $ordenId, string $motivo, int $actorId, ?string $correlacionId = null): CompraOrden
    {
        $motivoTrim = trim($motivo);
        if ($motivoTrim === '') {
            throw new ValidacionCompraExcepcion('El motivo de cancelación es obligatorio.');
        }

        $this->pdo->beginTransaction();
        try {
            $orden = $this->compraRepo->buscarOrdenPorId($ordenId);
            if (!$orden) {
                throw new CompraNoEncontradaExcepcion("Orden de compra ID [{$ordenId}] no encontrada.");
            }

            if ($orden->obtenerEstadoComercial() === CompraOrden::ESTADO_CANCELADA) {
                throw new ConflictoCompraExcepcion("La orden [{$orden->obtenerCodigo()}] ya se encuentra cancelada.");
            }

            // Prohibición D-080: Si ya cuenta con recepciones físicas o pagos, no se puede cancelar arbitrariamente
            if ($orden->obtenerEstadoRecepcion() !== CompraOrden::RECEPCION_SIN_RECEPCION) {
                throw new OrdenNoModificableExcepcion("La orden [{$orden->obtenerCodigo()}] posee recepciones físicas registradas y no puede ser cancelada directamente.");
            }

            if ($orden->obtenerEstadoFacturacion() !== CompraOrden::FACTURACION_SIN_FACTURAR) {
                throw new OrdenNoModificableExcepcion("La orden [{$orden->obtenerCodigo()}] tiene comprobantes fiscales asociados y no puede ser cancelada.");
            }

            if ($orden->obtenerEstadoPago() !== CompraOrden::PAGO_PENDIENTE) {
                throw new OrdenNoModificableExcepcion("La orden [{$orden->obtenerCodigo()}] posee pagos registrados.");
            }

            $estadoAnterior = $orden->obtenerEstadoComercial();
            $this->compraRepo->actualizarEstadoComercialOrden($ordenId, CompraOrden::ESTADO_CANCELADA, null, $motivoTrim);

            $this->compraRepo->registrarTransicionEstado(
                'ORDEN_COMPRA',
                $ordenId,
                $estadoAnterior,
                CompraOrden::ESTADO_CANCELADA,
                $actorId,
                $motivoTrim,
                $correlacionId
            );

            $this->pdo->commit();
            return $this->compraRepo->buscarOrdenPorId($ordenId);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function obtenerOrden(int $id): CompraOrden
    {
        $orden = $this->compraRepo->buscarOrdenPorId($id);
        if (!$orden) {
            throw new CompraNoEncontradaExcepcion("Orden de compra ID [{$id}] no encontrada.");
        }
        return $orden;
    }

    public function obtenerOrdenPorCodigo(string $codigo): CompraOrden
    {
        $orden = $this->compraRepo->buscarOrdenPorCodigo($codigo);
        if (!$orden) {
            throw new CompraNoEncontradaExcepcion("Orden de compra [{$codigo}] no encontrada.");
        }
        return $orden;
    }

    public function listarOrdenes(array $filtros = []): array
    {
        return $this->compraRepo->listarOrdenes($filtros);
    }

    public function emitirPdfOrden(int $ordenId, int $actorId, bool $esBorrador = false): array
    {
        $orden = $this->obtenerOrden($ordenId);
        if (!$esBorrador && $orden->obtenerEstadoComercial() !== CompraOrden::ESTADO_APROBADA && $orden->obtenerEstadoComercial() !== CompraOrden::ESTADO_CERRADA) {
            throw new ConflictoCompraExcepcion("Solo se puede emitir el documento oficial para órdenes en estado APROBADA o CERRADA.");
        }

        return $this->documentoServicio->emitirOrdenCompra($ordenId, $actorId, null, $esBorrador);
    }

    // =========================================================================
    // 3. RECEPCIONES FÍSICAS EN ALMACÉN (BIENES -> KARDEX)
    // =========================================================================

    public function registrarRecepcion(array $datos, int $actorId, ?string $correlacionId = null): CompraRecepcion
    {
        $ordenId = (int) ($datos['orden_compra_id'] ?? 0);
        $almacenId = (int) ($datos['almacen_id'] ?? 0);

        if ($ordenId <= 0) {
            throw new ValidacionCompraExcepcion('Debe especificar una orden de compra válida.');
        }
        if ($almacenId <= 0) {
            throw new ValidacionCompraExcepcion('Debe especificar un almacén de ingreso válido.');
        }

        $lineasRecepcion = $datos['lineas'] ?? [];
        if (!is_array($lineasRecepcion) || empty($lineasRecepcion)) {
            throw new ValidacionCompraExcepcion('Debe ingresar al menos una línea para recibir físicamente.');
        }

        $guiaRemision = !empty($datos['numero_guia_remision']) ? trim((string) $datos['numero_guia_remision']) : null;
        $observaciones = !empty($datos['observaciones']) ? trim((string) $datos['observaciones']) : null;

        $this->pdo->beginTransaction();
        try {
            $orden = $this->compraRepo->buscarOrdenPorId($ordenId);
            if (!$orden) {
                throw new CompraNoEncontradaExcepcion("Orden de compra ID [{$ordenId}] no encontrada.");
            }

            if ($orden->obtenerEstadoComercial() !== CompraOrden::ESTADO_APROBADA) {
                throw new ConflictoCompraExcepcion("La orden [{$orden->obtenerCodigo()}] debe estar APROBADA para recibir mercadería (estado actual: {$orden->obtenerEstadoComercial()}).");
            }

            // Mapear líneas de la orden
            $lineasOrdenMap = [];
            foreach ($orden->obtenerLineas() as $ol) {
                $lineasOrdenMap[(int) $ol->obtenerId()] = $ol;
            }

            $codigoFolio = $this->compraRepo->obtenerSiguienteFolio('RECEPCION_COMPRA', 'REC');

            $recepcion = new CompraRecepcion(
                null,
                $codigoFolio,
                $ordenId,
                $almacenId,
                $guiaRemision,
                new DateTimeImmutable(),
                $observaciones,
                $actorId
            );

            $recepcionId = $this->compraRepo->crearRecepcion($recepcion);

            foreach ($lineasRecepcion as $idx => $lr) {
                $ordenLineaId = (int) ($lr['orden_linea_id'] ?? 0);
                if (!isset($lineasOrdenMap[$ordenLineaId])) {
                    throw new ValidacionCompraExcepcion("La línea de orden #{$ordenLineaId} no pertenece a la orden [{$orden->obtenerCodigo()}].");
                }

                /** @var CompraOrdenLinea $ol */
                $ol = $lineasOrdenMap[$ordenLineaId];

                // Regla vinculante: Bienes van a almacén, Servicios van por Conformidad
                if ($ol->obtenerTipoLinea() !== 'BIEN') {
                    throw new ValidacionCompraExcepcion("La línea #{$ordenLineaId} corresponde a un SERVICIO y debe avalarse mediante Acta de Conformidad, no recepción de almacén.");
                }

                $cantAceptada = (string) ($lr['cantidad_aceptada'] ?? '0');
                $cantRechazada = (string) ($lr['cantidad_rechazada'] ?? '0');

                if (!is_numeric($cantAceptada) || bccomp($cantAceptada, '0.0000', 4) < 0) {
                    throw new ValidacionCompraExcepcion("La cantidad aceptada no puede ser negativa (ítem #" . ($idx + 1) . ").");
                }
                if (!is_numeric($cantRechazada) || bccomp($cantRechazada, '0.0000', 4) < 0) {
                    throw new ValidacionCompraExcepcion("La cantidad rechazada no puede ser negativa (ítem #" . ($idx + 1) . ").");
                }

                $cantRecibida = bcadd($cantAceptada, $cantRechazada, 4);
                if (bccomp($cantRecibida, '0.0000', 4) <= 0) {
                    throw new ValidacionCompraExcepcion("Debe recibir una cantidad mayor a 0 en la línea #{$ordenLineaId}.");
                }

                $motivoRechazo = !empty($lr['motivo_rechazo']) ? trim((string) $lr['motivo_rechazo']) : null;
                if (bccomp($cantRechazada, '0.0000', 4) > 0 && ($motivoRechazo === null || $motivoRechazo === '')) {
                    throw new ValidacionCompraExcepcion("Debe indicar el motivo de rechazo formal si existen unidades rechazadas (ítem #" . ($idx + 1) . ").");
                }

                // Validar saldo pendiente de la línea
                $cantAceptadaPrevia = $this->compraRepo->sumarCantidadAceptadaPorOrdenLinea($ordenLineaId);
                $saldoPendiente = bcsub($ol->obtenerCantidadPactada(), $cantAceptadaPrevia, 4);

                if (bccomp($cantAceptada, $saldoPendiente, 4) > 0) {
                    throw new ValidacionCompraExcepcion("La cantidad aceptada [{$cantAceptada}] excede el saldo pendiente [{$saldoPendiente}] de la línea de orden #{$ordenLineaId}.");
                }

                // D-080 #7: Aceptado != Recibido. Solo lo aceptado genera ENTRADA_COMPRA en Kardex
                $movimientoInventarioId = null;
                if (bccomp($cantAceptada, '0.0000', 4) > 0) {
                    $mov = $this->inventarioServicio->registrarEntradaCompra(
                        (int) $ol->obtenerArticuloId(),
                        $almacenId,
                        $cantAceptada,
                        $ol->obtenerPrecioUnitario(),
                        $actorId,
                        "Ingreso por Recepción {$codigoFolio} de OC {$orden->obtenerCodigo()}",
                        $orden->obtenerProveedorId()
                    );
                    $movimientoInventarioId = (int) $mov->obtenerId();
                }

                $recLinea = new CompraRecepcionLinea(
                    null,
                    $recepcionId,
                    $ordenLineaId,
                    (int) $ol->obtenerArticuloId(),
                    number_format((float) $cantRecibida, 4, '.', ''),
                    number_format((float) $cantAceptada, 4, '.', ''),
                    number_format((float) $cantRechazada, 4, '.', ''),
                    $motivoRechazo,
                    $movimientoInventarioId
                );
                $this->compraRepo->crearRecepcionLinea($recLinea);

                // Acumular cantidad aceptada en la línea de la orden
                $this->compraRepo->acumularCantidadAceptadaLinea($ordenLineaId, $cantAceptada);
            }

            // Recalcular estado_recepcion de la orden
            $ordenActualizada = $this->compraRepo->buscarOrdenPorId($ordenId);
            $totalBienesPactados = '0.0000';
            $totalBienesAceptados = '0.0000';

            foreach ($ordenActualizada->obtenerLineas() as $ol) {
                if ($ol->obtenerTipoLinea() === 'BIEN') {
                    $totalBienesPactados = bcadd($totalBienesPactados, $ol->obtenerCantidadPactada(), 4);
                    $totalBienesAceptados = bcadd($totalBienesAceptados, $ol->obtenerCantidadAceptada(), 4);
                }
            }

            $nuevoEstadoRecepcion = CompraOrden::RECEPCION_SIN_RECEPCION;
            if (bccomp($totalBienesAceptados, '0.0000', 4) > 0) {
                if (bccomp($totalBienesAceptados, $totalBienesPactados, 4) >= 0) {
                    $nuevoEstadoRecepcion = CompraOrden::RECEPCION_RECEPCION_TOTAL;
                } else {
                    $nuevoEstadoRecepcion = CompraOrden::RECEPCION_RECEPCION_PARCIAL;
                }
            }

            $this->compraRepo->actualizarEstadosOrden($ordenId, $nuevoEstadoRecepcion, null, null);

            $this->pdo->commit();
            return $this->compraRepo->buscarRecepcionPorId($recepcionId);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function obtenerRecepcion(int $id): CompraRecepcion
    {
        $rec = $this->compraRepo->buscarRecepcionPorId($id);
        if (!$rec) {
            throw new CompraNoEncontradaExcepcion("Recepción física ID [{$id}] no encontrada.");
        }
        return $rec;
    }

    public function listarRecepcionesPorOrden(int $ordenId): array
    {
        return $this->compraRepo->listarRecepcionesPorOrden($ordenId);
    }

    // =========================================================================
    // 4. CONFORMIDAD DE SERVICIOS (SERVICIOS -> CERO KARDEX)
    // =========================================================================

    public function registrarConformidad(array $datos, int $actorId, ?string $correlacionId = null): CompraConformidad
    {
        $ordenId = (int) ($datos['orden_compra_id'] ?? 0);
        $ordenLineaId = (int) ($datos['orden_linea_id'] ?? 0);

        if ($ordenId <= 0 || $ordenLineaId <= 0) {
            throw new ValidacionCompraExcepcion('Debe especificar la orden y la línea de servicio.');
        }

        $informe = trim((string) ($datos['informe_trabajo_realizado'] ?? ''));
        if ($informe === '') {
            throw new ValidacionCompraExcepcion('El informe técnico del trabajo realizado es obligatorio.');
        }

        $this->pdo->beginTransaction();
        try {
            $orden = $this->compraRepo->buscarOrdenPorId($ordenId);
            if (!$orden) {
                throw new CompraNoEncontradaExcepcion("Orden de compra ID [{$ordenId}] no encontrada.");
            }

            if ($orden->obtenerEstadoComercial() !== CompraOrden::ESTADO_APROBADA) {
                throw new ConflictoCompraExcepcion("La orden [{$orden->obtenerCodigo()}] debe estar APROBADA para emitir actas de conformidad.");
            }

            $lineaTarget = null;
            foreach ($orden->obtenerLineas() as $ol) {
                if ((int) $ol->obtenerId() === $ordenLineaId) {
                    $lineaTarget = $ol;
                    break;
                }
            }

            if (!$lineaTarget) {
                throw new ValidacionCompraExcepcion("La línea #{$ordenLineaId} no pertenece a la orden de compra.");
            }

            if ($lineaTarget->obtenerTipoLinea() !== 'SERVICIO') {
                throw new ValidacionCompraExcepcion("La línea #{$ordenLineaId} es un BIEN y requiere recepción física en almacén, no acta de conformidad.");
            }

            if (bccomp($lineaTarget->obtenerCantidadAceptada(), '0.0000', 4) > 0) {
                throw new ConflictoCompraExcepcion("La línea de servicio ya cuenta con conformidad otorgada previamente.");
            }

            $codigoFolio = $this->compraRepo->obtenerSiguienteFolio('CONFORMIDAD_SERVICIO', 'CONF');

            $conf = new CompraConformidad(
                null,
                $codigoFolio,
                $ordenId,
                $ordenLineaId,
                new DateTimeImmutable(),
                $informe,
                $actorId
            );

            $confId = $this->compraRepo->crearConformidad($conf);

            // Marcar cantidad aceptada de servicio
            $this->compraRepo->acumularCantidadAceptadaLinea($ordenLineaId, $lineaTarget->obtenerCantidadPactada());

            $this->pdo->commit();
            return $this->compraRepo->buscarConformidadPorId($confId);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function obtenerConformidad(int $id): CompraConformidad
    {
        $conf = $this->compraRepo->buscarConformidadPorId($id);
        if (!$conf) {
            throw new CompraNoEncontradaExcepcion("Acta de conformidad ID [{$id}] no encontrada.");
        }
        return $conf;
    }

    public function listarConformidadesPorOrden(int $ordenId): array
    {
        return $this->compraRepo->listarConformidadesPorOrden($ordenId);
    }

    // =========================================================================
    // 5. COMPROBANTES DEL PROVEEDOR, 3-WAY MATCHING Y DEVENGADO (CXP)
    // =========================================================================

    public function registrarComprobante(array $datos, int $actorId, ?string $correlacionId = null): array
    {
        $ordenId = (int) ($datos['orden_compra_id'] ?? 0);
        if ($ordenId <= 0) {
            throw new ValidacionCompraExcepcion('Debe vincular el comprobante a una orden de compra.');
        }

        $tipo = strtoupper(trim((string) ($datos['tipo_comprobante'] ?? 'FACTURA')));
        if (!in_array($tipo, ['FACTURA', 'BOLETA', 'RECIBO_HONORARIOS', 'NOTA_CREDITO', 'NOTA_DEBITO'], true)) {
            throw new ValidacionCompraExcepcion("Tipo de comprobante tributario inválido [{$tipo}].");
        }

        $serie = strtoupper(trim((string) ($datos['serie'] ?? '')));
        $numero = trim((string) ($datos['numero'] ?? ''));
        if ($serie === '' || $numero === '') {
            throw new ValidacionCompraExcepcion('La serie y el número del comprobante son obligatorios.');
        }

        $fechaEmision = trim((string) ($datos['fecha_emision'] ?? ''));
        $fechaVencimiento = trim((string) ($datos['fecha_vencimiento'] ?? $fechaEmision));
        if ($fechaEmision === '') {
            throw new ValidacionCompraExcepcion('La fecha de emisión del comprobante es obligatoria.');
        }

        $subtotal = (string) ($datos['subtotal'] ?? '0.00');
        $impuesto = (string) ($datos['impuesto'] ?? '0.00');
        $total = (string) ($datos['total'] ?? '0.00');

        if (!is_numeric($total) || bccomp($total, '0.00', 2) <= 0) {
            throw new ValidacionCompraExcepcion('El importe total del comprobante debe ser estrictamente mayor a 0.');
        }

        $this->pdo->beginTransaction();
        try {
            $orden = $this->compraRepo->buscarOrdenPorId($ordenId);
            if (!$orden) {
                throw new CompraNoEncontradaExcepcion("Orden de compra ID [{$ordenId}] no encontrada.");
            }

            if ($orden->obtenerEstadoComercial() !== CompraOrden::ESTADO_APROBADA && $orden->obtenerEstadoComercial() !== CompraOrden::ESTADO_CERRADA) {
                throw new ConflictoCompraExcepcion("Solo se pueden asociar comprobantes a órdenes en estado APROBADA o CERRADA.");
            }

            $proveedorId = $orden->obtenerProveedorId();

            // Unicidad comercial fiscal (D-080 #11)
            if ($this->compraRepo->existeComprobanteFiscal($proveedorId, $tipo, $serie, $numero)) {
                throw new ConflictoCompraExcepcion("El comprobante {$tipo} [{$serie}-{$numero}] ya fue registrado previamente para este proveedor.");
            }

            // Aplicaciones M:N
            $aplicaciones = $datos['aplicaciones'] ?? [];
            $montoAplicadoAcumulado = '0.00';
            $aplicacionesProcesadas = [];

            if (is_array($aplicaciones) && !empty($aplicaciones)) {
                foreach ($aplicaciones as $ap) {
                    $montoAp = (string) ($ap['monto_aplicado'] ?? '0.00');
                    if (is_numeric($montoAp) && bccomp($montoAp, '0.00', 2) > 0) {
                        $montoAplicadoAcumulado = bcadd($montoAplicadoAcumulado, $montoAp, 2);
                        $aplicacionesProcesadas[] = [
                            'recepcion_linea_id' => !empty($ap['recepcion_linea_id']) ? (int) $ap['recepcion_linea_id'] : null,
                            'conformidad_id' => !empty($ap['conformidad_id']) ? (int) $ap['conformidad_id'] : null,
                            'monto_aplicado' => number_format((float) $montoAp, 2, '.', ''),
                        ];
                    }
                }
            }

            // 3-Way Matching (D-080 #9)
            $estadoMatching = CompraComprobante::MATCHING_CONFORME;
            $obsMatching = 'Matching 3-way conforme con recepciones y cotización';

            // Evaluar discrepancia monetaria si se declararon aplicaciones
            if (!empty($aplicacionesProcesadas)) {
                if (bccomp($montoAplicadoAcumulado, $total, 2) !== 0) {
                    $estadoMatching = CompraComprobante::MATCHING_CON_DIFERENCIA;
                    $obsMatching = "Diferencia de importes: Total Comprobante [{$total}] vs Total Aplicado de Recepciones/Conformidades [{$montoAplicadoAcumulado}].";
                }
            } else {
                // Si no mandaron detalle línea por línea, comparar directamente con el total de la orden
                if (bccomp($total, $orden->obtenerTotal(), 2) !== 0) {
                    $estadoMatching = CompraComprobante::MATCHING_CON_DIFERENCIA;
                    $obsMatching = "El total de la factura ({$total}) difiere del total pactado en la OC ({$orden->obtenerTotal()}).";
                }
            }

            $comprobante = new CompraComprobante(
                null,
                $proveedorId,
                $ordenId,
                $tipo,
                $serie,
                $numero,
                $fechaEmision,
                $fechaVencimiento,
                $orden->obtenerMonedaCodigo(),
                number_format((float) $subtotal, 2, '.', ''),
                number_format((float) $impuesto, 2, '.', ''),
                number_format((float) $total, 2, '.', ''),
                $estadoMatching,
                $obsMatching,
                $actorId
            );

            $comprobanteId = $this->compraRepo->crearComprobante($comprobante);

            // Guardar aplicaciones
            foreach ($aplicacionesProcesadas as $apP) {
                $appObj = new CompraComprobanteAplicacion(
                    null,
                    $comprobanteId,
                    $apP['recepcion_linea_id'],
                    $apP['conformidad_id'],
                    $apP['monto_aplicado']
                );
                $this->compraRepo->crearComprobanteAplicacion($appObj);
            }

            // Devengar Cuenta por Pagar (D-080 #13)
            $codigoCxp = $this->compraRepo->obtenerSiguienteFolio('CUENTA_POR_PAGAR', 'CXP');

            $cxp = new CuentaPorPagar(
                null,
                $codigoCxp,
                $proveedorId,
                $comprobanteId,
                $ordenId,
                number_format((float) $total, 2, '.', ''),
                '0.00',
                number_format((float) $total, 2, '.', ''),
                $fechaVencimiento,
                CuentaPorPagar::ESTADO_PENDIENTE
            );

            $cxpId = $this->compraRepo->crearCuentaPorPagar($cxp);

            $this->compraRepo->registrarTransicionEstado(
                'CUENTA_POR_PAGAR',
                $cxpId,
                null,
                CuentaPorPagar::ESTADO_PENDIENTE,
                $actorId,
                "Devengado de pasivo por comprobante {$tipo} {$serie}-{$numero}",
                $correlacionId
            );

            // Actualizar estado de facturación de la orden
            $comprobantesOrden = $this->compraRepo->listarComprobantesPorOrden($ordenId);
            $totalFacturado = '0.00';
            foreach ($comprobantesOrden as $co) {
                $totalFacturado = bcadd($totalFacturado, $co->obtenerTotal(), 2);
            }

            $nuevoEstadoFact = CompraOrden::FACTURACION_SIN_FACTURAR;
            if (bccomp($totalFacturado, '0.00', 2) > 0) {
                if (bccomp($totalFacturado, $orden->obtenerTotal(), 2) >= 0) {
                    $nuevoEstadoFact = CompraOrden::FACTURACION_FACTURADA_TOTAL;
                } else {
                    $nuevoEstadoFact = CompraOrden::FACTURACION_FACTURADA_PARCIAL;
                }
            }

            $this->compraRepo->actualizarEstadosOrden($ordenId, null, $nuevoEstadoFact, null);

            $this->pdo->commit();

            return [
                'comprobante' => $this->compraRepo->buscarComprobantePorId($comprobanteId),
                'cuenta_por_pagar' => $this->compraRepo->buscarCuentaPorPagarPorId($cxpId),
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function obtenerComprobante(int $id): CompraComprobante
    {
        $comp = $this->compraRepo->buscarComprobantePorId($id);
        if (!$comp) {
            throw new CompraNoEncontradaExcepcion("Comprobante ID [{$id}] no encontrado.");
        }
        return $comp;
    }

    public function listarComprobantesPorOrden(int $ordenId): array
    {
        return $this->compraRepo->listarComprobantesPorOrden($ordenId);
    }

    // =========================================================================
    // 6. CUENTAS POR PAGAR Y AMORTIZACIONES / PAGOS (FINANCIERO-2)
    // =========================================================================

    public function obtenerCuentaPorPagar(int $id): CuentaPorPagar
    {
        $cxp = $this->compraRepo->buscarCuentaPorPagarPorId($id);
        if (!$cxp) {
            throw new CompraNoEncontradaExcepcion("Cuenta por pagar ID [{$id}] no encontrada.");
        }
        return $cxp;
    }

    public function listarCuentasPorPagar(array $filtros = []): array
    {
        return $this->compraRepo->listarCuentasPorPagar($filtros);
    }

    public function registrarPagoCxp(array $datos, int $actorId, ?string $correlacionId = null): CxpPago
    {
        $cxpId = (int) ($datos['cuenta_pagar_id'] ?? 0);
        if ($cxpId <= 0) {
            throw new ValidacionCompraExcepcion('Debe especificar una cuenta por pagar válida.');
        }

        $monto = (string) ($datos['monto'] ?? '0.00');
        if (!is_numeric($monto) || bccomp($monto, '0.00', 2) <= 0) {
            throw new ValidacionCompraExcepcion('El monto del pago debe ser estrictamente mayor a 0.');
        }

        $medioPago = strtoupper(trim((string) ($datos['medio_pago'] ?? 'TRANSFERENCIA_BANCARIA')));
        if (!in_array($medioPago, ['EFECTIVO_CAJA', 'TRANSFERENCIA_BANCARIA', 'CHEQUE', 'BILLETERA_DIGITAL'], true)) {
            throw new ValidacionCompraExcepcion("Medio de pago inválido [{$medioPago}].");
        }

        $numeroOperacion = !empty($datos['numero_operacion_bancaria']) ? trim((string) $datos['numero_operacion_bancaria']) : null;
        $notas = !empty($datos['notas']) ? trim((string) $datos['notas']) : null;

        $this->pdo->beginTransaction();
        try {
            // Lock pesimista sobre la cuenta por pagar
            $stmtLock = $this->pdo->prepare('SELECT * FROM cuentas_por_pagar WHERE id = :id FOR UPDATE');
            $stmtLock->execute(['id' => $cxpId]);
            $cxpRow = $stmtLock->fetch(PDO::FETCH_ASSOC);

            if (!$cxpRow) {
                throw new CompraNoEncontradaExcepcion("Cuenta por pagar ID [{$cxpId}] no encontrada.");
            }

            if ($cxpRow['estado'] === CuentaPorPagar::ESTADO_LIQUIDADA || $cxpRow['estado'] === CuentaPorPagar::ESTADO_ANULADA) {
                throw new ConflictoCompraExcepcion("La cuenta por pagar se encuentra en estado [{$cxpRow['estado']}] y no admite más pagos.");
            }

            $saldoPendiente = (string) $cxpRow['saldo_pendiente'];
            if (bccomp($monto, $saldoPendiente, 2) > 0) {
                throw new ValidacionCompraExcepcion("El monto a pagar [{$monto}] excede el saldo pendiente de la cuenta por pagar [{$saldoPendiente}].");
            }

            // Integración soberana con FINANCIERO-2 (Caja Chica si es EFECTIVO_CAJA)
            $movimientoCajaId = null;
            if ($medioPago === 'EFECTIVO_CAJA') {
                $sesionCajaId = !empty($datos['sesion_caja_id']) ? (int) $datos['sesion_caja_id'] : null;
                if ($sesionCajaId !== null && $sesionCajaId > 0) {
                    $movCaja = $this->cajaServicio->registrarMovimientoManual(
                        $sesionCajaId,
                        'EGRESO_GASTO_MENOR',
                        $monto,
                        "Pago a proveedor CxP {$cxpRow['codigo']}",
                        $actorId
                    );
                    $movimientoCajaId = (int) $movCaja->obtenerId();
                }
            }

            $movimientoBancarioId = !empty($datos['movimiento_bancario_id']) ? (int) $datos['movimiento_bancario_id'] : null;

            $pago = new CxpPago(
                null,
                $cxpId,
                $medioPago,
                number_format((float) $monto, 2, '.', ''),
                new DateTimeImmutable(),
                $numeroOperacion,
                $movimientoCajaId,
                $movimientoBancarioId,
                $notas,
                $actorId
            );

            $pagoId = $this->compraRepo->registrarPago($pago);

            // Actualizar saldos reconstructibles
            $montoAmortizadoPrevio = (string) $cxpRow['monto_amortizado'];
            $montoTotal = (string) $cxpRow['monto_total'];

            $nuevoAmortizado = bcadd($montoAmortizadoPrevio, $monto, 2);
            $nuevoSaldo = bcsub($montoTotal, $nuevoAmortizado, 2);

            $nuevoEstado = bccomp($nuevoSaldo, '0.00', 2) === 0
                ? CuentaPorPagar::ESTADO_LIQUIDADA
                : CuentaPorPagar::ESTADO_AMORTIZADA_PARCIAL;

            $this->compraRepo->actualizarSaldosCuentaPorPagar($cxpId, $nuevoAmortizado, $nuevoSaldo, $nuevoEstado);

            $this->compraRepo->registrarTransicionEstado(
                'CUENTA_POR_PAGAR',
                $cxpId,
                $cxpRow['estado'],
                $nuevoEstado,
                $actorId,
                "Amortización de {$monto} PEN vía {$medioPago}",
                $correlacionId
            );

            // Recalcular estado de pago de la orden
            $ordenId = (int) $cxpRow['orden_compra_id'];
            $orden = $this->compraRepo->buscarOrdenPorId($ordenId);

            if ($orden) {
                $stmtCxpOrden = $this->pdo->prepare('SELECT SUM(monto_amortizado) AS amortizado, SUM(monto_total) AS total FROM cuentas_por_pagar WHERE orden_compra_id = :oid');
                $stmtCxpOrden->execute(['oid' => $ordenId]);
                $totalesCxp = $stmtCxpOrden->fetch(PDO::FETCH_ASSOC);

                $amortizadoOrden = (string) ($totalesCxp['amortizado'] ?? '0.00');
                $nuevoEstadoPago = CompraOrden::PAGO_PENDIENTE;

                if (bccomp($amortizadoOrden, '0.00', 2) > 0) {
                    if (bccomp($amortizadoOrden, $orden->obtenerTotal(), 2) >= 0) {
                        $nuevoEstadoPago = CompraOrden::PAGO_PAGADO_TOTAL;
                    } else {
                        $nuevoEstadoPago = CompraOrden::PAGO_PAGADO_PARCIAL;
                    }
                }

                $this->compraRepo->actualizarEstadosOrden($ordenId, null, null, $nuevoEstadoPago);
            }

            $this->pdo->commit();
            $pagos = $this->compraRepo->listarPagosPorCuentaPagar($cxpId);
            return end($pagos);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function listarPagosPorCuentaPagar(int $cxpId): array
    {
        return $this->compraRepo->listarPagosPorCuentaPagar($cxpId);
    }
}
