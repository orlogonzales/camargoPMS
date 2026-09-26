<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

use InvalidArgumentException;
use JsonException;

/**
 * Tipos de datos funcionales soportados por el núcleo de configuración del sistema.
 */
enum TipoConfiguracion: string
{
    case TEXTO = 'TEXTO';
    case ENTERO = 'ENTERO';
    case DECIMAL = 'DECIMAL';
    case BOOLEANO = 'BOOLEANO';
    case FECHA = 'FECHA';
    case HORA = 'HORA';
    case JSON = 'JSON';

    /**
     * Valida sintáctica y semánticamente si un valor es compatible con el tipo.
     */
    public function validar(mixed $valor): bool
    {
        if ($valor === null) {
            return true;
        }

        return match ($this) {
            self::TEXTO => is_string($valor) || is_scalar($valor),
            self::ENTERO => is_int($valor) || (is_string($valor) && preg_match('/^-?\d+$/', trim($valor)) === 1),
            self::DECIMAL => is_numeric($valor) || (is_string($valor) && preg_match('/^-?\d+(\.\d+)?$/', trim($valor)) === 1),
            self::BOOLEANO => is_bool($valor) || in_array(strtolower(trim((string) $valor)), ['1', '0', 'true', 'false', 'si', 'no', 'on', 'off'], true),
            self::FECHA => is_string($valor) && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($valor)) === 1 && (function(string $f): bool {
                [$y, $m, $d] = explode('-', $f);
                return checkdate((int) $m, (int) $d, (int) $y);
            })(trim($valor)),
            self::HORA => is_string($valor) && preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', trim($valor)) === 1,
            self::JSON => (function(mixed $v): bool {
                if (is_array($v)) return true;
                if (!is_string($v) || trim($v) === '') return false;
                try {
                    json_decode($v, true, 512, JSON_THROW_ON_ERROR);
                    return true;
                } catch (JsonException) {
                    return false;
                }
            })($valor),
        };
    }

    /**
     * Transforma una cadena persistida (o valor primitivo) al tipo PHP nativo correspondiente.
     */
    public function castear(mixed $valorRaw): mixed
    {
        if ($valorRaw === null) {
            return null;
        }

        return match ($this) {
            self::TEXTO => (string) $valorRaw,
            self::ENTERO => (int) $valorRaw,
            self::DECIMAL => (float) $valorRaw,
            self::BOOLEANO => match (is_bool($valorRaw) ? ($valorRaw ? '1' : '0') : strtolower(trim((string) $valorRaw))) {
                '1', 'true', 'si', 'on' => true,
                default => false,
            },
            self::FECHA => trim((string) $valorRaw),
            self::HORA => trim((string) $valorRaw),
            self::JSON => (function(mixed $raw): mixed {
                if (is_array($raw)) return $raw;
                try {
                    return json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException) {
                    return null;
                }
            })($valorRaw),
        };
    }

    /**
     * Serializa un valor tipado para su almacenamiento canónico en la base de datos (string/null).
     */
    public function serializar(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        return match ($this) {
            self::TEXTO => (string) $valor,
            self::ENTERO => (string) ((int) $valor),
            self::DECIMAL => (string) ((float) $valor),
            self::BOOLEANO => match (is_bool($valor) ? ($valor ? '1' : '0') : strtolower(trim((string) $valor))) {
                '1', 'true', 'si', 'on' => '1',
                default => '0',
            },
            self::FECHA => trim((string) $valor),
            self::HORA => trim((string) $valor),
            self::JSON => is_string($valor) ? trim($valor) : json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        };
    }
}
