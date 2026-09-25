# Base de datos

## Enfoque y Motor Confirmado

- **Motor oficial:** **MySQL Community Server 8.4.3 LTS** (GPL).
- **Engine predeterminado:** `InnoDB` con soporte pleno de transacciones ACID y restricciones de integridad foránea.
- **Codificación:** Charset `utf8mb4` con collation `utf8mb4_0900_ai_ci`.
- **Modo SQL:** Estricto (`ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION`).
- **Persistencia en aplicación:** Acceso centralizado vía PDO nativo (`app/Nucleo/BaseDatos.php`) con opciones estrictas: `ATTR_ERRMODE => ERRMODE_EXCEPTION`, `ATTR_DEFAULT_FETCH_MODE => FETCH_ASSOC`, y `ATTR_EMULATE_PREPARES => false`.

## Esquema Consolidado y Migraciones

La gestión estructural del esquema sigue el principio de doble representación sincronizada:

1. **`SQL/camargo_pms.sql` (Esquema Consolidado Oficial):**
   - Representación íntegra, canónica y versionada del esquema vigente.
   - Permite recrear la base de datos estructuralmente desde cero en entornos limpios.
   - Libre de datos operativos, datos personales, contraseñas o volcados de producción.
   - Debe iniciar con `SET NAMES utf8mb4; SET FOREIGN_KEY_CHECKS = 0;` y finalizar obligatoriamente con `SET FOREIGN_KEY_CHECKS = 1;`.
2. **`SQL/migraciones/` (Evolución Incremental):**
   - Scripts secuenciales numerados deterministas (ej. `001_infraestructura.sql`, `002_identidad_personas.sql`).
   - Registrados y controlados mediante la tabla técnica `migraciones` (`id`, `migracion`, `lote`, `ejecutado_en`).
   - El campo `lote` representa exclusivamente metadatos de trazabilidad y agrupación cronológica por microfase (no asume reversibilidad automática hasta que se implemente una estrategia down/compensatoria formal).
   - Las migraciones ordinarias respetan el orden de dependencias relacionales y no desactivan `FOREIGN_KEY_CHECKS` de forma indiscriminada.
   - Ejecutados exclusivamente vía CLI mediante `php migrar.php`.

**Regla vinculante:** Todo cambio estructural de base de datos debe nacer de una migración versionada y reflejarse simultáneamente en `SQL/camargo_pms.sql`. Nunca se aplican cambios manuales en producción como sustituto de una migración.

## Esquema del Núcleo de Identidad (IDENTIDAD-1 y ajuste evolutivo en 003)

- `paises`: Catálogo normalizado (`id`, `codigo_iso2`, `codigo_iso3`, `nombre`, `nacionalidad`, `activo`). Restricciones UNIQUE en códigos ISO. Semilla base: Perú ('PE', 'PER').
- `tipos_documento`: Catálogo extensible para personas naturales (`id`, `codigo`, `nombre`, `descripcion`, `longitud_exacta`, `longitud_minima`, `longitud_maxima`, `formato_regex`, `activo`). Semillas base: DNI (8 dígitos exactos), Pasaporte y Carné de Extranjería (CE). Sin RUC (reservado a personas jurídicas / fiscalidad).
- `personas`: Maestro de personas naturales (`id` BIGINT, `nombres`, `apellido_paterno`, `apellido_materno`, `fecha_nacimiento`, `pais_nacionalidad_id`, `direccion`, `estado`). Nombres obligatorios; apellidos con nulabilidad para monónimos legales y personas extranjeras (evolución migración 003). Restricción CHECK de estado (`ACTIVO`, `INACTIVO`).
- `personas_documentos`: Colección de documentos (`id` BIGINT, `persona_id`, `tipo_documento_id`, `numero_documento`, `pais_emisor_id`, `pais_emisor_efectivo`, `es_principal`, `fecha_emision`, `fecha_vencimiento`, `estado`). Columna virtual `pais_emisor_efectivo = COALESCE(pais_emisor_id, 0)` con restricción `UNIQUE (tipo_documento_id, pais_emisor_efectivo, numero_documento)` para permitir coincidencia de números entre países distintos y prevenir duplicados dentro de la misma jurisdicción. Columna virtual `uq_persona_principal` para garantizar a lo sumo un principal activo por persona.
- `personas_contactos`: Colección de medios de contacto (`id` BIGINT, `persona_id`, `tipo_contacto`, `valor`, `es_whatsapp`, `es_principal`, `estado`). Columna virtual `uq_contacto_tipo_principal` para garantizar un único principal por tipo y persona. Indicador `es_whatsapp` para vincular WhatsApp a un número telefónico sin duplicación física de registros.

## Esquema del Núcleo de Personal y Colaboradores (PERSONAL-1)

- `cargos`: Catálogo administrable de puestos laborales (`id` INT, `codigo`, `nombre`, `descripcion`, `activo`). Restricción `UNIQUE (codigo)`. Semillas: ADMINISTRADOR, RECEPCIONISTA, RESERVAS, LIMPIEZA, MANTENIMIENTO.
- `colaboradores`: Identidad laboral estable vinculada a personas (`id` BIGINT, `persona_id`, `codigo`, `estado`, `creado_en`, `actualizado_en`). Restricción `UNIQUE (persona_id)` para cardinalidad 1:1 lógica estricta y `UNIQUE (codigo)` para código interno estable (`COL-XXXX`). Estado: `'ACTIVO'`, `'INACTIVO'`.
- `episodios_laborales`: Períodos continuos de relación laboral (`id` BIGINT, `colaborador_id`, `fecha_inicio`, `fecha_fin`, `motivo_cese`, `observaciones`, `estado`). Columna virtual generada `uq_colaborador_abierto = IF(fecha_fin IS NULL AND estado = 'ACTIVO', colaborador_id, NULL)` con restricción `UNIQUE` para forzar a nivel de motor máximo un episodio abierto activo por colaborador. `CHECK (fecha_fin IS NULL OR fecha_fin >= fecha_inicio)` y `CHECK (estado IN ('ACTIVO', 'INACTIVO'))`.
- `episodios_laborales_cargos`: Historial inmutable de funciones ocupadas por episodio (`id` BIGINT, `episodio_laboral_id`, `cargo_id`, `fecha_inicio`, `fecha_fin`, `observaciones`). Columna virtual generada `uq_episodio_cargo_abierto = IF(fecha_fin IS NULL, episodio_laboral_id, NULL)` con restricción `UNIQUE` para forzar máximo una asignación de cargo vigente por episodio. `CHECK (fecha_fin IS NULL OR fecha_fin >= fecha_inicio)`. Transición temporal continua en $D-1$ / $D$.


## Reglas

- Claves primarias estables y claves foráneas explícitas.
- Restricciones `NOT NULL`, `UNIQUE`, `CHECK` y FK cuando expresen una invariante real y sean compatibles con la versión seleccionada.
- Índices derivados de consultas justificadas; evitar índices especulativos.
- Importes en `DECIMAL`, nunca `FLOAT`.
- Fechas y horas almacenadas con una política única; intercambio ISO 8601 con zona explícita.
- Estados mediante catálogos o códigos estables con transiciones controladas; no texto libre.
- No usar campos JSON para evitar relaciones que deben ser consultables o validadas.

## Históricos

Las operaciones emitidas congelan los valores necesarios para reproducirlas: tarifa, descripción, precio, costo, proveedor, versión contractual, impuestos y datos relevantes. Cambiar el catálogo afecta operaciones futuras, no documentos pasados.

La configuración y tarifas sensibles a vigencia usan `vigente_desde`, `vigente_hasta` o versión equivalente. No sobrescribir una fila histórica si altera el significado de registros ya emitidos.

## Disponibilidad y concurrencia

Confirmar reserva, estancia, arrendamiento o bloqueo es una operación crítica. El servicio debe:

1. abrir transacción;
2. adquirir la protección de concurrencia definida;
3. volver a comprobar solapamientos;
4. persistir todos los cambios relacionados;
5. confirmar o revertir por completo.

La estrategia exacta —bloqueo pesimista, tabla de inventario temporal u otra— se decidirá con pruebas de concurrencia. Una comprobación previa en interfaz nunca es suficiente.

## Migraciones

- Toda modificación de esquema entra por una migración versionada y revisada.
- Una migración tiene propósito único, precondiciones y reversión o plan de recuperación.
- No editar una migración aplicada en entornos compartidos; crear una nueva.
- Cambios destructivos requieren autorización, respaldo verificado y plan de restauración.
- Semillas contienen catálogos mínimos, nunca datos productivos o credenciales.
- Probar migración desde cero y actualización desde la baseline soportada.

## PDO y repositorios

- Excepciones habilitadas y charset explícito.
- Parámetros enlazados con tipo correcto.
- Consultas en repositorios, no en vistas ni controladores.
- Paginación y límites obligatorios para colecciones potencialmente grandes.
- Evitar `SELECT *` en contratos estables y prevenir consultas N+1.

## Respaldo

Antes de la primera fase con datos reales se debe aprobar política de respaldo, cifrado, retención, pruebas de restauración y recuperación ante fallos. Tener un archivo de respaldo sin prueba de restauración no satisface este requisito.
