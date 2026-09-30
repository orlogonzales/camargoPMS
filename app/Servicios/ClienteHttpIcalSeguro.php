<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

use CamargoPMS\Nucleo\Configuracion;
use InvalidArgumentException;
use RuntimeException;

/**
 * Cliente HTTP Seguro y Especializado para Descarga de Feeds iCalendar.
 *
 * Implementa defensa en profundidad contra Server-Side Request Forgery (SSRF),
 * fijación de IP contra DNS Rebinding (CURLOPT_RESOLVE), control estricto de redirecciones
 * manuales (máximo 3 saltos) y límites deterministas de tamaño y tiempo de ejecución.
 */
class ClienteHttpIcalSeguro
{
    private const MAX_REDIRECCIONES = 3;
    private const TIMEOUT_CONEXION_SEGUNDOS = 5;

    private int $timeoutTotal;
    private int $maxBytes;

    /**
     * Constructor. Inicializa configuraciones de red y límites operativos.
     *
     * @param int|null $timeoutTotal Timeout total en segundos (default de .env o 10s).
     * @param int|null $maxBytes Tamaño máximo en bytes (default de .env o 2 MiB).
     */
    public function __construct(?int $timeoutTotal = null, ?int $maxBytes = null)
    {
        $this->timeoutTotal = $timeoutTotal ?? (int) Configuracion::obtener('ICAL_HTTP_TIMEOUT', 10);
        $this->maxBytes = $maxBytes ?? (int) Configuracion::obtener('ICAL_HTTP_MAX_BYTES', 2097152); // 2 MB
    }

    /**
     * Descarga el contenido de un feed iCalendar con blindaje SSRF completo.
     *
     * @param string $url URL externa del feed iCal.
     * @return array{codigo_http: int, contenido: string, duracion_ms: int}
     * @throws InvalidArgumentException Si la URL o IPs violan políticas de seguridad.
     * @throws RuntimeException Si la transferencia cURL falla o supera los límites.
     */
    public function descargar(string $url): array
    {
        $urlActual = trim($url);
        $redirecciones = 0;
        $tiempoInicio = microtime(true);

        while ($redirecciones <= self::MAX_REDIRECCIONES) {
            $parsedUrl = $this->validarYParsearUrl($urlActual);
            $host = $parsedUrl['host'];
            $puerto = $parsedUrl['port'] ?? 443;

            // 1. Resolución DNS manual y validación exhaustiva de cada IP
            $ipValidada = $this->resolverYValidarDns($host);

            // 2. Ejecución cURL con fijación de IP (Anti-DNS Rebinding)
            $resultadoHttp = $this->ejecutarPeticionCurl($urlActual, $host, $puerto, $ipValidada);
            $codigo = $resultadoHttp['codigo'];

            // 3. Manejo de redirecciones manuales
            if (in_array($codigo, [301, 302, 303, 307, 308], true)) {
                $redirecciones++;
                if ($redirecciones > self::MAX_REDIRECCIONES) {
                    throw new RuntimeException("Exceso de redirecciones HTTP (máximo permitido: " . self::MAX_REDIRECCIONES . ").");
                }

                $location = $resultadoHttp['headers']['location'] ?? null;
                if (!$location) {
                    throw new RuntimeException("Respuesta de redirección HTTP $codigo sin cabecera Location.");
                }

                $urlActual = $this->resolverUrlRelativa($urlActual, $location);
                continue;
            }

            // 4. Validación de respuesta HTTP
            if ($codigo !== 200) {
                $duracionMs = (int) round((microtime(true) - $tiempoInicio) * 1000);
                return [
                    'codigo_http' => $codigo,
                    'contenido' => $resultadoHttp['cuerpo'],
                    'duracion_ms' => $duracionMs,
                ];
            }

            $cuerpo = $resultadoHttp['cuerpo'];

            // 5. Validación básica de estructura iCalendar
            if (!str_contains($cuerpo, 'BEGIN:VCALENDAR') || !str_contains($cuerpo, 'END:VCALENDAR')) {
                throw new RuntimeException("El contenido descargado no es un calendario iCalendar (RFC 5545) válido.");
            }

            $duracionMs = (int) round((microtime(true) - $tiempoInicio) * 1000);
            return [
                'codigo_http' => 200,
                'contenido' => $cuerpo,
                'duracion_ms' => $duracionMs,
            ];
        }

        throw new RuntimeException("Exceso de redirecciones alcanzado.");
    }

    /**
     * Valida sintácticamente la URL y restringe estrictamente a HTTPS en puerto 443.
     *
     * @param string $url
     * @return array{scheme: string, host: string, port?: int, path?: string, query?: string}
     */
    public function validarYParsearUrl(string $url): array
    {
        $partes = parse_url($url);
        if ($partes === false || !isset($partes['scheme'], $partes['host'])) {
            throw new InvalidArgumentException("URL de importación malformada o inválida.");
        }

        $scheme = strtolower($partes['scheme']);
        if ($scheme !== 'https') {
            throw new InvalidArgumentException("Solo se permiten conexiones seguras HTTPS (protocolo recibido: '$scheme').");
        }

        $puerto = $partes['port'] ?? 443;
        if ($puerto !== 443) {
            throw new InvalidArgumentException("Solo se permite el puerto estándar HTTPS 443 (puerto recibido: $puerto).");
        }

        $host = strtolower($partes['host']);
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            throw new InvalidArgumentException("Host prohibido por políticas SSRF: '$host'.");
        }

        return $partes;
    }

    /**
     * Resuelve DNS (registros A y AAAA) y valida que NINGUNA de las IPs pertenezca a rangos privados/reservados.
     *
     * @param string $host
     * @return string Primera IP pública validada.
     */
    public function resolverYValidarDns(string $host): string
    {
        // Si el host ya es una IP literal
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $this->validarIpSegura($host);
            return $host;
        }

        $ips = [];

        // Consulta de registros A (IPv4)
        $registrosA = @dns_get_record($host, DNS_A);
        if (is_array($registrosA)) {
            foreach ($registrosA as $reg) {
                if (isset($reg['ip'])) {
                    $ips[] = $reg['ip'];
                }
            }
        }

        // Si dns_get_record no trajo IPs A, fallback a gethostbynamel
        if (empty($ips)) {
            $ipsFallback = @gethostbynamel($host);
            if (is_array($ipsFallback)) {
                $ips = array_merge($ips, $ipsFallback);
            }
        }

        // Consulta de registros AAAA (IPv6)
        $registrosAAAA = @dns_get_record($host, DNS_AAAA);
        if (is_array($registrosAAAA)) {
            foreach ($registrosAAAA as $reg) {
                if (isset($reg['ipv6'])) {
                    $ips[] = $reg['ipv6'];
                }
            }
        }

        if (empty($ips)) {
            throw new RuntimeException("No se pudo resolver ninguna dirección IP para el host '$host'.");
        }

        // Validar CADA IP resuelta. Si UNA SOLA es no pública, se rechaza inmediatamente (FAIL CLOSED)
        foreach ($ips as $ip) {
            $this->validarIpSegura($ip);
        }

        return $ips[0];
    }

    /**
     * Verifica que una dirección IP (IPv4 o IPv6) no pertenezca a ningún rango privado,
     * loopback, link-local, multicast, CGNAT o metadata de nube.
     *
     * @param string $ip
     * @throws InvalidArgumentException Si la IP es privada o reservada.
     */
    public function validarIpSegura(string $ip): void
    {
        // 1. Verificación básica con flags estándar de PHP
        $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
        if (filter_var($ip, FILTER_VALIDATE_IP, $flags) === false) {
            throw new InvalidArgumentException("Dirección IP no pública o reservada detectada por SSRF: $ip");
        }

        // 2. Verificación si es IPv4 o IPv4-mapped IPv6
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $this->validarIpV4RangosEspecificos($ip);
            return;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $this->validarIpV6RangosEspecificos($ip);
            return;
        }

        throw new InvalidArgumentException("Formato de dirección IP inválido: $ip");
    }

    /**
     * Valida rangos específicos IPv4 (incluyendo Carrier-Grade NAT y metadata de nube).
     */
    private function validarIpV4RangosEspecificos(string $ip): void
    {
        $ipLong = ip2long($ip);
        if ($ipLong === false) {
            throw new InvalidArgumentException("Dirección IPv4 no representable: $ip");
        }

        // Lista de rangos IPv4 prohibidos: [inicio, fin]
        $rangosProhibidos = [
            '0.0.0.0/8'          => ['0.0.0.0', '0.255.255.255'],
            '10.0.0.0/8'         => ['10.0.0.0', '10.255.255.255'],
            '100.64.0.0/10'      => ['100.64.0.0', '100.127.255.255'], // CGNAT (RFC 6598)
            '127.0.0.0/8'        => ['127.0.0.0', '127.255.255.255'], // Loopback
            '169.254.0.0/16'     => ['169.254.0.0', '169.254.255.255'], // Link-Local / Cloud Metadata
            '172.16.0.0/12'      => ['172.16.0.0', '172.31.255.255'],
            '192.0.0.0/24'       => ['192.0.0.0', '192.0.0.255'],
            '192.0.2.0/24'       => ['192.0.2.0', '192.0.2.255'],     // TEST-NET-1
            '192.168.0.0/16'     => ['192.168.0.0', '192.168.255.255'],
            '198.18.0.0/15'      => ['198.18.0.0', '198.19.255.255'], // Benchmarking
            '198.51.100.0/24'    => ['198.51.100.0', '198.51.100.255'], // TEST-NET-2
            '203.0.113.0/24'     => ['203.0.113.0', '203.0.113.255'], // TEST-NET-3
            '224.0.0.0/4'        => ['224.0.0.0', '239.255.255.255'], // Multicast
            '240.0.0.0/4'        => ['240.0.0.0', '255.255.255.254'], // Reservado
            '255.255.255.255/32' => ['255.255.255.255', '255.255.255.255'], // Broadcast
        ];

        // Comparación unsigned
        $ipUnsigned = sprintf('%u', $ipLong);
        foreach ($rangosProhibidos as $nombre => [$inicio, $fin]) {
            $inicioUnsigned = sprintf('%u', ip2long($inicio));
            $finUnsigned = sprintf('%u', ip2long($fin));
            if ($ipUnsigned >= $inicioUnsigned && $ipUnsigned <= $finUnsigned) {
                throw new InvalidArgumentException("Dirección IPv4 en rango prohibido ($nombre): $ip");
            }
        }
    }

    /**
     * Valida rangos específicos IPv6 e IPv4-mapped IPv6.
     */
    private function validarIpV6RangosEspecificos(string $ip): void
    {
        $binario = inet_pton($ip);
        if ($binario === false) {
            throw new InvalidArgumentException("Dirección IPv6 inválida: $ip");
        }

        // Detección de IPv4-mapped IPv6 (::ffff:x.x.x.x)
        if (str_starts_with($binario, "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xff\xff")) {
            $ipv4Embebida = inet_ntop(substr($binario, 12));
            $this->validarIpV4RangosEspecificos($ipv4Embebida);
            return;
        }

        // Loopback ::1
        if ($ip === '::1' || $binario === str_repeat("\x00", 15) . "\x01") {
            throw new InvalidArgumentException("Dirección IPv6 loopback prohibida: $ip");
        }

        // Unspecified ::
        if ($binario === str_repeat("\x00", 16)) {
            throw new InvalidArgumentException("Dirección IPv6 no especificada prohibida: $ip");
        }

        // Unique Local Address (ULA fc00::/7) -> primeros 7 bits son 1111110 (0xfc o 0xfd)
        $primerByte = ord($binario[0]);
        if (($primerByte & 0xfe) === 0xfc) {
            throw new InvalidArgumentException("Dirección IPv6 ULA privada prohibida (fc00::/7): $ip");
        }

        // Link-Local unicast (fe80::/10) -> primeros 10 bits son 1111111010
        $segundoByte = ord($binario[1]);
        if ($primerByte === 0xfe && ($segundoByte & 0xc0) === 0x80) {
            throw new InvalidArgumentException("Dirección IPv6 Link-Local prohibida (fe80::/10): $ip");
        }

        // Multicast (ff00::/8)
        if ($primerByte === 0xff) {
            throw new InvalidArgumentException("Dirección IPv6 Multicast prohibida (ff00::/8): $ip");
        }
    }

    /**
     * Ejecuta la petición HTTP con fijación de IP mediante CURLOPT_RESOLVE para impedir DNS Rebinding.
     *
     * @param string $url
     * @param string $host
     * @param int $puerto
     * @param string $ip
     * @return array{codigo: int, headers: array<string, string>, cuerpo: string}
     */
    private function ejecutarPeticionCurl(string $url, string $host, int $puerto, string $ip): array
    {
        $ch = curl_init();

        $headers = [];
        $headerCallback = function ($curl, $headerLine) use (&$headers) {
            $len = strlen($headerLine);
            $partes = explode(':', $headerLine, 2);
            if (count($partes) === 2) {
                $headers[strtolower(trim($partes[0]))] = trim($partes[1]);
            }
            return $len;
        };

        // Pinning de IP: garantiza que cURL se conecte a la IP que ya validamos
        $resolveEntry = ["$host:$puerto:$ip"];

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RESOLVE => $resolveEntry,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_HEADERFUNCTION => $headerCallback,
            CURLOPT_FOLLOWLOCATION => false, // Manejado manualmente para re-inspeccionar SSRF
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_CONEXION_SEGUNDOS,
            CURLOPT_TIMEOUT => $this->timeoutTotal,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'CamargoPMS-iCalSync/1.0 (+https://app.camargo-pms.pe)',
            CURLOPT_HTTPHEADER => [
                'Accept: text/calendar, application/json, text/plain;q=0.9, */*;q=0.8',
            ],
        ]);

        $cuerpo = curl_exec($ch);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            $errno = curl_errno($ch);
            curl_close($ch);
            throw new RuntimeException("Error cURL [$errno] al descargar feed iCal: $error");
        }

        $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $tamano = (int) curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);

        curl_close($ch);

        if ($tamano > $this->maxBytes || strlen((string) $cuerpo) > $this->maxBytes) {
            throw new RuntimeException(
                "El feed descargado excede el tamaño máximo permitido de {$this->maxBytes} bytes (tamaño recibido: $tamano bytes)."
            );
        }

        return [
            'codigo' => $codigo,
            'headers' => $headers,
            'cuerpo' => (string) $cuerpo,
        ];
    }

    /**
     * Resuelve una URL relativa a partir de la URL base y el target de Location.
     */
    private function resolverUrlRelativa(string $baseUrl, string $location): string
    {
        if (parse_url($location, PHP_URL_SCHEME) !== null) {
            return $location;
        }

        $base = parse_url($baseUrl);
        $scheme = $base['scheme'] ?? 'https';
        $host = $base['host'] ?? '';
        $port = isset($base['port']) && $base['port'] !== 443 ? ':' . $base['port'] : '';

        if (str_starts_with($location, '/')) {
            return "$scheme://$host$port$location";
        }

        $path = $base['path'] ?? '/';
        $dir = rtrim(dirname($path), '/\\');
        return "$scheme://$host$port$dir/$location";
    }
}
