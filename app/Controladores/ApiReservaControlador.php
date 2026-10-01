<?php

declare(strict_types=1);

namespace CamargoPMS\Controladores;

use CamargoPMS\Excepciones\ConflictoDisponibilidadExcepcion;
use CamargoPMS\Excepciones\CotizacionInvalidaExcepcion;
use CamargoPMS\Excepciones\EntidadNoEncontradaExcepcion;
use CamargoPMS\Excepciones\IntervaloInvalidoExcepcion;
use CamargoPMS\Excepciones\ValidacionExcepcion;
use CamargoPMS\Modelos\ActorAuditoria;
use CamargoPMS\Modelos\Reserva;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\ContextoHttpApi;
use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Nucleo\RespuestaApi;
use CamargoPMS\Repositorios\PersonaRepositorio;
use CamargoPMS\Repositorios\ReservaRepositorio;
use CamargoPMS\Repositorios\TipoDocumentoRepositorio;
use CamargoPMS\Servicios\ClienteServicio;
use CamargoPMS\Servicios\CotizacionServicio;
use CamargoPMS\Servicios\DisponibilidadServicio;
use CamargoPMS\Servicios\PersonaServicio;
use CamargoPMS\Servicios\ReservaServicio;
use PDO;
use Throwable;

/**
 * Controlador de Endpoints de Negocio para la API RESTful v1 (WORDPRESS-1D).
 *
 * Expone de forma soberana y segura:
 * 1. GET  /api/v1/disponibilidad: Consulta de disponibilidad física e inventario.
 * 2. POST /api/v1/cotizaciones: Cálculo de tarifas noche a noche sin bloqueo.
 * 3. POST /api/v1/reservas: Creación de retención temporal (hold) con bloqueo atómico e idempotencia.
 * 4. GET  /api/v1/reservas/{codigo}: Consulta pública segura del estado de una reserva (privacidad PII).
 *
 * Principios vinculantes:
 * - REUTILIZACIÓN ESTRICTA: El backend es la única fuente de verdad; no hay lógica duplicada.
 * - SOBERANÍA MONETARIA: Precios y disponibilidad calculados exclusivamente por servicios PMS (D-069).
 * - CONCURRENCIA ACID: Prevención estricta de doble reserva mediante inventario diario sparse (D-067).
 * - PROTECCIÓN PII: Cero fuga de identificadores internos ni datos personales ajenos.
 */
class ApiReservaControlador
{
    private PDO $pdo;
    private DisponibilidadServicio $disponibilidadServicio;
    private CotizacionServicio $cotizacionServicio;
    private ReservaServicio $reservaServicio;
    private ReservaRepositorio $reservaRepo;
    private PersonaRepositorio $personaRepo;
    private PersonaServicio $personaServicio;
    private TipoDocumentoRepositorio $tipoDocRepo;

    /** @var string|null Soporte para simulación de payload JSON en pruebas unitarias y CLI */
    public static ?string $cuerpoPrueba = null;

    public function __construct(
        ?PDO $pdo = null,
        ?DisponibilidadServicio $disponibilidadServicio = null,
        ?CotizacionServicio $cotizacionServicio = null,
        ?ReservaServicio $reservaServicio = null,
        ?ReservaRepositorio $reservaRepo = null,
        ?PersonaRepositorio $personaRepo = null,
        ?PersonaServicio $personaServicio = null,
        ?TipoDocumentoRepositorio $tipoDocRepo = null
    ) {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->disponibilidadServicio = $disponibilidadServicio ?? new DisponibilidadServicio($this->pdo);
        $this->cotizacionServicio = $cotizacionServicio ?? new CotizacionServicio($this->pdo, $this->disponibilidadServicio);
        $this->reservaServicio = $reservaServicio ?? new ReservaServicio(
            $this->pdo,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $this->disponibilidadServicio
        );
        $this->reservaRepo = $reservaRepo ?? new ReservaRepositorio($this->pdo);
        $this->personaRepo = $personaRepo ?? new PersonaRepositorio($this->pdo);
        $this->personaServicio = $personaServicio ?? new PersonaServicio($this->pdo, $this->personaRepo);
        $this->tipoDocRepo = $tipoDocRepo ?? new TipoDocumentoRepositorio($this->pdo);
    }

    /**
     * Responde a preflights OPTIONS de CORS con HTTP 204 No Content.
     */
    public function preflight(): Respuesta
    {
        return RespuestaApi::sinContenido();
    }

    /**
     * Consulta soberana de disponibilidad e inventario hotelero.
     * GET /api/v1/disponibilidad
     *
     * Requiere scope: disponibilidad.leer
     */
    public function disponibilidad(): Respuesta
    {
        $fechaEntrada = trim((string) ($_GET['fecha_entrada'] ?? $_GET['checkin'] ?? ''));
        $fechaSalida = trim((string) ($_GET['fecha_salida'] ?? $_GET['checkout'] ?? ''));

        if ($fechaEntrada === '' || $fechaSalida === '') {
            return RespuestaApi::error(
                'Debe especificar los parámetros obligatorios fecha_entrada y fecha_salida en formato YYYY-MM-DD.',
                'PARAMETROS_REQUERIDOS',
                422,
                ['parametros_faltantes' => array_values(array_filter([
                    $fechaEntrada === '' ? 'fecha_entrada' : null,
                    $fechaSalida === '' ? 'fecha_salida' : null,
                ]))]
            );
        }

        $propiedadId = isset($_GET['propiedad_id']) && is_numeric($_GET['propiedad_id']) && (int) $_GET['propiedad_id'] > 0
            ? (int) $_GET['propiedad_id']
            : null;

        $tipoUnidadId = isset($_GET['tipo_unidad_id']) && is_numeric($_GET['tipo_unidad_id']) && (int) $_GET['tipo_unidad_id'] > 0
            ? (int) $_GET['tipo_unidad_id']
            : null;

        $soloDisponibles = true;
        if (isset($_GET['solo_disponibles'])) {
            $val = strtolower(trim((string) $_GET['solo_disponibles']));
            $soloDisponibles = !in_array($val, ['0', 'false', 'no'], true);
        }

        $huespedes = isset($_GET['huespedes']) && is_numeric($_GET['huespedes'])
            ? max(1, (int) $_GET['huespedes'])
            : 1;

        try {
            $resultado = $this->disponibilidadServicio->consultarDisponibilidad(
                $fechaEntrada,
                $fechaSalida,
                $propiedadId,
                $tipoUnidadId,
                $soloDisponibles
            );

            // Filtrar por capacidad si se solicita para N huéspedes
            if ($huespedes > 1) {
                $unidadesAptas = [];
                foreach ($resultado['unidades'] as $u) {
                    if ((int) ($u['capacidad_personas'] ?? 1) >= $huespedes) {
                        $unidadesAptas[] = $u;
                    }
                }
                $resultado['unidades'] = array_values($unidadesAptas);
                $resultado['huespedes_solicitados'] = $huespedes;
            }

            return RespuestaApi::exito(
                datos: $resultado,
                codigoHttp: 200,
                codigoDominio: 'DISPONIBILIDAD_CONSULTADA'
            );
        } catch (IntervaloInvalidoExcepcion $e) {
            return RespuestaApi::error($e->getMessage(), 'INTERVALO_INVALIDO', 422);
        } catch (ValidacionExcepcion $e) {
            return RespuestaApi::error($e->getMessage(), 'VALIDACION_FALLIDA', 422, $e->obtenerErrores());
        } catch (Throwable $e) {
            return RespuestaApi::error('Error interno al consultar la disponibilidad: ' . $e->getMessage(), 'ERROR_INTERNO', 500);
        }
    }

    /**
     * Cotización soberana noche a noche y emisión de snapshot firmado (WORDPRESS-1B / 1D).
     * POST /api/v1/cotizaciones
     *
     * IMPORTANTE: No bloquea inventario.
     * Requiere scope: cotizacion.crear
     */
    public function cotizar(): Respuesta
    {
        $cuerpo = self::$cuerpoPrueba ?? (string) file_get_contents('php://input');
        $datos = json_decode($cuerpo !== '' ? $cuerpo : '{}', true);

        if (!is_array($datos)) {
            return RespuestaApi::error('El cuerpo de la petición debe ser un objeto JSON válido.', 'JSON_INVALIDO', 400);
        }

        $fechaEntrada = trim((string) ($datos['fecha_entrada'] ?? $datos['checkin'] ?? ''));
        $fechaSalida = trim((string) ($datos['fecha_salida'] ?? $datos['checkout'] ?? ''));
        $propiedadId = isset($datos['propiedad_id']) && is_numeric($datos['propiedad_id']) ? (int) $datos['propiedad_id'] : 0;
        $huespedes = isset($datos['huespedes']) && is_numeric($datos['huespedes']) ? max(1, (int) $datos['huespedes']) : 1;
        $tipoUnidadId = isset($datos['tipo_unidad_id']) && is_numeric($datos['tipo_unidad_id']) && (int) $datos['tipo_unidad_id'] > 0
            ? (int) $datos['tipo_unidad_id']
            : null;
        $unidadId = isset($datos['unidad_id']) && is_numeric($datos['unidad_id']) && (int) $datos['unidad_id'] > 0
            ? (int) $datos['unidad_id']
            : null;

        if ($fechaEntrada === '' || $fechaSalida === '') {
            return RespuestaApi::error(
                'Debe proporcionar fecha_entrada y fecha_salida para cotizar la estancia.',
                'PARAMETROS_REQUERIDOS',
                422
            );
        }

        if ($propiedadId <= 0) {
            return RespuestaApi::error(
                'Debe proporcionar un propiedad_id válido.',
                'PROPIEDAD_REQUERIDA',
                422
            );
        }

        if ($tipoUnidadId === null && $unidadId === null) {
            return RespuestaApi::error(
                'Debe especificar tipo_unidad_id o unidad_id para cotizar.',
                'UNIDAD_O_TIPO_REQUERIDO',
                422
            );
        }

        try {
            $resumen = $this->cotizacionServicio->cotizarEstancia([
                'fecha_entrada' => $fechaEntrada,
                'fecha_salida' => $fechaSalida,
                'propiedad_id' => $propiedadId,
                'huespedes' => $huespedes,
                'tipo_unidad_id' => $tipoUnidadId,
                'unidad_id' => $unidadId,
            ]);

            return RespuestaApi::exito(
                datos: $resumen->aArreglo(),
                codigoHttp: 200,
                codigoDominio: 'COTIZACION_GENERADA'
            );
        } catch (IntervaloInvalidoExcepcion $e) {
            return RespuestaApi::error($e->getMessage(), 'INTERVALO_INVALIDO', 422);
        } catch (ConflictoDisponibilidadExcepcion $e) {
            return RespuestaApi::error($e->getMessage(), 'CONFLICTO_DISPONIBILIDAD', 409);
        } catch (ValidacionExcepcion $e) {
            return RespuestaApi::error($e->getMessage(), 'VALIDACION_FALLIDA', 422, $e->obtenerErrores());
        } catch (Throwable $e) {
            return RespuestaApi::error('Error al generar la cotización: ' . $e->getMessage(), 'ERROR_COTIZACION', 500);
        }
    }

    /**
     * Creación de pre-reserva comercial en hold (PENDIENTE) con bloqueo atómico de inventario (WORDPRESS-1D).
     * POST /api/v1/reservas
     *
     * Requiere:
     * - Cabecera obligatoria Idempotency-Key
     * - Bearer Token con scope: reservas.hold
     */
    public function crearReserva(): Respuesta
    {
        $cuerpo = self::$cuerpoPrueba ?? (string) file_get_contents('php://input');
        $datos = json_decode($cuerpo !== '' ? $cuerpo : '{}', true);

        if (!is_array($datos)) {
            return RespuestaApi::error('El cuerpo de la petición debe ser un objeto JSON válido.', 'JSON_INVALIDO', 400);
        }

        $tokenCotizacion = trim((string) ($datos['token_cotizacion'] ?? ''));
        if ($tokenCotizacion === '') {
            return RespuestaApi::error("El campo 'token_cotizacion' es obligatorio.", 'TOKEN_COTIZACION_REQUERIDO', 422);
        }

        $titular = $datos['titular'] ?? null;
        if (!is_array($titular) || empty($titular)) {
            return RespuestaApi::error("Los datos del huésped titular ('titular') son obligatorios.", 'TITULAR_REQUERIDO', 422);
        }

        $observaciones = isset($datos['observaciones']) && trim((string) $datos['observaciones']) !== ''
            ? trim((string) $datos['observaciones'])
            : null;

        $duracionHold = isset($datos['duracion_hold_minutos']) && is_numeric($datos['duracion_hold_minutos'])
            ? max(5, min(60, (int) $datos['duracion_hold_minutos']))
            : 15;

        // 1. Resolver o asegurar persona titular
        try {
            $personaId = $this->resolverOAsegurarPersonaTitular($titular);
        } catch (ValidacionExcepcion $e) {
            return RespuestaApi::error($e->getMessage(), 'TITULAR_INVALIDO', 422, $e->obtenerErrores());
        } catch (Throwable $e) {
            return RespuestaApi::error('Error al registrar los datos del titular: ' . $e->getMessage(), 'ERROR_TITULAR', 500);
        }

        // 2. Obtener identidad del actor técnico autenticado
        $contexto = ContextoHttpApi::obtenerAutenticacion();
        $actorTecnico = $contexto?->obtenerActor();

        // 3. Crear reserva en hold desde la cotización validada
        try {
            $reserva = $this->reservaServicio->crearReservaDesdeCotizacion(
                cotizacion: $tokenCotizacion,
                personaTitularId: $personaId,
                datosAdicionales: [
                    'canal' => Reserva::CANAL_WEB,
                    'origen' => 'WEB_API',
                    'duracion_hold_minutos' => $duracionHold,
                    'observaciones' => $observaciones,
                    'actor' => $actorTecnico,
                    'actor_id' => $actorTecnico?->obtenerId(),
                ]
            );

            // Cargar unidades para formatear la respuesta
            $unidadesDetalle = [];
            foreach ($reserva->obtenerUnidades() as $u) {
                $unidadesDetalle[] = [
                    'unidad_id' => $u->obtenerUnidadId(),
                    'unidad_codigo' => $u->obtenerUnidadCodigo(),
                    'unidad_nombre' => $u->obtenerUnidadNombre(),
                    'tipo_unidad' => $u->obtenerTipoUnidadNombre(),
                    'precio_unitario_noche' => $u->obtenerPrecioUnitarioNoche(),
                    'noches' => $u->obtenerNoches(),
                    'total' => $u->obtenerTotal(),
                ];
            }

            $datosRespuesta = [
                'codigo' => $reserva->obtenerCodigo(),
                'estado' => $reserva->obtenerEstado(),
                'expira_en' => $reserva->obtenerExpiraEn(),
                'fecha_entrada' => $reserva->obtenerFechaEntrada(),
                'fecha_salida' => $reserva->obtenerFechaSalida(),
                'noches' => $reserva->obtenerNoches(),
                'moneda' => $reserva->obtenerMonedaCodigo(),
                'subtotal' => $reserva->obtenerSubtotal(),
                'impuesto_total' => $reserva->obtenerImpuestoTotal(),
                'total' => $reserva->obtenerTotal(),
                'titular' => [
                    'nombre' => $this->enmascararNombre($reserva->obtenerTitularNombreCompleto()),
                    'email_enmascarado' => $this->enmascararEmail($reserva->obtenerTitularEmail()),
                    'telefono_enmascarado' => $this->enmascararTelefono($reserva->obtenerTitularTelefono()),
                ],
                'unidades' => $unidadesDetalle,
            ];

            return RespuestaApi::exito(
                datos: $datosRespuesta,
                codigoHttp: 201,
                codigoDominio: 'RESERVA_HOLD_CREADA'
            );
        } catch (CotizacionInvalidaExcepcion $e) {
            return RespuestaApi::error($e->getMessage(), 'COTIZACION_INVALIDA', 422);
        } catch (ConflictoDisponibilidadExcepcion $e) {
            return RespuestaApi::error($e->getMessage(), 'CONFLICTO_DISPONIBILIDAD', 409);
        } catch (ValidacionExcepcion $e) {
            return RespuestaApi::error($e->getMessage(), 'VALIDACION_FALLIDA', 422, $e->obtenerErrores());
        } catch (Throwable $e) {
            return RespuestaApi::error('Error interno al crear la reserva: ' . $e->getMessage(), 'ERROR_RESERVA', 500);
        }
    }

    /**
     * Consulta pública segura del estado de una reserva directa (WORDPRESS-1D).
     * GET /api/v1/reservas/{codigo}
     *
     * Protege PII sensible y oculta identificadores de auditoría o notas internas.
     * Requiere scope: reservas.leer
     */
    public function consultar(string $codigo): Respuesta
    {
        $codigoNormalizado = trim(strtoupper($codigo));
        if ($codigoNormalizado === '') {
            return RespuestaApi::error('Debe indicar un código de reserva válido.', 'CODIGO_REQUERIDO', 422);
        }

        $reserva = $this->reservaRepo->buscarPorCodigo($codigoNormalizado, true);
        if ($reserva === null) {
            return RespuestaApi::error("Reserva con código '{$codigoNormalizado}' no encontrada.", 'RESERVA_NO_ENCONTRADA', 404);
        }

        $unidades = [];
        foreach ($reserva->obtenerUnidades() as $u) {
            $unidades[] = [
                'unidad_codigo' => $u->obtenerUnidadCodigo(),
                'unidad_nombre' => $u->obtenerUnidadNombre(),
                'tipo_unidad' => $u->obtenerTipoUnidadNombre(),
                'precio_unitario_noche' => $u->obtenerPrecioUnitarioNoche(),
                'noches' => $u->obtenerNoches(),
                'total' => $u->obtenerTotal(),
            ];
        }

        $datosSeguros = [
            'codigo' => $reserva->obtenerCodigo(),
            'estado' => $reserva->obtenerEstado(),
            'expira_en' => $reserva->obtenerExpiraEn(),
            'ha_expirado' => $reserva->haExpirado(),
            'fecha_entrada' => $reserva->obtenerFechaEntrada(),
            'fecha_salida' => $reserva->obtenerFechaSalida(),
            'noches' => $reserva->obtenerNoches(),
            'moneda' => $reserva->obtenerMonedaCodigo(),
            'subtotal' => $reserva->obtenerSubtotal(),
            'impuesto_total' => $reserva->obtenerImpuestoTotal(),
            'total' => $reserva->obtenerTotal(),
            'canal' => $reserva->obtenerCanal(),
            'titular' => [
                'nombre' => $this->enmascararNombre($reserva->obtenerTitularNombreCompleto()),
                'email_enmascarado' => $this->enmascararEmail($reserva->obtenerTitularEmail()),
                'telefono_enmascarado' => $this->enmascararTelefono($reserva->obtenerTitularTelefono()),
            ],
            'unidades' => $unidades,
        ];

        return RespuestaApi::exito(
            datos: $datosSeguros,
            codigoHttp: 200,
            codigoDominio: 'RESERVA_RECUPERADA'
        );
    }

    /**
     * Resuelve una persona titular existente por tipo y número de documento, o crea una nueva atómicamente.
     *
     * @param array<string, mixed> $titular
     * @return int ID de la Persona
     * @throws ValidacionExcepcion
     */
    private function resolverOAsegurarPersonaTitular(array $titular): int
    {
        $tipoDocStr = strtoupper(trim((string) ($titular['tipo_documento'] ?? 'DNI')));
        $numDoc = strtoupper(trim((string) ($titular['numero_documento'] ?? '')));

        if ($numDoc === '') {
            throw new ValidacionExcepcion("El número de documento ('numero_documento') es obligatorio para el titular.");
        }

        // 1. Buscar si ya existe la persona por documento
        $personaExistente = $this->personaRepo->buscarPorDocumento($tipoDocStr, $numDoc);
        if ($personaExistente !== null) {
            $personaId = (int) $personaExistente->obtenerId();
            $this->asegurarRegistroCliente($personaId);
            return $personaId;
        }

        // 2. Si no existe, validar catálogo de tipo de documento
        $tipoDoc = $this->tipoDocRepo->buscarPorCodigo($tipoDocStr);
        if ($tipoDoc === null) {
            throw new ValidacionExcepcion("El tipo de documento '{$tipoDocStr}' no está reconocido en el catálogo.");
        }

        $nombres = trim((string) ($titular['nombres'] ?? ''));
        if ($nombres === '') {
            throw new ValidacionExcepcion("El nombre del huésped titular ('nombres') es obligatorio.");
        }

        $apellidoPaterno = trim((string) ($titular['apellido_paterno'] ?? $titular['apellidos'] ?? ''));
        $apellidoMaterno = isset($titular['apellido_materno']) && trim((string) $titular['apellido_materno']) !== ''
            ? trim((string) $titular['apellido_materno'])
            : null;

        $paisId = isset($titular['pais_id']) && (int) $titular['pais_id'] > 0
            ? (int) $titular['pais_id']
            : 1; // 1 = Perú por defecto

        $datosPersona = [
            'nombres' => $nombres,
            'apellido_paterno' => $apellidoPaterno !== '' ? $apellidoPaterno : null,
            'apellido_materno' => $apellidoMaterno,
            'pais_nacionalidad_id' => $paisId,
        ];

        $paisEmisorId = $tipoDoc->tienePaisFijo()
            ? $tipoDoc->obtenerPaisFijoId()
            : (isset($titular['pais_emisor_id']) && (int) $titular['pais_emisor_id'] > 0 ? (int) $titular['pais_emisor_id'] : $paisId);

        $datosDocumento = [
            'tipo_documento_id' => (int) $tipoDoc->obtenerId(),
            'numero_documento' => $numDoc,
            'pais_emisor_id' => $paisEmisorId,
            'es_principal' => true,
        ];

        $contactos = [];
        $email = trim((string) ($titular['email'] ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $contactos[] = [
                'tipo_contacto' => 'EMAIL',
                'valor' => $email,
                'es_principal' => true,
            ];
        }

        $telefono = trim((string) ($titular['telefono'] ?? ''));
        if ($telefono !== '' && preg_match('/^[0-9+\s\-().]{6,25}$/', $telefono)) {
            $contactos[] = [
                'tipo_contacto' => 'TELEFONO',
                'valor' => $telefono,
                'es_principal' => empty($contactos),
            ];
        }

        $nuevaPersona = $this->personaServicio->crearPersona($datosPersona, $datosDocumento, $contactos);
        $personaId = (int) $nuevaPersona->obtenerId();

        $this->asegurarRegistroCliente($personaId);

        return $personaId;
    }

    /**
     * Asegura la existencia de la ficha comercial de cliente para la persona (CLIENTES-1).
     */
    private function asegurarRegistroCliente(int $personaId): void
    {
        try {
            $stmt = $this->pdo->prepare('SELECT id FROM clientes WHERE persona_id = :persona_id LIMIT 1');
            $stmt->bindValue(':persona_id', $personaId, PDO::PARAM_INT);
            $stmt->execute();
            if (!$stmt->fetch()) {
                $clienteServicio = new ClienteServicio($this->pdo);
                $clienteServicio->crearCliente([
                    'persona_id' => $personaId,
                    'canal_captacion' => 'WEB',
                    'observaciones' => 'Registrado automáticamente desde API Web de reservas.',
                ]);
            }
        } catch (Throwable) {
            // Silencioso ante posible colisión concurrente
        }
    }

    private function enmascararNombre(?string $nombreCompleto): string
    {
        if ($nombreCompleto === null || trim($nombreCompleto) === '') {
            return 'Huésped Titular';
        }
        $partes = preg_split('/\s+/', trim($nombreCompleto));
        if (count($partes) <= 1) {
            return $partes[0];
        }
        $nombre = $partes[0];
        $apellidoInicial = mb_substr($partes[1], 0, 1) . '.';
        return $nombre . ' ' . $apellidoInicial;
    }

    private function enmascararEmail(?string $email): ?string
    {
        if ($email === null || trim($email) === '' || !str_contains($email, '@')) {
            return null;
        }
        [$usuario, $dominio] = explode('@', trim($email), 2);
        $longitud = strlen($usuario);
        if ($longitud <= 2) {
            $usuarioM = substr($usuario, 0, 1) . '*';
        } else {
            $usuarioM = substr($usuario, 0, 1) . str_repeat('*', $longitud - 2) . substr($usuario, -1);
        }
        return $usuarioM . '@' . $dominio;
    }

    private function enmascararTelefono(?string $telefono): ?string
    {
        if ($telefono === null || trim($telefono) === '') {
            return null;
        }
        $tel = trim($telefono);
        $long = strlen($tel);
        if ($long <= 4) {
            return str_repeat('*', $long);
        }
        return str_repeat('*', $long - 4) . substr($tel, -4);
    }
}
