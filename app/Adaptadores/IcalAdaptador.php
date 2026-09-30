<?php

declare(strict_types=1);

namespace CamargoPMS\Adaptadores;

use CamargoPMS\Modelos\EventoIcalDTO;
use CamargoPMS\Nucleo\Configuracion;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use InvalidArgumentException;
use RuntimeException;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Reader;
use Sabre\VObject\RecurrenceException;

/**
 * Adaptador Especializado para la Especificación iCalendar (RFC 5545).
 *
 * Encapsula completamente la biblioteca sabre/vobject para aislar el dominio
 * de dependencias externas. Gestiona el parsing robusto, line-folding, secuencias
 * de escape, zonas horarias, semántica hotelera [inicio, fin), expansión acotada
 * de recurrencias y serialización estandarizada con privacidad garantizada.
 */
class IcalAdaptador
{
    private DateTimeZone $zonaHorariaPredeterminada;
    private int $diasExpansionPasado;
    private int $diasExpansionFuturo;

    /**
     * Constructor.
     *
     * @param DateTimeZone|null $tz Zona horaria de referencia (default 'America/Lima').
     * @param int|null $diasPasado Días hacia el pasado para expandir recurrencias (default de .env o 7).
     * @param int|null $diasFuturo Días hacia el futuro para expandir recurrencias (default de .env o 365).
     */
    public function __construct(
        ?DateTimeZone $tz = null,
        ?int $diasPasado = null,
        ?int $diasFuturo = null
    ) {
        $tzNombre = (string) Configuracion::obtener('APP_TIMEZONE', 'America/Lima');
        $this->zonaHorariaPredeterminada = $tz ?? new DateTimeZone($tzNombre);
        $this->diasExpansionPasado = $diasPasado ?? (int) Configuracion::obtener('ICAL_EXPANSION_PAST_DAYS', 7);
        $this->diasExpansionFuturo = $diasFuturo ?? (int) Configuracion::obtener('ICAL_EXPANSION_FUTURE_DAYS', 365);
    }

    /**
     * Parsea y normaliza un payload ICS a una colección de EventoIcalDTOs neutrales.
     *
     * @param string $contenidoIcs Texto crudo del archivo .ics.
     * @param DateTimeZone|null $tzPropiedad Zona horaria específica de la propiedad si difiere de la default.
     * @return array<EventoIcalDTO>
     * @throws InvalidArgumentException Si el archivo no es un VCALENDAR válido.
     * @throws RuntimeException Si la expansión de recurrencias falla catastróficamente.
     */
    public function parsear(string $contenidoIcs, ?DateTimeZone $tzPropiedad = null): array
    {
        $contenido = trim($contenidoIcs);
        if ($contenido === '') {
            return [];
        }

        try {
            /** @var VCalendar $vcalendar */
            $vcalendar = Reader::read($contenido);
        } catch (Exception $e) {
            throw new InvalidArgumentException("Error al parsear el feed iCalendar con Sabre/VObject: " . $e->getMessage(), 0, $e);
        }

        if (!isset($vcalendar->VEVENT)) {
            // Feed válido pero sin eventos (ej. calendario nuevo o vacío)
            return [];
        }

        $tz = $tzPropiedad ?? $this->zonaHorariaPredeterminada;

        // 1. Expansión acotada y segura de recurrencias (Bounded Horizon)
        $calendarioAProcesar = $vcalendar;
        $tieneRecurrencias = false;
        foreach ($vcalendar->VEVENT as $vevent) {
            if (isset($vevent->RRULE) || isset($vevent->RDATE)) {
                $tieneRecurrencias = true;
                break;
            }
        }

        if ($tieneRecurrencias) {
            $ahora = new DateTimeImmutable('now', $tz);
            $horizonteInicio = $ahora->modify("-{$this->diasExpansionPasado} days");
            $horizonteFin = $ahora->modify("+{$this->diasExpansionFuturo} days");

            try {
                $expandido = $vcalendar->expand($horizonteInicio, $horizonteFin, $tz);
                if ($expandido instanceof VCalendar) {
                    $calendarioAProcesar = $expandido;
                }
            } catch (RecurrenceException $e) {
                // Fail-safe: No interpretar recurrencia rota ni liberar fechas previas
                throw new RuntimeException("Fallo en la evaluación de reglas de recurrencia (RRULE): " . $e->getMessage(), 0, $e);
            } catch (Exception $e) {
                throw new RuntimeException("Error inesperado expandiendo recurrencias iCal: " . $e->getMessage(), 0, $e);
            }
        }

        // 2. Extracción y normalización a DTOs
        $eventosNormalizados = [];

        if (!isset($calendarioAProcesar->VEVENT)) {
            return [];
        }

        foreach ($calendarioAProcesar->VEVENT as $vevent) {
            $uid = isset($vevent->UID) ? trim((string) $vevent->UID) : '';
            if ($uid === '') {
                // RFC 5545 sección 3.8.4.7: UID es obligatorio para identificar un componente
                continue;
            }

            // Manejo de STATUS
            $status = isset($vevent->STATUS) ? strtoupper(trim((string) $vevent->STATUS)) : 'CONFIRMED';
            $estadoEvento = ($status === 'CANCELLED') ? 'CANCELADO' : 'ACTIVO';

            // Fechas de inicio y fin con preservación de semántica hotelera [inicio, fin)
            $fechas = $this->normalizarFechasHotel($vevent, $tz);
            if ($fechas === null) {
                // Evento sin fechas resolubles
                continue;
            }

            [$fechaInicio, $fechaFin, $noches] = $fechas;

            // Metadatos complementarios
            $resumen = isset($vevent->SUMMARY) ? trim((string) $vevent->SUMMARY) : 'Bloqueo Canal Externo';
            if ($resumen === '') {
                $resumen = 'Bloqueo Canal Externo';
            }

            $descripcion = isset($vevent->DESCRIPTION) ? trim((string) $vevent->DESCRIPTION) : null;

            $ultimaMod = null;
            if (isset($vevent->{'LAST-MODIFIED'})) {
                try {
                    $dt = $vevent->{'LAST-MODIFIED'}->getDateTime($tz);
                    $ultimaMod = $dt->format('Y-m-d H:i:s');
                } catch (Exception) {
                    $ultimaMod = null;
                }
            }

            $secuencia = isset($vevent->SEQUENCE) ? (int) (string) $vevent->SEQUENCE : null;
            $esRecurrente = isset($vevent->{'RECURRENCE-ID'}) || isset($vevent->RRULE);
            $rrule = isset($vevent->RRULE) ? (string) $vevent->RRULE : null;

            $eventosNormalizados[] = new EventoIcalDTO(
                uid: $uid,
                fechaInicio: $fechaInicio,
                fechaFin: $fechaFin,
                noches: $noches,
                resumen: $resumen,
                descripcion: $descripcion,
                estadoEvento: $estadoEvento,
                ultimaModificacion: $ultimaMod,
                secuencia: $secuencia,
                esRecurrente: $esRecurrente,
                recurrenciaRrule: $rrule
            );
        }

        return $eventosNormalizados;
    }

    /**
     * Normaliza las fechas DTSTART y DTEND respetando VALUE=DATE y DATE-TIME.
     *
     * Semántica hotelera:
     * - VALUE=DATE: [DTSTART, DTEND). DTSTART: 20261010, DTEND: 20261013 -> Bloquea noches 10, 11 y 12. Check-out el 13. Noches = 3.
     * - DATE-TIME: normaliza a la zona horaria del hotel.
     *
     * @param mixed $vevent Componente Sabre VEVENT.
     * @param DateTimeZone $tz
     * @return array{0: string, 1: string, 2: int}|null [fechaInicio, fechaFin, noches]
     */
    private function normalizarFechasHotel(mixed $vevent, DateTimeZone $tz): ?array
    {
        if (!isset($vevent->DTSTART)) {
            return null;
        }

        try {
            $dtStartProp = $vevent->DTSTART;
            $esAllDay = ($dtStartProp->getValueType() === 'DATE') || (method_exists($dtStartProp, 'hasTime') && !$dtStartProp->hasTime());

            if ($esAllDay) {
                // Formato YYYYMMDD o YYYY-MM-DD
                $rawStart = (string) $dtStartProp->getValue();
                $dtStart = new DateTimeImmutable(substr($rawStart, 0, 8), $tz);

                if (isset($vevent->DTEND)) {
                    $rawEnd = (string) $vevent->DTEND->getValue();
                    $dtEnd = new DateTimeImmutable(substr($rawEnd, 0, 8), $tz);
                } elseif (isset($vevent->DURATION)) {
                    $duration = new \DateInterval((string) $vevent->DURATION);
                    $dtEnd = $dtStart->add($duration);
                } else {
                    // Si no tiene DTEND ni DURATION, por RFC 5545 dura 1 día
                    $dtEnd = $dtStart->modify('+1 day');
                }
            } else {
                // Formato DATE-TIME (con Z, TZID o floating)
                $dtStart = $dtStartProp->getDateTime($tz);

                if (isset($vevent->DTEND)) {
                    $dtEnd = $vevent->DTEND->getDateTime($tz);
                } elseif (isset($vevent->DURATION)) {
                    $duration = new \DateInterval((string) $vevent->DURATION);
                    $dtEnd = $dtStart->add($duration);
                } else {
                    $dtEnd = $dtStart->modify('+1 day');
                }
            }

            $inicioStr = $dtStart->format('Y-m-d');
            $finStr = $dtEnd->format('Y-m-d');

            // Cálculo determinista de noches hoteleras por fechas calendario [inicio, fin)
            $calStart = DateTimeImmutable::createFromFormat('!Y-m-d', $inicioStr);
            $calEnd = DateTimeImmutable::createFromFormat('!Y-m-d', $finStr);
            $diff = ($calStart && $calEnd) ? $calStart->diff($calEnd) : $dtStart->diff($dtEnd);
            $noches = (int) $diff->days;

            if ($noches <= 0) {
                // Caso borde: DTSTART == DTEND en fechas hoteleras representa al menos 1 noche
                $calEnd = ($calStart ?? $dtStart)->modify('+1 day');
                $finStr = $calEnd->format('Y-m-d');
                $noches = 1;
            }

            return [$inicioStr, $finStr, $noches];
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Serializa una colección de bloqueos a un archivo iCalendar estándar (RFC 5545)
     * garantizando absoluta privacidad y UID estable.
     *
     * @param array<array{uid: string, fecha_inicio: string, fecha_fin: string}> $bloques
     * @param string $nombreCalendario Nombre del calendario para X-WR-CALNAME.
     * @return string Contenido ICS con CRLF.
     */
    public function generarCalendario(array $bloques, string $nombreCalendario = 'Camargo PMS'): string
    {
        $vcalendar = new VCalendar();

        $vcalendar->PRODID = '-//Camargo Hostelería//Camargo PMS 1.0//ES';
        $vcalendar->VERSION = '2.0';
        $vcalendar->CALSCALE = 'GREGORIAN';
        $vcalendar->METHOD = 'PUBLISH';
        $vcalendar->{'X-WR-CALNAME'} = $nombreCalendario;
        $vcalendar->{'X-WR-TIMEZONE'} = $this->zonaHorariaPredeterminada->getName();

        $dtstamp = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Ymd\THis\Z');

        foreach ($bloques as $b) {
            $vevent = $vcalendar->createComponent('VEVENT');

            $vevent->UID = $b['uid'];
            $vevent->DTSTAMP = $dtstamp;

            // Formato VALUE=DATE gregoriano Ymd
            $inicioFormatted = str_replace('-', '', $b['fecha_inicio']);
            $finFormatted = str_replace('-', '', $b['fecha_fin']);

            $vevent->DTSTART = $inicioFormatted;
            $vevent->DTSTART['VALUE'] = 'DATE';

            $vevent->DTEND = $finFormatted;
            $vevent->DTEND['VALUE'] = 'DATE';

            $vevent->SUMMARY = 'No disponible';
            $vevent->STATUS = 'CONFIRMED';

            // Omitir categóricamente DESCRIPTION y cualquier dato personal o tarifario
            $vcalendar->add($vevent);
        }

        return $vcalendar->serialize();
    }
}
