<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\CajaFisica;
use CamargoPMS\Modelos\CuentaBancaria;
use CamargoPMS\Modelos\MetodoPago;
use CamargoPMS\Modelos\MovimientoBancario;
use CamargoPMS\Modelos\MovimientoCaja;
use CamargoPMS\Modelos\SesionCaja;
use PDO;

/**
 * Repositorio para la gestión de cajas físicas, sesiones de turno, cuentas bancarias y libros mayores.
 */
class CajaRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    public function obtenerCajaFisica(int $id): ?CajaFisica
    {
        $stmt = $this->pdo->prepare('SELECT * FROM cajas_fisicas WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? CajaFisica::desdeArreglo($fila) : null;
    }

    /**
     * @return array<CajaFisica>
     */
    public function listarCajasFisicas(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM cajas_fisicas WHERE estado = "ACTIVO" ORDER BY id ASC');
        $resultado = [];
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resultado[] = CajaFisica::desdeArreglo($fila);
        }
        return $resultado;
    }

    public function obtenerCuentaBancaria(int $id): ?CuentaBancaria
    {
        $stmt = $this->pdo->prepare('SELECT * FROM cuentas_bancarias WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? CuentaBancaria::desdeArreglo($fila) : null;
    }

    /**
     * @return array<CuentaBancaria>
     */
    public function listarCuentasBancarias(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM cuentas_bancarias WHERE estado = "ACTIVO" ORDER BY id ASC');
        $resultado = [];
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resultado[] = CuentaBancaria::desdeArreglo($fila);
        }
        return $resultado;
    }

    public function obtenerMetodoPago(int $id): ?MetodoPago
    {
        $stmt = $this->pdo->prepare('SELECT * FROM metodos_pago WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? MetodoPago::desdeArreglo($fila) : null;
    }

    public function obtenerMetodoPagoPorCodigo(string $codigo): ?MetodoPago
    {
        $stmt = $this->pdo->prepare('SELECT * FROM metodos_pago WHERE codigo = :codigo LIMIT 1');
        $stmt->execute(['codigo' => $codigo]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? MetodoPago::desdeArreglo($fila) : null;
    }

    /**
     * @return array<MetodoPago>
     */
    public function listarMetodosPago(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM metodos_pago WHERE activo = 1 ORDER BY id ASC');
        $resultado = [];
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resultado[] = MetodoPago::desdeArreglo($fila);
        }
        return $resultado;
    }

    public function obtenerSesionCaja(int $id, bool $bloquear = false): ?SesionCaja
    {
        $sql = 'SELECT * FROM sesiones_caja WHERE id = :id LIMIT 1' . ($bloquear ? ' FOR UPDATE' : '');
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? SesionCaja::desdeArreglo($fila) : null;
    }

    public function obtenerSesionAbierta(int $cajaFisicaId, int $actorId, bool $bloquear = false): ?SesionCaja
    {
        $sql = 'SELECT * FROM sesiones_caja 
                WHERE caja_fisica_id = :caja_id AND actor_apertura_id = :actor_id AND estado = "ABIERTA" 
                ORDER BY id DESC LIMIT 1' . ($bloquear ? ' FOR UPDATE' : '');
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['caja_id' => $cajaFisicaId, 'actor_id' => $actorId]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? SesionCaja::desdeArreglo($fila) : null;
    }

    public function crearSesionCaja(SesionCaja $sesion): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO sesiones_caja (
                caja_fisica_id, actor_apertura_id, monto_apertura, total_ingresos_efectivo, total_egresos_efectivo,
                estado, abierta_en, observaciones_apertura
            ) VALUES (
                :caja_fisica_id, :actor_apertura_id, :monto_apertura, :total_ingresos_efectivo, :total_egresos_efectivo,
                :estado, :abierta_en, :observaciones_apertura
            )'
        );
        $stmt->execute([
            'caja_fisica_id' => $sesion->obtenerCajaFisicaId(),
            'actor_apertura_id' => $sesion->obtenerActorAperturaId(),
            'monto_apertura' => $sesion->obtenerMontoApertura(),
            'total_ingresos_efectivo' => $sesion->obtenerTotalIngresosEfectivo(),
            'total_egresos_efectivo' => $sesion->obtenerTotalEgresosEfectivo(),
            'estado' => $sesion->obtenerEstado(),
            'abierta_en' => $sesion->obtenerAbiertaEn(),
            'observaciones_apertura' => $sesion->obtenerObservacionesApertura(),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function actualizarTotalesSesionCaja(int $sesionId, string $deltaIngreso, string $deltaEgreso): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE sesiones_caja 
             SET total_ingresos_efectivo = total_ingresos_efectivo + :delta_ingreso,
                 total_egresos_efectivo = total_egresos_efectivo + :delta_egreso
             WHERE id = :id'
        );
        $stmt->execute([
            'delta_ingreso' => $deltaIngreso,
            'delta_egreso' => $deltaEgreso,
            'id' => $sesionId,
        ]);
    }

    public function cerrarSesionCaja(
        int $sesionId,
        int $actorCierreId,
        string $montoEsperado,
        string $montoDeclarado,
        string $diferencia,
        string $resultadoArqueo,
        ?string $obsCierre,
        string $cerradaEn
    ): void {
        $stmt = $this->pdo->prepare(
            'UPDATE sesiones_caja 
             SET actor_cierre_id = :actor_cierre_id,
                 monto_esperado = :monto_esperado,
                 monto_contado_declarado = :monto_declarado,
                 diferencia = :diferencia,
                 resultado_arqueo = :resultado_arqueo,
                 estado = "CERRADA",
                 cerrada_en = :cerrada_en,
                 observaciones_cierre = :obs_cierre
             WHERE id = :id'
        );
        $stmt->execute([
            'actor_cierre_id' => $actorCierreId,
            'monto_esperado' => $montoEsperado,
            'monto_declarado' => $montoDeclarado,
            'diferencia' => $diferencia,
            'resultado_arqueo' => $resultadoArqueo,
            'obs_cierre' => $obsCierre,
            'cerrada_en' => $cerradaEn,
            'id' => $sesionId,
        ]);
    }

    public function crearMovimientoCaja(MovimientoCaja $movimiento): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO movimientos_caja (
                sesion_caja_id, tipo_movimiento, pago_id, devolucion_id, monto, moneda_codigo, concepto, actor_id, creado_en
            ) VALUES (
                :sesion_caja_id, :tipo_movimiento, :pago_id, :devolucion_id, :monto, :moneda_codigo, :concepto, :actor_id, NOW()
            )'
        );
        $stmt->execute([
            'sesion_caja_id' => $movimiento->obtenerSesionCajaId(),
            'tipo_movimiento' => $movimiento->obtenerTipoMovimiento(),
            'pago_id' => $movimiento->obtenerPagoId(),
            'devolucion_id' => $movimiento->obtenerDevolucionId(),
            'monto' => $movimiento->obtenerMonto(),
            'moneda_codigo' => $movimiento->obtenerMonedaCodigo(),
            'concepto' => $movimiento->obtenerConcepto(),
            'actor_id' => $movimiento->obtenerActorId(),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function crearMovimientoBancario(MovimientoBancario $movimiento): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO movimientos_bancarios (
                cuenta_bancaria_id, tipo_movimiento, pago_id, devolucion_id, monto, moneda_codigo,
                numero_operacion, concepto, fecha_operacion, actor_id, creado_en
            ) VALUES (
                :cuenta_bancaria_id, :tipo_movimiento, :pago_id, :devolucion_id, :monto, :moneda_codigo,
                :numero_operacion, :concepto, :fecha_operacion, :actor_id, NOW()
            )'
        );
        $stmt->execute([
            'cuenta_bancaria_id' => $movimiento->obtenerCuentaBancariaId(),
            'tipo_movimiento' => $movimiento->obtenerTipoMovimiento(),
            'pago_id' => $movimiento->obtenerPagoId(),
            'devolucion_id' => $movimiento->obtenerDevolucionId(),
            'monto' => $movimiento->obtenerMonto(),
            'moneda_codigo' => $movimiento->obtenerMonedaCodigo(),
            'numero_operacion' => $movimiento->obtenerNumeroOperacion(),
            'concepto' => $movimiento->obtenerConcepto(),
            'fecha_operacion' => $movimiento->obtenerFechaOperacion(),
            'actor_id' => $movimiento->obtenerActorId(),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @return array<MovimientoCaja>
     */
    public function obtenerMovimientoCaja(int $id): ?MovimientoCaja
    {
        $stmt = $this->pdo->prepare('SELECT * FROM movimientos_caja WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? MovimientoCaja::desdeArreglo($fila) : null;
    }

    public function obtenerSesionAbiertaPorCaja(int $cajaFisicaId, bool $bloquear = false): ?SesionCaja
    {
        $sql = 'SELECT * FROM sesiones_caja 
                WHERE caja_fisica_id = :caja_id AND estado = "ABIERTA" 
                ORDER BY id DESC LIMIT 1' . ($bloquear ? ' FOR UPDATE' : '');
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['caja_id' => $cajaFisicaId]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? SesionCaja::desdeArreglo($fila) : null;
    }

    /**
     * @return array<MovimientoCaja>
     */
    public function listarMovimientosCaja(int $sesionId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM movimientos_caja WHERE sesion_caja_id = :sesion_id ORDER BY id ASC');
        $stmt->execute(['sesion_id' => $sesionId]);
        $resultado = [];
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resultado[] = MovimientoCaja::desdeArreglo($fila);
        }
        return $resultado;
    }
}

