-- Módulo Representantes · tabla `rep_procesos`
-- Sin dependencias
SET NAMES utf8mb4;

-- Proceso electoral por estado: fechas clave, reglas y umbrales
CREATE TABLE IF NOT EXISTS `rep_procesos` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `estado` varchar(100) NOT NULL,
  `nombre` varchar(150) NOT NULL DEFAULT 'Proceso electoral 2026-2027',
  `fecha_inicio_ruta` date NOT NULL,
  `inicio_registro` date NOT NULL,
  `cierre_registro` date NOT NULL,
  `cierre_sustituciones` date NOT NULL,
  `fecha_jornada` date NOT NULL,
  `umbral_ruta` decimal(5,1) NOT NULL DEFAULT 100.0,
  `umbral_atencion` decimal(5,1) NOT NULL DEFAULT 85.0,
  `rc_propietarios` tinyint(3) UNSIGNED NOT NULL DEFAULT 2,
  `rc_suplentes` tinyint(3) UNSIGNED NOT NULL DEFAULT 2,
  `rg_cada` smallint(5) UNSIGNED NOT NULL DEFAULT 10,
  `rg_por_grupo` tinyint(3) UNSIGNED NOT NULL DEFAULT 2,
  `rg_ambito` enum('municipio','zona','region') NOT NULL DEFAULT 'municipio' COMMENT 'Ámbito de redondeo de RG',
  `docs_rep` text DEFAULT NULL COMMENT 'JSON: documentos obligatorios RG/RC',
  `docs_con` text DEFAULT NULL COMMENT 'JSON: documentos obligatorios consejeros',
  `motivos_obs` text DEFAULT NULL COMMENT 'JSON: catálogo de motivos de observación',
  `meta_fuente` varchar(100) NOT NULL DEFAULT 'Cifras del CEN',
  `ruta_version` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_estado` (`estado`,`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
