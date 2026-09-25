<?php

declare(strict_types=1);

namespace CamargoPMS\Nucleo;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Fábrica centralizada de conexiones PDO para Camargo PMS.
 *
 * Administra una única instancia de conexión segura (patrón singleton)
 * configurada con excepciones estrictas, modo asociativo y consultas preparadas reales.
 */
final class BaseDatos
{
    private static ?PDO $instancia = null;

    /**
     * Constructor privado para impedir instanciación directa.
     */
    private function __construct()
    {
    }

    /**
     * Obtiene o inicializa la conexión PDO centralizada del sistema.
     *
     * @return PDO Conexión PDO activa y configurada.
     * @throws RuntimeException Si ocurre un error de conexión (sin filtrar credenciales).
     */
    public static function conexion(): PDO
    {
        if (self::$instancia !== null) {
            return self::$instancia;
        }

        $config = Configuracion::obtenerBaseDatos();

        $host = $config['host'];
        $port = $config['port'];
        $database = $config['database'];
        $charset = $config['charset'];
        $collation = $config['collation'];
        $username = $config['username'];
        $password = $config['password'];

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";

        $opciones = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES '{$charset}' COLLATE '{$collation}'",
        ];

        try {
            self::$instancia = new PDO($dsn, $username, $password, $opciones);
            return self::$instancia;
        } catch (PDOException $error) {
            // Protección de credenciales: registrar internamente sin filtrar contraseña en el mensaje al usuario
            $mensajeError = "Fallo de conexión a la base de datos de Camargo PMS.";

            if (Configuracion::modoDebug() && Configuracion::esLocal()) {
                $mensajeError .= " Detalle técnico local: " . $error->getMessage();
            }

            throw new RuntimeException($mensajeError, (int) $error->getCode(), $error);
        }
    }

    /**
     * Cierra explícitamente la conexión actual (útil para pruebas o reinicios de contexto).
     *
     * @return void
     */
    public static function desconectar(): void
    {
        self::$instancia = null;
    }
}
