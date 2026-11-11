-- Módulo Representantes · tabla `rep_estatus_historial`
-- Requiere que ya existan: rep_asignaciones
SET NAMES utf8mb4;

-- Cada transición de estatus (RN-06)
CREATE TABLE IF NOT EXISTS `rep_estatus_historial` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `asignacion_id` int(10) UNSIGNED NOT NULL,
  `estatus_anterior` varchar(20) DEFAULT NULL,
  `estatus_nuevo` varchar(20) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_nombre` varchar(150) DEFAULT NULL,
  `observacion` varchar(255) DEFAULT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_asig` (`asignacion_id`),
  CONSTRAINT `fk_rep_hist_asig` FOREIGN KEY (`asignacion_id`) REFERENCES `rep_asignaciones` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
