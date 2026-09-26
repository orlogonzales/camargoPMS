<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

use CamargoPMS\Excepciones\PropiedadDuplicadaExcepcion;
use CamargoPMS\Excepciones\PropiedadNoEncontradaExcepcion;
use CamargoPMS\Excepciones\ValidacionExcepcion;
use CamargoPMS\Modelos\AccionAuditoria;
use CamargoPMS\Modelos\ActorAuditoria;
use CamargoPMS\Modelos\Propiedad;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Repositorios\PaisRepositorio;
use CamargoPMS\Repositorios\PropiedadRepositorio;
use PDO;
use Throwable;

/**
 * Servicio de dominio central para la gestión de Propiedades e Inmuebles Físicos (PROPIEDADES-1).
 *
 * Principios vinculantes:
 * - PROPIEDAD != UNIDAD: La propiedad es el inmueble contenedor, no la unidad alojable.
 * - PROPIEDAD != REGISTRO DESECHABLE: Conservación histórica mediante ACTIVO <-> INACTIVO. Cero DELETE físico.
 * - AUDITORÍA D-061: Toda mutación resuelve el actor humano USR_x del usuario ejecutor.
 */
class PropiedadServicio
{
    private PDO $pdo;
    private PropiedadRepositorio $propiedadRepo;
    private AuditoriaServicio $auditoriaServicio;
    private ConfiguracionServicio $configServicio;
    private PaisRepositorio $paisRepo;

    public function __construct(
        ?PDO $pdo = null,
        ?PropiedadRepositorio $propiedadRepo = null,
        ?AuditoriaServicio $auditoriaServicio = null,
        ?ConfiguracionServicio $configServicio = null,
        ?PaisRepositorio $paisRepo = null
    ) {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->propiedadRepo = $propiedadRepo ?? new PropiedadRepositorio($this->pdo);
        $this->auditoriaServicio = $auditoriaServicio ?? new AuditoriaServicio($this->pdo);
        $this->configServicio = $configServicio ?? new ConfiguracionServicio($this->pdo);
        $this->paisRepo = $paisRepo ?? new PaisRepositorio($this->pdo);
    }

    /**
     * Lista propiedades aplicando filtros de búsqueda y paginación administrativa.
     *
     * @param string|null $busqueda
     * @param string|null $estado
     * @param int|null $paisId
     * @param int|null $pagina
     * @param int|null $limite
     * @return array{propiedades: array<int, array<string, mixed>>, total: int, pagina: int, limite: int, paginas: int}
     */
    public function listar(
        ?string $busqueda = null,
        ?string $estado = null,
        ?int $paisId = null,
        ?int $pagina = 1,
        ?int $limite = null
    ): array {
        $paginaActual = max(1, $pagina ?? 1);
        $limitePorPagina = $limite !== null && $limite > 0
            ? $limite
            : (int) $this->configServicio->obtener('operacion.paginacion_predeterminada', 15);

        $offset = ($paginaActual - 1) * $limitePorPagina;

        $total = $this->propiedadRepo->contar($busqueda, $estado, $paisId);
        $propiedades = $this->propiedadRepo->listar($busqueda, $estado, $paisId, $limitePorPagina, $offset);

        $paginas = $total > 0 ? (int) ceil($total / $limitePorPagina) : 1;

        return [
            'propiedades' => array_map(static fn(Propiedad $p) => $p->aArreglo(), $propiedades),
            'total' => $total,
            'pagina' => $paginaActual,
            'limite' => $limitePorPagina,
            'paginas' => $paginas,
        ];
    }

    /**
     * Busca y retorna una propiedad por su ID primario.
     *
     * @param int $id
     * @return Propiedad
     * @throws PropiedadNoEncontradaExcepcion
     */
    public function obtenerPorId(int $id): Propiedad
    {
        if ($id <= 0) {
            throw new PropiedadNoEncontradaExcepcion($id);
        }

        $propiedad = $this->propiedadRepo->buscarPorId($id);
        if ($propiedad === null) {
            throw new PropiedadNoEncontradaExcepcion($id);
        }

        return $propiedad;
    }

    /**
     * Busca una propiedad por su código técnico único.
     *
     * @param string $codigo
     * @return Propiedad|null
     */
    public function obtenerPorCodigo(string $codigo): ?Propiedad
    {
        $codigoNormalizado = trim(strtoupper($codigo));
        if ($codigoNormalizado === '') {
            return null;
        }

        return $this->propiedadRepo->buscarPorCodigo($codigoNormalizado);
    }

    /**
     * Registra una nueva propiedad en el maestro central.
     *
     * @param array<string, mixed> $datos
     * @param int|null $ejecutadoPorUsuarioId
     * @return Propiedad
     * @throws ValidacionExcepcion
     * @throws PropiedadDuplicadaExcepcion
     */
    public function crear(array $datos, ?int $ejecutadoPorUsuarioId = null): Propiedad
    {
        $datosValidados = $this->validarDatos($datos);

        $codigo = $datosValidados['codigo'];
        if ($this->propiedadRepo->existeCodigo($codigo)) {
            throw new PropiedadDuplicadaExcepcion('código', $codigo);
        }

        $propiedad = new Propiedad(
            null,
            $codigo,
            $datosValidados['nombre'],
            $datosValidados['descripcion'] ?? null,
            $datosValidados['pais_id'] ?? 1,
            $datosValidados['departamento'] ?? null,
            $datosValidados['provincia'] ?? null,
            $datosValidados['distrito'] ?? null,
            $datosValidados['direccion'] ?? null,
            $datosValidados['referencia'] ?? null,
            $datosValidados['latitud'] ?? null,
            $datosValidados['longitud'] ?? null,
            $datosValidados['estado'] ?? 'ACTIVO',
            $datosValidados['observaciones'] ?? null
        );

        $propiedadPersistida = $this->propiedadRepo->insertar($propiedad);

        // Auditoría transversal D-061
        try {
            $actorEjecutor = $this->resolverActorEjecutor($ejecutadoPorUsuarioId);
            $this->auditoriaServicio->registrar(
                AccionAuditoria::CREAR,
                'propiedades',
                'propiedades',
                (string) $propiedadPersistida->obtenerId(),
                "Creación de la propiedad '{$propiedadPersistida->obtenerNombre()}' (Código: {$propiedadPersistida->obtenerCodigo()})",
                null,
                $propiedadPersistida->aArreglo(),
                null,
                $actorEjecutor,
                $ejecutadoPorUsuarioId,
                null,
                $this->pdo
            );
        } catch (Throwable) {
            // No interrumpir si la auditoría informativa no crítica falla
        }

        return $propiedadPersistida;
    }

    /**
     * Actualiza la información de una propiedad existente.
     *
     * @param int $id
     * @param array<string, mixed> $datos
     * @param int|null $ejecutadoPorUsuarioId
     * @return Propiedad
     * @throws PropiedadNoEncontradaExcepcion
     * @throws ValidacionExcepcion
     * @throws PropiedadDuplicadaExcepcion
     */
    public function actualizar(int $id, array $datos, ?int $ejecutadoPorUsuarioId = null): Propiedad
    {
        $propiedadExistente = $this->obtenerPorId($id);

        $this->validarDatos($datos, true);

        $nuevoCodigo = isset($datos['codigo']) ? trim(strtoupper((string) $datos['codigo'])) : $propiedadExistente->obtenerCodigo();
        if ($nuevoCodigo !== $propiedadExistente->obtenerCodigo() && $this->propiedadRepo->existeCodigo($nuevoCodigo, $id)) {
            throw new PropiedadDuplicadaExcepcion('código', $nuevoCodigo);
        }

        $propiedadActualizada = new Propiedad(
            $id,
            $nuevoCodigo,
            isset($datos['nombre']) ? trim((string) $datos['nombre']) : $propiedadExistente->obtenerNombre(),
            array_key_exists('descripcion', $datos) ? (trim((string) $datos['descripcion']) !== '' ? trim((string) $datos['descripcion']) : null) : $propiedadExistente->obtenerDescripcion(),
            isset($datos['pais_id']) ? (int) $datos['pais_id'] : $propiedadExistente->obtenerPaisId(),
            array_key_exists('departamento', $datos) ? (trim((string) $datos['departamento']) !== '' ? trim((string) $datos['departamento']) : null) : $propiedadExistente->obtenerDepartamento(),
            array_key_exists('provincia', $datos) ? (trim((string) $datos['provincia']) !== '' ? trim((string) $datos['provincia']) : null) : $propiedadExistente->obtenerProvincia(),
            array_key_exists('distrito', $datos) ? (trim((string) $datos['distrito']) !== '' ? trim((string) $datos['distrito']) : null) : $propiedadExistente->obtenerDistrito(),
            isset($datos['direccion']) ? trim((string) $datos['direccion']) : $propiedadExistente->obtenerDireccion(),
            array_key_exists('referencia', $datos) ? (trim((string) $datos['referencia']) !== '' ? trim((string) $datos['referencia']) : null) : $propiedadExistente->obtenerReferencia(),
            array_key_exists('latitud', $datos) ? ($datos['latitud'] !== null && $datos['latitud'] !== '' ? (float) $datos['latitud'] : null) : $propiedadExistente->obtenerLatitud(),
            array_key_exists('longitud', $datos) ? ($datos['longitud'] !== null && $datos['longitud'] !== '' ? (float) $datos['longitud'] : null) : $propiedadExistente->obtenerLongitud(),
            isset($datos['estado']) ? (strtoupper(trim((string) $datos['estado'])) === 'INACTIVO' ? 'INACTIVO' : 'ACTIVO') : $propiedadExistente->obtenerEstado(),
            array_key_exists('observaciones', $datos) ? (trim((string) $datos['observaciones']) !== '' ? trim((string) $datos['observaciones']) : null) : $propiedadExistente->obtenerObservaciones()
        );

        $this->propiedadRepo->actualizar($propiedadActualizada);
        $propiedadFinal = $this->obtenerPorId($id);

        // Auditoría transversal D-061: Solo auditar si hubo diferencias reales en los datos
        $valoresAnteriores = $propiedadExistente->aArreglo();
        $valoresNuevos = $propiedadFinal->aArreglo();
        $compAnt = $valoresAnteriores;
        $compNue = $valoresNuevos;
        unset($compAnt['actualizado_en'], $compNue['actualizado_en']);

        $hayDiferencias = false;
        foreach ($compNue as $clave => $valNuevo) {
            if (!array_key_exists($clave, $compAnt) || $compAnt[$clave] !== $valNuevo) {
                $hayDiferencias = true;
                break;
            }
        }

        if ($hayDiferencias) {
            try {
                $actorEjecutor = $this->resolverActorEjecutor($ejecutadoPorUsuarioId);
                $this->auditoriaServicio->registrar(
                    AccionAuditoria::EDITAR,
                    'propiedades',
                    'propiedades',
                    (string) $id,
                    "Actualización de la propiedad '{$propiedadFinal->obtenerNombre()}' (Código: {$propiedadFinal->obtenerCodigo()})",
                    $valoresAnteriores,
                    $valoresNuevos,
                    null,
                    $actorEjecutor,
                    $ejecutadoPorUsuarioId,
                    null,
                    $this->pdo
                );
            } catch (Throwable) {
                // No interrumpir si la auditoría informativa no crítica falla
            }
        }

        return $propiedadFinal;
    }

    /**
     * Alterna o asigna el estado operativo de una propiedad (ACTIVO / INACTIVO).
     *
     * Principio Vinculante: PROPIEDAD != REGISTRO DESECHABLE.
     * La baja de una propiedad se gestiona exclusivamente como desactivación lógica.
     *
     * @param int $id
     * @param string $nuevoEstado 'ACTIVO' o 'INACTIVO'
     * @param string|null $motivo Justificación opcional del cambio
     * @param int|null $ejecutadoPorUsuarioId
     * @return Propiedad
     * @throws PropiedadNoEncontradaExcepcion
     * @throws ValidacionExcepcion
     */
    public function cambiarEstado(
        int $id,
        string $nuevoEstado,
        ?string $motivo = null,
        ?int $ejecutadoPorUsuarioId = null
    ): Propiedad {
        $propiedad = $this->obtenerPorId($id);
        $estadoNormalizado = strtoupper(trim($nuevoEstado));

        if (!in_array($estadoNormalizado, ['ACTIVO', 'INACTIVO'], true)) {
            throw new ValidacionExcepcion("El estado '{$nuevoEstado}' no es válido para una propiedad. Se admite ACTIVO o INACTIVO.");
        }

        if ($propiedad->obtenerEstado() === $estadoNormalizado) {
            return $propiedad;
        }

        $this->propiedadRepo->cambiarEstado($id, $estadoNormalizado);
        $propiedadActualizada = $this->obtenerPorId($id);

        // Auditoría transversal D-061
        try {
            $actorEjecutor = $this->resolverActorEjecutor($ejecutadoPorUsuarioId);
            $accion = $estadoNormalizado === 'ACTIVO' ? AccionAuditoria::ACTIVAR : AccionAuditoria::DESACTIVAR;
            $descripcion = "Cambio de estado operativo a {$estadoNormalizado} de la propiedad '{$propiedad->obtenerNombre()}'";
            if ($motivo !== null && trim($motivo) !== '') {
                $descripcion .= '. Motivo: ' . trim($motivo);
            }

            $contexto = $motivo !== null && trim($motivo) !== '' ? ['motivo' => trim($motivo)] : null;

            $this->auditoriaServicio->registrar(
                $accion,
                'propiedades',
                'propiedades',
                (string) $id,
                $descripcion,
                ['estado' => $propiedad->obtenerEstado()],
                ['estado' => $estadoNormalizado],
                $contexto,
                $actorEjecutor,
                $ejecutadoPorUsuarioId,
                null,
                $this->pdo
            );
        } catch (Throwable) {
            // No interrumpir si la auditoría informativa no crítica falla
        }

        return $propiedadActualizada;
    }

    /**
     * Valida sintáctica y semánticamente los datos de una propiedad y retorna los datos normalizados.
     *
     * @param array<string, mixed> $datos
     * @param bool $esActualizacion
     * @return array<string, mixed>
     * @throws ValidacionExcepcion
     */
    public function validarDatos(array $datos, bool $esActualizacion = false): array
    {
        $errores = [];
        $normalizados = [];

        // Código técnico
        if (!$esActualizacion || isset($datos['codigo'])) {
            $codigo = trim((string) ($datos['codigo'] ?? ''));
            if ($codigo === '') {
                $errores['codigo'] = 'El código de la propiedad es obligatorio.';
            } elseif (strlen($codigo) < 2 || strlen($codigo) > 50) {
                $errores['codigo'] = 'El código debe tener entre 2 y 50 caracteres.';
            } elseif (!preg_match('/^[A-Z0-9_-]+$/i', $codigo)) {
                $errores['codigo'] = 'El código solo puede contener letras, números, guiones y guiones bajos.';
            } else {
                $normalizados['codigo'] = strtoupper($codigo);
            }
        }

        // Nombre administrativo
        if (!$esActualizacion || isset($datos['nombre'])) {
            $nombre = trim((string) ($datos['nombre'] ?? ''));
            if ($nombre === '') {
                $errores['nombre'] = 'El nombre de la propiedad es obligatorio.';
            } elseif (mb_strlen($nombre, 'UTF-8') < 3 || mb_strlen($nombre, 'UTF-8') > 150) {
                $errores['nombre'] = 'El nombre debe tener entre 3 y 150 caracteres.';
            } else {
                $normalizados['nombre'] = $nombre;
            }
        }

        // País
        if (!$esActualizacion || isset($datos['pais_id'])) {
            $paisId = isset($datos['pais_id']) ? (int) $datos['pais_id'] : 0;
            if ($paisId <= 0) {
                $errores['pais_id'] = 'Debe seleccionar un país válido.';
            } elseif ($this->paisRepo !== null && $this->paisRepo->buscarPorId($paisId) === null) {
                $errores['pais_id'] = 'El país seleccionado no existe en el catálogo.';
            } else {
                $normalizados['pais_id'] = $paisId;
            }
        }

        // Departamento, Provincia, Distrito
        foreach (['departamento', 'provincia', 'distrito'] as $campoGeo) {
            if (isset($datos[$campoGeo])) {
                $val = trim((string) $datos[$campoGeo]);
                $normalizados[$campoGeo] = $val !== '' ? $val : null;
            }
        }

        // Dirección física (obligatoria para inmueble físico según DDL)
        if (!$esActualizacion || isset($datos['direccion'])) {
            $direccion = trim((string) ($datos['direccion'] ?? ''));
            if ($direccion === '') {
                $errores['direccion'] = 'La dirección física de la propiedad es obligatoria.';
            } elseif (mb_strlen($direccion, 'UTF-8') < 3 || mb_strlen($direccion, 'UTF-8') > 255) {
                $errores['direccion'] = 'La dirección debe tener entre 3 y 255 caracteres.';
            } else {
                $normalizados['direccion'] = $direccion;
            }
        }

        // Referencia (opcional)
        if (isset($datos['referencia'])) {
            $referencia = trim((string) $datos['referencia']);
            if ($referencia !== '' && mb_strlen($referencia, 'UTF-8') > 255) {
                $errores['referencia'] = 'La referencia no puede exceder 255 caracteres.';
            } else {
                $normalizados['referencia'] = $referencia !== '' ? $referencia : null;
            }
        }

        // Coordenadas GPS (Latitud y Longitud)
        if (isset($datos['latitud']) && $datos['latitud'] !== '' && $datos['latitud'] !== null) {
            if (!is_numeric($datos['latitud'])) {
                $errores['latitud'] = 'La latitud debe ser un valor numérico.';
            } else {
                $lat = (float) $datos['latitud'];
                if ($lat < -90.0 || $lat > 90.0) {
                    $errores['latitud'] = 'La latitud debe encontrarse en el rango de -90.0 a 90.0 grados.';
                } else {
                    $normalizados['latitud'] = $lat;
                }
            }
        } else {
            $normalizados['latitud'] = null;
        }

        if (isset($datos['longitud']) && $datos['longitud'] !== '' && $datos['longitud'] !== null) {
            if (!is_numeric($datos['longitud'])) {
                $errores['longitud'] = 'La longitud debe ser un valor numérico.';
            } else {
                $lon = (float) $datos['longitud'];
                if ($lon < -180.0 || $lon > 180.0) {
                    $errores['longitud'] = 'La longitud debe encontrarse en el rango de -180.0 a 180.0 grados.';
                } else {
                    $normalizados['longitud'] = $lon;
                }
            }
        } else {
            $normalizados['longitud'] = null;
        }

        // Descripción y Observaciones
        if (isset($datos['descripcion'])) {
            $desc = trim((string) $datos['descripcion']);
            $normalizados['descripcion'] = $desc !== '' ? $desc : null;
        }
        if (isset($datos['observaciones'])) {
            $obs = trim((string) $datos['observaciones']);
            $normalizados['observaciones'] = $obs !== '' ? $obs : null;
        }

        // Estado
        if (isset($datos['estado'])) {
            $estado = strtoupper(trim((string) $datos['estado']));
            if (!in_array($estado, ['ACTIVO', 'INACTIVO'], true)) {
                $errores['estado'] = "El estado debe ser 'ACTIVO' o 'INACTIVO'.";
            } else {
                $normalizados['estado'] = $estado;
            }
        }

        if (!empty($errores)) {
            throw new ValidacionExcepcion('Errores en los datos de la propiedad.', $errores);
        }

        return $normalizados;
    }

    /**
     * Resuelve el actor de auditoría correspondiente al usuario ejecutor (D-061).
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
