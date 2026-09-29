<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

use CamargoPMS\Excepciones\ConflictoEmpresaExcepcion;
use CamargoPMS\Excepciones\EmpresaNoEncontradaExcepcion;
use CamargoPMS\Excepciones\ValidacionEmpresaExcepcion;
use CamargoPMS\Modelos\AccionAuditoria;
use CamargoPMS\Modelos\Empresa;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Repositorios\EmpresaRepositorio;
use CamargoPMS\Repositorios\PersonaRepositorio;
use CamargoPMS\Repositorios\PropiedadRepositorio;
use PDO;
use Throwable;

/**
 * Servicio de dominio central para la gestión del Maestro de Empresas y Emisores Legales (EMPRESA-1).
 */
class EmpresaServicio
{
    private PDO $pdo;
    private EmpresaRepositorio $empresaRepo;
    private PersonaRepositorio $personaRepo;
    private PropiedadRepositorio $propiedadRepo;
    private AuditoriaServicio $auditoriaServicio;
    private string $storagePath;

    public function __construct(
        ?PDO $pdo = null,
        ?EmpresaRepositorio $empresaRepo = null,
        ?PersonaRepositorio $personaRepo = null,
        ?PropiedadRepositorio $propiedadRepo = null,
        ?AuditoriaServicio $auditoriaServicio = null,
        ?string $storagePath = null
    ) {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->empresaRepo = $empresaRepo ?? new EmpresaRepositorio($this->pdo);
        $this->personaRepo = $personaRepo ?? new PersonaRepositorio($this->pdo);
        $this->propiedadRepo = $propiedadRepo ?? new PropiedadRepositorio($this->pdo);
        $this->auditoriaServicio = $auditoriaServicio ?? new AuditoriaServicio($this->pdo);
        $this->storagePath = $storagePath ?? (dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage');
    }

    public function obtenerRepositorio(): EmpresaRepositorio
    {
        return $this->empresaRepo;
    }

    public function obtenerEmpresa(int $id): ?Empresa
    {
        return $this->empresaRepo->buscarPorId($id);
    }

    public function obtenerEmpresaParaPropiedad(?int $propiedadId): ?Empresa
    {
        if ($propiedadId !== null && $propiedadId > 0) {
            $prop = $this->propiedadRepo->buscarPorId($propiedadId);
            if ($prop !== null) {
                // Consultar directamente columna empresa_id
                $stmt = $this->pdo->prepare('SELECT empresa_id FROM propiedades WHERE id = :id');
                $stmt->execute(['id' => $propiedadId]);
                $empresaId = $stmt->fetchColumn();

                if ($empresaId) {
                    $empresa = $this->empresaRepo->buscarPorId((int) $empresaId);
                    if ($empresa !== null && $empresa->estaActivo()) {
                        return $empresa;
                    }
                }
            }
        }

        // Fallback: empresa principal activa
        return $this->empresaRepo->buscarPrincipal();
    }

    /**
     * @param array<string, mixed> $filtros
     * @return array<int, Empresa>
     */
    public function listarEmpresas(array $filtros = [], int $limite = 50, int $offset = 0): array
    {
        return $this->empresaRepo->listar($filtros, $limite, $offset);
    }

    /**
     * Valida estructuralmente un RUC peruano de 11 dígitos mediante el algoritmo de Módulo 11 oficial de SUNAT.
     */
    public static function validarRucEstructural(string $ruc): bool
    {
        $ruc = trim($ruc);
        if (!preg_match('/^[0-9]{11}$/', $ruc)) {
            return false;
        }

        $prefijo = substr($ruc, 0, 2);
        if (!in_array($prefijo, ['10', '15', '16', '17', '20'], true)) {
            return false;
        }

        $factores = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $suma = 0;

        for ($i = 0; $i < 10; $i++) {
            $suma += (int) $ruc[$i] * $factores[$i];
        }

        $residuo = $suma % 11;
        $digitoCalculado = 11 - $residuo;

        if ($digitoCalculado === 10) {
            $digitoCalculado = 0;
        } elseif ($digitoCalculado === 11) {
            $digitoCalculado = 1;
        }

        return (int) $ruc[10] === $digitoCalculado;
    }

    /**
     * Registra una nueva empresa en el maestro corporativo.
     *
     * @param array<string, mixed> $datos
     * @param array<string, mixed>|null $archivoLogo
     */
    public function crearEmpresa(array $datos, int $actorId, ?array $archivoLogo = null): Empresa
    {
        $this->validarDatosEmpresa($datos, null);

        $codigo = strtoupper(trim((string) $datos['codigo']));
        $numeroDoc = trim((string) $datos['numero_documento']);

        if ($this->empresaRepo->buscarPorCodigo($codigo) !== null) {
            throw new ConflictoEmpresaExcepcion("Ya existe una empresa registrada con el código [{$codigo}].");
        }

        if ($this->empresaRepo->buscarPorNumeroDocumento($numeroDoc) !== null) {
            throw new ConflictoEmpresaExcepcion("Ya existe una empresa registrada con el RUC / Documento [{$numeroDoc}].");
        }

        $repPersonaId = !empty($datos['representante_persona_id']) ? (int) $datos['representante_persona_id'] : null;
        if ($repPersonaId !== null) {
            $persona = $this->personaRepo->buscarPorId($repPersonaId);
            if ($persona === null) {
                throw new ValidacionEmpresaExcepcion("La persona natural designada como representante [{$repPersonaId}] no existe.");
            }
        }

        $esPrincipal = !empty($datos['es_principal']) && (bool) $datos['es_principal'];

        // Manejo de logo si se subió
        $logoUrl = null;
        if ($archivoLogo !== null && !empty($archivoLogo['tmp_name'])) {
            $logoUrl = $this->procesarArchivoLogo($archivoLogo);
        }

        $empresa = new Empresa(
            null,
            $codigo,
            (int) ($datos['tipo_documento_id'] ?? 4), // 4 = RUC según migración
            $numeroDoc,
            trim((string) $datos['razon_social']),
            !empty($datos['nombre_comercial']) ? trim((string) $datos['nombre_comercial']) : null,
            trim((string) $datos['direccion_fiscal']),
            (int) ($datos['pais_id'] ?? 1),
            !empty($datos['departamento']) ? trim((string) $datos['departamento']) : null,
            !empty($datos['provincia']) ? trim((string) $datos['provincia']) : null,
            !empty($datos['distrito']) ? trim((string) $datos['distrito']) : null,
            !empty($datos['ubigeo']) ? trim((string) $datos['ubigeo']) : null,
            !empty($datos['telefono']) ? trim((string) $datos['telefono']) : null,
            !empty($datos['email']) ? strtolower(trim((string) $datos['email'])) : null,
            !empty($datos['sitio_web']) ? trim((string) $datos['sitio_web']) : null,
            $logoUrl,
            $repPersonaId,
            !empty($datos['representante_cargo']) ? trim((string) $datos['representante_cargo']) : 'Gerente General',
            !empty($datos['representante_poder_partida']) ? trim((string) $datos['representante_poder_partida']) : null,
            $esPrincipal,
            $datos['estado'] ?? Empresa::ESTADO_ACTIVO,
            !empty($datos['observaciones']) ? trim((string) $datos['observaciones']) : null
        );

        $this->pdo->beginTransaction();
        try {
            if ($esPrincipal) {
                $this->empresaRepo->desmarcarPrincipalesExcepto(null);
            }

            $id = $this->empresaRepo->crear($empresa);

            // Si se suministraron propiedades para vincular
            if (!empty($datos['propiedad_ids']) && is_array($datos['propiedad_ids'])) {
                foreach ($datos['propiedad_ids'] as $pId) {
                    $this->empresaRepo->asignarPropiedad($id, (int) $pId);
                }
            }

            // Auditoría
            $this->auditoriaServicio->registrar(
                accion: 'CREAR',
                modulo: 'empresa',
                entidad: 'empresas',
                entidadId: $id,
                descripcion: "Creación de empresa operadora [{$codigo}] — {$empresa->obtenerRazonSocial()}",
                valoresAnteriores: null,
                valoresNuevos: $empresa->aArreglo(),
                actor: $actorId,
                pdoTransaccional: $this->pdo
            );

            $this->pdo->commit();
            return $this->empresaRepo->buscarPorId($id);
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            if ($logoUrl) {
                @unlink($this->storagePath . DIRECTORY_SEPARATOR . $logoUrl);
            }
            throw $e;
        }
    }

    /**
     * Modifica una empresa existente.
     *
     * @param array<string, mixed> $datos
     * @param array<string, mixed>|null $archivoLogo
     */
    public function actualizarEmpresa(int $id, array $datos, int $actorId, ?array $archivoLogo = null): Empresa
    {
        $actual = $this->empresaRepo->buscarPorId($id);
        if ($actual === null) {
            throw new EmpresaNoEncontradaExcepcion("La empresa [{$id}] no existe.");
        }

        $datosCompletos = array_merge([
            'codigo' => $actual->obtenerCodigo(),
            'tipo_documento_id' => $actual->obtenerTipoDocumentoId(),
            'numero_documento' => $actual->obtenerNumeroDocumento(),
            'razon_social' => $actual->obtenerRazonSocial(),
            'nombre_comercial' => $actual->obtenerNombreComercial(),
            'direccion_fiscal' => $actual->obtenerDireccionFiscal(),
            'pais_id' => $actual->obtenerPaisId(),
            'departamento' => $actual->obtenerDepartamento(),
            'provincia' => $actual->obtenerProvincia(),
            'distrito' => $actual->obtenerDistrito(),
            'ubigeo' => $actual->obtenerUbigeo(),
            'telefono' => $actual->obtenerTelefono(),
            'email' => $actual->obtenerEmail(),
            'sitio_web' => $actual->obtenerSitioWeb(),
            'representante_persona_id' => $actual->obtenerRepresentantePersonaId(),
            'representante_cargo' => $actual->obtenerRepresentanteCargo(),
            'representante_poder_partida' => $actual->obtenerRepresentantePoderPartida(),
            'es_principal' => $actual->esPrincipal(),
            'estado' => $actual->obtenerEstado(),
            'observaciones' => $actual->obtenerObservaciones(),
        ], $datos);

        $this->validarDatosEmpresa($datosCompletos, $id);

        $codigo = strtoupper(trim((string) $datosCompletos['codigo']));
        $numeroDoc = trim((string) $datosCompletos['numero_documento']);

        $existenteCod = $this->empresaRepo->buscarPorCodigo($codigo);
        if ($existenteCod !== null && $existenteCod->obtenerId() !== $id) {
            throw new ConflictoEmpresaExcepcion("Ya existe otra empresa registrada con el código [{$codigo}].");
        }

        $existenteDoc = $this->empresaRepo->buscarPorNumeroDocumento($numeroDoc);
        if ($existenteDoc !== null && $existenteDoc->obtenerId() !== $id) {
            throw new ConflictoEmpresaExcepcion("Ya existe otra empresa registrada con el RUC / Documento [{$numeroDoc}].");
        }

        $repPersonaId = !empty($datosCompletos['representante_persona_id']) ? (int) $datosCompletos['representante_persona_id'] : null;
        if ($repPersonaId !== null) {
            $persona = $this->personaRepo->buscarPorId($repPersonaId);
            if ($persona === null) {
                throw new ValidacionEmpresaExcepcion("La persona natural designada como representante [{$repPersonaId}] no existe.");
            }
        }

        $esPrincipal = isset($datosCompletos['es_principal']) ? (bool) $datosCompletos['es_principal'] : $actual->esPrincipal();

        // Manejo de logo si se subió reemplazo
        $logoUrl = $actual->obtenerLogoUrl();
        if ($archivoLogo !== null && !empty($archivoLogo['tmp_name'])) {
            $nuevoLogoUrl = $this->procesarArchivoLogo($archivoLogo);
            if ($logoUrl && $logoUrl !== $nuevoLogoUrl) {
                @unlink($this->storagePath . DIRECTORY_SEPARATOR . $logoUrl);
            }
            $logoUrl = $nuevoLogoUrl;
        }

        $empresaActualizada = new Empresa(
            $id,
            $codigo,
            (int) ($datosCompletos['tipo_documento_id'] ?? $actual->obtenerTipoDocumentoId()),
            $numeroDoc,
            trim((string) $datosCompletos['razon_social']),
            isset($datosCompletos['nombre_comercial']) ? (trim((string) $datosCompletos['nombre_comercial']) ?: null) : $actual->obtenerNombreComercial(),
            trim((string) $datosCompletos['direccion_fiscal']),
            (int) ($datosCompletos['pais_id'] ?? $actual->obtenerPaisId()),
            isset($datosCompletos['departamento']) ? (trim((string) $datosCompletos['departamento']) ?: null) : $actual->obtenerDepartamento(),
            isset($datosCompletos['provincia']) ? (trim((string) $datosCompletos['provincia']) ?: null) : $actual->obtenerProvincia(),
            isset($datosCompletos['distrito']) ? (trim((string) $datosCompletos['distrito']) ?: null) : $actual->obtenerDistrito(),
            isset($datosCompletos['ubigeo']) ? (trim((string) $datosCompletos['ubigeo']) ?: null) : $actual->obtenerUbigeo(),
            isset($datosCompletos['telefono']) ? (trim((string) $datosCompletos['telefono']) ?: null) : $actual->obtenerTelefono(),
            isset($datosCompletos['email']) ? (strtolower(trim((string) $datosCompletos['email'])) ?: null) : $actual->obtenerEmail(),
            isset($datosCompletos['sitio_web']) ? (trim((string) $datosCompletos['sitio_web']) ?: null) : $actual->obtenerSitioWeb(),
            $logoUrl,
            $repPersonaId,
            isset($datosCompletos['representante_cargo']) ? trim((string) $datosCompletos['representante_cargo']) : $actual->obtenerRepresentanteCargo(),
            isset($datosCompletos['representante_poder_partida']) ? (trim((string) $datosCompletos['representante_poder_partida']) ?: null) : $actual->obtenerRepresentantePoderPartida(),
            $esPrincipal,
            $datosCompletos['estado'] ?? $actual->obtenerEstado(),
            isset($datosCompletos['observaciones']) ? (trim((string) $datosCompletos['observaciones']) ?: null) : $actual->obtenerObservaciones(),
            $actual->obtenerCreadoEn()
        );

        $this->pdo->beginTransaction();
        try {
            if ($esPrincipal && !$actual->esPrincipal()) {
                $this->empresaRepo->desmarcarPrincipalesExcepto($id);
            }

            $this->empresaRepo->actualizar($empresaActualizada);

            // Reemplazo atómico de propiedades si se enviaron explícitamente
            if (isset($datos['propiedad_ids']) && is_array($datos['propiedad_ids'])) {
                $actualesProps = $this->empresaRepo->obtenerPropiedadesVinculadas($id);
                $actualesIds = array_column($actualesProps, 'id');
                $nuevosIds = array_map('intval', $datos['propiedad_ids']);

                // Desasignar las que ya no están
                foreach ($actualesIds as $pId) {
                    if (!in_array($pId, $nuevosIds, true)) {
                        $this->empresaRepo->desasignarPropiedad($pId);
                    }
                }

                // Asignar nuevas
                foreach ($nuevosIds as $pId) {
                    if (!in_array($pId, $actualesIds, true)) {
                        $this->empresaRepo->asignarPropiedad($id, $pId);
                    }
                }
            }

            // Auditoría
            $cambios = [];
            if ($actual->obtenerRepresentantePersonaId() !== $repPersonaId) {
                $cambios[] = 'cambio_representante';
            }
            if ($actual->obtenerLogoUrl() !== $logoUrl) {
                $cambios[] = 'cambio_logo';
            }

            $accionTipo = !empty($cambios) ? 'EMPRESA_ACTUALIZADA_' . strtoupper(implode('_', $cambios)) : 'EMPRESA_ACTUALIZADA';

            $this->auditoriaServicio->registrar(
                accion: 'EDITAR',
                modulo: 'empresa',
                entidad: 'empresas',
                entidadId: $id,
                descripcion: "Actualización de empresa operadora [{$codigo}] ({$accionTipo})",
                valoresAnteriores: $actual->aArreglo(),
                valoresNuevos: $empresaActualizada->aArreglo(),
                actor: $actorId,
                pdoTransaccional: $this->pdo
            );

            $this->pdo->commit();
            return $this->empresaRepo->buscarPorId($id);
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Cambia el estado operativo de una empresa (ACTIVO <-> INACTIVO).
     */
    public function cambiarEstado(int $id, string $nuevoEstado, int $actorId, string $motivo = ''): Empresa
    {
        $empresa = $this->empresaRepo->buscarPorId($id);
        if ($empresa === null) {
            throw new EmpresaNoEncontradaExcepcion("La empresa [{$id}] no existe.");
        }

        $nuevoEstado = strtoupper(trim($nuevoEstado));
        if (!in_array($nuevoEstado, [Empresa::ESTADO_ACTIVO, Empresa::ESTADO_INACTIVO], true)) {
            throw new ValidacionEmpresaExcepcion("Estado '{$nuevoEstado}' no reconocido.");
        }

        if ($empresa->obtenerEstado() === $nuevoEstado) {
            return $empresa;
        }

        if ($nuevoEstado === Empresa::ESTADO_INACTIVO) {
            if ($empresa->esPrincipal()) {
                throw new ConflictoEmpresaExcepcion('No se puede desactivar una empresa designada como principal. Asigne el rol principal a otra empresa activa antes de desactivarla.');
            }

            // Verificar si tiene propiedades activas asociadas
            $props = $this->empresaRepo->obtenerPropiedadesVinculadas($id);
            $propsActivas = array_filter($props, fn($p) => $p['estado'] === 'ACTIVO');
            if (!empty($propsActivas)) {
                $nombres = implode(', ', array_column($propsActivas, 'nombre'));
                throw new ConflictoEmpresaExcepcion("No se puede desactivar la empresa porque tiene propiedades activas vinculadas: {$nombres}. Reasigne las propiedades previamente.");
            }
        }

        $this->empresaRepo->cambiarEstado($id, $nuevoEstado);

        $this->auditoriaServicio->registrar(
            accion: 'CAMBIAR_ESTADO',
            modulo: 'empresa',
            entidad: 'empresas',
            entidadId: $id,
            descripcion: "Cambio de estado de empresa [{$empresa->obtenerCodigo()}] a {$nuevoEstado}",
            valoresAnteriores: ['estado' => $empresa->obtenerEstado()],
            valoresNuevos: ['estado' => $nuevoEstado, 'motivo' => $motivo],
            actor: $actorId
        );

        return $this->empresaRepo->buscarPorId($id);
    }

    /**
     * Vincula un lote de propiedades a una empresa operadora sincronizando la relación.
     *
     * @param array<int, int> $propiedadIds
     */
    public function asignarPropiedades(int $empresaId, array $propiedadIds, int $actorId): void
    {
        $empresa = $this->empresaRepo->buscarPorId($empresaId);
        if ($empresa === null) {
            throw new EmpresaNoEncontradaExcepcion("La empresa [{$empresaId}] no existe.");
        }

        $this->pdo->beginTransaction();
        try {
            $actuales = $this->empresaRepo->obtenerPropiedadesPorEmpresaId($empresaId);
            $nuevosIds = array_map('intval', $propiedadIds);

            foreach ($actuales as $act) {
                $pid = (int) $act['id'];
                if (!in_array($pid, $nuevosIds, true)) {
                    $this->empresaRepo->desasignarPropiedad($pid);
                }
            }

            foreach ($nuevosIds as $pid) {
                if ($pid > 0) {
                    $this->empresaRepo->asignarPropiedad($empresaId, $pid);
                }
            }

            $this->auditoriaServicio->registrar(
                accion: 'ASIGNAR_PROPIEDADES',
                modulo: 'empresa',
                entidad: 'empresas',
                entidadId: $empresaId,
                descripcion: "Sincronización de " . count($nuevosIds) . " propiedades a empresa [{$empresa->obtenerCodigo()}]",
                valoresAnteriores: null,
                valoresNuevos: ['propiedad_ids' => $nuevosIds],
                actor: $actorId,
                pdoTransaccional: $this->pdo
            );

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Elimina físicamente una empresa si no mantiene dependencias ni es la principal.
     */
    public function eliminarEmpresa(int $id, int $actorId): bool
    {
        $empresa = $this->empresaRepo->buscarPorId($id);
        if ($empresa === null) {
            throw new EmpresaNoEncontradaExcepcion("La empresa [{$id}] no existe.");
        }

        if ($empresa->esPrincipal()) {
            throw new ConflictoEmpresaExcepcion('No se puede eliminar la empresa designada como principal.');
        }

        if ($this->empresaRepo->tieneDependencias($id)) {
            throw new ConflictoEmpresaExcepcion('No se puede eliminar la empresa porque mantiene propiedades vinculadas.');
        }

        $this->pdo->beginTransaction();
        try {
            $this->auditoriaServicio->registrar(
                accion: 'ELIMINAR',
                modulo: 'empresa',
                entidad: 'empresas',
                entidadId: $id,
                descripcion: "Eliminación de empresa [{$empresa->obtenerCodigo()}]",
                valoresAnteriores: $empresa->aArreglo(),
                valoresNuevos: null,
                actor: $actorId,
                pdoTransaccional: $this->pdo
            );

            $resultado = $this->empresaRepo->eliminar($id);
            $this->pdo->commit();
            return $resultado;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Validador público de logotipos.
     *
     * @param array<string, mixed> $archivo
     */
    public function procesarLogotipo(array $archivo): string
    {
        return $this->procesarArchivoLogo($archivo);
    }

    /**
     * Resuelve la ruta física absoluta de un logotipo de empresa.
     */
    public function obtenerRutaFisicaLogo(int $empresaId): ?string
    {
        $empresa = $this->empresaRepo->buscarPorId($empresaId);
        if ($empresa === null || !$empresa->obtenerLogoUrl()) {
            return null;
        }

        $ruta = $this->storagePath . DIRECTORY_SEPARATOR . $empresa->obtenerLogoUrl();
        return file_exists($ruta) ? $ruta : null;
    }

    /**
     * Procesa, valida y persiste un archivo de logotipo subido.
     *
     * @param array<string, mixed> $archivo Matriz $_FILES
     */
    private function procesarArchivoLogo(array $archivo): string
    {
        if (!isset($archivo['tmp_name']) || !is_uploaded_file($archivo['tmp_name'])) {
            // Si es un entorno de prueba donde no se usa is_uploaded_file nativo
            if (empty($archivo['tmp_name']) || !file_exists($archivo['tmp_name'])) {
                $nombreOriginal = strtolower((string) ($archivo['name'] ?? ''));
                $ext = pathinfo($nombreOriginal, PATHINFO_EXTENSION);
                if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'svg'], true)) {
                    throw new ValidacionEmpresaExcepcion("Tipo de archivo no permitido [{$ext}]. Se aceptan únicamente imágenes PNG, JPG, WEBP o SVG.");
                }
                if ((int) ($archivo['size'] ?? 0) > 2 * 1024 * 1024) {
                    throw new ValidacionEmpresaExcepcion('El logotipo supera el tamaño máximo permitido de 2 MB.');
                }
                throw new ValidacionEmpresaExcepcion('No se pudo verificar el archivo de logotipo recibido.');
            }
        }

        // 1. Tamaño máximo 2MB
        $maxBytes = 2 * 1024 * 1024;
        $tamano = (int) ($archivo['size'] ?? filesize($archivo['tmp_name']));
        if ($tamano > $maxBytes) {
            throw new ValidacionEmpresaExcepcion('El logotipo supera el tamaño máximo permitido de 2 MB.');
        }

        // 2. MIME type seguro
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $archivo['tmp_name']);
        finfo_close($finfo);

        $mimesPermitidos = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
        ];

        if (!isset($mimesPermitidos[$mime])) {
            throw new ValidacionEmpresaExcepcion("Tipo de archivo no permitido ({$mime}). Se aceptan únicamente imágenes PNG, JPG, WEBP o SVG.");
        }

        $extension = $mimesPermitidos[$mime];
        $dirLogos = $this->storagePath . DIRECTORY_SEPARATOR . 'logos';
        if (!is_dir($dirLogos)) {
            mkdir($dirLogos, 0755, true);
        }

        $nombreSeguro = 'logo_' . bin2hex(random_bytes(8)) . '_' . time() . '.' . $extension;
        $destino = $dirLogos . DIRECTORY_SEPARATOR . $nombreSeguro;

        if (!move_uploaded_file($archivo['tmp_name'], $destino)) {
            // Soporte fallback para testing CLI
            if (!copy($archivo['tmp_name'], $destino)) {
                throw new ValidacionEmpresaExcepcion('Ocurrió un error al guardar el logotipo en el almacenamiento.');
            }
        }

        return 'logos/' . $nombreSeguro;
    }

    /**
     * Validaciones semánticas y de integridad de datos de entrada.
     *
     * @param array<string, mixed> $datos
     */
    private function validarDatosEmpresa(array $datos, ?int $actualId): void
    {
        $errores = [];

        // Código
        $codigo = strtoupper(trim((string) ($datos['codigo'] ?? '')));
        if ($codigo === '') {
            $errores['codigo'] = 'El código de la empresa es obligatorio.';
        } elseif (!preg_match('/^[A-Z0-9\-_]{2,50}$/', $codigo)) {
            $errores['codigo'] = 'El código debe contener entre 2 y 50 caracteres alfanuméricos, guiones o guiones bajos.';
        }

        // Razón social
        $razonSocial = trim((string) ($datos['razon_social'] ?? ''));
        if ($razonSocial === '') {
            $errores['razon_social'] = 'La razón social legal es obligatoria.';
        } elseif (mb_strlen($razonSocial) < 3) {
            $errores['razon_social'] = 'La razón social debe tener al menos 3 caracteres.';
        }

        // RUC / Documento
        $numDoc = trim((string) ($datos['numero_documento'] ?? ''));
        if ($numDoc === '') {
            $errores['numero_documento'] = 'El RUC / número de documento es obligatorio.';
        } else {
            if (!self::validarRucEstructural($numDoc)) {
                $errores['numero_documento'] = 'El RUC ingresado no cumple con el formato estructural o dígito verificador oficial.';
            }
        }

        // Domicilio fiscal
        $direccion = trim((string) ($datos['direccion_fiscal'] ?? ''));
        if ($direccion === '') {
            $errores['direccion_fiscal'] = 'El domicilio fiscal es obligatorio.';
        } elseif (mb_strlen($direccion) < 5) {
            $errores['direccion_fiscal'] = 'El domicilio fiscal debe tener al menos 5 caracteres.';
        }

        // Email opcional
        if (!empty($datos['email'])) {
            $email = trim((string) $datos['email']);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errores['email'] = 'El correo electrónico ingresado no tiene un formato válido.';
            }
        }

        // Ubigeo opcional
        if (!empty($datos['ubigeo'])) {
            $ubigeo = trim((string) $datos['ubigeo']);
            if (!preg_match('/^[0-9]{6}$/', $ubigeo)) {
                $errores['ubigeo'] = 'El código de ubigeo debe estar compuesto por exactamente 6 dígitos numéricos.';
            }
        }

        if (!empty($errores)) {
            $mensaje = implode(' ', $errores);
            throw new ValidacionEmpresaExcepcion($mensaje);
        }
    }
}
