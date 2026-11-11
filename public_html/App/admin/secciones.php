<?php
// ── Seguridad ──────────────────────────────────────────────
if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
} else { session_start(); }

$rol = $_SESSION['user_role'] ?? '';
$permitidos = ['admin','administrador','superadmin','super admin'];
if (!in_array($rol, $permitidos)) { header('Location: /public/index.php'); exit(); }

// ── Conexión y carga de datos ──────────────────────────────
$secciones = [];
$estados = [];
$municipios = [];
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

    // Cargar estados únicos
    $stmtEstados = $pdo->query("SELECT DISTINCT estado FROM secciones_electorales WHERE activo = 1 ORDER BY estado");
    $estados = $stmtEstados->fetchAll(PDO::FETCH_COLUMN);

    // Cargar secciones
    $stmt = $pdo->query("
        SELECT s.id, s.nombre_seccion, s.estado, s.municipio,
               s.distrito_federal, s.distrito_local,
               s.lista_nominal, s.meta_contactos, s.contactos_conseguidos,
               s.eventos_realizados, s.metas_votos,
               s.region_asignada, s.zona_asignada,
               s.principales_necesidades, s.enfoque, s.perfil_socioeconomico,
               s.activaciones_totales, s.con_candidato, s.sin_candidato,
               s.grupo_edad_18_24, s.grupo_edad_25_34, s.grupo_edad_35_44,
               s.grupo_edad_45_54, s.grupo_edad_55_mas
        FROM secciones_electorales s
        WHERE s.activo = 1
        ORDER BY s.nombre_seccion ASC
    ");
    $secciones = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_db = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secciones - GreenBoard Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    :root {
        --primary: #2d7a3e;
        --primary-dark: #1f5a2d;
        --dark: #1a1a1a;
        --light: #f0f7f0;
        --white: #ffffff;
        --gray-100: #f8f9fa;
        --gray-200: #e9ecef;
        --text-muted: #6c757d;
        --shadow-sm: 0 10px 30px rgba(0,0,0,0.05);
        --sidebar-width: 280px;
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Arial', sans-serif; background: var(--light); color: var(--dark); overflow-x: hidden; }

    /* ── LAYOUT ── */
    .container {
        max-width: 100%; margin: 0; background: var(--white);
        min-height: 100vh; position: relative;
        padding-left: 330px; padding-right: 50px;
        padding-bottom: 40px; transition: padding-left 0.3s ease;
    }
    .design-text {
        position: fixed; left: 290px; top: 50%; writing-mode: vertical-rl;
        font-size: 13px; font-weight: 500; color: var(--primary);
        opacity: 0.3; letter-spacing: 2px; z-index: 0; pointer-events: none;
    }

    /* ── PAGE HEADER ── */
    .page-header {
        padding: 50px 0 30px 0; position: relative; z-index: 2;
        display: flex; justify-content: space-between; align-items: flex-end;
        flex-wrap: wrap; gap: 20px;
    }
    .ai-badge {
        color: var(--primary); font-size: 13px; font-weight: 800; letter-spacing: 4px;
        margin-bottom: 15px; display: inline-block; text-transform: uppercase;
        background: rgba(45,122,62,0.1); padding: 5px 15px; border-radius: 20px;
    }
    .main-title { font-size: 54px; font-weight: 900; line-height: 1; margin-bottom: 15px; color: var(--dark); }
    .description { color: #666; font-size: 16px; line-height: 1.6; max-width: 600px; }
    .header-actions { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }

    /* ── BUTTONS ── */
    .btn-primary-action {
        background: var(--primary); color: white; border: none;
        padding: 12px 25px; border-radius: 12px; font-weight: 700;
        cursor: pointer; transition: all 0.3s;
        display: flex; align-items: center; gap: 10px; font-size: 14px;
        box-shadow: 0 4px 15px rgba(45,122,62,0.3); text-decoration: none;
    }
    .btn-primary-action:hover { background: var(--primary-dark); transform: translateY(-2px); }
    .btn-secondary {
        background: var(--white); color: var(--primary); border: 2px solid var(--primary);
        padding: 10px 20px; border-radius: 12px; font-weight: 700; cursor: pointer;
        transition: all 0.3s; display: flex; align-items: center; gap: 8px; font-size: 14px;
    }
    .btn-secondary:hover { background: var(--light); }

    /* ── FILTERS BAR ── */
    .filters-bar {
        background: var(--white); border: 1px solid var(--gray-200);
        border-radius: 16px; padding: 20px; margin-bottom: 30px;
        display: flex; gap: 15px; align-items: flex-end;
        box-shadow: var(--shadow-sm); flex-wrap: wrap;
    }
    .filter-group { display: flex; flex-direction: column; gap: 8px; flex: 1; min-width: 160px; }
    .form-label { font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.5px; }
    .form-input-styled {
        width: 100%; padding: 12px 15px; border-radius: 10px;
        border: 2px solid var(--gray-200); background: var(--gray-100);
        font-size: 14px; font-weight: 600; color: var(--dark);
        transition: all 0.3s; outline: none; appearance: none; font-family: Arial, sans-serif;
        cursor: pointer;
    }
    .form-input-styled:focus { border-color: var(--primary); background: var(--white); }

    /* ── STATS ROW ── */
    .stats-row { display: grid; grid-template-columns: repeat(3,1fr); gap: 20px; margin-bottom: 30px; }
    .stat-card {
        background: var(--white); border: 1px solid var(--gray-200);
        border-radius: 20px; padding: 25px 30px; box-shadow: var(--shadow-sm);
        display: flex; align-items: center; gap: 18px;
    }
    .stat-icon { width: 52px; height: 52px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0; }
    .stat-icon.verde   { background: rgba(45,122,62,.1); color: var(--primary); }
    .stat-icon.amarillo{ background: #fffbeb; color: #d97706; }
    .stat-icon.azul    { background: #eff6ff; color: #2196f3; }
    .stat-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); margin-bottom: 5px; }
    .stat-val { font-size: 30px; font-weight: 900; color: var(--dark); line-height: 1; }

    /* ── TABLE ── */
    .table-section {
        background: var(--white); border-radius: 20px; padding: 30px;
        border: 1px solid var(--gray-200); box-shadow: var(--shadow-sm);
    }
    .section-header { margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; }
    .section-title { font-size: 20px; font-weight: 800; color: var(--dark); }
    .modern-table { width: 100%; border-collapse: separate; border-spacing: 0; }
    .modern-table th {
        text-align: left; padding: 15px 20px;
        color: var(--text-muted); font-size: 12px; text-transform: uppercase;
        letter-spacing: 1px; border-bottom: 2px solid var(--gray-200);
        white-space: nowrap;
    }
    .modern-table td {
        padding: 16px 20px; border-bottom: 1px solid var(--gray-200);
        font-size: 14px; color: var(--dark); font-weight: 500;
        vertical-align: middle; transition: background 0.2s;
    }
    .modern-table tr:hover td { background: var(--gray-100); }
    .modern-table tr:last-child td { border-bottom: none; }

    /* ── PAGINACIÓN ── */
    .pagination-container {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 6px;
        margin-top: 25px;
        padding-top: 15px;
        border-top: 1px solid var(--gray-200);
    }
    .btn-page {
        background: var(--white);
        border: 1px solid var(--gray-200);
        color: var(--dark);
        border-radius: 8px;
        min-width: 36px;
        height: 36px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }
    .btn-page:hover:not(:disabled) {
        border-color: var(--primary);
        color: var(--primary);
        background: var(--light);
    }
    .btn-page.active {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
    }
    .btn-page:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    .pagination-ellipsis {
        color: var(--text-muted);
        font-weight: 600;
        padding: 0 5px;
    }

    /* ── PROGRESS BAR ── */
    .progress-wrap { min-width: 100px; }
    .progress-bar-bg {
        height: 8px; border-radius: 4px; background: var(--gray-200);
        overflow: hidden; margin-top: 4px;
    }
    .progress-bar-fill {
        height: 100%; border-radius: 4px;
        background: linear-gradient(90deg, var(--primary), #4caf50);
        transition: width 0.5s ease;
    }
    .progress-label { font-size: 11px; color: var(--text-muted); font-weight: 600; }

    .stat-cell { font-size: 16px; font-weight: 800; color: var(--primary); }
    .stat-cell.neutral { color: var(--dark); }
    .stat-cell-label { font-size: 10px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.5px; }

    /* ── ACTION BUTTONS ── */
    .btn-detalle {
        background: rgba(45,122,62,0.1); color: var(--primary);
        border: none; padding: 8px 18px; border-radius: 8px;
        font-size: 13px; font-weight: 700; cursor: pointer;
        transition: all 0.2s; white-space: nowrap;
    }
    .btn-detalle:hover { background: var(--primary); color: white; }

    .empty-row td { text-align: center; padding: 60px 20px; color: var(--text-muted); font-size: 14px; }

    /* ── EDAD CHIPS ── */
    .edad-group { display: flex; gap: 4px; flex-wrap: wrap; }
    .edad-chip {
        font-size: 10px; font-weight: 700; padding: 2px 7px;
        border-radius: 6px; background: var(--gray-200); color: var(--text-muted);
        white-space: nowrap;
    }

    /* ══════════════════════════════════════════════════════
        MODAL DE DETALLES
    ══════════════════════════════════════════════════════ */
    .modal {
        display: none; position: fixed; top: 0; left: 0;
        width: 100%; height: 100%;
        background: rgba(255,255,255,0.9);
        backdrop-filter: blur(5px);
        z-index: 2000; align-items: center; justify-content: center;
        opacity: 0; transition: opacity 0.3s ease;
    }
    .modal.open { display: flex; opacity: 1; }
    .modal-content {
        background: var(--white); border-radius: 20px;
        border: 1px solid var(--gray-200);
        box-shadow: 0 25px 50px rgba(0,0,0,0.1);
        width: 90%; max-width: 760px; padding: 40px;
        position: relative; transform: translateY(30px); opacity: 0;
        transition: all 0.4s ease-out; max-height: 90vh; overflow-y: auto;
    }
    .modal.open .modal-content { transform: translateY(0); opacity: 1; }
    .modal-close {
        position: absolute; top: 20px; right: 20px;
        background: var(--gray-100); border: none; cursor: pointer;
        width: 36px; height: 36px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 16px; color: var(--text-muted); transition: all 0.2s;
    }
    .modal-close:hover { background: #ffeaea; color: #d32f2f; }
    .modal-title { font-size: 26px; font-weight: 900; margin-bottom: 6px; color: var(--dark); }
    .modal-subtitle { font-size: 13px; color: var(--text-muted); margin-bottom: 30px; }

    /* Detail grid */
    .det-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px; }
    .det-item label {
        font-size: 11px; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.5px; color: var(--text-muted); display: block; margin-bottom: 4px;
    }
    .det-item span { font-size: 15px; font-weight: 700; color: var(--dark); }
    .det-item.full { grid-column: 1 / -1; }

    /* Edad table in modal */
    .edad-table { width: 100%; border-collapse: separate; border-spacing: 0; }
    .edad-table th { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); padding: 8px 12px; border-bottom: 2px solid var(--gray-200); }
    .edad-table td { font-size: 14px; font-weight: 700; padding: 10px 12px; border-bottom: 1px solid var(--gray-200); }

    .det-section-title {
        font-size: 13px; font-weight: 800; text-transform: uppercase;
        letter-spacing: 1px; color: var(--primary); margin: 20px 0 12px;
        padding-bottom: 6px; border-bottom: 2px solid var(--light);
    }

    .field-editable {
        background: var(--gray-100); border-radius: 10px; padding: 14px 16px;
        font-size: 14px; color: var(--dark); min-height: 60px; line-height: 1.5;
        position: relative;
    }
    .field-editable .edit-btn {
        position: absolute; top: 8px; right: 8px;
        background: var(--primary); color: white; border: none;
        border-radius: 6px; padding: 4px 10px; font-size: 11px;
        font-weight: 700; cursor: pointer; opacity: 0; transition: opacity 0.2s;
    }
    .field-editable:hover .edit-btn { opacity: 1; }

    /* Activaciones stats in modal */
    .act-stats { display: grid; grid-template-columns: repeat(3,1fr); gap: 12px; }
    .act-stat-card {
        background: var(--gray-100); border-radius: 12px; padding: 16px;
        text-align: center;
    }
    .act-stat-num { font-size: 28px; font-weight: 900; color: var(--primary); }
    .act-stat-lbl { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); margin-top: 2px; }

    /* Progress in modal */
    .modal-progress-wrap { margin: 10px 0; }
    .modal-progress-bg {
        height: 12px; border-radius: 6px; background: var(--gray-200); overflow: hidden;
    }
    .modal-progress-fill {
        height: 100%; border-radius: 6px;
        background: linear-gradient(90deg, var(--primary), #4caf50);
        transition: width 0.6s ease;
    }
    .modal-progress-label { display: flex; justify-content: space-between; font-size: 12px; font-weight: 700; color: var(--text-muted); margin-top: 4px; }

    /* lista nominal table */
    .nominal-table-wrap { overflow-x: auto; }

    /* ── INLINE EDIT ── */
    .inline-textarea {
        width: 100%; border: 2px solid var(--primary); border-radius: 8px;
        padding: 10px 12px; font-size: 14px; font-family: Arial, sans-serif;
        resize: vertical; min-height: 80px; outline: none; background: white;
    }
    .save-inline-btn {
        margin-top: 6px; background: var(--primary); color: white;
        border: none; border-radius: 8px; padding: 6px 16px; font-size: 13px;
        font-weight: 700; cursor: pointer;
    }
    .cancel-inline-btn {
        margin-top: 6px; margin-left: 6px; background: var(--gray-200);
        color: var(--dark); border: none; border-radius: 8px;
        padding: 6px 14px; font-size: 13px; font-weight: 700; cursor: pointer;
    }

    /* ── RESPONSIVE ── */
    @media (max-width: 768px) {
        .container { padding-left: 15px; padding-right: 15px; }
        .main-title { font-size: 36px; }
        .stats-row { grid-template-columns: 1fr; }
        .det-grid { grid-template-columns: 1fr; }
        .act-stats { grid-template-columns: 1fr 1fr; }
    }

    /* ── SEARCHABLE DROPDOWN SECCIÓN ── */
    .sec-dropdown-wrap {
        position: relative;
        width: 100%;
    }
    .sec-dropdown-input {
        width: 100%; padding: 12px 38px 12px 15px;
        border-radius: 10px; border: 2px solid var(--gray-200);
        background: var(--gray-100); font-size: 14px; font-weight: 600;
        color: var(--dark); transition: all 0.3s; outline: none;
        font-family: Arial, sans-serif; cursor: pointer;
        box-sizing: border-box;
    }
    .sec-dropdown-input:focus { border-color: var(--primary); background: var(--white); }
    .sec-dropdown-input::placeholder { color: #aaa; font-weight: 400; }
    .sec-dropdown-arrow {
        position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
        pointer-events: none; color: var(--text-muted); font-size: 12px;
        transition: transform 0.2s;
    }
    .sec-dropdown-wrap.open .sec-dropdown-arrow { transform: translateY(-50%) rotate(180deg); }
    .sec-dropdown-list {
        display: none; position: absolute; top: calc(100% + 4px); left: 0; right: 0;
        background: var(--white); border: 2px solid var(--primary);
        border-radius: 10px; z-index: 9999; max-height: 220px;
        overflow: hidden; box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        flex-direction: column;
    }
    .sec-dropdown-wrap.open .sec-dropdown-list { display: flex; }
    .sec-dropdown-search {
        padding: 10px 12px; border: none; border-bottom: 1px solid var(--gray-200);
        font-size: 13px; outline: none; font-family: Arial, sans-serif;
        color: var(--dark); background: var(--gray-100); flex-shrink: 0;
    }
    .sec-dropdown-search:focus { background: var(--white); }
    .sec-dropdown-options {
        overflow-y: auto; flex: 1;
    }
    .sec-dropdown-option {
        padding: 10px 14px; font-size: 13px; font-weight: 600;
        cursor: pointer; color: var(--dark); transition: background 0.15s;
        font-family: Arial, sans-serif;
    }
    .sec-dropdown-option:hover,
    .sec-dropdown-option.focused { background: var(--light); color: var(--primary); }
    .sec-dropdown-option.selected { background: rgba(45,122,62,0.1); color: var(--primary); }
    .sec-dropdown-empty {
        padding: 14px; text-align: center; font-size: 13px;
        color: var(--text-muted); font-style: italic;
    }
    .sec-dropdown-loading {
        padding: 14px; text-align: center; font-size: 13px; color: var(--text-muted);
    }
    </style>
</head>
<body>

<?php if (file_exists(__DIR__ . '/../../includes/components/sidebar_admin.php')) include __DIR__ . '/../../includes/components/sidebar_admin.php'; ?>

<div class="container" id="mainContainer">
    <div class="design-text">SECCIONES ELECTORALES</div>

    <div class="page-header">
        <div>
            <div class="ai-badge"><i class="fas fa-layer-group"></i> &nbsp; Secciones</div>
            <h1 class="main-title">Secciones</h1>
            <p class="description">Gestión y seguimiento por sección electoral. Filtros por Estado y Municipio.</p>
        </div>
    </div>

    <div class="filters-bar">
        <div class="filter-group">
            <label class="form-label">Estado</label>
            <select class="form-input-styled" id="filtroEstado" onchange="filtrarMunicipios()">
                <option value="">Todos los estados</option>
                </select>
        </div>
        <div class="filter-group">
            <label class="form-label">Municipio</label>
            <select class="form-input-styled" id="filtroMunicipio" onchange="resetPaginationAndRender()">
                <option value="">Todos los municipios</option>
            </select>
        </div>
        <div class="filter-group">
            <label class="form-label">Buscar</label>
            <input type="text" class="form-input-styled" id="filtroBuscar" placeholder="Nombre de sección…" oninput="resetPaginationAndRender()">
        </div>
        <button class="btn-secondary" onclick="limpiarFiltros()">
            <i class="fas fa-times"></i> Limpiar
        </button>
    </div>
    
        <div class="stats-row" id="statsRow">
        <div class="stat-card">
            <div class="stat-icon verde"><i class="fas fa-layer-group"></i></div>
            <div>
                <div class="stat-label">Secciones Totales</div>
                <div class="stat-val" id="stat-total">—</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon azul"><i class="fas fa-bolt"></i></div>
            <div>
                <div class="stat-label">Activaciones Totales</div>
                <div class="stat-val" id="stat-activaciones">—</div>
            </div>
        </div>
    </div>

    <div class="table-section">
        <div class="section-header">
            <div class="section-title">Tabla de Secciones</div>
            <div id="tableCount" style="font-size:13px; color:var(--text-muted); font-weight:600;"></div>
        </div>
        <div style="overflow-x:auto;">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Nombre de la Sección</th>
                        <th>Lista Nominal (tabla)</th>
                        <th>Grupos de Edad (tabla)</th>
                        <th>Meta de Contactos</th>
                        <th>Barra de Avance</th>
                        <th>Eventos Realizados</th>
                        <th>Metas de Votos</th>
                        <th>Detalles</th>
                    </tr>
                </thead>
                <tbody id="tablaBody">
                    <tr class="empty-row"><td colspan="9"><i class="fas fa-spinner fa-spin"></i> Cargando…</td></tr>
                </tbody>
            </table>
        </div>
        <div id="paginacion" class="pagination-container"></div>
    </div>
</div>

<div class="modal" id="modal-det">
    <div class="modal-content">
        <button class="modal-close" onclick="cerrarModal('modal-det')"><i class="fas fa-times"></i></button>
        <h2 class="modal-title" id="det-titulo">—</h2>
        <div class="modal-subtitle" id="det-ubicacion">—</div>

        <div class="det-grid">
            <div class="det-item">
                <label>Sección</label>
                <span id="det-seccion">—</span>
            </div>
            <div class="det-item">
                <label>Municipio</label>
                <span id="det-municipio">—</span>
            </div>
            <div class="det-item">
                <label>Distrito Federal</label>
                <span id="det-df">—</span>
            </div>
            <div class="det-item">
                <label>Distrito Local</label>
                <span id="det-dl">—</span>
            </div>
            <div class="det-item">
                <label>Región Asignada</label>
                <span id="det-region">—</span>
            </div>
            <div class="det-item">
                <label>Zona Asignada</label>
                <span id="det-zona">—</span>
            </div>
        </div>

        <div class="det-section-title"><i class="fas fa-users"></i> Lista Nominal</div>
        <div class="nominal-table-wrap">
            <table class="edad-table" id="det-tabla-nominal">
                <thead>
                    <tr>
                        <th>Total</th>
                        <th>18–24</th>
                        <th>25–34</th>
                        <th>35–44</th>
                        <th>45–54</th>
                        <th>55+</th>
                    </tr>
                </thead>
                <tbody id="det-nominal-body">
                    <tr><td colspan="6" style="text-align:center; color:var(--text-muted);">—</td></tr>
                </tbody>
            </table>
        </div>

        <div class="det-section-title"><i class="fas fa-address-book"></i> Contactos y Avance</div>
        <div class="det-grid">
            <div class="det-item">
                <label>Meta de Contactos</label>
                <span id="det-meta-contactos">—</span>
            </div>
        </div>
        <div class="modal-progress-wrap">
            <div class="modal-progress-bg">
                <div class="modal-progress-fill" id="det-progress-fill" style="width:0%"></div>
            </div>
            <div class="modal-progress-label">
                <span id="det-progress-pct">0%</span>
                <span id="det-progress-rem">0 restantes</span>
            </div>
        </div>
        <div class="det-grid" style="margin-top:16px;">
            <div class="det-item">
                <label>Eventos Realizados</label>
                <span id="det-eventos">—</span>
            </div>
            <div class="det-item">
                <label>Metas de Votos</label>
                <span id="det-votos">—</span>
            </div>
        </div>

        <div class="det-section-title"><i class="fas fa-edit"></i> Campos Abiertos</div>
        <div style="margin-bottom:14px;">
            <label class="form-label">Principales Necesidades</label>
            <div class="field-editable" id="det-necesidades">
                <span class="content-text">—</span>
                <button class="edit-btn" onclick="activarEdicion('necesidades','det-necesidades')">Editar</button>
            </div>
        </div>
        <div style="margin-bottom:14px;">
            <label class="form-label">Enfoque</label>
            <div class="field-editable" id="det-enfoque">
                <span class="content-text">—</span>
                <button class="edit-btn" onclick="activarEdicion('enfoque','det-enfoque')">Editar</button>
            </div>
        </div>
        <div style="margin-bottom:14px;">
            <label class="form-label">Perfil Socioeconómico</label>
            <div class="field-editable" id="det-perfil">
                <span class="content-text">—</span>
                <button class="edit-btn" onclick="activarEdicion('perfil_socioeconomico','det-perfil')">Editar</button>
            </div>
        </div>

        <div class="det-section-title"><i class="fas fa-bolt"></i> Activaciones Totales / Eventos</div>
        <div class="act-stats">
            <div class="act-stat-card">
                <div class="act-stat-num" id="det-act-total">—</div>
                <div class="act-stat-lbl">Total</div>
            </div>
            <div class="act-stat-card">
                <div class="act-stat-num" id="det-act-con">—</div>
                <div class="act-stat-lbl">Con Candidato</div>
            </div>
            <div class="act-stat-card">
                <div class="act-stat-num" id="det-act-sin">—</div>
                <div class="act-stat-lbl">Sin Candidato</div>
            </div>
        </div>

        <div style="display:flex; gap:10px; margin-top:30px; flex-wrap:wrap;">
            <button class="btn-primary-action" id="det-btn-edit" onclick="">
                <i class="fas fa-pen"></i> Editar Sección
            </button>
            <button class="btn-secondary" onclick="cerrarModal('modal-det')">
                <i class="fas fa-times"></i> Cerrar
            </button>
        </div>
    </div>
</div>

<div class="modal" id="modal-form">
    <div class="modal-content">
        <button class="modal-close" onclick="cerrarModal('modal-form')"><i class="fas fa-times"></i></button>
        <h2 class="modal-title" id="form-titulo">Nueva Sección</h2>
        <div class="modal-subtitle">Complete los datos de la sección electoral</div>

        <input type="hidden" id="form-id">

        <div class="det-section-title"><i class="fas fa-map-marker-alt"></i> Ubicación</div>
        <div class="det-grid">
            <div class="det-item" style="grid-column: 1 / -1;">
                <label class="form-label">Sección Electoral *
                    <span id="f-sec-contador" style="font-weight:400; color:var(--text-muted); margin-left:6px;"></span>
                </label>
                <div class="sec-dropdown-wrap" id="sec-dropdown-wrap">
                    <input type="hidden" id="f-nombre">
                    <input type="text" class="sec-dropdown-input" id="sec-display"
                           placeholder="— Selecciona estado y municipio primero —"
                           readonly onclick="secToggle()" autocomplete="off">
                    <i class="fas fa-chevron-down sec-dropdown-arrow"></i>
                    <div class="sec-dropdown-list" id="sec-dropdown-list">
                        <input type="text" class="sec-dropdown-search" id="sec-search"
                               placeholder="🔍  Buscar sección…"
                               oninput="secFiltrar(this.value)"
                               onkeydown="secNavegar(event)">
                        <div class="sec-dropdown-options" id="sec-options">
                            <div class="sec-dropdown-empty">Selecciona estado y municipio primero</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="det-item">
                <label class="form-label">Estado *</label>
                <select class="form-input-styled" id="f-estado" onchange="geoCargarMunicipios()">
                    <option value="">— Cargando estados… —</option>
                </select>
            </div>
            <div class="det-item">
                <label class="form-label">Municipio *</label>
                <select class="form-input-styled" id="f-municipio" onchange="geoCargarSecciones()">
                    <option value="">— Selecciona estado primero —</option>
                </select>
            </div>
            <div class="det-item">
                <label class="form-label">Distrito Federal</label>
                <input type="text" class="form-input-styled" id="f-df" placeholder="Ej: 06">
            </div>
            <div class="det-item">
                <label class="form-label">Distrito Local</label>
                <input type="text" class="form-input-styled" id="f-dl" placeholder="Ej: 15">
            </div>
            <div class="det-item">
                <label class="form-label">Región Asignada</label>
                <input type="text" class="form-input-styled" id="f-region" placeholder="Ej: Norte">
            </div>
            <div class="det-item">
                <label class="form-label">Zona Asignada</label>
                <input type="text" class="form-input-styled" id="f-zona" placeholder="Ej: Z-04">
            </div>
            <div class="det-item">
                <label class="form-label">Lista Nominal</label>
                <input type="number" class="form-input-styled" id="f-nominal" placeholder="0">
            </div>
        </div>

        <div class="det-section-title"><i class="fas fa-users"></i> Grupos de Edad</div>
        <div class="det-grid">
            <div class="det-item"><label class="form-label">18–24</label><input type="number" class="form-input-styled" id="f-edad-18" placeholder="0"></div>
            <div class="det-item"><label class="form-label">25–34</label><input type="number" class="form-input-styled" id="f-edad-25" placeholder="0"></div>
            <div class="det-item"><label class="form-label">35–44</label><input type="number" class="form-input-styled" id="f-edad-35" placeholder="0"></div>
            <div class="det-item"><label class="form-label">45–54</label><input type="number" class="form-input-styled" id="f-edad-45" placeholder="0"></div>
            <div class="det-item"><label class="form-label">55+</label><input type="number" class="form-input-styled" id="f-edad-55" placeholder="0"></div>
        </div>

        <div class="det-section-title"><i class="fas fa-bullseye"></i> Metas y Resultados</div>
        <div class="det-grid">
            <div class="det-item"><label class="form-label">Meta de Contactos</label><input type="number" class="form-input-styled" id="f-meta-cont" placeholder="0"></div>
            <div class="det-item"><label class="form-label">Contactos Conseguidos</label><input type="number" class="form-input-styled" id="f-cont" placeholder="0"></div>
            <div class="det-item"><label class="form-label">Eventos Realizados</label><input type="number" class="form-input-styled" id="f-eventos" placeholder="0"></div>
            <div class="det-item"><label class="form-label">Metas de Votos</label><input type="number" class="form-input-styled" id="f-votos" placeholder="0"></div>
            <div class="det-item"><label class="form-label">Activaciones Totales</label><input type="number" class="form-input-styled" id="f-act-total" placeholder="0"></div>
            <div class="det-item"><label class="form-label">Con Candidato</label><input type="number" class="form-input-styled" id="f-act-con" placeholder="0"></div>
            <div class="det-item"><label class="form-label">Sin Candidato</label><input type="number" class="form-input-styled" id="f-act-sin" placeholder="0"></div>
        </div>

        <div class="det-section-title"><i class="fas fa-align-left"></i> Campos Abiertos</div>
        <div style="margin-bottom:14px;">
            <label class="form-label">Principales Necesidades</label>
            <textarea class="form-input-styled" id="f-necesidades" rows="3" placeholder="Describe las principales necesidades de esta sección…"></textarea>
        </div>
        <div style="margin-bottom:14px;">
            <label class="form-label">Enfoque</label>
            <textarea class="form-input-styled" id="f-enfoque" rows="3" placeholder="Enfoque estratégico para esta sección…"></textarea>
        </div>
        <div style="margin-bottom:14px;">
            <label class="form-label">Perfil Socioeconómico</label>
            <textarea class="form-input-styled" id="f-perfil" rows="3" placeholder="Describe el perfil socioeconómico…"></textarea>
        </div>

        <div style="display:flex; gap:10px; margin-top:24px; flex-wrap:wrap;">
            <button class="btn-primary-action" id="form-save-btn" onclick="guardarSeccion()">
                <i class="fas fa-save"></i> Guardar
            </button>
            <button class="btn-secondary" onclick="cerrarModal('modal-form')">
                <i class="fas fa-times"></i> Cancelar
            </button>
        </div>
    </div>
</div>

<script>
// ══════════════════════════════════════════════════════════
//  DATA desde PHP
// ══════════════════════════════════════════════════════════
const API = '/api/secciones/secciones_api.php';
let secciones = <?php echo json_encode(array_values($secciones)); ?>;
let seccionActualId = null;

// Variables Paginación
let currentPage = 1;
const itemsPerPage = 50;

// ══════════════════════════════════════════════════════════
//  HELPERS
// ══════════════════════════════════════════════════════════
function fmt(n){ return n != null ? parseInt(n).toLocaleString('es-MX') : '—'; }
function pct(conseguidos, meta){
    if(!meta || meta == 0) return 0;
    return Math.min(100, Math.round((conseguidos / meta) * 100));
}

// ══════════════════════════════════════════════════════════
//  FILTROS
// ══════════════════════════════════════════════════════════
function filtrarMunicipios(){
    const estadoLabel = document.getElementById('filtroEstado').value;
    const sel = document.getElementById('filtroMunicipio');

    if (!estadoLabel) {
        // Sin estado: todos los municipios únicos de secciones_electorales
        const municipios = [...new Set(
            secciones.map(s => s.municipio).filter(Boolean)
        )].sort();
        sel.innerHTML = '<option value="">Todos los municipios</option>' +
            municipios.map(m => `<option value="${m}">${m}</option>`).join('');
    } else {
        // Con estado: usar GEO_CACHE (viene de la API → tabla municipios del INE)
        const entry = Object.values(GEO_CACHE).find(e => e.label === estadoLabel);
        if (entry && entry.municipios && entry.municipios.length) {
            sel.innerHTML = '<option value="">Todos los municipios</option>' +
                entry.municipios.map(m => `<option value="${m}">${m}</option>`).join('');
        } else {
            // Fallback: municipios que existan en secciones_electorales para ese estado
            const municipios = [...new Set(
                secciones
                    .filter(s => s.estado === estadoLabel)
                    .map(s => s.municipio)
                    .filter(Boolean)
            )].sort();
            sel.innerHTML = '<option value="">Todos los municipios</option>' +
                municipios.map(m => `<option value="${m}">${m}</option>`).join('');
        }
    }
    resetPaginationAndRender();
}

function limpiarFiltros(){
    document.getElementById('filtroEstado').value = '';
    document.getElementById('filtroMunicipio').value = '';
    document.getElementById('filtroBuscar').value = '';
    filtrarMunicipios();
}

function resetPaginationAndRender() {
    currentPage = 1;
    renderTabla();
}

function seccionesFiltradas(){
    const estado = document.getElementById('filtroEstado').value.toLowerCase();
    const muni   = document.getElementById('filtroMunicipio').value.toLowerCase();
    const buscar = document.getElementById('filtroBuscar').value.toLowerCase();
    return secciones.filter(s => {
        const matchE = !estado || (s.estado||'').toLowerCase() === estado;
        const matchM = !muni   || (s.municipio||'').toLowerCase() === muni;
        const matchB = !buscar || (s.nombre_seccion||'').toLowerCase().includes(buscar);
        return matchE && matchM && matchB;
    });
}

// ══════════════════════════════════════════════════════════
//  RENDER TABLA & PAGINACIÓN
// ══════════════════════════════════════════════════════════
function renderTabla(){
    const data = seccionesFiltradas();
    const tbody = document.getElementById('tablaBody');
    document.getElementById('tableCount').textContent = data.length + ' sección(es)';

    // Stats de la info filtrada (no solo la página actual)
    const totalContactos   = data.reduce((a,s) => a + (parseInt(s.contactos_conseguidos)||0), 0);
    const totalActivaciones= data.reduce((a,s) => a + (parseInt(s.activaciones_totales)||0), 0);
    document.getElementById('stat-total').textContent       = data.length;
    if (document.getElementById('stat-contactos')) {
        document.getElementById('stat-contactos').textContent = fmt(totalContactos);
    }
    document.getElementById('stat-activaciones').textContent= fmt(totalActivaciones);

    // Lógica de Paginación
    const totalPages = Math.ceil(data.length / itemsPerPage);
    if (currentPage > totalPages) currentPage = totalPages || 1;

    const startIndex = (currentPage - 1) * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;
    const paginatedData = data.slice(startIndex, endIndex);

    renderPaginacion(totalPages);

    if(!paginatedData.length){
        tbody.innerHTML = '<tr class="empty-row"><td colspan="9"><i class="fas fa-search"></i> Sin resultados</td></tr>';
        return;
    }

    tbody.innerHTML = paginatedData.map(s => {
        const meta = parseInt(s.meta_contactos)||0;
        const cons = parseInt(s.contactos_conseguidos)||0;
        const p    = pct(cons, meta);
        const edadChips = [
            ['18-24', s.grupo_edad_18_24],
            ['25-34', s.grupo_edad_25_34],
            ['35-44', s.grupo_edad_35_44],
            ['45-54', s.grupo_edad_45_54],
            ['55+',   s.grupo_edad_55_mas],
        ].filter(([,v]) => v > 0)
         .map(([l,v]) => `<span class="edad-chip">${l}: ${fmt(v)}</span>`).join('');

        return `<tr>
            <td><strong>${s.nombre_seccion||'—'}</strong></td>
            <td>${fmt(s.lista_nominal)}</td>
            <td>
                <div class="edad-group">
                    ${edadChips || '<span style="color:var(--text-muted);font-size:12px;">—</span>'}
                </div>
            </td>
            <td>${fmt(s.meta_contactos)}</td>
            <td class="stat-cell">${fmt(s.contactos_conseguidos)}</td>
            <td>
                <div class="progress-wrap">
                    <div class="progress-label">${p}%</div>
                    <div class="progress-bar-bg">
                        <div class="progress-bar-fill" style="width:${p}%"></div>
                    </div>
                </div>
            </td>
            <td>${fmt(s.eventos_realizados)}</td>
            <td class="stat-cell">${fmt(s.metas_votos)}</td>
            <td>
                <button class="btn-detalle" onclick="verDetalle(${s.id})">
                    <i class="fas fa-eye"></i> Detalles
                </button>
            </td>
        </tr>`;
    }).join('');
}

function renderPaginacion(totalPages) {
    const container = document.getElementById('paginacion');
    if (totalPages <= 1) {
        container.innerHTML = '';
        return;
    }

    let html = '';

    // Botón Anterior
    html += `<button class="btn-page" ${currentPage === 1 ? 'disabled' : `onclick="cambiarPagina(${currentPage - 1})"`}>
                <i class="fas fa-chevron-left"></i>
             </button>`;

    // Logica para no mostrar cientos de botones, mostrando un rango +/- 2 de la actual
    let startPage = Math.max(1, currentPage - 2);
    let endPage = Math.min(totalPages, currentPage + 2);

    if (startPage > 1) {
        html += `<button class="btn-page" onclick="cambiarPagina(1)">1</button>`;
        if (startPage > 2) html += `<span class="pagination-ellipsis">...</span>`;
    }

    for (let i = startPage; i <= endPage; i++) {
        html += `<button class="btn-page ${i === currentPage ? 'active' : ''}" onclick="cambiarPagina(${i})">${i}</button>`;
    }

    if (endPage < totalPages) {
        if (endPage < totalPages - 1) html += `<span class="pagination-ellipsis">...</span>`;
        html += `<button class="btn-page" onclick="cambiarPagina(${totalPages})">${totalPages}</button>`;
    }

    // Botón Siguiente
    html += `<button class="btn-page" ${currentPage === totalPages ? 'disabled' : `onclick="cambiarPagina(${currentPage + 1})"`}>
                <i class="fas fa-chevron-right"></i>
             </button>`;

    container.innerHTML = html;
}

function cambiarPagina(page) {
    currentPage = page;
    renderTabla();
    // Opcional: Volver al inicio de la tabla al cambiar de página
    // document.querySelector('.table-section').scrollIntoView({ behavior: 'smooth' });
}

// ══════════════════════════════════════════════════════════
//  DETALLE MODAL
// ══════════════════════════════════════════════════════════
function verDetalle(id){
    const s = secciones.find(x => parseInt(x.id) === parseInt(id));
    if(!s) return;
    seccionActualId = id;

    document.getElementById('det-titulo').textContent   = s.nombre_seccion || '—';
    document.getElementById('det-ubicacion').textContent= [s.estado, s.municipio].filter(Boolean).join(' › ') || '—';
    document.getElementById('det-seccion').textContent  = s.nombre_seccion || '—';
    document.getElementById('det-municipio').textContent= s.municipio || '—';
    document.getElementById('det-df').textContent       = s.distrito_federal || '—';
    document.getElementById('det-dl').textContent       = s.distrito_local || '—';
    document.getElementById('det-region').textContent   = s.region_asignada || '—';
    document.getElementById('det-zona').textContent     = s.zona_asignada || '—';

    // Tabla nominal
    const total = parseInt(s.lista_nominal)||0;
    const e18 = parseInt(s.grupo_edad_18_24)||0;
    const e25 = parseInt(s.grupo_edad_25_34)||0;
    const e35 = parseInt(s.grupo_edad_35_44)||0;
    const e45 = parseInt(s.grupo_edad_45_54)||0;
    const e55 = parseInt(s.grupo_edad_55_mas)||0;
    document.getElementById('det-nominal-body').innerHTML = `
        <tr>
            <td><strong>${fmt(total)}</strong></td>
            <td>${fmt(e18)}</td>
            <td>${fmt(e25)}</td>
            <td>${fmt(e35)}</td>
            <td>${fmt(e45)}</td>
            <td>${fmt(e55)}</td>
        </tr>`;

    // Contactos y avance
    const meta = parseInt(s.meta_contactos)||0;
    const cons = parseInt(s.contactos_conseguidos)||0;
    const p    = pct(cons, meta);
    document.getElementById('det-meta-contactos').textContent = fmt(meta);
    if (document.getElementById('det-contactos')) document.getElementById('det-contactos').textContent = fmt(cons);
    document.getElementById('det-progress-fill').style.width  = p + '%';
    document.getElementById('det-progress-pct').textContent   = p + '%';
    document.getElementById('det-progress-rem').textContent   = fmt(Math.max(0, meta - cons)) + ' restantes';
    document.getElementById('det-eventos').textContent = fmt(s.eventos_realizados);
    document.getElementById('det-votos').textContent   = fmt(s.metas_votos);

    // Campos abiertos
    setFieldEditable('det-necesidades', s.principales_necesidades);
    setFieldEditable('det-enfoque',     s.enfoque);
    setFieldEditable('det-perfil',      s.perfil_socioeconomico);

    // Activaciones
    document.getElementById('det-act-total').textContent = fmt(s.activaciones_totales);
    document.getElementById('det-act-con').textContent   = fmt(s.con_candidato);
    document.getElementById('det-act-sin').textContent   = fmt(s.sin_candidato);

    // Botón editar
    document.getElementById('det-btn-edit').onclick = () => { cerrarModal('modal-det'); abrirModalEditar(id); };

    document.getElementById('modal-det').classList.add('open');
}

function setFieldEditable(divId, value){
    const div = document.getElementById(divId);
    div.innerHTML = `<span class="content-text">${value || '<em style="color:var(--text-muted)">Sin información</em>'}</span>
        <button class="edit-btn" onclick="activarEdicion('${divId.replace('det-','')}','${divId}')">Editar</button>`;
}

// ══════════════════════════════════════════════════════════
//  EDICIÓN INLINE
// ══════════════════════════════════════════════════════════
const fieldMap = {
    'necesidades': 'principales_necesidades',
    'enfoque': 'enfoque',
    'perfil_socioeconomico': 'perfil_socioeconomico',
};

function activarEdicion(field, divId){
    const div = document.getElementById(divId);
    const s = secciones.find(x => parseInt(x.id) === parseInt(seccionActualId));
    const dbField = fieldMap[field] || field;
    const actual = s ? (s[dbField] || '') : '';
    div.innerHTML = `
        <textarea class="inline-textarea" id="inline-ta-${field}">${actual}</textarea>
        <br>
        <button class="save-inline-btn" onclick="guardarInline('${field}','${divId}')">
            <i class="fas fa-check"></i> Guardar
        </button>
        <button class="cancel-inline-btn" onclick="setFieldEditable('${divId}', document.getElementById('inline-ta-${field}').defaultValue)">
            Cancelar
        </button>`;
}

async function guardarInline(field, divId){
    const ta = document.getElementById('inline-ta-' + field);
    const valor = ta.value;
    const dbField = fieldMap[field] || field;
    const fd = new FormData();
    fd.append('action', 'update_field');
    fd.append('id', seccionActualId);
    fd.append('field', dbField);
    fd.append('value', valor);
    try {
        const res  = await fetch(API, { method: 'POST', body: fd });
        const data = await res.json();
        if(!data.ok) throw new Error(data.msg || 'Error');
        const s = secciones.find(x => parseInt(x.id) === parseInt(seccionActualId));
        if(s) s[dbField] = valor;
        setFieldEditable(divId, valor);
    } catch(err){
        alert('Error al guardar: ' + err.message);
    }
}


function abrirModalEditar(id){
    const s = secciones.find(x => parseInt(x.id) === parseInt(id));
    if(!s) return;
    document.getElementById('form-titulo').textContent = 'Editar Sección: ' + s.nombre_seccion;
    document.getElementById('form-id').value = s.id;
    document.getElementById('f-nombre').value    = s.nombre_seccion || '';
    geoPreseleccionar(s.estado || '', s.municipio || '', s.nombre_seccion || '');
    document.getElementById('f-df').value        = s.distrito_federal || '';
    document.getElementById('f-dl').value        = s.distrito_local || '';
    document.getElementById('f-region').value    = s.region_asignada || '';
    document.getElementById('f-zona').value      = s.zona_asignada || '';
    document.getElementById('f-nominal').value   = s.lista_nominal || '';
    document.getElementById('f-edad-18').value   = s.grupo_edad_18_24 || '';
    document.getElementById('f-edad-25').value   = s.grupo_edad_25_34 || '';
    document.getElementById('f-edad-35').value   = s.grupo_edad_35_44 || '';
    document.getElementById('f-edad-45').value   = s.grupo_edad_45_54 || '';
    document.getElementById('f-edad-55').value   = s.grupo_edad_55_mas || '';
    document.getElementById('f-meta-cont').value = s.meta_contactos || '';
    document.getElementById('f-cont').value      = s.contactos_conseguidos || '';
    document.getElementById('f-eventos').value   = s.eventos_realizados || '';
    document.getElementById('f-votos').value     = s.metas_votos || '';
    document.getElementById('f-act-total').value = s.activaciones_totales || '';
    document.getElementById('f-act-con').value   = s.con_candidato || '';
    document.getElementById('f-act-sin').value   = s.sin_candidato || '';
    document.getElementById('f-necesidades').value = s.principales_necesidades || '';
    document.getElementById('f-enfoque').value   = s.enfoque || '';
    document.getElementById('f-perfil').value    = s.perfil_socioeconomico || '';
    document.getElementById('modal-form').classList.add('open');
}

async function guardarSeccion(){
    const btn = document.getElementById('form-save-btn');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando…';
    const id = document.getElementById('form-id').value;
    const fd = new FormData();
    fd.append('action', id ? 'update' : 'create');
    if(id) fd.append('id', id);
    const campos = {
        nombre_seccion: 'f-nombre', estado: 'f-estado', municipio: 'f-municipio',
        distrito_federal: 'f-df', distrito_local: 'f-dl',
        region_asignada: 'f-region', zona_asignada: 'f-zona',
        lista_nominal: 'f-nominal',
        grupo_edad_18_24: 'f-edad-18', grupo_edad_25_34: 'f-edad-25',
        grupo_edad_35_44: 'f-edad-35', grupo_edad_45_54: 'f-edad-45',
        grupo_edad_55_mas: 'f-edad-55',
        meta_contactos: 'f-meta-cont', contactos_conseguidos: 'f-cont',
        eventos_realizados: 'f-eventos', metas_votos: 'f-votos',
        activaciones_totales: 'f-act-total', con_candidato: 'f-act-con',
        sin_candidato: 'f-act-sin',
        principales_necesidades: 'f-necesidades', enfoque: 'f-enfoque',
        perfil_socioeconomico: 'f-perfil',
    };
    for(const [k,v] of Object.entries(campos)){
        const el = document.getElementById(v);
        if(el) fd.append(k, el.value);
    }
    try {
        const res  = await fetch(API, { method: 'POST', body: fd });
        const data = await res.json();
        if(!data.ok) throw new Error(data.msg || 'Error');
        if(id){
            const idx = secciones.findIndex(x => parseInt(x.id) === parseInt(id));
            if(idx >= 0) Object.assign(secciones[idx], data.seccion || {});
        } else {
            secciones.unshift(data.seccion || {});
        }
        cerrarModal('modal-form');
        renderTabla();
    } catch(err){
        alert('Error al guardar: ' + err.message);
    } finally {
        btn.innerHTML = '<i class="fas fa-save"></i> Guardar';
    }
}


// ══════════════════════════════════════════════════════════
//  MODALS
// ══════════════════════════════════════════════════════════
function cerrarModal(id){ document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal').forEach(el =>
    el.addEventListener('click', e => { if(e.target === el) cerrarModal(el.id); })
);

// ══════════════════════════════════════════════════════════
//  SIDEBAR PADDING SYNC
// ══════════════════════════════════════════════════════════
(function(){
    const container = document.getElementById('mainContainer');
    const sidebar   = document.getElementById('sidebar');
    if(!sidebar || !container) return;
    const obs = new MutationObserver(() => {
        container.style.paddingLeft = sidebar.classList.contains('collapsed') ? '110px' : '330px';
    });
    obs.observe(sidebar, { attributes: true, attributeFilter: ['class'] });
})();


// ══════════════════════════════════════════════════════════
//  GEO — Estados / Municipios / Secciones desde la DB
// ══════════════════════════════════════════════════════════
const GEO_CACHE   = {};    // { clave: { label, municipios[] } }
let   SEC_LISTA   = [];    // secciones cargadas del municipio actual
let   SEC_FOCUSED = -1;    // índice flechas teclado

// ── Inicializar: cargar estados + municipios ──────────────
async function geoInicializar() {
    try {
        const res  = await fetch(API + '?action=geo_data&type=municipios');
        const data = await res.json();
        if (!data.ok) { console.warn('geo_data error', data.msg); return; }
        Object.assign(GEO_CACHE, data.estados);

        // Filtro Estado (barra superior)
        const selFiltro = document.getElementById('filtroEstado');
        if (selFiltro) {
            selFiltro.innerHTML = '<option value="">Todos los estados</option>';
            Object.values(data.estados).forEach(ed => {
                const o = document.createElement('option');
                o.value = ed.label; o.textContent = ed.label;
                selFiltro.appendChild(o);
            });
        }
        // Select Estado (modal)
        const selEst = document.getElementById('f-estado');
        if (selEst) {
            selEst.innerHTML = '<option value="">— Selecciona estado —</option>';
            Object.values(data.estados).forEach(ed => {
                const o = document.createElement('option');
                o.value = ed.label; o.textContent = ed.label;
                selEst.appendChild(o);
            });
        }
    } catch(err) { console.warn('geoInicializar:', err); }
}

// ── Cargar municipios al cambiar estado ───────────────────
function geoCargarMunicipios() {
    const label  = document.getElementById('f-estado')?.value || '';
    const selMun = document.getElementById('f-municipio');
    if (!selMun) return;
    selMun.innerHTML = '<option value="">— Selecciona municipio —</option>';
    secReset('Selecciona municipio primero');
    if (!label) return;
    const entry = Object.values(GEO_CACHE).find(e => e.label === label);
    if (!entry) return;
    entry.municipios.forEach(m => {
        const o = document.createElement('option');
        o.value = m; o.textContent = m;
        selMun.appendChild(o);
    });
}

// ── Cargar secciones al cambiar municipio (lazy API) ──────
async function geoCargarSecciones() {
    const estadoLabel = document.getElementById('f-estado')?.value   || '';
    const municipio   = document.getElementById('f-municipio')?.value || '';

    if (!estadoLabel || !municipio) {
        secReset('Selecciona municipio primero'); return;
    }
    const clave = Object.entries(GEO_CACHE).find(([,v]) => v.label === estadoLabel)?.[0];
    if (!clave) return;

    secReset('Cargando secciones…', true);
    try {
        const res  = await fetch(`${API}?action=geo_secciones&estado=${encodeURIComponent(clave)}&municipio=${encodeURIComponent(municipio)}`);
        const data = await res.json();
        if (data.ok && data.secciones.length) {
            SEC_LISTA = data.secciones;
            secActualizarContador(SEC_LISTA.length);
            secRenderOpciones(SEC_LISTA);
            document.getElementById('sec-display').placeholder = `— Selecciona sección (${SEC_LISTA.length}) —`;
        } else {
            secReset('Sin secciones para este municipio');
        }
    } catch(err) {
        secReset('Error al cargar secciones');
        console.warn('geoCargarSecciones:', err);
    }
}

// ── Pre-seleccionar estado+municipio al editar ────────────
async function geoPreseleccionar(estadoLabel, municipioLabel, seccionVal) {
    const selEst = document.getElementById('f-estado');
    if (selEst) { selEst.value = estadoLabel || ''; geoCargarMunicipios(); }
    const selMun = document.getElementById('f-municipio');
    if (selMun && municipioLabel) {
        selMun.value = municipioLabel;
        await geoCargarSecciones();
    }
    if (seccionVal) secSeleccionar(seccionVal);
}

// ══════════════════════════════════════════════════════════
//  SEARCHABLE DROPDOWN — Sección
// ══════════════════════════════════════════════════════════

function secReset(msg = 'Selecciona estado y municipio primero', loading = false) {
    SEC_LISTA = [];
    SEC_FOCUSED = -1;
    document.getElementById('f-nombre').value     = '';
    document.getElementById('sec-display').value  = '';
    document.getElementById('sec-display').placeholder = '— ' + msg + ' —';
    document.getElementById('sec-search').value   = '';
    secActualizarContador(0);
    const opts = document.getElementById('sec-options');
    opts.innerHTML = loading
        ? `<div class="sec-dropdown-loading"><i class="fas fa-spinner fa-spin"></i> ${msg}</div>`
        : `<div class="sec-dropdown-empty">${msg}</div>`;
    secCerrar();
}

function secActualizarContador(n) {
    const el = document.getElementById('f-sec-contador');
    if (el) el.textContent = n > 0 ? `(${n} secciones)` : '';
}

function secRenderOpciones(lista, busqueda = '') {
    const opts = document.getElementById('sec-options');
    const actual = document.getElementById('f-nombre').value;
    SEC_FOCUSED = -1;
    if (!lista.length) {
        opts.innerHTML = `<div class="sec-dropdown-empty">Sin resultados para "${busqueda}"</div>`;
        return;
    }
    opts.innerHTML = lista.map(s =>
        `<div class="sec-dropdown-option${s === actual ? ' selected' : ''}"
              onmousedown="secSeleccionar('${s}')">${s}</div>`
    ).join('');
}

function secFiltrar(q) {
    if (!SEC_LISTA.length) return;
    const filtradas = q.trim()
        ? SEC_LISTA.filter(s => s.toString().includes(q.trim()))
        : SEC_LISTA;
    secRenderOpciones(filtradas, q);
}

function secSeleccionar(val) {
    document.getElementById('f-nombre').value    = val;
    document.getElementById('sec-display').value = val;
    document.getElementById('sec-display').placeholder = '';
    secCerrar();
    // Marcar selected
    document.querySelectorAll('.sec-dropdown-option').forEach(el => {
        el.classList.toggle('selected', el.textContent === val);
    });
}

function secToggle() {
    const wrap = document.getElementById('sec-dropdown-wrap');
    if (!SEC_LISTA.length) return;
    if (wrap.classList.contains('open')) { secCerrar(); }
    else {
        wrap.classList.add('open');
        const search = document.getElementById('sec-search');
        search.value = '';
        secRenderOpciones(SEC_LISTA);
        setTimeout(() => search.focus(), 50);
    }
}

function secCerrar() {
    document.getElementById('sec-dropdown-wrap')?.classList.remove('open');
}

function secNavegar(e) {
    const items = document.querySelectorAll('.sec-dropdown-option');
    if (!items.length) return;
    if (e.key === 'ArrowDown') {
        SEC_FOCUSED = Math.min(SEC_FOCUSED + 1, items.length - 1);
    } else if (e.key === 'ArrowUp') {
        SEC_FOCUSED = Math.max(SEC_FOCUSED - 1, 0);
    } else if (e.key === 'Enter' && SEC_FOCUSED >= 0) {
        e.preventDefault();
        secSeleccionar(items[SEC_FOCUSED].textContent);
        return;
    } else if (e.key === 'Escape') {
        secCerrar(); return;
    }
    items.forEach((el, i) => el.classList.toggle('focused', i === SEC_FOCUSED));
    if (SEC_FOCUSED >= 0) items[SEC_FOCUSED].scrollIntoView({ block: 'nearest' });
}

// Cerrar dropdown al hacer click fuera
document.addEventListener('click', e => {
    if (!e.target.closest('#sec-dropdown-wrap')) secCerrar();
});

// ── INIT ──
geoInicializar();
filtrarMunicipios();
</script>

</body>
</html>