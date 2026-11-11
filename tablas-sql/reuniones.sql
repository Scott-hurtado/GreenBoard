-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 11-11-2026 a las 18:44:45
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
-- Estructura de tabla para la tabla `reuniones`
--

CREATE TABLE `reuniones` (
  `id` int(11) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `fecha` date NOT NULL,
  `asistentes` text DEFAULT NULL,
  `temas` text DEFAULT NULL,
  `descripcion` longtext DEFAULT NULL,
  `acuerdos` text DEFAULT NULL,
  `creado_por` varchar(100) DEFAULT NULL,
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `reuniones`
--

INSERT INTO `reuniones` (`id`, `titulo`, `fecha`, `asistentes`, `temas`, `descripcion`, `acuerdos`, `creado_por`, `fecha_creacion`) VALUES
(4, 'Primera Reunión de Planeación', '2026-01-05', 'Luis R. José B, Luisa R.', 'Puntos de partida y Análisis de Investigación', 'Objetivo: Iniciar formalmente la organización rumbo a 2027.\nPuntos clave tratados:\nSe acuerda que la elección se empieza a trabajar desde ahora, no en campaña.\nEl partido necesita reorganizarse y dejar la simulación.\nLa estructura debe ser real, territorial y medible, no solo en papel.\nSe exigirá disciplina, orden y resultados a todos los dirigentes.\nEl PVEM debe recuperar su identidad y liderazgo en el tema ambiental.\nSe establecen metas claras: crecer en votación, ser tercera fuerza y superar históricos.\nHabrá seguimiento semanal, reportes y medición por resultados.\nConclusión:\nEl partido entra en una etapa de organización total, exigencia y enfoque en resultados.\n', '[\"Llenar el Formato de Gestión\",\"Investigación de Mercado\"]', 'M. Acosta', '2026-01-13 05:55:28');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `reuniones`
--
ALTER TABLE `reuniones`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `reuniones`
--
ALTER TABLE `reuniones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
