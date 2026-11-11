-- Módulo Representantes · tabla `rep_meta_versiones`
-- Requiere que ya existan: rep_procesos
SET NAMES utf8mb4;

-- Historial de publicaciones de metas (RN-13)
CREATE TABLE IF NOT EXISTS `rep_meta_versiones` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `proceso_id` int(10) UNSIGNED NOT NULL,
  `version` int(10) UNSIGNED NOT NULL,
  `ambito` varchar(200) NOT NULL,
  `padre_tipo` varchar(10) NOT NULL,
  `padre_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `valores` longtext NOT NULL COMMENT 'JSON',
  `suma` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `superior` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `autorizada` tinyint(1) NOT NULL DEFAULT 0,
  `motivo` varchar(255) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_nombre` varchar(150) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_proc` (`proceso_id`,`version`),
  CONSTRAINT `fk_rep_mv_proc` FOREIGN KEY (`proceso_id`) REFERENCES `rep_procesos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
