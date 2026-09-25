<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\ContactoPersona;
use CamargoPMS\Nucleo\BaseDatos;
use PDO;

/**
 * Repositorio de persistencia para medios de contacto de personas naturales.
 */
class ContactoPersonaRepositorio
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? BaseDatos::conexion();
    }

    public function buscarPorId(int $id): ?ContactoPersona
    {
        $sql = "SELECT * FROM personas_contactos WHERE id = :id LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch();
        return $fila ? ContactoPersona::desdeArreglo($fila) : null;
    }

    /**
     * Lista los contactos de una persona.
     *
     * @param int $personaId
     * @param bool $soloActivos
     * @return array<int, ContactoPersona>
     */
    public function listarPorPersonaId(int $personaId, bool $soloActivos = true): array
    {
        $sql = "SELECT * FROM personas_contactos WHERE persona_id = :persona_id";
        if ($soloActivos) {
            $sql .= " AND estado = 'ACTIVO'";
        }
        $sql .= " ORDER BY tipo_contacto ASC, es_principal DESC, id ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':persona_id', $personaId, PDO::PARAM_INT);
        $stmt->execute();

        $resultado = [];
        while ($fila = $stmt->fetch()) {
            $resultado[] = ContactoPersona::desdeArreglo($fila);
        }

        return $resultado;
    }

    /**
     * Inserta un nuevo medio de contacto para una persona.
     *
     * @param ContactoPersona $contacto
     * @return int ID insertado.
     */
    public function insertar(ContactoPersona $contacto): int
    {
        $sql = "INSERT INTO personas_contactos (
                    persona_id, tipo_contacto, valor, 
                    es_whatsapp, es_principal, estado
                ) VALUES (
                    :persona_id, :tipo, :valor, 
                    :es_whatsapp, :es_principal, :estado
                )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':persona_id', $contacto->obtenerPersonaId(), PDO::PARAM_INT);
        $stmt->bindValue(':tipo', $contacto->obtenerTipoContacto(), PDO::PARAM_STR);
        $stmt->bindValue(':valor', $contacto->obtenerValor(), PDO::PARAM_STR);
        $stmt->bindValue(':es_whatsapp', $contacto->esWhatsapp() ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':es_principal', $contacto->esPrincipal() ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':estado', $contacto->obtenerEstado(), PDO::PARAM_STR);
        $stmt->execute();

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Desmarca los contactos principales de una persona para un tipo de contacto específico.
     *
     * @param int $personaId
     * @param string $tipoContacto
     * @return bool
     */
    public function desmarcarPrincipalesDePersonaYTipo(int $personaId, string $tipoContacto): bool
    {
        $sql = "UPDATE personas_contactos 
                SET es_principal = 0 
                WHERE persona_id = :persona_id AND tipo_contacto = :tipo AND es_principal = 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':persona_id', $personaId, PDO::PARAM_INT);
        $stmt->bindValue(':tipo', strtoupper(trim($tipoContacto)), PDO::PARAM_STR);
        return $stmt->execute();
    }

    /**
     * Establece un contacto específico como principal para su persona y tipo.
     *
     * @param int $contactoId
     * @param int $personaId
     * @param string $tipoContacto
     * @return bool
     */
    public function marcarComoPrincipal(int $contactoId, int $personaId, string $tipoContacto): bool
    {
        $this->desmarcarPrincipalesDePersonaYTipo($personaId, $tipoContacto);

        $sql = "UPDATE personas_contactos 
                SET es_principal = 1 
                WHERE id = :id AND persona_id = :persona_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $contactoId, PDO::PARAM_INT);
        $stmt->bindValue(':persona_id', $personaId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Desactiva lógicamente un medio de contacto (soft delete).
     *
     * @param int $id
     * @return bool
     */
    public function desactivar(int $id): bool
    {
        $sql = "UPDATE personas_contactos 
                SET estado = 'INACTIVO', es_principal = 0 
                WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
