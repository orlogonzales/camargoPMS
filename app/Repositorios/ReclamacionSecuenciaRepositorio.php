<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use PDO;

/**
 * Repositorio de secuencias atómicas por sede y año para el Libro de Reclamaciones (RECLAMACIONES-1).
 */
class ReclamacionSecuenciaRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Obtiene el siguiente correlativo secuencial para una propiedad y año dados.
     * DEBE ejecutarse dentro de una transacción activa con bloqueo pesimista (FOR UPDATE).
     */
    public function obtenerYSiguienteCorrelativo(int $propiedadId, int $anio): int
    {
        $stmtSelect = $this->pdo->prepare(
            'SELECT id, ultimo_numero FROM reclamacion_secuencias 
             WHERE propiedad_id = :propiedad_id AND anio = :anio 
             FOR UPDATE'
        );
        $stmtSelect->execute([
            'propiedad_id' => $propiedadId,
            'anio' => $anio,
        ]);

        $fila = $stmtSelect->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            $stmtInsert = $this->pdo->prepare(
                'INSERT INTO reclamacion_secuencias (propiedad_id, anio, ultimo_numero, actualizado_en) 
                 VALUES (:propiedad_id, :anio, 1, NOW())'
            );
            $stmtInsert->execute([
                'propiedad_id' => $propiedadId,
                'anio' => $anio,
            ]);

            return 1;
        }

        $siguiente = ((int) $fila['ultimo_numero']) + 1;

        $stmtUpdate = $this->pdo->prepare(
            'UPDATE reclamacion_secuencias 
             SET ultimo_numero = :siguiente, actualizado_en = NOW() 
             WHERE id = :id'
        );
        $stmtUpdate->execute([
            'siguiente' => $siguiente,
            'id' => (int) $fila['id'],
        ]);

        return $siguiente;
    }
}
