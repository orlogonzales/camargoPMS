<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\InventarioActivo;
use CamargoPMS\Modelos\InventarioArticulo;
use CamargoPMS\Modelos\InventarioDotacionEstandar;
use CamargoPMS\Modelos\InventarioExistencia;
use CamargoPMS\Modelos\InventarioMovimiento;
use CamargoPMS\Modelos\InventarioUbicacion;
use CamargoPMS\Modelos\InventarioUnidadMedida;
use PDO;

/**
 * Repositorio para la gestión y persistencia del dominio de inventario físico,
 * existencias por ubicación, Kardex append-only, activos y dotaciones.
 */
class InventarioRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    // =========================================================================
    // 1. UNIDADES DE MEDIDA
    // =========================================================================

    /**
     * @return InventarioUnidadMedida[]
     */
    public function obtenerUnidadesMedida(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM inventario_unidades_medida ORDER BY id ASC');
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resultado = [];
        foreach ($filas as $f) {
            $resultado[] = new InventarioUnidadMedida(
                (int) $f['id'],
                $f['codigo'],
                $f['nombre'],
                $f['simbolo'],
                (bool) $f['admite_decimales'],
                $f['creado_en']
            );
        }

        return $resultado;
    }

    public function obtenerUnidadMedidaPorId(int $id): ?InventarioUnidadMedida
    {
        $stmt = $this->pdo->prepare('SELECT * FROM inventario_unidades_medida WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $f = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$f) {
            return null;
        }

        return new InventarioUnidadMedida(
            (int) $f['id'],
            $f['codigo'],
            $f['nombre'],
            $f['simbolo'],
            (bool) $f['admite_decimales'],
            $f['creado_en']
        );
    }

    public function obtenerUnidadMedidaPorCodigo(string $codigo): ?InventarioUnidadMedida
    {
        $stmt = $this->pdo->prepare('SELECT * FROM inventario_unidades_medida WHERE codigo = :codigo LIMIT 1');
        $stmt->execute(['codigo' => strtoupper(trim($codigo))]);
        $f = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$f) {
            return null;
        }

        return new InventarioUnidadMedida(
            (int) $f['id'],
            $f['codigo'],
            $f['nombre'],
            $f['simbolo'],
            (bool) $f['admite_decimales'],
            $f['creado_en']
        );
    }

    // =========================================================================
    // 2. CATÁLOGO DE ARTÍCULOS
    // =========================================================================

    public function crearArticulo(InventarioArticulo $articulo): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO inventario_articulos (
                codigo_sku, nombre, descripcion, categoria, unidad_medida_id,
                costo_referencial, moneda_codigo, stock_minimo_alerta, estado, creado_en
            ) VALUES (
                :codigo_sku, :nombre, :descripcion, :categoria, :unidad_medida_id,
                :costo_referencial, :moneda_codigo, :stock_minimo_alerta, :estado, NOW()
            )'
        );

        $stmt->execute([
            'codigo_sku' => $articulo->obtenerCodigoSku(),
            'nombre' => $articulo->obtenerNombre(),
            'descripcion' => $articulo->obtenerDescripcion(),
            'categoria' => $articulo->obtenerCategoria(),
            'unidad_medida_id' => $articulo->obtenerUnidadMedidaId(),
            'costo_referencial' => $articulo->obtenerCostoReferencial(),
            'moneda_codigo' => $articulo->obtenerMonedaCodigo(),
            'stock_minimo_alerta' => $articulo->obtenerStockMinimoAlerta(),
            'estado' => $articulo->obtenerEstado(),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function actualizarArticulo(InventarioArticulo $articulo): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE inventario_articulos SET
                codigo_sku = :codigo_sku,
                nombre = :nombre,
                descripcion = :descripcion,
                categoria = :categoria,
                unidad_medida_id = :unidad_medida_id,
                costo_referencial = :costo_referencial,
                moneda_codigo = :moneda_codigo,
                stock_minimo_alerta = :stock_minimo_alerta,
                estado = :estado,
                actualizado_en = NOW()
            WHERE id = :id'
        );

        return $stmt->execute([
            'id' => $articulo->obtenerId(),
            'codigo_sku' => $articulo->obtenerCodigoSku(),
            'nombre' => $articulo->obtenerNombre(),
            'descripcion' => $articulo->obtenerDescripcion(),
            'categoria' => $articulo->obtenerCategoria(),
            'unidad_medida_id' => $articulo->obtenerUnidadMedidaId(),
            'costo_referencial' => $articulo->obtenerCostoReferencial(),
            'moneda_codigo' => $articulo->obtenerMonedaCodigo(),
            'stock_minimo_alerta' => $articulo->obtenerStockMinimoAlerta(),
            'estado' => $articulo->obtenerEstado(),
        ]);
    }

    public function obtenerArticuloPorId(int $id): ?InventarioArticulo
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.*, um.codigo AS um_codigo, um.nombre AS um_nombre, um.simbolo AS um_simbolo, um.admite_decimales AS um_admite_decimales
             FROM inventario_articulos a
             JOIN inventario_unidades_medida um ON um.id = a.unidad_medida_id
             WHERE a.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $f = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$f) {
            return null;
        }

        return $this->mapearArticulo($f);
    }

    public function obtenerArticuloPorSku(string $sku): ?InventarioArticulo
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.*, um.codigo AS um_codigo, um.nombre AS um_nombre, um.simbolo AS um_simbolo, um.admite_decimales AS um_admite_decimales
             FROM inventario_articulos a
             JOIN inventario_unidades_medida um ON um.id = a.unidad_medida_id
             WHERE a.codigo_sku = :sku
             LIMIT 1'
        );
        $stmt->execute(['sku' => trim($sku)]);
        $f = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$f) {
            return null;
        }

        return $this->mapearArticulo($f);
    }

    public function existeSku(string $sku, ?int $excluirId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM inventario_articulos WHERE codigo_sku = :sku';
        $params = ['sku' => trim($sku)];

        if ($excluirId !== null) {
            $sql .= ' AND id <> :excluir_id';
            $params['excluir_id'] = $excluirId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * @param array $filtros
     * @return InventarioArticulo[]
     */
    public function listarArticulos(array $filtros = []): array
    {
        $sql = 'SELECT a.*, um.codigo AS um_codigo, um.nombre AS um_nombre, um.simbolo AS um_simbolo, um.admite_decimales AS um_admite_decimales,
                COALESCE((SELECT SUM(ie.cantidad_actual) FROM inventario_existencias ie WHERE ie.articulo_id = a.id), 0.0000) AS stock_total
                FROM inventario_articulos a
                JOIN inventario_unidades_medida um ON um.id = a.unidad_medida_id
                WHERE 1=1';
        $params = [];

        if (!empty($filtros['categoria'])) {
            $sql .= ' AND a.categoria = :categoria';
            $params['categoria'] = $filtros['categoria'];
        }

        if (!empty($filtros['estado'])) {
            $sql .= ' AND a.estado = :estado';
            $params['estado'] = $filtros['estado'];
        }

        if (!empty($filtros['termino'])) {
            $sql .= ' AND (a.codigo_sku LIKE :termino OR a.nombre LIKE :termino)';
            $params['termino'] = '%' . trim((string) $filtros['termino']) . '%';
        }

        $sql .= ' ORDER BY a.nombre ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resultado = [];
        foreach ($filas as $f) {
            $art = $this->mapearArticulo($f);
            if (isset($f['stock_total'])) {
                $art->asignarStockTotalCalculado((string) $f['stock_total']);
            }
            $resultado[] = $art;
        }

        return $resultado;
    }

    private function mapearArticulo(array $f): InventarioArticulo
    {
        $art = new InventarioArticulo(
            (int) $f['id'],
            $f['codigo_sku'],
            $f['nombre'],
            $f['descripcion'],
            $f['categoria'],
            (int) $f['unidad_medida_id'],
            (string) $f['costo_referencial'],
            $f['moneda_codigo'],
            (string) $f['stock_minimo_alerta'],
            $f['estado'],
            $f['creado_en'],
            $f['actualizado_en']
        );

        if (isset($f['um_codigo'])) {
            $art->asignarUnidadMedidaInfo(
                $f['um_codigo'],
                $f['um_nombre'],
                $f['um_simbolo'],
                (bool) $f['um_admite_decimales']
            );
        }

        return $art;
    }

    // =========================================================================
    // 3. UBICACIONES Y ALMACENES
    // =========================================================================

    public function crearUbicacion(InventarioUbicacion $ubicacion): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO inventario_ubicaciones (
                propiedad_id, codigo, nombre, tipo, unidad_id, proveedor_id,
                responsable_colaborador_id, estado, creado_en
            ) VALUES (
                :propiedad_id, :codigo, :nombre, :tipo, :unidad_id, :proveedor_id,
                :responsable_colaborador_id, :estado, NOW()
            )'
        );

        $stmt->execute([
            'propiedad_id' => $ubicacion->obtenerPropiedadId(),
            'codigo' => $ubicacion->obtenerCodigo(),
            'nombre' => $ubicacion->obtenerNombre(),
            'tipo' => $ubicacion->obtenerTipo(),
            'unidad_id' => $ubicacion->obtenerUnidadId(),
            'proveedor_id' => $ubicacion->obtenerProveedorId(),
            'responsable_colaborador_id' => $ubicacion->obtenerResponsableColaboradorId(),
            'estado' => $ubicacion->obtenerEstado(),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function actualizarUbicacion(InventarioUbicacion $ubicacion): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE inventario_ubicaciones SET
                propiedad_id = :propiedad_id,
                codigo = :codigo,
                nombre = :nombre,
                tipo = :tipo,
                unidad_id = :unidad_id,
                proveedor_id = :proveedor_id,
                responsable_colaborador_id = :responsable_colaborador_id,
                estado = :estado,
                actualizado_en = NOW()
            WHERE id = :id'
        );

        return $stmt->execute([
            'id' => $ubicacion->obtenerId(),
            'propiedad_id' => $ubicacion->obtenerPropiedadId(),
            'codigo' => $ubicacion->obtenerCodigo(),
            'nombre' => $ubicacion->obtenerNombre(),
            'tipo' => $ubicacion->obtenerTipo(),
            'unidad_id' => $ubicacion->obtenerUnidadId(),
            'proveedor_id' => $ubicacion->obtenerProveedorId(),
            'responsable_colaborador_id' => $ubicacion->obtenerResponsableColaboradorId(),
            'estado' => $ubicacion->obtenerEstado(),
        ]);
    }

    public function obtenerUbicacionPorId(int $id): ?InventarioUbicacion
    {
        $stmt = $this->pdo->prepare(
            'SELECT ubi.*, p.nombre AS propiedad_nombre, u.nombre AS unidad_numero,
                    prov.razon_social AS proveedor_razon_social,
                    TRIM(CONCAT(per.nombres, " ", per.apellido_paterno, " ", COALESCE(per.apellido_materno, ""))) AS responsable_nombre
             FROM inventario_ubicaciones ubi
             JOIN propiedades p ON p.id = ubi.propiedad_id
             LEFT JOIN unidades u ON u.id = ubi.unidad_id
             LEFT JOIN proveedores prov ON prov.id = ubi.proveedor_id
             LEFT JOIN colaboradores col ON col.id = ubi.responsable_colaborador_id
             LEFT JOIN personas per ON per.id = col.persona_id
             WHERE ubi.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $f = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$f) {
            return null;
        }

        return $this->mapearUbicacion($f);
    }

    public function obtenerUbicacionPorCodigo(string $codigo): ?InventarioUbicacion
    {
        $stmt = $this->pdo->prepare(
            'SELECT ubi.*, p.nombre AS propiedad_nombre, u.nombre AS unidad_numero,
                    prov.razon_social AS proveedor_razon_social,
                    TRIM(CONCAT(per.nombres, " ", per.apellido_paterno, " ", COALESCE(per.apellido_materno, ""))) AS responsable_nombre
             FROM inventario_ubicaciones ubi
             JOIN propiedades p ON p.id = ubi.propiedad_id
             LEFT JOIN unidades u ON u.id = ubi.unidad_id
             LEFT JOIN proveedores prov ON prov.id = ubi.proveedor_id
             LEFT JOIN colaboradores col ON col.id = ubi.responsable_colaborador_id
             LEFT JOIN personas per ON per.id = col.persona_id
             WHERE ubi.codigo = :codigo
             LIMIT 1'
        );
        $stmt->execute(['codigo' => trim($codigo)]);
        $f = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$f) {
            return null;
        }

        return $this->mapearUbicacion($f);
    }

    public function obtenerUbicacionPorUnidadId(int $unidadId): ?InventarioUbicacion
    {
        $stmt = $this->pdo->prepare(
            'SELECT ubi.*, p.nombre AS propiedad_nombre, u.nombre AS unidad_numero,
                    prov.razon_social AS proveedor_razon_social,
                    TRIM(CONCAT(per.nombres, " ", per.apellido_paterno, " ", COALESCE(per.apellido_materno, ""))) AS responsable_nombre
             FROM inventario_ubicaciones ubi
             JOIN propiedades p ON p.id = ubi.propiedad_id
             JOIN unidades u ON u.id = ubi.unidad_id
             LEFT JOIN proveedores prov ON prov.id = ubi.proveedor_id
             LEFT JOIN colaboradores col ON col.id = ubi.responsable_colaborador_id
             LEFT JOIN personas per ON per.id = col.persona_id
             WHERE ubi.unidad_id = :unidad_id
             LIMIT 1'
        );
        $stmt->execute(['unidad_id' => $unidadId]);
        $f = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$f) {
            return null;
        }

        return $this->mapearUbicacion($f);
    }

    public function existeCodigoUbicacion(string $codigo, ?int $excluirId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM inventario_ubicaciones WHERE codigo = :codigo';
        $params = ['codigo' => trim($codigo)];

        if ($excluirId !== null) {
            $sql .= ' AND id <> :excluir_id';
            $params['excluir_id'] = $excluirId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * @param array $filtros
     * @return InventarioUbicacion[]
     */
    public function listarUbicaciones(array $filtros = []): array
    {
        $sql = 'SELECT ubi.*, p.nombre AS propiedad_nombre, u.nombre AS unidad_numero,
                prov.razon_social AS proveedor_razon_social,
                TRIM(CONCAT(per.nombres, " ", per.apellido_paterno, " ", COALESCE(per.apellido_materno, ""))) AS responsable_nombre
                FROM inventario_ubicaciones ubi
                JOIN propiedades p ON p.id = ubi.propiedad_id
                LEFT JOIN unidades u ON u.id = ubi.unidad_id
                LEFT JOIN proveedores prov ON prov.id = ubi.proveedor_id
                LEFT JOIN colaboradores col ON col.id = ubi.responsable_colaborador_id
                LEFT JOIN personas per ON per.id = col.persona_id
                WHERE 1=1';
        $params = [];

        if (!empty($filtros['propiedad_id'])) {
            $sql .= ' AND ubi.propiedad_id = :propiedad_id';
            $params['propiedad_id'] = (int) $filtros['propiedad_id'];
        }

        if (!empty($filtros['tipo'])) {
            $sql .= ' AND ubi.tipo = :tipo';
            $params['tipo'] = $filtros['tipo'];
        }

        if (!empty($filtros['estado'])) {
            $sql .= ' AND ubi.estado = :estado';
            $params['estado'] = $filtros['estado'];
        }

        $sql .= ' ORDER BY ubi.nombre ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resultado = [];
        foreach ($filas as $f) {
            $resultado[] = $this->mapearUbicacion($f);
        }

        return $resultado;
    }

    private function mapearUbicacion(array $f): InventarioUbicacion
    {
        $ubi = new InventarioUbicacion(
            (int) $f['id'],
            (int) $f['propiedad_id'],
            $f['codigo'],
            $f['nombre'],
            $f['tipo'],
            $f['unidad_id'] !== null ? (int) $f['unidad_id'] : null,
            $f['proveedor_id'] !== null ? (int) $f['proveedor_id'] : null,
            $f['responsable_colaborador_id'] !== null ? (int) $f['responsable_colaborador_id'] : null,
            $f['estado'],
            $f['creado_en'],
            $f['actualizado_en']
        );

        $ubi->asignarPropiedadNombre($f['propiedad_nombre'] ?? null);
        $ubi->asignarUnidadNumero($f['unidad_numero'] ?? null);
        $ubi->asignarProveedorRazonSocial($f['proveedor_razon_social'] ?? null);
        $ubi->asignarResponsableNombre($f['responsable_nombre'] ?? null);

        return $ubi;
    }

    // =========================================================================
    // 4. EXISTENCIAS (PROYECCIÓN OPERACIONAL MATERIALIZADA)
    // =========================================================================

    public function obtenerExistencia(int $articuloId, int $ubicacionId): ?InventarioExistencia
    {
        $stmt = $this->pdo->prepare(
            'SELECT ie.*, a.codigo_sku AS art_sku, a.nombre AS art_nombre, a.categoria AS art_categoria,
                    um.simbolo AS um_simbolo, um.admite_decimales AS um_admite_decimales,
                    ubi.codigo AS ubi_codigo, ubi.nombre AS ubi_nombre, ubi.tipo AS ubi_tipo,
                    p.nombre AS propiedad_nombre
             FROM inventario_existencias ie
             JOIN inventario_articulos a ON a.id = ie.articulo_id
             JOIN inventario_unidades_medida um ON um.id = a.unidad_medida_id
             JOIN inventario_ubicaciones ubi ON ubi.id = ie.ubicacion_id
             JOIN propiedades p ON p.id = ubi.propiedad_id
             WHERE ie.articulo_id = :articulo_id AND ie.ubicacion_id = :ubicacion_id
             LIMIT 1'
        );
        $stmt->execute([
            'articulo_id' => $articuloId,
            'ubicacion_id' => $ubicacionId,
        ]);
        $f = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$f) {
            return null;
        }

        return $this->mapearExistencia($f);
    }

    public function obtenerOcrearExistencia(int $articuloId, int $ubicacionId): InventarioExistencia
    {
        $existencia = $this->obtenerExistencia($articuloId, $ubicacionId);
        if ($existencia !== null) {
            return $existencia;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO inventario_existencias (articulo_id, ubicacion_id, cantidad_actual, cantidad_reservada, actualizado_en)
             VALUES (:articulo_id, :ubicacion_id, 0.0000, 0.0000, NOW())
             ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
        );
        $stmt->execute([
            'articulo_id' => $articuloId,
            'ubicacion_id' => $ubicacionId,
        ]);

        return $this->obtenerExistencia($articuloId, $ubicacionId);
    }

    /**
     * Bloquea pesimistamente las existencias ordenadas determinísticamente por ID
     * para prevenir deadlocks en transacciones concurrentes (D-078 #15).
     *
     * @param array $paresArticuloUbicacion Array de ['articulo_id' => int, 'ubicacion_id' => int]
     * @return InventarioExistencia[] Indexado por 'articuloId_ubicacionId'
     */
    public function obtenerExistenciasBloqueadas(array $paresArticuloUbicacion): array
    {
        if (empty($paresArticuloUbicacion)) {
            return [];
        }

        // Primero asegurar que todas existan para poder obtener sus IDs físicos
        $ids = [];
        $claveExistenciaMap = [];

        foreach ($paresArticuloUbicacion as $par) {
            $artId = (int) $par['articulo_id'];
            $ubiId = (int) $par['ubicacion_id'];
            $instancia = $this->obtenerOcrearExistencia($artId, $ubiId);
            $idFisico = (int) $instancia->obtenerId();
            $ids[] = $idFisico;
            $claveExistenciaMap[$idFisico] = "{$artId}_{$ubiId}";
        }

        $ids = array_unique($ids);
        sort($ids, SORT_NUMERIC); // Orden ascendente estricto por ID físico

        // Ejecutar SELECT ... FOR UPDATE ordenado por ID
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT ie.*, a.codigo_sku AS art_sku, a.nombre AS art_nombre, a.categoria AS art_categoria,
                    um.simbolo AS um_simbolo, um.admite_decimales AS um_admite_decimales,
                    ubi.codigo AS ubi_codigo, ubi.nombre AS ubi_nombre, ubi.tipo AS ubi_tipo,
                    p.nombre AS propiedad_nombre
             FROM inventario_existencias ie
             JOIN inventario_articulos a ON a.id = ie.articulo_id
             JOIN inventario_unidades_medida um ON um.id = a.unidad_medida_id
             JOIN inventario_ubicaciones ubi ON ubi.id = ie.ubicacion_id
             JOIN propiedades p ON p.id = ubi.propiedad_id
             WHERE ie.id IN ({$placeholders})
             ORDER BY ie.id ASC
             FOR UPDATE"
        );
        $stmt->execute($ids);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resultado = [];
        foreach ($filas as $f) {
            $ex = $this->mapearExistencia($f);
            $clave = "{$ex->obtenerArticuloId()}_{$ex->obtenerUbicacionId()}";
            $resultado[$clave] = $ex;
        }

        return $resultado;
    }

    public function actualizarCantidadActual(int $existenciaId, string $nuevaCantidad): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE inventario_existencias SET cantidad_actual = :cantidad, actualizado_en = NOW() WHERE id = :id'
        );

        return $stmt->execute([
            'id' => $existenciaId,
            'cantidad' => $nuevaCantidad,
        ]);
    }

    /**
     * @param array $filtros
     * @return InventarioExistencia[]
     */
    public function listarExistencias(array $filtros = []): array
    {
        $sql = 'SELECT ie.*, a.codigo_sku AS art_sku, a.nombre AS art_nombre, a.categoria AS art_categoria,
                um.simbolo AS um_simbolo, um.admite_decimales AS um_admite_decimales,
                ubi.codigo AS ubi_codigo, ubi.nombre AS ubi_nombre, ubi.tipo AS ubi_tipo,
                p.nombre AS propiedad_nombre
                FROM inventario_existencias ie
                JOIN inventario_articulos a ON a.id = ie.articulo_id
                JOIN inventario_unidades_medida um ON um.id = a.unidad_medida_id
                JOIN inventario_ubicaciones ubi ON ubi.id = ie.ubicacion_id
                JOIN propiedades p ON p.id = ubi.propiedad_id
                WHERE 1=1';
        $params = [];

        if (!empty($filtros['articulo_id'])) {
            $sql .= ' AND ie.articulo_id = :articulo_id';
            $params['articulo_id'] = (int) $filtros['articulo_id'];
        }

        if (!empty($filtros['ubicacion_id'])) {
            $sql .= ' AND ie.ubicacion_id = :ubicacion_id';
            $params['ubicacion_id'] = (int) $filtros['ubicacion_id'];
        }

        if (!empty($filtros['propiedad_id'])) {
            $sql .= ' AND ubi.propiedad_id = :propiedad_id';
            $params['propiedad_id'] = (int) $filtros['propiedad_id'];
        }

        if (!empty($filtros['categoria'])) {
            $sql .= ' AND a.categoria = :categoria';
            $params['categoria'] = $filtros['categoria'];
        }

        if (!empty($filtros['solo_con_stock'])) {
            $sql .= ' AND ie.cantidad_actual > 0';
        }

        $sql .= ' ORDER BY a.nombre ASC, ubi.nombre ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resultado = [];
        foreach ($filas as $f) {
            $resultado[] = $this->mapearExistencia($f);
        }

        return $resultado;
    }

    public function obtenerStockTotalArticulo(int $articuloId): string
    {
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(cantidad_actual), 0.0000) FROM inventario_existencias WHERE articulo_id = :articulo_id'
        );
        $stmt->execute(['articulo_id' => $articuloId]);

        return (string) $stmt->fetchColumn();
    }

    private function mapearExistencia(array $f): InventarioExistencia
    {
        $ex = new InventarioExistencia(
            (int) $f['id'],
            (int) $f['articulo_id'],
            (int) $f['ubicacion_id'],
            (string) $f['cantidad_actual'],
            (string) $f['cantidad_reservada'],
            $f['actualizado_en']
        );

        $ex->asignarArticuloInfo(
            $f['art_sku'] ?? '',
            $f['art_nombre'] ?? '',
            $f['art_categoria'] ?? '',
            $f['um_simbolo'] ?? '',
            (bool) ($f['um_admite_decimales'] ?? false)
        );

        $ex->asignarUbicacionInfo(
            $f['ubi_codigo'] ?? '',
            $f['ubi_nombre'] ?? '',
            $f['ubi_tipo'] ?? '',
            $f['propiedad_nombre'] ?? null
        );

        return $ex;
    }

    // =========================================================================
    // 5. MOVIMIENTOS (KARDEX INMUTABLE, APPEND-ONLY)
    // =========================================================================

    public function crearMovimiento(InventarioMovimiento $movimiento): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO inventario_movimientos (
                codigo, tipo_movimiento, articulo_id, ubicacion_id, cantidad,
                costo_unitario_historico, costo_total_historico, moneda_codigo,
                referencia_tipo, referencia_id, movimiento_referencia_id, correlativo_operacion,
                motivo, creado_por_actor_id, creado_en
            ) VALUES (
                :codigo, :tipo_movimiento, :articulo_id, :ubicacion_id, :cantidad,
                :costo_unitario_historico, :costo_total_historico, :moneda_codigo,
                :referencia_tipo, :referencia_id, :movimiento_referencia_id, :correlativo_operacion,
                :motivo, :creado_por_actor_id, NOW()
            )'
        );

        $stmt->execute([
            'codigo' => $movimiento->obtenerCodigo(),
            'tipo_movimiento' => $movimiento->obtenerTipoMovimiento(),
            'articulo_id' => $movimiento->obtenerArticuloId(),
            'ubicacion_id' => $movimiento->obtenerUbicacionId(),
            'cantidad' => $movimiento->obtenerCantidad(),
            'costo_unitario_historico' => $movimiento->obtenerCostoUnitarioHistorico(),
            'costo_total_historico' => $movimiento->obtenerCostoTotalHistorico(),
            'moneda_codigo' => $movimiento->obtenerMonedaCodigo(),
            'referencia_tipo' => $movimiento->obtenerReferenciaTipo(),
            'referencia_id' => $movimiento->obtenerReferenciaId(),
            'movimiento_referencia_id' => $movimiento->obtenerMovimientoReferenciaId(),
            'correlativo_operacion' => $movimiento->obtenerCorrelativoOperacion(),
            'motivo' => $movimiento->obtenerMotivo(),
            'creado_por_actor_id' => $movimiento->obtenerCreadoPorActorId(),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function obtenerMovimientoPorId(int $id): ?InventarioMovimiento
    {
        $stmt = $this->pdo->prepare(
            'SELECT m.*, a.codigo_sku AS art_sku, a.nombre AS art_nombre, um.simbolo AS um_simbolo,
                    ubi.codigo AS ubi_codigo, ubi.nombre AS ubi_nombre,
                    act.codigo AS actor_codigo, act.nombre AS actor_nombre
             FROM inventario_movimientos m
             JOIN inventario_articulos a ON a.id = m.articulo_id
             JOIN inventario_unidades_medida um ON um.id = a.unidad_medida_id
             JOIN inventario_ubicaciones ubi ON ubi.id = m.ubicacion_id
             JOIN actores act ON act.id = m.creado_por_actor_id
             WHERE m.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $f = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$f) {
            return null;
        }

        return $this->mapearMovimiento($f);
    }

    public function obtenerMovimientoPorCodigo(string $codigo): ?InventarioMovimiento
    {
        $stmt = $this->pdo->prepare(
            'SELECT m.*, a.codigo_sku AS art_sku, a.nombre AS art_nombre, um.simbolo AS um_simbolo,
                    ubi.codigo AS ubi_codigo, ubi.nombre AS ubi_nombre,
                    act.codigo AS actor_codigo, act.nombre AS actor_nombre
             FROM inventario_movimientos m
             JOIN inventario_articulos a ON a.id = m.articulo_id
             JOIN inventario_unidades_medida um ON um.id = a.unidad_medida_id
             JOIN inventario_ubicaciones ubi ON ubi.id = m.ubicacion_id
             JOIN actores act ON act.id = m.creado_por_actor_id
             WHERE m.codigo = :codigo
             LIMIT 1'
        );
        $stmt->execute(['codigo' => trim($codigo)]);
        $f = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$f) {
            return null;
        }

        return $this->mapearMovimiento($f);
    }

    /**
     * Verifica si un movimiento ya ha sido neutralizado por un movimiento previo de tipo REVERSO.
     */
    public function existeReversoParaMovimiento(int $movimientoId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM inventario_movimientos 
             WHERE movimiento_referencia_id = :mov_id 
               AND tipo_movimiento = 'REVERSO'"
        );
        $stmt->execute(['mov_id' => $movimientoId]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    /**
     * @param array $filtros
     * @return InventarioMovimiento[]
     */
    public function listarMovimientos(array $filtros = []): array
    {
        $sql = 'SELECT m.*, a.codigo_sku AS art_sku, a.nombre AS art_nombre, um.simbolo AS um_simbolo,
                ubi.codigo AS ubi_codigo, ubi.nombre AS ubi_nombre,
                act.codigo AS actor_codigo, act.nombre AS actor_nombre
                FROM inventario_movimientos m
                JOIN inventario_articulos a ON a.id = m.articulo_id
                JOIN inventario_unidades_medida um ON um.id = a.unidad_medida_id
                JOIN inventario_ubicaciones ubi ON ubi.id = m.ubicacion_id
                JOIN actores act ON act.id = m.creado_por_actor_id
                WHERE 1=1';
        $params = [];

        if (!empty($filtros['articulo_id'])) {
            $sql .= ' AND m.articulo_id = :articulo_id';
            $params['articulo_id'] = (int) $filtros['articulo_id'];
        }

        if (!empty($filtros['ubicacion_id'])) {
            $sql .= ' AND m.ubicacion_id = :ubicacion_id';
            $params['ubicacion_id'] = (int) $filtros['ubicacion_id'];
        }

        if (!empty($filtros['tipo_movimiento'])) {
            $sql .= ' AND m.tipo_movimiento = :tipo_movimiento';
            $params['tipo_movimiento'] = $filtros['tipo_movimiento'];
        }

        if (!empty($filtros['correlativo_operacion'])) {
            $sql .= ' AND m.correlativo_operacion = :correlativo_operacion';
            $params['correlativo_operacion'] = $filtros['correlativo_operacion'];
        }

        if (!empty($filtros['referencia_tipo']) && !empty($filtros['referencia_id'])) {
            $sql .= ' AND m.referencia_tipo = :ref_tipo AND m.referencia_id = :ref_id';
            $params['ref_tipo'] = $filtros['referencia_tipo'];
            $params['ref_id'] = (int) $filtros['referencia_id'];
        }

        $sql .= ' ORDER BY m.creado_en DESC, m.id DESC';

        if (!empty($filtros['limite'])) {
            $sql .= ' LIMIT ' . (int) $filtros['limite'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resultado = [];
        foreach ($filas as $f) {
            $resultado[] = $this->mapearMovimiento($f);
        }

        return $resultado;
    }

    /**
     * Suma el costo total histórico de movimientos válidos asociados a una referencia (D-078 #12).
     */
    public function sumarCostoMovimientosPorReferencia(string $referenciaTipo, int $referenciaId): string
    {
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(costo_total_historico), 0.00)
             FROM inventario_movimientos
             WHERE referencia_tipo = :ref_tipo AND referencia_id = :ref_id'
        );
        $stmt->execute([
            'ref_tipo' => $referenciaTipo,
            'ref_id' => $referenciaId,
        ]);

        return (string) $stmt->fetchColumn();
    }

    private function mapearMovimiento(array $f): InventarioMovimiento
    {
        $mov = new InventarioMovimiento(
            (int) $f['id'],
            $f['codigo'],
            $f['tipo_movimiento'],
            (int) $f['articulo_id'],
            (int) $f['ubicacion_id'],
            (string) $f['cantidad'],
            (string) $f['costo_unitario_historico'],
            (string) $f['costo_total_historico'],
            $f['moneda_codigo'],
            $f['referencia_tipo'],
            $f['referencia_id'] !== null ? (int) $f['referencia_id'] : null,
            $f['movimiento_referencia_id'] !== null ? (int) $f['movimiento_referencia_id'] : null,
            $f['correlativo_operacion'],
            $f['motivo'],
            (int) $f['creado_por_actor_id'],
            $f['creado_en']
        );

        $mov->asignarArticuloInfo(
            $f['art_sku'] ?? '',
            $f['art_nombre'] ?? '',
            $f['um_simbolo'] ?? ''
        );

        $mov->asignarUbicacionInfo(
            $f['ubi_codigo'] ?? '',
            $f['ubi_nombre'] ?? ''
        );

        $mov->asignarActorInfo(
            $f['actor_codigo'] ?? '',
            $f['actor_nombre'] ?? ''
        );

        return $mov;
    }

    // =========================================================================
    // 6. ACTIVOS SERIALIZABLES
    // =========================================================================

    public function crearActivo(InventarioActivo $activo): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO inventario_activos (
                articulo_id, codigo_placa, numero_serie_fabricante, marca, modelo,
                propiedad_id, ubicacion_id, estado, fecha_adquisicion, costo_adquisicion,
                moneda_codigo, garantia_vence_en, notas, motivo_baja, baja_por_actor_id, creado_en
            ) VALUES (
                :articulo_id, :codigo_placa, :numero_serie_fabricante, :marca, :modelo,
                :propiedad_id, :ubicacion_id, :estado, :fecha_adquisicion, :costo_adquisicion,
                :moneda_codigo, :garantia_vence_en, :notas, :motivo_baja, :baja_por_actor_id, NOW()
            )'
        );

        $stmt->execute([
            'articulo_id' => $activo->obtenerArticuloId(),
            'codigo_placa' => $activo->obtenerCodigoPlaca(),
            'numero_serie_fabricante' => $activo->obtenerNumeroSerieFabricante(),
            'marca' => $activo->obtenerMarca(),
            'modelo' => $activo->obtenerModelo(),
            'propiedad_id' => $activo->obtenerPropiedadId(),
            'ubicacion_id' => $activo->obtenerUbicacionId(),
            'estado' => $activo->obtenerEstado(),
            'fecha_adquisicion' => $activo->obtenerFechaAdquisicion(),
            'costo_adquisicion' => $activo->obtenerCostoAdquisicion(),
            'moneda_codigo' => $activo->obtenerMonedaCodigo(),
            'garantia_vence_en' => $activo->obtenerGarantiaVenceEn(),
            'notas' => $activo->obtenerNotas(),
            'motivo_baja' => $activo->obtenerMotivoBaja(),
            'baja_por_actor_id' => $activo->obtenerBajaPorActorId(),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function actualizarActivo(InventarioActivo $activo): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE inventario_activos SET
                articulo_id = :articulo_id,
                codigo_placa = :codigo_placa,
                numero_serie_fabricante = :numero_serie_fabricante,
                marca = :marca,
                modelo = :modelo,
                propiedad_id = :propiedad_id,
                ubicacion_id = :ubicacion_id,
                estado = :estado,
                fecha_adquisicion = :fecha_adquisicion,
                costo_adquisicion = :costo_adquisicion,
                moneda_codigo = :moneda_codigo,
                garantia_vence_en = :garantia_vence_en,
                notas = :notas,
                motivo_baja = :motivo_baja,
                baja_por_actor_id = :baja_por_actor_id,
                actualizado_en = NOW()
            WHERE id = :id'
        );

        return $stmt->execute([
            'id' => $activo->obtenerId(),
            'articulo_id' => $activo->obtenerArticuloId(),
            'codigo_placa' => $activo->obtenerCodigoPlaca(),
            'numero_serie_fabricante' => $activo->obtenerNumeroSerieFabricante(),
            'marca' => $activo->obtenerMarca(),
            'modelo' => $activo->obtenerModelo(),
            'propiedad_id' => $activo->obtenerPropiedadId(),
            'ubicacion_id' => $activo->obtenerUbicacionId(),
            'estado' => $activo->obtenerEstado(),
            'fecha_adquisicion' => $activo->obtenerFechaAdquisicion(),
            'costo_adquisicion' => $activo->obtenerCostoAdquisicion(),
            'moneda_codigo' => $activo->obtenerMonedaCodigo(),
            'garantia_vence_en' => $activo->obtenerGarantiaVenceEn(),
            'notas' => $activo->obtenerNotas(),
            'motivo_baja' => $activo->obtenerMotivoBaja(),
            'baja_por_actor_id' => $activo->obtenerBajaPorActorId(),
        ]);
    }

    public function obtenerActivoPorId(int $id): ?InventarioActivo
    {
        $stmt = $this->pdo->prepare(
            'SELECT act.*, a.codigo_sku AS art_sku, a.nombre AS art_nombre,
                    p.nombre AS propiedad_nombre,
                    ubi.codigo AS ubi_codigo, ubi.nombre AS ubi_nombre, ubi.tipo AS ubi_tipo,
                    u.nombre AS unidad_numero
             FROM inventario_activos act
             JOIN inventario_articulos a ON a.id = act.articulo_id
             JOIN propiedades p ON p.id = act.propiedad_id
             JOIN inventario_ubicaciones ubi ON ubi.id = act.ubicacion_id
             LEFT JOIN unidades u ON u.id = ubi.unidad_id
             WHERE act.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $f = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$f) {
            return null;
        }

        return $this->mapearActivo($f);
    }

    public function obtenerActivoPorPlaca(string $placa): ?InventarioActivo
    {
        $stmt = $this->pdo->prepare(
            'SELECT act.*, a.codigo_sku AS art_sku, a.nombre AS art_nombre,
                    p.nombre AS propiedad_nombre,
                    ubi.codigo AS ubi_codigo, ubi.nombre AS ubi_nombre, ubi.tipo AS ubi_tipo,
                    u.nombre AS unidad_numero
             FROM inventario_activos act
             JOIN inventario_articulos a ON a.id = act.articulo_id
             JOIN propiedades p ON p.id = act.propiedad_id
             JOIN inventario_ubicaciones ubi ON ubi.id = act.ubicacion_id
             LEFT JOIN unidades u ON u.id = ubi.unidad_id
             WHERE act.codigo_placa = :placa
             LIMIT 1'
        );
        $stmt->execute(['placa' => trim($placa)]);
        $f = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$f) {
            return null;
        }

        return $this->mapearActivo($f);
    }

    public function existePlacaActivo(string $placa, ?int $excluirId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM inventario_activos WHERE codigo_placa = :placa';
        $params = ['placa' => trim($placa)];

        if ($excluirId !== null) {
            $sql .= ' AND id <> :excluir_id';
            $params['excluir_id'] = $excluirId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * @param array $filtros
     * @return InventarioActivo[]
     */
    public function listarActivos(array $filtros = []): array
    {
        $sql = 'SELECT act.*, a.codigo_sku AS art_sku, a.nombre AS art_nombre,
                p.nombre AS propiedad_nombre,
                ubi.codigo AS ubi_codigo, ubi.nombre AS ubi_nombre, ubi.tipo AS ubi_tipo,
                u.nombre AS unidad_numero
                FROM inventario_activos act
                JOIN inventario_articulos a ON a.id = act.articulo_id
                JOIN propiedades p ON p.id = act.propiedad_id
                JOIN inventario_ubicaciones ubi ON ubi.id = act.ubicacion_id
                LEFT JOIN unidades u ON u.id = ubi.unidad_id
                WHERE 1=1';
        $params = [];

        if (!empty($filtros['articulo_id'])) {
            $sql .= ' AND act.articulo_id = :articulo_id';
            $params['articulo_id'] = (int) $filtros['articulo_id'];
        }

        if (!empty($filtros['propiedad_id'])) {
            $sql .= ' AND act.propiedad_id = :propiedad_id';
            $params['propiedad_id'] = (int) $filtros['propiedad_id'];
        }

        if (!empty($filtros['ubicacion_id'])) {
            $sql .= ' AND act.ubicacion_id = :ubicacion_id';
            $params['ubicacion_id'] = (int) $filtros['ubicacion_id'];
        }

        if (!empty($filtros['estado'])) {
            $sql .= ' AND act.estado = :estado';
            $params['estado'] = $filtros['estado'];
        }

        $sql .= ' ORDER BY act.codigo_placa ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resultado = [];
        foreach ($filas as $f) {
            $resultado[] = $this->mapearActivo($f);
        }

        return $resultado;
    }

    private function mapearActivo(array $f): InventarioActivo
    {
        $activo = new InventarioActivo(
            (int) $f['id'],
            (int) $f['articulo_id'],
            $f['codigo_placa'],
            $f['numero_serie_fabricante'],
            $f['marca'],
            $f['modelo'],
            (int) $f['propiedad_id'],
            (int) $f['ubicacion_id'],
            $f['estado'],
            $f['fecha_adquisicion'],
            (string) $f['costo_adquisicion'],
            $f['moneda_codigo'],
            $f['garantia_vence_en'],
            $f['notas'],
            $f['motivo_baja'],
            $f['baja_por_actor_id'] !== null ? (int) $f['baja_por_actor_id'] : null,
            $f['creado_en'],
            $f['actualizado_en']
        );

        $activo->asignarArticuloInfo($f['art_sku'] ?? '', $f['art_nombre'] ?? '');
        $activo->asignarPropiedadNombre($f['propiedad_nombre'] ?? null);
        $activo->asignarUbicacionInfo(
            $f['ubi_codigo'] ?? '',
            $f['ubi_nombre'] ?? '',
            $f['ubi_tipo'] ?? '',
            $f['unidad_numero'] ?? null
        );

        return $activo;
    }

    // =========================================================================
    // 7. DOTACIONES ESTÁNDAR
    // =========================================================================

    public function guardarDotacionEstandar(InventarioDotacionEstandar $dotacion): int
    {
        if ($dotacion->obtenerId() !== null) {
            $stmt = $this->pdo->prepare(
                'UPDATE inventario_dotaciones_estandar SET
                    tipo_unidad_id = :tipo_unidad_id,
                    unidad_id = :unidad_id,
                    articulo_id = :articulo_id,
                    cantidad_estandar = :cantidad_estandar,
                    notas = :notas,
                    actualizado_en = NOW()
                WHERE id = :id'
            );
            $stmt->execute([
                'id' => $dotacion->obtenerId(),
                'tipo_unidad_id' => $dotacion->obtenerTipoUnidadId(),
                'unidad_id' => $dotacion->obtenerUnidadId(),
                'articulo_id' => $dotacion->obtenerArticuloId(),
                'cantidad_estandar' => $dotacion->obtenerCantidadEstandar(),
                'notas' => $dotacion->obtenerNotas(),
            ]);

            return $dotacion->obtenerId();
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO inventario_dotaciones_estandar (
                tipo_unidad_id, unidad_id, articulo_id, cantidad_estandar, notas, creado_en
            ) VALUES (
                :tipo_unidad_id, :unidad_id, :articulo_id, :cantidad_estandar, :notas, NOW()
            )'
        );

        $stmt->execute([
            'tipo_unidad_id' => $dotacion->obtenerTipoUnidadId(),
            'unidad_id' => $dotacion->obtenerUnidadId(),
            'articulo_id' => $dotacion->obtenerArticuloId(),
            'cantidad_estandar' => $dotacion->obtenerCantidadEstandar(),
            'notas' => $dotacion->obtenerNotas(),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function eliminarDotacionEstandar(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM inventario_dotaciones_estandar WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public function obtenerDotacionEstandarPorId(int $id): ?InventarioDotacionEstandar
    {
        $stmt = $this->pdo->prepare(
            'SELECT ide.*, a.codigo_sku AS art_sku, a.nombre AS art_nombre, a.categoria AS art_categoria,
                    um.simbolo AS um_simbolo, tu.nombre AS tipo_unidad_nombre, u.nombre AS unidad_numero
             FROM inventario_dotaciones_estandar ide
             JOIN inventario_articulos a ON a.id = ide.articulo_id
             JOIN inventario_unidades_medida um ON um.id = a.unidad_medida_id
             LEFT JOIN tipos_unidad tu ON tu.id = ide.tipo_unidad_id
             LEFT JOIN unidades u ON u.id = ide.unidad_id
             WHERE ide.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $f = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$f) {
            return null;
        }

        return $this->mapearDotacion($f);
    }

    /**
     * @param array $filtros
     * @return InventarioDotacionEstandar[]
     */
    public function listarDotacionesEstandar(array $filtros = []): array
    {
        $sql = 'SELECT ide.*, a.codigo_sku AS art_sku, a.nombre AS art_nombre, a.categoria AS art_categoria,
                um.simbolo AS um_simbolo, tu.nombre AS tipo_unidad_nombre, u.nombre AS unidad_numero
                FROM inventario_dotaciones_estandar ide
                JOIN inventario_articulos a ON a.id = ide.articulo_id
                JOIN inventario_unidades_medida um ON um.id = a.unidad_medida_id
                LEFT JOIN tipos_unidad tu ON tu.id = ide.tipo_unidad_id
                LEFT JOIN unidades u ON u.id = ide.unidad_id
                WHERE 1=1';
        $params = [];

        if (!empty($filtros['tipo_unidad_id'])) {
            $sql .= ' AND ide.tipo_unidad_id = :tipo_unidad_id';
            $params['tipo_unidad_id'] = (int) $filtros['tipo_unidad_id'];
        }

        if (!empty($filtros['unidad_id'])) {
            $sql .= ' AND ide.unidad_id = :unidad_id';
            $params['unidad_id'] = (int) $filtros['unidad_id'];
        }

        if (!empty($filtros['articulo_id'])) {
            $sql .= ' AND ide.articulo_id = :articulo_id';
            $params['articulo_id'] = (int) $filtros['articulo_id'];
        }

        $sql .= ' ORDER BY a.nombre ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resultado = [];
        foreach ($filas as $f) {
            $resultado[] = $this->mapearDotacion($f);
        }

        return $resultado;
    }

    /**
     * Compara la dotación estándar esperada vs la realidad física de una unidad habitacional (D-078 #10).
     *
     * @param int $unidadId ID de la unidad
     * @return array Matriz con estándar, realidad, diferencia y estado por artículo
     */
    public function obtenerDotacionRealVsEstandarPorUnidad(int $unidadId): array
    {
        // 1. Obtener la unidad y su tipo_unidad_id
        $stmtU = $this->pdo->prepare('SELECT id, propiedad_id, tipo_unidad_id, nombre FROM unidades WHERE id = :id');
        $stmtU->execute(['id' => $unidadId]);
        $u = $stmtU->fetch(PDO::FETCH_ASSOC);
        if (!$u) {
            return [];
        }

        $tipoUnidadId = (int) $u['tipo_unidad_id'];

        // 2. Obtener la ubicación física de tipo UNIDAD mapeada a esta unidad
        $ubi = $this->obtenerUbicacionPorUnidadId($unidadId);
        $ubicacionId = $ubi ? $ubi->obtenerId() : null;

        // 3. Dotaciones estándar: primero específicas de la unidad, o por tipo_unidad
        $sqlEst = 'SELECT ide.articulo_id, ide.cantidad_estandar, a.codigo_sku, a.nombre, a.categoria, um.simbolo
                   FROM inventario_dotaciones_estandar ide
                   JOIN inventario_articulos a ON a.id = ide.articulo_id
                   JOIN inventario_unidades_medida um ON um.id = a.unidad_medida_id
                   WHERE (ide.unidad_id = :unidad_id)
                      OR (ide.tipo_unidad_id = :tipo_unidad_id AND ide.articulo_id NOT IN (
                          SELECT ide2.articulo_id FROM inventario_dotaciones_estandar ide2 WHERE ide2.unidad_id = :unidad_id_sub
                      ))';
        $stmtEst = $this->pdo->prepare($sqlEst);
        $stmtEst->execute([
            'unidad_id' => $unidadId,
            'tipo_unidad_id' => $tipoUnidadId,
            'unidad_id_sub' => $unidadId,
        ]);
        $estandar = $stmtEst->fetchAll(PDO::FETCH_ASSOC);

        $resultado = [];
        foreach ($estandar as $est) {
            $artId = (int) $est['articulo_id'];
            $categoria = $est['categoria'];
            $cantEstandar = (string) $est['cantidad_estandar'];
            $cantReal = '0.0000';

            if ($categoria === InventarioArticulo::CAT_ACTIVO_SERIALIZABLE) {
                // Activos serializados asignados a esta unidad (por ubicacion_id)
                if ($ubicacionId !== null) {
                    $stmtAct = $this->pdo->prepare(
                        'SELECT COUNT(*) FROM inventario_activos WHERE articulo_id = :art_id AND ubicacion_id = :ubi_id AND estado = "ASIGNADO"'
                    );
                    $stmtAct->execute(['art_id' => $artId, 'ubi_id' => $ubicacionId]);
                    $cantReal = number_format((float) $stmtAct->fetchColumn(), 4, '.', '');
                }
            } else {
                // Stock cuantitativo en existencias de la ubicación UNIDAD
                if ($ubicacionId !== null) {
                    $stmtEx = $this->pdo->prepare(
                        'SELECT cantidad_actual FROM inventario_existencias WHERE articulo_id = :art_id AND ubicacion_id = :ubi_id'
                    );
                    $stmtEx->execute(['art_id' => $artId, 'ubi_id' => $ubicacionId]);
                    $val = $stmtEx->fetchColumn();
                    $cantReal = $val !== false ? (string) $val : '0.0000';
                }
            }

            $diferencia = bcsub($cantReal, $cantEstandar, 4);
            $cumple = bccomp($cantReal, $cantEstandar, 4) >= 0;

            $resultado[] = [
                'articulo_id' => $artId,
                'codigo_sku' => $est['codigo_sku'],
                'nombre' => $est['nombre'],
                'categoria' => $categoria,
                'simbolo' => $est['simbolo'],
                'cantidad_estandar' => $cantEstandar,
                'cantidad_real' => $cantReal,
                'diferencia' => $diferencia,
                'cumple' => $cumple,
            ];
        }

        return $resultado;
    }

    private function mapearDotacion(array $f): InventarioDotacionEstandar
    {
        $dot = new InventarioDotacionEstandar(
            (int) $f['id'],
            $f['tipo_unidad_id'] !== null ? (int) $f['tipo_unidad_id'] : null,
            $f['unidad_id'] !== null ? (int) $f['unidad_id'] : null,
            (int) $f['articulo_id'],
            (string) $f['cantidad_estandar'],
            $f['notas'],
            $f['creado_en'],
            $f['actualizado_en']
        );

        $dot->asignarArticuloInfo(
            $f['art_sku'] ?? '',
            $f['art_nombre'] ?? '',
            $f['art_categoria'] ?? '',
            $f['um_simbolo'] ?? ''
        );

        $dot->asignarTipoUnidadNombre($f['tipo_unidad_nombre'] ?? null);
        $dot->asignarUnidadNumero($f['unidad_numero'] ?? null);

        return $dot;
    }
}
