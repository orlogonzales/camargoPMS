<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\DocumentoPersona;
use CamargoPMS\Nucleo\BaseDatos;
use PDO;

/**
 * Repositorio de persistencia para documentos de identidad de personas naturales.
 */
class DocumentoPersonaRepositorio
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? BaseDatos::conexion();
    }

    public function buscarPorId(int $id): ?DocumentoPersona
    {
        $sql = "SELECT * FROM personas_documentos WHERE id = :id LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch();
        return $fila ? DocumentoPersona::desdeArreglo($fila) : null;
    }

    /**
     * Busca un documento por su tipo, país emisor real y número normalizado.
     * Considera la jurisdicción internacional para permitir que distintos países
     * emitan el mismo número bajo el tipo genérico Pasaporte.
     *
     * @param int $tipoDocumentoId
     * @param int $paisEmisorId
     * @param string $numeroDocumento
     * @return DocumentoPersona|null
     */
    public function buscarPorTipoPaisYNumero(int $tipoDocumentoId, int $paisEmisorId, string $numeroDocumento): ?DocumentoPersona
    {
        $sql = "SELECT * FROM personas_documentos
                WHERE tipo_documento_id = :tipo_id
                  AND pais_emisor_id = :pais_emisor_id
                  AND numero_documento = :numero
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':tipo_id', $tipoDocumentoId, PDO::PARAM_INT);
        $stmt->bindValue(':pais_emisor_id', $paisEmisorId, PDO::PARAM_INT);
        $stmt->bindValue(':numero', strtoupper(trim($numeroDocumento)), PDO::PARAM_STR);
        $stmt->execute();

        $fila = $stmt->fetch();
        return $fila ? DocumentoPersona::desdeArreglo($fila) : null;
    }

    /**
     * Busca un documento por su tipo y número normalizado.
     *
     * @param int $tipoDocumentoId
     * @param string $numeroDocumento
     * @return DocumentoPersona|null
     */
    public function buscarPorTipoYNumero(int $tipoDocumentoId, string $numeroDocumento): ?DocumentoPersona
    {
        $sql = "SELECT * FROM personas_documentos 
                WHERE tipo_documento_id = :tipo_id AND numero_documento = :numero 
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':tipo_id', $tipoDocumentoId, PDO::PARAM_INT);
        $stmt->bindValue(':numero', strtoupper(trim($numeroDocumento)), PDO::PARAM_STR);
        $stmt->execute();

        $fila = $stmt->fetch();
        return $fila ? DocumentoPersona::desdeArreglo($fila) : null;
    }


    /**
     * Lista los documentos pertenecientes a una persona.
     *
     * @param int $personaId
     * @param bool $soloActivos
     * @return array<int, DocumentoPersona>
     */
    public function listarPorPersonaId(int $personaId, bool $soloActivos = true): array
    {
        $sql = "SELECT * FROM personas_documentos WHERE persona_id = :persona_id";
        if ($soloActivos) {
            $sql .= " AND estado = 'ACTIVO'";
        }
        $sql .= " ORDER BY es_principal DESC, id ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':persona_id', $personaId, PDO::PARAM_INT);
        $stmt->execute();

        $resultado = [];
        while ($fila = $stmt->fetch()) {
            $resultado[] = DocumentoPersona::desdeArreglo($fila);
        }

        return $resultado;
    }

    /**
     * Inserta un nuevo documento para una persona.
     *
     * @param DocumentoPersona $doc
     * @return int ID insertado.
     */
    public function insertar(DocumentoPersona $doc): int
    {
        $sql = "INSERT INTO personas_documentos (
                    persona_id, tipo_documento_id, numero_documento, 
                    pais_emisor_id, es_principal, fecha_emision, 
                    fecha_vencimiento, estado
                ) VALUES (
                    :persona_id, :tipo_id, :numero, 
                    :pais_emisor_id, :es_principal, :fecha_emision, 
                    :fecha_vencimiento, :estado
                )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':persona_id', $doc->obtenerPersonaId(), PDO::PARAM_INT);
        $stmt->bindValue(':tipo_id', $doc->obtenerTipoDocumentoId(), PDO::PARAM_INT);
        $stmt->bindValue(':numero', $doc->obtenerNumeroDocumento(), PDO::PARAM_STR);
        $stmt->bindValue(':pais_emisor_id', $doc->obtenerPaisEmisorId(), PDO::PARAM_INT);
        $stmt->bindValue(':es_principal', $doc->esPrincipal() ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':fecha_emision', $doc->obtenerFechaEmision(), $doc->obtenerFechaEmision() ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':fecha_vencimiento', $doc->obtenerFechaVencimiento(), $doc->obtenerFechaVencimiento() ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':estado', $doc->obtenerEstado(), PDO::PARAM_STR);
        $stmt->execute();

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Desmarca todos los documentos principales activos de una persona.
     *
     * @param int $personaId
     * @return bool
     */
    public function desmarcarPrincipalesDePersona(int $personaId): bool
    {
        $sql = "UPDATE personas_documentos 
                SET es_principal = 0 
                WHERE persona_id = :persona_id AND es_principal = 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':persona_id', $personaId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Establece un documento específico como principal para su persona asociada.
     *
     * @param int $documentoId
     * @param int $personaId
     * @return bool
     */
    public function marcarComoPrincipal(int $documentoId, int $personaId): bool
    {
        $this->desmarcarPrincipalesDePersona($personaId);

        $sql = "UPDATE personas_documentos 
                SET es_principal = 1 
                WHERE id = :id AND persona_id = :persona_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $documentoId, PDO::PARAM_INT);
        $stmt->bindValue(':persona_id', $personaId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Desactiva lógicamente un documento (soft delete).
     *
     * @param int $id
     * @return bool
     */
    public function desactivar(int $id): bool
    {
        $sql = "UPDATE personas_documentos 
                SET estado = 'INACTIVO', es_principal = 0 
                WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
