<?php
/**
 * geojson_to_sql.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Sube este archivo a: /public_html/geojson/geojson_to_sql.php
 * Ejecuta desde SSH:      php /public_html/geojson/geojson_to_sql.php
 * O desde navegador:      https://tudominio.com/geojson/geojson_to_sql.php
 *
 * Genera el archivo:  geojson_export.sql
 * Que contiene:
 *   1. CREATE TABLE estados
 *   2. CREATE TABLE municipios
 *   3. CREATE TABLE secciones
 *   4. INSERT de todos los datos extraídos de los GeoJSON
 *
 * BORRA este archivo del servidor después de usarlo.
 * ─────────────────────────────────────────────────────────────────────────────
 */

set_time_limit(0);
ini_set('memory_limit', '512M');

$isCli = (PHP_SAPI === 'cli');

// ── Output helper ─────────────────────────────────────────────────────────────
function out(string $msg, string $tipo = 'info'): void {
    global $isCli;
    $colores = ['ok'=>"\033[32m", 'err'=>"\033[31m", 'warn'=>"\033[33m", 'info'=>"\033[36m", 'head'=>"\033[35m"];
    $reset   = "\033[0m";
    if ($isCli) {
        $color = $colores[$tipo] ?? '';
        echo $color . $msg . $reset . "\n";
    } else {
        $clases = ['ok'=>'ok','err'=>'err','warn'=>'warn','info'=>'info','head'=>'head'];
        $cls = $clases[$tipo] ?? 'info';
        echo "<span class=\"{$cls}\">" . htmlspecialchars($msg) . "</span><br>\n";
        ob_flush(); flush();
    }
}

// ── Catálogo de estados ───────────────────────────────────────────────────────
// __DIR__ apunta a /public_html/geojson/ si subes el script ahí
$geojsonDir = __DIR__;

$catalogo = [
    // ── Batch 1 (originales) ─────────────────────────────────────────────────
    'sonora'           => ['id'=>26, 'label'=>'Sonora',              'mun'=>'municipio.geojson',                  'sec'=>'seccion.geojson'],
    'bc'               => ['id'=>2,  'label'=>'Baja California',     'mun'=>'municipio-bc.geojson',               'sec'=>'seccion-bc.geojson'],
    'bcs'              => ['id'=>3,  'label'=>'Baja California Sur', 'mun'=>'municipio-bcs.geojson',              'sec'=>'seccion-bcs.geojson'],
    'chihuahua'        => ['id'=>8,  'label'=>'Chihuahua',           'mun'=>'municipio-chihuahua.geojson',        'sec'=>'seccion-chihuahua.geojson'],
    'durango'          => ['id'=>10, 'label'=>'Durango',             'mun'=>'municipio-durango.geojson',          'sec'=>'seccion-durango.geojson'],
    'jalisco'          => ['id'=>14, 'label'=>'Jalisco',             'mun'=>'municipio-jalisco.geojson',          'sec'=>'seccion-jalisco.geojson'],
    'nayarit'          => ['id'=>18, 'label'=>'Nayarit',             'mun'=>'municipio-nayarit.geojson',          'sec'=>'seccion-nayarit.geojson'],
    'sinaloa'          => ['id'=>25, 'label'=>'Sinaloa',             'mun'=>'municipio-sinaloa.geojson',          'sec'=>'seccion-sinaloa.geojson'],
    // ── Batch 2 ──────────────────────────────────────────────────────────────
    'aguascalientes'   => ['id'=>1,  'label'=>'Aguascalientes',      'mun'=>'municipio-aguascalientes.geojson',   'sec'=>'seccion-aguascalientes.geojson'],
    'campeche'         => ['id'=>4,  'label'=>'Campeche',            'mun'=>'municipio-campeche.geojson',         'sec'=>'seccion-campeche.geojson'],
    'chiapas'          => ['id'=>7,  'label'=>'Chiapas',             'mun'=>'municipio-chiapas.geojson',          'sec'=>'seccion-chiapas.geojson'],
    'coahuila'         => ['id'=>5,  'label'=>'Coahuila',            'mun'=>'municipio-coahuila.geojson',         'sec'=>'seccion-coahuila.geojson'],
    'colima'           => ['id'=>6,  'label'=>'Colima',              'mun'=>'municipio-colima.geojson',           'sec'=>'seccion-colima.geojson'],
    'guanajuato'       => ['id'=>11, 'label'=>'Guanajuato',          'mun'=>'municipio-guanajuato.geojson',       'sec'=>'seccion-guanajuato.geojson'],
    // ── Batch 3 ──────────────────────────────────────────────────────────────
    'quintana-roo'     => ['id'=>23, 'label'=>'Quintana Roo',        'mun'=>'municipio-quintana-roo.geojson',     'sec'=>'seccion-quintana-roo.geojson'],
    'san-luis-potosi'  => ['id'=>24, 'label'=>'San Luis Potosi',     'mun'=>'municipio-san-luis-potosi.geojson',  'sec'=>'seccion-san-luis-potosi.geojson'],
    'tabasco'          => ['id'=>27, 'label'=>'Tabasco',             'mun'=>'municipio-tabasco.geojson',          'sec'=>'seccion-tabasco.geojson'],
    'tamaulipas'       => ['id'=>28, 'label'=>'Tamaulipas',          'mun'=>'municipio-tamaulipas.geojson',       'sec'=>'seccion-tamaulipas.geojson'],
    'tlaxcala'         => ['id'=>29, 'label'=>'Tlaxcala',            'mun'=>'municipio-tlaxcala.geojson',         'sec'=>'seccion-tlaxcala.geojson'],
    'veracruz'         => ['id'=>30, 'label'=>'Veracruz',            'mun'=>'municipio-veracruz.geojson',         'sec'=>'seccion-veracruz.geojson'],
    'yucatan'          => ['id'=>31, 'label'=>'Yucatan',             'mun'=>'municipio-yucatan.geojson',          'sec'=>'seccion-yucatan.geojson'],
    'zacatecas'        => ['id'=>32, 'label'=>'Zacatecas',           'mun'=>'municipio-zacatecas.geojson',        'sec'=>'seccion-zacatecas.geojson'],
    // ── Batch 4 ──────────────────────────────────────────────────────────────
    'queretaro'        => ['id'=>22, 'label'=>'Queretaro',           'mun'=>'municipio-queretaro.geojson',        'sec'=>'seccion-queretaro.geojson'],
    'puebla'           => ['id'=>21, 'label'=>'Puebla',              'mun'=>'municipio-puebla.geojson',           'sec'=>'seccion-puebla.geojson'],
    'oaxaca'           => ['id'=>20, 'label'=>'Oaxaca',              'mun'=>'municipio-oaxaca.geojson',           'sec'=>'seccion-oaxaca.geojson'],
    'nuevo-leon'       => ['id'=>19, 'label'=>'Nuevo Leon',          'mun'=>'municipio-nuevo-leon.geojson',       'sec'=>'seccion-nuevo-leon.geojson'],
    'morelos'          => ['id'=>17, 'label'=>'Morelos',             'mun'=>'municipio-morelos.geojson',          'sec'=>'seccion-morelos.geojson'],
    'michoacan'        => ['id'=>16, 'label'=>'Michoacan',           'mun'=>'municipio-michoacan.geojson',        'sec'=>'seccion-michoacan.geojson'],
    'estado-de-mexico' => ['id'=>15, 'label'=>'Estado de Mexico',    'mun'=>'municipio-estado-de-mexico.geojson', 'sec'=>'seccion-estado-de-mexico.geojson'],
    'hidalgo'          => ['id'=>13, 'label'=>'Hidalgo',             'mun'=>'municipio-hidalgo.geojson',          'sec'=>'seccion-hidalgo.geojson'],
    'guerrero'         => ['id'=>12, 'label'=>'Guerrero',            'mun'=>'municipio-guerrero.geojson',         'sec'=>'seccion-guerrero.geojson'],
    'ciudad-de-mexico' => ['id'=>9,  'label'=>'Ciudad de Mexico',    'mun'=>'municipio-ciudad-de-mexico.geojson', 'sec'=>'seccion-ciudad-de-mexico.geojson'],
];

// ── Campos GeoJSON a buscar (por prioridad) ───────────────────────────────────
$MUN_NOMBRE = ['nombre_mun', 'NOM_MUN', 'nom_mun', 'NOMBRE', 'nombre', 'municipio'];
$MUN_CVE    = ['cvegeo', 'CVEGEO', 'cve_mun', 'CVE_MUN', 'cve_municipio', 'municipio'];
$SEC_NUM    = ['seccion',  'SECCION',  'CVE_SECC'];
$SEC_MUN    = ['municipio','MUNICIPIO','CVE_MUN'];
$SEC_DF     = ['distrito_f','DISTRITO_F','distrito_federal','df'];
$SEC_DL     = ['distrito_l','DISTRITO_L','distrito_local','dl'];
$SEC_TIPO   = ['tipo','TIPO'];
$SEC_ENT    = ['entidad','ENTIDAD','cve_ent'];

function getProp(array $props, array $keys): string {
    foreach ($keys as $k) {
        if (isset($props[$k]) && $props[$k] !== '' && $props[$k] !== null) {
            return trim((string)$props[$k]);
        }
    }
    return '';
}

function sqlEsc(string $val): string {
    return str_replace(["\\", "'"], ["\\\\", "\\'"], $val);
}

// ── HTML header ───────────────────────────────────────────────────────────────
if (!$isCli) {
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'>
    <title>GeoJSON → SQL</title>
    <style>
      body { font-family: monospace; background: #0d1117; color: #c9d1d9; padding: 24px; line-height: 1.6; }
      .ok   { color: #3fb950; }
      .err  { color: #f85149; }
      .warn { color: #d29922; }
      .info { color: #58a6ff; }
      .head { color: #bc8cff; font-weight: bold; }
      hr    { border-color: #30363d; }
    </style></head><body>\n";
}

// ── Recolección de datos ──────────────────────────────────────────────────────
out("╔══════════════════════════════════════════════════════╗", 'head');
out("║   GeoJSON → SQL  (GreenBoard Electoral)              ║", 'head');
out("╚══════════════════════════════════════════════════════╝", 'head');
out("");

$estados_rows    = [];   // para estados
$municipios_rows = [];   // para municipios  [estado_key => [cve => nombre]]
$secciones_rows  = [];   // para secciones   [array de rows]

$munGlobalId = 1;        // ID autoincrement manual para municipios

foreach ($catalogo as $key => $cfg) {
    out("── Estado: {$cfg['label']} (clave: {$key}) ──────────────────────", 'head');

    // ── INSERT estados ────────────────────────────────────────────────────
    $estados_rows[] = [
        'key'   => $key,
        'label' => $cfg['label'],
        'cve'   => $cfg['id'],
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // MUNICIPIOS
    // ─────────────────────────────────────────────────────────────────────────
    $munPath = $geojsonDir . '/' . $cfg['mun'];
    $munMap  = [];   // cve_mun|nombre → id interno (para relacionar secciones)

    if (!file_exists($munPath)) {
        out("  ✗ No existe: {$cfg['mun']}", 'err');
    } else {
        $sizeMB = round(filesize($munPath) / 1048576, 1);
        out("  ↓ Municipios ({$sizeMB} MB)...", 'info');

        $gj = json_decode(file_get_contents($munPath), true);
        unset($gj['features']); // liberar luego
        $gj = json_decode(file_get_contents($munPath), true);

        if (!$gj || empty($gj['features'])) {
            out("  ✗ JSON inválido o sin features", 'err');
        } else {
            $seen = [];
            foreach ($gj['features'] as $feat) {
                $props  = $feat['properties'] ?? [];
                $nombre = getProp($props, $MUN_NOMBRE);
                $cve    = getProp($props, $MUN_CVE);

                if (!$nombre) continue;

                // Deduplicar por nombre normalizado
                $key_norm = mb_strtolower(trim($nombre));
                if (isset($seen[$key_norm])) continue;
                $seen[$key_norm] = true;

                $municipios_rows[] = [
                    'id'         => $munGlobalId,
                    'estado_key' => $key,
                    'estado_id'  => $cfg['id'],
                    'nombre'     => $nombre,
                    'cve_mun'    => $cve,
                ];

                // índice para lookup desde secciones
                $munMap[$key_norm] = $munGlobalId;
                if ($cve) $munMap['cve_' . $cve] = $munGlobalId;

                $munGlobalId++;
            }
            out("  ✓ " . count($seen) . " municipios extraídos", 'ok');
        }
        unset($gj);
        gc_collect_cycles();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SECCIONES
    // ─────────────────────────────────────────────────────────────────────────
    $secPath = $geojsonDir . '/' . $cfg['sec'];

    if (!file_exists($secPath)) {
        out("  ✗ No existe: {$cfg['sec']}", 'err');
    } else {
        $sizeMB = round(filesize($secPath) / 1048576, 1);
        out("  ↓ Secciones ({$sizeMB} MB)...", 'info');

        $gj = json_decode(file_get_contents($secPath), true);

        if (!$gj || empty($gj['features'])) {
            out("  ✗ JSON inválido o sin features", 'err');
        } else {
            $seen = [];
            foreach ($gj['features'] as $feat) {
                $props = $feat['properties'] ?? [];

                $secNum  = getProp($props, $SEC_NUM);
                if ($secNum === '' || isset($seen[$secNum])) continue;
                $seen[$secNum] = true;

                $munNombre = getProp($props, ['nombre_mun','NOM_MUN','nom_mun','municipio']);
                $munCve    = getProp($props, $SEC_MUN);

                // Buscar municipio_id
                $munId = 0;
                if ($munNombre) {
                    $norm = mb_strtolower(trim($munNombre));
                    $munId = $munMap[$norm] ?? 0;
                }
                if (!$munId && $munCve) {
                    $munId = $munMap['cve_' . $munCve] ?? 0;
                }

                $secciones_rows[] = [
                    'estado_key'       => $key,
                    'estado_id'        => $cfg['id'],
                    'estado_nombre'    => $cfg['label'],
                    'municipio_id'     => $munId,
                    'municipio_nombre' => $munNombre ?: ($munCve ? "Mun.$munCve" : ''),
                    'seccion'          => $secNum,
                    'distrito_federal' => getProp($props, $SEC_DF),
                    'distrito_local'   => getProp($props, $SEC_DL),
                    'tipo'             => getProp($props, $SEC_TIPO), // 1=Urbana 2=Rural
                ];
            }
            out("  ✓ " . count($seen) . " secciones extraídas", 'ok');
        }
        unset($gj);
        gc_collect_cycles();
    }
    out("");
}

// ── Generar SQL ───────────────────────────────────────────────────────────────
out("Generando SQL...", 'info');

$sql  = "-- ═══════════════════════════════════════════════════════════════\n";
$sql .= "-- GreenBoard Electoral — Datos GeoJSON del INE\n";
$sql .= "-- Generado: " . date('Y-m-d H:i:s') . "\n";
$sql .= "-- Estados: " . count($catalogo) . "\n";
$sql .= "-- Municipios: " . count($municipios_rows) . "\n";
$sql .= "-- Secciones: " . count($secciones_rows) . "\n";
$sql .= "-- ═══════════════════════════════════════════════════════════════\n\n";

$sql .= "SET NAMES utf8mb4;\n";
$sql .= "SET foreign_key_checks = 0;\n\n";

// ── CREATE TABLE estados ──────────────────────────────────────────────────
$sql .= "-- ─────────────────────────────────────────────────────────────\n";
$sql .= "-- TABLA: estados\n";
$sql .= "-- ─────────────────────────────────────────────────────────────\n";
$sql .= "DROP TABLE IF EXISTS `estados`;\n";
$sql .= "CREATE TABLE `estados` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `clave`     VARCHAR(20)  NOT NULL COMMENT 'key interno: sonora, bc, bcs…',
  `nombre`    VARCHAR(100) NOT NULL COMMENT 'nombre visible',
  `cve_ine`   TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'clave INE del estado',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_clave` (`clave`),
  UNIQUE KEY `uk_cve_ine` (`cve_ine`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

$sql .= "INSERT INTO `estados` (`clave`, `nombre`, `cve_ine`) VALUES\n";
$eRows = [];
foreach ($estados_rows as $r) {
    $eRows[] = "('" . sqlEsc($r['key']) . "','" . sqlEsc($r['label']) . "'," . (int)$r['cve'] . ")";
}
$sql .= implode(",\n", $eRows) . ";\n\n";

// ── CREATE TABLE municipios ───────────────────────────────────────────────
$sql .= "-- ─────────────────────────────────────────────────────────────\n";
$sql .= "-- TABLA: municipios\n";
$sql .= "-- ─────────────────────────────────────────────────────────────\n";
$sql .= "DROP TABLE IF EXISTS `municipios`;\n";
$sql .= "CREATE TABLE `municipios` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `estado_id`  INT UNSIGNED NOT NULL,
  `nombre`     VARCHAR(150) NOT NULL,
  `cve_mun`    VARCHAR(20)  NOT NULL DEFAULT '' COMMENT 'clave INE del municipio',
  PRIMARY KEY (`id`),
  KEY `idx_estado` (`estado_id`),
  KEY `idx_nombre` (`nombre`(50))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

// Insertar en lotes de 500 para no saturar phpMyAdmin
$mChunks = array_chunk($municipios_rows, 500);
foreach ($mChunks as $chunk) {
    $sql .= "INSERT INTO `municipios` (`id`, `estado_id`, `nombre`, `cve_mun`) VALUES\n";
    $mRows = [];
    foreach ($chunk as $r) {
        $mRows[] = sprintf(
            "(%d, (SELECT id FROM estados WHERE clave='%s'), '%s', '%s')",
            (int)$r['id'],
            sqlEsc($r['estado_key']),
            sqlEsc($r['nombre']),
            sqlEsc($r['cve_mun'])
        );
    }
    $sql .= implode(",\n", $mRows) . ";\n\n";
}

// ── CREATE TABLE secciones ────────────────────────────────────────────────
$sql .= "-- ─────────────────────────────────────────────────────────────\n";
$sql .= "-- TABLA: secciones\n";
$sql .= "-- ─────────────────────────────────────────────────────────────\n";
$sql .= "DROP TABLE IF EXISTS `secciones`;\n";
$sql .= "CREATE TABLE `secciones` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `estado_id`        INT UNSIGNED NOT NULL,
  `estado_nombre`    VARCHAR(100) NOT NULL,
  `municipio_id`     INT UNSIGNED NOT NULL DEFAULT 0,
  `municipio_nombre` VARCHAR(150) NOT NULL DEFAULT '',
  `seccion`          VARCHAR(10)  NOT NULL COMMENT 'número de sección INE',
  `distrito_federal` VARCHAR(10)  NOT NULL DEFAULT '',
  `distrito_local`   VARCHAR(10)  NOT NULL DEFAULT '',
  `tipo`             TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '1=Urbana 2=Rural',
  PRIMARY KEY (`id`),
  KEY `idx_estado`    (`estado_id`),
  KEY `idx_municipio` (`municipio_id`),
  KEY `idx_seccion`   (`seccion`),
  UNIQUE KEY `uk_estado_seccion` (`estado_id`, `seccion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

// Insertar en lotes de 500
$sChunks = array_chunk($secciones_rows, 500);
foreach ($sChunks as $chunk) {
    $sql .= "INSERT INTO `secciones` (`estado_id`,`estado_nombre`,`municipio_id`,`municipio_nombre`,`seccion`,`distrito_federal`,`distrito_local`,`tipo`) VALUES\n";
    $sRows = [];
    foreach ($chunk as $r) {
        $sRows[] = sprintf(
            "((SELECT id FROM estados WHERE clave='%s'),'%s',%d,'%s','%s','%s','%s',%d)",
            sqlEsc($r['estado_key']),
            sqlEsc($r['estado_nombre']),
            (int)$r['municipio_id'],
            sqlEsc($r['municipio_nombre']),
            sqlEsc($r['seccion']),
            sqlEsc($r['distrito_federal']),
            sqlEsc($r['distrito_local']),
            (int)$r['tipo']
        );
    }
    $sql .= implode(",\n", $sRows) . ";\n\n";
}

$sql .= "SET foreign_key_checks = 1;\n\n";
$sql .= "-- ═══════════════════════════════════════════════\n";
$sql .= "-- FIN — Importa este archivo en phpMyAdmin\n";
$sql .= "-- Base de datos: u881824383_pverde\n";
$sql .= "-- ═══════════════════════════════════════════════\n";

// ── Guardar SQL ───────────────────────────────────────────────────────────────
$outFile = $geojsonDir . '/geojson_export.sql';
$bytes   = file_put_contents($outFile, $sql);

out("");
out("╔══════════════════════════════════════════════════════╗", 'head');
out("║  RESUMEN FINAL                                       ║", 'head');
out("╠══════════════════════════════════════════════════════╣", 'head');
out(sprintf("║  %-52s║", "  Estados    : " . count($estados_rows)), 'head');
out(sprintf("║  %-52s║", "  Municipios : " . count($municipios_rows)), 'head');
out(sprintf("║  %-52s║", "  Secciones  : " . count($secciones_rows)), 'head');
out(sprintf("║  %-52s║", "  Archivo    : geojson_export.sql (" . round($bytes/1024) . " KB)"), 'head');
out("╚══════════════════════════════════════════════════════╝", 'head');
out("");
out("✓ Descarga geojson_export.sql e impórtalo en phpMyAdmin", 'ok');
out("  Base de datos: u881824383_pverde", 'info');
out("");
out("⚠ BORRA este script del servidor después de usarlo.", 'warn');

if (!$isCli) {
    echo "<br><a href='geojson_export.sql' style='color:#3fb950;font-weight:bold;font-size:16px;'>
    ⬇ Descargar geojson_export.sql</a></body></html>";
}