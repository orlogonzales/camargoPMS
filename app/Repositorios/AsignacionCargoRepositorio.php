<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\AsignacionCargo;
use CamargoPMS\Modelos\Cargo;
use CamargoPMS\Nucleo\BaseDatos;
use PDO;

/**
 * Repositorio de persistencia para las asignaciones de cargo dentro de episodios laborales.
 *
 * Mantiene la trazabilidad histórica de ascensos o cambios funcionales.
 */
class AsignacionCargoRepositorio
{
    private PDO $pdo;
    private ?CargoRepositorio $cargoRepo;

    public function __construct(?PDO $pdo = null, ?CargoRepositorio $cargoRepo = null)
    {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->cargoRepo = $cargoRepo ?? new CargoRepositorio($this->pdo);
    }

    /**
     * Busca una asignación de cargo por su identificador primario.
     *
     * @param int $id
     * @param bool $cargarCargo
     * @return AsignacionCargo|null
     */
    public function buscarPorId(int $id, bool $cargarCargo = true): ?AsignacionCargo
    {
        $sql = "SELECT * FROM episodios_laborales_cargos WHERE id = :id LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch();
        if (!$fila) {
            return null;
        }

        $asignacion = AsignacionCargo::desdeArreglo($fila);
        if ($cargarCargo) {
            $asignacion->asignarCargo($this->cargoRepo->buscarPorId($asignacion->obtenerCargoId()));
        }

        return $asignacion;
    }

    /**
     * Busca la asignación de cargo activa (abierta, fecha_fin IS NULL) de un episodio laboral.
     *
     * @param int $episodioLaboralId
     * @param bool $cargarCargo
     * @return AsignacionCargo|null
     */
    public function buscarAsignacionActiva(int $episodioLaboralId, bool $cargarCargo = true): ?AsignacionCargo
    {
        $sql = "SELECT * FROM episodios_laborales_cargos 
                WHERE episodio_laboral_id = :episodio_id 
                  AND fecha_fin IS NULL 
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':episodio_id', $episodioLaboralId, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch();
        if (!$fila) {
            return null;
        }

        $asignacion = AsignacionCargo::desdeArreglo($fila);
        if ($cargarCargo) {
            $asignacion->asignarCargo($this->cargoRepo->buscarPorId($asignacion->obtenerCargoId()));
        }

        return $asignacion;
    }

    /**
     * Alias de buscarAsignacionActiva para consistencia de nomenclatura.
     *
     * @param int $episodioLaboralId
     * @param bool $cargarCargo
     * @return AsignacionCargo|null
     */
    public function buscarAbiertaPorEpisodioId(int $episodioLaboralId, bool $cargarCargo = true): ?AsignacionCargo
    {
        return $this->buscarAsignacionActiva($episodioLaboralId, $cargarCargo);
    }

    /**
     * Lista todas las asignaciones de cargo de un episodio laboral ordenadas cronológicamente.
     *
     * @param int $episodioLaboralId
     * @param bool $cargarCargo
     * @return array<int, AsignacionCargo>
     */
    public function listarPorEpisodioId(int $episodioLaboralId, bool $cargarCargo = true): array
    {
        $sql = "SELECT * FROM episodios_laborales_cargos 
                WHERE episodio_laboral_id = :episodio_id 
                ORDER BY fecha_inicio ASC, id ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':episodio_id', $episodioLaboralId, PDO::PARAM_INT);
        $stmt->execute();

        $asignaciones = [];
        while ($fila = $stmt->fetch()) {
            $asig = AsignacionCargo::desdeArreglo($fila);
            if ($cargarCargo) {
                $asig->asignarCargo($this->cargoRepo->buscarPorId($asig->obtenerCargoId()));
            }
            $asignaciones[] = $asig;
        }

        return $asignaciones;
    }

    /**
     * Inserta una nueva asignación de cargo.
     *
     * @param AsignacionCargo $asignacion
     * @return AsignacionCargo
     */
    public function insertar(AsignacionCargo $asignacion): AsignacionCargo
    {
        $sql = "INSERT INTO episodios_laborales_cargos (
                    episodio_laboral_id,
                    cargo_id,
                    fecha_inicio,
                    fecha_fin,
                    observaciones,
                    creado_en,
                    actualizado_en
                ) VALUES (
                    :episodio_id,
                    :cargo_id,
                    :fecha_inicio,
                    :fecha_fin,
                    :observaciones,
                    NOW(),
                    NOW()
                )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':episodio_id', $asignacion->obtenerEpisodioLaboralId(), PDO::PARAM_INT);
        $stmt->bindValue(':cargo_id', $asignacion->obtenerCargoId(), PDO::PARAM_INT);
        $stmt->bindValue(':fecha_inicio', $asignacion->obtenerFechaInicio(), PDO::PARAM_STR);
        $stmt->bindValue(':fecha_fin', $asignacion->obtenerFechaFin(), PDO::PARAM_STR);
        $stmt->bindValue(':observaciones', $asignacion->obtenerObservaciones(), PDO::PARAM_STR);
        $stmt->execute();

        $nuevoId = (int) $this->pdo->lastInsertId();
        return $this->buscarPorId($nuevoId) ?? $asignacion;
    }

    /**
     * Alias de insertar para cumplimiento de especificación.
     *
     * @param AsignacionCargo $asignacion
     * @return AsignacionCargo
     */
    public function insertarAsignacion(AsignacionCargo $asignacion): AsignacionCargo
    {
        return $this->insertar($asignacion);
    }

    /**
     * Cierra una asignación de cargo estableciendo su fecha de finalización.
     *
     * @param int $id
     * @param string $fechaFin
     * @param string|null $observaciones
     * @return bool
     */
    public function cerrarAsignacion(int $id, string $fechaFin, ?string $observaciones = null): bool
    {
        $sql = "UPDATE episodios_laborales_cargos 
                SET fecha_fin = :fecha_fin,
                    observaciones = COALESCE(:observaciones, observaciones),
                    actualizado_en = NOW()
                WHERE id = :id AND fecha_fin IS NULL";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':fecha_fin', trim($fechaFin), PDO::PARAM_STR);
        $stmt->bindValue(':observaciones', $observaciones !== null && trim($observaciones) !== '' ? trim($observaciones) : null, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }
}
