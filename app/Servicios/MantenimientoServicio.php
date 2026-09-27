<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

use CamargoPMS\Excepciones\ConflictoDisponibilidadExcepcion;
use CamargoPMS\Excepciones\EstadoMantenimientoInvalidoExcepcion;
use CamargoPMS\Excepciones\IncidenciaNoEncontradaExcepcion;
use CamargoPMS\Excepciones\OrdenTrabajoNoEncontradaExcepcion;
use CamargoPMS\Excepciones\PropiedadNoEncontradaExcepcion;
use CamargoPMS\Excepciones\ProveedorNoEncontradoExcepcion;
use CamargoPMS\Excepciones\UnidadNoEncontradaExcepcion;
use CamargoPMS\Excepciones\ValidacionExcepcion;
use CamargoPMS\Modelos\Incidencia;
use CamargoPMS\Modelos\MantenimientoHistorialEstado;
use CamargoPMS\Modelos\OrdenTrabajo;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Repositorios\ColaboradorRepositorio;
use CamargoPMS\Repositorios\DisponibilidadRepositorio;
use CamargoPMS\Repositorios\IncidenciaRepositorio;
use CamargoPMS\Repositorios\MantenimientoHistorialEstadoRepositorio;
use CamargoPMS\Repositorios\OrdenTrabajoRepositorio;
use CamargoPMS\Repositorios\PersonaRepositorio;
use CamargoPMS\Repositorios\PropiedadRepositorio;
use CamargoPMS\Repositorios\ProveedorRepositorio;
use CamargoPMS\Repositorios\UnidadRepositorio;
use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use PDO;
use PDOException;
use Throwable;

/**
 * Servicio de dominio central para la gestión de incidencias, órdenes de trabajo
 * y bloqueo operativo de unidades físicas (MANTENIMIENTO-1 / Decisión D-077).
 * 
 * Reglas vinculantes:
 * 1. INCIDENCIA != ORDEN DE TRABAJO != BLOQUEO OPERATIVO.
 * 2. Protección anticipada de inventario: materialización transaccional al programar la orden.
 * 3. Semántica semiabierta [inicio, fin) en noches de inventario diario.
 * 4. Clasificación en inventario diario: tipo_bloqueo = 'MANTENIMIENTO', origen_tipo = 'MANTENIMIENTO_ORDEN'.
 * 5. Costos gestionados con BCMath (DECIMAL 15,2).
 * 6. Preservación histórica: al cancelar o completar con fechas pasadas, se conservan noches históricas.
 * 7. Manejo canónico de excepciones: 1062, 1205 y 1213 traducidos a ConflictoDisponibilidadExcepcion (HTTP 409).
 */
class MantenimientoServicio
{
    private PDO $pdo;
    private IncidenciaRepositorio $incidenciaRepo;
    private OrdenTrabajoRepositorio $ordenRepo;
    private MantenimientoHistorialEstadoRepositorio $historialRepo;
    private DisponibilidadRepositorio $disponibilidadRepo;
    private PropiedadRepositorio $propiedadRepo;
    private UnidadRepositorio $unidadRepo;
    private ColaboradorRepositorio $colaboradorRepo;
    private ProveedorRepositorio $proveedorRepo;
    private PersonaRepositorio $personaRepo;

    public function __construct(
        ?PDO $pdo = null,
        ?IncidenciaRepositorio $incidenciaRepo = null,
        ?OrdenTrabajoRepositorio $ordenRepo = null,
        ?MantenimientoHistorialEstadoRepositorio $historialRepo = null,
        ?DisponibilidadRepositorio $disponibilidadRepo = null,
        ?PropiedadRepositorio $propiedadRepo = null,
        ?UnidadRepositorio $unidadRepo = null,
        ?ColaboradorRepositorio $colaboradorRepo = null,
        ?ProveedorRepositorio $proveedorRepo = null,
        ?PersonaRepositorio $personaRepo = null
    ) {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->incidenciaRepo = $incidenciaRepo ?? new IncidenciaRepositorio($this->pdo);
        $this->ordenRepo = $ordenRepo ?? new OrdenTrabajoRepositorio($this->pdo);
        $this->historialRepo = $historialRepo ?? new MantenimientoHistorialEstadoRepositorio($this->pdo);
        $this->disponibilidadRepo = $disponibilidadRepo ?? new DisponibilidadRepositorio($this->pdo);
        $this->propiedadRepo = $propiedadRepo ?? new PropiedadRepositorio($this->pdo);
        $this->unidadRepo = $unidadRepo ?? new UnidadRepositorio($this->pdo);
        $this->colaboradorRepo = $colaboradorRepo ?? new ColaboradorRepositorio($this->pdo);
        $this->proveedorRepo = $proveedorRepo ?? new ProveedorRepositorio($this->pdo);
        $this->personaRepo = $personaRepo ?? new PersonaRepositorio($this->pdo);
    }

    // =========================================================================
    // 1. GESTIÓN DE INCIDENCIAS TÉCNICAS (TICKETS DE REPORTE)
    // =========================================================================

    /**
     * Registra un nuevo reporte o ticket de incidencia técnica.
     * Invariante D-077: Una incidencia por sí misma JAMÁS bloquea inventario.
     * 
     * @param array<string, mixed> $datos
     */
    public function reportarIncidencia(array $datos, int $actorId): Incidencia
    {
        $propiedadId = (int) ($datos['propiedad_id'] ?? 0);
        $propiedad = $this->propiedadRepo->buscarPorId($propiedadId);
        if (!$propiedad) {
            throw new PropiedadNoEncontradaExcepcion("La propiedad con ID {$propiedadId} no existe.");
        }

        $unidadId = isset($datos['unidad_id']) && $datos['unidad_id'] !== '' ? (int) $datos['unidad_id'] : null;
        if ($unidadId !== null) {
            $unidad = $this->unidadRepo->buscarPorId($unidadId);
            if (!$unidad) {
                throw new UnidadNoEncontradaExcepcion("La unidad con ID {$unidadId} no existe.");
            }
            if ($unidad->obtenerPropiedadId() !== $propiedadId) {
                throw new ValidacionExcepcion("La unidad {$unidadId} no pertenece a la propiedad {$propiedadId}.");
            }
        }

        $reportadoPorId = (int) ($datos['reportado_por_persona_id'] ?? 0);
        $persona = $this->personaRepo->buscarPorId($reportadoPorId);
        if (!$persona) {
            throw new ValidacionExcepcion("La persona reportadora con ID {$reportadoPorId} no existe.");
        }

        $titulo = trim((string) ($datos['titulo'] ?? ''));
        if (mb_strlen($titulo) < 3) {
            throw new ValidacionExcepcion('El título de la incidencia debe contener al menos 3 caracteres.');
        }

        $descripcion = trim((string) ($datos['descripcion'] ?? ''));
        if (mb_strlen($descripcion) < 5) {
            throw new ValidacionExcepcion('La descripción de la incidencia debe contener al menos 5 caracteres.');
        }

        $categoria = (string) ($datos['categoria'] ?? 'OTRO');
        $categoriasValidas = [
            'PLOMERIA', 'ELECTRICIDAD', 'CERRAJERIA', 'CLIMATIZACION',
            'PINTURA', 'MOBILIARIO', 'LIMPIEZA_PROFUNDA', 'ESTRUCTURAL', 'OTRO'
        ];
        if (!in_array($categoria, $categoriasValidas, true)) {
            throw new ValidacionExcepcion("Categoría '{$categoria}' no válida.");
        }

        $severidad = (string) ($datos['severidad'] ?? 'MEDIA');
        $severidadesValidas = ['BAJA', 'MEDIA', 'ALTA', 'CRITICA'];
        if (!in_array($severidad, $severidadesValidas, true)) {
            throw new ValidacionExcepcion("Severidad '{$severidad}' no válida.");
        }

        $ubicacion = isset($datos['ubicacion_detallada']) && trim((string) $datos['ubicacion_detallada']) !== ''
            ? trim((string) $datos['ubicacion_detallada'])
            : null;

        $codigo = $this->incidenciaRepo->generarSiguienteCodigo();

        $incidencia = new Incidencia(
            null,
            $codigo,
            $propiedadId,
            $unidadId,
            $reportadoPorId,
            $categoria,
            $severidad,
            $titulo,
            $descripcion,
            $ubicacion,
            'REPORTADA',
            null,
            $actorId
        );

        $this->pdo->beginTransaction();
        try {
            $id = $this->incidenciaRepo->crear($incidencia);

            $this->historialRepo->registrar(new MantenimientoHistorialEstado(
                null,
                'INCIDENCIA',
                $id,
                'REPORTADA',
                'REPORTADA',
                'Reporte inicial de incidencia técnica',
                $actorId
            ));

            $this->pdo->commit();
            return $this->incidenciaRepo->obtenerPorId($id);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Pasa una incidencia a revisión o evaluación técnica.
     */
    public function evaluarIncidencia(int $id, int $actorId): Incidencia
    {
        $incidencia = $this->incidenciaRepo->obtenerPorId($id);
        if (!$incidencia) {
            throw new IncidenciaNoEncontradaExcepcion();
        }

        if ($incidencia->obtenerEstado() !== 'REPORTADA') {
            throw new EstadoMantenimientoInvalidoExcepcion(
                'INCIDENCIA',
                $incidencia->obtenerEstado(),
                'EVALUAR'
            );
        }

        $this->pdo->beginTransaction();
        try {
            $this->incidenciaRepo->actualizarEstado($id, 'EN_EVALUACION');

            $this->historialRepo->registrar(new MantenimientoHistorialEstado(
                null,
                'INCIDENCIA',
                $id,
                'REPORTADA',
                'EN_EVALUACION',
                'Pase a evaluación técnica',
                $actorId
            ));

            $this->pdo->commit();
            return $this->incidenciaRepo->obtenerPorId($id);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Resuelve directamente una incidencia in situ sin generar orden de trabajo formal.
     */
    public function resolverIncidenciaDirecta(int $id, string $motivoCierre, int $actorId): Incidencia
    {
        $incidencia = $this->incidenciaRepo->obtenerPorId($id);
        if (!$incidencia) {
            throw new IncidenciaNoEncontradaExcepcion();
        }

        if (!$incidencia->estaAbierta()) {
            throw new EstadoMantenimientoInvalidoExcepcion(
                'INCIDENCIA',
                $incidencia->obtenerEstado(),
                'RESOLVER_DIRECTA'
            );
        }

        $motivo = trim($motivoCierre);
        if (mb_strlen($motivo) < 5) {
            throw new ValidacionExcepcion('El motivo de cierre directo debe contener al menos 5 caracteres.');
        }

        $this->pdo->beginTransaction();
        try {
            $estadoAnterior = $incidencia->obtenerEstado();
            $this->incidenciaRepo->actualizarEstado($id, 'RESUELTA_DIRECTA', $motivo, $actorId);

            $this->historialRepo->registrar(new MantenimientoHistorialEstado(
                null,
                'INCIDENCIA',
                $id,
                $estadoAnterior,
                'RESUELTA_DIRECTA',
                $motivo,
                $actorId
            ));

            $this->pdo->commit();
            return $this->incidenciaRepo->obtenerPorId($id);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Desestima un reporte de incidencia justificado (duplicado, falsa alarma, etc.).
     */
    public function desestimarIncidencia(int $id, string $motivoCierre, int $actorId): Incidencia
    {
        $incidencia = $this->incidenciaRepo->obtenerPorId($id);
        if (!$incidencia) {
            throw new IncidenciaNoEncontradaExcepcion();
        }

        if (!$incidencia->estaAbierta()) {
            throw new EstadoMantenimientoInvalidoExcepcion(
                'INCIDENCIA',
                $incidencia->obtenerEstado(),
                'DESESTIMAR'
            );
        }

        $motivo = trim($motivoCierre);
        if (mb_strlen($motivo) < 5) {
            throw new ValidacionExcepcion('El motivo para desestimar debe contener al menos 5 caracteres.');
        }

        $this->pdo->beginTransaction();
        try {
            $estadoAnterior = $incidencia->obtenerEstado();
            $this->incidenciaRepo->actualizarEstado($id, 'DESESTIMADA', $motivo, $actorId);

            $this->historialRepo->registrar(new MantenimientoHistorialEstado(
                null,
                'INCIDENCIA',
                $id,
                $estadoAnterior,
                'DESESTIMADA',
                $motivo,
                $actorId
            ));

            $this->pdo->commit();
            return $this->incidenciaRepo->obtenerPorId($id);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function obtenerIncidencia(int $id): Incidencia
    {
        $incidencia = $this->incidenciaRepo->obtenerPorId($id);
        if (!$incidencia) {
            throw new IncidenciaNoEncontradaExcepcion();
        }
        return $incidencia;
    }

    /**
     * @param array<string, mixed> $filtros
     * @return array<int, Incidencia>
     */
    public function listarIncidencias(array $filtros = []): array
    {
        return $this->incidenciaRepo->listar($filtros);
    }

    // =========================================================================
    // 2. GESTIÓN DE ÓRDENES DE TRABAJO (EJECUCIÓN, COSTEO Y BLOQUEO)
    // =========================================================================

    /**
     * Crea una orden de trabajo en estado BORRADOR.
     * Cero materialización de inventario en BORRADOR.
     * 
     * @param array<string, mixed> $datos
     */
    public function crearOrden(array $datos, int $actorId): OrdenTrabajo
    {
        $propiedadId = (int) ($datos['propiedad_id'] ?? 0);
        $propiedad = $this->propiedadRepo->buscarPorId($propiedadId);
        if (!$propiedad) {
            throw new PropiedadNoEncontradaExcepcion("La propiedad con ID {$propiedadId} no existe.");
        }

        $unidadId = isset($datos['unidad_id']) && $datos['unidad_id'] !== '' ? (int) $datos['unidad_id'] : null;
        if ($unidadId !== null) {
            $unidad = $this->unidadRepo->buscarPorId($unidadId);
            if (!$unidad) {
                throw new UnidadNoEncontradaExcepcion("La unidad con ID {$unidadId} no existe.");
            }
            if ($unidad->obtenerPropiedadId() !== $propiedadId) {
                throw new ValidacionExcepcion("La unidad {$unidadId} no pertenece a la propiedad {$propiedadId}.");
            }
        }

        $titulo = trim((string) ($datos['titulo'] ?? ''));
        if (mb_strlen($titulo) < 3) {
            throw new ValidacionExcepcion('El título de la orden debe contener al menos 3 caracteres.');
        }

        $descripcion = trim((string) ($datos['descripcion'] ?? ''));
        if (mb_strlen($descripcion) < 5) {
            throw new ValidacionExcepcion('La descripción de la orden debe contener al menos 5 caracteres.');
        }

        $tipo = (string) ($datos['tipo'] ?? 'CORRECTIVO');
        if (!in_array($tipo, ['CORRECTIVO', 'PREVENTIVO'], true)) {
            throw new ValidacionExcepcion("Tipo de orden '{$tipo}' no válido.");
        }

        $prioridad = (string) ($datos['prioridad'] ?? 'MEDIA');
        if (!in_array($prioridad, ['BAJA', 'MEDIA', 'ALTA', 'URGENTE'], true)) {
            throw new ValidacionExcepcion("Prioridad '{$prioridad}' no válida.");
        }

        $tipoAsignacion = (string) ($datos['tipo_asignacion'] ?? 'INTERNO');
        if (!in_array($tipoAsignacion, ['INTERNO', 'EXTERNO', 'MIXTO'], true)) {
            throw new ValidacionExcepcion("Tipo de asignación '{$tipoAsignacion}' no válido.");
        }

        $colaboradorId = isset($datos['colaborador_asignado_id']) && $datos['colaborador_asignado_id'] !== ''
            ? (int) $datos['colaborador_asignado_id']
            : null;
        if ($colaboradorId !== null) {
            $colaborador = $this->colaboradorRepo->buscarPorId($colaboradorId);
            if (!$colaborador) {
                throw new ValidacionExcepcion("El colaborador con ID {$colaboradorId} no existe.");
            }
        }

        $proveedorId = isset($datos['proveedor_id']) && $datos['proveedor_id'] !== ''
            ? (int) $datos['proveedor_id']
            : null;
        if ($proveedorId !== null) {
            $proveedor = $this->proveedorRepo->buscarPorId($proveedorId);
            if (!$proveedor) {
                throw new ProveedorNoEncontradoExcepcion("El proveedor con ID {$proveedorId} no existe.");
            }
        }

        $numComprobante = isset($datos['numero_comprobante_proveedor']) && trim((string) $datos['numero_comprobante_proveedor']) !== ''
            ? trim((string) $datos['numero_comprobante_proveedor'])
            : null;

        $fechaProgInicio = (string) ($datos['fecha_programada_inicio'] ?? '');
        $fechaProgFin = (string) ($datos['fecha_programada_fin'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaProgInicio) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaProgFin)) {
            throw new ValidacionExcepcion('Las fechas programadas deben tener formato YYYY-MM-DD.');
        }
        if ($fechaProgFin < $fechaProgInicio) {
            throw new ValidacionExcepcion('La fecha programada de fin no puede ser anterior a la de inicio.');
        }

        // Regla vinculante D-077: requiere_bloqueo y coherencia estricta
        $requiereBloqueo = !empty($datos['requiere_bloqueo']);
        $fechaBloqInicio = null;
        $fechaBloqFin = null;

        if ($requiereBloqueo) {
            if ($unidadId === null) {
                throw new ValidacionExcepcion('Una orden que requiere bloqueo debe asociarse obligatoriamente a una unidad física.');
            }

            $fechaBloqInicio = !empty($datos['fecha_bloqueo_inicio'])
                ? (string) $datos['fecha_bloqueo_inicio']
                : $fechaProgInicio;

            $fechaBloqFin = !empty($datos['fecha_bloqueo_fin'])
                ? (string) $datos['fecha_bloqueo_fin']
                : $fechaProgFin;

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaBloqInicio) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaBloqFin)) {
                throw new ValidacionExcepcion('Las fechas de bloqueo deben tener formato YYYY-MM-DD.');
            }

            if ($fechaBloqFin <= $fechaBloqInicio) {
                throw new ValidacionExcepcion("La fecha de fin de bloqueo ({$fechaBloqFin}) debe ser estrictamente posterior a la fecha de inicio ({$fechaBloqInicio}).");
            }
        }

        // Costos con BCMath
        $costoEstimado = number_format((float) ($datos['costo_estimado'] ?? 0), 2, '.', '');
        $costoManoObra = number_format((float) ($datos['costo_mano_obra'] ?? 0), 2, '.', '');
        $costoMateriales = number_format((float) ($datos['costo_materiales'] ?? 0), 2, '.', '');
        $costoTotal = bcadd($costoManoObra, $costoMateriales, 2);

        if (bccomp($costoEstimado, '0.00', 2) < 0 || bccomp($costoManoObra, '0.00', 2) < 0 || bccomp($costoMateriales, '0.00', 2) < 0) {
            throw new ValidacionExcepcion('Los costos no pueden ser negativos.');
        }

        $codigo = $this->ordenRepo->generarSiguienteCodigo();

        $orden = new OrdenTrabajo(
            null,
            $codigo,
            $tipo,
            $prioridad,
            $propiedadId,
            $unidadId,
            $titulo,
            $descripcion,
            $tipoAsignacion,
            $colaboradorId,
            $proveedorId,
            $numComprobante,
            $requiereBloqueo,
            $fechaProgInicio,
            $fechaProgFin,
            $fechaBloqInicio,
            $fechaBloqFin,
            null,
            null,
            $costoEstimado,
            $costoManoObra,
            $costoMateriales,
            $costoTotal,
            'PEN',
            'BORRADOR',
            $actorId
        );

        $this->pdo->beginTransaction();
        try {
            $ordenId = $this->ordenRepo->crear($orden);

            // Asociar incidencias reportadas si se suministraron (Relación N:M)
            if (!empty($datos['incidencias_ids']) && is_array($datos['incidencias_ids'])) {
                foreach ($datos['incidencias_ids'] as $incId) {
                    $iId = (int) $incId;
                    if ($iId > 0) {
                        $this->ordenRepo->asociarIncidencia($ordenId, $iId);
                        // Transición de la incidencia a CONVERTIDA_A_ORDEN
                        $inc = $this->incidenciaRepo->obtenerPorId($iId);
                        if ($inc && $inc->estaAbierta()) {
                            $this->incidenciaRepo->actualizarEstado($iId, 'CONVERTIDA_A_ORDEN');
                            $this->historialRepo->registrar(new MantenimientoHistorialEstado(
                                null,
                                'INCIDENCIA',
                                $iId,
                                $inc->obtenerEstado(),
                                'CONVERTIDA_A_ORDEN',
                                "Vinculada a orden de trabajo {$codigo}",
                                $actorId
                            ));
                        }
                    }
                }
            }

            $this->historialRepo->registrar(new MantenimientoHistorialEstado(
                null,
                'ORDEN_TRABAJO',
                $ordenId,
                'BORRADOR',
                'BORRADOR',
                'Creación de orden de trabajo en borrador',
                $actorId
            ));

            $this->pdo->commit();
            return $this->ordenRepo->obtenerPorId($ordenId);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Programa una orden de trabajo (BORRADOR -> PROGRAMADA).
     * 
     * Regla vinculante D-077 (Ajuste 2):
     * La protección de inventario ocurre inmediatamente al programar.
     * Si requiere_bloqueo = 1, materializa transaccionalmente las noches en inventario_diario_unidades.
     * En caso de conflicto, ejecuta ROLLBACK total y emite ConflictoDisponibilidadExcepcion (HTTP 409).
     * 
     * Regla vinculante D-077 (Ajuste 4):
     * Valida la asignación de responsable (interno o externo) según tipo_asignacion a nivel de servicio.
     * 
     * @param array<string, mixed> $datosAjuste
     */
    public function programarOrden(int $id, array $datosAjuste = [], int $actorId = 0): OrdenTrabajo
    {
        $this->pdo->beginTransaction();
        try {
            $orden = $this->ordenRepo->obtenerPorId($id, true);
            if (!$orden) {
                throw new OrdenTrabajoNoEncontradaExcepcion();
            }

            if ($orden->obtenerEstado() !== 'BORRADOR') {
                throw new EstadoMantenimientoInvalidoExcepcion(
                    'ORDEN_TRABAJO',
                    $orden->obtenerEstado(),
                    'PROGRAMAR'
                );
            }

            // Aplicar ajustes opcionales previos a la confirmación
            if (!empty($datosAjuste)) {
                if (isset($datosAjuste['colaborador_asignado_id'])) {
                    $cId = $datosAjuste['colaborador_asignado_id'] !== '' ? (int) $datosAjuste['colaborador_asignado_id'] : null;
                    if ($cId !== null && !$this->colaboradorRepo->buscarPorId($cId)) {
                        throw new ValidacionExcepcion("Colaborador {$cId} no existe.");
                    }
                }
                if (isset($datosAjuste['proveedor_id'])) {
                    $pId = $datosAjuste['proveedor_id'] !== '' ? (int) $datosAjuste['proveedor_id'] : null;
                    if ($pId !== null && !$this->proveedorRepo->buscarPorId($pId)) {
                        throw new ProveedorNoEncontradoExcepcion("Proveedor {$pId} no existe.");
                    }
                }
            }

            // Regla de Asignación en Servicio (Ajuste 4)
            $tipoAsignacion = $datosAjuste['tipo_asignacion'] ?? $orden->obtenerTipoAsignacion();
            $colabId = array_key_exists('colaborador_asignado_id', $datosAjuste)
                ? ($datosAjuste['colaborador_asignado_id'] !== '' ? (int) $datosAjuste['colaborador_asignado_id'] : null)
                : $orden->obtenerColaboradorAsignadoId();
            $provId = array_key_exists('proveedor_id', $datosAjuste)
                ? ($datosAjuste['proveedor_id'] !== '' ? (int) $datosAjuste['proveedor_id'] : null)
                : $orden->obtenerProveedorId();

            if ($tipoAsignacion === 'INTERNO' && $colabId === null) {
                throw new ValidacionExcepcion('Una orden con asignación interna requiere asignar un colaborador.');
            }
            if ($tipoAsignacion === 'EXTERNO' && $provId === null) {
                throw new ValidacionExcepcion('Una orden con asignación externa requiere asignar un proveedor.');
            }
            if ($tipoAsignacion === 'MIXTO' && $colabId === null && $provId === null) {
                throw new ValidacionExcepcion('Una orden con asignación mixta requiere asignar al menos un colaborador o proveedor.');
            }

            // Ajustar fechas o bloqueo si vino en el payload
            $requiereBloqueo = isset($datosAjuste['requiere_bloqueo']) ? !empty($datosAjuste['requiere_bloqueo']) : $orden->requiereBloqueo();
            $fechaBloqInicio = $datosAjuste['fecha_bloqueo_inicio'] ?? $orden->obtenerFechaBloqueoInicio();
            $fechaBloqFin = $datosAjuste['fecha_bloqueo_fin'] ?? $orden->obtenerFechaBloqueoFin();

            if ($requiereBloqueo) {
                $unidadId = $orden->obtenerUnidadId();
                if ($unidadId === null) {
                    throw new ValidacionExcepcion('No se puede programar un bloqueo en una orden sin unidad asignada.');
                }
                if ($fechaBloqInicio === null || $fechaBloqFin === null || $fechaBloqFin <= $fechaBloqInicio) {
                    throw new ValidacionExcepcion('Intervalo de bloqueo no válido para programar.');
                }

                // Generar noches en intervalo semiabierto [fechaBloqInicio, fechaBloqFin)
                $dtInicio = new DateTimeImmutable($fechaBloqInicio);
                $dtFin = new DateTimeImmutable($fechaBloqFin);
                $intervalo = new DateInterval('P1D');
                $periodo = new DatePeriod($dtInicio, $intervalo, $dtFin);

                $noches = [];
                foreach ($periodo as $dt) {
                    $noches[] = $dt->format('Y-m-d');
                }

                if (empty($noches)) {
                    throw new ValidacionExcepcion('El intervalo de fechas de bloqueo no genera noches.');
                }

                // Bloqueo pesimista de fila en unidades
                $stmtUnidadLock = $this->pdo->prepare('SELECT id FROM unidades WHERE id = :id FOR UPDATE');
                $stmtUnidadLock->execute(['id' => $unidadId]);

                // Verificar colisiones deterministamente ordenadas
                $placeholders = implode(',', array_fill(0, count($noches), '?'));
                $sqlCheck = "SELECT fecha, tipo_bloqueo, origen_tipo, origen_id 
                             FROM inventario_diario_unidades 
                             WHERE unidad_id = ? AND fecha IN ($placeholders) 
                             ORDER BY fecha ASC FOR UPDATE";
                $stmtCheck = $this->pdo->prepare($sqlCheck);
                $stmtCheck->execute(array_merge([$unidadId], $noches));
                $colisiones = $stmtCheck->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($colisiones)) {
                    $col = $colisiones[0];
                    throw new ConflictoDisponibilidadExcepcion(
                        $unidadId,
                        (string) $col['fecha'],
                        "Conflicto de disponibilidad: la unidad habitacional está ocupada en la noche {$col['fecha']} por {$col['tipo_bloqueo']} ({$col['origen_tipo']})."
                    );
                }

                // Materializar noches en inventario_diario_unidades
                foreach ($noches as $fechaNoche) {
                    $this->disponibilidadRepo->insertarInventarioNoche(
                        $unidadId,
                        $fechaNoche,
                        'MANTENIMIENTO',
                        'MANTENIMIENTO_ORDEN',
                        $id
                    );
                }
            }

            // Actualizar orden a PROGRAMADA
            $sqlUpd = 'UPDATE mantenimiento_ordenes SET 
                           estado = "PROGRAMADA",
                           tipo_asignacion = :tipo_asignacion,
                           colaborador_asignado_id = :colaborador_id,
                           proveedor_id = :proveedor_id,
                           requiere_bloqueo = :requiere_bloqueo,
                           fecha_bloqueo_inicio = :bloq_inicio,
                           fecha_bloqueo_fin = :bloq_fin
                       WHERE id = :id';
            $stmtUpd = $this->pdo->prepare($sqlUpd);
            $stmtUpd->execute([
                'id' => $id,
                'tipo_asignacion' => $tipoAsignacion,
                'colaborador_id' => $colabId,
                'proveedor_id' => $provId,
                'requiere_bloqueo' => $requiereBloqueo ? 1 : 0,
                'bloq_inicio' => $requiereBloqueo ? $fechaBloqInicio : null,
                'bloq_fin' => $requiereBloqueo ? $fechaBloqFin : null,
            ]);

            $this->historialRepo->registrar(new MantenimientoHistorialEstado(
                null,
                'ORDEN_TRABAJO',
                $id,
                'BORRADOR',
                'PROGRAMADA',
                $requiereBloqueo ? 'Programación confirmada con materialización de bloqueo en inventario' : 'Programación confirmada sin bloqueo',
                $actorId
            ));

            $this->pdo->commit();
            return $this->ordenRepo->obtenerPorId($id);
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $errorCode = (int) ($e->errorInfo[1] ?? 0);
            if (in_array($errorCode, [1062, 1205, 1213], true)) {
                throw new ConflictoDisponibilidadExcepcion(
                    (int) ($orden?->obtenerUnidadId() ?? 0),
                    '',
                    "Conflicto de concurrencia o clave duplicada al programar bloqueo (MySQL {$errorCode}): " . $e->getMessage(),
                    409,
                    $e
                );
            }
            throw $e;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Inicia los trabajos físicos de la orden (PROGRAMADA -> EN_PROCESO).
     */
    public function iniciarEjecucion(int $id, int $actorId): OrdenTrabajo
    {
        $this->pdo->beginTransaction();
        try {
            $orden = $this->ordenRepo->obtenerPorId($id, true);
            if (!$orden) {
                throw new OrdenTrabajoNoEncontradaExcepcion();
            }

            if ($orden->obtenerEstado() !== 'PROGRAMADA') {
                throw new EstadoMantenimientoInvalidoExcepcion(
                    'ORDEN_TRABAJO',
                    $orden->obtenerEstado(),
                    'INICIAR_EJECUCION'
                );
            }

            $sql = 'UPDATE mantenimiento_ordenes SET 
                        estado = "EN_PROCESO",
                        fecha_ejecucion_inicio = NOW()
                    WHERE id = :id';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['id' => $id]);

            $this->historialRepo->registrar(new MantenimientoHistorialEstado(
                null,
                'ORDEN_TRABAJO',
                $id,
                'PROGRAMADA',
                'EN_PROCESO',
                'Inicio formal de trabajos de mantenimiento',
                $actorId
            ));

            $this->pdo->commit();
            return $this->ordenRepo->obtenerPorId($id);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Asienta o actualiza los costos reales de mano de obra y materiales.
     */
    public function registrarCostos(int $id, string $materiales, string $manoObra, int $actorId): OrdenTrabajo
    {
        if (bccomp($materiales, '0.00', 2) < 0 || bccomp($manoObra, '0.00', 2) < 0) {
            throw new ValidacionExcepcion('Los costos de mano de obra y materiales no pueden ser negativos.');
        }

        $orden = $this->ordenRepo->obtenerPorId($id);
        if (!$orden) {
            throw new OrdenTrabajoNoEncontradaExcepcion();
        }

        $costoTotal = bcadd($materiales, $manoObra, 2);

        $this->ordenRepo->actualizarCostos($id, $materiales, $manoObra, $costoTotal);
        return $this->ordenRepo->obtenerPorId($id);
    }

    /**
     * Culmina la orden de trabajo (COMPLETADA).
     * 
     * Regla vinculante D-077 (Ajuste 6):
     * Preservación histórica:
     * - Las noches pasadas (< fechaEfectiva) se conservan intactas en inventario_diario_unidades.
     * - Las noches futuras (>= fechaEfectiva) se liberan atómicamente.
     */
    public function completarOrden(int $id, string $notasCierre, ?string $fechaEfectivaLiberacion, int $actorId): OrdenTrabajo
    {
        $notas = trim($notasCierre);
        if (mb_strlen($notas) < 5) {
            throw new ValidacionExcepcion('Las notas de cierre deben contener al menos 5 caracteres.');
        }

        $this->pdo->beginTransaction();
        try {
            $orden = $this->ordenRepo->obtenerPorId($id, true);
            if (!$orden) {
                throw new OrdenTrabajoNoEncontradaExcepcion();
            }

            if (!in_array($orden->obtenerEstado(), ['PROGRAMADA', 'EN_PROCESO'], true)) {
                throw new EstadoMantenimientoInvalidoExcepcion(
                    'ORDEN_TRABAJO',
                    $orden->obtenerEstado(),
                    'COMPLETAR'
                );
            }

            // Liberación de noches futuras preservando noches pasadas
            if ($orden->requiereBloqueo()) {
                $fechaEfectiva = $fechaEfectivaLiberacion ?? date('Y-m-d');
                $sqlLib = 'DELETE FROM inventario_diario_unidades 
                           WHERE origen_tipo = "MANTENIMIENTO_ORDEN" 
                             AND origen_id = :id 
                             AND fecha >= :fecha_efectiva';
                $stmtLib = $this->pdo->prepare($sqlLib);
                $stmtLib->execute([
                    'id' => $id,
                    'fecha_efectiva' => $fechaEfectiva,
                ]);
            }

            $sql = 'UPDATE mantenimiento_ordenes SET 
                        estado = "COMPLETADA",
                        fecha_ejecucion_fin = NOW(),
                        completado_por_actor_id = :actor_id,
                        notas_cierre = :notas
                    WHERE id = :id';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'id' => $id,
                'actor_id' => $actorId,
                'notas' => $notas,
            ]);

            $this->historialRepo->registrar(new MantenimientoHistorialEstado(
                null,
                'ORDEN_TRABAJO',
                $id,
                $orden->obtenerEstado(),
                'COMPLETADA',
                $notas,
                $actorId
            ));

            $this->pdo->commit();
            return $this->ordenRepo->obtenerPorId($id);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Cancela una orden de trabajo con causa obligatoria.
     * 
     * Regla vinculante D-077 (Ajuste 6):
     * Si existía bloqueo:
     * - Si la orden no había comenzado (fecha_bloqueo_inicio >= hoy), se eliminan todas sus noches.
     * - Si la orden ya estaba en transcurso, se preservan noches pasadas (< hoy) y se liberan noches futuras (>= hoy).
     */
    public function cancelarOrden(int $id, string $motivoCancelacion, int $actorId): OrdenTrabajo
    {
        $motivo = trim($motivoCancelacion);
        if (mb_strlen($motivo) < 5) {
            throw new ValidacionExcepcion('El motivo de cancelación debe contener al menos 5 caracteres.');
        }

        $this->pdo->beginTransaction();
        try {
            $orden = $this->ordenRepo->obtenerPorId($id, true);
            if (!$orden) {
                throw new OrdenTrabajoNoEncontradaExcepcion();
            }

            if (in_array($orden->obtenerEstado(), ['COMPLETADA', 'CANCELADA'], true)) {
                throw new EstadoMantenimientoInvalidoExcepcion(
                    'ORDEN_TRABAJO',
                    $orden->obtenerEstado(),
                    'CANCELAR'
                );
            }

            if ($orden->requiereBloqueo() && in_array($orden->obtenerEstado(), ['PROGRAMADA', 'EN_PROCESO'], true)) {
                $hoy = date('Y-m-d');
                $inicioBloqueo = $orden->obtenerFechaBloqueoInicio() ?? $hoy;

                if ($inicioBloqueo >= $hoy) {
                    // Orden aún no comenzada: liberar todas las noches
                    $sqlLib = 'DELETE FROM inventario_diario_unidades 
                               WHERE origen_tipo = "MANTENIMIENTO_ORDEN" AND origen_id = :id';
                    $stmtLib = $this->pdo->prepare($sqlLib);
                    $stmtLib->execute(['id' => $id]);
                } else {
                    // Orden ya en transcurso: preservar noches pasadas y liberar futuras
                    $sqlLib = 'DELETE FROM inventario_diario_unidades 
                               WHERE origen_tipo = "MANTENIMIENTO_ORDEN" 
                                 AND origen_id = :id 
                                 AND fecha >= :hoy';
                    $stmtLib = $this->pdo->prepare($sqlLib);
                    $stmtLib->execute([
                        'id' => $id,
                        'hoy' => $hoy,
                    ]);
                }
            }

            $sql = 'UPDATE mantenimiento_ordenes SET 
                        estado = "CANCELADA",
                        cancelado_por_actor_id = :actor_id,
                        motivo_cancelacion = :motivo
                    WHERE id = :id';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'id' => $id,
                'actor_id' => $actorId,
                'motivo' => $motivo,
            ]);

            $this->historialRepo->registrar(new MantenimientoHistorialEstado(
                null,
                'ORDEN_TRABAJO',
                $id,
                $orden->obtenerEstado(),
                'CANCELADA',
                $motivo,
                $actorId
            ));

            $this->pdo->commit();
            return $this->ordenRepo->obtenerPorId($id);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Prorroga el bloqueo de una orden activa ampliando fecha_bloqueo_fin.
     * Materializa de forma transaccional las noches adicionales.
     */
    public function prorrogarBloqueo(int $id, string $nuevaFechaFin, int $actorId): OrdenTrabajo
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $nuevaFechaFin)) {
            throw new ValidacionExcepcion('Formato de nueva fecha de fin inválido (debe ser YYYY-MM-DD).');
        }

        $this->pdo->beginTransaction();
        try {
            $orden = $this->ordenRepo->obtenerPorId($id, true);
            if (!$orden) {
                throw new OrdenTrabajoNoEncontradaExcepcion();
            }

            if (!$orden->requiereBloqueo()) {
                throw new ValidacionExcepcion('No se puede prorrogar bloqueo en una orden que no requiere inhabilitación física.');
            }

            if (!in_array($orden->obtenerEstado(), ['PROGRAMADA', 'EN_PROCESO'], true)) {
                throw new EstadoMantenimientoInvalidoExcepcion(
                    'ORDEN_TRABAJO',
                    $orden->obtenerEstado(),
                    'PRORROGAR_BLOQUEO'
                );
            }

            $fechaFinActual = $orden->obtenerFechaBloqueoFin();
            if ($fechaFinActual === null || $nuevaFechaFin <= $fechaFinActual) {
                throw new ValidacionExcepcion("La nueva fecha fin ({$nuevaFechaFin}) debe ser posterior a la fecha fin actual ({$fechaFinActual}).");
            }

            // Intervalo adicional semiabierto [fechaFinActual, nuevaFechaFin)
            $dtInicioExtra = new DateTimeImmutable($fechaFinActual);
            $dtFinExtra = new DateTimeImmutable($nuevaFechaFin);
            $intervalo = new DateInterval('P1D');
            $periodoExtra = new DatePeriod($dtInicioExtra, $intervalo, $dtFinExtra);

            $nochesExtra = [];
            foreach ($periodoExtra as $dt) {
                $nochesExtra[] = $dt->format('Y-m-d');
            }

            if (!empty($nochesExtra)) {
                $unidadId = (int) $orden->obtenerUnidadId();

                // Lock pesimista en unidades
                $stmtUnidadLock = $this->pdo->prepare('SELECT id FROM unidades WHERE id = :id FOR UPDATE');
                $stmtUnidadLock->execute(['id' => $unidadId]);

                $placeholders = implode(',', array_fill(0, count($nochesExtra), '?'));
                $sqlCheck = "SELECT fecha, tipo_bloqueo, origen_tipo 
                             FROM inventario_diario_unidades 
                             WHERE unidad_id = ? AND fecha IN ($placeholders) 
                             ORDER BY fecha ASC FOR UPDATE";
                $stmtCheck = $this->pdo->prepare($sqlCheck);
                $stmtCheck->execute(array_merge([$unidadId], $nochesExtra));
                $colisiones = $stmtCheck->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($colisiones)) {
                    $col = $colisiones[0];
                    throw new ConflictoDisponibilidadExcepcion(
                        $unidadId,
                        (string) $col['fecha'],
                        "No se puede prorrogar: la unidad no está libre en la noche {$col['fecha']} ({$col['tipo_bloqueo']})."
                    );
                }

                foreach ($nochesExtra as $fechaNoche) {
                    $this->disponibilidadRepo->insertarInventarioNoche(
                        $unidadId,
                        $fechaNoche,
                        'MANTENIMIENTO',
                        'MANTENIMIENTO_ORDEN',
                        $id
                    );
                }
            }

            // Actualizar fecha_bloqueo_fin y fecha_programada_fin si corresponde
            $fechaProgFin = $orden->obtenerFechaProgramadaFin();
            $nuevaProgFin = max($fechaProgFin, $nuevaFechaFin);

            $sqlUpd = 'UPDATE mantenimiento_ordenes SET 
                           fecha_bloqueo_fin = :nueva_fin,
                           fecha_programada_fin = :nueva_prog_fin
                       WHERE id = :id';
            $stmtUpd = $this->pdo->prepare($sqlUpd);
            $stmtUpd->execute([
                'id' => $id,
                'nueva_fin' => $nuevaFechaFin,
                'nueva_prog_fin' => $nuevaProgFin,
            ]);

            $this->historialRepo->registrar(new MantenimientoHistorialEstado(
                null,
                'ORDEN_TRABAJO',
                $id,
                $orden->obtenerEstado(),
                $orden->obtenerEstado(),
                "Prórroga de inhabilitación física hasta el {$nuevaFechaFin}",
                $actorId
            ));

            $this->pdo->commit();
            return $this->ordenRepo->obtenerPorId($id);
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $errorCode = (int) ($e->errorInfo[1] ?? 0);
            if (in_array($errorCode, [1062, 1205, 1213], true)) {
                throw new ConflictoDisponibilidadExcepcion(
                    (int) ($orden?->obtenerUnidadId() ?? 0),
                    '',
                    "Conflicto de concurrencia al prorrogar bloqueo (MySQL {$errorCode}): " . $e->getMessage(),
                    409,
                    $e
                );
            }
            throw $e;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function obtenerOrden(int $id): OrdenTrabajo
    {
        $orden = $this->ordenRepo->obtenerPorId($id);
        if (!$orden) {
            throw new OrdenTrabajoNoEncontradaExcepcion();
        }
        return $orden;
    }

    /**
     * @param array<string, mixed> $filtros
     * @return array<int, OrdenTrabajo>
     */
    public function listarOrdenes(array $filtros = []): array
    {
        return $this->ordenRepo->listar($filtros);
    }

    /**
     * Asocia una o más incidencias a una orden de trabajo existente.
     * 
     * @param array<int> $incidenciasIds
     */
    public function asociarIncidencias(int $ordenId, array $incidenciasIds, int $actorId): void
    {
        $orden = $this->ordenRepo->obtenerPorId($ordenId);
        if (!$orden) {
            throw new OrdenTrabajoNoEncontradaExcepcion();
        }

        $this->pdo->beginTransaction();
        try {
            foreach ($incidenciasIds as $incId) {
                $iId = (int) $incId;
                if ($iId > 0) {
                    $this->ordenRepo->asociarIncidencia($ordenId, $iId);
                    $inc = $this->incidenciaRepo->obtenerPorId($iId);
                    if ($inc && $inc->estaAbierta()) {
                        $this->incidenciaRepo->actualizarEstado($iId, 'CONVERTIDA_A_ORDEN');
                        $this->historialRepo->registrar(new MantenimientoHistorialEstado(
                            null,
                            'INCIDENCIA',
                            $iId,
                            $inc->obtenerEstado(),
                            'CONVERTIDA_A_ORDEN',
                            "Vinculada a orden de trabajo {$orden->obtenerCodigo()}",
                            $actorId
                        ));
                    }
                }
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Desasocia una incidencia de una orden de trabajo.
     */
    public function desasociarIncidencia(int $ordenId, int $incidenciaId, int $actorId): void
    {
        $orden = $this->ordenRepo->obtenerPorId($ordenId);
        if (!$orden) {
            throw new OrdenTrabajoNoEncontradaExcepcion();
        }

        $this->pdo->beginTransaction();
        try {
            $this->ordenRepo->desasociarIncidencia($ordenId, $incidenciaId);

            // Si la incidencia no tiene más órdenes asociadas, regresa a EN_EVALUACION
            $inc = $this->incidenciaRepo->obtenerPorId($incidenciaId);
            if ($inc && $inc->obtenerOrdenesAsociadasCount() === 0 && $inc->obtenerEstado() === 'CONVERTIDA_A_ORDEN') {
                $this->incidenciaRepo->actualizarEstado($incidenciaId, 'EN_EVALUACION');
                $this->historialRepo->registrar(new MantenimientoHistorialEstado(
                    null,
                    'INCIDENCIA',
                    $incidenciaId,
                    'CONVERTIDA_A_ORDEN',
                    'EN_EVALUACION',
                    "Desasociada de orden de trabajo {$orden->obtenerCodigo()}",
                    $actorId
                ));
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Estadísticas y métricas para el encabezado del panel de mantenimiento.
     * 
     * @return array<string, int>
     */
    public function obtenerEstadisticas(): array
    {
        $incidenciasAbiertas = $this->incidenciaRepo->contar(['estado' => 'REPORTADA']) +
            $this->incidenciaRepo->contar(['estado' => 'EN_EVALUACION']);

        $ordenesEnProceso = $this->ordenRepo->contar(['estado' => 'EN_PROCESO']);
        $ordenesProgramadas = $this->ordenRepo->contar(['estado' => 'PROGRAMADA']);
        $unidadesBloqueadas = $this->ordenRepo->contarUnidadesBloqueadasActivas();

        $mesActual = date('Y-m');
        $stmtPrev = $this->pdo->prepare(
            'SELECT COUNT(*) FROM mantenimiento_ordenes 
             WHERE tipo = "PREVENTIVO" AND fecha_programada_inicio LIKE :mes'
        );
        $stmtPrev->execute(['mes' => $mesActual . '%']);
        $preventivosMes = (int) $stmtPrev->fetchColumn();

        return [
            'incidencias_abiertas' => $incidenciasAbiertas,
            'ordenes_en_proceso' => $ordenesEnProceso,
            'ordenes_programadas' => $ordenesProgramadas,
            'unidades_bloqueadas' => $unidadesBloqueadas,
            'preventivos_mes' => $preventivosMes,
        ];
    }

    /**
     * @return array<int, MantenimientoHistorialEstado>
     */
    public function obtenerHistorial(string $entidadTipo, int $entidadId): array
    {
        return $this->historialRepo->obtenerPorEntidad($entidadTipo, $entidadId);
    }
}
