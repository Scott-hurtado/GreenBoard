SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE `dirigentes_temas` (
  `id` int(11) NOT NULL,
  `estado` varchar(100) NOT NULL,
  `mes` varchar(20) NOT NULL,
  `tema` varchar(255) DEFAULT NULL,
  `fecha_actualizacion` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `dirigentes_temas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_estado_mes` (`estado`,`mes`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `idx_mes` (`mes`);

ALTER TABLE `dirigentes_temas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

COMMIT;
