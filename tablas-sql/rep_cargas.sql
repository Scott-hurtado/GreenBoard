-- Módulo Representantes · tabla `rep_cargas`
-- Sin dependencias
SET NAMES utf8mb4;

-- Lotes de carga masiva
CREATE TABLE IF NOT EXISTS `rep_cargas` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `proceso_id` int(10) UNSIGNED NOT NULL,
  `zona_id` int(10) UNSIGNED NOT NULL,
  `filas_total` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `filas_ok` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `filas_error` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `contenido` longtext DEFAULT NULL COMMENT 'Archivo original',
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_nombre` varchar(150) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_proc` (`proceso_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
