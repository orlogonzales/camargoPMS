<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\CierreHotelero;
use PDO;

/**
 * Repositorio de persistencia PDO para Cierres Hoteleros y snapshots de inventario vendible (D-090).
 */
class CierreHoteleroRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    public function buscarPorId(int $id): ?CierreHotelero
    {
        $sql = 'SELECT * FROM cierres_hoteleros WHERE id = :id LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->mapearFila($row) : null;
    }

    public function buscarPorPropiedadYFecha(int $propiedadId, string $fechaHotelera, bool $paraActualizar = false): ?CierreHotelero
    {
        $sql = 'SELECT * FROM cierres_hoteleros WHERE propiedad_id = :propiedad_id AND fecha_hotelera = :fecha_hotelera LIMIT 1';
        if ($paraActualizar) {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'propiedad_id' => $propiedadId,
            'fecha_hotelera' => $fechaHotelera,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->mapearFila($row) : null;
    }

    public function crear(CierreHotelero $cierre): int
    {
        $sql = 'INSERT INTO cierres_hoteleros (
            propiedad_id, fecha_hotelera, timezone_utilizada, estado,
            total_estadias_procesadas, total_noches_devengadas,
            ingreso_alojamiento_neto, ingreso_alojamiento_impuestos, ingreso_alojamiento_total,
            unidades_totales, unidades_ooo, unidades_vendibles,
            habitaciones_vendidas, habitaciones_cortesia,
            ocupacion_porcentaje, adr, revpar,
            iniciado_en, cerrado_en, ejecutado_por_actor_id,
            observaciones, error_mensaje
        ) VALUES (
            :propiedad_id, :fecha_hotelera, :timezone_utilizada, :estado,
            :total_estadias_procesadas, :total_noches_devengadas,
            :ingreso_alojamiento_neto, :ingreso_alojamiento_impuestos, :ingreso_alojamiento_total,
            :unidades_totales, :unidades_ooo, :unidades_vendibles,
            :habitaciones_vendidas, :habitaciones_cortesia,
            :ocupacion_porcentaje, :adr, :revpar,
            :iniciado_en, :cerrado_en, :ejecutado_por_actor_id,
            :observaciones, :error_mensaje
        )';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'propiedad_id' => $cierre->obtenerPropiedadId(),
            'fecha_hotelera' => $cierre->obtenerFechaHotelera(),
            'timezone_utilizada' => $cierre->obtenerTimezoneUtilizada(),
            'estado' => $cierre->obtenerEstado(),
            'total_estadias_procesadas' => $cierre->obtenerTotalEstadiasProcesadas(),
            'total_noches_devengadas' => $cierre->obtenerTotalNochesDevengadas(),
            'ingreso_alojamiento_neto' => $cierre->obtenerIngresoAlojamientoNeto(),
            'ingreso_alojamiento_impuestos' => $cierre->obtenerIngresoAlojamientoImpuestos(),
            'ingreso_alojamiento_total' => $cierre->obtenerIngresoAlojamientoTotal(),
            'unidades_totales' => $cierre->obtenerUnidadesTotales(),
            'unidades_ooo' => $cierre->obtenerUnidadesOoo(),
            'unidades_vendibles' => $cierre->obtenerUnidadesVendibles(),
            'habitaciones_vendidas' => $cierre->obtenerHabitacionesVendidas(),
            'habitaciones_cortesia' => $cierre->obtenerHabitacionesCortesia(),
            'ocupacion_porcentaje' => $cierre->obtenerOcupacionPorcentaje(),
            'adr' => $cierre->obtenerAdr(),
            'revpar' => $cierre->obtenerRevpar(),
            'iniciado_en' => $cierre->obtenerIniciadoEn(),
            'cerrado_en' => $cierre->obtenerCerradoEn(),
            'ejecutado_por_actor_id' => $cierre->obtenerEjecutadoPorActorId(),
            'observaciones' => $cierre->obtenerObservaciones(),
            'error_mensaje' => $cierre->obtenerErrorMensaje(),
        ]);

        $id = (int) $this->pdo->lastInsertId();
        $cierre->fijarId($id);

        return $id;
    }

    public function actualizar(CierreHotelero $cierre): bool
    {
        $sql = 'UPDATE cierres_hoteleros SET
            estado = :estado,
            total_estadias_procesadas = :total_estadias_procesadas,
            total_noches_devengadas = :total_noches_devengadas,
            ingreso_alojamiento_neto = :ingreso_alojamiento_neto,
            ingreso_alojamiento_impuestos = :ingreso_alojamiento_impuestos,
            ingreso_alojamiento_total = :ingreso_alojamiento_total,
            unidades_totales = :unidades_totales,
            unidades_ooo = :unidades_ooo,
            unidades_vendibles = :unidades_vendibles,
            habitaciones_vendidas = :habitaciones_vendidas,
            habitaciones_cortesia = :habitaciones_cortesia,
            ocupacion_porcentaje = :ocupacion_porcentaje,
            adr = :adr,
            revpar = :revpar,
            cerrado_en = :cerrado_en,
            observaciones = :observaciones,
            error_mensaje = :error_mensaje
        WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'id' => $cierre->obtenerId(),
            'estado' => $cierre->obtenerEstado(),
            'total_estadias_procesadas' => $cierre->obtenerTotalEstadiasProcesadas(),
            'total_noches_devengadas' => $cierre->obtenerTotalNochesDevengadas(),
            'ingreso_alojamiento_neto' => $cierre->obtenerIngresoAlojamientoNeto(),
            'ingreso_alojamiento_impuestos' => $cierre->obtenerIngresoAlojamientoImpuestos(),
            'ingreso_alojamiento_total' => $cierre->obtenerIngresoAlojamientoTotal(),
            'unidades_totales' => $cierre->obtenerUnidadesTotales(),
            'unidades_ooo' => $cierre->obtenerUnidadesOoo(),
            'unidades_vendibles' => $cierre->obtenerUnidadesVendibles(),
            'habitaciones_vendidas' => $cierre->obtenerHabitacionesVendidas(),
            'habitaciones_cortesia' => $cierre->obtenerHabitacionesCortesia(),
            'ocupacion_porcentaje' => $cierre->obtenerOcupacionPorcentaje(),
            'adr' => $cierre->obtenerAdr(),
            'revpar' => $cierre->obtenerRevpar(),
            'cerrado_en' => $cierre->obtenerCerradoEn(),
            'observaciones' => $cierre->obtenerObservaciones(),
            'error_mensaje' => $cierre->obtenerErrorMensaje(),
        ]);
    }

    /**
     * @return array<CierreHotelero>
     */
    public function listarPorPropiedad(int $propiedadId, int $limite = 30): array
    {
        $sql = 'SELECT * FROM cierres_hoteleros
                WHERE propiedad_id = :propiedad_id
                ORDER BY fecha_hotelera DESC, id DESC
                LIMIT ' . (int) $limite;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['propiedad_id' => $propiedadId]);

        $cierres = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cierres[] = $this->mapearFila($row);
        }

        return $cierres;
    }

    public function obtenerUltimoCierre(int $propiedadId): ?CierreHotelero
    {
        $sql = 'SELECT * FROM cierres_hoteleros
                WHERE propiedad_id = :propiedad_id AND estado = :estado
                ORDER BY fecha_hotelera DESC, id DESC
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'propiedad_id' => $propiedadId,
            'estado' => CierreHotelero::ESTADO_CERRADO,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->mapearFila($row) : null;
    }

    private function mapearFila(array $row): CierreHotelero
    {
        return new CierreHotelero(
            (int) $row['id'],
            (int) $row['propiedad_id'],
            (string) $row['fecha_hotelera'],
            (string) $row['timezone_utilizada'],
            (string) $row['estado'],
            (int) $row['total_estadias_procesadas'],
            (int) $row['total_noches_devengadas'],
            (string) $row['ingreso_alojamiento_neto'],
            (string) $row['ingreso_alojamiento_impuestos'],
            (string) $row['ingreso_alojamiento_total'],
            (int) $row['unidades_totales'],
            (int) $row['unidades_ooo'],
            (int) $row['unidades_vendibles'],
            (int) $row['habitaciones_vendidas'],
            (int) $row['habitaciones_cortesia'],
            (string) $row['ocupacion_porcentaje'],
            (string) $row['adr'],
            (string) $row['revpar'],
            (string) $row['iniciado_en'],
            $row['cerrado_en'] ? (string) $row['cerrado_en'] : null,
            (int) $row['ejecutado_por_actor_id'],
            $row['observaciones'] ? (string) $row['observaciones'] : null,
            $row['error_mensaje'] ? (string) $row['error_mensaje'] : null,
            (string) $row['creado_en'],
            (string) $row['actualizado_en']
        );
    }
}
