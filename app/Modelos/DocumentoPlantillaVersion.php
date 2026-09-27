<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Modelo de dominio para las Versiones Inmutables de Contenido de Plantillas.
 */
class DocumentoPlantillaVersion
{
    private ?int $id;
    private int $plantillaId;
    private int $numeroVersion;
    private string $tituloDocumento;
    private string $cuerpoHtml;
    private ?string $estilosCss;
    private ?string $notasVersion;
    private bool $esActiva;
    private int $creadoPorActorId;
    private ?string $creadoEn;

    // Metadatos de lectura opcionales
    private ?string $plantillaCodigo;
    private ?string $creadorNombre;

    public function __construct(
        ?int $id,
        int $plantillaId,
        int $numeroVersion,
        string $tituloDocumento,
        string $cuerpoHtml,
        ?string $estilosCss = null,
        ?string $notasVersion = null,
        bool $esActiva = false,
        int $creadoPorActorId = 1,
        ?string $creadoEn = null,
        ?string $plantillaCodigo = null,
        ?string $creadorNombre = null
    ) {
        $this->id = $id;
        $this->plantillaId = $plantillaId;
        $this->numeroVersion = $numeroVersion;
        $this->tituloDocumento = trim($tituloDocumento);
        $this->cuerpoHtml = $cuerpoHtml;
        $this->estilosCss = $estilosCss;
        $this->notasVersion = $notasVersion ? trim($notasVersion) : null;
        $this->esActiva = $esActiva;
        $this->creadoPorActorId = $creadoPorActorId;
        $this->creadoEn = $creadoEn;
        $this->plantillaCodigo = $plantillaCodigo;
        $this->creadorNombre = $creadorNombre;
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerPlantillaId(): int
    {
        return $this->plantillaId;
    }

    public function obtenerNumeroVersion(): int
    {
        return $this->numeroVersion;
    }

    public function obtenerTituloDocumento(): string
    {
        return $this->tituloDocumento;
    }

    public function obtenerCuerpoHtml(): string
    {
        return $this->cuerpoHtml;
    }

    public function obtenerEstilosCss(): ?string
    {
        return $this->estilosCss;
    }

    public function obtenerNotasVersion(): ?string
    {
        return $this->notasVersion;
    }

    public function esActiva(): bool
    {
        return $this->esActiva;
    }

    public function obtenerCreadoPorActorId(): int
    {
        return $this->creadoPorActorId;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function obtenerPlantillaCodigo(): ?string
    {
        return $this->plantillaCodigo;
    }

    public function obtenerCreadorNombre(): ?string
    {
        return $this->creadorNombre;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'plantilla_id' => $this->plantillaId,
            'plantilla_codigo' => $this->plantillaCodigo,
            'numero_version' => $this->numeroVersion,
            'titulo_documento' => $this->tituloDocumento,
            'cuerpo_html' => $this->cuerpoHtml,
            'estilos_css' => $this->estilosCss,
            'notas_version' => $this->notasVersion,
            'es_activa' => $this->esActiva ? 1 : 0,
            'creado_por_actor_id' => $this->creadoPorActorId,
            'creador_nombre' => $this->creadorNombre,
            'creado_en' => $this->creadoEn,
        ];
    }
}
