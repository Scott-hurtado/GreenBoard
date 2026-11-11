-- Módulo Representantes · tabla `rep_bitacora`
-- Sin dependencias
SET NAMES utf8mb4;

-- Bitácora de solo inserción (RN-06, RN-17)
CREATE TABLE IF NOT EXISTS `rep_bitacora` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `proceso_id` int(10) UNSIGNED DEFAULT NULL,
  `region_id` int(10) UNSIGNED DEFAULT NULL,
  `zona_id` int(10) UNSIGNED DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_nombre` varchar(150) DEFAULT NULL,
  `rol` varchar(40) DEFAULT NULL,
  `accion` varchar(80) NOT NULL,
  `entidad` varchar(200) DEFAULT NULL,
  `detalle` text DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_proc_fecha` (`proceso_id`,`fecha`),
  KEY `idx_region` (`region_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
