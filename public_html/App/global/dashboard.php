<?php
/* ════════════════════════════════════════════════════════════════
   global/dashboard.php — Dashboard Unificado 5 niveles
   Nivel 1 (Admin):      context=admin,    switcher: Resumen / Estado / Región / Zona
   Nivel 2 (Circ):       context=circ,     switcher: Resumen / Estado
   Nivel 3 (Estatal):    context=estatal,  switcher: Resumen / Región / Zona
   Nivel 4 (Regional):   context=region,   switcher: Resumen / Zona
   Nivel 5 (Zona):       context=zona,     switcher: Resumen / Sección
════════════════════════════════════════════════════════════════ */

// ── AJAX: guardar metas (POST inline) ────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
        require_once __DIR__ . '/../../includes/bootstrap.php';
    } else { session_start(); }
    $body = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $estado = trim($body['estado'] ?? '');
    $mv     = (int)($body['meta_voto'] ?? 0);
    $mc     = (int)($body['meta_com']  ?? 0);
    $mo     = (int)($body['meta_org']  ?? 0);
    $total  = $mv + $mc + $mo;
    if (empty($estado) || $total <= 0) {
        echo json_encode(['ok'=>false,'msg'=>'Datos inválidos']); exit;
    }
    try {
        $dbCfg = __DIR__ . '/../../includes/database.php';
        $cfg   = file_exists($dbCfg) ? require $dbCfg : [];
        $dsn   = "mysql:host={$cfg['host']};dbname={$cfg['database']};charset=utf8mb4";
        $pdo   = new PDO($dsn, $cfg['username'], $cfg['password'],
                    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $pdo->prepare("
            INSERT INTO metas_estados (estado, meta, meta_voto, meta_com, meta_org, updated_at)
            VALUES (?,?,?,?,?,CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE
              meta=VALUES(meta), meta_voto=VALUES(meta_voto),
              meta_com=VALUES(meta_com), meta_org=VALUES(meta_org),
              updated_at=CURRENT_TIMESTAMP
        ")->execute([$estado, $total, $mv, $mc, $mo]);
        echo json_encode(['ok'=>true]);
    } catch (Exception $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── Seguridad ─────────────────────────────────────────────
if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
} else { session_start(); }

$rol_sesion  = strtolower($_SESSION['user_role']          ?? '');
$user_id     = $_SESSION['user_id']                       ?? 0;
$user_circ   = $_SESSION['user_circunscripcion']          ?? '';
$user_estado = trim($_SESSION['user_estado']              ?? '');
$user_region = $_SESSION['user_region']                   ?? '';
$user_zona   = $_SESSION['user_zona_nombre']              ?? '';
$user_name   = $_SESSION['user_name']                     ?? '';

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
$estados_circ = $CIRCS[$user_circ] ?? [];

// ── Conexión ──────────────────────────────────────────────
$entidades   = [];   // opciones para el selector de entidad (nivel 2+)
$entidades2  = [];   // segundo nivel (nivel 1: regiones; nivel 3: zonas)
$region_nombre = '—';
$init_meta_voto = 0;
$init_meta_com  = 0;
$init_meta_org  = 0;

try {
    $dbCfg2 = __DIR__ . '/../../includes/database.php';
    $cfg2   = file_exists($dbCfg2) ? require $dbCfg2 : [];
    $dsn2   = "mysql:host={$cfg2['host']};dbname={$cfg2['database']};charset=utf8mb4";
    $pdo    = new PDO($dsn2, $cfg2['username'], $cfg2['password'],
                  [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);

    if ($mi_nivel === 1) {
        // Admin: estados, regiones, zonas
        $st = $pdo->query("SELECT DISTINCT estado FROM regiones_estatales WHERE estado IS NOT NULL ORDER BY estado");
        $entidades = $st->fetchAll(PDO::FETCH_COLUMN);
        $st2 = $pdo->query("SELECT nombre, estado FROM regiones_estatales ORDER BY estado, nombre");
        $entidades2 = $st2->fetchAll();

    } elseif ($mi_nivel === 2) {
        // Circ: sus estados
        $entidades = $estados_circ;
        // Metas globales de circunscripción
        $st = $pdo->prepare("SELECT meta_voto, meta_com, meta_org FROM metas_estados WHERE estado='GLOBAL_CIRCUNSCRIPCION' LIMIT 1");
        $st->execute(); $row = $st->fetch();
        if ($row) { $init_meta_voto=(int)$row['meta_voto']; $init_meta_com=(int)$row['meta_com']; $init_meta_org=(int)$row['meta_org']; }

    } elseif ($mi_nivel === 3) {
        // Estatal: obtener estado desde BD
        $stE = $pdo->prepare("SELECT estado FROM coordinador_estatal WHERE id=? LIMIT 1");
        $stE->execute([$user_id]);
        $user_estado = trim($stE->fetchColumn() ?: $user_estado);
        // Regiones del estado
        $st = $pdo->prepare("SELECT nombre FROM regiones_estatales WHERE estado=? ORDER BY nombre");
        $st->execute([$user_estado]); $entidades = $st->fetchAll(PDO::FETCH_COLUMN);
        // Zonas del estado
        $st2 = $pdo->prepare("SELECT z.nombre FROM zonas_estatales z INNER JOIN regiones_estatales r ON z.estructura_id=r.id WHERE r.estado=? ORDER BY z.nombre");
        $st2->execute([$user_estado]); $entidades2 = $st2->fetchAll(PDO::FETCH_COLUMN);
        // Metas del estado
        $st = $pdo->prepare("SELECT meta_voto, meta_com, meta_org FROM metas_estados WHERE estado=? LIMIT 1");
        $st->execute([$user_estado]); $row = $st->fetch();
        if ($row) { $init_meta_voto=(int)$row['meta_voto']; $init_meta_com=(int)$row['meta_com']; $init_meta_org=(int)$row['meta_org']; }

    } elseif ($mi_nivel === 4) {
        // Regional: zonas de su región
        if ($user_region) {
            $st = $pdo->prepare("SELECT nombre FROM geo_regiones WHERE id=? LIMIT 1");
            $st->execute([$user_region]); $r = $st->fetch();
            if ($r) $region_nombre = ucfirst($r['nombre']);
            $st = $pdo->prepare("SELECT id, nombre FROM geo_zonas WHERE region_id=? ORDER BY nombre");
            $st->execute([$user_region]); $entidades = $st->fetchAll();
        }
        // Metas de región
        $key_region = 'REGION_'.$user_region;
        $st = $pdo->prepare("SELECT meta_voto, meta_com, meta_org FROM metas_estados WHERE estado=? LIMIT 1");
        $st->execute([$key_region]); $row = $st->fetch();
        if ($row) { $init_meta_voto=(int)$row['meta_voto']; $init_meta_com=(int)$row['meta_com']; $init_meta_org=(int)$row['meta_org']; }

    } elseif ($mi_nivel === 5) {
        // Zona: sus secciones electorales
        $st = $pdo->prepare("SELECT nombre_seccion FROM secciones_electorales WHERE TRIM(zona_asignada)=TRIM(?) AND activo=1 ORDER BY CAST(nombre_seccion AS UNSIGNED)");
        $st->execute([$user_zona]); $entidades = $st->fetchAll(PDO::FETCH_COLUMN);
    }

} catch (Exception $e) { $error_db = $e->getMessage(); }

// ── Config por nivel ──────────────────────────────────────
$api_context = match($mi_nivel) {
    1 => 'admin', 2 => 'circ', 3 => 'estatal', 4 => 'region', 5 => 'zona', default => 'admin'
};
$api_id_base = match($mi_nivel) {
    1 => 'todas',
    2 => 'GLOBAL_CIRCUNSCRIPCION',
    3 => $user_estado,
    4 => (string)$user_region,
    5 => $user_zona,
    default => 'todas'
};
// Switcher labels: [nivel resumen, nivel detalle]
$switcher = match($mi_nivel) {
    1 => [['resumen','Resumen General'],['estado','Por Estado'],['region','Por Región'],['zona','Por Zona']],
    2 => [['resumen','Resumen General'],['estado','Por Estado']],
    3 => [['resumen','Resumen General'],['region','Por Región'],['zona','Por Zona']],
    4 => [['resumen','Resumen Regional'],['zona','Por Zona']],
    5 => [['resumen','Resumen de Zona'],['seccion','Por Sección']],
    default => [['resumen','Resumen']]
};
$badge_base = match($mi_nivel) {
    1 => 'ADMINISTRADOR',
    2 => strtoupper($user_circ),
    3 => strtoupper($user_estado),
    4 => 'REGIÓN '.strtoupper($region_nombre),
    5 => strtoupper($user_zona),
    default => 'MOVILIZACIÓN'
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Proyecto Verde</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
    <style>
    :root {
        --primary:#2d7a3e; --primary-dark:#1f5a2d; --primary-light:#4caf50;
        --accent:#e8a020; --dark:#1a1a1a; --light:#f0f7f0; --white:#ffffff;
        --success:#22c55e; --warning:#f59e0b; --danger:#ef4444; --info:#3b82f6;
        --text-muted:#888; --border:#f0f0f0;
        --shadow-sm:0 10px 30px rgba(0,0,0,0.05);
        --shadow-hover:0 20px 40px rgba(45,122,62,0.15);
        --radius:20px;
    }
    *{margin:0;padding:0;box-sizing:border-box;}
    body{font-family:'Arial',sans-serif;background:var(--light);color:var(--dark);overflow-x:hidden;}
    .container{max-width:100%;margin:0;background:var(--white);min-height:100vh;padding-left:330px;padding-right:40px;padding-bottom:60px;transition:padding-left 0.3s ease;}
    .design-text{position:fixed;left:290px;top:50%;writing-mode:vertical-rl;font-size:13px;font-weight:500;color:var(--primary);opacity:0.3;letter-spacing:2px;z-index:0;pointer-events:none;}
    /* ── CONTEXT BAR ── */
    .context-bar{position:sticky;top:0;z-index:50;background:rgba(255,255,255,0.95);backdrop-filter:blur(10px);border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;padding:16px 0;margin-bottom:20px;gap:20px;flex-wrap:wrap;}
    .filters-left{display:flex;align-items:center;gap:24px;flex-wrap:wrap;}
    .filter-group{display:flex;align-items:center;gap:10px;}
    .filter-label{font-size:11px;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;}
    .level-switcher{display:inline-flex;background:var(--light);padding:4px;border-radius:12px;border:1px solid var(--border);}
    .level-btn{background:transparent;border:none;color:#666;padding:8px 16px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:700;transition:all 0.2s;}
    .level-btn:hover{color:var(--primary);}
    .level-btn.active{background:var(--white);color:var(--dark);box-shadow:0 2px 8px rgba(0,0,0,0.05);}
    .ui-select{background:var(--white);border:1.5px solid var(--border);padding:8px 36px 8px 16px;border-radius:10px;font-size:13px;font-weight:700;color:var(--dark);cursor:pointer;outline:none;transition:all 0.2s;appearance:none;background-image:url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%232d7a3e' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");background-repeat:no-repeat;background-position:right 12px center;background-size:14px;min-width:140px;}
    .ui-select:focus,.ui-select:hover{border-color:var(--primary);box-shadow:0 4px 12px rgba(45,122,62,0.1);}
    .filters-right{display:flex;align-items:center;gap:16px;margin-left:auto;}
    /* ── HEADER ── */
    .page-header{padding:20px 0 30px 0;position:relative;z-index:2;display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:16px;}
    .ai-badge{color:var(--primary);font-size:12px;font-weight:800;letter-spacing:4px;margin-bottom:14px;display:inline-block;text-transform:uppercase;background:rgba(45,122,62,0.1);padding:5px 15px;border-radius:20px;}
    .main-title{font-size:48px;font-weight:900;line-height:1;margin-bottom:12px;color:var(--dark);}
    .description{color:#666;font-size:15px;line-height:1.6;}
    .location-pills{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;}
    .loc-pill{background:rgba(45,122,62,0.08);color:var(--primary);font-size:11px;font-weight:700;padding:4px 10px;border-radius:20px;display:flex;align-items:center;gap:5px;}
    .header-actions{display:flex;gap:12px;align-items:center;}
    .btn-action{background:var(--dark);color:white;padding:13px 24px;border-radius:12px;border:none;font-weight:700;cursor:pointer;transition:all 0.3s;display:flex;align-items:center;gap:8px;font-size:14px;}
    .btn-action:hover{transform:translateY(-3px);box-shadow:0 10px 20px rgba(0,0,0,0.2);}
    .btn-outline{background:white;color:var(--dark);border:2px solid #eee;}
    .btn-outline:hover{border-color:var(--dark);}
    /* ── KPI GRID ── */
    .kpi-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;margin-bottom:10px;}
    .kpi-card{background:white;border:1px solid var(--border);border-radius:var(--radius);padding:28px;position:relative;overflow:hidden;transition:all 0.3s ease;box-shadow:var(--shadow-sm);}
    .kpi-card:hover{transform:translateY(-5px);box-shadow:var(--shadow-hover);border-color:var(--primary);}
    .kpi-header{display:flex;justify-content:space-between;margin-bottom:18px;}
    .kpi-icon{width:48px;height:48px;background:#f4fcf6;color:var(--primary);border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:22px;}
    .kpi-icon.accent{background:#fff8ec;color:var(--accent);}
    .kpi-icon.danger{background:#fff0f0;color:var(--danger);}
    .kpi-icon.info{background:#eff6ff;color:var(--info);}
    .kpi-icon.warning{background:#fff7ed;color:var(--warning);}
    .kpi-value{font-size:44px;font-weight:900;color:var(--dark);line-height:1;margin-bottom:5px;}
    .kpi-label{font-size:13px;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:0.5px;}
    /* ── CHARTS ── */
    .section-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;margin-top:36px;}
    .section-title{font-size:20px;font-weight:800;color:var(--dark);display:flex;align-items:center;gap:10px;}
    .section-title-bar{display:block;width:4px;height:22px;background:var(--accent);border-radius:2px;}
    .charts-section{display:grid;gap:20px;}
    .charts-13{grid-template-columns:1fr 2fr;}
    .charts-equal{grid-template-columns:1fr 1fr;}
    .chart-card{background:white;border-radius:var(--radius);padding:28px;border:1px solid var(--border);box-shadow:var(--shadow-sm);transition:all 0.3s;}
    .chart-card-header{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:20px;}
    .chart-card-title{font-size:16px;font-weight:800;color:var(--dark);}
    .chart-card-sub{font-size:12px;color:var(--text-muted);margin-top:3px;}
    /* ── BARS ── */
    .compliance-grid{display:flex;flex-direction:column;gap:16px;}
    .compliance-row{display:flex;flex-direction:column;gap:6px;}
    .compliance-header{display:flex;justify-content:space-between;align-items:center;font-size:13px;}
    .compliance-name{font-weight:700;color:var(--dark);}
    .compliance-meta{display:flex;gap:10px;font-size:12px;color:var(--text-muted);}
    .compliance-pct{font-weight:800;}
    .bar-track{height:10px;background:var(--border);border-radius:6px;overflow:hidden;}
    .bar-fill{height:100%;border-radius:6px;transition:width 0.8s ease;}
    .bar-fill.territorio{background:var(--info);}
    .bar-fill.comunicacion{background:#8b5cf6;}
    .bar-fill.organizacion{background:var(--accent);}
    /* ── STATUS CARDS ── */
    .status-cards{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:16px;}
    .status-card{background:var(--light);border-radius:12px;padding:14px;text-align:center;border:1.5px solid var(--border);}
    .s-count{font-size:28px;font-weight:900;line-height:1;margin-bottom:4px;}
    .s-label{font-size:10px;text-transform:uppercase;letter-spacing:0.5px;font-weight:700;color:var(--text-muted);}
    .status-card.programada .s-count{color:var(--info);}
    .status-card.en-curso .s-count{color:var(--warning);}
    .status-card.completada .s-count{color:var(--success);}
    .status-card.cancelada .s-count{color:var(--danger);}
    /* ── IMPACT ── */
    .contact-metrics{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:14px;}
    .contact-metric{text-align:center;padding:14px;background:var(--light);border-radius:12px;border:1.5px solid var(--border);}
    .cm-value{font-size:24px;font-weight:900;color:var(--dark);}
    .cm-label{font-size:11px;color:var(--text-muted);margin-top:3px;font-weight:600;}
    /* ── TABLE ── */
    .data-table{width:100%;border-collapse:collapse;font-size:13px;}
    .data-table thead tr{background:var(--primary);color:white;}
    .data-table thead th{padding:11px 14px;text-align:left;font-weight:700;font-size:11px;letter-spacing:0.5px;white-space:nowrap;}
    .data-table thead th:first-child{border-radius:10px 0 0 0;}
    .data-table thead th:last-child{border-radius:0 10px 0 0;}
    .data-table tbody tr{border-bottom:1px solid var(--border);transition:background 0.15s;}
    .data-table tbody tr:hover{background:var(--light);}
    .data-table tbody td{padding:11px 14px;vertical-align:middle;}
    .rank-badge{display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border-radius:50%;font-weight:800;font-size:12px;background:var(--border);color:var(--text-muted);}
    .rank-badge.top{background:var(--accent);color:#1a1a1a;}
    /* ── MODAL ── */
    .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:9999;align-items:center;justify-content:center;backdrop-filter:blur(3px);}
    .modal-overlay.open{display:flex;}
    .modal-box{background:white;width:90%;max-width:420px;padding:30px;border-radius:20px;box-shadow:0 20px 40px rgba(0,0,0,0.2);}
    .modal-title{font-size:20px;font-weight:800;margin-bottom:20px;}
    .form-group{margin-bottom:15px;}
    .form-group label{display:block;font-size:12px;font-weight:700;color:#666;margin-bottom:6px;}
    .form-group input{width:100%;padding:10px 14px;border:2px solid #eee;border-radius:10px;font-weight:700;color:#333;outline:none;font-family:inherit;}
    .form-group input:focus{border-color:var(--primary);}
    @media(max-width:1024px){.container{padding-left:20px;padding-right:20px;}.design-text{display:none;}}
    @media(max-width:768px){.kpi-grid{grid-template-columns:1fr 1fr;}.charts-13,.charts-equal{grid-template-columns:1fr;}}
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
<div class="design-text">CAMPAIGN 360</div>

<div class="container">
    <!-- CONTEXT BAR -->
    <div class="context-bar">
        <div class="filters-left">
            <div class="filter-group">
                <span class="filter-label">Vista:</span>
                <div class="level-switcher">
                    <?php foreach ($switcher as $i => [$key, $label]): ?>
                    <button class="level-btn <?php echo $i===0?'active':''; ?>"
                            data-level="<?php echo $key; ?>"
                            onclick="switchLevel('<?php echo $key; ?>', this)">
                        <?php echo $label; ?>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <!-- Selector de entidad (visible en modo detalle) -->
            <div class="filter-group" id="entity-filter-group" style="display:none;border-left:2px solid var(--border);padding-left:20px;">
                <span class="filter-label" id="entity-label">Entidad:</span>
                <select id="entity-select" class="ui-select" onchange="updateDashboard()">
                    <?php if ($mi_nivel === 1): ?>
                        <!-- Admin: estados por defecto, cambia con el nivel -->
                        <?php foreach ($entidades as $e): ?>
                        <option value="<?php echo htmlspecialchars($e); ?>"><?php echo htmlspecialchars($e); ?></option>
                        <?php endforeach; ?>
                    <?php elseif ($mi_nivel === 2): ?>
                        <?php foreach ($entidades as $e): ?>
                        <option value="<?php echo htmlspecialchars($e); ?>"><?php echo htmlspecialchars($e); ?></option>
                        <?php endforeach; ?>
                    <?php elseif ($mi_nivel === 3): ?>
                        <!-- Estatal: regiones por defecto -->
                        <?php foreach ($entidades as $e): ?>
                        <option value="<?php echo htmlspecialchars($e); ?>"><?php echo htmlspecialchars($e); ?></option>
                        <?php endforeach; ?>
                    <?php elseif ($mi_nivel === 4): ?>
                        <?php foreach ($entidades as $zona): ?>
                        <option value="<?php echo htmlspecialchars($zona['id']); ?>"><?php echo htmlspecialchars($zona['nombre']); ?></option>
                        <?php endforeach; ?>
                    <?php elseif ($mi_nivel === 5): ?>
                        <?php foreach ($entidades as $sec): ?>
                        <option value="<?php echo htmlspecialchars($sec); ?>">Sección <?php echo htmlspecialchars($sec); ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
        </div>
        <div class="filters-right">
            <div class="filter-group">
                <span class="filter-label">Mes:</span>
                <select id="month-select" class="ui-select" onchange="updateWeeksDropdown()"></select>
            </div>
            <div class="filter-group">
                <span class="filter-label">Semana:</span>
                <select id="week-select" class="ui-select" onchange="updateDashboard()"></select>
            </div>
            <button class="btn-action btn-outline" onclick="abrirModalMetas()">
                <i class="fas fa-bullseye"></i> Metas
            </button>
            <button class="btn-action btn-outline" onclick="updateDashboard()">
                <i class="fas fa-sync-alt"></i>
            </button>
        </div>
    </div>

    <div class="view-panel active" id="main-panel">
        <div class="page-header">
            <div class="header-content">
                <span class="ai-badge" id="dyn-badge"><?php echo htmlspecialchars($badge_base); ?></span>
                <h1 class="main-title" id="dyn-title">Dashboard.</h1>
                <p class="description"><span id="dyn-desc">Resumen ejecutivo</span> · <span id="dyn-time" style="font-weight:700;"></span></p>
                <?php if ($mi_nivel >= 4): ?>
                <div class="location-pills">
                    <?php if ($mi_nivel === 4): ?>
                    <span class="loc-pill"><i class="fas fa-flag"></i> <?php echo htmlspecialchars($user_estado); ?></span>
                    <span class="loc-pill"><i class="fas fa-map-marked-alt"></i> Región <?php echo htmlspecialchars($region_nombre); ?></span>
                    <?php elseif ($mi_nivel === 5): ?>
                    <span class="loc-pill"><i class="fas fa-flag"></i> <?php echo htmlspecialchars($user_estado); ?></span>
                    <span class="loc-pill"><i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($user_zona); ?></span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- KPIs -->
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-header"><div class="kpi-icon accent"><i class="fas fa-calendar-alt"></i></div></div>
                <div class="kpi-value" id="kpi-agenda">0</div>
                <div class="kpi-label">Eventos Agendados</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-header"><div class="kpi-icon"><i class="fas fa-bolt"></i></div></div>
                <div class="kpi-value" id="kpi-acts">0</div>
                <div class="kpi-label">Activaciones Totales</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-header"><div class="kpi-icon info"><i class="fas fa-user-plus"></i></div></div>
                <div class="kpi-value" id="kpi-contactos">0</div>
                <div class="kpi-label">Contactos Nuevos</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-header"><div class="kpi-icon warning"><i class="fas fa-clipboard-check"></i></div></div>
                <div class="kpi-value" id="kpi-cump">0%</div>
                <div class="kpi-label">Cumplimiento Agenda</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-header"><div class="kpi-icon"><i class="fas fa-map-marked-alt"></i></div></div>
                <div class="kpi-value" id="kpi-activos">0</div>
                <div class="kpi-label" id="lbl-activos">
                    <?php echo match($mi_nivel) {
                        1 => 'Estados Activos', 2 => 'Entidades Activas',
                        3 => 'Regiones Activas', 4 => 'Zonas Activas',
                        5 => 'Secciones Activas', default => 'Entidades Activas'
                    }; ?>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-header"><div class="kpi-icon danger"><i class="fas fa-dollar-sign"></i></div></div>
                <div class="kpi-value" id="kpi-costo">$0</div>
                <div class="kpi-label">Costo Acumulado</div>
            </div>
        </div>

        <!-- Agenda / Distribución por Eje -->
        <div class="section-header">
            <h3 class="section-title"><span class="section-title-bar"></span>Gestión de Agenda · Distribución por Eje</h3>
        </div>
        <div class="charts-section charts-13">
            <div class="chart-card">
                <div class="chart-card-header">
                    <div>
                        <div class="chart-card-title">Cumplimiento vs Metas</div>
                        <div class="chart-card-sub" id="lbl-metas">Voto: 0 · Com: 0 · Org: 0</div>
                    </div>
                </div>
                <div class="compliance-grid">
                    <div class="compliance-row">
                        <div class="compliance-header">
                            <span class="compliance-name"><i class="fas fa-map" style="color:#3b82f6;margin-right:6px;"></i>Voto-Territorio</span>
                            <div class="compliance-meta"><span id="cmp-voto-val">0</span><span> / </span><span id="cmp-voto-meta">0</span><span class="compliance-pct" id="cmp-voto-pct" style="color:#3b82f6;"> 0%</span></div>
                        </div>
                        <div class="bar-track"><div class="bar-fill territorio" id="cmp-voto-bar" style="width:0%"></div></div>
                    </div>
                    <div class="compliance-row">
                        <div class="compliance-header">
                            <span class="compliance-name"><i class="fas fa-bullhorn" style="color:#8b5cf6;margin-right:6px;"></i>Comunicación</span>
                            <div class="compliance-meta"><span id="cmp-com-val">0</span><span> / </span><span id="cmp-com-meta">0</span><span class="compliance-pct" id="cmp-com-pct" style="color:#8b5cf6;"> 0%</span></div>
                        </div>
                        <div class="bar-track"><div class="bar-fill comunicacion" id="cmp-com-bar" style="width:0%"></div></div>
                    </div>
                    <div class="compliance-row">
                        <div class="compliance-header">
                            <span class="compliance-name"><i class="fas fa-sitemap" style="color:#f59e0b;margin-right:6px;"></i>Organización</span>
                            <div class="compliance-meta"><span id="cmp-org-val">0</span><span> / </span><span id="cmp-org-meta">0</span><span class="compliance-pct" id="cmp-org-pct" style="color:#f59e0b;"> 0%</span></div>
                        </div>
                        <div class="bar-track"><div class="bar-fill organizacion" id="cmp-org-bar" style="width:0%"></div></div>
                    </div>
                </div>
            </div>
            <div class="chart-card">
                <div class="chart-card-header">
                    <div><div class="chart-card-title">Tendencia Semanal por Eje</div><div class="chart-card-sub">Eventos agendados en las últimas semanas</div></div>
                </div>
                <div style="height:250px;"><canvas id="trend-chart"></canvas></div>
            </div>
        </div>

        <!-- Activaciones -->
        <div class="section-header">
            <h3 class="section-title"><span class="section-title-bar"></span>Activaciones en Movimiento</h3>
        </div>
        <div class="charts-section charts-equal">
            <div style="display:flex;flex-direction:column;gap:20px;">
                <div class="chart-card">
                    <div class="chart-card-header" style="margin-bottom:15px;"><div><div class="chart-card-title">Estado de Actividades</div></div></div>
                    <div class="status-cards" style="margin-bottom:0;">
                        <div class="status-card programada"><div class="s-count" id="st-prog">0</div><div class="s-label">Programadas</div></div>
                        <div class="status-card en-curso"><div class="s-count" id="st-curs">0</div><div class="s-label">En Curso</div></div>
                        <div class="status-card completada"><div class="s-count" id="st-comp">0</div><div class="s-label">Completadas</div></div>
                        <div class="status-card cancelada"><div class="s-count" id="st-canc">0</div><div class="s-label">Canceladas</div></div>
                    </div>
                </div>
                <div class="chart-card">
                    <div class="chart-card-header"><div><div class="chart-card-title">Distribución por Tipo</div></div></div>
                    <div style="height:170px;"><canvas id="act-type-chart"></canvas></div>
                </div>
            </div>
            <div class="chart-card">
                <div class="chart-card-header">
                    <div><div class="chart-card-title">Resultados de Impacto</div><div class="chart-card-sub" id="sub-impacto">Contactos vs Asistentes</div></div>
                </div>
                <div class="contact-metrics">
                    <div class="contact-metric"><div class="cm-value" id="res-cont" style="color:#3b82f6;">0</div><div class="cm-label">Contactos Nuevos</div></div>
                    <div class="contact-metric"><div class="cm-value" id="res-asis" style="color:#8b5cf6;">0</div><div class="cm-label">Asistentes</div></div>
                    <div class="contact-metric"><div class="cm-value" id="res-cpc" style="color:#ef4444;">$0</div><div class="cm-label">Costo / Contacto</div></div>
                </div>
                <div style="height:140px;margin-top:20px;"><canvas id="contacts-chart"></canvas></div>
            </div>
        </div>

        <!-- Tabla -->
        <div id="wrapper-tabla">
            <div class="section-header">
                <h3 class="section-title"><span class="section-title-bar"></span><span id="tabla-title">Desempeño por Entidad</span></h3>
            </div>
            <div class="chart-card" style="padding:0;overflow:hidden;margin-bottom:40px;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th><th id="th-entidad">Entidad</th>
                            <th>Activaciones</th><th>Contactos Nuevos</th>
                            <th>Asistentes</th><th>Costo</th>
                        </tr>
                    </thead>
                    <tbody id="table-body"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODAL METAS -->
<div class="modal-overlay" id="modal-metas" onclick="if(event.target===this)cerrarModalMetas()">
    <div class="modal-box">
        <h2 class="modal-title">Metas de Agenda</h2>
        <p style="font-size:13px;color:#666;margin-bottom:20px;">Define la cantidad de eventos esperados para el periodo.</p>
        <div class="form-group"><label>Eventos de Voto / Territorio</label><input type="number" id="m-voto" min="0"></div>
        <div class="form-group"><label>Eventos de Comunicación</label><input type="number" id="m-com" min="0"></div>
        <div class="form-group"><label>Eventos de Organización</label><input type="number" id="m-org" min="0"></div>
        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:25px;">
            <button class="btn-action btn-outline" style="padding:10px 15px;" onclick="cerrarModalMetas()">Cancelar</button>
            <button class="btn-action" style="padding:10px 20px;" onclick="guardarMetas()"><i class="fas fa-check"></i> Guardar</button>
        </div>
    </div>
</div>

<script>
// ── Config desde PHP ──────────────────────────────────────
const MI_NIVEL      = <?php echo $mi_nivel; ?>;
const API_CONTEXT   = '<?php echo $api_context; ?>';
const API_ID_BASE   = <?php echo json_encode($api_id_base); ?>;
const BADGE_BASE    = <?php echo json_encode($badge_base); ?>;
const ENTIDADES_L2  = <?php echo json_encode($mi_nivel === 3 ? $entidades2 : ($mi_nivel === 1 ? array_column($entidades2, 'nombre') : []), JSON_UNESCAPED_UNICODE); ?>;

// Switcher config PHP→JS
const SWITCHER_LABELS = <?php echo json_encode(array_map(fn($s)=>[$s[0],$s[1]], $switcher), JSON_UNESCAPED_UNICODE); ?>;

let viewMode        = SWITCHER_LABELS[0][0];
let charts          = {};
let currentMeta     = <?php echo ($init_meta_voto + $init_meta_com + $init_meta_org) ?: 100; ?>;
let currentMetaVoto = <?php echo $init_meta_voto; ?>;
let currentMetaCom  = <?php echo $init_meta_com; ?>;
let currentMetaOrg  = <?php echo $init_meta_org; ?>;

const hoy = new Date();
const MESES = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
const MESES_ABR = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];

// ── Inicializar ───────────────────────────────────────────
window.addEventListener('DOMContentLoaded', () => {
    Chart.defaults.font.family = "'Arial', sans-serif";
    Chart.defaults.color = '#888';
    const ms = document.getElementById('month-select');
    ms.innerHTML = '';
    for (let i = 0; i < 12; i++)
        ms.innerHTML += `<option value="${i+1}" ${i===hoy.getMonth()?'selected':''}>${MESES[i]}</option>`;
    updateWeeksDropdown();
    actualizarBarras(0, 0, 0);
});

// ── Semanas ───────────────────────────────────────────────
function getWeekNumber(d) {
    d = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()));
    d.setUTCDate(d.getUTCDate() + 4 - (d.getUTCDay()||7));
    const yearStart = new Date(Date.UTC(d.getUTCFullYear(),0,1));
    return Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
}
function getDateRangeOfWeek(weekNo, year) {
    let d = new Date(year, 0, 1);
    d.setDate(d.getDate() + (4 - (d.getDay()||7)) + (weekNo - 1) * 7);
    const start = new Date(d); start.setDate(d.getDate() - 3);
    const end   = new Date(d); end.setDate(d.getDate() + 3);
    return `${start.getDate()} ${MESES_ABR[start.getMonth()]} - ${end.getDate()} ${MESES_ABR[end.getMonth()]}`;
}
function updateWeeksDropdown() {
    const month = parseInt(document.getElementById('month-select').value) - 1;
    const ws = document.getElementById('week-select');
    ws.innerHTML = '<option value="todas">Todo el Mes</option>';
    let w1 = getWeekNumber(new Date(hoy.getFullYear(), month, 1));
    for (let i = 0; i < 5; i++) {
        const wn = w1 + i;
        ws.innerHTML += `<option value="${wn}">Semana ${wn} (${getDateRangeOfWeek(wn, hoy.getFullYear())})</option>`;
    }
    if (month === hoy.getMonth()) {
        const cw = getWeekNumber(hoy).toString();
        if (ws.querySelector(`option[value="${cw}"]`)) ws.value = cw;
    }
    updateDashboard();
}

// ── Level switcher ────────────────────────────────────────
// Datos para poblar el select según el nivel seleccionado
const ENTIDADES_POR_NIVEL = <?php echo json_encode([
    'estado'  => $mi_nivel <= 2 ? $entidades : ($mi_nivel === 1 ? $entidades : []),
    'region'  => $mi_nivel === 1 ? array_column($entidades2, 'nombre') : ($mi_nivel === 3 ? $entidades : []),
    'zona'    => $mi_nivel === 3 ? $entidades2 : ($mi_nivel === 4 ? array_column($entidades, 'nombre') : []),
    'seccion' => $mi_nivel === 5 ? $entidades : [],
], JSON_UNESCAPED_UNICODE); ?>;

function switchLevel(level, btn) {
    document.querySelectorAll('.level-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    viewMode = level;

    const fg = document.getElementById('entity-filter-group');
    const isResumen = (level === 'resumen');
    fg.style.display = isResumen ? 'none' : 'flex';

    if (!isResumen) {
        // Actualizar label y opciones del select según level
        const labelMap = { estado:'Estado:', region:'Región:', zona:'Zona:', seccion:'Sección:' };
        document.getElementById('entity-label').textContent = labelMap[level] || 'Entidad:';
        const sel = document.getElementById('entity-select');
        const opts = ENTIDADES_POR_NIVEL[level] || [];
        if (opts.length > 0) {
            sel.innerHTML = opts.map(e => {
                const val = typeof e === 'object' ? (e.id||e.value||e.nombre) : e;
                const lbl = typeof e === 'object' ? (e.nombre||e.label||val) : e;
                const prefix = level === 'seccion' ? 'Sección ' : '';
                return `<option value="${val}">${prefix}${lbl}</option>`;
            }).join('');
        }
    }
    updateDashboard();
}

// ── Fetch principal ───────────────────────────────────────
async function updateDashboard() {
    const mes    = document.getElementById('month-select').value;
    const semana = document.getElementById('week-select').value;
    const selId  = document.getElementById('entity-select').value;
    const mName  = document.getElementById('month-select').options[document.getElementById('month-select').selectedIndex].text;
    const tText  = semana === 'todas' ? `${mName} ${hoy.getFullYear()}` : `Semana ${semana} · ${mName}`;
    document.getElementById('dyn-time').textContent = tText;

    const isResumen = (viewMode === 'resumen');
    const entityId  = isResumen ? API_ID_BASE : selId;
    const level     = isResumen ? 'resumen' : viewMode;

    // Actualizar header dinámico
    updateHeader(level, selId, isResumen);

    try {
        const url = `../../api/dashboard/api_dashboard.php?context=${API_CONTEXT}&level=${level}&id=${encodeURIComponent(entityId)}&mes=${mes}&semana=${semana}`;
        const res  = await fetch(url);
        const data = await res.json();
        if (data && !data.error) renderUI(data, semana);
        else renderUI({}, semana);
    } catch (err) {
        console.error('Dashboard fetch error:', err);
        renderUI({}, semana);
    }
}

function updateHeader(level, selId, isResumen) {
    const badge = document.getElementById('dyn-badge');
    const title = document.getElementById('dyn-title');
    const desc  = document.getElementById('dyn-desc');
    const tablaTitle = document.getElementById('tabla-title');
    const thEnt = document.getElementById('th-entidad');
    const lblAct = document.getElementById('lbl-activos');

    if (isResumen) {
        badge.textContent = BADGE_BASE;
        title.textContent = 'Dashboard.';
        desc.textContent  = 'Resumen ejecutivo';
        const tablaMap = {1:'Estado',2:'Estado',3:'Región',4:'Zona',5:'Sección Electoral'};
        tablaTitle.textContent = 'Desempeño por '+(tablaMap[MI_NIVEL]||'Entidad');
        thEnt.textContent      = tablaMap[MI_NIVEL] || 'Entidad';
        const lbl = {1:'Estados Activos',2:'Entidades Activas',3:'Regiones Activas',4:'Zonas Activas',5:'Secciones Activas'};
        lblAct.textContent = lbl[MI_NIVEL] || 'Entidades Activas';
    } else {
        const levelLabels = { estado:'ESTATAL', region:'REGIONAL', zona:'ZONA', seccion:'SECCIÓN' };
        badge.textContent = levelLabels[level] || 'DETALLE';
        const selEl = document.getElementById('entity-select');
        const selText = selEl.options[selEl.selectedIndex]?.text || selId;
        title.textContent = selText;
        desc.textContent  = 'Análisis microsegmentado';
        const subTitles = { estado:'Municipio', region:'Zona', zona:'Municipio', seccion:'Estructura' };
        tablaTitle.textContent = 'Desempeño por '+(subTitles[level]||'Entidad');
        thEnt.textContent      = subTitles[level] || 'Entidad';
        lblAct.textContent = 'Entidades Activas';
    }
}

// ── Render UI ─────────────────────────────────────────────
function renderUI(data, semana) {
    if (!data || data.error) data = {};
    const kpis      = data.kpis      || {};
    const chartsD   = data.charts    || {};
    const tableData = data.table     || [];

    // KPIs
    document.getElementById('kpi-agenda').textContent    = kpis.eventos     || 0;
    document.getElementById('kpi-acts').textContent      = kpis.activaciones|| 0;
    document.getElementById('kpi-contactos').textContent = parseInt(kpis.contactos||0).toLocaleString('es-MX');
    document.getElementById('kpi-activos').textContent   = tableData.length;
    const inv = kpis.inversion || 0;
    document.getElementById('kpi-costo').textContent     = '$' + (inv > 1000 ? (inv/1000).toFixed(1)+'K' : Math.round(inv));

    // Barras de cumplimiento
    const ejes = chartsD.ejes || {};
    let rv=0, rc=0, ro=0;
    Object.keys(ejes).forEach(k => {
        const kl = k.toLowerCase();
        if      (kl.match(/voto|territorio/)) rv += ejes[k];
        else if (kl.match(/comunic/))         rc += ejes[k];
        else if (kl.match(/org/))             ro += ejes[k];
    });

    // Metas dinámicas: si la API devuelve meta_global, usarla
    if (data.meta_global) {
        const mTotal = data.meta_global;
        const mult   = semana === 'todas' ? 1 : 0.25;
        const mt     = mTotal * mult;
        currentMetaVoto = Math.round(mt * 0.45);
        currentMetaCom  = Math.round(mt * 0.35);
        currentMetaOrg  = Math.round(mt * 0.20);
        currentMeta = mTotal;
    }
    actualizarBarras(rv, rc, ro);

    // Estatus activaciones
    const status = chartsD.status || {};
    document.getElementById('st-prog').textContent = status['programada']  || 0;
    document.getElementById('st-curs').textContent = status['en-curso']    || 0;
    document.getElementById('st-comp').textContent = status['completada']  || 0;
    document.getElementById('st-canc').textContent = status['cancelada']   || 0;

    // Impacto
    document.getElementById('res-cont').textContent = parseInt(kpis.contactos||0).toLocaleString('es-MX');
    document.getElementById('res-asis').textContent = parseInt(kpis.asistentes||0).toLocaleString('es-MX');
    const cpc = kpis.contactos > 0 ? Math.round(inv / kpis.contactos) : 0;
    document.getElementById('res-cpc').textContent  = '$' + cpc;

    // Gráficas
    renderTrend(chartsD.tendencia);
    renderType(chartsD.tipos);
    renderContactsBar(tableData);

    // Tabla
    const tbody = document.getElementById('table-body');
    tbody.innerHTML = tableData.map((r, i) => {
        const acts  = r.acts ?? r.activaciones ?? r.total ?? r.count ?? '—';
        const cont  = parseInt(r.cont  ?? r.contactos  ?? 0);
        const asist = parseInt(r.asist ?? r.asistentes ?? 0);
        const invR  = parseFloat(r.inv ?? r.costo ?? r.inversion ?? 0);
        const costK = invR > 1000 ? '$'+(invR/1000).toFixed(1)+'K' : '$'+Math.round(invR);
        return `<tr>
            <td><span class="rank-badge ${i<3?'top':''}">${i+1}</span></td>
            <td><strong>${r.nombre || '—'}</strong></td>
            <td>${isNaN(acts) ? (acts??'—') : acts}</td>
            <td><strong style="color:#2d7a3e">${cont.toLocaleString('es-MX')}</strong></td>
            <td>${asist.toLocaleString('es-MX')}</td>
            <td>${costK}</td>
        </tr>`;
    }).join('');
}

// ── Barras ────────────────────────────────────────────────
function actualizarBarras(rv, rc, ro) {
    const mv = currentMetaVoto, mc = currentMetaCom, mo = currentMetaOrg;
    document.getElementById('lbl-metas').textContent =
        `Meta: ${mv.toLocaleString('es-MX')} Voto · ${mc.toLocaleString('es-MX')} Com · ${mo.toLocaleString('es-MX')} Org`;
    const mTotal = mv + mc + mo;
    const totReal = rv + rc + ro;
    const pctG = mTotal > 0 ? Math.min(100, Math.round((totReal/mTotal)*100)) : 0;
    document.getElementById('kpi-cump').textContent = pctG + '%';

    function fillBar(idv, idm, idp, idb, real, meta) {
        document.getElementById(idv).textContent = real;
        document.getElementById(idm).textContent = meta;
        const p = meta > 0 ? (real/meta)*100 : 0;
        const txt = (p>0&&p<1) ? p.toFixed(2) : Math.round(p);
        const ancho = (real>0&&p<2) ? 2 : p;
        document.getElementById(idp).textContent = ` ${txt}%`;
        document.getElementById(idp).style.color = p >= 100 ? '#22c55e' : p >= 50 ? '#f59e0b' : '#ef4444';
        document.getElementById(idb).style.width = Math.min(100, ancho) + '%';
    }
    fillBar('cmp-voto-val','cmp-voto-meta','cmp-voto-pct','cmp-voto-bar', rv, mv);
    fillBar('cmp-com-val', 'cmp-com-meta', 'cmp-com-pct', 'cmp-com-bar',  rc, mc);
    fillBar('cmp-org-val', 'cmp-org-meta', 'cmp-org-pct', 'cmp-org-bar',  ro, mo);
}

// ── Chart.js renders ─────────────────────────────────────
function renderTrend(rawTrend) {
    if (charts['trend']) charts['trend'].destroy();
    if (!rawTrend) rawTrend = [];
    const sems = [...new Set(rawTrend.map(r => `Sem ${r.sem}`))];
    const match = (s, pattern) => rawTrend.filter(x => `Sem ${x.sem}`===s && String(x.eje).toLowerCase().match(pattern))
                                          .reduce((sum,item) => sum+(item.total||0), 0);
    charts['trend'] = new Chart(document.getElementById('trend-chart'), {
        type:'line',
        data:{ labels:sems, datasets:[
            { label:'Voto',    data:sems.map(s=>match(s,/voto|territorio/)), borderColor:'#3b82f6', backgroundColor:'rgba(59,130,246,0.08)', tension:0.4, fill:true },
            { label:'Com.',    data:sems.map(s=>match(s,/comunic/)),         borderColor:'#8b5cf6', backgroundColor:'rgba(139,92,246,0.08)',  tension:0.4, fill:true },
            { label:'Org.',    data:sems.map(s=>match(s,/org/)),             borderColor:'#f59e0b', backgroundColor:'rgba(245,158,11,0.08)',  tension:0.4, fill:true },
        ]},
        options:{ responsive:true, maintainAspectRatio:false, plugins:{ legend:{ position:'bottom' } }, scales:{ y:{ beginAtZero:true } } }
    });
}
function renderType(tipos) {
    if (charts['type']) charts['type'].destroy();
    if (!tipos) tipos = {};
    charts['type'] = new Chart(document.getElementById('act-type-chart'), {
        type:'doughnut',
        data:{
            labels:['Territorial','Digital','Impacto','Estructura'],
            datasets:[{ data:[tipos['territorial']||0,tipos['digital']||0,tipos['simbolica']||0,tipos['estructura']||0], backgroundColor:['#2d7a3e','#4caf50','#81c784','#a5d6a7'], borderWidth:0 }]
        },
        options:{ responsive:true, maintainAspectRatio:false, cutout:'68%', plugins:{ legend:{ position:'right' } } }
    });
}
function renderContactsBar(tableData) {
    if (charts['bar']) charts['bar'].destroy();
    if (!tableData) tableData = [];
    const top = tableData.slice(0, 10);
    const labels = top.map(r => (r.nombre||'').length > 10 ? r.nombre.substring(0,10)+'..' : r.nombre);
    charts['bar'] = new Chart(document.getElementById('contacts-chart'), {
        type:'bar',
        data:{
            labels,
            datasets:[
                { label:'Nuevos',    data:top.map(r=>parseInt(r.cont??r.contactos??0)),  backgroundColor:'#2d7a3e' },
                { label:'Asistentes',data:top.map(r=>parseInt(r.asist??r.asistentes??0)),backgroundColor:'#81c784' },
            ]
        },
        options:{ responsive:true, maintainAspectRatio:false, plugins:{ legend:{ display:false } }, scales:{ x:{ grid:{ display:false } } } }
    });
}

// ── Metas ─────────────────────────────────────────────────
function abrirModalMetas() {
    document.getElementById('m-voto').value = currentMetaVoto;
    document.getElementById('m-com').value  = currentMetaCom;
    document.getElementById('m-org').value  = currentMetaOrg;
    document.getElementById('modal-metas').classList.add('open');
}
function cerrarModalMetas() { document.getElementById('modal-metas').classList.remove('open'); }
async function guardarMetas() {
    const voto = parseInt(document.getElementById('m-voto').value) || 0;
    const com  = parseInt(document.getElementById('m-com').value)  || 0;
    const org  = parseInt(document.getElementById('m-org').value)  || 0;
    if ((voto+com+org) <= 0) { alert('Ingresa al menos una meta mayor a 0.'); return; }

    // Key de estado según contexto
    let estadoKey;
    if (viewMode === 'resumen') {
        estadoKey = API_ID_BASE;
    } else {
        estadoKey = document.getElementById('entity-select').value || API_ID_BASE;
    }

    try {
        const res = await fetch(window.location.pathname, {
            method:'POST',
            headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
            body:JSON.stringify({ estado:estadoKey, meta_voto:voto, meta_com:com, meta_org:org })
        });
        const data = await res.json();
        if (data.ok) {
            currentMetaVoto = voto; currentMetaCom = com; currentMetaOrg = org; currentMeta = voto+com+org;
            cerrarModalMetas();
            actualizarBarras(
                parseInt(document.getElementById('cmp-voto-val').textContent)||0,
                parseInt(document.getElementById('cmp-com-val').textContent)||0,
                parseInt(document.getElementById('cmp-org-val').textContent)||0
            );
        } else { alert('Error: '+(data.msg||'desconocido')); }
    } catch (err) { console.error('Error guardando metas', err); }
}
</script>
</body>
</html>