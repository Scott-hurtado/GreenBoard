SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE `geo_acciones` (
  `id` int(11) NOT NULL,
  `estado` varchar(100) NOT NULL,
  `estrategia_id` int(11) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `responsable` varchar(255) DEFAULT NULL,
  `plazo` varchar(255) DEFAULT NULL,
  `recursos` text DEFAULT NULL,
  `indicador` text DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `geo_acciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_accion_estrategia` (`estrategia_id`);

ALTER TABLE `geo_acciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `geo_acciones`
  ADD CONSTRAINT `fk_accion_estrategia` FOREIGN KEY (`estrategia_id`) REFERENCES `geo_estrategias` (`id`) ON DELETE CASCADE;

COMMIT;
