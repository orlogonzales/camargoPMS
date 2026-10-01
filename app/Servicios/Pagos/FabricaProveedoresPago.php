<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios\Pagos;

use CamargoPMS\Servicios\Pagos\Adaptadores\CulqiProveedor;
use InvalidArgumentException;

/**
 * Fábrica y Registro Soberano de Proveedores de Pago de Camargo PMS.
 *
 * Resuelve y entrega instancias de ProveedorPagoInterfaz de forma desacoplada,
 * permitiendo configurar credenciales desde variables de entorno o inyectar
 * instancias parametrizadas (útil para sandboxes y pruebas automáticas).
 */
class FabricaProveedoresPago
{
    /** @var array<string, ProveedorPagoInterfaz> */
    private static array $instancias = [];

    /**
     * Registra explícitamente una instancia de proveedor (inyección / mock).
     */
    public static function registrarProveedor(ProveedorPagoInterfaz $proveedor): void
    {
        $nombre = strtoupper(trim($proveedor->obtenerNombre()));
        self::$instancias[$nombre] = $proveedor;
    }

    /**
     * Limpia las instancias registradas (útil entre pruebas).
     */
    public static function reiniciar(): void
    {
        self::$instancias = [];
    }

    /**
     * Verifica si un proveedor está soportado arquitectónicamente.
     */
    public static function proveedorSoportado(string $nombre): bool
    {
        $proveedor = strtoupper(trim($nombre));
        return in_array($proveedor, ['CULQI', 'IZIPAY', 'PAYPAL'], true);
    }

    /**
     * Resuelve y retorna el driver del proveedor solicitado.
     */
    public static function obtenerProveedor(string $nombre): ProveedorPagoInterfaz
    {
        $proveedor = strtoupper(trim($nombre));

        if (isset(self::$instancias[$proveedor])) {
            return self::$instancias[$proveedor];
        }

        if (!self::proveedorSoportado($proveedor)) {
            throw new InvalidArgumentException("Proveedor de pagos no soportado: $nombre");
        }

        if ($proveedor === 'CULQI') {
            $secretKey = (string) (getenv('CULQI_SECRET_KEY') ?: ($_ENV['CULQI_SECRET_KEY'] ?? 'sk_test_mock_culqi_default'));
            $publicKey = (string) (getenv('CULQI_PUBLIC_KEY') ?: ($_ENV['CULQI_PUBLIC_KEY'] ?? 'pk_test_mock_culqi_default'));
            $webhookSecret = (string) (getenv('CULQI_WEBHOOK_SECRET') ?: ($_ENV['CULQI_WEBHOOK_SECRET'] ?? ''));

            $instancia = new CulqiProveedor(
                llaveSecreta: $secretKey,
                llavePublica: $publicKey,
                webhookSecret: $webhookSecret !== '' ? $webhookSecret : null
            );

            self::$instancias['CULQI'] = $instancia;
            return $instancia;
        }

        throw new InvalidArgumentException("El proveedor $proveedor está modelado en arquitectura pero su adaptador se encuentra planificado para fases posteriores (PAGOS-2).");
    }
}
