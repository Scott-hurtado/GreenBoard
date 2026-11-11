<?php
/**
 * backfill_region_zona.php
 *
 * Corre UNA SOLA VEZ para completar `region` y `zona` en los eventos y
 * activaciones que ya existen y quedaron con esos campos vacíos, usando
 * la misma lógica que ahora aplica procesar.php al crear/editar:
 *   - region: se resuelve por `municipio` contra el JSON `municipios`
 *     de `regiones_estatales`.
 *   - zona: se resuelve por `seccion`/`seccion_electoral` contra el JSON
 *     `secciones` de `zonas_estatales`, dentro de la región ya resuelta.
 *
 * Uso: subirlo a /api/ (o donde prefieras) y abrirlo una vez desde el
 * navegador o por CLI (`php backfill_region_zona.php`). Es seguro
 * correrlo más de una vez: solo toca filas con region/zona vacías.
 *
 * No borra ni sobreescribe nada que ya tenga un valor — solo rellena
 * lo que está vacío.
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

function resolverRegionPorMunicipio(PDO $pdo, string $estado, string $municipio): string {
    static $cache = [];
    $estado = trim($estado);
    $municipio = trim($municipio);
    if ($estado === '' || $municipio === '') return '';
    if (!isset($cache[$estado])) {
        $st = $pdo->prepare("SELECT nombre, municipios FROM regiones_estatales WHERE estado = ?");
        $st->execute([$estado]);
        $cache[$estado] = $st->fetchAll(PDO::FETCH_ASSOC);
    }
    $muniNorm = mb_strtoupper($municipio, 'UTF-8');
    foreach ($cache[$estado] as $row) {
        $lista = json_decode($row['municipios'] ?? '[]', true) ?: [];
        foreach ($lista as $m) {
            if (mb_strtoupper(trim($m), 'UTF-8') === $muniNorm) {
                return $row['nombre'];
            }
        }
    }
    return '';
}

function resolverZonaPorSeccion(PDO $pdo, string $regionNombre, string $seccion): string {
    static $cache = [];
    $regionNombre = trim($regionNombre);
    $seccion = trim($seccion);
    if ($regionNombre === '' || $seccion === '') return '';
    if (!isset($cache[$regionNombre])) {
        $st = $pdo->prepare("
            SELECT z.nombre, z.secciones
            FROM zonas_estatales z
            INNER JOIN regiones_estatales r ON z.estructura_id = r.id
            WHERE r.nombre = ?
        ");
        $st->execute([$regionNombre]);
        $cache[$regionNombre] = $st->fetchAll(PDO::FETCH_ASSOC);
    }
    foreach ($cache[$regionNombre] as $row) {
        $lista = json_decode($row['secciones'] ?? '[]', true) ?: [];
        foreach ($lista as $s) {
            if ((string)$s === $seccion) return $row['nombre'];
        }
    }
    return '';
}

function procesarTabla(PDO $pdo, string $tabla, string $colMunicipio, string $colSeccion, string $colFecha): array {
    $stats = ['revisadas' => 0, 'region_actualizada' => 0, 'zona_actualizada' => 0, 'sin_match' => 0];

    $rows = $pdo->query("
        SELECT id, estado, `$colMunicipio` AS municipio, `$colSeccion` AS seccion, region, zona
        FROM `$tabla`
        WHERE activo = 1
          AND (region IS NULL OR region = '' OR zona IS NULL OR zona = '')
    ")->fetchAll();

    $updRegion = $pdo->prepare("UPDATE `$tabla` SET region = :region WHERE id = :id");
    $updZona   = $pdo->prepare("UPDATE `$tabla` SET zona = :zona WHERE id = :id");
    $updAmbas  = $pdo->prepare("UPDATE `$tabla` SET region = :region, zona = :zona WHERE id = :id");

    foreach ($rows as $row) {
        $stats['revisadas']++;
        $estado    = (string)($row['estado'] ?? '');
        $municipio = (string)($row['municipio'] ?? '');
        $seccion   = (string)($row['seccion'] ?? '');
        $regionActual = trim((string)($row['region'] ?? ''));
        $zonaActual   = trim((string)($row['zona'] ?? ''));

        $nuevaRegion = $regionActual;
        $nuevaZona   = $zonaActual;

        if ($regionActual === '') {
            $nuevaRegion = resolverRegionPorMunicipio($pdo, $estado, $municipio);
        }
        if ($zonaActual === '' && $nuevaRegion !== '') {
            $nuevaZona = resolverZonaPorSeccion($pdo, $nuevaRegion, $seccion);
        }

        $cambioRegion = ($nuevaRegion !== '' && $nuevaRegion !== $regionActual);
        $cambioZona   = ($nuevaZona   !== '' && $nuevaZona   !== $zonaActual);

        if ($cambioRegion && $cambioZona) {
            $updAmbas->execute([':region' => $nuevaRegion, ':zona' => $nuevaZona, ':id' => $row['id']]);
            $stats['region_actualizada']++;
            $stats['zona_actualizada']++;
        } elseif ($cambioRegion) {
            $updRegion->execute([':region' => $nuevaRegion, ':id' => $row['id']]);
            $stats['region_actualizada']++;
        } elseif ($cambioZona) {
            $updZona->execute([':zona' => $nuevaZona, ':id' => $row['id']]);
            $stats['zona_actualizada']++;
        } else {
            $stats['sin_match']++;
        }
    }

    return $stats;
}

echo "=== Backfill region/zona ===\n\n";

echo "-- agenda_eventos --\n";
$r1 = procesarTabla($pdo, 'agenda_eventos', 'municipio', 'seccion', 'fecha');
foreach ($r1 as $k => $v) echo "$k: $v\n";

echo "\n-- activaciones --\n";
$r2 = procesarTabla($pdo, 'activaciones', 'municipio', 'seccion_electoral', 'fecha_actividad');
foreach ($r2 as $k => $v) echo "$k: $v\n";

echo "\nListo. 'sin_match' son filas cuyo municipio no aparece en ninguna region_estatales.municipios\n";
echo "de su estado (p. ej. estado sin regiones capturadas todavia, o el municipio esta mal escrito) —\n";
echo "esas quedan igual que estaban, no se tocan.\n";