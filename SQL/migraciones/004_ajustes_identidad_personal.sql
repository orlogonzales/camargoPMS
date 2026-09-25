-- ============================================================================
-- Camargo PMS — Migración 004: Cierre de Invariantes Documentales y Temporales
-- ============================================================================
-- Micro-lote: PERSONAL-1A
--
-- Objetivos:
-- 1. Jurisdicción documental estructural en tipos_documento:
--    - Añade pais_fijo_id (FK a paises) y pais_emisor_obligatorio.
--    - Actualiza semillas: DNI y CE fijados a Perú; PASAPORTE dependiente de país emisor.
-- 2. Eliminación definitiva del sentinel técnico pais_emisor_efectivo (0):
--    - Desvincula temporalmente fk_documentos_pais_emisor para modificar nulabilidad.
--    - pais_emisor_id pasa a ser NOT NULL en personas_documentos.
--    - Se elimina la columna virtual pais_emisor_efectivo.
--    - Se restablece la clave foránea real fk_documentos_pais_emisor.
--    - Se establece la restricción UNIQUE real (tipo_documento_id, pais_emisor_id, numero_documento).
-- ============================================================================

-- 1. Agregar columnas de jurisdicción documental a tipos_documento
ALTER TABLE `tipos_documento`
    ADD COLUMN `pais_fijo_id` INT UNSIGNED NULL AFTER `formato_regex`,
    ADD COLUMN `pais_emisor_obligatorio` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER `pais_fijo_id`,
    ADD CONSTRAINT `fk_tipos_documento_pais_fijo` FOREIGN KEY (`pais_fijo_id`) REFERENCES `paises` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- 2. Configurar semillas de tipos_documento según su alcance jurisdiccional
UPDATE `tipos_documento` td
JOIN `paises` p ON p.`codigo_iso2` = 'PE'
SET td.`pais_fijo_id` = p.`id`,
    td.`pais_emisor_obligatorio` = 0
WHERE td.`codigo` IN ('DNI', 'CE');

UPDATE `tipos_documento`
SET `pais_fijo_id` = NULL,
    `pais_emisor_obligatorio` = 1
WHERE `codigo` = 'PASAPORTE';

-- 3. Eliminar sentinel virtual en personas_documentos y consolidar jurisdicción obligatoria real
-- 3A. Desvincular clave foránea para permitir cambio de nulabilidad
ALTER TABLE `personas_documentos`
    DROP FOREIGN KEY `fk_documentos_pais_emisor`;

-- 3B. Eliminar columna virtual y modificar columna a NOT NULL
ALTER TABLE `personas_documentos`
    DROP INDEX `uq_documentos_tipo_emisor_numero`,
    DROP COLUMN `pais_emisor_efectivo`,
    MODIFY COLUMN `pais_emisor_id` INT UNSIGNED NOT NULL;

-- 3C. Restablecer clave foránea y nueva restricción UNIQUE sin sentinel
ALTER TABLE `personas_documentos`
    ADD CONSTRAINT `fk_documentos_pais_emisor` FOREIGN KEY (`pais_emisor_id`) REFERENCES `paises` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    ADD CONSTRAINT `uq_documentos_tipo_pais_numero` UNIQUE (`tipo_documento_id`, `pais_emisor_id`, `numero_documento`);
