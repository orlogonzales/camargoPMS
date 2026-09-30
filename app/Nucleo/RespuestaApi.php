<?php

declare(strict_types=1);

namespace CamargoPMS\Nucleo;

/**
 * Constructor unificado de respuestas JSON para la API v1.
 * Garantiza un sobre estandarizado (ok, datos, error, codigo, meta),
 * propagación de cabeceras de correlación, CORS y rate limiting.
 */
class RespuestaApi
{
    /**
     * Construye una respuesta exitosa con sobre estándar.
     *
     * @param mixed $datos
     * @param int $codigoHttp
     * @param array<string, string> $cabeceras
     * @param string $codigoDominio
     * @param array<string, mixed>|null $metaExtra
     * @return Respuesta
     */
    public static function exito(
        mixed $datos = null,
        int $codigoHttp = 200,
        array $cabeceras = [],
        string $codigoDominio = 'OPERACION_EXITOSA',
        ?array $metaExtra = null
    ): Respuesta {
        $correlacionId = ContextoHttpApi::obtenerCorrelacionId();

        $meta = [
            'correlacion_id' => $correlacionId,
            'marca_tiempo' => gmdate('Y-m-d\TH:i:s\Z'),
        ];

        if ($metaExtra !== null) {
            $meta = array_merge($meta, $metaExtra);
        }

        $cuerpo = [
            'ok' => true,
            'datos' => $datos,
            'error' => null,
            'codigo' => $codigoDominio,
            'meta' => $meta,
        ];

        return self::crearRespuesta($cuerpo, $codigoHttp, $cabeceras);
    }

    /**
     * Construye una respuesta de error con sobre estándar y código semántico.
     *
     * @param string $mensaje
     * @param string $codigoDominio
     * @param int $codigoHttp
     * @param mixed $detalles
     * @param array<string, string> $cabeceras
     * @return Respuesta
     */
    public static function error(
        string $mensaje,
        string $codigoDominio = 'ERROR_GENERAL',
        int $codigoHttp = 400,
        mixed $detalles = null,
        array $cabeceras = []
    ): Respuesta {
        $correlacionId = ContextoHttpApi::obtenerCorrelacionId();

        $meta = [
            'correlacion_id' => $correlacionId,
            'marca_tiempo' => gmdate('Y-m-d\TH:i:s\Z'),
        ];

        if ($detalles !== null) {
            $meta['detalles'] = $detalles;
        }

        $cuerpo = [
            'ok' => false,
            'datos' => null,
            'error' => $mensaje,
            'codigo' => $codigoDominio,
            'meta' => $meta,
        ];

        return self::crearRespuesta($cuerpo, $codigoHttp, $cabeceras);
    }

    /**
     * Respuesta HTTP 204 No Content (habitual en preflight CORS OPTIONS o acciones sin cuerpo).
     *
     * @param array<string, string> $cabeceras
     * @return Respuesta
     */
    public static function sinContenido(array $cabeceras = []): Respuesta
    {
        $cabecerasCombinadas = self::combinarCabeceras($cabeceras);
        return new Respuesta('', 204, $cabecerasCombinadas);
    }

    /**
     * @param array<string, mixed> $cuerpo
     * @param int $codigoHttp
     * @param array<string, string> $cabeceras
     * @return Respuesta
     */
    private static function crearRespuesta(array $cuerpo, int $codigoHttp, array $cabeceras): Respuesta
    {
        $cabecerasCombinadas = self::combinarCabeceras(array_merge([
            'Content-Type' => 'application/json; charset=UTF-8',
        ], $cabeceras));

        return Respuesta::json($cuerpo, $codigoHttp, $cabecerasCombinadas);
    }

    /**
     * @param array<string, string> $cabeceras
     * @return array<string, string>
     */
    private static function combinarCabeceras(array $cabeceras): array
    {
        $base = [
            'X-Correlacion-ID' => ContextoHttpApi::obtenerCorrelacionId(),
        ];

        if (ContextoHttpApi::esIdempotenteReplay()) {
            $base['X-Cache-Lookup'] = 'IDEMPOTENT-REPLAY';
        }

        $cors = ContextoHttpApi::obtenerCabecerasCors();
        $rateLimit = ContextoHttpApi::obtenerCabecerasRateLimit();

        return array_merge($base, $cors, $rateLimit, $cabeceras);
    }
}
