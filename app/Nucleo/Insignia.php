<?php

declare(strict_types=1);

namespace CamargoPMS\Nucleo;

/**
 * Generador y resolutor semántico de Badges y Chips de Alina (UI-2A / D-071).
 *
 * Estandariza la representación visual de estados, categorías y atributos en Camargo PMS:
 * - Variants of badge de Alina para estados compactos (tablas, listados, contadores).
 * - Variants of chip de Alina para categorías, atributos y clasificaciones.
 * - Iconografía exclusiva con Font Awesome 6.
 * - Prohibición estricta de bordes dotted y dashed.
 */
final class Insignia
{
    /**
     * Mapa semántico oficial de estados del PMS.
     */
    private const MAPA_ESTADOS = [
        // Estados operacionales de Disponibilidad e Inventario
        'DISPONIBLE' => [
            'variante' => 'success',
            'icono' => 'fa-solid fa-check',
            'texto' => 'Disponible',
        ],
        'BLOQUEADO' => [
            'variante' => 'danger',
            'icono' => 'fa-solid fa-lock',
            'texto' => 'Bloqueado',
        ],
        'OCUPADO' => [
            'variante' => 'danger',
            'icono' => 'fa-solid fa-lock',
            'texto' => 'Ocupado',
        ],
        'MANTENIMIENTO' => [
            'variante' => 'warning',
            'icono' => 'fa-solid fa-wrench',
            'texto' => 'Mantenimiento',
        ],
        'LIBERADO' => [
            'variante' => 'secondary',
            'icono' => 'fa-solid fa-check',
            'texto' => 'Liberado',
        ],

        // Estados del ciclo de vida de Reservas (D-070)
        'PENDIENTE' => [
            'variante' => 'warning',
            'icono' => 'fa-solid fa-clock',
            'texto' => 'PENDIENTE',
        ],
        'CONFIRMADA' => [
            'variante' => 'success',
            'icono' => 'fa-solid fa-check',
            'texto' => 'CONFIRMADA',
        ],
        'CANCELADA' => [
            'variante' => 'danger',
            'icono' => 'fa-solid fa-xmark',
            'texto' => 'CANCELADA',
        ],
        'EXPIRADA' => [
            'variante' => 'secondary',
            'icono' => 'fa-solid fa-hourglass-half',
            'texto' => 'EXPIRADA',
        ],

        // Estados de entidades maestras (Propiedades, Unidades, Usuarios, Roles)
        'ACTIVO' => [
            'variante' => 'success',
            'icono' => 'fa-solid fa-circle-check',
            'texto' => 'Activo',
        ],
        'INACTIVO' => [
            'variante' => 'secondary',
            'icono' => 'fa-solid fa-circle-xmark',
            'texto' => 'Inactivo',
        ],
        'SUSPENDIDO' => [
            'variante' => 'danger',
            'icono' => 'fa-solid fa-ban',
            'texto' => 'Suspendido',
        ],
    ];

    /**
     * Genera el HTML de un Badge Alina (Variants of badge).
     *
     * @param string $texto Texto visible del badge.
     * @param string $variante Variante de color Alina ('primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark').
     * @param string|null $icono Clase Font Awesome opcional (ej. 'fa-solid fa-check').
     * @param string $clasesExtra Clases CSS adicionales permitidas (ej. 'f-s-12').
     * @return string HTML seguro.
     */
    public static function badge(
        string $texto,
        string $variante = 'secondary',
        ?string $icono = null,
        string $clasesExtra = ''
    ): string {
        $claseVariante = self::resolverClaseVariante($variante, 'badge');
        $clases = trim("badge {$claseVariante} {$clasesExtra}");
        $iconoHtml = $icono ? '<i class="' . Ayudante::escapar($icono) . ' me-1"></i>' : '';

        return sprintf(
            '<span class="%s">%s%s</span>',
            Ayudante::escapar($clases),
            $iconoHtml,
            Ayudante::escapar($texto)
        );
    }

    /**
     * Genera el HTML de un Chip Alina (Variants of chip).
     *
     * @param string $texto Texto visible del chip.
     * @param string $variante Variante de color Alina ('primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark').
     * @param string|null $icono Clase Font Awesome opcional.
     * @param string $clasesExtra Clases CSS adicionales permitidas.
     * @return string HTML seguro.
     */
    public static function chip(
        string $texto,
        string $variante = 'primary',
        ?string $icono = null,
        string $clasesExtra = ''
    ): string {
        $claseVariante = self::resolverClaseVariante($variante, 'chip');
        $clases = trim("chip {$claseVariante} {$clasesExtra}");
        $iconoHtml = $icono ? '<i class="' . Ayudante::escapar($icono) . ' me-1"></i>' : '';

        return sprintf(
            '<span class="%s">%s%s</span>',
            Ayudante::escapar($clases),
            $iconoHtml,
            Ayudante::escapar($texto)
        );
    }

    /**
     * Resuelve y renderiza automáticamente la insignia de un estado del sistema.
     *
     * @param string $estado Código del estado (ej. 'CONFIRMADA', 'ACTIVO', 'DISPONIBLE').
     * @param bool $comoChip Si es true genera Chip; si es false genera Badge (por defecto).
     * @param string $clasesExtra Clases CSS complementarias.
     * @return string HTML seguro.
     */
    public static function estado(string $estado, bool $comoChip = false, string $clasesExtra = ''): string
    {
        $estadoNorm = strtoupper(trim($estado));
        $config = self::MAPA_ESTADOS[$estadoNorm] ?? [
            'variante' => 'secondary',
            'icono' => null,
            'texto' => $estado,
        ];

        return $comoChip
            ? self::chip($config['texto'], $config['variante'], $config['icono'], $clasesExtra)
            : self::badge($config['texto'], $config['variante'], $config['icono'], $clasesExtra);
    }

    /**
     * Obtiene la variante de color Alina para un estado.
     */
    public static function obtenerVarianteEstado(string $estado): string
    {
        $estadoNorm = strtoupper(trim($estado));
        return self::MAPA_ESTADOS[$estadoNorm]['variante'] ?? 'secondary';
    }

    /**
     * Obtiene el icono Font Awesome asociado a un estado.
     */
    public static function obtenerIconoEstado(string $estado): ?string
    {
        $estadoNorm = strtoupper(trim($estado));
        return self::MAPA_ESTADOS[$estadoNorm]['icono'] ?? null;
    }

    /**
     * Obtiene el texto formal de visualización para un estado.
     */
    public static function obtenerTextoEstado(string $estado): string
    {
        $estadoNorm = strtoupper(trim($estado));
        return self::MAPA_ESTADOS[$estadoNorm]['texto'] ?? $estado;
    }

    /**
     * Resuelve la clase CSS correspondiente a la variante Alina.
     */
    private static function resolverClaseVariante(string $variante, string $tipo): string
    {
        return match ($variante) {
            'primary' => 'bg-light-primary',
            'secondary' => 'bg-light-secondary',
            'success' => 'bg-light-success',
            'danger' => 'bg-light-danger',
            'warning' => 'bg-light-warning',
            'info' => 'bg-light-info',
            'light' => 'bg-light text-dark',
            'dark' => 'bg-dark text-white',
            default => 'bg-light-secondary',
        };
    }
}
