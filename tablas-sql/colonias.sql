SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE `colonias` (
  `id` int(11) NOT NULL,
  `nombre` varchar(60) NOT NULL,
  `municipio` int(6) NOT NULL,
  `asentamiento` varchar(40) NOT NULL,
  `codigo_postal` int(5) NOT NULL,
  `latitud` decimal(16,13) DEFAULT NULL,
  `longitud` decimal(16,13) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

ALTER TABLE `colonias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `municipio` (`municipio`);

ALTER TABLE `colonias`
  ADD CONSTRAINT `colonias_ibfk_1` FOREIGN KEY (`municipio`) REFERENCES `municipios` (`id`);

COMMIT;
