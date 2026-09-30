<?php

declare(strict_types=1);

namespace CamargoPMS\Intermediarios;

use CamargoPMS\Nucleo\ContextoHttpApi;
use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Nucleo\RespuestaApi;
use CamargoPMS\Repositorios\ApiIdempotenciaRepositorio;

/**
 * Intermediario que garantiza idempotencia estricta en métodos mutables (POST, PUT, PATCH).
 * - Exige Idempotency-Key.
 * - Detecta carreras concurrentes y responde HTTP 409 (OPERACION_EN_CURSO).
 * - Detecta desajuste de payload con la misma clave y responde HTTP 422 (IDEMPOTENCIA_DESAJUSTE_PAYLOAD).
 * - Realiza replay determinista de respuestas completadas previas (HTTP 200/201 con X-Cache-Lookup: IDEMPOTENT-REPLAY).
 */
class ApiIdempotenciaIntermediario
{
    private ApiIdempotenciaRepositorio $repo;
    public static ?string $cuerpoPrueba = null;
    private ?string $claveActual = null;

    public function __construct(?ApiIdempotenciaRepositorio $repo = null)
    {
        $this->repo = $repo ?? new ApiIdempotenciaRepositorio();
    }

    /**
     * @param string $rutaSolicitada
     * @return Respuesta|null
     */
    public function manejar(string $rutaSolicitada = '/'): ?Respuesta
    {
        $metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        // Solo aplica a métodos mutables
        if (!in_array($metodo, ['POST', 'PUT', 'PATCH'], true)) {
            return null;
        }

        $clave = trim((string) ($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ''));

        if ($clave === '') {
            return RespuestaApi::error(
                'La cabecera Idempotency-Key es obligatoria para esta operación.',
                'IDEMPOTENCIA_REQUERIDA',
                400
            );
        }

        if (strlen($clave) < 8 || strlen($clave) > 128 || !preg_match('/^[a-zA-Z0-9_\-]+$/', $clave)) {
            return RespuestaApi::error(
                'La cabecera Idempotency-Key tiene un formato inválido (debe tener entre 8 y 128 caracteres alfanuméricos, guiones o guiones bajos).',
                'IDEMPOTENCIA_INVALIDA',
                400
            );
        }

        $this->claveActual = $clave;

        $contexto = ContextoHttpApi::obtenerAutenticacion();
        if ($contexto === null) {
            return RespuestaApi::error(
                'Autenticación requerida para evaluar idempotencia.',
                'NO_AUTORIZADO',
                401
            );
        }

        $clienteId = (int) $contexto->obtenerCliente()->obtenerId();

        $cuerpo = self::$cuerpoPrueba ?? (string) file_get_contents('php://input');
        $cuerpoHash = hash('sha256', $cuerpo);

        $existente = $this->repo->buscar($clienteId, $rutaSolicitada, $clave);

        if ($existente !== null) {
            // Verificar colisión de cuerpo (mismo key, distinto payload)
            if ($existente->obtenerCuerpoHash() !== $cuerpoHash) {
                return RespuestaApi::error(
                    'La clave de idempotencia fue reutilizada con un cuerpo o parámetros diferentes.',
                    'IDEMPOTENCIA_DESAJUSTE_PAYLOAD',
                    422
                );
            }

            // Si ya fue completada exitosamente, devolver replay determinista instantáneo
            if ($existente->estaCompletado()) {
                ContextoHttpApi::marcarIdempotenteReplay(true);

                $cabeceras = $existente->obtenerCabeceras();
                $cabeceras['X-Cache-Lookup'] = 'IDEMPOTENT-REPLAY';
                $cabeceras['Idempotency-Key'] = $clave;
                $cabeceras['Content-Type'] = 'application/json; charset=UTF-8';
                $cabeceras['X-Correlacion-ID'] = ContextoHttpApi::obtenerCorrelacionId();

                $cors = ContextoHttpApi::obtenerCabecerasCors();
                $cabeceras = array_merge($cabeceras, $cors);

                return new Respuesta(
                    contenido: $existente->obtenerRespuestaJson() ?? '{}',
                    codigoEstado: $existente->obtenerCodigoHttp() ?? 200,
                    cabeceras: $cabeceras
                );
            }

            // Si está actualmente bloqueada en proceso
            if ($existente->estaBloqueadoActualmente()) {
                return RespuestaApi::error(
                    'Una petición concurrente con la misma clave de idempotencia se encuentra actualmente en procesamiento.',
                    'OPERACION_EN_CURSO',
                    409,
                    ['bloqueado_hasta' => $existente->obtenerBloqueadoHasta()]
                );
            }

            // Si el bloqueo expiró o falló, eliminar registro previo para reintento limpio
            $this->repo->eliminar((int) $existente->obtenerId());
        }

        // Registrar inicio de ejecución con exclusión mutua
        $nuevoId = $this->repo->registrarInicio($clienteId, $rutaSolicitada, $metodo, $clave, $cuerpoHash, 60);

        if ($nuevoId === null) {
            return RespuestaApi::error(
                'Una petición concurrente con la misma clave de idempotencia se encuentra actualmente en procesamiento.',
                'OPERACION_EN_CURSO',
                409
            );
        }

        ContextoHttpApi::establecerIdempotenciaId($nuevoId);

        return null;
    }

    /**
     * Hook post-dispatch: captura la respuesta del controlador y persiste el resultado idempotente.
     *
     * @param Respuesta $respuesta
     * @return Respuesta
     */
    public function despues(Respuesta $respuesta): Respuesta
    {
        $id = ContextoHttpApi::obtenerIdempotenciaId();

        if ($id !== null) {
            $codigoHttp = $respuesta->obtenerCodigo();

            // Persistir resultados exitosos o de validación cliente (200 a 499)
            if ($codigoHttp >= 200 && $codigoHttp < 500) {
                $this->repo->completar(
                    id: $id,
                    codigoHttp: $codigoHttp,
                    respuestaJson: $respuesta->obtenerContenido(),
                    cabeceras: ['Content-Type' => 'application/json; charset=UTF-8']
                );
            } else {
                // Errores del servidor (500+): marcar error para permitir reintento sin bloqueo
                $this->repo->marcarError($id);
            }
        }

        if ($this->claveActual !== null) {
            $respuesta = $respuesta->conCabecera('Idempotency-Key', $this->claveActual);
        }

        return $respuesta;
    }
}
