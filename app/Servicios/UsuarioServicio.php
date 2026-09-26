<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

use CamargoPMS\Excepciones\CredencialesInvalidasExcepcion;
use CamargoPMS\Excepciones\EntidadNoEncontradaExcepcion;
use CamargoPMS\Excepciones\UltimoSuperadministradorExcepcion;
use CamargoPMS\Excepciones\UsuarioDuplicadoExcepcion;
use CamargoPMS\Excepciones\ValidacionExcepcion;
use CamargoPMS\Modelos\Usuario;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Repositorios\AutorizacionRepositorio;
use CamargoPMS\Repositorios\PersonaRepositorio;
use CamargoPMS\Repositorios\UsuarioRepositorio;
use PDO;
use Throwable;

/**
 * Servicio de dominio para la gestión del ciclo de vida de cuentas humanas de usuario.
 *
 * Principio vinculante:
 * PERSONA ≠ COLABORADOR ≠ USUARIO ≠ CARGO ≠ ROL
 *
 * Garantiza:
 * - Cardinalidad 1:1 lógica Persona ↔ Usuario.
 * - Validación y hash robusto de contraseñas con PASSWORD_DEFAULT.
 * - Normalización canónica de nombres de usuario.
 * - Revocación automática de sesiones ante bloqueos o cambios de contraseña.
 */
class UsuarioServicio
{
    public const LONGITUD_MINIMA_CONTRASENA = 12;
    public const LONGITUD_MAXIMA_CONTRASENA = 1024;
    public const ESTADOS_VALIDOS = ['ACTIVO', 'BLOQUEADO', 'INACTIVO'];

    private PDO $pdo;
    private UsuarioRepositorio $usuarioRepo;
    private PersonaRepositorio $personaRepo;
    private SesionServicio $sesionServicio;

    public function __construct(
        ?PDO $pdo = null,
        ?UsuarioRepositorio $usuarioRepo = null,
        ?PersonaRepositorio $personaRepo = null,
        ?SesionServicio $sesionServicio = null
    ) {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->usuarioRepo = $usuarioRepo ?? new UsuarioRepositorio($this->pdo);
        $this->personaRepo = $personaRepo ?? new PersonaRepositorio($this->pdo);
        $this->sesionServicio = $sesionServicio ?? new SesionServicio($this->pdo);
    }

    /**
     * Crea una nueva cuenta humana de usuario vinculada directamente a una Persona existente.
     *
     * @param array<string, mixed> $datos
     * @return Usuario
     * @throws EntidadNoEncontradaExcepcion
     * @throws ValidacionExcepcion
     * @throws UsuarioDuplicadoExcepcion
     * @throws Throwable
     */
    public function crearUsuario(array $datos): Usuario
    {
        $personaId = isset($datos['persona_id']) ? (int) $datos['persona_id'] : 0;
        if ($personaId <= 0) {
            throw new ValidacionExcepcion('Se requiere un identificador de persona válido.', ['persona_id' => 'Requerido']);
        }

        $nombreUsuarioCrudo = isset($datos['nombre_usuario']) ? (string) $datos['nombre_usuario'] : '';
        $nombreUsuario = $this->normalizarNombreUsuario($nombreUsuarioCrudo);
        $this->validarNombreUsuario($nombreUsuario);

        $contrasena = isset($datos['contrasena']) ? (string) $datos['contrasena'] : '';
        $this->validarPoliticaContrasena($contrasena);

        $estado = isset($datos['estado']) ? strtoupper(trim((string) $datos['estado'])) : 'ACTIVO';
        if (!in_array($estado, self::ESTADOS_VALIDOS, true)) {
            throw new ValidacionExcepcion("El estado '{$estado}' no es válido.", ['estado' => 'Estado no permitido']);
        }

        $transaccionPropia = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $transaccionPropia = true;
        }

        try {
            // Bloqueo pesimista de la Persona para asegurar su estado y evitar carreras
            $stmtP = $this->pdo->prepare('SELECT id, estado FROM personas WHERE id = :id FOR UPDATE');
            $stmtP->bindValue(':id', $personaId, PDO::PARAM_INT);
            $stmtP->execute();
            $filaPersona = $stmtP->fetch();

            if (!$filaPersona) {
                throw new EntidadNoEncontradaExcepcion('Persona', $personaId);
            }

            if ($filaPersona['estado'] !== 'ACTIVO') {
                throw new ValidacionExcepcion(
                    'No se puede crear un usuario para una persona inactiva.',
                    ['persona_id' => 'La persona natural se encuentra inactiva.']
                );
            }

            // Unicidad 1:1 Persona -> Usuario
            $usuarioExistentePersona = $this->usuarioRepo->buscarPorPersonaId($personaId, false);
            if ($usuarioExistentePersona !== null) {
                throw new UsuarioDuplicadoExcepcion(
                    'persona_id',
                    (string) $personaId,
                    "La persona con ID {$personaId} ya cuenta con un usuario registrado ('{$usuarioExistentePersona->obtenerNombreUsuario()}')."
                );
            }

            // Unicidad de nombre de usuario
            $usuarioExistenteUsername = $this->usuarioRepo->buscarPorNombreUsuario($nombreUsuario, false);
            if ($usuarioExistenteUsername !== null) {
                throw new UsuarioDuplicadoExcepcion(
                    'nombre_usuario',
                    $nombreUsuario,
                    "El nombre de usuario '{$nombreUsuario}' ya se encuentra en uso."
                );
            }

            // Generar hash seguro con PASSWORD_DEFAULT
            $contrasenaHash = password_hash($contrasena, PASSWORD_DEFAULT);
            if ($contrasenaHash === false) {
                throw new ValidacionExcepcion('Error interno al generar el hash de seguridad de la contraseña.');
            }

            $nuevoUsuario = new Usuario(
                null,
                $personaId,
                $nombreUsuario,
                $contrasenaHash,
                $estado
            );

            $usuarioPersistido = $this->usuarioRepo->insertar($nuevoUsuario);

            if ($transaccionPropia && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }

            return $usuarioPersistido;
        } catch (Throwable $e) {
            if ($transaccionPropia && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Modifica el estado administrativo de un usuario y revoca sus sesiones activas si pasa a inactivo o bloqueado.
     *
     * @param int $usuarioId
     * @param string $nuevoEstado
     * @return bool
     * @throws EntidadNoEncontradaExcepcion
     * @throws ValidacionExcepcion
     * @throws Throwable
     */
    public function cambiarEstado(int $usuarioId, string $nuevoEstado): bool
    {
        if ($usuarioId <= 0) {
            throw new ValidacionExcepcion('Se requiere un identificador de usuario válido.');
        }

        $nuevoEstado = strtoupper(trim($nuevoEstado));
        if (!in_array($nuevoEstado, self::ESTADOS_VALIDOS, true)) {
            throw new ValidacionExcepcion("El estado '{$nuevoEstado}' no es válido.");
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT id, estado FROM usuarios WHERE id = :id FOR UPDATE');
            $stmt->bindValue(':id', $usuarioId, PDO::PARAM_INT);
            $stmt->execute();
            $fila = $stmt->fetch();

            if (!$fila) {
                throw new EntidadNoEncontradaExcepcion('Usuario', $usuarioId);
            }

            // Invariante del último Superadministrador activo:
            // Si el nuevo estado es no activo, impedir si es el único Superadministrador activo restante
            if ($nuevoEstado !== 'ACTIVO') {
                $autorizacionRepo = new AutorizacionRepositorio($this->pdo);
                $superadminsActivos = $autorizacionRepo->listarIdsSuperadministradoresActivos(true);

                if (in_array($usuarioId, $superadminsActivos, true) && count($superadminsActivos) <= 1) {
                    throw new UltimoSuperadministradorExcepcion(
                        'Operación denegada: no se puede bloquear ni desactivar al único Superadministrador activo del sistema.'
                    );
                }
            }

            $this->usuarioRepo->cambiarEstado($usuarioId, $nuevoEstado);

            // Si pasa a BLOQUEADO o INACTIVO, revocar inmediatamente todas sus sesiones activas
            if ($nuevoEstado !== 'ACTIVO') {
                $this->sesionServicio->revocarTodasDeUsuario($usuarioId, 'CAMBIO_ESTADO_USUARIO');
            }

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
     * Actualiza la contraseña de un usuario validando su contraseña actual y revocando las demás sesiones.
     *
     * @param int $usuarioId
     * @param string $contrasenaActual
     * @param string $nuevaContrasena
     * @param int|null $sesionActualId Si se especifica, esta sesión se mantiene abierta y se revocan las demás.
     * @return bool
     * @throws EntidadNoEncontradaExcepcion
     * @throws CredencialesInvalidasExcepcion
     * @throws ValidacionExcepcion
     * @throws Throwable
     */
    public function cambiarContrasena(
        int $usuarioId,
        string $contrasenaActual,
        string $nuevaContrasena,
        ?int $sesionActualId = null
    ): bool {
        if ($usuarioId <= 0) {
            throw new ValidacionExcepcion('Se requiere un identificador de usuario válido.');
        }

        $this->validarPoliticaContrasena($nuevaContrasena);

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT id, contrasena_hash, estado FROM usuarios WHERE id = :id FOR UPDATE');
            $stmt->bindValue(':id', $usuarioId, PDO::PARAM_INT);
            $stmt->execute();
            $fila = $stmt->fetch();

            if (!$fila) {
                throw new EntidadNoEncontradaExcepcion('Usuario', $usuarioId);
            }

            // Verificar contraseña actual
            if (!password_verify($contrasenaActual, (string) $fila['contrasena_hash'])) {
                throw new CredencialesInvalidasExcepcion('La contraseña actual es incorrecta.');
            }

            // Generar nuevo hash
            $nuevoHash = password_hash($nuevaContrasena, PASSWORD_DEFAULT);
            $this->usuarioRepo->actualizarHashContrasena($usuarioId, $nuevoHash);

            // Revocar las demás sesiones activas por seguridad
            $this->sesionServicio->revocarTodasDeUsuario($usuarioId, 'CAMBIO_CONTRASENA', $sesionActualId);

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
     * Normaliza un nombre de usuario: recorta espacios y convierte a minúsculas en UTF-8.
     *
     * @param string $username
     * @return string
     */
    public function normalizarNombreUsuario(string $username): string
    {
        return trim(mb_strtolower($username, 'UTF-8'));
    }

    /**
     * Valida sintácticamente un nombre de usuario.
     *
     * @param string $username
     * @throws ValidacionExcepcion
     */
    public function validarNombreUsuario(string $username): void
    {
        if ($username === '') {
            throw new ValidacionExcepcion('El nombre de usuario es obligatorio.', ['nombre_usuario' => 'Requerido']);
        }

        $longitud = mb_strlen($username, 'UTF-8');
        if ($longitud < 3 || $longitud > 50) {
            throw new ValidacionExcepcion(
                'El nombre de usuario debe tener entre 3 y 50 caracteres.',
                ['nombre_usuario' => 'Longitud fuera de rango (3 a 50 caracteres)']
            );
        }

        // Permite letras minúsculas, números, puntos, guiones y guiones bajos
        if (!preg_match('/^[a-z0-9._-]+$/', $username)) {
            throw new ValidacionExcepcion(
                'El nombre de usuario solo puede contener letras, números, puntos, guiones y guiones bajos.',
                ['nombre_usuario' => 'Caracteres no permitidos']
            );
        }
    }

    /**
     * Valida la política de contraseñas de Camargo PMS sin alteración silenciosa de caracteres.
     *
     * @param string $contrasena
     * @throws ValidacionExcepcion
     */
    public function validarPoliticaContrasena(string $contrasena): void
    {
        $longitud = strlen($contrasena); // Longitud en bytes para el hash
        $longitudUtf8 = mb_strlen($contrasena, 'UTF-8');

        if ($longitudUtf8 < self::LONGITUD_MINIMA_CONTRASENA) {
            throw new ValidacionExcepcion(
                'La contraseña debe tener como mínimo ' . self::LONGITUD_MINIMA_CONTRASENA . ' caracteres.',
                ['contrasena' => 'Contraseña demasiado corta']
            );
        }

        if ($longitud > self::LONGITUD_MAXIMA_CONTRASENA) {
            throw new ValidacionExcepcion(
                'La contraseña excede la longitud máxima permitida de ' . self::LONGITUD_MAXIMA_CONTRASENA . ' caracteres.',
                ['contrasena' => 'Contraseña demasiado larga']
            );
        }
    }

    /**
     * Busca un usuario por su ID primario.
     *
     * @param int $id
     * @param bool $cargarPersona
     * @return Usuario|null
     */
    public function buscarPorId(int $id, bool $cargarPersona = true): ?Usuario
    {
        return $this->usuarioRepo->buscarPorId($id, $cargarPersona);
    }

    /**
     * Busca un usuario por su nombre de usuario.
     *
     * @param string $nombreUsuario
     * @param bool $cargarPersona
     * @return Usuario|null
     */
    public function buscarPorNombreUsuario(string $nombreUsuario, bool $cargarPersona = true): ?Usuario
    {
        $normalizado = $this->normalizarNombreUsuario($nombreUsuario);
        return $this->usuarioRepo->buscarPorNombreUsuario($normalizado, $cargarPersona);
    }
}
