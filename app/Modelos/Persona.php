<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad central de dominio que representa a una Persona Natural en Camargo PMS.
 *
 * Cumple con el Principio de Separación de Identidad:
 * PERSONA ≠ COLABORADOR ≠ USUARIO ≠ CARGO ≠ ROL
 * NACIONALIDAD ≠ PAÍS DE RESIDENCIA
 *
 * Estructura de dominio desacoplada de PDO y persistencia.
 */
final class Persona
{
    public const array GENEROS_VALIDOS = ['MASCULINO', 'FEMENINO', 'OTRO', 'NO_ESPECIFICADO'];

    private ?int $id;
    private string $nombres;
    private ?string $apellidoPaterno;
    private ?string $apellidoMaterno;
    private ?string $genero;
    private ?string $fechaNacimiento;
    private ?int $paisNacionalidadId;
    private ?int $paisResidenciaId;
    private ?int $distritoId;
    private ?string $regionResidenciaExtranjera;
    private ?string $ciudadResidenciaExtranjera;
    private ?string $direccion;
    private string $estado;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    // Relaciones enriquecidas
    private ?Pais $paisNacionalidad;
    private ?Pais $paisResidencia;
    private ?Distrito $distrito;
    private ?string $departamentoNombre = null;
    private ?string $provinciaNombre = null;
    private ?string $distritoNombre = null;
    private ?string $ubigeo = null;
    private ?int $departamentoId = null;
    private ?int $provinciaId = null;
    /** @var array<int, DocumentoPersona> */
    private array $documentos;
    /** @var array<int, ContactoPersona> */
    private array $contactos;

    /**
     * @param int|null $id
     * @param string $nombres
     * @param string|null $apellidoPaterno
     * @param string|null $apellidoMaterno
     * @param string|null $genero
     * @param string|null $fechaNacimiento
     * @param int|null $paisNacionalidadId
     * @param int|null $paisResidenciaId
     * @param int|null $distritoId
     * @param string|null $regionResidenciaExtranjera
     * @param string|null $ciudadResidenciaExtranjera
     * @param string|null $direccion
     * @param string $estado
     * @param string|null $creadoEn
     * @param string|null $actualizadoEn
     * @param Pais|null $paisNacionalidad
     * @param Pais|null $paisResidencia
     * @param Distrito|null $distrito
     * @param array<int, DocumentoPersona> $documentos
     * @param array<int, ContactoPersona> $contactos
     */
    public function __construct(
        ?int $id,
        string $nombres,
        ?string $apellidoPaterno = null,
        ?string $apellidoMaterno = null,
        ?string $genero = null,
        ?string $fechaNacimiento = null,
        ?int $paisNacionalidadId = null,
        ?int $paisResidenciaId = null,
        ?int $distritoId = null,
        ?string $regionResidenciaExtranjera = null,
        ?string $ciudadResidenciaExtranjera = null,
        ?string $direccion = null,
        string $estado = 'ACTIVO',
        ?string $creadoEn = null,
        ?string $actualizadoEn = null,
        ?Pais $paisNacionalidad = null,
        ?Pais $paisResidencia = null,
        ?Distrito $distrito = null,
        array $documentos = [],
        array $contactos = []
    ) {
        $this->id = $id;
        $this->nombres = trim($nombres);
        $this->apellidoPaterno = $apellidoPaterno !== null && trim($apellidoPaterno) !== '' ? trim($apellidoPaterno) : null;
        $this->apellidoMaterno = $apellidoMaterno !== null && trim($apellidoMaterno) !== '' ? trim($apellidoMaterno) : null;
        $this->genero = $genero !== null && trim($genero) !== '' ? strtoupper(trim($genero)) : null;
        $this->fechaNacimiento = $fechaNacimiento !== null && trim($fechaNacimiento) !== '' ? trim($fechaNacimiento) : null;
        $this->paisNacionalidadId = $paisNacionalidadId;
        $this->paisResidenciaId = $paisResidenciaId;
        $this->distritoId = $distritoId;
        $this->regionResidenciaExtranjera = $regionResidenciaExtranjera !== null && trim($regionResidenciaExtranjera) !== '' ? trim($regionResidenciaExtranjera) : null;
        $this->ciudadResidenciaExtranjera = $ciudadResidenciaExtranjera !== null && trim($ciudadResidenciaExtranjera) !== '' ? trim($ciudadResidenciaExtranjera) : null;
        $this->direccion = $direccion !== null && trim($direccion) !== '' ? trim($direccion) : null;
        $this->estado = strtoupper(trim($estado));
        $this->creadoEn = $creadoEn;
        $this->actualizadoEn = $actualizadoEn;
        $this->paisNacionalidad = $paisNacionalidad;
        $this->paisResidencia = $paisResidencia;
        $this->distrito = $distrito;
        $this->documentos = $documentos;
        $this->contactos = $contactos;
    }

    public static function desdeArreglo(array $datos): self
    {
        $persona = new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (string) ($datos['nombres'] ?? ''),
            isset($datos['apellido_paterno']) && $datos['apellido_paterno'] !== null ? (string) $datos['apellido_paterno'] : null,
            isset($datos['apellido_materno']) && $datos['apellido_materno'] !== null ? (string) $datos['apellido_materno'] : null,
            isset($datos['genero']) && $datos['genero'] !== null ? (string) $datos['genero'] : null,
            isset($datos['fecha_nacimiento']) && $datos['fecha_nacimiento'] !== null ? (string) $datos['fecha_nacimiento'] : null,
            isset($datos['pais_nacionalidad_id']) && $datos['pais_nacionalidad_id'] !== null ? (int) $datos['pais_nacionalidad_id'] : null,
            isset($datos['pais_residencia_id']) && $datos['pais_residencia_id'] !== null ? (int) $datos['pais_residencia_id'] : null,
            isset($datos['distrito_id']) && $datos['distrito_id'] !== null ? (int) $datos['distrito_id'] : null,
            isset($datos['region_residencia_extranjera']) && $datos['region_residencia_extranjera'] !== null ? (string) $datos['region_residencia_extranjera'] : null,
            isset($datos['ciudad_residencia_extranjera']) && $datos['ciudad_residencia_extranjera'] !== null ? (string) $datos['ciudad_residencia_extranjera'] : null,
            isset($datos['direccion']) && $datos['direccion'] !== null ? (string) $datos['direccion'] : null,
            (string) ($datos['estado'] ?? 'ACTIVO'),
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            isset($datos['actualizado_en']) ? (string) $datos['actualizado_en'] : null
        );

        if (isset($datos['departamento'])) {
            $persona->departamentoNombre = (string) $datos['departamento'];
        }
        if (isset($datos['provincia'])) {
            $persona->provinciaNombre = (string) $datos['provincia'];
        }
        if (isset($datos['distrito'])) {
            $persona->distritoNombre = (string) $datos['distrito'];
        }
        if (isset($datos['ubigeo'])) {
            $persona->ubigeo = (string) $datos['ubigeo'];
        }

        return $persona;
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerNombres(): string
    {
        return $this->nombres;
    }

    public function obtenerApellidoPaterno(): ?string
    {
        return $this->apellidoPaterno;
    }

    public function obtenerApellidoMaterno(): ?string
    {
        return $this->apellidoMaterno;
    }

    /**
     * Construye dinámicamente el nombre completo sin redundancia en base de datos.
     *
     * @return string
     */
    public function obtenerNombreCompleto(): string
    {
        $partes = [$this->nombres];

        if ($this->apellidoPaterno !== null) {
            $partes[] = $this->apellidoPaterno;
        }

        if ($this->apellidoMaterno !== null) {
            $partes[] = $this->apellidoMaterno;
        }

        return implode(' ', $partes);
    }

    public function obtenerGenero(): ?string
    {
        return $this->genero;
    }

    public function obtenerFechaNacimiento(): ?string
    {
        return $this->fechaNacimiento;
    }

    public function obtenerPaisNacionalidadId(): ?int
    {
        return $this->paisNacionalidadId;
    }

    public function obtenerPaisResidenciaId(): ?int
    {
        return $this->paisResidenciaId;
    }

    public function obtenerDistritoId(): ?int
    {
        return $this->distritoId;
    }

    public function obtenerRegionResidenciaExtranjera(): ?string
    {
        return $this->regionResidenciaExtranjera;
    }

    public function obtenerCiudadResidenciaExtranjera(): ?string
    {
        return $this->ciudadResidenciaExtranjera;
    }

    public function obtenerDireccion(): ?string
    {
        return $this->direccion;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function esActivo(): bool
    {
        return $this->estado === 'ACTIVO';
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function obtenerActualizadoEn(): ?string
    {
        return $this->actualizadoEn;
    }

    public function obtenerPaisNacionalidad(): ?Pais
    {
        return $this->paisNacionalidad;
    }

    public function asignarPaisNacionalidad(?Pais $pais): void
    {
        $this->paisNacionalidad = $pais;
    }

    public function obtenerPaisResidencia(): ?Pais
    {
        return $this->paisResidencia;
    }

    public function asignarPaisResidencia(?Pais $pais): void
    {
        $this->paisResidencia = $pais;
    }

    public function obtenerDistrito(): ?Distrito
    {
        return $this->distrito;
    }

    public function asignarDistrito(?Distrito $distrito): void
    {
        $this->distrito = $distrito;
    }

    public function asignarJerarquiaTerritorial(
        ?string $departamento,
        ?string $provincia,
        ?string $distrito,
        ?string $ubigeo,
        ?int $departamentoId = null,
        ?int $provinciaId = null
    ): void {
        $this->departamentoNombre = $departamento;
        $this->provinciaNombre = $provincia;
        $this->distritoNombre = $distrito;
        $this->ubigeo = $ubigeo;
        $this->departamentoId = $departamentoId;
        $this->provinciaId = $provinciaId;
    }

    public function obtenerDepartamentoNombre(): ?string
    {
        return $this->departamentoNombre;
    }

    public function obtenerProvinciaNombre(): ?string
    {
        return $this->provinciaNombre;
    }

    public function obtenerDistritoNombre(): ?string
    {
        return $this->distritoNombre;
    }

    public function obtenerUbigeo(): ?string
    {
        return $this->ubigeo;
    }

    public function obtenerDepartamentoId(): ?int
    {
        return $this->departamentoId;
    }

    public function obtenerProvinciaId(): ?int
    {
        return $this->provinciaId;
    }

    /**
     * @return array<int, DocumentoPersona>
     */
    public function obtenerDocumentos(): array
    {
        return $this->documentos;
    }

    /**
     * @param array<int, DocumentoPersona> $documentos
     */
    public function asignarDocumentos(array $documentos): void
    {
        $this->documentos = $documentos;
    }

    /**
     * Obtiene el documento principal activo de la persona.
     *
     * @return DocumentoPersona|null
     */
    public function obtenerDocumentoPrincipal(): ?DocumentoPersona
    {
        foreach ($this->documentos as $documento) {
            if ($documento->esPrincipal() && $documento->esActivo()) {
                return $documento;
            }
        }

        return null;
    }

    /**
     * @return array<int, ContactoPersona>
     */
    public function obtenerContactos(): array
    {
        return $this->contactos;
    }

    /**
     * @param array<int, ContactoPersona> $contactos
     */
    public function asignarContactos(array $contactos): void
    {
        $this->contactos = $contactos;
    }

    /**
     * Obtiene el contacto principal activo para un tipo de contacto específico.
     *
     * @param string $tipoContacto 'TELEFONO' o 'EMAIL'.
     * @return ContactoPersona|null
     */
    public function obtenerContactoPrincipal(string $tipoContacto): ?ContactoPersona
    {
        $tipoNormalizado = strtoupper(trim($tipoContacto));

        foreach ($this->contactos as $contacto) {
            if ($contacto->obtenerTipoContacto() === $tipoNormalizado && $contacto->esPrincipal() && $contacto->esActivo()) {
                return $contacto;
            }
        }

        return null;
    }

    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'nombres' => $this->nombres,
            'apellido_paterno' => $this->apellidoPaterno,
            'apellido_materno' => $this->apellidoMaterno,
            'nombre_completo' => $this->obtenerNombreCompleto(),
            'genero' => $this->genero,
            'fecha_nacimiento' => $this->fechaNacimiento,
            'pais_nacionalidad_id' => $this->paisNacionalidadId,
            'pais_residencia_id' => $this->paisResidenciaId,
            'distrito_id' => $this->distritoId,
            'departamento_id' => $this->departamentoId,
            'provincia_id' => $this->provinciaId,
            'departamento' => $this->departamentoNombre,
            'provincia' => $this->provinciaNombre,
            'distrito' => $this->distritoNombre,
            'ubigeo' => $this->ubigeo,
            'region_residencia_extranjera' => $this->regionResidenciaExtranjera,
            'ciudad_residencia_extranjera' => $this->ciudadResidenciaExtranjera,
            'direccion' => $this->direccion,
            'estado' => $this->estado,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }
}
