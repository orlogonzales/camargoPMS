<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\CategoriaServicio;
use CamargoPMS\Modelos\ModalidadCobroServicio;
use CamargoPMS\Modelos\Servicio;
use CamargoPMS\Modelos\ServicioProveedor;
use CamargoPMS\Nucleo\BaseDatos;
use PDO;

/**
 * Repositorio de persistencia para el catálogo maestro de servicios, categorías, modalidades y proveedores homologados.
 */
class ServicioRepositorio
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

    // =========================================================================
    // 1. Catálogo Maestro de Servicios
    // =========================================================================

    /**
     * Busca un servicio por su ID primario.
     */
    public function buscarPorId(int $id, bool $cargarProveedores = true): ?Servicio
    {
        $sql = 'SELECT s.*,
                       c.codigo AS categoria_codigo,
                       c.nombre AS categoria_nombre,
                       c.icono AS categoria_icono,
                       m.codigo AS modalidad_cobro_codigo,
                       m.nombre AS modalidad_cobro_nombre,
                       p.nombre AS propiedad_nombre
                FROM servicios s
                INNER JOIN categorias_servicio c ON s.categoria_id = c.id
                INNER JOIN modalidades_cobro_servicio m ON s.modalidad_cobro_id = m.id
                LEFT JOIN propiedades p ON s.propiedad_id = p.id
                WHERE s.id = :id
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fila) {
            return null;
        }

        $servicio = Servicio::desdeArreglo($fila);

        if ($cargarProveedores) {
            $servicio->asignarProveedoresHomologados($this->listarProveedoresPorServicio($id));
        }

        return $servicio;
    }

    /**
     * Busca un servicio por su código único.
     */
    public function buscarPorCodigo(string $codigo, bool $cargarProveedores = true): ?Servicio
    {
        $sql = 'SELECT s.*,
                       c.codigo AS categoria_codigo,
                       c.nombre AS categoria_nombre,
                       c.icono AS categoria_icono,
                       m.codigo AS modalidad_cobro_codigo,
                       m.nombre AS modalidad_cobro_nombre,
                       p.nombre AS propiedad_nombre
                FROM servicios s
                INNER JOIN categorias_servicio c ON s.categoria_id = c.id
                INNER JOIN modalidades_cobro_servicio m ON s.modalidad_cobro_id = m.id
                LEFT JOIN propiedades p ON s.propiedad_id = p.id
                WHERE s.codigo = :codigo
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':codigo', trim(strtoupper($codigo)), PDO::PARAM_STR);
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fila) {
            return null;
        }

        $servicio = Servicio::desdeArreglo($fila);

        if ($cargarProveedores) {
            $servicio->asignarProveedoresHomologados($this->listarProveedoresPorServicio((int) $servicio->obtenerId()));
        }

        return $servicio;
    }

    /**
     * Lista servicios con filtros opcionales.
     *
     * @param array<string, mixed> $filtros
     * @return array<int, Servicio>
     */
    public function listar(array $filtros = []): array
    {
        $sql = 'SELECT s.*,
                       c.codigo AS categoria_codigo,
                       c.nombre AS categoria_nombre,
                       c.icono AS categoria_icono,
                       m.codigo AS modalidad_cobro_codigo,
                       m.nombre AS modalidad_cobro_nombre,
                       p.nombre AS propiedad_nombre
                FROM servicios s
                INNER JOIN categorias_servicio c ON s.categoria_id = c.id
                INNER JOIN modalidades_cobro_servicio m ON s.modalidad_cobro_id = m.id
                LEFT JOIN propiedades p ON s.propiedad_id = p.id
                WHERE 1 = 1';

        $params = [];

        if (!empty($filtros['categoria_id'])) {
            $sql .= ' AND s.categoria_id = :categoria_id';
            $params[':categoria_id'] = (int) $filtros['categoria_id'];
        }

        if (!empty($filtros['modalidad_cobro_id'])) {
            $sql .= ' AND s.modalidad_cobro_id = :modalidad_cobro_id';
            $params[':modalidad_cobro_id'] = (int) $filtros['modalidad_cobro_id'];
        }

        if (isset($filtros['propiedad_id'])) {
            if ($filtros['propiedad_id'] === 'null' || $filtros['propiedad_id'] === null) {
                $sql .= ' AND s.propiedad_id IS NULL';
            } elseif ($filtros['propiedad_id'] !== '') {
                $sql .= ' AND (s.propiedad_id = :propiedad_id OR s.propiedad_id IS NULL)';
                $params[':propiedad_id'] = (int) $filtros['propiedad_id'];
            }
        }

        if (!empty($filtros['estado'])) {
            $sql .= ' AND s.estado = :estado';
            $params[':estado'] = strtoupper(trim((string) $filtros['estado']));
        }

        if (!empty($filtros['q'])) {
            $sql .= ' AND (s.codigo LIKE :q OR s.nombre LIKE :q OR s.descripcion LIKE :q)';
            $params[':q'] = '%' . trim((string) $filtros['q']) . '%';
        }

        $sql .= ' ORDER BY c.orden ASC, s.nombre ASC';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $clave => $valor) {
            $stmt->bindValue($clave, $valor);
        }
        $stmt->execute();

        $resultados = [];
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resultados[] = Servicio::desdeArreglo($fila);
        }

        return $resultados;
    }

    /**
     * Inserta un nuevo servicio en el catálogo.
     */
    public function crear(Servicio $servicio): int
    {
        $sql = 'INSERT INTO servicios (
                    codigo, categoria_id, modalidad_cobro_id, propiedad_id, nombre,
                    descripcion, precio_venta_referencial, costo_referencial, moneda_codigo,
                    es_operacion_interna_habitual, requiere_traslado_detalle, estado
                ) VALUES (
                    :codigo, :categoria_id, :modalidad_cobro_id, :propiedad_id, :nombre,
                    :descripcion, :precio_venta, :costo, :moneda,
                    :es_interna, :requiere_traslado, :estado
                )';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':codigo', $servicio->obtenerCodigo(), PDO::PARAM_STR);
        $stmt->bindValue(':categoria_id', $servicio->obtenerCategoriaId(), PDO::PARAM_INT);
        $stmt->bindValue(':modalidad_cobro_id', $servicio->obtenerModalidadCobroId(), PDO::PARAM_INT);
        $stmt->bindValue(':propiedad_id', $servicio->obtenerPropiedadId(), $servicio->obtenerPropiedadId() !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':nombre', $servicio->obtenerNombre(), PDO::PARAM_STR);
        $stmt->bindValue(':descripcion', $servicio->obtenerDescripcion(), $servicio->obtenerDescripcion() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':precio_venta', $servicio->obtenerPrecioVentaReferencial(), PDO::PARAM_STR);
        $stmt->bindValue(':costo', $servicio->obtenerCostoReferencial(), PDO::PARAM_STR);
        $stmt->bindValue(':moneda', $servicio->obtenerMonedaCodigo(), PDO::PARAM_STR);
        $stmt->bindValue(':es_interna', $servicio->esOperacionInternaHabitual() ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':requiere_traslado', $servicio->requiereTrasladoDetalle() ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':estado', $servicio->obtenerEstado(), PDO::PARAM_STR);

        $stmt->execute();
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Actualiza un servicio en el catálogo.
     */
    public function actualizar(Servicio $servicio): bool
    {
        if ($servicio->obtenerId() === null) {
            return false;
        }

        $sql = 'UPDATE servicios SET
                    codigo = :codigo,
                    categoria_id = :categoria_id,
                    modalidad_cobro_id = :modalidad_cobro_id,
                    propiedad_id = :propiedad_id,
                    nombre = :nombre,
                    descripcion = :descripcion,
                    precio_venta_referencial = :precio_venta,
                    costo_referencial = :costo,
                    moneda_codigo = :moneda,
                    es_operacion_interna_habitual = :es_interna,
                    requiere_traslado_detalle = :requiere_traslado,
                    estado = :estado
                WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $servicio->obtenerId(), PDO::PARAM_INT);
        $stmt->bindValue(':codigo', $servicio->obtenerCodigo(), PDO::PARAM_STR);
        $stmt->bindValue(':categoria_id', $servicio->obtenerCategoriaId(), PDO::PARAM_INT);
        $stmt->bindValue(':modalidad_cobro_id', $servicio->obtenerModalidadCobroId(), PDO::PARAM_INT);
        $stmt->bindValue(':propiedad_id', $servicio->obtenerPropiedadId(), $servicio->obtenerPropiedadId() !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':nombre', $servicio->obtenerNombre(), PDO::PARAM_STR);
        $stmt->bindValue(':descripcion', $servicio->obtenerDescripcion(), $servicio->obtenerDescripcion() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':precio_venta', $servicio->obtenerPrecioVentaReferencial(), PDO::PARAM_STR);
        $stmt->bindValue(':costo', $servicio->obtenerCostoReferencial(), PDO::PARAM_STR);
        $stmt->bindValue(':moneda', $servicio->obtenerMonedaCodigo(), PDO::PARAM_STR);
        $stmt->bindValue(':es_interna', $servicio->esOperacionInternaHabitual() ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':requiere_traslado', $servicio->requiereTrasladoDetalle() ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':estado', $servicio->obtenerEstado(), PDO::PARAM_STR);

        return $stmt->execute();
    }

    /**
     * Cambia el estado de un servicio (ACTIVO / INACTIVO).
     */
    public function cambiarEstado(int $id, string $estado): bool
    {
        $sql = 'UPDATE servicios SET estado = :estado WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':estado', strtoupper(trim($estado)), PDO::PARAM_STR);
        return $stmt->execute();
    }

    /**
     * Comprueba si ya existe un código de servicio.
     */
    public function existeCodigo(string $codigo, ?int $excluirId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM servicios WHERE codigo = :codigo';
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

    // =========================================================================
    // 2. Categorías de Servicio
    // =========================================================================

    /**
     * @return array<int, CategoriaServicio>
     */
    public function listarCategorias(bool $soloActivas = false): array
    {
        $sql = 'SELECT * FROM categorias_servicio';
        if ($soloActivas) {
            $sql .= " WHERE estado = 'ACTIVO'";
        }
        $sql .= ' ORDER BY orden ASC, nombre ASC';

        $stmt = $this->pdo->query($sql);
        $resultados = [];
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resultados[] = CategoriaServicio::desdeArreglo($fila);
        }
        return $resultados;
    }

    public function buscarCategoriaPorId(int $id): ?CategoriaServicio
    {
        $stmt = $this->pdo->prepare('SELECT * FROM categorias_servicio WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? CategoriaServicio::desdeArreglo($fila) : null;
    }

    public function buscarCategoriaPorCodigo(string $codigo): ?CategoriaServicio
    {
        $stmt = $this->pdo->prepare('SELECT * FROM categorias_servicio WHERE codigo = :codigo LIMIT 1');
        $stmt->bindValue(':codigo', trim(strtoupper($codigo)), PDO::PARAM_STR);
        $stmt->execute();
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? CategoriaServicio::desdeArreglo($fila) : null;
    }

    // =========================================================================
    // 3. Modalidades de Cobro
    // =========================================================================

    /**
     * @return array<int, ModalidadCobroServicio>
     */
    public function listarModalidades(bool $soloActivas = false): array
    {
        $sql = 'SELECT * FROM modalidades_cobro_servicio';
        if ($soloActivas) {
            $sql .= " WHERE estado = 'ACTIVO'";
        }
        $sql .= ' ORDER BY id ASC';

        $stmt = $this->pdo->query($sql);
        $resultados = [];
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resultados[] = ModalidadCobroServicio::desdeArreglo($fila);
        }
        return $resultados;
    }

    public function buscarModalidadPorId(int $id): ?ModalidadCobroServicio
    {
        $stmt = $this->pdo->prepare('SELECT * FROM modalidades_cobro_servicio WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? ModalidadCobroServicio::desdeArreglo($fila) : null;
    }

    public function buscarModalidadPorCodigo(string $codigo): ?ModalidadCobroServicio
    {
        $stmt = $this->pdo->prepare('SELECT * FROM modalidades_cobro_servicio WHERE codigo = :codigo LIMIT 1');
        $stmt->bindValue(':codigo', trim(strtoupper($codigo)), PDO::PARAM_STR);
        $stmt->execute();
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? ModalidadCobroServicio::desdeArreglo($fila) : null;
    }

    // =========================================================================
    // 4. Matriz Homologada N:M Proveedor ↔ Servicio
    // =========================================================================

    /**
     * Lista proveedores homologados asociados a un servicio.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listarProveedoresPorServicio(int $servicioId): array
    {
        $sql = 'SELECT sp.*,
                       p.codigo AS proveedor_codigo,
                       p.razon_social AS proveedor_razon_social,
                       p.nombre_comercial AS proveedor_nombre_comercial,
                       p.tipo AS proveedor_tipo,
                       s.nombre AS servicio_nombre
                FROM servicio_proveedores sp
                INNER JOIN proveedores p ON sp.proveedor_id = p.id
                INNER JOIN servicios s ON sp.servicio_id = s.id
                WHERE sp.servicio_id = :servicio_id
                ORDER BY sp.es_preferente DESC, p.razon_social ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':servicio_id', $servicioId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Asigna o actualiza la relación N:M entre un servicio y un proveedor.
     */
    public function asignarProveedor(
        int $servicioId,
        int $proveedorId,
        ?string $costoPactado,
        bool $esPreferente,
        int $tiempoAnticipacionHoras = 0,
        string $estado = 'ACTIVO'
    ): int {
        if ($esPreferente) {
            $this->desmarcarPreferentes($servicioId, $proveedorId);
        }

        $sql = 'INSERT INTO servicio_proveedores (
                    servicio_id, proveedor_id, costo_pactado, es_preferente,
                    tiempo_anticipacion_horas, estado
                ) VALUES (
                    :servicio_id, :proveedor_id, :costo_pactado, :es_preferente,
                    :tiempo_anticipacion, :estado
                ) ON DUPLICATE KEY UPDATE
                    costo_pactado = VALUES(costo_pactado),
                    es_preferente = VALUES(es_preferente),
                    tiempo_anticipacion_horas = VALUES(tiempo_anticipacion_horas),
                    estado = VALUES(estado)';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':servicio_id', $servicioId, PDO::PARAM_INT);
        $stmt->bindValue(':proveedor_id', $proveedorId, PDO::PARAM_INT);
        $stmt->bindValue(':costo_pactado', $costoPactado !== null ? number_format((float) $costoPactado, 2, '.', '') : null, $costoPactado !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':es_preferente', $esPreferente ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':tiempo_anticipacion', $tiempoAnticipacionHoras, PDO::PARAM_INT);
        $stmt->bindValue(':estado', strtoupper(trim($estado)), PDO::PARAM_STR);

        $stmt->execute();
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Desmarca los preferentes de un servicio para garantizar máximo 1 preferente.
     */
    public function desmarcarPreferentes(int $servicioId, ?int $exceptoProveedorId = null): void
    {
        $sql = 'UPDATE servicio_proveedores SET es_preferente = 0 WHERE servicio_id = :servicio_id';
        if ($exceptoProveedorId !== null) {
            $sql .= ' AND proveedor_id <> :excepto_id';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':servicio_id', $servicioId, PDO::PARAM_INT);
        if ($exceptoProveedorId !== null) {
            $stmt->bindValue(':excepto_id', $exceptoProveedorId, PDO::PARAM_INT);
        }
        $stmt->execute();
    }

    /**
     * Obtiene el proveedor preferente de un servicio, si existe.
     */
    public function obtenerProveedorPreferente(int $servicioId): ?ServicioProveedor
    {
        $sql = 'SELECT sp.*,
                       p.codigo AS proveedor_codigo,
                       p.razon_social AS proveedor_razon_social,
                       p.nombre_comercial AS proveedor_nombre_comercial,
                       p.tipo AS proveedor_tipo,
                       s.nombre AS servicio_nombre
                FROM servicio_proveedores sp
                INNER JOIN proveedores p ON sp.proveedor_id = p.id
                INNER JOIN servicios s ON sp.servicio_id = s.id
                WHERE sp.servicio_id = :servicio_id
                  AND sp.es_preferente = 1
                  AND sp.estado = "ACTIVO"
                  AND p.estado = "ACTIVO"
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':servicio_id', $servicioId, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? ServicioProveedor::desdeArreglo($fila) : null;
    }
}
