-- Módulo Representantes · tabla `rep_casillas`
-- Requiere que ya existan: rep_procesos
SET NAMES utf8mb4;

-- Catálogo de casillas (hija de la sección; la zona se deriva de la sección)
CREATE TABLE IF NOT EXISTS `rep_casillas` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `proceso_id` int(10) UNSIGNED NOT NULL,
  `seccion` varchar(10) NOT NULL,
  `tipo` varchar(6) NOT NULL COMMENT 'B, C1..Cn, E1..En, X1..Xn',
  `ubicacion` varchar(255) DEFAULT NULL,
  `activa` tinyint(1) NOT NULL DEFAULT 1,
  `version_catalogo` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `fuente` varchar(50) NOT NULL DEFAULT 'manual',
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `baja_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_casilla` (`proceso_id`,`seccion`,`tipo`),
  KEY `idx_seccion` (`proceso_id`,`seccion`,`activa`),
  CONSTRAINT `fk_rep_casillas_proc` FOREIGN KEY (`proceso_id`) REFERENCES `rep_procesos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
