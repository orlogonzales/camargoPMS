<?php

declare(strict_types=1);

namespace CamargoPMS\Intermediarios;

use CamargoPMS\Nucleo\ContextoHttpApi;
use CamargoPMS\Nucleo\Respuesta;

/**
 * Intermediario que asegura un ID de correlación único y trazable por cada petición HTTP a /api/v1.
 * Si el cliente envía X-Correlacion-ID o X-Request-ID válido, lo adopta; de lo contrario genera uno criptográfico.
 */
class ApiCorrelacionIntermediario
{
    /**
     * @param string $rutaSolicitada
     * @return Respuesta|null
     */
    public function manejar(string $rutaSolicitada = '/'): ?Respuesta
    {
        $candidato = $_SERVER['HTTP_X_CORRELACION_ID'] 
            ?? $_SERVER['HTTP_X_CORRELATION_ID'] 
            ?? $_SERVER['HTTP_X_REQUEST_ID'] 
            ?? '';

        $candidato = trim((string) $candidato);

        if ($candidato !== '' && strlen($candidato) <= 64 && preg_match('/^[a-zA-Z0-9_\-\.]+$/', $candidato)) {
            $correlacionId = $candidato;
        } else {
            $correlacionId = bin2hex(random_bytes(16));
        }

        ContextoHttpApi::establecerCorrelacionId($correlacionId);

        return null;
    }
}
