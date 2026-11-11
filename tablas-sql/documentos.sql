SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE `documentos` (
  `id` int(11) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `categoria` enum('manual','flyer','reporte','presentacion','formulario','otro') NOT NULL,
  `descripcion` text DEFAULT NULL,
  `tipo_archivo` varchar(50) NOT NULL,
  `nombre_archivo` varchar(255) NOT NULL,
  `ruta_archivo` varchar(500) NOT NULL,
  `tamano_archivo` varchar(50) DEFAULT NULL,
  `descargas` int(11) DEFAULT 0,
  `fecha_subida` timestamp NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `usuario_subida` varchar(100) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `carpeta_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `documentos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_categoria` (`categoria`),
  ADD KEY `idx_fecha` (`fecha_subida`),
  ADD KEY `fk_documento_carpeta` (`carpeta_id`);

ALTER TABLE `documentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `documentos`
  ADD CONSTRAINT `fk_documento_carpeta` FOREIGN KEY (`carpeta_id`) REFERENCES `carpetas` (`id`) ON DELETE SET NULL;

COMMIT;
