<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\Persona;
use CamargoPMS\Modelos\Reclamacion;
use PDO;

/**
 * Repositorio de persistencia y consultas del Libro de Reclamaciones (RECLAMACIONES-1).
 */
class ReclamacionRepositorio
{
    private ReclamacionActuacionRepositorio $actuacionRepo;
    private PersonaRepositorio $personaRepo;

    public function __construct(private PDO $pdo)
    {
        $this->actuacionRepo = new ReclamacionActuacionRepositorio($pdo);
        $this->personaRepo = new PersonaRepositorio($pdo);
    }

    public function buscarPorId(int $id, bool $enriquecido = true): ?Reclamacion
    {
        $stmt = $this->pdo->prepare('SELECT * FROM reclamaciones WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            return null;
        }

        $reclamacion = Reclamacion::desdeArreglo($fila);
        if ($enriquecido) {
            $this->enriquecer($reclamacion);
        }

        return $reclamacion;
    }

    public function buscarPorCodigoInterno(string $codigoInterno, bool $enriquecido = true): ?Reclamacion
    {
        $stmt = $this->pdo->prepare('SELECT * FROM reclamaciones WHERE UPPER(codigo_interno) = UPPER(:codigo) LIMIT 1');
        $stmt->execute(['codigo' => trim($codigoInterno)]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            return null;
        }

        $reclamacion = Reclamacion::desdeArreglo($fila);
        if ($enriquecido) {
            $this->enriquecer($reclamacion);
        }

        return $reclamacion;
    }

    public function buscarPorCodigoHoja(string $codigoHoja, bool $enriquecido = true): ?Reclamacion
    {
        $stmt = $this->pdo->prepare('SELECT * FROM reclamaciones WHERE UPPER(codigo_hoja) = UPPER(:codigo) LIMIT 1');
        $stmt->execute(['codigo' => trim($codigoHoja)]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            return null;
        }

        $reclamacion = Reclamacion::desdeArreglo($fila);
        if ($enriquecido) {
            $this->enriquecer($reclamacion);
        }

        return $reclamacion;
    }

    public function guardar(Reclamacion $reclamacion): Reclamacion
    {
        $snapshotConsumidorJson = json_encode($reclamacion->obtenerSnapshotConsumidor(), JSON_UNESCAPED_UNICODE);
        $snapshotProveedorJson = json_encode($reclamacion->obtenerSnapshotProveedor(), JSON_UNESCAPED_UNICODE);

        if ($reclamacion->obtenerId() !== null) {
            $sql = 'UPDATE reclamaciones SET
                        codigo_hoja = :codigo_hoja,
                        codigo_interno = :codigo_interno,
                        empresa_id = :empresa_id,
                        propiedad_id = :propiedad_id,
                        consumidor_persona_id = :consumidor_persona_id,
                        es_menor_edad = :es_menor_edad,
                        apoderado_persona_id = :apoderado_persona_id,
                        tipo = :tipo,
                        tipo_bien = :tipo_bien,
                        monto_reclamado = :monto_reclamado,
                        moneda = :moneda,
                        descripcion_bien = :descripcion_bien,
                        detalle_reclamacion = :detalle_reclamacion,
                        pedido_consumidor = :pedido_consumidor,
                        canal_entrada = :canal_entrada,
                        estado = :estado,
                        fecha_interposicion = :fecha_interposicion,
                        fecha_limite_legal = :fecha_limite_legal,
                        dias_habiles_consumidos = :dias_habiles_consumidos,
                        fecha_suspension = :fecha_suspension,
                        fecha_limite_ofrecimiento = :fecha_limite_ofrecimiento,
                        documento_emitido_id = :documento_emitido_id,
                        motivo_anulacion = :motivo_anulacion,
                        anulado_por_actor_id = :anulado_por_actor_id,
                        anulado_en = :anulado_en,
                        snapshot_consumidor_json = :snapshot_consumidor_json,
                        snapshot_proveedor_json = :snapshot_proveedor_json,
                        actualizado_por_actor_id = :actualizado_por_actor_id,
                        actualizado_en = NOW()
                    WHERE id = :id';

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'id' => $reclamacion->obtenerId(),
                'codigo_hoja' => $reclamacion->obtenerCodigoHoja(),
                'codigo_interno' => $reclamacion->obtenerCodigoInterno(),
                'empresa_id' => $reclamacion->obtenerEmpresaId(),
                'propiedad_id' => $reclamacion->obtenerPropiedadId(),
                'consumidor_persona_id' => $reclamacion->obtenerConsumidorPersonaId(),
                'es_menor_edad' => $reclamacion->esMenorEdad() ? 1 : 0,
                'apoderado_persona_id' => $reclamacion->obtenerApoderadoPersonaId(),
                'tipo' => $reclamacion->obtenerTipo(),
                'tipo_bien' => $reclamacion->obtenerTipoBien(),
                'monto_reclamado' => $reclamacion->obtenerMontoReclamado(),
                'moneda' => $reclamacion->obtenerMoneda(),
                'descripcion_bien' => $reclamacion->obtenerDescripcionBien(),
                'detalle_reclamacion' => $reclamacion->obtenerDetalleReclamacion(),
                'pedido_consumidor' => $reclamacion->obtenerPedidoConsumidor(),
                'canal_entrada' => $reclamacion->obtenerCanalEntrada(),
                'estado' => $reclamacion->obtenerEstado(),
                'fecha_interposicion' => $reclamacion->obtenerFechaInterposicion(),
                'fecha_limite_legal' => $reclamacion->obtenerFechaLimiteLegal(),
                'dias_habiles_consumidos' => $reclamacion->obtenerDiasHabilesConsumidos(),
                'fecha_suspension' => $reclamacion->obtenerFechaSuspension(),
                'fecha_limite_ofrecimiento' => $reclamacion->obtenerFechaLimiteOfrecimiento(),
                'documento_emitido_id' => $reclamacion->obtenerDocumentoEmitidoId(),
                'motivo_anulacion' => $reclamacion->obtenerMotivoAnulacion(),
                'anulado_por_actor_id' => $reclamacion->obtenerAnuladoPorActorId(),
                'anulado_en' => $reclamacion->obtenerAnuladoEn(),
                'snapshot_consumidor_json' => $snapshotConsumidorJson,
                'snapshot_proveedor_json' => $snapshotProveedorJson,
                'actualizado_por_actor_id' => $reclamacion->obtenerActualizadoPorActorId(),
            ]);

            return $this->buscarPorId($reclamacion->obtenerId()) ?? $reclamacion;
        }

        $sql = 'INSERT INTO reclamaciones (
                    codigo_hoja, codigo_interno, empresa_id, propiedad_id, consumidor_persona_id,
                    es_menor_edad, apoderado_persona_id, tipo, tipo_bien, monto_reclamado, moneda,
                    descripcion_bien, detalle_reclamacion, pedido_consumidor, canal_entrada,
                    estado, fecha_interposicion, fecha_limite_legal, dias_habiles_consumidos,
                    fecha_suspension, fecha_limite_ofrecimiento, documento_emitido_id,
                    motivo_anulacion, anulado_por_actor_id, anulado_en,
                    snapshot_consumidor_json, snapshot_proveedor_json,
                    creado_por_actor_id, actualizado_por_actor_id, creado_en, actualizado_en
                ) VALUES (
                    :codigo_hoja, :codigo_interno, :empresa_id, :propiedad_id, :consumidor_persona_id,
                    :es_menor_edad, :apoderado_persona_id, :tipo, :tipo_bien, :monto_reclamado, :moneda,
                    :descripcion_bien, :detalle_reclamacion, :pedido_consumidor, :canal_entrada,
                    :estado, :fecha_interposicion, :fecha_limite_legal, :dias_habiles_consumidos,
                    :fecha_suspension, :fecha_limite_ofrecimiento, :documento_emitido_id,
                    :motivo_anulacion, :anulado_por_actor_id, :anulado_en,
                    :snapshot_consumidor_json, :snapshot_proveedor_json,
                    :creado_por_actor_id, :actualizado_por_actor_id, NOW(), NOW()
                )';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'codigo_hoja' => $reclamacion->obtenerCodigoHoja(),
            'codigo_interno' => $reclamacion->obtenerCodigoInterno(),
            'empresa_id' => $reclamacion->obtenerEmpresaId(),
            'propiedad_id' => $reclamacion->obtenerPropiedadId(),
            'consumidor_persona_id' => $reclamacion->obtenerConsumidorPersonaId(),
            'es_menor_edad' => $reclamacion->esMenorEdad() ? 1 : 0,
            'apoderado_persona_id' => $reclamacion->obtenerApoderadoPersonaId(),
            'tipo' => $reclamacion->obtenerTipo(),
            'tipo_bien' => $reclamacion->obtenerTipoBien(),
            'monto_reclamado' => $reclamacion->obtenerMontoReclamado(),
            'moneda' => $reclamacion->obtenerMoneda(),
            'descripcion_bien' => $reclamacion->obtenerDescripcionBien(),
            'detalle_reclamacion' => $reclamacion->obtenerDetalleReclamacion(),
            'pedido_consumidor' => $reclamacion->obtenerPedidoConsumidor(),
            'canal_entrada' => $reclamacion->obtenerCanalEntrada(),
            'estado' => $reclamacion->obtenerEstado(),
            'fecha_interposicion' => $reclamacion->obtenerFechaInterposicion(),
            'fecha_limite_legal' => $reclamacion->obtenerFechaLimiteLegal(),
            'dias_habiles_consumidos' => $reclamacion->obtenerDiasHabilesConsumidos(),
            'fecha_suspension' => $reclamacion->obtenerFechaSuspension(),
            'fecha_limite_ofrecimiento' => $reclamacion->obtenerFechaLimiteOfrecimiento(),
            'documento_emitido_id' => $reclamacion->obtenerDocumentoEmitidoId(),
            'motivo_anulacion' => $reclamacion->obtenerMotivoAnulacion(),
            'anulado_por_actor_id' => $reclamacion->obtenerAnuladoPorActorId(),
            'anulado_en' => $reclamacion->obtenerAnuladoEn(),
            'snapshot_consumidor_json' => $snapshotConsumidorJson,
            'snapshot_proveedor_json' => $snapshotProveedorJson,
            'creado_por_actor_id' => $reclamacion->obtenerCreadoPorActorId(),
            'actualizado_por_actor_id' => $reclamacion->obtenerActualizadoPorActorId(),
        ]);

        $nuevoId = (int) $this->pdo->lastInsertId();

        return $this->buscarPorId($nuevoId) ?? $reclamacion;
    }

    /**
     * @param array<string, mixed> $filtros
     * @param int $limite
     * @param int $offset
     * @return array<Reclamacion>
     */
    public function listar(array $filtros = [], int $limite = 25, int $offset = 0): array
    {
        [$condicionSql, $params] = $this->construirClausulaWhere($filtros);

        $sql = "SELECT r.* FROM reclamaciones r
                LEFT JOIN personas p ON p.id = r.consumidor_persona_id
                {$condicionSql}
                ORDER BY r.id DESC
                LIMIT :limite OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':limite', max(1, $limite), PDO::PARAM_INT);
        $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();

        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resultado = [];
        foreach ($filas as $fila) {
            $rec = Reclamacion::desdeArreglo($fila);
            $this->enriquecer($rec, false); // enriquecer ligero
            $resultado[] = $rec;
        }

        return $resultado;
    }

    /**
     * @param array<string, mixed> $filtros
     * @return int
     */
    public function contar(array $filtros = []): int
    {
        [$condicionSql, $params] = $this->construirClausulaWhere($filtros);

        $sql = "SELECT COUNT(r.id) FROM reclamaciones r
                LEFT JOIN personas p ON p.id = r.consumidor_persona_id
                {$condicionSql}";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * Resumen de KPIs de atención y semáforos regulatorios.
     *
     * @param array<string, mixed> $filtros
     * @return array<string, int>
     */
    public function obtenerKpis(array $filtros = []): array
    {
        $propiedadId = !empty($filtros['propiedad_id']) ? (int) $filtros['propiedad_id'] : null;

        $sqlBase = 'SELECT 
                        COUNT(id) AS total,
                        SUM(CASE WHEN estado IN (\'REGISTRADO\', \'EN_PROCESO\') THEN 1 ELSE 0 END) AS en_proceso,
                        SUM(CASE WHEN estado = \'SUSPENDIDO_OFRECIMIENTO\' THEN 1 ELSE 0 END) AS suspendidos,
                        SUM(CASE WHEN estado IN (\'ATENDIDO\', \'CONCLUIDO_POR_ACUERDO\') THEN 1 ELSE 0 END) AS atendidos,
                        SUM(CASE WHEN estado = \'ANULADO\' THEN 1 ELSE 0 END) AS anulados,
                        SUM(CASE WHEN estado IN (\'REGISTRADO\', \'EN_PROCESO\') AND fecha_limite_legal < CURDATE() THEN 1 ELSE 0 END) AS vencidos,
                        SUM(CASE WHEN estado IN (\'REGISTRADO\', \'EN_PROCESO\') AND fecha_limite_legal >= CURDATE() AND DATEDIFF(fecha_limite_legal, CURDATE()) <= 3 THEN 1 ELSE 0 END) AS por_vencer
                    FROM reclamaciones WHERE 1=1';

        $params = [];
        if ($propiedadId !== null) {
            $sqlBase .= ' AND propiedad_id = :propiedad_id';
            $params['propiedad_id'] = $propiedadId;
        }

        $stmt = $this->pdo->prepare($sqlBase);
        $stmt->execute($params);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total' => (int) ($fila['total'] ?? 0),
            'en_proceso' => (int) ($fila['en_proceso'] ?? 0),
            'suspendidos' => (int) ($fila['suspendidos'] ?? 0),
            'atendidos' => (int) ($fila['atendidos'] ?? 0),
            'anulados' => (int) ($fila['anulados'] ?? 0),
            'vencidos' => (int) ($fila['vencidos'] ?? 0),
            'por_vencer' => (int) ($fila['por_vencer'] ?? 0),
        ];
    }

    /**
     * Enriquece la entidad con datos de Consumidor, Empresa, Sede y Actuaciones.
     */
    public function enriquecer(Reclamacion $reclamacion, bool $conActuaciones = true): void
    {
        // Consumidor (Persona)
        $consumidor = $this->personaRepo->buscarPorId($reclamacion->obtenerConsumidorPersonaId());
        $reclamacion->asignarConsumidor($consumidor);

        // Apoderado si aplica
        if ($reclamacion->obtenerApoderadoPersonaId() !== null) {
            $apoderado = $this->personaRepo->buscarPorId($reclamacion->obtenerApoderadoPersonaId());
            $reclamacion->asignarApoderado($apoderado);
        }

        // Empresa
        $stmtEmpresa = $this->pdo->prepare('SELECT id, razon_social, nombre_comercial, numero_documento AS ruc, direccion_fiscal FROM empresas WHERE id = :id LIMIT 1');
        $stmtEmpresa->execute(['id' => $reclamacion->obtenerEmpresaId()]);
        $reclamacion->asignarEmpresa($stmtEmpresa->fetch(PDO::FETCH_ASSOC) ?: null);

        // Propiedad (Sede)
        $stmtProp = $this->pdo->prepare('SELECT id, nombre, codigo, direccion, provincia AS ciudad FROM propiedades WHERE id = :id LIMIT 1');
        $stmtProp->execute(['id' => $reclamacion->obtenerPropiedadId()]);
        $reclamacion->asignarPropiedad($stmtProp->fetch(PDO::FETCH_ASSOC) ?: null);

        // Actuaciones
        if ($conActuaciones && $reclamacion->obtenerId() !== null) {
            $reclamacion->asignarActuaciones($this->actuacionRepo->listarPorReclamacionId($reclamacion->obtenerId()));
        }
    }

    /**
     * @param array<string, mixed> $filtros
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function construirClausulaWhere(array $filtros): array
    {
        $condiciones = ['1=1'];
        $params = [];

        if (!empty($filtros['propiedad_id'])) {
            $condiciones[] = 'r.propiedad_id = :propiedad_id';
            $params['propiedad_id'] = (int) $filtros['propiedad_id'];
        }

        if (!empty($filtros['empresa_id'])) {
            $condiciones[] = 'r.empresa_id = :empresa_id';
            $params['empresa_id'] = (int) $filtros['empresa_id'];
        }

        if (!empty($filtros['estado'])) {
            $condiciones[] = 'r.estado = :estado';
            $params['estado'] = $filtros['estado'];
        }

        if (!empty($filtros['tipo'])) {
            $condiciones[] = 'r.tipo = :tipo';
            $params['tipo'] = $filtros['tipo'];
        }

        if (!empty($filtros['anio'])) {
            $condiciones[] = 'YEAR(r.fecha_interposicion) = :anio';
            $params['anio'] = (int) $filtros['anio'];
        }

        if (!empty($filtros['busqueda'])) {
            $condiciones[] = '(r.codigo_hoja LIKE :busq OR r.codigo_interno LIKE :busq OR p.nombres LIKE :busq OR p.apellidos LIKE :busq OR p.numero_documento LIKE :busq)';
            $params['busq'] = '%' . trim($filtros['busqueda']) . '%';
        }

        return ['WHERE ' . implode(' AND ', $condiciones), $params];
    }
}
