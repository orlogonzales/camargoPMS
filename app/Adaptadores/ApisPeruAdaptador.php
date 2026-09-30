<?php

declare(strict_types=1);

namespace CamargoPMS\Adaptadores;

use CamargoPMS\Nucleo\Configuracion;
use InvalidArgumentException;
use RuntimeException;

/**
 * Adaptador de integración técnica para el servicio de Consulta DNI y RUC de APIsPERU.
 *
 * Principios vinculantes de seguridad y arquitectura (APISPERU-1A / APISPERU-1B):
 * 1. El token se envía vía Query Parameter (?token=...) según el contrato Swagger del proveedor.
 * 2. CERO fuga de credenciales: La URL completa, el query string y el token NUNCA se imprimen,
 *    registran en logs, ni se incluyen en mensajes de error o excepciones del sistema.
 * 3. Timeout configurable con valor predeterminado seguro (5 segundos).
 * 4. Soporta cliente HTTP inyectable (callable/mock) para garantizar pruebas 100% aisladas
 *    sin consumo de cuota real ni dependencia de red en suites automatizadas.
 */
class ApisPeruAdaptador
{
    private string $token;
    private string $baseUrl;
    private int $timeout;

    /** @var (callable(string, string, int): array<string, mixed>)|null */
    private $clienteHttp;

    /**
     * @param string|null $token Token de APIsPERU (por defecto desde configuración).
     * @param string|null $baseUrl URL base de la API v1.
     * @param int|null $timeout Tiempo límite en segundos para la conexión HTTP.
     * @param (callable(string, string, int): array<string, mixed>)|null $clienteHttp Callable para mocks en tests.
     */
    public function __construct(
        ?string $token = null,
        ?string $baseUrl = null,
        ?int $timeout = null,
        ?callable $clienteHttp = null
    ) {
        $this->token = trim($token ?? (string) Configuracion::obtener('APISPERU_DNIRUC_TOKEN', ''));
        $this->baseUrl = rtrim($baseUrl ?? (string) Configuracion::obtener('APISPERU_DNIRUC_BASE_URL', 'https://dniruc.apisperu.com/api/v1'), '/');
        $this->timeout = $timeout ?? (int) Configuracion::obtener('APISPERU_DNIRUC_TIMEOUT', 5);
        $this->clienteHttp = $clienteHttp;

        if ($this->timeout <= 0) {
            $this->timeout = 5;
        }
    }

    /**
     * Indica si el adaptador cuenta con token de autenticación configurado.
     *
     * @return bool
     */
    public function estaConfigurado(): bool
    {
        return $this->token !== '';
    }

    /**
     * Obtiene el tiempo de espera configurado en segundos.
     *
     * @return int
     */
    public function obtenerTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * Consulta información de una persona natural por DNI (8 dígitos numéricos).
     *
     * Contrato APIsPERU: GET /dni/{numero}?token={token}
     * Retorno esperado: ['dni' => '...', 'nombres' => '...', 'apellidoPaterno' => '...', 'apellidoMaterno' => '...', 'codVerifica' => '...']
     *
     * @param string $dni
     * @return array<string, mixed>
     * @throws InvalidArgumentException Si el formato del DNI es inválido.
     * @throws RuntimeException Si el servicio falla o el token no está configurado.
     */
    public function consultarDni(string $dni): array
    {
        $dniLimpio = trim($dni);
        if (!preg_match('/^\d{8}$/', $dniLimpio)) {
            throw new InvalidArgumentException('El DNI debe contener exactamente 8 dígitos numéricos.');
        }

        if (!$this->estaConfigurado()) {
            throw new RuntimeException('El token del servicio externo APIsPERU no está configurado.');
        }

        return $this->ejecutarConsulta('dni', $dniLimpio);
    }

    /**
     * Consulta información de una empresa o contribuyente por RUC (11 dígitos numéricos).
     *
     * Contrato APIsPERU: GET /ruc/{numero}?token={token}
     * Retorno esperado: ['ruc' => '...', 'razonSocial' => '...', 'nombreComercial' => '...', 'direccion' => '...', 'estado' => '...', 'condicion' => '...', 'departamento' => '...', 'provincia' => '...', 'distrito' => '...', 'ubigeo' => '...']
     *
     * @param string $ruc
     * @return array<string, mixed>
     * @throws InvalidArgumentException Si el formato del RUC es inválido.
     * @throws RuntimeException Si el servicio falla o el token no está configurado.
     */
    public function consultarRuc(string $ruc): array
    {
        $rucLimpio = trim($ruc);
        if (!preg_match('/^\d{11}$/', $rucLimpio)) {
            throw new InvalidArgumentException('El RUC debe contener exactamente 11 dígitos numéricos.');
        }

        if (!$this->estaConfigurado()) {
            throw new RuntimeException('El token del servicio externo APIsPERU no está configurado.');
        }

        return $this->ejecutarConsulta('ruc', $rucLimpio);
    }

    /**
     * Ejecuta la llamada HTTP de manera segura sin exponer credenciales ni URL completa.
     *
     * @param string $recurso 'dni' o 'ruc'
     * @param string $parametro Número de documento
     * @return array<string, mixed>
     * @throws RuntimeException
     */
    private function ejecutarConsulta(string $recurso, string $parametro): array
    {
        // 1. Si existe un cliente mock inyectado (entorno de pruebas / CI), delegar ejecución
        if ($this->clienteHttp !== null) {
            return ($this->clienteHttp)($recurso, $parametro, $this->timeout);
        }

        // 2. Construcción de URL interna (el token se incluye aquí, pero JAMÁS se registra en logs ni excepciones)
        $urlInterna = $this->baseUrl . '/' . rawurlencode($recurso) . '/' . rawurlencode($parametro) . '?token=' . rawurlencode($this->token);

        $ch = curl_init();
        if ($ch === false) {
            throw new RuntimeException('No se pudo inicializar el cliente HTTP cURL.');
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $urlInterna,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => min(3, $this->timeout),
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: CamargoPMS/1.0',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $respuestaCuerpo = curl_exec($ch);
        $errorNum = curl_errno($ch);
        $codigoHttp = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Limpiar variable sensible de memoria de forma explícita
        unset($urlInterna);

        if ($errorNum !== 0) {
            if ($errorNum === CURLE_OPERATION_TIMEDOUT) {
                throw new RuntimeException('Tiempo de espera agotado al consultar el proveedor de identidad externo (' . $this->timeout . 's).');
            }
            throw new RuntimeException('Error de comunicación con el servicio externo de consulta de identidad.');
        }

        if (!is_string($respuestaCuerpo) || trim($respuestaCuerpo) === '') {
            throw new RuntimeException('El proveedor de identidad externo devolvió una respuesta vacía.');
        }

        /** @var array<string, mixed>|null $datos */
        $datos = json_decode($respuestaCuerpo, true);
        if (!is_array($datos)) {
            throw new RuntimeException('Respuesta no válida del proveedor externo (formato no JSON).');
        }

        // Manejo de errores específicos según códigos HTTP y payload
        if ($codigoHttp === 200) {
            if (isset($datos['success']) && $datos['success'] === false) {
                $mensaje = isset($datos['message']) && is_string($datos['message'])
                    ? $datos['message']
                    : 'Documento no encontrado o consulta denegada por el proveedor.';
                throw new RuntimeException($mensaje);
            }
            return $datos;
        }

        if ($codigoHttp === 404) {
            $mensaje = isset($datos['message']) && is_string($datos['message'])
                ? $datos['message']
                : 'Documento no encontrado en los registros externos.';
            throw new RuntimeException($mensaje);
        }

        if ($codigoHttp === 401 || $codigoHttp === 403) {
            throw new RuntimeException('Error de autenticación o cuota excedida con el proveedor de identidad externo.');
        }

        if ($codigoHttp === 422) {
            $mensaje = isset($datos['message']) && is_string($datos['message'])
                ? $datos['message']
                : 'Parámetros de consulta no aceptados por el proveedor.';
            throw new RuntimeException($mensaje);
        }

        if ($codigoHttp >= 500) {
            throw new RuntimeException('El servicio externo de consulta no se encuentra disponible temporalmente.');
        }

        throw new RuntimeException('El servicio externo respondió con un código de estado inesperado (' . $codigoHttp . ').');
    }
}
