<?php
// ── Seguridad ──────────────────────────────────────────────
if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
} else { session_start(); }

$rol = $_SESSION['user_role'] ?? '';
$permitidos = ['admin','administrador','superadmin','super admin', 'coordinador_estatal'];
if (!in_array($rol, $permitidos)) { header('Location: /public/index.php'); exit(); }

// ── Conexión y carga de datos ──────────────────────────────
$activaciones = [];
$estados_mx_db = []; // Arreglo dinámico para el mapa

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

    // 1. Obtener Activaciones
    $stmt = $pdo->query("
        SELECT id, codigo_referencia, titulo, tipo, estatus, estado, municipio,
               DATE_FORMAT(fecha_actividad,'%Y-%m-%d') AS fecha_actividad,
               coordinador, seccion_electoral, contactos_estimados,
               asistentes_estimados, costo, notas,
               latitud, longitud, fotos
        FROM activaciones
        WHERE activo = 1
        ORDER BY fecha_creacion DESC
    ");
    $activaciones = $stmt->fetchAll();

    // 2. Obtener Estados y Municipios calculando el centro desde sus colonias
    $stmtGeo = $pdo->query("
        SELECT 
            e.nombre as estado,
            m.nombre as municipio,
            AVG(c.latitud) as mun_lat,
            AVG(c.longitud) as mun_lng
        FROM estados e
        INNER JOIN municipios m ON e.clave = m.estado_id
        INNER JOIN colonias c ON m.id = c.municipio
        GROUP BY e.clave, m.id
        ORDER BY e.nombre ASC, m.nombre ASC
    ");
    
    foreach ($stmtGeo->fetchAll() as $row) {
        $est = $row['estado'];
        if (!isset($estados_mx_db[$est])) {
            $estados_mx_db[$est] = [
                'lat' => (float)($row['mun_lat'] ?? 23.6345), // fallback centro de mx
                'lng' => (float)($row['mun_lng'] ?? -102.5528),
                'municipios' => []
            ];
        }
        if ($row['municipio']) {
            $estados_mx_db[$est]['municipios'][$row['municipio']] = [
                'lat' => (float)$row['mun_lat'],
                'lng' => (float)$row['mun_lng']
            ];
        }
    }

} catch (PDOException $e) {
    $error_db = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activaciones - Admin</title>
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
    textarea.form-input-styled { resize:vertical; min-height:90px; font-weight:400; }
    .table-section { background:var(--white); border-radius:20px; padding:30px; border:1px solid var(--gray-200); box-shadow:var(--shadow-sm); }
    .section-header { margin-bottom:25px; display:flex; justify-content:space-between; align-items:center; }
    .section-title { font-size:20px; font-weight:800; color:var(--dark); }
    .modern-table { width:100%; border-collapse:separate; border-spacing:0; }
    .modern-table th { text-align:left; padding:15px 20px; color:var(--text-muted); font-size:12px; text-transform:uppercase; letter-spacing:1px; border-bottom:2px solid var(--gray-200); }
    .modern-table td { padding:18px 20px; border-bottom:1px solid var(--gray-200); font-size:14px; color:var(--dark); font-weight:500; vertical-align:middle; transition:background 0.2s; }
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
    .form-group { display:flex; flex-direction:column; gap:8px; }
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
    /* SPINNER guardar */
    .btn-save.loading { opacity:0.7; pointer-events:none; }
    /* FOTOS */
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

    /* ── NAVEGADOR ESTADO / MUNICIPIO ── */
    #modal-nav { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.55); backdrop-filter:blur(4px); z-index:3000; align-items:center; justify-content:center; }
    #modal-nav.open { display:flex; }
    .nav-box { background:#fff; border-radius:20px; box-shadow:0 30px 60px rgba(0,0,0,0.18); padding:36px 40px; width:90%; max-width:480px; position:relative; animation:fadeInUp 0.35s ease; }
    .nav-box h3 { font-size:20px; font-weight:900; margin-bottom:4px; color:var(--dark); }
    .nav-box p.nav-sub { font-size:13px; color:var(--text-muted); margin-bottom:24px; }
    .nav-step { display:flex; flex-direction:column; gap:8px; margin-bottom:18px; }
    .nav-step label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--text-muted); }
    .nav-step select { width:100%; padding:13px 16px; border-radius:11px; border:2px solid var(--gray-200); background:var(--gray-100); font-size:14px; font-weight:600; color:var(--dark); outline:none; appearance:none; font-family:Arial,sans-serif; transition:border-color .2s; cursor:pointer; }
    .nav-step select:focus { border-color:var(--primary); background:#fff; }
    .nav-step select:disabled { opacity:.45; cursor:default; }
    .btn-nav-ir { width:100%; padding:13px; background:var(--primary); color:#fff; border:none; border-radius:12px; font-size:15px; font-weight:800; cursor:pointer; transition:background .2s,transform .15s; box-shadow:0 4px 15px rgba(45,122,62,.3); font-family:Arial,sans-serif; margin-top:4px; }
    .btn-nav-ir:hover { background:var(--primary-dark); transform:translateY(-2px); }
    .btn-nav-ir:disabled { opacity:.4; pointer-events:none; }
    .close-nav { position:absolute; top:18px; right:20px; background:none; border:none; font-size:20px; cursor:pointer; color:#ccc; transition:color .2s; }
    .close-nav:hover { color:var(--primary); }
    .btn-nav-quick { display:inline-flex; align-items:center; gap:6px; padding:8px 14px; background:rgba(45,122,62,.1); color:var(--primary); border:none; border-radius:9px; font-size:12px; font-weight:700; cursor:pointer; font-family:Arial,sans-serif; margin-bottom:10px; transition:background .2s; }
    .btn-nav-quick:hover { background:rgba(45,122,62,.18); }

    @media(max-width:1024px){ .container{padding-left:20px;padding-right:20px;} .stats-row{grid-template-columns:1fr;} .filters-bar{flex-direction:column;} .design-text{display:none;} }
    </style>
</head>
<body>
    <?php if (file_exists(__DIR__ . '/../../includes/components/sidebar_admin.php')) include __DIR__ . '/../../includes/components/sidebar_admin.php'; ?>
    <div class="design-text">ACTIVACIONES</div>

    <div class="container">

        <div class="page-header animate-up">
            <div>
                <div class="ai-badge">MOVILIZACIÓN</div>
                <h1 class="main-title">Activaciones.</h1>
                <p class="description">Caravanas, rescates, brigadas y más. Registra y monitorea cada activación territorial.</p>
            </div>
            <div class="header-actions">
                <button class="btn-primary-action" onclick="abrirModal()">
                    <i class="fas fa-plus-circle"></i> Nueva Activación
                </button>
            </div>
        </div>

        <?php if(isset($error_db)): ?>
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
        </div>

        <div class="table-section animate-up animate-delay-2">
            <div class="section-header">
                <div class="section-title">Registro de Activaciones</div>
                <div id="count-label" style="font-size:13px;color:var(--text-muted);font-weight:600;"></div>
            </div>
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Activación</th><th>Tipo</th><th>Fecha</th><th>Estatus</th><th>Estado</th>
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

    <div class="modal" id="modal-form">
        <div class="modal-content">
            <div class="close-modal" onclick="cerrarModal('modal-form')"><i class="fas fa-times"></i></div>
            <h2 id="form-title" style="font-size:24px;font-weight:800;margin-bottom:5px;">Nueva Activación</h2>
            <p style="font-size:14px;color:#999;margin-bottom:30px;">Registra o edita una activación en el sistema.</p>

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
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
                <div class="form-group">
                    <label class="form-label">Coordinador</label>
                    <input type="text" id="f-coordinador" class="form-input-styled" placeholder="Nombre del coordinador">
                </div>
                <div class="form-group">
                    <label class="form-label">Sección Electoral / Colonia</label>
                    <input type="text" id="f-seccion" class="form-input-styled" placeholder="Ej. Col. Pitic, Sección 042">
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
            <div class="form-group" style="margin-bottom:5px;">
                <label class="form-label">Ubicación / Georeferencia</label>
                <button class="btn-nav-quick" onclick="abrirNavMapa()" type="button"><i class="fas fa-map-marker-alt"></i> Ir a Estado / Municipio</button>
                <p class="map-note"><i class="fas fa-hand-pointer"></i> Haz clic en el mapa para colocar el marcador</p>
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
            <div class="modal-footer">
                <button class="btn-danger" id="btn-eliminar" style="visibility:hidden"><i class="fas fa-trash-alt"></i> Eliminar</button>
                <div style="display:flex;gap:10px;">
                    <button class="btn-cancel" onclick="cerrarModal('modal-form')">Cancelar</button>
                    <button class="btn-save" id="btn-guardar" onclick="guardar()"><i class="fas fa-check"></i> Guardar</button>
                </div>
            </div>
        </div>
    </div>

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
                <div class="detail-item"><strong>Coordinador</strong><span id="det-coordinador">—</span></div>
                <div class="detail-item"><strong>Sección / Zona</strong><span id="det-seccion">—</span></div>
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


    <div id="modal-nav">
        <div class="nav-box">
            <button class="close-nav" onclick="cerrarNavMapa()"><i class="fas fa-times"></i></button>
            <h3><i class="fas fa-map-marked-alt" style="color:var(--primary);margin-right:8px;"></i>Navegar al mapa</h3>
            <p class="nav-sub">Selecciona el estado y municipio para centrar el mapa rápidamente.</p>
            <div class="nav-step">
                <label>Estado</label>
                <select id="nav-estado" onchange="cargarMunicipios()">
                    <option value="">— Selecciona un estado —</option>
                </select>
            </div>
            <div class="nav-step">
                <label>Municipio</label>
                <select id="nav-municipio" disabled>
                    <option value="">— Primero selecciona estado —</option>
                </select>
            </div>
            <button class="btn-nav-ir" id="btn-nav-ir" onclick="irAlMapa()" disabled>
                <i class="fas fa-crosshairs"></i> Centrar mapa aquí
            </button>
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
// ── Datos desde PHP → JS ────────────────────────────────────
// Mapeamos las columnas de BD a los nombres que usa el JS del frontend
const activacionesDB = <?php
    $out = [];
    foreach ($activaciones as $row) {
        $out[] = [
            'id'          => (int)$row['id'],
            'titulo'      => $row['titulo'],
            'tipo'        => $row['tipo'],
            'fecha'       => $row['fecha_actividad'] ?? '',
            'estatus'     => $row['estatus'],
            'coordinador' => $row['coordinador'] ?? '',
            'seccion'     => $row['seccion_electoral'] ?? '',
            'contactos'   => (int)($row['contactos_estimados'] ?? 0),
            'asistentes'  => (int)($row['asistentes_estimados'] ?? 0),
            'costo'       => (float)($row['costo'] ?? 0),
            'notas'       => $row['notas'] ?? '',
            'lat'         => $row['latitud']  ? (float)$row['latitud']  : null,
            'lng'         => $row['longitud'] ? (float)$row['longitud'] : null,
            'fotos'       => json_decode($row['fotos'] ?? '[]', true) ?: [],
            'estado'      => $row['estado'] ?? '',
            'municipio'   => $row['municipio'] ?? '',
        ];
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
?>;

let activaciones = activacionesDB;
let editandoId = null, estatusSeleccionado = null;
let mapaInst = null, marcador = null, detMapaInst = null;
let fotosActuales = [];
let lbFotos = [], lbIdx = 0;

const API = '../../api/activaciones/procesar.php';
const TIPO_LABELS = {territorial:'Territorial',digital:'Digital',simbolica:'Simbólica / Impacto',estructura:'Estructura'};

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

    // 2. Filtrado de la lista
    let lista = [...activaciones].sort((a,b) => (b.fecha||'').localeCompare(a.fecha||''));
    if(q)         lista = lista.filter(a => (a.titulo||'').toLowerCase().includes(q) || (a.seccion||'').includes(q));
    if(est)       lista = lista.filter(a => a.estatus === est);
    if(tipo)      lista = lista.filter(a => a.tipo === tipo);
    if(estFiltro) lista = lista.filter(a => a.estado === estFiltro);
    if(munFiltro) lista = lista.filter(a => a.municipio === munFiltro);

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

        rowHTML += `
            <td style="text-align:right;"><div class="stat-cell-label">Contactos</div><div class="stat-cell">${(a.contactos||0).toLocaleString('es-MX')}</div></td>
            <td style="text-align:right;"><div class="stat-cell-label">Asistentes</div><div class="stat-cell">${(a.asistentes||0).toLocaleString('es-MX')}</div></td>
            <td style="text-align:right;"><div class="stat-cell-label">Costo</div><div class="stat-cell neutral">$${parseFloat(a.costo||0).toLocaleString('es-MX')}</div></td>
            <td class="action-cell">
                <button class="action-btn" onclick="toggleDropdown(this)"><i class="fas fa-ellipsis-v"></i></button>
                <div class="dropdown-menu">
                    <button class="dropdown-item" onclick="verDetalle(${a.id})"><i class="fas fa-eye"></i> Ver detalle</button>
                    <button class="dropdown-item" onclick="abrirModal(${a.id})"><i class="fas fa-pen"></i> Editar</button>
                    ${(a.estatus === 'en-curso' || a.estatus === 'completada')
                        ? '<button class="dropdown-item delete-item" disabled title="No se puede eliminar una activación en curso o completada" style="opacity:0.4;cursor:not-allowed;"><i class=\"fas fa-trash-alt\"></i> Eliminar</button>'
                        : `<button class="dropdown-item delete-item" onclick="eliminar(${a.id})"><i class="fas fa-trash-alt"></i> Eliminar</button>`}
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

// ── MODAL FORM ─────────────────────────────────────────────
function abrirModal(id=null){
    editandoId = id ? parseInt(id) : null; id = editandoId; estatusSeleccionado = null;
    document.getElementById('form-title').textContent = id ? 'Editar Activación' : 'Nueva Activación';
    ['f-titulo','f-tipo','f-fecha','f-coordinador','f-seccion','f-contactos','f-asistentes','f-costo','f-notas','f-lat','f-lng']
        .forEach(f => document.getElementById(f).value = '');
    fotosActuales = []; renderFotosPreview();
    document.querySelectorAll('.est-btn').forEach(b => b.className = 'est-btn');
    const btnEl = document.getElementById('btn-eliminar');
    
    // Resetear los selects de ubicación
    document.getElementById('nav-estado').value = '';
    document.getElementById('nav-municipio').innerHTML = '<option value="">— Primero selecciona estado —</option>';
    document.getElementById('nav-municipio').disabled = true;

    if(!id){
        document.getElementById('f-fecha').value = new Date().toISOString().split('T')[0];
        btnEl.style.visibility = 'hidden';
    } else {
        const a = activaciones.find(x => parseInt(x.id) === parseInt(id));
        if(a){
            document.getElementById('f-titulo').value     = a.titulo||'';
            document.getElementById('f-tipo').value       = a.tipo||'';
            document.getElementById('f-fecha').value      = a.fecha||'';
            document.getElementById('f-coordinador').value= a.coordinador||'';
            document.getElementById('f-seccion').value    = a.seccion||'';
            document.getElementById('f-contactos').value  = a.contactos||'';
            document.getElementById('f-asistentes').value = a.asistentes||'';
            document.getElementById('f-costo').value      = a.costo||'';
            document.getElementById('f-notas').value      = a.notas||'';
            if(a.lat) document.getElementById('f-lat').value = a.lat;
            if(a.lng) document.getElementById('f-lng').value = a.lng;
            if(a.estatus) seleccionarEstatus(a.estatus);
            fotosActuales = (a.fotos||[]).map(d => ({dataUrl:d, name:'foto'}));
            renderFotosPreview();

            // Rellenar valores previos del estado y municipio si los tiene guardados
            if (a.estado && document.querySelector(`#nav-estado option[value="${a.estado}"]`)) {
                document.getElementById('nav-estado').value = a.estado;
                cargarMunicipios();
                if (a.municipio && document.querySelector(`#nav-municipio option[value="${a.municipio}"]`)) {
                    document.getElementById('nav-municipio').value = a.municipio;
                }
            }
        }
        btnEl.style.visibility = 'visible';
        btnEl.onclick = () => eliminar(id);
    }
    document.getElementById('modal-form').classList.add('open');
    setTimeout(() => iniciarMapa(), 120);
}
function cerrarModal(id){
    document.getElementById(id).classList.remove('open');
    if(id==='modal-form' && mapaInst){ mapaInst.remove(); mapaInst=null; marcador=null; }
    if(id==='modal-det'  && detMapaInst){ detMapaInst.remove(); detMapaInst=null; }
}
function seleccionarEstatus(est){
    estatusSeleccionado = est;
    document.querySelectorAll('.est-btn').forEach(b => b.className = b.dataset.est===est ? `est-btn sel-${est}` : 'est-btn');
}
function iniciarMapa(){
    const el = document.getElementById('mapa'); if(!el||mapaInst) return;
    const lat0 = parseFloat(document.getElementById('f-lat').value) || 29.0729; // Coordenada por default o guardada
    const lng0 = parseFloat(document.getElementById('f-lng').value) || -110.9559;
    mapaInst = L.map('mapa').setView([lat0,lng0],12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'© OpenStreetMap'}).addTo(mapaInst);
    if(document.getElementById('f-lat').value) marcador = L.marker([lat0,lng0]).addTo(mapaInst);
    mapaInst.on('click',function(e){
        const{lat,lng}=e.latlng;
        document.getElementById('f-lat').value = lat.toFixed(6);
        document.getElementById('f-lng').value = lng.toFixed(6);
        if(marcador) mapaInst.removeLayer(marcador);
        marcador = L.marker([lat,lng]).addTo(mapaInst);
    });
}

// ── GUARDAR → fetch a procesar.php ────────────────────────
async function guardar(){
    const titulo = document.getElementById('f-titulo').value.trim();
    const tipo   = document.getElementById('f-tipo').value;
    const fecha  = document.getElementById('f-fecha').value;
    if(!titulo){ alert('El título es obligatorio.'); return; }
    if(!tipo)  { alert('Selecciona un tipo.'); return; }
    if(!fecha) { alert('La fecha es obligatoria.'); return; }
    if(!estatusSeleccionado){ alert('Selecciona un estatus.'); return; }

    const btn = document.getElementById('btn-guardar');
    btn.classList.add('loading');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

    // Se extrae la información del modal de ubicación si la llenó
    const estVal = document.getElementById('nav-estado') ? document.getElementById('nav-estado').value : '';
    const munVal = document.getElementById('nav-municipio') ? document.getElementById('nav-municipio').value : '';

    const fd = new FormData();
    fd.append('action',              editandoId ? 'update' : 'create');
    if(editandoId) fd.append('id',   editandoId);
    fd.append('titulo',              titulo);
    fd.append('tipo',                tipo);
    fd.append('fecha_actividad',     fecha);
    fd.append('estatus',             estatusSeleccionado);
    fd.append('coordinador',         document.getElementById('f-coordinador').value.trim());
    fd.append('seccion_electoral',   document.getElementById('f-seccion').value.trim());
    fd.append('contactos_estimados', document.getElementById('f-contactos').value || 0);
    fd.append('asistentes_estimados',document.getElementById('f-asistentes').value || 0);
    fd.append('costo',               document.getElementById('f-costo').value || 0);
    fd.append('notas',               document.getElementById('f-notas').value.trim());
    fd.append('latitud',             document.getElementById('f-lat').value || '');
    fd.append('longitud',            document.getElementById('f-lng').value || '');
    fd.append('estado',              estVal);
    fd.append('municipio',           munVal);
    fd.append('fotos',               JSON.stringify(fotosActuales.map(f => f.dataUrl)));

    try {
        const res  = await fetch(API, {method:'POST', body:fd});
        const data = await res.json();
        if(!data.ok) throw new Error(data.msg || 'Error desconocido');

        const obj = {
            id:          editandoId ? parseInt(editandoId) : parseInt(data.id),
            titulo, tipo, fecha, estatus: estatusSeleccionado,
            coordinador: document.getElementById('f-coordinador').value.trim(),
            seccion:     document.getElementById('f-seccion').value.trim(),
            contactos:   parseInt(document.getElementById('f-contactos').value)||0,
            asistentes:  parseInt(document.getElementById('f-asistentes').value)||0,
            costo:       parseFloat(document.getElementById('f-costo').value)||0,
            notas:       document.getElementById('f-notas').value.trim(),
            lat:         document.getElementById('f-lat').value || null,
            lng:         document.getElementById('f-lng').value || null,
            estado:      estVal,
            municipio:   munVal,
            fotos:       fotosActuales.map(f => f.dataUrl),
        };
        if(editandoId){
            const i = activaciones.findIndex(x => parseInt(x.id) === parseInt(editandoId));
            if(i !== -1) activaciones[i] = obj;
        } else {
            activaciones.push(obj);
        }
        cerrarModal('modal-form');
        renderTabla();
    } catch(err){
        alert('Error al guardar: ' + err.message);
    } finally {
        btn.classList.remove('loading');
        btn.innerHTML = '<i class="fas fa-check"></i> Guardar';
    }
}

// ── ELIMINAR (soft delete) ────────────────────────────────
async function eliminar(id){
    if(!confirm('¿Eliminar esta activación?')) return;
    const fd = new FormData();
    fd.append('action','delete');
    fd.append('id', id);
    try {
        const res  = await fetch(API, {method:'POST', body:fd});
        const data = await res.json();
        if(!data.ok) throw new Error(data.msg);
        activaciones = activaciones.filter(x => parseInt(x.id) !== parseInt(id));
        renderTabla();
    } catch(err){
        alert('Error al eliminar: ' + err.message);
    }
}

// ── DETALLE ────────────────────────────────────────────────
function verDetalle(id){
    id = parseInt(id);
    const a = activaciones.find(x => parseInt(x.id) === id); if(!a) return;
    document.getElementById('det-titulo').textContent = a.titulo||'—';
    const bc = `badge-${a.estatus||'programada'}`;
    const bt = (a.estatus||'programada').replace('-',' ').replace(/\b\w/g,c=>c.toUpperCase());
    document.getElementById('det-estatus-badge').className = `badge ${bc}`;
    document.getElementById('det-estatus-badge').textContent = bt;
    document.getElementById('det-tipo-chip').textContent = TIPO_LABELS[a.tipo]||a.tipo||'—';
    document.getElementById('det-fecha').textContent       = a.fecha ? fmtFecha(a.fecha) : '—';
    document.getElementById('det-coordinador').textContent = a.coordinador||'—';
    document.getElementById('det-seccion').textContent     = a.seccion||'—';
    document.getElementById('det-contactos').textContent   = (a.contactos||0).toLocaleString('es-MX');
    document.getElementById('det-asistentes').textContent  = (a.asistentes||0).toLocaleString('es-MX');
    document.getElementById('det-costo').textContent       = a.costo ? '$'+parseFloat(a.costo).toLocaleString('es-MX') : '—';
    document.getElementById('det-notas').textContent       = a.notas||'Sin notas.';
    document.getElementById('det-btn-del').onclick  = () => { cerrarModal('modal-det'); eliminar(id); };
    document.getElementById('det-btn-edit').onclick = () => { cerrarModal('modal-det'); abrirModal(id); };
    document.getElementById('det-mapa-wrap').style.display = (a.lat&&a.lng) ? 'block' : 'none';
    const galeriaWrap = document.getElementById('det-galeria-wrap');
    const galeriaGrid = document.getElementById('det-galeria-grid');
    if(a.fotos && a.fotos.length){
        galeriaWrap.style.display = 'block';
        galeriaGrid.innerHTML = a.fotos.map((src,i) =>
            `<img src="${src}" alt="foto ${i+1}" onclick="abrirLightbox(${JSON.stringify(a.fotos)},${i})">`
        ).join('');
    } else { galeriaWrap.style.display='none'; galeriaGrid.innerHTML=''; }
    document.getElementById('modal-det').classList.add('open');
    if(a.lat && a.lng){
        setTimeout(()=>{
            if(detMapaInst){ detMapaInst.remove(); detMapaInst=null; }
            detMapaInst = L.map('det-mapa').setView([parseFloat(a.lat),parseFloat(a.lng)],14);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'© OpenStreetMap'}).addTo(detMapaInst);
            L.marker([parseFloat(a.lat),parseFloat(a.lng)]).bindPopup(a.titulo).addTo(detMapaInst).openPopup();
        },150);
    }
}

// ── FOTOS ──────────────────────────────────────────────────
function procesarFotos(files){
    Array.from(files).forEach(file=>{
        if(!file.type.startsWith('image/')) return;
        if(file.size > 5*1024*1024){ alert('La foto "'+file.name+'" supera 5 MB.'); return; }
        const reader = new FileReader();
        reader.onload = e => { fotosActuales.push({dataUrl:e.target.result,name:file.name}); renderFotosPreview(); };
        reader.readAsDataURL(file);
    });
    document.getElementById('foto-input').value = '';
}
function renderFotosPreview(){
    const grid    = document.getElementById('fotos-preview');
    const counter = document.getElementById('foto-counter');
    grid.innerHTML = fotosActuales.map((f,i) => `
        <div class="foto-thumb">
            <img src="${f.dataUrl}" alt="${f.name}" onclick="abrirLightbox(fotosActuales.map(x=>x.dataUrl),${i})">
            <button class="remove-foto" onclick="quitarFoto(${i})" type="button"><i class="fas fa-times"></i></button>
        </div>`).join('');
    counter.textContent = fotosActuales.length ? fotosActuales.length+' foto'+(fotosActuales.length!==1?'s':'') : '';
}
function quitarFoto(i){ fotosActuales.splice(i,1); renderFotosPreview(); }
function dragOver(e){ e.preventDefault(); document.getElementById('drop-zone').classList.add('drag-over'); }
function dragLeave(e){ document.getElementById('drop-zone').classList.remove('drag-over'); }
function dropFotos(e){ e.preventDefault(); document.getElementById('drop-zone').classList.remove('drag-over'); procesarFotos(e.dataTransfer.files); }

// ── LIGHTBOX ───────────────────────────────────────────────
function abrirLightbox(urls,idx){ lbFotos=urls; lbIdx=idx; document.getElementById('lb-img').src=lbFotos[lbIdx]; document.getElementById('lb-counter').textContent=(lbIdx+1)+' / '+lbFotos.length; document.getElementById('lightbox').classList.add('open'); }
function cerrarLightbox(){ document.getElementById('lightbox').classList.remove('open'); }
function lbPrev(){ lbIdx=(lbIdx-1+lbFotos.length)%lbFotos.length; document.getElementById('lb-img').src=lbFotos[lbIdx]; document.getElementById('lb-counter').textContent=(lbIdx+1)+' / '+lbFotos.length; }
function lbNext(){ lbIdx=(lbIdx+1)%lbFotos.length; document.getElementById('lb-img').src=lbFotos[lbIdx]; document.getElementById('lb-counter').textContent=(lbIdx+1)+' / '+lbFotos.length; }
document.addEventListener('keydown',e=>{ if(!document.getElementById('lightbox').classList.contains('open'))return; if(e.key==='ArrowLeft')lbPrev(); if(e.key==='ArrowRight')lbNext(); if(e.key==='Escape')cerrarLightbox(); });
document.getElementById('lightbox').addEventListener('click',e=>{ if(e.target===document.getElementById('lightbox'))cerrarLightbox(); });

renderTabla();


// ══════════════════════════════════════════════════════════════
// NAVEGADOR ESTADO / MUNICIPIO
// ══════════════════════════════════════════════════════════════

// Inyectamos el JSON procesado en el Backend PHP
const ESTADOS_MX = <?= json_encode($estados_mx_db, JSON_UNESCAPED_UNICODE) ?: '{}' ?>;

function abrirNavMapa(){
    const selE = document.getElementById('nav-estado');
    // poblar estados si está vacío (el length 1 es por la opcion de "- Selecciona -")
    if(selE.options.length <= 1){
        Object.keys(ESTADOS_MX).sort().forEach(est => {
            const o = document.createElement('option');
            o.value = est; o.textContent = est;
            selE.appendChild(o);
        });
    }
    document.getElementById('modal-nav').classList.add('open');
}

function cerrarNavMapa(){
    document.getElementById('modal-nav').classList.remove('open');
}

function cargarMunicipios(){
    const estado = document.getElementById('nav-estado').value;
    const selM   = document.getElementById('nav-municipio');
    const btnIr  = document.getElementById('btn-nav-ir');
    
    selM.innerHTML = '<option value="">— Selecciona municipio —</option>';
    selM.disabled = true;
    btnIr.disabled = true;
    
    if(!estado) return;
    
    const muns = ESTADOS_MX[estado]?.municipios || {};
    Object.keys(muns).sort().forEach(m => {
        const o = document.createElement('option');
        o.value = m; o.textContent = m;
        selM.appendChild(o);
    });
    
    selM.disabled = false;
    selM.onchange = () => { btnIr.disabled = !selM.value; };
}

function irAlMapa(){
    const estado = document.getElementById('nav-estado').value;
    const mun    = document.getElementById('nav-municipio').value;
    
    if(!estado) return;
    
    const data   = ESTADOS_MX[estado];
    let lat, lng;
    
    if(mun && data.municipios[mun]){
        lat = data.municipios[mun].lat;
        lng = data.municipios[mun].lng;
    } else {
        lat = data.lat; lng = data.lng;
    }
    
    cerrarNavMapa();
    
    // centrar el mapa del formulario
    if(typeof mapaInst !== 'undefined' && mapaInst){
        mapaInst.setView([lat, lng], mun ? 12 : 9);
        
        // Opcional: Autocolocar el marcador central si el usuario quiere auto-setearlo
        if(marcador) mapaInst.removeLayer(marcador);
        marcador = L.marker([lat,lng]).addTo(mapaInst);
        document.getElementById('f-lat').value = lat.toFixed(6);
        document.getElementById('f-lng').value = lng.toFixed(6);
    }
}

document.getElementById('modal-nav').addEventListener('click', e => {
    if(e.target === document.getElementById('modal-nav')) cerrarNavMapa();
});

</script>
</body>
</html>