<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

use CamargoPMS\Excepciones\ConfiguracionNoEditableExcepcion;
use CamargoPMS\Excepciones\ConfiguracionNoEncontradaExcepcion;
use CamargoPMS\Excepciones\ValidacionExcepcion;
use CamargoPMS\Modelos\AccionAuditoria;
use CamargoPMS\Modelos\ActorAuditoria;
use CamargoPMS\Modelos\ConfiguracionParametro;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Repositorios\ConfiguracionRepositorio;
use PDO;
use Throwable;

/**
 * Servicio de dominio central para la gestión y consulta de configuración y parámetros del sistema.
 *
 * Principios vinculantes:
 * - CANÓNICO: Todo acceso a configuraciones funcionales debe canalizarse a través de este servicio.
 * - TIPADO: Retorna y valida tipos nativos de PHP según el contrato de TipoConfiguracion.
 * - SEPARACIÓN: Las configuraciones funcionales administrativas viven en BD; la infraestructura en .env.
 * - AUDITORÍA D-061: Las mutaciones se auditan resolviendo el actor ejecutor humano correspondiente.
 */
class ConfiguracionServicio
{
    private PDO $pdo;
    private ConfiguracionRepositorio $configRepo;
    private AuditoriaServicio $auditoriaServicio;

    /**
     * Caché de memoria en el ciclo de vida de la petición para lecturas ultra-rápidas.
     * @var array<string, ConfiguracionParametro>
     */
    private array $cache = [];

    public function __construct(
        ?PDO $pdo = null,
        ?ConfiguracionRepositorio $configRepo = null,
        ?AuditoriaServicio $auditoriaServicio = null
    ) {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->configRepo = $configRepo ?? new ConfiguracionRepositorio($this->pdo);
        $this->auditoriaServicio = $auditoriaServicio ?? new AuditoriaServicio($this->pdo);
    }

    /**
     * Limpia la caché interna en memoria.
     */
    public function limpiarCache(): void
    {
        $this->cache = [];
    }

    /**
     * Obtiene el valor funcional tipado de un parámetro por su clave.
     * Si no existe o su estado no es ACTIVO, retorna el valor predeterminado provisto.
     *
     * @param string $clave
     * @param mixed $predeterminado
     * @return mixed
     */
    public function obtener(string $clave, mixed $predeterminado = null): mixed
    {
        $parametro = $this->obtenerParametro($clave);
        if ($parametro === null || !$parametro->estaActivo()) {
            return $predeterminado;
        }

        $valorTipado = $parametro->obtenerValorTipado();
        return $valorTipado !== null ? $valorTipado : $predeterminado;
    }

    /**
     * Obtiene la entidad de dominio completa de un parámetro por su clave.
     *
     * @param string $clave
     * @return ConfiguracionParametro|null
     */
    public function obtenerParametro(string $clave): ?ConfiguracionParametro
    {
        $claveNormalizada = trim(strtolower($clave));
        if (isset($this->cache[$claveNormalizada])) {
            return $this->cache[$claveNormalizada];
        }

        $parametro = $this->configRepo->buscarPorClave($claveNormalizada);
        if ($parametro !== null) {
            $this->cache[$claveNormalizada] = $parametro;
        }

        return $parametro;
    }

    /**
     * Obtiene todos los parámetros de un grupo funcional.
     *
     * @param string $grupo
     * @param string|null $estado
     * @return array<int, ConfiguracionParametro>
     */
    public function obtenerPorGrupo(string $grupo, ?string $estado = null): array
    {
        return $this->configRepo->listarPorGrupo($grupo, $estado);
    }

    /**
     * Lista todos los parámetros de configuración.
     *
     * @param string|null $estado
     * @return array<int, ConfiguracionParametro>
     */
    public function listarTodos(?string $estado = null): array
    {
        return $this->configRepo->listarTodos($estado);
    }

    /**
     * Lista todos los parámetros estructurados y organizados por su grupo funcional.
     *
     * @param string|null $estado
     * @return array<string, array<int, ConfiguracionParametro>>
     */
    public function listarAgrupadas(?string $estado = null): array
    {
        $parametros = $this->configRepo->listarTodos($estado);
        $agrupadas = [];

        foreach ($parametros as $parametro) {
            $grupo = $parametro->obtenerGrupo();
            if (!isset($agrupadas[$grupo])) {
                $agrupadas[$grupo] = [];
            }
            $agrupadas[$grupo][] = $parametro;
        }

        return $agrupadas;
    }

    /**
     * Actualiza el valor de un único parámetro de configuración validando tipos y permisos de edición.
     *
     * @param string $clave
     * @param mixed $valor
     * @param int|null $ejecutadoPorUsuarioId
     * @param string|null $correlacionId
     * @param PDO|null $pdoTransaccional
     * @return bool
     * @throws ConfiguracionNoEncontradaExcepcion
     * @throws ConfiguracionNoEditableExcepcion
     * @throws ValidacionExcepcion
     */
    public function actualizar(
        string $clave,
        mixed $valor,
        ?int $ejecutadoPorUsuarioId = null,
        ?string $correlacionId = null,
        ?PDO $pdoTransaccional = null
    ): bool {
        $claveNormalizada = trim(strtolower($clave));
        $parametro = $this->configRepo->buscarPorClave($claveNormalizada);

        if ($parametro === null) {
            throw new ConfiguracionNoEncontradaExcepcion($claveNormalizada);
        }

        if (!$parametro->esEditable()) {
            throw new ConfiguracionNoEditableExcepcion($claveNormalizada);
        }

        $tipo = $parametro->obtenerTipo();
        if (!$tipo->validar($valor)) {
            throw new ValidacionExcepcion(
                "El valor proporcionado no es válido para el tipo {$tipo->value} en el parámetro '{$claveNormalizada}'.",
                ['clave' => $claveNormalizada, 'tipo' => $tipo->value]
            );
        }

        $nuevoValorSerializado = $tipo->serializar($valor);
        $valorAnteriorRaw = $parametro->obtenerValor();

        $pdo = $pdoTransaccional ?? $this->pdo;
        $transaccionPropia = ($pdoTransaccional === null) && !$pdo->inTransaction();

        if ($transaccionPropia) {
            $pdo->beginTransaction();
        }

        try {
            $exito = $this->configRepo->actualizarValor($claveNormalizada, $nuevoValorSerializado);
            if (!$exito) {
                if ($transaccionPropia && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                return false;
            }

            // Invalida la entrada en memoria
            unset($this->cache[$claveNormalizada]);

            // Auditoría transversal D-061
            try {
                $actorEjecutor = $this->resolverActorEjecutor($ejecutadoPorUsuarioId);
                $this->auditoriaServicio->registrar(
                    AccionAuditoria::EDITAR,
                    'configuracion',
                    'configuraciones',
                    (string) $parametro->obtenerId(),
                    "Actualización de configuración '{$claveNormalizada}'",
                    [
                        'clave' => $claveNormalizada,
                        'grupo' => $parametro->obtenerGrupo(),
                        'tipo' => $tipo->value,
                        'valor' => $parametro->esSensible() ? '***' : $valorAnteriorRaw,
                    ],
                    [
                        'clave' => $claveNormalizada,
                        'grupo' => $parametro->obtenerGrupo(),
                        'tipo' => $tipo->value,
                        'valor' => $parametro->esSensible() ? '***' : $nuevoValorSerializado,
                    ],
                    null,
                    $actorEjecutor,
                    $ejecutadoPorUsuarioId,
                    $correlacionId,
                    $pdo
                );
            } catch (Throwable) {
                // No interrumpir la persistencia si la auditoría informativa no crítica falla
            }

            if ($transaccionPropia && $pdo->inTransaction()) {
                $pdo->commit();
            }

            return true;
        } catch (Throwable $e) {
            if ($transaccionPropia && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Actualiza múltiples parámetros en una única transacción atómica con validación previa estricta.
     * Si alguno falla (no existe, no es editable o tipo inválido), ningún cambio se aplica.
     *
     * @param array<string, mixed> $valoresPorClave
     * @param int|null $ejecutadoPorUsuarioId
     * @return array{actualizados: int, claves: array<int, string>, correlacion_id: string}
     * @throws ConfiguracionNoEncontradaExcepcion
     * @throws ConfiguracionNoEditableExcepcion
     * @throws ValidacionExcepcion
     * @throws Throwable
     */
    public function actualizarMultiples(array $valoresPorClave, ?int $ejecutadoPorUsuarioId = null): array
    {
        if (empty($valoresPorClave)) {
            return [
                'actualizados' => 0,
                'claves' => [],
                'correlacion_id' => '',
            ];
        }

        // 1. Fase de PRE-VALIDACIÓN ATÓMICA
        $parametrosAProcesar = [];
        $valoresSerializados = [];

        foreach ($valoresPorClave as $clave => $valor) {
            $claveNormalizada = trim(strtolower((string) $clave));
            $parametro = $this->configRepo->buscarPorClave($claveNormalizada);

            if ($parametro === null) {
                throw new ConfiguracionNoEncontradaExcepcion($claveNormalizada);
            }

            if (!$parametro->esEditable()) {
                throw new ConfiguracionNoEditableExcepcion($claveNormalizada);
            }

            $tipo = $parametro->obtenerTipo();
            if (!$tipo->validar($valor)) {
                throw new ValidacionExcepcion(
                    "El valor provisto para el parámetro '{$claveNormalizada}' no es válido para su tipo {$tipo->value}.",
                    ['clave' => $claveNormalizada, 'tipo' => $tipo->value]
                );
            }

            $parametrosAProcesar[$claveNormalizada] = $parametro;
            $valoresSerializados[$claveNormalizada] = $tipo->serializar($valor);
        }

        // 2. Fase de APLICACIÓN TRANSACCIONAL
        $correlacionId = 'cfg_' . bin2hex(random_bytes(10));
        $transaccionPropia = !$this->pdo->inTransaction();

        if ($transaccionPropia) {
            $this->pdo->beginTransaction();
        }

        try {
            $actualizados = 0;
            $clavesActualizadas = [];

            foreach ($parametrosAProcesar as $clave => $parametro) {
                $nuevoValor = $valoresSerializados[$clave];
                $this->actualizar($clave, $nuevoValor, $ejecutadoPorUsuarioId, $correlacionId, $this->pdo);
                $actualizados++;
                $clavesActualizadas[] = $clave;
            }

            if ($transaccionPropia && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }

            $this->limpiarCache();

            return [
                'actualizados' => $actualizados,
                'claves' => $clavesActualizadas,
                'correlacion_id' => $correlacionId,
            ];
        } catch (Throwable $e) {
            if ($transaccionPropia && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Restaura un parámetro a su valor predeterminado de fábrica (`valor = valor_predeterminado`).
     *
     * @param string $clave
     * @param int|null $ejecutadoPorUsuarioId
     * @param string|null $correlacionId
     * @param PDO|null $pdoTransaccional
     * @return bool
     * @throws ConfiguracionNoEncontradaExcepcion
     * @throws ConfiguracionNoEditableExcepcion
     */
    public function restaurarPredeterminado(
        string $clave,
        ?int $ejecutadoPorUsuarioId = null,
        ?string $correlacionId = null,
        ?PDO $pdoTransaccional = null
    ): bool {
        $claveNormalizada = trim(strtolower($clave));
        $parametro = $this->configRepo->buscarPorClave($claveNormalizada);

        if ($parametro === null) {
            throw new ConfiguracionNoEncontradaExcepcion($claveNormalizada);
        }

        if (!$parametro->esEditable()) {
            throw new ConfiguracionNoEditableExcepcion($claveNormalizada);
        }

        $pdo = $pdoTransaccional ?? $this->pdo;
        $transaccionPropia = ($pdoTransaccional === null) && !$pdo->inTransaction();

        if ($transaccionPropia) {
            $pdo->beginTransaction();
        }

        try {
            $valorAnterior = $parametro->obtenerValor();
            $valorPredeterminado = $parametro->obtenerValorPredeterminado();

            $exito = $this->configRepo->restaurarPredeterminado($claveNormalizada);
            if (!$exito) {
                if ($transaccionPropia && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                return false;
            }

            unset($this->cache[$claveNormalizada]);

            // Auditoría transversal D-061
            try {
                $actorEjecutor = $this->resolverActorEjecutor($ejecutadoPorUsuarioId);
                $this->auditoriaServicio->registrar(
                    AccionAuditoria::EDITAR,
                    'configuracion',
                    'configuraciones',
                    (string) $parametro->obtenerId(),
                    "Restauración al valor predeterminado del parámetro '{$claveNormalizada}'",
                    [
                        'clave' => $claveNormalizada,
                        'valor' => $parametro->esSensible() ? '***' : $valorAnterior,
                    ],
                    [
                        'clave' => $claveNormalizada,
                        'valor' => $parametro->esSensible() ? '***' : $valorPredeterminado,
                    ],
                    ['accion_especifica' => 'RESTAURAR_PREDETERMINADO'],
                    $actorEjecutor,
                    $ejecutadoPorUsuarioId,
                    $correlacionId,
                    $pdo
                );
            } catch (Throwable) {
                // No interrumpir si la auditoría informativa no crítica falla
            }

            if ($transaccionPropia && $pdo->inTransaction()) {
                $pdo->commit();
            }

            return true;
        } catch (Throwable $e) {
            if ($transaccionPropia && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Resuelve el actor de auditoría correspondiente al usuario ejecutor,
     * garantizando el cumplimiento estricto del principio D-061 (ACTOR != USUARIO).
     *
     * @param int|null $usuarioId ID de la cuenta de usuario (usuarios.id)
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
