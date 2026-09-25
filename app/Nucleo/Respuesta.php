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
