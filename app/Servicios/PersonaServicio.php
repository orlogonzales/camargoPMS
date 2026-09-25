<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

use CamargoPMS\Excepciones\DocumentoDuplicadoExcepcion;
use CamargoPMS\Excepciones\EntidadNoEncontradaExcepcion;
use CamargoPMS\Excepciones\ValidacionExcepcion;
use CamargoPMS\Modelos\ContactoPersona;
use CamargoPMS\Modelos\DocumentoPersona;
use CamargoPMS\Modelos\Persona;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Repositorios\ContactoPersonaRepositorio;
use CamargoPMS\Repositorios\DocumentoPersonaRepositorio;
use CamargoPMS\Repositorios\PaisRepositorio;
use CamargoPMS\Repositorios\PersonaRepositorio;
use CamargoPMS\Repositorios\TipoDocumentoRepositorio;
use DateTimeImmutable;
use PDO;
use PDOException;
use Throwable;

/**
 * Servicio de dominio para la gestión del maestro de Personas Naturales.
 *
 * Coordina validaciones de negocio, normalización, control de unicidad documental,
 * gestión de documentos y contactos, estados de desactivación lógica y transaccionalidad.
 */
class PersonaServicio
{
    private PDO $pdo;
    private PersonaRepositorio $personaRepo;
    private DocumentoPersonaRepositorio $documentoRepo;
    private ContactoPersonaRepositorio $contactoRepo;
    private PaisRepositorio $paisRepo;
    private TipoDocumentoRepositorio $tipoDocumentoRepo;

    public function __construct(
        ?PDO $pdo = null,
        ?PersonaRepositorio $personaRepo = null,
        ?DocumentoPersonaRepositorio $documentoRepo = null,
        ?ContactoPersonaRepositorio $contactoRepo = null,
        ?PaisRepositorio $paisRepo = null,
        ?TipoDocumentoRepositorio $tipoDocumentoRepo = null
    ) {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->personaRepo = $personaRepo ?? new PersonaRepositorio($this->pdo);
        $this->documentoRepo = $documentoRepo ?? new DocumentoPersonaRepositorio($this->pdo);
        $this->contactoRepo = $contactoRepo ?? new ContactoPersonaRepositorio($this->pdo);
        $this->paisRepo = $paisRepo ?? new PaisRepositorio($this->pdo);
        $this->tipoDocumentoRepo = $tipoDocumentoRepo ?? new TipoDocumentoRepositorio($this->pdo);
    }

    /**
     * Crea de forma atómica una persona natural, opcionalmente con su documento principal y contactos iniciales.
     *
     * @param array<string, mixed> $datosPersona
     * @param array<string, mixed>|null $datosDocumentoPrincipal
     * @param array<int, array<string, mixed>> $contactos
     * @return Persona
     * @throws ValidacionExcepcion Si los datos no superan las invariantes del dominio.
     * @throws DocumentoDuplicadoExcepcion Si el documento ya pertenece a otra persona.
     * @throws Throwable En caso de error interno, garantizando rollback.
     */
    public function crearPersona(
        array $datosPersona,
        ?array $datosDocumentoPrincipal = null,
        array $contactos = []
    ): Persona {
        $personaValidada = $this->validarYNormalizarPersona($datosPersona);

        if ($datosDocumentoPrincipal !== null) {
            $this->validarDocumento($datosDocumentoPrincipal);
        }

        foreach ($contactos as $indice => $contacto) {
            $this->validarContacto($contacto, "contactos[{$indice}]");
        }

        $this->pdo->beginTransaction();

        try {
            $personaId = $this->personaRepo->insertar($personaValidada);

            if ($datosDocumentoPrincipal !== null) {
                $datosDocNormalizados = $this->normalizarDocumento($datosDocumentoPrincipal);
                $datosDocNormalizados['persona_id'] = $personaId;
                $datosDocNormalizados['es_principal'] = true;

                $docModelo = DocumentoPersona::desdeArreglo($datosDocNormalizados);
                $this->documentoRepo->insertar($docModelo);
            }

            foreach ($contactos as $contacto) {
                $contactoNormalizado = $this->normalizarContacto($contacto);
                $contactoNormalizado['persona_id'] = $personaId;

                $contactoModelo = ContactoPersona::desdeArreglo($contactoNormalizado);
                $this->contactoRepo->insertar($contactoModelo);
            }

            $this->pdo->commit();

            return $this->obtenerPersonaOExcepcion($personaId);
        } catch (PDOException $errorSql) {
            $this->pdo->rollBack();

            if ($errorSql->getCode() === '23000') {
                if (
                    str_contains($errorSql->getMessage(), 'uq_documentos_tipo_emisor_numero') ||
                    str_contains($errorSql->getMessage(), 'uq_documentos_tipo_numero')
                ) {
                    $tipoId = (int) ($datosDocumentoPrincipal['tipo_documento_id'] ?? 0);
                    $tipoDoc = $this->tipoDocumentoRepo->buscarPorId($tipoId);
                    $codigo = $tipoDoc ? $tipoDoc->obtenerCodigo() : 'DOCUMENTO';
                    $numero = (string) ($datosDocumentoPrincipal['numero_documento'] ?? '');

                    throw new DocumentoDuplicadoExcepcion($codigo, $numero);
                }
            }

            throw $errorSql;
        } catch (Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }

    }

    /**
     * Agrega un nuevo documento a una persona existente.
     *
     * @param int $personaId
     * @param array<string, mixed> $datosDocumento
     * @return DocumentoPersona
     */
    public function agregarDocumento(int $personaId, array $datosDocumento): DocumentoPersona
    {
        $this->obtenerPersonaOExcepcion($personaId);

        $this->validarDocumento($datosDocumento);
        $datosNormalizados = $this->normalizarDocumento($datosDocumento);
        $datosNormalizados['persona_id'] = $personaId;

        $esPrincipal = (bool) ($datosNormalizados['es_principal'] ?? false);

        $this->pdo->beginTransaction();

        try {
            if ($esPrincipal) {
                $this->documentoRepo->desmarcarPrincipalesDePersona($personaId);
            }

            $docModelo = DocumentoPersona::desdeArreglo($datosNormalizados);
            $docId = $this->documentoRepo->insertar($docModelo);

            $this->pdo->commit();

            $docCreado = $this->documentoRepo->buscarPorId($docId);
            if (!$docCreado) {
                throw new EntidadNoEncontradaExcepcion('DocumentoPersona', $docId);
            }

            return $docCreado;
        } catch (PDOException $errorSql) {
            $this->pdo->rollBack();

            if (
                $errorSql->getCode() === '23000' && (
                    str_contains($errorSql->getMessage(), 'uq_documentos_tipo_emisor_numero') ||
                    str_contains($errorSql->getMessage(), 'uq_documentos_tipo_numero')
                )
            ) {
                $tipoDoc = $this->tipoDocumentoRepo->buscarPorId((int) $datosNormalizados['tipo_documento_id']);
                $codigo = $tipoDoc ? $tipoDoc->obtenerCodigo() : 'DOCUMENTO';
                throw new DocumentoDuplicadoExcepcion($codigo, $datosNormalizados['numero_documento']);
            }

            throw $errorSql;
        } catch (Throwable $error) {

            $this->pdo->rollBack();
            throw $error;
        }
    }

    /**
     * Agrega un medio de contacto a una persona existente.
     *
     * @param int $personaId
     * @param array<string, mixed> $datosContacto
     * @return ContactoPersona
     */
    public function agregarContacto(int $personaId, array $datosContacto): ContactoPersona
    {
        $this->obtenerPersonaOExcepcion($personaId);

        $this->validarContacto($datosContacto);
        $datosNormalizados = $this->normalizarContacto($datosContacto);
        $datosNormalizados['persona_id'] = $personaId;

        $esPrincipal = (bool) ($datosNormalizados['es_principal'] ?? false);
        $tipoContacto = $datosNormalizados['tipo_contacto'];

        $this->pdo->beginTransaction();

        try {
            if ($esPrincipal) {
                $this->contactoRepo->desmarcarPrincipalesDePersonaYTipo($personaId, $tipoContacto);
            }

            $contactoModelo = ContactoPersona::desdeArreglo($datosNormalizados);
            $contactoId = $this->contactoRepo->insertar($contactoModelo);

            $this->pdo->commit();

            $contactoCreado = $this->contactoRepo->buscarPorId($contactoId);
            if (!$contactoCreado) {
                throw new EntidadNoEncontradaExcepcion('ContactoPersona', $contactoId);
            }

            return $contactoCreado;
        } catch (Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }

    /**
     * Actualiza los datos generales de una persona natural.
     *
     * @param int $personaId
     * @param array<string, mixed> $datosPersona
     * @return Persona
     */
    public function actualizarPersona(int $personaId, array $datosPersona): Persona
    {
        $personaActual = $this->obtenerPersonaOExcepcion($personaId);
        $datosActualizados = array_merge($personaActual->aArreglo(), $datosPersona);
        $datosActualizados['id'] = $personaId;

        $personaValidada = $this->validarYNormalizarPersona($datosActualizados);

        $this->personaRepo->actualizar($personaValidada);

        return $this->obtenerPersonaOExcepcion($personaId);
    }

    /**
     * Desactiva lógicamente una persona natural (soft delete).
     *
     * @param int $personaId
     * @return bool
     */
    public function desactivarPersona(int $personaId): bool
    {
        $this->obtenerPersonaOExcepcion($personaId);
        return $this->personaRepo->desactivar($personaId);
    }

    /**
     * Desactiva lógicamente un documento específico.
     *
     * @param int $documentoId
     * @return bool
     */
    public function desactivarDocumento(int $documentoId): bool
    {
        $doc = $this->documentoRepo->buscarPorId($documentoId);
        if (!$doc) {
            throw new EntidadNoEncontradaExcepcion('DocumentoPersona', $documentoId);
        }

        return $this->documentoRepo->desactivar($documentoId);
    }

    /**
     * Desactiva lógicamente un contacto específico.
     *
     * @param int $contactoId
     * @return bool
     */
    public function desactivarContacto(int $contactoId): bool
    {
        $contacto = $this->contactoRepo->buscarPorId($contactoId);
        if (!$contacto) {
            throw new EntidadNoEncontradaExcepcion('ContactoPersona', $contactoId);
        }

        return $this->contactoRepo->desactivar($contactoId);
    }

    /**
     * Busca una persona por su ID.
     *
     * @param int $personaId
     * @return Persona|null
     */
    public function buscarPorId(int $personaId): ?Persona
    {
        return $this->personaRepo->buscarPorId($personaId);
    }

    /**
     * Busca una persona por documento.
     *
     * @param string $codigoTipo 'DNI', 'PASAPORTE', 'CE'.
     * @param string $numero
     * @return Persona|null
     */
    public function buscarPorDocumento(string $codigoTipo, string $numero): ?Persona
    {
        return $this->personaRepo->buscarPorDocumento($codigoTipo, $numero);
    }

    /**
     * Obtiene una persona por ID o lanza EntidadNoEncontradaExcepcion.
     *
     * @param int $personaId
     * @return Persona
     */
    public function obtenerPersonaOExcepcion(int $personaId): Persona
    {
        $persona = $this->personaRepo->buscarPorId($personaId);
        if (!$persona) {
            throw new EntidadNoEncontradaExcepcion('Persona', $personaId);
        }

        return $persona;
    }

    // =========================================================================
    // Métodos privados de validación y normalización
    // =========================================================================

    /**
     * @param array<string, mixed> $datos
     * @return Persona
     */
    private function validarYNormalizarPersona(array $datos): Persona
    {
        $errores = [];

        $nombres = trim((string) ($datos['nombres'] ?? ''));
        if ($nombres === '') {
            $errores['nombres'] = 'Los nombres son obligatorios.';
        }

        $paterno = isset($datos['apellido_paterno']) && trim((string) $datos['apellido_paterno']) !== ''
            ? trim((string) $datos['apellido_paterno'])
            : null;

        $materno = isset($datos['apellido_materno']) && trim((string) $datos['apellido_materno']) !== ''
            ? trim((string) $datos['apellido_materno'])
            : null;

        $fechaNac = null;
        if (isset($datos['fecha_nacimiento']) && trim((string) $datos['fecha_nacimiento']) !== '') {
            $fechaTexto = trim((string) $datos['fecha_nacimiento']);
            $fechaObj = DateTimeImmutable::createFromFormat('Y-m-d', $fechaTexto);

            if (!$fechaObj || $fechaObj->format('Y-m-d') !== $fechaTexto) {
                $errores['fecha_nacimiento'] = 'La fecha de nacimiento debe tener formato YYYY-MM-DD.';
            } else {
                $hoy = new DateTimeImmutable('today');
                if ($fechaObj > $hoy) {
                    $errores['fecha_nacimiento'] = 'La fecha de nacimiento no puede ser una fecha futura.';
                } else {
                    $fechaNac = $fechaTexto;
                }
            }
        }

        $paisId = null;
        if (isset($datos['pais_nacionalidad_id']) && $datos['pais_nacionalidad_id'] !== null && $datos['pais_nacionalidad_id'] !== '') {
            $paisId = (int) $datos['pais_nacionalidad_id'];
            $pais = $this->paisRepo->buscarPorId($paisId);
            if (!$pais || !$pais->esActivo()) {
                $errores['pais_nacionalidad_id'] = 'El país de nacionalidad especificado no es válido o está inactivo.';
            }
        }

        if (!empty($errores)) {
            throw new ValidacionExcepcion('Datos de persona inválidos.', $errores);
        }

        return new Persona(
            isset($datos['id']) ? (int) $datos['id'] : null,
            $nombres,
            $paterno,
            $materno,
            $fechaNac,
            $paisId,
            isset($datos['direccion']) && trim((string) $datos['direccion']) !== '' ? trim((string) $datos['direccion']) : null,
            (string) ($datos['estado'] ?? 'ACTIVO')
        );
    }

    /**
     * @param array<string, mixed> $datos
     * @param string $prefijoCampo
     * @return void
     */
    private function validarDocumento(array $datos, string $prefijoCampo = 'documento'): void
    {
        $errores = [];

        $tipoId = (int) ($datos['tipo_documento_id'] ?? 0);
        $tipoDoc = $this->tipoDocumentoRepo->buscarPorId($tipoId);

        if (!$tipoDoc || !$tipoDoc->esActivo()) {
            $errores["{$prefijoCampo}.tipo_documento_id"] = 'El tipo de documento especificado no es válido o está inactivo.';
            throw new ValidacionExcepcion('Tipo de documento no válido.', $errores);
        }

        $numero = strtoupper(trim((string) ($datos['numero_documento'] ?? '')));
        if ($numero === '') {
            $errores["{$prefijoCampo}.numero_documento"] = 'El número de documento es obligatorio.';
        } else {
            $paisEmisorId = isset($datos['pais_emisor_id']) && $datos['pais_emisor_id'] !== null && $datos['pais_emisor_id'] !== ''
                ? (int) $datos['pais_emisor_id']
                : null;

            if ($tipoDoc->tienePaisFijo()) {
                $paisFijoId = $tipoDoc->obtenerPaisFijoId();
                if ($paisEmisorId === null) {
                    // Resuelve automáticamente al país fijo de emisión del documento (ej. DNI/CE -> Perú)
                    $paisEmisorId = $paisFijoId;
                } elseif ($paisEmisorId !== $paisFijoId) {
                    $paisFijo = $this->paisRepo->buscarPorId($paisFijoId);
                    $nombrePais = $paisFijo ? $paisFijo->obtenerNombre() : 'el país autorizado';
                    $errores["{$prefijoCampo}.pais_emisor_id"] = "El tipo de documento {$tipoDoc->obtenerCodigo()} únicamente puede ser emitido por {$nombrePais}.";
                }
            } elseif ($tipoDoc->requierePaisEmisor()) {
                if ($paisEmisorId === null || $paisEmisorId <= 0) {
                    $errores["{$prefijoCampo}.pais_emisor_id"] = "El tipo de documento {$tipoDoc->obtenerCodigo()} requiere especificar obligatoriamente un país emisor válido.";
                } else {
                    $paisValido = $this->paisRepo->buscarPorId($paisEmisorId);
                    if (!$paisValido || !$paisValido->esActivo()) {
                        $errores["{$prefijoCampo}.pais_emisor_id"] = 'El país emisor especificado no es válido o está inactivo.';
                    }
                }
            }

            if ($tipoDoc->obtenerFormatoRegex() !== null) {
                if (!preg_match('/' . $tipoDoc->obtenerFormatoRegex() . '/', $numero)) {
                    $errores["{$prefijoCampo}.numero_documento"] = "El número no cumple con el formato requerido para {$tipoDoc->obtenerNombre()}.";
                }
            }

            // Comprobación preventiva de duplicados considerando la jurisdicción real obligatoria
            if ($paisEmisorId !== null && empty($errores)) {
                $existente = $this->documentoRepo->buscarPorTipoPaisYNumero($tipoId, $paisEmisorId, $numero);
                if ($existente !== null) {
                    $personaActualId = isset($datos['persona_id']) ? (int) $datos['persona_id'] : null;
                    if ($personaActualId === null || $existente->obtenerPersonaId() !== $personaActualId) {
                        throw new DocumentoDuplicadoExcepcion($tipoDoc->obtenerCodigo(), $numero);
                    }
                }
            }
        }

        if (!empty($errores)) {
            throw new ValidacionExcepcion('Datos de documento inválidos.', $errores);
        }
    }

    /**
     * @param array<string, mixed> $datos
     * @return array<string, mixed>
     */
    private function normalizarDocumento(array $datos): array
    {
        $paisEmisorId = isset($datos['pais_emisor_id']) && $datos['pais_emisor_id'] !== null && $datos['pais_emisor_id'] !== ''
            ? (int) $datos['pais_emisor_id']
            : null;

        $tipoDoc = $this->tipoDocumentoRepo->buscarPorId((int) $datos['tipo_documento_id']);
        if ($tipoDoc !== null && $tipoDoc->tienePaisFijo() && $paisEmisorId === null) {
            $paisEmisorId = $tipoDoc->obtenerPaisFijoId();
        }

        return [
            'tipo_documento_id' => (int) $datos['tipo_documento_id'],
            'numero_documento' => strtoupper(trim((string) $datos['numero_documento'])),
            'pais_emisor_id' => (int) $paisEmisorId,
            'es_principal' => (bool) ($datos['es_principal'] ?? false),
            'fecha_emision' => isset($datos['fecha_emision']) && trim((string) $datos['fecha_emision']) !== '' ? trim((string) $datos['fecha_emision']) : null,
            'fecha_vencimiento' => isset($datos['fecha_vencimiento']) && trim((string) $datos['fecha_vencimiento']) !== '' ? trim((string) $datos['fecha_vencimiento']) : null,
            'estado' => strtoupper(trim((string) ($datos['estado'] ?? 'ACTIVO'))),
        ];
    }

    /**

     * @param array<string, mixed> $datos
     * @param string $prefijoCampo
     * @return void
     */
    private function validarContacto(array $datos, string $prefijoCampo = 'contacto'): void
    {
        $errores = [];

        $tipo = strtoupper(trim((string) ($datos['tipo_contacto'] ?? '')));
        if (!in_array($tipo, ['TELEFONO', 'EMAIL'], true)) {
            $errores["{$prefijoCampo}.tipo_contacto"] = "El tipo de contacto debe ser 'TELEFONO' o 'EMAIL'.";
        }

        $valor = trim((string) ($datos['valor'] ?? ''));
        if ($valor === '') {
            $errores["{$prefijoCampo}.valor"] = 'El valor del medio de contacto es obligatorio.';
        } elseif ($tipo === 'EMAIL') {
            if (!filter_var($valor, FILTER_VALIDATE_EMAIL)) {
                $errores["{$prefijoCampo}.valor"] = 'El formato del correo electrónico es inválido.';
            }
        } elseif ($tipo === 'TELEFONO') {
            if (!preg_match('/^[0-9+\s\-().]{6,25}$/', $valor)) {
                $errores["{$prefijoCampo}.valor"] = 'El formato del número telefónico es inválido.';
            }
        }

        if (!empty($errores)) {
            throw new ValidacionExcepcion('Datos de contacto inválidos.', $errores);
        }
    }

    /**
     * @param array<string, mixed> $datos
     * @return array<string, mixed>
     */
    private function normalizarContacto(array $datos): array
    {
        $tipo = strtoupper(trim((string) $datos['tipo_contacto']));
        $valor = trim((string) $datos['valor']);

        if ($tipo === 'EMAIL') {
            $valor = strtolower($valor);
        }

        return [
            'tipo_contacto' => $tipo,
            'valor' => $valor,
            'es_whatsapp' => (bool) ($datos['es_whatsapp'] ?? false),
            'es_principal' => (bool) ($datos['es_principal'] ?? false),
            'estado' => strtoupper(trim((string) ($datos['estado'] ?? 'ACTIVO'))),
        ];
    }
}
