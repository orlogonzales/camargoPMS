<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\SesionUsuario;
use CamargoPMS\Nucleo\BaseDatos;
use PDO;

/**
 * Repositorio de persistencia para sesiones de usuario (tabla `sesiones_usuario`).
 *
 * Administra el almacenamiento seguro de hashes de tokens, actividad y revocación.
 */
class SesionUsuarioRepositorio
{
    private PDO $pdo;
    private ?UsuarioRepositorio $usuarioRepo;

    public function __construct(?PDO $pdo = null, ?UsuarioRepositorio $usuarioRepo = null)
    {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->usuarioRepo = $usuarioRepo ?? new UsuarioRepositorio($this->pdo);
    }

    /**
     * Busca una sesión por el hash SHA-256 de su token criptográfico.
     *
     * @param string $tokenHash
     * @param bool $cargarUsuario
     * @return SesionUsuario|null
     */
    public function buscarPorTokenHash(string $tokenHash, bool $cargarUsuario = true): ?SesionUsuario
    {
        $sql = 'SELECT * FROM sesiones_usuario WHERE token_hash = :token_hash LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':token_hash', trim($tokenHash), PDO::PARAM_STR);
        $stmt->execute();

        $fila = $stmt->fetch();
        if (!$fila) {
            return null;
        }

        $sesion = SesionUsuario::desdeArreglo($fila);
        if ($cargarUsuario && $sesion->obtenerUsuarioId() > 0) {
            $sesion->asignarUsuario($this->usuarioRepo->buscarPorId($sesion->obtenerUsuarioId(), true));
        }

        return $sesion;
    }

    /**
     * Busca una sesión por su identificador primario.
     *
     * @param int $id
     * @param bool $cargarUsuario
     * @return SesionUsuario|null
     */
    public function buscarPorId(int $id, bool $cargarUsuario = true): ?SesionUsuario
    {
        $sql = 'SELECT * FROM sesiones_usuario WHERE id = :id LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch();
        if (!$fila) {
            return null;
        }

        $sesion = SesionUsuario::desdeArreglo($fila);
        if ($cargarUsuario && $sesion->obtenerUsuarioId() > 0) {
            $sesion->asignarUsuario($this->usuarioRepo->buscarPorId($sesion->obtenerUsuarioId(), true));
        }

        return $sesion;
    }

    /**
     * Inserta una nueva sesión de usuario en la base de datos.
     *
     * @param SesionUsuario $sesion
     * @return SesionUsuario
     */
    public function insertar(SesionUsuario $sesion): SesionUsuario
    {
        $sql = 'INSERT INTO sesiones_usuario (
                    usuario_id,
                    token_hash,
                    iniciada_en,
                    ultima_actividad_en,
                    expira_en,
                    revocada_en,
                    motivo_cierre,
                    ip,
                    user_agent,
                    creado_en
                ) VALUES (
                    :usuario_id,
                    :token_hash,
                    :iniciada_en,
                    :ultima_actividad_en,
                    :expira_en,
                    :revocada_en,
                    :motivo_cierre,
                    :ip,
                    :user_agent,
                    NOW()
                )';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':usuario_id', $sesion->obtenerUsuarioId(), PDO::PARAM_INT);
        $stmt->bindValue(':token_hash', $sesion->obtenerTokenHash(), PDO::PARAM_STR);
        $stmt->bindValue(':iniciada_en', $sesion->obtenerIniciadaEn(), PDO::PARAM_STR);
        $stmt->bindValue(':ultima_actividad_en', $sesion->obtenerUltimaActividadEn(), PDO::PARAM_STR);
        $stmt->bindValue(':expira_en', $sesion->obtenerExpiraEn(), PDO::PARAM_STR);
        $stmt->bindValue(':revocada_en', $sesion->obtenerRevocadaEn(), PDO::PARAM_STR);
        $stmt->bindValue(':motivo_cierre', $sesion->obtenerMotivoCierre(), PDO::PARAM_STR);
        $stmt->bindValue(':ip', $sesion->obtenerIp(), PDO::PARAM_STR);
        $stmt->bindValue(':user_agent', $sesion->obtenerUserAgent(), PDO::PARAM_STR);
        $stmt->execute();

        $nuevoId = (int) $this->pdo->lastInsertId();
        return $this->buscarPorId($nuevoId, true) ?? $sesion;
    }

    /**
     * Actualiza la fecha/hora de la última actividad y renueva la fecha de expiración por inactividad.
     *
     * @param int $id
     * @param string $nuevaUltimaActividad Formato 'Y-m-d H:i:s'
     * @param string|null $nuevaExpiracion Formato 'Y-m-d H:i:s'
     * @return bool
     */
    public function actualizarUltimaActividad(int $id, string $nuevaUltimaActividad, ?string $nuevaExpiracion = null): bool
    {
        $sql = 'UPDATE sesiones_usuario
                SET ultima_actividad_en = :ultima_actividad,
                    expira_en = COALESCE(:nueva_expiracion, expira_en)
                WHERE id = :id AND revocada_en IS NULL';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':ultima_actividad', $nuevaUltimaActividad, PDO::PARAM_STR);
        $stmt->bindValue(':nueva_expiracion', $nuevaExpiracion, $nuevaExpiracion !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * Revoca o cierra una sesión individual específica.
     *
     * @param int $id
     * @param string $motivo
     * @param string|null $fechaHora
     * @return bool
     */
    public function revocar(int $id, string $motivo, ?string $fechaHora = null): bool
    {
        $sql = 'UPDATE sesiones_usuario
                SET revocada_en = COALESCE(:fecha_hora, NOW()),
                    motivo_cierre = :motivo
                WHERE id = :id AND revocada_en IS NULL';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':motivo', $motivo, PDO::PARAM_STR);
        $stmt->bindValue(':fecha_hora', $fechaHora, $fechaHora !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * Revoca todas las sesiones activas de un usuario determinado, con opción de excluir una (ej. sesión actual).
     *
     * @param int $usuarioId
     * @param string $motivo
     * @param int|null $exceptoSesionId
     * @param string|null $fechaHora
     * @return int Cantidad de sesiones revocadas.
     */
    public function revocarTodasDeUsuario(
        int $usuarioId,
        string $motivo,
        ?int $exceptoSesionId = null,
        ?string $fechaHora = null
    ): int {
        $sql = 'UPDATE sesiones_usuario
                SET revocada_en = COALESCE(:fecha_hora, NOW()),
                    motivo_cierre = :motivo
                WHERE usuario_id = :usuario_id
                  AND revocada_en IS NULL';

        if ($exceptoSesionId !== null) {
            $sql .= ' AND id != :excepto_id';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(':motivo', $motivo, PDO::PARAM_STR);
        $stmt->bindValue(':fecha_hora', $fechaHora, $fechaHora !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        if ($exceptoSesionId !== null) {
            $stmt->bindValue(':excepto_id', $exceptoSesionId, PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->rowCount();
    }

    /**
     * Lista todas las sesiones activas no expiradas de un usuario.
     *
     * @param int $usuarioId
     * @param string|null $ahora Formato 'Y-m-d H:i:s'
     * @return array<int, SesionUsuario>
     */
    public function listarActivasPorUsuario(int $usuarioId, ?string $ahora = null): array
    {
        $momento = $ahora ?? date('Y-m-d H:i:s');
        $sql = 'SELECT * FROM sesiones_usuario
                WHERE usuario_id = :usuario_id
                  AND revocada_en IS NULL
                  AND expira_en > :ahora
                ORDER BY ultima_actividad_en DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(':ahora', $momento, PDO::PARAM_STR);
        $stmt->execute();

        $sesiones = [];
        while ($fila = $stmt->fetch()) {
            $sesiones[] = SesionUsuario::desdeArreglo($fila);
        }

        return $sesiones;
    }

    /**
     * Lista global de sesiones de usuario con paginación, filtros de estado y precarga de usuarios/personas.
     *
     * @param array<string, mixed> $filtros
     * @param int $limite
     * @param int $pagina
     * @param int $minutosInactividad
     * @param int $horasDuracionMaxima
     * @param int $minutosPresencia
     * @return array<int, array<string, mixed>> Retorna arreglos de sesión formateados listos para interfaz o DTO
     */
    public function listarSesionesGlobales(
        array $filtros = [],
        int $limite = 20,
        int $pagina = 1,
        int $minutosInactividad = 30,
        int $horasDuracionMaxima = 12,
        int $minutosPresencia = 15
    ): array {
        $offset = max(0, ($pagina - 1) * $limite);
        $ahora = time();
        $limiteInactividad = date('Y-m-d H:i:s', $ahora - ($minutosInactividad * 60));
        $limiteAbsoluto = date('Y-m-d H:i:s', $ahora - ($horasDuracionMaxima * 3600));
        $limitePresencia = date('Y-m-d H:i:s', $ahora - ($minutosPresencia * 60));

        $sql = 'SELECT
                    s.id,
                    s.usuario_id,
                    s.iniciada_en,
                    s.ultima_actividad_en,
                    s.expira_en,
                    s.revocada_en,
                    s.motivo_cierre,
                    s.ip,
                    s.user_agent,
                    s.creado_en,
                    u.nombre_usuario,
                    u.estado AS usuario_estado,
                    p.nombres,
                    p.apellido_paterno,
                    p.apellido_materno,
                    (SELECT r.nombre
                     FROM usuarios_roles ur
                     JOIN roles r ON ur.rol_id = r.id
                     WHERE ur.usuario_id = u.id
                     LIMIT 1) AS rol_nombre
                FROM sesiones_usuario s
                JOIN usuarios u ON s.usuario_id = u.id
                JOIN personas p ON u.persona_id = p.id
                WHERE 1=1';

        $params = [];
        $this->aplicarFiltrosWhere($sql, $params, $filtros, $limiteInactividad, $limiteAbsoluto, $limitePresencia);

        $sql .= ' ORDER BY s.ultima_actividad_en DESC LIMIT :limite OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $clave => $valor) {
            $stmt->bindValue($clave, $valor, is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $resultados = [];
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $tsIniciada = strtotime((string) $fila['iniciada_en']);
            $tsUltimaActividad = strtotime((string) $fila['ultima_actividad_en']);

            // Derivación soberana del estado
            if ($fila['revocada_en'] !== null) {
                $estadoSesion = 'REVOCADA';
            } elseif (($ahora - $tsIniciada) > ($horasDuracionMaxima * 3600)) {
                $estadoSesion = 'EXPIRADA_ABSOLUTA';
            } elseif (($ahora - $tsUltimaActividad) > ($minutosInactividad * 60)) {
                $estadoSesion = 'EXPIRADA_INACTIVIDAD';
            } else {
                $estadoSesion = 'ACTIVA';
            }

            // Indicador de presencia reciente
            $presenciaReciente = ($estadoSesion === 'ACTIVA' && ($ahora - $tsUltimaActividad) <= ($minutosPresencia * 60))
                ? 'PRESENCIA_RECIENTE'
                : 'SIN_ACTIVIDAD_RECIENTE';

            $expiracionAbsoluta = date('Y-m-d H:i:s', $tsIniciada + ($horasDuracionMaxima * 3600));

            $resultados[] = [
                'id' => (int) $fila['id'],
                'usuario_id' => (int) $fila['usuario_id'],
                'iniciada_en' => (string) $fila['iniciada_en'],
                'ultima_actividad_en' => (string) $fila['ultima_actividad_en'],
                'expira_en' => (string) $fila['expira_en'],
                'expiracion_absoluta_en' => $expiracionAbsoluta,
                'revocada_en' => $fila['revocada_en'] ? (string) $fila['revocada_en'] : null,
                'motivo_cierre' => $fila['motivo_cierre'] ? (string) $fila['motivo_cierre'] : null,
                'ip' => $fila['ip'] ? (string) $fila['ip'] : null,
                'user_agent' => $fila['user_agent'] ? (string) $fila['user_agent'] : null,
                'creado_en' => (string) $fila['creado_en'],
                'estado_sesion' => $estadoSesion,
                'presencia_reciente' => $presenciaReciente,
                'esta_activa' => ($estadoSesion === 'ACTIVA'),
                'usuario' => [
                    'id' => (int) $fila['usuario_id'],
                    'nombre_usuario' => (string) $fila['nombre_usuario'],
                    'estado' => (string) $fila['usuario_estado'],
                    'rol_nombre' => $fila['rol_nombre'] ? (string) $fila['rol_nombre'] : 'Sin Rol',
                    'persona' => [
                        'nombre_completo' => trim(($fila['nombres'] ?? '') . ' ' . ($fila['apellido_paterno'] ?? '')),
                    ],
                ],
            ];
        }

        return $resultados;
    }

    /**
     * Cuenta el total de sesiones bajo los filtros especificados para control de paginación.
     *
     * @param array<string, mixed> $filtros
     * @param int $minutosInactividad
     * @param int $horasDuracionMaxima
     * @param int $minutosPresencia
     * @return int
     */
    public function contarSesionesGlobales(
        array $filtros = [],
        int $minutosInactividad = 30,
        int $horasDuracionMaxima = 12,
        int $minutosPresencia = 15
    ): int {
        $ahora = time();
        $limiteInactividad = date('Y-m-d H:i:s', $ahora - ($minutosInactividad * 60));
        $limiteAbsoluto = date('Y-m-d H:i:s', $ahora - ($horasDuracionMaxima * 3600));
        $limitePresencia = date('Y-m-d H:i:s', $ahora - ($minutosPresencia * 60));

        $sql = 'SELECT COUNT(*) FROM sesiones_usuario s
                JOIN usuarios u ON s.usuario_id = u.id
                JOIN personas p ON u.persona_id = p.id
                WHERE 1=1';

        $params = [];
        $this->aplicarFiltrosWhere($sql, $params, $filtros, $limiteInactividad, $limiteAbsoluto, $limitePresencia);

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $clave => $valor) {
            $stmt->bindValue($clave, $valor, is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * Obtiene el resumen de métricas de concurrencia y actividad en una única consulta agregada.
     *
     * @param int $minutosInactividad
     * @param int $horasDuracionMaxima
     * @param int $minutosPresencia
     * @return array<string, int>
     */
    public function obtenerResumenMetricas(
        int $minutosInactividad = 30,
        int $horasDuracionMaxima = 12,
        int $minutosPresencia = 15
    ): array {
        $ahora = time();
        $limiteInactividad = date('Y-m-d H:i:s', $ahora - ($minutosInactividad * 60));
        $limiteAbsoluto = date('Y-m-d H:i:s', $ahora - ($horasDuracionMaxima * 3600));
        $limitePresencia = date('Y-m-d H:i:s', $ahora - ($minutosPresencia * 60));

        $sql = 'SELECT
            COALESCE(SUM(CASE
                WHEN s.revocada_en IS NULL
                 AND s.ultima_actividad_en >= :lim_inact_1
                 AND s.iniciada_en >= :lim_abs_1
                THEN 1 ELSE 0 END), 0) AS sesiones_activas,
            COALESCE(SUM(CASE
                WHEN s.revocada_en IS NULL
                 AND s.ultima_actividad_en >= :lim_pres_1
                 AND s.iniciada_en >= :lim_abs_2
                THEN 1 ELSE 0 END), 0) AS actividad_reciente,
            COALESCE(SUM(CASE
                WHEN s.revocada_en IS NULL
                 AND (s.ultima_actividad_en < :lim_inact_2 OR s.iniciada_en < :lim_abs_3)
                THEN 1 ELSE 0 END), 0) AS sesiones_expiradas,
            COALESCE(SUM(CASE
                WHEN s.revocada_en IS NOT NULL
                THEN 1 ELSE 0 END), 0) AS sesiones_revocadas,
            COALESCE(SUM(CASE
                WHEN s.revocada_en >= CURDATE()
                THEN 1 ELSE 0 END), 0) AS revocadas_hoy,
            COUNT(DISTINCT CASE
                WHEN s.revocada_en IS NULL
                 AND s.ultima_actividad_en >= :lim_inact_3
                 AND s.iniciada_en >= :lim_abs_4
                THEN s.usuario_id ELSE NULL END) AS usuarios_activos_unicos,
            COUNT(DISTINCT CASE
                WHEN s.revocada_en IS NULL
                 AND s.ultima_actividad_en >= :lim_pres_2
                 AND s.iniciada_en >= :lim_abs_5
                THEN s.usuario_id ELSE NULL END) AS usuarios_recientes_unicos
        FROM sesiones_usuario s';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':lim_inact_1', $limiteInactividad, PDO::PARAM_STR);
        $stmt->bindValue(':lim_inact_2', $limiteInactividad, PDO::PARAM_STR);
        $stmt->bindValue(':lim_inact_3', $limiteInactividad, PDO::PARAM_STR);
        $stmt->bindValue(':lim_abs_1', $limiteAbsoluto, PDO::PARAM_STR);
        $stmt->bindValue(':lim_abs_2', $limiteAbsoluto, PDO::PARAM_STR);
        $stmt->bindValue(':lim_abs_3', $limiteAbsoluto, PDO::PARAM_STR);
        $stmt->bindValue(':lim_abs_4', $limiteAbsoluto, PDO::PARAM_STR);
        $stmt->bindValue(':lim_abs_5', $limiteAbsoluto, PDO::PARAM_STR);
        $stmt->bindValue(':lim_pres_1', $limitePresencia, PDO::PARAM_STR);
        $stmt->bindValue(':lim_pres_2', $limitePresencia, PDO::PARAM_STR);
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fila) {
            return [
                'sesiones_activas' => 0,
                'actividad_reciente' => 0,
                'sesiones_expiradas' => 0,
                'sesiones_revocadas' => 0,
                'revocadas_hoy' => 0,
                'usuarios_activos_unicos' => 0,
                'usuarios_recientes_unicos' => 0,
            ];
        }

        return [
            'sesiones_activas' => (int) $fila['sesiones_activas'],
            'actividad_reciente' => (int) $fila['actividad_reciente'],
            'sesiones_expiradas' => (int) $fila['sesiones_expiradas'],
            'sesiones_revocadas' => (int) $fila['sesiones_revocadas'],
            'revocadas_hoy' => (int) $fila['revocadas_hoy'],
            'usuarios_activos_unicos' => (int) $fila['usuarios_activos_unicos'],
            'usuarios_recientes_unicos' => (int) $fila['usuarios_recientes_unicos'],
        ];
    }

    /**
     * Marca en lote las sesiones que hayan caducado por inactividad o expiración absoluta.
     * Operación de mantenimiento idempotente.
     *
     * @param int $minutosInactividad
     * @param int $horasDuracionMaxima
     * @return int Cantidad de sesiones actualizadas.
     */
    public function marcarExpiradasLote(int $minutosInactividad = 30, int $horasDuracionMaxima = 12): int
    {
        $ahora = time();
        $limiteInactividad = date('Y-m-d H:i:s', $ahora - ($minutosInactividad * 60));
        $limiteAbsoluto = date('Y-m-d H:i:s', $ahora - ($horasDuracionMaxima * 3600));

        // 1. Expiración absoluta
        $sqlAbs = 'UPDATE sesiones_usuario
                   SET revocada_en = NOW(),
                       motivo_cierre = \'EXPIRACION_ABSOLUTA\'
                   WHERE revocada_en IS NULL
                     AND iniciada_en < :lim_abs';
        $stmtAbs = $this->pdo->prepare($sqlAbs);
        $stmtAbs->bindValue(':lim_abs', $limiteAbsoluto, PDO::PARAM_STR);
        $stmtAbs->execute();
        $afectadas = $stmtAbs->rowCount();

        // 2. Expiración por inactividad
        $sqlInact = 'UPDATE sesiones_usuario
                     SET revocada_en = NOW(),
                         motivo_cierre = \'EXPIRACION_INACTIVIDAD\'
                     WHERE revocada_en IS NULL
                       AND ultima_actividad_en < :lim_inact';
        $stmtInact = $this->pdo->prepare($sqlInact);
        $stmtInact->bindValue(':lim_inact', $limiteInactividad, PDO::PARAM_STR);
        $stmtInact->execute();
        $afectadas += $stmtInact->rowCount();

        return $afectadas;
    }

    /**
     * Construye dinámicamente las cláusulas WHERE y parámetros de filtrado.
     *
     * @param string $sql Referencia al string SQL
     * @param array<string, mixed> $params Referencia a parámetros PDO
     * @param array<string, mixed> $filtros
     * @param string $limiteInactividad
     * @param string $limiteAbsoluto
     * @param string $limitePresencia
     */
    private function aplicarFiltrosWhere(
        string &$sql,
        array &$params,
        array $filtros,
        string $limiteInactividad,
        string $limiteAbsoluto,
        string $limitePresencia
    ): void {
        $estado = strtoupper(trim((string) ($filtros['estado'] ?? '')));
        if ($estado === 'ACTIVAS' || $estado === 'ACTIVA') {
            $sql .= ' AND s.revocada_en IS NULL AND s.ultima_actividad_en >= :lim_inact AND s.iniciada_en >= :lim_abs';
            $params[':lim_inact'] = $limiteInactividad;
            $params[':lim_abs'] = $limiteAbsoluto;
        } elseif ($estado === 'EXPIRADAS' || $estado === 'EXPIRADA') {
            $sql .= ' AND s.revocada_en IS NULL AND (s.ultima_actividad_en < :lim_inact_exp OR s.iniciada_en < :lim_abs_exp)';
            $params[':lim_inact_exp'] = $limiteInactividad;
            $params[':lim_abs_exp'] = $limiteAbsoluto;
        } elseif ($estado === 'EXPIRADAS_INACTIVIDAD' || $estado === 'EXPIRADA_INACTIVIDAD') {
            $sql .= ' AND s.revocada_en IS NULL AND s.ultima_actividad_en < :lim_inact_in AND s.iniciada_en >= :lim_abs_in';
            $params[':lim_inact_in'] = $limiteInactividad;
            $params[':lim_abs_in'] = $limiteAbsoluto;
        } elseif ($estado === 'EXPIRADAS_ABSOLUTA' || $estado === 'EXPIRADA_ABSOLUTA') {
            $sql .= ' AND s.revocada_en IS NULL AND s.iniciada_en < :lim_abs_ab';
            $params[':lim_abs_ab'] = $limiteAbsoluto;
        } elseif ($estado === 'REVOCADAS' || $estado === 'REVOCADA') {
            $sql .= ' AND s.revocada_en IS NOT NULL';
        }

        $presencia = strtoupper(trim((string) ($filtros['presencia'] ?? '')));
        if ($presencia === 'PRESENCIA_RECIENTE') {
            $sql .= ' AND s.revocada_en IS NULL AND s.ultima_actividad_en >= :lim_pres AND s.iniciada_en >= :lim_abs_p';
            $params[':lim_pres'] = $limitePresencia;
            $params[':lim_abs_p'] = $limiteAbsoluto;
        } elseif ($presencia === 'SIN_ACTIVIDAD_RECIENTE') {
            $sql .= ' AND s.revocada_en IS NULL AND s.ultima_actividad_en < :lim_pres_sin AND s.ultima_actividad_en >= :lim_inact_sin AND s.iniciada_en >= :lim_abs_sin';
            $params[':lim_pres_sin'] = $limitePresencia;
            $params[':lim_inact_sin'] = $limiteInactividad;
            $params[':lim_abs_sin'] = $limiteAbsoluto;
        }

        if (!empty($filtros['usuario_id']) && is_numeric($filtros['usuario_id'])) {
            $sql .= ' AND s.usuario_id = :filtro_usuario_id';
            $params[':filtro_usuario_id'] = (int) $filtros['usuario_id'];
        }

        if (!empty($filtros['busqueda']) && is_string($filtros['busqueda'])) {
            $busq = '%' . trim($filtros['busqueda']) . '%';
            $sql .= ' AND (u.nombre_usuario LIKE :busq_1 OR p.nombres LIKE :busq_2 OR p.apellido_paterno LIKE :busq_3 OR s.ip LIKE :busq_4)';
            $params[':busq_1'] = $busq;
            $params[':busq_2'] = $busq;
            $params[':busq_3'] = $busq;
            $params[':busq_4'] = $busq;
        }
    }
}
