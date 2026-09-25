<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\Persona;
use CamargoPMS\Nucleo\BaseDatos;
use PDO;

/**
 * Repositorio de persistencia para el maestro central de personas naturales.
 */
class PersonaRepositorio
{
    private PDO $pdo;
    private ?PaisRepositorio $paisRepo;
    private ?DocumentoPersonaRepositorio $documentoRepo;
    private ?ContactoPersonaRepositorio $contactoRepo;

    public function __construct(
        ?PDO $pdo = null,
        ?PaisRepositorio $paisRepo = null,
        ?DocumentoPersonaRepositorio $documentoRepo = null,
        ?ContactoPersonaRepositorio $contactoRepo = null
    ) {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->paisRepo = $paisRepo ?? new PaisRepositorio($this->pdo);
        $this->documentoRepo = $documentoRepo ?? new DocumentoPersonaRepositorio($this->pdo);
        $this->contactoRepo = $contactoRepo ?? new ContactoPersonaRepositorio($this->pdo);
    }

    /**
     * Busca una persona por su identificador primario.
     *
     * @param int $id
     * @param bool $cargarRelaciones Carga documentos, contactos y país asociado.
     * @return Persona|null
     */
    public function buscarPorId(int $id, bool $cargarRelaciones = true): ?Persona
    {
        $sql = "SELECT * FROM personas WHERE id = :id LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch();
        if (!$fila) {
            return null;
        }

        $persona = Persona::desdeArreglo($fila);

        if ($cargarRelaciones) {
            $this->hidratarRelaciones($persona);
        }

        return $persona;
    }

    /**
     * Busca una persona a través de un tipo y número de documento específico.
     *
     * @param string $codigoTipoDocumento Ej. 'DNI', 'PASAPORTE', 'CE'.
     * @param string $numeroDocumento
     * @param bool $cargarRelaciones
     * @return Persona|null
     */
    public function buscarPorDocumento(string $codigoTipoDocumento, string $numeroDocumento, bool $cargarRelaciones = true): ?Persona
    {
        $sql = "SELECT p.* FROM personas p
                INNER JOIN personas_documentos d ON d.persona_id = p.id
                INNER JOIN tipos_documento t ON t.id = d.tipo_documento_id
                WHERE t.codigo = :codigo_tipo AND d.numero_documento = :numero
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':codigo_tipo', strtoupper(trim($codigoTipoDocumento)), PDO::PARAM_STR);
        $stmt->bindValue(':numero', strtoupper(trim($numeroDocumento)), PDO::PARAM_STR);
        $stmt->execute();

        $fila = $stmt->fetch();
        if (!$fila) {
            return null;
        }

        $persona = Persona::desdeArreglo($fila);

        if ($cargarRelaciones) {
            $this->hidratarRelaciones($persona);
        }

        return $persona;
    }

    /**
     * Lista personas activas con paginación defensiva.
     *
     * @param int $limite
     * @param int $offset
     * @return array<int, Persona>
     */
    public function listarActivos(int $limite = 50, int $offset = 0): array
    {
        $sql = "SELECT * FROM personas 
                WHERE estado = 'ACTIVO' 
                ORDER BY apellido_paterno ASC, apellido_materno ASC, nombres ASC 
                LIMIT :limite OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limite', max(1, min(100, $limite)), PDO::PARAM_INT);
        $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();

        $resultado = [];
        while ($fila = $stmt->fetch()) {
            $persona = Persona::desdeArreglo($fila);
            $this->hidratarRelaciones($persona);
            $resultado[] = $persona;
        }

        return $resultado;
    }

    /**
     * Inserta una persona natural en la base de datos.
     *
     * @param Persona $persona
     * @return int ID de la persona generada.
     */
    public function insertar(Persona $persona): int
    {
        $sql = "INSERT INTO personas (
                    nombres, apellido_paterno, apellido_materno, 
                    fecha_nacimiento, pais_nacionalidad_id, direccion, estado
                ) VALUES (
                    :nombres, :apellido_paterno, :apellido_materno, 
                    :fecha_nacimiento, :pais_nacionalidad_id, :direccion, :estado
                )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':nombres', $persona->obtenerNombres(), PDO::PARAM_STR);
        $stmt->bindValue(':apellido_paterno', $persona->obtenerApellidoPaterno(), $persona->obtenerApellidoPaterno() ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':apellido_materno', $persona->obtenerApellidoMaterno(), $persona->obtenerApellidoMaterno() ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':fecha_nacimiento', $persona->obtenerFechaNacimiento(), $persona->obtenerFechaNacimiento() ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':pais_nacionalidad_id', $persona->obtenerPaisNacionalidadId(), $persona->obtenerPaisNacionalidadId() ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':direccion', $persona->obtenerDireccion(), $persona->obtenerDireccion() ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':estado', $persona->obtenerEstado(), PDO::PARAM_STR);
        $stmt->execute();

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Actualiza los datos de una persona natural existente.
     *
     * @param Persona $persona
     * @return bool
     */
    public function actualizar(Persona $persona): bool
    {
        if ($persona->obtenerId() === null) {
            return false;
        }

        $sql = "UPDATE personas SET 
                    nombres = :nombres,
                    apellido_paterno = :apellido_paterno,
                    apellido_materno = :apellido_materno,
                    fecha_nacimiento = :fecha_nacimiento,
                    pais_nacionalidad_id = :pais_nacionalidad_id,
                    direccion = :direccion,
                    estado = :estado
                WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $persona->obtenerId(), PDO::PARAM_INT);
        $stmt->bindValue(':nombres', $persona->obtenerNombres(), PDO::PARAM_STR);
        $stmt->bindValue(':apellido_paterno', $persona->obtenerApellidoPaterno(), $persona->obtenerApellidoPaterno() ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':apellido_materno', $persona->obtenerApellidoMaterno(), $persona->obtenerApellidoMaterno() ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':fecha_nacimiento', $persona->obtenerFechaNacimiento(), $persona->obtenerFechaNacimiento() ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':pais_nacionalidad_id', $persona->obtenerPaisNacionalidadId(), $persona->obtenerPaisNacionalidadId() ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':direccion', $persona->obtenerDireccion(), $persona->obtenerDireccion() ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':estado', $persona->obtenerEstado(), PDO::PARAM_STR);

        return $stmt->execute();
    }

    /**
     * Desactiva lógicamente una persona natural (soft delete).
     * No realiza borrado físico para preservar integridad histórica y auditoría.
     *
     * @param int $id
     * @return bool
     */
    public function desactivar(int $id): bool
    {
        $sql = "UPDATE personas SET estado = 'INACTIVO' WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Hidrata las colecciones de documentos, contactos y país de nacionalidad.
     *
     * @param Persona $persona
     * @return void
     */
    private function hidratarRelaciones(Persona $persona): void
    {
        $id = $persona->obtenerId();
        if ($id === null) {
            return;
        }

        if ($persona->obtenerPaisNacionalidadId() !== null && $this->paisRepo !== null) {
            $pais = $this->paisRepo->buscarPorId($persona->obtenerPaisNacionalidadId());
            $persona->asignarPaisNacionalidad($pais);
        }

        if ($this->documentoRepo !== null) {
            $documentos = $this->documentoRepo->listarPorPersonaId($id, false);
            $persona->asignarDocumentos($documentos);
        }

        if ($this->contactoRepo !== null) {
            $contactos = $this->contactoRepo->listarPorPersonaId($id, false);
            $persona->asignarContactos($contactos);
        }
    }
}
