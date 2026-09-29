<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

use CamargoPMS\Excepciones\ValidacionExcepcion;
use CamargoPMS\Repositorios\GeografiaRepositorio;

/**
 * Servicio de lógica de dominio para jerarquías geográficas territoriales.
 */
class GeografiaServicio
{
    public function __construct(private GeografiaRepositorio $geografiaRepo)
    {
    }

    /**
     * Retorna los departamentos de un país (por defecto Perú).
     *
     * @param int|null $paisId
     * @return array<int, array<string, mixed>>
     */
    public function listarDepartamentos(?int $paisId = null): array
    {
        return $this->geografiaRepo->listarDepartamentos($paisId);
    }

    /**
     * Retorna las provincias asociadas a un departamento.
     *
     * @param int $departamentoId
     * @return array<int, array<string, mixed>>
     * @throws ValidacionExcepcion
     */
    public function listarProvincias(int $departamentoId): array
    {
        if ($departamentoId <= 0) {
            throw new ValidacionExcepcion('Se requiere un departamento válido.');
        }

        return $this->geografiaRepo->listarProvincias($departamentoId);
    }

    /**
     * Retorna los distritos asociados a una provincia.
     *
     * @param int $provinciaId
     * @return array<int, array<string, mixed>>
     * @throws ValidacionExcepcion
     */
    public function listarDistritos(int $provinciaId): array
    {
        if ($provinciaId <= 0) {
            throw new ValidacionExcepcion('Se requiere una provincia válida.');
        }

        return $this->geografiaRepo->listarDistritos($provinciaId);
    }

    /**
     * Obtiene el país predeterminado para el sistema (Perú) resolviéndolo
     * dinámicamente mediante el código ISO-2 'PE' (sin hardcodear ID).
     *
     * @return array<string, mixed>
     * @throws ValidacionExcepcion
     */
    public function obtenerPaisDefault(): array
    {
        $pais = $this->geografiaRepo->buscarPaisPorCodigo('PE');
        if ($pais === null) {
            throw new ValidacionExcepcion("No se encontró el país base 'Perú' (PE) en el catálogo.");
        }

        return $pais;
    }

    /**
     * Resuelve los datos del país de residencia dado un ID.
     *
     * @param int $paisId
     * @return array<string, mixed>|null
     */
    public function obtenerPaisPorId(int $paisId): ?array
    {
        return $this->geografiaRepo->buscarPaisPorId($paisId);
    }

    /**
     * Retorna la jerarquía completa de un distrito (distrito, provincia, departamento, país, ubigeo).
     *
     * @param int $distritoId
     * @return array<string, mixed>|null
     */
    public function obtenerJerarquiaPorDistritoId(int $distritoId): ?array
    {
        return $this->geografiaRepo->buscarJerarquiaPorDistritoId($distritoId);
    }

    /**
     * Valida estrictamente la coherencia y consistencia territorial de una ubicación de residencia.
     *
     * Reglas vinculantes:
     * 1. Si pais_residencia es Perú:
     *    - distrito_id es obligatorio en registros completos.
     *    - La terna departamento -> provincia -> distrito debe ser matemáticamente consistente.
     *    - No se admiten valores en campos de residencia extranjera.
     * 2. Si pais_residencia es extranjero:
     *    - distrito_id debe ser estrictamente NULL.
     *    - Se permiten región y ciudad extranjera como texto libre.
     *
     * @param int|null $paisResidenciaId
     * @param int|null $distritoId
     * @param string|null $regionExtranjera
     * @param string|null $ciudadExtranjera
     * @param int|null $provinciaId
     * @param int|null $departamentoId
     * @throws ValidacionExcepcion
     */
    public function validarUbicacion(
        ?int $paisResidenciaId,
        ?int $distritoId = null,
        ?string $regionExtranjera = null,
        ?string $ciudadExtranjera = null,
        ?int $provinciaId = null,
        ?int $departamentoId = null
    ): void {
        if ($paisResidenciaId === null || $paisResidenciaId <= 0) {
            return; // Residencia no provista (compatible con históricos incompletos)
        }

        $pais = $this->geografiaRepo->buscarPaisPorId($paisResidenciaId);
        if ($pais === null) {
            throw new ValidacionExcepcion("El país de residencia [{$paisResidenciaId}] no existe o está inactivo.");
        }

        $esPeru = ($pais['codigo_iso2'] === 'PE');

        if ($esPeru) {
            // Regla: En Perú no aplican campos de residencia extranjera
            if (!empty($regionExtranjera) || !empty($ciudadExtranjera)) {
                throw new ValidacionExcepcion('No se permiten campos de residencia extranjera cuando el país de residencia es Perú.');
            }

            if ($distritoId !== null && $distritoId > 0) {
                $jerarquia = $this->geografiaRepo->buscarJerarquiaPorDistritoId($distritoId);
                if ($jerarquia === null) {
                    throw new ValidacionExcepcion("El distrito [{$distritoId}] no existe en el catálogo territorial peruano.");
                }

                if ($departamentoId !== null && (int) $jerarquia['departamento_id'] !== $departamentoId) {
                    throw new ValidacionExcepcion("Inconsistencia territorial: El distrito no pertenece al departamento seleccionado.");
                }

                if ($provinciaId !== null && (int) $jerarquia['provincia_id'] !== $provinciaId) {
                    throw new ValidacionExcepcion("Inconsistencia territorial: El distrito no pertenece a la provincia seleccionada.");
                }
            }
        } else {
            // Regla: En país extranjero no se permite distrito peruano
            if ($distritoId !== null && $distritoId > 0) {
                throw new ValidacionExcepcion('No se puede asignar un distrito peruano cuando el país de residencia es extranjero.');
            }
        }
    }
}
