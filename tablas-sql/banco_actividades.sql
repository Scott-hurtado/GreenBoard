SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE `banco_actividades` (
  `id` int(11) NOT NULL,
  `codigo_referencia` varchar(50) NOT NULL COMMENT 'El ID manual ej: A-001',
  `titulo` varchar(255) NOT NULL,
  `descripcion` longtext DEFAULT NULL,
  `agenda` enum('mediatica','politica','social','tematica') DEFAULT 'social',
  `nivel_impacto` enum('alto','medio','bajo') DEFAULT 'bajo',
  `impacto_personas` int(11) DEFAULT 0,
  `ubicacion` varchar(255) DEFAULT NULL,
  `costo` decimal(10,2) DEFAULT 0.00,
  `fecha_actividad` date DEFAULT NULL,
  `documento_adjunto` varchar(255) DEFAULT NULL,
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `creado_por_id` int(11) DEFAULT NULL,
  `creado_por_rol` varchar(50) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `banco_actividades`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo_referencia` (`codigo_referencia`);

ALTER TABLE `banco_actividades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

COMMIT;
