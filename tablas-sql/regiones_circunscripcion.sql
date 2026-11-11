-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 11-11-2026 a las 18:39:43
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
-- Estructura de tabla para la tabla `regiones_circunscripcion`
--

CREATE TABLE `regiones_circunscripcion` (
  `id` int(10) UNSIGNED NOT NULL,
  `circunscripcion` tinyint(1) UNSIGNED NOT NULL COMMENT 'Número de circunscripción: 1 al 5',
  `estado` varchar(100) NOT NULL COMMENT 'Estado dentro de la circunscripción (ej: Sonora)',
  `nombre` varchar(150) NOT NULL COMMENT 'Nombre de la región (ej: Región Norte)',
  `descripcion` text DEFAULT NULL COMMENT 'Descripción general de la región',
  `meta` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Meta de miembros',
  `municipios` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT '[]' COMMENT 'Array JSON con nombres de municipios asignados',
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Regiones definidas por coordinadores de circunscripción';

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `regiones_circunscripcion`
--
ALTER TABLE `regiones_circunscripcion`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_circunscripcion` (`circunscripcion`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `idx_circ_estado` (`circunscripcion`,`estado`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `regiones_circunscripcion`
--
ALTER TABLE `regiones_circunscripcion`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
