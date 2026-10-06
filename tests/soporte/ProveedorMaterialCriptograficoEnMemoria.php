<?php

declare(strict_types=1);

namespace CamargoPMS\Tests\Soporte;

use CamargoPMS\Servicios\CPE\Firma\MaterialCriptograficoCpe;
use CamargoPMS\Servicios\CPE\Firma\ProveedorMaterialCriptograficoInterfaz;

/**
 * Proveedor en memoria para inyectar material criptográfico en suites de pruebas.
 */
final class ProveedorMaterialCriptograficoEnMemoria implements ProveedorMaterialCriptograficoInterfaz
{
    public function __construct(
        private MaterialCriptograficoCpe $material
    ) {
    }

    public function obtenerMaterial(): MaterialCriptograficoCpe
    {
        return $this->material;
    }
}
