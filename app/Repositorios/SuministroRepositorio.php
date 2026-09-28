<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\Suministro;
use CamargoPMS\Modelos\SuministroTarifa;
use CamargoPMS\Modelos\SuministroMedidor;
use CamargoPMS\Modelos\SuministroLectura;
use CamargoPMS\Modelos\SuministroLiquidacion;
use CamargoPMS\Modelos\SuministroLiquidacionTramo;
use DateTimeImmutable;
use PDO;

/**
 * Repositorio de persistencia relacional para SUMINISTROS-1 (D-081).
 * Encapsula consultas parametrizadas sobre suministros, tarifas históricas,
 * medidores, lecturas inmutables y liquidaciones multitramo.
 */
class SuministroRepositorio
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function obtenerConexion(): PDO
    {
        return $this->pdo;
    }

    // -------------------------------------------------------------------------
    // GENERADOR DE FOLIOS CONCURRENCY-SAFE (FOR UPDATE)
    // -------------------------------------------------------------------------

    public function obtenerSiguienteFolio(string $tipoDocumento = 'SUMINISTRO_LIQUIDACION', string $prefijo = 'LIQ-SUM', ?DateTimeImmutable $fecha = null): string
    {
        $fechaOperacion = $fecha ?? new DateTimeImmutable('now');
        $periodoYm = $fechaOperacion->format('Ym');

        // 1. Asegurar existencia de fila con ON DUPLICATE KEY
        $stmtInit = $this->pdo->prepare(
            'INSERT INTO documento_secuencias (tipo_documento, periodo_ym, ultimo_correlativo)
             VALUES (:tipo, :periodo, 0)
             ON DUPLICATE KEY UPDATE ultimo_correlativo = ultimo_correlativo'
        );
        $stmtInit->execute([
            'tipo' => $tipoDocumento,
            'periodo' => $periodoYm,
        ]);

        // 2. Bloquear pesimistamente la fila con FOR UPDATE
        $stmtLock = $this->pdo->prepare(
            'SELECT ultimo_correlativo
             FROM documento_secuencias
             WHERE tipo_documento = :tipo AND periodo_ym = :periodo
             FOR UPDATE'
        );
        $stmtLock->execute([
            'tipo' => $tipoDocumento,
            'periodo' => $periodoYm,
        ]);

        $correlativoActual = (int) $stmtLock->fetchColumn();
        $nuevoCorrelativo = $correlativoActual + 1;

        // 3. Actualizar el correlativo
        $stmtUpdate = $this->pdo->prepare(
            'UPDATE documento_secuencias
             SET ultimo_correlativo = :nuevo
             WHERE tipo_documento = :tipo AND periodo_ym = :periodo'
        );
        $stmtUpdate->execute([
            'nuevo' => $nuevoCorrelativo,
            'tipo' => $tipoDocumento,
            'periodo' => $periodoYm,
        ]);

        return sprintf('%s-%s-%04d', $prefijo, $periodoYm, $nuevoCorrelativo);
    }

    // -------------------------------------------------------------------------
    // 1. SUMINISTROS (CATÁLOGO)
    // -------------------------------------------------------------------------

    public function crearSuministro(Suministro $suministro): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO suministros (
                codigo, nombre, modalidad, unidad_medida, descripcion, permite_rollover, estado
            ) VALUES (
                :codigo, :nombre, :modalidad, :unidad_medida, :descripcion, :permite_rollover, :estado
            )'
        );

        $stmt->execute([
            'codigo' => $suministro->obtenerCodigo(),
            'nombre' => $suministro->obtenerNombre(),
            'modalidad' => $suministro->obtenerModalidad(),
            'unidad_medida' => $suministro->obtenerUnidadMedida(),
            'descripcion' => $suministro->obtenerDescripcion(),
            'permite_rollover' => $suministro->permiteRollover() ? 1 : 0,
            'estado' => $suministro->obtenerEstado(),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function actualizarSuministro(Suministro $suministro): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE suministros
             SET nombre = :nombre,
                 modalidad = :modalidad,
                 unidad_medida = :unidad_medida,
                 descripcion = :descripcion,
                 permite_rollover = :permite_rollover,
                 estado = :estado
             WHERE id = :id'
        );

        return $stmt->execute([
            'id' => $suministro->obtenerId(),
            'nombre' => $suministro->obtenerNombre(),
            'modalidad' => $suministro->obtenerModalidad(),
            'unidad_medida' => $suministro->obtenerUnidadMedida(),
            'descripcion' => $suministro->obtenerDescripcion(),
            'permite_rollover' => $suministro->permiteRollover() ? 1 : 0,
            'estado' => $suministro->obtenerEstado(),
        ]);
    }

    public function buscarSuministroPorId(int $id): ?Suministro
    {
        $stmt = $this->pdo->prepare('SELECT * FROM suministros WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? Suministro::desdeArreglo($fila) : null;
    }

    public function buscarSuministroPorCodigo(string $codigo): ?Suministro
    {
        $stmt = $this->pdo->prepare('SELECT * FROM suministros WHERE codigo = :codigo');
        $stmt->execute(['codigo' => $codigo]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? Suministro::desdeArreglo($fila) : null;
    }

    /**
     * @return Suministro[]
     */
    public function listarSuministros(?string $estado = null): array
    {
        $sql = 'SELECT * FROM suministros';
        $params = [];

        if ($estado !== null) {
            $sql .= ' WHERE estado = :estado';
            $params['estado'] = $estado;
        }

        $sql .= ' ORDER BY nombre ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $resultado = [];
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resultado[] = Suministro::desdeArreglo($fila);
        }

        return $resultado;
    }

    // -------------------------------------------------------------------------
    // 2. TARIFAS HISTÓRICAS
    // -------------------------------------------------------------------------

    public function crearTarifa(SuministroTarifa $tarifa): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO suministro_tarifas (
                suministro_id, ambito_tipo, propiedad_id, unidad_id, valor_unitario,
                moneda_codigo, vigencia_desde, vigencia_hasta, estado, creado_por_actor_id
            ) VALUES (
                :suministro_id, :ambito_tipo, :propiedad_id, :unidad_id, :valor_unitario,
                :moneda_codigo, :vigencia_desde, :vigencia_hasta, :estado, :creado_por_actor_id
            )'
        );

        $stmt->execute([
            'suministro_id' => $tarifa->obtenerSuministroId(),
            'ambito_tipo' => $tarifa->obtenerAmbitoTipo(),
            'propiedad_id' => $tarifa->obtenerPropiedadId(),
            'unidad_id' => $tarifa->obtenerUnidadId(),
            'valor_unitario' => $tarifa->obtenerValorUnitario(),
            'moneda_codigo' => $tarifa->obtenerMonedaCodigo(),
            'vigencia_desde' => $tarifa->obtenerVigenciaDesde(),
            'vigencia_hasta' => $tarifa->obtenerVigenciaHasta(),
            'estado' => $tarifa->obtenerEstado(),
            'creado_por_actor_id' => $tarifa->obtenerCreadoPorActorId() ?: 1,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function buscarTarifaPorId(int $id): ?SuministroTarifa
    {
        $stmt = $this->pdo->prepare('SELECT * FROM suministro_tarifas WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? SuministroTarifa::desdeArreglo($fila) : null;
    }

    /**
     * @return SuministroTarifa[]
     */
    public function listarTarifasPorSuministro(int $suministroId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM suministro_tarifas
             WHERE suministro_id = :suministro_id
             ORDER BY vigencia_desde DESC, id DESC'
        );
        $stmt->execute(['suministro_id' => $suministroId]);

        $resultado = [];
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resultado[] = SuministroTarifa::desdeArreglo($fila);
        }

        return $resultado;
    }

    /**
     * Bloquea pesimistamente las tarifas del mismo ámbito para garantizar anti-solapamiento.
     *
     * @return SuministroTarifa[]
     */
    public function bloquearTarifasAmbitoParaValidacion(int $suministroId, string $ambito, ?int $propiedadId, ?int $unidadId): array
    {
        $sql = 'SELECT * FROM suministro_tarifas
                WHERE suministro_id = :suministro_id
                  AND ambito_tipo = :ambito
                  AND estado = :estado';
        $params = [
            'suministro_id' => $suministroId,
            'ambito' => $ambito,
            'estado' => SuministroTarifa::ESTADO_ACTIVO,
        ];

        if ($propiedadId !== null) {
            $sql .= ' AND propiedad_id = :propiedad_id';
            $params['propiedad_id'] = $propiedadId;
        } else {
            $sql .= ' AND propiedad_id IS NULL';
        }

        if ($unidadId !== null) {
            $sql .= ' AND unidad_id = :unidad_id';
            $params['unidad_id'] = $unidadId;
        } else {
            $sql .= ' AND unidad_id IS NULL';
        }

        $sql .= ' FOR UPDATE';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $resultado = [];
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resultado[] = SuministroTarifa::desdeArreglo($fila);
        }

        return $resultado;
    }

    /**
     * Busca tarifas vigentes en una fecha específica para un suministro y jerarquía (UNIDAD > PROPIEDAD > GLOBAL).
     *
     * @return SuministroTarifa[]
     */
    public function buscarTarifasCandidatasEnFecha(int $suministroId, string $fecha, ?int $propiedadId = null, ?int $unidadId = null): array
    {
        $sql = 'SELECT * FROM suministro_tarifas
                WHERE suministro_id = :suministro_id
                  AND estado = :estado
                  AND vigencia_desde <= :fecha_desde
                  AND (vigencia_hasta IS NULL OR vigencia_hasta >= :fecha_hasta)
                  AND (
                      (ambito_tipo = "UNIDAD" AND unidad_id = :unidad_id) OR
                      (ambito_tipo = "PROPIEDAD" AND propiedad_id = :propiedad_id) OR
                      (ambito_tipo = "GLOBAL" AND propiedad_id IS NULL AND unidad_id IS NULL)
                  )
                ORDER BY
                    CASE ambito_tipo
                        WHEN "UNIDAD" THEN 1
                        WHEN "PROPIEDAD" THEN 2
                        WHEN "GLOBAL" THEN 3
                        ELSE 4
                    END ASC,
                    vigencia_desde DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'suministro_id' => $suministroId,
            'estado' => SuministroTarifa::ESTADO_ACTIVO,
            'fecha_desde' => $fecha,
            'fecha_hasta' => $fecha,
            'unidad_id' => $unidadId,
            'propiedad_id' => $propiedadId,
        ]);

        $resultado = [];
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resultado[] = SuministroTarifa::desdeArreglo($fila);
        }

        return $resultado;
    }

    /**
     * Busca todas las tarifas que cubran o intersecten un intervalo de fechas para un suministro y ámbito.
     *
     * @return SuministroTarifa[]
     */
    public function buscarTarifasEnRango(int $suministroId, string $fechaDesde, string $fechaHasta, ?int $propiedadId = null, ?int $unidadId = null): array
    {
        $sql = 'SELECT * FROM suministro_tarifas
                WHERE suministro_id = :suministro_id
                  AND estado = :estado
                  AND vigencia_desde <= :fecha_hasta
                  AND (vigencia_hasta IS NULL OR vigencia_hasta >= :fecha_desde)
                  AND (
                      (ambito_tipo = "UNIDAD" AND unidad_id = :unidad_id) OR
                      (ambito_tipo = "PROPIEDAD" AND propiedad_id = :propiedad_id) OR
                      (ambito_tipo = "GLOBAL" AND propiedad_id IS NULL AND unidad_id IS NULL)
                  )
                ORDER BY
                    CASE ambito_tipo
                        WHEN "UNIDAD" THEN 1
                        WHEN "PROPIEDAD" THEN 2
                        WHEN "GLOBAL" THEN 3
                        ELSE 4
                    END ASC,
                    vigencia_desde ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'suministro_id' => $suministroId,
            'estado' => SuministroTarifa::ESTADO_ACTIVO,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'unidad_id' => $unidadId,
            'propiedad_id' => $propiedadId,
        ]);

        $resultado = [];
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resultado[] = SuministroTarifa::desdeArreglo($fila);
        }

        return $resultado;
    }

    // -------------------------------------------------------------------------
    // 3. MEDIDORES FÍSICOS
    // -------------------------------------------------------------------------

    public function crearMedidor(SuministroMedidor $medidor): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO suministro_medidores (
                suministro_id, propiedad_id, unidad_id, codigo,
                marca, modelo, fecha_instalacion, lectura_inicial, capacidad_maxima,
                fecha_retiro, estado, observaciones, creado_por_actor_id
            ) VALUES (
                :suministro_id, :propiedad_id, :unidad_id, :codigo,
                :marca, :modelo, :fecha_instalacion, :lectura_inicial, :capacidad_maxima,
                :fecha_retiro, :estado, :observaciones, :creado_por_actor_id
            )'
        );

        $stmt->execute([
            'suministro_id' => $medidor->obtenerSuministroId(),
            'propiedad_id' => $medidor->obtenerPropiedadId(),
            'unidad_id' => $medidor->obtenerUnidadId(),
            'codigo' => $medidor->obtenerNumeroSerie(),
            'marca' => $medidor->obtenerMarca(),
            'modelo' => $medidor->obtenerModelo(),
            'fecha_instalacion' => $medidor->obtenerFechaInstalacion(),
            'lectura_inicial' => $medidor->obtenerLecturaInicial(),
            'capacidad_maxima' => $medidor->obtenerLecturaMaxima(),
            'fecha_retiro' => $medidor->obtenerFechaRetiro(),
            'estado' => $medidor->obtenerEstado(),
            'observaciones' => $medidor->obtenerNotas(),
            'creado_por_actor_id' => $medidor->obtenerCreadoPorActorId() ?: 1,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function actualizarMedidor(SuministroMedidor $medidor): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE suministro_medidores
             SET codigo = :codigo,
                 marca = :marca,
                 modelo = :modelo,
                 fecha_instalacion = :fecha_instalacion,
                 lectura_inicial = :lectura_inicial,
                 capacidad_maxima = :capacidad_maxima,
                 fecha_retiro = :fecha_retiro,
                 estado = :estado,
                 observaciones = :observaciones
             WHERE id = :id'
        );

        return $stmt->execute([
            'id' => $medidor->obtenerId(),
            'codigo' => $medidor->obtenerNumeroSerie(),
            'marca' => $medidor->obtenerMarca(),
            'modelo' => $medidor->obtenerModelo(),
            'fecha_instalacion' => $medidor->obtenerFechaInstalacion(),
            'lectura_inicial' => $medidor->obtenerLecturaInicial(),
            'capacidad_maxima' => $medidor->obtenerLecturaMaxima(),
            'fecha_retiro' => $medidor->obtenerFechaRetiro(),
            'estado' => $medidor->obtenerEstado(),
            'observaciones' => $medidor->obtenerNotas(),
        ]);
    }

    public function buscarMedidorPorId(int $id): ?SuministroMedidor
    {
        $stmt = $this->pdo->prepare('SELECT * FROM suministro_medidores WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? SuministroMedidor::desdeArreglo($fila) : null;
    }

    public function buscarMedidorPorSerie(string $serie): ?SuministroMedidor
    {
        $stmt = $this->pdo->prepare('SELECT * FROM suministro_medidores WHERE codigo = :serie');
        $stmt->execute(['serie' => $serie]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? SuministroMedidor::desdeArreglo($fila) : null;
    }

    public function buscarMedidorActivoPorUnidad(int $suministroId, int $unidadId): ?SuministroMedidor
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM suministro_medidores
             WHERE suministro_id = :suministro_id
               AND unidad_id = :unidad_id
               AND estado = :estado'
        );
        $stmt->execute([
            'suministro_id' => $suministroId,
            'unidad_id' => $unidadId,
            'estado' => SuministroMedidor::ESTADO_ACTIVO,
        ]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? SuministroMedidor::desdeArreglo($fila) : null;
    }

    /**
     * @return SuministroMedidor[]
     */
    public function listarMedidores(?int $suministroId = null, ?int $propiedadId = null, ?int $unidadId = null): array
    {
        $sql = 'SELECT sm.*, s.nombre AS suministro_nombre, u.codigo AS unidad_numero, p.nombre AS propiedad_nombre
                FROM suministro_medidores sm
                JOIN suministros s ON s.id = sm.suministro_id
                JOIN propiedades p ON p.id = sm.propiedad_id
                LEFT JOIN unidades u ON u.id = sm.unidad_id
                WHERE 1=1';
        $params = [];

        if ($suministroId !== null) {
            $sql .= ' AND sm.suministro_id = :suministro_id';
            $params['suministro_id'] = $suministroId;
        }

        if ($propiedadId !== null) {
            $sql .= ' AND sm.propiedad_id = :propiedad_id';
            $params['propiedad_id'] = $propiedadId;
        }

        if ($unidadId !== null) {
            $sql .= ' AND sm.unidad_id = :unidad_id';
            $params['unidad_id'] = $unidadId;
        }

        $sql .= ' ORDER BY sm.estado ASC, sm.id DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $resultado = [];
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resultado[] = SuministroMedidor::desdeArreglo($fila);
        }

        return $resultado;
    }

    // -------------------------------------------------------------------------
    // 4. LECTURAS FÍSICAS (APPEND-ONLY)
    // -------------------------------------------------------------------------

    public function registrarLectura(SuministroLectura $lectura): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO suministro_lecturas (
                medidor_id, arrendamiento_id, tipo_evento, fecha_lectura,
                valor_lectura, lectura_referencia_id, motivo, estado,
                registrado_por_actor_id
            ) VALUES (
                :medidor_id, :arrendamiento_id, :tipo_evento, :fecha_lectura,
                :valor_lectura, :lectura_referencia_id, :motivo, :estado,
                :registrado_por_actor_id
            )'
        );

        $stmt->execute([
            'medidor_id' => $lectura->obtenerMedidorId(),
            'arrendamiento_id' => $lectura->obtenerArrendamientoId(),
            'tipo_evento' => $lectura->obtenerTipoEvento(),
            'fecha_lectura' => $lectura->obtenerFechaLectura(),
            'valor_lectura' => $lectura->obtenerValorLectura(),
            'lectura_referencia_id' => $lectura->obtenerLecturaReferenciaId(),
            'motivo' => $lectura->obtenerMotivo(),
            'estado' => $lectura->obtenerEstado(),
            'registrado_por_actor_id' => $lectura->obtenerRegistradoPorActorId(),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function buscarLecturaPorId(int $id): ?SuministroLectura
    {
        $stmt = $this->pdo->prepare('SELECT * FROM suministro_lecturas WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? SuministroLectura::desdeArreglo($fila) : null;
    }

    /**
     * @return SuministroLectura[]
     */
    public function buscarLecturasPorMedidor(int $medidorId, bool $soloVigentes = true): array
    {
        $sql = 'SELECT * FROM suministro_lecturas WHERE medidor_id = :medidor_id';
        $params = ['medidor_id' => $medidorId];

        if ($soloVigentes) {
            $sql .= ' AND estado = :estado';
            $params['estado'] = SuministroLectura::ESTADO_VIGENTE;
        }

        $sql .= ' ORDER BY fecha_lectura ASC, id ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $resultado = [];
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resultado[] = SuministroLectura::desdeArreglo($fila);
        }

        return $resultado;
    }

    public function buscarUltimaLecturaVigente(int $medidorId, ?string $hastaFecha = null): ?SuministroLectura
    {
        $sql = 'SELECT * FROM suministro_lecturas
                WHERE medidor_id = :medidor_id
                  AND estado = :estado';
        $params = [
            'medidor_id' => $medidorId,
            'estado' => SuministroLectura::ESTADO_VIGENTE,
        ];

        if ($hastaFecha !== null) {
            $sql .= ' AND fecha_lectura <= :hasta_fecha';
            $params['hasta_fecha'] = $hastaFecha;
        }

        $sql .= ' ORDER BY fecha_lectura DESC, id DESC LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? SuministroLectura::desdeArreglo($fila) : null;
    }

    public function buscarLecturaVigenteEnFecha(int $medidorId, string $fechaLectura): ?SuministroLectura
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM suministro_lecturas
             WHERE medidor_id = :medidor_id
               AND fecha_lectura = :fecha
               AND estado = :estado
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([
            'medidor_id' => $medidorId,
            'fecha' => $fechaLectura,
            'estado' => SuministroLectura::ESTADO_VIGENTE,
        ]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? SuministroLectura::desdeArreglo($fila) : null;
    }

    /**
     * @return SuministroLectura[]
     */
    public function buscarLecturasEnRango(int $medidorId, string $fechaDesde, string $fechaHasta, bool $soloVigentes = true): array
    {
        $sql = 'SELECT * FROM suministro_lecturas
                WHERE medidor_id = :medidor_id
                  AND fecha_lectura >= :fecha_desde
                  AND fecha_lectura <= :fecha_hasta';
        $params = [
            'medidor_id' => $medidorId,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
        ];

        if ($soloVigentes) {
            $sql .= ' AND estado = :estado';
            $params['estado'] = SuministroLectura::ESTADO_VIGENTE;
        }

        $sql .= ' ORDER BY fecha_lectura ASC, id ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $resultado = [];
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resultado[] = SuministroLectura::desdeArreglo($fila);
        }

        return $resultado;
    }

    public function actualizarEstadoLectura(int $lecturaId, string $nuevoEstado): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE suministro_lecturas
             SET estado = :nuevo_estado
             WHERE id = :id'
        );

        return $stmt->execute([
            'id' => $lecturaId,
            'nuevo_estado' => $nuevoEstado,
        ]);
    }

    // -------------------------------------------------------------------------
    // 5. LIQUIDACIONES Y TRAMOS
    // -------------------------------------------------------------------------

    public function crearLiquidacion(SuministroLiquidacion $liq): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO suministro_liquidaciones (
                folio, suministro_id, modalidad, propiedad_id, unidad_id,
                arrendamiento_id, cuenta_folio_id, cargo_cuenta_id, periodo_anio,
                periodo_mes, periodo_desde, periodo_hasta, fecha_emision,
                fecha_vencimiento, cantidad_total, subtotal, impuesto_monto,
                total, moneda_codigo, revision, liquidacion_previa_id, estado,
                creado_por_actor_id
            ) VALUES (
                :folio, :suministro_id, :modalidad, :propiedad_id, :unidad_id,
                :arrendamiento_id, :cuenta_folio_id, :cargo_cuenta_id, :periodo_anio,
                :periodo_mes, :periodo_desde, :periodo_hasta, :fecha_emision,
                :fecha_vencimiento, :cantidad_total, :subtotal, :impuesto_monto,
                :total, :moneda_codigo, :revision, :liquidacion_previa_id, :estado,
                :creado_por_actor_id
            )'
        );

        $stmt->execute([
            'folio' => $liq->obtenerFolio(),
            'suministro_id' => $liq->obtenerSuministroId(),
            'modalidad' => $liq->obtenerModalidad(),
            'propiedad_id' => $liq->obtenerPropiedadId(),
            'unidad_id' => $liq->obtenerUnidadId(),
            'arrendamiento_id' => $liq->obtenerArrendamientoId(),
            'cuenta_folio_id' => $liq->obtenerCuentaFolioId(),
            'cargo_cuenta_id' => $liq->obtenerCargoCuentaId(),
            'periodo_anio' => $liq->obtenerPeriodoAnio(),
            'periodo_mes' => $liq->obtenerPeriodoMes(),
            'periodo_desde' => $liq->obtenerPeriodoDesde(),
            'periodo_hasta' => $liq->obtenerPeriodoHasta(),
            'fecha_emision' => $liq->obtenerFechaEmision(),
            'fecha_vencimiento' => $liq->obtenerFechaVencimiento(),
            'cantidad_total' => $liq->obtenerCantidadTotal(),
            'subtotal' => $liq->obtenerSubtotal(),
            'impuesto_monto' => $liq->obtenerImpuestoMonto(),
            'total' => $liq->obtenerTotal(),
            'moneda_codigo' => $liq->obtenerMonedaCodigo(),
            'revision' => $liq->obtenerRevision(),
            'liquidacion_previa_id' => $liq->obtenerLiquidacionPreviaId(),
            'estado' => $liq->obtenerEstado(),
            'creado_por_actor_id' => $liq->obtenerCreadoPorActorId(),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function crearTramo(SuministroLiquidacionTramo $tramo): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO suministro_liquidacion_tramos (
                liquidacion_id, numero_tramo, medidor_id, lectura_anterior_id,
                lectura_actual_id, lectura_anterior_valor, lectura_actual_valor,
                cantidad, tarifa_id, tarifa_valor, subtotal, impuesto_monto,
                total, fecha_desde, fecha_hasta
            ) VALUES (
                :liquidacion_id, :numero_tramo, :medidor_id, :lectura_anterior_id,
                :lectura_actual_id, :lectura_anterior_valor, :lectura_actual_valor,
                :cantidad, :tarifa_id, :tarifa_valor, :subtotal, :impuesto_monto,
                :total, :fecha_desde, :fecha_hasta
            )'
        );

        $stmt->execute([
            'liquidacion_id' => $tramo->obtenerLiquidacionId(),
            'numero_tramo' => $tramo->obtenerNumeroTramo(),
            'medidor_id' => $tramo->obtenerMedidorId(),
            'lectura_anterior_id' => $tramo->obtenerLecturaAnteriorId(),
            'lectura_actual_id' => $tramo->obtenerLecturaActualId(),
            'lectura_anterior_valor' => $tramo->obtenerLecturaAnteriorValor(),
            'lectura_actual_valor' => $tramo->obtenerLecturaActualValor(),
            'cantidad' => $tramo->obtenerCantidad(),
            'tarifa_id' => $tramo->obtenerTarifaId(),
            'tarifa_valor' => $tramo->obtenerTarifaValor(),
            'subtotal' => $tramo->obtenerSubtotal(),
            'impuesto_monto' => $tramo->obtenerImpuestoMonto(),
            'total' => $tramo->obtenerTotal(),
            'fecha_desde' => $tramo->obtenerFechaDesde(),
            'fecha_hasta' => $tramo->obtenerFechaHasta(),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function buscarLiquidacionPorId(int $id): ?SuministroLiquidacion
    {
        $stmt = $this->pdo->prepare('SELECT * FROM suministro_liquidaciones WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            return null;
        }

        $fila['tramos'] = $this->obtenerTramosPorLiquidacion($id);

        return SuministroLiquidacion::desdeArreglo($fila);
    }

    public function buscarLiquidacionPorFolio(string $folio): ?SuministroLiquidacion
    {
        $stmt = $this->pdo->prepare('SELECT * FROM suministro_liquidaciones WHERE folio = :folio');
        $stmt->execute(['folio' => $folio]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            return null;
        }

        $fila['tramos'] = $this->obtenerTramosPorLiquidacion((int) $fila['id']);

        return SuministroLiquidacion::desdeArreglo($fila);
    }

    public function buscarLiquidacionActivaPorPeriodo(int $arrendamientoId, int $suministroId, string $periodoDesde, string $periodoHasta): ?SuministroLiquidacion
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM suministro_liquidaciones
             WHERE arrendamiento_id = :arrendamiento_id
               AND suministro_id = :suministro_id
               AND periodo_desde = :periodo_desde
               AND periodo_hasta = :periodo_hasta
               AND estado = :estado'
        );
        $stmt->execute([
            'arrendamiento_id' => $arrendamientoId,
            'suministro_id' => $suministroId,
            'periodo_desde' => $periodoDesde,
            'periodo_hasta' => $periodoHasta,
            'estado' => SuministroLiquidacion::ESTADO_DEVENGADO,
        ]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            return null;
        }

        $fila['tramos'] = $this->obtenerTramosPorLiquidacion((int) $fila['id']);

        return SuministroLiquidacion::desdeArreglo($fila);
    }

    /**
     * @return SuministroLiquidacionTramo[]
     */
    public function obtenerTramosPorLiquidacion(int $liquidacionId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM suministro_liquidacion_tramos
             WHERE liquidacion_id = :liquidacion_id
             ORDER BY numero_tramo ASC'
        );
        $stmt->execute(['liquidacion_id' => $liquidacionId]);

        $resultado = [];
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resultado[] = SuministroLiquidacionTramo::desdeArreglo($fila);
        }

        return $resultado;
    }

    /**
     * @return array
     */
    public function listarLiquidaciones(?int $arrendamientoId = null, ?int $suministroId = null, ?string $estado = null): array
    {
        $sql = 'SELECT sl.*, s.nombre AS suministro_nombre, s.codigo AS suministro_codigo,
                       u.codigo AS unidad_numero, p.nombre AS propiedad_nombre,
                       a.codigo AS arrendamiento_codigo, cc.total AS cargo_monto, (cc.total - cc.monto_aplicado_acumulado) AS cargo_saldo
                FROM suministro_liquidaciones sl
                JOIN suministros s ON s.id = sl.suministro_id
                JOIN unidades u ON u.id = sl.unidad_id
                JOIN propiedades p ON p.id = sl.propiedad_id
                JOIN arrendamientos a ON a.id = sl.arrendamiento_id
                JOIN cargos_cuenta cc ON cc.id = sl.cargo_cuenta_id
                WHERE 1=1';
        $params = [];

        if ($arrendamientoId !== null) {
            $sql .= ' AND sl.arrendamiento_id = :arrendamiento_id';
            $params['arrendamiento_id'] = $arrendamientoId;
        }

        if ($suministroId !== null) {
            $sql .= ' AND sl.suministro_id = :suministro_id';
            $params['suministro_id'] = $suministroId;
        }

        if ($estado !== null) {
            $sql .= ' AND sl.estado = :estado';
            $params['estado'] = $estado;
        }

        $sql .= ' ORDER BY sl.id DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function anularLiquidacion(int $liquidacionId, string $motivo, int $anuladoPorActorId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE suministro_liquidaciones
             SET estado = :estado,
                 motivo_anulacion = :motivo,
                 anulado_en = NOW(),
                 anulado_por_actor_id = :actor_id
             WHERE id = :id'
        );

        return $stmt->execute([
            'id' => $liquidacionId,
            'estado' => SuministroLiquidacion::ESTADO_ANULADO,
            'motivo' => $motivo,
            'actor_id' => $anuladoPorActorId,
        ]);
    }
}
