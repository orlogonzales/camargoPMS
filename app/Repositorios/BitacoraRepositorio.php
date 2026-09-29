<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\BitacoraEntrada;
use CamargoPMS\Modelos\BitacoraSeguimiento;
use CamargoPMS\Nucleo\BaseDatos;
use PDO;

/**
 * Repositorio para la persistencia del Libro de Guardia y Bitácora Operacional.
 *
 * Implementa las operaciones de base de datos conforme a D-089 (BITÁCORA-1):
 * - Feed cronológico de entradas con enriquecimiento relacional.
 * - Registro append-only en bitacora_seguimientos.
 * - Preservación de trazabilidad histórica sin eliminaciones físicas (ANULAR != DELETE).
 */
class BitacoraRepositorio
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? BaseDatos::conexion();
    }

    /**
     * Inserta una nueva entrada en la bitácora operativa.
     */
    public function insertarEntrada(BitacoraEntrada $entrada): BitacoraEntrada
    {
        $sql = 'INSERT INTO bitacora_entradas (
                    propiedad_id, usuario_creador_id, tipo, prioridad, titulo, contenido,
                    turno, fecha_operativa, estado, unidad_id, reserva_id, estadia_id,
                    mantenimiento_id, sesion_caja_id, resuelta_por_usuario_id, resuelta_en,
                    nota_resolucion, anulada_por_usuario_id, anulada_en, motivo_anulacion,
                    creado_en
                ) VALUES (
                    :propiedad_id, :usuario_creador_id, :tipo, :prioridad, :titulo, :contenido,
                    :turno, :fecha_operativa, :estado, :unidad_id, :reserva_id, :estadia_id,
                    :mantenimiento_id, :sesion_caja_id, :resuelta_por_usuario_id, :resuelta_en,
                    :nota_resolucion, :anulada_por_usuario_id, :anulada_en, :motivo_anulacion,
                    NOW()
                )';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'propiedad_id' => $entrada->obtenerPropiedadId(),
            'usuario_creador_id' => $entrada->obtenerUsuarioCreadorId(),
            'tipo' => $entrada->obtenerTipo(),
            'prioridad' => $entrada->obtenerPrioridad(),
            'titulo' => $entrada->obtenerTitulo(),
            'contenido' => $entrada->obtenerContenido(),
            'turno' => $entrada->obtenerTurno(),
            'fecha_operativa' => $entrada->obtenerFechaOperativa(),
            'estado' => $entrada->obtenerEstado(),
            'unidad_id' => $entrada->obtenerUnidadId(),
            'reserva_id' => $entrada->obtenerReservaId(),
            'estadia_id' => $entrada->obtenerEstadiaId(),
            'mantenimiento_id' => $entrada->obtenerMantenimientoId(),
            'sesion_caja_id' => $entrada->obtenerSesionCajaId(),
            'resuelta_por_usuario_id' => $entrada->obtenerResueltaPorUsuarioId(),
            'resuelta_en' => $entrada->obtenerResueltaEn(),
            'nota_resolucion' => $entrada->obtenerNotaResolucion(),
            'anulada_por_usuario_id' => $entrada->obtenerAnuladaPorUsuarioId(),
            'anulada_en' => $entrada->obtenerAnuladaEn(),
            'motivo_anulacion' => $entrada->obtenerMotivoAnulacion(),
        ]);

        $id = (int) $this->pdo->lastInsertId();
        return $this->buscarPorId($id, false) ?? $entrada;
    }

    /**
     * Inserta un seguimiento o evento append-only vinculado a una entrada.
     */
    public function insertarSeguimiento(BitacoraSeguimiento $seguimiento): BitacoraSeguimiento
    {
        $sql = 'INSERT INTO bitacora_seguimientos (
                    entrada_id, usuario_id, tipo_evento, contenido,
                    estado_anterior, estado_nuevo, creado_en
                ) VALUES (
                    :entrada_id, :usuario_id, :tipo_evento, :contenido,
                    :estado_anterior, :estado_nuevo, NOW()
                )';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'entrada_id' => $seguimiento->obtenerEntradaId(),
            'usuario_id' => $seguimiento->obtenerUsuarioId(),
            'tipo_evento' => $seguimiento->obtenerTipoEvento(),
            'contenido' => $seguimiento->obtenerContenido(),
            'estado_anterior' => $seguimiento->obtenerEstadoAnterior(),
            'estado_nuevo' => $seguimiento->obtenerEstadoNuevo(),
        ]);

        $id = (int) $this->pdo->lastInsertId();
        $seguimientoNuevo = BitacoraSeguimiento::fromArray(array_merge($seguimiento->toArray(), ['id' => $id]));
        return $seguimientoNuevo;
    }

    /**
     * Actualiza el estado a RESUELTA registrando el usuario y nota de resolución.
     */
    public function actualizarResolucion(
        int $entradaId,
        string $nuevoEstado,
        ?int $resolutorId,
        ?string $resueltaEn,
        ?string $notaResolucion
    ): bool {
        $sql = 'UPDATE bitacora_entradas SET
                    estado = :estado,
                    resuelta_por_usuario_id = :resuelta_por_usuario_id,
                    resuelta_en = :resuelta_en,
                    nota_resolucion = :nota_resolucion,
                    actualizado_en = NOW()
                WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'id' => $entradaId,
            'estado' => $nuevoEstado,
            'resuelta_por_usuario_id' => $resolutorId,
            'resuelta_en' => $resueltaEn,
            'nota_resolucion' => $notaResolucion,
        ]);
    }

    /**
     * Actualiza el estado a ANULADA con justificación supervisada (ANULAR != DELETE).
     */
    public function actualizarAnulacion(
        int $entradaId,
        int $anuladorId,
        string $motivoAnulacion,
        string $anuladaEn
    ): bool {
        $sql = 'UPDATE bitacora_entradas SET
                    estado = :estado,
                    anulada_por_usuario_id = :anulada_por_usuario_id,
                    motivo_anulacion = :motivo_anulacion,
                    anulada_en = :anulada_en,
                    actualizado_en = NOW()
                WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'id' => $entradaId,
            'estado' => BitacoraEntrada::ESTADO_ANULADA,
            'anulada_por_usuario_id' => $anuladorId,
            'motivo_anulacion' => $motivoAnulacion,
            'anulada_en' => $anuladaEn,
        ]);
    }

    /**
     * Actualiza el estado general de una entrada (por ejemplo, transiciones PENDIENTE <-> EN_PROCESO).
     */
    public function actualizarEstado(int $entradaId, string $nuevoEstado): bool
    {
        $sql = 'UPDATE bitacora_entradas SET
                    estado = :estado,
                    actualizado_en = NOW()
                WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'id' => $entradaId,
            'estado' => $nuevoEstado,
        ]);
    }

    /**
     * Busca una entrada por ID con todos sus metadatos enriquecidos y seguimientos.
     */
    public function buscarPorId(int $id, bool $cargarSeguimientos = true): ?BitacoraEntrada
    {
        $sql = 'SELECT
                    e.*,
                    p.nombre AS propiedad_nombre,
                    u.nombre_usuario AS usuario_creador_nombre,
                    un.codigo AS unidad_numero,
                    ur.nombre_usuario AS resuelta_por_nombre,
                    ua.nombre_usuario AS anulada_por_nombre
                FROM bitacora_entradas e
                INNER JOIN propiedades p ON p.id = e.propiedad_id
                INNER JOIN usuarios u ON u.id = e.usuario_creador_id
                LEFT JOIN unidades un ON un.id = e.unidad_id
                LEFT JOIN usuarios ur ON ur.id = e.resuelta_por_usuario_id
                LEFT JOIN usuarios ua ON ua.id = e.anulada_por_usuario_id
                WHERE e.id = :id
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fila) {
            return null;
        }

        $entrada = BitacoraEntrada::fromArray($fila);

        if ($cargarSeguimientos) {
            $seguimientos = $this->buscarSeguimientosPorEntradaId($id);
            $entrada->asignarSeguimientos($seguimientos);
        }

        return $entrada;
    }

    /**
     * Obtiene los seguimientos cronológicos asociados a una entrada.
     *
     * @return BitacoraSeguimiento[]
     */
    public function buscarSeguimientosPorEntradaId(int $entradaId): array
    {
        $sql = 'SELECT
                    s.*,
                    u.nombre_usuario AS usuario_nombre
                FROM bitacora_seguimientos s
                INNER JOIN usuarios u ON u.id = s.usuario_id
                WHERE s.entrada_id = :entrada_id
                ORDER BY s.creado_en ASC, s.id ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':entrada_id', $entradaId, PDO::PARAM_INT);
        $stmt->execute();

        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(static fn(array $fila) => BitacoraSeguimiento::fromArray($fila), $filas);
    }

    /**
     * Lista entradas con filtros dinámicos, paginación y orden cronológico inverso.
     *
     * @param array $filtros
     * @param int $limite
     * @param int $offset
     * @return BitacoraEntrada[]
     */
    public function listar(array $filtros = [], int $limite = 50, int $offset = 0): array
    {
        [$condiciones, $parametros] = $this->construirWhereFiltros($filtros);

        $sql = 'SELECT
                    e.*,
                    p.nombre AS propiedad_nombre,
                    u.nombre_usuario AS usuario_creador_nombre,
                    un.codigo AS unidad_numero,
                    ur.nombre_usuario AS resuelta_por_nombre,
                    ua.nombre_usuario AS anulada_por_nombre
                FROM bitacora_entradas e
                INNER JOIN propiedades p ON p.id = e.propiedad_id
                INNER JOIN usuarios u ON u.id = e.usuario_creador_id
                LEFT JOIN unidades un ON un.id = e.unidad_id
                LEFT JOIN usuarios ur ON ur.id = e.resuelta_por_usuario_id
                LEFT JOIN usuarios ua ON ua.id = e.anulada_por_usuario_id';

        if (!empty($condiciones)) {
            $sql .= ' WHERE ' . implode(' AND ', $condiciones);
        }

        $sql .= ' ORDER BY e.fecha_operativa DESC, e.creado_en DESC, e.id DESC';
        $sql .= ' LIMIT :limite OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($parametros as $clave => $valor) {
            $stmt->bindValue($clave, $valor);
        }
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(static fn(array $fila) => BitacoraEntrada::fromArray($fila), $filas);
    }

    /**
     * Cuenta total de registros según los filtros provistos.
     */
    public function contar(array $filtros = []): int
    {
        [$condiciones, $parametros] = $this->construirWhereFiltros($filtros);

        $sql = 'SELECT COUNT(*) FROM bitacora_entradas e';
        if (!empty($condiciones)) {
            $sql .= ' WHERE ' . implode(' AND ', $condiciones);
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($parametros as $clave => $valor) {
            $stmt->bindValue($clave, $valor);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * Obtiene métricas agregadas operacionales para dashboard/badges de bitácora.
     */
    public function obtenerMetricas(int $propiedadId = 0, ?string $fechaOperativa = null): array
    {
        $fecha = $fechaOperativa ?? date('Y-m-d');
        $params = ['fecha_1' => $fecha, 'fecha_2' => $fecha];
        $filtroPropiedad = '';

        if ($propiedadId > 0) {
            $filtroPropiedad = ' AND propiedad_id = :propiedad_id';
            $params['propiedad_id'] = $propiedadId;
        }

        $sql = "SELECT
                    COUNT(*) AS total_general,
                    SUM(CASE WHEN fecha_operativa = :fecha_1 THEN 1 ELSE 0 END) AS total_hoy,
                    SUM(CASE WHEN estado IN ('REGISTRADA', 'PENDIENTE') THEN 1 ELSE 0 END) AS pendientes,
                    SUM(CASE WHEN estado = 'EN_PROCESO' THEN 1 ELSE 0 END) AS en_proceso,
                    SUM(CASE WHEN prioridad = 'URGENTE' AND estado NOT IN ('RESUELTA', 'ANULADA') THEN 1 ELSE 0 END) AS urgentes_activas,
                    SUM(CASE WHEN tipo = 'CONSIGNA' AND estado NOT IN ('RESUELTA', 'ANULADA') THEN 1 ELSE 0 END) AS consignas_activas,
                    SUM(CASE WHEN tipo = 'INCIDENCIA' AND estado NOT IN ('RESUELTA', 'ANULADA') THEN 1 ELSE 0 END) AS incidencias_activas,
                    SUM(CASE WHEN estado = 'RESUELTA' AND fecha_operativa = :fecha_2 THEN 1 ELSE 0 END) AS resueltas_hoy,
                    SUM(CASE WHEN estado = 'ANULADA' THEN 1 ELSE 0 END) AS anuladas_total
                FROM bitacora_entradas
                WHERE 1=1 {$filtroPropiedad}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $resultado = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'total_general' => (int) ($resultado['total_general'] ?? 0),
            'total_hoy' => (int) ($resultado['total_hoy'] ?? 0),
            'pendientes' => (int) ($resultado['pendientes'] ?? 0),
            'en_proceso' => (int) ($resultado['en_proceso'] ?? 0),
            'urgentes_activas' => (int) ($resultado['urgentes_activas'] ?? 0),
            'consignas_activas' => (int) ($resultado['consignas_activas'] ?? 0),
            'incidencias_activas' => (int) ($resultado['incidencias_activas'] ?? 0),
            'resueltas_hoy' => (int) ($resultado['resueltas_hoy'] ?? 0),
            'anuladas_total' => (int) ($resultado['anuladas_total'] ?? 0),
        ];
    }

    /**
     * Construye cláusulas WHERE y parámetros de filtrado seguro.
     */
    private function construirWhereFiltros(array $filtros): array
    {
        $condiciones = [];
        $parametros = [];

        if (!empty($filtros['propiedad_id'])) {
            $condiciones[] = 'e.propiedad_id = :propiedad_id';
            $parametros[':propiedad_id'] = (int) $filtros['propiedad_id'];
        }

        if (!empty($filtros['tipo'])) {
            $condiciones[] = 'e.tipo = :tipo';
            $parametros[':tipo'] = (string) $filtros['tipo'];
        }

        if (!empty($filtros['prioridad'])) {
            $condiciones[] = 'e.prioridad = :prioridad';
            $parametros[':prioridad'] = (string) $filtros['prioridad'];
        }

        if (!empty($filtros['turno'])) {
            $condiciones[] = 'e.turno = :turno';
            $parametros[':turno'] = (string) $filtros['turno'];
        }

        if (!empty($filtros['estado'])) {
            $condiciones[] = 'e.estado = :estado';
            $parametros[':estado'] = (string) $filtros['estado'];
        }

        if (!empty($filtros['no_anuladas'])) {
            $condiciones[] = "e.estado <> 'ANULADA'";
        }

        if (!empty($filtros['unidad_id'])) {
            $condiciones[] = 'e.unidad_id = :unidad_id';
            $parametros[':unidad_id'] = (int) $filtros['unidad_id'];
        }

        if (!empty($filtros['fecha_operativa'])) {
            $condiciones[] = 'e.fecha_operativa = :fecha_operativa';
            $parametros[':fecha_operativa'] = (string) $filtros['fecha_operativa'];
        }

        if (!empty($filtros['fecha_desde'])) {
            $condiciones[] = 'e.fecha_operativa >= :fecha_desde';
            $parametros[':fecha_desde'] = (string) $filtros['fecha_desde'];
        }

        if (!empty($filtros['fecha_hasta'])) {
            $condiciones[] = 'e.fecha_operativa <= :fecha_hasta';
            $parametros[':fecha_hasta'] = (string) $filtros['fecha_hasta'];
        }

        if (!empty($filtros['busqueda'])) {
            $condiciones[] = '(e.titulo LIKE :busqueda OR e.contenido LIKE :busqueda)';
            $parametros[':busqueda'] = '%' . trim((string) $filtros['busqueda']) . '%';
        }

        return [$condiciones, $parametros];
    }
}
