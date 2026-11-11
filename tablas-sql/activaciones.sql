SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE `activaciones` (
  `id` int(11) NOT NULL,
  `codigo_referencia` varchar(50) NOT NULL COMMENT 'Ej: ACT-260323-001',
  `titulo` varchar(255) NOT NULL,
  `tipo` enum('territorial','digital','simbolica','estructura') NOT NULL DEFAULT 'territorial',
  `descripcion` longtext DEFAULT NULL,
  `agenda` enum('mediatica','politica','social','tematica') DEFAULT 'social',
  `nivel_impacto` enum('alto','medio','bajo') DEFAULT 'bajo',
  `estatus` enum('programada','en-curso','completada','cancelada') NOT NULL DEFAULT 'programada',
  `estado` varchar(100) DEFAULT NULL,
  `municipio` varchar(150) DEFAULT NULL,
  `region` varchar(120) DEFAULT NULL COMMENT 'Región estructural del estado',
  `zona` varchar(120) DEFAULT NULL COMMENT 'Zona estructural de la región',
  `candidato` varchar(255) DEFAULT NULL COMMENT 'Nombre del candidato/a del evento (NULL = sin candidato)',
  `fecha_actividad` date DEFAULT NULL,
  `coordinador` varchar(255) DEFAULT NULL,
  `contactos_estimados` int(11) DEFAULT 0,
  `asistentes_estimados` int(11) DEFAULT 0,
  `costo` decimal(10,2) DEFAULT 0.00,
  `seccion_electoral` varchar(255) DEFAULT NULL COMMENT 'Ej: Col. Pitic, Sección 042',
  `colonia` varchar(255) DEFAULT NULL,
  `ubicacion` varchar(255) DEFAULT NULL,
  `latitud` decimal(10,6) DEFAULT NULL,
  `longitud` decimal(10,6) DEFAULT NULL,
  `fotos` longtext DEFAULT NULL COMMENT 'JSON array con paths o base64 de fotos',
  `enlaces` longtext DEFAULT NULL COMMENT 'JSON: array de URLs (repositorios de fotos/videos/documentos o redes sociales)',
  `notas` text DEFAULT NULL,
  `id_banco_actividad` int(11) DEFAULT NULL COMMENT 'FK opcional a banco_actividades.id',
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `creado_por_id` int(11) DEFAULT NULL,
  `creado_por_rol` varchar(50) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `activaciones`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo_referencia` (`codigo_referencia`),
  ADD KEY `idx_estatus` (`estatus`),
  ADD KEY `idx_tipo` (`tipo`),
  ADD KEY `idx_agenda` (`agenda`),
  ADD KEY `idx_nivel_impacto` (`nivel_impacto`),
  ADD KEY `idx_fecha_actividad` (`fecha_actividad`),
  ADD KEY `idx_creado_por_id` (`creado_por_id`),
  ADD KEY `idx_id_banco_actividad` (`id_banco_actividad`);

ALTER TABLE `activaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;
