<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

/**
 * Sanitizador central de seguridad del subsistema de auditoría.
 *
 * Garantiza de forma estricta y recursiva que ningún secreto técnico o dato
 * confidencial sea almacenado en el registro histórico inmutable de auditoría:
 * - Contraseñas y hashes de contraseñas.
 * - Tokens CSRF, tokens de sesión y tokens de recuperación.
 * - Cookies e identificadores de sesión PHP (PHPSESSID).
 * - Cabeceras Authorization (Bearer, Basic).
 * - Llaves de API (API keys), secretos y credenciales de pasarelas de pago.
 * - Contenido de variables o archivos de entorno (.env).
 */
class SanitizadorAuditoria
{
    /**
     * Vocabulario de nombres canónicos y subcadenas identificadoras de claves sensibles.
     */
    private const CLAVES_SENSIBLES = [
        'contrasena',
        'password',
        'clave',
        'contrasena_hash',
        'password_hash',
        'hash',
        'hash_contrasena',
        'csrf',
        '_csrf_token',
        'csrf_token',
        'token_csrf',
        'cookie',
        'cookies',
        'phpsessid',
        'session_id',
        'sesion_token',
        'token_sesion',
        'token_recuperacion',
        'token',
        'authorization',
        'auth_header',
        'bearer',
        'api_key',
        'apikey',
        'secret_key',
        'secret',
        'webhook_secret',
        'private_key',
        'env',
    ];

    /**
     * Sanitiza de manera recursiva un arreglo asociativo o lista de valores, eliminando
     * en su totalidad las entradas cuyas claves correspondan a datos sensibles.
     *
     * @param array<string|int, mixed>|null $datos
     * @return array<string|int, mixed>|null
     */
    public function sanitizar(?array $datos): ?array
    {
        if ($datos === null) {
            return null;
        }

        $resultado = [];
        foreach ($datos as $clave => $valor) {
            $nombreClave = (string) $clave;
            if ($this->esClaveSensible($nombreClave)) {
                // Elimina completamente la clave sensible del conjunto
                continue;
            }

            if (is_array($valor)) {
                $resultado[$clave] = $this->sanitizar($valor);
            } elseif (is_string($valor)) {
                $resultado[$clave] = $this->sanitizarCadena($valor);
            } else {
                $resultado[$clave] = $valor;
            }
        }

        return $resultado;
    }

    /**
     * Sanitiza una cadena de texto libre o descripción, redactando tokens y credenciales.
     *
     * @param string|null $texto
     * @return string|null
     */
    public function sanitizarTexto(?string $texto): ?string
    {
        if ($texto === null || $texto === '') {
            return $texto;
        }

        // Redactar Authorization: Bearer <token>
        $limpio = preg_replace('/bearer\s+[a-zA-Z0-9_\-\.]+/i', 'Bearer [REDACTADO]', $texto);

        // Redactar Basic <base64>
        $limpio = preg_replace('/basic\s+[a-zA-Z0-9+\/]+={0,2}/i', 'Basic [REDACTADO]', (string) $limpio);

        // Redactar patrones clave=valor para passwords/tokens
        $patron = '/(password|contrasena|clave|token|secret|hash|api_key)=([^\s&]+)/i';
        $limpio = preg_replace($patron, '$1=[REDACTADO]', (string) $limpio);

        return (string) $limpio;
    }

    /**
     * Determina si una clave dada coincide o contiene una denominación sensible.
     *
     * @param string $clave
     * @return bool
     */
    public function esClaveSensible(string $clave): bool
    {
        $normalizada = strtolower(str_replace(['-', '_', ' '], '', trim($clave)));

        foreach (self::CLAVES_SENSIBLES as $sensible) {
            $sensibleNormalizada = strtolower(str_replace(['-', '_', ' '], '', $sensible));
            if ($normalizada === $sensibleNormalizada || str_contains($normalizada, $sensibleNormalizada)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sanitiza cadenas que contienen estructuras JSON embebidas.
     *
     * @param string $cadena
     * @return string
     */
    private function sanitizarCadena(string $cadena): string
    {
        $recortada = trim($cadena);
        if ((str_starts_with($recortada, '{') && str_ends_with($recortada, '}')) ||
            (str_starts_with($recortada, '[') && str_ends_with($recortada, ']'))) {
            $decodificado = json_decode($recortada, true);
            if (is_array($decodificado)) {
                $sanitizado = $this->sanitizar($decodificado);
                return (string) json_encode($sanitizado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

        return $this->sanitizarTexto($cadena) ?? $cadena;
    }
}
