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
    private ?GeografiaRepositorio $geografiaRepo;

    public function __construct(
        ?PDO $pdo = null,
        ?PaisRepositorio $paisRepo = null,
        ?DocumentoPersonaRepositorio $documentoRepo = null,
        ?ContactoPersonaRepositorio $contactoRepo = null,
        ?GeografiaRepositorio $geografiaRepo = null
    ) {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->paisRepo = $paisRepo ?? new PaisRepositorio($this->pdo);
        $this->documentoRepo = $documentoRepo ?? new DocumentoPersonaRepositorio($this->pdo);
        $this->contactoRepo = $contactoRepo ?? new ContactoPersonaRepositorio($this->pdo);
        $this->geografiaRepo = $geografiaRepo ?? new GeografiaRepositorio($this->pdo);
    }

    /**
     * Busca una persona por su identificador primario.
     *
     * @param int $id
     * @param bool $cargarRelaciones Carga documentos, contactos, geografía y país asociado.
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
     * Inserta una persona natural en la base de datos con atributos completos de identidad y geografía.
     *
     * @param Persona $persona
     * @return int ID de la persona generada.
     */
    public function insertar(Persona $persona): int
    {
        $sql = "INSERT INTO personas (
                    nombres, apellido_paterno, apellido_materno, genero,
                    fecha_nacimiento, pais_nacionalidad_id, pais_residencia_id,
                    distrito_id, region_residencia_extranjera, ciudad_residencia_extranjera,
                    direccion, estado
                ) VALUES (
                    :nombres, :apellido_paterno, :apellido_materno, :genero,
                    :fecha_nacimiento, :pais_nacionalidad_id, :pais_residencia_id,
                    :distrito_id, :region_residencia_extranjera, :ciudad_residencia_extranjera,
                    :direccion, :estado
                )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':nombres', $persona->obtenerNombres(), PDO::PARAM_STR);
        $stmt->bindValue(':apellido_paterno', $persona->obtenerApellidoPaterno(), $persona->obtenerApellidoPaterno() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':apellido_materno', $persona->obtenerApellidoMaterno(), $persona->obtenerApellidoMaterno() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':genero', $persona->obtenerGenero(), $persona->obtenerGenero() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':fecha_nacimiento', $persona->obtenerFechaNacimiento(), $persona->obtenerFechaNacimiento() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':pais_nacionalidad_id', $persona->obtenerPaisNacionalidadId(), $persona->obtenerPaisNacionalidadId() !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':pais_residencia_id', $persona->obtenerPaisResidenciaId(), $persona->obtenerPaisResidenciaId() !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':distrito_id', $persona->obtenerDistritoId(), $persona->obtenerDistritoId() !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':region_residencia_extranjera', $persona->obtenerRegionResidenciaExtranjera(), $persona->obtenerRegionResidenciaExtranjera() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':ciudad_residencia_extranjera', $persona->obtenerCiudadResidenciaExtranjera(), $persona->obtenerCiudadResidenciaExtranjera() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':direccion', $persona->obtenerDireccion(), $persona->obtenerDireccion() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
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
                    genero = :genero,
                    fecha_nacimiento = :fecha_nacimiento,
                    pais_nacionalidad_id = :pais_nacionalidad_id,
                    pais_residencia_id = :pais_residencia_id,
                    distrito_id = :distrito_id,
                    region_residencia_extranjera = :region_residencia_extranjera,
                    ciudad_residencia_extranjera = :ciudad_residencia_extranjera,
                    direccion = :direccion,
                    estado = :estado
                WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $persona->obtenerId(), PDO::PARAM_INT);
        $stmt->bindValue(':nombres', $persona->obtenerNombres(), PDO::PARAM_STR);
        $stmt->bindValue(':apellido_paterno', $persona->obtenerApellidoPaterno(), $persona->obtenerApellidoPaterno() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':apellido_materno', $persona->obtenerApellidoMaterno(), $persona->obtenerApellidoMaterno() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':genero', $persona->obtenerGenero(), $persona->obtenerGenero() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':fecha_nacimiento', $persona->obtenerFechaNacimiento(), $persona->obtenerFechaNacimiento() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':pais_nacionalidad_id', $persona->obtenerPaisNacionalidadId(), $persona->obtenerPaisNacionalidadId() !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':pais_residencia_id', $persona->obtenerPaisResidenciaId(), $persona->obtenerPaisResidenciaId() !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':distrito_id', $persona->obtenerDistritoId(), $persona->obtenerDistritoId() !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':region_residencia_extranjera', $persona->obtenerRegionResidenciaExtranjera(), $persona->obtenerRegionResidenciaExtranjera() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':ciudad_residencia_extranjera', $persona->obtenerCiudadResidenciaExtranjera(), $persona->obtenerCiudadResidenciaExtranjera() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':direccion', $persona->obtenerDireccion(), $persona->obtenerDireccion() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
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
     * Hidrata las colecciones de documentos, contactos, geografía y país de nacionalidad/residencia.
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

        if ($persona->obtenerPaisResidenciaId() !== null && $this->paisRepo !== null) {
            $paisRes = $this->paisRepo->buscarPorId($persona->obtenerPaisResidenciaId());
            $persona->asignarPaisResidencia($paisRes);
        }

        if ($persona->obtenerDistritoId() !== null && $this->geografiaRepo !== null) {
            $jerarquia = $this->geografiaRepo->buscarJerarquiaPorDistritoId($persona->obtenerDistritoId());
            if ($jerarquia !== null) {
                $persona->asignarJerarquiaTerritorial(
                    $jerarquia['departamento_nombre'] ?? null,
                    $jerarquia['provincia_nombre'] ?? null,
                    $jerarquia['distrito_nombre'] ?? null,
                    $jerarquia['distrito_codigo_ubigeo'] ?? null,
                    isset($jerarquia['departamento_id']) ? (int) $jerarquia['departamento_id'] : null,
                    isset($jerarquia['provincia_id']) ? (int) $jerarquia['provincia_id'] : null
                );
            }
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
