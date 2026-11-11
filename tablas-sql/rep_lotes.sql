-- Módulo Representantes · tabla `rep_lotes`
-- Requiere que ya existan: rep_procesos
SET NAMES utf8mb4;

-- Lotes de registro ante la autoridad (acuses)
CREATE TABLE IF NOT EXISTS `rep_lotes` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `proceso_id` int(10) UNSIGNED NOT NULL,
  `folio` varchar(50) NOT NULL,
  `fecha_presentacion` date NOT NULL,
  `archivo` varchar(255) DEFAULT NULL,
  `nombre_original` varchar(255) DEFAULT NULL,
  `sha256` char(64) DEFAULT NULL,
  `total` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `origen` enum('manual','importacion') NOT NULL DEFAULT 'manual',
  `creado_por` varchar(150) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_folio` (`proceso_id`,`folio`),
  CONSTRAINT `fk_rep_lote_proc` FOREIGN KEY (`proceso_id`) REFERENCES `rep_procesos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
