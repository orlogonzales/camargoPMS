<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

use CamargoPMS\Excepciones\CredencialesInvalidasExcepcion;
use CamargoPMS\Excepciones\DemasiadosIntentosExcepcion;
use CamargoPMS\Excepciones\UsuarioBloqueadoExcepcion;
use CamargoPMS\Modelos\Usuario;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Repositorios\IntentoAutenticacionRepositorio;
use CamargoPMS\Repositorios\PersonaRepositorio;
use CamargoPMS\Repositorios\UsuarioRepositorio;
use PDO;

/**
 * Servicio de dominio para la autenticación de identidades humanas.
 *
 * "¿Quién eres?"
 *
 * Aplica:
 * - Rate limiting / Throttling temporal preventivo contra ataques de fuerza bruta.
 * - Mitigación de timing attacks y prevención estricta de enumeración de cuentas.
 * - Verificación criptográfica con password_verify() y rehash transparente con password_needs_rehash().
 * - Validación concurrente del estado de la Persona natural y de la cuenta de Usuario.
 * - Inicio atómico de sesión de aplicación persistente.
 */
class AutenticacionServicio
{
    /**
     * Hash dummy alineado con PASSWORD_DEFAULT utilizado para equilibrar el tiempo de respuesta ante usuarios inexistentes.
     */
    private static ?string $hashDummy = null;

    private PDO $pdo;
    private UsuarioRepositorio $usuarioRepo;
    private PersonaRepositorio $personaRepo;
    private SesionServicio $sesionServicio;
    private IntentoAutenticacionRepositorio $intentoRepo;

    private int $maxIntentosFallidos;
    private int $ventanaBloqueoSegundos;

    public function __construct(
        ?PDO $pdo = null,
        ?UsuarioRepositorio $usuarioRepo = null,
        ?PersonaRepositorio $personaRepo = null,
        ?SesionServicio $sesionServicio = null,
        ?IntentoAutenticacionRepositorio $intentoRepo = null
    ) {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->usuarioRepo = $usuarioRepo ?? new UsuarioRepositorio($this->pdo);
        $this->personaRepo = $personaRepo ?? new PersonaRepositorio($this->pdo);
        $this->sesionServicio = $sesionServicio ?? new SesionServicio($this->pdo);
        $this->intentoRepo = $intentoRepo ?? new IntentoAutenticacionRepositorio($this->pdo);

        $this->maxIntentosFallidos = (int) Configuracion::obtener('AUTH_MAX_INTENTOS_FALLIDOS', 5);
        $this->ventanaBloqueoSegundos = (int) Configuracion::obtener('AUTH_VENTANA_BLOQUEO_SEGUNDOS', 900); // 15 minutos
    }

    /**
     * Autentica las credenciales provistas y establece la sesión si son válidas.
     *
     * @param string $nombreUsuario
     * @param string $contrasena
     * @param string|null $ip
     * @param string|null $userAgent
     * @return array{usuario: Usuario, sesion: SesionUsuario, token_claro: string}
     * @throws CredencialesInvalidasExcepcion
     * @throws UsuarioBloqueadoExcepcion
     * @throws DemasiadosIntentosExcepcion
     */
    public function autenticar(
        string $nombreUsuario,
        string $contrasena,
        ?string $ip = null,
        ?string $userAgent = null
    ): array {
        $usernameNormalizado = trim(mb_strtolower($nombreUsuario, 'UTF-8'));
        $ipFinal = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');

        // 1. Verificación de Rate Limiting / Fuerza Bruta
        $intentosFallidos = $this->intentoRepo->contarIntentosFallidosRecientes(
            $usernameNormalizado,
            $ipFinal,
            $this->ventanaBloqueoSegundos
        );

        if ($intentosFallidos >= $this->maxIntentosFallidos) {
            throw new DemasiadosIntentosExcepcion($this->ventanaBloqueoSegundos);
        }

        // 2. Búsqueda del usuario por nombre normalizado
        $usuario = $this->usuarioRepo->buscarPorNombreUsuario($usernameNormalizado, true);

        // 3. Mitigación de timing attack ante usuarios inexistentes
        if (!$usuario) {
            password_verify($contrasena, $this->obtenerHashDummy());
            $this->intentoRepo->registrarIntento($usernameNormalizado, $ipFinal, false);
            throw new CredencialesInvalidasExcepcion('Credenciales de acceso inválidas.');
        }

        // 4. Verificación de la contraseña mediante API nativa segura
        if (!password_verify($contrasena, $usuario->obtenerContrasenaHash())) {
            $this->intentoRepo->registrarIntento($usernameNormalizado, $ipFinal, false);
            throw new CredencialesInvalidasExcepcion('Credenciales de acceso inválidas.');
        }

        // 5. Validación del estado de la cuenta de usuario (BLOQUEADO / INACTIVO)
        if ($usuario->estaBloqueado()) {
            $this->intentoRepo->registrarIntento($usernameNormalizado, $ipFinal, false);
            throw new UsuarioBloqueadoExcepcion('La cuenta de usuario se encuentra bloqueada.');
        }

        if (!$usuario->esActivo()) {
            $this->intentoRepo->registrarIntento($usernameNormalizado, $ipFinal, false);
            throw new CredencialesInvalidasExcepcion('Credenciales de acceso inválidas.');
        }

        // 6. Validación del estado de la Persona natural asociada
        $persona = $usuario->obtenerPersona() ?? $this->personaRepo->buscarPorId($usuario->obtenerPersonaId(), false);
        if (!$persona || !$persona->esActivo()) {
            $this->intentoRepo->registrarIntento($usernameNormalizado, $ipFinal, false);
            throw new CredencialesInvalidasExcepcion('Credenciales de acceso inválidas.');
        }

        // 7. Rehash transparente si los parámetros o algoritmo de contraseñas cambiaron
        if (password_needs_rehash($usuario->obtenerContrasenaHash(), PASSWORD_DEFAULT)) {
            $nuevoHash = password_hash($contrasena, PASSWORD_DEFAULT);
            if ($nuevoHash !== false) {
                $this->usuarioRepo->actualizarHashContrasena((int) $usuario->obtenerId(), $nuevoHash);
            }
        }

        // 8. Actualizar fecha de último acceso exitoso y registrar intento exitoso
        $this->usuarioRepo->actualizarUltimoAcceso((int) $usuario->obtenerId());
        $this->intentoRepo->registrarIntento($usernameNormalizado, $ipFinal, true);

        // 9. Crear sesión persistente de aplicación y fijar variables seguras
        $infoSesion = $this->sesionServicio->crearSesion($usuario, $ipFinal, $userAgent);

        return [
            'usuario' => $usuario,
            'sesion' => $infoSesion['sesion'],
            'token_claro' => $infoSesion['token_claro'],
        ];
    }

    /**
     * Cierra la sesión activa actual del usuario (Logout).
     *
     * @return bool
     */
    public function cerrarSesion(): bool
    {
        return $this->sesionServicio->cerrarSesionActual();
    }

    /**
     * Obtiene el usuario autenticado en la sesión actual, o null si es anónimo o inválido.
     *
     * @return Usuario|null
     */
    public function obtenerUsuarioAutenticado(): ?Usuario
    {
        return $this->sesionServicio->validarSesionActual();
    }

    /**
     * Obtiene un hash dummy calculado dinámicamente con PASSWORD_DEFAULT para mitigar timing attacks.
     *
     * @return string
     */
    private function obtenerHashDummy(): string
    {
        if (self::$hashDummy === null) {
            self::$hashDummy = password_hash('camargo_timing_dummy_entropy_seed', PASSWORD_DEFAULT);
        }
        return self::$hashDummy;
    }
}
