<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Nucleo\BaseDatos;
use PDO;

/**
 * Repositorio de lectura por lotes para el Tape Chart y Rack Hotelero (TAPE-CHART-1 / D-084).
 *
 * Principios vinculantes:
 * - TAPE CHART != FUENTE DE VERDAD (solo lectura agregada sin estado persistido).
 * - O(1) en número de consultas respecto al número de celdas (cero antipatrón N x M).
 * - Todas las extracciones se realizan en bloque por propiedad e intervalo.
 */
class TapeChartRepositorio
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
     * Carga por lotes las unidades físicas activas de una propiedad.
     *
     * @param int $propiedadId
     * @param int|null $tipoUnidadId
     * @param string|null $pisoNivel
     * @return array<int, array<string, mixed>>
     */
    public function obtenerUnidadesPropiedad(
        int $propiedadId,
        ?int $tipoUnidadId = null,
        ?string $pisoNivel = null
    ): array {
        $sql = 'SELECT u.id, u.codigo, u.nombre, u.propiedad_id, p.nombre AS propiedad_nombre,
                       p.zona_horaria AS propiedad_zona_horaria,
                       u.tipo_unidad_id, tu.nombre AS tipo_unidad_nombre,
                       u.piso_nivel, u.capacidad_personas, u.estado
                FROM unidades u
                INNER JOIN propiedades p ON p.id = u.propiedad_id
                INNER JOIN tipos_unidad tu ON tu.id = u.tipo_unidad_id
                WHERE u.propiedad_id = :propiedad_id
                  AND u.estado = "ACTIVO"';

        $params = ['propiedad_id' => $propiedadId];

        if ($tipoUnidadId !== null && $tipoUnidadId > 0) {
            $sql .= ' AND u.tipo_unidad_id = :tipo_id';
            $params['tipo_id'] = $tipoUnidadId;
        }

        if ($pisoNivel !== null && trim($pisoNivel) !== '') {
            $sql .= ' AND u.piso_nivel = :piso';
            $params['piso'] = trim($pisoNivel);
        }

        $sql .= ' ORDER BY u.piso_nivel ASC, u.codigo ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Carga por lotes el inventario sparse de una propiedad en el intervalo [fechaDesde, fechaHasta).
     *
     * @param int $propiedadId
     * @param string $fechaDesde Inclusive (Y-m-d)
     * @param string $fechaHasta Exclusive (Y-m-d)
     * @return array<int, array<string, mixed>>
     */
    public function obtenerInventarioRango(
        int $propiedadId,
        string $fechaDesde,
        string $fechaHasta
    ): array {
        $sql = 'SELECT inv.id, inv.unidad_id, inv.fecha, inv.tipo_bloqueo, inv.origen_tipo, inv.origen_id
                FROM inventario_diario_unidades inv
                INNER JOIN unidades u ON inv.unidad_id = u.id
                WHERE u.propiedad_id = :propiedad_id
                  AND inv.fecha >= :desde
                  AND inv.fecha < :hasta
                ORDER BY inv.unidad_id ASC, inv.fecha ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'propiedad_id' => $propiedadId,
            'desde' => $fechaDesde,
            'hasta' => $fechaHasta,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Carga por lotes las reservas activas que intersectan el rango.
     *
     * @param int $propiedadId
     * @param string $fechaDesde
     * @param string $fechaHasta
     * @return array<int, array<string, mixed>>
     */
    public function obtenerReservasEnRango(
        int $propiedadId,
        string $fechaDesde,
        string $fechaHasta
    ): array {
        $sql = 'SELECT r.id, r.codigo, r.estado, r.creado_en AS fecha_creacion, r.expira_en, r.moneda_codigo, r.total,
                       r.persona_titular_id,
                       TRIM(CONCAT(COALESCE(p.nombres, ""), " ", COALESCE(p.apellido_paterno, ""), " ", COALESCE(p.apellido_materno, ""))) AS titular_nombre_completo,
                       COALESCE(p.apellido_paterno, p.nombres) AS titular_apellido,
                       p.nombres AS titular_nombres,
                       COALESCE((SELECT pd.numero_documento FROM personas_documentos pd WHERE pd.persona_id = p.id LIMIT 1), "") AS titular_documento,
                       ru.id AS reserva_unidad_id, ru.unidad_id, r.fecha_entrada, r.fecha_salida, ru.noches, ru.precio_unitario_noche AS precio_por_noche, ru.total AS total_linea
                FROM reservas r
                INNER JOIN reserva_unidades ru ON r.id = ru.reserva_id
                INNER JOIN unidades u ON ru.unidad_id = u.id
                INNER JOIN personas p ON r.persona_titular_id = p.id
                WHERE u.propiedad_id = :propiedad_id
                  AND r.estado IN ("PENDIENTE", "CONFIRMADA")
                  AND r.fecha_entrada < :hasta
                  AND r.fecha_salida > :desde
                ORDER BY ru.unidad_id ASC, r.fecha_entrada ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'propiedad_id' => $propiedadId,
            'desde' => $fechaDesde,
            'hasta' => $fechaHasta,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Carga por lotes las estadías que intersectan el rango.
     *
     * @param int $propiedadId
     * @param string $fechaDesde
     * @param string $fechaHasta
     * @return array<int, array<string, mixed>>
     */
    public function obtenerEstadiasEnRango(
        int $propiedadId,
        string $fechaDesde,
        string $fechaHasta
    ): array {
        $sql = 'SELECT e.id, e.codigo, e.reserva_id, e.reserva_unidad_id, e.unidad_id,
                       e.fecha_entrada, e.fecha_salida_prevista AS fecha_salida_programada,
                       DATE(e.checkout_en) AS fecha_salida_real, e.estado,
                       DATEDIFF(e.fecha_salida_prevista, e.fecha_entrada) AS noches,
                       r.codigo AS reserva_codigo,
                       TRIM(CONCAT(COALESCE(pt.nombres, ""), " ", COALESCE(pt.apellido_paterno, ""), " ", COALESCE(pt.apellido_materno, ""))) AS titular_reserva_nombre,
                       TRIM(CONCAT(COALESCE(resp.nombres, ""), " ", COALESCE(resp.apellido_paterno, ""), " ", COALESCE(resp.apellido_materno, ""))) AS titular_huesped_responsable,
                       COALESCE(resp.apellido_paterno, resp.nombres, pt.apellido_paterno, pt.nombres) AS responsable_apellido,
                       COALESCE(resp.nombres, pt.nombres) AS responsable_nombres,
                       COALESCE((SELECT pd.numero_documento FROM personas_documentos pd WHERE pd.persona_id = resp.id LIMIT 1),
                                (SELECT pd2.numero_documento FROM personas_documentos pd2 WHERE pd2.persona_id = pt.id LIMIT 1), "") AS responsable_documento
                FROM estadias e
                INNER JOIN unidades u ON e.unidad_id = u.id
                INNER JOIN reservas r ON e.reserva_id = r.id
                INNER JOIN personas pt ON r.persona_titular_id = pt.id
                LEFT JOIN estadia_huespedes eh ON eh.estadia_id = e.id AND eh.es_responsable = 1
                LEFT JOIN personas resp ON eh.persona_id = resp.id
                WHERE u.propiedad_id = :propiedad_id
                  AND e.estado IN ("EN_CURSO", "FINALIZADA")
                  AND e.fecha_entrada < :hasta
                  AND (
                      CASE
                          WHEN e.estado = "FINALIZADA" AND e.checkout_en IS NOT NULL
                          THEN DATE(e.checkout_en)
                          ELSE e.fecha_salida_prevista
                      END > :desde
                  )
                ORDER BY e.unidad_id ASC, e.fecha_entrada ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'propiedad_id' => $propiedadId,
            'desde' => $fechaDesde,
            'hasta' => $fechaHasta,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Carga por lotes los contratos de arrendamiento que intersectan el rango.
     *
     * @param int $propiedadId
     * @param string $fechaDesde
     * @param string $fechaHasta
     * @return array<int, array<string, mixed>>
     */
    public function obtenerArrendamientosEnRango(
        int $propiedadId,
        string $fechaDesde,
        string $fechaHasta
    ): array {
        $sql = 'SELECT a.id, a.codigo, a.unidad_id, a.fecha_inicio, a.fecha_fin, a.estado,
                       a.renta_mensual, a.moneda_codigo,
                       TRIM(CONCAT(COALESCE(per.nombres, ""), " ", COALESCE(per.apellido_paterno, ""), " ", COALESCE(per.apellido_materno, ""))) AS titular_nombre_completo,
                       COALESCE(per.apellido_paterno, per.nombres) AS titular_apellido,
                       per.nombres AS titular_nombres,
                       COALESCE((SELECT pd.numero_documento FROM personas_documentos pd WHERE pd.persona_id = per.id LIMIT 1), "") AS titular_documento
                FROM arrendamientos a
                INNER JOIN unidades u ON a.unidad_id = u.id
                LEFT JOIN arrendamiento_personas ap ON ap.arrendamiento_id = a.id AND ap.tipo_relacion = "TITULAR"
                LEFT JOIN personas per ON ap.persona_id = per.id
                WHERE u.propiedad_id = :propiedad_id
                  AND a.estado IN ("VIGENTE", "FINALIZADO")
                  AND a.fecha_inicio < :hasta
                  AND a.fecha_fin > :desde
                ORDER BY a.unidad_id ASC, a.fecha_inicio ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'propiedad_id' => $propiedadId,
            'desde' => $fechaDesde,
            'hasta' => $fechaHasta,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Carga por lotes las órdenes de mantenimiento activas y sus incidencias asociadas.
     *
     * @param int $propiedadId
     * @param string $fechaDesde
     * @param string $fechaHasta
     * @return array<int, array<string, mixed>>
     */
    public function obtenerMantenimientoEnRango(
        int $propiedadId,
        string $fechaDesde,
        string $fechaHasta
    ): array {
        $sql = 'SELECT o.id, o.codigo, o.unidad_id, o.tipo, o.prioridad, o.estado,
                       o.requiere_bloqueo, o.fecha_bloqueo_inicio, o.fecha_bloqueo_fin,
                       o.descripcion AS descripcion_trabajo
                FROM mantenimiento_ordenes o
                INNER JOIN unidades u ON o.unidad_id = u.id
                WHERE u.propiedad_id = :propiedad_id
                  AND o.estado IN ("PROGRAMADA", "EN_PROCESO")
                  AND (
                      (o.requiere_bloqueo = 1 AND o.fecha_bloqueo_inicio < :hasta AND o.fecha_bloqueo_fin > :desde)
                      OR
                      (o.requiere_bloqueo = 0)
                  )
                ORDER BY o.unidad_id ASC, o.id ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'propiedad_id' => $propiedadId,
            'desde' => $fechaDesde,
            'hasta' => $fechaHasta,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Carga por lotes el estado de limpieza actual de todas las unidades de la propiedad.
     *
     * @param int $propiedadId
     * @return array<int, array<string, mixed>>
     */
    public function obtenerLimpiezaActual(int $propiedadId): array
    {
        $sql = 'SELECT u.id AS unidad_id,
                       COALESCE(hul.estado_limpieza, "SUCIA") AS estado_limpieza,
                       hul.tarea_activa_id,
                       hul.ultima_limpieza_en,
                       hul.ultima_inspeccion_en,
                       t.codigo AS tarea_codigo,
                       t.tipo_tarea AS tarea_tipo,
                       t.prioridad AS tarea_prioridad,
                       t.estado AS tarea_estado,
                       CONCAT(COALESCE(p_col.nombres, ""), " ", COALESCE(p_col.apellido_paterno, "")) AS camarera_nombre
                FROM unidades u
                LEFT JOIN housekeeping_unidades_limpieza hul ON hul.unidad_id = u.id
                LEFT JOIN housekeeping_tareas t ON t.id = hul.tarea_activa_id
                LEFT JOIN colaboradores col ON col.id = t.camarera_colaborador_id
                LEFT JOIN personas p_col ON p_col.id = col.persona_id
                WHERE u.propiedad_id = :propiedad_id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['propiedad_id' => $propiedadId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
