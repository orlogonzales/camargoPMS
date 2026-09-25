#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Camargo PMS — Script CLI de Bootstrap para Creación de Usuario Inicial.
 *
 * Propósito:
 * Inicializar de forma segura la primera cuenta humana de acceso al sistema (usuario inicial de bootstrap).
 * No atribuye roles ni permisos ficticios (la autorización pertenece a la fase RBAC).
 *
 * Restricciones:
 * - Ejecutable exclusivamente bajo CLI (PHP_SAPI === 'cli').
 * - No imprime contraseñas ni las almacena en texto plano.
 * - Rechaza la ejecución si el sistema ya cuenta con usuarios inicializados salvo con la bandera --forzar.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Acceso denegado: este script solo puede ejecutarse desde la línea de comandos (CLI).\n";
    exit(1);
}

define('RUTA_RAIZ', dirname(__DIR__));
define('RUTA_APP', RUTA_RAIZ . DIRECTORY_SEPARATOR . 'app');

// Autocargador PSR-4 (Composer con fallback nativo)
$archivoAutoload = RUTA_RAIZ . '/vendor/autoload.php';
if (file_exists($archivoAutoload)) {
    require_once $archivoAutoload;
} else {
    require_once RUTA_APP . '/Nucleo/Autocargador.php';
    $autocargador = new \CamargoPMS\Nucleo\Autocargador(RUTA_APP);
    $autocargador->registrar();
    require_once RUTA_APP . '/Nucleo/Ayudante.php';
    require_once RUTA_APP . '/Nucleo/Funciones.php';
}

\CamargoPMS\Nucleo\Configuracion::cargar(RUTA_RAIZ);

// Parsear opciones de línea de comandos
$opciones = getopt('', [
    'usuario:',
    'email:',
    'password:',
    'persona-id::',
    'nombres::',
    'primer-apellido::',
    'segundo-apellido::',
    'tipo-documento::',
    'numero-documento::',
    'pais-emisor::',
    'forzar',
    'ayuda',
]);

if (isset($opciones['ayuda'])) {
    echo "Uso: php bin/crear-usuario-inicial.php [opciones]\n\n";
    echo "Opciones:\n";
    echo "  --usuario=VALOR           Nombre de usuario (3-50 caracteres, alfanumérico)\n";
    echo "  --email=VALOR             Correo electrónico válido y único\n";
    echo "  --password=VALOR          Contraseña segura (mín. 10 chars, mayús, minús, número, símbolo)\n";
    echo "  --persona-id=ID           ID de persona existente (opcional si se pasan datos de persona)\n";
    echo "  --nombres=VALOR           Nombres para crear nueva persona\n";
    echo "  --primer-apellido=VALOR   Primer apellido de la persona\n";
    echo "  --segundo-apellido=VALOR  Segundo apellido de la persona (opcional)\n";
    echo "  --tipo-documento=CODIGO   Código de tipo documento (ej. DNI, CE, PASAPORTE)\n";
    echo "  --numero-documento=VALOR  Número de documento\n";
    echo "  --pais-emisor=CODIGO      Código ISO Alpha-3 del país emisor (ej. PER)\n";
    echo "  --forzar                  Permite crear usuario si ya existe otro registrado\n";
    echo "  --ayuda                   Muestra este mensaje de ayuda\n";
    exit(0);
}

use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Servicios\UsuarioServicio;
use CamargoPMS\Servicios\PersonaServicio;
use CamargoPMS\Repositorios\PersonaRepositorio;
use CamargoPMS\Repositorios\UsuarioRepositorio;

$pdo = BaseDatos::conexion();
$usuarioServicio = new UsuarioServicio($pdo);
$personaServicio = new PersonaServicio($pdo);

// Comprobar política de usuario inicial único
$stmtCount = $pdo->query("SELECT COUNT(*) FROM usuarios");
$totalUsuarios = (int) $stmtCount->fetchColumn();

if ($totalUsuarios > 0 && !isset($opciones['forzar'])) {
    fwrite(STDERR, "[ERROR] El sistema ya cuenta con {$totalUsuarios} usuario(s) registrado(s). El bootstrap inicial ya fue completado.\n");
    exit(1);
}

// Lectura de parámetros
$nombreUsuario = $opciones['usuario'] ?? null;
$email = $opciones['email'] ?? null;
$password = $opciones['password'] ?? null;
$personaId = isset($opciones['persona-id']) ? (int) $opciones['persona-id'] : null;

// Solicitud interactiva en caso de ausencia
if (empty($nombreUsuario)) {
    echo "Nombre de usuario: ";
    $nombreUsuario = trim((string) fgets(STDIN));
}

if (empty($email)) {
    echo "Correo electrónico: ";
    $email = trim((string) fgets(STDIN));
}

if (empty($password)) {
    echo "Contraseña: ";
    $password = trim((string) fgets(STDIN));
}

if ($personaId === null) {
    if (isset($opciones['nombres'], $opciones['primer-apellido'])) {
        $tipoDocCodigo = (string) ($opciones['tipo-documento'] ?? 'DNI');
        $numDoc = (string) ($opciones['numero-documento'] ?? str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT));
        $paisCodigo = (string) ($opciones['pais-emisor'] ?? 'PER');

        $stmtTipo = $pdo->prepare("SELECT id FROM tipos_documento WHERE codigo = :c");
        $stmtTipo->execute([':c' => $tipoDocCodigo]);
        $tipoDocId = (int) $stmtTipo->fetchColumn();
        if ($tipoDocId === 0) {
            $tipoDocId = 1;
        }

        $stmtPais = $pdo->prepare("SELECT id FROM paises WHERE codigo_iso3 = :c");
        $stmtPais->execute([':c' => $paisCodigo]);
        $paisId = (int) $stmtPais->fetchColumn();
        if ($paisId === 0) {
            $paisId = 1;
        }

        $datosPersona = [
            'nombres' => $opciones['nombres'],
            'primer_apellido' => $opciones['primer-apellido'],
            'segundo_apellido' => $opciones['segundo-apellido'] ?? null,
            'pais_nacimiento_id' => $paisId,
        ];
        $docPrincipal = [
            'tipo_documento_id' => $tipoDocId,
            'numero_documento' => $numDoc,
            'pais_emisor_id' => $paisId,
            'es_principal' => true,
        ];
        $contactos = [
            [
                'tipo_contacto' => 'EMAIL',
                'valor' => $email,
                'es_principal' => true,
            ],
        ];

        $persona = $personaServicio->crearPersona($datosPersona, $docPrincipal, $contactos);
        $personaId = (int) $persona->obtenerId();
    } else {
        $stmtFirstPersona = $pdo->query("SELECT id FROM personas WHERE estado = 'ACTIVO' LIMIT 1");
        $primeraPersona = $stmtFirstPersona->fetchColumn();
        if ($primeraPersona !== false) {
            $personaId = (int) $primeraPersona;
        } else {
            $datosPersona = [
                'nombres' => 'Administrador',
                'primer_apellido' => 'Inicial',
                'pais_nacimiento_id' => 1,
            ];
            $docPrincipal = [
                'tipo_documento_id' => 1,
                'numero_documento' => str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT),
                'pais_emisor_id' => 1,
                'es_principal' => true,
            ];
            $contactos = [
                [
                    'tipo_contacto' => 'EMAIL',
                    'valor' => $email,
                    'es_principal' => true,
                ],
            ];
            $persona = $personaServicio->crearPersona($datosPersona, $docPrincipal, $contactos);
            $personaId = (int) $persona->obtenerId();
        }
    }
}

try {
    $nuevoUsuario = $usuarioServicio->crearUsuario([
        'persona_id' => $personaId,
        'nombre_usuario' => $nombreUsuario,
        'contrasena' => $password,
    ]);

    echo "[OK] Usuario inicial de bootstrap creado exitosamente.\n";
    echo "ID Usuario: " . $nuevoUsuario->obtenerId() . "\n";
    echo "Nombre de usuario: " . $nuevoUsuario->obtenerNombreUsuario() . "\n";
    echo "Persona ID: " . $nuevoUsuario->obtenerPersonaId() . "\n";
    echo "Estado: " . $nuevoUsuario->obtenerEstado() . "\n";
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, "[ERROR] " . $e->getMessage() . "\n");
    exit(1);
}
