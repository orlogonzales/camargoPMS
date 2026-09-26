<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Vocabulario normativo de acciones de auditoría y trazabilidad en Camargo PMS.
 */
final class AccionAuditoria
{
    public const CREAR = 'CREAR';
    public const EDITAR = 'EDITAR';
    public const ELIMINAR = 'ELIMINAR';
    public const ANULAR = 'ANULAR';
    public const ACTIVAR = 'ACTIVAR';
    public const DESACTIVAR = 'DESACTIVAR';
    public const LOGIN = 'LOGIN';
    public const LOGOUT = 'LOGOUT';
    public const BLOQUEAR = 'BLOQUEAR';
    public const DESBLOQUEAR = 'DESBLOQUEAR';
    public const ASIGNAR = 'ASIGNAR';
    public const REVOCAR = 'REVOCAR';
    public const REORDENAR = 'REORDENAR';
    public const CAMBIAR_CLAVE = 'CAMBIAR_CLAVE';
    public const CERRAR_SESION = 'CERRAR_SESION';

    public const TODAS = [
        self::CREAR,
        self::EDITAR,
        self::ELIMINAR,
        self::ANULAR,
        self::ACTIVAR,
        self::DESACTIVAR,
        self::LOGIN,
        self::LOGOUT,
        self::BLOQUEAR,
        self::DESBLOQUEAR,
        self::ASIGNAR,
        self::REVOCAR,
        self::REORDENAR,
        self::CAMBIAR_CLAVE,
        self::CERRAR_SESION,
    ];

    /**
     * Evalúa si una acción de auditoría provista pertenece al vocabulario normalizado.
     *
     * @param string $accion
     * @return bool
     */
    public static function esValida(string $accion): bool
    {
        return in_array(strtoupper(trim($accion)), self::TODAS, true);
    }
}
