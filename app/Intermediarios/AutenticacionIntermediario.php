<?php

declare(strict_types=1);

namespace CamargoPMS\Intermediarios;

use CamargoPMS\Nucleo\Ayudante;
use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Servicios\SesionServicio;

/**
 * Intermediario (Middleware) que protege rutas exigiendo una identidad humana autenticada válida.
 *
 * "¿Existe una identidad humana autenticada válida?"
 * Si no está autenticado, redirige al formulario de inicio de sesión (/login).
 */
class AutenticacionIntermediario
{
    private SesionServicio $sesionServicio;

    public function __construct(?SesionServicio $sesionServicio = null)
    {
        $this->sesionServicio = $sesionServicio ?? new SesionServicio();
    }

    /**
     * Evalúa la petición entrante.
     *
     * @param string $rutaSolicitada
     * @return Respuesta|null Retorna Respuesta de redirección si no está autenticado, o null si puede continuar.
     */
    public function manejar(string $rutaSolicitada = '/'): ?Respuesta
    {
        $usuario = $this->sesionServicio->validarSesionActual();

        if ($usuario === null) {
            // Protección contra Open Redirect: sanitizar ruta de retorno para aceptar solo rutas internas
            $rutaRetorno = $this->sanitizarRutaRetorno($rutaSolicitada);
            $urlLogin = Ayudante::ruta('/login');

            if ($rutaRetorno !== '/' && $rutaRetorno !== '') {
                $urlLogin .= '?return=' . urlencode($rutaRetorno);
            }

            return Respuesta::redirigir($urlLogin);
        }

        return null;
    }

    /**
     * Sanitiza una URL de retorno asegurando que sea un path relativo interno estricto.
     *
     * @param string $ruta
     * @return string
     */
    public static function sanitizarRutaRetorno(string $ruta): string
    {
        $ruta = trim($ruta);
        // Si comienza con //, contiene esquemas (http:, https:) o caracteres de control, descartar
        if ($ruta === '' || str_starts_with($ruta, '//') || preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*:#', $ruta)) {
            return '/';
        }

        return '/' . ltrim($ruta, '/');
    }
}
