<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\ConexionIcal;
use CamargoPMS\Nucleo\BaseDatos;
use PDO;

/**
 * Repositorio de persistencia PDO para Conexiones iCalendar (1:N por Unidad).
 */
class ConexionIcalRepositorio
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? BaseDatos::conexion();
    }

    public function buscarPorId(int $id): ?ConexionIcal
    {
        $sql = 'SELECT c.*,
                       cd.codigo AS canal_codigo,
                       cd.nombre AS canal_nombre,
                       cd.color_badge AS canal_color_badge,
                       u.nombre AS unidad_nombre,
                       u.codigo AS unidad_codigo,
                       p.nombre AS propiedad_nombre,
                       p.id AS propiedad_id
                FROM conexiones_ical c
                JOIN canales_distribucion cd ON cd.id = c.canal_id
                JOIN unidades u ON u.id = c.unidad_id
                JOIN propiedades p ON p.id = u.propiedad_id
                WHERE c.id = :id
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? $this->mapearFila($fila) : null;
    }

    /**
     * Búsqueda indexada O(1) por hash SHA-256 del token de exportación.
     */
    public function buscarPorTokenHash(string $hash): ?ConexionIcal
    {
        $sql = 'SELECT c.*,
                       cd.codigo AS canal_codigo,
                       cd.nombre AS canal_nombre,
                       cd.color_badge AS canal_color_badge,
                       u.nombre AS unidad_nombre,
                       u.codigo AS unidad_codigo,
                       p.nombre AS propiedad_nombre,
                       p.id AS propiedad_id
                FROM conexiones_ical c
                JOIN canales_distribucion cd ON cd.id = c.canal_id
                JOIN unidades u ON u.id = c.unidad_id
                JOIN propiedades p ON p.id = u.propiedad_id
                WHERE c.token_exportacion_hash = :hash
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['hash' => $hash]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? $this->mapearFila($fila) : null;
    }

    /**
     * @return array<ConexionIcal>
     */
    public function listarPorUnidad(int $unidadId): array
    {
        $sql = 'SELECT c.*,
                       cd.codigo AS canal_codigo,
                       cd.nombre AS canal_nombre,
                       cd.color_badge AS canal_color_badge,
                       u.nombre AS unidad_nombre,
                       u.codigo AS unidad_codigo,
                       p.nombre AS propiedad_nombre,
                       p.id AS propiedad_id
                FROM conexiones_ical c
                JOIN canales_distribucion cd ON cd.id = c.canal_id
                JOIN unidades u ON u.id = c.unidad_id
                JOIN propiedades p ON p.id = u.propiedad_id
                WHERE c.unidad_id = :unidad_id
                ORDER BY c.id ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['unidad_id' => $unidadId]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'mapearFila'], $filas);
    }

    /**
     * Lista conexiones iCal con filtros opcionales para la capa operativa Alina.
     *
     * @return array<ConexionIcal>
     */
    public function listarTodas(
        ?int $propiedadId = null,
        ?int $unidadId = null,
        ?int $canalId = null,
        ?string $estado = null
    ): array {
        $sql = 'SELECT c.*,
                       cd.codigo AS canal_codigo,
                       cd.nombre AS canal_nombre,
                       cd.color_badge AS canal_color_badge,
                       u.nombre AS unidad_nombre,
                       u.codigo AS unidad_codigo,
                       p.nombre AS propiedad_nombre,
                       p.id AS propiedad_id
                FROM conexiones_ical c
                JOIN canales_distribucion cd ON cd.id = c.canal_id
                JOIN unidades u ON u.id = c.unidad_id
                JOIN propiedades p ON p.id = u.propiedad_id
                WHERE 1=1';
        $params = [];

        if ($propiedadId !== null) {
            $sql .= ' AND p.id = :propiedad_id';
            $params['propiedad_id'] = $propiedadId;
        }

        if ($unidadId !== null) {
            $sql .= ' AND c.unidad_id = :unidad_id';
            $params['unidad_id'] = $unidadId;
        }

        if ($canalId !== null) {
            $sql .= ' AND c.canal_id = :canal_id';
            $params['canal_id'] = $canalId;
        }

        if ($estado !== null && $estado !== '') {
            $sql .= ' AND c.estado = :estado';
            $params['estado'] = $estado;
        }

        $sql .= ' ORDER BY c.id DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'mapearFila'], $filas);
    }

    /**
     * @return array<ConexionIcal>
     */
    public function listarHabilitadasParaSondeo(): array
    {
        $sql = "SELECT c.*,
                       cd.codigo AS canal_codigo,
                       cd.nombre AS canal_nombre,
                       cd.color_badge AS canal_color_badge,
                       u.nombre AS unidad_nombre,
                       u.codigo AS unidad_codigo,
                       p.nombre AS propiedad_nombre,
                       p.id AS propiedad_id
                FROM conexiones_ical c
                JOIN canales_distribucion cd ON cd.id = c.canal_id
                JOIN unidades u ON u.id = c.unidad_id
                JOIN propiedades p ON p.id = u.propiedad_id
                WHERE c.estado = 'ACTIVO'
                  AND c.importacion_habilitada = 1
                  AND c.url_importacion_cifrada IS NOT NULL
                  AND cd.estado = 'ACTIVO'
                ORDER BY c.id ASC";

        $stmt = $this->pdo->query($sql);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'mapearFila'], $filas);
    }

    /**
     * Lista conexiones iCal activas y con importación habilitada cuya última
     * sincronización haya superado su frecuencia configurada (o nunca hayan corrido).
     *
     * @return array<ConexionIcal>
     */
    public function listarDebidasParaSondeo(): array
    {
        $sql = "SELECT c.*,
                       cd.codigo AS canal_codigo,
                       cd.nombre AS canal_nombre,
                       cd.color_badge AS canal_color_badge,
                       u.nombre AS unidad_nombre,
                       u.codigo AS unidad_codigo,
                       p.nombre AS propiedad_nombre,
                       p.id AS propiedad_id
                FROM conexiones_ical c
                JOIN canales_distribucion cd ON cd.id = c.canal_id
                JOIN unidades u ON u.id = c.unidad_id
                JOIN propiedades p ON p.id = u.propiedad_id
                WHERE c.estado = 'ACTIVO'
                  AND c.importacion_habilitada = 1
                  AND c.url_importacion_cifrada IS NOT NULL
                  AND cd.estado = 'ACTIVO'
                  AND (
                      c.ultima_sincronizacion_en IS NULL
                      OR c.ultima_sincronizacion_en <= NOW() - INTERVAL c.frecuencia_minutos MINUTE
                  )
                ORDER BY c.id ASC";

        $stmt = $this->pdo->query($sql);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'mapearFila'], $filas);
    }

    public function crear(ConexionIcal $conexion): int
    {
        $sql = 'INSERT INTO conexiones_ical (
                    unidad_id, canal_id, nombre, url_importacion_cifrada,
                    token_exportacion_hash, token_exportacion_cifrado, token_prefijo,
                    importacion_habilitada, exportacion_habilitada, frecuencia_minutos,
                    estado, creado_por
                ) VALUES (
                    :unidad_id, :canal_id, :nombre, :url_cifrada,
                    :token_hash, :token_cifrado, :token_prefijo,
                    :import_hab, :export_hab, :frecuencia,
                    :estado, :creado_por
                )';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'unidad_id' => $conexion->obtenerUnidadId(),
            'canal_id' => $conexion->obtenerCanalId(),
            'nombre' => $conexion->obtenerNombre(),
            'url_cifrada' => $conexion->obtenerUrlImportacionCifrada(),
            'token_hash' => $conexion->obtenerTokenExportacionHash(),
            'token_cifrado' => $conexion->obtenerTokenExportacionCifrado(),
            'token_prefijo' => $conexion->obtenerTokenPrefijo(),
            'import_hab' => $conexion->importacionHabilitada() ? 1 : 0,
            'export_hab' => $conexion->exportacionHabilitada() ? 1 : 0,
            'frecuencia' => $conexion->obtenerFrecuenciaMinutos(),
            'estado' => $conexion->obtenerEstado(),
            'creado_por' => $conexion->obtenerCreadoPor(),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function actualizar(ConexionIcal $conexion): bool
    {
        $sql = 'UPDATE conexiones_ical SET
                    nombre = :nombre,
                    url_importacion_cifrada = :url_cifrada,
                    importacion_habilitada = :import_hab,
                    exportacion_habilitada = :export_hab,
                    frecuencia_minutos = :frecuencia,
                    estado = :estado
                WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'id' => $conexion->obtenerId(),
            'nombre' => $conexion->obtenerNombre(),
            'url_cifrada' => $conexion->obtenerUrlImportacionCifrada(),
            'import_hab' => $conexion->importacionHabilitada() ? 1 : 0,
            'export_hab' => $conexion->exportacionHabilitada() ? 1 : 0,
            'frecuencia' => $conexion->obtenerFrecuenciaMinutos(),
            'estado' => $conexion->obtenerEstado(),
        ]);
    }

    public function actualizarUltimoResultado(int $id, string $resultado, ?string $error = null): bool
    {
        $sql = 'UPDATE conexiones_ical SET
                    ultima_sincronizacion_en = NOW(),
                    ultimo_resultado = :resultado,
                    ultimo_error = :error
                WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'id' => $id,
            'resultado' => $resultado,
            'error' => $error,
        ]);
    }

    public function rotarTokenExportacion(int $id, string $nuevoHash, string $nuevoCifrado, string $nuevoPrefijo): bool
    {
        $sql = 'UPDATE conexiones_ical SET
                    token_exportacion_hash = :hash,
                    token_exportacion_cifrado = :cifrado,
                    token_prefijo = :prefijo
                WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'id' => $id,
            'hash' => $nuevoHash,
            'cifrado' => $nuevoCifrado,
            'prefijo' => $nuevoPrefijo,
        ]);
    }

    public function cambiarEstado(int $id, string $nuevoEstado): bool
    {
        $sql = 'UPDATE conexiones_ical SET estado = :estado, actualizado_en = NOW() WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'id' => $id,
            'estado' => $nuevoEstado,
        ]);
    }

    /**
     * @param array<string, mixed> $fila
     */
    private function mapearFila(array $fila): ConexionIcal
    {
        return new ConexionIcal(
            id: (int) $fila['id'],
            unidadId: (int) $fila['unidad_id'],
            canalId: (int) $fila['canal_id'],
            nombre: (string) $fila['nombre'],
            urlImportacionCifrada: $fila['url_importacion_cifrada'] ? (string) $fila['url_importacion_cifrada'] : null,
            tokenExportacionHash: (string) $fila['token_exportacion_hash'],
            tokenExportacionCifrado: (string) $fila['token_exportacion_cifrado'],
            tokenPrefijo: (string) $fila['token_prefijo'],
            importacionHabilitada: (bool) $fila['importacion_habilitada'],
            exportacionHabilitada: (bool) $fila['exportacion_habilitada'],
            frecuenciaMinutos: (int) ($fila['frecuencia_minutos'] ?? 60),
            estado: (string) ($fila['estado'] ?? ConexionIcal::ESTADO_ACTIVO),
            ultimaSincronizacionEn: $fila['ultima_sincronizacion_en'] ? (string) $fila['ultima_sincronizacion_en'] : null,
            ultimoResultado: (string) ($fila['ultimo_resultado'] ?? ConexionIcal::RESULTADO_NO_EJECUTADO),
            ultimoError: $fila['ultimo_error'] ? (string) $fila['ultimo_error'] : null,
            creadoPor: isset($fila['creado_por']) && $fila['creado_por'] !== null ? (int) $fila['creado_por'] : null,
            creadoEn: (string) ($fila['creado_en'] ?? ''),
            actualizadoEn: (string) ($fila['actualizado_en'] ?? ''),
            canalCodigo: isset($fila['canal_codigo']) ? (string) $fila['canal_codigo'] : null,
            canalNombre: isset($fila['canal_nombre']) ? (string) $fila['canal_nombre'] : null,
            canalColorBadge: isset($fila['canal_color_badge']) ? (string) $fila['canal_color_badge'] : null,
            unidadNombre: isset($fila['unidad_nombre']) ? (string) $fila['unidad_nombre'] : null,
            unidadCodigo: isset($fila['unidad_codigo']) ? (string) $fila['unidad_codigo'] : null,
            propiedadNombre: isset($fila['propiedad_nombre']) ? (string) $fila['propiedad_nombre'] : null,
            propiedadId: isset($fila['propiedad_id']) && $fila['propiedad_id'] !== null ? (int) $fila['propiedad_id'] : null
        );
    }
}
