<?php
if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
} else { session_start(); }

$rol = $_SESSION['user_role'] ?? '';
$user_circ = $_SESSION['user_circunscripcion'] ?? 'Circunscripción 1';
$permitidos = ['circunscripcion', 'superadmin', 'super admin', 'admin', 'coordinador_estatal'];
if (!in_array($rol, $permitidos)) { header('Location: /public/index.php'); exit(); }

$CIRCS = [
    'Circunscripción 1' => ['Baja California','Baja California Sur','Chihuahua','Durango','Jalisco','Nayarit','Sinaloa','Sonora'],
    'Circunscripción 2' => ['Aguascalientes','Coahuila','Guanajuato','Nuevo León','San Luis Potosí','Tamaulipas','Zacatecas'],
    'Circunscripción 3' => ['Campeche','Chiapas','Oaxaca','Quintana Roo','Tabasco','Veracruz','Yucatán'],
    'Circunscripción 4' => ['Ciudad de México','Guerrero','Hidalgo','Morelos','Puebla','Tlaxcala'],
    'Circunscripción 5' => ['Colima','Estado de México','Michoacán','Querétaro'],
];
$estados_circ = $CIRCS[$user_circ] ?? [];

// ── LEER METAS DIRECTAMENTE DE LA DB AL CARGAR LA PÁGINA ──
$init_meta_voto = 0;
$init_meta_com  = 0;
$init_meta_org  = 0;
try {
    $dbCfg = __DIR__ . '/../../includes/database.php';
    $cfg = file_exists($dbCfg) ? require $dbCfg : [
        'host' => 'localhost', 'database' => 'u881824383_pverde',
        'username' => 'u881824383_proyverde', 'password' => 'PProyectoverde8#', 'charset' => 'utf8mb4'
    ];
    $pdo_dash = new PDO(
        "mysql:host={$cfg['host']};dbname={$cfg['database']};charset={$cfg['charset']}",
        $cfg['username'], $cfg['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $st = $pdo_dash->prepare(
        "SELECT meta_voto, meta_com, meta_org FROM metas_estados WHERE estado = 'GLOBAL_CIRCUNSCRIPCION' LIMIT 1"
    );
    $st->execute();
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $init_meta_voto = (int)$row['meta_voto'];
        $init_meta_com  = (int)$row['meta_com'];
        $init_meta_org  = (int)$row['meta_org'];
    }
} catch (Exception $e) { /* Sin metas, se quedan en 0 */ }

// ── GUARDAR METAS (POST AJAX desde el modal) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $body = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $estado = trim($body['estado'] ?? '');
    $mv     = (int)($body['meta_voto'] ?? 0);
    $mc     = (int)($body['meta_com']  ?? 0);
    $mo     = (int)($body['meta_org']  ?? 0);
    $total  = $mv + $mc + $mo;
    if (empty($estado) || $total <= 0) {
        echo json_encode(['ok' => false, 'msg' => 'Datos inválidos.']); exit();
    }
    try {
        $pdo_dash->prepare(
            "INSERT INTO metas_estados (estado, meta, meta_voto, meta_com, meta_org, updated_at)
             VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
             ON DUPLICATE KEY UPDATE
               meta=VALUES(meta), meta_voto=VALUES(meta_voto),
               meta_com=VALUES(meta_com), meta_org=VALUES(meta_org),
               updated_at=CURRENT_TIMESTAMP"
        )->execute([$estado, $total, $mv, $mc, $mo]);
        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard - <?= htmlspecialchars($user_circ) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
  :root {
    --primary: #2d7a3e; --primary-dark: #1f5a2d; --primary-light: #4caf50;
    --accent: #e8a020; --dark: #1a1a1a; --light: #f0f7f0; --white: #ffffff;
    --success: #22c55e; --warning: #f59e0b; --danger: #ef4444; --info: #3b82f6;
    --text-muted: #888; --border: #f0f0f0;
    --shadow-sm: 0 10px 30px rgba(0,0,0,0.05);
    --shadow-hover: 0 20px 40px rgba(45, 122, 62, 0.15);
    --radius: 20px;
  }

  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: 'Arial', sans-serif; background: var(--light); color: var(--dark); overflow-x: hidden; }

  /* ── CONTAINER ── */
  .container { max-width: 100%; margin: 0; background: var(--white); min-height: 100vh; position: relative; padding-left: 330px; padding-right: 40px; padding-bottom: 60px; transition: padding-left 0.3s ease; }
  .design-text { position: fixed; left: 290px; top: 50%; writing-mode: vertical-rl; font-size: 13px; font-weight: 500; color: var(--primary); opacity: 0.3; letter-spacing: 2px; z-index: 0; pointer-events: none; }

  /* ── CONTEXT BAR ── */
  .context-bar { position: sticky; top: 0; z-index: 50; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; padding: 16px 0; margin-bottom: 20px; gap: 20px; flex-wrap: wrap; }
  .filters-left { display: flex; align-items: center; gap: 24px; flex-wrap: wrap; }
  .filter-group { display: flex; align-items: center; gap: 10px; }
  .filter-label { font-size: 11px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
  .level-switcher { display: inline-flex; background: var(--light); padding: 4px; border-radius: 12px; border: 1px solid var(--border); }
  .level-btn { background: transparent; border: none; color: #666; padding: 8px 16px; border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: 700; transition: all 0.2s; }
  .level-btn:hover { color: var(--primary); }
  .level-btn.active { background: var(--white); color: var(--dark); box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
  .ui-select { background: var(--white); border: 1.5px solid var(--border); padding: 8px 36px 8px 16px; border-radius: 10px; font-size: 13px; font-weight: 700; color: var(--dark); cursor: pointer; outline: none; transition: all 0.2s; appearance: none; background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%232d7a3e' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e"); background-repeat: no-repeat; background-position: right 12px center; background-size: 14px; min-width: 140px; }
  .ui-select:focus, .ui-select:hover { border-color: var(--primary); box-shadow: 0 4px 12px rgba(45,122,62,0.1); }
  .filters-right { display: flex; align-items: center; gap: 16px; margin-left: auto; }

  /* ── HEADER ── */
  .page-header { padding: 20px 0 30px 0; position: relative; z-index: 2; display: flex; justify-content: space-between; align-items: flex-end; }
  .ai-badge { color: var(--primary); font-size: 12px; font-weight: 800; letter-spacing: 4px; margin-bottom: 14px; display: inline-block; text-transform: uppercase; background: rgba(45,122,62,0.1); padding: 5px 15px; border-radius: 20px; }
  .main-title { font-size: 48px; font-weight: 900; line-height: 1; margin-bottom: 12px; color: var(--dark); }
  .description { color: #666; font-size: 15px; line-height: 1.6; }
  .header-actions { display: flex; gap: 12px; align-items: center; }
  .btn-action { background: var(--dark); color: white; padding: 13px 24px; border-radius: 12px; border: none; font-weight: 700; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; gap: 8px; font-size: 14px; text-decoration: none; }
  .btn-action:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.2); }
  .btn-outline { background: white; color: var(--dark); border: 2px solid #eee; }
  .btn-outline:hover { border-color: var(--dark); }

  /* ── CARDS & GRIDS ── */
  .section-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; margin-top: 36px; }
  .section-title { font-size: 20px; font-weight: 800; color: var(--dark); display: flex; align-items: center; gap: 10px; }
  .section-title-bar { display: block; width: 4px; height: 22px; background: var(--accent); border-radius: 2px; }
  .kpi-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 10px; }
  .kpi-card { background: white; border: 1px solid var(--border); border-radius: var(--radius); padding: 28px; position: relative; overflow: hidden; transition: all 0.3s ease; box-shadow: var(--shadow-sm); }
  .kpi-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-hover); border-color: var(--primary); }
  .kpi-header { display: flex; justify-content: space-between; margin-bottom: 18px; }
  .kpi-icon { width: 48px; height: 48px; background: #f4fcf6; color: var(--primary); border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 22px; }
  .kpi-icon.accent { background: #fff8ec; color: var(--accent); }
  .kpi-icon.danger { background: #fff0f0; color: var(--danger); }
  .kpi-icon.info { background: #eff6ff; color: var(--info); }
  .kpi-icon.warning { background: #fff7ed; color: var(--warning); }
  .kpi-value { font-size: 44px; font-weight: 900; color: var(--dark); line-height: 1; margin-bottom: 5px; }
  .kpi-label { font-size: 13px; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }

  .charts-section { display: grid; gap: 20px; }
  .charts-13 { grid-template-columns: 1fr 2fr; }
  .charts-equal { grid-template-columns: 1fr 1fr; }
  .chart-card { background: white; border-radius: var(--radius); padding: 28px; border: 1px solid var(--border); box-shadow: var(--shadow-sm); transition: all 0.3s; }
  .chart-card-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; }
  .chart-card-title { font-size: 16px; font-weight: 800; color: var(--dark); }
  .chart-card-sub { font-size: 12px; color: var(--text-muted); margin-top: 3px; }

  /* ── BARS & STATUS ── */
  .compliance-grid { display: flex; flex-direction: column; gap: 16px; }
  .compliance-row { display: flex; flex-direction: column; gap: 6px; }
  .compliance-header { display: flex; justify-content: space-between; align-items: center; font-size: 13px; }
  .compliance-name { font-weight: 700; color: var(--dark); }
  .compliance-meta { display: flex; gap: 10px; font-size: 12px; color: var(--text-muted); }
  .compliance-pct { font-weight: 800; }
  .bar-track { height: 10px; background: var(--border); border-radius: 6px; overflow: hidden; position: relative; }
  .bar-fill { height: 100%; border-radius: 6px; transition: width 0.8s ease; }
  .bar-fill.territorio { background: var(--info); }
  .bar-fill.comunicacion { background: #8b5cf6; }
  .bar-fill.organizacion { background: var(--accent); }

  .status-cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 16px; }
  .status-card { background: var(--light); border-radius: 12px; padding: 14px; text-align: center; border: 1.5px solid var(--border); transition: 0.2s; }
  .s-count { font-size: 28px; font-weight: 900; line-height: 1; margin-bottom: 4px; }
  .s-label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700; color: var(--text-muted); }
  .status-card.programada .s-count { color: var(--info); }
  .status-card.en-curso .s-count { color: var(--warning); }
  .status-card.completada .s-count { color: var(--success); }
  .status-card.cancelada .s-count { color: var(--danger); }

  .contact-metrics { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 14px; }
  .contact-metric { text-align: center; padding: 14px; background: var(--light); border-radius: 12px; border: 1.5px solid var(--border); }
  .cm-value { font-size: 24px; font-weight: 900; color: var(--dark); }
  .cm-label { font-size: 11px; color: var(--text-muted); margin-top: 3px; font-weight: 600; }

  /* ── DATA TABLE ── */
  .data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
  .data-table thead tr { background: var(--primary); color: white; }
  .data-table thead th { padding: 11px 14px; text-align: left; font-weight: 700; font-size: 11px; letter-spacing: 0.5px; white-space: nowrap; }
  .data-table thead th:first-child { border-radius: 10px 0 0 0; }
  .data-table thead th:last-child { border-radius: 0 10px 0 0; }
  .data-table tbody tr { border-bottom: 1px solid var(--border); transition: background 0.15s; }
  .data-table tbody tr:hover { background: var(--light); }
  .data-table tbody td { padding: 11px 14px; vertical-align: middle; }
  .rank-badge { display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 50%; font-weight: 800; font-size: 12px; background: var(--border); color: var(--text-muted); }
  .rank-badge.top { background: var(--accent); color: #1a1a1a; }
  .mini-bar { display: flex; align-items: center; gap: 8px; }
  .mini-bar-track { flex: 1; height: 6px; background: var(--border); border-radius: 3px; overflow: hidden; }
  .mini-bar-fill { height: 100%; border-radius: 3px; }

  .view-panel { display: none; }
  .view-panel.active { display: block; animation: fadeIn 0.3s ease; }
  @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

  /* ── MODALS ── */
  .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(3px); }
  .modal-overlay.open { display: flex; }
  .modal-box { background: white; width: 90%; max-width: 400px; padding: 30px; border-radius: 20px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); }
  .modal-title { font-size: 20px; font-weight: 800; margin-bottom: 20px; }
  .form-group { margin-bottom: 15px; }
  .form-group label { display: block; font-size: 12px; font-weight: 700; color: #666; margin-bottom: 6px; }
  .form-group input { width: 100%; padding: 10px 14px; border: 2px solid #eee; border-radius: 10px; font-weight: 700; color: #333; outline: none; }
  .form-group input:focus { border-color: var(--primary); }

  @media (max-width: 1024px) { .container { padding-left: 20px; padding-right: 20px; } .design-text { display: none; } }
  .empty-state { text-align: center; padding: 30px 20px; color: var(--text-muted); font-size: 14px; }
  .alert-card { border: 1.5px solid #fecaca; background: #fff8f8; overflow: hidden; max-height: 3000px; opacity: 1; transition: max-height 0.35s ease, opacity 0.25s ease, padding 0.3s ease, border-width 0.3s ease, margin 0.3s ease; }
  .alert-card.ok { border-color: var(--border); background: white; }
  .alert-card.collapsed { max-height: 0; opacity: 0; padding-top: 0; padding-bottom: 0; border-width: 0; margin-bottom: 0 !important; }
  .alertas-toggle { display: flex; align-items: center; gap: 10px; cursor: pointer; user-select: none; }
  .alertas-toggle:hover .section-title { opacity: 0.75; }
  .alertas-chevron { font-size: 13px; color: var(--text-muted); transition: transform 0.3s ease; }
  .alertas-chevron.collapsed { transform: rotate(-90deg); }
  .alerta-estado-block { margin-bottom: 18px; }
  .alerta-estado-block:last-child { margin-bottom: 0; }
  .alerta-estado-title { font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); margin-bottom: 8px; padding-bottom: 6px; border-bottom: 1px solid var(--border); }
  .alerta-row { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px dashed #f3e0e0; }
  .alerta-row:last-child { border-bottom: none; }
  .alerta-row i { color: var(--danger); font-size: 16px; width: 20px; text-align: center; flex-shrink: 0; }
  .alerta-info { display: flex; flex-direction: column; gap: 2px; }
  .alerta-info strong { font-size: 13px; color: var(--dark); }
  .alerta-info span { font-size: 12px; color: var(--danger); font-weight: 600; }
  .alerta-badge { font-size: 11px; font-weight: 800; padding: 4px 12px; border-radius: 20px; background: #fee2e2; color: #ef4444; }
  .alerta-badge.ok { background: #dcfce7; color: #16a34a; }

  @media (max-width: 768px) { .kpi-grid { grid-template-columns: 1fr 1fr; } .charts-13, .charts-equal { grid-template-columns: 1fr; } }
</style>
</head>
<body>
<?php if (file_exists(__DIR__ . '/../../includes/components/sidebar_circunscripcion.php'))
    include __DIR__ . '/../../includes/components/sidebar_circunscripcion.php'; ?>

<div class="design-text">CAMPAIGN 360</div>

<div class="container">
  <div class="context-bar">
    <div class="filters-left">
      <div class="filter-group">
        <span class="filter-label">Vista:</span>
        <div class="level-switcher">
          <button class="level-btn active" onclick="switchLevel('resumen', this)">Resumen General</button>
          <button class="level-btn" onclick="switchLevel('detalle', this)">Detalle por Estado</button>
        </div>
      </div>
      <div class="filter-group" id="entity-filter-group" style="display: none; border-left: 2px solid var(--border); padding-left: 20px;">
        <span class="filter-label" id="entity-label">Estado:</span>
        <select id="entity-select" class="ui-select" onchange="updateDashboard()">
          <?php foreach($estados_circ as $estado): ?>
            <option value="<?= htmlspecialchars($estado) ?>"><?= htmlspecialchars($estado) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="filters-right">
      <div class="filter-group">
        <span class="filter-label">Año:</span>
        <select id="year-select" class="ui-select" onchange="updateWeeksDropdown()">
          <?php $anioActual = (int)date('Y'); for ($y = 2026; $y <= max(2026, $anioActual); $y++): ?>
            <option value="<?= $y ?>" <?= $y === $anioActual ? 'selected' : '' ?>><?= $y ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="filter-group">
        <span class="filter-label">Mes:</span>
        <select id="month-select" class="ui-select" onchange="updateWeeksDropdown()">
          </select>
      </div>
      <div class="filter-group" id="week-filter-group">
        <span class="filter-label">Semana:</span>
        <select id="week-select" class="ui-select" onchange="updateDashboard()">
        </select>
      </div>
    </div>
  </div>

  <div class="view-panel active" id="main-panel">
    
    <div class="page-header">
      <div class="header-content">
        <span class="ai-badge" id="dyn-badge">CIRCUNSCRIPCIÓN · <?= count($estados_circ) ?> ESTADOS</span>
        <h1 class="main-title" id="dyn-title">Dashboard de Gestión</h1>
        <p class="description"><span id="dyn-desc">Resumen ejecutivo</span> · <span id="dyn-time" style="font-weight:700;"></span></p>
      </div>
      <div class="header-actions">
        <button class="btn-action btn-outline" onclick="updateDashboard(); cargarAlertas();"><i class="fas fa-sync-alt"></i> Actualizar</button>
      </div>
    </div>

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
        <div class="kpi-label" id="lbl-activos">Entidades Activas</div>
      </div>
      <div class="kpi-card">
        <div class="kpi-header"><div class="kpi-icon danger"><i class="fas fa-dollar-sign"></i></div></div>
        <div class="kpi-value" id="kpi-costo">$0</div>
        <div class="kpi-label">Costo Acumulado</div>
      </div>
    </div>

    <div class="section-header alertas-toggle" onclick="toggleAlertas()">
      <h3 class="section-title"><span class="section-title-bar" style="background:var(--danger);"></span>Alertas de Inactividad (72h)<i class="fas fa-chevron-down alertas-chevron" id="alertas-chevron"></i></h3>
      <span class="alerta-badge" id="alertas-badge" style="display:none;">0 alertas</span>
    </div>
    <div class="chart-card alert-card" id="alertas-card" style="margin-bottom:10px;">
      <div class="chart-card-sub" style="margin-bottom:14px;">Regiones y zonas de tu circunscripción sin ningún evento o activación registrado en las últimas 72 horas.</div>
      <div id="alertas-content"><div class="empty-state"><i class="fas fa-spinner fa-spin"></i>Cargando…</div></div>
    </div>

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
              <span class="compliance-name"><i class="fas fa-map" style="color:#3b82f6;margin-right:6px"></i>Voto-Territorio</span>
              <div class="compliance-meta"><span id="cmp-voto-val">0</span><span> / </span><span id="cmp-voto-meta">0</span><span class="compliance-pct" id="cmp-voto-pct" style="color:#3b82f6"> 0%</span></div>
            </div>
            <div class="bar-track"><div class="bar-fill territorio" id="cmp-voto-bar" style="width:0%"></div></div>
          </div>
          <div class="compliance-row">
            <div class="compliance-header">
              <span class="compliance-name"><i class="fas fa-bullhorn" style="color:#8b5cf6;margin-right:6px"></i>Comunicación</span>
              <div class="compliance-meta"><span id="cmp-com-val">0</span><span> / </span><span id="cmp-com-meta">0</span><span class="compliance-pct" id="cmp-com-pct" style="color:#8b5cf6"> 0%</span></div>
            </div>
            <div class="bar-track"><div class="bar-fill comunicacion" id="cmp-com-bar" style="width:0%"></div></div>
          </div>
          <div class="compliance-row">
            <div class="compliance-header">
              <span class="compliance-name"><i class="fas fa-sitemap" style="color:#f59e0b;margin-right:6px"></i>Organización</span>
              <div class="compliance-meta"><span id="cmp-org-val">0</span><span> / </span><span id="cmp-org-meta">0</span><span class="compliance-pct" id="cmp-org-pct" style="color:#f59e0b"> 0%</span></div>
            </div>
            <div class="bar-track"><div class="bar-fill organizacion" id="cmp-org-bar" style="width:0%"></div></div>
          </div>
        </div>
      </div>
      <div class="chart-card">
        <div class="chart-card-header">
          <div><div class="chart-card-title">Tendencia Semanal por Eje</div><div class="chart-card-sub">Eventos agendados en las últimas 4 semanas</div></div>
        </div>
        <div style="height:250px"><canvas id="trend-chart"></canvas></div>
      </div>
    </div>

    <div class="section-header">
      <h3 class="section-title"><span class="section-title-bar"></span>Activaciones en Movimiento</h3>
    </div>
    <div class="charts-section charts-equal">
      <div style="display: flex; flex-direction: column; gap: 20px;">
        <div class="chart-card">
          <div class="chart-card-header" style="margin-bottom: 15px;"><div><div class="chart-card-title">Estado de Actividades</div></div></div>
          <div class="status-cards" style="margin-bottom: 0;">
            <div class="status-card programada"><div class="s-count" id="st-prog">0</div><div class="s-label">Programadas</div></div>
            <div class="status-card en-curso"><div class="s-count" id="st-curs">0</div><div class="s-label">En Curso</div></div>
            <div class="status-card completada"><div class="s-count" id="st-comp">0</div><div class="s-label">Completadas</div></div>
            <div class="status-card cancelada"><div class="s-count" id="st-canc">0</div><div class="s-label">Canceladas</div></div>
          </div>
        </div>
        <div class="chart-card">
          <div class="chart-card-header"><div><div class="chart-card-title">Distribución por Tipo</div></div></div>
          <div style="height:170px"><canvas id="act-type-chart"></canvas></div>
        </div>
      </div>

      <div class="chart-card">
        <div class="chart-card-header">
          <div><div class="chart-card-title">Resultados de Impacto</div><div class="chart-card-sub">Top entidades (Contactos vs Asistentes)</div></div>
        </div>
        <div class="contact-metrics">
          <div class="contact-metric"><div class="cm-value" id="res-cont" style="color:#3b82f6">0</div><div class="cm-label">Contactos Nuevos</div></div>
          <div class="contact-metric"><div class="cm-value" id="res-asis" style="color:#8b5cf6">0</div><div class="cm-label">Asistentes</div></div>
          <div class="contact-metric"><div class="cm-value" id="res-cpc" style="color:#ef4444">$0</div><div class="cm-label">Costo por Contacto</div></div>
        </div>
        <div style="height:140px; margin-top:20px;"><canvas id="contacts-chart"></canvas></div>
      </div>
    </div>

    <div id="wrapper-tabla">
      <div class="section-header">
        <h3 class="section-title"><span class="section-title-bar"></span><span id="tabla-title">Desempeño por Estado</span></h3>
      </div>
      <div class="chart-card" style="padding:0;overflow:hidden; margin-bottom:40px;">
        <table class="data-table">
          <thead>
            <tr>
              <th>#</th><th id="th-entidad">Estado</th><th>Activaciones</th><th>Contactos Nuevos</th><th>Asistentes</th><th>Costo</th>
            </tr>
          </thead>
          <tbody id="table-body"></tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<script>
// ── VARIABLES GLOBALES ──
const num_estados = <?= count($estados_circ) ?>;
let viewMode = 'resumen';
let charts = {};
let currentMeta     = <?= ($init_meta_voto + $init_meta_com + $init_meta_org) ?: 100 ?>;
let currentMetaVoto = <?= $init_meta_voto ?>;
let currentMetaCom  = <?= $init_meta_com ?>;
let currentMetaOrg  = <?= $init_meta_org ?>;

const hoy = new Date();
const mesesArr = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

window.addEventListener('DOMContentLoaded', () => {
  Chart.defaults.font.family = "'Arial', sans-serif";
  Chart.defaults.color = '#888';
  
  // Poblador meses
  const ms = document.getElementById('month-select');
  ms.innerHTML = '';
  for(let i=0; i<12; i++){
    ms.innerHTML += `<option value="${i+1}" ${i===hoy.getMonth()?'selected':''}>${mesesArr[i]}</option>`;
  }
  ms.innerHTML += `<option value="todas">Todo el Año</option>`;
  updateWeeksDropdown();
  cargarAlertas();
});

// ══ ALERTAS DE INACTIVIDAD (72h) — desplegable ══
const ALERTAS_COLLAPSE_KEY = 'gb_alertas_collapsed_circ';
function toggleAlertas() {
  const card = document.getElementById('alertas-card');
  const chevron = document.getElementById('alertas-chevron');
  const collapsed = card.classList.toggle('collapsed');
  chevron.classList.toggle('collapsed', collapsed);
  localStorage.setItem(ALERTAS_COLLAPSE_KEY, collapsed ? '1' : '0');
}
(function initAlertasCollapseState() {
  if (localStorage.getItem(ALERTAS_COLLAPSE_KEY) === '1') {
    document.addEventListener('DOMContentLoaded', () => {
      document.getElementById('alertas-card')?.classList.add('collapsed');
      document.getElementById('alertas-chevron')?.classList.add('collapsed');
    });
  }
})();

async function cargarAlertas() {
  const cont = document.getElementById('alertas-content');
  try {
    const res = await fetch('../../api/alertas/procesar.php?action=inactividad&horas=72');
    const data = await res.json();
    if (!data.ok) { cont.innerHTML = '<div class="empty-state">No se pudieron cargar las alertas.</div>'; return; }
    renderAlertas(data);
  } catch (e) {
    cont.innerHTML = '<div class="empty-state">No se pudieron cargar las alertas.</div>';
  }
}

function fmtInactividad(h) {
  if (h === null || h === undefined) return 'Sin actividad registrada';
  const dias = Math.floor(h / 24);
  if (dias >= 1) return `Sin actividad hace ${dias} día${dias!==1?'s':''}`;
  return `Sin actividad hace ${Math.floor(h)}h`;
}

function renderAlertas(data) {
  const regiones = data.regiones || [];
  const zonas = data.zonas || [];
  const total = regiones.length + zonas.length;
  const badge = document.getElementById('alertas-badge');
  const card = document.getElementById('alertas-card');
  const cont = document.getElementById('alertas-content');
  if (total > 0) {
    badge.textContent = total + (total === 1 ? ' alerta' : ' alertas');
    badge.className = 'alerta-badge';
    badge.style.display = 'inline-block';
    card.classList.remove('ok');
  } else {
    badge.style.display = 'none';
    card.classList.add('ok');
    cont.innerHTML = '<div class="empty-state"><i class="fas fa-check-circle" style="color:var(--success);font-size:20px;display:block;margin-bottom:8px;"></i>Todas las regiones y zonas de tu circunscripción tienen actividad reciente.</div>';
    return;
  }

  const porEstado = {};
  regiones.forEach(r => {
    if (!porEstado[r.estado]) porEstado[r.estado] = { regiones: [], zonas: [] };
    porEstado[r.estado].regiones.push(r);
  });
  zonas.forEach(z => {
    if (!porEstado[z.estado]) porEstado[z.estado] = { regiones: [], zonas: [] };
    porEstado[z.estado].zonas.push(z);
  });

  let html = '';
  Object.keys(porEstado).sort().forEach(estado => {
    const g = porEstado[estado];
    html += `<div class="alerta-estado-block"><div class="alerta-estado-title">${estado}</div>`;
    g.regiones.forEach(r => {
      html += `<div class="alerta-row"><i class="fas fa-map-marked-alt"></i><div class="alerta-info"><strong>Región ${r.nombre}</strong><span>${fmtInactividad(r.horas_inactividad)}</span></div></div>`;
    });
    g.zonas.forEach(z => {
      html += `<div class="alerta-row"><i class="fas fa-map-pin"></i><div class="alerta-info"><strong>Zona ${z.nombre}</strong><span>${z.region_nombre} · ${fmtInactividad(z.horas_inactividad)}</span></div></div>`;
    });
    html += `</div>`;
  });
  cont.innerHTML = html;
}

function getWeekNumber(d) {
  d = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()));
  d.setUTCDate(d.getUTCDate() + 4 - (d.getUTCDay()||7));
  var yearStart = new Date(Date.UTC(d.getUTCFullYear(),0,1));
  return Math.ceil((((d - yearStart) / 86400000) + 1)/7);
}

function getDateRangeOfWeek(weekNo, year) {
  let d = new Date(year, 0, 1);
  let dayNum = d.getDay() || 7;
  d.setDate(d.getDate() + (4 - dayNum) + (weekNo - 1) * 7);

  let start = new Date(d);
  start.setDate(d.getDate() - 3); // Lunes
  let end = new Date(d);
  end.setDate(d.getDate() + 3); // Domingo

  const mesesAbrev = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
  return `${start.getDate()} ${mesesAbrev[start.getMonth()]} - ${end.getDate()} ${mesesAbrev[end.getMonth()]}`;
}

function updateWeeksDropdown() {
  const monthRaw  = document.getElementById('month-select').value;
  const ws        = document.getElementById('week-select');
  const weekGroup = document.getElementById('week-filter-group');

  // Todo el Año: no aplica selección de semana
  if (monthRaw === 'todas') {
    ws.innerHTML = '<option value="todas">Todo el Año</option>';
    if (weekGroup) weekGroup.style.display = 'none';
    updateDashboard();
    return;
  }
  if (weekGroup) weekGroup.style.display = 'flex';

  const month  = parseInt(monthRaw) - 1;
  const yearSel = parseInt(document.getElementById('year-select').value) || hoy.getFullYear();
  ws.innerHTML = '<option value="todas">Todo el Mes</option>';
  
  let firstDay = new Date(yearSel, month, 1);
  let w1 = getWeekNumber(firstDay);
  for(let i=0; i<5; i++) {
    let numSemana = w1 + i;
    let rangoFechas = getDateRangeOfWeek(numSemana, yearSel);
    ws.innerHTML += `<option value="${numSemana}">Semana ${numSemana} (${rangoFechas})</option>`;
  }
  if(yearSel === hoy.getFullYear() && month === hoy.getMonth()) {
    let currentW = getWeekNumber(hoy);
    if(ws.querySelector(`option[value="${currentW}"]`)) ws.value = currentW;
  }
  updateDashboard();
}

function switchLevel(level, btn) {
  document.querySelectorAll('.level-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  viewMode = level;
  
  if (level === 'resumen') {
    document.getElementById('entity-filter-group').style.display = 'none';
  } else {
    document.getElementById('entity-filter-group').style.display = 'flex';
  }
  updateDashboard();
}

// ── METAS ──
function abrirModalMetas() {
  document.getElementById('m-voto').value = currentMetaVoto;
  document.getElementById('m-com').value  = currentMetaCom;
  document.getElementById('m-org').value  = currentMetaOrg;
  document.getElementById('modal-metas').classList.add('open');
}

function cerrarModalMetas() { document.getElementById('modal-metas').classList.remove('open'); }

async function guardarMetas() {
  let voto = parseInt(document.getElementById('m-voto').value) || 0;
  let com  = parseInt(document.getElementById('m-com').value)  || 0;
  let org  = parseInt(document.getElementById('m-org').value)  || 0;
  if ((voto + com + org) <= 0) return alert('Ingresa al menos una meta mayor a 0.');

  let est = (viewMode === 'detalle')
    ? document.getElementById('entity-select').value
    : 'GLOBAL_CIRCUNSCRIPCION';
  if (!est) return alert('Selecciona un estado.');

  try {
    const res = await fetch(window.location.pathname, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ estado: est, meta_voto: voto, meta_com: com, meta_org: org })
    });
    const data = await res.json();
    if (data.ok) {
      // Actualizar variables globales sin recargar página
      currentMetaVoto = voto;
      currentMetaCom  = com;
      currentMetaOrg  = org;
      currentMeta     = voto + com + org;
      cerrarModalMetas();
      // Re-renderizar solo las barras con los nuevos valores
      actualizarBarras(
        parseInt(document.getElementById('cmp-voto-val').textContent)||0,
        parseInt(document.getElementById('cmp-com-val').textContent)||0,
        parseInt(document.getElementById('cmp-org-val').textContent)||0
      );
    } else {
      alert('Error: ' + (data.msg || 'desconocido'));
    }
  } catch (err) {
    console.error('Error guardando metas', err);
  }
}

// ── CORE FETCH LOGIC ──
async function updateDashboard() {
  const mSelect = document.getElementById('month-select');
  const wSelect = document.getElementById('week-select');
  const ySelect = document.getElementById('year-select');
  const selEst  = document.getElementById('entity-select').value;
  
  const targetMonth = mSelect.value;
  const targetWeek  = wSelect.value;
  const targetYear  = ySelect.value;
  
  // Headers text
  const mName = mSelect.options[mSelect.selectedIndex].text;
  const weekText = wSelect.options[wSelect.selectedIndex].text;
  let tText;
  if (targetMonth === 'todas') tText = `Año ${targetYear}`;
  else if (targetWeek === 'todas') tText = `${mName} ${targetYear}`;
  else tText = `${weekText} · ${targetYear}`;
  document.getElementById('dyn-time').textContent = tText;
  
  if(viewMode === 'resumen') {
    document.getElementById('dyn-badge').textContent = `CIRCUNSCRIPCIÓN · ${num_estados} ESTADOS`;
    document.getElementById('dyn-title').textContent = 'Dashboard de Gestión';
    document.getElementById('dyn-desc').textContent = 'Resumen ejecutivo de la región';
    document.getElementById('tabla-title').textContent = 'Desempeño por Estado';
    document.getElementById('th-entidad').textContent = 'Estado';
  } else {
    document.getElementById('dyn-badge').textContent = `DETALLE ESTATAL`;
    document.getElementById('dyn-title').textContent = selEst;
    document.getElementById('dyn-desc').textContent = 'Análisis microsegmentado';
    document.getElementById('tabla-title').textContent = 'Desempeño por Municipio';
    document.getElementById('th-entidad').textContent = 'Municipio';
  }

  try {
    const response = await fetch(`../../api/dashboard/api_dashboard.php?level=${viewMode}&id=${selEst}&mes=${targetMonth}&semana=${targetWeek}&anio=${targetYear}`);
    const data = await response.json();
    if(data.error) { console.error(data.error); return; }
    renderUI(data, targetWeek);
  } catch (error) {
    console.error("Fetch error:", error);
  }
}

function renderUI(data, targetWeek) {
  // KPIs Globales
  document.getElementById('kpi-agenda').textContent    = data.kpis.eventos;
  document.getElementById('kpi-acts').textContent      = data.kpis.activaciones;
  document.getElementById('kpi-contactos').textContent = parseInt(data.kpis.contactos).toLocaleString();
  document.getElementById('kpi-activos').textContent   = data.table.length; // Filas en tabla = entidades activas
  document.getElementById('kpi-costo').textContent     = '$' + (data.kpis.inversion > 1000 ? (data.kpis.inversion/1000).toFixed(1)+'K' : Math.round(data.kpis.inversion));

  // Metas: ya cargadas desde DB en variables globales (currentMetaVoto/Com/Org)
  let rv = 0, rc = 0, ro = 0;
  if (data.charts && data.charts.ejes) {
    Object.keys(data.charts.ejes).forEach(key => {
      let k = String(key).toLowerCase();
      if (k.includes('voto') || k.includes('territorio')) rv += data.charts.ejes[key];
      else if (k.includes('comunic')) rc += data.charts.ejes[key];
      else if (k.includes('org')) ro += data.charts.ejes[key];
    });
  }
  actualizarBarras(rv, rc, ro);

  // Estatus de Activaciones
  document.getElementById('st-prog').textContent = data.charts.status['programada'] || 0;
  document.getElementById('st-curs').textContent = data.charts.status['en-curso'] || 0;
  document.getElementById('st-comp').textContent = data.charts.status['completada'] || 0;
  document.getElementById('st-canc').textContent = data.charts.status['cancelada'] || 0;

  // Impacto
  document.getElementById('res-cont').textContent = parseInt(data.kpis.contactos).toLocaleString();
  document.getElementById('res-asis').textContent = parseInt(data.kpis.asistentes).toLocaleString();
  let cpc = data.kpis.contactos > 0 ? Math.round(data.kpis.inversion / data.kpis.contactos) : 0;
  document.getElementById('res-cpc').textContent = '$' + cpc;

  // Renderizar Gráficas
  renderTrend(data.charts.tendencia);
  renderType(data.charts.tipos);
  renderContactsBar(data.table);

  // Renderizar Tabla
  const tbody = document.getElementById('table-body');
  tbody.innerHTML = data.table.map((r, i) => {
    let acts  = r.acts  ?? r.activaciones ?? r.total ?? r.count ?? '—';
    let cont  = parseInt(r.cont  ?? r.contactos  ?? r.contactos_estimados ?? 0);
    let asist = parseInt(r.asist ?? r.asistentes ?? r.asistentes_estimados ?? 0);
    let inv   = parseFloat(r.inv ?? r.costo ?? r.inversion ?? 0);
    let costK = inv > 1000 ? '$'+(inv/1000).toFixed(1)+'K' : '$'+Math.round(inv);
    return `<tr>
      <td><span class="rank-badge ${i<3?'top':''}">${i+1}</span></td>
      <td><strong>${r.nombre}</strong></td>
      <td>${isNaN(acts) ? (acts ?? '—') : acts}</td>
      <td><strong style="color:#2d7a3e">${isNaN(cont) ? 0 : cont.toLocaleString()}</strong></td>
      <td>${isNaN(asist) ? 0 : asist.toLocaleString()}</td>
      <td>${costK}</td>
    </tr>`;
  }).join('');
}

function actualizarBarras(rv, rc, ro) {
  let mv = currentMetaVoto;
  let mc = currentMetaCom;
  let mo = currentMetaOrg;
  document.getElementById('lbl-metas').textContent =
    `Meta: ${mv.toLocaleString()} Voto · ${mc.toLocaleString()} Com · ${mo.toLocaleString()} Org`;

  let mTotal = mv + mc + mo;
  let totReal = rv + rc + ro;
  let pctGlobal = mTotal > 0 ? Math.min(100, Math.round((totReal / mTotal) * 100)) : 0;
  document.getElementById('kpi-cump').textContent = pctGlobal + '%';

  function fillBar(idv, idm, idp, idb, real, meta) {
    document.getElementById(idv).textContent = real;
    document.getElementById(idm).textContent = meta;
    let p = meta > 0 ? (real / meta) * 100 : 0;
    let txt = (p > 0 && p < 1) ? p.toFixed(2) : Math.round(p);
    let ancho = (real > 0 && p < 2) ? 2 : p;
    document.getElementById(idp).textContent = ` ${txt}%`;
    document.getElementById(idp).style.color = p >= 100 ? '#22c55e' : (p >= 50 ? '#f59e0b' : '#ef4444');
    document.getElementById(idb).style.width = Math.min(100, ancho) + '%';
  }
  fillBar('cmp-voto-val','cmp-voto-meta','cmp-voto-pct','cmp-voto-bar', rv, mv);
  fillBar('cmp-com-val', 'cmp-com-meta', 'cmp-com-pct', 'cmp-com-bar',  rc, mc);
  fillBar('cmp-org-val', 'cmp-org-meta', 'cmp-org-pct', 'cmp-org-bar',  ro, mo);
}

function renderTrend(rawTrend) {
  if(charts['trend']) charts['trend'].destroy();
  
  // Validación de seguridad por si la API envía nulo
  if (!rawTrend) rawTrend = [];
  
  let sems = [...new Set(rawTrend.map(r => `Sem ${r.sem}`))];
  
  // SOLUCIÓN GRÁFICA: Búsqueda flexible de palabras clave (evita error por acentos o mayúsculas)
  let dtV = sems.map(s => { 
      let found = rawTrend.filter(x => `Sem ${x.sem}` === s && String(x.eje).toLowerCase().match(/voto|territorio/));
      return found.reduce((sum, item) => sum + (item.total || 0), 0);
  });
  
  let dtC = sems.map(s => { 
      let found = rawTrend.filter(x => `Sem ${x.sem}` === s && String(x.eje).toLowerCase().match(/comunic/));
      return found.reduce((sum, item) => sum + (item.total || 0), 0);
  });
  
  let dtO = sems.map(s => { 
      let found = rawTrend.filter(x => `Sem ${x.sem}` === s && String(x.eje).toLowerCase().match(/org/));
      return found.reduce((sum, item) => sum + (item.total || 0), 0);
  });

  charts['trend'] = new Chart(document.getElementById('trend-chart'), {
    type: 'line',
    data: { labels: sems, datasets: [
      { label: 'Voto', data: dtV, borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,0.08)', tension: 0.4, fill: true },
      { label: 'Com.', data: dtC, borderColor: '#8b5cf6', backgroundColor: 'rgba(139,92,246,0.08)', tension: 0.4, fill: true },
      { label: 'Org.', data: dtO, borderColor: '#f59e0b', backgroundColor: 'rgba(245,158,11,0.08)', tension: 0.4, fill: true },
    ]},
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero:true } } }
  });
}

function renderType(tipos) {
  if(charts['type']) charts['type'].destroy();
  if(!tipos) tipos = {}; // Seguridad
  
  let tt = tipos['territorial'] || 0;
  let td = tipos['digital'] || 0;
  let ts = tipos['simbolica'] || 0;
  let te = tipos['estructura'] || 0;

  charts['type'] = new Chart(document.getElementById('act-type-chart'), {
    type: 'doughnut',
    data: { labels: ['Territorial','Digital','Impacto','Estructura'], datasets: [{ data: [tt,td,ts,te], backgroundColor: ['#2d7a3e','#4caf50','#81c784','#a5d6a7'], borderWidth: 0 }] },
    options: { responsive: true, maintainAspectRatio: false, cutout: '68%', plugins: { legend: { position: 'right' } } }
  });
}

function renderContactsBar(tableData) {
  if(charts['bar']) charts['bar'].destroy();
  if(!tableData) tableData = [];
  
  let top = tableData.slice(0, 10);
  let bLabels = top.map(r => r.nombre.length>10 ? r.nombre.substring(0,10)+'..' : r.nombre);
  let bdCont = top.map(r => parseInt(r.cont ?? r.contactos ?? r.contactos_estimados ?? 0));
  let bdAsis = top.map(r => parseInt(r.asist ?? r.asistentes ?? r.asistentes_estimados ?? 0));

  charts['bar'] = new Chart(document.getElementById('contacts-chart'), {
    type: 'bar',
    data: { labels: bLabels, datasets: [
      { label: 'Nuevos', data: bdCont, backgroundColor: '#2d7a3e' },
      { label: 'Asistentes', data: bdAsis, backgroundColor: '#81c784' },
    ]},
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false } } } }
  });
}

</script>
</body>
</html>