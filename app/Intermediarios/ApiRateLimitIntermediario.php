<?php

declare(strict_types=1);

namespace CamargoPMS\Intermediarios;

use CamargoPMS\Nucleo\ContextoHttpApi;
use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Nucleo\RespuestaApi;
use CamargoPMS\Servicios\ApiRateLimitServicio;

/**
 * Intermediario que aplica límite de peticiones por minuto (Rate Limiting) con ventana deslizante.
 * Emite cabeceras X-RateLimit-* y responde HTTP 429 con Retry-After al sobrepasar la cuota.
 */
class ApiRateLimitIntermediario
{
    private ApiRateLimitServicio $rateLimitServicio;

    public function __construct(?ApiRateLimitServicio $rateLimitServicio = null)
    {
        $this->rateLimitServicio = $rateLimitServicio ?? new ApiRateLimitServicio();
    }

    /**
     * @param string $rutaSolicitada
     * @return Respuesta|null
     */
    public function manejar(string $rutaSolicitada = '/'): ?Respuesta
    {
        $contexto = ContextoHttpApi::obtenerAutenticacion();

        if ($contexto !== null) {
            $cliente = $contexto->obtenerCliente();
            $identificador = 'cliente:' . $cliente->obtenerId();
            $limite = $cliente->obtenerLimitePeticionesMinuto();
        } else {
            $ipRemota = trim((string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'));
            $identificador = 'ip:' . ($ipRemota !== '' ? $ipRemota : '127.0.0.1');
            $limite = 60;
        }

        $resultado = $this->rateLimitServicio->verificarYConsumir($identificador, $limite);

        $cabecerasRateLimit = [
            'X-RateLimit-Limit' => (string) $resultado['limite'],
            'X-RateLimit-Remaining' => (string) $resultado['restantes'],
            'X-RateLimit-Reset' => (string) $resultado['reset'],
        ];

        if (!$resultado['permitido']) {
            $cabecerasRateLimit['Retry-After'] = (string) $resultado['retry_after'];
            ContextoHttpApi::establecerCabecerasRateLimit($cabecerasRateLimit);

            return RespuestaApi::error(
                'Límite de peticiones por minuto excedido.',
                'RATE_LIMIT_EXCEDIDO',
                429,
                [
                    'limite_por_minuto' => $resultado['limite'],
                    'retry_after_segundos' => $resultado['retry_after'],
                ],
                ['Retry-After' => (string) $resultado['retry_after']]
            );
        }

        ContextoHttpApi::establecerCabecerasRateLimit($cabecerasRateLimit);

        return null;
    }
}
