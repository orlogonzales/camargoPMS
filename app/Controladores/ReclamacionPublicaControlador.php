<?php

declare(strict_types=1);

namespace CamargoPMS\Controladores;

use CamargoPMS\Excepciones\ValidacionReclamacionExcepcion;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Nucleo\Vista;
use CamargoPMS\Repositorios\PropiedadRepositorio;
use CamargoPMS\Repositorios\TipoDocumentoRepositorio;
use CamargoPMS\Servicios\CsrfServicio;
use CamargoPMS\Servicios\ReclamacionServicio;
use CamargoPMS\Servicios\SesionServicio;
use PDO;
use Throwable;

/**
 * Controlador de acceso público para el Libro de Reclamaciones Virtual (RECLAMACIONES-1 / Ley 32495).
 */
class ReclamacionPublicaControlador
{
    private Vista $vista;
    private PDO $pdo;
    private ReclamacionServicio $reclamacionServicio;
    private PropiedadRepositorio $propiedadRepo;
    private TipoDocumentoRepositorio $tipoDocRepo;
    private CsrfServicio $csrfServicio;

    public function __construct(
        ?Vista $vista = null,
        ?PDO $pdo = null,
        ?ReclamacionServicio $reclamacionServicio = null,
        ?PropiedadRepositorio $propiedadRepo = null,
        ?TipoDocumentoRepositorio $tipoDocRepo = null,
        ?CsrfServicio $csrfServicio = null
    ) {
        $this->vista = $vista ?? new Vista();
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->reclamacionServicio = $reclamacionServicio ?? new ReclamacionServicio($this->pdo);
        $this->propiedadRepo = $propiedadRepo ?? new PropiedadRepositorio($this->pdo);
        $this->tipoDocRepo = $tipoDocRepo ?? new TipoDocumentoRepositorio($this->pdo);
        $this->csrfServicio = $csrfServicio ?? new CsrfServicio();
    }

    /**
     * Muestra el formulario público del Libro de Reclamaciones (GET /libro-reclamaciones).
     */
    public function mostrarFormulario(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $propiedades = $this->propiedadRepo->listar(null, 'ACTIVO');
        $tiposDoc = $this->tipoDocRepo->listarActivos();

        $html = $this->vista->renderizar('reclamaciones/publico/formulario', [
            'titulo' => 'Libro de Reclamaciones Virtual — Camargo Hostelería',
            'propiedades' => $propiedades,
            'tiposDoc' => $tiposDoc,
            'csrf_token' => $this->csrfServicio->generarToken(),
            'honeypot_field' => 'empresa_sitio_web_hp',
        ], null);

        return new Respuesta($html);
    }

    /**
     * Procesa la interposición pública del reclamo o queja (POST /libro-reclamaciones).
     */
    public function procesarRegistro(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $datos = $this->leerEntrada();

        // 1. Capa Anti-Abuso: Honeypot para bots
        if (!empty($datos['empresa_sitio_web_hp'])) {
            // Rechazo silencioso para bots
            return Respuesta::json(['exito' => false, 'mensaje' => 'Solicitud rechazada.'], 422);
        }

        // 2. Validación de Token CSRF
        $token = $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? $datos['csrf_token']
            ?? $datos['_csrf_token']
            ?? null;

        if (!$token || !$this->csrfServicio->validarToken((string) $token)) {
            if ($this->esPeticionJson()) {
                return Respuesta::json(['exito' => false, 'mensaje' => 'Token CSRF inválido o expirado. Recargue la página.'], 403);
            }
            return new Respuesta("Token de seguridad CSRF inválido. Por favor vuelva a intentar.", 403);
        }

        try {
            $reclamacion = $this->reclamacionServicio->interponerReclamacion($datos, null, 'VIRTUAL');

            if ($this->esPeticionJson()) {
                return Respuesta::json([
                    'exito' => true,
                    'mensaje' => 'Reclamación registrada exitosamente.',
                    'codigo_hoja' => $reclamacion->obtenerCodigoHoja(),
                    'codigo_interno' => $reclamacion->obtenerCodigoInterno(),
                    'url_confirmacion' => url_ruta('/libro-reclamaciones/confirmacion?codigo=' . urlencode($reclamacion->obtenerCodigoInterno())),
                    'url_descarga_pdf' => url_ruta('/libro-reclamaciones/descargar-pdf?codigo=' . urlencode($reclamacion->obtenerCodigoInterno())),
                ], 201);
            }

            return Respuesta::redirigir('/libro-reclamaciones/confirmacion?codigo=' . urlencode($reclamacion->obtenerCodigoInterno()));
        } catch (ValidacionReclamacionExcepcion $eVal) {
            if ($this->esPeticionJson()) {
                return Respuesta::json([
                    'exito' => false,
                    'mensaje' => $eVal->getMessage(),
                    'errores' => $eVal->obtenerErrores(),
                ], 422);
            }
            return new Respuesta("Error de validación: " . $eVal->getMessage(), 422);
        } catch (Throwable $e) {
            error_log("Error al procesar reclamación pública: " . $e->getMessage());
            if ($this->esPeticionJson()) {
                return Respuesta::json([
                    'exito' => false,
                    'mensaje' => 'Ocurrió un error inesperado al registrar su reclamación. Intente nuevamente o comuníquese directamente con recepción.',
                ], 500);
            }
            return new Respuesta("Error interno al procesar su solicitud.", 500);
        }
    }

    /**
     * Muestra la pantalla oficial de confirmación y constancia (GET /libro-reclamaciones/confirmacion).
     */
    public function mostrarConfirmacion(): Respuesta
    {
        $codigo = trim((string) ($_GET['codigo'] ?? ''));
        if ($codigo === '') {
            return Respuesta::redirigir('/libro-reclamaciones');
        }

        $reclamacion = $this->reclamacionServicio->obtenerPorCodigoInterno($codigo, true);
        if (!$reclamacion) {
            return new Respuesta("No se encontró la constancia de reclamación especificada.", 404);
        }

        $html = $this->vista->renderizar('reclamaciones/publico/confirmacion', [
            'titulo' => 'Constancia de Registro — Libro de Reclamaciones',
            'reclamacion' => $reclamacion,
            'url_descarga_pdf' => url_ruta('/libro-reclamaciones/descargar-pdf?codigo=' . urlencode($reclamacion->obtenerCodigoInterno())),
        ], null);

        return new Respuesta($html);
    }

    /**
     * Descarga pública de la copia de la Hoja de Reclamación en PDF (GET /libro-reclamaciones/descargar-pdf).
     */
    public function descargarPdfPublico(): Respuesta
    {
        $codigo = trim((string) ($_GET['codigo'] ?? ''));
        if ($codigo === '') {
            return new Respuesta("Código de reclamación no proporcionado.", 400);
        }

        $reclamacion = $this->reclamacionServicio->obtenerPorCodigoInterno($codigo, true);
        if (!$reclamacion) {
            return new Respuesta("Expediente no encontrado.", 404);
        }

        try {
            $pdf = $this->reclamacionServicio->obtenerPdfParaDescarga((int) $reclamacion->obtenerId());

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
            error_log("Error al emitir descarga pública de PDF: " . $e->getMessage());
            return new Respuesta("No fue posible generar el comprobante PDF en este momento.", 500);
        }
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

    private function esPeticionJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $contentType = $_SERVER['HTTP_CONTENT_TYPE'] ?? $_SERVER['CONTENT_TYPE'] ?? '';

        return str_contains($accept, 'application/json') || str_contains($contentType, 'application/json');
    }
}
