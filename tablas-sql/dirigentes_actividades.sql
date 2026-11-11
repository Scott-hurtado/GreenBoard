SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE `dirigentes_actividades` (
  `id` int(11) NOT NULL,
  `estado` varchar(100) NOT NULL,
  `id_actividad_banco` int(11) NOT NULL,
  `mes_objetivo` varchar(20) NOT NULL,
  `impacto_estimado` int(11) NOT NULL DEFAULT 5,
  `formulario_vinculado` varchar(500) DEFAULT NULL,
  `comentarios` text DEFAULT NULL,
  `creado_por_id` int(11) DEFAULT NULL,
  `categoria` varchar(50) DEFAULT 'social',
  `fecha_asignacion` datetime NOT NULL DEFAULT current_timestamp(),
  `estatus` enum('pendiente','en_proceso','completado','por_revisar') DEFAULT 'pendiente',
  `reporte_adjunto` varchar(255) DEFAULT NULL,
  `reporte_pdf` varchar(255) DEFAULT NULL,
  `fecha_reporte` datetime DEFAULT NULL,
  `creado_por_nombre` varchar(150) DEFAULT 'Sistema',
  `creado_por_rol` varchar(50) DEFAULT 'sistema',
  `nombre_actividad` varchar(255) DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `dirigentes_actividades`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `idx_mes` (`mes_objetivo`),
  ADD KEY `idx_actividad` (`id_actividad_banco`),
  ADD KEY `idx_fecha` (`fecha_asignacion`);

ALTER TABLE `dirigentes_actividades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

COMMIT;
