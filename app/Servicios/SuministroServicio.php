<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

use CamargoPMS\Excepciones\ConflictoSuministroExcepcion;
use CamargoPMS\Excepciones\SuministroNoEncontradoExcepcion;
use CamargoPMS\Excepciones\TarifaFaltanteExcepcion;
use CamargoPMS\Excepciones\ValidacionSuministroExcepcion;
use CamargoPMS\Modelos\AplicacionPago;
use CamargoPMS\Modelos\CargoCuenta;
use CamargoPMS\Modelos\CuentaFolio;
use CamargoPMS\Modelos\PagoCuenta;
use CamargoPMS\Modelos\Suministro;
use CamargoPMS\Modelos\SuministroLectura;
use CamargoPMS\Modelos\SuministroLiquidacion;
use CamargoPMS\Modelos\SuministroLiquidacionTramo;
use CamargoPMS\Modelos\SuministroMedidor;
use CamargoPMS\Modelos\SuministroTarifa;
use CamargoPMS\Repositorios\ActorAuditoriaRepositorio;
use CamargoPMS\Repositorios\AplicacionPagoRepositorio;
use CamargoPMS\Repositorios\ArrendamientoRepositorio;
use CamargoPMS\Repositorios\CargoCuentaRepositorio;
use CamargoPMS\Repositorios\CuentaFolioRepositorio;
use CamargoPMS\Repositorios\PagoCuentaRepositorio;
use CamargoPMS\Repositorios\SuministroRepositorio;
use DateTimeImmutable;
use PDO;
use Throwable;

/**
 * Servicio de Dominio para la GestiÃ³n de Suministros, Medidores, Lecturas,
 * Tarifas con Vigencia HistÃ³rica y LiquidaciÃ³n a Cuentas Folio de Arrendamiento (SUMINISTROS-1 / D-081).
 *
 * Axioma ontolÃ³gico:
 * SUMINISTRO != MEDIDOR != LECTURA != TARIFA != CONSUMO VALORIZADO != CARGO != PAGO
 */
class SuministroServicio
{
    private PDO $pdo;
    private SuministroRepositorio $suministroRepo;
    private CargoCuentaRepositorio $cargoRepo;
    private AplicacionPagoRepositorio $aplicacionRepo;
    private PagoCuentaRepositorio $pagoRepo;
    private CuentaFolioRepositorio $cuentaFolioRepo;
    private ArrendamientoRepositorio $arrendamientoRepo;
    private ActorAuditoriaRepositorio $actorRepo;

    public function __construct(
        PDO $pdo,
        ?SuministroRepositorio $suministroRepo = null,
        ?CargoCuentaRepositorio $cargoRepo = null,
        ?AplicacionPagoRepositorio $aplicacionRepo = null,
        ?PagoCuentaRepositorio $pagoRepo = null,
        ?CuentaFolioRepositorio $cuentaFolioRepo = null,
        ?ArrendamientoRepositorio $arrendamientoRepo = null,
        ?ActorAuditoriaRepositorio $actorRepo = null
    ) {
        $this->pdo = $pdo;
        $this->suministroRepo = $suministroRepo ?? new SuministroRepositorio($pdo);
        $this->cargoRepo = $cargoRepo ?? new CargoCuentaRepositorio($pdo);
        $this->aplicacionRepo = $aplicacionRepo ?? new AplicacionPagoRepositorio($pdo);
        $this->pagoRepo = $pagoRepo ?? new PagoCuentaRepositorio($pdo);
        $this->cuentaFolioRepo = $cuentaFolioRepo ?? new CuentaFolioRepositorio($pdo);
        $this->arrendamientoRepo = $arrendamientoRepo ?? new ArrendamientoRepositorio($pdo);
        $this->actorRepo = $actorRepo ?? new ActorAuditoriaRepositorio($pdo);
    }

    public function obtenerRepositorio(): SuministroRepositorio
    {
        return $this->suministroRepo;
    }

    // -------------------------------------------------------------------------
    // 1. GESTIÃ“N DEL CATÃLOGO DE SUMINISTROS
    // -------------------------------------------------------------------------

    public function crearSuministro(array $datos): Suministro
    {
        $codigo = strtoupper(trim((string) ($datos['codigo'] ?? '')));
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        $modalidad = strtoupper(trim((string) ($datos['modalidad'] ?? Suministro::MODALIDAD_MEDIDO)));
        $unidadMedida = trim((string) ($datos['unidad_medida'] ?? ''));

        if ($codigo === '' || $nombre === '' || $unidadMedida === '') {
            throw new ValidacionSuministroExcepcion('El cÃ³digo, nombre y unidad de medida son obligatorios');
        }

        if (!in_array($modalidad, [Suministro::MODALIDAD_MEDIDO, Suministro::MODALIDAD_FIJO_PERIODICO], true)) {
            throw new ValidacionSuministroExcepcion("Modalidad '{$modalidad}' no vÃ¡lida");
        }

        $existente = $this->suministroRepo->buscarSuministroPorCodigo($codigo);
        if ($existente !== null) {
            throw new ConflictoSuministroExcepcion("Ya existe un suministro registrado con el cÃ³digo '{$codigo}'");
        }

        $suministro = new Suministro(
            null,
            $codigo,
            $nombre,
            $modalidad,
            $unidadMedida,
            isset($datos['descripcion']) && trim((string) $datos['descripcion']) !== '' ? trim((string) $datos['descripcion']) : null,
            !empty($datos['permite_rollover']),
            (string) ($datos['estado'] ?? Suministro::ESTADO_ACTIVO)
        );

        $id = $this->suministroRepo->crearSuministro($suministro);

        return $this->suministroRepo->buscarSuministroPorId($id);
    }

    public function actualizarSuministro(int $id, array $datos): Suministro
    {
        $suministro = $this->suministroRepo->buscarSuministroPorId($id);
        if ($suministro === null) {
            throw new SuministroNoEncontradoExcepcion("Suministro ID {$id} inexistente");
        }

        $nombre = isset($datos['nombre']) ? trim((string) $datos['nombre']) : $suministro->obtenerNombre();
        $modalidad = isset($datos['modalidad']) ? strtoupper(trim((string) $datos['modalidad'])) : $suministro->obtenerModalidad();
        $unidadMedida = isset($datos['unidad_medida']) ? trim((string) $datos['unidad_medida']) : $suministro->obtenerUnidadMedida();
        $descripcion = array_key_exists('descripcion', $datos) ? (trim((string) $datos['descripcion']) !== '' ? trim((string) $datos['descripcion']) : null) : $suministro->obtenerDescripcion();
        $permiteRollover = array_key_exists('permite_rollover', $datos) ? !empty($datos['permite_rollover']) : $suministro->permiteRollover();
        $estado = isset($datos['estado']) ? (string) $datos['estado'] : $suministro->obtenerEstado();

        $actualizado = new Suministro(
            $id,
            $suministro->obtenerCodigo(),
            $nombre,
            $modalidad,
            $unidadMedida,
            $descripcion,
            $permiteRollover,
            $estado
        );

        $this->suministroRepo->actualizarSuministro($actualizado);

        return $this->suministroRepo->buscarSuministroPorId($id);
    }

    public function obtenerSuministro(int $id): Suministro
    {
        $sum = $this->suministroRepo->buscarSuministroPorId($id);
        if ($sum === null) {
            throw new SuministroNoEncontradoExcepcion("Suministro ID {$id} inexistente");
        }

        return $sum;
    }

    public function listarSuministros(?string $estado = null): array
    {
        return $this->suministroRepo->listarSuministros($estado);
    }

    // -------------------------------------------------------------------------
    // 2. TARIFAS HISTÃ“RICAS CON PRECEDENCIA Y BLOQUEO PESIMISTA
    // -------------------------------------------------------------------------

    public function crearTarifa(array $datos): SuministroTarifa
    {
        $suministroId = (int) ($datos['suministro_id'] ?? 0);
        $suministro = $this->suministroRepo->buscarSuministroPorId($suministroId);
        if ($suministro === null) {
            throw new SuministroNoEncontradoExcepcion("Suministro ID {$suministroId} inexistente");
        }

        $ambito = strtoupper(trim((string) ($datos['ambito'] ?? SuministroTarifa::AMBITO_GLOBAL)));
        if (!in_array($ambito, [SuministroTarifa::AMBITO_GLOBAL, SuministroTarifa::AMBITO_PROPIEDAD, SuministroTarifa::AMBITO_UNIDAD], true)) {
            throw new ValidacionSuministroExcepcion("Ãmbito '{$ambito}' no vÃ¡lido");
        }

        $propiedadId = isset($datos['propiedad_id']) && (int) $datos['propiedad_id'] > 0 ? (int) $datos['propiedad_id'] : null;
        $unidadId = isset($datos['unidad_id']) && (int) $datos['unidad_id'] > 0 ? (int) $datos['unidad_id'] : null;

        // Si es UNIDAD, validar o deducir propiedad_id
        if ($ambito === SuministroTarifa::AMBITO_UNIDAD) {
            if ($unidadId === null) {
                throw new ValidacionSuministroExcepcion("El Ã¡mbito 'UNIDAD' exige especificar unidad_id");
            }
            if ($propiedadId === null) {
                // Obtener la propiedad a la que pertenece la unidad
                $stmtU = $this->pdo->prepare('SELECT propiedad_id FROM unidades WHERE id = :id');
                $stmtU->execute(['id' => $unidadId]);
                $propiedadId = (int) $stmtU->fetchColumn();
                if ($propiedadId <= 0) {
                    throw new ValidacionSuministroExcepcion("Unidad ID {$unidadId} inexistente");
                }
            }
        } elseif ($ambito === SuministroTarifa::AMBITO_PROPIEDAD) {
            if ($propiedadId === null) {
                throw new ValidacionSuministroExcepcion("El Ã¡mbito 'PROPIEDAD' exige especificar propiedad_id");
            }
            $unidadId = null;
        } else {
            // GLOBAL
            $propiedadId = null;
            $unidadId = null;
        }

        $precioUnitario = (string) ($datos['precio_unitario'] ?? '0.0000');
        if (bccomp($precioUnitario, '0.0000', 4) <= 0) {
            throw new ValidacionSuministroExcepcion('El precio unitario debe ser estrictamente mayor a 0');
        }

        $fechaInicio = (string) ($datos['fecha_inicio'] ?? '');
        $fechaFin = isset($datos['fecha_fin']) && trim((string) $datos['fecha_fin']) !== '' ? (string) $datos['fecha_fin'] : null;

        if ($fechaInicio === '') {
            throw new ValidacionSuministroExcepcion('La fecha de inicio de vigencia es obligatoria');
        }

        if ($fechaFin !== null && $fechaFin < $fechaInicio) {
            throw new ValidacionSuministroExcepcion('La fecha de fin no puede ser anterior a la fecha de inicio');
        }

        $moneda = strtoupper(trim((string) ($datos['moneda_codigo'] ?? 'PEN')));

        // Iniciar transacciÃ³n para bloqueo pesimista anti-solapamiento
        $debeCerrarTx = !$this->pdo->inTransaction();
        if ($debeCerrarTx) {
            $this->pdo->beginTransaction();
        }

        try {
            // 1. Bloqueo pesimista con FOR UPDATE
            $tarifasExistentes = $this->suministroRepo->bloquearTarifasAmbitoParaValidacion(
                $suministroId,
                $ambito,
                $propiedadId,
                $unidadId
            );

            // 2. Comprobar solapamiento temporal exacto
            foreach ($tarifasExistentes as $te) {
                $eInicio = $te->obtenerFechaInicio();
                $eFin = $te->obtenerFechaFin();

                // Solapamiento entre [fechaInicio, fechaFin] y [eInicio, eFin]
                // Regla: A_inicio <= B_fin && (A_fin >= B_inicio || A_fin is null)
                $solapa = true;
                if ($fechaFin !== null && $eInicio > $fechaFin) {
                    $solapa = false;
                }
                if ($eFin !== null && $fechaInicio > $eFin) {
                    $solapa = false;
                }

                if ($solapa) {
                    throw new ConflictoSuministroExcepcion(
                        "Existe una tarifa activa ID {$te->obtenerId()} que se solapa con el intervalo indicado [{$fechaInicio} al " . ($fechaFin ?? 'abierto') . "]"
                    );
                }
            }

            $tarifa = new SuministroTarifa(
                null,
                $suministroId,
                $ambito,
                $propiedadId,
                $unidadId,
                $fechaInicio,
                $fechaFin,
                $precioUnitario,
                $moneda,
                SuministroTarifa::ESTADO_ACTIVO
            );

            $id = $this->suministroRepo->crearTarifa($tarifa);

            if ($debeCerrarTx) {
                $this->pdo->commit();
            }

            return $this->suministroRepo->buscarTarifaPorId($id);
        } catch (Throwable $e) {
            if ($debeCerrarTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Resuelve la tarifa aplicable para una fecha respetando la jerarquÃ­a obligatoria:
     * UNIDAD > PROPIEDAD > GLOBAL (D-081).
     */
    public function resolverTarifaParaFecha(int $suministroId, string $fecha, int $propiedadId, int $unidadId): SuministroTarifa
    {
        $candidatas = $this->suministroRepo->buscarTarifasCandidatasEnFecha($suministroId, $fecha, $propiedadId, $unidadId);
        if (empty($candidatas)) {
            throw new TarifaFaltanteExcepcion("No existe ninguna tarifa activa vigente para el suministro ID {$suministroId} en la fecha {$fecha}");
        }

        // La consulta SQL ya ordena por prioridad UNIDAD (1) > PROPIEDAD (2) > GLOBAL (3) y fecha_inicio DESC
        return $candidatas[0];
    }

    public function listarTarifasPorSuministro(int $suministroId): array
    {
        return $this->suministroRepo->listarTarifasPorSuministro($suministroId);
    }

    // -------------------------------------------------------------------------
    // 3. MEDIDORES FÃSICOS Y REEMPLAZO ATÃ“MICO
    // -------------------------------------------------------------------------

    public function instalarMedidor(array $datos, int $actorId): SuministroMedidor
    {
        $suministroId = (int) ($datos['suministro_id'] ?? 0);
        $suministro = $this->obtenerSuministro($suministroId);

        if (!$suministro->esMedido()) {
            throw new ValidacionSuministroExcepcion("No se puede instalar un medidor en un suministro con modalidad '{$suministro->obtenerModalidad()}'");
        }

        $unidadId = (int) ($datos['unidad_id'] ?? 0);
        $propiedadId = (int) ($datos['propiedad_id'] ?? 0);

        if ($unidadId <= 0) {
            throw new ValidacionSuministroExcepcion('La unidad fÃ­sica es obligatoria para la instalaciÃ³n de un medidor');
        }

        if ($propiedadId <= 0) {
            $stmtU = $this->pdo->prepare('SELECT propiedad_id FROM unidades WHERE id = :id');
            $stmtU->execute(['id' => $unidadId]);
            $propiedadId = (int) $stmtU->fetchColumn();
            if ($propiedadId <= 0) {
                throw new ValidacionSuministroExcepcion("Unidad ID {$unidadId} inexistente");
            }
        }

        // Verificar medidor activo existente en la misma unidad y suministro
        $activo = $this->suministroRepo->buscarMedidorActivoPorUnidad($suministroId, $unidadId);
        if ($activo !== null) {
            throw new ConflictoSuministroExcepcion("Ya existe un medidor activo (Serie: {$activo->obtenerNumeroSerie()}) para este suministro en la unidad indicada. Debe realizarse un reemplazo formal.");
        }

        $serie = trim((string) ($datos['numero_serie'] ?? ''));
        if ($serie === '') {
            throw new ValidacionSuministroExcepcion('El nÃºmero de serie del medidor es obligatorio');
        }

        $existenteSerie = $this->suministroRepo->buscarMedidorPorSerie($serie);
        if ($existenteSerie !== null) {
            throw new ConflictoSuministroExcepcion("El nÃºmero de serie '{$serie}' ya se encuentra registrado en el sistema");
        }

        $fechaInstalacion = (string) ($datos['fecha_instalacion'] ?? date('Y-m-d'));
        $lecturaInicial = (string) ($datos['lectura_inicial'] ?? '0.0000');

        if (bccomp($lecturaInicial, '0.0000', 4) < 0) {
            throw new ValidacionSuministroExcepcion('La lectura inicial no puede ser negativa');
        }

        $lecturaMaxima = isset($datos['lectura_maxima']) && trim((string) $datos['lectura_maxima']) !== '' ? (string) $datos['lectura_maxima'] : null;
        $permiteRollover = !empty($datos['permite_rollover']) || $suministro->permiteRollover();

        $debeCerrarTx = !$this->pdo->inTransaction();
        if ($debeCerrarTx) {
            $this->pdo->beginTransaction();
        }

        try {
            $medidor = SuministroMedidor::desdeArreglo([
                'suministro_id' => $suministroId,
                'propiedad_id' => $propiedadId,
                'unidad_id' => $unidadId,
                'codigo' => $serie,
                'marca' => isset($datos['marca']) && trim((string) $datos['marca']) !== '' ? trim((string) $datos['marca']) : null,
                'modelo' => isset($datos['modelo']) && trim((string) $datos['modelo']) !== '' ? trim((string) $datos['modelo']) : null,
                'fecha_instalacion' => $fechaInstalacion,
                'fecha_retiro' => null,
                'lectura_inicial' => $lecturaInicial,
                'capacidad_maxima' => $lecturaMaxima,
                'estado' => SuministroMedidor::ESTADO_ACTIVO,
                'observaciones' => isset($datos['notas']) && trim((string) $datos['notas']) !== '' ? trim((string) $datos['notas']) : null,
                'creado_por_actor_id' => $actorId,
            ]);

            $medidorId = $this->suministroRepo->crearMedidor($medidor);

            // Registrar inmediatamente la lectura inicial inmutable
            $lecturaInicialEntidad = SuministroLectura::desdeArreglo([
                'medidor_id' => $medidorId,
                'tipo_evento' => SuministroLectura::EVENTO_INSTALACION,
                'fecha_lectura' => $fechaInstalacion,
                'valor_lectura' => $lecturaInicial,
                'motivo' => 'Lectura base por instalaciÃ³n de medidor',
                'estado' => SuministroLectura::ESTADO_VALIDA,
                'registrado_por_actor_id' => $actorId,
            ]);

            $this->suministroRepo->registrarLectura($lecturaInicialEntidad);

            if ($debeCerrarTx) {
                $this->pdo->commit();
            }

            return $this->suministroRepo->buscarMedidorPorId($medidorId);
        } catch (Throwable $e) {
            if ($debeCerrarTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Realiza el reemplazo atÃ³mico de un medidor activo por uno nuevo en la misma unidad.
     */
    public function reemplazarMedidor(
        int $medidorActualId,
        array $datosNuevoMedidor,
        string $fechaCorte,
        string $lecturaFinalActual,
        int $actorId
    ): array {
        $actual = $this->suministroRepo->buscarMedidorPorId($medidorActualId);
        if ($actual === null) {
            throw new SuministroNoEncontradoExcepcion("Medidor ID {$medidorActualId} inexistente");
        }

        if (!$actual->esActivo()) {
            throw new ValidacionSuministroExcepcion("El medidor ID {$medidorActualId} no estÃ¡ activo");
        }

        $debeCerrarTx = !$this->pdo->inTransaction();
        if ($debeCerrarTx) {
            $this->pdo->beginTransaction();
        }

        try {
            // 1. Registrar lectura final de corte en medidor saliente
            $ultimaLec = $this->suministroRepo->buscarUltimaLecturaVigente($medidorActualId, $fechaCorte);
            if ($ultimaLec !== null && bccomp($lecturaFinalActual, $ultimaLec->obtenerValorLectura(), 4) < 0 && !$actual->permiteRollover()) {
                throw new ValidacionSuministroExcepcion('La lectura final de retiro no puede ser menor a la Ãºltima lectura registrada');
            }

            $lecturaCorte = SuministroLectura::desdeArreglo([
                'medidor_id' => $medidorActualId,
                'tipo_evento' => SuministroLectura::EVENTO_RETIRO,
                'fecha_lectura' => $fechaCorte,
                'valor_lectura' => $lecturaFinalActual,
                'motivo' => 'Lectura de corte por reemplazo de medidor',
                'estado' => SuministroLectura::ESTADO_VALIDA,
                'registrado_por_actor_id' => $actorId,
            ]);
            $this->suministroRepo->registrarLectura($lecturaCorte);

            // 2. Dar de baja formalmente al medidor saliente
            $medidorRetirado = SuministroMedidor::desdeArreglo([
                'id' => $actual->obtenerId(),
                'suministro_id' => $actual->obtenerSuministroId(),
                'propiedad_id' => $actual->obtenerPropiedadId(),
                'unidad_id' => $actual->obtenerUnidadId(),
                'codigo' => $actual->obtenerNumeroSerie(),
                'marca' => $actual->obtenerMarca(),
                'modelo' => $actual->obtenerModelo(),
                'fecha_instalacion' => $actual->obtenerFechaInstalacion(),
                'fecha_retiro' => $fechaCorte,
                'lectura_inicial' => $actual->obtenerLecturaInicial(),
                'capacidad_maxima' => $actual->obtenerLecturaMaxima(),
                'estado' => SuministroMedidor::ESTADO_REEMPLAZADO,
                'observaciones' => $actual->obtenerNotas(),
                'creado_por_actor_id' => $actual->obtenerCreadoPorActorId(),
                'lectura_final' => $lecturaFinalActual,
            ]);
            $this->suministroRepo->actualizarMedidor($medidorRetirado);

            // 3. Dar de alta al nuevo medidor en la misma unidad
            $datosNuevoMedidor['suministro_id'] = $actual->obtenerSuministroId();
            $datosNuevoMedidor['unidad_id'] = $actual->obtenerUnidadId();
            $datosNuevoMedidor['propiedad_id'] = $actual->obtenerPropiedadId();
            $datosNuevoMedidor['fecha_instalacion'] = $fechaCorte;

            $nuevoMedidor = $this->instalarMedidor($datosNuevoMedidor, $actorId);

            if ($debeCerrarTx) {
                $this->pdo->commit();
            }

            return [
                'medidor_retirado' => $medidorRetirado,
                'medidor_nuevo' => $nuevoMedidor,
            ];
        } catch (Throwable $e) {
            if ($debeCerrarTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function obtenerMedidor(int $id): SuministroMedidor
    {
        $m = $this->suministroRepo->buscarMedidorPorId($id);
        if ($m === null) {
            throw new SuministroNoEncontradoExcepcion("Medidor ID {$id} inexistente");
        }

        return $m;
    }

    public function listarMedidores(?int $suministroId = null, ?int $propiedadId = null, ?int $unidadId = null): array
    {
        return $this->suministroRepo->listarMedidores($suministroId, $propiedadId, $unidadId);
    }

    // -------------------------------------------------------------------------
    // 4. LECTURAS FÃSICAS INMUTABLES Y CORRECCIONES AUDITADAS
    // -------------------------------------------------------------------------

    public function registrarLectura(array $datos, int $actorId): SuministroLectura
    {
        $medidorId = (int) ($datos['medidor_id'] ?? 0);
        $medidor = $this->obtenerMedidor($medidorId);

        if (!$medidor->esActivo()) {
            throw new ValidacionSuministroExcepcion("No se pueden registrar lecturas en un medidor que no estÃ¡ activo (Estado: {$medidor->obtenerEstado()})");
        }

        $valorLectura = (string) ($datos['valor_lectura'] ?? '');
        if ($valorLectura === '' || bccomp($valorLectura, '0.0000', 4) < 0) {
            throw new ValidacionSuministroExcepcion('El valor de la lectura debe ser un nÃºmero no negativo');
        }

        $fechaLectura = (string) ($datos['fecha_lectura'] ?? date('Y-m-d'));
        $tipoEvento = strtoupper(trim((string) ($datos['tipo_evento'] ?? SuministroLectura::EVENTO_PERIODICA)));
        $arrendamientoId = isset($datos['arrendamiento_id']) && (int) $datos['arrendamiento_id'] > 0 ? (int) $datos['arrendamiento_id'] : null;

        // ComprobaciÃ³n de no decrecimiento
        $ultima = $this->suministroRepo->buscarUltimaLecturaVigente($medidorId, $fechaLectura);
        if ($ultima === null) {
            $ultima = $this->suministroRepo->buscarUltimaLecturaVigente($medidorId);
        }
        $valorReferencia = $ultima !== null ? $ultima->obtenerValorLectura() : $medidor->obtenerLecturaInicial();

        if (bccomp($valorLectura, $valorReferencia, 4) < 0) {
            if (!$medidor->permiteRollover()) {
                throw new ValidacionSuministroExcepcion(
                    "El valor de lectura ({$valorLectura}) no puede ser menor a la lectura previa ({$valorReferencia}). Si se trata de un error de digitaciÃ³n, aplique una correcciÃ³n auditada."
                );
            }
            // Si permite rollover, comprobar capacidad mÃ¡xima
            if ($medidor->obtenerLecturaMaxima() === null || bccomp($medidor->obtenerLecturaMaxima(), '0.0000', 4) <= 0) {
                throw new ValidacionSuministroExcepcion('El medidor permite rollover pero no tiene configurada una lectura mÃ¡xima vÃ¡lida');
            }
        }

        $lectura = SuministroLectura::desdeArreglo([
            'medidor_id' => $medidorId,
            'arrendamiento_id' => $arrendamientoId,
            'tipo_evento' => $tipoEvento,
            'fecha_lectura' => $fechaLectura,
            'valor_lectura' => $valorLectura,
            'motivo' => isset($datos['motivo']) && trim((string) $datos['motivo']) !== '' ? trim((string) $datos['motivo']) : null,
            'estado' => SuministroLectura::ESTADO_VALIDA,
            'registrado_por_actor_id' => $actorId,
        ]);

        $id = $this->suministroRepo->registrarLectura($lectura);

        return $this->suministroRepo->buscarLecturaPorId($id);
    }

    /**
     * Aplica una correcciÃ³n auditada append-only sobre una lectura previa errÃ³nea.
     */
    public function corregirLectura(int $lecturaOriginalId, string $nuevoValor, string $motivo, int $actorId): SuministroLectura
    {
        $original = $this->suministroRepo->buscarLecturaPorId($lecturaOriginalId);
        if ($original === null) {
            throw new SuministroNoEncontradoExcepcion("Lectura ID {$lecturaOriginalId} inexistente");
        }

        if (!$original->esVigente()) {
            throw new ConflictoSuministroExcepcion("La lectura ID {$lecturaOriginalId} ya fue corregida o no estÃ¡ vigente");
        }

        $motivo = trim($motivo);
        if ($motivo === '') {
            throw new ValidacionSuministroExcepcion('El motivo de la correcciÃ³n es estrictamente obligatorio para fines de auditorÃ­a');
        }

        if (bccomp($nuevoValor, '0.0000', 4) < 0) {
            throw new ValidacionSuministroExcepcion('El nuevo valor de lectura no puede ser negativo');
        }

        $debeCerrarTx = !$this->pdo->inTransaction();
        if ($debeCerrarTx) {
            $this->pdo->beginTransaction();
        }

        try {
            // 1. Marcar lectura original como CORREGIDA
            $this->suministroRepo->actualizarEstadoLectura($lecturaOriginalId, SuministroLectura::ESTADO_CORREGIDA);

            // 2. Insertar nueva lectura con referencia a la original
            $nuevaLectura = SuministroLectura::desdeArreglo([
                'medidor_id' => $original->obtenerMedidorId(),
                'arrendamiento_id' => $original->obtenerArrendamientoId(),
                'tipo_evento' => SuministroLectura::EVENTO_CORRECCION,
                'fecha_lectura' => $original->obtenerFechaLectura(),
                'valor_lectura' => $nuevoValor,
                'lectura_referencia_id' => $lecturaOriginalId,
                'motivo' => $motivo,
                'estado' => SuministroLectura::ESTADO_VALIDA,
                'registrado_por_actor_id' => $actorId,
            ]);

            $id = $this->suministroRepo->registrarLectura($nuevaLectura);

            if ($debeCerrarTx) {
                $this->pdo->commit();
            }

            return $this->suministroRepo->buscarLecturaPorId($id);
        } catch (Throwable $e) {
            if ($debeCerrarTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function listarLecturasPorMedidor(int $medidorId, bool $soloVigentes = true): array
    {
        return $this->suministroRepo->buscarLecturasPorMedidor($medidorId, $soloVigentes);
    }

    // -------------------------------------------------------------------------
    // 5. LIQUIDACIÃ“N DE SUMINISTROS Y DEVENGO FINANCIERO ATÃ“MICO
    // -------------------------------------------------------------------------

    /**
     * Computa el consumo valorizado y devenga atÃ³micamente el cargo financiero en el folio del arrendamiento (D-081).
     */
    public function liquidarPeriodoArrendamiento(
        int $arrendamientoId,
        int $suministroId,
        string $periodoDesde,
        string $periodoHasta,
        ?string $fechaVencimiento = null,
        ?int $actorId = null,
        int $revision = 1,
        ?int $liquidacionPreviaId = null
    ): SuministroLiquidacion {
        if ($periodoHasta <= $periodoDesde) {
            throw new ValidacionSuministroExcepcion("La fecha final del perÃ­odo ({$periodoHasta}) debe ser posterior a la fecha inicial ({$periodoDesde})");
        }

        $actorIdFinal = $this->resolverActorId($actorId);

        // 1. Obtener y validar contrato de arrendamiento
        $arrendamiento = $this->arrendamientoRepo->obtenerPorId($arrendamientoId);
        if ($arrendamiento === null) {
            throw new ValidacionSuministroExcepcion("Contrato de arrendamiento ID {$arrendamientoId} no encontrado");
        }

        $unidadId = $arrendamiento->obtenerUnidadId();
        $stmtP = $this->pdo->prepare('SELECT propiedad_id FROM unidades WHERE id = :id');
        $stmtP->execute(['id' => $unidadId]);
        $propiedadId = (int) $stmtP->fetchColumn();

        // 2. Obtener cuenta folio del arrendamiento
        $cuentaFolio = $this->cuentaFolioRepo->obtenerPorArrendamientoId($arrendamientoId);
        if ($cuentaFolio === null) {
            throw new ValidacionSuministroExcepcion("El contrato de arrendamiento ID {$arrendamientoId} no posee una cuenta folio financiera asociada");
        }

        // 3. Obtener suministro
        $suministro = $this->obtenerSuministro($suministroId);

        // 4. Iniciar transacciÃ³n atÃ³mica
        $debeCerrarTx = !$this->pdo->inTransaction();
        if ($debeCerrarTx) {
            $this->pdo->beginTransaction();
        }

        try {
            // Verificar liquidaciÃ³n activa existente en el mismo perÃ­odo exacto
            $activa = $this->suministroRepo->buscarLiquidacionActivaPorPeriodo($arrendamientoId, $suministroId, $periodoDesde, $periodoHasta);
            if ($activa !== null) {
                throw new ConflictoSuministroExcepcion("Ya existe una liquidaciÃ³n activa (Folio: {$activa->obtenerFolio()}) devengada para este contrato en el perÃ­odo indicado");
            }

            $dtDesde = new DateTimeImmutable($periodoDesde);
            $periodoAnio = (int) $dtDesde->format('Y');
            $periodoMes = (int) $dtDesde->format('n');
            $fechaEmision = date('Y-m-d');
            $fechaVenc = $fechaVencimiento ?? date('Y-m-d', strtotime('+7 days'));

            $tramosCalculados = [];
            $cantidadTotal = '0.0000';
            $subtotalAcumulado = '0.00';
            $totalAcumulado = '0.00';

            if ($suministro->esFijoPeriodico()) {
                // Modalidad FIJO_PERIODICO (Internet, mantenimiento comÃºn, cuota fija)
                $tarifa = $this->resolverTarifaParaFecha($suministroId, $periodoDesde, $propiedadId, $unidadId);
                $precioUnitario = $tarifa->obtenerPrecioUnitario();

                $cantidadTotal = '1.0000';
                $subtotalAcumulado = bcmul($cantidadTotal, $precioUnitario, 2);
                $totalAcumulado = $subtotalAcumulado;

                $tramosCalculados[] = [
                    'numero_tramo' => 1,
                    'medidor_id' => null,
                    'lectura_anterior_id' => null,
                    'lectura_actual_id' => null,
                    'lectura_anterior_valor' => null,
                    'lectura_actual_valor' => null,
                    'cantidad' => $cantidadTotal,
                    'tarifa_id' => $tarifa->obtenerId(),
                    'tarifa_valor' => $precioUnitario,
                    'subtotal' => $subtotalAcumulado,
                    'impuesto_monto' => '0.00',
                    'total' => $totalAcumulado,
                    'fecha_desde' => $periodoDesde,
                    'fecha_hasta' => $periodoHasta,
                ];
                $origenTipoCargo = 'SUMINISTRO_CUOTA_FIJA';
            } else {
                // Modalidad MEDIDO (Electricidad, agua)
                $medidor = $this->suministroRepo->buscarMedidorActivoPorUnidad($suministroId, $unidadId);
                if ($medidor === null) {
                    throw new ValidacionSuministroExcepcion("No existe un medidor activo para el suministro '{$suministro->obtenerNombre()}' en la unidad del contrato");
                }

                // Buscar lecturas vigentes en el rango
                $lecturaInicial = $this->suministroRepo->buscarLecturaVigenteEnFecha($medidor->obtenerId(), $periodoDesde);
                if ($lecturaInicial === null) {
                    // Buscar la Ãºltima disponible hasta la fecha inicial
                    $lecturaInicial = $this->suministroRepo->buscarUltimaLecturaVigente($medidor->obtenerId(), $periodoDesde);
                }

                $lecturaFinal = $this->suministroRepo->buscarLecturaVigenteEnFecha($medidor->obtenerId(), $periodoHasta);
                if ($lecturaFinal === null) {
                    // Si no estÃ¡ exactamente en periodoHasta, buscar la Ãºltima registrada
                    $lecturaFinal = $this->suministroRepo->buscarUltimaLecturaVigente($medidor->obtenerId(), $periodoHasta);
                }

                if ($lecturaInicial === null || $lecturaFinal === null) {
                    throw new ValidacionSuministroExcepcion("No se cuentan con lecturas vigentes suficientes para liquidar el perÃ­odo del {$periodoDesde} al {$periodoHasta}");
                }

                if ($lecturaInicial->obtenerId() === $lecturaFinal->obtenerId()) {
                    throw new ValidacionSuministroExcepcion("La lectura inicial y final corresponden al mismo registro (ID: {$lecturaInicial->obtenerId()}). Se requiere registrar la lectura de fin de perÃ­odo.");
                }

                // Obtener lecturas intermedias si existen
                $lecturasRango = $this->suministroRepo->buscarLecturasEnRango(
                    $medidor->obtenerId(),
                    $lecturaInicial->obtenerFechaLectura(),
                    $lecturaFinal->obtenerFechaLectura(),
                    true
                );

                // Detectar si hay cambios reales en la tarifa efectiva dentro del perÃ­odo
                $tarifasCandidatas = $this->suministroRepo->buscarTarifasEnRango($suministroId, $periodoDesde, $periodoHasta, $propiedadId, $unidadId);
                $tarifaEfectivaInicio = $this->resolverTarifaParaFecha($suministroId, $periodoDesde, $propiedadId, $unidadId);
                $hayCambioTarifaReal = false;

                foreach ($tarifasCandidatas as $tCand) {
                    $vDesde = $tCand->obtenerVigenciaDesde();
                    if ($vDesde > $periodoDesde && $vDesde <= $periodoHasta) {
                        $tarifaEnFecha = $this->resolverTarifaParaFecha($suministroId, $vDesde, $propiedadId, $unidadId);
                        if ($tarifaEnFecha->obtenerId() !== $tarifaEfectivaInicio->obtenerId()) {
                            $hayCambioTarifaReal = true;
                            break;
                        }
                    }
                }

                if ($hayCambioTarifaReal && count($lecturasRango) <= 2) {
                    // Si la tarifa efectiva cambia en el perÃ­odo pero solo hay lectura inicial y final, exigir lectura de corte
                    throw new ConflictoSuministroExcepcion(
                        'Se detectÃ³ un cambio de tarifa dentro del perÃ­odo a liquidar sin lectura de corte tarifario registrada. Ingrese la lectura intermedia de corte correspondiente.'
                    );
                }

                // Construir tramos entre lecturas consecutivas
                $numTramo = 1;
                for ($i = 0; $i < count($lecturasRango) - 1; $i++) {
                    $lecAnt = $lecturasRango[$i];
                    $lecAct = $lecturasRango[$i + 1];

                    $valAnt = $lecAnt->obtenerValorLectura();
                    $valAct = $lecAct->obtenerValorLectura();

                    if (bccomp($valAct, $valAnt, 4) >= 0) {
                        $cantTramo = bcsub($valAct, $valAnt, 4);
                    } else {
                        // Rollover
                        if (!$medidor->permiteRollover() || $medidor->obtenerLecturaMaxima() === null) {
                            throw new ValidacionSuministroExcepcion("Consumo negativo detectado en medidor sin capacidad de rollover entre fechas {$lecAnt->obtenerFechaLectura()} y {$lecAct->obtenerFechaLectura()}");
                        }
                        $maxDial = $medidor->obtenerLecturaMaxima();
                        $cantTramo = bcadd(bcsub($maxDial, $valAnt, 4), $valAct, 4);
                    }

                    $tarifaTramo = $this->resolverTarifaParaFecha($suministroId, $lecAnt->obtenerFechaLectura(), $propiedadId, $unidadId);
                    $subtotalTramo = bcmul($cantTramo, $tarifaTramo->obtenerPrecioUnitario(), 2);

                    $tramosCalculados[] = [
                        'numero_tramo' => $numTramo++,
                        'medidor_id' => $medidor->obtenerId(),
                        'lectura_anterior_id' => $lecAnt->obtenerId(),
                        'lectura_actual_id' => $lecAct->obtenerId(),
                        'lectura_anterior_valor' => $valAnt,
                        'lectura_actual_valor' => $valAct,
                        'cantidad' => $cantTramo,
                        'tarifa_id' => $tarifaTramo->obtenerId(),
                        'tarifa_valor' => $tarifaTramo->obtenerPrecioUnitario(),
                        'subtotal' => $subtotalTramo,
                        'impuesto_monto' => '0.00',
                        'total' => $subtotalTramo,
                        'fecha_desde' => $lecAnt->obtenerFechaLectura(),
                        'fecha_hasta' => $lecAct->obtenerFechaLectura(),
                    ];

                    $cantidadTotal = bcadd($cantidadTotal, $cantTramo, 4);
                    $subtotalAcumulado = bcadd($subtotalAcumulado, $subtotalTramo, 2);
                    $totalAcumulado = bcadd($totalAcumulado, $subtotalTramo, 2);
                }

                $origenTipoCargo = 'SUMINISTRO_CONSUMO';
            }

            // 5. Generar Folio Oficial para la liquidaciÃ³n
            $folioLiquidacion = $this->suministroRepo->obtenerSiguienteFolio('SUMINISTRO_LIQUIDACION', 'LIQ-SUM');

            // 6. Devengar formalmente el Cargo en Cuenta Folio (FINANCIERO-2)
            $conceptoCargo = sprintf(
                'Consumo %s (%s al %s) - %s %s',
                $suministro->obtenerNombre(),
                $periodoDesde,
                $periodoHasta,
                $cantidadTotal,
                $suministro->obtenerUnidadMedida()
            );

            $precioUnitarioPonderado = count($tramosCalculados) === 1
                ? $tramosCalculados[0]['tarifa_valor']
                : (bccomp($cantidadTotal, '0.0000', 4) > 0 ? bcdiv($totalAcumulado, $cantidadTotal, 4) : '0.0000');

            $cargoCodigo = $this->cargoRepo->generarSiguienteCodigo();
            $cargoCuenta = new CargoCuenta(
                null,
                $cargoCodigo,
                $cuentaFolio->obtenerId(),
                $origenTipoCargo,
                0, // Se actualizarÃ¡ al ID de la liquidaciÃ³n
                null, // estadia_id
                $conceptoCargo,
                $cantidadTotal,
                $precioUnitarioPonderado,
                $subtotalAcumulado,
                '0.00',
                $totalAcumulado,
                '0.00',
                'PEN',
                'DEVENGADO',
                null, // motivoAnulacion
                null, // anuladoEn
                null, // anuladoPorActorId
                date('Y-m-d H:i:s'), // devengadoEn
                $actorIdFinal // creadoPorActorId
            );

            $cargoId = $this->cargoRepo->crear($cargoCuenta);

            // 7. Persistir la liquidaciÃ³n vinculando el cargo_cuenta_id
            $liquidacion = new SuministroLiquidacion(
                null,
                $folioLiquidacion,
                $suministroId,
                $suministro->obtenerModalidad(),
                $propiedadId,
                $unidadId,
                $arrendamientoId,
                $cuentaFolio->obtenerId(),
                $cargoId,
                $periodoAnio,
                $periodoMes,
                $periodoDesde,
                $periodoHasta,
                $fechaEmision,
                $fechaVenc,
                $cantidadTotal,
                $subtotalAcumulado,
                '0.00',
                $totalAcumulado,
                'PEN',
                $revision,
                $liquidacionPreviaId,
                SuministroLiquidacion::ESTADO_DEVENGADO,
                null,
                null,
                null,
                $actorIdFinal
            );

            $liquidacionId = $this->suministroRepo->crearLiquidacion($liquidacion);

            // 8. Actualizar origen_id del cargo con el ID de la liquidaciÃ³n
            $stmtUpdCargo = $this->pdo->prepare('UPDATE cargos_cuenta SET origen_id = :liq_id WHERE id = :cargo_id');
            $stmtUpdCargo->execute([
                'liq_id' => $liquidacionId,
                'cargo_id' => $cargoId,
            ]);

            // 9. Persistir cada uno de los tramos
            foreach ($tramosCalculados as $tc) {
                $tramoEntidad = new SuministroLiquidacionTramo(
                    null,
                    $liquidacionId,
                    $tc['numero_tramo'],
                    $tc['medidor_id'],
                    $tc['lectura_anterior_id'],
                    $tc['lectura_actual_id'],
                    $tc['lectura_anterior_valor'],
                    $tc['lectura_actual_valor'],
                    $tc['cantidad'],
                    $tc['tarifa_id'],
                    $tc['tarifa_valor'],
                    $tc['subtotal'],
                    $tc['impuesto_monto'],
                    $tc['total'],
                    $tc['fecha_desde'],
                    $tc['fecha_hasta']
                );
                $this->suministroRepo->crearTramo($tramoEntidad);
            }

            if ($debeCerrarTx) {
                $this->pdo->commit();
            }

            return $this->suministroRepo->buscarLiquidacionPorId($liquidacionId);
        } catch (Throwable $e) {
            if ($debeCerrarTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Anula una liquidaciÃ³n de suministro y gestiona con integridad sus cargos y aplicaciones de pago asociadas (D-081).
     */
    public function anularLiquidacion(int $liquidacionId, string $motivo, int $actorId): bool
    {
        $liq = $this->suministroRepo->buscarLiquidacionPorId($liquidacionId);
        if ($liq === null) {
            throw new SuministroNoEncontradoExcepcion("LiquidaciÃ³n ID {$liquidacionId} inexistente");
        }

        if ($liq->esAnulado()) {
            throw new ValidacionSuministroExcepcion("La liquidaciÃ³n ID {$liquidacionId} ya fue anulada");
        }

        $motivo = trim($motivo);
        if ($motivo === '') {
            throw new ValidacionSuministroExcepcion('El motivo de anulaciÃ³n es estrictamente obligatorio');
        }

        $actorIdFinal = $this->resolverActorId($actorId);

        $debeCerrarTx = !$this->pdo->inTransaction();
        if ($debeCerrarTx) {
            $this->pdo->beginTransaction();
        }

        try {
            $cargoId = $liq->obtenerCargoCuentaId();
            $cargo = $this->cargoRepo->obtenerPorId($cargoId, true);

            if ($cargo !== null && $cargo->esDevengado()) {
                // Verificar si tiene aplicaciones activas de pagos
                $aplicacionesActivas = $this->aplicacionRepo->listarPorCargo($cargoId);
                foreach ($aplicacionesActivas as $app) {
                    if ($app->obtenerEstado() === 'ACTIVA') {
                        $montoApp = $app->obtenerMontoAplicado();
                        $pagoId = $app->obtenerPagoId();

                        // 1. Revertir aplicaciÃ³n
                        $stmtRev = $this->pdo->prepare(
                            'UPDATE aplicaciones_pago
                             SET estado = "REVERTIDA",
                                 revertida_en = NOW(),
                                 revertida_por_actor_id = :actor_id
                             WHERE id = :id'
                        );
                        $stmtRev->execute(['actor_id' => $actorIdFinal, 'id' => $app->obtenerId()]);

                        // 2. Disminuir monto aplicado del pago (libera fondos a favor del folio)
                        $this->pagoRepo->actualizarMontoAplicado($pagoId, '-' . $montoApp);

                        // 3. Disminuir monto aplicado acumulado del cargo
                        $this->cargoRepo->actualizarMontoAplicado($cargoId, '-' . $montoApp);
                    }
                }

                // Anular el cargo en FINANCIERO-2
                $this->cargoRepo->actualizarEstado(
                    $cargoId,
                    'ANULADO',
                    null,
                    $motivo,
                    $actorIdFinal,
                    date('Y-m-d H:i:s')
                );
            }

            // Anular la liquidaciÃ³n en suministros
            $this->suministroRepo->anularLiquidacion($liquidacionId, $motivo, $actorIdFinal);

            if ($debeCerrarTx) {
                $this->pdo->commit();
            }

            return true;
        } catch (Throwable $e) {
            if ($debeCerrarTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Reliquida un perÃ­odo corrigiendo lecturas o tarifas, preservando trazabilidad de la liquidaciÃ³n previa (D-081).
     */
    public function reliquidarPorCorreccion(int $liquidacionPreviaId, string $motivo, int $actorId): SuministroLiquidacion
    {
        $previa = $this->suministroRepo->buscarLiquidacionPorId($liquidacionPreviaId);
        if ($previa === null) {
            throw new SuministroNoEncontradoExcepcion("LiquidaciÃ³n ID {$liquidacionPreviaId} inexistente");
        }

        if ($previa->esAnulado()) {
            throw new ValidacionSuministroExcepcion("La liquidaciÃ³n previa ya se encuentra anulada");
        }

        $debeCerrarTx = !$this->pdo->inTransaction();
        if ($debeCerrarTx) {
            $this->pdo->beginTransaction();
        }

        try {
            $cargoPrevioId = $previa->obtenerCargoCuentaId();
            $aplicacionesPrevias = $this->aplicacionRepo->listarPorCargo($cargoPrevioId);
            $pagosParaReaplicar = [];
            foreach ($aplicacionesPrevias as $ap) {
                if ($ap->obtenerEstado() === 'ACTIVA') {
                    $pagosParaReaplicar[] = [
                        'pago_id' => $ap->obtenerPagoId(),
                        'monto_aplicado_previo' => $ap->obtenerMontoAplicado(),
                    ];
                }
            }

            // 1. Anular formalmente la liquidaciÃ³n previa (des-aplicando pagos a su favor sin destruir historial)
            $this->anularLiquidacion($liquidacionPreviaId, "CorrecciÃ³n/ReliquidaciÃ³n: {$motivo}", $actorId);

            // 2. Emitir nueva liquidaciÃ³n incrementando revisiÃ³n
            $nueva = $this->liquidarPeriodoArrendamiento(
                $previa->obtenerArrendamientoId(),
                $previa->obtenerSuministroId(),
                $previa->obtenerPeriodoDesde(),
                $previa->obtenerPeriodoHasta(),
                $previa->obtenerFechaVencimiento(),
                $actorId,
                $previa->obtenerRevision() + 1,
                $liquidacionPreviaId
            );

            $nuevoCargoId = $nueva->obtenerCargoCuentaId();
            $saldoPendienteNuevoCargo = $nueva->obtenerTotal();

            // 3. Re-aplicar formalmente los pagos que cubrÃ­an la liquidaciÃ³n previa hacia el nuevo cargo
            foreach ($pagosParaReaplicar as $pInfo) {
                if (bccomp($saldoPendienteNuevoCargo, '0.00', 2) <= 0) {
                    break;
                }

                $pagoId = (int) $pInfo['pago_id'];
                $pago = $this->pagoRepo->obtenerPorId($pagoId, true);
                if ($pago === null || !$pago->estaConfirmado()) {
                    continue;
                }

                $saldoDisponiblePago = $pago->calcularSaldoDisponible();
                if (bccomp($saldoDisponiblePago, '0.00', 2) <= 0) {
                    continue;
                }

                // El monto a reaplicar es el menor entre el saldo disponible del pago,
                // lo que estuvo aplicado en la previa, y el saldo pendiente del nuevo cargo
                $topeMonto = bccomp($saldoDisponiblePago, $pInfo['monto_aplicado_previo'], 2) <= 0
                    ? $saldoDisponiblePago
                    : $pInfo['monto_aplicado_previo'];

                $montoAReaplicar = bccomp($topeMonto, $saldoPendienteNuevoCargo, 2) <= 0
                    ? $topeMonto
                    : $saldoPendienteNuevoCargo;

                if (bccomp($montoAReaplicar, '0.00', 2) > 0) {
                    $codigoApl = $this->aplicacionRepo->generarSiguienteCodigo();
                    $nuevaApl = new AplicacionPago(
                        null,
                        $codigoApl,
                        $pagoId,
                        $nuevoCargoId,
                        $montoAReaplicar,
                        'PEN',
                        'ACTIVA',
                        null,
                        null,
                        $this->resolverActorId($actorId)
                    );
                    $this->aplicacionRepo->crear($nuevaApl);
                    $this->pagoRepo->actualizarMontoAplicado($pagoId, $montoAReaplicar);
                    $this->cargoRepo->actualizarMontoAplicado($nuevoCargoId, $montoAReaplicar);

                    $saldoPendienteNuevoCargo = bcsub($saldoPendienteNuevoCargo, $montoAReaplicar, 2);
                }
            }

            if ($debeCerrarTx) {
                $this->pdo->commit();
            }

            return $nueva;
        } catch (Throwable $e) {
            if ($debeCerrarTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function obtenerLiquidacion(int $id): SuministroLiquidacion
    {
        $l = $this->suministroRepo->buscarLiquidacionPorId($id);
        if ($l === null) {
            throw new SuministroNoEncontradoExcepcion("LiquidaciÃ³n ID {$id} inexistente");
        }

        return $l;
    }

    public function listarLiquidaciones(?int $arrendamientoId = null, ?int $suministroId = null, ?string $estado = null): array
    {
        return $this->suministroRepo->listarLiquidaciones($arrendamientoId, $suministroId, $estado);
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
