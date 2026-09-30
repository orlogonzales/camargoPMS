<?php

declare(strict_types=1);

namespace CamargoPMS\Intermediarios;

use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Nucleo\ContextoHttpApi;
use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Nucleo\RespuestaApi;

/**
 * Intermediario que gestiona las políticas CORS (Cross-Origin Resource Sharing) para /api/v1.
 * Soporta allowlist de orígenes y resuelve preflights OPTIONS con HTTP 204 sin exigir autenticación Bearer.
 */
class ApiCorsIntermediario
{
    /** @var array<int, string> */
    private array $origenesPermitidos = [];
    private bool $permitirCualquiera = false;

    /**
     * @param array<int, string>|string|null $origenesConfigurados
     */
    public function __construct(array|string|null $origenesConfigurados = null)
    {
        $origenesRaw = $origenesConfigurados 
            ?? Configuracion::obtener('API_CORS_ORIGINS', getenv('API_CORS_ORIGINS') ?: '*');

        if (is_string($origenesRaw)) {
            $partes = array_map('trim', explode(',', $origenesRaw));
            $this->origenesPermitidos = array_values(array_filter($partes));
        } elseif (is_array($origenesRaw)) {
            $this->origenesPermitidos = array_values(array_map('trim', $origenesRaw));
        }

        $this->permitirCualquiera = in_array('*', $this->origenesPermitidos, true) || empty($this->origenesPermitidos);
    }

    /**
     * @param string $rutaSolicitada
     * @return Respuesta|null
     */
    public function manejar(string $rutaSolicitada = '/'): ?Respuesta
    {
        $metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $origen = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));

        $origenPermitido = null;

        if ($origen !== '') {
            if ($this->permitirCualquiera) {
                $origenPermitido = $origen;
            } elseif (in_array($origen, $this->origenesPermitidos, true)) {
                $origenPermitido = $origen;
            } else {
                // Origen explícitamente no permitido
                return RespuestaApi::error(
                    'Origen no permitido por la política CORS.',
                    'CORS_NO_PERMITIDO',
                    403
                );
            }
        } else {
            // Petición interna o de cliente no navegador (curl, postman, backend a backend)
            $origenPermitido = $this->permitirCualquiera ? '*' : ($this->origenesPermitidos[0] ?? '*');
        }

        $cabecerasCors = [
            'Access-Control-Allow-Origin' => $origenPermitido,
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Authorization, Content-Type, Idempotency-Key, X-Correlacion-ID, X-Requested-With',
            'Access-Control-Expose-Headers' => 'X-Correlacion-ID, X-RateLimit-Limit, X-RateLimit-Remaining, X-RateLimit-Reset, Retry-After, Idempotency-Key, X-Cache-Lookup',
            'Access-Control-Max-Age' => '86400',
        ];

        ContextoHttpApi::establecerCabecerasCors($cabecerasCors);

        // Si es una petición preflight OPTIONS, cortocircuita y responde 204 sin autenticación
        if ($metodo === 'OPTIONS') {
            return RespuestaApi::sinContenido($cabecerasCors);
        }

        return null;
    }
}
