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
}
