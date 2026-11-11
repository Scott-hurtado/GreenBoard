-- Módulo Representantes · tabla `rep_documentos`
-- Requiere que ya existan: rep_asignaciones
SET NAMES utf8mb4;

-- Expediente: un archivo por documento obligatorio
CREATE TABLE IF NOT EXISTS `rep_documentos` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `asignacion_id` int(10) UNSIGNED NOT NULL,
  `tipo_documento` varchar(120) NOT NULL,
  `archivo` varchar(255) NOT NULL,
  `nombre_original` varchar(255) NOT NULL,
  `mime` varchar(60) NOT NULL,
  `tamano` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `sha256` char(64) NOT NULL,
  `estatus` enum('cargado','revision','aprobado','observado') NOT NULL DEFAULT 'cargado',
  `motivo` varchar(255) DEFAULT NULL,
  `subido_por` varchar(150) DEFAULT NULL,
  `subido_en` datetime NOT NULL DEFAULT current_timestamp(),
  `revisado_por` varchar(150) DEFAULT NULL,
  `revisado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_doc` (`asignacion_id`,`tipo_documento`),
  KEY `idx_sha` (`sha256`),
  CONSTRAINT `fk_rep_doc_asig` FOREIGN KEY (`asignacion_id`) REFERENCES `rep_asignaciones` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
