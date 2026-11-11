<?php
/**
 * reporte_regiones_actualizar.php
 *
 * Endpoint de apoyo para reporte_regiones.php. Dos acciones:
 *
 *  - resolver: dado estado + seccion, intenta detectar municipio (tabla
 *    `secciones`), y región/zona (JSON `secciones` de zonas_estatales).
 *    Es solo de LECTURA, no modifica nada — se usa para mostrar el preview
 *    "Detectamos: Región X · Zona Y · Municipio Z" antes de confirmar.
 *
 *  - guardar: aplica los valores (confirmados o escritos a mano) a un
 *    evento o activación específico.
 */

header('Content-Type: application/json; charset=utf-8');

if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
} else { session_start(); }

$rol = $_SESSION['user_role'] ?? '';
$permitidos = ['admin','administrador','superadmin','super admin','coordinador_estatal'];
if (!in_array($rol, $permitidos)) {
    echo json_encode(['ok'=>false,'msg'=>'No autorizado']); exit();
}

try {
    $dbConfigPath = __DIR__ . '/../../includes/database.php';
    $dbConfig = file_exists($dbConfigPath) ? require $dbConfigPath : [
        'host'=>'localhost','database'=>'u881824383_pverde',
        'username'=>'u881824383_proyverde','password'=>'PProyectoverde8#',
        'charset'=>'utf8mb4',
        'options'=>[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
    ];
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options'] ?? []);
} catch (PDOException $e) {
    echo json_encode(['ok'=>false,'msg'=>'Error de conexión: '.$e->getMessage()]); exit();
}

// ── Mismos resolvers que procesar_php_agenda.php / activaciones ────────────
function resolverMunicipioPorSeccion(PDO $pdo, string $estado, string $seccion): string {
    if ($estado === '' || $seccion === '') return '';
    $st = $pdo->prepare("
        SELECT m.nombre FROM secciones s
        LEFT JOIN municipios m ON m.id = s.municipio_id
        WHERE s.seccion = ? AND s.estado_nombre = ? LIMIT 1
    ");
    $st->execute([$seccion, $estado]);
    return (string)($st->fetchColumn() ?: '');
}

function resolverZonaRegionPorSeccion(PDO $pdo, string $estado, string $seccion): array {
    if ($estado === '' || $seccion === '') return ['zona'=>'', 'region'=>''];
    $rows = $pdo->prepare("
        SELECT z.nombre AS zona_nombre, r.nombre AS region_nombre, z.secciones
        FROM zonas_estatales z
        INNER JOIN regiones_estatales r ON z.estructura_id = r.id
        WHERE r.estado = ?
    ");
    $rows->execute([$estado]);
    foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $lista = json_decode($row['secciones'] ?? '[]', true) ?: [];
        foreach ($lista as $s) {
            if ((string)$s === $seccion) {
                return ['zona'=>$row['zona_nombre'], 'region'=>$row['region_nombre']];
            }
        }
    }
    return ['zona'=>'', 'region'=>''];
}

function resolverRegionPorMunicipio(PDO $pdo, string $estado, string $municipio): string {
    if ($estado === '' || $municipio === '') return '';
    $st = $pdo->prepare("SELECT nombre, municipios FROM regiones_estatales WHERE estado = ?");
    $st->execute([$estado]);
    $muniNorm = mb_strtoupper(trim($municipio), 'UTF-8');
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $lista = json_decode($row['municipios'] ?? '[]', true) ?: [];
        foreach ($lista as $m) {
            if (mb_strtoupper(trim($m), 'UTF-8') === $muniNorm) return $row['nombre'];
        }
    }
    return '';
}

// ── ESTRUCTURA: listar regiones+zonas+municipios de un estado ──────────────
// (para poblar los dropdowns del modal de asignación)
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'estructura') {
    $estado = trim($_GET['estado'] ?? '');
    if ($estado === '') { echo json_encode(['ok'=>false,'msg'=>'Estado requerido']); exit(); }

    $stR = $pdo->prepare("SELECT id, nombre, descripcion AS `desc`, meta, municipios FROM regiones_estatales WHERE estado = ? ORDER BY nombre");
    $stR->execute([$estado]);
    $regiones = $stR->fetchAll(PDO::FETCH_ASSOC);

    foreach ($regiones as &$r) {
        $r['id']         = (int)$r['id'];
        $r['meta']       = (int)$r['meta'];
        $r['municipios'] = json_decode($r['municipios'] ?? '[]', true) ?: [];

        $stZ = $pdo->prepare("SELECT id, nombre, clave, foco, colonias, meta, secciones FROM zonas_estatales WHERE estructura_id = ? ORDER BY nombre");
        $stZ->execute([$r['id']]);
        $zonas = $stZ->fetchAll(PDO::FETCH_ASSOC);
        foreach ($zonas as &$z) {
            $z['id']        = (int)$z['id'];
            $z['meta']      = (int)$z['meta'];
            $z['secciones'] = json_decode($z['secciones'] ?? '[]', true) ?: [];
        }
        unset($z);
        $r['zonas'] = $zonas;
    }
    unset($r);

    // Municipios disponibles del estado (para el picker de nueva región)
    $municipios = [];
    $stE = $pdo->prepare("SELECT id FROM estados WHERE nombre = ? LIMIT 1");
    $stE->execute([$estado]);
    $eid = $stE->fetchColumn();
    if ($eid) {
        $stM = $pdo->prepare("SELECT nombre FROM municipios WHERE estado_id = ? ORDER BY nombre");
        $stM->execute([$eid]);
        $municipios = array_column($stM->fetchAll(PDO::FETCH_ASSOC), 'nombre');
    }

    echo json_encode(['ok'=>true, 'regiones'=>$regiones, 'municipios'=>$municipios], JSON_UNESCAPED_UNICODE);
    exit();
}

// ── CREAR REGIÓN (misma lógica que guardar_region en Estructura) ───────────
if ($action === 'crear_region') {
    $estado     = trim($_POST['estado']  ?? '');
    $nombre     = trim($_POST['nombre']  ?? '');
    $desc       = trim($_POST['desc']    ?? '');
    $meta       = (int)($_POST['meta']   ?? 0);
    $municipios = json_decode($_POST['municipios'] ?? '[]', true) ?: [];

    if ($estado === '' || $nombre === '') {
        echo json_encode(['ok'=>false,'msg'=>'Estado y nombre son requeridos']); exit();
    }
    if (empty($municipios)) {
        echo json_encode(['ok'=>false,'msg'=>'Selecciona al menos un municipio']); exit();
    }

    $stmt = $pdo->prepare("
        INSERT INTO regiones_estatales (estado, nombre, descripcion, meta, municipios, coordinador_id)
        VALUES (?, ?, ?, ?, ?, NULL)
    ");
    $stmt->execute([$estado, $nombre, $desc, $meta, json_encode($municipios, JSON_UNESCAPED_UNICODE)]);
    echo json_encode(['ok'=>true, 'id'=>(int)$pdo->lastInsertId(), 'nombre'=>$nombre]);
    exit();
}

// ── EDITAR REGIÓN ────────────────────────────────────────────────────────
if ($action === 'editar_region') {
    $id         = (int)($_POST['id'] ?? 0);
    $estado     = trim($_POST['estado']  ?? '');
    $nombre     = trim($_POST['nombre']  ?? '');
    $desc       = trim($_POST['desc']    ?? '');
    $meta       = (int)($_POST['meta']   ?? 0);
    $municipios = json_decode($_POST['municipios'] ?? '[]', true) ?: [];

    if (!$id || $estado === '' || $nombre === '') {
        echo json_encode(['ok'=>false,'msg'=>'Datos incompletos']); exit();
    }
    if (empty($municipios)) {
        echo json_encode(['ok'=>false,'msg'=>'Selecciona al menos un municipio']); exit();
    }

    $chk = $pdo->prepare("SELECT id FROM regiones_estatales WHERE id = ? AND estado = ?");
    $chk->execute([$id, $estado]);
    if (!$chk->fetch()) { echo json_encode(['ok'=>false,'msg'=>'Región no encontrada']); exit(); }

    $stmt = $pdo->prepare("
        UPDATE regiones_estatales SET nombre=?, descripcion=?, meta=?, municipios=?
        WHERE id=? AND estado=?
    ");
    $stmt->execute([$nombre, $desc, $meta, json_encode($municipios, JSON_UNESCAPED_UNICODE), $id, $estado]);
    echo json_encode(['ok'=>true, 'id'=>$id, 'nombre'=>$nombre]);
    exit();
}

// ── CREAR ZONA (misma lógica y validación anti-duplicado que guardar_zona) ─
if ($action === 'crear_zona') {
    $estado    = trim($_POST['estado']    ?? '');
    $regionId  = (int)($_POST['region_id'] ?? 0);
    $nombre    = trim($_POST['nombre']    ?? '');
    $clave     = trim($_POST['clave']     ?? '');
    $foco      = trim($_POST['foco']      ?? '');
    $colonias  = trim($_POST['colonias']  ?? '');
    $meta      = (int)($_POST['meta']     ?? 0);
    $secciones = json_decode($_POST['secciones'] ?? '[]', true) ?: [];

    if ($estado === '' || $nombre === '' || !$regionId) {
        echo json_encode(['ok'=>false,'msg'=>'Estado, región y nombre son requeridos']); exit();
    }

    $chk = $pdo->prepare("SELECT id FROM regiones_estatales WHERE id = ? AND estado = ?");
    $chk->execute([$regionId, $estado]);
    if (!$chk->fetch()) { echo json_encode(['ok'=>false,'msg'=>'Región no válida']); exit(); }

    if (!empty($secciones)) {
        $stmtConf = $pdo->prepare("
            SELECT z.nombre, z.secciones, r.nombre AS region_nombre
            FROM zonas_estatales z
            INNER JOIN regiones_estatales r ON r.id = z.estructura_id
            WHERE r.estado = ?
        ");
        $stmtConf->execute([$estado]);
        $conflictos = [];
        foreach ($stmtConf->fetchAll(PDO::FETCH_ASSOC) as $oz) {
            $secsOtra   = json_decode($oz['secciones'] ?? '[]', true) ?: [];
            $duplicadas = array_intersect($secciones, $secsOtra);
            if (!empty($duplicadas)) {
                $conflictos[] = 'Sección(es) ' . implode(', ', $duplicadas) . ' ya asignada(s) en zona "' . $oz['nombre'] . '" (Región: ' . $oz['region_nombre'] . ')';
            }
        }
        if (!empty($conflictos)) {
            echo json_encode(['ok'=>false,'msg'=>implode(' | ', $conflictos)]); exit();
        }
    }

    $stmt = $pdo->prepare("
        INSERT INTO zonas_estatales (estructura_id, nombre, clave, foco, colonias, meta, secciones, coordinador_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, NULL)
    ");
    $stmt->execute([$regionId, $nombre, $clave, $foco, $colonias, $meta, json_encode($secciones, JSON_UNESCAPED_UNICODE)]);
    echo json_encode(['ok'=>true, 'id'=>(int)$pdo->lastInsertId(), 'nombre'=>$nombre]);
    exit();
}

// ── EDITAR ZONA ──────────────────────────────────────────────────────────
if ($action === 'editar_zona') {
    $id        = (int)($_POST['id'] ?? 0);
    $estado    = trim($_POST['estado']    ?? '');
    $regionId  = (int)($_POST['region_id'] ?? 0);
    $nombre    = trim($_POST['nombre']    ?? '');
    $clave     = trim($_POST['clave']     ?? '');
    $foco      = trim($_POST['foco']      ?? '');
    $colonias  = trim($_POST['colonias']  ?? '');
    $meta      = (int)($_POST['meta']     ?? 0);
    $secciones = json_decode($_POST['secciones'] ?? '[]', true) ?: [];

    if (!$id || $estado === '' || $nombre === '' || !$regionId) {
        echo json_encode(['ok'=>false,'msg'=>'Datos incompletos']); exit();
    }

    $chk = $pdo->prepare("SELECT id FROM regiones_estatales WHERE id = ? AND estado = ?");
    $chk->execute([$regionId, $estado]);
    if (!$chk->fetch()) { echo json_encode(['ok'=>false,'msg'=>'Región no válida']); exit(); }

    if (!empty($secciones)) {
        $stmtConf = $pdo->prepare("
            SELECT z.nombre, z.secciones, r.nombre AS region_nombre
            FROM zonas_estatales z
            INNER JOIN regiones_estatales r ON r.id = z.estructura_id
            WHERE r.estado = ? AND z.id != ?
        ");
        $stmtConf->execute([$estado, $id]);
        $conflictos = [];
        foreach ($stmtConf->fetchAll(PDO::FETCH_ASSOC) as $oz) {
            $secsOtra   = json_decode($oz['secciones'] ?? '[]', true) ?: [];
            $duplicadas = array_intersect($secciones, $secsOtra);
            if (!empty($duplicadas)) {
                $conflictos[] = 'Sección(es) ' . implode(', ', $duplicadas) . ' ya asignada(s) en zona "' . $oz['nombre'] . '" (Región: ' . $oz['region_nombre'] . ')';
            }
        }
        if (!empty($conflictos)) {
            echo json_encode(['ok'=>false,'msg'=>implode(' | ', $conflictos)]); exit();
        }
    }

    $stmt = $pdo->prepare("
        UPDATE zonas_estatales SET nombre=?, clave=?, foco=?, colonias=?, meta=?, secciones=?
        WHERE id=? AND estructura_id=?
    ");
    $stmt->execute([$nombre, $clave, $foco, $colonias, $meta, json_encode($secciones, JSON_UNESCAPED_UNICODE), $id, $regionId]);
    echo json_encode(['ok'=>true, 'id'=>$id, 'nombre'=>$nombre]);
    exit();
}

// ── RESOLVER (solo lectura, preview) ────────────────────────────────────
if ($action === 'resolver') {
    $estado  = trim($_GET['estado']  ?? $_POST['estado']  ?? '');
    $seccion = trim($_GET['seccion'] ?? $_POST['seccion'] ?? '');

    if ($estado === '' || $seccion === '') {
        echo json_encode(['ok'=>false,'msg'=>'Estado y sección son requeridos']); exit();
    }

    $municipio = resolverMunicipioPorSeccion($pdo, $estado, $seccion);
    $zr        = resolverZonaRegionPorSeccion($pdo, $estado, $seccion);
    $region    = $zr['region'];
    $zona      = $zr['zona'];

    // Si la sección no dio región directo pero sí dio municipio, respaldo por municipio.
    if ($region === '' && $municipio !== '') {
        $region = resolverRegionPorMunicipio($pdo, $estado, $municipio);
    }

    echo json_encode([
        'ok' => true,
        'encontrado' => ($municipio !== '' || $region !== '' || $zona !== ''),
        'municipio' => $municipio,
        'region'    => $region,
        'zona'      => $zona,
    ]);
    exit();
}

// ── GUARDAR (escribe el registro) ───────────────────────────────────────
if ($action === 'guardar') {
    $tabla     = $_POST['tabla'] ?? '';
    $id        = intval($_POST['id'] ?? 0);
    $seccion   = trim($_POST['seccion']   ?? '');
    $municipio = trim($_POST['municipio'] ?? '');
    $region    = trim($_POST['region']    ?? '');
    $zona      = trim($_POST['zona']      ?? '');

    if (!in_array($tabla, ['eventos', 'activaciones'], true) || !$id) {
        echo json_encode(['ok'=>false,'msg'=>'Parámetros inválidos']); exit();
    }
    if ($seccion === '' || $region === '') {
        echo json_encode(['ok'=>false,'msg'=>'Sección y región son obligatorias']); exit();
    }

    if ($tabla === 'eventos') {
        $stmt = $pdo->prepare("
            UPDATE agenda_eventos
            SET seccion = :seccion, municipio = :municipio, region = :region, zona = :zona
            WHERE id = :id AND activo = 1
        ");
    } else {
        $stmt = $pdo->prepare("
            UPDATE activaciones
            SET seccion_electoral = :seccion, municipio = :municipio, region = :region, zona = :zona
            WHERE id = :id AND activo = 1
        ");
    }

    $stmt->execute([
        ':seccion'   => $seccion,
        ':municipio' => $municipio,
        ':region'    => $region,
        ':zona'      => $zona,
        ':id'        => $id,
    ]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['ok'=>false,'msg'=>'No se encontró el registro (o ya no está activo)']); exit();
    }

    echo json_encode(['ok'=>true, 'seccion'=>$seccion, 'municipio'=>$municipio, 'region'=>$region, 'zona'=>$zona]);
    exit();
}

echo json_encode(['ok'=>false,'msg'=>'Acción no reconocida']);