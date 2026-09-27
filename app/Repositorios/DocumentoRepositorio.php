<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\DocumentoEmitido;
use CamargoPMS\Modelos\DocumentoIncidencia;
use CamargoPMS\Modelos\DocumentoPlantilla;
use CamargoPMS\Modelos\DocumentoPlantillaVersion;
use DateTimeImmutable;
use PDO;

/**
 * Repositorio de persistencia relacional para el motor documental (D-079).
 */
class DocumentoRepositorio
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function obtenerPdo(): PDO
    {
        return $this->pdo;
    }

    // =========================================================================
    // 1. SECUENCIAS CONCURRENCY-SAFE (D-079 #14)
    // =========================================================================

    /**
     * Genera el siguiente correlativo y folio de manera atómica con bloqueo pesimista.
     *
     * @param string $tipoDocumento Código de la plantilla (ej: 'CONTRATO_ARRENDAMIENTO')
     * @param string $prefijo Prefijo del folio (ej: 'ARR')
     * @param DateTimeImmutable|null $fecha
     * @return string Folio generado (ej: 'DOC-ARR-202609-0001')
     */
    public function obtenerSiguienteFolio(
        string $tipoDocumento,
        string $prefijo = 'DOC',
        ?DateTimeImmutable $fecha = null
    ): string {
        $fecha = $fecha ?? new DateTimeImmutable();
        $periodoYm = $fecha->format('Ym');
        $tipoDoc = strtoupper(trim($tipoDocumento));

        $enTransaccion = $this->pdo->inTransaction();
        if (!$enTransaccion) {
            $this->pdo->beginTransaction();
        }

        try {
            // Lock pesimista de la fila de secuencia
            $stmt = $this->pdo->prepare(
                'SELECT ultimo_correlativo 
                 FROM documento_secuencias 
                 WHERE tipo_documento = :tipo AND periodo_ym = :ym 
                 FOR UPDATE'
            );
            $stmt->execute(['tipo' => $tipoDoc, 'ym' => $periodoYm]);
            $correlativoActual = $stmt->fetchColumn();

            if ($correlativoActual === false) {
                // Primera emisión del mes para este tipo
                $nuevoCorrelativo = 1;
                $stmtIns = $this->pdo->prepare(
                    'INSERT INTO documento_secuencias (tipo_documento, periodo_ym, ultimo_correlativo) 
                     VALUES (:tipo, :ym, :correlativo)'
                );
                $stmtIns->execute([
                    'tipo' => $tipoDoc,
                    'ym' => $periodoYm,
                    'correlativo' => $nuevoCorrelativo,
                ]);
            } else {
                $nuevoCorrelativo = ((int) $correlativoActual) + 1;
                $stmtUpd = $this->pdo->prepare(
                    'UPDATE documento_secuencias 
                     SET ultimo_correlativo = :correlativo 
                     WHERE tipo_documento = :tipo AND periodo_ym = :ym'
                );
                $stmtUpd->execute([
                    'correlativo' => $nuevoCorrelativo,
                    'tipo' => $tipoDoc,
                    'ym' => $periodoYm,
                ]);
            }

            if (!$enTransaccion) {
                $this->pdo->commit();
            }

            $correlativoStr = str_pad((string) $nuevoCorrelativo, 4, '0', STR_PAD_LEFT);
            $prefijoLimpio = strtoupper(trim($prefijo));

            return "DOC-{$prefijoLimpio}-{$periodoYm}-{$correlativoStr}";
        } catch (\Throwable $e) {
            if (!$enTransaccion && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    // =========================================================================
    // 2. CATÁLOGO DE PLANTILLAS
    // =========================================================================

    public function crearPlantilla(DocumentoPlantilla $p): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO documento_plantillas (
                codigo, nombre, descripcion, origen_tipo_permitido,
                orientacion, tamano_papel, requiere_membrete, archivo_membrete_fondo,
                margen_superior_mm, margen_inferior_mm, margen_izquierdo_mm, margen_derecho_mm,
                estado
            ) VALUES (
                :codigo, :nombre, :descripcion, :origen_tipo,
                :orientacion, :tamano_papel, :requiere_membrete, :archivo_membrete,
                :m_sup, :m_inf, :m_izq, :m_der,
                :estado
            )'
        );

        $stmt->execute([
            'codigo' => $p->obtenerCodigo(),
            'nombre' => $p->obtenerNombre(),
            'descripcion' => $p->obtenerDescripcion(),
            'origen_tipo' => $p->obtenerOrigenTipoPermitido(),
            'orientacion' => $p->obtenerOrientacion(),
            'tamano_papel' => $p->obtenerTamanoPapel(),
            'requiere_membrete' => $p->requiereMembrete() ? 1 : 0,
            'archivo_membrete' => $p->obtenerArchivoMembreteFondo(),
            'm_sup' => $p->obtenerMargenSuperiorMm(),
            'm_inf' => $p->obtenerMargenInferiorMm(),
            'm_izq' => $p->obtenerMargenIzquierdoMm(),
            'm_der' => $p->obtenerMargenDerechoMm(),
            'estado' => $p->obtenerEstado(),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function obtenerPlantillaPorId(int $id): ?DocumentoPlantilla
    {
        $stmt = $this->pdo->prepare('SELECT * FROM documento_plantillas WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->mapearPlantilla($row) : null;
    }

    public function obtenerPlantillaPorCodigo(string $codigo): ?DocumentoPlantilla
    {
        $stmt = $this->pdo->prepare('SELECT * FROM documento_plantillas WHERE codigo = :codigo LIMIT 1');
        $stmt->execute(['codigo' => strtoupper(trim($codigo))]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->mapearPlantilla($row) : null;
    }

    /**
     * @param array<string, mixed> $filtros
     * @return DocumentoPlantilla[]
     */
    public function listarPlantillas(array $filtros = []): array
    {
        $sql = 'SELECT * FROM documento_plantillas WHERE 1=1';
        $params = [];

        if (!empty($filtros['estado'])) {
            $sql .= ' AND estado = :estado';
            $params['estado'] = $filtros['estado'];
        }

        if (!empty($filtros['origen_tipo'])) {
            $sql .= ' AND origen_tipo_permitido = :origen';
            $params['origen'] = $filtros['origen_tipo'];
        }

        $sql .= ' ORDER BY nombre ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $resultado = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resultado[] = $this->mapearPlantilla($row);
        }

        return $resultado;
    }

    // =========================================================================
    // 3. VERSIONES INMUTABLES DE PLANTILLAS (D-079 #12, #13)
    // =========================================================================

    public function crearVersion(DocumentoPlantillaVersion $v): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO documento_plantilla_versiones (
                plantilla_id, numero_version, titulo_documento, cuerpo_html,
                estilos_css, notas_version, es_activa, creado_por_actor_id
            ) VALUES (
                :plantilla_id, :numero_version, :titulo, :html,
                :css, :notas, :es_activa, :actor_id
            )'
        );

        $stmt->execute([
            'plantilla_id' => $v->obtenerPlantillaId(),
            'numero_version' => $v->obtenerNumeroVersion(),
            'titulo' => $v->obtenerTituloDocumento(),
            'html' => $v->obtenerCuerpoHtml(),
            'css' => $v->obtenerEstilosCss(),
            'notas' => $v->obtenerNotasVersion(),
            'es_activa' => $v->esActiva() ? 1 : 0,
            'actor_id' => $v->obtenerCreadoPorActorId(),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function obtenerVersionPorId(int $id): ?DocumentoPlantillaVersion
    {
        $stmt = $this->pdo->prepare(
            'SELECT v.*, p.codigo AS plantilla_codigo, act.nombre AS creador_nombre
             FROM documento_plantilla_versiones v
             JOIN documento_plantillas p ON p.id = v.plantilla_id
             JOIN actores act ON act.id = v.creado_por_actor_id
             WHERE v.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->mapearVersion($row) : null;
    }

    public function obtenerVersionActivaPorPlantillaId(int $plantillaId): ?DocumentoPlantillaVersion
    {
        $stmt = $this->pdo->prepare(
            'SELECT v.*, p.codigo AS plantilla_codigo, act.nombre AS creador_nombre
             FROM documento_plantilla_versiones v
             JOIN documento_plantillas p ON p.id = v.plantilla_id
             JOIN actores act ON act.id = v.creado_por_actor_id
             WHERE v.plantilla_id = :pId AND v.es_activa = 1
             LIMIT 1'
        );
        $stmt->execute(['pId' => $plantillaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->mapearVersion($row) : null;
    }

    public function obtenerVersionActivaPorCodigo(string $codigo): ?DocumentoPlantillaVersion
    {
        $stmt = $this->pdo->prepare(
            'SELECT v.*, p.codigo AS plantilla_codigo, act.nombre AS creador_nombre
             FROM documento_plantilla_versiones v
             JOIN documento_plantillas p ON p.id = v.plantilla_id
             JOIN actores act ON act.id = v.creado_por_actor_id
             WHERE p.codigo = :codigo AND v.es_activa = 1
             LIMIT 1'
        );
        $stmt->execute(['codigo' => strtoupper(trim($codigo))]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->mapearVersion($row) : null;
    }

    public function obtenerUltimoNumeroVersion(int $plantillaId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(MAX(numero_version), 0) FROM documento_plantilla_versiones WHERE plantilla_id = :pId'
        );
        $stmt->execute(['pId' => $plantillaId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * @return DocumentoPlantillaVersion[]
     */
    public function listarVersionesPorPlantillaId(int $plantillaId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT v.*, p.codigo AS plantilla_codigo, act.nombre AS creador_nombre
             FROM documento_plantilla_versiones v
             JOIN documento_plantillas p ON p.id = v.plantilla_id
             JOIN actores act ON act.id = v.creado_por_actor_id
             WHERE v.plantilla_id = :pId
             ORDER BY v.numero_version DESC'
        );
        $stmt->execute(['pId' => $plantillaId]);

        $res = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res[] = $this->mapearVersion($row);
        }

        return $res;
    }

    /**
     * Activa atómicamente una versión de plantilla con bloqueo pesimista y protección InnoDB (D-079 #13).
     */
    public function activarVersion(int $plantillaId, int $versionId): bool
    {
        $enTransaccion = $this->pdo->inTransaction();
        if (!$enTransaccion) {
            $this->pdo->beginTransaction();
        }

        try {
            // Bloqueo pesimista del maestro de plantilla
            $stmtLock = $this->pdo->prepare('SELECT id FROM documento_plantillas WHERE id = :id FOR UPDATE');
            $stmtLock->execute(['id' => $plantillaId]);

            // Desactivar cualquier versión activa actual de esta plantilla
            $stmtDesact = $this->pdo->prepare(
                'UPDATE documento_plantilla_versiones 
                 SET es_activa = 0 
                 WHERE plantilla_id = :pId AND es_activa = 1'
            );
            $stmtDesact->execute(['pId' => $plantillaId]);

            // Activar la versión seleccionada
            $stmtAct = $this->pdo->prepare(
                'UPDATE documento_plantilla_versiones 
                 SET es_activa = 1 
                 WHERE id = :vId AND plantilla_id = :pId'
            );
            $stmtAct->execute(['vId' => $versionId, 'pId' => $plantillaId]);

            if (!$enTransaccion) {
                $this->pdo->commit();
            }

            return true;
        } catch (\Throwable $e) {
            if (!$enTransaccion && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    // =========================================================================
    // 4. DOCUMENTOS EMITIDOS (D-079 #4, #5, #6, #7)
    // =========================================================================

    public function crearDocumentoEmitido(DocumentoEmitido $d): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO documentos_emitidos (
                codigo_folio, plantilla_id, plantilla_version_id, origen_tipo, origen_id,
                snapshot_datos_json, snapshot_html, ruta_archivo_pdf, tamano_bytes,
                hash_pdf_sha256, hash_snapshot_sha256, numero_paginas,
                emitido_por_actor_id, emitido_en, estado
            ) VALUES (
                :folio, :plantilla_id, :version_id, :origen_tipo, :origen_id,
                :datos_json, :snapshot_html, :ruta_pdf, :tamano,
                :hash_pdf, :hash_snap, :paginas,
                :emisor_id, :emitido_en, :estado
            )'
        );

        $stmt->execute([
            'folio' => $d->obtenerCodigoFolio(),
            'plantilla_id' => $d->obtenerPlantillaId(),
            'version_id' => $d->obtenerPlantillaVersionId(),
            'origen_tipo' => $d->obtenerOrigenTipo(),
            'origen_id' => $d->obtenerOrigenId(),
            'datos_json' => json_encode($d->obtenerSnapshotDatosJson(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'snapshot_html' => $d->obtenerSnapshotHtml(),
            'ruta_pdf' => $d->obtenerRutaArchivoPdf(),
            'tamano' => $d->obtenerTamanoBytes(),
            'hash_pdf' => $d->obtenerHashPdfSha256(),
            'hash_snap' => $d->obtenerHashSnapshotSha256(),
            'paginas' => $d->obtenerNumeroPaginas(),
            'emisor_id' => $d->obtenerEmitidoPorActorId(),
            'emitido_en' => $d->obtenerEmitidoEn() ?? (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            'estado' => $d->obtenerEstado(),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function obtenerDocumentoEmitidoPorId(int $id): ?DocumentoEmitido
    {
        $stmt = $this->pdo->prepare(
            'SELECT d.*, p.codigo AS plantilla_codigo, p.nombre AS plantilla_nombre,
                    v.numero_version AS plantilla_numero_version, act.nombre AS emisor_nombre
             FROM documentos_emitidos d
             JOIN documento_plantillas p ON p.id = d.plantilla_id
             JOIN documento_plantilla_versiones v ON v.id = d.plantilla_version_id
             JOIN actores act ON act.id = d.emitido_por_actor_id
             WHERE d.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->mapearDocumentoEmitido($row) : null;
    }

    public function obtenerDocumentoEmitidoPorFolio(string $folio): ?DocumentoEmitido
    {
        $stmt = $this->pdo->prepare(
            'SELECT d.*, p.codigo AS plantilla_codigo, p.nombre AS plantilla_nombre,
                    v.numero_version AS plantilla_numero_version, act.nombre AS emisor_nombre
             FROM documentos_emitidos d
             JOIN documento_plantillas p ON p.id = d.plantilla_id
             JOIN documento_plantilla_versiones v ON v.id = d.plantilla_version_id
             JOIN actores act ON act.id = d.emitido_por_actor_id
             WHERE d.codigo_folio = :folio
             LIMIT 1'
        );
        $stmt->execute(['folio' => trim($folio)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->mapearDocumentoEmitido($row) : null;
    }

    /**
     * @param array<string, mixed> $filtros
     * @return DocumentoEmitido[]
     */
    public function listarDocumentosEmitidos(array $filtros = []): array
    {
        $sql = 'SELECT d.*, p.codigo AS plantilla_codigo, p.nombre AS plantilla_nombre,
                       v.numero_version AS plantilla_numero_version, act.nombre AS emisor_nombre
                FROM documentos_emitidos d
                JOIN documento_plantillas p ON p.id = d.plantilla_id
                JOIN documento_plantilla_versiones v ON v.id = d.plantilla_version_id
                JOIN actores act ON act.id = d.emitido_por_actor_id
                WHERE 1=1';
        $params = [];

        if (!empty($filtros['origen_tipo'])) {
            $sql .= ' AND d.origen_tipo = :origen_tipo';
            $params['origen_tipo'] = $filtros['origen_tipo'];
        }

        if (!empty($filtros['origen_id'])) {
            $sql .= ' AND d.origen_id = :origen_id';
            $params['origen_id'] = (int) $filtros['origen_id'];
        }

        if (!empty($filtros['plantilla_id'])) {
            $sql .= ' AND d.plantilla_id = :plantilla_id';
            $params['plantilla_id'] = (int) $filtros['plantilla_id'];
        }

        if (!empty($filtros['estado'])) {
            $sql .= ' AND d.estado = :estado';
            $params['estado'] = $filtros['estado'];
        }

        $sql .= ' ORDER BY d.id DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $res = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res[] = $this->mapearDocumentoEmitido($row);
        }

        return $res;
    }

    public function anularDocumento(int $documentoId, int $actorId, string $motivo): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE documentos_emitidos 
             SET estado = \'ANULADO\', motivo_anulacion = :motivo, anulado_en = NOW(), anulado_por_actor_id = :actor_id
             WHERE id = :id AND estado = \'VALIDO\''
        );
        $stmt->execute([
            'id' => $documentoId,
            'actor_id' => $actorId,
            'motivo' => trim($motivo),
        ]);

        return $stmt->rowCount() > 0;
    }

    public function actualizarRutaYHashPdf(int $documentoId, string $nuevaRuta, int $tamanoBytes, string $nuevoHashPdf): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE documentos_emitidos 
             SET ruta_archivo_pdf = :ruta, tamano_bytes = :tamano, hash_pdf_sha256 = :hash_pdf
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $documentoId,
            'ruta' => $nuevaRuta,
            'tamano' => $tamanoBytes,
            'hash_pdf' => $nuevoHashPdf,
        ]);

        return $stmt->rowCount() > 0;
    }

    // =========================================================================
    // 5. INCIDENCIAS DOCUMENTALES (D-079 #9)
    // =========================================================================

    public function registrarIncidencia(DocumentoIncidencia $inc): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO documento_incidencias (
                documento_emitido_id, tipo_incidencia, descripcion, detectado_por_actor_id
            ) VALUES (
                :doc_id, :tipo, :desc, :actor_id
            )'
        );
        $stmt->execute([
            'doc_id' => $inc->obtenerDocumentoEmitidoId(),
            'tipo' => $inc->obtenerTipoIncidencia(),
            'desc' => $inc->obtenerDescripcion(),
            'actor_id' => $inc->obtenerDetectadoPorActorId(),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @return DocumentoIncidencia[]
     */
    public function listarIncidenciasPorDocumentoId(int $documentoId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM documento_incidencias WHERE documento_emitido_id = :docId ORDER BY id DESC'
        );
        $stmt->execute(['docId' => $documentoId]);

        $res = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res[] = new DocumentoIncidencia(
                (int) $row['id'],
                (int) $row['documento_emitido_id'],
                $row['tipo_incidencia'],
                $row['descripcion'],
                (int) $row['detectado_por_actor_id'],
                $row['detectado_en'],
                (bool) $row['resuelto'],
                $row['resuelto_en'],
                $row['resolucion_notas']
            );
        }

        return $res;
    }

    /**
     * @return DocumentoIncidencia[]
     */
    public function listarIncidencias(int $limite = 50): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT i.*, d.codigo_folio 
             FROM documento_incidencias i
             JOIN documentos_emitidos d ON d.id = i.documento_emitido_id
             ORDER BY i.id DESC
             LIMIT :limite'
        );
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        $res = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res[] = new DocumentoIncidencia(
                (int) $row['id'],
                (int) $row['documento_emitido_id'],
                $row['tipo_incidencia'],
                $row['descripcion'],
                (int) $row['detectado_por_actor_id'],
                $row['detectado_en'],
                (bool) $row['resuelto'],
                $row['resuelto_en'],
                $row['resolucion_notas']
            );
        }

        return $res;
    }

    // =========================================================================
    // MAPPERS PRIVADOS
    // =========================================================================

    private function mapearPlantilla(array $r): DocumentoPlantilla
    {
        return new DocumentoPlantilla(
            (int) $r['id'],
            $r['codigo'],
            $r['nombre'],
            $r['descripcion'] ?? null,
            $r['origen_tipo_permitido'],
            $r['orientacion'],
            $r['tamano_papel'],
            (bool) $r['requiere_membrete'],
            $r['archivo_membrete_fondo'] ?? null,
            (int) $r['margen_superior_mm'],
            (int) $r['margen_inferior_mm'],
            (int) $r['margen_izquierdo_mm'],
            (int) $r['margen_derecho_mm'],
            $r['estado'],
            $r['creado_en'],
            $r['actualizado_en']
        );
    }

    private function mapearVersion(array $r): DocumentoPlantillaVersion
    {
        return new DocumentoPlantillaVersion(
            (int) $r['id'],
            (int) $r['plantilla_id'],
            (int) $r['numero_version'],
            $r['titulo_documento'],
            $r['cuerpo_html'],
            $r['estilos_css'] ?? null,
            $r['notas_version'] ?? null,
            (bool) $r['es_activa'],
            (int) $r['creado_por_actor_id'],
            $r['creado_en'],
            $r['plantilla_codigo'] ?? null,
            $r['creador_nombre'] ?? null
        );
    }

    private function mapearDocumentoEmitido(array $r): DocumentoEmitido
    {
        $datosJson = [];
        if (!empty($r['snapshot_datos_json'])) {
            $dec = json_decode($r['snapshot_datos_json'], true);
            if (is_array($dec)) {
                $datosJson = $dec;
            }
        }

        return new DocumentoEmitido(
            (int) $r['id'],
            $r['codigo_folio'],
            (int) $r['plantilla_id'],
            (int) $r['plantilla_version_id'],
            $r['origen_tipo'],
            (int) $r['origen_id'],
            $datosJson,
            $r['snapshot_html'],
            $r['ruta_archivo_pdf'],
            (int) $r['tamano_bytes'],
            $r['hash_pdf_sha256'],
            $r['hash_snapshot_sha256'],
            (int) ($r['numero_paginas'] ?? 1),
            (int) $r['emitido_por_actor_id'],
            $r['emitido_en'],
            $r['estado'],
            $r['motivo_anulacion'] ?? null,
            $r['anulado_en'] ?? null,
            isset($r['anulado_por_actor_id']) ? (int) $r['anulado_por_actor_id'] : null,
            $r['plantilla_codigo'] ?? null,
            $r['plantilla_nombre'] ?? null,
            isset($r['plantilla_numero_version']) ? (int) $r['plantilla_numero_version'] : null,
            $r['emisor_nombre'] ?? null
        );
    }
}
