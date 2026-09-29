<?php

declare(strict_types=1);

namespace CamargoPMS\Controladores;

use CamargoPMS\Excepciones\ClienteDuplicadoExcepcion;
use CamargoPMS\Excepciones\ClienteNoEncontradoExcepcion;
use CamargoPMS\Excepciones\EntidadNoEncontradaExcepcion;
use CamargoPMS\Excepciones\ValidacionClienteExcepcion;
use CamargoPMS\Excepciones\ValidacionExcepcion;
use CamargoPMS\Modelos\Cliente;
use CamargoPMS\Modelos\Usuario;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Nucleo\Vista;
use CamargoPMS\Repositorios\ActorAuditoriaRepositorio;
use CamargoPMS\Repositorios\ClienteCategoriaRepositorio;
use CamargoPMS\Repositorios\ClienteRepositorio;
use CamargoPMS\Repositorios\GeografiaRepositorio;
use CamargoPMS\Repositorios\PersonaRepositorio;
use CamargoPMS\Repositorios\TipoDocumentoRepositorio;
use CamargoPMS\Servicios\AuditoriaServicio;
use CamargoPMS\Servicios\AutorizacionServicio;
use CamargoPMS\Servicios\ClienteServicio;
use CamargoPMS\Servicios\CsrfServicio;
use CamargoPMS\Servicios\GeografiaServicio;
use CamargoPMS\Servicios\PersonaServicio;
use CamargoPMS\Servicios\SesionServicio;
use PDO;
use Throwable;

/**
 * Controlador para la Gestión Comercial de Clientes y Ficha 360° (CLIENTES-1).
 *
 * Principio Vinculante:
 * PERSONA ≠ CLIENTE pero CLIENTE -> PERSONA.
 */
class ClienteControlador
{
    private Vista $vista;
    private PDO $pdo;
    private ClienteRepositorio $clienteRepo;
    private ClienteCategoriaRepositorio $categoriaRepo;
    private PersonaRepositorio $personaRepo;
    private TipoDocumentoRepositorio $tipoDocRepo;
    private ClienteServicio $clienteServicio;
    private PersonaServicio $personaServicio;
    private GeografiaServicio $geografiaServicio;
    private SesionServicio $sesionServicio;
    private AutorizacionServicio $autorizacionServicio;
    private CsrfServicio $csrfServicio;

    public function __construct(
        ?Vista $vista = null,
        ?PDO $pdo = null,
        ?ClienteRepositorio $clienteRepo = null,
        ?ClienteCategoriaRepositorio $categoriaRepo = null,
        ?PersonaRepositorio $personaRepo = null,
        ?TipoDocumentoRepositorio $tipoDocRepo = null,
        ?ClienteServicio $clienteServicio = null,
        ?PersonaServicio $personaServicio = null,
        ?GeografiaServicio $geografiaServicio = null,
        ?SesionServicio $sesionServicio = null,
        ?AutorizacionServicio $autorizacionServicio = null,
        ?CsrfServicio $csrfServicio = null
    ) {
        $this->vista = $vista ?? new Vista();
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->clienteRepo = $clienteRepo ?? new ClienteRepositorio($this->pdo);
        $this->categoriaRepo = $categoriaRepo ?? new ClienteCategoriaRepositorio($this->pdo);
        $this->personaRepo = $personaRepo ?? new PersonaRepositorio($this->pdo);
        $this->tipoDocRepo = $tipoDocRepo ?? new TipoDocumentoRepositorio($this->pdo);
        $this->geografiaServicio = $geografiaServicio ?? new GeografiaServicio(new GeografiaRepositorio($this->pdo));
        $this->personaServicio = $personaServicio ?? new PersonaServicio(
            $this->pdo,
            $this->personaRepo,
            null,
            null,
            null,
            $this->tipoDocRepo,
            $this->geografiaServicio
        );
        $this->clienteServicio = $clienteServicio ?? new ClienteServicio(
            $this->pdo,
            $this->clienteRepo,
            $this->categoriaRepo,
            $this->personaRepo,
            $this->personaServicio
        );
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
        // En peticiones puras de API con sesión ya validada o cabecera X-CSRF-Token
        $token = $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? $_POST['csrf_token']
            ?? $_POST['_csrf_token']
            ?? ($datos['csrf_token'] ?? null)
            ?? ($datos['_csrf_token'] ?? null);

        // Valida presencia y vigencia del token CSRF en peticiones mutantes
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
     * Directorio Comercial de Clientes (GET /clientes).
     */
    public function index(): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if ($usuario === null) {
            return Respuesta::redirigir('/login');
        }

        $usuarioId = (int) $usuario->obtenerId();
        if (!$this->autorizacionServicio->puede($usuarioId, 'clientes.ver')) {
            return new Respuesta($this->vista->renderizar('errores/403', [
                'titulo' => '403 — Acceso Denegado',
                'mensaje' => 'No cuenta con permisos para consultar el directorio de clientes.',
                'usuario' => $usuario,
            ]), 403);
        }

        // Catálogos auxiliares
        $categorias = $this->clienteServicio->listarCategorias(true);
        $canales = ClienteServicio::CANALES_CAPTACION;

        // Catálogos geográficos y documentales para Alta rápida de Persona
        $stmtTipos = $this->pdo->query('SELECT id, codigo, nombre FROM tipos_documento WHERE activo = 1 ORDER BY id ASC');
        $tiposDocumento = $stmtTipos->fetchAll(PDO::FETCH_ASSOC);

        $paisDefault = $this->geografiaServicio->obtenerPaisDefault();
        $departamentos = $this->geografiaServicio->listarDepartamentos((int) $paisDefault['id']);
        $stmtPais = $this->pdo->query('SELECT id, codigo_iso2, codigo_iso3, nombre, nacionalidad FROM paises WHERE activo = 1 ORDER BY nombre ASC');
        $paises = $stmtPais->fetchAll(PDO::FETCH_ASSOC);

        // Métricas iniciales rápidas
        $totalClientes = $this->clienteRepo->contar();
        $totalActivos = $this->clienteRepo->contar(['estado' => 'ACTIVO']);
        $totalBloqueados = $this->clienteRepo->contar(['estado' => 'BLOQUEADO']);

        $permisos = [
            'puede_crear' => $this->autorizacionServicio->puede($usuarioId, 'clientes.crear'),
            'puede_editar' => $this->autorizacionServicio->puede($usuarioId, 'clientes.editar'),
            'puede_bloquear' => $this->autorizacionServicio->puede($usuarioId, 'clientes.bloquear'),
            'puede_gestionar_categorias' => $this->autorizacionServicio->puede($usuarioId, 'clientes.gestionar_categorias'),
        ];

        $html = $this->vista->renderizar('clientes/index', [
            'titulo' => 'Clientes Comerciales — Camargo PMS',
            'usuario' => $usuario,
            'categorias' => $categorias,
            'canales' => $canales,
            'tiposDocumento' => $tiposDocumento,
            'paisDefault' => $paisDefault,
            'departamentos' => $departamentos,
            'paises' => $paises,
            'kpis' => [
                'total' => $totalClientes,
                'activos' => $totalActivos,
                'bloqueados' => $totalBloqueados,
            ],
            'permisos' => $permisos,
            'csrf_token' => $this->csrfServicio->generarToken(),
        ]);

        return new Respuesta($html);
    }

    /**
     * Endpoint API JSON para el listado paginado y filtrado de clientes (GET /clientes/datos o /api/clientes).
     */
    public function apiListar(): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if ($usuario === null) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'No autenticado.'], 401);
        }

        $usuarioId = (int) $usuario->obtenerId();
        if (!$this->autorizacionServicio->puede($usuarioId, 'clientes.ver')) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'Permiso denegado.'], 403);
        }

        $pagina = max(1, (int) ($_GET['pagina'] ?? $_GET['page'] ?? 1));
        $limite = max(1, min(100, (int) ($_GET['limite'] ?? $_GET['limit'] ?? 20)));
        $offset = ($pagina - 1) * $limite;

        $filtros = [];
        if (!empty($_GET['buscar'])) {
            $filtros['buscar'] = trim((string) $_GET['buscar']);
        }
        if (!empty($_GET['categoria_id'])) {
            $filtros['categoria_id'] = (int) $_GET['categoria_id'];
        }
        if (!empty($_GET['estado'])) {
            $filtros['estado'] = trim((string) $_GET['estado']);
        }
        if (!empty($_GET['canal_captacion'])) {
            $filtros['canal_captacion'] = trim((string) $_GET['canal_captacion']);
        }

        $total = $this->clienteServicio->contarClientes($filtros);
        $clientes = $this->clienteServicio->listarClientes($filtros, $limite, $offset);

        return Respuesta::json([
            'exito' => true,
            'datos' => $clientes,
            'paginacion' => [
                'total' => $total,
                'pagina' => $pagina,
                'limite' => $limite,
                'total_paginas' => (int) ceil($total / $limite),
            ],
        ]);
    }

    /**
     * Retorna el catálogo de categorías en JSON (GET /api/clientes/categorias).
     */
    public function apiCategorias(): Respuesta
    {
        $categorias = array_map(function ($c) {
            return $c->aArreglo();
        }, $this->clienteServicio->listarCategorias(true));

        return Respuesta::json(['exito' => true, 'datos' => $categorias]);
    }

    /**
     * Retorna el catálogo de canales de captación en JSON (GET /api/clientes/canales).
     */
    public function apiCanales(): Respuesta
    {
        return Respuesta::json(['exito' => true, 'datos' => ClienteServicio::CANALES_CAPTACION]);
    }

    /**
     * Búsqueda de personas para asociar perfil de cliente (GET /api/clientes/buscar-persona).
     */
    public function apiBuscarPersona(): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if ($usuario === null) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'No autenticado.'], 401);
        }

        $q = isset($_GET['q']) ? trim((string) $_GET['q']) : null;
        $doc = isset($_GET['documento']) ? trim((string) $_GET['documento']) : null;

        if ($doc !== null && $doc !== '') {
            $sql = "SELECT p.id, p.nombres, p.apellido_paterno, p.apellido_materno,
                           CONCAT(p.nombres, ' ', p.apellido_paterno, IF(p.apellido_materno IS NOT NULL AND p.apellido_materno <> '', CONCAT(' ', p.apellido_materno), '')) AS nombre_completo,
                           p.genero, p.fecha_nacimiento, p.pais_nacionalidad_id, p.pais_residencia_id,
                           p.distrito_id, p.region_residencia_extranjera, p.ciudad_residencia_extranjera, p.direccion,
                           td.codigo AS tipo_documento, pd.tipo_documento_id, pd.numero_documento,
                           c.id AS cliente_id, c.codigo AS cliente_codigo, c.estado AS cliente_estado
                    FROM personas_documentos pd
                    INNER JOIN personas p ON p.id = pd.persona_id
                    LEFT JOIN tipos_documento td ON td.id = pd.tipo_documento_id
                    LEFT JOIN clientes c ON c.persona_id = p.id
                    WHERE pd.numero_documento = :doc
                    LIMIT 1";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':doc' => $doc]);
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($fila) {
                return Respuesta::json([
                    'exito' => true,
                    'encontrado' => true,
                    'persona' => $fila,
                    'es_cliente' => !empty($fila['cliente_id']),
                    'cliente_codigo' => $fila['cliente_codigo'] ?? null,
                ]);
            }

            return Respuesta::json(['exito' => true, 'encontrado' => false]);
        }

        if ($q !== null && strlen($q) >= 2) {
            $sql = "SELECT p.id, p.nombres, p.apellido_paterno, p.apellido_materno,
                           CONCAT(p.nombres, ' ', p.apellido_paterno, IF(p.apellido_materno IS NOT NULL AND p.apellido_materno <> '', CONCAT(' ', p.apellido_materno), '')) AS nombre_completo,
                           p.genero, p.fecha_nacimiento,
                           td.codigo AS tipo_documento, pd.numero_documento,
                           c.id AS cliente_id, c.codigo AS cliente_codigo, c.estado AS cliente_estado
                    FROM personas p
                    LEFT JOIN personas_documentos pd ON pd.persona_id = p.id AND pd.es_principal = 1
                    LEFT JOIN tipos_documento td ON td.id = pd.tipo_documento_id
                    LEFT JOIN clientes c ON c.persona_id = p.id
                    WHERE p.nombres LIKE :q1 OR p.apellido_paterno LIKE :q2 OR p.apellido_materno LIKE :q3 OR pd.numero_documento LIKE :q4
                    LIMIT 10";
            $stmt = $this->pdo->prepare($sql);
            $paramQ = "%{$q}%";
            $stmt->execute([':q1' => $paramQ, ':q2' => $paramQ, ':q3' => $paramQ, ':q4' => $paramQ]);
            $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($resultados as &$resItem) {
                $resItem['es_cliente'] = !empty($resItem['cliente_id']);
            }
            unset($resItem);

            return Respuesta::json([
                'exito' => true,
                'resultados' => $resultados,
                'datos' => $resultados,
            ]);
        }

        return Respuesta::json(['exito' => true, 'resultados' => []]);
    }

    /**
     * Vista de Ficha Integral 360° en Página Completa (GET /clientes/{id}).
     *
     * @param array<string, mixed> $parametros
     */
    public function detalle(string|int|array $id): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if ($usuario === null) {
            return Respuesta::redirigir('/login');
        }

        $usuarioId = (int) $usuario->obtenerId();
        if (!$this->autorizacionServicio->puede($usuarioId, 'clientes.ver')) {
            return new Respuesta($this->vista->renderizar('errores/403', [
                'titulo' => '403 — Acceso Denegado',
                'mensaje' => 'No cuenta con permisos para consultar la ficha integral 360° del cliente.',
                'usuario' => $usuario,
            ]), 403);
        }

        $clienteId = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;
        if ($clienteId <= 0) {
            return Respuesta::redirigir('/clientes');
        }

        try {
            $ficha360 = $this->clienteServicio->obtenerFicha360($clienteId);
        } catch (ClienteNoEncontradoExcepcion) {
            return new Respuesta($this->vista->renderizar('errores/404', [
                'titulo' => '404 — Cliente No Encontrado',
                'mensaje' => "El perfil comercial de cliente ID [{$clienteId}] no existe en el sistema.",
                'usuario' => $usuario,
            ]), 404);
        }

        $categorias = $this->clienteServicio->listarCategorias(true);
        $canales = ClienteServicio::CANALES_CAPTACION;

        $permisos = [
            'puede_editar' => $this->autorizacionServicio->puede($usuarioId, 'clientes.editar'),
            'puede_bloquear' => $this->autorizacionServicio->puede($usuarioId, 'clientes.bloquear'),
        ];

        $html = $this->vista->renderizar('clientes/detalle', [
            'titulo' => "Ficha 360°: {$ficha360['cliente']['codigo']} — {$ficha360['persona']['nombres']} {$ficha360['persona']['apellido_paterno']}",
            'usuario' => $usuario,
            'ficha' => $ficha360,
            'categorias' => $categorias,
            'canales' => $canales,
            'permisos' => $permisos,
            'csrf_token' => $this->csrfServicio->generarToken(),
        ]);

        return new Respuesta($html);
    }

    /**
     * Endpoint API JSON de Ficha Integral 360° (GET /api/clientes/{id}).
     *
     * @param string|int|array $id
     */
    public function apiFicha360(string|int|array $id): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if ($usuario === null) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'No autenticado.'], 401);
        }

        $usuarioId = (int) $usuario->obtenerId();
        if (!$this->autorizacionServicio->puede($usuarioId, 'clientes.ver')) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'Permiso denegado.'], 403);
        }

        $clienteId = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;
        if ($clienteId <= 0) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'ID inválido.'], 400);
        }

        try {
            $ficha360 = $this->clienteServicio->obtenerFicha360($clienteId);
            return Respuesta::json(['exito' => true, 'datos' => $ficha360]);
        } catch (ClienteNoEncontradoExcepcion $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'Error al obtener ficha 360: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Endpoint para registrar un nuevo cliente comercial (POST /clientes o /api/clientes).
     */
    public function apiCrear(): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if ($usuario === null) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'No autenticado.'], 401);
        }

        $usuarioId = (int) $usuario->obtenerId();
        if (!$this->autorizacionServicio->puede($usuarioId, 'clientes.crear')) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'Permiso denegado para crear clientes.'], 403);
        }

        $datos = $this->leerEntrada();
        $errorCsrf = $this->validarCsrf($datos);
        if ($errorCsrf !== null) {
            return $errorCsrf;
        }

        // Si se enviaron datos planos para una nueva persona, estructurarlos para PersonaServicio
        if (empty($datos['persona_id']) && !empty($datos['nombres']) && !empty($datos['apellido_paterno'])) {
            $datosPersona = [
                'nombres' => trim((string) $datos['nombres']),
                'apellido_paterno' => trim((string) $datos['apellido_paterno']),
                'apellido_materno' => isset($datos['apellido_materno']) ? trim((string) $datos['apellido_materno']) : null,
                'genero' => !empty($datos['genero']) ? trim((string) $datos['genero']) : 'NO_ESPECIFICADO',
                'fecha_nacimiento' => !empty($datos['fecha_nacimiento']) ? trim((string) $datos['fecha_nacimiento']) : null,
                'pais_nacionalidad_id' => !empty($datos['pais_nacionalidad_id']) ? (int) $datos['pais_nacionalidad_id'] : 1,
                'pais_residencia_id' => !empty($datos['pais_residencia_id']) ? (int) $datos['pais_residencia_id'] : 1,
                'distrito_id' => !empty($datos['distrito_id']) ? (int) $datos['distrito_id'] : null,
                'region_residencia_extranjera' => isset($datos['region_residencia_extranjera']) ? trim((string) $datos['region_residencia_extranjera']) : null,
                'ciudad_residencia_extranjera' => isset($datos['ciudad_residencia_extranjera']) ? trim((string) $datos['ciudad_residencia_extranjera']) : null,
                'direccion' => isset($datos['direccion']) ? trim((string) $datos['direccion']) : null,
            ];

            $docPrincipal = null;
            if (!empty($datos['tipo_documento_id']) && !empty($datos['numero_documento'])) {
                $docPrincipal = [
                    'tipo_documento_id' => (int) $datos['tipo_documento_id'],
                    'numero_documento' => trim((string) $datos['numero_documento']),
                    'pais_emisor_id' => !empty($datos['pais_emisor_id']) ? (int) $datos['pais_emisor_id'] : 1,
                ];
            }

            $contactos = [];
            if (!empty($datos['telefono'])) {
                $contactos[] = [
                    'tipo_contacto' => 'TELEFONO',
                    'valor' => trim((string) $datos['telefono']),
                    'es_whatsapp' => !empty($datos['es_whatsapp']) ? 1 : 0,
                    'es_principal' => 1,
                ];
            }
            if (!empty($datos['email'])) {
                $contactos[] = [
                    'tipo_contacto' => 'EMAIL',
                    'valor' => trim((string) $datos['email']),
                    'es_principal' => 1,
                ];
            }

            $datos['datos_persona'] = $datosPersona;
            $datos['documento_principal'] = $docPrincipal;
            $datos['contactos'] = $contactos;
        }

        $actorId = $this->resolverActorId();

        try {
            $cliente = $this->clienteServicio->crearCliente($datos, $actorId);

            return Respuesta::json([
                'exito' => true,
                'mensaje' => "Cliente comercial [{$cliente->obtenerCodigo()}] registrado correctamente.",
                'datos' => $cliente->aArreglo(),
                'cliente' => $cliente->aArreglo(),
                'id' => $cliente->obtenerId(),
                'codigo' => $cliente->obtenerCodigo(),
            ], 201);
        } catch (ClienteDuplicadoExcepcion $e) {
            return Respuesta::json([
                'exito' => false,
                'mensaje' => $e->getMessage(),
                'codigo_error' => 'CLIENTE_DUPLICADO',
            ], 409);
        } catch (ValidacionClienteExcepcion $e) {
            return Respuesta::json([
                'exito' => false,
                'mensaje' => $e->getMessage(),
                'errores' => $e->obtenerErrores(),
            ], 422);
        } catch (ValidacionExcepcion $e) {
            return Respuesta::json([
                'exito' => false,
                'mensaje' => $e->getMessage(),
                'errores' => $e->obtenerErrores(),
            ], 422);
        } catch (Throwable $e) {
            return Respuesta::json([
                'exito' => false,
                'mensaje' => 'Error al registrar cliente: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Endpoint para actualizar perfil comercial (PUT/POST /clientes/{id} o /api/clientes/{id}).
     *
     * @param string|int|array $id
     */
    public function apiActualizar(string|int|array $id): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if ($usuario === null) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'No autenticado.'], 401);
        }

        $usuarioId = (int) $usuario->obtenerId();
        if (!$this->autorizacionServicio->puede($usuarioId, 'clientes.editar')) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'Permiso denegado para editar clientes.'], 403);
        }

        $clienteId = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;
        if ($clienteId <= 0) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'ID inválido.'], 400);
        }

        $datos = $this->leerEntrada();
        $errorCsrf = $this->validarCsrf($datos);
        if ($errorCsrf !== null) {
            return $errorCsrf;
        }

        $actorId = $this->resolverActorId();

        try {
            $cliente = $this->clienteServicio->actualizarCliente($clienteId, $datos, $actorId);

            return Respuesta::json([
                'exito' => true,
                'mensaje' => 'Perfil comercial actualizado correctamente.',
                'datos' => $cliente->aArreglo(),
                'cliente' => $cliente->aArreglo(),
            ]);
        } catch (ClienteNoEncontradoExcepcion $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (ValidacionClienteExcepcion $e) {
            return Respuesta::json([
                'exito' => false,
                'mensaje' => $e->getMessage(),
                'errores' => $e->obtenerErrores(),
            ], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'Error al actualizar cliente: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Endpoint para bloquear comercialmente a un cliente (POST /clientes/{id}/bloquear).
     *
     * @param string|int|array $id
     */
    public function apiBloquear(string|int|array $id): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if ($usuario === null) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'No autenticado.'], 401);
        }

        $usuarioId = (int) $usuario->obtenerId();
        if (!$this->autorizacionServicio->puede($usuarioId, 'clientes.bloquear')) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'Permiso denegado para bloquear clientes.'], 403);
        }

        $clienteId = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;
        if ($clienteId <= 0) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'ID inválido.'], 400);
        }

        $datos = $this->leerEntrada();
        $errorCsrf = $this->validarCsrf($datos);
        if ($errorCsrf !== null) {
            return $errorCsrf;
        }

        $motivo = trim((string) ($datos['motivo'] ?? $datos['motivo_bloqueo'] ?? ''));
        if ($motivo === '') {
            return Respuesta::json([
                'exito' => false,
                'mensaje' => 'El motivo de bloqueo es obligatorio.',
                'errores' => ['motivo' => 'Motivo requerido'],
            ], 422);
        }

        $actorId = $this->resolverActorId();

        try {
            $cliente = $this->clienteServicio->cambiarEstado($clienteId, Cliente::ESTADO_BLOQUEADO, $motivo, $actorId);

            return Respuesta::json([
                'exito' => true,
                'mensaje' => "Cliente [{$cliente->obtenerCodigo()}] bloqueado comercialmente.",
                'datos' => $cliente->aArreglo(),
                'cliente' => $cliente->aArreglo(),
            ]);
        } catch (ClienteNoEncontradoExcepcion $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'Error al bloquear cliente: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Endpoint para desbloquear/reactivar comercialmente a un cliente (POST /clientes/{id}/desbloquear).
     *
     * @param string|int|array $id
     */
    public function apiDesbloquear(string|int|array $id): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if ($usuario === null) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'No autenticado.'], 401);
        }

        $usuarioId = (int) $usuario->obtenerId();
        if (!$this->autorizacionServicio->puede($usuarioId, 'clientes.bloquear')) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'Permiso denegado para desbloquear clientes.'], 403);
        }

        $clienteId = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;
        if ($clienteId <= 0) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'ID inválido.'], 400);
        }

        $datos = $this->leerEntrada();
        $errorCsrf = $this->validarCsrf($datos);
        if ($errorCsrf !== null) {
            return $errorCsrf;
        }

        $actorId = $this->resolverActorId();

        try {
            $cliente = $this->clienteServicio->cambiarEstado($clienteId, Cliente::ESTADO_ACTIVO, null, $actorId);

            return Respuesta::json([
                'exito' => true,
                'mensaje' => "Cliente [{$cliente->obtenerCodigo()}] reactivado exitosamente.",
                'datos' => $cliente->aArreglo(),
                'cliente' => $cliente->aArreglo(),
            ]);
        } catch (ClienteNoEncontradoExcepcion $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'Error al desbloquear cliente: ' . $e->getMessage()], 500);
        }
    }
}
