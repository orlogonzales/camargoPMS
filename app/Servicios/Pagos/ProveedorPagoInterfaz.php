<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios\Pagos;

use CamargoPMS\Servicios\Pagos\DTOs\ConsultaOrdenResultado;
use CamargoPMS\Servicios\Pagos\DTOs\ConsultaTransaccionResultado;
use CamargoPMS\Servicios\Pagos\DTOs\IntencionPagoResultado;
use CamargoPMS\Servicios\Pagos\DTOs\IntencionPagoSolicitud;
use CamargoPMS\Servicios\Pagos\DTOs\ReembolsoResultado;
use CamargoPMS\Servicios\Pagos\DTOs\ReembolsoSolicitud;
use CamargoPMS\Servicios\Pagos\DTOs\WebhookNotificacionResultado;

/**
 * Contrato canónico soberano para adaptadores y drivers de pasarelas de pago externas.
 * Desacopla la lógica de negocio de Camargo PMS de los SDKs o formatos específicos de Culqi, Izipay o PayPal.
 */
interface ProveedorPagoInterfaz
{
    /**
     * Nombre o identificador único del proveedor ('CULQI', 'IZIPAY', 'PAYPAL').
     */
    public function obtenerNombre(): string;

    /**
     * Crea una intención u orden de pago en la pasarela externa.
     */
    public function crearIntencionPago(IntencionPagoSolicitud $solicitud): IntencionPagoResultado;

    /**
     * Valida la autenticidad criptográfica del webhook entrante y parsea su contenido a un DTO estándar.
     *
     * @param array<string, string> $headers Cabeceras HTTP de la petición recibida.
     * @param string $cuerpoBruto Contenido en texto plano del cuerpo HTTP (raw payload).
     */
    public function verificarYParsearWebhook(array $headers, string $cuerpoBruto): WebhookNotificacionResultado;

    /**
     * Consulta el estado de una orden directamente en la API de la pasarela.
     */
    public function consultarOrden(string $proveedorOrdenId): ConsultaOrdenResultado;

    /**
     * Consulta el estado de una transacción o cargo específico en la pasarela.
     */
    public function consultarTransaccion(string $proveedorTransaccionId): ConsultaTransaccionResultado;

    /**
     * Procesa un reembolso total o parcial ante la pasarela externa.
     */
    public function procesarReembolso(ReembolsoSolicitud $solicitud): ReembolsoResultado;
}
