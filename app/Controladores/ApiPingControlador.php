<?php

declare(strict_types=1);

namespace CamargoPMS\Controladores;

use CamargoPMS\Nucleo\ContextoHttpApi;
use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Nucleo\RespuestaApi;

/**
 * Controlador de verificación técnica y diagnóstico del perímetro API v1.
 * Proporciona comprobación de estado (ping) y perfil de identidad técnica autenticada.
 */
class ApiPingControlador
{
    /**
     * Comprobación básica de conectividad y estado operativo del servicio API.
     * Endpoint público con correlación y CORS: GET /api/v1/ping
     */
    public function ping(): Respuesta
    {
        return RespuestaApi::exito(
            datos: [
                'servicio' => 'Camargo PMS API',
                'version' => '1.0',
                'estado' => 'OPERATIVO',
                'marca_tiempo' => gmdate('Y-m-d\TH:i:s\Z'),
            ],
            codigoHttp: 200,
            codigoDominio: 'SERVICIO_OPERATIVO'
        );
    }

    /**
     * Inspección del perfil de identidad técnica autenticada del cliente.
     * Requiere Bearer Token válido: GET /api/v1/perfil
     *
     * IMPORTANTE: No expone secretos, hashes ni credenciales sensibles.
     */
    public function perfil(): Respuesta
    {
        $contexto = ContextoHttpApi::obtenerAutenticacion();

        if ($contexto === null) {
            return RespuestaApi::error(
                'Autenticación técnica requerida.',
                'NO_AUTORIZADO',
                401
            );
        }

        $cliente = $contexto->obtenerCliente();
        $credencial = $contexto->obtenerCredencial();
        $actor = $contexto->obtenerActor();

        return RespuestaApi::exito(
            datos: [
                'cliente' => [
                    'id' => $cliente->obtenerId(),
                    'codigo' => $cliente->obtenerCodigo(),
                    'nombre' => $cliente->obtenerNombre(),
                    'descripcion' => $cliente->obtenerDescripcion(),
                    'limite_peticiones_minuto' => $cliente->obtenerLimitePeticionesMinuto(),
                    'estado' => $cliente->obtenerEstado(),
                ],
                'credencial' => [
                    'id' => $credencial->obtenerId(),
                    'nombre' => $credencial->obtenerNombre(),
                    'expira_en' => $credencial->obtenerExpiraEn(),
                    'ultimo_uso_en' => $credencial->obtenerUltimoUsoEn(),
                ],
                'actor_tecnico' => [
                    'id' => $actor->obtenerId(),
                    'codigo' => $actor->obtenerCodigo(),
                    'tipo' => $actor->obtenerTipo(),
                ],
                'scopes' => $contexto->obtenerScopes(),
            ],
            codigoHttp: 200,
            codigoDominio: 'PERFIL_CLIENTE_RECUPERADO'
        );
    }
}
