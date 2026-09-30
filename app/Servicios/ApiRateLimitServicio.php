<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

/**
 * Servicio autónomo de Rate Limiting con ventana deslizante de 60 segundos.
 *
 * Utiliza exclusión mutua a nivel de archivo (flock) en storage/cache/rate_limits/,
 * garantizando O(1), atomicidad concurrente y desacoplamiento absoluto de base de datos o Redis.
 */
class ApiRateLimitServicio
{
    private string $directorioAlmacenamiento;

    public function __construct(?string $directorioAlmacenamiento = null)
    {
        $this->directorioAlmacenamiento = $directorioAlmacenamiento ?? (dirname(__DIR__, 2) . '/storage/cache/rate_limits');
        if (!is_dir($this->directorioAlmacenamiento)) {
            @mkdir($this->directorioAlmacenamiento, 0755, true);
        }
    }

    /**
     * Evalúa y registra el consumo de una petición dentro de la ventana de 60 segundos.
     *
     * @param string $identificador Clave del cliente o IP (ej. "cliente:1" o "ip:192.168.1.1")
     * @param int $limitePorMinuto Cantidad máxima de peticiones permitidas por minuto
     * @return array{
     *     permitido: bool,
     *     limite: int,
     *     restantes: int,
     *     reset: int,
     *     retry_after: int
     * }
     */
    public function verificarYConsumir(string $identificador, int $limitePorMinuto = 60): array
    {
        if ($limitePorMinuto <= 0) {
            $limitePorMinuto = 60;
        }

        $archivo = $this->directorioAlmacenamiento . '/' . hash('sha256', $identificador) . '.json';
        $fp = @fopen($archivo, 'c+');

        if (!$fp) {
            // Si no se puede abrir el archivo, por degradación elegante se permite la petición
            return [
                'permitido' => true,
                'limite' => $limitePorMinuto,
                'restantes' => $limitePorMinuto - 1,
                'reset' => time() + 60,
                'retry_after' => 0,
            ];
        }

        flock($fp, LOCK_EX);

        $ahora = time();
        $ventanaInicio = $ahora - 60;

        $contenido = '';
        while (!feof($fp)) {
            $contenido .= fread($fp, 8192);
        }

        $peticiones = [];
        if ($contenido !== '') {
            $datos = json_decode($contenido, true);
            if (is_array($datos)) {
                $peticiones = $datos;
            }
        }

        // Filtrar peticiones fuera de la ventana de 60 segundos
        $peticionesValidas = array_values(array_filter($peticiones, function ($marca) use ($ventanaInicio) {
            return is_int($marca) && $marca > $ventanaInicio;
        }));

        $totalEnVentana = count($peticionesValidas);

        if ($totalEnVentana >= $limitePorMinuto) {
            // Límite excedido
            $masAntiguo = !empty($peticionesValidas) ? min($peticionesValidas) : $ahora;
            $retryAfter = max(1, 60 - ($ahora - $masAntiguo));
            $reset = $masAntiguo + 60;

            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, (string) json_encode($peticionesValidas));
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);

            return [
                'permitido' => false,
                'limite' => $limitePorMinuto,
                'restantes' => 0,
                'reset' => $reset,
                'retry_after' => $retryAfter,
            ];
        }

        // Permitido: registrar petición actual
        $peticionesValidas[] = $ahora;
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, (string) json_encode($peticionesValidas));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        $restantes = max(0, $limitePorMinuto - count($peticionesValidas));
        $reset = $ahora + 60;

        return [
            'permitido' => true,
            'limite' => $limitePorMinuto,
            'restantes' => $restantes,
            'reset' => $reset,
            'retry_after' => 0,
        ];
    }

    public function limpiar(string $identificador): void
    {
        $archivo = $this->directorioAlmacenamiento . '/' . hash('sha256', $identificador) . '.json';
        if (file_exists($archivo)) {
            @unlink($archivo);
        }
    }

    public function limpiarTodo(): void
    {
        if (is_dir($this->directorioAlmacenamiento)) {
            $archivos = glob($this->directorioAlmacenamiento . '/*.json');
            if (is_array($archivos)) {
                foreach ($archivos as $archivo) {
                    @unlink($archivo);
                }
            }
        }
    }
}
