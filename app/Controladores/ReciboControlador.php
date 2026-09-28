<?php

declare(strict_types=1);

namespace CamargoPMS\Controladores;

use CamargoPMS\Excepciones\ConflictoReciboExcepcion;
use CamargoPMS\Excepciones\DocumentoCorruptoExcepcion;
use CamargoPMS\Excepciones\ReciboNoEncontradoExcepcion;
use CamargoPMS\Excepciones\ValidacionReciboExcepcion;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Nucleo\Vista;
use CamargoPMS\Repositorios\ActorAuditoriaRepositorio;
use CamargoPMS\Repositorios\AplicacionPagoRepositorio;
use CamargoPMS\Repositorios\CargoCuentaRepositorio;
use CamargoPMS\Repositorios\CuentaFolioRepositorio;
use CamargoPMS\Repositorios\DocumentoRepositorio;
use CamargoPMS\Repositorios\PagoCuentaRepositorio;
use CamargoPMS\Repositorios\ReciboRepositorio;
use CamargoPMS\Servicios\AuditoriaServicio;
use CamargoPMS\Servicios\AutorizacionServicio;
use CamargoPMS\Servicios\CsrfServicio;
use CamargoPMS\Servicios\DocumentoServicio;
use CamargoPMS\Servicios\ReciboServicio;
use CamargoPMS\Servicios\SesionServicio;
use PDO;
use Throwable;

/**
 * Controlador HTTP para la gestión de Recibos de Cobranza (RECIBOS-1 / D-082).
 */
class ReciboControlador
{
    private Vista $vista;
    private ReciboServicio $reciboServicio;
    private ReciboRepositorio $reciboRepo;
    private SesionServicio $sesionServicio;
    private AutorizacionServicio $autorizacionServicio;
    private AuditoriaServicio $auditoriaServicio;
    private CsrfServicio $csrfServicio;
    private PDO $pdo;

    public function __construct(
        ?Vista $vista = null,
        ?ReciboServicio $reciboServicio = null,
        ?ReciboRepositorio $reciboRepo = null,
        ?SesionServicio $sesionServicio = null,
        ?AutorizacionServicio $autorizacionServicio = null,
        ?AuditoriaServicio $auditoriaServicio = null,
        ?CsrfServicio $csrfServicio = null,
        ?PDO $pdo = null
    ) {
        $this->vista = $vista ?? new Vista();
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->reciboRepo = $reciboRepo ?? new ReciboRepositorio($this->pdo);

        if ($reciboServicio !== null) {
            $this->reciboServicio = $reciboServicio;
        } else {
            $pagoRepo = new PagoCuentaRepositorio($this->pdo);
            $aplRepo = new AplicacionPagoRepositorio($this->pdo);
            $cargoRepo = new CargoCuentaRepositorio($this->pdo);
            $folioRepo = new CuentaFolioRepositorio($this->pdo);
            $docRepo = new DocumentoRepositorio($this->pdo);
            $docServicio = new DocumentoServicio($docRepo);
            $this->reciboServicio = new ReciboServicio(
                $this->reciboRepo,
                $pagoRepo,
                $aplRepo,
                $cargoRepo,
                $folioRepo,
                $docServicio,
                $docRepo,
                $this->pdo
            );
        }

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

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'recibos.ver')) {
            return new Respuesta(
                $this->vista->renderizar('errores/error', [
                    'codigo' => 403,
                    'mensaje' => 'No cuenta con permisos para acceder al módulo de recibos.',
                    'usuario' => $usuario,
                ]),
                403
            );
        }

        $kpis = $this->reciboServicio->obtenerEstadisticas();

        $contenido = $this->vista->renderizar('recibos/index', [
            'usuario' => $usuario,
            'permisos' => $this->autorizacionServicio->obtenerPermisosEfectivos((int) $usuario->obtenerId()),
            'csrf_token' => $this->csrfServicio->generarToken(),
            'kpis' => $kpis,
        ]);

        return new Respuesta($contenido);
    }

    // =========================================================================
    // API: CATÁLOGOS AUXILIARES
    // =========================================================================

    public function apiCatalogos(): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'recibos.ver')) {
            return Respuesta::json(['exito' => false, 'error' => 'No autorizado'], 403);
        }

        // 1. Pagos confirmados elegibles para emitir recibo (sin recibo activo ya emitido)
        $stmtPagos = $this->pdo->query(
            'SELECT pc.id, pc.codigo, pc.cuenta_folio_id, pc.monto_total, pc.monto_aplicado, pc.moneda_codigo,
                    pc.referencia_operacion, pc.creado_en,
                    cf.codigo AS folio_codigo,
                    TRIM(CONCAT(p.nombres, " ", p.apellido_paterno, " ", COALESCE(p.apellido_materno, ""))) AS titular_nombre,
                    COALESCE(pd.numero_documento, "S/D") AS titular_documento,
                    mp.nombre AS metodo_pago_nombre
             FROM pagos_cuenta pc
             JOIN cuentas_folios cf ON cf.id = pc.cuenta_folio_id
             JOIN personas p ON p.id = cf.persona_titular_id
             LEFT JOIN personas_documentos pd ON pd.persona_id = p.id
             LEFT JOIN metodos_pago mp ON mp.id = pc.metodo_pago_id
             WHERE pc.estado = "CONFIRMADO"
               AND NOT EXISTS (
                   SELECT 1 FROM recibos r WHERE r.pago_id = pc.id AND r.estado = "EMITIDO"
               )
             ORDER BY pc.id DESC
             LIMIT 100'
        );
        $pagosElegibles = $stmtPagos->fetchAll(PDO::FETCH_ASSOC);

        // 2. Cuentas folios activas
        $stmtFolios = $this->pdo->query(
            'SELECT cf.id, cf.codigo, cf.moneda_codigo,
                    TRIM(CONCAT(p.nombres, " ", p.apellido_paterno, " ", COALESCE(p.apellido_materno, ""))) AS titular_nombre
             FROM cuentas_folios cf
             JOIN personas p ON p.id = cf.persona_titular_id
             WHERE cf.estado IN ("ABIERTA", "CERRADA")
             ORDER BY cf.id DESC
             LIMIT 100'
        );
        $folios = $stmtFolios->fetchAll(PDO::FETCH_ASSOC);

        return Respuesta::json([
            'exito' => true,
            'datos' => [
                'pagos_elegibles' => $pagosElegibles,
                'cuentas_folios' => $folios,
            ],
        ]);
    }

    // =========================================================================
    // API: LISTADO Y CONSULTA
    // =========================================================================

    public function apiListar(): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'recibos.ver')) {
            return Respuesta::json(['exito' => false, 'error' => 'No autorizado'], 403);
        }

        $folioId = isset($_GET['cuenta_folio_id']) && is_numeric($_GET['cuenta_folio_id']) ? (int) $_GET['cuenta_folio_id'] : null;
        $estado = isset($_GET['estado']) && $_GET['estado'] !== '' ? trim((string) $_GET['estado']) : null;

        $recibos = $this->reciboServicio->listarRecibos($folioId, $estado);
        $datos = array_map(static fn($r) => $r->aArreglo(), $recibos);

        return Respuesta::json([
            'exito' => true,
            'datos' => $datos,
        ]);
    }

    public function apiObtener(mixed $id): Respuesta
    {
        $id = (int) $id;
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'recibos.ver')) {
            return Respuesta::json(['exito' => false, 'error' => 'No autorizado'], 403);
        }

        try {
            $recibo = $this->reciboServicio->obtenerRecibo($id);
            return Respuesta::json([
                'exito' => true,
                'datos' => $recibo->aArreglo(),
            ]);
        } catch (ReciboNoEncontradoExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 404);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // API: EMISIÓN DE RECIBO
    // =========================================================================

    public function apiEmitir(): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'recibos.emitir')) {
            return Respuesta::json(['exito' => false, 'error' => 'No autorizado'], 403);
        }

        $csrfError = $this->validarCsrf();
        if ($csrfError !== null) {
            return $csrfError;
        }

        $cuerpo = $this->obtenerCuerpoJson();
        $pagoId = isset($cuerpo['pago_id']) ? (int) $cuerpo['pago_id'] : 0;
        $notas = isset($cuerpo['notas']) ? trim((string) $cuerpo['notas']) : null;
        $conceptoGeneral = isset($cuerpo['concepto_general']) ? trim((string) $cuerpo['concepto_general']) : null;

        if ($pagoId <= 0) {
            return Respuesta::json(['exito' => false, 'error' => 'Debe seleccionar un pago válido.'], 422);
        }

        $actorId = $this->obtenerActorIdActual();

        try {
            $recibo = $this->reciboServicio->emitirReciboParaPago($pagoId, $actorId, $notas, $conceptoGeneral);

            $this->auditoriaServicio->registrar(
                'EMITIR',
                'recibos',
                'recibo',
                (int) $recibo->obtenerId(),
                "Emisión de recibo [{$recibo->obtenerCodigo()}] para pago [{$pagoId}]",
                null,
                $recibo->aArreglo(),
                null,
                $actorId
            );

            return Respuesta::json([
                'exito' => true,
                'mensaje' => "Recibo [{$recibo->obtenerCodigo()}] emitido exitosamente con constancia PDF.",
                'datos' => $recibo->aArreglo(),
            ], 201);
        } catch (ValidacionReciboExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage(), 'errores' => $e->obtenerErrores()], 422);
        } catch (ConflictoReciboExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 409);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // API: ANULACIÓN FORMAL AUDITADA (D-082 #3, #4)
    // =========================================================================

    public function apiAnular(mixed $id): Respuesta
    {
        $id = (int) $id;
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'recibos.anular')) {
            return Respuesta::json(['exito' => false, 'error' => 'No autorizado'], 403);
        }

        $csrfError = $this->validarCsrf();
        if ($csrfError !== null) {
            return $csrfError;
        }

        $cuerpo = $this->obtenerCuerpoJson();
        $motivo = isset($cuerpo['motivo']) ? trim((string) $cuerpo['motivo']) : '';

        if ($motivo === '') {
            return Respuesta::json(['exito' => false, 'error' => 'El motivo de anulación es obligatorio.'], 422);
        }

        $actorId = $this->obtenerActorIdActual();

        try {
            $reciboAntes = $this->reciboServicio->obtenerRecibo($id);
            $this->reciboServicio->anularRecibo($id, $motivo, $actorId);
            $reciboDespues = $this->reciboServicio->obtenerRecibo($id);

            $this->auditoriaServicio->registrar(
                'ANULAR',
                'recibos',
                'recibo',
                $id,
                "Anulación formal de recibo [{$reciboAntes->obtenerCodigo()}]: {$motivo}",
                $reciboAntes->aArreglo(),
                $reciboDespues->aArreglo(),
                null,
                $actorId
            );

            return Respuesta::json([
                'exito' => true,
                'mensaje' => "Recibo [{$reciboAntes->obtenerCodigo()}] anulado administrativamente. El PDF original se conserva intacto.",
                'datos' => $reciboDespues->aArreglo(),
            ]);
        } catch (ReciboNoEncontradoExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 404);
        } catch (ValidacionReciboExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 422);
        } catch (ConflictoReciboExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 409);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // DESCARGA Y VERIFICACIÓN CRIPTOGRÁFICA DE PDF (D-082 #13)
    // =========================================================================

    public function descargarPdf(mixed $id): Respuesta
    {
        $id = (int) $id;
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'recibos.descargar')) {
            return Respuesta::json(['exito' => false, 'error' => 'No autorizado para descargar este documento'], 403);
        }

        try {
            $resultado = $this->reciboServicio->descargarPdfRecibo($id);
            $disposicion = (isset($_GET['descargar']) && $_GET['descargar'] === '1') ? 'attachment' : 'inline';

            return new Respuesta(
                $resultado['binario'],
                200,
                [
                    'Content-Type' => $resultado['mime'],
                    'Content-Disposition' => "{$disposicion}; filename=\"{$resultado['nombre_archivo']}\"",
                    'Content-Length' => (string) strlen($resultado['binario']),
                    'X-Document-SHA256' => $resultado['hash'],
                    'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
                ]
            );
        } catch (ReciboNoEncontradoExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 404);
        } catch (DocumentoCorruptoExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => 'Violación de integridad: ' . $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function apiVerificarHash(mixed $id): Respuesta
    {
        $id = (int) $id;
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'recibos.ver')) {
            return Respuesta::json(['exito' => false, 'error' => 'No autorizado'], 403);
        }

        try {
            $verificacion = $this->reciboServicio->verificarHashRecibo($id);
            return Respuesta::json([
                'exito' => true,
                'datos' => $verificacion,
            ]);
        } catch (ReciboNoEncontradoExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 404);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 500);
        }
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

    private function validarCsrf(): ?Respuesta
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? null;
        if (!$token || !$this->csrfServicio->validarToken((string) $token)) {
            return Respuesta::json(['exito' => false, 'error' => 'Token CSRF inválido o expirado.'], 403);
        }
        return null;
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
