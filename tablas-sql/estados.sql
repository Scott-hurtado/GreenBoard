SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE `estados` (
  `id` int(10) UNSIGNED NOT NULL,
  `clave` varchar(20) NOT NULL COMMENT 'key interno: sonora, bc, bcs…',
  `nombre` varchar(100) NOT NULL COMMENT 'nombre visible',
  `cve_ine` tinyint(3) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'clave INE del estado'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `estados`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_clave` (`clave`),
  ADD UNIQUE KEY `uk_cve_ine` (`cve_ine`);

ALTER TABLE `estados`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

COMMIT;
