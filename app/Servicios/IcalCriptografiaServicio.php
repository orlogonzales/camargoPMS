<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

use CamargoPMS\Nucleo\Configuracion;
use InvalidArgumentException;
use RuntimeException;

/**
 * Servicio de Criptografía Segura para Integraciones iCalendar.
 *
 * Implementa cifrado autenticado AES-256-GCM para proteger URLs externas
 * privadas y almacenamiento seguro híbrido de tokens de exportación.
 */
class IcalCriptografiaServicio
{
    private const METODO_CIFRADO = 'aes-256-gcm';
    private const LONGITUD_IV = 12;      // 96 bits recomendado para GCM
    private const LONGITUD_TAG = 16;     // 128 bits de autenticación
    private const LONGITUD_CLAVE = 32;   // 256 bits

    private string $claveBinaria;

    /**
     * Constructor. Inicializa y valida la clave criptográfica maestra.
     *
     * @param string|null $claveBase64 Clave codificada en Base64. Si es null, se obtiene de Configuracion.
     * @throws InvalidArgumentException Si la clave está ausente o no tiene exactamente 32 bytes al decodificarse.
     */
    public function __construct(?string $claveBase64 = null)
    {
        $clave = $claveBase64 ?? (string) Configuracion::obtener('ICAL_ENCRYPTION_KEY', '');
        $clave = trim($clave);

        if ($clave === '') {
            throw new InvalidArgumentException(
                'La variable de entorno ICAL_ENCRYPTION_KEY no está configurada.'
            );
        }

        $binario = base64_decode($clave, true);
        if ($binario === false || strlen($binario) !== self::LONGITUD_CLAVE) {
            throw new InvalidArgumentException(
                'ICAL_ENCRYPTION_KEY debe ser una cadena Base64 válida que decodifique exactamente 32 bytes (256 bits).'
            );
        }

        $this->claveBinaria = $binario;
    }

    /**
     * Cifra un texto plano utilizando AES-256-GCM con un IV aleatorio único.
     *
     * Empaqueta el resultado en formato: base64(IV[12] . TAG[16] . CIPHERTEXT)
     *
     * @param string $textoPlano Cadena a cifrar (ej. URL privada de feed).
     * @return string Payload seguro codificado en Base64.
     * @throws RuntimeException Si la generación criptográfica u OpenSSL fallan.
     */
    public function cifrar(string $textoPlano): string
    {
        $iv = random_bytes(self::LONGITUD_IV);
        $tag = '';

        $cifrado = openssl_encrypt(
            $textoPlano,
            self::METODO_CIFRADO,
            $this->claveBinaria,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::LONGITUD_TAG
        );

        if ($cifrado === false) {
            throw new RuntimeException('Fallo al ejecutar cifrado AES-256-GCM.');
        }

        return base64_encode($iv . $tag . $cifrado);
    }

    /**
     * Descifra un payload cifrado con AES-256-GCM previa verificación del tag de autenticación.
     *
     * Principio FAIL-CLOSED: Si el tag no coincide, los datos fueron alterados o están truncados,
     * retorna null o lanza excepción sin intentar usar datos corruptos.
     *
     * @param string $payloadBase64 Payload generado previamente por cifrar().
     * @return string|null Texto plano recuperado, o null si falla la autenticación o integridad.
     */
    public function descifrar(string $payloadBase64): ?string
    {
        $datos = base64_decode($payloadBase64, true);
        if ($datos === false) {
            return null;
        }

        $longitudMinima = self::LONGITUD_IV + self::LONGITUD_TAG;
        if (strlen($datos) < $longitudMinima) {
            return null;
        }

        $iv = substr($datos, 0, self::LONGITUD_IV);
        $tag = substr($datos, self::LONGITUD_IV, self::LONGITUD_TAG);
        $cifrado = substr($datos, $longitudMinima);

        $descifrado = openssl_decrypt(
            $cifrado,
            self::METODO_CIFRADO,
            $this->claveBinaria,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($descifrado === false) {
            // Falla de autenticación o alteración de datos (FAIL CLOSED)
            return null;
        }

        return $descifrado;
    }

    /**
     * Genera un nuevo token de exportación criptográficamente seguro y su empaquetado para BD.
     *
     * Justificación de arquitectura (Hash SHA-256 + Cifrado AES-256-GCM):
     * 1. token_exportacion_hash (SHA-256): Permite búsqueda indexada O(1) de alta velocidad
     *    en el endpoint público GET /ical/exportar/{token} sin descifrar masivamente toda la tabla.
     * 2. token_exportacion_cifrado (AES-256-GCM): Permite al administrador volver a visualizar
     *    o copiar el enlace de exportación desde el panel Alina sin persistir nunca el token en plano.
     * 3. token_prefijo: Facilita identificación visual segura en listados (ej. 'cal_7a8b...').
     *
     * @return array{token: string, hash: string, cifrado: string, prefijo: string}
     */
    public function generarTokenExportacion(): array
    {
        $token = 'cal_' . bin2hex(random_bytes(30)); // 4 + 60 = 64 caracteres URL-safe
        $hash = hash('sha256', $token);
        $cifrado = $this->cifrar($token);
        $prefijo = substr($token, 0, 12);

        return [
            'token' => $token,
            'hash' => $hash,
            'cifrado' => $cifrado,
            'prefijo' => $prefijo,
        ];
    }

    /**
     * Calcula el hash SHA-256 de un token entrante para búsqueda indexada O(1).
     *
     * @param string $token
     * @return string
     */
    public function hashearToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
