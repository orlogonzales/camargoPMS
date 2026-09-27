<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\Arrendamiento;
use PDO;

/**
 * Repositorio para la gestión y persistencia de contratos de arrendamiento.
 */
class ArrendamientoRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    public function crear(Arrendamiento $arrendamiento): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO arrendamientos (
                codigo, unidad_id, arrendamiento_anterior_id, fecha_inicio, fecha_fin, dia_vencimiento,
                renta_mensual, deposito_garantia, monto_primer_periodo, es_primer_mes_prorrateado,
                moneda_codigo, estado, motivo_rescision, rescidido_en, rescidido_por_actor_id,
                notas_adicionales, creado_por_actor_id, creado_en
            ) VALUES (
                :codigo, :unidad_id, :arrendamiento_anterior_id, :fecha_inicio, :fecha_fin, :dia_vencimiento,
                :renta_mensual, :deposito_garantia, :monto_primer_periodo, :es_primer_mes_prorrateado,
                :moneda_codigo, :estado, :motivo_rescision, :rescidido_en, :rescidido_por_actor_id,
                :notas_adicionales, :creado_por_actor_id, NOW()
            )'
        );

        $stmt->execute([
            'codigo' => $arrendamiento->obtenerCodigo(),
            'unidad_id' => $arrendamiento->obtenerUnidadId(),
            'arrendamiento_anterior_id' => $arrendamiento->obtenerArrendamientoAnteriorId(),
            'fecha_inicio' => $arrendamiento->obtenerFechaInicio(),
            'fecha_fin' => $arrendamiento->obtenerFechaFin(),
            'dia_vencimiento' => $arrendamiento->obtenerDiaVencimiento(),
            'renta_mensual' => $arrendamiento->obtenerRentaMensual(),
            'deposito_garantia' => $arrendamiento->obtenerDepositoGarantia(),
            'monto_primer_periodo' => $arrendamiento->obtenerMontoPrimerPeriodo(),
            'es_primer_mes_prorrateado' => $arrendamiento->esPrimerMesProrrateado() ? 1 : 0,
            'moneda_codigo' => $arrendamiento->obtenerMonedaCodigo(),
            'estado' => $arrendamiento->obtenerEstado(),
            'motivo_rescision' => $arrendamiento->obtenerMotivoRescision(),
            'rescidido_en' => $arrendamiento->obtenerRescididoEn(),
            'rescidido_por_actor_id' => $arrendamiento->obtenerRescididoPorActorId(),
            'notas_adicionales' => $arrendamiento->obtenerNotasAdicionales(),
            'creado_por_actor_id' => $arrendamiento->obtenerCreadoPorActorId(),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function obtenerPorId(int $id, bool $bloquear = false): ?Arrendamiento
    {
        $sql = 'SELECT 
                    a.*,
                    u.codigo AS unidad_numero,
                    u.nombre AS unidad_nombre,
                    p.nombre AS propiedad_nombre,
                    TRIM(CONCAT(per.nombres, " ", per.apellido_paterno, " ", COALESCE(per.apellido_materno, ""))) AS titular_nombre_completo,
                    COALESCE((SELECT pd.numero_documento FROM personas_documentos pd WHERE pd.persona_id = per.id LIMIT 1), "") AS titular_numero_documento,
                    per.id AS titular_persona_id,
                    cf.codigo AS folio_codigo,
                    cf.id AS folio_id
                FROM arrendamientos a
                INNER JOIN unidades u ON u.id = a.unidad_id
                INNER JOIN propiedades p ON p.id = u.propiedad_id
                LEFT JOIN arrendamiento_personas ap ON ap.arrendamiento_id = a.id AND ap.tipo_relacion = "TITULAR"
                LEFT JOIN personas per ON per.id = ap.persona_id
                LEFT JOIN cuentas_folios cf ON cf.arrendamiento_id = a.id
                WHERE a.id = :id
                LIMIT 1' . ($bloquear ? ' FOR UPDATE' : '');

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? Arrendamiento::desdeArreglo($fila) : null;
    }

    public function obtenerPorCodigo(string $codigo, bool $bloquear = false): ?Arrendamiento
    {
        $sql = 'SELECT 
                    a.*,
                    u.codigo AS unidad_numero,
                    u.nombre AS unidad_nombre,
                    p.nombre AS propiedad_nombre,
                    TRIM(CONCAT(per.nombres, " ", per.apellido_paterno, " ", COALESCE(per.apellido_materno, ""))) AS titular_nombre_completo,
                    COALESCE((SELECT pd.numero_documento FROM personas_documentos pd WHERE pd.persona_id = per.id LIMIT 1), "") AS titular_numero_documento,
                    per.id AS titular_persona_id,
                    cf.codigo AS folio_codigo,
                    cf.id AS folio_id
                FROM arrendamientos a
                INNER JOIN unidades u ON u.id = a.unidad_id
                INNER JOIN propiedades p ON p.id = u.propiedad_id
                LEFT JOIN arrendamiento_personas ap ON ap.arrendamiento_id = a.id AND ap.tipo_relacion = "TITULAR"
                LEFT JOIN personas per ON per.id = ap.persona_id
                LEFT JOIN cuentas_folios cf ON cf.arrendamiento_id = a.id
                WHERE a.codigo = :codigo
                LIMIT 1' . ($bloquear ? ' FOR UPDATE' : '');

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['codigo' => $codigo]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? Arrendamiento::desdeArreglo($fila) : null;
    }

    public function actualizarEstado(
        int $id,
        string $nuevoEstado,
        ?string $motivoRescision = null,
        ?int $actorId = null
    ): void {
        if ($nuevoEstado === 'RESCINDIDO') {
            $sql = 'UPDATE arrendamientos 
                    SET estado = :estado,
                        motivo_rescision = :motivo,
                        rescidido_en = NOW(),
                        rescidido_por_actor_id = :actor_id,
                        actualizado_en = NOW()
                    WHERE id = :id';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'estado' => $nuevoEstado,
                'motivo' => $motivoRescision,
                'actor_id' => $actorId,
                'id' => $id,
            ]);
        } elseif ($nuevoEstado === 'CANCELADO') {
            $sql = 'UPDATE arrendamientos 
                    SET estado = :estado,
                        motivo_rescision = :motivo,
                        actualizado_en = NOW()
                    WHERE id = :id';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'estado' => $nuevoEstado,
                'motivo' => $motivoRescision,
                'id' => $id,
            ]);
        } else {
            $sql = 'UPDATE arrendamientos 
                    SET estado = :estado,
                        actualizado_en = NOW()
                    WHERE id = :id';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'estado' => $nuevoEstado,
                'id' => $id,
            ]);
        }
    }

    public function actualizarFechaFin(int $id, string $nuevaFechaFin): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE arrendamientos SET fecha_fin = :fecha_fin, actualizado_en = NOW() WHERE id = :id'
        );
        $stmt->execute([
            'fecha_fin' => $nuevaFechaFin,
            'id' => $id,
        ]);
    }

    /**
     * @param array<string, mixed> $filtros
     * @return array<array<string, mixed>>
     */
    public function listar(array $filtros = []): array
    {
        $sql = 'SELECT 
                    a.*,
                    u.codigo AS unidad_numero,
                    u.nombre AS unidad_nombre,
                    p.nombre AS propiedad_nombre,
                    TRIM(CONCAT(per.nombres, " ", per.apellido_paterno, " ", COALESCE(per.apellido_materno, ""))) AS titular_nombre_completo,
                    COALESCE((SELECT pd.numero_documento FROM personas_documentos pd WHERE pd.persona_id = per.id LIMIT 1), "") AS titular_numero_documento,
                    per.id AS titular_persona_id,
                    cf.codigo AS folio_codigo,
                    cf.id AS folio_id,
                    cf.estado AS folio_estado,
                    ag.estado AS garantia_estado,
                    ag.monto_pactado AS garantia_pactada,
                    ag.monto_retenido_actual AS garantia_retenida
                FROM arrendamientos a
                INNER JOIN unidades u ON u.id = a.unidad_id
                INNER JOIN propiedades p ON p.id = u.propiedad_id
                LEFT JOIN arrendamiento_personas ap ON ap.arrendamiento_id = a.id AND ap.tipo_relacion = "TITULAR"
                LEFT JOIN personas per ON per.id = ap.persona_id
                LEFT JOIN cuentas_folios cf ON cf.arrendamiento_id = a.id
                LEFT JOIN arrendamiento_garantias ag ON ag.arrendamiento_id = a.id
                WHERE 1=1';

        $params = [];

        if (!empty($filtros['estado'])) {
            $sql .= ' AND a.estado = :estado';
            $params['estado'] = $filtros['estado'];
        }

        if (!empty($filtros['unidad_id'])) {
            $sql .= ' AND a.unidad_id = :unidad_id';
            $params['unidad_id'] = (int) $filtros['unidad_id'];
        }

        if (!empty($filtros['propiedad_id'])) {
            $sql .= ' AND u.propiedad_id = :propiedad_id';
            $params['propiedad_id'] = (int) $filtros['propiedad_id'];
        }

        if (!empty($filtros['q'])) {
            $sql .= ' AND (a.codigo LIKE :q OR u.numero LIKE :q OR per.nombres LIKE :q OR per.apellido_paterno LIKE :q)';
            $params['q'] = '%' . $filtros['q'] . '%';
        }

        $sql .= ' ORDER BY a.id DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function generarSiguienteCodigo(): string
    {
        $prefijo = 'ARR-' . date('Ymd') . '-';
        $stmt = $this->pdo->prepare(
            'SELECT codigo FROM arrendamientos WHERE codigo LIKE :prefijo ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['prefijo' => $prefijo . '%']);
        $ultimo = $stmt->fetchColumn();

        if ($ultimo && preg_match('/-(\d{4})$/', (string) $ultimo, $m)) {
            $siguiente = (int) $m[1] + 1;
        } else {
            $siguiente = 1;
        }

        return $prefijo . str_pad((string) $siguiente, 4, '0', STR_PAD_LEFT);
    }
}
