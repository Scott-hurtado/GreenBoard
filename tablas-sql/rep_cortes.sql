-- Módulo Representantes · tabla `rep_cortes`
-- Requiere que ya existan: rep_procesos
SET NAMES utf8mb4;

-- Ruta de cortes: % acumulado esperado por etapa
CREATE TABLE IF NOT EXISTS `rep_cortes` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `proceso_id` int(10) UNSIGNED NOT NULL,
  `fecha` date NOT NULL,
  `orden` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `pct_rec` decimal(5,2) NOT NULL DEFAULT 0,
  `pct_exp` decimal(5,2) NOT NULL DEFAULT 0,
  `pct_reg` decimal(5,2) NOT NULL DEFAULT 0,
  `pct_con` decimal(5,2) NOT NULL DEFAULT 0,
  `cerrado_en` datetime DEFAULT NULL,
  `cerrado_por` varchar(150) DEFAULT NULL,
  `snapshot` longtext DEFAULT NULL COMMENT 'JSON inmutable al cerrar (RN-08)',
  PRIMARY KEY (`id`),
  KEY `idx_proceso` (`proceso_id`,`fecha`),
  CONSTRAINT `fk_rep_cortes_proc` FOREIGN KEY (`proceso_id`) REFERENCES `rep_procesos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
