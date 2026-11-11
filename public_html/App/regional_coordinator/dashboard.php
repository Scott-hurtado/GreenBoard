<?php
if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
} else { session_start(); }

$rol         = $_SESSION['user_role']   ?? '';
$user_id     = $_SESSION['user_id']     ?? 0;
$user_name   = $_SESSION['user_name']   ?? '';
$user_estado = $_SESSION['user_estado'] ?? '';
$user_region = $_SESSION['user_region'] ?? '';
$user_id    = $_SESSION['user_id']     ?? 0;

$permitidos = ['coordinador_regional', 'regional'];
if (!in_array($rol, $permitidos)) { header('Location: /public/index.php'); exit(); }

$region_nombre = '—';
$stats = ['zonas'=>0,'usuarios'=>0,'activaciones'=>0,'secciones'=>0,'contactos'=>0,'meta_contactos'=>0];
$zonas = [];
$agenda_proxima = [];
$activaciones_recientes = [];
$municipios_region = [];
$zona_ids = [];

// ── METAS INICIALES DESDE DB ──
$init_meta_voto = 0;
$init_meta_com  = 0;
$init_meta_org  = 0;

try {
    $dbCfg = __DIR__ . '/../../includes/database.php';
    $cfg = file_exists($dbCfg) ? require $dbCfg : [
        'host'=>'localhost','database'=>'u881824383_pverde',
        'username'=>'u881824383_proyverde','password'=>'PProyectoverde8#',
        'charset'=>'utf8mb4',
        'options'=>[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
    ];
    $dsn = "mysql:host={$cfg['host']};dbname={$cfg['database']};charset={$cfg['charset']}";
    $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], $cfg['options'] ?? [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);

    // Región asignada al coordinador desde regiones_estatales
    $st = $pdo->prepare("SELECT id, nombre, municipios FROM regiones_estatales WHERE coordinador_id = ? AND estado = ? LIMIT 1");
    $st->execute([$user_id, $user_estado]);
    $region_row = $st->fetch(PDO::FETCH_ASSOC);
    if ($region_row) {
        $region_nombre     = $region_row['nombre'];
        $region_id_re      = (int)$region_row['id'];
        $municipios_region = json_decode($region_row['municipios'] ?? '[]', true) ?: [];
    } else {
        $region_id_re = 0;
    }

    // Zonas de la región (zonas_estatales)
    if ($region_id_re) {
        $st = $pdo->prepare("
            SELECT id, nombre, clave, meta,
                   JSON_LENGTH(secciones) AS num_secciones
            FROM zonas_estatales
            WHERE estructura_id = ?
            ORDER BY nombre
        ");
        $st->execute([$region_id_re]);
        $zonas = $st->fetchAll(PDO::FETCH_ASSOC);
        $stats['zonas'] = count($zonas);
        $zona_ids = array_column($zonas, 'id');
    }

    // Usuarios de la región desde coordinador_regional
    $st = $pdo->prepare("SELECT COUNT(*) FROM coordinador_regional WHERE estado = ? AND region = ? AND estatus = 'activo'");
    $st->execute([$user_estado, $region_id_re]);
    $stats['usuarios'] = (int)$st->fetchColumn();

    // Secciones: contar las del JSON de zonas_estatales
    if (!empty($zona_ids)) {
        $in = implode(',', array_fill(0, count($zona_ids), '?'));
        $st = $pdo->prepare("SELECT secciones FROM zonas_estatales WHERE id IN ($in)");
        $st->execute($zona_ids);
        $all_secs = [];
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $json) {
            $arr = json_decode($json ?? '[]', true) ?: [];
            $all_secs = array_merge($all_secs, $arr);
        }
        $stats['secciones']      = count(array_unique($all_secs));
        $stats['contactos']      = 0;
        $stats['meta_contactos'] = 0;
    }

    // Activaciones (filtradas por municipios de la región)
    if ($municipios_region) {
        $in2 = implode(',', array_fill(0, count($municipios_region), '?'));
        $p = array_merge([$user_estado], $municipios_region);
        $st = $pdo->prepare("SELECT COUNT(*) FROM activaciones WHERE activo=1 AND estado=? AND municipio IN ($in2)");
        $st->execute($p);
        $stats['activaciones'] = (int)$st->fetchColumn();

        $p2 = array_merge([$user_estado], $municipios_region);
        $st = $pdo->prepare("
            SELECT id, titulo, tipo, estatus, municipio, DATE_FORMAT(fecha_actividad,'%d/%m/%Y') as fecha
            FROM activaciones WHERE activo=1 AND estado=? AND municipio IN ($in2)
            ORDER BY fecha_actividad DESC LIMIT 5
        ");
        $st->execute($p2);
        $activaciones_recientes = $st->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $st = $pdo->prepare("SELECT COUNT(*) FROM activaciones WHERE activo=1 AND estado=?");
        $st->execute([$user_estado]);
        $stats['activaciones'] = (int)$st->fetchColumn();

        $st = $pdo->prepare("
            SELECT id, titulo, tipo, estatus, municipio, DATE_FORMAT(fecha_actividad,'%d/%m/%Y') as fecha
            FROM activaciones WHERE activo=1 AND estado=?
            ORDER BY fecha_actividad DESC LIMIT 5
        ");
        $st->execute([$user_estado]);
        $activaciones_recientes = $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // Agenda próxima del estado
    $st = $pdo->prepare("
        SELECT id, titulo, eje, fecha, inicio, municipio, estatus
        FROM agenda_eventos
        WHERE activo=1 AND estado=? AND fecha >= CURDATE()
        ORDER BY fecha ASC LIMIT 5
    ");
    $st->execute([$user_estado]);
    $agenda_proxima = $st->fetchAll(PDO::FETCH_ASSOC);

    // Metas desde DB
    $st = $pdo->prepare(
        "SELECT meta_voto, meta_com, meta_org FROM metas_estados WHERE estado = ? LIMIT 1"
    );
    $key_region = 'REGION_' . ($region_row['nombre'] ?? $user_region);
    $st->execute([$key_region]);
    $rowMeta = $st->fetch(PDO::FETCH_ASSOC);
    if ($rowMeta) {
        $init_meta_voto = (int)$rowMeta['meta_voto'];
        $init_meta_com  = (int)$rowMeta['meta_com'];
        $init_meta_org  = (int)$rowMeta['meta_org'];
    }

} catch (PDOException $e) { $error_db = $e->getMessage(); }

// ── GUARDAR METAS (AJAX POST) ──
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
        $pdo->prepare(
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

$pct = $stats['meta_contactos'] > 0 ? min(100, round($stats['contactos'] / $stats['meta_contactos'] * 100)) : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard - Región <?= htmlspecialchars($region_nombre) ?></title>
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
  .context-bar { position: sticky; top: 0; z-index: 50; background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; padding: 16px 0; margin-bottom: 20px; gap: 20px; flex-wrap: wrap; }
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
  .section-link { font-size: 13px; color: var(--primary); font-weight: 700; text-decoration: none; display: flex; align-items: center; gap: 5px; }
  .section-link:hover { text-decoration: underline; }
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
  .kpi-sub { font-size: 12px; color: var(--text-muted); margin-top: 8px; }

  /* ── CHARTS ── */
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
  .bar-fill.contactos { background: var(--primary); }

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

  /* ── ZONAS LIST ── */
  .zonas-list { display: flex; flex-direction: column; gap: 12px; }
  .zona-item { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; background: var(--light); border-radius: 12px; border: 1px solid var(--border); }
  .zona-name { font-weight: 700; font-size: 14px; }
  .zona-meta { font-size: 12px; color: var(--text-muted); margin-top: 3px; }

  /* ── LISTA RECIENTE ── */
  .lista-reciente { display: flex; flex-direction: column; gap: 10px; }
  .lista-item { display: flex; align-items: center; gap: 14px; padding: 12px 14px; border-radius: 12px; border: 1px solid var(--border); transition: background 0.2s; }
  .lista-item:hover { background: var(--light); }
  .lista-icon { width: 38px; height: 38px; border-radius: 10px; background: #f4fcf6; display: flex; align-items: center; justify-content: center; color: var(--primary); font-size: 15px; flex-shrink: 0; }
  .lista-content { flex: 1; min-width: 0; }
  .lista-title { font-weight: 700; font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .lista-sub { font-size: 11px; color: var(--text-muted); margin-top: 2px; }
  .badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; display: inline-block; }
  .badge-programada { background: #dbeafe; color: #1d4ed8; }
  .badge-en-curso { background: #dcfce7; color: #15803d; }
  .badge-completada { background: #d1fae5; color: #065f46; }
  .badge-cancelada { background: #fee2e2; color: #b91c1c; }
  .badge-voto { background: rgba(45,122,62,0.1); color: var(--primary); }
  .badge-comunicacion { background: #dbeafe; color: #1d4ed8; }
  .badge-organizacion { background: #f3e8ff; color: #7e22ce; }
  .badge-personal { background: #fff3cd; color: #92400e; }
  .empty-state { text-align: center; padding: 40px 20px; color: var(--text-muted); font-size: 14px; }
  .alert-card { border: 1.5px solid #fecaca; background: #fff8f8; overflow: hidden; max-height: 3000px; opacity: 1; transition: max-height 0.35s ease, opacity 0.25s ease, padding 0.3s ease, border-width 0.3s ease, margin 0.3s ease; }
  .alert-card.ok { border-color: var(--border); background: white; }
  .alert-card.collapsed { max-height: 0; opacity: 0; padding-top: 0; padding-bottom: 0; border-width: 0; margin-bottom: 0 !important; }
  .alertas-toggle { display: flex; align-items: center; gap: 10px; cursor: pointer; user-select: none; }
  .alertas-toggle:hover .section-title { opacity: 0.75; }
  .alertas-chevron { font-size: 13px; color: var(--text-muted); transition: transform 0.3s ease; }
  .alertas-chevron.collapsed { transform: rotate(-90deg); }
  .alerta-row { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px dashed #f3e0e0; }
  .alerta-row:last-child { border-bottom: none; }
  .alerta-row i { color: var(--danger); font-size: 16px; width: 20px; text-align: center; flex-shrink: 0; }
  .alerta-info { display: flex; flex-direction: column; gap: 2px; }
  .alerta-info strong { font-size: 13px; color: var(--dark); }
  .alerta-info span { font-size: 12px; color: var(--danger); font-weight: 600; }
  .alerta-badge { font-size: 11px; font-weight: 800; padding: 4px 12px; border-radius: 20px; background: #fee2e2; color: #ef4444; }
  .empty-state i { font-size: 30px; opacity: 0.15; display: block; margin-bottom: 10px; }

  /* ── MODALS ── */
  .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(3px); }
  .modal-overlay.open { display: flex; }
  .modal-box { background: white; width: 90%; max-width: 400px; padding: 30px; border-radius: 20px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); }
  .modal-title { font-size: 20px; font-weight: 800; margin-bottom: 20px; }
  .form-group { margin-bottom: 15px; }
  .form-group label { display: block; font-size: 12px; font-weight: 700; color: #666; margin-bottom: 6px; }
  .form-group input { width: 100%; padding: 10px 14px; border: 2px solid #eee; border-radius: 10px; font-weight: 700; color: #333; outline: none; }
  .form-group input:focus { border-color: var(--primary); }

  /* ── PANELS ── */
  .view-panel { display: none; }
  .view-panel.active { display: block; animation: fadeIn 0.3s ease; }
  @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

  /* ── ERROR ── */
  .error-bar { background: #fee2e2; color: #991b1b; padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; font-size: 14px; }

  @media (max-width: 1024px) { .container { padding-left: 20px; padding-right: 20px; } .design-text { display: none; } }
  @media (max-width: 768px) { .kpi-grid { grid-template-columns: 1fr 1fr; } .charts-13, .charts-equal { grid-template-columns: 1fr; } }
</style>
</head>
<body>
<?php if (file_exists(__DIR__ . '/../../includes/components/sidebar_regional.php'))
    include __DIR__ . '/../../includes/components/sidebar_regional.php'; ?>

<div class="design-text">REGIONAL</div>

<div class="container">

  <!-- ── CONTEXT BAR ── -->
  <div class="context-bar">
    <div class="filters-left">
      <div class="filter-group">
        <span class="filter-label">Vista:</span>
        <div class="level-switcher">
          <button class="level-btn active" onclick="switchLevel('resumen', this)">Resumen Regional</button>
          <button class="level-btn" onclick="switchLevel('detalle', this)">Detalle por Zona</button>
        </div>
      </div>
      <div class="filter-group" id="entity-filter-group" style="display: none; border-left: 2px solid var(--border); padding-left: 20px;">
        <span class="filter-label">Zona:</span>
        <select id="entity-select" class="ui-select" onchange="updateDashboard()">
          <?php foreach($zonas as $z): ?>
            <option value="<?= htmlspecialchars($z['id']) ?>"><?= htmlspecialchars($z['nombre']) ?></option>
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
        <select id="month-select" class="ui-select" onchange="updateWeeksDropdown()"></select>
      </div>
      <div class="filter-group" id="week-filter-group">
        <span class="filter-label">Semana:</span>
        <select id="week-select" class="ui-select" onchange="updateDashboard()"></select>
      </div>
      <div class="filter-group">
        <span class="filter-label">&nbsp;</span>
        <a href="metas.php" class="btn-action btn-outline" style="text-decoration:none;"><i class="fas fa-bullseye"></i> Configurar Metas</a>
      </div>
    </div>
  </div>

  <!-- ── PANEL PRINCIPAL ── -->
  <div class="view-panel active" id="main-panel">

    <?php if (isset($error_db)): ?>
    <div class="error-bar"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error_db) ?></div>
    <?php endif; ?>

    <div class="page-header">
      <div class="header-content">
        <span class="ai-badge" id="dyn-badge">REGIÓN <?= htmlspecialchars(strtoupper($region_nombre)) ?> · <?= $stats['zonas'] ?> ZONAS</span>
        <h1 class="main-title" id="dyn-title">Dashboard de Gestión</h1>
        <p class="description"><span id="dyn-desc">Resumen ejecutivo de la región</span> · <span id="dyn-time" style="font-weight:700;"></span></p>
      </div>
      <div class="header-actions">
        <button class="btn-action btn-outline" onclick="updateDashboard(); cargarAlertas();"><i class="fas fa-sync-alt"></i> Actualizar</button>
        <button class="btn-action" onclick="generarPDF()"><i class="fas fa-file-alt"></i> Reportes</button>
        <a href="activaciones.php" class="btn-action btn-outline"><i class="fas fa-bolt"></i> Activaciones</a>
      </div>
    </div>

    <!-- ── KPI GRID ── -->
    <div class="kpi-grid">
      <div class="kpi-card">
        <div class="kpi-header"><div class="kpi-icon accent"><i class="fas fa-calendar-alt"></i></div></div>
        <div class="kpi-value" id="kpi-agenda">0</div>
        <div class="kpi-label">Eventos Agendados</div>
      </div>
      <div class="kpi-card">
        <div class="kpi-header"><div class="kpi-icon"><i class="fas fa-bolt"></i></div></div>
        <div class="kpi-value" id="kpi-act"><?= $stats['activaciones'] ?></div>
        <div class="kpi-label">Activaciones Totales</div>
      </div>
      <div class="kpi-card">
        <div class="kpi-header"><div class="kpi-icon info"><i class="fas fa-user-plus"></i></div></div>
        <div class="kpi-value" id="kpi-contactos"><?= number_format($stats['contactos']) ?></div>
        <div class="kpi-label">Contactos Nuevos</div>
      </div>
      <div class="kpi-card">
        <div class="kpi-header"><div class="kpi-icon warning"><i class="fas fa-clipboard-check"></i></div></div>
        <div class="kpi-value" id="kpi-cump">0%</div>
        <div class="kpi-label">Cumplimiento Agenda</div>
      </div>
      <div class="kpi-card">
        <div class="kpi-header"><div class="kpi-icon"><i class="fas fa-map-marked-alt"></i></div></div>
        <div class="kpi-value" id="kpi-zonas"><?= $stats['zonas'] ?></div>
        <div class="kpi-label">Zonas Activas</div>
        <div class="kpi-sub"><?= $stats['secciones'] ?> secciones electorales</div>
      </div>
      <div class="kpi-card">
        <div class="kpi-header"><div class="kpi-icon danger"><i class="fas fa-users"></i></div></div>
        <div class="kpi-value" id="kpi-usuarios"><?= $stats['usuarios'] ?></div>
        <div class="kpi-label">Usuarios de Zona</div>
        <div class="kpi-sub">Activos en tu región</div>
      </div>
    </div>

    <!-- ── CUMPLIMIENTO + TENDENCIA ── -->
    <div class="section-header alertas-toggle" onclick="toggleAlertas()">
      <h3 class="section-title"><span class="section-title-bar" style="background:var(--danger);"></span>Alertas de Inactividad (72h)<i class="fas fa-chevron-down alertas-chevron" id="alertas-chevron"></i></h3>
      <span class="alerta-badge" id="alertas-badge" style="display:none;">0 alertas</span>
    </div>
    <div class="chart-card alert-card" id="alertas-card" style="margin-bottom:10px;">
      <div class="chart-card-sub" style="margin-bottom:14px;">Zonas de tu región sin ningún evento o activación registrado en las últimas 72 horas.</div>
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
            <div class="chart-card-sub" id="lbl-metas">Meta: 0 Voto · 0 Com · 0 Org</div>
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
          <!-- Barra extra: Contactos vs Meta -->
          <div class="compliance-row" style="margin-top:8px; padding-top:14px; border-top:1px solid var(--border);">
            <div class="compliance-header">
              <span class="compliance-name"><i class="fas fa-handshake" style="color:#2d7a3e;margin-right:6px"></i>Contactos</span>
              <div class="compliance-meta">
                <span><?= number_format($stats['contactos']) ?></span><span> / </span><span><?= number_format($stats['meta_contactos']) ?></span>
                <span class="compliance-pct" style="color:<?= $pct >= 100 ? '#22c55e' : ($pct >= 50 ? '#f59e0b' : '#ef4444') ?>"> <?= $pct ?>%</span>
              </div>
            </div>
            <div class="bar-track"><div class="bar-fill contactos" style="width:<?= $pct ?>%"></div></div>
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

    <!-- ── ACTIVACIONES EN MOVIMIENTO ── -->
    <div class="section-header">
      <h3 class="section-title"><span class="section-title-bar"></span>Activaciones en Movimiento</h3>
    </div>
    <div class="charts-section charts-equal">
      <div style="display: flex; flex-direction: column; gap: 20px;">
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
          <div style="height:170px"><canvas id="act-type-chart"></canvas></div>
        </div>
      </div>

      <div class="chart-card">
        <div class="chart-card-header">
          <div><div class="chart-card-title">Resultados de Impacto</div><div class="chart-card-sub">Top zonas (Contactos vs Asistentes)</div></div>
        </div>
        <div class="contact-metrics">
          <div class="contact-metric"><div class="cm-value" id="res-cont" style="color:#3b82f6">0</div><div class="cm-label">Contactos Nuevos</div></div>
          <div class="contact-metric"><div class="cm-value" id="res-asis" style="color:#8b5cf6">0</div><div class="cm-label">Asistentes</div></div>
          <div class="contact-metric"><div class="cm-value" id="res-cpc" style="color:#ef4444">$0</div><div class="cm-label">Costo por Contacto</div></div>
        </div>
        <div style="height:140px; margin-top:20px;"><canvas id="contacts-chart"></canvas></div>
      </div>
    </div>

    <!-- ── ZONAS + AGENDA (estáticas desde PHP) ── -->
    <div class="charts-section charts-equal" style="margin-top:36px;">
      <div class="chart-card">
        <div class="section-header" style="margin-top:0">
          <div class="section-title"><span class="section-title-bar"></span>Zonas de la Región</div>
          <a href="estructura.php" class="section-link"><i class="fas fa-arrow-right"></i> Ver todo</a>
        </div>
        <?php if ($zonas): ?>
        <div class="zonas-list">
          <?php foreach ($zonas as $z): ?>
          <div class="zona-item">
            <div>
              <div class="zona-name"><?= htmlspecialchars($z['nombre']) ?><?= $z['clave'] ? ' <span style="color:var(--text-muted);font-weight:400;font-size:12px">('.$z['clave'].')</span>' : '' ?></div>
              <div class="zona-meta"><?= $z['num_secciones'] ?> secciones · Meta: <?= number_format($z['meta']) ?></div>
            </div>
            <i class="fas fa-chevron-right" style="color:var(--text-muted);font-size:12px"></i>
          </div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state"><i class="fas fa-map-marked-alt"></i>No hay zonas configuradas.</div>
        <?php endif; ?>
      </div>

      <div class="chart-card">
        <div class="section-header" style="margin-top:0">
          <div class="section-title"><span class="section-title-bar"></span>Próximos Eventos</div>
          <a href="agenda.php" class="section-link"><i class="fas fa-arrow-right"></i> Ver todo</a>
        </div>
        <?php if ($agenda_proxima): ?>
        <div class="lista-reciente">
          <?php foreach ($agenda_proxima as $ev): ?>
          <div class="lista-item">
            <div class="lista-icon"><i class="fas fa-calendar-day"></i></div>
            <div class="lista-content">
              <div class="lista-title"><?= htmlspecialchars($ev['titulo']) ?></div>
              <div class="lista-sub">
                <?= htmlspecialchars($ev['fecha']) ?>
                <?= $ev['inicio'] ? ' · '.substr($ev['inicio'],0,5) : '' ?>
                <?= $ev['municipio'] ? ' · '.htmlspecialchars($ev['municipio']) : '' ?>
              </div>
            </div>
            <span class="badge badge-<?= $ev['eje'] ?>"><?= ucfirst($ev['eje']) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state"><i class="fas fa-calendar-times"></i>Sin eventos próximos.</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ── TABLA DE DESEMPEÑO ── -->
    <div id="wrapper-tabla">
      <div class="section-header">
        <h3 class="section-title"><span class="section-title-bar"></span><span id="tabla-title">Desempeño por Zona</span></h3>
        <a href="activaciones.php" class="section-link"><i class="fas fa-arrow-right"></i> Ver todas</a>
      </div>
      <div class="chart-card" style="padding:0;overflow:hidden; margin-bottom:40px;">
        <table class="data-table">
          <thead>
            <tr>
              <th>#</th><th id="th-entidad">Zona</th><th>Activaciones</th><th>Contactos Nuevos</th><th>Asistentes</th><th>Costo</th>
            </tr>
          </thead>
          <tbody id="table-body">
            <?php if ($activaciones_recientes): ?>
              <?php foreach ($activaciones_recientes as $i => $act): ?>
              <tr>
                <td><span class="rank-badge <?= $i < 3 ? 'top' : '' ?>"><?= $i+1 ?></span></td>
                <td><strong><?= htmlspecialchars($act['titulo']) ?></strong></td>
                <td><?= htmlspecialchars($act['tipo'] ?? '—') ?></td>
                <td><strong style="color:#2d7a3e">—</strong></td>
                <td>—</td>
                <td><span class="badge badge-<?= $act['estatus'] ?>"><?= ucfirst(str_replace('-',' ',$act['estatus'])) ?></span></td>
              </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="6"><div class="empty-state"><i class="fas fa-bolt"></i>Sin activaciones registradas.</div></td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div><!-- /main-panel -->
</div><!-- /container -->

<!-- ── MODAL METAS ── -->
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
const num_zonas     = <?= $stats['zonas'] ?>;
const region_id         = '<?= $user_region ?>';
const region_nombre_api = '<?= addslashes($region_nombre) ?>';
const user_estado_js    = '<?= addslashes($user_estado) ?>';
const region_key    = 'REGION_<?= $user_region ?>';
let viewMode        = 'resumen';
let charts          = {};
let currentMeta     = <?= ($init_meta_voto + $init_meta_com + $init_meta_org) ?: 100 ?>;
let currentMetaVoto = <?= $init_meta_voto ?>;
let currentMetaCom  = <?= $init_meta_com ?>;
let currentMetaOrg  = <?= $init_meta_org ?>;

const hoy = new Date();
const mesesArr = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

window.addEventListener('DOMContentLoaded', () => {
  Chart.defaults.font.family = "'Arial', sans-serif";
  Chart.defaults.color = '#888';

  const ms = document.getElementById('month-select');
  ms.innerHTML = '';
  for(let i = 0; i < 12; i++){
    ms.innerHTML += `<option value="${i+1}" ${i === hoy.getMonth() ? 'selected' : ''}>${mesesArr[i]}</option>`;
  }
  ms.innerHTML += `<option value="todas">Todo el Año</option>`;
  updateWeeksDropdown();
  // Pintar barras de metas con valores iniciales
  actualizarBarras(0, 0, 0);
  cargarAlertas();
});

// ══ ALERTAS DE INACTIVIDAD (72h) ══
// ══ ALERTAS DE INACTIVIDAD (72h) — desplegable ══
const ALERTAS_COLLAPSE_KEY = 'gb_alertas_collapsed_cr';
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
  const zonas = data.zonas || [];
  const badge = document.getElementById('alertas-badge');
  const card = document.getElementById('alertas-card');
  const cont = document.getElementById('alertas-content');
  if (zonas.length > 0) {
    badge.textContent = zonas.length + (zonas.length === 1 ? ' alerta' : ' alertas');
    badge.style.display = 'inline-block';
    card.classList.remove('ok');
    cont.innerHTML = zonas.map(z =>
      `<div class="alerta-row"><i class="fas fa-map-pin"></i><div class="alerta-info"><strong>Zona ${z.nombre}</strong><span>${fmtInactividad(z.horas_inactividad)}</span></div></div>`
    ).join('');
  } else {
    badge.style.display = 'none';
    card.classList.add('ok');
    cont.innerHTML = '<div class="empty-state"><i class="fas fa-check-circle" style="color:var(--success);font-size:20px;display:block;margin-bottom:8px;"></i>Todas las zonas de tu región tienen actividad reciente.</div>';
  }
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
  for(let i = 0; i < 5; i++) {
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
    const kpiAct = getTxt('kpi-act');
    const kpiCon = getTxt('kpi-contactos');
    const kpiCum = getTxt('kpi-cump');
    const kpiZon = getTxt('kpi-zonas');
    const kpiUsr = getTxt('kpi-usuarios');

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
                <td style="border: 1px solid #ccc; padding: 12px;"><strong>Zonas Activas:</strong><br><span style="font-size:18px; font-weight:bold;">${kpiZon}</span></td>
                <td style="border: 1px solid #ccc; padding: 12px;"><strong>Usuarios de Zona:</strong><br><span style="font-size:18px; font-weight:bold;">${kpiUsr}</span></td>
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

// ── CORE FETCH ──
// ══ METAS REALES (módulo Metas) ══
let metasRealesActuales = { voto: 0, comunicacion: 0, organizacion: 0 };
async function obtenerMetasReales(scope, entidadNombre, anio, mes, semana) {
  try {
    const url = `../../api/metas/procesar.php?action=resumen&nivel=zona&scope=${scope}&entidad=${encodeURIComponent(entidadNombre||"")}&anio=${anio}&mes=${mes}&semana=${semana}`;
    const res = await fetch(url);
    const data = await res.json();
    if (data.ok) return { voto: data.voto||0, comunicacion: data.comunicacion||0, organizacion: data.organizacion||0 };
  } catch (e) { console.error('Error cargando metas reales:', e); }
  return { voto: 0, comunicacion: 0, organizacion: 0 };
}

async function updateDashboard() {
  const mSelect = document.getElementById('month-select');
  const wSelect = document.getElementById('week-select');
  const ySelect = document.getElementById('year-select');
  const selZona = document.getElementById('entity-select').value;
  const targetMonth = mSelect.value;
  const targetWeek  = wSelect.value;
  const targetYear  = ySelect.value;
  const mName = mSelect.options[mSelect.selectedIndex].text;
  const weekText = wSelect.options[wSelect.selectedIndex].text;
  let tText;
  if (targetMonth === 'todas') tText = `Año ${targetYear}`;
  else if (targetWeek === 'todas') tText = `${mName} ${targetYear}`;
  else tText = `${weekText} · ${targetYear}`;
  document.getElementById('dyn-time').textContent = tText;

  if(viewMode === 'resumen') {
    document.getElementById('dyn-badge').textContent = `REGIÓN · ${num_zonas} ZONAS`;
    document.getElementById('dyn-title').textContent = 'Dashboard de Gestión';
    document.getElementById('dyn-desc').textContent  = 'Resumen ejecutivo de la región';
    document.getElementById('tabla-title').textContent = 'Desempeño por Zona';
    document.getElementById('th-entidad').textContent  = 'Zona';
  } else {
    const zonaNombre = document.getElementById('entity-select').options[document.getElementById('entity-select').selectedIndex]?.text || selZona;
    document.getElementById('dyn-badge').textContent = `DETALLE DE ZONA`;
    document.getElementById('dyn-title').textContent = zonaNombre;
    document.getElementById('dyn-desc').textContent  = 'Análisis microsegmentado';
    document.getElementById('tabla-title').textContent = 'Desempeño por Municipio';
    document.getElementById('th-entidad').textContent  = 'Municipio';
  }

  try {
    const lvl = viewMode === 'detalle' ? 'zona' : 'region';
    const id  = viewMode === 'detalle' ? selZona : region_nombre_api;
    const zonaNombreMeta = viewMode === 'detalle'
      ? (document.getElementById('entity-select').options[document.getElementById('entity-select').selectedIndex]?.text || '')
      : '';
    const scopeMeta = viewMode === 'detalle' ? 'una' : 'propio';
    const [response, metasReales] = await Promise.all([
      fetch(`../../api/dashboard/api_dashboard.php?context=regional&estado=${encodeURIComponent(user_estado_js)}&level=${lvl}&id=${encodeURIComponent(id)}&mes=${targetMonth}&semana=${targetWeek}&anio=${targetYear}`),
      obtenerMetasReales(scopeMeta, zonaNombreMeta, targetYear, targetMonth, targetWeek)
    ]);
    const data = await response.json();
    if(data.error) { console.error(data.error); return; }
    metasRealesActuales = metasReales;
    renderUI(data, targetWeek);
  } catch (error) {
    console.error("Fetch error:", error);
  }
}

function renderUI(data, targetWeek) {
  document.getElementById('kpi-agenda').textContent    = data.kpis.eventos;
  document.getElementById('kpi-act').textContent       = data.kpis.activaciones;
  document.getElementById('kpi-contactos').textContent = parseInt(data.kpis.contactos).toLocaleString();

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

  document.getElementById('st-prog').textContent = data.charts.status['programada'] || 0;
  document.getElementById('st-curs').textContent = data.charts.status['en-curso']   || 0;
  document.getElementById('st-comp').textContent = data.charts.status['completada'] || 0;
  document.getElementById('st-canc').textContent = data.charts.status['cancelada']  || 0;

  document.getElementById('res-cont').textContent = parseInt(data.kpis.contactos).toLocaleString();
  document.getElementById('res-asis').textContent = parseInt(data.kpis.asistentes).toLocaleString();
  let cpc = data.kpis.contactos > 0 ? Math.round(data.kpis.inversion / data.kpis.contactos) : 0;
  document.getElementById('res-cpc').textContent = '$' + cpc;

  renderTrend(data.charts.tendencia);
  renderType(data.charts.tipos);
  renderContactsBar(data.table);

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
  let mv = metasRealesActuales.voto, mc = metasRealesActuales.comunicacion, mo = metasRealesActuales.organizacion;
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
  if (!rawTrend) rawTrend = [];
  let sems = [...new Set(rawTrend.map(r => `Sem ${r.sem}`))];
  let dtV = sems.map(s => rawTrend.filter(x => `Sem ${x.sem}` === s && String(x.eje).toLowerCase().match(/voto|territorio/)).reduce((sum, i) => sum + (i.total || 0), 0));
  let dtC = sems.map(s => rawTrend.filter(x => `Sem ${x.sem}` === s && String(x.eje).toLowerCase().match(/comunic/)).reduce((sum, i) => sum + (i.total || 0), 0));
  let dtO = sems.map(s => rawTrend.filter(x => `Sem ${x.sem}` === s && String(x.eje).toLowerCase().match(/org/)).reduce((sum, i) => sum + (i.total || 0), 0));
  charts['trend'] = new Chart(document.getElementById('trend-chart'), {
    type: 'line',
    data: { labels: sems, datasets: [
      { label: 'Voto', data: dtV, borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,0.08)', tension: 0.4, fill: true },
      { label: 'Com.', data: dtC, borderColor: '#8b5cf6', backgroundColor: 'rgba(139,92,246,0.08)', tension: 0.4, fill: true },
      { label: 'Org.', data: dtO, borderColor: '#f59e0b', backgroundColor: 'rgba(245,158,11,0.08)', tension: 0.4, fill: true },
    ]},
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true } } }
  });
}

function renderType(tipos) {
  if(charts['type']) charts['type'].destroy();
  if(!tipos) tipos = {};
  let tt = tipos['territorial'] || 0, td = tipos['digital'] || 0, ts = tipos['simbolica'] || 0, te = tipos['estructura'] || 0;
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
  let bLabels = top.map(r => r.nombre.length > 10 ? r.nombre.substring(0,10)+'..' : r.nombre);
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