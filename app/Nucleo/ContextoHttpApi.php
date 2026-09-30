<?php

declare(strict_types=1);

namespace CamargoPMS\Nucleo;

use CamargoPMS\Modelos\ContextoAutenticacionApi;

/**
 * Contenedor de contexto HTTP específico para el perímetro /api/v1.
 * Mantiene la correlación, identidad técnica autenticada, cabeceras CORS
 * y metadatos de idempotencia durante el ciclo de vida de la petición.
 */
class ContextoHttpApi
{
    private static ?string $correlacionId = null;
    private static ?ContextoAutenticacionApi $autenticacion = null;
    /** @var array<string, string> */
    private static array $cabecerasCors = [];
    /** @var array<string, string> */
    private static array $cabecerasRateLimit = [];
    private static ?int $idempotenciaId = null;
    private static bool $idempotenciaReplay = false;

    public static function establecerCorrelacionId(string $correlacionId): void
    {
        self::$correlacionId = trim($correlacionId);
    }

    public static function obtenerCorrelacionId(): string
    {
        if (self::$correlacionId === null || self::$correlacionId === '') {
            self::$correlacionId = bin2hex(random_bytes(16));
        }
        return self::$correlacionId;
    }

    public static function establecerAutenticacion(?ContextoAutenticacionApi $autenticacion): void
    {
        self::$autenticacion = $autenticacion;
    }

    public static function obtenerAutenticacion(): ?ContextoAutenticacionApi
    {
        return self::$autenticacion;
    }

    /**
     * @param array<string, string> $cabeceras
     */
    public static function establecerCabecerasCors(array $cabeceras): void
    {
        self::$cabecerasCors = $cabeceras;
    }

    /**
     * @return array<string, string>
     */
    public static function obtenerCabecerasCors(): array
    {
        return self::$cabecerasCors;
    }

    /**
     * @param array<string, string> $cabeceras
     */
    public static function establecerCabecerasRateLimit(array $cabeceras): void
    {
        self::$cabecerasRateLimit = $cabeceras;
    }

    /**
     * @return array<string, string>
     */
    public static function obtenerCabecerasRateLimit(): array
    {
        return self::$cabecerasRateLimit;
    }

    public static function establecerIdempotenciaId(?int $id): void
    {
        self::$idempotenciaId = $id;
    }

    public static function obtenerIdempotenciaId(): ?int
    {
        return self::$idempotenciaId;
    }

    public static function marcarIdempotenteReplay(bool $replay = true): void
    {
        self::$idempotenciaReplay = $replay;
    }

    public static function esIdempotenteReplay(): bool
    {
        return self::$idempotenciaReplay;
    }

    /**
     * Restablece el contexto completo a su estado inicial.
     * Esencial para pruebas y entornos de larga duración.
     */
    public static function limpiar(): void
    {
        self::$correlacionId = null;
        self::$autenticacion = null;
        self::$cabecerasCors = [];
        self::$cabecerasRateLimit = [];
        self::$idempotenciaId = null;
        self::$idempotenciaReplay = false;
    }
}
