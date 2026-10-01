<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use PDO;

/**
 * Repositorio analítico de solo lectura para el Módulo de Reportes.
 * Gobernanza: D-087 / REPORTES-1.
 * Contrato O(1): Consultas acotadas por lotes (Batch Queries), sin loops N+1.
 * Cero mutaciones, cero DDL, cero modificación de estado.
 */
class ReporteRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    // =========================================================================
    // 1. MANAGER'S DAILY REPORT (MDR)
    // =========================================================================

    /**
     * @return array<int, array<string, mixed>>
     */
    public function obtenerUnidadesInventario(?int $propiedadId = null): array
    {
        $sql = 'SELECT u.id, u.codigo, u.nombre, u.piso_nivel, u.capacidad_personas,
                       u.propiedad_id, p.nombre AS propiedad_nombre,
                       tu.id AS tipo_unidad_id, tu.nombre AS tipo_unidad_nombre
                FROM unidades u
                JOIN propiedades p ON p.id = u.propiedad_id
                JOIN tipos_unidad tu ON tu.id = u.tipo_unidad_id
                WHERE u.estado = "ACTIVO"';
        $params = [];
        if ($propiedadId !== null && $propiedadId > 0) {
            $sql .= ' AND u.propiedad_id = :propiedad_id';
            $params['propiedad_id'] = $propiedadId;
        }
        $sql .= ' ORDER BY u.piso_nivel ASC, u.codigo ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene órdenes de mantenimiento con bloqueo activo para la fecha.
     * Semántica real de D-084 / D-087: requiere_bloqueo = 1 y fecha dentro del intervalo.
     *
     * @return array<int, array<string, mixed>>
     */
    public function obtenerOrdenesBloqueantesFecha(string $fecha, ?int $propiedadId = null): array
    {
        $sql = 'SELECT mo.id, mo.codigo, mo.unidad_id, mo.propiedad_id, mo.titulo,
                       mo.fecha_bloqueo_inicio, mo.fecha_bloqueo_fin, mo.estado
                FROM mantenimiento_ordenes mo
                WHERE mo.requiere_bloqueo = 1
                  AND mo.estado IN ("PROGRAMADA", "EN_PROCESO")
                  AND :fecha_b1 >= mo.fecha_bloqueo_inicio
                  AND :fecha_b2 < mo.fecha_bloqueo_fin';
        $params = ['fecha_b1' => $fecha, 'fecha_b2' => $fecha];
        if ($propiedadId !== null && $propiedadId > 0) {
            $sql .= ' AND mo.propiedad_id = :propiedad_id';
            $params['propiedad_id'] = $propiedadId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function obtenerEstadiasActivasFecha(string $fecha, ?int $propiedadId = null): array
    {
        $sql = 'SELECT e.id, e.codigo, e.unidad_id, e.reserva_id, e.fecha_entrada,
                       e.fecha_salida_prevista, e.checkin_en, e.estado,
                       u.propiedad_id, r.persona_titular_id,
                       TRIM(CONCAT(p.nombres, " ", p.apellido_paterno)) AS titular_nombre
                FROM estadias e
                JOIN unidades u ON u.id = e.unidad_id
                LEFT JOIN reservas r ON r.id = e.reserva_id
                LEFT JOIN personas p ON p.id = r.persona_titular_id
                WHERE e.estado = "EN_CURSO"
                  AND e.fecha_entrada <= :fecha_e1
                  AND e.fecha_salida_prevista > :fecha_e2';
        $params = ['fecha_e1' => $fecha, 'fecha_e2' => $fecha];
        if ($propiedadId !== null && $propiedadId > 0) {
            $sql .= ' AND u.propiedad_id = :propiedad_id';
            $params['propiedad_id'] = $propiedadId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function obtenerArrendamientosActivosFecha(string $fecha, ?int $propiedadId = null): array
    {
        $sql = 'SELECT a.id, a.codigo, a.unidad_id, a.fecha_inicio, a.fecha_fin, a.estado,
                       u.propiedad_id, p.id AS titular_id,
                       TRIM(CONCAT(p.nombres, " ", p.apellido_paterno)) AS titular_nombre
                FROM arrendamientos a
                JOIN unidades u ON u.id = a.unidad_id
                LEFT JOIN arrendamiento_personas ap ON ap.arrendamiento_id = a.id AND ap.tipo_relacion = "TITULAR"
                LEFT JOIN personas p ON p.id = ap.persona_id
                WHERE a.estado = "VIGENTE"
                  AND a.fecha_inicio <= :fecha_a1
                  AND a.fecha_fin >= :fecha_a2';
        $params = ['fecha_a1' => $fecha, 'fecha_a2' => $fecha];
        if ($propiedadId !== null && $propiedadId > 0) {
            $sql .= ' AND u.propiedad_id = :propiedad_id';
            $params['propiedad_id'] = $propiedadId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function obtenerLlegadasPrevistasFecha(string $fecha, ?int $propiedadId = null): array
    {
        $sql = 'SELECT r.id, r.codigo, r.fecha_entrada, r.fecha_salida, r.noches, r.estado,
                       r.total, r.canal, ru.unidad_id,
                       TRIM(CONCAT(p.nombres, " ", p.apellido_paterno)) AS titular_nombre
                FROM reservas r
                JOIN reserva_unidades ru ON ru.reserva_id = r.id
                JOIN unidades u ON u.id = ru.unidad_id
                LEFT JOIN personas p ON p.id = r.persona_titular_id
                WHERE r.fecha_entrada = :fecha
                  AND r.estado IN ("CONFIRMADA", "CHECKIN")';
        $params = ['fecha' => $fecha];
        if ($propiedadId !== null && $propiedadId > 0) {
            $sql .= ' AND u.propiedad_id = :propiedad_id';
            $params['propiedad_id'] = $propiedadId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function obtenerLlegadasRealizadasFecha(string $fecha, ?int $propiedadId = null): array
    {
        $sql = 'SELECT e.id, e.codigo, e.unidad_id, e.checkin_en, e.estado,
                       TRIM(CONCAT(p.nombres, " ", p.apellido_paterno)) AS titular_nombre
                FROM estadias e
                JOIN unidades u ON u.id = e.unidad_id
                LEFT JOIN reservas r ON r.id = e.reserva_id
                LEFT JOIN personas p ON p.id = r.persona_titular_id
                WHERE DATE(e.checkin_en) = :fecha';
        $params = ['fecha' => $fecha];
        if ($propiedadId !== null && $propiedadId > 0) {
            $sql .= ' AND u.propiedad_id = :propiedad_id';
            $params['propiedad_id'] = $propiedadId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function obtenerSalidasPrevistasFecha(string $fecha, ?int $propiedadId = null): array
    {
        $sql = 'SELECT e.id, e.codigo, e.unidad_id, e.fecha_salida_prevista, e.estado,
                       TRIM(CONCAT(p.nombres, " ", p.apellido_paterno)) AS titular_nombre
                FROM estadias e
                JOIN unidades u ON u.id = e.unidad_id
                LEFT JOIN reservas r ON r.id = e.reserva_id
                LEFT JOIN personas p ON p.id = r.persona_titular_id
                WHERE e.fecha_salida_prevista = :fecha
                  AND e.estado = "EN_CURSO"';
        $params = ['fecha' => $fecha];
        if ($propiedadId !== null && $propiedadId > 0) {
            $sql .= ' AND u.propiedad_id = :propiedad_id';
            $params['propiedad_id'] = $propiedadId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function obtenerSalidasRealizadasFecha(string $fecha, ?int $propiedadId = null): array
    {
        $sql = 'SELECT e.id, e.codigo, e.unidad_id, e.checkout_en, e.estado,
                       TRIM(CONCAT(p.nombres, " ", p.apellido_paterno)) AS titular_nombre
                FROM estadias e
                JOIN unidades u ON u.id = e.unidad_id
                LEFT JOIN reservas r ON r.id = e.reserva_id
                LEFT JOIN personas p ON p.id = r.persona_titular_id
                WHERE DATE(e.checkout_en) = :fecha
                  AND e.estado = "FINALIZADA"';
        $params = ['fecha' => $fecha];
        if ($propiedadId !== null && $propiedadId > 0) {
            $sql .= ' AND u.propiedad_id = :propiedad_id';
            $params['propiedad_id'] = $propiedadId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function obtenerHigienePisos(?int $propiedadId = null): array
    {
        $sql = 'SELECT h.unidad_id, h.estado_limpieza, h.ultima_inspeccion_en
                FROM housekeeping_unidades_limpieza h
                JOIN unidades u ON u.id = h.unidad_id
                WHERE u.estado = "ACTIVO"';
        $params = [];
        if ($propiedadId !== null && $propiedadId > 0) {
            $sql .= ' AND u.propiedad_id = :propiedad_id';
            $params['propiedad_id'] = $propiedadId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function obtenerIncidenciasAbiertas(?int $propiedadId = null): array
    {
        $sql = 'SELECT id, codigo, titulo, severidad, estado, creado_en
                FROM mantenimiento_incidencias
                WHERE estado IN ("REPORTADA", "EN_EVALUACION")';
        $params = [];
        if ($propiedadId !== null && $propiedadId > 0) {
            $sql .= ' AND propiedad_id = :propiedad_id';
            $params['propiedad_id'] = $propiedadId;
        }
        $sql .= ' ORDER BY id DESC LIMIT 20';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================================================================
    // 2. FLUJO DE CAJA (CASH FLOW)
    // =========================================================================

    /**
     * Calcula el saldo de efectivo acumulado previo a una fecha de inicio.
     */
    public function obtenerSaldoInicialCaja(string $fechaDesde, ?int $propiedadId = null): string
    {
        $sql = 'SELECT COALESCE(SUM(CASE
                    WHEN tipo_movimiento IN ("INGRESO_COBRO", "INGRESO_AJUSTE", "APERTURA") THEN monto
                    WHEN tipo_movimiento IN ("EGRESO_GASTO_MENOR", "EGRESO_AJUSTE") THEN -monto
                    ELSE 0.00 END), 0.00) AS saldo_inicial
                FROM movimientos_caja mc
                JOIN sesiones_caja sc ON sc.id = mc.sesion_caja_id
                JOIN cajas_fisicas cf ON cf.id = sc.caja_fisica_id
                WHERE mc.creado_en < :fecha_desde';
        $params = ['fecha_desde' => "{$fechaDesde} 00:00:00"];
        if ($propiedadId !== null && $propiedadId > 0) {
            $sql .= ' AND cf.propiedad_id = :propiedad_id';
            $params['propiedad_id'] = $propiedadId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (string) $stmt->fetchColumn();
    }

    /**
     * Calcula el saldo bancario acumulado previo a una fecha de inicio.
     */
    public function obtenerSaldoInicialBanco(string $fechaDesde, ?int $propiedadId = null): string
    {
        $sql = 'SELECT COALESCE(SUM(CASE
                    WHEN tipo_movimiento IN ("INGRESO_COBRO", "INGRESO_TRANSFERENCIA", "INGRESO_AJUSTE") THEN monto
                    WHEN tipo_movimiento IN ("EGRESO_TRANSFERENCIA", "EGRESO_COMISION", "EGRESO_AJUSTE") THEN -monto
                    ELSE 0.00 END), 0.00) AS saldo_inicial
                FROM movimientos_bancarios mb
                JOIN cuentas_bancarias cb ON cb.id = mb.cuenta_bancaria_id
                WHERE mb.fecha_operacion < :fecha_desde';
        $params = ['fecha_desde' => $fechaDesde];

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (string) $stmt->fetchColumn();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function obtenerMovimientosCajaRango(string $fechaDesde, string $fechaHasta, ?int $propiedadId = null): array
    {
        $sql = 'SELECT mc.id, mc.sesion_caja_id, mc.tipo_movimiento, mc.monto, mc.moneda_codigo,
                       mc.concepto, mc.creado_en, cf.nombre AS caja_nombre, cf.propiedad_id
                FROM movimientos_caja mc
                JOIN sesiones_caja sc ON sc.id = mc.sesion_caja_id
                JOIN cajas_fisicas cf ON cf.id = sc.caja_fisica_id
                WHERE mc.creado_en >= :fecha_desde
                  AND mc.creado_en <= :fecha_hasta';
        $params = [
            'fecha_desde' => "{$fechaDesde} 00:00:00",
            'fecha_hasta' => "{$fechaHasta} 23:59:59",
        ];
        if ($propiedadId !== null && $propiedadId > 0) {
            $sql .= ' AND cf.propiedad_id = :propiedad_id';
            $params['propiedad_id'] = $propiedadId;
        }
        $sql .= ' ORDER BY mc.creado_en ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function obtenerMovimientosBancariosRango(string $fechaDesde, string $fechaHasta, ?int $propiedadId = null): array
    {
        $sql = 'SELECT mb.id, mb.cuenta_bancaria_id, mb.tipo_movimiento, mb.monto, mb.moneda_codigo,
                       mb.numero_operacion, mb.concepto, mb.fecha_operacion, mb.creado_en,
                       cb.banco_nombre, cb.numero_cuenta
                FROM movimientos_bancarios mb
                JOIN cuentas_bancarias cb ON cb.id = mb.cuenta_bancaria_id
                WHERE mb.fecha_operacion >= :fecha_desde
                  AND mb.fecha_operacion <= :fecha_hasta';
        $params = [
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
        ];
        $sql .= ' ORDER BY mb.fecha_operacion ASC, mb.id ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================================================================
    // 3. AGING — CUENTAS POR COBRAR (CxC)
    // =========================================================================

    /**
     * Obtiene cuotas de arrendamiento pendientes con fecha de vencimiento.
     *
     * @return array<int, array<string, mixed>>
     */
    public function obtenerCuotasArrendamientoPendientes(string $fechaCorte, ?int $propiedadId = null): array
    {
        $sql = 'SELECT ac.id, ac.arrendamiento_id, ac.periodo_codigo, ac.fecha_emision, ac.fecha_vencimiento,
                       ac.monto_renta, ac.estado, a.codigo AS arrendamiento_codigo,
                       u.id AS unidad_id, u.codigo AS unidad_codigo, u.propiedad_id,
                       p.id AS titular_id, TRIM(CONCAT(p.nombres, " ", p.apellido_paterno)) AS titular_nombre,
                       cc.id AS cargo_id, cc.total AS monto_cargo,
                       COALESCE((
                           SELECT SUM(ap.monto_aplicado)
                           FROM aplicaciones_pago ap
                           WHERE ap.cargo_id = cc.id AND ap.estado = "ACTIVA"
                       ), 0.00) AS monto_pagado
                FROM arrendamiento_cuotas ac
                JOIN arrendamientos a ON a.id = ac.arrendamiento_id
                JOIN unidades u ON u.id = a.unidad_id
                LEFT JOIN arrendamiento_personas arp ON arp.arrendamiento_id = a.id AND arp.tipo_relacion = "TITULAR"
                LEFT JOIN personas p ON p.id = arp.persona_id
                JOIN cargos_cuenta cc ON cc.id = ac.cargo_cuenta_id
                WHERE a.estado IN ("VIGENTE", "FINALIZADO")
                  AND ac.estado != "ANULADA"
                  AND ac.fecha_emision <= :fecha_corte';
        $params = ['fecha_corte' => $fechaCorte];
        if ($propiedadId !== null && $propiedadId > 0) {
            $sql .= ' AND u.propiedad_id = :propiedad_id';
            $params['propiedad_id'] = $propiedadId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene cargos de folios de huéspedes con saldo pendiente al corte.
     *
     * @return array<int, array<string, mixed>>
     */
    public function obtenerCargosFoliosHuespedesPendientes(string $fechaCorte, ?int $propiedadId = null): array
    {
        $sql = 'SELECT cc.id, cc.codigo AS cargo_codigo, cc.cuenta_folio_id, cc.origen_tipo, cc.concepto,
                       cc.total, cc.devengado_en, cc.creado_en,
                       cf.codigo AS folio_codigo, cf.reserva_id,
                       r.codigo AS reserva_codigo, r.fecha_salida,
                       p.id AS titular_id, TRIM(CONCAT(p.nombres, " ", p.apellido_paterno)) AS titular_nombre,
                       COALESCE((
                           SELECT SUM(ap.monto_aplicado)
                           FROM aplicaciones_pago ap
                           WHERE ap.cargo_id = cc.id AND ap.estado = "ACTIVA"
                       ), 0.00) AS monto_pagado
                FROM cargos_cuenta cc
                JOIN cuentas_folios cf ON cf.id = cc.cuenta_folio_id
                JOIN reservas r ON r.id = cf.reserva_id
                JOIN personas p ON p.id = cf.persona_titular_id
                WHERE cf.estado IN ("ABIERTA", "CONGELADA")
                  AND cc.estado = "DEVENGADO"
                  AND cc.origen_tipo != "RENTA_ARRENDAMIENTO"
                  AND DATE(cc.creado_en) <= :fecha_corte';
        $params = ['fecha_corte' => $fechaCorte];
        if ($propiedadId !== null && $propiedadId > 0) {
            $sql .= ' AND EXISTS (SELECT 1 FROM reserva_unidades ru JOIN unidades u ON u.id = ru.unidad_id WHERE ru.reserva_id = r.id AND u.propiedad_id = :propiedad_id)';
            $params['propiedad_id'] = $propiedadId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================================================================
    // 4. AGING — CUENTAS POR PAGAR (CxP)
    // =========================================================================

    /**
     * Obtiene gastos aprobados con saldo pendiente.
     *
     * @return array<int, array<string, mixed>>
     */
    public function obtenerGastosAprobadosPendientes(string $fechaCorte, ?int $propiedadId = null): array
    {
        $sql = 'SELECT g.id, g.codigo, g.descripcion_concepto AS concepto, g.acreedor_nombre, g.acreedor_documento,
                       g.fecha_emision, g.fecha_vencimiento, g.total, g.estado, g.propiedad_id,
                       COALESCE((
                            SELECT SUM(gap.monto_aplicado)
                            FROM gasto_aplicaciones_pago gap
                            WHERE gap.gasto_id = g.id AND gap.estado = "ACTIVO"
                       ), 0.00) AS monto_aplicado
                FROM gastos g
                WHERE g.estado = "APROBADO"
                  AND g.fecha_emision <= :fecha_corte';
        $params = ['fecha_corte' => $fechaCorte];
        if ($propiedadId !== null && $propiedadId > 0) {
            $sql .= ' AND (g.propiedad_id = :propiedad_id OR g.propiedad_id IS NULL)';
            $params['propiedad_id'] = $propiedadId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene cuentas por pagar a proveedores derivadas de compras.
     *
     * @return array<int, array<string, mixed>>
     */
    public function obtenerComprasCxPPendientes(string $fechaCorte, ?int $propiedadId = null): array
    {
        $sql = 'SELECT cxp.id, cxp.codigo, cxp.monto_total, cxp.monto_amortizado AS monto_pagado, cxp.saldo_pendiente,
                       cxp.estado, cxp.fecha_vencimiento, CONCAT(cc.serie, "-", cc.numero) AS numero_comprobante,
                       p.razon_social AS proveedor_nombre, p.numero_documento AS proveedor_ruc
                FROM cuentas_por_pagar cxp
                JOIN compra_comprobantes cc ON cc.id = cxp.comprobante_id
                JOIN proveedores p ON p.id = cxp.proveedor_id
                WHERE cxp.estado IN ("PENDIENTE", "AMORTIZADA_PARCIAL")
                  AND cc.fecha_emision <= :fecha_corte';
        $params = ['fecha_corte' => $fechaCorte];

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene el cierre hotelero de auditoría nocturna para la fecha y propiedad.
     *
     * @return array<string, mixed>|null
     */
    public function obtenerCierreHoteleroFecha(string $fecha, ?int $propiedadId = null): ?array
    {
        $sql = 'SELECT * FROM cierres_hoteleros WHERE fecha_hotelera = :fecha';
        $params = ['fecha' => $fecha];
        if ($propiedadId !== null && $propiedadId > 0) {
            $sql .= ' AND propiedad_id = :propiedad_id';
            $params['propiedad_id'] = $propiedadId;
        }
        $sql .= ' ORDER BY id DESC LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Obtiene métricas agregadas de devengo de alojamiento para una fecha y propiedad.
     *
     * @return array<string, mixed>
     */
    public function obtenerMetricasDevengoFecha(string $fecha, ?int $propiedadId = null): array
    {
        $sql = 'SELECT COUNT(DISTINCT unidad_id) AS habitaciones_vendidas,
                       COALESCE(SUM(importe_neto), 0.00) AS ingreso_alojamiento_neto,
                       COUNT(DISTINCT estadia_id) AS estadias_devengadas
                FROM devengos_alojamiento
                WHERE fecha_hotelera = :fecha AND estado = "DEVENGADO"';
        $params = ['fecha' => $fecha];
        if ($propiedadId !== null && $propiedadId > 0) {
            $sql .= ' AND propiedad_id = :propiedad_id';
            $params['propiedad_id'] = $propiedadId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: [
            'habitaciones_vendidas' => 0,
            'ingreso_alojamiento_neto' => '0.00',
            'estadias_devengadas' => 0,
        ];
    }

    // =========================================================================
    // 5. ANALÍTICA DE PERIODOS Y RENDIMIENTO POR CANAL (REPORTES-1A)
    // =========================================================================

    /**
     * Obtiene los cierres hoteleros auditados que caen en un rango de fechas.
     *
     * @return array<string, array<string, mixed>> Indexado por fecha_hotelera
     */
    public function obtenerCierresEnRango(string $desde, string $hasta, ?int $propiedadId = null): array
    {
        $sql = 'SELECT * FROM cierres_hoteleros
                WHERE fecha_hotelera >= :desde AND fecha_hotelera <= :hasta
                  AND estado = "CERRADO"';
        $params = ['desde' => $desde, 'hasta' => $hasta];
        if ($propiedadId !== null && $propiedadId > 0) {
            $sql .= ' AND propiedad_id = :propiedad_id';
            $params['propiedad_id'] = $propiedadId;
        }
        $sql .= ' ORDER BY fecha_hotelera ASC, id ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resultado = [];
        foreach ($filas as $f) {
            $resultado[$f['fecha_hotelera']] = $f;
        }
        return $resultado;
    }

    /**
     * Obtiene la agregación de devengos diarios de alojamiento agrupados por fecha hotelera.
     *
     * @return array<string, array<string, mixed>> Indexado por fecha_hotelera
     */
    public function obtenerDevengosEnRango(string $desde, string $hasta, ?int $propiedadId = null): array
    {
        $sql = 'SELECT fecha_hotelera,
                       COUNT(DISTINCT CASE WHEN es_cortesia = 0 AND importe_neto > 0 THEN unidad_id END) AS habitaciones_vendidas,
                       COUNT(DISTINCT CASE WHEN es_cortesia = 1 OR importe_neto = 0 THEN unidad_id END) AS habitaciones_cortesia,
                       COALESCE(SUM(importe_neto), 0.00) AS ingreso_alojamiento_neto,
                       COALESCE(SUM(impuesto_monto), 0.00) AS ingreso_alojamiento_impuestos,
                       COALESCE(SUM(importe_total), 0.00) AS ingreso_alojamiento_total,
                       COUNT(DISTINCT estadia_id) AS estadias_devengadas
                FROM devengos_alojamiento
                WHERE fecha_hotelera >= :desde AND fecha_hotelera <= :hasta
                  AND estado = "DEVENGADO"';
        $params = ['desde' => $desde, 'hasta' => $hasta];
        if ($propiedadId !== null && $propiedadId > 0) {
            $sql .= ' AND propiedad_id = :propiedad_id';
            $params['propiedad_id'] = $propiedadId;
        }
        $sql .= ' GROUP BY fecha_hotelera ORDER BY fecha_hotelera ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resultado = [];
        foreach ($filas as $f) {
            $resultado[$f['fecha_hotelera']] = $f;
        }
        return $resultado;
    }

    /**
     * Obtiene las órdenes de mantenimiento con bloqueo que intersectan el rango de fechas.
     *
     * @return array<int, array<string, mixed>>
     */
    public function obtenerMantenimientoBloqueanteEnRango(string $desde, string $hasta, ?int $propiedadId = null): array
    {
        $sql = 'SELECT mo.id, mo.codigo, mo.unidad_id, mo.propiedad_id,
                       mo.fecha_bloqueo_inicio, mo.fecha_bloqueo_fin, mo.estado
                FROM mantenimiento_ordenes mo
                WHERE mo.requiere_bloqueo = 1
                  AND mo.estado IN ("PROGRAMADA", "EN_PROCESO")
                  AND mo.fecha_bloqueo_inicio <= :hasta
                  AND mo.fecha_bloqueo_fin >= :desde';
        $params = ['desde' => $desde, 'hasta' => $hasta];
        if ($propiedadId !== null && $propiedadId > 0) {
            $sql .= ' AND mo.propiedad_id = :propiedad_id';
            $params['propiedad_id'] = $propiedadId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene las estadías activas y arrendamientos que intersectan el rango de fechas.
     *
     * @return array<int, array<string, mixed>>
     */
    public function obtenerEstadiasActivasEnRango(string $desde, string $hasta, ?int $propiedadId = null): array
    {
        $sql = 'SELECT e.id, e.unidad_id, e.fecha_entrada, e.fecha_salida_prevista,
                       u.propiedad_id
                FROM estadias e
                JOIN unidades u ON u.id = e.unidad_id
                WHERE e.estado = "EN_CURSO"
                  AND e.fecha_entrada <= :hasta
                  AND e.fecha_salida_prevista >= :desde';
        $params = ['desde' => $desde, 'hasta' => $hasta];
        if ($propiedadId !== null && $propiedadId > 0) {
            $sql .= ' AND u.propiedad_id = :propiedad_id';
            $params['propiedad_id'] = $propiedadId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene arrendamientos vigentes que intersectan el rango de fechas.
     *
     * @return array<int, array<string, mixed>>
     */
    public function obtenerArrendamientosEnRango(string $desde, string $hasta, ?int $propiedadId = null): array
    {
        $sql = 'SELECT a.id, a.unidad_id, a.fecha_inicio, a.fecha_fin, u.propiedad_id
                FROM arrendamientos a
                JOIN unidades u ON u.id = a.unidad_id
                WHERE a.estado = "VIGENTE"
                  AND a.fecha_inicio <= :hasta
                  AND a.fecha_fin >= :desde';
        $params = ['desde' => $desde, 'hasta' => $hasta];
        if ($propiedadId !== null && $propiedadId > 0) {
            $sql .= ' AND u.propiedad_id = :propiedad_id';
            $params['propiedad_id'] = $propiedadId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene métricas comerciales agrupadas por canal y origen de reserva.
     *
     * @return array<int, array<string, mixed>>
     */
    public function obtenerRendimientoCanalesReservas(string $desde, string $hasta, ?int $propiedadId = null): array
    {
        $sql = 'SELECT r.canal,
                       r.origen,
                       COUNT(DISTINCT r.id) AS total_reservas,
                       COUNT(DISTINCT CASE WHEN r.estado = "CONFIRMADA" THEN r.id END) AS reservas_confirmadas,
                       COUNT(DISTINCT CASE WHEN r.estado = "CANCELADA" THEN r.id END) AS reservas_canceladas,
                       COALESCE(SUM(r.noches), 0) AS noches_totales,
                       COALESCE(SUM(r.total), 0.00) AS ingresos_totales,
                       COALESCE(AVG(r.noches), 0.0) AS alos,
                       COALESCE(AVG(GREATEST(0, DATEDIFF(r.fecha_entrada, r.creado_en))), 0.0) AS lead_time_medio
                FROM reservas r';

        $params = ['desde' => $desde, 'hasta' => $hasta];

        if ($propiedadId !== null && $propiedadId > 0) {
            $sql .= ' JOIN reserva_unidades ru ON ru.reserva_id = r.id
                      JOIN unidades u ON u.id = ru.unidad_id
                      WHERE u.propiedad_id = :propiedad_id
                        AND r.fecha_entrada <= :hasta AND r.fecha_salida >= :desde';
            $params['propiedad_id'] = $propiedadId;
        } else {
            $sql .= ' WHERE r.fecha_entrada <= :hasta AND r.fecha_salida >= :desde';
        }

        $sql .= ' GROUP BY r.canal, r.origen ORDER BY ingresos_totales DESC, total_reservas DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene noches y eventos bloqueados por canales iCalendar externos (Airbnb, Booking, VRBO, etc.).
     *
     * @return array<int, array<string, mixed>>
     */
    public function obtenerBloqueosIcalCanalesEnRango(string $desde, string $hasta, ?int $propiedadId = null): array
    {
        $sql = 'SELECT cd.id AS canal_id,
                       cd.codigo AS canal_codigo,
                       cd.nombre AS canal_nombre,
                       cd.color_badge,
                       COUNT(DISTINCT e.id) AS total_bloqueos,
                       COALESCE(SUM(e.noches), 0) AS noches_bloqueadas_total
                FROM eventos_ical_externos e
                JOIN conexiones_ical ci ON ci.id = e.conexion_ical_id
                JOIN canales_distribucion cd ON cd.id = ci.canal_id
                JOIN unidades u ON u.id = ci.unidad_id
                WHERE e.estado_evento = "ACTIVO"
                  AND e.estado_bloqueo IN ("APLICADO", "APLICADO_CON_SOLAPAMIENTO")
                  AND e.fecha_inicio <= :hasta AND e.fecha_fin >= :desde';

        $params = ['desde' => $desde, 'hasta' => $hasta];
        if ($propiedadId !== null && $propiedadId > 0) {
            $sql .= ' AND u.propiedad_id = :propiedad_id';
            $params['propiedad_id'] = $propiedadId;
        }
        $sql .= ' GROUP BY cd.id ORDER BY cd.id ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene el desglose de ingresos devengados por tipo de cargo en cuentas folios.
     *
     * @return array<int, array<string, mixed>>
     */
    public function obtenerDesgloseCargosEnRango(string $desde, string $hasta, ?int $propiedadId = null): array
    {
        $sql = 'SELECT cc.origen_tipo,
                       COUNT(cc.id) AS total_cargos,
                       COALESCE(SUM(cc.subtotal), 0.00) AS subtotal,
                       COALESCE(SUM(cc.impuesto_total), 0.00) AS impuesto_total,
                       COALESCE(SUM(cc.total), 0.00) AS total
                FROM cargos_cuenta cc
                JOIN cuentas_folios cf ON cf.id = cc.cuenta_folio_id';

        $params = ['desde' => $desde, 'hasta' => $hasta];

        if ($propiedadId !== null && $propiedadId > 0) {
            $sql .= ' LEFT JOIN reservas r ON r.id = cf.reserva_id
                      LEFT JOIN reserva_unidades ru ON ru.reserva_id = r.id
                      LEFT JOIN unidades u ON u.id = ru.unidad_id
                      WHERE (u.propiedad_id = :propiedad_id OR u.propiedad_id IS NULL)
                        AND cc.estado = "DEVENGADO"
                        AND DATE(cc.devengado_en) >= :desde AND DATE(cc.devengado_en) <= :hasta';
            $params['propiedad_id'] = $propiedadId;
        } else {
            $sql .= ' WHERE cc.estado = "DEVENGADO"
                        AND DATE(cc.devengado_en) >= :desde AND DATE(cc.devengado_en) <= :hasta';
        }

        $sql .= ' GROUP BY cc.origen_tipo ORDER BY total DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene el desglose de ingresos cobrados en tesorería por método de pago.
     *
     * @return array<int, array<string, mixed>>
     */
    public function obtenerDesgloseCobrosEnRango(string $desde, string $hasta, ?int $propiedadId = null): array
    {
        $sql = 'SELECT mp.codigo AS metodo_codigo,
                       mp.nombre AS metodo_nombre,
                       COUNT(pc.id) AS total_cobros,
                       COALESCE(SUM(pc.monto_total), 0.00) AS monto_total
                FROM pagos_cuenta pc
                JOIN metodos_pago mp ON mp.id = pc.metodo_pago_id
                WHERE pc.estado = "CONFIRMADO"
                  AND DATE(pc.creado_en) >= :desde AND DATE(pc.creado_en) <= :hasta';

        $params = ['desde' => $desde, 'hasta' => $hasta];
        $sql .= ' GROUP BY mp.id ORDER BY monto_total DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene las métricas netas de pasarelas de pago para el periodo.
     *
     * @return array<string, mixed>
     */
    public function obtenerMetricasPasarelasEnRango(string $desde, string $hasta, ?int $propiedadId = null): array
    {
        $sql = 'SELECT COALESCE(SUM(COALESCE(monto_cobrado, monto_esperado)), 0.00) AS monto_cobrado,
                       COALESCE(SUM(monto_reembolsado), 0.00) AS monto_reembolsado,
                       COUNT(*) AS total_transacciones
                FROM pagos_transacciones_pasarela
                WHERE estado_pago = "APROBADO"
                  AND DATE(creado_en) >= :desde AND DATE(creado_en) <= :hasta';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['desde' => $desde, 'hasta' => $hasta]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: [
            'monto_cobrado' => '0.00',
            'monto_reembolsado' => '0.00',
            'total_transacciones' => 0,
        ];
    }
}

