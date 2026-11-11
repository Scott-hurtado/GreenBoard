SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE `formseventos` (
  `id` int(11) NOT NULL,
  `evento_id` int(11) NOT NULL,
  `nombres` varchar(150) NOT NULL,
  `apellidos` varchar(150) NOT NULL,
  `lada` varchar(5) NOT NULL DEFAULT '+52',
  `celular` varchar(20) NOT NULL,
  `fecha_registro` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

ALTER TABLE `formseventos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_evento` (`evento_id`);

ALTER TABLE `formseventos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

COMMIT;
