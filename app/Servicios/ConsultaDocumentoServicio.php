<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

use CamargoPMS\Adaptadores\ApisPeruAdaptador;
use CamargoPMS\Repositorios\EmpresaRepositorio;
use CamargoPMS\Repositorios\PersonaRepositorio;
use Throwable;

/**
 * Servicio soberano de consulta y normalización de documentos de identidad (Local-First + APIsPERU).
 *
 * Principios vinculantes (APISPERU-1A / APISPERU-1B):
 * 1. LOCAL-FIRST: Consulta primero el repositorio local (PersonaRepositorio / EmpresaRepositorio).
 *    Si la entidad ya existe en Camargo PMS, devuelve origen 'LOCAL' sin consumir cuota externa.
 * 2. EXTERNAL FALLBACK: Si no existe localmente y es DNI o RUC, consulta APIsPERU con origen 'APISPERU'.
 * 3. EXCLUSIÓN Y CONTROL: CE y Pasaporte no disponen de API externa y devuelven origen 'MANUAL'.
 * 4. NO DESTRUCTIVO: La normalización provee los datos necesarios sin sobreescribir información existente.
 * 5. CERO INVENCIÓN DE DATOS: DNI en APIsPERU solo provee nombres y apellidos; no inventa género,
 *    dirección ni fecha de nacimiento.
 */
class ConsultaDocumentoServicio
{
    private PersonaRepositorio $personaRepo;
    private EmpresaRepositorio $empresaRepo;
    private ApisPeruAdaptador $apisPeruAdaptador;

    public function __construct(
        ?PersonaRepositorio $personaRepo = null,
        ?EmpresaRepositorio $empresaRepo = null,
        ?ApisPeruAdaptador $apisPeruAdaptador = null
    ) {
        $this->personaRepo = $personaRepo ?? new PersonaRepositorio();
        $this->empresaRepo = $empresaRepo ?? new EmpresaRepositorio();
        $this->apisPeruAdaptador = $apisPeruAdaptador ?? new ApisPeruAdaptador();
    }

    /**
     * Consulta un documento de identidad aplicando la política Local-First y fallback externo.
     *
     * @param string $tipo Tipo de documento: 'DNI', 'RUC', 'CE', 'PASAPORTE'
     * @param string $numero Número de documento a consultar
     * @return array{
     *     success: bool,
     *     origen: 'LOCAL'|'APISPERU'|'MANUAL'|'ERROR',
     *     encontrado: bool,
     *     mensaje: string,
     *     datos: array<string, mixed>|null
     * }
     */
    public function consultar(string $tipo, string $numero): array
    {
        $tipoNormalizado = strtoupper(trim($tipo));
        $numeroLimpio = trim($numero);

        if ($tipoNormalizado === '' || $numeroLimpio === '') {
            return [
                'success' => false,
                'origen' => 'ERROR',
                'encontrado' => false,
                'mensaje' => 'El tipo y número de documento son obligatorios.',
                'datos' => null,
            ];
        }

        // 1. Tipos de documento que requieren registro estrictamente manual (sin API externa)
        if (in_array($tipoNormalizado, ['CE', 'PASAPORTE', 'OTRO'], true)) {
            return [
                'success' => true,
                'origen' => 'MANUAL',
                'encontrado' => false,
                'mensaje' => 'El documento de tipo ' . $tipoNormalizado . ' no dispone de consulta automatizada. Ingrese los datos manualmente.',
                'datos' => null,
            ];
        }

        // 2. Consulta de DNI (Persona Natural)
        if ($tipoNormalizado === 'DNI') {
            return $this->consultarDni($numeroLimpio);
        }

        // 3. Consulta de RUC (Empresa o Contribuyente Jurídico/Natural)
        if ($tipoNormalizado === 'RUC') {
            return $this->consultarRuc($numeroLimpio);
        }

        return [
            'success' => false,
            'origen' => 'ERROR',
            'encontrado' => false,
            'mensaje' => 'Tipo de documento no admitido para consulta: ' . $tipoNormalizado . '.',
            'datos' => null,
        ];
    }

    /**
     * Procesa la consulta de DNI con política Local-First.
     *
     * @param string $dni
     * @return array<string, mixed>
     */
    private function consultarDni(string $dni): array
    {
        if (!preg_match('/^\d{8}$/', $dni)) {
            return [
                'success' => false,
                'origen' => 'ERROR',
                'encontrado' => false,
                'mensaje' => 'El DNI debe contener exactamente 8 dígitos numéricos.',
                'datos' => null,
            ];
        }

        // PASO 1: Consulta local en base de datos de Camargo PMS
        $personaLocal = $this->personaRepo->buscarPorDocumento('DNI', $dni, true);
        if ($personaLocal !== null) {
            return [
                'success' => true,
                'origen' => 'LOCAL',
                'encontrado' => true,
                'mensaje' => 'Persona encontrada en la base de datos local de Camargo PMS.',
                'datos' => [
                    'id' => $personaLocal->obtenerId(),
                    'tipo_documento' => 'DNI',
                    'numero_documento' => $dni,
                    'nombres' => $personaLocal->obtenerNombres(),
                    'apellido_paterno' => $personaLocal->obtenerApellidoPaterno(),
                    'apellido_materno' => $personaLocal->obtenerApellidoMaterno(),
                    'nombre_completo' => $personaLocal->obtenerNombreCompleto(),
                    'genero' => $personaLocal->obtenerGenero(),
                    'fecha_nacimiento' => $personaLocal->obtenerFechaNacimiento(),
                    'direccion' => $personaLocal->obtenerDireccion(),
                    'ubigeo' => $personaLocal->obtenerUbigeo(),
                    'departamento' => $personaLocal->obtenerDepartamentoNombre(),
                    'provincia' => $personaLocal->obtenerProvinciaNombre(),
                    'distrito' => $personaLocal->obtenerDistritoNombre(),
                    'estado' => $personaLocal->obtenerEstado(),
                ],
            ];
        }

        // PASO 2: Fallback a APIsPERU si no existe localmente
        if (!$this->apisPeruAdaptador->estaConfigurado()) {
            return [
                'success' => false,
                'origen' => 'APISPERU',
                'encontrado' => false,
                'mensaje' => 'El servicio de consulta externa APIsPERU no se encuentra configurado (token ausente). Ingrese los datos manualmente.',
                'datos' => null,
            ];
        }

        try {
            $resultado = $this->apisPeruAdaptador->consultarDni($dni);

            $nombres = trim((string) ($resultado['nombres'] ?? ''));
            $paterno = trim((string) ($resultado['apellidoPaterno'] ?? ''));
            $materno = trim((string) ($resultado['apellidoMaterno'] ?? ''));
            $nombreCompleto = trim($nombres . ' ' . $paterno . ' ' . $materno);

            return [
                'success' => true,
                'origen' => 'APISPERU',
                'encontrado' => true,
                'mensaje' => 'Datos de identidad obtenidos exitosamente desde APIsPERU (Reniec).',
                'datos' => [
                    'tipo_documento' => 'DNI',
                    'numero_documento' => (string) ($resultado['dni'] ?? $dni),
                    'nombres' => $nombres,
                    'apellido_paterno' => $paterno,
                    'apellido_materno' => $materno,
                    'nombre_completo' => $nombreCompleto,
                    'codigo_verificacion' => $resultado['codVerifica'] ?? null,
                ],
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'origen' => 'APISPERU',
                'encontrado' => false,
                'mensaje' => $e->getMessage(),
                'datos' => null,
            ];
        }
    }

    /**
     * Procesa la consulta de RUC con política Local-First.
     *
     * @param string $ruc
     * @return array<string, mixed>
     */
    private function consultarRuc(string $ruc): array
    {
        if (!preg_match('/^\d{11}$/', $ruc)) {
            return [
                'success' => false,
                'origen' => 'ERROR',
                'encontrado' => false,
                'mensaje' => 'El RUC debe contener exactamente 11 dígitos numéricos.',
                'datos' => null,
            ];
        }

        // PASO 1: Consulta local en base de datos de Camargo PMS
        $empresaLocal = $this->empresaRepo->buscarPorNumeroDocumento($ruc);
        if ($empresaLocal !== null) {
            return [
                'success' => true,
                'origen' => 'LOCAL',
                'encontrado' => true,
                'mensaje' => 'Empresa encontrada en la base de datos local de Camargo PMS.',
                'datos' => [
                    'id' => $empresaLocal->obtenerId(),
                    'codigo' => $empresaLocal->obtenerCodigo(),
                    'tipo_documento' => 'RUC',
                    'numero_documento' => $empresaLocal->obtenerNumeroDocumento(),
                    'razon_social' => $empresaLocal->obtenerRazonSocial(),
                    'nombre_comercial' => $empresaLocal->obtenerNombreComercial(),
                    'direccion' => $empresaLocal->obtenerDireccionFiscal(),
                    'departamento' => $empresaLocal->obtenerDepartamento(),
                    'provincia' => $empresaLocal->obtenerProvincia(),
                    'distrito' => $empresaLocal->obtenerDistrito(),
                    'ubigeo' => $empresaLocal->obtenerUbigeo(),
                    'telefono' => $empresaLocal->obtenerTelefono(),
                    'email' => $empresaLocal->obtenerEmail(),
                    'estado' => $empresaLocal->obtenerEstado(),
                ],
            ];
        }

        // PASO 2: Fallback a APIsPERU si no existe localmente
        if (!$this->apisPeruAdaptador->estaConfigurado()) {
            return [
                'success' => false,
                'origen' => 'APISPERU',
                'encontrado' => false,
                'mensaje' => 'El servicio de consulta externa APIsPERU no se encuentra configurado (token ausente). Ingrese los datos manualmente.',
                'datos' => null,
            ];
        }

        try {
            $resultado = $this->apisPeruAdaptador->consultarRuc($ruc);

            $telefonos = [];
            if (isset($resultado['telefonos']) && is_array($resultado['telefonos'])) {
                $telefonos = array_values(array_filter($resultado['telefonos'], fn($t) => is_string($t) && trim($t) !== ''));
            }

            return [
                'success' => true,
                'origen' => 'APISPERU',
                'encontrado' => true,
                'mensaje' => 'Datos tributarios obtenidos exitosamente desde APIsPERU (SUNAT).',
                'datos' => [
                    'tipo_documento' => 'RUC',
                    'numero_documento' => (string) ($resultado['ruc'] ?? $ruc),
                    'razon_social' => trim((string) ($resultado['razonSocial'] ?? '')),
                    'nombre_comercial' => trim((string) ($resultado['nombreComercial'] ?? '')) ?: null,
                    'direccion' => trim((string) ($resultado['direccion'] ?? '')),
                    'departamento' => trim((string) ($resultado['departamento'] ?? '')),
                    'provincia' => trim((string) ($resultado['provincia'] ?? '')),
                    'distrito' => trim((string) ($resultado['distrito'] ?? '')),
                    'ubigeo' => trim((string) ($resultado['ubigeo'] ?? '')),
                    'telefonos' => $telefonos,
                    'estado_sunat' => trim((string) ($resultado['estado'] ?? '')),
                    'condicion_sunat' => trim((string) ($resultado['condicion'] ?? '')),
                ],
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'origen' => 'APISPERU',
                'encontrado' => false,
                'mensaje' => $e->getMessage(),
                'datos' => null,
            ];
        }
    }
}
