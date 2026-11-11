-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 11-11-2026 a las 18:38:52
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
-- Estructura de tabla para la tabla `metas_semanales`
--

CREATE TABLE `metas_semanales` (
  `id` int(11) NOT NULL,
  `nivel` enum('region','zona') NOT NULL COMMENT 'region = meta puesta por Estatal; zona = meta puesta por Regional',
  `estado` varchar(100) NOT NULL COMMENT 'Estado al que pertenece (para aislar por estado)',
  `entidad_id` int(11) NOT NULL COMMENT 'ID en regiones_estatales (nivel=region) o zonas_estatales (nivel=zona)',
  `entidad_nombre` varchar(150) NOT NULL COMMENT 'Nombre desnormalizado, para cruzar con agenda_eventos.region/zona',
  `region_id` int(11) DEFAULT NULL COMMENT 'Solo para nivel=zona: a qué región pertenece la zona (para scope del Regional)',
  `anio` smallint(6) NOT NULL,
  `mes` tinyint(4) NOT NULL COMMENT '1-12',
  `semana` smallint(6) NOT NULL COMMENT 'Número de semana ISO-8601 (mismo cálculo que usa el Dashboard)',
  `eje` enum('voto','comunicacion','organizacion') NOT NULL,
  `meta` int(11) NOT NULL DEFAULT 0,
  `creado_por_id` int(11) DEFAULT NULL,
  `creado_por_rol` varchar(50) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `metas_semanales`
--
ALTER TABLE `metas_semanales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_meta` (`nivel`,`entidad_id`,`anio`,`mes`,`semana`,`eje`),
  ADD KEY `idx_periodo` (`estado`,`nivel`,`anio`,`mes`,`semana`),
  ADD KEY `idx_region` (`region_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `metas_semanales`
--
ALTER TABLE `metas_semanales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1921;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
