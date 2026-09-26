<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

use CamargoPMS\Excepciones\UnidadDuplicadaExcepcion;
use CamargoPMS\Excepciones\UnidadNoEncontradaExcepcion;
use CamargoPMS\Excepciones\ValidacionExcepcion;
use CamargoPMS\Modelos\ActorAuditoria;
use CamargoPMS\Modelos\TipoActor;
use CamargoPMS\Modelos\Unidad;
use CamargoPMS\Repositorios\PropiedadRepositorio;
use CamargoPMS\Repositorios\TipoUnidadRepositorio;
use CamargoPMS\Repositorios\UnidadRepositorio;
use PDO;
use Throwable;

/**
 * Servicio de dominio para la gestión del maestro de unidades físicas alojables.
 *
 * Principios vinculantes:
 * - PROPIEDAD ≠ UNIDAD: Delimita estrictamente las divisiones físicas del contenedor.
 * - UNIDAD ≠ REGISTRO DESECHABLE: Cero eliminación física; preservación de trazabilidad.
 * - UNIDAD ≠ DISPONIBILIDAD / TARIFA / RESERVA: P-004, P-005 y P-006 se preservan pendientes.
 * - D-061: Resolución canónica de actor humano ejecutor (USR_x) y transaccionalidad atómica.
 */
class UnidadServicio
{
    private UnidadRepositorio $unidadRepositorio;
    private PropiedadRepositorio $propiedadRepositorio;
    private TipoUnidadRepositorio $tipoUnidadRepositorio;
    private AuditoriaServicio $auditoriaServicio;
    private PDO $pdo;

    public function __construct(
        ?PDO $pdo = null,
        ?UnidadRepositorio $unidadRepositorio = null,
        ?PropiedadRepositorio $propiedadRepositorio = null,
        ?TipoUnidadRepositorio $tipoUnidadRepositorio = null,
        ?AuditoriaServicio $auditoriaServicio = null
    ) {
        $this->pdo = $pdo ?? \CamargoPMS\Nucleo\BaseDatos::conexion();
        $this->unidadRepositorio = $unidadRepositorio ?? new UnidadRepositorio($this->pdo);
        $this->propiedadRepositorio = $propiedadRepositorio ?? new PropiedadRepositorio($this->pdo);
        $this->tipoUnidadRepositorio = $tipoUnidadRepositorio ?? new TipoUnidadRepositorio($this->pdo);
        $this->auditoriaServicio = $auditoriaServicio ?? new AuditoriaServicio($this->pdo);
    }

    /**
     * Obtiene una unidad por su identificador primario.
     *
     * @throws UnidadNoEncontradaExcepcion
     */
    public function obtenerPorId(int $id): Unidad
    {
        $unidad = $this->unidadRepositorio->buscarPorId($id);
        if ($unidad === null) {
            throw new UnidadNoEncontradaExcepcion("No se encontró la unidad especificada (ID: {$id}).");
        }

        return $unidad;
    }

    /**
     * Lista unidades aplicando filtros multicriterio y paginación.
     *
     * @param array<string, mixed> $filtros
     * @param int $pagina
     * @param int $limite
     * @return array{unidades: array<Unidad>, total: int, pagina: int, limite: int, total_paginas: int}
     */
    public function listar(array $filtros = [], int $pagina = 1, int $limite = 20): array
    {
        $pagina = max(1, $pagina);
        $limite = max(1, min(100, $limite));

        $total = $this->unidadRepositorio->contar($filtros);
        $unidades = $this->unidadRepositorio->listar($filtros, $pagina, $limite);
        $totalPaginas = (int)ceil($total / $limite);

        return [
            'unidades' => $unidades,
            'total' => $total,
            'pagina' => $pagina,
            'limite' => $limite,
            'total_paginas' => max(1, $totalPaginas),
        ];
    }

    /**
     * Lista todas las unidades pertenecientes a una propiedad específica.
     *
     * @return array<Unidad>
     */
    public function listarPorPropiedad(int $propiedadId, ?string $estado = null): array
    {
        return $this->unidadRepositorio->listarPorPropiedad($propiedadId, $estado);
    }

    /**
     * Lista los tipos de unidad activos del catálogo.
     *
     * @return array<\CamargoPMS\Modelos\TipoUnidad>
     */
    public function listarTiposUnidad(): array
    {
        return $this->tipoUnidadRepositorio->listarActivos();
    }

    /**
     * Registra una nueva unidad habitacional física bajo una propiedad.
     *
     * @param array<string, mixed> $datos
     * @param int|null $ejecutadoPorUsuarioId
     * @return Unidad
     *
     * @throws ValidacionExcepcion
     * @throws UnidadDuplicadaExcepcion
     */
    public function crear(array $datos, ?int $ejecutadoPorUsuarioId = null): Unidad
    {
        $datosValidados = $this->validarDatos($datos, null, true);

        $unidad = new Unidad(
            id: null,
            propiedadId: $datosValidados['propiedad_id'],
            tipoUnidadId: $datosValidados['tipo_unidad_id'],
            codigo: $datosValidados['codigo'],
            nombre: $datosValidados['nombre'],
            descripcion: $datosValidados['descripcion'],
            pisoNivel: $datosValidados['piso_nivel'],
            capacidadPersonas: $datosValidados['capacidad_personas'],
            dormitorios: $datosValidados['dormitorios'],
            banos: $datosValidados['banos'],
            areaM2: $datosValidados['area_m2'],
            estado: 'ACTIVO',
            observaciones: $datosValidados['observaciones']
        );

        $this->pdo->beginTransaction();
        try {
            $id = $this->unidadRepositorio->insertar($unidad);
            $unidad->fijarId($id);

            // Trazabilidad D-061
            $actor = $this->resolverActorEjecutor($ejecutadoPorUsuarioId);
            $this->auditoriaServicio->registrar(
                accion: 'CREAR',
                modulo: 'unidades',
                entidad: 'unidades',
                entidadId: (string)$id,
                descripcion: "Creación de la unidad '{$unidad->obtenerCodigo()}' ({$unidad->obtenerNombre()}) en la propiedad ID {$unidad->obtenerPropiedadId()}",
                valoresAnteriores: null,
                valoresNuevos: $unidad->aArreglo(),
                contexto: null,
                actor: $actor,
                usuarioId: $ejecutadoPorUsuarioId,
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
     * Actualiza la información técnica y descriptiva de una unidad existente.
     *
     * @param int $id
     * @param array<string, mixed> $datos
     * @param int|null $ejecutadoPorUsuarioId
     * @return Unidad
     *
     * @throws UnidadNoEncontradaExcepcion
     * @throws ValidacionExcepcion
     * @throws UnidadDuplicadaExcepcion
     */
    public function actualizar(int $id, array $datos, ?int $ejecutadoPorUsuarioId = null): Unidad
    {
        $unidadActual = $this->obtenerPorId($id);
        $datosValidados = $this->validarDatos($datos, $id, false, $unidadActual->obtenerPropiedadId());

        $valoresAnteriores = $unidadActual->aArreglo();

        $unidadActual->fijarTipoUnidadId($datosValidados['tipo_unidad_id']);
        $unidadActual->fijarCodigo($datosValidados['codigo']);
        $unidadActual->fijarNombre($datosValidados['nombre']);
        $unidadActual->fijarDescripcion($datosValidados['descripcion']);
        $unidadActual->fijarPisoNivel($datosValidados['piso_nivel']);
        $unidadActual->fijarCapacidadPersonas($datosValidados['capacidad_personas']);
        $unidadActual->fijarDormitorios($datosValidados['dormitorios']);
        $unidadActual->fijarBanos($datosValidados['banos']);
        $unidadActual->fijarAreaM2($datosValidados['area_m2']);
        $unidadActual->fijarObservaciones($datosValidados['observaciones']);

        $valoresNuevos = $unidadActual->aArreglo();

        // Cálculo diferencial de cambios ignorando timestamps y metadatos relacionales
        $clavesIgnoradas = [
            'id', 'creado_en', 'actualizado_en', 'resumen_fisico',
            'propiedad_nombre', 'propiedad_codigo', 'propiedad_estado',
            'tipo_unidad_codigo', 'tipo_unidad_nombre'
        ];
        $diffAnterior = [];
        $diffNuevo = [];
        foreach ($valoresNuevos as $k => $v) {
            if (in_array($k, $clavesIgnoradas, true)) {
                continue;
            }
            $vAnt = $valoresAnteriores[$k] ?? null;
            if (is_numeric($v) && is_numeric($vAnt)) {
                if ((float)$v !== (float)$vAnt) {
                    $diffAnterior[$k] = $vAnt;
                    $diffNuevo[$k] = $v;
                }
            } elseif ($v !== $vAnt) {
                $diffAnterior[$k] = $vAnt;
                $diffNuevo[$k] = $v;
            }
        }

        $this->pdo->beginTransaction();
        try {
            $this->unidadRepositorio->actualizar($unidadActual);

            // Solo emitir auditoría si hubo modificaciones efectivas
            if (!empty($diffNuevo)) {
                $actor = $this->resolverActorEjecutor($ejecutadoPorUsuarioId);
                $this->auditoriaServicio->registrar(
                    accion: 'EDITAR',
                    modulo: 'unidades',
                    entidad: 'unidades',
                    entidadId: (string)$id,
                    descripcion: "Actualización de información de la unidad '{$unidadActual->obtenerCodigo()}'",
                    valoresAnteriores: $diffAnterior,
                    valoresNuevos: $diffNuevo,
                    contexto: null,
                    actor: $actor,
                    usuarioId: $ejecutadoPorUsuarioId,
                    correlacionId: null,
                    pdoTransaccional: $this->pdo
                );
            }

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
     * Alterna el estado operacional de una unidad (ACTIVO ↔ INACTIVO).
     *
     * Principio vinculante: UNIDAD ≠ REGISTRO DESECHABLE.
     * Cero eliminación física.
     *
     * @param int $id
     * @param string $nuevoEstado
     * @param string|null $motivo
     * @param int|null $ejecutadoPorUsuarioId
     * @return Unidad
     *
     * @throws UnidadNoEncontradaExcepcion
     * @throws ValidacionExcepcion
     */
    public function cambiarEstado(int $id, string $nuevoEstado, ?string $motivo = null, ?int $ejecutadoPorUsuarioId = null): Unidad
    {
        $unidad = $this->obtenerPorId($id);
        $estadoNormalizado = strtoupper(trim($nuevoEstado));

        if (!in_array($estadoNormalizado, ['ACTIVO', 'INACTIVO'], true)) {
            throw new ValidacionExcepcion('Estado inválido.', [
                'estado' => "El estado '{$nuevoEstado}' no es válido. Los estados permitidos son ACTIVO e INACTIVO.",
            ]);
        }

        if ($unidad->obtenerEstado() === $estadoNormalizado) {
            return $unidad; // Idempotente
        }

        $estadoAnterior = $unidad->obtenerEstado();
        $accionAuditoria = $estadoNormalizado === 'ACTIVO' ? 'ACTIVAR' : 'DESACTIVAR';

        $this->pdo->beginTransaction();
        try {
            $this->unidadRepositorio->cambiarEstado($id, $estadoNormalizado);
            $unidad->fijarEstado($estadoNormalizado);

            $actor = $this->resolverActorEjecutor($ejecutadoPorUsuarioId);
            $contexto = $motivo !== null && trim($motivo) !== '' ? ['motivo' => trim($motivo)] : null;

            $this->auditoriaServicio->registrar(
                accion: $accionAuditoria,
                modulo: 'unidades',
                entidad: 'unidades',
                entidadId: (string)$id,
                descripcion: "Cambio de estado de la unidad '{$unidad->obtenerCodigo()}' de {$estadoAnterior} a {$estadoNormalizado}" .
                    ($motivo ? ". Motivo: " . trim($motivo) : ''),
                valoresAnteriores: ['estado' => $estadoAnterior],
                valoresNuevos: ['estado' => $estadoNormalizado],
                contexto: $contexto,
                actor: $actor,
                usuarioId: $ejecutadoPorUsuarioId,
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
     * Valida exhaustivamente los datos de entrada para creación o edición de una unidad.
     *
     * @param array<string, mixed> $datos
     * @param int|null $unidadId
     * @param bool $esCreacion
     * @param int|null $propiedadIdFijo
     * @return array<string, mixed>
     *
     * @throws ValidacionExcepcion
     * @throws UnidadDuplicadaExcepcion
     */
    public function validarDatos(array $datos, ?int $unidadId = null, bool $esCreacion = true, ?int $propiedadIdFijo = null): array
    {
        $errores = [];

        // 1. Propiedad
        $propiedadId = $propiedadIdFijo ?? (isset($datos['propiedad_id']) ? (int)$datos['propiedad_id'] : 0);
        if ($propiedadId <= 0) {
            $errores['propiedad_id'][] = 'Debe seleccionar una propiedad física válida.';
        } else {
            $propiedad = $this->propiedadRepositorio->buscarPorId($propiedadId);
            if ($propiedad === null) {
                $errores['propiedad_id'][] = 'La propiedad seleccionada no existe en el sistema.';
            } elseif ($esCreacion && !$propiedad->estaActiva()) {
                // Regla vinculante: No se pueden registrar nuevas unidades en una propiedad inactiva
                $errores['propiedad_id'][] = 'No se pueden registrar nuevas unidades en una propiedad que se encuentra inactiva.';
            }
        }

        // 2. Tipo de Unidad
        $tipoUnidadId = isset($datos['tipo_unidad_id']) ? (int)$datos['tipo_unidad_id'] : 0;
        if ($tipoUnidadId <= 0) {
            $errores['tipo_unidad_id'][] = 'Debe seleccionar un tipo de unidad válido.';
        } else {
            $tipoUnidad = $this->tipoUnidadRepositorio->buscarPorId($tipoUnidadId);
            if ($tipoUnidad === null || !$tipoUnidad->estaActivo()) {
                $errores['tipo_unidad_id'][] = 'El tipo de unidad seleccionado no es válido o está inactivo.';
            }
        }

        // 3. Código
        $codigo = strtoupper(trim((string)($datos['codigo'] ?? '')));
        if ($codigo === '') {
            $errores['codigo'][] = 'El código de la unidad es obligatorio.';
        } elseif (strlen($codigo) < 1 || strlen($codigo) > 50) {
            $errores['codigo'][] = 'El código debe tener entre 1 y 50 caracteres.';
        } elseif (!preg_match('/^[A-Z0-9\-_.\/ ]+$/', $codigo)) {
            $errores['codigo'][] = 'El código solo puede contener letras mayúsculas, números, espacios, guiones y barras.';
        } elseif ($propiedadId > 0 && $this->unidadRepositorio->existeCodigoEnPropiedad($propiedadId, $codigo, $unidadId)) {
            // Unicidad scoped por propiedad: UNIQUE(propiedad_id, codigo)
            throw new UnidadDuplicadaExcepcion($codigo, $propiedadId);
        }

        // 4. Nombre
        $nombre = trim((string)($datos['nombre'] ?? ''));
        if ($nombre === '') {
            $errores['nombre'][] = 'El nombre descriptivo de la unidad es obligatorio.';
        } elseif (strlen($nombre) < 2 || strlen($nombre) > 150) {
            $errores['nombre'][] = 'El nombre debe tener entre 2 y 150 caracteres.';
        }

        // 5. Capacidad de Personas
        $capacidad = isset($datos['capacidad_personas']) ? (int)$datos['capacidad_personas'] : 1;
        if ($capacidad < 1) {
            $errores['capacidad_personas'][] = 'La capacidad física debe ser al menos de 1 persona.';
        } elseif ($capacidad > 100) {
            $errores['capacidad_personas'][] = 'La capacidad física no puede superar las 100 personas.';
        }

        // 6. Dormitorios
        $dormitorios = isset($datos['dormitorios']) ? (int)$datos['dormitorios'] : 1;
        if ($dormitorios < 0) {
            $errores['dormitorios'][] = 'El número de dormitorios no puede ser negativo.';
        } elseif ($dormitorios > 50) {
            $errores['dormitorios'][] = 'El número de dormitorios no puede superar 50.';
        }

        // 7. Baños
        $banos = isset($datos['banos']) ? (float)$datos['banos'] : 1.0;
        if ($banos < 0.0) {
            $errores['banos'][] = 'El número de baños no puede ser negativo.';
        } elseif ($banos > 50.0) {
            $errores['banos'][] = 'El número de baños no puede superar 50.';
        }

        // 8. Área m2 (opcional)
        $areaM2 = null;
        if (isset($datos['area_m2']) && trim((string)$datos['area_m2']) !== '') {
            $areaFloat = (float)$datos['area_m2'];
            if ($areaFloat <= 0.0) {
                $errores['area_m2'][] = 'El área en m² debe ser un valor positivo mayor a 0.';
            } elseif ($areaFloat > 99999.99) {
                $errores['area_m2'][] = 'El área en m² no puede exceder 99,999.99 m².';
            } else {
                $areaM2 = round($areaFloat, 2);
            }
        }

        // 9. Piso / Nivel (opcional)
        $pisoNivel = isset($datos['piso_nivel']) && trim((string)$datos['piso_nivel']) !== ''
            ? trim((string)$datos['piso_nivel'])
            : null;
        if ($pisoNivel !== null && strlen($pisoNivel) > 30) {
            $errores['piso_nivel'][] = 'El piso o nivel no puede superar los 30 caracteres.';
        }

        // 10. Campos descriptivos opcionales
        $descripcion = isset($datos['descripcion']) && trim((string)$datos['descripcion']) !== ''
            ? trim((string)$datos['descripcion'])
            : null;

        $observaciones = isset($datos['observaciones']) && trim((string)$datos['observaciones']) !== ''
            ? trim((string)$datos['observaciones'])
            : null;

        if (!empty($errores)) {
            throw new ValidacionExcepcion('Existen errores de validación en los datos de la unidad.', $errores);
        }

        return [
            'propiedad_id' => $propiedadId,
            'tipo_unidad_id' => $tipoUnidadId,
            'codigo' => $codigo,
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'piso_nivel' => $pisoNivel,
            'capacidad_personas' => $capacidad,
            'dormitorios' => $dormitorios,
            'banos' => $banos,
            'area_m2' => $areaM2,
            'observaciones' => $observaciones,
        ];
    }

    /**
     * Resuelve semánticamente el ActorAuditoria ejecutor garantizando el cumplimiento de D-061.
     *
     * @param int|null $usuarioId
     * @return ActorAuditoria|null
     */
    private function resolverActorEjecutor(?int $usuarioId): ?ActorAuditoria
    {
        if ($usuarioId === null || $usuarioId <= 0) {
            return null;
        }

        return $this->auditoriaServicio->obtenerOAsegurarActorUsuario($usuarioId, $this->pdo);
    }
}
