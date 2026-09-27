<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\Estadia;
use CamargoPMS\Modelos\EstadiaHuesped;
use CamargoPMS\Nucleo\BaseDatos;
use PDO;

/**
 * Repositorio de persistencia para el módulo operativo de estadías y huéspedes (ESTADÍAS-1).
 *
 * Principios vinculantes:
 * - RESERVA != ESTADÍA != ARRENDAMIENTO.
 * - 1 Reserva : N Estadías físicas independientes (una por reserva_unidad).
 * - UNIQUE(reserva_unidad_id): una reserva_unidad genera como máximo una estadía histórica.
 * - Preservación histórica: Cero DELETE sobre la tabla estadias.
 * - Consultas parametrizadas y PDO estricto.
 */
class EstadiaRepositorio
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
     * Busca una estadía por su ID primario.
     */
    public function buscarPorId(int $id, bool $cargarHuespedes = true): ?Estadia
    {
        $sql = 'SELECT e.*,
                       r.codigo AS reserva_codigo,
                       u.codigo AS unidad_numero,
                       u.nombre AS unidad_nombre,
                       u.capacidad_personas,
                       prop.id AS propiedad_id,
                       prop.nombre AS propiedad_nombre,
                       TRIM(CONCAT(COALESCE(pt.nombres, ""), " ", COALESCE(pt.apellido_paterno, ""), " ", COALESCE(pt.apellido_materno, ""))) AS titular_nombre_completo,
                       act_in.nombre AS checkin_por_actor_nombre,
                       act_out.nombre AS checkout_por_actor_nombre,
                       act_anul.nombre AS anulada_por_actor_nombre
                FROM estadias e
                INNER JOIN reservas r ON e.reserva_id = r.id
                INNER JOIN unidades u ON e.unidad_id = u.id
                INNER JOIN propiedades prop ON u.propiedad_id = prop.id
                INNER JOIN personas pt ON r.persona_titular_id = pt.id
                LEFT JOIN actores act_in ON e.checkin_por_actor_id = act_in.id
                LEFT JOIN actores act_out ON e.checkout_por_actor_id = act_out.id
                LEFT JOIN actores act_anul ON e.anulada_por_actor_id = act_anul.id
                WHERE e.id = :id
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fila) {
            return null;
        }

        $estadia = Estadia::desdeArreglo($fila);

        if ($cargarHuespedes) {
            $huespedes = $this->obtenerHuespedesPorEstadiaId($id);
            $estadia->asignarHuespedes($huespedes);
        }

        return $estadia;
    }

    /**
     * Busca y bloquea una estadía con FOR UPDATE para transiciones atómicas seguras.
     */
    public function buscarPorIdParaActualizar(int $id): ?Estadia
    {
        $sql = 'SELECT e.*,
                       r.codigo AS reserva_codigo,
                       u.codigo AS unidad_numero,
                       u.nombre AS unidad_nombre,
                       u.capacidad_personas,
                       prop.id AS propiedad_id,
                       prop.nombre AS propiedad_nombre,
                       TRIM(CONCAT(COALESCE(pt.nombres, ""), " ", COALESCE(pt.apellido_paterno, ""), " ", COALESCE(pt.apellido_materno, ""))) AS titular_nombre_completo,
                       act_in.nombre AS checkin_por_actor_nombre,
                       act_out.nombre AS checkout_por_actor_nombre,
                       act_anul.nombre AS anulada_por_actor_nombre
                FROM estadias e
                INNER JOIN reservas r ON e.reserva_id = r.id
                INNER JOIN unidades u ON e.unidad_id = u.id
                INNER JOIN propiedades prop ON u.propiedad_id = prop.id
                INNER JOIN personas pt ON r.persona_titular_id = pt.id
                LEFT JOIN actores act_in ON e.checkin_por_actor_id = act_in.id
                LEFT JOIN actores act_out ON e.checkout_por_actor_id = act_out.id
                LEFT JOIN actores act_anul ON e.anulada_por_actor_id = act_anul.id
                WHERE e.id = :id
                LIMIT 1
                FOR UPDATE';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fila) {
            return null;
        }

        $estadia = Estadia::desdeArreglo($fila);
        $huespedes = $this->obtenerHuespedesPorEstadiaId($id);
        $estadia->asignarHuespedes($huespedes);

        return $estadia;
    }

    /**
     * Busca una estadía por su código único de estadía.
     */
    public function buscarPorCodigo(string $codigo, bool $cargarHuespedes = true): ?Estadia
    {
        $sql = 'SELECT e.*,
                       r.codigo AS reserva_codigo,
                       u.codigo AS unidad_numero,
                       u.nombre AS unidad_nombre,
                       u.capacidad_personas,
                       prop.id AS propiedad_id,
                       prop.nombre AS propiedad_nombre,
                       TRIM(CONCAT(COALESCE(pt.nombres, ""), " ", COALESCE(pt.apellido_paterno, ""), " ", COALESCE(pt.apellido_materno, ""))) AS titular_nombre_completo,
                       act_in.nombre AS checkin_por_actor_nombre,
                       act_out.nombre AS checkout_por_actor_nombre,
                       act_anul.nombre AS anulada_por_actor_nombre
                FROM estadias e
                INNER JOIN reservas r ON e.reserva_id = r.id
                INNER JOIN unidades u ON e.unidad_id = u.id
                INNER JOIN propiedades prop ON u.propiedad_id = prop.id
                INNER JOIN personas pt ON r.persona_titular_id = pt.id
                LEFT JOIN actores act_in ON e.checkin_por_actor_id = act_in.id
                LEFT JOIN actores act_out ON e.checkout_por_actor_id = act_out.id
                LEFT JOIN actores act_anul ON e.anulada_por_actor_id = act_anul.id
                WHERE e.codigo = :codigo
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':codigo', trim(strtoupper($codigo)), PDO::PARAM_STR);
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fila) {
            return null;
        }

        $estadia = Estadia::desdeArreglo($fila);

        if ($cargarHuespedes) {
            $huespedes = $this->obtenerHuespedesPorEstadiaId((int) $fila['id']);
            $estadia->asignarHuespedes($huespedes);
        }

        return $estadia;
    }

    /**
     * Busca una estadía por el ID de la unidad de reserva asignada.
     */
    public function buscarPorReservaUnidadId(int $reservaUnidadId): ?Estadia
    {
        $sql = 'SELECT e.*,
                       r.codigo AS reserva_codigo,
                       u.codigo AS unidad_numero,
                       u.nombre AS unidad_nombre,
                       u.capacidad_personas,
                       prop.id AS propiedad_id,
                       prop.nombre AS propiedad_nombre,
                       TRIM(CONCAT(COALESCE(pt.nombres, ""), " ", COALESCE(pt.apellido_paterno, ""), " ", COALESCE(pt.apellido_materno, ""))) AS titular_nombre_completo,
                       act_in.nombre AS checkin_por_actor_nombre,
                       act_out.nombre AS checkout_por_actor_nombre,
                       act_anul.nombre AS anulada_por_actor_nombre
                FROM estadias e
                INNER JOIN reservas r ON e.reserva_id = r.id
                INNER JOIN unidades u ON e.unidad_id = u.id
                INNER JOIN propiedades prop ON u.propiedad_id = prop.id
                INNER JOIN personas pt ON r.persona_titular_id = pt.id
                LEFT JOIN actores act_in ON e.checkin_por_actor_id = act_in.id
                LEFT JOIN actores act_out ON e.checkout_por_actor_id = act_out.id
                LEFT JOIN actores act_anul ON e.anulada_por_actor_id = act_anul.id
                WHERE e.reserva_unidad_id = :reserva_unidad_id
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':reserva_unidad_id', $reservaUnidadId, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fila) {
            return null;
        }

        $estadia = Estadia::desdeArreglo($fila);
        $huespedes = $this->obtenerHuespedesPorEstadiaId((int) $fila['id']);
        $estadia->asignarHuespedes($huespedes);

        return $estadia;
    }

    /**
     * Inserta un nuevo registro de estadía física en la base de datos.
     */
    public function guardar(Estadia $estadia): int
    {
        $sql = 'INSERT INTO estadias (
                    codigo, reserva_id, reserva_unidad_id, unidad_id,
                    fecha_entrada, fecha_salida_prevista, estado,
                    checkin_en, checkin_por_actor_id,
                    identificador_llave, observaciones_checkin,
                    creado_en
                ) VALUES (
                    :codigo, :reserva_id, :reserva_unidad_id, :unidad_id,
                    :fecha_entrada, :fecha_salida_prevista, :estado,
                    :checkin_en, :checkin_por_actor_id,
                    :identificador_llave, :observaciones_checkin,
                    NOW()
                )';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':codigo', $estadia->obtenerCodigo(), PDO::PARAM_STR);
        $stmt->bindValue(':reserva_id', $estadia->obtenerReservaId(), PDO::PARAM_INT);
        $stmt->bindValue(':reserva_unidad_id', $estadia->obtenerReservaUnidadId(), PDO::PARAM_INT);
        $stmt->bindValue(':unidad_id', $estadia->obtenerUnidadId(), PDO::PARAM_INT);
        $stmt->bindValue(':fecha_entrada', $estadia->obtenerFechaEntrada(), PDO::PARAM_STR);
        $stmt->bindValue(':fecha_salida_prevista', $estadia->obtenerFechaSalidaPrevista(), PDO::PARAM_STR);
        $stmt->bindValue(':estado', $estadia->obtenerEstado(), PDO::PARAM_STR);
        $stmt->bindValue(':checkin_en', $estadia->obtenerCheckinEn(), PDO::PARAM_STR);
        $stmt->bindValue(':checkin_por_actor_id', $estadia->obtenerCheckinPorActorId(), PDO::PARAM_INT);
        $stmt->bindValue(':identificador_llave', $estadia->obtenerIdentificadorLlave(), $estadia->obtenerIdentificadorLlave() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':observaciones_checkin', $estadia->obtenerObservacionesCheckin(), $estadia->obtenerObservacionesCheckin() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->execute();

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Actualiza el estado operativo de una estadía (Check-out o Anulación).
     */
    public function actualizarEstado(
        int $id,
        string $nuevoEstado,
        ?string $checkoutEn = null,
        ?int $checkoutPorActorId = null,
        ?string $observacionesCheckout = null,
        ?string $anuladaEn = null,
        ?int $anuladaPorActorId = null,
        ?string $motivoAnulacion = null
    ): bool {
        $sql = 'UPDATE estadias SET
                    estado = :estado,
                    checkout_en = :checkout_en,
                    checkout_por_actor_id = :checkout_por_actor_id,
                    observaciones_checkout = :observaciones_checkout,
                    anulada_en = :anulada_en,
                    anulada_por_actor_id = :anulada_por_actor_id,
                    motivo_anulacion = :motivo_anulacion,
                    actualizado_en = NOW()
                WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':estado', $nuevoEstado, PDO::PARAM_STR);
        $stmt->bindValue(':checkout_en', $checkoutEn, $checkoutEn !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':checkout_por_actor_id', $checkoutPorActorId, $checkoutPorActorId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':observaciones_checkout', $observacionesCheckout, $observacionesCheckout !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':anulada_en', $anuladaEn, $anuladaEn !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':anulada_por_actor_id', $anuladaPorActorId, $anuladaPorActorId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':motivo_anulacion', $motivoAnulacion, $motivoAnulacion !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Guarda la lista de huéspedes para una estadía.
     *
     * @param array<EstadiaHuesped> $huespedes
     */
    public function guardarHuespedes(int $estadiaId, array $huespedes): void
    {
        $sql = 'INSERT INTO estadia_huespedes (
                    estadia_id, persona_id, es_responsable, creado_en
                ) VALUES (
                    :estadia_id, :persona_id, :es_responsable, NOW()
                )';

        $stmt = $this->pdo->prepare($sql);
        foreach ($huespedes as $huesped) {
            $stmt->bindValue(':estadia_id', $estadiaId, PDO::PARAM_INT);
            $stmt->bindValue(':persona_id', $huesped->obtenerPersonaId(), PDO::PARAM_INT);
            $stmt->bindValue(':es_responsable', $huesped->esResponsable() ? 1 : 0, PDO::PARAM_INT);
            $stmt->execute();
        }
    }

    /**
     * Reemplaza atómicamente la lista de huéspedes de una estadía (dentro de una transacción).
     *
     * @param array<EstadiaHuesped> $huespedes
     */
    public function reemplazarHuespedes(int $estadiaId, array $huespedes): void
    {
        $sqlDelete = 'DELETE FROM estadia_huespedes WHERE estadia_id = :estadia_id';
        $stmtDel = $this->pdo->prepare($sqlDelete);
        $stmtDel->bindValue(':estadia_id', $estadiaId, PDO::PARAM_INT);
        $stmtDel->execute();

        $this->guardarHuespedes($estadiaId, $huespedes);
    }

    /**
     * Obtiene los huéspedes de una estadía con sus datos de persona vinculados.
     *
     * @return array<EstadiaHuesped>
     */
    public function obtenerHuespedesPorEstadiaId(int $estadiaId): array
    {
        $sql = 'SELECT eh.*,
                       TRIM(CONCAT(COALESCE(p.nombres, ""), " ", COALESCE(p.apellido_paterno, ""), " ", COALESCE(p.apellido_materno, ""))) AS nombre_completo,
                       p.nombres AS persona_nombres,
                       p.apellido_paterno,
                       p.apellido_materno
                FROM estadia_huespedes eh
                INNER JOIN personas p ON eh.persona_id = p.id
                WHERE eh.estadia_id = :estadia_id
                ORDER BY eh.es_responsable DESC, eh.id ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':estadia_id', $estadiaId, PDO::PARAM_INT);
        $stmt->execute();

        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $huespedes = [];

        foreach ($filas as $fila) {
            $huesped = EstadiaHuesped::desdeArreglo($fila);
            $this->cargarContactoHuesped($huesped);
            $huespedes[] = $huesped;
        }

        return $huespedes;
    }

    /**
     * Carga documento principal y datos de contacto de un huésped.
     */
    private function cargarContactoHuesped(EstadiaHuesped $huesped): void
    {
        $personaId = $huesped->obtenerPersonaId();
        if ($personaId <= 0) {
            return;
        }

        // Documento principal
        $sqlDoc = 'SELECT d.numero_documento, td.codigo AS tipo_codigo
                   FROM personas_documentos d
                   INNER JOIN tipos_documento td ON d.tipo_documento_id = td.id
                   WHERE d.persona_id = :persona_id AND d.estado = "ACTIVO"
                   ORDER BY d.es_principal DESC, d.id ASC LIMIT 1';
        $stmtDoc = $this->pdo->prepare($sqlDoc);
        $stmtDoc->bindValue(':persona_id', $personaId, PDO::PARAM_INT);
        $stmtDoc->execute();
        $doc = $stmtDoc->fetch(PDO::FETCH_ASSOC);

        // Contactos
        $sqlCont = 'SELECT valor, tipo_contacto AS tipo FROM personas_contactos
                    WHERE persona_id = :persona_id AND estado = "ACTIVO"
                    ORDER BY es_principal DESC, id ASC';
        $stmtCont = $this->pdo->prepare($sqlCont);
        $stmtCont->bindValue(':persona_id', $personaId, PDO::PARAM_INT);
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

        $huesped->asignarMetadatosPersona(
            $huesped->obtenerNombreCompleto(),
            $doc ? (string) $doc['tipo_codigo'] : null,
            $doc ? (string) $doc['numero_documento'] : null,
            $email,
            $telefono
        );
    }

    /**
     * Lista estadías según filtros y paginación.
     *
     * @param array<string, mixed> $filtros
     * @return array<Estadia>
     */
    public function listar(array $filtros = [], int $limite = 20, int $offset = 0): array
    {
        [$where, $params] = $this->construirWhereFiltros($filtros);

        $sql = "SELECT e.*,
                       r.codigo AS reserva_codigo,
                       u.codigo AS unidad_numero,
                       u.nombre AS unidad_nombre,
                       u.capacidad_personas,
                       prop.id AS propiedad_id,
                       prop.nombre AS propiedad_nombre,
                       TRIM(CONCAT(COALESCE(pt.nombres, ''), ' ', COALESCE(pt.apellido_paterno, ''), ' ', COALESCE(pt.apellido_materno, ''))) AS titular_nombre_completo,
                       act_in.nombre AS checkin_por_actor_nombre,
                       act_out.nombre AS checkout_por_actor_nombre,
                       act_anul.nombre AS anulada_por_actor_nombre
                FROM estadias e
                INNER JOIN reservas r ON e.reserva_id = r.id
                INNER JOIN unidades u ON e.unidad_id = u.id
                INNER JOIN propiedades prop ON u.propiedad_id = prop.id
                INNER JOIN personas pt ON r.persona_titular_id = pt.id
                LEFT JOIN actores act_in ON e.checkin_por_actor_id = act_in.id
                LEFT JOIN actores act_out ON e.checkout_por_actor_id = act_out.id
                LEFT JOIN actores act_anul ON e.anulada_por_actor_id = act_anul.id
                WHERE {$where}
                ORDER BY e.id DESC
                LIMIT :limite OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limite', max(1, $limite), PDO::PARAM_INT);
        $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();

        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $estadias = [];

        foreach ($filas as $fila) {
            $estadia = Estadia::desdeArreglo($fila);
            $huespedes = $this->obtenerHuespedesPorEstadiaId((int) $fila['id']);
            $estadia->asignarHuespedes($huespedes);
            $estadias[] = $estadia;
        }

        return $estadias;
    }

    /**
     * Cuenta el total de estadías según los filtros.
     *
     * @param array<string, mixed> $filtros
     */
    public function contar(array $filtros = []): int
    {
        [$where, $params] = $this->construirWhereFiltros($filtros);

        $sql = "SELECT COUNT(*)
                FROM estadias e
                INNER JOIN reservas r ON e.reserva_id = r.id
                INNER JOIN unidades u ON e.unidad_id = u.id
                INNER JOIN propiedades prop ON u.propiedad_id = prop.id
                INNER JOIN personas pt ON r.persona_titular_id = pt.id
                WHERE {$where}";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * Obtiene las unidades de una reserva confirmada que aún NO han realizado check-in (elegibles).
     *
     * @return array<array<string, mixed>>
     */
    public function obtenerUnidadesElegiblesParaCheckin(int $reservaId): array
    {
        $sql = 'SELECT ru.id AS reserva_unidad_id,
                       ru.reserva_id,
                       ru.unidad_id,
                       ru.precio_unitario_noche,
                       ru.noches,
                       ru.total,
                       u.codigo AS unidad_numero,
                       u.nombre AS unidad_nombre,
                       u.capacidad_personas,
                       tu.nombre AS tipo_unidad_nombre,
                       p.id AS propiedad_id,
                       p.nombre AS propiedad_nombre
                FROM reserva_unidades ru
                INNER JOIN reservas r ON ru.reserva_id = r.id
                INNER JOIN unidades u ON ru.unidad_id = u.id
                LEFT JOIN tipos_unidad tu ON u.tipo_unidad_id = tu.id
                INNER JOIN propiedades p ON u.propiedad_id = p.id
                LEFT JOIN estadias e ON ru.id = e.reserva_unidad_id
                WHERE ru.reserva_id = :reserva_id
                  AND r.estado = "CONFIRMADA"
                  AND e.id IS NULL
                ORDER BY u.codigo ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':reserva_id', $reservaId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene todas las estadías asociadas a una reserva específica.
     *
     * @return array<Estadia>
     */
    public function obtenerEstadiasPorReservaId(int $reservaId): array
    {
        $sql = 'SELECT e.*,
                       r.codigo AS reserva_codigo,
                       u.codigo AS unidad_numero,
                       u.nombre AS unidad_nombre,
                       u.capacidad_personas,
                       prop.id AS propiedad_id,
                       prop.nombre AS propiedad_nombre,
                       TRIM(CONCAT(COALESCE(pt.nombres, ""), " ", COALESCE(pt.apellido_paterno, ""), " ", COALESCE(pt.apellido_materno, ""))) AS titular_nombre_completo,
                       act_in.nombre AS checkin_por_actor_nombre,
                       act_out.nombre AS checkout_por_actor_nombre,
                       act_anul.nombre AS anulada_por_actor_nombre
                FROM estadias e
                INNER JOIN reservas r ON e.reserva_id = r.id
                INNER JOIN unidades u ON e.unidad_id = u.id
                INNER JOIN propiedades prop ON u.propiedad_id = prop.id
                INNER JOIN personas pt ON r.persona_titular_id = pt.id
                LEFT JOIN actores act_in ON e.checkin_por_actor_id = act_in.id
                LEFT JOIN actores act_out ON e.checkout_por_actor_id = act_out.id
                LEFT JOIN actores act_anul ON e.anulada_por_actor_id = act_anul.id
                WHERE e.reserva_id = :reserva_id
                ORDER BY e.id ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':reserva_id', $reservaId, PDO::PARAM_INT);
        $stmt->execute();

        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $estadias = [];

        foreach ($filas as $fila) {
            $estadia = Estadia::desdeArreglo($fila);
            $huespedes = $this->obtenerHuespedesPorEstadiaId((int) $fila['id']);
            $estadia->asignarHuespedes($huespedes);
            $estadias[] = $estadia;
        }

        return $estadias;
    }

    /**
     * Verifica si existe un código de estadía en la base de datos.
     */
    public function existeCodigo(string $codigo, ?int $excluirId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM estadias WHERE codigo = :codigo';
        if ($excluirId !== null) {
            $sql .= ' AND id != :excluir_id';
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
     * Genera un código único para la estadía en formato 'EST-YYYYMMDD-XXXX'.
     */
    public function generarSiguienteCodigo(): string
    {
        $prefijo = 'EST-' . date('Ymd') . '-';
        do {
            $aleatorio = strtoupper(bin2hex(random_bytes(2)));
            $codigo = $prefijo . $aleatorio;
        } while ($this->existeCodigo($codigo));

        return $codigo;
    }

    /**
     * Construye dinámicamente la cláusula WHERE y parámetros para listados.
     *
     * @param array<string, mixed> $filtros
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function construirWhereFiltros(array $filtros): array
    {
        $condiciones = ['1=1'];
        $params = [];

        if (!empty($filtros['estado'])) {
            $condiciones[] = 'e.estado = :estado';
            $params[':estado'] = trim(strtoupper((string) $filtros['estado']));
        }

        if (!empty($filtros['reserva_id'])) {
            $condiciones[] = 'e.reserva_id = :reserva_id';
            $params[':reserva_id'] = (int) $filtros['reserva_id'];
        }

        if (!empty($filtros['unidad_id'])) {
            $condiciones[] = 'e.unidad_id = :unidad_id';
            $params[':unidad_id'] = (int) $filtros['unidad_id'];
        }

        if (!empty($filtros['propiedad_id'])) {
            $condiciones[] = 'u.propiedad_id = :propiedad_id';
            $params[':propiedad_id'] = (int) $filtros['propiedad_id'];
        }

        if (!empty($filtros['fecha_entrada'])) {
            $condiciones[] = 'e.fecha_entrada >= :fecha_entrada';
            $params[':fecha_entrada'] = trim((string) $filtros['fecha_entrada']);
        }

        if (!empty($filtros['fecha_salida_prevista'])) {
            $condiciones[] = 'e.fecha_salida_prevista <= :fecha_salida_prevista';
            $params[':fecha_salida_prevista'] = trim((string) $filtros['fecha_salida_prevista']);
        }

        if (!empty($filtros['termino'])) {
            $condiciones[] = '(e.codigo LIKE :termino OR r.codigo LIKE :termino OR u.codigo LIKE :termino OR pt.nombres LIKE :termino OR pt.apellido_paterno LIKE :termino)';
            $params[':termino'] = '%' . trim((string) $filtros['termino']) . '%';
        }

        return [implode(' AND ', $condiciones), $params];
    }
}
