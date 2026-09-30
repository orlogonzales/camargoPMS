<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Representa el contexto autenticado de una petición API.
 *
 * Agrupa la identidad técnica de auditoría (ActorAuditoria), el perfil
 * comercial del cliente API (ApiClient) y la credencial específica empleada.
 */
class ContextoAutenticacionApi
{
    /**
     * @param array<int, string> $scopes
     */
    public function __construct(
        private ApiClient $cliente,
        private ApiCredencial $credencial,
        private ActorAuditoria $actor,
        private array $scopes = []
    ) {
    }

    public function obtenerCliente(): ApiClient
    {
        return $this->cliente;
    }

    public function obtenerCredencial(): ApiCredencial
    {
        return $this->credencial;
    }

    public function obtenerActor(): ActorAuditoria
    {
        return $this->actor;
    }

    public function obtenerActorId(): int
    {
        return (int) $this->actor->obtenerId();
    }

    /**
     * @return array<int, string>
     */
    public function obtenerScopes(): array
    {
        return $this->scopes;
    }

    public function tieneScope(string $codigoScope): bool
    {
        return in_array(trim($codigoScope), $this->scopes, true);
    }

    public function exigirScope(string $codigoScope): void
    {
        if (!$this->tieneScope($codigoScope)) {
            throw new \CamargoPMS\Excepciones\AutorizacionExcepcion(
                "La credencial técnica no cuenta con el alcance requerido: '{$codigoScope}'"
            );
        }
    }
}
