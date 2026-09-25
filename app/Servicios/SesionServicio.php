<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

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

    private int $minutosInactividad;
    private int $horasDuracionMaxima;
    private int $segundosThrottleActividad;

    public function __construct(
        ?PDO $pdo = null,
        ?SesionUsuarioRepositorio $sesionRepo = null,
        ?UsuarioRepositorio $usuarioRepo = null,
        ?PersonaRepositorio $personaRepo = null
    ) {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->sesionRepo = $sesionRepo ?? new SesionUsuarioRepositorio($this->pdo);
        $this->usuarioRepo = $usuarioRepo ?? new UsuarioRepositorio($this->pdo);
        $this->personaRepo = $personaRepo ?? new PersonaRepositorio($this->pdo);

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
     * Limpia completamente el estado de la sesión local en el navegador y memoria PHP.
     *
     * @return void
     */
    public function destruirSesionLocal(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];

            if (ini_get('session.use_cookies')) {
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
}
