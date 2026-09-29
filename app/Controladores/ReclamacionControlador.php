<?php

declare(strict_types=1);

namespace CamargoPMS\Controladores;

use CamargoPMS\Excepciones\ReclamacionNoEncontradaExcepcion;
use CamargoPMS\Excepciones\TransicionReclamacionInvalidaExcepcion;
use CamargoPMS\Excepciones\ValidacionReclamacionExcepcion;
use CamargoPMS\Modelos\Reclamacion;
use CamargoPMS\Modelos\Usuario;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Nucleo\Vista;
use CamargoPMS\Repositorios\ActorAuditoriaRepositorio;
use CamargoPMS\Repositorios\PropiedadRepositorio;
use CamargoPMS\Repositorios\TipoDocumentoRepositorio;
use CamargoPMS\Servicios\AutorizacionServicio;
use CamargoPMS\Servicios\CalculadorPlazosReclamacion;
use CamargoPMS\Servicios\CsrfServicio;
use CamargoPMS\Servicios\ReclamacionServicio;
use CamargoPMS\Servicios\SesionServicio;
use PDO;
use Throwable;

/**
 * Controlador de gestión interna para el Libro de Reclamaciones (RECLAMACIONES-1).
 */
class ReclamacionControlador
{
    private Vista $vista;
    private PDO $pdo;
    private ReclamacionServicio $reclamacionServicio;
    private CalculadorPlazosReclamacion $calculadorPlazos;
    private PropiedadRepositorio $propiedadRepo;
    private TipoDocumentoRepositorio $tipoDocRepo;
    private SesionServicio $sesionServicio;
    private AutorizacionServicio $autorizacionServicio;
    private CsrfServicio $csrfServicio;

    public function __construct(
        ?Vista $vista = null,
        ?PDO $pdo = null,
        ?ReclamacionServicio $reclamacionServicio = null,
        ?CalculadorPlazosReclamacion $calculadorPlazos = null,
        ?PropiedadRepositorio $propiedadRepo = null,
        ?TipoDocumentoRepositorio $tipoDocRepo = null,
        ?SesionServicio $sesionServicio = null,
        ?AutorizacionServicio $autorizacionServicio = null,
        ?CsrfServicio $csrfServicio = null
    ) {
        $this->vista = $vista ?? new Vista();
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->reclamacionServicio = $reclamacionServicio ?? new ReclamacionServicio($this->pdo);
        $this->calculadorPlazos = $calculadorPlazos ?? new CalculadorPlazosReclamacion(new \CamargoPMS\Repositorios\FeriadoRepositorio($this->pdo));
        $this->propiedadRepo = $propiedadRepo ?? new PropiedadRepositorio($this->pdo);
        $this->tipoDocRepo = $tipoDocRepo ?? new TipoDocumentoRepositorio($this->pdo);
        $this->sesionServicio = $sesionServicio ?? new SesionServicio($this->pdo);
        $this->autorizacionServicio = $autorizacionServicio ?? new AutorizacionServicio($this->pdo);
        $this->csrfServicio = $csrfServicio ?? new CsrfServicio();
    }

    private function obtenerUsuarioAutenticado(): ?Usuario
    {
        SesionServicio::iniciarSesionPhp();
        return $this->sesionServicio->validarSesionActual();
    }

    private function resolverActorId(): int
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if ($usuario !== null && $usuario->obtenerId() !== null) {
            $actorRepo = new ActorAuditoriaRepositorio($this->pdo);
            $actor = $actorRepo->buscarPorUsuarioId((int) $usuario->obtenerId());
            if ($actor !== null && $actor->obtenerId() !== null) {
                return (int) $actor->obtenerId();
            }
        }
        return 1;
    }

    private function validarCsrf(?array $datos = null): ?Respuesta
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? $_POST['csrf_token']
            ?? $_POST['_csrf_token']
            ?? ($datos['csrf_token'] ?? null)
            ?? ($datos['_csrf_token'] ?? null);

        if (!$token || !$this->csrfServicio->validarToken((string) $token)) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'Token CSRF inválido o expirado.'], 403);
        }

        return null;
    }

    private function leerEntrada(): array
    {
        $raw = file_get_contents('php://input');
        if (!empty($raw)) {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                return array_merge($_POST, $json);
            }
        }
        return $_POST;
    }

    /**
     * Directorio de Expedientes (GET /reclamaciones).
     */
    public function index(): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if ($usuario === null) {
            return Respuesta::redirigir('/login');
        }

        $usuarioId = (int) $usuario->obtenerId();
        if (!$this->autorizacionServicio->puede($usuarioId, 'reclamaciones.ver')) {
            return new Respuesta($this->vista->renderizar('errores/403', [
                'titulo' => '403 — Acceso Denegado',
                'mensaje' => 'No cuenta con permisos para consultar los expedientes de reclamaciones.',
                'usuario' => $usuario,
            ]), 403);
        }

        $propiedades = $this->propiedadRepo->listar(null, 'ACTIVO');
        $tiposDoc = $this->tipoDocRepo->listarActivos();
        $kpis = $this->reclamacionServicio->obtenerKpis();

        $permisos = [
            'puede_crear' => $this->autorizacionServicio->puede($usuarioId, 'reclamaciones.crear'),
            'puede_gestionar' => $this->autorizacionServicio->puede($usuarioId, 'reclamaciones.gestionar'),
            'puede_actuar' => $this->autorizacionServicio->puede($usuarioId, 'reclamaciones.actuar'),
            'puede_responder' => $this->autorizacionServicio->puede($usuarioId, 'reclamaciones.responder'),
            'puede_anular' => $this->autorizacionServicio->puede($usuarioId, 'reclamaciones.anular'),
        ];

        $html = $this->vista->renderizar('reclamaciones/index', [
            'titulo' => 'Libro de Reclamaciones — Camargo PMS',
            'categoriaActiva' => 'reservas',
            'subcategoriaActiva' => 'reclamaciones_directorio',
            'migasPan' => [
                ['etiqueta' => 'Panel', 'url' => url_ruta('/'), 'activo' => false],
                ['etiqueta' => 'Operaciones', 'url' => url_ruta('/reservas'), 'activo' => false],
                ['etiqueta' => 'Libro de Reclamaciones', 'url' => url_ruta('/reclamaciones'), 'activo' => true],
            ],
            'usuario' => $usuario,
            'propiedades' => $propiedades,
            'tiposDoc' => $tiposDoc,
            'kpis' => $kpis,
            'permisos' => $permisos,
            'csrf_token' => $this->csrfServicio->generarToken(),
        ]);

        return new Respuesta($html);
    }

    /**
     * Endpoint API JSON para el listado de expedientes con filtros y semáforos (GET /reclamaciones/datos).
     */
    public function apiListar(): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if ($usuario === null) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'No autenticado.'], 401);
        }

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'reclamaciones.ver')) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'Acceso denegado.'], 403);
        }

        $filtros = [
            'propiedad_id' => !empty($_GET['propiedad_id']) ? (int) $_GET['propiedad_id'] : null,
            'estado' => !empty($_GET['estado']) ? trim((string) $_GET['estado']) : null,
            'tipo' => !empty($_GET['tipo']) ? trim((string) $_GET['tipo']) : null,
            'anio' => !empty($_GET['anio']) ? (int) $_GET['anio'] : null,
            'busqueda' => !empty($_GET['busqueda']) ? trim((string) $_GET['busqueda']) : null,
        ];

        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
        $limite = min(100, max(5, (int) ($_GET['limite'] ?? 25)));
        $offset = ($pagina - 1) * $limite;

        $total = $this->reclamacionServicio->contar($filtros);
        $reclamaciones = $this->reclamacionServicio->listar($filtros, $limite, $offset);

        $datos = [];
        foreach ($reclamaciones as $rec) {
            $fila = $rec->aArreglo();
            $fila['semaforo'] = $this->calculadorPlazos->calcularSemaforo($rec);
            $fila['url_detalle'] = url_ruta('/reclamaciones/' . $rec->obtenerId());
            $fila['url_pdf'] = url_ruta('/reclamaciones/' . $rec->obtenerId() . '/pdf');
            $datos[] = $fila;
        }

        return Respuesta::json([
            'exito' => true,
            'datos' => $datos,
            'paginacion' => [
                'total_registros' => $total,
                'pagina_actual' => $pagina,
                'total_paginas' => (int) ceil($total / $limite),
                'limite' => $limite,
            ],
            'kpis' => $this->reclamacionServicio->obtenerKpis($filtros),
        ]);
    }

    /**
     * Expediente 360° y Línea de Tiempo Append-Only (GET /reclamaciones/{id}).
     */
    public function detalle(string|int|array $id): Respuesta
    {
        $id = (int) (is_array($id) ? ($id['id'] ?? 0) : $id);
        $usuario = $this->obtenerUsuarioAutenticado();
        if ($usuario === null) {
            return Respuesta::redirigir('/login');
        }

        $usuarioId = (int) $usuario->obtenerId();
        if (!$this->autorizacionServicio->puede($usuarioId, 'reclamaciones.ver')) {
            return new Respuesta($this->vista->renderizar('errores/403', [
                'titulo' => '403 — Acceso Denegado',
                'mensaje' => 'No cuenta con permisos para ver este expediente.',
                'usuario' => $usuario,
            ]), 403);
        }

        $reclamacion = $this->reclamacionServicio->obtenerPorId($id, true);
        if (!$reclamacion) {
            return new Respuesta($this->vista->renderizar('errores/404', [
                'titulo' => '404 — Expediente no encontrado',
                'mensaje' => "El expediente con identificador [{$id}] no existe.",
                'usuario' => $usuario,
            ]), 404);
        }

        $semaforo = $this->calculadorPlazos->calcularSemaforo($reclamacion);

        $permisos = [
            'puede_gestionar' => $this->autorizacionServicio->puede($usuarioId, 'reclamaciones.gestionar'),
            'puede_actuar' => $this->autorizacionServicio->puede($usuarioId, 'reclamaciones.actuar'),
            'puede_responder' => $this->autorizacionServicio->puede($usuarioId, 'reclamaciones.responder'),
            'puede_anular' => $this->autorizacionServicio->puede($usuarioId, 'reclamaciones.anular'),
        ];

        $html = $this->vista->renderizar('reclamaciones/detalle', [
            'titulo' => "Expediente {$reclamacion->obtenerCodigoHoja()} — Camargo PMS",
            'categoriaActiva' => 'reservas',
            'subcategoriaActiva' => 'reclamaciones_directorio',
            'migasPan' => [
                ['etiqueta' => 'Panel', 'url' => url_ruta('/'), 'activo' => false],
                ['etiqueta' => 'Libro de Reclamaciones', 'url' => url_ruta('/reclamaciones'), 'activo' => false],
                ['etiqueta' => $reclamacion->obtenerCodigoHoja(), 'url' => url_ruta('/reclamaciones/' . $id), 'activo' => true],
            ],
            'usuario' => $usuario,
            'reclamacion' => $reclamacion,
            'semaforo' => $semaforo,
            'permisos' => $permisos,
            'csrf_token' => $this->csrfServicio->generarToken(),
        ]);

        return new Respuesta($html);
    }

    /**
     * Registro asistido de reclamación presencial desde consola interna (POST /reclamaciones/crear-asistido).
     */
    public function crearAsistido(): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if ($usuario === null) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'No autenticado.'], 401);
        }

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'reclamaciones.crear')) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'Acceso denegado: requiere permiso reclamaciones.crear.'], 403);
        }

        $datos = $this->leerEntrada();
        if ($errorCsrf = $this->validarCsrf($datos)) {
            return $errorCsrf;
        }

        try {
            $actorId = $this->resolverActorId();
            $reclamacion = $this->reclamacionServicio->interponerReclamacion($datos, $actorId, 'PRESENCIAL');

            return Respuesta::json([
                'exito' => true,
                'mensaje' => 'Reclamación asistida registrada con éxito.',
                'id' => $reclamacion->obtenerId(),
                'codigo_hoja' => $reclamacion->obtenerCodigoHoja(),
                'codigo_interno' => $reclamacion->obtenerCodigoInterno(),
                'url_detalle' => url_ruta('/reclamaciones/' . $reclamacion->obtenerId()),
            ], 201);
        } catch (ValidacionReclamacionExcepcion $eVal) {
            return Respuesta::json(['exito' => false, 'mensaje' => $eVal->getMessage(), 'errores' => $eVal->obtenerErrores()], 422);
        } catch (Throwable $e) {
            error_log("Error en crearAsistido: " . $e->getMessage());
            return Respuesta::json(['exito' => false, 'mensaje' => 'Error interno al registrar la reclamación asistida.'], 500);
        }
    }

    /**
     * Agrega una nota interna append-only (POST /reclamaciones/{id}/notas).
     */
    public function agregarNota(string|int|array $id): Respuesta
    {
        $id = (int) (is_array($id) ? ($id['id'] ?? 0) : $id);
        $usuario = $this->obtenerUsuarioAutenticado();
        if ($usuario === null) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'No autenticado.'], 401);
        }

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'reclamaciones.actuar')) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'Acceso denegado.'], 403);
        }

        $datos = $this->leerEntrada();
        if ($errorCsrf = $this->validarCsrf($datos)) {
            return $errorCsrf;
        }

        try {
            $actorId = $this->resolverActorId();
            $actuacion = $this->reclamacionServicio->agregarNotaInterna($id, (string) ($datos['descripcion'] ?? ''), $actorId);

            return Respuesta::json([
                'exito' => true,
                'mensaje' => 'Nota agregada con éxito.',
                'actuacion' => $actuacion->aArreglo(),
            ]);
        } catch (ValidacionReclamacionExcepcion $eVal) {
            return Respuesta::json(['exito' => false, 'mensaje' => $eVal->getMessage(), 'errores' => $eVal->obtenerErrores()], 422);
        } catch (Throwable $e) {
            error_log("Error en agregarNota: " . $e->getMessage());
            return Respuesta::json(['exito' => false, 'mensaje' => 'Error al agregar la nota interna.'], 500);
        }
    }

    /**
     * Formula un ofrecimiento de solución que suspende el cómputo de plazos (POST /reclamaciones/{id}/ofrecimiento).
     */
    public function formularOfrecimiento(string|int|array $id): Respuesta
    {
        $id = (int) (is_array($id) ? ($id['id'] ?? 0) : $id);
        $usuario = $this->obtenerUsuarioAutenticado();
        if ($usuario === null) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'No autenticado.'], 401);
        }

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'reclamaciones.responder')) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'Acceso denegado: requiere permiso reclamaciones.responder.'], 403);
        }

        $datos = $this->leerEntrada();
        if ($errorCsrf = $this->validarCsrf($datos)) {
            return $errorCsrf;
        }

        try {
            $actorId = $this->resolverActorId();
            $propuesta = (string) ($datos['propuesta'] ?? $datos['descripcion'] ?? '');
            $reclamacion = $this->reclamacionServicio->formularOfrecimiento(
                $id,
                $propuesta,
                (string) ($datos['medio_notificacion'] ?? 'CORREO_ELECTRONICO'),
                (string) ($datos['destinatario'] ?? $datos['referencia_notificacion'] ?? ''),
                $actorId
            );

            return Respuesta::json([
                'exito' => true,
                'mensaje' => 'Ofrecimiento formulado. El cómputo de 15 días hábiles ha quedado suspendido por hasta 5 días hábiles.',
                'reclamacion' => $reclamacion->aArreglo(),
            ]);
        } catch (TransicionReclamacionInvalidaExcepcion $eTrans) {
            return Respuesta::json(['exito' => false, 'mensaje' => $eTrans->getMessage()], 409);
        } catch (ValidacionReclamacionExcepcion $eVal) {
            return Respuesta::json(['exito' => false, 'mensaje' => $eVal->getMessage(), 'errores' => $eVal->obtenerErrores()], 422);
        } catch (Throwable $e) {
            error_log("Error en formularOfrecimiento: " . $e->getMessage());
            return Respuesta::json(['exito' => false, 'mensaje' => 'Error al formular ofrecimiento.'], 500);
        }
    }

    /**
     * Registra la respuesta del consumidor frente al ofrecimiento (POST /reclamaciones/{id}/ofrecimiento/respuesta).
     */
    public function responderOfrecimiento(string|int|array $id): Respuesta
    {
        $id = (int) (is_array($id) ? ($id['id'] ?? 0) : $id);
        $usuario = $this->obtenerUsuarioAutenticado();
        if ($usuario === null) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'No autenticado.'], 401);
        }

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'reclamaciones.responder')) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'Acceso denegado.'], 403);
        }

        $datos = $this->leerEntrada();
        if ($errorCsrf = $this->validarCsrf($datos)) {
            return $errorCsrf;
        }

        try {
            $actorId = $this->resolverActorId();
            $aceptado = !empty($datos['aceptado']);
            $sustento = (string) ($datos['sustento'] ?? $datos['motivo'] ?? '');

            $reclamacion = $this->reclamacionServicio->responderOfrecimiento($id, $aceptado, $sustento, $actorId);

            return Respuesta::json([
                'exito' => true,
                'mensaje' => $aceptado ? 'Ofrecimiento aceptado formalmente. Expediente concluido por acuerdo.' : 'Ofrecimiento rechazado. Se reanudó el cómputo del plazo legal.',
                'reclamacion' => $reclamacion->aArreglo(),
            ]);
        } catch (TransicionReclamacionInvalidaExcepcion $eTrans) {
            return Respuesta::json(['exito' => false, 'mensaje' => $eTrans->getMessage()], 409);
        } catch (Throwable $e) {
            error_log("Error en responderOfrecimiento: " . $e->getMessage());
            return Respuesta::json(['exito' => false, 'mensaje' => 'Error al procesar la respuesta al ofrecimiento.'], 500);
        }
    }

    /**
     * Registra la expiración del plazo de 5 días de ofrecimiento (POST /reclamaciones/{id}/ofrecimiento/expirar).
     */
    public function expirarOfrecimiento(string|int|array $id): Respuesta
    {
        $id = (int) (is_array($id) ? ($id['id'] ?? 0) : $id);
        $usuario = $this->obtenerUsuarioAutenticado();
        if ($usuario === null) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'No autenticado.'], 401);
        }

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'reclamaciones.responder')) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'Acceso denegado.'], 403);
        }

        $datos = $this->leerEntrada();
        if ($errorCsrf = $this->validarCsrf($datos)) {
            return $errorCsrf;
        }

        try {
            $actorId = $this->resolverActorId();
            $reclamacion = $this->reclamacionServicio->expirarOfrecimiento($id, $actorId);

            return Respuesta::json([
                'exito' => true,
                'mensaje' => 'Plazo de ofrecimiento expirado. El conteo legal de días hábiles ha sido reanudado.',
                'reclamacion' => $reclamacion->aArreglo(),
            ]);
        } catch (TransicionReclamacionInvalidaExcepcion $eTrans) {
            return Respuesta::json(['exito' => false, 'mensaje' => $eTrans->getMessage()], 409);
        } catch (Throwable $e) {
            error_log("Error en expirarOfrecimiento: " . $e->getMessage());
            return Respuesta::json(['exito' => false, 'mensaje' => 'Error al expirar ofrecimiento.'], 500);
        }
    }

    /**
     * Emite la respuesta formal oficial al consumidor concluyendo en ATENDIDO (POST /reclamaciones/{id}/respuesta).
     */
    public function emitirRespuesta(string|int|array $id): Respuesta
    {
        $id = (int) (is_array($id) ? ($id['id'] ?? 0) : $id);
        $usuario = $this->obtenerUsuarioAutenticado();
        if ($usuario === null) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'No autenticado.'], 401);
        }

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'reclamaciones.responder')) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'Acceso denegado: requiere permiso reclamaciones.responder.'], 403);
        }

        $datos = $this->leerEntrada();
        if ($errorCsrf = $this->validarCsrf($datos)) {
            return $errorCsrf;
        }

        try {
            $actorId = $this->resolverActorId();
            $contenido = (string) ($datos['contenido_respuesta'] ?? $datos['descripcion'] ?? $datos['contenido'] ?? '');
            $reclamacion = $this->reclamacionServicio->emitirRespuestaFormal(
                $id,
                $contenido,
                (string) ($datos['medio_notificacion'] ?? 'CORREO_ELECTRONICO'),
                (string) ($datos['destinatario'] ?? $datos['referencia_notificacion'] ?? ''),
                $actorId
            );

            return Respuesta::json([
                'exito' => true,
                'mensaje' => 'Respuesta formal registrada y notificada con éxito. El expediente queda en estado ATENDIDO.',
                'reclamacion' => $reclamacion->aArreglo(),
            ]);
        } catch (TransicionReclamacionInvalidaExcepcion $eTrans) {
            return Respuesta::json(['exito' => false, 'mensaje' => $eTrans->getMessage()], 409);
        } catch (ValidacionReclamacionExcepcion $eVal) {
            return Respuesta::json(['exito' => false, 'mensaje' => $eVal->getMessage(), 'errores' => $eVal->obtenerErrores()], 422);
        } catch (Throwable $e) {
            error_log("Error en emitirRespuesta: " . $e->getMessage());
            return Respuesta::json(['exito' => false, 'mensaje' => 'Error al emitir respuesta formal: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Anulación supervisada de expediente (POST /reclamaciones/{id}/anular).
     */
    public function anular(string|int|array $id): Respuesta
    {
        $id = (int) (is_array($id) ? ($id['id'] ?? 0) : $id);
        $usuario = $this->obtenerUsuarioAutenticado();
        if ($usuario === null) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'No autenticado.'], 401);
        }

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'reclamaciones.anular')) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'Acceso denegado: requiere permiso reclamaciones.anular.'], 403);
        }

        $datos = $this->leerEntrada();
        if ($errorCsrf = $this->validarCsrf($datos)) {
            return $errorCsrf;
        }

        try {
            $actorId = $this->resolverActorId();
            $reclamacion = $this->reclamacionServicio->anularReclamacion(
                $id,
                (string) ($datos['motivo'] ?? ''),
                $actorId
            );

            return Respuesta::json([
                'exito' => true,
                'mensaje' => 'Expediente anulado de forma supervisada.',
                'reclamacion' => $reclamacion->aArreglo(),
            ]);
        } catch (TransicionReclamacionInvalidaExcepcion $eTrans) {
            return Respuesta::json(['exito' => false, 'mensaje' => $eTrans->getMessage()], 409);
        } catch (ValidacionReclamacionExcepcion $eVal) {
            return Respuesta::json(['exito' => false, 'mensaje' => $eVal->getMessage(), 'errores' => $eVal->obtenerErrores()], 422);
        } catch (Throwable $e) {
            error_log("Error en anular: " . $e->getMessage());
            return Respuesta::json(['exito' => false, 'mensaje' => 'Error al anular expediente: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Descarga del PDF oficial de la Hoja de Reclamación (GET /reclamaciones/{id}/pdf).
     */
    public function descargarPdf(string|int|array $id): Respuesta
    {
        $id = (int) (is_array($id) ? ($id['id'] ?? 0) : $id);
        $usuario = $this->obtenerUsuarioAutenticado();
        if ($usuario === null) {
            return Respuesta::redirigir('/login');
        }

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'reclamaciones.ver')) {
            return new Respuesta("Acceso denegado.", 403);
        }

        try {
            $pdf = $this->reclamacionServicio->obtenerPdfParaDescarga($id);

            return new Respuesta(
                $pdf['binario_pdf'],
                200,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="' . $pdf['nombre_archivo'] . '"',
                    'Content-Length' => (string) $pdf['tamano_bytes'],
                    'Cache-Control' => 'private, max-age=0, must-revalidate',
                    'Pragma' => 'public',
                ]
            );
        } catch (Throwable $e) {
            error_log("Error al descargar PDF: " . $e->getMessage());
            return new Respuesta("No fue posible generar el PDF.", 500);
        }
    }

    /**
     * Regeneración forzada del PDF en storage (POST /reclamaciones/{id}/regenerar-pdf).
     */
    public function regenerarPdf(string|int|array $id): Respuesta
    {
        $id = (int) (is_array($id) ? ($id['id'] ?? 0) : $id);
        $usuario = $this->obtenerUsuarioAutenticado();
        if ($usuario === null) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'No autenticado.'], 401);
        }

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'reclamaciones.gestionar')) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'Acceso denegado.'], 403);
        }

        $datos = $this->leerEntrada();
        if ($errorCsrf = $this->validarCsrf($datos)) {
            return $errorCsrf;
        }

        try {
            $actorId = $this->resolverActorId();
            $res = $this->reclamacionServicio->regenerarPdf($id, $actorId);

            return Respuesta::json([
                'exito' => true,
                'mensaje' => 'PDF de la Hoja de Reclamación regenerado exitosamente.',
                'tamano_bytes' => $res['tamano_bytes'],
                'hash_sha256' => $res['hash_sha256'],
            ]);
        } catch (Throwable $e) {
            error_log("Error en regenerarPdf: " . $e->getMessage());
            return Respuesta::json(['exito' => false, 'mensaje' => 'Error al regenerar PDF.'], 500);
        }
    }
}
