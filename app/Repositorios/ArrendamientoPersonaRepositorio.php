<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\ArrendamientoPersona;
use PDO;

/**
 * Repositorio para la gestión de las partes (titular, cotitulares, ocupantes) de contratos de arrendamiento.
 */
class ArrendamientoPersonaRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    public function asociar(ArrendamientoPersona $relacion): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO arrendamiento_personas (
                arrendamiento_id, persona_id, tipo_relacion, observaciones, creado_en
            ) VALUES (
                :arrendamiento_id, :persona_id, :tipo_relacion, :observaciones, NOW()
            )'
        );

        $stmt->execute([
            'arrendamiento_id' => $relacion->obtenerArrendamientoId(),
            'persona_id' => $relacion->obtenerPersonaId(),
            'tipo_relacion' => $relacion->obtenerTipoRelacion(),
            'observaciones' => $relacion->obtenerObservaciones(),
        ]);
    }

    public function eliminar(int $arrendamientoId, int $personaId): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM arrendamiento_personas WHERE arrendamiento_id = :arrendamiento_id AND persona_id = :persona_id'
        );
        $stmt->execute([
            'arrendamiento_id' => $arrendamientoId,
            'persona_id' => $personaId,
        ]);
    }

    public function obtenerTitular(int $arrendamientoId): ?ArrendamientoPersona
    {
        $sql = 'SELECT 
                    ap.*,
                    TRIM(CONCAT(p.nombres, " ", p.apellido_paterno, " ", COALESCE(p.apellido_materno, ""))) AS nombre_completo,
                    td.nombre AS tipo_documento,
                    pd.numero_documento,
                    COALESCE((SELECT c.valor FROM personas_contactos c WHERE c.persona_id = p.id AND c.tipo_contacto = "TELEFONO_MOVIL" LIMIT 1), "") AS telefono,
                    COALESCE((SELECT c.valor FROM personas_contactos c WHERE c.persona_id = p.id AND c.tipo_contacto = "CORREO_ELECTRONICO" LIMIT 1), "") AS correo_electronico
                FROM arrendamiento_personas ap
                INNER JOIN personas p ON p.id = ap.persona_id
                LEFT JOIN personas_documentos pd ON pd.persona_id = p.id
                LEFT JOIN tipos_documento td ON td.id = pd.tipo_documento_id
                WHERE ap.arrendamiento_id = :arrendamiento_id AND ap.tipo_relacion = "TITULAR"
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['arrendamiento_id' => $arrendamientoId]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? ArrendamientoPersona::desdeArreglo($fila) : null;
    }

    /**
     * @return array<ArrendamientoPersona>
     */
    public function listarPorArrendamientoId(int $arrendamientoId): array
    {
        $sql = 'SELECT 
                    ap.*,
                    TRIM(CONCAT(p.nombres, " ", p.apellido_paterno, " ", COALESCE(p.apellido_materno, ""))) AS nombre_completo,
                    td.nombre AS tipo_documento,
                    pd.numero_documento,
                    COALESCE((SELECT c.valor FROM personas_contactos c WHERE c.persona_id = p.id AND c.tipo_contacto = "TELEFONO_MOVIL" LIMIT 1), "") AS telefono,
                    COALESCE((SELECT c.valor FROM personas_contactos c WHERE c.persona_id = p.id AND c.tipo_contacto = "CORREO_ELECTRONICO" LIMIT 1), "") AS correo_electronico
                FROM arrendamiento_personas ap
                INNER JOIN personas p ON p.id = ap.persona_id
                LEFT JOIN personas_documentos pd ON pd.persona_id = p.id
                LEFT JOIN tipos_documento td ON td.id = pd.tipo_documento_id
                WHERE ap.arrendamiento_id = :arrendamiento_id
                ORDER BY CASE ap.tipo_relacion 
                    WHEN "TITULAR" THEN 1 
                    WHEN "COTITULAR" THEN 2 
                    ELSE 3 
                END, ap.persona_id ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['arrendamiento_id' => $arrendamientoId]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resultado = [];
        foreach ($filas as $fila) {
            $resultado[] = ArrendamientoPersona::desdeArreglo($fila);
        }

        return $resultado;
    }

    public function existePersonaEnArrendamiento(int $arrendamientoId, int $personaId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM arrendamiento_personas WHERE arrendamiento_id = :arrendamiento_id AND persona_id = :persona_id'
        );
        $stmt->execute([
            'arrendamiento_id' => $arrendamientoId,
            'persona_id' => $personaId,
        ]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function existeTitular(int $arrendamientoId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM arrendamiento_personas WHERE arrendamiento_id = :arrendamiento_id AND tipo_relacion = "TITULAR"'
        );
        $stmt->execute(['arrendamiento_id' => $arrendamientoId]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
