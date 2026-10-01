#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Camargo PMS — Ejecutor CLI de Auditoría Nocturna y Cierre de Fecha Hotelera (NIGHT-AUDIT-2B).
 *
 * Automatización y orquestación desatendida para cierres hoteleros bajo directivas D-090 y D-112.
 *
 * Modalidades de uso:
 *   php bin/ejecutar-night-audit.php --solo-debidas --todas
 *   php bin/ejecutar-night-audit.php --solo-debidas --propiedad=<ID>
 *   php bin/ejecutar-night-audit.php --fecha=<YYYY-MM-DD> --propiedad=<ID>
 *   php bin/ejecutar-night-audit.php --fecha=<YYYY-MM-DD> --todas
 *   php bin/ejecutar-night-audit.php --quiet ...
 *   php bin/ejecutar-night-audit.php --ayuda
 *
 * Códigos de salida:
 *   0 = Ejecución exitosa (cierres procesados correctamente o ninguna fecha debida).
 *   1 = Error operacional durante la ejecución (fallo de devengo, lock ocupado, fecha no cerrable).
 *   2 = Parámetros de invocación inválidos, incompatibles o faltantes.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Acceso denegado: este script solo puede ejecutarse desde la línea de comandos (CLI).\n";
    exit(1);
}

define('RUTA_RAIZ', dirname(__DIR__));
define('RUTA_APP', RUTA_RAIZ . DIRECTORY_SEPARATOR . 'app');

require_once RUTA_RAIZ . '/vendor/autoload.php';
\CamargoPMS\Nucleo\Configuracion::cargar(RUTA_RAIZ);

$opciones = getopt('', [
    'solo-debidas',
    'propiedad:',
    'todas',
    'fecha:',
    'quiet',
    'silencioso',
    'ayuda',
]);

$esSilencioso = isset($opciones['quiet']) || isset($opciones['silencioso']);

function imprimirAyuda(): void
{
    echo "====================================================================\n";
    echo " Camargo PMS — Ejecutor CLI de Auditoría Nocturna (Scheduler)\n";
    echo "====================================================================\n";
    echo "Uso:\n";
    echo "  php bin/ejecutar-night-audit.php --solo-debidas --propiedad=<ID>\n";
    echo "  php bin/ejecutar-night-audit.php --solo-debidas --todas\n";
    echo "  php bin/ejecutar-night-audit.php --fecha=<YYYY-MM-DD> --propiedad=<ID>\n";
    echo "  php bin/ejecutar-night-audit.php --fecha=<YYYY-MM-DD> --todas\n";
    echo "  php bin/ejecutar-night-audit.php --quiet (suprime cabeceras y banners)\n";
    echo "  php bin/ejecutar-night-audit.php --ayuda\n\n";
    echo "Parámetros:\n";
    echo "  --solo-debidas         Procesa cronológicamente todas las fechas pendientes hasta ayer.\n";
    echo "  --fecha=<YYYY-MM-DD>   Ejecuta el cierre puntual para una fecha específica (< hoy).\n";
    echo "  --propiedad=<ID>       Aplica la acción a una propiedad específica por su ID numérico.\n";
    echo "  --todas                Aplica la acción a todas las propiedades activas del sistema.\n";
    echo "  --quiet, --silencioso  Modo desatendido (adecuado para crontab / scheduler).\n";
    echo "  --ayuda                Muestra esta ayuda.\n\n";
    echo "Códigos de salida:\n";
    echo "  0 = Cierres procesados correctamente o ninguna fecha pendiente.\n";
    echo "  1 = Error operacional durante la ejecución.\n";
    echo "  2 = Parámetros inválidos o incompatibles.\n";
}

if (isset($opciones['ayuda'])) {
    imprimirAyuda();
    exit(0);
}

// -----------------------------------------------------------------------------
// Validación estricta de parámetros (Salida 2 ante sintaxis o argumentos erróneos)
// -----------------------------------------------------------------------------
$tieneSoloDebidas = isset($opciones['solo-debidas']);
$tieneFecha = !empty($opciones['fecha']);
$tienePropiedad = isset($opciones['propiedad']) && $opciones['propiedad'] !== '';
$tieneTodas = isset($opciones['todas']);

if (!$tieneSoloDebidas && !$tieneFecha) {
    if (!$esSilencioso) {
        fwrite(STDERR, "Error: Debe especificar una modalidad de cierre: --solo-debidas o --fecha=<YYYY-MM-DD>.\n");
    }
    exit(2);
}

if ($tieneSoloDebidas && $tieneFecha) {
    if (!$esSilencioso) {
        fwrite(STDERR, "Error: No puede combinar --solo-debidas con --fecha simultáneamente.\n");
    }
    exit(2);
}

if (!$tienePropiedad && !$tieneTodas) {
    if (!$esSilencioso) {
        fwrite(STDERR, "Error: Debe especificar el alcance: --propiedad=<ID> o --todas.\n");
    }
    exit(2);
}

if ($tienePropiedad && $tieneTodas) {
    if (!$esSilencioso) {
        fwrite(STDERR, "Error: No puede combinar --propiedad y --todas simultáneamente.\n");
    }
    exit(2);
}

if ($tieneFecha && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $opciones['fecha'])) {
    if (!$esSilencioso) {
        fwrite(STDERR, "Error: Formato de fecha inválido en --fecha. Se requiere formato YYYY-MM-DD.\n");
    }
    exit(2);
}

if ($tienePropiedad) {
    $propVal = (string) $opciones['propiedad'];
    if (!ctype_digit($propVal) || (int) $propVal <= 0) {
        if (!$esSilencioso) {
            fwrite(STDERR, "Error: El parámetro --propiedad debe ser un ID numérico entero positivo.\n");
        }
        exit(2);
    }
}

// -----------------------------------------------------------------------------
// Inicio de la ejecución técnica
// -----------------------------------------------------------------------------
if (!$esSilencioso) {
    echo "====================================================================\n";
    echo " Camargo PMS — Ejecutor CLI de Auditoría Nocturna (Scheduler)\n";
    echo " Fecha y hora UTC: " . gmdate('Y-m-d H:i:s') . "\n";
    echo "====================================================================\n\n";
}

try {
    $pdo = \CamargoPMS\Nucleo\BaseDatos::conexion();
    $actorRepo = new \CamargoPMS\Repositorios\ActorAuditoriaRepositorio($pdo);
    $propiedadRepo = new \CamargoPMS\Repositorios\PropiedadRepositorio($pdo);
    $nightAuditServicio = new \CamargoPMS\Servicios\NightAuditServicio($pdo);

    // Resolver actor del sistema CAMARGO_PMS (ID 1 por defecto)
    $actorSistema = $actorRepo->buscarPorCodigo('CAMARGO_PMS');
    $actorId = $actorSistema !== null ? (int) $actorSistema->obtenerId() : 1;

    $erroresDetectados = false;

    // Caso A: Modalidad --solo-debidas
    if ($tieneSoloDebidas) {
        if ($tienePropiedad) {
            $propId = (int) $opciones['propiedad'];
            $propiedad = $propiedadRepo->buscarPorId($propId);
            if ($propiedad === null) {
                if (!$esSilencioso) {
                    fwrite(STDERR, "Error: No se encontró la propiedad ID {$propId}.\n");
                }
                exit(1);
            }

            if (!$esSilencioso) {
                echo "Evaluando fechas debidas para la propiedad [{$propId}] {$propiedad->obtenerNombre()}...\n";
            }

            $fechasDebidas = $nightAuditServicio->obtenerFechasDebidasPropiedad($propId);
            if (empty($fechasDebidas)) {
                if (!$esSilencioso) {
                    echo "-> Ninguna fecha hotelera pendiente de cierre. Estado al día.\n";
                }
                exit(0);
            }

            if (!$esSilencioso) {
                echo "-> Fechas debidas encontradas: " . implode(', ', $fechasDebidas) . "\n";
                echo "-> Procesando cierres en orden cronológico estricto...\n";
            }

            $cierres = $nightAuditServicio->ejecutarCierresDebidosPropiedad(
                $propId,
                $actorId,
                'Cierre desatendido Scheduler CLI (--solo-debidas)'
            );

            if (!$esSilencioso) {
                echo "\n✓ Se ejecutaron exitosamente " . count($cierres) . " cierre(s) para la propiedad [{$propId}].\n";
                foreach ($cierres as $c) {
                    echo sprintf(
                        "   * Fecha: %s | Vendidas: %d | Ocup: %s%% | ADR: S/ %s | RevPAR: S/ %s | Estado: %s\n",
                        $c->obtenerFechaHotelera(),
                        $c->obtenerHabitacionesVendidas(),
                        $c->obtenerOcupacionPorcentaje(),
                        $c->obtenerAdr(),
                        $c->obtenerRevpar(),
                        $c->obtenerEstado()
                    );
                }
            }
            exit(0);
        }

        if ($tieneTodas) {
            if (!$esSilencioso) {
                echo "Evaluando fechas debidas para todas las propiedades activas...\n";
            }

            $resumen = $nightAuditServicio->ejecutarCierresDebidosTodas(
                $actorId,
                'Cierre desatendido Scheduler CLI (--solo-debidas --todas)'
            );

            $totalProcesados = 0;
            foreach ($resumen as $pId => $info) {
                if (!empty($info['error'])) {
                    $erroresDetectados = true;
                    if (!$esSilencioso) {
                        fwrite(STDERR, "✗ Propiedad [{$pId}] {$info['nombre']}: Error -> {$info['error']}\n");
                    }
                } else {
                    $totalProcesados += $info['total_procesados'];
                    if (!$esSilencioso) {
                        echo "✓ Propiedad [{$pId}] {$info['nombre']}: {$info['total_procesados']} fecha(s) cerrada(s).\n";
                    }
                }
            }

            if (!$esSilencioso) {
                echo "\nResumen: Total de cierres ejecutados en el sistema: {$totalProcesados}.\n";
            }

            exit($erroresDetectados ? 1 : 0);
        }
    }

    // Caso B: Modalidad --fecha=<YYYY-MM-DD>
    if ($tieneFecha) {
        $fechaHotelera = (string) $opciones['fecha'];

        if ($tienePropiedad) {
            $propId = (int) $opciones['propiedad'];
            $propiedad = $propiedadRepo->buscarPorId($propId);
            if ($propiedad === null) {
                if (!$esSilencioso) {
                    fwrite(STDERR, "Error: No se encontró la propiedad ID {$propId}.\n");
                }
                exit(1);
            }

            // Validar elegibilidad de la fecha antes de ejecutar (rechaza hoy o fechas futuras)
            $nightAuditServicio->validarFechaCerrable($propId, $fechaHotelera);

            if (!$esSilencioso) {
                echo "Ejecutando cierre de fecha {$fechaHotelera} para la propiedad [{$propId}] {$propiedad->obtenerNombre()}...\n";
            }

            $tz = $nightAuditServicio->resolverZonaHorariaPropiedad($propId);
            $cierre = $nightAuditServicio->ejecutarCierre(
                $propId,
                $fechaHotelera,
                $actorId,
                'Cierre puntual CLI (--fecha)',
                $tz,
                true,
                true
            );

            if (!$esSilencioso) {
                echo "✓ Cierre completado exitosamente.\n";
                echo sprintf(
                    "   * Fecha: %s | Vendidas: %d | Ocup: %s%% | ADR: S/ %s | RevPAR: S/ %s | Estado: %s\n",
                    $cierre->obtenerFechaHotelera(),
                    $cierre->obtenerHabitacionesVendidas(),
                    $cierre->obtenerOcupacionPorcentaje(),
                    $cierre->obtenerAdr(),
                    $cierre->obtenerRevpar(),
                    $cierre->obtenerEstado()
                );
            }
            exit(0);
        }

        if ($tieneTodas) {
            $propiedades = $propiedadRepo->listar(estado: 'ACTIVO', limite: 500);
            if (!$esSilencioso) {
                echo "Ejecutando cierre de fecha {$fechaHotelera} para todas las propiedades activas (" . count($propiedades) . ")...\n";
            }

            foreach ($propiedades as $propiedad) {
                $pId = (int) $propiedad->obtenerId();
                try {
                    $nightAuditServicio->validarFechaCerrable($pId, $fechaHotelera);
                    $tz = $nightAuditServicio->resolverZonaHorariaPropiedad($pId);
                    $cierre = $nightAuditServicio->ejecutarCierre(
                        $pId,
                        $fechaHotelera,
                        $actorId,
                        'Cierre puntual CLI (--fecha --todas)',
                        $tz,
                        true,
                        true
                    );
                    if (!$esSilencioso) {
                        echo "✓ Propiedad [{$pId}] {$propiedad->obtenerNombre()}: CERRADO (Ocup: {$cierre->obtenerOcupacionPorcentaje()}%)\n";
                    }
                } catch (\Throwable $e) {
                    $erroresDetectados = true;
                    if (!$esSilencioso) {
                        fwrite(STDERR, "✗ Propiedad [{$pId}] {$propiedad->obtenerNombre()}: Error -> {$e->getMessage()}\n");
                    }
                }
            }

            exit($erroresDetectados ? 1 : 0);
        }
    }

    exit(0);
} catch (\CamargoPMS\Excepciones\CierreHoteleroInvalidoExcepcion $e) {
    if (!$esSilencioso) {
        fwrite(STDERR, "Error de validación operacional: {$e->getMessage()}\n");
    }
    exit(1);
} catch (\Throwable $e) {
    if (!$esSilencioso) {
        fwrite(STDERR, "Error fatal inesperado: {$e->getMessage()}\n");
    }
    exit(1);
}
