<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\Reserva;
use CamargoPMS\Modelos\ReservaUnidad;
use CamargoPMS\Nucleo\BaseDatos;
use PDO;

/**
 * Repositorio de persistencia para el módulo de reservas directas (RESERVAS-1).
 *
 * Principios vinculantes:
 * - RESERVA != DISPONIBILIDAD != INVENTARIO != ESTANCIA != PAGO.
 * - Multiunidad: 1 Reserva : N Unidades a través de reserva_unidades.
 * - Preservación histórica: Cero DELETE sobre reservas.
 * - Consultas parametrizadas y PDO estricto.
 */
class ReservaRepositorio
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
     * Busca una reserva por su ID primario.
     */
    public function buscarPorId(int $id, bool $cargarUnidades = true): ?Reserva
    {
        $sql = 'SELECT r.*,
                       TRIM(CONCAT(COALESCE(p.nombres, ""), " ", COALESCE(p.apellido_paterno, ""), " ", COALESCE(p.apellido_materno, ""))) AS titular_nombre_completo,
                       act_c.nombre AS creador_nombre,
                       act_conf.nombre AS confirmador_nombre,
                       act_canc.nombre AS cancelador_nombre
                FROM reservas r
                INNER JOIN personas p ON r.persona_titular_id = p.id
                LEFT JOIN actores act_c ON r.creado_por_actor_id = act_c.id
                LEFT JOIN actores act_conf ON r.confirmada_por_actor_id = act_conf.id
                LEFT JOIN actores act_canc ON r.cancelada_por_actor_id = act_canc.id
                WHERE r.id = :id
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fila) {
            return null;
        }

        $reserva = Reserva::desdeArreglo($fila);

        // Cargar documento y contacto del titular si están disponibles
        $this->cargarContactoTitular($reserva);

        if ($cargarUnidades) {
            $unidades = $this->obtenerUnidadesPorReservaId($id);
            $reserva->asignarUnidades($unidades);
        }

        return $reserva;
    }

    /**
     * Busca una reserva por su código comercial único.
     */
    public function buscarPorCodigo(string $codigo, bool $cargarUnidades = true): ?Reserva
    {
        $sql = 'SELECT r.*,
                       TRIM(CONCAT(COALESCE(p.nombres, ""), " ", COALESCE(p.apellido_paterno, ""), " ", COALESCE(p.apellido_materno, ""))) AS titular_nombre_completo,
                       act_c.nombre AS creador_nombre,
                       act_conf.nombre AS confirmador_nombre,
                       act_canc.nombre AS cancelador_nombre
                FROM reservas r
                INNER JOIN personas p ON r.persona_titular_id = p.id
                LEFT JOIN actores act_c ON r.creado_por_actor_id = act_c.id
                LEFT JOIN actores act_conf ON r.confirmada_por_actor_id = act_conf.id
                LEFT JOIN actores act_canc ON r.cancelada_por_actor_id = act_canc.id
                WHERE r.codigo = :codigo
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':codigo', trim(strtoupper($codigo)), PDO::PARAM_STR);
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fila) {
            return null;
        }

        $reserva = Reserva::desdeArreglo($fila);
        $this->cargarContactoTitular($reserva);

        if ($cargarUnidades && $reserva->obtenerId() !== null) {
            $unidades = $this->obtenerUnidadesPorReservaId($reserva->obtenerId());
            $reserva->asignarUnidades($unidades);
        }

        return $reserva;
    }

    /**
     * Inserta un nuevo registro maestro de reserva. Retorna el ID generado.
     */
    public function crear(Reserva $reserva): int
    {
        $sql = 'INSERT INTO reservas (
                    codigo, persona_titular_id, fecha_entrada, fecha_salida, noches,
                    estado, expira_en, canal, origen, moneda_codigo,
                    subtotal, impuesto_total, total, observaciones,
                    confirmada_en, confirmada_por_actor_id,
                    creado_por_actor_id, creado_en
                ) VALUES (
                    :codigo, :persona_titular_id, :fecha_entrada, :fecha_salida, :noches,
                    :estado, :expira_en, :canal, :origen, :moneda_codigo,
                    :subtotal, :impuesto_total, :total, :observaciones,
                    :confirmada_en, :confirmada_por_actor_id,
                    :creado_por_actor_id, NOW()
                )';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':codigo', $reserva->obtenerCodigo(), PDO::PARAM_STR);
        $stmt->bindValue(':persona_titular_id', $reserva->obtenerPersonaTitularId(), PDO::PARAM_INT);
        $stmt->bindValue(':fecha_entrada', $reserva->obtenerFechaEntrada(), PDO::PARAM_STR);
        $stmt->bindValue(':fecha_salida', $reserva->obtenerFechaSalida(), PDO::PARAM_STR);
        $stmt->bindValue(':noches', $reserva->obtenerNoches(), PDO::PARAM_INT);
        $stmt->bindValue(':estado', $reserva->obtenerEstado(), PDO::PARAM_STR);
        $stmt->bindValue(':expira_en', $reserva->obtenerExpiraEn(), $reserva->obtenerExpiraEn() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':canal', $reserva->obtenerCanal(), PDO::PARAM_STR);
        $stmt->bindValue(':origen', $reserva->obtenerOrigen(), PDO::PARAM_STR);
        $stmt->bindValue(':moneda_codigo', $reserva->obtenerMonedaCodigo(), PDO::PARAM_STR);
        $stmt->bindValue(':subtotal', $reserva->obtenerSubtotal(), PDO::PARAM_STR);
        $stmt->bindValue(':impuesto_total', $reserva->obtenerImpuestoTotal(), PDO::PARAM_STR);
        $stmt->bindValue(':total', $reserva->obtenerTotal(), PDO::PARAM_STR);
        $stmt->bindValue(':observaciones', $reserva->obtenerObservaciones(), $reserva->obtenerObservaciones() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':confirmada_en', $reserva->obtenerConfirmadaEn(), $reserva->obtenerConfirmadaEn() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':confirmada_por_actor_id', $reserva->obtenerConfirmadaPorActorId(), $reserva->obtenerConfirmadaPorActorId() !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':creado_por_actor_id', $reserva->obtenerCreadoPorActorId(), $reserva->obtenerCreadoPorActorId() !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->execute();

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Inserta una unidad asignada a la reserva (snapshot económico).
     */
    public function guardarUnidad(ReservaUnidad $unidad): int
    {
        $sql = 'INSERT INTO reserva_unidades (
                    reserva_id, unidad_id, precio_unitario_noche, noches,
                    subtotal, impuesto, total, moneda_codigo, creado_en
                ) VALUES (
                    :reserva_id, :unidad_id, :precio_unitario_noche, :noches,
                    :subtotal, :impuesto, :total, :moneda_codigo, NOW()
                )';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':reserva_id', $unidad->obtenerReservaId(), PDO::PARAM_INT);
        $stmt->bindValue(':unidad_id', $unidad->obtenerUnidadId(), PDO::PARAM_INT);
        $stmt->bindValue(':precio_unitario_noche', $unidad->obtenerPrecioUnitarioNoche(), PDO::PARAM_STR);
        $stmt->bindValue(':noches', $unidad->obtenerNoches(), PDO::PARAM_INT);
        $stmt->bindValue(':subtotal', $unidad->obtenerSubtotal(), PDO::PARAM_STR);
        $stmt->bindValue(':impuesto', $unidad->obtenerImpuesto(), PDO::PARAM_STR);
        $stmt->bindValue(':total', $unidad->obtenerTotal(), PDO::PARAM_STR);
        $stmt->bindValue(':moneda_codigo', $unidad->obtenerMonedaCodigo(), PDO::PARAM_STR);
        $stmt->execute();

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Obtiene el listado de unidades asignadas a una reserva, con nombres y datos de inmueble.
     *
     * @return array<ReservaUnidad>
     */
    public function obtenerUnidadesPorReservaId(int $reservaId): array
    {
        $sql = 'SELECT ru.*,
                       u.codigo AS unidad_codigo,
                       u.nombre AS unidad_nombre,
                       u.propiedad_id,
                       p.nombre AS propiedad_nombre,
                       tu.nombre AS tipo_unidad_nombre
                FROM reserva_unidades ru
                INNER JOIN unidades u ON ru.unidad_id = u.id
                INNER JOIN propiedades p ON u.propiedad_id = p.id
                LEFT JOIN tipos_unidad tu ON u.tipo_unidad_id = tu.id
                WHERE ru.reserva_id = :reserva_id
                ORDER BY ru.id ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':reserva_id', $reservaId, PDO::PARAM_INT);
        $stmt->execute();

        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $resultado = [];
        foreach ($filas as $fila) {
            $resultado[] = ReservaUnidad::desdeArreglo($fila);
        }

        return $resultado;
    }

    /**
     * Actualiza el estado de una reserva y sus campos de auditoría / transición.
     *
     * @param array<string, mixed> $datosTransicion
     */
    public function cambiarEstado(int $id, string $nuevoEstado, array $datosTransicion = []): bool
    {
        $setClauses = ['estado = :estado'];
        $params = [
            ':id' => $id,
            ':estado' => $nuevoEstado,
        ];

        if ($nuevoEstado === Reserva::ESTADO_CONFIRMADA) {
            $setClauses[] = 'confirmada_en = NOW()';
            if (isset($datosTransicion['actor_id'])) {
                $setClauses[] = 'confirmada_por_actor_id = :actor_id';
                $params[':actor_id'] = (int) $datosTransicion['actor_id'];
            }
        } elseif ($nuevoEstado === Reserva::ESTADO_CANCELADA) {
            $setClauses[] = 'cancelada_en = NOW()';
            if (isset($datosTransicion['actor_id'])) {
                $setClauses[] = 'cancelada_por_actor_id = :actor_id';
                $params[':actor_id'] = (int) $datosTransicion['actor_id'];
            }
            if (isset($datosTransicion['motivo'])) {
                $setClauses[] = 'motivo_cancelacion = :motivo';
                $params[':motivo'] = (string) $datosTransicion['motivo'];
            }
        } elseif ($nuevoEstado === Reserva::ESTADO_EXPIRADA) {
            $setClauses[] = 'cancelada_en = NOW()';
            $setClauses[] = 'motivo_cancelacion = :motivo';
            $params[':motivo'] = (string) ($datosTransicion['motivo'] ?? 'Hold de reserva pendiente expirado');
        }

        $sql = 'UPDATE reservas SET ' . implode(', ', $setClauses) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $param => $valor) {
            $stmt->bindValue($param, $valor, is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }

        return $stmt->execute();
    }

    /**
     * Lista reservas según filtros opcionales y paginación.
     *
     * @param array<string, mixed> $filtros
     * @return array<Reserva>
     */
    public function listar(array $filtros = [], int $limite = 20, int $offset = 0): array
    {
        [$where, $params] = $this->construirWhereFiltros($filtros);

        $sql = "SELECT r.*,
                       TRIM(CONCAT(COALESCE(p.nombres, ''), ' ', COALESCE(p.apellido_paterno, ''), ' ', COALESCE(p.apellido_materno, ''))) AS titular_nombre_completo,
                       act_c.nombre AS creador_nombre,
                       act_conf.nombre AS confirmador_nombre,
                       act_canc.nombre AS cancelador_nombre
                FROM reservas r
                INNER JOIN personas p ON r.persona_titular_id = p.id
                LEFT JOIN actores act_c ON r.creado_por_actor_id = act_c.id
                LEFT JOIN actores act_conf ON r.confirmada_por_actor_id = act_conf.id
                LEFT JOIN actores act_canc ON r.cancelada_por_actor_id = act_canc.id
                WHERE {$where}
                ORDER BY r.id DESC
                LIMIT :limite OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limite', max(1, $limite), PDO::PARAM_INT);
        $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();

        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $reservas = [];
        foreach ($filas as $fila) {
            $reserva = Reserva::desdeArreglo($fila);
            $this->cargarContactoTitular($reserva);
            $unidades = $this->obtenerUnidadesPorReservaId((int) $fila['id']);
            $reserva->asignarUnidades($unidades);
            $reservas[] = $reserva;
        }

        return $reservas;
    }

    /**
     * Cuenta el total de reservas según los filtros proporcionados.
     *
     * @param array<string, mixed> $filtros
     */
    public function contar(array $filtros = []): int
    {
        [$where, $params] = $this->construirWhereFiltros($filtros);

        $sql = "SELECT COUNT(DISTINCT r.id)
                FROM reservas r
                INNER JOIN personas p ON r.persona_titular_id = p.id
                WHERE {$where}";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * Obtiene todas las reservas PENDIENTES que han expirado según el instante dado.
     *
     * @return array<Reserva>
     */
    public function obtenerPendientesExpiradas(string $ahora): array
    {
        $sql = 'SELECT r.*,
                       TRIM(CONCAT(COALESCE(p.nombres, ""), " ", COALESCE(p.apellido_paterno, ""), " ", COALESCE(p.apellido_materno, ""))) AS titular_nombre_completo
                FROM reservas r
                INNER JOIN personas p ON r.persona_titular_id = p.id
                WHERE r.estado = "PENDIENTE"
                  AND r.expira_en IS NOT NULL
                  AND r.expira_en <= :ahora
                ORDER BY r.id ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':ahora', $ahora, PDO::PARAM_STR);
        $stmt->execute();

        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $reservas = [];
        foreach ($filas as $fila) {
            $reserva = Reserva::desdeArreglo($fila);
            $unidades = $this->obtenerUnidadesPorReservaId((int) $fila['id']);
            $reserva->asignarUnidades($unidades);
            $reservas[] = $reserva;
        }

        return $reservas;
    }

    /**
     * Comprueba si un código comercial ya está en uso.
     */
    public function existeCodigo(string $codigo, ?int $excluirId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM reservas WHERE codigo = :codigo';
        if ($excluirId !== null) {
            $sql .= ' AND id <> :excluir_id';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':codigo', trim(strtoupper($codigo)), PDO::PARAM_STR);
        if ($excluirId !== null) {
            $stmt->bindValue(':excluir_id', $excluirId, PDO::PARAM_INT);
        }
        $stmt->execute();

        return ((int) $stmt->fetchColumn()) > 0;
    }

    /**
     * Genera un código comercial único para la reserva en formato 'RES-YYYYMMDD-XXXX'.
     */
    public function generarSiguienteCodigo(): string
    {
        $prefijo = 'RES-' . date('Ymd') . '-';
        do {
            $aleatorio = strtoupper(bin2hex(random_bytes(2)));
            $codigo = $prefijo . $aleatorio;
        } while ($this->existeCodigo($codigo));

        return $codigo;
    }

    /**
     * Carga el primer documento y teléfono/correo de la persona titular.
     */
    private function cargarContactoTitular(Reserva $reserva): void
    {
        $titularId = $reserva->obtenerPersonaTitularId();
        if ($titularId <= 0) {
            return;
        }

        // Obtener documento principal
        $sqlDoc = 'SELECT d.numero_documento, td.codigo AS tipo_codigo
                   FROM personas_documentos d
                   INNER JOIN tipos_documento td ON d.tipo_documento_id = td.id
                   WHERE d.persona_id = :persona_id AND d.estado = "ACTIVO"
                   ORDER BY d.es_principal DESC, d.id ASC LIMIT 1';
        $stmtDoc = $this->pdo->prepare($sqlDoc);
        $stmtDoc->bindValue(':persona_id', $titularId, PDO::PARAM_INT);
        $stmtDoc->execute();
        $doc = $stmtDoc->fetch(PDO::FETCH_ASSOC);

        // Obtener teléfono y email
        $sqlCont = 'SELECT valor, tipo_contacto AS tipo FROM personas_contactos
                    WHERE persona_id = :persona_id AND estado = "ACTIVO"
                    ORDER BY es_principal DESC, id ASC';
        $stmtCont = $this->pdo->prepare($sqlCont);
        $stmtCont->bindValue(':persona_id', $titularId, PDO::PARAM_INT);
        $stmtCont->execute();
        $contactos = $stmtCont->fetchAll(PDO::FETCH_ASSOC);

        $telefono = null;
        $email = null;
        foreach ($contactos as $c) {
            $tipo = strtoupper((string) ($c['tipo'] ?? ''));
            if ($telefono === null && in_array($tipo, ['TELEFONO', 'CELULAR', 'WHATSAPP'], true)) {
                $telefono = (string) $c['valor'];
            }
            if ($email === null && $tipo === 'EMAIL') {
                $email = (string) $c['valor'];
            }
        }

        $reserva->asignarMetadatosTitular(
            $reserva->obtenerTitularNombreCompleto(),
            $doc ? (string) $doc['numero_documento'] : null,
            $doc ? (string) $doc['tipo_codigo'] : null,
            $telefono,
            $email
        );
    }

    /**
     * Construye cláusula WHERE y parámetros dinámicos para listados.
     *
     * @param array<string, mixed> $filtros
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function construirWhereFiltros(array $filtros): array
    {
        $condiciones = ['1=1'];
        $params = [];

        if (!empty($filtros['estado'])) {
            if (is_array($filtros['estado'])) {
                $placeholders = [];
                foreach ($filtros['estado'] as $i => $est) {
                    $ph = ":estado_{$i}";
                    $placeholders[] = $ph;
                    $params[$ph] = strtoupper(trim((string) $est));
                }
                $condiciones[] = 'r.estado IN (' . implode(',', $placeholders) . ')';
            } else {
                $condiciones[] = 'r.estado = :estado';
                $params[':estado'] = strtoupper(trim((string) $filtros['estado']));
            }
        }

        if (!empty($filtros['canal'])) {
            $condiciones[] = 'r.canal = :canal';
            $params[':canal'] = strtoupper(trim((string) $filtros['canal']));
        }

        if (!empty($filtros['persona_titular_id'])) {
            $condiciones[] = 'r.persona_titular_id = :titular_id';
            $params[':titular_id'] = (int) $filtros['persona_titular_id'];
        }

        if (!empty($filtros['fecha_desde'])) {
            $condiciones[] = 'r.fecha_salida >= :fecha_desde';
            $params[':fecha_desde'] = trim((string) $filtros['fecha_desde']);
        }

        if (!empty($filtros['fecha_hasta'])) {
            $condiciones[] = 'r.fecha_entrada <= :fecha_hasta';
            $params[':fecha_hasta'] = trim((string) $filtros['fecha_hasta']);
        }

        if (!empty($filtros['propiedad_id'])) {
            $condiciones[] = 'EXISTS (
                SELECT 1 FROM reserva_unidades ru_prop
                INNER JOIN unidades u_prop ON ru_prop.unidad_id = u_prop.id
                WHERE ru_prop.reserva_id = r.id AND u_prop.propiedad_id = :propiedad_id
            )';
            $params[':propiedad_id'] = (int) $filtros['propiedad_id'];
        }

        if (!empty($filtros['unidad_id'])) {
            $condiciones[] = 'EXISTS (
                SELECT 1 FROM reserva_unidades ru_u
                WHERE ru_u.reserva_id = r.id AND ru_u.unidad_id = :unidad_id
            )';
            $params[':unidad_id'] = (int) $filtros['unidad_id'];
        }

        if (!empty($filtros['busqueda'])) {
            $condiciones[] = '(
                r.codigo LIKE :busqueda OR
                p.nombres LIKE :busqueda OR
                p.apellido_paterno LIKE :busqueda OR
                p.apellido_materno LIKE :busqueda
            )';
            $params[':busqueda'] = '%' . trim((string) $filtros['busqueda']) . '%';
        }

        return [implode(' AND ', $condiciones), $params];
    }
}
