<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\Gasto;
use CamargoPMS\Modelos\GastoAplicacionPago;
use CamargoPMS\Modelos\GastoCategoria;
use CamargoPMS\Modelos\GastoEvidencia;
use CamargoPMS\Modelos\GastoHistorialEstado;
use CamargoPMS\Modelos\GastoResumenDTO;
use PDO;

/**
 * Repositorio para la persistencia del Hecho Económico de Gasto y sus evidencias.
 * GASTOS-1 / D-086.
 */
class GastoRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    // =========================================================================
    // CATEGORÍAS DE GASTO
    // =========================================================================

    public function crearCategoria(GastoCategoria $cat): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO gasto_categorias (codigo, nombre, descripcion, requiere_comprobante_fiscal, activo, creado_en)
             VALUES (:codigo, :nombre, :descripcion, :requiere_comprobante_fiscal, :activo, NOW())'
        );
        $stmt->execute([
            'codigo' => $cat->obtenerCodigo(),
            'nombre' => $cat->obtenerNombre(),
            'descripcion' => $cat->obtenerDescripcion(),
            'requiere_comprobante_fiscal' => $cat->requiereComprobanteFiscal() ? 1 : 0,
            'activo' => $cat->estaActivo() ? 1 : 0,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function obtenerCategoriaPorId(int $id): ?GastoCategoria
    {
        $stmt = $this->pdo->prepare('SELECT * FROM gasto_categorias WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? GastoCategoria::desdeArreglo($fila) : null;
    }

    public function obtenerCategoriaPorCodigo(string $codigo): ?GastoCategoria
    {
        $stmt = $this->pdo->prepare('SELECT * FROM gasto_categorias WHERE codigo = :codigo LIMIT 1');
        $stmt->execute(['codigo' => $codigo]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? GastoCategoria::desdeArreglo($fila) : null;
    }

    /**
     * @return GastoCategoria[]
     */
    public function listarCategorias(bool $soloActivas = true): array
    {
        $sql = 'SELECT * FROM gasto_categorias';
        if ($soloActivas) {
            $sql .= ' WHERE activo = 1';
        }
        $sql .= ' ORDER BY nombre ASC';
        $stmt = $this->pdo->query($sql);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($f) => GastoCategoria::desdeArreglo($f), $filas);
    }

    // =========================================================================
    // GASTOS (HECHO ECONÓMICO)
    // =========================================================================

    public function generarSiguienteCodigoGasto(): string
    {
        $ym = date('Ym');
        $stmt = $this->pdo->prepare(
            "SELECT codigo FROM gastos WHERE codigo LIKE :prefix ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute(['prefix' => "GST-{$ym}-%"]);
        $ultimo = $stmt->fetchColumn();

        if ($ultimo && preg_match('/GST-\d{6}-(\d{4})/', (string) $ultimo, $m)) {
            $siguiente = (int) $m[1] + 1;
        } else {
            $siguiente = 1;
        }

        return sprintf('GST-%s-%04d', $ym, $siguiente);
    }

    public function crearGasto(Gasto $gasto): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO gastos (
                codigo, categoria_id, ambito, propiedad_id, unidad_id, proveedor_id,
                acreedor_nombre, acreedor_documento, descripcion_concepto, tipo_comprobante,
                comprobante_serie, comprobante_numero, fecha_emision, fecha_vencimiento,
                moneda_codigo, subtotal, impuestos, total, estado, creado_por_actor_id, creado_en
            ) VALUES (
                :codigo, :categoria_id, :ambito, :propiedad_id, :unidad_id, :proveedor_id,
                :acreedor_nombre, :acreedor_documento, :descripcion_concepto, :tipo_comprobante,
                :comprobante_serie, :comprobante_numero, :fecha_emision, :fecha_vencimiento,
                :moneda_codigo, :subtotal, :impuestos, :total, :estado, :creado_por_actor_id, NOW()
            )'
        );
        $stmt->execute([
            'codigo' => $gasto->obtenerCodigo(),
            'categoria_id' => $gasto->obtenerCategoriaId(),
            'ambito' => $gasto->obtenerAmbito(),
            'propiedad_id' => $gasto->obtenerPropiedadId(),
            'unidad_id' => $gasto->obtenerUnidadId(),
            'proveedor_id' => $gasto->obtenerProveedorId(),
            'acreedor_nombre' => $gasto->obtenerAcreedorNombre(),
            'acreedor_documento' => $gasto->obtenerAcreedorDocumento(),
            'descripcion_concepto' => $gasto->obtenerDescripcionConcepto(),
            'tipo_comprobante' => $gasto->obtenerTipoComprobante(),
            'comprobante_serie' => $this->obtanteSerieSafe($gasto),
            'comprobante_numero' => $this->obtanteNumeroSafe($gasto),
            'fecha_emision' => $gasto->obtenerFechaEmision(),
            'fecha_vencimiento' => $gasto->obtenerFechaVencimiento(),
            'moneda_codigo' => $gasto->obtenerMonedaCodigo(),
            'subtotal' => $gasto->obtenerSubtotal(),
            'impuestos' => $gasto->obtenerImpuestos(),
            'total' => $gasto->obtenerTotal(),
            'estado' => $gasto->obtenerEstado(),
            'creado_por_actor_id' => $gasto->obtenerCreadoPorActorId(),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    private function obtanteSerieSafe(Gasto $g): ?string
    {
        $v = $g->obtenerComprobanteSerie();
        return ($v !== null && trim($v) !== '') ? trim($v) : null;
    }

    private function obtanteNumeroSafe(Gasto $g): ?string
    {
        $v = $g->obtenerComprobanteNumero();
        return ($v !== null && trim($v) !== '') ? trim($v) : null;
    }

    public function obtenerGastoPorId(int $id, bool $bloquear = false): ?Gasto
    {
        $sql = 'SELECT * FROM gastos WHERE id = :id LIMIT 1';
        if ($bloquear) {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? Gasto::desdeArreglo($fila) : null;
    }

    public function obtenerGastoPorCodigo(string $codigo): ?Gasto
    {
        $stmt = $this->pdo->prepare('SELECT * FROM gastos WHERE codigo = :codigo LIMIT 1');
        $stmt->execute(['codigo' => $codigo]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? Gasto::desdeArreglo($fila) : null;
    }

    public function actualizarEstadoGasto(
        int $id,
        string $estado,
        ?string $motivoAnulacion = null,
        ?int $actorId = null,
        ?string $anuladoEn = null
    ): bool {
        $stmt = $this->pdo->prepare(
            'UPDATE gastos SET
                estado = :estado,
                motivo_anulacion = :motivo_anulacion,
                anulado_en = :anulado_en,
                anulado_por_actor_id = :anulado_por_actor_id,
                actualizado_en = NOW()
             WHERE id = :id'
        );
        return $stmt->execute([
            'id' => $id,
            'estado' => $estado,
            'motivo_anulacion' => $motivoAnulacion,
            'anulado_en' => $anuladoEn,
            'anulado_por_actor_id' => $actorId,
        ]);
    }

    /**
     * @return Gasto[]
     */
    public function listarGastos(array $filtros = []): array
    {
        $sql = 'SELECT * FROM gastos WHERE 1=1';
        $params = [];

        if (!empty($filtros['categoria_id'])) {
            $sql .= ' AND categoria_id = :categoria_id';
            $params['categoria_id'] = (int) $filtros['categoria_id'];
        }
        if (!empty($filtros['ambito'])) {
            $sql .= ' AND ambito = :ambito';
            $params['ambito'] = $filtros['ambito'];
        }
        if (!empty($filtros['propiedad_id'])) {
            $sql .= ' AND propiedad_id = :propiedad_id';
            $params['propiedad_id'] = (int) $filtros['propiedad_id'];
        }
        if (!empty($filtros['unidad_id'])) {
            $sql .= ' AND unidad_id = :unidad_id';
            $params['unidad_id'] = (int) $filtros['unidad_id'];
        }
        if (!empty($filtros['estado'])) {
            $sql .= ' AND estado = :estado';
            $params['estado'] = $filtros['estado'];
        }
        if (!empty($filtros['fecha_desde'])) {
            $sql .= ' AND fecha_emision >= :fecha_desde';
            $params['fecha_desde'] = $filtros['fecha_desde'];
        }
        if (!empty($filtros['fecha_hasta'])) {
            $sql .= ' AND fecha_emision <= :fecha_hasta';
            $params['fecha_hasta'] = $filtros['fecha_hasta'];
        }

        $sql .= ' ORDER BY fecha_emision DESC, id DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($f) => Gasto::desdeArreglo($f), $filas);
    }

    // =========================================================================
    // SALDO RECONSTRUCTIBLE Y APLICACIONES
    // =========================================================================

    public function calcularMontoAplicadoAcumulado(int $gastoId): string
    {
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(monto_aplicado), 0.00)
             FROM gasto_aplicaciones_pago
             WHERE gasto_id = :gasto_id AND estado = "ACTIVO"'
        );
        $stmt->execute(['gasto_id' => $gastoId]);
        $val = $stmt->fetchColumn();
        return bcadd((string) $val, '0.00', 2);
    }

    public function obtenerResumenGasto(int $gastoId): ?GastoResumenDTO
    {
        $gasto = $this->obtenerGastoPorId($gastoId);
        if ($gasto === null) {
            return null;
        }

        $categoriaNombre = null;
        if ($gasto->obtenerCategoriaId() > 0) {
            $cat = $this->obtenerCategoriaPorId($gasto->obtenerCategoriaId());
            $categoriaNombre = $cat?->obtenerNombre();
        }

        $propiedadNombre = null;
        if ($gasto->obtenerPropiedadId() !== null) {
            $stmtP = $this->pdo->prepare('SELECT nombre FROM propiedades WHERE id = :id LIMIT 1');
            $stmtP->execute(['id' => $gasto->obtenerPropiedadId()]);
            $propiedadNombre = $stmtP->fetchColumn() ?: null;
        }

        $unidadNumero = null;
        if ($gasto->obtenerUnidadId() !== null) {
            $stmtU = $this->pdo->prepare('SELECT codigo FROM unidades WHERE id = :id LIMIT 1');
            $stmtU->execute(['id' => $gasto->obtenerUnidadId()]);
            $unidadNumero = $stmtU->fetchColumn() ?: null;
        }

        $montoAplicado = $this->calcularMontoAplicadoAcumulado($gastoId);
        $totalGasto = $gasto->obtenerTotal();

        // Saldo = Total - Aplicado (con suelo en 0.00)
        $saldoDiff = bcsub($totalGasto, $montoAplicado, 2);
        $saldoPendiente = bccomp($saldoDiff, '0.00', 2) > 0 ? $saldoDiff : '0.00';

        if (bccomp($montoAplicado, '0.00', 2) === 0) {
            $situacion = GastoResumenDTO::SITUACION_PENDIENTE;
        } elseif (bccomp($saldoPendiente, '0.00', 2) === 0) {
            $situacion = GastoResumenDTO::SITUACION_PAGADO;
        } else {
            $situacion = GastoResumenDTO::SITUACION_PARCIAL;
        }

        $evidencias = $this->listarEvidenciasPorGasto($gastoId);
        $aplicaciones = $this->listarAplicacionesPorGasto($gastoId);

        return new GastoResumenDTO(
            $gasto,
            $categoriaNombre,
            $propiedadNombre,
            $unidadNumero,
            $montoAplicado,
            $saldoPendiente,
            $situacion,
            $evidencias,
            $aplicaciones
        );
    }

    // =========================================================================
    // EVIDENCIAS
    // =========================================================================

    public function crearEvidencia(GastoEvidencia $evidencia): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO gasto_evidencias (
                gasto_id, tipo_evidencia, nombre_original, ruta_archivo,
                mime_type, tamano_bytes, hash_sha256, subido_por_actor_id, creado_en
            ) VALUES (
                :gasto_id, :tipo_evidencia, :nombre_original, :ruta_archivo,
                :mime_type, :tamano_bytes, :hash_sha256, :subido_por_actor_id, NOW()
            )'
        );
        $stmt->execute([
            'gasto_id' => $evidencia->obtenerGastoId(),
            'tipo_evidencia' => $evidencia->obtenerTipoEvidencia(),
            'nombre_original' => $evidencia->obtenerNombreOriginal(),
            'ruta_archivo' => $evidencia->obtenerRutaArchivo(),
            'mime_type' => $evidencia->obtenerMimeType(),
            'tamano_bytes' => $evidencia->obtenerTamanoBytes(),
            'hash_sha256' => $evidencia->obtenerHashSha256(),
            'subido_por_actor_id' => $evidencia->obtenerSubidoPorActorId(),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @return GastoEvidencia[]
     */
    public function listarEvidenciasPorGasto(int $gastoId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM gasto_evidencias WHERE gasto_id = :gasto_id ORDER BY id ASC');
        $stmt->execute(['gasto_id' => $gastoId]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($f) => GastoEvidencia::desdeArreglo($f), $filas);
    }

    // =========================================================================
    // APLICACIONES DE PAGO POR GASTO
    // =========================================================================

    /**
     * @return GastoAplicacionPago[]
     */
    public function listarAplicacionesPorGasto(int $gastoId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM gasto_aplicaciones_pago WHERE gasto_id = :gasto_id ORDER BY id ASC'
        );
        $stmt->execute(['gasto_id' => $gastoId]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($f) => GastoAplicacionPago::desdeArreglo($f), $filas);
    }

    // =========================================================================
    // HISTORIAL APPEND-ONLY D-061
    // =========================================================================

    public function registrarHistorialEstado(GastoHistorialEstado $historial): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO gasto_historial_estados (
                gasto_id, estado_anterior, estado_nuevo, motivo, actor_id, correlation_id, creado_en
            ) VALUES (
                :gasto_id, :estado_anterior, :estado_nuevo, :motivo, :actor_id, :correlation_id, NOW()
            )'
        );
        $stmt->execute([
            'gasto_id' => $historial->obtenerGastoId(),
            'estado_anterior' => $historial->obtenerEstadoAnterior(),
            'estado_nuevo' => $historial->obtenerEstadoNuevo(),
            'motivo' => $historial->obtenerMotivo(),
            'actor_id' => $historial->obtenerActorId(),
            'correlation_id' => $historial->obtenerCorrelationId(),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @return GastoHistorialEstado[]
     */
    public function listarHistorialGasto(int $gastoId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM gasto_historial_estados WHERE gasto_id = :gasto_id ORDER BY id ASC'
        );
        $stmt->execute(['gasto_id' => $gastoId]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($f) => GastoHistorialEstado::desdeArreglo($f), $filas);
    }
}
