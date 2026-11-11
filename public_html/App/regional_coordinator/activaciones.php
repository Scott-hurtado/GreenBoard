<?php
// ── Seguridad ──────────────────────────────────────────────
if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
} else { session_start(); }

$rol         = $_SESSION['user_role']   ?? '';
$user_id     = $_SESSION['user_id']     ?? 0;
$user_estado = $_SESSION['user_estado'] ?? '';
$user_region = $_SESSION['user_region'] ?? '';

$permitidos = ['coordinador_regional', 'regional'];
if (!in_array($rol, $permitidos)) { header('Location: /public/index.php'); exit(); }

// ── Conexión y carga de datos ──────────────────────────────
$activaciones      = [];
$municipios_region = [];
$region_nombre     = '—';
$estados_mx_db     = [];
$secciones_geo     = [];

try {
    $dbCfg = __DIR__ . '/../../includes/database.php';
    $cfg = file_exists($dbCfg) ? require $dbCfg : [
        'host'=>'localhost','database'=>'u881824383_pverde',
        'username'=>'u881824383_proyverde','password'=>'PProyectoverde8#',
        'charset'=>'utf8mb4',
        'options'=>[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
    ];
    $dsn = "mysql:host={$cfg['host']};dbname={$cfg['database']};charset={$cfg['charset']}";
    $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], $cfg['options'] ?? []);

    // Nombre de región y municipios asignados por el coordinador estatal
    // Se obtienen desde regiones_estatales donde coordinador_id = usuario actual
    $region_id         = null;
    $st = $pdo->prepare("
        SELECT id, nombre, municipios, tipo_integracion
        FROM regiones_estatales
        WHERE coordinador_id = ? AND estado = ?
        LIMIT 1
    ");
    $st->execute([$user_id, $user_estado]);
    $region_row = $st->fetch();
    $tipo_integracion_region = 'municipios';
    if ($region_row) {
        $region_id         = (int)$region_row['id'];
        $region_nombre     = $region_row['nombre'];
        $municipios_region = json_decode($region_row['municipios'] ?? '[]', true) ?: [];
        $tipo_integracion_region = $region_row['tipo_integracion'] ?: 'municipios';
    }

    // Zonas de la región
    $zonas_region = [];
    $secciones_por_zona = [];
    if ($region_id) {
        $stZ = $pdo->prepare("SELECT id, nombre, secciones FROM zonas_estatales WHERE estructura_id = ? ORDER BY nombre");
        $stZ->execute([$region_id]);
        foreach ($stZ->fetchAll(PDO::FETCH_ASSOC) as $z) {
            $zonas_region[] = ['id' => (int)$z['id'], 'nombre' => $z['nombre']];
            $secciones_por_zona[strval($z['id'])] = json_decode($z['secciones'] ?? '[]', true) ?: [];
        }
    }
    // Fallback: si por alguna razón el usuario tiene user_region (tabla vieja), intentar también
    if (empty($municipios_region) && $user_region) {
        $st = $pdo->prepare("SELECT nombre FROM geo_regiones WHERE id = ? LIMIT 1");
        $st->execute([$user_region]); $r = $st->fetch(); if ($r) $region_nombre = ucfirst($r['nombre']);
        $st = $pdo->prepare("SELECT municipio FROM region_municipios WHERE region_id = ?");
        $st->execute([$user_region]); $municipios_region = $st->fetchAll(PDO::FETCH_COLUMN);
    }

    // Activaciones filtradas por región/estado
    if ($municipios_region) {
        $in = implode(',', array_fill(0, count($municipios_region), '?'));
        $params = array_merge([$user_estado], $municipios_region);
        $stmt = $pdo->prepare("
            SELECT id, codigo_referencia, titulo, tipo, estatus, estado, municipio,
                   DATE_FORMAT(fecha_actividad,'%Y-%m-%d') AS fecha_actividad,
                   coordinador, seccion_electoral, contactos_estimados,
                   asistentes_estimados, costo, notas, latitud, longitud,
                   candidato, colonia, fotos, enlaces, creado_por_rol
            FROM activaciones
            WHERE activo = 1 AND estado = ? AND municipio IN ($in)
            ORDER BY fecha_actividad DESC
        ");
        $stmt->execute($params);
    } else {
        $stmt = $pdo->prepare("
            SELECT id, codigo_referencia, titulo, tipo, estatus, estado, municipio,
                   DATE_FORMAT(fecha_actividad,'%Y-%m-%d') AS fecha_actividad,
                   coordinador, seccion_electoral, contactos_estimados,
                   asistentes_estimados, costo, notas, latitud, longitud,
                   candidato, colonia, fotos, enlaces, creado_por_rol
            FROM activaciones
            WHERE activo = 1 AND estado = ?
            ORDER BY fecha_actividad DESC
        ");
        $stmt->execute([$user_estado]);
    }
    $activaciones = $stmt->fetchAll();

    // Geo: municipios del estado para el formulario (restringidos a los de la región)
    $municipios_para_form = !empty($municipios_region) ? $municipios_region : [];

    // Coordenadas base por estado
    $coords_base = [
        'Aguascalientes'=>['lat'=>21.8818,'lng'=>-102.2916],'Baja California'=>['lat'=>30.8406,'lng'=>-115.2838],
        'Baja California Sur'=>['lat'=>26.0444,'lng'=>-111.1666],'Campeche'=>['lat'=>19.8301,'lng'=>-90.5349],
        'Chiapas'=>['lat'=>16.7569,'lng'=>-93.1292],'Chihuahua'=>['lat'=>28.6320,'lng'=>-106.0691],
        'Ciudad de Mexico'=>['lat'=>19.4326,'lng'=>-99.1332],'Coahuila'=>['lat'=>27.0587,'lng'=>-101.7068],
        'Colima'=>['lat'=>19.2452,'lng'=>-103.7241],'Durango'=>['lat'=>24.0273,'lng'=>-104.6532],
        'Estado de Mexico'=>['lat'=>19.2952,'lng'=>-99.8944],'Guanajuato'=>['lat'=>21.0190,'lng'=>-101.2574],
        'Guerrero'=>['lat'=>17.4392,'lng'=>-100.0000],'Hidalgo'=>['lat'=>20.0911,'lng'=>-98.7624],
        'Jalisco'=>['lat'=>20.6595,'lng'=>-103.3490],'Michoacan'=>['lat'=>19.5665,'lng'=>-101.7068],
        'Morelos'=>['lat'=>18.6813,'lng'=>-99.1013],'Nayarit'=>['lat'=>21.7514,'lng'=>-104.8455],
        'Nuevo Leon'=>['lat'=>25.5922,'lng'=>-99.9962],'Oaxaca'=>['lat'=>17.0732,'lng'=>-96.7266],
        'Puebla'=>['lat'=>19.0414,'lng'=>-98.2063],'Queretaro'=>['lat'=>20.5888,'lng'=>-100.3899],
        'Quintana Roo'=>['lat'=>19.1817,'lng'=>-88.4791],'San Luis Potosi'=>['lat'=>22.1565,'lng'=>-100.9855],
        'Sinaloa'=>['lat'=>25.1721,'lng'=>-107.4795],'Sonora'=>['lat'=>29.2972,'lng'=>-110.3309],
        'Tabasco'=>['lat'=>17.8409,'lng'=>-92.6189],'Tamaulipas'=>['lat'=>24.2669,'lng'=>-98.8363],
        'Tlaxcala'=>['lat'=>19.3182,'lng'=>-98.2375],'Veracruz'=>['lat'=>19.1738,'lng'=>-96.1342],
        'Yucatan'=>['lat'=>20.7099,'lng'=>-89.0943],'Zacatecas'=>['lat'=>22.7709,'lng'=>-102.5832],
    ];

    // Construir ESTADOS_MX para JS (solo el estado de la región): municipios, distritos locales o distritos federales según el tipo
    if (!empty($municipios_para_form)) {
        $in2 = implode(',', array_fill(0, count($municipios_para_form), '?'));
        if ($tipo_integracion_region === 'distrito_local') {
            $stmtGeo = $pdo->prepare("
                SELECT DISTINCT distrito_local AS municipio
                FROM secciones
                WHERE estado_nombre = ? AND distrito_local IN ($in2)
                ORDER BY CAST(distrito_local AS UNSIGNED) ASC
            ");
        } elseif ($tipo_integracion_region === 'distrito_federal') {
            $stmtGeo = $pdo->prepare("
                SELECT DISTINCT distrito_federal AS municipio
                FROM secciones
                WHERE estado_nombre = ? AND distrito_federal IN ($in2)
                ORDER BY CAST(distrito_federal AS UNSIGNED) ASC
            ");
        } else {
            $stmtGeo = $pdo->prepare("
                SELECT m.nombre AS municipio
                FROM municipios m
                JOIN estados e ON e.id = m.estado_id
                WHERE e.nombre = ? AND m.nombre IN ($in2)
                ORDER BY m.nombre ASC
            ");
        }
        $stmtGeo->execute(array_merge([$user_estado], $municipios_para_form));
        $estados_mx_db[$user_estado] = [
            'lat' => $coords_base[$user_estado]['lat'] ?? 23.6345,
            'lng' => $coords_base[$user_estado]['lng'] ?? -102.5528,
            'municipios' => []
        ];
        foreach ($stmtGeo->fetchAll() as $row) {
            $estados_mx_db[$user_estado]['municipios'][$row['municipio']] = true;
        }
    } else {
        $estados_mx_db[$user_estado] = [
            'lat' => $coords_base[$user_estado]['lat'] ?? 23.6345,
            'lng' => $coords_base[$user_estado]['lng'] ?? -102.5528,
            'municipios' => []
        ];
        $stmtGeo2 = $pdo->prepare("SELECT m.nombre FROM municipios m JOIN estados e ON e.id = m.estado_id WHERE e.nombre = ? ORDER BY m.nombre ASC");
        $stmtGeo2->execute([$user_estado]);
        foreach ($stmtGeo2->fetchAll(PDO::FETCH_COLUMN) as $mun) {
            $estados_mx_db[$user_estado]['municipios'][$mun] = true;
        }
    }

    // Secciones geo
    // Construir también: secciones_region (solo las de las zonas de esta región)
    // y seccion_zona_map (seccion → nombre de zona) para mostrar la zona al elegir sección
    $secciones_region   = [];  // array plano de secciones de la región
    $seccion_zona_map   = [];  // [ "1608" => "Z1.1 Humaya-Fovissste (D01)", ... ]
    foreach ($secciones_por_zona as $zona_id => $secs) {
        $zona_nombre = '';
        foreach ($zonas_region as $z) {
            if ((string)$z['id'] === (string)$zona_id) { $zona_nombre = $z['nombre']; break; }
        }
        foreach ($secs as $s) {
            $secciones_region[] = (string)$s;
            if ($zona_nombre) $seccion_zona_map[(string)$s] = $zona_nombre;
        }
    }
    $secciones_region = array_unique($secciones_region);

    try {
        if ($tipo_integracion_region === 'distrito_local') {
            $stmtSec = $pdo->prepare(
                "SELECT sec.estado_nombre, sec.distrito_local AS entidad_nombre, sec.seccion
                 FROM secciones sec
                 WHERE sec.estado_nombre = ? AND sec.distrito_local <> ''
                 ORDER BY CAST(sec.distrito_local AS UNSIGNED) ASC, CAST(sec.seccion AS UNSIGNED) ASC"
            );
        } elseif ($tipo_integracion_region === 'distrito_federal') {
            $stmtSec = $pdo->prepare(
                "SELECT sec.estado_nombre, sec.distrito_federal AS entidad_nombre, sec.seccion
                 FROM secciones sec
                 WHERE sec.estado_nombre = ? AND sec.distrito_federal <> ''
                 ORDER BY CAST(sec.distrito_federal AS UNSIGNED) ASC, CAST(sec.seccion AS UNSIGNED) ASC"
            );
        } else {
            $stmtSec = $pdo->prepare(
                "SELECT sec.estado_nombre, m.nombre AS entidad_nombre, sec.seccion
                 FROM secciones sec
                 LEFT JOIN municipios m ON m.id = sec.municipio_id
                 WHERE sec.estado_nombre = ?
                 ORDER BY m.nombre ASC, CAST(sec.seccion AS UNSIGNED) ASC"
            );
        }
        $stmtSec->execute([$user_estado]);
        foreach ($stmtSec->fetchAll() as $row) {
            $ent = $row['entidad_nombre'] ?? '';
            if ($ent !== '') $secciones_geo[$user_estado][$ent][] = $row['seccion'];
        }
    } catch (Exception $e2) { /* sin secciones */ }

} catch (PDOException $e) { $error_db = $e->getMessage(); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Activaciones - Región <?= htmlspecialchars($region_nombre) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<style>
:root { --primary:#2d7a3e; --primary-dark:#1f5a2d; --dark:#1a1a1a; --light:#f0f7f0; --white:#ffffff; --gray-100:#f8f9fa; --gray-200:#e9ecef; --text-muted:#6c757d; --shadow-sm:0 10px 30px rgba(0,0,0,0.05); }
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'Arial',sans-serif; background:var(--light); color:var(--dark); overflow-x:hidden; }
.container { max-width:100%; margin:0; background:var(--white); min-height:100vh; position:relative; padding-left:330px; padding-right:50px; padding-bottom:40px; transition:padding-left 0.3s ease; }
.design-text { position:fixed; left:290px; top:50%; writing-mode:vertical-rl; font-size:13px; font-weight:500; color:var(--primary); opacity:0.3; letter-spacing:2px; z-index:0; pointer-events:none; }
.page-header { padding:50px 0 30px 0; position:relative; z-index:2; display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:20px; }
.ai-badge { color:var(--primary); font-size:13px; font-weight:800; letter-spacing:4px; margin-bottom:15px; display:inline-block; text-transform:uppercase; background:rgba(45,122,62,0.1); padding:5px 15px; border-radius:20px; }
.main-title { font-size:54px; font-weight:900; line-height:1; margin-bottom:15px; color:var(--dark); }
.description { color:#666; font-size:16px; line-height:1.6; max-width:600px; }
.header-actions { display:flex; gap:12px; align-items:center; flex-wrap:wrap; }
.btn-primary-action { background:var(--primary); color:white; border:none; padding:12px 25px; border-radius:12px; font-weight:700; cursor:pointer; transition:all 0.3s; display:flex; align-items:center; gap:10px; font-size:14px; box-shadow:0 4px 15px rgba(45,122,62,0.3); }
.btn-primary-action:hover { background:var(--primary-dark); transform:translateY(-2px); }
.stats-row { display:grid; grid-template-columns:repeat(3,1fr); gap:20px; margin-bottom:30px; }
.stat-card { background:var(--white); border:1px solid var(--gray-200); border-radius:20px; padding:25px 30px; box-shadow:var(--shadow-sm); display:flex; align-items:center; gap:18px; }
.stat-icon { width:52px; height:52px; border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:22px; flex-shrink:0; }
.stat-icon.verde { background:rgba(45,122,62,.1); color:var(--primary); }
.stat-icon.amarillo { background:#fffbeb; color:#d97706; }
.stat-icon.azul { background:#eff6ff; color:#2196f3; }
.stat-label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:var(--text-muted); margin-bottom:5px; }
.stat-val { font-size:30px; font-weight:900; color:var(--dark); line-height:1; }
.filters-bar { background:var(--white); border:1px solid var(--gray-200); border-radius:16px; padding:20px; margin-bottom:30px; display:flex; gap:15px; align-items:flex-end; box-shadow:var(--shadow-sm); flex-wrap:wrap; }
.filter-group { display:flex; flex-direction:column; gap:8px; flex:1; min-width:160px; }
.form-label { font-size:12px; font-weight:700; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.5px; }
.form-input-styled { width:100%; padding:12px 15px; border-radius:10px; border:2px solid var(--gray-200); background:var(--gray-100); font-size:14px; font-weight:600; color:var(--dark); transition:all 0.3s; outline:none; appearance:none; font-family:Arial,sans-serif; }
.form-input-styled:focus { border-color:var(--primary); background:var(--white); }
.form-input-styled:disabled { opacity:0.6; cursor:not-allowed; }
textarea.form-input-styled { resize:vertical; min-height:90px; font-weight:400; }
.table-section { background:var(--white); border-radius:20px; padding:30px; border:1px solid var(--gray-200); box-shadow:var(--shadow-sm); }
.section-header { margin-bottom:25px; display:flex; justify-content:space-between; align-items:center; }
.section-title { font-size:20px; font-weight:800; color:var(--dark); }
.modern-table { width:100%; border-collapse:separate; border-spacing:0; table-layout:fixed; }
.modern-table th { text-align:left; padding:12px 10px; color:var(--text-muted); font-size:11px; text-transform:uppercase; letter-spacing:0.5px; border-bottom:2px solid var(--gray-200); }
.modern-table td { padding:14px 10px; border-bottom:1px solid var(--gray-200); font-size:13px; color:var(--dark); font-weight:500; vertical-align:middle; transition:background 0.2s; }
/* Anchos de columna para que quepan todas */
.modern-table th:nth-child(1), .modern-table td:nth-child(1) { width:22%; }
.modern-table th:nth-child(2), .modern-table td:nth-child(2) { width:10%; }
.modern-table th:nth-child(3), .modern-table td:nth-child(3) { width:9%; }
.modern-table th:nth-child(4), .modern-table td:nth-child(4) { width:11%; }
.modern-table th:nth-child(5), .modern-table td:nth-child(5) { width:11%; }
.modern-table th:nth-child(6), .modern-table td:nth-child(6) { width:13%; }
.modern-table th:nth-child(7), .modern-table td:nth-child(7) { width:9%; text-align:right; }
.modern-table th:nth-child(8), .modern-table td:nth-child(8) { width:9%; text-align:right; }
.modern-table th:nth-child(9), .modern-table td:nth-child(9) { width:8%; text-align:right; }
.modern-table th:nth-child(10),.modern-table td:nth-child(10){ width:4%; }
.modern-table tr:hover td { background:var(--gray-100); }
.modern-table tr:last-child td { border-bottom:none; }
.badge { padding:5px 12px; border-radius:20px; font-size:11px; font-weight:700; text-transform:uppercase; display:inline-block; }
.badge-programada { background:#fff7ed; color:#d97706; border:1px solid #fde68a; }
.badge-en-curso { background:#eff6ff; color:#1e88e5; border:1px solid #bfdbfe; }
.badge-completada { background:#f0fdf4; color:#16a34a; border:1px solid #bbf7d0; }
.badge-cancelada { background:#fff5f5; color:#e53935; border:1px solid #fecaca; }
.tipo-chip { display:inline-block; padding:3px 10px; border-radius:8px; font-size:11px; font-weight:600; background:var(--gray-200); color:var(--text-muted); }
.stat-cell { font-size:16px; font-weight:800; color:var(--primary); }
.stat-cell.neutral { color:var(--dark); }
.stat-cell-label { font-size:10px; font-weight:700; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.5px; }
.action-cell { text-align:right; position:relative; }
.action-btn { background:none; border:none; cursor:pointer; color:var(--text-muted); font-size:16px; padding:8px 12px; border-radius:50%; transition:all 0.2s; }
.action-btn:hover, .action-btn.active { background:var(--gray-200); color:var(--primary); }
.dropdown-menu { position:absolute; right:20px; top:50px; width:160px; background:var(--white); border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,0.15); border:1px solid var(--gray-200); z-index:100; padding:8px 0; opacity:0; visibility:hidden; transform:translateY(10px); transition:all 0.25s cubic-bezier(0.175,0.885,0.32,1.275); }
.dropdown-menu.show { opacity:1; visibility:visible; transform:translateY(0); }
.dropdown-item { display:flex; align-items:center; gap:10px; padding:10px 20px; width:100%; border:none; background:none; text-align:left; cursor:pointer; font-size:13px; font-weight:600; color:var(--dark); transition:background 0.2s; font-family:Arial,sans-serif; }
.dropdown-item:hover { background:var(--gray-100); color:var(--primary); }
.dropdown-item.delete-item:hover { background:#ffeaea; color:#d32f2f; }
.empty-row td { text-align:center; padding:60px 20px; color:var(--text-muted); font-size:14px; }
.modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(255,255,255,0.9); backdrop-filter:blur(5px); z-index:2000; align-items:center; justify-content:center; opacity:0; transition:opacity 0.3s ease; }
.modal.open { display:flex; opacity:1; }
.modal-content { background:var(--white); border-radius:20px; border:1px solid var(--gray-200); box-shadow:0 25px 50px rgba(0,0,0,0.1); width:90%; max-width:720px; padding:40px; position:relative; transform:translateY(30px); opacity:0; transition:all 0.4s ease-out; max-height:90vh; overflow-y:auto; }
.modal.open .modal-content { transform:translateY(0); opacity:1; }
.close-modal { position:absolute; top:25px; right:25px; font-size:22px; cursor:pointer; color:#ccc; width:40px; height:40px; display:flex; align-items:center; justify-content:center; border-radius:50%; transition:all 0.3s cubic-bezier(0.175,0.885,0.32,1.275); }
.close-modal:hover { color:var(--primary); background:#f0f7f0; transform:rotate(90deg) scale(1.1); }
.estatus-selector { display:grid; grid-template-columns:repeat(4,1fr); gap:8px; }
.est-btn { padding:10px 6px; border-radius:10px; border:2px solid var(--gray-200); font-size:11px; font-weight:700; cursor:pointer; text-align:center; background:var(--gray-100); color:var(--text-muted); transition:0.2s; font-family:Arial,sans-serif; }
.est-btn.sel-programada { background:#fff7ed; border-color:#f59e0b; color:#d97706; }
.est-btn.sel-en-curso { background:#eff6ff; border-color:#2196f3; color:#1e88e5; }
.est-btn.sel-completada { background:#f0fdf4; border-color:#2d7a3e; color:#16a34a; }
.est-btn.sel-cancelada { background:#fff5f5; border-color:#e53935; color:#e53935; }
#mapa { height:220px; border-radius:12px; border:2px solid var(--gray-200); }
#det-mapa { height:200px; border-radius:12px; border:2px solid var(--gray-200); margin-top:10px; }
.map-coords { display:flex; gap:10px; margin-top:8px; }
.map-note { font-size:12px; color:var(--text-muted); margin-bottom:6px; }
.sec-act-wrap{position:relative;width:100%;}
.sec-act-input{cursor:pointer!important;padding-right:32px!important;background:var(--white)!important;}
.sec-act-arrow{position:absolute;right:12px;top:50%;transform:translateY(-50%);pointer-events:none;color:var(--text-muted);font-size:11px;transition:transform 0.2s;}
.sec-act-wrap.open .sec-act-arrow{transform:translateY(-50%) rotate(180deg);}
.sec-act-list{display:none;position:absolute;top:calc(100% + 4px);left:0;right:0;background:var(--white);border:2px solid var(--primary);border-radius:10px;z-index:9999;max-height:220px;overflow:hidden;box-shadow:0 8px 24px rgba(0,0,0,0.12);flex-direction:column;}
.sec-act-wrap.open .sec-act-list{display:flex;}
.sec-act-search{padding:10px 12px;border:none;border-bottom:1px solid var(--gray-200);font-size:13px;outline:none;font-family:inherit;color:var(--dark);background:#f8f9fa;flex-shrink:0;}
.sec-act-search:focus{background:var(--white);}
.sec-act-options{overflow-y:auto;flex:1;}
.sec-act-opt{padding:9px 14px;font-size:13px;font-weight:600;cursor:pointer;color:var(--dark);transition:background 0.12s;font-family:inherit;border-bottom:1px solid var(--gray-200);}
.sec-act-opt:last-child{border-bottom:none;}
.sec-act-opt:hover,.sec-act-opt.focused{background:rgba(45,122,62,0.08);color:var(--primary);}
.sec-act-opt.selected{background:rgba(45,122,62,0.08);color:var(--primary);font-weight:700;}
.sec-act-opt em{font-style:normal;font-weight:700;color:var(--primary);}
.sec-act-empty,.sec-act-loading{padding:14px;text-align:center;font-size:13px;color:var(--text-muted);font-style:italic;}
.modal-footer { display:flex; justify-content:space-between; align-items:center; margin-top:30px; padding-top:25px; border-top:1px solid var(--gray-200); }
.btn-save { background:var(--primary); color:white; border:none; padding:12px 28px; border-radius:12px; font-weight:700; cursor:pointer; transition:all 0.3s; font-size:14px; box-shadow:0 4px 15px rgba(45,122,62,0.3); font-family:Arial,sans-serif; }
.btn-save:hover { background:var(--primary-dark); transform:translateY(-1px); }
.btn-cancel { background:var(--white); color:var(--text-muted); border:1px solid var(--gray-200); padding:12px 22px; border-radius:12px; font-weight:600; cursor:pointer; transition:0.3s; font-size:13px; font-family:Arial,sans-serif; }
.btn-cancel:hover { border-color:var(--dark); color:var(--dark); }
.btn-danger { background:#ffeaea; color:#d32f2f; border:1px solid #fecaca; padding:12px 20px; border-radius:12px; font-weight:700; cursor:pointer; transition:0.3s; font-size:13px; font-family:Arial,sans-serif; }
.btn-danger:hover { background:#fee2e2; }
.detail-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:20px; margin-top:20px; padding-top:20px; border-top:1px solid var(--gray-200); }
.detail-item strong { display:block; font-size:11px; color:var(--text-muted); text-transform:uppercase; margin-bottom:5px; letter-spacing:0.5px; }
.detail-item span { font-size:15px; color:var(--dark); font-weight:600; }
.detail-item span.big { font-size:24px; font-weight:900; color:var(--primary); }
.animate-up { animation:fadeInUp 0.6s ease forwards; opacity:0; }
@keyframes fadeInUp { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
.animate-delay-1 { animation-delay:0.1s; }
.animate-delay-2 { animation-delay:0.2s; }
.btn-save.loading { opacity:0.7; pointer-events:none; }
.fotos-upload-area { border:2px dashed var(--gray-200); border-radius:14px; padding:28px 20px; text-align:center; cursor:pointer; transition:all 0.3s; background:var(--gray-100); position:relative; }
.fotos-upload-area:hover, .fotos-upload-area.drag-over { border-color:var(--primary); background:rgba(45,122,62,0.04); }
.fotos-upload-area .upload-icon { font-size:28px; color:var(--primary); opacity:0.6; margin-bottom:8px; }
.fotos-upload-area p { font-size:13px; color:var(--text-muted); font-weight:600; margin:0; }
.fotos-upload-area p span { color:var(--primary); text-decoration:underline; }
#foto-input { display:none; }
.fotos-preview-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(90px,1fr)); gap:10px; margin-top:12px; }
.foto-thumb { position:relative; border-radius:10px; overflow:hidden; aspect-ratio:1; background:var(--gray-200); box-shadow:0 2px 8px rgba(0,0,0,0.08); }
.foto-thumb img { width:100%; height:100%; object-fit:cover; display:block; transition:transform 0.2s; }
.foto-thumb:hover img { transform:scale(1.04); }
.foto-thumb .remove-foto { position:absolute; top:4px; right:4px; background:rgba(0,0,0,0.6); color:#fff; border:none; border-radius:50%; width:22px; height:22px; cursor:pointer; font-size:11px; display:flex; align-items:center; justify-content:center; transition:background 0.2s; opacity:0; }
.foto-thumb:hover .remove-foto { opacity:1; }
.foto-thumb .remove-foto:hover { background:rgba(211,47,47,0.9); }
.foto-counter { font-size:11px; font-weight:700; color:var(--text-muted); margin-top:8px; text-align:right; letter-spacing:0.5px; }
.det-galeria { margin-top:20px; }
.det-galeria-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(80px,1fr)); gap:8px; margin-top:10px; }
.det-galeria-grid img { width:100%; aspect-ratio:1; object-fit:cover; border-radius:8px; cursor:pointer; transition:transform 0.2s,box-shadow 0.2s; }
.det-galeria-grid img:hover { transform:scale(1.05); box-shadow:0 4px 12px rgba(0,0,0,0.15); }
#lightbox { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.88); z-index:9999; align-items:center; justify-content:center; flex-direction:column; }
#lightbox.open { display:flex; }
#lightbox img { max-width:90vw; max-height:80vh; border-radius:12px; box-shadow:0 20px 60px rgba(0,0,0,0.5); object-fit:contain; }
#lightbox .lb-close { position:absolute; top:20px; right:25px; color:#fff; font-size:28px; cursor:pointer; opacity:0.8; transition:opacity 0.2s; background:none; border:none; }
#lightbox .lb-close:hover { opacity:1; }
#lightbox .lb-nav { display:flex; gap:20px; margin-top:16px; }
#lightbox .lb-btn { background:rgba(255,255,255,0.15); color:#fff; border:none; border-radius:8px; padding:8px 20px; cursor:pointer; font-size:14px; font-weight:700; transition:background 0.2s; }
#lightbox .lb-btn:hover { background:rgba(255,255,255,0.3); }
#lightbox .lb-counter { color:rgba(255,255,255,0.6); font-size:13px; margin-top:8px; }
@media(max-width:1024px){ .container{padding-left:20px;padding-right:20px;} .stats-row{grid-template-columns:1fr;} .filters-bar{flex-direction:column;} .design-text{display:none;} }
</style>
</head>
<body>
<?php if (file_exists(__DIR__ . '/../../includes/components/sidebar_regional.php'))
    include __DIR__ . '/../../includes/components/sidebar_regional.php'; ?>

<div class="design-text">ACTIVACIONES</div>
<div class="container">

    <div class="page-header animate-up">
        <div>
            <div class="ai-badge">REGIÓN <?= htmlspecialchars(strtoupper($region_nombre)) ?></div>
            <h1 class="main-title">Activaciones.</h1>
            <p class="description">Caravanas, rescates, brigadas y más en <?= htmlspecialchars($user_estado) ?>. Registra y monitorea cada activación.</p>
        </div>
        <div class="header-actions">
            <button class="btn-primary-action" onclick="abrirModal()">
                <i class="fas fa-plus-circle"></i> Nueva Activación
            </button>
        </div>
    </div>

    <?php if (isset($error_db)): ?>
    <div style="background:#f8d7da;color:#721c24;padding:15px;border-radius:10px;margin-bottom:20px;">
        <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error_db) ?>
    </div>
    <?php endif; ?>

    <div class="stats-row animate-up animate-delay-1">
        <div class="stat-card">
            <div class="stat-icon verde"><i class="fas fa-users"></i></div>
            <div><div class="stat-label">Contactos Totales</div><div class="stat-val" id="res-contactos">0</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon amarillo"><i class="fas fa-dollar-sign"></i></div>
            <div><div class="stat-label">Inversión Total</div><div class="stat-val" id="res-inversion">$0</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon azul"><i class="fas fa-bolt"></i></div>
            <div><div class="stat-label">Costo / Contacto</div><div class="stat-val" id="res-cpc">$0</div></div>
        </div>
    </div>

        <!-- ── FILTRO MES / SEMANA (igual que dashboard) ── -->
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:16px;flex-wrap:wrap;">
            <div style="display:flex;align-items:center;gap:8px;">
                <span style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);">Mes:</span>
                <select id="filtro-mes" class="form-input-styled" style="min-width:130px;font-size:13px;padding:8px 12px;" onchange="mesChange()">
                    <option value="0">Todos los meses</option>
                    <option value="1">Enero</option><option value="2">Febrero</option>
                    <option value="3">Marzo</option><option value="4">Abril</option>
                    <option value="5">Mayo</option><option value="6">Junio</option>
                    <option value="7">Julio</option><option value="8">Agosto</option>
                    <option value="9">Septiembre</option><option value="10">Octubre</option>
                    <option value="11">Noviembre</option><option value="12">Diciembre</option>
                </select>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
                <span style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);">Semana:</span>
                <select id="filtro-semana" class="form-input-styled" style="min-width:210px;font-size:13px;padding:8px 12px;" onchange="renderTabla()" disabled>
                    <option value="">Todas las semanas</option>
                </select>
            </div>
            <button onclick="limpiarMesSemana()" id="btn-limpiar-fecha"
                style="display:none;height:36px;padding:0 14px;border-radius:8px;border:1.5px solid var(--gray-200);background:var(--white);font-size:12px;font-weight:600;color:var(--text-muted);cursor:pointer;transition:all 0.15s;"
                onmouseover="this.style.borderColor='var(--primary)';this.style.color='var(--primary)'"
                onmouseout="this.style.borderColor='var(--gray-200)';this.style.color='var(--text-muted)'">
                × Limpiar fecha
            </button>
        </div>
    <div class="filters-bar animate-up animate-delay-1">
        <div class="filter-group">
            <label class="form-label">Estatus</label>
            <select id="filtro-estatus" class="form-input-styled" onchange="renderTabla()">
                <option value="">Todos</option>
                <option value="programada">Programada</option>
                <option value="en-curso">En curso</option>
                <option value="completada">Completada</option>
                <option value="cancelada">Cancelada</option>
            </select>
        </div>
        <div class="filter-group">
            <label class="form-label">Tipo</label>
            <select id="filtro-tipo" class="form-input-styled" onchange="renderTabla()">
                <option value="">Todos los tipos</option>
                <option value="territorial">Territorial</option>
                <option value="digital">Digital</option>
                <option value="simbolica">Simbólica / Impacto</option>
                <option value="estructura">Estructura</option>
            </select>
        </div>
        <div class="filter-group">
            <label class="form-label">Municipio</label>
            <select id="filtro-municipio" class="form-input-styled" onchange="renderTabla()">
                <option value="">Todos los municipios</option>
            </select>
        </div>
        <div class="filter-group">
            <label class="form-label">Zona</label>
            <select id="filtro-zona" class="form-input-styled" onchange="filtroZonaCambio()">
                <option value="">Todas las zonas</option>
                <?php foreach($zonas_region as $z): ?>
                <option value="<?= htmlspecialchars($z['id']) ?>"><?= htmlspecialchars($z['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label class="form-label">Sección</label>
            <select id="filtro-seccion" class="form-input-styled" onchange="renderTabla()" disabled>
                <option value="">Todas las secciones</option>
            </select>
        </div>
    </div>

    <div class="table-section animate-up animate-delay-2">
        <div class="section-header">
            <div class="section-title">Registro de Activaciones</div>
            <div id="count-label" style="font-size:13px;color:var(--text-muted);font-weight:600;"></div>
        </div>
        <table class="modern-table">
            <thead>
                <tr>
                    <th>Activación</th><th>Tipo</th><th>Fecha</th><th>Estatus</th><th>Municipio</th><th>Registró</th>
                    <th style="text-align:right;">Contactos</th>
                    <th style="text-align:right;">Asistentes</th>
                    <th style="text-align:right;">Costo</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="tabla-body"></tbody>
        </table>
    </div>

</div>

<!-- ── MODAL FORM ─────────────────────────────────────────── -->
<div class="modal" id="modal-form">
    <div class="modal-content">
        <div class="close-modal" onclick="cerrarModal('modal-form')"><i class="fas fa-times"></i></div>
        <h2 id="form-title" style="font-size:24px;font-weight:800;margin-bottom:5px;">Nueva Activación</h2>
        <p style="font-size:14px;color:#999;margin-bottom:30px;">Registra o edita una activación en tu región.</p>

        <div class="form-group" style="margin-bottom:20px;">
            <label class="form-label">Título *</label>
            <input type="text" id="f-titulo" class="form-input-styled" placeholder="Ej. Caravana Verde Col. Pitic">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
            <div class="form-group">
                <label class="form-label">Tipo *</label>
                <select id="f-tipo" class="form-input-styled">
                    <option value="">Selecciona...</option>
                    <option value="territorial">Activación Territorial (presencial)</option>
                    <option value="digital">Activación Digital</option>
                    <option value="simbolica">Activación Simbólica / Impacto</option>
                    <option value="estructura">Activación de Estructura</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Fecha *</label>
                <input type="date" id="f-fecha" class="form-input-styled">
            </div>
        </div>

        <!-- CON / SIN CANDIDATO -->
        <div class="form-group" style="margin-bottom:20px;">
            <label class="form-label">Evento con candidato</label>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:6px;">
                <button type="button" id="btn-con-candidato" onclick="seleccionarCandidato(true)" class="est-btn" style="padding:12px 6px;font-size:13px;">🙋 Con Candidato</button>
                <button type="button" id="btn-sin-candidato" onclick="seleccionarCandidato(false)" class="est-btn" style="padding:12px 6px;font-size:13px;">🚫 Sin Candidato</button>
            </div>
            <div id="candidato-wrap" style="display:none;margin-top:12px;">
                <input type="text" id="f-candidato" class="form-input-styled" placeholder="Nombre del candidato o candidata">
            </div>
        </div>

        <!-- Estado bloqueado + Municipio de la región -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;background:var(--gray-100);padding:15px;border-radius:10px;border:1px solid var(--gray-200);">
            <div class="form-group">
                <label class="form-label">Estado</label>
                <div style="padding:13px 16px;border:2px solid #eee;border-radius:12px;font-size:14px;font-weight:600;color:#555;background:#f8f9fa;display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-lock" style="color:var(--primary);font-size:13px;"></i> <?= htmlspecialchars($user_estado) ?>
                </div>
                <span style="font-size:11px;color:var(--text-muted);margin-top:3px;">Solo puedes registrar activaciones en tu estado.</span>
            </div>
            <div class="form-group">
                <label class="form-label" id="f-municipio-label">Municipio *</label>
                <select id="f-municipio" class="form-input-styled" onchange="centrarMapa()">
                    <option value="">— Selecciona un municipio —</option>
                </select>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
            <div class="form-group">
                <label class="form-label">Coordinador</label>
                <input type="text" id="f-coordinador" class="form-input-styled" placeholder="Nombre del coordinador">
            </div>
            <div class="form-group">
                <label class="form-label">Colonia / Zona</label>
                <input type="text" id="f-colonia" class="form-input-styled" placeholder="Ej. Col. Pitic, Centro">
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
            <div class="form-group">
                <label class="form-label">Sección Electoral <span id="f-sec-act-contador" style="font-weight:400;color:var(--text-muted);margin-left:6px;font-size:10px;"></span></label>
                <div class="sec-act-wrap" id="sec-act-wrap">
                    <input type="hidden" id="f-seccion" value="">
                    <input type="text" class="sec-act-input form-input-styled" id="sec-act-display"
                           placeholder="— Selecciona municipio primero —"
                           readonly onclick="secActToggle()" autocomplete="off">
                    <i class="fas fa-chevron-down sec-act-arrow" id="sec-act-arrow"></i>
                    <div class="sec-act-list" id="sec-act-list">
                        <input type="text" class="sec-act-search" id="sec-act-search"
                               placeholder="🔍 Buscar sección…"
                               oninput="secActFiltrar(this.value)"
                               onkeydown="secActNavegar(event)">
                        <div class="sec-act-options" id="sec-act-options">
                            <div class="sec-act-empty">Selecciona municipio primero</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Zona <small style="font-weight:400;color:var(--text-muted);font-size:10px;">(de la sección)</small></label>
                <input type="text" id="f-zona-act" class="form-input-styled" readonly
                       placeholder="— se detecta al elegir sección —"
                       style="background:#f0f0f0;cursor:not-allowed;opacity:0.85;">
            </div>
        </div>

        <div class="form-group" style="margin-bottom:20px;">
            <label class="form-label">Estatus *</label>
            <div class="estatus-selector" style="margin-top:5px;">
                <button class="est-btn" data-est="programada" onclick="seleccionarEstatus('programada')" type="button">📅 Programada</button>
                <button class="est-btn" data-est="en-curso"   onclick="seleccionarEstatus('en-curso')"   type="button">▶️ En curso</button>
                <button class="est-btn" data-est="completada" onclick="seleccionarEstatus('completada')" type="button">✅ Completada</button>
                <button class="est-btn" data-est="cancelada"  onclick="seleccionarEstatus('cancelada')"  type="button">❌ Cancelada</button>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;margin-bottom:20px;">
            <div class="form-group"><label class="form-label">Contactos Nuevos Est.</label><input type="number" id="f-contactos" class="form-input-styled" min="0" placeholder="0"></div>
            <div class="form-group"><label class="form-label">Asistentes Est.</label><input type="number" id="f-asistentes" class="form-input-styled" min="0" placeholder="0"></div>
            <div class="form-group"><label class="form-label">Costo Estimado ($)</label><input type="number" id="f-costo" class="form-input-styled" min="0" placeholder="0"></div>
        </div>
        <div class="form-group" style="margin-bottom:20px;">
            <label class="form-label">Notas Adicionales</label>
            <textarea id="f-notas" class="form-input-styled" placeholder="Materiales, logística, objetivos específicos..."></textarea>
        </div>

        <!-- MAPA -->
        <div class="form-group" style="margin-bottom:5px;">
            <label class="form-label">Ubicación / Georeferencia</label>
            <div style="display:flex;gap:8px;margin-bottom:8px;">
                <div style="position:relative;flex:1;">
                    <i class="fas fa-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#aaa;font-size:13px;pointer-events:none;"></i>
                    <input type="text" id="map-search-input" placeholder="Buscar dirección o lugar..."
                        style="width:100%;padding:10px 12px 10px 34px;border:2px solid #eee;border-radius:10px;font-size:13px;outline:none;transition:border 0.2s;"
                        onfocus="this.style.borderColor='var(--primary)'" onblur="this.style.borderColor='#eee'"
                        onkeydown="if(event.key==='Enter'){event.preventDefault();buscarEnMapa();}">
                </div>
                <button type="button" onclick="buscarEnMapa()"
                    style="padding:10px 16px;background:var(--primary);color:white;border:none;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;white-space:nowrap;transition:background 0.2s;"
                    onmouseover="this.style.background='var(--primary-dark)'" onmouseout="this.style.background='var(--primary)'">
                    <i class="fas fa-map-marker-alt"></i> Buscar
                </button>
            </div>
            <div id="map-search-results" style="display:none;border:1px solid #eee;border-radius:10px;background:white;max-height:180px;overflow-y:auto;margin-bottom:8px;box-shadow:0 4px 12px rgba(0,0,0,0.08);"></div>
            <p class="map-note"><i class="fas fa-hand-pointer"></i> O haz clic directamente en el mapa para ubicar el punto exacto</p>
            <div id="mapa"></div>
            <div class="map-coords">
                <input type="number" id="f-lat" class="form-input-styled" placeholder="Latitud" step="0.000001" readonly style="background:#f0f0f0;">
                <input type="number" id="f-lng" class="form-input-styled" placeholder="Longitud" step="0.000001" readonly style="background:#f0f0f0;">
            </div>
        </div>
        <div class="form-group" style="margin-bottom:20px;margin-top:20px;">
            <label class="form-label">📷 Fotos de la Activación</label>
            <div class="fotos-upload-area" id="drop-zone" onclick="document.getElementById('foto-input').click()" ondragover="dragOver(event)" ondragleave="dragLeave(event)" ondrop="dropFotos(event)">
                <div class="upload-icon"><i class="fas fa-images"></i></div>
                <p>Arrastra imágenes aquí o <span>haz clic para seleccionar</span></p>
                <p style="font-size:11px;margin-top:4px;opacity:0.7;">JPG, PNG, WEBP — máx. 5 MB por foto</p>
            </div>
            <input type="file" id="foto-input" accept="image/*" multiple onchange="procesarFotos(this.files)">
            <div class="fotos-preview-grid" id="fotos-preview"></div>
            <div class="foto-counter" id="foto-counter"></div>
        </div>
        <div class="form-group" style="margin-bottom:20px;">
            <label class="form-label">🔗 Enlaces (repositorios / redes sociales)</label>
            <div style="display:flex;gap:8px;margin-bottom:8px;">
                <input type="url" id="enlace-input" class="form-input-styled" placeholder="https://... (Drive, Fotos, Facebook, Instagram, X, etc.)" style="flex:1;"
                       onkeydown="if(event.key==='Enter'){event.preventDefault();agregarEnlace();}">
                <button type="button" onclick="agregarEnlace()" style="background:var(--primary);color:#fff;border:none;border-radius:10px;padding:0 16px;font-weight:700;font-size:13px;cursor:pointer;white-space:nowrap;"><i class="fas fa-plus"></i> Agregar</button>
            </div>
            <div id="enlaces-preview"></div>
        </div>
        <div class="form-group" style="background:#f0f7f0;border:1px solid #d5e8d5;border-radius:10px;padding:14px;margin-bottom:20px;">
            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-weight:700;font-size:13px;color:#1f5a2d;">
                <input type="checkbox" id="f-registro-qr" style="width:18px;height:18px;">
                <i class="fas fa-qrcode"></i> Activar registro de asistentes (liga + QR)
            </label>
            <div style="font-size:11px;color:#4a7a4a;margin-top:6px;">La liga y el QR quedan disponibles en "Database QR" al guardar.</div>
        </div>
        <div class="modal-footer">
            <button class="btn-danger" id="btn-eliminar" style="visibility:hidden"><i class="fas fa-trash-alt"></i> Eliminar</button>
            <div style="display:flex;gap:10px;">
                <button class="btn-cancel" onclick="cerrarModal('modal-form')">Cancelar</button>
                <button class="btn-save" id="btn-guardar" onclick="guardar()"><i class="fas fa-check"></i> Guardar</button>
            </div>
        </div>
    </div>
</div>

<!-- ── MODAL DETALLE ──────────────────────────────────────── -->
<div class="modal" id="modal-det">
    <div class="modal-content" style="max-width:580px;">
        <div class="close-modal" onclick="cerrarModal('modal-det')"><i class="fas fa-times"></i></div>
        <h2 id="det-titulo" style="font-size:24px;font-weight:800;margin-bottom:10px;">—</h2>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:5px;">
            <span id="det-estatus-badge" class="badge"></span>
            <span id="det-tipo-chip" class="tipo-chip"></span>
        </div>
        <div class="detail-grid">
            <div class="detail-item"><strong>Fecha</strong><span id="det-fecha">—</span></div>
            <div class="detail-item"><strong>Candidato</strong><span id="det-candidato">—</span></div>
            <div class="detail-item"><strong>Coordinador</strong><span id="det-coordinador">—</span></div>
            <div class="detail-item"><strong>Colonia / Zona</strong><span id="det-colonia">—</span></div>
            <div class="detail-item"><strong>Sección Electoral</strong><span id="det-seccion">—</span></div>
            <div class="detail-item"><strong>Municipio</strong><span id="det-municipio">—</span></div>
            <div class="detail-item"><strong>Contactos Est.</strong><span id="det-contactos" class="big">—</span></div>
            <div class="detail-item"><strong>Asistentes Est.</strong><span id="det-asistentes" class="big">—</span></div>
            <div class="detail-item"><strong>Costo Estimado</strong><span id="det-costo">—</span></div>
        </div>
        <div style="margin-top:20px;">
            <strong style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;">Notas</strong>
            <div id="det-notas" style="margin-top:8px;font-size:14px;color:#555;line-height:1.6;background:var(--gray-100);padding:15px;border-radius:10px;border-left:4px solid var(--primary);">—</div>
        </div>
        <div id="det-mapa-wrap" style="display:none;margin-top:20px;">
            <strong style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;">Ubicación</strong>
            <div id="det-mapa"></div>
        </div>
        <div class="det-galeria" id="det-galeria-wrap" style="display:none;">
            <strong style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;">📷 Fotos</strong>
            <div class="det-galeria-grid" id="det-galeria-grid"></div>
        </div>
        <div class="modal-footer">
            <button class="btn-danger" id="det-btn-del"><i class="fas fa-trash-alt"></i> Eliminar</button>
            <div style="display:flex;gap:10px;">
                <button class="btn-cancel" onclick="cerrarModal('modal-det')">Cerrar</button>
                <button class="btn-save" id="det-btn-edit"><i class="fas fa-pen"></i> Editar</button>
            </div>
        </div>
    </div>
</div>

<!-- ── MODAL VERIFICAR COMPLETADA ────────────────────────── -->
<div class="modal" id="modal-verificar" style="z-index:3000;">
    <div class="modal-content" style="max-width:480px;padding:36px;">
        <h3 style="font-size:20px;font-weight:800;color:#1a1a1a;margin-bottom:6px;">✅ Confirmar datos antes de completar</h3>
        <p style="font-size:13px;color:#888;margin-bottom:24px;">Revisa que los datos registrados sean correctos. Puedes actualizarlos antes de guardar.</p>
        <div style="background:#f8fdf9;border:1px solid #d1fae5;border-radius:14px;padding:20px;margin-bottom:20px;display:flex;flex-direction:column;gap:14px;">
            <div><label style="font-size:11px;font-weight:700;color:#555;text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:4px;">Contactos Nuevos Est.</label><input type="number" id="vrf-contactos" min="0" class="form-input-styled" style="width:100%;"></div>
            <div><label style="font-size:11px;font-weight:700;color:#555;text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:4px;">Asistentes Est.</label><input type="number" id="vrf-asistentes" min="0" class="form-input-styled" style="width:100%;"></div>
            <div><label style="font-size:11px;font-weight:700;color:#555;text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:4px;">Costo Estimado ($)</label><input type="number" id="vrf-costo" min="0" step="0.01" class="form-input-styled" style="width:100%;"></div>
        </div>
        <p style="font-size:12px;color:#aaa;margin-bottom:20px;text-align:center;"><i class="fas fa-info-circle"></i> Si los datos son correctos, presiona <strong>Confirmar y Guardar</strong>.</p>
        <div style="display:flex;gap:10px;justify-content:flex-end;">
            <button class="btn-cancel" onclick="cerrarModal('modal-verificar')">Cancelar</button>
            <button class="btn-save" onclick="guardarConfirmado()"><i class="fas fa-check"></i> Confirmar y Guardar</button>
        </div>
    </div>
</div>

<div id="lightbox">
    <button class="lb-close" onclick="cerrarLightbox()"><i class="fas fa-times"></i></button>
    <img id="lb-img" src="" alt="">
    <div class="lb-nav">
        <button class="lb-btn" onclick="lbPrev()"><i class="fas fa-chevron-left"></i> Ant.</button>
        <button class="lb-btn" onclick="lbNext()">Sig. <i class="fas fa-chevron-right"></i></button>
    </div>
    <div class="lb-counter" id="lb-counter"></div>
</div>

<script>
// ── Datos PHP → JS ──────────────────────────────────────────
const activacionesDB = <?= json_encode(array_map(function($row) {
    return [
        'id'          => (int)$row['id'],
        'titulo'      => $row['titulo'],
        'tipo'        => $row['tipo'],
        'fecha'       => $row['fecha_actividad'] ?? '',
        'estatus'     => $row['estatus'],
        'coordinador' => $row['coordinador'] ?? '',
        'seccion'     => $row['seccion_electoral'] ?? '',
        'colonia'     => $row['colonia'] ?? '',
        'contactos'   => (int)($row['contactos_estimados'] ?? 0),
        'asistentes'  => (int)($row['asistentes_estimados'] ?? 0),
        'costo'       => (float)($row['costo'] ?? 0),
        'notas'       => $row['notas'] ?? '',
        'lat'         => $row['latitud']  ? (float)$row['latitud']  : null,
        'lng'         => $row['longitud'] ? (float)$row['longitud'] : null,
        'fotos'       => json_decode($row['fotos'] ?? '[]', true) ?: [],
        'enlaces'     => json_decode($row['enlaces'] ?? '[]', true) ?: [],
        'estado'      => $row['estado'] ?? '',
        'municipio'   => $row['municipio'] ?? '',
        'candidato'   => $row['candidato'] ?? '',
        'creado_por_rol' => $row['creado_por_rol'] ?? '',
    ];
}, $activaciones), JSON_UNESCAPED_UNICODE) ?>;

const ESTADO_REGION   = '<?= addslashes($user_estado) ?>';
const ESTADOS_MX      = <?= json_encode($estados_mx_db, JSON_UNESCAPED_UNICODE) ?: '{}' ?>;
const SECCIONES_GEO_ACT = <?php echo json_encode($secciones_geo ?? [], JSON_UNESCAPED_UNICODE); ?>;
const SECCIONES_POR_ZONA = <?= json_encode($secciones_por_zona ?? [], JSON_UNESCAPED_UNICODE) ?>;
// Secciones que pertenecen a las zonas de esta región (para filtrar el selector)
const SECCIONES_REGION = <?= json_encode(array_values($secciones_region ?? []), JSON_UNESCAPED_UNICODE) ?>;
// Mapa seccion → nombre de zona (para auto-detectar zona al elegir sección)
const SECCION_ZONA_MAP = <?= json_encode($seccion_zona_map ?? [], JSON_UNESCAPED_UNICODE) ?>;
const TIPO_INTEGRACION_REGION = <?php echo json_encode($tipo_integracion_region ?? 'municipios', JSON_UNESCAPED_UNICODE); ?>;

function getEtiquetaIntegracionRegional() {
    if (TIPO_INTEGRACION_REGION === 'distrito_local')   return { label: 'Distrito Local *', filtroLabel: 'Distrito Local', vacio: '— Selecciona un distrito local —', todos: 'Todos los distritos locales', prefijo: 'DL ', placeholder: '— Selecciona distrito local primero —', vacioSel: 'Selecciona distrito local primero' };
    if (TIPO_INTEGRACION_REGION === 'distrito_federal') return { label: 'Distrito Federal *', filtroLabel: 'Distrito Federal', vacio: '— Selecciona un distrito federal —', todos: 'Todos los distritos federales', prefijo: 'DF ', placeholder: '— Selecciona distrito federal primero —', vacioSel: 'Selecciona distrito federal primero' };
    return { label: 'Municipio *', filtroLabel: 'Municipio', vacio: '— Selecciona un municipio —', todos: 'Todos los municipios', prefijo: '', placeholder: '— Selecciona municipio primero —', vacioSel: 'Selecciona municipio primero' };
}

let activaciones = activacionesDB;
let editandoId = null, estatusSeleccionado = null;
let mapaInst = null, marcador = null, detMapaInst = null;
let fotosActuales = [];
let enlacesActuales = [];
let lbFotos = [], lbIdx = 0;
let SEC_ACT = [], SEC_ACT_FOCUSED = -1;
let conCandidato = null;

const API = '../../api/activaciones/procesar.php';
const ROL_ACTUAL = '<?= addslashes($rol) ?>';
const TIPO_LABELS = {territorial:'Territorial',digital:'Digital',simbolica:'Simbólica / Impacto',estructura:'Estructura'};

// ── Inicializar filtro municipio y select del form ─────────
window.addEventListener('DOMContentLoaded', () => {
    const et = getEtiquetaIntegracionRegional();
    const municipios = Object.keys(ESTADOS_MX[ESTADO_REGION]?.municipios || {}).sort();

    const lblForm = document.getElementById('f-municipio-label');
    if (lblForm) lblForm.textContent = et.label;
    const filtroLabelEl = document.getElementById('filtro-municipio')?.previousElementSibling;
    if (filtroLabelEl && filtroLabelEl.classList.contains('form-label')) filtroLabelEl.textContent = et.filtroLabel;

    // Filtro barra
    const selFiltro = document.getElementById('filtro-municipio');
    selFiltro.innerHTML = `<option value="">${et.todos}</option>`;
    municipios.forEach(m => { selFiltro.innerHTML += `<option value="${m}">${et.prefijo}${m}</option>`; });

    // Select del formulario
    const selForm = document.getElementById('f-municipio');
    let opts = `<option value="">${et.vacio}</option>`;
    municipios.forEach(m => { opts += `<option value="${m}">${et.prefijo}${m}</option>`; });
    selForm.innerHTML = opts;

    const disp = document.getElementById('sec-act-display');
    if (disp) disp.placeholder = et.placeholder;
});

function filtroZonaCambio() {
    const zonaId = document.getElementById('filtro-zona').value;
    const selS   = document.getElementById('filtro-seccion');
    selS.innerHTML = '<option value="">Todas las secciones</option>';
    selS.disabled  = true;
    if (zonaId) {
        const secs = (SECCIONES_POR_ZONA[zonaId] || []).slice().sort((a,b)=>parseInt(a)-parseInt(b));
        secs.forEach(s => { selS.innerHTML += `<option value="${s}">${s}</option>`; });
        if (secs.length) selS.disabled = false;
    }
    renderTabla();
}

// ── RESUMEN ────────────────────────────────────────────────
function actualizarResumen(){
    const activas = activaciones.filter(a => a.estatus !== 'cancelada');
    const c   = activas.reduce((s,a) => s + (parseInt(a.contactos)||0), 0);
    const inv = activas.reduce((s,a) => s + (parseFloat(a.costo)||0),   0);
    const cpc = c > 0 ? Math.round(inv/c) : 0;
    document.getElementById('res-contactos').textContent = c.toLocaleString('es-MX');
    document.getElementById('res-inversion').textContent = '$' + inv.toLocaleString('es-MX');
    document.getElementById('res-cpc').textContent       = '$' + cpc.toLocaleString('es-MX');
}

// ── TABLA ──────────────────────────────────────────────────

// ── Filtro Mes / Semana ─────────────────────────────────────────────
function isoWeek(dateStr) {
    if (!dateStr) return null;
    const d = new Date(dateStr + 'T12:00:00');
    const day = d.getDay() || 7;
    d.setDate(d.getDate() + 4 - day);
    const yearStart = new Date(d.getFullYear(), 0, 1);
    return { week: Math.ceil(((d - yearStart) / 86400000 + 1) / 7), year: d.getFullYear() };
}
function weekLabel(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr + 'T12:00:00');
    const day = d.getDay() || 7;
    const lun = new Date(d); lun.setDate(d.getDate() - (day - 1));
    const dom = new Date(lun); dom.setDate(lun.getDate() + 6);
    const fmtD = dd => String(dd.getDate()).padStart(2,'0');
    const MESES_CORTO = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
    return `${fmtD(lun)} ${MESES_CORTO[lun.getMonth()]} - ${fmtD(dom)} ${MESES_CORTO[dom.getMonth()]}`;
}
function mesChange() {
    const mes = parseInt(document.getElementById('filtro-mes').value);
    const selSem = document.getElementById('filtro-semana');
    selSem.innerHTML = '<option value="">Todas las semanas</option>';
    selSem.disabled = (mes === 0);
    document.getElementById('btn-limpiar-fecha').style.display = mes !== 0 ? 'inline-block' : 'none';
    if (mes === 0) { selSem.value = ''; renderTabla(); return; }

    // Generar TODAS las semanas del mes (no solo las que tienen activaciones)
    const year = new Date().getFullYear();
    const semanasMap = new Map();

    // Iterar cada día del mes y colectar semanas únicas
    const diasEnMes = new Date(year, mes, 0).getDate();
    for (let dia = 1; dia <= diasEnMes; dia++) {
        const dateStr = `${year}-${String(mes).padStart(2,'0')}-${String(dia).padStart(2,'0')}`;
        const w = isoWeek(dateStr);
        if (!w) continue;
        const key = `${w.year}-W${String(w.week).padStart(2,'0')}`;
        if (!semanasMap.has(key)) semanasMap.set(key, weekLabel(dateStr));
    }

    [...semanasMap.entries()]
        .sort(([a],[b]) => a.localeCompare(b))
        .forEach(([key, label]) => {
            const o = document.createElement('option');
            o.value = key; o.textContent = `Semana ${key.split('W')[1]} (${label})`;
            selSem.appendChild(o);
        });
    renderTabla();
}
function limpiarMesSemana() {
    document.getElementById('filtro-mes').value = '0';
    document.getElementById('filtro-semana').innerHTML = '<option value="">Todas las semanas</option>';
    document.getElementById('filtro-semana').disabled = true;
    document.getElementById('btn-limpiar-fecha').style.display = 'none';
    renderTabla();
}


// ── BADGE ROL REGISTRADOR ─────────────────────────────────
const ROL_LABELS = {
    'coordinador_estatal':    {label:'Estatal',    color:'#1e88e5', bg:'#eff6ff'},
    'coordinador_regional':   {label:'Regional',   color:'#7c3aed', bg:'#f5f3ff'},
    'regional':               {label:'Regional',   color:'#7c3aed', bg:'#f5f3ff'},
    'coordinador_zona':       {label:'Zona',       color:'#d97706', bg:'#fffbeb'},
    'coordinador_circunscripcion': {label:'Circunscripción', color:'#0891b2', bg:'#ecfeff'},
    'circunscripcion':        {label:'Circunscripción', color:'#0891b2', bg:'#ecfeff'},
    'admin':                  {label:'Admin',      color:'#dc2626', bg:'#fef2f2'},
    'administrador':          {label:'Admin',      color:'#dc2626', bg:'#fef2f2'},
};
function rolBadge(rol) {
    if (!rol) return '';
    const r = ROL_LABELS[rol] || {label: rol, color:'#6c757d', bg:'#f8f9fa'};
    return `<span style="display:inline-block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;padding:3px 8px;border-radius:5px;color:${r.color};background:${r.bg};border:1px solid ${r.color}33;white-space:nowrap;">${r.label}</span>`;
}

function renderTabla(){
    // 1. Lectura segura de filtros (Evita errores si el input no existe en este archivo)
    const inputBuscar = document.getElementById('buscador');
    const q = inputBuscar ? inputBuscar.value.toLowerCase() : '';

    const filtroEst   = document.getElementById('filtro-estatus');
    const est         = filtroEst ? filtroEst.value : '';

    const filtroTipo  = document.getElementById('filtro-tipo');
    const tipo        = filtroTipo ? filtroTipo.value : '';

    const filtroEdo   = document.getElementById('filtro-estado');
    const estFiltro   = filtroEdo ? filtroEdo.value : '';

    const filtroMun   = document.getElementById('filtro-municipio');
    const munFiltro   = filtroMun ? filtroMun.value : '';

    const filtroZona    = document.getElementById('filtro-zona')    ? document.getElementById('filtro-zona').value    : '';
    const filtroSeccion = document.getElementById('filtro-seccion') ? document.getElementById('filtro-seccion').value : '';

    // Filtro Mes / Semana
    const _mes  = parseInt(document.getElementById('filtro-mes')?.value || '0');
    const _sem  = document.getElementById('filtro-semana')?.value || '';

    // 2. Filtrado de la lista
    let lista = [...activaciones].sort((a,b) => (b.fecha||'').localeCompare(a.fecha||''));
    if(q)         lista = lista.filter(a => (a.titulo||'').toLowerCase().includes(q) || (a.seccion||'').includes(q));
    if (_mes > 0) lista = lista.filter(a => a.fecha && (new Date(a.fecha+'T12:00:00').getMonth()+1) === _mes);
    if (_sem)     lista = lista.filter(a => {
        const w = isoWeek(a.fecha);
        return w && `${w.year}-W${String(w.week).padStart(2,'0')}` === _sem;
    });
    if(est)       lista = lista.filter(a => a.estatus === est);
    if(tipo)      lista = lista.filter(a => a.tipo === tipo);
    if(estFiltro) lista = lista.filter(a => a.estado === estFiltro);
    if(munFiltro) lista = lista.filter(a => a.municipio === munFiltro);

    if (filtroZona && !filtroSeccion) {
        const zonaSecs = (SECCIONES_POR_ZONA[filtroZona] || []).map(String);
        lista = lista.filter(a => a.seccion && zonaSecs.includes(String(a.seccion)));
    }
    if (filtroSeccion) {
        lista = lista.filter(a => String(a.seccion) === String(filtroSeccion));
    }

    // Actualizar contador
    const countLabel = document.getElementById('count-label');
    if(countLabel) countLabel.textContent = lista.length + ' activación' + (lista.length !== 1 ? 'es' : '');

    // 3. Leer las columnas del HTML para adaptar el renderizado
    const thead = document.querySelector('.modern-table thead tr');
    const numColumnas = thead ? thead.children.length : 8; // Para el colspan
    
    // Verificar qué columnas existen en este nivel (Regional usa Municipio, Admin usa Estado)
    const headersText = thead ? Array.from(thead.children).map(th => th.textContent.trim().toLowerCase()) : [];
    const tieneEstado = headersText.includes('estado');
    const tieneMunicipio = headersText.includes('municipio');

    const tbody = document.getElementById('tabla-body');
    if(!lista.length){
        tbody.innerHTML = `<tr class="empty-row"><td colspan="${numColumnas}"><i class="fas fa-calendar-xmark" style="font-size:30px;opacity:0.2;display:block;margin-bottom:10px;"></i>No hay activaciones registradas.</td></tr>`;
        actualizarResumen(); return;
    }

    tbody.innerHTML = lista.map(a => {
        const bc   = `badge-${a.estatus||'programada'}`;
        const bt   = (a.estatus||'programada').replace('-',' ').replace(/\b\w/g,c=>c.toUpperCase());
        const fecha = a.fecha ? fmtFecha(a.fecha) : '—';
        
        // Construir la fila dinámica
        let rowHTML = `<tr>
            <td>
                <span style="font-weight:700;font-size:14px;cursor:pointer;color:var(--dark);" onclick="verDetalle(${a.id})">${a.titulo||'Sin título'}</span>
                ${a.fotos && a.fotos.length ? `<span style="font-size:11px;font-weight:700;color:var(--primary);background:rgba(45,122,62,0.1);padding:2px 7px;border-radius:6px;margin-left:6px;"><i class="fas fa-camera"></i> ${a.fotos.length}</span>` : ''}
                ${(a.colonia || a.seccion) ? `<div style="font-size:12px;color:var(--text-muted);margin-top:3px;"><i class="fas fa-location-dot"></i> ${[a.colonia, a.seccion ? 'Secc. ' + a.seccion : ''].filter(Boolean).join(' · ')}</div>` : ''}
                ${a.coordinador ? `<div style="font-size:12px;color:var(--text-muted);"><i class="fas fa-user"></i> ${a.coordinador}</div>` : ''}
            </td>
            <td><span class="tipo-chip">${TIPO_LABELS[a.tipo]||a.tipo||'—'}</span></td>
            <td style="font-size:13px;">${fecha}</td>
            <td><span class="badge ${bc}">${bt}</span></td>`;
            
        // Agregar columnas dinámicas si existen en el THEAD
        if(tieneEstado)   rowHTML += `<td><span style="font-size:13px; font-weight:600; color:var(--text-muted);">${a.estado || '—'}</span></td>`;
        if(tieneMunicipio) rowHTML += `<td><span style="font-size:13px; font-weight:600; color:var(--text-muted);">${a.municipio || '—'}</span></td>`;
        rowHTML += `<td>${rolBadge(a.creado_por_rol)}</td>`;

        rowHTML += `
            <td style="text-align:right;"><div class="stat-cell-label">Contactos</div><div class="stat-cell">${(a.contactos||0).toLocaleString('es-MX')}</div></td>
            <td style="text-align:right;"><div class="stat-cell-label">Asistentes</div><div class="stat-cell">${(a.asistentes||0).toLocaleString('es-MX')}</div></td>
            <td style="text-align:right;"><div class="stat-cell-label">Costo</div><div class="stat-cell neutral">$${parseFloat(a.costo||0).toLocaleString('es-MX')}</div></td>
            <td class="action-cell">
                <button class="action-btn" onclick="toggleDropdown(this)"><i class="fas fa-ellipsis-v"></i></button>
                <div class="dropdown-menu">
                    <button class="dropdown-item" onclick="verDetalle(${a.id})"><i class="fas fa-eye"></i> Ver detalle</button>
                    ${a.creado_por_rol === ROL_ACTUAL ? `
                    <button class="dropdown-item" onclick="abrirModal(${a.id})"><i class="fas fa-pen"></i> Editar</button>
                    ${(a.estatus === 'en-curso' || a.estatus === 'completada')
                        ? '<button class="dropdown-item delete-item" disabled title="No se puede eliminar una activación en curso o completada" style="opacity:0.4;cursor:not-allowed;"><i class=\"fas fa-trash-alt\"></i> Eliminar</button>'
                        : `<button class="dropdown-item delete-item" onclick="eliminar(${a.id})"><i class="fas fa-trash-alt"></i> Eliminar</button>`}
                    ` : ''}
                </div>
            </td>
        </tr>`;
        return rowHTML;
    }).join('');
    
    actualizarResumen();
}

function fmtFecha(f){ const m=['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic']; const[y,mo,d]=f.split('-'); return `${parseInt(d)} ${m[parseInt(mo)-1]} ${y}`; }

function toggleDropdown(btn){
    document.querySelectorAll('.dropdown-menu.show').forEach(d=>{if(d!==btn.nextElementSibling)d.classList.remove('show');});
    btn.classList.toggle('active'); btn.nextElementSibling.classList.toggle('show');
}
document.addEventListener('click',e=>{
    if(!e.target.closest('.action-cell')){
        document.querySelectorAll('.dropdown-menu.show').forEach(d=>d.classList.remove('show'));
        document.querySelectorAll('.action-btn.active').forEach(b=>b.classList.remove('active'));
    }
});

// ── SECCIONES ─────────────────────────────────────────────
function cargarSeccionesActivacion(municipio) {
    const et = getEtiquetaIntegracionRegional();
    secActReset('Cargando secciones…', true);
    if (!municipio) { secActReset(et.vacioSel); return; }
    // Todas las secciones del municipio/distrito elegido (ya no se restringe a las que
    // ya tienen zona asignada: la zona es informativa, no un requisito para activar)
    SEC_ACT = (SECCIONES_GEO_ACT[ESTADO_REGION] || {})[municipio] || [];
    const n = SEC_ACT.length;
    const cnt = document.getElementById('f-sec-act-contador');
    if (cnt) cnt.textContent = n > 0 ? `(${n} secciones)` : '';
    secActRenderOpts(SEC_ACT);
    document.getElementById('sec-act-display').placeholder = n ? `— Selecciona sección (${n}) —` : '— Sin secciones —';
}
function secActReset(msg='Selecciona municipio primero', loading=false) {
    SEC_ACT=[]; SEC_ACT_FOCUSED=-1;
    document.getElementById('f-seccion').value='';
    document.getElementById('sec-act-display').value='';
    document.getElementById('sec-act-display').placeholder='— '+msg+' —';
    if(document.getElementById('sec-act-search')) document.getElementById('sec-act-search').value='';
    const cnt=document.getElementById('f-sec-act-contador'); if(cnt) cnt.textContent='';
    const zonaEl=document.getElementById('f-zona-act'); if(zonaEl) zonaEl.value='';
    const opts=document.getElementById('sec-act-options');
    opts.innerHTML=loading?`<div class="sec-act-loading"><i class="fas fa-spinner fa-spin"></i> ${msg}</div>`:`<div class="sec-act-empty">${msg}</div>`;
    secActCerrar();
}
function secActRenderOpts(lista, busqueda='') {
    const opts=document.getElementById('sec-act-options');
    const actual=document.getElementById('f-seccion').value;
    SEC_ACT_FOCUSED=-1;
    if(!lista.length){opts.innerHTML=`<div class="sec-act-empty">Sin resultados${busqueda?` para "${busqueda}"`:''}</div>`;return;}
    opts.innerHTML=lista.map(s=>{
        const label=busqueda.trim()?s.replace(new RegExp(`(${busqueda.trim()})`, 'gi'), '<em>$1</em>'):s;
        return `<div class="sec-act-opt${String(s)===String(actual)?' selected':''}" onmousedown="secActSeleccionar('${s}')">${label}</div>`;
    }).join('');
}
function secActFiltrar(q){if(!SEC_ACT.length)return;const f=q.trim()?SEC_ACT.filter(s=>s.toString().toLowerCase().includes(q.trim().toLowerCase())):SEC_ACT;secActRenderOpts(f,q);}
function secActSeleccionar(val){
    document.getElementById('f-seccion').value=val;
    document.getElementById('sec-act-display').value=val;
    document.getElementById('sec-act-display').placeholder='';
    secActCerrar();
    document.querySelectorAll('.sec-act-opt').forEach(el=>el.classList.toggle('selected',el.textContent===val));
    // Auto-detectar zona desde SECCION_ZONA_MAP
    const zonaEl = document.getElementById('f-zona-act');
    if (zonaEl) zonaEl.value = SECCION_ZONA_MAP[String(val)] || '';
}
function secActToggle(){const wrap=document.getElementById('sec-act-wrap');if(!SEC_ACT.length)return;if(wrap.classList.contains('open')){secActCerrar();}else{wrap.classList.add('open');const s=document.getElementById('sec-act-search');s.value='';secActRenderOpts(SEC_ACT);setTimeout(()=>s.focus(),50);}}
function secActCerrar(){document.getElementById('sec-act-wrap')?.classList.remove('open');}
function secActNavegar(e){const items=document.querySelectorAll('.sec-act-opt');if(!items.length)return;if(e.key==='ArrowDown')SEC_ACT_FOCUSED=Math.min(SEC_ACT_FOCUSED+1,items.length-1);else if(e.key==='ArrowUp')SEC_ACT_FOCUSED=Math.max(SEC_ACT_FOCUSED-1,0);else if(e.key==='Enter'&&SEC_ACT_FOCUSED>=0){e.preventDefault();secActSeleccionar(items[SEC_ACT_FOCUSED].textContent);return;}else if(e.key==='Escape'){secActCerrar();return;}items.forEach((el,i)=>el.classList.toggle('focused',i===SEC_ACT_FOCUSED));if(SEC_ACT_FOCUSED>=0)items[SEC_ACT_FOCUSED].scrollIntoView({block:'nearest'});}
document.addEventListener('click',e=>{if(!e.target.closest('#sec-act-wrap'))secActCerrar();});

// ── MAPA ──────────────────────────────────────────────────
function centrarMapa() {
    const mun = document.getElementById('f-municipio').value;
    const data = ESTADOS_MX[ESTADO_REGION];
    if (!data) return;
    const lat = data.lat, lng = data.lng;
    if (mapaInst) {
        mapaInst.setView([lat, lng], mun ? 11 : 7);
        if(marcador) mapaInst.removeLayer(marcador);
        marcador = L.marker([lat, lng]).addTo(mapaInst);
        document.getElementById('f-lat').value = lat.toFixed(6);
        document.getElementById('f-lng').value = lng.toFixed(6);
    }
    secActReset('Cargando secciones…', true);
    cargarSeccionesActivacion(mun);
}

function iniciarMapa(){
    const el=document.getElementById('mapa'); if(!el||mapaInst) return;
    const lat0=parseFloat(document.getElementById('f-lat').value)||(ESTADOS_MX[ESTADO_REGION]?.lat||23.6345);
    const lng0=parseFloat(document.getElementById('f-lng').value)||(ESTADOS_MX[ESTADO_REGION]?.lng||-102.5528);
    mapaInst=L.map('mapa').setView([lat0,lng0],document.getElementById('f-lat').value?12:7);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'© OpenStreetMap'}).addTo(mapaInst);
    if(document.getElementById('f-lat').value) marcador=L.marker([lat0,lng0]).addTo(mapaInst);
    mapaInst.on('click',function(e){
        const{lat,lng}=e.latlng;
        document.getElementById('f-lat').value=lat.toFixed(6);
        document.getElementById('f-lng').value=lng.toFixed(6);
        if(marcador) mapaInst.removeLayer(marcador);
        marcador=L.marker([lat,lng]).addTo(mapaInst);
    });
}

async function buscarEnMapa() {
    const q=document.getElementById('map-search-input').value.trim(); if(!q) return;
    const resultsEl=document.getElementById('map-search-results');
    resultsEl.style.display='block';
    resultsEl.innerHTML='<div style="padding:10px 14px;font-size:13px;color:#999;"><i class="fas fa-spinner fa-spin"></i> Buscando...</div>';
    try {
        const resp=await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(q)}&limit=6&accept-language=es`,{headers:{'Accept-Language':'es'}});
        const data=await resp.json();
        if(!data.length){resultsEl.innerHTML='<div style="padding:10px 14px;font-size:13px;color:#999;">Sin resultados.</div>';return;}
        resultsEl.innerHTML=data.map(r=>`
            <div onclick="seleccionarLugar(${r.lat},${r.lon},'${r.display_name.replace(/'/g,"\\'")}')"
                style="padding:10px 14px;font-size:13px;cursor:pointer;border-bottom:1px solid #f5f5f5;display:flex;align-items:flex-start;gap:10px;transition:background 0.15s;"
                onmouseover="this.style.background='#f4fcf6'" onmouseout="this.style.background='white'">
                <i class="fas fa-map-marker-alt" style="color:var(--primary);margin-top:2px;flex-shrink:0;"></i>
                <span>${r.display_name}</span>
            </div>`).join('');
    } catch(e){resultsEl.innerHTML='<div style="padding:10px 14px;font-size:13px;color:#e53e3e;">Error al buscar.</div>';}
}

function seleccionarLugar(lat,lng,nombre){
    lat=parseFloat(lat);lng=parseFloat(lng);
    document.getElementById('f-lat').value=lat.toFixed(6);
    document.getElementById('f-lng').value=lng.toFixed(6);
    document.getElementById('map-search-input').value=nombre;
    document.getElementById('map-search-results').style.display='none';
    if(!mapaInst) { iniciarMapa(); }
    setTimeout(()=>{
        if(marcador) mapaInst.removeLayer(marcador);
        marcador=L.marker([lat,lng]).addTo(mapaInst);
        mapaInst.invalidateSize();
        mapaInst.setView([lat,lng],15);
    }, mapaInst ? 0 : 150);
}

document.addEventListener('click',function(e){
    const inp=document.getElementById('map-search-input');
    const res=document.getElementById('map-search-results');
    if(inp&&res&&!inp.contains(e.target)&&!res.contains(e.target)) res.style.display='none';
});

// ── MODAL FORM ─────────────────────────────────────────────
function abrirModal(id=null){
    if (id) {
        const _a = activaciones.find(x => parseInt(x.id) === parseInt(id));
        if (_a && _a.creado_por_rol && _a.creado_por_rol !== ROL_ACTUAL) {
            alert('Solo el rol que registró esta activación puede editarla.');
            return;
        }
    }
    editandoId=id?parseInt(id):null; id=editandoId; estatusSeleccionado=null;
    document.getElementById('form-title').textContent=id?'Editar Activación':'Nueva Activación';
    ['f-titulo','f-tipo','f-fecha','f-coordinador','f-colonia','f-seccion','f-contactos','f-asistentes','f-costo','f-notas','f-lat','f-lng']
        .forEach(f=>{const el=document.getElementById(f);if(el)el.value='';});
    secActReset(getEtiquetaIntegracionRegional().vacioSel);
    document.getElementById('f-municipio').value='';
    fotosActuales=[]; renderFotosPreview();
    enlacesActuales=[]; renderEnlacesPreview();
    document.querySelectorAll('.est-btn').forEach(b=>b.className='est-btn');
    resetCandidatoUI();
    document.getElementById('f-registro-qr').checked = false;
    if (id) {
        fetch(`../../api/registro_asistentes/procesar.php?action=list&tipo=activacion`)
            .then(r=>r.json())
            .then(d=>{
                if (d.ok && d.items.some(it => it.origen_id === id)) {
                    document.getElementById('f-registro-qr').checked = true;
                }
            }).catch(()=>{});
    }
    const btnEl=document.getElementById('btn-eliminar');
    if(!id){
        document.getElementById('f-fecha').value=new Date().toISOString().split('T')[0];
        btnEl.style.visibility='hidden';
    } else {
        const a=activaciones.find(x=>parseInt(x.id)===parseInt(id));
        if(a){
            document.getElementById('f-titulo').value=a.titulo||'';
            document.getElementById('f-tipo').value=a.tipo||'';
            document.getElementById('f-fecha').value=a.fecha||'';
            document.getElementById('f-coordinador').value=a.coordinador||'';
            document.getElementById('f-contactos').value=a.contactos||'';
            document.getElementById('f-asistentes').value=a.asistentes||'';
            document.getElementById('f-costo').value=a.costo||'';
            document.getElementById('f-notas').value=a.notas||'';
            if(a.lat) document.getElementById('f-lat').value=a.lat;
            if(a.lng) document.getElementById('f-lng').value=a.lng;
            if(a.estatus) seleccionarEstatus(a.estatus);
            if(a.candidato){seleccionarCandidato(true);document.getElementById('f-candidato').value=a.candidato;}
            else seleccionarCandidato(false);
            fotosActuales=(a.fotos||[]).map(d=>({dataUrl:d,name:'foto'})); renderFotosPreview();
            enlacesActuales=(a.enlaces||[]); renderEnlacesPreview();
            if(a.municipio){
                document.getElementById('f-municipio').value=a.municipio;
                cargarSeccionesActivacion(a.municipio);
                if(a.seccion){document.getElementById('f-seccion').value=a.seccion;document.getElementById('sec-act-display').value=a.seccion;}
            }
            if(a.colonia) document.getElementById('f-colonia').value=a.colonia||'';
        }
        if(a&&(a.estatus==='en-curso'||a.estatus==='completada')){
            btnEl.style.visibility='visible'; btnEl.disabled=true;
            btnEl.title='No se puede eliminar'; btnEl.style.opacity='0.4'; btnEl.style.cursor='not-allowed'; btnEl.onclick=null;
        } else {
            btnEl.style.visibility='visible'; btnEl.disabled=false;
            btnEl.title=''; btnEl.style.opacity=''; btnEl.style.cursor=''; btnEl.onclick=()=>eliminar(id);
        }
    }
    document.getElementById('modal-form').classList.add('open');
    setTimeout(()=>iniciarMapa(),120);
}

function cerrarModal(id){
    document.getElementById(id).classList.remove('open');
    if(id==='modal-form'&&mapaInst){mapaInst.remove();mapaInst=null;marcador=null;}
    if(id==='modal-det'&&detMapaInst){detMapaInst.remove();detMapaInst=null;}
}

function seleccionarEstatus(est){
    if(editandoId&&estatusSeleccionado&&estatusSeleccionado!==est){
        const labels={'programada':'📅 Programada','en-curso':'▶️ En curso','completada':'✅ Completada','cancelada':'❌ Cancelada'};
        if(!confirm(`¿Cambiar estatus de "${labels[estatusSeleccionado]}" a "${labels[est]}"?`)) return;
    }
    estatusSeleccionado=est;
    document.querySelectorAll('.est-btn').forEach(b=>b.className=b.dataset.est===est?`est-btn sel-${est}`:'est-btn');
}

function seleccionarCandidato(tiene){
    conCandidato=tiene;
    const btnCon=document.getElementById('btn-con-candidato'),btnSin=document.getElementById('btn-sin-candidato'),wrap=document.getElementById('candidato-wrap');
    if(tiene){btnCon.style.background='rgba(45,122,62,0.12)';btnCon.style.borderColor='var(--primary)';btnCon.style.color='var(--primary)';btnSin.style.background='';btnSin.style.borderColor='';btnSin.style.color='';wrap.style.display='block';document.getElementById('f-candidato').focus();}
    else{btnSin.style.background='#fef2f2';btnSin.style.borderColor='#fca5a5';btnSin.style.color='#dc2626';btnCon.style.background='';btnCon.style.borderColor='';btnCon.style.color='';wrap.style.display='none';document.getElementById('f-candidato').value='';}
}
function resetCandidatoUI(){conCandidato=null;['btn-con-candidato','btn-sin-candidato'].forEach(id=>{const b=document.getElementById(id);b.style.background='';b.style.borderColor='';b.style.color='';});document.getElementById('candidato-wrap').style.display='none';document.getElementById('f-candidato').value='';}

// ── VERIFICACIÓN COMPLETADA ───────────────────────────────
function mostrarVerificacionCompletada(){
    document.getElementById('vrf-contactos').value=document.getElementById('f-contactos').value||'';
    document.getElementById('vrf-asistentes').value=document.getElementById('f-asistentes').value||'';
    document.getElementById('vrf-costo').value=document.getElementById('f-costo').value||'';
    document.getElementById('modal-verificar').classList.add('open');
}
function guardarConfirmado(){
    document.getElementById('f-contactos').value=document.getElementById('vrf-contactos').value;
    document.getElementById('f-asistentes').value=document.getElementById('vrf-asistentes').value;
    document.getElementById('f-costo').value=document.getElementById('vrf-costo').value;
    document.getElementById('modal-verificar').classList.remove('open');
    _saltarVerificacion=true; guardarEjecutar();
}

// ── GUARDAR ───────────────────────────────────────────────
let _saltarVerificacion=false;
async function guardar(){_saltarVerificacion=false; await guardarEjecutar();}
async function guardarEjecutar(){
    const titulo=document.getElementById('f-titulo').value.trim();
    const tipo=document.getElementById('f-tipo').value;
    const fecha=document.getElementById('f-fecha').value;
    const mun=document.getElementById('f-municipio').value;
    if(!titulo){alert('El título es obligatorio.');return;}
    if(!tipo){alert('Selecciona un tipo.');return;}
    if(!fecha){alert('La fecha es obligatoria.');return;}
    if(!mun){alert('Debes seleccionar el Municipio.');return;}
    if(!estatusSeleccionado){alert('Selecciona un estatus.');return;}
    if(estatusSeleccionado==='completada'&&!_saltarVerificacion){mostrarVerificacionCompletada();return;}
    const btn=document.getElementById('btn-guardar');
    btn.classList.add('loading'); btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Guardando...';
    const fd=new FormData();
    fd.append('action', editandoId?'update':'create');
    if(editandoId) fd.append('id', editandoId);
    fd.append('titulo', titulo);
    fd.append('tipo', tipo);
    fd.append('fecha_actividad', fecha);
    fd.append('estatus', estatusSeleccionado);
    fd.append('estado', ESTADO_REGION);
    fd.append('municipio', mun);
    fd.append('candidato', document.getElementById('f-candidato').value.trim());
    fd.append('coordinador', document.getElementById('f-coordinador').value.trim());
    fd.append('seccion_electoral', document.getElementById('f-seccion').value.trim());
    fd.append('colonia', document.getElementById('f-colonia')?.value.trim()||'');
    fd.append('contactos_estimados', document.getElementById('f-contactos').value||0);
    fd.append('asistentes_estimados', document.getElementById('f-asistentes').value||0);
    fd.append('costo', document.getElementById('f-costo').value||0);
    fd.append('notas', document.getElementById('f-notas').value.trim());
    fd.append('latitud', document.getElementById('f-lat').value||'');
    fd.append('longitud', document.getElementById('f-lng').value||'');
    fd.append('fotos', JSON.stringify(fotosActuales.map(f=>f.dataUrl)));
    fd.append('enlaces', JSON.stringify(enlacesActuales));
    try {
        const res=await fetch(API,{method:'POST',body:fd});
        const data=await res.json();
        if(!data.ok) throw new Error(data.msg||'Error desconocido');
        const obj={
            id: editandoId?parseInt(editandoId):parseInt(data.id),
            titulo, tipo, fecha, estatus:estatusSeleccionado,
            estado:ESTADO_REGION, municipio:mun,
            candidato:document.getElementById('f-candidato').value.trim(),
            coordinador:document.getElementById('f-coordinador').value.trim(),
            seccion:document.getElementById('f-seccion').value.trim(),
            colonia:document.getElementById('f-colonia')?.value.trim()||'',
            contactos:parseInt(document.getElementById('f-contactos').value)||0,
            asistentes:parseInt(document.getElementById('f-asistentes').value)||0,
            costo:parseFloat(document.getElementById('f-costo').value)||0,
            notas:document.getElementById('f-notas').value.trim(),
            lat:document.getElementById('f-lat').value?parseFloat(document.getElementById('f-lat').value):null,
            lng:document.getElementById('f-lng').value?parseFloat(document.getElementById('f-lng').value):null,
            fotos:fotosActuales.map(f=>f.dataUrl),
            enlaces:enlacesActuales.slice(),
            creado_por_rol: editandoId
                ? (activaciones.find(x=>parseInt(x.id)===parseInt(editandoId))?.creado_por_rol||'')
                : '<?= addslashes($rol) ?>',
        };
        if(editandoId){const i=activaciones.findIndex(x=>parseInt(x.id)===parseInt(editandoId));if(i!==-1)activaciones[i]=obj;}
        else activaciones.push(obj);
        const idFinalRA = editandoId ? parseInt(editandoId) : parseInt(data.id);
        if (idFinalRA) {
            if (document.getElementById('f-registro-qr').checked) {
                await activarRegistroQR('activacion', idFinalRA);
            } else if (editandoId) {
                await desactivarRegistroQR('activacion', idFinalRA);
            }
        }
        cerrarModal('modal-form'); renderTabla();
    } catch(err){alert('Error al guardar: '+err.message);}
    finally{btn.classList.remove('loading');btn.innerHTML='<i class="fas fa-check"></i> Guardar';}
}

async function activarRegistroQR(tipoOrigen, origenId) {
    try {
        const fdQR = new FormData();
        fdQR.append('action', 'activar');
        fdQR.append('tipo_origen', tipoOrigen);
        fdQR.append('origen_id', origenId);
        await fetch('../../api/registro_asistentes/procesar.php', { method:'POST', body:fdQR });
    } catch (e) { /* no bloquea el guardado de la activación si falla el QR */ }
}

async function desactivarRegistroQR(tipoOrigen, origenId) {
    try {
        const fdQR = new FormData();
        fdQR.append('action', 'desactivar');
        fdQR.append('tipo_origen', tipoOrigen);
        fdQR.append('origen_id', origenId);
        await fetch('../../api/registro_asistentes/procesar.php', { method:'POST', body:fdQR });
    } catch (e) { /* no bloquea el guardado de la activación */ }
}

// ── ELIMINAR ──────────────────────────────────────────────
async function eliminar(id){
    if(!confirm('¿Eliminar esta activación?')) return;
    const fd=new FormData(); fd.append('action','delete'); fd.append('id',id);
    try {
        const res=await fetch(API,{method:'POST',body:fd});
        const data=await res.json();
        if(!data.ok) throw new Error(data.msg);
        activaciones=activaciones.filter(x=>parseInt(x.id)!==parseInt(id)); renderTabla();
    } catch(err){alert('Error al eliminar: '+err.message);}
}

// ── DETALLE ───────────────────────────────────────────────
function verDetalle(id){
    id=parseInt(id);
    const a=activaciones.find(x=>parseInt(x.id)===id); if(!a) return;
    document.getElementById('det-titulo').textContent=a.titulo||'—';
    const bc=`badge-${a.estatus||'programada'}`;
    const bt=(a.estatus||'programada').replace('-',' ').replace(/\b\w/g,c=>c.toUpperCase());
    document.getElementById('det-estatus-badge').className=`badge ${bc}`;
    document.getElementById('det-estatus-badge').textContent=bt;
    document.getElementById('det-tipo-chip').textContent=TIPO_LABELS[a.tipo]||a.tipo||'—';
    document.getElementById('det-fecha').textContent=a.fecha?fmtFecha(a.fecha):'—';
    document.getElementById('det-candidato').textContent=a.candidato?('🙋 '+a.candidato):'Sin candidato';
    document.getElementById('det-coordinador').textContent=a.coordinador||'—';
    document.getElementById('det-colonia').textContent=a.colonia||'—';
    document.getElementById('det-seccion').textContent=a.seccion||'—';
    document.getElementById('det-municipio').textContent=a.municipio||'—';
    document.getElementById('det-contactos').textContent=(a.contactos||0).toLocaleString('es-MX');
    document.getElementById('det-asistentes').textContent=(a.asistentes||0).toLocaleString('es-MX');
    document.getElementById('det-costo').textContent=a.costo?'$'+parseFloat(a.costo).toLocaleString('es-MX'):'—';
    document.getElementById('det-notas').textContent=a.notas||'Sin notas.';
    const puedeEditar = (a.creado_por_rol === ROL_ACTUAL);
    document.getElementById('det-btn-del').style.display  = puedeEditar ? '' : 'none';
    document.getElementById('det-btn-edit').style.display = puedeEditar ? '' : 'none';
    document.getElementById('det-btn-del').onclick=()=>{cerrarModal('modal-det');eliminar(id);};
    document.getElementById('det-btn-edit').onclick=()=>{cerrarModal('modal-det');abrirModal(id);};
    document.getElementById('det-mapa-wrap').style.display=(a.lat&&a.lng)?'block':'none';
    const galeriaWrap=document.getElementById('det-galeria-wrap');
    const galeriaGrid=document.getElementById('det-galeria-grid');
    if(a.fotos&&a.fotos.length){
        galeriaWrap.style.display='block';
        galeriaGrid.innerHTML=a.fotos.map((src,i)=>`<img src="${src}" alt="foto ${i+1}" onclick="abrirLightbox(${JSON.stringify(a.fotos)},${i})">`).join('');
    } else {galeriaWrap.style.display='none';galeriaGrid.innerHTML='';}
    document.getElementById('modal-det').classList.add('open');
    if(a.lat&&a.lng){
        setTimeout(()=>{
            if(detMapaInst){detMapaInst.remove();detMapaInst=null;}
            detMapaInst=L.map('det-mapa').setView([parseFloat(a.lat),parseFloat(a.lng)],14);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'© OpenStreetMap'}).addTo(detMapaInst);
            L.marker([parseFloat(a.lat),parseFloat(a.lng)]).bindPopup(a.titulo).addTo(detMapaInst).openPopup();
        },150);
    }
}

// ── FOTOS ─────────────────────────────────────────────────
function procesarFotos(files){
    Array.from(files).forEach(file=>{
        if(!file.type.startsWith('image/')) return;
        if(file.size>5*1024*1024){alert('La foto "'+file.name+'" supera 5 MB.');return;}
        const reader=new FileReader();
        reader.onload=e=>{fotosActuales.push({dataUrl:e.target.result,name:file.name});renderFotosPreview();};
        reader.readAsDataURL(file);
    });
    document.getElementById('foto-input').value='';
}
function renderFotosPreview(){
    const grid=document.getElementById('fotos-preview');
    const counter=document.getElementById('foto-counter');
    grid.innerHTML=fotosActuales.map((f,i)=>`
        <div class="foto-thumb">
            <img src="${f.dataUrl}" alt="${f.name}" onclick="abrirLightbox(fotosActuales.map(x=>x.dataUrl),${i})">
            <button class="remove-foto" onclick="quitarFoto(${i})" type="button"><i class="fas fa-times"></i></button>
        </div>`).join('');
    counter.textContent=fotosActuales.length?fotosActuales.length+' foto'+(fotosActuales.length!==1?'s':''):'';
}
function quitarFoto(i){fotosActuales.splice(i,1);renderFotosPreview();}

function agregarEnlace(){
    const input = document.getElementById('enlace-input');
    const url = (input.value||'').trim();
    if(!url) return;
    try{ new URL(url); }catch(_e){ alert('Ingresa una URL válida (debe iniciar con http:// o https://).'); return; }
    enlacesActuales.push(url);
    input.value='';
    renderEnlacesPreview();
}
function renderEnlacesPreview(){
    const list = document.getElementById('enlaces-preview');
    if(!list) return;
    list.innerHTML = enlacesActuales.map((url,i)=>`
        <div style="display:flex;align-items:center;gap:8px;padding:8px 10px;border:1px solid #eee;border-radius:8px;margin-bottom:6px;background:#fafafa;">
            <i class="fas fa-link" style="color:var(--primary);flex-shrink:0;"></i>
            <a href="${url}" target="_blank" rel="noopener" style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;color:#333;">${url}</a>
            <button type="button" onclick="quitarEnlace(${i})" style="background:none;border:none;cursor:pointer;color:#999;"><i class="fas fa-times"></i></button>
        </div>`).join('');
}
function quitarEnlace(i){ enlacesActuales.splice(i,1); renderEnlacesPreview(); }
function dragOver(e){e.preventDefault();document.getElementById('drop-zone').classList.add('drag-over');}
function dragLeave(e){document.getElementById('drop-zone').classList.remove('drag-over');}
function dropFotos(e){e.preventDefault();document.getElementById('drop-zone').classList.remove('drag-over');procesarFotos(e.dataTransfer.files);}

// ── LIGHTBOX ──────────────────────────────────────────────
function abrirLightbox(urls,idx){lbFotos=urls;lbIdx=idx;document.getElementById('lb-img').src=lbFotos[lbIdx];document.getElementById('lb-counter').textContent=(lbIdx+1)+' / '+lbFotos.length;document.getElementById('lightbox').classList.add('open');}
function cerrarLightbox(){document.getElementById('lightbox').classList.remove('open');}
function lbPrev(){lbIdx=(lbIdx-1+lbFotos.length)%lbFotos.length;document.getElementById('lb-img').src=lbFotos[lbIdx];document.getElementById('lb-counter').textContent=(lbIdx+1)+' / '+lbFotos.length;}
function lbNext(){lbIdx=(lbIdx+1)%lbFotos.length;document.getElementById('lb-img').src=lbFotos[lbIdx];document.getElementById('lb-counter').textContent=(lbIdx+1)+' / '+lbFotos.length;}
document.addEventListener('keydown',e=>{if(!document.getElementById('lightbox').classList.contains('open'))return;if(e.key==='ArrowLeft')lbPrev();if(e.key==='ArrowRight')lbNext();if(e.key==='Escape')cerrarLightbox();});
document.getElementById('lightbox').addEventListener('click',e=>{if(e.target===document.getElementById('lightbox'))cerrarLightbox();});

renderTabla();
</script>
</body>
</html>