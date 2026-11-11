<?php
/* ════════════════════════════════════════════════════════════════
   global/territorio.php — Cartografía Electoral Unificada
   Nivel 1 (Admin):      todo el país, selector de estado
   Nivel 2 (Circ):       estados de su circunscripción
   Nivel 3 (Estatal):    su estado fijo (BD + fallback sesión)
   Nivel 4 (Regional):   su estado, resaltando secciones de su región
   Nivel 5 (Zona):       su estado, resaltando solo sus secciones
════════════════════════════════════════════════════════════════ */

if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
} else { session_start(); }

$rol_sesion  = strtolower($_SESSION['user_role']          ?? '');
$user_id     = $_SESSION['user_id']                       ?? 0;
$user_circ   = $_SESSION['user_circunscripcion']          ?? '';
$user_estado = trim($_SESSION['user_estado']              ?? '');
$user_region = $_SESSION['user_region']                   ?? '';
$user_zona   = $_SESSION['user_zona_nombre']              ?? '';

$jerarquia = [
    'admin'=>1,'administrador'=>1,'superadmin'=>1,'super admin'=>1,
    'coordinadores_circunscripcion'=>2,'coordinador_circunscripcion'=>2,'circunscripcion'=>2,
    'coordinador_estatal'=>3,
    'coordinador_regional'=>4,'regional'=>4,
    'coordinador_zona'=>5,
];
$mi_nivel = $jerarquia[$rol_sesion] ?? 6;

$permitidos = array_keys($jerarquia);
if (!in_array($rol_sesion, $permitidos)) { header('Location: /public/index.php'); exit(); }

// ── Circunscripciones ─────────────────────────────────────
$CIRCS = [
    'Circunscripción 1' => ['Baja California','Baja California Sur','Chihuahua','Durango','Jalisco','Nayarit','Sinaloa','Sonora'],
    'Circunscripción 2' => ['Aguascalientes','Coahuila','Guanajuato','Nuevo Leon','San Luis Potosi','Tamaulipas','Zacatecas'],
    'Circunscripción 3' => ['Campeche','Chiapas','Oaxaca','Quintana Roo','Tabasco','Veracruz','Yucatan'],
    'Circunscripción 4' => ['Ciudad de Mexico','Guerrero','Hidalgo','Morelos','Puebla','Tlaxcala'],
    'Circunscripción 5' => ['Colima','Estado de Mexico','Michoacan','Queretaro'],
];
$estados_circ = ($mi_nivel === 2) ? ($CIRCS[$user_circ] ?? []) : [];

// ── Catálogo de estados → archivos GeoJSON ────────────────
$geojsonPath = __DIR__ . '/../../geojson/';
$CATALOGO = [
    'Sonora'              => ['key'=>'sonora',            'c'=>[29.5,  -110.5],  'z'=>7],
    'Baja California'     => ['key'=>'bc',                'c'=>[30.5,  -115.5],  'z'=>7],
    'Baja California Sur' => ['key'=>'bcs',               'c'=>[24.5,  -111.5],  'z'=>7],
    'Chihuahua'           => ['key'=>'chihuahua',         'c'=>[28.6,  -106.1],  'z'=>7],
    'Durango'             => ['key'=>'durango',           'c'=>[24.5,  -104.7],  'z'=>7],
    'Sinaloa'             => ['key'=>'sinaloa',           'c'=>[24.8,  -107.4],  'z'=>7],
    'Nayarit'             => ['key'=>'nayarit',           'c'=>[21.75, -104.85], 'z'=>8],
    'Jalisco'             => ['key'=>'jalisco',           'c'=>[20.6,  -103.3],  'z'=>7],
    'Aguascalientes'      => ['key'=>'aguascalientes',    'c'=>[21.88, -102.29], 'z'=>9],
    'Coahuila'            => ['key'=>'coahuila',          'c'=>[27.3,  -102.0],  'z'=>7],
    'Guanajuato'          => ['key'=>'guanajuato',        'c'=>[21.0,  -101.25], 'z'=>8],
    'Nuevo Leon'          => ['key'=>'nuevo-leon',        'c'=>[25.5,  -99.9],   'z'=>7],
    'San Luis Potosi'     => ['key'=>'san-luis-potosi',   'c'=>[22.2,  -100.98], 'z'=>7],
    'Tamaulipas'          => ['key'=>'tamaulipas',        'c'=>[24.3,  -98.8],   'z'=>7],
    'Zacatecas'           => ['key'=>'zacatecas',         'c'=>[23.0,  -102.5],  'z'=>7],
    'Campeche'            => ['key'=>'campeche',          'c'=>[18.9,  -90.2],   'z'=>7],
    'Chiapas'             => ['key'=>'chiapas',           'c'=>[16.75, -92.6],   'z'=>7],
    'Oaxaca'              => ['key'=>'oaxaca',            'c'=>[17.0,  -96.7],   'z'=>7],
    'Quintana Roo'        => ['key'=>'quintana-roo',      'c'=>[19.5,  -88.0],   'z'=>7],
    'Tabasco'             => ['key'=>'tabasco',           'c'=>[18.0,  -92.8],   'z'=>8],
    'Veracruz'            => ['key'=>'veracruz',          'c'=>[19.5,  -96.9],   'z'=>7],
    'Yucatan'             => ['key'=>'yucatan',           'c'=>[20.7,  -89.1],   'z'=>8],
    'Ciudad de Mexico'    => ['key'=>'ciudad-de-mexico',  'c'=>[19.43, -99.13],  'z'=>10],
    'Guerrero'            => ['key'=>'guerrero',          'c'=>[17.55, -99.5],   'z'=>7],
    'Hidalgo'             => ['key'=>'hidalgo',           'c'=>[20.5,  -98.8],   'z'=>8],
    'Morelos'             => ['key'=>'morelos',           'c'=>[18.7,  -99.1],   'z'=>9],
    'Puebla'              => ['key'=>'puebla',            'c'=>[19.0,  -97.9],   'z'=>8],
    'Tlaxcala'            => ['key'=>'tlaxcala',          'c'=>[19.32, -98.23],  'z'=>9],
    'Colima'              => ['key'=>'colima',            'c'=>[19.12, -103.72], 'z'=>9],
    'Estado de Mexico'    => ['key'=>'estado-de-mexico',  'c'=>[19.35, -99.65],  'z'=>8],
    'Michoacan'           => ['key'=>'michoacan',         'c'=>[19.0,  -101.9],  'z'=>7],
    'Queretaro'           => ['key'=>'queretaro',         'c'=>[20.6,  -100.4],  'z'=>8],
];

// ── Determinar estados que verá este usuario ──────────────
$estados_scope = [];   // nombres de estados visibles

if ($mi_nivel === 1) {
    $estados_scope = array_keys($CATALOGO);
} elseif ($mi_nivel === 2) {
    $estados_scope = !empty($estados_circ) ? $estados_circ : array_keys($CATALOGO);
} else {
    // Niveles 3-5: un solo estado
    if ($mi_nivel === 3) {
        // Intentar obtener estado desde BD
        try {
            $dbCfg = __DIR__ . '/../../includes/database.php';
            $cfg   = file_exists($dbCfg) ? require $dbCfg : [];
            $dsn   = "mysql:host={$cfg['host']};dbname={$cfg['database']};charset=utf8mb4";
            $pdoT  = new PDO($dsn, $cfg['username'], $cfg['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
            $stT   = $pdoT->prepare("SELECT estado FROM coordinador_estatal WHERE id=? LIMIT 1");
            $stT->execute([$user_id]);
            $user_estado = trim($stT->fetchColumn() ?: $user_estado);
        } catch (Exception $e) { /* usa sesión */ }
    }
    $estados_scope = [$user_estado];
}

// ── Obtener secciones/datos territoriales para nivel 4-5 ──
$secciones_scope = [];  // nivel 4: secciones de su región; nivel 5: de su zona
$region_nombre   = '—';
$dbConn          = null;

if ($mi_nivel >= 4) {
    try {
        $dbCfg2 = __DIR__ . '/../../includes/database.php';
        $cfg2   = file_exists($dbCfg2) ? require $dbCfg2 : [];
        $dsn2   = "mysql:host={$cfg2['host']};dbname={$cfg2['database']};charset=utf8mb4";
        $dbConn = new PDO($dsn2, $cfg2['username'], $cfg2['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    } catch (Exception $e) {}
}

if ($mi_nivel === 4 && $dbConn && $user_region) {
    $st = $dbConn->prepare("SELECT nombre FROM geo_regiones WHERE id=? LIMIT 1");
    $st->execute([$user_region]); $r = $st->fetch();
    if ($r) $region_nombre = ucfirst($r['nombre']);

    $st = $dbConn->prepare("SELECT id FROM geo_zonas WHERE region_id=?");
    $st->execute([$user_region]);
    $zona_ids = $st->fetchAll(PDO::FETCH_COLUMN);

    if ($zona_ids) {
        $in = implode(',', array_fill(0, count($zona_ids), '?'));
        $st = $dbConn->prepare("SELECT DISTINCT seccion FROM zona_secciones WHERE zona_id IN ($in)");
        $st->execute($zona_ids);
        $secciones_scope = $st->fetchAll(PDO::FETCH_COLUMN);
    }
}

if ($mi_nivel === 5 && $dbConn && $user_zona) {
    $st = $dbConn->prepare("SELECT nombre_seccion FROM secciones_electorales WHERE TRIM(zona_asignada)=TRIM(?) AND activo=1");
    $st->execute([$user_zona]);
    $secciones_scope = $st->fetchAll(PDO::FETCH_COLUMN);
}

// ── Google Sheets ─────────────────────────────────────────
if (!defined('GOOGLE_SHEET_ID')) {
    define('GOOGLE_SHEET_ID',   '1PB10SQiyOBoRoD7lIoA4dhSC9ITLvixMP5VrOoIRQ-E');
    define('GOOGLE_SHEET_NAME', 'DB');
}
function getSheetUsers(): array {
    $url = 'https://docs.google.com/spreadsheets/d/'.GOOGLE_SHEET_ID.'/gviz/tq?tqx=out:csv&sheet='.GOOGLE_SHEET_NAME;
    $users = [];
    try {
        $ctx = stream_context_create(['http'=>['timeout'=>30,'user_agent'=>'Mozilla/5.0']]);
        $csv = @file_get_contents($url, false, $ctx);
        if (!$csv) return [];
        foreach (explode("\n", $csv) as $i => $line) {
            if ($i===0 || !trim($line)) continue;
            $row = array_pad(str_getcsv($line), 14, '');
            $users[] = [
                'nombre'    => trim($row[4]).' '.trim($row[5]),
                'celular'   => trim($row[1]),
                'municipio' => trim($row[6]),
                'estado'    => trim($row[7]),
                'colonia'   => trim($row[8]),
                'seccion'   => trim($row[10]),
                'rol'       => trim($row[11]),
            ];
        }
    } catch (Exception $e) {}
    return $users;
}

$allUsers = getSheetUsers();

// Filtrar por scope
$usersFiltrados = array_values(array_filter($allUsers, function($u) use ($estados_scope, $secciones_scope, $mi_nivel) {
    $estadoMatch = empty($estados_scope) || in_array(mb_strtolower(trim($u['estado']),'UTF-8'),
        array_map(fn($e) => mb_strtolower($e,'UTF-8'), $estados_scope));
    if ($mi_nivel === 4 && !empty($secciones_scope))
        return $estadoMatch && in_array($u['seccion'], $secciones_scope);
    if ($mi_nivel === 5 && !empty($secciones_scope))
        return in_array($u['seccion'], $secciones_scope);
    return $estadoMatch;
}));

$rolesCount = ['simpatizante'=>0,'militante'=>0,'voluntario'=>0,'evento'=>0,'indeciso'=>0];
foreach ($usersFiltrados as $u) {
    $r = strtolower($u['rol']);
    if     (stripos($r,'simpatizante')!==false)                                          $rolesCount['simpatizante']++;
    elseif (stripos($r,'militante')!==false)                                             $rolesCount['militante']++;
    elseif (stripos($r,'voluntario')!==false||stripos($r,'activista')!==false)       $rolesCount['voluntario']++;
    elseif (stripos($r,'evento')!==false||stripos($r,'vine')!==false)               $rolesCount['evento']++;
    else                                                                                 $rolesCount['indeciso']++;
}
$statsData = ['total'=>count($usersFiltrados),'roles'=>$rolesCount];

// ── Construir ESTADOS_DATA para JS ────────────────────────
$estadosData = [];
foreach ($estados_scope as $nombre) {
    // Buscar en catálogo ignorando mayúsculas
    $cfg = null;
    foreach ($CATALOGO as $n => $c) {
        if (mb_strtolower(trim($n),'UTF-8') === mb_strtolower(trim($nombre),'UTF-8')) {
            $cfg = $c; $nombre = $n; break;
        }
    }
    if (!$cfg) continue;
    $key = $cfg['key'];
    $munFile = $geojsonPath . 'municipio-'.$key.'.geojson';
    // fallback para sonora que no tiene guión
    if (!file_exists($munFile)) $munFile = $geojsonPath . 'municipio.geojson';
    $munData  = file_exists($munFile) ? json_decode(file_get_contents($munFile), true) : null;
    $secFile  = 'seccion-'.$key.'.geojson';
    if ($key === 'sonora') $secFile = 'seccion.geojson';
    $secPath  = $geojsonPath.$secFile;
    $secUrl   = file_exists($secPath) ? '../../geojson/'.$secFile : null;

    $estadosData[$key] = [
        'label'      => $nombre,
        'centroide'  => $cfg['c'],
        'zoom'       => $cfg['z'],
        'municipio'  => $munData,
        'seccionUrl' => $secUrl,
    ];
}

// Estado inicial para el mapa
$estadoInicial = null;
if ($mi_nivel >= 3 && !empty($estados_scope)) {
    foreach ($CATALOGO as $n => $c) {
        if (mb_strtolower(trim($n),'UTF-8') === mb_strtolower(trim($estados_scope[0]),'UTF-8')) {
            $estadoInicial = $c['key']; break;
        }
    }
}

$estadosJson = json_encode($estadosData, JSON_UNESCAPED_UNICODE);
$cfgInicial  = $estadoInicial && isset($estadosData[$estadoInicial])
    ? $estadosData[$estadoInicial]
    : ['centroide'=>[23.6345,-102.5528],'zoom'=>5];

// Header labels
$header_labels = [
    1 => 'Cartografía Electoral', 2 => 'Cartografía — '.$user_circ,
    3 => 'Cartografía — '.($estados_scope[0] ?? 'Estatal'),
    4 => 'Cartografía — Región '.$region_nombre,
    5 => 'Cartografía — Zona '.$user_zona,
];
$header_sub = $estados_scope[0] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cartografía Electoral</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
    :root{--verde:#1a7a3c;--verde-hover:#0f5228;--dark:#1a1e2a;--texto-dim:#6b7280;--gris-panel:#f3f4f6;--borde:#e5e7eb;--sidebar-w:280px;}
    *{margin:0;padding:0;box-sizing:border-box;}
    html,body{height:100%;overflow:hidden;font-family:'IBM Plex Sans','Arial',sans-serif;background:#f0f7f0;color:var(--dark);}
    /* ── LAYOUT MAPA ── */
    body{display:flex;flex-direction:column;}
    header{flex-shrink:0;height:48px;background:var(--dark);display:flex;align-items:center;justify-content:space-between;padding:0 20px;gap:16px;z-index:1000;position:relative;}
    .header-left{display:flex;align-items:center;gap:12px;}
    .logo-dot{width:10px;height:10px;border-radius:50%;background:var(--verde);}
    header h1{font-size:15px;font-weight:700;color:#fff;letter-spacing:0.02em;}
    header span{font-size:11px;color:#9ca3af;font-weight:500;}
    .region-badge{background:rgba(26,122,60,0.25);color:#4ade80;font-size:10px;font-weight:700;padding:3px 10px;border-radius:4px;letter-spacing:0.05em;}
    .btn-evento{display:flex;align-items:center;gap:7px;background:var(--verde);color:#fff;font-size:11px;font-weight:600;padding:0 14px;height:32px;border-radius:5px;border:none;cursor:pointer;font-family:inherit;transition:background 0.15s;white-space:nowrap;}
    .btn-evento:hover{background:var(--verde-hover);}
    .search-box{display:flex;align-items:center;background:#2d3142;border-radius:6px;overflow:hidden;}
    .search-box input{background:transparent;border:none;color:#fff;padding:6px 10px;font-size:12px;font-family:inherit;outline:none;width:130px;}
    .search-box input::placeholder{color:#6b7280;}
    .search-box button{background:var(--verde);border:none;color:#fff;padding:6px 10px;cursor:pointer;font-size:12px;}
    .main{flex:1;display:flex;overflow:hidden;position:relative;}
    #map{flex:1;z-index:1;}
    /* ── PANEL LATERAL ── */
    .panel{width:var(--sidebar-w);background:#fff;border-right:1px solid var(--borde);overflow-y:auto;flex-shrink:0;z-index:10;display:flex;flex-direction:column;}
    .panel-section{padding:16px;border-bottom:1px solid var(--borde);}
    .panel-label{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;color:var(--texto-dim);margin-bottom:10px;}
    .stats-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;}
    .stat-box{background:var(--gris-panel);border-radius:8px;padding:10px 12px;}
    .stat-num{font-size:22px;font-weight:900;color:var(--dark);}
    .stat-label{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:var(--texto-dim);margin-top:2px;}
    .layer-item{display:flex;align-items:center;justify-content:space-between;padding:8px 0;cursor:pointer;gap:10px;border-bottom:1px solid var(--gris-panel);}
    .layer-item:last-child{border-bottom:none;}
    .layer-left{display:flex;align-items:center;gap:8px;}
    .layer-color{width:14px;height:14px;border-radius:3px;flex-shrink:0;}
    .layer-name{font-size:12px;font-weight:600;color:var(--dark);}
    .toggle{width:32px;height:18px;border-radius:9px;background:#d1d5db;position:relative;transition:background 0.2s;flex-shrink:0;}
    .toggle.on{background:var(--verde);}
    .toggle::after{content:'';position:absolute;top:2px;left:2px;width:14px;height:14px;border-radius:50%;background:#fff;transition:left 0.2s;}
    .toggle.on::after{left:16px;}
    .ev-count-badge{background:var(--verde);color:#fff;font-size:9px;font-weight:700;padding:1px 5px;border-radius:10px;margin-left:4px;}
    /* ── INFO PANEL ── */
    #info-panel{padding:16px;display:flex;flex-direction:column;gap:2px;border-top:2px solid var(--verde);margin-top:auto;}
    #info-panel:not(.visible){display:none;}
    .info-title{font-size:18px;font-weight:900;color:var(--dark);margin-bottom:6px;}
    .info-row{display:flex;justify-content:space-between;align-items:center;padding:4px 0;font-size:12px;border-bottom:1px solid var(--gris-panel);}
    .info-key{color:var(--texto-dim);font-weight:600;}
    .info-val{font-weight:700;color:var(--dark);}
    .info-val.verde{color:var(--verde);}
    .db-sep{border:none;border-top:1px solid var(--borde);margin:10px 0;}
    .db-section-label{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--verde);margin:10px 0 4px;}
    .db-progress-bg{height:6px;border-radius:3px;background:#e5e7eb;overflow:hidden;margin:4px 0;}
    .db-progress-fill{height:100%;border-radius:3px;background:var(--verde);transition:width 0.5s;}
    .db-progress-meta{display:flex;justify-content:space-between;font-size:10px;color:var(--texto-dim);font-weight:600;margin-top:2px;}
    .edad-chips{display:flex;flex-wrap:wrap;gap:4px;margin:4px 0;}
    .edad-chip{font-size:9px;font-weight:700;padding:2px 6px;border-radius:4px;background:#f3f4f6;color:#374151;}
    .act-mini-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:6px;margin:6px 0;}
    .act-mini-card{background:var(--gris-panel);border-radius:6px;padding:6px;text-align:center;}
    .act-mini-num{font-size:16px;font-weight:900;color:var(--verde);}
    .act-mini-lbl{font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;color:var(--texto-dim);}
    .db-open-field{background:var(--gris-panel);border-radius:6px;padding:8px;margin-top:6px;}
    .db-open-label{font-size:9px;font-weight:700;text-transform:uppercase;color:var(--texto-dim);margin-bottom:3px;}
    .db-open-text{font-size:11px;color:var(--dark);line-height:1.4;}
    .db-link-btn{margin-top:10px;width:100%;background:rgba(26,122,60,0.08);color:var(--verde);border:1px solid rgba(26,122,60,0.25);border-radius:6px;padding:7px;font-size:11px;font-weight:700;cursor:pointer;font-family:inherit;transition:background 0.15s;}
    .db-link-btn:hover{background:rgba(26,122,60,0.15);}
    .btn-ver-detalles{margin-top:8px;width:100%;background:rgba(26,122,60,0.08);color:var(--verde);border:1px solid rgba(26,122,60,0.25);border-radius:6px;padding:7px;font-size:11px;font-weight:700;cursor:pointer;font-family:inherit;}
    /* ── ESTADO SELECTOR (header) ── */
    .estado-selector-wrap{display:flex;align-items:center;gap:8px;}
    .estado-selector-label{font-size:11px;color:#9ca3af;font-weight:600;}
    .estado-select{background:#2d3142;color:#fff;border:1px solid #374151;border-radius:6px;padding:5px 10px;font-size:12px;font-family:inherit;outline:none;cursor:pointer;}
    /* ── LABELS SECCIONES ── */
    .lbl-seccion{font-size:8px;font-weight:800;color:#1a1e2a;background:rgba(255,255,255,0.85);padding:1px 3px;border-radius:2px;white-space:nowrap;pointer-events:none;}
    /* ── EVENTOS MARKERS ── */
    .evento-marker{background:transparent!important;border:none!important;}
    .evento-pin{display:flex;flex-direction:column;align-items:center;}
    .evento-pin-icon{width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:12px;box-shadow:0 2px 6px rgba(0,0,0,0.25);}
    .evento-pin-label{font-size:8px;font-weight:700;color:#1a1e2a;background:rgba(255,255,255,0.9);padding:1px 4px;border-radius:3px;margin-top:2px;white-space:nowrap;}
    /* ── MODALES ── */
    .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:3000;align-items:center;justify-content:center;padding:20px;}
    .modal-overlay.open{display:flex;}
    .modal{background:#fff;border-radius:16px;width:100%;max-width:560px;max-height:88vh;overflow-y:auto;padding:32px;position:relative;}
    .modal-close{position:absolute;top:16px;right:16px;background:#f3f4f6;border:none;border-radius:50%;width:32px;height:32px;cursor:pointer;font-size:14px;color:#6b7280;display:flex;align-items:center;justify-content:center;}
    .modal-close:hover{background:#fee2e2;color:#d32f2f;}
    .modal-title{font-size:20px;font-weight:800;margin-bottom:4px;}
    .form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;}
    .form-row.full{grid-template-columns:1fr;}
    .form-group label{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#6b7280;margin-bottom:4px;}
    .form-group input,.form-group select,.form-group textarea{width:100%;padding:8px 10px;border:1.5px solid #e5e7eb;border-radius:7px;font-size:13px;font-family:inherit;outline:none;transition:border 0.2s;background:#f9fafb;color:#1a1e2a;}
    .form-group input:focus,.form-group select:focus,.form-group textarea:focus{border-color:var(--verde);background:#fff;}
    .form-group textarea{resize:vertical;min-height:70px;}
    .eje-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:14px;}
    .eje-btn-ag{padding:10px;border:2px solid #e5e7eb;border-radius:9px;font-size:11px;font-weight:700;cursor:pointer;text-align:center;background:#f9fafb;color:#6b7280;transition:0.15s;font-family:inherit;}
    .eje-btn-ag.sel-voto{background:#f0fdf4;border-color:#1a7a3c;color:#1a7a3c;}
    .eje-btn-ag.sel-comunicacion{background:#eff6ff;border-color:#2196f3;color:#1a73e8;}
    .eje-btn-ag.sel-organizacion{background:#faf5ff;border-color:#8b00d4;color:#8b00d4;}
    .seccion-badge{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:6px;font-size:12px;font-weight:700;background:rgba(26,122,60,0.1);color:var(--verde);border:1px solid rgba(26,122,60,0.3);cursor:default;}
    .seccion-badge.empty{background:#f3f4f6;color:#9ca3af;border-color:#e5e7eb;}
    .btn-pick-sec{background:none;border:1px solid #e5e7eb;border-radius:6px;padding:5px 10px;font-size:11px;font-weight:700;cursor:pointer;color:#374151;font-family:inherit;transition:all 0.15s;margin-top:4px;}
    .btn-pick-sec:hover{background:var(--verde);color:#fff;border-color:var(--verde);}
    .btn-save-ev{width:100%;padding:12px;background:var(--verde);color:#fff;font-size:14px;font-weight:700;border:none;border-radius:9px;cursor:pointer;font-family:inherit;transition:background 0.15s;margin-top:8px;}
    .btn-save-ev:hover{background:var(--verde-hover);}
    /* Det evento */
    .det-eje-badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;margin-bottom:10px;}
    .det-row{display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid #f3f4f6;font-size:13px;}
    .det-key{color:#6b7280;font-weight:600;}
    .det-val{font-weight:700;}
    /* Modal seccion completa */
    .sec-modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:4000;align-items:center;justify-content:center;padding:20px;}
    .sec-modal-overlay.open{display:flex;}
    .sec-modal{background:#fff;border-radius:16px;width:100%;max-width:640px;max-height:90vh;overflow-y:auto;padding:32px;position:relative;}
    .sec-modal-close{position:absolute;top:16px;right:16px;background:#f3f4f6;border:none;border-radius:50%;width:32px;height:32px;cursor:pointer;font-size:14px;color:#6b7280;display:flex;align-items:center;justify-content:center;}
    .sec-section-title{font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:0.08em;color:var(--verde);margin:16px 0 8px;padding-bottom:4px;border-bottom:2px solid #f0f7f0;}
    .sec-det-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:10px;}
    .sec-det-item label{display:block;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#6b7280;margin-bottom:3px;}
    .sec-det-item span{font-size:14px;font-weight:700;color:#1a1e2a;}
    .sec-act-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;}
    .sec-act-card{background:#f3f4f6;border-radius:8px;padding:10px;text-align:center;}
    .sec-act-num{font-size:22px;font-weight:900;color:var(--verde);}
    .sec-act-lbl{font-size:9px;font-weight:700;text-transform:uppercase;color:#6b7280;}
    .sec-modal-progress-bg{height:8px;border-radius:4px;background:#e5e7eb;overflow:hidden;margin:6px 0;}
    .sec-modal-progress-fill{height:100%;border-radius:4px;background:var(--verde);transition:width 0.5s;}
    .sec-modal-progress-lbl{display:flex;justify-content:space-between;font-size:11px;font-weight:700;color:#6b7280;}
    .sec-open-field{background:#f3f4f6;border-radius:6px;padding:8px;margin-top:6px;}
    .sec-open-label{font-size:9px;font-weight:700;text-transform:uppercase;color:#6b7280;margin-bottom:3px;}
    .sec-open-text{font-size:12px;color:#1a1e2a;line-height:1.4;}
    .sec-modal-footer{display:flex;justify-content:flex-end;margin-top:20px;padding-top:16px;border-top:1px solid #e5e7eb;}
    .sec-modal-btn-close{background:#f3f4f6;color:#374151;border:none;border-radius:8px;padding:8px 18px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;}
    @media(max-width:768px){.panel{width:220px;} header h1{font-size:13px;}}
    </style>
</head>
<body>
<?php
$path_sb = __DIR__ . '/../../includes/components/';
if      ($mi_nivel === 1) @include $path_sb . 'sidebar_admin.php';
elseif  ($mi_nivel === 2) @include $path_sb . 'sidebar_circunscripcion.php';
elseif  ($mi_nivel === 3) @include $path_sb . 'sidebar_ce.php';
elseif  ($mi_nivel === 4) @include $path_sb . 'sidebar_regional.php';
elseif  ($mi_nivel === 5) @include $path_sb . 'sidebar_cz.php';
?>

<header>
    <div class="header-left">
        <div class="logo-dot"></div>
        <h1><?php echo htmlspecialchars($header_labels[$mi_nivel] ?? 'Cartografía Electoral'); ?></h1>
        <?php if ($mi_nivel <= 2): ?>
        <span id="header-estado-span">— Selecciona un estado</span>
        <?php elseif ($mi_nivel === 4): ?>
        <span class="region-badge">REGIÓN <?php echo htmlspecialchars(strtoupper($region_nombre)); ?></span>
        <?php else: ?>
        <span><?php echo htmlspecialchars($header_sub); ?></span>
        <?php endif; ?>
    </div>
    <div style="display:flex;align-items:center;gap:12px;">
        <?php if ($mi_nivel <= 2): ?>
        <div class="estado-selector-wrap">
            <span class="estado-selector-label">Estado</span>
            <select class="estado-select" id="estado-select" onchange="cambiarEstado(this.value)">
                <option value="">— Seleccionar estado —</option>
                <?php foreach ($estadosData as $key => $cfg): ?>
                <option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($cfg['label']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php elseif ($mi_nivel === 5): ?>
        <div class="search-box">
            <input type="number" id="buscar-seccion-input" placeholder="Buscar sección…" onkeypress="if(event.key==='Enter') buscarSeccionMapa()">
            <button onclick="buscarSeccionMapa()"><i class="fas fa-search"></i></button>
        </div>
        <?php endif; ?>
        <button class="btn-evento" onclick="abrirModal()">
            <i class="fa-solid fa-calendar-plus"></i> Agregar Evento
        </button>
    </div>
</header>

<div class="main">
    <aside class="panel">
        <div class="panel-section">
            <div class="panel-label">Estadísticas</div>
            <div class="stats-grid">
                <div class="stat-box">
                    <div class="stat-num" id="stat-secciones"><?php echo $mi_nivel >= 4 ? count($secciones_scope) : '—'; ?></div>
                    <div class="stat-label"><?php echo $mi_nivel === 5 ? 'Secciones Zona' : 'Secciones'; ?></div>
                </div>
                <div class="stat-box">
                    <div class="stat-num" id="stat-municipios">—</div>
                    <div class="stat-label">Municipios</div>
                </div>
            </div>
        </div>

        <div class="panel-section">
            <div class="panel-label">Capas</div>
            <?php if ($mi_nivel <= 3): ?>
            <div class="layer-item" id="row-municipio" onclick="toggleCapa('municipio')" style="display:none;">
                <div class="layer-left">
                    <div class="layer-color" style="background:#e8f0eb;border:2px solid #4a7c5e;"></div>
                    <span class="layer-name" id="label-municipio">Municipios</span>
                </div>
                <div class="toggle on" id="toggle-municipio"></div>
            </div>
            <?php endif; ?>
            <div class="layer-item" id="row-secciones" onclick="toggleCapa('secciones')" <?php echo $mi_nivel <= 2 ? 'style="display:none;"' : ''; ?>>
                <div class="layer-left">
                    <div class="layer-color" style="background:transparent;border:2px solid #22a050;"></div>
                    <span class="layer-name" id="label-secciones">
                        <?php echo $mi_nivel === 5 ? 'Tu Territorio (Zona)' : 'Secciones Electorales'; ?>
                    </span>
                </div>
                <div class="toggle on" id="toggle-secciones"></div>
            </div>
            <?php if ($mi_nivel === 4): ?>
            <div class="layer-item" id="row-region" onclick="toggleCapa('region')" style="display:none;">
                <div class="layer-left">
                    <div class="layer-color" style="background:rgba(26,122,60,0.3);border:2px solid #1a7a3c;"></div>
                    <span class="layer-name">Secciones de la Región</span>
                </div>
                <div class="toggle on" id="toggle-region"></div>
            </div>
            <?php endif; ?>
            <div class="layer-item" id="row-labels" onclick="toggleCapa('labels')" <?php echo $mi_nivel <= 2 ? 'style="display:none;"' : ''; ?>>
                <div class="layer-left">
                    <div class="layer-color" style="background:#1a7a3c;border-radius:50%;"></div>
                    <span class="layer-name">Números de Sección</span>
                </div>
                <div class="toggle on" id="toggle-labels"></div>
            </div>
            <div class="layer-item" id="row-eventos" onclick="toggleCapaEventos()">
                <div class="layer-left">
                    <div class="layer-color" style="background:#1a7a3c;border-radius:50%;"></div>
                    <span class="layer-name">Eventos <span class="ev-count-badge" id="ev-count-badge">0</span></span>
                </div>
                <div class="toggle on" id="toggle-eventos"></div>
            </div>
            <?php if ($mi_nivel <= 2): ?>
            <div id="capas-placeholder" style="font-size:11px;color:var(--texto-dim);padding:6px 0;">
                Selecciona un estado para ver las capas disponibles.
            </div>
            <?php endif; ?>
        </div>

        <!-- Panel info sección -->
        <div id="info-panel">
            <div class="panel-label">Sección seleccionada</div>
            <div class="info-title" id="info-seccion">—</div>
            <div class="info-row"><span class="info-key">Dist. Federal</span><span class="info-val" id="info-df">—</span></div>
            <div class="info-row"><span class="info-key">Dist. Local</span><span class="info-val" id="info-dl">—</span></div>
            <div class="info-row"><span class="info-key">Municipio</span><span class="info-val" id="info-mun">—</span></div>
            <div class="info-row"><span class="info-key">Entidad</span><span class="info-val" id="info-ent">—</span></div>
            <div class="info-row"><span class="info-key">Tipo</span><span class="info-val" id="info-tipo">—</span></div>
            <div id="db-spinner" style="display:none;text-align:center;padding:12px 0;">
                <i class="fas fa-spinner fa-spin" style="color:var(--verde);font-size:15px;"></i>
                <div style="font-size:9px;color:var(--texto-dim);margin-top:4px;">Cargando datos…</div>
            </div>
            <div id="db-no-data" style="display:none;font-size:11px;color:var(--texto-dim);background:var(--gris-panel);border-radius:5px;padding:8px;text-align:center;margin-top:8px;">
                <i class="fas fa-info-circle"></i> Sin datos registrados
            </div>
            <div id="db-panel" style="display:none;">
                <hr class="db-sep">
                <div class="db-section-label"><i class="fas fa-map-pin"></i> Zona</div>
                <div class="info-row"><span class="info-key">Región</span><span class="info-val" id="db-region">—</span></div>
                <div class="info-row"><span class="info-key">Zona</span><span class="info-val" id="db-zona">—</span></div>
                <div class="db-section-label"><i class="fas fa-users"></i> Lista Nominal</div>
                <div class="info-row"><span class="info-key">Total</span><span class="info-val verde" id="db-nominal">—</span></div>
                <div class="edad-chips" id="db-edad-chips"></div>
                <div class="db-section-label"><i class="fas fa-address-book"></i> Contactos</div>
                <div class="info-row"><span class="info-key">Meta</span><span class="info-val" id="db-meta">—</span></div>
                <div class="info-row"><span class="info-key">Conseguidos</span><span class="info-val verde" id="db-contactos">—</span></div>
                <div class="db-progress-bg"><div class="db-progress-fill" id="db-progress-fill" style="width:0%"></div></div>
                <div class="db-progress-meta"><span id="db-pct">0%</span><span id="db-rem">0 restantes</span></div>
                <div class="db-section-label"><i class="fas fa-calendar-check"></i> Resultados</div>
                <div class="info-row"><span class="info-key">Eventos realizados</span><span class="info-val verde" id="db-eventos">—</span></div>
                <div class="info-row"><span class="info-key">Meta de votos</span><span class="info-val verde" id="db-votos">—</span></div>
                <div class="db-section-label"><i class="fas fa-bolt"></i> Activaciones</div>
                <div class="act-mini-grid">
                    <div class="act-mini-card"><div class="act-mini-num" id="db-act-total">—</div><div class="act-mini-lbl">Total</div></div>
                    <div class="act-mini-card"><div class="act-mini-num" id="db-act-con">—</div><div class="act-mini-lbl">Con Cand.</div></div>
                    <div class="act-mini-card"><div class="act-mini-num" id="db-act-sin">—</div><div class="act-mini-lbl">Sin Cand.</div></div>
                </div>
                <div id="db-act-lista-wrap" style="display:none;margin-top:6px;"><div id="db-act-lista"></div></div>
                <div id="db-eventos-wrap" style="display:none;margin-top:8px;">
                    <div class="db-section-label"><i class="fas fa-calendar-check"></i> Eventos de Agenda</div>
                    <div id="db-eventos-lista"></div>
                </div>
                <div class="db-section-label"><i class="fas fa-align-left"></i> Información</div>
                <div class="db-open-field" id="db-nec-wrap" style="display:none;"><div class="db-open-label">Principales Necesidades</div><div class="db-open-text" id="db-necesidades"></div></div>
                <div class="db-open-field" id="db-enf-wrap" style="display:none;"><div class="db-open-label">Enfoque</div><div class="db-open-text" id="db-enfoque"></div></div>
                <div class="db-open-field" id="db-per-wrap" style="display:none;"><div class="db-open-label">Perfil Socioeconómico</div><div class="db-open-text" id="db-perfil"></div></div>
                <button id="db-link" class="db-link-btn" type="button" onclick="abrirModalSeccionCompleta()">
                    <i class="fas fa-external-link-alt"></i> Ver detalle completo
                </button>
            </div>
        </div>
    </aside>

    <div id="map"></div>
</div>

<!-- MODAL AGREGAR EVENTO -->
<div class="modal-overlay" id="modal-overlay" onclick="if(event.target===this)cerrarModal()">
    <div class="modal">
        <button class="modal-close" onclick="cerrarModal()"><i class="fas fa-times"></i></button>
        <h2 class="modal-title" id="m-modal-title">Nuevo Evento</h2>
        <p style="font-size:13px;color:#6b7280;margin-bottom:20px;">Registra un evento en la agenda. La sección se toma del mapa.</p>
        <div style="margin-bottom:14px;">
            <label style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#6b7280;display:block;margin-bottom:6px;">Sección Electoral</label>
            <div><span class="seccion-badge empty" id="seccion-badge-text"><i class="fa-solid fa-map-marker-alt"></i> Ninguna sección seleccionada</span></div>
            <button type="button" class="btn-pick-sec" onclick="activarSeleccionSeccion()" style="margin-top:6px;"><i class="fas fa-map-marked-alt"></i> Seleccionar en mapa</button>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Estado</label><input type="text" id="m-estado" readonly style="background:#f3f4f6;"></div>
            <div class="form-group"><label>Municipio</label><input type="text" id="m-municipio" readonly style="background:#f3f4f6;"></div>
        </div>
        <div class="form-row full">
            <div class="form-group"><label>Título del Evento *</label><input type="text" id="m-titulo" placeholder="Ej. Caravana Verde"></div>
        </div>
        <label style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#6b7280;display:block;margin-bottom:6px;">Eje *</label>
        <div class="eje-grid">
            <button class="eje-btn-ag" data-eje="voto"         onclick="seleccionarEjeM('voto')">🗳️ Voto/<br>Territorio</button>
            <button class="eje-btn-ag" data-eje="comunicacion" onclick="seleccionarEjeM('comunicacion')">📣 Comu-<br>nicación</button>
            <button class="eje-btn-ag" data-eje="organizacion" onclick="seleccionarEjeM('organizacion')">⚙️ Organi-<br>zación</button>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Subtipo</label>
                <select id="m-subtipo"><option value="">— Selecciona un eje primero —</option></select>
            </div>
            <div class="form-group"><label>Fecha *</label><input type="date" id="m-fecha"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Inicio</label><input type="time" id="m-inicio" oninput="calcHorasM()"></div>
            <div class="form-group"><label>Fin</label><input type="time" id="m-fin" oninput="calcHorasM()"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Horas (auto)</label><input type="number" id="m-horas" step="0.5" placeholder="1"></div>
            <div class="form-group"><label>Responsable</label><input type="text" id="m-responsable" placeholder="Nombre"></div>
        </div>
        <div class="form-row full">
            <div class="form-group"><label>Región</label><input type="text" id="m-region" placeholder="Opcional"></div>
        </div>
        <div class="form-row full">
            <div class="form-group"><label>Zona</label><input type="text" id="m-zona" placeholder="Opcional"></div>
        </div>
        <div class="form-row full">
            <div class="form-group"><label>Notas</label><textarea id="m-notas" placeholder="Detalles adicionales…"></textarea></div>
        </div>
        <button class="btn-save-ev" id="m-save-btn" onclick="guardarEvento()"><i class="fas fa-check"></i> Guardar Evento</button>
    </div>
</div>

<!-- MODAL DETALLE EVENTO -->
<div class="modal-overlay" id="modal-det" onclick="if(event.target===this)cerrarModalDet()">
    <div class="modal" style="max-width:460px;">
        <button class="modal-close" onclick="cerrarModalDet()"><i class="fas fa-times"></i></button>
        <h2 class="modal-title" id="det-titulo">—</h2>
        <span class="det-eje-badge" id="det-eje-badge">—</span>
        <div class="det-row"><span class="det-key">Subtipo</span><span class="det-val" id="det-subtipo">—</span></div>
        <div class="det-row"><span class="det-key">Fecha</span><span class="det-val" id="det-fecha">—</span></div>
        <div class="det-row"><span class="det-key">Horario</span><span class="det-val" id="det-horario">—</span></div>
        <div class="det-row"><span class="det-key">Horas</span><span class="det-val" id="det-horas">—</span></div>
        <div class="det-row"><span class="det-key">Estado</span><span class="det-val" id="det-estado">—</span></div>
        <div class="det-row"><span class="det-key">Municipio</span><span class="det-val" id="det-municipio">—</span></div>
        <div class="det-row"><span class="det-key">Sección</span><span class="det-val" id="det-seccion">—</span></div>
        <div class="det-row"><span class="det-key">Responsable</span><span class="det-val" id="det-responsable">—</span></div>
        <div style="margin-top:12px;background:#f3f4f6;border-radius:8px;padding:10px;">
            <div style="font-size:9px;font-weight:700;text-transform:uppercase;color:#6b7280;margin-bottom:4px;">Notas</div>
            <div style="font-size:13px;color:#1a1e2a;line-height:1.5;" id="det-notas">—</div>
        </div>
    </div>
</div>

<!-- MODAL DETALLE SECCIÓN COMPLETA -->
<div class="sec-modal-overlay" id="modal-seccion-completa" onclick="if(event.target===this)cerrarModalSeccion()">
    <div class="sec-modal">
        <button class="sec-modal-close" onclick="cerrarModalSeccion()"><i class="fas fa-times"></i></button>
        <h2 style="font-size:22px;font-weight:900;margin-bottom:4px;" id="sec-det-titulo">—</h2>
        <div style="font-size:13px;color:#6b7280;margin-bottom:20px;" id="sec-det-ubicacion">—</div>
        <div class="sec-section-title"><i class="fas fa-map-marker-alt"></i> Ubicación</div>
        <div class="sec-det-grid">
            <div class="sec-det-item"><label>Sección</label><span id="sdet-seccion">—</span></div>
            <div class="sec-det-item"><label>Municipio</label><span id="sdet-municipio">—</span></div>
            <div class="sec-det-item"><label>Distrito Federal</label><span id="sdet-df">—</span></div>
            <div class="sec-det-item"><label>Distrito Local</label><span id="sdet-dl">—</span></div>
            <div class="sec-det-item"><label>Región</label><span id="sdet-region">—</span></div>
            <div class="sec-det-item"><label>Zona</label><span id="sdet-zona">—</span></div>
        </div>
        <div class="sec-section-title"><i class="fas fa-users"></i> Lista Nominal</div>
        <div class="sec-det-grid" style="grid-template-columns:1fr 1fr 1fr;">
            <div class="sec-det-item"><label><i class="fas fa-users" style="color:#6b7280;"></i> Total</label><span id="sdet-padron" style="font-size:20px;font-weight:900;">—</span></div>
            <div class="sec-det-item"><label><i class="fas fa-mars" style="color:#3b82f6;"></i> Hombres</label><span id="sdet-hombres" style="font-size:20px;font-weight:900;color:#3b82f6;">—</span></div>
            <div class="sec-det-item"><label><i class="fas fa-venus" style="color:#ec4899;"></i> Mujeres</label><span id="sdet-mujeres" style="font-size:20px;font-weight:900;color:#ec4899;">—</span></div>
        </div>
        <div class="sec-section-title"><i class="fas fa-address-book"></i> Contactos y Avance</div>
        <div class="sec-det-grid" style="margin-bottom:8px;">
            <div class="sec-det-item"><label>Meta de Contactos</label><span id="sdet-meta" style="font-size:20px;font-weight:900;">—</span></div>
            <div class="sec-det-item"><label>Contactos Conseguidos</label><span id="sdet-contactos" style="font-size:20px;font-weight:900;color:#1a7a3c;">—</span></div>
        </div>
        <div class="sec-modal-progress-bg"><div class="sec-modal-progress-fill" id="sdet-prog-fill" style="width:0%"></div></div>
        <div class="sec-modal-progress-lbl"><span id="sdet-prog-pct">0%</span><span id="sdet-prog-rem">0 restantes</span></div>
        <div class="sec-section-title"><i class="fas fa-calendar-check"></i> Resultados</div>
        <div class="sec-det-grid">
            <div class="sec-det-item"><label>Eventos Realizados</label><span id="sdet-eventos">—</span></div>
            <div class="sec-det-item"><label>Metas de Votos</label><span id="sdet-votos">—</span></div>
        </div>
        <div class="sec-section-title"><i class="fas fa-bolt"></i> Activaciones</div>
        <div class="sec-act-grid">
            <div class="sec-act-card"><div class="sec-act-num" id="sdet-act-total">—</div><div class="sec-act-lbl">Total</div></div>
            <div class="sec-act-card"><div class="sec-act-num" id="sdet-act-con">—</div><div class="sec-act-lbl">Con Candidato</div></div>
            <div class="sec-act-card"><div class="sec-act-num" id="sdet-act-sin">—</div><div class="sec-act-lbl">Sin Candidato</div></div>
        </div>
        <div id="sdet-act-lista-wrap" style="display:none;margin-top:6px;"><div id="sdet-act-lista"></div></div>
        <div id="sdet-eventos-wrap" style="display:none;margin-top:10px;">
            <div class="sec-section-title"><i class="fas fa-calendar-check"></i> Eventos de Agenda</div>
            <div id="sdet-eventos-lista"></div>
        </div>
        <div id="sdet-info-wrap" style="display:none;">
            <div class="sec-section-title"><i class="fas fa-align-left"></i> Información</div>
            <div class="sec-open-field" id="sdet-nec-wrap" style="display:none;"><div class="sec-open-label">Principales Necesidades</div><div class="sec-open-text" id="sdet-necesidades"></div></div>
            <div class="sec-open-field" id="sdet-enf-wrap" style="display:none;"><div class="sec-open-label">Enfoque</div><div class="sec-open-text" id="sdet-enfoque"></div></div>
            <div class="sec-open-field" id="sdet-per-wrap" style="display:none;"><div class="sec-open-label">Perfil Socioeconómico</div><div class="sec-open-text" id="sdet-perfil"></div></div>
        </div>
        <div class="sec-modal-footer"><button class="sec-modal-btn-close" onclick="cerrarModalSeccion()"><i class="fas fa-times"></i> Cerrar</button></div>
    </div>
</div>

<script>
// ── Datos PHP → JS ────────────────────────────────────────
const MI_NIVEL         = <?php echo $mi_nivel; ?>;
const ESTADOS_DATA     = <?php echo $estadosJson; ?>;
const SECCIONES_SCOPE  = <?php echo json_encode(array_values($secciones_scope), JSON_UNESCAPED_UNICODE); ?>;
const ESTADO_INICIAL   = <?php echo json_encode($estadoInicial); ?>;
const ESTADO_LABEL     = <?php echo json_encode($estados_scope[0] ?? ''); ?>;

const MEXICO_BOUNDS = L.latLngBounds(L.latLng(14.5,-118.5), L.latLng(32.7,-86.7));

const map = L.map('map', {
    zoomControl:true, attributionControl:false,
    minZoom:5, maxZoom:19,
    maxBounds:MEXICO_BOUNDS, maxBoundsViscosity:1.0
}).setView([<?php echo $cfgInicial['centroide'][0]; ?>, <?php echo $cfgInicial['centroide'][1]; ?>], <?php echo $cfgInicial['zoom'] ?? 5; ?>);

L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png',{maxZoom:19}).addTo(map);

// ── Capas ─────────────────────────────────────────────────
let layerEstadoSilueta = null;
let layerMunicipio     = null;
let layerSecciones     = null;
let layerLabels        = null;
let layerRegion        = null;
let seccionActiva      = null;
let municipioActivo    = null;
let estadoActual       = null;
let seccionDetalleActual = null;

const capasVisibles = { municipio:true, secciones:true, labels:true, region:true };

function limpiarCapas() {
    [layerEstadoSilueta,layerMunicipio,layerSecciones,layerLabels,layerRegion]
        .forEach(l => { if(l) map.removeLayer(l); });
    layerEstadoSilueta=layerMunicipio=layerSecciones=layerLabels=layerRegion=null;
    seccionActiva=municipioActivo=null;
    document.getElementById('info-panel').classList.remove('visible');
}

function cargarSilueta(munData) {
    if (!munData) return;
    layerEstadoSilueta = L.geoJSON(munData, {
        style:{color:'#1a7a3c',weight:2,fillColor:'#e8f5ec',fillOpacity:0.25},
        interactive:false
    }).addTo(map);
    map.fitBounds(layerEstadoSilueta.getBounds(),{padding:[24,24]});
}

function cargarMunicipios(munData) {
    if (!munData) return;
    municipioActivo=null;
    layerMunicipio = L.geoJSON(munData, {
        style:{color:'#4a7c5e',weight:1.2,fillColor:'#e8f0eb',fillOpacity:0.30,dashArray:'4,4'},
        onEachFeature:(feature,layer)=>{
            const p=feature.properties;
            const nombre=p.nombre_mun||p.NOM_MUN||p.nom_mun||p.NOMBRE||p.nombre||p.municipio||'—';
            layer.on('mouseover',function(){if(municipioActivo!==this){this.setStyle({fillOpacity:0.55,color:'#1a7a3c',weight:2,dashArray:null});this.bringToFront();}});
            layer.on('mouseout',function(){if(municipioActivo!==this)layerMunicipio.resetStyle(this);});
            layer.on('click',function(e){
                if(municipioActivo&&municipioActivo!==this)layerMunicipio.resetStyle(municipioActivo);
                this.setStyle({fillColor:'#1a7a3c',fillOpacity:0.45,color:'#0f5228',weight:2,dashArray:null});
                this.bringToFront(); municipioActivo=this; L.DomEvent.stopPropagation(e);
            });
            layer.bindTooltip(`<span style="font-size:11px;font-weight:600;color:#1a1e2a;">${nombre}</span>`,{sticky:true,direction:'top',offset:[0,-8],opacity:0.95});
        }
    }).addTo(map);
    if (!capasVisibles.municipio) map.removeLayer(layerMunicipio);
    map.fitBounds(layerMunicipio.getBounds(),{padding:[24,24]});
    document.getElementById('stat-municipios').textContent = munData.features ? munData.features.length : '—';
}

function cargarSecciones(secData) {
    if (!secData||!secData.features||!secData.features.length) return;

    layerSecciones = L.geoJSON(secData, {
        style:feature => {
            const sec=String(feature.properties.seccion);
            // Niveles 4-5: resaltar solo las de su scope
            if (SECCIONES_SCOPE.length > 0) {
                const enScope = SECCIONES_SCOPE.includes(sec);
                if (MI_NIVEL===4) return enScope
                    ? {color:'#1a7a3c',weight:1.5,fillColor:'#1a7a3c',fillOpacity:0.10}
                    : {color:'#22a050',weight:0.8,fillColor:'#fff',fillOpacity:0.01};
                if (MI_NIVEL===5) return enScope
                    ? {color:'#22a050',weight:1.5,fillColor:'#22a050',fillOpacity:0.18}
                    : {color:'#e0e0e0',weight:0.5,fillColor:'#fff',fillOpacity:0.0};
            }
            return {color:'#22a050',weight:1,fillColor:'#fff',fillOpacity:0.01};
        },
        onEachFeature:(feature,layer)=>{
            const p=feature.properties;
            const sec=String(p.seccion);
            // Nivel 5: solo interactivas las de su zona
            if (MI_NIVEL===5 && SECCIONES_SCOPE.length>0 && !SECCIONES_SCOPE.includes(sec)) {
                layer.interactive=false; return;
            }
            layer.on('click',function(e){
                if(seccionActiva) layerSecciones.resetStyle(seccionActiva);
                this.setStyle({fillColor:'#1a7a3c',fillOpacity:0.45,color:'#0f5228',weight:2});
                this.bringToFront(); seccionActiva=this;
                document.getElementById('info-panel').classList.add('visible');
                document.getElementById('info-seccion').textContent=`SEC. ${p.seccion}`;
                document.getElementById('info-df').textContent  =`Distrito ${p.distrito_federal||p.distrito_f||'—'}`;
                document.getElementById('info-dl').textContent  =`Distrito ${p.distrito_local||p.distrito_l||'—'}`;
                document.getElementById('info-mun').textContent =p.nombre_mun||`Mun. ${p.municipio||'—'}`;
                document.getElementById('info-ent').textContent =p.nombre_ent||`Ent. ${p.entidad||'—'}`;
                document.getElementById('info-tipo').textContent=p.tipo==1?'Urbana':p.tipo==2?'Rural':`Tipo ${p.tipo||'—'}`;
                cargarDatosSeccion(p.seccion);
                L.DomEvent.stopPropagation(e);
            });
        }
    }).addTo(map);

    if (!capasVisibles.secciones) map.removeLayer(layerSecciones);

    // Capa región (nivel 4)
    if (MI_NIVEL===4 && SECCIONES_SCOPE.length>0) {
        layerRegion = L.geoJSON(secData, {
            filter: f => SECCIONES_SCOPE.includes(String(f.properties.seccion)),
            style: {color:'#1a7a3c',weight:2,fillColor:'rgba(26,122,60,0.35)',fillOpacity:0.35},
            interactive:false
        }).addTo(map);
        if (!capasVisibles.region) map.removeLayer(layerRegion);
        const rowR = document.getElementById('row-region');
        if (rowR) rowR.style.display='flex';
    }

    // Labels
    layerLabels = L.layerGroup();
    secData.features.forEach(feature => {
        if (!feature.geometry) return;
        const sec=String(feature.properties.seccion);
        // Para niveles 4-5, labels solo en secciones del scope
        if (SECCIONES_SCOPE.length>0 && !SECCIONES_SCOPE.includes(sec)) return;
        let latlng;
        try { latlng=L.geoJSON(feature).getBounds().getCenter(); } catch(e){return;}
        layerLabels.addLayer(L.marker(latlng,{
            icon:L.divIcon({className:'lbl-seccion',html:feature.properties.seccion,iconSize:null,iconAnchor:[10,7]}),
            interactive:false
        }));
    });
    layerLabels.addTo(map);
    if (!capasVisibles.labels) map.removeLayer(layerLabels);

    // Zoom al scope si es nivel 4-5
    if (SECCIONES_SCOPE.length>0 && (MI_NIVEL===4||MI_NIVEL===5) && layerSecciones) {
        const bounds=L.latLngBounds();
        layerSecciones.eachLayer(l=>{
            if(SECCIONES_SCOPE.includes(String(l.feature?.properties.seccion))) bounds.extend(l.getBounds());
        });
        if(bounds.isValid()) map.fitBounds(bounds,{padding:[30,30]});
    }

    const totalSec = MI_NIVEL>=4 ? SECCIONES_SCOPE.length : secData.features.length;
    document.getElementById('stat-secciones').textContent = totalSec;
    repintarEventos();
}

// ── Cambiar estado (niveles 1-2) ──────────────────────────
function cambiarEstado(key) {
    limpiarCapas();
    document.getElementById('stat-secciones').textContent='—';
    document.getElementById('stat-municipios').textContent='—';

    ['row-municipio','row-secciones','row-labels'].forEach(id => {
        const el=document.getElementById(id); if(el) el.style.display='none';
    });
    const ph=document.getElementById('capas-placeholder');
    if(ph) ph.style.display='block';
    ['municipio','secciones','labels'].forEach(n => {
        capasVisibles[n]=true;
        const t=document.getElementById('toggle-'+n); if(t) t.classList.add('on');
    });

    if (!key||!ESTADOS_DATA[key]) {
        const span=document.getElementById('header-estado-span');
        if(span) span.textContent='— Selecciona un estado';
        map.setView([23.6345,-102.5528],5); return;
    }
    estadoActual=key;
    const cfg=ESTADOS_DATA[key];
    const span=document.getElementById('header-estado-span');
    if(span) span.textContent=cfg.label;

    const lm=document.getElementById('label-municipio');
    if(lm) lm.textContent=`Municipios de ${cfg.label}`;
    const ls=document.getElementById('label-secciones');
    if(ls) ls.textContent=`Secciones — ${cfg.label}`;

    if (!cfg.municipio) { mostrarToast(`Sin datos de municipios para ${cfg.label}`); return; }

    const rowM=document.getElementById('row-municipio');
    if(rowM) rowM.style.display='flex';
    if(ph) ph.style.display='none';

    cargarSilueta(cfg.municipio);
    cargarMunicipios(cfg.municipio);

    if (cfg.seccionUrl) {
        document.getElementById('stat-secciones').textContent='…';
        mostrarToast(`Cargando secciones de ${cfg.label}…`);
        fetch(cfg.seccionUrl)
            .then(r=>{ if(!r.ok) throw new Error(`HTTP ${r.status}`); return r.json(); })
            .then(secData=>{
                if(estadoActual!==key) return;
                cargarSecciones(secData);
                const rs=document.getElementById('row-secciones'); if(rs) rs.style.display='flex';
                const rl=document.getElementById('row-labels');    if(rl) rl.style.display='flex';
            })
            .catch(err=>{ mostrarToast(`No se encontró cartografía para ${cfg.label}`); console.warn(err); });
    }
}

// ── Toggle capas ─────────────────────────────────────────
function toggleCapa(nombre) {
    capasVisibles[nombre]=!capasVisibles[nombre];
    const t=document.getElementById('toggle-'+nombre); if(t) t.classList.toggle('on',capasVisibles[nombre]);
    const capaMap={municipio:layerMunicipio,secciones:layerSecciones,labels:layerLabels,region:layerRegion};
    const capa=capaMap[nombre]; if(!capa) return;
    capasVisibles[nombre]?map.addLayer(capa):map.removeLayer(capa);
}

// ── Click en mapa (deseleccionar / seleccionar para evento) ──
let seleccionandoSeccion=false, seccionParaEvento=null;

map.on('click',function(e){
    if(seleccionandoSeccion){
        seleccionandoSeccion=false;
        map.getContainer().style.cursor='';
        let found=null;
        if(layerSecciones){
            layerSecciones.eachLayer(layer=>{
                if(found) return;
                try{
                    const sec=String(layer.feature?.properties.seccion);
                    const validScope = SECCIONES_SCOPE.length===0 || SECCIONES_SCOPE.includes(sec);
                    if(validScope && layer.getBounds?.().contains(e.latlng)) found=layer;
                }catch(_){}
            });
        }
        if(found&&found.feature){
            const p=found.feature.properties;
            const latlng=found.getBounds().getCenter();
            seccionParaEvento={num:p.seccion,latlng,estado:ESTADOS_DATA[estadoActual]?.label||ESTADO_LABEL||'',municipio:p.nombre_mun||p.NOM_MUN||''};
        } else {
            if(SECCIONES_SCOPE.length>0){mostrarToast('Esta sección no pertenece a tu territorio.');return;}
            seccionParaEvento={num:'—',latlng:e.latlng,estado:'',municipio:''};
        }
        setTimeout(()=>{actualizarSelectorSeccion();document.getElementById('modal-overlay').classList.add('open');},80);
        return;
    }
    if(seccionActiva&&layerSecciones){layerSecciones.resetStyle(seccionActiva);seccionActiva=null;}
    if(municipioActivo&&layerMunicipio){layerMunicipio.resetStyle(municipioActivo);municipioActivo=null;}
    document.getElementById('info-panel').classList.remove('visible');
    document.getElementById('db-panel').style.display='none';
    document.getElementById('db-no-data').style.display='none';
    document.getElementById('db-spinner').style.display='none';
});

// ── Buscar sección (nivel 5) ──────────────────────────────
function buscarSeccionMapa(){
    const input=document.getElementById('buscar-seccion-input')?.value.trim(); if(!input) return;
    let found=false;
    if(layerSecciones){
        layerSecciones.eachLayer(layer=>{
            if(found) return;
            if(String(layer.feature?.properties.seccion)===input){
                map.fitBounds(layer.getBounds(),{padding:[50,50],maxZoom:15});
                layer.fire('click'); found=true;
            }
        });
    }
    if(!found) mostrarToast('La sección '+input+' no pertenece a tu zona o no existe.');
}

// ── AGENDA EVENTOS ────────────────────────────────────────
const AGENDA_API='/api/agenda/procesar.php';
let eventosData=[]; let capasVisiblesEventos=true;
const layerEventos=L.layerGroup().addTo(map);

const SUBTIPOS_M={
    voto:['Casa por casa','Evento de campaña','Mitin','Brigada vecinal','Recorrido territorial','Firma de compromisos','Contacto directo'],
    comunicacion:['Entrevista de radio','Entrevista de TV','Rueda de prensa','Publicación redes sociales','Spot / grabación','Vocería','Evento mediático'],
    organizacion:['Reunión de estructura','Capacitación','Coordinación interna','Planeación estratégica','Logística','Revisión de presupuesto','Reunión de equipo']
};
const EJE_LABELS={voto:'🗳️ Voto / Territorio',comunicacion:'📣 Comunicación',organizacion:'⚙️ Organización'};
const EJE_COLORS={voto:'#1a7a3c',comunicacion:'#2196f3',organizacion:'#9c27b0'};
let ejeSeleccionadoM=null;

async function cargarYPintarEventos(){
    try{
        const res=await fetch(AGENDA_API+'?action=list');
        const data=await res.json();
        if(!data.ok) return;
        eventosData=data.eventos||[];
        // Nivel 4-5: filtrar solo eventos de sus secciones
        if(SECCIONES_SCOPE.length>0) eventosData=eventosData.filter(ev=>SECCIONES_SCOPE.includes(String(ev.seccion)));
        repintarEventos();
        document.getElementById('ev-count-badge').textContent=eventosData.length;
    }catch(e){}
}
function repintarEventos(){layerEventos.clearLayers();eventosData.forEach(ev=>colocarMarkerEvento(ev));}
function toggleCapaEventos(){
    capasVisiblesEventos=!capasVisiblesEventos;
    document.getElementById('toggle-eventos').classList.toggle('on',capasVisiblesEventos);
    capasVisiblesEventos?map.addLayer(layerEventos):map.removeLayer(layerEventos);
}
function seleccionarEjeM(eje){
    ejeSeleccionadoM=eje;
    document.querySelectorAll('.eje-btn-ag').forEach(b=>b.className=b.dataset.eje===eje?`eje-btn-ag sel-${eje}`:'eje-btn-ag');
    const sel=document.getElementById('m-subtipo');
    sel.innerHTML='<option value="">Selecciona subtipo…</option>';
    (SUBTIPOS_M[eje]||[]).forEach(s=>{const o=document.createElement('option');o.value=s;o.textContent=s;sel.appendChild(o);});
}
function calcHorasM(){
    const ini=document.getElementById('m-inicio').value,fin=document.getElementById('m-fin').value;
    if(ini&&fin){const[hi,mi]=ini.split(':').map(Number),[hf,mf]=fin.split(':').map(Number);const d=(hf*60+mf-hi*60-mi)/60;if(d>0)document.getElementById('m-horas').value=d.toFixed(1);}
}
function abrirModal(){
    ejeSeleccionadoM=null;
    document.querySelectorAll('.eje-btn-ag').forEach(b=>b.className='eje-btn-ag');
    document.getElementById('m-subtipo').innerHTML='<option value="">— Selecciona un eje primero —</option>';
    ['m-titulo','m-inicio','m-fin','m-horas','m-responsable','m-notas','m-region','m-zona'].forEach(id=>{document.getElementById(id).value='';});
    const hoy=new Date();
    document.getElementById('m-fecha').value=`${hoy.getFullYear()}-${String(hoy.getMonth()+1).padStart(2,'0')}-${String(hoy.getDate()).padStart(2,'0')}`;
    if(seccionActiva){
        const p=seccionActiva.feature.properties;
        const latlng=L.geoJSON(seccionActiva.feature).getBounds().getCenter();
        seccionParaEvento={num:p.seccion,latlng,estado:ESTADOS_DATA[estadoActual]?.label||ESTADO_LABEL||'',municipio:p.nombre_mun||p.NOM_MUN||''};
    }
    actualizarSelectorSeccion();
    document.getElementById('modal-overlay').classList.add('open');
}
function cerrarModal(){document.getElementById('modal-overlay').classList.remove('open');seleccionandoSeccion=false;}
function cerrarModalDet(){document.getElementById('modal-det').classList.remove('open');}
function activarSeleccionSeccion(){
    cerrarModal();seleccionandoSeccion=true;
    map.getContainer().style.cursor='crosshair';
    mostrarToast(SECCIONES_SCOPE.length>0?'Haz clic en una sección de tu territorio':'Haz clic en una sección del mapa');
}
function actualizarSelectorSeccion(){
    const badge=document.getElementById('seccion-badge-text');
    if(seccionParaEvento&&seccionParaEvento.num&&seccionParaEvento.num!=='—'){
        badge.className='seccion-badge';
        badge.innerHTML=`<i class="fas fa-map-marker-alt"></i> Sección ${seccionParaEvento.num}`;
        document.getElementById('m-estado').value=seccionParaEvento.estado||'';
        document.getElementById('m-municipio').value=seccionParaEvento.municipio||'';
    }else{
        badge.className='seccion-badge empty';
        badge.innerHTML=`<i class="fa-solid fa-map-marker-alt"></i> Ninguna sección seleccionada`;
        document.getElementById('m-estado').value='';
        document.getElementById('m-municipio').value='';
    }
}
async function guardarEvento(){
    const titulo=document.getElementById('m-titulo').value.trim();
    const fecha=document.getElementById('m-fecha').value;
    const btn=document.getElementById('m-save-btn');
    if(!titulo){mostrarToast('El título es obligatorio');return;}
    if(!fecha){mostrarToast('La fecha es obligatoria');return;}
    if(!ejeSeleccionadoM){mostrarToast('Selecciona un eje');return;}
    if(!seccionParaEvento||seccionParaEvento.num==='—'){mostrarToast('Selecciona una sección en el mapa');return;}
    const ini=document.getElementById('m-inicio').value,fin=document.getElementById('m-fin').value;
    let horas=parseFloat(document.getElementById('m-horas').value)||0;
    if(!horas&&ini&&fin){const[hi,mi]=ini.split(':').map(Number),[hf,mf]=fin.split(':').map(Number);horas=Math.max(0,(hf*60+mf-hi*60-mi)/60);}
    if(!horas)horas=1;
    btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Guardando…';btn.disabled=true;
    const fd=new FormData();
    fd.append('action','create');fd.append('titulo',titulo);fd.append('fecha',fecha);
    fd.append('eje',ejeSeleccionadoM);fd.append('subtipo',document.getElementById('m-subtipo').value);
    fd.append('seccion',seccionParaEvento.num);
    fd.append('estado',seccionParaEvento.estado||'');fd.append('municipio',seccionParaEvento.municipio||'');
    fd.append('region',document.getElementById('m-region').value);
    fd.append('zona',document.getElementById('m-zona').value);
    fd.append('inicio',ini);fd.append('fin',fin);fd.append('horas',horas);
    fd.append('responsable',document.getElementById('m-responsable').value.trim());
    fd.append('notas',document.getElementById('m-notas').value.trim());
    if(seccionParaEvento.latlng){fd.append('lat',seccionParaEvento.latlng.lat??seccionParaEvento.latlng[0]??'');fd.append('lng',seccionParaEvento.latlng.lng??seccionParaEvento.latlng[1]??'');}
    try{
        const res=await fetch(AGENDA_API,{method:'POST',body:fd});
        const text=await res.text();let data;
        try{data=JSON.parse(text);}catch(_){mostrarToast('Error del servidor.');return;}
        if(!data.ok) throw new Error(data.msg||'Error al guardar');
        await cargarYPintarEventos();cerrarModal();mostrarToast(`Evento guardado — Sec. ${seccionParaEvento.num}`);
    }catch(err){mostrarToast('Error: '+err.message);}
    finally{btn.innerHTML='<i class="fas fa-check"></i> Guardar Evento';btn.disabled=false;}
}
function verDetalleEventoById(id){const ev=eventosData.find(e=>String(e.id)===String(id));if(ev)verDetalleEvento(ev);}
function verDetalleEvento(ev){
    document.getElementById('det-titulo').textContent=ev.titulo||'—';
    const b=document.getElementById('det-eje-badge');const ej=ev.eje||'voto';
    b.textContent=EJE_LABELS[ej]||ej;b.style.cssText=`background:${EJE_COLORS[ej]}20;color:${EJE_COLORS[ej]};border:1px solid ${EJE_COLORS[ej]}40;padding:3px 10px;border-radius:20px;`;
    document.getElementById('det-subtipo').textContent=ev.subtipo||'—';
    document.getElementById('det-fecha').textContent=ev.fecha||'—';
    document.getElementById('det-estado').textContent=ev.estado||'—';
    document.getElementById('det-municipio').textContent=ev.municipio||'—';
    document.getElementById('det-seccion').textContent=ev.seccion?`Sección ${ev.seccion}`:'—';
    document.getElementById('det-responsable').textContent=ev.responsable||'—';
    document.getElementById('det-horas').textContent=ev.horas?ev.horas+'h':'—';
    const fmtH=t=>{if(!t)return'—';const[h,m]=t.split(':');const hh=parseInt(h);return`${hh%12||12}:${m} ${hh>=12?'p.m.':'a.m.'}`};
    document.getElementById('det-horario').textContent=(ev.inicio||ev.fin)?`${fmtH(ev.inicio)} – ${fmtH(ev.fin)}`:'—';
    document.getElementById('det-notas').textContent=ev.notas||'Sin notas.';
    document.getElementById('modal-det').classList.add('open');
}
function colocarMarkerEvento(ev){
    let latlng=null;
    if(ev.lat&&ev.lng)latlng=[parseFloat(ev.lat),parseFloat(ev.lng)];
    else if(layerSecciones)layerSecciones.eachLayer(l=>{if(latlng)return;try{if(l.feature&&String(l.feature.properties.seccion)===String(ev.seccion))latlng=l.getBounds().getCenter();}catch(_){}});
    if(!latlng)return;
    const color=EJE_COLORS[ev.eje]||'#1a7a3c';
    const iconHtml=`<div class="evento-pin"><div class="evento-pin-icon" style="background:${color};"><i class="fa-solid fa-location-dot"></i></div><div class="evento-pin-label">SEC.${ev.seccion||'?'}</div></div>`;
    const marker=L.marker(latlng,{icon:L.divIcon({className:'evento-marker',html:iconHtml,iconSize:[40,44],iconAnchor:[13,38]}),interactive:true});
    const popup=`<div style="font-family:inherit;min-width:160px;"><b style="font-size:13px;display:block;margin-bottom:4px;">${ev.titulo||'—'}</b><span style="font-size:11px;color:${color};">Sección ${ev.seccion||'?'}${ev.fecha?' — '+ev.fecha:''}</span><br><button onclick="verDetalleEventoById('${ev.id}')" style="margin-top:8px;padding:5px 10px;background:${color};color:white;border:none;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer;">Ver detalle</button></div>`;
    marker.bindPopup(popup,{maxWidth:260});
    layerEventos.addLayer(marker);
}

// ── Datos BD por sección ──────────────────────────────────
async function cargarDatosSeccion(numSeccion){
    document.getElementById('db-panel').style.display='none';
    document.getElementById('db-no-data').style.display='none';
    document.getElementById('db-spinner').style.display='block';
    const fmt=n=>(n!=null&&n!==''&&!isNaN(n))?parseInt(n).toLocaleString('es-MX'):'—';
    const pct=(c,m)=>(!m||m==0)?0:Math.min(100,Math.round(c/m*100));
    try{
        const estadoReq=ESTADOS_DATA[estadoActual]?.label||ESTADO_LABEL||'';
        const res=await fetch(`/api/secciones/secciones_api.php?action=get_by_num&seccion=${encodeURIComponent(numSeccion)}&estado=${encodeURIComponent(estadoReq)}`);
        const data=await res.json();
        document.getElementById('db-spinner').style.display='none';
        if(!data.ok||!data.seccion){document.getElementById('db-no-data').style.display='block';return;}
        const s=data.seccion;seccionDetalleActual=s;
        document.getElementById('db-region').textContent=s.region_asignada||'—';
        document.getElementById('db-zona').textContent=s.zona_asignada||'—';
        document.getElementById('db-nominal').textContent=fmt(s.lista_nominal);
        const colores={'18-24':'#3498db','25-34':'#2ecc71','35-44':'#f1c40f','45-54':'#e67e22','55+':'#9b59b6'};
        const edadRows=[['18-24',s.grupo_edad_18_24],['25-34',s.grupo_edad_25_34],['35-44',s.grupo_edad_35_44],['45-54',s.grupo_edad_45_54],['55+',s.grupo_edad_55_mas]].filter(([,v])=>v>0);
        document.getElementById('db-edad-chips').innerHTML=edadRows.map(([l,v])=>{const c=colores[l]||'#6b7280';return`<span class="edad-chip" style="background:${c}20;color:${c};border:1px solid ${c}40;">${l}: ${fmt(v)}</span>`;}).join('');
        const meta=parseInt(s.meta_contactos)||0,cons=parseInt(s.contactos_conseguidos)||0,p=pct(cons,meta);
        document.getElementById('db-meta').textContent=fmt(meta);
        document.getElementById('db-contactos').textContent=fmt(cons);
        document.getElementById('db-progress-fill').style.width=p+'%';
        document.getElementById('db-pct').textContent=p+'%';
        document.getElementById('db-rem').textContent=fmt(Math.max(0,meta-cons))+' rest.';
        document.getElementById('db-eventos').textContent=fmt(s.eventos_realizados);
        document.getElementById('db-votos').textContent=fmt(s.metas_votos);
        document.getElementById('db-act-total').textContent=fmt(s.activaciones_totales);
        document.getElementById('db-act-con').textContent=fmt(s.con_candidato);
        document.getElementById('db-act-sin').textContent=fmt(s.sin_candidato);
        const setOpen=(wId,tId,val)=>{const ok=val&&val.trim();document.getElementById(wId).style.display=ok?'block':'none';if(ok)document.getElementById(tId).textContent=val;};
        setOpen('db-nec-wrap','db-necesidades',s.principales_necesidades);
        setOpen('db-enf-wrap','db-enfoque',s.enfoque);
        setOpen('db-per-wrap','db-perfil',s.perfil_socioeconomico);
        // Activaciones lista
        const wA=document.getElementById('db-act-lista-wrap'),lA=document.getElementById('db-act-lista');
        const acts=s.activaciones_lista||[];
        wA.style.display='block';
        const esBadge={programada:'background:#fff7ed;color:#d97706;','en-curso':'background:#eff6ff;color:#1e88e5;',completada:'background:#f0fdf4;color:#16a34a;',cancelada:'background:#fff5f5;color:#e53935;'};
        lA.innerHTML=acts.length?acts.map(a=>`<div style="padding:5px 0;border-bottom:1px solid #f0f0f0;"><div style="display:flex;justify-content:space-between;align-items:center;gap:4px;"><span style="font-size:11px;font-weight:700;">${a.titulo||'—'}</span><span style="font-size:9px;font-weight:700;padding:1px 6px;border-radius:8px;${esBadge[a.estatus]||''}">${(a.estatus||'').replace('-',' ')}</span></div><div style="font-size:10px;color:#6b7280;">${a.fecha_actividad||''}</div></div>`).join(''):'<div style="font-size:11px;color:#9ca3af;padding:4px 0;">Sin activaciones.</div>';
        // Eventos agenda lista
        const wE=document.getElementById('db-eventos-wrap'),lE=document.getElementById('db-eventos-lista');
        const evs=s.eventos_lista||[];
        wE.style.display='block';
        const ejeColor={voto:'#2d7a3e',comunicacion:'#1a73e8',organizacion:'#8b00d4'};
        lE.innerHTML=evs.length?evs.map(ev=>{const c=ejeColor[ev.eje]||'#666';return`<div style="display:flex;gap:6px;align-items:flex-start;padding:5px 0;border-bottom:1px solid #f0f0f0;"><span style="width:7px;height:7px;border-radius:50%;background:${c};flex-shrink:0;margin-top:4px;"></span><div><div style="font-size:11px;font-weight:700;">${ev.titulo||'—'}</div><div style="font-size:10px;color:#6b7280;">${ev.fecha||''}</div></div></div>`;}).join(''):'<div style="font-size:11px;color:#9ca3af;padding:4px 0;">Sin eventos.</div>';
        document.getElementById('db-panel').style.display='block';
    }catch(err){document.getElementById('db-spinner').style.display='none';document.getElementById('db-no-data').style.display='block';}
}

// ── Modal sección completa ────────────────────────────────
function abrirModalSeccionCompleta(){
    if(!seccionDetalleActual){mostrarToast('Sin datos cargados aún.');return;}
    const s=seccionDetalleActual;
    const fmt=n=>(n!=null&&n!==''&&!isNaN(n))?parseInt(n).toLocaleString('es-MX'):'—';
    document.getElementById('sec-det-titulo').textContent='Sección '+s.nombre_seccion||'—';
    document.getElementById('sec-det-ubicacion').textContent=[s.estado_nombre||s.estado,s.municipio].filter(Boolean).join(' › ')||'—';
    document.getElementById('sdet-seccion').textContent=s.nombre_seccion||s.seccion||'—';
    document.getElementById('sdet-municipio').textContent=s.municipio||'—';
    document.getElementById('sdet-df').textContent=s.distrito_federal||'—';
    document.getElementById('sdet-dl').textContent=s.distrito_local||'—';
    document.getElementById('sdet-region').textContent=s.region_asignada||'—';
    document.getElementById('sdet-zona').textContent=s.zona_asignada||'—';
    document.getElementById('sdet-padron').textContent=fmt(s.padron_electoral||s.lista_nominal);
    document.getElementById('sdet-hombres').textContent=fmt(s.lista_hombres);
    document.getElementById('sdet-mujeres').textContent=fmt(s.lista_mujeres);
    let meta=parseInt(s.meta_contactos)||0,cons=parseInt(s.contactos_conseguidos)||0;
    if(!meta&&parseInt(s.lista_nominal)>0)meta=Math.round(parseInt(s.lista_nominal)*0.12);
    const p=(!meta)?0:Math.min(100,Math.round((cons/meta)*100));
    document.getElementById('sdet-meta').textContent=fmt(meta);
    document.getElementById('sdet-contactos').textContent=fmt(cons);
    document.getElementById('sdet-prog-fill').style.width=p+'%';
    document.getElementById('sdet-prog-pct').textContent=p+'%';
    document.getElementById('sdet-prog-rem').textContent=fmt(Math.max(0,meta-cons))+' restantes';
    document.getElementById('sdet-eventos').textContent=fmt(s.eventos_realizados);
    document.getElementById('sdet-votos').textContent=fmt(s.metas_votos);
    document.getElementById('sdet-act-total').textContent=fmt(s.activaciones_totales);
    document.getElementById('sdet-act-con').textContent=fmt(s.con_candidato);
    document.getElementById('sdet-act-sin').textContent=fmt(s.sin_candidato);
    const setO=(wId,tId,val)=>{const ok=val&&val.trim();document.getElementById(wId).style.display=ok?'block':'none';if(ok)document.getElementById(tId).textContent=val;};
    setO('sdet-nec-wrap','sdet-necesidades',s.principales_necesidades);
    setO('sdet-enf-wrap','sdet-enfoque',s.enfoque);
    setO('sdet-per-wrap','sdet-perfil',s.perfil_socioeconomico);
    const hasInfo=s.principales_necesidades||s.enfoque||s.perfil_socioeconomico;
    document.getElementById('sdet-info-wrap').style.display=hasInfo?'block':'none';
    document.getElementById('modal-seccion-completa').classList.add('open');
}
function cerrarModalSeccion(){document.getElementById('modal-seccion-completa').classList.remove('open');}

// ── Toast ─────────────────────────────────────────────────
function mostrarToast(msg){
    let t=document.getElementById('toast-ev');
    if(!t){t=document.createElement('div');t.id='toast-ev';t.style.cssText='position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:#1a1e2a;color:#fff;font-family:inherit;font-size:12px;padding:8px 16px;border-radius:6px;z-index:9999;pointer-events:none;opacity:0;transition:opacity 0.2s;white-space:nowrap;';document.body.appendChild(t);}
    t.textContent=msg;t.style.opacity='1';clearTimeout(t._timer);t._timer=setTimeout(()=>{t.style.opacity='0';},2500);
}

// ── Inicialización ────────────────────────────────────────
cargarYPintarEventos();

// Para niveles 3-5: cargar el estado automáticamente
if (MI_NIVEL>=3 && ESTADO_INICIAL && ESTADOS_DATA[ESTADO_INICIAL]) {
    window.addEventListener('load', () => {
        const sel=document.getElementById('estado-select');
        if(sel){sel.value=ESTADO_INICIAL;}
        cambiarEstado(ESTADO_INICIAL);
    });
} else if (MI_NIVEL<=2 && ESTADO_INICIAL) {
    window.addEventListener('load', () => {
        const sel=document.getElementById('estado-select');
        if(sel&&ESTADOS_DATA[ESTADO_INICIAL]){sel.value=ESTADO_INICIAL;cambiarEstado(ESTADO_INICIAL);}
    });
}
</script>
</body>
</html>