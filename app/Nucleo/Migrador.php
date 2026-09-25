<?php

declare(strict_types=1);

namespace CamargoPMS\Nucleo;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Ejecutor CLI de migraciones incrementales de base de datos para Camargo PMS.
 *
 * Lee los archivos SQL versionados bajo SQL/migraciones/, verifica el historial
 * en la tabla técnica `migraciones` y ejecuta ordenadamente las pendientes.
 */
final class Migrador
{
    private PDO $pdo;
    private string $directorioMigraciones;
    private string $baseDatosObjetivo;

    /**
     * @param PDO|null $pdo Instancia PDO opcional; si es null usa BaseDatos::conexion().
     * @param string|null $directorioMigraciones Directorio de archivos SQL.
     */
    public function __construct(?PDO $pdo = null, ?string $directorioMigraciones = null)
    {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->directorioMigraciones = $directorioMigraciones ?? dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'SQL' . DIRECTORY_SEPARATOR . 'migraciones';
        $this->baseDatosObjetivo = Configuracion::obtener('DB_DATABASE', 'camargo_pms');
    }

    /**
     * Ejecuta todas las migraciones pendientes en orden alfanumérico.
     *
     * @return array{aplicadas: array<string>, pendientes: int, exito: bool, mensaje: string}
     */
    public function ejecutar(): array
    {
        $this->verificarSeguridadEntorno();
        $this->asegurarTablaControl();

        $archivos = $this->obtenerArchivosDisponibles();
        $aplicadas = $this->obtenerMigracionesAplicadas();

        $pendientes = array_diff($archivos, $aplicadas);
        sort($pendientes, SORT_NATURAL);

        if (empty($pendientes)) {
            return [
                'aplicadas' => [],
                'pendientes' => 0,
                'exito' => true,
                'mensaje' => 'La base de datos se encuentra al día. No hay migraciones pendientes.'
            ];
        }

        $lote = $this->obtenerSiguienteLote();
        $migracionesEjecutadas = [];

        foreach ($pendientes as $archivo) {
            $rutaCompleta = $this->directorioMigraciones . DIRECTORY_SEPARATOR . $archivo;
            $contenidoSql = file_get_contents($rutaCompleta);

            if ($contenidoSql === false || trim($contenidoSql) === '') {
                throw new RuntimeException("El archivo de migración {$archivo} está vacío o es ilegible.");
            }

            try {
                // Ejecución del script SQL de la migración
                $this->pdo->exec($contenidoSql);

                // Registro técnico de la migración aplicada
                $stmt = $this->pdo->prepare("INSERT INTO `migraciones` (`migracion`, `lote`, `ejecutado_en`) VALUES (:migracion, :lote, NOW())");
                $stmt->execute([
                    ':migracion' => $archivo,
                    ':lote' => $lote
                ]);

                $migracionesEjecutadas[] = $archivo;
            } catch (Throwable $error) {
                throw new RuntimeException("Error al ejecutar migración [{$archivo}]: " . $error->getMessage(), (int) $error->getCode(), $error);
            }
        }

        return [
            'aplicadas' => $migracionesEjecutadas,
            'pendientes' => 0,
            'exito' => true,
            'mensaje' => sprintf("Se ejecutaron con éxito %d migración(es) en el lote %d.", count($migracionesEjecutadas), $lote)
        ];
    }

    /**
     * Obtiene el listado de archivos .sql disponibles en el directorio de migraciones.
     *
     * @return array<string>
     */
    public function obtenerArchivosDisponibles(): array
    {
        if (!is_dir($this->directorioMigraciones)) {
            return [];
        }

        $elementos = scandir($this->directorioMigraciones) ?: [];
        $archivosSql = [];

        foreach ($elementos as $elemento) {
            if (str_ends_with($elemento, '.sql') && !str_starts_with($elemento, '.')) {
                $archivosSql[] = $elemento;
            }
        }

        sort($archivosSql, SORT_NATURAL);
        return $archivosSql;
    }

    /**
     * Consulta la lista de migraciones registradas previamente en la tabla técnica.
     *
     * @return array<string>
     */
    public function obtenerMigracionesAplicadas(): array
    {
        $stmt = $this->pdo->query("SELECT `migracion` FROM `migraciones` ORDER BY `id` ASC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    /**
     * Garantiza que la tabla técnica de control exista en la base de datos.
     *
     * @return void
     */
    private function asegurarTablaControl(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS `migraciones` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `migracion` VARCHAR(255) NOT NULL UNIQUE,
            `lote` INT UNSIGNED NOT NULL DEFAULT 1,
            `ejecutado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Control técnico de migraciones aplicadas en Camargo PMS'";

        $this->pdo->exec($sql);
    }

    /**
     * Verifica que la base de datos conectada coincida exactamente con la base configurada.
     *
     * @return void
     * @throws RuntimeException
     */
    private function verificarSeguridadEntorno(): void
    {
        $stmt = $this->pdo->query("SELECT DATABASE()");
        $bdActual = (string) $stmt->fetchColumn();

        if (strtolower($bdActual) !== strtolower($this->baseDatosObjetivo)) {
            throw new RuntimeException("Seguridad: La base de datos conectada ({$bdActual}) no coincide con la base esperada ({$this->baseDatosObjetivo}). Ejecución abortada.");
        }
    }

    /**
     * Calcula el identificador del siguiente lote de ejecución.
     *
     * @return int
     */
    private function obtenerSiguienteLote(): int
    {
        $stmt = $this->pdo->query("SELECT COALESCE(MAX(`lote`), 0) + 1 FROM `migraciones`");
        return (int) $stmt->fetchColumn();
    }
}
