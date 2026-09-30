<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

use CamargoPMS\Excepciones\ApiClientNoEncontradoExcepcion;
use CamargoPMS\Excepciones\CredencialApiInvalidaExcepcion;
use CamargoPMS\Excepciones\ValidacionExcepcion;
use CamargoPMS\Modelos\ActorAuditoria;
use CamargoPMS\Modelos\ApiClient;
use CamargoPMS\Modelos\ApiCredencial;
use CamargoPMS\Modelos\ContextoAutenticacionApi;
use CamargoPMS\Modelos\TipoActor;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Repositorios\ActorAuditoriaRepositorio;
use CamargoPMS\Repositorios\ApiClientRepositorio;
use CamargoPMS\Repositorios\ApiCredencialRepositorio;
use CamargoPMS\Repositorios\ApiScopeRepositorio;
use PDO;
use Throwable;

/**
 * Servicio de Dominio para la Gestión de Clientes API, Credenciales Técnicas y Scopes.
 *
 * Arquitectura Vinculante:
 * ACTOR INTEGRACION -> API_CLIENT -> CREDENCIAL TÉCNICA -> SCOPES
 * - Cero usuarios humanos ficticios.
 * - Cero almacenamiento de API keys en claro (hash SHA-256 para búsqueda O(1)).
 * - Emisión CSPRNG de secreto entregado una sola vez.
 * - Rotación y revocación atómica e inmutable.
 */
class ApiClientServicio
{
    private PDO $pdo;
    private ApiClientRepositorio $clientRepo;
    private ApiCredencialRepositorio $credencialRepo;
    private ApiScopeRepositorio $scopeRepo;
    private ActorAuditoriaRepositorio $actorRepo;
    private AuditoriaServicio $auditoriaServicio;

    public function __construct(
        ?PDO $pdo = null,
        ?ApiClientRepositorio $clientRepo = null,
        ?ApiCredencialRepositorio $credencialRepo = null,
        ?ApiScopeRepositorio $scopeRepo = null,
        ?ActorAuditoriaRepositorio $actorRepo = null,
        ?AuditoriaServicio $auditoriaServicio = null
    ) {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->scopeRepo = $scopeRepo ?? new ApiScopeRepositorio($this->pdo);
        $this->clientRepo = $clientRepo ?? new ApiClientRepositorio($this->pdo);
        $this->credencialRepo = $credencialRepo ?? new ApiCredencialRepositorio($this->pdo, $this->scopeRepo);
        $this->actorRepo = $actorRepo ?? new ActorAuditoriaRepositorio($this->pdo);
        $this->auditoriaServicio = $auditoriaServicio ?? new AuditoriaServicio($this->pdo);
    }

    /**
     * Registra un nuevo Cliente API vinculándolo a un actor técnico de tipo INTEGRACION.
     *
     * @param array<string, mixed> $datos
     * @param int $actorCreadorId
     * @return ApiClient
     */
    public function crearCliente(array $datos, int $actorCreadorId): ApiClient
    {
        $codigo = strtoupper(trim((string) ($datos['codigo'] ?? '')));
        if ($codigo === '') {
            throw new ValidacionExcepcion('El código del cliente API es obligatorio.');
        }

        if (!preg_match('/^[A-Z0-9_-]{3,60}$/', $codigo)) {
            throw new ValidacionExcepcion("El código '{$codigo}' debe contener entre 3 y 60 caracteres alfanuméricos, guiones o guiones bajos.");
        }

        if ($this->clientRepo->buscarPorCodigo($codigo) !== null) {
            throw new ValidacionExcepcion("Ya existe un cliente API con el código '{$codigo}'.");
        }

        $nombre = trim((string) ($datos['nombre'] ?? ''));
        if ($nombre === '') {
            throw new ValidacionExcepcion('El nombre descriptivo del cliente API es obligatorio.');
        }

        $descripcion = isset($datos['descripcion']) && trim((string) $datos['descripcion']) !== ''
            ? trim((string) $datos['descripcion'])
            : null;

        $email = isset($datos['contacto_email']) && trim((string) $datos['contacto_email']) !== ''
            ? trim((string) $datos['contacto_email'])
            : null;

        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidacionExcepcion("El correo electrónico '{$email}' no tiene un formato válido.");
        }

        $ips = isset($datos['ips_permitidas']) && trim((string) $datos['ips_permitidas']) !== ''
            ? trim((string) $datos['ips_permitidas'])
            : null;

        $limiteRpm = isset($datos['limite_peticiones_minuto']) && (int) $datos['limite_peticiones_minuto'] > 0
            ? (int) $datos['limite_peticiones_minuto']
            : 60;

        $debeCerrarTx = !$this->pdo->inTransaction();
        if ($debeCerrarTx) {
            $this->pdo->beginTransaction();
        }

        try {
            // 1. Crear o asociar Actor de Auditoría tipo INTEGRACION
            $actorCodigo = 'INT_API_' . $codigo;
            $actorExistente = $this->actorRepo->buscarPorCodigo($actorCodigo);

            if ($actorExistente === null) {
                $actorNuevo = new ActorAuditoria(
                    null,
                    TipoActor::INTEGRACION,
                    $actorCodigo,
                    "Cliente API: {$nombre}",
                    null,
                    'ACTIVO'
                );
                $actorInsertado = $this->actorRepo->insertar($actorNuevo);
                $actorId = (int) $actorInsertado->obtenerId();
            } else {
                $actorId = (int) $actorExistente->obtenerId();
            }

            // 2. Crear Cliente API
            $cliente = new ApiClient(
                id: null,
                actorId: $actorId,
                codigo: $codigo,
                nombre: $nombre,
                descripcion: $descripcion,
                contactoEmail: $email,
                ipsPermitidas: $ips,
                limitePeticionesMinuto: $limiteRpm,
                estado: ApiClient::ESTADO_ACTIVO
            );

            $clienteId = $this->clientRepo->insertar($cliente);

            // 3. Auditoría transversal inmutable
            $this->auditoriaServicio->registrar(
                accion: 'CREAR',
                modulo: 'api_clientes',
                entidad: 'api_clientes',
                entidadId: (string) $clienteId,
                descripcion: "Cliente API '{$nombre}' ({$codigo}) registrado exitosamente con Actor ID {$actorId}.",
                valoresAnteriores: null,
                valoresNuevos: $cliente->aArreglo(),
                contexto: null,
                actor: $actorCreadorId,
                usuarioId: null,
                correlacionId: null,
                pdoTransaccional: $this->pdo
            );

            if ($debeCerrarTx) {
                $this->pdo->commit();
            }

            return $this->clientRepo->buscarPorId($clienteId);
        } catch (Throwable $e) {
            if ($debeCerrarTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Genera una nueva credencial técnica Bearer para el cliente API.
     *
     * El secreto en claro se entrega ÚNICAMENTE en el array retornado y jamás se persiste.
     *
     * @param int $clienteId
     * @param string $nombreCredencial
     * @param array<int, string> $codigosScopes
     * @param string|null $expiraEn
     * @param int|null $actorCreadorId
     * @return array{credencial: ApiCredencial, token_secreto: string, scopes: array<string>}
     */
    public function crearCredencial(
        int $clienteId,
        string $nombreCredencial = 'Credencial Principal',
        array $codigosScopes = [],
        ?string $expiraEn = null,
        ?int $actorCreadorId = null
    ): array {
        $cliente = $this->clientRepo->buscarPorId($clienteId);
        if ($cliente === null) {
            throw new ApiClientNoEncontradoExcepcion("Cliente API con ID {$clienteId} no existe.");
        }

        if (!$cliente->estaActivo()) {
            throw new ValidacionExcepcion("No se pueden emitir credenciales para el cliente API inactivo '{$cliente->obtenerCodigo()}'.");
        }

        // Validar scopes solicitados contra catálogo oficial
        $scopesModelos = $this->scopeRepo->buscarPorCodigos($codigosScopes);
        $scopeIds = array_map(static fn($s) => (int) $s->obtenerId(), $scopesModelos);
        $codigosValidados = array_map(static fn($s) => $s->obtenerCodigo(), $scopesModelos);

        if (empty($scopeIds)) {
            throw new ValidacionExcepcion('Debe asignar al menos un alcance (scope) válido a la credencial técnica.');
        }

        // Generar token criptográfico CSPRNG de alta entropía (56 bytes hex)
        $tokenSecreto = 'cpms_live_' . bin2hex(random_bytes(24));
        $tokenHash = hash('sha256', $tokenSecreto);
        $tokenPrefijo = substr($tokenSecreto, 0, 16);
        $identificadorPublico = 'key_' . bin2hex(random_bytes(12));

        $debeCerrarTx = !$this->pdo->inTransaction();
        if ($debeCerrarTx) {
            $this->pdo->beginTransaction();
        }

        try {
            $credencial = new ApiCredencial(
                id: null,
                apiClienteId: $clienteId,
                identificadorPublico: $identificadorPublico,
                tokenHash: $tokenHash,
                tokenPrefijo: $tokenPrefijo,
                nombre: trim($nombreCredencial) ?: 'Credencial Principal',
                estado: ApiCredencial::ESTADO_ACTIVO,
                ultimoUsoEn: null,
                expiraEn: $expiraEn,
                creadoEn: null,
                revocadoEn: null,
                scopes: $codigosValidados
            );

            $credencialId = $this->credencialRepo->insertar($credencial, $scopeIds);

            // Auditoría (sin registrar jamás el token en claro)
            $this->auditoriaServicio->registrar(
                accion: 'CREAR_CREDENCIAL',
                modulo: 'api_clientes',
                entidad: 'api_credenciales',
                entidadId: (string) $credencialId,
                descripcion: "Credencial '{$credencial->obtenerNombre()}' ({$identificadorPublico}, prefijo: {$tokenPrefijo}...) emitida para cliente '{$cliente->obtenerCodigo()}'. Scopes: " . implode(', ', $codigosValidados),
                valoresAnteriores: null,
                valoresNuevos: [
                    'id' => $credencialId,
                    'identificador_publico' => $identificadorPublico,
                    'token_prefijo' => $tokenPrefijo,
                    'scopes' => $codigosValidados,
                    'expira_en' => $expiraEn,
                ],
                contexto: null,
                actor: $actorCreadorId,
                usuarioId: null,
                correlacionId: null,
                pdoTransaccional: $this->pdo
            );

            if ($debeCerrarTx) {
                $this->pdo->commit();
            }

            $credencialCreada = $this->credencialRepo->buscarPorId($credencialId);

            return [
                'credencial' => $credencialCreada ?? $credencial,
                'token_secreto' => $tokenSecreto,
                'scopes' => $codigosValidados,
            ];
        } catch (Throwable $e) {
            if ($debeCerrarTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Autentica una petición API validando el token Bearer en tiempo O(1) con hash SHA-256.
     *
     * @param string $tokenSecreto
     * @return ContextoAutenticacionApi|null
     */
    public function autenticarToken(string $tokenSecreto): ?ContextoAutenticacionApi
    {
        $tokenLimpio = trim($tokenSecreto);
        if ($tokenLimpio === '' || !str_starts_with($tokenLimpio, 'cpms_live_')) {
            return null;
        }

        $hash = hash('sha256', $tokenLimpio);
        $credencial = $this->credencialRepo->buscarPorTokenHash($hash);

        if ($credencial === null) {
            return null;
        }

        // Si expiró por tiempo, actualizar estado
        if ($credencial->obtenerExpiraEn() !== null && $credencial->obtenerExpiraEn() < date('Y-m-d H:i:s')) {
            $this->credencialRepo->expirar((int) $credencial->obtenerId());
            return null;
        }

        if (!$credencial->estaActiva()) {
            return null;
        }

        $cliente = $this->clientRepo->buscarPorId($credencial->obtenerApiClientId());
        if ($cliente === null || !$cliente->estaActivo()) {
            return null;
        }

        $actor = $this->actorRepo->buscarPorId($cliente->obtenerActorId());
        if ($actor === null || !$actor->esActivo()) {
            return null;
        }

        // Actualizar marca de último uso
        $this->credencialRepo->actualizarUltimoUso((int) $credencial->obtenerId());

        return new ContextoAutenticacionApi(
            cliente: $cliente,
            credencial: $credencial,
            actor: $actor,
            scopes: $credencial->obtenerScopes()
        );
    }

    /**
     * Rota una credencial técnica generando una nueva con los mismos scopes y revocando la anterior.
     *
     * @param int $credencialId
     * @param string|null $expiraEnAntigua Período de gracia para la credencial vieja antes de apagarse
     * @param int|null $actorId
     * @return array{credencial: ApiCredencial, token_secreto: string, scopes: array<string>}
     */
    public function rotarCredencial(
        int $credencialId,
        ?string $expiraEnAntigua = null,
        ?int $actorId = null
    ): array {
        $credencialVieja = $this->credencialRepo->buscarPorId($credencialId);
        if ($credencialVieja === null) {
            throw new CredencialApiInvalidaExcepcion("Credencial con ID {$credencialId} no encontrada.");
        }

        $clienteId = $credencialVieja->obtenerApiClientId();
        $scopes = $credencialVieja->obtenerScopes();
        $nombreNueva = $credencialVieja->obtenerNombre() . ' (Rotada ' . date('Y-m-d') . ')';

        $resultado = $this->crearCredencial(
            $clienteId,
            $nombreNueva,
            $scopes,
            null,
            $actorId
        );

        // Desactivar o fijar gracia en la credencial anterior
        if ($expiraEnAntigua === null) {
            $this->credencialRepo->revocar($credencialId);
        } else {
            // Se le asigna fecha de caducidad cercana
            $stmt = $this->pdo->prepare('UPDATE api_credenciales SET expira_en = :expira WHERE id = :id');
            $stmt->execute(['expira' => $expiraEnAntigua, 'id' => $credencialId]);
        }

        return $resultado;
    }

    /**
     * Revoca formalmente una credencial técnica.
     *
     * @param int $credencialId
     * @param string $motivo
     * @param int|null $actorId
     * @return bool
     */
    public function revocarCredencial(int $credencialId, string $motivo = 'Revocación manual', ?int $actorId = null): bool
    {
        $credencial = $this->credencialRepo->buscarPorId($credencialId);
        if ($credencial === null) {
            throw new CredencialApiInvalidaExcepcion("Credencial con ID {$credencialId} no encontrada.");
        }

        $ok = $this->credencialRepo->revocar($credencialId);

        if ($ok) {
            $this->auditoriaServicio->registrar(
                accion: 'REVOCAR_CREDENCIAL',
                modulo: 'api_clientes',
                entidad: 'api_credenciales',
                entidadId: (string) $credencialId,
                descripcion: "Credencial '{$credencial->obtenerIdentificadorPublico()}' revocada. Motivo: {$motivo}.",
                valoresAnteriores: ['estado' => $credencial->obtenerEstado()],
                valoresNuevos: ['estado' => ApiCredencial::ESTADO_REVOCADO],
                contexto: ['motivo' => $motivo],
                actor: $actorId
            );
        }

        return $ok;
    }

    /**
     * @return array<int, ApiClient>
     */
    public function listarClientes(?string $estado = null): array
    {
        return $this->clientRepo->listar($estado);
    }

    /**
     * @return array<int, ApiCredencial>
     */
    public function listarCredencialesPorCliente(int $clienteId): array
    {
        return $this->credencialRepo->listarPorCliente($clienteId);
    }

    /**
     * @return array<int, \CamargoPMS\Modelos\ApiScope>
     */
    public function listarScopes(): array
    {
        return $this->scopeRepo->listarActivos();
    }
}
