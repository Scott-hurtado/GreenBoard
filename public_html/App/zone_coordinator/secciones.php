<?php
// ── Seguridad ──────────────────────────────────────────────
if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
} else { session_start(); }

$rol = $_SESSION['user_role'] ?? '';
$user_zona = $_SESSION['user_zona_nombre'] ?? 'Zona Asignada';
$user_estado = $_SESSION['user_estado'] ?? ''; 

// Permitir el acceso al coordinador de zona y roles superiores
$permitidos = ['coordinador_zona', 'coordinador_regional', 'coordinador_estatal', 'admin', 'superadmin'];
if (!in_array($rol, $permitidos)) { header('Location: /public/index.php'); exit(); }

// ── Conexión y carga de datos ──────────────────────────────
$secciones  = [];
$error_db = '';

try {
    // Reutilizar $pdo del bootstrap si ya existe, sino crear conexión
    if (!isset($pdo)) {
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
    }

    // ── Paso 1: secciones asignadas desde zonas_estatales ─────────────────────
    // Mismo JOIN que estructura_cz.php: coordinador_zona.zona → zonas_estatales.id
    // Esto garantiza exactamente las secciones de esta zona, sin duplicados.
    $nums_seccion = [];

    if (!empty($_SESSION['user_id'])) {
        $stmtZ = $pdo->prepare("
            SELECT z.secciones
            FROM coordinador_zona cz
            INNER JOIN zonas_estatales z ON cz.zona = z.id
            WHERE cz.id = ?
            LIMIT 1
        ");
        $stmtZ->execute([(int)$_SESSION['user_id']]);
        $zonaRow = $stmtZ->fetch();
        if ($zonaRow && !empty($zonaRow['secciones'])) {
            $nums_seccion = json_decode($zonaRow['secciones'], true) ?: [];
        }
    }

    // Fallback: por nombre de zona si el JOIN no devolvio nada
    if (empty($nums_seccion) && !empty($user_zona)) {
        $stmtZ = $pdo->prepare("
            SELECT secciones FROM zonas_estatales
            WHERE TRIM(nombre) = TRIM(?)
            LIMIT 1
        ");
        $stmtZ->execute([$user_zona]);
        $zonaRow = $stmtZ->fetch();
        if ($zonaRow && !empty($zonaRow['secciones'])) {
            $nums_seccion = json_decode($zonaRow['secciones'], true) ?: [];
        }
    }

    // ── Paso 2: query a secciones_electorales filtrando por ESTADO + numero ────
    // CRITICO: filtrar por estado evita traer la misma seccion de otros estados.
    // estructura_cz.php usa exactamente este mismo patron.
    if (!empty($nums_seccion) && !empty($user_estado)) {
        $placeholders = implode(',', array_fill(0, count($nums_seccion), '?'));
        $stmt = $pdo->prepare("
            SELECT
                id,
                nombre_seccion,
                estado,
                municipio,
                distrito_federal,
                distrito_local,
                COALESCE(lista_nominal,        0) AS lista_nominal,
                COALESCE(lista_hombres,        0) AS lista_hombres,
                COALESCE(lista_mujeres,        0) AS lista_mujeres,
                COALESCE(meta_contactos,       0) AS meta_contactos,
                COALESCE(contactos_conseguidos,0) AS contactos_conseguidos,
                COALESCE(eventos_realizados,   0) AS eventos_realizados,
                COALESCE(metas_votos,          0) AS metas_votos,
                COALESCE(region_asignada,      '') AS region_asignada,
                COALESCE(zona_asignada,        '') AS zona_asignada,
                COALESCE(principales_necesidades,'') AS principales_necesidades,
                COALESCE(enfoque,              '') AS enfoque,
                COALESCE(perfil_socioeconomico,'') AS perfil_socioeconomico,
                COALESCE(activaciones_totales, 0) AS activaciones_totales,
                COALESCE(con_candidato,        0) AS con_candidato,
                COALESCE(sin_candidato,        0) AS sin_candidato,
                COALESCE(grupo_edad_18_24,     0) AS grupo_edad_18_24,
                COALESCE(grupo_edad_25_34,     0) AS grupo_edad_25_34,
                COALESCE(grupo_edad_35_44,     0) AS grupo_edad_35_49,
                COALESCE(grupo_edad_45_54,     0) AS grupo_edad_50_64,
                COALESCE(grupo_edad_55_mas,    0) AS grupo_edad_65_mas
            FROM secciones_electorales
            WHERE estado = ?
              AND nombre_seccion IN ($placeholders)
              AND activo = 1
            ORDER BY CAST(nombre_seccion AS UNSIGNED) ASC
        ");
        // Primero el estado, luego los numeros de seccion (igual que estructura_cz.php)
        $stmt->execute(array_merge([$user_estado], $nums_seccion));
        $secciones = $stmt->fetchAll();
    }
    // Si $nums_seccion o $user_estado estan vacios, $secciones queda [] -> Sin resultados.

} catch (PDOException $e) {
    $error_db = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secciones de Zona - GreenBoard</title>
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

    /* ── PAGINACIÓN ── */
    .pagination {
        display: flex; justify-content: center; align-items: center;
        gap: 6px; margin-top: 25px; flex-wrap: wrap;
    }
    .page-btn {
        background: var(--white); border: 1px solid var(--gray-200);
        color: var(--dark); padding: 8px 14px; border-radius: 8px;
        font-size: 14px; font-weight: 600; cursor: pointer;
        transition: all 0.2s; display: flex; align-items: center; justify-content: center;
    }
    .page-btn:hover:not(.disabled) { background: var(--gray-100); border-color: var(--primary); color: var(--primary); }
    .page-btn.active { background: var(--primary); color: var(--white); border-color: var(--primary); }
    .page-btn.disabled { opacity: 0.5; cursor: not-allowed; }
    .page-dots { color: var(--text-muted); font-weight: 600; padding: 0 5px; }

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
    </style>
</head>
<body>

<?php if (file_exists(__DIR__ . '/../../includes/components/sidebar_cz.php')) include __DIR__ . '/../../includes/components/sidebar_cz.php'; ?>

<div class="container" id="mainContainer">
    <div class="design-text">SECCIONES ELECTORALES</div>

    <div class="page-header">
        <div>
            <div class="ai-badge"><i class="fas fa-layer-group"></i> &nbsp; ZONA: <?= htmlspecialchars($user_zona) ?></div>
            <h1 class="main-title">Secciones</h1>
            <p class="description">Gestión y seguimiento por sección electoral asignada a tu zona.</p>
        </div>
    </div>

    <div class="stats-row" id="statsRow">
        <div class="stat-card">
            <div class="stat-icon verde"><i class="fas fa-layer-group"></i></div>
            <div>
                <div class="stat-label">Secciones en Zona</div>
                <div class="stat-val" id="stat-total">—</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon amarillo"><i class="fas fa-users"></i></div>
            <div>
                <div class="stat-label">Contactos Conseguidos</div>
                <div class="stat-val" id="stat-contactos">—</div>
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

    <div class="filters-bar">
        <div class="filter-group">
            <label class="form-label">Buscar</label>
            <input type="text" class="form-input-styled" id="filtroBuscar" placeholder="Nombre de sección…" oninput="aplicarFiltros()">
        </div>
        <button class="btn-secondary" onclick="limpiarFiltros()">
            <i class="fas fa-times"></i> Limpiar
        </button>
    </div>

    <div class="table-section">
        <div class="section-header">
            <div class="section-title">Secciones de la Zona</div>
            <div id="tableCount" style="font-size:13px; color:var(--text-muted); font-weight:600;"></div>
        </div>
        <div style="overflow-x:auto;">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Sección</th>
                        <th>Lista Nominal</th>
                        <th>Meta de Contactos</th>
                        <th>Contactos Conseguidos</th>
                        <th>Barra de Avance</th>
                        <th>Eventos Realizados</th>
                        <th>Metas de Votos</th>
                        <th>Detalles</th>
                    </tr>
                </thead>
                <tbody id="tablaBody">
                    <tr class="empty-row"><td colspan="8"><i class="fas fa-spinner fa-spin"></i> Cargando…</td></tr>
                </tbody>
            </table>
        </div>
        <div class="pagination" id="pagination-container"></div>
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
        </div>

        <div class="det-section-title"><i class="fas fa-users"></i> Lista Nominal</div>

        <div class="det-grid" style="margin-bottom:16px;">
            <div class="det-item">
                <label><i class="fas fa-mars" style="color:#3b82f6;margin-right:4px;"></i> Hombres</label>
                <span id="det-lista-hombres" style="font-size:22px;font-weight:900;color:#3b82f6;">—</span>
            </div>
            <div class="det-item">
                <label><i class="fas fa-venus" style="color:#ec4899;margin-right:4px;"></i> Mujeres</label>
                <span id="det-lista-mujeres" style="font-size:22px;font-weight:900;color:#ec4899;">—</span>
            </div>
        </div>

        <div class="nominal-table-wrap">
            <table class="edad-table" id="det-tabla-nominal">
                <thead>
                    <tr>
                        <th>Total</th>
                        <th>18–24</th>
                        <th>25–34</th>
                        <th>35–49</th>
                        <th>50–64</th>
                        <th>65+</th>
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
            <div class="det-item">
                <label>Contactos Conseguidos</label>
                <span id="det-contactos">—</span>
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

        <div class="det-section-title"><i class="fas fa-edit"></i> Información Estratégica</div>
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

        <div style="display:flex; gap:10px; margin-top:30px; flex-wrap:wrap; justify-content: flex-end;">
            <button class="btn-secondary" onclick="cerrarModal('modal-det')">
                <i class="fas fa-times"></i> Cerrar Ficha
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

// Variables de Paginación
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
//  FILTROS Y PAGINACIÓN
// ══════════════════════════════════════════════════════════
function aplicarFiltros() {
    currentPage = 1;
    renderTabla();
}

function limpiarFiltros(){
    document.getElementById('filtroBuscar').value = '';
    aplicarFiltros(); 
}

function seccionesFiltradas(){
    const buscar = document.getElementById('filtroBuscar').value.toLowerCase();
    return secciones.filter(s => {
        return !buscar || (s.nombre_seccion||'').toLowerCase().includes(buscar);
    });
}

// ══════════════════════════════════════════════════════════
//  RENDER TABLA CON PAGINACIÓN
// ══════════════════════════════════════════════════════════
function renderTabla(){
    const allData = seccionesFiltradas();
    const tbody = document.getElementById('tablaBody');
    document.getElementById('tableCount').textContent = allData.length + ' sección(es)';

    // Stats calculados sobre TODO el filtro, no solo la página actual
    const totalContactos   = allData.reduce((a,s) => a + (parseInt(s.contactos_conseguidos)||0), 0);
    const totalActivaciones= allData.reduce((a,s) => a + (parseInt(s.activaciones_totales)||0), 0);
    document.getElementById('stat-total').textContent        = allData.length;
    document.getElementById('stat-contactos').textContent   = fmt(totalContactos);
    document.getElementById('stat-activaciones').textContent= fmt(totalActivaciones);

    if(!allData.length){
        tbody.innerHTML = '<tr class="empty-row"><td colspan="8"><i class="fas fa-search"></i> Sin resultados</td></tr>';
        renderPaginacion(0);
        return;
    }

    // Lógica de Paginación para extraer solo 50 elementos
    const startIndex = (currentPage - 1) * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;
    const dataOnPage = allData.slice(startIndex, endIndex);

    tbody.innerHTML = dataOnPage.map(s => {
        const meta = parseInt(s.meta_contactos)||0;
        const cons = parseInt(s.contactos_conseguidos)||0;
        const p    = pct(cons, meta);

        return `<tr>
            <td><strong style="font-size: 16px; color: var(--primary);">${s.nombre_seccion||'—'}</strong></td>
            <td>${fmt(s.lista_nominal)}</td>
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

    renderPaginacion(allData.length);
}

function renderPaginacion(totalItems) {
    const container = document.getElementById('pagination-container');
    if (totalItems <= itemsPerPage) {
        container.innerHTML = ''; 
        return;
    }

    const totalPages = Math.ceil(totalItems / itemsPerPage);
    let html = '';

    // Botón Anterior
    html += `<button class="page-btn ${currentPage === 1 ? 'disabled' : ''}" 
             onclick="${currentPage === 1 ? '' : `cambiarPagina(${currentPage - 1})`}"><i class="fas fa-chevron-left"></i></button>`;

    // Números de Página (Lógica para mostrar ventana corta y elipsis)
    let startPage = Math.max(1, currentPage - 2);
    let endPage = Math.min(totalPages, currentPage + 2);

    if (startPage > 1) {
        html += `<button class="page-btn" onclick="cambiarPagina(1)">1</button>`;
        if (startPage > 2) html += `<span class="page-dots">...</span>`;
    }

    for (let i = startPage; i <= endPage; i++) {
        html += `<button class="page-btn ${i === currentPage ? 'active' : ''}" onclick="cambiarPagina(${i})">${i}</button>`;
    }

    if (endPage < totalPages) {
        if (endPage < totalPages - 1) html += `<span class="page-dots">...</span>`;
        html += `<button class="page-btn" onclick="cambiarPagina(${totalPages})">${totalPages}</button>`;
    }

    // Botón Siguiente
    html += `<button class="page-btn ${currentPage === totalPages ? 'disabled' : ''}" 
             onclick="${currentPage === totalPages ? '' : `cambiarPagina(${currentPage + 1})`}"><i class="fas fa-chevron-right"></i></button>`;

    container.innerHTML = html;
}

function cambiarPagina(page) {
    const totalItems = seccionesFiltradas().length;
    const totalPages = Math.ceil(totalItems / itemsPerPage);
    if (page < 1 || page > totalPages) return;
    
    currentPage = page;
    renderTabla();
    
    document.querySelector('.table-section').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// ══════════════════════════════════════════════════════════
//  DETALLE MODAL
// ══════════════════════════════════════════════════════════
function verDetalle(id){
    const s = secciones.find(x => parseInt(x.id) === parseInt(id));
    if(!s) return;
    seccionActualId = id;

    document.getElementById('det-titulo').textContent   = "Sección " + (s.nombre_seccion || '—');
    document.getElementById('det-ubicacion').textContent= [s.estado, s.municipio].filter(Boolean).join(' › ') || '—';
    document.getElementById('det-seccion').textContent  = s.nombre_seccion || '—';
    document.getElementById('det-municipio').textContent= s.municipio || '—';
    document.getElementById('det-df').textContent       = s.distrito_federal || '—';
    document.getElementById('det-dl').textContent       = s.distrito_local || '—';

    // Hombres y Mujeres
    document.getElementById('det-lista-hombres').textContent = fmt(parseInt(s.lista_hombres)||0);
    document.getElementById('det-lista-mujeres').textContent = fmt(parseInt(s.lista_mujeres)||0);

    // Tabla nominal
    const total = parseInt(s.lista_nominal)||0;
    const e18 = parseInt(s.grupo_edad_18_24)||0;
    const e25 = parseInt(s.grupo_edad_25_34)||0;
    const e35 = parseInt(s.grupo_edad_35_49)||0;
    const e45 = parseInt(s.grupo_edad_50_64)||0;
    const e55 = parseInt(s.grupo_edad_65_mas)||0;
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
    document.getElementById('det-contactos').textContent      = fmt(cons);
    document.getElementById('det-progress-fill').style.width  = p + '%';
    document.getElementById('det-progress-pct').textContent   = p + '%';
    document.getElementById('det-progress-rem').textContent   = fmt(Math.max(0, meta - cons)) + ' restantes';
    document.getElementById('det-eventos').textContent = fmt(s.eventos_realizados);
    document.getElementById('det-votos').textContent   = fmt(s.metas_votos);

    // Campos abiertos (Permite a los coordinadores guardar apuntes estratégicos de sus secciones)
    setFieldEditable('det-necesidades', s.principales_necesidades);
    setFieldEditable('det-enfoque',     s.enfoque);
    setFieldEditable('det-perfil',      s.perfil_socioeconomico);

    // Activaciones
    document.getElementById('det-act-total').textContent = fmt(s.activaciones_totales);
    document.getElementById('det-act-con').textContent   = fmt(s.con_candidato);
    document.getElementById('det-act-sin').textContent   = fmt(s.sin_candidato);

    document.getElementById('modal-det').classList.add('open');
}

function setFieldEditable(divId, value){
    const div = document.getElementById(divId);
    div.innerHTML = `<span class="content-text">${value || '<em style="color:var(--text-muted)">Sin información</em>'}</span>
        <button class="edit-btn" onclick="activarEdicion('${divId.replace('det-','')}','${divId}')">Anotar</button>`;
}

// ══════════════════════════════════════════════════════════
//  EDICIÓN INLINE PARA CAMPOS ABIERTOS
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

// ══════════════════════════════════════════════════════════
//  MODALS
// ══════════════════════════════════════════════════════════
function cerrarModal(id){ document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal').forEach(el =>
    el.addEventListener('click', e => { if(e.target === el) cerrarModal(el.id); })
);

// ── INIT ──
renderTabla();       
</script>

</body>
</html>