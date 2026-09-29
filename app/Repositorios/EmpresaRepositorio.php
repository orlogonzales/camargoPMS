<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\Empresa;
use PDO;

/**
 * Repositorio de persistencia PDO para el Maestro de Empresas y Emisores Legales (EMPRESA-1).
 */
class EmpresaRepositorio
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? \CamargoPMS\Nucleo\BaseDatos::conexion();
    }

    public function obtenerPdo(): PDO
    {
        return $this->pdo;
    }

    public function buscarPorId(int $id): ?Empresa
    {
        $sql = 'SELECT e.*,
                       td.codigo AS tipo_doc_codigo,
                       p.nombre AS pais_nombre,
                       (SELECT COUNT(*) FROM propiedades prop WHERE prop.empresa_id = e.id) AS total_propiedades
                FROM empresas e
                JOIN tipos_documento td ON td.id = e.tipo_documento_id
                JOIN paises p ON p.id = e.pais_id
                WHERE e.id = :id
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            return null;
        }

        return $this->mapearFila($fila);
    }

    public function buscarPorCodigo(string $codigo): ?Empresa
    {
        $sql = 'SELECT e.*,
                       td.codigo AS tipo_doc_codigo,
                       p.nombre AS pais_nombre,
                       (SELECT COUNT(*) FROM propiedades prop WHERE prop.empresa_id = e.id) AS total_propiedades
                FROM empresas e
                JOIN tipos_documento td ON td.id = e.tipo_documento_id
                JOIN paises p ON p.id = e.pais_id
                WHERE e.codigo = :codigo
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['codigo' => strtoupper(trim($codigo))]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            return null;
        }

        return $this->mapearFila($fila);
    }

    public function buscarPorNumeroDocumento(string $numeroDocumento): ?Empresa
    {
        $sql = 'SELECT e.*,
                       td.codigo AS tipo_doc_codigo,
                       p.nombre AS pais_nombre,
                       (SELECT COUNT(*) FROM propiedades prop WHERE prop.empresa_id = e.id) AS total_propiedades
                FROM empresas e
                JOIN tipos_documento td ON td.id = e.tipo_documento_id
                JOIN paises p ON p.id = e.pais_id
                WHERE e.numero_documento = :num
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['num' => trim($numeroDocumento)]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            return null;
        }

        return $this->mapearFila($fila);
    }

    public function buscarPrincipal(): ?Empresa
    {
        $sql = 'SELECT e.*,
                       td.codigo AS tipo_doc_codigo,
                       p.nombre AS pais_nombre,
                       (SELECT COUNT(*) FROM propiedades prop WHERE prop.empresa_id = e.id) AS total_propiedades
                FROM empresas e
                JOIN tipos_documento td ON td.id = e.tipo_documento_id
                JOIN paises p ON p.id = e.pais_id
                WHERE e.es_principal = 1 AND e.estado = "ACTIVO"
                ORDER BY e.id ASC
                LIMIT 1';

        $stmt = $this->pdo->query($sql);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($fila) {
            return $this->mapearFila($fila);
        }

        // Si no hay principal explícita, seleccionar la primera empresa activa
        $sqlFallback = 'SELECT e.*,
                               td.codigo AS tipo_doc_codigo,
                               p.nombre AS pais_nombre,
                               (SELECT COUNT(*) FROM propiedades prop WHERE prop.empresa_id = e.id) AS total_propiedades
                        FROM empresas e
                        JOIN tipos_documento td ON td.id = e.tipo_documento_id
                        JOIN paises p ON p.id = e.pais_id
                        WHERE e.estado = "ACTIVO"
                        ORDER BY e.id ASC
                        LIMIT 1';

        $stmtF = $this->pdo->query($sqlFallback);
        $filaF = $stmtF->fetch(PDO::FETCH_ASSOC);

        return $filaF ? $this->mapearFila($filaF) : null;
    }

    /**
     * @param array<string, mixed> $filtros
     * @return array<int, Empresa>
     */
    public function listar(array $filtros = [], int $limite = 50, int $offset = 0): array
    {
        $condiciones = [];
        $params = [];

        if (!empty($filtros['estado'])) {
            $condiciones[] = 'e.estado = :estado';
            $params['estado'] = $filtros['estado'];
        }

        if (!empty($filtros['q'])) {
            $condiciones[] = '(e.razon_social LIKE :q OR e.nombre_comercial LIKE :q OR e.numero_documento LIKE :q OR e.codigo LIKE :q)';
            $params['q'] = '%' . trim((string) $filtros['q']) . '%';
        }

        if (isset($filtros['es_principal']) && $filtros['es_principal'] !== '') {
            $condiciones[] = 'e.es_principal = :es_principal';
            $params['es_principal'] = (int) $filtros['es_principal'];
        }

        $where = !empty($condiciones) ? 'WHERE ' . implode(' AND ', $condiciones) : '';

        $sql = "SELECT e.*,
                       td.codigo AS tipo_doc_codigo,
                       p.nombre AS pais_nombre,
                       (SELECT COUNT(*) FROM propiedades prop WHERE prop.empresa_id = e.id) AS total_propiedades
                FROM empresas e
                JOIN tipos_documento td ON td.id = e.tipo_documento_id
                JOIN paises p ON p.id = e.pais_id
                {$where}
                ORDER BY e.es_principal DESC, e.razon_social ASC
                LIMIT {$limite} OFFSET {$offset}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $empresas = [];
        foreach ($filas as $fila) {
            $empresas[] = $this->mapearFila($fila);
        }

        return $empresas;
    }

    /**
     * @param array<string, mixed> $filtros
     */
    public function contar(array $filtros = []): int
    {
        $condiciones = [];
        $params = [];

        if (!empty($filtros['estado'])) {
            $condiciones[] = 'e.estado = :estado';
            $params['estado'] = $filtros['estado'];
        }

        if (!empty($filtros['q'])) {
            $condiciones[] = '(e.razon_social LIKE :q OR e.nombre_comercial LIKE :q OR e.numero_documento LIKE :q OR e.codigo LIKE :q)';
            $params['q'] = '%' . trim((string) $filtros['q']) . '%';
        }

        $where = !empty($condiciones) ? 'WHERE ' . implode(' AND ', $condiciones) : '';

        $sql = "SELECT COUNT(*) FROM empresas e {$where}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function crear(Empresa $empresa): int
    {
        $sql = 'INSERT INTO empresas (
                    codigo, tipo_documento_id, numero_documento, razon_social,
                    nombre_comercial, direccion_fiscal, pais_id, departamento,
                    provincia, distrito, ubigeo, telefono, email, sitio_web,
                    logo_url, representante_persona_id, representante_cargo,
                    representante_poder_partida, es_principal, estado, observaciones
                ) VALUES (
                    :codigo, :tipo_doc_id, :num_doc, :razon_social,
                    :nombre_comercial, :direccion_fiscal, :pais_id, :departamento,
                    :provincia, :distrito, :ubigeo, :telefono, :email, :sitio_web,
                    :logo_url, :rep_persona_id, :rep_cargo,
                    :rep_partida, :es_principal, :estado, :observaciones
                )';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'codigo' => $empresa->obtenerCodigo(),
            'tipo_doc_id' => $empresa->obtenerTipoDocumentoId(),
            'num_doc' => $empresa->obtenerNumeroDocumento(),
            'razon_social' => $empresa->obtenerRazonSocial(),
            'nombre_comercial' => $empresa->obtenerNombreComercial(),
            'direccion_fiscal' => $empresa->obtenerDireccionFiscal(),
            'pais_id' => $empresa->obtenerPaisId(),
            'departamento' => $empresa->obtenerDepartamento(),
            'provincia' => $empresa->obtenerProvincia(),
            'distrito' => $empresa->obtenerDistrito(),
            'ubigeo' => $empresa->obtenerUbigeo(),
            'telefono' => $empresa->obtenerTelefono(),
            'email' => $empresa->obtenerEmail(),
            'sitio_web' => $empresa->obtenerSitioWeb(),
            'logo_url' => $empresa->obtenerLogoUrl(),
            'rep_persona_id' => $empresa->obtenerRepresentantePersonaId(),
            'rep_cargo' => $empresa->obtenerRepresentanteCargo(),
            'rep_partida' => $empresa->obtenerRepresentantePoderPartida(),
            'es_principal' => $empresa->esPrincipal() ? 1 : 0,
            'estado' => $empresa->obtenerEstado(),
            'observaciones' => $empresa->obtenerObservaciones(),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function actualizar(Empresa $empresa): bool
    {
        $sql = 'UPDATE empresas SET
                    codigo = :codigo,
                    tipo_documento_id = :tipo_doc_id,
                    numero_documento = :num_doc,
                    razon_social = :razon_social,
                    nombre_comercial = :nombre_comercial,
                    direccion_fiscal = :direccion_fiscal,
                    pais_id = :pais_id,
                    departamento = :departamento,
                    provincia = :provincia,
                    distrito = :distrito,
                    ubigeo = :ubigeo,
                    telefono = :telefono,
                    email = :email,
                    sitio_web = :sitio_web,
                    logo_url = :logo_url,
                    representante_persona_id = :rep_persona_id,
                    representante_cargo = :rep_cargo,
                    representante_poder_partida = :rep_partida,
                    es_principal = :es_principal,
                    estado = :estado,
                    observaciones = :observaciones
                WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'id' => $empresa->obtenerId(),
            'codigo' => $empresa->obtenerCodigo(),
            'tipo_doc_id' => $empresa->obtenerTipoDocumentoId(),
            'num_doc' => $empresa->obtenerNumeroDocumento(),
            'razon_social' => $empresa->obtenerRazonSocial(),
            'nombre_comercial' => $empresa->obtenerNombreComercial(),
            'direccion_fiscal' => $empresa->obtenerDireccionFiscal(),
            'pais_id' => $empresa->obtenerPaisId(),
            'departamento' => $empresa->obtenerDepartamento(),
            'provincia' => $empresa->obtenerProvincia(),
            'distrito' => $empresa->obtenerDistrito(),
            'ubigeo' => $empresa->obtenerUbigeo(),
            'telefono' => $empresa->obtenerTelefono(),
            'email' => $empresa->obtenerEmail(),
            'sitio_web' => $empresa->obtenerSitioWeb(),
            'logo_url' => $empresa->obtenerLogoUrl(),
            'rep_persona_id' => $empresa->obtenerRepresentantePersonaId(),
            'rep_cargo' => $empresa->obtenerRepresentanteCargo(),
            'rep_partida' => $empresa->obtenerRepresentantePoderPartida(),
            'es_principal' => $empresa->esPrincipal() ? 1 : 0,
            'estado' => $empresa->obtenerEstado(),
            'observaciones' => $empresa->obtenerObservaciones(),
        ]);
    }

    public function cambiarEstado(int $id, string $nuevoEstado): bool
    {
        $stmt = $this->pdo->prepare('UPDATE empresas SET estado = :estado WHERE id = :id');
        return $stmt->execute(['id' => $id, 'estado' => $nuevoEstado]);
    }

    public function desmarcarPrincipalesExcepto(?int $exceptoId = null): void
    {
        if ($exceptoId !== null) {
            $stmt = $this->pdo->prepare('UPDATE empresas SET es_principal = 0 WHERE id <> :id');
            $stmt->execute(['id' => $exceptoId]);
        } else {
            $this->pdo->exec('UPDATE empresas SET es_principal = 0');
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function obtenerPropiedadesVinculadas(int $empresaId): array
    {
        $sql = 'SELECT p.id, p.codigo, p.nombre, p.direccion, p.estado,
                       (SELECT COUNT(*) FROM unidades u WHERE u.propiedad_id = p.id) AS total_unidades
                FROM propiedades p
                WHERE p.empresa_id = :empresa_id
                ORDER BY p.nombre ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['empresa_id' => $empresaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function obtenerPropiedadesPorEmpresaId(int $empresaId): array
    {
        return $this->obtenerPropiedadesVinculadas($empresaId);
    }

    public function asignarPropiedad(int $empresaId, int $propiedadId): bool
    {
        $stmt = $this->pdo->prepare('UPDATE propiedades SET empresa_id = :empresa_id WHERE id = :propiedad_id');
        return $stmt->execute(['empresa_id' => $empresaId, 'propiedad_id' => $propiedadId]);
    }

    public function desasignarPropiedad(int $propiedadId): bool
    {
        $stmt = $this->pdo->prepare('UPDATE propiedades SET empresa_id = NULL WHERE id = :propiedad_id');
        return $stmt->execute(['propiedad_id' => $propiedadId]);
    }

    public function tieneDependencias(int $empresaId): bool
    {
        // 1. Propiedades vinculadas activas
        $stmtP = $this->pdo->prepare('SELECT COUNT(*) FROM propiedades WHERE empresa_id = :id');
        $stmtP->execute(['id' => $empresaId]);
        if ((int) $stmtP->fetchColumn() > 0) {
            return true;
        }

        return false;
    }

    public function eliminar(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM empresas WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Resuelve nombre y documento principal de una persona vinculada como representante.
     *
     * @return array{nombre_completo: ?string, numero_documento: ?string}
     */
    public function resolverRepresentanteInfo(?int $personaId): array
    {
        if ($personaId === null || $personaId <= 0) {
            return ['nombre_completo' => null, 'numero_documento' => null];
        }

        $sql = 'SELECT p.nombres, p.apellido_paterno, p.apellido_materno,
                       pd.numero_documento
                FROM personas p
                LEFT JOIN personas_documentos pd ON pd.persona_id = p.id AND pd.es_principal = 1 AND pd.estado = "ACTIVO"
                WHERE p.id = :id
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $personaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return ['nombre_completo' => null, 'numero_documento' => null];
        }

        $apellidos = trim(($row['apellido_paterno'] ?? '') . ' ' . ($row['apellido_materno'] ?? ''));
        $nombreCompleto = trim(($row['nombres'] ?? '') . ' ' . $apellidos);

        return [
            'nombre_completo' => $nombreCompleto !== '' ? $nombreCompleto : null,
            'numero_documento' => $row['numero_documento'] ?? null,
        ];
    }

    private function mapearFila(array $f): Empresa
    {
        $repInfo = $this->resolverRepresentanteInfo(isset($f['representante_persona_id']) ? (int) $f['representante_persona_id'] : null);

        $empresa = new Empresa(
            (int) $f['id'],
            (string) $f['codigo'],
            (int) $f['tipo_documento_id'],
            (string) $f['numero_documento'],
            (string) $f['razon_social'],
            $f['nombre_comercial'] ?? null,
            (string) ($f['direccion_fiscal'] ?? ''),
            (int) ($f['pais_id'] ?? 1),
            $f['departamento'] ?? null,
            $f['provincia'] ?? null,
            $f['distrito'] ?? null,
            $f['ubigeo'] ?? null,
            $f['telefono'] ?? null,
            $f['email'] ?? null,
            $f['sitio_web'] ?? null,
            $f['logo_url'] ?? null,
            isset($f['representante_persona_id']) ? (int) $f['representante_persona_id'] : null,
            $f['representante_cargo'] ?? 'Gerente General',
            $f['representante_poder_partida'] ?? null,
            (bool) ($f['es_principal'] ?? false),
            (string) ($f['estado'] ?? Empresa::ESTADO_ACTIVO),
            $f['observaciones'] ?? null,
            $f['creado_en'] ?? null,
            $f['actualizado_en'] ?? null
        );

        $empresa->asignarMetadatos(
            $repInfo['nombre_completo'],
            $repInfo['numero_documento'],
            $f['tipo_doc_codigo'] ?? null,
            $f['pais_nombre'] ?? null,
            (int) ($f['total_propiedades'] ?? 0)
        );

        return $empresa;
    }
}
