<?php

declare(strict_types=1);

namespace CamargoPMS\Controladores;

use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Servicios\ExportacionIcalServicio;
use Throwable;

/**
 * Controlador Público para Exportación de Feeds iCalendar por Conexión.
 *
 * Expone el endpoint HTTP público: GET /ical/exportar/{token}
 * No requiere sesión humana porque el token criptográfico opaco actúa como credencial técnica.
 */
class IcalExportarControlador
{
    private ExportacionIcalServicio $exportacionServicio;

    public function __construct(?ExportacionIcalServicio $exportacionServicio = null)
    {
        $this->exportacionServicio = $exportacionServicio ?? new ExportacionIcalServicio();
    }

    /**
     * Endpoint: GET /ical/exportar/{token}
     *
     * @param string $token Token de exportación hexadecimal de 64 caracteres.
     * @return Respuesta
     */
    public function exportar(string $token = ''): Respuesta
    {
        $tokenLimpio = trim($token);
        if ($tokenLimpio === '') {
            return new Respuesta("Token de exportación no provisto.\n", 400, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        }

        try {
            $ipCliente = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : null;
            $contenidoIcs = $this->exportacionServicio->exportarFeed($tokenLimpio, $ipCliente);

            if ($contenidoIcs === null) {
                return new Respuesta("Calendario iCal no encontrado, inactivo o token revocado.\n", 404, [
                    'Content-Type' => 'text/plain; charset=UTF-8',
                ]);
            }

            return new Respuesta($contenidoIcs, 200, [
                'Content-Type' => 'text/calendar; charset=utf-8',
                'Content-Disposition' => 'inline; filename="calendario.ics"',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);

        } catch (Throwable $e) {
            return new Respuesta("Error interno al generar feed de calendario.\n", 500, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        }
    }
}
