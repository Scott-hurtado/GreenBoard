-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 11-11-2026 a las 20:44:17
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
-- Estructura de tabla para la tabla `zonas_circunscripcion`
--

CREATE TABLE `zonas_circunscripcion` (
  `id` int(10) UNSIGNED NOT NULL,
  `region_id` int(10) UNSIGNED NOT NULL COMMENT 'ID de regiones_circunscripcion a la que pertenece',
  `nombre` varchar(150) NOT NULL COMMENT 'Nombre de la zona (ej: Zona Norte)',
  `clave` varchar(50) DEFAULT NULL COMMENT 'Clave o código corto (ej: ZN-01)',
  `foco` text DEFAULT NULL COMMENT 'Foco / descripción estratégica de la zona',
  `colonias` varchar(500) DEFAULT NULL COMMENT 'Colonias clave separadas por coma',
  `meta` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Meta de miembros',
  `miembros` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Miembros actuales',
  `secciones` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT '[]' COMMENT 'Array JSON con números de sección electoral asignados',
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Zonas dentro de regiones de circunscripción';

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `zonas_circunscripcion`
--
ALTER TABLE `zonas_circunscripcion`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_region` (`region_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `zonas_circunscripcion`
--
ALTER TABLE `zonas_circunscripcion`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `zonas_circunscripcion`
--
ALTER TABLE `zonas_circunscripcion`
  ADD CONSTRAINT `fk_zona_circ_region` FOREIGN KEY (`region_id`) REFERENCES `regiones_circunscripcion` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
