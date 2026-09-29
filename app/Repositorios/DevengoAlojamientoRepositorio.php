<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Excepciones\DevengoDuplicadoExcepcion;
use CamargoPMS\Modelos\DevengoAlojamiento;
use PDO;
use PDOException;

/**
 * Repositorio de persistencia PDO para el Libro Diario de Devengos de Alojamiento (D-090).
 */
class DevengoAlojamientoRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    public function buscarPorId(int $id): ?DevengoAlojamiento
    {
        $sql = 'SELECT * FROM devengos_alojamiento WHERE id = :id LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->mapearFila($row) : null;
    }

    public function buscarPorCodigo(string $codigo): ?DevengoAlojamiento
    {
        $sql = 'SELECT * FROM devengos_alojamiento WHERE codigo = :codigo LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['codigo' => $codigo]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->mapearFila($row) : null;
    }

    /**
     * Busca el devengo activo para una estadía y fecha hotelera determinada.
     */
    public function buscarActivoPorEstadiaYFecha(int $estadiaId, string $fechaHotelera, bool $paraActualizar = false): ?DevengoAlojamiento
    {
        $sql = 'SELECT * FROM devengos_alojamiento
                WHERE estadia_id = :estadia_id AND fecha_hotelera = :fecha_hotelera AND estado = :estado
                ORDER BY secuencia DESC LIMIT 1';
        if ($paraActualizar) {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'estadia_id' => $estadiaId,
            'fecha_hotelera' => $fechaHotelera,
            'estado' => DevengoAlojamiento::ESTADO_DEVENGADO,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->mapearFila($row) : null;
    }

    public function obtenerUltimaSecuencia(int $estadiaId, string $fechaHotelera): int
    {
        $sql = 'SELECT MAX(secuencia) AS max_sec FROM devengos_alojamiento
                WHERE estadia_id = :estadia_id AND fecha_hotelera = :fecha_hotelera';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'estadia_id' => $estadiaId,
            'fecha_hotelera' => $fechaHotelera,
        ]);
        $val = $stmt->fetchColumn();

        return $val !== false && $val !== null ? (int) $val : 0;
    }

    public function crear(DevengoAlojamiento $devengo): int
    {
        $sql = 'INSERT INTO devengos_alojamiento (
            codigo, cierre_hotelero_id, estadia_id, reserva_id, reserva_unidad_id,
            unidad_id, propiedad_id, cargo_cuenta_id, fecha_hotelera,
            noche_indice, total_noches_estadia, secuencia,
            tarifa_base_noche, descuento_monto, impuesto_monto, importe_neto, importe_total,
            moneda_codigo, es_cortesia, origen_tarifa, metodo_distribucion, timezone_utilizada,
            tarifa_snapshot, estado, metodo_devengo, reverso_de_id, motivo_reversion,
            devengado_en, devengado_por_actor_id, revertido_en, revertido_por_actor_id
        ) VALUES (
            :codigo, :cierre_hotelero_id, :estadia_id, :reserva_id, :reserva_unidad_id,
            :unidad_id, :propiedad_id, :cargo_cuenta_id, :fecha_hotelera,
            :noche_indice, :total_noches_estadia, :secuencia,
            :tarifa_base_noche, :descuento_monto, :impuesto_monto, :importe_neto, :importe_total,
            :moneda_codigo, :es_cortesia, :origen_tarifa, :metodo_distribucion, :timezone_utilizada,
            :tarifa_snapshot, :estado, :metodo_devengo, :reverso_de_id, :motivo_reversion,
            :devengado_en, :devengado_por_actor_id, :revertido_en, :revertido_por_actor_id
        )';

        $stmt = $this->pdo->prepare($sql);
        try {
            $stmt->execute([
                'codigo' => $devengo->obtenerCodigo(),
                'cierre_hotelero_id' => $devengo->obtenerCierreHoteleroId(),
                'estadia_id' => $devengo->obtenerEstadiaId(),
                'reserva_id' => $devengo->obtenerReservaId(),
                'reserva_unidad_id' => $devengo->obtenerReservaUnidadId(),
                'unidad_id' => $devengo->obtenerUnidadId(),
                'propiedad_id' => $devengo->obtenerPropiedadId(),
                'cargo_cuenta_id' => $devengo->obtenerCargoCuentaId(),
                'fecha_hotelera' => $devengo->obtenerFechaHotelera(),
                'noche_indice' => $devengo->obtenerNocheIndice(),
                'total_noches_estadia' => $devengo->obtenerTotalNochesEstadia(),
                'secuencia' => $devengo->obtenerSecuencia(),
                'tarifa_base_noche' => $devengo->obtenerTarifaBaseNoche(),
                'descuento_monto' => $devengo->obtenerDescuentoMonto(),
                'impuesto_monto' => $devengo->obtenerImpuestoMonto(),
                'importe_neto' => $devengo->obtenerImporteNeto(),
                'importe_total' => $devengo->obtenerImporteTotal(),
                'moneda_codigo' => $devengo->obtenerMonedaCodigo(),
                'es_cortesia' => $devengo->esCortesia() ? 1 : 0,
                'origen_tarifa' => $devengo->obtenerOrigenTarifa(),
                'metodo_distribucion' => $devengo->obtenerMetodoDistribucion(),
                'timezone_utilizada' => $devengo->obtenerTimezoneUtilizada(),
                'tarifa_snapshot' => $devengo->obtenerTarifaSnapshot() ? json_encode($devengo->obtenerTarifaSnapshot(), JSON_UNESCAPED_UNICODE) : null,
                'estado' => $devengo->obtenerEstado(),
                'metodo_devengo' => $devengo->obtenerMetodoDevengo(),
                'reverso_de_id' => $devengo->obtenerReversoDeId(),
                'motivo_reversion' => $devengo->obtenerMotivoReversion(),
                'devengado_en' => $devengo->obtenerDevengadoEn(),
                'devengado_por_actor_id' => $devengo->obtenerDevengadoPorActorId(),
                'revertido_en' => $devengo->obtenerRevertidoEn(),
                'revertido_por_actor_id' => $devengo->obtenerRevertidoPorActorId(),
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000' || (isset($e->errorInfo[1]) && (int) $e->errorInfo[1] === 1062)) {
                throw new DevengoDuplicadoExcepcion(
                    "Ya existe un registro de devengo para la estadía {$devengo->obtenerEstadiaId()}, fecha {$devengo->obtenerFechaHotelera()} y secuencia {$devengo->obtenerSecuencia()}.",
                    $e
                );
            }
            throw $e;
        }

        $id = (int) $this->pdo->lastInsertId();
        $devengo->fijarId($id);

        return $id;
    }

    public function actualizarEstado(
        int $id,
        string $nuevoEstado,
        ?int $actorId,
        ?string $motivoReversion = null,
        ?string $revertidoEn = null
    ): bool {
        $sql = 'UPDATE devengos_alojamiento SET
            estado = :estado,
            motivo_reversion = :motivo_reversion,
            revertido_en = :revertido_en,
            revertido_por_actor_id = :revertido_por_actor_id
        WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'id' => $id,
            'estado' => $nuevoEstado,
            'motivo_reversion' => $motivoReversion,
            'revertido_en' => $revertidoEn,
            'revertido_por_actor_id' => $actorId,
        ]);
    }

    /**
     * @return array<DevengoAlojamiento>
     */
    public function listarPorEstadia(int $estadiaId): array
    {
        $sql = 'SELECT * FROM devengos_alojamiento
                WHERE estadia_id = :estadia_id
                ORDER BY fecha_hotelera ASC, secuencia ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['estadia_id' => $estadiaId]);

        $devengos = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $devengos[] = $this->mapearFila($row);
        }

        return $devengos;
    }

    /**
     * @return array<DevengoAlojamiento>
     */
    public function listarPorFechaYPropiedad(string $fechaHotelera, int $propiedadId, ?string $estado = 'DEVENGADO'): array
    {
        $sql = 'SELECT * FROM devengos_alojamiento
                WHERE propiedad_id = :propiedad_id AND fecha_hotelera = :fecha_hotelera';
        $params = [
            'propiedad_id' => $propiedadId,
            'fecha_hotelera' => $fechaHotelera,
        ];

        if ($estado !== null) {
            $sql .= ' AND estado = :estado';
            $params['estado'] = $estado;
        }

        $sql .= ' ORDER BY id ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $devengos = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $devengos[] = $this->mapearFila($row);
        }

        return $devengos;
    }

    public function sumarImporteNetoPorFechaYPropiedad(string $fechaHotelera, int $propiedadId): string
    {
        $sql = 'SELECT COALESCE(SUM(importe_neto), 0.00) AS total_neto
                FROM devengos_alojamiento
                WHERE propiedad_id = :propiedad_id
                  AND fecha_hotelera = :fecha_hotelera
                  AND estado = :estado';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'propiedad_id' => $propiedadId,
            'fecha_hotelera' => $fechaHotelera,
            'estado' => DevengoAlojamiento::ESTADO_DEVENGADO,
        ]);
        $val = $stmt->fetchColumn();

        return bcadd((string) $val, '0.00', 2);
    }

    public function contarHabitacionesVendidasPorFechaYPropiedad(string $fechaHotelera, int $propiedadId, bool $excluirCortesias = false): int
    {
        $sql = 'SELECT COUNT(DISTINCT unidad_id) AS total_vendidas
                FROM devengos_alojamiento
                WHERE propiedad_id = :propiedad_id
                  AND fecha_hotelera = :fecha_hotelera
                  AND estado = :estado';

        if ($excluirCortesias) {
            $sql .= ' AND es_cortesia = 0 AND importe_neto > 0';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'propiedad_id' => $propiedadId,
            'fecha_hotelera' => $fechaHotelera,
            'estado' => DevengoAlojamiento::ESTADO_DEVENGADO,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function generarSiguienteCodigo(): string
    {
        $prefijo = 'DEV-' . date('Ymd') . '-';
        $stmt = $this->pdo->prepare(
            'SELECT codigo FROM devengos_alojamiento WHERE codigo LIKE :prefijo ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['prefijo' => $prefijo . '%']);
        $ultimo = $stmt->fetchColumn();

        if ($ultimo && preg_match('/-(\d{4})$/', (string) $ultimo, $m)) {
            $siguiente = (int) $m[1] + 1;
        } else {
            $siguiente = 1;
        }

        return sprintf('%s%04d', $prefijo, $siguiente);
    }

    private function mapearFila(array $row): DevengoAlojamiento
    {
        $snapshot = null;
        if (!empty($row['tarifa_snapshot'])) {
            $decoded = json_decode($row['tarifa_snapshot'], true);
            if (is_array($decoded)) {
                $snapshot = $decoded;
            }
        }

        return new DevengoAlojamiento(
            (int) $row['id'],
            (string) $row['codigo'],
            $row['cierre_hotelero_id'] ? (int) $row['cierre_hotelero_id'] : null,
            (int) $row['estadia_id'],
            (int) $row['reserva_id'],
            (int) $row['reserva_unidad_id'],
            (int) $row['unidad_id'],
            (int) $row['propiedad_id'],
            $row['cargo_cuenta_id'] ? (int) $row['cargo_cuenta_id'] : null,
            (string) $row['fecha_hotelera'],
            (int) $row['noche_indice'],
            (int) $row['total_noches_estadia'],
            (int) $row['secuencia'],
            (string) $row['tarifa_base_noche'],
            (string) $row['descuento_monto'],
            (string) $row['impuesto_monto'],
            (string) $row['importe_neto'],
            (string) $row['importe_total'],
            (string) $row['moneda_codigo'],
            (bool) $row['es_cortesia'],
            (string) $row['origen_tarifa'],
            (string) $row['metodo_distribucion'],
            (string) $row['timezone_utilizada'],
            $snapshot,
            (string) $row['estado'],
            (string) $row['metodo_devengo'],
            $row['reverso_de_id'] ? (int) $row['reverso_de_id'] : null,
            $row['motivo_reversion'] ? (string) $row['motivo_reversion'] : null,
            (string) $row['devengado_en'],
            (int) $row['devengado_por_actor_id'],
            $row['revertido_en'] ? (string) $row['revertido_en'] : null,
            $row['revertido_por_actor_id'] ? (int) $row['revertido_por_actor_id'] : null,
            (string) $row['creado_en'],
            (string) $row['actualizado_en']
        );
    }
}
