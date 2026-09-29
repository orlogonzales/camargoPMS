<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

use CamargoPMS\Excepciones\ClienteDuplicadoExcepcion;
use CamargoPMS\Excepciones\ClienteNoEncontradoExcepcion;
use CamargoPMS\Excepciones\EntidadNoEncontradaExcepcion;
use CamargoPMS\Excepciones\ValidacionClienteExcepcion;
use CamargoPMS\Modelos\Cliente;
use CamargoPMS\Modelos\ClienteCategoria;
use CamargoPMS\Modelos\Persona;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Repositorios\ActorAuditoriaRepositorio;
use CamargoPMS\Repositorios\AuditoriaRepositorio;
use CamargoPMS\Repositorios\ClienteCategoriaRepositorio;
use CamargoPMS\Repositorios\ClienteRepositorio;
use CamargoPMS\Repositorios\ContactoPersonaRepositorio;
use CamargoPMS\Repositorios\DocumentoPersonaRepositorio;
use CamargoPMS\Repositorios\PersonaRepositorio;
use CamargoPMS\Repositorios\TipoDocumentoRepositorio;
use DateTimeImmutable;
use PDO;
use Throwable;

/**
 * Servicio de dominio soberano para la gestión comercial de Clientes y Ficha 360° (CLIENTES-1).
 *
 * Principio Vinculante:
 * PERSONA ≠ CLIENTE pero CLIENTE -> PERSONA.
 * - Persona: Registro maestro de identidad biológica y civil soberano.
 * - Cliente: Modela la relación comercial para Personas Naturales (1:1 estricto con personas.id).
 * - Cero recreación de datos de identidad en clientes.
 * - Cero duplicación de contabilidad: Consume servicios financieros soberanos existentes.
 */
class ClienteServicio
{
    private PDO $pdo;
    private ClienteRepositorio $clienteRepo;
    private ClienteCategoriaRepositorio $categoriaRepo;
    private PersonaRepositorio $personaRepo;
    private PersonaServicio $personaServicio;
    private DocumentoPersonaRepositorio $documentoRepo;
    private ContactoPersonaRepositorio $contactoRepo;
    private TipoDocumentoRepositorio $tipoDocRepo;
    private CuentaFolioServicio $cuentaFolioServicio;
    private AuditoriaServicio $auditoriaServicio;
    private ActorAuditoriaRepositorio $actorRepo;

    /**
     * Catálogo estándar de canales de captación comercial.
     */
    public const CANALES_CAPTACION = [
        'DIRECTO' => 'Directo / Presencial',
        'WEB' => 'Sitio Web Oficial',
        'TELEFONO' => 'Contacto Telefónico',
        'WHATSAPP' => 'Mensajería WhatsApp',
        'OTA_BOOKING' => 'Booking.com',
        'OTA_AIRBNB' => 'Airbnb',
        'WALK_IN' => 'Walk-in (Sin reserva previa)',
        'RECOMENDACION' => 'Recomendación / Referido',
        'OTRO' => 'Otro Canal',
    ];

    public function __construct(
        ?PDO $pdo = null,
        ?ClienteRepositorio $clienteRepo = null,
        ?ClienteCategoriaRepositorio $categoriaRepo = null,
        ?PersonaRepositorio $personaRepo = null,
        ?PersonaServicio $personaServicio = null,
        ?DocumentoPersonaRepositorio $documentoRepo = null,
        ?ContactoPersonaRepositorio $contactoRepo = null,
        ?TipoDocumentoRepositorio $tipoDocRepo = null,
        ?CuentaFolioServicio $cuentaFolioServicio = null,
        ?AuditoriaServicio $auditoriaServicio = null,
        ?ActorAuditoriaRepositorio $actorRepo = null
    ) {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->clienteRepo = $clienteRepo ?? new ClienteRepositorio($this->pdo);
        $this->categoriaRepo = $categoriaRepo ?? new ClienteCategoriaRepositorio($this->pdo);
        $this->personaRepo = $personaRepo ?? new PersonaRepositorio($this->pdo);
        $this->personaServicio = $personaServicio ?? new PersonaServicio($this->pdo, $this->personaRepo);
        $this->documentoRepo = $documentoRepo ?? new DocumentoPersonaRepositorio($this->pdo);
        $this->contactoRepo = $contactoRepo ?? new ContactoPersonaRepositorio($this->pdo);
        $this->tipoDocRepo = $tipoDocRepo ?? new TipoDocumentoRepositorio($this->pdo);
        $this->cuentaFolioServicio = $cuentaFolioServicio ?? new CuentaFolioServicio($this->pdo);
        $this->auditoriaServicio = $auditoriaServicio ?? new AuditoriaServicio($this->pdo);
        $this->actorRepo = $actorRepo ?? new ActorAuditoriaRepositorio($this->pdo);
    }

    /**
     * Registra un nuevo perfil comercial de cliente.
     * Soporta vincular una persona existente (persona_id) o crear una nueva persona atómicamente.
     *
     * @param array<string, mixed> $datos
     * @param int|null $actorId
     * @return Cliente
     * @throws ClienteDuplicadoExcepcion Si la persona ya tiene un perfil de cliente.
     * @throws ValidacionClienteExcepcion Si las invariantes de negocio fallan.
     * @throws EntidadNoEncontradaExcepcion Si la persona especificada no existe.
     * @throws Throwable
     */
    public function crearCliente(array $datos, ?int $actorId = null): Cliente
    {
        $actorIdFinal = $this->resolverActorId($actorId);

        $personaId = isset($datos['persona_id']) && (int) $datos['persona_id'] > 0
            ? (int) $datos['persona_id']
            : null;

        $personaCreadaId = null;

        // 1. Resolver o Crear Persona (PersonaServicio maneja su propia transacción interna)
        if ($personaId !== null) {
            $personaExistente = $this->personaRepo->buscarPorId($personaId);
            if ($personaExistente === null) {
                throw new ValidacionClienteExcepcion("La persona con ID {$personaId} no existe.", ['persona_id' => 'Persona inexistente']);
            }

            if ($this->clienteRepo->existeParaPersona($personaId)) {
                throw new ClienteDuplicadoExcepcion($personaId);
            }
        } else {
            // Workflow creación atómica de Persona
            if (empty($datos['datos_persona']) || !is_array($datos['datos_persona'])) {
                throw new ValidacionClienteExcepcion(
                    'Debe seleccionar una persona existente o suministrar los datos completos de una nueva persona.',
                    ['persona_id' => 'Persona requerida']
                );
            }

            $datosPersona = $datos['datos_persona'];
            $docPrincipal = !empty($datos['documento_principal']) && is_array($datos['documento_principal'])
                ? $datos['documento_principal']
                : null;
            $contactos = !empty($datos['contactos']) && is_array($datos['contactos'])
                ? $datos['contactos']
                : [];

            // Crear persona en su propia transacción
            $nuevaPersona = $this->personaServicio->crearPersona($datosPersona, $docPrincipal, $contactos);
            $personaId = (int) $nuevaPersona->obtenerId();
            $personaCreadaId = $personaId;
        }

        // 2. Resolver Categoría Comercial
        $categoriaId = isset($datos['categoria_id']) && (int) $datos['categoria_id'] > 0
            ? (int) $datos['categoria_id']
            : null;

        if ($categoriaId !== null) {
            $cat = $this->categoriaRepo->buscarPorId($categoriaId);
            if ($cat === null || !$cat->estaActivo()) {
                throw new ValidacionClienteExcepcion("La categoría seleccionada no existe o está inactiva.", ['categoria_id' => 'Categoría inválida']);
            }
        } else {
            $catDefault = $this->categoriaRepo->obtenerPredeterminada();
            $categoriaId = $catDefault !== null && $catDefault->obtenerId() !== null
                ? $catDefault->obtenerId()
                : 1;
        }

        $this->pdo->beginTransaction();

        try {
            // 3. Generar Código Secuencial CLI-XXXXX Concurrency-Safe
            $codigo = $this->clienteRepo->generarSiguienteCodigo();

            // 4. Validar y normalizar estado
            $estado = strtoupper(trim((string) ($datos['estado'] ?? Cliente::ESTADO_ACTIVO)));
            if (!in_array($estado, Cliente::ESTADOS_VALIDOS, true)) {
                $estado = Cliente::ESTADO_ACTIVO;
            }

            $motivoBloqueo = isset($datos['motivo_bloqueo']) ? trim((string) $datos['motivo_bloqueo']) : null;
            if ($estado === Cliente::ESTADO_BLOQUEADO && ($motivoBloqueo === null || $motivoBloqueo === '')) {
                throw new ValidacionClienteExcepcion(
                    'El motivo de bloqueo es obligatorio cuando el estado del cliente es BLOQUEADO.',
                    ['motivo_bloqueo' => 'Motivo requerido para estado BLOQUEADO']
                );
            }

            $canalCaptacion = isset($datos['canal_captacion']) ? trim((string) $datos['canal_captacion']) : null;
            $preferencias = isset($datos['preferencias']) ? trim((string) $datos['preferencias']) : null;
            $observaciones = isset($datos['observaciones']) ? trim((string) $datos['observaciones']) : null;

            $cliente = new Cliente(
                null,
                $codigo,
                $personaId,
                $categoriaId,
                $estado,
                $motivoBloqueo,
                $canalCaptacion,
                $preferencias,
                $observaciones,
                $actorIdFinal,
                $actorIdFinal
            );

            $clienteId = $this->clienteRepo->insertar($cliente);

            // 5. Auditoría D-061
            $this->auditoriaServicio->registrar(
                accion: 'CREAR',
                modulo: 'clientes',
                entidad: 'clientes',
                entidadId: $clienteId,
                descripcion: "Alta de perfil comercial de cliente [{$codigo}] vinculado a Persona ID [{$personaId}]",
                valoresAnteriores: null,
                valoresNuevos: $cliente->aArreglo(),
                actor: $actorIdFinal,
                pdoTransaccional: $this->pdo
            );

            $this->pdo->commit();

            return $this->obtenerPorId($clienteId);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Actualiza los datos del perfil comercial de un cliente.
     *
     * @param int $id
     * @param array<string, mixed> $datos
     * @param int|null $actorId
     * @return Cliente
     */
    public function actualizarCliente(int $id, array $datos, ?int $actorId = null): Cliente
    {
        $actorIdFinal = $this->resolverActorId($actorId);

        $actual = $this->clienteRepo->buscarPorId($id);
        if ($actual === null) {
            throw new ClienteNoEncontradoExcepcion((string) $id);
        }

        $valoresAnteriores = $actual->aArreglo();

        // Categoría
        if (isset($datos['categoria_id']) && (int) $datos['categoria_id'] > 0) {
            $catId = (int) $datos['categoria_id'];
            $cat = $this->categoriaRepo->buscarPorId($catId);
            if ($cat === null) {
                throw new ValidacionClienteExcepcion("Categoría [{$catId}] inexistente.", ['categoria_id' => 'Categoría inexistente']);
            }
            $actual->fijarCategoriaId($catId);
        }

        // Canal de captación
        if (array_key_exists('canal_captacion', $datos)) {
            $actual->fijarCanalCaptacion($datos['canal_captacion'] !== null ? trim((string) $datos['canal_captacion']) : null);
        }

        // Preferencias
        if (array_key_exists('preferencias', $datos)) {
            $actual->fijarPreferencias($datos['preferencias'] !== null ? trim((string) $datos['preferencias']) : null);
        }

        // Observaciones
        if (array_key_exists('observaciones', $datos)) {
            $actual->fijarObservaciones($datos['observaciones'] !== null ? trim((string) $datos['observaciones']) : null);
        }

        // Estado
        if (isset($datos['estado'])) {
            $nuevoEstado = strtoupper(trim((string) $datos['estado']));
            if (!in_array($nuevoEstado, Cliente::ESTADOS_VALIDOS, true)) {
                throw new ValidacionClienteExcepcion("Estado '{$nuevoEstado}' no válido.", ['estado' => 'Estado inválido']);
            }

            if ($nuevoEstado === Cliente::ESTADO_BLOQUEADO) {
                $motivo = isset($datos['motivo_bloqueo']) ? trim((string) $datos['motivo_bloqueo']) : '';
                if ($motivo === '') {
                    throw new ValidacionClienteExcepcion('El motivo de bloqueo es obligatorio.', ['motivo_bloqueo' => 'Motivo requerido']);
                }
                $actual->bloquear($motivo);
            } elseif ($nuevoEstado === Cliente::ESTADO_ACTIVO) {
                $actual->activar();
            } else {
                $actual->desactivar();
            }
        }

        $actual->fijarActualizadoPorActorId($actorIdFinal);

        $this->pdo->beginTransaction();
        try {
            $this->clienteRepo->actualizar($actual);

            $this->auditoriaServicio->registrar(
                accion: 'ACTUALIZAR',
                modulo: 'clientes',
                entidad: 'clientes',
                entidadId: $id,
                descripcion: "Actualización de perfil comercial de cliente [{$actual->obtenerCodigo()}]",
                valoresAnteriores: $valoresAnteriores,
                valoresNuevos: $actual->aArreglo(),
                actor: $actorIdFinal,
                pdoTransaccional: $this->pdo
            );

            $this->pdo->commit();

            return $this->obtenerPorId($id);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Cambia el estado comercial de un cliente (ACTIVO, INACTIVO, BLOQUEADO).
     *
     * @param int $id
     * @param string $nuevoEstado
     * @param string|null $motivo
     * @param int|null $actorId
     * @return Cliente
     */
    public function cambiarEstado(int $id, string $nuevoEstado, ?string $motivo = null, ?int $actorId = null): Cliente
    {
        $actorIdFinal = $this->resolverActorId($actorId);

        $cliente = $this->clienteRepo->buscarPorId($id);
        if ($cliente === null) {
            throw new ClienteNoEncontradoExcepcion((string) $id);
        }

        $estadoLimpio = strtoupper(trim($nuevoEstado));
        if (!in_array($estadoLimpio, Cliente::ESTADOS_VALIDOS, true)) {
            throw new ValidacionClienteExcepcion("Estado '{$nuevoEstado}' inválido.", ['estado' => 'Estado inválido']);
        }

        $motivoLimpio = $motivo !== null ? trim($motivo) : null;
        if ($estadoLimpio === Cliente::ESTADO_BLOQUEADO && ($motivoLimpio === null || $motivoLimpio === '')) {
            throw new ValidacionClienteExcepcion('El motivo de bloqueo es obligatorio.', ['motivo_bloqueo' => 'Motivo requerido']);
        }

        $valoresAnteriores = $cliente->aArreglo();

        $this->pdo->beginTransaction();
        try {
            $this->clienteRepo->cambiarEstado($id, $estadoLimpio, $motivoLimpio, $actorIdFinal);

            $this->auditoriaServicio->registrar(
                accion: 'CAMBIAR_ESTADO',
                modulo: 'clientes',
                entidad: 'clientes',
                entidadId: $id,
                descripcion: "Cambio de estado comercial del cliente [{$cliente->obtenerCodigo()}] de {$cliente->obtenerEstado()} a {$estadoLimpio}" . ($motivoLimpio ? ": {$motivoLimpio}" : ''),
                valoresAnteriores: $valoresAnteriores,
                valoresNuevos: array_merge($valoresAnteriores, ['estado' => $estadoLimpio, 'motivo_bloqueo' => $motivoLimpio]),
                actor: $actorIdFinal,
                pdoTransaccional: $this->pdo
            );

            $this->pdo->commit();

            return $this->obtenerPorId($id);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function obtenerPorId(int $id): Cliente
    {
        $cliente = $this->clienteRepo->buscarPorId($id, true);
        if ($cliente === null) {
            throw new ClienteNoEncontradoExcepcion((string) $id);
        }

        return $cliente;
    }

    public function obtenerPorCodigo(string $codigo): Cliente
    {
        $cliente = $this->clienteRepo->buscarPorCodigo($codigo, true);
        if ($cliente === null) {
            throw new ClienteNoEncontradoExcepcion($codigo);
        }

        return $cliente;
    }

    public function obtenerPorPersonaId(int $personaId): ?Cliente
    {
        return $this->clienteRepo->buscarPorPersonaId($personaId, true);
    }

    /**
     * @param array<string, mixed> $filtros
     * @param int $limite
     * @param int $offset
     * @return array<int, array<string, mixed>>
     */
    public function listarClientes(array $filtros = [], int $limite = 50, int $offset = 0): array
    {
        return $this->clienteRepo->listar($filtros, $limite, $offset);
    }

    /**
     * @param array<string, mixed> $filtros
     * @return int
     */
    public function contarClientes(array $filtros = []): int
    {
        return $this->clienteRepo->contar($filtros);
    }

    /**
     * Obtiene todas las categorías comerciales activas.
     *
     * @return array<int, ClienteCategoria>
     */
    public function listarCategorias(bool $soloActivos = true): array
    {
        return $this->categoriaRepo->listar($soloActivos);
    }

    /**
     * Construye y agrega la Ficha Integral 360° del cliente consumiendo todas las fuentes de verdad soberanas.
     *
     * @param int $clienteId
     * @return array<string, mixed>
     */
    public function obtenerFicha360(int $clienteId): array
    {
        $cliente = $this->obtenerPorId($clienteId);
        $personaId = $cliente->obtenerPersonaId();

        $persona = $this->personaRepo->buscarPorId($personaId);
        if ($persona === null) {
            throw new EntidadNoEncontradaExcepcion("Persona vinculada ID {$personaId} no encontrada.");
        }

        $categoria = $this->categoriaRepo->buscarPorId($cliente->obtenerCategoriaId());

        // 1. Identidad soberana de la Persona
        $documentosPersona = $this->documentoRepo->listarPorPersonaId($personaId);
        $contactosPersona = $this->contactoRepo->listarPorPersonaId($personaId);

        $documentosArreglo = [];
        $documentoPrincipal = null;
        foreach ($documentosPersona as $doc) {
            $tipoDoc = $this->tipoDocRepo->buscarPorId($doc->obtenerTipoDocumentoId());
            $docArr = array_merge($doc->aArreglo(), [
                'tipo_codigo' => $tipoDoc ? $tipoDoc->obtenerCodigo() : 'DOC',
                'tipo_nombre' => $tipoDoc ? $tipoDoc->obtenerNombre() : 'Documento',
            ]);
            $documentosArreglo[] = $docArr;
            if ($doc->esPrincipal()) {
                $documentoPrincipal = $docArr;
            }
        }

        $contactosArreglo = [];
        $telefonoPrincipal = null;
        $emailPrincipal = null;
        foreach ($contactosPersona as $cont) {
            $contArr = $cont->aArreglo();
            $contactosArreglo[] = $contArr;
            if ($cont->obtenerTipoContacto() === 'TELEFONO' && ($telefonoPrincipal === null || $cont->esPrincipal())) {
                $telefonoPrincipal = $contArr;
            }
            if ($cont->obtenerTipoContacto() === 'EMAIL' && ($emailPrincipal === null || $cont->esPrincipal())) {
                $emailPrincipal = $contArr;
            }
        }

        // 2. Historial de Reservas
        $reservas = $this->clienteRepo->obtenerHistorialReservas($personaId);

        // 3. Historial de Estadías (Huéspedes: responsable vs acompañante)
        $estadias = $this->clienteRepo->obtenerHistorialEstadias($personaId);

        // 4. Historial de Arrendamientos (Titular vs cotitular vs ocupante)
        $arrendamientos = $this->clienteRepo->obtenerHistorialArrendamientos($personaId);

        // 5. Historial de Servicios Contratados
        $servicios = $this->clienteRepo->obtenerHistorialServicios($personaId);

        // 6. Estado de Cuenta Soberano (Cuentas Folios, Cargos y Pagos)
        $foliosCrudos = $this->clienteRepo->obtenerFoliosPersona($personaId);

        $foliosDetallados = [];
        $saldoConsolidado = '0.00';
        $totalCargosDevengados = '0.00';
        $totalCargosProvisionales = '0.00';
        $totalPagosConfirmados = '0.00';
        $totalPagosAplicados = '0.00';
        $totalPagosDisponibles = '0.00';
        $totalDevoluciones = '0.00';

        foreach ($foliosCrudos as $f) {
            $folioId = (int) $f['id'];
            try {
                $estadoFolio = $this->cuentaFolioServicio->obtenerEstadoCuenta($folioId);
                $foliosDetallados[] = $estadoFolio;

                $saldoConsolidado = bcadd($saldoConsolidado, (string) $estadoFolio['saldo_neto_exigible'], 2);
                $totalCargosDevengados = bcadd($totalCargosDevengados, (string) $estadoFolio['total_cargos_devengados'], 2);
                $totalCargosProvisionales = bcadd($totalCargosProvisionales, (string) $estadoFolio['total_cargos_provisionales'], 2);
                $totalPagosConfirmados = bcadd($totalPagosConfirmados, (string) $estadoFolio['total_pagos_confirmados'], 2);
                $totalPagosAplicados = bcadd($totalPagosAplicados, (string) $estadoFolio['total_pagos_aplicados'], 2);
                $totalPagosDisponibles = bcadd($totalPagosDisponibles, (string) $estadoFolio['total_pagos_disponibles'], 2);
                $totalDevoluciones = bcadd($totalDevoluciones, (string) $estadoFolio['total_devoluciones_confirmadas'], 2);
            } catch (Throwable) {
                // Si el folio no tiene cargos o falló la consulta individual, se incluye básico
                $foliosDetallados[] = ['folio' => $f, 'saldo_neto_exigible' => '0.00', 'cargos' => [], 'pagos' => [], 'devoluciones' => []];
            }
        }

        // 7. Recibos de Cobro
        $recibos = $this->clienteRepo->obtenerRecibosPersona($personaId);

        // 8. Documentos Emitidos
        $documentosEmitidos = $this->clienteRepo->obtenerDocumentosEmitidosPersona($personaId);

        // 9. Cálculo de KPIs y Fechas
        $fechasOperativas = [];
        foreach ($reservas as $r) {
            if (!empty($r['fecha_entrada'])) {
                $fechasOperativas[] = $r['fecha_entrada'];
            }
        }
        foreach ($arrendamientos as $a) {
            if (!empty($a['fecha_inicio'])) {
                $fechasOperativas[] = $a['fecha_inicio'];
            }
        }
        if (!empty($cliente->obtenerCreadoEn())) {
            $fechasOperativas[] = substr($cliente->obtenerCreadoEn(), 0, 10);
        }

        sort($fechasOperativas);
        $fechaPrimerRegistro = count($fechasOperativas) > 0 ? $fechasOperativas[0] : substr((string) $cliente->obtenerCreadoEn(), 0, 10);
        $fechaUltimaOperacion = count($fechasOperativas) > 0 ? end($fechasOperativas) : null;

        // Antigüedad descriptiva
        $antiguedadTexto = 'Nuevo';
        if ($fechaPrimerRegistro) {
            try {
                $dInicio = new DateTimeImmutable($fechaPrimerRegistro);
                $dHoy = new DateTimeImmutable('today');
                $intervalo = $dInicio->diff($dHoy);
                if ($intervalo->y > 0) {
                    $antiguedadTexto = "{$intervalo->y} " . ($intervalo->y === 1 ? 'año' : 'años');
                } elseif ($intervalo->m > 0) {
                    $antiguedadTexto = "{$intervalo->m} " . ($intervalo->m === 1 ? 'mes' : 'meses');
                } else {
                    $dias = $intervalo->d;
                    $antiguedadTexto = "{$dias} " . ($dias === 1 ? 'día' : 'días');
                }
            } catch (Throwable) {
                $antiguedadTexto = 'N/D';
            }
        }

        $kpis = [
            'total_reservas' => count($reservas),
            'total_estadias' => count($estadias),
            'total_arrendamientos' => count($arrendamientos),
            'total_servicios' => count($servicios),
            'total_recibos' => count($recibos),
            'total_documentos' => count($documentosEmitidos),
            'saldo_pendiente_consolidado' => $saldoConsolidado,
            'total_cargos_devengados' => $totalCargosDevengados,
            'total_cargos_provisionales' => $totalCargosProvisionales,
            'total_pagos_confirmados' => $totalPagosConfirmados,
            'total_pagos_aplicados' => $totalPagosAplicados,
            'total_pagos_disponibles' => $totalPagosDisponibles,
            'total_devoluciones' => $totalDevoluciones,
            'fecha_primer_registro' => $fechaPrimerRegistro,
            'fecha_ultima_operacion' => $fechaUltimaOperacion,
            'antiguedad' => $antiguedadTexto,
        ];

        return [
            'cliente' => $cliente->aArreglo(),
            'categoria' => $categoria ? $categoria->aArreglo() : null,
            'persona' => array_merge($persona->aArreglo(), [
                'documento_principal' => $documentoPrincipal,
                'telefono_principal' => $telefonoPrincipal,
                'email_principal' => $emailPrincipal,
                'documentos' => $documentosArreglo,
                'contactos' => $contactosArreglo,
            ]),
            'kpis' => $kpis,
            'reservas' => $reservas,
            'estadias' => $estadias,
            'arrendamientos' => $arrendamientos,
            'servicios' => $servicios,
            'estado_cuenta' => [
                'saldo_consolidado' => $saldoConsolidado,
                'total_cargos_devengados' => $totalCargosDevengados,
                'total_pagos_confirmados' => $totalPagosConfirmados,
                'total_pagos_aplicados' => $totalPagosAplicados,
                'folios' => $foliosDetallados,
                'recibos' => $recibos,
            ],
            'documentos' => $documentosEmitidos,
            'preferencias_notas' => [
                'canal_captacion' => $cliente->obtenerCanalCaptacion(),
                'canal_captacion_nombre' => self::CANALES_CAPTACION[$cliente->obtenerCanalCaptacion() ?? ''] ?? ($cliente->obtenerCanalCaptacion() ?? 'No especificado'),
                'preferencias' => $cliente->obtenerPreferencias(),
                'observaciones' => $cliente->obtenerObservaciones(),
            ],
        ];
    }

    private function resolverActorId(?int $actorOUsuarioId): int
    {
        if ($actorOUsuarioId !== null && $actorOUsuarioId > 0) {
            $actor = $this->actorRepo->buscarPorId($actorOUsuarioId);
            if ($actor !== null) {
                return (int) $actor->obtenerId();
            }
            $actorHumano = $this->actorRepo->buscarPorUsuarioId($actorOUsuarioId);
            if ($actorHumano !== null) {
                return (int) $actorHumano->obtenerId();
            }
        }

        $sistema = $this->actorRepo->buscarPorCodigo('CAMARGO_PMS');
        if ($sistema !== null) {
            return (int) $sistema->obtenerId();
        }

        return 1;
    }
}
