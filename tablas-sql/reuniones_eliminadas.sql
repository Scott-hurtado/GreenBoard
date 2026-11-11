-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 11-11-2026 a las 18:44:53
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
-- Estructura de tabla para la tabla `reuniones_eliminadas`
--

CREATE TABLE `reuniones_eliminadas` (
  `id` int(11) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `fecha` date NOT NULL,
  `asistentes` text DEFAULT NULL,
  `temas` text DEFAULT NULL,
  `descripcion` longtext DEFAULT NULL,
  `acuerdos` text DEFAULT NULL,
  `creado_por` varchar(100) DEFAULT NULL,
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp(),
  `fecha_eliminacion` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `reuniones_eliminadas`
--

INSERT INTO `reuniones_eliminadas` (`id`, `titulo`, `fecha`, `asistentes`, `temas`, `descripcion`, `acuerdos`, `creado_por`, `fecha_creacion`, `fecha_eliminacion`) VALUES
(1, 'Reunión de prueba', '2026-01-05', 'Persona de Prueba', 'Prueba', 'test', '[\"Llegar a tiempo\",\"Participar activamente\"]', 'M. Acosta', '2026-01-05 20:13:20', '2026-01-08 18:42:20'),
(2, 'Avance vizion', '2026-01-03', 'Juan, Omar', 'agenda', '<p><br></p>', '[]', 'M. Acosta', '2026-01-06 01:55:16', '2026-01-08 21:32:35'),
(3, 'sdasdasdasdasdasdasdasdasd', '2026-01-09', 'daniel,scott', 'vamos a ver  el tema tal de la tal  para tal', 'sdsdsdsdsdddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddd\n', '[\"se va ser tal cosa\",\"vamos hacer tal tambein\"]', 'M. Acosta', '2026-01-09 21:27:27', '2026-01-11 05:02:19'),
(5, 'asdljch asklñdjc naskñldjc as alskñdmcn aslñdkjc asdlñkc asdñlkcn dcñkdn cñqskdc sdñkc asdñkcmas clñkasnmd claksdn claksdn calsdknc alsdknc asldmnc asdlkcn asdljcn', '2026-01-13', 'asdasdadasdcasdvsdfvdfvdfvwfvwevewrverveveverfercqercqecqfc asdasdadasdcasdvsdfvdfvdfvwfvwevewrverveveverfercqercqecqfc. asdasdadasdcasdvsdfvdfvdfvwfvwevewrverveveverfercqercqecqfc asdasdadasdcasdvsdfvdfvdfvwfvwevewrverveveverfercqercqecqfc', 'aasdasdasdasdd dasdasdadasdcasdvsdfvdfvdfvwfvwevewrverveveverfercqercqecqfc asdasdadasdcasdvsdfvdfvdfvwfvwevewrverveveverfercqercqecqfc. asdasdadasdcasdvsdfvdfvdfvwfvwevewrverveveverfercqercqecqfc asdasdadasdcasdvsdfvdfvdfvwfvwevewrverveveverfercqercqecqfc', 'sdfasdfasdfasdvasdfasdvadvasdc\n', '[\"sdfadfwqedqwedqwedq\"]', 'M. Acosta', '2026-01-16 19:53:50', '2026-01-17 22:42:46'),
(6, 'sdsd sds ds ds d sd sd s ds ds d sd sd s dsd s ds ds d sd s ', '2026-01-08', 'sdsdsd sd s ds d sd s ds d s d sd s d s ds d s d sd s d sd s d s ds d s d sd s d s d sd s d s d sd s', 'sd s d sd s d sd s d sd s d s ds. djdnfkjdnfdkjfndakjfndakjfnadkjfndakjfnadkjfna', '<p>lkadnfkjdnfkadjnfsakjnfaskjfnadkjfnadkjfnadkjfnakdjfndakjfnakdjfnkajdfnkjadnfkjadnfkjadnfkjadnfkjanfkjadnfkjadnfkjadnfkjadnfkjadnfkjadnfkjadnfkjadnfkjandfkjadnfkjandfkjandkfjnadkjnakjnfkjadfnkjadnfkjadnfkjasnfkjasnfkjadnfkjandfjknadskjfnaskjfnkjasnfkjanfkjasnfkjasnfkjanf kad fkd lkadnfkjdnfkadjnfsakjnfaskjfnadkjfnadkjfnadkjfnakdjfndakjfnakdjfnkajdfnkjadnfkjadnfkjadnfkjadnfkjanfkjadnfkjadnfkjadnfkjadnfkjadnfkjadnfkjadnfkjadnfkjandfkjadnfkjandfkjandkfjnadkjnakjnfkjadfnkjadnfkjadnfkjasnfkjasnfkj</p>', '[\"ksdmf\"]', 'M. Acosta', '2026-01-19 17:33:18', '2026-01-26 23:53:15');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `reuniones_eliminadas`
--
ALTER TABLE `reuniones_eliminadas`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `reuniones_eliminadas`
--
ALTER TABLE `reuniones_eliminadas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
