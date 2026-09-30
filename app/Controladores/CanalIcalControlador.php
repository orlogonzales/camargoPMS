<?php

declare(strict_types=1);

namespace CamargoPMS\Controladores;

use CamargoPMS\Modelos\CanalDistribucion;
use CamargoPMS\Modelos\ConexionIcal;
use CamargoPMS\Modelos\EventoIcalExterno;
use CamargoPMS\Modelos\SincronizacionIcalLog;
use CamargoPMS\Modelos\Usuario;
use CamargoPMS\Nucleo\Ayudante;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Nucleo\Vista;
use CamargoPMS\Repositorios\ActorAuditoriaRepositorio;
use CamargoPMS\Repositorios\CanalDistribucionRepositorio;
use CamargoPMS\Repositorios\ConexionIcalRepositorio;
use CamargoPMS\Repositorios\EventoIcalExternoRepositorio;
use CamargoPMS\Repositorios\SincronizacionIcalLogRepositorio;
use CamargoPMS\Servicios\AuditoriaServicio;
use CamargoPMS\Servicios\AutorizacionServicio;
use CamargoPMS\Servicios\CsrfServicio;
use CamargoPMS\Servicios\IcalCriptografiaServicio;
use CamargoPMS\Servicios\SesionServicio;
use CamargoPMS\Servicios\SincronizacionIcalServicio;
use PDO;
use Throwable;

/**
 * Controlador de Gestión Administrativa y Operativa de Canales y Conexiones iCalendar (AIRBNB-ICAL-1C).
 *
 * Principios Vinculantes:
 * - ALINA DESIGN SYSTEM: Respeta patrones visuales de Alina (cards, tablas bordeadas/striped, badges sin sub-estilos).
 * - PROTECCIÓN RADICAL DE SECRETOS: Ni la URL de importación descifrada ni los tokens en claro se exponen en listados ni DOM.
 * - SOBERANÍA DEL BACKEND: Toda mutación valida CSRF, autenticación y permisos RBAC específicos.
 * - CONCURRENCIA DETERMINISTA: Locking atómico y control de ciclo de vida para evitar carreras o doble submit.
 * - PRESERVACIÓN HISTÓRICA: Cero borrado físico; revocación lógica y auditoría inmutable.
 */
class CanalIcalControlador
{
    private PDO $pdo;
    private SesionServicio $sesionServicio;
    private AutorizacionServicio $autorizacionServicio;
    private CsrfServicio $csrfServicio;
    private AuditoriaServicio $auditoriaServicio;
    private ConexionIcalRepositorio $conexionRepo;
    private CanalDistribucionRepositorio $canalRepo;
    private EventoIcalExternoRepositorio $eventoRepo;
    private SincronizacionIcalLogRepositorio $logRepo;
    private IcalCriptografiaServicio $criptoServicio;
    private SincronizacionIcalServicio $syncServicio;

    public function __construct(
        ?PDO $pdo = null,
        ?SesionServicio $sesionServicio = null,
        ?AutorizacionServicio $autorizacionServicio = null,
        ?CsrfServicio $csrfServicio = null,
        ?AuditoriaServicio $auditoriaServicio = null,
        ?ConexionIcalRepositorio $conexionRepo = null,
        ?CanalDistribucionRepositorio $canalRepo = null,
        ?EventoIcalExternoRepositorio $eventoRepo = null,
        ?SincronizacionIcalLogRepositorio $logRepo = null,
        ?IcalCriptografiaServicio $criptoServicio = null,
        ?SincronizacionIcalServicio $syncServicio = null
    ) {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->sesionServicio = $sesionServicio ?? new SesionServicio($this->pdo);
        $this->autorizacionServicio = $autorizacionServicio ?? new AutorizacionServicio($this->pdo);
        $this->csrfServicio = $csrfServicio ?? new CsrfServicio();
        $this->auditoriaServicio = $auditoriaServicio ?? new AuditoriaServicio($this->pdo);
        $this->conexionRepo = $conexionRepo ?? new ConexionIcalRepositorio($this->pdo);
        $this->canalRepo = $canalRepo ?? new CanalDistribucionRepositorio($this->pdo);
        $this->eventoRepo = $eventoRepo ?? new EventoIcalExternoRepositorio($this->pdo);
        $this->logRepo = $logRepo ?? new SincronizacionIcalLogRepositorio($this->pdo);
        $this->criptoServicio = $criptoServicio ?? new IcalCriptografiaServicio();
        $this->syncServicio = $syncServicio ?? new SincronizacionIcalServicio(
            pdo: $this->pdo,
            conexionRepo: $this->conexionRepo,
            eventoRepo: $this->eventoRepo,
            logRepo: $this->logRepo,
            criptoServicio: $this->criptoServicio
        );
    }

    /**
     * GET /canales-ical: Renderiza la pantalla operativa principal Alina.
     */
    public function index(): Respuesta
    {
        $usuario = $this->sesionServicio->validarSesionActual();
        if ($usuario === null) {
            return Respuesta::redirigir(Ayudante::ruta('/login?return=' . urlencode('/canales-ical')));
        }

        $usuarioId = (int) $usuario->obtenerId();
        if (!$this->autorizacionServicio->puede($usuarioId, 'canales.ver')) {
            $panel = new PanelControlador();
            return $panel->error(403);
        }

        $permisos = [
            'puede_gestionar' => $this->autorizacionServicio->puede($usuarioId, 'canales.gestionar'),
            'puede_sincronizar' => $this->autorizacionServicio->puede($usuarioId, 'canales.sincronizar'),
        ];

        $migasPan = [
            ['etiqueta' => 'Inicio', 'url' => Ayudante::ruta('/')],
            ['etiqueta' => 'Comercial y Reservas', 'url' => Ayudante::ruta('/reservas')],
            ['etiqueta' => 'Canales iCal', 'activo' => true],
        ];

        $canales = $this->canalRepo->listarTodos();
        $propiedades = $this->obtenerPropiedadesActivas();
        $unidades = $this->obtenerUnidadesActivas();

        $vista = new Vista();
        $html = $vista->renderizar('canales_ical/index', [
            'titulo' => 'Canales y Conexiones iCal — Camargo PMS',
            'usuario' => $usuario,
            'permisos' => $permisos,
            'csrf_token' => $this->csrfServicio->obtenerToken(),
            'migasPan' => $migasPan,
            'categoriaActiva' => 'reservas',
            'canales' => $canales,
            'propiedades' => $propiedades,
            'unidades' => $unidades,
        ]);

        return new Respuesta($html);
    }

    /**
     * GET /canales-ical/datos: Retorna listado de conexiones, KPIs y catálogos en JSON (CERO SECRETOS).
     */
    public function datosJson(): Respuesta
    {
        $usuario = $this->sesionServicio->validarSesionActual();
        if ($usuario === null) {
            return Respuesta::json(['ok' => false, 'error' => 'Sesión no válida.'], 401);
        }

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'canales.ver')) {
            return Respuesta::json(['ok' => false, 'error' => 'No autorizado para ver canales.'], 403);
        }

        $propiedadId = isset($_GET['propiedad_id']) && is_numeric($_GET['propiedad_id']) ? (int) $_GET['propiedad_id'] : null;
        $unidadId = isset($_GET['unidad_id']) && is_numeric($_GET['unidad_id']) ? (int) $_GET['unidad_id'] : null;
        $canalId = isset($_GET['canal_id']) && is_numeric($_GET['canal_id']) ? (int) $_GET['canal_id'] : null;
        $estado = isset($_GET['estado']) && is_string($_GET['estado']) && trim($_GET['estado']) !== '' ? trim($_GET['estado']) : null;

        $conexiones = $this->conexionRepo->listarTodas($propiedadId, $unidadId, $canalId, $estado);

        $listaConexiones = array_map(function (ConexionIcal $c) {
            $arr = $c->aArray();
            $arr['tiene_url_importacion'] = $c->tieneUrlImportacion();
            return $arr;
        }, $conexiones);

        // Indicadores reales
        $todas = $this->conexionRepo->listarTodas();
        $totalConexiones = count($todas);
        $activas = 0;
        $conError = 0;
        $conAdvertencia = 0;

        foreach ($todas as $c) {
            if ($c->estaActiva()) {
                $activas++;
            }
            if ($c->obtenerUltimoResultado() === ConexionIcal::RESULTADO_ERROR) {
                $conError++;
            }
            if ($c->obtenerUltimoResultado() === ConexionIcal::RESULTADO_CON_ADVERTENCIA) {
                $conAdvertencia++;
            }
        }

        $conflictos = $this->eventoRepo->contarConflictosActivos();
        $syncsHoy = $this->logRepo->contarSincronizacionesHoy();

        $kpis = [
            'total_conexiones' => $totalConexiones,
            'activas' => $activas,
            'con_error' => $conError,
            'con_advertencia' => $conAdvertencia,
            'conflictos_pendientes' => $conflictos,
            'syncs_hoy' => $syncsHoy,
        ];

        return Respuesta::json([
            'ok' => true,
            'conexiones' => $listaConexiones,
            'kpis' => $kpis,
            'canales' => array_map(fn(CanalDistribucion $cd) => $cd->aArray(), $this->canalRepo->listarTodos()),
            'propiedades' => $this->obtenerPropiedadesActivas(),
            'unidades' => $this->obtenerUnidadesActivas(),
        ]);
    }

    /**
     * POST /canales-ical: Crea una nueva conexión iCal vinculada a unidad y canal.
     */
    public function crear(): Respuesta
    {
        $usuario = $this->sesionServicio->validarSesionActual();
        if ($usuario === null) {
            return Respuesta::json(['ok' => false, 'error' => 'Sesión no válida.'], 401);
        }

        $usuarioId = (int) $usuario->obtenerId();
        if (!$this->autorizacionServicio->puede($usuarioId, 'canales.gestionar')) {
            return Respuesta::json(['ok' => false, 'error' => 'No autorizado para gestionar conexiones iCal.'], 403);
        }

        $datos = $this->obtenerCuerpoPeticion();
        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'error' => 'Token CSRF inválido o ausente.'], 403);
        }

        $unidadId = isset($datos['unidad_id']) ? (int) $datos['unidad_id'] : 0;
        $canalId = isset($datos['canal_id']) ? (int) $datos['canal_id'] : 0;
        $nombre = isset($datos['nombre']) ? trim((string) $datos['nombre']) : '';
        $importacionHabilitada = !empty($datos['importacion_habilitada']);
        $urlImportacion = isset($datos['url_importacion']) ? trim((string) $datos['url_importacion']) : '';
        $exportacionHabilitada = !empty($datos['exportacion_habilitada']);
        $frecuenciaMinutos = isset($datos['frecuencia_minutos']) ? max(15, min(1440, (int) $datos['frecuencia_minutos'])) : 60;
        $estado = isset($datos['estado']) && in_array($datos['estado'], [ConexionIcal::ESTADO_ACTIVO, ConexionIcal::ESTADO_PAUSADO], true)
            ? $datos['estado']
            : ConexionIcal::ESTADO_ACTIVO;

        // Validaciones
        if ($unidadId <= 0) {
            return Respuesta::json(['ok' => false, 'error' => 'Debe seleccionar una unidad válida.'], 422);
        }

        if ($canalId <= 0 || !$this->canalRepo->buscarPorId($canalId)) {
            return Respuesta::json(['ok' => false, 'error' => 'Debe seleccionar un canal de distribución válido.'], 422);
        }

        if ($nombre === '' || mb_strlen($nombre) < 3 || mb_strlen($nombre) > 150) {
            return Respuesta::json(['ok' => false, 'error' => 'El nombre debe contener entre 3 y 150 caracteres.'], 422);
        }

        $urlCifrada = null;
        if ($urlImportacion !== '') {
            $errorUrl = $this->validarUrlImportacion($urlImportacion);
            if ($errorUrl !== null) {
                return Respuesta::json(['ok' => false, 'error' => $errorUrl], 422);
            }
            try {
                $urlCifrada = $this->criptoServicio->cifrar($urlImportacion);
            } catch (Throwable $e) {
                return Respuesta::json(['ok' => false, 'error' => 'Error criptográfico al asegurar la URL de importación.'], 500);
            }
        } elseif ($importacionHabilitada) {
            return Respuesta::json(['ok' => false, 'error' => 'Si habilita la importación debe ingresar una URL iCal privada.'], 422);
        }

        // Generar credencial de exportación de forma soberana y criptográfica
        try {
            $tokenPlano = bin2hex(random_bytes(32)); // 64 chars hex (256 bits entropía)
            $tokenHash = hash('sha256', $tokenPlano);
            $tokenCifrado = $this->criptoServicio->cifrar($tokenPlano);
            $tokenPrefijo = substr($tokenPlano, 0, 8);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'error' => 'Error al generar credenciales criptográficas de exportación.'], 500);
        }

        $conexion = new ConexionIcal(
            id: null,
            unidadId: $unidadId,
            canalId: $canalId,
            nombre: $nombre,
            urlImportacionCifrada: $urlCifrada,
            tokenExportacionHash: $tokenHash,
            tokenExportacionCifrado: $tokenCifrado,
            tokenPrefijo: $tokenPrefijo,
            importacionHabilitada: $importacionHabilitada,
            exportacionHabilitada: $exportacionHabilitada,
            frecuenciaMinutos: $frecuenciaMinutos,
            estado: $estado,
            creadoPor: $usuarioId
        );

        try {
            $id = $this->conexionRepo->crear($conexion);

            // Auditoría inmutable sin secretos
            $this->auditoriaServicio->registrar(
                accion: 'CREAR',
                modulo: 'canales',
                entidad: 'conexion_ical',
                entidadId: $id,
                descripcion: "Conexión iCal creada: {$nombre} (Unidad #{$unidadId}, Canal #{$canalId})",
                valoresNuevos: [
                    'id' => $id,
                    'unidad_id' => $unidadId,
                    'canal_id' => $canalId,
                    'nombre' => $nombre,
                    'estado' => $estado,
                    'importacion_habilitada' => $importacionHabilitada,
                    'exportacion_habilitada' => $exportacionHabilitada,
                    'frecuencia_minutos' => $frecuenciaMinutos,
                    'token_prefijo' => $tokenPrefijo,
                ],
                usuarioId: $usuarioId
            );

            return Respuesta::json([
                'ok' => true,
                'mensaje' => 'Conexión iCal creada exitosamente.',
                'id' => $id,
            ], 201);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'error' => 'Error al registrar la conexión: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /canales-ical/{id}/editar: Modifica parámetros de la conexión.
     * Regla 15: Si nueva_url_importacion viene vacía, se conserva la actual intacta.
     */
    public function editar(int|string $id): Respuesta
    {
        $usuario = $this->sesionServicio->validarSesionActual();
        if ($usuario === null) {
            return Respuesta::json(['ok' => false, 'error' => 'Sesión no válida.'], 401);
        }

        $usuarioId = (int) $usuario->obtenerId();
        if (!$this->autorizacionServicio->puede($usuarioId, 'canales.gestionar')) {
            return Respuesta::json(['ok' => false, 'error' => 'No autorizado para gestionar conexiones iCal.'], 403);
        }

        $datos = $this->obtenerCuerpoPeticion();
        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'error' => 'Token CSRF inválido o ausente.'], 403);
        }

        $conexion = $this->conexionRepo->buscarPorId((int) $id);
        if (!$conexion) {
            return Respuesta::json(['ok' => false, 'error' => 'Conexión iCal no encontrada.'], 404);
        }

        $nombre = isset($datos['nombre']) ? trim((string) $datos['nombre']) : '';
        $importacionHabilitada = !empty($datos['importacion_habilitada']);
        $nuevaUrl = isset($datos['nueva_url_importacion']) ? trim((string) $datos['nueva_url_importacion']) : '';
        $exportacionHabilitada = !empty($datos['exportacion_habilitada']);
        $frecuenciaMinutos = isset($datos['frecuencia_minutos']) ? max(15, min(1440, (int) $datos['frecuencia_minutos'])) : 60;
        $estado = isset($datos['estado']) && in_array($datos['estado'], [ConexionIcal::ESTADO_ACTIVO, ConexionIcal::ESTADO_PAUSADO, ConexionIcal::ESTADO_REVOCADO], true)
            ? $datos['estado']
            : $conexion->obtenerEstado();

        if ($nombre === '' || mb_strlen($nombre) < 3 || mb_strlen($nombre) > 150) {
            return Respuesta::json(['ok' => false, 'error' => 'El nombre debe contener entre 3 y 150 caracteres.'], 422);
        }

        // Regla 15: Semántica de URL
        $urlCifrada = $conexion->obtenerUrlImportacionCifrada();
        if ($nuevaUrl !== '') {
            $errorUrl = $this->validarUrlImportacion($nuevaUrl);
            if ($errorUrl !== null) {
                return Respuesta::json(['ok' => false, 'error' => $errorUrl], 422);
            }
            try {
                $urlCifrada = $this->criptoServicio->cifrar($nuevaUrl);
            } catch (Throwable $e) {
                return Respuesta::json(['ok' => false, 'error' => 'Error criptográfico al asegurar la nueva URL.'], 500);
            }
        } elseif ($importacionHabilitada && !$conexion->tieneUrlImportacion()) {
            return Respuesta::json(['ok' => false, 'error' => 'Debe configurar una URL iCal privada para habilitar la importación.'], 422);
        }

        $conexionActualizada = new ConexionIcal(
            id: (int) $id,
            unidadId: $conexion->obtenerUnidadId(),
            canalId: $conexion->obtenerCanalId(),
            nombre: $nombre,
            urlImportacionCifrada: $urlCifrada,
            tokenExportacionHash: $conexion->obtenerTokenExportacionHash(),
            tokenExportacionCifrado: $conexion->obtenerTokenExportacionCifrado(),
            tokenPrefijo: $conexion->obtenerTokenPrefijo(),
            importacionHabilitada: $importacionHabilitada,
            exportacionHabilitada: $exportacionHabilitada,
            frecuenciaMinutos: $frecuenciaMinutos,
            estado: $estado
        );

        try {
            $this->conexionRepo->actualizar($conexionActualizada);

            // Auditoría inmutable sin secretos
            $this->auditoriaServicio->registrar(
                accion: 'EDITAR',
                modulo: 'canales',
                entidad: 'conexion_ical',
                entidadId: (int) $id,
                descripcion: "Conexión iCal editada: {$nombre}",
                valoresAnteriores: [
                    'nombre' => $conexion->obtenerNombre(),
                    'estado' => $conexion->obtenerEstado(),
                    'importacion_habilitada' => $conexion->importacionHabilitada(),
                    'exportacion_habilitada' => $conexion->exportacionHabilitada(),
                    'frecuencia_minutos' => $conexion->obtenerFrecuenciaMinutos(),
                ],
                valoresNuevos: [
                    'nombre' => $nombre,
                    'estado' => $estado,
                    'importacion_habilitada' => $importacionHabilitada,
                    'exportacion_habilitada' => $exportacionHabilitada,
                    'frecuencia_minutos' => $frecuenciaMinutos,
                    'url_actualizada' => ($nuevaUrl !== ''),
                ],
                usuarioId: $usuarioId
            );

            return Respuesta::json(['ok' => true, 'mensaje' => 'Conexión actualizada exitosamente.']);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'error' => 'Error al actualizar: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /canales-ical/{id}/estado: Cambia estado de la conexión (ACTIVO, PAUSADO, REVOCADO).
     */
    public function cambiarEstado(int|string $id): Respuesta
    {
        $usuario = $this->sesionServicio->validarSesionActual();
        if ($usuario === null) {
            return Respuesta::json(['ok' => false, 'error' => 'Sesión no válida.'], 401);
        }

        $usuarioId = (int) $usuario->obtenerId();
        if (!$this->autorizacionServicio->puede($usuarioId, 'canales.gestionar')) {
            return Respuesta::json(['ok' => false, 'error' => 'No autorizado para gestionar conexiones iCal.'], 403);
        }

        $datos = $this->obtenerCuerpoPeticion();
        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'error' => 'Token CSRF inválido o ausente.'], 403);
        }

        $conexion = $this->conexionRepo->buscarPorId((int) $id);
        if (!$conexion) {
            return Respuesta::json(['ok' => false, 'error' => 'Conexión iCal no encontrada.'], 404);
        }

        $nuevoEstado = isset($datos['estado']) ? strtoupper(trim((string) $datos['estado'])) : '';
        if (!in_array($nuevoEstado, [ConexionIcal::ESTADO_ACTIVO, ConexionIcal::ESTADO_PAUSADO, ConexionIcal::ESTADO_REVOCADO], true)) {
            return Respuesta::json(['ok' => false, 'error' => 'Estado no válido.'], 422);
        }

        try {
            $this->conexionRepo->cambiarEstado((int) $id, $nuevoEstado);

            $this->auditoriaServicio->registrar(
                accion: 'CAMBIAR_ESTADO',
                modulo: 'canales',
                entidad: 'conexion_ical',
                entidadId: (int) $id,
                descripcion: "Estado de conexión #$id cambiado de {$conexion->obtenerEstado()} a $nuevoEstado",
                valoresAnteriores: ['estado' => $conexion->obtenerEstado()],
                valoresNuevos: ['estado' => $nuevoEstado],
                usuarioId: $usuarioId
            );

            return Respuesta::json([
                'ok' => true,
                'mensaje' => "Conexión cambiada a estado {$nuevoEstado}.",
                'nuevo_estado' => $nuevoEstado,
            ]);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'error' => 'Error al actualizar estado: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /canales-ical/{id}/rotar-token: Invalida el feed de exportación anterior y genera uno nuevo.
     */
    public function rotarToken(int|string $id): Respuesta
    {
        $usuario = $this->sesionServicio->validarSesionActual();
        if ($usuario === null) {
            return Respuesta::json(['ok' => false, 'error' => 'Sesión no válida.'], 401);
        }

        $usuarioId = (int) $usuario->obtenerId();
        if (!$this->autorizacionServicio->puede($usuarioId, 'canales.gestionar')) {
            return Respuesta::json(['ok' => false, 'error' => 'No autorizado para rotar credenciales iCal.'], 403);
        }

        $datos = $this->obtenerCuerpoPeticion();
        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'error' => 'Token CSRF inválido o ausente.'], 403);
        }

        $conexion = $this->conexionRepo->buscarPorId((int) $id);
        if (!$conexion) {
            return Respuesta::json(['ok' => false, 'error' => 'Conexión iCal no encontrada.'], 404);
        }

        try {
            $nuevoTokenPlano = bin2hex(random_bytes(32));
            $nuevoHash = hash('sha256', $nuevoTokenPlano);
            $nuevoCifrado = $this->criptoServicio->cifrar($nuevoTokenPlano);
            $nuevoPrefijo = substr($nuevoTokenPlano, 0, 8);

            $this->conexionRepo->rotarTokenExportacion((int) $id, $nuevoHash, $nuevoCifrado, $nuevoPrefijo);

            // Auditoría estricta sin secretos
            $this->auditoriaServicio->registrar(
                accion: 'ROTAR_TOKEN',
                modulo: 'canales',
                entidad: 'conexion_ical',
                entidadId: (int) $id,
                descripcion: "Token de exportación rotado para conexión #$id ({$conexion->obtenerNombre()})",
                valoresAnteriores: ['token_prefijo' => $conexion->obtenerTokenPrefijo()],
                valoresNuevos: ['token_prefijo' => $nuevoPrefijo],
                usuarioId: $usuarioId
            );

            return Respuesta::json([
                'ok' => true,
                'mensaje' => 'Token de exportación rotado exitosamente. La URL anterior ha sido revocada de forma inmediata.',
                'nuevo_prefijo' => $nuevoPrefijo,
            ]);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'error' => 'Error al rotar token: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /canales-ical/{id}/copiar-feed: Recupera la URL de exportación descifrada bajo demanda explícita.
     * Regla 16: Cero token en listados, dataset, logs, auditoría o HTML inicial.
     */
    public function copiarFeed(int|string $id): Respuesta
    {
        $usuario = $this->sesionServicio->validarSesionActual();
        if ($usuario === null) {
            return Respuesta::json(['ok' => false, 'error' => 'Sesión no válida.'], 401);
        }

        $usuarioId = (int) $usuario->obtenerId();
        if (!$this->autorizacionServicio->puede($usuarioId, 'canales.ver') && !$this->autorizacionServicio->puede($usuarioId, 'canales.gestionar')) {
            return Respuesta::json(['ok' => false, 'error' => 'No autorizado para acceder al feed de exportación.'], 403);
        }

        $datos = $this->obtenerCuerpoPeticion();
        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'error' => 'Token CSRF inválido o ausente.'], 403);
        }

        $conexion = $this->conexionRepo->buscarPorId((int) $id);
        if (!$conexion) {
            return Respuesta::json(['ok' => false, 'error' => 'Conexión iCal no encontrada.'], 404);
        }

        if (!$conexion->exportacionHabilitada()) {
            return Respuesta::json(['ok' => false, 'error' => 'La exportación está deshabilitada para esta conexión.'], 422);
        }

        if ($conexion->estaRevocada()) {
            return Respuesta::json(['ok' => false, 'error' => 'La conexión se encuentra en estado REVOCADO.'], 422);
        }

        try {
            $tokenPlano = $this->criptoServicio->descifrar($conexion->obtenerTokenExportacionCifrado());
            if (!$tokenPlano) {
                return Respuesta::json(['ok' => false, 'error' => 'No fue posible descifrar la credencial de exportación.'], 500);
            }

            $urlFeed = Ayudante::ruta('/ical/exportar/' . $tokenPlano);

            return Respuesta::json([
                'ok' => true,
                'url_feed' => $urlFeed,
            ], 200, [
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
                'Pragma' => 'no-cache',
            ]);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'error' => 'Error al recuperar URL de exportación: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /canales-ical/{id}/sincronizar: Dispara sincronización manual con control de concurrencia y locking atómico.
     */
    public function sincronizar(int|string $id): Respuesta
    {
        $usuario = $this->sesionServicio->validarSesionActual();
        if ($usuario === null) {
            return Respuesta::json(['ok' => false, 'error' => 'Sesión no válida.'], 401);
        }

        $usuarioId = (int) $usuario->obtenerId();
        if (!$this->autorizacionServicio->puede($usuarioId, 'canales.sincronizar')) {
            return Respuesta::json(['ok' => false, 'error' => 'No autorizado para sincronizar conexiones iCal.'], 403);
        }

        $datos = $this->obtenerCuerpoPeticion();
        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'error' => 'Token CSRF inválido o ausente.'], 403);
        }

        $conexionId = (int) $id;
        $conexion = $this->conexionRepo->buscarPorId($conexionId);
        if (!$conexion) {
            return Respuesta::json(['ok' => false, 'error' => 'Conexión iCal no encontrada.'], 404);
        }

        if (!$conexion->estaActiva()) {
            return Respuesta::json(['ok' => false, 'error' => 'La conexión no está activa (estado actual: ' . $conexion->obtenerEstado() . ').'], 422);
        }

        if (!$conexion->importacionHabilitada()) {
            return Respuesta::json(['ok' => false, 'error' => 'La importación está deshabilitada para esta conexión.'], 422);
        }

        // 1. Control de concurrencia a nivel de ciclo de vida (log activo)
        if ($this->logRepo->haySincronizacionEnCurso($conexionId)) {
            return Respuesta::json([
                'ok' => false,
                'error' => 'Ya existe una sincronización en curso para esta conexión. Por favor espere a que finalice.',
                'codigo' => 'CONEXION_EN_SINCRONIZACION',
            ], 409);
        }

        // 2. Control atómico de concurrencia MySQL (GET_LOCK no bloqueante)
        $lockName = "camargo_ical_sync_{$conexionId}";
        $stmtLock = $this->pdo->prepare('SELECT GET_LOCK(:lock_name, 0)');
        $stmtLock->execute(['lock_name' => $lockName]);
        $lockAdquirido = (int) $stmtLock->fetchColumn() === 1;

        if (!$lockAdquirido) {
            return Respuesta::json([
                'ok' => false,
                'error' => 'Ya existe una sincronización en curso para esta conexión (bloqueo concurrente activo).',
                'codigo' => 'CONEXION_BLOQUEADA',
            ], 409);
        }

        $actorId = $this->resolverActorId($usuario);

        try {
            $resultado = $this->syncServicio->sincronizarConexion(
                conexionId: $conexionId,
                payloadIcsOpcional: null,
                origenEjecucion: SincronizacionIcalLog::ORIGEN_MANUAL,
                actorId: $actorId
            );

            // Auditoría de la sincronización manual sin secretos
            $this->auditoriaServicio->registrar(
                accion: 'SINCRONIZAR_MANUAL',
                modulo: 'canales',
                entidad: 'conexion_ical',
                entidadId: $conexionId,
                descripcion: "Sincronización manual ejecutada: {$conexion->obtenerNombre()} — Resultado: {$resultado['resultado']}",
                valoresNuevos: [
                    'resultado' => $resultado['resultado'],
                    'eventos_recibidos' => $resultado['eventos_recibidos'] ?? 0,
                    'eventos_creados' => $resultado['eventos_creados'] ?? 0,
                    'eventos_actualizados' => $resultado['eventos_actualizados'] ?? 0,
                    'eventos_cancelados' => $resultado['eventos_cancelados'] ?? 0,
                    'eventos_ausentes' => $resultado['eventos_ausentes'] ?? 0,
                    'conflictos' => $resultado['conflictos'] ?? 0,
                ],
                usuarioId: $usuarioId
            );

            return Respuesta::json([
                'ok' => true,
                'mensaje' => $resultado['mensaje'] ?? 'Sincronización finalizada.',
                'datos' => $resultado,
            ]);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'error' => 'Error durante la sincronización: ' . $e->getMessage(),
            ], 500);
        } finally {
            // Liberación garantizada del semáforo atómico
            try {
                $stmtRelease = $this->pdo->prepare('SELECT RELEASE_LOCK(:lock_name)');
                $stmtRelease->execute(['lock_name' => $lockName]);
            } catch (Throwable) {
                // Fallback continuo
            }
        }
    }

    /**
     * GET /canales-ical/{id}/historial: Retorna el historial de sincronizaciones técnicas (CERO SECRETOS).
     */
    public function historial(int|string $id): Respuesta
    {
        $usuario = $this->sesionServicio->validarSesionActual();
        if ($usuario === null) {
            return Respuesta::json(['ok' => false, 'error' => 'Sesión no válida.'], 401);
        }

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'canales.ver')) {
            return Respuesta::json(['ok' => false, 'error' => 'No autorizado para ver historial.'], 403);
        }

        $conexion = $this->conexionRepo->buscarPorId((int) $id);
        if (!$conexion) {
            return Respuesta::json(['ok' => false, 'error' => 'Conexión iCal no encontrada.'], 404);
        }

        $logs = $this->logRepo->listarPorConexion((int) $id, 25);

        $datos = array_map(function (SincronizacionIcalLog $log) {
            return [
                'id' => $log->obtenerId(),
                'tipo_operacion' => $log->obtenerTipoOperacion(),
                'origen_ejecucion' => $log->obtenerOrigenEjecucion(),
                'iniciado_en' => $log->obtenerIniciadoEn(),
                'finalizado_en' => $log->obtenerFinalizadoEn(),
                'duracion_ms' => $log->obtenerDuracionMs(),
                'http_codigo' => $log->obtenerHttpCodigo(),
                'resultado' => $log->obtenerResultado(),
                'eventos_recibidos' => $log->obtenerEventosRecibidos(),
                'eventos_creados' => $log->obtenerEventosCreados(),
                'eventos_actualizados' => $log->obtenerEventosActualizados(),
                'eventos_cancelados' => $log->obtenerEventosCancelados(),
                'eventos_ausentes' => $log->obtenerEventosAusentes(),
                'conflictos_detectados' => $log->obtenerConflictosDetectados(),
                'mensaje_resultado' => $log->obtenerMensajeResultado(),
            ];
        }, $logs);

        return Respuesta::json([
            'ok' => true,
            'conexion_nombre' => $conexion->obtenerNombre(),
            'historial' => $datos,
        ]);
    }

    /**
     * GET /canales-ical/{id}/conflictos: Retorna los conflictos de una conexión específica.
     */
    public function conflictos(int|string $id): Respuesta
    {
        $usuario = $this->sesionServicio->validarSesionActual();
        if ($usuario === null) {
            return Respuesta::json(['ok' => false, 'error' => 'Sesión no válida.'], 401);
        }

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'canales.ver')) {
            return Respuesta::json(['ok' => false, 'error' => 'No autorizado para ver conflictos.'], 403);
        }

        $conexion = $this->conexionRepo->buscarPorId((int) $id);
        if (!$conexion) {
            return Respuesta::json(['ok' => false, 'error' => 'Conexión iCal no encontrada.'], 404);
        }

        $conflictos = $this->eventoRepo->listarConflictosPorConexion((int) $id);

        return Respuesta::json([
            'ok' => true,
            'conexion_nombre' => $conexion->obtenerNombre(),
            'conflictos' => $conflictos,
        ]);
    }

    /**
     * GET /canales-ical/conflictos-activos: Retorna todos los conflictos activos del sistema.
     */
    public function todosLosConflictos(): Respuesta
    {
        $usuario = $this->sesionServicio->validarSesionActual();
        if ($usuario === null) {
            return Respuesta::json(['ok' => false, 'error' => 'Sesión no válida.'], 401);
        }

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'canales.ver')) {
            return Respuesta::json(['ok' => false, 'error' => 'No autorizado para ver conflictos.'], 403);
        }

        $conflictos = $this->eventoRepo->listarTodosLosConflictos();

        return Respuesta::json([
            'ok' => true,
            'conflictos' => $conflictos,
        ]);
    }

    // =========================================================================
    // MÉTODOS AUXILIARES PRIVADOS Y DEFENSIVOS
    // =========================================================================

    /**
     * Valida formato sintáctico y de seguridad para URLs privadas de importación iCal.
     */
    private function validarUrlImportacion(string $url): ?string
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return 'La URL ingresada no posee un formato web válido.';
        }

        $parsed = parse_url($url);
        $scheme = strtolower($parsed['scheme'] ?? '');
        $host = strtolower($parsed['host'] ?? '');

        if ($scheme !== 'https') {
            return 'Por seguridad estricta, la URL iCal debe utilizar el protocolo HTTPS.';
        }

        if ($host === '' || in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true) || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            return 'No se permiten URLs que apunten a loopback, nombres internos o redes locales.';
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function obtenerCuerpoPeticion(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $input = file_get_contents('php://input');
            if ($input !== false && $input !== '') {
                $decoded = json_decode($input, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        return $_POST;
    }

    /**
     * @param array<string, mixed> $datos
     */
    private function validarCsrf(array $datos): bool
    {
        $token = $datos['_csrf'] ?? $datos['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if ($token === null || !is_string($token) || trim($token) === '') {
            return false;
        }

        return $this->csrfServicio->validarToken(trim($token));
    }

    private function resolverActorId(?Usuario $usuario): int
    {
        if ($usuario !== null && $usuario->obtenerId() !== null) {
            try {
                $actor = $this->auditoriaServicio->obtenerOAsegurarActorUsuario((int) $usuario->obtenerId(), $this->pdo);
                if ($actor && $actor->obtenerId() !== null) {
                    return (int) $actor->obtenerId();
                }
            } catch (Throwable) {
                // Fallback continuo
            }
        }

        try {
            $repo = new ActorAuditoriaRepositorio($this->pdo);
            $sistema = $repo->buscarPorCodigo('CAMARGO_PMS');
            if ($sistema && $sistema->obtenerId() !== null) {
                return (int) $sistema->obtenerId();
            }
        } catch (Throwable) {
            // Fallback continuo
        }

        return 1;
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function obtenerPropiedadesActivas(): array
    {
        $sql = "SELECT id, nombre, codigo FROM propiedades WHERE estado = 'ACTIVO' ORDER BY nombre ASC";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function obtenerUnidadesActivas(): array
    {
        $sql = "SELECT u.id, u.propiedad_id, u.nombre, u.codigo, p.nombre AS propiedad_nombre
                FROM unidades u
                JOIN propiedades p ON p.id = u.propiedad_id
                WHERE u.estado = 'ACTIVO'
                ORDER BY p.nombre ASC, u.nombre ASC";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
}
