-- ============================================================================
-- CAMARGO PMS — MIGRACIÓN 034: PERSISTENCIA DE FOTOGRAFÍA DE PERSONA (UI-ALINA-1B-C1)
-- ============================================================================
-- Principios Arquitectónicos y Ontológicos (D-095):
-- 1. IDENTIDAD SOBERANA: PERSONA ≠ USUARIO.
--    La fotografía pertenece exclusivamente a la Persona natural (sujeto humano),
--    no a la cuenta técnica de acceso (Usuario) ni a roles o contratos.
-- 2. DESACOPLAMIENTO DE ALMACENAMIENTO:
--    La columna `foto_ruta` almacena una referencia relativa controlada (ej. 'avatars/<id>.jpg'),
--    nunca una ruta física absoluta del sistema operativo ni una URL dependiente del host.
-- 3. MÍNIMA INTERVENCIÓN DDL:
--    Columna nullable que no afecta la cardinalidad de tablas (se mantienen 118 tablas relacionales).
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE `personas`
    ADD COLUMN `foto_ruta` VARCHAR(255) NULL DEFAULT NULL AFTER `direccion`;

SET FOREIGN_KEY_CHECKS = 1;
