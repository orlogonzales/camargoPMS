-- ============================================================================
-- CAMARGO PMS — MIGRACIÓN 006
-- Fase: AUTH-1A (Cierre de Contrato Criptográfico y Bootstrap Inicial)
-- ============================================================================
-- Modifica formalmente la columna contrasena_hash en la tabla usuarios para
-- asegurar el tipo VARCHAR(255), garantizando soporte extensible para
-- PASSWORD_DEFAULT y algoritmos futuros de hash de contraseñas.
-- ============================================================================

ALTER TABLE `usuarios`
    MODIFY COLUMN `contrasena_hash` VARCHAR(255) NOT NULL COMMENT 'Hash criptográfico de la contraseña (soporta PASSWORD_DEFAULT y algoritmos futuros)',
    COMMENT = 'Cuentas de usuario de acceso para personas humanas';

ALTER TABLE `sesiones_usuario`
    COMMENT = 'Sesiones de usuario activas y revocadas';

ALTER TABLE `intentos_autenticacion`
    COMMENT = 'Registro de intentos de autenticación para rate limiting';
