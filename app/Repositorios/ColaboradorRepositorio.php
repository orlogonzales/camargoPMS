<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\Colaborador;
use CamargoPMS\Nucleo\BaseDatos;
use PDO;

/**
 * Repositorio de persistencia para colaboradores de la organización.
 *
 * Mantiene la relación 1:1 lógica con la entidad Persona y coordina
 * la hidratación del historial laboral.
 */
class ColaboradorRepositorio
{
    private PDO $pdo;
    private ?PersonaRepositorio $personaRepo;
    private ?EpisodioLaboralRepositorio $episodioRepo;

    public function __construct(
        ?PDO $pdo = null,
        ?PersonaRepositorio $personaRepo = null,
        ?EpisodioLaboralRepositorio $episodioRepo = null
    ) {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->personaRepo = $personaRepo ?? new PersonaRepositorio($this->pdo);
        $this->episodioRepo = $episodioRepo ?? new EpisodioLaboralRepositorio($this->pdo);
    }

    /**
     * Busca un colaborador por su identificador primario.
     *
     * @param int $id
     * @param bool $cargarRelaciones
     * @return Colaborador|null
     */
    public function buscarPorId(int $id, bool $cargarRelaciones = true): ?Colaborador
    {
        $sql = "SELECT * FROM colaboradores WHERE id = :id LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch();
        if (!$fila) {
            return null;
        }

        $colaborador = Colaborador::desdeArreglo($fila);
        if ($cargarRelaciones) {
            $this->hidratarRelaciones($colaborador);
        }

        return $colaborador;
    }

    /**
     * Busca un colaborador por el ID de la persona asociada (relación 1:1 lógica).
     *
     * @param int $personaId
     * @param bool $cargarRelaciones
     * @return Colaborador|null
     */
    public function buscarPorPersonaId(int $personaId, bool $cargarRelaciones = true): ?Colaborador
    {
        $sql = "SELECT * FROM colaboradores WHERE persona_id = :persona_id LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':persona_id', $personaId, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch();
        if (!$fila) {
            return null;
        }

        $colaborador = Colaborador::desdeArreglo($fila);
        if ($cargarRelaciones) {
            $this->hidratarRelaciones($colaborador);
        }

        return $colaborador;
    }

    /**
     * Busca un colaborador por su código interno único (ej. 'COL-0001').
     *
     * @param string $codigo
     * @param bool $cargarRelaciones
     * @return Colaborador|null
     */
    public function buscarPorCodigo(string $codigo, bool $cargarRelaciones = true): ?Colaborador
    {
        $sql = "SELECT * FROM colaboradores WHERE codigo = :codigo LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':codigo', strtoupper(trim($codigo)), PDO::PARAM_STR);
        $stmt->execute();

        $fila = $stmt->fetch();
        if (!$fila) {
            return null;
        }

        $colaborador = Colaborador::desdeArreglo($fila);
        if ($cargarRelaciones) {
            $this->hidratarRelaciones($colaborador);
        }

        return $colaborador;
    }

    /**
     * Lista colaboradores activos.
     *
     * @param int $limite
     * @param int $offset
     * @param bool $cargarRelaciones
     * @return array<int, Colaborador>
     */
    public function listarActivos(int $limite = 50, int $offset = 0, bool $cargarRelaciones = true): array
    {
        $sql = "SELECT * FROM colaboradores 
                WHERE estado = 'ACTIVO' 
                ORDER BY id ASC 
                LIMIT :limite OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $colaboradores = [];
        while ($fila = $stmt->fetch()) {
            $colab = Colaborador::desdeArreglo($fila);
            if ($cargarRelaciones) {
                $this->hidratarRelaciones($colab);
            }
            $colaboradores[] = $colab;
        }

        return $colaboradores;
    }

    /**
     * Inserta un nuevo colaborador en la base de datos.
     *
     * @param Colaborador $colaborador
     * @return Colaborador
     */
    public function insertar(Colaborador $colaborador): Colaborador
    {
        $sql = "INSERT INTO colaboradores (
                    persona_id,
                    codigo,
                    estado,
                    creado_en,
                    actualizado_en
                ) VALUES (
                    :persona_id,
                    :codigo,
                    :estado,
                    NOW(),
                    NOW()
                )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':persona_id', $colaborador->obtenerPersonaId(), PDO::PARAM_INT);
        $stmt->bindValue(':codigo', $colaborador->obtenerCodigo(), PDO::PARAM_STR);
        $stmt->bindValue(':estado', $colaborador->obtenerEstado(), PDO::PARAM_STR);
        $stmt->execute();

        $nuevoId = (int) $this->pdo->lastInsertId();
        return $this->buscarPorId($nuevoId, false) ?? $colaborador;
    }

    /**
     * Actualiza el estado de un colaborador.
     *
     * @param int $id
     * @param string $estado
     * @return bool
     */
    public function actualizarEstado(int $id, string $estado): bool
    {
        $sql = "UPDATE colaboradores 
                SET estado = :estado,
                    actualizado_en = NOW() 
                WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':estado', strtoupper(trim($estado)), PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * Genera el siguiente código secuencial disponible para colaboradores (ej. 'COL-0001').
     *
     * @return string
     */
    public function generarSiguienteCodigo(): string
    {
        $sql = "SELECT MAX(id) AS max_id FROM colaboradores";
        $stmt = $this->pdo->query($sql);
        $fila = $stmt->fetch();
        $siguienteNumero = ($fila && isset($fila['max_id']) ? (int) $fila['max_id'] : 0) + 1;

        return sprintf('COL-%04d', $siguienteNumero);
    }

    /**
     * Lista colaboradores con filtros de búsqueda, estado y cargo.
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

        $sql = "SELECT
                    c.id,
                    c.persona_id,
                    c.codigo,
                    c.estado,
                    c.creado_en,
                    c.actualizado_en,
                    p.nombres,
                    p.apellido_paterno,
                    p.apellido_materno,
                    CONCAT(p.nombres, ' ', p.apellido_paterno, IF(p.apellido_materno IS NOT NULL AND p.apellido_materno <> '', CONCAT(' ', p.apellido_materno), '')) AS nombre_completo,
                    td.codigo AS tipo_documento,
                    pd.numero_documento,
                    pc_tel.valor AS telefono,
                    pc_em.valor AS email,
                    car.id AS cargo_id,
                    car.codigo AS cargo_codigo,
                    car.nombre AS cargo_nombre,
                    asig.fecha_inicio AS cargo_fecha_inicio,
                    ep.id AS episodio_id,
                    ep.fecha_inicio AS episodio_fecha_inicio,
                    ep.fecha_fin AS episodio_fecha_fin,
                    u.id AS usuario_id,
                    u.nombre_usuario,
                    u.estado AS usuario_estado,
                    u.ultimo_acceso_en AS usuario_ultimo_acceso
                FROM colaboradores c
                INNER JOIN personas p ON p.id = c.persona_id
                LEFT JOIN personas_documentos pd ON pd.persona_id = p.id AND pd.es_principal = 1
                LEFT JOIN tipos_documento td ON td.id = pd.tipo_documento_id
                LEFT JOIN personas_contactos pc_tel ON pc_tel.persona_id = p.id AND pc_tel.tipo_contacto = 'TELEFONO' AND pc_tel.es_principal = 1
                LEFT JOIN personas_contactos pc_em ON pc_em.persona_id = p.id AND pc_em.tipo_contacto = 'EMAIL' AND pc_em.es_principal = 1
                LEFT JOIN episodios_laborales ep ON ep.colaborador_id = c.id AND ep.fecha_fin IS NULL AND ep.estado = 'ACTIVO'
                LEFT JOIN episodios_laborales_cargos asig ON asig.episodio_laboral_id = ep.id AND asig.fecha_fin IS NULL
                LEFT JOIN cargos car ON car.id = asig.cargo_id
                LEFT JOIN usuarios u ON u.persona_id = p.id
                WHERE 1=1";

        $params = [];

        if (!empty($filtros['estado'])) {
            $sql .= " AND c.estado = :estado";
            $params[':estado'] = strtoupper(trim((string) $filtros['estado']));
        }

        if (!empty($filtros['cargo_id'])) {
            $sql .= " AND car.id = :cargo_id";
            $params[':cargo_id'] = (int) $filtros['cargo_id'];
        }

        if (!empty($filtros['q'])) {
            $sql .= " AND (c.codigo LIKE :q OR p.nombres LIKE :q OR p.apellido_paterno LIKE :q OR p.apellido_materno LIKE :q OR pd.numero_documento LIKE :q OR u.nombre_usuario LIKE :q)";
            $params[':q'] = '%' . trim((string) $filtros['q']) . '%';
        }

        $sql .= " ORDER BY c.id DESC LIMIT :limite OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $clave => $valor) {
            if (is_int($valor)) {
                $stmt->bindValue($clave, $valor, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($clave, $valor, PDO::PARAM_STR);
            }
        }
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cuenta el total de colaboradores según filtros aplicados.
     *
     * @param array<string, mixed> $filtros
     * @return int
     */
    public function contar(array $filtros = []): int
    {
        $sql = "SELECT COUNT(DISTINCT c.id)
                FROM colaboradores c
                INNER JOIN personas p ON p.id = c.persona_id
                LEFT JOIN personas_documentos pd ON pd.persona_id = p.id AND pd.es_principal = 1
                LEFT JOIN episodios_laborales ep ON ep.colaborador_id = c.id AND ep.fecha_fin IS NULL AND ep.estado = 'ACTIVO'
                LEFT JOIN episodios_laborales_cargos asig ON asig.episodio_laboral_id = ep.id AND asig.fecha_fin IS NULL
                LEFT JOIN cargos car ON car.id = asig.cargo_id
                LEFT JOIN usuarios u ON u.persona_id = p.id
                WHERE 1=1";

        $params = [];

        if (!empty($filtros['estado'])) {
            $sql .= " AND c.estado = :estado";
            $params[':estado'] = strtoupper(trim((string) $filtros['estado']));
        }

        if (!empty($filtros['cargo_id'])) {
            $sql .= " AND car.id = :cargo_id";
            $params[':cargo_id'] = (int) $filtros['cargo_id'];
        }

        if (!empty($filtros['q'])) {
            $sql .= " AND (c.codigo LIKE :q OR p.nombres LIKE :q OR p.apellido_paterno LIKE :q OR p.apellido_materno LIKE :q OR pd.numero_documento LIKE :q OR u.nombre_usuario LIKE :q)";
            $params[':q'] = '%' . trim((string) $filtros['q']) . '%';
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $clave => $valor) {
            if (is_int($valor)) {
                $stmt->bindValue($clave, $valor, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($clave, $valor, PDO::PARAM_STR);
            }
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * Busca la ficha completa y consolidada de un colaborador con historial laboral y de accesos.
     *
     * @param int $id
     * @return array<string, mixed>|null
     */
    public function buscarDetalleCompleto(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $sql = "SELECT
                    c.id,
                    c.persona_id,
                    c.codigo,
                    c.estado,
                    c.creado_en,
                    c.actualizado_en,
                    p.nombres,
                    p.apellido_paterno,
                    p.apellido_materno,
                    CONCAT(p.nombres, ' ', p.apellido_paterno, IF(p.apellido_materno IS NOT NULL AND p.apellido_materno <> '', CONCAT(' ', p.apellido_materno), '')) AS nombre_completo,
                    p.genero,
                    p.fecha_nacimiento,
                    p.direccion,
                    p.pais_nacionalidad_id,
                    p.pais_residencia_id,
                    p.distrito_id,
                    p.region_residencia_extranjera,
                    p.ciudad_residencia_extranjera,
                    p.estado AS persona_estado,
                    td.codigo AS tipo_documento,
                    pd.numero_documento,
                    pc_tel.valor AS telefono,
                    pc_em.valor AS email,
                    car.id AS cargo_id,
                    car.codigo AS cargo_codigo,
                    car.nombre AS cargo_nombre,
                    asig.fecha_inicio AS cargo_fecha_inicio,
                    ep.id AS episodio_id,
                    ep.fecha_inicio AS episodio_fecha_inicio,
                    ep.fecha_fin AS episodio_fecha_fin,
                    ep.motivo_cese,
                    ep.observaciones AS episodio_observaciones,
                    u.id AS usuario_id,
                    u.nombre_usuario,
                    u.estado AS usuario_estado,
                    u.ultimo_acceso_en AS usuario_ultimo_acceso
                FROM colaboradores c
                INNER JOIN personas p ON p.id = c.persona_id
                LEFT JOIN personas_documentos pd ON pd.persona_id = p.id AND pd.es_principal = 1
                LEFT JOIN tipos_documento td ON td.id = pd.tipo_documento_id
                LEFT JOIN personas_contactos pc_tel ON pc_tel.persona_id = p.id AND pc_tel.tipo_contacto = 'TELEFONO' AND pc_tel.es_principal = 1
                LEFT JOIN personas_contactos pc_em ON pc_em.persona_id = p.id AND pc_em.tipo_contacto = 'EMAIL' AND pc_em.es_principal = 1
                LEFT JOIN episodios_laborales ep ON ep.colaborador_id = c.id AND ep.fecha_fin IS NULL AND ep.estado = 'ACTIVO'
                LEFT JOIN episodios_laborales_cargos asig ON asig.episodio_laboral_id = ep.id AND asig.fecha_fin IS NULL
                LEFT JOIN cargos car ON car.id = asig.cargo_id
                LEFT JOIN usuarios u ON u.persona_id = p.id
                WHERE c.id = :id
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            return null;
        }

        // Historial completo de episodios y sus asignaciones de cargos
        $sqlEp = "SELECT
                    e.id,
                    e.fecha_inicio,
                    e.fecha_fin,
                    e.motivo_cese,
                    e.observaciones,
                    e.estado,
                    (e.fecha_fin IS NULL AND e.estado = 'ACTIVO') AS es_abierto
                  FROM episodios_laborales e
                  WHERE e.colaborador_id = :colab_id
                  ORDER BY e.fecha_inicio DESC, e.id DESC";
        $stmtEp = $this->pdo->prepare($sqlEp);
        $stmtEp->bindValue(':colab_id', $id, PDO::PARAM_INT);
        $stmtEp->execute();
        $episodios = $stmtEp->fetchAll(PDO::FETCH_ASSOC);

        $sqlCargos = "SELECT
                        ac.id,
                        ac.episodio_laboral_id,
                        ac.cargo_id,
                        c.codigo AS cargo_codigo,
                        c.nombre AS cargo_nombre,
                        ac.fecha_inicio,
                        ac.fecha_fin,
                        ac.observaciones,
                        (ac.fecha_fin IS NULL) AS es_vigente
                      FROM episodios_laborales_cargos ac
                      INNER JOIN cargos c ON c.id = ac.cargo_id
                      WHERE ac.episodio_laboral_id = :ep_id
                      ORDER BY ac.fecha_inicio DESC, ac.id DESC";
        $stmtCargos = $this->pdo->prepare($sqlCargos);

        foreach ($episodios as &$epItem) {
            $stmtCargos->bindValue(':ep_id', $epItem['id'], PDO::PARAM_INT);
            $stmtCargos->execute();
            $epItem['cargos'] = $stmtCargos->fetchAll(PDO::FETCH_ASSOC);
        }
        unset($epItem);

        $fila['episodios'] = $episodios;

        // Contactos de la persona
        $sqlContactos = "SELECT id, tipo_contacto, valor, es_whatsapp, es_principal, estado
                         FROM personas_contactos
                         WHERE persona_id = :persona_id
                         ORDER BY es_principal DESC, id ASC";
        $stmtCont = $this->pdo->prepare($sqlContactos);
        $stmtCont->bindValue(':persona_id', $fila['persona_id'], PDO::PARAM_INT);
        $stmtCont->execute();
        $fila['contactos'] = $stmtCont->fetchAll(PDO::FETCH_ASSOC);

        return $fila;
    }

    /**
     * Hidrata las relaciones de Persona y Episodios para un colaborador.
     *
     * @param Colaborador $colaborador
     */
    private function hidratarRelaciones(Colaborador $colaborador): void
    {
        $persona = $this->personaRepo->buscarPorId($colaborador->obtenerPersonaId(), true);
        if ($persona) {
            $colaborador->asignarPersona($persona);
        }

        if ($colaborador->obtenerId() !== null) {
            $episodios = $this->episodioRepo->listarPorColaboradorId((int) $colaborador->obtenerId(), true);
            $colaborador->asignarEpisodios($episodios);
        }
    }
}
