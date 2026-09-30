-- ============================================================================
-- CAMARGO PMS
-- Esquema consolidado oficial
-- Base de datos: camargo_pms
-- Motor: MySQL 8.4.3 LTS
-- Collation: utf8mb4_0900_ai_ci
-- ============================================================================
--
-- Este archivo representa el esquema estructural oficial vigente.
--
-- REGLAS:
-- - Mantener sincronizado con las migraciones oficiales.
-- - No almacenar credenciales ni secretos.
-- - No almacenar datos operativos reales.
-- - Todo cambio estructural debe realizarse mediante una migración
--   controlada cuando corresponda.
-- - Aplicar las convenciones de gobernanza de Camargo PMS.
--
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- Tabla técnica: migraciones
-- Control de versiones e historial de migraciones estructurales ejecutadas.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `migraciones` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `migracion` VARCHAR(255) NOT NULL UNIQUE,
    `lote` INT UNSIGNED NOT NULL DEFAULT 1,
    `ejecutado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Control técnico de migraciones aplicadas en Camargo PMS';

-- ----------------------------------------------------------------------------
-- 1. Catálogo normalizado de países y nacionalidades
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `paises` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo_iso2` CHAR(2) NOT NULL,
    `codigo_iso3` CHAR(3) NOT NULL,
    `nombre` VARCHAR(100) NOT NULL,
    `nacionalidad` VARCHAR(100) NOT NULL,
    `activo` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_paises_iso2` (`codigo_iso2`),
    UNIQUE KEY `uq_paises_iso3` (`codigo_iso3`),
    INDEX `idx_paises_activo` (`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Catálogo normalizado de países y nacionalidades';

-- Semilla estructural: Perú como país base predeterminado
INSERT INTO `paises` (`codigo_iso2`, `codigo_iso3`, `nombre`, `nacionalidad`, `activo`)
VALUES ('PE', 'PER', 'Perú', 'Peruana', 1),
('AR', 'ARG', 'Argentina', 'Argentina', 1),
('BO', 'BOL', 'Bolivia', 'Boliviana', 1),
('BR', 'BRA', 'Brasil', 'Brasileña', 1),
('CL', 'CHL', 'Chile', 'Chilena', 1),
('CO', 'COL', 'Colombia', 'Colombiana', 1),
('EC', 'ECU', 'Ecuador', 'Ecuatoriana', 1),
('ES', 'ESP', 'España', 'Española', 1),
('US', 'USA', 'Estados Unidos', 'Estadounidense', 1),
('MX', 'MEX', 'México', 'Mexicana', 1),
('VE', 'VEN', 'Venezuela', 'Venezolana', 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `nacionalidad` = VALUES(`nacionalidad`);

-- ----------------------------------------------------------------------------

-- ----------------------------------------------------------------------------
-- 1.1 Catálogo oficial de Departamentos, Provincias y Distritos del Perú (INEI UBIGEO)
-- ----------------------------------------------------------------------------
-- 1. Tabla de Departamentos
CREATE TABLE IF NOT EXISTS `departamentos` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `pais_id` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'FK a paises (1 = Perú)',
    `codigo_ubigeo` CHAR(2) NOT NULL COMMENT 'Código INEI 2 dígitos (01-25)',
    `nombre` VARCHAR(100) NOT NULL COMMENT 'Nombre oficial del departamento',
    `activo` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_departamentos_codigo` (`codigo_ubigeo`),
    UNIQUE KEY `uq_departamentos_pais_nombre` (`pais_id`, `nombre`),
    INDEX `idx_departamentos_activo` (`activo`),
    CONSTRAINT `fk_departamentos_pais` FOREIGN KEY (`pais_id`) REFERENCES `paises` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Catálogo oficial de departamentos del Perú (INEI)';

-- 2. Tabla de Provincias
CREATE TABLE IF NOT EXISTS `provincias` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `departamento_id` INT UNSIGNED NOT NULL,
    `codigo_ubigeo` CHAR(4) NOT NULL COMMENT 'Código INEI 4 dígitos (DD+PP)',
    `nombre` VARCHAR(100) NOT NULL COMMENT 'Nombre oficial de la provincia',
    `activo` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_provincias_codigo` (`codigo_ubigeo`),
    INDEX `idx_provincias_departamento` (`departamento_id`),
    INDEX `idx_provincias_activo` (`activo`),
    CONSTRAINT `fk_provincias_departamento` FOREIGN KEY (`departamento_id`) REFERENCES `departamentos` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Catálogo oficial de provincias del Perú (INEI)';

-- 3. Tabla de Distritos
CREATE TABLE IF NOT EXISTS `distritos` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `provincia_id` INT UNSIGNED NOT NULL,
    `codigo_ubigeo` CHAR(6) NOT NULL COMMENT 'Código INEI 6 dígitos (DD+PP+DI)',
    `nombre` VARCHAR(100) NOT NULL COMMENT 'Nombre oficial del distrito',
    `activo` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_distritos_codigo` (`codigo_ubigeo`),
    INDEX `idx_distritos_provincia` (`provincia_id`),
    INDEX `idx_distritos_activo` (`activo`),
    CONSTRAINT `fk_distritos_provincia` FOREIGN KEY (`provincia_id`) REFERENCES `provincias` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Catálogo oficial de distritos del Perú (INEI)';

-- CARGA DE DATOS OFICIALES (INEI UBIGEO)
-- ============================================================================
-- Carga de Departamentos
INSERT INTO `departamentos` (`id`, `pais_id`, `codigo_ubigeo`, `nombre`, `activo`) VALUES
(1, 1, '01', 'Amazonas', 1),
(2, 1, '02', 'Áncash', 1),
(3, 1, '03', 'Apurímac', 1),
(4, 1, '04', 'Arequipa', 1),
(5, 1, '05', 'Ayacucho', 1),
(6, 1, '06', 'Cajamarca', 1),
(7, 1, '07', 'Callao', 1),
(8, 1, '08', 'Cusco', 1),
(9, 1, '09', 'Huancavelica', 1),
(10, 1, '10', 'Huánuco', 1),
(11, 1, '11', 'Ica', 1),
(12, 1, '12', 'Junín', 1),
(13, 1, '13', 'La Libertad', 1),
(14, 1, '14', 'Lambayeque', 1),
(15, 1, '15', 'Lima', 1),
(16, 1, '16', 'Loreto', 1),
(17, 1, '17', 'Madre de Dios', 1),
(18, 1, '18', 'Moquegua', 1),
(19, 1, '19', 'Pasco', 1),
(20, 1, '20', 'Piura', 1),
(21, 1, '21', 'Puno', 1),
(22, 1, '22', 'San Martín', 1),
(23, 1, '23', 'Tacna', 1),
(24, 1, '24', 'Tumbes', 1),
(25, 1, '25', 'Ucayali', 1);

-- Carga de Provincias
INSERT INTO `provincias` (`id`, `departamento_id`, `codigo_ubigeo`, `nombre`, `activo`) VALUES
(1, 1, '0101', 'Chachapoyas', 1),
(2, 1, '0102', 'Bagua', 1),
(3, 1, '0103', 'Bongará', 1),
(4, 1, '0104', 'Condorcanqui', 1),
(5, 1, '0105', 'Luya', 1),
(6, 1, '0106', 'Rodríguez de Mendoza', 1),
(7, 1, '0107', 'Utcubamba', 1),
(8, 2, '0201', 'Huaraz', 1),
(9, 2, '0202', 'Aija', 1),
(10, 2, '0203', 'Antonio Raymondi', 1),
(11, 2, '0204', 'Asunción', 1),
(12, 2, '0205', 'Bolognesi', 1),
(13, 2, '0206', 'Carhuaz', 1),
(14, 2, '0207', 'Carlos Fermín Fitzcarrald', 1),
(15, 2, '0208', 'Casma', 1),
(16, 2, '0209', 'Corongo', 1),
(17, 2, '0210', 'Huari', 1),
(18, 2, '0211', 'Huarmey', 1),
(19, 2, '0212', 'Huaylas', 1),
(20, 2, '0213', 'Mariscal Luzuriaga', 1),
(21, 2, '0214', 'Ocros', 1),
(22, 2, '0215', 'Pallasca', 1),
(23, 2, '0216', 'Pomabamba', 1),
(24, 2, '0217', 'Recuay', 1),
(25, 2, '0218', 'Santa', 1),
(26, 2, '0219', 'Sihuas', 1),
(27, 2, '0220', 'Yungay', 1),
(28, 3, '0301', 'Abancay', 1),
(29, 3, '0302', 'Andahuaylas', 1),
(30, 3, '0303', 'Antabamba', 1),
(31, 3, '0304', 'Aymaraes', 1),
(32, 3, '0305', 'Cotabambas', 1),
(33, 3, '0306', 'Chincheros', 1),
(34, 3, '0307', 'Grau', 1),
(35, 4, '0401', 'Arequipa', 1),
(36, 4, '0402', 'Camaná', 1),
(37, 4, '0403', 'Caravelí', 1),
(38, 4, '0404', 'Castilla', 1),
(39, 4, '0405', 'Caylloma', 1),
(40, 4, '0406', 'Condesuyos', 1),
(41, 4, '0407', 'Islay', 1),
(42, 4, '0408', 'La Uniòn', 1),
(43, 5, '0501', 'Huamanga', 1),
(44, 5, '0502', 'Cangallo', 1),
(45, 5, '0503', 'Huanca Sancos', 1),
(46, 5, '0504', 'Huanta', 1),
(47, 5, '0505', 'La Mar', 1),
(48, 5, '0506', 'Lucanas', 1),
(49, 5, '0507', 'Parinacochas', 1),
(50, 5, '0508', 'Pàucar del Sara Sara', 1),
(51, 5, '0509', 'Sucre', 1),
(52, 5, '0510', 'Víctor Fajardo', 1),
(53, 5, '0511', 'Vilcas Huamán', 1),
(54, 6, '0601', 'Cajamarca', 1),
(55, 6, '0602', 'Cajabamba', 1),
(56, 6, '0603', 'Celendín', 1),
(57, 6, '0604', 'Chota', 1),
(58, 6, '0605', 'Contumazá', 1),
(59, 6, '0606', 'Cutervo', 1),
(60, 6, '0607', 'Hualgayoc', 1),
(61, 6, '0608', 'Jaén', 1),
(62, 6, '0609', 'San Ignacio', 1),
(63, 6, '0610', 'San Marcos', 1),
(64, 6, '0611', 'San Miguel', 1),
(65, 6, '0612', 'San Pablo', 1),
(66, 6, '0613', 'Santa Cruz', 1),
(67, 7, '0701', 'Prov. Const. del Callao', 1),
(68, 8, '0801', 'Cusco', 1),
(69, 8, '0802', 'Acomayo', 1),
(70, 8, '0803', 'Anta', 1),
(71, 8, '0804', 'Calca', 1),
(72, 8, '0805', 'Canas', 1),
(73, 8, '0806', 'Canchis', 1),
(74, 8, '0807', 'Chumbivilcas', 1),
(75, 8, '0808', 'Espinar', 1),
(76, 8, '0809', 'La Convención', 1),
(77, 8, '0810', 'Paruro', 1),
(78, 8, '0811', 'Paucartambo', 1),
(79, 8, '0812', 'Quispicanchi', 1),
(80, 8, '0813', 'Urubamba', 1),
(81, 9, '0901', 'Huancavelica', 1),
(82, 9, '0902', 'Acobamba', 1),
(83, 9, '0903', 'Angaraes', 1),
(84, 9, '0904', 'Castrovirreyna', 1),
(85, 9, '0905', 'Churcampa', 1),
(86, 9, '0906', 'Huaytará', 1),
(87, 9, '0907', 'Tayacaja', 1),
(88, 10, '1001', 'Huánuco', 1),
(89, 10, '1002', 'Ambo', 1),
(90, 10, '1003', 'Dos de Mayo', 1),
(91, 10, '1004', 'Huacaybamba', 1),
(92, 10, '1005', 'Huamalíes', 1),
(93, 10, '1006', 'Leoncio Prado', 1),
(94, 10, '1007', 'Marañón', 1),
(95, 10, '1008', 'Pachitea', 1),
(96, 10, '1009', 'Puerto Inca', 1),
(97, 10, '1010', 'Lauricocha', 1),
(98, 10, '1011', 'Yarowilca', 1),
(99, 11, '1101', 'Ica', 1),
(100, 11, '1102', 'Chincha', 1),
(101, 11, '1103', 'Nasca', 1),
(102, 11, '1104', 'Palpa', 1),
(103, 11, '1105', 'Pisco', 1),
(104, 12, '1201', 'Huancayo', 1),
(105, 12, '1202', 'Concepción', 1),
(106, 12, '1203', 'Chanchamayo', 1),
(107, 12, '1204', 'Jauja', 1),
(108, 12, '1205', 'Junín', 1),
(109, 12, '1206', 'Satipo', 1),
(110, 12, '1207', 'Tarma', 1),
(111, 12, '1208', 'Yauli', 1),
(112, 12, '1209', 'Chupaca', 1),
(113, 13, '1301', 'Trujillo', 1),
(114, 13, '1302', 'Ascope', 1),
(115, 13, '1303', 'Bolívar', 1),
(116, 13, '1304', 'Chepén', 1),
(117, 13, '1305', 'Julcán', 1),
(118, 13, '1306', 'Otuzco', 1),
(119, 13, '1307', 'Pacasmayo', 1),
(120, 13, '1308', 'Pataz', 1),
(121, 13, '1309', 'Sánchez Carrión', 1),
(122, 13, '1310', 'Santiago de Chuco', 1),
(123, 13, '1311', 'Gran Chimú', 1),
(124, 13, '1312', 'Virú', 1),
(125, 14, '1401', 'Chiclayo', 1),
(126, 14, '1402', 'Ferreñafe', 1),
(127, 14, '1403', 'Lambayeque', 1),
(128, 15, '1501', 'Lima', 1),
(129, 15, '1502', 'Barranca', 1),
(130, 15, '1503', 'Cajatambo', 1),
(131, 15, '1504', 'Canta', 1),
(132, 15, '1505', 'Cañete', 1),
(133, 15, '1506', 'Huaral', 1),
(134, 15, '1507', 'Huarochirí', 1),
(135, 15, '1508', 'Huaura', 1),
(136, 15, '1509', 'Oyón', 1),
(137, 15, '1510', 'Yauyos', 1),
(138, 16, '1601', 'Maynas', 1),
(139, 16, '1602', 'Alto Amazonas', 1),
(140, 16, '1603', 'Loreto', 1),
(141, 16, '1604', 'Mariscal Ramón Castilla', 1),
(142, 16, '1605', 'Requena', 1),
(143, 16, '1606', 'Ucayali', 1),
(144, 16, '1607', 'Datem del Marañón', 1),
(145, 16, '1608', 'Putumayo', 1),
(146, 17, '1701', 'Tambopata', 1),
(147, 17, '1702', 'Manu', 1),
(148, 17, '1703', 'Tahuamanu', 1),
(149, 18, '1801', 'Mariscal Nieto', 1),
(150, 18, '1802', 'General Sánchez Cerro', 1),
(151, 18, '1803', 'Ilo', 1),
(152, 19, '1901', 'Pasco', 1),
(153, 19, '1902', 'Daniel Alcides Carrión', 1),
(154, 19, '1903', 'Oxapampa', 1),
(155, 20, '2001', 'Piura', 1),
(156, 20, '2002', 'Ayabaca', 1),
(157, 20, '2003', 'Huancabamba', 1),
(158, 20, '2004', 'Morropón', 1),
(159, 20, '2005', 'Paita', 1),
(160, 20, '2006', 'Sullana', 1),
(161, 20, '2007', 'Talara', 1),
(162, 20, '2008', 'Sechura', 1),
(163, 21, '2101', 'Puno', 1),
(164, 21, '2102', 'Azángaro', 1),
(165, 21, '2103', 'Carabaya', 1),
(166, 21, '2104', 'Chucuito', 1),
(167, 21, '2105', 'El Collao', 1),
(168, 21, '2106', 'Huancané', 1),
(169, 21, '2107', 'Lampa', 1),
(170, 21, '2108', 'Melgar', 1),
(171, 21, '2109', 'Moho', 1),
(172, 21, '2110', 'San Antonio de Putina', 1),
(173, 21, '2111', 'San Román', 1),
(174, 21, '2112', 'Sandia', 1),
(175, 21, '2113', 'Yunguyo', 1),
(176, 22, '2201', 'Moyobamba', 1),
(177, 22, '2202', 'Bellavista', 1),
(178, 22, '2203', 'El Dorado', 1),
(179, 22, '2204', 'Huallaga', 1),
(180, 22, '2205', 'Lamas', 1),
(181, 22, '2206', 'Mariscal Cáceres', 1),
(182, 22, '2207', 'Picota', 1),
(183, 22, '2208', 'Rioja', 1),
(184, 22, '2209', 'San Martín', 1),
(185, 22, '2210', 'Tocache', 1),
(186, 23, '2301', 'Tacna', 1),
(187, 23, '2302', 'Candarave', 1),
(188, 23, '2303', 'Jorge Basadre', 1),
(189, 23, '2304', 'Tarata', 1),
(190, 24, '2401', 'Tumbes', 1),
(191, 24, '2402', 'Contralmirante Villar', 1),
(192, 24, '2403', 'Zarumilla', 1),
(193, 25, '2501', 'Coronel Portillo', 1),
(194, 25, '2502', 'Atalaya', 1),
(195, 25, '2503', 'Padre Abad', 1),
(196, 25, '2504', 'Purús', 1);

-- Carga de Distritos
INSERT INTO `distritos` (`id`, `provincia_id`, `codigo_ubigeo`, `nombre`, `activo`) VALUES
(1, 1, '010101', 'Chachapoyas', 1),
(2, 1, '010102', 'Asunción', 1),
(3, 1, '010103', 'Balsas', 1),
(4, 1, '010104', 'Cheto', 1),
(5, 1, '010105', 'Chiliquin', 1),
(6, 1, '010106', 'Chuquibamba', 1),
(7, 1, '010107', 'Granada', 1),
(8, 1, '010108', 'Huancas', 1),
(9, 1, '010109', 'La Jalca', 1),
(10, 1, '010110', 'Leimebamba', 1),
(11, 1, '010111', 'Levanto', 1),
(12, 1, '010112', 'Magdalena', 1),
(13, 1, '010113', 'Mariscal Castilla', 1),
(14, 1, '010114', 'Molinopampa', 1),
(15, 1, '010115', 'Montevideo', 1),
(16, 1, '010116', 'Olleros', 1),
(17, 1, '010117', 'Quinjalca', 1),
(18, 1, '010118', 'San Francisco de Daguas', 1),
(19, 1, '010119', 'San Isidro de Maino', 1),
(20, 1, '010120', 'Soloco', 1),
(21, 1, '010121', 'Sonche', 1),
(22, 2, '010201', 'Bagua', 1),
(23, 2, '010202', 'Aramango', 1),
(24, 2, '010203', 'Copallin', 1),
(25, 2, '010204', 'El Parco', 1),
(26, 2, '010205', 'Imaza', 1),
(27, 2, '010206', 'La Peca', 1),
(28, 3, '010301', 'Jumbilla', 1),
(29, 3, '010302', 'Chisquilla', 1),
(30, 3, '010303', 'Churuja', 1),
(31, 3, '010304', 'Corosha', 1),
(32, 3, '010305', 'Cuispes', 1),
(33, 3, '010306', 'Florida', 1),
(34, 3, '010307', 'Jazan', 1),
(35, 3, '010308', 'Recta', 1),
(36, 3, '010309', 'San Carlos', 1),
(37, 3, '010310', 'Shipasbamba', 1),
(38, 3, '010311', 'Valera', 1),
(39, 3, '010312', 'Yambrasbamba', 1),
(40, 4, '010401', 'Nieva', 1),
(41, 4, '010402', 'El Cenepa', 1),
(42, 4, '010403', 'Río Santiago', 1),
(43, 5, '010501', 'Lamud', 1),
(44, 5, '010502', 'Camporredondo', 1),
(45, 5, '010503', 'Cocabamba', 1),
(46, 5, '010504', 'Colcamar', 1),
(47, 5, '010505', 'Conila', 1),
(48, 5, '010506', 'Inguilpata', 1),
(49, 5, '010507', 'Longuita', 1),
(50, 5, '010508', 'Lonya Chico', 1),
(51, 5, '010509', 'Luya', 1),
(52, 5, '010510', 'Luya Viejo', 1),
(53, 5, '010511', 'María', 1),
(54, 5, '010512', 'Ocalli', 1),
(55, 5, '010513', 'Ocumal', 1),
(56, 5, '010514', 'Pisuquia', 1),
(57, 5, '010515', 'Providencia', 1),
(58, 5, '010516', 'San Cristóbal', 1),
(59, 5, '010517', 'San Francisco de Yeso', 1),
(60, 5, '010518', 'San Jerónimo', 1),
(61, 5, '010519', 'San Juan de Lopecancha', 1),
(62, 5, '010520', 'Santa Catalina', 1),
(63, 5, '010521', 'Santo Tomas', 1),
(64, 5, '010522', 'Tingo', 1),
(65, 5, '010523', 'Trita', 1),
(66, 6, '010601', 'San Nicolás', 1),
(67, 6, '010602', 'Chirimoto', 1),
(68, 6, '010603', 'Cochamal', 1),
(69, 6, '010604', 'Huambo', 1),
(70, 6, '010605', 'Limabamba', 1),
(71, 6, '010606', 'Longar', 1),
(72, 6, '010607', 'Mariscal Benavides', 1),
(73, 6, '010608', 'Milpuc', 1),
(74, 6, '010609', 'Omia', 1),
(75, 6, '010610', 'Santa Rosa', 1),
(76, 6, '010611', 'Totora', 1),
(77, 6, '010612', 'Vista Alegre', 1),
(78, 7, '010701', 'Bagua Grande', 1),
(79, 7, '010702', 'Cajaruro', 1),
(80, 7, '010703', 'Cumba', 1),
(81, 7, '010704', 'El Milagro', 1),
(82, 7, '010705', 'Jamalca', 1),
(83, 7, '010706', 'Lonya Grande', 1),
(84, 7, '010707', 'Yamon', 1),
(85, 8, '020101', 'Huaraz', 1),
(86, 8, '020102', 'Cochabamba', 1),
(87, 8, '020103', 'Colcabamba', 1),
(88, 8, '020104', 'Huanchay', 1),
(89, 8, '020105', 'Independencia', 1),
(90, 8, '020106', 'Jangas', 1),
(91, 8, '020107', 'La Libertad', 1),
(92, 8, '020108', 'Olleros', 1),
(93, 8, '020109', 'Pampas Grande', 1),
(94, 8, '020110', 'Pariacoto', 1),
(95, 8, '020111', 'Pira', 1),
(96, 8, '020112', 'Tarica', 1),
(97, 9, '020201', 'Aija', 1),
(98, 9, '020202', 'Coris', 1),
(99, 9, '020203', 'Huacllan', 1),
(100, 9, '020204', 'La Merced', 1),
(101, 9, '020205', 'Succha', 1),
(102, 10, '020301', 'Llamellin', 1),
(103, 10, '020302', 'Aczo', 1),
(104, 10, '020303', 'Chaccho', 1),
(105, 10, '020304', 'Chingas', 1),
(106, 10, '020305', 'Mirgas', 1),
(107, 10, '020306', 'San Juan de Rontoy', 1),
(108, 11, '020401', 'Chacas', 1),
(109, 11, '020402', 'Acochaca', 1),
(110, 12, '020501', 'Chiquian', 1),
(111, 12, '020502', 'Abelardo Pardo Lezameta', 1),
(112, 12, '020503', 'Antonio Raymondi', 1),
(113, 12, '020504', 'Aquia', 1),
(114, 12, '020505', 'Cajacay', 1),
(115, 12, '020506', 'Canis', 1),
(116, 12, '020507', 'Colquioc', 1),
(117, 12, '020508', 'Huallanca', 1),
(118, 12, '020509', 'Huasta', 1),
(119, 12, '020510', 'Huayllacayan', 1),
(120, 12, '020511', 'La Primavera', 1),
(121, 12, '020512', 'Mangas', 1),
(122, 12, '020513', 'Pacllon', 1),
(123, 12, '020514', 'San Miguel de Corpanqui', 1),
(124, 12, '020515', 'Ticllos', 1),
(125, 13, '020601', 'Carhuaz', 1),
(126, 13, '020602', 'Acopampa', 1),
(127, 13, '020603', 'Amashca', 1),
(128, 13, '020604', 'Anta', 1),
(129, 13, '020605', 'Ataquero', 1),
(130, 13, '020606', 'Marcara', 1),
(131, 13, '020607', 'Pariahuanca', 1),
(132, 13, '020608', 'San Miguel de Aco', 1),
(133, 13, '020609', 'Shilla', 1),
(134, 13, '020610', 'Tinco', 1),
(135, 13, '020611', 'Yungar', 1),
(136, 14, '020701', 'San Luis', 1),
(137, 14, '020702', 'San Nicolás', 1),
(138, 14, '020703', 'Yauya', 1),
(139, 15, '020801', 'Casma', 1),
(140, 15, '020802', 'Buena Vista Alta', 1),
(141, 15, '020803', 'Comandante Noel', 1),
(142, 15, '020804', 'Yautan', 1),
(143, 16, '020901', 'Corongo', 1),
(144, 16, '020902', 'Aco', 1),
(145, 16, '020903', 'Bambas', 1),
(146, 16, '020904', 'Cusca', 1),
(147, 16, '020905', 'La Pampa', 1),
(148, 16, '020906', 'Yanac', 1),
(149, 16, '020907', 'Yupan', 1),
(150, 17, '021001', 'Huari', 1),
(151, 17, '021002', 'Anra', 1),
(152, 17, '021003', 'Cajay', 1),
(153, 17, '021004', 'Chavin de Huantar', 1),
(154, 17, '021005', 'Huacachi', 1),
(155, 17, '021006', 'Huacchis', 1),
(156, 17, '021007', 'Huachis', 1),
(157, 17, '021008', 'Huantar', 1),
(158, 17, '021009', 'Masin', 1),
(159, 17, '021010', 'Paucas', 1),
(160, 17, '021011', 'Ponto', 1),
(161, 17, '021012', 'Rahuapampa', 1),
(162, 17, '021013', 'Rapayan', 1),
(163, 17, '021014', 'San Marcos', 1),
(164, 17, '021015', 'San Pedro de Chana', 1),
(165, 17, '021016', 'Uco', 1),
(166, 18, '021101', 'Huarmey', 1),
(167, 18, '021102', 'Cochapeti', 1),
(168, 18, '021103', 'Culebras', 1),
(169, 18, '021104', 'Huayan', 1),
(170, 18, '021105', 'Malvas', 1),
(171, 19, '021201', 'Caraz', 1),
(172, 19, '021202', 'Huallanca', 1),
(173, 19, '021203', 'Huata', 1),
(174, 19, '021204', 'Huaylas', 1),
(175, 19, '021205', 'Mato', 1),
(176, 19, '021206', 'Pamparomas', 1),
(177, 19, '021207', 'Pueblo Libre', 1),
(178, 19, '021208', 'Santa Cruz', 1),
(179, 19, '021209', 'Santo Toribio', 1),
(180, 19, '021210', 'Yuracmarca', 1),
(181, 20, '021301', 'Piscobamba', 1),
(182, 20, '021302', 'Casca', 1),
(183, 20, '021303', 'Eleazar Guzmán Barron', 1),
(184, 20, '021304', 'Fidel Olivas Escudero', 1),
(185, 20, '021305', 'Llama', 1),
(186, 20, '021306', 'Llumpa', 1),
(187, 20, '021307', 'Lucma', 1),
(188, 20, '021308', 'Musga', 1),
(189, 21, '021401', 'Ocros', 1),
(190, 21, '021402', 'Acas', 1),
(191, 21, '021403', 'Cajamarquilla', 1),
(192, 21, '021404', 'Carhuapampa', 1),
(193, 21, '021405', 'Cochas', 1),
(194, 21, '021406', 'Congas', 1),
(195, 21, '021407', 'Llipa', 1),
(196, 21, '021408', 'San Cristóbal de Rajan', 1),
(197, 21, '021409', 'San Pedro', 1),
(198, 21, '021410', 'Santiago de Chilcas', 1),
(199, 22, '021501', 'Cabana', 1),
(200, 22, '021502', 'Bolognesi', 1),
(201, 22, '021503', 'Conchucos', 1),
(202, 22, '021504', 'Huacaschuque', 1),
(203, 22, '021505', 'Huandoval', 1),
(204, 22, '021506', 'Lacabamba', 1),
(205, 22, '021507', 'Llapo', 1),
(206, 22, '021508', 'Pallasca', 1),
(207, 22, '021509', 'Pampas', 1),
(208, 22, '021510', 'Santa Rosa', 1),
(209, 22, '021511', 'Tauca', 1),
(210, 23, '021601', 'Pomabamba', 1),
(211, 23, '021602', 'Huayllan', 1),
(212, 23, '021603', 'Parobamba', 1),
(213, 23, '021604', 'Quinuabamba', 1),
(214, 24, '021701', 'Recuay', 1),
(215, 24, '021702', 'Catac', 1),
(216, 24, '021703', 'Cotaparaco', 1),
(217, 24, '021704', 'Huayllapampa', 1),
(218, 24, '021705', 'Llacllin', 1),
(219, 24, '021706', 'Marca', 1),
(220, 24, '021707', 'Pampas Chico', 1),
(221, 24, '021708', 'Pararin', 1),
(222, 24, '021709', 'Tapacocha', 1),
(223, 24, '021710', 'Ticapampa', 1),
(224, 25, '021801', 'Chimbote', 1),
(225, 25, '021802', 'Cáceres del Perú', 1),
(226, 25, '021803', 'Coishco', 1),
(227, 25, '021804', 'Macate', 1),
(228, 25, '021805', 'Moro', 1),
(229, 25, '021806', 'Nepeña', 1),
(230, 25, '021807', 'Samanco', 1),
(231, 25, '021808', 'Santa', 1),
(232, 25, '021809', 'Nuevo Chimbote', 1),
(233, 26, '021901', 'Sihuas', 1),
(234, 26, '021902', 'Acobamba', 1),
(235, 26, '021903', 'Alfonso Ugarte', 1),
(236, 26, '021904', 'Cashapampa', 1),
(237, 26, '021905', 'Chingalpo', 1),
(238, 26, '021906', 'Huayllabamba', 1),
(239, 26, '021907', 'Quiches', 1),
(240, 26, '021908', 'Ragash', 1),
(241, 26, '021909', 'San Juan', 1),
(242, 26, '021910', 'Sicsibamba', 1),
(243, 27, '022001', 'Yungay', 1),
(244, 27, '022002', 'Cascapara', 1),
(245, 27, '022003', 'Mancos', 1),
(246, 27, '022004', 'Matacoto', 1),
(247, 27, '022005', 'Quillo', 1),
(248, 27, '022006', 'Ranrahirca', 1),
(249, 27, '022007', 'Shupluy', 1),
(250, 27, '022008', 'Yanama', 1),
(251, 28, '030101', 'Abancay', 1),
(252, 28, '030102', 'Chacoche', 1),
(253, 28, '030103', 'Circa', 1),
(254, 28, '030104', 'Curahuasi', 1),
(255, 28, '030105', 'Huanipaca', 1),
(256, 28, '030106', 'Lambrama', 1),
(257, 28, '030107', 'Pichirhua', 1),
(258, 28, '030108', 'San Pedro de Cachora', 1),
(259, 28, '030109', 'Tamburco', 1),
(260, 29, '030201', 'Andahuaylas', 1),
(261, 29, '030202', 'Andarapa', 1),
(262, 29, '030203', 'Chiara', 1),
(263, 29, '030204', 'Huancarama', 1),
(264, 29, '030205', 'Huancaray', 1),
(265, 29, '030206', 'Huayana', 1),
(266, 29, '030207', 'Kishuara', 1),
(267, 29, '030208', 'Pacobamba', 1),
(268, 29, '030209', 'Pacucha', 1),
(269, 29, '030210', 'Pampachiri', 1),
(270, 29, '030211', 'Pomacocha', 1),
(271, 29, '030212', 'San Antonio de Cachi', 1),
(272, 29, '030213', 'San Jerónimo', 1),
(273, 29, '030214', 'San Miguel de Chaccrampa', 1),
(274, 29, '030215', 'Santa María de Chicmo', 1),
(275, 29, '030216', 'Talavera', 1),
(276, 29, '030217', 'Tumay Huaraca', 1),
(277, 29, '030218', 'Turpo', 1),
(278, 29, '030219', 'Kaquiabamba', 1),
(279, 29, '030220', 'José María Arguedas', 1),
(280, 30, '030301', 'Antabamba', 1),
(281, 30, '030302', 'El Oro', 1),
(282, 30, '030303', 'Huaquirca', 1),
(283, 30, '030304', 'Juan Espinoza Medrano', 1),
(284, 30, '030305', 'Oropesa', 1),
(285, 30, '030306', 'Pachaconas', 1),
(286, 30, '030307', 'Sabaino', 1),
(287, 31, '030401', 'Chalhuanca', 1),
(288, 31, '030402', 'Capaya', 1),
(289, 31, '030403', 'Caraybamba', 1),
(290, 31, '030404', 'Chapimarca', 1),
(291, 31, '030405', 'Colcabamba', 1),
(292, 31, '030406', 'Cotaruse', 1),
(293, 31, '030407', 'Ihuayllo', 1),
(294, 31, '030408', 'Justo Apu Sahuaraura', 1),
(295, 31, '030409', 'Lucre', 1),
(296, 31, '030410', 'Pocohuanca', 1),
(297, 31, '030411', 'San Juan de Chacña', 1),
(298, 31, '030412', 'Sañayca', 1),
(299, 31, '030413', 'Soraya', 1),
(300, 31, '030414', 'Tapairihua', 1),
(301, 31, '030415', 'Tintay', 1),
(302, 31, '030416', 'Toraya', 1),
(303, 31, '030417', 'Yanaca', 1),
(304, 32, '030501', 'Tambobamba', 1),
(305, 32, '030502', 'Cotabambas', 1),
(306, 32, '030503', 'Coyllurqui', 1),
(307, 32, '030504', 'Haquira', 1),
(308, 32, '030505', 'Mara', 1),
(309, 32, '030506', 'Challhuahuacho', 1),
(310, 33, '030601', 'Chincheros', 1),
(311, 33, '030602', 'Anco_Huallo', 1),
(312, 33, '030603', 'Cocharcas', 1),
(313, 33, '030604', 'Huaccana', 1),
(314, 33, '030605', 'Ocobamba', 1),
(315, 33, '030606', 'Ongoy', 1),
(316, 33, '030607', 'Uranmarca', 1),
(317, 33, '030608', 'Ranracancha', 1),
(318, 33, '030609', 'Rocchacc', 1),
(319, 33, '030610', 'El Porvenir', 1),
(320, 33, '030611', 'Los Chankas', 1),
(321, 34, '030701', 'Chuquibambilla', 1),
(322, 34, '030702', 'Curpahuasi', 1),
(323, 34, '030703', 'Gamarra', 1),
(324, 34, '030704', 'Huayllati', 1),
(325, 34, '030705', 'Mamara', 1),
(326, 34, '030706', 'Micaela Bastidas', 1),
(327, 34, '030707', 'Pataypampa', 1),
(328, 34, '030708', 'Progreso', 1),
(329, 34, '030709', 'San Antonio', 1),
(330, 34, '030710', 'Santa Rosa', 1),
(331, 34, '030711', 'Turpay', 1),
(332, 34, '030712', 'Vilcabamba', 1),
(333, 34, '030713', 'Virundo', 1),
(334, 34, '030714', 'Curasco', 1),
(335, 35, '040101', 'Arequipa', 1),
(336, 35, '040102', 'Alto Selva Alegre', 1),
(337, 35, '040103', 'Cayma', 1),
(338, 35, '040104', 'Cerro Colorado', 1),
(339, 35, '040105', 'Characato', 1),
(340, 35, '040106', 'Chiguata', 1),
(341, 35, '040107', 'Jacobo Hunter', 1),
(342, 35, '040108', 'La Joya', 1),
(343, 35, '040109', 'Mariano Melgar', 1),
(344, 35, '040110', 'Miraflores', 1),
(345, 35, '040111', 'Mollebaya', 1),
(346, 35, '040112', 'Paucarpata', 1),
(347, 35, '040113', 'Pocsi', 1),
(348, 35, '040114', 'Polobaya', 1),
(349, 35, '040115', 'Quequeña', 1),
(350, 35, '040116', 'Sabandia', 1),
(351, 35, '040117', 'Sachaca', 1),
(352, 35, '040118', 'San Juan de Siguas', 1),
(353, 35, '040119', 'San Juan de Tarucani', 1),
(354, 35, '040120', 'Santa Isabel de Siguas', 1),
(355, 35, '040121', 'Santa Rita de Siguas', 1),
(356, 35, '040122', 'Socabaya', 1),
(357, 35, '040123', 'Tiabaya', 1),
(358, 35, '040124', 'Uchumayo', 1),
(359, 35, '040125', 'Vitor', 1),
(360, 35, '040126', 'Yanahuara', 1),
(361, 35, '040127', 'Yarabamba', 1),
(362, 35, '040128', 'Yura', 1),
(363, 35, '040129', 'José Luis Bustamante Y Rivero', 1),
(364, 36, '040201', 'Camaná', 1),
(365, 36, '040202', 'José María Quimper', 1),
(366, 36, '040203', 'Mariano Nicolás Valcárcel', 1),
(367, 36, '040204', 'Mariscal Cáceres', 1),
(368, 36, '040205', 'Nicolás de Pierola', 1),
(369, 36, '040206', 'Ocoña', 1),
(370, 36, '040207', 'Quilca', 1),
(371, 36, '040208', 'Samuel Pastor', 1),
(372, 37, '040301', 'Caravelí', 1),
(373, 37, '040302', 'Acarí', 1),
(374, 37, '040303', 'Atico', 1),
(375, 37, '040304', 'Atiquipa', 1),
(376, 37, '040305', 'Bella Unión', 1),
(377, 37, '040306', 'Cahuacho', 1),
(378, 37, '040307', 'Chala', 1),
(379, 37, '040308', 'Chaparra', 1),
(380, 37, '040309', 'Huanuhuanu', 1),
(381, 37, '040310', 'Jaqui', 1),
(382, 37, '040311', 'Lomas', 1),
(383, 37, '040312', 'Quicacha', 1),
(384, 37, '040313', 'Yauca', 1),
(385, 38, '040401', 'Aplao', 1),
(386, 38, '040402', 'Andagua', 1),
(387, 38, '040403', 'Ayo', 1),
(388, 38, '040404', 'Chachas', 1),
(389, 38, '040405', 'Chilcaymarca', 1),
(390, 38, '040406', 'Choco', 1),
(391, 38, '040407', 'Huancarqui', 1),
(392, 38, '040408', 'Machaguay', 1),
(393, 38, '040409', 'Orcopampa', 1),
(394, 38, '040410', 'Pampacolca', 1),
(395, 38, '040411', 'Tipan', 1),
(396, 38, '040412', 'Uñon', 1),
(397, 38, '040413', 'Uraca', 1),
(398, 38, '040414', 'Viraco', 1),
(399, 39, '040501', 'Chivay', 1),
(400, 39, '040502', 'Achoma', 1),
(401, 39, '040503', 'Cabanaconde', 1),
(402, 39, '040504', 'Callalli', 1),
(403, 39, '040505', 'Caylloma', 1),
(404, 39, '040506', 'Coporaque', 1),
(405, 39, '040507', 'Huambo', 1),
(406, 39, '040508', 'Huanca', 1),
(407, 39, '040509', 'Ichupampa', 1),
(408, 39, '040510', 'Lari', 1),
(409, 39, '040511', 'Lluta', 1),
(410, 39, '040512', 'Maca', 1),
(411, 39, '040513', 'Madrigal', 1),
(412, 39, '040514', 'San Antonio de Chuca', 1),
(413, 39, '040515', 'Sibayo', 1),
(414, 39, '040516', 'Tapay', 1),
(415, 39, '040517', 'Tisco', 1),
(416, 39, '040518', 'Tuti', 1),
(417, 39, '040519', 'Yanque', 1),
(418, 39, '040520', 'Majes', 1),
(419, 40, '040601', 'Chuquibamba', 1),
(420, 40, '040602', 'Andaray', 1),
(421, 40, '040603', 'Cayarani', 1),
(422, 40, '040604', 'Chichas', 1),
(423, 40, '040605', 'Iray', 1),
(424, 40, '040606', 'Río Grande', 1),
(425, 40, '040607', 'Salamanca', 1),
(426, 40, '040608', 'Yanaquihua', 1),
(427, 41, '040701', 'Mollendo', 1),
(428, 41, '040702', 'Cocachacra', 1),
(429, 41, '040703', 'Dean Valdivia', 1),
(430, 41, '040704', 'Islay', 1),
(431, 41, '040705', 'Mejia', 1),
(432, 41, '040706', 'Punta de Bombón', 1),
(433, 42, '040801', 'Cotahuasi', 1),
(434, 42, '040802', 'Alca', 1),
(435, 42, '040803', 'Charcana', 1),
(436, 42, '040804', 'Huaynacotas', 1),
(437, 42, '040805', 'Pampamarca', 1),
(438, 42, '040806', 'Puyca', 1),
(439, 42, '040807', 'Quechualla', 1),
(440, 42, '040808', 'Sayla', 1),
(441, 42, '040809', 'Tauria', 1),
(442, 42, '040810', 'Tomepampa', 1),
(443, 42, '040811', 'Toro', 1),
(444, 43, '050101', 'Ayacucho', 1),
(445, 43, '050102', 'Acocro', 1),
(446, 43, '050103', 'Acos Vinchos', 1),
(447, 43, '050104', 'Carmen Alto', 1),
(448, 43, '050105', 'Chiara', 1),
(449, 43, '050106', 'Ocros', 1),
(450, 43, '050107', 'Pacaycasa', 1),
(451, 43, '050108', 'Quinua', 1),
(452, 43, '050109', 'San José de Ticllas', 1),
(453, 43, '050110', 'San Juan Bautista', 1),
(454, 43, '050111', 'Santiago de Pischa', 1),
(455, 43, '050112', 'Socos', 1),
(456, 43, '050113', 'Tambillo', 1),
(457, 43, '050114', 'Vinchos', 1),
(458, 43, '050115', 'Jesús Nazareno', 1),
(459, 43, '050116', 'Andrés Avelino Cáceres Dorregaray', 1),
(460, 44, '050201', 'Cangallo', 1),
(461, 44, '050202', 'Chuschi', 1),
(462, 44, '050203', 'Los Morochucos', 1),
(463, 44, '050204', 'María Parado de Bellido', 1),
(464, 44, '050205', 'Paras', 1),
(465, 44, '050206', 'Totos', 1),
(466, 45, '050301', 'Sancos', 1),
(467, 45, '050302', 'Carapo', 1),
(468, 45, '050303', 'Sacsamarca', 1),
(469, 45, '050304', 'Santiago de Lucanamarca', 1),
(470, 46, '050401', 'Huanta', 1),
(471, 46, '050402', 'Ayahuanco', 1),
(472, 46, '050403', 'Huamanguilla', 1),
(473, 46, '050404', 'Iguain', 1),
(474, 46, '050405', 'Luricocha', 1),
(475, 46, '050406', 'Santillana', 1),
(476, 46, '050407', 'Sivia', 1),
(477, 46, '050408', 'Llochegua', 1),
(478, 46, '050409', 'Canayre', 1),
(479, 46, '050410', 'Uchuraccay', 1),
(480, 46, '050411', 'Pucacolpa', 1),
(481, 46, '050412', 'Chaca', 1),
(482, 47, '050501', 'San Miguel', 1),
(483, 47, '050502', 'Anco', 1),
(484, 47, '050503', 'Ayna', 1),
(485, 47, '050504', 'Chilcas', 1),
(486, 47, '050505', 'Chungui', 1),
(487, 47, '050506', 'Luis Carranza', 1),
(488, 47, '050507', 'Santa Rosa', 1),
(489, 47, '050508', 'Tambo', 1),
(490, 47, '050509', 'Samugari', 1),
(491, 47, '050510', 'Anchihuay', 1),
(492, 47, '050511', 'Oronccoy', 1),
(493, 48, '050601', 'Puquio', 1),
(494, 48, '050602', 'Aucara', 1),
(495, 48, '050603', 'Cabana', 1),
(496, 48, '050604', 'Carmen Salcedo', 1),
(497, 48, '050605', 'Chaviña', 1),
(498, 48, '050606', 'Chipao', 1),
(499, 48, '050607', 'Huac-Huas', 1),
(500, 48, '050608', 'Laramate', 1);

INSERT INTO `distritos` (`id`, `provincia_id`, `codigo_ubigeo`, `nombre`, `activo`) VALUES
(501, 48, '050609', 'Leoncio Prado', 1),
(502, 48, '050610', 'Llauta', 1),
(503, 48, '050611', 'Lucanas', 1),
(504, 48, '050612', 'Ocaña', 1),
(505, 48, '050613', 'Otoca', 1),
(506, 48, '050614', 'Saisa', 1),
(507, 48, '050615', 'San Cristóbal', 1),
(508, 48, '050616', 'San Juan', 1),
(509, 48, '050617', 'San Pedro', 1),
(510, 48, '050618', 'San Pedro de Palco', 1),
(511, 48, '050619', 'Sancos', 1),
(512, 48, '050620', 'Santa Ana de Huaycahuacho', 1),
(513, 48, '050621', 'Santa Lucia', 1),
(514, 49, '050701', 'Coracora', 1),
(515, 49, '050702', 'Chumpi', 1),
(516, 49, '050703', 'Coronel Castañeda', 1),
(517, 49, '050704', 'Pacapausa', 1),
(518, 49, '050705', 'Pullo', 1),
(519, 49, '050706', 'Puyusca', 1),
(520, 49, '050707', 'San Francisco de Ravacayco', 1),
(521, 49, '050708', 'Upahuacho', 1),
(522, 50, '050801', 'Pausa', 1),
(523, 50, '050802', 'Colta', 1),
(524, 50, '050803', 'Corculla', 1),
(525, 50, '050804', 'Lampa', 1),
(526, 50, '050805', 'Marcabamba', 1),
(527, 50, '050806', 'Oyolo', 1),
(528, 50, '050807', 'Pararca', 1),
(529, 50, '050808', 'San Javier de Alpabamba', 1),
(530, 50, '050809', 'San José de Ushua', 1),
(531, 50, '050810', 'Sara Sara', 1),
(532, 51, '050901', 'Querobamba', 1),
(533, 51, '050902', 'Belén', 1),
(534, 51, '050903', 'Chalcos', 1),
(535, 51, '050904', 'Chilcayoc', 1),
(536, 51, '050905', 'Huacaña', 1),
(537, 51, '050906', 'Morcolla', 1),
(538, 51, '050907', 'Paico', 1),
(539, 51, '050908', 'San Pedro de Larcay', 1),
(540, 51, '050909', 'San Salvador de Quije', 1),
(541, 51, '050910', 'Santiago de Paucaray', 1),
(542, 51, '050911', 'Soras', 1),
(543, 52, '051001', 'Huancapi', 1),
(544, 52, '051002', 'Alcamenca', 1),
(545, 52, '051003', 'Apongo', 1),
(546, 52, '051004', 'Asquipata', 1),
(547, 52, '051005', 'Canaria', 1),
(548, 52, '051006', 'Cayara', 1),
(549, 52, '051007', 'Colca', 1),
(550, 52, '051008', 'Huamanquiquia', 1),
(551, 52, '051009', 'Huancaraylla', 1),
(552, 52, '051010', 'Hualla', 1),
(553, 52, '051011', 'Sarhua', 1),
(554, 52, '051012', 'Vilcanchos', 1),
(555, 53, '051101', 'Vilcas Huaman', 1),
(556, 53, '051102', 'Accomarca', 1),
(557, 53, '051103', 'Carhuanca', 1),
(558, 53, '051104', 'Concepción', 1),
(559, 53, '051105', 'Huambalpa', 1),
(560, 53, '051106', 'Independencia', 1),
(561, 53, '051107', 'Saurama', 1),
(562, 53, '051108', 'Vischongo', 1),
(563, 54, '060101', 'Cajamarca', 1),
(564, 54, '060102', 'Asunción', 1),
(565, 54, '060103', 'Chetilla', 1),
(566, 54, '060104', 'Cospan', 1),
(567, 54, '060105', 'Encañada', 1),
(568, 54, '060106', 'Jesús', 1),
(569, 54, '060107', 'Llacanora', 1),
(570, 54, '060108', 'Los Baños del Inca', 1),
(571, 54, '060109', 'Magdalena', 1),
(572, 54, '060110', 'Matara', 1),
(573, 54, '060111', 'Namora', 1),
(574, 54, '060112', 'San Juan', 1),
(575, 55, '060201', 'Cajabamba', 1),
(576, 55, '060202', 'Cachachi', 1),
(577, 55, '060203', 'Condebamba', 1),
(578, 55, '060204', 'Sitacocha', 1),
(579, 56, '060301', 'Celendín', 1),
(580, 56, '060302', 'Chumuch', 1),
(581, 56, '060303', 'Cortegana', 1),
(582, 56, '060304', 'Huasmin', 1),
(583, 56, '060305', 'Jorge Chávez', 1),
(584, 56, '060306', 'José Gálvez', 1),
(585, 56, '060307', 'Miguel Iglesias', 1),
(586, 56, '060308', 'Oxamarca', 1),
(587, 56, '060309', 'Sorochuco', 1),
(588, 56, '060310', 'Sucre', 1),
(589, 56, '060311', 'Utco', 1),
(590, 56, '060312', 'La Libertad de Pallan', 1),
(591, 57, '060401', 'Chota', 1),
(592, 57, '060402', 'Anguia', 1),
(593, 57, '060403', 'Chadin', 1),
(594, 57, '060404', 'Chiguirip', 1),
(595, 57, '060405', 'Chimban', 1),
(596, 57, '060406', 'Choropampa', 1),
(597, 57, '060407', 'Cochabamba', 1),
(598, 57, '060408', 'Conchan', 1),
(599, 57, '060409', 'Huambos', 1),
(600, 57, '060410', 'Lajas', 1),
(601, 57, '060411', 'Llama', 1),
(602, 57, '060412', 'Miracosta', 1),
(603, 57, '060413', 'Paccha', 1),
(604, 57, '060414', 'Pion', 1),
(605, 57, '060415', 'Querocoto', 1),
(606, 57, '060416', 'San Juan de Licupis', 1),
(607, 57, '060417', 'Tacabamba', 1),
(608, 57, '060418', 'Tocmoche', 1),
(609, 57, '060419', 'Chalamarca', 1),
(610, 58, '060501', 'Contumaza', 1),
(611, 58, '060502', 'Chilete', 1),
(612, 58, '060503', 'Cupisnique', 1),
(613, 58, '060504', 'Guzmango', 1),
(614, 58, '060505', 'San Benito', 1),
(615, 58, '060506', 'Santa Cruz de Toledo', 1),
(616, 58, '060507', 'Tantarica', 1),
(617, 58, '060508', 'Yonan', 1),
(618, 59, '060601', 'Cutervo', 1),
(619, 59, '060602', 'Callayuc', 1),
(620, 59, '060603', 'Choros', 1),
(621, 59, '060604', 'Cujillo', 1),
(622, 59, '060605', 'La Ramada', 1),
(623, 59, '060606', 'Pimpingos', 1),
(624, 59, '060607', 'Querocotillo', 1),
(625, 59, '060608', 'San Andrés de Cutervo', 1),
(626, 59, '060609', 'San Juan de Cutervo', 1),
(627, 59, '060610', 'San Luis de Lucma', 1),
(628, 59, '060611', 'Santa Cruz', 1),
(629, 59, '060612', 'Santo Domingo de la Capilla', 1),
(630, 59, '060613', 'Santo Tomas', 1),
(631, 59, '060614', 'Socota', 1),
(632, 59, '060615', 'Toribio Casanova', 1),
(633, 60, '060701', 'Bambamarca', 1),
(634, 60, '060702', 'Chugur', 1),
(635, 60, '060703', 'Hualgayoc', 1),
(636, 61, '060801', 'Jaén', 1),
(637, 61, '060802', 'Bellavista', 1),
(638, 61, '060803', 'Chontali', 1),
(639, 61, '060804', 'Colasay', 1),
(640, 61, '060805', 'Huabal', 1),
(641, 61, '060806', 'Las Pirias', 1),
(642, 61, '060807', 'Pomahuaca', 1),
(643, 61, '060808', 'Pucara', 1),
(644, 61, '060809', 'Sallique', 1),
(645, 61, '060810', 'San Felipe', 1),
(646, 61, '060811', 'San José del Alto', 1),
(647, 61, '060812', 'Santa Rosa', 1),
(648, 62, '060901', 'San Ignacio', 1),
(649, 62, '060902', 'Chirinos', 1),
(650, 62, '060903', 'Huarango', 1),
(651, 62, '060904', 'La Coipa', 1),
(652, 62, '060905', 'Namballe', 1),
(653, 62, '060906', 'San José de Lourdes', 1),
(654, 62, '060907', 'Tabaconas', 1),
(655, 63, '061001', 'Pedro Gálvez', 1),
(656, 63, '061002', 'Chancay', 1),
(657, 63, '061003', 'Eduardo Villanueva', 1),
(658, 63, '061004', 'Gregorio Pita', 1),
(659, 63, '061005', 'Ichocan', 1),
(660, 63, '061006', 'José Manuel Quiroz', 1),
(661, 63, '061007', 'José Sabogal', 1),
(662, 64, '061101', 'San Miguel', 1),
(663, 64, '061102', 'Bolívar', 1),
(664, 64, '061103', 'Calquis', 1),
(665, 64, '061104', 'Catilluc', 1),
(666, 64, '061105', 'El Prado', 1),
(667, 64, '061106', 'La Florida', 1),
(668, 64, '061107', 'Llapa', 1),
(669, 64, '061108', 'Nanchoc', 1),
(670, 64, '061109', 'Niepos', 1),
(671, 64, '061110', 'San Gregorio', 1),
(672, 64, '061111', 'San Silvestre de Cochan', 1),
(673, 64, '061112', 'Tongod', 1),
(674, 64, '061113', 'Unión Agua Blanca', 1),
(675, 65, '061201', 'San Pablo', 1),
(676, 65, '061202', 'San Bernardino', 1),
(677, 65, '061203', 'San Luis', 1),
(678, 65, '061204', 'Tumbaden', 1),
(679, 66, '061301', 'Santa Cruz', 1),
(680, 66, '061302', 'Andabamba', 1),
(681, 66, '061303', 'Catache', 1),
(682, 66, '061304', 'Chancaybaños', 1),
(683, 66, '061305', 'La Esperanza', 1),
(684, 66, '061306', 'Ninabamba', 1),
(685, 66, '061307', 'Pulan', 1),
(686, 66, '061308', 'Saucepampa', 1),
(687, 66, '061309', 'Sexi', 1),
(688, 66, '061310', 'Uticyacu', 1),
(689, 66, '061311', 'Yauyucan', 1),
(690, 67, '070101', 'Callao', 1),
(691, 67, '070102', 'Bellavista', 1),
(692, 67, '070103', 'Carmen de la Legua Reynoso', 1),
(693, 67, '070104', 'La Perla', 1),
(694, 67, '070105', 'La Punta', 1),
(695, 67, '070106', 'Ventanilla', 1),
(696, 67, '070107', 'Mi Perú', 1),
(697, 68, '080101', 'Cusco', 1),
(698, 68, '080102', 'Ccorca', 1),
(699, 68, '080103', 'Poroy', 1),
(700, 68, '080104', 'San Jerónimo', 1),
(701, 68, '080105', 'San Sebastian', 1),
(702, 68, '080106', 'Santiago', 1),
(703, 68, '080107', 'Saylla', 1),
(704, 68, '080108', 'Wanchaq', 1),
(705, 69, '080201', 'Acomayo', 1),
(706, 69, '080202', 'Acopia', 1),
(707, 69, '080203', 'Acos', 1),
(708, 69, '080204', 'Mosoc Llacta', 1),
(709, 69, '080205', 'Pomacanchi', 1),
(710, 69, '080206', 'Rondocan', 1),
(711, 69, '080207', 'Sangarara', 1),
(712, 70, '080301', 'Anta', 1),
(713, 70, '080302', 'Ancahuasi', 1),
(714, 70, '080303', 'Cachimayo', 1),
(715, 70, '080304', 'Chinchaypujio', 1),
(716, 70, '080305', 'Huarocondo', 1),
(717, 70, '080306', 'Limatambo', 1),
(718, 70, '080307', 'Mollepata', 1),
(719, 70, '080308', 'Pucyura', 1),
(720, 70, '080309', 'Zurite', 1),
(721, 71, '080401', 'Calca', 1),
(722, 71, '080402', 'Coya', 1),
(723, 71, '080403', 'Lamay', 1),
(724, 71, '080404', 'Lares', 1),
(725, 71, '080405', 'Pisac', 1),
(726, 71, '080406', 'San Salvador', 1),
(727, 71, '080407', 'Taray', 1),
(728, 71, '080408', 'Yanatile', 1),
(729, 72, '080501', 'Yanaoca', 1),
(730, 72, '080502', 'Checca', 1),
(731, 72, '080503', 'Kunturkanki', 1),
(732, 72, '080504', 'Langui', 1),
(733, 72, '080505', 'Layo', 1),
(734, 72, '080506', 'Pampamarca', 1),
(735, 72, '080507', 'Quehue', 1),
(736, 72, '080508', 'Tupac Amaru', 1),
(737, 73, '080601', 'Sicuani', 1),
(738, 73, '080602', 'Checacupe', 1),
(739, 73, '080603', 'Combapata', 1),
(740, 73, '080604', 'Marangani', 1),
(741, 73, '080605', 'Pitumarca', 1),
(742, 73, '080606', 'San Pablo', 1),
(743, 73, '080607', 'San Pedro', 1),
(744, 73, '080608', 'Tinta', 1),
(745, 74, '080701', 'Santo Tomas', 1),
(746, 74, '080702', 'Capacmarca', 1),
(747, 74, '080703', 'Chamaca', 1),
(748, 74, '080704', 'Colquemarca', 1),
(749, 74, '080705', 'Livitaca', 1),
(750, 74, '080706', 'Llusco', 1),
(751, 74, '080707', 'Quiñota', 1),
(752, 74, '080708', 'Velille', 1),
(753, 75, '080801', 'Espinar', 1),
(754, 75, '080802', 'Condoroma', 1),
(755, 75, '080803', 'Coporaque', 1),
(756, 75, '080804', 'Ocoruro', 1),
(757, 75, '080805', 'Pallpata', 1),
(758, 75, '080806', 'Pichigua', 1),
(759, 75, '080807', 'Suyckutambo', 1),
(760, 75, '080808', 'Alto Pichigua', 1),
(761, 76, '080901', 'Santa Ana', 1),
(762, 76, '080902', 'Echarate', 1),
(763, 76, '080903', 'Huayopata', 1),
(764, 76, '080904', 'Maranura', 1),
(765, 76, '080905', 'Ocobamba', 1),
(766, 76, '080906', 'Quellouno', 1),
(767, 76, '080907', 'Kimbiri', 1),
(768, 76, '080908', 'Santa Teresa', 1),
(769, 76, '080909', 'Vilcabamba', 1),
(770, 76, '080910', 'Pichari', 1),
(771, 76, '080911', 'Inkawasi', 1),
(772, 76, '080912', 'Villa Virgen', 1),
(773, 76, '080913', 'Villa Kintiarina', 1),
(774, 76, '080914', 'Megantoni', 1),
(775, 77, '081001', 'Paruro', 1),
(776, 77, '081002', 'Accha', 1),
(777, 77, '081003', 'Ccapi', 1),
(778, 77, '081004', 'Colcha', 1),
(779, 77, '081005', 'Huanoquite', 1),
(780, 77, '081006', 'Omachaç', 1),
(781, 77, '081007', 'Paccaritambo', 1),
(782, 77, '081008', 'Pillpinto', 1),
(783, 77, '081009', 'Yaurisque', 1),
(784, 78, '081101', 'Paucartambo', 1),
(785, 78, '081102', 'Caicay', 1),
(786, 78, '081103', 'Challabamba', 1),
(787, 78, '081104', 'Colquepata', 1),
(788, 78, '081105', 'Huancarani', 1),
(789, 78, '081106', 'Kosñipata', 1),
(790, 79, '081201', 'Urcos', 1),
(791, 79, '081202', 'Andahuaylillas', 1),
(792, 79, '081203', 'Camanti', 1),
(793, 79, '081204', 'Ccarhuayo', 1),
(794, 79, '081205', 'Ccatca', 1),
(795, 79, '081206', 'Cusipata', 1),
(796, 79, '081207', 'Huaro', 1),
(797, 79, '081208', 'Lucre', 1),
(798, 79, '081209', 'Marcapata', 1),
(799, 79, '081210', 'Ocongate', 1),
(800, 79, '081211', 'Oropesa', 1),
(801, 79, '081212', 'Quiquijana', 1),
(802, 80, '081301', 'Urubamba', 1),
(803, 80, '081302', 'Chinchero', 1),
(804, 80, '081303', 'Huayllabamba', 1),
(805, 80, '081304', 'Machupicchu', 1),
(806, 80, '081305', 'Maras', 1),
(807, 80, '081306', 'Ollantaytambo', 1),
(808, 80, '081307', 'Yucay', 1),
(809, 81, '090101', 'Huancavelica', 1),
(810, 81, '090102', 'Acobambilla', 1),
(811, 81, '090103', 'Acoria', 1),
(812, 81, '090104', 'Conayca', 1),
(813, 81, '090105', 'Cuenca', 1),
(814, 81, '090106', 'Huachocolpa', 1),
(815, 81, '090107', 'Huayllahuara', 1),
(816, 81, '090108', 'Izcuchaca', 1),
(817, 81, '090109', 'Laria', 1),
(818, 81, '090110', 'Manta', 1),
(819, 81, '090111', 'Mariscal Cáceres', 1),
(820, 81, '090112', 'Moya', 1),
(821, 81, '090113', 'Nuevo Occoro', 1),
(822, 81, '090114', 'Palca', 1),
(823, 81, '090115', 'Pilchaca', 1),
(824, 81, '090116', 'Vilca', 1),
(825, 81, '090117', 'Yauli', 1),
(826, 81, '090118', 'Ascensión', 1),
(827, 81, '090119', 'Huando', 1),
(828, 82, '090201', 'Acobamba', 1),
(829, 82, '090202', 'Andabamba', 1),
(830, 82, '090203', 'Anta', 1),
(831, 82, '090204', 'Caja', 1),
(832, 82, '090205', 'Marcas', 1),
(833, 82, '090206', 'Paucara', 1),
(834, 82, '090207', 'Pomacocha', 1),
(835, 82, '090208', 'Rosario', 1),
(836, 83, '090301', 'Lircay', 1),
(837, 83, '090302', 'Anchonga', 1),
(838, 83, '090303', 'Callanmarca', 1),
(839, 83, '090304', 'Ccochaccasa', 1),
(840, 83, '090305', 'Chincho', 1),
(841, 83, '090306', 'Congalla', 1),
(842, 83, '090307', 'Huanca-Huanca', 1),
(843, 83, '090308', 'Huayllay Grande', 1),
(844, 83, '090309', 'Julcamarca', 1),
(845, 83, '090310', 'San Antonio de Antaparco', 1),
(846, 83, '090311', 'Santo Tomas de Pata', 1),
(847, 83, '090312', 'Secclla', 1),
(848, 84, '090401', 'Castrovirreyna', 1),
(849, 84, '090402', 'Arma', 1),
(850, 84, '090403', 'Aurahua', 1),
(851, 84, '090404', 'Capillas', 1),
(852, 84, '090405', 'Chupamarca', 1),
(853, 84, '090406', 'Cocas', 1),
(854, 84, '090407', 'Huachos', 1),
(855, 84, '090408', 'Huamatambo', 1),
(856, 84, '090409', 'Mollepampa', 1),
(857, 84, '090410', 'San Juan', 1),
(858, 84, '090411', 'Santa Ana', 1),
(859, 84, '090412', 'Tantara', 1),
(860, 84, '090413', 'Ticrapo', 1),
(861, 85, '090501', 'Churcampa', 1),
(862, 85, '090502', 'Anco', 1),
(863, 85, '090503', 'Chinchihuasi', 1),
(864, 85, '090504', 'El Carmen', 1),
(865, 85, '090505', 'La Merced', 1),
(866, 85, '090506', 'Locroja', 1),
(867, 85, '090507', 'Paucarbamba', 1),
(868, 85, '090508', 'San Miguel de Mayocc', 1),
(869, 85, '090509', 'San Pedro de Coris', 1),
(870, 85, '090510', 'Pachamarca', 1),
(871, 85, '090511', 'Cosme', 1),
(872, 86, '090601', 'Huaytara', 1),
(873, 86, '090602', 'Ayavi', 1),
(874, 86, '090603', 'Córdova', 1),
(875, 86, '090604', 'Huayacundo Arma', 1),
(876, 86, '090605', 'Laramarca', 1),
(877, 86, '090606', 'Ocoyo', 1),
(878, 86, '090607', 'Pilpichaca', 1),
(879, 86, '090608', 'Querco', 1),
(880, 86, '090609', 'Quito-Arma', 1),
(881, 86, '090610', 'San Antonio de Cusicancha', 1),
(882, 86, '090611', 'San Francisco de Sangayaico', 1),
(883, 86, '090612', 'San Isidro', 1),
(884, 86, '090613', 'Santiago de Chocorvos', 1),
(885, 86, '090614', 'Santiago de Quirahuara', 1),
(886, 86, '090615', 'Santo Domingo de Capillas', 1),
(887, 86, '090616', 'Tambo', 1),
(888, 87, '090701', 'Pampas', 1),
(889, 87, '090702', 'Acostambo', 1),
(890, 87, '090703', 'Acraquia', 1),
(891, 87, '090704', 'Ahuaycha', 1),
(892, 87, '090705', 'Colcabamba', 1),
(893, 87, '090706', 'Daniel Hernández', 1),
(894, 87, '090707', 'Huachocolpa', 1),
(895, 87, '090709', 'Huaribamba', 1),
(896, 87, '090710', 'Ñahuimpuquio', 1),
(897, 87, '090711', 'Pazos', 1),
(898, 87, '090713', 'Quishuar', 1),
(899, 87, '090714', 'Salcabamba', 1),
(900, 87, '090715', 'Salcahuasi', 1),
(901, 87, '090716', 'San Marcos de Rocchac', 1),
(902, 87, '090717', 'Surcubamba', 1),
(903, 87, '090718', 'Tintay Puncu', 1),
(904, 87, '090719', 'Quichuas', 1),
(905, 87, '090720', 'Andaymarca', 1),
(906, 87, '090721', 'Roble', 1),
(907, 87, '090722', 'Pichos', 1),
(908, 87, '090723', 'Santiago de Tucuma', 1),
(909, 88, '100101', 'Huanuco', 1),
(910, 88, '100102', 'Amarilis', 1),
(911, 88, '100103', 'Chinchao', 1),
(912, 88, '100104', 'Churubamba', 1),
(913, 88, '100105', 'Margos', 1),
(914, 88, '100106', 'Quisqui (Kichki)', 1),
(915, 88, '100107', 'San Francisco de Cayran', 1),
(916, 88, '100108', 'San Pedro de Chaulan', 1),
(917, 88, '100109', 'Santa María del Valle', 1),
(918, 88, '100110', 'Yarumayo', 1),
(919, 88, '100111', 'Pillco Marca', 1),
(920, 88, '100112', 'Yacus', 1),
(921, 88, '100113', 'San Pablo de Pillao', 1),
(922, 89, '100201', 'Ambo', 1),
(923, 89, '100202', 'Cayna', 1),
(924, 89, '100203', 'Colpas', 1),
(925, 89, '100204', 'Conchamarca', 1),
(926, 89, '100205', 'Huacar', 1),
(927, 89, '100206', 'San Francisco', 1),
(928, 89, '100207', 'San Rafael', 1),
(929, 89, '100208', 'Tomay Kichwa', 1),
(930, 90, '100301', 'La Unión', 1),
(931, 90, '100307', 'Chuquis', 1),
(932, 90, '100311', 'Marías', 1),
(933, 90, '100313', 'Pachas', 1),
(934, 90, '100316', 'Quivilla', 1),
(935, 90, '100317', 'Ripan', 1),
(936, 90, '100321', 'Shunqui', 1),
(937, 90, '100322', 'Sillapata', 1),
(938, 90, '100323', 'Yanas', 1),
(939, 91, '100401', 'Huacaybamba', 1),
(940, 91, '100402', 'Canchabamba', 1),
(941, 91, '100403', 'Cochabamba', 1),
(942, 91, '100404', 'Pinra', 1),
(943, 92, '100501', 'Llata', 1),
(944, 92, '100502', 'Arancay', 1),
(945, 92, '100503', 'Chavín de Pariarca', 1),
(946, 92, '100504', 'Jacas Grande', 1),
(947, 92, '100505', 'Jircan', 1),
(948, 92, '100506', 'Miraflores', 1),
(949, 92, '100507', 'Monzón', 1),
(950, 92, '100508', 'Punchao', 1),
(951, 92, '100509', 'Puños', 1),
(952, 92, '100510', 'Singa', 1),
(953, 92, '100511', 'Tantamayo', 1),
(954, 93, '100601', 'Rupa-Rupa', 1),
(955, 93, '100602', 'Daniel Alomía Robles', 1),
(956, 93, '100603', 'Hermílio Valdizan', 1),
(957, 93, '100604', 'José Crespo y Castillo', 1),
(958, 93, '100605', 'Luyando', 1),
(959, 93, '100606', 'Mariano Damaso Beraun', 1),
(960, 93, '100607', 'Pucayacu', 1),
(961, 93, '100608', 'Castillo Grande', 1),
(962, 93, '100609', 'Pueblo Nuevo', 1),
(963, 93, '100610', 'Santo Domingo de Anda', 1),
(964, 94, '100701', 'Huacrachuco', 1),
(965, 94, '100702', 'Cholon', 1),
(966, 94, '100703', 'San Buenaventura', 1),
(967, 94, '100704', 'La Morada', 1),
(968, 94, '100705', 'Santa Rosa de Alto Yanajanca', 1),
(969, 95, '100801', 'Panao', 1),
(970, 95, '100802', 'Chaglla', 1),
(971, 95, '100803', 'Molino', 1),
(972, 95, '100804', 'Umari', 1),
(973, 96, '100901', 'Puerto Inca', 1),
(974, 96, '100902', 'Codo del Pozuzo', 1),
(975, 96, '100903', 'Honoria', 1),
(976, 96, '100904', 'Tournavista', 1),
(977, 96, '100905', 'Yuyapichis', 1),
(978, 97, '101001', 'Jesús', 1),
(979, 97, '101002', 'Baños', 1),
(980, 97, '101003', 'Jivia', 1),
(981, 97, '101004', 'Queropalca', 1),
(982, 97, '101005', 'Rondos', 1),
(983, 97, '101006', 'San Francisco de Asís', 1),
(984, 97, '101007', 'San Miguel de Cauri', 1),
(985, 98, '101101', 'Chavinillo', 1),
(986, 98, '101102', 'Cahuac', 1),
(987, 98, '101103', 'Chacabamba', 1),
(988, 98, '101104', 'Aparicio Pomares', 1),
(989, 98, '101105', 'Jacas Chico', 1),
(990, 98, '101106', 'Obas', 1),
(991, 98, '101107', 'Pampamarca', 1),
(992, 98, '101108', 'Choras', 1),
(993, 99, '110101', 'Ica', 1),
(994, 99, '110102', 'La Tinguiña', 1),
(995, 99, '110103', 'Los Aquijes', 1),
(996, 99, '110104', 'Ocucaje', 1),
(997, 99, '110105', 'Pachacutec', 1),
(998, 99, '110106', 'Parcona', 1),
(999, 99, '110107', 'Pueblo Nuevo', 1),
(1000, 99, '110108', 'Salas', 1);

INSERT INTO `distritos` (`id`, `provincia_id`, `codigo_ubigeo`, `nombre`, `activo`) VALUES
(1001, 99, '110109', 'San José de Los Molinos', 1),
(1002, 99, '110110', 'San Juan Bautista', 1),
(1003, 99, '110111', 'Santiago', 1),
(1004, 99, '110112', 'Subtanjalla', 1),
(1005, 99, '110113', 'Tate', 1),
(1006, 99, '110114', 'Yauca del Rosario', 1),
(1007, 100, '110201', 'Chincha Alta', 1),
(1008, 100, '110202', 'Alto Laran', 1),
(1009, 100, '110203', 'Chavin', 1),
(1010, 100, '110204', 'Chincha Baja', 1),
(1011, 100, '110205', 'El Carmen', 1),
(1012, 100, '110206', 'Grocio Prado', 1),
(1013, 100, '110207', 'Pueblo Nuevo', 1),
(1014, 100, '110208', 'San Juan de Yanac', 1),
(1015, 100, '110209', 'San Pedro de Huacarpana', 1),
(1016, 100, '110210', 'Sunampe', 1),
(1017, 100, '110211', 'Tambo de Mora', 1),
(1018, 101, '110301', 'Nasca', 1),
(1019, 101, '110302', 'Changuillo', 1),
(1020, 101, '110303', 'El Ingenio', 1),
(1021, 101, '110304', 'Marcona', 1),
(1022, 101, '110305', 'Vista Alegre', 1),
(1023, 102, '110401', 'Palpa', 1),
(1024, 102, '110402', 'Llipata', 1),
(1025, 102, '110403', 'Río Grande', 1),
(1026, 102, '110404', 'Santa Cruz', 1),
(1027, 102, '110405', 'Tibillo', 1),
(1028, 103, '110501', 'Pisco', 1),
(1029, 103, '110502', 'Huancano', 1),
(1030, 103, '110503', 'Humay', 1),
(1031, 103, '110504', 'Independencia', 1),
(1032, 103, '110505', 'Paracas', 1),
(1033, 103, '110506', 'San Andrés', 1),
(1034, 103, '110507', 'San Clemente', 1),
(1035, 103, '110508', 'Tupac Amaru Inca', 1),
(1036, 104, '120101', 'Huancayo', 1),
(1037, 104, '120104', 'Carhuacallanga', 1),
(1038, 104, '120105', 'Chacapampa', 1),
(1039, 104, '120106', 'Chicche', 1),
(1040, 104, '120107', 'Chilca', 1),
(1041, 104, '120108', 'Chongos Alto', 1),
(1042, 104, '120111', 'Chupuro', 1),
(1043, 104, '120112', 'Colca', 1),
(1044, 104, '120113', 'Cullhuas', 1),
(1045, 104, '120114', 'El Tambo', 1),
(1046, 104, '120116', 'Huacrapuquio', 1),
(1047, 104, '120117', 'Hualhuas', 1),
(1048, 104, '120119', 'Huancan', 1),
(1049, 104, '120120', 'Huasicancha', 1),
(1050, 104, '120121', 'Huayucachi', 1),
(1051, 104, '120122', 'Ingenio', 1),
(1052, 104, '120124', 'Pariahuanca', 1),
(1053, 104, '120125', 'Pilcomayo', 1),
(1054, 104, '120126', 'Pucara', 1),
(1055, 104, '120127', 'Quichuay', 1),
(1056, 104, '120128', 'Quilcas', 1),
(1057, 104, '120129', 'San Agustín', 1),
(1058, 104, '120130', 'San Jerónimo de Tunan', 1),
(1059, 104, '120132', 'Saño', 1),
(1060, 104, '120133', 'Sapallanga', 1),
(1061, 104, '120134', 'Sicaya', 1),
(1062, 104, '120135', 'Santo Domingo de Acobamba', 1),
(1063, 104, '120136', 'Viques', 1),
(1064, 105, '120201', 'Concepción', 1),
(1065, 105, '120202', 'Aco', 1),
(1066, 105, '120203', 'Andamarca', 1),
(1067, 105, '120204', 'Chambara', 1),
(1068, 105, '120205', 'Cochas', 1),
(1069, 105, '120206', 'Comas', 1),
(1070, 105, '120207', 'Heroínas Toledo', 1),
(1071, 105, '120208', 'Manzanares', 1),
(1072, 105, '120209', 'Mariscal Castilla', 1),
(1073, 105, '120210', 'Matahuasi', 1),
(1074, 105, '120211', 'Mito', 1),
(1075, 105, '120212', 'Nueve de Julio', 1),
(1076, 105, '120213', 'Orcotuna', 1),
(1077, 105, '120214', 'San José de Quero', 1),
(1078, 105, '120215', 'Santa Rosa de Ocopa', 1),
(1079, 106, '120301', 'Chanchamayo', 1),
(1080, 106, '120302', 'Perene', 1),
(1081, 106, '120303', 'Pichanaqui', 1),
(1082, 106, '120304', 'San Luis de Shuaro', 1),
(1083, 106, '120305', 'San Ramón', 1),
(1084, 106, '120306', 'Vitoc', 1),
(1085, 107, '120401', 'Jauja', 1),
(1086, 107, '120402', 'Acolla', 1),
(1087, 107, '120403', 'Apata', 1),
(1088, 107, '120404', 'Ataura', 1),
(1089, 107, '120405', 'Canchayllo', 1),
(1090, 107, '120406', 'Curicaca', 1),
(1091, 107, '120407', 'El Mantaro', 1),
(1092, 107, '120408', 'Huamali', 1),
(1093, 107, '120409', 'Huaripampa', 1),
(1094, 107, '120410', 'Huertas', 1),
(1095, 107, '120411', 'Janjaillo', 1),
(1096, 107, '120412', 'Julcán', 1),
(1097, 107, '120413', 'Leonor Ordóñez', 1),
(1098, 107, '120414', 'Llocllapampa', 1),
(1099, 107, '120415', 'Marco', 1),
(1100, 107, '120416', 'Masma', 1),
(1101, 107, '120417', 'Masma Chicche', 1),
(1102, 107, '120418', 'Molinos', 1),
(1103, 107, '120419', 'Monobamba', 1),
(1104, 107, '120420', 'Muqui', 1),
(1105, 107, '120421', 'Muquiyauyo', 1),
(1106, 107, '120422', 'Paca', 1),
(1107, 107, '120423', 'Paccha', 1),
(1108, 107, '120424', 'Pancan', 1),
(1109, 107, '120425', 'Parco', 1),
(1110, 107, '120426', 'Pomacancha', 1),
(1111, 107, '120427', 'Ricran', 1),
(1112, 107, '120428', 'San Lorenzo', 1),
(1113, 107, '120429', 'San Pedro de Chunan', 1),
(1114, 107, '120430', 'Sausa', 1),
(1115, 107, '120431', 'Sincos', 1),
(1116, 107, '120432', 'Tunan Marca', 1),
(1117, 107, '120433', 'Yauli', 1),
(1118, 107, '120434', 'Yauyos', 1),
(1119, 108, '120501', 'Junin', 1),
(1120, 108, '120502', 'Carhuamayo', 1),
(1121, 108, '120503', 'Ondores', 1),
(1122, 108, '120504', 'Ulcumayo', 1),
(1123, 109, '120601', 'Satipo', 1),
(1124, 109, '120602', 'Coviriali', 1),
(1125, 109, '120603', 'Llaylla', 1),
(1126, 109, '120604', 'Mazamari', 1),
(1127, 109, '120605', 'Pampa Hermosa', 1),
(1128, 109, '120606', 'Pangoa', 1),
(1129, 109, '120607', 'Río Negro', 1),
(1130, 109, '120608', 'Río Tambo', 1),
(1131, 109, '120609', 'Vizcatan del Ene', 1),
(1132, 110, '120701', 'Tarma', 1),
(1133, 110, '120702', 'Acobamba', 1),
(1134, 110, '120703', 'Huaricolca', 1),
(1135, 110, '120704', 'Huasahuasi', 1),
(1136, 110, '120705', 'La Unión', 1),
(1137, 110, '120706', 'Palca', 1),
(1138, 110, '120707', 'Palcamayo', 1),
(1139, 110, '120708', 'San Pedro de Cajas', 1),
(1140, 110, '120709', 'Tapo', 1),
(1141, 111, '120801', 'La Oroya', 1),
(1142, 111, '120802', 'Chacapalpa', 1),
(1143, 111, '120803', 'Huay-Huay', 1),
(1144, 111, '120804', 'Marcapomacocha', 1),
(1145, 111, '120805', 'Morococha', 1),
(1146, 111, '120806', 'Paccha', 1),
(1147, 111, '120807', 'Santa Bárbara de Carhuacayan', 1),
(1148, 111, '120808', 'Santa Rosa de Sacco', 1),
(1149, 111, '120809', 'Suitucancha', 1),
(1150, 111, '120810', 'Yauli', 1),
(1151, 112, '120901', 'Chupaca', 1),
(1152, 112, '120902', 'Ahuac', 1),
(1153, 112, '120903', 'Chongos Bajo', 1),
(1154, 112, '120904', 'Huachac', 1),
(1155, 112, '120905', 'Huamancaca Chico', 1),
(1156, 112, '120906', 'San Juan de Iscos', 1),
(1157, 112, '120907', 'San Juan de Jarpa', 1),
(1158, 112, '120908', 'Tres de Diciembre', 1),
(1159, 112, '120909', 'Yanacancha', 1),
(1160, 113, '130101', 'Trujillo', 1),
(1161, 113, '130102', 'El Porvenir', 1),
(1162, 113, '130103', 'Florencia de Mora', 1),
(1163, 113, '130104', 'Huanchaco', 1),
(1164, 113, '130105', 'La Esperanza', 1),
(1165, 113, '130106', 'Laredo', 1),
(1166, 113, '130107', 'Moche', 1),
(1167, 113, '130108', 'Poroto', 1),
(1168, 113, '130109', 'Salaverry', 1),
(1169, 113, '130110', 'Simbal', 1),
(1170, 113, '130111', 'Victor Larco Herrera', 1),
(1171, 114, '130201', 'Ascope', 1),
(1172, 114, '130202', 'Chicama', 1),
(1173, 114, '130203', 'Chocope', 1),
(1174, 114, '130204', 'Magdalena de Cao', 1),
(1175, 114, '130205', 'Paijan', 1),
(1176, 114, '130206', 'Rázuri', 1),
(1177, 114, '130207', 'Santiago de Cao', 1),
(1178, 114, '130208', 'Casa Grande', 1),
(1179, 115, '130301', 'Bolívar', 1),
(1180, 115, '130302', 'Bambamarca', 1),
(1181, 115, '130303', 'Condormarca', 1),
(1182, 115, '130304', 'Longotea', 1),
(1183, 115, '130305', 'Uchumarca', 1),
(1184, 115, '130306', 'Ucuncha', 1),
(1185, 116, '130401', 'Chepen', 1),
(1186, 116, '130402', 'Pacanga', 1),
(1187, 116, '130403', 'Pueblo Nuevo', 1),
(1188, 117, '130501', 'Julcan', 1),
(1189, 117, '130502', 'Calamarca', 1),
(1190, 117, '130503', 'Carabamba', 1),
(1191, 117, '130504', 'Huaso', 1),
(1192, 118, '130601', 'Otuzco', 1),
(1193, 118, '130602', 'Agallpampa', 1),
(1194, 118, '130604', 'Charat', 1),
(1195, 118, '130605', 'Huaranchal', 1),
(1196, 118, '130606', 'La Cuesta', 1),
(1197, 118, '130608', 'Mache', 1),
(1198, 118, '130610', 'Paranday', 1),
(1199, 118, '130611', 'Salpo', 1),
(1200, 118, '130613', 'Sinsicap', 1),
(1201, 118, '130614', 'Usquil', 1),
(1202, 119, '130701', 'San Pedro de Lloc', 1),
(1203, 119, '130702', 'Guadalupe', 1),
(1204, 119, '130703', 'Jequetepeque', 1),
(1205, 119, '130704', 'Pacasmayo', 1),
(1206, 119, '130705', 'San José', 1),
(1207, 120, '130801', 'Tayabamba', 1),
(1208, 120, '130802', 'Buldibuyo', 1),
(1209, 120, '130803', 'Chillia', 1),
(1210, 120, '130804', 'Huancaspata', 1),
(1211, 120, '130805', 'Huaylillas', 1),
(1212, 120, '130806', 'Huayo', 1),
(1213, 120, '130807', 'Ongon', 1),
(1214, 120, '130808', 'Parcoy', 1),
(1215, 120, '130809', 'Pataz', 1),
(1216, 120, '130810', 'Pias', 1),
(1217, 120, '130811', 'Santiago de Challas', 1),
(1218, 120, '130812', 'Taurija', 1),
(1219, 120, '130813', 'Urpay', 1),
(1220, 121, '130901', 'Huamachuco', 1),
(1221, 121, '130902', 'Chugay', 1),
(1222, 121, '130903', 'Cochorco', 1),
(1223, 121, '130904', 'Curgos', 1),
(1224, 121, '130905', 'Marcabal', 1),
(1225, 121, '130906', 'Sanagoran', 1),
(1226, 121, '130907', 'Sarin', 1),
(1227, 121, '130908', 'Sartimbamba', 1),
(1228, 122, '131001', 'Santiago de Chuco', 1),
(1229, 122, '131002', 'Angasmarca', 1),
(1230, 122, '131003', 'Cachicadan', 1),
(1231, 122, '131004', 'Mollebamba', 1),
(1232, 122, '131005', 'Mollepata', 1),
(1233, 122, '131006', 'Quiruvilca', 1),
(1234, 122, '131007', 'Santa Cruz de Chuca', 1),
(1235, 122, '131008', 'Sitabamba', 1),
(1236, 123, '131101', 'Cascas', 1),
(1237, 123, '131102', 'Lucma', 1),
(1238, 123, '131103', 'Marmot', 1),
(1239, 123, '131104', 'Sayapullo', 1),
(1240, 124, '131201', 'Viru', 1),
(1241, 124, '131202', 'Chao', 1),
(1242, 124, '131203', 'Guadalupito', 1),
(1243, 125, '140101', 'Chiclayo', 1),
(1244, 125, '140102', 'Chongoyape', 1),
(1245, 125, '140103', 'Eten', 1),
(1246, 125, '140104', 'Eten Puerto', 1),
(1247, 125, '140105', 'José Leonardo Ortiz', 1),
(1248, 125, '140106', 'La Victoria', 1),
(1249, 125, '140107', 'Lagunas', 1),
(1250, 125, '140108', 'Monsefu', 1),
(1251, 125, '140109', 'Nueva Arica', 1),
(1252, 125, '140110', 'Oyotun', 1),
(1253, 125, '140111', 'Picsi', 1),
(1254, 125, '140112', 'Pimentel', 1),
(1255, 125, '140113', 'Reque', 1),
(1256, 125, '140114', 'Santa Rosa', 1),
(1257, 125, '140115', 'Saña', 1),
(1258, 125, '140116', 'Cayalti', 1),
(1259, 125, '140117', 'Patapo', 1),
(1260, 125, '140118', 'Pomalca', 1),
(1261, 125, '140119', 'Pucala', 1),
(1262, 125, '140120', 'Tuman', 1),
(1263, 126, '140201', 'Ferreñafe', 1),
(1264, 126, '140202', 'Cañaris', 1),
(1265, 126, '140203', 'Incahuasi', 1),
(1266, 126, '140204', 'Manuel Antonio Mesones Muro', 1),
(1267, 126, '140205', 'Pitipo', 1),
(1268, 126, '140206', 'Pueblo Nuevo', 1),
(1269, 127, '140301', 'Lambayeque', 1),
(1270, 127, '140302', 'Chochope', 1),
(1271, 127, '140303', 'Illimo', 1),
(1272, 127, '140304', 'Jayanca', 1),
(1273, 127, '140305', 'Mochumi', 1),
(1274, 127, '140306', 'Morrope', 1),
(1275, 127, '140307', 'Motupe', 1),
(1276, 127, '140308', 'Olmos', 1),
(1277, 127, '140309', 'Pacora', 1),
(1278, 127, '140310', 'Salas', 1),
(1279, 127, '140311', 'San José', 1),
(1280, 127, '140312', 'Tucume', 1),
(1281, 128, '150101', 'Lima', 1),
(1282, 128, '150102', 'Ancón', 1),
(1283, 128, '150103', 'Ate', 1),
(1284, 128, '150104', 'Barranco', 1),
(1285, 128, '150105', 'Breña', 1),
(1286, 128, '150106', 'Carabayllo', 1),
(1287, 128, '150107', 'Chaclacayo', 1),
(1288, 128, '150108', 'Chorrillos', 1),
(1289, 128, '150109', 'Cieneguilla', 1),
(1290, 128, '150110', 'Comas', 1),
(1291, 128, '150111', 'El Agustino', 1),
(1292, 128, '150112', 'Independencia', 1),
(1293, 128, '150113', 'Jesús María', 1),
(1294, 128, '150114', 'La Molina', 1),
(1295, 128, '150115', 'La Victoria', 1),
(1296, 128, '150116', 'Lince', 1),
(1297, 128, '150117', 'Los Olivos', 1),
(1298, 128, '150118', 'Lurigancho', 1),
(1299, 128, '150119', 'Lurin', 1),
(1300, 128, '150120', 'Magdalena del Mar', 1),
(1301, 128, '150121', 'Pueblo Libre', 1),
(1302, 128, '150122', 'Miraflores', 1),
(1303, 128, '150123', 'Pachacamac', 1),
(1304, 128, '150124', 'Pucusana', 1),
(1305, 128, '150125', 'Puente Piedra', 1),
(1306, 128, '150126', 'Punta Hermosa', 1),
(1307, 128, '150127', 'Punta Negra', 1),
(1308, 128, '150128', 'Rímac', 1),
(1309, 128, '150129', 'San Bartolo', 1),
(1310, 128, '150130', 'San Borja', 1),
(1311, 128, '150131', 'San Isidro', 1),
(1312, 128, '150132', 'San Juan de Lurigancho', 1),
(1313, 128, '150133', 'San Juan de Miraflores', 1),
(1314, 128, '150134', 'San Luis', 1),
(1315, 128, '150135', 'San Martín de Porres', 1),
(1316, 128, '150136', 'San Miguel', 1),
(1317, 128, '150137', 'Santa Anita', 1),
(1318, 128, '150138', 'Santa María del Mar', 1),
(1319, 128, '150139', 'Santa Rosa', 1),
(1320, 128, '150140', 'Santiago de Surco', 1),
(1321, 128, '150141', 'Surquillo', 1),
(1322, 128, '150142', 'Villa El Salvador', 1),
(1323, 128, '150143', 'Villa María del Triunfo', 1),
(1324, 129, '150201', 'Barranca', 1),
(1325, 129, '150202', 'Paramonga', 1),
(1326, 129, '150203', 'Pativilca', 1),
(1327, 129, '150204', 'Supe', 1),
(1328, 129, '150205', 'Supe Puerto', 1),
(1329, 130, '150301', 'Cajatambo', 1),
(1330, 130, '150302', 'Copa', 1),
(1331, 130, '150303', 'Gorgor', 1),
(1332, 130, '150304', 'Huancapon', 1),
(1333, 130, '150305', 'Manas', 1),
(1334, 131, '150401', 'Canta', 1),
(1335, 131, '150402', 'Arahuay', 1),
(1336, 131, '150403', 'Huamantanga', 1),
(1337, 131, '150404', 'Huaros', 1),
(1338, 131, '150405', 'Lachaqui', 1),
(1339, 131, '150406', 'San Buenaventura', 1),
(1340, 131, '150407', 'Santa Rosa de Quives', 1),
(1341, 132, '150501', 'San Vicente de Cañete', 1),
(1342, 132, '150502', 'Asia', 1),
(1343, 132, '150503', 'Calango', 1),
(1344, 132, '150504', 'Cerro Azul', 1),
(1345, 132, '150505', 'Chilca', 1),
(1346, 132, '150506', 'Coayllo', 1),
(1347, 132, '150507', 'Imperial', 1),
(1348, 132, '150508', 'Lunahuana', 1),
(1349, 132, '150509', 'Mala', 1),
(1350, 132, '150510', 'Nuevo Imperial', 1),
(1351, 132, '150511', 'Pacaran', 1),
(1352, 132, '150512', 'Quilmana', 1),
(1353, 132, '150513', 'San Antonio', 1),
(1354, 132, '150514', 'San Luis', 1),
(1355, 132, '150515', 'Santa Cruz de Flores', 1),
(1356, 132, '150516', 'Zúñiga', 1),
(1357, 133, '150601', 'Huaral', 1),
(1358, 133, '150602', 'Atavillos Alto', 1),
(1359, 133, '150603', 'Atavillos Bajo', 1),
(1360, 133, '150604', 'Aucallama', 1),
(1361, 133, '150605', 'Chancay', 1),
(1362, 133, '150606', 'Ihuari', 1),
(1363, 133, '150607', 'Lampian', 1),
(1364, 133, '150608', 'Pacaraos', 1),
(1365, 133, '150609', 'San Miguel de Acos', 1),
(1366, 133, '150610', 'Santa Cruz de Andamarca', 1),
(1367, 133, '150611', 'Sumbilca', 1),
(1368, 133, '150612', 'Veintisiete de Noviembre', 1),
(1369, 134, '150701', 'Matucana', 1),
(1370, 134, '150702', 'Antioquia', 1),
(1371, 134, '150703', 'Callahuanca', 1),
(1372, 134, '150704', 'Carampoma', 1),
(1373, 134, '150705', 'Chicla', 1),
(1374, 134, '150706', 'Cuenca', 1),
(1375, 134, '150707', 'Huachupampa', 1),
(1376, 134, '150708', 'Huanza', 1),
(1377, 134, '150709', 'Huarochiri', 1),
(1378, 134, '150710', 'Lahuaytambo', 1),
(1379, 134, '150711', 'Langa', 1),
(1380, 134, '150712', 'Laraos', 1),
(1381, 134, '150713', 'Mariatana', 1),
(1382, 134, '150714', 'Ricardo Palma', 1),
(1383, 134, '150715', 'San Andrés de Tupicocha', 1),
(1384, 134, '150716', 'San Antonio', 1),
(1385, 134, '150717', 'San Bartolomé', 1),
(1386, 134, '150718', 'San Damian', 1),
(1387, 134, '150719', 'San Juan de Iris', 1),
(1388, 134, '150720', 'San Juan de Tantaranche', 1),
(1389, 134, '150721', 'San Lorenzo de Quinti', 1),
(1390, 134, '150722', 'San Mateo', 1),
(1391, 134, '150723', 'San Mateo de Otao', 1),
(1392, 134, '150724', 'San Pedro de Casta', 1),
(1393, 134, '150725', 'San Pedro de Huancayre', 1),
(1394, 134, '150726', 'Sangallaya', 1),
(1395, 134, '150727', 'Santa Cruz de Cocachacra', 1),
(1396, 134, '150728', 'Santa Eulalia', 1),
(1397, 134, '150729', 'Santiago de Anchucaya', 1),
(1398, 134, '150730', 'Santiago de Tuna', 1),
(1399, 134, '150731', 'Santo Domingo de Los Olleros', 1),
(1400, 134, '150732', 'Surco', 1),
(1401, 135, '150801', 'Huacho', 1),
(1402, 135, '150802', 'Ambar', 1),
(1403, 135, '150803', 'Caleta de Carquin', 1),
(1404, 135, '150804', 'Checras', 1),
(1405, 135, '150805', 'Hualmay', 1),
(1406, 135, '150806', 'Huaura', 1),
(1407, 135, '150807', 'Leoncio Prado', 1),
(1408, 135, '150808', 'Paccho', 1),
(1409, 135, '150809', 'Santa Leonor', 1),
(1410, 135, '150810', 'Santa María', 1),
(1411, 135, '150811', 'Sayan', 1),
(1412, 135, '150812', 'Vegueta', 1),
(1413, 136, '150901', 'Oyon', 1),
(1414, 136, '150902', 'Andajes', 1),
(1415, 136, '150903', 'Caujul', 1),
(1416, 136, '150904', 'Cochamarca', 1),
(1417, 136, '150905', 'Navan', 1),
(1418, 136, '150906', 'Pachangara', 1),
(1419, 137, '151001', 'Yauyos', 1),
(1420, 137, '151002', 'Alis', 1),
(1421, 137, '151003', 'Allauca', 1),
(1422, 137, '151004', 'Ayaviri', 1),
(1423, 137, '151005', 'Azángaro', 1),
(1424, 137, '151006', 'Cacra', 1),
(1425, 137, '151007', 'Carania', 1),
(1426, 137, '151008', 'Catahuasi', 1),
(1427, 137, '151009', 'Chocos', 1),
(1428, 137, '151010', 'Cochas', 1),
(1429, 137, '151011', 'Colonia', 1),
(1430, 137, '151012', 'Hongos', 1),
(1431, 137, '151013', 'Huampara', 1),
(1432, 137, '151014', 'Huancaya', 1),
(1433, 137, '151015', 'Huangascar', 1),
(1434, 137, '151016', 'Huantan', 1),
(1435, 137, '151017', 'Huañec', 1),
(1436, 137, '151018', 'Laraos', 1),
(1437, 137, '151019', 'Lincha', 1),
(1438, 137, '151020', 'Madean', 1),
(1439, 137, '151021', 'Miraflores', 1),
(1440, 137, '151022', 'Omas', 1),
(1441, 137, '151023', 'Putinza', 1),
(1442, 137, '151024', 'Quinches', 1),
(1443, 137, '151025', 'Quinocay', 1),
(1444, 137, '151026', 'San Joaquín', 1),
(1445, 137, '151027', 'San Pedro de Pilas', 1),
(1446, 137, '151028', 'Tanta', 1),
(1447, 137, '151029', 'Tauripampa', 1),
(1448, 137, '151030', 'Tomas', 1),
(1449, 137, '151031', 'Tupe', 1),
(1450, 137, '151032', 'Viñac', 1),
(1451, 137, '151033', 'Vitis', 1),
(1452, 138, '160101', 'Iquitos', 1),
(1453, 138, '160102', 'Alto Nanay', 1),
(1454, 138, '160103', 'Fernando Lores', 1),
(1455, 138, '160104', 'Indiana', 1),
(1456, 138, '160105', 'Las Amazonas', 1),
(1457, 138, '160106', 'Mazan', 1),
(1458, 138, '160107', 'Napo', 1),
(1459, 138, '160108', 'Punchana', 1),
(1460, 138, '160110', 'Torres Causana', 1),
(1461, 138, '160112', 'Belén', 1),
(1462, 138, '160113', 'San Juan Bautista', 1),
(1463, 139, '160201', 'Yurimaguas', 1),
(1464, 139, '160202', 'Balsapuerto', 1),
(1465, 139, '160205', 'Jeberos', 1),
(1466, 139, '160206', 'Lagunas', 1),
(1467, 139, '160210', 'Santa Cruz', 1),
(1468, 139, '160211', 'Teniente Cesar López Rojas', 1),
(1469, 140, '160301', 'Nauta', 1),
(1470, 140, '160302', 'Parinari', 1),
(1471, 140, '160303', 'Tigre', 1),
(1472, 140, '160304', 'Trompeteros', 1),
(1473, 140, '160305', 'Urarinas', 1),
(1474, 141, '160401', 'Ramón Castilla', 1),
(1475, 141, '160402', 'Pebas', 1),
(1476, 141, '160403', 'Yavari', 1),
(1477, 141, '160404', 'San Pablo', 1),
(1478, 142, '160501', 'Requena', 1),
(1479, 142, '160502', 'Alto Tapiche', 1),
(1480, 142, '160503', 'Capelo', 1),
(1481, 142, '160504', 'Emilio San Martín', 1),
(1482, 142, '160505', 'Maquia', 1),
(1483, 142, '160506', 'Puinahua', 1),
(1484, 142, '160507', 'Saquena', 1),
(1485, 142, '160508', 'Soplin', 1),
(1486, 142, '160509', 'Tapiche', 1),
(1487, 142, '160510', 'Jenaro Herrera', 1),
(1488, 142, '160511', 'Yaquerana', 1),
(1489, 143, '160601', 'Contamana', 1),
(1490, 143, '160602', 'Inahuaya', 1),
(1491, 143, '160603', 'Padre Márquez', 1),
(1492, 143, '160604', 'Pampa Hermosa', 1),
(1493, 143, '160605', 'Sarayacu', 1),
(1494, 143, '160606', 'Vargas Guerra', 1),
(1495, 144, '160701', 'Barranca', 1),
(1496, 144, '160702', 'Cahuapanas', 1),
(1497, 144, '160703', 'Manseriche', 1),
(1498, 144, '160704', 'Morona', 1),
(1499, 144, '160705', 'Pastaza', 1),
(1500, 144, '160706', 'Andoas', 1);

INSERT INTO `distritos` (`id`, `provincia_id`, `codigo_ubigeo`, `nombre`, `activo`) VALUES
(1501, 145, '160801', 'Putumayo', 1),
(1502, 145, '160802', 'Rosa Panduro', 1),
(1503, 145, '160803', 'Teniente Manuel Clavero', 1),
(1504, 145, '160804', 'Yaguas', 1),
(1505, 146, '170101', 'Tambopata', 1),
(1506, 146, '170102', 'Inambari', 1),
(1507, 146, '170103', 'Las Piedras', 1),
(1508, 146, '170104', 'Laberinto', 1),
(1509, 147, '170201', 'Manu', 1),
(1510, 147, '170202', 'Fitzcarrald', 1),
(1511, 147, '170203', 'Madre de Dios', 1),
(1512, 147, '170204', 'Huepetuhe', 1),
(1513, 148, '170301', 'Iñapari', 1),
(1514, 148, '170302', 'Iberia', 1),
(1515, 148, '170303', 'Tahuamanu', 1),
(1516, 149, '180101', 'Moquegua', 1),
(1517, 149, '180102', 'Carumas', 1),
(1518, 149, '180103', 'Cuchumbaya', 1),
(1519, 149, '180104', 'Samegua', 1),
(1520, 149, '180105', 'San Cristóbal', 1),
(1521, 149, '180106', 'Torata', 1),
(1522, 150, '180201', 'Omate', 1),
(1523, 150, '180202', 'Chojata', 1),
(1524, 150, '180203', 'Coalaque', 1),
(1525, 150, '180204', 'Ichuña', 1),
(1526, 150, '180205', 'La Capilla', 1),
(1527, 150, '180206', 'Lloque', 1),
(1528, 150, '180207', 'Matalaque', 1),
(1529, 150, '180208', 'Puquina', 1),
(1530, 150, '180209', 'Quinistaquillas', 1),
(1531, 150, '180210', 'Ubinas', 1),
(1532, 150, '180211', 'Yunga', 1),
(1533, 151, '180301', 'Ilo', 1),
(1534, 151, '180302', 'El Algarrobal', 1),
(1535, 151, '180303', 'Pacocha', 1),
(1536, 152, '190101', 'Chaupimarca', 1),
(1537, 152, '190102', 'Huachon', 1),
(1538, 152, '190103', 'Huariaca', 1),
(1539, 152, '190104', 'Huayllay', 1),
(1540, 152, '190105', 'Ninacaca', 1),
(1541, 152, '190106', 'Pallanchacra', 1),
(1542, 152, '190107', 'Paucartambo', 1),
(1543, 152, '190108', 'San Francisco de Asís de Yarusyacan', 1),
(1544, 152, '190109', 'Simon Bolívar', 1),
(1545, 152, '190110', 'Ticlacayan', 1),
(1546, 152, '190111', 'Tinyahuarco', 1),
(1547, 152, '190112', 'Vicco', 1),
(1548, 152, '190113', 'Yanacancha', 1),
(1549, 153, '190201', 'Yanahuanca', 1),
(1550, 153, '190202', 'Chacayan', 1),
(1551, 153, '190203', 'Goyllarisquizga', 1),
(1552, 153, '190204', 'Paucar', 1),
(1553, 153, '190205', 'San Pedro de Pillao', 1),
(1554, 153, '190206', 'Santa Ana de Tusi', 1),
(1555, 153, '190207', 'Tapuc', 1),
(1556, 153, '190208', 'Vilcabamba', 1),
(1557, 154, '190301', 'Oxapampa', 1),
(1558, 154, '190302', 'Chontabamba', 1),
(1559, 154, '190303', 'Huancabamba', 1),
(1560, 154, '190304', 'Palcazu', 1),
(1561, 154, '190305', 'Pozuzo', 1),
(1562, 154, '190306', 'Puerto Bermúdez', 1),
(1563, 154, '190307', 'Villa Rica', 1),
(1564, 154, '190308', 'Constitución', 1),
(1565, 155, '200101', 'Piura', 1),
(1566, 155, '200104', 'Castilla', 1),
(1567, 155, '200105', 'Catacaos', 1),
(1568, 155, '200107', 'Cura Mori', 1),
(1569, 155, '200108', 'El Tallan', 1),
(1570, 155, '200109', 'La Arena', 1),
(1571, 155, '200110', 'La Unión', 1),
(1572, 155, '200111', 'Las Lomas', 1),
(1573, 155, '200114', 'Tambo Grande', 1),
(1574, 155, '200115', 'Veintiseis de Octubre', 1),
(1575, 156, '200201', 'Ayabaca', 1),
(1576, 156, '200202', 'Frias', 1),
(1577, 156, '200203', 'Jilili', 1),
(1578, 156, '200204', 'Lagunas', 1),
(1579, 156, '200205', 'Montero', 1),
(1580, 156, '200206', 'Pacaipampa', 1),
(1581, 156, '200207', 'Paimas', 1),
(1582, 156, '200208', 'Sapillica', 1),
(1583, 156, '200209', 'Sicchez', 1),
(1584, 156, '200210', 'Suyo', 1),
(1585, 157, '200301', 'Huancabamba', 1),
(1586, 157, '200302', 'Canchaque', 1),
(1587, 157, '200303', 'El Carmen de la Frontera', 1),
(1588, 157, '200304', 'Huarmaca', 1),
(1589, 157, '200305', 'Lalaquiz', 1),
(1590, 157, '200306', 'San Miguel de El Faique', 1),
(1591, 157, '200307', 'Sondor', 1),
(1592, 157, '200308', 'Sondorillo', 1),
(1593, 158, '200401', 'Chulucanas', 1),
(1594, 158, '200402', 'Buenos Aires', 1),
(1595, 158, '200403', 'Chalaco', 1),
(1596, 158, '200404', 'La Matanza', 1),
(1597, 158, '200405', 'Morropon', 1),
(1598, 158, '200406', 'Salitral', 1),
(1599, 158, '200407', 'San Juan de Bigote', 1),
(1600, 158, '200408', 'Santa Catalina de Mossa', 1),
(1601, 158, '200409', 'Santo Domingo', 1),
(1602, 158, '200410', 'Yamango', 1),
(1603, 159, '200501', 'Paita', 1),
(1604, 159, '200502', 'Amotape', 1),
(1605, 159, '200503', 'Arenal', 1),
(1606, 159, '200504', 'Colan', 1),
(1607, 159, '200505', 'La Huaca', 1),
(1608, 159, '200506', 'Tamarindo', 1),
(1609, 159, '200507', 'Vichayal', 1),
(1610, 160, '200601', 'Sullana', 1),
(1611, 160, '200602', 'Bellavista', 1),
(1612, 160, '200603', 'Ignacio Escudero', 1),
(1613, 160, '200604', 'Lancones', 1),
(1614, 160, '200605', 'Marcavelica', 1),
(1615, 160, '200606', 'Miguel Checa', 1),
(1616, 160, '200607', 'Querecotillo', 1),
(1617, 160, '200608', 'Salitral', 1),
(1618, 161, '200701', 'Pariñas', 1),
(1619, 161, '200702', 'El Alto', 1),
(1620, 161, '200703', 'La Brea', 1),
(1621, 161, '200704', 'Lobitos', 1),
(1622, 161, '200705', 'Los Organos', 1),
(1623, 161, '200706', 'Mancora', 1),
(1624, 162, '200801', 'Sechura', 1),
(1625, 162, '200802', 'Bellavista de la Unión', 1),
(1626, 162, '200803', 'Bernal', 1),
(1627, 162, '200804', 'Cristo Nos Valga', 1),
(1628, 162, '200805', 'Vice', 1),
(1629, 162, '200806', 'Rinconada Llicuar', 1),
(1630, 163, '210101', 'Puno', 1),
(1631, 163, '210102', 'Acora', 1),
(1632, 163, '210103', 'Amantani', 1),
(1633, 163, '210104', 'Atuncolla', 1),
(1634, 163, '210105', 'Capachica', 1),
(1635, 163, '210106', 'Chucuito', 1),
(1636, 163, '210107', 'Coata', 1),
(1637, 163, '210108', 'Huata', 1),
(1638, 163, '210109', 'Mañazo', 1),
(1639, 163, '210110', 'Paucarcolla', 1),
(1640, 163, '210111', 'Pichacani', 1),
(1641, 163, '210112', 'Plateria', 1),
(1642, 163, '210113', 'San Antonio', 1),
(1643, 163, '210114', 'Tiquillaca', 1),
(1644, 163, '210115', 'Vilque', 1),
(1645, 164, '210201', 'Azángaro', 1),
(1646, 164, '210202', 'Achaya', 1),
(1647, 164, '210203', 'Arapa', 1),
(1648, 164, '210204', 'Asillo', 1),
(1649, 164, '210205', 'Caminaca', 1),
(1650, 164, '210206', 'Chupa', 1),
(1651, 164, '210207', 'José Domingo Choquehuanca', 1),
(1652, 164, '210208', 'Muñani', 1),
(1653, 164, '210209', 'Potoni', 1),
(1654, 164, '210210', 'Saman', 1),
(1655, 164, '210211', 'San Anton', 1),
(1656, 164, '210212', 'San José', 1),
(1657, 164, '210213', 'San Juan de Salinas', 1),
(1658, 164, '210214', 'Santiago de Pupuja', 1),
(1659, 164, '210215', 'Tirapata', 1),
(1660, 165, '210301', 'Macusani', 1),
(1661, 165, '210302', 'Ajoyani', 1),
(1662, 165, '210303', 'Ayapata', 1),
(1663, 165, '210304', 'Coasa', 1),
(1664, 165, '210305', 'Corani', 1),
(1665, 165, '210306', 'Crucero', 1),
(1666, 165, '210307', 'Ituata', 1),
(1667, 165, '210308', 'Ollachea', 1),
(1668, 165, '210309', 'San Gaban', 1),
(1669, 165, '210310', 'Usicayos', 1),
(1670, 166, '210401', 'Juli', 1),
(1671, 166, '210402', 'Desaguadero', 1),
(1672, 166, '210403', 'Huacullani', 1),
(1673, 166, '210404', 'Kelluyo', 1),
(1674, 166, '210405', 'Pisacoma', 1),
(1675, 166, '210406', 'Pomata', 1),
(1676, 166, '210407', 'Zepita', 1),
(1677, 167, '210501', 'Ilave', 1),
(1678, 167, '210502', 'Capazo', 1),
(1679, 167, '210503', 'Pilcuyo', 1),
(1680, 167, '210504', 'Santa Rosa', 1),
(1681, 167, '210505', 'Conduriri', 1),
(1682, 168, '210601', 'Huancane', 1),
(1683, 168, '210602', 'Cojata', 1),
(1684, 168, '210603', 'Huatasani', 1),
(1685, 168, '210604', 'Inchupalla', 1),
(1686, 168, '210605', 'Pusi', 1),
(1687, 168, '210606', 'Rosaspata', 1),
(1688, 168, '210607', 'Taraco', 1),
(1689, 168, '210608', 'Vilque Chico', 1),
(1690, 169, '210701', 'Lampa', 1),
(1691, 169, '210702', 'Cabanilla', 1),
(1692, 169, '210703', 'Calapuja', 1),
(1693, 169, '210704', 'Nicasio', 1),
(1694, 169, '210705', 'Ocuviri', 1),
(1695, 169, '210706', 'Palca', 1),
(1696, 169, '210707', 'Paratia', 1),
(1697, 169, '210708', 'Pucara', 1),
(1698, 169, '210709', 'Santa Lucia', 1),
(1699, 169, '210710', 'Vilavila', 1),
(1700, 170, '210801', 'Ayaviri', 1),
(1701, 170, '210802', 'Antauta', 1),
(1702, 170, '210803', 'Cupi', 1),
(1703, 170, '210804', 'Llalli', 1),
(1704, 170, '210805', 'Macari', 1),
(1705, 170, '210806', 'Nuñoa', 1),
(1706, 170, '210807', 'Orurillo', 1),
(1707, 170, '210808', 'Santa Rosa', 1),
(1708, 170, '210809', 'Umachiri', 1),
(1709, 171, '210901', 'Moho', 1),
(1710, 171, '210902', 'Conima', 1),
(1711, 171, '210903', 'Huayrapata', 1),
(1712, 171, '210904', 'Tilali', 1),
(1713, 172, '211001', 'Putina', 1),
(1714, 172, '211002', 'Ananea', 1),
(1715, 172, '211003', 'Pedro Vilca Apaza', 1),
(1716, 172, '211004', 'Quilcapuncu', 1),
(1717, 172, '211005', 'Sina', 1),
(1718, 173, '211101', 'Juliaca', 1),
(1719, 173, '211102', 'Cabana', 1),
(1720, 173, '211103', 'Cabanillas', 1),
(1721, 173, '211104', 'Caracoto', 1),
(1722, 173, '211105', 'San Miguel', 1),
(1723, 174, '211201', 'Sandia', 1),
(1724, 174, '211202', 'Cuyocuyo', 1),
(1725, 174, '211203', 'Limbani', 1),
(1726, 174, '211204', 'Patambuco', 1),
(1727, 174, '211205', 'Phara', 1),
(1728, 174, '211206', 'Quiaca', 1),
(1729, 174, '211207', 'San Juan del Oro', 1),
(1730, 174, '211208', 'Yanahuaya', 1),
(1731, 174, '211209', 'Alto Inambari', 1),
(1732, 174, '211210', 'San Pedro de Putina Punco', 1),
(1733, 175, '211301', 'Yunguyo', 1),
(1734, 175, '211302', 'Anapia', 1),
(1735, 175, '211303', 'Copani', 1),
(1736, 175, '211304', 'Cuturapi', 1),
(1737, 175, '211305', 'Ollaraya', 1),
(1738, 175, '211306', 'Tinicachi', 1),
(1739, 175, '211307', 'Unicachi', 1),
(1740, 176, '220101', 'Moyobamba', 1),
(1741, 176, '220102', 'Calzada', 1),
(1742, 176, '220103', 'Habana', 1),
(1743, 176, '220104', 'Jepelacio', 1),
(1744, 176, '220105', 'Soritor', 1),
(1745, 176, '220106', 'Yantalo', 1),
(1746, 177, '220201', 'Bellavista', 1),
(1747, 177, '220202', 'Alto Biavo', 1),
(1748, 177, '220203', 'Bajo Biavo', 1),
(1749, 177, '220204', 'Huallaga', 1),
(1750, 177, '220205', 'San Pablo', 1),
(1751, 177, '220206', 'San Rafael', 1),
(1752, 178, '220301', 'San José de Sisa', 1),
(1753, 178, '220302', 'Agua Blanca', 1),
(1754, 178, '220303', 'San Martín', 1),
(1755, 178, '220304', 'Santa Rosa', 1),
(1756, 178, '220305', 'Shatoja', 1),
(1757, 179, '220401', 'Saposoa', 1),
(1758, 179, '220402', 'Alto Saposoa', 1),
(1759, 179, '220403', 'El Eslabón', 1),
(1760, 179, '220404', 'Piscoyacu', 1),
(1761, 179, '220405', 'Sacanche', 1),
(1762, 179, '220406', 'Tingo de Saposoa', 1),
(1763, 180, '220501', 'Lamas', 1),
(1764, 180, '220502', 'Alonso de Alvarado', 1),
(1765, 180, '220503', 'Barranquita', 1),
(1766, 180, '220504', 'Caynarachi', 1),
(1767, 180, '220505', 'Cuñumbuqui', 1),
(1768, 180, '220506', 'Pinto Recodo', 1),
(1769, 180, '220507', 'Rumisapa', 1),
(1770, 180, '220508', 'San Roque de Cumbaza', 1),
(1771, 180, '220509', 'Shanao', 1),
(1772, 180, '220510', 'Tabalosos', 1),
(1773, 180, '220511', 'Zapatero', 1),
(1774, 181, '220601', 'Juanjuí', 1),
(1775, 181, '220602', 'Campanilla', 1),
(1776, 181, '220603', 'Huicungo', 1),
(1777, 181, '220604', 'Pachiza', 1),
(1778, 181, '220605', 'Pajarillo', 1),
(1779, 182, '220701', 'Picota', 1),
(1780, 182, '220702', 'Buenos Aires', 1),
(1781, 182, '220703', 'Caspisapa', 1),
(1782, 182, '220704', 'Pilluana', 1),
(1783, 182, '220705', 'Pucacaca', 1),
(1784, 182, '220706', 'San Cristóbal', 1),
(1785, 182, '220707', 'San Hilarión', 1),
(1786, 182, '220708', 'Shamboyacu', 1),
(1787, 182, '220709', 'Tingo de Ponasa', 1),
(1788, 182, '220710', 'Tres Unidos', 1),
(1789, 183, '220801', 'Rioja', 1),
(1790, 183, '220802', 'Awajun', 1),
(1791, 183, '220803', 'Elías Soplin Vargas', 1),
(1792, 183, '220804', 'Nueva Cajamarca', 1),
(1793, 183, '220805', 'Pardo Miguel', 1),
(1794, 183, '220806', 'Posic', 1),
(1795, 183, '220807', 'San Fernando', 1),
(1796, 183, '220808', 'Yorongos', 1),
(1797, 183, '220809', 'Yuracyacu', 1),
(1798, 184, '220901', 'Tarapoto', 1),
(1799, 184, '220902', 'Alberto Leveau', 1),
(1800, 184, '220903', 'Cacatachi', 1),
(1801, 184, '220904', 'Chazuta', 1),
(1802, 184, '220905', 'Chipurana', 1),
(1803, 184, '220906', 'El Porvenir', 1),
(1804, 184, '220907', 'Huimbayoc', 1),
(1805, 184, '220908', 'Juan Guerra', 1),
(1806, 184, '220909', 'La Banda de Shilcayo', 1),
(1807, 184, '220910', 'Morales', 1),
(1808, 184, '220911', 'Papaplaya', 1),
(1809, 184, '220912', 'San Antonio', 1),
(1810, 184, '220913', 'Sauce', 1),
(1811, 184, '220914', 'Shapaja', 1),
(1812, 185, '221001', 'Tocache', 1),
(1813, 185, '221002', 'Nuevo Progreso', 1),
(1814, 185, '221003', 'Polvora', 1),
(1815, 185, '221004', 'Shunte', 1),
(1816, 185, '221005', 'Uchiza', 1),
(1817, 186, '230101', 'Tacna', 1),
(1818, 186, '230102', 'Alto de la Alianza', 1),
(1819, 186, '230103', 'Calana', 1),
(1820, 186, '230104', 'Ciudad Nueva', 1),
(1821, 186, '230105', 'Inclan', 1),
(1822, 186, '230106', 'Pachia', 1),
(1823, 186, '230107', 'Palca', 1),
(1824, 186, '230108', 'Pocollay', 1),
(1825, 186, '230109', 'Sama', 1),
(1826, 186, '230110', 'Coronel Gregorio Albarracín Lanchipa', 1),
(1827, 186, '230111', 'La Yarada los Palos', 1),
(1828, 187, '230201', 'Candarave', 1),
(1829, 187, '230202', 'Cairani', 1),
(1830, 187, '230203', 'Camilaca', 1),
(1831, 187, '230204', 'Curibaya', 1),
(1832, 187, '230205', 'Huanuara', 1),
(1833, 187, '230206', 'Quilahuani', 1),
(1834, 188, '230301', 'Locumba', 1),
(1835, 188, '230302', 'Ilabaya', 1),
(1836, 188, '230303', 'Ite', 1),
(1837, 189, '230401', 'Tarata', 1),
(1838, 189, '230402', 'Héroes Albarracín', 1),
(1839, 189, '230403', 'Estique', 1),
(1840, 189, '230404', 'Estique-Pampa', 1),
(1841, 189, '230405', 'Sitajara', 1),
(1842, 189, '230406', 'Susapaya', 1),
(1843, 189, '230407', 'Tarucachi', 1),
(1844, 189, '230408', 'Ticaco', 1),
(1845, 190, '240101', 'Tumbes', 1),
(1846, 190, '240102', 'Corrales', 1),
(1847, 190, '240103', 'La Cruz', 1),
(1848, 190, '240104', 'Pampas de Hospital', 1),
(1849, 190, '240105', 'San Jacinto', 1),
(1850, 190, '240106', 'San Juan de la Virgen', 1),
(1851, 191, '240201', 'Zorritos', 1),
(1852, 191, '240202', 'Casitas', 1),
(1853, 191, '240203', 'Canoas de Punta Sal', 1),
(1854, 192, '240301', 'Zarumilla', 1),
(1855, 192, '240302', 'Aguas Verdes', 1),
(1856, 192, '240303', 'Matapalo', 1),
(1857, 192, '240304', 'Papayal', 1),
(1858, 193, '250101', 'Calleria', 1),
(1859, 193, '250102', 'Campoverde', 1),
(1860, 193, '250103', 'Iparia', 1),
(1861, 193, '250104', 'Masisea', 1),
(1862, 193, '250105', 'Yarinacocha', 1),
(1863, 193, '250106', 'Nueva Requena', 1),
(1864, 193, '250107', 'Manantay', 1),
(1865, 194, '250201', 'Raymondi', 1),
(1866, 194, '250202', 'Sepahua', 1),
(1867, 194, '250203', 'Tahuania', 1),
(1868, 194, '250204', 'Yurua', 1),
(1869, 195, '250301', 'Padre Abad', 1),
(1870, 195, '250302', 'Irazola', 1),
(1871, 195, '250303', 'Curimana', 1),
(1872, 195, '250304', 'Neshuya', 1),
(1873, 195, '250305', 'Alexander Von Humboldt', 1),
(1874, 196, '250401', 'Purus', 1);

-- 2. Catálogo de tipos de documento de identidad personal (sin RUC)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tipos_documento` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(20) NOT NULL,
    `nombre` VARCHAR(100) NOT NULL,
    `descripcion` VARCHAR(255) NULL,
    `longitud_exacta` SMALLINT UNSIGNED NULL,
    `longitud_minima` SMALLINT UNSIGNED NULL,
    `longitud_maxima` SMALLINT UNSIGNED NULL,
    `formato_regex` VARCHAR(100) NULL,
    `pais_fijo_id` INT UNSIGNED NULL,
    `pais_emisor_obligatorio` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `activo` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_tipos_documento_pais_fijo` FOREIGN KEY (`pais_fijo_id`) REFERENCES `paises` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_tipos_documento_codigo` (`codigo`),
    INDEX `idx_tipos_documento_activo` (`activo`),
    INDEX `idx_tipos_documento_pais_fijo` (`pais_fijo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Catálogo dinámico de tipos de documento de identidad para personas naturales';

-- Semilla estructural: Tipos de documento base para personas naturales con su alcance jurisdiccional
INSERT INTO `tipos_documento` (`codigo`, `nombre`, `descripcion`, `longitud_exacta`, `longitud_minima`, `longitud_maxima`, `formato_regex`, `pais_fijo_id`, `pais_emisor_obligatorio`, `activo`) VALUES
('DNI', 'Documento Nacional de Identidad', 'Documento nacional de identidad peruano para personas naturales', 8, 8, 8, '^[0-9]{8}$', 1, 0, 1),
('PASAPORTE', 'Pasaporte', 'Documento de identidad internacional para viajes', NULL, 6, 20, '^[A-Z0-9]{6,20}$', NULL, 1, 1),
('CE', 'Carné de Extranjería', 'Documento oficial para extranjeros residentes en Perú', NULL, 6, 15, '^[A-Z0-9]{6,15}$', 1, 0, 1),
('RUC', 'Registro Único de Contribuyentes', 'Documento de identificación tributaria para personas jurídicas y naturales con negocio en Perú', 11, 11, 11, '^[0-9]{11}$', 1, 0, 1)
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `pais_fijo_id` = VALUES(`pais_fijo_id`),
    `pais_emisor_obligatorio` = VALUES(`pais_emisor_obligatorio`);

-- ----------------------------------------------------------------------------
-- 3. Maestro de personas naturales (Soporta monónimos y nombres internacionales)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `personas` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `nombres` VARCHAR(100) NOT NULL,
    `apellido_paterno` VARCHAR(100) NULL,
    `apellido_materno` VARCHAR(100) NULL,
    `genero` ENUM('MASCULINO', 'FEMENINO', 'OTRO', 'NO_ESPECIFICADO') NULL DEFAULT NULL,
    `fecha_nacimiento` DATE NULL,
    `pais_nacionalidad_id` INT UNSIGNED NULL,
    `pais_residencia_id` INT UNSIGNED NULL,
    `distrito_id` INT UNSIGNED NULL,
    `region_residencia_extranjera` VARCHAR(100) NULL DEFAULT NULL,
    `ciudad_residencia_extranjera` VARCHAR(100) NULL DEFAULT NULL,
    `direccion` VARCHAR(255) NULL,
    `foto_ruta` VARCHAR(255) NULL DEFAULT NULL,
    `estado` VARCHAR(20) NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_personas_pais_nacionalidad` FOREIGN KEY (`pais_nacionalidad_id`) REFERENCES `paises` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_personas_pais_residencia` FOREIGN KEY (`pais_residencia_id`) REFERENCES `paises` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_personas_distrito` FOREIGN KEY (`distrito_id`) REFERENCES `distritos` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_personas_nombres_no_vacio` CHECK (`nombres` <> ''),
    CONSTRAINT `chk_personas_estado_valido` CHECK (`estado` IN ('ACTIVO', 'INACTIVO')),
    INDEX `idx_personas_estado` (`estado`),
    INDEX `idx_personas_pais_nacionalidad` (`pais_nacionalidad_id`),
    INDEX `idx_personas_pais_residencia` (`pais_residencia_id`),
    INDEX `idx_personas_distrito` (`distrito_id`),
    INDEX `idx_personas_apellidos_nombres` (`apellido_paterno`, `apellido_materno`, `nombres`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Maestro central de personas naturales';

-- ----------------------------------------------------------------------------
-- 4. Documentos de identificación personal (Con jurisdicción real obligatoria)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `personas_documentos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `persona_id` BIGINT UNSIGNED NOT NULL,
    `tipo_documento_id` INT UNSIGNED NOT NULL,
    `numero_documento` VARCHAR(30) NOT NULL,
    `pais_emisor_id` INT UNSIGNED NOT NULL,
    `es_principal` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `fecha_emision` DATE NULL,
    `fecha_vencimiento` DATE NULL,
    `estado` VARCHAR(20) NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- Columna virtual generada para garantizar un único documento principal activo por persona
    `uq_persona_principal` BIGINT UNSIGNED GENERATED ALWAYS AS (IF(`es_principal` = 1 AND `estado` = 'ACTIVO', `persona_id`, NULL)) VIRTUAL,
    CONSTRAINT `fk_documentos_persona` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_documentos_tipo` FOREIGN KEY (`tipo_documento_id`) REFERENCES `tipos_documento` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_documentos_pais_emisor` FOREIGN KEY (`pais_emisor_id`) REFERENCES `paises` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_documentos_estado` CHECK (`estado` IN ('ACTIVO', 'INACTIVO')),
    CONSTRAINT `chk_documentos_numero_no_vacio` CHECK (`numero_documento` <> ''),
    -- Unicidad documental internacional: distingue por tipo, país emisor real y número
    UNIQUE KEY `uq_documentos_tipo_pais_numero` (`tipo_documento_id`, `pais_emisor_id`, `numero_documento`),
    UNIQUE KEY `uq_documentos_persona_principal` (`uq_persona_principal`),
    INDEX `idx_documentos_persona` (`persona_id`),
    INDEX `idx_documentos_tipo` (`tipo_documento_id`),
    INDEX `idx_documentos_pais_emisor` (`pais_emisor_id`),
    INDEX `idx_documentos_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Documentos de identidad asociados a personas naturales';

-- ----------------------------------------------------------------------------
-- 5. Medios de contacto de personas naturales
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `personas_contactos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `persona_id` BIGINT UNSIGNED NOT NULL,
    `tipo_contacto` VARCHAR(20) NOT NULL,
    `valor` VARCHAR(150) NOT NULL,
    `es_whatsapp` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `es_principal` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `estado` VARCHAR(20) NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- Columna virtual para garantizar un único contacto principal activo por tipo y persona
    `uq_contacto_tipo_principal` VARCHAR(50) GENERATED ALWAYS AS (IF(`es_principal` = 1 AND `estado` = 'ACTIVO', CONCAT(`persona_id`, '-', `tipo_contacto`), NULL)) VIRTUAL,
    CONSTRAINT `fk_contactos_persona` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_contactos_tipo` CHECK (`tipo_contacto` IN ('TELEFONO', 'EMAIL')),
    CONSTRAINT `chk_contactos_es_whatsapp` CHECK (`es_whatsapp` IN (0, 1)),
    CONSTRAINT `chk_contactos_es_principal` CHECK (`es_principal` IN (0, 1)),
    CONSTRAINT `chk_contactos_estado` CHECK (`estado` IN ('ACTIVO', 'INACTIVO')),
    CONSTRAINT `chk_contactos_valor_no_vacio` CHECK (`valor` <> ''),
    UNIQUE KEY `uq_contactos_tipo_principal` (`uq_contacto_tipo_principal`),
    INDEX `idx_contactos_persona` (`persona_id`),
    INDEX `idx_contactos_tipo` (`tipo_contacto`),
    INDEX `idx_contactos_valor` (`valor`),
    INDEX `idx_contactos_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Medios de contacto para personas naturales';

-- ----------------------------------------------------------------------------
-- 6. Catálogo dinámico de cargos laborales
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cargos` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL,
    `nombre` VARCHAR(100) NOT NULL,
    `descripcion` VARCHAR(255) NULL,
    `activo` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_cargos_codigo` (`codigo`),
    INDEX `idx_cargos_activo` (`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Catálogo dinámico de cargos laborales de Camargo PMS';

-- Semillas estructurales base de cargos
INSERT INTO `cargos` (`codigo`, `nombre`, `descripcion`, `activo`) VALUES
('ADMINISTRADOR', 'Administrador', 'Responsable general de operación y administración', 1),
('RECEPCIONISTA', 'Recepcionista', 'Atención directa a huéspedes, check-in y check-out', 1),
('RESERVAS', 'Encargado de Reservas', 'Gestión de canales, reservas y ocupación', 1),
('LIMPIEZA', 'Personal de Limpieza', 'Mantenimiento de áreas comunes y habitaciones', 1),
('MANTENIMIENTO', 'Personal de Mantenimiento', 'Mantenimiento técnico e infraestructura física', 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

-- ----------------------------------------------------------------------------
-- 7. Entidad Colaborador (Identidad laboral estable de una Persona)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `colaboradores` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `persona_id` BIGINT UNSIGNED NOT NULL,
    `codigo` VARCHAR(20) NOT NULL,
    `estado` VARCHAR(20) NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_colaboradores_persona` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_colaboradores_estado` CHECK (`estado` IN ('ACTIVO', 'INACTIVO')),
    CONSTRAINT `chk_colaboradores_codigo_no_vacio` CHECK (`codigo` <> ''),
    -- Cardinalidad estricta 1:1 lógica entre Persona y su registro de Colaborador
    UNIQUE KEY `uq_colaboradores_persona` (`persona_id`),
    UNIQUE KEY `uq_colaboradores_codigo` (`codigo`),
    INDEX `idx_colaboradores_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Identidad laboral estable de personas en Camargo PMS';

-- ----------------------------------------------------------------------------
-- 8. Episodios Laborales (Historial inmutable de períodos de vinculación)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `episodios_laborales` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `colaborador_id` BIGINT UNSIGNED NOT NULL,
    `fecha_inicio` DATE NOT NULL,
    `fecha_fin` DATE NULL,
    `motivo_cese` VARCHAR(255) NULL,
    `observaciones` TEXT NULL,
    `estado` VARCHAR(20) NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- Columna virtual generada para garantizar exactamente un episodio abierto activo por colaborador
    `uq_colaborador_abierto` BIGINT UNSIGNED GENERATED ALWAYS AS (IF(`fecha_fin` IS NULL AND `estado` = 'ACTIVO', `colaborador_id`, NULL)) VIRTUAL,
    CONSTRAINT `fk_episodios_colaborador` FOREIGN KEY (`colaborador_id`) REFERENCES `colaboradores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_episodios_fechas` CHECK (`fecha_fin` IS NULL OR `fecha_fin` >= `fecha_inicio`),
    CONSTRAINT `chk_episodios_estado` CHECK (`estado` IN ('ACTIVO', 'INACTIVO')),
    UNIQUE KEY `uq_episodios_colaborador_abierto` (`uq_colaborador_abierto`),
    INDEX `idx_episodios_colaborador` (`colaborador_id`),
    INDEX `idx_episodios_fechas` (`fecha_inicio`, `fecha_fin`),
    INDEX `idx_episodios_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Episodios cronológicos de vinculación laboral';

-- ----------------------------------------------------------------------------
-- 9. Historial de Asignaciones de Cargos por Episodio
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `episodios_laborales_cargos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `episodio_laboral_id` BIGINT UNSIGNED NOT NULL,
    `cargo_id` INT UNSIGNED NOT NULL,
    `fecha_inicio` DATE NOT NULL,
    `fecha_fin` DATE NULL,
    `observaciones` VARCHAR(255) NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- Columna virtual generada para garantizar exactamente un cargo abierto por episodio
    `uq_episodio_cargo_abierto` BIGINT UNSIGNED GENERATED ALWAYS AS (IF(`fecha_fin` IS NULL, `episodio_laboral_id`, NULL)) VIRTUAL,
    CONSTRAINT `fk_cargos_episodio` FOREIGN KEY (`episodio_laboral_id`) REFERENCES `episodios_laborales` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_cargos_cargo` FOREIGN KEY (`cargo_id`) REFERENCES `cargos` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_cargos_fechas` CHECK (`fecha_fin` IS NULL OR `fecha_fin` >= `fecha_inicio`),
    UNIQUE KEY `uq_cargos_episodio_abierto` (`uq_episodio_cargo_abierto`),
    INDEX `idx_cargos_episodio` (`episodio_laboral_id`),
    INDEX `idx_cargos_cargo` (`cargo_id`),
    INDEX `idx_cargos_fechas` (`fecha_inicio`, `fecha_fin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Historial inmutable de cargos ejercidos dentro de cada episodio laboral';

-- ----------------------------------------------------------------------------
-- 10. Cuentas Humanas de Usuario (AUTH-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `usuarios` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `persona_id` BIGINT UNSIGNED NOT NULL,
    `nombre_usuario` VARCHAR(50) NOT NULL,
    `contrasena_hash` VARCHAR(255) NOT NULL COMMENT 'Hash criptográfico de la contraseña (soporta PASSWORD_DEFAULT y algoritmos futuros)',
    `estado` ENUM('ACTIVO', 'BLOQUEADO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `ultimo_acceso_en` DATETIME NULL,
    `contrasena_cambiada_en` DATETIME NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `uq_usuarios_persona` UNIQUE (`persona_id`),
    CONSTRAINT `uq_usuarios_nombre_usuario` UNIQUE (`nombre_usuario`),
    CONSTRAINT `fk_usuarios_persona` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_usuarios_estado` CHECK (`estado` IN ('ACTIVO', 'BLOQUEADO', 'INACTIVO'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Cuentas de usuario de acceso para personas humanas';

-- ----------------------------------------------------------------------------
-- 11. Sesiones de Usuario Persistentes y Revocables (AUTH-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sesiones_usuario` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `usuario_id` BIGINT UNSIGNED NOT NULL,
    `token_hash` CHAR(64) NOT NULL,
    `iniciada_en` DATETIME NOT NULL,
    `ultima_actividad_en` DATETIME NOT NULL,
    `expira_en` DATETIME NOT NULL,
    `revocada_en` DATETIME NULL,
    `motivo_cierre` ENUM(
        'LOGOUT',
        'EXPIRACION_INACTIVIDAD',
        'EXPIRACION_ABSOLUTA',
        'REVOCACION_ADMINISTRATIVA',
        'CAMBIO_CONTRASENA',
        'CAMBIO_ESTADO_USUARIO',
        'DESACTIVACION_PERSONA',
        'OTRO'
    ) NULL,
    `ip` VARCHAR(45) NULL,
    `user_agent` VARCHAR(255) NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `uq_sesiones_token_hash` UNIQUE (`token_hash`),
    CONSTRAINT `fk_sesiones_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX `idx_sesiones_usuario_id` (`usuario_id`),
    INDEX `idx_sesiones_activas` (`usuario_id`, `revocada_en`, `expira_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Sesiones de usuario activas y revocadas';

-- ----------------------------------------------------------------------------
-- 12. Intentos de Autenticación (Throttling y Prevención de Fuerza Bruta)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `intentos_autenticacion` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `nombre_usuario_normalizado` VARCHAR(50) NOT NULL,
    `ip` VARCHAR(45) NOT NULL,
    `exitoso` TINYINT(1) NOT NULL DEFAULT 0,
    `intentado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_intentos_ip_fecha` (`ip`, `intentado_en`),
    INDEX `idx_intentos_usuario_fecha` (`nombre_usuario_normalizado`, `intentado_en`),
    INDEX `idx_intentos_limpieza` (`intentado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Registro de intentos de autenticación para rate limiting';

-- ----------------------------------------------------------------------------
-- 13. Roles de Autorización RBAC (ROLES-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `roles` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(50) NOT NULL,
    `nombre` VARCHAR(100) NOT NULL,
    `descripcion` VARCHAR(255) NULL,
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `es_sistema` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `es_superadministrador` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_roles_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_roles_nombre_no_vacio` CHECK (`nombre` <> ''),
    UNIQUE KEY `uq_roles_codigo` (`codigo`),
    INDEX `idx_roles_estado` (`estado`),
    INDEX `idx_roles_superadmin` (`es_superadministrador`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Roles de autorización RBAC del sistema';

-- ----------------------------------------------------------------------------
-- 14. Catálogo de Permisos Atómicos (ROLES-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `permisos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(100) NOT NULL,
    `nombre` VARCHAR(100) NOT NULL,
    `descripcion` VARCHAR(255) NULL,
    `modulo` VARCHAR(50) NOT NULL,
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `es_sistema` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_permisos_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_permisos_nombre_no_vacio` CHECK (`nombre` <> ''),
    UNIQUE KEY `uq_permisos_codigo` (`codigo`),
    INDEX `idx_permisos_modulo` (`modulo`),
    INDEX `idx_permisos_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Catálogo de permisos atómicos recurso.accion';

-- ----------------------------------------------------------------------------
-- 15. Asignación N:M de Permisos a Roles (ROLES-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `roles_permisos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `rol_id` BIGINT UNSIGNED NOT NULL,
    `permiso_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_roles_permisos_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_roles_permisos_permiso` FOREIGN KEY (`permiso_id`) REFERENCES `permisos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY `uq_roles_permisos_rol_permiso` (`rol_id`, `permiso_id`),
    INDEX `idx_roles_permisos_permiso` (`permiso_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Relación N:M de asignación de permisos a roles';

-- ----------------------------------------------------------------------------
-- 16. Asignación N:M de Roles a Usuarios (ROLES-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `usuarios_roles` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `usuario_id` BIGINT UNSIGNED NOT NULL,
    `rol_id` BIGINT UNSIGNED NOT NULL,
    `asignado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `asignado_por_usuario_id` BIGINT UNSIGNED NULL,
    CONSTRAINT `fk_usuarios_roles_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_usuarios_roles_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_usuarios_roles_asignado_por` FOREIGN KEY (`asignado_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    UNIQUE KEY `uq_usuarios_roles_usuario_rol` (`usuario_id`, `rol_id`),
    INDEX `idx_usuarios_roles_rol` (`rol_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Relación N:M de asignación de roles a usuarios';

-- ----------------------------------------------------------------------------
-- Semillas de Roles y Permisos Iniciales
-- ----------------------------------------------------------------------------
INSERT INTO `roles` (`codigo`, `nombre`, `descripcion`, `estado`, `es_sistema`, `es_superadministrador`) VALUES
('SUPERADMINISTRADOR', 'Superadministrador', 'Acceso total e irrestricto a todas las funciones y recursos del sistema', 'ACTIVO', 1, 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

INSERT INTO `permisos` (`codigo`, `nombre`, `descripcion`, `modulo`, `estado`, `es_sistema`) VALUES
('usuarios.ver', 'Ver usuarios', 'Permite consultar el listado y detalle de cuentas de usuario', 'usuarios', 'ACTIVO', 1),
('usuarios.crear', 'Crear usuarios', 'Permite registrar nuevas cuentas de usuario en el sistema', 'usuarios', 'ACTIVO', 1),
('usuarios.editar', 'Editar usuarios', 'Permite modificar datos de cuentas de usuario existentes', 'usuarios', 'ACTIVO', 1),
('usuarios.bloquear', 'Bloquear usuarios', 'Permite bloquear o desactivar cuentas de usuario', 'usuarios', 'ACTIVO', 1),
('sesiones.ver', 'Ver sesiones de usuario', 'Permite consultar el monitor y listado de sesiones de usuario en el sistema', 'seguridad', 'ACTIVO', 1),
('sesiones.revocar', 'Revocar sesiones de usuario', 'Permite revocar administrativamente sesiones activas de usuarios', 'seguridad', 'ACTIVO', 1),
('personal.ver', 'Ver directorio y legajo de personal', 'Permite consultar el catálogo y detalle del personal', 'personal', 'ACTIVO', 1),
('personal.gestionar', 'Gestionar personal y colaboradores', 'Permite registrar, actualizar y cesar personal', 'personal', 'ACTIVO', 1),
('roles.ver', 'Ver roles', 'Permite consultar los roles y sus permisos asociados', 'roles', 'ACTIVO', 1),
('roles.crear', 'Crear roles', 'Permite definir nuevos roles de autorización en el sistema', 'roles', 'ACTIVO', 1),
('roles.editar', 'Editar roles', 'Permite modificar nombres y descripciones de roles', 'roles', 'ACTIVO', 1),
('roles.asignar', 'Asignar roles', 'Permite asignar roles a cuentas de usuario', 'roles', 'ACTIVO', 1),
('roles.revocar', 'Revocar roles', 'Permite revocar roles previamente asignados a usuarios', 'roles', 'ACTIVO', 1),
('permisos.ver', 'Ver permisos', 'Permite consultar el catálogo general de permisos del sistema', 'permisos', 'ACTIVO', 1),
('menu.ver', 'Ver gestión de menú', 'Permite consultar las opciones y estructura del menú de navegación', 'menu', 'ACTIVO', 1),
('menu.gestionar', 'Gestionar opciones de menú', 'Permite crear, modificar, activar, desactivar y ordenar opciones del menú', 'menu', 'ACTIVO', 1),
('configuracion.ver', 'Ver configuraciones del sistema', 'Permite consultar los parámetros y configuraciones del sistema', 'configuracion', 'ACTIVO', 1),
('configuracion.editar', 'Modificar configuraciones del sistema', 'Permite editar y restaurar valores de configuración del sistema', 'configuracion', 'ACTIVO', 1),
('propiedades.ver', 'Ver catálogo y detalle de propiedades', 'Permite consultar el catálogo y los detalles de las propiedades', 'propiedades', 'ACTIVO', 1),
('propiedades.crear', 'Crear nuevas propiedades', 'Permite registrar nuevas propiedades en el sistema', 'propiedades', 'ACTIVO', 1),
('propiedades.editar', 'Modificar propiedades existentes', 'Permite editar la información de las propiedades', 'propiedades', 'ACTIVO', 1),
('propiedades.cambiar_estado', 'Activar o desactivar propiedades', 'Permite alternar el estado operacional entre ACTIVO e INACTIVO de una propiedad', 'propiedades', 'ACTIVO', 1),
('unidades.ver', 'Ver catálogo y detalle de unidades', 'Permite consultar el catálogo y los detalles de las unidades', 'unidades', 'ACTIVO', 1),
('unidades.crear', 'Crear nuevas unidades', 'Permite registrar nuevas unidades habitacionales en el sistema', 'unidades', 'ACTIVO', 1),
('unidades.editar', 'Modificar unidades existentes', 'Permite editar la información física y descriptiva de las unidades', 'unidades', 'ACTIVO', 1),
('unidades.cambiar_estado', 'Activar o desactivar unidades', 'Permite alternar el estado operacional entre ACTIVO e INACTIVO de una unidad', 'unidades', 'ACTIVO', 1),
('disponibilidad.ver', 'Ver disponibilidad e inventario', 'Permite consultar el calendario, estados de ocupación y unidades disponibles', 'disponibilidad', 'ACTIVO', 1),
('disponibilidad.bloquear', 'Crear bloqueos de inventario', 'Permite aplicar bloqueos manuales y técnicos sobre unidades para fechas determinadas', 'disponibilidad', 'ACTIVO', 1),
('disponibilidad.liberar', 'Liberar bloqueos de inventario', 'Permite levantar bloqueos previamente aplicados y restablecer la disponibilidad', 'disponibilidad', 'ACTIVO', 1),
('reservas.ver', 'Ver reservas', 'Permite consultar el catálogo, filtros y detalles de reservas', 'reservas', 'ACTIVO', 1),
('reservas.crear', 'Crear reservas directas', 'Permite crear nuevas reservas directas multiunidad en el sistema', 'reservas', 'ACTIVO', 1),
('reservas.confirmar', 'Confirmar reservas', 'Permite cambiar el estado de reservas de PENDIENTE a CONFIRMADA', 'reservas', 'ACTIVO', 1),
('reservas.cancelar', 'Cancelar reservas', 'Permite cancelar reservas y liberar el inventario diario asociado', 'reservas', 'ACTIVO', 1),
('reservas.expirar', 'Expirar reservas vencidas', 'Permite ejecutar la expiración manual o por comando de reservas con hold vencido', 'reservas', 'ACTIVO', 1),
('estadias.ver', 'Ver estadías y ocupación', 'Permite consultar el catálogo, filtros y detalles de estadías', 'estadias', 'ACTIVO', 1),
('estadias.checkin', 'Realizar check-in de estadías', 'Permite efectuar el check-in físico de unidades reservadas', 'estadias', 'ACTIVO', 1),
('estadias.checkout', 'Realizar check-out de estadías', 'Permite registrar la salida física y devolución de llaves de unidades en curso', 'estadias', 'ACTIVO', 1),
('estadias.huespedes', 'Gestionar huéspedes de estadía', 'Permite actualizar acompañantes y responsable en estadías en curso', 'estadias', 'ACTIVO', 1),
('estadias.anular', 'Anular check-in de estadía', 'Permite anular excepcionalmente una estadía en curso con justificación obligatoria', 'estadias', 'ACTIVO', 1),
('servicios.ver', 'Ver catálogo y consumos de servicios', 'Consultar catálogo de servicios, proveedores y consumos contratados', 'servicios', 'ACTIVO', 1),
('servicios.gestionar', 'Gestionar catálogo de servicios y proveedores', 'Crear y modificar servicios del catálogo y proveedores homologados', 'servicios', 'ACTIVO', 1),
('servicios.contratar', 'Contratar servicios y consumos', 'Registrar contratación de servicios para reservas y estadías', 'servicios', 'ACTIVO', 1),
('servicios.ejecutar', 'Ejecutar servicios contratados', 'Marcar servicios como ejecutados/entregados físicamente', 'servicios', 'ACTIVO', 1),
('servicios.cancelar', 'Cancelar contratación de servicios', 'Anular contratación de servicios con registro de motivo', 'servicios', 'ACTIVO', 1),
('caja.ver', 'Ver módulos de caja, folios y finanzas', 'Consultar folios, cargos, pagos y sesiones de caja', 'caja', 'ACTIVO', 1),
('caja.aperturar', 'Aperturar sesiones de caja', 'Iniciar turnos de caja física con fondo inicial', 'caja', 'ACTIVO', 1),
('caja.cerrar', 'Arqueo y cierre de sesiones de caja', 'Conteo de efectivo y cierre irreversible de turnos', 'caja', 'ACTIVO', 1),
('caja.movimientos', 'Registrar movimientos de caja', 'Registrar ingresos y egresos de efectivo manuales', 'caja', 'ACTIVO', 1),
('caja.cobrar', 'Registrar cobros y pagos a cuentas', 'Recibir fondos en efectivo, tarjeta o banco', 'caja', 'ACTIVO', 1),
('caja.aplicar', 'Imputar pagos a cargos específicos', 'Vincular cobros a cargos de alojamiento o servicios', 'caja', 'ACTIVO', 1),
('caja.devolver', 'Registrar devoluciones y reembolsos', 'Emitir devoluciones reales de fondos al cliente', 'caja', 'ACTIVO', 1),
('caja.reversar', 'Reversar pagos y anular cargos', 'Operaciones compensatorias y correcciones de auditoría', 'caja', 'ACTIVO', 1),
('arrendamientos.ver', 'Ver contratos de arrendamiento', 'Consultar listado, detalle y estados de arrendamientos', 'arrendamientos', 'ACTIVO', 1),
('arrendamientos.crear', 'Crear borradores de arrendamiento', 'Formular nuevos contratos con unidad, fechas y sujetos', 'arrendamientos', 'ACTIVO', 1),
('arrendamientos.activar', 'Activar contratos de arrendamiento', 'Poner en vigencia contratos y materializar bloqueos de inventario', 'arrendamientos', 'ACTIVO', 1),
('arrendamientos.gestionar', 'Gestionar sujetos y prórrogas', 'Agregar cotitulares, ocupantes y extender plazos contractuales', 'arrendamientos', 'ACTIVO', 1),
('arrendamientos.rescindir', 'Rescindir o finalizar contratos', 'Terminación anticipada con causa o cierre regular de contrato', 'arrendamientos', 'ACTIVO', 1),
('arrendamientos.generar_cargos', 'Generar cuotas periódicas de renta', 'Emitir cargos mensuales y liquidar fondos de custodia', 'arrendamientos', 'ACTIVO', 1),
('mantenimiento.ver', 'Ver panel y órdenes de mantenimiento', 'Consultar incidencias, órdenes de trabajo y estados', 'mantenimiento', 'ACTIVO', 1),
('mantenimiento.incidencias.reportar', 'Reportar incidencias', 'Registrar desperfectos físicos en propiedades o unidades', 'mantenimiento', 'ACTIVO', 1),
('mantenimiento.incidencias.gestionar', 'Gestionar incidencias', 'Evaluar, clasificar y desestimar reportes de incidencias', 'mantenimiento', 'ACTIVO', 1),
('mantenimiento.ordenes.crear', 'Crear órdenes de trabajo', 'Formular órdenes preventivas o correctivas', 'mantenimiento', 'ACTIVO', 1),
('mantenimiento.ordenes.programar', 'Programar y asignar órdenes', 'Fijar fechas, asignar técnico/proveedor y autorizar bloqueo', 'mantenimiento', 'ACTIVO', 1),
('mantenimiento.ordenes.ejecutar', 'Ejecutar órdenes de trabajo', 'Iniciar trabajos y registrar costos reales de materiales y mano de obra', 'mantenimiento', 'ACTIVO', 1),
('mantenimiento.ordenes.cerrar', 'Cerrar y aprobar órdenes', 'Finalizar trabajos, registrar notas de cierre y liberar inventario', 'mantenimiento', 'ACTIVO', 1),
('mantenimiento.ordenes.cancelar', 'Cancelar órdenes de trabajo', 'Cancelar órdenes con motivo y liberar inventario bloqueado', 'mantenimiento', 'ACTIVO', 1),
('inventario.ver', 'Ver panel y existencias de inventario', 'Consultar artículos, existencias, kardex, activos y dotaciones', 'inventario', 'ACTIVO', 1),
('inventario.articulos.gestionar', 'Gestionar catálogo de artículos', 'Crear y editar artículos y unidades de medida', 'inventario', 'ACTIVO', 1),
('inventario.ubicaciones.gestionar', 'Gestionar almacenes y ubicaciones', 'Crear y administrar almacenes, bodegas y ubicaciones', 'inventario', 'ACTIVO', 1),
('inventario.movimientos.registrar', 'Registrar movimientos de inventario', 'Registrar entradas, consumos, mermas y ajustes de stock', 'inventario', 'ACTIVO', 1),
('inventario.traslados.ejecutar', 'Ejecutar traslados entre ubicaciones', 'Transferir stock entre almacenes, unidades y custodias externas', 'inventario', 'ACTIVO', 1),
('inventario.activos.gestionar', 'Gestionar activos serializables', 'Registrar, asignar, transferir y dar de baja activos fijos', 'inventario', 'ACTIVO', 1),
('inventario.dotaciones.gestionar', 'Gestionar dotaciones estándar', 'Configurar dotaciones reglamentarias de unidades', 'inventario', 'ACTIVO', 1),
('empresa.ver', 'Ver catálogo y detalle de empresas / emisores', 'Permite consultar el maestro y detalle de empresas y emisores legales', 'empresa', 'ACTIVO', 1),
('empresa.crear', 'Crear nuevas empresas / emisores', 'Permite registrar nuevas entidades empresariales en el sistema', 'empresa', 'ACTIVO', 1),
('empresa.editar', 'Modificar datos de empresa / emisor', 'Permite actualizar datos societarios, fiscales, representante y branding', 'empresa', 'ACTIVO', 1),
('empresa.cambiar_estado', 'Activar o desactivar empresas', 'Permite cambiar el estado operativo entre ACTIVO e INACTIVO', 'empresa', 'ACTIVO', 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

-- Asignar permisos al rol SUPERADMINISTRADOR
INSERT INTO `roles_permisos` (`rol_id`, `permiso_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
CROSS JOIN `permisos` p
WHERE r.`codigo` = 'SUPERADMINISTRADOR'
  AND (
      p.`codigo` IN (
          'reservas.ver', 'reservas.crear', 'reservas.confirmar', 'reservas.cancelar', 'reservas.expirar',
          'estadias.ver', 'estadias.checkin', 'estadias.checkout', 'estadias.huespedes', 'estadias.anular',
          'servicios.ver', 'servicios.gestionar', 'servicios.contratar', 'servicios.ejecutar', 'servicios.cancelar',
          'caja.ver', 'caja.aperturar', 'caja.cerrar', 'caja.movimientos', 'caja.cobrar', 'caja.aplicar', 'caja.devolver', 'caja.reversar',
          'arrendamientos.ver', 'arrendamientos.crear', 'arrendamientos.activar', 'arrendamientos.gestionar', 'arrendamientos.rescindir', 'arrendamientos.generar_cargos'
      )
      OR p.`codigo` LIKE 'mantenimiento.%'
      OR p.`codigo` LIKE 'inventario.%'
      OR p.`modulo` = 'empresa'
      OR p.`modulo` = 'personal'
  )
ON DUPLICATE KEY UPDATE `permiso_id` = VALUES(`permiso_id`);

-- ----------------------------------------------------------------------------
-- 17. Opciones de Menú y Navegación Dinámica (MENÚ-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `opciones_menu` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `padre_id` BIGINT UNSIGNED NULL,
    `clave` VARCHAR(50) NOT NULL,
    `nombre` VARCHAR(100) NOT NULL,
    `icono` VARCHAR(100) NULL,
    `ruta` VARCHAR(255) NULL,
    `orden` INT UNSIGNED NOT NULL DEFAULT 1,
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `permiso_id` BIGINT UNSIGNED NULL,
    `es_sistema` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_opciones_menu_clave_no_vacia` CHECK (`clave` <> ''),
    CONSTRAINT `chk_opciones_menu_nombre_no_vacio` CHECK (`nombre` <> ''),
    UNIQUE KEY `uq_opciones_menu_clave` (`clave`),
    INDEX `idx_opciones_menu_padre` (`padre_id`),
    INDEX `idx_opciones_menu_permiso` (`permiso_id`),
    INDEX `idx_opciones_menu_orden` (`padre_id`, `orden`),
    INDEX `idx_opciones_menu_estado` (`estado`),
    CONSTRAINT `fk_opciones_menu_padre` FOREIGN KEY (`padre_id`) REFERENCES `opciones_menu` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_opciones_menu_permiso` FOREIGN KEY (`permiso_id`) REFERENCES `permisos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Opciones y estructura de navegación dinámica autorizada del sistema';

-- Semillas Estructurales del Menú (Nivel 1 y Nivel 2)
INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`) VALUES
(NULL, 'inicio', 'Inicio', 'fa-solid fa-house', NULL, 1, 'ACTIVO', NULL, 1),
(NULL, 'configuracion', 'Configuración', 'fa-solid fa-gear', NULL, 90, 'ACTIVO', NULL, 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `icono` = VALUES(`icono`), `orden` = VALUES(`orden`);

INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'inicio_panel', 'Panel General', 'fa-solid fa-gauge-high', '/', 1, 'ACTIVO', NULL, 1
FROM `opciones_menu` p
WHERE p.`clave` = 'inicio' AND p.`padre_id` IS NULL
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `ruta` = VALUES(`ruta`), `orden` = VALUES(`orden`);

INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'config_sistema', 'Configuración General', 'fa-solid fa-sliders', '/configuracion/sistema', 1, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'configuracion' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'configuracion.ver'
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `icono` = VALUES(`icono`), `ruta` = VALUES(`ruta`), `orden` = VALUES(`orden`), `permiso_id` = VALUES(`permiso_id`);

INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'config_menu', 'Gestión de menú', 'fa-solid fa-bars', '/configuracion/menu', 2, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'configuracion' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'menu.ver'
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `ruta` = VALUES(`ruta`), `orden` = VALUES(`orden`), `permiso_id` = VALUES(`permiso_id`);

INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'config_usuarios', 'Usuarios', 'fa-solid fa-users', '/usuarios', 3, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'configuracion' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'usuarios.ver'
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `ruta` = VALUES(`ruta`), `orden` = VALUES(`orden`), `permiso_id` = VALUES(`permiso_id`);

INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'config_roles', 'Roles y Permisos', 'fa-solid fa-shield-halved', '/configuracion/roles', 4, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'configuracion' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'roles.ver'
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `icono` = VALUES(`icono`), `ruta` = VALUES(`ruta`), `orden` = VALUES(`orden`), `permiso_id` = VALUES(`permiso_id`);

INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'config_empresa', 'Empresa / Emisor', 'fa-solid fa-building', '/empresas', 6, 'ACTIVO', perm.`id`, 0
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'configuracion' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'empresa.ver'
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `icono` = VALUES(`icono`), `ruta` = VALUES(`ruta`), `orden` = VALUES(`orden`), `permiso_id` = VALUES(`permiso_id`);

INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'config_personal', 'Personal / RR.HH.', 'fa-solid fa-user-tie', '/personal', 7, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'configuracion' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'personal.ver'
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `icono` = VALUES(`icono`), `ruta` = VALUES(`ruta`), `orden` = VALUES(`orden`), `permiso_id` = VALUES(`permiso_id`);

INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`) VALUES
(NULL, 'propiedades', 'Propiedades', 'fa-solid fa-building', NULL, 10, 'ACTIVO', NULL, 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `icono` = VALUES(`icono`), `orden` = VALUES(`orden`);

INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'propiedades_catalogo', 'Propiedades', 'fa-solid fa-building', '/propiedades', 1, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'propiedades' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'propiedades.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);

INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'unidades_catalogo', 'Unidades', 'fa-solid fa-door-open', '/unidades', 2, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'propiedades' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'unidades.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);

INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'disponibilidad_calendario', 'Disponibilidad', 'fa-solid fa-calendar-plus', '/disponibilidad', 3, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'propiedades' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'disponibilidad.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);

-- Nivel 1: Dominios Principales Alina (UI-ALINA-1A)
INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`) VALUES
(NULL, 'reservas', 'Comercial y Reservas', 'fa-solid fa-calendar-check', NULL, 20, 'ACTIVO', NULL, 1),
(NULL, 'operaciones', 'Operaciones', 'fa-solid fa-clipboard-check', NULL, 30, 'ACTIVO', NULL, 1),
(NULL, 'caja_finanzas', 'Caja y Finanzas', 'fa-solid fa-cash-register', NULL, 40, 'ACTIVO', NULL, 1),
(NULL, 'abastecimiento', 'Abastecimiento', 'fa-solid fa-boxes-stacked', NULL, 50, 'ACTIVO', NULL, 1),
(NULL, 'documentos', 'Documentos', 'fa-solid fa-file-invoice', NULL, 60, 'ACTIVO', NULL, 1),
(NULL, 'atencion_cliente', 'Atención al Cliente', 'fa-solid fa-headset', NULL, 70, 'ACTIVO', NULL, 1)
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `orden` = VALUES(`orden`);

-- Dominio 'reservas' (Comercial y Reservas)
-- Nivel 2: Opción Secundaria 'Tape Chart / Rack'
INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'tape_chart', 'Tape Chart / Rack', 'fa-solid fa-table-cells', '/tape-chart', 1, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'reservas' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'disponibilidad.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);

-- Nivel 2: Opción Secundaria 'Gestión de Reservas'
INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'reservas_catalogo', 'Reservas', 'fa-solid fa-book-bookmark', '/reservas', 2, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'reservas' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'reservas.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);

-- Nivel 2: Opción Secundaria 'Arrendamientos'
INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'arrendamientos_catalogo', 'Arrendamientos', 'fa-solid fa-file-signature', '/arrendamientos', 4, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'reservas' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'arrendamientos.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);

-- Dominio 'operaciones' (Operaciones)
-- Nivel 2: Opción Secundaria 'Estadías / Check-in'
INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'estadias_catalogo', 'Estadías / Check-in', 'fa-solid fa-key', '/estadias', 1, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'operaciones' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'estadias.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);

-- Nivel 2: Opción Secundaria 'Servicios y Consumos'
INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'servicios_catalogo', 'Servicios y Consumos', 'fa-solid fa-bell-concierge', '/servicios', 2, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'operaciones' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'servicios.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);

-- Nivel 2: Opción Secundaria 'Mantenimiento'
INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'mantenimiento_catalogo', 'Mantenimiento', 'fa-solid fa-wrench', '/mantenimiento', 4, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'operaciones' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'mantenimiento.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);

-- Dominio 'caja_finanzas' (Caja y Finanzas)
-- Nivel 2: Opción Secundaria 'Caja y Cuentas'
INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'caja_cuentas', 'Caja y Cuentas', 'fa-solid fa-cash-register', '/caja', 1, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'caja_finanzas' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'caja.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);

-- Dominio 'abastecimiento' (Abastecimiento)
-- Nivel 2: Opción Secundaria 'Inventario'
INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'inventario_catalogo', 'Inventario', 'fa-solid fa-boxes-stacked', '/inventario', 1, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'abastecimiento' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'inventario.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);

-- ----------------------------------------------------------------------------
-- 11. Actores y principales del sistema para auditoría y trazabilidad
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `actores` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tipo` ENUM('USUARIO', 'SISTEMA', 'INTEGRACION', 'PROVEEDOR_PAGO') NOT NULL,
    `codigo` VARCHAR(60) NOT NULL,
    `nombre` VARCHAR(150) NOT NULL,
    `usuario_id` BIGINT UNSIGNED NULL,
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_actores_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_actores_nombre_no_vacio` CHECK (`nombre` <> ''),
    UNIQUE KEY `uq_actores_codigo` (`codigo`),
    UNIQUE KEY `uq_actores_usuario_id` (`usuario_id`),
    INDEX `idx_actores_tipo` (`tipo`),
    INDEX `idx_actores_estado` (`estado`),
    CONSTRAINT `fk_actores_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Actores y principales del sistema para auditoría y trazabilidad';

-- Semilla estructural del actor de sistema
INSERT INTO `actores` (`tipo`, `codigo`, `nombre`, `usuario_id`, `estado`) VALUES
('SISTEMA', 'CAMARGO_PMS', 'Camargo PMS — Sistema Central', NULL, 'ACTIVO')
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `estado` = VALUES(`estado`);

-- Vincular cuentas de usuario existentes con su actor humano correspondiente
INSERT INTO `actores` (`tipo`, `codigo`, `nombre`, `usuario_id`, `estado`)
SELECT 'USUARIO', CONCAT('USR_', u.`id`), u.`nombre_usuario`, u.`id`, 'ACTIVO'
FROM `usuarios` u
WHERE NOT EXISTS (
    SELECT 1 FROM `actores` a WHERE a.`usuario_id` = u.`id`
);

-- ----------------------------------------------------------------------------
-- 12. Registro histórico inmutable de auditoría transversal y trazabilidad
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `auditoria` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `actor_id` BIGINT UNSIGNED NOT NULL,
    `usuario_id` BIGINT UNSIGNED NULL,
    `accion` VARCHAR(60) NOT NULL,
    `modulo` VARCHAR(60) NOT NULL,
    `entidad` VARCHAR(60) NOT NULL,
    `entidad_id` VARCHAR(100) NULL,
    `descripcion` TEXT NULL,
    `valores_anteriores` JSON NULL,
    `valores_nuevos` JSON NULL,
    `contexto` JSON NULL,
    `ip` VARCHAR(45) NULL,
    `user_agent` VARCHAR(500) NULL,
    `correlacion_id` VARCHAR(64) NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_auditoria_accion_no_vacia` CHECK (`accion` <> ''),
    CONSTRAINT `chk_auditoria_modulo_no_vacio` CHECK (`modulo` <> ''),
    CONSTRAINT `chk_auditoria_entidad_no_vacia` CHECK (`entidad` <> ''),
    INDEX `idx_auditoria_actor_id` (`actor_id`),
    INDEX `idx_auditoria_usuario_id` (`usuario_id`),
    INDEX `idx_auditoria_accion` (`accion`),
    INDEX `idx_auditoria_modulo` (`modulo`),
    INDEX `idx_auditoria_entidad` (`entidad`, `entidad_id`),
    INDEX `idx_auditoria_correlacion_id` (`correlacion_id`),
    INDEX `idx_auditoria_creado_en` (`creado_en`),
    CONSTRAINT `fk_auditoria_actor` FOREIGN KEY (`actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_auditoria_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Registro histórico inmutable de auditoría transversal y trazabilidad';

-- ----------------------------------------------------------------------------
-- 13. Parámetros funcionales y configuración del sistema (CONFIGURACIÓN-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `configuraciones` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `clave` VARCHAR(100) NOT NULL,
    `grupo` VARCHAR(50) NOT NULL,
    `nombre` VARCHAR(100) NOT NULL,
    `descripcion` TEXT NULL,
    `tipo` ENUM('TEXTO', 'ENTERO', 'DECIMAL', 'BOOLEANO', 'FECHA', 'HORA', 'JSON') NOT NULL DEFAULT 'TEXTO',
    `valor` LONGTEXT NULL,
    `valor_predeterminado` LONGTEXT NULL,
    `editable` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `es_sensible` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `orden` INT UNSIGNED NOT NULL DEFAULT 1,
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_configuraciones_clave_no_vacia` CHECK (`clave` <> ''),
    CONSTRAINT `chk_configuraciones_nombre_no_vacio` CHECK (`nombre` <> ''),
    CONSTRAINT `chk_configuraciones_grupo_no_vacio` CHECK (`grupo` <> ''),
    UNIQUE KEY `uq_configuraciones_clave` (`clave`),
    INDEX `idx_configuraciones_grupo` (`grupo`),
    INDEX `idx_configuraciones_estado` (`estado`),
    INDEX `idx_configuraciones_orden` (`grupo`, `orden`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Parámetros funcionales y configuración del sistema';

-- Semillas Estructurales de Parámetros de Configuración
INSERT INTO `configuraciones` (`clave`, `grupo`, `nombre`, `descripcion`, `tipo`, `valor`, `valor_predeterminado`, `editable`, `es_sensible`, `orden`, `estado`) VALUES
('sistema.nombre', 'GENERAL', 'Nombre del Sistema', 'Nombre visible del PMS para encabezados y comunicaciones', 'TEXTO', 'Camargo Hostelería', 'Camargo Hostelería', 1, 0, 1, 'ACTIVO'),
('sistema.descripcion', 'GENERAL', 'Descripción del Sistema', 'Lema o descripción general de la organización hotelera', 'TEXTO', 'Gestión Hotelera y Extrahotelera', 'Gestión Hotelera y Extrahotelera', 1, 0, 2, 'ACTIVO'),
('sistema.version_instalada', 'GENERAL', 'Versión Instalada', 'Versión de software del núcleo Camargo PMS (parámetro protegido)', 'TEXTO', '1.0.0', '1.0.0', 0, 0, 99, 'ACTIVO'),
('sistema.idioma', 'LOCALIZACION', 'Idioma Principal', 'Código de idioma predeterminado para la interfaz de usuario (ISO 639-1)', 'TEXTO', 'es', 'es', 1, 0, 1, 'ACTIVO'),
('sistema.formato_fecha', 'LOCALIZACION', 'Formato de Visualización de Fecha', 'Patrón visual estándar para representación de fechas', 'TEXTO', 'd/m/Y', 'd/m/Y', 1, 0, 2, 'ACTIVO'),
('sistema.formato_hora', 'LOCALIZACION', 'Formato de Visualización de Hora', 'Patrón visual estándar para representación horaria', 'TEXTO', 'H:i', 'H:i', 1, 0, 3, 'ACTIVO'),
('operacion.modo_mantenimiento', 'OPERACION', 'Modo Mantenimiento', 'Indica si el sistema se encuentra en ventana de mantenimiento operativo', 'BOOLEANO', '0', '0', 1, 0, 1, 'ACTIVO'),
('operacion.paginacion_predeterminada', 'OPERACION', 'Paginación Predeterminada', 'Cantidad de registros predeterminada por página en listados administrativos', 'ENTERO', '15', '15', 1, 0, 2, 'ACTIVO'),
('operacion.zona_horaria_predeterminada', 'OPERACION', 'Zona Horaria Predeterminada', 'Identificador IANA de la zona horaria central del sistema (ej. America/Lima)', 'TEXTO', 'America/Lima', 'America/Lima', 1, 0, 5, 'ACTIVO'),
('reservas.duracion_hold_minutos', 'OPERACION', 'Duración de Hold para Reservas Pendientes (minutos)', 'Tiempo en minutos que una reserva en estado PENDIENTE retiene el inventario antes de expirar automáticamente (requiere configuración operacional explícita previa)', 'ENTERO', NULL, NULL, 1, 0, 6, 'ACTIVO')
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `descripcion` = VALUES(`descripcion`),
    `tipo` = VALUES(`tipo`),
    `editable` = VALUES(`editable`),
    `es_sensible` = VALUES(`es_sensible`),
    `orden` = VALUES(`orden`);

-- ----------------------------------------------------------------------------
-- 13. Maestro de Empresas Operadoras y Emisores Legales (EMPRESA-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `empresas` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(50) NOT NULL COMMENT 'Código técnico único, ej. EMP-001 o CAMARGO-HOSTELERIA',
    `tipo_documento_id` INT UNSIGNED NOT NULL COMMENT 'FK a tipos_documento (ej. RUC)',
    `numero_documento` VARCHAR(30) NOT NULL COMMENT 'Número de identificación fiscal (ej. RUC de 11 dígitos)',
    `razon_social` VARCHAR(255) NOT NULL COMMENT 'Razón social formal legal',
    `nombre_comercial` VARCHAR(255) NULL COMMENT 'Nombre comercial / marca para difusión y branding',
    `direccion_fiscal` VARCHAR(255) NOT NULL COMMENT 'Domicilio fiscal formal',
    `pais_id` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'FK a paises (1 = Perú)',
    `departamento` VARCHAR(100) NULL,
    `provincia` VARCHAR(100) NULL,
    `distrito` VARCHAR(100) NULL,
    `ubigeo` VARCHAR(10) NULL COMMENT 'Código de ubigeo 6 dígitos',
    `telefono` VARCHAR(50) NULL COMMENT 'Teléfono de contacto institucional',
    `email` VARCHAR(150) NULL COMMENT 'Correo electrónico corporativo / facturación',
    `sitio_web` VARCHAR(255) NULL COMMENT 'Portal web oficial',
    `logo_url` VARCHAR(255) NULL COMMENT 'Ruta relativa del logo corporativo almacenado en storage',
    `representante_persona_id` BIGINT UNSIGNED NULL COMMENT 'FK al registro central de personas para el representante legal',
    `representante_cargo` VARCHAR(100) NULL DEFAULT 'Gerente General',
    `representante_poder_partida` VARCHAR(100) NULL COMMENT 'Partida registral / poder notarial de representación',
    `es_principal` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT '1 si es la empresa operadora principal/default del sistema',
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `observaciones` TEXT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_empresas_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_empresas_razon_social_no_vacia` CHECK (`razon_social` <> ''),
    CONSTRAINT `chk_empresas_num_doc_no_vacio` CHECK (`numero_documento` <> ''),
    CONSTRAINT `chk_empresas_dir_fiscal_no_vacia` CHECK (`direccion_fiscal` <> ''),
    CONSTRAINT `chk_empresas_es_principal` CHECK (`es_principal` IN (0, 1)),
    CONSTRAINT `fk_empresas_tipo_documento` FOREIGN KEY (`tipo_documento_id`) REFERENCES `tipos_documento` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_empresas_pais` FOREIGN KEY (`pais_id`) REFERENCES `paises` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_empresas_representante` FOREIGN KEY (`representante_persona_id`) REFERENCES `personas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_empresas_codigo` (`codigo`),
    UNIQUE KEY `uq_empresas_num_doc` (`numero_documento`),
    INDEX `idx_empresas_estado` (`estado`),
    INDEX `idx_empresas_principal` (`es_principal`),
    INDEX `idx_empresas_representante` (`representante_persona_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Maestro central de empresas operadoras y emisores legales';

-- ----------------------------------------------------------------------------
-- 14. Maestro de Propiedades e Inmuebles Físicos (PROPIEDADES-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `propiedades` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `empresa_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `codigo` VARCHAR(50) NOT NULL,
    `nombre` VARCHAR(150) NOT NULL,
    `descripcion` TEXT NULL,
    `pais_id` INT UNSIGNED NOT NULL,
    `departamento` VARCHAR(100) NULL,
    `provincia` VARCHAR(100) NULL,
    `distrito` VARCHAR(100) NULL,
    `direccion` VARCHAR(255) NOT NULL,
    `zona_horaria` VARCHAR(50) NULL DEFAULT NULL,
    `referencia` VARCHAR(255) NULL,
    `latitud` DECIMAL(10, 7) NULL,
    `longitud` DECIMAL(10, 7) NULL,
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `observaciones` TEXT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_propiedades_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_propiedades_nombre_no_vacio` CHECK (`nombre` <> ''),
    CONSTRAINT `chk_propiedades_direccion_no_vacia` CHECK (`direccion` <> ''),
    CONSTRAINT `chk_propiedades_latitud` CHECK (`latitud` IS NULL OR (`latitud` >= -90.0000000 AND `latitud` <= 90.0000000)),
    CONSTRAINT `chk_propiedades_longitud` CHECK (`longitud` IS NULL OR (`longitud` >= -180.0000000 AND `longitud` <= 180.0000000)),
    CONSTRAINT `fk_propiedades_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_propiedades_pais` FOREIGN KEY (`pais_id`) REFERENCES `paises` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_propiedades_codigo` (`codigo`),
    INDEX `idx_propiedades_empresa` (`empresa_id`),
    INDEX `idx_propiedades_pais_id` (`pais_id`),
    INDEX `idx_propiedades_estado` (`estado`),
    INDEX `idx_propiedades_nombre` (`nombre`),
    INDEX `idx_propiedades_departamento` (`departamento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Maestro de propiedades e inmuebles físicos administrados por Camargo PMS';

-- ----------------------------------------------------------------------------
-- 14. Catálogo de Tipos de Unidad (UNIDADES-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tipos_unidad` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL,
    `nombre` VARCHAR(100) NOT NULL,
    `descripcion` VARCHAR(255) NULL,
    `activo` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_tipos_unidad_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_tipos_unidad_nombre_no_vacio` CHECK (`nombre` <> ''),
    UNIQUE KEY `uq_tipos_unidad_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Catálogo de tipologías arquitectónicas de unidades alojables';

INSERT INTO `tipos_unidad` (`codigo`, `nombre`, `descripcion`, `activo`) VALUES
('DEPARTAMENTO', 'Departamento', 'Unidad habitacional independiente en edificio o condominio', 1),
('HABITACION', 'Habitación', 'Espacio privado para alojamiento dentro de un inmueble', 1),
('CASA', 'Casa', 'Inmueble unifamiliar completo o vivienda independiente', 1),
('SUITE', 'Suite', 'Habitación premium con sala de estar o ambientes integrados', 1),
('BUNGALOW', 'Bungalow', 'Cabaña o módulo campestre/playero independiente', 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

-- ----------------------------------------------------------------------------
-- 15. Maestro de Unidades Físicas y Alojables (UNIDADES-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `unidades` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `propiedad_id` BIGINT UNSIGNED NOT NULL,
    `tipo_unidad_id` INT UNSIGNED NOT NULL,
    `codigo` VARCHAR(50) NOT NULL,
    `nombre` VARCHAR(150) NOT NULL,
    `descripcion` TEXT NULL,
    `piso_nivel` VARCHAR(30) NULL,
    `capacidad_personas` INT UNSIGNED NOT NULL DEFAULT 1,
    `dormitorios` INT UNSIGNED NOT NULL DEFAULT 1,
    `banos` DECIMAL(3, 1) NOT NULL DEFAULT 1.0,
    `area_m2` DECIMAL(8, 2) NULL,
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `observaciones` TEXT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_unidades_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_unidades_nombre_no_vacio` CHECK (`nombre` <> ''),
    CONSTRAINT `chk_unidades_capacidad_positiva` CHECK (`capacidad_personas` >= 1),
    CONSTRAINT `chk_unidades_dormitorios` CHECK (`dormitorios` >= 0),
    CONSTRAINT `chk_unidades_banos` CHECK (`banos` >= 0.0),
    CONSTRAINT `chk_unidades_area_positiva` CHECK (`area_m2` IS NULL OR `area_m2` > 0.00),
    CONSTRAINT `fk_unidades_propiedad` FOREIGN KEY (`propiedad_id`) REFERENCES `propiedades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_unidades_tipo` FOREIGN KEY (`tipo_unidad_id`) REFERENCES `tipos_unidad` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_unidades_propiedad_codigo` (`propiedad_id`, `codigo`),
    INDEX `idx_unidades_propiedad_id` (`propiedad_id`),
    INDEX `idx_unidades_tipo_unidad_id` (`tipo_unidad_id`),
    INDEX `idx_unidades_estado` (`estado`),
    INDEX `idx_unidades_codigo` (`codigo`),
    INDEX `idx_unidades_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Maestro de unidades físicas habitacionales y alojables';

-- ----------------------------------------------------------------------------
-- 16. Maestro de Bloqueos de Unidad (DISPONIBILIDAD-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bloqueos_unidad` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `unidad_id` BIGINT UNSIGNED NOT NULL,
    `fecha_inicio` DATE NOT NULL,
    `fecha_fin` DATE NOT NULL,
    `noches` INT UNSIGNED NOT NULL,
    `motivo` VARCHAR(255) NOT NULL,
    `tipo` ENUM('BLOQUEO_MANUAL', 'MANTENIMIENTO') NOT NULL DEFAULT 'BLOQUEO_MANUAL',
    `estado` ENUM('ACTIVO', 'LIBERADO') NOT NULL DEFAULT 'ACTIVO',
    `creado_por_actor_id` BIGINT UNSIGNED NULL,
    `liberado_por_actor_id` BIGINT UNSIGNED NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `liberado_en` DATETIME NULL,
    CONSTRAINT `chk_bloqueos_fechas` CHECK (`fecha_fin` > `fecha_inicio`),
    CONSTRAINT `chk_bloqueos_noches` CHECK (`noches` >= 1),
    CONSTRAINT `chk_bloqueos_motivo_no_vacio` CHECK (`motivo` <> ''),
    CONSTRAINT `fk_bloqueos_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_bloqueos_actor_creador` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_bloqueos_actor_liberador` FOREIGN KEY (`liberado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_bloqueos_unidad_id` (`unidad_id`),
    INDEX `idx_bloqueos_estado` (`estado`),
    INDEX `idx_bloqueos_rango` (`fecha_inicio`, `fecha_fin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Registro maestro de bloqueos administrativos y técnicos de unidades';

-- ----------------------------------------------------------------------------
-- 17. Inventario Diario de Unidades - Modelo Sparse (DISPONIBILIDAD-1 / D-067)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inventario_diario_unidades` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `unidad_id` BIGINT UNSIGNED NOT NULL,
    `fecha` DATE NOT NULL,
    `tipo_bloqueo` ENUM('BLOQUEO_MANUAL', 'MANTENIMIENTO', 'RESERVA', 'ARRENDAMIENTO') NOT NULL DEFAULT 'BLOQUEO_MANUAL',
    `origen_tipo` VARCHAR(50) NOT NULL DEFAULT 'BLOQUEO_MANUAL',
    `origen_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_inventario_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_inventario_unidad_fecha` (`unidad_id`, `fecha`),
    INDEX `idx_inventario_fecha` (`fecha`),
    INDEX `idx_inventario_origen` (`origen_tipo`, `origen_id`),
    INDEX `idx_inventario_unidad_fecha` (`unidad_id`, `fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Inventario diario sparse de ocupación y bloqueos por noche y unidad';

-- ----------------------------------------------------------------------------
-- 18. Maestro de Reservas Directas (RESERVAS-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reservas` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL,
    `persona_titular_id` BIGINT UNSIGNED NOT NULL,
    `fecha_entrada` DATE NOT NULL,
    `fecha_salida` DATE NOT NULL,
    `noches` INT UNSIGNED NOT NULL,
    `estado` ENUM('PENDIENTE', 'CONFIRMADA', 'CANCELADA', 'EXPIRADA') NOT NULL DEFAULT 'PENDIENTE',
    `expira_en` DATETIME NULL COMMENT 'Instante técnico absoluto de vencimiento de hold para reservas PENDIENTES',
    `canal` ENUM('PMS', 'WEB', 'APP', 'OTA') NOT NULL DEFAULT 'PMS',
    `origen` VARCHAR(50) NOT NULL DEFAULT 'DIRECTO',
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `subtotal` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `impuesto_total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `observaciones` TEXT NULL,
    `motivo_cancelacion` VARCHAR(255) NULL,
    `cancelada_en` DATETIME NULL,
    `cancelada_por_actor_id` BIGINT UNSIGNED NULL,
    `confirmada_en` DATETIME NULL,
    `confirmada_por_actor_id` BIGINT UNSIGNED NULL,
    `creado_por_actor_id` BIGINT UNSIGNED NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_reservas_persona_titular` FOREIGN KEY (`persona_titular_id`) REFERENCES `personas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_reservas_actor_creador` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_reservas_actor_confirmador` FOREIGN KEY (`confirmada_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_reservas_actor_cancelador` FOREIGN KEY (`cancelada_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `chk_reservas_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_reservas_fechas` CHECK (`fecha_salida` > `fecha_entrada`),
    CONSTRAINT `chk_reservas_noches` CHECK (`noches` >= 1),
    CONSTRAINT `chk_reservas_subtotal_no_negativo` CHECK (`subtotal` >= 0.00),
    CONSTRAINT `chk_reservas_impuesto_no_negativo` CHECK (`impuesto_total` >= 0.00),
    CONSTRAINT `chk_reservas_total_no_negativo` CHECK (`total` >= 0.00),
    UNIQUE KEY `uq_reservas_codigo` (`codigo`),
    INDEX `idx_reservas_persona_titular` (`persona_titular_id`),
    INDEX `idx_reservas_estado` (`estado`),
    INDEX `idx_reservas_fechas` (`fecha_entrada`, `fecha_salida`),
    INDEX `idx_reservas_expira_en` (`expira_en`),
    INDEX `idx_reservas_canal` (`canal`),
    INDEX `idx_reservas_creado_en` (`creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Registro maestro de reservas directas y contratos comerciales';

-- ----------------------------------------------------------------------------
-- 19. Asignación Multiunidad y Snapshot Económico (RESERVAS-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reserva_unidades` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `reserva_id` BIGINT UNSIGNED NOT NULL,
    `unidad_id` BIGINT UNSIGNED NOT NULL,
    `precio_unitario_noche` DECIMAL(15,2) NOT NULL,
    `noches` INT UNSIGNED NOT NULL,
    `subtotal` DECIMAL(15,2) NOT NULL,
    `impuesto` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total` DECIMAL(15,2) NOT NULL,
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_reserva_unidades_reserva` FOREIGN KEY (`reserva_id`) REFERENCES `reservas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_reserva_unidades_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_reserva_unidades_noches` CHECK (`noches` >= 1),
    CONSTRAINT `chk_reserva_unidades_precio` CHECK (`precio_unitario_noche` >= 0.00),
    CONSTRAINT `chk_reserva_unidades_subtotal` CHECK (`subtotal` >= 0.00),
    CONSTRAINT `chk_reserva_unidades_impuesto` CHECK (`impuesto` >= 0.00),
    CONSTRAINT `chk_reserva_unidades_total` CHECK (`total` >= 0.00),
    UNIQUE KEY `uq_reserva_unidad` (`reserva_id`, `unidad_id`),
    INDEX `idx_reserva_unidades_reserva` (`reserva_id`),
    INDEX `idx_reserva_unidades_unidad` (`unidad_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Detalle de unidades asignadas y snapshot económico por unidad';

-- ----------------------------------------------------------------------------
-- 20. Estadías y Ocupación Física de Unidades (ESTADÍAS-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `estadias` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL,
    `reserva_id` BIGINT UNSIGNED NOT NULL,
    `reserva_unidad_id` BIGINT UNSIGNED NOT NULL,
    `unidad_id` BIGINT UNSIGNED NOT NULL,
    `fecha_entrada` DATE NOT NULL COMMENT 'Fecha hotelera local de entrada (D-066)',
    `fecha_salida_prevista` DATE NOT NULL COMMENT 'Fecha hotelera local prevista de salida (D-066)',
    `estado` ENUM('EN_CURSO', 'FINALIZADA', 'ANULADA') NOT NULL DEFAULT 'EN_CURSO',
    `checkin_en` DATETIME NOT NULL COMMENT 'Instante técnico UTC real de check-in',
    `checkin_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `checkout_en` DATETIME NULL COMMENT 'Instante técnico UTC real de check-out',
    `checkout_por_actor_id` BIGINT UNSIGNED NULL,
    `identificador_llave` VARCHAR(50) NULL COMMENT 'Código de tarjeta magnética o número de llave física',
    `observaciones_checkin` TEXT NULL,
    `observaciones_checkout` TEXT NULL,
    `motivo_anulacion` VARCHAR(255) NULL,
    `anulada_en` DATETIME NULL COMMENT 'Instante técnico UTC real de anulación',
    `anulada_por_actor_id` BIGINT UNSIGNED NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_estadias_reserva` FOREIGN KEY (`reserva_id`) REFERENCES `reservas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_estadias_reserva_unidad` FOREIGN KEY (`reserva_unidad_id`) REFERENCES `reserva_unidades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_estadias_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_estadias_actor_checkin` FOREIGN KEY (`checkin_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_estadias_actor_checkout` FOREIGN KEY (`checkout_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_estadias_actor_anulador` FOREIGN KEY (`anulada_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `chk_estadias_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_estadias_fechas` CHECK (`fecha_salida_prevista` > `fecha_entrada`),
    UNIQUE KEY `uq_estadias_codigo` (`codigo`),
    UNIQUE KEY `uq_estadias_reserva_unidad` (`reserva_unidad_id`),
    INDEX `idx_estadias_reserva_id` (`reserva_id`),
    INDEX `idx_estadias_unidad_id` (`unidad_id`),
    INDEX `idx_estadias_estado` (`estado`),
    INDEX `idx_estadias_fechas` (`fecha_entrada`, `fecha_salida_prevista`),
    INDEX `idx_estadias_checkin_en` (`checkin_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Registro operativo de ocupación física real de unidades (Check-in / Check-out)';

-- ----------------------------------------------------------------------------
-- 21. Huéspedes y Acompañantes de la Estadía (ESTADÍAS-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `estadia_huespedes` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `estadia_id` BIGINT UNSIGNED NOT NULL,
    `persona_id` BIGINT UNSIGNED NOT NULL,
    `es_responsable` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 si es el huésped responsable de la estadía (exactamente 1 por estadía)',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_estadia_huespedes_estadia` FOREIGN KEY (`estadia_id`) REFERENCES `estadias` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_estadia_huespedes_persona` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_estadia_persona` (`estadia_id`, `persona_id`),
    INDEX `idx_estadia_huespedes_estadia` (`estadia_id`),
    INDEX `idx_estadia_huespedes_persona` (`persona_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Huéspedes y acompañantes alojados físicamente en la unidad';

-- ----------------------------------------------------------------------------
-- 22. Maestro Extensible de Categorías de Servicio (SERVICIOS-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categorias_servicio` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(50) NOT NULL COMMENT 'Código único estable, ej. TRASLADOS',
    `nombre` VARCHAR(100) NOT NULL COMMENT 'Nombre descriptivo visible',
    `descripcion` TEXT NULL,
    `icono` VARCHAR(50) NOT NULL DEFAULT 'fa-solid fa-bell-concierge' COMMENT'Icono Font Awesome 6 (D-071)',
    `orden` INT UNSIGNED NOT NULL DEFAULT 0,
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_categorias_servicio_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_categorias_servicio_nombre_no_vacio` CHECK (`nombre` <> ''),
    UNIQUE KEY `uq_categorias_servicio_codigo` (`codigo`),
    INDEX `idx_categorias_servicio_estado` (`estado`),
    INDEX `idx_categorias_servicio_orden` (`orden`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Catálogo administrable de categorías de servicios adicionales';

-- ----------------------------------------------------------------------------
-- 23. Maestro Extensible de Modalidades de Cobro (SERVICIOS-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `modalidades_cobro_servicio` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(50) NOT NULL COMMENT 'Código canónico: FIJO, POR_PERSONA, POR_NOCHE, POR_DIA, POR_UNIDAD, POR_UNIDAD_CONSUMO',
    `nombre` VARCHAR(100) NOT NULL,
    `descripcion` TEXT NULL,
    `unidad_medida_sugerida` VARCHAR(30) NULL COMMENT 'Sugerencia para UI: servicio, persona, noche, día, unidad, etc.',
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_modalidades_cobro_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_modalidades_cobro_nombre_no_vacio` CHECK (`nombre` <> ''),
    UNIQUE KEY `uq_modalidades_cobro_codigo` (`codigo`),
    INDEX `idx_modalidades_cobro_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Catálogo administrable de modalidades de cobro y tarificación';

-- ----------------------------------------------------------------------------
-- 24. Maestro de Proveedores Externos (SERVICIOS-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `proveedores` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(50) NOT NULL COMMENT 'Código único de negocio, ej. PROV-001',
    `tipo` ENUM('EMPRESA', 'PERSONA_NATURAL') NOT NULL DEFAULT 'EMPRESA',
    `persona_id` BIGINT UNSIGNED NULL COMMENT 'FK a personas si es persona natural del registro central',
    `razon_social` VARCHAR(200) NOT NULL COMMENT 'Razón social formal o nombre legal',
    `nombre_comercial` VARCHAR(200) NULL COMMENT 'Nombre de fantasía o marca',
    `numero_documento` VARCHAR(30) NULL COMMENT 'RUC de 11 dígitos u otro documento fiscal',
    `email` VARCHAR(150) NULL,
    `telefono` VARCHAR(50) NULL,
    `direccion` TEXT NULL,
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `observaciones` TEXT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_proveedores_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_proveedores_razon_social_no_vacia` CHECK (`razon_social` <> ''),
    CONSTRAINT `fk_proveedores_persona` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_proveedores_codigo` (`codigo`),
    INDEX `idx_proveedores_tipo` (`tipo`),
    INDEX `idx_proveedores_estado` (`estado`),
    INDEX `idx_proveedores_documento` (`numero_documento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Maestro de proveedores externos de servicios (empresas y personas naturales)';

-- ----------------------------------------------------------------------------
-- 25. Catálogo Maestro de Servicios (SERVICIOS-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `servicios` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(50) NOT NULL COMMENT 'Código único del servicio, ej. SERV-TRF-AERO',
    `categoria_id` INT UNSIGNED NOT NULL,
    `modalidad_cobro_id` INT UNSIGNED NOT NULL,
    `propiedad_id` BIGINT UNSIGNED NULL COMMENT 'NULL si es global, o ID específico si aplica solo a un predio',
    `nombre` VARCHAR(150) NOT NULL,
    `descripcion` TEXT NULL,
    `precio_venta_referencial` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    `costo_referencial` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `es_operacion_interna_habitual` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 si habitualmente lo presta Camargo con staff propio',
    `requiere_traslado_detalle` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 si exige captura operativa en servicio_traslados',
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_servicios_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_servicios_nombre_no_vacio` CHECK (`nombre` <> ''),
    CONSTRAINT `chk_servicios_precio_no_negativo` CHECK (`precio_venta_referencial` >= 0.00),
    CONSTRAINT `chk_servicios_costo_no_negativo` CHECK (`costo_referencial` >= 0.00),
    CONSTRAINT `fk_servicios_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias_servicio` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_servicios_modalidad` FOREIGN KEY (`modalidad_cobro_id`) REFERENCES `modalidades_cobro_servicio` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_servicios_propiedad` FOREIGN KEY (`propiedad_id`) REFERENCES `propiedades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_servicios_codigo` (`codigo`),
    INDEX `idx_servicios_categoria` (`categoria_id`),
    INDEX `idx_servicios_modalidad` (`modalidad_cobro_id`),
    INDEX `idx_servicios_propiedad` (`propiedad_id`),
    INDEX `idx_servicios_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Catálogo maestro de conceptos de servicios adicionales';

-- ----------------------------------------------------------------------------
-- 26. Matriz Homologada N:M Proveedor ↔ Servicio (SERVICIOS-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `servicio_proveedores` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `servicio_id` BIGINT UNSIGNED NOT NULL,
    `proveedor_id` BIGINT UNSIGNED NOT NULL,
    `costo_pactado` DECIMAL(15, 2) NULL COMMENT 'Costo de compra específico acordado con este proveedor',
    `es_preferente` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 si es el proveedor sugerido por defecto',
    `tiempo_anticipacion_horas` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Horas mínimas de preaviso para coordinar',
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `uq_preferente` BIGINT UNSIGNED GENERATED ALWAYS AS (IF(`es_preferente` = 1, `servicio_id`, NULL)) VIRTUAL COMMENT 'Garantiza máximo un proveedor preferente activo por servicio',
    CONSTRAINT `chk_sp_costo_no_negativo` CHECK (`costo_pactado` IS NULL OR `costo_pactado` >= 0.00),
    CONSTRAINT `fk_sp_servicio` FOREIGN KEY (`servicio_id`) REFERENCES `servicios` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_sp_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_servicio_proveedor` (`servicio_id`, `proveedor_id`),
    UNIQUE KEY `uq_sp_servicio_preferente` (`uq_preferente`),
    INDEX `idx_sp_servicio` (`servicio_id`),
    INDEX `idx_sp_proveedor` (`proveedor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Matriz N:M de proveedores externos homologados por servicio';

-- ----------------------------------------------------------------------------
-- 27. Servicios Contratados y Consumos Imputados (SERVICIOS-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `servicios_contratados` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(50) NOT NULL COMMENT 'Código único de contratación, ej. SC-20261015-0001',
    `reserva_id` BIGINT UNSIGNED NOT NULL COMMENT 'Reserva comercial titular obligatoria (fuente de verdad del folio)',
    `estadia_id` BIGINT UNSIGNED NULL COMMENT 'Estadía física opcional si fue consumido in situ en una habitación',
    `servicio_id` BIGINT UNSIGNED NOT NULL COMMENT 'Concepto del catálogo',
    `proveedor_id` BIGINT UNSIGNED NULL COMMENT 'Proveedor asignado. NULL indica operación interna por staff de Camargo',
    
    -- Snapshots históricos inmutables congelados al contratar (D-010 / D-069)
    `descripcion_servicio_snapshot` VARCHAR(150) NOT NULL,
    `categoria_codigo_snapshot` VARCHAR(50) NOT NULL,
    `modalidad_cobro_codigo_snapshot` VARCHAR(50) NOT NULL,
    `es_operacion_interna` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 si proveedor_id IS NULL (personal propio)',
    `cantidad` DECIMAL(8, 2) NOT NULL DEFAULT 1.00,
    `precio_unitario` DECIMAL(15, 2) NOT NULL,
    `costo_unitario` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    `subtotal` DECIMAL(15, 2) NOT NULL,
    `tasa_impuesto` DECIMAL(15, 4) NOT NULL DEFAULT 0.0000 COMMENT 'Tasa tributaria porcentual congelada (0.0000 bajo política fiscal actual)',
    `impuesto_total` DECIMAL(15, 2) NOT NULL DEFAULT 0.00 COMMENT '0.00 actual sin motor tributario',
    `total` DECIMAL(15, 2) NOT NULL,
    `costo_total` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    
    -- Ciclo de vida y tiempos operativos
    `estado` ENUM('SOLICITADO', 'CONFIRMADO', 'EJECUTADO', 'CANCELADO') NOT NULL DEFAULT 'SOLICITADO',
    `fecha_servicio` DATE NOT NULL COMMENT 'Fecha prevista para la prestación',
    `hora_servicio` TIME NULL COMMENT 'Hora prevista (si aplica)',
    `ejecutado_en` DATETIME NULL COMMENT 'Instante técnico UTC en que se ejecutó/entregó físicamente',
    `cancelada_en` DATETIME NULL COMMENT 'Instante técnico UTC en que se canceló',
    `motivo_cancelacion` VARCHAR(255) NULL,
    `observaciones` TEXT NULL,
    
    -- Trazabilidad y autoría D-061 (ACTOR != USUARIO)
    `solicitado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `ejecutado_por_actor_id` BIGINT UNSIGNED NULL,
    `cancelada_por_actor_id` BIGINT UNSIGNED NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    CONSTRAINT `chk_sc_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_sc_cantidad_positiva` CHECK (`cantidad` > 0.00),
    CONSTRAINT `chk_sc_precio_no_negativo` CHECK (`precio_unitario` >= 0.00),
    CONSTRAINT `chk_sc_costo_no_negativo` CHECK (`costo_unitario` >= 0.00),
    CONSTRAINT `chk_sc_coherencia_operacion_interna` CHECK (
        (`es_operacion_interna` = 1 AND `proveedor_id` IS NULL) OR 
        (`es_operacion_interna` = 0 AND `proveedor_id` IS NOT NULL)
    ),
    CONSTRAINT `chk_sc_cancelacion_coherente` CHECK (
        (`estado` <> 'CANCELADO' AND `cancelada_en` IS NULL AND `cancelada_por_actor_id` IS NULL AND `motivo_cancelacion` IS NULL) OR
        (`estado` = 'CANCELADO' AND `cancelada_en` IS NOT NULL AND `cancelada_por_actor_id` IS NOT NULL AND `motivo_cancelacion` IS NOT NULL)
    ),
    CONSTRAINT `fk_sc_reserva` FOREIGN KEY (`reserva_id`) REFERENCES `reservas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_sc_estadia` FOREIGN KEY (`estadia_id`) REFERENCES `estadias` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_sc_servicio` FOREIGN KEY (`servicio_id`) REFERENCES `servicios` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_sc_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_sc_actor_solicitado` FOREIGN KEY (`solicitado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_sc_actor_ejecutado` FOREIGN KEY (`ejecutado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sc_actor_cancelado` FOREIGN KEY (`cancelada_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    UNIQUE KEY `uq_sc_codigo` (`codigo`),
    INDEX `idx_sc_reserva` (`reserva_id`),
    INDEX `idx_sc_estadia` (`estadia_id`),
    INDEX `idx_sc_servicio` (`servicio_id`),
    INDEX `idx_sc_proveedor` (`proveedor_id`),
    INDEX `idx_sc_estado` (`estado`),
    INDEX `idx_sc_fecha_servicio` (`fecha_servicio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Registro transaccional de servicios contratados y consumos';

-- ----------------------------------------------------------------------------
-- 28. Extensión Logística Especializada 1:1 de Traslados (SERVICIOS-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `servicio_traslados` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `servicio_contratado_id` BIGINT UNSIGNED NOT NULL COMMENT 'Relación 1:1 estricta con el servicio contratado',
    `tipo_traslado` ENUM('LLEGADA', 'SALIDA') NOT NULL COMMENT 'LLEGADA al predio o SALIDA hacia terminal/aeropuerto/otro',
    `origen` VARCHAR(150) NOT NULL COMMENT 'Punto de partida físico (Aeropuerto, Terminal, Edificio, Dirección)',
    `destino` VARCHAR(150) NOT NULL COMMENT 'Punto de llegada físico',
    `fecha_hora_recogida` DATETIME NOT NULL COMMENT 'Fecha y hora local acordada para esperar al huésped',
    `aerolinea_empresa` VARCHAR(100) NULL COMMENT 'Aerolínea o empresa de transporte (LATAM, Sky, Cruz del Sur, etc.)',
    `numero_vuelo_viaje` VARCHAR(50) NULL COMMENT 'Número de vuelo o viaje (ej. LA2045)',
    `cantidad_pasajeros` INT UNSIGNED NOT NULL DEFAULT 1,
    `cantidad_maletas` INT UNSIGNED NOT NULL DEFAULT 0,
    `datos_conductor_vehiculo` VARCHAR(255) NULL COMMENT 'Nombre del chofer, teléfono, modelo y placa del vehículo',
    `instrucciones_recogida` TEXT NULL COMMENT 'Detalles para el encuentro (cartel con nombre en puerta de salida, etc.)',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_st_origen_no_vacio` CHECK (`origen` <> ''),
    CONSTRAINT `chk_st_destino_no_vacio` CHECK (`destino` <> ''),
    CONSTRAINT `chk_st_pasajeros_minimo` CHECK (`cantidad_pasajeros` >= 1),
    CONSTRAINT `fk_st_servicio_contratado` FOREIGN KEY (`servicio_contratado_id`) REFERENCES `servicios_contratados` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_st_servicio_contratado` (`servicio_contratado_id`),
    INDEX `idx_st_fecha_hora_recogida` (`fecha_hora_recogida`),
    INDEX `idx_st_numero_vuelo` (`numero_vuelo_viaje`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Extensión 1:1 con detalles logísticos para servicios de traslado';

-- ----------------------------------------------------------------------------
-- 29. Catálogo Normalizado de Métodos de Pago (FINANCIERO-2)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `metodos_pago` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'EFECTIVO, TARJETA_CREDITO, TARJETA_DEBITO, TRANSFERENCIA, BILLETERA_DIGITAL',
    `nombre` VARCHAR(100) NOT NULL,
    `tipo_destino` ENUM('CAJA_FISICA', 'CUENTA_BANCARIA', 'PASARELA_INTERMEDIARIO') NOT NULL DEFAULT 'CAJA_FISICA',
    `requiere_referencia` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 si exige nro de voucher u operación',
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_metp_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_metp_nombre_no_vacio` CHECK (`nombre` <> ''),
    UNIQUE KEY `uq_metodos_pago_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Catálogo de medios de pago soportados';

-- ----------------------------------------------------------------------------
-- 30. Cuentas Bancarias y Financieras de la Empresa (FINANCIERO-2)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cuentas_bancarias` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'Identificador interno (ej. BCP_CORRIENTE_PEN)',
    `banco_nombre` VARCHAR(100) NOT NULL COMMENT 'Entidad bancaria o financiera',
    `tipo_cuenta` ENUM('CORRIENTE', 'AHORROS', 'RECAUDADORA') NOT NULL DEFAULT 'CORRIENTE',
    `numero_cuenta` VARCHAR(50) NOT NULL,
    `numero_cci` VARCHAR(50) NULL,
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `titular` VARCHAR(150) NOT NULL COMMENT 'Razón social titular de la cuenta',
    `saldo_contable` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Saldo contable referencial',
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_ctab_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_ctab_numero_no_vacio` CHECK (`numero_cuenta` <> ''),
    UNIQUE KEY `uq_cuentas_bancarias_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Cuentas bancarias de Camargo Hostelería para recaudación y transferencias';

-- ----------------------------------------------------------------------------
-- 31. Cajas Físicas de Custodia en Predios (FINANCIERO-2)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cajas_fisicas` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'Identificador único (ej. CAJA_RECEPCION_1)',
    `nombre` VARCHAR(100) NOT NULL,
    `propiedad_id` BIGINT UNSIGNED NOT NULL COMMENT 'Predio o propiedad física a la que pertenece la gaveta',
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_cajf_propiedad` FOREIGN KEY (`propiedad_id`) REFERENCES `propiedades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_cajf_codigo_no_vacio` CHECK (`codigo` <> ''),
    UNIQUE KEY `uq_cajas_fisicas_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Puntos de recaudación y custodia de efectivo físico';

-- ----------------------------------------------------------------------------
-- 32. Sesiones de Turno de Caja (FINANCIERO-2)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sesiones_caja` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `caja_fisica_id` INT UNSIGNED NOT NULL,
    `actor_apertura_id` BIGINT UNSIGNED NOT NULL COMMENT 'Actor cajero humano que inicia el turno (D-061)',
    `actor_cierre_id` BIGINT UNSIGNED NULL COMMENT 'Actor cajero o supervisor que sella el turno (D-061)',
    `monto_apertura` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Fondo de cambio inicial en efectivo',
    `total_ingresos_efectivo` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Suma acumulada de cobros en efectivo',
    `total_egresos_efectivo` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Suma acumulada de devoluciones o salidas',
    `monto_esperado` DECIMAL(15,2) NULL COMMENT 'monto_apertura + ingresos - egresos',
    `monto_contado_declarado` DECIMAL(15,2) NULL COMMENT 'Conteo físico final de billetes y monedas',
    `diferencia` DECIMAL(15,2) NULL COMMENT 'declarado - esperado',
    `resultado_arqueo` ENUM('CUADRADA', 'SOBRANTE', 'FALTANTE') NULL,
    `estado` ENUM('ABIERTA', 'CERRADA') NOT NULL DEFAULT 'ABIERTA',
    `abierta_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `cerrada_en` DATETIME NULL,
    `observaciones_apertura` TEXT NULL,
    `observaciones_cierre` TEXT NULL COMMENT 'Obligatorio cuando diferencia != 0',
    CONSTRAINT `fk_sesc_caja_fisica` FOREIGN KEY (`caja_fisica_id`) REFERENCES `cajas_fisicas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_sesc_actor_apertura` FOREIGN KEY (`actor_apertura_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_sesc_actor_cierre` FOREIGN KEY (`actor_cierre_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `chk_sesc_monto_apertura_no_negativo` CHECK (`monto_apertura` >= 0),
    CONSTRAINT `chk_sesc_totales_no_negativos` CHECK (`total_ingresos_efectivo` >= 0 AND `total_egresos_efectivo` >= 0),
    INDEX `idx_sesc_caja_estado` (`caja_fisica_id`, `estado`),
    INDEX `idx_sesc_actor_apertura` (`actor_apertura_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Turnos de trabajo y arqueos de gaveta de efectivo';

-- ----------------------------------------------------------------------------
-- 33. Folios Financieros / Cuentas de Reserva o Arrendamiento (FINANCIERO-2 / ARRENDAMIENTOS-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cuentas_folios` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'Formato FOL-YYYYMMDD-XXXX',
    `reserva_id` BIGINT UNSIGNED NULL COMMENT '1:1 con la reserva raíz comercial (NULL si es arrendamiento)',
    `arrendamiento_id` BIGINT UNSIGNED NULL COMMENT '1:1 con el arrendamiento patrimonial (NULL si es reserva)',
    `persona_titular_id` BIGINT UNSIGNED NOT NULL COMMENT 'Titular principal de la cuenta',
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `estado` ENUM('ABIERTA', 'CONGELADA', 'CERRADA', 'ANULADA') NOT NULL DEFAULT 'ABIERTA',
    `creado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_ctaf_reserva` FOREIGN KEY (`reserva_id`) REFERENCES `reservas` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_ctaf_arrendamiento` FOREIGN KEY (`arrendamiento_id`) REFERENCES `arrendamientos` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_ctaf_persona_titular` FOREIGN KEY (`persona_titular_id`) REFERENCES `personas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_ctaf_actor_creador` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_ctaf_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_ctaf_sujeto_exclusivo` CHECK (
        (`reserva_id` IS NOT NULL AND `arrendamiento_id` IS NULL) OR
        (`reserva_id` IS NULL AND `arrendamiento_id` IS NOT NULL)
    ),
    UNIQUE KEY `uq_cuentas_folios_codigo` (`codigo`),
    UNIQUE KEY `uq_cuentas_folios_reserva` (`reserva_id`),
    UNIQUE KEY `uq_cuentas_folios_arrendamiento` (`arrendamiento_id`),
    INDEX `idx_ctaf_estado` (`estado`),
    INDEX `idx_ctaf_titular` (`persona_titular_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Contenedor financiero consolidado de la reserva comercial o contrato de arrendamiento';

-- ----------------------------------------------------------------------------
-- 34. Cargos a la Cuenta (FINANCIERO-2 / ARRENDAMIENTOS-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cargos_cuenta` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'Formato CRG-YYYYMMDD-XXXX',
    `cuenta_folio_id` BIGINT UNSIGNED NOT NULL,
    `origen_tipo` ENUM('ALOJAMIENTO_NOCHES', 'SERVICIO_CONTRATADO', 'PENALIDAD', 'AJUSTE_MANUAL', 'RENTA_ARRENDAMIENTO', 'DEPOSITO_GARANTIA', 'SUMINISTRO_CONSUMO', 'SUMINISTRO_CUOTA_FIJA', 'AJUSTE_SUMINISTRO') NOT NULL,
    `origen_id` BIGINT UNSIGNED NULL COMMENT 'ID de reserva_unidades o servicios_contratados',
    `estadia_id` BIGINT UNSIGNED NULL COMMENT 'Habitación física que consumió (NULL si es preventa o general)',
    `concepto` VARCHAR(255) NOT NULL,
    `cantidad` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
    `precio_unitario` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `subtotal` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `impuesto_total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `monto_aplicado_acumulado` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Total de amortizaciones activas',
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `estado` ENUM('PROVISIONAL', 'DEVENGADO', 'ANULADO') NOT NULL DEFAULT 'DEVENGADO' COMMENT'PROVISIONAL para servicios confirmados pero no ejecutados',
    `motivo_anulacion` VARCHAR(255) NULL,
    `anulado_en` DATETIME NULL,
    `anulado_por_actor_id` BIGINT UNSIGNED NULL,
    `devengado_en` DATETIME NULL COMMENT 'Instante UTC en que pasó a DEVENGADO irrevocable',
    `creado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_crgc_cuenta_folio` FOREIGN KEY (`cuenta_folio_id`) REFERENCES `cuentas_folios` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_crgc_estadia` FOREIGN KEY (`estadia_id`) REFERENCES `estadias` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_crgc_actor_creador` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_crgc_actor_anulador` FOREIGN KEY (`anulado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `chk_crgc_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_crgc_total_positivo` CHECK (`total` >= 0),
    CONSTRAINT `chk_crgc_aplicado_rango` CHECK (`monto_aplicado_acumulado` >= 0 AND `monto_aplicado_acumulado` <= `total`),
    UNIQUE KEY `uq_cargos_cuenta_codigo` (`codigo`),
    INDEX `idx_crgc_folio_estado` (`cuenta_folio_id`, `estado`),
    INDEX `idx_crgc_origen` (`origen_tipo`, `origen_id`),
    INDEX `idx_crgc_estadia` (`estadia_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Obligaciones y consumos devengados o provisionales en el folio';

-- ----------------------------------------------------------------------------
-- 35. Pagos y Cobros a la Cuenta (FINANCIERO-2)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pagos_cuenta` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'Formato PAG-YYYYMMDD-XXXX',
    `cuenta_folio_id` BIGINT UNSIGNED NOT NULL,
    `metodo_pago_id` INT UNSIGNED NOT NULL,
    `monto_total` DECIMAL(15,2) NOT NULL COMMENT 'Monto total recaudado en este pago',
    `monto_aplicado` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Suma de aplicaciones a cargos específicos',
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `sesion_caja_id` BIGINT UNSIGNED NULL COMMENT 'Obligatorio si método es EFECTIVO',
    `cuenta_bancaria_id` INT UNSIGNED NULL COMMENT 'Obligatorio si método es TRANSFERENCIA',
    `referencia_operacion` VARCHAR(100) NULL COMMENT 'Nro voucher POS, nro operación bancaria',
    `estado` ENUM('CONFIRMADO', 'REVERSADO') NOT NULL DEFAULT 'CONFIRMADO',
    `motivo_reverso` VARCHAR(255) NULL,
    `reversado_en` DATETIME NULL,
    `reversado_por_actor_id` BIGINT UNSIGNED NULL,
    `creado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_pagc_cuenta_folio` FOREIGN KEY (`cuenta_folio_id`) REFERENCES `cuentas_folios` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_pagc_metodo_pago` FOREIGN KEY (`metodo_pago_id`) REFERENCES `metodos_pago` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_pagc_sesion_caja` FOREIGN KEY (`sesion_caja_id`) REFERENCES `sesiones_caja` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_pagc_cuenta_bancaria` FOREIGN KEY (`cuenta_bancaria_id`) REFERENCES `cuentas_bancarias` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_pagc_actor_creador` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_pagc_actor_reversor` FOREIGN KEY (`reversado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `chk_pagc_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_pagc_monto_positivo` CHECK (`monto_total` > 0),
    CONSTRAINT `chk_pagc_aplicado_rango` CHECK (`monto_aplicado` >= 0 AND `monto_aplicado` <= `monto_total`),
    UNIQUE KEY `uq_pagos_cuenta_codigo` (`codigo`),
    INDEX `idx_pagc_folio_estado` (`cuenta_folio_id`, `estado`),
    INDEX `idx_pagc_metodo` (`metodo_pago_id`),
    INDEX `idx_pagc_sesion` (`sesion_caja_id`),
    INDEX `idx_pagc_cuenta_bancaria` (`cuenta_bancaria_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Dinero recibido o reconocido para la cuenta';

-- ----------------------------------------------------------------------------
-- 36. Aplicaciones de Pago (FINANCIERO-2)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `aplicaciones_pago` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'Formato APL-YYYYMMDD-XXXX',
    `pago_id` BIGINT UNSIGNED NOT NULL,
    `cargo_id` BIGINT UNSIGNED NOT NULL,
    `monto_aplicado` DECIMAL(15,2) NOT NULL,
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `estado` ENUM('ACTIVA', 'REVERTIDA') NOT NULL DEFAULT 'ACTIVA',
    `revertida_en` DATETIME NULL,
    `revertida_por_actor_id` BIGINT UNSIGNED NULL,
    `creado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_aplp_pago` FOREIGN KEY (`pago_id`) REFERENCES `pagos_cuenta` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_aplp_cargo` FOREIGN KEY (`cargo_id`) REFERENCES `cargos_cuenta` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_aplp_actor_creador` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_aplp_actor_reversor` FOREIGN KEY (`revertida_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `chk_aplp_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_aplp_monto_positivo` CHECK (`monto_aplicado` > 0),
    UNIQUE KEY `uq_aplicaciones_pago_codigo` (`codigo`),
    INDEX `idx_aplp_pago_estado` (`pago_id`, `estado`),
    INDEX `idx_aplp_cargo_estado` (`cargo_id`, `estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Imputación formal entre fondos recaudados y obligaciones devengadas';

-- ----------------------------------------------------------------------------
-- 37. Devoluciones de Fondos (FINANCIERO-2)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `devoluciones_cuenta` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'Formato DEV-YYYYMMDD-XXXX',
    `cuenta_folio_id` BIGINT UNSIGNED NOT NULL,
    `pago_origen_id` BIGINT UNSIGNED NOT NULL COMMENT 'Pago original que se reembolsa',
    `metodo_pago_id` INT UNSIGNED NOT NULL,
    `sesion_caja_id` BIGINT UNSIGNED NULL COMMENT 'Obligatorio si se reembolsa en EFECTIVO',
    `cuenta_bancaria_id` INT UNSIGNED NULL COMMENT 'Obligatorio si se transfiere a cliente',
    `monto` DECIMAL(15,2) NOT NULL,
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `motivo` VARCHAR(255) NOT NULL,
    `estado` ENUM('CONFIRMADA', 'ANULADA') NOT NULL DEFAULT 'CONFIRMADA',
    `creado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_devc_cuenta_folio` FOREIGN KEY (`cuenta_folio_id`) REFERENCES `cuentas_folios` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_devc_pago_origen` FOREIGN KEY (`pago_origen_id`) REFERENCES `pagos_cuenta` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_devc_metodo_pago` FOREIGN KEY (`metodo_pago_id`) REFERENCES `metodos_pago` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_devc_sesion_caja` FOREIGN KEY (`sesion_caja_id`) REFERENCES `sesiones_caja` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_devc_cuenta_bancaria` FOREIGN KEY (`cuenta_bancaria_id`) REFERENCES `cuentas_bancarias` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_devc_actor_creador` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_devc_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_devc_monto_positivo` CHECK (`monto` > 0),
    UNIQUE KEY `uq_devoluciones_cuenta_codigo` (`codigo`),
    INDEX `idx_devc_folio` (`cuenta_folio_id`),
    INDEX `idx_devc_pago` (`pago_origen_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Reembolsos reales entregados o transferidos al huésped';

-- ----------------------------------------------------------------------------
-- 38. Libro Mayor de Movimientos de Caja Física (FINANCIERO-2)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `movimientos_caja` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `sesion_caja_id` BIGINT UNSIGNED NOT NULL,
    `tipo_movimiento` ENUM('INGRESO_COBRO', 'INGRESO_AJUSTE', 'EGRESO_DEVOLUCION', 'EGRESO_GASTO_MENOR', 'EGRESO_REMESA') NOT NULL,
    `pago_id` BIGINT UNSIGNED NULL COMMENT 'Vinculado a pagos_cuenta si fue cobro a huésped',
    `devolucion_id` BIGINT UNSIGNED NULL COMMENT 'Vinculado a devoluciones_cuenta si fue reembolso',
    `monto` DECIMAL(15,2) NOT NULL,
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `concepto` VARCHAR(255) NOT NULL,
    `actor_id` BIGINT UNSIGNED NOT NULL COMMENT 'Actor responsable del movimiento (D-061)',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_movc_sesion_caja` FOREIGN KEY (`sesion_caja_id`) REFERENCES `sesiones_caja` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_movc_pago` FOREIGN KEY (`pago_id`) REFERENCES `pagos_cuenta` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_movc_devolucion` FOREIGN KEY (`devolucion_id`) REFERENCES `devoluciones_cuenta` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_movc_actor` FOREIGN KEY (`actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_movc_monto_positivo` CHECK (`monto` > 0),
    INDEX `idx_movc_sesion` (`sesion_caja_id`),
    INDEX `idx_movc_pago` (`pago_id`),
    INDEX `idx_movc_devolucion` (`devolucion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Libro mayor de entradas y salidas de billetes y monedas en gaveta';

-- ----------------------------------------------------------------------------
-- 39. Libro Mayor de Movimientos Bancarios (FINANCIERO-2)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `movimientos_bancarios` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `cuenta_bancaria_id` INT UNSIGNED NOT NULL,
    `tipo_movimiento` ENUM('INGRESO_TRANSFERENCIA', 'INGRESO_LIQUIDACION_POS', 'EGRESO_DEVOLUCION', 'EGRESO_TRANSFERENCIA', 'COMISION_BANCARIA') NOT NULL,
    `pago_id` BIGINT UNSIGNED NULL,
    `devolucion_id` BIGINT UNSIGNED NULL,
    `monto` DECIMAL(15,2) NOT NULL,
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `numero_operacion` VARCHAR(100) NOT NULL COMMENT 'Código de operación en extracto bancario',
    `concepto` VARCHAR(255) NOT NULL,
    `fecha_operacion` DATE NOT NULL,
    `actor_id` BIGINT UNSIGNED NOT NULL COMMENT 'Actor que concilia o asienta el movimiento (D-061)',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_movb_cuenta_bancaria` FOREIGN KEY (`cuenta_bancaria_id`) REFERENCES `cuentas_bancarias` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_movb_pago` FOREIGN KEY (`pago_id`) REFERENCES `pagos_cuenta` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_movb_devolucion` FOREIGN KEY (`devolucion_id`) REFERENCES `devoluciones_cuenta` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_movb_actor` FOREIGN KEY (`actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_movb_monto_positivo` CHECK (`monto` > 0),
    INDEX `idx_movb_cuenta` (`cuenta_bancaria_id`),
    INDEX `idx_movb_operacion` (`numero_operacion`),
    INDEX `idx_movb_pago` (`pago_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Libro de movimientos en cuentas bancarias para posterior conciliación';

-- ----------------------------------------------------------------------------
-- 40. Arrendamientos de Mediana y Larga Estancia (ARRENDAMIENTOS-1 / D-076)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `arrendamientos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'Formato ARR-YYYYMMDD-XXXX',
    `unidad_id` BIGINT UNSIGNED NOT NULL,
    `arrendamiento_anterior_id` BIGINT UNSIGNED NULL COMMENT 'Para contratos renovados',
    `fecha_inicio` DATE NOT NULL,
    `fecha_fin` DATE NOT NULL,
    `dia_vencimiento` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Día contractual de vencimiento (1..31)',
    `renta_mensual` DECIMAL(15,2) NOT NULL,
    `deposito_garantia` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `monto_primer_periodo` DECIMAL(15,2) NOT NULL COMMENT 'Monto congelado inicial (completo o prorrateado)',
    `es_primer_mes_prorrateado` TINYINT(1) NOT NULL DEFAULT 0,
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `estado` ENUM('BORRADOR', 'VIGENTE', 'FINALIZADO', 'RESCINDIDO', 'CANCELADO') NOT NULL DEFAULT 'BORRADOR',
    `motivo_rescision` VARCHAR(500) NULL,
    `rescidido_en` DATETIME NULL,
    `rescidido_por_actor_id` BIGINT UNSIGNED NULL,
    `notas_adicionales` TEXT NULL,
    `creado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_arr_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_arr_arrendamiento_anterior` FOREIGN KEY (`arrendamiento_anterior_id`) REFERENCES `arrendamientos` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_arr_actor_creador` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_arr_actor_rescisor` FOREIGN KEY (`rescidido_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `chk_arr_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_arr_fechas_coherentes` CHECK (`fecha_fin` > `fecha_inicio`),
    CONSTRAINT `chk_arr_dia_vencimiento_rango` CHECK (`dia_vencimiento` BETWEEN 1 AND 31),
    CONSTRAINT `chk_arr_renta_positiva` CHECK (`renta_mensual` > 0),
    CONSTRAINT `chk_arr_garantia_no_negativa` CHECK (`deposito_garantia` >= 0),
    UNIQUE KEY `uq_arrendamientos_codigo` (`codigo`),
    INDEX `idx_arr_unidad_fechas` (`unidad_id`, `fecha_inicio`, `fecha_fin`),
    INDEX `idx_arr_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Contratos patrimoniales de arrendamiento de unidades';

-- ----------------------------------------------------------------------------
-- 41. Sujetos del Arrendamiento: Titular Único, Cotitulares y Ocupantes (ARRENDAMIENTOS-1 / D-076)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `arrendamiento_personas` (
    `arrendamiento_id` BIGINT UNSIGNED NOT NULL,
    `persona_id` BIGINT UNSIGNED NOT NULL,
    `tipo_relacion` ENUM('TITULAR', 'COTITULAR', 'OCUPANTE') NOT NULL,
    `es_titular_unico` BIGINT UNSIGNED GENERATED ALWAYS AS (
        CASE WHEN `tipo_relacion` = 'TITULAR' THEN `arrendamiento_id` ELSE NULL END
    ) VIRTUAL,
    `observaciones` VARCHAR(255) NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`arrendamiento_id`, `persona_id`),
    CONSTRAINT `fk_arrp_arrendamiento` FOREIGN KEY (`arrendamiento_id`) REFERENCES `arrendamientos` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_arrp_persona` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_arrp_titular_unico` (`es_titular_unico`),
    INDEX `idx_arrp_persona` (`persona_id`),
    INDEX `idx_arrp_tipo` (`arrendamiento_id`, `tipo_relacion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Partes contractuales y residentes de la unidad con unicidad de titular';

-- ----------------------------------------------------------------------------
-- 42. Cuotas Periódicas Idempotentes de Renta (ARRENDAMIENTOS-1 / D-076)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `arrendamiento_cuotas` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `arrendamiento_id` BIGINT UNSIGNED NOT NULL,
    `periodo_anio` SMALLINT UNSIGNED NOT NULL,
    `periodo_mes` TINYINT UNSIGNED NOT NULL,
    `periodo_codigo` VARCHAR(7) NOT NULL COMMENT 'YYYY-MM',
    `tipo_cuota` ENUM('RENTA_MENSUAL', 'CUOTA_PRORRATEADA', 'AJUSTE_PERIODICO') NOT NULL DEFAULT 'RENTA_MENSUAL',
    `fecha_emision` DATE NOT NULL,
    `fecha_vencimiento` DATE NOT NULL,
    `monto_renta` DECIMAL(15,2) NOT NULL,
    `cargo_cuenta_id` BIGINT UNSIGNED NOT NULL,
    `estado` ENUM('PENDIENTE', 'PAGADA_PARCIAL', 'PAGADA_TOTAL', 'ANULADA') NOT NULL DEFAULT 'PENDIENTE',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_arrc_arrendamiento` FOREIGN KEY (`arrendamiento_id`) REFERENCES `arrendamientos` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_arrc_cargo_cuenta` FOREIGN KEY (`cargo_cuenta_id`) REFERENCES `cargos_cuenta` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_arrc_periodo_mes` CHECK (`periodo_mes` BETWEEN 1 AND 12),
    CONSTRAINT `chk_arrc_monto_positivo` CHECK (`monto_renta` > 0),
    UNIQUE KEY `uq_arrc_arrendamiento_periodo_tipo` (`arrendamiento_id`, `periodo_anio`, `periodo_mes`, `tipo_cuota`),
    INDEX `idx_arrc_arrendamiento_estado` (`arrendamiento_id`, `estado`),
    INDEX `idx_arrc_vencimiento` (`fecha_vencimiento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Registro de obligaciones mensuales recurrentes por contrato';

-- ----------------------------------------------------------------------------
-- 43. Custodia Segregada de Garantía (ARRENDAMIENTOS-1 / D-076)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `arrendamiento_garantias` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `arrendamiento_id` BIGINT UNSIGNED NOT NULL UNIQUE,
    `monto_pactado` DECIMAL(15,2) NOT NULL,
    `monto_recibido` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `monto_retenido_actual` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `monto_compensado_danos` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `monto_compensado_renta` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `monto_devuelto` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `estado` ENUM('PENDIENTE', 'CUSTODIADA', 'COMPENSADA_PARCIAL', 'LIQUIDADA') NOT NULL DEFAULT 'PENDIENTE',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_arrg_arrendamiento` FOREIGN KEY (`arrendamiento_id`) REFERENCES `arrendamientos` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_arrg_montos_no_negativos` CHECK (
        `monto_pactado` >= 0 AND `monto_recibido` >= 0 AND `monto_retenido_actual` >= 0
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Fondo de garantía en custodia con saldo reconstructible';

-- ----------------------------------------------------------------------------
-- 44. Historial Inmutable de Transiciones de Estado (ARRENDAMIENTOS-1 / D-076)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `arrendamiento_historial_estados` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `arrendamiento_id` BIGINT UNSIGNED NOT NULL,
    `estado_anterior` ENUM('BORRADOR', 'VIGENTE', 'FINALIZADO', 'RESCINDIDO', 'CANCELADO') NOT NULL,
    `estado_nuevo` ENUM('BORRADOR', 'VIGENTE', 'FINALIZADO', 'RESCINDIDO', 'CANCELADO') NOT NULL,
    `motivo` VARCHAR(500) NULL,
    `actor_id` BIGINT UNSIGNED NOT NULL,
    `cambiado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_ahe_arrendamiento` FOREIGN KEY (`arrendamiento_id`) REFERENCES `arrendamientos` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_ahe_actor` FOREIGN KEY (`actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX `idx_ahe_arrendamiento` (`arrendamiento_id`, `cambiado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Trazabilidad histórica inmutable de cambios de estado';

-- ----------------------------------------------------------------------------
-- 45. Incidencias Técnicas y Desperfectos Físicos (MANTENIMIENTO-1 / D-077)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mantenimiento_incidencias` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'INC-YYYYMMDD-XXXX',
    `propiedad_id` BIGINT UNSIGNED NOT NULL,
    `unidad_id` BIGINT UNSIGNED NULL COMMENT 'NULL si la incidencia ocurre en áreas comunes o infraestructura general',
    `reportado_por_persona_id` BIGINT UNSIGNED NOT NULL,
    `categoria` ENUM(
        'PLOMERIA',
        'ELECTRICIDAD',
        'CERRAJERIA',
        'CLIMATIZACION',
        'PINTURA',
        'MOBILIARIO',
        'LIMPIEZA_PROFUNDA',
        'ESTRUCTURAL',
        'OTRO'
    ) NOT NULL DEFAULT 'OTRO',
    `severidad` ENUM('BAJA', 'MEDIA', 'ALTA', 'CRITICA') NOT NULL DEFAULT 'MEDIA',
    `titulo` VARCHAR(150) NOT NULL,
    `descripcion` TEXT NOT NULL,
    `ubicacion_detallada` VARCHAR(255) NULL,
    `estado` ENUM(
        'REPORTADA',
        'EN_EVALUACION',
        'CONVERTIDA_A_ORDEN',
        'RESUELTA_DIRECTA',
        'DESESTIMADA'
    ) NOT NULL DEFAULT 'REPORTADA',
    `motivo_cierre` VARCHAR(500) NULL COMMENT 'Explicación si se desestima o se resuelve directamente',
    `creado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `cerrado_por_actor_id` BIGINT UNSIGNED NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `resuelto_en` DATETIME NULL,
    CONSTRAINT `chk_minc_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_minc_titulo_no_vacio` CHECK (`titulo` <> ''),
    CONSTRAINT `fk_minc_propiedad` FOREIGN KEY (`propiedad_id`) REFERENCES `propiedades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_minc_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_minc_reportador` FOREIGN KEY (`reportado_por_persona_id`) REFERENCES `personas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_minc_actor_creador` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_minc_actor_cerrador` FOREIGN KEY (`cerrado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    UNIQUE KEY `uq_minc_codigo` (`codigo`),
    INDEX `idx_minc_propiedad_unidad` (`propiedad_id`, `unidad_id`),
    INDEX `idx_minc_estado` (`estado`),
    INDEX `idx_minc_severidad` (`severidad`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Reportes e incidencias físicas y técnicas en propiedades y unidades';

-- ----------------------------------------------------------------------------
-- 46. Órdenes de Trabajo de Mantenimiento (MANTENIMIENTO-1 / D-077)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mantenimiento_ordenes` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'OT-YYYYMMDD-XXXX',
    `tipo` ENUM('CORRECTIVO', 'PREVENTIVO') NOT NULL DEFAULT 'CORRECTIVO',
    `prioridad` ENUM('BAJA', 'MEDIA', 'ALTA', 'URGENTE') NOT NULL DEFAULT 'MEDIA',
    `propiedad_id` BIGINT UNSIGNED NOT NULL,
    `unidad_id` BIGINT UNSIGNED NULL COMMENT 'NULL si el trabajo es de áreas comunes o infraestructura general',
    `titulo` VARCHAR(150) NOT NULL,
    `descripcion` TEXT NOT NULL,
    `tipo_asignacion` ENUM('INTERNO', 'EXTERNO', 'MIXTO') NOT NULL DEFAULT 'INTERNO',
    `colaborador_asignado_id` BIGINT UNSIGNED NULL COMMENT 'Personal interno responsable',
    `proveedor_id` BIGINT UNSIGNED NULL COMMENT 'Proveedor externo homologado',
    `numero_comprobante_proveedor` VARCHAR(50) NULL COMMENT 'Nro de factura o boleta del proveedor',
    `requiere_bloqueo` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 si inhabilita físicamente la unidad para venta',
    `fecha_programada_inicio` DATE NOT NULL,
    `fecha_programada_fin` DATE NOT NULL,
    `fecha_bloqueo_inicio` DATE NULL COMMENT 'Inicio del bloqueo en inventario diario',
    `fecha_bloqueo_fin` DATE NULL COMMENT 'Fin del bloqueo (exclusivo) en inventario diario',
    `fecha_ejecucion_inicio` DATETIME NULL,
    `fecha_ejecucion_fin` DATETIME NULL,
    `costo_estimado` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `costo_mano_obra` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `costo_materiales` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `costo_total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `moneda_codigo` CHAR(3) NOT NULL DEFAULT 'PEN',
    `estado` ENUM(
        'BORRADOR',
        'PROGRAMADA',
        'EN_PROCESO',
        'COMPLETADA',
        'CANCELADA'
    ) NOT NULL DEFAULT 'BORRADOR',
    `creado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `completado_por_actor_id` BIGINT UNSIGNED NULL,
    `cancelado_por_actor_id` BIGINT UNSIGNED NULL,
    `motivo_cancelacion` VARCHAR(500) NULL,
    `notas_cierre` TEXT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_mord_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_mord_titulo_no_vacio` CHECK (`titulo` <> ''),
    CONSTRAINT `chk_mord_fechas_programadas` CHECK (`fecha_programada_fin` >= `fecha_programada_inicio`),
    CONSTRAINT `chk_mord_bloqueo_coherente` CHECK (
        (`requiere_bloqueo` = 0 AND `fecha_bloqueo_inicio` IS NULL AND `fecha_bloqueo_fin` IS NULL) OR
        (`requiere_bloqueo` = 1 AND `unidad_id` IS NOT NULL AND `fecha_bloqueo_inicio` IS NOT NULL AND `fecha_bloqueo_fin` IS NOT NULL AND `fecha_bloqueo_fin` > `fecha_bloqueo_inicio`)
    ),
    CONSTRAINT `chk_mord_costos_no_negativos` CHECK (`costo_estimado` >= 0 AND `costo_materiales` >= 0 AND `costo_mano_obra` >= 0 AND `costo_total` >= 0),
    CONSTRAINT `fk_mord_propiedad` FOREIGN KEY (`propiedad_id`) REFERENCES `propiedades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_mord_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_mord_colaborador` FOREIGN KEY (`colaborador_asignado_id`) REFERENCES `colaboradores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_mord_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_mord_actor_creador` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_mord_actor_completador` FOREIGN KEY (`completado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_mord_actor_cancelador` FOREIGN KEY (`cancelado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    UNIQUE KEY `uq_mord_codigo` (`codigo`),
    INDEX `idx_mord_propiedad_unidad` (`propiedad_id`, `unidad_id`),
    INDEX `idx_mord_estado` (`estado`),
    INDEX `idx_mord_fechas_programadas` (`fecha_programada_inicio`, `fecha_programada_fin`),
    INDEX `idx_mord_bloqueo` (`requiere_bloqueo`, `fecha_bloqueo_inicio`, `fecha_bloqueo_fin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Órdenes de trabajo preventivas y correctivas de mantenimiento';

-- ----------------------------------------------------------------------------
-- 47. Tabla Pivote Órdenes <-> Incidencias (MANTENIMIENTO-1 / D-077)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mantenimiento_orden_incidencias` (
    `orden_id` BIGINT UNSIGNED NOT NULL,
    `incidencia_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`orden_id`, `incidencia_id`),
    CONSTRAINT `fk_moi_orden` FOREIGN KEY (`orden_id`) REFERENCES `mantenimiento_ordenes` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_moi_incidencia` FOREIGN KEY (`incidencia_id`) REFERENCES `mantenimiento_incidencias` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Asociación entre órdenes de trabajo e incidencias atendidas';

-- ----------------------------------------------------------------------------
-- 48. Historial Inmutable de Estados de Mantenimiento (MANTENIMIENTO-1 / D-077)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mantenimiento_historial_estados` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `entidad_tipo` ENUM('INCIDENCIA', 'ORDEN_TRABAJO') NOT NULL,
    `entidad_id` BIGINT UNSIGNED NOT NULL,
    `estado_anterior` VARCHAR(30) NOT NULL,
    `estado_nuevo` VARCHAR(30) NOT NULL,
    `motivo` VARCHAR(500) NULL,
    `actor_id` BIGINT UNSIGNED NOT NULL,
    `cambiado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_mhe_actor` FOREIGN KEY (`actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX `idx_mhe_entidad` (`entidad_tipo`, `entidad_id`, `cambiado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Historial inmutable de cambios de estado en mantenimiento e incidencias';

-- ----------------------------------------------------------------------------
-- 49. Unidades de Medida Normalizadas (INVENTARIO-1 / D-078)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inventario_unidades_medida` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(20) NOT NULL COMMENT 'UND, PAR, JGO, LT, KG, MTR, ROLLO, CAJA, PQTE',
    `nombre` VARCHAR(60) NOT NULL,
    `simbolo` VARCHAR(10) NOT NULL,
    `admite_decimales` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 si admite fraccionamiento (ej: KG, LT, MTR)',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_ium_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_ium_nombre_no_vacio` CHECK (`nombre` <> ''),
    CONSTRAINT `chk_ium_simbolo_no_vacio` CHECK (`simbolo` <> ''),
    UNIQUE KEY `uq_ium_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Catálogo normalizado de unidades de medida para inventario';

-- Semillas canónicas de unidades de medida
INSERT INTO `inventario_unidades_medida` (`codigo`, `nombre`, `simbolo`, `admite_decimales`) VALUES
('UND', 'Unidad', 'und', 0),
('PAR', 'Par', 'par', 0),
('JGO', 'Juego', 'jgo', 0),
('LT', 'Litro', 'L', 1),
('KG', 'Kilogramo', 'kg', 1),
('MTR', 'Metro', 'm', 1),
('ROLLO', 'Rollo', 'rll', 0),
('CAJA', 'Caja', 'cj', 0),
('PQTE', 'Paquete', 'paq', 0)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `simbolo` = VALUES(`simbolo`), `admite_decimales` = VALUES(`admite_decimales`);

-- ----------------------------------------------------------------------------
-- 50. Ubicaciones Polimórficas de Inventario (INVENTARIO-1 / D-078)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inventario_ubicaciones` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `propiedad_id` BIGINT UNSIGNED NOT NULL,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'UBI-BOD-01, UBI-HAB-101, UBI-LAV-01',
    `nombre` VARCHAR(100) NOT NULL,
    `tipo` ENUM('ALMACEN', 'UNIDAD', 'CUSTODIA_EXTERNA') NOT NULL DEFAULT 'ALMACEN',
    `unidad_id` BIGINT UNSIGNED NULL COMMENT 'Obligatorio si tipo = UNIDAD, NULL en otros tipos',
    `proveedor_id` BIGINT UNSIGNED NULL COMMENT 'Proveedor externo si tipo = CUSTODIA_EXTERNA (ej: lavandería/taller)',
    `responsable_colaborador_id` BIGINT UNSIGNED NULL COMMENT 'Colaborador a cargo',
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_iubi_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_iubi_nombre_no_vacio` CHECK (`nombre` <> ''),
    CONSTRAINT `chk_iubi_unidad_coherencia` CHECK (
        (`tipo` = 'UNIDAD' AND `unidad_id` IS NOT NULL) OR
        (`tipo` <> 'UNIDAD' AND `unidad_id` IS NULL)
    ),
    CONSTRAINT `fk_iubi_propiedad` FOREIGN KEY (`propiedad_id`) REFERENCES `propiedades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_iubi_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_iubi_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_iubi_responsable` FOREIGN KEY (`responsable_colaborador_id`) REFERENCES `colaboradores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_iubi_codigo` (`codigo`),
    UNIQUE KEY `uq_iubi_unidad_id` (`unidad_id`),
    INDEX `idx_iubi_propiedad_tipo` (`propiedad_id`, `tipo`),
    INDEX `idx_iubi_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Ubicaciones físicas y externas de inventario (almacenes, habitaciones, lavanderías)';

-- ----------------------------------------------------------------------------
-- 51. Catálogo de Artículos de Inventario (INVENTARIO-1 / D-078)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inventario_articulos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo_sku` VARCHAR(30) NOT NULL COMMENT 'SKU-AMN-001, SKU-LEN-001, SKU-REP-001, SKU-ACT-001',
    `nombre` VARCHAR(150) NOT NULL,
    `descripcion` TEXT NULL,
    `categoria` ENUM(
        'CONSUMIBLE_OPERATIVO',
        'LENCERIA_BLANCOS',
        'REPUESTO_MANTENIMIENTO',
        'ACTIVO_SERIALIZABLE',
        'HERRAMIENTA',
        'OTRO'
    ) NOT NULL DEFAULT 'CONSUMIBLE_OPERATIVO',
    `unidad_medida_id` BIGINT UNSIGNED NOT NULL,
    `costo_referencial` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `moneda_codigo` CHAR(3) NOT NULL DEFAULT 'PEN',
    `stock_minimo_alerta` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_iart_sku_no_vacio` CHECK (`codigo_sku` <> ''),
    CONSTRAINT `chk_iart_nombre_no_vacio` CHECK (`nombre` <> ''),
    CONSTRAINT `chk_iart_costo_no_negativo` CHECK (`costo_referencial` >= 0),
    CONSTRAINT `chk_iart_stock_min_no_negativo` CHECK (`stock_minimo_alerta` >= 0),
    CONSTRAINT `fk_iart_unidad_medida` FOREIGN KEY (`unidad_medida_id`) REFERENCES `inventario_unidades_medida` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_iart_sku` (`codigo_sku`),
    INDEX `idx_iart_categoria` (`categoria`),
    INDEX `idx_iart_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Catálogo maestro de artículos y bienes de inventario';

-- ----------------------------------------------------------------------------
-- 52. Existencias por Ubicación (INVENTARIO-1 / D-078)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inventario_existencias` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `articulo_id` BIGINT UNSIGNED NOT NULL,
    `ubicacion_id` BIGINT UNSIGNED NOT NULL,
    `cantidad_actual` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `cantidad_reservada` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_ie_cantidad_no_negativa` CHECK (`cantidad_actual` >= 0),
    CONSTRAINT `chk_ie_reservada_no_negativa` CHECK (`cantidad_reservada` >= 0),
    CONSTRAINT `fk_ie_articulo` FOREIGN KEY (`articulo_id`) REFERENCES `inventario_articulos` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_ie_ubicacion` FOREIGN KEY (`ubicacion_id`) REFERENCES `inventario_ubicaciones` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_ie_articulo_ubicacion` (`articulo_id`, `ubicacion_id`),
    INDEX `idx_ie_ubicacion` (`ubicacion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Proyección operacional de stock materializado por artículo y ubicación';

-- ----------------------------------------------------------------------------
-- 53. Movimientos de Inventario (INVENTARIO-1 / D-078)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inventario_movimientos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'MOV-YYYYMMDD-XXXX',
    `tipo_movimiento` ENUM(
        'SALDO_INICIAL',
        'ENTRADA_COMPRA',
        'SALIDA_CONSUMO',
        'SALIDA_MANTENIMIENTO',
        'TRASLADO_SALIDA',
        'TRASLADO_ENTRADA',
        'AJUSTE_POSITIVO',
        'AJUSTE_NEGATIVO',
        'REVERSO'
    ) NOT NULL,
    `articulo_id` BIGINT UNSIGNED NOT NULL,
    `ubicacion_id` BIGINT UNSIGNED NOT NULL,
    `cantidad` DECIMAL(15,4) NOT NULL,
    `costo_unitario_historico` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `costo_total_historico` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `moneda_codigo` CHAR(3) NOT NULL DEFAULT 'PEN',
    `referencia_tipo` VARCHAR(50) NULL COMMENT 'MANTENIMIENTO_ORDEN, TRASLADO, AJUSTE_FISICO, COMPRA, etc.',
    `referencia_id` BIGINT UNSIGNED NULL COMMENT 'ID de la entidad vinculada',
    `movimiento_referencia_id` BIGINT UNSIGNED NULL COMMENT 'Enlace a movimiento original en caso de REVERSO o traslado',
    `correlativo_operacion` VARCHAR(40) NULL COMMENT 'Identificador único compartido para ambas patas de un traslado',
    `motivo` VARCHAR(500) NOT NULL,
    `creado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `reverso_movimiento_id` BIGINT UNSIGNED GENERATED ALWAYS AS (
        CASE WHEN `tipo_movimiento` = 'REVERSO' THEN `movimiento_referencia_id` ELSE NULL END
    ) VIRTUAL COMMENT 'Garantiza a nivel de motor MySQL que un movimiento no pueda ser neutralizado por más de un REVERSO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_imov_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_imov_cantidad_positiva` CHECK (`cantidad` > 0),
    CONSTRAINT `chk_imov_costo_unit_no_negativo` CHECK (`costo_unitario_historico` >= 0),
    CONSTRAINT `chk_imov_costo_tot_no_negativo` CHECK (`costo_total_historico` >= 0),
    CONSTRAINT `fk_imov_articulo` FOREIGN KEY (`articulo_id`) REFERENCES `inventario_articulos` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_imov_ubicacion` FOREIGN KEY (`ubicacion_id`) REFERENCES `inventario_ubicaciones` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_imov_actor` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_imov_mov_ref` FOREIGN KEY (`movimiento_referencia_id`) REFERENCES `inventario_movimientos` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_imov_codigo` (`codigo`),
    UNIQUE KEY `uq_imov_reverso_unico` (`reverso_movimiento_id`),
    INDEX `idx_imov_articulo_ubicacion_fecha` (`articulo_id`, `ubicacion_id`, `creado_en`),
    INDEX `idx_imov_referencia` (`referencia_tipo`, `referencia_id`),
    INDEX `idx_imov_correlativo` (`correlativo_operacion`),
    INDEX `idx_imov_tipo` (`tipo_movimiento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Kardex inmutable y bitácora física append-only de inventario';

-- ----------------------------------------------------------------------------
-- 54. Activos Fijos Individuales y Serializables (INVENTARIO-1 / D-078)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inventario_activos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `articulo_id` BIGINT UNSIGNED NOT NULL,
    `codigo_placa` VARCHAR(40) NOT NULL COMMENT 'Placa patrimonial / código de barras único',
    `numero_serie_fabricante` VARCHAR(60) NULL,
    `marca` VARCHAR(60) NULL,
    `modelo` VARCHAR(60) NULL,
    `propiedad_id` BIGINT UNSIGNED NOT NULL,
    `ubicacion_id` BIGINT UNSIGNED NOT NULL,
    `estado` ENUM('DISPONIBLE', 'ASIGNADO', 'EN_MANTENIMIENTO', 'DE_BAJA') NOT NULL DEFAULT 'DISPONIBLE',
    `fecha_adquisicion` DATE NULL,
    `costo_adquisicion` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `moneda_codigo` CHAR(3) NOT NULL DEFAULT 'PEN',
    `garantia_vence_en` DATE NULL,
    `notas` TEXT NULL,
    `motivo_baja` VARCHAR(500) NULL,
    `baja_por_actor_id` BIGINT UNSIGNED NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_iact_placa_no_vacia` CHECK (`codigo_placa` <> ''),
    CONSTRAINT `chk_iact_costo_no_negativo` CHECK (`costo_adquisicion` >= 0),
    CONSTRAINT `fk_iact_articulo` FOREIGN KEY (`articulo_id`) REFERENCES `inventario_articulos` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_iact_propiedad` FOREIGN KEY (`propiedad_id`) REFERENCES `propiedades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_iact_ubicacion` FOREIGN KEY (`ubicacion_id`) REFERENCES `inventario_ubicaciones` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_iact_baja_actor` FOREIGN KEY (`baja_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_iact_codigo_placa` (`codigo_placa`),
    INDEX `idx_iact_articulo` (`articulo_id`),
    INDEX `idx_iact_propiedad_ubicacion` (`propiedad_id`, `ubicacion_id`),
    INDEX `idx_iact_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Ejemplares individuales de activos serializables (Smart TVs, frigobares, etc.)';

-- ----------------------------------------------------------------------------
-- 55. Dotaciones Estándar de Unidades (INVENTARIO-1 / D-078)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inventario_dotaciones_estandar` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tipo_unidad_id` INT UNSIGNED NULL,
    `unidad_id` BIGINT UNSIGNED NULL,
    `articulo_id` BIGINT UNSIGNED NOT NULL,
    `cantidad_estandar` DECIMAL(15,4) NOT NULL,
    `notas` VARCHAR(255) NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_ide_destino_xor` CHECK (
        (`tipo_unidad_id` IS NOT NULL AND `unidad_id` IS NULL) OR
        (`tipo_unidad_id` IS NULL AND `unidad_id` IS NOT NULL)
    ),
    CONSTRAINT `chk_ide_cantidad_positiva` CHECK (`cantidad_estandar` > 0),
    CONSTRAINT `fk_ide_tipo_unidad` FOREIGN KEY (`tipo_unidad_id`) REFERENCES `tipos_unidad` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_ide_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_ide_articulo` FOREIGN KEY (`articulo_id`) REFERENCES `inventario_articulos` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_ide_tipo_unidad_articulo` (`tipo_unidad_id`, `articulo_id`),
    UNIQUE KEY `uq_ide_unidad_articulo` (`unidad_id`, `articulo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Dotación esperada o reglamentaria por tipo de unidad o por unidad específica';

-- ----------------------------------------------------------------------------
-- 56. Secuencias Concurrency-Safe para Folios (DOCUMENTOS-1 / D-079)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `documento_secuencias` (
    `tipo_documento` VARCHAR(40) NOT NULL COMMENT 'CONTRATO_ARRENDAMIENTO, RECIBO_PAGO, etc.',
    `periodo_ym` CHAR(6) NOT NULL COMMENT 'YYYYMM para reinicio mensual ordenado',
    `ultimo_correlativo` INT UNSIGNED NOT NULL DEFAULT 0,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`tipo_documento`, `periodo_ym`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Secuencias concurrency-safe para folios documentales (DOC-ARR-YYYYMM-XXXX)';

-- ----------------------------------------------------------------------------
-- 57. Catálogo Maestro de Plantillas Documentales (DOCUMENTOS-1 / D-079)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `documento_plantillas` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(40) NOT NULL COMMENT 'Identificador único canónico (CONTRATO_ARRENDAMIENTO)',
    `nombre` VARCHAR(150) NOT NULL,
    `descripcion` VARCHAR(500) NULL,
    `origen_tipo_permitido` ENUM('ARRENDAMIENTO', 'RESERVA', 'ESTADIA', 'PAGO', 'RECIBO', 'COMPRA') NOT NULL,
    `orientacion` ENUM('PORTRAIT', 'LANDSCAPE') NOT NULL DEFAULT 'PORTRAIT',
    `tamano_papel` ENUM('A4', 'LETTER', 'TICKET_80MM') NOT NULL DEFAULT 'A4',
    `requiere_membrete` TINYINT(1) NOT NULL DEFAULT 1,
    `archivo_membrete_fondo` VARCHAR(255) NULL COMMENT 'Ruta inmutable en storage/membretes/',
    `margen_superior_mm` INT NOT NULL DEFAULT 35,
    `margen_inferior_mm` INT NOT NULL DEFAULT 28,
    `margen_izquierdo_mm` INT NOT NULL DEFAULT 20,
    `margen_derecho_mm` INT NOT NULL DEFAULT 20,
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_docp_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_docp_nombre_no_vacio` CHECK (`nombre` <> ''),
    CONSTRAINT `chk_docp_m_sup_no_negativo` CHECK (`margen_superior_mm` >= 0),
    CONSTRAINT `chk_docp_m_inf_no_negativo` CHECK (`margen_inferior_mm` >= 0),
    CONSTRAINT `chk_docp_m_izq_no_negativo` CHECK (`margen_izquierdo_mm` >= 0),
    CONSTRAINT `chk_docp_m_der_no_negativo` CHECK (`margen_derecho_mm` >= 0),
    UNIQUE KEY `uq_docp_codigo` (`codigo`),
    INDEX `idx_docp_origen_tipo` (`origen_tipo_permitido`),
    INDEX `idx_docp_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Catálogo maestro de plantillas documentales';

-- ----------------------------------------------------------------------------
-- 58. Versiones Inmutables de Contenido de Plantillas (DOCUMENTOS-1 / D-079)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `documento_plantilla_versiones` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `plantilla_id` BIGINT UNSIGNED NOT NULL,
    `numero_version` INT NOT NULL,
    `titulo_documento` VARCHAR(200) NOT NULL,
    `cuerpo_html` MEDIUMTEXT NOT NULL COMMENT 'Plantilla HTML con shortcodes {{entidad.propiedad}}',
    `estilos_css` TEXT NULL COMMENT 'CSS documental específico para print/Dompdf',
    `notas_version` VARCHAR(500) NULL,
    `es_activa` TINYINT(1) NOT NULL DEFAULT 0,
    `version_activa_idx` BIGINT UNSIGNED GENERATED ALWAYS AS (
        CASE WHEN `es_activa` = 1 THEN `plantilla_id` ELSE NULL END
    ) VIRTUAL COMMENT 'Garantiza a nivel InnoDB que solo exista una versión activa por plantilla',
    `creado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_dpv_version_positiva` CHECK (`numero_version` > 0),
    CONSTRAINT `chk_dpv_titulo_no_vacio` CHECK (`titulo_documento` <> ''),
    CONSTRAINT `fk_dpv_plantilla` FOREIGN KEY (`plantilla_id`) REFERENCES `documento_plantillas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_dpv_actor` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_dpv_plantilla_version` (`plantilla_id`, `numero_version`),
    UNIQUE KEY `uq_dpv_plantilla_activa` (`version_activa_idx`),
    INDEX `idx_dpv_plantilla` (`plantilla_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Versiones inmutables de contenido de plantillas documentales';

-- ----------------------------------------------------------------------------
-- 59. Documentos Emitidos con Snapshots e Integridad Criptográfica (DOCUMENTOS-1 / D-079)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `documentos_emitidos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo_folio` VARCHAR(50) NOT NULL COMMENT 'DOC-ARR-YYYYMM-XXXX',
    `plantilla_id` BIGINT UNSIGNED NOT NULL,
    `plantilla_version_id` BIGINT UNSIGNED NOT NULL,
    `origen_tipo` ENUM('ARRENDAMIENTO', 'RESERVA', 'ESTADIA', 'PAGO', 'RECIBO', 'COMPRA') NOT NULL,
    `origen_id` BIGINT UNSIGNED NOT NULL,
    `snapshot_datos_json` JSON NOT NULL COMMENT 'Valores crudos de los shortcodes en orden determinista',
    `snapshot_html` MEDIUMTEXT NOT NULL COMMENT 'HTML resuelto exactamente como fue renderizado',
    `ruta_archivo_pdf` VARCHAR(255) NOT NULL COMMENT 'Ruta física relativa a storage/documentos/',
    `tamano_bytes` INT UNSIGNED NOT NULL,
    `hash_pdf_sha256` CHAR(64) NOT NULL COMMENT 'Hash criptográfico del binario PDF almacenado',
    `hash_snapshot_sha256` CHAR(64) NOT NULL COMMENT 'Hash del HTML compilado congelado',
    `numero_paginas` INT UNSIGNED NOT NULL DEFAULT 1,
    `emitido_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `emitido_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `estado` ENUM('VALIDO', 'ANULADO') NOT NULL DEFAULT 'VALIDO',
    `motivo_anulacion` VARCHAR(500) NULL,
    `anulado_en` DATETIME NULL,
    `anulado_por_actor_id` BIGINT UNSIGNED NULL,
    CONSTRAINT `chk_demit_folio_no_vacio` CHECK (`codigo_folio` <> ''),
    CONSTRAINT `chk_demit_tamano_positivo` CHECK (`tamano_bytes` > 0),
    CONSTRAINT `fk_demit_plantilla` FOREIGN KEY (`plantilla_id`) REFERENCES `documento_plantillas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_demit_version` FOREIGN KEY (`plantilla_version_id`) REFERENCES `documento_plantilla_versiones` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_demit_emisor` FOREIGN KEY (`emitido_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_demit_anulador` FOREIGN KEY (`anulado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_demit_codigo_folio` (`codigo_folio`),
    INDEX `idx_demit_origen` (`origen_tipo`, `origen_id`),
    INDEX `idx_demit_hash` (`hash_pdf_sha256`),
    INDEX `idx_demit_estado` (`estado`),
    INDEX `idx_demit_plantilla` (`plantilla_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Documentos emitidos con snapshot inmutable y hash SHA-256';

-- ----------------------------------------------------------------------------
-- 60. Incidencias Documentales (Auditoría de Archivos Ausentes o Corruptos)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `documento_incidencias` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `documento_emitido_id` BIGINT UNSIGNED NOT NULL,
    `tipo_incidencia` ENUM('ARCHIVO_FALTANTE', 'HASH_NO_COINCIDE', 'ERROR_LECTURA') NOT NULL,
    `descripcion` VARCHAR(500) NOT NULL,
    `detectado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `detectado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `resuelto` TINYINT(1) NOT NULL DEFAULT 0,
    `resuelto_en` DATETIME NULL,
    `resolucion_notas` VARCHAR(500) NULL,
    CONSTRAINT `fk_dinc_documento` FOREIGN KEY (`documento_emitido_id`) REFERENCES `documentos_emitidos` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_dinc_actor` FOREIGN KEY (`detectado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX `idx_dinc_documento` (`documento_emitido_id`),
    INDEX `idx_dinc_resuelto` (`resuelto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Registro de auditoría de inconsistencias físicas documentales';

-- ----------------------------------------------------------------------------
-- SEMILLAS DOCUMENTOS-1 (D-079): Permisos, Plantilla Canónica V1 y Menú
-- ----------------------------------------------------------------------------
INSERT INTO `permisos` (`codigo`, `nombre`, `descripcion`, `modulo`, `estado`, `es_sistema`) VALUES
('documentos.ver', 'Ver documentos', 'Ver catálogo de plantillas y documentos emitidos', 'DOCUMENTOS', 'ACTIVO', 1),
('documentos.emitir', 'Emitir documentos', 'Emitir documentos oficiales y contratos PDF', 'DOCUMENTOS', 'ACTIVO', 1),
('documentos.descargar', 'Descargar documentos', 'Descargar archivos PDF emitidos', 'DOCUMENTOS', 'ACTIVO', 1),
('documentos.regenerar', 'Regenerar documentos', 'Regenerar archivos PDF desde snapshot ante incidencias', 'DOCUMENTOS', 'ACTIVO', 1),
('documentos.anular', 'Anular documentos', 'Anular documentos oficiales emitidos', 'DOCUMENTOS', 'ACTIVO', 1),
('documentos.plantillas.gestionar', 'Gestionar plantillas', 'Crear y editar versiones de plantillas documentales', 'DOCUMENTOS', 'ACTIVO', 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

INSERT INTO `roles_permisos` (`rol_id`, `permiso_id`)
SELECT r.id, p.id
FROM `roles` r
CROSS JOIN `permisos` p
WHERE r.codigo = 'SUPERADMINISTRADOR'
  AND p.codigo LIKE 'documentos.%'
ON DUPLICATE KEY UPDATE `rol_id` = VALUES(`rol_id`);

INSERT INTO `documento_plantillas` (
    `id`, `codigo`, `nombre`, `descripcion`, `origen_tipo_permitido`,
    `orientacion`, `tamano_papel`, `requiere_membrete`, `archivo_membrete_fondo`,
    `margen_superior_mm`, `margen_inferior_mm`, `margen_izquierdo_mm`, `margen_derecho_mm`,
    `estado`
) VALUES (
    1,
    'CONTRATO_ARRENDAMIENTO',
    'Contrato de Arrendamiento Inmobiliario',
    'Plantilla oficial canónica A4 para formalización de contratos de arrendamiento',
    'ARRENDAMIENTO',
    'portrait',
    'A4',
    1,
    'membrete_a4_canonica_v1.png',
    35,
    28,
    20,
    20,
    'ACTIVO'
) ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'documentos_motor', 'Documentos', 'fa-solid fa-file-invoice', '/documentos', 1, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'documentos' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'documentos.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);

-- ----------------------------------------------------------------------------
-- 61. Solicitudes de Compra / Requerimientos Internos (COMPRAS-1 / D-080)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `compra_solicitudes` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'Formato SOL-YYYYMM-XXXX',
    `departamento_area` VARCHAR(80) NOT NULL COMMENT 'Pisos, Recepción, Mantenimiento, etc.',
    `almacen_destino_id` BIGINT UNSIGNED NULL COMMENT 'Almacén físico sugerido',
    `unidad_destino_id` BIGINT UNSIGNED NULL COMMENT 'Habitación/unidad específica si aplica',
    `fecha_limite_requerida` DATE NOT NULL,
    `justificacion` TEXT NOT NULL,
    `estado` ENUM('BORRADOR', 'PENDIENTE_APROBACION', 'APROBADA', 'RECHAZADA', 'ATENDIDA', 'ANULADA') NOT NULL DEFAULT 'BORRADOR',
    `motivo_rechazo` TEXT NULL,
    `solicitado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `aprobado_por_actor_id` BIGINT UNSIGNED NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_csol_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_csol_area_no_vacia` CHECK (`departamento_area` <> ''),
    CONSTRAINT `chk_csol_justificacion_no_vacia` CHECK (`justificacion` <> ''),
    CONSTRAINT `fk_csol_almacen` FOREIGN KEY (`almacen_destino_id`) REFERENCES `inventario_ubicaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_csol_unidad` FOREIGN KEY (`unidad_destino_id`) REFERENCES `unidades` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_csol_solicitante` FOREIGN KEY (`solicitado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_csol_aprobador` FOREIGN KEY (`aprobado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uq_csol_codigo` (`codigo`),
    INDEX `idx_csol_estado` (`estado`),
    INDEX `idx_csol_area` (`departamento_area`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Requerimientos internos de compras de bienes o servicios';

-- ----------------------------------------------------------------------------
-- 62. Líneas de Solicitud de Compra (COMPRAS-1 / D-080)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `compra_solicitud_lineas` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `solicitud_id` BIGINT UNSIGNED NOT NULL,
    `tipo_linea` ENUM('BIEN', 'SERVICIO') NOT NULL DEFAULT 'BIEN',
    `articulo_id` BIGINT UNSIGNED NULL,
    `descripcion_servicio` VARCHAR(255) NULL,
    `cantidad_solicitada` DECIMAL(15,4) NOT NULL,
    `especificaciones_tecnicas` TEXT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_cslin_tipo_consistente` CHECK (
        (`tipo_linea` = 'BIEN' AND `articulo_id` IS NOT NULL) OR
        (`tipo_linea` = 'SERVICIO' AND `descripcion_servicio` IS NOT NULL AND `descripcion_servicio` <> '')
    ),
    CONSTRAINT `chk_cslin_cant_positiva` CHECK (`cantidad_solicitada` > 0),
    CONSTRAINT `fk_cslin_solicitud` FOREIGN KEY (`solicitud_id`) REFERENCES `compra_solicitudes` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cslin_articulo` FOREIGN KEY (`articulo_id`) REFERENCES `inventario_articulos` (`id`) ON DELETE RESTRICT,
    INDEX `idx_cslin_solicitud` (`solicitud_id`),
    INDEX `idx_cslin_articulo` (`articulo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Detalle de bienes o servicios solicitados';

-- ----------------------------------------------------------------------------
-- 63. Órdenes de Compra (COMPRAS-1 / D-080)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `compra_ordenes` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'Formato OC-YYYYMM-XXXX',
    `solicitud_id` BIGINT UNSIGNED NULL COMMENT 'Opcional, NULL para órdenes directas',
    `proveedor_id` BIGINT UNSIGNED NOT NULL,
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `condicion_pago` ENUM('CONTADO', 'CREDITO_15D', 'CREDITO_30D', 'ADELANTADO') NOT NULL DEFAULT 'CONTADO',
    `almacen_entrega_id` BIGINT UNSIGNED NULL COMMENT 'Almacén físico receptor para bienes',
    `fecha_entrega_esperada` DATE NOT NULL,
    `notas_comerciales` TEXT NULL,
    `subtotal` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `impuesto_total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `descuento_total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `estado_comercial` ENUM('BORRADOR', 'APROBADA', 'CERRADA', 'CANCELADA') NOT NULL DEFAULT 'BORRADOR',
    `estado_recepcion` ENUM('SIN_RECEPCION', 'RECEPCION_PARCIAL', 'RECEPCION_TOTAL') NOT NULL DEFAULT 'SIN_RECEPCION',
    `estado_facturacion` ENUM('SIN_FACTURAR', 'FACTURADA_PARCIAL', 'FACTURADA_TOTAL') NOT NULL DEFAULT 'SIN_FACTURAR',
    `estado_pago` ENUM('PENDIENTE', 'PAGADO_PARCIAL', 'PAGADO_TOTAL') NOT NULL DEFAULT 'PENDIENTE',
    `creado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `aprobado_por_actor_id` BIGINT UNSIGNED NULL,
    `motivo_cancelacion` TEXT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_cord_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_cord_subtotal_no_negativo` CHECK (`subtotal` >= 0),
    CONSTRAINT `chk_cord_impuesto_no_negativo` CHECK (`impuesto_total` >= 0),
    CONSTRAINT `chk_cord_descuento_no_negativo` CHECK (`descuento_total` >= 0),
    CONSTRAINT `chk_cord_total_no_negativo` CHECK (`total` >= 0),
    CONSTRAINT `fk_cord_solicitud` FOREIGN KEY (`solicitud_id`) REFERENCES `compra_solicitudes` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_cord_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_cord_almacen` FOREIGN KEY (`almacen_entrega_id`) REFERENCES `inventario_ubicaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_cord_creador` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_cord_aprobador` FOREIGN KEY (`aprobado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uq_cord_codigo` (`codigo`),
    INDEX `idx_cord_proveedor` (`proveedor_id`),
    INDEX `idx_cord_estado_comercial` (`estado_comercial`),
    INDEX `idx_cord_estado_recepcion` (`estado_recepcion`),
    INDEX `idx_cord_estado_facturacion` (`estado_facturacion`),
    INDEX `idx_cord_estado_pago` (`estado_pago`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Compromisos contractuales y comerciales de compra formal';

-- ----------------------------------------------------------------------------
-- 64. Líneas de Orden de Compra (COMPRAS-1 / D-080)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `compra_orden_lineas` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `orden_compra_id` BIGINT UNSIGNED NOT NULL,
    `tipo_linea` ENUM('BIEN', 'SERVICIO') NOT NULL DEFAULT 'BIEN',
    `articulo_id` BIGINT UNSIGNED NULL,
    `descripcion_servicio` VARCHAR(255) NULL,
    `cantidad_pactada` DECIMAL(15,4) NOT NULL,
    `precio_unitario` DECIMAL(15,4) NOT NULL,
    `subtotal_linea` DECIMAL(15,2) NOT NULL,
    `impuesto_linea` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total_linea` DECIMAL(15,2) NOT NULL,
    `cantidad_aceptada` DECIMAL(15,4) NOT NULL DEFAULT 0.0000 COMMENT 'Acumulado aceptado en recepciones/conformidades',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_colin_tipo_consistente` CHECK (
        (`tipo_linea` = 'BIEN' AND `articulo_id` IS NOT NULL) OR
        (`tipo_linea` = 'SERVICIO' AND `descripcion_servicio` IS NOT NULL AND `descripcion_servicio` <> '')
    ),
    CONSTRAINT `chk_colin_cant_positiva` CHECK (`cantidad_pactada` > 0),
    CONSTRAINT `chk_colin_precio_no_negativo` CHECK (`precio_unitario` >= 0),
    CONSTRAINT `chk_colin_subtotal_no_negativo` CHECK (`subtotal_linea` >= 0),
    CONSTRAINT `chk_colin_total_no_negativo` CHECK (`total_linea` >= 0),
    CONSTRAINT `chk_colin_aceptada_no_negativa` CHECK (`cantidad_aceptada` >= 0),
    CONSTRAINT `fk_colin_orden` FOREIGN KEY (`orden_compra_id`) REFERENCES `compra_ordenes` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_colin_articulo` FOREIGN KEY (`articulo_id`) REFERENCES `inventario_articulos` (`id`) ON DELETE RESTRICT,
    INDEX `idx_colin_orden` (`orden_compra_id`),
    INDEX `idx_colin_articulo` (`articulo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Líneas tipadas de la orden de compra con cantidades y precios';

-- ----------------------------------------------------------------------------
-- 65. Recepciones Físicas en Almacén (COMPRAS-1 / D-080)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `compra_recepciones` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'Formato REC-YYYYMM-XXXX',
    `orden_compra_id` BIGINT UNSIGNED NOT NULL,
    `almacen_id` BIGINT UNSIGNED NOT NULL,
    `numero_guia_remision` VARCHAR(50) NULL,
    `fecha_recepcion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `observaciones` TEXT NULL,
    `recibido_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_crec_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `fk_crec_orden` FOREIGN KEY (`orden_compra_id`) REFERENCES `compra_ordenes` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_crec_almacen` FOREIGN KEY (`almacen_id`) REFERENCES `inventario_ubicaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_crec_receptor` FOREIGN KEY (`recibido_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uq_crec_codigo` (`codigo`),
    INDEX `idx_crec_orden` (`orden_compra_id`),
    INDEX `idx_crec_almacen` (`almacen_id`),
    INDEX `idx_crec_fecha` (`fecha_recepcion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Ingresos físicos de bienes a almacén contrastados con la orden';

-- ----------------------------------------------------------------------------
-- 66. Líneas de Recepción Física (COMPRAS-1 / D-080)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `compra_recepcion_lineas` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `recepcion_id` BIGINT UNSIGNED NOT NULL,
    `orden_linea_id` BIGINT UNSIGNED NOT NULL,
    `articulo_id` BIGINT UNSIGNED NOT NULL,
    `cantidad_recibida` DECIMAL(15,4) NOT NULL,
    `cantidad_aceptada` DECIMAL(15,4) NOT NULL,
    `cantidad_rechazada` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `motivo_rechazo` VARCHAR(255) NULL,
    `movimiento_inventario_id` BIGINT UNSIGNED NULL COMMENT 'FK a Kardex ENTRADA_COMPRA bajo D-078',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_crlin_cant_consistente` CHECK (`cantidad_recibida` = `cantidad_aceptada` + `cantidad_rechazada`),
    CONSTRAINT `chk_crlin_recibida_positiva` CHECK (`cantidad_recibida` > 0),
    CONSTRAINT `chk_crlin_aceptada_no_neg` CHECK (`cantidad_aceptada` >= 0),
    CONSTRAINT `chk_crlin_rechazada_no_neg` CHECK (`cantidad_rechazada` >= 0),
    CONSTRAINT `fk_crlin_recepcion` FOREIGN KEY (`recepcion_id`) REFERENCES `compra_recepciones` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_crlin_orden_linea` FOREIGN KEY (`orden_linea_id`) REFERENCES `compra_orden_lineas` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_crlin_articulo` FOREIGN KEY (`articulo_id`) REFERENCES `inventario_articulos` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_crlin_mov_inv` FOREIGN KEY (`movimiento_inventario_id`) REFERENCES `inventario_movimientos` (`id`) ON DELETE RESTRICT,
    INDEX `idx_crlin_recepcion` (`recepcion_id`),
    INDEX `idx_crlin_orden_linea` (`orden_linea_id`),
    INDEX `idx_crlin_articulo` (`articulo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Detalle físico de bienes aceptados hacia Kardex y rechazados';

-- ----------------------------------------------------------------------------
-- 67. Actas de Conformidad de Servicio (COMPRAS-1 / D-080)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `compra_conformidades` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'Formato CONF-YYYYMM-XXXX',
    `orden_compra_id` BIGINT UNSIGNED NOT NULL,
    `orden_linea_id` BIGINT UNSIGNED NOT NULL,
    `fecha_conformidad` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `informe_trabajo_realizado` TEXT NOT NULL,
    `aprobado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_cconf_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_cconf_informe_no_vacio` CHECK (`informe_trabajo_realizado` <> ''),
    CONSTRAINT `fk_cconf_orden` FOREIGN KEY (`orden_compra_id`) REFERENCES `compra_ordenes` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_cconf_orden_linea` FOREIGN KEY (`orden_linea_id`) REFERENCES `compra_orden_lineas` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_cconf_aprobador` FOREIGN KEY (`aprobado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uq_cconf_codigo` (`codigo`),
    INDEX `idx_cconf_orden` (`orden_compra_id`),
    INDEX `idx_cconf_orden_linea` (`orden_linea_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Actas de conformidad técnica para líneas de servicios (cero Kardex)';

-- ----------------------------------------------------------------------------
-- 68. Comprobantes Fiscales del Proveedor (COMPRAS-1 / D-080)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `compra_comprobantes` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `proveedor_id` BIGINT UNSIGNED NOT NULL,
    `orden_compra_id` BIGINT UNSIGNED NOT NULL,
    `tipo_comprobante` ENUM('FACTURA', 'BOLETA', 'RECIBO_HONORARIOS', 'NOTA_CREDITO', 'NOTA_DEBITO') NOT NULL,
    `serie` VARCHAR(10) NOT NULL,
    `numero` VARCHAR(20) NOT NULL,
    `fecha_emision` DATE NOT NULL,
    `fecha_vencimiento` DATE NOT NULL,
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `subtotal` DECIMAL(15,2) NOT NULL,
    `impuesto` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total` DECIMAL(15,2) NOT NULL,
    `estado_matching` ENUM('CONFORME', 'CON_DIFERENCIA', 'OBSERVADO') NOT NULL DEFAULT 'CONFORME',
    `observaciones_matching` TEXT NULL,
    `registrado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_ccomp_serie_no_vacia` CHECK (`serie` <> ''),
    CONSTRAINT `chk_ccomp_numero_no_vacio` CHECK (`numero` <> ''),
    CONSTRAINT `chk_ccomp_total_positivo` CHECK (`total` > 0),
    CONSTRAINT `fk_ccomp_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_ccomp_orden` FOREIGN KEY (`orden_compra_id`) REFERENCES `compra_ordenes` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_ccomp_registrador` FOREIGN KEY (`registrado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uq_ccomp_fiscal` (`proveedor_id`, `tipo_comprobante`, `serie`, `numero`),
    INDEX `idx_ccomp_orden` (`orden_compra_id`),
    INDEX `idx_ccomp_matching` (`estado_matching`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Comprobantes tributarios de compras recibidos de proveedores';

-- ----------------------------------------------------------------------------
-- 69. Aplicaciones y Matching M:N Comprobante vs Recepciones/Conformidades
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `compra_comprobante_aplicaciones` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `comprobante_id` BIGINT UNSIGNED NOT NULL,
    `recepcion_linea_id` BIGINT UNSIGNED NULL,
    `conformidad_id` BIGINT UNSIGNED NULL,
    `monto_aplicado` DECIMAL(15,2) NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_ccapp_origen_exclusivo` CHECK (
        (`recepcion_linea_id` IS NOT NULL AND `conformidad_id` IS NULL) OR
        (`recepcion_linea_id` IS NULL AND `conformidad_id` IS NOT NULL)
    ),
    CONSTRAINT `chk_ccapp_monto_positivo` CHECK (`monto_aplicado` > 0),
    CONSTRAINT `fk_ccapp_comprobante` FOREIGN KEY (`comprobante_id`) REFERENCES `compra_comprobantes` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ccapp_recepcion_linea` FOREIGN KEY (`recepcion_linea_id`) REFERENCES `compra_recepcion_lineas` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_ccapp_conformidad` FOREIGN KEY (`conformidad_id`) REFERENCES `compra_conformidades` (`id`) ON DELETE RESTRICT,
    INDEX `idx_ccapp_comprobante` (`comprobante_id`),
    INDEX `idx_ccapp_rec_linea` (`recepcion_linea_id`),
    INDEX `idx_ccapp_conformidad` (`conformidad_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Vínculo formal 3-way matching entre comprobante y recepciones/conformidades';

-- ----------------------------------------------------------------------------
-- 70. Cuentas por Pagar (Obligaciones Financieras Devengadas) (COMPRAS-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cuentas_por_pagar` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'Formato CXP-YYYYMM-XXXX',
    `proveedor_id` BIGINT UNSIGNED NOT NULL,
    `comprobante_id` BIGINT UNSIGNED NOT NULL,
    `orden_compra_id` BIGINT UNSIGNED NOT NULL,
    `monto_total` DECIMAL(15,2) NOT NULL,
    `monto_amortizado` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `saldo_pendiente` DECIMAL(15,2) NOT NULL,
    `fecha_vencimiento` DATE NOT NULL,
    `estado` ENUM('PENDIENTE', 'AMORTIZADA_PARCIAL', 'LIQUIDADA', 'ANULADA') NOT NULL DEFAULT 'PENDIENTE',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_cxp_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_cxp_total_positivo` CHECK (`monto_total` > 0),
    CONSTRAINT `chk_cxp_amort_no_negativa` CHECK (`monto_amortizado` >= 0),
    CONSTRAINT `chk_cxp_saldo_no_negativo` CHECK (`saldo_pendiente` >= 0),
    CONSTRAINT `chk_cxp_coherencia_saldos` CHECK (`saldo_pendiente` = `monto_total` - `monto_amortizado`),
    CONSTRAINT `fk_cxp_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_cxp_comprobante` FOREIGN KEY (`comprobante_id`) REFERENCES `compra_comprobantes` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_cxp_orden` FOREIGN KEY (`orden_compra_id`) REFERENCES `compra_ordenes` (`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uq_cxp_codigo` (`codigo`),
    UNIQUE KEY `uq_cxp_comprobante` (`comprobante_id`),
    INDEX `idx_cxp_proveedor` (`proveedor_id`),
    INDEX `idx_cxp_orden` (`orden_compra_id`),
    INDEX `idx_cxp_estado` (`estado`),
    INDEX `idx_cxp_vencimiento` (`fecha_vencimiento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Pasivos formales devengados con proveedores';

-- ----------------------------------------------------------------------------
-- 71. Pagos y Amortizaciones de Cuentas por Pagar (COMPRAS-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cxp_pagos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `cuenta_pagar_id` BIGINT UNSIGNED NOT NULL,
    `medio_pago` ENUM('EFECTIVO_CAJA', 'TRANSFERENCIA_BANCARIA', 'CHEQUE', 'BILLETERA_DIGITAL') NOT NULL,
    `monto` DECIMAL(15,2) NOT NULL,
    `fecha_pago` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `numero_operacion_bancaria` VARCHAR(100) NULL,
    `movimiento_caja_id` BIGINT UNSIGNED NULL COMMENT 'FK a movimientos_caja si se pagó desde caja chica',
    `movimiento_bancario_id` BIGINT UNSIGNED NULL COMMENT 'FK a movimientos_bancarios si fue transferencia/banco',
    `notas` TEXT NULL,
    `registrado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_cxpp_monto_positivo` CHECK (`monto` > 0),
    CONSTRAINT `fk_cxpp_cxp` FOREIGN KEY (`cuenta_pagar_id`) REFERENCES `cuentas_por_pagar` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_cxpp_mov_caja` FOREIGN KEY (`movimiento_caja_id`) REFERENCES `movimientos_caja` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_cxpp_mov_banco` FOREIGN KEY (`movimiento_bancario_id`) REFERENCES `movimientos_bancarios` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_cxpp_registrador` FOREIGN KEY (`registrado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT,
    INDEX `idx_cxpp_cxp` (`cuenta_pagar_id`),
    INDEX `idx_cxpp_fecha` (`fecha_pago`),
    INDEX `idx_cxpp_mov_caja` (`movimiento_caja_id`),
    INDEX `idx_cxpp_mov_banco` (`movimiento_bancario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Egresos financieros aplicados a cuentas por pagar';

-- ----------------------------------------------------------------------------
-- 72. Historial Inmutable de Estados y Auditoría D-061 (COMPRAS-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `compra_historial_estados` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `entidad_tipo` ENUM('SOLICITUD', 'ORDEN_COMPRA', 'CUENTA_POR_PAGAR') NOT NULL,
    `entidad_id` BIGINT UNSIGNED NOT NULL,
    `estado_anterior` VARCHAR(50) NULL,
    `estado_nuevo` VARCHAR(50) NOT NULL,
    `actor_id` BIGINT UNSIGNED NOT NULL,
    `motivo` TEXT NULL,
    `correlacion_id` VARCHAR(64) NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_chist_actor` FOREIGN KEY (`actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT,
    INDEX `idx_chist_entidad` (`entidad_tipo`, `entidad_id`),
    INDEX `idx_chist_actor` (`actor_id`),
    INDEX `idx_chist_fecha` (`creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Trazabilidad inmutable de transiciones y auditoría D-061 para compras';

-- Permisos y Menú para Compras
INSERT INTO `permisos` (`codigo`, `nombre`, `modulo`, `descripcion`, `creado_en`) VALUES
('compras.ver', 'Ver módulo de compras', 'COMPRAS', 'Acceso a la vista y listados de abastecimiento y compras', NOW()),
('compras.solicitudes.crear', 'Crear solicitudes de compra', 'COMPRAS', 'Registrar requerimientos internos de compra', NOW()),
('compras.solicitudes.aprobar', 'Aprobar solicitudes de compra', 'COMPRAS', 'Aprobar o rechazar requerimientos internos', NOW()),
('compras.ordenes.crear', 'Crear órdenes de compra', 'COMPRAS', 'Formular órdenes de compra directas o desde solicitud', NOW()),
('compras.ordenes.aprobar', 'Aprobar órdenes de compra', 'COMPRAS', 'Aprobar formalmente y congelar condiciones de la OC', NOW()),
('compras.recepciones.registrar', 'Registrar recepciones físicas', 'COMPRAS', 'Recibir bienes en almacén y afectar Kardex', NOW()),
('compras.conformidad.registrar', 'Registrar conformidad de servicios', 'COMPRAS', 'Emitir actas de conformidad técnica para servicios', NOW()),
('compras.comprobantes.registrar', 'Registrar comprobantes de proveedor', 'COMPRAS', 'Registrar facturas/boletas y ejecutar 3-way matching', NOW()),
('compras.cuentas_pagar.ver', 'Ver cuentas por pagar', 'COMPRAS', 'Consultar obligaciones financieras con proveedores', NOW()),
('compras.pagos.registrar', 'Registrar pagos a proveedores', 'COMPRAS', 'Amortizar cuentas por pagar con egresos de caja o banco', NOW()),
('compras.anular', 'Anular operaciones de compra', 'COMPRAS', 'Cancelar órdenes o anular comprobantes justificados', NOW())
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

INSERT IGNORE INTO `roles_permisos` (`rol_id`, `permiso_id`)
SELECT 1, p.id FROM `permisos` p WHERE p.codigo LIKE 'compras.%';

INSERT IGNORE INTO `roles_permisos` (`rol_id`, `permiso_id`)
SELECT 2, p.id FROM `permisos` p WHERE p.codigo LIKE 'compras.%';

INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'compras', 'Compras', 'fa-solid fa-cart-shopping', '/compras', 3, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'abastecimiento' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'compras.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);

INSERT INTO `documento_plantillas` (
    `codigo`, `nombre`, `descripcion`, `origen_tipo_permitido`, `orientacion`,
    `tamano_papel`, `requiere_membrete`, `archivo_membrete_fondo`,
    `margen_superior_mm`, `margen_inferior_mm`, `margen_izquierdo_mm`, `margen_derecho_mm`, `estado`
) VALUES (
    'ORDEN_COMPRA',
    'Orden de Compra Oficial A4',
    'Documento comercial institucional emitido a proveedores con detalle de bienes/servicios pactados',
    'COMPRA',
    'PORTRAIT',
    'A4',
    1,
    'storage/membretes/membrete_a4_canonica_v1.png',
    35, 28, 20, 20,
    'ACTIVO'
) ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

-- Versión 1 activa para la plantilla ORDEN_COMPRA
INSERT INTO `documento_plantilla_versiones` (
    `plantilla_id`, `numero_version`, `titulo_documento`,
    `cuerpo_html`, `estilos_css`, `notas_version`, `es_activa`, `creado_por_actor_id`
)
SELECT
    p.`id`,
    1,
    'ORDEN DE COMPRA OFICIAL',
    '<div class=\"documento-orden-compra\">
    <div class=\"encabezado-orden\">
        <h1 class=\"titulo-principal\">ORDEN DE COMPRA</h1>
        <p class=\"subtitulo-folio\">FOLIO N°: <strong>{{documento.folio}}</strong> | CÓDIGO OC: <strong>{{orden.codigo}}</strong></p>
    </div>
    <div class=\"seccion-datos\">
        <table class=\"tabla-info\">
            <tr>
                <td class=\"campo-etiqueta\"><strong>Proveedor:</strong></td>
                <td class=\"campo-valor\">{{proveedor.razon_social}} (RUC/DOC: {{proveedor.numero_documento}})</td>
                <td class=\"campo-etiqueta\"><strong>Fecha Emisión:</strong></td>
                <td class=\"campo-valor\">{{orden.fecha}}</td>
            </tr>
            <tr>
                <td class=\"campo-etiqueta\"><strong>Contacto:</strong></td>
                <td class=\"campo-valor\">{{proveedor.contacto}} - {{proveedor.telefono}}</td>
                <td class=\"campo-etiqueta\"><strong>Fecha Entrega:</strong></td>
                <td class=\"campo-valor\">{{orden.fecha_entrega}}</td>
            </tr>
            <tr>
                <td class=\"campo-etiqueta\"><strong>Dirección:</strong></td>
                <td class=\"campo-valor\">{{proveedor.direccion}}</td>
                <td class=\"campo-etiqueta\"><strong>Condición Pago:</strong></td>
                <td class=\"campo-valor\">{{orden.condicion_pago}}</td>
            </tr>
            <tr>
                <td class=\"campo-etiqueta\"><strong>Almacén Entrega:</strong></td>
                <td class=\"campo-valor\" colspan=\"3\">{{almacen.nombre}} - {{almacen.direccion}}</td>
            </tr>
        </table>
    </div>
    <div class=\"seccion-lineas\">
        <h3 class=\"seccion-subtitulo\">Detalle de Bienes y Servicios Solicitados</h3>
        {{tabla_lineas}}
    </div>
    <div class=\"seccion-totales\">
        <table class=\"tabla-totales\">
            <tr>
                <td class=\"tot-etiqueta\">Subtotal:</td>
                <td class=\"tot-valor\">{{totales.moneda}} {{totales.subtotal}}</td>
            </tr>
            <tr>
                <td class=\"tot-etiqueta\">Impuestos (IGV):</td>
                <td class=\"tot-valor\">{{totales.moneda}} {{totales.impuesto}}</td>
            </tr>
            <tr>
                <td class=\"tot-etiqueta font-bold\">TOTAL:</td>
                <td class=\"tot-valor font-bold\">{{totales.moneda}} {{totales.total}}</td>
            </tr>
        </table>
        <p class=\"monto-texto\">SON: {{totales.texto}}</p>
    </div>
    <div class=\"seccion-observaciones\">
        <p><strong>Observaciones / Notas:</strong> {{orden.notas}}</p>
    </div>
    <div class=\"seccion-firmas\">
        <table class=\"tabla-firmas\">
            <tr>
                <td class=\"firma-caja\">
                    <div class=\"linea-firma\"></div>
                    <p>Emitido por: Compras y Abastecimiento<br>Camargo Hostelería S.A.C.</p>
                </td>
                <td class=\"firma-caja\">
                    <div class=\"linea-firma\"></div>
                    <p>Aprobado por: Gerencia / Administración<br>Camargo Hostelería S.A.C.</p>
                </td>
            </tr>
        </table>
    </div>
</div>',
    'body { font-family: Helvetica, Arial, sans-serif; font-size: 10pt; color: #222; }
.documento-orden-compra { width: 100%; margin: 0 auto; }
.encabezado-orden { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #0d6efd; padding-bottom: 10px; }
.titulo-principal { font-size: 18pt; margin: 0; color: #0d6efd; text-transform: uppercase; }
.subtitulo-folio { font-size: 10pt; margin: 5px 0 0 0; color: #555; }
.tabla-info { width: 100%; margin-bottom: 15px; border-collapse: collapse; }
.tabla-info td { padding: 4px 6px; font-size: 9pt; }
.campo-etiqueta { width: 18%; color: #333; }
.campo-valor { width: 32%; }
.seccion-subtitulo { font-size: 11pt; border-bottom: 1px solid #ccc; padding-bottom: 4px; margin-bottom: 10px; color: #333; }
.tabla-lineas-doc { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
.tabla-lineas-doc th { background-color: #f2f5f9; border: 1px solid #ccc; padding: 6px; font-size: 8.5pt; text-align: center; }
.tabla-lineas-doc td { border: 1px solid #ddd; padding: 5px 6px; font-size: 8.5pt; }
.seccion-totales { width: 100%; margin-top: 10px; }
.tabla-totales { float: right; width: 40%; border-collapse: collapse; margin-bottom: 10px; }
.tabla-totales td { padding: 4px 8px; font-size: 9.5pt; }
.tot-etiqueta { text-align: right; }
.tot-valor { text-align: right; }
.font-bold { font-weight: bold; }
.monto-texto { clear: both; font-style: italic; font-size: 8.5pt; color: #444; padding-top: 5px; }
.seccion-observaciones { margin-top: 15px; font-size: 8.5pt; border-left: 3px solid #0d6efd; padding-left: 8px; }
.seccion-firmas { margin-top: 50px; width: 100%; }
.tabla-firmas { width: 100%; border-collapse: collapse; }
.firma-caja { width: 50%; text-align: center; padding: 0 40px; font-size: 8.5pt; }
.linea-firma { border-top: 1px solid #333; margin-bottom: 5px; }',
    'Versión canónica inicial para emisión oficial de órdenes de compra con membrete A4',
    1,
    1
FROM `documento_plantillas` p
WHERE p.`codigo` = 'ORDEN_COMPRA'
ON DUPLICATE KEY UPDATE `titulo_documento` = VALUES(`titulo_documento`);

-- ----------------------------------------------------------------------------
-- 73. Catálogo Maestro de Suministros y Servicios Periódicos (SUMINISTROS-1 / D-081)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `suministros` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(50) NOT NULL COMMENT 'Identificador único en mayúsculas, ej. LUZ_ELECTRICA, AGUA_POTABLE, INTERNET_FIBRA',
    `nombre` VARCHAR(100) NOT NULL COMMENT 'Denominación descriptiva para vistas y reportes',
    `modalidad` ENUM('MEDIDO', 'FIJO_PERIODICO') NOT NULL COMMENT 'MEDIDO exige medidor y lecturas, FIJO_PERIODICO opera por cuota mensual o periódica',
    `unidad_medida` VARCHAR(20) NOT NULL COMMENT 'kWh, m3, MES, etc.',
    `descripcion` TEXT NULL,
    `permite_rollover` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 si el medidor asociado admite reinicio de dial con valor máximo, 0 rechaza cualquier lectura decreciente',
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_sum_codigo` (`codigo`),
    CONSTRAINT `chk_sum_codigo_no_vacio` CHECK (`codigo` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Catálogo maestro de suministros y servicios periódicos';

-- ----------------------------------------------------------------------------
-- 74. Tarifas con Vigencia Histórica y Precedencia Jerárquica
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `suministro_tarifas` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `suministro_id` BIGINT UNSIGNED NOT NULL,
    `ambito_tipo` ENUM('GLOBAL', 'PROPIEDAD', 'UNIDAD') NOT NULL DEFAULT 'GLOBAL',
    `propiedad_id` BIGINT UNSIGNED NULL COMMENT 'Obligatorio si ambito_tipo es PROPIEDAD o UNIDAD',
    `unidad_id` BIGINT UNSIGNED NULL COMMENT 'Obligatorio si ambito_tipo es UNIDAD',
    `vigencia_desde` DATE NOT NULL COMMENT 'Fecha inicial de vigencia (inclusiva)',
    `vigencia_hasta` DATE NULL COMMENT 'Fecha final de vigencia (inclusiva). NULL indica vigente indefinidamente',
    `valor_unitario` DECIMAL(15,4) NOT NULL COMMENT 'Costo unitario por kWh, m3 o cuota fija en moneda funcional',
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `creado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_star_suministro_vigencia` (`suministro_id`, `vigencia_desde`, `vigencia_hasta`),
    KEY `idx_star_ambito` (`ambito_tipo`, `propiedad_id`, `unidad_id`),
    CONSTRAINT `fk_star_suministro` FOREIGN KEY (`suministro_id`) REFERENCES `suministros` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_star_propiedad` FOREIGN KEY (`propiedad_id`) REFERENCES `propiedades` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_star_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_star_actor_creador` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `chk_star_vigencia` CHECK (`vigencia_hasta` IS NULL OR `vigencia_hasta` >= `vigencia_desde`),
    CONSTRAINT `chk_star_valor_positivo` CHECK (`valor_unitario` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Tarifas de suministros con vigencia histórica y ámbito jerárquico';

-- ----------------------------------------------------------------------------
-- 75. Medidores Físicos e Historial de Reemplazos
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `suministro_medidores` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `suministro_id` BIGINT UNSIGNED NOT NULL,
    `propiedad_id` BIGINT UNSIGNED NOT NULL,
    `unidad_id` BIGINT UNSIGNED NULL COMMENT 'NULL si es medidor general del edificio o inmueble',
    `codigo` VARCHAR(50) NOT NULL COMMENT 'Código de placa, serie o identificación física',
    `marca` VARCHAR(100) NULL,
    `modelo` VARCHAR(100) NULL,
    `fecha_instalacion` DATE NOT NULL,
    `fecha_retiro` DATE NULL,
    `lectura_inicial` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `capacidad_maxima` DECIMAL(15,4) NULL COMMENT 'Capacidad máxima de dial para rollover (ej. 99999.9999)',
    `estado` ENUM('ACTIVO', 'EN_MANTENIMIENTO', 'REEMPLAZADO', 'DE_BAJA') NOT NULL DEFAULT 'ACTIVO',
    `observaciones` TEXT NULL,
    `creado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `medidor_activo_idx` VARCHAR(80) GENERATED ALWAYS AS (
        IF(`estado` = 'ACTIVO' AND `unidad_id` IS NOT NULL, CONCAT(`suministro_id`, '_', `unidad_id`), NULL)
    ) STORED,
    UNIQUE KEY `uq_med_propiedad_codigo` (`propiedad_id`, `codigo`),
    UNIQUE KEY `uq_med_unidad_suministro_activo` (`medidor_activo_idx`),
    KEY `idx_med_suministro_unidad` (`suministro_id`, `unidad_id`),
    CONSTRAINT `fk_med_suministro` FOREIGN KEY (`suministro_id`) REFERENCES `suministros` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_med_propiedad` FOREIGN KEY (`propiedad_id`) REFERENCES `propiedades` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_med_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_med_actor_creador` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `chk_med_fechas` CHECK (`fecha_retiro` IS NULL OR `fecha_retiro` >= `fecha_instalacion`),
    CONSTRAINT `chk_med_lectura_inicial` CHECK (`lectura_inicial` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Medidores físicos instalados en unidades o áreas comunes';

-- ----------------------------------------------------------------------------
-- 76. Historial Append-Only Inmutable de Lecturas de Medidores
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `suministro_lecturas` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `medidor_id` BIGINT UNSIGNED NOT NULL,
    `fecha_lectura` DATE NOT NULL,
    `hora_lectura` TIME NULL,
    `valor_lectura` DECIMAL(15,4) NOT NULL,
    `tipo_evento` ENUM('ORDINARIA', 'CORTE_CONTRACTUAL', 'CORTE_TARIFARIO', 'INSTALACION', 'RETIRO', 'REINICIO_DIAL', 'CORRECCION') NOT NULL DEFAULT 'ORDINARIA',
    `lectura_referencia_id` BIGINT UNSIGNED NULL COMMENT 'Lectura original sustituida cuando tipo_evento = CORRECCION',
    `motivo` VARCHAR(255) NULL,
    `arrendamiento_id` BIGINT UNSIGNED NULL COMMENT 'Vinculación si fue tomada como corte de un contrato específico',
    `estado` ENUM('VALIDA', 'CORREGIDA', 'ANULADA') NOT NULL DEFAULT 'VALIDA',
    `registrado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `correccion_activa_idx` VARCHAR(50) GENERATED ALWAYS AS (
        IF(`estado` = 'VALIDA' AND `tipo_evento` = 'CORRECCION', CAST(`lectura_referencia_id` AS CHAR), NULL)
    ) STORED,
    UNIQUE KEY `uq_lec_correccion_unica` (`correccion_activa_idx`),
    KEY `idx_lec_medidor_fecha` (`medidor_id`, `fecha_lectura`, `id`),
    CONSTRAINT `fk_slec_medidor` FOREIGN KEY (`medidor_id`) REFERENCES `suministro_medidores` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_slec_referencia` FOREIGN KEY (`lectura_referencia_id`) REFERENCES `suministro_lecturas` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_slec_arrendamiento` FOREIGN KEY (`arrendamiento_id`) REFERENCES `arrendamientos` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_slec_actor_creador` FOREIGN KEY (`registrado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `chk_slec_valor_no_negativo` CHECK (`valor_lectura` >= 0),
    CONSTRAINT `chk_slec_motivo_correccion` CHECK (`tipo_evento` <> 'CORRECCION' OR (`lectura_referencia_id` IS NOT NULL AND `motivo` IS NOT NULL AND `motivo` <> ''))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Registro inmutable append-only de lecturas físicas de medidores';

-- ----------------------------------------------------------------------------
-- 77. Liquidaciones de Suministro por Arrendamiento y Período
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `suministro_liquidaciones` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `folio` VARCHAR(30) NOT NULL,
    `suministro_id` BIGINT UNSIGNED NOT NULL,
    `modalidad` ENUM('MEDIDO', 'FIJO_PERIODICO') NOT NULL,
    `propiedad_id` BIGINT UNSIGNED NOT NULL,
    `unidad_id` BIGINT UNSIGNED NOT NULL,
    `arrendamiento_id` BIGINT UNSIGNED NOT NULL,
    `cuenta_folio_id` BIGINT UNSIGNED NOT NULL,
    `cargo_cuenta_id` BIGINT UNSIGNED NOT NULL,
    `periodo_anio` SMALLINT UNSIGNED NOT NULL,
    `periodo_mes` TINYINT UNSIGNED NOT NULL,
    `periodo_desde` DATE NOT NULL,
    `periodo_hasta` DATE NOT NULL,
    `fecha_emision` DATE NOT NULL,
    `fecha_vencimiento` DATE NOT NULL,
    `cantidad_total` DECIMAL(15,4) NOT NULL,
    `subtotal` DECIMAL(15,2) NOT NULL,
    `impuesto_monto` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total` DECIMAL(15,2) NOT NULL,
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `revision` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `liquidacion_previa_id` BIGINT UNSIGNED NULL,
    `estado` ENUM('DEVENGADO', 'ANULADO') NOT NULL DEFAULT 'DEVENGADO',
    `motivo_anulacion` VARCHAR(255) NULL,
    `anulado_en` DATETIME NULL,
    `anulado_por_actor_id` BIGINT UNSIGNED NULL,
    `creado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `liquidacion_activa_idx` VARCHAR(120) GENERATED ALWAYS AS (
        IF(`estado` = 'DEVENGADO', CONCAT(`arrendamiento_id`, '_', `suministro_id`, '_', `periodo_desde`, '_', `periodo_hasta`), NULL)
    ) STORED,
    UNIQUE KEY `uq_sliq_folio` (`folio`),
    UNIQUE KEY `uq_sliq_periodo_activo` (`liquidacion_activa_idx`),
    UNIQUE KEY `uq_sliq_cargo_cuenta` (`cargo_cuenta_id`),
    KEY `idx_sliq_arrendamiento` (`arrendamiento_id`, `estado`),
    KEY `idx_sliq_cuenta_folio` (`cuenta_folio_id`),
    CONSTRAINT `fk_sliq_suministro` FOREIGN KEY (`suministro_id`) REFERENCES `suministros` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_sliq_propiedad` FOREIGN KEY (`propiedad_id`) REFERENCES `propiedades` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_sliq_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_sliq_arrendamiento` FOREIGN KEY (`arrendamiento_id`) REFERENCES `arrendamientos` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_sliq_cuenta_folio` FOREIGN KEY (`cuenta_folio_id`) REFERENCES `cuentas_folios` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_sliq_cargo_cuenta` FOREIGN KEY (`cargo_cuenta_id`) REFERENCES `cargos_cuenta` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_sliq_previa` FOREIGN KEY (`liquidacion_previa_id`) REFERENCES `suministro_liquidaciones` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_sliq_actor_creador` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_sliq_actor_anulador` FOREIGN KEY (`anulado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
    CONSTRAINT `chk_sliq_periodo` CHECK (`periodo_hasta` > `periodo_desde`),
    CONSTRAINT `chk_sliq_total_positivo` CHECK (`total` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Liquidaciones de suministros imputadas a contratos de arrendamiento';

-- ----------------------------------------------------------------------------
-- 78. Tramos de Liquidación de Suministros (Detalle Multitramo)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `suministro_liquidacion_tramos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `liquidacion_id` BIGINT UNSIGNED NOT NULL,
    `numero_tramo` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `medidor_id` BIGINT UNSIGNED NULL,
    `lectura_anterior_id` BIGINT UNSIGNED NULL,
    `lectura_actual_id` BIGINT UNSIGNED NULL,
    `lectura_anterior_valor` DECIMAL(15,4) NULL,
    `lectura_actual_valor` DECIMAL(15,4) NULL,
    `cantidad` DECIMAL(15,4) NOT NULL,
    `tarifa_id` BIGINT UNSIGNED NOT NULL,
    `tarifa_valor` DECIMAL(15,4) NOT NULL,
    `subtotal` DECIMAL(15,2) NOT NULL,
    `impuesto_monto` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total` DECIMAL(15,2) NOT NULL,
    `fecha_desde` DATE NOT NULL,
    `fecha_hasta` DATE NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_slt_liq_tramo` (`liquidacion_id`, `numero_tramo`),
    CONSTRAINT `fk_slt_liquidacion` FOREIGN KEY (`liquidacion_id`) REFERENCES `suministro_liquidaciones` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_slt_medidor` FOREIGN KEY (`medidor_id`) REFERENCES `suministro_medidores` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_slt_lec_ant` FOREIGN KEY (`lectura_anterior_id`) REFERENCES `suministro_lecturas` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_slt_lec_act` FOREIGN KEY (`lectura_actual_id`) REFERENCES `suministro_lecturas` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_slt_tarifa` FOREIGN KEY (`tarifa_id`) REFERENCES `suministro_tarifas` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `chk_slt_cantidad_positiva` CHECK (`cantidad` >= 0),
    CONSTRAINT `chk_slt_total_positivo` CHECK (`total` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Tramos de consumo y tarifas aplicadas en una liquidación';

-- Permisos RBAC de Suministros
INSERT INTO `permisos` (`codigo`, `nombre`, `modulo`, `descripcion`, `creado_en`) VALUES
('suministros.ver', 'Ver suministros', 'SUMINISTROS', 'Permite consultar suministros, medidores, tarifas y liquidaciones', NOW()),
('suministros.gestionar', 'Gestionar suministros', 'SUMINISTROS', 'Permite crear y editar suministros y parámetros del catálogo', NOW()),
('suministros.tarifas.gestionar', 'Gestionar tarifas', 'SUMINISTROS', 'Permite definir tarifas con vigencia y ámbitos jerárquicos', NOW()),
('suministros.medidores.gestionar', 'Gestionar medidores', 'SUMINISTROS', 'Permite instalar, reemplazar y dar de baja medidores físicos', NOW()),
('suministros.lecturas.registrar', 'Registrar lecturas', 'SUMINISTROS', 'Permite ingresar lecturas periódicas, de corte e instalación', NOW()),
('suministros.lecturas.corregir', 'Corregir lecturas', 'SUMINISTROS', 'Permite emitir correcciones auditadas sobre lecturas previas', NOW()),
('suministros.liquidar', 'Liquidar suministros', 'SUMINISTROS', 'Permite computar consumos periódicos y devengar cargos en folios', NOW()),
('suministros.anular', 'Anular liquidaciones', 'SUMINISTROS', 'Permite anular liquidaciones y sus cargos asociados de forma auditada', NOW())
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

INSERT IGNORE INTO `roles_permisos` (`rol_id`, `permiso_id`)
SELECT 1, p.id FROM `permisos` p WHERE p.codigo LIKE 'suministros.%';

INSERT IGNORE INTO `roles_permisos` (`rol_id`, `permiso_id`)
SELECT 2, p.id FROM `permisos` p WHERE p.codigo LIKE 'suministros.%';

-- Opción de Menú Suministros
INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'suministros', 'Suministros', 'fa-solid fa-dolly', '/suministros', 2, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'abastecimiento' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'suministros.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);

-- Semillas iniciales de suministros
INSERT INTO `suministros` (`codigo`, `nombre`, `modalidad`, `unidad_medida`, `descripcion`, `permite_rollover`, `estado`) VALUES
('LUZ_ELECTRICA', 'Energía Eléctrica', 'MEDIDO', 'kWh', 'Consumo eléctrico individual por medidor físico', 0, 'ACTIVO'),
('AGUA_POTABLE', 'Agua Potable', 'MEDIDO', 'm3', 'Consumo de agua medido por flujómetro', 0, 'ACTIVO'),
('INTERNET_FIBRA', 'Internet Dedicado Fibra', 'FIJO_PERIODICO', 'MES', 'Servicio recurrente mensual de conectividad de alta velocidad', 0, 'ACTIVO'),
('MANTENIMIENTO_COMUN', 'Cuota de Mantenimiento Común', 'FIJO_PERIODICO', 'MES', 'Cuota periódica para áreas comunes y vigilancia', 0, 'ACTIVO')
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `modalidad` = VALUES(`modalidad`),
    `unidad_medida` = VALUES(`unidad_medida`),
    `descripcion` = VALUES(`descripcion`);

-- ----------------------------------------------------------------------------
-- 79. Recibos Administrativos de Cobro (Constancia Histórica en T0)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `recibos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'Formato REC-YYYYMM-XXXX',
    `cuenta_folio_id` BIGINT UNSIGNED NOT NULL,
    `pago_id` BIGINT UNSIGNED NOT NULL COMMENT 'Pago confirmado que originó el recibo',
    `persona_id` BIGINT UNSIGNED NOT NULL,
    `persona_nombre_snapshot` VARCHAR(255) NOT NULL,
    `persona_documento_tipo_snapshot` VARCHAR(20) NOT NULL,
    `persona_documento_numero_snapshot` VARCHAR(30) NOT NULL,
    `arrendamiento_id` BIGINT UNSIGNED NULL,
    `reserva_id` BIGINT UNSIGNED NULL,
    `documento_emitido_id` BIGINT UNSIGNED NULL COMMENT 'Enlace a DOCUMENTOS-1',
    `monto_recaudado` DECIMAL(15,2) NOT NULL,
    `monto_imputado` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `monto_no_aplicado_pago` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `saldo_pendiente_folio_despues` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `saldo_favor_folio_despues` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `metodo_pago_nombre` VARCHAR(50) NOT NULL,
    `referencia_cobro` VARCHAR(100) NULL,
    `concepto_general` VARCHAR(255) NOT NULL,
    `notas` VARCHAR(500) NULL,
    `fecha_emision` DATETIME NOT NULL,
    `estado` ENUM('EMITIDO', 'ANULADO') NOT NULL DEFAULT 'EMITIDO',
    `motivo_anulacion` VARCHAR(255) NULL,
    `anulado_en` DATETIME NULL,
    `anulado_por_actor_id` BIGINT UNSIGNED NULL,
    `creado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `recibo_activo_idx` BIGINT UNSIGNED GENERATED ALWAYS AS (
        IF(`estado` = 'EMITIDO', `pago_id`, NULL)
    ) VIRTUAL COMMENT 'Garantiza en InnoDB un único recibo activo por pago',
    UNIQUE KEY `uq_rec_codigo` (`codigo`),
    UNIQUE KEY `uq_rec_pago_activo` (`recibo_activo_idx`),
    KEY `idx_rec_cuenta_folio` (`cuenta_folio_id`, `estado`),
    KEY `idx_rec_pago` (`pago_id`),
    KEY `idx_rec_persona` (`persona_id`),
    KEY `idx_rec_arrendamiento` (`arrendamiento_id`),
    KEY `idx_rec_reserva` (`reserva_id`),
    KEY `idx_rec_documento` (`documento_emitido_id`),
    CONSTRAINT `fk_rec_cuenta_folio` FOREIGN KEY (`cuenta_folio_id`) REFERENCES `cuentas_folios` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_rec_pago` FOREIGN KEY (`pago_id`) REFERENCES `pagos_cuenta` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_rec_persona` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_rec_arrendamiento` FOREIGN KEY (`arrendamiento_id`) REFERENCES `arrendamientos` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_rec_reserva` FOREIGN KEY (`reserva_id`) REFERENCES `reservas` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_rec_documento` FOREIGN KEY (`documento_emitido_id`) REFERENCES `documentos_emitidos` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_rec_actor_creador` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_rec_actor_anulador` FOREIGN KEY (`anulado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
    CONSTRAINT `chk_rec_recaudado_positivo` CHECK (`monto_recaudado` > 0),
    CONSTRAINT `chk_rec_imputado_rango` CHECK (`monto_imputado` >= 0 AND `monto_imputado` <= `monto_recaudado`),
    CONSTRAINT `chk_rec_no_aplicado_no_negativo` CHECK (`monto_no_aplicado_pago` >= 0),
    CONSTRAINT `chk_rec_saldo_pendiente_folio` CHECK (`saldo_pendiente_folio_despues` >= 0),
    CONSTRAINT `chk_rec_saldo_favor_folio` CHECK (`saldo_favor_folio_despues` >= 0),
    CONSTRAINT `chk_rec_balance_algebraico` CHECK (`monto_recaudado` = `monto_imputado` + `monto_no_aplicado_pago`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Recibos de cobranza con snapshot financiero en T0';

-- ----------------------------------------------------------------------------
-- 80. Líneas de Imputación del Recibo (Snapshot de Amortizaciones en T0)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `recibo_lineas` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `recibo_id` BIGINT UNSIGNED NOT NULL,
    `numero_linea` INT UNSIGNED NOT NULL DEFAULT 1,
    `aplicacion_id` BIGINT UNSIGNED NULL,
    `cargo_id` BIGINT UNSIGNED NOT NULL,
    `cargo_codigo` VARCHAR(30) NOT NULL,
    `cargo_concepto` VARCHAR(255) NOT NULL,
    `cargo_origen_tipo` VARCHAR(40) NOT NULL,
    `cargo_monto_total` DECIMAL(15,2) NOT NULL,
    `monto_aplicado` DECIMAL(15,2) NOT NULL,
    `cargo_saldo_restante` DECIMAL(15,2) NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_reclin_recibo_num` (`recibo_id`, `numero_linea`),
    KEY `idx_reclin_cargo` (`cargo_id`),
    CONSTRAINT `fk_reclin_recibo` FOREIGN KEY (`recibo_id`) REFERENCES `recibos` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_reclin_aplicacion` FOREIGN KEY (`aplicacion_id`) REFERENCES `aplicaciones_pago` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_reclin_cargo` FOREIGN KEY (`cargo_id`) REFERENCES `cargos_cuenta` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `chk_reclin_aplicado_positivo` CHECK (`monto_aplicado` > 0),
    CONSTRAINT `chk_reclin_saldo_no_negativo` CHECK (`cargo_saldo_restante` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Líneas del snapshot de amortizaciones cubiertas en el recibo';

-- Permisos RBAC de Recibos
INSERT INTO `permisos` (`codigo`, `nombre`, `modulo`, `descripcion`, `creado_en`) VALUES
('recibos.ver', 'Ver recibos de cobro', 'RECIBOS', 'Permite consultar el listado y detalle de recibos emitidos', NOW()),
('recibos.emitir', 'Emitir recibos de cobro', 'RECIBOS', 'Permite generar recibos oficiales y PDFs con snapshot T0', NOW()),
('recibos.anular', 'Anular recibos de cobro', 'RECIBOS', 'Permite revocar formalmente la validez de un recibo emitido', NOW()),
('recibos.descargar', 'Descargar PDF de recibos', 'RECIBOS', 'Permite descargar el documento PDF soberano con verificación SHA-256', NOW())
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

INSERT IGNORE INTO `roles_permisos` (`rol_id`, `permiso_id`)
SELECT 1, p.id FROM `permisos` p WHERE p.codigo LIKE 'recibos.%';

INSERT IGNORE INTO `roles_permisos` (`rol_id`, `permiso_id`)
SELECT 2, p.id FROM `permisos` p WHERE p.codigo LIKE 'recibos.%';

-- Opción de Menú Recibos
INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'recibos', 'Recibos de Pago', 'fa-solid fa-receipt', '/recibos', 2, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'caja_finanzas' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'recibos.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);

-- Plantilla Oficial RECIBO_PAGO en DOCUMENTOS-1
INSERT INTO `documento_plantillas` (
    `codigo`, `nombre`, `descripcion`, `origen_tipo_permitido`,
    `orientacion`, `tamano_papel`, `requiere_membrete`, `archivo_membrete_fondo`,
    `margen_superior_mm`, `margen_inferior_mm`, `margen_izquierdo_mm`, `margen_derecho_mm`,
    `estado`, `creado_en`
) VALUES (
    'RECIBO_PAGO',
    'Recibo de Cobranza Oficial',
    'Constancia histórica administrativa de pago e imputación a folio en T0',
    'RECIBO',
    'PORTRAIT',
    'A4',
    0,
    NULL,
    20, 20, 20, 20,
    'ACTIVO',
    NOW()
) ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `descripcion` = VALUES(`descripcion`);

SET @plantilla_recibo_id = (SELECT `id` FROM `documento_plantillas` WHERE `codigo` = 'RECIBO_PAGO' LIMIT 1);

INSERT INTO `documento_plantilla_versiones` (
    `plantilla_id`, `numero_version`, `titulo_documento`, `cuerpo_html`, `estilos_css`,
    `notas_version`, `es_activa`, `creado_por_actor_id`, `creado_en`
) VALUES (
    @plantilla_recibo_id,
    1,
    'Constancia Oficial de Pago',
    '<div class="documento-recibo">
    <div class="encabezado-recibo">
        <h1 class="titulo-empresa">CAMARGO HOSTELERÍA S.A.C.</h1>
        <p class="subtitulo-empresa">R.U.C. 20600000001 — Gestión Inmobiliaria y Turística</p>
        <div class="caja-folio">
            <h2 class="titulo-recibo">RECIBO DE COBRANZA</h2>
            <p class="folio-numero">N° {{documento.folio}}</p>
            <p class="fecha-emision">Fecha y Hora: {{emision.fecha}} {{emision.hora}}</p>
        </div>
    </div>

    <div class="seccion-cliente">
        <table class="tabla-info">
            <tr>
                <td class="campo-etiqueta"><strong>Recibido de:</strong></td>
                <td class="campo-valor" colspan="3"><strong>{{cliente.nombre_completo}}</strong></td>
            </tr>
            <tr>
                <td class="campo-etiqueta"><strong>Documento:</strong></td>
                <td class="campo-valor">{{cliente.tipo_documento}} {{cliente.numero_documento}}</td>
                <td class="campo-etiqueta"><strong>Folio Comercial:</strong></td>
                <td class="campo-valor">{{folio.codigo}}</td>
            </tr>
            <tr>
                <td class="campo-etiqueta"><strong>Inmueble / Unidad:</strong></td>
                <td class="campo-valor">{{propiedad.nombre}} - {{unidad.nombre}}</td>
                <td class="campo-etiqueta"><strong>Contrato / Reserva:</strong></td>
                <td class="campo-valor">{{contrato.codigo}} {{reserva.codigo}}</td>
            </tr>
        </table>
    </div>

    <div class="seccion-cobro">
        <h3 class="seccion-subtitulo">Hecho Económico de Cobro</h3>
        <table class="tabla-info">
            <tr>
                <td class="campo-etiqueta"><strong>Código Pago:</strong></td>
                <td class="campo-valor">{{pago.codigo}}</td>
                <td class="campo-etiqueta"><strong>Método de Pago:</strong></td>
                <td class="campo-valor">{{pago.metodo}} ({{pago.medio_detalle}})</td>
            </tr>
            <tr>
                <td class="campo-etiqueta"><strong>N° Operación / Ref:</strong></td>
                <td class="campo-valor">{{pago.referencia_operacion}}</td>
                <td class="campo-etiqueta"><strong>Monto Recaudado:</strong></td>
                <td class="campo-valor font-bold text-primary">{{pago.moneda}} {{pago.monto_recaudado}}</td>
            </tr>
        </table>
    </div>

    <div class="seccion-amortizaciones">
        <h3 class="seccion-subtitulo">Detalle de Obligaciones Amortizadas (Imputación T0)</h3>
        {{tabla_amortizaciones}}
    </div>

    <div class="seccion-resumen">
        <table class="tabla-resumen">
            <tr>
                <td class="res-etiqueta">Total Recaudado en Pago:</td>
                <td class="res-valor font-bold">{{pago.moneda}} {{pago.monto_recaudado}}</td>
            </tr>
            <tr>
                <td class="res-etiqueta">Total Imputado a Cargos:</td>
                <td class="res-valor">{{pago.moneda}} {{totales.monto_imputado}}</td>
            </tr>
            <tr>
                <td class="res-etiqueta">Monto No Aplicado (Saldo a Favor en Folio):</td>
                <td class="res-valor text-success">{{pago.moneda}} {{totales.monto_no_aplicado_pago}}</td>
            </tr>
            <tr class="linea-separador">
                <td class="res-etiqueta font-bold">Saldo Pendiente Exigible del Folio:</td>
                <td class="res-valor font-bold">{{pago.moneda}} {{totales.saldo_pendiente_folio_despues}}</td>
            </tr>
            <tr>
                <td class="res-etiqueta">Saldo a Favor Acumulado del Folio:</td>
                <td class="res-valor">{{pago.moneda}} {{totales.saldo_favor_folio_despues}}</td>
            </tr>
        </table>
        <p class="monto-texto">SON: {{pago.monto_texto}}</p>
    </div>

    <div class="seccion-pie">
        <p class="aviso-legal">
            <strong>AVISO LEGAL:</strong> El presente documento constituye una constancia administrativa interna de recaudación y no representa un comprobante de pago electrónico con efectos tributarios ante la SUNAT.
        </p>
        <table class="tabla-firmas">
            <tr>
                <td class="firma-col">
                    <div class="linea-firma"></div>
                    <p class="firma-texto">Recibido Conforme<br>Caja / Administración Camargo Hostelería</p>
                </td>
            </tr>
        </table>
    </div>
</div>',
    'body { font-family: Helvetica, Arial, sans-serif; font-size: 9.5pt; color: #222; }
.documento-recibo { width: 100%; margin: 0 auto; }
.encabezado-recibo { border-bottom: 2px solid #0d6efd; padding-bottom: 12px; margin-bottom: 15px; }
.titulo-empresa { font-size: 16pt; margin: 0; color: #0d6efd; }
.subtitulo-empresa { font-size: 8.5pt; color: #666; margin: 3px 0 10px 0; }
.caja-folio { text-align: right; margin-top: -45px; }
.titulo-recibo { font-size: 13pt; margin: 0; color: #222; }
.folio-numero { font-size: 11pt; font-weight: bold; color: #0d6efd; margin: 2px 0; }
.fecha-emision { font-size: 8pt; color: #777; margin: 0; }
.tabla-info { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
.tabla-info td { padding: 4px 6px; font-size: 9pt; }
.campo-etiqueta { width: 20%; color: #444; }
.campo-valor { width: 30%; }
.seccion-subtitulo { font-size: 10.5pt; border-bottom: 1px solid #ddd; padding-bottom: 3px; margin: 12px 0 8px 0; color: #0d6efd; }
.tabla-amort-doc { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
.tabla-amort-doc th { background-color: #f1f4f9; border: 1px solid #ccc; padding: 6px; font-size: 8.5pt; text-align: center; }
.tabla-amort-doc td { border: 1px solid #ddd; padding: 5px 6px; font-size: 8.5pt; }
.seccion-resumen { width: 100%; margin-top: 10px; }
.tabla-resumen { float: right; width: 55%; border-collapse: collapse; margin-bottom: 8px; }
.tabla-resumen td { padding: 3px 8px; font-size: 9pt; }
.res-etiqueta { text-align: right; }
.res-valor { text-align: right; width: 35%; }
.linea-separador td { border-top: 1px dashed #aaa; padding-top: 5px; }
.monto-texto { clear: both; font-size: 8.5pt; font-style: italic; color: #333; margin-top: 5px; }
.aviso-legal { font-size: 7.5pt; color: #777; border-top: 1px solid #eee; padding-top: 8px; margin-top: 25px; text-align: justify; }
.tabla-firmas { width: 100%; margin-top: 25px; }
.firma-col { width: 45%; margin: 0 auto; text-align: center; }
.linea-firma { width: 220px; border-bottom: 1px solid #333; margin: 0 auto 5px auto; }
.firma-texto { font-size: 8pt; color: #444; margin: 0; }
.font-bold { font-weight: bold; }
.text-primary { color: #0d6efd; }
.text-success { color: #198754; }',
    'Versión inicial canónica del recibo de cobranza con snapshot T0',
    1,
    1,
    NOW()
) ON DUPLICATE KEY UPDATE
    `cuerpo_html` = VALUES(`cuerpo_html`),
    `estilos_css` = VALUES(`estilos_css`);

-- ============================================================================
-- CAMARGO PMS — FASE 25: HOUSEKEEPING, PISOS Y CONTROL DE LENCERÍA
-- Decisión Vinculante: D-083 (GATE HOUSEKEEPING-1)
-- Tablas: 81 a 89 del esquema canónico
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 81. Estado Físico de Higiene y Limpieza de Unidades (HOUSEKEEPING-1 / D-083)
-- Relación 1:1 con unidades físicas. No duplica ocupación ni comercial.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `housekeeping_unidades_limpieza` (
    `unidad_id` BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    `estado_limpieza` ENUM(
        'SUCIA',
        'EN_LIMPIEZA',
        'LIMPIA_POR_INSPECCIONAR',
        'LIMPIA_INSPECCIONADA',
        'RETOQUE_REQUERIDO'
    ) NOT NULL DEFAULT 'SUCIA',
    `tarea_activa_id` BIGINT UNSIGNED NULL,
    `ultima_limpieza_en` DATETIME NULL,
    `ultima_inspeccion_en` DATETIME NULL,
    `inspeccionado_por_actor_id` BIGINT UNSIGNED NULL,
    `observaciones` TEXT NULL,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_hku_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_hku_actor` FOREIGN KEY (`inspeccionado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_hku_estado` (`estado_limpieza`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Estado físico de higiene y sanitización de unidades (1:1 con unidades)';

-- ----------------------------------------------------------------------------
-- 82. Catálogo Maestro de Plantillas de Checklist de Inspección (HOUSEKEEPING-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `housekeeping_plantillas_checklist` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL UNIQUE,
    `nombre` VARCHAR(100) NOT NULL,
    `tipo_tarea` ENUM('TODAS', 'SALIDA', 'ESTADIA', 'PROFUNDA', 'RETOQUE') NOT NULL DEFAULT 'TODAS',
    `version` INT UNSIGNED NOT NULL DEFAULT 1,
    `es_activa` TINYINT(1) NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_hkpc_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_hkpc_nombre_no_vacio` CHECK (`nombre` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Estándares de inspección y checklists versionados';

-- ----------------------------------------------------------------------------
-- 83. Ítems Individuales de Plantilla de Checklist (HOUSEKEEPING-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `housekeeping_plantilla_items` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `plantilla_id` INT UNSIGNED NOT NULL,
    `categoria` VARCHAR(50) NOT NULL COMMENT 'BANO, DORMITORIO, SUPERFICIES, EQUIPAMIENTO, AMENITIES, GENERAL',
    `codigo_item` VARCHAR(30) NOT NULL,
    `descripcion` VARCHAR(200) NOT NULL,
    `es_critico` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 si el item es obligatorio para aprobar la habitación',
    `orden` INT UNSIGNED NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_hkpi_codigo_no_vacio` CHECK (`codigo_item` <> ''),
    CONSTRAINT `chk_hkpi_desc_no_vacia` CHECK (`descripcion` <> ''),
    CONSTRAINT `fk_hkpi_plantilla` FOREIGN KEY (`plantilla_id`) REFERENCES `housekeeping_plantillas_checklist` (`id`) ON DELETE CASCADE,
    INDEX `idx_hkpi_plantilla_orden` (`plantilla_id`, `orden`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Puntos de control individuales del estándar de inspección';

-- ----------------------------------------------------------------------------
-- 84. Órdenes Operativas de Trabajo de Housekeeping (HOUSEKEEPING-1 / D-083)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `housekeeping_tareas` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL UNIQUE COMMENT 'Formato HK-YYYYMMDD-XXXX',
    `propiedad_id` BIGINT UNSIGNED NOT NULL,
    `unidad_id` BIGINT UNSIGNED NOT NULL,
    `estadia_id` BIGINT UNSIGNED NULL COMMENT 'Estadía vinculada si la tarea fue disparada por check-out o stay-over',
    `tipo_tarea` ENUM('SALIDA', 'ESTADIA', 'PROFUNDA', 'RETOQUE') NOT NULL,
    `prioridad` ENUM('BAJA', 'MEDIA', 'ALTA', 'URGENTE') NOT NULL DEFAULT 'MEDIA',
    `estado` ENUM(
        'PENDIENTE',
        'ASIGNADA',
        'EN_PROCESO',
        'POR_INSPECCIONAR',
        'RECHAZADA',
        'COMPLETADA',
        'CANCELADA'
    ) NOT NULL DEFAULT 'PENDIENTE',
    `camarera_colaborador_id` BIGINT UNSIGNED NULL COMMENT 'Colaborador asignado para limpiar',
    `supervisor_colaborador_id` BIGINT UNSIGNED NULL COMMENT 'Colaborador que inspecciona',
    `fecha_programada` DATE NOT NULL,
    `iniciado_en` DATETIME NULL,
    `terminado_en` DATETIME NULL COMMENT 'Momento en que camarera envía a inspección',
    `inspeccionado_en` DATETIME NULL COMMENT 'Momento en que supervisor completa inspección',
    `condicion_operacional` ENUM('NINGUNA', 'DND', 'SIN_ACCESO', 'RECHAZO_HUESPED') NOT NULL DEFAULT 'NINGUNA',
    `notas_operario` TEXT NULL,
    `notas_supervisor` TEXT NULL,
    `motivo_cancelacion` TEXT NULL,
    `creado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `salida_estadia_activa_idx` BIGINT UNSIGNED GENERATED ALWAYS AS (
        IF(`tipo_tarea` = 'SALIDA' AND `estado` NOT IN ('CANCELADA'), `estadia_id`, NULL)
    ) VIRTUAL COMMENT 'Garantiza idempotencia estricta: 1 checkout -> máximo 1 tarea de salida activa',
    CONSTRAINT `chk_hkt_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `fk_hkt_propiedad` FOREIGN KEY (`propiedad_id`) REFERENCES `propiedades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_hkt_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_hkt_estadia` FOREIGN KEY (`estadia_id`) REFERENCES `estadias` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_hkt_camarera` FOREIGN KEY (`camarera_colaborador_id`) REFERENCES `colaboradores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_hkt_supervisor` FOREIGN KEY (`supervisor_colaborador_id`) REFERENCES `colaboradores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_hkt_actor` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_hkt_salida_estadia` (`salida_estadia_activa_idx`),
    INDEX `idx_hkt_unidad_estado` (`unidad_id`, `estado`),
    INDEX `idx_hkt_fecha_prioridad` (`fecha_programada`, `prioridad`),
    INDEX `idx_hkt_camarera` (`camarera_colaborador_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Tareas operativas de intervención de limpieza e inspección';

-- ----------------------------------------------------------------------------
-- 85. Snapshot Inmutable de Checklist por Tarea (HOUSEKEEPING-1 / D-083)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `housekeeping_tarea_checklist` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tarea_id` BIGINT UNSIGNED NOT NULL,
    `codigo_item_snapshot` VARCHAR(30) NOT NULL,
    `categoria_snapshot` VARCHAR(50) NOT NULL,
    `descripcion_snapshot` VARCHAR(200) NOT NULL,
    `es_critico_snapshot` TINYINT(1) NOT NULL DEFAULT 0,
    `resultado` ENUM('CONFORME', 'NO_CONFORME', 'NO_APLICA') NOT NULL DEFAULT 'CONFORME',
    `observacion` VARCHAR(255) NULL,
    `verificado_en` DATETIME NULL,
    `verificado_por_actor_id` BIGINT UNSIGNED NULL,
    CONSTRAINT `fk_hktchk_tarea` FOREIGN KEY (`tarea_id`) REFERENCES `housekeeping_tareas` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_hktchk_actor` FOREIGN KEY (`verificado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_hktchk_tarea` (`tarea_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Evaluación congelada e inmutable de puntos de control por tarea';

-- ----------------------------------------------------------------------------
-- 86. Consumos de Amenities Repuestos Vinculados a Kardex (HOUSEKEEPING-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `housekeeping_tarea_consumos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tarea_id` BIGINT UNSIGNED NOT NULL,
    `articulo_id` BIGINT UNSIGNED NOT NULL,
    `almacen_origen_id` BIGINT UNSIGNED NOT NULL,
    `cantidad` DECIMAL(15,4) NOT NULL,
    `movimiento_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK a inventario_movimientos con tipo SALIDA_CONSUMO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_hktcon_cantidad` CHECK (`cantidad` > 0),
    CONSTRAINT `fk_hktcon_tarea` FOREIGN KEY (`tarea_id`) REFERENCES `housekeeping_tareas` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_hktcon_articulo` FOREIGN KEY (`articulo_id`) REFERENCES `inventario_articulos` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_hktcon_almacen` FOREIGN KEY (`almacen_origen_id`) REFERENCES `inventario_ubicaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_hktcon_movimiento` FOREIGN KEY (`movimiento_id`) REFERENCES `inventario_movimientos` (`id`) ON DELETE RESTRICT,
    INDEX `idx_hktcon_tarea` (`tarea_id`),
    INDEX `idx_hktcon_articulo` (`articulo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Consumos reales de amenities en limpieza vinculados atómicamente a Kardex';

-- ----------------------------------------------------------------------------
-- 87. Historial Inmutable Append-Only de Estados de Tarea (HOUSEKEEPING-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `housekeeping_tarea_historial` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tarea_id` BIGINT UNSIGNED NOT NULL,
    `estado_anterior` VARCHAR(50) NULL,
    `estado_nuevo` VARCHAR(50) NOT NULL,
    `actor_id` BIGINT UNSIGNED NOT NULL,
    `motivo` TEXT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_hkth_tarea` FOREIGN KEY (`tarea_id`) REFERENCES `housekeeping_tareas` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_hkth_actor` FOREIGN KEY (`actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT,
    INDEX `idx_hkth_tarea` (`tarea_id`, `creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Trazabilidad append-only de transiciones de tarea y auditoría D-061';

-- ----------------------------------------------------------------------------
-- 88. Lotes de Despacho y Retorno de Lavandería Textil (HOUSEKEEPING-1 / D-083)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `housekeeping_lotes_lavanderia` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL UNIQUE COMMENT 'Formato LAV-YYYYMMDD-XXXX',
    `propiedad_id` BIGINT UNSIGNED NOT NULL,
    `almacen_origen_id` BIGINT UNSIGNED NOT NULL COMMENT 'Almacén/office local de lencería sucia',
    `ubicacion_lavanderia_id` BIGINT UNSIGNED NOT NULL COMMENT 'Ubicación CUSTODIA_EXTERNA de lavandería',
    `fecha_despacho` DATE NOT NULL,
    `fecha_retorno_estimada` DATE NULL,
    `fecha_retorno_real` DATE NULL,
    `estado` ENUM('DESPACHADO', 'RETORNADO_TOTAL', 'RETORNADO_PARCIAL', 'CON_DISCREPANCIA', 'ANULADO') NOT NULL DEFAULT 'DESPACHADO',
    `notas_despacho` TEXT NULL,
    `notas_retorno` TEXT NULL,
    `creado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_hkl_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `fk_hkl_propiedad` FOREIGN KEY (`propiedad_id`) REFERENCES `propiedades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_hkl_almacen` FOREIGN KEY (`almacen_origen_id`) REFERENCES `inventario_ubicaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_hkl_lavanderia` FOREIGN KEY (`ubicacion_lavanderia_id`) REFERENCES `inventario_ubicaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_hkl_actor` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT,
    INDEX `idx_hkl_estado` (`estado`),
    INDEX `idx_hkl_fecha` (`fecha_despacho`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Lotes de despacho y retorno de lencería textil con lavandería';

-- ----------------------------------------------------------------------------
-- 89. Líneas de Lote de Lavandería con Diferencias Explícitas (HOUSEKEEPING-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `housekeeping_lote_lineas` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `lote_id` BIGINT UNSIGNED NOT NULL,
    `articulo_id` BIGINT UNSIGNED NOT NULL COMMENT 'Prenda textil (LENCERIA_BLANCOS)',
    `cantidad_enviada` DECIMAL(15,4) NOT NULL,
    `cantidad_recibida` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `cantidad_baja_merma` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `cantidad_diferencia` DECIMAL(15,4) GENERATED ALWAYS AS (
        `cantidad_enviada` - (`cantidad_recibida` + `cantidad_baja_merma`)
    ) VIRTUAL COMMENT 'Discrepancia física explícita preservada',
    `observaciones` VARCHAR(255) NULL,
    CONSTRAINT `chk_hkl_cant_enviada_positiva` CHECK (`cantidad_enviada` > 0),
    CONSTRAINT `chk_hkl_cant_recibida_no_neg` CHECK (`cantidad_recibida` >= 0),
    CONSTRAINT `chk_hkl_cant_baja_no_neg` CHECK (`cantidad_baja_merma` >= 0),
    CONSTRAINT `fk_hkll_lote` FOREIGN KEY (`lote_id`) REFERENCES `housekeeping_lotes_lavanderia` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_hkll_articulo` FOREIGN KEY (`articulo_id`) REFERENCES `inventario_articulos` (`id`) ON DELETE RESTRICT,
    INDEX `idx_hkll_lote` (`lote_id`),
    INDEX `idx_hkll_articulo` (`articulo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Detalle de prendas textiles por lote de lavandería con cálculo de diferencias';

-- ----------------------------------------------------------------------------
-- Semillas: Plantilla Estándar Canónica de Inspección
-- ----------------------------------------------------------------------------
INSERT INTO `housekeeping_plantillas_checklist` (`codigo`, `nombre`, `tipo_tarea`, `version`, `es_activa`)
VALUES ('CHK_ESTANDAR_HOTEL', 'Checklist Estándar de Inspección Hotelera', 'TODAS', 1, 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

SET @plantilla_hk_id = (SELECT `id` FROM `housekeeping_plantillas_checklist` WHERE `codigo` = 'CHK_ESTANDAR_HOTEL' LIMIT 1);

INSERT INTO `housekeeping_plantilla_items` (`plantilla_id`, `categoria`, `codigo_item`, `descripcion`, `es_critico`, `orden`)
VALUES
(@plantilla_hk_id, 'BANO', 'BANO_INODORO', 'Inodoro higienizado, descalcificado y con precinto', 1, 1),
(@plantilla_hk_id, 'BANO', 'BANO_DUCHA', 'Ducha/Tina libre de sarro, cabellos y mampara seca', 1, 2),
(@plantilla_hk_id, 'BANO', 'BANO_TOALLAS', 'Juego de toallas completo según estándar (cuerpo, mano, piso)', 1, 3),
(@plantilla_hk_id, 'BANO', 'BANO_AMENITIES', 'Amenities de baño completos y repuestos', 0, 4),
(@plantilla_hk_id, 'DORMITORIO', 'CAMA_SABANAS', 'Sábanas y fundas limpias, tensadas sin arrugas ni manchas', 1, 5),
(@plantilla_hk_id, 'DORMITORIO', 'CAMA_ALMOHADAS', 'Almohadas alineadas y cubrecama estirado', 0, 6),
(@plantilla_hk_id, 'SUPERFICIES', 'SUPERF_POLVO', 'Muebles, rodapiés y molduras libres de polvo', 0, 7),
(@plantilla_hk_id, 'SUPERFICIES', 'PISO_LIMPIO', 'Piso aspirado y trapeado sin olores residuales', 1, 8),
(@plantilla_hk_id, 'EQUIPAMIENTO', 'EQUIP_CLIMA_LUCES', 'Aire acondicionado/calefacción y luces operativas', 0, 9),
(@plantilla_hk_id, 'EQUIPAMIENTO', 'EQUIP_CONTROL_TV', 'Control remoto de TV sanitizado y con funda', 0, 10)
ON DUPLICATE KEY UPDATE `descripcion` = VALUES(`descripcion`), `es_critico` = VALUES(`es_critico`);

-- ----------------------------------------------------------------------------
-- Inicialización de Estado de Limpieza para Unidades Existentes
-- (Se inicializan en LIMPIA_INSPECCIONADA para preservar el funcionamiento existente)
-- ----------------------------------------------------------------------------
INSERT INTO `housekeeping_unidades_limpieza` (`unidad_id`, `estado_limpieza`, `ultima_inspeccion_en`)
SELECT u.`id`, 'LIMPIA_INSPECCIONADA', NOW()
FROM `unidades` u
ON DUPLICATE KEY UPDATE `actualizado_en` = NOW();

-- ----------------------------------------------------------------------------
-- Permisos RBAC para Housekeeping
-- ----------------------------------------------------------------------------
INSERT INTO `permisos` (`codigo`, `nombre`, `modulo`, `descripcion`, `creado_en`) VALUES
('housekeeping.ver', 'Ver módulo de Housekeeping', 'HOUSEKEEPING', 'Acceso al rack de pisos, tablero operativo y listados de tareas', NOW()),
('housekeeping.tareas.gestionar', 'Gestionar tareas de limpieza', 'HOUSEKEEPING', 'Crear, asignar, cancelar y reprogramar tareas de limpieza', NOW()),
('housekeeping.limpieza.ejecutar', 'Ejecutar labores de limpieza', 'HOUSEKEEPING', 'Iniciar limpieza, registrar amenities y enviar a inspección', NOW()),
('housekeeping.inspeccion.ejecutar', 'Inspeccionar y calificar habitaciones', 'HOUSEKEEPING', 'Aprobar o rechazar checklists de inspección de habitaciones', NOW()),
('housekeeping.lavanderia.gestionar', 'Gestionar lencería y lavandería', 'HOUSEKEEPING', 'Despachar y recibir lotes de lencería con lavandería interna/externa', NOW())
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

-- Asignación de permisos a SUPERADMINISTRADOR (rol 1) y ADMINISTRADOR (rol 2)
INSERT IGNORE INTO `roles_permisos` (`rol_id`, `permiso_id`)
SELECT 1, p.id FROM `permisos` p WHERE p.codigo LIKE 'housekeeping.%';

INSERT IGNORE INTO `roles_permisos` (`rol_id`, `permiso_id`)
SELECT 2, p.id FROM `permisos` p WHERE p.codigo LIKE 'housekeeping.%';

-- ----------------------------------------------------------------------------
-- Opción de Menú Alina (Bajo Menú Operaciones)
-- ----------------------------------------------------------------------------
INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'housekeeping_tablero', 'Housekeeping / Pisos', 'fa-solid fa-broom', '/housekeeping', 3, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'operaciones' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'housekeeping.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);



-- ============================================================================
-- CAMARGO PMS — Migración 026: Módulo de Gastos Operativos, Egresos Administrativos y Tesorería
-- Gobernanza: D-086 (GATE GASTOS-1)
-- Axioma: GASTO ≠ COMPRA ≠ CxP ≠ PAGO ≠ MOVIMIENTO DE TESORERÍA
-- Regla de Oro: GASTOS-1 NO CREA UNA SEGUNDA TESORERÍA NI UNA SEGUNDA CxP
-- Tablas:
--   1. gasto_categorias           (Catálogo maestro de clasificación de gastos)
--   2. gastos                     (Hecho económico soberano y centro de costo)
--   3. gasto_evidencias           (Soporte documental N evidencias por gasto)
--   4. pagos_egreso               (Extensión soberana de FINANCIERO-2 para egresos)
--   5. gasto_aplicaciones_pago    (Imputación formal M:N entre pago y gasto)
--   6. gasto_historial_estados    (Auditoría append-only D-061)
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 1. Catálogo Maestro de Categorías de Gasto
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `gasto_categorias` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Identificador único, ej. SERV_ELECTRICIDAD',
    `nombre` VARCHAR(150) NOT NULL,
    `descripcion` TEXT NULL,
    `requiere_comprobante_fiscal` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 si exige comprobante formal SUNAT, 0 si admite recibo interno/DJ',
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_gcat_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_gcat_nombre_no_vacio` CHECK (`nombre` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Catálogo maestro de categorías operativas de gasto';

-- ----------------------------------------------------------------------------
-- 2. Hecho Económico Soberano: Gastos
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `gastos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL UNIQUE COMMENT 'Formato GST-YYYYMM-XXXX',
    `categoria_id` BIGINT UNSIGNED NOT NULL,
    `ambito` ENUM('CORPORATIVO', 'PROPIEDAD', 'UNIDAD') NOT NULL DEFAULT 'PROPIEDAD',
    `propiedad_id` BIGINT UNSIGNED NULL,
    `unidad_id` BIGINT UNSIGNED NULL,
    `proveedor_id` BIGINT UNSIGNED NULL COMMENT 'FK opcional a tabla maestros proveedores',
    `acreedor_nombre` VARCHAR(200) NOT NULL COMMENT 'Nombre o razón social del acreedor/beneficiario',
    `acreedor_documento` VARCHAR(30) NULL COMMENT 'RUC, DNI o documento del acreedor',
    `descripcion_concepto` VARCHAR(255) NOT NULL,
    `tipo_comprobante` ENUM('FACTURA', 'BOLETA', 'RECIBO_HONORARIOS', 'RECIBO_SERVICIO_PUBLICO', 'DECLARACION_JURADA_CAJA_CHICA', 'TICKET_MAQUINA', 'OTRO_NO_TRIBUTARIO') NOT NULL,
    `comprobante_serie` VARCHAR(10) NULL,
    `comprobante_numero` VARCHAR(20) NULL,
    `fecha_emision` DATE NOT NULL,
    `fecha_vencimiento` DATE NOT NULL,
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN' COMMENT'Moneda funcional oficial estricta',
    `subtotal` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `impuestos` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total` DECIMAL(15,2) NOT NULL,
    `estado` ENUM('BORRADOR', 'REGISTRADO', 'APROBADO', 'ANULADO') NOT NULL DEFAULT 'REGISTRADO',
    `motivo_anulacion` VARCHAR(255) NULL,
    `anulado_en` DATETIME NULL,
    `anulado_por_actor_id` BIGINT UNSIGNED NULL,
    `creado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_gst_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_gst_concepto_no_vacio` CHECK (`descripcion_concepto` <> ''),
    CONSTRAINT `chk_gst_acreedor_no_vacio` CHECK (`acreedor_nombre` <> ''),
    CONSTRAINT `chk_gst_total_positivo` CHECK (`total` >= 0),
    CONSTRAINT `chk_gst_coherencia_ambito` CHECK (
        (`ambito` = 'CORPORATIVO' AND `propiedad_id` IS NULL AND `unidad_id` IS NULL) OR
        (`ambito` = 'PROPIEDAD' AND `propiedad_id` IS NOT NULL AND `unidad_id` IS NULL) OR
        (`ambito` = 'UNIDAD' AND `propiedad_id` IS NOT NULL AND `unidad_id` IS NOT NULL)
    ),
    CONSTRAINT `fk_gst_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `gasto_categorias` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_gst_propiedad` FOREIGN KEY (`propiedad_id`) REFERENCES `propiedades` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_gst_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_gst_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_gst_creador` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_gst_anulador` FOREIGN KEY (`anulado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT,
    INDEX `idx_gst_ambito` (`ambito`, `propiedad_id`, `unidad_id`),
    INDEX `idx_gst_estado` (`estado`),
    INDEX `idx_gst_fechas` (`fecha_emision`, `fecha_vencimiento`),
    INDEX `idx_gst_acreedor` (`acreedor_documento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Hecho económico soberano de gasto y centro de costo';

-- ----------------------------------------------------------------------------
-- 3. Evidencias Múltiples de Sustento Documental (N archivos por gasto)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `gasto_evidencias` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gasto_id` BIGINT UNSIGNED NOT NULL,
    `tipo_evidencia` ENUM('COMPROBANTE_FISCAL', 'VOUCHER_PAGO', 'FOTO_BIEN_REPARADO', 'INFORME_TECNICO', 'OTRO') NOT NULL,
    `nombre_original` VARCHAR(255) NOT NULL,
    `ruta_archivo` VARCHAR(255) NOT NULL,
    `mime_type` VARCHAR(100) NOT NULL,
    `tamano_bytes` INT UNSIGNED NOT NULL,
    `hash_sha256` CHAR(64) NOT NULL,
    `subido_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_gevid_nombre_no_vacio` CHECK (`nombre_original` <> ''),
    CONSTRAINT `chk_gevid_ruta_no_vacia` CHECK (`ruta_archivo` <> ''),
    CONSTRAINT `chk_gevid_tamano_positivo` CHECK (`tamano_bytes` > 0),
    CONSTRAINT `fk_gevid_gasto` FOREIGN KEY (`gasto_id`) REFERENCES `gastos` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_gevid_actor` FOREIGN KEY (`subido_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT,
    INDEX `idx_gevid_gasto` (`gasto_id`),
    INDEX `idx_gevid_hash` (`hash_sha256`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Evidencias y comprobantes digitalizados vinculados al gasto';

-- ----------------------------------------------------------------------------
-- 4. Extensión Soberana de Tesorería: Pagos de Egreso (FINANCIERO-2)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pagos_egreso` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL UNIQUE COMMENT 'Formato PAG-EGR-YYYYMM-XXXX',
    `metodo_pago_id` INT UNSIGNED NOT NULL,
    `monto_total` DECIMAL(15,2) NOT NULL,
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `fecha_pago` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `sesion_caja_id` BIGINT UNSIGNED NULL COMMENT 'Obligatorio si método es EFECTIVO',
    `movimiento_caja_id` BIGINT UNSIGNED NULL COMMENT 'Enlace directo al libro mayor de caja',
    `cuenta_bancaria_id` INT UNSIGNED NULL COMMENT 'Obligatorio si método es TRANSFERENCIA/BANCO',
    `movimiento_bancario_id` BIGINT UNSIGNED NULL COMMENT 'Enlace directo al libro mayor bancario',
    `referencia_operacion` VARCHAR(100) NULL,
    `estado` ENUM('CONFIRMADO', 'REVERSADO') NOT NULL DEFAULT 'CONFIRMADO',
    `motivo_reverso` VARCHAR(255) NULL,
    `reversado_en` DATETIME NULL,
    `reversado_por_actor_id` BIGINT UNSIGNED NULL,
    `registrado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_pegr_monto_positivo` CHECK (`monto_total` > 0),
    CONSTRAINT `chk_pegr_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `fk_pegr_metodo` FOREIGN KEY (`metodo_pago_id`) REFERENCES `metodos_pago` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_pegr_sesion` FOREIGN KEY (`sesion_caja_id`) REFERENCES `sesiones_caja` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_pegr_mov_caja` FOREIGN KEY (`movimiento_caja_id`) REFERENCES `movimientos_caja` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_pegr_cuenta_banc` FOREIGN KEY (`cuenta_bancaria_id`) REFERENCES `cuentas_bancarias` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_pegr_mov_banc` FOREIGN KEY (`movimiento_bancario_id`) REFERENCES `movimientos_bancarios` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_pegr_registrador` FOREIGN KEY (`registrado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_pegr_reversor` FOREIGN KEY (`reversado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT,
    INDEX `idx_pegr_estado` (`estado`),
    INDEX `idx_pegr_sesion` (`sesion_caja_id`),
    INDEX `idx_pegr_banco` (`cuenta_bancaria_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Pagos soberanos de egreso en tesorería';

-- ----------------------------------------------------------------------------
-- 5. Imputación / Aplicación de Pagos a Gastos (M:N)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `gasto_aplicaciones_pago` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL UNIQUE COMMENT 'Formato APL-GST-YYYYMM-XXXX',
    `gasto_id` BIGINT UNSIGNED NOT NULL,
    `pago_egreso_id` BIGINT UNSIGNED NOT NULL,
    `monto_aplicado` DECIMAL(15,2) NOT NULL,
    `estado` ENUM('ACTIVO', 'REVERSADO') NOT NULL DEFAULT 'ACTIVO',
    `motivo_reverso` VARCHAR(255) NULL,
    `reversado_en` DATETIME NULL,
    `reversado_por_actor_id` BIGINT UNSIGNED NULL,
    `creado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_gapp_monto_positivo` CHECK (`monto_aplicado` > 0),
    CONSTRAINT `chk_gapp_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `fk_gapp_gasto` FOREIGN KEY (`gasto_id`) REFERENCES `gastos` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_gapp_pago` FOREIGN KEY (`pago_egreso_id`) REFERENCES `pagos_egreso` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_gapp_creador` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_gapp_reversor` FOREIGN KEY (`reversado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT,
    INDEX `idx_gapp_gasto_estado` (`gasto_id`, `estado`),
    INDEX `idx_gapp_pago_estado` (`pago_egreso_id`, `estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Imputación formal entre pagos de egreso y gastos';

-- ----------------------------------------------------------------------------
-- 6. Auditoría Append-Only D-061
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `gasto_historial_estados` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gasto_id` BIGINT UNSIGNED NOT NULL,
    `estado_anterior` VARCHAR(30) NULL,
    `estado_nuevo` VARCHAR(30) NOT NULL,
    `motivo` TEXT NULL,
    `actor_id` BIGINT UNSIGNED NOT NULL,
    `correlation_id` VARCHAR(100) NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_ghist_gasto` FOREIGN KEY (`gasto_id`) REFERENCES `gastos` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ghist_actor` FOREIGN KEY (`actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT,
    INDEX `idx_ghist_gasto` (`gasto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Historial append-only de cambios de estado en gastos';

-- ----------------------------------------------------------------------------
-- Semillas: Categorías Operativas de Gasto Iniciales
-- ----------------------------------------------------------------------------
INSERT INTO `gasto_categorias` (`codigo`, `nombre`, `descripcion`, `requiere_comprobante_fiscal`, `activo`) VALUES
('SERV_ELECTRICIDAD', 'Servicios de Energía Eléctrica', 'Facturación mensual de electricidad para predios y oficinas', 1, 1),
('SERV_AGUA', 'Servicios de Agua Potable y Alcantarillado', 'Facturación mensual de agua potable y saneamiento', 1, 1),
('SERV_TELECOM', 'Telecomunicaciones e Internet', 'Conexiones de fibra óptica, telefonía fija/móvil y televisión', 1, 1),
('MANT_MENOR', 'Mantenimiento y Reparaciones Urgentes', 'Reparaciones menores in-situ de gasfitería, cerrajería, vidriería y electricidad', 0, 1),
('HONORARIOS_PROF', 'Honorarios y Asesorías Profesionales', 'Servicios contables, legales, tributarios e informáticos externos con RxH', 1, 1),
('TRIBUTOS_TASAS', 'Tributos y Arbitrios Municipales', 'Arbitrios municipales de predios, licencias y tasas gubernamentales', 1, 1),
('TRANSPORTE_VIATICOS', 'Movilidad y Viáticos Operativos', 'Pasajes locales, combustible y viáticos para personal operativo', 0, 1),
('COMISIONES_BANCARIAS', 'Comisiones y Portes Bancarios', 'Mantenimiento de cuentas bancarias y portes no deducibles en POS', 1, 1),
('SUMINISTROS_DIRECTOS', 'Suministros de Consumo Directo', 'Café, azúcar, artículos menores de oficina y limpieza para uso inmediato', 0, 1),
('OTROS_OPERATIVOS', 'Otros Gastos Operativos Diversos', 'Erogaciones operativas no clasificadas en otras categorías', 0, 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

-- ----------------------------------------------------------------------------
-- Permisos RBAC para Gastos y Egresos
-- ----------------------------------------------------------------------------
INSERT INTO `permisos` (`codigo`, `nombre`, `modulo`, `descripcion`, `creado_en`) VALUES
('gastos.ver', 'Ver módulo de gastos y egresos', 'GASTOS', 'Acceso al listado de gastos, detalles y reportes de erogaciones', NOW()),
('gastos.crear', 'Registrar nuevos gastos', 'GASTOS', 'Permite crear gastos en estado borrador o registrado con sus evidencias', NOW()),
('gastos.aprobar', 'Aprobar gastos', 'GASTOS', 'Autorizar erogaciones para habilitar su desembolso y pago', NOW()),
('gastos.anular', 'Anular gastos', 'GASTOS', 'Permite anular gastos que no cuenten con aplicaciones de pago activas', NOW()),
('gastos.pagar', 'Pagar y aplicar egresos', 'GASTOS', 'Emitir pagos de egreso en efectivo (caja chica) o banco y aplicarlos a gastos', NOW())
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

-- Asignación a SUPERADMINISTRADOR (1) y ADMINISTRADOR (2)
INSERT IGNORE INTO `roles_permisos` (`rol_id`, `permiso_id`)
SELECT 1, p.id FROM `permisos` p WHERE p.codigo LIKE 'gastos.%';

INSERT IGNORE INTO `roles_permisos` (`rol_id`, `permiso_id`)
SELECT 2, p.id FROM `permisos` p WHERE p.codigo LIKE 'gastos.%';

-- ----------------------------------------------------------------------------
-- Opción de Menú Alina (Bajo Caja y Finanzas)
-- ----------------------------------------------------------------------------
INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'gastos_modulo', 'Gastos y Egresos', 'fa-solid fa-file-invoice-dollar', '/gastos', 3, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'caja_finanzas' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'gastos.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);

-- ----------------------------------------------------------------------------
-- Asignación de Permisos de Sesiones (SESIONES-1)
-- ----------------------------------------------------------------------------
INSERT IGNORE INTO `roles_permisos` (`rol_id`, `permiso_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
CROSS JOIN `permisos` p
WHERE r.`codigo` = 'SUPERADMINISTRADOR'
  AND p.`codigo` LIKE 'sesiones.%';

-- ----------------------------------------------------------------------------
-- Opción de Menú Alina para Sesiones (Bajo Configuración id 2)
-- ----------------------------------------------------------------------------
INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'seguridad_sesiones', 'Sesiones Activas', 'fa-solid fa-laptop-code', '/seguridad/sesiones', 5, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'configuracion' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'sesiones.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);

-- ----------------------------------------------------------------------------
-- MÓDULO: BITÁCORA OPERATIVA Y LIBRO DE GUARDIA (D-089 / BITÁCORA-1)
-- ----------------------------------------------------------------------------
-- 1. Bitácora de Entradas Operacionales
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bitacora_entradas` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `propiedad_id` BIGINT UNSIGNED NOT NULL COMMENT 'Propiedad o sede física del turno',
    `usuario_creador_id` BIGINT UNSIGNED NOT NULL COMMENT 'Colaborador humano autor del registro',
    `tipo` ENUM('NOVEDAD', 'CONSIGNA', 'INCIDENCIA', 'RELEVO', 'AVISO_GENERAL') NOT NULL DEFAULT 'NOVEDAD',
    `prioridad` ENUM('BAJA', 'MEDIA', 'ALTA', 'URGENTE') NOT NULL DEFAULT 'MEDIA',
    `titulo` VARCHAR(200) NOT NULL COMMENT 'Resumen o asunto breve del hecho operativo',
    `contenido` TEXT NOT NULL COMMENT 'Relato inmutable del hecho u orden de guardia',
    `turno` ENUM('MANANA', 'TARDE', 'NOCHE', 'GENERAL') NOT NULL DEFAULT 'GENERAL',
    `fecha_operativa` DATE NOT NULL COMMENT 'Fecha hotelera/operativa del suceso',
    `estado` ENUM('REGISTRADA', 'PENDIENTE', 'EN_PROCESO', 'RESUELTA', 'ANULADA') NOT NULL DEFAULT 'REGISTRADA',

    -- Referencias operacionales opcionales
    `unidad_id` BIGINT UNSIGNED NULL COMMENT 'FK opcional si ocurrió en una habitación/departamento',
    `reserva_id` BIGINT UNSIGNED NULL COMMENT 'Referencia opcional a reserva vinculada',
    `estadia_id` BIGINT UNSIGNED NULL COMMENT 'Referencia opcional a estadía/huésped vinculado',
    `mantenimiento_id` BIGINT UNSIGNED NULL COMMENT 'Referencia opcional a orden de mantenimiento',
    `sesion_caja_id` BIGINT UNSIGNED NULL COMMENT 'Referencia opcional al turno de caja coincidente',

    -- Trazabilidad de resolución de consignas e incidencias
    `resuelta_por_usuario_id` BIGINT UNSIGNED NULL,
    `resuelta_en` DATETIME NULL,
    `nota_resolucion` TEXT NULL,

    -- Trazabilidad de anulación supervisada (ANULAR != DELETE)
    `anulada_por_usuario_id` BIGINT UNSIGNED NULL,
    `anulada_en` DATETIME NULL,
    `motivo_anulacion` VARCHAR(255) NULL,

    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT `chk_bitacora_titulo_no_vacio` CHECK (`titulo` <> ''),
    CONSTRAINT `chk_bitacora_contenido_no_vacio` CHECK (`contenido` <> ''),
    CONSTRAINT `fk_bitacora_propiedad` FOREIGN KEY (`propiedad_id`) REFERENCES `propiedades` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_bitacora_usuario_creador` FOREIGN KEY (`usuario_creador_id`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_bitacora_usuario_resolutor` FOREIGN KEY (`resuelta_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_bitacora_usuario_anulador` FOREIGN KEY (`anulada_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_bitacora_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,

    INDEX `idx_bitacora_propiedad_fecha` (`propiedad_id`, `fecha_operativa`),
    INDEX `idx_bitacora_estado_prioridad` (`estado`, `prioridad`),
    INDEX `idx_bitacora_tipo` (`tipo`),
    INDEX `idx_bitacora_unidad` (`unidad_id`),
    INDEX `idx_bitacora_creador` (`usuario_creador_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Libro de guardia y bitácora de novedades operacionales';

-- ----------------------------------------------------------------------------
-- 2. Historial de Seguimientos y Enmiendas (Append-Only)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bitacora_seguimientos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `entrada_id` BIGINT UNSIGNED NOT NULL COMMENT 'Entrada de bitácora vinculada',
    `usuario_id` BIGINT UNSIGNED NOT NULL COMMENT 'Colaborador autor del seguimiento o enmienda',
    `tipo_evento` ENUM('COMENTARIO', 'ENMIENDA', 'CAMBIO_ESTADO', 'RESOLUCION', 'REAPERTURA', 'ANULACION') NOT NULL DEFAULT 'COMENTARIO',
    `contenido` TEXT NOT NULL COMMENT 'Texto del comentario, aclaración o nota de seguimiento',
    `estado_anterior` ENUM('REGISTRADA', 'PENDIENTE', 'EN_PROCESO', 'RESUELTA', 'ANULADA') NULL,
    `estado_nuevo` ENUM('REGISTRADA', 'PENDIENTE', 'EN_PROCESO', 'RESUELTA', 'ANULADA') NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT `chk_bitacora_seg_contenido_no_vacio` CHECK (`contenido` <> ''),
    CONSTRAINT `fk_bitacora_seg_entrada` FOREIGN KEY (`entrada_id`) REFERENCES `bitacora_entradas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_bitacora_seg_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE,

    INDEX `idx_bitacora_seg_entrada_fecha` (`entrada_id`, `creado_en`),
    INDEX `idx_bitacora_seg_usuario` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Trazabilidad append-only de comentarios y transiciones de bitácora';

-- ----------------------------------------------------------------------------
-- 3. Catálogo de Permisos RBAC de Bitácora (ROLES-1)
-- ----------------------------------------------------------------------------
INSERT INTO `permisos` (`codigo`, `nombre`, `descripcion`, `modulo`, `estado`, `es_sistema`) VALUES
('bitacora.ver', 'Ver libro de guardia', 'Permite consultar el feed y entradas de la bitácora operativa', 'operaciones', 'ACTIVO', 1),
('bitacora.crear', 'Crear entradas en bitácora', 'Permite registrar novedades, consignas e incidencias de guardia', 'operaciones', 'ACTIVO', 1),
('bitacora.seguir', 'Añadir seguimientos y notas', 'Permite comentar o enmendar entradas en el libro de guardia', 'operaciones', 'ACTIVO', 1),
('bitacora.resolver', 'Resolver y gestionar consignas', 'Permite marcar consignas e incidencias como resueltas o en proceso', 'operaciones', 'ACTIVO', 1),
('bitacora.anular', 'Anular entradas de guardia', 'Permite anulación supervisada con motivo justificado (ANULAR != DELETE)', 'operaciones', 'ACTIVO', 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

-- ----------------------------------------------------------------------------
-- 4. Asignación de Permisos al Rol SUPERADMINISTRADOR
-- ----------------------------------------------------------------------------
INSERT IGNORE INTO `roles_permisos` (`rol_id`, `permiso_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
CROSS JOIN `permisos` p
WHERE r.`codigo` = 'SUPERADMINISTRADOR'
  AND p.`codigo` LIKE 'bitacora.%';

-- ----------------------------------------------------------------------------
-- 5. Opción de Menú Alina: Libro de Guardia (Bajo Operaciones)
-- ----------------------------------------------------------------------------
INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'operaciones_bitacora', 'Libro de Guardia', 'fa-solid fa-book', '/operaciones/bitacora', 5, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'operaciones' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'bitacora.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- DEVENGO-ALOJAMIENTO-1 (MIGRACIÓN 028): Cierres Hoteleros y Devengos
-- ============================================================================

-- ============================================================================
-- CAMARGO PMS - MIGRACIÓN 028: Devengo Diario de Alojamiento y Auditoría Nocturna
-- Microfase: DEVENGO-ALOJAMIENTO-1
-- Gobernanza: D-090
-- Tablas creadas: `cierres_hoteleros`, `devengos_alojamiento`
-- Total tablas en BD tras ejecución: 108 tablas
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 1. Tabla: cierres_hoteleros (Auditoría Nocturna / Cierre de Día Hotelero)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cierres_hoteleros` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `propiedad_id` BIGINT UNSIGNED NOT NULL,
    `fecha_hotelera` DATE NOT NULL,
    `timezone_utilizada` VARCHAR(50) NOT NULL DEFAULT 'America/Lima',
    `estado` ENUM('EN_PROCESO', 'CERRADO', 'FALLIDO') NOT NULL DEFAULT 'EN_PROCESO',
    `total_estadias_procesadas` INT UNSIGNED NOT NULL DEFAULT 0,
    `total_noches_devengadas` INT UNSIGNED NOT NULL DEFAULT 0,
    `ingreso_alojamiento_neto` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `ingreso_alojamiento_impuestos` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `ingreso_alojamiento_total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `unidades_totales` INT UNSIGNED NOT NULL DEFAULT 0,
    `unidades_ooo` INT UNSIGNED NOT NULL DEFAULT 0,
    `unidades_vendibles` INT UNSIGNED NOT NULL DEFAULT 0,
    `habitaciones_vendidas` INT UNSIGNED NOT NULL DEFAULT 0,
    `habitaciones_cortesia` INT UNSIGNED NOT NULL DEFAULT 0,
    `ocupacion_porcentaje` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `adr` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `revpar` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `iniciado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `cerrado_en` DATETIME NULL,
    `ejecutado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `observaciones` TEXT NULL,
    `error_mensaje` TEXT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_cierre_propiedad_fecha` (`propiedad_id`, `fecha_hotelera`),
    INDEX `idx_ch_estado` (`estado`),
    INDEX `idx_ch_fecha` (`fecha_hotelera`),
    CONSTRAINT `fk_ch_propiedad` FOREIGN KEY (`propiedad_id`) REFERENCES `propiedades` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_ch_actor` FOREIGN KEY (`ejecutado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `chk_ch_ocupacion` CHECK (`ocupacion_porcentaje` >= 0.00 AND `ocupacion_porcentaje` <= 100.00),
    CONSTRAINT `chk_ch_ingreso_neto` CHECK (`ingreso_alojamiento_neto` >= 0.00),
    CONSTRAINT `chk_ch_ingreso_total` CHECK (`ingreso_alojamiento_total` >= 0.00),
    CONSTRAINT `chk_ch_adr` CHECK (`adr` >= 0.00),
    CONSTRAINT `chk_ch_revpar` CHECK (`revpar` >= 0.00)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Cierres soberanos de fecha hotelera y snapshots de inventario vendible';

-- ----------------------------------------------------------------------------
-- 2. Tabla: devengos_alojamiento (Libro Diario de Devengos por Noche Hotelera)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `devengos_alojamiento` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `codigo` VARCHAR(30) NOT NULL,
    `cierre_hotelero_id` BIGINT UNSIGNED NULL,
    `estadia_id` BIGINT UNSIGNED NOT NULL,
    `reserva_id` BIGINT UNSIGNED NOT NULL,
    `reserva_unidad_id` BIGINT UNSIGNED NOT NULL,
    `unidad_id` BIGINT UNSIGNED NOT NULL,
    `propiedad_id` BIGINT UNSIGNED NOT NULL,
    `cargo_cuenta_id` BIGINT UNSIGNED NULL,
    `fecha_hotelera` DATE NOT NULL,
    `noche_indice` INT UNSIGNED NOT NULL,
    `total_noches_estadia` INT UNSIGNED NOT NULL,
    `secuencia` INT UNSIGNED NOT NULL DEFAULT 1,
    `tarifa_base_noche` DECIMAL(15,2) NOT NULL,
    `descuento_monto` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `impuesto_monto` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `importe_neto` DECIMAL(15,2) NOT NULL,
    `importe_total` DECIMAL(15,2) NOT NULL,
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `es_cortesia` TINYINT(1) NOT NULL DEFAULT 0,
    `origen_tarifa` ENUM('TARIFA_NOCTURNA_PACTADA', 'DISTRIBUCION_CONTRATO_UNIFORME', 'AJUSTE_OPERACIONAL') NOT NULL DEFAULT 'DISTRIBUCION_CONTRATO_UNIFORME',
    `metodo_distribucion` ENUM('TARIFA_EXPLICITA', 'DISTRIBUCION_UNIFORME', 'AJUSTE_RESIDUAL') NOT NULL DEFAULT 'DISTRIBUCION_UNIFORME',
    `timezone_utilizada` VARCHAR(50) NOT NULL DEFAULT 'America/Lima',
    `tarifa_snapshot` JSON NULL,
    `estado` ENUM('DEVENGADO', 'REVERTIDO') NOT NULL DEFAULT 'DEVENGADO',
    `metodo_devengo` ENUM('NIGHT_AUDIT', 'CHECKOUT_ANTICIPADO', 'MANUAL_SUPERVISADO') NOT NULL DEFAULT 'NIGHT_AUDIT',
    `reverso_de_id` BIGINT UNSIGNED NULL,
    `motivo_reversion` VARCHAR(255) NULL,
    `devengado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `devengado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `revertido_en` DATETIME NULL,
    `revertido_por_actor_id` BIGINT UNSIGNED NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_dev_codigo` (`codigo`),
    UNIQUE KEY `uq_devengo_estadia_fecha_sec` (`estadia_id`, `fecha_hotelera`, `secuencia`),
    INDEX `idx_dev_prop_fecha` (`propiedad_id`, `fecha_hotelera`, `estado`),
    INDEX `idx_dev_unidad_fecha` (`unidad_id`, `fecha_hotelera`),
    INDEX `idx_dev_cierre` (`cierre_hotelero_id`),
    INDEX `idx_dev_cargo` (`cargo_cuenta_id`),
    INDEX `idx_dev_reserva` (`reserva_id`),
    INDEX `idx_dev_reverso` (`reverso_de_id`),
    CONSTRAINT `fk_dev_cierre` FOREIGN KEY (`cierre_hotelero_id`) REFERENCES `cierres_hoteleros` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_dev_estadia` FOREIGN KEY (`estadia_id`) REFERENCES `estadias` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_dev_reserva` FOREIGN KEY (`reserva_id`) REFERENCES `reservas` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_dev_reserva_uni` FOREIGN KEY (`reserva_unidad_id`) REFERENCES `reserva_unidades` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_dev_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_dev_propiedad` FOREIGN KEY (`propiedad_id`) REFERENCES `propiedades` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_dev_cargo` FOREIGN KEY (`cargo_cuenta_id`) REFERENCES `cargos_cuenta` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_dev_reverso` FOREIGN KEY (`reverso_de_id`) REFERENCES `devengos_alojamiento` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_dev_actor_dev` FOREIGN KEY (`devengado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_dev_actor_rev` FOREIGN KEY (`revertido_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `chk_dev_neto` CHECK (`importe_neto` >= 0.00),
    CONSTRAINT `chk_dev_total` CHECK (`importe_total` >= 0.00),
    CONSTRAINT `chk_dev_noche_idx` CHECK (`noche_indice` >= 1),
    CONSTRAINT `chk_dev_sec` CHECK (`secuencia` >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Libro diario de reconocimiento económico de alojamiento por noche';

-- ----------------------------------------------------------------------------
-- 3. Catálogo de Permisos RBAC de Devengo y Night Audit (D-090)
-- ----------------------------------------------------------------------------
INSERT INTO `permisos` (`codigo`, `nombre`, `descripcion`, `modulo`, `estado`, `es_sistema`) VALUES
('devengo.ver', 'Ver devengos de alojamiento', 'Permite consultar el libro diario de devengos y detalle por estancia', 'operaciones', 'ACTIVO', 1),
('devengo.ejecutar', 'Devengar alojamiento manualmente', 'Permite ejecutar el devengo manual o por checkout anticipado', 'operaciones', 'ACTIVO', 1),
('devengo.revertir', 'Revertir devengos de alojamiento', 'Permite reversión supervisada con motivo justificado (REVERTIR != DELETE)', 'operaciones', 'ACTIVO', 1),
('night_audit.ver', 'Ver auditoría nocturna', 'Permite consultar el historial de cierres hoteleros y KPIs de ocupación', 'operaciones', 'ACTIVO', 1),
('night_audit.ejecutar', 'Ejecutar auditoría nocturna', 'Permite procesar el cierre diario hotelero y congelar métricas ADR/RevPAR', 'operaciones', 'ACTIVO', 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

-- ----------------------------------------------------------------------------
-- 4. Asignación de Permisos al Rol SUPERADMINISTRADOR
-- ----------------------------------------------------------------------------
INSERT IGNORE INTO `roles_permisos` (`rol_id`, `permiso_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
CROSS JOIN `permisos` p
WHERE r.`codigo` = 'SUPERADMINISTRADOR'
  AND (p.`codigo` LIKE 'devengo.%' OR p.`codigo` LIKE 'night_audit.%');

-- ----------------------------------------------------------------------------
-- 5. Opción de Menú Alina: Auditoría Nocturna (Bajo Caja y Finanzas)
-- ----------------------------------------------------------------------------
INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'operaciones_night_audit', 'Auditoría Nocturna', 'fa-solid fa-moon', '/operaciones/night-audit', 4, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'caja_finanzas' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'night_audit.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);

-- ============================================================================
-- 29. MÓDULO COMERCIAL DE CLIENTES Y FICHA INTEGRAL 360° (CLIENTES-1 / D-092)
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1. Tabla de Categorías Comerciales de Cliente
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cliente_categorias` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'Identificador único canónico: ESTANDAR, FRECUENTE, VIP',
    `nombre` VARCHAR(100) NOT NULL COMMENT 'Nombre visual descriptivo de la categoría',
    `descripcion` VARCHAR(255) NULL,
    `color_badge` VARCHAR(30) NOT NULL DEFAULT 'secondary' COMMENT 'Clase CSS o variante Bootstrap/Alina para chips/badges',
    `es_predeterminada` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 si es la categoría asignada automáticamente a nuevos clientes',
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_clic_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_clic_nombre_no_vacio` CHECK (`nombre` <> ''),
    UNIQUE KEY `uq_cliente_categorias_codigo` (`codigo`),
    INDEX `idx_cliente_categorias_activo` (`activo`),
    INDEX `idx_cliente_categorias_predeterminada` (`es_predeterminada`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Catálogo administrable de categorías comerciales de cliente';

-- Semillas oficiales de Categorías (Estrictamente ESTANDAR, FRECUENTE, VIP)
INSERT INTO `cliente_categorias` (`codigo`, `nombre`, `descripcion`, `color_badge`, `es_predeterminada`, `activo`) VALUES
('ESTANDAR', 'Estándar', 'Cliente estándar o nuevo sin historial comercial preferente', 'secondary', 1, 1),
('FRECUENTE', 'Frecuente', 'Cliente con estancias o arrendamientos recurrentes', 'info', 0, 1),
('VIP', 'VIP', 'Cliente distinguido de alto valor y atención prioritaria', 'warning', 0, 1)
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `descripcion` = VALUES(`descripcion`),
    `color_badge` = VALUES(`color_badge`),
    `es_predeterminada` = VALUES(`es_predeterminada`),
    `activo` = VALUES(`activo`);

-- ----------------------------------------------------------------------------
-- 2. Tabla Central de Perfiles de Clientes Comerciales
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `clientes` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(20) NOT NULL COMMENT 'Código comercial secuencial con prefijo CLI-XXXXX',
    `persona_id` BIGINT UNSIGNED NOT NULL COMMENT 'Relación 1:1 estricta con el maestro soberano de Personas',
    `categoria_id` INT UNSIGNED NOT NULL COMMENT 'FK a cliente_categorias',
    `estado` ENUM('ACTIVO', 'INACTIVO', 'BLOQUEADO') NOT NULL DEFAULT 'ACTIVO' COMMENT 'Estado comercial del cliente',
    `motivo_bloqueo` TEXT NULL COMMENT 'Obligatorio cuando estado = BLOQUEADO',
    `canal_captacion` VARCHAR(50) NULL COMMENT 'DIRECTO, WEB, OTA_BOOKING, AIRBNB, RECOMENDACION, WALK_IN, etc.',
    `preferencias` TEXT NULL COMMENT 'Preferencias declaradas del cliente (habitación alta, almohadas, etc.)',
    `observaciones` TEXT NULL COMMENT 'Notas operativas y comerciales internas',
    `creado_por_actor_id` BIGINT UNSIGNED NULL COMMENT 'Actor responsable del alta (D-061)',
    `actualizado_por_actor_id` BIGINT UNSIGNED NULL COMMENT 'Actor responsable de última modificación (D-061)',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_clientes_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `fk_clientes_persona` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_clientes_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `cliente_categorias` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_clientes_actor_creador` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_clientes_actor_actualizador` FOREIGN KEY (`actualizado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    UNIQUE KEY `uq_clientes_codigo` (`codigo`),
    UNIQUE KEY `uq_clientes_persona` (`persona_id`),
    INDEX `idx_clientes_categoria` (`categoria_id`),
    INDEX `idx_clientes_estado` (`estado`),
    INDEX `idx_clientes_canal` (`canal_captacion`),
    INDEX `idx_clientes_creado_en` (`creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Maestro de perfiles comerciales de clientes (Personas Naturales)';

-- ----------------------------------------------------------------------------
-- 3. Permisos RBAC para el Módulo de Clientes
-- ----------------------------------------------------------------------------
INSERT INTO `permisos` (`codigo`, `nombre`, `modulo`, `descripcion`) VALUES
('clientes.ver', 'Ver catálogo y ficha integral 360 de clientes', 'clientes', 'Permite consultar el directorio y la ficha integral 360 de clientes'),
('clientes.crear', 'Crear perfil comercial de cliente', 'clientes', 'Permite registrar un nuevo cliente vinculado a una persona'),
('clientes.editar', 'Modificar perfil comercial de cliente', 'clientes', 'Permite editar categoría, preferencias, observaciones y canal de captación'),
('clientes.bloquear', 'Bloquear o desbloquear clientes', 'clientes', 'Permite cambiar el estado comercial a BLOQUEADO con motivo obligatorio'),
('clientes.gestionar_categorias', 'Gestionar categorías de clientes', 'clientes', 'Permite administrar el catálogo de categorías comerciales de cliente')
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `descripcion` = VALUES(`descripcion`);

-- Asignar permisos al rol SUPERADMINISTRADOR (rol_id = 1)
INSERT INTO `roles_permisos` (`rol_id`, `permiso_id`)
SELECT 1, p.`id`
FROM `permisos` p
WHERE p.`modulo` = 'clientes'
ON DUPLICATE KEY UPDATE `permiso_id` = VALUES(`permiso_id`);

-- ----------------------------------------------------------------------------
-- 4. Opción de Menú en Navegación Administrativa (bajo Comercial y Reservas)
-- ----------------------------------------------------------------------------
INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT
    p.`id`,
    'clientes_catalogo',
    'Clientes',
    'fa-solid fa-users',
    '/clientes',
    3,
    'ACTIVO',
    perm.`id`,
    1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'reservas' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'clientes.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);

-- ============================================================================
-- 30. LIBRO DE RECLAMACIONES INTEGRAL (RECLAMACIONES-1)
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1. Catálogo Soberano de Feriados y Días No Laborables
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `calendario_feriados` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `fecha` DATE NOT NULL COMMENT 'Fecha del feriado o día no laborable',
    `descripcion` VARCHAR(150) NOT NULL COMMENT 'Descripción de la festividad o motivo',
    `tipo` ENUM('FERIADO_LEGAL', 'NO_LABORABLE_COMPENSABLE') NOT NULL DEFAULT 'FERIADO_LEGAL' COMMENT 'Feriado nacional de ley vs día no laborable administrativo',
    `aplica_sector_privado` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 si excluye cómputo legal en sector privado; 0 si solo rige para sector público',
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_cal_feriados_desc_no_vacia` CHECK (`descripcion` <> ''),
    UNIQUE KEY `uq_calendario_feriados_fecha` (`fecha`),
    INDEX `idx_cal_feriados_busqueda` (`fecha`, `activo`, `aplica_sector_privado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Calendario administrable de feriados oficiales para cómputo de plazos';

-- Semillas oficiales de Feriados Nacionales Peruanos (2026 y 2027)
INSERT INTO `calendario_feriados` (`fecha`, `descripcion`, `tipo`, `aplica_sector_privado`, `activo`) VALUES
-- 2026
('2026-01-01', 'Año Nuevo', 'FERIADO_LEGAL', 1, 1),
('2026-04-02', 'Jueves Santo', 'FERIADO_LEGAL', 1, 1),
('2026-04-03', 'Viernes Santo', 'FERIADO_LEGAL', 1, 1),
('2026-05-01', 'Día del Trabajo', 'FERIADO_LEGAL', 1, 1),
('2026-06-07', 'Batalla de Arica y Día de la Bandera', 'FERIADO_LEGAL', 1, 1),
('2026-06-29', 'San Pedro y San Pablo', 'FERIADO_LEGAL', 1, 1),
('2026-07-23', 'Día de la Fuerza Aérea del Perú', 'FERIADO_LEGAL', 1, 1),
('2026-07-28', 'Fiestas Patrias — Día de la Independencia', 'FERIADO_LEGAL', 1, 1),
('2026-07-29', 'Fiestas Patrias — Homenaje a la Patria', 'FERIADO_LEGAL', 1, 1),
('2026-08-06', 'Batalla de Junín', 'FERIADO_LEGAL', 1, 1),
('2026-08-30', 'Santa Rosa de Lima', 'FERIADO_LEGAL', 1, 1),
('2026-10-08', 'Combate de Angamos', 'FERIADO_LEGAL', 1, 1),
('2026-11-01', 'Día de Todos los Santos', 'FERIADO_LEGAL', 1, 1),
('2026-12-08', 'Inmaculada Concepción', 'FERIADO_LEGAL', 1, 1),
('2026-12-09', 'Batalla de Ayacucho', 'FERIADO_LEGAL', 1, 1),
('2026-12-25', 'Navidad', 'FERIADO_LEGAL', 1, 1),
-- 2027
('2027-01-01', 'Año Nuevo 2027', 'FERIADO_LEGAL', 1, 1),
('2027-03-25', 'Jueves Santo 2027', 'FERIADO_LEGAL', 1, 1),
('2027-03-26', 'Viernes Santo 2027', 'FERIADO_LEGAL', 1, 1),
('2027-05-01', 'Día del Trabajo 2027', 'FERIADO_LEGAL', 1, 1),
('2027-06-07', 'Batalla de Arica y Día de la Bandera 2027', 'FERIADO_LEGAL', 1, 1),
('2027-06-29', 'San Pedro y San Pablo 2027', 'FERIADO_LEGAL', 1, 1),
('2027-07-23', 'Día de la Fuerza Aérea del Perú 2027', 'FERIADO_LEGAL', 1, 1),
('2027-07-28', 'Fiestas Patrias 2027', 'FERIADO_LEGAL', 1, 1),
('2027-07-29', 'Fiestas Patrias 2027 (Segundo día)', 'FERIADO_LEGAL', 1, 1),
('2027-08-06', 'Batalla de Junín 2027', 'FERIADO_LEGAL', 1, 1),
('2027-08-30', 'Santa Rosa de Lima 2027', 'FERIADO_LEGAL', 1, 1),
('2027-10-08', 'Combate de Angamos 2027', 'FERIADO_LEGAL', 1, 1),
('2027-11-01', 'Día de Todos los Santos 2027', 'FERIADO_LEGAL', 1, 1),
('2027-12-08', 'Inmaculada Concepción 2027', 'FERIADO_LEGAL', 1, 1),
('2027-12-09', 'Batalla de Ayacucho 2027', 'FERIADO_LEGAL', 1, 1),
('2027-12-25', 'Navidad 2027', 'FERIADO_LEGAL', 1, 1)
ON DUPLICATE KEY UPDATE
    `descripcion` = VALUES(`descripcion`),
    `tipo` = VALUES(`tipo`),
    `aplica_sector_privado` = VALUES(`aplica_sector_privado`),
    `activo` = VALUES(`activo`);

-- ----------------------------------------------------------------------------
-- 2. Control Concurrente de Correlativos por Sede y Año Fiscal
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reclamacion_secuencias` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `propiedad_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK al establecimiento comercial físico',
    `anio` INT UNSIGNED NOT NULL COMMENT 'Año fiscal de la secuencia (ej. 2026)',
    `ultimo_numero` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Correlativo incremental asegurado con SELECT FOR UPDATE',
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_rec_secuencias_propiedad` FOREIGN KEY (`propiedad_id`) REFERENCES `propiedades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_rec_secuencia_propiedad_anio` (`propiedad_id`, `anio`),
    INDEX `idx_rec_secuencias_propiedad` (`propiedad_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Secuencias correlativas atómicas de hojas de reclamación por sede y año';

-- ----------------------------------------------------------------------------
-- 3. Tabla Maestra de Expedientes de Reclamaciones
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reclamaciones` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo_hoja` VARCHAR(30) NOT NULL COMMENT 'Numeración correlativa exigida por el Libro para el establecimiento',
    `codigo_interno` VARCHAR(40) NOT NULL COMMENT 'Identificador técnico estable Camargo PMS: LR-{SEDE}-{AAAA}-{NNNNN}',
    `empresa_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK al proveedor emisor responsable legal',
    `propiedad_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK a la sede física o establecimiento donde ocurrió el hecho',
    `consumidor_persona_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK al consumidor reclamante en el maestro soberano de personas',
    `es_menor_edad` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 si el consumidor es menor de edad y se registró con apoderado',
    `apoderado_persona_id` BIGINT UNSIGNED NULL COMMENT 'FK a la persona del padre/madre/tutor o apoderado legal',
    `tipo` ENUM('RECLAMO', 'QUEJA') NOT NULL COMMENT 'Tipificación legal excluyente según D.S. 011-2011-PCM',
    `tipo_bien` ENUM('PRODUCTO', 'SERVICIO') NOT NULL DEFAULT 'SERVICIO' COMMENT 'Bien contratado en disputa',
    `monto_reclamado` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Importe económico reclamado (0.00 si no aplica)',
    `moneda` VARCHAR(3) NOT NULL DEFAULT 'PEN' COMMENT 'Código ISO de la moneda (PEN, USD)',
    `descripcion_bien` VARCHAR(255) NOT NULL COMMENT 'Descripción sucinta del producto o servicio contratado',
    `detalle_reclamacion` TEXT NOT NULL COMMENT 'Relato inmutable de los hechos expuestos por el consumidor',
    `pedido_consumidor` TEXT NOT NULL COMMENT 'Pretensión concreta que solicita el consumidor',
    `canal_entrada` ENUM('VIRTUAL', 'PRESENCIAL') NOT NULL DEFAULT 'VIRTUAL' COMMENT 'Canal de interposición del reclamo',
    `estado` ENUM('REGISTRADO', 'EN_PROCESO', 'SUSPENDIDO_OFRECIMIENTO', 'ATENDIDO', 'CONCLUIDO_POR_ACUERDO', 'ANULADO') NOT NULL DEFAULT 'REGISTRADO' COMMENT 'Estado regulatorio del expediente',
    `fecha_interposicion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora exacta de presentación de la Hoja',
    `fecha_limite_legal` DATE NOT NULL COMMENT 'Fecha límite legal computable (15 días hábiles según Ley 31435)',
    `dias_habiles_consumidos` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Días hábiles transcurridos hasta la fecha o suspensión',
    `fecha_suspension` DATE NULL COMMENT 'Fecha en que se formuló ofrecimiento de solución',
    `fecha_limite_ofrecimiento` DATE NULL COMMENT 'Plazo máximo de 5 días hábiles para pronunciamiento del consumidor',
    `documento_emitido_id` BIGINT UNSIGNED NULL COMMENT 'FK a documentos_emitidos para la representación PDF oficial',
    `motivo_anulacion` TEXT NULL COMMENT 'Obligatorio ante anulación formal supervisada',
    `anulado_por_actor_id` BIGINT UNSIGNED NULL COMMENT 'FK a actores para el responsable de anulación',
    `anulado_en` DATETIME NULL COMMENT 'Momento en que se anuló el expediente',
    `snapshot_consumidor_json` JSON NOT NULL COMMENT 'Snapshot legal inmutable T0 de identidad civil, domicilio y contactos del consumidor y apoderado',
    `snapshot_proveedor_json` JSON NOT NULL COMMENT 'Snapshot legal inmutable T0 de razón social, RUC, domicilio fiscal y datos del establecimiento',
    `creado_por_actor_id` BIGINT UNSIGNED NULL COMMENT 'FK a actores creador (null si es autoservicio público)',
    `actualizado_por_actor_id` BIGINT UNSIGNED NULL COMMENT 'FK a actores que modificó el estado o expediente',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_rec_codigo_hoja_no_vacio` CHECK (`codigo_hoja` <> ''),
    CONSTRAINT `chk_rec_codigo_interno_no_vacio` CHECK (`codigo_interno` <> ''),
    CONSTRAINT `chk_rec_descripcion_no_vacia` CHECK (`descripcion_bien` <> ''),
    CONSTRAINT `chk_rec_detalle_no_vacio` CHECK (`detalle_reclamacion` <> ''),
    CONSTRAINT `chk_rec_pedido_no_vacio` CHECK (`pedido_consumidor` <> ''),
    CONSTRAINT `fk_reclamaciones_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_reclamaciones_propiedad` FOREIGN KEY (`propiedad_id`) REFERENCES `propiedades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_reclamaciones_consumidor` FOREIGN KEY (`consumidor_persona_id`) REFERENCES `personas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_reclamaciones_apoderado` FOREIGN KEY (`apoderado_persona_id`) REFERENCES `personas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_reclamaciones_doc_emitido` FOREIGN KEY (`documento_emitido_id`) REFERENCES `documentos_emitidos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_reclamaciones_actor_anulador` FOREIGN KEY (`anulado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_reclamaciones_actor_creador` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_reclamaciones_actor_actualizador` FOREIGN KEY (`actualizado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    UNIQUE KEY `uq_reclamaciones_codigo_interno` (`codigo_interno`),
    INDEX `idx_reclamaciones_propiedad_hoja` (`propiedad_id`, `codigo_hoja`),
    INDEX `idx_reclamaciones_consumidor` (`consumidor_persona_id`),
    INDEX `idx_reclamaciones_estado` (`estado`),
    INDEX `idx_reclamaciones_tipo` (`tipo`),
    INDEX `idx_reclamaciones_fecha_interposicion` (`fecha_interposicion`),
    INDEX `idx_reclamaciones_fecha_limite` (`fecha_limite_legal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Expedientes regulatorios del Libro de Reclamaciones';

-- ----------------------------------------------------------------------------
-- 4. Trazabilidad Append-Only de Actuaciones del Expediente
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reclamacion_actuaciones` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `reclamacion_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK al expediente regulatorio',
    `actor_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK al actor que ejecuta la actuación',
    `tipo_actuacion` ENUM(
        'RECEPCION_INICIAL',
        'NOTA_INTERNA',
        'OFRECIMIENTO_SOLUCION',
        'RESPUESTA_OFRECIMIENTO_ACEPTADO',
        'RESPUESTA_OFRECIMIENTO_RECHAZADO',
        'EXPIRACION_OFRECIMIENTO',
        'RESPUESTA_FORMAL',
        'NOTIFICACION_ENVIADA',
        'ANULACION_SUPERVISADA'
    ) NOT NULL COMMENT 'Tipificación de la actuación legal o administrativa',
    `descripcion` TEXT NOT NULL COMMENT 'Detalle del acto, descargo, contenido de la respuesta u observaciones',
    `medio_notificacion` ENUM('CORREO_ELECTRONICO', 'CARTA_NOTARIAL', 'FISICO_RECEPCION', 'SISTEMA_WEB') NULL COMMENT 'Medio probatorio de notificación al consumidor',
    `destinatario_notificacion` VARCHAR(150) NULL COMMENT 'Correo o dirección probatoria notificada',
    `fecha_notificacion` DATETIME NULL COMMENT 'Fecha y hora en que se materializó la notificación',
    `documento_emitido_id` BIGINT UNSIGNED NULL COMMENT 'FK a documentos_emitidos en caso de cartas o resoluciones formales generadas',
    `archivo_adjunto_path` VARCHAR(255) NULL COMMENT 'Ruta interna controlada en almacenamiento local para evidencias',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_rec_actuaciones_desc_no_vacia` CHECK (`descripcion` <> ''),
    CONSTRAINT `fk_rec_actuaciones_reclamacion` FOREIGN KEY (`reclamacion_id`) REFERENCES `reclamaciones` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_rec_actuaciones_actor` FOREIGN KEY (`actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_rec_actuaciones_doc_emitido` FOREIGN KEY (`documento_emitido_id`) REFERENCES `documentos_emitidos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_rec_actuaciones_reclamacion` (`reclamacion_id`),
    INDEX `idx_rec_actuaciones_tipo` (`tipo_actuacion`),
    INDEX `idx_rec_actuaciones_creado_en` (`creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Historial append-only de actuaciones y respuestas del expediente';

-- ----------------------------------------------------------------------------
-- 5. Adaptación del Motor Documental (D-079 / RECLAMACIONES-1)
-- ----------------------------------------------------------------------------
ALTER TABLE `documento_plantillas`
    MODIFY COLUMN `origen_tipo_permitido` ENUM('ARRENDAMIENTO', 'RESERVA', 'ESTADIA', 'PAGO', 'RECIBO', 'COMPRA', 'RECLAMACION') NOT NULL;

ALTER TABLE `documentos_emitidos`
    MODIFY COLUMN `origen_tipo` ENUM('ARRENDAMIENTO', 'RESERVA', 'ESTADIA', 'PAGO', 'RECIBO', 'COMPRA', 'RECLAMACION') NOT NULL;

INSERT INTO `documento_plantillas` (
    `codigo`, `nombre`, `descripcion`, `origen_tipo_permitido`,
    `orientacion`, `tamano_papel`, `requiere_membrete`,
    `margen_superior_mm`, `margen_inferior_mm`, `margen_izquierdo_mm`, `margen_derecho_mm`,
    `estado`
) VALUES (
    'HOJA_RECLAMACION',
    'Hoja de Reclamación Oficial A4',
    'Formato regulatorio estandarizado de Hoja de Reclamación según D.S. 011-2011-PCM y Ley 29571',
    'RECLAMACION',
    'PORTRAIT',
    'A4',
    0,
    15, 15, 15, 15,
    'ACTIVO'
) ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `descripcion` = VALUES(`descripcion`);

INSERT INTO `documento_plantilla_versiones` (
    `plantilla_id`, `numero_version`, `titulo_documento`, `cuerpo_html`, `es_activa`, `creado_por_actor_id`
)
SELECT p.`id`, 1, 'Hoja de Reclamación Oficial', '<!-- Template gestionado por ReclamacionDocumentoServicio -->', 1, 1
FROM `documento_plantillas` p
WHERE p.`codigo` = 'HOJA_RECLAMACION'
ON DUPLICATE KEY UPDATE `titulo_documento` = VALUES(`titulo_documento`);

-- ----------------------------------------------------------------------------
-- 6. Permisos RBAC para Reclamaciones y Feriados
-- ----------------------------------------------------------------------------
INSERT INTO `permisos` (`codigo`, `nombre`, `modulo`, `descripcion`) VALUES
('reclamaciones.ver', 'Ver libro y expedientes de reclamaciones', 'reclamaciones', 'Permite consultar el listado y fichas de expedientes de reclamos y quejas'),
('reclamaciones.crear', 'Registrar reclamación asistida en recepción', 'reclamaciones', 'Permite ingresar reclamaciones presenciales desde la consola interna'),
('reclamaciones.gestionar', 'Gestionar expedientes y calendario de feriados', 'reclamaciones', 'Permite administrar el estado de expedientes y el catálogo de días no laborables'),
('reclamaciones.actuar', 'Añadir notas y actuaciones al expediente', 'reclamaciones', 'Permite asentar notas internas y diligencias append-only'),
('reclamaciones.responder', 'Formular ofrecimientos y emitir respuesta formal', 'reclamaciones', 'Permite formular ofrecimientos de solución y emitir la respuesta oficial al consumidor'),
('reclamaciones.anular', 'Anulación supervisada de expedientes', 'reclamaciones', 'Permite anular expedientes por duplicidad técnica comprobada con motivo obligatorio')
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `descripcion` = VALUES(`descripcion`);

-- Asignar permisos al rol SUPERADMINISTRADOR (rol_id = 1)
INSERT INTO `roles_permisos` (`rol_id`, `permiso_id`)
SELECT 1, p.`id`
FROM `permisos` p
WHERE p.`modulo` = 'reclamaciones'
ON DUPLICATE KEY UPDATE `permiso_id` = VALUES(`permiso_id`);

-- ----------------------------------------------------------------------------
-- 7. Opciones de Menú en Navegación Administrativa Alina
-- ----------------------------------------------------------------------------
-- Opción 1: Directorio del Libro de Reclamaciones (Bajo Atención al Cliente)
INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT
    p.`id`,
    'reclamaciones_directorio',
    'Libro de Reclamaciones',
    'fa-solid fa-book-open-reader',
    '/reclamaciones',
    1,
    'ACTIVO',
    perm.`id`,
    1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'atencion_cliente' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'reclamaciones.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);

-- Opción 2: Calendario de Feriados (Bajo categoría Configuración)
INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT
    p.`id`,
    'config_feriados',
    'Calendario de Feriados',
    'fa-solid fa-calendar-days',
    '/configuracion/feriados',
    8,
    'ACTIVO',
    perm.`id`,
    1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'configuracion' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'reclamaciones.gestionar'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);

-- ============================================================================
-- INFRAESTRUCTURA MULTICANAL ICALENDAR (MIGRACIÓN 035 / AIRBNB-ICAL-1B)
-- ============================================================================

-- 1. Catálogo Maestro de Canales de Distribución
CREATE TABLE IF NOT EXISTS `canales_distribucion` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(50) NOT NULL COMMENT 'Código canónico del canal (AIRBNB, BOOKING, VRBO, GENERICO)',
    `nombre` VARCHAR(100) NOT NULL COMMENT 'Nombre comercial del canal',
    `protocolo` VARCHAR(20) NOT NULL DEFAULT 'ICAL' COMMENT 'Protocolo técnico de integración (ICAL)',
    `frecuencia_defecto_minutos` INT UNSIGNED NOT NULL DEFAULT 60 COMMENT 'Frecuencia de sondeo sugerida en minutos',
    `color_badge` VARCHAR(30) NOT NULL DEFAULT 'badge-light-primary' COMMENT 'Clase visual Alina para badges',
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_canales_distribucion_codigo` (`codigo`),
    INDEX `idx_canales_distribucion_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Catálogo maestro de canales de distribución y OTAs';

INSERT INTO `canales_distribucion` (`codigo`, `nombre`, `protocolo`, `frecuencia_defecto_minutos`, `color_badge`, `estado`) VALUES
('AIRBNB', 'Airbnb', 'ICAL', 60, 'badge-light-danger', 'ACTIVO'),
('BOOKING', 'Booking.com', 'ICAL', 60, 'badge-light-primary', 'ACTIVO'),
('VRBO', 'VRBO / HomeAway', 'ICAL', 60, 'badge-light-info', 'ACTIVO'),
('GENERICO', 'Canal iCal Genérico', 'ICAL', 60, 'badge-light-secondary', 'ACTIVO')
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

-- 2. Conexiones iCalendar por Unidad Habitacional (1:N)
CREATE TABLE IF NOT EXISTS `conexiones_ical` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `unidad_id` BIGINT UNSIGNED NOT NULL COMMENT 'Unidad habitacional vinculada',
    `canal_id` INT UNSIGNED NOT NULL COMMENT 'Canal de distribución asociado',
    `nombre` VARCHAR(150) NOT NULL COMMENT 'Nombre descriptivo de la conexión (ej. Airbnb Suite 101)',
    `url_importacion_cifrada` TEXT DEFAULT NULL COMMENT 'URL externa del feed iCal cifrada con AES-256-GCM',
    `token_exportacion_hash` CHAR(64) NOT NULL COMMENT 'Hash SHA-256 del token de exportación para búsqueda indexada O(1)',
    `token_exportacion_cifrado` TEXT NOT NULL COMMENT 'Token en claro cifrado con AES-256-GCM para visualización en panel',
    `token_prefijo` VARCHAR(16) NOT NULL COMMENT 'Prefijo legible del token para identificación segura sin descifrar',
    `importacion_habilitada` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 si permite importar eventos desde la URL externa',
    `exportacion_habilitada` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 si el feed de salida responde con bloqueos',
    `frecuencia_minutos` INT UNSIGNED NOT NULL DEFAULT 60 COMMENT 'Frecuencia de sincronización programada',
    `estado` ENUM('ACTIVO', 'PAUSADO', 'REVOCADO') NOT NULL DEFAULT 'ACTIVO' COMMENT 'Estado operativo de la conexión',
    `ultima_sincronizacion_en` DATETIME DEFAULT NULL COMMENT 'Timestamp de la última sincronización ejecutada',
    `ultimo_resultado` ENUM('EXITO', 'CON_ADVERTENCIA', 'ERROR', 'NO_EJECUTADO') NOT NULL DEFAULT 'NO_EJECUTADO',
    `ultimo_error` TEXT DEFAULT NULL COMMENT 'Detalle del último error técnico o advertencia',
    `creado_por` BIGINT UNSIGNED DEFAULT NULL COMMENT 'Actor o usuario que creó la conexión',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_conexiones_ical_token_hash` (`token_exportacion_hash`),
    INDEX `idx_conexiones_ical_unidad` (`unidad_id`),
    INDEX `idx_conexiones_ical_canal` (`canal_id`),
    INDEX `idx_conexiones_ical_estado` (`estado`),
    CONSTRAINT `fk_conexiones_ical_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_conexiones_ical_canal` FOREIGN KEY (`canal_id`) REFERENCES `canales_distribucion` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Conexiones iCalendar 1:N por unidad habitacional';

-- 3. Eventos iCalendar Externos (VEVENT)
CREATE TABLE IF NOT EXISTS `eventos_ical_externos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `conexion_ical_id` BIGINT UNSIGNED NOT NULL COMMENT 'Conexión que importó el evento',
    `uid_externo` VARCHAR(255) NOT NULL COMMENT 'UID original del VEVENT en el feed externo',
    `fecha_inicio` DATE NOT NULL COMMENT 'Fecha de inicio del bloqueo hotelero (inclusive)',
    `fecha_fin` DATE NOT NULL COMMENT 'Fecha de fin del bloqueo hotelero [inicio, fin) (exclusive)',
    `noches` INT UNSIGNED NOT NULL COMMENT 'Cantidad de noches bloqueadas',
    `resumen` VARCHAR(255) NOT NULL DEFAULT 'Bloqueo Canal Externo' COMMENT 'SUMMARY normalizado',
    `descripcion` TEXT DEFAULT NULL COMMENT 'DESCRIPTION original si viene provista',
    `estado_evento` ENUM('ACTIVO', 'CANCELADO', 'AUSENTE', 'EN_CONFLICTO') NOT NULL DEFAULT 'ACTIVO' COMMENT 'Estado ontológico del evento externo',
    `estado_bloqueo` ENUM('APLICADO', 'EN_CONFLICTO', 'LIBERADO', 'APLICADO_CON_SOLAPAMIENTO', 'IGNORADO') NOT NULL DEFAULT 'APLICADO' COMMENT 'Estado físico del bloqueo en inventario_diario_unidades',
    `detalle_conflicto` TEXT DEFAULT NULL COMMENT 'Explicación del conflicto en caso de colisión local o error',
    `ultima_modificacion_externa` DATETIME DEFAULT NULL COMMENT 'LAST-MODIFIED del VEVENT',
    `secuencia_externa` INT DEFAULT NULL COMMENT 'SEQUENCE del VEVENT',
    `es_recurrente` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 si el evento proviene de expansión de RRULE',
    `recurrencia_rrule` TEXT DEFAULT NULL COMMENT 'Regla RRULE original si aplica',
    `ultimo_sync_run_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'ID de la última corrida de sincronización que lo observó',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_evento_conexion_uid` (`conexion_ical_id`, `uid_externo`),
    INDEX `idx_eventos_ical_fechas` (`conexion_ical_id`, `fecha_inicio`, `fecha_fin`),
    INDEX `idx_eventos_ical_estado_evento` (`conexion_ical_id`, `estado_evento`),
    INDEX `idx_eventos_ical_estado_bloqueo` (`conexion_ical_id`, `estado_bloqueo`),
    CONSTRAINT `fk_eventos_ical_conexion` FOREIGN KEY (`conexion_ical_id`) REFERENCES `conexiones_ical` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Eventos VEVENT externos importados desde feeds iCal';

-- 4. Log y Telemetría Técnica de Sincronizaciones iCalendar
CREATE TABLE IF NOT EXISTS `sincronizaciones_ical_log` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `conexion_ical_id` BIGINT UNSIGNED NOT NULL COMMENT 'Conexión sincronizada',
    `tipo_operacion` ENUM('IMPORTACION', 'EXPORTACION') NOT NULL COMMENT 'Sentido del intercambio',
    `origen_ejecucion` ENUM('MANUAL', 'CLI', 'CRON', 'WEBHOOK') NOT NULL DEFAULT 'MANUAL' COMMENT 'Disparador de la ejecución',
    `iniciado_en` DATETIME NOT NULL COMMENT 'Timestamp de inicio del sondeo o entrega',
    `finalizado_en` DATETIME DEFAULT NULL COMMENT 'Timestamp de finalización',
    `duracion_ms` INT UNSIGNED DEFAULT NULL COMMENT 'Duración total en milisegundos',
    `http_codigo` INT DEFAULT NULL COMMENT 'Código de respuesta HTTP obtenido del feed externo',
    `resultado` ENUM('EXITO', 'CON_ADVERTENCIA', 'ERROR') NOT NULL COMMENT 'Resultado de la ejecución',
    `eventos_recibidos` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Conteo de VEVENTs parseados',
    `eventos_creados` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Nuevos eventos insertados',
    `eventos_actualizados` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Eventos modificados',
    `eventos_cancelados` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Eventos marcados CANCELLED',
    `eventos_ausentes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Eventos ausentes en feed',
    `conflictos_detectados` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Colisiones con reservas locales detectadas',
    `mensaje_resultado` TEXT DEFAULT NULL COMMENT 'Mensaje explicativo o advertencia técnica',
    `actor_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'Actor técnico o usuario responsable',
    `ip_origen` VARCHAR(45) DEFAULT NULL COMMENT 'IP cliente en caso de exportación o webhook',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_sync_log_conexion` (`conexion_ical_id`, `iniciado_en`),
    INDEX `idx_sync_log_resultado` (`resultado`),
    CONSTRAINT `fk_sync_log_conexion` FOREIGN KEY (`conexion_ical_id`) REFERENCES `conexiones_ical` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Telemetría y log técnico de sincronizaciones iCalendar';

SET FOREIGN_KEY_CHECKS = 1;


