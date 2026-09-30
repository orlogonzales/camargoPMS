<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

use CamargoPMS\Excepciones\EntidadNoEncontradaExcepcion;
use CamargoPMS\Excepciones\ValidacionExcepcion;
use CamargoPMS\Nucleo\Ayudante;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Repositorios\PersonaRepositorio;
use PDO;
use Throwable;

/**
 * Servicio de almacenamiento y gestión segura de fotografías de Persona (UI-ALINA-1B-C1).
 *
 * Aplica los principios rectores de Camargo PMS:
 * 1. PERSONA ≠ USUARIO: La fotografía pertenece a la Persona natural soberana.
 * 2. Desacoplamiento de almacenamiento: Almacena referencias relativas (ej. 'avatars/<id>.jpg'),
 *    desacopladas de rutas físicas de despliegue y URLs de presentación.
 * 3. Reemplazo atómico y defensivo: Garantiza que un fallo en BD nunca destruya la foto previa
 *    y limpia archivos huérfanos.
 * 4. Seguridad estricta: Validación de MIME real binario (exclusivamente JPEG y PNG),
 *    restricción de tamaño a 2 MB, identificadores criptográficos y protección anti path traversal.
 */
class FotoPersonaServicio
{
    public const int TAMANO_MAXIMO_BYTES = 2097152; // 2 MB
    public const array MIMES_PERMITIDOS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];
    public const string RECURSO_FALLBACK_AVATAR = 'images/avatar/01.png';

    private PDO $pdo;
    private PersonaRepositorio $personaRepo;
    private string $storagePath;

    /**
     * @param PDO|null $pdo Instancia de base de datos opcional.
     * @param PersonaRepositorio|null $personaRepo Repositorio de personas opcional.
     * @param string|null $storagePath Directorio base de almacenamiento público (ej. public/storage).
     */
    public function __construct(
        ?PDO $pdo = null,
        ?PersonaRepositorio $personaRepo = null,
        ?string $storagePath = null
    ) {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->personaRepo = $personaRepo ?? new PersonaRepositorio($this->pdo);
        $this->storagePath = $storagePath ?? (dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'storage');
    }

    /**
     * Procesa la subida, validación, almacenamiento y actualización atómica de la fotografía de una persona.
     *
     * @param int $personaId Identificador de la Persona natural.
     * @param array<string, mixed> $archivo Estructura $_FILES de la imagen enviada.
     * @return string Referencia relativa guardada en BD (ej. 'avatars/avatar_xxxx.jpg').
     * @throws ValidacionExcepcion Si el archivo viola formato, tamaño o integridad.
     * @throws EntidadNoEncontradaExcepcion Si la persona no existe.
     * @throws Throwable En caso de error de persistencia.
     */
    public function procesarSubidaFoto(int $personaId, array $archivo): string
    {
        // 1. Verificar existencia de la Persona
        $persona = $this->personaRepo->buscarPorId($personaId, false);
        if ($persona === null) {
            throw new EntidadNoEncontradaExcepcion("La persona natural [{$personaId}] no existe en el sistema.");
        }

        $fotoAnterior = $persona->obtenerFotoRuta();

        // 2. Validación exhaustiva del archivo enviado
        $extension = $this->validarArchivoImagen($archivo);

        // 3. Generación de nombre criptográficamente seguro (no derivado del cliente)
        $identificadorSeguro = 'avatar_' . bin2hex(random_bytes(16));
        $nombreArchivo = $identificadorSeguro . '.' . $extension;
        $referenciaRelativa = 'avatars/' . $nombreArchivo;

        // 4. Asegurar existencia del directorio administrado de avatars
        $dirAvatars = $this->storagePath . DIRECTORY_SEPARATOR . 'avatars';
        if (!is_dir($dirAvatars)) {
            if (!mkdir($dirAvatars, 0755, true) && !is_dir($dirAvatars)) {
                throw new ValidacionExcepcion('No se pudo inicializar el directorio de almacenamiento de fotografías.');
            }
        }

        $rutaDestinoFisica = $dirAvatars . DIRECTORY_SEPARATOR . $nombreArchivo;

        // 5. Guardar archivo físico nuevo (move_uploaded_file con fallback a copy para CLI/testing)
        $guardado = false;
        if (!empty($archivo['tmp_name']) && is_uploaded_file($archivo['tmp_name'])) {
            $guardado = move_uploaded_file($archivo['tmp_name'], $rutaDestinoFisica);
        } elseif (!empty($archivo['tmp_name']) && file_exists($archivo['tmp_name'])) {
            $guardado = copy($archivo['tmp_name'], $rutaDestinoFisica);
        }

        if (!$guardado || !file_exists($rutaDestinoFisica)) {
            throw new ValidacionExcepcion('Error al transferir el archivo al almacenamiento de fotografías.');
        }

        // 6. Actualización atómica en base de datos
        try {
            $actualizado = $this->personaRepo->actualizarFotoRuta($personaId, $referenciaRelativa);
            if (!$actualizado) {
                throw new ValidacionExcepcion('No se pudo actualizar el registro de fotografía en la base de datos.');
            }
        } catch (Throwable $errorBd) {
            // Reemplazo atómico seguro: Si falla la BD, eliminar inmediatamente el archivo nuevo huérfano
            if (file_exists($rutaDestinoFisica)) {
                @unlink($rutaDestinoFisica);
            }
            throw $errorBd;
        }

        // 7. Si la persistencia fue exitosa, eliminar la fotografía anterior si era propia del sistema
        if ($fotoAnterior !== null && trim($fotoAnterior) !== '') {
            $this->eliminarArchivoFisicoSeguro($fotoAnterior);
        }

        return $referenciaRelativa;
    }

    /**
     * Elimina la fotografía de perfil de una persona, restableciendo la referencia a NULL.
     *
     * @param int $personaId
     * @return bool
     * @throws EntidadNoEncontradaExcepcion
     */
    public function eliminarFoto(int $personaId): bool
    {
        $persona = $this->personaRepo->buscarPorId($personaId, false);
        if ($persona === null) {
            throw new EntidadNoEncontradaExcepcion("La persona natural [{$personaId}] no existe.");
        }

        $fotoAnterior = $persona->obtenerFotoRuta();
        if ($fotoAnterior === null || trim($fotoAnterior) === '') {
            return true;
        }

        $actualizado = $this->personaRepo->actualizarFotoRuta($personaId, null);
        if ($actualizado) {
            $this->eliminarArchivoFisicoSeguro($fotoAnterior);
            return true;
        }

        return false;
    }

    /**
     * Resuelve la URL de presentación pública para una referencia de fotografía,
     * aplicando el fallback canónico si no tiene foto asignada.
     *
     * @param string|null $fotoRuta Referencia relativa persistida en BD.
     * @return string URL pública normalizada para navegadores.
     */
    public function resolverUrlFoto(?string $fotoRuta): string
    {
        if ($fotoRuta !== null && trim($fotoRuta) !== '') {
            return Ayudante::storage($fotoRuta, self::RECURSO_FALLBACK_AVATAR);
        }

        return Ayudante::asset(self::RECURSO_FALLBACK_AVATAR);
    }

    /**
     * Valida de manera estricta los metadatos y contenido binario de la imagen.
     *
     * @param array<string, mixed> $archivo
     * @return string Extensión canónica correspondiente ('jpg' o 'png').
     * @throws ValidacionExcepcion
     */
    private function validarArchivoImagen(array $archivo): string
    {
        if (empty($archivo) || !isset($archivo['tmp_name'])) {
            throw new ValidacionExcepcion('No se proporcionó ningún archivo de imagen para cargar.');
        }

        $errorUpload = (int) ($archivo['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorUpload !== UPLOAD_ERR_OK) {
            throw new ValidacionExcepcion(match ($errorUpload) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'El archivo supera el tamaño máximo permitido por el servidor.',
                UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente. Intente nuevamente.',
                UPLOAD_ERR_NO_FILE => 'No se seleccionó ningún archivo para subir.',
                default => 'Ocurrió un error al cargar el archivo de imagen.',
            });
        }

        $tamano = (int) ($archivo['size'] ?? 0);
        if ($tamano <= 0 || !file_exists($archivo['tmp_name'])) {
            throw new ValidacionExcepcion('El archivo de fotografía subido está vacío o es inválido.');
        }

        if ($tamano > self::TAMANO_MAXIMO_BYTES) {
            throw new ValidacionExcepcion('La fotografía supera el límite máximo permitido de 2 MB.');
        }

        // Inspección binaria segura del tipo MIME mediante Fileinfo (no confiar en $_FILES['type'])
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            throw new ValidacionExcepcion('No se pudo inicializar la verificación de tipos de archivo en el servidor.');
        }

        $mimeReal = finfo_file($finfo, $archivo['tmp_name']);
        finfo_close($finfo);

        if ($mimeReal === false || !isset(self::MIMES_PERMITIDOS[$mimeReal])) {
            throw new ValidacionExcepcion(
                "Tipo de archivo no permitido ({$mimeReal}). Se aceptan exclusivamente imágenes en formato JPG o PNG."
            );
        }

        return self::MIMES_PERMITIDOS[$mimeReal];
    }

    /**
     * Elimina físicamente un archivo de fotografía del almacenamiento verificando
     * que pertenezca estrictamente al directorio administrado de avatars (previene path traversal).
     *
     * @param string $referenciaRelativa Ej. 'avatars/avatar_xxx.jpg'.
     * @return bool
     */
    private function eliminarArchivoFisicoSeguro(string $referenciaRelativa): bool
    {
        // 1. Nunca eliminar el recurso neutro de fallback de Alina
        if (str_contains($referenciaRelativa, '01.png') || str_contains($referenciaRelativa, 'avatar/01.png')) {
            return false;
        }

        // 2. Extraer el nombre base para neutralizar cualquier intento de path traversal
        $nombreArchivo = basename(trim($referenciaRelativa));
        if ($nombreArchivo === '' || $nombreArchivo === '.' || $nombreArchivo === '..' || $nombreArchivo === '.gitkeep') {
            return false;
        }

        // 3. Resolver ruta absoluta dentro del directorio de avatars
        $dirAvatars = realpath($this->storagePath . DIRECTORY_SEPARATOR . 'avatars');
        if ($dirAvatars === false) {
            return false;
        }

        $rutaCompleta = $dirAvatars . DIRECTORY_SEPARATOR . $nombreArchivo;

        // 4. Verificar que el archivo esté estrictamente dentro de avatars
        $realRuta = realpath($rutaCompleta);
        if ($realRuta !== false && str_starts_with($realRuta, $dirAvatars)) {
            if (is_file($realRuta)) {
                return @unlink($realRuta);
            }
        }

        return false;
    }
}
