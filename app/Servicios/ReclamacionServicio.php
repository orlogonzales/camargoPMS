<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

use CamargoPMS\Excepciones\ReclamacionNoEncontradaExcepcion;
use CamargoPMS\Excepciones\TransicionReclamacionInvalidaExcepcion;
use CamargoPMS\Excepciones\ValidacionReclamacionExcepcion;
use CamargoPMS\Modelos\Feriado;
use CamargoPMS\Modelos\Persona;
use CamargoPMS\Modelos\Reclamacion;
use CamargoPMS\Modelos\ReclamacionActuacion;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Repositorios\ActorAuditoriaRepositorio;
use CamargoPMS\Repositorios\AuditoriaRepositorio;
use CamargoPMS\Repositorios\DocumentoPersonaRepositorio;
use CamargoPMS\Repositorios\EmpresaRepositorio;
use CamargoPMS\Repositorios\FeriadoRepositorio;
use CamargoPMS\Repositorios\PaisRepositorio;
use CamargoPMS\Repositorios\PersonaRepositorio;
use CamargoPMS\Repositorios\PropiedadRepositorio;
use CamargoPMS\Repositorios\ReclamacionActuacionRepositorio;
use CamargoPMS\Repositorios\ReclamacionRepositorio;
use CamargoPMS\Repositorios\ReclamacionSecuenciaRepositorio;
use CamargoPMS\Repositorios\TipoDocumentoRepositorio;
use DateTimeImmutable;
use PDO;
use Throwable;

/**
 * Servicio central de dominio para el Libro de Reclamaciones (RECLAMACIONES-1).
 *
 * Principios:
 * 1. RECLAMO ≠ QUEJA (Tipificación legal excluyente)
 * 2. RECLAMANTE → PERSONA con Snapshot T0 congelado
 * 3. EXPEDIENTE INMUTABLE Y CERO DELETE
 * 4. PLAZO LEGAL: 15 días hábiles improrrogables con suspensión de hasta 5 días hábiles
 * 5. RESILIENCIA DOCUMENTAL: El registro en BD prevalece ante contingencias del PDF
 */
class ReclamacionServicio
{
    private PDO $pdo;
    private ReclamacionRepositorio $reclamacionRepo;
    private ReclamacionActuacionRepositorio $actuacionRepo;
    private ReclamacionSecuenciaRepositorio $secuenciaRepo;
    private FeriadoRepositorio $feriadoRepo;
    private CalculadorPlazosReclamacion $calculadorPlazos;
    private PersonaRepositorio $personaRepo;
    private PersonaServicio $personaServicio;
    private PropiedadRepositorio $propiedadRepo;
    private EmpresaRepositorio $empresaRepo;
    private TipoDocumentoRepositorio $tipoDocumentoRepo;
    private PaisRepositorio $paisRepo;
    private ReclamacionDocumentoServicio $documentoServicio;
    private AuditoriaServicio $auditoriaServicio;
    private ActorAuditoriaRepositorio $actorRepo;

    public function __construct(
        ?PDO $pdo = null,
        ?ReclamacionRepositorio $reclamacionRepo = null,
        ?ReclamacionActuacionRepositorio $actuacionRepo = null,
        ?ReclamacionSecuenciaRepositorio $secuenciaRepo = null,
        ?FeriadoRepositorio $feriadoRepo = null,
        ?CalculadorPlazosReclamacion $calculadorPlazos = null,
        ?PersonaRepositorio $personaRepo = null,
        ?PersonaServicio $personaServicio = null,
        ?PropiedadRepositorio $propiedadRepo = null,
        ?EmpresaRepositorio $empresaRepo = null,
        ?TipoDocumentoRepositorio $tipoDocumentoRepo = null,
        ?PaisRepositorio $paisRepo = null,
        ?ReclamacionDocumentoServicio $documentoServicio = null,
        ?AuditoriaServicio $auditoriaServicio = null,
        ?ActorAuditoriaRepositorio $actorRepo = null
    ) {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->reclamacionRepo = $reclamacionRepo ?? new ReclamacionRepositorio($this->pdo);
        $this->actuacionRepo = $actuacionRepo ?? new ReclamacionActuacionRepositorio($this->pdo);
        $this->secuenciaRepo = $secuenciaRepo ?? new ReclamacionSecuenciaRepositorio($this->pdo);
        $this->feriadoRepo = $feriadoRepo ?? new FeriadoRepositorio($this->pdo);
        $this->calculadorPlazos = $calculadorPlazos ?? new CalculadorPlazosReclamacion($this->feriadoRepo);
        $this->personaRepo = $personaRepo ?? new PersonaRepositorio($this->pdo);
        $this->personaServicio = $personaServicio ?? new PersonaServicio($this->pdo, $this->personaRepo);
        $this->propiedadRepo = $propiedadRepo ?? new PropiedadRepositorio($this->pdo);
        $this->empresaRepo = $empresaRepo ?? new EmpresaRepositorio($this->pdo);
        $this->tipoDocumentoRepo = $tipoDocumentoRepo ?? new TipoDocumentoRepositorio($this->pdo);
        $this->paisRepo = $paisRepo ?? new PaisRepositorio($this->pdo);
        $this->documentoServicio = $documentoServicio ?? new ReclamacionDocumentoServicio($this->pdo, $this->reclamacionRepo);
        $this->auditoriaServicio = $auditoriaServicio ?? new AuditoriaServicio($this->pdo);
        $this->actorRepo = $actorRepo ?? new ActorAuditoriaRepositorio($this->pdo);
    }

    /**
     * Interpone formalmente un reclamo o queja en el Libro de Reclamaciones.
     *
     * @param array<string, mixed> $datos
     * @param int|null $actorId ID del actor si es asistido por recepción; null si es público/virtual
     * @param string $canal VIRTUAL o PRESENCIAL
     * @return Reclamacion
     */
    public function interponerReclamacion(array $datos, ?int $actorId = null, string $canal = Reclamacion::CANAL_VIRTUAL): Reclamacion
    {
        $errores = $this->validarDatosInterposicion($datos);
        if (!empty($errores)) {
            throw new ValidacionReclamacionExcepcion("Existen campos requeridos o inválidos en la reclamación.", $errores);
        }

        // 1. Validar propiedad y empresa
        $propiedadId = (int) $datos['propiedad_id'];
        $propiedad = $this->propiedadRepo->buscarPorId($propiedadId);
        if (!$propiedad || !$propiedad->estaActiva()) {
            throw new ValidacionReclamacionExcepcion("El establecimiento o sede seleccionado no es válido.", ['propiedad_id' => 'Sede inválida o inactiva']);
        }

        $empresaId = (int) ($datos['empresa_id'] ?? 1);
        $empresa = $this->empresaRepo->buscarPorId($empresaId);
        if (!$empresa) {
            throw new ValidacionReclamacionExcepcion("La empresa asociada a la sede no existe.", ['empresa_id' => 'Empresa no encontrada']);
        }

        // 2. Resolver o registrar Persona del consumidor (fuera de la transacción de reclamaciones)
        $consumidorPersona = $this->resolverOCrearPersonaConsumidor($datos);
        $consumidorPersonaId = (int) $consumidorPersona->obtenerId();

        // 3. Resolver apoderado si es menor de edad
        $esMenorEdad = !empty($datos['es_menor_edad']);
        $apoderadoPersonaId = null;
        $apoderadoPersona = null;
        if ($esMenorEdad) {
            $apoderadoPersona = $this->resolverOCrearPersonaApoderado($datos);
            $apoderadoPersonaId = (int) $apoderadoPersona->obtenerId();
        }

        // 4. Congelar Snapshots Legales T0 Inmutables
        $snapshotConsumidor = $this->construirSnapshotConsumidor($datos, $consumidorPersona, $apoderadoPersona);
        $snapshotProveedor = $this->construirSnapshotProveedor($empresa, $propiedad);

        // 5. Transacción atómica para correlativo, inserción y actuación inicial
        $this->pdo->beginTransaction();

        try {
            $anio = (int) date('Y');
            $correlativo = $this->secuenciaRepo->obtenerYSiguienteCorrelativo($propiedadId, $anio);

            $codigoHoja = sprintf('%06d-%d', $correlativo, $anio);
            $codigoSede = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', (string) ($propiedad->obtenerCodigo() ?: 'CAM')));
            $codigoSede = substr($codigoSede, 0, 4) ?: 'CAM';
            $codigoInterno = sprintf('LR-%s-%d-%05d', $codigoSede, $anio, $correlativo);

            $fechaInterposicion = date('Y-m-d H:i:s');
            $fechaLimiteLegal = $this->calculadorPlazos->calcularFechaLimiteLegalInicial($fechaInterposicion);

            $actorIdFinal = $actorId ?? 1; // 1 = Actor Sistema / Plataforma Digital

            $reclamacion = new Reclamacion(
                null,
                $codigoHoja,
                $codigoInterno,
                $empresaId,
                $propiedadId,
                $consumidorPersonaId,
                $esMenorEdad,
                $apoderadoPersonaId,
                strtoupper(trim((string) $datos['tipo'])),
                strtoupper(trim((string) $datos['tipo_bien'])),
                number_format((float) ($datos['monto_reclamado'] ?? 0), 2, '.', ''),
                strtoupper(trim((string) ($datos['moneda'] ?? 'PEN'))),
                trim((string) $datos['descripcion_bien']),
                trim((string) $datos['detalle_reclamacion']),
                trim((string) $datos['pedido_consumidor']),
                $canal,
                Reclamacion::ESTADO_REGISTRADO,
                $fechaInterposicion,
                $fechaLimiteLegal,
                0,
                null,
                null,
                null,
                null,
                null,
                null,
                $snapshotConsumidor,
                $snapshotProveedor,
                $actorIdFinal,
                $actorIdFinal
            );

            $reclamacionPersistida = $this->reclamacionRepo->guardar($reclamacion);
            $reclamacionId = (int) $reclamacionPersistida->obtenerId();

            // Asentar actuación inicial append-only
            $actuacionInicial = new ReclamacionActuacion(
                null,
                $reclamacionId,
                $actorIdFinal,
                ReclamacionActuacion::TIPO_RECEPCION_INICIAL,
                'Registro formal e ingreso del expediente al Libro de Reclamaciones.',
                $canal === Reclamacion::CANAL_VIRTUAL ? ReclamacionActuacion::MEDIO_WEB : ReclamacionActuacion::MEDIO_FISICO,
                (string) ($snapshotConsumidor['email'] ?? ''),
                date('Y-m-d H:i:s')
            );
            $this->actuacionRepo->insertar($actuacionInicial);

            // Registrar auditoría formal D-061
            $this->auditoriaServicio->registrar(
                accion: 'CREAR',
                modulo: 'reclamaciones',
                entidad: 'reclamaciones',
                entidadId: $reclamacionId,
                descripcion: "Interposición de {$reclamacionPersistida->obtenerTipo()} [{$codigoHoja}] - Ref: [{$codigoInterno}]",
                valoresAnteriores: null,
                valoresNuevos: $reclamacionPersistida->aArreglo(),
                actor: $actorIdFinal,
                pdoTransaccional: $this->pdo
            );

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        // 6. Resiliencia Documental: Generar PDF en storage sin abortar si Dompdf tiene alguna contingencia
        try {
            $this->documentoServicio->generarHojaReclamacionPdf($reclamacionPersistida, $actorIdFinal);
        } catch (Throwable $eDoc) {
            // Se registra el incidente pero NO se elimina ni cancela la reclamación legal
            error_log("Aviso de contingencia documental al emitir PDF de reclamación [{$codigoInterno}]: " . $eDoc->getMessage());
        }

        return $this->reclamacionRepo->buscarPorId($reclamacionId, true) ?? $reclamacionPersistida;
    }

    /**
     * Agrega una nota interna u observación append-only al expediente.
     */
    public function agregarNotaInterna(int $reclamacionId, string $descripcion, int $actorId): ReclamacionActuacion
    {
        $reclamacion = $this->obtenerReclamacionOExcepcion($reclamacionId);

        $desc = trim($descripcion);
        if (mb_strlen($desc) < 3) {
            throw new ValidacionReclamacionExcepcion("La nota interna debe tener al menos 3 caracteres.", ['descripcion' => 'Descripción requerida']);
        }

        $actuacion = new ReclamacionActuacion(
            null,
            $reclamacionId,
            $actorId,
            ReclamacionActuacion::TIPO_NOTA_INTERNA,
            $desc
        );

        $nuevaActuacion = $this->actuacionRepo->insertar($actuacion);

        $this->auditoriaServicio->registrar(
            accion: 'ACTUALIZAR',
            modulo: 'reclamaciones',
            entidad: 'reclamacion_actuaciones',
            entidadId: (int) $nuevaActuacion->obtenerId(),
            descripcion: "Nota interna agregada al expediente [{$reclamacion->obtenerCodigoInterno()}]",
            valoresAnteriores: null,
            valoresNuevos: $nuevaActuacion->aArreglo(),
            actor: $actorId
        );

        return $nuevaActuacion;
    }

    /**
     * Formula una propuesta u ofrecimiento de solución al consumidor, activando la suspensión regulatoria de hasta 5 días hábiles.
     */
    public function formularOfrecimiento(
        int $reclamacionId,
        string $descripcionPropuesta,
        string $medioNotificacion,
        string $destinatario,
        int $actorId
    ): Reclamacion {
        $reclamacion = $this->obtenerReclamacionOExcepcion($reclamacionId);

        if (in_array($reclamacion->obtenerEstado(), [Reclamacion::ESTADO_ATENDIDO, Reclamacion::ESTADO_CONCLUIDO_POR_ACUERDO, Reclamacion::ESTADO_ANULADO], true)) {
            throw new TransicionReclamacionInvalidaExcepcion($reclamacion->obtenerEstado(), 'FORMULAR_OFRECIMIENTO');
        }

        $propuestaLimpia = trim($descripcionPropuesta);
        if (mb_strlen($propuestaLimpia) < 10) {
            throw new ValidacionReclamacionExcepcion("La propuesta de solución debe contener al menos 10 caracteres.", ['descripcion' => 'Propuesta insuficiente']);
        }

        $hoy = date('Y-m-d');
        $diasConsumidos = $this->calculadorPlazos->contarDiasHabilesTranscurridos(
            $reclamacion->obtenerFechaInterposicion(),
            $hoy
        );
        $fechaLimiteOfrecimiento = $this->calculadorPlazos->calcularFechaLimiteOfrecimiento($hoy);

        $this->pdo->beginTransaction();

        try {
            $valoresAnteriores = $reclamacion->aArreglo();

            $reclamacion->suspenderPorOfrecimiento($hoy, $fechaLimiteOfrecimiento, $diasConsumidos);
            $this->reclamacionRepo->guardar($reclamacion);

            $actuacion = new ReclamacionActuacion(
                null,
                $reclamacionId,
                $actorId,
                ReclamacionActuacion::TIPO_OFRECIMIENTO_SOLUCION,
                $propuestaLimpia,
                $medioNotificacion,
                $destinatario,
                $hoy
            );
            $this->actuacionRepo->insertar($actuacion);

            $this->auditoriaServicio->registrar(
                accion: 'ACTUALIZAR',
                modulo: 'reclamaciones',
                entidad: 'reclamaciones',
                entidadId: $reclamacionId,
                descripcion: "Ofrecimiento de solución formulado en [{$reclamacion->obtenerCodigoInterno()}]. Suspensión hasta {$fechaLimiteOfrecimiento}.",
                valoresAnteriores: $valoresAnteriores,
                valoresNuevos: $reclamacion->aArreglo(),
                actor: $actorId,
                pdoTransaccional: $this->pdo
            );

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $this->reclamacionRepo->buscarPorId($reclamacionId, true) ?? $reclamacion;
    }

    /**
     * Registra la respuesta del consumidor frente al ofrecimiento de solución formulado.
     */
    public function responderOfrecimiento(int $reclamacionId, bool $aceptado, string $sustento, int $actorId): Reclamacion
    {
        $reclamacion = $this->obtenerReclamacionOExcepcion($reclamacionId);

        if ($reclamacion->obtenerEstado() !== Reclamacion::ESTADO_SUSPENDIDO_OFRECIMIENTO) {
            throw new TransicionReclamacionInvalidaExcepcion($reclamacion->obtenerEstado(), 'RESPONDER_OFRECIMIENTO');
        }

        $sustentoLimpio = trim($sustento);
        if ($sustentoLimpio === '') {
            $sustentoLimpio = $aceptado ? 'El consumidor aceptó formalmente el ofrecimiento de solución.' : 'El consumidor rechazó el ofrecimiento de solución.';
        }

        $this->pdo->beginTransaction();

        try {
            $valoresAnteriores = $reclamacion->aArreglo();

            if ($aceptado) {
                $reclamacion->concluirPorAcuerdo();
                $tipoActuacion = ReclamacionActuacion::TIPO_RESPUESTA_OFRECIMIENTO_ACEPTADO;
            } else {
                // Reanudar el plazo de 15 días hábiles descontando los días transcurridos previamente
                $hoy = date('Y-m-d');
                $reanudacion = $this->calculadorPlazos->calcularReanudacionPlazo($hoy, $reclamacion->obtenerDiasHabilesConsumidos());
                $reclamacion->reanudar($reanudacion['nueva_fecha_limite'], $reclamacion->obtenerDiasHabilesConsumidos());
                $tipoActuacion = ReclamacionActuacion::TIPO_RESPUESTA_OFRECIMIENTO_RECHAZADO;
            }

            $this->reclamacionRepo->guardar($reclamacion);

            $actuacion = new ReclamacionActuacion(
                null,
                $reclamacionId,
                $actorId,
                $tipoActuacion,
                $sustentoLimpio,
                ReclamacionActuacion::MEDIO_WEB,
                null,
                date('Y-m-d H:i:s')
            );
            $this->actuacionRepo->insertar($actuacion);

            $this->auditoriaServicio->registrar(
                accion: 'ACTUALIZAR',
                modulo: 'reclamaciones',
                entidad: 'reclamaciones',
                entidadId: $reclamacionId,
                descripcion: "Respuesta a ofrecimiento: " . ($aceptado ? "ACEPTADO (Concluido)" : "RECHAZADO (Reanudado hasta {$reclamacion->obtenerFechaLimiteLegal()})"),
                valoresAnteriores: $valoresAnteriores,
                valoresNuevos: $reclamacion->aArreglo(),
                actor: $actorId,
                pdoTransaccional: $this->pdo
            );

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $this->reclamacionRepo->buscarPorId($reclamacionId, true) ?? $reclamacion;
    }

    /**
     * Registra la expiración del plazo de 5 días hábiles del ofrecimiento sin respuesta del consumidor, reanudando el conteo legal.
     */
    public function expirarOfrecimiento(int $reclamacionId, int $actorId): Reclamacion
    {
        $reclamacion = $this->obtenerReclamacionOExcepcion($reclamacionId);

        if ($reclamacion->obtenerEstado() !== Reclamacion::ESTADO_SUSPENDIDO_OFRECIMIENTO) {
            throw new TransicionReclamacionInvalidaExcepcion($reclamacion->obtenerEstado(), 'EXPIRAR_OFRECIMIENTO');
        }

        $this->pdo->beginTransaction();

        try {
            $valoresAnteriores = $reclamacion->aArreglo();

            $hoy = date('Y-m-d');
            $reanudacion = $this->calculadorPlazos->calcularReanudacionPlazo($hoy, $reclamacion->obtenerDiasHabilesConsumidos());
            $reclamacion->reanudar($reanudacion['nueva_fecha_limite'], $reclamacion->obtenerDiasHabilesConsumidos());

            $this->reclamacionRepo->guardar($reclamacion);

            $actuacion = new ReclamacionActuacion(
                null,
                $reclamacionId,
                $actorId,
                ReclamacionActuacion::TIPO_EXPIRACION_OFRECIMIENTO,
                "Expiró el plazo de 5 días hábiles otorgado para el ofrecimiento de solución. Se reanuda el plazo legal de atención hasta el {$reclamacion->obtenerFechaLimiteLegal()}.",
                ReclamacionActuacion::MEDIO_WEB,
                null,
                date('Y-m-d H:i:s')
            );
            $this->actuacionRepo->insertar($actuacion);

            $this->auditoriaServicio->registrar(
                accion: 'ACTUALIZAR',
                modulo: 'reclamaciones',
                entidad: 'reclamaciones',
                entidadId: $reclamacionId,
                descripcion: "Expiración de ofrecimiento en [{$reclamacion->obtenerCodigoInterno()}]. Plazo reanudado.",
                valoresAnteriores: $valoresAnteriores,
                valoresNuevos: $reclamacion->aArreglo(),
                actor: $actorId,
                pdoTransaccional: $this->pdo
            );

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $this->reclamacionRepo->buscarPorId($reclamacionId, true) ?? $reclamacion;
    }

    /**
     * Emite la respuesta formal al consumidor, concluyendo la atención del expediente en estado ATENDIDO.
     */
    public function emitirRespuestaFormal(
        int $reclamacionId,
        string $contenidoRespuesta,
        string $medioNotificacion,
        string $destinatario,
        int $actorId,
        ?string $archivoAdjuntoPath = null
    ): Reclamacion {
        $reclamacion = $this->obtenerReclamacionOExcepcion($reclamacionId);

        if (in_array($reclamacion->obtenerEstado(), [Reclamacion::ESTADO_ATENDIDO, Reclamacion::ESTADO_CONCLUIDO_POR_ACUERDO, Reclamacion::ESTADO_ANULADO], true)) {
            throw new TransicionReclamacionInvalidaExcepcion($reclamacion->obtenerEstado(), 'EMITIR_RESPUESTA_FORMAL');
        }

        $contenidoLimpio = trim($contenidoRespuesta);
        if (mb_strlen($contenidoLimpio) < 10) {
            throw new ValidacionReclamacionExcepcion("El contenido de la respuesta formal debe tener al menos 10 caracteres.", ['contenido' => 'Respuesta requerida']);
        }

        $this->pdo->beginTransaction();

        try {
            $valoresAnteriores = $reclamacion->aArreglo();

            $reclamacion->atender();
            $this->reclamacionRepo->guardar($reclamacion);

            $actuacion = new ReclamacionActuacion(
                null,
                $reclamacionId,
                $actorId,
                ReclamacionActuacion::TIPO_RESPUESTA_FORMAL,
                $contenidoLimpio,
                $medioNotificacion,
                $destinatario,
                date('Y-m-d H:i:s'),
                null,
                $archivoAdjuntoPath
            );
            $this->actuacionRepo->insertar($actuacion);

            $this->auditoriaServicio->registrar(
                accion: 'ACTUALIZAR',
                modulo: 'reclamaciones',
                entidad: 'reclamaciones',
                entidadId: $reclamacionId,
                descripcion: "Emisión de respuesta formal y conclusión de expediente [{$reclamacion->obtenerCodigoInterno()}]",
                valoresAnteriores: $valoresAnteriores,
                valoresNuevos: $reclamacion->aArreglo(),
                actor: $actorId,
                pdoTransaccional: $this->pdo
            );

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $this->reclamacionRepo->buscarPorId($reclamacionId, true) ?? $reclamacion;
    }

    /**
     * Anula de forma supervisada un expediente por error material o duplicidad técnica fundamentada.
     */
    public function anularReclamacion(int $reclamacionId, string $motivo, int $actorId): Reclamacion
    {
        $reclamacion = $this->obtenerReclamacionOExcepcion($reclamacionId);

        if ($reclamacion->obtenerEstado() === Reclamacion::ESTADO_ANULADO) {
            throw new TransicionReclamacionInvalidaExcepcion($reclamacion->obtenerEstado(), 'ANULAR');
        }

        $motivoLimpio = trim($motivo);
        if (mb_strlen($motivoLimpio) < 10) {
            throw new ValidacionReclamacionExcepcion("La anulación supervisada exige un motivo fundamentado de al menos 10 caracteres.", ['motivo' => 'Motivo insuficiente']);
        }

        $this->pdo->beginTransaction();

        try {
            $valoresAnteriores = $reclamacion->aArreglo();

            $reclamacion->anular($motivoLimpio, $actorId);
            $this->reclamacionRepo->guardar($reclamacion);

            $actuacion = new ReclamacionActuacion(
                null,
                $reclamacionId,
                $actorId,
                ReclamacionActuacion::TIPO_ANULACION_SUPERVISADA,
                "ANULACIÓN SUPERVISADA: " . $motivoLimpio,
                ReclamacionActuacion::MEDIO_WEB,
                null,
                date('Y-m-d H:i:s')
            );
            $this->actuacionRepo->insertar($actuacion);

            $this->auditoriaServicio->registrar(
                accion: 'ANULAR',
                modulo: 'reclamaciones',
                entidad: 'reclamaciones',
                entidadId: $reclamacionId,
                descripcion: "Anulación supervisada de expediente [{$reclamacion->obtenerCodigoInterno()}]. Motivo: {$motivoLimpio}",
                valoresAnteriores: $valoresAnteriores,
                valoresNuevos: $reclamacion->aArreglo(),
                actor: $actorId,
                pdoTransaccional: $this->pdo
            );

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $this->reclamacionRepo->buscarPorId($reclamacionId, true) ?? $reclamacion;
    }

    /**
     * Regenera la Hoja de Reclamación PDF oficial en almacenamiento.
     */
    public function regenerarPdf(int $reclamacionId, int $actorId): array
    {
        $reclamacion = $this->obtenerReclamacionOExcepcion($reclamacionId);
        return $this->documentoServicio->generarHojaReclamacionPdf($reclamacion, $actorId);
    }

    /**
     * Obtiene el binario PDF oficial para descarga.
     */
    public function obtenerPdfParaDescarga(int $reclamacionId): array
    {
        $reclamacion = $this->obtenerReclamacionOExcepcion($reclamacionId);
        return $this->documentoServicio->obtenerORecrearBinarioPdf($reclamacion);
    }

    public function obtenerPorId(int $id, bool $enriquecido = true): ?Reclamacion
    {
        return $this->reclamacionRepo->buscarPorId($id, $enriquecido);
    }

    public function obtenerPorCodigoInterno(string $codigo, bool $enriquecido = true): ?Reclamacion
    {
        return $this->reclamacionRepo->buscarPorCodigoInterno($codigo, $enriquecido);
    }

    public function obtenerPorCodigoHoja(string $codigo, bool $enriquecido = true): ?Reclamacion
    {
        return $this->reclamacionRepo->buscarPorCodigoHoja($codigo, $enriquecido);
    }

    /**
     * @param array<string, mixed> $filtros
     * @param int $limite
     * @param int $offset
     * @return array<Reclamacion>
     */
    public function listar(array $filtros = [], int $limite = 25, int $offset = 0): array
    {
        return $this->reclamacionRepo->listar($filtros, $limite, $offset);
    }

    /**
     * @param array<string, mixed> $filtros
     * @return int
     */
    public function contar(array $filtros = []): int
    {
        return $this->reclamacionRepo->contar($filtros);
    }

    /**
     * @param array<string, mixed> $filtros
     * @return array<string, int>
     */
    public function obtenerKpis(array $filtros = []): array
    {
        return $this->reclamacionRepo->obtenerKpis($filtros);
    }

    // =========================================================================
    // GESTIÓN DEL CALENDARIO DE FERIADOS
    // =========================================================================

    /**
     * @param array<string, mixed> $filtros
     * @return array<Feriado>
     */
    public function listarFeriados(array $filtros = []): array
    {
        return $this->feriadoRepo->listarTodos($filtros);
    }

    public function guardarFeriado(array $datos, int $actorId): Feriado
    {
        $fecha = trim((string) ($datos['fecha'] ?? ''));
        $desc = trim((string) ($datos['descripcion'] ?? ''));
        $tipo = trim((string) ($datos['tipo'] ?? Feriado::TIPO_FERIADO_LEGAL));
        $aplicaPrivado = !empty($datos['aplica_sector_privado']);
        $activo = !isset($datos['activo']) || !empty($datos['activo']);

        $id = !empty($datos['id']) ? (int) $datos['id'] : null;

        $feriado = new Feriado($id, $fecha, $desc, $tipo, $aplicaPrivado, $activo);
        $feriadoGuardado = $this->feriadoRepo->guardar($feriado);

        $this->auditoriaServicio->registrar(
            accion: $id !== null ? 'ACTUALIZAR' : 'CREAR',
            modulo: 'reclamaciones',
            entidad: 'calendario_feriados',
            entidadId: (int) $feriadoGuardado->obtenerId(),
            descripcion: ($id !== null ? 'Actualización' : 'Registro') . " de feriado [{$fecha} - {$desc}]",
            valoresAnteriores: null,
            valoresNuevos: $feriadoGuardado->aArreglo(),
            actor: $actorId
        );

        return $feriadoGuardado;
    }

    public function alternarEstadoFeriado(int $id, int $actorId): bool
    {
        $res = $this->feriadoRepo->alternarEstado($id);
        if ($res) {
            $this->auditoriaServicio->registrar(
                accion: 'CAMBIAR_ESTADO',
                modulo: 'reclamaciones',
                entidad: 'calendario_feriados',
                entidadId: $id,
                descripcion: "Alternado estado activo de feriado ID [{$id}]",
                valoresAnteriores: null,
                valoresNuevos: null,
                actor: $actorId
            );
        }
        return $res;
    }

    // =========================================================================
    // MÉTODOS PRIVADOS DE SOPORTE E INVARIANTES
    // =========================================================================

    private function obtenerReclamacionOExcepcion(int $id): Reclamacion
    {
        $rec = $this->reclamacionRepo->buscarPorId($id, true);
        if (!$rec) {
            throw new ReclamacionNoEncontradaExcepcion($id);
        }
        return $rec;
    }

    /**
     * @param array<string, mixed> $datos
     * @return array<string, string>
     */
    private function validarDatosInterposicion(array $datos): array
    {
        $errores = [];

        if (empty($datos['propiedad_id']) || (int) $datos['propiedad_id'] <= 0) {
            $errores['propiedad_id'] = 'Debe seleccionar el establecimiento o sede.';
        }

        $tipo = strtoupper(trim((string) ($datos['tipo'] ?? '')));
        if (!in_array($tipo, Reclamacion::TIPOS_VALIDOS, true)) {
            $errores['tipo'] = 'El tipo debe ser RECLAMO o QUEJA.';
        }

        $tipoBien = strtoupper(trim((string) ($datos['tipo_bien'] ?? '')));
        if (!in_array($tipoBien, Reclamacion::BIENES_VALIDOS, true)) {
            $errores['tipo_bien'] = 'Debe indicar si el bien contratado es PRODUCTO o SERVICIO.';
        }

        if (empty($datos['descripcion_bien']) || mb_strlen(trim((string) $datos['descripcion_bien'])) < 3) {
            $errores['descripcion_bien'] = 'La descripción del bien o servicio es obligatoria (mínimo 3 caracteres).';
        }

        if (empty($datos['detalle_reclamacion']) || mb_strlen(trim((string) $datos['detalle_reclamacion'])) < 5) {
            $errores['detalle_reclamacion'] = 'El detalle de los hechos es obligatorio (mínimo 5 caracteres).';
        }

        if (empty($datos['pedido_consumidor']) || mb_strlen(trim((string) $datos['pedido_consumidor'])) < 3) {
            $errores['pedido_consumidor'] = 'El pedido concreto al proveedor es obligatorio (mínimo 3 caracteres).';
        }

        // Datos del consumidor
        $doc = trim((string) ($datos['consumidor_numero_documento'] ?? $datos['numero_documento'] ?? ''));
        if ($doc === '') {
            $errores['consumidor_numero_documento'] = 'El número de documento del consumidor es obligatorio.';
        }

        $nombres = trim((string) ($datos['consumidor_nombres'] ?? $datos['nombres'] ?? ''));
        if ($nombres === '') {
            $errores['consumidor_nombres'] = 'Los nombres o denominación del consumidor son obligatorios.';
        }

        $email = trim((string) ($datos['consumidor_email'] ?? $datos['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errores['consumidor_email'] = 'El correo electrónico es obligatorio y debe ser válido.';
        }

        $telefono = trim((string) ($datos['consumidor_telefono'] ?? $datos['telefono'] ?? ''));
        if ($telefono === '') {
            $errores['consumidor_telefono'] = 'El teléfono de contacto es obligatorio.';
        }

        $direccion = trim((string) ($datos['consumidor_direccion'] ?? $datos['direccion'] ?? ''));
        if ($direccion === '') {
            $errores['consumidor_direccion'] = 'El domicilio o dirección del consumidor es obligatorio.';
        }

        // Validar apoderado si es menor de edad
        if (!empty($datos['es_menor_edad'])) {
            $apDoc = trim((string) ($datos['apoderado_numero_documento'] ?? ''));
            if ($apDoc === '') {
                $errores['apoderado_numero_documento'] = 'Para menores de edad, el documento del apoderado es obligatorio.';
            }
            $apNom = trim((string) ($datos['apoderado_nombres'] ?? ''));
            if ($apNom === '') {
                $errores['apoderado_nombres'] = 'Los nombres del apoderado son obligatorios.';
            }
        }

        return $errores;
    }

    private function resolverOCrearPersonaConsumidor(array $datos): Persona
    {
        $tipoDocCodigo = strtoupper(trim((string) ($datos['consumidor_tipo_documento'] ?? $datos['tipo_documento'] ?? 'DNI')));
        $numeroDoc = trim((string) ($datos['consumidor_numero_documento'] ?? $datos['numero_documento'] ?? ''));

        $personaExistente = $this->personaRepo->buscarPorDocumento($tipoDocCodigo, $numeroDoc);
        if ($personaExistente !== null) {
            return $personaExistente;
        }

        // Descomponer nombres / apellidos
        $nombres = trim((string) ($datos['consumidor_nombres'] ?? $datos['nombres'] ?? ''));
        $apellidos = trim((string) ($datos['consumidor_apellidos'] ?? $datos['apellidos'] ?? ''));
        $partesAp = explode(' ', $apellidos, 2);
        $apPaterno = $partesAp[0] ?? $apellidos;
        $apMaterno = $partesAp[1] ?? null;

        $datosPersona = [
            'nombres' => $nombres,
            'apellido_paterno' => $apPaterno,
            'apellido_materno' => $apMaterno,
            'genero' => 'NO_ESPECIFICADO',
            'pais_nacionalidad_id' => 1, // PE
            'pais_residencia_id' => 1,
            'direccion' => trim((string) ($datos['consumidor_direccion'] ?? $datos['direccion'] ?? '')),
            'estado' => 'ACTIVO',
        ];

        // Buscar tipo documento ID
        $tipoDoc = $this->tipoDocumentoRepo->buscarPorCodigo($tipoDocCodigo);
        $tipoDocId = $tipoDoc !== null ? (int) $tipoDoc->obtenerId() : 1;

        $docPrincipal = [
            'tipo_documento_id' => $tipoDocId,
            'numero_documento' => $numeroDoc,
            'pais_emisor_id' => 1,
            'es_principal' => true,
        ];

        $contactos = [];
        $email = trim((string) ($datos['consumidor_email'] ?? $datos['email'] ?? ''));
        if ($email !== '') {
            $contactos[] = [
                'tipo_contacto' => 'EMAIL',
                'valor' => $email,
                'es_principal' => true,
                'es_whatsapp' => false,
            ];
        }
        $telefono = trim((string) ($datos['consumidor_telefono'] ?? $datos['telefono'] ?? ''));
        if ($telefono !== '') {
            $contactos[] = [
                'tipo_contacto' => 'TELEFONO',
                'valor' => $telefono,
                'es_principal' => true,
                'es_whatsapp' => false,
            ];
        }

        return $this->personaServicio->crearPersona($datosPersona, $docPrincipal, $contactos);
    }

    private function resolverOCrearPersonaApoderado(array $datos): Persona
    {
        $tipoDocCodigo = strtoupper(trim((string) ($datos['apoderado_tipo_documento'] ?? 'DNI')));
        $numeroDoc = trim((string) ($datos['apoderado_numero_documento'] ?? ''));

        $personaExistente = $this->personaRepo->buscarPorDocumento($tipoDocCodigo, $numeroDoc);
        if ($personaExistente !== null) {
            return $personaExistente;
        }

        $nombres = trim((string) ($datos['apoderado_nombres'] ?? ''));
        $apellidos = trim((string) ($datos['apoderado_apellidos'] ?? ''));
        $partesAp = explode(' ', $apellidos, 2);
        $apPaterno = $partesAp[0] ?? $apellidos;
        $apMaterno = $partesAp[1] ?? null;

        $datosPersona = [
            'nombres' => $nombres,
            'apellido_paterno' => $apPaterno,
            'apellido_materno' => $apMaterno,
            'genero' => 'NO_ESPECIFICADO',
            'pais_nacionalidad_id' => 1,
            'pais_residencia_id' => 1,
            'direccion' => trim((string) ($datos['apoderado_direccion'] ?? $datos['consumidor_direccion'] ?? '')),
            'estado' => 'ACTIVO',
        ];

        $tipoDoc = $this->tipoDocumentoRepo->buscarPorCodigo($tipoDocCodigo);
        $tipoDocId = $tipoDoc !== null ? (int) $tipoDoc->obtenerId() : 1;

        $docPrincipal = [
            'tipo_documento_id' => $tipoDocId,
            'numero_documento' => $numeroDoc,
            'pais_emisor_id' => 1,
            'es_principal' => true,
        ];

        $contactos = [];
        $email = trim((string) ($datos['apoderado_email'] ?? ''));
        if ($email !== '') {
            $contactos[] = ['tipo_contacto' => 'EMAIL', 'valor' => $email, 'es_principal' => true, 'es_whatsapp' => false];
        }
        $telefono = trim((string) ($datos['apoderado_telefono'] ?? ''));
        if ($telefono !== '') {
            $contactos[] = ['tipo_contacto' => 'TELEFONO', 'valor' => $telefono, 'es_principal' => true, 'es_whatsapp' => false];
        }

        return $this->personaServicio->crearPersona($datosPersona, $docPrincipal, $contactos);
    }

    private function construirSnapshotConsumidor(array $datos, Persona $consumidor, ?Persona $apoderado): array
    {
        $tipoDoc = strtoupper(trim((string) ($datos['consumidor_tipo_documento'] ?? $datos['tipo_documento'] ?? 'DNI')));
        $numDoc = trim((string) ($datos['consumidor_numero_documento'] ?? $datos['numero_documento'] ?? ''));
        $nombres = trim((string) ($datos['consumidor_nombres'] ?? $datos['nombres'] ?? $consumidor->obtenerNombres()));
        $apellidos = trim((string) ($datos['consumidor_apellidos'] ?? $datos['apellidos'] ?? $consumidor->obtenerNombreCompleto()));
        $email = trim((string) ($datos['consumidor_email'] ?? $datos['email'] ?? ''));
        $tel = trim((string) ($datos['consumidor_telefono'] ?? $datos['telefono'] ?? ''));
        $dir = trim((string) ($datos['consumidor_direccion'] ?? $datos['direccion'] ?? $consumidor->obtenerDireccion() ?? ''));

        $snapshot = [
            'persona_id' => $consumidor->obtenerId(),
            'tipo_documento' => $tipoDoc,
            'numero_documento' => $numDoc,
            'nombres' => $nombres,
            'apellidos' => $apellidos,
            'nombre_completo' => trim("{$nombres} {$apellidos}"),
            'email' => $email,
            'telefono' => $tel,
            'direccion' => $dir,
            'nacionalidad' => 'Peruana',
        ];

        if ($apoderado !== null) {
            $apTipoDoc = strtoupper(trim((string) ($datos['apoderado_tipo_documento'] ?? 'DNI')));
            $apNumDoc = trim((string) ($datos['apoderado_numero_documento'] ?? ''));
            $apNom = trim((string) ($datos['apoderado_nombres'] ?? $apoderado->obtenerNombres()));
            $apAp = trim((string) ($datos['apoderado_apellidos'] ?? ''));
            $apEmail = trim((string) ($datos['apoderado_email'] ?? ''));
            $apTel = trim((string) ($datos['apoderado_telefono'] ?? ''));
            $apDir = trim((string) ($datos['apoderado_direccion'] ?? $dir));

            $snapshot['apoderado'] = [
                'persona_id' => $apoderado->obtenerId(),
                'tipo_documento' => $apTipoDoc,
                'numero_documento' => $apNumDoc,
                'nombres' => $apNom,
                'apellidos' => $apAp,
                'nombre_completo' => trim("{$apNom} {$apAp}"),
                'email' => $apEmail,
                'telefono' => $apTel,
                'direccion' => $apDir,
            ];
        }

        return $snapshot;
    }

    private function construirSnapshotProveedor(mixed $empresa, mixed $propiedad): array
    {
        return [
            'empresa_id' => $empresa->obtenerId(),
            'razon_social' => $empresa->obtenerRazonSocial(),
            'nombre_comercial' => $empresa->obtenerNombreComercial(),
            'ruc' => $empresa->obtenerNumeroDocumento(),
            'direccion_fiscal' => $empresa->obtenerDireccionFiscal(),
            'propiedad_id' => $propiedad->obtenerId(),
            'sede_nombre' => $propiedad->obtenerNombre(),
            'sede_codigo' => $propiedad->obtenerCodigo(),
            'sede_direccion' => $propiedad->obtenerDireccion(),
            'sede_ciudad' => $propiedad->obtenerProvincia() ?? $propiedad->obtenerDistrito() ?? 'Lima',
        ];
    }
}
