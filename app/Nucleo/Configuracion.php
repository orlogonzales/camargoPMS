<?php

declare(strict_types=1);

namespace CamargoPMS\Nucleo;

use Dotenv\Dotenv;
use RuntimeException;

/**
 * Gestor centralizado de configuración y variables de entorno de Camargo PMS.
 *
 * Carga de forma segura el archivo .env utilizando vlucas/phpdotenv y provee
 * acceso controlado a los parámetros del sistema sin dispersar lecturas directas.
 */
final class Configuracion
{
    /** @var array<string, mixed> */
    private static array $valores = [];
    private static bool $iniciado = false;

    /**
     * Inicializa y carga las variables de entorno desde la ruta raíz.
     *
     * @param string $rutaRaiz Directorio raíz donde reside .env
     * @return void
     */
    public static function cargar(string $rutaRaiz): void
    {
        if (self::$iniciado) {
            return;
        }

        $archivoEnv = rtrim($rutaRaiz, '/\\') . DIRECTORY_SEPARATOR . '.env';

        if (file_exists($archivoEnv)) {
            $dotenv = Dotenv::createImmutable($rutaRaiz);
            $dotenv->safeLoad();
        }

        // Carga valores predeterminados seguros combinados con $_ENV / getenv()
        self::$valores = [
            'APP_ENV' => $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'production',
            'APP_DEBUG' => filter_var($_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOLEAN),
            'APP_URL' => rtrim((string) ($_ENV['APP_URL'] ?? getenv('APP_URL') ?: ''), '/'),
            'APP_TIMEZONE' => (string) ($_ENV['APP_TIMEZONE'] ?? getenv('APP_TIMEZONE') ?: 'America/Lima'),

            // Parámetros de base de datos
            'DB_HOST' => (string) ($_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1'),
            'DB_PORT' => (int) ($_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: 3306),
            'DB_DATABASE' => (string) ($_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: 'camargo_pms'),
            'DB_USERNAME' => (string) ($_ENV['DB_USERNAME'] ?? getenv('DB_USERNAME') ?: 'root'),
            'DB_PASSWORD' => (string) ($_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: ''),
            'DB_CHARSET' => (string) ($_ENV['DB_CHARSET'] ?? getenv('DB_CHARSET') ?: 'utf8mb4'),
            'DB_COLLATION' => (string) ($_ENV['DB_COLLATION'] ?? getenv('DB_COLLATION') ?: 'utf8mb4_0900_ai_ci'),
        ];

        // Establece la zona horaria técnica base
        date_default_timezone_set(self::$valores['APP_TIMEZONE']);

        self::$iniciado = true;
    }

    /**
     * Obtiene un parámetro de configuración.
     *
     * @param string $clave Clave del parámetro (ej. "APP_ENV", "DB_HOST").
     * @param mixed $predeterminado Valor por defecto si no existe la clave.
     * @return mixed
     */
    public static function obtener(string $clave, mixed $predeterminado = null): mixed
    {
        return self::$valores[$clave] ?? $predeterminado;
    }

    /**
     * Indica si la aplicación se ejecuta en entorno local/desarrollo.
     *
     * @return bool
     */
    public static function esLocal(): bool
    {
        return self::obtener('APP_ENV') === 'local';
    }

    /**
     * Indica si el modo depuración detallada está activo.
     *
     * @return bool
     */
    public static function modoDebug(): bool
    {
        return (bool) self::obtener('APP_DEBUG', false);
    }

    /**
     * Devuelve la configuración de conexión a base de datos.
     *
     * @return array<string, mixed>
     */
    public static function obtenerBaseDatos(): array
    {
        return [
            'host' => self::obtener('DB_HOST'),
            'port' => self::obtener('DB_PORT'),
            'database' => self::obtener('DB_DATABASE'),
            'username' => self::obtener('DB_USERNAME'),
            'password' => self::obtener('DB_PASSWORD'),
            'charset' => self::obtener('DB_CHARSET'),
            'collation' => self::obtener('DB_COLLATION'),
        ];
    }
}
