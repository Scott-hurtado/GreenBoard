-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 11-11-2026 a las 18:14:54
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
-- Estructura de tabla para la tabla `geo_diagnostico`
--

CREATE TABLE `geo_diagnostico` (
  `id` int(11) NOT NULL,
  `estado` varchar(100) NOT NULL,
  `zona` int(11) NOT NULL,
  `responsable` varchar(150) DEFAULT NULL,
  `cargo` varchar(100) DEFAULT NULL,
  `tel` varchar(20) DEFAULT NULL,
  `mail` varchar(100) DEFAULT NULL,
  `contacto_otro` varchar(100) DEFAULT NULL,
  `mun_totales` int(11) DEFAULT 0,
  `mun_presencia` int(11) DEFAULT 0,
  `afil_registrados` int(11) DEFAULT 0,
  `afil_activos` int(11) DEFAULT 0,
  `promo_registrados` int(11) DEFAULT 0,
  `promo_confirmados` int(11) DEFAULT 0,
  `presupuesto` decimal(15,2) DEFAULT 0.00,
  `ofi_municipal` int(11) DEFAULT 0,
  `ofi_regional` int(11) DEFAULT 0,
  `ofi_estatal` int(11) DEFAULT 0,
  `link_facebook` varchar(255) DEFAULT NULL,
  `seg_facebook` int(11) DEFAULT 0,
  `link_x` varchar(255) DEFAULT NULL,
  `seg_x` int(11) DEFAULT 0,
  `link_instagram` varchar(255) DEFAULT NULL,
  `seg_instagram` int(11) DEFAULT 0,
  `link_otro` varchar(255) DEFAULT NULL,
  `seg_otro` int(11) DEFAULT 0,
  `foda_fortalezas` text DEFAULT NULL,
  `foda_debilidades` text DEFAULT NULL,
  `foda_oportunidades` text DEFAULT NULL,
  `foda_amenazas` text DEFAULT NULL,
  `problema_1_titulo` varchar(255) DEFAULT NULL,
  `problema_1_desc` text DEFAULT NULL,
  `kpi_1_1` varchar(255) DEFAULT NULL,
  `kpi_1_2` varchar(255) DEFAULT NULL,
  `kpi_1_3` varchar(255) DEFAULT NULL,
  `problema_2_titulo` varchar(255) DEFAULT NULL,
  `problema_2_desc` text DEFAULT NULL,
  `kpi_2_1` varchar(255) DEFAULT NULL,
  `kpi_2_2` varchar(255) DEFAULT NULL,
  `kpi_2_3` varchar(255) DEFAULT NULL,
  `problema_3_titulo` varchar(255) DEFAULT NULL,
  `problema_3_desc` text DEFAULT NULL,
  `kpi_3_1` varchar(255) DEFAULT NULL,
  `kpi_3_2` varchar(255) DEFAULT NULL,
  `kpi_3_3` varchar(255) DEFAULT NULL,
  `links_adjuntos` text DEFAULT NULL,
  `fecha_actualizacion` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `geo_diagnostico`
--
ALTER TABLE `geo_diagnostico`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `estado` (`estado`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `geo_diagnostico`
--
ALTER TABLE `geo_diagnostico`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
