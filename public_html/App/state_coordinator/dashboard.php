<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

$rol = $_SESSION['user_role'] ?? '';
$usuario_id = $_SESSION['user_id'] ?? 0;

// 1. Ampliamos los roles permitidos para incluir al coordinador estatal
$permitidos = ['admin', 'administrador', 'superadmin', 'super admin', 'coordinador_estatal'];
if (!in_array($rol, $permitidos)) {
    header('Location: /public/index.php');
    exit();
}

// 2. Obtener el estado al que pertenece el usuario
$estado_actual = $_SESSION['user_estado'] ?? 'Baja California'; // Valor por defecto para pruebas

// 3. Conexión y carga de Regiones y Zonas dinámicas
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

    // Cargar Regiones del Estado
    $stmtReg = $pdo->prepare("SELECT id, nombre FROM regiones_estatales WHERE estado = :estado ORDER BY nombre ASC");
    $stmtReg->execute([':estado' => $estado_actual]);
    while($row = $stmtReg->fetch()) {
        $regiones_db[] = ['value' => $row['nombre'], 'label' => $row['nombre']];
    }

    // Cargar Zonas del Estado (Uniendo con regiones_estatales)
    // Se incluye r.nombre (region) para poder filtrar las zonas por región en el frontend
    $stmtZon = $pdo->prepare("
        SELECT z.nombre, z.clave, r.nombre AS region
        FROM zonas_estatales z
        INNER JOIN regiones_estatales r ON z.estructura_id = r.id
        WHERE r.estado = :estado
        ORDER BY z.nombre ASC
    ");
    $stmtZon->execute([':estado' => $estado_actual]);
    while($row = $stmtZon->fetch()) {
        $label = $row['nombre'] . (!empty($row['clave']) ? " ({$row['clave']})" : '');
        $zonas_db[] = ['value' => $row['nombre'], 'label' => $label, 'region' => $row['region']];
    }
} catch (PDOException $e) {
    error_log("Error cargando estructuras en Dashboard: " . $e->getMessage());
}

// Preparamos los JSON para pasarlos a JavaScript.
// Si hay datos, agregamos la opción "Todas" al inicio.
$regiones_json = count($regiones_db) > 0 ? json_encode(array_merge([['value' => 'todas', 'label' => 'Todas las Regiones']], $regiones_db)) : '[]';
$zonas_json = count($zonas_db) > 0 ? json_encode(array_merge([['value' => 'todas', 'label' => 'Todas las Zonas']], $zonas_db)) : '[]';

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Estatal - <?php echo htmlspecialchars($estado_actual); ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<style>
  :root { --primary: #2d7a3e; --primary-dark: #1f5a2d; --primary-light: #4caf50; --accent: #e8a020; --dark: #1a1a1a; --light: #f0f7f0; --white: #ffffff; --success: #22c55e; --warning: #f59e0b; --danger: #ef4444; --info: #3b82f6; --text-muted: #888; --border: #f0f0f0; --shadow-sm: 0 10px 30px rgba(0,0,0,0.05); --shadow-hover: 0 20px 40px rgba(45, 122, 62, 0.15); --radius: 20px; }
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: 'Arial', sans-serif; background: var(--light); color: var(--dark); overflow-x: hidden; }
  .container { max-width: 100%; margin: 0; background: var(--white); min-height: 100vh; position: relative; padding-left: 330px; padding-right: 40px; padding-bottom: 60px; transition: padding-left 0.3s ease; }
  .design-text { position: fixed; left: 290px; top: 50%; writing-mode: vertical-rl; font-size: 13px; font-weight: 500; color: var(--primary); opacity: 0.3; letter-spacing: 2px; z-index: 0; pointer-events: none; }
  
  /* ── CONTEXT BAR (FILTROS UNIFICADOS) ── */
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
  .bar-fill { height: 100%; border-radius: 6px; transition: all 0.8s ease; } /* Añadido transition all para animar colores */
  .bar-fill.territorio { background: var(--info); }
  .bar-fill.comunicacion { background: #8b5cf6; }
  .bar-fill.organizacion { background: var(--accent); }
  
  /* Nuevas clases para Resultados de Impacto */
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
<?php include __DIR__ . '/../../includes/components/sidebar_ce.php'; ?>

<div class="design-text">CAMPAIGN 360</div>

<div class="container">

  <div class="context-bar">
    <div class="filters-left">
      <div class="filter-group">
        <span class="filter-label">Vista:</span>
        <div class="level-switcher">
          <button class="level-btn active" onclick="switchLevel('estatal', this)">Resumen Estatal</button>
          <button class="level-btn" onclick="switchLevel('region', this)">Regiones</button>
          <button class="level-btn" onclick="switchLevel('zona', this)">Zonas</button>
        </div>
      </div>

      <div class="filter-group" id="entity-filter-group" style="display: none; border-left: 2px solid var(--border); padding-left: 20px;">
        <span class="filter-label" id="entity-label">Entidad:</span>
        <select id="entity-select" class="ui-select" onchange="handleEntityChange(this)"></select>
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
        <select id="month-select" class="ui-select" onchange="updateWeeksDropdown()"></select>
      </div>

      <div class="filter-group" id="week-filter-group">
        <span class="filter-label">Semana:</span>
        <select id="week-select" class="ui-select" onchange="fetchDashboardData()"></select>
      </div>
      <div class="filter-group">
        <span class="filter-label">&nbsp;</span>
        <a href="metas.php" class="btn-action btn-outline" style="text-decoration:none;"><i class="fas fa-bullseye"></i> Configurar Metas</a>
      </div>
    </div>
  </div>

  <div class="view-panel active" id="panel-estatal">

    <div class="page-header">
      <div class="header-content">
        <span class="ai-badge" id="estatal-badge">ESTATAL</span>
        <h1 class="main-title" id="estatal-title"><?php echo htmlspecialchars($estado_actual); ?></h1>
        <p class="description"><span id="est-desc-name"><?php echo htmlspecialchars($estado_actual); ?></span> · <span class="time-text">Cargando...</span></p>
      </div>
      <div class="header-actions">
        <button class="btn-action btn-outline" onclick="fetchDashboardData(); cargarAlertas();"><i class="fas fa-sync-alt"></i> Actualizar</button>
        <button class="btn-action" onclick="generarPDF()"><i class="fas fa-file-alt"></i> Reportes</button>
      </div>
    </div>

    <div class="kpi-grid">
      <div class="kpi-card"><div class="kpi-header"><div class="kpi-icon accent"><i class="fas fa-calendar-alt"></i></div></div><div class="kpi-value dyna-val" id="est-kpi-ev">0</div><div class="kpi-label">Eventos Totales</div></div>
      <div class="kpi-card"><div class="kpi-header"><div class="kpi-icon"><i class="fas fa-bolt"></i></div></div><div class="kpi-value dyna-val" id="est-kpi-act">0</div><div class="kpi-label">Activaciones</div></div>
      <div class="kpi-card"><div class="kpi-header"><div class="kpi-icon info"><i class="fas fa-user-plus"></i></div></div><div class="kpi-value dyna-val" id="est-kpi-con">0</div><div class="kpi-label">Contactos Nuevos</div></div>
      <div class="kpi-card"><div class="kpi-header"><div class="kpi-icon warning"><i class="fas fa-clipboard-check"></i></div></div><div class="kpi-value dyna-val" id="est-kpi-cum">0%</div><div class="kpi-label">Cumplimiento Agenda</div></div>
      <div class="kpi-card"><div class="kpi-header"><div class="kpi-icon"><i class="fas fa-map"></i></div></div><div class="kpi-value dyna-val" id="est-kpi-zon">0</div><div class="kpi-label">Zonas / Elementos</div></div>
      <div class="kpi-card"><div class="kpi-header"><div class="kpi-icon danger"><i class="fas fa-dollar-sign"></i></div></div><div class="kpi-value dyna-val money-k" id="est-kpi-cos">$0</div><div class="kpi-label">Costo Acumulado</div></div>
    </div>

    <div class="section-header alertas-toggle" onclick="toggleAlertas()">
      <h3 class="section-title"><span class="section-title-bar" style="background:var(--danger);"></span>Alertas de Inactividad (72h)<i class="fas fa-chevron-down alertas-chevron" id="alertas-chevron"></i></h3>
      <span class="alerta-badge" id="alertas-badge" style="display:none;">0 alertas</span>
    </div>
    <div class="chart-card alert-card" id="alertas-card" style="margin-bottom:10px;">
      <div class="chart-card-sub" style="margin-bottom:14px;">Regiones y zonas de tu estado sin ningún evento o activación registrado en las últimas 72 horas.</div>
      <div id="alertas-content"><div class="empty-state"><i class="fas fa-spinner fa-spin"></i>Cargando…</div></div>
    </div>

    <div class="section-header">
      <h3 class="section-title"><span class="section-title-bar"></span>Distribución de Agenda</h3>
    </div>
    <div class="charts-section charts-13">
      <div class="chart-card">
        <div class="chart-card-header">
          <div><div class="chart-card-title time-title-1">Cumplimiento por Eje</div><div class="chart-card-sub">Regla 45-35-20</div></div>
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
        <div class="chart-card-header">
          <div><div class="chart-card-title">Tendencia Semanal por Eje</div><div class="chart-card-sub">Eventos agendados en las últimas semanas</div></div>
        </div>
        <div style="height:250px"><canvas id="est-trend-chart"></canvas></div>
      </div>
    </div>

    <div class="section-header">
      <h3 class="section-title"><span class="section-title-bar"></span>Activaciones Generales</h3>
    </div>
    <div class="charts-section charts-equal">
      <div style="display: flex; flex-direction: column; gap: 20px;">
        <div class="chart-card">
          <div class="chart-card-header" style="margin-bottom: 15px;"><div><div class="chart-card-title">Estado de Actividades</div></div></div>
          <div class="status-cards" style="margin-bottom: 0;">
            <div class="status-card programada"><div class="s-count dyna-val" id="est-st-prog">0</div><div class="s-label">Programadas</div></div>
            <div class="status-card en-curso"><div class="s-count dyna-val" id="est-st-curs">0</div><div class="s-label">En Curso</div></div>
            <div class="status-card completada"><div class="s-count dyna-val" id="est-st-comp">0</div><div class="s-label">Completadas</div></div>
            <div class="status-card cancelada"><div class="s-count dyna-val" id="est-st-canc">0</div><div class="s-label">Canceladas</div></div>
          </div>
        </div>
        <div class="chart-card">
          <div class="chart-card-header"><div><div class="chart-card-title">Porcentaje Tipo de Agenda</div></div></div>
          <div style="height:170px"><canvas id="est-act-chart"></canvas></div>
        </div>
      </div>
      <div class="chart-card">
        <div class="chart-card-header">
          <div><div class="chart-card-title">Resultados de Impacto</div><div class="chart-card-sub">Top entidades (Contactos vs Asistentes)</div></div>
        </div>
        <div class="contact-metrics">
          <div class="contact-metric"><div class="cm-value dyna-val" id="est-res-cont" style="color:#3b82f6">0</div><div class="cm-label">Contactos Nuevos</div></div>
          <div class="contact-metric"><div class="cm-value dyna-val" id="est-res-asis" style="color:#8b5cf6">0</div><div class="cm-label">Asistentes</div></div>
          <div class="contact-metric"><div class="cm-value dyna-val" id="est-res-cpc" style="color:#ef4444">$0</div><div class="cm-label">Costo por Contacto</div></div>
        </div>
        <div style="height:140px; margin-top:20px;"><canvas id="est-contacts-bar-chart"></canvas></div>
      </div>
    </div>

    <div class="section-header">
      <h3 class="section-title"><span class="section-title-bar"></span>Desempeño Detallado</h3>
      <span class="section-sub time-sub">Ranking actual</span>
    </div>
    <div class="chart-card" style="padding:0;overflow:hidden">
      <table class="data-table">
        <thead><tr><th>#</th><th>Nombre</th><th>Eventos</th><th>Cumplimiento</th><th>Activaciones</th><th>Contactos</th><th>Sub-Entidades</th><th>Estatus</th></tr></thead>
        <tbody id="est-table-body"></tbody>
      </table>
    </div>
  </div>

  <div class="view-panel" id="panel-region">
    <div class="page-header">
      <div class="header-content">
        <span class="ai-badge" id="region-badge">REGIÓN</span>
        <h1 class="main-title" id="region-title">Cargando...</h1>
        <p class="description"><span id="reg-desc-name">Cargando...</span> · <span class="time-text"></span></p>
      </div>
      <div class="header-actions">
        <button class="btn-action btn-outline" onclick="fetchDashboardData(); cargarAlertas();"><i class="fas fa-sync-alt"></i> Actualizar</button>
        <button class="btn-action" onclick="generarPDF()"><i class="fas fa-file-alt"></i> Reportes</button>
      </div>
    </div>

    <div class="kpi-grid">
      <div class="kpi-card"><div class="kpi-header"><div class="kpi-icon accent"><i class="fas fa-calendar-alt"></i></div></div><div class="kpi-value dyna-val" id="reg-kpi-ev">0</div><div class="kpi-label">Eventos Totales</div></div>
      <div class="kpi-card"><div class="kpi-header"><div class="kpi-icon"><i class="fas fa-bolt"></i></div></div><div class="kpi-value dyna-val" id="reg-kpi-act">0</div><div class="kpi-label">Activaciones</div></div>
      <div class="kpi-card"><div class="kpi-header"><div class="kpi-icon info"><i class="fas fa-user-plus"></i></div></div><div class="kpi-value dyna-val" id="reg-kpi-con">0</div><div class="kpi-label">Contactos Nuevos</div></div>
      <div class="kpi-card"><div class="kpi-header"><div class="kpi-icon warning"><i class="fas fa-clipboard-check"></i></div></div><div class="kpi-value dyna-val" id="reg-kpi-cum">0%</div><div class="kpi-label">Cumplimiento Agenda</div></div>
      <div class="kpi-card"><div class="kpi-header"><div class="kpi-icon"><i class="fas fa-map"></i></div></div><div class="kpi-value dyna-val" id="reg-kpi-zon">0</div><div class="kpi-label">Zonas / Elementos</div></div>
      <div class="kpi-card"><div class="kpi-header"><div class="kpi-icon danger"><i class="fas fa-dollar-sign"></i></div></div><div class="kpi-value dyna-val money-k" id="reg-kpi-cos">$0</div><div class="kpi-label">Costo Acumulado</div></div>
    </div>

    <div class="section-header">
      <h3 class="section-title"><span class="section-title-bar"></span>Distribución de Agenda</h3>
    </div>
    <div class="charts-section charts-13">
      <div class="chart-card">
        <div class="chart-card-header">
          <div><div class="chart-card-title time-title-1">Cumplimiento por Eje</div><div class="chart-card-sub">Regla 45-35-20</div></div>
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
        <div class="chart-card-header">
          <div><div class="chart-card-title">Tendencia Semanal por Eje</div><div class="chart-card-sub">Eventos agendados en las últimas semanas</div></div>
        </div>
        <div style="height:250px"><canvas id="reg-trend-chart"></canvas></div>
      </div>
    </div>

    <div class="section-header">
      <h3 class="section-title"><span class="section-title-bar"></span>Activaciones Generales</h3>
    </div>
    <div class="charts-section charts-equal">
      <div style="display: flex; flex-direction: column; gap: 20px;">
        <div class="chart-card">
          <div class="chart-card-header" style="margin-bottom: 15px;"><div><div class="chart-card-title">Estado de Actividades</div></div></div>
          <div class="status-cards" style="margin-bottom: 0;">
            <div class="status-card programada"><div class="s-count dyna-val" id="reg-st-prog">0</div><div class="s-label">Programadas</div></div>
            <div class="status-card en-curso"><div class="s-count dyna-val" id="reg-st-curs">0</div><div class="s-label">En Curso</div></div>
            <div class="status-card completada"><div class="s-count dyna-val" id="reg-st-comp">0</div><div class="s-label">Completadas</div></div>
            <div class="status-card cancelada"><div class="s-count dyna-val" id="reg-st-canc">0</div><div class="s-label">Canceladas</div></div>
          </div>
        </div>
        <div class="chart-card">
          <div class="chart-card-header"><div><div class="chart-card-title">Porcentaje Tipo de Agenda</div></div></div>
          <div style="height:170px"><canvas id="reg-act-chart"></canvas></div>
        </div>
      </div>
      <div class="chart-card">
        <div class="chart-card-header">
          <div><div class="chart-card-title">Resultados de Impacto</div><div class="chart-card-sub">Top entidades (Contactos vs Asistentes)</div></div>
        </div>
        <div class="contact-metrics">
          <div class="contact-metric"><div class="cm-value dyna-val" id="reg-res-cont" style="color:#3b82f6">0</div><div class="cm-label">Contactos Nuevos</div></div>
          <div class="contact-metric"><div class="cm-value dyna-val" id="reg-res-asis" style="color:#8b5cf6">0</div><div class="cm-label">Asistentes</div></div>
          <div class="contact-metric"><div class="cm-value dyna-val" id="reg-res-cpc" style="color:#ef4444">$0</div><div class="cm-label">Costo por Contacto</div></div>
        </div>
        <div style="height:140px; margin-top:20px;"><canvas id="reg-contacts-bar-chart"></canvas></div>
      </div>
    </div>

    <div class="section-header">
      <h3 class="section-title"><span class="section-title-bar"></span>Desempeño Detallado</h3>
      <span class="section-sub time-sub">Ranking actual</span>
    </div>
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
        <button class="btn-action btn-outline" onclick="fetchDashboardData(); cargarAlertas();"><i class="fas fa-sync-alt"></i> Actualizar</button>
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

    <div class="section-header">
      <h3 class="section-title"><span class="section-title-bar"></span>Distribución de Agenda</h3>
    </div>
    <div class="charts-section charts-13">
      <div class="chart-card">
        <div class="chart-card-header">
          <div><div class="chart-card-title time-title-1">Cumplimiento por Eje</div><div class="chart-card-sub">Regla 45-35-20</div></div>
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
        <div class="chart-card-header">
          <div><div class="chart-card-title">Tendencia Semanal por Eje</div><div class="chart-card-sub">Eventos agendados en las últimas semanas</div></div>
        </div>
        <div style="height:250px"><canvas id="zon-trend-chart"></canvas></div>
      </div>
    </div>

    <div class="section-header">
      <h3 class="section-title"><span class="section-title-bar"></span>Activaciones Generales</h3>
    </div>
    <div class="charts-section charts-equal">
      <div style="display: flex; flex-direction: column; gap: 20px;">
        <div class="chart-card">
          <div class="chart-card-header" style="margin-bottom: 15px;"><div><div class="chart-card-title">Estado de Actividades</div></div></div>
          <div class="status-cards" style="margin-bottom: 0;">
            <div class="status-card programada"><div class="s-count dyna-val" id="zon-st-prog">0</div><div class="s-label">Programadas</div></div>
            <div class="status-card en-curso"><div class="s-count dyna-val" id="zon-st-curs">0</div><div class="s-label">En Curso</div></div>
            <div class="status-card completada"><div class="s-count dyna-val" id="zon-st-comp">0</div><div class="s-label">Completadas</div></div>
            <div class="status-card cancelada"><div class="s-count dyna-val" id="zon-st-canc">0</div><div class="s-label">Canceladas</div></div>
          </div>
        </div>
        <div class="chart-card">
          <div class="chart-card-header"><div><div class="chart-card-title">Porcentaje Tipo de Agenda</div></div></div>
          <div style="height:170px"><canvas id="zon-act-chart"></canvas></div>
        </div>
      </div>
      <div class="chart-card">
        <div class="chart-card-header">
          <div><div class="chart-card-title">Resultados de Impacto</div><div class="chart-card-sub">Top entidades (Contactos vs Asistentes)</div></div>
        </div>
        <div class="contact-metrics">
          <div class="contact-metric"><div class="cm-value dyna-val" id="zon-res-cont" style="color:#3b82f6">0</div><div class="cm-label">Contactos Nuevos</div></div>
          <div class="contact-metric"><div class="cm-value dyna-val" id="zon-res-asis" style="color:#8b5cf6">0</div><div class="cm-label">Asistentes</div></div>
          <div class="contact-metric"><div class="cm-value dyna-val" id="zon-res-cpc" style="color:#ef4444">$0</div><div class="cm-label">Costo por Contacto</div></div>
        </div>
        <div style="height:140px; margin-top:20px;"><canvas id="zon-contacts-bar-chart"></canvas></div>
      </div>
    </div>

    <div class="section-header">
      <h3 class="section-title"><span class="section-title-bar"></span>Desempeño Detallado</h3>
      <span class="section-sub time-sub">Ranking actual</span>
    </div>
    <div class="chart-card" style="padding:0;overflow:hidden">
      <table class="data-table">
        <thead><tr><th>#</th><th>Nombre</th><th>Eventos</th><th>Cumplimiento</th><th>Activaciones</th><th>Contactos</th><th>Sub-Entidades</th><th>Estatus</th></tr></thead>
        <tbody id="zon-table-body"></tbody>
      </table>
    </div>
  </div>

<div class="modal-overlay" id="modal-metas" onclick="if(event.target===this)cerrarModalMetas()">
  <div class="modal-box">
    <h2 class="modal-title">Meta Mensual (Objetivo Macro)</h2>
    <p style="font-size:13px; color:#666; margin-bottom:20px;">
      Ingresa la meta total del mes. El sistema la dividirá automáticamente en 45-35-20, y tomará el 25% para el cálculo de las vistas semanales.
    </p>
    <div class="form-group-m">
      <label>Total de Eventos (Mes Completo)</label>
      <input type="number" id="m-total" min="0" oninput="calcularDistribucion()">
    </div>
    
    <div style="display:flex; justify-content:space-between; background:var(--light); padding:12px; border-radius:10px; font-size:12px; font-weight:700; border: 1px solid var(--border); margin-top:15px;">
       <span id="m-voto-preview" style="color:var(--info)"><i class="fas fa-map"></i> Voto: 0</span>
       <span id="m-com-preview" style="color:#8b5cf6"><i class="fas fa-bullhorn"></i> Com: 0</span>
       <span id="m-org-preview" style="color:var(--accent)"><i class="fas fa-sitemap"></i> Org: 0</span>
    </div>

    <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:25px;">
      <button class="btn-action btn-outline" style="padding:10px 15px;" onclick="cerrarModalMetas()">Cancelar</button>
      <button class="btn-action" style="padding:10px 20px;" onclick="guardarMetas()">Guardar</button>
    </div>
  </div>
</div>

<script>
// 1. VARIABLES GLOBALES DE CONEXIÓN
const estadoUsuario = "<?php echo addslashes($estado_actual); ?>";
const hoy = new Date();
const mesesArr = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

// 2. Estructuras dinámicas obtenidas desde PHP
const entidadesMap = {
  region: <?php echo $regiones_json; ?>,
  zona: <?php echo $zonas_json; ?>
};

let activePrefix = 'est'; 
let selectedRegionValue = 'todas'; // Región elegida en la vista "Regiones"; 'todas' = sin filtro
let charts = {};
let currentMeta = 100; // Meta mensual global actual
Chart.defaults.font.family = "'Arial', sans-serif";

// ══ LÓGICA DE TIEMPO ══
window.addEventListener('DOMContentLoaded', () => { 
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
const ALERTAS_COLLAPSE_KEY = 'gb_alertas_collapsed_ce';
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
  if (!cont) return;
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
    badge.style.display = 'inline-block';
    card.classList.remove('ok');
  } else {
    badge.style.display = 'none';
    card.classList.add('ok');
    cont.innerHTML = '<div class="empty-state"><i class="fas fa-check-circle" style="color:var(--success);font-size:20px;display:block;margin-bottom:8px;"></i>Todas las regiones y zonas tienen actividad reciente.</div>';
    return;
  }

  // Solo hay una región del estado, así que agrupamos regiones planas y zonas por región
  let html = '';
  if (regiones.length) {
    html += `<div class="alerta-estado-block"><div class="alerta-estado-title">Regiones</div>`;
    regiones.forEach(r => {
      html += `<div class="alerta-row"><i class="fas fa-map-marked-alt"></i><div class="alerta-info"><strong>Región ${r.nombre}</strong><span>${fmtInactividad(r.horas_inactividad)}</span></div></div>`;
    });
    html += `</div>`;
  }
  if (zonas.length) {
    const porRegion = {};
    zonas.forEach(z => { (porRegion[z.region_nombre] = porRegion[z.region_nombre] || []).push(z); });
    Object.keys(porRegion).sort().forEach(regionNombre => {
      html += `<div class="alerta-estado-block"><div class="alerta-estado-title">Zonas · ${regionNombre}</div>`;
      porRegion[regionNombre].forEach(z => {
        html += `<div class="alerta-row"><i class="fas fa-map-pin"></i><div class="alerta-info"><strong>Zona ${z.nombre}</strong><span>${fmtInactividad(z.horas_inactividad)}</span></div></div>`;
      });
      html += `</div>`;
    });
  }
  cont.innerHTML = html;
}

function getWeekNumber(d) {
  d = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()));
  d.setUTCDate(d.getUTCDate() + 4 - (d.getUTCDay()||7));
  var yearStart = new Date(Date.UTC(d.getUTCFullYear(),0,1));
  return Math.ceil((((d - yearStart) / 86400000) + 1)/7);
}

// NUEVA FUNCIÓN: Obtiene el texto del rango de fechas de una semana específica
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
    fetchDashboardData();
    return;
  }
  if (weekGroup) weekGroup.style.display = 'flex';

  const month   = parseInt(monthRaw) - 1;
  const yearSel = parseInt(document.getElementById('year-select').value) || hoy.getFullYear();
  ws.innerHTML = '<option value="todas">Todo el Mes</option>';
  
  let firstDay = new Date(yearSel, month, 1);
  let w1 = getWeekNumber(firstDay);
  
  for(let i=0; i<5; i++) {
    let numSemana = w1 + i;
    let rangoFechas = getDateRangeOfWeek(numSemana, yearSel);
    // Agregamos las fechas al texto del select
    ws.innerHTML += `<option value="${numSemana}">Semana ${numSemana} (${rangoFechas})</option>`;
  }
  
  if(yearSel === hoy.getFullYear() && month === hoy.getMonth()) {
    let currentW = getWeekNumber(hoy);
    if(ws.querySelector(`option[value="${currentW}"]`)) ws.value = currentW;
  }
  fetchDashboardData();
}

// ══ LÓGICA DE FILTROS UNIFICADOS Y UI ══
function switchLevel(level, btn) {
  if (level === 'region' && entidadesMap.region.length === 0) {
      alert("Debes crear regiones en la sección de Estructura.");
      return;
  }
  if (level === 'zona' && entidadesMap.zona.length === 0) {
      alert("Debes crear zonas en la sección de Estructura.");
      return;
  }

  document.querySelectorAll('.level-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  
  document.querySelectorAll('.view-panel').forEach(p => p.classList.remove('active'));
  document.getElementById('panel-' + level).classList.add('active');

  const entityGroup = document.getElementById('entity-filter-group');
  const entitySelect = document.getElementById('entity-select');
  const entityLabel = document.getElementById('entity-label');

  if (level === 'estatal') {
    entityGroup.style.display = 'none';
    activePrefix = 'est';
  } else {
    entityGroup.style.display = 'flex';
    entityLabel.textContent = level === 'region' ? 'Región:' : 'Zona:';

    let opciones = entidadesMap[level];

    if (level === 'zona') {
      // Si hay una región seleccionada (distinta de "todas"), solo mostramos sus zonas.
      // Si no hay región seleccionada, se muestran todas.
      const conFiltro = selectedRegionValue && selectedRegionValue !== 'todas';
      opciones = entidadesMap.zona.filter(z => z.value === 'todas' || !conFiltro || z.region === selectedRegionValue);

      // Orden alfabético (la opción "todas" siempre va primero)
      opciones = [...opciones].sort((a, b) => {
        if (a.value === 'todas') return -1;
        if (b.value === 'todas') return 1;
        return a.label.localeCompare(b.label, 'es', { sensitivity: 'base' });
      });
    }

    entitySelect.innerHTML = opciones.map(opt => `<option value="${opt.value}">${opt.label}</option>`).join('');
    activePrefix = level === 'region' ? 'reg' : 'zon';
  }
  
  if(level !== 'estatal') handleEntityChange(entitySelect, level);
  else fetchDashboardData(); 
}

function handleEntityChange(selectElement, forcedLevel = null) {
  const selectedEntity = selectElement.value;
  const selectedText = selectElement.options[selectElement.selectedIndex].text;
  const currentLevel = forcedLevel || document.querySelector('.level-btn.active').textContent.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "").split(' ')[0];

  if (currentLevel === 'region' || currentLevel === 'regiones') {
    selectedRegionValue = selectedEntity; // Recordamos la región elegida para filtrar la vista de Zonas
    document.getElementById('region-title').textContent = selectedText;
    document.getElementById('reg-desc-name').textContent = selectedText;
    document.getElementById('region-badge').textContent = (selectedEntity === 'todas' ? 'ESTATAL' : selectedText.toUpperCase()) + ' · VISTA REGIÓN';
  } else if (currentLevel === 'zona' || currentLevel === 'zonas') {
    document.getElementById('zona-title').textContent = selectedText;
    document.getElementById('zon-desc-name').textContent = selectedText;
    document.getElementById('zona-badge').textContent = selectedText.toUpperCase() + ' · VISTA ZONA';
  }
  fetchDashboardData();
}

// ══ LÓGICA DE METAS EDITABLES ══
function calcularDistribucion() {
    let total = parseInt(document.getElementById('m-total').value) || 0;
    document.getElementById('m-voto-preview').innerHTML = `<i class="fas fa-map"></i> Voto: ${Math.round(total * 0.45)}`;
    document.getElementById('m-com-preview').innerHTML = `<i class="fas fa-bullhorn"></i> Com: ${Math.round(total * 0.35)}`;
    document.getElementById('m-org-preview').innerHTML = `<i class="fas fa-sitemap"></i> Org: ${Math.round(total * 0.20)}`;
}

// ══ GENERACIÓN DE REPORTES EN PDF ══
function generarPDF() {
    const panelActivo = document.querySelector('.view-panel.active');
    const prefix = panelActivo.id.split('-')[1].substring(0, 3);
    
    const tituloBase = panelActivo.querySelector('.main-title').innerText.trim();
    const periodo = panelActivo.querySelector('.time-text').innerText.trim();
    const nombreVista = document.querySelector('.level-btn.active').innerText.trim();
    const nombreArchivo = `Reporte_${nombreVista}_${tituloBase}.pdf`.replace(/\s+/g, '_');
    
    const kpiEv = document.getElementById(`${prefix}-kpi-ev`).innerText;
    const kpiAct = document.getElementById(`${prefix}-kpi-act`).innerText;
    const kpiCon = document.getElementById(`${prefix}-kpi-con`).innerText;
    const kpiCum = document.getElementById(`${prefix}-kpi-cum`).innerText;
    const kpiZon = document.getElementById(`${prefix}-kpi-zon`).innerText;
    const kpiCos = document.getElementById(`${prefix}-kpi-cos`).innerText;

    const compEv1 = document.getElementById(`${prefix}-comp-ev1`).innerText;
    const pctEv1 = document.getElementById(`${prefix}-pct-ev1`).innerText;
    const compEv2 = document.getElementById(`${prefix}-comp-ev2`).innerText;
    const pctEv2 = document.getElementById(`${prefix}-pct-ev2`).innerText;
    const compEv3 = document.getElementById(`${prefix}-comp-ev3`).innerText;
    const pctEv3 = document.getElementById(`${prefix}-pct-ev3`).innerText;

    const tableRows = Array.from(document.getElementById(`${prefix}-table-body`).querySelectorAll('tr'));
    
    let tableHTML = `
        <table style="width: 100%; border-collapse: collapse; font-size: 12px; margin-top: 10px;">
            <thead>
                <tr style="background-color: #e0e0e0; color: #333; text-align: left;">
                    <th style="border: 1px solid #ccc; padding: 8px;">#</th>
                    <th style="border: 1px solid #ccc; padding: 8px;">Nombre</th>
                    <th style="border: 1px solid #ccc; padding: 8px;">Eventos</th>
                    <th style="border: 1px solid #ccc; padding: 8px;">Cumplimiento</th>
                    <th style="border: 1px solid #ccc; padding: 8px;">Activaciones</th>
                    <th style="border: 1px solid #ccc; padding: 8px;">Contactos</th>
                    <th style="border: 1px solid #ccc; padding: 8px;">Sub-Entidades</th>
                </tr>
            </thead>
            <tbody>
    `;

    tableRows.forEach(row => {
        const cols = row.querySelectorAll('td');
        if (cols.length >= 7) {
            let textCump = cols[3].innerText.trim();
            tableHTML += `<tr>
                <td style="border: 1px solid #ccc; padding: 8px; text-align: center;">${cols[0].innerText}</td>
                <td style="border: 1px solid #ccc; padding: 8px;"><strong>${cols[1].innerText}</strong></td>
                <td style="border: 1px solid #ccc; padding: 8px; text-align: center;">${cols[2].innerText}</td>
                <td style="border: 1px solid #ccc; padding: 8px; text-align: center;">${textCump}</td>
                <td style="border: 1px solid #ccc; padding: 8px; text-align: center;">${cols[4].innerText}</td>
                <td style="border: 1px solid #ccc; padding: 8px; text-align: center;">${cols[5].innerText}</td>
                <td style="border: 1px solid #ccc; padding: 8px; text-align: center;">${cols[6].innerText}</td>
            </tr>`;
        }
    });
    tableHTML += `</tbody></table>`;

    const printDiv = document.createElement('div');
    printDiv.style.fontFamily = 'Arial, sans-serif';
    printDiv.style.color = '#333';
    printDiv.style.padding = '30px';
    printDiv.style.background = '#fff';
    printDiv.style.width = '100%';

    const dateStr = new Date().toLocaleDateString('es-MX', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' });

    printDiv.innerHTML = `
        <h1 style="text-align: center; color: #2d7a3e; margin-bottom: 5px; font-size: 24px; font-weight: bold; text-transform: uppercase;">Reporte Ejecutivo - ${nombreVista}</h1>
        <h2 style="text-align: center; margin-top: 0; margin-bottom: 25px; font-size: 20px; color: #1a1a1a;">${tituloBase}</h2>
        
        <div style="margin-bottom: 20px; font-size: 14px;">
            <p style="margin: 3px 0;"><strong>Periodo de consulta:</strong> ${periodo}</p>
            <p style="margin: 3px 0;"><strong>Fecha de emisión:</strong> ${dateStr}</p>
        </div>
        
        <hr style="border: 0; border-top: 2px solid #2d7a3e; margin-bottom: 20px;">
        
        <h3 style="background-color: #f0f7f0; color: #1f5a2d; padding: 8px 12px; margin-bottom: 10px; font-size: 16px; border-left: 4px solid #2d7a3e;">Resumen de Indicadores Clave</h3>
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px; font-size: 13px;">
            <tr>
                <td style="border: 1px solid #ccc; padding: 12px; width: 33.3%;"><strong>Eventos Totales:</strong><br><span style="font-size:18px; color:#2d7a3e; font-weight:bold;">${kpiEv}</span></td>
                <td style="border: 1px solid #ccc; padding: 12px; width: 33.3%;"><strong>Activaciones:</strong><br><span style="font-size:18px; color:#2d7a3e; font-weight:bold;">${kpiAct}</span></td>
                <td style="border: 1px solid #ccc; padding: 12px; width: 33.3%;"><strong>Contactos Nuevos:</strong><br><span style="font-size:18px; color:#3b82f6; font-weight:bold;">${kpiCon}</span></td>
            </tr>
            <tr>
                <td style="border: 1px solid #ccc; padding: 12px;"><strong>Cumplimiento de Agenda:</strong><br><span style="font-size:18px; color:#f59e0b; font-weight:bold;">${kpiCum}</span></td>
                <td style="border: 1px solid #ccc; padding: 12px;"><strong>Zonas / Elementos:</strong><br><span style="font-size:18px; font-weight:bold;">${kpiZon}</span></td>
                <td style="border: 1px solid #ccc; padding: 12px;"><strong>Costo Acumulado:</strong><br><span style="font-size:18px; color:#ef4444; font-weight:bold;">${kpiCos}</span></td>
            </tr>
        </table>

        <h3 style="background-color: #f0f7f0; color: #1f5a2d; padding: 8px 12px; margin-bottom: 10px; font-size: 16px; border-left: 4px solid #2d7a3e;">Distribución de Agenda (Regla 45-35-20)</h3>
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px; font-size: 13px;">
            <tr>
                <td style="border: 1px solid #ccc; padding: 12px;"><strong>Voto / Territorio (45%):</strong><br>${compEv1} eventos logrados de la meta <span style="font-weight:bold; color:#3b82f6">(${pctEv1})</span></td>
                <td style="border: 1px solid #ccc; padding: 12px;"><strong>Comunicación (35%):</strong><br>${compEv2} eventos logrados de la meta <span style="font-weight:bold; color:#8b5cf6">(${pctEv2})</span></td>
                <td style="border: 1px solid #ccc; padding: 12px;"><strong>Organización (20%):</strong><br>${compEv3} eventos logrados de la meta <span style="font-weight:bold; color:#f59e0b">(${pctEv3})</span></td>
            </tr>
        </table>

        <h3 style="background-color: #f0f7f0; color: #1f5a2d; padding: 8px 12px; margin-bottom: 10px; font-size: 16px; border-left: 4px solid #2d7a3e;">Desempeño Detallado (Ranking)</h3>
        ${tableHTML}
    `;

    const opt = {
        margin:       [15, 15, 15, 15],
        filename:     nombreArchivo,
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2, useCORS: true, logging: false },
        jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };

    html2pdf().set(opt).from(printDiv).save();
}

// ══ METAS REALES (módulo Metas) ══
function parametrosMeta(level, entityId) {
  if (level === 'estatal') return { nivel: 'region', scope: 'propio', entidad: '' };
  if (level === 'region')  return { nivel: 'region', scope: (entityId && entityId !== 'todas') ? 'una' : 'propio', entidad: entityId || '' };
  return { nivel: 'zona', scope: (entityId && entityId !== 'todas') ? 'una' : 'propio', entidad: entityId || '' };
}

async function obtenerMetasReales(level, entityId, anio, mes, semana) {
  const p = parametrosMeta(level, entityId);
  try {
    const url = `../../api/metas/procesar.php?action=resumen&nivel=${p.nivel}&scope=${p.scope}&entidad=${encodeURIComponent(p.entidad)}&anio=${anio}&mes=${mes}&semana=${semana}`;
    const res = await fetch(url);
    const data = await res.json();
    if (data.ok) return { voto: data.voto||0, comunicacion: data.comunicacion||0, organizacion: data.organizacion||0 };
  } catch (e) { console.error('Error cargando metas reales:', e); }
  return { voto: 0, comunicacion: 0, organizacion: 0 };
}

// ══ NÚCLEO FETCH AL BACKEND REAL ══
async function fetchDashboardData() {
  const mSelect = document.getElementById('month-select');
  const wSelect = document.getElementById('week-select');
  const ySelect = document.getElementById('year-select');
  const monthVal = mSelect.value;
  const targetYear = ySelect.value;
  const monthName = mSelect.options[mSelect.selectedIndex].text;
  const weekVal = wSelect.value;
  const isYearView = (monthVal === 'todas');
  const isMonthView = (weekVal === 'todas');
  
  // Obtenemos el texto completo de la semana seleccionada (incluye las fechas)
  const weekText = wSelect.options[wSelect.selectedIndex].text; 
  
  // Actualizamos el título usando el nuevo weekText
  let textTitle;
  if (isYearView) textTitle = `Año ${targetYear}`;
  else if (isMonthView) textTitle = `${monthName} ${targetYear}`;
  else textTitle = `${weekText} · ${targetYear}`;
  document.querySelectorAll('.time-text').forEach(el => el.textContent = textTitle);
  document.querySelectorAll('.time-sub').forEach(el => el.textContent = isYearView ? 'Acumulado anual' : (isMonthView ? 'Acumulado mensual' : 'Acumulado semanal'));
  document.querySelectorAll('.time-title-1').forEach(el => el.textContent = isYearView ? 'Cumplimiento vs Objetivo Anual' : (isMonthView ? 'Cumplimiento vs Objetivo Mensual' : 'Cumplimiento vs Objetivo Semanal'));

  let level = 'estatal';
  if(activePrefix === 'reg') level = 'region';
  if(activePrefix === 'zon') level = 'zona';
  
  let entityId = '';
  if(level !== 'estatal') {
      entityId = document.getElementById('entity-select').value;
  }

  const url = `../../api/dashboard/api_dashboard.php?context=estatal&estado=${encodeURIComponent(estadoUsuario)}&level=${level}&id=${encodeURIComponent(entityId)}&mes=${mSelect.value}&semana=${weekVal}&anio=${targetYear}`;

  // DEBUG 1: Imprimir URL de la petición para revisar filtros
  console.log("🟢 SOLICITANDO DATOS A API:", url);

  try {
    const [res, metasReales] = await Promise.all([
      fetch(url),
      obtenerMetasReales(level, entityId, targetYear, monthVal, weekVal)
    ]);
    const data = await res.json();
    
    // DEBUG 2: Imprimir respuesta completa de la API
    console.log("🟢 RESPUESTA DE LA API:", data);
    
    if(data.error) throw new Error(data.error);
    
    renderizarDatosEnVista(activePrefix, data, isMonthView, metasReales);

  } catch (error) {
    console.error("🔴 Error conectando con API:", error);
  }
}

// ══ RENDERIZADO REACTIVO ══
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

function renderizarDatosEnVista(prefix, data, isMonthView, metasReales) {
    metasReales = metasReales || { voto: 0, comunicacion: 0, organizacion: 0 };
    if(!data || !data.kpis) return;

    // 1. KPIs Superiores
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

    // 2. Barras de Cumplimiento vs Meta real (módulo Metas, acumulada según Año/Mes/Semana/Vista)
    const metaVoto = metasReales.voto;
    const metaCom  = metasReales.comunicacion;
    const metaOrg  = metasReales.organizacion;
    currentMeta = metaVoto + metaCom + metaOrg;

    let rv=0, rc=0, ro=0;
    if(data.charts && data.charts.ejes) {
       Object.keys(data.charts.ejes).forEach(key => {
          let strKey = String(key).toLowerCase();
          if (strKey.includes('voto') || strKey.includes('territorio')) rv += data.charts.ejes[key];
          else if (strKey.includes('comunic')) rc += data.charts.ejes[key];
          else if (strKey.includes('org')) ro += data.charts.ejes[key];
       });
    }

    // CORRECCIÓN VISUAL: Mostramos la meta en el HTML explícitamente y quitamos static target lines
    const calcBar = (real, meta, pctId, barId, evId) => {
        const elEv = document.getElementById(evId);
        if(elEv) {
            // Imprime explícitamente el número real y el objetivo
            elEv.innerHTML = `<span class="dyna-val" style="font-weight:900;">${real}</span> <span style="font-size:11px; color:var(--primary); font-weight:bold;">/ ${meta}</span>`;
        }
        
        let p = meta > 0 ? Math.min((real/meta)*100, 100) : 0;
        let elPct = document.getElementById(pctId);
        if(elPct) elPct.textContent = Math.round(p) + '% ' + (p>=100?'✓':'');
        
        let elBar = document.getElementById(barId);
        if(elBar) {
            elBar.style.width = '0%';
            // Opcional: Feedback visual extra si cumplen el objetivo
            if (p >= 100) {
                elBar.style.backgroundColor = 'var(--success)';
            } else {
                elBar.style.backgroundColor = ''; // Restablecer al color por CSS
            }
            setTimeout(() => elBar.style.width = p + '%', 150);
        }
    };

    calcBar(rv, metaVoto, `${prefix}-pct-ev1`, `${prefix}-bar-ev1`, `${prefix}-comp-ev1`);
    calcBar(rc, metaCom,  `${prefix}-pct-ev2`, `${prefix}-bar-ev2`, `${prefix}-comp-ev2`);
    calcBar(ro, metaOrg,  `${prefix}-pct-ev3`, `${prefix}-bar-ev3`, `${prefix}-comp-ev3`);

    // 3. Estatus Cards
    if(data.charts && data.charts.status) {
        animateValue(`${prefix}-st-prog`, data.charts.status['programada'] || 0);
        animateValue(`${prefix}-st-curs`, data.charts.status['en-curso'] || 0);
        animateValue(`${prefix}-st-comp`, data.charts.status['completada'] || 0);
        animateValue(`${prefix}-st-canc`, data.charts.status['cancelada'] || 0);
    }
    
    // 4. Resultados de Impacto
    const asis = data.kpis.asistentes || 0;
    const cont = data.kpis.contactos || 0;
    const inv = data.kpis.inversion || 0;
    let cpc = cont > 0 ? Math.round(inv / cont) : 0;

    animateValue(`${prefix}-res-cont`, cont);
    animateValue(`${prefix}-res-asis`, asis);
    animateValue(`${prefix}-res-cpc`, cpc, true);

    // 5. Tabla Detallada
    renderTableReal(prefix, data.table || []);

    // 6. Gráficos
    initChartsReal(prefix, data);
}

// ══ HELPERS TABLAS Y GRÁFICOS REALES ══
function pillStatus(pct) {
  if (pct >= 80) return `<span class="pill green"></span>`;
  if (pct >= 70) return `<span class="pill yellow"></span>`;
  return `<span class="pill red"></span>`;
}
function barColor(pct) {
  return pct >= 80 ? '#22c55e' : pct >= 70 ? '#f59e0b' : '#ef4444';
}

function renderTableReal(prefix, tableData) {
  const body = document.getElementById(`${prefix}-table-body`);
  if(!body) return;
  
  const sorted = [...tableData].sort((a, b) => (b.contactos||0) - (a.contactos||0));
  
  body.innerHTML = sorted.map((row, i) => {
    let cump = row.cumplimiento || row.cump || 0;
    return `
    <tr>
      <td><span class="rank-badge ${i < 3 ? 'top' : ''}">${i + 1}</span></td>
      <td><strong>${row.nombre || 'N/A'}</strong></td>
      <td>${row.eventos || row.acts || 0}</td>
      <td><div class="mini-bar"><div class="mini-bar-track"><div class="mini-bar-fill" style="width:${cump}%;background:${barColor(cump)}"></div></div><span style="font-size:12px;font-weight:800;min-width:32px">${cump}%</span></div></td>
      <td>${row.activaciones || 0}</td>
      <td><strong style="color:#2d7a3e">${(row.contactos || row.cont || 0).toLocaleString()}</strong></td>
      <td>${row.sub_entidades || row.seccionales || '-'}</td>
      <td style="display:none;">${pillStatus(cump)}</td> </tr>
  `}).join('');
}

function initChartsReal(prefix, data) {
  if (charts[`${prefix}t`]) charts[`${prefix}t`].destroy();
  if (charts[`${prefix}a`]) charts[`${prefix}a`].destroy();
  if (charts[`${prefix}b`]) charts[`${prefix}b`].destroy();

  const trendEl = document.getElementById(`${prefix}-trend-chart`);
  if (trendEl) {
      let rawTrend = (data.charts && data.charts.tendencia) ? data.charts.tendencia : [];
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

  const actEl = document.getElementById(`${prefix}-act-chart`);
  if (actEl) {
      let tipos = (data.charts && data.charts.tipos) ? data.charts.tipos : {};
      let tt = tipos['territorial'] || 0;
      let td = tipos['digital'] || 0;
      let ts = tipos['simbolica'] || 0;
      let te = tipos['estructura'] || 0;

      charts[`${prefix}a`] = new Chart(actEl, {
          type: 'doughnut',
          data: { labels: ['Territorial','Digital','Impacto','Estructura'], datasets: [{ data: [tt,td,ts,te], backgroundColor: ['#2d7a3e','#4caf50','#81c784','#a5d6a7'], borderWidth: 0 }] },
          options: { responsive: true, maintainAspectRatio: false, cutout: '68%', plugins: { legend: { position: 'right', labels: { boxWidth: 10, font: { size: 11 } } } } }
      });
  }

  const barConEl = document.getElementById(`${prefix}-contacts-bar-chart`);
  if (barConEl) {
      let top = (data.table || []).slice(0, 10);
      let bLabels = top.map(r => String(r.nombre).length>10 ? String(r.nombre).substring(0,10)+'..' : String(r.nombre));
      let bdCont = top.map(r => r.contactos || r.cont || 0);
      let bdAsis = top.map(r => r.asistentes || r.asist || 0);

      charts[`${prefix}b`] = new Chart(barConEl, {
          type: 'bar',
          data: { labels: bLabels, datasets: [
              { label: 'Nuevos', data: bdCont, backgroundColor: '#2d7a3e' },
              { label: 'Asistentes', data: bdAsis, backgroundColor: '#81c784' },
          ]},
          options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false } } } }
      });
  }
}
</script>
</body>
</html>