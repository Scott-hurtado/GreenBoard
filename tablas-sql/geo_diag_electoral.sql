-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 11-11-2026 a las 18:15:31
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
-- Estructura de tabla para la tabla `geo_diag_electoral`
--

CREATE TABLE `geo_diag_electoral` (
  `id` int(11) NOT NULL,
  `estado` varchar(100) NOT NULL,
  `zona` int(11) NOT NULL,
  `anio` int(11) NOT NULL,
  `lista_nominal` bigint(20) DEFAULT 0,
  `participacion` decimal(5,2) DEFAULT 0.00,
  `votos_est` int(11) DEFAULT 0,
  `porc_est` decimal(5,2) DEFAULT 0.00,
  `votos_fed` int(11) DEFAULT 0,
  `porc_fed` decimal(5,2) DEFAULT 0.00,
  `encuesta_fuente` varchar(150) DEFAULT NULL,
  `encuesta_fecha` date DEFAULT NULL,
  `encuesta_intencion` decimal(5,2) DEFAULT 0.00,
  `encuesta_link` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `geo_diag_electoral`
--
ALTER TABLE `geo_diag_electoral`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_estado_anio` (`estado`,`anio`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `geo_diag_electoral`
--
ALTER TABLE `geo_diag_electoral`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=226;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
