<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

use CamargoPMS\Excepciones\ConflictoDocumentalExcepcion;
use CamargoPMS\Excepciones\DocumentoCorruptoExcepcion;
use CamargoPMS\Excepciones\PlantillaNoEncontradaExcepcion;
use CamargoPMS\Excepciones\ValidacionExcepcion;
use CamargoPMS\Modelos\Arrendamiento;
use CamargoPMS\Modelos\DocumentoEmitido;
use CamargoPMS\Modelos\DocumentoIncidencia;
use CamargoPMS\Modelos\DocumentoPlantilla;
use CamargoPMS\Modelos\DocumentoPlantillaVersion;
use CamargoPMS\Repositorios\ArrendamientoRepositorio;
use CamargoPMS\Repositorios\DocumentoRepositorio;
use CamargoPMS\Servicios\Documentos\CompiladorDocumental;
use CamargoPMS\Servicios\Documentos\GeneradorPdf;
use CamargoPMS\Servicios\Documentos\RegistroVariablesDocumentales;
use CamargoPMS\Servicios\Documentos\ValidadorHtmlDocumental;
use CamargoPMS\Servicios\EmpresaServicio;
use DateTimeImmutable;
use PDO;

/**
 * Servicio de dominio central para la gestión documental, plantillas versionadas y generación PDF (D-079).
 */
class DocumentoServicio
{
    private DocumentoRepositorio $docRepo;
    private ?ArrendamientoRepositorio $arrendamientoRepo;
    private CompiladorDocumental $compilador;
    private GeneradorPdf $generadorPdf;
    private ValidadorHtmlDocumental $validadorHtml;
    private RegistroVariablesDocumentales $registroVariables;
    private EmpresaServicio $empresaServicio;
    private string $storagePath;

    public function __construct(
        DocumentoRepositorio $docRepo,
        ?ArrendamientoRepositorio $arrendamientoRepo = null,
        ?CompiladorDocumental $compilador = null,
        ?GeneradorPdf $generadorPdf = null,
        ?ValidadorHtmlDocumental $validadorHtml = null,
        ?RegistroVariablesDocumentales $registroVariables = null,
        ?EmpresaServicio $empresaServicio = null,
        ?string $basePath = null
    ) {
        $this->docRepo = $docRepo;
        $this->arrendamientoRepo = $arrendamientoRepo;
        $this->validadorHtml = $validadorHtml ?? new ValidadorHtmlDocumental();
        $this->registroVariables = $registroVariables ?? new RegistroVariablesDocumentales();
        $this->compilador = $compilador ?? new CompiladorDocumental($this->validadorHtml, $this->registroVariables);
        $this->generadorPdf = $generadorPdf ?? new GeneradorPdf($basePath);
        $this->empresaServicio = $empresaServicio ?? new EmpresaServicio($this->docRepo->obtenerPdo());

        $base = $basePath ?? dirname(__DIR__, 2);
        $this->storagePath = rtrim($base, '/\\') . DIRECTORY_SEPARATOR . 'storage';
    }

    public function obtenerRepositorio(): DocumentoRepositorio
    {
        return $this->docRepo;
    }

    // =========================================================================
    // 1. GESTIÓN DE PLANTILLAS Y VERSIONES (D-079 #12, #13)
    // =========================================================================

    public function crearPlantilla(array $datos): DocumentoPlantilla
    {
        $codigo = strtoupper(trim($datos['codigo'] ?? ''));
        $nombre = trim($datos['nombre'] ?? '');
        $origenTipo = strtoupper(trim($datos['origen_tipo_permitido'] ?? 'ARRENDAMIENTO'));

        if ($codigo === '' || $nombre === '') {
            throw new ValidacionExcepcion(['codigo' => 'El código y nombre de la plantilla son obligatorios.']);
        }

        if ($this->docRepo->obtenerPlantillaPorCodigo($codigo) !== null) {
            throw new ConflictoDocumentalExcepcion("Ya existe una plantilla documental con el código [{$codigo}].");
        }

        $plantilla = new DocumentoPlantilla(
            null,
            $codigo,
            $nombre,
            $datos['descripcion'] ?? null,
            $origenTipo,
            $datos['orientacion'] ?? 'PORTRAIT',
            $datos['tamano_papel'] ?? 'A4',
            (bool) ($datos['requiere_membrete'] ?? true),
            $datos['archivo_membrete_fondo'] ?? 'membrete_a4_canonica_v1.png',
            (int) ($datos['margen_superior_mm'] ?? 35),
            (int) ($datos['margen_inferior_mm'] ?? 28),
            (int) ($datos['margen_izquierdo_mm'] ?? 20),
            (int) ($datos['margen_derecho_mm'] ?? 20),
            $datos['estado'] ?? DocumentoPlantilla::ESTADO_ACTIVO
        );

        $id = $this->docRepo->crearPlantilla($plantilla);

        return $this->docRepo->obtenerPlantillaPorId($id);
    }

    public function crearVersionPlantilla(
        int $plantillaId,
        string $tituloDocumento,
        string $cuerpoHtml,
        ?string $estilosCss = null,
        ?string $notasVersion = null,
        bool $activarInmediatamente = false,
        int $actorId = 1
    ): DocumentoPlantillaVersion {
        $plantilla = $this->docRepo->obtenerPlantillaPorId($plantillaId);
        if (!$plantilla) {
            throw new PlantillaNoEncontradaExcepcion("Plantilla ID [{$plantillaId}]");
        }

        // 1. Validar seguridad estricta del HTML y CSS
        $this->validadorHtml->validar($cuerpoHtml);
        $this->validadorHtml->validarCss($estilosCss);

        // 2. Validar que los shortcodes correspondan al origen de la plantilla
        $this->registroVariables->validarShortcodesEnHtml(
            $cuerpoHtml,
            $plantilla->obtenerOrigenTipoPermitido(),
            $plantilla->obtenerCodigo()
        );

        $siguienteVersion = $this->docRepo->obtenerUltimoNumeroVersion($plantillaId) + 1;

        $version = new DocumentoPlantillaVersion(
            null,
            $plantillaId,
            $siguienteVersion,
            $tituloDocumento,
            $cuerpoHtml,
            $estilosCss,
            $notasVersion,
            false, // Siempre se inserta inactiva primero
            $actorId
        );

        $versionId = $this->docRepo->crearVersion($version);

        if ($activarInmediatamente) {
            $this->activarVersionPlantilla($plantillaId, $versionId);
        }

        return $this->docRepo->obtenerVersionPorId($versionId);
    }

    public function activarVersionPlantilla(int $plantillaId, int $versionId): bool
    {
        $version = $this->docRepo->obtenerVersionPorId($versionId);
        if (!$version || $version->obtenerPlantillaId() !== $plantillaId) {
            throw new PlantillaNoEncontradaExcepcion("Versión ID [{$versionId}] no pertenece a la plantilla [{$plantillaId}].");
        }

        try {
            return $this->docRepo->activarVersion($plantillaId, $versionId);
        } catch (\PDOException $e) {
            // Capturar violación de uq_dpv_plantilla_activa ante carrera en motor MySQL
            if (str_contains($e->getMessage(), 'uq_dpv_plantilla_activa') || $e->getCode() === '23000') {
                throw new ConflictoDocumentalExcepcion('Conflicto concurrente: ya se encuentra una versión en proceso de activación.');
            }
            throw $e;
        }
    }

    // =========================================================================
    // 2. EMISIÓN DE CONTRATOS DE ARRENDAMIENTO (VERTICAL DOCUMENTOS-1)
    // =========================================================================

    /**
     * Emite el contrato de arrendamiento formal (o previsualización en borrador).
     *
     * @param int $arrendamientoId
     * @param int $actorId
     * @param int|null $versionId Si es null, se utiliza la versión activa de CONTRATO_ARRENDAMIENTO
     * @param bool $esBorrador Si es true, renderiza con marca de agua y no almacena registro definitivo
     * @return array{documento: ?DocumentoEmitido, binario_pdf: string, nombre_archivo: string}
     */
    public function emitirContratoArrendamiento(
        int $arrendamientoId,
        int $actorId,
        ?int $versionId = null,
        bool $esBorrador = false
    ): array {
        // 1. Obtener contrato de arrendamiento
        $arrendamiento = $this->obtenerArrendamientoValido($arrendamientoId);

        // 2. Obtener plantilla y versión
        $plantilla = $this->docRepo->obtenerPlantillaPorCodigo('CONTRATO_ARRENDAMIENTO');
        if (!$plantilla || !$plantilla->estaActivo()) {
            throw new PlantillaNoEncontradaExcepcion('Plantilla activa [CONTRATO_ARRENDAMIENTO]');
        }

        if ($versionId !== null) {
            $version = $this->docRepo->obtenerVersionPorId($versionId);
        } else {
            $version = $this->docRepo->obtenerVersionActivaPorPlantillaId((int) $plantilla->obtenerId());
        }

        if (!$version) {
            throw new PlantillaNoEncontradaExcepcion("No existe versión activa para la plantilla [{$plantilla->obtenerCodigo()}].");
        }

        // 3. Preparar folio
        $fechaActual = new DateTimeImmutable();
        if ($esBorrador) {
            $codigoFolio = 'BORRADOR-' . $arrendamiento->obtenerCodigo();
        } else {
            $codigoFolio = $this->docRepo->obtenerSiguienteFolio('CONTRATO_ARRENDAMIENTO', 'ARR', $fechaActual);
        }

        // 4. Extraer datos tipados del contrato y sus partes
        $datosContrato = $this->extraerDatosContratoArrendamiento($arrendamiento, $codigoFolio, $fechaActual);

        // 5. Resolver ruta absoluta del membrete
        $rutaMembrete = null;
        if ($plantilla->requiereMembrete() && $plantilla->obtenerArchivoMembreteFondo()) {
            $rutaMembrete = $this->storagePath . DIRECTORY_SEPARATOR . 'membretes' . DIRECTORY_SEPARATOR . $plantilla->obtenerArchivoMembreteFondo();
        }

        // 6. Compilar HTML y generar snapshot inmutable
        $marcaAgua = $esBorrador ? 'BORRADOR - SIN VALIDEZ LEGAL' : null;
        $compilacion = $this->compilador->compilar(
            $plantilla,
            $version,
            $datosContrato,
            $marcaAgua,
            $rutaMembrete
        );

        // 7. Renderizar PDF con Dompdf
        $renderPdf = $this->generadorPdf->renderizar($compilacion['snapshot_html'], true);

        $nombreArchivo = "{$codigoFolio}.pdf";

        // Si es borrador, devolvemos el PDF en memoria sin persistir emisión definitiva
        if ($esBorrador) {
            return [
                'documento' => null,
                'binario_pdf' => $renderPdf['binario_pdf'],
                'nombre_archivo' => $nombreArchivo,
            ];
        }

        // 8. Persistencia física del archivo PDF emitido
        $subcarpetaYm = $fechaActual->format('Y') . DIRECTORY_SEPARATOR . $fechaActual->format('m');
        $directorioDestino = $this->storagePath . DIRECTORY_SEPARATOR . 'documentos' . DIRECTORY_SEPARATOR . $subcarpetaYm;

        if (!is_dir($directorioDestino)) {
            mkdir($directorioDestino, 0775, true);
        }

        $rutaFisicaAbsoluta = $directorioDestino . DIRECTORY_SEPARATOR . $nombreArchivo;
        file_put_contents($rutaFisicaAbsoluta, $renderPdf['binario_pdf']);

        $rutaRelativaStorage = 'documentos/' . $fechaActual->format('Y') . '/' . $fechaActual->format('m') . '/' . $nombreArchivo;

        // 9. Persistir documento emitido con snapshots inmutables y hash SHA-256
        $docEmitido = new DocumentoEmitido(
            null,
            $codigoFolio,
            (int) $plantilla->obtenerId(),
            (int) $version->obtenerId(),
            'ARRENDAMIENTO',
            $arrendamientoId,
            $compilacion['snapshot_datos_json'],
            $compilacion['snapshot_html'],
            $rutaRelativaStorage,
            $renderPdf['tamano_bytes'],
            $renderPdf['hash_pdf_sha256'],
            $compilacion['hash_snapshot_sha256'],
            $renderPdf['numero_paginas'],
            $actorId,
            $fechaActual->format('Y-m-d H:i:s')
        );

        $docId = $this->docRepo->crearDocumentoEmitido($docEmitido);
        $documentoPersistido = $this->docRepo->obtenerDocumentoEmitidoPorId($docId);

        return [
            'documento' => $documentoPersistido,
            'binario_pdf' => $renderPdf['binario_pdf'],
            'nombre_archivo' => $nombreArchivo,
        ];
    }

    /**
     * Emite una Orden de Compra oficial en PDF A4 bajo el motor homologado Dompdf.
     *
     * @param int $ordenId
     * @param int $actorId
     * @param int|null $versionId
     * @param bool $esBorrador
     * @return array{documento: ?DocumentoEmitido, binario_pdf: string, nombre_archivo: string}
     */
    public function emitirOrdenCompra(
        int $ordenId,
        int $actorId,
        ?int $versionId = null,
        bool $esBorrador = false
    ): array {
        // 1. Obtener plantilla y versión
        $plantilla = $this->docRepo->obtenerPlantillaPorCodigo('ORDEN_COMPRA');
        if (!$plantilla || !$plantilla->estaActivo()) {
            throw new PlantillaNoEncontradaExcepcion('Plantilla activa [ORDEN_COMPRA]');
        }

        if ($versionId !== null) {
            $version = $this->docRepo->obtenerVersionPorId($versionId);
        } else {
            $version = $this->docRepo->obtenerVersionActivaPorPlantillaId((int) $plantilla->obtenerId());
        }

        if (!$version) {
            throw new PlantillaNoEncontradaExcepcion("No existe versión activa para la plantilla [{$plantilla->obtenerCodigo()}].");
        }

        // 2. Preparar folio
        $fechaActual = new DateTimeImmutable();
        if ($esBorrador) {
            $codigoFolio = 'BORRADOR-OC-' . $ordenId;
        } else {
            $codigoFolio = $this->docRepo->obtenerSiguienteFolio('ORDEN_COMPRA', 'OC-DOC', $fechaActual);
        }

        // 3. Extraer datos tipados de la orden y sus líneas
        $datosOrden = $this->extraerDatosOrdenCompra($ordenId, $codigoFolio, $fechaActual);

        // 4. Resolver ruta del membrete
        $rutaMembrete = null;
        if ($plantilla->requiereMembrete() && $plantilla->obtenerArchivoMembreteFondo()) {
            $rutaMembrete = $this->storagePath . DIRECTORY_SEPARATOR . 'membretes' . DIRECTORY_SEPARATOR . $plantilla->obtenerArchivoMembreteFondo();
        }

        // 5. Compilar HTML y generar snapshot inmutable
        $marcaAgua = $esBorrador ? 'BORRADOR - SIN VALIDEZ' : null;
        $compilacion = $this->compilador->compilar(
            $plantilla,
            $version,
            $datosOrden,
            $marcaAgua,
            $rutaMembrete
        );

        // 6. Renderizar PDF con Dompdf
        $renderPdf = $this->generadorPdf->renderizar($compilacion['snapshot_html'], true);
        $nombreArchivo = "{$codigoFolio}.pdf";

        if ($esBorrador) {
            return [
                'documento' => null,
                'binario_pdf' => $renderPdf['binario_pdf'],
                'nombre_archivo' => $nombreArchivo,
            ];
        }

        // 7. Persistencia física
        $subcarpetaYm = $fechaActual->format('Y') . DIRECTORY_SEPARATOR . $fechaActual->format('m');
        $directorioDestino = $this->storagePath . DIRECTORY_SEPARATOR . 'documentos' . DIRECTORY_SEPARATOR . $subcarpetaYm;

        if (!is_dir($directorioDestino)) {
            mkdir($directorioDestino, 0775, true);
        }

        $rutaFisicaAbsoluta = $directorioDestino . DIRECTORY_SEPARATOR . $nombreArchivo;
        file_put_contents($rutaFisicaAbsoluta, $renderPdf['binario_pdf']);

        $rutaRelativaStorage = 'documentos/' . $fechaActual->format('Y') . '/' . $fechaActual->format('m') . '/' . $nombreArchivo;

        // 8. Persistir documento emitido
        $docEmitido = new DocumentoEmitido(
            null,
            $codigoFolio,
            (int) $plantilla->obtenerId(),
            (int) $version->obtenerId(),
            'COMPRA',
            $ordenId,
            $compilacion['snapshot_datos_json'],
            $compilacion['snapshot_html'],
            $rutaRelativaStorage,
            $renderPdf['tamano_bytes'],
            $renderPdf['hash_pdf_sha256'],
            $compilacion['hash_snapshot_sha256'],
            $renderPdf['numero_paginas'],
            $actorId,
            $fechaActual->format('Y-m-d H:i:s')
        );

        $docId = $this->docRepo->crearDocumentoEmitido($docEmitido);
        $documentoPersistido = $this->docRepo->obtenerDocumentoEmitidoPorId($docId);

        return [
            'documento' => $documentoPersistido,
            'binario_pdf' => $renderPdf['binario_pdf'],
            'nombre_archivo' => $nombreArchivo,
        ];
    }

    /**
     * Emite un Recibo Oficial de Cobranza en PDF A4 bajo el motor homologado Dompdf (D-082).
     *
     * @param int $reciboId
     * @param int $actorId
     * @param int|null $versionId
     * @param bool $esBorrador
     * @return array{documento: ?DocumentoEmitido, binario_pdf: string, nombre_archivo: string}
     */
    public function emitirReciboPago(
        int $reciboId,
        int $actorId,
        ?int $versionId = null,
        bool $esBorrador = false
    ): array {
        // 1. Obtener plantilla y versión
        $plantilla = $this->docRepo->obtenerPlantillaPorCodigo('RECIBO_PAGO');
        if (!$plantilla || !$plantilla->estaActivo()) {
            throw new PlantillaNoEncontradaExcepcion('Plantilla activa [RECIBO_PAGO]');
        }

        if ($versionId !== null) {
            $version = $this->docRepo->obtenerVersionPorId($versionId);
        } else {
            $version = $this->docRepo->obtenerVersionActivaPorPlantillaId((int) $plantilla->obtenerId());
        }

        if (!$version) {
            throw new PlantillaNoEncontradaExcepcion("No existe versión activa para la plantilla [{$plantilla->obtenerCodigo()}].");
        }

        // 2. Extraer datos tipados del recibo y sus líneas
        $datosRecibo = $this->extraerDatosReciboPago($reciboId, $esBorrador);
        $codigoFolio = $datosRecibo['documento.folio'];
        $fechaActual = new DateTimeImmutable();

        // 3. Resolver ruta del membrete si correspondiera
        $rutaMembrete = null;
        if ($plantilla->requiereMembrete() && $plantilla->obtenerArchivoMembreteFondo()) {
            $rutaMembrete = $this->storagePath . DIRECTORY_SEPARATOR . 'membretes' . DIRECTORY_SEPARATOR . $plantilla->obtenerArchivoMembreteFondo();
        }

        // 4. Compilar HTML y generar snapshot inmutable
        $marcaAgua = $esBorrador ? 'BORRADOR - SIN VALIDEZ' : null;
        $compilacion = $this->compilador->compilar(
            $plantilla,
            $version,
            $datosRecibo,
            $marcaAgua,
            $rutaMembrete
        );

        // 5. Renderizar PDF con Dompdf
        $renderPdf = $this->generadorPdf->renderizar($compilacion['snapshot_html'], true);
        $nombreArchivo = "{$codigoFolio}.pdf";

        if ($esBorrador) {
            return [
                'documento' => null,
                'binario_pdf' => $renderPdf['binario_pdf'],
                'nombre_archivo' => $nombreArchivo,
            ];
        }

        // 6. Persistencia física
        $subcarpetaYm = $fechaActual->format('Y') . DIRECTORY_SEPARATOR . $fechaActual->format('m');
        $directorioDestino = $this->storagePath . DIRECTORY_SEPARATOR . 'documentos' . DIRECTORY_SEPARATOR . $subcarpetaYm;

        if (!is_dir($directorioDestino)) {
            mkdir($directorioDestino, 0775, true);
        }

        $rutaFisicaAbsoluta = $directorioDestino . DIRECTORY_SEPARATOR . $nombreArchivo;
        file_put_contents($rutaFisicaAbsoluta, $renderPdf['binario_pdf']);

        $rutaRelativaStorage = 'documentos/' . $fechaActual->format('Y') . '/' . $fechaActual->format('m') . '/' . $nombreArchivo;

        // 7. Persistir documento emitido
        $docEmitido = new DocumentoEmitido(
            null,
            $codigoFolio,
            (int) $plantilla->obtenerId(),
            (int) $version->obtenerId(),
            'RECIBO',
            $reciboId,
            $compilacion['snapshot_datos_json'],
            $compilacion['snapshot_html'],
            $rutaRelativaStorage,
            $renderPdf['tamano_bytes'],
            $renderPdf['hash_pdf_sha256'],
            $compilacion['hash_snapshot_sha256'],
            $renderPdf['numero_paginas'],
            $actorId,
            $fechaActual->format('Y-m-d H:i:s')
        );

        $docId = $this->docRepo->crearDocumentoEmitido($docEmitido);
        $documentoPersistido = $this->docRepo->obtenerDocumentoEmitidoPorId($docId);

        return [
            'documento' => $documentoPersistido,
            'binario_pdf' => $renderPdf['binario_pdf'],
            'nombre_archivo' => $nombreArchivo,
        ];
    }

    private function extraerDatosReciboPago(int $reciboId, bool $esBorrador): array
    {
        $pdo = $this->docRepo->obtenerPdo();
        $stmt = $pdo->prepare(
            'SELECT r.*,
                    cf.codigo AS folio_codigo,
                    pc.codigo AS pago_codigo,
                    mp.nombre AS metodo_nombre_catalogo,
                    arr.codigo AS arrendamiento_codigo,
                    res.codigo AS reserva_codigo,
                    COALESCE(prop_arr.id, prop_res.id, prop_cf.id) AS propiedad_id,
                    COALESCE(prop_arr.nombre, prop_res.nombre, prop_cf.nombre, "Camargo Hostelería") AS propiedad_nombre,
                    COALESCE(prop_arr.direccion, prop_res.direccion, prop_cf.direccion, "Principal") AS propiedad_direccion,
                    COALESCE(u_arr.nombre, u_res.nombre, "General") AS unidad_nombre
             FROM recibos r
             JOIN cuentas_folios cf ON cf.id = r.cuenta_folio_id
             JOIN pagos_cuenta pc ON pc.id = r.pago_id
             LEFT JOIN metodos_pago mp ON mp.id = pc.metodo_pago_id
             LEFT JOIN arrendamientos arr ON arr.id = r.arrendamiento_id
             LEFT JOIN unidades u_arr ON u_arr.id = arr.unidad_id
             LEFT JOIN propiedades prop_arr ON prop_arr.id = u_arr.propiedad_id
             LEFT JOIN reservas res ON res.id = r.reserva_id
             LEFT JOIN unidades u_res ON u_res.id = (SELECT ru.unidad_id FROM reserva_unidades ru WHERE ru.reserva_id = res.id LIMIT 1)
             LEFT JOIN propiedades prop_res ON prop_res.id = u_res.propiedad_id
             LEFT JOIN propiedades prop_cf ON prop_cf.id = 1
             WHERE r.id = :id'
        );
        $stmt->execute(['id' => $reciboId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new ValidacionExcepcion("Recibo de cobranza [{$reciboId}] no encontrado.");
        }

        $email = 'S/E';
        $telefono = 'S/T';
        if (!empty($row['persona_id'])) {
            $stmtC = $pdo->prepare('SELECT tipo_contacto, valor FROM personas_contactos WHERE persona_id = :pId AND estado = \'ACTIVO\' ORDER BY es_principal DESC');
            $stmtC->execute(['pId' => $row['persona_id']]);
            while ($c = $stmtC->fetch(PDO::FETCH_ASSOC)) {
                if ($c['tipo_contacto'] === 'EMAIL' && $email === 'S/E') {
                    $email = $c['valor'];
                } elseif ($c['tipo_contacto'] === 'TELEFONO' && $telefono === 'S/T') {
                    $telefono = $c['valor'];
                }
            }
        }

        $stmtL = $pdo->prepare(
            'SELECT * FROM recibo_lineas WHERE recibo_id = :id ORDER BY numero_linea ASC'
        );
        $stmtL->execute(['id' => $reciboId]);
        $lineas = $stmtL->fetchAll(PDO::FETCH_ASSOC);

        $tablaHtml = '<table class="tabla-amort-doc"><thead><tr><th style="width:6%;">Item</th><th style="width:18%;">Código Cargo</th><th>Concepto / Detalle</th><th style="width:16%;">Total Cargo</th><th style="width:16%;">Amortizado</th><th style="width:16%;">Saldo Restante</th></tr></thead><tbody>';

        if (empty($lineas)) {
            $tablaHtml .= '<tr><td colspan="6" style="text-align:center; padding:12px; color:#666; font-style:italic;">Sin imputaciones directas a cargos en T0 (Monto recibido como saldo a favor en cuenta folio).</td></tr>';
        } else {
            $item = 1;
            foreach ($lineas as $l) {
                $cargoCod = htmlspecialchars((string) $l['cargo_codigo'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $concepto = htmlspecialchars((string) $l['cargo_concepto'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $totalCargo = number_format((float) $l['cargo_monto_total'], 2, '.', ',');
                $amortizado = number_format((float) $l['monto_aplicado'], 2, '.', ',');
                $saldoRest = number_format((float) $l['cargo_saldo_restante'], 2, '.', ',');

                $tablaHtml .= "<tr><td style=\"text-align:center;\">{$item}</td><td style=\"text-align:center;\"><code>{$cargoCod}</code></td><td>{$concepto}</td><td style=\"text-align:right;\">{$totalCargo}</td><td style=\"text-align:right; font-weight:bold; color:#0d6efd;\">{$amortizado}</td><td style=\"text-align:right;\">{$saldoRest}</td></tr>";
                $item++;
            }
        }
        $tablaHtml .= '</tbody></table>';

        $fechaEmision = new DateTimeImmutable($row['fecha_emision']);
        $codigoFolio = $esBorrador ? 'BORRADOR-REC-' . $reciboId : (string) $row['codigo'];

        $propiedadId = !empty($row['propiedad_id']) ? (int) $row['propiedad_id'] : null;
        $empresa = $this->empresaServicio->obtenerEmpresaParaPropiedad($propiedadId);

        return [
            'documento.folio' => $codigoFolio,
            'emision.fecha' => $fechaEmision->format('d/m/Y'),
            'emision.hora' => $fechaEmision->format('H:i:s'),
            'emision.actor' => 'Caja / Administración',
            'emisor.razon_social' => $empresa ? $empresa->obtenerRazonSocial() : 'Camargo Hostelería S.A.C.',
            'emisor.ruc' => $empresa ? $empresa->obtenerNumeroDocumento() : '20601234567',
            'emisor.nombre_comercial' => $empresa ? ($empresa->obtenerNombreComercial() ?: $empresa->obtenerRazonSocial()) : 'Camargo Hostelería',
            'emisor.direccion_fiscal' => $empresa ? $empresa->obtenerDireccionFiscal() : 'Av. Principal 123, Miraflores, Lima - Perú',
            'cliente.nombre_completo' => (string) $row['persona_nombre_snapshot'],
            'cliente.tipo_documento' => (string) $row['persona_documento_tipo_snapshot'],
            'cliente.numero_documento' => (string) $row['persona_documento_numero_snapshot'],
            'cliente.email' => $email,
            'cliente.telefono' => $telefono,
            'folio.codigo' => (string) $row['folio_codigo'],
            'propiedad.nombre' => (string) $row['propiedad_nombre'],
            'propiedad.direccion' => (string) $row['propiedad_direccion'],
            'unidad.nombre' => (string) $row['unidad_nombre'],
            'contrato.codigo' => !empty($row['arrendamiento_codigo']) ? (string) $row['arrendamiento_codigo'] : '-',
            'reserva.codigo' => !empty($row['reserva_codigo']) ? (string) $row['reserva_codigo'] : '-',
            'pago.codigo' => (string) $row['pago_codigo'],
            'pago.metodo' => (string) $row['metodo_pago_nombre'],
            'pago.medio_detalle' => !empty($row['referencia_cobro']) ? (string) $row['referencia_cobro'] : 'Operación Directa',
            'pago.referencia_operacion' => !empty($row['referencia_cobro']) ? (string) $row['referencia_cobro'] : 'N/A',
            'pago.moneda' => (string) $row['moneda_codigo'],
            'pago.monto_recaudado' => number_format((float) $row['monto_recaudado'], 2, '.', ','),
            'pago.monto_texto' => $this->convertirMontoATexto((string) $row['monto_recaudado'], (string) $row['moneda_codigo']),
            'tabla_amortizaciones' => $tablaHtml,
            'totales.monto_imputado' => number_format((float) $row['monto_imputado'], 2, '.', ','),
            'totales.monto_no_aplicado_pago' => number_format((float) $row['monto_no_aplicado_pago'], 2, '.', ','),
            'totales.saldo_pendiente_folio_despues' => number_format((float) $row['saldo_pendiente_folio_despues'], 2, '.', ','),
            'totales.saldo_favor_folio_despues' => number_format((float) $row['saldo_favor_folio_despues'], 2, '.', ','),
        ];
    }

    private function extraerDatosOrdenCompra(int $ordenId, string $codigoFolio, DateTimeImmutable $fechaActual): array
    {
        $pdo = $this->docRepo->obtenerPdo();
        $stmt = $pdo->prepare(
            'SELECT o.*, p.razon_social, p.numero_documento, p.telefono AS proveedor_telefono, p.email AS proveedor_email, p.direccion AS proveedor_dir,
                    u.nombre AS almacen_nombre, prop.id AS propiedad_id, prop.direccion AS almacen_dir
             FROM compra_ordenes o
             JOIN proveedores p ON p.id = o.proveedor_id
             LEFT JOIN inventario_ubicaciones u ON u.id = o.almacen_entrega_id
             LEFT JOIN propiedades prop ON prop.id = u.propiedad_id
             WHERE o.id = :id'
        );
        $stmt->execute(['id' => $ordenId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new ValidacionExcepcion("Orden de compra [{$ordenId}] no encontrada.");
        }

        $stmtL = $pdo->prepare(
            'SELECT col.*, ia.nombre AS articulo_nombre, ia.codigo_sku
             FROM compra_orden_lineas col
             LEFT JOIN inventario_articulos ia ON ia.id = col.articulo_id
             WHERE col.orden_compra_id = :id
             ORDER BY col.id ASC'
        );
        $stmtL->execute(['id' => $ordenId]);
        $lineas = $stmtL->fetchAll(PDO::FETCH_ASSOC);

        $tablaHtml = '<table class="tabla-lineas-doc"><thead><tr><th>Item</th><th>Tipo</th><th>Descripción / Artículo</th><th>Cantidad</th><th>P. Unitario</th><th>Subtotal</th><th>Impuesto</th><th>Total</th></tr></thead><tbody>';
        $item = 1;
        foreach ($lineas as $l) {
            $desc = $l['tipo_linea'] === 'BIEN' ? "{$l['articulo_nombre']} (SKU: {$l['codigo_sku']})" : (string) $l['descripcion_servicio'];
            $descHtml = htmlspecialchars($desc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $cantHtml = number_format((float) $l['cantidad_pactada'], 2, '.', ',');
            $pUnitHtml = number_format((float) $l['precio_unitario'], 2, '.', ',');
            $subtHtml = number_format((float) $l['subtotal_linea'], 2, '.', ',');
            $impHtml = number_format((float) $l['impuesto_linea'], 2, '.', ',');
            $totHtml = number_format((float) $l['total_linea'], 2, '.', ',');
            $tablaHtml .= "<tr><td style=\"text-align:center;\">{$item}</td><td style=\"text-align:center;\">{$l['tipo_linea']}</td><td>{$descHtml}</td><td style=\"text-align:right;\">{$cantHtml}</td><td style=\"text-align:right;\">{$pUnitHtml}</td><td style=\"text-align:right;\">{$subtHtml}</td><td style=\"text-align:right;\">{$impHtml}</td><td style=\"text-align:right;\">{$totHtml}</td></tr>";
            $item++;
        }
        $tablaHtml .= '</tbody></table>';

        $creadoEn = new DateTimeImmutable($row['creado_en']);
        $entregaEsperada = $row['fecha_entrega_esperada'] ? (new DateTimeImmutable($row['fecha_entrega_esperada']))->format('d/m/Y') : 'Por acordar';

        $propiedadId = !empty($row['propiedad_id']) ? (int) $row['propiedad_id'] : null;
        $empresa = $this->empresaServicio->obtenerEmpresaParaPropiedad($propiedadId);

        return [
            'documento.folio' => $codigoFolio,
            'orden.codigo' => $row['codigo'],
            'orden.fecha' => $creadoEn->format('d/m/Y'),
            'orden.fecha_entrega' => $entregaEsperada,
            'orden.condicion_pago' => str_replace('_', ' ', $row['condicion_pago']),
            'proveedor.razon_social' => $row['razon_social'],
            'proveedor.numero_documento' => $row['numero_documento'],
            'proveedor.contacto' => $row['razon_social'],
            'proveedor.telefono' => $row['proveedor_telefono'] ?? 'S/T',
            'proveedor.email' => $row['proveedor_email'] ?? 'S/E',
            'proveedor.direccion' => $row['proveedor_dir'] ?? 'Dirección no consignada',
            'comprador.razon_social' => $empresa ? $empresa->obtenerRazonSocial() : 'Camargo Hostelería S.A.C.',
            'comprador.ruc' => $empresa ? $empresa->obtenerNumeroDocumento() : '20601234567',
            'comprador.direccion' => $empresa ? $empresa->obtenerDireccionFiscal() : 'Av. Principal 123, Miraflores, Lima - Perú',
            'almacen.nombre' => $row['almacen_nombre'] ?? 'Almacén Central',
            'almacen.direccion' => $row['almacen_dir'] ?? 'Sede Principal',
            'tabla_lineas' => $tablaHtml,
            'totales.moneda' => $row['moneda_codigo'],
            'totales.subtotal' => number_format((float) $row['subtotal'], 2, '.', ','),
            'totales.impuesto' => number_format((float) $row['impuesto_total'], 2, '.', ','),
            'totales.total' => number_format((float) $row['total'], 2, '.', ','),
            'totales.texto' => $this->convertirMontoATexto((string) $row['total'], $row['moneda_codigo']),
            'orden.notas' => !empty($row['notas_comerciales']) ? htmlspecialchars($row['notas_comerciales'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : 'Sin observaciones adicionales',
            'emision.fecha' => $fechaActual->format('d/m/Y'),
        ];
    }

    // =========================================================================
    // 3. DESCARGA Y VERIFICACIÓN DE INTEGRIDAD CRIPTOGRÁFICA (D-079 #7, #8, #9)
    // =========================================================================

    /**
     * Descarga y valida la integridad de un documento emitido sin regeneración silenciosa.
     *
     * @param int $documentoId
     * @param int $actorId
     * @return array{binario_pdf: string, nombre_archivo: string, documento: DocumentoEmitido}
     * @throws DocumentoCorruptoExcepcion si el archivo está ausente o su hash no coincide.
     */
    public function descargarDocumento(int $documentoId, int $actorId): array
    {
        $doc = $this->docRepo->obtenerDocumentoEmitidoPorId($documentoId);
        if (!$doc) {
            throw new PlantillaNoEncontradaExcepcion("Documento emitido ID [{$documentoId}]");
        }

        $rutaAbsoluta = $this->storagePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $doc->obtenerRutaArchivoPdf());

        // 1. Comprobar existencia física
        if (!file_exists($rutaAbsoluta)) {
            $this->docRepo->registrarIncidencia(new DocumentoIncidencia(
                null,
                $documentoId,
                DocumentoIncidencia::TIPO_ARCHIVO_FALTANTE,
                "El archivo físico no fue encontrado en la ruta [{$doc->obtenerRutaArchivoPdf()}].",
                $actorId
            ));
            throw new DocumentoCorruptoExcepcion($doc->obtenerCodigoFolio(), 'ARCHIVO_FALTANTE');
        }

        // 2. Comprobar integridad por hash SHA-256
        $hashReal = hash_file('sha256', $rutaAbsoluta);
        if ($hashReal !== $doc->obtenerHashPdfSha256()) {
            $this->docRepo->registrarIncidencia(new DocumentoIncidencia(
                null,
                $documentoId,
                DocumentoIncidencia::TIPO_HASH_NO_COINCIDE,
                "Discrepancia de integridad: hash esperado [{$doc->obtenerHashPdfSha256()}], hash real [{$hashReal}].",
                $actorId
            ));
            throw new DocumentoCorruptoExcepcion($doc->obtenerCodigoFolio(), 'HASH_NO_COINCIDE');
        }

        $contenido = file_get_contents($rutaAbsoluta);
        if ($contenido === false) {
            $this->docRepo->registrarIncidencia(new DocumentoIncidencia(
                null,
                $documentoId,
                DocumentoIncidencia::TIPO_ERROR_LECTURA,
                "Error al leer los bytes del archivo físico [{$rutaAbsoluta}].",
                $actorId
            ));
            throw new DocumentoCorruptoExcepcion($doc->obtenerCodigoFolio(), 'ERROR_LECTURA');
        }

        return [
            'binario_pdf' => $contenido,
            'nombre_archivo' => "{$doc->obtenerCodigoFolio()}.pdf",
            'documento' => $doc,
        ];
    }

    /**
     * Verifica la integridad física y criptográfica de un documento emitido contra su hash SHA-256 inmutable.
     *
     * @return array{valido: bool, hash_esperado: string, hash_calculado: string, ruta: string, error?: string}
     */
    public function verificarIntegridad(int $documentoId): array
    {
        $doc = $this->docRepo->obtenerDocumentoEmitidoPorId($documentoId);
        if (!$doc) {
            throw new PlantillaNoEncontradaExcepcion("Documento emitido ID [{$documentoId}] no encontrado.");
        }

        $rutaAbsoluta = $this->storagePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $doc->obtenerRutaArchivoPdf());
        if (!file_exists($rutaAbsoluta)) {
            return [
                'valido' => false,
                'hash_esperado' => $doc->obtenerHashPdfSha256(),
                'hash_calculado' => '',
                'ruta' => $rutaAbsoluta,
                'error' => 'ARCHIVO_FALTANTE',
            ];
        }

        $hashReal = hash_file('sha256', $rutaAbsoluta);
        return [
            'valido' => ($hashReal === $doc->obtenerHashPdfSha256()),
            'hash_esperado' => $doc->obtenerHashPdfSha256(),
            'hash_calculado' => (string) $hashReal,
            'ruta' => $rutaAbsoluta,
        ];
    }

    /**
     * Regeneración controlada de un documento corrupto o extraviado desde su snapshot congelado (D-079 #8, #9).
     */
    public function regenerarPdfDesdeSnapshot(int $documentoId, int $actorId, string $motivo): DocumentoEmitido
    {
        $doc = $this->docRepo->obtenerDocumentoEmitidoPorId($documentoId);
        if (!$doc) {
            throw new PlantillaNoEncontradaExcepcion("Documento emitido ID [{$documentoId}]");
        }

        // Renderizar ÚNICAMENTE a partir del snapshot_html congelado
        $renderPdf = $this->generadorPdf->renderizar($doc->obtenerSnapshotHtml(), true);

        // Guardar archivo físico en la ruta original
        $rutaAbsoluta = $this->storagePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $doc->obtenerRutaArchivoPdf());
        $dir = dirname($rutaAbsoluta);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents($rutaAbsoluta, $renderPdf['binario_pdf']);

        // Actualizar metadatos y nuevo hash del PDF regenerado
        $this->docRepo->actualizarRutaYHashPdf(
            $documentoId,
            $doc->obtenerRutaArchivoPdf(),
            $renderPdf['tamano_bytes'],
            $renderPdf['hash_pdf_sha256']
        );

        // Marcar incidencias previas como resueltas
        $stmt = $this->docRepo->obtenerPdo()->prepare(
            'UPDATE documento_incidencias 
             SET resuelto = 1, resuelto_en = NOW(), resolucion_notas = :notas 
             WHERE documento_emitido_id = :docId AND resuelto = 0'
        );
        $stmt->execute([
            'docId' => $documentoId,
            'notas' => "Regenerado exitosamente por actor [{$actorId}]: {$motivo}",
        ]);

        return $this->docRepo->obtenerDocumentoEmitidoPorId($documentoId);
    }

    public function anularDocumento(int $documentoId, int $actorId, string $motivo): bool
    {
        if (trim($motivo) === '') {
            throw new ValidacionExcepcion(['motivo' => 'El motivo de anulación es obligatorio.']);
        }
        return $this->docRepo->anularDocumento($documentoId, $actorId, $motivo);
    }

    // =========================================================================
    // EXTRACCIÓN Y FORMATEO DE DATOS DE ARRENDAMIENTO
    // =========================================================================

    private function obtenerArrendamientoValido(int $arrendamientoId): Arrendamiento
    {
        if ($this->arrendamientoRepo !== null) {
            $arr = $this->arrendamientoRepo->obtenerPorId($arrendamientoId);
            if ($arr !== null) {
                return $arr;
            }
        }

        $repo = new ArrendamientoRepositorio($this->docRepo->obtenerPdo());
        $arr = $repo->obtenerPorId($arrendamientoId);
        if (!$arr) {
            throw new PlantillaNoEncontradaExcepcion("Contrato de arrendamiento ID [{$arrendamientoId}] no existe.");
        }

        return $arr;
    }

    private function extraerDatosContratoArrendamiento(
        Arrendamiento $a,
        string $codigoFolio,
        DateTimeImmutable $fechaActual
    ): array {
        $pdo = $this->docRepo->obtenerPdo();

        // 1. Consultar datos de contacto del arrendatario
        $personaId = $a->obtenerTitularPersonaId();
        $email = '';
        $telefono = '';
        $tipoDoc = 'DNI';

        if ($personaId !== null) {
            $stmtC = $pdo->prepare('SELECT tipo_contacto, valor FROM personas_contactos WHERE persona_id = :pId AND estado = \'ACTIVO\' ORDER BY es_principal DESC');
            $stmtC->execute(['pId' => $personaId]);
            while ($c = $stmtC->fetch(PDO::FETCH_ASSOC)) {
                if ($c['tipo_contacto'] === 'EMAIL' && $email === '') {
                    $email = $c['valor'];
                } elseif ($c['tipo_contacto'] === 'TELEFONO' && $telefono === '') {
                    $telefono = $c['valor'];
                }
            }

            $stmtD = $pdo->prepare(
                'SELECT td.codigo 
                 FROM personas_documentos pd 
                 JOIN tipos_documento td ON td.id = pd.tipo_documento_id 
                 WHERE pd.persona_id = :pId LIMIT 1'
            );
            $stmtD->execute(['pId' => $personaId]);
            $tipoDoc = $stmtD->fetchColumn() ?: 'DNI';
        }

        // 2. Consultar tipología y dirección de la unidad e inmueble
        $stmtU = $pdo->prepare(
            'SELECT u.nombre AS unidad_nombre, tu.nombre AS tipologia_nombre, p.id AS propiedad_id, p.nombre AS propiedad_nombre, p.direccion AS propiedad_direccion
             FROM unidades u
             JOIN tipos_unidad tu ON tu.id = u.tipo_unidad_id
             JOIN propiedades p ON p.id = u.propiedad_id
             WHERE u.id = :uId LIMIT 1'
        );
        $stmtU->execute(['uId' => $a->obtenerUnidadId()]);
        $uInfo = $stmtU->fetch(PDO::FETCH_ASSOC) ?: [];

        // 3. Duración en meses
        $inicio = new DateTimeImmutable($a->obtenerFechaInicio());
        $fin = new DateTimeImmutable($a->obtenerFechaFin());
        $diff = $inicio->diff($fin);
        $meses = ($diff->y * 12) + $diff->m;
        if ($meses === 0 && $diff->days > 0) {
            $meses = 1;
        }

        // 4. Bloque de inventario y dotación de la unidad
        $bloqueDotacion = $this->construirBloqueInventarioDotacion((int) $a->obtenerUnidadId());

        // 5. Empresa emisora / Arrendadora
        $propId = !empty($uInfo['propiedad_id']) ? (int) $uInfo['propiedad_id'] : null;
        $empresa = $this->empresaServicio->obtenerEmpresaParaPropiedad($propId);

        $arrendadorRazonSocial = $empresa ? $empresa->obtenerRazonSocial() : 'Camargo Hostelería S.A.C.';
        $arrendadorRuc = $empresa ? $empresa->obtenerNumeroDocumento() : '20601234567';
        $arrendadorDomicilio = $empresa ? $empresa->obtenerDireccionFiscal() : 'Av. Principal 123, Miraflores, Lima - Perú';
        $arrendadorRep = $empresa && $empresa->obtenerRepresentanteNombreCompleto()
            ? $empresa->obtenerRepresentanteNombreCompleto()
            : 'Orlando Gonzales Camargo';
        $arrendadorDni = $empresa && $empresa->obtenerRepresentanteDocumento()
            ? $empresa->obtenerRepresentanteDocumento()
            : '45879632';

        return [
            // Contrato
            'contrato.numero' => $a->obtenerCodigo(),
            'contrato.fecha_inicio' => $inicio->format('d/m/Y'),
            'contrato.fecha_fin' => $fin->format('d/m/Y'),
            'contrato.duracion_meses' => (string) max(1, $meses),
            'contrato.dia_corte_pago' => (string) $a->obtenerDiaVencimiento(),
            'contrato.canon_monto' => number_format((float) $a->obtenerRentaMensual(), 2, '.', ','),
            'contrato.canon_moneda' => $a->obtenerMonedaCodigo(),
            'contrato.canon_texto' => $this->convertirMontoATexto((string) $a->obtenerRentaMensual(), $a->obtenerMonedaCodigo()),
            'garantia.monto' => number_format((float) $a->obtenerDepositoGarantia(), 2, '.', ','),
            'garantia.texto' => $this->convertirMontoATexto((string) $a->obtenerDepositoGarantia(), $a->obtenerMonedaCodigo()),

            // Arrendador
            'arrendador.razon_social' => $arrendadorRazonSocial,
            'arrendador.ruc' => $arrendadorRuc,
            'arrendador.representante_legal' => $arrendadorRep,
            'arrendador.representante_dni' => $arrendadorDni,
            'arrendador.domicilio_legal' => $arrendadorDomicilio,

            // Arrendatario
            'arrendatario.nombre_completo' => $a->obtenerTitularNombreCompleto() ?: 'Cliente Sin Nombre',
            'arrendatario.tipo_documento' => $tipoDoc,
            'arrendatario.numero_documento' => $a->obtenerTitularNumeroDocumento() ?: 'S/D',
            'arrendatario.email' => $email ?: 'sin_correo@camargohosteleria.pe',
            'arrendatario.telefono' => $telefono ?: 'sin_telefono',

            // Unidad
            'propiedad.nombre' => $uInfo['propiedad_nombre'] ?? $a->obtenerPropiedadNombre(),
            'propiedad.direccion' => $uInfo['propiedad_direccion'] ?? 'Dirección no especificada',
            'unidad.nombre' => $uInfo['unidad_nombre'] ?? $a->obtenerUnidadNombre(),
            'unidad.tipologia' => $uInfo['tipologia_nombre'] ?? 'Departamento',

            // Sistema
            'emision.fecha' => $fechaActual->format('d/m/Y'),
            'documento.folio' => $codigoFolio,

            // Bloques
            'bloque.inventario_dotacion' => $bloqueDotacion,
            'bloque.firmas_partes' => '',
        ];
    }

    private function construirBloqueInventarioDotacion(int $unidadId): string
    {
        $pdo = $this->docRepo->obtenerPdo();

        // Buscar ubicación física asociada a esta unidad
        $stmtUbi = $pdo->prepare('SELECT id FROM inventario_ubicaciones WHERE unidad_id = :uId LIMIT 1');
        $stmtUbi->execute(['uId' => $unidadId]);
        $ubiId = $stmtUbi->fetchColumn();

        if (!$ubiId) {
            return '<p><em>(Sin inventario o dotación registrada para esta unidad).</em></p>';
        }

        // Consultar existencias de lencería y consumibles en la habitación
        $stmtEx = $pdo->prepare(
            'SELECT a.nombre AS art_nombre, a.categoria AS art_cat, e.cantidad_actual, um.simbolo AS um_simbolo
             FROM inventario_existencias e
             JOIN inventario_articulos a ON a.id = e.articulo_id
             JOIN inventario_unidades_medida um ON um.id = a.unidad_medida_id
             WHERE e.ubicacion_id = :ubiId AND e.cantidad_actual > 0
             ORDER BY a.categoria ASC, a.nombre ASC'
        );
        $stmtEx->execute(['ubiId' => $ubiId]);
        $existencias = $stmtEx->fetchAll(PDO::FETCH_ASSOC);

        // Consultar activos asignados a la habitación
        $stmtAct = $pdo->prepare(
            'SELECT a.nombre AS art_nombre, act.codigo_placa, act.marca, act.modelo, act.estado
             FROM inventario_activos act
             JOIN inventario_articulos a ON a.id = act.articulo_id
             WHERE act.ubicacion_id = :ubiId AND act.estado = \'ASIGNADO\'
             ORDER BY a.nombre ASC'
        );
        $stmtAct->execute(['ubiId' => $ubiId]);
        $activos = $stmtAct->fetchAll(PDO::FETCH_ASSOC);

        if (empty($existencias) && empty($activos)) {
            return '<p><em>(La unidad se entrega con dotación estándar según acta física de entrega).</em></p>';
        }

        $html = '<table class="tabla-dotacion">';
        $html .= '<thead><tr><th>Elemento / Bien</th><th>Categoría / Detalle</th><th>Identificador / Serie</th><th>Cantidad / Estado</th></tr></thead>';
        $html .= '<tbody>';

        foreach ($existencias as $ex) {
            $nombre = htmlspecialchars($ex['art_nombre'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $cat = htmlspecialchars($ex['art_cat'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $cant = number_format((float) $ex['cantidad_actual'], 2, '.', '') . ' ' . htmlspecialchars($ex['um_simbolo'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $html .= "<tr><td>{$nombre}</td><td>{$cat}</td><td>Stock físico</td><td>{$cant}</td></tr>";
        }

        foreach ($activos as $act) {
            $nombre = htmlspecialchars($act['art_nombre'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $detalle = htmlspecialchars(($act['marca'] ? $act['marca'] . ' ' : '') . ($act['modelo'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $placa = htmlspecialchars($act['codigo_placa'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $estado = htmlspecialchars($act['estado'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $html .= "<tr><td>{$nombre}</td><td>{$detalle}</td><td>Placa: {$placa}</td><td>{$estado}</td></tr>";
        }

        $html .= '</tbody></table>';
        return $html;
    }

    private function convertirMontoATexto(string $monto, string $moneda): string
    {
        $partes = explode('.', number_format((float) $monto, 2, '.', ''));
        $enteros = (int) ($partes[0] ?? 0);
        $decimales = $partes[1] ?? '00';

        $textoEntero = $this->numeroEnLetras($enteros);
        $nombreMoneda = $moneda === 'USD' ? 'Dólares Americanos' : 'Soles';

        return "{$textoEntero} con {$decimales}/100 {$nombreMoneda}";
    }

    private function numeroEnLetras(int $numero): string
    {
        if ($numero === 0) {
            return 'Cero';
        }

        $unidades = ['', 'un', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve', 'diez',
            'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve'];
        $decenas = ['', '', 'veinte', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'];
        $centenas = ['', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos', 'seiscientos', 'setecientos', 'ochocientos', 'novecientos'];

        if ($numero < 20) {
            return ucfirst($unidades[$numero]);
        }
        if ($numero < 30) {
            return $numero === 20 ? 'Veinte' : 'Veinti' . $unidades[$numero - 20];
        }
        if ($numero < 100) {
            $u = $numero % 10;
            return ucfirst($decenas[(int) ($numero / 10)] . ($u > 0 ? ' y ' . $unidades[$u] : ''));
        }
        if ($numero === 100) {
            return 'Cien';
        }
        if ($numero < 1000) {
            $resto = $numero % 100;
            return ucfirst($centenas[(int) ($numero / 100)] . ($resto > 0 ? ' ' . strtolower($this->numeroEnLetras($resto)) : ''));
        }
        if ($numero < 2000) {
            $resto = $numero % 1000;
            return 'Mil' . ($resto > 0 ? ' ' . strtolower($this->numeroEnLetras($resto)) : '');
        }
        if ($numero < 1000000) {
            $miles = (int) ($numero / 1000);
            $resto = $numero % 1000;
            return ucfirst($this->numeroEnLetras($miles)) . ' mil' . ($resto > 0 ? ' ' . strtolower($this->numeroEnLetras($resto)) : '');
        }

        return (string) $numero;
    }
}
