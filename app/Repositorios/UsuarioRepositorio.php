<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\Usuario;
use CamargoPMS\Nucleo\BaseDatos;
use PDO;

/**
 * Repositorio de persistencia para cuentas de usuario humano (tabla `usuarios`).
 *
 * Utiliza consultas preparadas con PDO sin interpolación directa.
 */
class UsuarioRepositorio
{
    private PDO $pdo;
    private ?PersonaRepositorio $personaRepo;

    public function __construct(?PDO $pdo = null, ?PersonaRepositorio $personaRepo = null)
    {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->personaRepo = $personaRepo ?? new PersonaRepositorio($this->pdo);
    }

    /**
     * Busca un usuario por su identificador primario.
     *
     * @param int $id
     * @param bool $cargarPersona
     * @return Usuario|null
     */
    public function buscarPorId(int $id, bool $cargarPersona = true): ?Usuario
    {
        $sql = 'SELECT * FROM usuarios WHERE id = :id LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch();
        if (!$fila) {
            return null;
        }

        $usuario = Usuario::desdeArreglo($fila);
        if ($cargarPersona && $usuario->obtenerPersonaId() > 0) {
            $usuario->asignarPersona($this->personaRepo->buscarPorId($usuario->obtenerPersonaId(), true));
        }

        return $usuario;
    }

    /**
     * Busca el usuario asociado a una persona específica (cardinalidad 1:1 lógica).
     *
     * @param int $personaId
     * @param bool $cargarPersona
     * @return Usuario|null
     */
    public function buscarPorPersonaId(int $personaId, bool $cargarPersona = true): ?Usuario
    {
        $sql = 'SELECT * FROM usuarios WHERE persona_id = :persona_id LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':persona_id', $personaId, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch();
        if (!$fila) {
            return null;
        }

        $usuario = Usuario::desdeArreglo($fila);
        if ($cargarPersona) {
            $usuario->asignarPersona($this->personaRepo->buscarPorId($personaId, true));
        }

        return $usuario;
    }

    /**
     * Busca un usuario por su nombre de usuario normalizado.
     *
     * @param string $nombreUsuario
     * @param bool $cargarPersona
     * @return Usuario|null
     */
    public function buscarPorNombreUsuario(string $nombreUsuario, bool $cargarPersona = true): ?Usuario
    {
        $sql = 'SELECT * FROM usuarios WHERE nombre_usuario = :nombre_usuario LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':nombre_usuario', trim($nombreUsuario), PDO::PARAM_STR);
        $stmt->execute();

        $fila = $stmt->fetch();
        if (!$fila) {
            return null;
        }

        $usuario = Usuario::desdeArreglo($fila);
        if ($cargarPersona && $usuario->obtenerPersonaId() > 0) {
            $usuario->asignarPersona($this->personaRepo->buscarPorId($usuario->obtenerPersonaId(), true));
        }

        return $usuario;
    }

    /**
     * Inserta un nuevo usuario en la base de datos.
     *
     * @param Usuario $usuario
     * @return Usuario
     */
    public function insertar(Usuario $usuario): Usuario
    {
        $sql = 'INSERT INTO usuarios (
                    persona_id,
                    nombre_usuario,
                    contrasena_hash,
                    estado,
                    ultimo_acceso_en,
                    contrasena_cambiada_en,
                    creado_en,
                    actualizado_en
                ) VALUES (
                    :persona_id,
                    :nombre_usuario,
                    :contrasena_hash,
                    :estado,
                    :ultimo_acceso_en,
                    :contrasena_cambiada_en,
                    NOW(),
                    NOW()
                )';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':persona_id', $usuario->obtenerPersonaId(), PDO::PARAM_INT);
        $stmt->bindValue(':nombre_usuario', $usuario->obtenerNombreUsuario(), PDO::PARAM_STR);
        $stmt->bindValue(':contrasena_hash', $usuario->obtenerContrasenaHash(), PDO::PARAM_STR);
        $stmt->bindValue(':estado', $usuario->obtenerEstado(), PDO::PARAM_STR);
        $stmt->bindValue(':ultimo_acceso_en', $usuario->obtenerUltimoAccesoEn(), PDO::PARAM_STR);
        $stmt->bindValue(':contrasena_cambiada_en', $usuario->obtenerContrasenaCambiadaEn(), PDO::PARAM_STR);
        $stmt->execute();

        $nuevoId = (int) $this->pdo->lastInsertId();
        return $this->buscarPorId($nuevoId, true) ?? $usuario;
    }

    /**
     * Actualiza el hash de la contraseña de un usuario y registra la fecha de cambio.
     *
     * @param int $id
     * @param string $nuevoHash
     * @return bool
     */
    public function actualizarHashContrasena(int $id, string $nuevoHash): bool
    {
        $sql = 'UPDATE usuarios
                SET contrasena_hash = :hash,
                    contrasena_cambiada_en = NOW(),
                    actualizado_en = NOW()
                WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':hash', $nuevoHash, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * Actualiza la fecha y hora del último acceso exitoso del usuario.
     *
     * @param int $id
     * @param string|null $fechaHora Formato 'Y-m-d H:i:s' o null para NOW()
     * @return bool
     */
    public function actualizarUltimoAcceso(int $id, ?string $fechaHora = null): bool
    {
        $sql = 'UPDATE usuarios
                SET ultimo_acceso_en = COALESCE(:fecha_hora, NOW()),
                    actualizado_en = NOW()
                WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':fecha_hora', $fechaHora, $fechaHora !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * Actualiza el estado administrativo del usuario ('ACTIVO', 'BLOQUEADO', 'INACTIVO').
     *
     * @param int $id
     * @param string $nuevoEstado
     * @return bool
     */
    public function cambiarEstado(int $id, string $nuevoEstado): bool
    {
        $sql = 'UPDATE usuarios
                SET estado = :estado,
                    actualizado_en = NOW()
                WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':estado', $nuevoEstado, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }
}
