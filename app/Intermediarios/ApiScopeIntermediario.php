<?php

declare(strict_types=1);

namespace CamargoPMS\Intermediarios;

use CamargoPMS\Nucleo\ContextoHttpApi;
use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Nucleo\RespuestaApi;

/**
 * Intermediario que valida que la credencial técnica autenticada posea los scopes requeridos.
 * Retorna HTTP 403 ACCESO_DENEGADO ante insuficiencia de permisos sin filtrar datos de negocio.
 */
class ApiScopeIntermediario
{
    /** @var array<int, string> */
    private array $scopesRequeridos;
    private bool $requiereTodos;

    /**
     * @param string|array<int, string> $scopesRequeridos Scope o lista de scopes requeridos
     * @param bool $requiereTodos True si debe poseer todos; False si basta con poseer al menos uno
     */
    public function __construct(string|array $scopesRequeridos = [], bool $requiereTodos = true)
    {
        $this->scopesRequeridos = is_array($scopesRequeridos)
            ? array_values(array_filter(array_map('trim', $scopesRequeridos)))
            : (trim($scopesRequeridos) !== '' ? [trim($scopesRequeridos)] : []);
        $this->requiereTodos = $requiereTodos;
    }

    /**
     * Fábrica fluida para requerir un único scope o varios.
     *
     * @param string|array<int, string> $scopes
     * @param bool $requiereTodos
     * @return self
     */
    public static function requerir(string|array $scopes, bool $requiereTodos = true): self
    {
        return new self($scopes, $requiereTodos);
    }

    /**
     * @param string $rutaSolicitada
     * @return Respuesta|null
     */
    public function manejar(string $rutaSolicitada = '/'): ?Respuesta
    {
        if (empty($this->scopesRequeridos)) {
            return null;
        }

        $contexto = ContextoHttpApi::obtenerAutenticacion();
        if ($contexto === null) {
            return RespuestaApi::error(
                'Autenticación técnica requerida antes de evaluar permisos.',
                'NO_AUTORIZADO',
                401
            );
        }

        $scopesPoseidos = $contexto->obtenerScopes();

        if ($this->requiereTodos) {
            foreach ($this->scopesRequeridos as $scope) {
                if (!in_array($scope, $scopesPoseidos, true)) {
                    return RespuestaApi::error(
                        "La credencial técnica no cuenta con el alcance requerido: '{$scope}'",
                        'ACCESO_DENEGADO',
                        403,
                        [
                            'scopes_requeridos' => $this->scopesRequeridos,
                            'scopes_poseidos' => $scopesPoseidos,
                        ]
                    );
                }
            }
        } else {
            $coincidencias = array_intersect($this->scopesRequeridos, $scopesPoseidos);
            if (empty($coincidencias)) {
                return RespuestaApi::error(
                    'La credencial técnica no cuenta con ninguno de los alcances requeridos.',
                    'ACCESO_DENEGADO',
                    403,
                    [
                        'scopes_requeridos' => $this->scopesRequeridos,
                        'scopes_poseidos' => $scopesPoseidos,
                    ]
                );
            }
        }

        return null;
    }
}
