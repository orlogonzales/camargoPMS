<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

/**
 * Servicio soberano de protección antiabuso y throttling progresivo no impeditivo
 * para el canal público del Libro de Reclamaciones (RECLAMACIONES-1A / Ley 32495).
 *
 * Axiomas de diseño vinculantes:
 * 1. ANTIABUSO ≠ DENEGACIÓN DEL DERECHO A RECLAMAR: El sistema bajo ninguna circunstancia
 *    rechaza con HTTP 429 ni impide materialmente el ejercicio de interponer una queja o reclamo.
 * 2. IP ≠ IDENTIDAD: El mecanismo no castiga a redes compartidas (NAT, Wi-Fi del hotel,
 *    oficinas o redes móviles). El control se apoya en el contexto de sesión del visitante.
 * 3. RETARDO PROGRESIVO ACOTADO: En lugar de bloqueos, impone micro-retardos crecientes
 *    (0ms -> 250ms -> 500ms -> 1000ms -> máx 2000ms) que inutilizan scripts de inundación
 *    sin agotar los workers del servidor ni degradar la experiencia de un ser humano.
 * 4. RECUPERACIÓN AUTOMÁTICA: La penalización decae naturalmente con el paso del tiempo
 *    según una ventana rodante móvil.
 * 5. PRIVACIDAD ESTRICTA: Cero persistencia de IPs o User-Agents en expedientes ni snapshots.
 */
class ProteccionFormularioPublicoServicio
{
    /** Clave de sesión donde se almacena el estado temporal volátil */
    public const CLAVE_SESION = '_proteccion_formulario_reclamaciones';

    /** Ventana rodante de evaluación en segundos */
    public const VENTANA_SEGUNDOS = 60;

    /** Umbral de intentos sin retardo (comportamiento normal) */
    public const UMBRAL_CORTESIA = 1;

    /** Techo máximo de retardo en milisegundos para no agotar workers */
    public const RETARDO_MAXIMO_MS = 2000;

    /** Escala progresiva de retardo en milisegundos según repeticiones dentro de la ventana */
    private const ESCALA_RETARDOS_MS = [
        0 => 0,      // Intento 1: cortesía, sin retardo
        1 => 250,    // Intento 2: 250 ms
        2 => 500,    // Intento 3: 500 ms
        3 => 1000,   // Intento 4: 1.0 s
        4 => 2000,   // Intento 5 o más: 2.0 s (techo no impeditivo)
    ];

    /** @var callable Inyector de reloj para pruebas deterministas sin depender del tiempo real */
    private $reloj;

    /** @var callable Inyector de retardador para pruebas deterministas sin pausar ejecución real */
    private $retardador;

    /**
     * @param (callable(): int)|null $reloj Retorna la marca de tiempo Unix actual en segundos
     * @param (callable(int): void)|null $retardador Recibe microsegundos para pausar
     */
    public function __construct(?callable $reloj = null, ?callable $retardador = null)
    {
        $this->reloj = $reloj ?? static fn(): int => time();
        $this->retardador = $retardador ?? static function (int $microsegundos): void {
            if ($microsegundos > 0) {
                usleep($microsegundos);
            }
        };
    }

    /**
     * Aplica la penalización de throttling progresivo no impeditivo registrando el intento.
     *
     * @return int Retardo aplicado en milisegundos
     */
    public function aplicarThrottling(): int
    {
        $this->asegurarSesionActiva();

        $ahora = ($this->reloj)();
        $intentos = $this->obtenerIntentosValidos($ahora);

        $conteoPrevio = count($intentos);
        $retardoMs = $this->calcularRetardoMsParaConteo($conteoPrevio);

        // Si corresponde retardo, aplicarlo mediante el retardador inyectado
        if ($retardoMs > 0) {
            ($this->retardador)($retardoMs * 1000);
        }

        // Registrar el intento actual y guardar en sesión
        $intentos[] = $ahora;
        $this->guardarIntentos($intentos);

        return $retardoMs;
    }

    /**
     * Calcula los milisegundos de retardo que corresponden al siguiente intento según el historial actual.
     *
     * @return int Milisegundos de retardo (0 a 2000)
     */
    public function calcularRetardoActual(): int
    {
        $this->asegurarSesionActiva();
        $ahora = ($this->reloj)();
        $intentos = $this->obtenerIntentosValidos($ahora);

        return $this->calcularRetardoMsParaConteo(count($intentos));
    }

    /**
     * Obtiene el nivel de penalización actual (0 = normal, 1 a 4 = progresivo).
     *
     * @return int
     */
    public function obtenerNivelActual(): int
    {
        $this->asegurarSesionActiva();
        $ahora = ($this->reloj)();
        $intentos = $this->obtenerIntentosValidos($ahora);
        $conteo = count($intentos);

        if ($conteo <= self::UMBRAL_CORTESIA) {
            return 0;
        }

        $exceso = $conteo - self::UMBRAL_CORTESIA;
        return min($exceso, count(self::ESCALA_RETARDOS_MS) - 1);
    }

    /**
     * Obtiene el total de intentos registrados en la ventana móvil activa.
     *
     * @return int
     */
    public function contarIntentosEnVentana(): int
    {
        $this->asegurarSesionActiva();
        $ahora = ($this->reloj)();
        return count($this->obtenerIntentosValidos($ahora));
    }

    /**
     * Limpia el historial de intentos de la sesión actual (recuperación manual o tras éxito).
     */
    public function reiniciar(): void
    {
        $this->asegurarSesionActiva();
        unset($_SESSION[self::CLAVE_SESION]);
    }

    /**
     * Calcula los milisegundos según la tabla de escala acotada.
     */
    private function calcularRetardoMsParaConteo(int $conteoPrevio): int
    {
        if ($conteoPrevio < self::UMBRAL_CORTESIA) {
            return 0;
        }

        $indice = $conteoPrevio - self::UMBRAL_CORTESIA + 1;
        $maxIndice = count(self::ESCALA_RETARDOS_MS) - 1;
        $indiceAjustado = min($indice, $maxIndice);

        return min(self::ESCALA_RETARDOS_MS[$indiceAjustado] ?? self::RETARDO_MAXIMO_MS, self::RETARDO_MAXIMO_MS);
    }

    /**
     * Filtra los intentos descartando los que cayeron fuera de la ventana móvil.
     *
     * @param int $ahora
     * @return array<int, int>
     */
    private function obtenerIntentosValidos(int $ahora): array
    {
        $almacenados = $_SESSION[self::CLAVE_SESION]['intentos'] ?? [];
        if (!is_array($almacenados)) {
            return [];
        }

        $limiteInferior = $ahora - self::VENTANA_SEGUNDOS;
        $validos = [];

        foreach ($almacenados as $ts) {
            if (is_int($ts) && $ts > $limiteInferior && $ts <= $ahora) {
                $validos[] = $ts;
            }
        }

        return $validos;
    }

    /**
     * Persiste el arreglo de marcas de tiempo en la sesión.
     *
     * @param array<int, int> $intentos
     */
    private function guardarIntentos(array $intentos): void
    {
        $_SESSION[self::CLAVE_SESION] = [
            'intentos' => array_values($intentos),
            'actualizado_en' => ($this->reloj)(),
        ];
    }

    /**
     * Garantiza que la sesión PHP esté activa para operar con el estado volátil.
     */
    private function asegurarSesionActiva(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            SesionServicio::iniciarSesionPhp();
        }
    }
}
