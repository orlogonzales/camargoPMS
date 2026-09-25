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

## Esquema del Núcleo de Identidad (IDENTIDAD-1 y ajustes evolutivos 003 y 004 / PERSONAL-1A)

- `paises`: Catálogo normalizado (`id`, `codigo_iso2`, `codigo_iso3`, `nombre`, `nacionalidad`, `activo`). Restricciones UNIQUE en códigos ISO. Semilla base: Perú ('PE', 'PER').
- `tipos_documento`: Catálogo extensible para personas naturales (`id`, `codigo`, `nombre`, `descripcion`, `longitud_exacta`, `longitud_minima`, `longitud_maxima`, `formato_regex`, `pais_fijo_id`, `pais_emisor_obligatorio`, `activo`). Semillas base: DNI (`pais_fijo_id = 1`, `pais_emisor_obligatorio = 0`), Carné de Extranjería (`pais_fijo_id = 1`, `pais_emisor_obligatorio = 0`) y Pasaporte (`pais_fijo_id = NULL`, `pais_emisor_obligatorio = 1`). Clave foránea `fk_tipos_documento_pais_fijo` hacia `paises(id)`. Sin RUC (reservado a personas jurídicas / fiscalidad).
- `personas`: Maestro de personas naturales (`id` BIGINT, `nombres`, `apellido_paterno`, `apellido_materno`, `fecha_nacimiento`, `pais_nacionalidad_id`, `direccion`, `estado`). Nombres obligatorios; apellidos con nulabilidad para monónimos legales y personas extranjeras (evolución migración 003). Restricción CHECK de estado (`ACTIVO`, `INACTIVO`).
- `personas_documentos`: Colección de documentos (`id` BIGINT, `persona_id`, `tipo_documento_id`, `numero_documento`, `pais_emisor_id` INT UNSIGNED NOT NULL, `es_principal`, `fecha_emision`, `fecha_vencimiento`, `estado`). Se eliminan centinelas artificiales y columnas virtuales (evolución migración 004): `pais_emisor_id` es obligatorio y respaldado por la clave foránea íntegra `fk_documentos_pais_emisor` e indexado con `UNIQUE (tipo_documento_id, pais_emisor_id, numero_documento)` para permitir coincidencia de números entre países distintos y prevenir duplicados dentro de la misma jurisdicción. Columna virtual `uq_persona_principal` para garantizar a lo sumo un principal activo por persona.
- `personas_contactos`: Colección de medios de contacto (`id` BIGINT, `persona_id`, `tipo_contacto`, `valor`, `es_whatsapp`, `es_principal`, `estado`). Columna virtual `uq_contacto_tipo_principal` para garantizar un único principal por tipo y persona. Indicador `es_whatsapp` para vincular WhatsApp a un número telefónico sin duplicación física de registros.

## Esquema del Núcleo de Personal y Colaboradores (PERSONAL-1 y PERSONAL-1A)

- `cargos`: Catálogo administrable de puestos laborales (`id` INT, `codigo`, `nombre`, `descripcion`, `activo`). Restricción `UNIQUE (codigo)`. Semillas: ADMINISTRADOR, RECEPCIONISTA, RESERVAS, LIMPIEZA, MANTENIMIENTO.
- `colaboradores`: Identidad laboral estable vinculada a personas (`id` BIGINT, `persona_id`, `codigo`, `estado`, `creado_en`, `actualizado_en`). Restricción `UNIQUE (persona_id)` para cardinalidad 1:1 lógica estricta y `UNIQUE (codigo)` para código interno estable (`COL-XXXX`). Estado: `'ACTIVO'`, `'INACTIVO'`.
- `episodios_laborales`: Períodos continuos de relación laboral (`id` BIGINT, `colaborador_id`, `fecha_inicio`, `fecha_fin`, `motivo_cese`, `observaciones`, `estado`). Columna virtual generada `uq_colaborador_abierto = IF(fecha_fin IS NULL AND estado = 'ACTIVO', colaborador_id, NULL)` con restricción `UNIQUE` para forzar a nivel de motor máximo un episodio abierto activo por colaborador. `CHECK (fecha_fin IS NULL OR fecha_fin >= fecha_inicio)` y `CHECK (estado IN ('ACTIVO', 'INACTIVO'))`.
- `episodios_laborales_cargos`: Historial inmutable de funciones ocupadas por episodio (`id` BIGINT, `episodio_laboral_id`, `cargo_id`, `fecha_inicio`, `fecha_fin`, `observaciones`). Columna virtual generada `uq_episodio_cargo_abierto = IF(fecha_fin IS NULL, episodio_laboral_id, NULL)` con restricción `UNIQUE` para forzar máximo una asignación de cargo vigente por episodio. `CHECK (fecha_fin IS NULL OR fecha_fin >= fecha_inicio)`. Invariante temporal de no solapamiento integral protegida por servicio con `FOR UPDATE` (PERSONAL-1A). Transición temporal continua en $D-1$ / $D$.

## Esquema de Autenticación, Usuarios y Sesiones (AUTH-1 / Migración 005)

- `usuarios`: Cuentas de acceso humano vinculadas 1:1 al núcleo de personas (`id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, `persona_id` BIGINT UNSIGNED NOT NULL UNIQUE, `nombre_usuario` VARCHAR(50) NOT NULL, `nombre_usuario_normalizado` VARCHAR(50) NOT NULL UNIQUE, `contrasena_hash` CHAR(60) NOT NULL, `estado` ENUM('ACTIVO', 'INACTIVO', 'BLOQUEADO') NOT NULL DEFAULT 'ACTIVO', `ultimo_login_en` DATETIME NULL, `creado_en` DATETIME NOT NULL, `actualizado_en` DATETIME NOT NULL). Clave foránea `fk_usuarios_persona` con restricción de borrado `ON DELETE RESTRICT`. Prohibición de relación con `colaboradores` (`colaborador_id`).
- `sesiones_usuario`: Sesiones activas y trazabilidad de tokens opacos (`id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, `usuario_id` BIGINT UNSIGNED NOT NULL, `token_hash` CHAR(64) NOT NULL UNIQUE, `ip_origen` VARCHAR(45) NULL, `user_agent` VARCHAR(500) NULL, `ultimo_acceso_en` DATETIME NOT NULL, `creado_en` DATETIME NOT NULL, `expira_en` DATETIME NOT NULL, `revocada_en` DATETIME NULL). Clave foránea `fk_sesiones_usuario` con `ON DELETE CASCADE`. Índices para búsqueda por hash, usuario activo y limpieza por expiración.
- `intentos_autenticacion`: Registro de intentos de acceso para control de fuerza bruta y rate limiting (`id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, `ip` VARCHAR(45) NOT NULL, `identificador` VARCHAR(100) NOT NULL, `nombre_usuario_normalizado` VARCHAR(50) NULL, `exitoso` TINYINT(1) NOT NULL, `intentado_en` DATETIME NOT NULL). Índices en `(ip, intentado_en)`, `(nombre_usuario_normalizado, intentado_en)` e `idx_intentos_limpieza`.


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
