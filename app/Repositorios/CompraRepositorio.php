<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\CompraSolicitud;
use CamargoPMS\Modelos\CompraSolicitudLinea;
use CamargoPMS\Modelos\CompraOrden;
use CamargoPMS\Modelos\CompraOrdenLinea;
use CamargoPMS\Modelos\CompraRecepcion;
use CamargoPMS\Modelos\CompraRecepcionLinea;
use CamargoPMS\Modelos\CompraConformidad;
use CamargoPMS\Modelos\CompraComprobante;
use CamargoPMS\Modelos\CompraComprobanteAplicacion;
use CamargoPMS\Modelos\CuentaPorPagar;
use CamargoPMS\Modelos\CxpPago;
use DateTimeImmutable;
use PDO;

class CompraRepositorio
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function obtenerConexion(): PDO
    {
        return $this->pdo;
    }

    // -------------------------------------------------------------------------
    // GENERADOR DE FOLIOS CONCURRENCY-SAFE (FOR UPDATE)
    // -------------------------------------------------------------------------

    public function obtenerSiguienteFolio(string $tipoDocumento, string $prefijo, ?DateTimeImmutable $fecha = null): string
    {
        $fechaOperacion = $fecha ?? new DateTimeImmutable('now');
        $periodoYm = $fechaOperacion->format('Ym');

        // 1. Asegurar existencia de fila con ON DUPLICATE KEY
        $stmtInit = $this->pdo->prepare(
            'INSERT INTO documento_secuencias (tipo_documento, periodo_ym, ultimo_correlativo)
             VALUES (:tipo, :periodo, 0)
             ON DUPLICATE KEY UPDATE ultimo_correlativo = ultimo_correlativo'
        );
        $stmtInit->execute([
            'tipo' => $tipoDocumento,
            'periodo' => $periodoYm,
        ]);

        // 2. Bloquear pesimistamente la fila con FOR UPDATE
        $stmtLock = $this->pdo->prepare(
            'SELECT ultimo_correlativo
             FROM documento_secuencias
             WHERE tipo_documento = :tipo AND periodo_ym = :periodo
             FOR UPDATE'
        );
        $stmtLock->execute([
            'tipo' => $tipoDocumento,
            'periodo' => $periodoYm,
        ]);

        $correlativoActual = (int) $stmtLock->fetchColumn();
        $nuevoCorrelativo = $correlativoActual + 1;

        // 3. Actualizar el correlativo
        $stmtUpdate = $this->pdo->prepare(
            'UPDATE documento_secuencias
             SET ultimo_correlativo = :nuevo
             WHERE tipo_documento = :tipo AND periodo_ym = :periodo'
        );
        $stmtUpdate->execute([
            'nuevo' => $nuevoCorrelativo,
            'tipo' => $tipoDocumento,
            'periodo' => $periodoYm,
        ]);

        return sprintf('%s-%s-%04d', $prefijo, $periodoYm, $nuevoCorrelativo);
    }

    // -------------------------------------------------------------------------
    // SOLICITUDES DE COMPRA
    // -------------------------------------------------------------------------

    public function crearSolicitud(CompraSolicitud $solicitud): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO compra_solicitudes (
                codigo, departamento_area, almacen_destino_id, unidad_destino_id,
                fecha_limite_requerida, justificacion, estado, motivo_rechazo,
                solicitado_por_actor_id, aprobado_por_actor_id
            ) VALUES (
                :codigo, :area, :almacenId, :unidadId,
                :fechaLimite, :justificacion, :estado, :motivoRechazo,
                :solicitadoPor, :aprobadoPor
            )'
        );
        $stmt->execute([
            'codigo' => $solicitud->obtenerCodigo(),
            'area' => $solicitud->obtenerDepartamentoArea(),
            'almacenId' => $solicitud->obtenerAlmacenDestinoId(),
            'unidadId' => $solicitud->obtenerUnidadDestinoId(),
            'fechaLimite' => $solicitud->obtenerFechaLimiteRequerida(),
            'justificacion' => $solicitud->obtenerJustificacion(),
            'estado' => $solicitud->obtenerEstado(),
            'motivoRechazo' => $solicitud->obtenerMotivoRechazo(),
            'solicitadoPor' => $solicitud->obtenerSolicitadoPorActorId(),
            'aprobadoPor' => $solicitud->obtenerAprobadoPorActorId(),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function crearSolicitudLinea(CompraSolicitudLinea $linea): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO compra_solicitud_lineas (
                solicitud_id, tipo_linea, articulo_id, descripcion_servicio,
                cantidad_solicitada, especificaciones_tecnicas
            ) VALUES (
                :solicitudId, :tipoLinea, :articuloId, :descServicio,
                :cantidad, :especificaciones
            )'
        );
        $stmt->execute([
            'solicitudId' => $linea->obtenerSolicitudId(),
            'tipoLinea' => $linea->obtenerTipoLinea(),
            'articuloId' => $linea->obtenerArticuloId(),
            'descServicio' => $linea->obtenerDescripcionServicio(),
            'cantidad' => $linea->obtenerCantidadSolicitada(),
            'especificaciones' => $linea->obtenerEspecificacionesTecnicas(),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function buscarSolicitudPorId(int $id): ?CompraSolicitud
    {
        $stmt = $this->pdo->prepare('SELECT * FROM compra_solicitudes WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $solicitud = $this->hidratarSolicitud($row);

        // Hidratar líneas
        $stmtLineas = $this->pdo->prepare('SELECT * FROM compra_solicitud_lineas WHERE solicitud_id = :id ORDER BY id ASC');
        $stmtLineas->execute(['id' => $id]);
        $lineas = [];
        while ($lRow = $stmtLineas->fetch(PDO::FETCH_ASSOC)) {
            $lineas[] = $this->hidratarSolicitudLinea($lRow);
        }
        $solicitud->asignarLineas($lineas);

        return $solicitud;
    }

    public function listarSolicitudes(array $filtros = []): array
    {
        $sql = 'SELECT * FROM compra_solicitudes WHERE 1=1';
        $params = [];

        if (!empty($filtros['estado'])) {
            $sql .= ' AND estado = :estado';
            $params['estado'] = $filtros['estado'];
        }
        if (!empty($filtros['area'])) {
            $sql .= ' AND departamento_area LIKE :area';
            $params['area'] = '%' . $filtros['area'] . '%';
        }

        $sql .= ' ORDER BY id DESC LIMIT 100';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $solicitudes = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $solicitud = $this->hidratarSolicitud($row);
            $solicitudes[] = $solicitud;
        }

        return $solicitudes;
    }

    public function actualizarEstadoSolicitud(int $id, string $nuevoEstado, ?string $motivoRechazo = null, ?int $aprobadorActorId = null): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE compra_solicitudes
             SET estado = :estado, motivo_rechazo = :motivo, aprobado_por_actor_id = :aprobador
             WHERE id = :id'
        );
        return $stmt->execute([
            'estado' => $nuevoEstado,
            'motivo' => $motivoRechazo,
            'aprobador' => $aprobadorActorId,
            'id' => $id,
        ]);
    }

    // -------------------------------------------------------------------------
    // ÓRDENES DE COMPRA
    // -------------------------------------------------------------------------

    public function crearOrden(CompraOrden $orden): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO compra_ordenes (
                codigo, solicitud_id, proveedor_id, moneda_codigo, condicion_pago,
                almacen_entrega_id, fecha_entrega_esperada, notas_comerciales,
                subtotal, impuesto_total, descuento_total, total,
                estado_comercial, estado_recepcion, estado_facturacion, estado_pago,
                creado_por_actor_id, aprobado_por_actor_id, motivo_cancelacion
            ) VALUES (
                :codigo, :solicitudId, :proveedorId, :moneda, :condicionPago,
                :almacenId, :fechaEntrega, :notas,
                :subtotal, :impuesto, :descuento, :total,
                :estComercial, :estRecepcion, :estFacturacion, :estPago,
                :creadoPor, :aprobadoPor, :motivoCancelacion
            )'
        );
        $stmt->execute([
            'codigo' => $orden->obtenerCodigo(),
            'solicitudId' => $orden->obtenerSolicitudId(),
            'proveedorId' => $orden->obtenerProveedorId(),
            'moneda' => $orden->obtenerMonedaCodigo(),
            'condicionPago' => $orden->obtenerCondicionPago(),
            'almacenId' => $orden->obtenerAlmacenEntregaId(),
            'fechaEntrega' => $orden->obtenerFechaEntregaEsperada(),
            'notas' => $orden->obtenerNotasComerciales(),
            'subtotal' => $orden->obtenerSubtotal(),
            'impuesto' => $orden->obtenerImpuestoTotal(),
            'descuento' => $orden->obtenerDescuentoTotal(),
            'total' => $orden->obtenerTotal(),
            'estComercial' => $orden->obtenerEstadoComercial(),
            'estRecepcion' => $orden->obtenerEstadoRecepcion(),
            'estFacturacion' => $orden->obtenerEstadoFacturacion(),
            'estPago' => $orden->obtenerEstadoPago(),
            'creadoPor' => $orden->obtenerCreadoPorActorId(),
            'aprobadoPor' => $orden->obtenerAprobadoPorActorId(),
            'motivoCancelacion' => $orden->obtenerMotivoCancelacion(),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function crearOrdenLinea(CompraOrdenLinea $linea): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO compra_orden_lineas (
                orden_compra_id, tipo_linea, articulo_id, descripcion_servicio,
                cantidad_pactada, precio_unitario, subtotal_linea, impuesto_linea,
                total_linea, cantidad_aceptada
            ) VALUES (
                :ordenId, :tipoLinea, :articuloId, :descServicio,
                :cantidad, :precio, :subtotal, :impuesto,
                :total, :aceptada
            )'
        );
        $stmt->execute([
            'ordenId' => $linea->obtenerOrdenCompraId(),
            'tipoLinea' => $linea->obtenerTipoLinea(),
            'articuloId' => $linea->obtenerArticuloId(),
            'descServicio' => $linea->obtenerDescripcionServicio(),
            'cantidad' => $linea->obtenerCantidadPactada(),
            'precio' => $linea->obtenerPrecioUnitario(),
            'subtotal' => $linea->obtenerSubtotalLinea(),
            'impuesto' => $linea->obtenerImpuestoLinea(),
            'total' => $linea->obtenerTotalLinea(),
            'aceptada' => $linea->obtenerCantidadAceptada(),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function buscarOrdenPorId(int $id): ?CompraOrden
    {
        $stmt = $this->pdo->prepare('SELECT * FROM compra_ordenes WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $orden = $this->hidratarOrden($row);

        $stmtLineas = $this->pdo->prepare('SELECT * FROM compra_orden_lineas WHERE orden_compra_id = :id ORDER BY id ASC');
        $stmtLineas->execute(['id' => $id]);
        $lineas = [];
        while ($lRow = $stmtLineas->fetch(PDO::FETCH_ASSOC)) {
            $lineas[] = $this->hidratarOrdenLinea($lRow);
        }
        $orden->asignarLineas($lineas);

        return $orden;
    }

    public function buscarOrdenPorCodigo(string $codigo): ?CompraOrden
    {
        $stmt = $this->pdo->prepare('SELECT id FROM compra_ordenes WHERE codigo = :codigo');
        $stmt->execute(['codigo' => $codigo]);
        $id = $stmt->fetchColumn();

        return $id ? $this->buscarOrdenPorId((int) $id) : null;
    }

    public function listarOrdenes(array $filtros = []): array
    {
        $sql = 'SELECT * FROM compra_ordenes WHERE 1=1';
        $params = [];

        if (!empty($filtros['estado_comercial'])) {
            $sql .= ' AND estado_comercial = :estComercial';
            $params['estComercial'] = $filtros['estado_comercial'];
        }
        if (!empty($filtros['proveedor_id'])) {
            $sql .= ' AND proveedor_id = :provId';
            $params['provId'] = $filtros['proveedor_id'];
        }

        $sql .= ' ORDER BY id DESC LIMIT 100';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $ordenes = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $orden = $this->hidratarOrden($row);
            $ordenes[] = $orden;
        }

        return $ordenes;
    }

    public function actualizarEstadoComercialOrden(int $id, string $nuevoEstado, ?int $aprobadorActorId = null, ?string $motivoCancelacion = null): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE compra_ordenes
             SET estado_comercial = :estado,
                 aprobado_por_actor_id = COALESCE(:aprobador, aprobado_por_actor_id),
                 motivo_cancelacion = COALESCE(:motivo, motivo_cancelacion)
             WHERE id = :id'
        );
        return $stmt->execute([
            'estado' => $nuevoEstado,
            'aprobador' => $aprobadorActorId,
            'motivo' => $motivoCancelacion,
            'id' => $id,
        ]);
    }

    public function actualizarEstadosOrden(int $id, ?string $estRecepcion = null, ?string $estFacturacion = null, ?string $estPago = null): bool
    {
        $campos = [];
        $params = ['id' => $id];

        if ($estRecepcion !== null) {
            $campos[] = 'estado_recepcion = :recepcion';
            $params['recepcion'] = $estRecepcion;
        }
        if ($estFacturacion !== null) {
            $campos[] = 'estado_facturacion = :facturacion';
            $params['facturacion'] = $estFacturacion;
        }
        if ($estPago !== null) {
            $campos[] = 'estado_pago = :pago';
            $params['pago'] = $estPago;
        }

        if (empty($campos)) {
            return false;
        }

        $sql = 'UPDATE compra_ordenes SET ' . implode(', ', $campos) . ' WHERE id = :id';
        return $this->pdo->prepare($sql)->execute($params);
    }

    public function acumularCantidadAceptadaLinea(int $lineaId, string $cantidadAceptadaNueva): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE compra_orden_lineas
             SET cantidad_aceptada = cantidad_aceptada + :nueva
             WHERE id = :id'
        );
        return $stmt->execute([
            'nueva' => $cantidadAceptadaNueva,
            'id' => $lineaId,
        ]);
    }

    // -------------------------------------------------------------------------
    // RECEPCIONES FÍSICAS (BIENES)
    // -------------------------------------------------------------------------

    public function crearRecepcion(CompraRecepcion $recepcion): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO compra_recepciones (
                codigo, orden_compra_id, almacen_id, numero_guia_remision,
                fecha_recepcion, observaciones, recibido_por_actor_id
            ) VALUES (
                :codigo, :ordenId, :almacenId, :guia,
                :fecha, :observaciones, :receptorId
            )'
        );
        $stmt->execute([
            'codigo' => $recepcion->obtenerCodigo(),
            'ordenId' => $recepcion->obtenerOrdenCompraId(),
            'almacenId' => $recepcion->obtenerAlmacenId(),
            'guia' => $recepcion->obtenerNumeroGuiaRemision(),
            'fecha' => $recepcion->obtenerFechaRecepcion()->format('Y-m-d H:i:s'),
            'observaciones' => $recepcion->obtenerObservaciones(),
            'receptorId' => $recepcion->obtenerRecibidoPorActorId(),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function crearRecepcionLinea(CompraRecepcionLinea $linea): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO compra_recepcion_lineas (
                recepcion_id, orden_linea_id, articulo_id, cantidad_recibida,
                cantidad_aceptada, cantidad_rechazada, motivo_rechazo, movimiento_inventario_id
            ) VALUES (
                :recepcionId, :ordenLineaId, :articuloId, :recibida,
                :aceptada, :rechazada, :motivo, :movimientoId
            )'
        );
        $stmt->execute([
            'recepcionId' => $linea->obtenerRecepcionId(),
            'ordenLineaId' => $linea->obtenerOrdenLineaId(),
            'articuloId' => $linea->obtenerArticuloId(),
            'recibida' => $linea->obtenerCantidadRecibida(),
            'aceptada' => $linea->obtenerCantidadAceptada(),
            'rechazada' => $linea->obtenerCantidadRechazada(),
            'motivo' => $linea->obtenerMotivoRechazo(),
            'movimientoId' => $linea->obtenerMovimientoInventarioId(),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function buscarRecepcionPorId(int $id): ?CompraRecepcion
    {
        $stmt = $this->pdo->prepare('SELECT * FROM compra_recepciones WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $recepcion = $this->hidratarRecepcion($row);

        $stmtLineas = $this->pdo->prepare('SELECT * FROM compra_recepcion_lineas WHERE recepcion_id = :id ORDER BY id ASC');
        $stmtLineas->execute(['id' => $id]);
        $lineas = [];
        while ($lRow = $stmtLineas->fetch(PDO::FETCH_ASSOC)) {
            $lineas[] = $this->hidratarRecepcionLinea($lRow);
        }
        $recepcion->asignarLineas($lineas);

        return $recepcion;
    }

    public function listarRecepcionesPorOrden(int $ordenId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM compra_recepciones WHERE orden_compra_id = :ordenId ORDER BY id ASC');
        $stmt->execute(['ordenId' => $ordenId]);

        $recepciones = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $recepciones[] = $this->hidratarRecepcion($row);
        }
        return $recepciones;
    }

    public function sumarCantidadAceptadaPorOrdenLinea(int $ordenLineaId): string
    {
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(cantidad_aceptada), 0)
             FROM compra_recepcion_lineas
             WHERE orden_linea_id = :lineaId'
        );
        $stmt->execute(['lineaId' => $ordenLineaId]);
        return (string) ($stmt->fetchColumn() ?: '0.0000');
    }

    // -------------------------------------------------------------------------
    // CONFORMIDADES DE SERVICIO
    // -------------------------------------------------------------------------

    public function crearConformidad(CompraConformidad $conf): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO compra_conformidades (
                codigo, orden_compra_id, orden_linea_id, fecha_conformidad,
                informe_trabajo_realizado, aprobado_por_actor_id
            ) VALUES (
                :codigo, :ordenId, :lineaId, :fecha,
                :informe, :aprobadorId
            )'
        );
        $stmt->execute([
            'codigo' => $conf->obtenerCodigo(),
            'ordenId' => $conf->obtenerOrdenCompraId(),
            'lineaId' => $conf->obtenerOrdenLineaId(),
            'fecha' => $conf->obtenerFechaConformidad()->format('Y-m-d H:i:s'),
            'informe' => $conf->obtenerInformeTrabajoRealizado(),
            'aprobadorId' => $conf->obtenerAprobadoPorActorId(),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function buscarConformidadPorId(int $id): ?CompraConformidad
    {
        $stmt = $this->pdo->prepare('SELECT * FROM compra_conformidades WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hidratarConformidad($row) : null;
    }

    public function listarConformidadesPorOrden(int $ordenId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM compra_conformidades WHERE orden_compra_id = :ordenId ORDER BY id ASC');
        $stmt->execute(['ordenId' => $ordenId]);

        $conformidades = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $conformidades[] = $this->hidratarConformidad($row);
        }
        return $conformidades;
    }

    // -------------------------------------------------------------------------
    // COMPROBANTES FISCALES Y MATCHING
    // -------------------------------------------------------------------------

    public function crearComprobante(CompraComprobante $comp): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO compra_comprobantes (
                proveedor_id, orden_compra_id, tipo_comprobante, serie, numero,
                fecha_emision, fecha_vencimiento, moneda_codigo,
                subtotal, impuesto, total, estado_matching, observaciones_matching,
                registrado_por_actor_id
            ) VALUES (
                :proveedorId, :ordenId, :tipo, :serie, :numero,
                :emision, :vencimiento, :moneda,
                :subtotal, :impuesto, :total, :matching, :obs,
                :registradorId
            )'
        );
        $stmt->execute([
            'proveedorId' => $comp->obtenerProveedorId(),
            'ordenId' => $comp->obtenerOrdenCompraId(),
            'tipo' => $comp->obtenerTipoComprobante(),
            'serie' => $comp->obtenerSerie(),
            'numero' => $comp->obtenerNumero(),
            'emision' => $comp->obtenerFechaEmision(),
            'vencimiento' => $comp->obtenerFechaVencimiento(),
            'moneda' => $comp->obtenerMonedaCodigo(),
            'subtotal' => $comp->obtenerSubtotal(),
            'impuesto' => $comp->obtenerImpuesto(),
            'total' => $comp->obtenerTotal(),
            'matching' => $comp->obtenerEstadoMatching(),
            'obs' => $comp->obtenerObservacionesMatching(),
            'registradorId' => $comp->obtenerRegistradoPorActorId(),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function crearComprobanteAplicacion(CompraComprobanteAplicacion $app): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO compra_comprobante_aplicaciones (
                comprobante_id, recepcion_linea_id, conformidad_id, monto_aplicado
            ) VALUES (
                :comprobanteId, :recLineaId, :confId, :monto
            )'
        );
        $stmt->execute([
            'comprobanteId' => $app->obtenerComprobanteId(),
            'recLineaId' => $app->obtenerRecepcionLineaId(),
            'confId' => $app->obtenerConformidadId(),
            'monto' => $app->obtenerMontoAplicado(),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function existeComprobanteFiscal(int $proveedorId, string $tipo, string $serie, string $numero, ?int $excluirId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM compra_comprobantes
                WHERE proveedor_id = :provId AND tipo_comprobante = :tipo AND serie = :serie AND numero = :numero';
        $params = [
            'provId' => $proveedorId,
            'tipo' => $tipo,
            'serie' => $serie,
            'numero' => $numero,
        ];
        if ($excluirId !== null) {
            $sql .= ' AND id <> :excluirId';
            $params['excluirId'] = $excluirId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function buscarComprobantePorId(int $id): ?CompraComprobante
    {
        $stmt = $this->pdo->prepare('SELECT * FROM compra_comprobantes WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $comp = $this->hidratarComprobante($row);

        $stmtApps = $this->pdo->prepare('SELECT * FROM compra_comprobante_aplicaciones WHERE comprobante_id = :id ORDER BY id ASC');
        $stmtApps->execute(['id' => $id]);
        $apps = [];
        while ($aRow = $stmtApps->fetch(PDO::FETCH_ASSOC)) {
            $apps[] = $this->hidratarComprobanteAplicacion($aRow);
        }
        $comp->asignarAplicaciones($apps);

        return $comp;
    }

    public function listarComprobantesPorOrden(int $ordenId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM compra_comprobantes WHERE orden_compra_id = :ordenId ORDER BY id ASC');
        $stmt->execute(['ordenId' => $ordenId]);

        $comprobantes = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $comprobantes[] = $this->hidratarComprobante($row);
        }
        return $comprobantes;
    }

    // -------------------------------------------------------------------------
    // CUENTAS POR PAGAR (PASIVOS DEVENGADOS)
    // -------------------------------------------------------------------------

    public function crearCuentaPorPagar(CuentaPorPagar $cxp): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO cuentas_por_pagar (
                codigo, proveedor_id, comprobante_id, orden_compra_id,
                monto_total, monto_amortizado, saldo_pendiente, fecha_vencimiento, estado
            ) VALUES (
                :codigo, :provId, :compId, :ordenId,
                :total, :amortizado, :saldo, :vencimiento, :estado
            )'
        );
        $stmt->execute([
            'codigo' => $cxp->obtenerCodigo(),
            'provId' => $cxp->obtenerProveedorId(),
            'compId' => $cxp->obtenerComprobanteId(),
            'ordenId' => $cxp->obtenerOrdenCompraId(),
            'total' => $cxp->obtenerMontoTotal(),
            'amortizado' => $cxp->obtenerMontoAmortizado(),
            'saldo' => $cxp->obtenerSaldoPendiente(),
            'vencimiento' => $cxp->obtenerFechaVencimiento(),
            'estado' => $cxp->obtenerEstado(),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function buscarCuentaPorPagarPorId(int $id): ?CuentaPorPagar
    {
        $stmt = $this->pdo->prepare('SELECT * FROM cuentas_por_pagar WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $cxp = $this->hidratarCuentaPorPagar($row);

        $stmtPagos = $this->pdo->prepare('SELECT * FROM cxp_pagos WHERE cuenta_pagar_id = :id ORDER BY id ASC');
        $stmtPagos->execute(['id' => $id]);
        $pagos = [];
        while ($pRow = $stmtPagos->fetch(PDO::FETCH_ASSOC)) {
            $pagos[] = $this->hidratarCxpPago($pRow);
        }
        $cxp->asignarPagos($pagos);

        return $cxp;
    }

    public function buscarCuentaPorPagarPorComprobanteId(int $comprobanteId): ?CuentaPorPagar
    {
        $stmt = $this->pdo->prepare('SELECT id FROM cuentas_por_pagar WHERE comprobante_id = :compId');
        $stmt->execute(['compId' => $comprobanteId]);
        $id = $stmt->fetchColumn();

        return $id ? $this->buscarCuentaPorPagarPorId((int) $id) : null;
    }

    public function listarCuentasPorPagar(array $filtros = []): array
    {
        $sql = 'SELECT * FROM cuentas_por_pagar WHERE 1=1';
        $params = [];

        if (!empty($filtros['estado'])) {
            $sql .= ' AND estado = :estado';
            $params['estado'] = $filtros['estado'];
        }
        if (!empty($filtros['proveedor_id'])) {
            $sql .= ' AND proveedor_id = :provId';
            $params['provId'] = $filtros['proveedor_id'];
        }

        $sql .= ' ORDER BY id DESC LIMIT 100';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $lista = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $lista[] = $this->hidratarCuentaPorPagar($row);
        }
        return $lista;
    }

    public function actualizarSaldosCuentaPorPagar(int $id, string $nuevoMontoAmortizado, string $nuevoSaldo, string $nuevoEstado): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE cuentas_por_pagar
             SET monto_amortizado = :amort,
                 saldo_pendiente = :saldo,
                 estado = :estado
             WHERE id = :id'
        );
        return $stmt->execute([
            'amort' => $nuevoMontoAmortizado,
            'saldo' => $nuevoSaldo,
            'estado' => $nuevoEstado,
            'id' => $id,
        ]);
    }

    // -------------------------------------------------------------------------
    // PAGOS Y AMORTIZACIONES
    // -------------------------------------------------------------------------

    public function registrarPago(CxpPago $pago): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO cxp_pagos (
                cuenta_pagar_id, medio_pago, monto, fecha_pago,
                numero_operacion_bancaria, movimiento_caja_id, movimiento_bancario_id,
                notas, registrado_por_actor_id
            ) VALUES (
                :cxpId, :medio, :monto, :fecha,
                :opBancaria, :movCajaId, :movBancoId,
                :notas, :registradorId
            )'
        );
        $stmt->execute([
            'cxpId' => $pago->obtenerCuentaPagarId(),
            'medio' => $pago->obtenerMedioPago(),
            'monto' => $pago->obtenerMonto(),
            'fecha' => $pago->obtenerFechaPago()->format('Y-m-d H:i:s'),
            'opBancaria' => $pago->obtenerNumeroOperacionBancaria(),
            'movCajaId' => $pago->obtenerMovimientoCajaId(),
            'movBancoId' => $pago->obtenerMovimientoBancarioId(),
            'notas' => $pago->obtenerNotas(),
            'registradorId' => $pago->obtenerRegistradoPorActorId(),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function listarPagosPorCuentaPagar(int $cxpId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM cxp_pagos WHERE cuenta_pagar_id = :id ORDER BY id ASC');
        $stmt->execute(['id' => $cxpId]);

        $pagos = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $pagos[] = $this->hidratarCxpPago($row);
        }
        return $pagos;
    }

    // -------------------------------------------------------------------------
    // AUDITORÍA Y HISTORIAL DE ESTADOS (D-061)
    // -------------------------------------------------------------------------

    public function registrarTransicionEstado(
        string $entidadTipo,
        int $entidadId,
        ?string $estadoAnt,
        string $estadoNuevo,
        int $actorId,
        ?string $motivo = null,
        ?string $correlacionId = null
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO compra_historial_estados (
                entidad_tipo, entidad_id, estado_anterior, estado_nuevo,
                actor_id, motivo, correlacion_id
            ) VALUES (
                :tipo, :id, :ant, :nuevo,
                :actor, :motivo, :correlacion
            )'
        );
        $stmt->execute([
            'tipo' => $entidadTipo,
            'id' => $entidadId,
            'ant' => $estadoAnt,
            'nuevo' => $estadoNuevo,
            'actor' => $actorId,
            'motivo' => $motivo,
            'correlacion' => $correlacionId,
        ]);
    }

    // -------------------------------------------------------------------------
    // MÉTODOS PRIVADOS DE HIDRATACIÓN
    // -------------------------------------------------------------------------

    private function hidratarSolicitud(array $r): CompraSolicitud
    {
        return new CompraSolicitud(
            (int) $r['id'],
            $r['codigo'],
            $r['departamento_area'],
            $r['almacen_destino_id'] !== null ? (int) $r['almacen_destino_id'] : null,
            $r['unidad_destino_id'] !== null ? (int) $r['unidad_destino_id'] : null,
            $r['fecha_limite_requerida'],
            $r['justificacion'],
            $r['estado'],
            $r['motivo_rechazo'],
            (int) $r['solicitado_por_actor_id'],
            $r['aprobado_por_actor_id'] !== null ? (int) $r['aprobado_por_actor_id'] : null,
            new DateTimeImmutable($r['creado_en']),
            $r['actualizado_en'] !== null ? new DateTimeImmutable($r['actualizado_en']) : null
        );
    }

    private function hidratarSolicitudLinea(array $r): CompraSolicitudLinea
    {
        return new CompraSolicitudLinea(
            (int) $r['id'],
            (int) $r['solicitud_id'],
            $r['tipo_linea'],
            $r['articulo_id'] !== null ? (int) $r['articulo_id'] : null,
            $r['descripcion_servicio'],
            $r['cantidad_solicitada'],
            $r['especificaciones_tecnicas'],
            new DateTimeImmutable($r['creado_en'])
        );
    }

    private function hidratarOrden(array $r): CompraOrden
    {
        return new CompraOrden(
            (int) $r['id'],
            $r['codigo'],
            $r['solicitud_id'] !== null ? (int) $r['solicitud_id'] : null,
            (int) $r['proveedor_id'],
            $r['moneda_codigo'],
            $r['condicion_pago'],
            $r['almacen_entrega_id'] !== null ? (int) $r['almacen_entrega_id'] : null,
            $r['fecha_entrega_esperada'],
            $r['notas_comerciales'],
            $r['subtotal'],
            $r['impuesto_total'],
            $r['descuento_total'],
            $r['total'],
            $r['estado_comercial'],
            $r['estado_recepcion'],
            $r['estado_facturacion'],
            $r['estado_pago'],
            (int) $r['creado_por_actor_id'],
            $r['aprobado_por_actor_id'] !== null ? (int) $r['aprobado_por_actor_id'] : null,
            $r['motivo_cancelacion'],
            new DateTimeImmutable($r['creado_en']),
            $r['actualizado_en'] !== null ? new DateTimeImmutable($r['actualizado_en']) : null
        );
    }

    private function hidratarOrdenLinea(array $r): CompraOrdenLinea
    {
        return new CompraOrdenLinea(
            (int) $r['id'],
            (int) $r['orden_compra_id'],
            $r['tipo_linea'],
            $r['articulo_id'] !== null ? (int) $r['articulo_id'] : null,
            $r['descripcion_servicio'],
            $r['cantidad_pactada'],
            $r['precio_unitario'],
            $r['subtotal_linea'],
            $r['impuesto_linea'],
            $r['total_linea'],
            $r['cantidad_aceptada'],
            new DateTimeImmutable($r['creado_en'])
        );
    }

    private function hidratarRecepcion(array $r): CompraRecepcion
    {
        return new CompraRecepcion(
            (int) $r['id'],
            $r['codigo'],
            (int) $r['orden_compra_id'],
            (int) $r['almacen_id'],
            $r['numero_guia_remision'],
            new DateTimeImmutable($r['fecha_recepcion']),
            $r['observaciones'],
            (int) $r['recibido_por_actor_id'],
            new DateTimeImmutable($r['creado_en'])
        );
    }

    private function hidratarRecepcionLinea(array $r): CompraRecepcionLinea
    {
        return new CompraRecepcionLinea(
            (int) $r['id'],
            (int) $r['recepcion_id'],
            (int) $r['orden_linea_id'],
            (int) $r['articulo_id'],
            $r['cantidad_recibida'],
            $r['cantidad_aceptada'],
            $r['cantidad_rechazada'],
            $r['motivo_rechazo'],
            $r['movimiento_inventario_id'] !== null ? (int) $r['movimiento_inventario_id'] : null,
            new DateTimeImmutable($r['creado_en'])
        );
    }

    private function hidratarConformidad(array $r): CompraConformidad
    {
        return new CompraConformidad(
            (int) $r['id'],
            $r['codigo'],
            (int) $r['orden_compra_id'],
            (int) $r['orden_linea_id'],
            new DateTimeImmutable($r['fecha_conformidad']),
            $r['informe_trabajo_realizado'],
            (int) $r['aprobado_por_actor_id'],
            new DateTimeImmutable($r['creado_en'])
        );
    }

    private function hidratarComprobante(array $r): CompraComprobante
    {
        return new CompraComprobante(
            (int) $r['id'],
            (int) $r['proveedor_id'],
            (int) $r['orden_compra_id'],
            $r['tipo_comprobante'],
            $r['serie'],
            $r['numero'],
            $r['fecha_emision'],
            $r['fecha_vencimiento'],
            $r['moneda_codigo'],
            $r['subtotal'],
            $r['impuesto'],
            $r['total'],
            $r['estado_matching'],
            $r['observaciones_matching'],
            (int) $r['registrado_por_actor_id'],
            new DateTimeImmutable($r['creado_en']),
            $r['actualizado_en'] !== null ? new DateTimeImmutable($r['actualizado_en']) : null
        );
    }

    private function hidratarComprobanteAplicacion(array $r): CompraComprobanteAplicacion
    {
        return new CompraComprobanteAplicacion(
            (int) $r['id'],
            (int) $r['comprobante_id'],
            $r['recepcion_linea_id'] !== null ? (int) $r['recepcion_linea_id'] : null,
            $r['conformidad_id'] !== null ? (int) $r['conformidad_id'] : null,
            $r['monto_aplicado'],
            new DateTimeImmutable($r['creado_en'])
        );
    }

    private function hidratarCuentaPorPagar(array $r): CuentaPorPagar
    {
        return new CuentaPorPagar(
            (int) $r['id'],
            $r['codigo'],
            (int) $r['proveedor_id'],
            (int) $r['comprobante_id'],
            (int) $r['orden_compra_id'],
            $r['monto_total'],
            $r['monto_amortizado'],
            $r['saldo_pendiente'],
            $r['fecha_vencimiento'],
            $r['estado'],
            new DateTimeImmutable($r['creado_en']),
            $r['actualizado_en'] !== null ? new DateTimeImmutable($r['actualizado_en']) : null
        );
    }

    private function hidratarCxpPago(array $r): CxpPago
    {
        return new CxpPago(
            (int) $r['id'],
            (int) $r['cuenta_pagar_id'],
            $r['medio_pago'],
            $r['monto'],
            new DateTimeImmutable($r['fecha_pago']),
            $r['numero_operacion_bancaria'],
            $r['movimiento_caja_id'] !== null ? (int) $r['movimiento_caja_id'] : null,
            $r['movimiento_bancario_id'] !== null ? (int) $r['movimiento_bancario_id'] : null,
            $r['notas'],
            (int) $r['registrado_por_actor_id'],
            new DateTimeImmutable($r['creado_en'])
        );
    }
}
