<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

use CamargoPMS\Excepciones\EstadiaNoEncontradaExcepcion;
use CamargoPMS\Excepciones\EstadoEstadiaInvalidoExcepcion;
use CamargoPMS\Excepciones\EstadoReservaInvalidoExcepcion;
use CamargoPMS\Excepciones\EstadoServicioInvalidoExcepcion;
use CamargoPMS\Excepciones\ProveedorNoEncontradoExcepcion;
use CamargoPMS\Excepciones\ProveedorNoHabilitadoExcepcion;
use CamargoPMS\Excepciones\ReservaNoEncontradaExcepcion;
use CamargoPMS\Excepciones\ServicioContratadoNoEncontradoExcepcion;
use CamargoPMS\Excepciones\ServicioNoEncontradoExcepcion;
use CamargoPMS\Excepciones\ValidacionExcepcion;
use CamargoPMS\Modelos\AccionAuditoria;
use CamargoPMS\Modelos\ServicioContratado;
use CamargoPMS\Modelos\ServicioTraslado;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Repositorios\EstadiaRepositorio;
use CamargoPMS\Repositorios\ProveedorRepositorio;
use CamargoPMS\Repositorios\ReservaRepositorio;
use CamargoPMS\Repositorios\ServicioContratadoRepositorio;
use CamargoPMS\Repositorios\ServicioRepositorio;
use PDO;
use Throwable;

/**
 * Servicio transaccional central para la contratación, ejecución y cancelación
 * de servicios adicionales, consumos imputados y traslados (SERVICIOS-1).
 *
 * Principios vinculantes:
 * - RESERVA != ESTADÍA != ARRENDAMIENTO != SERVICIO != PROVEEDOR.
 * - Snapshots inmutables: precio, costo, modalidad, categoría y descripción congelados al contratar (D-010/D-069).
 * - reserva_id obligatorio (NOT NULL): la reserva comercial es el titular del folio.
 * - Integridad de estadía: si se imputa a una habitación, debe pertenecer a la reserva titular.
 * - Operación interna: es_operacion_interna = 1 y proveedor_id = NULL.
 * - Operación externa: es_operacion_interna = 0 y proveedor_id = NOT NULL.
 * - Ciclo de vida: SOLICITADO -> CONFIRMADO -> EJECUTADO / CANCELADO. Prohibida cancelación de EJECUTADO.
 * - Cero DELETE sobre registros de consumos o traslados.
 */
class ServicioContratadoServicio
{
    private PDO $pdo;
    private ServicioContratadoRepositorio $contratadoRepo;
    private ServicioRepositorio $servicioRepo;
    private ProveedorRepositorio $proveedorRepo;
    private ReservaRepositorio $reservaRepo;
    private EstadiaRepositorio $estadiaRepo;
    private AuditoriaServicio $auditoriaServicio;

    public function __construct(
        ?PDO $pdo = null,
        ?ServicioContratadoRepositorio $contratadoRepo = null,
        ?ServicioRepositorio $servicioRepo = null,
        ?ProveedorRepositorio $proveedorRepo = null,
        ?ReservaRepositorio $reservaRepo = null,
        ?EstadiaRepositorio $estadiaRepo = null,
        ?AuditoriaServicio $auditoriaServicio = null
    ) {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->contratadoRepo = $contratadoRepo ?? new ServicioContratadoRepositorio($this->pdo);
        $this->servicioRepo = $servicioRepo ?? new ServicioRepositorio($this->pdo);
        $this->proveedorRepo = $proveedorRepo ?? new ProveedorRepositorio($this->pdo);
        $this->reservaRepo = $reservaRepo ?? new ReservaRepositorio($this->pdo);
        $this->estadiaRepo = $estadiaRepo ?? new EstadiaRepositorio($this->pdo);
        $this->auditoriaServicio = $auditoriaServicio ?? new AuditoriaServicio($this->pdo);
    }

    public function obtenerPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Obtiene un servicio contratado por su ID.
     */
    public function obtenerPorId(int $id): ServicioContratado
    {
        $contratado = $this->contratadoRepo->buscarPorId($id);
        if (!$contratado) {
            throw new ServicioContratadoNoEncontradoExcepcion("No se encontró el servicio contratado con ID {$id}.");
        }
        return $contratado;
    }

    /**
     * Lista servicios contratados con filtros.
     *
     * @param array<string, mixed> $filtros
     * @return array<int, ServicioContratado>
     */
    public function listar(array $filtros = []): array
    {
        return $this->contratadoRepo->listar($filtros);
    }

    /**
     * Lista servicios contratados de una reserva.
     *
     * @return array<int, ServicioContratado>
     */
    public function listarPorReserva(int $reservaId): array
    {
        return $this->contratadoRepo->listarPorReserva($reservaId);
    }

    /**
     * Lista servicios contratados imputados a una estadía.
     *
     * @return array<int, ServicioContratado>
     */
    public function listarPorEstadia(int $estadiaId): array
    {
        return $this->contratadoRepo->listarPorEstadia($estadiaId);
    }

    /**
     * Registra la contratación o consumo de un servicio para una reserva (y opcionalmente una estadía).
     *
     * @param array<string, mixed> $datos
     */
    public function contratarServicio(array $datos, int $actorId): ServicioContratado
    {
        $this->pdo->beginTransaction();
        try {
            // 1. Validar Reserva Comercial Titular (OBLIGATORIA)
            $reservaId = (int) ($datos['reserva_id'] ?? 0);
            if ($reservaId <= 0) {
                throw new ValidacionExcepcion('La reserva comercial titular es obligatoria para registrar un servicio contratado.');
            }

            $reserva = $this->reservaRepo->buscarPorId($reservaId, false);
            if (!$reserva) {
                throw new ReservaNoEncontradaExcepcion("La reserva ID {$reservaId} no existe.");
            }

            if ($reserva->obtenerEstado() === 'CANCELADA') {
                throw new EstadoReservaInvalidoExcepcion(
                    $reserva->obtenerEstado(),
                    'CONTRATAR_SERVICIO',
                    "No es posible contratar servicios para una reserva que se encuentra CANCELADA."
                );
            }

            // 2. Validar Estadía Física (OPCIONAL, pero con estricta coherencia)
            $estadiaId = isset($datos['estadia_id']) && $datos['estadia_id'] !== '' ? (int) $datos['estadia_id'] : null;
            if ($estadiaId !== null) {
                $estadia = $this->estadiaRepo->buscarPorId($estadiaId, false);
                if (!$estadia) {
                    throw new EstadiaNoEncontradaExcepcion("La estadía ID {$estadiaId} no existe.");
                }

                if ($estadia->obtenerReservaId() !== $reservaId) {
                    throw new ValidacionExcepcion(
                        "La estadía ID {$estadiaId} (código {$estadia->obtenerCodigo()}) no pertenece a la reserva ID {$reservaId}."
                    );
                }

                if ($estadia->estaAnulada()) {
                    throw new EstadoEstadiaInvalidoExcepcion(
                        $estadia->obtenerEstado(),
                        'IMPUTAR_CONSUMO',
                        "No es posible imputar consumos a una estadía que ha sido ANULADA."
                    );
                }
            }

            // 3. Validar Concepto de Catálogo
            $servicioId = (int) ($datos['servicio_id'] ?? 0);
            if ($servicioId <= 0) {
                throw new ValidacionExcepcion('Debe seleccionar un servicio válido del catálogo.');
            }

            $servicio = $this->servicioRepo->buscarPorId($servicioId, true);
            if (!$servicio) {
                throw new ServicioNoEncontradoExcepcion("El servicio ID {$servicioId} no existe en el catálogo.");
            }
            if (!$servicio->esActivo()) {
                throw new ValidacionExcepcion("El servicio '{$servicio->obtenerNombre()}' se encuentra inactivo en el catálogo.");
            }

            $categoria = $this->servicioRepo->buscarCategoriaPorId($servicio->obtenerCategoriaId());
            $modalidad = $this->servicioRepo->buscarModalidadPorId($servicio->obtenerModalidadCobroId());

            $catCodigoSnapshot = $categoria ? $categoria->obtenerCodigo() : 'OTROS';
            $modCodigoSnapshot = $modalidad ? $modalidad->obtenerCodigo() : 'FIJO';
            $descSnapshot = $servicio->obtenerNombre();

            // 4. Operación Interna vs Proveedor Externo
            $esOperacionInterna = isset($datos['es_operacion_interna'])
                ? !empty($datos['es_operacion_interna'])
                : (empty($datos['proveedor_id']) && $servicio->esOperacionInternaHabitual());

            $proveedorId = null;
            $costoUnitario = '0.00';

            if ($esOperacionInterna) {
                // Operación interna: proveedor_id forzado a NULL
                $proveedorId = null;
                $costoUnitario = isset($datos['costo_unitario']) && $datos['costo_unitario'] !== ''
                    ? (string) $datos['costo_unitario']
                    : $servicio->obtenerCostoReferencial();
            } else {
                // Operación externa: proveedor_id obligatorio
                $proveedorId = isset($datos['proveedor_id']) && $datos['proveedor_id'] !== '' ? (int) $datos['proveedor_id'] : null;

                // Si no se envió proveedor_id pero hay proveedor preferente configurado en el servicio
                if ($proveedorId === null || $proveedorId <= 0) {
                    $provPreferente = $this->servicioRepo->obtenerProveedorPreferente($servicioId);
                    if ($provPreferente) {
                        $proveedorId = $provPreferente->obtenerProveedorId();
                        $costoUnitario = $provPreferente->obtenerCostoPactado() ?? $servicio->obtenerCostoReferencial();
                    }
                }

                if ($proveedorId === null || $proveedorId <= 0) {
                    throw new ValidacionExcepcion('Para un servicio externo debe especificarse un proveedor homologado activo.');
                }

                $proveedor = $this->proveedorRepo->buscarPorId($proveedorId);
                if (!$proveedor) {
                    throw new ProveedorNoEncontradoExcepcion("El proveedor ID {$proveedorId} no existe.");
                }
                if (!$proveedor->esActivo()) {
                    throw new ProveedorNoHabilitadoExcepcion("El proveedor '{$proveedor->obtenerRazonSocial()}' se encuentra inactivo.");
                }

                if (isset($datos['costo_unitario']) && $datos['costo_unitario'] !== '') {
                    $costoUnitario = (string) $datos['costo_unitario'];
                } elseif ($costoUnitario === '0.00') {
                    $costoUnitario = $servicio->obtenerCostoReferencial();
                }
            }

            // 5. Cálculos Matemáticos Exactos (BCMath / D-069)
            $cantidad = (float) ($datos['cantidad'] ?? 1.0);
            if ($cantidad <= 0.0) {
                throw new ValidacionExcepcion('La cantidad del servicio debe ser mayor a 0.');
            }
            $cantidadStr = number_format($cantidad, 2, '.', '');

            $precioUnitario = isset($datos['precio_unitario']) && $datos['precio_unitario'] !== ''
                ? (float) $datos['precio_unitario']
                : (float) $servicio->obtenerPrecioVentaReferencial();

            if ($precioUnitario < 0.0) {
                throw new ValidacionExcepcion('El precio unitario no puede ser negativo.');
            }
            $precioUnitarioStr = number_format($precioUnitario, 2, '.', '');

            $costoUnitarioFloat = (float) $costoUnitario;
            if ($costoUnitarioFloat < 0.0) {
                throw new ValidacionExcepcion('El costo unitario no puede ser negativo.');
            }
            $costoUnitarioStr = number_format($costoUnitarioFloat, 2, '.', '');

            $subtotalStr = bcmul($precioUnitarioStr, $cantidadStr, 2);
            $costoTotalStr = bcmul($costoUnitarioStr, $cantidadStr, 2);
            $tasaImpuestoStr = '0.0000';
            $impuestoTotalStr = '0.00';
            $totalStr = $subtotalStr;
            $monedaCodigo = $servicio->obtenerMonedaCodigo();

            // 6. Fechas y Ciclo de Vida
            $fechaServicio = trim((string) ($datos['fecha_servicio'] ?? ''));
            if ($fechaServicio === '') {
                $fechaServicio = date('Y-m-d');
            } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaServicio)) {
                throw new ValidacionExcepcion("La fecha del servicio debe tener formato YYYY-MM-DD.");
            }

            $horaServicio = isset($datos['hora_servicio']) && trim((string) $datos['hora_servicio']) !== ''
                ? trim((string) $datos['hora_servicio'])
                : null;

            $estadoInicial = strtoupper(trim((string) ($datos['estado'] ?? 'SOLICITADO')));
            if (!in_array($estadoInicial, ['SOLICITADO', 'CONFIRMADO'], true)) {
                $estadoInicial = 'SOLICITADO';
            }

            // 7. Generar Código Unico
            $codigo = $this->contratadoRepo->generarSiguienteCodigo();

            $actorIdFinal = $this->resolverActorId($actorId);

            $contratado = new ServicioContratado(
                id: null,
                codigo: $codigo,
                reservaId: $reservaId,
                estadiaId: $estadiaId,
                servicioId: $servicioId,
                proveedorId: $proveedorId,
                descripcionServicioSnapshot: $descSnapshot,
                categoriaCodigoSnapshot: $catCodigoSnapshot,
                modalidadCobroCodigoSnapshot: $modCodigoSnapshot,
                esOperacionInterna: $esOperacionInterna,
                cantidad: $cantidadStr,
                precioUnitario: $precioUnitarioStr,
                costoUnitario: $costoUnitarioStr,
                subtotal: $subtotalStr,
                tasaImpuesto: $tasaImpuestoStr,
                impuestoTotal: $impuestoTotalStr,
                total: $totalStr,
                costoTotal: $costoTotalStr,
                monedaCodigo: $monedaCodigo,
                estado: $estadoInicial,
                fechaServicio: $fechaServicio,
                horaServicio: $horaServicio,
                observaciones: isset($datos['observaciones']) ? trim((string) $datos['observaciones']) : null,
                solicitadoPorActorId: $actorIdFinal
            );

            $id = $this->contratadoRepo->crear($contratado);

            // 8. Extensión Logística de Traslados (1:1)
            $esTraslado = $servicio->requiereTrasladoDetalle() || $catCodigoSnapshot === 'TRASLADOS' || !empty($datos['traslado']);
            if ($esTraslado && !empty($datos['traslado']) && is_array($datos['traslado'])) {
                $datosT = $datos['traslado'];
                $origen = trim((string) ($datosT['origen'] ?? ''));
                $destino = trim((string) ($datosT['destino'] ?? ''));
                $fechaHoraRecogida = trim((string) ($datosT['fecha_hora_recogida'] ?? ''));

                if ($origen === '' || $destino === '') {
                    throw new ValidacionExcepcion('Para un servicio de traslado, el origen y destino son obligatorios.');
                }
                if ($fechaHoraRecogida === '') {
                    $fechaHoraRecogida = $fechaServicio . ' ' . ($horaServicio ?? '12:00:00');
                }

                $traslado = new ServicioTraslado(
                    id: null,
                    servicioContratadoId: $id,
                    tipoTraslado: strtoupper(trim((string) ($datosT['tipo_traslado'] ?? 'LLEGADA'))),
                    origen: $origen,
                    destino: $destino,
                    fechaHoraRecogida: $fechaHoraRecogida,
                    aerolineaEmpresa: isset($datosT['aerolinea_empresa']) ? trim((string) $datosT['aerolinea_empresa']) : null,
                    numeroVueloViaje: isset($datosT['numero_vuelo_viaje']) ? trim((string) $datosT['numero_vuelo_viaje']) : null,
                    cantidadPasajeros: (int) ($datosT['cantidad_pasajeros'] ?? 1),
                    cantidadMaletas: (int) ($datosT['cantidad_maletas'] ?? 0),
                    datosConductorVehiculo: isset($datosT['datos_conductor_vehiculo']) ? trim((string) $datosT['datos_conductor_vehiculo']) : null,
                    instruccionesRecogida: isset($datosT['instrucciones_recogida']) ? trim((string) $datosT['instrucciones_recogida']) : null
                );

                $this->contratadoRepo->crearTraslado($traslado);
            }

            // 9. Trazabilidad de Auditoría D-061
            $this->auditoriaServicio->registrar(
                accion: AccionAuditoria::CREAR,
                modulo: 'servicios',
                entidad: 'servicios_contratados',
                entidadId: (string) $id,
                descripcion: "Servicio contratado '{$codigo}': {$descSnapshot} (Total: {$monedaCodigo} {$totalStr}) para Reserva ID {$reservaId}",
                valoresAnteriores: null,
                valoresNuevos: array_merge($contratado->haciaArreglo(), ['id' => $id]),
                contexto: null,
                actor: $actorId,
                usuarioId: null,
                correlacionId: null,
                pdoTransaccional: $this->pdo
            );

            $this->pdo->commit();
            return $this->obtenerPorId($id);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Confirma un servicio contratado en estado SOLICITADO.
     */
    public function confirmarServicio(int $id, int $actorId): ServicioContratado
    {
        $this->pdo->beginTransaction();
        try {
            $servicio = $this->contratadoRepo->buscarPorIdParaActualizar($id);
            if (!$servicio) {
                throw new ServicioContratadoNoEncontradoExcepcion("El servicio contratado ID {$id} no existe.");
            }

            if ($servicio->esConfirmado()) {
                $this->pdo->commit();
                return $servicio;
            }

            if ($servicio->esEjecutado() || $servicio->esCancelado()) {
                throw new EstadoServicioInvalidoExcepcion(
                    $servicio->obtenerEstado(),
                    'CONFIRMAR',
                    "No se puede confirmar un servicio en estado '{$servicio->obtenerEstado()}'."
                );
            }

            $actorIdFinal = $this->resolverActorId($actorId);
            $this->contratadoRepo->actualizarEstado($id, 'CONFIRMADO', $actorIdFinal);

            $this->auditoriaServicio->registrar(
                accion: AccionAuditoria::EDITAR,
                modulo: 'servicios',
                entidad: 'servicios_contratados',
                entidadId: (string) $id,
                descripcion: "Servicio contratado '{$servicio->obtenerCodigo()}' confirmado.",
                valoresAnteriores: ['estado' => $servicio->obtenerEstado()],
                valoresNuevos: ['estado' => 'CONFIRMADO'],
                contexto: null,
                actor: $actorIdFinal,
                usuarioId: null,
                correlacionId: null,
                pdoTransaccional: $this->pdo
            );

            $cuentaFolioServicio = new CuentaFolioServicio($this->pdo);
            $cuentaFolioServicio->sincronizarCargoServicioContratado($id, 'CONFIRMADO', $actorIdFinal);

            $this->pdo->commit();
            return $this->obtenerPorId($id);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Marca un servicio como EJECUTADO / entregado físicamente.
     */
    public function ejecutarServicio(int $id, int $actorId): ServicioContratado
    {
        $this->pdo->beginTransaction();
        try {
            $servicio = $this->contratadoRepo->buscarPorIdParaActualizar($id);
            if (!$servicio) {
                throw new ServicioContratadoNoEncontradoExcepcion("El servicio contratado ID {$id} no existe.");
            }

            if ($servicio->esEjecutado()) {
                $this->pdo->commit();
                return $servicio;
            }

            if ($servicio->esCancelado()) {
                throw new EstadoServicioInvalidoExcepcion(
                    $servicio->obtenerEstado(),
                    'EJECUTAR',
                    "No se puede ejecutar un servicio que ha sido CANCELADO."
                );
            }

            $actorIdFinal = $this->resolverActorId($actorId);
            $this->contratadoRepo->actualizarEstado($id, 'EJECUTADO', $actorIdFinal);

            $this->auditoriaServicio->registrar(
                accion: AccionAuditoria::EDITAR,
                modulo: 'servicios',
                entidad: 'servicios_contratados',
                entidadId: (string) $id,
                descripcion: "Servicio contratado '{$servicio->obtenerCodigo()}' marcado como EJECUTADO físicamente.",
                valoresAnteriores: ['estado' => $servicio->obtenerEstado()],
                valoresNuevos: ['estado' => 'EJECUTADO'],
                contexto: null,
                actor: $actorIdFinal,
                usuarioId: null,
                correlacionId: null,
                pdoTransaccional: $this->pdo
            );

            $cuentaFolioServicio = new CuentaFolioServicio($this->pdo);
            $cuentaFolioServicio->sincronizarCargoServicioContratado($id, 'EJECUTADO', $actorIdFinal);

            $this->pdo->commit();
            return $this->obtenerPorId($id);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Cancela la contratación de un servicio con motivo justificado.
     *
     * Principio vinculado:
     * - PROHIBIDO cancelar un servicio que ya ha sido EJECUTADO físicamente.
     */
    public function cancelarServicio(int $id, string $motivo, int $actorId): ServicioContratado
    {
        $motivoLimpio = trim($motivo);
        if ($motivoLimpio === '') {
            throw new ValidacionExcepcion('Debe indicar un motivo justificado para la cancelación del servicio.');
        }

        $this->pdo->beginTransaction();
        try {
            $servicio = $this->contratadoRepo->buscarPorIdParaActualizar($id);
            if (!$servicio) {
                throw new ServicioContratadoNoEncontradoExcepcion("El servicio contratado ID {$id} no existe.");
            }

            if ($servicio->esEjecutado()) {
                throw new EstadoServicioInvalidoExcepcion(
                    'EJECUTADO',
                    'CANCELAR',
                    "No es posible cancelar un servicio que ya ha sido EJECUTADO físicamente."
                );
            }

            if ($servicio->esCancelado()) {
                throw new EstadoServicioInvalidoExcepcion(
                    'CANCELADO',
                    'CANCELAR',
                    "El servicio contratado ya se encuentra cancelado."
                );
            }

            $actorIdFinal = $this->resolverActorId($actorId);
            $this->contratadoRepo->actualizarEstado($id, 'CANCELADO', $actorIdFinal, $motivoLimpio);

            $this->auditoriaServicio->registrar(
                accion: AccionAuditoria::ANULAR,
                modulo: 'servicios',
                entidad: 'servicios_contratados',
                entidadId: (string) $id,
                descripcion: "Servicio contratado '{$servicio->obtenerCodigo()}' cancelado. Motivo: {$motivoLimpio}",
                valoresAnteriores: ['estado' => $servicio->obtenerEstado()],
                valoresNuevos: [
                    'estado' => 'CANCELADO',
                    'motivo_cancelacion' => $motivoLimpio,
                ],
                contexto: null,
                actor: $actorIdFinal,
                usuarioId: null,
                correlacionId: null,
                pdoTransaccional: $this->pdo
            );

            $cuentaFolioServicio = new CuentaFolioServicio($this->pdo);
            $cuentaFolioServicio->sincronizarCargoServicioContratado($id, 'CANCELADO', $actorIdFinal);

            $this->pdo->commit();
            return $this->obtenerPorId($id);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Resuelve canónicamente el ID de actor ejecutor (D-061: ACTOR != USUARIO).
     */
    public function resolverActorId(?int $actorOUsuarioId = null): int
    {
        if ($actorOUsuarioId !== null && $actorOUsuarioId > 0) {
            $stmt = $this->pdo->prepare('SELECT id FROM actores WHERE id = :id LIMIT 1');
            $stmt->bindValue(':id', $actorOUsuarioId, PDO::PARAM_INT);
            $stmt->execute();
            if ($stmt->fetchColumn()) {
                return $actorOUsuarioId;
            }

            try {
                $actor = $this->auditoriaServicio->obtenerOAsegurarActorUsuario($actorOUsuarioId, $this->pdo);
                if ($actor && $actor->obtenerId() !== null) {
                    return (int) $actor->obtenerId();
                }
            } catch (Throwable) {
                // Fallback defensivo
            }
        }

        $actorActual = $this->auditoriaServicio->obtenerActorActual($this->pdo);
        return (int) ($actorActual->obtenerId() ?? 1);
    }
}
