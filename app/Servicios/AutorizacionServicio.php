<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

use CamargoPMS\Excepciones\AutorizacionDenegadaExcepcion;
use CamargoPMS\Modelos\Permiso;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Repositorios\AutorizacionRepositorio;
use CamargoPMS\Repositorios\PermisoRepositorio;
use CamargoPMS\Repositorios\RolRepositorio;
use CamargoPMS\Repositorios\UsuarioRepositorio;
use PDO;

/**
 * Servicio de dominio central para la evaluación y exigencia de autorizaciones RBAC.
 *
 * "¿Qué tienes permitido hacer?"
 *
 * Reglas de Autorización:
 * 1. El rol 'SUPERADMINISTRADOR' confiere autoridad total e incondicional sobre
 *    todos los recursos y acciones del sistema, siempre que dicho rol y el usuario estén activos.
 * 2. Para otros roles, los permisos son estrictamente aditivos y calculados como la unión
 *    de permisos de todos los roles activos del usuario.
 * 3. Si el usuario o su persona natural asociada están inactivos o bloqueados, carece de permisos.
 */
class AutorizacionServicio
{
    private PDO $pdo;
    private AutorizacionRepositorio $autorizacionRepo;
    private PermisoRepositorio $permisoRepo;
    private RolRepositorio $rolRepo;
    private UsuarioRepositorio $usuarioRepo;

    public function __construct(
        ?PDO $pdo = null,
        ?AutorizacionRepositorio $autorizacionRepo = null,
        ?PermisoRepositorio $permisoRepo = null,
        ?RolRepositorio $rolRepo = null,
        ?UsuarioRepositorio $usuarioRepo = null
    ) {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->autorizacionRepo = $autorizacionRepo ?? new AutorizacionRepositorio($this->pdo);
        $this->permisoRepo = $permisoRepo ?? new PermisoRepositorio($this->pdo);
        $this->rolRepo = $rolRepo ?? new RolRepositorio($this->pdo);
        $this->usuarioRepo = $usuarioRepo ?? new UsuarioRepositorio($this->pdo);
    }

    /**
     * Evalúa si un usuario tiene autorización para ejecutar una acción sobre un recurso.
     *
     * @param int $usuarioId
     * @param string $permisoClave Formato atómico 'recurso.accion' (ej. 'usuarios.ver')
     * @return bool
     */
    public function puede(int $usuarioId, string $permisoClave): bool
    {
        if ($usuarioId <= 0 || trim($permisoClave) === '') {
            return false;
        }

        // Si es Superadministrador activo (usuario activo + persona activa + rol activo), tiene acceso absoluto
        if ($this->esSuperadministrador($usuarioId)) {
            return true;
        }

        $permisoNormalizado = strtolower(trim($permisoClave));
        $permisosEfectivos = $this->obtenerPermisosEfectivos($usuarioId);

        return in_array($permisoNormalizado, $permisosEfectivos, true);
    }

    /**
     * Exige que un usuario cuente con un permiso específico, lanzando una excepción si no lo tiene.
     *
     * @param int $usuarioId
     * @param string $permisoClave Formato 'recurso.accion'
     * @throws AutorizacionDenegadaExcepcion Si carece de autorización
     */
    public function exigirPermiso(int $usuarioId, string $permisoClave): void
    {
        if (!$this->puede($usuarioId, $permisoClave)) {
            throw new AutorizacionDenegadaExcepcion(
                $permisoClave,
                "No cuenta con la autorización requerida para acceder al recurso ('{$permisoClave}')."
            );
        }
    }

    /**
     * Evalúa si un usuario posee AL MENOS UNO de los permisos solicitados.
     *
     * @param int $usuarioId
     * @param array<int, string> $permisosClaves
     * @return bool
     */
    public function puedeAlguno(int $usuarioId, array $permisosClaves): bool
    {
        if ($usuarioId <= 0 || empty($permisosClaves)) {
            return false;
        }

        if ($this->esSuperadministrador($usuarioId)) {
            return true;
        }

        foreach ($permisosClaves as $permiso) {
            if ($this->puede($usuarioId, $permiso)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Evalúa si un usuario posee TODOS los permisos solicitados.
     *
     * @param int $usuarioId
     * @param array<int, string> $permisosClaves
     * @return bool
     */
    public function puedeTodos(int $usuarioId, array $permisosClaves): bool
    {
        if ($usuarioId <= 0 || empty($permisosClaves)) {
            return false;
        }

        if ($this->esSuperadministrador($usuarioId)) {
            return true;
        }

        foreach ($permisosClaves as $permiso) {
            if (!$this->puede($usuarioId, $permiso)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Verifica si el usuario cuenta con el rol estructural SUPERADMINISTRADOR activo,
     * considerando la validez de su cuenta y de su persona natural.
     *
     * @param int $usuarioId
     * @return bool
     */
    public function esSuperadministrador(int $usuarioId): bool
    {
        if ($usuarioId <= 0) {
            return false;
        }

        return $this->autorizacionRepo->esUsuarioSuperadministradorActivo($usuarioId);
    }

    /**
     * Obtiene el conjunto unificado de claves de permisos efectivos para un usuario.
     *
     * @param int $usuarioId
     * @return array<int, string>
     */
    public function obtenerPermisosEfectivos(int $usuarioId): array
    {
        if ($usuarioId <= 0) {
            return [];
        }

        return $this->autorizacionRepo->obtenerClavesPermisosEfectivos($usuarioId);
    }
}
