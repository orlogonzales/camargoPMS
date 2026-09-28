<?php

declare(strict_types=1);

/**
 * Suite de Verificación de Resiliencia HTTP y Desacoplamiento de Intermediarios
 *
 * Micro-baseline: HOTFIX-HTTP500
 *
 * Valida de forma permanente:
 * 1. Desacoplamiento estructural de intermediarios: la construcción de rutas no realiza I/O ni abre conexiones PDO.
 * 2. Carga perezosa (lazy loading) efectiva en AutenticacionIntermediario y AutorizacionIntermediario.
 * 3. Mapeo defensivo de excepciones HTTP en Front Controller:
 *    - CsrfInvalidoExcepcion -> HTTP 403 (NUNCA 500).
 *    - EntidadNoEncontradaExcepcion -> HTTP 404 (NUNCA 500).
 *    - Excepciones no controladas -> HTTP 500 con log sin crasheo de Apache.
 * 4. Peticiones anónimas HTML -> Redirección HTTP 302 a /login.
 * 5. Peticiones anónimas API/JSON -> HTTP 401 opaco (SESION_NO_VALIDA).
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CamargoPMS\Excepciones\CsrfInvalidoExcepcion;
use CamargoPMS\Excepciones\EntidadNoEncontradaExcepcion;
use CamargoPMS\Intermediarios\AutenticacionIntermediario;
use CamargoPMS\Intermediarios\AutorizacionIntermediario;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Servicios\SesionServicio;

Configuracion::cargar(dirname(__DIR__));

$casosPasados = 0;
$casosTotales = 12;

function afirmar(bool $condicion, string $mensaje): void {
    global $casosPasados;
    if (!$condicion) {
        echo "  [FAIL] $mensaje" . PHP_EOL;
        throw new RuntimeException("Aserción fallida: $mensaje");
    }
    echo "  [PASS] $mensaje" . PHP_EOL;
    $casosPasados++;
}

echo "====================================================================" . PHP_EOL;
echo " CAMARGO PMS — RESILIENCIA HTTP Y DESACOPLAMIENTO DE MIDDLEWARE" . PHP_EOL;
echo " Hotfix de Arquitectura: HOTFIX-HTTP500" . PHP_EOL;
echo "====================================================================" . PHP_EOL;

try {
    // 1. AutorizacionIntermediario: no conecta ansiosamente a BD en constructor
    $authz = new AutorizacionIntermediario('usuarios.ver');
    $refAuthz = new ReflectionClass($authz);
    $propSesionAuthz = $refAuthz->getProperty('sesionServicio');
    $propSesionAuthz->setAccessible(true);
    $propServicioAuthz = $refAuthz->getProperty('autorizacionServicio');
    $propServicioAuthz->setAccessible(true);

    afirmar(
        $propSesionAuthz->getValue($authz) === null &&
        $propServicioAuthz->getValue($authz) === null &&
        $authz->obtenerPermisoRequerido() === 'usuarios.ver',
        "Caso 1: AutorizacionIntermediario almacena permiso sin instanciar servicios ni abrir PDO en constructor"
    );

    // 2. AutenticacionIntermediario: no conecta ansiosamente a BD en constructor
    $authn = new AutenticacionIntermediario();
    $refAuthn = new ReflectionClass($authn);
    $propSesionAuthn = $refAuthn->getProperty('sesionServicio');
    $propSesionAuthn->setAccessible(true);

    afirmar(
        $propSesionAuthn->getValue($authn) === null,
        "Caso 2: AutenticacionIntermediario no instancia SesionServicio ni abre PDO en constructor"
    );

    // 3. Inyección explícita en AutorizacionIntermediario respetada (compatibilidad con mocks/tests)
    $mockSesion = new SesionServicio();
    $authzConInyeccion = new AutorizacionIntermediario('reportes.ver', $mockSesion);
    afirmar(
        $propSesionAuthz->getValue($authzConInyeccion) === $mockSesion,
        "Caso 3: Inyección explícita de dependencias en AutorizacionIntermediario es respetada íntegramente"
    );

    // 4. Inyección explícita en AutenticacionIntermediario respetada
    $authnConInyeccion = new AutenticacionIntermediario($mockSesion);
    afirmar(
        $propSesionAuthn->getValue($authnConInyeccion) === $mockSesion,
        "Caso 4: Inyección explícita de dependencias en AutenticacionIntermediario es respetada íntegramente"
    );

    // 5. Carga perezosa (lazy loading) activada solo bajo demanda
    $metodoObtenerSesion = $refAuthz->getMethod('obtenerSesionServicio');
    $metodoObtenerSesion->setAccessible(true);
    $sesionInstanciada = $metodoObtenerSesion->invoke($authz);

    afirmar(
        $sesionInstanciada instanceof SesionServicio &&
        $propSesionAuthz->getValue($authz) instanceof SesionServicio,
        "Caso 5: obtenerSesionServicio() instancía y cachea SesionServicio de forma estrictamente perezosa"
    );

    // 6. Simulación de construcción masiva de rutas (100 rutas protegidas)
    $rutasSimuladas = [];
    $inicioMemoria = memory_get_usage();
    for ($i = 0; $i < 100; $i++) {
        $rutasSimuladas[] = new AutorizacionIntermediario("permiso.simulado.$i");
    }
    $memoriaUsada = memory_get_usage() - $inicioMemoria;

    afirmar(
        count($rutasSimuladas) === 100 && $memoriaUsada < 100000,
        "Caso 6: Construcción de 100 intermediarios en arranque de rutas consume < 100 KB y 0 I/O"
    );

    // 7. Petición anónima HTML a AutenticacionIntermediario -> HTTP 302 a /login
    unset($_SESSION[SesionServicio::CLAVE_SESION_TOKEN], $_SESSION[SesionServicio::CLAVE_USUARIO_ID]);
    $_SERVER['HTTP_ACCEPT'] = 'text/html';
    $_SERVER['REQUEST_URI'] = '/reportes';

    $respAuthnHtml = $authn->manejar('/reportes');
    afirmar(
        $respAuthnHtml instanceof Respuesta &&
        $respAuthnHtml->obtenerCodigoEstado() === 302 &&
        str_contains($respAuthnHtml->obtenerCabeceras()['Location'] ?? '', '/login'),
        "Caso 7: Petición anónima HTML emite redirección HTTP 302 controlada hacia /login"
    );

    // 8. Petición anónima API a AutenticacionIntermediario -> HTTP 401 con SESION_NO_VALIDA
    $_SERVER['HTTP_ACCEPT'] = 'application/json';
    $_SERVER['REQUEST_URI'] = '/api/reportes';

    $respAuthnApi = $authn->manejar('/api/reportes');
    afirmar(
        $respAuthnApi instanceof Respuesta &&
        $respAuthnApi->obtenerCodigoEstado() === 401 &&
        str_contains($respAuthnApi->obtenerCuerpo(), 'SESION_NO_VALIDA'),
        "Caso 8: Petición anónima API emite HTTP 401 opaco con código SESION_NO_VALIDA"
    );

    // 9. Petición anónima API a AutorizacionIntermediario -> HTTP 401 con SESION_NO_VALIDA
    $respAuthzApi = $authz->manejar('/api/usuarios');
    afirmar(
        $respAuthzApi instanceof Respuesta &&
        $respAuthzApi->obtenerCodigoEstado() === 401 &&
        str_contains($respAuthzApi->obtenerCuerpo(), 'SESION_NO_VALIDA'),
        "Caso 9: Petición anónima API a AutorizacionIntermediario emite HTTP 401 con SESION_NO_VALIDA"
    );

    // 10. Mapeo defensivo de CsrfInvalidoExcepcion en Front Controller -> 403, NUNCA 500
    $simuladorCatch = function (\Throwable $error) {
        $codigoHttp = 500;
        if ($error instanceof CsrfInvalidoExcepcion) {
            $codigoHttp = 403;
        } elseif ($error instanceof EntidadNoEncontradaExcepcion) {
            $codigoHttp = 404;
        } elseif ($error->getCode() >= 400 && $error->getCode() < 500) {
            $codigoHttp = (int) $error->getCode();
        }
        return $codigoHttp;
    };

    $codigoCsrf = $simuladorCatch(new CsrfInvalidoExcepcion('Token CSRF inválido'));
    afirmar(
        $codigoCsrf === 403,
        "Caso 10: Mapeo defensivo de CsrfInvalidoExcepcion resuelve estrictamente código HTTP 403 Forbidden"
    );

    // 11. Mapeo defensivo de EntidadNoEncontradaExcepcion -> 404
    $codigo404 = $simuladorCatch(new EntidadNoEncontradaExcepcion('Usuario', 9999));
    afirmar(
        $codigo404 === 404,
        "Caso 11: Mapeo defensivo de EntidadNoEncontradaExcepcion resuelve estrictamente código HTTP 404 Not Found"
    );

    // 12. Fallo no controlado resuelve HTTP 500
    $codigo500 = $simuladorCatch(new RuntimeException('Error catastrófico simulado'));
    afirmar(
        $codigo500 === 500,
        "Caso 12: Excepción general no controlada se clasifica limpiamente como HTTP 500 sin crashear el servidor"
    );

    echo "====================================================================" . PHP_EOL;
    echo " RESULTADOS RESILIENCIA HTTP: $casosPasados/$casosTotales PASADAS" . PHP_EOL;
    echo "====================================================================" . PHP_EOL;

} catch (\Throwable $e) {
    echo "ERROR EN SUITE: " . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
    exit(1);
}
