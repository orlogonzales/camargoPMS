<?php

declare(strict_types=1);

/**
 * Suite de Pruebas: SUNAT-1C1 — Migración 040 y Persistencia Estructural CPE
 *
 * Valida:
 * 1. Conteo exacto de 139 tablas relacionales en MySQL 8.4 LTS.
 * 2. Registro de la migración 040_cpe_esquema_fiscal.sql en la tabla técnica `migraciones`.
 * 3. Ranura 041 estrictamente LIBRE (cero archivos 041 en SQL/migraciones/).
 * 4. Presencia de las 8 tablas relacionales autorizadas del dominio CPE.
 * 5. Claves primarias estándar AUTO_INCREMENT BIGINT UNSIGNED en las 8 tablas.
 * 6. Integridad referencial con ON DELETE RESTRICT en todas las FKs fiscales.
 * 7. Restricciones UNIQUE críticas (series, número fiscal, idempotencia, líneas, intentos).
 * 8. Constraints CHECK de formato, valores positivos y no autorreferencia.
 * 9. Ausencia absoluta de tipos FLOAT/DOUBLE en columnas numéricas y financieras.
 * 10. Tipado DECIMAL estricto en bases imponibles, IGV, totales y cantidades.
 * 11. Snapshots fiscales inmutables T0 de emisor y receptor presentes en cpe_comprobantes.
 * 12. Soporte para régimen de exportación de hospedaje a no domiciliados (DL 919).
 * 13. Cuatro dimensiones de estados ortogonales independientes en cpe_comprobantes.
 * 14. Relaciones estructurales 1:N y M:N (línea-cargos, cpe-envíos, envíos-respuestas).
 * 15. Auditoría de seguridad estructural: ausencia absoluta de credenciales o secretos en BD.
 * 16. Sincronización y paridad del esquema consolidado SQL/camargo_pms.sql.
 * 17. Inmutabilidad estricta del catálogo de referencia admin-dashboard/.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;

Configuracion::cargar(dirname(__DIR__));
$pdo = BaseDatos::conexion();

$totalChecks = 0;
$passedChecks = 0;
$failedChecks = 0;

function verificar(string $descripcion, bool $condicion): void
{
    global $totalChecks, $passedChecks, $failedChecks;
    $totalChecks++;
    if ($condicion) {
        $passedChecks++;
        echo "  [PASS] {$descripcion}\n";
    } else {
        $failedChecks++;
        echo "  [FAIL] {$descripcion}\n";
    }
}

echo "====================================================================\n";
echo " EJECUTANDO SUITE: test_sunat_cpe_1c1.php (SUNAT-1C1)\n";
echo "====================================================================\n\n";

// --------------------------------------------------------------------
// BLOQUE 1: Conteo de Tablas, Migración 040 y Ranura 041
// --------------------------------------------------------------------
echo "--- BLOQUE 1: Conteo de Tablas, Migración 040 y Ranura 041 ---\n";

$totalTablas = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();
verificar("Total de tablas relacionales en MySQL es exactamente 139 (actual: {$totalTablas})", $totalTablas === 139);

$ultimaMigracion = (string) $pdo->query("SELECT migracion FROM migraciones ORDER BY id DESC LIMIT 1")->fetchColumn();
verificar("Última migración aplicada en sistema es 040_cpe_esquema_fiscal.sql ({$ultimaMigracion})", $ultimaMigracion === '040_cpe_esquema_fiscal.sql');

$migracionRegistrada = (int) $pdo->query("SELECT COUNT(*) FROM migraciones WHERE migracion = '040_cpe_esquema_fiscal.sql'")->fetchColumn();
verificar("Migración 040 registrada formalmente en tabla técnica 'migraciones'", $migracionRegistrada === 1);

$archivos041 = glob(dirname(__DIR__) . '/SQL/migraciones/*041*');
verificar("Ranura de migración 041 estrictamente LIBRE (cero archivos 041)", empty($archivos041));

$archivo040 = dirname(__DIR__) . '/SQL/migraciones/040_cpe_esquema_fiscal.sql';
verificar("Archivo físico SQL/migraciones/040_cpe_esquema_fiscal.sql existe y es legible", file_exists($archivo040) && is_readable($archivo040));

// --------------------------------------------------------------------
// BLOQUE 2: Presencia de las 8 Tablas Relacionales Autorizadas
// --------------------------------------------------------------------
echo "\n--- BLOQUE 2: Presencia de las 8 Tablas Relacionales Autorizadas ---\n";

$tablasAutorizadas = [
    'cpe_establecimientos_configuracion',
    'cpe_series',
    'cpe_comprobantes',
    'cpe_lineas',
    'cpe_linea_cargos',
    'cpe_documentos_relacionados',
    'cpe_envios',
    'cpe_respuestas'
];

foreach ($tablasAutorizadas as $tabla) {
    $existe = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '{$tabla}'")->fetchColumn();
    verificar("Tabla '{$tabla}' existe en la base de datos", $existe === 1);
}

// --------------------------------------------------------------------
// BLOQUE 3: Claves Primarias (PK)
// --------------------------------------------------------------------
echo "\n--- BLOQUE 3: Claves Primarias (PK) ---\n";

foreach ($tablasAutorizadas as $tabla) {
    $stmt = $pdo->prepare("
        SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, EXTRA
        FROM information_schema.columns
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tabla AND COLUMN_KEY = 'PRI'
    ");
    $stmt->execute([':tabla' => $tabla]);
    $pk = $stmt->fetch(PDO::FETCH_ASSOC);

    verificar(
        "Tabla '{$tabla}' tiene PK 'id' BIGINT UNSIGNED AUTO_INCREMENT",
        $pk !== false && $pk['COLUMN_NAME'] === 'id' && str_contains($pk['COLUMN_TYPE'], 'bigint unsigned') && str_contains($pk['EXTRA'], 'auto_increment')
    );
}

// --------------------------------------------------------------------
// BLOQUE 4: Integridad Referencial y Regla ON DELETE RESTRICT
// --------------------------------------------------------------------
echo "\n--- BLOQUE 4: Integridad Referencial y Regla ON DELETE RESTRICT ---\n";

$fksEsperadas = [
    ['cpe_establecimientos_configuracion', 'empresa_id', 'empresas', 'id'],
    ['cpe_establecimientos_configuracion', 'propiedad_id', 'propiedades', 'id'],
    ['cpe_series', 'emisor_establecimiento_id', 'cpe_establecimientos_configuracion', 'id'],
    ['cpe_comprobantes', 'emisor_establecimiento_id', 'cpe_establecimientos_configuracion', 'id'],
    ['cpe_comprobantes', 'serie_id', 'cpe_series', 'id'],
    ['cpe_comprobantes', 'cuenta_folio_id', 'cuentas_folios', 'id'],
    ['cpe_comprobantes', 'creado_por_usuario_id', 'usuarios', 'id'],
    ['cpe_lineas', 'cpe_id', 'cpe_comprobantes', 'id'],
    ['cpe_linea_cargos', 'cpe_linea_id', 'cpe_lineas', 'id'],
    ['cpe_linea_cargos', 'cargo_cuenta_id', 'cargos_cuenta', 'id'],
    ['cpe_documentos_relacionados', 'cpe_id', 'cpe_comprobantes', 'id'],
    ['cpe_documentos_relacionados', 'cpe_relacionado_id', 'cpe_comprobantes', 'id'],
    ['cpe_envios', 'cpe_id', 'cpe_comprobantes', 'id'],
    ['cpe_envios', 'creado_por_actor_id', 'actores', 'id'],
    ['cpe_respuestas', 'cpe_envio_id', 'cpe_envios', 'id'],
    ['cpe_respuestas', 'cpe_id', 'cpe_comprobantes', 'id']
];

foreach ($fksEsperadas as [$tablaOrigen, $columnaOrigen, $tablaDestino, $columnaDestino]) {
    $stmt = $pdo->prepare("
        SELECT kcu.CONSTRAINT_NAME, rc.DELETE_RULE
        FROM information_schema.key_column_usage kcu
        JOIN information_schema.referential_constraints rc
            ON rc.CONSTRAINT_SCHEMA = kcu.TABLE_SCHEMA
            AND rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
        WHERE kcu.TABLE_SCHEMA = DATABASE()
            AND kcu.TABLE_NAME = :tabla
            AND kcu.COLUMN_NAME = :columna
            AND kcu.REFERENCED_TABLE_NAME = :tablaDestino
            AND kcu.REFERENCED_COLUMN_NAME = :columnaDestino
    ");
    $stmt->execute([
        ':tabla' => $tablaOrigen,
        ':columna' => $columnaOrigen,
        ':tablaDestino' => $tablaDestino,
        ':columnaDestino' => $columnaDestino
    ]);
    $fk = $stmt->fetch(PDO::FETCH_ASSOC);

    verificar(
        "FK {$tablaOrigen}.{$columnaOrigen} -> {$tablaDestino}.{$columnaDestino} existe con ON DELETE RESTRICT",
        $fk !== false && $fk['DELETE_RULE'] === 'RESTRICT'
    );
}

// --------------------------------------------------------------------
// BLOQUE 5: Restricciones UNIQUE Vinculantes
// --------------------------------------------------------------------
echo "\n--- BLOQUE 5: Restricciones UNIQUE Vinculantes ---\n";

$uniquesEsperados = [
    'cpe_establecimientos_configuracion' => ['uq_cpe_estab_anexo', 'uq_cpe_estab_propiedad'],
    'cpe_series' => ['uq_cpe_serie_tipo'],
    'cpe_comprobantes' => ['uq_cpe_numero_fiscal', 'uq_cpe_idempotencia'],
    'cpe_lineas' => ['uq_cpe_linea_orden'],
    'cpe_documentos_relacionados' => ['uq_cpe_doc_relacionado'],
    'cpe_envios' => ['uq_cpe_envio_intento']
];

foreach ($uniquesEsperados as $tabla => $indices) {
    foreach ($indices as $indice) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM information_schema.table_constraints
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = :tabla
                AND CONSTRAINT_NAME = :indice
                AND CONSTRAINT_TYPE = 'UNIQUE'
        ");
        $stmt->execute([':tabla' => $tabla, ':indice' => $indice]);
        $existe = (int) $stmt->fetchColumn();
        verificar("Restricción UNIQUE '{$indice}' presente en tabla '{$tabla}'", $existe === 1);
    }
}

// --------------------------------------------------------------------
// BLOQUE 6: Restricciones CHECK en Motor
// --------------------------------------------------------------------
echo "\n--- BLOQUE 6: Restricciones CHECK en Motor ---\n";

$checksEsperados = [
    'cpe_establecimientos_configuracion' => ['chk_cpe_estab_anexo'],
    'cpe_series' => ['chk_cpe_serie_formato', 'chk_cpe_serie_prefijo'],
    'cpe_comprobantes' => ['chk_cpe_correlativo_positivo', 'chk_cpe_total_no_negativo'],
    'cpe_lineas' => ['chk_cpe_linea_cant_pos', 'chk_cpe_linea_tot_no_neg'],
    'cpe_linea_cargos' => ['chk_cpe_lc_monto_pos', 'chk_cpe_lc_cant_pos'],
    'cpe_documentos_relacionados' => ['chk_cpe_dr_no_autoreferencia', 'chk_cpe_dr_motivo_no_vacio']
];

foreach ($checksEsperados as $tabla => $checks) {
    foreach ($checks as $chk) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM information_schema.check_constraints
            WHERE CONSTRAINT_SCHEMA = DATABASE()
                AND CONSTRAINT_NAME = :chk
        ");
        $stmt->execute([':chk' => $chk]);
        $existe = (int) $stmt->fetchColumn();
        verificar("Restricción CHECK '{$chk}' activa en tabla '{$tabla}'", $existe === 1);
    }
}

// --------------------------------------------------------------------
// BLOQUE 7: Ausencia de FLOAT/DOUBLE y Tipado DECIMAL Financiero
// --------------------------------------------------------------------
echo "\n--- BLOQUE 7: Ausencia de FLOAT/DOUBLE y Tipado DECIMAL Financiero ---\n";

$tablasCpeIn = "'" . implode("','", $tablasAutorizadas) . "'";
$floatCount = (int) $pdo->query("
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME IN ({$tablasCpeIn})
        AND DATA_TYPE IN ('float', 'double')
")->fetchColumn();
verificar("Ausencia absoluta de columnas FLOAT o DOUBLE en las 8 tablas CPE (encontradas: {$floatCount})", $floatCount === 0);

$decimalesEsperados = [
    ['cpe_comprobantes', 'total_operaciones_gravadas', 15, 2],
    ['cpe_comprobantes', 'total_operaciones_exoneradas', 15, 2],
    ['cpe_comprobantes', 'total_operaciones_inafectas', 15, 2],
    ['cpe_comprobantes', 'total_operaciones_exportacion', 15, 2],
    ['cpe_comprobantes', 'total_operaciones_gratuitas', 15, 2],
    ['cpe_comprobantes', 'total_igv', 15, 2],
    ['cpe_comprobantes', 'total_descuentos', 15, 2],
    ['cpe_comprobantes', 'total_venta', 15, 2],
    ['cpe_lineas', 'cantidad', 12, 4],
    ['cpe_lineas', 'valor_unitario', 15, 4],
    ['cpe_lineas', 'precio_unitario', 15, 4],
    ['cpe_lineas', 'base_imponible', 15, 2],
    ['cpe_lineas', 'monto_igv', 15, 2],
    ['cpe_lineas', 'total_linea', 15, 2],
    ['cpe_linea_cargos', 'cantidad_atribuida', 12, 4],
    ['cpe_linea_cargos', 'monto_atribuido', 15, 2]
];

foreach ($decimalesEsperados as [$tabla, $columna, $precision, $escala]) {
    $stmt = $pdo->prepare("
        SELECT DATA_TYPE, NUMERIC_PRECISION, NUMERIC_SCALE
        FROM information_schema.columns
        WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :tabla
            AND COLUMN_NAME = :columna
    ");
    $stmt->execute([':tabla' => $tabla, ':columna' => $columna]);
    $col = $stmt->fetch(PDO::FETCH_ASSOC);

    verificar(
        "Columna {$tabla}.{$columna} es DECIMAL({$precision},{$escala})",
        $col !== false && $col['DATA_TYPE'] === 'decimal' && (int)$col['NUMERIC_PRECISION'] === $precision && (int)$col['NUMERIC_SCALE'] === $escala
    );
}

// --------------------------------------------------------------------
// BLOQUE 8: Snapshots de Emisor y Receptor en cpe_comprobantes
// --------------------------------------------------------------------
echo "\n--- BLOQUE 8: Snapshots de Emisor y Receptor en cpe_comprobantes ---\n";

$columnasSnapshotEmisor = [
    'emisor_ruc', 'emisor_razon_social', 'emisor_nombre_comercial',
    'emisor_direccion_fiscal', 'emisor_ubigeo', 'emisor_codigo_establecimiento',
    'emisor_departamento', 'emisor_provincia', 'emisor_distrito'
];

foreach ($columnasSnapshotEmisor as $col) {
    $existe = (int) $pdo->query("
        SELECT COUNT(*)
        FROM information_schema.columns
        WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'cpe_comprobantes'
            AND COLUMN_NAME = '{$col}'
    ")->fetchColumn();
    verificar("Snapshot Emisor T0: columna '{$col}' presente en cpe_comprobantes", $existe === 1);
}

$columnasSnapshotReceptor = [
    'receptor_tipo_documento', 'receptor_numero_documento', 'receptor_razon_social',
    'receptor_direccion_fiscal', 'receptor_ubigeo', 'receptor_email', 'receptor_pais_codigo'
];

foreach ($columnasSnapshotReceptor as $col) {
    $existe = (int) $pdo->query("
        SELECT COUNT(*)
        FROM information_schema.columns
        WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'cpe_comprobantes'
            AND COLUMN_NAME = '{$col}'
    ")->fetchColumn();
    verificar("Snapshot Receptor T0: columna '{$col}' presente en cpe_comprobantes", $existe === 1);
}

// Régimen Hospedaje No Domiciliado (DL 919)
$columnasHospedaje = [
    'es_exportacion_hospedaje', 'hospedaje_tam_virtual_numero',
    'hospedaje_fecha_ingreso_pais', 'hospedaje_dias_permanencia', 'hospedaje_leyenda_tributaria'
];

foreach ($columnasHospedaje as $col) {
    $existe = (int) $pdo->query("
        SELECT COUNT(*)
        FROM information_schema.columns
        WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'cpe_comprobantes'
            AND COLUMN_NAME = '{$col}'
    ")->fetchColumn();
    verificar("Régimen Hospedaje DL 919: columna '{$col}' presente en cpe_comprobantes", $existe === 1);
}

// --------------------------------------------------------------------
// BLOQUE 9: Ejes Ortogonales de Estado
// --------------------------------------------------------------------
echo "\n--- BLOQUE 9: Ejes Ortogonales de Estado en cpe_comprobantes ---\n";

$ejesEstado = [
    'estado_generacion' => ["'BORRADOR'", "'EMITIDO'"],
    'estado_transmision' => ["'NO_INICIADO'", "'EN_PROCESO'", "'TRANSMITIDO'", "'FALLO_CONEXION'"],
    'estado_fiscal_sunat' => ["'PENDIENTE_ENVIO'", "'ACEPTADO'", "'ACEPTADO_OBSERVADO'", "'RECHAZADO'", "'BAJA_ACEPTADA'", "'EXCEPCION_SISTEMA'"],
    'estado_rectificacion' => ["'ORIGINAL'", "'RECTIFICADO_PARCIAL'", "'ANULADO_TOTAL'"]
];

foreach ($ejesEstado as $eje => $valoresEsperados) {
    $stmt = $pdo->prepare("
        SELECT COLUMN_TYPE
        FROM information_schema.columns
        WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'cpe_comprobantes'
            AND COLUMN_NAME = :eje
    ");
    $stmt->execute([':eje' => $eje]);
    $colType = (string) $stmt->fetchColumn();

    $contieneTodos = true;
    foreach ($valoresEsperados as $val) {
        if (!str_contains($colType, $val)) {
            $contieneTodos = false;
            break;
        }
    }
    verificar("Eje de estado '{$eje}' es ENUM con valores esperados ({$colType})", $contieneTodos && str_starts_with($colType, 'enum'));
}

// --------------------------------------------------------------------
// BLOQUE 10: Auditoría de Seguridad Estructural (Cero Secretos en BD)
// --------------------------------------------------------------------
echo "\n--- BLOQUE 10: Auditoría de Seguridad Estructural (Cero Secretos) ---\n";

$patronesSecretos = ['%sol%', '%pass%', '%secret%', '%private%', '%key%', '%cert%', '%token%'];
$columnasSospechosas = [];

foreach ($tablasAutorizadas as $tabla) {
    $stmt = $pdo->prepare("
        SELECT COLUMN_NAME
        FROM information_schema.columns
        WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :tabla
            AND (
                LOWER(COLUMN_NAME) LIKE '%sol%'
                OR LOWER(COLUMN_NAME) LIKE '%pass%'
                OR LOWER(COLUMN_NAME) LIKE '%secret%'
                OR LOWER(COLUMN_NAME) LIKE '%priv%'
                OR LOWER(COLUMN_NAME) LIKE '%token%'
            )
            -- Excepciones legítimas que contienen subcadenas no secretas
            AND COLUMN_NAME NOT IN ('codigo_establecimiento_sunat', 'emisor_codigo_establecimiento', 'ticket_remoto')
    ");
    $stmt->execute([':tabla' => $tabla]);
    $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($cols)) {
        foreach ($cols as $c) {
            $columnasSospechosas[] = "{$tabla}.{$c}";
        }
    }
}

verificar(
    "Cero columnas destinadas a contraseñas, claves SOL, llaves privadas o secretos (sospechosas: " . implode(', ', $columnasSospechosas) . ")",
    empty($columnasSospechosas)
);

// --------------------------------------------------------------------
// BLOQUE 11: Paridad y Sincronización de SQL/camargo_pms.sql
// --------------------------------------------------------------------
echo "\n--- BLOQUE 11: Paridad y Sincronización de SQL/camargo_pms.sql ---\n";

$sqlConsolidado = (string) file_get_contents(dirname(__DIR__) . '/SQL/camargo_pms.sql');
verificar("SQL/camargo_pms.sql contiene la Sección 43 de CPE / SUNAT", str_contains($sqlConsolidado, '43. Comprobantes de Pago Electrónicos (CPE / SUNAT)'));

foreach ($tablasAutorizadas as $tabla) {
    verificar(
        "SQL/camargo_pms.sql contiene definición CREATE TABLE IF NOT EXISTS `{$tabla}`",
        str_contains($sqlConsolidado, "CREATE TABLE IF NOT EXISTS `{$tabla}`")
    );
}

// --------------------------------------------------------------------
// BLOQUE 12: Inmutabilidad de admin-dashboard/
// --------------------------------------------------------------------
echo "\n--- BLOQUE 12: Inmutabilidad de admin-dashboard/ ---\n";

$gitAlina = trim((string) shell_exec('git status --porcelain admin-dashboard/'));
verificar("admin-dashboard/ permanece 100% inmutable y libre de modificaciones", empty($gitAlina));

// --------------------------------------------------------------------
// RESUMEN FINAL
// --------------------------------------------------------------------
echo "\n====================================================================\n";
echo " RESUMEN: test_sunat_cpe_1c1.php\n";
echo " Total checks:  {$totalChecks}\n";
echo " Checks PASS:   {$passedChecks}\n";
echo " Checks FAIL:   {$failedChecks}\n";
echo " Estado:        " . ($failedChecks === 0 ? "100% PASS — HOMOLOGADO" : "CON FALLOS") . "\n";
echo "====================================================================\n";

if ($failedChecks > 0) {
    exit(1);
}
exit(0);
