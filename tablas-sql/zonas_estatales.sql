-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 11-11-2026 a las 20:44:30
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
-- Estructura de tabla para la tabla `zonas_estatales`
--

CREATE TABLE `zonas_estatales` (
  `id` int(10) UNSIGNED NOT NULL,
  `estructura_id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `clave` varchar(50) DEFAULT NULL,
  `foco` text DEFAULT NULL,
  `colonias` varchar(500) DEFAULT NULL,
  `meta` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `responsable` int(11) DEFAULT NULL,
  `miembros` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `secciones` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT json_array() CHECK (json_valid(`secciones`)),
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `coordinador_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `zonas_estatales`
--
ALTER TABLE `zonas_estatales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_estructura` (`estructura_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `zonas_estatales`
--
ALTER TABLE `zonas_estatales`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=559;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `zonas_estatales`
--
ALTER TABLE `zonas_estatales`
  ADD CONSTRAINT `zonas_estatales_ibfk_1` FOREIGN KEY (`estructura_id`) REFERENCES `regiones_estatales` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
