<?php

declare(strict_types=1);

namespace CamargoPMS\Intermediarios;

use CamargoPMS\Nucleo\ContextoHttpApi;
use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Nucleo\RespuestaApi;
use CamargoPMS\Servicios\ApiClientServicio;

/**
 * Intermediario que autentica peticiones HTTP al perímetro /api/v1 mediante Bearer Token.
 * Valida formato, existencia de credencial activa, vigencia temporal, cliente activo e IP allowlist.
 */
class ApiAutenticacionIntermediario
{
    private ApiClientServicio $apiClientServicio;

    public function __construct(?ApiClientServicio $apiClientServicio = null)
    {
        $this->apiClientServicio = $apiClientServicio ?? new ApiClientServicio();
    }

    /**
     * @param string $rutaSolicitada
     * @return Respuesta|null
     */
    public function manejar(string $rutaSolicitada = '/'): ?Respuesta
    {
        $cabeceraAuth = $_SERVER['HTTP_AUTHORIZATION'] 
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] 
            ?? '';

        $cabeceraAuth = trim((string) $cabeceraAuth);

        if ($cabeceraAuth === '') {
            return RespuestaApi::error(
                'Cabecera Authorization Bearer ausente o vacía.',
                'NO_AUTORIZADO',
                401,
                null,
                ['WWW-Authenticate' => 'Bearer']
            );
        }

        if (!preg_match('/^Bearer\s+(cpms_live_[a-f0-9]{32,64})$/i', $cabeceraAuth, $coincidencias)) {
            return RespuestaApi::error(
                'Formato de token Bearer inválido o malformado.',
                'TOKEN_INVALIDO',
                401,
                null,
                ['WWW-Authenticate' => 'Bearer error="invalid_token"']
            );
        }

        $tokenSecreto = $coincidencias[1];
        $contexto = $this->apiClientServicio->autenticarToken($tokenSecreto);

        if ($contexto === null) {
            return RespuestaApi::error(
                'Credencial técnica inválida, revocada, expirada o cliente inactivo.',
                'CREDENCIAL_INVALIDA',
                401,
                null,
                ['WWW-Authenticate' => 'Bearer error="invalid_token"']
            );
        }

        // Validación de lista blanca de IPs si está configurada en el cliente
        $ipRemota = $_SERVER['REMOTE_ADDR'] ?? '';
        if (!$contexto->obtenerCliente()->permiteIp($ipRemota)) {
            return RespuestaApi::error(
                'Dirección IP no autorizada para este cliente API.',
                'IP_NO_AUTORIZADA',
                403
            );
        }

        // Establecer identidad en el contexto transversal de la petición
        ContextoHttpApi::establecerAutenticacion($contexto);

        return null;
    }
}
