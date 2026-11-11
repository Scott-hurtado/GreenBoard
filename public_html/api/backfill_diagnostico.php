<?php
/**
 * backfill_diagnostico.php
 *
 * SOLO LECTURA — no modifica nada. Ayuda a entender por qué el backfill
 * dejó filas sin resolver, separándolas en dos categorías:
 *
 *   A) Estado sin ninguna región capturada en regiones_estatales
 *      (no hay nada que resolver todavía, hay que dar de alta las regiones)
 *   B) Estado con regiones, pero el municipio no aparece en ninguna
 *      (probable typo/variante de escritura, o municipio genuinamente
 *       sin asignar a una región)
 *
 * También reporta cuántas filas activas tienen `seccion`/`seccion_electoral`
 * vacía, que es la causa más probable de que zona_actualizada haya salido 0.
 *
 * Uso: igual que el backfill, súbelo a la misma carpeta y ábrelo una vez.
 * Bórralo después de revisarlo.
 */

header('Content-Type: text/plain; charset=utf-8');

if (file_exists(__DIR__ . '/../includes/bootstrap.php')) {
    require_once __DIR__ . '/../includes/bootstrap.php';
}
if (!isset($pdo)) {
    $dbConfigPath = __DIR__ . '/../includes/database.php';
    $cfg = file_exists($dbConfigPath) ? require $dbConfigPath : [
        'host' => 'localhost', 'database' => 'u881824383_pverde',
        'username' => 'u881824383_proyverde', 'password' => 'PProyectoverde8#',
        'charset' => 'utf8mb4',
    ];
    $pdo = new PDO(
        "mysql:host={$cfg['host']};dbname={$cfg['database']};charset={$cfg['charset']}",
        $cfg['username'], $cfg['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
}

// Estados que sí tienen al menos una región capturada, y el set de
// municipios (en mayúsculas, sin espacios extra) que cubren esas regiones.
$estadosConRegiones = [];
foreach ($pdo->query("SELECT estado, municipios FROM regiones_estatales") as $row) {
    $est = trim($row['estado']);
    if (!isset($estadosConRegiones[$est])) $estadosConRegiones[$est] = [];
    $lista = json_decode($row['municipios'] ?? '[]', true) ?: [];
    foreach ($lista as $m) {
        $estadosConRegiones[$est][mb_strtoupper(trim($m), 'UTF-8')] = true;
    }
}

function diagnosticar(PDO $pdo, string $tabla, string $colMunicipio, string $colSeccion, array $estadosConRegiones): void {
    $rows = $pdo->query("
        SELECT id, estado, `$colMunicipio` AS municipio, `$colSeccion` AS seccion, region
        FROM `$tabla`
        WHERE activo = 1 AND (region IS NULL OR region = '')
    ")->fetchAll();

    $catA = []; // estado sin regiones capturadas
    $catB = []; // estado con regiones, municipio no matchea

    foreach ($rows as $r) {
        $est = trim((string)($r['estado'] ?? ''));
        $mun = trim((string)($r['municipio'] ?? ''));
        if ($est === '') { $catA['(estado vacío)'] = ($catA['(estado vacío)'] ?? 0) + 1; continue; }
        if (!isset($estadosConRegiones[$est]) || empty($estadosConRegiones[$est])) {
            $catA[$est] = ($catA[$est] ?? 0) + 1;
        } else {
            $key = $est . ' → ' . ($mun !== '' ? $mun : '(municipio vacío)');
            $catB[$key] = ($catB[$key] ?? 0) + 1;
        }
    }

    echo "Total sin region: " . count($rows) . "\n\n";

    echo "-- Categoría A: estado SIN regiones capturadas (" . array_sum($catA) . " filas) --\n";
    arsort($catA);
    foreach ($catA as $k => $v) echo "  $k: $v\n";

    echo "\n-- Categoría B: estado CON regiones, municipio sin match (" . array_sum($catB) . " filas) --\n";
    arsort($catB);
    $i = 0;
    foreach ($catB as $k => $v) {
        echo "  $k: $v\n";
        if (++$i >= 40) { echo "  ... (" . (count($catB) - 40) . " más)\n"; break; }
    }

    // Cuántas de las filas activas (con o sin region) traen seccion vacía
    $totalActivas = (int)$pdo->query("SELECT COUNT(*) c FROM `$tabla` WHERE activo=1")->fetch()['c'];
    $sinSeccion   = (int)$pdo->query("SELECT COUNT(*) c FROM `$tabla` WHERE activo=1 AND (`$colSeccion` IS NULL OR `$colSeccion` = '')")->fetch()['c'];
    echo "\nFilas activas totales: $totalActivas | sin '$colSeccion' capturada: $sinSeccion\n";
}

echo "=== Diagnóstico backfill region/zona ===\n\n";

echo "########## agenda_eventos ##########\n";
diagnosticar($pdo, 'agenda_eventos', 'municipio', 'seccion', $estadosConRegiones);

echo "\n\n########## activaciones ##########\n";
diagnosticar($pdo, 'activaciones', 'municipio', 'seccion_electoral', $estadosConRegiones);