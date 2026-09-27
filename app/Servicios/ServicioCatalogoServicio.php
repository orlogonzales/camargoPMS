<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

use CamargoPMS\Excepciones\ProveedorNoEncontradoExcepcion;
use CamargoPMS\Excepciones\ServicioNoEncontradoExcepcion;
use CamargoPMS\Excepciones\ValidacionExcepcion;
use CamargoPMS\Modelos\AccionAuditoria;
use CamargoPMS\Modelos\Servicio;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Repositorios\PropiedadRepositorio;
use CamargoPMS\Repositorios\ProveedorRepositorio;
use CamargoPMS\Repositorios\ServicioRepositorio;
use PDO;
use Throwable;

/**
 * Servicio de dominio para la gestión del catálogo maestro de servicios adicionales,
 * categorías, modalidades de cobro y asignación N:M de proveedores homologados.
 */
class ServicioCatalogoServicio
{
    private PDO $pdo;
    private ServicioRepositorio $servicioRepo;
    private ProveedorRepositorio $proveedorRepo;
    private PropiedadRepositorio $propiedadRepo;
    private AuditoriaServicio $auditoriaServicio;

    public function __construct(
        ?PDO $pdo = null,
        ?ServicioRepositorio $servicioRepo = null,
        ?ProveedorRepositorio $proveedorRepo = null,
        ?PropiedadRepositorio $propiedadRepo = null,
        ?AuditoriaServicio $auditoriaServicio = null
    ) {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->servicioRepo = $servicioRepo ?? new ServicioRepositorio($this->pdo);
        $this->proveedorRepo = $proveedorRepo ?? new ProveedorRepositorio($this->pdo);
        $this->propiedadRepo = $propiedadRepo ?? new PropiedadRepositorio($this->pdo);
        $this->auditoriaServicio = $auditoriaServicio ?? new AuditoriaServicio($this->pdo);
    }

    /**
     * Lista servicios con filtros.
     *
     * @param array<string, mixed> $filtros
     * @return array<int, Servicio>
     */
    public function listarServicios(array $filtros = []): array
    {
        return $this->servicioRepo->listar($filtros);
    }

    /**
     * Obtiene un concepto del catálogo por ID.
     */
    public function obtenerServicioPorId(int $id, bool $cargarProveedores = true): Servicio
    {
        $servicio = $this->servicioRepo->buscarPorId($id, $cargarProveedores);
        if (!$servicio) {
            throw new ServicioNoEncontradoExcepcion("No se encontró el servicio con ID {$id}.");
        }
        return $servicio;
    }

    /**
     * Crea un nuevo concepto en el catálogo maestro.
     *
     * @param array<string, mixed> $datos
     */
    public function crearServicio(array $datos, int $actorId): Servicio
    {
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        if ($nombre === '') {
            throw new ValidacionExcepcion('El nombre descriptivo del servicio es obligatorio.');
        }

        $categoriaId = (int) ($datos['categoria_id'] ?? 0);
        $categoria = $this->servicioRepo->buscarCategoriaPorId($categoriaId);
        if (!$categoria || !$categoria->esActiva()) {
            throw new ValidacionExcepcion("La categoría de servicio seleccionada no es válida o está inactiva.");
        }

        $modalidadId = (int) ($datos['modalidad_cobro_id'] ?? 0);
        $modalidad = $this->servicioRepo->buscarModalidadPorId($modalidadId);
        if (!$modalidad || !$modalidad->esActiva()) {
            throw new ValidacionExcepcion("La modalidad de cobro seleccionada no es válida o está inactiva.");
        }

        $propiedadId = isset($datos['propiedad_id']) && $datos['propiedad_id'] !== '' ? (int) $datos['propiedad_id'] : null;
        if ($propiedadId !== null) {
            $propiedad = $this->propiedadRepo->buscarPorId($propiedadId);
            if (!$propiedad) {
                throw new ValidacionExcepcion("El predio o propiedad asociada ID {$propiedadId} no existe.");
            }
        }

        $precioVenta = (float) ($datos['precio_venta_referencial'] ?? 0.0);
        if ($precioVenta < 0.0) {
            throw new ValidacionExcepcion("El precio de venta referencial no puede ser negativo.");
        }

        $costoRef = (float) ($datos['costo_referencial'] ?? 0.0);
        if ($costoRef < 0.0) {
            throw new ValidacionExcepcion("El costo referencial no puede ser negativo.");
        }

        $codigo = trim(strtoupper((string) ($datos['codigo'] ?? '')));
        if ($codigo === '') {
            $codigo = $this->generarCodigoServicio($categoria->obtenerCodigo());
        } elseif ($this->servicioRepo->existeCodigo($codigo)) {
            throw new ValidacionExcepcion("El código de servicio '{$codigo}' ya existe en el catálogo.");
        }

        $esOperacionInterna = !empty($datos['es_operacion_interna_habitual']);
        $requiereTraslado = !empty($datos['requiere_traslado_detalle']) || $categoria->obtenerCodigo() === 'TRASLADOS';

        $servicio = new Servicio(
            id: null,
            codigo: $codigo,
            categoriaId: $categoriaId,
            modalidadCobroId: $modalidadId,
            propiedadId: $propiedadId,
            nombre: $nombre,
            descripcion: isset($datos['descripcion']) ? trim((string) $datos['descripcion']) : null,
            precioVentaReferencial: (string) $precioVenta,
            costoReferencial: (string) $costoRef,
            monedaCodigo: (string) ($datos['moneda_codigo'] ?? 'PEN'),
            esOperacionInternaHabitual: $esOperacionInterna,
            requiereTrasladoDetalle: $requiereTraslado,
            estado: strtoupper(trim((string) ($datos['estado'] ?? 'ACTIVO')))
        );

        $this->pdo->beginTransaction();
        try {
            $id = $this->servicioRepo->crear($servicio);

            // Si se definieron proveedores asociados en la creación
            if (!empty($datos['proveedores']) && is_array($datos['proveedores'])) {
                foreach ($datos['proveedores'] as $p) {
                    $provId = (int) ($p['proveedor_id'] ?? 0);
                    if ($provId > 0) {
                        $this->servicioRepo->asignarProveedor(
                            servicioId: $id,
                            proveedorId: $provId,
                            costoPactado: isset($p['costo_pactado']) ? (string) $p['costo_pactado'] : null,
                            esPreferente: !empty($p['es_preferente']),
                            tiempoAnticipacionHoras: (int) ($p['tiempo_anticipacion_horas'] ?? 0)
                        );
                    }
                }
            }

            $actorIdFinal = $this->resolverActorId($actorId);

            $this->auditoriaServicio->registrar(
                accion: AccionAuditoria::CREAR,
                modulo: 'servicios',
                entidad: 'servicios',
                entidadId: (string) $id,
                descripcion: "Servicio creado en catálogo: '{$codigo}' - {$nombre}",
                valoresAnteriores: null,
                valoresNuevos: $servicio->haciaArreglo(),
                contexto: null,
                actor: $actorIdFinal,
                usuarioId: null,
                correlacionId: null,
                pdoTransaccional: $this->pdo
            );

            $this->pdo->commit();
            return $this->obtenerServicioPorId($id);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Actualiza un concepto en el catálogo maestro.
     *
     * @param array<string, mixed> $datos
     */
    public function actualizarServicio(int $id, array $datos, int $actorId): Servicio
    {
        $servicioActual = $this->obtenerServicioPorId($id, false);
        $valoresAnteriores = $servicioActual->haciaArreglo();

        $nombre = isset($datos['nombre']) ? trim((string) $datos['nombre']) : $servicioActual->obtenerNombre();
        if ($nombre === '') {
            throw new ValidacionExcepcion('El nombre descriptivo del servicio no puede estar vacío.');
        }

        $categoriaId = isset($datos['categoria_id']) ? (int) $datos['categoria_id'] : $servicioActual->obtenerCategoriaId();
        $categoria = $this->servicioRepo->buscarCategoriaPorId($categoriaId);
        if (!$categoria || !$categoria->esActiva()) {
            throw new ValidacionExcepcion("La categoría de servicio seleccionada no es válida o está inactiva.");
        }

        $modalidadId = isset($datos['modalidad_cobro_id']) ? (int) $datos['modalidad_cobro_id'] : $servicioActual->obtenerModalidadCobroId();
        $modalidad = $this->servicioRepo->buscarModalidadPorId($modalidadId);
        if (!$modalidad || !$modalidad->esActiva()) {
            throw new ValidacionExcepcion("La modalidad de cobro seleccionada no es válida o está inactiva.");
        }

        $propiedadId = array_key_exists('propiedad_id', $datos)
            ? ($datos['propiedad_id'] !== '' && $datos['propiedad_id'] !== null ? (int) $datos['propiedad_id'] : null)
            : $servicioActual->obtenerPropiedadId();

        if ($propiedadId !== null) {
            $propiedad = $this->propiedadRepo->buscarPorId($propiedadId);
            if (!$propiedad) {
                throw new ValidacionExcepcion("El predio o propiedad asociada ID {$propiedadId} no existe.");
            }
        }

        $codigo = isset($datos['codigo']) ? trim(strtoupper((string) $datos['codigo'])) : $servicioActual->obtenerCodigo();
        if ($codigo === '') {
            throw new ValidacionExcepcion('El código de servicio no puede ser vacío.');
        }
        if ($this->servicioRepo->existeCodigo($codigo, $id)) {
            throw new ValidacionExcepcion("El código '{$codigo}' ya está asignado a otro servicio.");
        }

        $precioVenta = isset($datos['precio_venta_referencial']) ? (float) $datos['precio_venta_referencial'] : (float) $servicioActual->obtenerPrecioVentaReferencial();
        if ($precioVenta < 0.0) {
            throw new ValidacionExcepcion("El precio de venta referencial no puede ser negativo.");
        }

        $costoRef = isset($datos['costo_referencial']) ? (float) $datos['costo_referencial'] : (float) $servicioActual->obtenerCostoReferencial();
        if ($costoRef < 0.0) {
            throw new ValidacionExcepcion("El costo referencial no puede ser negativo.");
        }

        $servicioActualizado = new Servicio(
            id: $id,
            codigo: $codigo,
            categoriaId: $categoriaId,
            modalidadCobroId: $modalidadId,
            propiedadId: $propiedadId,
            nombre: $nombre,
            descripcion: array_key_exists('descripcion', $datos) ? (trim((string) $datos['descripcion']) ?: null) : $servicioActual->obtenerDescripcion(),
            precioVentaReferencial: (string) $precioVenta,
            costoReferencial: (string) $costoRef,
            monedaCodigo: (string) ($datos['moneda_codigo'] ?? $servicioActual->obtenerMonedaCodigo()),
            esOperacionInternaHabitual: isset($datos['es_operacion_interna_habitual']) ? !empty($datos['es_operacion_interna_habitual']) : $servicioActual->esOperacionInternaHabitual(),
            requiereTrasladoDetalle: isset($datos['requiere_traslado_detalle']) ? !empty($datos['requiere_traslado_detalle']) : ($categoria->obtenerCodigo() === 'TRASLADOS' || $servicioActual->requiereTrasladoDetalle()),
            estado: isset($datos['estado']) ? strtoupper(trim((string) $datos['estado'])) : $servicioActual->obtenerEstado()
        );

        $this->pdo->beginTransaction();
        try {
            $this->servicioRepo->actualizar($servicioActualizado);

            $actorIdFinal = $this->resolverActorId($actorId);

            $this->auditoriaServicio->registrar(
                accion: AccionAuditoria::EDITAR,
                modulo: 'servicios',
                entidad: 'servicios',
                entidadId: (string) $id,
                descripcion: "Servicio ID {$id} ({$codigo}) actualizado en catálogo",
                valoresAnteriores: $valoresAnteriores,
                valoresNuevos: $servicioActualizado->haciaArreglo(),
                contexto: null,
                actor: $actorIdFinal,
                usuarioId: null,
                correlacionId: null,
                pdoTransaccional: $this->pdo
            );

            $this->pdo->commit();
            return $this->obtenerServicioPorId($id);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Cambia el estado de un servicio (cero eliminación física).
     */
    public function cambiarEstadoServicio(int $id, string $nuevoEstado, int $actorId): bool
    {
        $servicio = $this->obtenerServicioPorId($id, false);
        $nuevoEstado = strtoupper(trim($nuevoEstado));

        if (!in_array($nuevoEstado, ['ACTIVO', 'INACTIVO'], true)) {
            throw new ValidacionExcepcion("El estado '{$nuevoEstado}' no es válido.");
        }

        if ($servicio->obtenerEstado() === $nuevoEstado) {
            return true;
        }

        $accion = $nuevoEstado === 'ACTIVO' ? AccionAuditoria::ACTIVAR : AccionAuditoria::DESACTIVAR;

        $this->pdo->beginTransaction();
        try {
            $this->servicioRepo->cambiarEstado($id, $nuevoEstado);

            $actorIdFinal = $this->resolverActorId($actorId);

            $this->auditoriaServicio->registrar(
                accion: $accion,
                modulo: 'servicios',
                entidad: 'servicios',
                entidadId: (string) $id,
                descripcion: "Servicio ID {$id} ({$servicio->obtenerCodigo()}) pasó a {$nuevoEstado}",
                valoresAnteriores: ['estado' => $servicio->obtenerEstado()],
                valoresNuevos: ['estado' => $nuevoEstado],
                contexto: null,
                actor: $actorIdFinal,
                usuarioId: null,
                correlacionId: null,
                pdoTransaccional: $this->pdo
            );

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Asocia u homologa un proveedor externo a un servicio con su costo pactado y condición de preferente.
     */
    public function homologarProveedor(
        int $servicioId,
        int $proveedorId,
        ?string $costoPactado,
        bool $esPreferente,
        int $tiempoAnticipacionHoras,
        int $actorId
    ): void {
        $servicio = $this->obtenerServicioPorId($servicioId, false);
        $proveedor = $this->proveedorRepo->buscarPorId($proveedorId);
        if (!$proveedor) {
            throw new ProveedorNoEncontradoExcepcion("El proveedor ID {$proveedorId} no existe.");
        }
        if (!$proveedor->esActivo()) {
            throw new ValidacionExcepcion("No se puede homologar al proveedor '{$proveedor->obtenerRazonSocial()}' porque se encuentra INACTIVO.");
        }

        $this->pdo->beginTransaction();
        try {
            $this->servicioRepo->asignarProveedor(
                servicioId: $servicioId,
                proveedorId: $proveedorId,
                costoPactado: $costoPactado,
                esPreferente: $esPreferente,
                tiempoAnticipacionHoras: max(0, $tiempoAnticipacionHoras)
            );

            $actorIdFinal = $this->resolverActorId($actorId);

            $this->auditoriaServicio->registrar(
                accion: AccionAuditoria::ASIGNAR,
                modulo: 'servicios',
                entidad: 'servicio_proveedores',
                entidadId: "{$servicioId}-{$proveedorId}",
                descripcion: "Proveedor {$proveedor->obtenerRazonSocial()} homologado para servicio {$servicio->obtenerCodigo()} (Preferente: " . ($esPreferente ? 'SÍ' : 'NO') . ")",
                valoresAnteriores: null,
                valoresNuevos: [
                    'servicio_id' => $servicioId,
                    'proveedor_id' => $proveedorId,
                    'costo_pactado' => $costoPactado,
                    'es_preferente' => $esPreferente,
                    'tiempo_anticipacion_horas' => $tiempoAnticipacionHoras,
                ],
                contexto: null,
                actor: $actorIdFinal,
                usuarioId: null,
                correlacionId: null,
                pdoTransaccional: $this->pdo
            );

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Lista todas las categorías de servicio.
     *
     * @return array<int, \CamargoPMS\Modelos\CategoriaServicio>
     */
    public function listarCategorias(bool $soloActivas = false): array
    {
        return $this->servicioRepo->listarCategorias($soloActivas);
    }

    /**
     * Lista todas las modalidades de cobro.
     *
     * @return array<int, \CamargoPMS\Modelos\ModalidadCobroServicio>
     */
    public function listarModalidades(bool $soloActivas = false): array
    {
        return $this->servicioRepo->listarModalidades($soloActivas);
    }

    /**
     * Resuelve canónicamente el ID de actor ejecutor (D-061: ACTOR != USUARIO).
     */
    public function resolverActorId(?int $actorOUsuarioId = null): int
    {
        if ($actorOUsuarioId !== null && $actorOUsuarioId > 0) {
            $stmt = $this->pdo->prepare('SELECT id FROM actores WHERE id = :id LIMIT 1');
            $stmt->bindValue(':id', $actorOUsuarioId, PDO::PARAM_INT);
            $stmt->execute();
            if ($stmt->fetchColumn()) {
                return $actorOUsuarioId;
            }

            try {
                $actor = $this->auditoriaServicio->obtenerOAsegurarActorUsuario($actorOUsuarioId, $this->pdo);
                if ($actor && $actor->obtenerId() !== null) {
                    return (int) $actor->obtenerId();
                }
            } catch (Throwable) {
                // Fallback defensivo
            }
        }

        $actorActual = $this->auditoriaServicio->obtenerActorActual($this->pdo);
        return (int) ($actorActual->obtenerId() ?? 1);
    }

    private function generarCodigoServicio(string $codigoCategoria): string
    {
        $prefijo = match ($codigoCategoria) {
            'TRASLADOS' => 'SERV-TRF',
            'ALIMENTOS_BEBIDAS' => 'SERV-ALM',
            'LAVANDERIA' => 'SERV-LAV',
            'LIMPIEZA' => 'SERV-LMP',
            'TURISMO_TOURS' => 'SERV-TUR',
            'ESTACIONAMIENTO' => 'SERV-EST',
            'BIENESTAR_SPA' => 'SERV-SPA',
            default => 'SERV-GEN',
        };

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM servicios WHERE codigo LIKE :prefijo");
        $stmt->bindValue(':prefijo', $prefijo . '%', PDO::PARAM_STR);
        $stmt->execute();
        $conteo = ((int) $stmt->fetchColumn()) + 1;

        $codigo = sprintf('%s-%03d', $prefijo, $conteo);
        while ($this->servicioRepo->existeCodigo($codigo)) {
            $conteo++;
            $codigo = sprintf('%s-%03d', $prefijo, $conteo);
        }

        return $codigo;
    }
}
