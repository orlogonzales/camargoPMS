<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\EpisodioLaboral;
use CamargoPMS\Nucleo\BaseDatos;
use PDO;

/**
 * Repositorio de persistencia para episodios laborales.
 *
 * Cada episodio representa una relación laboral continua desde la contratación hasta el cese.
 */
class EpisodioLaboralRepositorio
{
    private PDO $pdo;
    private ?AsignacionCargoRepositorio $asignacionRepo;

    public function __construct(?PDO $pdo = null, ?AsignacionCargoRepositorio $asignacionRepo = null)
    {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->asignacionRepo = $asignacionRepo ?? new AsignacionCargoRepositorio($this->pdo);
    }

    /**
     * Busca un episodio laboral por su ID primario.
     *
     * @param int $id
     * @param bool $cargarAsignaciones
     * @return EpisodioLaboral|null
     */
    public function buscarPorId(int $id, bool $cargarAsignaciones = true): ?EpisodioLaboral
    {
        $sql = "SELECT * FROM episodios_laborales WHERE id = :id LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch();
        if (!$fila) {
            return null;
        }

        $episodio = EpisodioLaboral::desdeArreglo($fila);
        if ($cargarAsignaciones) {
            $episodio->asignarHistorialCargos($this->asignacionRepo->listarPorEpisodioId((int) $episodio->obtenerId()));
        }

        return $episodio;
    }

    /**
     * Busca el episodio laboral activo (abierto, fecha_fin IS NULL y estado 'ACTIVO') de un colaborador.
     *
     * @param int $colaboradorId
     * @param bool $cargarAsignaciones
     * @return EpisodioLaboral|null
     */
    public function buscarEpisodioActivo(int $colaboradorId, bool $cargarAsignaciones = true): ?EpisodioLaboral
    {
        $sql = "SELECT * FROM episodios_laborales 
                WHERE colaborador_id = :colaborador_id 
                  AND fecha_fin IS NULL 
                  AND estado = 'ACTIVO' 
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':colaborador_id', $colaboradorId, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch();
        if (!$fila) {
            return null;
        }

        $episodio = EpisodioLaboral::desdeArreglo($fila);
        if ($cargarAsignaciones) {
            $episodio->asignarHistorialCargos($this->asignacionRepo->listarPorEpisodioId((int) $episodio->obtenerId()));
        }

        return $episodio;
    }

    /**
     * Alias de buscarEpisodioActivo.
     *
     * @param int $colaboradorId
     * @param bool $cargarAsignaciones
     * @return EpisodioLaboral|null
     */
    public function buscarAbiertoPorColaboradorId(int $colaboradorId, bool $cargarAsignaciones = true): ?EpisodioLaboral
    {
        return $this->buscarEpisodioActivo($colaboradorId, $cargarAsignaciones);
    }

    /**
     * Lista todos los episodios de un colaborador ordenados cronológicamente (más recientes primero).
     *
     * @param int $colaboradorId
     * @param bool $cargarAsignaciones
     * @return array<int, EpisodioLaboral>
     */
    public function listarPorColaboradorId(int $colaboradorId, bool $cargarAsignaciones = true): array
    {
        $sql = "SELECT * FROM episodios_laborales 
                WHERE colaborador_id = :colaborador_id 
                ORDER BY fecha_inicio DESC, id DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':colaborador_id', $colaboradorId, PDO::PARAM_INT);
        $stmt->execute();

        $episodios = [];
        while ($fila = $stmt->fetch()) {
            $episodio = EpisodioLaboral::desdeArreglo($fila);
            if ($cargarAsignaciones) {
                $episodio->asignarHistorialCargos($this->asignacionRepo->listarPorEpisodioId((int) $episodio->obtenerId()));
            }
            $episodios[] = $episodio;
        }

        return $episodios;
    }

    /**
     * Inserta un nuevo episodio laboral.
     *
     * @param EpisodioLaboral $episodio
     * @return EpisodioLaboral
     */
    public function insertar(EpisodioLaboral $episodio): EpisodioLaboral
    {
        $sql = "INSERT INTO episodios_laborales (
                    colaborador_id,
                    fecha_inicio,
                    fecha_fin,
                    motivo_cese,
                    observaciones,
                    estado,
                    creado_en,
                    actualizado_en
                ) VALUES (
                    :colaborador_id,
                    :fecha_inicio,
                    :fecha_fin,
                    :motivo_cese,
                    :observaciones,
                    :estado,
                    NOW(),
                    NOW()
                )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':colaborador_id', $episodio->obtenerColaboradorId(), PDO::PARAM_INT);
        $stmt->bindValue(':fecha_inicio', $episodio->obtenerFechaInicio(), PDO::PARAM_STR);
        $stmt->bindValue(':fecha_fin', $episodio->obtenerFechaFin(), PDO::PARAM_STR);
        $stmt->bindValue(':motivo_cese', $episodio->obtenerMotivoCese(), PDO::PARAM_STR);
        $stmt->bindValue(':observaciones', $episodio->obtenerObservaciones(), PDO::PARAM_STR);
        $stmt->bindValue(':estado', $episodio->obtenerEstado(), PDO::PARAM_STR);
        $stmt->execute();

        $nuevoId = (int) $this->pdo->lastInsertId();
        return $this->buscarPorId($nuevoId) ?? $episodio;
    }

    /**
     * Cierra un episodio laboral registrando fecha de término, motivo de cese y cambiando estado a CESADO.
     *
     * @param int $id
     * @param string $fechaFin
     * @param string $motivoCese
     * @param string|null $observaciones
     * @return bool
     */
    public function cerrarEpisodio(int $id, string $fechaFin, string $motivoCese, ?string $observaciones = null): bool
    {
        $sql = "UPDATE episodios_laborales 
                SET fecha_fin = :fecha_fin,
                    motivo_cese = :motivo_cese,
                    observaciones = COALESCE(:observaciones, observaciones),
                    estado = 'INACTIVO',
                    actualizado_en = NOW()
                WHERE id = :id AND fecha_fin IS NULL";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':fecha_fin', trim($fechaFin), PDO::PARAM_STR);
        $stmt->bindValue(':motivo_cese', strtoupper(trim($motivoCese)), PDO::PARAM_STR);
        $stmt->bindValue(':observaciones', $observaciones !== null && trim($observaciones) !== '' ? trim($observaciones) : null, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }
}
