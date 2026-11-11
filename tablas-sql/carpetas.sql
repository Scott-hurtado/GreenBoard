SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE `carpetas` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `roles_acceso` longtext NOT NULL DEFAULT '[]',
  `carpeta_padre_id` int(11) DEFAULT NULL,
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `carpetas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `carpeta_padre_id` (`carpeta_padre_id`);

ALTER TABLE `carpetas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `carpetas`
  ADD CONSTRAINT `carpetas_ibfk_1` FOREIGN KEY (`carpeta_padre_id`) REFERENCES `carpetas` (`id`) ON DELETE CASCADE;

COMMIT;
