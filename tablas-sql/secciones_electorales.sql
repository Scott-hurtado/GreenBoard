-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 11-11-2026 a las 18:45:11
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
-- Estructura de tabla para la tabla `secciones_electorales`
--

CREATE TABLE `secciones_electorales` (
  `id` int(11) NOT NULL,
  `nombre_seccion` varchar(20) NOT NULL COMMENT 'Número de sección INE',
  `estado` varchar(100) NOT NULL DEFAULT '',
  `municipio` varchar(150) NOT NULL DEFAULT '',
  `distrito_federal` varchar(10) NOT NULL DEFAULT '',
  `distrito_local` varchar(10) NOT NULL DEFAULT '',
  `region_asignada` varchar(100) NOT NULL DEFAULT '',
  `zona_asignada` varchar(100) NOT NULL DEFAULT '',
  `lista_nominal` int(11) NOT NULL DEFAULT 0,
  `padron_electoral` int(11) NOT NULL DEFAULT 0 COMMENT 'Padrón electoral INE',
  `lista_hombres` int(11) NOT NULL DEFAULT 0 COMMENT 'Lista nominal hombres INE',
  `lista_mujeres` int(11) NOT NULL DEFAULT 0 COMMENT 'Lista nominal mujeres INE',
  `grupo_edad_18_24` int(11) NOT NULL DEFAULT 0,
  `grupo_edad_25_34` int(11) NOT NULL DEFAULT 0,
  `grupo_edad_35_44` int(11) NOT NULL DEFAULT 0,
  `grupo_edad_45_54` int(11) NOT NULL DEFAULT 0,
  `grupo_edad_55_mas` int(11) NOT NULL DEFAULT 0,
  `meta_contactos` int(11) NOT NULL DEFAULT 0,
  `contactos_conseguidos` int(11) NOT NULL DEFAULT 0,
  `eventos_realizados` int(11) NOT NULL DEFAULT 0,
  `metas_votos` int(11) NOT NULL DEFAULT 0,
  `activaciones_totales` int(11) NOT NULL DEFAULT 0,
  `con_candidato` int(11) NOT NULL DEFAULT 0,
  `sin_candidato` int(11) NOT NULL DEFAULT 0,
  `principales_necesidades` text DEFAULT NULL,
  `enfoque` text DEFAULT NULL,
  `perfil_socioeconomico` text DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_creacion` datetime DEFAULT current_timestamp(),
  `fecha_actualizacion` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `secciones_electorales`
--
ALTER TABLE `secciones_electorales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `idx_municipio` (`municipio`),
  ADD KEY `idx_seccion` (`nombre_seccion`),
  ADD KEY `idx_activo` (`activo`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `secciones_electorales`
--
ALTER TABLE `secciones_electorales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71555;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
