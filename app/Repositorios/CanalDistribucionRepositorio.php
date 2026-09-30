<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\CanalDistribucion;
use CamargoPMS\Nucleo\BaseDatos;
use PDO;

/**
 * Repositorio de persistencia PDO para el Catálogo Maestro de Canales de Distribución.
 */
class CanalDistribucionRepositorio
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? BaseDatos::conexion();
    }

    public function buscarPorId(int $id): ?CanalDistribucion
    {
        $sql = 'SELECT * FROM canales_distribucion WHERE id = :id LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? $this->mapearFila($fila) : null;
    }

    public function buscarPorCodigo(string $codigo): ?CanalDistribucion
    {
        $sql = 'SELECT * FROM canales_distribucion WHERE codigo = :codigo LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['codigo' => strtoupper(trim($codigo))]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? $this->mapearFila($fila) : null;
    }

    /**
     * @return array<CanalDistribucion>
     */
    public function listarActivos(): array
    {
        $sql = "SELECT * FROM canales_distribucion WHERE estado = 'ACTIVO' ORDER BY id ASC";
        $stmt = $this->pdo->query($sql);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'mapearFila'], $filas);
    }

    /**
     * @return array<CanalDistribucion>
     */
    public function listarTodos(): array
    {
        $sql = "SELECT * FROM canales_distribucion ORDER BY id ASC";
        $stmt = $this->pdo->query($sql);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'mapearFila'], $filas);
    }

    /**
     * @param array<string, mixed> $fila
     */
    private function mapearFila(array $fila): CanalDistribucion
    {
        return new CanalDistribucion(
            id: (int) $fila['id'],
            codigo: (string) $fila['codigo'],
            nombre: (string) $fila['nombre'],
            protocolo: (string) ($fila['protocolo'] ?? 'ICAL'),
            frecuenciaDefectoMinutos: (int) ($fila['frecuencia_defecto_minutos'] ?? 60),
            colorBadge: (string) ($fila['color_badge'] ?? 'badge-light-primary'),
            estado: (string) ($fila['estado'] ?? CanalDistribucion::ESTADO_ACTIVO),
            creadoEn: (string) ($fila['creado_en'] ?? ''),
            actualizadoEn: (string) ($fila['actualizado_en'] ?? '')
        );
    }
}
