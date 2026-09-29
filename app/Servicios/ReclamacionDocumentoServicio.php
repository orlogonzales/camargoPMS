<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

use CamargoPMS\Modelos\DocumentoEmitido;
use CamargoPMS\Modelos\Reclamacion;
use CamargoPMS\Repositorios\DocumentoRepositorio;
use CamargoPMS\Repositorios\ReclamacionRepositorio;
use CamargoPMS\Servicios\Documentos\GeneradorPdf;
use DateTimeImmutable;
use Exception;
use PDO;
use RuntimeException;

/**
 * Servicio de generación y custodia documental para el Libro de Reclamaciones (RECLAMACIONES-1).
 *
 * Principio:
 * RESILIENCIA DOCUMENTAL Y DESACOPLAMIENTO
 * La reclamación se asienta en la base de datos de forma transaccional antes de la emisión del PDF.
 * Si la compilación o renderizado falla, el expediente no se pierde y el PDF puede regenerarse a demanda.
 */
class ReclamacionDocumentoServicio
{
    private GeneradorPdf $generadorPdf;
    private DocumentoRepositorio $docRepo;
    private string $storagePath;
    private string $basePath;

    public function __construct(
        private PDO $pdo,
        private ReclamacionRepositorio $reclamacionRepo,
        ?GeneradorPdf $generadorPdf = null,
        ?DocumentoRepositorio $docRepo = null,
        ?string $basePath = null
    ) {
        $this->basePath = $basePath ?? dirname(__DIR__, 2);
        $this->storagePath = rtrim($this->basePath, '/\\') . DIRECTORY_SEPARATOR . 'storage';
        $this->generadorPdf = $generadorPdf ?? new GeneradorPdf($this->basePath);
        $this->docRepo = $docRepo ?? new DocumentoRepositorio($this->pdo);
    }

    /**
     * Renderiza y emite la Hoja de Reclamación oficial en PDF, registrando el snapshot inmutable en documentos_emitidos.
     *
     * @param Reclamacion $reclamacion
     * @param int $actorId
     * @return array{
     *     documento_id: int,
     *     codigo_folio: string,
     *     nombre_archivo: string,
     *     ruta_archivo: string,
     *     tamano_bytes: int,
     *     hash_sha256: string,
     *     binario_pdf: string
     * }
     */
    public function generarHojaReclamacionPdf(Reclamacion $reclamacion, int $actorId = 1): array
    {
        if ($reclamacion->obtenerId() === null) {
            throw new RuntimeException("No se puede emitir el PDF de una reclamación sin ID persistido.");
        }

        // 1. Obtener la plantilla oficial
        $stmtPlantilla = $this->pdo->prepare("SELECT * FROM documento_plantillas WHERE codigo = 'HOJA_RECLAMACION' LIMIT 1");
        $stmtPlantilla->execute();
        $plantilla = $stmtPlantilla->fetch(PDO::FETCH_ASSOC);

        $plantillaId = $plantilla ? (int) $plantilla['id'] : 1;

        // Versión activa
        $stmtVer = $this->pdo->prepare("SELECT id FROM documento_plantilla_versiones WHERE plantilla_id = :pid AND es_activa = 1 LIMIT 1");
        $stmtVer->execute(['pid' => $plantillaId]);
        $versionId = (int) ($stmtVer->fetchColumn() ?: 1);

        // 2. Compilar el HTML oficial a partir de la vista
        $datos = $reclamacion->aArreglo();
        $vistaPath = $this->basePath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Vistas'
            . DIRECTORY_SEPARATOR . 'documentos' . DIRECTORY_SEPARATOR . 'plantillas' . DIRECTORY_SEPARATOR . 'hoja_reclamacion.php';

        if (!file_exists($vistaPath)) {
            throw new RuntimeException("No se encontró la plantilla física de la Hoja de Reclamación: {$vistaPath}");
        }

        ob_start();
        include $vistaPath;
        $htmlCompilado = ob_get_clean();

        if (empty($htmlCompilado)) {
            throw new RuntimeException("La compilación de la Hoja de Reclamación produjo un contenido vacío.");
        }

        // 3. Renderizar mediante GeneradorPdf (Dompdf)
        $renderPdf = $this->generadorPdf->renderizar($htmlCompilado, true);

        // 4. Guardar archivo físico en storage/documentos/YYYY/MM/
        $fecha = new DateTimeImmutable($reclamacion->obtenerFechaInterposicion());
        $dirAnioMes = $this->storagePath . DIRECTORY_SEPARATOR . 'documentos' . DIRECTORY_SEPARATOR . $fecha->format('Y') . DIRECTORY_SEPARATOR . $fecha->format('m');

        if (!is_dir($dirAnioMes)) {
            mkdir($dirAnioMes, 0755, true);
        }

        $nombreArchivo = 'hoja_reclamacion_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $reclamacion->obtenerCodigoInterno()) . '.pdf';
        $rutaFisicaCompleta = $dirAnioMes . DIRECTORY_SEPARATOR . $nombreArchivo;
        $rutaRelativa = 'documentos/' . $fecha->format('Y') . '/' . $fecha->format('m') . '/' . $nombreArchivo;

        if (file_put_contents($rutaFisicaCompleta, $renderPdf['binario_pdf']) === false) {
            throw new RuntimeException("No fue posible escribir el archivo PDF en el disco: {$rutaFisicaCompleta}");
        }

        // 5. Registrar en documentos_emitidos
        $codigoFolio = 'DOC-' . $reclamacion->obtenerCodigoInterno();

        // Evitar duplicidad de folio si se está regenerando
        $stmtExiste = $this->pdo->prepare("SELECT id FROM documentos_emitidos WHERE codigo_folio = :folio LIMIT 1");
        $stmtExiste->execute(['folio' => $codigoFolio]);
        $docExistenteId = $stmtExiste->fetchColumn();

        if ($docExistenteId) {
            $sqlDoc = "UPDATE documentos_emitidos SET
                            plantilla_id = :plantilla_id,
                            plantilla_version_id = :plantilla_version_id,
                            origen_tipo = 'RECLAMACION',
                            origen_id = :origen_id,
                            snapshot_datos_json = :snapshot_datos,
                            snapshot_html = :snapshot_html,
                            ruta_archivo_pdf = :ruta_pdf,
                            tamano_bytes = :tamano_bytes,
                            hash_pdf_sha256 = :hash_pdf,
                            hash_snapshot_sha256 = :hash_snapshot,
                            numero_paginas = :paginas,
                            emitido_por_actor_id = :actor_id,
                            emitido_en = NOW()
                       WHERE id = :id";

            $stmtDoc = $this->pdo->prepare($sqlDoc);
            $stmtDoc->execute([
                'id' => (int) $docExistenteId,
                'plantilla_id' => $plantillaId,
                'plantilla_version_id' => $versionId,
                'origen_id' => $reclamacion->obtenerId(),
                'snapshot_datos' => json_encode($datos, JSON_UNESCAPED_UNICODE),
                'snapshot_html' => $htmlCompilado,
                'ruta_pdf' => $rutaRelativa,
                'tamano_bytes' => $renderPdf['tamano_bytes'],
                'hash_pdf' => $renderPdf['hash_pdf_sha256'],
                'hash_snapshot' => hash('sha256', $htmlCompilado),
                'paginas' => $renderPdf['numero_paginas'],
                'actor_id' => $actorId,
            ]);

            $docId = (int) $docExistenteId;
        } else {
            $sqlDoc = "INSERT INTO documentos_emitidos (
                            codigo_folio, plantilla_id, plantilla_version_id, origen_tipo, origen_id,
                            snapshot_datos_json, snapshot_html, ruta_archivo_pdf, tamano_bytes,
                            hash_pdf_sha256, hash_snapshot_sha256, numero_paginas, emitido_por_actor_id,
                            emitido_en, estado
                       ) VALUES (
                            :folio, :plantilla_id, :plantilla_version_id, 'RECLAMACION', :origen_id,
                            :snapshot_datos, :snapshot_html, :ruta_pdf, :tamano_bytes,
                            :hash_pdf, :hash_snapshot, :paginas, :actor_id,
                            NOW(), 'VALIDO'
                       )";

            $stmtDoc = $this->pdo->prepare($sqlDoc);
            $stmtDoc->execute([
                'folio' => $codigoFolio,
                'plantilla_id' => $plantillaId,
                'plantilla_version_id' => $versionId,
                'origen_id' => $reclamacion->obtenerId(),
                'snapshot_datos' => json_encode($datos, JSON_UNESCAPED_UNICODE),
                'snapshot_html' => $htmlCompilado,
                'ruta_pdf' => $rutaRelativa,
                'tamano_bytes' => $renderPdf['tamano_bytes'],
                'hash_pdf' => $renderPdf['hash_pdf_sha256'],
                'hash_snapshot' => hash('sha256', $htmlCompilado),
                'paginas' => $renderPdf['numero_paginas'],
                'actor_id' => $actorId,
            ]);

            $docId = (int) $this->pdo->lastInsertId();
        }

        // 6. Actualizar el expediente con la referencia al documento
        $reclamacion->asignarDocumentoEmitidoId($docId);
        $stmtUpdateRec = $this->pdo->prepare("UPDATE reclamaciones SET documento_emitido_id = :doc_id, actualizado_en = NOW() WHERE id = :id");
        $stmtUpdateRec->execute([
            'doc_id' => $docId,
            'id' => $reclamacion->obtenerId(),
        ]);

        return [
            'documento_id' => $docId,
            'codigo_folio' => $codigoFolio,
            'nombre_archivo' => $nombreArchivo,
            'ruta_archivo' => $rutaFisicaCompleta,
            'tamano_bytes' => $renderPdf['tamano_bytes'],
            'hash_sha256' => $renderPdf['hash_pdf_sha256'],
            'binario_pdf' => $renderPdf['binario_pdf'],
        ];
    }

    /**
     * Obtiene el binario PDF para descarga o transmisión. Si no existe o se corrompió, lo regenera.
     *
     * @param Reclamacion $reclamacion
     * @return array{nombre_archivo: string, binario_pdf: string, tamano_bytes: int}
     */
    public function obtenerORecrearBinarioPdf(Reclamacion $reclamacion): array
    {
        $nombreArchivo = 'hoja_reclamacion_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $reclamacion->obtenerCodigoInterno()) . '.pdf';

        if ($reclamacion->obtenerDocumentoEmitidoId() !== null) {
            $stmt = $this->pdo->prepare("SELECT ruta_archivo_pdf FROM documentos_emitidos WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $reclamacion->obtenerDocumentoEmitidoId()]);
            $rutaRelativa = $stmt->fetchColumn();

            if ($rutaRelativa) {
                $rutaCompleta = $this->storagePath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $rutaRelativa);
                if (file_exists($rutaCompleta) && is_readable($rutaCompleta)) {
                    $binario = file_get_contents($rutaCompleta);
                    if ($binario !== false && strlen($binario) > 0) {
                        return [
                            'nombre_archivo' => $nombreArchivo,
                            'binario_pdf' => $binario,
                            'tamano_bytes' => strlen($binario),
                        ];
                    }
                }
            }
        }

        // Si no existe, lo regeneramos de forma transparente
        $res = $this->generarHojaReclamacionPdf($reclamacion);

        return [
            'nombre_archivo' => $res['nombre_archivo'],
            'binario_pdf' => $res['binario_pdf'],
            'tamano_bytes' => $res['tamano_bytes'],
        ];
    }
}
