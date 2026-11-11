SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE `agenda_eventos` (
  `id` int(11) NOT NULL,
  `codigo_referencia` varchar(50) NOT NULL COMMENT 'Ej: AGE-260323-001',
  `eje` enum('voto','comunicacion','organizacion','personal') NOT NULL DEFAULT 'voto' COMMENT 'Regla de Oro: voto=40%, comunicacion=30%, organizacion=15%, personal=15%',
  `subtipo` varchar(100) DEFAULT NULL COMMENT 'Subtipo según el eje: Casa por casa, Entrevista de radio, etc.',
  `titulo` varchar(255) NOT NULL,
  `fecha` date NOT NULL,
  `estado` varchar(100) DEFAULT NULL COMMENT 'Estado de la República',
  `municipio` varchar(150) DEFAULT NULL COMMENT 'Municipio',
  `seccion` varchar(10) DEFAULT NULL COMMENT 'Sección electoral',
  `region` varchar(100) DEFAULT NULL,
  `zona` varchar(100) DEFAULT NULL,
  `lugar` varchar(255) DEFAULT NULL,
  `inicio` time DEFAULT NULL COMMENT 'Hora de inicio HH:MM',
  `fin` time DEFAULT NULL COMMENT 'Hora de fin HH:MM',
  `horas` decimal(4,1) DEFAULT 1.0 COMMENT 'Duración en horas (calculada o manual)',
  `responsable` varchar(255) DEFAULT NULL,
  `activacion` varchar(50) DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `estatus` enum('pendiente','completado','cancelado') NOT NULL DEFAULT 'pendiente',
  `realizado` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Marca el evento como Realizado: bloquea edición/eliminación',
  `fecha_realizado` timestamp NULL DEFAULT NULL COMMENT 'Fecha/hora en que se marcó como Realizado',
  `realizado_por_id` int(11) DEFAULT NULL COMMENT 'Usuario que marcó el evento como Realizado',
  `evidencia_fotos` longtext DEFAULT NULL COMMENT 'JSON: array de fotos en base64 (data URLs)',
  `evidencia_pdfs` longtext DEFAULT NULL COMMENT 'JSON: array de {name, dataUrl} de documentos PDF',
  `evidencia_enlaces` longtext DEFAULT NULL COMMENT 'JSON: array de URLs (repositorios de fotos/videos/documentos o redes sociales)',
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `creado_por_id` int(11) DEFAULT NULL,
  `creado_por_rol` varchar(50) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `lat` decimal(10,7) DEFAULT NULL COMMENT 'Latitud del centroide de la sección',
  `lng` decimal(10,7) DEFAULT NULL COMMENT 'Longitud del centroide de la sección'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- NOTA: el archivo original estaba cortado y no traia los indices.
-- Lo siguiente es el minimo estandar para `id`; revisa si necesitas mas indices.
ALTER TABLE `agenda_eventos`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `agenda_eventos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;
