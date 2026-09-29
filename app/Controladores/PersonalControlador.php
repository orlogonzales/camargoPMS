<?php

declare(strict_types=1);

namespace CamargoPMS\Controladores;

use CamargoPMS\Excepciones\ColaboradorDuplicadoExcepcion;
use CamargoPMS\Excepciones\EntidadNoEncontradaExcepcion;
use CamargoPMS\Excepciones\EpisodioLaboralActivoExcepcion;
use CamargoPMS\Excepciones\SolapamientoLaboralExcepcion;
use CamargoPMS\Excepciones\ValidacionExcepcion;
use CamargoPMS\Modelos\Usuario;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Nucleo\Vista;
use CamargoPMS\Repositorios\ActorAuditoriaRepositorio;
use CamargoPMS\Repositorios\CargoRepositorio;
use CamargoPMS\Repositorios\ColaboradorRepositorio;
use CamargoPMS\Repositorios\PersonaRepositorio;
use CamargoPMS\Servicios\AuditoriaServicio;
use CamargoPMS\Servicios\AutorizacionServicio;
use CamargoPMS\Servicios\ColaboradorServicio;
use CamargoPMS\Servicios\CsrfServicio;
use CamargoPMS\Servicios\EmpresaServicio;
use CamargoPMS\Servicios\PersonaServicio;
use CamargoPMS\Servicios\SesionServicio;
use PDO;
use Throwable;

/**
 * Controlador de Gestión Administrativa de Personal y Legajo Laboral (PERSONAL-1A).
 *
 * Principio Rector:
 * PERSONA ≠ COLABORADOR ≠ USUARIO ≠ CARGO ≠ ROL
 */
class PersonalControlador
{
    private Vista $vista;
    private PDO $pdo;
    private ColaboradorRepositorio $colaboradorRepo;
    private PersonaRepositorio $personaRepo;
    private CargoRepositorio $cargoRepo;
    private ColaboradorServicio $colaboradorServicio;
    private PersonaServicio $personaServicio;
    private EmpresaServicio $empresaServicio;
    private SesionServicio $sesionServicio;
    private AutorizacionServicio $autorizacionServicio;
    private CsrfServicio $csrfServicio;

    public function __construct(
        ?Vista $vista = null,
        ?PDO $pdo = null,
        ?ColaboradorRepositorio $colaboradorRepo = null,
        ?PersonaRepositorio $personaRepo = null,
        ?CargoRepositorio $cargoRepo = null,
        ?ColaboradorServicio $colaboradorServicio = null,
        ?PersonaServicio $personaServicio = null,
        ?EmpresaServicio $empresaServicio = null,
        ?SesionServicio $sesionServicio = null,
        ?AutorizacionServicio $autorizacionServicio = null,
        ?CsrfServicio $csrfServicio = null
    ) {
        $this->vista = $vista ?? new Vista();
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->colaboradorRepo = $colaboradorRepo ?? new ColaboradorRepositorio($this->pdo);
        $this->personaRepo = $personaRepo ?? new PersonaRepositorio($this->pdo);
        $this->cargoRepo = $cargoRepo ?? new CargoRepositorio($this->pdo);
        $this->empresaServicio = $empresaServicio ?? new EmpresaServicio($this->pdo);
        $this->colaboradorServicio = $colaboradorServicio ?? new ColaboradorServicio(
            $this->pdo,
            $this->colaboradorRepo,
            $this->personaRepo,
            null,
            null,
            $this->cargoRepo,
            new AuditoriaServicio($this->pdo),
            $this->empresaServicio
        );
        $this->personaServicio = $personaServicio ?? new PersonaServicio($this->pdo, $this->personaRepo);
        $this->sesionServicio = $sesionServicio ?? new SesionServicio($this->pdo);
        $this->autorizacionServicio = $autorizacionServicio ?? new AutorizacionServicio($this->pdo);
        $this->csrfServicio = $csrfServicio ?? new CsrfServicio();
    }

    private function obtenerUsuarioAutenticado(): ?Usuario
    {
        SesionServicio::iniciarSesionPhp();
        return $this->sesionServicio->validarSesionActual();
    }

    private function resolverActorId(): int
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if ($usuario !== null && $usuario->obtenerId() !== null) {
            $actorRepo = new ActorAuditoriaRepositorio($this->pdo);
            $actor = $actorRepo->buscarPorUsuarioId((int) $usuario->obtenerId());
            if ($actor !== null && $actor->obtenerId() !== null) {
                return (int) $actor->obtenerId();
            }
        }
        return 1;
    }

    private function validarCsrf(?array $datos = null): ?Respuesta
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? $_POST['csrf_token']
            ?? $_POST['_csrf_token']
            ?? ($datos['csrf_token'] ?? null)
            ?? ($datos['_csrf_token'] ?? null);

        if (!$token || !$this->csrfServicio->validarToken((string) $token)) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'Token CSRF inválido o expirado.'], 403);
        }
        return null;
    }

    private function leerEntrada(): array
    {
        $raw = file_get_contents('php://input');
        if (!empty($raw)) {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                return array_merge($_POST, $json);
            }
        }
        return $_POST;
    }

    /**
     * Vista principal del Directorio de Personal / RR.HH.
     */
    public function index(): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if ($usuario === null) {
            return Respuesta::redirigir('/login');
        }

        $usuarioId = (int) $usuario->obtenerId();
        if (!$this->autorizacionServicio->puede($usuarioId, 'personal.ver')) {
            return new Respuesta($this->vista->renderizar('errores/403', [
                'titulo' => '403 — Acceso Denegado',
                'mensaje' => 'No cuenta con permisos para consultar el directorio de personal y colaboradores.',
                'usuario' => $usuario,
            ]), 403);
        }

        $cargos = array_map(function ($c) {
            return $c instanceof \CamargoPMS\Modelos\Cargo ? $c->aArreglo() : (array) $c;
        }, $this->cargoRepo->listarActivos());
        $empresas = $this->empresaServicio->listarEmpresas(['estado' => 'ACTIVO'], 50);
        $empresaPrincipal = $this->empresaServicio->obtenerEmpresaParaPropiedad(null);

        // Tipos de documento para formulario de personas
        $stmtTipos = $this->pdo->query('SELECT id, codigo, nombre FROM tipos_documento WHERE activo = 1 ORDER BY id ASC');
        $tiposDocumento = $stmtTipos->fetchAll(PDO::FETCH_ASSOC);

        // Paises
        $stmtPais = $this->pdo->query('SELECT id, codigo_iso2 AS codigo_iso, nombre FROM paises WHERE activo = 1 ORDER BY nombre ASC');
        $paises = $stmtPais->fetchAll(PDO::FETCH_ASSOC);

        // Métricas y lista inicial
        $totalColaboradores = $this->colaboradorRepo->contar();
        $totalActivos = $this->colaboradorRepo->contar(['estado' => 'ACTIVO']);
        $totalInactivos = $this->colaboradorRepo->contar(['estado' => 'INACTIVO']);
        $colaboradores = $this->colaboradorServicio->listarColaboradores([], 100, 0);

        $totalConUsuario = 0;
        foreach ($colaboradores as $col) {
            if (!empty($col['usuario_id'])) {
                $totalConUsuario++;
            }
        }

        $permisos = [
            'puede_gestionar' => $this->autorizacionServicio->puede($usuarioId, 'personal.gestionar'),
        ];

        $html = $this->vista->renderizar('personal/index', [
            'titulo' => 'Personal y Legajo Laboral — Camargo PMS',
            'usuario' => $usuario,
            'colaboradores' => $colaboradores,
            'cargos' => $cargos,
            'empresas' => $empresas,
            'empresaPrincipal' => $empresaPrincipal,
            'tiposDocumento' => $tiposDocumento,
            'paises' => $paises,
            'resumen' => [
                'total' => $totalColaboradores,
                'activos' => $totalActivos,
                'inactivos' => $totalInactivos,
                'con_usuario' => $totalConUsuario,
            ],
            'permisos' => $permisos,
            'csrf_token' => $this->csrfServicio->generarToken(),
        ]);

        return new Respuesta($html);
    }

    /**
     * API: Listar colaboradores con filtros y paginación.
     */
    public function apiListar(): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if ($usuario === null || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'personal.ver')) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'No autorizado.'], 403);
        }

        try {
            $filtros = [
                'estado' => $_GET['estado'] ?? null,
                'cargo_id' => isset($_GET['cargo_id']) && is_numeric($_GET['cargo_id']) ? (int) $_GET['cargo_id'] : null,
                'q' => $_GET['q'] ?? null,
            ];

            $limite = min(100, max(1, (int) ($_GET['limite'] ?? 50)));
            $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
            $offset = ($pagina - 1) * $limite;

            $total = $this->colaboradorServicio->contarColaboradores($filtros);
            $datos = $this->colaboradorServicio->listarColaboradores($filtros, $limite, $offset);

            return Respuesta::json([
                'exito' => true,
                'total' => $total,
                'pagina' => $pagina,
                'limite' => $limite,
                'datos' => $datos,
            ]);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Ficha completa consolidada del colaborador.
     */
    public function apiDetalle(string|int|array $id): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if ($usuario === null || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'personal.ver')) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'No autorizado.'], 403);
        }

        $id = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;
        $ficha = $this->colaboradorServicio->obtenerFichaCompleta($id);

        if ($ficha === null) {
            return Respuesta::json(['exito' => false, 'mensaje' => "Colaborador [{$id}] no encontrado."], 404);
        }

        return Respuesta::json([
            'exito' => true,
            'datos' => $ficha,
        ]);
    }

    /**
     * API: Búsqueda de personas para reutilización en alta de colaborador.
     * Busca por DNI / documento o por coincidencia de nombre.
     */
    public function apiBuscarPersona(): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if ($usuario === null || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'personal.ver')) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'No autorizado.'], 403);
        }

        try {
            $tipoDoc = isset($_GET['tipo_documento']) ? strtoupper(trim((string) $_GET['tipo_documento'])) : null;
            $numeroDoc = isset($_GET['numero_documento']) ? trim((string) $_GET['numero_documento']) : null;
            $q = isset($_GET['q']) ? trim((string) $_GET['q']) : null;

            if ($tipoDoc !== null && $numeroDoc !== null && $numeroDoc !== '') {
                $persona = $this->personaRepo->buscarPorDocumento($tipoDoc, $numeroDoc, true);
                if ($persona !== null) {
                    $personaId = (int) $persona->obtenerId();
                    $colaboradorExistente = $this->colaboradorRepo->buscarPorPersonaId($personaId, false);

                    return Respuesta::json([
                        'exito' => true,
                        'encontrado' => true,
                        'persona' => $persona->aArreglo(),
                        'es_colaborador' => ($colaboradorExistente !== null),
                        'colaborador' => $colaboradorExistente ? $colaboradorExistente->aArreglo() : null,
                    ]);
                }

                return Respuesta::json(['exito' => true, 'encontrado' => false]);
            }

            // Búsqueda por texto libre
            if ($q !== null && strlen($q) >= 2) {
                $sql = "SELECT p.id, p.nombres, p.apellido_paterno, p.apellido_materno, td.codigo AS tipo_documento, pd.numero_documento, c.id AS colaborador_id, c.codigo AS colaborador_codigo, c.estado AS colaborador_estado
                        FROM personas p
                        LEFT JOIN personas_documentos pd ON pd.persona_id = p.id AND pd.es_principal = 1
                        LEFT JOIN tipos_documento td ON td.id = pd.tipo_documento_id
                        LEFT JOIN colaboradores c ON c.persona_id = p.id
                        WHERE p.nombres LIKE :q1 OR p.apellido_paterno LIKE :q2 OR p.apellido_materno LIKE :q3 OR pd.numero_documento LIKE :q4
                        LIMIT 10";
                $stmt = $this->pdo->prepare($sql);
                $paramQ = "%{$q}%";
                $stmt->execute([
                    ':q1' => $paramQ,
                    ':q2' => $paramQ,
                    ':q3' => $paramQ,
                    ':q4' => $paramQ,
                ]);
                $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

                return Respuesta::json([
                    'exito' => true,
                    'resultados' => $resultados,
                ]);
            }

            return Respuesta::json(['exito' => true, 'resultados' => []]);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Alta de colaborador.
     * Si persona_id es provista, reutiliza la identidad soberana.
     * Si no, crea primero la persona en el registro central.
     */
    public function apiCrear(): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if ($usuario === null || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'personal.gestionar')) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'No autorizado.'], 403);
        }

        $entrada = $this->leerEntrada();
        $errorCsrf = $this->validarCsrf($entrada);
        if ($errorCsrf !== null) {
            return $errorCsrf;
        }

        try {
            $actorId = $this->resolverActorId();
            $personaId = isset($entrada['persona_id']) && is_numeric($entrada['persona_id'])
                ? (int) $entrada['persona_id']
                : 0;

            // Flujo 1: Si no viene persona_id, registrar la persona natural en el maestro central
            if ($personaId <= 0) {
                $nombres = trim((string) ($entrada['nombres'] ?? ''));
                $apellidoPaterno = trim((string) ($entrada['apellido_paterno'] ?? ''));
                $apellidoMaterno = trim((string) ($entrada['apellido_materno'] ?? ''));

                if ($nombres === '' || $apellidoPaterno === '') {
                    return Respuesta::json([
                        'exito' => false,
                        'mensaje' => 'Nombres y apellido paterno son obligatorios para crear la persona natural.',
                    ], 422);
                }

                $tipoDocId = isset($entrada['tipo_documento_id']) ? (int) $entrada['tipo_documento_id'] : 1; // 1 = DNI
                $numDoc = trim((string) ($entrada['numero_documento'] ?? ''));

                if ($numDoc === '') {
                    return Respuesta::json([
                        'exito' => false,
                        'mensaje' => 'El número de documento es obligatorio para identificar a la persona.',
                    ], 422);
                }

                $datosPersona = [
                    'nombres' => $nombres,
                    'apellido_paterno' => $apellidoPaterno,
                    'apellido_materno' => $apellidoMaterno,
                    'pais_nacionalidad_id' => isset($entrada['pais_nacionalidad_id']) ? (int) $entrada['pais_nacionalidad_id'] : 1,
                    'direccion' => $entrada['direccion'] ?? null,
                    'fecha_nacimiento' => $entrada['fecha_nacimiento'] ?? null,
                ];

                $datosDoc = [
                    'tipo_documento_id' => $tipoDocId,
                    'numero_documento' => $numDoc,
                    'pais_emisor_id' => isset($entrada['pais_emisor_id']) ? (int) $entrada['pais_emisor_id'] : 1,
                ];

                $contactos = [];
                if (!empty($entrada['telefono'])) {
                    $contactos[] = [
                        'tipo_contacto' => 'TELEFONO',
                        'valor' => trim((string) $entrada['telefono']),
                        'es_whatsapp' => !empty($entrada['es_whatsapp']),
                        'es_principal' => true,
                    ];
                }
                if (!empty($entrada['email'])) {
                    $contactos[] = [
                        'tipo_contacto' => 'EMAIL',
                        'valor' => trim((string) $entrada['email']),
                        'es_whatsapp' => false,
                        'es_principal' => empty($contactos),
                    ];
                }

                $personaCreada = $this->personaServicio->crearPersona($datosPersona, $datosDoc, $contactos);
                $personaId = (int) $personaCreada->obtenerId();
            }

            // Flujo 2: Registrar como Colaborador
            $cargoId = isset($entrada['cargo_id']) ? (int) $entrada['cargo_id'] : 0;
            if ($cargoId <= 0) {
                return Respuesta::json(['exito' => false, 'mensaje' => 'Debe seleccionar un cargo laboral válido.'], 422);
            }

            $datosColab = [
                'persona_id' => $personaId,
                'cargo_id' => $cargoId,
                'fecha_inicio' => $entrada['fecha_inicio'] ?? date('Y-m-d'),
                'codigo' => !empty($entrada['codigo']) ? trim((string) $entrada['codigo']) : null,
                'observaciones' => $entrada['observaciones'] ?? null,
            ];

            $colaborador = $this->colaboradorServicio->crearColaborador($datosColab, $actorId);
            $colabId = (int) $colaborador->obtenerId();
            $ficha = $this->colaboradorServicio->obtenerFichaCompleta($colabId);

            return Respuesta::json([
                'exito' => true,
                'mensaje' => "Colaborador [{$colaborador->obtenerCodigo()}] registrado exitosamente.",
                'datos' => $ficha,
            ], 201);
        } catch (ColaboradorDuplicadoExcepcion $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 409);
        } catch (ValidacionExcepcion $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage(), 'errores' => $e->obtenerErrores()], 422);
        } catch (EntidadNoEncontradaExcepcion $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Actualizar datos personales y/o laborales de un colaborador.
     */
    public function apiActualizar(string|int|array $id): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if ($usuario === null || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'personal.gestionar')) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'No autorizado.'], 403);
        }

        $entrada = $this->leerEntrada();
        $errorCsrf = $this->validarCsrf($entrada);
        if ($errorCsrf !== null) {
            return $errorCsrf;
        }

        $id = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;

        try {
            $colab = $this->colaboradorServicio->buscarPorId($id);
            if ($colab === null) {
                return Respuesta::json(['exito' => false, 'mensaje' => "Colaborador [{$id}] no encontrado."], 404);
            }

            $personaId = $colab->obtenerPersonaId();

            // Actualizar datos de persona si fueron enviados
            if (isset($entrada['nombres']) || isset($entrada['apellido_paterno']) || isset($entrada['telefono']) || isset($entrada['email'])) {
                $camposPersona = [];
                $paramsPersona = [':id' => $personaId];

                if (!empty($entrada['nombres'])) {
                    $camposPersona[] = 'nombres = :nombres';
                    $paramsPersona[':nombres'] = trim((string) $entrada['nombres']);
                }
                if (!empty($entrada['apellido_paterno'])) {
                    $camposPersona[] = 'apellido_paterno = :ap_paterno';
                    $paramsPersona[':ap_paterno'] = trim((string) $entrada['apellido_paterno']);
                }
                if (isset($entrada['apellido_materno'])) {
                    $camposPersona[] = 'apellido_materno = :ap_materno';
                    $paramsPersona[':ap_materno'] = trim((string) $entrada['apellido_materno']);
                }

                if (!empty($camposPersona)) {
                    $sqlP = "UPDATE personas SET " . implode(', ', $camposPersona) . ", actualizado_en = NOW() WHERE id = :id";
                    $stmtP = $this->pdo->prepare($sqlP);
                    $stmtP->execute($paramsPersona);
                }

                // Actualizar o insertar contacto telefónico si aplica
                if (!empty($entrada['telefono'])) {
                    $tel = trim((string) $entrada['telefono']);
                    $stmtCheckTel = $this->pdo->prepare("SELECT id FROM personas_contactos WHERE persona_id = ? AND tipo_contacto = 'TELEFONO' LIMIT 1");
                    $stmtCheckTel->execute([$personaId]);
                    $telId = $stmtCheckTel->fetchColumn();

                    if ($telId) {
                        $this->pdo->prepare("UPDATE personas_contactos SET valor = ?, actualizado_en = NOW() WHERE id = ?")->execute([$tel, $telId]);
                    } else {
                        $this->pdo->prepare("INSERT INTO personas_contactos (persona_id, tipo_contacto, valor, es_principal, estado, creado_en) VALUES (?, 'TELEFONO', ?, 1, 'ACTIVO', NOW())")->execute([$personaId, $tel]);
                    }
                }

                // Actualizar o insertar contacto email si aplica
                if (!empty($entrada['email'])) {
                    $em = trim((string) $entrada['email']);
                    $stmtCheckEm = $this->pdo->prepare("SELECT id FROM personas_contactos WHERE persona_id = ? AND tipo_contacto = 'EMAIL' LIMIT 1");
                    $stmtCheckEm->execute([$personaId]);
                    $emId = $stmtCheckEm->fetchColumn();

                    if ($emId) {
                        $this->pdo->prepare("UPDATE personas_contactos SET valor = ?, actualizado_en = NOW() WHERE id = ?")->execute([$em, $emId]);
                    } else {
                        $this->pdo->prepare("INSERT INTO personas_contactos (persona_id, tipo_contacto, valor, es_principal, estado, creado_en) VALUES (?, 'EMAIL', ?, 1, 'ACTIVO', NOW())")->execute([$personaId, $em]);
                    }
                }
            }

            // Auditoría
            $actorId = $this->resolverActorId();
            $auditoriaServicio = new AuditoriaServicio($this->pdo);
            $auditoriaServicio->registrar(
                'EDITAR',
                'personal',
                'colaboradores',
                $id,
                "Actualización de datos del colaborador [{$colab->obtenerCodigo()}].",
                null,
                $entrada,
                ['origen' => 'PERSONAL-1A'],
                $actorId
            );

            return Respuesta::json([
                'exito' => true,
                'mensaje' => "Colaborador [{$colab->obtenerCodigo()}] actualizado correctamente.",
                'datos' => $this->colaboradorServicio->obtenerFichaCompleta($id),
            ]);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Transición o cambio de cargo dentro del vínculo laboral activo.
     */
    public function apiCambiarCargo(string|int|array $id): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if ($usuario === null || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'personal.gestionar')) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'No autorizado.'], 403);
        }

        $entrada = $this->leerEntrada();
        $errorCsrf = $this->validarCsrf($entrada);
        if ($errorCsrf !== null) {
            return $errorCsrf;
        }

        $id = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;

        try {
            $nuevoCargoId = isset($entrada['nuevo_cargo_id'])
                ? (int) $entrada['nuevo_cargo_id']
                : (isset($entrada['cargo_id']) ? (int) $entrada['cargo_id'] : 0);
            $fechaCambio = isset($entrada['fecha_cambio']) ? trim((string) $entrada['fecha_cambio']) : date('Y-m-d');
            $observaciones = $entrada['observaciones'] ?? null;
            $actorId = $this->resolverActorId();

            $colab = $this->colaboradorServicio->cambiarCargo($id, $nuevoCargoId, $fechaCambio, $observaciones, $actorId);

            return Respuesta::json([
                'exito' => true,
                'mensaje' => "Cambio de cargo registrado correctamente para [{$colab->obtenerCodigo()}].",
                'datos' => $this->colaboradorServicio->obtenerFichaCompleta($id),
            ]);
        } catch (ValidacionExcepcion $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (EntidadNoEncontradaExcepcion $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Cese del vínculo laboral de un colaborador.
     */
    public function apiCesar(string|int|array $id): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if ($usuario === null || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'personal.gestionar')) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'No autorizado.'], 403);
        }

        $entrada = $this->leerEntrada();
        $errorCsrf = $this->validarCsrf($entrada);
        if ($errorCsrf !== null) {
            return $errorCsrf;
        }

        $id = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;

        try {
            $fechaCese = isset($entrada['fecha_cese']) ? trim((string) $entrada['fecha_cese']) : date('Y-m-d');
            $motivoCese = isset($entrada['motivo_cese']) ? trim((string) $entrada['motivo_cese']) : 'RENUNCIA';
            $observaciones = $entrada['observaciones'] ?? null;
            $actorId = $this->resolverActorId();

            $colab = $this->colaboradorServicio->cesarColaborador($id, $fechaCese, $motivoCese, $observaciones, $actorId);

            return Respuesta::json([
                'exito' => true,
                'mensaje' => "Cese laboral de [{$colab->obtenerCodigo()}] registrado correctamente.",
                'datos' => $this->colaboradorServicio->obtenerFichaCompleta($id),
            ]);
        } catch (ValidacionExcepcion $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (EntidadNoEncontradaExcepcion $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Reingreso laboral de un colaborador previamente cesado.
     */
    public function apiReingresar(string|int|array $id): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if ($usuario === null || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'personal.gestionar')) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'No autorizado.'], 403);
        }

        $entrada = $this->leerEntrada();
        $errorCsrf = $this->validarCsrf($entrada);
        if ($errorCsrf !== null) {
            return $errorCsrf;
        }

        $id = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;

        try {
            $cargoId = isset($entrada['cargo_id']) ? (int) $entrada['cargo_id'] : 0;
            $fechaInicio = isset($entrada['fecha_inicio']) && trim((string) $entrada['fecha_inicio']) !== ''
                ? trim((string) $entrada['fecha_inicio'])
                : (isset($entrada['fecha_reingreso']) && trim((string) $entrada['fecha_reingreso']) !== '' ? trim((string) $entrada['fecha_reingreso']) : date('Y-m-d'));
            $observaciones = $entrada['observaciones'] ?? null;
            $actorId = $this->resolverActorId();

            $colab = $this->colaboradorServicio->reingresarColaborador($id, [
                'cargo_id' => $cargoId,
                'fecha_inicio' => $fechaInicio,
                'observaciones' => $observaciones,
            ], $actorId);

            return Respuesta::json([
                'exito' => true,
                'mensaje' => "Reingreso laboral de [{$colab->obtenerCodigo()}] registrado exitosamente.",
                'datos' => $this->colaboradorServicio->obtenerFichaCompleta($id),
            ]);
        } catch (EpisodioLaboralActivoExcepcion|SolapamientoLaboralExcepcion $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 409);
        } catch (ValidacionExcepcion $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (EntidadNoEncontradaExcepcion $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }
}
