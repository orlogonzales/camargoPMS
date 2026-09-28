<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

use CamargoPMS\Excepciones\CajaNoAbiertaExcepcion;
use CamargoPMS\Excepciones\ConflictoGastoExcepcion;
use CamargoPMS\Excepciones\GastoNoEncontradoExcepcion;
use CamargoPMS\Excepciones\MontoInvalidoExcepcion;
use CamargoPMS\Excepciones\PagoEgresoNoEncontradoExcepcion;
use CamargoPMS\Excepciones\ValidacionGastoExcepcion;
use CamargoPMS\Modelos\Gasto;
use CamargoPMS\Modelos\GastoAplicacionPago;
use CamargoPMS\Modelos\GastoCategoria;
use CamargoPMS\Modelos\GastoEvidencia;
use CamargoPMS\Modelos\GastoHistorialEstado;
use CamargoPMS\Modelos\GastoResumenDTO;
use CamargoPMS\Modelos\MovimientoBancario;
use CamargoPMS\Modelos\MovimientoCaja;
use CamargoPMS\Modelos\PagoEgreso;
use CamargoPMS\Repositorios\ActorAuditoriaRepositorio;
use CamargoPMS\Repositorios\CajaRepositorio;
use CamargoPMS\Repositorios\GastoRepositorio;
use CamargoPMS\Repositorios\PagoEgresoRepositorio;
use PDO;
use Throwable;

/**
 * Servicio de dominio central para la gestión del Hecho Económico de Gastos,
 * evidencias documentales, y su articulación soberana con Tesorería (FINANCIERO-2).
 *
 * GASTOS-1 / D-086.
 */
class GastoServicio
{
    private GastoRepositorio $gastoRepo;
    private PagoEgresoRepositorio $pagoRepo;
    private CajaRepositorio $cajaRepo;
    private ActorAuditoriaRepositorio $actorRepo;

    public function __construct(
        private PDO $pdo,
        ?GastoRepositorio $gastoRepo = null,
        ?PagoEgresoRepositorio $pagoRepo = null,
        ?CajaRepositorio $cajaRepo = null,
        ?ActorAuditoriaRepositorio $actorRepo = null
    ) {
        $this->gastoRepo = $gastoRepo ?? new GastoRepositorio($pdo);
        $this->pagoRepo = $pagoRepo ?? new PagoEgresoRepositorio($pdo);
        $this->cajaRepo = $cajaRepo ?? new CajaRepositorio($pdo);
        $this->actorRepo = $actorRepo ?? new ActorAuditoriaRepositorio($pdo);
    }

    private function resolverActorId(?int $actorOUsuarioId): int
    {
        if ($actorOUsuarioId !== null && $actorOUsuarioId > 0) {
            $actor = $this->actorRepo->buscarPorId($actorOUsuarioId);
            if ($actor !== null) {
                return (int) $actor->obtenerId();
            }
            $actorHumano = $this->actorRepo->buscarPorUsuarioId($actorOUsuarioId);
            if ($actorHumano !== null) {
                return (int) $actorHumano->obtenerId();
            }
        }
        $sistema = $this->actorRepo->buscarPorCodigo('CAMARGO_PMS');
        return $sistema !== null ? (int) $sistema->obtenerId() : 1;
    }

    // =========================================================================
    // CATEGORÍAS
    // =========================================================================

    /**
     * @return GastoCategoria[]
     */
    public function listarCategorias(bool $soloActivas = true): array
    {
        return $this->gastoRepo->listarCategorias($soloActivas);
    }

    public function obtenerCategoriaPorId(int $id): ?GastoCategoria
    {
        return $this->gastoRepo->obtenerCategoriaPorId($id);
    }

    // =========================================================================
    // GASTOS (HECHO ECONÓMICO SOBERANO)
    // =========================================================================

    /**
     * Registra un nuevo hecho económico de gasto.
     */
    public function crearGasto(array $datos, ?int $actorId = null): Gasto
    {
        $actorIdFinal = $this->resolverActorId($actorId);

        // Validación de Categoría
        $categoriaId = (int) ($datos['categoria_id'] ?? 0);
        if ($categoriaId <= 0) {
            throw new ValidacionGastoExcepcion('La categoría de gasto es obligatoria.');
        }
        $categoria = $this->gastoRepo->obtenerCategoriaPorId($categoriaId);
        if ($categoria === null || !$categoria->estaActivo()) {
            throw new ValidacionGastoExcepcion("La categoría de gasto [{$categoriaId}] no existe o no está activa.");
        }

        // Validación de Concepto y Acreedor
        $concepto = trim((string) ($datos['descripcion_concepto'] ?? ''));
        if ($concepto === '') {
            throw new ValidacionGastoExcepcion('La descripción del concepto de gasto es obligatoria.');
        }
        $acreedorNombre = trim((string) ($datos['acreedor_nombre'] ?? ''));
        if ($acreedorNombre === '') {
            throw new ValidacionGastoExcepcion('El nombre o razón social del acreedor es obligatorio.');
        }
        $acreedorDocumento = isset($datos['acreedor_documento']) && trim((string) $datos['acreedor_documento']) !== ''
            ? trim((string) $datos['acreedor_documento'])
            : null;

        $propiedadId = isset($datos['propiedad_id']) && (int) $datos['propiedad_id'] > 0
            ? (int) $datos['propiedad_id']
            : null;
        $unidadId = isset($datos['unidad_id']) && (int) $datos['unidad_id'] > 0
            ? (int) $datos['unidad_id']
            : null;

        // Si el ámbito no se especificó expresamente, se infiere según la imputación provista
        if (empty($datos['ambito'])) {
            if ($propiedadId !== null && $unidadId !== null) {
                $ambito = Gasto::AMBITO_UNIDAD;
            } elseif ($propiedadId !== null) {
                $ambito = Gasto::AMBITO_PROPIEDAD;
            } else {
                $ambito = Gasto::AMBITO_CORPORATIVO;
            }
        } else {
            $ambito = strtoupper(trim((string) $datos['ambito']));
            if (!in_array($ambito, [Gasto::AMBITO_CORPORATIVO, Gasto::AMBITO_PROPIEDAD, Gasto::AMBITO_UNIDAD], true)) {
                $ambito = Gasto::AMBITO_CORPORATIVO;
            }
        }

        if ($ambito === Gasto::AMBITO_CORPORATIVO) {
            $propiedadId = null;
            $unidadId = null;
        } elseif ($ambito === Gasto::AMBITO_PROPIEDAD) {
            if ($propiedadId === null) {
                throw new ValidacionGastoExcepcion('El ámbito PROPIEDAD requiere especificar una propiedad válida.');
            }
            $unidadId = null;
        } elseif ($ambito === Gasto::AMBITO_UNIDAD) {
            if ($propiedadId === null || $unidadId === null) {
                throw new ValidacionGastoExcepcion('El ámbito UNIDAD requiere especificar tanto la propiedad como la unidad.');
            }
            // Verificar que la unidad pertenezca a la propiedad
            $stmtU = $this->pdo->prepare('SELECT propiedad_id FROM unidades WHERE id = :id LIMIT 1');
            $stmtU->execute(['id' => $unidadId]);
            $propIdUnidad = $stmtU->fetchColumn();
            if ($propIdUnidad === false || (int) $propIdUnidad !== $propiedadId) {
                throw new ValidacionGastoExcepcion("La unidad [{$unidadId}] no pertenece a la propiedad seleccionada [{$propiedadId}].");
            }
        }

        // Proveedor opcional
        $proveedorId = isset($datos['proveedor_id']) && (int) $datos['proveedor_id'] > 0
            ? (int) $datos['proveedor_id']
            : null;

        // Tipo de Comprobante
        $tipoComprobante = strtoupper(trim((string) ($datos['tipo_comprobante'] ?? Gasto::COMPROBANTE_FACTURA)));
        $tiposValidos = [
            Gasto::COMPROBANTE_FACTURA,
            Gasto::COMPROBANTE_BOLETA,
            Gasto::COMPROBANTE_RECIBO_HONORARIOS,
            Gasto::COMPROBANTE_RECIBO_SERVICIO_PUBLICO,
            Gasto::COMPROBANTE_DECLARACION_JURADA,
            Gasto::COMPROBANTE_TICKET,
            Gasto::COMPROBANTE_OTRO,
        ];
        if (!in_array($tipoComprobante, $tiposValidos, true)) {
            $tipoComprobante = Gasto::COMPROBANTE_OTRO;
        }

        $serie = isset($datos['comprobante_serie']) && trim((string) $datos['comprobante_serie']) !== ''
            ? trim((string) $datos['comprobante_serie'])
            : null;
        $numero = isset($datos['comprobante_numero']) && trim((string) $datos['comprobante_numero']) !== ''
            ? trim((string) $datos['comprobante_numero'])
            : null;

        $fechaEmision = !empty($datos['fecha_emision']) ? (string) $datos['fecha_emision'] : date('Y-m-d');
        $fechaVencimiento = !empty($datos['fecha_vencimiento']) ? (string) $datos['fecha_vencimiento'] : $fechaEmision;

        // Cálculo exacto con BCMath: Total = Subtotal + Impuestos
        $subtotal = isset($datos['subtotal']) && is_numeric($datos['subtotal'])
            ? bcadd((string) $datos['subtotal'], '0.00', 2)
            : '0.00';
        $impuestos = isset($datos['impuestos']) && is_numeric($datos['impuestos'])
            ? bcadd((string) $datos['impuestos'], '0.00', 2)
            : '0.00';

        if (isset($datos['total']) && is_numeric($datos['total'])) {
            $total = bcadd((string) $datos['total'], '0.00', 2);
            // Si subtotal vino en 0 pero vino total, subtotal = total - impuestos
            if (bccomp($subtotal, '0.00', 2) === 0 && bccomp($total, '0.00', 2) > 0) {
                $subtotal = bcsub($total, $impuestos, 2);
            }
        } else {
            $total = bcadd($subtotal, $impuestos, 2);
        }

        if (bccomp($total, '0.00', 2) < 0) {
            throw new MontoInvalidoExcepcion('total', $total);
        }

        $estado = strtoupper(trim((string) ($datos['estado'] ?? Gasto::ESTADO_REGISTRADO)));
        if (!in_array($estado, [Gasto::ESTADO_BORRADOR, Gasto::ESTADO_REGISTRADO, Gasto::ESTADO_APROBADO], true)) {
            $estado = Gasto::ESTADO_REGISTRADO;
        }

        $this->pdo->beginTransaction();
        try {
            $codigo = $this->gastoRepo->generarSiguienteCodigoGasto();

            $nuevoGasto = new Gasto(
                null,
                $codigo,
                $categoriaId,
                $ambito,
                $propiedadId,
                $unidadId,
                $proveedorId,
                $acreedorNombre,
                $acreedorDocumento,
                $concepto,
                $tipoComprobante,
                $serie,
                $numero,
                $fechaEmision,
                $fechaVencimiento,
                'PEN',
                $subtotal,
                $impuestos,
                $total,
                $estado,
                null,
                null,
                null,
                $actorIdFinal
            );

            $id = $this->gastoRepo->crearGasto($nuevoGasto);

            // Registro en Historial D-061
            $hist = new GastoHistorialEstado(
                null,
                $id,
                null,
                $estado,
                'Registro inicial del gasto',
                $actorIdFinal,
                $datos['correlation_id'] ?? null
            );
            $this->gastoRepo->registrarHistorialEstado($hist);

            // Si vienen evidencias iniciales
            if (!empty($datos['evidencias']) && is_array($datos['evidencias'])) {
                foreach ($datos['evidencias'] as $ev) {
                    if (is_array($ev) && !empty($ev['ruta_archivo'])) {
                        $evidencia = new GastoEvidencia(
                            null,
                            $id,
                            $ev['tipo_evidencia'] ?? GastoEvidencia::TIPO_COMPROBANTE_FISCAL,
                            $ev['nombre_original'] ?? basename((string) $ev['ruta_archivo']),
                            (string) $ev['ruta_archivo'],
                            $ev['mime_type'] ?? 'application/pdf',
                            (int) ($ev['tamano_bytes'] ?? 0),
                            (string) ($ev['hash_sha256'] ?? hash('sha256', (string) $ev['ruta_archivo'])),
                            $actorIdFinal
                        );
                        $this->gastoRepo->crearEvidencia($evidencia);
                    }
                }
            }

            $this->pdo->commit();

            $creado = $this->gastoRepo->obtenerGastoPorId($id);
            if ($creado === null) {
                throw new ValidacionGastoExcepcion("Error al recuperar el gasto recién creado [{$id}].");
            }
            return $creado;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Aprueba formalmente un gasto para habilitar su desembolso y pago.
     */
    public function aprobarGasto(int $gastoId, ?int $actorId = null, ?string $motivo = null): Gasto
    {
        $actorIdFinal = $this->resolverActorId($actorId);

        $this->pdo->beginTransaction();
        try {
            $gasto = $this->gastoRepo->obtenerGastoPorId($gastoId, true);
            if ($gasto === null) {
                throw new GastoNoEncontradoExcepcion("El gasto [{$gastoId}] no fue encontrado.");
            }

            if ($gasto->estaAnulado()) {
                throw new ConflictoGastoExcepcion("No se puede aprobar un gasto que ya se encuentra ANULADO.");
            }

            if ($gasto->estaAprobado()) {
                $this->pdo->commit();
                return $gasto;
            }

            $estadoAnterior = $gasto->obtenerEstado();
            $this->gastoRepo->actualizarEstadoGasto($gastoId, Gasto::ESTADO_APROBADO);

            $hist = new GastoHistorialEstado(
                null,
                $gastoId,
                $estadoAnterior,
                Gasto::ESTADO_APROBADO,
                $motivo ?? 'Aprobación formal del gasto',
                $actorIdFinal
            );
            $this->gastoRepo->registrarHistorialEstado($hist);

            $this->pdo->commit();
            return $this->gastoRepo->obtenerGastoPorId($gastoId);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Anula un gasto.
     * Invariante crítica: Si el gasto posee pagos/aplicaciones activas, se RECHAZA la anulación.
     */
    public function anularGasto(int $gastoId, ?int $actorId = null, string $motivo = ''): Gasto
    {
        $actorIdFinal = $this->resolverActorId($actorId);

        if (trim($motivo) === '') {
            throw new ValidacionGastoExcepcion('Es obligatorio especificar el motivo de anulación del gasto.');
        }

        $this->pdo->beginTransaction();
        try {
            $gasto = $this->gastoRepo->obtenerGastoPorId($gastoId, true);
            if ($gasto === null) {
                throw new GastoNoEncontradoExcepcion("El gasto [{$gastoId}] no fue encontrado.");
            }

            if ($gasto->estaAnulado()) {
                $this->pdo->commit();
                return $gasto;
            }

            // Invariante de Anulación: Comprobar que no existan aplicaciones activas
            $montoAplicado = $this->gastoRepo->calcularMontoAplicadoAcumulado($gastoId);
            if (bccomp($montoAplicado, '0.00', 2) > 0) {
                throw new ConflictoGastoExcepcion(
                    "No se puede anular el gasto [{$gasto->obtenerCodigo()}] porque cuenta con S/ {$montoAplicado} en pagos aplicados activos. Debe reversar previamente dichos pagos en tesorería."
                );
            }

            $estadoAnterior = $gasto->obtenerEstado();
            $ahora = date('Y-m-d H:i:s');
            $this->gastoRepo->actualizarEstadoGasto($gastoId, Gasto::ESTADO_ANULADO, $motivo, $actorIdFinal, $ahora);

            $hist = new GastoHistorialEstado(
                null,
                $gastoId,
                $estadoAnterior,
                Gasto::ESTADO_ANULADO,
                $motivo,
                $actorIdFinal
            );
            $this->gastoRepo->registrarHistorialEstado($hist);

            $this->pdo->commit();
            return $this->gastoRepo->obtenerGastoPorId($gastoId);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Adjunta una evidencia documental (archivo) a un gasto existente.
     */
    public function adjuntarEvidencia(int $gastoId, array $datosArchivo, ?int $actorId = null): GastoEvidencia
    {
        $actorIdFinal = $this->resolverActorId($actorId);

        $gasto = $this->gastoRepo->obtenerGastoPorId($gastoId);
        if ($gasto === null) {
            throw new GastoNoEncontradoExcepcion("El gasto [{$gastoId}] no fue encontrado.");
        }

        if ($gasto->estaAnulado()) {
            throw new ConflictoGastoExcepcion("No se pueden adjuntar evidencias a un gasto ANULADO.");
        }

        $ruta = trim((string) ($datosArchivo['ruta_archivo'] ?? ''));
        if ($ruta === '') {
            throw new ValidacionGastoExcepcion('La ruta del archivo de evidencia es obligatoria.');
        }

        $nombreOriginal = trim((string) ($datosArchivo['nombre_original'] ?? basename($ruta)));
        $mimeType = (string) ($datosArchivo['mime_type'] ?? 'application/pdf');
        $tamanoBytes = (int) ($datosArchivo['tamano_bytes'] ?? 0);
        $hash = (string) ($datosArchivo['hash_sha256'] ?? hash('sha256', $ruta));

        $evidencia = new GastoEvidencia(
            null,
            $gastoId,
            $datosArchivo['tipo_evidencia'] ?? GastoEvidencia::TIPO_COMPROBANTE_FISCAL,
            $nombreOriginal,
            $ruta,
            $mimeType,
            $tamanoBytes,
            $hash,
            $actorIdFinal
        );

        $id = $this->gastoRepo->crearEvidencia($evidencia);
        return new GastoEvidencia(
            $id,
            $gastoId,
            $evidencia->obtenerTipoEvidencia(),
            $nombreOriginal,
            $ruta,
            $mimeType,
            $tamanoBytes,
            $hash,
            $actorIdFinal,
            date('Y-m-d H:i:s')
        );
    }

    public function obtenerGastoPorId(int $gastoId): ?Gasto
    {
        return $this->gastoRepo->obtenerGastoPorId($gastoId);
    }

    public function obtenerResumenGasto(int $gastoId): ?GastoResumenDTO
    {
        return $this->gastoRepo->obtenerResumenGasto($gastoId);
    }

    /**
     * @return Gasto[]
     */
    public function listarGastos(array $filtros = []): array
    {
        return $this->gastoRepo->listarGastos($filtros);
    }

    // =========================================================================
    // TESORERÍA: EJECUCIÓN DE PAGOS DE EGRESO E IMPUTACIÓN (M:N)
    // =========================================================================

    /**
     * Emite un Pago de Egreso soberano y lo aplica a uno o varios gastos.
     * Cero tesorerías paralelas: Reutiliza sesiones_caja, movimientos_caja, cuentas_bancarias y movimientos_bancarios.
     *
     * @param array $datosPago [
     *   'metodo_pago_id' => int,
     *   'sesion_caja_id' => ?int,
     *   'cuenta_bancaria_id' => ?int,
     *   'referencia_operacion' => ?string,
     *   'aplicaciones' => [
     *       ['gasto_id' => int, 'monto' => string],
     *       ...
     *   ]
     * ]
     * @return array ['pago' => PagoEgreso, 'aplicaciones' => GastoAplicacionPago[]]
     */
    public function ejecutarPagoEgreso(array $datosPago, ?int $actorId = null): array
    {
        $actorIdFinal = $this->resolverActorId($actorId);

        $metodoPagoId = (int) ($datosPago['metodo_pago_id'] ?? 0);
        if ($metodoPagoId <= 0) {
            throw new ValidacionGastoExcepcion('El método de pago es obligatorio.');
        }

        // Consultar método de pago para saber si es caja física o banco
        $stmtM = $this->pdo->prepare('SELECT * FROM metodos_pago WHERE id = :id AND activo = 1 LIMIT 1');
        $stmtM->execute(['id' => $metodoPagoId]);
        $metodo = $stmtM->fetch(PDO::FETCH_ASSOC);
        if (!$metodo) {
            throw new ValidacionGastoExcepcion("El método de pago [{$metodoPagoId}] no existe o no está activo.");
        }

        $tipoDestino = (string) $metodo['tipo_destino'];
        $esEfectivo = ($tipoDestino === 'CAJA_FISICA' || (string) $metodo['codigo'] === 'EFECTIVO');

        $sesionCajaId = isset($datosPago['sesion_caja_id']) && (int) $datosPago['sesion_caja_id'] > 0
            ? (int) $datosPago['sesion_caja_id']
            : null;
        $cuentaBancariaId = isset($datosPago['cuenta_bancaria_id']) && (int) $datosPago['cuenta_bancaria_id'] > 0
            ? (int) $datosPago['cuenta_bancaria_id']
            : null;

        if ($esEfectivo && $sesionCajaId === null) {
            throw new ValidacionGastoExcepcion('El pago en efectivo requiere especificar una sesión de caja activa.');
        }
        if (!$esEfectivo && $tipoDestino === 'CUENTA_BANCARIA' && $cuentaBancariaId === null) {
            throw new ValidacionGastoExcepcion('El pago por transferencia bancaria requiere especificar una cuenta bancaria.');
        }

        $aplicacionesInput = $datosPago['aplicaciones'] ?? [];
        if (!is_array($aplicacionesInput) || empty($aplicacionesInput)) {
            throw new ValidacionGastoExcepcion('Debe especificar al menos un gasto para aplicar el pago.');
        }

        $this->pdo->beginTransaction();
        try {
            $montoTotalPago = '0.00';
            $gastosBloqueados = [];
            $aplicacionesValidadas = [];

            // 1. Validar y bloquear cada gasto concurrentemente
            foreach ($aplicacionesInput as $item) {
                $gId = (int) ($item['gasto_id'] ?? 0);
                $montoItem = (string) ($item['monto'] ?? '0.00');

                if (!is_numeric($montoItem) || bccomp($montoItem, '0.00', 2) <= 0) {
                    throw new MontoInvalidoExcepcion('monto_aplicado', $montoItem);
                }
                $montoItemNorm = bcadd($montoItem, '0.00', 2);

                $gasto = $this->gastoRepo->obtenerGastoPorId($gId, true);
                if ($gasto === null) {
                    throw new GastoNoEncontradoExcepcion("El gasto [{$gId}] no fue encontrado.");
                }

                if ($gasto->estaAnulado()) {
                    throw new ConflictoGastoExcepcion("No se puede emitir pagos a un gasto ANULADO [{$gasto->obtenerCodigo()}].");
                }

                if (!$gasto->estaAprobado()) {
                    throw new ConflictoGastoExcepcion("El gasto [{$gasto->obtenerCodigo()}] debe estar APROBADO para poder ser pagado (estado actual: {$gasto->obtenerEstado()}).");
                }

                // Cómputo del saldo reconstructible bloqueado
                $totalAplicado = $this->pagoRepo->obtenerTotalAplicadoActivoPorGasto($gId, true);
                $saldoDisponible = bcsub($gasto->obtenerTotal(), $totalAplicado, 2);

                if (bccomp($montoItemNorm, $saldoDisponible, 2) > 0) {
                    throw new ConflictoGastoExcepcion(
                        "El monto a pagar [S/ {$montoItemNorm}] supera el saldo pendiente [S/ {$saldoDisponible}] del gasto [{$gasto->obtenerCodigo()}]."
                    );
                }

                $montoTotalPago = bcadd($montoTotalPago, $montoItemNorm, 2);
                $gastosBloqueados[$gId] = $gasto;
                $aplicacionesValidadas[] = [
                    'gasto_id' => $gId,
                    'monto' => $montoItemNorm,
                    'gasto' => $gasto,
                ];
            }

            $montoTotalDeclarado = isset($datosPago['monto_total']) && is_numeric($datosPago['monto_total'])
                ? bcadd((string) $datosPago['monto_total'], '0.00', 2)
                : null;

            if ($montoTotalDeclarado !== null) {
                if (bccomp($montoTotalPago, $montoTotalDeclarado, 2) > 0) {
                    throw new ConflictoGastoExcepcion(
                        "La sumatoria de aplicaciones activas [S/ {$montoTotalPago}] no puede superar el monto total del desembolso [S/ {$montoTotalDeclarado}]."
                    );
                }
                $montoDesembolso = $montoTotalDeclarado;
            } else {
                $montoDesembolso = $montoTotalPago;
            }

            $movimientoCajaId = null;
            $movimientoBancarioId = null;

            // 2. Si es EFECTIVO: Validar sesión de caja y saldo de efectivo
            if ($esEfectivo) {
                $sesion = $this->cajaRepo->obtenerSesionCaja($sesionCajaId, true);
                if ($sesion === null || !$sesion->estaAbierta()) {
                    throw new CajaNoAbiertaExcepcion("La sesión de caja ID [{$sesionCajaId}] no se encuentra abierta.");
                }

                // Saldo esperado actual en caja = apertura + ingresos - egresos
                $ingresosNetos = bcsub($sesion->obtenerTotalIngresosEfectivo(), $sesion->obtenerTotalEgresosEfectivo(), 2);
                $saldoEfectivoCaja = bcadd($sesion->obtenerMontoApertura(), $ingresosNetos, 2);

                if (bccomp($montoDesembolso, $saldoEfectivoCaja, 2) > 0) {
                    throw new ConflictoGastoExcepcion(
                        "Saldo insuficiente en la caja física. Saldo en gaveta: S/ {$saldoEfectivoCaja}, monto a egresar: S/ {$montoDesembolso}."
                    );
                }

                // Concepto descriptivo del egreso en el libro mayor de caja
                $conceptosGastos = array_map(fn($a) => $a['gasto']->obtenerCodigo(), $aplicacionesValidadas);
                $conceptoCaja = 'Pago de egreso de gastos: ' . implode(', ', $conceptosGastos);

                $movCaja = new MovimientoCaja(
                    null,
                    $sesionCajaId,
                    'EGRESO_GASTO_MENOR',
                    null,
                    null,
                    $montoDesembolso,
                    'PEN',
                    $conceptoCaja,
                    $actorIdFinal
                );
                $movimientoCajaId = $this->cajaRepo->crearMovimientoCaja($movCaja);

                // Actualizar total egresos en la sesión
                $this->cajaRepo->actualizarTotalesSesionCaja($sesionCajaId, '0.00', $montoDesembolso);
            } elseif ($cuentaBancariaId !== null) {
                // Pago Bancario
                $conceptosGastos = array_map(fn($a) => $a['gasto']->obtenerCodigo(), $aplicacionesValidadas);
                $conceptoBanco = 'Pago de egreso de gastos: ' . implode(', ', $conceptosGastos);

                $movBanco = new MovimientoBancario(
                    null,
                    $cuentaBancariaId,
                    'EGRESO_TRANSFERENCIA',
                    null,
                    null,
                    $montoDesembolso,
                    'PEN',
                    (string) ($datosPago['referencia_operacion'] ?? 'SIN_OPERACION'),
                    $conceptoBanco,
                    date('Y-m-d'),
                    $actorIdFinal
                );
                $movimientoBancarioId = $this->cajaRepo->crearMovimientoBancario($movBanco);
            }

            // 3. Crear el PagoEgreso soberano
            $codigoPago = $this->pagoRepo->generarSiguienteCodigoPagoEgreso();
            $pagoEgreso = new PagoEgreso(
                null,
                $codigoPago,
                $metodoPagoId,
                $montoDesembolso,
                'PEN',
                date('Y-m-d H:i:s'),
                $sesionCajaId,
                $movimientoCajaId,
                $cuentaBancariaId,
                $movimientoBancarioId,

                $datosPago['referencia_operacion'] ?? null,
                PagoEgreso::ESTADO_CONFIRMADO,
                null,
                null,
                null,
                $actorIdFinal
            );
            $pagoEgresoId = $this->pagoRepo->crearPagoEgreso($pagoEgreso);

            // 4. Crear las aplicaciones individuales M:N
            $aplicacionesCreadas = [];
            foreach ($aplicacionesValidadas as $appData) {
                $codigoApl = $this->pagoRepo->generarSiguienteCodigoAplicacion();
                $apl = new GastoAplicacionPago(
                    null,
                    $codigoApl,
                    $appData['gasto_id'],
                    $pagoEgresoId,
                    $appData['monto'],
                    GastoAplicacionPago::ESTADO_ACTIVO,
                    null,
                    null,
                    null,
                    $actorIdFinal
                );
                $aplId = $this->pagoRepo->crearAplicacion($apl);
                $aplicacionesCreadas[] = new GastoAplicacionPago(
                    $aplId,
                    $codigoApl,
                    $appData['gasto_id'],
                    $pagoEgresoId,
                    $appData['monto'],
                    GastoAplicacionPago::ESTADO_ACTIVO,
                    null,
                    null,
                    null,
                    $actorIdFinal,
                    date('Y-m-d H:i:s')
                );
            }

            $this->pdo->commit();

            return [
                'pago' => $this->pagoRepo->obtenerPagoEgresoPorId($pagoEgresoId),
                'aplicaciones' => $aplicacionesCreadas,
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Aplica un desembolso soberano existente a un gasto aprobado.
     * Garantiza bajo concurrencia:
     * 1. Invariante Desembolso: SUM(aplicaciones activas) <= monto_total del desembolso (no gastar dos veces el mismo dinero).
     * 2. Invariante Gasto: SUM(aplicaciones activas) <= total del gasto (no sobrepagar el gasto).
     */
    public function aplicarPagoExistenteAGasto(
        int $pagoEgresoId,
        int $gastoId,
        string $monto,
        ?int $actorId = null
    ): GastoAplicacionPago {
        $actorIdFinal = $this->resolverActorId($actorId);

        if (!is_numeric($monto) || bccomp((string) $monto, '0.00', 2) <= 0) {
            throw new MontoInvalidoExcepcion('monto_aplicado', (string) $monto);
        }
        $montoNorm = bcadd((string) $monto, '0.00', 2);

        $this->pdo->beginTransaction();
        try {
            // 1. Bloqueo pesimista del desembolso soberano
            $pago = $this->pagoRepo->obtenerPagoEgresoPorId($pagoEgresoId, true);
            if ($pago === null) {
                throw new PagoEgresoNoEncontradoExcepcion("El pago de egreso [{$pagoEgresoId}] no existe.");
            }

            if ($pago->estaReversado()) {
                throw new ConflictoGastoExcepcion("No se pueden aplicar fondos de un pago de egreso REVERSADO [{$pago->obtenerCodigo()}].");
            }

            // Invariante 1: Fondos disponibles en el desembolso
            $totalAplicadoPago = $this->pagoRepo->obtenerTotalAplicadoActivoPorPago($pagoEgresoId, true);
            $disponibleEnPago = bcsub($pago->obtenerMontoTotal(), $totalAplicadoPago, 2);

            if (bccomp($montoNorm, $disponibleEnPago, 2) > 0) {
                throw new ConflictoGastoExcepcion(
                    "El monto a aplicar [S/ {$montoNorm}] supera el remanente disponible [S/ {$disponibleEnPago}] del desembolso [{$pago->obtenerCodigo()}]. Se previene doble aplicación de fondos."
                );
            }

            // 2. Bloqueo pesimista del gasto
            $gasto = $this->gastoRepo->obtenerGastoPorId($gastoId, true);
            if ($gasto === null) {
                throw new GastoNoEncontradoExcepcion("El gasto [{$gastoId}] no fue encontrado.");
            }

            if ($gasto->estaAnulado()) {
                throw new ConflictoGastoExcepcion("No se puede aplicar pagos a un gasto ANULADO [{$gasto->obtenerCodigo()}].");
            }

            if (!$gasto->estaAprobado()) {
                throw new ConflictoGastoExcepcion("El gasto [{$gasto->obtenerCodigo()}] debe estar APROBADO.");
            }

            // Invariante 2: Saldo pendiente del gasto
            $totalAplicadoGasto = $this->pagoRepo->obtenerTotalAplicadoActivoPorGasto($gastoId, true);
            $saldoDisponibleGasto = bcsub($gasto->obtenerTotal(), $totalAplicadoGasto, 2);

            if (bccomp($montoNorm, $saldoDisponibleGasto, 2) > 0) {
                throw new ConflictoGastoExcepcion(
                    "El monto a aplicar [S/ {$montoNorm}] supera el saldo pendiente [S/ {$saldoDisponibleGasto}] del gasto [{$gasto->obtenerCodigo()}]."
                );
            }

            // 3. Crear aplicación formal M:N
            $codigoApl = $this->pagoRepo->generarSiguienteCodigoAplicacion();
            $apl = new GastoAplicacionPago(
                null,
                $codigoApl,
                $gastoId,
                $pagoEgresoId,
                $montoNorm,
                GastoAplicacionPago::ESTADO_ACTIVO,
                null,
                null,
                null,
                $actorIdFinal
            );
            $aplId = $this->pagoRepo->crearAplicacion($apl);

            $this->pdo->commit();

            return new GastoAplicacionPago(
                $aplId,
                $codigoApl,
                $gastoId,
                $pagoEgresoId,
                $montoNorm,
                GastoAplicacionPago::ESTADO_ACTIVO,
                null,
                null,
                null,
                $actorIdFinal,
                date('Y-m-d H:i:s')
            );
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Reversa un Pago de Egreso, liberando el saldo de los gastos y restituyendo los fondos en tesorería.
     */
    public function reversarPagoEgreso(int $pagoEgresoId, ?int $actorId = null, string $motivo = ''): bool
    {
        $actorIdFinal = $this->resolverActorId($actorId);

        if (trim($motivo) === '') {
            throw new ValidacionGastoExcepcion('Es obligatorio ingresar un motivo de reversión del pago.');
        }

        $this->pdo->beginTransaction();
        try {
            $pago = $this->pagoRepo->obtenerPagoEgresoPorId($pagoEgresoId, true);
            if ($pago === null) {
                throw new PagoEgresoNoEncontradoExcepcion("El pago de egreso [{$pagoEgresoId}] no existe.");
            }

            if ($pago->estaReversado()) {
                $this->pdo->commit();
                return true;
            }

            // Reversar el Pago
            $this->pagoRepo->actualizarEstadoPagoEgreso($pagoEgresoId, PagoEgreso::ESTADO_REVERSADO, $motivo, $actorIdFinal);

            // Reversar todas las aplicaciones activas
            $aplicaciones = $this->pagoRepo->listarAplicacionesPorPago($pagoEgresoId);
            foreach ($aplicaciones as $apl) {
                if ($apl->estaActivo() && $apl->obtenerId() !== null) {
                    $this->pagoRepo->actualizarEstadoAplicacion($apl->obtenerId(), GastoAplicacionPago::ESTADO_REVERSADO, $motivo, $actorIdFinal);
                }
            }

            // Restitución en Tesorería
            if ($pago->esEnEfectivo() && $pago->obtenerSesionCajaId() !== null) {
                // Registrar movimiento de restitución de efectivo en caja
                $sesion = $this->cajaRepo->obtenerSesionCaja($pago->obtenerSesionCajaId(), true);
                if ($sesion !== null && $sesion->estaAbierta()) {
                    $movAjuste = new MovimientoCaja(
                        null,
                        $pago->obtenerSesionCajaId(),
                        'INGRESO_AJUSTE',
                        null,
                        null,
                        $pago->obtenerMontoTotal(),
                        'PEN',
                        "Restitución por reversión de pago {$pago->obtenerCodigo()}: {$motivo}",
                        $actorIdFinal
                    );
                    $this->cajaRepo->crearMovimientoCaja($movAjuste);
                    $this->cajaRepo->actualizarTotalesSesionCaja($pago->obtenerSesionCajaId(), $pago->obtenerMontoTotal(), '0.00');
                }
            } elseif ($pago->esBancario() && $pago->obtenerCuentaBancariaId() !== null) {
                $movBanco = new MovimientoBancario(
                    null,
                    $pago->obtenerCuentaBancariaId(),
                    'INGRESO_TRANSFERENCIA',
                    null,
                    null,
                    $pago->obtenerMontoTotal(),
                    'PEN',
                    'REV-' . $pago->obtenerCodigo(),
                    "Restitución bancaria por reversión de pago {$pago->obtenerCodigo()}: {$motivo}",
                    date('Y-m-d'),
                    $actorIdFinal
                );
                $this->cajaRepo->crearMovimientoBancario($movBanco);
            }

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
