-- Módulo Representantes · tabla `rep_personas`
-- Sin dependencias
SET NAMES utf8mb4;

-- Registro maestro único de personas (anti-duplicado por hash de CURP)
CREATE TABLE IF NOT EXISTS `rep_personas` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `curp_hash` char(64) NOT NULL,
  `curp_cifrado` text NOT NULL,
  `curp_mask` varchar(20) NOT NULL,
  `nombre` varchar(60) NOT NULL,
  `ap_paterno` varchar(60) NOT NULL,
  `ap_materno` varchar(60) NOT NULL DEFAULT '',
  `telefono` varchar(10) NOT NULL,
  `email` varchar(120) NOT NULL DEFAULT '',
  `seccion_domicilio` varchar(10) NOT NULL,
  `estado` varchar(100) NOT NULL,
  `creado_por` int(11) DEFAULT NULL,
  `creado_por_rol` varchar(40) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_curp_hash` (`curp_hash`),
  KEY `idx_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
