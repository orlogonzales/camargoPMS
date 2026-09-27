<?php

declare(strict_types=1);

namespace CamargoPMS\Controladores;

use CamargoPMS\Excepciones\ContenidoDocumentalInseguroExcepcion;
use CamargoPMS\Excepciones\DocumentoCorruptoExcepcion;
use CamargoPMS\Excepciones\PlantillaNoEncontradaExcepcion;
use CamargoPMS\Excepciones\ValidacionExcepcion;
use CamargoPMS\Excepciones\VariableDocumentalDesconocidaExcepcion;
use CamargoPMS\Excepciones\VariableDocumentalFaltanteExcepcion;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Nucleo\Vista;
use CamargoPMS\Repositorios\ActorAuditoriaRepositorio;
use CamargoPMS\Repositorios\ArrendamientoRepositorio;
use CamargoPMS\Repositorios\DocumentoRepositorio;
use CamargoPMS\Servicios\AuditoriaServicio;
use CamargoPMS\Servicios\AutorizacionServicio;
use CamargoPMS\Servicios\CsrfServicio;
use CamargoPMS\Servicios\DocumentoServicio;
use CamargoPMS\Servicios\SesionServicio;
use PDO;
use Throwable;

/**
 * Controlador HTTP para el motor documental, plantillas versionadas y generación PDF (DOCUMENTOS-1 / D-079).
 */
class DocumentoControlador
{
    private Vista $vista;
    private DocumentoServicio $documentoServicio;
    private DocumentoRepositorio $docRepo;
    private ArrendamientoRepositorio $arrendamientoRepo;
    private SesionServicio $sesionServicio;
    private AutorizacionServicio $autorizacionServicio;
    private AuditoriaServicio $auditoriaServicio;
    private CsrfServicio $csrfServicio;
    private PDO $pdo;

    public function __construct(
        ?Vista $vista = null,
        ?DocumentoServicio $documentoServicio = null,
        ?DocumentoRepositorio $docRepo = null,
        ?ArrendamientoRepositorio $arrendamientoRepo = null,
        ?SesionServicio $sesionServicio = null,
        ?AutorizacionServicio $autorizacionServicio = null,
        ?AuditoriaServicio $auditoriaServicio = null,
        ?CsrfServicio $csrfServicio = null,
        ?PDO $pdo = null
    ) {
        $this->vista = $vista ?? new Vista();
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->docRepo = $docRepo ?? new DocumentoRepositorio($this->pdo);
        $this->arrendamientoRepo = $arrendamientoRepo ?? new ArrendamientoRepositorio($this->pdo);
        $this->documentoServicio = $documentoServicio ?? new DocumentoServicio($this->docRepo, $this->arrendamientoRepo);
        $this->sesionServicio = $sesionServicio ?? new SesionServicio();
        $this->autorizacionServicio = $autorizacionServicio ?? new AutorizacionServicio($this->pdo);
        $this->auditoriaServicio = $auditoriaServicio ?? new AuditoriaServicio($this->pdo);
        $this->csrfServicio = $csrfServicio ?? new CsrfServicio();
    }

    // =========================================================================
    // VISTA PRINCIPAL (ALINA D-075)
    // =========================================================================

    public function index(): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario) {
            return Respuesta::redirigir('/login');
        }

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'documentos.ver')) {
            return new Respuesta(
                $this->vista->renderizar('errores/error', [
                    'codigo' => 403,
                    'mensaje' => 'No cuenta con permisos para acceder al módulo de documentos.',
                    'usuario' => $usuario,
                ]),
                403
            );
        }

        // Métricas para los KPIs
        $totalPlantillas = count($this->docRepo->listarPlantillas());
        $documentosEmitidos = $this->docRepo->listarDocumentosEmitidos();
        $totalEmitidos = count($documentosEmitidos);

        $stmtInc = $this->pdo->query('SELECT COUNT(*) FROM documento_incidencias WHERE resuelto = 0');
        $incidenciasAbiertas = (int) $stmtInc->fetchColumn();

        $stmtVers = $this->pdo->query('SELECT COUNT(*) FROM documento_plantilla_versiones');
        $totalVersiones = (int) $stmtVers->fetchColumn();

        $contenido = $this->vista->renderizar('documentos/index', [
            'usuario' => $usuario,
            'permisos' => $this->autorizacionServicio->obtenerPermisosEfectivos((int) $usuario->obtenerId()),
            'csrf_token' => $this->csrfServicio->generarToken(),
            'kpis' => [
                'total_plantillas' => $totalPlantillas,
                'total_versiones' => $totalVersiones,
                'total_emitidos' => $totalEmitidos,
                'incidencias_abiertas' => $incidenciasAbiertas,
            ],
            'plantillas' => $this->docRepo->listarPlantillas(),
            'documentos' => array_slice($documentosEmitidos, 0, 50),
        ]);

        return new Respuesta($contenido, 200);
    }

    // =========================================================================
    // ENDPOINTS DE API (JSON)
    // =========================================================================

    public function apiListarDocumentos(): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'documentos.ver')) {
            return Respuesta::json(['error' => 'No autorizado'], 403);
        }

        $docs = $this->docRepo->listarDocumentosEmitidos();
        $data = array_map(fn($d) => $d->toArray(), $docs);

        return Respuesta::json(['datos' => $data], 200);
    }

    public function apiListarPlantillas(): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'documentos.ver')) {
            return Respuesta::json(['error' => 'No autorizado'], 403);
        }

        $plantillas = $this->docRepo->listarPlantillas();
        $data = [];
        foreach ($plantillas as $p) {
            $arr = $p->toArray();
            $vActiva = $this->docRepo->obtenerVersionActivaPorPlantillaId((int) $p->obtenerId());
            $arr['version_activa'] = $vActiva ? $vActiva->toArray() : null;
            $data[] = $arr;
        }

        return Respuesta::json(['datos' => $data], 200);
    }

    public function apiListarVersiones(mixed $plantillaId): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'documentos.ver')) {
            return Respuesta::json(['error' => 'No autorizado'], 403);
        }

        $versiones = $this->docRepo->listarVersionesPorPlantillaId((int) $plantillaId);
        $data = array_map(fn($v) => $v->toArray(), $versiones);

        return Respuesta::json(['datos' => $data], 200);
    }

    public function apiCrearVersion(mixed $plantillaId): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'documentos.plantillas.gestionar')) {
            return Respuesta::json(['error' => 'No autorizado'], 403);
        }

        $body = $this->obtenerCuerpoJson();
        $titulo = trim($body['titulo_documento'] ?? '');
        $html = trim($body['cuerpo_html'] ?? '');
        $css = isset($body['estilos_css']) ? trim($body['estilos_css']) : null;
        $notas = isset($body['notas_version']) ? trim($body['notas_version']) : null;
        $activar = (bool) ($body['activar_inmediatamente'] ?? false);

        if ($titulo === '' || $html === '') {
            return Respuesta::json(['error' => 'El título y cuerpo HTML son obligatorios.'], 422);
        }

        try {
            $actorId = $this->obtenerActorIdActual();
            $version = $this->documentoServicio->crearVersionPlantilla(
                (int) $plantillaId,
                $titulo,
                $html,
                $css,
                $notas,
                $activar,
                $actorId
            );

            return Respuesta::json(['mensaje' => 'Versión creada exitosamente.', 'version' => $version->toArray()], 201);
        } catch (ContenidoDocumentalInseguroExcepcion | VariableDocumentalDesconocidaExcepcion $e) {
            return Respuesta::json(['error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['error' => $e->getMessage()], 400);
        }
    }

    public function apiActivarVersion(mixed $plantillaId, mixed $versionId): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'documentos.plantillas.gestionar')) {
            return Respuesta::json(['error' => 'No autorizado'], 403);
        }

        try {
            $this->documentoServicio->activarVersionPlantilla((int) $plantillaId, (int) $versionId);
            return Respuesta::json(['mensaje' => 'Versión activada exitosamente.'], 200);
        } catch (Throwable $e) {
            return Respuesta::json(['error' => $e->getMessage()], 400);
        }
    }

    public function apiCrearPlantilla(): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'documentos.plantillas.gestionar')) {
            return Respuesta::json(['error' => 'No autorizado'], 403);
        }

        $body = $this->obtenerCuerpoJson();
        $codigo = strtoupper(trim($body['codigo'] ?? ''));
        $nombre = trim($body['nombre'] ?? '');
        $origenTipo = strtoupper(trim($body['origen_tipo_permitido'] ?? 'ARRENDAMIENTO'));

        if ($codigo === '' || $nombre === '') {
            return Respuesta::json(['error' => 'El código y nombre de plantilla son requeridos.'], 422);
        }

        try {
            $plantilla = new \CamargoPMS\Modelos\DocumentoPlantilla(
                null,
                $codigo,
                $nombre,
                $body['descripcion'] ?? null,
                $origenTipo,
                $body['orientacion'] ?? 'portrait',
                $body['tamano_papel'] ?? 'A4',
                (bool) ($body['requiere_membrete'] ?? true),
                $body['archivo_membrete_fondo'] ?? 'membrete_a4_canonica_v1.png',
                (int) ($body['margen_superior_mm'] ?? 35),
                (int) ($body['margen_inferior_mm'] ?? 28),
                (int) ($body['margen_izquierdo_mm'] ?? 20),
                (int) ($body['margen_derecho_mm'] ?? 20),
                'ACTIVA'
            );

            $id = $this->docRepo->crearPlantilla($plantilla);
            $nueva = $this->docRepo->obtenerPlantillaPorId($id);

            return Respuesta::json([
                'mensaje' => 'Plantilla creada exitosamente.',
                'plantilla' => $nueva ? $nueva->toArray() : ['id' => $id],
            ], 201);
        } catch (Throwable $e) {
            return Respuesta::json(['error' => $e->getMessage()], 400);
        }
    }

    public function apiEmitirContratoArrendamiento(mixed $arrendamientoId): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'documentos.emitir')) {
            return Respuesta::json(['error' => 'No autorizado'], 403);
        }

        $body = $this->obtenerCuerpoJson();
        $esBorrador = (bool) ($body['es_borrador'] ?? false);
        $versionId = isset($body['plantilla_version_id']) ? (int) $body['plantilla_version_id'] : null;

        try {
            $actorId = $this->obtenerActorIdActual();
            $resultado = $this->documentoServicio->emitirContratoArrendamiento(
                (int) $arrendamientoId,
                $actorId,
                $versionId,
                $esBorrador
            );

            if ($esBorrador) {
                return new Respuesta(
                    $resultado['binario_pdf'],
                    200,
                    [
                        'Content-Type' => 'application/pdf',
                        'Content-Disposition' => "inline; filename=\"{$resultado['nombre_archivo']}\"",
                        'Content-Length' => (string) strlen($resultado['binario_pdf']),
                    ]
                );
            }

            return Respuesta::json([
                'mensaje' => 'Contrato emitido exitosamente.',
                'documento' => $resultado['documento']->toArray(),
                'descarga_url' => "/api/documentos/{$resultado['documento']->obtenerId()}/descargar",
            ], 201);
        } catch (VariableDocumentalDesconocidaExcepcion | VariableDocumentalFaltanteExcepcion | ContenidoDocumentalInseguroExcepcion $e) {
            return Respuesta::json(['error' => $e->getMessage()], 422);
        } catch (PlantillaNoEncontradaExcepcion $e) {
            return Respuesta::json(['error' => $e->getMessage()], 404);
        } catch (Throwable $e) {
            return Respuesta::json(['error' => $e->getMessage()], 500);
        }
    }

    public function apiDescargarDocumento(mixed $documentoId): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'documentos.descargar')) {
            return Respuesta::json(['error' => 'No autorizado'], 403);
        }

        try {
            $actorId = $this->obtenerActorIdActual();
            $res = $this->documentoServicio->descargarDocumento((int) $documentoId, $actorId);

            $inline = isset($_GET['inline']) && $_GET['inline'] === '1';
            $disposition = $inline ? 'inline' : 'attachment';

            return new Respuesta(
                $res['binario_pdf'],
                200,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => "{$disposition}; filename=\"{$res['nombre_archivo']}\"",
                    'Content-Length' => (string) strlen($res['binario_pdf']),
                ]
            );
        } catch (DocumentoCorruptoExcepcion $e) {
            return Respuesta::json([
                'error' => $e->getMessage(),
                'codigo_folio' => $e->obtenerCodigoFolio(),
                'tipo_incidencia' => $e->obtenerTipoIncidencia(),
                'requiere_regeneracion' => true,
            ], 500);
        } catch (PlantillaNoEncontradaExcepcion $e) {
            return Respuesta::json(['error' => $e->getMessage()], 404);
        } catch (Throwable $e) {
            return Respuesta::json(['error' => $e->getMessage()], 500);
        }
    }

    public function apiVerificarDocumento(mixed $documentoId): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'documentos.ver')) {
            return Respuesta::json(['error' => 'No autorizado'], 403);
        }

        try {
            $actorId = $this->obtenerActorIdActual();
            $res = $this->documentoServicio->descargarDocumento((int) $documentoId, $actorId);

            return Respuesta::json([
                'estado_integridad' => 'VALIDO',
                'hash_sha256' => $res['documento']->obtenerHashPdfSha256(),
                'tamano_bytes' => $res['documento']->obtenerTamanoBytes(),
            ], 200);
        } catch (DocumentoCorruptoExcepcion $e) {
            return Respuesta::json([
                'estado_integridad' => 'CORRUPTO',
                'error' => $e->getMessage(),
                'tipo_incidencia' => $e->obtenerTipoIncidencia(),
            ], 500);
        }
    }

    public function apiRegenerarDocumento(mixed $documentoId): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'documentos.regenerar')) {
            return Respuesta::json(['error' => 'No autorizado'], 403);
        }

        $body = $this->obtenerCuerpoJson();
        $motivo = trim($body['motivo'] ?? 'Regeneración autorizada por administración');

        try {
            $actorId = $this->obtenerActorIdActual();
            $doc = $this->documentoServicio->regenerarPdfDesdeSnapshot((int) $documentoId, $actorId, $motivo);

            return Respuesta::json([
                'mensaje' => 'Documento regenerado exitosamente desde su snapshot inmutable.',
                'documento' => $doc->toArray(),
            ], 200);
        } catch (Throwable $e) {
            return Respuesta::json(['error' => $e->getMessage()], 500);
        }
    }

    public function apiAnularDocumento(mixed $documentoId): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'documentos.anular')) {
            return Respuesta::json(['error' => 'No autorizado'], 403);
        }

        $body = $this->obtenerCuerpoJson();
        $motivo = trim($body['motivo'] ?? '');
        if ($motivo === '') {
            return Respuesta::json(['error' => 'El motivo de anulación es obligatorio.'], 422);
        }

        try {
            $actorId = $this->obtenerActorIdActual();
            $exito = $this->documentoServicio->anularDocumento((int) $documentoId, $actorId, $motivo);
            if (!$exito) {
                return Respuesta::json(['error' => 'No se pudo anular el documento (puede estar ya anulado o no existir).'], 400);
            }

            return Respuesta::json(['mensaje' => 'Documento anulado formalmente.'], 200);
        } catch (Throwable $e) {
            return Respuesta::json(['error' => $e->getMessage()], 500);
        }
    }

    public function apiListarIncidencias(): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'documentos.ver')) {
            return Respuesta::json(['error' => 'No autorizado'], 403);
        }

        $incidencias = $this->docRepo->listarIncidencias();
        $data = array_map(fn($i) => $i->toArray(), $incidencias);

        return Respuesta::json(['datos' => $data], 200);
    }

    // =========================================================================
    // HELPERS INTERNOS
    // =========================================================================

    private function obtenerUsuarioAutenticado(): ?\CamargoPMS\Modelos\Usuario
    {
        SesionServicio::iniciarSesionPhp();
        return $this->sesionServicio->validarSesionActual();
    }

    private function obtenerActorIdActual(): int
    {
        $usr = $this->obtenerUsuarioAutenticado();
        if ($usr) {
            $actorRepo = new ActorAuditoriaRepositorio($this->pdo);
            $actor = $actorRepo->buscarPorUsuarioId((int) $usr->obtenerId());
            if ($actor) {
                return (int) $actor->obtenerId();
            }
        }
        return 1;
    }

    private function obtenerCuerpoJson(): array
    {
        $crudo = file_get_contents('php://input');
        if (!$crudo) {
            return $_POST;
        }
        $datos = json_decode($crudo, true);
        return is_array($datos) ? array_merge($_POST, $datos) : $_POST;
    }
}
