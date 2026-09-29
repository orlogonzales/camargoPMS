<?php

declare(strict_types=1);

namespace CamargoPMS\Controladores;

use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Repositorios\GeografiaRepositorio;
use CamargoPMS\Servicios\GeografiaServicio;
use PDO;
use Throwable;

/**
 * Controlador de API para consultas geográficas y territoriales (INEI UBIGEO).
 */
class GeografiaControlador
{
    private GeografiaServicio $geografiaServicio;
    private PDO $pdo;

    public function __construct(?GeografiaServicio $geografiaServicio = null, ?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->geografiaServicio = $geografiaServicio ?? new GeografiaServicio(new GeografiaRepositorio($this->pdo));
    }

    /**
     * API: Retorna el país predeterminado (Perú) resuelto dinámicamente.
     */
    public function apiPaisDefault(): Respuesta
    {
        try {
            $pais = $this->geografiaServicio->obtenerPaisDefault();

            return Respuesta::json([
                'exito' => true,
                'datos' => $pais,
            ]);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Listado de departamentos filtrados opcionalmente por país.
     */
    public function apiDepartamentos(): Respuesta
    {
        try {
            $paisId = isset($_GET['pais_id']) && is_numeric($_GET['pais_id'])
                ? (int) $_GET['pais_id']
                : null;

            if ($paisId === null && isset($_GET['pais']) && strtoupper(trim((string) $_GET['pais'])) === 'PE') {
                $paisDefault = $this->geografiaServicio->obtenerPaisDefault();
                $paisId = (int) $paisDefault['id'];
            }

            $departamentos = $this->geografiaServicio->listarDepartamentos($paisId);

            return Respuesta::json([
                'exito' => true,
                'datos' => $departamentos,
            ]);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Listado de provincias de un departamento.
     */
    public function apiProvincias(): Respuesta
    {
        try {
            $departamentoId = isset($_GET['departamento_id']) && is_numeric($_GET['departamento_id'])
                ? (int) $_GET['departamento_id']
                : 0;

            if ($departamentoId <= 0) {
                return Respuesta::json(['exito' => false, 'mensaje' => 'Se requiere departamento_id válido.'], 422);
            }

            $provincias = $this->geografiaServicio->listarProvincias($departamentoId);

            return Respuesta::json([
                'exito' => true,
                'datos' => $provincias,
            ]);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Listado de distritos de una provincia.
     */
    public function apiDistritos(): Respuesta
    {
        try {
            $provinciaId = isset($_GET['provincia_id']) && is_numeric($_GET['provincia_id'])
                ? (int) $_GET['provincia_id']
                : 0;

            if ($provinciaId <= 0) {
                return Respuesta::json(['exito' => false, 'mensaje' => 'Se requiere provincia_id válido.'], 422);
            }

            $distritos = $this->geografiaServicio->listarDistritos($provinciaId);

            return Respuesta::json([
                'exito' => true,
                'datos' => $distritos,
            ]);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }
}
