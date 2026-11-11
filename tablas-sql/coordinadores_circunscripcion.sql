SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE `coordinadores_circunscripcion` (
  `id` int(11) NOT NULL,
  `nombre_completo` varchar(100) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `telefono_alterno` varchar(20) DEFAULT NULL,
  `correo` varchar(100) NOT NULL,
  `contrasena` varchar(255) NOT NULL,
  `circunscripcion` varchar(40) NOT NULL COMMENT 'Ej: Circunscripción 1',
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp(),
  `ultimo_acceso` timestamp NULL DEFAULT NULL,
  `creado_por` int(11) DEFAULT NULL COMMENT 'FK a administradores.id',
  `es_espejo` tinyint(1) NOT NULL DEFAULT 0,
  `espejo_de` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `coordinadores_circunscripcion`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `correo` (`correo`),
  ADD KEY `idx_correo` (`correo`),
  ADD KEY `idx_activo` (`activo`),
  ADD KEY `idx_circunscripcion` (`circunscripcion`),
  ADD KEY `idx_circunscripcion_espejo_de` (`espejo_de`);

ALTER TABLE `coordinadores_circunscripcion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `coordinadores_circunscripcion`
  ADD CONSTRAINT `fk_circunscripcion_espejo_de` FOREIGN KEY (`espejo_de`) REFERENCES `coordinadores_circunscripcion` (`id`) ON DELETE SET NULL;

COMMIT;
