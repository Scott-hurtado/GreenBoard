-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 11-11-2026 a las 18:40:48
-- Versión del servidor: 11.8.9-MariaDB-log
-- Versión de PHP: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `u881824383_pverde`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `respuestas`
--

CREATE TABLE `respuestas` (
  `id` int(11) NOT NULL,
  `id_encuesta` int(11) NOT NULL,
  `id_pregunta` int(11) NOT NULL,
  `id_opcion` int(11) DEFAULT NULL,
  `respuesta_texto` text DEFAULT NULL,
  `respondido_por` int(11) DEFAULT NULL,
  `fecha_respuesta` datetime DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `respuestas`
--
ALTER TABLE `respuestas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_id_encuesta` (`id_encuesta`),
  ADD KEY `idx_id_pregunta` (`id_pregunta`),
  ADD KEY `idx_id_opcion` (`id_opcion`),
  ADD KEY `idx_respondido_por` (`respondido_por`),
  ADD KEY `idx_fecha_respuesta` (`fecha_respuesta`),
  ADD KEY `idx_respuesta_completa` (`id_encuesta`,`respondido_por`,`fecha_respuesta`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `respuestas`
--
ALTER TABLE `respuestas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `respuestas`
--
ALTER TABLE `respuestas`
  ADD CONSTRAINT `fk_respuesta_encuesta` FOREIGN KEY (`id_encuesta`) REFERENCES `encuestas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_respuesta_opcion` FOREIGN KEY (`id_opcion`) REFERENCES `opciones` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_respuesta_pregunta` FOREIGN KEY (`id_pregunta`) REFERENCES `preguntas` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
