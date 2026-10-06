<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios\CPE\Firma;

use CamargoPMS\Excepciones\FirmaCpeExcepcion;

/**
 * Contrato de abstracción para proveedores de material criptográfico (certificados y claves privadas).
 *
 * Desacopla la lógica de firma del origen de las credenciales (archivos en disco, variables de entorno,
 * HSM o almacenes seguros en memoria).
 */
interface ProveedorMaterialCriptograficoInterfaz
{
    /**
     * Obtiene el material criptográfico necesario para la firma digital de un CPE.
     *
     * @return MaterialCriptograficoCpe
     * @throws FirmaCpeExcepcion Si ocurre un error al cargar o autenticar el material criptográfico.
     */
    public function obtenerMaterial(): MaterialCriptograficoCpe;
}
