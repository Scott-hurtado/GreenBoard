-- Módulo Representantes · tabla `rep_metas`
-- Requiere que ya existan: rep_procesos
SET NAMES utf8mb4;

-- Metas vigentes por unidad (estado / región / zona)
CREATE TABLE IF NOT EXISTS `rep_metas` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `proceso_id` int(10) UNSIGNED NOT NULL,
  `unidad_tipo` enum('estado','region','zona') NOT NULL,
  `unidad_id` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 para estado',
  `meta_rep` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'RG + RC',
  `meta_con` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Consejeros',
  `version` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `actualizado_por` varchar(150) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_meta` (`proceso_id`,`unidad_tipo`,`unidad_id`),
  CONSTRAINT `fk_rep_meta_proc` FOREIGN KEY (`proceso_id`) REFERENCES `rep_procesos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
