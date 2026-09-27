<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

use CamargoPMS\Excepciones\ActivoNoEncontradoExcepcion;
use CamargoPMS\Excepciones\ArticuloNoEncontradoExcepcion;
use CamargoPMS\Excepciones\ConflictoInventarioExcepcion;
use CamargoPMS\Excepciones\MovimientoInvalidoExcepcion;
use CamargoPMS\Excepciones\OrdenTrabajoNoEncontradaExcepcion;
use CamargoPMS\Excepciones\PropiedadNoEncontradaExcepcion;
use CamargoPMS\Excepciones\StockInsuficienteExcepcion;
use CamargoPMS\Excepciones\UbicacionNoEncontradaExcepcion;
use CamargoPMS\Excepciones\ValidacionExcepcion;
use CamargoPMS\Modelos\InventarioActivo;
use CamargoPMS\Modelos\InventarioArticulo;
use CamargoPMS\Modelos\InventarioDotacionEstandar;
use CamargoPMS\Modelos\InventarioExistencia;
use CamargoPMS\Modelos\InventarioMovimiento;
use CamargoPMS\Modelos\InventarioUbicacion;
use CamargoPMS\Modelos\InventarioUnidadMedida;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Repositorios\InventarioRepositorio;
use CamargoPMS\Repositorios\OrdenTrabajoRepositorio;
use CamargoPMS\Repositorios\PropiedadRepositorio;
use CamargoPMS\Repositorios\UnidadRepositorio;
use DateTimeImmutable;
use PDO;
use PDOException;
use Throwable;

/**
 * Servicio de dominio central para el catálogo de artículos, almacenes y ubicaciones,
 * existencias, bitácora de movimientos (Kardex), activos serializados y dotaciones.
 * 
 * Reglas vinculantes (D-078):
 * 1. ARTÍCULO != EXISTENCIA != MOVIMIENTO != ACTIVO INDIVIDUAL.
 * 2. cantidad_actual es proyección materializada; el Kardex es la verdad soberana append-only.
 * 3. Traslados de dos patas con correlativo único atómico (TRASLADO_SALIDA y TRASLADO_ENTRADA).
 * 4. Precisión numérica: Cantidades en DECIMAL(15,4), costo unitario en DECIMAL(15,4), costo total en DECIMAL(15,2).
 * 5. Moneda funcional desacoplada (PEN).
 * 6. Activos serializables no poseen stock en existencias; viven en inventario_activos.
 * 7. Integración no incremental con MANTENIMIENTO-1: salidas recalculan el costo de materiales de la OT.
 * 8. Concurrencia determinista: locking SELECT ... FOR UPDATE ordenado por ID físico para mitigar deadlocks.
 * 9. Stock negativo estrictamente imposible (CHECK DDL + validación previa con StockInsuficienteExcepcion 422).
 */
class InventarioServicio
{
    private PDO $pdo;
    private InventarioRepositorio $inventarioRepo;
    private PropiedadRepositorio $propiedadRepo;
    private UnidadRepositorio $unidadRepo;
    private ?OrdenTrabajoRepositorio $ordenRepo;

    public function __construct(
        ?PDO $pdo = null,
        ?InventarioRepositorio $inventarioRepo = null,
        ?PropiedadRepositorio $propiedadRepo = null,
        ?UnidadRepositorio $unidadRepo = null,
        ?OrdenTrabajoRepositorio $ordenRepo = null
    ) {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->inventarioRepo = $inventarioRepo ?? new InventarioRepositorio($this->pdo);
        $this->propiedadRepo = $propiedadRepo ?? new PropiedadRepositorio($this->pdo);
        $this->unidadRepo = $unidadRepo ?? new UnidadRepositorio($this->pdo);
        $this->ordenRepo = $ordenRepo ?? new OrdenTrabajoRepositorio($this->pdo);
    }

    // =========================================================================
    // 1. GESTIÓN DEL CATÁLOGO DE ARTÍCULOS
    // =========================================================================

    public function crearArticulo(array $datos): InventarioArticulo
    {
        $this->validarDatosArticulo($datos);

        $sku = strtoupper(trim((string) ($datos['codigo_sku'] ?? '')));
        if ($this->inventarioRepo->existeSku($sku)) {
            throw new ValidacionExcepcion("Ya existe un artículo con el código SKU [{$sku}].");
        }

        $umId = (int) $datos['unidad_medida_id'];
        $um = $this->inventarioRepo->obtenerUnidadMedidaPorId($umId);
        if (!$um) {
            throw new ValidacionExcepcion("La unidad de medida seleccionada no existe.");
        }

        $costoRef = isset($datos['costo_referencial']) ? (string) $datos['costo_referencial'] : '0.0000';
        $stockMin = isset($datos['stock_minimo_alerta']) ? (string) $datos['stock_minimo_alerta'] : '0.0000';

        $articulo = new InventarioArticulo(
            null,
            $sku,
            trim((string) $datos['nombre']),
            !empty($datos['descripcion']) ? trim((string) $datos['descripcion']) : null,
            strtoupper(trim((string) $datos['categoria'])),
            $umId,
            $this->formatearDecimal($costoRef, 4),
            !empty($datos['moneda_codigo']) ? strtoupper(trim((string) $datos['moneda_codigo'])) : 'PEN',
            $this->formatearDecimal($stockMin, 4),
            !empty($datos['estado']) ? strtoupper(trim((string) $datos['estado'])) : InventarioArticulo::ESTADO_ACTIVO
        );

        $id = $this->inventarioRepo->crearArticulo($articulo);

        return $this->inventarioRepo->obtenerArticuloPorId($id);
    }

    public function actualizarArticulo(int $id, array $datos): InventarioArticulo
    {
        $articulo = $this->inventarioRepo->obtenerArticuloPorId($id);
        if (!$articulo) {
            throw new ArticuloNoEncontradoExcepcion("El artículo con ID [{$id}] no existe.");
        }

        $this->validarDatosArticulo($datos, $id);

        $sku = strtoupper(trim((string) ($datos['codigo_sku'] ?? $articulo->obtenerCodigoSku())));
        if ($this->inventarioRepo->existeSku($sku, $id)) {
            throw new ValidacionExcepcion("Ya existe otro artículo con el código SKU [{$sku}].");
        }

        $umId = isset($datos['unidad_medida_id']) ? (int) $datos['unidad_medida_id'] : $articulo->obtenerUnidadMedidaId();
        $um = $this->inventarioRepo->obtenerUnidadMedidaPorId($umId);
        if (!$um) {
            throw new ValidacionExcepcion("La unidad de medida seleccionada no existe.");
        }

        $costoRef = isset($datos['costo_referencial']) ? (string) $datos['costo_referencial'] : $articulo->obtenerCostoReferencial();
        $stockMin = isset($datos['stock_minimo_alerta']) ? (string) $datos['stock_minimo_alerta'] : $articulo->obtenerStockMinimoAlerta();

        $actualizado = new InventarioArticulo(
            $id,
            $sku,
            isset($datos['nombre']) ? trim((string) $datos['nombre']) : $articulo->obtenerNombre(),
            array_key_exists('descripcion', $datos) ? ($datos['descripcion'] !== null ? trim((string) $datos['descripcion']) : null) : $articulo->obtenerDescripcion(),
            isset($datos['categoria']) ? strtoupper(trim((string) $datos['categoria'])) : $articulo->obtenerCategoria(),
            $umId,
            $this->formatearDecimal($costoRef, 4),
            isset($datos['moneda_codigo']) ? strtoupper(trim((string) $datos['moneda_codigo'])) : $articulo->obtenerMonedaCodigo(),
            $this->formatearDecimal($stockMin, 4),
            isset($datos['estado']) ? strtoupper(trim((string) $datos['estado'])) : $articulo->obtenerEstado(),
            $articulo->obtenerCreadoEn()
        );

        $this->inventarioRepo->actualizarArticulo($actualizado);

        return $this->inventarioRepo->obtenerArticuloPorId($id);
    }

    private function validarDatosArticulo(array $datos, ?int $idActual = null): void
    {
        $esCreacion = ($idActual === null);

        if ($esCreacion || array_key_exists('nombre', $datos)) {
            if (empty($datos['nombre']) || trim((string) $datos['nombre']) === '') {
                throw new ValidacionExcepcion("El nombre del artículo es obligatorio.");
            }
        }

        if ($esCreacion || array_key_exists('codigo_sku', $datos)) {
            if (empty($datos['codigo_sku']) || trim((string) $datos['codigo_sku']) === '') {
                throw new ValidacionExcepcion("El código SKU es obligatorio.");
            }
        }

        $categoriasValidas = [
            InventarioArticulo::CAT_CONSUMIBLE_OPERATIVO,
            InventarioArticulo::CAT_LENCERIA_BLANCOS,
            InventarioArticulo::CAT_REPUESTO_MANTENIMIENTO,
            InventarioArticulo::CAT_ACTIVO_SERIALIZABLE,
            InventarioArticulo::CAT_HERRAMIENTA,
            InventarioArticulo::CAT_OTRO,
        ];

        if ($esCreacion || array_key_exists('categoria', $datos)) {
            if (empty($datos['categoria']) || !in_array(strtoupper(trim((string) $datos['categoria'])), $categoriasValidas, true)) {
                throw new ValidacionExcepcion("La categoría del artículo no es válida.");
            }
        }

        if ($esCreacion || array_key_exists('unidad_medida_id', $datos)) {
            if (empty($datos['unidad_medida_id']) || (int) $datos['unidad_medida_id'] <= 0) {
                throw new ValidacionExcepcion("Debe especificar una unidad de medida válida.");
            }
        }
    }

    // =========================================================================
    // 2. GESTIÓN DE UBICACIONES Y ALMACENES
    // =========================================================================

    public function crearUbicacion(array $datos): InventarioUbicacion
    {
        $this->validarDatosUbicacion($datos);

        $codigo = strtoupper(trim((string) ($datos['codigo'] ?? '')));
        if ($this->inventarioRepo->existeCodigoUbicacion($codigo)) {
            throw new ValidacionExcepcion("Ya existe una ubicación con el código [{$codigo}].");
        }

        $propiedadId = (int) $datos['propiedad_id'];
        if (!$this->propiedadRepo->buscarPorId($propiedadId)) {
            throw new PropiedadNoEncontradaExcepcion("La propiedad indicada no existe.");
        }

        $tipo = strtoupper(trim((string) $datos['tipo']));
        $unidadId = null;

        if ($tipo === InventarioUbicacion::TIPO_UNIDAD) {
            $unidadId = (int) ($datos['unidad_id'] ?? 0);
            if ($unidadId <= 0 || !$this->unidadRepo->obtenerPorId($unidadId)) {
                throw new ValidacionExcepcion("Para una ubicación de tipo UNIDAD debe especificar una unidad habitacional válida.");
            }

            // Verificar si la unidad ya tiene una ubicación asignada
            $existente = $this->inventarioRepo->obtenerUbicacionPorUnidadId($unidadId);
            if ($existente !== null) {
                throw new ValidacionExcepcion("La unidad habitacional [{$unidadId}] ya posee una ubicación física vinculada ({$existente->obtenerCodigo()}).");
            }
        }

        $proveedorId = !empty($datos['proveedor_id']) ? (int) $datos['proveedor_id'] : null;
        $colaboradorId = !empty($datos['responsable_colaborador_id']) ? (int) $datos['responsable_colaborador_id'] : null;

        $ubicacion = new InventarioUbicacion(
            null,
            $propiedadId,
            $codigo,
            trim((string) $datos['nombre']),
            $tipo,
            $unidadId,
            $proveedorId,
            $colaboradorId,
            !empty($datos['estado']) ? strtoupper(trim((string) $datos['estado'])) : InventarioUbicacion::ESTADO_ACTIVO
        );

        $id = $this->inventarioRepo->crearUbicacion($ubicacion);

        return $this->inventarioRepo->obtenerUbicacionPorId($id);
    }

    public function actualizarUbicacion(int $id, array $datos): InventarioUbicacion
    {
        $ubicacion = $this->inventarioRepo->obtenerUbicacionPorId($id);
        if (!$ubicacion) {
            throw new UbicacionNoEncontradaExcepcion("La ubicación con ID [{$id}] no existe.");
        }

        $codigo = isset($datos['codigo']) ? strtoupper(trim((string) $datos['codigo'])) : $ubicacion->obtenerCodigo();
        if ($this->inventarioRepo->existeCodigoUbicacion($codigo, $id)) {
            throw new ValidacionExcepcion("Ya existe otra ubicación con el código [{$codigo}].");
        }

        $propiedadId = isset($datos['propiedad_id']) ? (int) $datos['propiedad_id'] : $ubicacion->obtenerPropiedadId();
        $tipo = isset($datos['tipo']) ? strtoupper(trim((string) $datos['tipo'])) : $ubicacion->obtenerTipo();
        $unidadId = $ubicacion->obtenerUnidadId();

        if ($tipo === InventarioUbicacion::TIPO_UNIDAD) {
            $unidadId = isset($datos['unidad_id']) ? (int) $datos['unidad_id'] : $ubicacion->obtenerUnidadId();
            if (!$unidadId || !$this->unidadRepo->obtenerPorId($unidadId)) {
                throw new ValidacionExcepcion("Para una ubicación de tipo UNIDAD debe especificar una unidad habitacional válida.");
            }
        } else {
            $unidadId = null;
        }

        $actualizada = new InventarioUbicacion(
            $id,
            $propiedadId,
            $codigo,
            isset($datos['nombre']) ? trim((string) $datos['nombre']) : $ubicacion->obtenerNombre(),
            $tipo,
            $unidadId,
            array_key_exists('proveedor_id', $datos) ? ($datos['proveedor_id'] ? (int) $datos['proveedor_id'] : null) : $ubicacion->obtenerProveedorId(),
            array_key_exists('responsable_colaborador_id', $datos) ? ($datos['responsable_colaborador_id'] ? (int) $datos['responsable_colaborador_id'] : null) : $ubicacion->obtenerResponsableColaboradorId(),
            isset($datos['estado']) ? strtoupper(trim((string) $datos['estado'])) : $ubicacion->obtenerEstado(),
            $ubicacion->obtenerCreadoEn()
        );

        $this->inventarioRepo->actualizarUbicacion($actualizada);

        return $this->inventarioRepo->obtenerUbicacionPorId($id);
    }

    public function asegurarUbicacionParaUnidad(int $unidadId): InventarioUbicacion
    {
        $existente = $this->inventarioRepo->obtenerUbicacionPorUnidadId($unidadId);
        if ($existente !== null) {
            return $existente;
        }

        $unidad = $this->unidadRepo->obtenerPorId($unidadId);
        if (!$unidad) {
            throw new ValidacionExcepcion("La unidad habitacional [{$unidadId}] no existe.");
        }

        $codigo = "UBI-HAB-{$unidad->obtenerCodigo()}";
        $nombre = "Habitación {$unidad->obtenerNombre()}";

        // Asegurar que el código sea único
        if ($this->inventarioRepo->existeCodigoUbicacion($codigo)) {
            $codigo .= "-U{$unidadId}";
        }

        return $this->crearUbicacion([
            'propiedad_id' => $unidad->obtenerPropiedadId(),
            'codigo' => $codigo,
            'nombre' => $nombre,
            'tipo' => InventarioUbicacion::TIPO_UNIDAD,
            'unidad_id' => $unidadId,
            'estado' => InventarioUbicacion::ESTADO_ACTIVO,
        ]);
    }

    private function validarDatosUbicacion(array $datos): void
    {
        if (empty($datos['nombre']) || trim((string) $datos['nombre']) === '') {
            throw new ValidacionExcepcion("El nombre de la ubicación es obligatorio.");
        }

        if (empty($datos['codigo']) || trim((string) $datos['codigo']) === '') {
            throw new ValidacionExcepcion("El código de la ubicación es obligatorio.");
        }

        $tiposValidos = [
            InventarioUbicacion::TIPO_ALMACEN,
            InventarioUbicacion::TIPO_UNIDAD,
            InventarioUbicacion::TIPO_CUSTODIA_EXTERNA,
        ];

        if (empty($datos['tipo']) || !in_array(strtoupper(trim((string) $datos['tipo'])), $tiposValidos, true)) {
            throw new ValidacionExcepcion("El tipo de ubicación no es válido.");
        }

        if (empty($datos['propiedad_id']) || (int) $datos['propiedad_id'] <= 0) {
            throw new ValidacionExcepcion("Debe seleccionar una propiedad física válida.");
        }
    }

    // =========================================================================
    // 3. MOVIMIENTOS Y KARDEX (CONCURRENCIA Y ATOMICIDAD)
    // =========================================================================

    /**
     * Registra el saldo inicial de un artículo en una ubicación (D-078 #14).
     */
    public function registrarSaldoInicial(
        int $articuloId,
        int $ubicacionId,
        string $cantidad,
        string $costoUnitario,
        int $actorId,
        string $motivo = 'Registro de saldo inicial'
    ): InventarioMovimiento {
        return $this->ejecutarTransaccionMovimiento(function () use ($articuloId, $ubicacionId, $cantidad, $costoUnitario, $actorId, $motivo) {
            $articulo = $this->obtenerArticuloCuantificable($articuloId);
            $ubicacion = $this->obtenerUbicacionActiva($ubicacionId);

            $cant = $this->validarCantidad($cantidad, $articulo);
            $costoUnit = $this->formatearDecimal($costoUnitario, 4);
            $costoTotal = bcmul($cant, $costoUnit, 2);

            // Locking pesimista de existencias
            $locks = $this->inventarioRepo->obtenerExistenciasBloqueadas([
                ['articulo_id' => $articuloId, 'ubicacion_id' => $ubicacionId],
            ]);
            $ex = $locks["{$articuloId}_{$ubicacionId}"];

            $nuevaCantidad = bcadd($ex->obtenerCantidadActual(), $cant, 4);
            $this->inventarioRepo->actualizarCantidadActual((int) $ex->obtenerId(), $nuevaCantidad);

            $codigoMov = $this->generarCodigoMovimiento();
            $mov = new InventarioMovimiento(
                null,
                $codigoMov,
                InventarioMovimiento::TIPO_SALDO_INICIAL,
                $articuloId,
                $ubicacionId,
                $cant,
                $costoUnit,
                $costoTotal,
                $articulo->obtenerMonedaCodigo(),
                'SALDO_INICIAL',
                null,
                null,
                null,
                $motivo,
                $actorId
            );

            $movId = $this->inventarioRepo->crearMovimiento($mov);

            return $this->inventarioRepo->obtenerMovimientoPorId($movId);
        });
    }

    /**
     * Registra una entrada por compra directa o recepción de proveedor.
     */
    public function registrarEntradaCompra(
        int $articuloId,
        int $ubicacionId,
        string $cantidad,
        string $costoUnitario,
        int $actorId,
        string $motivo,
        ?int $proveedorId = null
    ): InventarioMovimiento {
        return $this->ejecutarTransaccionMovimiento(function () use ($articuloId, $ubicacionId, $cantidad, $costoUnitario, $actorId, $motivo, $proveedorId) {
            $articulo = $this->obtenerArticuloCuantificable($articuloId);
            $ubicacion = $this->obtenerUbicacionActiva($ubicacionId);

            $cant = $this->validarCantidad($cantidad, $articulo);
            $costoUnit = $this->formatearDecimal($costoUnitario, 4);
            $costoTotal = bcmul($cant, $costoUnit, 2);

            // Locking pesimista
            $locks = $this->inventarioRepo->obtenerExistenciasBloqueadas([
                ['articulo_id' => $articuloId, 'ubicacion_id' => $ubicacionId],
            ]);
            $ex = $locks["{$articuloId}_{$ubicacionId}"];

            $nuevaCantidad = bcadd($ex->obtenerCantidadActual(), $cant, 4);
            $this->inventarioRepo->actualizarCantidadActual((int) $ex->obtenerId(), $nuevaCantidad);

            $codigoMov = $this->generarCodigoMovimiento();
            $mov = new InventarioMovimiento(
                null,
                $codigoMov,
                InventarioMovimiento::TIPO_ENTRADA_COMPRA,
                $articuloId,
                $ubicacionId,
                $cant,
                $costoUnit,
                $costoTotal,
                $articulo->obtenerMonedaCodigo(),
                'PROVEEDOR',
                $proveedorId,
                null,
                null,
                $motivo,
                $actorId
            );

            $movId = $this->inventarioRepo->crearMovimiento($mov);

            return $this->inventarioRepo->obtenerMovimientoPorId($movId);
        });
    }

    /**
     * Registra una salida por consumo operativo ordinario (amenities, limpieza, suministros).
     */
    public function registrarSalidaConsumo(
        int $articuloId,
        int $ubicacionId,
        string $cantidad,
        int $actorId,
        string $motivo
    ): InventarioMovimiento {
        return $this->ejecutarTransaccionMovimiento(function () use ($articuloId, $ubicacionId, $cantidad, $actorId, $motivo) {
            $articulo = $this->obtenerArticuloCuantificable($articuloId);
            $ubicacion = $this->obtenerUbicacionActiva($ubicacionId);

            $cant = $this->validarCantidad($cantidad, $articulo);

            // La lencería y blancos no se consumen operativamente; se trasladan o se ajustan por merma/baja
            if ($articulo->esLenceria()) {
                throw new MovimientoInvalidoExcepcion(
                    "El artículo [{$articulo->obtenerCodigoSku()}] pertenece a lencería y blancos. Debe trasladarse entre ubicaciones mediante traslado o registrarse merma física por ajuste, no retirarse por consumo operativo ordinario."
                );
            }

            // Locking pesimista
            $locks = $this->inventarioRepo->obtenerExistenciasBloqueadas([
                ['articulo_id' => $articuloId, 'ubicacion_id' => $ubicacionId],
            ]);
            $ex = $locks["{$articuloId}_{$ubicacionId}"];

            // Prohibición absoluta de stock negativo
            if (bccomp($ex->obtenerCantidadActual(), $cant, 4) < 0) {
                throw new StockInsuficienteExcepcion(
                    $articulo->obtenerCodigoSku(),
                    $ubicacion->obtenerCodigo(),
                    $cant,
                    $ex->obtenerCantidadActual()
                );
            }

            $nuevaCantidad = bcsub($ex->obtenerCantidadActual(), $cant, 4);
            $this->inventarioRepo->actualizarCantidadActual((int) $ex->obtenerId(), $nuevaCantidad);

            $costoUnit = $articulo->obtenerCostoReferencial();
            $costoTotal = bcmul($cant, $costoUnit, 2);

            $codigoMov = $this->generarCodigoMovimiento();
            $mov = new InventarioMovimiento(
                null,
                $codigoMov,
                InventarioMovimiento::TIPO_SALIDA_CONSUMO,
                $articuloId,
                $ubicacionId,
                $cant,
                $costoUnit,
                $costoTotal,
                $articulo->obtenerMonedaCodigo(),
                'CONSUMO_OPERATIVO',
                null,
                null,
                null,
                $motivo,
                $actorId
            );

            $movId = $this->inventarioRepo->crearMovimiento($mov);

            return $this->inventarioRepo->obtenerMovimientoPorId($movId);
        });
    }

    /**
     * Registra una salida de material o repuesto para mantenimiento (D-078 #12).
     * Recalcula soberanamente el costo_materiales de la orden de trabajo.
     */
    public function registrarSalidaMantenimiento(
        int $articuloId,
        int $ubicacionId,
        string $cantidad,
        int $ordenId,
        int $actorId,
        string $motivo
    ): InventarioMovimiento {
        return $this->ejecutarTransaccionMovimiento(function () use ($articuloId, $ubicacionId, $cantidad, $ordenId, $actorId, $motivo) {
            $articulo = $this->obtenerArticuloCuantificable($articuloId);
            $ubicacion = $this->obtenerUbicacionActiva($ubicacionId);

            $orden = $this->ordenRepo->obtenerPorId($ordenId);
            if (!$orden) {
                throw new OrdenTrabajoNoEncontradaExcepcion("La orden de mantenimiento con ID [{$ordenId}] no existe.");
            }

            $cant = $this->validarCantidad($cantidad, $articulo);

            // Locking pesimista
            $locks = $this->inventarioRepo->obtenerExistenciasBloqueadas([
                ['articulo_id' => $articuloId, 'ubicacion_id' => $ubicacionId],
            ]);
            $ex = $locks["{$articuloId}_{$ubicacionId}"];

            // Prohibición absoluta de stock negativo
            if (bccomp($ex->obtenerCantidadActual(), $cant, 4) < 0) {
                throw new StockInsuficienteExcepcion(
                    $articulo->obtenerCodigoSku(),
                    $ubicacion->obtenerCodigo(),
                    $cant,
                    $ex->obtenerCantidadActual()
                );
            }

            $nuevaCantidad = bcsub($ex->obtenerCantidadActual(), $cant, 4);
            $this->inventarioRepo->actualizarCantidadActual((int) $ex->obtenerId(), $nuevaCantidad);

            $costoUnit = $articulo->obtenerCostoReferencial();
            $costoTotal = bcmul($cant, $costoUnit, 2);

            $codigoMov = $this->generarCodigoMovimiento();
            $mov = new InventarioMovimiento(
                null,
                $codigoMov,
                InventarioMovimiento::TIPO_SALIDA_MANTENIMIENTO,
                $articuloId,
                $ubicacionId,
                $cant,
                $costoUnit,
                $costoTotal,
                $articulo->obtenerMonedaCodigo(),
                'MANTENIMIENTO_ORDEN',
                $ordenId,
                null,
                null,
                $motivo,
                $actorId
            );

            $movId = $this->inventarioRepo->crearMovimiento($mov);

            // Recalcular soberanamente el costo_materiales de la orden de trabajo (D-078 #12)
            $nuevoCostoMateriales = $this->inventarioRepo->sumarCostoMovimientosPorReferencia('MANTENIMIENTO_ORDEN', $ordenId);
            $this->actualizarCostoMaterialesOrden($ordenId, $nuevoCostoMateriales);

            return $this->inventarioRepo->obtenerMovimientoPorId($movId);
        });
    }

    /**
     * Realiza un traslado atómico de dos patas entre ubicación origen y destino (D-078 #2 y #15).
     *
     * @return array [0 => MovimientoSalida, 1 => MovimientoEntrada, 'correlativo' => string]
     */
    public function registrarTraslado(
        int $articuloId,
        int $ubicacionOrigenId,
        int $ubicacionDestinoId,
        string $cantidad,
        int $actorId,
        string $motivo
    ): array {
        if ($ubicacionOrigenId === $ubicacionDestinoId) {
            throw new MovimientoInvalidoExcepcion("La ubicación de origen y destino no pueden ser la misma.");
        }

        return $this->ejecutarTransaccionMovimiento(function () use ($articuloId, $ubicacionOrigenId, $ubicacionDestinoId, $cantidad, $actorId, $motivo) {
            $articulo = $this->obtenerArticuloCuantificable($articuloId);
            $ubiOrigen = $this->obtenerUbicacionActiva($ubicacionOrigenId);
            $ubiDestino = $this->obtenerUbicacionActiva($ubicacionDestinoId);

            $cant = $this->validarCantidad($cantidad, $articulo);

            // Locking pesimista ordenado determinísticamente por ID físico (D-078 #15)
            $locks = $this->inventarioRepo->obtenerExistenciasBloqueadas([
                ['articulo_id' => $articuloId, 'ubicacion_id' => $ubicacionOrigenId],
                ['articulo_id' => $articuloId, 'ubicacion_id' => $ubicacionDestinoId],
            ]);

            $exOrigen = $locks["{$articuloId}_{$ubicacionOrigenId}"];
            $exDestino = $locks["{$articuloId}_{$ubicacionDestinoId}"];

            // Verificar suficiencia en origen
            if (bccomp($exOrigen->obtenerCantidadActual(), $cant, 4) < 0) {
                throw new StockInsuficienteExcepcion(
                    $articulo->obtenerCodigoSku(),
                    $ubiOrigen->obtenerCodigo(),
                    $cant,
                    $exOrigen->obtenerCantidadActual()
                );
            }

            // Actualizar stock materializado
            $nuevaCantOrigen = bcsub($exOrigen->obtenerCantidadActual(), $cant, 4);
            $nuevaCantDestino = bcadd($exDestino->obtenerCantidadActual(), $cant, 4);

            $this->inventarioRepo->actualizarCantidadActual((int) $exOrigen->obtenerId(), $nuevaCantOrigen);
            $this->inventarioRepo->actualizarCantidadActual((int) $exDestino->obtenerId(), $nuevaCantDestino);

            // Generar correlativo compartido único para ambas patas
            $correlativo = $this->generarCorrelativoTraslado();
            $costoUnit = $articulo->obtenerCostoReferencial();
            $costoTotal = bcmul($cant, $costoUnit, 2);

            // Pata 1: TRASLADO_SALIDA en origen
            $codigoSalida = $this->generarCodigoMovimiento();
            $movSalida = new InventarioMovimiento(
                null,
                $codigoSalida,
                InventarioMovimiento::TIPO_TRASLADO_SALIDA,
                $articuloId,
                $ubicacionOrigenId,
                $cant,
                $costoUnit,
                $costoTotal,
                $articulo->obtenerMonedaCodigo(),
                'TRASLADO',
                $ubicacionDestinoId,
                null,
                $correlativo,
                $motivo,
                $actorId
            );
            $idSalida = $this->inventarioRepo->crearMovimiento($movSalida);

            // Pata 2: TRASLADO_ENTRADA en destino (enlazada a la pata 1)
            $codigoEntrada = $this->generarCodigoMovimiento();
            $movEntrada = new InventarioMovimiento(
                null,
                $codigoEntrada,
                InventarioMovimiento::TIPO_TRASLADO_ENTRADA,
                $articuloId,
                $ubicacionDestinoId,
                $cant,
                $costoUnit,
                $costoTotal,
                $articulo->obtenerMonedaCodigo(),
                'TRASLADO',
                $ubicacionOrigenId,
                $idSalida,
                $correlativo,
                $motivo,
                $actorId
            );
            $idEntrada = $this->inventarioRepo->crearMovimiento($movEntrada);

            return [
                $this->inventarioRepo->obtenerMovimientoPorId($idSalida),
                $this->inventarioRepo->obtenerMovimientoPorId($idEntrada),
                'correlativo' => $correlativo,
            ];
        });
    }

    /**
     * Registra un ajuste de inventario (físico o merma) positivo o negativo (D-078 #13).
     */
    public function registrarAjusteFisico(
        int $articuloId,
        int $ubicacionId,
        string $cantidadDiferencia,
        string $tipoAjuste,
        int $actorId,
        string $motivo
    ): InventarioMovimiento {
        $tipoAjuste = strtoupper(trim($tipoAjuste));
        if (!in_array($tipoAjuste, [InventarioMovimiento::TIPO_AJUSTE_POSITIVO, InventarioMovimiento::TIPO_AJUSTE_NEGATIVO], true)) {
            throw new MovimientoInvalidoExcepcion("El tipo de ajuste debe ser AJUSTE_POSITIVO o AJUSTE_NEGATIVO.");
        }

        return $this->ejecutarTransaccionMovimiento(function () use ($articuloId, $ubicacionId, $cantidadDiferencia, $tipoAjuste, $actorId, $motivo) {
            $articulo = $this->obtenerArticuloCuantificable($articuloId);
            $ubicacion = $this->obtenerUbicacionActiva($ubicacionId);

            $cant = $this->validarCantidad($cantidadDiferencia, $articulo);

            // Locking pesimista
            $locks = $this->inventarioRepo->obtenerExistenciasBloqueadas([
                ['articulo_id' => $articuloId, 'ubicacion_id' => $ubicacionId],
            ]);
            $ex = $locks["{$articuloId}_{$ubicacionId}"];

            if ($tipoAjuste === InventarioMovimiento::TIPO_AJUSTE_NEGATIVO) {
                if (bccomp($ex->obtenerCantidadActual(), $cant, 4) < 0) {
                    throw new StockInsuficienteExcepcion(
                        $articulo->obtenerCodigoSku(),
                        $ubicacion->obtenerCodigo(),
                        $cant,
                        $ex->obtenerCantidadActual()
                    );
                }
                $nuevaCantidad = bcsub($ex->obtenerCantidadActual(), $cant, 4);
            } else {
                $nuevaCantidad = bcadd($ex->obtenerCantidadActual(), $cant, 4);
            }

            $this->inventarioRepo->actualizarCantidadActual((int) $ex->obtenerId(), $nuevaCantidad);

            $costoUnit = $articulo->obtenerCostoReferencial();
            $costoTotal = bcmul($cant, $costoUnit, 2);

            $codigoMov = $this->generarCodigoMovimiento();
            $mov = new InventarioMovimiento(
                null,
                $codigoMov,
                $tipoAjuste,
                $articuloId,
                $ubicacionId,
                $cant,
                $costoUnit,
                $costoTotal,
                $articulo->obtenerMonedaCodigo(),
                'AJUSTE_FISICO',
                null,
                null,
                null,
                $motivo,
                $actorId
            );

            $movId = $this->inventarioRepo->crearMovimiento($mov);

            return $this->inventarioRepo->obtenerMovimientoPorId($movId);
        });
    }

    /**
     * Neutraliza un movimiento previo erróneo mediante un reverso append-only (D-078 #13).
     */
    public function reversarMovimiento(int $movimientoId, int $actorId, string $motivo): InventarioMovimiento
    {
        return $this->ejecutarTransaccionMovimiento(function () use ($movimientoId, $actorId, $motivo) {
            $movOriginal = $this->inventarioRepo->obtenerMovimientoPorId($movimientoId);
            if (!$movOriginal) {
                throw new MovimientoInvalidoExcepcion("El movimiento a reversar con ID [{$movimientoId}] no existe.");
            }

            if ($movOriginal->obtenerTipoMovimiento() === InventarioMovimiento::TIPO_REVERSO) {
                throw new MovimientoInvalidoExcepcion("No se puede reversar un movimiento que ya es de tipo REVERSO.");
            }

            if ($this->inventarioRepo->existeReversoParaMovimiento((int) $movOriginal->obtenerId())) {
                throw new MovimientoInvalidoExcepcion(
                    "El movimiento [{$movOriginal->obtenerCodigo()}] ya fue reversado previamente. No se permite duplicar reversos del mismo movimiento."
                );
            }

            $articuloId = $movOriginal->obtenerArticuloId();
            $ubicacionId = $movOriginal->obtenerUbicacionId();
            $articulo = $this->obtenerArticuloCuantificable($articuloId);
            $cant = $movOriginal->obtenerCantidad();

            // Locking pesimista
            $locks = $this->inventarioRepo->obtenerExistenciasBloqueadas([
                ['articulo_id' => $articuloId, 'ubicacion_id' => $ubicacionId],
            ]);
            $ex = $locks["{$articuloId}_{$ubicacionId}"];

            // Si el original era incremento (sumó stock), el reverso debe restar stock
            if ($movOriginal->esIncremento()) {
                if (bccomp($ex->obtenerCantidadActual(), $cant, 4) < 0) {
                    throw new StockInsuficienteExcepcion(
                        $articulo->obtenerCodigoSku(),
                        $ex->obtenerUbicacionCodigo() ?? "UBI-{$ubicacionId}",
                        $cant,
                        $ex->obtenerCantidadActual(),
                        "No se puede reversar el movimiento [{$movOriginal->obtenerCodigo()}]: causaría stock negativo."
                    );
                }
                $nuevaCantidad = bcsub($ex->obtenerCantidadActual(), $cant, 4);
            } else {
                // Si el original era decremento (restó stock), el reverso devuelve stock
                $nuevaCantidad = bcadd($ex->obtenerCantidadActual(), $cant, 4);
            }

            $this->inventarioRepo->actualizarCantidadActual((int) $ex->obtenerId(), $nuevaCantidad);

            $codigoMov = $this->generarCodigoMovimiento();
            $reverso = new InventarioMovimiento(
                null,
                $codigoMov,
                InventarioMovimiento::TIPO_REVERSO,
                $articuloId,
                $ubicacionId,
                $cant,
                $movOriginal->obtenerCostoUnitarioHistorico(),
                $movOriginal->obtenerCostoTotalHistorico(),
                $movOriginal->obtenerMonedaCodigo(),
                'REVERSO',
                $movOriginal->obtenerId(),
                $movOriginal->obtenerId(),
                $movOriginal->obtenerCorrelativoOperacion(),
                "Reverso de movimiento [{$movOriginal->obtenerCodigo()}]: {$motivo}",
                $actorId
            );

            $reversoId = $this->inventarioRepo->crearMovimiento($reverso);

            // Si el movimiento original era de mantenimiento, recalcular costos de la orden
            if ($movOriginal->obtenerReferenciaTipo() === 'MANTENIMIENTO_ORDEN' && $movOriginal->obtenerReferenciaId()) {
                $ordenId = (int) $movOriginal->obtenerReferenciaId();
                $nuevoCosto = $this->inventarioRepo->sumarCostoMovimientosPorReferencia('MANTENIMIENTO_ORDEN', $ordenId);
                $this->actualizarCostoMaterialesOrden($ordenId, $nuevoCosto);
            }

            return $this->inventarioRepo->obtenerMovimientoPorId($reversoId);
        });
    }

    /**
     * Valida la reconciliación matemática universal entre el Kardex inmutable y la proyección operacional (D-078 #1).
     */
    public function verificarReconciliacionExistencia(int $articuloId, int $ubicacionId): bool
    {
        $ex = $this->inventarioRepo->obtenerExistencia($articuloId, $ubicacionId);
        if (!$ex) {
            return true;
        }

        $movs = $this->inventarioRepo->listarMovimientos([
            'articulo_id' => $articuloId,
            'ubicacion_id' => $ubicacionId,
        ]);

        $saldoReconstruido = '0.0000';
        // Recorrer los movimientos en orden cronológico (listarMovimientos devuelve DESC)
        $movsCronologicos = array_reverse($movs);

        foreach ($movsCronologicos as $m) {
            if ($m->obtenerTipoMovimiento() === InventarioMovimiento::TIPO_REVERSO) {
                // Consultar si el movimiento original era incremento o decremento
                $originalId = $m->obtenerMovimientoReferenciaId();
                if ($originalId) {
                    $orig = $this->inventarioRepo->obtenerMovimientoPorId($originalId);
                    if ($orig && $orig->esIncremento()) {
                        $saldoReconstruido = bcsub($saldoReconstruido, $m->obtenerCantidad(), 4);
                    } else {
                        $saldoReconstruido = bcadd($saldoReconstruido, $m->obtenerCantidad(), 4);
                    }
                }
            } elseif ($m->esIncremento()) {
                $saldoReconstruido = bcadd($saldoReconstruido, $m->obtenerCantidad(), 4);
            } elseif ($m->esDecremento()) {
                $saldoReconstruido = bcsub($saldoReconstruido, $m->obtenerCantidad(), 4);
            }
        }

        return bccomp($saldoReconstruido, $ex->obtenerCantidadActual(), 4) === 0;
    }

    // =========================================================================
    // 4. GESTIÓN DE ACTIVOS SERIALIZABLES
    // =========================================================================

    public function registrarActivo(array $datos, int $actorId): InventarioActivo
    {
        $this->validarDatosActivo($datos);

        $placa = strtoupper(trim((string) $datos['codigo_placa']));
        if ($this->inventarioRepo->existePlacaActivo($placa)) {
            throw new ValidacionExcepcion("Ya existe un activo registrado con la placa patrimonial [{$placa}].");
        }

        $articuloId = (int) $datos['articulo_id'];
        $articulo = $this->inventarioRepo->obtenerArticuloPorId($articuloId);
        if (!$articulo) {
            throw new ArticuloNoEncontradoExcepcion("El artículo base para el activo no existe.");
        }

        if (!$articulo->esSerializable()) {
            throw new ValidacionExcepcion("El artículo [{$articulo->obtenerNombre()}] no pertenece a la categoría ACTIVO_SERIALIZABLE.");
        }

        $propiedadId = (int) $datos['propiedad_id'];
        if (!$this->propiedadRepo->buscarPorId($propiedadId)) {
            throw new PropiedadNoEncontradaExcepcion("La propiedad indicada no existe.");
        }

        $ubicacionId = (int) $datos['ubicacion_id'];
        $ubicacion = $this->inventarioRepo->obtenerUbicacionPorId($ubicacionId);
        if (!$ubicacion) {
            throw new UbicacionNoEncontradaExcepcion("La ubicación indicada no existe.");
        }

        $estado = !empty($datos['estado']) ? strtoupper(trim((string) $datos['estado'])) : InventarioActivo::ESTADO_DISPONIBLE;
        $costo = isset($datos['costo_adquisicion']) ? $this->formatearDecimal((string) $datos['costo_adquisicion'], 2) : '0.00';

        $activo = new InventarioActivo(
            null,
            $articuloId,
            $placa,
            !empty($datos['numero_serie_fabricante']) ? trim((string) $datos['numero_serie_fabricante']) : null,
            !empty($datos['marca']) ? trim((string) $datos['marca']) : null,
            !empty($datos['modelo']) ? trim((string) $datos['modelo']) : null,
            $propiedadId,
            $ubicacionId,
            $estado,
            !empty($datos['fecha_adquisicion']) ? trim((string) $datos['fecha_adquisicion']) : null,
            $costo,
            !empty($datos['moneda_codigo']) ? strtoupper(trim((string) $datos['moneda_codigo'])) : 'PEN',
            !empty($datos['garantia_vence_en']) ? trim((string) $datos['garantia_vence_en']) : null,
            !empty($datos['notas']) ? trim((string) $datos['notas']) : null
        );

        $id = $this->inventarioRepo->crearActivo($activo);

        return $this->inventarioRepo->obtenerActivoPorId($id);
    }

    public function asignarActivoAUnidad(int $activoId, int $ubicacionUnidadId, int $actorId): InventarioActivo
    {
        $activo = $this->inventarioRepo->obtenerActivoPorId($activoId);
        if (!$activo) {
            throw new ActivoNoEncontradoExcepcion("El activo individual con ID [{$activoId}] no existe.");
        }

        if ($activo->estaDeBaja()) {
            throw new MovimientoInvalidoExcepcion("Un activo dado de baja no puede ser asignado.");
        }

        $ubicacion = $this->inventarioRepo->obtenerUbicacionPorId($ubicacionUnidadId);
        if (!$ubicacion || !$ubicacion->esUnidad()) {
            throw new UbicacionNoEncontradaExcepcion("La ubicación de destino debe ser una unidad habitacional activa.");
        }

        $actualizado = new InventarioActivo(
            $activoId,
            $activo->obtenerArticuloId(),
            $activo->obtenerCodigoPlaca(),
            $activo->obtenerNumeroSerieFabricante(),
            $activo->obtenerMarca(),
            $activo->obtenerModelo(),
            $ubicacion->obtenerPropiedadId(),
            $ubicacionUnidadId,
            InventarioActivo::ESTADO_ASIGNADO,
            $activo->obtenerFechaAdquisicion(),
            $activo->obtenerCostoAdquisicion(),
            $activo->obtenerMonedaCodigo(),
            $activo->obtenerGarantiaVenceEn(),
            $activo->obtenerNotas(),
            $activo->obtenerMotivoBaja(),
            $activo->obtenerBajaPorActorId()
        );

        $this->inventarioRepo->actualizarActivo($actualizado);

        return $this->inventarioRepo->obtenerActivoPorId($activoId);
    }

    public function transferirActivo(int $activoId, int $nuevaUbicacionId, int $actorId): InventarioActivo
    {
        $activo = $this->inventarioRepo->obtenerActivoPorId($activoId);
        if (!$activo) {
            throw new ActivoNoEncontradoExcepcion("El activo individual con ID [{$activoId}] no existe.");
        }

        if ($activo->estaDeBaja()) {
            throw new MovimientoInvalidoExcepcion("Un activo dado de baja no puede ser transferido.");
        }

        $ubicacion = $this->inventarioRepo->obtenerUbicacionPorId($nuevaUbicacionId);
        if (!$ubicacion || !$ubicacion->estaActiva()) {
            throw new UbicacionNoEncontradaExcepcion("La ubicación de destino no existe o está inactiva.");
        }

        $nuevoEstado = $ubicacion->esUnidad() ? InventarioActivo::ESTADO_ASIGNADO : InventarioActivo::ESTADO_DISPONIBLE;

        $actualizado = new InventarioActivo(
            $activoId,
            $activo->obtenerArticuloId(),
            $activo->obtenerCodigoPlaca(),
            $activo->obtenerNumeroSerieFabricante(),
            $activo->obtenerMarca(),
            $activo->obtenerModelo(),
            $ubicacion->obtenerPropiedadId(),
            $nuevaUbicacionId,
            $nuevoEstado,
            $activo->obtenerFechaAdquisicion(),
            $activo->obtenerCostoAdquisicion(),
            $activo->obtenerMonedaCodigo(),
            $activo->obtenerGarantiaVenceEn(),
            $activo->obtenerNotas(),
            $activo->obtenerMotivoBaja(),
            $activo->obtenerBajaPorActorId()
        );

        $this->inventarioRepo->actualizarActivo($actualizado);

        return $this->inventarioRepo->obtenerActivoPorId($activoId);
    }

    public function darDeBajaActivo(int $activoId, string $motivoBaja, int $actorId): InventarioActivo
    {
        $activo = $this->inventarioRepo->obtenerActivoPorId($activoId);
        if (!$activo) {
            throw new ActivoNoEncontradoExcepcion("El activo individual con ID [{$activoId}] no existe.");
        }

        if (trim($motivoBaja) === '') {
            throw new ValidacionExcepcion("Debe proporcionar un motivo para dar de baja el activo patrimonial.");
        }

        $actualizado = new InventarioActivo(
            $activoId,
            $activo->obtenerArticuloId(),
            $activo->obtenerCodigoPlaca(),
            $activo->obtenerNumeroSerieFabricante(),
            $activo->obtenerMarca(),
            $activo->obtenerModelo(),
            $activo->obtenerPropiedadId(),
            $activo->obtenerUbicacionId(),
            InventarioActivo::ESTADO_DE_BAJA,
            $activo->obtenerFechaAdquisicion(),
            $activo->obtenerCostoAdquisicion(),
            $activo->obtenerMonedaCodigo(),
            $activo->obtenerGarantiaVenceEn(),
            $activo->obtenerNotas(),
            trim($motivoBaja),
            $actorId
        );

        $this->inventarioRepo->actualizarActivo($actualizado);

        return $this->inventarioRepo->obtenerActivoPorId($activoId);
    }

    private function validarDatosActivo(array $datos): void
    {
        if (empty($datos['codigo_placa']) || trim((string) $datos['codigo_placa']) === '') {
            throw new ValidacionExcepcion("La placa patrimonial o código único del activo es obligatoria.");
        }

        if (empty($datos['articulo_id']) || (int) $datos['articulo_id'] <= 0) {
            throw new ValidacionExcepcion("Debe asociar un artículo maestro válido.");
        }

        if (empty($datos['propiedad_id']) || (int) $datos['propiedad_id'] <= 0) {
            throw new ValidacionExcepcion("Debe seleccionar una propiedad física.");
        }

        if (empty($datos['ubicacion_id']) || (int) $datos['ubicacion_id'] <= 0) {
            throw new ValidacionExcepcion("Debe seleccionar una ubicación inicial.");
        }
    }

    // =========================================================================
    // 5. DOTACIONES ESTÁNDAR VS REALIDAD
    // =========================================================================

    public function configurarDotacionEstandar(array $datos): InventarioDotacionEstandar
    {
        $articuloId = (int) ($datos['articulo_id'] ?? 0);
        $articulo = $this->inventarioRepo->obtenerArticuloPorId($articuloId);
        if (!$articulo) {
            throw new ArticuloNoEncontradoExcepcion("El artículo especificado no existe.");
        }

        $tipoUnidadId = !empty($datos['tipo_unidad_id']) ? (int) $datos['tipo_unidad_id'] : null;
        $unidadId = !empty($datos['unidad_id']) ? (int) $datos['unidad_id'] : null;

        // Regla XOR estricta (D-078 #10)
        if (($tipoUnidadId !== null && $unidadId !== null) || ($tipoUnidadId === null && $unidadId === null)) {
            throw new ValidacionExcepcion("La dotación estándar debe asignarse a un tipo de unidad O a una unidad específica, no a ambos.");
        }

        $cant = $this->validarCantidad((string) ($datos['cantidad_estandar'] ?? '0'), $articulo);

        $dotacion = new InventarioDotacionEstandar(
            !empty($datos['id']) ? (int) $datos['id'] : null,
            $tipoUnidadId,
            $unidadId,
            $articuloId,
            $cant,
            !empty($datos['notas']) ? trim((string) $datos['notas']) : null
        );

        $id = $this->inventarioRepo->guardarDotacionEstandar($dotacion);

        return $this->inventarioRepo->obtenerDotacionEstandarPorId($id);
    }

    public function eliminarDotacionEstandar(int $id): bool
    {
        return $this->inventarioRepo->eliminarDotacionEstandar($id);
    }

    public function auditarDotacionUnidad(int $unidadId): array
    {
        $unidad = $this->unidadRepo->obtenerPorId($unidadId);
        if (!$unidad) {
            throw new ValidacionExcepcion("La unidad habitacional con ID [{$unidadId}] no existe.");
        }

        return $this->inventarioRepo->obtenerDotacionRealVsEstandarPorUnidad($unidadId);
    }

    // =========================================================================
    // 6. HELPERS Y UTILITARIOS PRIVADOS
    // =========================================================================

    private function obtenerArticuloCuantificable(int $articuloId): InventarioArticulo
    {
        $articulo = $this->inventarioRepo->obtenerArticuloPorId($articuloId);
        if (!$articulo) {
            throw new ArticuloNoEncontradoExcepcion("El artículo con ID [{$articuloId}] no existe.");
        }

        if ($articulo->esSerializable()) {
            throw new MovimientoInvalidoExcepcion(
                "El artículo [{$articulo->obtenerNombre()}] es un ACTIVO_SERIALIZABLE y no admite movimientos cuantitativos de stock. Debe gestionarse a través del módulo de activos individuales."
            );
        }

        return $articulo;
    }

    private function obtenerUbicacionActiva(int $ubicacionId): InventarioUbicacion
    {
        $ubi = $this->inventarioRepo->obtenerUbicacionPorId($ubicacionId);
        if (!$ubi) {
            throw new UbicacionNoEncontradaExcepcion("La ubicación con ID [{$ubicacionId}] no existe.");
        }

        if (!$ubi->estaActiva()) {
            throw new MovimientoInvalidoExcepcion("La ubicación [{$ubi->obtenerCodigo()}] se encuentra inactiva.");
        }

        return $ubi;
    }

    private function validarCantidad(string $cantidad, InventarioArticulo $articulo): string
    {
        $cant = trim($cantidad);
        if (!is_numeric($cant) || bccomp($cant, '0.0000', 4) <= 0) {
            throw new ValidacionExcepcion("La cantidad debe ser un número positivo mayor que cero.");
        }

        if (!$articulo->admiteDecimales()) {
            $decimales = bcsub($cant, (string) (int) (float) $cant, 4);
            if (bccomp($decimales, '0.0000', 4) !== 0) {
                throw new ValidacionExcepcion("La unidad de medida [{$articulo->obtenerUnidadMedidaSimbolo()}] no admite fracciones decimales.");
            }
        }

        return $this->formatearDecimal($cant, 4);
    }

    private function formatearDecimal(string $valor, int $escala): string
    {
        $val = trim($valor);
        if (!is_numeric($val)) {
            $val = '0';
        }
        return bcadd($val, '0', $escala);
    }

    private function generarCodigoMovimiento(): string
    {
        $fecha = (new DateTimeImmutable())->format('Ymd');
        do {
            $random = strtoupper(bin2hex(random_bytes(4)));
            $codigo = "MOV-{$fecha}-{$random}";
        } while ($this->inventarioRepo->obtenerMovimientoPorCodigo($codigo) !== null);

        return $codigo;
    }

    private function generarCorrelativoTraslado(): string
    {
        $fecha = (new DateTimeImmutable())->format('Ymd');
        $random = strtoupper(bin2hex(random_bytes(4)));
        return "TRS-{$fecha}-{$random}";
    }

    private function actualizarCostoMaterialesOrden(int $ordenId, string $nuevoCostoMateriales): void
    {
        // Recalcular costo_total = costo_mano_obra + nuevoCostoMateriales
        $stmt = $this->pdo->prepare('SELECT costo_mano_obra FROM mantenimiento_ordenes WHERE id = :id');
        $stmt->execute(['id' => $ordenId]);
        $manoObra = (string) $stmt->fetchColumn();

        $costoTotal = bcadd($manoObra, $nuevoCostoMateriales, 2);

        $stmtUp = $this->pdo->prepare(
            'UPDATE mantenimiento_ordenes SET costo_materiales = :mat, costo_total = :tot WHERE id = :id'
        );
        $stmtUp->execute([
            'id' => $ordenId,
            'mat' => $nuevoCostoMateriales,
            'tot' => $costoTotal,
        ]);
    }

    /**
     * Ejecuta una operación de movimiento dentro de una transacción con captura de deadlocks.
     */
    protected function ejecutarTransaccionMovimiento(callable $operacion): mixed
    {
        $enTransaccionPrevia = $this->pdo->inTransaction();
        if (!$enTransaccionPrevia) {
            $this->pdo->beginTransaction();
        }

        try {
            $resultado = $operacion();

            if (!$enTransaccionPrevia) {
                $this->pdo->commit();
            }

            return $resultado;
        } catch (PDOException $e) {
            if (!$enTransaccionPrevia && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            // Códigos 1205 (Lock wait timeout) y 1213 (Deadlock)
            $errorCodigo = (int) ($e->errorInfo[1] ?? 0);
            if ($errorCodigo === 1205 || $errorCodigo === 1213) {
                throw new ConflictoInventarioExcepcion(
                    "Conflicto de concurrencia al procesar existencias de inventario (código MySQL: {$errorCodigo}).",
                    $e
                );
            }

            throw $e;
        } catch (Throwable $t) {
            if (!$enTransaccionPrevia && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $t;
        }
    }

    // =========================================================================
    // 7. GETTERS DE CONSULTA RÁPIDA
    // =========================================================================

    public function obtenerRepositorio(): InventarioRepositorio
    {
        return $this->inventarioRepo;
    }
}
