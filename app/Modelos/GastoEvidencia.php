<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio que representa una evidencia documental o soporte físico de un gasto.
 * GASTOS-1 / D-086.
 */
class GastoEvidencia
{
    public const TIPO_COMPROBANTE_FISCAL = 'COMPROBANTE_FISCAL';
    public const TIPO_VOUCHER_PAGO = 'VOUCHER_PAGO';
    public const TIPO_FOTO_BIEN = 'FOTO_BIEN_REPARADO';
    public const TIPO_INFORME_TECNICO = 'INFORME_TECNICO';
    public const TIPO_OTRO = 'OTRO';

    public function __construct(
        private ?int $id,
        private int $gastoId,
        private string $tipoEvidencia = self::TIPO_COMPROBANTE_FISCAL,
        private string $nombreOriginal = '',
        private string $rutaArchivo = '',
        private string $mimeType = 'application/pdf',
        private int $tamanoBytes = 0,
        private string $hashSha256 = '',
        private int $subidoPorActorId = 1,
        private ?string $creadoEn = null
    ) {
    }

    public function obtenerId(): ?int { return $this->id; }
    public function obtenerGastoId(): int { return $this->gastoId; }
    public function obtenerTipoEvidencia(): string { return $this->tipoEvidencia; }
    public function obtenerNombreOriginal(): string { return $this->nombreOriginal; }
    public function obtenerRutaArchivo(): string { return $this->rutaArchivo; }
    public function obtenerMimeType(): string { return $this->mimeType; }
    public function obtenerTamanoBytes(): int { return $this->tamanoBytes; }
    public function obtenerHashSha256(): string { return $this->hashSha256; }
    public function obtenerSubidoPorActorId(): int { return $this->subidoPorActorId; }
    public function obtenerCreadoEn(): ?string { return $this->creadoEn; }

    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'gasto_id' => $this->gastoId,
            'tipo_evidencia' => $this->tipoEvidencia,
            'nombre_original' => $this->nombreOriginal,
            'ruta_archivo' => $this->rutaArchivo,
            'mime_type' => $this->mimeType,
            'tamano_bytes' => $this->tamanoBytes,
            'hash_sha256' => $this->hashSha256,
            'subido_por_actor_id' => $this->subidoPorActorId,
            'creado_en' => $this->creadoEn,
        ];
    }

    public static function desdeArreglo(array $datos): self
    {
        return new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (int) ($datos['gasto_id'] ?? 0),
            (string) ($datos['tipo_evidencia'] ?? self::TIPO_COMPROBANTE_FISCAL),
            (string) ($datos['nombre_original'] ?? ''),
            (string) ($datos['ruta_archivo'] ?? ''),
            (string) ($datos['mime_type'] ?? 'application/pdf'),
            (int) ($datos['tamano_bytes'] ?? 0),
            (string) ($datos['hash_sha256'] ?? ''),
            isset($datos['subido_por_actor_id']) ? (int) $datos['subido_por_actor_id'] : 1,
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null
        );
    }
}
