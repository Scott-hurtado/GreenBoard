SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE `coordinador_estatal` (
  `id` int(11) NOT NULL,
  `nombres` varchar(100) DEFAULT NULL,
  `ap_paterno` varchar(100) DEFAULT NULL,
  `ap_materno` varchar(100) DEFAULT NULL,
  `nombre` varchar(255) NOT NULL,
  `telefono` varchar(20) NOT NULL,
  `email` varchar(255) NOT NULL,
  `estado` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `colonia` varchar(255) NOT NULL,
  `calle` varchar(255) NOT NULL,
  `seccion_electoral` varchar(50) NOT NULL,
  `estatus` enum('activo','inactivo') DEFAULT 'activo',
  `fecha_registro` timestamp NULL DEFAULT current_timestamp(),
  `ultima_actualizacion` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `telefono_alterno` varchar(20) DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `capacitaciones` text DEFAULT NULL,
  `disponibilidad` varchar(50) DEFAULT NULL,
  `horario` varchar(50) DEFAULT NULL,
  `dias_disponibles` varchar(50) DEFAULT NULL,
  `movilidad` varchar(50) DEFAULT NULL,
  `fecha_alta` date DEFAULT NULL,
  `fecha_baja` date DEFAULT NULL,
  `es_espejo` tinyint(1) NOT NULL DEFAULT 0,
  `espejo_de` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `coordinador_estatal`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_estatus` (`estatus`),
  ADD KEY `idx_seccion` (`seccion_electoral`),
  ADD KEY `idx_estatal_espejo_de` (`espejo_de`);

ALTER TABLE `coordinador_estatal`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `coordinador_estatal`
  ADD CONSTRAINT `fk_estatal_espejo_de` FOREIGN KEY (`espejo_de`) REFERENCES `coordinador_estatal` (`id`) ON DELETE SET NULL;

COMMIT;
