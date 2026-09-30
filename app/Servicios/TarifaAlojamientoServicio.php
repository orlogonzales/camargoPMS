<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

use CamargoPMS\Excepciones\ConflictoTarifaExcepcion;
use CamargoPMS\Excepciones\TarifaNoEncontradaExcepcion;
use CamargoPMS\Excepciones\ValidacionTarifaExcepcion;
use CamargoPMS\Modelos\AmbitoTarifaAlojamiento;
use CamargoPMS\Modelos\TarifaAlojamiento;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Repositorios\PropiedadRepositorio;
use CamargoPMS\Repositorios\TarifaAlojamientoRepositorio;
use CamargoPMS\Repositorios\TipoUnidadRepositorio;
use CamargoPMS\Repositorios\UnidadRepositorio;
use DateTimeImmutable;
use PDO;
use Throwable;

/**
 * Servicio de Dominio para la Gestión y Resolución Soberana de Tarifas de Alojamiento.
 *
 * Principios Vinculantes:
 * 1. UNIDAD ≠ TARIFA. Precios desacoplados de la estructura física del hotel.
 * 2. JERARQUÍA DE RESOLUCIÓN: UNIDAD > TIPO_UNIDAD > PROPIEDAD.
 * 3. ANTI-SOLAPAMIENTO TEMPORAL: Bloqueo pesimista `SELECT ... FOR UPDATE`
 *    que garantiza cero ambigüedad en rangos de fechas para el mismo ámbito.
 * 4. MONEDA Y ARITMÉTICA D-069: 'PEN', BCMath 4 decimales.
 */
class TarifaAlojamientoServicio
{
    private PDO $pdo;
    private TarifaAlojamientoRepositorio $tarifaRepo;
    private PropiedadRepositorio $propiedadRepo;
    private UnidadRepositorio $unidadRepo;
    private TipoUnidadRepositorio $tipoUnidadRepo;
    private AuditoriaServicio $auditoriaServicio;

    public function __construct(
        ?PDO $pdo = null,
        ?TarifaAlojamientoRepositorio $tarifaRepo = null,
        ?PropiedadRepositorio $propiedadRepo = null,
        ?UnidadRepositorio $unidadRepo = null,
        ?TipoUnidadRepositorio $tipoUnidadRepo = null,
        ?AuditoriaServicio $auditoriaServicio = null
    ) {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->tarifaRepo = $tarifaRepo ?? new TarifaAlojamientoRepositorio($this->pdo);
        $this->propiedadRepo = $propiedadRepo ?? new PropiedadRepositorio($this->pdo);
        $this->unidadRepo = $unidadRepo ?? new UnidadRepositorio($this->pdo);
        $this->tipoUnidadRepo = $tipoUnidadRepo ?? new TipoUnidadRepositorio($this->pdo);
        $this->auditoriaServicio = $auditoriaServicio ?? new AuditoriaServicio($this->pdo);
    }

    /**
     * Crea una nueva tarifa de alojamiento validando ámbito, vigencia y anti-solapamiento.
     *
     * @param array<string, mixed> $datos
     * @param int $creadoPorActorId
     * @return TarifaAlojamiento
     * @throws ValidacionTarifaExcepcion
     * @throws ConflictoTarifaExcepcion
     */
    public function crearTarifa(array $datos, int $creadoPorActorId): TarifaAlojamiento
    {
        $datosValidados = $this->validarYNormalizarDatosTarifa($datos);

        $propiedadId = $datosValidados['propiedad_id'];
        $ambito = $datosValidados['ambito_tipo'];
        $tipoUnidadId = $datosValidados['tipo_unidad_id'];
        $unidadId = $datosValidados['unidad_id'];
        $fechaInicio = $datosValidados['vigencia_desde'];
        $fechaFin = $datosValidados['vigencia_hasta'];

        $debeCerrarTx = !$this->pdo->inTransaction();
        if ($debeCerrarTx) {
            $this->pdo->beginTransaction();
        }

        try {
            // 1. Bloqueo pesimista sobre las tarifas activas del mismo ámbito exacto
            $existentes = $this->tarifaRepo->bloquearTarifasAmbitoParaValidacion(
                $propiedadId,
                $ambito,
                $tipoUnidadId,
                $unidadId
            );

            // 2. Verificar solapamiento temporal
            $this->verificarSolapamientoTemporal($existentes, $fechaInicio, $fechaFin);

            // 3. Crear modelo y persistir
            $tarifa = new TarifaAlojamiento(
                id: null,
                propiedadId: $propiedadId,
                ambitoTipo: $ambito,
                tipoUnidadId: $tipoUnidadId,
                unidadId: $unidadId,
                nombre: $datosValidados['nombre'],
                precioNoche: $datosValidados['precio_noche'],
                vigenciaDesde: $fechaInicio,
                vigenciaHasta: $fechaFin,
                monedaCodigo: $datosValidados['moneda_codigo'],
                estado: TarifaAlojamiento::ESTADO_ACTIVO,
                creadoPorActorId: $creadoPorActorId
            );

            $id = $this->tarifaRepo->crearTarifa($tarifa);

            // 4. Auditoría transversal inmutable
            $this->auditoriaServicio->registrar(
                accion: 'CREAR',
                modulo: 'tarifas',
                entidad: 'tarifas_alojamiento',
                entidadId: (string) $id,
                descripcion: "Tarifa '{$tarifa->obtenerNombre()}' (Ámbito: {$ambito}, S/ {$tarifa->obtenerPrecioNoche()}) creada para propiedad ID {$propiedadId}.",
                valoresAnteriores: null,
                valoresNuevos: $tarifa->aArreglo(),
                contexto: null,
                actor: $creadoPorActorId,
                usuarioId: null,
                correlacionId: null,
                pdoTransaccional: $this->pdo
            );

            if ($debeCerrarTx) {
                $this->pdo->commit();
            }

            $tarifaCreada = $this->tarifaRepo->buscarPorId($id);
            if ($tarifaCreada === null) {
                throw new TarifaNoEncontradaExcepcion("Error al recuperar la tarifa creada ID {$id}");
            }

            return $tarifaCreada;
        } catch (Throwable $e) {
            if ($debeCerrarTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Actualiza una tarifa existente verificando anti-solapamiento.
     *
     * @param int $id
     * @param array<string, mixed> $datos
     * @param int $actorId
     * @return TarifaAlojamiento
     */
    public function actualizarTarifa(int $id, array $datos, int $actorId): TarifaAlojamiento
    {
        $tarifaActual = $this->tarifaRepo->buscarPorId($id);
        if ($tarifaActual === null) {
            throw new TarifaNoEncontradaExcepcion("Tarifa con ID {$id} inexistente.");
        }

        $datosCombinados = array_merge($tarifaActual->aArreglo(), $datos);
        $datosValidados = $this->validarYNormalizarDatosTarifa($datosCombinados);

        $propiedadId = $datosValidados['propiedad_id'];
        $ambito = $datosValidados['ambito_tipo'];
        $tipoUnidadId = $datosValidados['tipo_unidad_id'];
        $unidadId = $datosValidados['unidad_id'];
        $fechaInicio = $datosValidados['vigencia_desde'];
        $fechaFin = $datosValidados['vigencia_hasta'];

        $debeCerrarTx = !$this->pdo->inTransaction();
        if ($debeCerrarTx) {
            $this->pdo->beginTransaction();
        }

        try {
            $existentes = $this->tarifaRepo->bloquearTarifasAmbitoParaValidacion(
                $propiedadId,
                $ambito,
                $tipoUnidadId,
                $unidadId,
                $id // Excluir la tarifa actual de la colisión
            );

            if ($datosValidados['estado'] === TarifaAlojamiento::ESTADO_ACTIVO) {
                $this->verificarSolapamientoTemporal($existentes, $fechaInicio, $fechaFin);
            }

            $actualizada = new TarifaAlojamiento(
                id: $id,
                propiedadId: $propiedadId,
                ambitoTipo: $ambito,
                tipoUnidadId: $tipoUnidadId,
                unidadId: $unidadId,
                nombre: $datosValidados['nombre'],
                precioNoche: $datosValidados['precio_noche'],
                vigenciaDesde: $fechaInicio,
                vigenciaHasta: $fechaFin,
                monedaCodigo: $datosValidados['moneda_codigo'],
                estado: $datosValidados['estado'],
                creadoPorActorId: $tarifaActual->obtenerCreadoPorActorId()
            );

            $this->tarifaRepo->actualizarTarifa($actualizada);

            $this->auditoriaServicio->registrar(
                accion: 'ACTUALIZAR',
                modulo: 'tarifas',
                entidad: 'tarifas_alojamiento',
                entidadId: (string) $id,
                descripcion: "Tarifa ID {$id} ('{$actualizada->obtenerNombre()}') actualizada.",
                valoresAnteriores: $tarifaActual->aArreglo(),
                valoresNuevos: $actualizada->aArreglo(),
                contexto: null,
                actor: $actorId,
                usuarioId: null,
                correlacionId: null,
                pdoTransaccional: $this->pdo
            );

            if ($debeCerrarTx) {
                $this->pdo->commit();
            }

            return $this->tarifaRepo->buscarPorId($id) ?? $actualizada;
        } catch (Throwable $e) {
            if ($debeCerrarTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Resuelve soberanamente la tarifa efectiva para una noche hotelera específica.
     *
     * Jerarquía obligatoria:
     * 1. UNIDAD (Override físico si existe)
     * 2. TIPO_UNIDAD (Tarifa de la categoría)
     * 3. PROPIEDAD (Tarifa base de la sede)
     *
     * @param int $propiedadId
     * @param int|null $tipoUnidadId
     * @param int|null $unidadId
     * @param string $fecha Y-m-d
     * @return TarifaAlojamiento
     * @throws TarifaNoEncontradaExcepcion
     */
    public function resolverTarifaParaFecha(
        int $propiedadId,
        ?int $tipoUnidadId,
        ?int $unidadId,
        string $fecha
    ): TarifaAlojamiento {
        // Si no se proporcionó tipoUnidadId pero sí unidadId, inferirlo
        if ($tipoUnidadId === null && $unidadId !== null && $unidadId > 0) {
            $unidad = $this->unidadRepo->buscarPorId($unidadId);
            if ($unidad !== null) {
                $tipoUnidadId = $unidad->obtenerTipoUnidadId();
            }
        }

        $candidatas = $this->tarifaRepo->buscarTarifasCandidatasEnFecha(
            $propiedadId,
            $tipoUnidadId,
            $unidadId,
            $fecha
        );

        if (empty($candidatas)) {
            $detalle = "propiedad ID {$propiedadId}";
            if ($tipoUnidadId !== null) {
                $detalle .= ", tipo de unidad ID {$tipoUnidadId}";
            }
            if ($unidadId !== null) {
                $detalle .= ", unidad ID {$unidadId}";
            }
            throw new TarifaNoEncontradaExcepcion("No existe ninguna tarifa activa vigente para la fecha {$fecha} ({$detalle}).");
        }

        // La consulta SQL ya ordena rigurosamente: UNIDAD (1) > TIPO_UNIDAD (2) > PROPIEDAD (3), y vigencia_desde DESC
        return $candidatas[0];
    }

    public function cambiarEstado(int $id, string $estado, int $actorId): TarifaAlojamiento
    {
        $tarifa = $this->tarifaRepo->buscarPorId($id);
        if ($tarifa === null) {
            throw new TarifaNoEncontradaExcepcion("Tarifa con ID {$id} inexistente.");
        }

        $estadoNormalizado = strtoupper(trim($estado));
        if (!in_array($estadoNormalizado, [TarifaAlojamiento::ESTADO_ACTIVO, TarifaAlojamiento::ESTADO_INACTIVO], true)) {
            throw new ValidacionTarifaExcepcion("Estado '{$estado}' inválido.");
        }

        // Si se va a activar, verificar que no genere solapamiento
        if ($estadoNormalizado === TarifaAlojamiento::ESTADO_ACTIVO && !$tarifa->estaActiva()) {
            $existentes = $this->tarifaRepo->bloquearTarifasAmbitoParaValidacion(
                $tarifa->obtenerPropiedadId(),
                $tarifa->obtenerAmbitoTipo(),
                $tarifa->obtenerTipoUnidadId(),
                $tarifa->obtenerUnidadId(),
                $id
            );
            $this->verificarSolapamientoTemporal(
                $existentes,
                $tarifa->obtenerVigenciaDesde(),
                $tarifa->obtenerVigenciaHasta()
            );
        }

        $this->tarifaRepo->cambiarEstado($id, $estadoNormalizado);

        $this->auditoriaServicio->registrar(
            accion: 'CAMBIAR_ESTADO',
            modulo: 'tarifas',
            entidad: 'tarifas_alojamiento',
            entidadId: (string) $id,
            descripcion: "Estado de tarifa ID {$id} cambiado a {$estadoNormalizado}.",
            valoresAnteriores: ['estado' => $tarifa->obtenerEstado()],
            valoresNuevos: ['estado' => $estadoNormalizado],
            contexto: null,
            actor: $actorId
        );

        return $this->tarifaRepo->buscarPorId($id) ?? $tarifa;
    }

    /**
     * @return array<int, TarifaAlojamiento>
     */
    public function listarPorPropiedad(int $propiedadId, ?string $estado = null): array
    {
        return $this->tarifaRepo->listarPorPropiedad($propiedadId, $estado);
    }

    public function buscarPorId(int $id): ?TarifaAlojamiento
    {
        return $this->tarifaRepo->buscarPorId($id);
    }

    // =========================================================================
    // Métodos Auxiliares y Validaciones
    // =========================================================================

    /**
     * @param array<int, TarifaAlojamiento> $existentes
     * @param string $fechaInicio Y-m-d
     * @param string|null $fechaFin Y-m-d|null
     * @throws ConflictoTarifaExcepcion
     */
    private function verificarSolapamientoTemporal(array $existentes, string $fechaInicio, ?string $fechaFin): void
    {
        foreach ($existentes as $te) {
            $eInicio = $te->obtenerVigenciaDesde();
            $eFin = $te->obtenerVigenciaHasta();

            // Dos intervalos [A_ini, A_fin] y [B_ini, B_fin] se solapan si:
            // (A_fin es null || B_ini <= A_fin) && (B_fin es null || A_ini <= B_fin)
            $solapa = true;
            if ($fechaFin !== null && $eInicio > $fechaFin) {
                $solapa = false;
            }
            if ($eFin !== null && $fechaInicio > $eFin) {
                $solapa = false;
            }

            if ($solapa) {
                $rangoExistente = $eInicio . ' al ' . ($eFin ?? 'indefinido');
                $rangoNuevo = $fechaInicio . ' al ' . ($fechaFin ?? 'indefinido');
                throw new ConflictoTarifaExcepcion(
                    "Conflicto de vigencia tarifaria: ya existe la tarifa activa ID {$te->obtenerId()} ('{$te->obtenerNombre()}') vigente del {$rangoExistente}, la cual se solapa con el intervalo solicitado [{$rangoNuevo}]."
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $datos
     * @return array<string, mixed>
     * @throws ValidacionTarifaExcepcion
     */
    private function validarYNormalizarDatosTarifa(array $datos): array
    {
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        if ($nombre === '') {
            throw new ValidacionTarifaExcepcion('El nombre de la tarifa es obligatorio.');
        }

        $propiedadId = (int) ($datos['propiedad_id'] ?? 0);
        $propiedad = $this->propiedadRepo->buscarPorId($propiedadId);
        if ($propiedad === null) {
            throw new ValidacionTarifaExcepcion("La propiedad con ID {$propiedadId} no existe.");
        }

        $ambito = strtoupper(trim((string) ($datos['ambito_tipo'] ?? AmbitoTarifaAlojamiento::TIPO_UNIDAD)));
        if (!AmbitoTarifaAlojamiento::esValido($ambito)) {
            throw new ValidacionTarifaExcepcion("Ámbito tarifario '{$ambito}' no válido.");
        }

        $tipoUnidadId = isset($datos['tipo_unidad_id']) && (int) $datos['tipo_unidad_id'] > 0
            ? (int) $datos['tipo_unidad_id']
            : null;

        $unidadId = isset($datos['unidad_id']) && (int) $datos['unidad_id'] > 0
            ? (int) $datos['unidad_id']
            : null;

        if ($ambito === AmbitoTarifaAlojamiento::UNIDAD) {
            if ($unidadId === null) {
                throw new ValidacionTarifaExcepcion("El ámbito 'UNIDAD' exige especificar 'unidad_id'.");
            }
            $unidad = $this->unidadRepo->buscarPorId($unidadId);
            if ($unidad === null) {
                throw new ValidacionTarifaExcepcion("La unidad con ID {$unidadId} no existe.");
            }
            if ((int) $unidad->obtenerPropiedadId() !== $propiedadId) {
                throw new ValidacionTarifaExcepcion("La unidad ID {$unidadId} no pertenece a la propiedad ID {$propiedadId}.");
            }
            // En override de unidad, tipo_unidad_id se deduce o limpia
            $tipoUnidadId = $unidad->obtenerTipoUnidadId();
        } elseif ($ambito === AmbitoTarifaAlojamiento::TIPO_UNIDAD) {
            if ($tipoUnidadId === null) {
                throw new ValidacionTarifaExcepcion("El ámbito 'TIPO_UNIDAD' exige especificar 'tipo_unidad_id'.");
            }
            $tipoUnidad = $this->tipoUnidadRepo->buscarPorId($tipoUnidadId);
            if ($tipoUnidad === null) {
                throw new ValidacionTarifaExcepcion("El tipo de unidad con ID {$tipoUnidadId} no existe.");
            }
            $unidadId = null;
        } else {
            // PROPIEDAD
            $tipoUnidadId = null;
            $unidadId = null;
        }

        $precioNoche = trim((string) ($datos['precio_noche'] ?? ''));
        if (!is_numeric($precioNoche) || bccomp($precioNoche, '0.0000', 4) < 0) {
            throw new ValidacionTarifaExcepcion('El precio por noche debe ser un valor numérico mayor o igual a 0.');
        }
        $precioNocheNormalizado = number_format((float) $precioNoche, 4, '.', '');

        $fechaInicio = trim((string) ($datos['vigencia_desde'] ?? ''));
        $objInicio = DateTimeImmutable::createFromFormat('Y-m-d', $fechaInicio);
        if (!$objInicio || $objInicio->format('Y-m-d') !== $fechaInicio) {
            throw new ValidacionTarifaExcepcion("La fecha 'vigencia_desde' debe tener formato YYYY-MM-DD.");
        }

        $fechaFin = isset($datos['vigencia_hasta']) && trim((string) $datos['vigencia_hasta']) !== ''
            ? trim((string) $datos['vigencia_hasta'])
            : null;

        if ($fechaFin !== null) {
            $objFin = DateTimeImmutable::createFromFormat('Y-m-d', $fechaFin);
            if (!$objFin || $objFin->format('Y-m-d') !== $fechaFin) {
                throw new ValidacionTarifaExcepcion("La fecha 'vigencia_hasta' debe tener formato YYYY-MM-DD.");
            }
            if ($fechaFin < $fechaInicio) {
                throw new ValidacionTarifaExcepcion("La fecha 'vigencia_hasta' ({$fechaFin}) no puede ser anterior a 'vigencia_desde' ({$fechaInicio}).");
            }
        }

        $moneda = strtoupper(trim((string) ($datos['moneda_codigo'] ?? 'PEN')));
        if ($moneda !== 'PEN') {
            throw new ValidacionTarifaExcepcion("La moneda de alojamiento debe ser 'PEN' (D-069).");
        }

        $estado = strtoupper(trim((string) ($datos['estado'] ?? TarifaAlojamiento::ESTADO_ACTIVO)));

        return [
            'nombre' => $nombre,
            'propiedad_id' => $propiedadId,
            'ambito_tipo' => $ambito,
            'tipo_unidad_id' => $tipoUnidadId,
            'unidad_id' => $unidadId,
            'precio_noche' => $precioNocheNormalizado,
            'vigencia_desde' => $fechaInicio,
            'vigencia_hasta' => $fechaFin,
            'moneda_codigo' => $moneda,
            'estado' => $estado,
        ];
    }
}
