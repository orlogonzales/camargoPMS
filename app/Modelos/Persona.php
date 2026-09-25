<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad central de dominio que representa a una Persona Natural en Camargo PMS.
 *
 * Cumple con el Principio de Separación de Identidad:
 * PERSONA ≠ COLABORADOR ≠ USUARIO ≠ CARGO ≠ ROL
 *
 * Estructura de dominio desacoplada de PDO y persistencia.
 */
final class Persona
{
    private ?int $id;
    private string $nombres;
    private ?string $apellidoPaterno;
    private ?string $apellidoMaterno;
    private ?string $fechaNacimiento;
    private ?int $paisNacionalidadId;
    private ?string $direccion;
    private string $estado;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    // Relaciones enriquecidas
    private ?Pais $paisNacionalidad;
    /** @var array<int, DocumentoPersona> */
    private array $documentos;
    /** @var array<int, ContactoPersona> */
    private array $contactos;

    /**
     * @param int|null $id
     * @param string $nombres
     * @param string|null $apellidoPaterno
     * @param string|null $apellidoMaterno
     * @param string|null $fechaNacimiento
     * @param int|null $paisNacionalidadId
     * @param string|null $direccion
     * @param string $estado
     * @param string|null $creadoEn
     * @param string|null $actualizadoEn
     * @param Pais|null $paisNacionalidad
     * @param array<int, DocumentoPersona> $documentos
     * @param array<int, ContactoPersona> $contactos
     */
    public function __construct(
        ?int $id,
        string $nombres,
        ?string $apellidoPaterno = null,
        ?string $apellidoMaterno = null,
        ?string $fechaNacimiento = null,
        ?int $paisNacionalidadId = null,
        ?string $direccion = null,
        string $estado = 'ACTIVO',
        ?string $creadoEn = null,
        ?string $actualizadoEn = null,
        ?Pais $paisNacionalidad = null,
        array $documentos = [],
        array $contactos = []
    ) {
        $this->id = $id;
        $this->nombres = trim($nombres);
        $this->apellidoPaterno = $apellidoPaterno !== null && trim($apellidoPaterno) !== '' ? trim($apellidoPaterno) : null;
        $this->apellidoMaterno = $apellidoMaterno !== null && trim($apellidoMaterno) !== '' ? trim($apellidoMaterno) : null;
        $this->fechaNacimiento = $fechaNacimiento !== null && trim($fechaNacimiento) !== '' ? trim($fechaNacimiento) : null;
        $this->paisNacionalidadId = $paisNacionalidadId;
        $this->direccion = $direccion !== null && trim($direccion) !== '' ? trim($direccion) : null;
        $this->estado = strtoupper(trim($estado));
        $this->creadoEn = $creadoEn;
        $this->actualizadoEn = $actualizadoEn;
        $this->paisNacionalidad = $paisNacionalidad;
        $this->documentos = $documentos;
        $this->contactos = $contactos;
    }

    public static function desdeArreglo(array $datos): self
    {
        return new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (string) ($datos['nombres'] ?? ''),
            isset($datos['apellido_paterno']) && $datos['apellido_paterno'] !== null ? (string) $datos['apellido_paterno'] : null,
            isset($datos['apellido_materno']) && $datos['apellido_materno'] !== null ? (string) $datos['apellido_materno'] : null,
            isset($datos['fecha_nacimiento']) && $datos['fecha_nacimiento'] !== null ? (string) $datos['fecha_nacimiento'] : null,
            isset($datos['pais_nacionalidad_id']) && $datos['pais_nacionalidad_id'] !== null ? (int) $datos['pais_nacionalidad_id'] : null,
            isset($datos['direccion']) && $datos['direccion'] !== null ? (string) $datos['direccion'] : null,
            (string) ($datos['estado'] ?? 'ACTIVO'),
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            isset($datos['actualizado_en']) ? (string) $datos['actualizado_en'] : null
        );
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

    public function obtenerFechaNacimiento(): ?string
    {
        return $this->fechaNacimiento;
    }

    public function obtenerPaisNacionalidadId(): ?int
    {
        return $this->paisNacionalidadId;
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
            'fecha_nacimiento' => $this->fechaNacimiento,
            'pais_nacionalidad_id' => $this->paisNacionalidadId,
            'direccion' => $this->direccion,
            'estado' => $this->estado,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }
}
