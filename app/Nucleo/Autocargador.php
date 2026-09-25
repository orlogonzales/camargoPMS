<?php

declare(strict_types=1);

namespace CamargoPMS\Nucleo;

/**
 * Autocargador PSR-4 mínimo para el espacio de nombres CamargoPMS.
 *
 * Mapea el espacio de nombres base `CamargoPMS\` directamente al directorio `app/`.
 * Permite la ejecución limpia sin dependencias externas en la fase UI-0,
 * preservando la decisión P-002 abierta para la fase de infraestructura.
 */
final class Autocargador
{
    /**
     * Prefijo del espacio de nombres del proyecto.
     */
    private const PREFIJO = 'CamargoPMS\\';

    /**
     * Ruta base absoluta hacia el directorio app/.
     */
    private string $directorioBase;

    /**
     * Constructor del autocargador.
     *
     * @param string $directorioBase Ruta absoluta hacia la carpeta app.
     */
    public function __construct(string $directorioBase)
    {
        $this->directorioBase = rtrim($directorioBase, '/\\') . DIRECTORY_SEPARATOR;
    }

    /**
     * Registra el autocargador en la pila SPL de PHP.
     *
     * @return void
     */
    public function registrar(): void
    {
        spl_autoload_register([$this, 'cargarClase']);
    }

    /**
     * Carga el archivo de la clase solicitada si pertenece al espacio de nombres.
     *
     * @param string $clase Nombre completo y cualificado de la clase.
     * @return bool Verdadero si el archivo se cargó exitosamente; falso en caso contrario.
     */
    public function cargarClase(string $clase): bool
    {
        $longitudPrefijo = strlen(self::PREFIJO);

        if (strncmp(self::PREFIJO, $clase, $longitudPrefijo) !== 0) {
            return false;
        }

        $claseRelativa = substr($clase, $longitudPrefijo);
        $archivo = $this->directorioBase . str_replace('\\', DIRECTORY_SEPARATOR, $claseRelativa) . '.php';

        if (file_exists($archivo)) {
            require_once $archivo;
            return true;
        }

        return false;
    }
}
