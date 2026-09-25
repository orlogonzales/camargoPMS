<?php

declare(strict_types=1);

namespace CamargoPMS\Nucleo;

use RuntimeException;
use InvalidArgumentException;

/**
 * Motor de renderización seguro y desacoplado para vistas y plantillas de Camargo PMS.
 */
final class Vista
{
    private string $directorioVistas;

    /**
     * Constructor del motor de vistas.
     *
     * @param string|null $directorioVistas Ruta absoluta opcional a la carpeta de vistas.
     */
    public function __construct(?string $directorioVistas = null)
    {
        $this->directorioVistas = $directorioVistas
            ? rtrim($directorioVistas, '/\\') . DIRECTORY_SEPARATOR
            : dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Vistas' . DIRECTORY_SEPARATOR;
    }

    /**
     * Renderiza una vista dentro de una plantilla principal o directamente.
     *
     * @param string $vista Nombre relativo de la vista (ej. "panel/inicio" o "errores/404").
     * @param array<string, mixed> $datos Parámetros preparados por el controlador.
     * @param string|null $plantilla Nombre de la plantilla contenedora (por defecto "principal"), o null para vista aislada.
     * @return string Contenido HTML resultante.
     * @throws InvalidArgumentException Si el nombre de vista es inseguro o inválido.
     * @throws RuntimeException Si el archivo de vista o plantilla no existe.
     */
    public function renderizar(string $vista, array $datos = [], ?string $plantilla = 'principal'): string
    {
        $archivoVista = $this->resolverRutaSegura($vista);

        // Renderiza el contenido interno de la vista
        $contenidoVista = $this->evaluarArchivo($archivoVista, $datos);

        // Si no requiere plantilla contenedora, devuelve la vista pura
        if ($plantilla === null) {
            return $contenidoVista;
        }

        // Renderiza la plantilla envolvente pasando el contenido interno en $contenido
        $archivoPlantilla = $this->resolverRutaSegura('plantillas/' . $plantilla);
        $datosPlantilla = array_merge($datos, [
            'contenido' => $contenidoVista,
            'vistaActual' => $vista
        ]);

        return $this->evaluarArchivo($archivoPlantilla, $datosPlantilla);
    }

    /**
     * Renderiza un componente de interfaz reutilizable.
     *
     * @param string $nombre Nombre del componente relativo a Vistas/componentes/ (ej. "head" o "migas-pan").
     * @param array<string, mixed> $datos Variables locales para el componente.
     * @return string HTML generado por el componente.
     */
    public static function componente(string $nombre, array $datos = []): string
    {
        $instancia = new self();
        $archivo = $instancia->resolverRutaSegura('componentes/' . $nombre);
        return $instancia->evaluarArchivo($archivo, $datos);
    }

    /**
     * Resuelve y valida estrictamente una ruta para impedir ataques de inclusión o Directory Traversal.
     *
     * @param string $rutaRelativa
     * @return string
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    private function resolverRutaSegura(string $rutaRelativa): string
    {
        // Sanitiza eliminando caracteres no válidos o nulos
        if (str_contains($rutaRelativa, "\0") || str_contains($rutaRelativa, '..')) {
            throw new InvalidArgumentException("Intento de Directory Traversal detectado en vista: {$rutaRelativa}");
        }

        // Permitir solo caracteres alfanuméricos, barras, guiones y barras bajas
        if (!preg_match('/^[a-zA-Z0-9_\-\/]+$/', $rutaRelativa)) {
            throw new InvalidArgumentException("Nombre de vista con caracteres inválidos: {$rutaRelativa}");
        }

        $archivo = $this->directorioVistas . str_replace('/', DIRECTORY_SEPARATOR, $rutaRelativa) . '.php';

        if (!file_exists($archivo)) {
            throw new RuntimeException("Archivo de vista no encontrado: {$archivo}");
        }

        return $archivo;
    }

    /**
     * Evalúa el archivo PHP aislando su alcance de variables.
     *
     * @param string $archivo
     * @param array<string, mixed> $datos
     * @return string
     */
    private function evaluarArchivo(string $archivo, array $datos): string
    {
        extract($datos, EXTR_SKIP);

        ob_start();
        try {
            require $archivo;
            return (string) ob_get_clean();
        } catch (\Throwable $error) {
            ob_end_clean();
            throw $error;
        }
    }
}
