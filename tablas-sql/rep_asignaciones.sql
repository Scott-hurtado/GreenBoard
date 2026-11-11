-- Módulo Representantes · tabla `rep_asignaciones`
-- Requiere que ya existan: rep_personas, rep_procesos
SET NAMES utf8mb4;

-- Persona en un rol y, opcionalmente, en una posición
-- posicion_key: C{casilla_id}:P1|P2|S1|S2  ·  Z{zona_id}:RG{n}  ·  R{region_id}:CON{n}
CREATE TABLE IF NOT EXISTS `rep_asignaciones` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `persona_id` int(10) UNSIGNED NOT NULL,
  `proceso_id` int(10) UNSIGNED NOT NULL,
  `tipo_rep` enum('RG','RC','CONSEJERO') NOT NULL,
  `region_id` int(10) UNSIGNED NOT NULL,
  `zona_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Zona donde cuenta (RN-10); NULL para consejeros',
  `posicion_key` varchar(40) DEFAULT NULL,
  `casilla_id` int(10) UNSIGNED DEFAULT NULL,
  `estatus` enum('RECLUTADO','EN_REVISION','OBSERVADO','COMPLETO','REGISTRADO','ACREDITADO','BAJA') NOT NULL DEFAULT 'RECLUTADO',
  `vigente` tinyint(1) NOT NULL DEFAULT 1,
  `folio` varchar(60) DEFAULT NULL,
  `lote_id` int(10) UNSIGNED DEFAULT NULL,
  `carga_id` int(10) UNSIGNED DEFAULT NULL,
  `f_reclutado` datetime NOT NULL,
  `f_revision` datetime DEFAULT NULL,
  `f_completo` datetime DEFAULT NULL,
  `f_registrado` datetime DEFAULT NULL,
  `f_acreditado` datetime DEFAULT NULL,
  `baja_en` datetime DEFAULT NULL,
  `motivo_baja` varchar(150) DEFAULT NULL,
  `sustituye_a_id` int(10) UNSIGNED DEFAULT NULL,
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `pos_vigente` varchar(40) GENERATED ALWAYS AS (if(`vigente` = 1, `posicion_key`, NULL)) STORED,
  `per_vigente` int(10) UNSIGNED GENERATED ALWAYS AS (if(`vigente` = 1, `persona_id`, NULL)) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_posicion_vigente` (`proceso_id`,`pos_vigente`),
  UNIQUE KEY `ux_persona_vigente` (`proceso_id`,`per_vigente`),
  KEY `idx_zona` (`proceso_id`,`zona_id`),
  KEY `idx_region` (`proceso_id`,`region_id`),
  KEY `idx_estatus` (`proceso_id`,`estatus`),
  KEY `idx_persona` (`persona_id`),
  KEY `idx_casilla` (`casilla_id`),
  CONSTRAINT `fk_rep_asig_persona` FOREIGN KEY (`persona_id`) REFERENCES `rep_personas` (`id`),
  CONSTRAINT `fk_rep_asig_proc` FOREIGN KEY (`proceso_id`) REFERENCES `rep_procesos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
