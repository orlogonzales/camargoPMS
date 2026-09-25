<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\Colaborador;
use CamargoPMS\Nucleo\BaseDatos;
use PDO;

/**
 * Repositorio de persistencia para colaboradores de la organización.
 *
 * Mantiene la relación 1:1 lógica con la entidad Persona y coordina
 * la hidratación del historial laboral.
 */
class ColaboradorRepositorio
{
    private PDO $pdo;
    private ?PersonaRepositorio $personaRepo;
    private ?EpisodioLaboralRepositorio $episodioRepo;

    public function __construct(
        ?PDO $pdo = null,
        ?PersonaRepositorio $personaRepo = null,
        ?EpisodioLaboralRepositorio $episodioRepo = null
    ) {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->personaRepo = $personaRepo ?? new PersonaRepositorio($this->pdo);
        $this->episodioRepo = $episodioRepo ?? new EpisodioLaboralRepositorio($this->pdo);
    }

    /**
     * Busca un colaborador por su identificador primario.
     *
     * @param int $id
     * @param bool $cargarRelaciones
     * @return Colaborador|null
     */
    public function buscarPorId(int $id, bool $cargarRelaciones = true): ?Colaborador
    {
        $sql = "SELECT * FROM colaboradores WHERE id = :id LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch();
        if (!$fila) {
            return null;
        }

        $colaborador = Colaborador::desdeArreglo($fila);
        if ($cargarRelaciones) {
            $this->hidratarRelaciones($colaborador);
        }

        return $colaborador;
    }

    /**
     * Busca un colaborador por el ID de la persona asociada (relación 1:1 lógica).
     *
     * @param int $personaId
     * @param bool $cargarRelaciones
     * @return Colaborador|null
     */
    public function buscarPorPersonaId(int $personaId, bool $cargarRelaciones = true): ?Colaborador
    {
        $sql = "SELECT * FROM colaboradores WHERE persona_id = :persona_id LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':persona_id', $personaId, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch();
        if (!$fila) {
            return null;
        }

        $colaborador = Colaborador::desdeArreglo($fila);
        if ($cargarRelaciones) {
            $this->hidratarRelaciones($colaborador);
        }

        return $colaborador;
    }

    /**
     * Busca un colaborador por su código interno único (ej. 'COL-0001').
     *
     * @param string $codigo
     * @param bool $cargarRelaciones
     * @return Colaborador|null
     */
    public function buscarPorCodigo(string $codigo, bool $cargarRelaciones = true): ?Colaborador
    {
        $sql = "SELECT * FROM colaboradores WHERE codigo = :codigo LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':codigo', strtoupper(trim($codigo)), PDO::PARAM_STR);
        $stmt->execute();

        $fila = $stmt->fetch();
        if (!$fila) {
            return null;
        }

        $colaborador = Colaborador::desdeArreglo($fila);
        if ($cargarRelaciones) {
            $this->hidratarRelaciones($colaborador);
        }

        return $colaborador;
    }

    /**
     * Lista colaboradores activos.
     *
     * @param int $limite
     * @param int $offset
     * @param bool $cargarRelaciones
     * @return array<int, Colaborador>
     */
    public function listarActivos(int $limite = 50, int $offset = 0, bool $cargarRelaciones = true): array
    {
        $sql = "SELECT * FROM colaboradores 
                WHERE estado = 'ACTIVO' 
                ORDER BY id ASC 
                LIMIT :limite OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $colaboradores = [];
        while ($fila = $stmt->fetch()) {
            $colab = Colaborador::desdeArreglo($fila);
            if ($cargarRelaciones) {
                $this->hidratarRelaciones($colab);
            }
            $colaboradores[] = $colab;
        }

        return $colaboradores;
    }

    /**
     * Inserta un nuevo colaborador en la base de datos.
     *
     * @param Colaborador $colaborador
     * @return Colaborador
     */
    public function insertar(Colaborador $colaborador): Colaborador
    {
        $sql = "INSERT INTO colaboradores (
                    persona_id,
                    codigo,
                    estado,
                    creado_en,
                    actualizado_en
                ) VALUES (
                    :persona_id,
                    :codigo,
                    :estado,
                    NOW(),
                    NOW()
                )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':persona_id', $colaborador->obtenerPersonaId(), PDO::PARAM_INT);
        $stmt->bindValue(':codigo', $colaborador->obtenerCodigo(), PDO::PARAM_STR);
        $stmt->bindValue(':estado', $colaborador->obtenerEstado(), PDO::PARAM_STR);
        $stmt->execute();

        $nuevoId = (int) $this->pdo->lastInsertId();
        return $this->buscarPorId($nuevoId, false) ?? $colaborador;
    }

    /**
     * Actualiza el estado de un colaborador.
     *
     * @param int $id
     * @param string $estado
     * @return bool
     */
    public function actualizarEstado(int $id, string $estado): bool
    {
        $sql = "UPDATE colaboradores 
                SET estado = :estado,
                    actualizado_en = NOW() 
                WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':estado', strtoupper(trim($estado)), PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * Genera el siguiente código secuencial disponible para colaboradores (ej. 'COL-0001').
     *
     * @return string
     */
    public function generarSiguienteCodigo(): string
    {
        $sql = "SELECT MAX(id) AS max_id FROM colaboradores";
        $stmt = $this->pdo->query($sql);
        $fila = $stmt->fetch();
        $siguienteNumero = ($fila && isset($fila['max_id']) ? (int) $fila['max_id'] : 0) + 1;

        return sprintf('COL-%04d', $siguienteNumero);
    }

    /**
     * Hidrata las relaciones de Persona y Episodios para un colaborador.
     *
     * @param Colaborador $colaborador
     */
    private function hidratarRelaciones(Colaborador $colaborador): void
    {
        $persona = $this->personaRepo->buscarPorId($colaborador->obtenerPersonaId(), true);
        if ($persona) {
            $colaborador->asignarPersona($persona);
        }

        if ($colaborador->obtenerId() !== null) {
            $episodios = $this->episodioRepo->listarPorColaboradorId((int) $colaborador->obtenerId(), true);
            $colaborador->asignarEpisodios($episodios);
        }
    }
}
