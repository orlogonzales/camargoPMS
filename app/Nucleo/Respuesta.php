<?php

declare(strict_types=1);

namespace CamargoPMS\Nucleo;

/**
 * Representa una respuesta HTTP preparada por el controlador o enrutador.
 */
final class Respuesta
{
    private string $contenido;
    private int $codigoEstado;
    /** @var array<string, string> */
    private array $cabeceras;

    /**
     * @param string $contenido Contenido textual o HTML de la respuesta.
     * @param int $codigoEstado Código de estado HTTP (por defecto 200).
     * @param array<string, string> $cabeceras Cabeceras HTTP adicionales.
     */
    public function __construct(string $contenido = '', int $codigoEstado = 200, array $cabeceras = [])
    {
        $this->contenido = $contenido;
        $this->codigoEstado = $codigoEstado;
        $this->cabeceras = array_merge([
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-XSS-Protection' => '1; mode=block'
        ], $cabeceras);
    }

    /**
     * Modifica el código de estado HTTP.
     *
     * @param int $codigo
     * @return self
     */
    public function conCodigo(int $codigo): self
    {
        $this->codigoEstado = $codigo;
        return $this;
    }

    /**
     * Añade o sobreescribe una cabecera HTTP.
     *
     * @param string $nombre
     * @param string $valor
     * @return self
     */
    public function conCabecera(string $nombre, string $valor): self
    {
        $this->cabeceras[$nombre] = $valor;
        return $this;
    }

    /**
     * Obtiene el código de estado HTTP actual.
     *
     * @return int
     */
    public function obtenerCodigo(): int
    {
        return $this->codigoEstado;
    }

    /**
     * Alias semántico para obtenerCodigo().
     */
    public function obtenerCodigoEstado(): int
    {
        return $this->codigoEstado;
    }

    /**
     * Obtiene el contenido o cuerpo de la respuesta.
     */
    public function obtenerContenido(): string
    {
        return $this->contenido;
    }

    /**
     * Alias semántico para obtenerContenido().
     */
    public function obtenerCuerpo(): string
    {
        return $this->contenido;
    }

    /**
     * Obtiene la lista de cabeceras HTTP configuradas.
     *
     * @return array<string, string>
     */
    public function obtenerCabeceras(): array
    {
        return $this->cabeceras;
    }

    /**
     * Crea una respuesta JSON estructurada.
     *
     * @param mixed $datos Datos serializables a JSON.
     * @param int $codigo Código de estado HTTP (por defecto 200).
     * @param array<string, string> $cabeceras Cabeceras HTTP adicionales.
     * @return self
     */
    public static function json(mixed $datos, int $codigo = 200, array $cabeceras = []): self
    {
        $json = json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return new self($json !== false ? $json : '{}', $codigo, array_merge([
            'Content-Type' => 'application/json; charset=UTF-8',
        ], $cabeceras));
    }

    /**
     * Crea una respuesta de redirección HTTP inmediata.
     *
     * @param string $url URL de destino
     * @param int $codigo Código de estado HTTP (por defecto 302)
     * @return self
     */
    public static function redirigir(string $url, int $codigo = 302): self
    {
        return new self('', $codigo, ['Location' => $url]);
    }

    /**
     * Emite los encabezados y el cuerpo de la respuesta HTTP al cliente.
     *
     * @return void
     */
    public function enviar(): void
    {
        if (!headers_sent()) {
            http_response_code($this->codigoEstado);
            foreach ($this->cabeceras as $nombre => $valor) {
                header("{$nombre}: {$valor}");
            }
        }

        echo $this->contenido;
    }
}
