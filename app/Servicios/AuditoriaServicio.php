<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

use CamargoPMS\Excepciones\ActorDuplicadoExcepcion;
use CamargoPMS\Excepciones\ActorNoEncontradoExcepcion;
use CamargoPMS\Excepciones\AuditoriaExcepcion;
use CamargoPMS\Excepciones\EntidadNoEncontradaExcepcion;
use CamargoPMS\Modelos\ActorAuditoria;
use CamargoPMS\Modelos\RegistroAuditoria;
use CamargoPMS\Modelos\TipoActor;
use CamargoPMS\Modelos\Usuario;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Repositorios\ActorAuditoriaRepositorio;
use CamargoPMS\Repositorios\AuditoriaRepositorio;
use CamargoPMS\Repositorios\UsuarioRepositorio;
use PDO;
use Throwable;

/**
 * Servicio transversal de auditoría, trazabilidad y registro inmutable de eventos.
 *
 * Principios Vinculantes:
 * - ACTOR != USUARIO: Los actores abarcan identidades humanas, procesos de sistema,
 *   integraciones API y webhooks de pagos sin crear cuentas de usuario ficticias.
 * - INMUTABILIDAD: El historial de auditoría no se modifica ni se destruye desde la aplicación.
 * - SANITIZACIÓN CENTRAL: Ninguna credencial, hash, token o secreto técnico se persiste en claro.
 * - ATOMICIDAD: La auditoría crítica acompaña transaccionalmente a la operación de dominio.
 */
class AuditoriaServicio
{
    private PDO $pdo;
    private AuditoriaRepositorio $auditoriaRepo;
    private ActorAuditoriaRepositorio $actorRepo;
    private SanitizadorAuditoria $sanitizador;
    private UsuarioRepositorio $usuarioRepo;

    private ?ActorAuditoria $actorActual = null;
    private ?string $correlacionIdActual = null;

    /**
     * Código estable del actor estructural del sistema.
     */
    public const CODIGO_ACTOR_SISTEMA = 'CAMARGO_PMS';

    public function __construct(
        ?PDO $pdo = null,
        ?AuditoriaRepositorio $auditoriaRepo = null,
        ?ActorAuditoriaRepositorio $actorRepo = null,
        ?SanitizadorAuditoria $sanitizador = null,
        ?UsuarioRepositorio $usuarioRepo = null
    ) {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->auditoriaRepo = $auditoriaRepo ?? new AuditoriaRepositorio($this->pdo);
        $this->actorRepo = $actorRepo ?? new ActorAuditoriaRepositorio($this->pdo);
        $this->sanitizador = $sanitizador ?? new SanitizadorAuditoria();
        $this->usuarioRepo = $usuarioRepo ?? new UsuarioRepositorio($this->pdo);
    }

    /**
     * Registra un evento de auditoría estructurado e inmutable.
     *
     * @param string $accion Acción normalizada (ej. 'CREAR', 'EDITAR', 'LOGIN')
     * @param string $modulo Módulo funcional (ej. 'auth', 'seguridad', 'menu')
     * @param string $entidad Nombre de la entidad afectada (ej. 'usuario', 'opcion_menu')
     * @param string|int|null $entidadId Identificador del registro afectado
     * @param string|null $descripcion Detalle legible de la operación
     * @param array<string, mixed>|null $valoresAnteriores Estado previo (sanitizado automáticamente)
     * @param array<string, mixed>|null $valoresNuevos Estado posterior (sanitizado automáticamente)
     * @param array<string, mixed>|null $contexto Metadatos contextuales adicionales
     * @param ActorAuditoria|int|string|null $actor Actor emisor (instancia, ID o código). Si es null, se infiere
     * @param int|null $usuarioId ID del usuario humano si aplica explícitamente
     * @param string|null $correlacionId ID de correlación cruzada de eventos
     * @param PDO|null $pdoTransaccional Conexión transaccional activa si la operación debe ser atómica
     * @return RegistroAuditoria
     * @throws ActorNoEncontradoExcepcion
     * @throws AuditoriaExcepcion
     */
    public function registrar(
        string $accion,
        string $modulo,
        string $entidad,
        string|int|null $entidadId = null,
        ?string $descripcion = null,
        ?array $valoresAnteriores = null,
        ?array $valoresNuevos = null,
        ?array $contexto = null,
        ActorAuditoria|int|string|null $actor = null,
        ?int $usuarioId = null,
        ?string $correlacionId = null,
        ?PDO $pdoTransaccional = null
    ): RegistroAuditoria {
        $pdo = $pdoTransaccional ?? $this->pdo;

        // 1. Validación de campos obligatorios
        $accionNormalizada = strtoupper(trim($accion));
        if ($accionNormalizada === '') {
            throw new AuditoriaExcepcion('La acción de auditoría es obligatoria.');
        }

        $moduloNormalizado = trim($modulo);
        if ($moduloNormalizado === '') {
            throw new AuditoriaExcepcion('El módulo de auditoría es obligatorio.');
        }

        $entidadNormalizada = trim($entidad);
        if ($entidadNormalizada === '') {
            throw new AuditoriaExcepcion('La entidad de auditoría es obligatoria.');
        }

        // 2. Resolución del Actor emisor
        $actorResuelto = $this->resolverActor($actor, $pdo);
        if ($actorResuelto === null) {
            throw new ActorNoEncontradoExcepcion(is_scalar($actor) ? (string) $actor : '');
        }

        $actorId = (int) $actorResuelto->obtenerId();
        $usuarioIdFinal = $usuarioId ?? $actorResuelto->obtenerUsuarioId();

        // 3. Resolución de correlación y contexto de petición
        $correlacionFinal = $correlacionId ?? $this->obtenerCorrelacionId();
        $contextoPeticion = $this->capturarContextoHttp($contexto);

        $ip = $contextoPeticion['ip'] ?? $this->obtenerIpSegura();
        $userAgent = $contextoPeticion['user_agent'] ?? $this->obtenerUserAgentSeguro();

        // 4. Sanitización estricta de seguridad
        $antSanitizado = $this->sanitizador->sanitizar($valoresAnteriores);
        $nueSanitizado = $this->sanitizador->sanitizar($valoresNuevos);
        $ctxSanitizado = $this->sanitizador->sanitizar($contextoPeticion);
        $descSanitizada = $this->sanitizador->sanitizarTexto($descripcion);

        // 5. Construcción y persistencia inmutable
        $registro = new RegistroAuditoria(
            null,
            $actorId,
            $usuarioIdFinal,
            $accionNormalizada,
            $moduloNormalizado,
            $entidadNormalizada,
            $entidadId !== null ? (string) $entidadId : null,
            $descSanitizada,
            $antSanitizado,
            $nueSanitizado,
            $ctxSanitizado,
            $ip,
            $userAgent,
            $correlacionFinal
        );

        return $this->auditoriaRepo->insertar($registro, $pdo);
    }

    /**
     * Resuelve un actor a partir de diferentes fuentes (instancia, ID, código o sesión actual).
     */
    private function resolverActor(ActorAuditoria|int|string|null $actor, PDO $pdo): ?ActorAuditoria
    {
        if ($actor instanceof ActorAuditoria) {
            return $actor;
        }

        if (is_int($actor) && $actor > 0) {
            return $this->actorRepo->buscarPorId($actor, $pdo);
        }

        if (is_string($actor) && trim($actor) !== '') {
            return $this->actorRepo->buscarPorCodigo(trim($actor), $pdo);
        }

        if ($actor === null) {
            return $this->obtenerActorActual($pdo);
        }

        return null;
    }

    /**
     * Obtiene el actor del contexto actual de ejecución (humano autenticado en sesión o sistema).
     *
     * @param PDO|null $pdoTransaccional
     * @return ActorAuditoria
     */
    public function obtenerActorActual(?PDO $pdoTransaccional = null): ActorAuditoria
    {
        if ($this->actorActual !== null) {
            return $this->actorActual;
        }

        $pdo = $pdoTransaccional ?? $this->pdo;

        // Si existe sesión PHP con usuario autenticado, resolver o asegurar su actor
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION[SesionServicio::CLAVE_USUARIO_ID])) {
            $usuarioId = (int) $_SESSION[SesionServicio::CLAVE_USUARIO_ID];
            if ($usuarioId > 0) {
                try {
                    $actor = $this->obtenerOAsegurarActorUsuario($usuarioId, $pdo);
                    $this->actorActual = $actor;
                    return $actor;
                } catch (Throwable) {
                    // Fallback defensivo a actor de sistema si el usuario no pudo ser hidratado
                }
            }
        }

        return $this->obtenerActorSistema($pdo);
    }

    /**
     * Asigna manualmente el actor actual en memoria para el ciclo de vida de la petición.
     *
     * @param ActorAuditoria $actor
     * @return void
     */
    public function establecerActorActual(ActorAuditoria $actor): void
    {
        $this->actorActual = $actor;
    }

    /**
     * Obtiene el actor estructural del sistema 'CAMARGO_PMS', creándolo de forma segura si no existiera.
     *
     * @param PDO|null $pdoTransaccional
     * @return ActorAuditoria
     */
    public function obtenerActorSistema(?PDO $pdoTransaccional = null): ActorAuditoria
    {
        $pdo = $pdoTransaccional ?? $this->pdo;
        $actor = $this->actorRepo->buscarPorCodigo(self::CODIGO_ACTOR_SISTEMA, $pdo);

        if ($actor !== null) {
            return $actor;
        }

        // Crear la semilla estructural si por alguna razón no existiera
        $nuevo = new ActorAuditoria(
            null,
            TipoActor::SISTEMA,
            self::CODIGO_ACTOR_SISTEMA,
            'Camargo PMS — Sistema Central',
            null,
            'ACTIVO'
        );

        return $this->actorRepo->insertar($nuevo, $pdo);
    }

    /**
     * Obtiene o crea el actor humano correspondiente a una cuenta de usuario existente.
     *
     * @param Usuario|int $usuario
     * @param PDO|null $pdoTransaccional
     * @return ActorAuditoria
     * @throws EntidadNoEncontradaExcepcion
     */
    public function obtenerOAsegurarActorUsuario(Usuario|int $usuario, ?PDO $pdoTransaccional = null): ActorAuditoria
    {
        $pdo = $pdoTransaccional ?? $this->pdo;
        $usuarioId = $usuario instanceof Usuario ? (int) $usuario->obtenerId() : (int) $usuario;

        $actorExistente = $this->actorRepo->buscarPorUsuarioId($usuarioId, $pdo);
        if ($actorExistente !== null) {
            return $actorExistente;
        }

        $usuarioObj = $usuario instanceof Usuario
            ? $usuario
            : $this->usuarioRepo->buscarPorId($usuarioId, true);

        if ($usuarioObj === null) {
            throw new EntidadNoEncontradaExcepcion('Usuario', $usuarioId);
        }

        $codigo = 'USR_' . $usuarioId;
        $nombre = $usuarioObj->obtenerNombreUsuario();
        $estado = $usuarioObj->esActivo() ? 'ACTIVO' : 'INACTIVO';

        $nuevoActor = new ActorAuditoria(
            null,
            TipoActor::USUARIO,
            $codigo,
            $nombre,
            $usuarioId,
            $estado
        );

        return $this->actorRepo->insertar($nuevoActor, $pdo);
    }

    /**
     * Crea y persiste un nuevo actor en el sistema.
     *
     * @param string $tipo Tipo de actor (USUARIO, SISTEMA, INTEGRACION, PROVEEDOR_PAGO)
     * @param string $codigo Código único e inmutable (ej. 'CULQI_WEBHOOK', 'APP_MOVIL')
     * @param string $nombre Nombre descriptivo
     * @param int|null $usuarioId ID de cuenta humana vinculada si aplica
     * @param string $estado 'ACTIVO' o 'INACTIVO'
     * @param PDO|null $pdoTransaccional
     * @return ActorAuditoria
     * @throws AuditoriaExcepcion
     * @throws ActorDuplicadoExcepcion
     * @throws EntidadNoEncontradaExcepcion
     */
    public function crearActor(
        string $tipo,
        string $codigo,
        string $nombre,
        ?int $usuarioId = null,
        string $estado = 'ACTIVO',
        ?PDO $pdoTransaccional = null
    ): ActorAuditoria {
        $pdo = $pdoTransaccional ?? $this->pdo;

        $tipoNormalizado = strtoupper(trim($tipo));
        if (!TipoActor::esValido($tipoNormalizado)) {
            throw new AuditoriaExcepcion("El tipo de actor '{$tipo}' no es válido.");
        }

        $codigoNormalizado = trim($codigo);
        if ($codigoNormalizado === '') {
            throw new AuditoriaExcepcion('El código del actor es obligatorio.');
        }

        $nombreNormalizado = trim($nombre);
        if ($nombreNormalizado === '') {
            throw new AuditoriaExcepcion('El nombre del actor es obligatorio.');
        }

        if ($this->actorRepo->existeCodigo($codigoNormalizado, null, $pdo)) {
            throw new ActorDuplicadoExcepcion('código', $codigoNormalizado);
        }

        if ($usuarioId !== null && $usuarioId > 0) {
            $usuario = $this->usuarioRepo->buscarPorId($usuarioId, false);
            if ($usuario === null) {
                throw new EntidadNoEncontradaExcepcion('Usuario', $usuarioId);
            }

            $actorPrevioUsuario = $this->actorRepo->buscarPorUsuarioId($usuarioId, $pdo);
            if ($actorPrevioUsuario !== null) {
                throw new ActorDuplicadoExcepcion('usuario_id', (string) $usuarioId);
            }
        } else {
            $usuarioId = null;
        }

        $estadoNormalizado = strtoupper(trim($estado)) === 'INACTIVO' ? 'INACTIVO' : 'ACTIVO';

        $actor = new ActorAuditoria(
            null,
            $tipoNormalizado,
            $codigoNormalizado,
            $nombreNormalizado,
            $usuarioId,
            $estadoNormalizado
        );

        return $this->actorRepo->insertar($actor, $pdo);
    }

    /**
     * Fija un identificador explícito de correlación para agrupar múltiples eventos de negocio.
     *
     * @param string $correlacionId
     * @return void
     */
    public function establecerCorrelacionId(string $correlacionId): void
    {
        $this->correlacionIdActual = trim($correlacionId);
    }

    /**
     * Obtiene el identificador de correlación actual o genera uno nuevo si no existía.
     *
     * @return string
     */
    public function obtenerCorrelacionId(): string
    {
        if ($this->correlacionIdActual === null || $this->correlacionIdActual === '') {
            $this->correlacionIdActual = $this->generarCorrelacionId();
        }

        return $this->correlacionIdActual;
    }

    /**
     * Genera un identificador de correlación aleatorio de alta entropía.
     *
     * @return string Cadena hexadecimal de 32 caracteres
     */
    public function generarCorrelacionId(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Captura los metadatos seguros de la petición HTTP actual.
     *
     * @param array<string, mixed>|null $extraContexto
     * @return array<string, mixed>
     */
    public function capturarContextoHttp(?array $extraContexto = null): array
    {
        $metodo = $_SERVER['REQUEST_METHOD'] ?? (PHP_SAPI === 'cli' ? 'CLI' : 'GET');
        $ruta = $_SERVER['REQUEST_URI'] ?? (PHP_SAPI === 'cli' ? 'cli' : '/');
        $ip = $this->obtenerIpSegura();
        $userAgent = $this->obtenerUserAgentSeguro();
        $correlacionId = $this->obtenerCorrelacionId();

        $contexto = [
            'metodo' => $metodo,
            'ruta' => $ruta,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'correlacion_id' => $correlacionId,
        ];

        if ($extraContexto !== null) {
            foreach ($extraContexto as $k => $v) {
                $contexto[$k] = $v;
            }
        }

        return $contexto;
    }

    /**
     * Obtiene la dirección IP cliente de forma segura (sin confiar a ciegas en cabeceras proxy no verificadas).
     *
     * @return string
     */
    public function obtenerIpSegura(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Obtiene y sanitiza el User-Agent cliente de la petición actual.
     *
     * @return string|null
     */
    public function obtenerUserAgentSeguro(): ?string
    {
        if (!isset($_SERVER['HTTP_USER_AGENT']) || trim((string) $_SERVER['HTTP_USER_AGENT']) === '') {
            return null;
        }

        return mb_substr(trim((string) $_SERVER['HTTP_USER_AGENT']), 0, 500);
    }

    /**
     * Acceso directo al repositorio de auditoría subyacente.
     */
    public function obtenerRepositorio(): AuditoriaRepositorio
    {
        return $this->auditoriaRepo;
    }

    /**
     * Acceso directo al repositorio de actores subyacente.
     */
    public function obtenerActorRepositorio(): ActorAuditoriaRepositorio
    {
        return $this->actorRepo;
    }

    /**
     * Acceso directo al sanitizador de seguridad.
     */
    public function obtenerSanitizador(): SanitizadorAuditoria
    {
        return $this->sanitizador;
    }
}
