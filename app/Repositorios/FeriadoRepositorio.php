<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\Feriado;
use PDO;

/**
 * Repositorio para la gestión del calendario de feriados y días no laborables (RECLAMACIONES-1).
 */
class FeriadoRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    public function buscarPorId(int $id): ?Feriado
    {
        $stmt = $this->pdo->prepare('SELECT * FROM calendario_feriados WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? Feriado::desdeArreglo($fila) : null;
    }

    public function buscarPorFecha(string $fecha): ?Feriado
    {
        $stmt = $this->pdo->prepare('SELECT * FROM calendario_feriados WHERE fecha = :fecha LIMIT 1');
        $stmt->execute(['fecha' => trim($fecha)]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? Feriado::desdeArreglo($fila) : null;
    }

    /**
     * @param array<string, mixed> $filtros
     * @return array<Feriado>
     */
    public function listarTodos(array $filtros = []): array
    {
        $sql = 'SELECT * FROM calendario_feriados WHERE 1=1';
        $params = [];

        if (!empty($filtros['anio'])) {
            $sql .= ' AND YEAR(fecha) = :anio';
            $params['anio'] = (int) $filtros['anio'];
        }

        if (isset($filtros['activo']) && $filtros['activo'] !== '') {
            $sql .= ' AND activo = :activo';
            $params['activo'] = (int) $filtros['activo'];
        }

        if (!empty($filtros['tipo'])) {
            $sql .= ' AND tipo = :tipo';
            $params['tipo'] = $filtros['tipo'];
        }

        $sql .= ' ORDER BY fecha ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn(array $f) => Feriado::desdeArreglo($f), $filas);
    }

    /**
     * Obtiene una lista indexada de fechas 'YYYY-MM-DD' que son feriados aplicables al sector privado.
     *
     * @param string $fechaInicio 'YYYY-MM-DD'
     * @param string $fechaFin 'YYYY-MM-DD'
     * @return array<string, string> Arreglo con clave y valor igual a la fecha 'YYYY-MM-DD'
     */
    public function obtenerFechasFeriadosSectorPrivado(string $fechaInicio, string $fechaFin): array
    {
        $sql = 'SELECT fecha FROM calendario_feriados
                WHERE fecha >= :inicio AND fecha <= :fin
                  AND activo = 1 AND aplica_sector_privado = 1
                ORDER BY fecha ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'inicio' => $fechaInicio,
            'fin' => $fechaFin,
        ]);

        $fechas = [];
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $f = (string) $fila['fecha'];
            $fechas[$f] = $f;
        }

        return $fechas;
    }

    public function guardar(Feriado $feriado): Feriado
    {
        if ($feriado->obtenerId() !== null) {
            $sql = 'UPDATE calendario_feriados SET
                        fecha = :fecha,
                        descripcion = :descripcion,
                        tipo = :tipo,
                        aplica_sector_privado = :aplica_sector_privado,
                        activo = :activo,
                        actualizado_en = NOW()
                    WHERE id = :id';

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'id' => $feriado->obtenerId(),
                'fecha' => $feriado->obtenerFecha(),
                'descripcion' => $feriado->obtenerDescripcion(),
                'tipo' => $feriado->obtenerTipo(),
                'aplica_sector_privado' => $feriado->aplicaSectorPrivado() ? 1 : 0,
                'activo' => $feriado->esActivo() ? 1 : 0,
            ]);

            return $this->buscarPorId($feriado->obtenerId()) ?? $feriado;
        }

        $sql = 'INSERT INTO calendario_feriados (
                    fecha, descripcion, tipo, aplica_sector_privado, activo, creado_en, actualizado_en
                ) VALUES (
                    :fecha, :descripcion, :tipo, :aplica_sector_privado, :activo, NOW(), NOW()
                )';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'fecha' => $feriado->obtenerFecha(),
            'descripcion' => $feriado->obtenerDescripcion(),
            'tipo' => $feriado->obtenerTipo(),
            'aplica_sector_privado' => $feriado->aplicaSectorPrivado() ? 1 : 0,
            'activo' => $feriado->esActivo() ? 1 : 0,
        ]);

        $nuevoId = (int) $this->pdo->lastInsertId();
        return $this->buscarPorId($nuevoId) ?? $feriado;
    }

    public function alternarEstado(int $id): bool
    {
        $stmt = $this->pdo->prepare('UPDATE calendario_feriados SET activo = IF(activo = 1, 0, 1), actualizado_en = NOW() WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }
}
