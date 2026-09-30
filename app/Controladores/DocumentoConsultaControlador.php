<?php

declare(strict_types=1);

namespace CamargoPMS\Controladores;

use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Servicios\ConsultaDocumentoServicio;
use CamargoPMS\Servicios\SesionServicio;
use Throwable;

/**
 * Controlador para la consulta unificada de identidad y documentos (APISPERU-1B).
 *
 * Expone el endpoint interno autenticado GET /api/documentos/consultar
 * para coordinar la consulta Local-First y fallback a APIsPERU.
 */
class DocumentoConsultaControlador
{
    private ConsultaDocumentoServicio $consultaServicio;
    private SesionServicio $sesionServicio;

    public function __construct(
        ?ConsultaDocumentoServicio $consultaServicio = null,
        ?SesionServicio $sesionServicio = null
    ) {
        $this->consultaServicio = $consultaServicio ?? new ConsultaDocumentoServicio();
        $this->sesionServicio = $sesionServicio ?? new SesionServicio();
    }

    /**
     * Endpoint: GET /api/documentos/consultar
     *
     * Parámetros GET requeridos:
     * - tipo: string ('DNI', 'RUC', 'CE', 'PASAPORTE')
     * - numero: string (número de documento)
     *
     * @return Respuesta
     */
    public function consultar(): Respuesta
    {
        // 1. Verificación defensiva de identidad humana autenticada
        $usuario = $this->sesionServicio->validarSesionActual();
        if ($usuario === null) {
            return Respuesta::json([
                'success' => false,
                'origen' => 'ERROR',
                'encontrado' => false,
                'mensaje' => 'Sesión expirada o no autenticada.',
                'datos' => null,
            ], 401);
        }

        // 2. Extracción y saneamiento de parámetros
        $tipo = isset($_GET['tipo']) ? trim((string) $_GET['tipo']) : '';
        $numero = isset($_GET['numero']) ? trim((string) $_GET['numero']) : '';

        if ($tipo === '' || $numero === '') {
            return Respuesta::json([
                'success' => false,
                'origen' => 'ERROR',
                'encontrado' => false,
                'mensaje' => 'Los parámetros "tipo" y "numero" son obligatorios.',
                'datos' => null,
            ], 422);
        }

        try {
            $resultado = $this->consultaServicio->consultar($tipo, $numero);

            // Si hay un error de validación estructural (ej. regex no coincide)
            if (!$resultado['success'] && ($resultado['origen'] ?? '') === 'ERROR') {
                return Respuesta::json($resultado, 422);
            }

            return Respuesta::json($resultado, 200);
        } catch (Throwable $e) {
            return Respuesta::json([
                'success' => false,
                'origen' => 'ERROR',
                'encontrado' => false,
                'mensaje' => 'Error al procesar la consulta del documento.',
                'datos' => null,
            ], 500);
        }
    }
}
