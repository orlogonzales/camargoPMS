<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios\CPE\Validacion;

use CamargoPMS\Excepciones\EsquemaCpeNoDisponibleExcepcion;
use CamargoPMS\Excepciones\IntegridadEsquemaCpeExcepcion;
use CamargoPMS\Nucleo\Configuracion;

/**
 * Proveedor local y soberano de esquemas XSD OASIS UBL 2.1 / SUNAT.
 *
 * Resuelve y valida esquemas almacenados en el sistema de archivos local,
 * garantizando resolución canónica sin acceso a red, aislamiento frente a
 * path traversal y verificación de integridad SHA-256 contra el manifiesto.
 */
class ProveedorEsquemasCpeLocal implements ProveedorEsquemasCpeInterfaz
{
    private string $directorioBase;
    /** @var array<string, bool> */
    private array $esquemasVerificados = [];

    /**
     * @param string|null $directorioBase Ruta base de esquemas. Si es null, lee de configuración o usa valor predeterminado seguro en storage/
     */
    public function __construct(?string $directorioBase = null)
    {
        if ($directorioBase !== null && trim($directorioBase) !== '') {
            $this->directorioBase = rtrim($directorioBase, '/\\');
        } else {
            $configDir = (string) Configuracion::obtener('CPE_ESQUEMAS_DIR', '');
            if ($configDir !== '') {
                $this->directorioBase = rtrim($configDir, '/\\');
            } else {
                $this->directorioBase = dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cpe' . DIRECTORY_SEPARATOR . 'esquemas' . DIRECTORY_SEPARATOR . '2.1';
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerRutaEsquema(string $tipoComprobante): string
    {
        $relRaiz = ManifiestoEsquemasUbl21::obtenerEsquemaRaizParaTipoCpe($tipoComprobante);
        $this->verificarClausuraParaTipoCpe($tipoComprobante);

        $rutaAbsoluta = $this->resolverRutaSegura($relRaiz);
        if (!file_exists($rutaAbsoluta)) {
            throw new EsquemaCpeNoDisponibleExcepcion(
                "El esquema raíz para tipo CPE '{$tipoComprobante}' no existe en disco: {$relRaiz}",
                $relRaiz
            );
        }

        return $rutaAbsoluta;
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerVersionNormativa(): string
    {
        return ManifiestoEsquemasUbl21::VERSION_NORMATIVA;
    }

    /**
     * Retorna la ruta base configurada para este proveedor.
     */
    public function obtenerDirectorioBase(): string
    {
        return $this->directorioBase;
    }

    /**
     * {@inheritdoc}
     */
    public function verificarIntegridad(): bool
    {
        $lista = ManifiestoEsquemasUbl21::obtenerListaEsquemas();
        foreach ($lista as $relPath) {
            $this->verificarArchivoIndividual($relPath);
        }
        return true;
    }

    /**
     * Verifica la integridad de todos los esquemas en el cierre de dependencias de un tipo de CPE.
     */
    private function verificarClausuraParaTipoCpe(string $tipoComprobante): void
    {
        $clausura = ManifiestoEsquemasUbl21::obtenerClausuraDependenciasParaTipoCpe($tipoComprobante);
        foreach ($clausura as $relPath) {
            if (!isset($this->esquemasVerificados[$relPath])) {
                $this->verificarArchivoIndividual($relPath);
                $this->esquemasVerificados[$relPath] = true;
            }
        }
    }

    /**
     * Resuelve y audita la integridad de un archivo individual contra el manifiesto.
     */
    private function verificarArchivoIndividual(string $relPath): void
    {
        $rutaAbsoluta = $this->resolverRutaSegura($relPath);

        if (!file_exists($rutaAbsoluta)) {
            throw new EsquemaCpeNoDisponibleExcepcion(
                "Esquema requerido no disponible en almacenamiento local: {$relPath}",
                $relPath
            );
        }

        $meta = ManifiestoEsquemasUbl21::obtenerMetadatos($relPath);
        if ($meta === null) {
            throw new IntegridadEsquemaCpeExcepcion(
                "Esquema '{$relPath}' no reconocido en el manifiesto oficial",
                $relPath
            );
        }

        $tamanoActual = filesize($rutaAbsoluta);
        if ($tamanoActual !== $meta['bytes']) {
            throw new IntegridadEsquemaCpeExcepcion(
                "Discrepancia en tamaño de esquema '{$relPath}'. Esperado: {$meta['bytes']} bytes, actual: {$tamanoActual} bytes",
                $relPath
            );
        }

        $hashActual = hash_file('sha256', $rutaAbsoluta);
        if (!hash_equals($meta['sha256'], (string) $hashActual)) {
            throw new IntegridadEsquemaCpeExcepcion(
                "Huella SHA-256 no coincide para esquema '{$relPath}'",
                $relPath,
                $meta['sha256'],
                (string) $hashActual
            );
        }
    }

    /**
     * Resuelve una ruta relativa asegurando que no escape del directorio base (Anti-Traversal).
     */
    private function resolverRutaSegura(string $relPath): string
    {
        // Rechazo inmediato de caracteres de traversal o URLs
        if (str_contains($relPath, '..') || str_starts_with($relPath, '/') || str_starts_with($relPath, '\\') || preg_match('/^[a-zA-Z]:/', $relPath) || preg_match('/^[a-zA-Z0-9]+:\/\//', $relPath)) {
            throw new IntegridadEsquemaCpeExcepcion(
                "Intento de escape de directorio o ruta no permitida en esquema: {$relPath}",
                $relPath
            );
        }

        $dirBaseReal = realpath($this->directorioBase);
        if ($dirBaseReal === false) {
            // El directorio base aún no existe físicamente en disco
            return $this->directorioBase . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath);
        }

        $rutaCompleta = $dirBaseReal . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath);
        $realCompleta = realpath($rutaCompleta);

        if ($realCompleta !== false) {
            // Verifica que la ruta canónica final sea hija estricta del directorio base
            $prefijoRequerido = rtrim($dirBaseReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            if (!str_starts_with($realCompleta, $prefijoRequerido)) {
                throw new IntegridadEsquemaCpeExcepcion(
                    "Ruta resuelta escapa del directorio base confiable: {$relPath}",
                    $relPath
                );
            }
            return $realCompleta;
        }

        return $rutaCompleta;
    }
}
