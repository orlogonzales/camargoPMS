<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Nucleo\BaseDatos;
use PDO;

/**
 * Repositorio de persistencia para el registro de intentos de autenticación.
 *
 * Soporta rate limiting y mitigación contra ataques de fuerza bruta por IP o username.
 */
class IntentoAutenticacionRepositorio
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? BaseDatos::conexion();
    }

    /**
     * Registra un intento de autenticación (exitoso o fallido).
     *
     * @param string $usernameNormalizado
     * @param string $ip
     * @param bool $exitoso
     * @return void
     */
    public function registrarIntento(string $usernameNormalizado, string $ip, bool $exitoso): void
    {
        $sql = 'INSERT INTO intentos_autenticacion (
                    nombre_usuario_normalizado,
                    ip,
                    exitoso,
                    intentado_en
                ) VALUES (
                    :username,
                    :ip,
                    :exitoso,
                    NOW()
                )';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':username', trim($usernameNormalizado), PDO::PARAM_STR);
        $stmt->bindValue(':ip', trim($ip), PDO::PARAM_STR);
        $stmt->bindValue(':exitoso', $exitoso ? 1 : 0, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Cuenta los intentos fallidos recientes dentro de una ventana temporal dada en segundos.
     * Considera la combinación de nombre de usuario normalizado o dirección IP.
     *
     * @param string $usernameNormalizado
     * @param string $ip
     * @param int $ventanaSegundos
     * @return int
     */
    public function contarIntentosFallidosRecientes(string $usernameNormalizado, string $ip, int $ventanaSegundos): int
    {
        $sql = 'SELECT COUNT(*) FROM intentos_autenticacion
                WHERE exitoso = 0
                  AND (nombre_usuario_normalizado = :username OR ip = :ip)
                  AND intentado_en >= DATE_SUB(NOW(), INTERVAL :ventana SECOND)';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':username', trim($usernameNormalizado), PDO::PARAM_STR);
        $stmt->bindValue(':ip', trim($ip), PDO::PARAM_STR);
        $stmt->bindValue(':ventana', $ventanaSegundos, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * Limpia registros de intentos más antiguos que el umbral configurado.
     *
     * @param int $segundosAntiguedad
     * @return int
     */
    public function limpiarAntiguos(int $segundosAntiguedad): int
    {
        $sql = 'DELETE FROM intentos_autenticacion
                WHERE intentado_en < DATE_SUB(NOW(), INTERVAL :antiguedad SECOND)';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':antiguedad', $segundosAntiguedad, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }
}
