<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

use CamargoPMS\Excepciones\ConflictoDisponibilidadExcepcion;
use CamargoPMS\Excepciones\CotizacionInvalidaExcepcion;
use CamargoPMS\Excepciones\EntidadNoEncontradaExcepcion;
use CamargoPMS\Excepciones\ValidacionExcepcion;
use CamargoPMS\Modelos\CotizacionResumen;
use CamargoPMS\Modelos\DesgloseNocheCotizacion;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Repositorios\PropiedadRepositorio;
use CamargoPMS\Repositorios\TipoUnidadRepositorio;
use CamargoPMS\Repositorios\UnidadRepositorio;
use DateTimeImmutable;
use PDO;

/**
 * Servicio Soberano de Cotización de Alojamiento y Autoridad Monetaria (WORDPRESS-1B).
 *
 * Principios Vinculantes:
 * 1. AUTORIDAD MONETARIA CENTRAL: El backend es la única fuente que calcula precios.
 *    WordPress jamás calcula tarifas ni envía importes libres.
 * 2. RESOLUCIÓN NOCHE A NOCHE: Resuelve la tarifa aplicable para cada fecha del intervalo.
 * 3. NO BLOQUEA INVENTARIO: La cotización es puramente consultiva; no inserta bloqueos
 *    en `inventario_diario_unidades`.
 * 4. SNAPSHOT FIRMADO Y REPRODUCIBLE: Emite un token HMAC-SHA256 para verificación
 *    inmutable durante la posterior creación del hold / reserva.
 * 5. CONTRATO MONETARIO D-069: Aritmética BCMath en 'PEN', 4 decimales intermedios,
 *    redondeo final ROUND_HALF_UP a 2 decimales, impuesto provisional 0.00.
 */
class CotizacionServicio
{
    private PDO $pdo;
    private DisponibilidadServicio $disponibilidadServicio;
    private TarifaAlojamientoServicio $tarifaServicio;
    private PropiedadRepositorio $propiedadRepo;
    private UnidadRepositorio $unidadRepo;
    private TipoUnidadRepositorio $tipoUnidadRepo;

    /** Tiempo de vigencia estándar de una cotización en segundos (30 minutos) */
    public const VIGENCIA_COTIZACION_SEGUNDOS = 1800;

    public function __construct(
        ?PDO $pdo = null,
        ?DisponibilidadServicio $disponibilidadServicio = null,
        ?TarifaAlojamientoServicio $tarifaServicio = null,
        ?PropiedadRepositorio $propiedadRepo = null,
        ?UnidadRepositorio $unidadRepo = null,
        ?TipoUnidadRepositorio $tipoUnidadRepo = null
    ) {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->disponibilidadServicio = $disponibilidadServicio ?? new DisponibilidadServicio($this->pdo);
        $this->tarifaServicio = $tarifaServicio ?? new TarifaAlojamientoServicio($this->pdo);
        $this->propiedadRepo = $propiedadRepo ?? new PropiedadRepositorio($this->pdo);
        $this->unidadRepo = $unidadRepo ?? new UnidadRepositorio($this->pdo);
        $this->tipoUnidadRepo = $tipoUnidadRepo ?? new TipoUnidadRepositorio($this->pdo);
    }

    /**
     * Cotiza soberanamente una estancia verificando disponibilidad y calculando tarifas noche a noche.
     *
     * @param array<string, mixed> $criterios
     * @return CotizacionResumen
     * @throws ValidacionExcepcion
     * @throws ConflictoDisponibilidadExcepcion
     */
    public function cotizarEstancia(array $criterios): CotizacionResumen
    {
        // 1. Validar intervalo temporal hotelero (D-066)
        $fechaEntrada = trim((string) ($criterios['fecha_entrada'] ?? ''));
        $fechaSalida = trim((string) ($criterios['fecha_salida'] ?? ''));
        $intervalo = $this->disponibilidadServicio->validarIntervaloHotelero($fechaEntrada, $fechaSalida);
        $noches = $intervalo['noches'];

        // 2. Validar propiedad
        $propiedadId = (int) ($criterios['propiedad_id'] ?? 0);
        $propiedad = $this->propiedadRepo->buscarPorId($propiedadId);
        if ($propiedad === null || !$propiedad->estaActiva()) {
            throw new ValidacionExcepcion("La propiedad hotelera con ID {$propiedadId} no existe o no se encuentra activa.");
        }

        $huespedes = max(1, (int) ($criterios['huespedes'] ?? 1));

        $tipoUnidadId = isset($criterios['tipo_unidad_id']) && (int) $criterios['tipo_unidad_id'] > 0
            ? (int) $criterios['tipo_unidad_id']
            : null;

        $unidadId = isset($criterios['unidad_id']) && (int) $criterios['unidad_id'] > 0
            ? (int) $criterios['unidad_id']
            : null;

        if ($tipoUnidadId === null && $unidadId === null) {
            throw new ValidacionExcepcion('Debe especificar al menos un tipo de unidad o una unidad física para cotizar.');
        }

        $unidadSeleccionada = null;
        $tipoUnidadSeleccionado = null;

        // 3. Resolución de unidad y verificación de disponibilidad (FAIL-FAST)
        if ($unidadId !== null) {
            $unidad = $this->unidadRepo->buscarPorId($unidadId);
            if ($unidad === null || !$unidad->estaActiva()) {
                throw new ValidacionExcepcion("La unidad habitacional con ID {$unidadId} no existe o está inactiva.");
            }
            if ((int) $unidad->obtenerPropiedadId() !== $propiedadId) {
                throw new ValidacionExcepcion("La unidad ID {$unidadId} no pertenece a la propiedad ID {$propiedadId}.");
            }
            if ($unidad->obtenerCapacidadPersonas() < $huespedes) {
                throw new ValidacionExcepcion("La unidad '{$unidad->obtenerCodigo()}' tiene capacidad para {$unidad->obtenerCapacidadPersonas()} persona(s), insuficiente para {$huespedes} huésped(es).");
            }

            // Verificar si la unidad física está libre en el intervalo
            $disponibles = $this->disponibilidadServicio->consultarDisponibilidad($fechaEntrada, $fechaSalida, $propiedadId);
            $unidadDisponible = false;
            foreach ($disponibles['unidades'] as $uDisp) {
                if ($uDisp['id'] === $unidadId && $uDisp['disponible']) {
                    $unidadDisponible = true;
                    break;
                }
            }

            if (!$unidadDisponible) {
                throw new ConflictoDisponibilidadExcepcion(
                    $unidadId,
                    $fechaEntrada,
                    "La unidad seleccionada '{$unidad->obtenerCodigo()}' no tiene disponibilidad para las fechas solicitadas.",
                    409
                );
            }

            $unidadSeleccionada = $unidad;
            $tipoUnidadId = $unidad->obtenerTipoUnidadId();
            $tipoUnidadSeleccionado = $this->tipoUnidadRepo->buscarPorId($tipoUnidadId);
        } else {
            // Se cotiza por TIPO DE UNIDAD
            $tipoUnidad = $this->tipoUnidadRepo->buscarPorId($tipoUnidadId);
            if ($tipoUnidad === null || !$tipoUnidad->estaActivo()) {
                throw new ValidacionExcepcion("El tipo de unidad con ID {$tipoUnidadId} no existe o está inactivo.");
            }

            // Buscar unidades operativas de este tipo con capacidad suficiente
            $disponibles = $this->disponibilidadServicio->consultarDisponibilidad($fechaEntrada, $fechaSalida, $propiedadId);
            $candidatasDisponibles = [];
            foreach ($disponibles['unidades'] as $uDisp) {
                if ($uDisp['tipo_unidad_id'] === $tipoUnidadId && $uDisp['disponible']) {
                    $uObj = $this->unidadRepo->buscarPorId($uDisp['id']);
                    if ($uObj !== null && $uObj->obtenerCapacidadPersonas() >= $huespedes) {
                        $candidatasDisponibles[] = $uObj;
                    }
                }
            }

            if (empty($candidatasDisponibles)) {
                throw new ConflictoDisponibilidadExcepcion(
                    0,
                    $fechaEntrada,
                    "No hay unidades disponibles del tipo '{$tipoUnidad->obtenerNombre()}' para las fechas y ocupación solicitadas.",
                    409
                );
            }

            $tipoUnidadSeleccionado = $tipoUnidad;
            // Se toma una candidata referencial para resolver posibles overrides
            $unidadSeleccionada = $candidatasDisponibles[0];
            $unidadId = $unidadSeleccionada->obtenerId();
        }

        // 4. Resolución soberana noche a noche
        $desgloseNoches = [];
        $subtotalAcumulado = '0.0000';

        $inicioTs = strtotime($fechaEntrada);
        for ($i = 0; $i < $noches; $i++) {
            $fechaNoche = date('Y-m-d', strtotime("+{$i} days", $inicioTs));

            $tarifa = $this->tarifaServicio->resolverTarifaParaFecha(
                $propiedadId,
                $tipoUnidadId,
                $unidadId,
                $fechaNoche
            );

            $precioNoche = $tarifa->obtenerPrecioNoche();
            $subtotalAcumulado = bcadd($subtotalAcumulado, $precioNoche, 4);

            $desgloseNoches[] = new DesgloseNocheCotizacion(
                fecha: $fechaNoche,
                tarifaId: (int) $tarifa->obtenerId(),
                tarifaNombre: $tarifa->obtenerNombre(),
                ambito: $tarifa->obtenerAmbitoTipo(),
                precioNoche: $precioNoche,
                moneda: 'PEN'
            );
        }

        // 5. Aplicar política monetaria D-069
        $subtotalRedondeado = $this->redondearBc($subtotalAcumulado, 2);
        $tasaImpuesto = '0.0000';
        $impuestoRedondeado = '0.00'; // D-069 regla tributaria provisional
        $totalRedondeado = bcadd($subtotalRedondeado, $impuestoRedondeado, 2);
        $tarifaPromedio = bcdiv($subtotalRedondeado, (string) $noches, 2);

        // 6. Generar snapshot reproducible y token firmado
        $idCotizacion = 'COT-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
        $expiraEn = date('Y-m-d H:i:s', time() + self::VIGENCIA_COTIZACION_SEGUNDOS);

        $cotizacion = new CotizacionResumen(
            idCotizacion: $idCotizacion,
            propiedadId: $propiedadId,
            propiedadNombre: $propiedad->obtenerNombre(),
            tipoUnidadId: $tipoUnidadId,
            tipoUnidadNombre: $tipoUnidadSeleccionado?->obtenerNombre(),
            unidadId: $unidadId,
            unidadCodigo: $unidadSeleccionada?->obtenerCodigo(),
            fechaEntrada: $fechaEntrada,
            fechaSalida: $fechaSalida,
            noches: $noches,
            huespedes: $huespedes,
            desgloseNoches: $desgloseNoches,
            tarifaPromedioNoche: $tarifaPromedio,
            subtotal: $subtotalRedondeado,
            tasaImpuesto: $tasaImpuesto,
            impuesto: $impuestoRedondeado,
            total: $totalRedondeado,
            monedaCodigo: 'PEN',
            expiraEn: $expiraEn,
            creadoEn: date('Y-m-d H:i:s')
        );

        $token = $this->generarTokenCotizacion($cotizacion);
        $cotizacion->fijarTokenCotizacion($token);

        return $cotizacion;
    }

    /**
     * Genera un token HMAC-SHA256 verificable para blindar la cotización contra alteraciones.
     */
    public function generarTokenCotizacion(CotizacionResumen $cotizacion): string
    {
        $claveFirma = Configuracion::obtener('APP_KEY', 'CamargoPMS_CotizacionKey_2026_Secure');

        $payload = [
            'cot_id' => $cotizacion->obtenerIdCotizacion(),
            'prop_id' => $cotizacion->obtenerPropiedadId(),
            'tipo_id' => $cotizacion->obtenerTipoUnidadId(),
            'uni_id' => $cotizacion->obtenerUnidadId(),
            'checkin' => $cotizacion->obtenerFechaEntrada(),
            'checkout' => $cotizacion->obtenerFechaSalida(),
            'noches' => $cotizacion->obtenerNoches(),
            'huespedes' => $cotizacion->obtenerHuespedes(),
            'total' => $cotizacion->obtenerTotal(),
            'moneda' => $cotizacion->obtenerMonedaCodigo(),
            'expira' => $cotizacion->obtenerExpiraEn(),
        ];

        $jsonPayload = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $firma = hash_hmac('sha256', $jsonPayload, $claveFirma);

        $envelope = [
            'p' => $payload,
            's' => $firma,
        ];

        return rtrim(strtr(base64_encode(json_encode($envelope, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }

    /**
     * Valida la firma criptográfica y vigencia temporal de un token de cotización.
     *
     * @param string $token
     * @return array<string, mixed> Payload validado
     * @throws CotizacionInvalidaExcepcion
     */
    public function validarTokenCotizacion(string $token): array
    {
        $token = trim($token);
        if ($token === '') {
            throw new CotizacionInvalidaExcepcion('Token de cotización ausente.');
        }

        $b64 = strtr($token, '-_', '+/');
        $resto = strlen($b64) % 4;
        if ($resto !== 0) {
            $b64 .= str_repeat('=', 4 - $resto);
        }

        $json = base64_decode($b64, true);
        if ($json === false) {
            throw new CotizacionInvalidaExcepcion('Formato de token de cotización corrupto o no válido.');
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new CotizacionInvalidaExcepcion('Estructura interna del token de cotización no decodificable.');
        }

        if (!isset($data['p'], $data['s']) || !is_array($data['p'])) {
            throw new CotizacionInvalidaExcepcion('Sobre de cotización incompleto.');
        }

        $payload = $data['p'];
        $firmaRecibida = (string) $data['s'];

        $claveFirma = Configuracion::obtener('APP_KEY', 'CamargoPMS_CotizacionKey_2026_Secure');
        $jsonPayload = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $firmaEsperada = hash_hmac('sha256', $jsonPayload, $claveFirma);

        if (!hash_equals($firmaEsperada, $firmaRecibida)) {
            throw new CotizacionInvalidaExcepcion('Firma de cotización no válida o alterada.');
        }

        $expiraEn = (string) ($payload['expira'] ?? '');
        if ($expiraEn === '' || $expiraEn < date('Y-m-d H:i:s')) {
            throw new CotizacionInvalidaExcepcion("La cotización '{$payload['cot_id']}' ha expirado ({$expiraEn}). Solicite una nueva cotización.");
        }

        return $payload;
    }

    /**
     * Redondeo bancario simétrico HALF_UP exacto para BCMath (D-069).
     */
    private function redondearBc(string $valor, int $decimales = 2): string
    {
        $esNegativo = str_starts_with($valor, '-');
        $valorAbs = ltrim($valor, '-');

        $factor = '0.' . str_repeat('0', $decimales) . '5';
        $sumado = bcadd($valorAbs, $factor, $decimales + 1);

        $partes = explode('.', $sumado);
        $enteros = $partes[0];
        $fraccion = substr($partes[1] ?? str_repeat('0', $decimales), 0, $decimales);

        $resultado = $enteros . '.' . str_pad($fraccion, $decimales, '0', STR_PAD_RIGHT);

        return $esNegativo ? '-' . $resultado : $resultado;
    }
}
