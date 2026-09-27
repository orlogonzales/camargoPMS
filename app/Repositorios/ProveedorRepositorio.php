<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\Proveedor;
use CamargoPMS\Nucleo\BaseDatos;
use PDO;

/**
 * Repositorio de persistencia para el maestro de proveedores externos de servicios.
 */
class ProveedorRepositorio
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? BaseDatos::conexion();
    }

    public function obtenerPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Busca un proveedor por su ID primario.
     */
    public function buscarPorId(int $id): ?Proveedor
    {
        $sql = 'SELECT p.*,
                       TRIM(CONCAT(COALESCE(per.nombres, ""), " ", COALESCE(per.apellido_paterno, ""), " ", COALESCE(per.apellido_materno, ""))) AS persona_nombre_completo
                FROM proveedores p
                LEFT JOIN personas per ON p.persona_id = per.id
                WHERE p.id = :id
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fila) {
            return null;
        }

        return Proveedor::desdeArreglo($fila);
    }

    /**
     * Busca un proveedor por su código de negocio único.
     */
    public function buscarPorCodigo(string $codigo): ?Proveedor
    {
        $sql = 'SELECT p.*,
                       TRIM(CONCAT(COALESCE(per.nombres, ""), " ", COALESCE(per.apellido_paterno, ""), " ", COALESCE(per.apellido_materno, ""))) AS persona_nombre_completo
                FROM proveedores p
                LEFT JOIN personas per ON p.persona_id = per.id
                WHERE p.codigo = :codigo
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':codigo', trim(strtoupper($codigo)), PDO::PARAM_STR);
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fila) {
            return null;
        }

        return Proveedor::desdeArreglo($fila);
    }

    /**
     * Lista proveedores con filtros opcionales.
     *
     * @param array<string, mixed> $filtros
     * @return array<int, Proveedor>
     */
    public function listar(array $filtros = []): array
    {
        $sql = 'SELECT p.*,
                       TRIM(CONCAT(COALESCE(per.nombres, ""), " ", COALESCE(per.apellido_paterno, ""), " ", COALESCE(per.apellido_materno, ""))) AS persona_nombre_completo
                FROM proveedores p
                LEFT JOIN personas per ON p.persona_id = per.id
                WHERE 1 = 1';

        $params = [];

        if (!empty($filtros['tipo'])) {
            $sql .= ' AND p.tipo = :tipo';
            $params[':tipo'] = strtoupper(trim((string) $filtros['tipo']));
        }

        if (!empty($filtros['estado'])) {
            $sql .= ' AND p.estado = :estado';
            $params[':estado'] = strtoupper(trim((string) $filtros['estado']));
        }

        if (!empty($filtros['q'])) {
            $sql .= ' AND (p.codigo LIKE :q OR p.razon_social LIKE :q OR p.nombre_comercial LIKE :q OR p.numero_documento LIKE :q)';
            $params[':q'] = '%' . trim((string) $filtros['q']) . '%';
        }

        $sql .= ' ORDER BY p.razon_social ASC';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $clave => $valor) {
            $stmt->bindValue($clave, $valor);
        }
        $stmt->execute();

        $resultados = [];
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resultados[] = Proveedor::desdeArreglo($fila);
        }

        return $resultados;
    }

    /**
     * Inserta un nuevo proveedor en base de datos.
     */
    public function crear(Proveedor $proveedor): int
    {
        $sql = 'INSERT INTO proveedores (
                    codigo, tipo, persona_id, razon_social, nombre_comercial,
                    numero_documento, email, telefono, direccion, estado, observaciones
                ) VALUES (
                    :codigo, :tipo, :persona_id, :razon_social, :nombre_comercial,
                    :numero_documento, :email, :telefono, :direccion, :estado, :observaciones
                )';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':codigo', $proveedor->obtenerCodigo(), PDO::PARAM_STR);
        $stmt->bindValue(':tipo', $proveedor->obtenerTipo(), PDO::PARAM_STR);
        $stmt->bindValue(':persona_id', $proveedor->obtenerPersonaId(), $proveedor->obtenerPersonaId() !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':razon_social', $proveedor->obtenerRazonSocial(), PDO::PARAM_STR);
        $stmt->bindValue(':nombre_comercial', $proveedor->obtenerNombreComercial(), $proveedor->obtenerNombreComercial() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':numero_documento', $proveedor->obtenerNumeroDocumento(), $proveedor->obtenerNumeroDocumento() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':email', $proveedor->obtenerEmail(), $proveedor->obtenerEmail() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':telefono', $proveedor->obtenerTelefono(), $proveedor->obtenerTelefono() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':direccion', $proveedor->obtenerDireccion(), $proveedor->obtenerDireccion() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':estado', $proveedor->obtenerEstado(), PDO::PARAM_STR);
        $stmt->bindValue(':observaciones', $proveedor->obtenerObservaciones(), $proveedor->obtenerObservaciones() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);

        $stmt->execute();
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Actualiza un proveedor existente.
     */
    public function actualizar(Proveedor $proveedor): bool
    {
        if ($proveedor->obtenerId() === null) {
            return false;
        }

        $sql = 'UPDATE proveedores SET
                    codigo = :codigo,
                    tipo = :tipo,
                    persona_id = :persona_id,
                    razon_social = :razon_social,
                    nombre_comercial = :nombre_comercial,
                    numero_documento = :numero_documento,
                    email = :email,
                    telefono = :telefono,
                    direccion = :direccion,
                    estado = :estado,
                    observaciones = :observaciones
                WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $proveedor->obtenerId(), PDO::PARAM_INT);
        $stmt->bindValue(':codigo', $proveedor->obtenerCodigo(), PDO::PARAM_STR);
        $stmt->bindValue(':tipo', $proveedor->obtenerTipo(), PDO::PARAM_STR);
        $stmt->bindValue(':persona_id', $proveedor->obtenerPersonaId(), $proveedor->obtenerPersonaId() !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':razon_social', $proveedor->obtenerRazonSocial(), PDO::PARAM_STR);
        $stmt->bindValue(':nombre_comercial', $proveedor->obtenerNombreComercial(), $proveedor->obtenerNombreComercial() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':numero_documento', $proveedor->obtenerNumeroDocumento(), $proveedor->obtenerNumeroDocumento() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':email', $proveedor->obtenerEmail(), $proveedor->obtenerEmail() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':telefono', $proveedor->obtenerTelefono(), $proveedor->obtenerTelefono() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':direccion', $proveedor->obtenerDireccion(), $proveedor->obtenerDireccion() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':estado', $proveedor->obtenerEstado(), PDO::PARAM_STR);
        $stmt->bindValue(':observaciones', $proveedor->obtenerObservaciones(), $proveedor->obtenerObservaciones() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);

        return $stmt->execute();
    }

    /**
     * Cambia el estado (ACTIVO / INACTIVO) de un proveedor (cero eliminación física).
     */
    public function cambiarEstado(int $id, string $estado): bool
    {
        $sql = 'UPDATE proveedores SET estado = :estado WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':estado', strtoupper(trim($estado)), PDO::PARAM_STR);
        return $stmt->execute();
    }

    /**
     * Comprueba si ya existe un código asignado.
     */
    public function existeCodigo(string $codigo, ?int $excluirId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM proveedores WHERE codigo = :codigo';
        if ($excluirId !== null) {
            $sql .= ' AND id <> :excluir_id';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':codigo', trim(strtoupper($codigo)), PDO::PARAM_STR);
        if ($excluirId !== null) {
            $stmt->bindValue(':excluir_id', $excluirId, PDO::PARAM_INT);
        }
        $stmt->execute();

        return ((int) $stmt->fetchColumn()) > 0;
    }

    /**
     * Comprueba si ya existe un número de documento fiscal registrado.
     */
    public function existeDocumento(string $documento, ?int $excluirId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM proveedores WHERE numero_documento = :doc';
        if ($excluirId !== null) {
            $sql .= ' AND id <> :excluir_id';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':doc', trim($documento), PDO::PARAM_STR);
        if ($excluirId !== null) {
            $stmt->bindValue(':excluir_id', $excluirId, PDO::PARAM_INT);
        }
        $stmt->execute();

        return ((int) $stmt->fetchColumn()) > 0;
    }
}
