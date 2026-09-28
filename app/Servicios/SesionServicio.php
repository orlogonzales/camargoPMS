<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

use CamargoPMS\Excepciones\EntidadNoEncontradaExcepcion;
use CamargoPMS\Modelos\AccionAuditoria;
use CamargoPMS\Modelos\SesionUsuario;
use CamargoPMS\Modelos\Usuario;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Repositorios\PersonaRepositorio;
use CamargoPMS\Repositorios\SesionUsuarioRepositorio;
use CamargoPMS\Repositorios\UsuarioRepositorio;
use PDO;

/**
 * Servicio de dominio para el control, persistencia, validación y ciclo de vida de sesiones de usuario.
 *
 * Aplica:
 * - Tokens criptográficos de alta entropía (CSPRNG).
 * - Almacenamiento exclusivo del hash SHA-256 en base de datos.
 * - Doble control de expiración: por inactividad y duración absoluta.
 * - Throttling de escritura de actividad en BD para minimizar contención.
 * - Revocación controlada con motivos de auditoría.
 */
class SesionServicio
{
    public const CLAVE_USUARIO_ID = 'auth_usuario_id';
    public const CLAVE_SESION_TOKEN = 'auth_sesion_token';

    private PDO $pdo;
    private SesionUsuarioRepositorio $sesionRepo;
    private UsuarioRepositorio $usuarioRepo;
    private PersonaRepositorio $personaRepo;
    private AuditoriaServicio $auditoriaServicio;

    private int $minutosInactividad;
    private int $horasDuracionMaxima;
    private int $segundosThrottleActividad;

    public function __construct(
        ?PDO $pdo = null,
        ?SesionUsuarioRepositorio $sesionRepo = null,
        ?UsuarioRepositorio $usuarioRepo = null,
        ?PersonaRepositorio $personaRepo = null,
        ?AuditoriaServicio $auditoriaServicio = null
    ) {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->sesionRepo = $sesionRepo ?? new SesionUsuarioRepositorio($this->pdo);
        $this->usuarioRepo = $usuarioRepo ?? new UsuarioRepositorio($this->pdo);
        $this->personaRepo = $personaRepo ?? new PersonaRepositorio($this->pdo);
        $this->auditoriaServicio = $auditoriaServicio ?? new AuditoriaServicio($this->pdo);

        $this->minutosInactividad = (int) Configuracion::obtener('SESION_INACTIVIDAD_MINUTOS', 30);
        $this->horasDuracionMaxima = (int) Configuracion::obtener('SESION_DURACION_MAXIMA_HORAS', 12);
        $this->segundosThrottleActividad = (int) Configuracion::obtener('SESION_THROTTLE_ACTIVIDAD_SEGUNDOS', 60);
    }

    /**
     * Inicia de forma segura la sesión PHP configurando cookies defensivas.
     *
     * @return void
     */
    public static function iniciarSesionPhp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if (!headers_sent()) {
            $esHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);

            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');

            session_set_cookie_params([
                'lifetime' => 0, // Cookie de sesión que expira al cerrar navegador
                'path' => '/',
                'domain' => '',
                'secure' => $esHttps,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
    }

    /**
     * Crea y persiste una nueva sesión para el usuario autenticado, regenerando el ID de sesión PHP.
     *
     * @param Usuario $usuario
     * @param string|null $ip
     * @param string|null $userAgent
     * @return array{sesion: SesionUsuario, token_claro: string}
     */
    public function crearSesion(Usuario $usuario, ?string $ip = null, ?string $userAgent = null): array
    {
        self::iniciarSesionPhp();

        // Mitigación de Session Fixation: regenerar identificador anónimo
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_regenerate_id(true);
        }

        // Generar token secreto de aplicación de alta entropía (32 bytes = 64 hex chars)
        $tokenClaro = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $tokenClaro);

        $ahora = time();
        $iniciadaEn = date('Y-m-d H:i:s', $ahora);
        $ultimaActividadEn = $iniciadaEn;
        $expiraEn = date('Y-m-d H:i:s', $ahora + ($this->minutosInactividad * 60));

        $ipFinal = $ip ?? $this->obtenerIpCliente();
        $userAgentFinal = $userAgent ?? (isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 255) : null);

        $nuevaSesion = new SesionUsuario(
            null,
            (int) $usuario->obtenerId(),
            $tokenHash,
            $iniciadaEn,
            $ultimaActividadEn,
            $expiraEn,
            null,
            null,
            $ipFinal,
            $userAgentFinal
        );

        $sesionPersistida = $this->sesionRepo->insertar($nuevaSesion);
        $sesionPersistida->asignarUsuario($usuario);

        // Registrar secreto y referencia en $_SESSION
        $_SESSION[self::CLAVE_USUARIO_ID] = $usuario->obtenerId();
        $_SESSION[self::CLAVE_SESION_TOKEN] = $tokenClaro;

        return [
            'sesion' => $sesionPersistida,
            'token_claro' => $tokenClaro,
        ];
    }

    /**
     * Valida un token de sesión de forma aislada sin depender del estado global de $_SESSION.
     *
     * @param string $tokenClaro
     * @return Usuario|null Retorna el Usuario asociado si la sesión y su persona están activas y vigentes; null en caso contrario.
     */
    public function validarToken(string $tokenClaro): ?Usuario
    {
        if (trim($tokenClaro) === '') {
            return null;
        }

        $tokenHash = hash('sha256', $tokenClaro);
        $sesion = $this->sesionRepo->buscarPorTokenHash($tokenHash, true);

        if (!$sesion || $sesion->estaRevocada()) {
            return null;
        }

        $ahora = time();

        // 1. Validar expiración por inactividad
        $tsUltimaActividad = strtotime($sesion->obtenerUltimaActividadEn());
        if (($ahora - $tsUltimaActividad) > ($this->minutosInactividad * 60)) {
            $this->sesionRepo->revocar((int) $sesion->obtenerId(), 'EXPIRACION_INACTIVIDAD');
            return null;
        }

        // 2. Validar expiración absoluta
        $tsIniciada = strtotime($sesion->obtenerIniciadaEn());
        if (($ahora - $tsIniciada) > ($this->horasDuracionMaxima * 3600)) {
            $this->sesionRepo->revocar((int) $sesion->obtenerId(), 'EXPIRACION_ABSOLUTA');
            return null;
        }

        $usuario = $sesion->obtenerUsuario();
        if (!$usuario || !$usuario->esActivo()) {
            $this->sesionRepo->revocar((int) $sesion->obtenerId(), 'CAMBIO_ESTADO_USUARIO');
            return null;
        }

        // 3. Validar estado de la Persona natural asociada
        $persona = $this->personaRepo->buscarPorId($usuario->obtenerPersonaId(), false);
        if (!$persona || !$persona->esActivo()) {
            $this->sesionRepo->revocar((int) $sesion->obtenerId(), 'DESACTIVACION_PERSONA');
            return null;
        }

        $usuario->asignarPersona($persona);

        // 4. Throttling de actualización de actividad en base de datos
        if (($ahora - $tsUltimaActividad) >= $this->segundosThrottleActividad) {
            $nuevaActividad = date('Y-m-d H:i:s', $ahora);
            $nuevaExpiracion = date('Y-m-d H:i:s', $ahora + ($this->minutosInactividad * 60));
            $this->sesionRepo->actualizarUltimaActividad((int) $sesion->obtenerId(), $nuevaActividad, $nuevaExpiracion);
        }

        return $usuario;
    }

    /**
     * Valida la sesión actual del cliente en curso.
     *
     * @return Usuario|null Retorna el Usuario autenticado si la sesión es válida; de lo contrario null.
     */
    public function validarSesionActual(): ?Usuario
    {
        self::iniciarSesionPhp();

        $tokenClaro = $_SESSION[self::CLAVE_SESION_TOKEN] ?? null;
        $usuarioId = $_SESSION[self::CLAVE_USUARIO_ID] ?? null;

        if (!$tokenClaro || !is_string($tokenClaro) || !$usuarioId) {
            return null;
        }

        $usuario = $this->validarToken($tokenClaro);
        if ($usuario === null || $usuario->obtenerId() !== (int) $usuarioId) {
            $this->destruirSesionLocal();
            return null;
        }

        return $usuario;
    }

    /**
     * Cierra la sesión activa actual del cliente (Logout seguro).
     *
     * @return bool
     */
    public function cerrarSesionActual(): bool
    {
        self::iniciarSesionPhp();

        $tokenClaro = $_SESSION[self::CLAVE_SESION_TOKEN] ?? null;
        if ($tokenClaro && is_string($tokenClaro)) {
            $tokenHash = hash('sha256', $tokenClaro);
            $sesion = $this->sesionRepo->buscarPorTokenHash($tokenHash, false);
            if ($sesion) {
                $this->sesionRepo->revocar((int) $sesion->obtenerId(), 'LOGOUT');
            }
        }

        $this->destruirSesionLocal();
        return true;
    }

    /**
     * Revoca administrativamente una sesión concreta por su ID.
     *
     * @param int $sesionId
     * @param string $motivo
     * @return bool
     */
    public function revocarSesion(int $sesionId, string $motivo = 'REVOCACION_ADMINISTRATIVA'): bool
    {
        return $this->sesionRepo->revocar($sesionId, $motivo);
    }

    /**
     * Revoca todas las sesiones de un usuario determinado.
     *
     * @param int $usuarioId
     * @param string $motivo
     * @param int|null $exceptoSesionId
     * @return int
     */
    public function revocarTodasDeUsuario(
        int $usuarioId,
        string $motivo = 'REVOCACION_ADMINISTRATIVA',
        ?int $exceptoSesionId = null
    ): int {
        return $this->sesionRepo->revocarTodasDeUsuario($usuarioId, $motivo, $exceptoSesionId);
    }

    /**
     * Obtiene el listado de sesiones activas de un usuario.
     *
     * @param int $usuarioId
     * @return array<int, SesionUsuario>
     */
    public function obtenerSesionesActivas(int $usuarioId): array
    {
        return $this->sesionRepo->listarActivasPorUsuario($usuarioId);
    }

    /**
     * Busca una sesión por su identificador primario.
     *
     * @param int $id
     * @param bool $cargarUsuario
     * @return SesionUsuario|null
     */
    public function buscarPorId(int $id, bool $cargarUsuario = true): ?SesionUsuario
    {
        return $this->sesionRepo->buscarPorId($id, $cargarUsuario);
    }

    /**
     * Limpia completamente el estado de la sesión local en el navegador y memoria PHP.
     *
     * @return void
     */
    public function destruirSesionLocal(): void
    {
        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            if (ini_get('session.use_cookies') && !headers_sent()) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly']
                );
            }

            session_destroy();
        }
    }

    /**
     * Obtiene la IP del cliente de forma sanitizada.
     *
     * @return string|null
     */
    private function obtenerIpCliente(): ?string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        if ($ip && filter_var($ip, FILTER_VALIDATE_IP)) {
            return (string) $ip;
        }

        return null;
    }

    /**
     * Consulta paginada y filtrada del universo global de sesiones en el sistema.
     *
     * @param array<string, mixed> $filtros
     * @param int $limite
     * @param int $pagina
     * @return array{items: array<int, array<string, mixed>>, total: int, pagina: int, limite: int, total_paginas: int}
     */
    public function listarSesionesGlobales(array $filtros = [], int $limite = 20, int $pagina = 1): array
    {
        $limiteSeguro = max(1, min(100, $limite));
        $paginaSegura = max(1, $pagina);

        $total = $this->sesionRepo->contarSesionesGlobales(
            $filtros,
            $this->minutosInactividad,
            $this->horasDuracionMaxima
        );

        $items = $this->sesionRepo->listarSesionesGlobales(
            $filtros,
            $limiteSeguro,
            $paginaSegura,
            $this->minutosInactividad,
            $this->horasDuracionMaxima
        );

        // Identificar si alguna de las sesiones mostradas corresponde a la sesión en curso del cliente
        $tokenClaroActual = $_SESSION[self::CLAVE_SESION_TOKEN] ?? null;
        $hashActual = is_string($tokenClaroActual) && trim($tokenClaroActual) !== '' ? hash('sha256', $tokenClaroActual) : null;

        foreach ($items as &$item) {
            // Evaluamos si el ID coincide con la sesión del usuario autenticado
            $esActual = false;
            if ($hashActual !== null) {
                $sesionEnBD = $this->sesionRepo->buscarPorId($item['id'], false);
                if ($sesionEnBD !== null && $sesionEnBD->obtenerTokenHash() === $hashActual) {
                    $esActual = true;
                }
            }
            $item['es_sesion_actual'] = $esActual;
        }
        unset($item);

        return [
            'items' => $items,
            'total' => $total,
            'pagina' => $paginaSegura,
            'limite' => $limiteSeguro,
            'total_paginas' => (int) ceil($total / max(1, $limiteSeguro)),
        ];
    }

    /**
     * Obtiene el resumen de métricas de concurrencia y actividad en tiempo real.
     *
     * @return array<string, int>
     */
    public function obtenerResumenMetricas(): array
    {
        return $this->sesionRepo->obtenerResumenMetricas(
            $this->minutosInactividad,
            $this->horasDuracionMaxima,
            15
        );
    }

    /**
     * Revoca administrativamente una sesión concreta con auditoría D-061 y control de idempotencia.
     *
     * Reglas vinculantes:
     * - Si la sesión ya estaba REVOCADA: operación idempotente sin error.
     * - Si es la propia sesión del usuario actual: se revoca, se audita, se destruye la sesión PHP local y se retorna indicador de redirección a /login.
     * - Si es la sesión de otro usuario: se revoca, se audita y la sesión del administrador permanece intacta.
     *
     * @param int $sesionId
     * @param int $ejecutadoPorUsuarioId
     * @param string $motivo
     * @return array{exito: bool, ya_revocada: bool, es_sesion_actual: bool, mensaje: string}
     * @throws EntidadNoEncontradaExcepcion
     */
    public function revocarSesionAdministrativa(
        int $sesionId,
        int $ejecutadoPorUsuarioId,
        string $motivo = 'REVOCACION_ADMINISTRATIVA'
    ): array {
        $sesion = $this->sesionRepo->buscarPorId($sesionId, true);
        if ($sesion === null) {
            throw new EntidadNoEncontradaExcepcion('SesionUsuario', $sesionId);
        }

        // Idempotencia: Si ya está revocada, no producir error ni duplicar eventos de auditoría contradictorios
        if ($sesion->estaRevocada()) {
            return [
                'exito' => true,
                'ya_revocada' => true,
                'es_sesion_actual' => false,
                'mensaje' => 'La sesión ya se encontraba revocada previamente.',
            ];
        }

        $esSesionActual = $this->esSesionActual($sesion);

        // Ejecutar revocación en BD
        $this->sesionRepo->revocar($sesionId, $motivo);

        // Registro de auditoría D-061 inmutable
        $this->auditoriaServicio->registrar(
            AccionAuditoria::CERRAR_SESION,
            'seguridad',
            'sesiones_usuario',
            (string) $sesionId,
            "Revocación administrativa de sesión ID {$sesionId} para el usuario ID {$sesion->obtenerUsuarioId()}",
            ['estado' => 'ACTIVA'],
            ['estado' => 'REVOCADA', 'motivo_cierre' => $motivo],
            [
                'sesion_id' => $sesionId,
                'usuario_afectado_id' => $sesion->obtenerUsuarioId(),
                'es_autorrevocacion' => $esSesionActual,
                'ip' => $sesion->obtenerIp(),
            ],
            null,
            $ejecutadoPorUsuarioId
        );

        if ($esSesionActual) {
            $this->destruirSesionLocal();
            return [
                'exito' => true,
                'ya_revocada' => false,
                'es_sesion_actual' => true,
                'mensaje' => 'Su propia sesión actual ha sido revocada. Será redirigido al inicio de sesión.',
            ];
        }

        return [
            'exito' => true,
            'ya_revocada' => false,
            'es_sesion_actual' => false,
            'mensaje' => "La sesión ID {$sesionId} ha sido revocada exitosamente.",
        ];
    }

    /**
     * Revoca administrativamente todas las sesiones activas de un usuario determinado.
     *
     * @param int $usuarioId
     * @param int $ejecutadoPorUsuarioId
     * @param string $motivo
     * @return array{exito: bool, es_sesion_actual: bool, sesiones_revocadas: int, mensaje: string}
     * @throws EntidadNoEncontradaExcepcion
     */
    public function revocarTodasDeUsuarioAdministrativa(
        int $usuarioId,
        int $ejecutadoPorUsuarioId,
        string $motivo = 'REVOCACION_ADMINISTRATIVA'
    ): array {
        $usuario = $this->usuarioRepo->buscarPorId($usuarioId, false);
        if ($usuario === null) {
            throw new EntidadNoEncontradaExcepcion('Usuario', $usuarioId);
        }

        self::iniciarSesionPhp();
        $esSesionActualPropia = ((int) ($_SESSION[self::CLAVE_USUARIO_ID] ?? 0) === $usuarioId);

        $totalRevocadas = $this->sesionRepo->revocarTodasDeUsuario($usuarioId, $motivo);

        // Registro de auditoría D-061
        $this->auditoriaServicio->registrar(
            AccionAuditoria::CERRAR_SESION,
            'seguridad',
            'sesiones_usuario',
            (string) $usuarioId,
            "Cierre administrativo de todas las sesiones ({$totalRevocadas} afectadas) del usuario ID {$usuarioId}",
            null,
            ['sesiones_revocadas' => $totalRevocadas, 'motivo_cierre' => $motivo],
            [
                'usuario_afectado_id' => $usuarioId,
                'es_autorrevocacion_masiva' => $esSesionActualPropia,
            ],
            null,
            $ejecutadoPorUsuarioId
        );

        if ($esSesionActualPropia) {
            $this->destruirSesionLocal();
            return [
                'exito' => true,
                'es_sesion_actual' => true,
                'sesiones_revocadas' => $totalRevocadas,
                'mensaje' => "Se revocaron {$totalRevocadas} sesiones (incluyendo la actual). Será redirigido al inicio de sesión.",
            ];
        }

        return [
            'exito' => true,
            'es_sesion_actual' => false,
            'sesiones_revocadas' => $totalRevocadas,
            'mensaje' => "Se revocaron {$totalRevocadas} sesiones activas del usuario exitosamente.",
        ];
    }

    /**
     * Marca en lote las sesiones expiradas en base de datos para saneamiento analítico.
     */
    public function purgarExpiradasLote(): int
    {
        return $this->sesionRepo->marcarExpiradasLote($this->minutosInactividad, $this->horasDuracionMaxima);
    }

    /**
     * Determina si una instancia de SesionUsuario coincide con la sesión del cliente actual.
     */
    public function esSesionActual(SesionUsuario $sesion): bool
    {
        self::iniciarSesionPhp();
        $tokenClaroActual = $_SESSION[self::CLAVE_SESION_TOKEN] ?? null;
        if (!is_string($tokenClaroActual) || trim($tokenClaroActual) === '') {
            return false;
        }

        return hash('sha256', $tokenClaroActual) === $sesion->obtenerTokenHash();
    }

    public function obtenerMinutosInactividad(): int
    {
        return $this->minutosInactividad;
    }

    public function obtenerHorasDuracionMaxima(): int
    {
        return $this->horasDuracionMaxima;
    }

    public function obtenerSegundosThrottle(): int
    {
        return $this->segundosThrottleActividad;
    }
}
