<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios\CPE;

use CamargoPMS\Modelos\CPE\CpeEstablecimiento;
use PDO;

/**
 * Repositorio para la configuración fiscal de establecimientos y anexos emisores ante SUNAT.
 */
class CpeEstablecimientoRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Obtiene la configuración de un establecimiento por su ID de clave primaria.
     */
    public function obtenerPorId(int $id): ?CpeEstablecimiento
    {
        $stmt = $this->pdo->prepare('SELECT * FROM cpe_establecimientos_configuracion WHERE id = :id');
        $stmt->execute(['id' => $id]);

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fila) {
            return null;
        }

        return CpeEstablecimiento::desdeArreglo($fila);
    }

    /**
     * Obtiene el establecimiento emisor asociado a una propiedad física hotelera.
     */
    public function obtenerPorPropiedadId(int $propiedadId): ?CpeEstablecimiento
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM cpe_establecimientos_configuracion WHERE propiedad_id = :propiedad_id'
        );
        $stmt->execute(['propiedad_id' => $propiedadId]);

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fila) {
            return null;
        }

        return CpeEstablecimiento::desdeArreglo($fila);
    }

    /**
     * Obtiene el establecimiento fiscal para una empresa y código de anexo SUNAT de 4 dígitos.
     */
    public function obtenerPorEmpresaYAnexo(int $empresaId, string $codigoAnexo): ?CpeEstablecimiento
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM cpe_establecimientos_configuracion
             WHERE empresa_id = :empresa_id AND codigo_establecimiento_sunat = :codigo_anexo'
        );
        $stmt->execute([
            'empresa_id' => $empresaId,
            'codigo_anexo' => $codigoAnexo,
        ]);

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fila) {
            return null;
        }

        return CpeEstablecimiento::desdeArreglo($fila);
    }

    /**
     * Lista todos los establecimientos configurados para una empresa.
     *
     * @return array<CpeEstablecimiento>
     */
    public function listarPorEmpresa(int $empresaId, ?string $estado = null): array
    {
        $sql = 'SELECT * FROM cpe_establecimientos_configuracion WHERE empresa_id = :empresa_id';
        $params = ['empresa_id' => $empresaId];

        if ($estado !== null) {
            $sql .= ' AND estado = :estado';
            $params['estado'] = $estado;
        }

        $sql .= ' ORDER BY codigo_establecimiento_sunat ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $establecimientos = [];
        foreach ($filas as $fila) {
            $establecimientos[] = CpeEstablecimiento::desdeArreglo($fila);
        }

        return $establecimientos;
    }

    /**
     * Registra un nuevo establecimiento emisor en la configuración fiscal.
     */
    public function crear(CpeEstablecimiento $estab): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO cpe_establecimientos_configuracion (
                empresa_id, propiedad_id, codigo_establecimiento_sunat, razon_social_snapshot,
                nombre_comercial, direccion_fiscal, ubigeo, departamento, provincia, distrito,
                modo_entorno, proveedor_transporte_default, estado, creado_en, actualizado_en
            ) VALUES (
                :empresa_id, :propiedad_id, :codigo_establecimiento_sunat, :razon_social_snapshot,
                :nombre_comercial, :direccion_fiscal, :ubigeo, :departamento, :provincia, :distrito,
                :modo_entorno, :proveedor_transporte_default, :estado, NOW(), NOW()
            )'
        );

        $stmt->execute([
            'empresa_id' => $estab->obtenerEmpresaId(),
            'propiedad_id' => $estab->obtenerPropiedadId(),
            'codigo_establecimiento_sunat' => $estab->obtenerCodigoEstablecimientoSunat(),
            'razon_social_snapshot' => $estab->obtenerRazonSocialSnapshot(),
            'nombre_comercial' => $estab->obtenerNombreComercial(),
            'direccion_fiscal' => $estab->obtenerDireccionFiscal(),
            'ubigeo' => $estab->obtenerUbigeo(),
            'departamento' => $estab->obtenerDepartamento(),
            'provincia' => $estab->obtenerProvincia(),
            'distrito' => $estab->obtenerDistrito(),
            'modo_entorno' => $estab->obtenerModoEntorno(),
            'proveedor_transporte_default' => $estab->obtenerProveedorTransporteDefault(),
            'estado' => $estab->obtenerEstado(),
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
