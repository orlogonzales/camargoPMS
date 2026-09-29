<?php

declare(strict_types=1);

namespace CamargoPMS\Controladores;

use CamargoPMS\Excepciones\ConflictoEmpresaExcepcion;
use CamargoPMS\Excepciones\EmpresaNoEncontradaExcepcion;
use CamargoPMS\Excepciones\ValidacionEmpresaExcepcion;
use CamargoPMS\Modelos\Usuario;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Nucleo\Vista;
use CamargoPMS\Repositorios\ActorAuditoriaRepositorio;
use CamargoPMS\Repositorios\EmpresaRepositorio;
use CamargoPMS\Repositorios\PersonaRepositorio;
use CamargoPMS\Repositorios\PropiedadRepositorio;
use CamargoPMS\Servicios\AuditoriaServicio;
use CamargoPMS\Servicios\AutorizacionServicio;
use CamargoPMS\Servicios\CsrfServicio;
use CamargoPMS\Servicios\EmpresaServicio;
use CamargoPMS\Servicios\SesionServicio;
use PDO;
use Throwable;

/**
 * Controlador HTTP y API REST para el Maestro de Empresas y Emisores Legales (EMPRESA-1).
 */
class EmpresaControlador
{
    private Vista $vista;
    private EmpresaServicio $empresaServicio;
    private EmpresaRepositorio $empresaRepo;
    private PersonaRepositorio $personaRepo;
    private PropiedadRepositorio $propiedadRepo;
    private SesionServicio $sesionServicio;
    private AutorizacionServicio $autorizacionServicio;
    private CsrfServicio $csrfServicio;
    private PDO $pdo;

    public function __construct(
        ?Vista $vista = null,
        ?EmpresaServicio $empresaServicio = null,
        ?EmpresaRepositorio $empresaRepo = null,
        ?PersonaRepositorio $personaRepo = null,
        ?PropiedadRepositorio $propiedadRepo = null,
        ?SesionServicio $sesionServicio = null,
        ?AutorizacionServicio $autorizacionServicio = null,
        ?CsrfServicio $csrfServicio = null,
        ?PDO $pdo = null
    ) {
        $this->vista = $vista ?? new Vista();
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->empresaRepo = $empresaRepo ?? new EmpresaRepositorio($this->pdo);
        $this->personaRepo = $personaRepo ?? new PersonaRepositorio($this->pdo);
        $this->propiedadRepo = $propiedadRepo ?? new PropiedadRepositorio($this->pdo);
        $this->empresaServicio = $empresaServicio ?? new EmpresaServicio(
            $this->pdo,
            $this->empresaRepo,
            $this->personaRepo,
            $this->propiedadRepo,
            new AuditoriaServicio($this->pdo)
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
     * Vista principal del Maestro de Empresas.
     */
    public function index(): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if ($usuario === null) {
            return Respuesta::redirigir('/login');
        }

        $usuarioId = (int) $usuario->obtenerId();
        if (!$this->autorizacionServicio->puede($usuarioId, 'empresa.ver')) {
            return new Respuesta($this->vista->renderizar('errores/403', [
                'titulo' => '403 — Acceso Denegado',
                'mensaje' => 'No cuenta con permisos para consultar el catálogo de empresas y emisores legales.',
                'usuario' => $usuario,
            ]), 403);
        }

        $empresas = $this->empresaServicio->listarEmpresas([], 100);

        // Listado de personas para asociar representantes
        $stmtPer = $this->pdo->query('SELECT p.id, p.nombres, p.apellido_paterno, p.apellido_materno, pd.numero_documento
                                      FROM personas p
                                      LEFT JOIN personas_documentos pd ON pd.persona_id = p.id AND pd.es_principal = 1
                                      WHERE p.estado = "ACTIVO"
                                      ORDER BY p.nombres ASC, p.apellido_paterno ASC');
        $personas = $stmtPer->fetchAll(PDO::FETCH_ASSOC);

        // Propiedades para asignar
        $stmtProp = $this->pdo->query('SELECT id, codigo, nombre, empresa_id FROM propiedades WHERE estado = "ACTIVO" ORDER BY nombre ASC');
        $propiedades = $stmtProp->fetchAll(PDO::FETCH_ASSOC);

        // Paises
        $stmtPais = $this->pdo->query('SELECT id, codigo_iso2 AS codigo_iso, nombre FROM paises WHERE activo = 1 ORDER BY nombre ASC');
        $paises = $stmtPais->fetchAll(PDO::FETCH_ASSOC);

        // Tipos de documento
        $stmtTipos = $this->pdo->query('SELECT id, codigo, nombre FROM tipos_documento WHERE activo = 1 ORDER BY id ASC');
        $tiposDocumento = $stmtTipos->fetchAll(PDO::FETCH_ASSOC);

        $permisos = [
            'puede_crear' => $this->autorizacionServicio->puede($usuarioId, 'empresa.crear'),
            'puede_editar' => $this->autorizacionServicio->puede($usuarioId, 'empresa.editar'),
            'puede_cambiar_estado' => $this->autorizacionServicio->puede($usuarioId, 'empresa.cambiar_estado'),
        ];

        $html = $this->vista->renderizar('empresas/index', [
            'titulo' => 'Empresas y Emisores Legales — Camargo PMS',
            'usuario' => $usuario,
            'empresas' => $empresas,
            'personas' => $personas,
            'propiedades' => $propiedades,
            'paises' => $paises,
            'tiposDocumento' => $tiposDocumento,
            'permisos' => $permisos,
            'csrf_token' => $this->csrfServicio->generarToken(),
        ]);

        return new Respuesta($html);
    }

    /**
     * API: Listado de empresas en formato JSON.
     */
    public function apiListar(): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if ($usuario === null || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'empresa.ver')) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'No autorizado.'], 403);
        }

        try {
            $filtros = [
                'estado' => $_GET['estado'] ?? null,
                'q' => $_GET['q'] ?? null,
                'es_principal' => $_GET['es_principal'] ?? null,
            ];

            $limite = min(100, max(1, (int) ($_GET['limite'] ?? 50)));
            $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
            $offset = ($pagina - 1) * $limite;

            $total = $this->empresaRepo->contar($filtros);
            $empresas = $this->empresaServicio->listarEmpresas($filtros, $limite, $offset);

            $datos = array_map(fn($e) => $e->aArreglo(), $empresas);

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
     * API: Detalle completo de una empresa.
     */
    public function apiDetalle(string|int|array $id): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if ($usuario === null || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'empresa.ver')) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'No autorizado.'], 403);
        }

        $id = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;
        $empresa = $this->empresaServicio->obtenerEmpresa($id);
        if ($empresa === null) {
            return Respuesta::json(['exito' => false, 'mensaje' => "Empresa [{$id}] no encontrada."], 404);
        }

        $datos = $empresa->aArreglo();
        $datos['propiedades'] = $this->empresaRepo->obtenerPropiedadesVinculadas($id);

        return Respuesta::json([
            'exito' => true,
            'datos' => $datos,
        ]);
    }

    /**
     * API: Crear nueva empresa.
     */
    public function apiCrear(): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if ($usuario === null || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'empresa.crear')) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'No autorizado.'], 403);
        }

        $entrada = $this->leerEntrada();
        $errorCsrf = $this->validarCsrf($entrada);
        if ($errorCsrf !== null) {
            return $errorCsrf;
        }

        try {
            $actorId = $this->resolverActorId();
            $archivoLogo = $_FILES['logo'] ?? null;

            $empresa = $this->empresaServicio->crearEmpresa($entrada, $actorId, $archivoLogo);

            return Respuesta::json([
                'exito' => true,
                'mensaje' => "Empresa [{$empresa->obtenerCodigo()}] registrada exitosamente.",
                'datos' => $empresa->aArreglo(),
            ], 201);
        } catch (ValidacionEmpresaExcepcion $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (ConflictoEmpresaExcepcion $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 409);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Actualizar datos de una empresa.
     */
    public function apiActualizar(string|int|array $id): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if ($usuario === null || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'empresa.editar')) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'No autorizado.'], 403);
        }

        $entrada = $this->leerEntrada();
        $errorCsrf = $this->validarCsrf($entrada);
        if ($errorCsrf !== null) {
            return $errorCsrf;
        }

        $id = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;

        try {
            $actorId = $this->resolverActorId();
            $archivoLogo = $_FILES['logo'] ?? null;

            $empresa = $this->empresaServicio->actualizarEmpresa($id, $entrada, $actorId, $archivoLogo);

            return Respuesta::json([
                'exito' => true,
                'mensaje' => "Empresa [{$empresa->obtenerCodigo()}] actualizada correctamente.",
                'datos' => $empresa->aArreglo(),
            ]);
        } catch (EmpresaNoEncontradaExcepcion $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (ValidacionEmpresaExcepcion $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (ConflictoEmpresaExcepcion $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 409);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Cambiar estado operativo (ACTIVO <-> INACTIVO).
     */
    public function apiCambiarEstado(string|int|array $id): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if ($usuario === null || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'empresa.cambiar_estado')) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'No autorizado.'], 403);
        }

        $entrada = $this->leerEntrada();
        $errorCsrf = $this->validarCsrf($entrada);
        if ($errorCsrf !== null) {
            return $errorCsrf;
        }

        $id = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;

        try {
            $nuevoEstado = (string) ($entrada['estado'] ?? '');
            $motivo = (string) ($entrada['motivo'] ?? '');
            $actorId = $this->resolverActorId();

            $empresa = $this->empresaServicio->cambiarEstado($id, $nuevoEstado, $actorId, $motivo);

            return Respuesta::json([
                'exito' => true,
                'mensaje' => "Estado de la empresa [{$empresa->obtenerCodigo()}] cambiado a {$empresa->obtenerEstado()}.",
                'datos' => $empresa->aArreglo(),
            ]);
        } catch (EmpresaNoEncontradaExcepcion $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (ValidacionEmpresaExcepcion $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (ConflictoEmpresaExcepcion $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 409);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Asignar propiedades a una empresa operadora.
     */
    public function apiAsignarPropiedades(string|int|array $id): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if ($usuario === null || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'empresa.editar')) {
            return Respuesta::json(['exito' => false, 'mensaje' => 'No autorizado.'], 403);
        }

        $entrada = $this->leerEntrada();
        $errorCsrf = $this->validarCsrf($entrada);
        if ($errorCsrf !== null) {
            return $errorCsrf;
        }

        $id = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;

        try {
            $propiedadIds = isset($entrada['propiedad_ids']) && is_array($entrada['propiedad_ids'])
                ? array_map('intval', $entrada['propiedad_ids'])
                : [];

            $actorId = $this->resolverActorId();
            $this->empresaServicio->asignarPropiedades($id, $propiedadIds, $actorId);

            return Respuesta::json([
                'exito' => true,
                'mensaje' => 'Propiedades asignadas exitosamente a la empresa operadora.',
                'propiedades' => $this->empresaRepo->obtenerPropiedadesVinculadas($id),
            ]);
        } catch (EmpresaNoEncontradaExcepcion $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }

    /**
     * Servir archivo binario del logotipo corporativo de forma segura.
     */
    public function verLogo(string|int|array $id): Respuesta
    {
        $id = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;
        $rutaFisica = $this->empresaServicio->obtenerRutaFisicaLogo($id);

        if (!$rutaFisica || !file_exists($rutaFisica)) {
            // Servir logo default o 404
            return new Respuesta('Logotipo no encontrado.', 404, ['Content-Type' => 'text/plain']);
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $rutaFisica);
        finfo_close($finfo);

        $contenido = file_get_contents($rutaFisica);

        return new Respuesta($contenido, 200, [
            'Content-Type' => $mime,
            'Content-Length' => (string) strlen($contenido),
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
