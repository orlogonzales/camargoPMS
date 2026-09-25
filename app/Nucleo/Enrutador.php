<?php

declare(strict_types=1);

namespace CamargoPMS\Nucleo;

/**
 * Enrutador mínimo reversible para la fase UI-0 de Camargo PMS.
 *
 * Resuelve rutas HTTP y delega en controladores sin incorporar paquetes externos,
 * manteniendo abierta la decisión P-002 para la fase de infraestructura.
 */
final class Enrutador
{
    /** @var array<string, array<string, array|callable>> */
    private array $rutas = [];

    /** @var callable|array|null */
    private $manejador404 = null;

    /**
     * Registra una ruta para el método HTTP GET.
     *
     * @param string $ruta Ruta relativa normalizada (ej. "/").
     * @param array|callable $manejador Definición del manejador [Controlador::class, 'metodo'].
     * @return void
     */
    public function get(string $ruta, array|callable $manejador): void
    {
        $this->agregarRuta('GET', $ruta, $manejador);
    }

    /**
     * Registra una ruta genérica indicando el método HTTP.
     *
     * @param string $metodo Método HTTP (GET, POST, etc.).
     * @param string $ruta Ruta asociada.
     * @param array|callable $manejador Manejador ejecutable.
     * @return void
     */
    public function agregarRuta(string $metodo, string $ruta, array|callable $manejador): void
    {
        $metodoNormalizado = strtoupper($metodo);
        $rutaNormalizada = $this->normalizarRuta($ruta);
        $this->rutas[$metodoNormalizado][$rutaNormalizada] = $manejador;
    }

    /**
     * Define un manejador personalizado para rutas no encontradas (404).
     *
     * @param array|callable $manejador
     * @return void
     */
    public function definir404(array|callable $manejador): void
    {
        $this->manejador404 = $manejador;
    }

    /**
     * Despacha la petición HTTP entrante hacia el controlador correspondiente.
     *
     * @param string $metodo Método HTTP recibido ($_SERVER['REQUEST_METHOD']).
     * @param string $uri URI completa recibida ($_SERVER['REQUEST_URI']).
     * @return Respuesta Respuesta HTTP preparada para emisión.
     */
    public function despachar(string $metodo, string $uri): Respuesta
    {
        $metodoNormalizado = strtoupper($metodo);
        $rutaSolicitada = $this->extraerRutaLimpia($uri);

        $manejador = $this->rutas[$metodoNormalizado][$rutaSolicitada] ?? null;

        if ($manejador !== null) {
            return $this->ejecutarManejador($manejador);
        }

        // Manejo controlado de ruta no encontrada (404)
        if ($this->manejador404 !== null) {
            $respuesta404 = $this->ejecutarManejador($this->manejador404);
            return $respuesta404->conCodigo(404);
        }

        // Fallback predeterminado si no se configuró manejador 404 explícito
        $vista = new Vista();
        $html404 = $vista->renderizar('errores/404', [
            'titulo' => 'Página no encontrada — Camargo PMS',
            'rutaSolicitada' => $rutaSolicitada
        ]);

        return new Respuesta($html404, 404);
    }

    /**
     * Ejecuta el callable o [Controlador, metodo] y convierte su retorno en objeto Respuesta.
     *
     * @param array|callable $manejador
     * @return Respuesta
     */
    private function ejecutarManejador(array|callable $manejador): Respuesta
    {
        if (is_array($manejador) && count($manejador) === 2 && is_string($manejador[0])) {
            [$clase, $metodo] = $manejador;
            $instancia = new $clase();
            $resultado = $instancia->$metodo();
        } else {
            $resultado = call_user_func($manejador);
        }

        if ($resultado instanceof Respuesta) {
            return $resultado;
        }

        return new Respuesta((string) $resultado, 200);
    }

    /**
     * Normaliza una ruta asegurando barra inicial y sin barra final redundante.
     *
     * @param string $ruta
     * @return string
     */
    private function normalizarRuta(string $ruta): string
    {
        $ruta = trim($ruta);
        if ($ruta === '' || $ruta === '/') {
            return '/';
        }

        return '/' . trim($ruta, '/');
    }

    /**
     * Extrae y normaliza el path relativo respecto a la URL base configurada.
     *
     * @param string $uri
     * @return string
     */
    private function extraerRutaLimpia(string $uri): string
    {
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';
        $urlBase = Ayudante::obtenerUrlBase();

        if ($urlBase !== '' && str_starts_with($path, $urlBase)) {
            $path = substr($path, strlen($urlBase));
        }

        return $this->normalizarRuta($path ?: '/');
    }
}
