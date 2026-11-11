<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

$rol = $_SESSION['user_role'] ?? '';
$permitidos = ['admin', 'administrador', 'superadmin', 'super admin'];
if (!in_array($rol, $permitidos)) {
    header('Location: /public/index.php');
    exit();
}

// Conexión y carga de Entidades dinámicas para los filtros
$estados_db = [];
$regiones_db = [];
$zonas_db = [];

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

    // Cargar Estados únicos (usando regiones_estatales como base)
    $stmtEst = $pdo->query("SELECT DISTINCT estado FROM regiones_estatales WHERE estado IS NOT NULL ORDER BY estado ASC");
    while($row = $stmtEst->fetch()) {
        $estados_db[] = ['value' => $row['estado'], 'label' => $row['estado']];
    }

    // Cargar todas las Regiones
    $stmtReg = $pdo->query("SELECT id, nombre, estado FROM regiones_estatales ORDER BY estado, nombre ASC");
    while($row = $stmtReg->fetch()) {
        $regiones_db[] = ['value' => $row['nombre'], 'label' => $row['nombre'] . ' (' . $row['estado'] . ')'];
    }

    // Cargar todas las Zonas
    $stmtZon = $pdo->query("SELECT nombre, clave FROM zonas_estatales ORDER BY nombre ASC");
    while($row = $stmtZon->fetch()) {
        $label = $row['nombre'] . (!empty($row['clave']) ? " ({$row['clave']})" : '');
        $zonas_db[] = ['value' => $row['nombre'], 'label' => $label];
    }
} catch (PDOException $e) {
    error_log("Error cargando estructuras en Dashboard Admin: " . $e->getMessage());
}

$estados_json = count($estados_db) > 0 ? json_encode(array_merge([['value' => 'todas', 'label' => 'Todos los Estados']], $estados_db)) : '[]';
$regiones_json = count($regiones_db) > 0 ? json_encode(array_merge([['value' => 'todas', 'label' => 'Todas las Regiones']], $regiones_db)) : '[]';
$zonas_json = count($zonas_db) > 0 ? json_encode(array_merge([['value' => 'todas', 'label' => 'Todas las Zonas']], $zonas_db)) : '[]';

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Administrador - Proyecto Verde</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<style>
  :root { --primary: #2d7a3e; --primary-dark: #1f5a2d; --primary-light: #4caf50; --accent: #e8a020; --dark: #1a1a1a; --light: #f0f7f0; --white: #ffffff; --success: #22c55e; --warning: #f59e0b; --danger: #ef4444; --info: #3b82f6; --text-muted: #888; --border: #f0f0f0; --shadow-sm: 0 10px 30px rgba(0,0,0,0.05); --shadow-hover: 0 20px 40px rgba(45, 122, 62, 0.15); --radius: 20px; }
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: 'Arial', sans-serif; background: var(--light); color: var(--dark); overflow-x: hidden; }
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

  /* ── UI ELEMENTS ── */
  .page-header { padding: 20px 0 30px 0; position: relative; z-index: 2; display: flex; justify-content: space-between; align-items: flex-end; }
  .ai-badge { color: var(--primary); font-size: 12px; font-weight: 800; letter-spacing: 4px; margin-bottom: 14px; display: inline-block; text-transform: uppercase; background: rgba(45,122,62,0.1); padding: 5px 15px; border-radius: 20px; }
  .main-title { font-size: 48px; font-weight: 900; line-height: 1; margin-bottom: 12px; color: var(--dark); }
  .description { color: #666; font-size: 15px; line-height: 1.6; }
  .header-actions { display: flex; gap: 12px; align-items: center; }
  .btn-action { background: var(--dark); color: white; padding: 13px 24px; border-radius: 12px; border: none; font-weight: 700; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; gap: 8px; font-size: 14px; text-decoration: none; }
  .btn-action:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.2); }
  .btn-outline { background: white; color: var(--dark); border: 2px solid #eee; }
  .btn-outline:hover { border-color: var(--dark); }
  .section-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; margin-top: 36px; }
  .section-title { font-size: 20px; font-weight: 800; color: var(--dark); display: flex; align-items: center; gap: 10px; }
  .section-title-bar { display: block; width: 4px; height: 22px; background: var(--accent); border-radius: 2px; }
  .section-sub { font-size: 13px; color: var(--text-muted); font-weight: 500; }
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
  .chart-card:hover { box-shadow: var(--shadow-hover); }
  .chart-card-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; }
  .chart-card-title { font-size: 16px; font-weight: 800; color: var(--dark); }
  .chart-card-sub { font-size: 12px; color: var(--text-muted); margin-top: 3px; }
  .chart-badge { font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 8px; }
  .badge-green { background: #dcfce7; color: #15803d; }
  .compliance-grid { display: flex; flex-direction: column; gap: 16px; }
  .compliance-row { display: flex; flex-direction: column; gap: 6px; }
  .compliance-header { display: flex; justify-content: space-between; align-items: center; font-size: 13px; }
  .compliance-name { font-weight: 700; color: var(--dark); }
  .compliance-meta { display: flex; gap: 10px; font-size: 12px; color: var(--text-muted); }
  .compliance-pct { font-weight: 800; }
  .bar-track { height: 10px; background: var(--border); border-radius: 6px; overflow: hidden; position: relative; }
  .bar-fill { height: 100%; border-radius: 6px; transition: all 0.8s ease; } 
  .bar-fill.territorio { background: var(--info); }
  .bar-fill.comunicacion { background: #8b5cf6; }
  .bar-fill.organizacion { background: var(--accent); }
  .contact-metrics { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 14px; }
  .contact-metric { text-align: center; padding: 14px; background: var(--light); border-radius: 12px; border: 1.5px solid var(--border); }
  .cm-value { font-size: 24px; font-weight: 900; color: var(--dark); }
  .cm-label { font-size: 11px; color: var(--text-muted); margin-top: 3px; font-weight: 600; }
  .status-cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 16px; }
  .status-card { background: var(--light); border-radius: 12px; padding: 14px; text-align: center; border: 1.5px solid var(--border); transition: 0.2s; }
  .status-card:hover { border-color: var(--primary); }
  .s-count { font-size: 28px; font-weight: 900; line-height: 1; margin-bottom: 4px; }
  .s-label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700; color: var(--text-muted); }
  .status-card.programada .s-count { color: var(--info); }
  .status-card.en-curso .s-count { color: var(--warning); }
  .status-card.completada .s-count { color: var(--success); }
  .status-card.cancelada .s-count { color: var(--danger); }
  .data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
  .data-table thead tr { background: var(--primary); color: white; }
  .data-table thead th { padding: 11px 14px; text-align: left; font-weight: 700; font-size: 11px; letter-spacing: 0.5px; white-space: nowrap; }
  .data-table thead th:first-child { border-radius: 10px 0 0 0; }
  .data-table thead th:last-child { border-radius: 0 10px 0 0 0; }
  .data-table tbody tr { border-bottom: 1px solid var(--border); transition: background 0.15s; }
  .data-table tbody tr:hover { background: var(--light); }
  .data-table tbody td { padding: 11px 14px; vertical-align: middle; }
  .rank-badge { display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 50%; font-weight: 800; font-size: 12px; background: var(--border); color: var(--text-muted); }
  .rank-badge.top { background: var(--accent); color: #1a1a1a; }
  .mini-bar { display: flex; align-items: center; gap: 8px; }
  .mini-bar-track { flex: 1; height: 6px; background: var(--border); border-radius: 3px; overflow: hidden; }
  .mini-bar-fill { height: 100%; border-radius: 3px; }
  .pill { display: inline-block; padding: 3px 11px; border-radius: 20px; font-size: 11px; font-weight: 700; }
  .pill.green { background: #1dfa69;}
  .pill.yellow { background: #ffea07; }
  .pill.red { background: #b91c1c; }
  .view-panel { display: none; }
  .view-panel.active { display: block; }
  .dyna-val { transition: opacity 0.2s ease-in-out; }

  /* ── MODALES ── */
  .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(3px); }
  .modal-overlay.open { display: flex; }
  .modal-box { background: white; width: 90%; max-width: 400px; padding: 30px; border-radius: 20px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); }
  .modal-title { font-size: 20px; font-weight: 800; margin-bottom: 20px; }
  .form-group-m { margin-bottom: 15px; }
  .form-group-m label { display: block; font-size: 12px; font-weight: 700; color: #666; margin-bottom: 6px; }
  .form-group-m input { width: 100%; padding: 10px 14px; border: 2px solid #eee; border-radius: 10px; font-weight: 700; color: #333; outline: none; transition: 0.3s; }
  .form-group-m input:focus { border-color: var(--primary); }

  @media (max-width: 1200px) { .charts-13 { grid-template-columns: 1fr; } .kpi-grid { grid-template-columns: repeat(2, 1fr); } }
  @media (max-width: 1024px) { .container { padding-left: 20px; padding-right: 20px; } .design-text { display: none; } }
  @media (max-width: 768px) { 
    .kpi-grid { grid-template-columns: 1fr 1fr; } 
    .charts-equal { grid-template-columns: 1fr; } 
    .status-cards { grid-template-columns: repeat(2, 1fr); } 
    .main-title { font-size: 34px; } 
    .page-header { flex-direction: column; align-items: flex-start; gap: 16px; } 
    .filters-left, .context-bar { flex-direction: column; align-items: flex-start; }
    .filters-right { margin-left: 0; }
    #entity-filter-group { border-left: none; padding-left: 0; }
  }
</style>
</head>
<body>
<?php include __DIR__ . '/../../includes/components/sidebar_admin.php'; ?>

<div class="design-text">CAMPAIGN 360</div>

<div class="container">

  <div class="context-bar">
    <div class="filters-left">
      <div class="filter-group">
        <span class="filter-label">Nivel:</span>
        <div class="level-switcher">
          <button class="level-btn active" onclick="switchLevel('circunscripcion', this)">Global</button>
          <button class="level-btn" onclick="switchLevel('estatal', this)">Estatal</button>
          <button class="level-btn" onclick="switchLevel('regional', this)">Regional</button>
          <button class="level-btn" onclick="switchLevel('zona', this)">Zona</button>
        </div>
      </div>

      <div class="filter-group" id="entity-filter-group" style="display: none; border-left: 2px solid var(--border); padding-left: 20px;">
        <span class="filter-label" id="entity-label">Entidad:</span>
        <select id="entity-select" class="ui-select" onchange="handleEntityChange(this)"></select>
      </div>
    </div>

    <div class="filters-right">
      <div class="filter-group">
        <span class="filter-label">Mes:</span>
        <select id="month-select" class="ui-select" onchange="updateWeeksDropdown()"></select>
      </div>

      <div class="filter-group">
        <span class="filter-label">Semana:</span>
        <select id="week-select" class="ui-select" onchange="fetchDashboardData()"></select>
      </div>
    </div>
  </div>

  <div class="view-panel active" id="panel-circunscripcion">
    <div class="page-header">
      <div class="header-content">
        <span class="ai-badge">ADMINISTRADOR GLOBAL</span>
        <h1 class="main-title">Dashboard General</h1>
        <p class="description">Resumen de Circunscripción · <span class="time-text">Cargando...</span></p>
      </div>
      <div class="header-actions">
        <button class="btn-action btn-outline" onclick="fetchDashboardData()"><i class="fas fa-sync-alt"></i> Actualizar</button>
        <button class="btn-action" onclick="generarPDF()"><i class="fas fa-file-alt"></i> Reportes</button>
      </div>
    </div>

    <div class="kpi-grid">
      <div class="kpi-card"><div class="kpi-header"><div class="kpi-icon accent"><i class="fas fa-calendar-alt"></i></div></div><div class="kpi-value dyna-val" id="circ-kpi-ev">0</div><div class="kpi-label">Eventos Totales</div></div>
      <div class="kpi-card"><div class="kpi-header"><div class="kpi-icon"><i class="fas fa-bolt"></i></div></div><div class="kpi-value dyna-val" id="circ-kpi-act">0</div><div class="kpi-label">Activaciones</div></div>
      <div class="kpi-card"><div class="kpi-header"><div class="kpi-icon info"><i class="fas fa-user-plus"></i></div></div><div class="kpi-value dyna-val" id="circ-kpi-con">0</div><div class="kpi-label">Contactos Nuevos</div></div>
      <div class="kpi-card"><div class="kpi-header"><div class="kpi-icon warning"><i class="fas fa-clipboard-check"></i></div></div><div class="kpi-value dyna-val" id="circ-kpi-cum">0%</div><div class="kpi-label">Cumplimiento Agenda</div></div>
      <div class="kpi-card"><div class="kpi-header"><div class="kpi-icon"><i class="fas fa-map"></i></div></div><div class="kpi-value dyna-val" id="circ-kpi-zon">0</div><div class="kpi-label">Estatales Activos</div></div>
      <div class="kpi-card"><div class="kpi-header"><div class="kpi-icon danger"><i class="fas fa-dollar-sign"></i></div></div><div class="kpi-value dyna-val money-k" id="circ-kpi-cos">$0</div><div class="kpi-label">Inversión Acumulada</div></div>
    </div>

    <div class="section-header">
      <h3 class="section-title"><span class="section-title-bar"></span>Distribución de Agenda Global</h3>
    </div>
    <div class="charts-section charts-13">
      <div class="chart-card">
        <div class="chart-card-header">
          <div><div class="chart-card-title time-title-1">Cumplimiento por Eje</div><div class="chart-card-sub">Regla 45-35-20</div></div>
          <div style="display:flex; align-items:center; gap:8px;">
              <span class="chart-badge badge-green" id="circ-chart-badge">0% global</span>
          </div>
        </div>
        <div class="compliance-grid">
          <div class="compliance-row">
            <div class="compliance-header"><span class="compliance-name"><i class="fas fa-map" style="color:#3b82f6;margin-right:6px"></i>Voto-Territorio</span><div class="compliance-meta"><span class="dyna-val" id="circ-comp-ev1">0</span> eventos<span class="compliance-pct" id="circ-pct-ev1" style="color:#3b82f6">0%</span></div></div>
            <div class="bar-track"><div class="bar-fill territorio" id="circ-bar-ev1" style="width:0%"></div></div>
          </div>
          <div class="compliance-row">
            <div class="compliance-header"><span class="compliance-name"><i class="fas fa-bullhorn" style="color:#8b5cf6;margin-right:6px"></i>Comunicación</span><div class="compliance-meta"><span class="dyna-val" id="circ-comp-ev2">0</span> eventos<span class="compliance-pct" id="circ-pct-ev2" style="color:#8b5cf6">0%</span></div></div>
            <div class="bar-track"><div class="bar-fill comunicacion" id="circ-bar-ev2" style="width:0%"></div></div>
          </div>
          <div class="compliance-row">
            <div class="compliance-header"><span class="compliance-name"><i class="fas fa-sitemap" style="color:#f59e0b;margin-right:6px"></i>Organización</span><div class="compliance-meta"><span class="dyna-val" id="circ-comp-ev3">0</span> eventos<span class="compliance-pct" id="circ-pct-ev3" style="color:#f59e0b">0%</span></div></div>
            <div class="bar-track"><div class="bar-fill organizacion" id="circ-bar-ev3" style="width:0%"></div></div>
          </div>
        </div>
      </div>
      <div class="chart-card">
        <div class="chart-card-header">
          <div><div class="chart-card-title">Tendencia Semanal por Eje</div><div class="chart-card-sub">Eventos completados a nivel global</div></div>
        </div>
        <div style="height:250px"><canvas id="circ-trend-chart"></canvas></div>
      </div>
    </div>

    <div class="section-header"><h3 class="section-title"><span class="section-title-bar"></span>Desempeño por Entidad (Ranking)</h3></div>
    <div class="chart-card" style="padding:0;overflow:hidden">
      <table class="data-table">
        <thead><tr><th>#</th><th>Nombre</th><th>Eventos</th><th>Cumplimiento</th><th>Activaciones</th><th>Contactos</th><th>Sub-Entidades</th><th>Estatus</th></tr></thead>
        <tbody id="circ-table-body"></tbody>
      </table>
    </div>
  </div>

  <div class="view-panel" id="panel-estatal">
    <div class="page-header">
      <div class="header-content">
        <span class="ai-badge" id="estatal-badge">ESTATAL</span>
        <h1 class="main-title" id="estatal-title">Cargando...</h1>
        <p class="description"><span id="est-desc-name">Cargando...</span> · <span class="time-text"></span></p>
      </div>
      <div class="header-actions">
        <button class="btn-action btn-outline" onclick="fetchDashboardData()"><i class="fas fa-sync-alt"></i> Actualizar</button>
        <button class="btn-action" onclick="generarPDF()"><i class="fas fa-file-alt"></i> Reportes</button>
      </div>
    </div>
    
    <div class="kpi-grid">
      <div class="kpi-card"><div class="kpi-header"><div class="kpi-icon accent"><i class="fas fa-calendar-alt"></i></div></div><div class="kpi-value dyna-val" id="est-kpi-ev">0</div><div class="kpi-label">Eventos Totales</div></div>
      <div class="kpi-card"><div class="kpi-header"><div class="kpi-icon"><i class="fas fa-bolt"></i></div></div><div class="kpi-value dyna-val" id="est-kpi-act">0</div><div class="kpi-label">Activaciones</div></div>
      <div class="kpi-card"><div class="kpi-header"><div class="kpi-icon info"><i class="fas fa-user-plus"></i></div></div><div class="kpi-value dyna-val" id="est-kpi-con">0</div><div class="kpi-label">Contactos Nuevos</div></div>
      <div class="kpi-card"><div class="kpi-header"><div class="kpi-icon warning"><i class="fas fa-clipboard-check"></i></div></div><div class="kpi-value dyna-val" id="est-kpi-cum">0%</div><div class="kpi-label">Cumplimiento Agenda</div></div>
      <div class="kpi-card"><div class="kpi-header"><div class="kpi-icon"><i class="fas fa-map"></i></div></div><div class="kpi-value dyna-val" id="est-kpi-zon">0</div><div class="kpi-label">Regiones Activas</div></div>
      <div class="kpi-card"><div class="kpi-header"><div class="kpi-icon danger"><i class="fas fa-dollar-sign"></i></div></div><div class="kpi-value dyna-val money-k" id="est-kpi-cos">$0</div><div class="kpi-label">Costo Acumulado</div></div>
    </div>

    <div class="section-header"><h3 class="section-title"><span class="section-title-bar"></span>Distribución de Agenda</h3></div>
    <div class="charts-section charts-13">
      <div class="chart-card">
        <div class="chart-card-header">
          <div><div class="chart-card-title time-title-1">Cumplimiento por Eje</div></div>
          <div style="display:flex; align-items:center; gap:8px;">
              <span class="chart-badge badge-green" id="est-chart-badge">0% global</span>
          </div>
        </div>
        <div class="compliance-grid">
          <div class="compliance-row">
            <div class="compliance-header"><span class="compliance-name"><i class="fas fa-map" style="color:#3b82f6;margin-right:6px"></i>Voto-Territorio</span><div class="compliance-meta"><span class="dyna-val" id="est-comp-ev1">0</span> eventos<span class="compliance-pct" id="est-pct-ev1" style="color:#3b82f6">0%</span></div></div>
            <div class="bar-track"><div class="bar-fill territorio" id="est-bar-ev1" style="width:0%"></div></div>
          </div>
          <div class="compliance-row">
            <div class="compliance-header"><span class="compliance-name"><i class="fas fa-bullhorn" style="color:#8b5cf6;margin-right:6px"></i>Comunicación</span><div class="compliance-meta"><span class="dyna-val" id="est-comp-ev2">0</span> eventos<span class="compliance-pct" id="est-pct-ev2" style="color:#8b5cf6">0%</span></div></div>
            <div class="bar-track"><div class="bar-fill comunicacion" id="est-bar-ev2" style="width:0%"></div></div>
          </div>
          <div class="compliance-row">
            <div class="compliance-header"><span class="compliance-name"><i class="fas fa-sitemap" style="color:#f59e0b;margin-right:6px"></i>Organización</span><div class="compliance-meta"><span class="dyna-val" id="est-comp-ev3">0</span> eventos<span class="compliance-pct" id="est-pct-ev3" style="color:#f59e0b">0%</span></div></div>
            <div class="bar-track"><div class="bar-fill organizacion" id="est-bar-ev3" style="width:0%"></div></div>
          </div>
        </div>
      </div>
      <div class="chart-card">
        <div class="chart-card-header"><div><div class="chart-card-title">Tendencia Semanal por Eje</div></div></div>
        <div style="height:250px"><canvas id="est-trend-chart"></canvas></div>
      </div>
    </div>

    <div class="section-header"><h3 class="section-title"><span class="section-title-bar"></span>Desempeño Detallado (Ranking)</h3></div>
    <div class="chart-card" style="padding:0;overflow:hidden">
      <table class="data-table">
        <thead><tr><th>#</th><th>Nombre</th><th>Eventos</th><th>Cumplimiento</th><th>Activaciones</th><th>Contactos</th><th>Sub-Entidades</th><th>Estatus</th></tr></thead>
        <tbody id="est-table-body"></tbody>
      </table>
    </div>
  </div>

  <div class="view-panel" id="panel-regional">
    <div class="page-header">
      <div class="header-content">
        <span class="ai-badge" id="region-badge">REGIÓN</span>
        <h1 class="main-title" id="region-title">Cargando...</h1>
        <p class="description"><span id="reg-desc-name">Cargando...</span> · <span class="time-text"></span></p>
      </div>
      <div class="header-actions">
        <button class="btn-action btn-outline" onclick="fetchDashboardData()"><i class="fas fa-sync-alt"></i> Actualizar</button>
        <button class="btn-action" onclick="generarPDF()"><i class="fas fa-file-alt"></i> Reportes</button>
      </div>
    </div>

    <div class="kpi-grid">
      <div class="kpi-card"><div class="kpi-header"><div class="kpi-icon accent"><i class="fas fa-calendar-alt"></i></div></div><div class="kpi-value dyna-val" id="reg-kpi-ev">0</div><div class="kpi-label">Eventos Totales</div></div>
      <div class="kpi-card"><div class="kpi-header"><div class="kpi-icon"><i class="fas fa-bolt"></i></div></div><div class="kpi-value dyna-val" id="reg-kpi-act">0</div><div class="kpi-label">Activaciones</div></div>
      <div class="kpi-card"><div class="kpi-header"><div class="kpi-icon info"><i class="fas fa-user-plus"></i></div></div><div class="kpi-value dyna-val" id="reg-kpi-con">0</div><div class="kpi-label">Contactos Nuevos</div></div>
      <div class="kpi-card"><div class="kpi-header"><div class="kpi-icon warning"><i class="fas fa-clipboard-check"></i></div></div><div class="kpi-value dyna-val" id="reg-kpi-cum">0%</div><div class="kpi-label">Cumplimiento Agenda</div></div>
      <div class="kpi-card"><div class="kpi-header"><div class="kpi-icon"><i class="fas fa-map"></i></div></div><div class="kpi-value dyna-val" id="reg-kpi-zon">0</div><div class="kpi-label">Zonas Activas</div></div>
      <div class="kpi-card"><div class="kpi-header"><div class="kpi-icon danger"><i class="fas fa-dollar-sign"></i></div></div><div class="kpi-value dyna-val money-k" id="reg-kpi-cos">$0</div><div class="kpi-label">Costo Acumulado</div></div>
    </div>

    <div class="section-header"><h3 class="section-title"><span class="section-title-bar"></span>Distribución de Agenda</h3></div>
    <div class="charts-section charts-13">
      <div class="chart-card">
        <div class="chart-card-header">
          <div><div class="chart-card-title time-title-1">Cumplimiento por Eje</div></div>
          <div style="display:flex; align-items:center; gap:8px;">
              <span class="chart-badge badge-green" id="reg-chart-badge">0% global</span>
          </div>
        </div>
        <div class="compliance-grid">
          <div class="compliance-row">
            <div class="compliance-header"><span class="compliance-name"><i class="fas fa-map" style="color:#3b82f6;margin-right:6px"></i>Voto-Territorio</span><div class="compliance-meta"><span class="dyna-val" id="reg-comp-ev1">0</span> eventos<span class="compliance-pct" id="reg-pct-ev1" style="color:#3b82f6">0%</span></div></div>
            <div class="bar-track"><div class="bar-fill territorio" id="reg-bar-ev1" style="width:0%"></div></div>
          </div>
          <div class="compliance-row">
            <div class="compliance-header"><span class="compliance-name"><i class="fas fa-bullhorn" style="color:#8b5cf6;margin-right:6px"></i>Comunicación</span><div class="compliance-meta"><span class="dyna-val" id="reg-comp-ev2">0</span> eventos<span class="compliance-pct" id="reg-pct-ev2" style="color:#8b5cf6">0%</span></div></div>
            <div class="bar-track"><div class="bar-fill comunicacion" id="reg-bar-ev2" style="width:0%"></div></div>
          </div>
          <div class="compliance-row">
            <div class="compliance-header"><span class="compliance-name"><i class="fas fa-sitemap" style="color:#f59e0b;margin-right:6px"></i>Organización</span><div class="compliance-meta"><span class="dyna-val" id="reg-comp-ev3">0</span> eventos<span class="compliance-pct" id="reg-pct-ev3" style="color:#f59e0b">0%</span></div></div>
            <div class="bar-track"><div class="bar-fill organizacion" id="reg-bar-ev3" style="width:0%"></div></div>
          </div>
        </div>
      </div>
      <div class="chart-card">
        <div class="chart-card-header"><div><div class="chart-card-title">Tendencia Semanal por Eje</div></div></div>
        <div style="height:250px"><canvas id="reg-trend-chart"></canvas></div>
      </div>
    </div>

    <div class="section-header"><h3 class="section-title"><span class="section-title-bar"></span>Desempeño Detallado (Ranking)</h3></div>
    <div class="chart-card" style="padding:0;overflow:hidden">
      <table class="data-table">
        <thead><tr><th>#</th><th>Nombre</th><th>Eventos</th><th>Cumplimiento</th><th>Activaciones</th><th>Contactos</th><th>Sub-Entidades</th><th>Estatus</th></tr></thead>
        <tbody id="reg-table-body"></tbody>
      </table>
    </div>
  </div>

  <div class="view-panel" id="panel-zona">
    <div class="page-header">
      <div class="header-content">
        <span class="ai-badge" id="zona-badge">ZONA</span>
        <h1 class="main-title" id="zona-title">Cargando...</h1>
        <p class="description"><span id="zon-desc-name">Cargando...</span> · <span class="time-text"></span></p>
      </div>
      <div class="header-actions">
        <button class="btn-action btn-outline" onclick="fetchDashboardData()"><i class="fas fa-sync-alt"></i> Actualizar</button>
        <button class="btn-action" onclick="generarPDF()"><i class="fas fa-file-alt"></i> Reportes</button>
      </div>
    </div>

    <div class="kpi-grid">
      <div class="kpi-card"><div class="kpi-header"><div class="kpi-icon accent"><i class="fas fa-calendar-alt"></i></div></div><div class="kpi-value dyna-val" id="zon-kpi-ev">0</div><div class="kpi-label">Eventos Totales</div></div>
      <div class="kpi-card"><div class="kpi-header"><div class="kpi-icon"><i class="fas fa-bolt"></i></div></div><div class="kpi-value dyna-val" id="zon-kpi-act">0</div><div class="kpi-label">Activaciones</div></div>
      <div class="kpi-card"><div class="kpi-header"><div class="kpi-icon info"><i class="fas fa-user-plus"></i></div></div><div class="kpi-value dyna-val" id="zon-kpi-con">0</div><div class="kpi-label">Contactos Nuevos</div></div>
      <div class="kpi-card"><div class="kpi-header"><div class="kpi-icon warning"><i class="fas fa-clipboard-check"></i></div></div><div class="kpi-value dyna-val" id="zon-kpi-cum">0%</div><div class="kpi-label">Cumplimiento Agenda</div></div>
      <div class="kpi-card"><div class="kpi-header"><div class="kpi-icon"><i class="fas fa-map"></i></div></div><div class="kpi-value dyna-val" id="zon-kpi-zon">0</div><div class="kpi-label">Seccionales Activas</div></div>
      <div class="kpi-card"><div class="kpi-header"><div class="kpi-icon danger"><i class="fas fa-dollar-sign"></i></div></div><div class="kpi-value dyna-val money-k" id="zon-kpi-cos">$0</div><div class="kpi-label">Costo Acumulado</div></div>
    </div>

    <div class="section-header"><h3 class="section-title"><span class="section-title-bar"></span>Distribución de Agenda</h3></div>
    <div class="charts-section charts-13">
      <div class="chart-card">
        <div class="chart-card-header">
          <div><div class="chart-card-title time-title-1">Cumplimiento por Eje</div></div>
          <div style="display:flex; align-items:center; gap:8px;">
              <span class="chart-badge badge-green" id="zon-chart-badge">0% global</span>
          </div>
        </div>
        <div class="compliance-grid">
          <div class="compliance-row">
            <div class="compliance-header"><span class="compliance-name"><i class="fas fa-map" style="color:#3b82f6;margin-right:6px"></i>Voto-Territorio</span><div class="compliance-meta"><span class="dyna-val" id="zon-comp-ev1">0</span> eventos<span class="compliance-pct" id="zon-pct-ev1" style="color:#3b82f6">0%</span></div></div>
            <div class="bar-track"><div class="bar-fill territorio" id="zon-bar-ev1" style="width:0%"></div></div>
          </div>
          <div class="compliance-row">
            <div class="compliance-header"><span class="compliance-name"><i class="fas fa-bullhorn" style="color:#8b5cf6;margin-right:6px"></i>Comunicación</span><div class="compliance-meta"><span class="dyna-val" id="zon-comp-ev2">0</span> eventos<span class="compliance-pct" id="zon-pct-ev2" style="color:#8b5cf6">0%</span></div></div>
            <div class="bar-track"><div class="bar-fill comunicacion" id="zon-bar-ev2" style="width:0%"></div></div>
          </div>
          <div class="compliance-row">
            <div class="compliance-header"><span class="compliance-name"><i class="fas fa-sitemap" style="color:#f59e0b;margin-right:6px"></i>Organización</span><div class="compliance-meta"><span class="dyna-val" id="zon-comp-ev3">0</span> eventos<span class="compliance-pct" id="zon-pct-ev3" style="color:#f59e0b">0%</span></div></div>
            <div class="bar-track"><div class="bar-fill organizacion" id="zon-bar-ev3" style="width:0%"></div></div>
          </div>
        </div>
      </div>
      <div class="chart-card">
        <div class="chart-card-header"><div><div class="chart-card-title">Tendencia Semanal por Eje</div></div></div>
        <div style="height:250px"><canvas id="zon-trend-chart"></canvas></div>
      </div>
    </div>

    <div class="section-header"><h3 class="section-title"><span class="section-title-bar"></span>Desempeño Detallado (Ranking)</h3></div>
    <div class="chart-card" style="padding:0;overflow:hidden">
      <table class="data-table">
        <thead><tr><th>#</th><th>Nombre</th><th>Eventos</th><th>Cumplimiento</th><th>Activaciones</th><th>Contactos</th><th>Sub-Entidades</th><th>Estatus</th></tr></thead>
        <tbody id="zon-table-body"></tbody>
      </table>
    </div>
  </div>

</div> 

<script>
// 1. VARIABLES GLOBALES
const hoy = new Date();
const mesesArr = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

// Estructuras dinámicas inyectadas desde PHP
const entidadesMap = {
  estatal: <?php echo $estados_json; ?>,
  regional: <?php echo $regiones_json; ?>,
  zona: <?php echo $zonas_json; ?>
};

let activePrefix = 'circ'; 
let charts = {};
let currentMeta = 100;
Chart.defaults.font.family = "'Arial', sans-serif";

// 2. LÓGICA DE TIEMPO
window.addEventListener('DOMContentLoaded', () => { 
  const ms = document.getElementById('month-select');
  ms.innerHTML = '';
  for(let i=0; i<12; i++){
    ms.innerHTML += `<option value="${i+1}" ${i===hoy.getMonth()?'selected':''}>${mesesArr[i]}</option>`;
  }
  updateWeeksDropdown(); 
});

function getWeekNumber(d) {
  d = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()));
  d.setUTCDate(d.getUTCDate() + 4 - (d.getUTCDay()||7));
  var yearStart = new Date(Date.UTC(d.getUTCFullYear(),0,1));
  return Math.ceil((((d - yearStart) / 86400000) + 1)/7);
}

function updateWeeksDropdown() {
  const month = parseInt(document.getElementById('month-select').value) - 1;
  const ws = document.getElementById('week-select');
  ws.innerHTML = '<option value="todas">Todo el Mes</option>';
  
  let firstDay = new Date(hoy.getFullYear(), month, 1);
  let w1 = getWeekNumber(firstDay);
  for(let i=0; i<5; i++) {
    ws.innerHTML += `<option value="${w1+i}">Semana ${w1+i}</option>`;
  }
  if(month === hoy.getMonth()) {
    let currentW = getWeekNumber(hoy);
    if(ws.querySelector(`option[value="${currentW}"]`)) ws.value = currentW;
  }
  fetchDashboardData();
}

// 3. LÓGICA DE FILTROS UNIFICADOS
function switchLevel(level, btn) {
  document.querySelectorAll('.level-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  
  document.querySelectorAll('.view-panel').forEach(p => p.classList.remove('active'));
  document.getElementById('panel-' + level).classList.add('active');

  const entityGroup = document.getElementById('entity-filter-group');
  const entitySelect = document.getElementById('entity-select');
  const entityLabel = document.getElementById('entity-label');

  if (level === 'circunscripcion') {
    entityGroup.style.display = 'none';
    activePrefix = 'circ';
  } else {
    entityGroup.style.display = 'flex';
    entityLabel.textContent = level === 'estatal' ? 'Estado:' : (level === 'regional' ? 'Región:' : 'Zona:');
    entitySelect.innerHTML = entidadesMap[level].map(opt => `<option value="${opt.value}">${opt.label}</option>`).join('');
    
    if (level === 'estatal') activePrefix = 'est';
    else if (level === 'regional') activePrefix = 'reg';
    else if (level === 'zona') activePrefix = 'zon';
  }
  
  if(level !== 'circunscripcion') handleEntityChange(entitySelect, level);
  else fetchDashboardData(); 
}

function handleEntityChange(selectElement, forcedLevel = null) {
  const selectedEntity = selectElement.value;
  const selectedText = selectElement.options[selectElement.selectedIndex].text;
  const currentLevel = forcedLevel || document.querySelector('.level-btn.active').textContent.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "").split(' ')[0];

  if (currentLevel === 'estatal' || currentLevel === 'estado') {
    document.getElementById('estatal-title').textContent = selectedText;
    document.getElementById('est-desc-name').textContent = selectedText;
    document.getElementById('estatal-badge').textContent = (selectedEntity === 'todas' ? 'GLOBAL' : selectedText.toUpperCase()) + ' · VISTA ESTATAL';
  } else if (currentLevel === 'regional' || currentLevel === 'regiones') {
    document.getElementById('region-title').textContent = selectedText;
    document.getElementById('reg-desc-name').textContent = selectedText;
    document.getElementById('region-badge').textContent = (selectedEntity === 'todas' ? 'GLOBAL' : selectedText.toUpperCase()) + ' · VISTA REGIÓN';
  } else if (currentLevel === 'zona' || currentLevel === 'zonas') {
    document.getElementById('zona-title').textContent = selectedText;
    document.getElementById('zon-desc-name').textContent = selectedText;
    document.getElementById('zona-badge').textContent = (selectedEntity === 'todas' ? 'GLOBAL' : selectedText.toUpperCase()) + ' · VISTA ZONA';
  }
  fetchDashboardData();
}

// 4. LÓGICA DE METAS
function calcularDistribucion() {
    let total = parseInt(document.getElementById('m-total').value) || 0;
    document.getElementById('m-voto-preview').innerHTML = `<i class="fas fa-map"></i> Voto: ${Math.round(total * 0.45)}`;
    document.getElementById('m-com-preview').innerHTML = `<i class="fas fa-bullhorn"></i> Com: ${Math.round(total * 0.35)}`;
    document.getElementById('m-org-preview').innerHTML = `<i class="fas fa-sitemap"></i> Org: ${Math.round(total * 0.20)}`;
}


// 5. RENDERIZADO REACTIVO (FETCH)
async function fetchDashboardData() {
  const mSelect = document.getElementById('month-select');
  const wSelect = document.getElementById('week-select');
  const monthName = mSelect.options[mSelect.selectedIndex].text;
  const weekVal = wSelect.value;
  const isMonthView = (weekVal === 'todas');
  
  const textTitle = isMonthView ? `${monthName} 2026` : `Semana ${weekVal} · ${monthName} 2026`;
  document.querySelectorAll('.time-text').forEach(el => el.textContent = textTitle);
  document.querySelectorAll('.time-title-1').forEach(el => el.textContent = isMonthView ? 'Cumplimiento vs Objetivo Mensual' : 'Cumplimiento vs Objetivo Semanal');

  let levelMap = { 'circ': 'circunscripcion', 'est': 'estatal', 'reg': 'regional', 'zon': 'zona' };
  let level = levelMap[activePrefix];
  
  let entityId = (activePrefix !== 'circ') ? document.getElementById('entity-select').value : '';

  const url = `../../api/dashboard/api_dashboard.php?context=admin&level=${level}&id=${encodeURIComponent(entityId)}&mes=${mSelect.value}&semana=${weekVal}`;

  console.log("SOLICITANDO API ADMIN:", url);

  try {
    const res = await fetch(url);
    const data = await res.json();
    if(data.error) throw new Error(data.error);
    
    renderizarDatosEnVista(activePrefix, data, isMonthView);
  } catch (error) {
    console.error("🔴 Error conectando con API:", error);
  }
}

function animateValue(id, value, isMoney = false) {
    const el = document.getElementById(id);
    if(!el) return;
    el.style.opacity = '0';
    setTimeout(() => {
        if(isMoney) {
            el.textContent = '$' + (value > 1000 ? (value/1000).toFixed(1)+'K' : Math.round(value).toLocaleString());
        } else {
            el.textContent = isNaN(value) ? value : parseInt(value).toLocaleString();
        }
        el.style.opacity = '1';
    }, 200);
}

function renderizarDatosEnVista(prefix, data, isMonthView) {
    if(!data || !data.kpis) return;

    // KPIs Generales
    animateValue(`${prefix}-kpi-ev`, data.kpis.eventos || 0);
    animateValue(`${prefix}-kpi-act`, data.kpis.activaciones || 0);
    animateValue(`${prefix}-kpi-con`, data.kpis.contactos || 0);
    
    const cump = data.kpis.cumplimiento || 0;
    animateValue(`${prefix}-kpi-cum`, cump + '%');
    
    const totalEntidades = data.table ? data.table.length : 0;
    animateValue(`${prefix}-kpi-zon`, totalEntidades);
    
    animateValue(`${prefix}-kpi-cos`, data.kpis.inversion || 0, true);

    const badgeChart = document.getElementById(`${prefix}-chart-badge`);
    if(badgeChart) badgeChart.textContent = cump + '% global';

    // Manejo de Metas
    currentMeta = data.meta_global ? parseInt(data.meta_global) : 1000; // Por defecto mayor para el admin
    const mult = isMonthView ? 1 : 0.25; 
    const metaBase = currentMeta * mult;
    
    const metaVoto = Math.round(metaBase * 0.45);
    const metaCom = Math.round(metaBase * 0.35);
    const metaOrg = Math.round(metaBase * 0.20);

    let rv=0, rc=0, ro=0;
    if(data.charts && data.charts.ejes) {
       Object.keys(data.charts.ejes).forEach(key => {
          let strKey = String(key).toLowerCase();
          if (strKey.includes('voto') || strKey.includes('territorio')) rv += data.charts.ejes[key];
          else if (strKey.includes('comunic')) rc += data.charts.ejes[key];
          else if (strKey.includes('org')) ro += data.charts.ejes[key];
       });
    }

    const calcBar = (real, meta, pctId, barId, evId) => {
        const elEv = document.getElementById(evId);
        if(elEv) elEv.innerHTML = `<span class="dyna-val" style="font-weight:900;">${real}</span> <span style="font-size:11px; color:var(--primary); font-weight:bold;">/ ${meta}</span>`;
        
        let p = meta > 0 ? Math.min((real/meta)*100, 100) : 0;
        let elPct = document.getElementById(pctId);
        if(elPct) elPct.textContent = Math.round(p) + '% ' + (p>=100?'✓':'');
        
        let elBar = document.getElementById(barId);
        if(elBar) {
            elBar.style.width = '0%';
            if (p >= 100) elBar.style.backgroundColor = 'var(--success)';
            else elBar.style.backgroundColor = ''; 
            setTimeout(() => elBar.style.width = p + '%', 150);
        }
    };

    calcBar(rv, metaVoto, `${prefix}-pct-ev1`, `${prefix}-bar-ev1`, `${prefix}-comp-ev1`);
    calcBar(rc, metaCom,  `${prefix}-pct-ev2`, `${prefix}-bar-ev2`, `${prefix}-comp-ev2`);
    calcBar(ro, metaOrg,  `${prefix}-pct-ev3`, `${prefix}-bar-ev3`, `${prefix}-comp-ev3`);

    // Tablas y Gráficos
    renderTableReal(prefix, data.table || []);
    initChartsReal(prefix, data);
}

function renderTableReal(prefix, tableData) {
  const body = document.getElementById(`${prefix}-table-body`);
  if(!body) return;
  
  const sorted = [...tableData].sort((a, b) => (b.contactos||0) - (a.contactos||0));
  
  body.innerHTML = sorted.map((row, i) => {
    let cump = row.cumplimiento || row.cump || 0;
    let color = cump >= 80 ? '#22c55e' : (cump >= 70 ? '#f59e0b' : '#ef4444');
    let pill = cump >= 80 ? '<span class="pill green"></span>' : (cump >= 70 ? '<span class="pill yellow"></span>' : '<span class="pill red"></span>');

    return `
    <tr>
      <td><span class="rank-badge ${i < 3 ? 'top' : ''}">${i + 1}</span></td>
      <td><strong>${row.nombre || 'N/A'}</strong></td>
      <td>${row.eventos || row.acts || 0}</td>
      <td><div class="mini-bar"><div class="mini-bar-track"><div class="mini-bar-fill" style="width:${cump}%;background:${color}"></div></div><span style="font-size:12px;font-weight:800;min-width:32px">${cump}%</span></div></td>
      <td>${row.activaciones || 0}</td>
      <td><strong style="color:#2d7a3e">${(row.contactos || row.cont || 0).toLocaleString()}</strong></td>
      <td>${row.sub_entidades || row.seccionales || '-'}</td>
      <td>${pill}</td> 
    </tr>
  `}).join('');
}

function initChartsReal(prefix, data) {
  if (charts[`${prefix}t`]) charts[`${prefix}t`].destroy();

  const trendEl = document.getElementById(`${prefix}-trend-chart`);
  if (trendEl && data.charts && data.charts.tendencia) {
      let rawTrend = data.charts.tendencia;
      let sems = [...new Set(rawTrend.map(r => r.sem ? `Sem ${r.sem}` : r.fecha))];

      let dtV = sems.map(s => {
          let found = rawTrend.filter(x => (x.sem ? `Sem ${x.sem}` : x.fecha) === s && String(x.eje||'').toLowerCase().match(/voto|territorio/));
          return found.reduce((sum, item) => sum + (item.total || 0), 0);
      });
      let dtC = sems.map(s => {
          let found = rawTrend.filter(x => (x.sem ? `Sem ${x.sem}` : x.fecha) === s && String(x.eje||'').toLowerCase().match(/comunic/));
          return found.reduce((sum, item) => sum + (item.total || 0), 0);
      });
      let dtO = sems.map(s => {
          let found = rawTrend.filter(x => (x.sem ? `Sem ${x.sem}` : x.fecha) === s && String(x.eje||'').toLowerCase().match(/org/));
          return found.reduce((sum, item) => sum + (item.total || 0), 0);
      });

      charts[`${prefix}t`] = new Chart(trendEl, {
          type: 'line',
          data: { labels: sems, datasets: [
              { label: 'Voto', data: dtV, borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,0.08)', tension: 0.4, fill: true },
              { label: 'Com.', data: dtC, borderColor: '#8b5cf6', backgroundColor: 'rgba(139,92,246,0.08)', tension: 0.4, fill: true },
              { label: 'Org.', data: dtO, borderColor: '#f59e0b', backgroundColor: 'rgba(245,158,11,0.08)', tension: 0.4, fill: true },
          ]},
          options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero:true } } }
      });
  }
}

// 6. EXPORTACIÓN PDF
function generarPDF() {
    const panelActivo = document.querySelector('.view-panel.active');
    const prefix = panelActivo.id.split('-')[1].substring(0, 4); // "circ", "esta", "regi", "zona"
    const realPrefix = activePrefix; // Más seguro usar la variable global
    
    const tituloBase = panelActivo.querySelector('.main-title').innerText.trim();
    const periodo = panelActivo.querySelector('.time-text').innerText.trim();
    const nombreVista = document.querySelector('.level-btn.active').innerText.trim();
    const nombreArchivo = `Reporte_${nombreVista}_${tituloBase}.pdf`.replace(/\s+/g, '_');
    
    const kpiEv = document.getElementById(`${realPrefix}-kpi-ev`).innerText;
    const kpiAct = document.getElementById(`${realPrefix}-kpi-act`).innerText;
    const kpiCon = document.getElementById(`${realPrefix}-kpi-con`).innerText;
    const kpiCum = document.getElementById(`${realPrefix}-kpi-cum`).innerText;

    const tableRows = Array.from(document.getElementById(`${realPrefix}-table-body`).querySelectorAll('tr'));
    
    let tableHTML = `
        <table style="width: 100%; border-collapse: collapse; font-size: 12px; margin-top: 10px;">
            <thead>
                <tr style="background-color: #e0e0e0; color: #333; text-align: left;">
                    <th style="border: 1px solid #ccc; padding: 8px;">#</th>
                    <th style="border: 1px solid #ccc; padding: 8px;">Nombre</th>
                    <th style="border: 1px solid #ccc; padding: 8px;">Eventos</th>
                    <th style="border: 1px solid #ccc; padding: 8px;">Cumplimiento</th>
                    <th style="border: 1px solid #ccc; padding: 8px;">Contactos</th>
                </tr>
            </thead>
            <tbody>
    `;

    tableRows.forEach(row => {
        const cols = row.querySelectorAll('td');
        if (cols.length >= 5) {
            let textCump = cols[3].innerText.trim();
            tableHTML += `<tr>
                <td style="border: 1px solid #ccc; padding: 8px; text-align: center;">${cols[0].innerText}</td>
                <td style="border: 1px solid #ccc; padding: 8px;"><strong>${cols[1].innerText}</strong></td>
                <td style="border: 1px solid #ccc; padding: 8px; text-align: center;">${cols[2].innerText}</td>
                <td style="border: 1px solid #ccc; padding: 8px; text-align: center;">${textCump}</td>
                <td style="border: 1px solid #ccc; padding: 8px; text-align: center;">${cols[5].innerText}</td>
            </tr>`;
        }
    });
    tableHTML += `</tbody></table>`;

    const printDiv = document.createElement('div');
    printDiv.style.fontFamily = 'Arial, sans-serif';
    printDiv.style.padding = '30px';
    const dateStr = new Date().toLocaleDateString('es-MX', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' });

    printDiv.innerHTML = `
        <h1 style="text-align: center; color: #2d7a3e; margin-bottom: 5px; font-size: 24px; text-transform: uppercase;">Reporte Directivo - Admin</h1>
        <h2 style="text-align: center; margin-top: 0; margin-bottom: 25px; font-size: 20px;">${tituloBase}</h2>
        <div style="margin-bottom: 20px; font-size: 14px;">
            <p><strong>Periodo:</strong> ${periodo}</p>
            <p><strong>Emisión:</strong> ${dateStr}</p>
        </div>
        <hr style="border-top: 2px solid #2d7a3e; margin-bottom: 20px;">
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px; font-size: 13px;">
            <tr>
                <td style="border: 1px solid #ccc; padding: 12px; width: 25%;"><strong>Eventos:</strong><br><span style="font-size:18px; color:#2d7a3e;">${kpiEv}</span></td>
                <td style="border: 1px solid #ccc; padding: 12px; width: 25%;"><strong>Activaciones:</strong><br><span style="font-size:18px; color:#2d7a3e;">${kpiAct}</span></td>
                <td style="border: 1px solid #ccc; padding: 12px; width: 25%;"><strong>Contactos:</strong><br><span style="font-size:18px; color:#3b82f6;">${kpiCon}</span></td>
                <td style="border: 1px solid #ccc; padding: 12px; width: 25%;"><strong>Cumplimiento:</strong><br><span style="font-size:18px; color:#f59e0b;">${kpiCum}</span></td>
            </tr>
        </table>
        <h3 style="background-color: #f0f7f0; color: #1f5a2d; padding: 8px;">Desempeño Detallado</h3>
        ${tableHTML}
    `;

    const opt = {
        margin: [15, 15, 15, 15],
        filename: nombreArchivo,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true, logging: false },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };

    html2pdf().set(opt).from(printDiv).save();
}
</script>
</body>
</html>