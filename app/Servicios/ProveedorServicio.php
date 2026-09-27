<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

use CamargoPMS\Excepciones\ProveedorNoEncontradoExcepcion;
use CamargoPMS\Excepciones\ValidacionExcepcion;
use CamargoPMS\Modelos\AccionAuditoria;
use CamargoPMS\Modelos\Proveedor;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Repositorios\PersonaRepositorio;
use CamargoPMS\Repositorios\ProveedorRepositorio;
use PDO;
use Throwable;

/**
 * Servicio de dominio para la gestión del maestro de proveedores externos de servicios.
 *
 * Principios vinculantes:
 * - Cero proveedor "INTERNO" ficticio (D-071/D-010).
 * - Cero DELETE físico.
 * - Validación de identidad y unicidad de RUC / documentos.
 * - Trazabilidad D-061.
 */
class ProveedorServicio
{
    private PDO $pdo;
    private ProveedorRepositorio $proveedorRepo;
    private PersonaRepositorio $personaRepo;
    private AuditoriaServicio $auditoriaServicio;

    public function __construct(
        ?PDO $pdo = null,
        ?ProveedorRepositorio $proveedorRepo = null,
        ?PersonaRepositorio $personaRepo = null,
        ?AuditoriaServicio $auditoriaServicio = null
    ) {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->proveedorRepo = $proveedorRepo ?? new ProveedorRepositorio($this->pdo);
        $this->personaRepo = $personaRepo ?? new PersonaRepositorio($this->pdo);
        $this->auditoriaServicio = $auditoriaServicio ?? new AuditoriaServicio($this->pdo);
    }

    /**
     * Lista proveedores con filtros opcionales.
     *
     * @param array<string, mixed> $filtros
     * @return array<int, Proveedor>
     */
    public function listar(array $filtros = []): array
    {
        return $this->proveedorRepo->listar($filtros);
    }

    /**
     * Obtiene un proveedor por ID o lanza excepción.
     */
    public function obtenerPorId(int $id): Proveedor
    {
        $proveedor = $this->proveedorRepo->buscarPorId($id);
        if (!$proveedor) {
            throw new ProveedorNoEncontradoExcepcion("No se encontró el proveedor con ID {$id}.");
        }
        return $proveedor;
    }

    /**
     * Registra un nuevo proveedor externo.
     *
     * @param array<string, mixed> $datos
     */
    public function crear(array $datos, int $actorId): Proveedor
    {
        $razonSocial = trim((string) ($datos['razon_social'] ?? ''));
        if ($razonSocial === '') {
            throw new ValidacionExcepcion('La razón social o nombre legal del proveedor es obligatoria.');
        }

        $tipo = strtoupper(trim((string) ($datos['tipo'] ?? 'EMPRESA')));
        if (!in_array($tipo, ['EMPRESA', 'PERSONA_NATURAL'], true)) {
            throw new ValidacionExcepcion("El tipo de proveedor '{$tipo}' no es válido. Debe ser EMPRESA o PERSONA_NATURAL.");
        }

        $personaId = isset($datos['persona_id']) && $datos['persona_id'] !== '' ? (int) $datos['persona_id'] : null;
        if ($tipo === 'PERSONA_NATURAL' && $personaId !== null) {
            $persona = $this->personaRepo->buscarPorId($personaId);
            if (!$persona) {
                throw new ValidacionExcepcion("La persona natural vinculada ID {$personaId} no existe en el registro central.");
            }
        }

        // Generar o validar código
        $codigo = trim(strtoupper((string) ($datos['codigo'] ?? '')));
        if ($codigo === '') {
            $codigo = $this->generarCodigoProveedor();
        } elseif ($this->proveedorRepo->existeCodigo($codigo)) {
            throw new ValidacionExcepcion("El código de proveedor '{$codigo}' ya se encuentra registrado.");
        }

        // Validar unicidad de documento fiscal si viene provisto
        $numeroDocumento = isset($datos['numero_documento']) ? trim((string) $datos['numero_documento']) : null;
        if ($numeroDocumento !== null && $numeroDocumento !== '') {
            if ($this->proveedorRepo->existeDocumento($numeroDocumento)) {
                throw new ValidacionExcepcion("El número de documento '{$numeroDocumento}' ya está registrado con otro proveedor.");
            }
        }

        // Validar formato de email
        $email = isset($datos['email']) && trim((string) $datos['email']) !== '' ? trim((string) $datos['email']) : null;
        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidacionExcepcion("El correo electrónico '{$email}' no tiene un formato válido.");
        }

        $proveedor = new Proveedor(
            id: null,
            codigo: $codigo,
            tipo: $tipo,
            personaId: $personaId,
            razonSocial: $razonSocial,
            nombreComercial: isset($datos['nombre_comercial']) ? trim((string) $datos['nombre_comercial']) : null,
            numeroDocumento: $numeroDocumento,
            email: $email,
            telefono: isset($datos['telefono']) ? trim((string) $datos['telefono']) : null,
            direccion: isset($datos['direccion']) ? trim((string) $datos['direccion']) : null,
            estado: strtoupper(trim((string) ($datos['estado'] ?? 'ACTIVO'))),
            observaciones: isset($datos['observaciones']) ? trim((string) $datos['observaciones']) : null
        );

        $this->pdo->beginTransaction();
        try {
            $id = $this->proveedorRepo->crear($proveedor);

            $actorIdFinal = $this->resolverActorId($actorId);

            $this->auditoriaServicio->registrar(
                accion: AccionAuditoria::CREAR,
                modulo: 'servicios',
                entidad: 'proveedores',
                entidadId: (string) $id,
                descripcion: "Proveedor externo creado: '{$codigo}' - {$razonSocial} ({$tipo})",
                valoresAnteriores: null,
                valoresNuevos: $proveedor->haciaArreglo(),
                contexto: null,
                actor: $actorIdFinal,
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
     * Actualiza los datos de un proveedor existente.
     *
     * @param array<string, mixed> $datos
     */
    public function actualizar(int $id, array $datos, int $actorId): Proveedor
    {
        $proveedorActual = $this->obtenerPorId($id);
        $valoresAnteriores = $proveedorActual->haciaArreglo();

        $razonSocial = isset($datos['razon_social']) ? trim((string) $datos['razon_social']) : $proveedorActual->obtenerRazonSocial();
        if ($razonSocial === '') {
            throw new ValidacionExcepcion('La razón social o nombre legal del proveedor es obligatoria.');
        }

        $tipo = isset($datos['tipo']) ? strtoupper(trim((string) $datos['tipo'])) : $proveedorActual->obtenerTipo();
        if (!in_array($tipo, ['EMPRESA', 'PERSONA_NATURAL'], true)) {
            throw new ValidacionExcepcion("El tipo de proveedor '{$tipo}' no es válido.");
        }

        $personaId = array_key_exists('persona_id', $datos)
            ? ($datos['persona_id'] !== '' && $datos['persona_id'] !== null ? (int) $datos['persona_id'] : null)
            : $proveedorActual->obtenerPersonaId();

        if ($tipo === 'PERSONA_NATURAL' && $personaId !== null) {
            $persona = $this->personaRepo->buscarPorId($personaId);
            if (!$persona) {
                throw new ValidacionExcepcion("La persona natural vinculada ID {$personaId} no existe en el registro central.");
            }
        }

        $codigo = isset($datos['codigo']) ? trim(strtoupper((string) $datos['codigo'])) : $proveedorActual->obtenerCodigo();
        if ($codigo === '') {
            throw new ValidacionExcepcion('El código de proveedor no puede ser vacío.');
        }
        if ($this->proveedorRepo->existeCodigo($codigo, $id)) {
            throw new ValidacionExcepcion("El código de proveedor '{$codigo}' ya está asignado a otro proveedor.");
        }

        $numeroDocumento = array_key_exists('numero_documento', $datos)
            ? (trim((string) $datos['numero_documento']) !== '' ? trim((string) $datos['numero_documento']) : null)
            : $proveedorActual->obtenerNumeroDocumento();

        if ($numeroDocumento !== null && $this->proveedorRepo->existeDocumento($numeroDocumento, $id)) {
            throw new ValidacionExcepcion("El documento '{$numeroDocumento}' ya está registrado con otro proveedor.");
        }

        $email = array_key_exists('email', $datos)
            ? (trim((string) $datos['email']) !== '' ? trim((string) $datos['email']) : null)
            : $proveedorActual->obtenerEmail();
        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidacionExcepcion("El correo electrónico '{$email}' no tiene un formato válido.");
        }

        $proveedorActualizado = new Proveedor(
            id: $id,
            codigo: $codigo,
            tipo: $tipo,
            personaId: $personaId,
            razonSocial: $razonSocial,
            nombreComercial: array_key_exists('nombre_comercial', $datos) ? (trim((string) $datos['nombre_comercial']) ?: null) : $proveedorActual->obtenerNombreComercial(),
            numeroDocumento: $numeroDocumento,
            email: $email,
            telefono: array_key_exists('telefono', $datos) ? (trim((string) $datos['telefono']) ?: null) : $proveedorActual->obtenerTelefono(),
            direccion: array_key_exists('direccion', $datos) ? (trim((string) $datos['direccion']) ?: null) : $proveedorActual->obtenerDireccion(),
            estado: isset($datos['estado']) ? strtoupper(trim((string) $datos['estado'])) : $proveedorActual->obtenerEstado(),
            observaciones: array_key_exists('observaciones', $datos) ? (trim((string) $datos['observaciones']) ?: null) : $proveedorActual->obtenerObservaciones()
        );

        $this->pdo->beginTransaction();
        try {
            $this->proveedorRepo->actualizar($proveedorActualizado);

            $actorIdFinal = $this->resolverActorId($actorId);

            $this->auditoriaServicio->registrar(
                accion: AccionAuditoria::EDITAR,
                modulo: 'servicios',
                entidad: 'proveedores',
                entidadId: (string) $id,
                descripcion: "Proveedor ID {$id} ({$codigo}) actualizado",
                valoresAnteriores: $valoresAnteriores,
                valoresNuevos: $proveedorActualizado->haciaArreglo(),
                contexto: null,
                actor: $actorIdFinal,
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
     * Cambia el estado de un proveedor (cero eliminación física).
     */
    public function cambiarEstado(int $id, string $nuevoEstado, int $actorId): bool
    {
        $proveedor = $this->obtenerPorId($id);
        $nuevoEstado = strtoupper(trim($nuevoEstado));

        if (!in_array($nuevoEstado, ['ACTIVO', 'INACTIVO'], true)) {
            throw new ValidacionExcepcion("El estado '{$nuevoEstado}' no es válido. Debe ser ACTIVO o INACTIVO.");
        }

        if ($proveedor->obtenerEstado() === $nuevoEstado) {
            return true;
        }

        $accion = $nuevoEstado === 'ACTIVO' ? AccionAuditoria::ACTIVAR : AccionAuditoria::DESACTIVAR;

        $this->pdo->beginTransaction();
        try {
            $this->proveedorRepo->cambiarEstado($id, $nuevoEstado);

            $actorIdFinal = $this->resolverActorId($actorId);

            $this->auditoriaServicio->registrar(
                accion: $accion,
                modulo: 'servicios',
                entidad: 'proveedores',
                entidadId: (string) $id,
                descripcion: "Proveedor ID {$id} ({$proveedor->obtenerCodigo()}) pasó a {$nuevoEstado}",
                valoresAnteriores: ['estado' => $proveedor->obtenerEstado()],
                valoresNuevos: ['estado' => $nuevoEstado],
                contexto: null,
                actor: $actorIdFinal,
                usuarioId: null,
                correlacionId: null,
                pdoTransaccional: $this->pdo
            );

            $this->pdo->commit();
            return true;
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

    private function generarCodigoProveedor(): string
    {
        $stmt = $this->pdo->query("SELECT MAX(id) FROM proveedores");
        $siguienteId = ((int) $stmt->fetchColumn()) + 1;
        $codigo = sprintf('PROV-%03d', $siguienteId);

        // Prevenir colisión si se borraron IDs
        while ($this->proveedorRepo->existeCodigo($codigo)) {
            $siguienteId++;
            $codigo = sprintf('PROV-%03d', $siguienteId);
        }

        return $codigo;
    }
}
