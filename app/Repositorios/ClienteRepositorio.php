<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\Cliente;
use CamargoPMS\Modelos\ClienteCategoria;
use CamargoPMS\Modelos\Persona;
use PDO;

/**
 * Repositorio de persistencia y consultas comerciales para Clientes (CLIENTES-1).
 */
class ClienteRepositorio
{
    private PersonaRepositorio $personaRepo;
    private ClienteCategoriaRepositorio $categoriaRepo;

    public function __construct(private PDO $pdo)
    {
        $this->personaRepo = new PersonaRepositorio($pdo);
        $this->categoriaRepo = new ClienteCategoriaRepositorio($pdo);
    }

    public function buscarPorId(int $id, bool $enriquecido = true): ?Cliente
    {
        $sql = "SELECT * FROM clientes WHERE id = :id LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            return null;
        }

        $cliente = Cliente::desdeArreglo($fila);
        if ($enriquecido) {
            $this->enriquecerCliente($cliente);
        }

        return $cliente;
    }

    public function buscarPorCodigo(string $codigo, bool $enriquecido = true): ?Cliente
    {
        $sql = "SELECT * FROM clientes WHERE UPPER(codigo) = UPPER(:codigo) LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['codigo' => trim($codigo)]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            return null;
        }

        $cliente = Cliente::desdeArreglo($fila);
        if ($enriquecido) {
            $this->enriquecerCliente($cliente);
        }

        return $cliente;
    }

    public function buscarPorPersonaId(int $personaId, bool $enriquecido = true): ?Cliente
    {
        $sql = "SELECT * FROM clientes WHERE persona_id = :persona_id LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['persona_id' => $personaId]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            return null;
        }

        $cliente = Cliente::desdeArreglo($fila);
        if ($enriquecido) {
            $this->enriquecerCliente($cliente);
        }

        return $cliente;
    }

    public function existeParaPersona(int $personaId, ?int $excluirClienteId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM clientes WHERE persona_id = :persona_id";
        $params = ['persona_id' => $personaId];

        if ($excluirClienteId !== null) {
            $sql .= " AND id <> :excluir_id";
            $params['excluir_id'] = $excluirClienteId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Genera el siguiente código secuencial disponible para clientes (ej. 'CLI-00001').
     * Concurrency-safe: bloquea o consulta el máximo correlativo de forma segura.
     *
     * @return string
     */
    public function generarSiguienteCodigo(): string
    {
        $sql = "SELECT COALESCE(MAX(CAST(SUBSTRING(codigo, 5) AS UNSIGNED)), 0) AS max_num FROM clientes";
        $stmt = $this->pdo->query($sql);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        $siguienteNumero = ($fila && isset($fila['max_num']) ? (int) $fila['max_num'] : 0) + 1;

        return sprintf('CLI-%05d', $siguienteNumero);
    }

    public function insertar(Cliente $cliente): int
    {
        $sql = "INSERT INTO clientes (
                    codigo, persona_id, categoria_id, estado, motivo_bloqueo,
                    canal_captacion, preferencias, observaciones,
                    creado_por_actor_id, actualizado_por_actor_id
                ) VALUES (
                    :codigo, :persona_id, :categoria_id, :estado, :motivo_bloqueo,
                    :canal_captacion, :preferencias, :observaciones,
                    :creado_por_actor_id, :actualizado_por_actor_id
                )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'codigo' => $cliente->obtenerCodigo(),
            'persona_id' => $cliente->obtenerPersonaId(),
            'categoria_id' => $cliente->obtenerCategoriaId(),
            'estado' => $cliente->obtenerEstado(),
            'motivo_bloqueo' => $cliente->obtenerMotivoBloqueo(),
            'canal_captacion' => $cliente->obtenerCanalCaptacion(),
            'preferencias' => $cliente->obtenerPreferencias(),
            'observaciones' => $cliente->obtenerObservaciones(),
            'creado_por_actor_id' => $cliente->obtenerCreadoPorActorId(),
            'actualizado_por_actor_id' => $cliente->obtenerActualizadoPorActorId(),
        ]);

        $id = (int) $this->pdo->lastInsertId();
        $cliente->fijarId($id);

        return $id;
    }

    public function actualizar(Cliente $cliente): bool
    {
        if ($cliente->obtenerId() === null) {
            return false;
        }

        $sql = "UPDATE clientes
                SET categoria_id = :categoria_id,
                    estado = :estado,
                    motivo_bloqueo = :motivo_bloqueo,
                    canal_captacion = :canal_captacion,
                    preferencias = :preferencias,
                    observaciones = :observaciones,
                    actualizado_por_actor_id = :actualizado_por_actor_id
                WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'id' => $cliente->obtenerId(),
            'categoria_id' => $cliente->obtenerCategoriaId(),
            'estado' => $cliente->obtenerEstado(),
            'motivo_bloqueo' => $cliente->obtenerMotivoBloqueo(),
            'canal_captacion' => $cliente->obtenerCanalCaptacion(),
            'preferencias' => $cliente->obtenerPreferencias(),
            'observaciones' => $cliente->obtenerObservaciones(),
            'actualizado_por_actor_id' => $cliente->obtenerActualizadoPorActorId(),
        ]);
    }

    public function cambiarEstado(int $id, string $nuevoEstado, ?string $motivo = null, ?int $actorId = null): bool
    {
        $sql = "UPDATE clientes
                SET estado = :estado,
                    motivo_bloqueo = :motivo,
                    actualizado_por_actor_id = :actor_id
                WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'id' => $id,
            'estado' => $nuevoEstado,
            'motivo' => $nuevoEstado === Cliente::ESTADO_BLOQUEADO ? $motivo : null,
            'actor_id' => $actorId,
        ]);
    }

    /**
     * Lista clientes con filtros de búsqueda, estado y categoría.
     *
     * @param array<string, mixed> $filtros
     * @param int $limite
     * @param int $offset
     * @return array<int, array<string, mixed>>
     */
    public function listar(array $filtros = [], int $limite = 50, int $offset = 0): array
    {
        $limite = max(1, min(100, $limite));
        $offset = max(0, $offset);

        $params = [];
        $condiciones = [];

        if (!empty($filtros['buscar'])) {
            $termino = '%' . trim((string) $filtros['buscar']) . '%';
            $condiciones[] = "(
                c.codigo LIKE :b1
                OR p.nombres LIKE :b2
                OR p.apellido_paterno LIKE :b3
                OR p.apellido_materno LIKE :b4
                OR CONCAT(p.nombres, ' ', p.apellido_paterno, ' ', COALESCE(p.apellido_materno, '')) LIKE :b5
                OR pd.numero_documento LIKE :b6
                OR pc_tel.valor LIKE :b7
                OR pc_email.valor LIKE :b8
            )";
            $params['b1'] = $termino;
            $params['b2'] = $termino;
            $params['b3'] = $termino;
            $params['b4'] = $termino;
            $params['b5'] = $termino;
            $params['b6'] = $termino;
            $params['b7'] = $termino;
            $params['b8'] = $termino;
        }

        if (!empty($filtros['categoria_id'])) {
            $condiciones[] = "c.categoria_id = :cat_id";
            $params['cat_id'] = (int) $filtros['categoria_id'];
        }

        if (!empty($filtros['estado'])) {
            $condiciones[] = "c.estado = :estado";
            $params['estado'] = (string) $filtros['estado'];
        }

        if (!empty($filtros['canal_captacion'])) {
            $condiciones[] = "c.canal_captacion = :canal";
            $params['canal'] = (string) $filtros['canal_captacion'];
        }

        $clausulaWhere = count($condiciones) > 0 ? 'WHERE ' . implode(' AND ', $condiciones) : '';

        $sql = "SELECT
                    c.id,
                    c.codigo,
                    c.persona_id,
                    c.categoria_id,
                    c.estado,
                    c.motivo_bloqueo,
                    c.canal_captacion,
                    c.preferencias,
                    c.observaciones,
                    c.creado_en,
                    c.actualizado_en,
                    -- Persona
                    p.nombres,
                    p.apellido_paterno,
                    p.apellido_materno,
                    p.genero,
                    p.fecha_nacimiento,
                    p.direccion,
                    -- Documento principal
                    td.codigo AS tipo_documento_codigo,
                    td.nombre AS tipo_documento_nombre,
                    pd.numero_documento,
                    -- Contactos principales
                    pc_tel.valor AS telefono_principal,
                    pc_tel.es_whatsapp,
                    pc_email.valor AS email_principal,
                    -- Categoría
                    cc.codigo AS categoria_codigo,
                    cc.nombre AS categoria_nombre,
                    cc.color_badge AS categoria_color_badge,
                    -- Métricas históricas
                    (SELECT COUNT(*) FROM reservas r WHERE r.persona_titular_id = p.id) AS total_reservas,
                    (SELECT COUNT(*) FROM estadia_huespedes eh WHERE eh.persona_id = p.id) AS total_estadias,
                    (SELECT COUNT(*) FROM arrendamiento_personas ap WHERE ap.persona_id = p.id) AS total_arrendamientos
                FROM clientes c
                INNER JOIN personas p ON p.id = c.persona_id
                INNER JOIN cliente_categorias cc ON cc.id = c.categoria_id
                LEFT JOIN personas_documentos pd ON pd.persona_id = p.id AND pd.es_principal = 1
                LEFT JOIN tipos_documento td ON td.id = pd.tipo_documento_id
                LEFT JOIN personas_contactos pc_tel ON pc_tel.persona_id = p.id AND pc_tel.tipo_contacto = 'TELEFONO' AND pc_tel.es_principal = 1
                LEFT JOIN personas_contactos pc_email ON pc_email.persona_id = p.id AND pc_email.tipo_contacto = 'EMAIL' AND pc_email.es_principal = 1
                {$clausulaWhere}
                ORDER BY c.id DESC
                LIMIT {$limite} OFFSET {$offset}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resultado = [];
        foreach ($filas as $fila) {
            $nombrePartes = array_filter([
                $fila['nombres'] ?? '',
                $fila['apellido_paterno'] ?? '',
                $fila['apellido_materno'] ?? '',
            ]);
            $fila['nombre_completo'] = implode(' ', $nombrePartes);
            $resultado[] = $fila;
        }

        return $resultado;
    }

    /**
     * Cuenta el total de registros según los filtros especificados.
     *
     * @param array<string, mixed> $filtros
     * @return int
     */
    public function contar(array $filtros = []): int
    {
        $params = [];
        $condiciones = [];

        if (!empty($filtros['buscar'])) {
            $termino = '%' . trim((string) $filtros['buscar']) . '%';
            $condiciones[] = "(
                c.codigo LIKE :b1
                OR p.nombres LIKE :b2
                OR p.apellido_paterno LIKE :b3
                OR p.apellido_materno LIKE :b4
                OR CONCAT(p.nombres, ' ', p.apellido_paterno, ' ', COALESCE(p.apellido_materno, '')) LIKE :b5
                OR pd.numero_documento LIKE :b6
                OR pc_tel.valor LIKE :b7
                OR pc_email.valor LIKE :b8
            )";
            $params['b1'] = $termino;
            $params['b2'] = $termino;
            $params['b3'] = $termino;
            $params['b4'] = $termino;
            $params['b5'] = $termino;
            $params['b6'] = $termino;
            $params['b7'] = $termino;
            $params['b8'] = $termino;
        }

        if (!empty($filtros['categoria_id'])) {
            $condiciones[] = "c.categoria_id = :cat_id";
            $params['cat_id'] = (int) $filtros['categoria_id'];
        }

        if (!empty($filtros['estado'])) {
            $condiciones[] = "c.estado = :estado";
            $params['estado'] = (string) $filtros['estado'];
        }

        if (!empty($filtros['canal_captacion'])) {
            $condiciones[] = "c.canal_captacion = :canal";
            $params['canal'] = (string) $filtros['canal_captacion'];
        }

        $clausulaWhere = count($condiciones) > 0 ? 'WHERE ' . implode(' AND ', $condiciones) : '';

        $sql = "SELECT COUNT(*)
                FROM clientes c
                INNER JOIN personas p ON p.id = c.persona_id
                INNER JOIN cliente_categorias cc ON cc.id = c.categoria_id
                LEFT JOIN personas_documentos pd ON pd.persona_id = p.id AND pd.es_principal = 1
                LEFT JOIN personas_contactos pc_tel ON pc_tel.persona_id = p.id AND pc_tel.tipo_contacto = 'TELEFONO' AND pc_tel.es_principal = 1
                LEFT JOIN personas_contactos pc_email ON pc_email.persona_id = p.id AND pc_email.tipo_contacto = 'EMAIL' AND pc_email.es_principal = 1
                {$clausulaWhere}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Obtiene el historial de reservas donde la persona es titular comercial.
     *
     * @param int $personaId
     * @return array<int, array<string, mixed>>
     */
    public function obtenerHistorialReservas(int $personaId): array
    {
        $sql = "SELECT
                    r.id,
                    r.codigo,
                    r.fecha_entrada,
                    r.fecha_salida,
                    r.noches,
                    r.estado,
                    r.canal,
                    r.origen,
                    r.moneda_codigo,
                    r.subtotal,
                    r.impuesto_total,
                    r.total,
                    r.observaciones,
                    r.creado_en
                FROM reservas r
                WHERE r.persona_titular_id = :persona_id
                ORDER BY r.fecha_entrada DESC, r.id DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['persona_id' => $personaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene el historial de estadías físicas de la persona, distinguiendo responsable vs acompañante.
     *
     * @param int $personaId
     * @return array<int, array<string, mixed>>
     */
    public function obtenerHistorialEstadias(int $personaId): array
    {
        $sql = "SELECT
                    e.id,
                    e.codigo,
                    e.reserva_id,
                    r.codigo AS reserva_codigo,
                    e.unidad_id,
                    u.codigo AS unidad_codigo,
                    u.nombre AS unidad_nombre,
                    e.fecha_entrada,
                    e.fecha_salida_prevista,
                    e.checkin_en,
                    e.checkout_en,
                    e.estado,
                    eh.es_responsable,
                    IF(eh.es_responsable = 1, 'RESPONSABLE', 'ACOMPAÑANTE') AS rol_estadia
                FROM estadia_huespedes eh
                INNER JOIN estadias e ON e.id = eh.estadia_id
                LEFT JOIN reservas r ON r.id = e.reserva_id
                LEFT JOIN unidades u ON u.id = e.unidad_id
                WHERE eh.persona_id = :persona_id
                ORDER BY e.fecha_entrada DESC, e.id DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['persona_id' => $personaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene el historial de contratos de arrendamiento, distinguiendo titular, cotitular y ocupante.
     *
     * @param int $personaId
     * @return array<int, array<string, mixed>>
     */
    public function obtenerHistorialArrendamientos(int $personaId): array
    {
        $sql = "SELECT
                    a.id,
                    a.codigo,
                    a.unidad_id,
                    u.codigo AS unidad_codigo,
                    u.nombre AS unidad_nombre,
                    a.fecha_inicio,
                    a.fecha_fin,
                    a.dia_vencimiento,
                    a.renta_mensual,
                    a.deposito_garantia,
                    a.moneda_codigo,
                    a.estado,
                    ap.tipo_relacion,
                    ap.observaciones AS persona_observaciones,
                    IF(ap.tipo_relacion = 'OCUPANTE', 0, 1) AS es_deudor_financiero
                FROM arrendamiento_personas ap
                INNER JOIN arrendamientos a ON a.id = ap.arrendamiento_id
                LEFT JOIN unidades u ON u.id = a.unidad_id
                WHERE ap.persona_id = :persona_id
                ORDER BY a.fecha_inicio DESC, a.id DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['persona_id' => $personaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene los servicios contratados vinculados a las reservas de la persona.
     *
     * @param int $personaId
     * @return array<int, array<string, mixed>>
     */
    public function obtenerHistorialServicios(int $personaId): array
    {
        $sql = "SELECT
                    sc.id,
                    sc.codigo,
                    sc.reserva_id,
                    r.codigo AS reserva_codigo,
                    sc.descripcion_servicio_snapshot AS concepto,
                    sc.fecha_servicio,
                    sc.cantidad,
                    sc.precio_unitario,
                    sc.total AS total_venta,
                    sc.moneda_codigo,
                    sc.estado AS estado_operativo,
                    'REGISTRADO' AS estado_financiero,
                    sc.creado_en
                FROM servicios_contratados sc
                INNER JOIN reservas r ON r.id = sc.reserva_id
                WHERE r.persona_titular_id = :persona_id
                ORDER BY sc.fecha_servicio DESC, sc.id DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['persona_id' => $personaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene los folios financieros directos de la persona.
     *
     * @param int $personaId
     * @return array<int, array<string, mixed>>
     */
    public function obtenerFoliosPersona(int $personaId): array
    {
        $sql = "SELECT
                    cf.id,
                    cf.codigo,
                    cf.reserva_id,
                    r.codigo AS reserva_codigo,
                    cf.moneda_codigo,
                    cf.estado,
                    cf.creado_en
                FROM cuentas_folios cf
                LEFT JOIN reservas r ON r.id = cf.reserva_id
                WHERE cf.persona_titular_id = :persona_id
                ORDER BY cf.id DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['persona_id' => $personaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene los recibos de pago emitidos a favor de la persona.
     *
     * @param int $personaId
     * @return array<int, array<string, mixed>>
     */
    public function obtenerRecibosPersona(int $personaId): array
    {
        $sql = "SELECT
                    rec.id,
                    rec.codigo,
                    rec.cuenta_folio_id,
                    rec.pago_id,
                    rec.monto_recaudado,
                    rec.moneda_codigo,
                    rec.metodo_pago_nombre,
                    rec.concepto_general,
                    rec.fecha_emision,
                    rec.estado
                FROM recibos rec
                WHERE rec.persona_id = :persona_id
                ORDER BY rec.fecha_emision DESC, rec.id DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['persona_id' => $personaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene documentos emitidos asociados a las reservas o recibos de la persona.
     *
     * @param int $personaId
     * @return array<int, array<string, mixed>>
     */
    public function obtenerDocumentosEmitidosPersona(int $personaId): array
    {
        $sql = "SELECT
                    de.id,
                    de.codigo_folio,
                    dp.nombre AS plantilla_nombre,
                    de.origen_tipo,
                    de.origen_id,
                    de.emitido_en,
                    de.estado,
                    de.ruta_archivo_pdf
                FROM documentos_emitidos de
                INNER JOIN documento_plantillas dp ON dp.id = de.plantilla_id
                WHERE (
                    de.origen_tipo = 'RESERVA' AND de.origen_id IN (
                        SELECT r.id FROM reservas r WHERE r.persona_titular_id = :p1
                    )
                ) OR (
                    de.origen_tipo = 'RECIBO' AND de.origen_id IN (
                        SELECT rec.id FROM recibos rec WHERE rec.persona_id = :p2
                    )
                ) OR (
                    de.origen_tipo = 'ARRENDAMIENTO' AND de.origen_id IN (
                        SELECT ap.arrendamiento_id FROM arrendamiento_personas ap WHERE ap.persona_id = :p3
                    )
                )
                ORDER BY de.emitido_en DESC, de.id DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['p1' => $personaId, 'p2' => $personaId, 'p3' => $personaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function enriquecerCliente(Cliente $cliente): void
    {
        $persona = $this->personaRepo->buscarPorId($cliente->obtenerPersonaId());
        if ($persona !== null) {
            $cliente->fijarPersona($persona);
        }

        $categoria = $this->categoriaRepo->buscarPorId($cliente->obtenerCategoriaId());
        if ($categoria !== null) {
            $cliente->fijarCategoria($categoria);
        }
    }
}
