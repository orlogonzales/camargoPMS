<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

use InvalidArgumentException;

/**
 * Modelo de dominio puro que representa un Parámetro de Configuración Funcional del Sistema.
 *
 * Principios vinculantes:
 * - CONFIGURACIÓN != ENTORNO: No almacena secretos técnicos ni credenciales de infraestructura.
 * - CONTRATO DE TIPADO: El tipo gobierna la conversión, validación y persistencia canónica.
 * - VALOR PREDETERMINADO: Permite reversión o restauración segura sin mutar la definición.
 */
class ConfiguracionParametro
{
    private ?int $id;
    private string $clave;
    private string $grupo;
    private string $nombre;
    private ?string $descripcion;
    private TipoConfiguracion $tipo;
    private ?string $valor;
    private ?string $valorPredeterminado;
    private bool $editable;
    private bool $esSensible;
    private int $orden;
    private string $estado;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    /**
     * @param int|null $id
     * @param string $clave Identificador técnico único (ej. 'sistema.nombre')
     * @param string $grupo Agrupador funcional (ej. 'GENERAL', 'LOCALIZACION', 'OPERACION')
     * @param string $nombre Etiqueta visible
     * @param string|null $descripcion Explicación de uso funcional
     * @param TipoConfiguracion|string $tipo Tipo de dato soportado
     * @param string|null $valor Valor persistido actualmente en formato canónico
     * @param string|null $valorPredeterminado Valor por defecto de fábrica
     * @param bool $editable Si permite mutación por parte del administrador
     * @param bool $esSensible Si debe ofuscarse o protegerse en salidas generales
     * @param int $orden Posición relativa de presentación en la interfaz
     * @param string $estado 'ACTIVO' o 'INACTIVO'
     * @param string|null $creadoEn
     * @param string|null $actualizadoEn
     */
    public function __construct(
        ?int $id,
        string $clave,
        string $grupo,
        string $nombre,
        ?string $descripcion = null,
        TipoConfiguracion|string $tipo = TipoConfiguracion::TEXTO,
        ?string $valor = null,
        ?string $valorPredeterminado = null,
        bool $editable = true,
        bool $esSensible = false,
        int $orden = 1,
        string $estado = 'ACTIVO',
        ?string $creadoEn = null,
        ?string $actualizadoEn = null
    ) {
        $this->id = $id;
        $this->clave = trim(strtolower($clave));
        $this->grupo = trim(strtoupper($grupo));
        $this->nombre = trim($nombre);
        $this->descripcion = $descripcion !== null ? trim($descripcion) : null;
        $this->tipo = is_string($tipo) ? TipoConfiguracion::from(strtoupper(trim($tipo))) : $tipo;
        $this->valor = $valor !== null ? (string) $valor : null;
        $this->valorPredeterminado = $valorPredeterminado !== null ? (string) $valorPredeterminado : null;
        $this->editable = $editable;
        $this->esSensible = $esSensible;
        $this->orden = $orden;
        $this->estado = strtoupper(trim($estado)) === 'INACTIVO' ? 'INACTIVO' : 'ACTIVO';
        $this->creadoEn = $creadoEn;
        $this->actualizadoEn = $actualizadoEn;
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function obtenerGrupo(): string
    {
        return $this->grupo;
    }

    public function obtenerNombre(): string
    {
        return $this->nombre;
    }

    public function obtenerDescripcion(): ?string
    {
        return $this->descripcion;
    }

    public function obtenerTipo(): TipoConfiguracion
    {
        return $this->tipo;
    }

    public function obtenerValor(): ?string
    {
        return $this->valor;
    }

    public function obtenerValorPredeterminado(): ?string
    {
        return $this->valorPredeterminado;
    }

    /**
     * Devuelve el valor casteado a su tipo nativo de PHP.
     */
    public function obtenerValorTipado(): mixed
    {
        return $this->tipo->castear($this->valor);
    }

    /**
     * Devuelve el valor predeterminado casteado a su tipo nativo de PHP.
     */
    public function obtenerValorPredeterminadoTipado(): mixed
    {
        return $this->tipo->castear($this->valorPredeterminado);
    }

    public function esEditable(): bool
    {
        return $this->editable;
    }

    public function esSensible(): bool
    {
        return $this->esSensible;
    }

    public function obtenerOrden(): int
    {
        return $this->orden;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function estaActivo(): bool
    {
        return $this->estado === 'ACTIVO';
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function obtenerActualizadoEn(): ?string
    {
        return $this->actualizadoEn;
    }

    /**
     * Hidrata una entidad ConfiguracionParametro desde un arreglo asociativo de base de datos.
     *
     * @param array<string, mixed> $datos
     * @return self
     */
    public static function hidratar(array $datos): self
    {
        $tipoStr = (string) ($datos['tipo'] ?? 'TEXTO');
        $tipo = TipoConfiguracion::tryFrom(strtoupper(trim($tipoStr))) ?? TipoConfiguracion::TEXTO;

        return new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (string) ($datos['clave'] ?? ''),
            (string) ($datos['grupo'] ?? 'GENERAL'),
            (string) ($datos['nombre'] ?? ''),
            isset($datos['descripcion']) && $datos['descripcion'] !== null ? (string) $datos['descripcion'] : null,
            $tipo,
            isset($datos['valor']) && $datos['valor'] !== null ? (string) $datos['valor'] : null,
            isset($datos['valor_predeterminado']) && $datos['valor_predeterminado'] !== null ? (string) $datos['valor_predeterminado'] : null,
            !empty($datos['editable']),
            !empty($datos['es_sensible']),
            isset($datos['orden']) ? (int) $datos['orden'] : 1,
            (string) ($datos['estado'] ?? 'ACTIVO'),
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            isset($datos['actualizado_en']) ? (string) $datos['actualizado_en'] : null
        );
    }

    /**
     * Alias de hidratar().
     *
     * @param array<string, mixed> $datos
     * @return self
     */
    public static function desdeArreglo(array $datos): self
    {
        return self::hidratar($datos);
    }

    /**
     * Convierte la entidad a un arreglo asociativo para respuestas JSON o vistas.
     *
     * @return array<string, mixed>
     */
    public function haciaArreglo(): array
    {
        return [
            'id' => $this->id,
            'clave' => $this->clave,
            'grupo' => $this->grupo,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'tipo' => $this->tipo->value,
            'valor' => $this->valor,
            'valor_tipado' => $this->obtenerValorTipado(),
            'valor_predeterminado' => $this->valorPredeterminado,
            'valor_predeterminado_tipado' => $this->obtenerValorPredeterminadoTipado(),
            'editable' => $this->editable,
            'es_sensible' => $this->esSensible,
            'orden' => $this->orden,
            'estado' => $this->estado,
            'activo' => $this->estaActivo(),
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }

    /**
     * Alias de haciaArreglo().
     *
     * @return array<string, mixed>
     */
    public function aArreglo(): array
    {
        return $this->haciaArreglo();
    }

    /**
     * Alias de haciaArreglo() para interoperabilidad uniforme.
     *
     * @return array<string, mixed>
     */
    public function aArray(): array
    {
        return $this->haciaArreglo();
    }
}
