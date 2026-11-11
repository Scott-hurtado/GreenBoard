<?php
if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
} else { 
    if (session_status() === PHP_SESSION_NONE) session_start(); 
}

$rol = $_SESSION['user_role'] ?? '';
$usuario_id = $_SESSION['user_id'] ?? 0;

// Permitimos el acceso al coordinador de zona y roles superiores
$permitidos = ['coordinador_zona', 'coordinador_regional', 'coordinador_estatal', 'superadmin', 'super admin', 'admin'];
if (!in_array($rol, $permitidos)) { 
    header('Location: /public/index.php'); 
    exit(); 
}

// --------------------------------------------------------
// 1. OBTENER JERARQUÍA GEOGRÁFICA (ESTADO -> REGIÓN -> ZONA)
// --------------------------------------------------------
$db = Database::getInstance()->getConnection();

$info_ubicacion = [
    'estado' => $_SESSION['user_estado'] ?? 'Estado no definido',
    'region' => $_SESSION['user_region_nombre'] ?? 'Región no asignada',
    'zona'   => $_SESSION['user_zona_nombre'] ?? 'Zona Asignada'
];

$id_zona_real = 0;

// Consultamos la DB para asegurar los nombres y obtener el ID numérico de la zona
if ($usuario_id > 0) {
    try {
        // CORRECCIÓN: buscar en coordinador_zona (no en usuarios)
        $sql_geo = "SELECT 
                        cz.estado AS nombre_estado,
                        r.nombre  AS nombre_region,
                        z.nombre  AS nombre_zona,
                        cz.zona   AS id_zona
                    FROM coordinador_zona cz
                    INNER JOIN zonas_estatales z      ON cz.zona = z.id
                    INNER JOIN regiones_estatales r   ON z.estructura_id = r.id
                    WHERE cz.id = ? LIMIT 1";
                    
        $stmt_geo = $db->prepare($sql_geo);
        $stmt_geo->execute([$usuario_id]);
        $res_geo = $stmt_geo->fetch(PDO::FETCH_ASSOC);

        if ($res_geo) {
            $info_ubicacion['estado'] = $res_geo['nombre_estado'];
            $info_ubicacion['region'] = $res_geo['nombre_region'];
            $info_ubicacion['zona']   = $res_geo['nombre_zona'];
            $id_zona_real             = (int)$res_geo['id_zona'];
            
            // Actualizar sesión
            $_SESSION['user_estado'] = $info_ubicacion['estado'];
            $_SESSION['user_region_nombre'] = $info_ubicacion['region'];
            $_SESSION['user_zona_nombre'] = $info_ubicacion['zona'];
        }
    } catch (Exception $e) {
        // Fallo silencioso
    }
}

$user_zona = $info_ubicacion['zona'];

// --------------------------------------------------------
// 2. OBTENER ENTIDADES DINÁMICAS (SECCIONES DE LA ZONA)
// --------------------------------------------------------
$entidades_zona = [];
try {
    // CORRECCIÓN: Leemos el JSON de secciones directamente de zonas_estatales
    // usando el id_zona_real obtenido de coordinador_zona
    if ($id_zona_real > 0) {
        $sql_sec = "SELECT secciones FROM zonas_estatales WHERE id = ? LIMIT 1";
        $stmt_sec = $db->prepare($sql_sec);
        $stmt_sec->execute([$id_zona_real]);
        $row_sec = $stmt_sec->fetch(PDO::FETCH_ASSOC);

        if ($row_sec && !empty($row_sec['secciones'])) {
            $secciones_json = json_decode($row_sec['secciones'], true);
            if (is_array($secciones_json)) {
                // Ordenar numéricamente
                usort($secciones_json, fn($a, $b) => (int)$a - (int)$b);
                $entidades_zona = $secciones_json;
            }
        }
    }
} catch (Exception $e) {
    // Fallo silencioso
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard - <?= htmlspecialchars($user_zona) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
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
  .page-header { padding: 20px 0 30px 0; position: relative; z-index: 2; display: flex; justify-content: space-between; align-items: flex-start; }
  .ai-badge { color: var(--primary); font-size: 12px; font-weight: 800; letter-spacing: 4px; margin-bottom: 14px; display: inline-block; text-transform: uppercase; background: rgba(45,122,62,0.1); padding: 5px 15px; border-radius: 20px; }
  .main-title { font-size: 48px; font-weight: 900; line-height: 1; margin-bottom: 12px; color: var(--dark); }
  .description { color: #666; font-size: 15px; line-height: 1.6; }
  .header-actions { display: flex; gap: 12px; align-items: center; margin-top: 25px; }
  .btn-action { background: var(--dark); color: white; padding: 13px 24px; border-radius: 12px; border: none; font-weight: 700; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; gap: 8px; font-size: 14px; text-decoration: none; }
  .btn-action:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.2); }
  .btn-outline { background: white; color: var(--dark); border: 2px solid #eee; }
  .btn-outline:hover { border-color: var(--dark); }

  /* PILLS UBICACION */
  .location-pills { display: flex; gap: 10px; margin-top: 14px; flex-wrap: wrap;}
  .loc-pill { font-size: 11px; font-weight: 800; padding: 6px 12px; border-radius: 8px; background: #f4fcf6; border: 1px solid #e8f5e9; color: #555; text-transform: uppercase; display: flex; align-items: center; gap: 6px; }
  .loc-pill i { color: var(--primary); font-size: 13px; }

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
  @media (max-width: 768px) { .kpi-grid { grid-template-columns: 1fr 1fr; } .charts-13, .charts-equal { grid-template-columns: 1fr; } .page-header{flex-direction:column;} .header-actions{margin-top:15px;} }
</style>
</head>
<body>
<?php 
// Llamar al sidebar correspondiente a las zonas
if (file_exists(__DIR__ . '/../../includes/components/sidebar_cz.php'))
    include __DIR__ . '/../../includes/components/sidebar_cz.php'; 
?>

<div class="design-text">CAMPAIGN 360</div>

<div class="container">
  <div class="context-bar">
    <div class="filters-left">
      <div class="filter-group">
        <span class="filter-label">Vista:</span>
        <div class="level-switcher">
          <button class="level-btn active" onclick="switchLevel('resumen', this)">Resumen de Zona</button>
          <button class="level-btn" onclick="switchLevel('detalle', this)">Detalle de Sección</button>
        </div>
      </div>
      <div class="filter-group" id="entity-filter-group" style="display: none; border-left: 2px solid var(--border); padding-left: 20px;">
        <span class="filter-label" id="entity-label">Sección:</span>
        <select id="entity-select" class="ui-select" onchange="updateDashboard()">
          <?php if(empty($entidades_zona)): ?>
            <option value="">Sin secciones asignadas</option>
          <?php else: ?>
            <?php foreach($entidades_zona as $entidad): ?>
              <option value="<?= htmlspecialchars($entidad) ?>">Sección <?= htmlspecialchars($entidad) ?></option>
            <?php endforeach; ?>
          <?php endif; ?>
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
        <span class="ai-badge" id="dyn-badge">ZONA · <?= count($entidades_zona) ?> SECCIONES</span>
        <h1 class="main-title" id="dyn-title"><?= htmlspecialchars($user_zona) ?></h1>
        <p class="description"><span id="dyn-desc">Resumen operativo de la zona</span> · <span id="dyn-time" style="font-weight:700;"></span></p>
        
        <div class="location-pills">
            <span class="loc-pill"><i class="fas fa-flag"></i> Estado: <?= htmlspecialchars($info_ubicacion['estado']) ?></span>
            <span class="loc-pill"><i class="fas fa-map-marked-alt"></i> Región: <?= htmlspecialchars($info_ubicacion['region']) ?></span>
        </div>
      </div>
      
      <div class="header-actions">
        <button class="btn-action btn-outline" onclick="updateDashboard()"><i class="fas fa-sync-alt"></i> Actualizar</button>
        <button class="btn-action" onclick="generarPDF()"><i class="fas fa-file-alt"></i> Reportes</button>
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
        <div class="kpi-label" id="lbl-activos">Secciones Activas</div>
      </div>
      <div class="kpi-card">
        <div class="kpi-header"><div class="kpi-icon danger"><i class="fas fa-dollar-sign"></i></div></div>
        <div class="kpi-value" id="kpi-costo">$0</div>
        <div class="kpi-label">Costo Acumulado</div>
      </div>
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
          <div><div class="chart-card-title">Resultados de Impacto</div><div class="chart-card-sub">Top secciones (Contactos vs Asistentes)</div></div>
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
        <h3 class="section-title"><span class="section-title-bar"></span><span id="tabla-title">Desempeño por Sección</span></h3>
      </div>
      <div class="chart-card" style="padding:0;overflow:hidden; margin-bottom:40px;">
        <table class="data-table">
          <thead>
            <tr>
              <th>#</th><th id="th-entidad">Sección Electoral</th><th>Activaciones</th><th>Contactos Nuevos</th><th>Asistentes</th><th>Costo</th>
            </tr>
          </thead>
          <tbody id="table-body"></tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<div class="modal-overlay" id="modal-metas" onclick="if(event.target===this)cerrarModalMetas()">
  <div class="modal-box">
    <h2 class="modal-title">Metas de Agenda</h2>
    <p style="font-size:13px; color:#666; margin-bottom:20px;">Define la cantidad de eventos esperados para el periodo seleccionado.</p>
    <div class="form-group"><label>Eventos de Voto / Territorio</label><input type="number" id="m-voto" min="0"></div>
    <div class="form-group"><label>Eventos de Comunicación</label><input type="number" id="m-com" min="0"></div>
    <div class="form-group"><label>Eventos de Organización</label><input type="number" id="m-org" min="0"></div>
    <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:25px;">
      <button class="btn-action btn-outline" style="padding:10px 15px;" onclick="cerrarModalMetas()">Cancelar</button>
      <button class="btn-action" style="padding:10px 20px;" onclick="guardarMetas()">Guardar</button>
    </div>
  </div>
</div>

<script>
// ── VARIABLES GLOBALES ──
const num_entidades = <?= count($entidades_zona) ?>;
let viewMode = 'resumen'; // 'resumen' o 'detalle'
let charts = {};
let currentMeta = 100; 
const nombreZonaEstatica = "<?= htmlspecialchars($user_zona) ?>";

// ══ METAS REALES (módulo Metas, registradas por el Regional para esta zona) ══
let metasRealesActuales = { voto: 0, comunicacion: 0, organizacion: 0 };
async function obtenerMetasReales(anio, mes, semana) {
  try {
    const url = `../../api/metas/procesar.php?action=resumen&nivel=zona&scope=una&entidad=${encodeURIComponent(nombreZonaEstatica)}&anio=${anio}&mes=${mes}&semana=${semana}`;
    const res = await fetch(url);
    const data = await res.json();
    if (data.ok) return { voto: data.voto||0, comunicacion: data.comunicacion||0, organizacion: data.organizacion||0 };
  } catch (e) { console.error('Error cargando metas reales:', e); }
  return { voto: 0, comunicacion: 0, organizacion: 0 };
}

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
});

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
  start.setDate(d.getDate() - 3);
  let end = new Date(d);
  end.setDate(d.getDate() + 3);
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

  const month   = parseInt(monthRaw) - 1;
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

// ══ GENERACIÓN DE REPORTES EN PDF ══
function generarPDF() {
    const tituloBase = document.getElementById('dyn-title').innerText.trim();
    const periodo = document.getElementById('dyn-time').innerText.trim();
    const nombreVista = document.querySelector('.level-btn.active').innerText.trim();
    const nombreArchivo = `Reporte_${nombreVista}_${tituloBase}.pdf`.replace(/\s+/g, '_');

    const getTxt = id => { const el = document.getElementById(id); return el ? el.innerText.trim() : ''; };

    const kpiEv  = getTxt('kpi-agenda');
    const kpiAct = getTxt('kpi-acts');
    const kpiCon = getTxt('kpi-contactos');
    const kpiCum = getTxt('kpi-cump');
    const kpiAct2 = getTxt('kpi-activos');
    const lblAct2 = getTxt('lbl-activos') || 'Secciones Activas';
    const kpiCos = getTxt('kpi-costo');

    const cmpVotoVal = getTxt('cmp-voto-val'), cmpVotoMeta = getTxt('cmp-voto-meta'), cmpVotoPct = getTxt('cmp-voto-pct');
    const cmpComVal  = getTxt('cmp-com-val'),  cmpComMeta  = getTxt('cmp-com-meta'),  cmpComPct  = getTxt('cmp-com-pct');
    const cmpOrgVal  = getTxt('cmp-org-val'),  cmpOrgMeta  = getTxt('cmp-org-meta'),  cmpOrgPct  = getTxt('cmp-org-pct');

    const thEntidad = getTxt('th-entidad') || 'Entidad';
    const tableRows = Array.from(document.getElementById('table-body').querySelectorAll('tr'));

    let tableHTML = `
        <table style="width: 100%; border-collapse: collapse; font-size: 12px; margin-top: 10px;">
            <thead>
                <tr style="background-color: #e0e0e0; color: #333; text-align: left;">
                    <th style="border: 1px solid #ccc; padding: 8px;">#</th>
                    <th style="border: 1px solid #ccc; padding: 8px;">${thEntidad}</th>
                    <th style="border: 1px solid #ccc; padding: 8px;">Activaciones</th>
                    <th style="border: 1px solid #ccc; padding: 8px;">Contactos Nuevos</th>
                    <th style="border: 1px solid #ccc; padding: 8px;">Asistentes</th>
                    <th style="border: 1px solid #ccc; padding: 8px;">Costo</th>
                </tr>
            </thead>
            <tbody>
    `;
    tableRows.forEach(row => {
        const cols = row.querySelectorAll('td');
        if (cols.length >= 6) {
            tableHTML += `<tr>
                <td style="border: 1px solid #ccc; padding: 8px; text-align: center;">${cols[0].innerText}</td>
                <td style="border: 1px solid #ccc; padding: 8px;"><strong>${cols[1].innerText}</strong></td>
                <td style="border: 1px solid #ccc; padding: 8px; text-align: center;">${cols[2].innerText}</td>
                <td style="border: 1px solid #ccc; padding: 8px; text-align: center;">${cols[3].innerText}</td>
                <td style="border: 1px solid #ccc; padding: 8px; text-align: center;">${cols[4].innerText}</td>
                <td style="border: 1px solid #ccc; padding: 8px; text-align: center;">${cols[5].innerText}</td>
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
                <td style="border: 1px solid #ccc; padding: 12px; width: 33.3%;"><strong>Eventos Agendados:</strong><br><span style="font-size:18px; color:#2d7a3e; font-weight:bold;">${kpiEv}</span></td>
                <td style="border: 1px solid #ccc; padding: 12px; width: 33.3%;"><strong>Activaciones Totales:</strong><br><span style="font-size:18px; color:#2d7a3e; font-weight:bold;">${kpiAct}</span></td>
                <td style="border: 1px solid #ccc; padding: 12px; width: 33.3%;"><strong>Contactos Nuevos:</strong><br><span style="font-size:18px; color:#3b82f6; font-weight:bold;">${kpiCon}</span></td>
            </tr>
            <tr>
                <td style="border: 1px solid #ccc; padding: 12px;"><strong>Cumplimiento de Agenda:</strong><br><span style="font-size:18px; color:#f59e0b; font-weight:bold;">${kpiCum}</span></td>
                <td style="border: 1px solid #ccc; padding: 12px;"><strong>${lblAct2}:</strong><br><span style="font-size:18px; font-weight:bold;">${kpiAct2}</span></td>
                <td style="border: 1px solid #ccc; padding: 12px;"><strong>Costo Acumulado:</strong><br><span style="font-size:18px; color:#ef4444; font-weight:bold;">${kpiCos}</span></td>
            </tr>
        </table>

        <h3 style="background-color: #f0f7f0; color: #1f5a2d; padding: 8px 12px; margin-bottom: 10px; font-size: 16px; border-left: 4px solid #2d7a3e;">Cumplimiento vs Metas (Distribución por Eje)</h3>
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px; font-size: 13px;">
            <tr>
                <td style="border: 1px solid #ccc; padding: 12px;"><strong>Voto / Territorio:</strong><br>${cmpVotoVal} / ${cmpVotoMeta} <span style="font-weight:bold; color:#3b82f6">(${cmpVotoPct})</span></td>
                <td style="border: 1px solid #ccc; padding: 12px;"><strong>Comunicación:</strong><br>${cmpComVal} / ${cmpComMeta} <span style="font-weight:bold; color:#8b5cf6">(${cmpComPct})</span></td>
                <td style="border: 1px solid #ccc; padding: 12px;"><strong>Organización:</strong><br>${cmpOrgVal} / ${cmpOrgMeta} <span style="font-weight:bold; color:#f59e0b">(${cmpOrgPct})</span></td>
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

// ── CORE FETCH LOGIC ──
async function updateDashboard() {
  const mSelect = document.getElementById('month-select');
  const wSelect = document.getElementById('week-select');
  const ySelect = document.getElementById('year-select');
  const selEnt  = document.getElementById('entity-select').value;
  
  const targetMonth = mSelect.value;
  const targetWeek  = wSelect.value;
  const targetYear  = ySelect.value;
  
  // Para el resumen, pasamos GLOBAL_ZONA. Para detalle, la sección seleccionada.
  const idParam = viewMode === 'resumen' ? 'GLOBAL_ZONA' : selEnt;
  
  // Headers text
  const mName = mSelect.options[mSelect.selectedIndex].text;
  const weekText = wSelect.options[wSelect.selectedIndex].text;
  let tText;
  if (targetMonth === 'todas') tText = `Año ${targetYear}`;
  else if (targetWeek === 'todas') tText = `${mName} ${targetYear}`;
  else tText = `${weekText} · ${targetYear}`;
  document.getElementById('dyn-time').textContent = tText;
  
  if(viewMode === 'resumen') {
    document.getElementById('dyn-badge').textContent = `ZONA · ${num_entidades} SECCIONES`;
    document.getElementById('dyn-title').textContent = nombreZonaEstatica;
    document.getElementById('dyn-desc').textContent = 'Resumen operativo de la zona';
    document.getElementById('tabla-title').textContent = 'Desempeño General de Zona';
    document.getElementById('th-entidad').textContent = 'Sección Electoral';
  } else {
    document.getElementById('dyn-badge').textContent = `DETALLE DE SECCIÓN`;
    document.getElementById('dyn-title').textContent = `Sección ${selEnt}`;
    document.getElementById('dyn-desc').textContent = 'Análisis focalizado';
    document.getElementById('tabla-title').textContent = 'Desempeño Específico';
    document.getElementById('th-entidad').textContent = 'Estructura / Referencia';
  }

  try {
    const urlFetch = `../../api/dashboard/api_dashboard.php?context=zona&level=${viewMode}&id=${idParam}&mes=${targetMonth}&semana=${targetWeek}&anio=${targetYear}`;
    const [response, metasReales] = await Promise.all([
      fetch(urlFetch),
      obtenerMetasReales(targetYear, targetMonth, targetWeek)
    ]);
    const data = await response.json();
    metasRealesActuales = metasReales;
    renderUI(data, targetWeek);
  } catch (error) {
    console.error("Fetch error:", error);
    // Renderizamos con datos vacíos para evitar que se quede pasmado
    renderUI({}, targetWeek); 
  }
}

function renderUI(data, targetWeek) {
  // 1. SANITIZAR Y ASEGURAR DATOS (Esto arregla el error de pantalla blanca / truene)
  if(!data || data.error) data = {};
  const kpis = data.kpis || {};
  const chartsData = data.charts || {};
  const tableData = data.table || [];
  
  const ejes = chartsData.ejes || {};
  const status = chartsData.status || {};
  const tendencia = chartsData.tendencia || [];
  const tipos = chartsData.tipos || {};

  // KPIs Globales
  document.getElementById('kpi-agenda').textContent    = kpis.eventos || 0;
  document.getElementById('kpi-acts').textContent      = kpis.activaciones || 0;
  document.getElementById('kpi-contactos').textContent = parseInt(kpis.contactos || 0).toLocaleString();
  document.getElementById('kpi-activos').textContent   = tableData.length; 
  let inversionReal = kpis.inversion || 0;
  document.getElementById('kpi-costo').textContent     = '$' + (inversionReal > 1000 ? (inversionReal/1000).toFixed(1)+'K' : Math.round(inversionReal));

  // Metas y Barras de Cumplimiento (valores reales registrados por el Regional para esta zona/semana)
  let mv = metasRealesActuales.voto;
  let mc = metasRealesActuales.comunicacion;
  let mo = metasRealesActuales.organizacion;
  let mTotal = mv + mc + mo;
  document.getElementById('lbl-metas').textContent = `Meta: ${mv} Voto · ${mc} Com · ${mo} Org`;

  let rv = ejes['voto'] || 0;
  let rc = ejes['comunicacion'] || 0;
  let ro = ejes['organizacion'] || 0;
  
  let totReal = rv + rc + ro;
  let pctGlobal = mTotal > 0 ? Math.min(100, Math.round((totReal/mTotal)*100)) : 0;
  document.getElementById('kpi-cump').textContent = pctGlobal + '%';

  function fillBar(idv, idm, idp, idb, real, meta) {
    document.getElementById(idv).textContent = real;
    document.getElementById(idm).textContent = meta;
    let p = meta>0 ? Math.min(100, Math.round((real/meta)*100)) : 0;
    document.getElementById(idp).textContent = ` ${p}%`;
    document.getElementById(idp).style.color = p>=100 ? '#22c55e' : (p>=50 ? '#f59e0b' : '#ef4444');
    document.getElementById(idb).style.width = p + '%';
  }
  fillBar('cmp-voto-val', 'cmp-voto-meta', 'cmp-voto-pct', 'cmp-voto-bar', rv, mv);
  fillBar('cmp-com-val', 'cmp-com-meta', 'cmp-com-pct', 'cmp-com-bar', rc, mc);
  fillBar('cmp-org-val', 'cmp-org-meta', 'cmp-org-pct', 'cmp-org-bar', ro, mo);

  // Estatus de Activaciones
  document.getElementById('st-prog').textContent = status['programada'] || 0;
  document.getElementById('st-curs').textContent = status['en-curso'] || 0;
  document.getElementById('st-comp').textContent = status['completada'] || 0;
  document.getElementById('st-canc').textContent = status['cancelada'] || 0;

  // Impacto
  let numContactos = kpis.contactos || 0;
  let numAsistentes = kpis.asistentes || 0;
  document.getElementById('res-cont').textContent = parseInt(numContactos).toLocaleString();
  document.getElementById('res-asis').textContent = parseInt(numAsistentes).toLocaleString();
  let cpc = numContactos > 0 ? Math.round(inversionReal / numContactos) : 0;
  document.getElementById('res-cpc').textContent = '$' + cpc;

  // Renderizar Gráficas (pasando arreglos limpios)
  renderTrend(tendencia);
  renderType(tipos);
  renderContactsBar(tableData);

  // Renderizar Tabla
  const tbody = document.getElementById('table-body');
  if(tableData.length === 0) {
      tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding: 30px; color: #888;">No hay registros para este periodo</td></tr>';
  } else {
      tbody.innerHTML = tableData.map((r, i) => {
        let rInv = r.inv || 0;
        let costK = rInv > 1000 ? '$'+(rInv/1000).toFixed(1)+'K' : '$'+Math.round(rInv);
        let nombreEntidad = isNaN(r.nombre) ? r.nombre : 'Sección ' + r.nombre; // Añadir prefijo si es número
        return `<tr>
          <td><span class="rank-badge ${i<3?'top':''}">${i+1}</span></td>
          <td><strong>${nombreEntidad}</strong></td>
          <td>${r.acts || 0}</td>
          <td><strong style="color:#2d7a3e">${parseInt(r.cont || 0).toLocaleString()}</strong></td>
          <td>${parseInt(r.asist || 0).toLocaleString()}</td>
          <td>${costK}</td>
        </tr>`;
      }).join('');
  }
}

function renderTrend(rawTrend) {
  if(charts['trend']) charts['trend'].destroy();
  if(!rawTrend || rawTrend.length === 0) rawTrend = [];
  
  let sems = [...new Set(rawTrend.map(r => `Sem ${r.sem}`))];
  
  let dtV = sems.map(s => { let r = rawTrend.find(x => `Sem ${x.sem}` === s && x.eje === 'voto'); return r ? r.total : 0; });
  let dtC = sems.map(s => { let r = rawTrend.find(x => `Sem ${x.sem}` === s && x.eje === 'comunicacion'); return r ? r.total : 0; });
  let dtO = sems.map(s => { let r = rawTrend.find(x => `Sem ${x.sem}` === s && x.eje === 'organizacion'); return r ? r.total : 0; });

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
  if(!tableData || tableData.length === 0) tableData = [];
  
  let top = tableData.slice(0, 10);
  let bLabels = top.map(r => {
      let nm = isNaN(r.nombre) ? r.nombre : 'Sec. ' + r.nombre;
      return nm.length>12 ? nm.substring(0,12)+'..' : nm;
  });
  let bdCont = top.map(r => r.cont || 0);
  let bdAsis = top.map(r => r.asist || 0);

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