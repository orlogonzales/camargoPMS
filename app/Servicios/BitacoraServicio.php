<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

use CamargoPMS\Excepciones\EntidadNoEncontradaExcepcion;
use CamargoPMS\Excepciones\OperacionInvalidaExcepcion;
use CamargoPMS\Modelos\BitacoraEntrada;
use CamargoPMS\Modelos\BitacoraSeguimiento;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Repositorios\BitacoraRepositorio;
use InvalidArgumentException;
use PDO;
use Throwable;

/**
 * Servicio de dominio para el Libro de Guardia y Bitácora Operacional.
 *
 * Implementa las reglas y axiomas vinculantes de D-089 (BITÁCORA-1):
 * - BITÁCORA ≠ AUDITORÍA TÉCNICA (D-061) ≠ TURNO CAJA ≠ MANTENIMIENTO ≠ HOUSEKEEPING.
 * - Inmutabilidad del relato original de guardia: no existe edición directa de contenido.
 * - Toda aclaración o corrección se realiza como enmienda append-only.
 * - ANULAR ≠ DELETE: las anulaciones son supervisadas, justificadas y preservan el historial.
 * - Las operaciones de cambio de estado y resolución operan bajo transacciones PDO y emiten
 *   trazabilidad transversal a través de AuditoriaServicio (D-061).
 */
class BitacoraServicio
{
    private PDO $pdo;
    private BitacoraRepositorio $bitacoraRepo;
    private AuditoriaServicio $auditoriaServicio;

    public function __construct(
        ?PDO $pdo = null,
        ?BitacoraRepositorio $bitacoraRepo = null,
        ?AuditoriaServicio $auditoriaServicio = null
    ) {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->bitacoraRepo = $bitacoraRepo ?? new BitacoraRepositorio($this->pdo);
        $this->auditoriaServicio = $auditoriaServicio ?? new AuditoriaServicio($this->pdo);
    }

    /**
     * Registra una nueva entrada u orden de guardia en la bitácora operativa.
     *
     * @param array<string, mixed> $datos
     * @param int $usuarioCreadorId
     * @param int|null $propiedadContextoId
     * @return BitacoraEntrada
     * @throws InvalidArgumentException
     * @throws Throwable
     */
    public function registrarEntrada(
        array $datos,
        int $usuarioCreadorId,
        ?int $propiedadContextoId = null
    ): BitacoraEntrada {
        if ($usuarioCreadorId <= 0) {
            throw new InvalidArgumentException('El identificador del usuario autor es obligatorio.');
        }

        $propiedadId = (int) ($datos['propiedad_id'] ?? $propiedadContextoId ?? 0);
        if ($propiedadId <= 0) {
            throw new InvalidArgumentException('Debe asociar la entrada a una propiedad física válida.');
        }

        $titulo = trim((string) ($datos['titulo'] ?? ''));
        if ($titulo === '') {
            throw new InvalidArgumentException('El título de la entrada de guardia no puede estar vacío.');
        }
        if (mb_strlen($titulo) > 200) {
            throw new InvalidArgumentException('El título de la entrada no puede superar los 200 caracteres.');
        }

        $contenido = trim((string) ($datos['contenido'] ?? ''));
        if ($contenido === '') {
            throw new InvalidArgumentException('El relato o contenido de la novedad no puede estar vacío.');
        }

        $tipo = strtoupper(trim((string) ($datos['tipo'] ?? BitacoraEntrada::TIPO_NOVEDAD)));
        if (!in_array($tipo, BitacoraEntrada::TIPOS_VALIDOS, true)) {
            throw new InvalidArgumentException(sprintf('Tipo de bitácora no válido: "%s"', $tipo));
        }

        $prioridad = strtoupper(trim((string) ($datos['prioridad'] ?? BitacoraEntrada::PRIORIDAD_MEDIA)));
        if (!in_array($prioridad, BitacoraEntrada::PRIORIDADES_VALIDAS, true)) {
            throw new InvalidArgumentException(sprintf('Prioridad de bitácora no válida: "%s"', $prioridad));
        }

        $turno = strtoupper(trim((string) ($datos['turno'] ?? BitacoraEntrada::TURNO_GENERAL)));
        if (!in_array($turno, BitacoraEntrada::TURNOS_VALIDOS, true)) {
            throw new InvalidArgumentException(sprintf('Turno de guardia no válido: "%s"', $turno));
        }

        $fechaOperativa = trim((string) ($datos['fecha_operativa'] ?? date('Y-m-d')));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaOperativa)) {
            $fechaOperativa = date('Y-m-d');
        }

        // Estado inicial coherente con el tipo de entrada
        $estadoInicial = BitacoraEntrada::ESTADO_REGISTRADA;
        if (in_array($tipo, [BitacoraEntrada::TIPO_CONSIGNA, BitacoraEntrada::TIPO_INCIDENCIA], true)) {
            $estadoInicial = BitacoraEntrada::ESTADO_PENDIENTE;
        }

        $unidadId = !empty($datos['unidad_id']) ? (int) $datos['unidad_id'] : null;
        $reservaId = !empty($datos['reserva_id']) ? (int) $datos['reserva_id'] : null;
        $estadiaId = !empty($datos['estadia_id']) ? (int) $datos['estadia_id'] : null;
        $mantenimientoId = !empty($datos['mantenimiento_id']) ? (int) $datos['mantenimiento_id'] : null;
        $sesionCajaId = !empty($datos['sesion_caja_id']) ? (int) $datos['sesion_caja_id'] : null;

        $entrada = new BitacoraEntrada(
            id: null,
            propiedadId: $propiedadId,
            usuarioCreadorId: $usuarioCreadorId,
            tipo: $tipo,
            prioridad: $prioridad,
            titulo: $titulo,
            contenido: $contenido,
            turno: $turno,
            fechaOperativa: $fechaOperativa,
            estado: $estadoInicial,
            unidadId: $unidadId,
            reservaId: $reservaId,
            estadiaId: $estadiaId,
            mantenimientoId: $mantenimientoId,
            sesionCajaId: $sesionCajaId
        );

        $this->pdo->beginTransaction();
        try {
            $entradaPersistida = $this->bitacoraRepo->insertarEntrada($entrada);
            $entradaId = (int) $entradaPersistida->obtenerId();

            // Auditoría técnica D-061
            $this->auditoriaServicio->registrar(
                accion: 'CREAR',
                modulo: 'operaciones',
                entidad: 'bitacora_entrada',
                entidadId: $entradaId,
                descripcion: sprintf('Registro de entrada en bitácora [%s]: "%s"', $tipo, $titulo),
                valoresAnteriores: null,
                valoresNuevos: $entradaPersistida->toArray(),
                contexto: ['propiedad_id' => $propiedadId, 'turno' => $turno],
                usuarioId: $usuarioCreadorId,
                pdoTransaccional: $this->pdo
            );

            $this->pdo->commit();
            return $entradaPersistida;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Agrega un seguimiento, nota o enmienda cronológica append-only a una entrada.
     *
     * @param int $entradaId
     * @param int $usuarioId
     * @param string $contenido
     * @param string $tipoEvento
     * @return BitacoraSeguimiento
     * @throws EntidadNoEncontradaExcepcion
     * @throws OperacionInvalidaExcepcion
     * @throws InvalidArgumentException
     * @throws Throwable
     */
    public function agregarSeguimiento(
        int $entradaId,
        int $usuarioId,
        string $contenido,
        string $tipoEvento = BitacoraSeguimiento::TIPO_COMENTARIO
    ): BitacoraSeguimiento {
        if ($entradaId <= 0) {
            throw new InvalidArgumentException('ID de entrada no válido.');
        }
        if ($usuarioId <= 0) {
            throw new InvalidArgumentException('ID de usuario no válido.');
        }

        $contenidoLimpio = trim($contenido);
        if ($contenidoLimpio === '') {
            throw new InvalidArgumentException('El contenido del seguimiento no puede estar vacío.');
        }

        $tipoEvento = strtoupper(trim($tipoEvento));
        if (!in_array($tipoEvento, BitacoraSeguimiento::TIPOS_EVENTO_VALIDOS, true)) {
            throw new InvalidArgumentException(sprintf('Tipo de evento no válido: "%s"', $tipoEvento));
        }

        $entrada = $this->bitacoraRepo->buscarPorId($entradaId, false);
        if ($entrada === null) {
            throw new EntidadNoEncontradaExcepcion(sprintf('No existe la entrada de bitácora #%d', $entradaId));
        }

        if ($entrada->estaAnulada()) {
            throw new OperacionInvalidaExcepcion('No se pueden añadir notas ni enmiendas a una entrada anulada.');
        }

        $seguimiento = new BitacoraSeguimiento(
            id: null,
            entradaId: $entradaId,
            usuarioId: $usuarioId,
            tipoEvento: $tipoEvento,
            contenido: $contenidoLimpio,
            estadoAnterior: $entrada->obtenerEstado(),
            estadoNuevo: $entrada->obtenerEstado()
        );

        $this->pdo->beginTransaction();
        try {
            $seguimientoPersistido = $this->bitacoraRepo->insertarSeguimiento($seguimiento);

            $this->auditoriaServicio->registrar(
                accion: 'SEGUIMIENTO',
                modulo: 'operaciones',
                entidad: 'bitacora_seguimiento',
                entidadId: $seguimientoPersistido->obtenerId(),
                descripcion: sprintf('Seguimiento [%s] en entrada de bitácora #%d', $tipoEvento, $entradaId),
                valoresAnteriores: null,
                valoresNuevos: $seguimientoPersistido->toArray(),
                contexto: ['entrada_id' => $entradaId],
                usuarioId: $usuarioId,
                pdoTransaccional: $this->pdo
            );

            $this->pdo->commit();
            return $seguimientoPersistido;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Transiciona el estado operativo de una entrada (ej. REGISTRADA -> EN_PROCESO o PENDIENTE).
     */
    public function cambiarEstado(
        int $entradaId,
        int $usuarioId,
        string $nuevoEstado,
        ?string $nota = null
    ): BitacoraEntrada {
        $nuevoEstado = strtoupper(trim($nuevoEstado));
        if (!in_array($nuevoEstado, [BitacoraEntrada::ESTADO_PENDIENTE, BitacoraEntrada::ESTADO_EN_PROCESO, BitacoraEntrada::ESTADO_REGISTRADA], true)) {
            throw new InvalidArgumentException(sprintf('Para resolver o anular use los métodos dedicados. Estado solicitado no válido: "%s"', $nuevoEstado));
        }

        $entrada = $this->bitacoraRepo->buscarPorId($entradaId, false);
        if ($entrada === null) {
            throw new EntidadNoEncontradaExcepcion(sprintf('No existe la entrada de bitácora #%d', $entradaId));
        }

        if ($entrada->estaAnulada()) {
            throw new OperacionInvalidaExcepcion('No se puede modificar el estado de una entrada anulada.');
        }

        $estadoAnterior = $entrada->obtenerEstado();
        if ($estadoAnterior === $nuevoEstado) {
            return $entrada;
        }

        $this->pdo->beginTransaction();
        try {
            $this->bitacoraRepo->actualizarEstado($entradaId, $nuevoEstado);

            $textoSeguimiento = sprintf(
                'Cambio de estado operativo: de %s a %s%s',
                $estadoAnterior,
                $nuevoEstado,
                $nota ? ' — Nota: ' . trim($nota) : ''
            );

            $seguimiento = new BitacoraSeguimiento(
                id: null,
                entradaId: $entradaId,
                usuarioId: $usuarioId,
                tipoEvento: BitacoraSeguimiento::TIPO_CAMBIO_ESTADO,
                contenido: $textoSeguimiento,
                estadoAnterior: $estadoAnterior,
                estadoNuevo: $nuevoEstado
            );
            $this->bitacoraRepo->insertarSeguimiento($seguimiento);

            $this->auditoriaServicio->registrar(
                accion: 'CAMBIO_ESTADO',
                modulo: 'operaciones',
                entidad: 'bitacora_entrada',
                entidadId: $entradaId,
                descripcion: sprintf('Estado de bitácora #%d actualizado a %s', $entradaId, $nuevoEstado),
                valoresAnteriores: ['estado' => $estadoAnterior],
                valoresNuevos: ['estado' => $nuevoEstado],
                contexto: ['entrada_id' => $entradaId],
                usuarioId: $usuarioId,
                pdoTransaccional: $this->pdo
            );

            $this->pdo->commit();
            return $this->bitacoraRepo->buscarPorId($entradaId, true) ?? $entrada;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Resuelve formalmente una consigna o incidencia operativa.
     */
    public function resolverEntrada(
        int $entradaId,
        int $usuarioResolutorId,
        string $notaResolucion
    ): BitacoraEntrada {
        if ($usuarioResolutorId <= 0) {
            throw new InvalidArgumentException('ID de usuario resolutor no válido.');
        }

        $notaLimpia = trim($notaResolucion);
        if ($notaLimpia === '') {
            throw new InvalidArgumentException('Debe detallar la solución o descargo aplicado para resolver la entrada.');
        }

        $entrada = $this->bitacoraRepo->buscarPorId($entradaId, false);
        if ($entrada === null) {
            throw new EntidadNoEncontradaExcepcion(sprintf('No existe la entrada de bitácora #%d', $entradaId));
        }

        if ($entrada->estaAnulada()) {
            throw new OperacionInvalidaExcepcion('Una entrada anulada no puede ser resuelta.');
        }

        if ($entrada->estaResuelta()) {
            throw new OperacionInvalidaExcepcion('La entrada de bitácora ya se encuentra en estado RESUELTA.');
        }

        $estadoAnterior = $entrada->obtenerEstado();
        $resueltaEn = date('Y-m-d H:i:s');

        $this->pdo->beginTransaction();
        try {
            $this->bitacoraRepo->actualizarResolucion(
                entradaId: $entradaId,
                nuevoEstado: BitacoraEntrada::ESTADO_RESUELTA,
                resolutorId: $usuarioResolutorId,
                resueltaEn: $resueltaEn,
                notaResolucion: $notaLimpia
            );

            $seguimiento = new BitacoraSeguimiento(
                id: null,
                entradaId: $entradaId,
                usuarioId: $usuarioResolutorId,
                tipoEvento: BitacoraSeguimiento::TIPO_RESOLUCION,
                contenido: sprintf('Entrada marcada como RESUELTA: %s', $notaLimpia),
                estadoAnterior: $estadoAnterior,
                estadoNuevo: BitacoraEntrada::ESTADO_RESUELTA
            );
            $this->bitacoraRepo->insertarSeguimiento($seguimiento);

            $this->auditoriaServicio->registrar(
                accion: 'RESOLVER',
                modulo: 'operaciones',
                entidad: 'bitacora_entrada',
                entidadId: $entradaId,
                descripcion: sprintf('Resolución de entrada de bitácora #%d', $entradaId),
                valoresAnteriores: ['estado' => $estadoAnterior],
                valoresNuevos: ['estado' => BitacoraEntrada::ESTADO_RESUELTA, 'nota' => $notaLimpia],
                contexto: ['entrada_id' => $entradaId],
                usuarioId: $usuarioResolutorId,
                pdoTransaccional: $this->pdo
            );

            $this->pdo->commit();
            return $this->bitacoraRepo->buscarPorId($entradaId, true) ?? $entrada;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Reabre una entrada previamente resuelta si la novedad persiste o requiere nueva acción.
     */
    public function reabrirEntrada(
        int $entradaId,
        int $usuarioId,
        string $motivoReapertura
    ): BitacoraEntrada {
        if ($usuarioId <= 0) {
            throw new InvalidArgumentException('ID de usuario no válido.');
        }

        $motivoLimpio = trim($motivoReapertura);
        if ($motivoLimpio === '') {
            throw new InvalidArgumentException('Debe fundamentar el motivo de reapertura.');
        }

        $entrada = $this->bitacoraRepo->buscarPorId($entradaId, false);
        if ($entrada === null) {
            throw new EntidadNoEncontradaExcepcion(sprintf('No existe la entrada de bitácora #%d', $entradaId));
        }

        if (!$entrada->estaResuelta()) {
            throw new OperacionInvalidaExcepcion('Solo se pueden reabrir entradas que se encuentren en estado RESUELTA.');
        }

        $estadoAnterior = $entrada->obtenerEstado();
        $nuevoEstado = BitacoraEntrada::ESTADO_EN_PROCESO;

        $this->pdo->beginTransaction();
        try {
            $this->bitacoraRepo->actualizarEstado($entradaId, $nuevoEstado);

            $seguimiento = new BitacoraSeguimiento(
                id: null,
                entradaId: $entradaId,
                usuarioId: $usuarioId,
                tipoEvento: BitacoraSeguimiento::TIPO_REAPERTURA,
                contenido: sprintf('Reapertura de entrada: %s', $motivoLimpio),
                estadoAnterior: $estadoAnterior,
                estadoNuevo: $nuevoEstado
            );
            $this->bitacoraRepo->insertarSeguimiento($seguimiento);

            $this->auditoriaServicio->registrar(
                accion: 'REAPERTURA',
                modulo: 'operaciones',
                entidad: 'bitacora_entrada',
                entidadId: $entradaId,
                descripcion: sprintf('Reapertura de entrada de bitácora #%d', $entradaId),
                valoresAnteriores: ['estado' => $estadoAnterior],
                valoresNuevos: ['estado' => $nuevoEstado, 'motivo' => $motivoLimpio],
                contexto: ['entrada_id' => $entradaId],
                usuarioId: $usuarioId,
                pdoTransaccional: $this->pdo
            );

            $this->pdo->commit();
            return $this->bitacoraRepo->buscarPorId($entradaId, true) ?? $entrada;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Anula de forma supervisada una entrada de guardia (ANULAR != DELETE).
     */
    public function anularEntrada(
        int $entradaId,
        int $usuarioAnuladorId,
        string $motivoAnulacion
    ): BitacoraEntrada {
        if ($usuarioAnuladorId <= 0) {
            throw new InvalidArgumentException('ID de usuario supervisor no válido.');
        }

        $motivoLimpio = trim($motivoAnulacion);
        if (mb_strlen($motivoLimpio) < 5) {
            throw new InvalidArgumentException('El motivo de anulación supervisada debe tener al menos 5 caracteres.');
        }

        $entrada = $this->bitacoraRepo->buscarPorId($entradaId, false);
        if ($entrada === null) {
            throw new EntidadNoEncontradaExcepcion(sprintf('No existe la entrada de bitácora #%d', $entradaId));
        }

        if ($entrada->estaAnulada()) {
            throw new OperacionInvalidaExcepcion('La entrada de bitácora ya se encuentra anulada.');
        }

        $estadoAnterior = $entrada->obtenerEstado();
        $anuladaEn = date('Y-m-d H:i:s');

        $this->pdo->beginTransaction();
        try {
            $this->bitacoraRepo->actualizarAnulacion(
                entradaId: $entradaId,
                anuladorId: $usuarioAnuladorId,
                motivoAnulacion: $motivoLimpio,
                anuladaEn: $anuladaEn
            );

            $seguimiento = new BitacoraSeguimiento(
                id: null,
                entradaId: $entradaId,
                usuarioId: $usuarioAnuladorId,
                tipoEvento: BitacoraSeguimiento::TIPO_ANULACION,
                contenido: sprintf('Entrada ANULADA por supervisión: %s', $motivoLimpio),
                estadoAnterior: $estadoAnterior,
                estadoNuevo: BitacoraEntrada::ESTADO_ANULADA
            );
            $this->bitacoraRepo->insertarSeguimiento($seguimiento);

            $this->auditoriaServicio->registrar(
                accion: 'ANULAR',
                modulo: 'operaciones',
                entidad: 'bitacora_entrada',
                entidadId: $entradaId,
                descripcion: sprintf('Anulación de entrada de bitácora #%d (ANULAR != DELETE)', $entradaId),
                valoresAnteriores: ['estado' => $estadoAnterior],
                valoresNuevos: ['estado' => BitacoraEntrada::ESTADO_ANULADA, 'motivo' => $motivoLimpio],
                contexto: ['entrada_id' => $entradaId],
                usuarioId: $usuarioAnuladorId,
                pdoTransaccional: $this->pdo
            );

            $this->pdo->commit();
            return $this->bitacoraRepo->buscarPorId($entradaId, true) ?? $entrada;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Consulta una entrada específica por ID.
     */
    public function obtenerEntrada(int $id, bool $cargarSeguimientos = true): BitacoraEntrada
    {
        $entrada = $this->bitacoraRepo->buscarPorId($id, $cargarSeguimientos);
        if ($entrada === null) {
            throw new EntidadNoEncontradaExcepcion(sprintf('No existe la entrada de bitácora #%d', $id));
        }
        return $entrada;
    }

    /**
     * Lista entradas con soporte para paginación y filtros.
     *
     * @param array<string, mixed> $filtros
     * @param int $pagina
     * @param int $porPagina
     * @return array{entradas: BitacoraEntrada[], total: int, pagina: int, por_pagina: int, total_paginas: int}
     */
    public function listarEntradas(array $filtros = [], int $pagina = 1, int $porPagina = 20): array
    {
        $pagina = max(1, $pagina);
        $porPagina = max(1, min(100, $porPagina));
        $offset = ($pagina - 1) * $porPagina;

        $total = $this->bitacoraRepo->contar($filtros);
        $entradas = $this->bitacoraRepo->listar($filtros, $porPagina, $offset);
        $totalPaginas = (int) ceil($total / $porPagina);

        return [
            'entradas' => $entradas,
            'total' => $total,
            'pagina' => $pagina,
            'por_pagina' => $porPagina,
            'total_paginas' => $totalPaginas,
        ];
    }

    /**
     * Obtiene métricas para cuadros de mando y resúmenes de turno.
     *
     * @param int $propiedadId
     * @param string|null $fechaOperativa
     * @return array<string, int>
     */
    public function obtenerMetricas(int $propiedadId = 0, ?string $fechaOperativa = null): array
    {
        return $this->bitacoraRepo->obtenerMetricas($propiedadId, $fechaOperativa);
    }
}
