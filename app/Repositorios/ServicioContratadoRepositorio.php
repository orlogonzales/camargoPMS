<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\ServicioContratado;
use CamargoPMS\Modelos\ServicioTraslado;
use CamargoPMS\Nucleo\BaseDatos;
use PDO;

/**
 * Repositorio de persistencia para servicios contratados, consumos imputados y extensión 1:1 de traslados.
 *
 * Principios vinculantes:
 * - Reserva obligatoria (reserva_id NOT NULL).
 * - Cero DELETE sobre servicios contratados ni traslados.
 * - Snapshots inmutables de catálogo.
 * - Bloqueo pesimista FOR UPDATE en transiciones operativas.
 */
class ServicioContratadoRepositorio
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? BaseDatos::conexion();
    }

    public function obtenerPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Busca un servicio contratado por su ID primario.
     */
    public function buscarPorId(int $id, bool $cargarTraslado = true): ?ServicioContratado
    {
        $sql = 'SELECT sc.*,
                       r.codigo AS reserva_codigo,
                       TRIM(CONCAT(COALESCE(p.nombres, ""), " ", COALESCE(p.apellido_paterno, ""), " ", COALESCE(p.apellido_materno, ""))) AS titular_nombre_completo,
                       e.codigo AS estadia_codigo,
                       u.nombre AS unidad_nombre,
                       s.nombre AS servicio_nombre,
                       prov.razon_social AS proveedor_razon_social,
                       prov.codigo AS proveedor_codigo
                FROM servicios_contratados sc
                INNER JOIN reservas r ON sc.reserva_id = r.id
                INNER JOIN personas p ON r.persona_titular_id = p.id
                INNER JOIN servicios s ON sc.servicio_id = s.id
                LEFT JOIN estadias e ON sc.estadia_id = e.id
                LEFT JOIN unidades u ON e.unidad_id = u.id
                LEFT JOIN proveedores prov ON sc.proveedor_id = prov.id
                WHERE sc.id = :id
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fila) {
            return null;
        }

        $contratado = ServicioContratado::desdeArreglo($fila);

        if ($cargarTraslado) {
            $contratado->asignarTraslado($this->buscarTrasladoPorContratadoId($id));
        }

        return $contratado;
    }

    /**
     * Busca un servicio contratado por ID con bloqueo pesimista FOR UPDATE.
     */
    public function buscarPorIdParaActualizar(int $id): ?ServicioContratado
    {
        $sql = 'SELECT sc.*,
                       r.codigo AS reserva_codigo,
                       TRIM(CONCAT(COALESCE(p.nombres, ""), " ", COALESCE(p.apellido_paterno, ""), " ", COALESCE(p.apellido_materno, ""))) AS titular_nombre_completo,
                       e.codigo AS estadia_codigo,
                       u.nombre AS unidad_nombre,
                       s.nombre AS servicio_nombre,
                       prov.razon_social AS proveedor_razon_social,
                       prov.codigo AS proveedor_codigo
                FROM servicios_contratados sc
                INNER JOIN reservas r ON sc.reserva_id = r.id
                INNER JOIN personas p ON r.persona_titular_id = p.id
                INNER JOIN servicios s ON sc.servicio_id = s.id
                LEFT JOIN estadias e ON sc.estadia_id = e.id
                LEFT JOIN unidades u ON e.unidad_id = u.id
                LEFT JOIN proveedores prov ON sc.proveedor_id = prov.id
                WHERE sc.id = :id
                LIMIT 1
                FOR UPDATE';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fila) {
            return null;
        }

        $contratado = ServicioContratado::desdeArreglo($fila);
        $contratado->asignarTraslado($this->buscarTrasladoPorContratadoId($id));

        return $contratado;
    }

    /**
     * Busca por código único de negocio.
     */
    public function buscarPorCodigo(string $codigo, bool $cargarTraslado = true): ?ServicioContratado
    {
        $sql = 'SELECT sc.*,
                       r.codigo AS reserva_codigo,
                       TRIM(CONCAT(COALESCE(p.nombres, ""), " ", COALESCE(p.apellido_paterno, ""), " ", COALESCE(p.apellido_materno, ""))) AS titular_nombre_completo,
                       e.codigo AS estadia_codigo,
                       u.nombre AS unidad_nombre,
                       s.nombre AS servicio_nombre,
                       prov.razon_social AS proveedor_razon_social,
                       prov.codigo AS proveedor_codigo
                FROM servicios_contratados sc
                INNER JOIN reservas r ON sc.reserva_id = r.id
                INNER JOIN personas p ON r.persona_titular_id = p.id
                INNER JOIN servicios s ON sc.servicio_id = s.id
                LEFT JOIN estadias e ON sc.estadia_id = e.id
                LEFT JOIN unidades u ON e.unidad_id = u.id
                LEFT JOIN proveedores prov ON sc.proveedor_id = prov.id
                WHERE sc.codigo = :codigo
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':codigo', trim(strtoupper($codigo)), PDO::PARAM_STR);
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fila) {
            return null;
        }

        $contratado = ServicioContratado::desdeArreglo($fila);

        if ($cargarTraslado) {
            $contratado->asignarTraslado($this->buscarTrasladoPorContratadoId((int) $contratado->obtenerId()));
        }

        return $contratado;
    }

    /**
     * Lista servicios contratados para una reserva comercial.
     *
     * @return array<int, ServicioContratado>
     */
    public function listarPorReserva(int $reservaId): array
    {
        return $this->listar(['reserva_id' => $reservaId]);
    }

    /**
     * Lista servicios contratados imputados directamente a una estadía física.
     *
     * @return array<int, ServicioContratado>
     */
    public function listarPorEstadia(int $estadiaId): array
    {
        return $this->listar(['estadia_id' => $estadiaId]);
    }

    /**
     * Lista servicios contratados con filtros múltiples.
     *
     * @param array<string, mixed> $filtros
     * @return array<int, ServicioContratado>
     */
    public function listar(array $filtros = []): array
    {
        $sql = 'SELECT sc.*,
                       r.codigo AS reserva_codigo,
                       TRIM(CONCAT(COALESCE(p.nombres, ""), " ", COALESCE(p.apellido_paterno, ""), " ", COALESCE(p.apellido_materno, ""))) AS titular_nombre_completo,
                       e.codigo AS estadia_codigo,
                       u.nombre AS unidad_nombre,
                       s.nombre AS servicio_nombre,
                       prov.razon_social AS proveedor_razon_social,
                       prov.codigo AS proveedor_codigo
                FROM servicios_contratados sc
                INNER JOIN reservas r ON sc.reserva_id = r.id
                INNER JOIN personas p ON r.persona_titular_id = p.id
                INNER JOIN servicios s ON sc.servicio_id = s.id
                LEFT JOIN estadias e ON sc.estadia_id = e.id
                LEFT JOIN unidades u ON e.unidad_id = u.id
                LEFT JOIN proveedores prov ON sc.proveedor_id = prov.id
                WHERE 1 = 1';

        $params = [];

        if (!empty($filtros['reserva_id'])) {
            $sql .= ' AND sc.reserva_id = :reserva_id';
            $params[':reserva_id'] = (int) $filtros['reserva_id'];
        }

        if (!empty($filtros['estadia_id'])) {
            $sql .= ' AND sc.estadia_id = :estadia_id';
            $params[':estadia_id'] = (int) $filtros['estadia_id'];
        }

        if (!empty($filtros['servicio_id'])) {
            $sql .= ' AND sc.servicio_id = :servicio_id';
            $params[':servicio_id'] = (int) $filtros['servicio_id'];
        }

        if (isset($filtros['proveedor_id']) && $filtros['proveedor_id'] !== '') {
            if ($filtros['proveedor_id'] === 'interno') {
                $sql .= ' AND sc.es_operacion_interna = 1';
            } else {
                $sql .= ' AND sc.proveedor_id = :proveedor_id';
                $params[':proveedor_id'] = (int) $filtros['proveedor_id'];
            }
        }

        if (!empty($filtros['estado'])) {
            $sql .= ' AND sc.estado = :estado';
            $params[':estado'] = strtoupper(trim((string) $filtros['estado']));
        }

        if (!empty($filtros['fecha_desde'])) {
            $sql .= ' AND sc.fecha_servicio >= :fecha_desde';
            $params[':fecha_desde'] = trim((string) $filtros['fecha_desde']);
        }

        if (!empty($filtros['fecha_hasta'])) {
            $sql .= ' AND sc.fecha_servicio <= :fecha_hasta';
            $params[':fecha_hasta'] = trim((string) $filtros['fecha_hasta']);
        }

        if (!empty($filtros['q'])) {
            $sql .= ' AND (sc.codigo LIKE :q OR sc.descripcion_servicio_snapshot LIKE :q OR r.codigo LIKE :q OR p.nombres LIKE :q OR p.apellido_paterno LIKE :q)';
            $params[':q'] = '%' . trim((string) $filtros['q']) . '%';
        }

        $sql .= ' ORDER BY sc.fecha_servicio DESC, sc.id DESC';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $clave => $valor) {
            $stmt->bindValue($clave, $valor);
        }
        $stmt->execute();

        $contratados = [];
        $ids = [];
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $item = ServicioContratado::desdeArreglo($fila);
            $contratados[(int) $fila['id']] = $item;
            $ids[] = (int) $fila['id'];
        }

        // Cargar traslados en batch si hay registros
        if (!empty($ids)) {
            $inClause = implode(',', array_fill(0, count($ids), '?'));
            $sqlTraslados = "SELECT * FROM servicio_traslados WHERE servicio_contratado_id IN ({$inClause})";
            $stmtT = $this->pdo->prepare($sqlTraslados);
            foreach ($ids as $idx => $idVal) {
                $stmtT->bindValue($idx + 1, $idVal, PDO::PARAM_INT);
            }
            $stmtT->execute();
            while ($filaT = $stmtT->fetch(PDO::FETCH_ASSOC)) {
                $scId = (int) $filaT['servicio_contratado_id'];
                if (isset($contratados[$scId])) {
                    $contratados[$scId]->asignarTraslado(ServicioTraslado::desdeArreglo($filaT));
                }
            }
        }

        return array_values($contratados);
    }

    /**
     * Inserta un nuevo servicio contratado.
     */
    public function crear(ServicioContratado $contratado): int
    {
        $sql = 'INSERT INTO servicios_contratados (
                    codigo, reserva_id, estadia_id, servicio_id, proveedor_id,
                    descripcion_servicio_snapshot, categoria_codigo_snapshot, modalidad_cobro_codigo_snapshot,
                    es_operacion_interna, cantidad, precio_unitario, costo_unitario,
                    subtotal, tasa_impuesto, impuesto_total, total, costo_total, moneda_codigo,
                    estado, fecha_servicio, hora_servicio, observaciones,
                    solicitado_por_actor_id
                ) VALUES (
                    :codigo, :reserva_id, :estadia_id, :servicio_id, :proveedor_id,
                    :desc_snapshot, :cat_snapshot, :mod_snapshot,
                    :es_interna, :cantidad, :precio_unitario, :costo_unitario,
                    :subtotal, :tasa_impuesto, :impuesto_total, :total, :costo_total, :moneda_codigo,
                    :estado, :fecha_servicio, :hora_servicio, :observaciones,
                    :solicitado_por
                )';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':codigo', $contratado->obtenerCodigo(), PDO::PARAM_STR);
        $stmt->bindValue(':reserva_id', $contratado->obtenerReservaId(), PDO::PARAM_INT);
        $stmt->bindValue(':estadia_id', $contratado->obtenerEstadiaId(), $contratado->obtenerEstadiaId() !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':servicio_id', $contratado->obtenerServicioId(), PDO::PARAM_INT);
        $stmt->bindValue(':proveedor_id', $contratado->obtenerProveedorId(), $contratado->obtenerProveedorId() !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':desc_snapshot', $contratado->obtenerDescripcionServicioSnapshot(), PDO::PARAM_STR);
        $stmt->bindValue(':cat_snapshot', $contratado->obtenerCategoriaCodigoSnapshot(), PDO::PARAM_STR);
        $stmt->bindValue(':mod_snapshot', $contratado->obtenerModalidadCobroCodigoSnapshot(), PDO::PARAM_STR);
        $stmt->bindValue(':es_interna', $contratado->esOperacionInterna() ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':cantidad', $contratado->obtenerCantidad(), PDO::PARAM_STR);
        $stmt->bindValue(':precio_unitario', $contratado->obtenerPrecioUnitario(), PDO::PARAM_STR);
        $stmt->bindValue(':costo_unitario', $contratado->obtenerCostoUnitario(), PDO::PARAM_STR);
        $stmt->bindValue(':subtotal', $contratado->obtenerSubtotal(), PDO::PARAM_STR);
        $stmt->bindValue(':tasa_impuesto', $contratado->obtenerTasaImpuesto(), PDO::PARAM_STR);
        $stmt->bindValue(':impuesto_total', $contratado->obtenerImpuestoTotal(), PDO::PARAM_STR);
        $stmt->bindValue(':total', $contratado->obtenerTotal(), PDO::PARAM_STR);
        $stmt->bindValue(':costo_total', $contratado->obtenerCostoTotal(), PDO::PARAM_STR);
        $stmt->bindValue(':moneda_codigo', $contratado->obtenerMonedaCodigo(), PDO::PARAM_STR);
        $stmt->bindValue(':estado', $contratado->obtenerEstado(), PDO::PARAM_STR);
        $stmt->bindValue(':fecha_servicio', $contratado->obtenerFechaServicio(), PDO::PARAM_STR);
        $stmt->bindValue(':hora_servicio', $contratado->obtenerHoraServicio(), $contratado->obtenerHoraServicio() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':observaciones', $contratado->obtenerObservaciones(), $contratado->obtenerObservaciones() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':solicitado_por', $contratado->obtenerSolicitadoPorActorId(), PDO::PARAM_INT);

        $stmt->execute();
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Actualiza el estado operativo de un servicio contratado.
     */
    public function actualizarEstado(
        int $id,
        string $nuevoEstado,
        ?int $actorId = null,
        ?string $motivo = null
    ): bool {
        $nuevoEstado = strtoupper(trim($nuevoEstado));

        if ($nuevoEstado === 'EJECUTADO') {
            $sql = 'UPDATE servicios_contratados SET
                        estado = :estado,
                        ejecutado_en = CURRENT_TIMESTAMP,
                        ejecutado_por_actor_id = :actor_id
                    WHERE id = :id';
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->bindValue(':estado', $nuevoEstado, PDO::PARAM_STR);
            $stmt->bindValue(':actor_id', $actorId, $actorId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
            return $stmt->execute();
        }

        if ($nuevoEstado === 'CANCELADO') {
            $sql = 'UPDATE servicios_contratados SET
                        estado = :estado,
                        cancelada_en = CURRENT_TIMESTAMP,
                        cancelada_por_actor_id = :actor_id,
                        motivo_cancelacion = :motivo
                    WHERE id = :id';
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->bindValue(':estado', $nuevoEstado, PDO::PARAM_STR);
            $stmt->bindValue(':actor_id', $actorId, PDO::PARAM_INT);
            $stmt->bindValue(':motivo', trim((string) $motivo), PDO::PARAM_STR);
            return $stmt->execute();
        }

        $sql = 'UPDATE servicios_contratados SET estado = :estado WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':estado', $nuevoEstado, PDO::PARAM_STR);
        return $stmt->execute();
    }

    // =========================================================================
    // Extensión 1:1 de Traslados
    // =========================================================================

    /**
     * Inserta la extensión de traslado.
     */
    public function crearTraslado(ServicioTraslado $traslado): int
    {
        $sql = 'INSERT INTO servicio_traslados (
                    servicio_contratado_id, tipo_traslado, origen, destino,
                    fecha_hora_recogida, aerolinea_empresa, numero_vuelo_viaje,
                    cantidad_pasajeros, cantidad_maletas, datos_conductor_vehiculo,
                    instrucciones_recogida
                ) VALUES (
                    :sc_id, :tipo_traslado, :origen, :destino,
                    :fecha_hora, :aerolinea, :numero_vuelo,
                    :pasajeros, :maletas, :conductor,
                    :instrucciones
                )';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':sc_id', $traslado->obtenerServicioContratadoId(), PDO::PARAM_INT);
        $stmt->bindValue(':tipo_traslado', $traslado->obtenerTipoTraslado(), PDO::PARAM_STR);
        $stmt->bindValue(':origen', $traslado->obtenerOrigen(), PDO::PARAM_STR);
        $stmt->bindValue(':destino', $traslado->obtenerDestino(), PDO::PARAM_STR);
        $stmt->bindValue(':fecha_hora', $traslado->obtenerFechaHoraRecogida(), PDO::PARAM_STR);
        $stmt->bindValue(':aerolinea', $traslado->obtenerAerolineaEmpresa(), $traslado->obtenerAerolineaEmpresa() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':numero_vuelo', $traslado->obtenerNumeroVueloViaje(), $traslado->obtenerNumeroVueloViaje() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':pasajeros', $traslado->obtenerCantidadPasajeros(), PDO::PARAM_INT);
        $stmt->bindValue(':maletas', $traslado->obtenerCantidadMaletas(), PDO::PARAM_INT);
        $stmt->bindValue(':conductor', $traslado->obtenerDatosConductorVehiculo(), $traslado->obtenerDatosConductorVehiculo() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':instrucciones', $traslado->obtenerInstruccionesRecogida(), $traslado->obtenerInstruccionesRecogida() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);

        $stmt->execute();
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Busca la extensión de traslado por ID de servicio contratado.
     */
    public function buscarTrasladoPorContratadoId(int $servicioContratadoId): ?ServicioTraslado
    {
        $sql = 'SELECT * FROM servicio_traslados WHERE servicio_contratado_id = :sc_id LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':sc_id', $servicioContratadoId, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? ServicioTraslado::desdeArreglo($fila) : null;
    }

    /**
     * Genera el siguiente código secuencial único para un servicio contratado (SC-YYYYMMDD-XXXX).
     */
    public function generarSiguienteCodigo(): string
    {
        $fecha = date('Ymd');
        $prefijo = 'SC-' . $fecha . '-';

        $sql = 'SELECT codigo FROM servicios_contratados WHERE codigo LIKE :prefijo ORDER BY id DESC LIMIT 1 FOR UPDATE';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':prefijo', $prefijo . '%', PDO::PARAM_STR);
        $stmt->execute();

        $ultimoCodigo = $stmt->fetchColumn();
        if ($ultimoCodigo && preg_match('/(\d{4})$/', (string) $ultimoCodigo, $coincidencias)) {
            $correlativo = (int) $coincidencias[1] + 1;
        } else {
            $correlativo = 1;
        }

        return sprintf('%s%04d', $prefijo, $correlativo);
    }
}
