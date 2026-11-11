SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE `dirigentes_mision_vision` (
  `id` int(11) NOT NULL,
  `estado` varchar(100) NOT NULL,
  `vision` text DEFAULT NULL,
  `mision` text DEFAULT NULL,
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `dirigentes_mision_vision`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_estado` (`estado`);

ALTER TABLE `dirigentes_mision_vision`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

COMMIT;
